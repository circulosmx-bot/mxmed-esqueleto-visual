<?php
declare(strict_types=1);

namespace Agenda\Controllers;

use Agenda\Helpers as DbHelpers;
use Agenda\Repositories\WaitlistRepository;
use Agenda\Repositories\AppointmentWriteRepository;
use DateTimeImmutable;
use DateTimeZone;
use PDOException;
use RuntimeException;
use Throwable;

require_once __DIR__ . '/../repositories/WaitlistRepository.php';
require_once __DIR__ . '/../repositories/AppointmentWriteRepository.php';
require_once __DIR__ . '/../../../api/_lib/db.php';

class WaitlistController
{
    private ?WaitlistRepository $repository = null;
    private ?string $dbError = null;
    private ?\PDO $pdo = null;
    private bool $qaNotReady = false;

    public function __construct()
    {
        $this->qaNotReady = DbHelpers\isQaModeNotReady();
        if ($this->qaNotReady) {
            return;
        }
        try {
            $pdo = mxmed_pdo();
            $this->pdo = $pdo;
        } catch (RuntimeException $e) {
            $this->dbError = $e->getMessage();
            return;
        } catch (Throwable $e) {
            $this->dbError = 'database error';
            return;
        }
        try {
            $this->repository = new WaitlistRepository($this->pdo);
        } catch (RuntimeException $e) {
            $this->dbError = $e->getMessage();
        }
    }

    public function index(array $params = []): array
    {
        $notReady = $this->ensureReady();
        if ($notReady) {
            return $notReady;
        }

        $status = $params['status'] ?? 'active';
        $entries = $this->repository->listEntries([
            'doctor_id' => $params['doctor_id'] ?? null,
            'consultorio_id' => $params['consultorio_id'] ?? null,
            'status' => $status,
        ]);

        return $this->success($entries, ['count' => count($entries), 'status' => $status]);
    }

    public function store(): array
    {
        $notReady = $this->ensureReady();
        if ($notReady) {
            return $notReady;
        }

        $payload = $this->getPayload();
        $errors = $this->validateCreate($payload);
        if (!empty($errors)) {
            return $this->error('invalid_params', 'invalid payload for waitlist', $errors);
        }

        try {
            $entry = $this->repository->createEntry([
                'doctor_id' => $payload['doctor_id'],
                'consultorio_id' => $payload['consultorio_id'],
                'patient_id' => $payload['patient_id'] ?? null,
                'patient_name' => $payload['patient_name'] ?? null,
                'patient_phone' => $payload['patient_phone'] ?? null,
                'notes' => $payload['notes'] ?? null,
            ]);
        } catch (RuntimeException $e) {
            return $this->error('db_not_ready', $e->getMessage());
        } catch (Throwable $e) {
            return $this->error('db_error', 'database error');
        }

        return $this->success($entry, ['write' => 'create']);
    }

    public function update(string $id): array
    {
        $notReady = $this->ensureReady();
        if ($notReady) {
            return $notReady;
        }

        $id = trim($id);
        if ($id === '') {
            return $this->error('invalid_params', 'waitlist id is required', ['id' => $id]);
        }

        $payload = $this->getPayload();
        $errors = $this->validateStatus($payload);
        if (!empty($errors)) {
            return $this->error('invalid_params', 'invalid payload for waitlist update', $errors);
        }

        try {
            $entry = $this->repository->updateStatus($id, strtolower((string)$payload['status']));
        } catch (RuntimeException $e) {
            return $this->error('not_found', 'waitlist entry not found');
        } catch (Throwable $e) {
            return $this->error('db_error', 'database error');
        }

        return $this->success($entry, ['write' => 'status']);
    }

    public function assign(string $id): array
    {
        $notReady = $this->ensureReady();
        if ($notReady) {
            return $notReady;
        }

        $id = trim($id);
        if ($id === '') {
            return $this->error('invalid_params', 'waitlist id is required', ['id' => $id]);
        }

        $entry = $this->repository->getById($id);
        if (!$entry) {
            return $this->error('not_found', 'waitlist entry not found');
        }

        if (!in_array($entry['status'], ['active', 'contacted', 'accepted'], true)) {
            return $this->error('invalid_params', 'waitlist entry is not eligible for assign', ['status' => $entry['status']]);
        }

        $payload = $this->getPayload();
        $errors = $this->validateAssign($payload);
        if (!empty($errors)) {
            return $this->error('invalid_params', 'invalid payload for waitlist assign', $errors);
        }

        if ((string)$payload['doctor_id'] !== (string)$entry['doctor_id'] || (string)$payload['consultorio_id'] !== (string)$entry['consultorio_id']) {
            return $this->error('invalid_params', 'doctor or consultorio mismatch', [
                'entry_doctor_id' => $entry['doctor_id'],
                'entry_consultorio_id' => $entry['consultorio_id'],
            ]);
        }

        try {
            $patientId = $this->resolvePatientId($entry);
        } catch (RuntimeException $e) {
            return $this->error('db_not_ready', $e->getMessage());
        }
        if (!$patientId) {
            return $this->error('invalid_params', 'patient information required');
        }

        try {
            $writeRepo = new AppointmentWriteRepository($this->pdo);
        } catch (RuntimeException $e) {
            return $this->error('db_not_ready', $e->getMessage());
        }

        $appointmentPayload = [
            'doctor_id' => $payload['doctor_id'],
            'consultorio_id' => $payload['consultorio_id'],
            'start_at' => $payload['start_at'],
            'end_at' => $payload['end_at'],
            'slot_minutes' => $payload['slot_minutes'],
            'modality' => $payload['modality'] ?? 'waitlist',
            'patient_id' => $patientId,
            'channel_origin' => $payload['channel_origin'],
            'created_by_role' => $payload['actor_role'],
            'created_by_id' => $payload['actor_id'],
        ];

        try {
            $result = $writeRepo->createAppointment($appointmentPayload);
        } catch (RuntimeException $e) {
            $message = $e->getMessage();
            if ($message === 'appointments table not ready' || $message === 'appointment events not ready') {
                return $this->error('db_not_ready', $message, $this->qaDebugMeta($e));
            }
            return $this->error('db_error', 'database error', $this->qaDebugMeta($e));
        } catch (PDOException $e) {
            $mapped = $this->mapPdoExceptionToDomainError($e);
            if ($mapped) {
                return $this->error($mapped['error'], $mapped['message'], $mapped['meta']);
            }
            return $this->error('db_error', 'database error', $this->qaDebugMeta($e));
        }

        $eventsMeta = ['events_appended' => 0];
        try {
            $eventId = $writeRepo->appendWaitlistAssignmentEvent($result['appointment_id'], $payload, $entry);
            $eventsMeta['events_appended'] = 1;
            $eventsMeta['event_id'] = $eventId;
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'appointment events not ready') {
                $eventsMeta['events_appended'] = 0;
            } else {
                return $this->error('db_error', 'database error', $this->qaDebugMeta($e));
            }
        } catch (PDOException $e) {
            return $this->error('db_error', 'database error', $this->qaDebugMeta($e));
        }

        try {
            $entry = $this->repository->updateStatus($id, 'confirmed');
        } catch (RuntimeException $e) {
            return $this->error('db_error', 'database error');
        }

        $response = [
            'entry' => $entry,
            'appointment_id' => $result['appointment_id'],
        ];

        return $this->success($response, array_merge(['write' => 'assign'], $eventsMeta));
    }

    private function validateCreate(array $payload): array
    {
        $errors = [];
        if (trim((string)($payload['doctor_id'] ?? '')) === '') {
            $errors['doctor_id'] = 'required';
        }
        if (trim((string)($payload['consultorio_id'] ?? '')) === '') {
            $errors['consultorio_id'] = 'required';
        }
        $hasPatientId = trim((string)($payload['patient_id'] ?? '')) !== '';
        $hasName = trim((string)($payload['patient_name'] ?? '')) !== '';
        $hasPhone = trim((string)($payload['patient_phone'] ?? '')) !== '';
        if (!$hasPatientId && !($hasName && $hasPhone)) {
            $errors['patient'] = 'patient_id or patient_name + patient_phone required';
        }
        return $errors;
    }

    private function validateStatus(array $payload): array
    {
        $errors = [];
        $status = $payload['status'] ?? '';
        $allowed = ['contacted', 'accepted', 'declined', 'removed'];
        if (!in_array($status, $allowed, true)) {
            $errors['status'] = 'invalid';
        }
        return $errors;
    }

    private function validateAssign(array $payload): array
    {
        $errors = [];
        $required = ['doctor_id', 'consultorio_id', 'start_at', 'end_at', 'slot_minutes', 'actor_role', 'actor_id', 'channel_origin'];
        foreach ($required as $field) {
            if (trim((string)($payload[$field] ?? '')) === '') {
                $errors[$field] = 'required';
            }
        }
        if (!empty($payload['start_at']) && !empty($payload['end_at']) && !$this->isValidRange($payload['start_at'], $payload['end_at'])) {
            $errors['time_range'] = 'start_at must be before end_at';
        }
        $slot = $this->normalizeSlotMinutes($payload['slot_minutes'] ?? null);
        if ($slot === null) {
            $errors['slot_minutes'] = 'must be between 5 and 720';
        }
        $role = trim((string)($payload['actor_role'] ?? ''));
        if (!in_array($role, ['operator', 'doctor', 'patient', 'system'], true)) {
            $errors['actor_role'] = 'invalid';
        }
        return $errors;
    }

    private function ensureReady(): ?array
    {
        if ($this->qaNotReady || $this->dbError) {
            $message = $this->dbError ?? 'waitlist table not ready';
            return $this->error('db_not_ready', $message);
        }
        if (!$this->repository) {
            return $this->error('db_error', 'database error');
        }
        return null;
    }

    private function resolvePatientId(array $entry): ?string
    {
        if (!empty($entry['patient_id'])) {
            return (string)$entry['patient_id'];
        }
        $name = trim((string)($entry['patient_name'] ?? ''));
        $phone = trim((string)($entry['patient_phone'] ?? ''));
        if ($name === '' || $phone === '') {
            return null;
        }

        require_once __DIR__ . '/../helpers/patients_client.php';
        $patientPayload = [
            'display_name' => $name,
            'contacts' => [
                [
                    'type' => 'phone',
                    'value' => $phone,
                ],
            ],
            'doctor_id' => $entry['doctor_id'],
        ];

        $response = agenda_patients_create($patientPayload);
        if (!$response['ok']) {
            if (in_array($response['error'] ?? '', ['db_not_ready', 'db_error'], true)) {
                throw new RuntimeException($response['message'] ?? 'patients db not ready');
            }
            return null;
        }

        if (empty($response['data']['patient_id'])) {
            return null;
        }
        return (string)$response['data']['patient_id'];
    }

    private function getPayload(): array
    {
        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw ?: '', true);
        return is_array($decoded) ? $decoded : [];
    }

    private function getQaMode(): string
    {
        $headers = function_exists('getallheaders') ? (array)getallheaders() : [];
        $qa = $headers['X-QA-Mode'] ?? $headers['x-qa-mode'] ?? null;
        if (is_string($qa) && $qa !== '') {
            return $qa;
        }
        $env = getenv('QA_MODE');
        return is_string($env) ? $env : '';
    }

    private function success(array $data, array $meta = []): array
    {
        $meta['qa_mode_seen'] = $this->getQaMode();
        return [
            'ok' => true,
            'error' => null,
            'message' => '',
            'data' => $data,
            'meta' => empty($meta) ? (object)[] : (object)$meta,
        ];
    }

    private function error(string $code, string $message, array $meta = []): array
    {
        $meta['qa_mode_seen'] = $this->getQaMode();
        return [
            'ok' => false,
            'error' => $code,
            'message' => $message,
            'data' => null,
            'meta' => empty($meta) ? (object)[] : (object)$meta,
        ];
    }

    private function normalizeSlotMinutes($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $minutes = (int)$value;
        if ($minutes < 5 || $minutes > 720) {
            return null;
        }
        return $minutes;
    }

    private function isValidRange(string $start, string $end): bool
    {
        $startDt = $this->parseDatetime($start);
        $endDt = $this->parseDatetime($end);
        if (!$startDt || !$endDt) {
            return false;
        }
        return $startDt < $endDt;
    }

    private function parseDatetime(string $value): ?DateTimeImmutable
    {
        return DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, new DateTimeZone('America/Mexico_City'));
    }

    private function qaDebugMeta(?Throwable $e): array
    {
        $qa = $this->getQaMode();
        if ($qa !== 'ready' || !$e) {
            return [];
        }
        return ['debug_exception' => get_class($e), 'debug_message' => $e->getMessage()];
    }

    private function mapPdoExceptionToDomainError(PDOException $e): ?array
    {
        $sqlState = (string)$e->getCode();
        $msg = strtolower($e->getMessage());
        if ($sqlState === '23000' || str_contains($msg, 'duplicate') || str_contains($msg, 'unique')) {
            $meta = ['reason' => 'collision_db_constraint'];
            $meta = array_merge($meta, $this->qaDebugMeta($e));
            return [
                'error' => 'collision',
                'message' => 'collision detected',
                'meta' => $meta,
            ];
        }
        return null;
    }
}
