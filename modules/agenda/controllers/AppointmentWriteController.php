<?php
namespace Agenda\Controllers;

use Agenda\Repositories\AppointmentWriteRepository;
use PDOException;
use RuntimeException;

require_once __DIR__ . '/../repositories/AppointmentWriteRepository.php';
require_once __DIR__ . '/../../../api/_lib/db.php';

class AppointmentWriteController
{
    private ?AppointmentWriteRepository $repository = null;
    private ?string $dbError = null;
    private bool $dbConnectionError = false;

    public function __construct()
    {
        try {
            $pdo = mxmed_pdo();
        } catch (RuntimeException $e) {
            $this->dbConnectionError = true;
            return;
        }
        try {
            $this->repository = new AppointmentWriteRepository($pdo);
        } catch (RuntimeException $e) {
            $this->dbError = $e->getMessage();
        }
    }

    public function create(): array
    {
        $payload = $this->getPayload();
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
            return $this->error('db_error', 'database error');
        }
        if (!$this->repository) {
            return $this->notImplemented();
        }
        try {
            $result = $this->repository->createAppointment($payload);
        } catch (RuntimeException $e) {
            return $this->error('db_not_ready', $e->getMessage());
        } catch (PDOException $e) {
            return $this->error('db_error', 'database error');
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
        $errors = $this->validateCancel($appointmentId, $payload);
        if ($errors) {
            return $this->error('invalid_params', 'invalid payload for cancel', $errors);
        }
        if ($this->dbConnectionError) {
            return $this->error('db_error', 'database error');
        }
        if ($this->dbError) {
            return $this->error('db_not_ready', $this->dbError);
        }
        if (!$this->repository) {
            return $this->notImplemented();
        }
        try {
            $result = $this->repository->cancelAppointment($appointmentId, $payload);
        } catch (RuntimeException $e) {
            $message = $e->getMessage();
            if ($message === 'appointment not found') {
                return ['ok' => false, 'error' => 'not_found', 'message' => 'appointment not found', 'data' => null, 'meta' => (object)[]];
            }
            if (in_array($message, ['appointments table not ready', 'appointment events not ready'], true)) {
                return $this->error('db_not_ready', $message);
            }
            return $this->error('db_error', 'database error');
        } catch (PDOException $e) {
            return $this->error('db_error', 'database error');
        }
        return $this->success(
            [
                'appointment_id' => $result['appointment_id'],
                'status' => 'canceled',
                'start_at' => $result['start_at'],
                'end_at' => $result['end_at'],
                'motivo_code' => $result['motivo_code'],
                'motivo_text' => $result['motivo_text'],
            ],
            [
                'write' => 'cancel',
                'events_appended' => 1,
                'notify_patient' => $result['notify_patient'],
                'contact_method' => $result['contact_method'],
            ]
        );
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
            return $this->error('db_error', 'database error');
        }
        if (!$this->repository) {
            return $this->notImplemented();
        }
        try {
            $result = $this->repository->markNoShow($appointmentId, $payload);
        } catch (RuntimeException $e) {
            $message = $e->getMessage();
            if ($message === 'appointment not found') {
                return ['ok' => false, 'error' => 'not_found', 'message' => 'appointment not found', 'data' => null, 'meta' => (object)[]];
            }
            if (in_array($message, ['appointments table not ready', 'appointment events not ready'], true)) {
                return $this->error('db_not_ready', $message);
            }
            return $this->error('db_error', 'database error');
        } catch (PDOException $e) {
            return $this->error('db_error', 'database error');
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
        if ($start === null || $end === null || $start >= $end) {
            $errors['start_at'] = $payload['start_at'] ?? null;
            $errors['end_at'] = $payload['end_at'] ?? null;
        }
        return $errors;
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
            return $this->error('db_not_ready', $this->dbError);
        }
        if (!$this->repository) {
            return $this->notImplemented();
        }
        try {
            $result = $this->repository->rescheduleAppointment($appointmentId, $payload);
        } catch (RuntimeException $e) {
            $message = $e->getMessage();
            if ($message === 'appointment not found') {
                return ['ok' => false, 'error' => 'not_found', 'message' => 'appointment not found', 'data' => null, 'meta' => (object)[]];
            }
            if (in_array($message, ['appointments table not ready', 'appointment events not ready'], true)) {
                return $this->error('db_not_ready', $message);
            }
            return $this->error('db_error', 'database error');
        } catch (PDOException $e) {
            return $this->error('db_error', 'database error');
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
        $fromStart = $this->parseDateTime($payload['from_start_at'] ?? null);
        $fromEnd = $this->parseDateTime($payload['from_end_at'] ?? null);
        $toStart = $this->parseDateTime($payload['to_start_at'] ?? null);
        $toEnd = $this->parseDateTime($payload['to_end_at'] ?? null);
        if ($fromStart && $fromEnd && $fromStart >= $fromEnd) {
            $errors['from_range'] = 'must have start < end';
        }
        if ($toStart && $toEnd && $toStart >= $toEnd) {
            $errors['to_range'] = 'must have start < end';
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
        if (($payload['motivo_code'] ?? '') === '' && ($payload['motivo_text'] ?? '') === '') {
            $errors['motivo'] = 'motivo_code or motivo_text required';
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

    private function parseDateTime($value): ?\DateTime
    {
        if (!$value) {
            return null;
        }
        $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $value);
        return $dt && $dt->format('Y-m-d H:i:s') === $value ? $dt : null;
    }

    private function getPayload(): array
    {
        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function notImplemented(): array
    {
        return $this->error('not_implemented', 'write operations not enabled yet');
    }

    private function success(array $data, array $meta = []): array
    {
        return ['ok' => true, 'error' => null, 'message' => '', 'data' => $data, 'meta' => empty($meta) ? (object)[] : (object)$meta];
    }

    private function error(string $code, string $message, array $meta = []): array
    {
        return ['ok' => false, 'error' => $code, 'message' => $message, 'data' => null, 'meta' => empty($meta) ? (object)[] : (object)$meta];
    }
}
