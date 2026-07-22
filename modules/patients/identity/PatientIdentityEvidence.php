<?php
declare(strict_types=1);

namespace Patients\Identity;

final readonly class PatientIdentityEvidence
{
    private string $nameReference;
    private ?string $birthdateReference;
    private ?string $sex;
    private ?string $phoneReference;
    private ?string $emailReference;

    public function __construct(string $nameReference, ?string $birthdateReference = null, ?string $sex = null, ?string $phoneReference = null, ?string $emailReference = null)
    {
        $this->nameReference = PatientIdentityPolicy::reference($nameReference, 'invalid_identity_evidence');
        $this->birthdateReference = $birthdateReference === null ? null : PatientIdentityPolicy::reference($birthdateReference, 'invalid_identity_evidence');
        if ($sex !== null && !in_array($sex, ['female', 'male', 'other', 'undisclosed'], true)) throw new PatientIdentityDomainException('invalid_identity_evidence');
        $this->sex = $sex;
        $this->phoneReference = $phoneReference === null ? null : PatientIdentityPolicy::reference($phoneReference, 'invalid_identity_evidence');
        $this->emailReference = $emailReference === null ? null : PatientIdentityPolicy::reference($emailReference, 'invalid_identity_evidence');
    }
    public function nameReference(): string { return $this->nameReference; }
    public function birthdateReference(): ?string { return $this->birthdateReference; }
    public function sex(): ?string { return $this->sex; }
    public function phoneReference(): ?string { return $this->phoneReference; }
    public function emailReference(): ?string { return $this->emailReference; }
    public function hasAdditionalReference(): bool { return $this->birthdateReference !== null || $this->sex !== null || $this->phoneReference !== null || $this->emailReference !== null; }
    public function toArray(): array
    {
        return ['name_reference' => $this->nameReference, 'birthdate_reference' => $this->birthdateReference, 'sex' => $this->sex, 'phone_reference' => $this->phoneReference, 'email_reference' => $this->emailReference];
    }
}
