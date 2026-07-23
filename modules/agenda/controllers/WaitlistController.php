<?php
declare(strict_types=1);

namespace Agenda\Controllers;

use Agenda\Adapters\CanonicalScheduleReadAdapter;
use Agenda\Helpers as DbHelpers;
use Agenda\Repositories\WaitlistRepository;
use Agenda\Repositories\AppointmentWriteRepository;
use DateTimeImmutable;
use DateTimeZone;
use PDOException;
use RuntimeException;
use Throwable;

require_once __DIR__ . '/../adapters/CanonicalScheduleReadAdapter.php';
require_once __DIR__ . '/../repositories/WaitlistRepository.php';
require_once __DIR__ . '/../repositories/AppointmentWriteRepository.php';
require_once __DIR__ . '/../../../api/_lib/db.php';

class WaitlistController
{
    private const ANY_CONSULTORIO_ID = '__all__';
    private const CONSULTORIO_SCOPE_ALL = 'all';
    private const CONSULTORIO_SCOPE_SINGLE = 'single';
    private ?WaitlistRepository $repository = null;
    private ?string $dbError = null;
    private ?\PDO $pdo = null;
    private bool $qaNotReady = false;
    private array $actorContext = [];
    private array $contextWarnings = [];

    public function __construct()
    {
        $config = require __DIR__ . '/../config/agenda.php';
        $canonicalScheduleReadAdapterClass = CanonicalScheduleReadAdapter::canonicalScheduleReadEnabled($config)
            ? CanonicalScheduleReadAdapter::class
            : null;
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

    public function setActorContext(array $context = []): void
    {
        $this->actorContext = $context;
    }

    public function index(array $params = []): array
    {
        $this->contextWarnings = [];
        $notReady = $this->ensureReady();
        if ($notReady) {
            return $notReady;
        }

        $doctorIdRequested = trim((string)($params['doctor_id'] ?? ''));
        $doctorScope = $this->resolveDoctorScope($doctorIdRequested, false);
        if (!$doctorScope['ok']) {
            return $this->error((string)$doctorScope['error'], (string)$doctorScope['message'], (array)($doctorScope['meta'] ?? []));
        }
        $doctorId = $doctorScope['doctor_id'] ?? null;
        if (is_string($doctorId) && $doctorId === '') {
            $doctorId = null;
        }

        $status = $params['status'] ?? 'active';
        $entries = $this->repository->listEntries([
            'doctor_id' => $doctorId,
            'consultorio_id' => $params['consultorio_id'] ?? null,
            'status' => $status,
        ]);

        return $this->success($entries, [
            'count' => count($entries),
            'status' => $status,
            'doctor_id_effective' => $doctorId,
            'doctor_id_requested' => ($doctorIdRequested !== '' ? $doctorIdRequested : null),
        ]);
    }

    public function store(): array
    {
        $this->contextWarnings = [];
        $notReady = $this->ensureReady();
        if ($notReady) {
            return $notReady;
        }

        $payload = $this->getPayload();
        $doctorIdRequested = trim((string)($payload['doctor_id'] ?? ''));
        $doctorScope = $this->resolveDoctorScope($doctorIdRequested, true);
        if (!$doctorScope['ok']) {
            return $this->error((string)$doctorScope['error'], (string)$doctorScope['message'], (array)($doctorScope['meta'] ?? []));
        }
        $payload['doctor_id'] = (string)$doctorScope['doctor_id'];
        $consultorioId = trim((string)($payload['consultorio_id'] ?? ''));
        $consultorioScope = $this->resolveConsultorioScope($payload['consultorio_scope'] ?? null, $consultorioId);
        if ($consultorioScope === null) {
            return $this->error('invalid_params', 'invalid payload for waitlist', [
                'consultorio_scope' => 'must be all or single',
            ]);
        }
        if ($consultorioScope === self::CONSULTORIO_SCOPE_ALL) {
            $payload['consultorio_id'] = self::ANY_CONSULTORIO_ID;
        }
        $payload['consultorio_scope'] = $consultorioScope;
        $errors = $this->validateCreate($payload);
        if (!empty($errors)) {
            return $this->error('invalid_params', 'invalid payload for waitlist', $errors);
        }

        try {
            $entry = $this->repository->createEntry([
                'doctor_id' => $payload['doctor_id'],
                'consultorio_id' => $payload['consultorio_id'],
                'consultorio_scope' => $payload['consultorio_scope'],
                'patient_id' => $payload['patient_id'] ?? null,
                'patient_name' => $payload['patient_name'] ?? null,
                'patient_phone' => $payload['patient_phone'] ?? null,
                'notes' => $payload['notes'] ?? null,
            ], $this->resolveWaitlistActorAuditPayload($payload, 'waitlist_created', null));
        } catch (RuntimeException $e) {
            return $this->error('db_not_ready', $e->getMessage());
        } catch (Throwable $e) {
            return $this->error('db_error', 'database error');
        }

        return $this->success($entry, [
            'write' => 'create',
            'doctor_id_effective' => $payload['doctor_id'],
            'doctor_id_requested' => ($doctorIdRequested !== '' ? $doctorIdRequested : null),
        ]);
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
            $entry = $this->repository->updateStatus(
                $id,
                strtolower((string)$payload['status']),
                $this->resolveWaitlistActorAuditPayload($payload, 'waitlist_updated', $id)
            );
        } catch (RuntimeException $e) {
            return $this->error('not_found', 'waitlist entry not found');
        } catch (Throwable $e) {
            return $this->error('db_error', 'database error');
        }

        return $this->success($entry, ['write' => 'status']);
    }

    public function assign(string $id): array
    {
        $this->contextWarnings = [];
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

        $entryDoctorId = trim((string)($entry['doctor_id'] ?? ''));
        $entryScope = $this->assertEntryDoctorScope($entryDoctorId, $id);
        if (!$entryScope['ok']) {
            return $this->error((string)$entryScope['error'], (string)$entryScope['message'], (array)($entryScope['meta'] ?? []));
        }

        if (!in_array($entry['status'], ['active', 'contacted', 'accepted'], true)) {
            return $this->error('invalid_params', 'waitlist entry is not eligible for assign', ['status' => $entry['status']]);
        }

        $payload = $this->getPayload();
        $doctorIdRequested = trim((string)($payload['doctor_id'] ?? ''));
        $doctorScope = $this->resolveDoctorScope($doctorIdRequested, true);
        if (!$doctorScope['ok']) {
            return $this->error((string)$doctorScope['error'], (string)$doctorScope['message'], (array)($doctorScope['meta'] ?? []));
        }
        $payload['doctor_id'] = (string)$doctorScope['doctor_id'];
        $assignAudit = $this->resolveWaitlistActorAuditPayload($payload, 'waitlist_assigned', $id);
        $payload['actor_role'] = $assignAudit['actor_role'] ?? ($payload['actor_role'] ?? null);
        $payload['actor_id'] = $assignAudit['actor_id'] ?? ($payload['actor_id'] ?? null);
        $payload['actor_display_name'] = $assignAudit['actor_display_name'] ?? ($payload['actor_display_name'] ?? null);
        $payload['channel_origin'] = $assignAudit['channel_origin'] ?? ($payload['channel_origin'] ?? null);
        $payload['created_by_role'] = $assignAudit['created_by_role'] ?? ($payload['created_by_role'] ?? null);
        $payload['created_by_id'] = $assignAudit['created_by_id'] ?? ($payload['created_by_id'] ?? null);
        $payload['action'] = $assignAudit['action'] ?? ($payload['action'] ?? null);
        $payload['entity_type'] = $assignAudit['entity_type'] ?? ($payload['entity_type'] ?? null);
        $payload['entity_id'] = $assignAudit['entity_id'] ?? ($payload['entity_id'] ?? null);
        $payload['occurred_at'] = $assignAudit['occurred_at'] ?? ($payload['occurred_at'] ?? null);
        $payload['metadata'] = array_merge(
            is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [],
            is_array($assignAudit['metadata'] ?? null) ? $assignAudit['metadata'] : []
        );
        $errors = $this->validateAssign($payload);
        if (!empty($errors)) {
            return $this->error('invalid_params', 'invalid payload for waitlist assign', $errors);
        }
        if (trim((string)($payload['consultorio_id'] ?? '')) === self::ANY_CONSULTORIO_ID) {
            return $this->error(
                'invalid_consultorio_id',
                'consultorio_id must be a real consultorio for assignment',
                [
                    'consultorio_id' => self::ANY_CONSULTORIO_ID,
                    'allowed' => 'real_consultorio_id',
                ]
            );
        }

        if ((string)$payload['doctor_id'] !== (string)$entry['doctor_id']) {
            $strictMode = ($this->actorContext['strict'] ?? false) === true;
            if ($strictMode) {
                return $this->error('forbidden', 'doctor scope mismatch', [
                    'entry_doctor_id' => $entry['doctor_id'],
                    'payload_doctor_id' => $payload['doctor_id'],
                ]);
            }
            $this->contextWarnings[] = [
                'type' => 'doctor_scope_mismatch',
                'entry_doctor_id' => (string)$entry['doctor_id'],
                'payload_doctor_id' => (string)$payload['doctor_id'],
            ];
            $payload['doctor_id'] = (string)$entry['doctor_id'];
        }
        $entryConsultorioId = trim((string)($entry['consultorio_id'] ?? ''));
        $entryConsultorioScope = $this->resolveConsultorioScope($entry['consultorio_scope'] ?? null, $entryConsultorioId);
        $isAnyConsultorioEntry = $entryConsultorioScope === self::CONSULTORIO_SCOPE_ALL;
        if (!$isAnyConsultorioEntry && (string)$payload['consultorio_id'] !== $entryConsultorioId) {
            return $this->error('invalid_params', 'doctor or consultorio mismatch', [
                'entry_doctor_id' => $entry['doctor_id'],
                'entry_consultorio_id' => $entry['consultorio_id'],
                'entry_consultorio_scope' => $entryConsultorioScope ?? self::CONSULTORIO_SCOPE_SINGLE,
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
            'channel_origin' => $assignAudit['channel_origin'],
            'created_by_role' => $assignAudit['created_by_role'],
            'created_by_id' => $assignAudit['created_by_id'],
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

        $statusAudit = $assignAudit;
        $statusAudit['metadata'] = array_merge(
            is_array($assignAudit['metadata'] ?? null) ? $assignAudit['metadata'] : [],
            [
                'source' => 'waitlist_assign',
                'waitlist_entry_id' => $id,
                'consultorio_id' => (string)$payload['consultorio_id'],
                'waitlist_entry_consultorio_scope' => $entryConsultorioScope ?? self::CONSULTORIO_SCOPE_SINGLE,
                'appointment_id' => (string)($result['appointment_id'] ?? ''),
                'assigned_slot' => [
                    'start_at' => (string)($payload['start_at'] ?? ''),
                    'end_at' => (string)($payload['end_at'] ?? ''),
                    'slot_minutes' => (int)($payload['slot_minutes'] ?? 0),
                ],
            ]
        );
        try {
            $entry = $this->repository->updateStatus($id, 'confirmed', $statusAudit);
        } catch (RuntimeException $e) {
            return $this->error('db_error', 'database error');
        }

        $response = [
            'entry' => $entry,
            'appointment_id' => $result['appointment_id'],
        ];

        return $this->success($response, array_merge([
            'write' => 'assign',
            'doctor_id_effective' => $payload['doctor_id'],
            'doctor_id_requested' => ($doctorIdRequested !== '' ? $doctorIdRequested : null),
        ], $eventsMeta));
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
        $consultorioScope = trim((string)($payload['consultorio_scope'] ?? ''));
        if ($consultorioScope !== '' && !in_array($consultorioScope, [self::CONSULTORIO_SCOPE_SINGLE, self::CONSULTORIO_SCOPE_ALL], true)) {
            $errors['consultorio_scope'] = 'invalid';
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

    private function resolveWaitlistActorAuditPayload(array $payload, string $action, ?string $entityId): array
    {
        $actorRole = trim((string)(
            $payload['actor_role']
            ?? $payload['created_by_role']
            ?? $this->actorContext['actor_role']
            ?? $this->actorContext['role']
            ?? 'doctor'
        ));
        if ($actorRole === '') {
            $actorRole = 'doctor';
        }

        $actorId = trim((string)(
            $payload['actor_id']
            ?? $payload['created_by_id']
            ?? $this->actorContext['user_id']
            ?? $this->actorContext['actor_id']
            ?? $this->actorContext['doctor_id']
            ?? ''
        ));
        $createdByRole = trim((string)($payload['created_by_role'] ?? $actorRole));
        if ($createdByRole === '') {
            $createdByRole = $actorRole;
        }
        $createdById = trim((string)($payload['created_by_id'] ?? $actorId));
        $channelOrigin = trim((string)($payload['channel_origin'] ?? ''));
        if ($channelOrigin === '') {
            $channelOrigin = $actorRole !== '' ? $actorRole : 'agenda_internal';
        }

        $actorDisplayName = trim((string)($payload['actor_display_name'] ?? ''));
        $resolvedEntityId = trim((string)($entityId ?? $payload['entity_id'] ?? ''));
        $entityType = trim((string)($payload['entity_type'] ?? 'waitlist_entry'));
        if ($entityType === '') {
            $entityType = 'waitlist_entry';
        }
        $occurredAt = trim((string)($payload['occurred_at'] ?? ''));
        if ($occurredAt === '') {
            $now = new DateTimeImmutable('now', new DateTimeZone('America/Mexico_City'));
            $occurredAt = $now->format('c');
        }

        $incomingMetadata = $payload['metadata'] ?? [];
        if (!is_array($incomingMetadata)) {
            $incomingMetadata = [];
        }
        $resolvedConsultorioId = trim((string)($payload['consultorio_id'] ?? ''));
        $resolvedConsultorioScope = $this->resolveConsultorioScope(
            $payload['consultorio_scope'] ?? null,
            $resolvedConsultorioId
        ) ?? self::CONSULTORIO_SCOPE_SINGLE;
        $safeMetadata = array_merge($incomingMetadata, [
            'doctor_id' => trim((string)($payload['doctor_id'] ?? $this->actorContext['doctor_id'] ?? '')),
            'consultorio_id' => $resolvedConsultorioId,
            'consultorio_scope' => $resolvedConsultorioScope,
            'status' => trim((string)($payload['status'] ?? '')),
        ]);

        return [
            'actor_role' => $actorRole,
            'actor_id' => $actorId,
            'actor_display_name' => $actorDisplayName,
            'channel_origin' => $channelOrigin,
            'created_by_role' => $createdByRole,
            'created_by_id' => $createdById,
            'action' => trim((string)($payload['action'] ?? $action)),
            'entity_type' => $entityType,
            'entity_id' => $resolvedEntityId,
            'occurred_at' => $occurredAt,
            'metadata' => $safeMetadata,
        ];
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

    private function resolveDoctorScope(string $doctorIdRequested, bool $doctorIsRequired): array
    {
        $doctorIdContext = trim((string)($this->actorContext['doctor_id'] ?? ''));
        $strictMode = ($this->actorContext['strict'] ?? false) === true;
        if ($doctorIdContext !== '') {
            if ($doctorIdRequested !== '' && $doctorIdRequested !== $doctorIdContext) {
                if ($strictMode) {
                    return [
                        'ok' => false,
                        'error' => 'forbidden',
                        'message' => 'doctor scope mismatch',
                        'meta' => [
                            'doctor_id_requested' => $doctorIdRequested,
                            'doctor_id_context' => $doctorIdContext,
                        ],
                    ];
                }
                $this->contextWarnings[] = [
                    'type' => 'doctor_scope_mismatch',
                    'doctor_id_requested' => $doctorIdRequested,
                    'doctor_id_context' => $doctorIdContext,
                ];
            }
            return ['ok' => true, 'doctor_id' => $doctorIdContext];
        }
        if ($doctorIsRequired && $doctorIdRequested === '') {
            return [
                'ok' => false,
                'error' => 'invalid_params',
                'message' => 'doctor_id is required',
                'meta' => [],
            ];
        }
        return ['ok' => true, 'doctor_id' => $doctorIdRequested];
    }

    private function assertEntryDoctorScope(string $entryDoctorId, string $entryId): array
    {
        $doctorIdContext = trim((string)($this->actorContext['doctor_id'] ?? ''));
        if ($doctorIdContext === '' || $entryDoctorId === '' || $entryDoctorId === $doctorIdContext) {
            return ['ok' => true];
        }
        $strictMode = ($this->actorContext['strict'] ?? false) === true;
        if ($strictMode) {
            return [
                'ok' => false,
                'error' => 'forbidden',
                'message' => 'waitlist entry out of doctor scope',
                'meta' => [
                    'waitlist_id' => $entryId,
                    'doctor_id_context' => $doctorIdContext,
                    'doctor_id_entry' => $entryDoctorId,
                ],
            ];
        }
        $this->contextWarnings[] = [
            'type' => 'doctor_scope_mismatch',
            'waitlist_id' => $entryId,
            'doctor_id_context' => $doctorIdContext,
            'doctor_id_entry' => $entryDoctorId,
        ];
        return ['ok' => true];
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
        $meta = $this->appendAuthMeta($meta);
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
        $meta = $this->appendAuthMeta($meta);
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

    private function resolveConsultorioScope($rawScope, string $consultorioId): ?string
    {
        $safeConsultorioId = trim((string)$consultorioId);
        if ($safeConsultorioId === self::ANY_CONSULTORIO_ID) {
            return self::CONSULTORIO_SCOPE_ALL;
        }

        $scope = strtolower(trim((string)($rawScope ?? '')));
        if ($scope === '') {
            return self::CONSULTORIO_SCOPE_SINGLE;
        }
        if ($scope !== self::CONSULTORIO_SCOPE_SINGLE && $scope !== self::CONSULTORIO_SCOPE_ALL) {
            return null;
        }
        return $scope;
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

    private function appendAuthMeta(array $meta): array
    {
        $meta['auth_mode'] = trim((string)($this->actorContext['mode'] ?? ''));
        $meta['auth_warnings'] = $this->contextWarnings;
        return $meta;
    }
}
