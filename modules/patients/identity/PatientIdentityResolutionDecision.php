<?php
declare(strict_types=1);

namespace Patients\Identity;

final readonly class PatientIdentityResolutionDecision
{
    private string $status;
    private string $reasonCode;
    private ?CanonicalPatientId $resolvedPatientId;
    private string $eventualResolutionMode;
    private string $matchTier;
    private ?PatientDuplicateReview $duplicateReview;
    private string $candidateSetDigest;
    private string $requestFingerprint;
    private string $decisionDigest;
    private bool $createMinimalRequired;
    private PatientIdentityAuditEvent $auditEvent;

    private function __construct(string $status, string $reasonCode, ?CanonicalPatientId $resolvedPatientId, string $matchTier, ?PatientDuplicateReview $duplicateReview, PatientIdentityCandidateSet $candidateSet, PatientIdentityResolutionRequest $request)
    {
        $status = PatientIdentityPolicy::resultState($status);
        $reasonCode = PatientIdentityPolicy::decisionReason($reasonCode);
        PatientIdentityPolicy::assertStatusReasonCoherence($status, $reasonCode);
        $modes = ['already_canonical' => 'already_canonical', 'mapped_from_legacy' => 'mapped_from_legacy', 'create_minimal_required' => 'created_minimal_patient', 'review_required' => 'unresolved', 'ambiguous' => 'unresolved', 'not_found' => 'unresolved', 'invalid_candidate_set' => 'unresolved'];
        if (!isset($modes[$status])) throw new PatientIdentityDomainException('invalid_resolution_request');
        if (in_array($status, ['already_canonical', 'mapped_from_legacy'], true) !== ($resolvedPatientId !== null)) throw new PatientIdentityDomainException('invalid_resolution_request');
        if (in_array($status, ['review_required', 'ambiguous', 'invalid_candidate_set'], true) !== ($duplicateReview !== null)) throw new PatientIdentityDomainException('invalid_resolution_request');
        PatientIdentityPolicy::tierRank($matchTier);
        $this->status = $status;
        $this->reasonCode = $reasonCode;
        $this->resolvedPatientId = $resolvedPatientId;
        $this->eventualResolutionMode = $modes[$status];
        $this->matchTier = $matchTier;
        $this->duplicateReview = $duplicateReview;
        $this->candidateSetDigest = $candidateSet->digest();
        $this->requestFingerprint = $request->fingerprint();
        $this->createMinimalRequired = $status === 'create_minimal_required';
        $this->decisionDigest = PatientIdentityPolicy::digest(['status' => $status, 'reason_code' => $reasonCode, 'resolved_patient_id' => $resolvedPatientId?->value(), 'eventual_resolution_mode' => $this->eventualResolutionMode, 'match_tier' => $matchTier, 'duplicate_review_id' => $duplicateReview?->reviewId(), 'candidate_set_digest' => $this->candidateSetDigest, 'request_fingerprint' => $this->requestFingerprint, 'mutation_allowed' => false, 'create_minimal_required' => $this->createMinimalRequired, 'merge_allowed' => false]);
        $eventType = ['already_canonical' => 'patient_identity_already_canonical', 'mapped_from_legacy' => 'patient_identity_mapped_from_legacy', 'create_minimal_required' => 'patient_identity_create_minimal_required', 'review_required' => 'patient_identity_review_required', 'ambiguous' => 'patient_identity_ambiguous', 'not_found' => 'patient_identity_not_found', 'invalid_candidate_set' => 'patient_identity_candidate_set_invalid'][$status];
        $auditCandidates = $duplicateReview?->candidatePatientIds() ?? ($resolvedPatientId === null ? [] : [$resolvedPatientId->value()]);
        $this->auditEvent = new PatientIdentityAuditEvent($eventType, $request, $candidateSet, $resolvedPatientId, $auditCandidates, $status, $matchTier, $duplicateReview !== null, $this->createMinimalRequired);
    }
    public static function create(string $status, string $reasonCode, ?CanonicalPatientId $resolvedPatientId, string $matchTier, ?PatientDuplicateReview $duplicateReview, PatientIdentityCandidateSet $candidateSet, PatientIdentityResolutionRequest $request): self
    {
        return new self($status, $reasonCode, $resolvedPatientId, $matchTier, $duplicateReview, $candidateSet, $request);
    }
    public function status(): string { return $this->status; }
    public function reasonCode(): string { return $this->reasonCode; }
    public function resolvedPatientId(): ?CanonicalPatientId { return $this->resolvedPatientId; }
    public function eventualResolutionMode(): string { return $this->eventualResolutionMode; }
    public function matchTier(): string { return $this->matchTier; }
    public function duplicateReview(): ?PatientDuplicateReview { return $this->duplicateReview; }
    public function candidateSetDigest(): string { return $this->candidateSetDigest; }
    public function requestFingerprint(): string { return $this->requestFingerprint; }
    public function decisionDigest(): string { return $this->decisionDigest; }
    public function mutationAllowed(): bool { return false; }
    public function createMinimalRequired(): bool { return $this->createMinimalRequired; }
    public function mergeAllowed(): bool { return false; }
    public function auditEvent(): PatientIdentityAuditEvent { return $this->auditEvent; }
    public function toArray(): array
    {
        return ['status' => $this->status, 'reason_code' => $this->reasonCode, 'resolved_canonical_patient_id' => $this->resolvedPatientId?->value(), 'eventual_resolution_mode' => $this->eventualResolutionMode, 'match_tier' => $this->matchTier, 'duplicate_review' => $this->duplicateReview?->toArray(), 'candidate_set_digest' => $this->candidateSetDigest, 'request_fingerprint' => $this->requestFingerprint, 'decision_digest' => $this->decisionDigest, 'mutation_allowed' => false, 'create_minimal_required' => $this->createMinimalRequired, 'merge_allowed' => false, 'audit_event' => $this->auditEvent->toArray()];
    }
}
