<?php
declare(strict_types=1);

namespace Agenda\Appointments;

final readonly class AppointmentLifecycleEvent
{
    public const EVENT_TYPE = 'appointment_lifecycle_transition';

    private string $eventId;

    private function __construct(
        string $eventId,
        private string $appointmentId,
        private int $sequence,
        private int $lifecycleVersion,
        private string $fromState,
        private string $toState,
        private string $operationId,
        private string $correlationId,
        private string $actorRealId,
        private string $actorEffectiveId,
        private string $reasonCode,
        private string $occurredAt,
        private ?string $slotKey,
        private string $eventType = self::EVENT_TYPE
    ) {
        $this->eventId = $eventId;
    }

    public static function forTransition(AppointmentSnapshot $snapshot, AppointmentTransitionCommand $command, AppointmentSlotIdentity $slot): self
    {
        $sequence = $snapshot->aggregateVersion() + 1;
        $fields = [
            'appointment_id' => $snapshot->appointmentId(),
            'sequence' => $sequence,
            'lifecycle_version' => $snapshot->lifecycleVersion(),
            'from_state' => $command->fromState(),
            'to_state' => $command->toState(),
            'operation_id' => $command->operationId(),
            'correlation_id' => $command->correlationId(),
            'actor_real_id' => $command->actorRealId(),
            'actor_effective_id' => $command->actorEffectiveId(),
            'reason_code' => $command->reasonCode(),
            'occurred_at' => $command->occurredAt(),
            'slot_key' => $slot->slotKey(),
            'event_type' => self::EVENT_TYPE,
        ];
        return new self(
            'evt:' . AppointmentValue::digest($fields),
            $fields['appointment_id'],
            $fields['sequence'],
            $fields['lifecycle_version'],
            $fields['from_state'],
            $fields['to_state'],
            $fields['operation_id'],
            $fields['correlation_id'],
            $fields['actor_real_id'],
            $fields['actor_effective_id'],
            $fields['reason_code'],
            $fields['occurred_at'],
            $fields['slot_key'],
            $fields['event_type']
        );
    }

    public function eventId(): string { return $this->eventId; }
    public function appointmentId(): string { return $this->appointmentId; }
    public function sequence(): int { return $this->sequence; }
    public function lifecycleVersion(): int { return $this->lifecycleVersion; }
    public function fromState(): string { return $this->fromState; }
    public function toState(): string { return $this->toState; }
    public function operationId(): string { return $this->operationId; }
    public function correlationId(): string { return $this->correlationId; }
    public function actorRealId(): string { return $this->actorRealId; }
    public function actorEffectiveId(): string { return $this->actorEffectiveId; }
    public function reasonCode(): string { return $this->reasonCode; }
    public function occurredAt(): string { return $this->occurredAt; }
    public function slotKey(): ?string { return $this->slotKey; }
    public function eventType(): string { return $this->eventType; }
    public function appendOnly(): bool { return true; }
    public function clinicalEvent(): bool { return false; }
    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId,
            'appointment_id' => $this->appointmentId,
            'sequence' => $this->sequence,
            'lifecycle_version' => $this->lifecycleVersion,
            'from_state' => $this->fromState,
            'to_state' => $this->toState,
            'operation_id' => $this->operationId,
            'correlation_id' => $this->correlationId,
            'actor_real_id' => $this->actorRealId,
            'actor_effective_id' => $this->actorEffectiveId,
            'reason_code' => $this->reasonCode,
            'occurred_at' => $this->occurredAt,
            'slot_key' => $this->slotKey,
            'event_type' => $this->eventType,
            'append_only' => true,
            'clinical_event' => false,
        ];
    }
}
