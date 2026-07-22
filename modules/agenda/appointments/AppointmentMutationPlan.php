<?php
declare(strict_types=1);

namespace Agenda\Appointments;

final readonly class AppointmentMutationPlan
{
    private const STEPS = [
        'begin_transaction',
        'lock_idempotency_key',
        'resolve_idempotency',
        'lock_appointment',
        'lock_slot_scope',
        'verify_expected_version',
        'verify_lifecycle_transition',
        'verify_active_slot_uniqueness',
        'persist_appointment',
        'append_lifecycle_event',
        'persist_idempotency_result',
        'commit',
    ];

    public function steps(): array { return self::STEPS; }
    public function failureAction(): string { return 'rollback'; }
    public function transactionRequired(): bool { return true; }
    public function idempotencyLockRequired(): bool { return true; }
    public function appointmentLockRequired(): bool { return true; }
    public function slotLockRequired(): bool { return true; }
    public function activeSlotUniqueConstraintRequired(): bool { return true; }
    public function appendEventInSameTransaction(): bool { return true; }
    public function idempotencyResultInSameTransaction(): bool { return true; }
    public function rollbackRequired(): bool { return true; }
    public function executesOperations(): bool { return false; }
    public function toArray(): array
    {
        return [
            'steps' => self::STEPS,
            'on_failure' => 'rollback',
            'transaction_required' => true,
            'idempotency_lock_required' => true,
            'appointment_lock_required' => true,
            'slot_lock_required' => true,
            'active_slot_unique_constraint_required' => true,
            'append_event_in_same_transaction' => true,
            'idempotency_result_in_same_transaction' => true,
            'executes_operations' => false,
        ];
    }
}
