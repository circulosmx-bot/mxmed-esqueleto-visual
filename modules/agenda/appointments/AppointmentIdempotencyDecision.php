<?php
declare(strict_types=1);

namespace Agenda\Appointments;

final readonly class AppointmentIdempotencyDecision
{
    public const NEW_OPERATION = 'new_operation';
    public const REPLAY = 'replay';
    public const CONFLICT = 'idempotency_conflict';

    private function __construct(
        private string $status,
        private bool $canContinue,
        private int $httpStatus,
        private AppointmentOperationFingerprint $fingerprint,
        private ?AppointmentIdempotencyRecord $record
    ) {}

    public static function newOperation(AppointmentOperationFingerprint $fingerprint): self
    {
        return new self(self::NEW_OPERATION, true, 200, $fingerprint, null);
    }

    public static function replay(AppointmentOperationFingerprint $fingerprint, AppointmentIdempotencyRecord $record): self
    {
        return new self(self::REPLAY, false, $record->originalHttpStatus(), $fingerprint, $record);
    }

    public static function conflict(AppointmentOperationFingerprint $fingerprint, AppointmentIdempotencyRecord $record): self
    {
        return new self(self::CONFLICT, false, 409, $fingerprint, $record);
    }

    public function status(): string { return $this->status; }
    public function canContinue(): bool { return $this->canContinue; }
    public function httpStatus(): int { return $this->httpStatus; }
    public function fingerprint(): AppointmentOperationFingerprint { return $this->fingerprint; }
    public function record(): ?AppointmentIdempotencyRecord { return $this->record; }
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'can_continue' => $this->canContinue,
            'http_status' => $this->httpStatus,
            'fingerprint' => $this->fingerprint->value(),
            'original_result_digest' => $this->record?->resultDigest(),
            'aggregate_version_result' => $this->record?->aggregateVersionResult(),
        ];
    }
}
