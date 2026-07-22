<?php
declare(strict_types=1);

namespace Patients\Identity;

final readonly class PatientIdentityAuditEvent
{
    private const TYPES = ['patient_identity_already_canonical', 'patient_identity_mapped_from_legacy', 'patient_identity_create_minimal_required', 'patient_identity_review_required', 'patient_identity_ambiguous', 'patient_identity_not_found', 'patient_identity_candidate_set_invalid'];
    private string $eventId;
    private string $eventType;
    private string $operationId;
    private string $correlationId;
    private string $source;
    private string $inputType;
    private string $requestFingerprint;
    private string $candidateSetDigest;
    private ?string $resolvedPatientIdDigest;
    private array $candidatePatientIdDigests;
    private string $outcomeCode;
    private string $matchTier;
    private string $actorRealId;
    private string $actorEffectiveId;
    private int $policyVersion;
    private string $occurredAt;
    private bool $humanReviewRequired;
    private bool $createMinimalRequired;

    public function __construct(string $eventType, PatientIdentityResolutionRequest $request, PatientIdentityCandidateSet $candidateSet, ?CanonicalPatientId $resolvedPatientId, array $candidatePatientIds, string $outcomeCode, string $matchTier, bool $humanReviewRequired, bool $createMinimalRequired)
    {
        if (!in_array($eventType, self::TYPES, true)) throw new PatientIdentityDomainException('unauthorized_identity_mutation');
        PatientIdentityPolicy::tierRank($matchTier);
        $this->eventType = $eventType;
        $this->operationId = PatientIdentityPolicy::operationId($request->operationId());
        $this->correlationId = PatientIdentityPolicy::correlationId($request->correlationId());
        $this->source = $request->resolutionSource();
        $this->inputType = $request->inputType();
        $this->requestFingerprint = $request->fingerprint();
        $this->candidateSetDigest = $candidateSet->digest();
        $this->resolvedPatientIdDigest = $resolvedPatientId === null ? null : PatientIdentityPolicy::digest(['canonical_patient_id' => $resolvedPatientId->value()]);
        $digests = [];
        foreach ($candidatePatientIds as $patientId) {
            $value = $patientId instanceof CanonicalPatientId ? $patientId->value() : (string) $patientId;
            $canonical = new CanonicalPatientId($value);
            $digests[$canonical->value()] = PatientIdentityPolicy::digest(['canonical_patient_id' => $canonical->value()]);
        }
        ksort($digests, SORT_STRING);
        $this->candidatePatientIdDigests = array_values($digests);
        $this->outcomeCode = PatientIdentityPolicy::resultState($outcomeCode);
        $this->matchTier = $matchTier;
        $this->actorRealId = PatientIdentityPolicy::actorReference($request->actorRealId());
        $this->actorEffectiveId = PatientIdentityPolicy::actorReference($request->actorEffectiveId());
        $this->policyVersion = $request->policyVersion();
        $this->occurredAt = $request->occurredAt();
        $this->humanReviewRequired = $humanReviewRequired;
        $this->createMinimalRequired = $createMinimalRequired;
        $this->eventId = PatientIdentityPolicy::digest($this->toArrayWithoutId());
    }
    public function eventId(): string { return $this->eventId; }
    public function eventType(): string { return $this->eventType; }
    public function outcomeCode(): string { return $this->outcomeCode; }
    public function toArrayWithoutId(): array
    {
        return ['event_type' => $this->eventType, 'operation_id' => $this->operationId, 'correlation_id' => $this->correlationId, 'source' => $this->source, 'input_type' => $this->inputType, 'request_fingerprint' => $this->requestFingerprint, 'candidate_set_digest' => $this->candidateSetDigest, 'resolved_patient_id_digest' => $this->resolvedPatientIdDigest, 'candidate_patient_id_digests' => $this->candidatePatientIdDigests, 'outcome_code' => $this->outcomeCode, 'match_tier' => $this->matchTier, 'actor_real_id' => $this->actorRealId, 'actor_effective_id' => $this->actorEffectiveId, 'policy_version' => $this->policyVersion, 'occurred_at' => $this->occurredAt, 'human_review_required' => $this->humanReviewRequired, 'create_minimal_required' => $this->createMinimalRequired, 'merge_allowed' => false];
    }
    public function toArray(): array { return ['event_id' => $this->eventId] + $this->toArrayWithoutId(); }
    public static function types(): array { return self::TYPES; }
}
