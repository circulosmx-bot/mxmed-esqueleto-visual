<?php
declare(strict_types=1);

namespace Agenda\Appointments;

final readonly class AppointmentIdempotencyRecord
{
    private AppointmentIdempotencyKey $idempotencyKey;
    private string $operationId;
    private AppointmentOperationFingerprint $fingerprint;
    private string $appointmentId;
    private string $outcomeCode;
    private string $resultDigest;
    private string $recordedAt;

    public function __construct(
        string $idempotencyKey,
        string $operationId,
        string $fingerprint,
        string $appointmentId,
        string $outcomeCode,
        private int $originalHttpStatus,
        string $resultDigest,
        private int $aggregateVersionResult,
        string $recordedAt
    ) {
        $this->idempotencyKey = new AppointmentIdempotencyKey($idempotencyKey);
        $this->operationId = AppointmentValue::safeIdentifier($operationId, 'invalid_idempotency_key');
        $this->fingerprint = AppointmentOperationFingerprint::fromDigest($fingerprint);
        $this->appointmentId = AppointmentValue::scopedIdentity($appointmentId, 'appointment_mismatch');
        $this->outcomeCode = AppointmentValue::safeIdentifier($outcomeCode, 'idempotency_conflict');
        if ($originalHttpStatus < 100 || $originalHttpStatus > 599) throw new AppointmentDomainException('idempotency_conflict');
        if (preg_match('/\A[a-f0-9]{64}\z/D', $resultDigest) !== 1) throw new AppointmentDomainException('idempotency_conflict');
        if ($aggregateVersionResult < 1) throw new AppointmentDomainException('idempotency_conflict');
        $this->resultDigest = $resultDigest;
        $this->recordedAt = AppointmentValue::canonicalUtc($recordedAt, 'invalid_timestamp');
    }

    public function idempotencyKey(): AppointmentIdempotencyKey { return $this->idempotencyKey; }
    public function operationId(): string { return $this->operationId; }
    public function fingerprint(): AppointmentOperationFingerprint { return $this->fingerprint; }
    public function appointmentId(): string { return $this->appointmentId; }
    public function outcomeCode(): string { return $this->outcomeCode; }
    public function originalHttpStatus(): int { return $this->originalHttpStatus; }
    public function resultDigest(): string { return $this->resultDigest; }
    public function aggregateVersionResult(): int { return $this->aggregateVersionResult; }
    public function recordedAt(): string { return $this->recordedAt; }
    public function toArray(): array
    {
        return [
            'idempotency_key' => $this->idempotencyKey->value(),
            'operation_id' => $this->operationId,
            'fingerprint' => $this->fingerprint->value(),
            'appointment_id' => $this->appointmentId,
            'outcome_code' => $this->outcomeCode,
            'original_http_status' => $this->originalHttpStatus,
            'result_digest' => $this->resultDigest,
            'aggregate_version_result' => $this->aggregateVersionResult,
            'recorded_at' => $this->recordedAt,
        ];
    }
}
