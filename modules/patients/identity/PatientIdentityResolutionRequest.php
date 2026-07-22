<?php
declare(strict_types=1);

namespace Patients\Identity;

final readonly class PatientIdentityResolutionRequest
{
    private string $operationId;
    private string $correlationId;
    private string $resolutionSource;
    private string $inputType;
    private ?CanonicalPatientId $canonicalPatientId;
    private ?LegacyPatientReference $legacyReference;
    private ?PatientIdentityEvidence $evidence;
    private string $actorRealId;
    private string $actorEffectiveId;
    private string $occurredAt;
    private int $policyVersion;
    private string $fingerprint;

    public function __construct(string $operationId, string $correlationId, string $resolutionSource, string $inputType, ?CanonicalPatientId $canonicalPatientId, ?LegacyPatientReference $legacyReference, ?PatientIdentityEvidence $evidence, string $actorRealId, string $actorEffectiveId, string $occurredAt, int $policyVersion = PatientIdentityPolicy::VERSION)
    {
        if ($policyVersion !== PatientIdentityPolicy::VERSION) throw new PatientIdentityDomainException('invalid_contract_version');
        if (!PatientIdentityPolicy::isSource($resolutionSource)) throw new PatientIdentityDomainException('invalid_resolution_source');
        if (!PatientIdentityPolicy::isInputType($inputType)) throw new PatientIdentityDomainException('invalid_resolution_request');
        if ($inputType === 'canonical_patient_id' && ($canonicalPatientId === null || $legacyReference !== null || $evidence !== null)) throw new PatientIdentityDomainException('invalid_resolution_request');
        if ($inputType === 'legacy_patient_key_hash' && ($canonicalPatientId !== null || $legacyReference === null || $evidence === null)) throw new PatientIdentityDomainException('invalid_resolution_request');
        $this->operationId = PatientIdentityPolicy::operationId($operationId);
        $this->correlationId = PatientIdentityPolicy::correlationId($correlationId);
        $this->actorRealId = PatientIdentityPolicy::actorReference($actorRealId);
        $this->actorEffectiveId = PatientIdentityPolicy::actorReference($actorEffectiveId);
        $this->occurredAt = PatientIdentityPolicy::timestamp($occurredAt)->format('Y-m-d\TH:i:s.uP');
        $this->resolutionSource = $resolutionSource;
        $this->inputType = $inputType;
        $this->canonicalPatientId = $canonicalPatientId;
        $this->legacyReference = $legacyReference;
        $this->evidence = $evidence;
        $this->policyVersion = $policyVersion;
        $this->fingerprint = PatientIdentityPolicy::digest([
            'contract_id' => PatientIdentityPolicy::CONTRACT_ID,
            'policy_version' => $policyVersion,
            'operation_id' => $this->operationId,
            'resolution_source' => $resolutionSource,
            'input_type' => $inputType,
            'identity_reference' => $canonicalPatientId?->value() ?? $legacyReference?->legacyKeyHash(),
            'identity_evidence' => $evidence?->toArray(),
            'actor_real_id' => $this->actorRealId,
            'actor_effective_id' => $this->actorEffectiveId,
            'occurred_at' => $this->occurredAt,
        ]);
    }
    public function operationId(): string { return $this->operationId; }
    public function correlationId(): string { return $this->correlationId; }
    public function resolutionSource(): string { return $this->resolutionSource; }
    public function inputType(): string { return $this->inputType; }
    public function canonicalPatientId(): ?CanonicalPatientId { return $this->canonicalPatientId; }
    public function legacyReference(): ?LegacyPatientReference { return $this->legacyReference; }
    public function evidence(): ?PatientIdentityEvidence { return $this->evidence; }
    public function actorRealId(): string { return $this->actorRealId; }
    public function actorEffectiveId(): string { return $this->actorEffectiveId; }
    public function occurredAt(): string { return $this->occurredAt; }
    public function policyVersion(): int { return $this->policyVersion; }
    public function fingerprint(): string { return $this->fingerprint; }
    public function toArray(): array { return ['operation_id' => $this->operationId, 'correlation_id' => $this->correlationId, 'resolution_source' => $this->resolutionSource, 'input_type' => $this->inputType, 'canonical_patient_id' => $this->canonicalPatientId?->value(), 'legacy_reference_digest' => $this->legacyReference?->digest(), 'evidence_present' => $this->evidence !== null, 'actor_real_id' => $this->actorRealId, 'actor_effective_id' => $this->actorEffectiveId, 'occurred_at' => $this->occurredAt, 'policy_version' => $this->policyVersion, 'request_fingerprint' => $this->fingerprint]; }
}
