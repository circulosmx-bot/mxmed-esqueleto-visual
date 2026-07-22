<?php
declare(strict_types=1);

namespace Patients\Identity\Persistence;

final readonly class PatientIdentityPersistencePolicy
{
    public function contractId(): string { return 'pg03-patient-identity-persistence'; }
    public function contractVersion(): int { return 1; }
    public function transactionRequired(): bool { return true; }
    public function lockOrder(): array { return ['resolution_fingerprint', 'legacy_reference', 'candidate_set', 'audit_stream']; }
    public function idempotencyStates(): array { return ['processing', 'completed', 'failed']; }
    public function auditInSameTransaction(): bool { return true; }
    public function automaticMerge(): bool { return false; }
    public function directSqlExecution(): bool { return false; }
    public function runtimeWiring(): bool { return false; }
    public function clinicalMutation(): bool { return false; }
    public function foreignKeysToLegacyPatients(): bool { return false; }
    public function defaultRollout(): string { return 'disabled'; }
    public function executesOperations(): bool { return false; }

    public function toArray(): array
    {
        return [
            'contract_id' => $this->contractId(),
            'contract_version' => $this->contractVersion(),
            'transaction_required' => $this->transactionRequired(),
            'lock_order' => $this->lockOrder(),
            'idempotency_states' => $this->idempotencyStates(),
            'audit_in_same_transaction' => $this->auditInSameTransaction(),
            'automatic_merge' => $this->automaticMerge(),
            'direct_sql_execution' => $this->directSqlExecution(),
            'runtime_wiring' => $this->runtimeWiring(),
            'clinical_mutation' => $this->clinicalMutation(),
            'foreign_keys_to_legacy_patients' => $this->foreignKeysToLegacyPatients(),
            'default_rollout' => $this->defaultRollout(),
            'execution' => false,
        ];
    }
}
