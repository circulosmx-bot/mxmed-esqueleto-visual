<?php
declare(strict_types=1);

namespace Patients\Identity;

final readonly class PatientIdentityCandidateSet
{
    private array $candidates;
    private string $digest;
    public function __construct(array $candidates)
    {
        $byId = [];
        foreach ($candidates as $candidate) {
            if (!$candidate instanceof PatientIdentityCandidate) throw new PatientIdentityDomainException('invalid_candidate_set');
            $id = $candidate->patientId()->value();
            if (isset($byId[$id])) throw new PatientIdentityDomainException('duplicate_candidate_id');
            $byId[$id] = $candidate;
        }
        ksort($byId, SORT_STRING);
        $this->candidates = array_values($byId);
        $this->digest = PatientIdentityPolicy::digest(array_map(static fn(PatientIdentityCandidate $candidate): array => $candidate->toArray(), $this->candidates));
    }
    public function candidates(): array { return $this->candidates; }
    public function count(): int { return count($this->candidates); }
    public function digest(): string { return $this->digest; }
    public function find(CanonicalPatientId $patientId): ?PatientIdentityCandidate
    {
        foreach ($this->candidates as $candidate) if ($candidate->patientId()->value() === $patientId->value()) return $candidate;
        return null;
    }
    public function patientIds(): array { return array_map(static fn(PatientIdentityCandidate $candidate): string => $candidate->patientId()->value(), $this->candidates); }
    public function toArray(): array { return ['candidate_set_digest' => $this->digest, 'candidate_count' => count($this->candidates), 'candidate_ids' => $this->patientIds()]; }
}
