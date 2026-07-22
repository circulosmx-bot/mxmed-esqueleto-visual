<?php
declare(strict_types=1);

foreach (glob(__DIR__ . '/../identity/*.php') as $file) require_once $file;

use Patients\Identity\CanonicalPatientId;
use Patients\Identity\LegacyPatientReference;
use Patients\Identity\PatientDuplicateReview;
use Patients\Identity\PatientIdentityAuditEvent;
use Patients\Identity\PatientIdentityCandidate;
use Patients\Identity\PatientIdentityCandidateSet;
use Patients\Identity\PatientIdentityDomainException;
use Patients\Identity\PatientIdentityEvidence;
use Patients\Identity\PatientIdentityMutationPlan;
use Patients\Identity\PatientIdentityPolicy;
use Patients\Identity\PatientIdentityResolutionDecision;
use Patients\Identity\PatientIdentityResolutionRequest;
use Patients\Identity\PatientIdentityResolver;
use Patients\Identity\PatientMergePolicy;

function gate8fAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function gate8fThrows(callable $callback, string $reason, string $message): void
{
    try { $callback(); }
    catch (PatientIdentityDomainException $error) {
        if ($error->reason() === $reason) return;
        throw new RuntimeException($message . ' (' . $error->reason() . ')');
    }
    throw new RuntimeException($message);
}

function gate8fRejectsMetadata(callable $callback, string $reason, string $message, int &$caseCount): void
{
    $created = null;
    try { $created = $callback(); }
    catch (PatientIdentityDomainException $error) {
        if ($error->reason() !== $reason) throw new RuntimeException($message . ' (' . $error->reason() . ')');
        gate8fAssert($created === null, $message . ' (partial object)');
        $caseCount++;
        return;
    }
    throw new RuntimeException($message . ' (accepted or redacted)');
}

function gate8fRef(string $label): string { return hash('sha256', 'gate8f:' . $label); }

function gate8fEvidence(string $name, ?string $birth = null, ?string $sex = null, ?string $phone = null, ?string $email = null): PatientIdentityEvidence
{
    return new PatientIdentityEvidence(gate8fRef($name), $birth === null ? null : gate8fRef($birth), $sex, $phone === null ? null : gate8fRef($phone), $email === null ? null : gate8fRef($email));
}

function gate8fCandidate(string $id, PatientIdentityEvidence $evidence, bool $eligible = true, int $version = 1): PatientIdentityCandidate
{
    return new PatientIdentityCandidate(new CanonicalPatientId($id), $evidence, $version, $eligible);
}

function gate8fLegacyRequest(PatientIdentityEvidence $evidence, string $operation = 'operation-gate8f', string $source = 'legacy_bridge'): PatientIdentityResolutionRequest
{
    return new PatientIdentityResolutionRequest($operation, 'correlation-gate8f', $source, 'legacy_patient_key_hash', null, new LegacyPatientReference(gate8fRef('legacy-key')), $evidence, 'account-gate8f', 'operator-gate8f', '2026-07-21T11:00:00-06:00');
}

function gate8fCanonicalRequest(string $patientId, string $operation = 'operation-canonical-01'): PatientIdentityResolutionRequest
{
    return new PatientIdentityResolutionRequest($operation, 'correlation-gate8f', 'private_authenticated', 'canonical_patient_id', new CanonicalPatientId($patientId), null, null, 'account-gate8f', 'operator-gate8f', '2026-07-21T11:00:00-06:00');
}

$policy = new PatientIdentityPolicy();
gate8fAssert($policy->contractId() === 'pg03-patient-identity-duplicates' && $policy->version() === 1, 'contract exact');
gate8fAssert($policy->canonicalSource() === 'patients_patients.patient_id' && $policy->ownerDomain() === 'modules/patients', 'canonical source exact');
gate8fAssert($policy->inputTypes() === ['canonical_patient_id', 'legacy_patient_key_hash'], 'input types exact');
gate8fAssert($policy->resolutionSources() === ['public_verified', 'private_authenticated', 'legacy_bridge'], 'sources exact');
gate8fAssert($policy->resultStates() === ['already_canonical', 'mapped_from_legacy', 'create_minimal_required', 'review_required', 'ambiguous', 'not_found', 'invalid_candidate_set'], 'results exact');
gate8fAssert(!$policy->automaticMergeAllowed() && !$policy->manualMergeImplemented() && !$policy->probabilisticMatchingImplemented(), 'unsafe automation disabled');
gate8fAssert(!$policy->rawLegacyKeyAccepted() && !$policy->rawContactAccepted() && !$policy->rawPatientNameAccepted() && !$policy->clinicalEncounter(), 'privacy and care boundary');
gate8fAssert(PatientIdentityPolicy::identifier('Legacy.identifier:01', 'invalid_resolution_request') === 'Legacy.identifier:01', 'generic identifier behavior preserved');
foreach (['operation-gate8f', 'operation-legacy-priority-01', 'op:8f'] as $value) gate8fAssert(PatientIdentityPolicy::operationId($value) === $value, 'operation namespace accepted');
foreach (['correlation-gate8f', 'corr-8f', 'request:2026a', 'req_identity_01'] as $value) gate8fAssert(PatientIdentityPolicy::correlationId($value) === $value, 'correlation namespace accepted');
foreach (['account-gate8f', 'acct-8f', 'operator-gate8f', 'doctor-990099', 'system-gate8f', 'support-8f', 'user-8f', 'profile-8f'] as $value) gate8fAssert(PatientIdentityPolicy::actorReference($value) === $value, 'actor namespace accepted');
foreach ($policy->resultStates() as $value) gate8fAssert(PatientIdentityPolicy::resultState($value) === $value, 'result state accepted');
$validReasons = ['already_canonical', 'canonical_patient_not_found', 'candidate_not_eligible', 'unique_strong_identity_match', 'multiple_strong_candidates', 'identity_evidence_conflict', 'weak_identity_evidence', 'no_identity_candidate', 'invalid_candidate_set'];
foreach ($validReasons as $value) gate8fAssert(PatientIdentityPolicy::decisionReason($value) === $value, 'decision reason accepted');
$coherentPairs = ['already_canonical' => ['already_canonical'], 'mapped_from_legacy' => ['unique_strong_identity_match'], 'create_minimal_required' => ['no_identity_candidate'], 'review_required' => ['candidate_not_eligible', 'identity_evidence_conflict', 'weak_identity_evidence'], 'ambiguous' => ['multiple_strong_candidates'], 'not_found' => ['canonical_patient_not_found'], 'invalid_candidate_set' => ['invalid_candidate_set']];
foreach ($coherentPairs as $status => $reasons) foreach ($reasons as $reason) PatientIdentityPolicy::assertStatusReasonCoherence($status, $reason);

$canonical = new CanonicalPatientId('p_Ana.01:test');
gate8fAssert($canonical->value() === 'p_Ana.01:test', 'canonical value preserved');
gate8fAssert(strlen((new CanonicalPatientId('p_' . str_repeat('A', 62)))->value()) === 64, 'canonical maximum accepted');
foreach (['patient-123', 'p_', 'p_bad value', ' p_valid', "p_bad\n", 'p_' . str_repeat('A', 63)] as $invalid) gate8fThrows(fn() => new CanonicalPatientId($invalid), 'invalid_canonical_patient_id', 'invalid canonical rejected');

$legacy = new LegacyPatientReference(gate8fRef('legacy-valid'));
gate8fAssert(strlen($legacy->legacyKeyHash()) === 64, 'legacy hash valid');
gate8fThrows(fn() => LegacyPatientReference::fromRaw('legacy-name|date|sex'), 'raw_legacy_patient_key_forbidden', 'raw legacy rejected');
foreach (['ana@example.mx', '+5214491234567', strtoupper(gate8fRef('legacy-valid')), 'abc'] as $invalid) gate8fThrows(fn() => new LegacyPatientReference($invalid), 'invalid_legacy_patient_reference', 'invalid legacy reference rejected');

$input = gate8fEvidence('ana-name', 'ana-birth', 'female', 'ana-phone', 'ana-email');
gate8fAssert($input->hasAdditionalReference(), 'additional evidence present');
gate8fAssert((new PatientIdentityEvidence(gate8fRef('name-only')))->hasAdditionalReference() === false, 'name only evidence represented');
foreach (['female', 'male', 'other', 'undisclosed', null] as $sex) gate8fAssert((new PatientIdentityEvidence(gate8fRef('sex-' . ($sex ?? 'null')), null, $sex))->sex() === $sex, 'sex allow list');
gate8fThrows(fn() => new PatientIdentityEvidence('Ana Pérez'), 'invalid_identity_evidence', 'raw name rejected');
gate8fThrows(fn() => new PatientIdentityEvidence(gate8fRef('name'), '1990-01-20'), 'invalid_identity_evidence', 'raw birthdate rejected');
gate8fThrows(fn() => new PatientIdentityEvidence(gate8fRef('name'), null, null, '+5214491234567'), 'invalid_identity_evidence', 'raw phone rejected');
gate8fThrows(fn() => new PatientIdentityEvidence(gate8fRef('name'), null, null, null, 'ana@example.mx'), 'invalid_identity_evidence', 'raw email rejected');
gate8fThrows(fn() => new PatientIdentityEvidence(gate8fRef('name'), null, 'unknown'), 'invalid_identity_evidence', 'invalid sex rejected');

$tier1 = gate8fCandidate('p_tier1', gate8fEvidence('other-name', 'ana-birth', 'male', 'ana-phone'));
$tier2 = gate8fCandidate('p_tier2', gate8fEvidence('ana-name', null, null, 'ana-phone'));
$tier3 = gate8fCandidate('p_tier3', gate8fEvidence('ana-name', 'ana-birth', 'female', 'other-phone'));
$setOrdered = new PatientIdentityCandidateSet([$tier1, $tier2, $tier3]);
$setPermuted = new PatientIdentityCandidateSet([$tier3, $tier1, $tier2]);
gate8fAssert($setOrdered->patientIds() === ['p_tier1', 'p_tier2', 'p_tier3'], 'candidate set sorted');
gate8fAssert($setOrdered->digest() === $setPermuted->digest(), 'candidate set permutation stable');
gate8fThrows(fn() => new PatientIdentityCandidateSet([$tier1, $tier1]), 'duplicate_candidate_id', 'duplicate candidate rejected');
gate8fThrows(fn() => gate8fCandidate('p_invalid_version', $input, true, 0), 'invalid_identity_candidate', 'candidate version rejected');

$metadataInjectionCases = 0;
$requestMetadataInjections = [
    'AnaPerez',
    'ana_perez',
    'female',
    '1990-01-20',
    'ana@example.mx',
    '+5214491234567',
    'operation-AnaPerez',
    'operation-ana_perez',
    'operation-female',
    'operation-1990-01-20',
    'operation-5214491234567',
    'account-AnaPerez',
    'account-female',
    'doctor-1990-01-20',
    'doctor-5214491234567',
    'operation-bad value1',
    "operation-bad\n1",
    'operation',
    'operation-',
    'operation-' . str_repeat('a', 119) . '1',
];
foreach ($requestMetadataInjections as $injection) {
    gate8fRejectsMetadata(fn() => new PatientIdentityResolutionRequest($injection, 'correlation-gate8f', 'legacy_bridge', 'legacy_patient_key_hash', null, $legacy, $input, 'account-gate8f', 'operator-gate8f', '2026-07-21T11:00:00-06:00'), 'invalid_operation_id', 'operation metadata injection rejected', $metadataInjectionCases);
    gate8fRejectsMetadata(fn() => new PatientIdentityResolutionRequest('operation-metadata-test-01', $injection, 'legacy_bridge', 'legacy_patient_key_hash', null, $legacy, $input, 'account-gate8f', 'operator-gate8f', '2026-07-21T11:00:00-06:00'), 'invalid_correlation_id', 'correlation metadata injection rejected', $metadataInjectionCases);
    gate8fRejectsMetadata(fn() => new PatientIdentityResolutionRequest('operation-metadata-test-01', 'correlation-gate8f', 'legacy_bridge', 'legacy_patient_key_hash', null, $legacy, $input, $injection, 'operator-gate8f', '2026-07-21T11:00:00-06:00'), 'invalid_actor', 'real actor metadata injection rejected', $metadataInjectionCases);
    gate8fRejectsMetadata(fn() => new PatientIdentityResolutionRequest('operation-metadata-test-01', 'correlation-gate8f', 'legacy_bridge', 'legacy_patient_key_hash', null, $legacy, $input, 'account-gate8f', $injection, '2026-07-21T11:00:00-06:00'), 'invalid_actor', 'effective actor metadata injection rejected', $metadataInjectionCases);
}

$metadataAuditRequest = gate8fLegacyRequest($input, 'operation-metadata-audit-01');
$metadataEmptySet = new PatientIdentityCandidateSet([]);
foreach (['AnaPerez', 'ana_perez', 'female', '1990-01-20', 'ana@example.mx', '+5214491234567', 'unknown_outcome'] as $injection) {
    gate8fRejectsMetadata(fn() => new PatientIdentityAuditEvent('patient_identity_create_minimal_required', $metadataAuditRequest, $metadataEmptySet, null, [], $injection, 'no_match', false, true), 'invalid_identity_outcome', 'audit outcome injection rejected', $metadataInjectionCases);
}
foreach (['AnaPerez', 'ana_perez', 'female', '1990-01-20', 'ana@example.mx', '+5214491234567', 'unknown_reason'] as $injection) {
    gate8fRejectsMetadata(fn() => PatientIdentityResolutionDecision::create('create_minimal_required', $injection, null, 'no_match', null, $metadataEmptySet, $metadataAuditRequest), 'invalid_decision_reason', 'decision reason injection rejected', $metadataInjectionCases);
}
$statusReasonMismatches = [
    ['already_canonical', 'unique_strong_identity_match'],
    ['mapped_from_legacy', 'already_canonical'],
    ['create_minimal_required', 'weak_identity_evidence'],
    ['review_required', 'no_identity_candidate'],
    ['ambiguous', 'identity_evidence_conflict'],
    ['not_found', 'multiple_strong_candidates'],
    ['invalid_candidate_set', 'canonical_patient_not_found'],
];
foreach ($statusReasonMismatches as [$status, $reason]) {
    gate8fRejectsMetadata(fn() => PatientIdentityResolutionDecision::create($status, $reason, null, 'no_match', null, $metadataEmptySet, $metadataAuditRequest), 'identity_status_reason_mismatch', 'status reason mismatch rejected', $metadataInjectionCases);
}
gate8fAssert($metadataInjectionCases === (count($requestMetadataInjections) * 4) + 21, 'metadata injection count exact');

$resolver = new PatientIdentityResolver();
$existingDecision = $resolver->resolve(gate8fCanonicalRequest('p_tier1'), $setOrdered);
gate8fAssert($existingDecision->status() === 'already_canonical' && $existingDecision->resolvedPatientId()?->value() === 'p_tier1' && !$existingDecision->mutationAllowed(), 'canonical existing resolves');
$missingDecision = $resolver->resolve(gate8fCanonicalRequest('p_missing', 'operation-canonical-missing-01'), $setOrdered);
gate8fAssert($missingDecision->status() === 'not_found' && $missingDecision->resolvedPatientId() === null && !$missingDecision->createMinimalRequired(), 'canonical missing does not create');
$ineligible = gate8fCandidate('p_ineligible', gate8fEvidence('ineligible-name'), false);
$ineligibleDecision = $resolver->resolve(gate8fCanonicalRequest('p_ineligible', 'operation-canonical-ineligible-01'), new PatientIdentityCandidateSet([$ineligible]));
gate8fAssert($ineligibleDecision->status() === 'review_required' && $ineligibleDecision->duplicateReview()?->reasonCode() === 'candidate_not_eligible', 'canonical ineligible reviews');

$priorityDecision = $resolver->resolve(gate8fLegacyRequest($input, 'operation-legacy-priority-01'), $setPermuted);
gate8fAssert($priorityDecision->status() === 'mapped_from_legacy' && $priorityDecision->matchTier() === 'contact_birthdate_exact' && $priorityDecision->resolvedPatientId()?->value() === 'p_tier1', 'highest strong tier wins');
$tier2Decision = $resolver->resolve(gate8fLegacyRequest($input, 'operation-legacy-tier2-01'), new PatientIdentityCandidateSet([$tier2]));
gate8fAssert($tier2Decision->status() === 'mapped_from_legacy' && $tier2Decision->matchTier() === 'contact_name_exact', 'contact name strong maps');
$tier3Decision = $resolver->resolve(gate8fLegacyRequest($input, 'operation-legacy-tier3-01'), new PatientIdentityCandidateSet([$tier3]));
gate8fAssert($tier3Decision->status() === 'mapped_from_legacy' && $tier3Decision->matchTier() === 'name_birthdate_sex_exact', 'name birth sex strong maps');
$permutationDecision = $resolver->resolve(gate8fLegacyRequest($input, 'operation-legacy-order-01'), new PatientIdentityCandidateSet([$tier2, $tier1]));
$permutationDecisionReverse = $resolver->resolve(gate8fLegacyRequest($input, 'operation-legacy-order-01'), new PatientIdentityCandidateSet([$tier1, $tier2]));
gate8fAssert($permutationDecision->decisionDigest() === $permutationDecisionReverse->decisionDigest(), 'decision independent of candidate input order');

$weakNameBirth = gate8fCandidate('p_weak_name_birth', gate8fEvidence('ana-name', 'ana-birth'));
$weakContact = gate8fCandidate('p_weak_contact', gate8fEvidence('other-name', null, null, 'ana-phone'));
$weakName = gate8fCandidate('p_weak_name', gate8fEvidence('ana-name'));
foreach ([[$weakNameBirth, 'name_birthdate_exact'], [$weakContact, 'contact_only'], [$weakName, 'name_only']] as [$candidate, $tier]) {
    $decision = $resolver->resolve(gate8fLegacyRequest($input, 'operation-weak-' . $tier . '-01'), new PatientIdentityCandidateSet([$candidate]));
    gate8fAssert($decision->status() === 'review_required' && $decision->matchTier() === $tier && $decision->resolvedPatientId() === null && !$decision->createMinimalRequired() && $decision->duplicateReview()?->requiresHumanReview(), 'weak tier reviews');
}

$ambiguousA = gate8fCandidate('p_ambiguous_a', gate8fEvidence('first-name', 'ana-birth', null, 'ana-phone'));
$ambiguousB = gate8fCandidate('p_ambiguous_b', gate8fEvidence('second-name', 'ana-birth', null, 'ana-phone'));
$ambiguous = $resolver->resolve(gate8fLegacyRequest($input, 'operation-legacy-ambiguous-01'), new PatientIdentityCandidateSet([$ambiguousB, $ambiguousA]));
gate8fAssert($ambiguous->status() === 'ambiguous' && $ambiguous->resolvedPatientId() === null && $ambiguous->duplicateReview()?->candidatePatientIds() === ['p_ambiguous_a', 'p_ambiguous_b'] && !$ambiguous->mergeAllowed(), 'multiple strong candidates ambiguous');

$contactConflict = gate8fCandidate('p_contact_conflict', gate8fEvidence('other-name', 'different-birth', null, 'ana-phone'));
$contactConflictDecision = $resolver->resolve(gate8fLegacyRequest($input, 'operation-contact-conflict-01'), new PatientIdentityCandidateSet([$contactConflict]));
gate8fAssert($contactConflictDecision->status() === 'review_required' && $contactConflictDecision->reasonCode() === 'identity_evidence_conflict', 'contact birth conflict reviews');
$nameConflict = gate8fCandidate('p_name_conflict', gate8fEvidence('ana-name', 'different-birth'));
$nameConflictDecision = $resolver->resolve(gate8fLegacyRequest($input, 'operation-name-conflict-01'), new PatientIdentityCandidateSet([$nameConflict]));
gate8fAssert($nameConflictDecision->status() === 'review_required', 'name birth conflict reviews');
$strongIneligible = gate8fCandidate('p_strong_ineligible', gate8fEvidence('other-name', 'ana-birth', null, 'ana-phone'), false);
$strongIneligibleDecision = $resolver->resolve(gate8fLegacyRequest($input, 'operation-strong-ineligible-01'), new PatientIdentityCandidateSet([$strongIneligible]));
gate8fAssert($strongIneligibleDecision->status() === 'review_required', 'strong ineligible reviews');

$noMatch = $resolver->resolve(gate8fLegacyRequest($input, 'operation-legacy-no-match-01'), new PatientIdentityCandidateSet([]));
gate8fAssert($noMatch->status() === 'create_minimal_required' && $noMatch->eventualResolutionMode() === 'created_minimal_patient' && $noMatch->resolvedPatientId() === null && $noMatch->createMinimalRequired() && !$noMatch->mutationAllowed() && !$noMatch->mergeAllowed(), 'no candidate creates plan only');

$merge = new PatientMergePolicy();
gate8fAssert(!$merge->automaticMergeAllowed() && !$merge->manualMergeImplemented() && !$merge->survivorSelectionAllowed() && !$merge->sourcePatientDeletionAllowed() && !$merge->careRecordReassignmentAllowed() && !$merge->contactConsolidationAllowed() && !$merge->consentConsolidationAllowed() && !$merge->mergeEndpointAvailable(), 'merge fully disabled');
gate8fAssert($merge->reason() === 'MERGE_DISABLED_PENDING_SEPARATE_APPROVAL_AND_IMPLEMENTATION', 'merge reason exact');
gate8fThrows(fn() => $merge->requestMerge(), 'patient_merge_disabled', 'merge request rejected');
gate8fThrows(fn() => $merge->requestSurvivorSelection(), 'patient_merge_disabled', 'survivor selection rejected');
gate8fThrows(fn() => $merge->requestCareRecordReassignment(), 'patient_merge_disabled', 'care record reassignment rejected');

$audit = $priorityDecision->auditEvent();
$auditAgain = $resolver->resolve(gate8fLegacyRequest($input, 'operation-legacy-priority-01'), $setOrdered)->auditEvent();
gate8fAssert($audit->eventId() === $auditAgain->eventId() && $audit->outcomeCode() === 'mapped_from_legacy', 'audit deterministic');
gate8fAssert($audit->toArray()['actor_real_id'] === 'account-gate8f' && $audit->toArray()['actor_effective_id'] === 'operator-gate8f' && $audit->toArray()['merge_allowed'] === false, 'audit authority and merge boundary');
$privateOutputs = json_encode([$priorityDecision->toArray(), $ambiguous->toArray(), $noMatch->toArray(), $audit->toArray(), (new PatientIdentityMutationPlan())->toArray()], JSON_THROW_ON_ERROR);
foreach (['Ana Pérez', '1990-01-20', '+5214491234567', 'ana@example.mx', 'ana perez|1990-01-20|female'] as $forbidden) gate8fAssert(!str_contains($privateOutputs, $forbidden), 'raw pii absent from outputs');
$acceptedMetadataOutputs = json_encode([$priorityDecision->toArray(), $audit->toArray(), gate8fLegacyRequest($input)->toArray()], JSON_THROW_ON_ERROR);
foreach (['AnaPerez', 'ana_perez', 'Ana Pérez', '1990-01-20', '+5214491234567', 'ana@example.mx', 'ana perez|1990-01-20|female'] as $forbidden) gate8fAssert(!str_contains($acceptedMetadataOutputs, $forbidden), 'metadata pii absent from accepted objects');

gate8fThrows(fn() => new PatientIdentityResolutionRequest('operation-actor-required-01', 'correlation-gate8f', 'legacy_bridge', 'legacy_patient_key_hash', null, $legacy, $input, '', 'operator-gate8f', '2026-07-21T11:00:00-06:00'), 'invalid_actor', 'real actor required');
gate8fThrows(fn() => new PatientIdentityResolutionRequest('operation-source-01', 'correlation-gate8f', 'client_role', 'legacy_patient_key_hash', null, $legacy, $input, 'account-gate8f', 'operator-gate8f', '2026-07-21T11:00:00-06:00'), 'invalid_resolution_source', 'source allow list');
gate8fThrows(fn() => new PatientIdentityResolutionRequest('ana@example.mx', 'correlation-gate8f', 'legacy_bridge', 'legacy_patient_key_hash', null, $legacy, $input, 'account-gate8f', 'operator-gate8f', '2026-07-21T11:00:00-06:00'), 'invalid_operation_id', 'pii audit metadata rejected');
gate8fAssert(gate8fLegacyRequest($input, 'operation-public-source-01', 'public_verified')->resolutionSource() === 'public_verified', 'public verified source accepted without identity implication');
gate8fAssert($policy->persistenceDeferred() === 'IDENTITY_PERSISTENCE_MIGRATION_RETENTION_ROLLOUT_DEFERRED_TO_GATE_8G', 'gate8g deferred');
gate8fAssert(\Patients\Identity\patientIdentityResolutionIsClinicalEncounter() === false, 'identity resolution is not care encounter');

$mutationPlan = new PatientIdentityMutationPlan();
$expectedSteps = ['begin_transaction', 'lock_resolution_fingerprint', 'verify_gate8b_actor_authority', 'resolve_idempotency', 'load_canonical_candidate_set', 'verify_candidate_set_integrity', 'evaluate_exact_identity_evidence', 'detect_ambiguity_and_conflicts', 'enforce_automatic_merge_disabled', 'choose_existing_or_create_minimal_plan', 'delegate_to_patients_domain_adapter', 'append_identity_audit_event', 'persist_idempotency_result', 'commit'];
gate8fAssert($mutationPlan->steps() === $expectedSteps && $mutationPlan->failureAction() === 'rollback' && $mutationPlan->transactionRequired(), 'mutation plan exact');
gate8fAssert($mutationPlan->gate8bAuthorityRequired() && !$mutationPlan->automaticMergeAllowed() && !$mutationPlan->directPatientCreationAllowed() && !$mutationPlan->directPatientUpdateAllowed() && !$mutationPlan->directLinkCreationAllowed() && !$mutationPlan->directCareRecordMutationAllowed() && !$mutationPlan->directSqlAllowed() && !$mutationPlan->executesOperations(), 'mutation plan boundaries');
gate8fThrows(fn() => $mutationPlan->requestDirectPatientCreation(), 'unauthorized_identity_mutation', 'direct creation rejected');
gate8fThrows(fn() => $mutationPlan->requestDirectPatientChange(), 'unauthorized_identity_mutation', 'direct update rejected');
gate8fThrows(fn() => $mutationPlan->requestDirectLinkCreation(), 'unauthorized_identity_mutation', 'direct link rejected');
gate8fThrows(fn() => $mutationPlan->requestDirectCareRecordMutation(), 'unauthorized_identity_mutation', 'direct care mutation rejected');
gate8fThrows(fn() => $mutationPlan->requestDirectSql(), 'unauthorized_identity_mutation', 'direct sql rejected');

gate8fAssert(hash_file('sha256', __DIR__ . '/../../agenda/publicflow/PublicAgendaPolicy.php') === 'd0187246d1f63630a77ac73884c366feaacdb00da74bd3730c8c1e349178f99e', 'gate8e policy byte equivalent');
gate8fAssert(hash_file('sha256', __DIR__ . '/../../agenda/publicflow/PublicBookingIntent.php') === 'c78dc1369c5e26315acd4ed161440d79aae292cdccd03cd234f273f158799d7d', 'gate8e booking intent byte equivalent');
gate8fAssert(hash_file('sha256', __DIR__ . '/../../agenda/tests/Gate8EPublicAgendaOtpPrivacyTest.php') === '7819240e25ab82901b39e2102af6d5aa9868b3eb2438b66cdde976f088193f37', 'gate8e test byte equivalent');
gate8fAssert(hash_file('sha256', __DIR__ . '/../../../docs/clinical/CONTRATO_PATIENT_ID_RESOLVER_V1.md') === '5e387480be3d12aedac3786d4e54acf62c65385828c7b47375bf3f27d0f8d445', 'historic resolver contract preserved');
gate8fAssert(hash_file('sha256', __DIR__ . '/../../../docs/clinical/DECISION_IDENTITY_BRIDGE_PATIENT_ID.md') === '5f80fb0d27068e7d56162ba0d2817867740edc7bf849907b4beab0d17e36be29', 'historic bridge decision preserved');

$planText = file_get_contents(__DIR__ . '/../../../docs/PLAN_MAESTRO_MXMED.md');
gate8fAssert(is_string($planText), 'plan readable');
foreach (['PP-304', 'PP-305', 'PP-306', 'PP-307', 'PP-308', 'PP-309'] as $number) gate8fAssert(substr_count($planText, '### ' . $number . ' —') === 1, $number . ' exact once');
$pp309 = [];
preg_match('/### PP-309 .*?(?=### PP-[0-9]+ —|\z)/s', $planText, $pp309);
gate8fAssert(isset($pp309[0]), 'PP-309 present');
$pp309Normalized = rtrim($pp309[0], "\r\n") . "\n";
gate8fAssert(hash('sha256', $pp309Normalized) === '2939e9301d8117a2e4d1cd470758b07407d07c794861be0735f68a45ac94fa70', 'PP-309 normalized hash');

$domainSource = '';
foreach (glob(__DIR__ . '/../identity/*.php') as $file) $domainSource .= file_get_contents($file);
foreach (['PDO', 'SELECT ', 'INSERT ', 'UPDATE ', 'DELETE ', 'FOR UPDATE', 'beginTransaction', 'commit(', 'rollBack', '$_GET', '$_POST', '$_SESSION', 'getenv(', 'header(', 'getallheaders', 'curl_', 'fopen(', 'file_put_contents', 'error_log', 'PatientsRepository', 'CreatePatientController', 'Clinical', 'clinical_documents', 'DevOtpSender', 'AppointmentWriteController', 'createPatient', 'mergePatient', 'random_bytes', 'uniqid(', 'date(', 'password_hash', 'Ana Pérez', '+5214491234567', 'ana@example.mx'] as $forbidden) gate8fAssert(!str_contains($domainSource, $forbidden), 'domain purity: ' . $forbidden);

echo 'METADATA_INJECTION_CASES=' . $metadataInjectionCases . '/' . $metadataInjectionCases . "\n";
echo "Gate8FPatientIdentityDuplicatesTest PASS\n";
