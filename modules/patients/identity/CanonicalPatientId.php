<?php
declare(strict_types=1);

namespace Patients\Identity;

final readonly class CanonicalPatientId
{
    public function __construct(private string $value)
    {
        if (strlen($value) > 64 || preg_match('/\Ap_[A-Za-z0-9][A-Za-z0-9_.:-]{0,61}\z/D', $value) !== 1) {
            throw new PatientIdentityDomainException('invalid_canonical_patient_id');
        }
    }
    public function value(): string { return $this->value; }
    public function toArray(): array { return ['canonical_patient_id' => $this->value]; }
    public function __toString(): string { return $this->value; }
}
