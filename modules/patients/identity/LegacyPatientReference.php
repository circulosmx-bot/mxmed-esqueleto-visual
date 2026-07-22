<?php
declare(strict_types=1);

namespace Patients\Identity;

final readonly class LegacyPatientReference
{
    private string $legacyKeyHash;
    public function __construct(string $legacyKeyHash)
    {
        $this->legacyKeyHash = PatientIdentityPolicy::reference($legacyKeyHash, 'invalid_legacy_patient_reference');
    }
    public static function fromRaw(string $unused): self { throw new PatientIdentityDomainException('raw_legacy_patient_key_forbidden'); }
    public function legacyKeyHash(): string { return $this->legacyKeyHash; }
    public function digest(): string { return PatientIdentityPolicy::digest(['legacy_key_hash' => $this->legacyKeyHash]); }
    public function toArray(): array { return ['legacy_key_hash_digest' => $this->digest()]; }
}
