<?php
declare(strict_types=1);

namespace Agenda\Appointments;

use Agenda\Contracts\AppointmentLifecycleContract;

final readonly class AppointmentTransitionCommand
{
    private string $operationId;
    private AppointmentIdempotencyKey $idempotencyKey;
    private string $correlationId;
    private string $appointmentId;
    private string $reasonCode;
    private string $actorRealId;
    private string $actorEffectiveId;
    private string $occurredAt;

    public function __construct(
        string $operationId,
        string $idempotencyKey,
        string $correlationId,
        string $appointmentId,
        private int $expectedAggregateVersion,
        private string $fromState,
        private string $toState,
        string $reasonCode,
        string $actorRealId,
        string $actorEffectiveId,
        string $occurredAt,
        private ?AppointmentSlotIdentity $requestedSlot = null
    ) {
        $this->operationId = AppointmentValue::safeIdentifier($operationId, 'invalid_idempotency_key');
        $this->idempotencyKey = new AppointmentIdempotencyKey($idempotencyKey);
        $this->correlationId = AppointmentValue::safeIdentifier($correlationId, 'invalid_idempotency_key');
        $this->appointmentId = AppointmentValue::scopedIdentity($appointmentId, 'appointment_mismatch');
        if ($expectedAggregateVersion < 1) throw new AppointmentDomainException('aggregate_version_conflict');
        try {
            AppointmentLifecycleContract::assertState($fromState);
            AppointmentLifecycleContract::assertState($toState);
        } catch (\InvalidArgumentException) {
            throw new AppointmentDomainException('unknown_appointment_state');
        }
        $this->reasonCode = AppointmentValue::safeIdentifier($reasonCode, 'invalid_reason');
        $this->actorRealId = AppointmentValue::safeIdentifier($actorRealId, 'invalid_actor');
        $this->actorEffectiveId = AppointmentValue::safeIdentifier($actorEffectiveId, 'invalid_actor');
        $this->occurredAt = AppointmentValue::canonicalUtc($occurredAt, 'invalid_timestamp');
    }

    public function operationId(): string { return $this->operationId; }
    public function idempotencyKey(): AppointmentIdempotencyKey { return $this->idempotencyKey; }
    public function correlationId(): string { return $this->correlationId; }
    public function appointmentId(): string { return $this->appointmentId; }
    public function expectedAggregateVersion(): int { return $this->expectedAggregateVersion; }
    public function fromState(): string { return $this->fromState; }
    public function toState(): string { return $this->toState; }
    public function reasonCode(): string { return $this->reasonCode; }
    public function actorRealId(): string { return $this->actorRealId; }
    public function actorEffectiveId(): string { return $this->actorEffectiveId; }
    public function occurredAt(): string { return $this->occurredAt; }
    public function requestedSlot(): ?AppointmentSlotIdentity { return $this->requestedSlot; }

    public function fingerprintFields(): array
    {
        return [
            'operation_id' => $this->operationId,
            'idempotency_key' => $this->idempotencyKey->value(),
            'correlation_id' => $this->correlationId,
            'appointment_id' => $this->appointmentId,
            'expected_aggregate_version' => $this->expectedAggregateVersion,
            'from_state' => $this->fromState,
            'to_state' => $this->toState,
            'reason_code' => $this->reasonCode,
            'actor_real_id' => $this->actorRealId,
            'actor_effective_id' => $this->actorEffectiveId,
            'occurred_at' => $this->occurredAt,
            'requested_slot' => $this->requestedSlot?->keyFields(),
        ];
    }

    public function toArray(): array
    {
        $value = $this->fingerprintFields();
        $value['requested_slot'] = $this->requestedSlot?->toArray();
        return $value;
    }
}
