<?php
declare(strict_types=1);

namespace Patients\Identity;

final readonly class PatientIdentityMutationPlan
{
    private const STEPS = ['begin_transaction', 'lock_resolution_fingerprint', 'verify_gate8b_actor_authority', 'resolve_idempotency', 'load_canonical_candidate_set', 'verify_candidate_set_integrity', 'evaluate_exact_identity_evidence', 'detect_ambiguity_and_conflicts', 'enforce_automatic_merge_disabled', 'choose_existing_or_create_minimal_plan', 'delegate_to_patients_domain_adapter', 'append_identity_audit_event', 'persist_idempotency_result', 'commit'];
    public function steps(): array { return self::STEPS; }
    public function failureAction(): string { return 'rollback'; }
    public function transactionRequired(): bool { return true; }
    public function resolutionLockRequired(): bool { return true; }
    public function idempotencyLockRequired(): bool { return true; }
    public function gate8bAuthorityRequired(): bool { return true; }
    public function canonicalCandidateLoadRequired(): bool { return true; }
    public function automaticMergeAllowed(): bool { return false; }
    public function directPatientCreationAllowed(): bool { return false; }
    public function directPatientUpdateAllowed(): bool { return false; }
    public function directLinkCreationAllowed(): bool { return false; }
    public function directCareRecordMutationAllowed(): bool { return false; }
    public function directSqlAllowed(): bool { return false; }
    public function auditInSameTransaction(): bool { return true; }
    public function executesOperations(): bool { return false; }
    public function requestDirectPatientCreation(): never { throw new PatientIdentityDomainException('unauthorized_identity_mutation'); }
    public function requestDirectPatientChange(): never { throw new PatientIdentityDomainException('unauthorized_identity_mutation'); }
    public function requestDirectLinkCreation(): never { throw new PatientIdentityDomainException('unauthorized_identity_mutation'); }
    public function requestDirectCareRecordMutation(): never { throw new PatientIdentityDomainException('unauthorized_identity_mutation'); }
    public function requestDirectSql(): never { throw new PatientIdentityDomainException('unauthorized_identity_mutation'); }
    public function toArray(): array { return ['steps' => self::STEPS, 'on_failure' => 'rollback', 'transaction_required' => true, 'resolution_lock_required' => true, 'idempotency_lock_required' => true, 'gate8b_authority_required' => true, 'canonical_candidate_load_required' => true, 'automatic_merge_allowed' => false, 'direct_patient_creation_allowed' => false, 'direct_patient_update_allowed' => false, 'direct_link_creation_allowed' => false, 'direct_clinical_mutation_allowed' => false, 'direct_sql_allowed' => false, 'audit_in_same_transaction' => true, 'executes_operations' => false]; }
}
