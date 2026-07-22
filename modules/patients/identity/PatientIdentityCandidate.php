<?php
declare(strict_types=1);

namespace Patients\Identity;

final readonly class PatientIdentityCandidate
{
    public function __construct(private CanonicalPatientId $patientId, private PatientIdentityEvidence $evidence, private int $candidateVersion, private bool $identityEligible)
    {
        if ($candidateVersion < 1) throw new PatientIdentityDomainException('invalid_identity_candidate');
    }
    public function patientId(): CanonicalPatientId { return $this->patientId; }
    public function evidence(): PatientIdentityEvidence { return $this->evidence; }
    public function candidateVersion(): int { return $this->candidateVersion; }
    public function identityEligible(): bool { return $this->identityEligible; }
    public function toArray(): array { return ['canonical_patient_id' => $this->patientId->value(), 'evidence' => $this->evidence->toArray(), 'candidate_version' => $this->candidateVersion, 'identity_eligible' => $this->identityEligible]; }
}
