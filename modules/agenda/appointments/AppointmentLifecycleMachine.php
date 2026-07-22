<?php
declare(strict_types=1);

namespace Agenda\Appointments;

final class AppointmentLifecycleMachine
{
    public function __construct(
        private readonly AppointmentIdempotencyGuard $idempotencyGuard = new AppointmentIdempotencyGuard(),
        private readonly AppointmentConcurrencyGuard $concurrencyGuard = new AppointmentConcurrencyGuard()
    ) {}

    public function evaluate(
        AppointmentSnapshot $snapshot,
        AppointmentTransitionCommand $command,
        ?AppointmentIdempotencyRecord $idempotencyRecord,
        array $activeSlotClaims,
        AppointmentLifecycleDefinition $definition
    ): AppointmentLifecycleDecision {
        $idempotency = $this->idempotencyGuard->evaluate($command, $idempotencyRecord);
        if ($idempotency->status() === AppointmentIdempotencyDecision::REPLAY) {
            $record = $idempotency->record();
            if ($record === null) return AppointmentLifecycleDecision::failure('idempotency_conflict', AppointmentIdempotencyDecision::CONFLICT);
            return AppointmentLifecycleDecision::replay($record);
        }
        if ($idempotency->status() === AppointmentIdempotencyDecision::CONFLICT) {
            return AppointmentLifecycleDecision::failure('idempotency_conflict', AppointmentIdempotencyDecision::CONFLICT);
        }
        if ($snapshot->lifecycleVersion() !== $definition->version()) {
            return AppointmentLifecycleDecision::failure('lifecycle_version_mismatch');
        }
        if ($snapshot->appointmentId() !== $command->appointmentId()) {
            return AppointmentLifecycleDecision::failure('appointment_mismatch');
        }
        if ($snapshot->aggregateVersion() !== $command->expectedAggregateVersion()) {
            return AppointmentLifecycleDecision::failure('aggregate_version_conflict');
        }
        if ($snapshot->state() !== $command->fromState()) {
            return AppointmentLifecycleDecision::failure('state_mismatch');
        }
        $transition = $definition->evaluate($command->fromState(), $command->toState());
        if (!$transition->allowed()) return AppointmentLifecycleDecision::failure($transition->reason());

        $slot = $command->requestedSlot() ?? $snapshot->slot();
        if ($slot->profileId() !== $snapshot->profileId() || $slot->consultorioId() !== $snapshot->consultorioId()) {
            return AppointmentLifecycleDecision::failure('invalid_slot');
        }
        if ($definition->isTerminal($command->toState())
            && $command->requestedSlot() !== null
            && $slot->slotKey() !== $snapshot->slot()->slotKey()) {
            return AppointmentLifecycleDecision::failure('invalid_slot');
        }
        $occupiesSlot = $definition->occupiesSlot($command->toState());
        $concurrency = $this->concurrencyGuard->evaluate($slot, $activeSlotClaims, $snapshot->appointmentId(), $occupiesSlot);
        if (!$concurrency->allowed()) {
            return AppointmentLifecycleDecision::failure($concurrency->reason(), AppointmentIdempotencyDecision::NEW_OPERATION, $concurrency);
        }

        $nextVersion = $snapshot->aggregateVersion() + 1;
        $nextSnapshot = new AppointmentSnapshot(
            $snapshot->appointmentId(),
            $snapshot->profileId(),
            $snapshot->consultorioId(),
            $command->toState(),
            $nextVersion,
            $slot,
            $definition->version()
        );
        $event = AppointmentLifecycleEvent::forTransition($snapshot, $command, $slot);
        $claim = new AppointmentSlotClaim($snapshot->appointmentId(), $slot, $command->toState(), $nextVersion, $occupiesSlot);
        $resultDigest = AppointmentValue::digest([
            'appointment_id' => $snapshot->appointmentId(),
            'aggregate_version_result' => $nextVersion,
            'state_result' => $command->toState(),
            'slot_key' => $slot->slotKey(),
            'event_id' => $event->eventId(),
            'outcome_code' => 'transition_applied',
            'http_status' => 200,
        ]);
        $newRecord = new AppointmentIdempotencyRecord(
            $command->idempotencyKey()->value(),
            $command->operationId(),
            $idempotency->fingerprint()->value(),
            $snapshot->appointmentId(),
            'transition_applied',
            200,
            $resultDigest,
            $nextVersion,
            $command->occurredAt()
        );
        return AppointmentLifecycleDecision::success(
            $nextSnapshot,
            $event,
            $claim,
            new AppointmentMutationPlan(),
            $newRecord,
            $concurrency
        );
    }
}
