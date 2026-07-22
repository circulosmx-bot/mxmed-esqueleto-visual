<?php
declare(strict_types=1);

namespace Agenda\Appointments;

use Agenda\Contracts\AppointmentLifecycleContract;

final readonly class AppointmentSnapshot
{
    private string $appointmentId;
    private string $profileId;
    private string $consultorioId;

    public function __construct(
        string $appointmentId,
        string $profileId,
        string $consultorioId,
        private string $state,
        private int $aggregateVersion,
        private AppointmentSlotIdentity $slot,
        private int $lifecycleVersion
    ) {
        $this->appointmentId = AppointmentValue::scopedIdentity($appointmentId, 'appointment_mismatch');
        $this->profileId = AppointmentValue::scopedIdentity($profileId, 'invalid_slot');
        $this->consultorioId = AppointmentValue::scopedIdentity($consultorioId, 'invalid_slot');
        try { AppointmentLifecycleContract::assertState($state); }
        catch (\InvalidArgumentException) { throw new AppointmentDomainException('unknown_appointment_state'); }
        if ($aggregateVersion < 1) throw new AppointmentDomainException('aggregate_version_conflict');
        if ($lifecycleVersion !== AppointmentLifecycleDefinition::VERSION) throw new AppointmentDomainException('lifecycle_version_mismatch');
        if ($slot->profileId() !== $this->profileId || $slot->consultorioId() !== $this->consultorioId) {
            throw new AppointmentDomainException('invalid_slot', 'snapshot and slot identities differ');
        }
    }

    public function appointmentId(): string { return $this->appointmentId; }
    public function profileId(): string { return $this->profileId; }
    public function consultorioId(): string { return $this->consultorioId; }
    public function state(): string { return $this->state; }
    public function aggregateVersion(): int { return $this->aggregateVersion; }
    public function slot(): AppointmentSlotIdentity { return $this->slot; }
    public function lifecycleVersion(): int { return $this->lifecycleVersion; }
    public function toArray(): array
    {
        return [
            'appointment_id' => $this->appointmentId,
            'profile_id' => $this->profileId,
            'consultorio_id' => $this->consultorioId,
            'state' => $this->state,
            'aggregate_version' => $this->aggregateVersion,
            'slot' => $this->slot->toArray(),
            'lifecycle_version' => $this->lifecycleVersion,
        ];
    }
}
