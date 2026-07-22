<?php
declare(strict_types=1);

namespace Agenda\Appointments;

use Agenda\Contracts\AppointmentLifecycleContract;

final readonly class AppointmentSlotClaim
{
    private string $appointmentId;

    public function __construct(
        string $appointmentId,
        private AppointmentSlotIdentity $slot,
        private string $state,
        private int $aggregateVersion,
        private bool $active
    ) {
        $this->appointmentId = AppointmentValue::scopedIdentity($appointmentId, 'invalid_claim');
        try { AppointmentLifecycleContract::assertState($state); }
        catch (\InvalidArgumentException) { throw new AppointmentDomainException('invalid_claim'); }
        if ($aggregateVersion < 1) throw new AppointmentDomainException('invalid_claim');
        if ($active && !in_array($state, ['tentative', 'pending_otp', 'pending', 'scheduled', 'confirmed'], true)) {
            throw new AppointmentDomainException('invalid_claim', 'terminal states cannot have active claims');
        }
    }

    public function appointmentId(): string { return $this->appointmentId; }
    public function slot(): AppointmentSlotIdentity { return $this->slot; }
    public function state(): string { return $this->state; }
    public function aggregateVersion(): int { return $this->aggregateVersion; }
    public function active(): bool { return $this->active; }
    public function toArray(): array
    {
        return [
            'appointment_id' => $this->appointmentId,
            'slot' => $this->slot->toArray(),
            'state' => $this->state,
            'aggregate_version' => $this->aggregateVersion,
            'active' => $this->active,
        ];
    }
}
