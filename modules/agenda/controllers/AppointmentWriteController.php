<?php
namespace Agenda\Controllers;

class AppointmentWriteController
{
    public function create(): array
    {
        $payload = $this->getPayload();
        $errors = $this->validateCreate($payload);
        if ($errors) {
            return $this->error('invalid_params', 'invalid payload for create', $errors);
        }
        return $this->notImplemented();
    }

    public function reschedule(string $appointmentId): array
    {
        $payload = $this->getPayload();
        $errors = $this->validateReschedule($appointmentId, $payload);
        if ($errors) {
            return $this->error('invalid_params', 'invalid payload for reschedule', $errors);
        }
        return $this->notImplemented();
    }

    public function cancel(string $appointmentId): array
    {
        $payload = $this->getPayload();
        $errors = $this->validateCancel($appointmentId, $payload);
        if ($errors) {
            return $this->error('invalid_params', 'invalid payload for cancel', $errors);
        }
        return $this->notImplemented();
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
        return ['ok' => false, 'error' => 'not_implemented', 'message' => 'write operations not enabled yet', 'data' => null, 'meta' => (object)[]];
    }

    private function error(string $code, string $message, array $meta = []): array
    {
        return ['ok' => false, 'error' => $code, 'message' => $message, 'data' => null, 'meta' => empty($meta) ? (object)[] : (object)$meta];
    }
}
