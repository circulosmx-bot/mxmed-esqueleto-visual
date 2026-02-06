<?php
declare(strict_types=1);

namespace Agenda\Controllers;

use Agenda\Repositories\AppointmentWriteRepository;
use Agenda\Repositories\AvailabilityRepository;
use Agenda\Repositories\OverrideRepository;
use Agenda\Repositories\AppointmentCollisionsRepository;
use Agenda\Services\HolidayMxProvider;
use DateTime;
use DateTimeZone;
use PDOException;
use RuntimeException;

require_once __DIR__ . '/../repositories/AppointmentWriteRepository.php';
require_once __DIR__ . '/../repositories/AvailabilityRepository.php';
require_once __DIR__ . '/../repositories/OverrideRepository.php';
require_once __DIR__ . '/../repositories/AppointmentCollisionsRepository.php';
require_once __DIR__ . '/../services/HolidayMxProvider.php';
require_once __DIR__ . '/../config/agenda.php';
require_once __DIR__ . '/../../../api/_lib/db.php';

class AppointmentWriteController
{
    private ?AppointmentWriteRepository $repository = null;
    private ?string $dbError = null;
    private bool $dbConnectionError = false;
    private ?\PDO $pdo = null;
    private array $agendaConfig = [];

    public function __construct()
    {
        try {
            $pdo = mxmed_pdo();
            $this->pdo = $pdo;
        } catch (RuntimeException $e) {
            $this->dbConnectionError = true;
            return;
        }

        try {
            $this->repository = new AppointmentWriteRepository($pdo);
        } catch (RuntimeException $e) {
            $this->dbError = $e->getMessage();
        }

        try {
            $config = require __DIR__ . '/../config/agenda.php';
            $this->agendaConfig = is_array($config) ? $config : [];
        } catch (\Throwable $e) {
            $this->agendaConfig = [];
        }
    }

    public function create(): array
    {
        $payload = $this->getPayload();

        // Auto-create patient if missing patient_id and patient info is provided
        if (!isset($payload['patient_id'])) {
            $patientInput = $payload['patient'] ?? null;
            if (!$patientInput) {
                // fallback to top-level minimal fields
                $patientInput = [
                    'display_name' => $payload['display_name'] ?? null,
                    'sex' => $payload['sex'] ?? null,
                    'birthdate' => $payload['birthdate'] ?? null,
                    'contacts' => $payload['contacts'] ?? null,
                ];
            }
            if (!empty($patientInput['display_name'])) {
                if (!isset($patientInput['doctor_id']) && isset($payload['doctor_id'])) {
                    $patientInput['doctor_id'] = $payload['doctor_id'];
                }
                require_once __DIR__ . '/../helpers/patients_client.php';
                $patientResp = agenda_patients_create($patientInput);
                if (!$patientResp['ok']) {
                    return $this->error(
                        (string)($patientResp['error'] ?? 'error'),
                        (string)($patientResp['message'] ?? 'error'),
                        (array)($patientResp['meta'] ?? ['visibility' => ['contact' => 'masked']])
                    );
                }
                $payload['patient_id'] = $patientResp['data']['patient_id'] ?? null;
            }
        }

        $errors = $this->validateCreate($payload);
        if ($errors) {
            return $this->error('invalid_params', 'invalid payload for create', $errors);
        }

        if ($this->dbConnectionError) {
            return $this->error('db_error', 'database error');
        }

        if ($this->dbError) {
            if (in_array($this->dbError, ['appointments table not ready', 'appointment events not ready'], true)) {
                return $this->error('db_not_ready', $this->dbError);
            }
            return $this->error('db_error', 'database error', $this->qaDebugMeta(null, $this->dbError));
        }

        $guard = $this->checkAvailabilityRange(
            (string)$payload['doctor_id'],
            (string)$payload['consultorio_id'],
            (string)$payload['start_at'],
            (string)$payload['end_at'],
            $payload['slot_minutes'] ?? null,
            null
        );

        if (!$guard['ok']) {
            return $this->error(
                (string)$guard['error'],
                (string)$guard['message'],
                (array)($guard['meta'] ?? [])
            );
        }

        if (!$this->repository) {
            return $this->notImplemented();
        }

        try {
            $result = $this->repository->createAppointment($payload);
        } catch (RuntimeException $e) {
            return $this->error('db_not_ready', $e->getMessage(), $this->qaDebugMeta($e));
        } catch (PDOException $e) {
            // IMPORTANT: many "collision" cases are actually enforced by DB constraints.
            // Map common SQLSTATE/driver errors to a semantic error in QA.
            $mapped = $this->mapPdoExceptionToDomainError($e);
            if ($mapped) {
                return $this->error($mapped['error'], $mapped['message'], $mapped['meta']);
            }
            return $this->error('db_error', 'database error', $this->qaDebugMeta($e));
        } catch (\Throwable $e) {
            return $this->error('db_error', 'database error', $this->qaDebugMeta($e));
        }

        return $this->success(
            [
                'appointment_id' => $result['appointment_id'],
                'status' => 'created',
                'start_at' => $payload['start_at'],
                'end_at' => $payload['end_at'],
                'doctor_id' => $payload['doctor_id'],
                'consultorio_id' => $payload['consultorio_id'],
                'patient_id' => $payload['patient_id'] ?? null,
                'created_at' => $result['created_at'],
            ],
            ['write' => 'create', 'events_appended' => 1]
        );
    }

    public function cancel(string $appointmentId): array
    {
        $payload = $this->getPayload();
        if (!isset($payload['motivo_code']) && isset($payload['reason_code'])) {
            $payload['motivo_code'] = $payload['reason_code'];
        }
        if (!isset($payload['motivo_text']) && isset($payload['reason_text'])) {
            $payload['motivo_text'] = $payload['reason_text'];
        }
        if (!isset($payload['actor_role']) && isset($payload['created_by_role'])) {
            $payload['actor_role'] = $payload['created_by_role'];
        }
        if (!isset($payload['actor_id']) && isset($payload['created_by_id'])) {
            $payload['actor_id'] = $payload['created_by_id'];
        }
        if (!isset($payload['notify_patient'])) {
            $payload['notify_patient'] = false;
        }
        $payload['notify_patient'] = $this->normalizeBoolean($payload['notify_patient']);
        if (!isset($payload['contact_method'])) {
            $payload['contact_method'] = 'none';
        }

        $errors = $this->validateCancel($appointmentId, $payload);
        if ($errors) {
            return $this->error('invalid_params', 'invalid payload for cancel', $errors);
        }

        if ($this->dbConnectionError) {
            return $this->error('db_error', 'database error');
        }

        if ($this->dbError) {
            return $this->error('db_not_ready', $this->dbError, $this->qaDebugMeta(null, $this->dbError));
        }

        if (!$this->repository) {
            return $this->notImplemented();
        }

        try {
            $result = $this->repository->cancelAppointment($appointmentId, $payload);
        } catch (RuntimeException $e) {
            $message = $e->getMessage();
            if ($message === 'appointment not found') {
                return $this->error('not_found', 'appointment not found');
            }
            if (in_array($message, ['appointments table not ready', 'appointment events not ready'], true)) {
                return $this->error('db_not_ready', $message);
            }
            return $this->error('db_error', 'database error', $this->qaDebugMeta($e));
        } catch (PDOException $e) {
            return $this->error('db_error', 'database error', $this->qaDebugMeta($e));
        } catch (\Throwable $e) {
            return $this->error('db_error', 'database error', $this->qaDebugMeta($e));
        }

        $data = [
            'appointment_id' => $result['appointment_id'],
            'status' => $result['status'],
            'start_at' => $result['start_at'],
            'end_at' => $result['end_at'],
            'cancelled_at' => $result['cancelled_at'] ?? null,
            'event_id' => $result['event_id'] ?? null,
        ];
        $meta = [
            'write' => 'cancel',
            'events_appended' => $result['events_appended'],
            'notify_patient' => $result['notify_patient'],
            'contact_method' => $result['contact_method'],
        ];
        if (!empty($result['already_cancelled'])) {
            return [
                'ok' => true,
                'error' => null,
                'message' => 'already_cancelled',
                'data' => $data,
                'meta' => (object)$meta,
            ];
        }
        return $this->success($data, $meta);
    }

    public function noShow(string $appointmentId): array
    {
        $payload = $this->getPayload();

        $errors = $this->validateNoShow($appointmentId, $payload);
        if ($errors) {
            return $this->error('invalid_params', 'invalid payload for no_show', $errors);
        }

        if ($this->dbConnectionError) {
            return $this->error('db_error', 'database error');
        }

        if ($this->dbError) {
            if (in_array($this->dbError, ['appointments table not ready', 'appointment events not ready'], true)) {
                return $this->error('db_not_ready', $this->dbError);
            }
            return $this->error('db_error', 'database error', $this->qaDebugMeta(null, $this->dbError));
        }

        if (!$this->repository) {
            return $this->notImplemented();
        }

        try {
            $result = $this->repository->markNoShow($appointmentId, $payload);
        } catch (RuntimeException $e) {
            $message = $e->getMessage();
            if ($message === 'appointment not found') {
                return $this->error('not_found', 'appointment not found');
            }
            if (in_array($message, ['appointments table not ready', 'appointment events not ready'], true)) {
                return $this->error('db_not_ready', $message);
            }
            return $this->error('db_error', 'database error', $this->qaDebugMeta($e));
        } catch (PDOException $e) {
            return $this->error('db_error', 'database error', $this->qaDebugMeta($e));
        } catch (\Throwable $e) {
            return $this->error('db_error', 'database error', $this->qaDebugMeta($e));
        }

        return $this->success(
            [
                'appointment_id' => $result['appointment_id'],
                'status' => 'no_show',
                'start_at' => $result['start_at'],
                'end_at' => $result['end_at'],
                'motivo_code' => $result['motivo_code'],
                'motivo_text' => $result['motivo_text'],
                'observed_at' => $result['observed_at'],
            ],
            [
                'write' => 'no_show',
                'events_appended' => 1,
                'flag_appended' => $result['flag_appended'],
                'notify_patient' => $result['notify_patient'],
                'contact_method' => $result['contact_method'],
            ]
        );
    }

    public function reschedule(string $appointmentId): array
    {
        $payload = $this->getPayload();

        $errors = $this->validateReschedule($appointmentId, $payload);
        if ($errors) {
            return $this->error('invalid_params', 'invalid payload for reschedule', $errors);
        }

        if ($this->dbConnectionError) {
            return $this->error('db_error', 'database error');
        }

        if ($this->dbError) {
            return $this->error('db_not_ready', $this->dbError, $this->qaDebugMeta(null, $this->dbError));
        }

        $context = $this->fetchAppointmentContext($appointmentId);
        if (!$context['ok']) {
            return $this->error(
                (string)$context['error'],
                (string)$context['message'],
                (array)($context['meta'] ?? [])
            );
        }

        $guard = $this->checkAvailabilityRange(
            (string)$context['doctor_id'],
            (string)$context['consultorio_id'],
            (string)$payload['to_start_at'],
            (string)$payload['to_end_at'],
            $payload['slot_minutes'] ?? null,
            $appointmentId
        );

        if (!$guard['ok']) {
            return $this->error(
                (string)$guard['error'],
                (string)$guard['message'],
                (array)($guard['meta'] ?? [])
            );
        }

        if (!$this->repository) {
            return $this->notImplemented();
        }

        try {
            $result = $this->repository->rescheduleAppointment($appointmentId, $payload);
        } catch (RuntimeException $e) {
            $message = $e->getMessage();
            if ($message === 'appointment not found') {
                return $this->error('not_found', 'appointment not found');
            }
            if (in_array($message, ['appointments table not ready', 'appointment events not ready'], true)) {
                return $this->error('db_not_ready', $message);
            }
            return $this->error('db_error', 'database error', $this->qaDebugMeta($e));
        } catch (PDOException $e) {
            $mapped = $this->mapPdoExceptionToDomainError($e);
            if ($mapped) {
                return $this->error($mapped['error'], $mapped['message'], $mapped['meta']);
            }
            return $this->error('db_error', 'database error', $this->qaDebugMeta($e));
        } catch (\Throwable $e) {
            return $this->error('db_error', 'database error', $this->qaDebugMeta($e));
        }

        return $this->success(
            [
                'appointment_id' => $result['appointment_id'],
                'status' => 'rescheduled',
                'from_start_at' => $result['from_start_at'],
                'from_end_at' => $result['from_end_at'],
                'to_start_at' => $result['to_start_at'],
                'to_end_at' => $result['to_end_at'],
                'motivo_code' => $result['motivo_code'],
                'motivo_text' => $result['motivo_text'],
            ],
            [
                'write' => 'reschedule',
                'events_appended' => 1,
                'notify_patient' => $result['notify_patient'],
                'contact_method' => $result['contact_method'],
            ]
        );
    }

    private function validateCreate(array $payload): array
    {
        $errors = [];
        foreach (['doctor_id', 'consultorio_id', 'start_at', 'end_at', 'modality', 'channel_origin', 'created_by_role', 'created_by_id'] as $field) {
            if (!isset($payload[$field]) || trim((string)$payload[$field]) === '') {
                $errors[$field] = $payload[$field] ?? null;
            }
        }

        $start = $this->parseDateTime($payload['start_at'] ?? null);
        $end = $this->parseDateTime($payload['end_at'] ?? null);
        if ($start === null || $end === null) {
            $errors['start_at'] = $payload['start_at'] ?? null;
            $errors['end_at'] = $payload['end_at'] ?? null;
        }
        return $errors;
    }

    private function validateReschedule(string $appointmentId, array $payload): array
    {
        $errors = [];
        if (trim($appointmentId) === '') {
            $errors['appointment_id'] = $appointmentId;
        }

        $required = ['from_start_at', 'from_end_at', 'to_start_at', 'to_end_at'];
        foreach ($required as $field) {
            if (!isset($payload[$field]) || $this->parseDateTime($payload[$field]) === null) {
                $errors[$field] = $payload[$field] ?? null;
            }
        }

        if (($payload['motivo_code'] ?? '') === '' && ($payload['motivo_text'] ?? '') === '') {
            $errors['motivo'] = 'motivo_code or motivo_text required';
        }
        return $errors;
    }

    private function validateCancel(string $appointmentId, array $payload): array
    {
        $errors = [];
        if (trim($appointmentId) === '') {
            $errors['appointment_id'] = $appointmentId;
        }
        $actorRole = trim((string)($payload['actor_role'] ?? ''));
        if ($actorRole === '') {
            $errors['actor_role'] = 'actor_role required';
        } elseif (!in_array($actorRole, ['patient', 'operator', 'doctor', 'system'], true)) {
            $errors['actor_role'] = 'actor_role invalid';
        }
        $channelOrigin = trim((string)($payload['channel_origin'] ?? ''));
        if ($channelOrigin === '') {
            $errors['channel_origin'] = 'channel_origin required';
        }
        $reasonCode = $payload['motivo_code'] ?? null;
        $reasonText = $payload['motivo_text'] ?? null;
        if ($reasonCode !== null && (!is_string($reasonCode) || strlen($reasonCode) > 255)) {
            $errors['reason_code'] = 'reason_code invalid';
        }
        if ($reasonText !== null && (!is_string($reasonText) || strlen($reasonText) > 255)) {
            $errors['reason_text'] = 'reason_text invalid';
        }
        if (isset($payload['notify_patient']) && !in_array($payload['notify_patient'], ['0', '1', 0, 1, true, false, 'true', 'false'], true)) {
            $errors['notify_patient'] = $payload['notify_patient'];
        }
        $contactMethod = $payload['contact_method'] ?? 'none';
        if (!is_string($contactMethod) || trim($contactMethod) === '') {
            $errors['contact_method'] = 'contact_method invalid';
        } elseif (!in_array($contactMethod, ['sms', 'whatsapp', 'email', 'phone', 'none'], true)) {
            $errors['contact_method'] = 'contact_method invalid';
        }
        return $errors;
    }

    private function validateNoShow(string $appointmentId, array $payload): array
    {
        $errors = [];
        if (trim($appointmentId) === '') {
            $errors['appointment_id'] = $appointmentId;
        }
        if (($payload['motivo_code'] ?? '') === '' && ($payload['motivo_text'] ?? '') === '') {
            $errors['motivo'] = 'motivo_code or motivo_text required';
        }
        if (isset($payload['notify_patient']) && !in_array($payload['notify_patient'], ['0', '1', 0, 1], true)) {
            $errors['notify_patient'] = $payload['notify_patient'];
        }
        if (isset($payload['observed_at']) && $this->parseDateTime($payload['observed_at']) === null) {
            $errors['observed_at'] = $payload['observed_at'];
        }
        return $errors;
    }

    private function parseDateTime($value): ?DateTime
    {
        if (!$value) {
            return null;
        }
        $formats = ['Y-m-d H:i:s', 'Y-m-d\TH:i:s'];
        foreach ($formats as $fmt) {
            $dt = DateTime::createFromFormat($fmt, (string)$value);
            if ($dt && $dt->format($fmt) === (string)$value) {
                return $dt;
            }
        }
        return null;
    }

    private function normalizeBoolean($value): int
    {
        $truthy = [true, 1, '1', 'true'];
        if (in_array($value, $truthy, true)) {
            return 1;
        }
        return 0;
    }

    private function normalizeSlotMinutes($value): ?int
    {
        if ($value === null || $value === '') {
            return 30;
        }
        $minutes = (int)$value;
        if ($minutes < 5 || $minutes > 720) {
            return null;
        }
        return $minutes;
    }

    private function fetchAppointmentContext(string $appointmentId): array
    {
        $table = trim((string)($this->agendaConfig['appointments_table'] ?? ''));
        $pk = trim((string)($this->agendaConfig['appointment_pk'] ?? 'appointment_id')) ?: 'appointment_id';

        if ($table === '') {
            return ['ok' => false, 'error' => 'db_not_ready', 'message' => 'appointments table not ready', 'meta' => []];
        }
        if (!$this->pdo) {
            return ['ok' => false, 'error' => 'db_error', 'message' => 'database error', 'meta' => []];
        }

        try {
            if (!$this->tableExists($table)) {
                return ['ok' => false, 'error' => 'db_not_ready', 'message' => 'appointments table not ready', 'meta' => []];
            }
            $stmt = $this->pdo->prepare("SELECT doctor_id, consultorio_id FROM {$table} WHERE {$pk} = :id LIMIT 1");
            $stmt->execute(['id' => $appointmentId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return ['ok' => false, 'error' => 'db_error', 'message' => 'database error', 'meta' => $this->qaDebugMeta($e)];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'db_error', 'message' => 'database error', 'meta' => $this->qaDebugMeta($e)];
        }

        if (!$row) {
            return ['ok' => false, 'error' => 'not_found', 'message' => 'appointment not found', 'meta' => []];
        }

        return [
            'ok' => true,
            'doctor_id' => (string)$row['doctor_id'],
            'consultorio_id' => (string)$row['consultorio_id'],
        ];
    }

    private function tableExists(string $name): bool
    {
        if (!$this->pdo) {
            return false;
        }
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table');
        $stmt->execute(['table' => $name]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function checkAvailabilityRange(
        string $doctorId,
        string $consultorioId,
        string $startAt,
        string $endAt,
        $slotMinutes,
        ?string $excludeAppointmentId
    ): array {
        if (!$this->pdo) {
            return ['ok' => false, 'error' => 'db_error', 'message' => 'database error', 'meta' => []];
        }

        $start = $this->parseDateTime($startAt);
        $end = $this->parseDateTime($endAt);
        if (!$start || !$end) {
            return ['ok' => false, 'error' => 'invalid_params', 'message' => 'invalid params', 'meta' => []];
        }

        if ($start >= $end) {
            return [
                'ok' => false,
                'error' => 'invalid_time_range',
                'message' => 'invalid time range',
                'meta' => [
                    'reason' => 'invalid_time_range',
                    'doctor_id' => $doctorId,
                    'consultorio_id' => $consultorioId,
                    'date' => $start->format('Y-m-d'),
                ],
            ];
        }

        $slotMinutes = $this->normalizeSlotMinutes($slotMinutes);
        if ($slotMinutes === null) {
            return [
                'ok' => false,
                'error' => 'invalid_params',
                'message' => 'slot_minutes must be between 5 and 720',
                'meta' => [
                    'doctor_id' => $doctorId,
                    'consultorio_id' => $consultorioId,
                ],
            ];
        }

        $date = $start->format('Y-m-d');
        $metaBase = [
            'doctor_id' => $doctorId,
            'consultorio_id' => $consultorioId,
            'date' => $date,
            'slot_minutes' => $slotMinutes,
        ];

        $holiday = HolidayMxProvider::isHoliday($date);
        $isHoliday = (bool)($holiday['is_holiday'] ?? false);

        $overrides = [];
        $overridesEnabled = false;
        $overridesConfigured = trim((string)($this->agendaConfig['overrides_table'] ?? '')) !== '';

        if ($overridesConfigured) {
            try {
                $overrideRepo = new OverrideRepository($this->pdo);
                if (!$overrideRepo->isEnabled()) {
                    return [
                        'ok' => false,
                        'error' => 'db_not_ready',
                        'message' => 'availability overrides not ready',
                        'meta' => $metaBase,
                    ];
                }
                $overridesEnabled = true;
                $overrides = $overrideRepo->getOverridesForDate($doctorId, $consultorioId, $date);
            } catch (RuntimeException $e) {
                if ($e->getMessage() === 'availability overrides not ready') {
                    return [
                        'ok' => false,
                        'error' => 'db_not_ready',
                        'message' => 'availability overrides not ready',
                        'meta' => $metaBase,
                    ];
                }
                return ['ok' => false, 'error' => 'db_error', 'message' => 'database error', 'meta' => array_merge($metaBase, $this->qaDebugMeta($e))];
            } catch (PDOException $e) {
                return ['ok' => false, 'error' => 'db_error', 'message' => 'database error', 'meta' => array_merge($metaBase, $this->qaDebugMeta($e))];
            } catch (\Throwable $e) {
                return ['ok' => false, 'error' => 'db_error', 'message' => 'database error', 'meta' => array_merge($metaBase, $this->qaDebugMeta($e))];
            }
        }

        $closeOverrides = array_values(array_filter($overrides, fn($override) => ($override['type'] ?? '') === 'close'));
        $openOverrides = array_values(array_filter($overrides, fn($override) => ($override['type'] ?? '') === 'open'));
        $hasOpen = !empty($openOverrides);
        $hasCloseFullDay = $this->hasFullDayClose($closeOverrides, $date);

        $shouldLoadBase = (!$isHoliday || !empty($closeOverrides) || $hasOpen) && (!$hasCloseFullDay || $hasOpen);

        $baseWindows = [];
        if ($shouldLoadBase) {
            try {
                $baseRepo = new AvailabilityRepository($this->pdo);
                $baseWindows = $baseRepo->getBaseWindowsForDate($doctorId, $consultorioId, $date);
            } catch (RuntimeException $e) {
                if ($e->getMessage() === 'availability base schedule not ready') {
                    return [
                        'ok' => false,
                        'error' => 'db_not_ready',
                        'message' => 'availability base schedule not ready',
                        'meta' => $metaBase,
                    ];
                }
                return ['ok' => false, 'error' => 'db_error', 'message' => 'database error', 'meta' => array_merge($metaBase, $this->qaDebugMeta($e))];
            } catch (PDOException $e) {
                return ['ok' => false, 'error' => 'db_error', 'message' => 'database error', 'meta' => array_merge($metaBase, $this->qaDebugMeta($e))];
            } catch (\Throwable $e) {
                return ['ok' => false, 'error' => 'db_error', 'message' => 'database error', 'meta' => array_merge($metaBase, $this->qaDebugMeta($e))];
            }
        }

        $windows = $baseWindows;
        if (!empty($closeOverrides)) {
            $windows = $this->subtractIntervals($windows, $closeOverrides);
        }
        if (!empty($openOverrides)) {
            $windows = array_merge($windows, $this->buildOverrideWindows($openOverrides));
        }
        $windows = $this->deduplicateWindows($windows);
        $windows = $this->sortWindows($windows);

        $startStr = $start->format('Y-m-d H:i:s');
        $endStr = $end->format('Y-m-d H:i:s');

        if (!$this->rangeFitsWindows($startStr, $endStr, $windows)) {
            return [
                'ok' => false,
                'error' => 'outside_schedule',
                'message' => 'outside schedule',
                'meta' => array_merge($metaBase, [
                    'reason' => 'outside_schedule',
                    'overrides_enabled' => $overridesEnabled,
                    'collisions_enabled' => false,
                ]),
            ];
        }

        $collisionsEnabled = true;
        $busyIntervals = [];

        try {
            $collisionsRepo = new AppointmentCollisionsRepository($this->pdo, $this->agendaConfig);
            $busyIntervals = $collisionsRepo->getBusyIntervalsForDate($doctorId, $consultorioId, $date, $excludeAppointmentId);
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'availability appointments not ready') {
                return [
                    'ok' => false,
                    'error' => 'db_not_ready',
                    'message' => 'availability appointments not ready',
                    'meta' => array_merge($metaBase, [
                        'overrides_enabled' => $overridesEnabled,
                        'collisions_enabled' => false,
                    ]),
                ];
            }
            return ['ok' => false, 'error' => 'db_error', 'message' => 'database error', 'meta' => array_merge($metaBase, $this->qaDebugMeta($e))];
        } catch (PDOException $e) {
            return ['ok' => false, 'error' => 'db_error', 'message' => 'database error', 'meta' => array_merge($metaBase, $this->qaDebugMeta($e))];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'db_error', 'message' => 'database error', 'meta' => array_merge($metaBase, $this->qaDebugMeta($e))];
        }

        if ($this->rangeOverlaps($startStr, $endStr, $busyIntervals)) {
            return [
                'ok' => false,
                'error' => 'collision',
                'message' => 'collision detected',
                'meta' => array_merge($metaBase, [
                    'reason' => 'collision',
                    'overrides_enabled' => $overridesEnabled,
                    'collisions_enabled' => $collisionsEnabled,
                ]),
            ];
        }

        $windows = $this->subtractIntervals($windows, $busyIntervals);
        $slots = $this->generateSlots($windows, $slotMinutes);

        if (!$this->slotExists($startStr, $endStr, $slots)) {
            return [
                'ok' => false,
                'error' => 'slot_unavailable',
                'message' => 'slot unavailable',
                'meta' => array_merge($metaBase, [
                    'reason' => 'slot_unavailable',
                    'overrides_enabled' => $overridesEnabled,
                    'collisions_enabled' => $collisionsEnabled,
                ]),
            ];
        }

        return [
            'ok' => true,
            'overrides_enabled' => $overridesEnabled,
            'collisions_enabled' => $collisionsEnabled,
        ];
    }

    private function rangeFitsWindows(string $startAt, string $endAt, array $windows): bool
    {
        foreach ($windows as $window) {
            if ($startAt >= $window['start_at'] && $endAt <= $window['end_at']) {
                return true;
            }
        }
        return false;
    }

    private function rangeOverlaps(string $startAt, string $endAt, array $intervals): bool
    {
        foreach ($intervals as $interval) {
            if ($startAt < $interval['end_at'] && $endAt > $interval['start_at']) {
                return true;
            }
        }
        return false;
    }

    private function slotExists(string $startAt, string $endAt, array $slots): bool
    {
        foreach ($slots as $slot) {
            if ($slot['start_at'] === $startAt && $slot['end_at'] === $endAt) {
                return true;
            }
        }
        return false;
    }

    private function buildOverrideWindows(array $overrides): array
    {
        return array_map(fn(array $override) => [
            'start_at' => $override['start_at'],
            'end_at' => $override['end_at'],
            'source' => 'C',
        ], $overrides);
    }

    private function deduplicateWindows(array $windows): array
    {
        $seen = [];
        $deduped = [];
        foreach ($windows as $window) {
            $key = sprintf('%s|%s|%s', $window['start_at'], $window['end_at'], $window['source'] ?? 'A');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $deduped[] = $window;
        }
        return $deduped;
    }

    private function subtractIntervals(array $windows, array $closes): array
    {
        $result = [];
        foreach ($windows as $window) {
            $segments = [$window];
            foreach ($closes as $close) {
                $temporary = [];
                foreach ($segments as $segment) {
                    $temporary = array_merge($temporary, $this->subtractSegment($segment, $close));
                }
                $segments = $temporary;
                if (empty($segments)) {
                    break;
                }
            }
            $result = array_merge($result, $segments);
        }
        return $result;
    }

    private function subtractSegment(array $segment, array $close): array
    {
        $segmentStart = $this->toTimestamp($segment['start_at']);
        $segmentEnd   = $this->toTimestamp($segment['end_at']);
        $closeStart   = $this->toTimestamp($close['start_at']);
        $closeEnd     = $this->toTimestamp($close['end_at']);

        if ($closeEnd <= $segmentStart || $closeStart >= $segmentEnd) {
            return [$segment];
        }

        $parts = [];
        if ($closeStart > $segmentStart) {
            $parts[] = [
                'start_at' => $segment['start_at'],
                'end_at'   => $this->formatTimestamp(min($closeStart, $segmentEnd)),
                'source'   => $segment['source'] ?? 'A',
            ];
        }
        if ($closeEnd < $segmentEnd) {
            $parts[] = [
                'start_at' => $this->formatTimestamp(max($closeEnd, $segmentStart)),
                'end_at'   => $segment['end_at'],
                'source'   => $segment['source'] ?? 'A',
            ];
        }
        return $parts;
    }

    private function sortWindows(array $windows): array
    {
        usort($windows, fn($a, $b) => strcmp($a['start_at'], $b['start_at']));
        return $windows;
    }

    private function generateSlots(array $windows, int $slotMinutes): array
    {
        if ($slotMinutes <= 0) {
            return [];
        }
        $slots = [];
        foreach ($windows as $window) {
            $startTs = $this->toTimestamp($window['start_at']);
            $endTs = $this->toTimestamp($window['end_at']);
            $step = $slotMinutes * 60;
            if ($step <= 0 || $startTs >= $endTs) {
                continue;
            }
            for ($cursor = $startTs; $cursor + $step <= $endTs; $cursor += $step) {
                $slots[] = [
                    'start_at' => $this->formatTimestamp($cursor),
                    'end_at' => $this->formatTimestamp($cursor + $step),
                ];
            }
        }
        return $slots;
    }

    private function hasFullDayClose(array $closes, string $date): bool
    {
        if (empty($closes)) {
            return false;
        }
        $startOfDay = "{$date} 00:00:00";
        $endOfDay   = "{$date} 23:59:59";
        foreach ($closes as $close) {
            if ($this->toTimestamp($close['start_at']) <= $this->toTimestamp($startOfDay)
                && $this->toTimestamp($close['end_at']) >= $this->toTimestamp($endOfDay)
            ) {
                return true;
            }
        }
        return false;
    }

    private function toTimestamp(string $datetime): int
    {
        $dt = DateTime::createFromFormat(
            'Y-m-d H:i:s',
            $datetime,
            new DateTimeZone(AvailabilityRepository::TIMEZONE)
        );
        if (!$dt) {
            return (int)strtotime($datetime);
        }
        return (int)$dt->format('U');
    }

    private function formatTimestamp(int $timestamp): string
    {
        $dt = new DateTime('@' . $timestamp);
        $dt->setTimezone(new DateTimeZone(AvailabilityRepository::TIMEZONE));
        return $dt->format('Y-m-d H:i:s');
    }

    private function getPayload(): array
    {
        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw ?: '', true);
        return is_array($decoded) ? $decoded : [];
    }

    private function getQaMode(): string
    {
        // Prefer header, fallback to env
        $headers = function_exists('getallheaders') ? (array)getallheaders() : [];
        $qa = $headers['X-QA-Mode'] ?? $headers['x-qa-mode'] ?? null;
        if (is_string($qa) && $qa !== '') {
            return $qa;
        }
        $env = getenv('QA_MODE');
        return is_string($env) ? $env : '';
    }

    private function notImplemented(): array
    {
        return $this->error('not_implemented', 'write operations not enabled yet');
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

    /**
     * @param mixed $meta
     */
    private function error(string $code, string $message, $meta = []): array
    {
        $arr = is_array($meta) ? $meta : (array)$meta;
        $arr['qa_mode_seen'] = $this->getQaMode();
        return [
            'ok' => false,
            'error' => $code,
            'message' => $message,
            'data' => null,
            'meta' => empty($arr) ? (object)[] : (object)$arr,
        ];
    }

    /**
     * Adds safe debug only when QA_MODE=ready.
     * You will finally see the real PDO error message in the response meta.
     */
    private function qaDebugMeta(?\Throwable $e, ?string $fallbackMessage = null): array
    {
        $qa = $this->getQaMode();
        if ($qa !== 'ready') {
            return [];
        }
        $meta = [];
        if ($fallbackMessage) {
            $meta['debug_message'] = $fallbackMessage;
        }
        if ($e) {
            $meta['debug_exception'] = get_class($e);
            $meta['debug_message'] = $e->getMessage();
            // not including stack trace intentionally
        }
        return $meta;
    }

    /**
     * Map DB constraint errors to semantic domain errors (collision)
     * so QA can pass and we don't hide "expected" failures behind db_error.
     */
    private function mapPdoExceptionToDomainError(PDOException $e): ?array
    {
        $qa = $this->getQaMode();
        $sqlState = (string)$e->getCode();
        $msg = strtolower($e->getMessage());

        // Most MySQL duplicate-key / constraint violations are SQLSTATE 23000.
        // If your schema enforces uniqueness for overlapping slots, treat as collision.
        if ($sqlState === '23000' || str_contains($msg, 'duplicate') || str_contains($msg, 'unique')) {
            $meta = ['reason' => 'collision_db_constraint'];
            if ($qa === 'ready') {
                $meta = array_merge($meta, $this->qaDebugMeta($e));
            }
            return [
                'error' => 'collision',
                'message' => 'collision detected',
                'meta' => $meta,
            ];
        }

        return null;
    }
}
