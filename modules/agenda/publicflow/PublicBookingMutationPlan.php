<?php
declare(strict_types=1);

namespace Agenda\PublicFlow;

final readonly class PublicBookingMutationPlan
{
    private const STEPS = ['begin_transaction', 'lock_public_intent', 'lock_rate_limit_scope', 'lock_active_otp_challenge', 'resolve_idempotency', 'verify_binding_fingerprint', 'verify_challenge_state', 'verify_expiration', 'verify_attempt_budget', 'verify_credential', 'persist_challenge_result', 'persist_or_replay_verification_grant', 'delegate_appointment_mutation_to_gate8d', 'append_public_audit_event', 'persist_idempotency_result', 'commit'];
    public function steps(): array { return self::STEPS; }
    public function failureAction(): string { return 'rollback'; }
    public function transactionRequired(): bool { return true; }
    public function publicIntentLockRequired(): bool { return true; }
    public function rateLimitScopeLockRequired(): bool { return true; }
    public function challengeLockRequired(): bool { return true; }
    public function idempotencyLockRequired(): bool { return true; }
    public function gate8dDelegationRequired(): bool { return true; }
    public function appendAuditInSameTransaction(): bool { return true; }
    public function grantInSameTransaction(): bool { return true; }
    public function directAppointmentWriteAllowed(): bool { return false; }
    public function directSqlAllowed(): bool { return false; }
    public function executesOperations(): bool { return false; }
    public function toArray(): array
    {
        return ['steps' => self::STEPS, 'on_failure' => 'rollback', 'transaction_required' => true, 'public_intent_lock_required' => true, 'rate_limit_scope_lock_required' => true, 'challenge_lock_required' => true, 'idempotency_lock_required' => true, 'gate8d_delegation_required' => true, 'append_audit_in_same_transaction' => true, 'grant_in_same_transaction' => true, 'direct_appointment_write_allowed' => false, 'direct_sql_allowed' => false, 'executes_operations' => false];
    }
}
