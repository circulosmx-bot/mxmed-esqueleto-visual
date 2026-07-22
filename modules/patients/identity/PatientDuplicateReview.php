<?php
declare(strict_types=1);

namespace Patients\Identity;

final readonly class PatientDuplicateReview
{
    private const REASONS = ['weak_identity_evidence', 'multiple_strong_candidates', 'identity_evidence_conflict', 'candidate_not_eligible', 'invalid_candidate_set'];
    private string $reviewId;
    private string $reasonCode;
    private array $candidatePatientIds;
    private array $candidateIdDigests;
    private string $highestMatchTier;
    private string $requestFingerprint;

    public function __construct(string $reasonCode, array $candidatePatientIds, string $highestMatchTier, string $requestFingerprint)
    {
        if (!in_array($reasonCode, self::REASONS, true)) throw new PatientIdentityDomainException('human_review_required');
        PatientIdentityPolicy::tierRank($highestMatchTier);
        PatientIdentityPolicy::reference($requestFingerprint, 'invalid_resolution_request');
        $ids = [];
        foreach ($candidatePatientIds as $patientId) {
            $value = $patientId instanceof CanonicalPatientId ? $patientId->value() : (string) $patientId;
            $canonical = new CanonicalPatientId($value);
            $ids[$canonical->value()] = $canonical->value();
        }
        ksort($ids, SORT_STRING);
        $this->reasonCode = $reasonCode;
        $this->candidatePatientIds = array_values($ids);
        $this->candidateIdDigests = array_map(static fn(string $id): string => PatientIdentityPolicy::digest(['canonical_patient_id' => $id]), $this->candidatePatientIds);
        $this->highestMatchTier = $highestMatchTier;
        $this->requestFingerprint = $requestFingerprint;
        $this->reviewId = PatientIdentityPolicy::digest(['reason_code' => $reasonCode, 'candidate_patient_ids' => $this->candidatePatientIds, 'highest_match_tier' => $highestMatchTier, 'request_fingerprint' => $requestFingerprint]);
    }
    public function reviewId(): string { return $this->reviewId; }
    public function reasonCode(): string { return $this->reasonCode; }
    public function candidatePatientIds(): array { return $this->candidatePatientIds; }
    public function candidateIdDigests(): array { return $this->candidateIdDigests; }
    public function highestMatchTier(): string { return $this->highestMatchTier; }
    public function requiresHumanReview(): bool { return true; }
    public function toArray(): array { return ['review_id' => $this->reviewId, 'reason_code' => $this->reasonCode, 'candidate_patient_ids' => $this->candidatePatientIds, 'candidate_id_digests' => $this->candidateIdDigests, 'highest_match_tier' => $this->highestMatchTier, 'request_fingerprint' => $this->requestFingerprint, 'requires_human_review' => true]; }
}
