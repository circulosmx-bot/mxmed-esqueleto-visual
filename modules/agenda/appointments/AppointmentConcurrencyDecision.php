<?php
declare(strict_types=1);

namespace Agenda\Appointments;

final readonly class AppointmentConcurrencyDecision
{
    private function __construct(
        private bool $allowed,
        private string $reason,
        private int $httpStatus,
        private ?string $conflictingAppointmentId,
        private string $requestedSlotKey,
        private string $lockScope,
        private string $uniqueClaimKey
    ) {}

    public static function allow(AppointmentSlotIdentity $slot): self
    {
        return new self(true, 'allowed', 200, null, $slot->slotKey(), $slot->lockScope(), $slot->uniqueClaimKey());
    }

    public static function deny(string $reason, AppointmentSlotIdentity $slot, ?string $conflictingAppointmentId = null): self
    {
        return new self(false, $reason, 409, $conflictingAppointmentId, $slot->slotKey(), $slot->lockScope(), $slot->uniqueClaimKey());
    }

    public function allowed(): bool { return $this->allowed; }
    public function reason(): string { return $this->reason; }
    public function httpStatus(): int { return $this->httpStatus; }
    public function conflictingAppointmentId(): ?string { return $this->conflictingAppointmentId; }
    public function requestedSlotKey(): string { return $this->requestedSlotKey; }
    public function lockScope(): string { return $this->lockScope; }
    public function uniqueClaimKey(): string { return $this->uniqueClaimKey; }
    public function toArray(): array
    {
        return [
            'allowed' => $this->allowed,
            'reason' => $this->reason,
            'http_status' => $this->httpStatus,
            'conflicting_appointment_id' => $this->conflictingAppointmentId,
            'requested_slot_key' => $this->requestedSlotKey,
            'lock_scope' => $this->lockScope,
            'unique_claim_key' => $this->uniqueClaimKey,
        ];
    }
}
