<?php
declare(strict_types=1);

namespace Platform\Contracts;

final readonly class RetentionPolicyRegistration
{
    public function __construct(private RetentionPolicy $policy, private bool $clinicalData, private bool $commercialStateDependency, private string $anonymizationResolution = DispositionResolution::ANONYMIZATION_UNRESOLVED, private bool $sensitiveData = true, private ?string $policyReference = null)
    {
        if ($this->anonymizationResolution !== DispositionResolution::ANONYMIZATION_UNRESOLVED && $this->anonymizationResolution !== 'resolved') throw new \InvalidArgumentException('unknown_anonymization_resolution');
        if ($this->clinicalData && $this->commercialStateDependency) throw new \InvalidArgumentException('clinical_data_cannot_depend_on_commercial_state');
    }
    public function policy(): RetentionPolicy { return $this->policy; }
    public function clinicalData(): bool { return $this->clinicalData; }
    public function commercialStateDependency(): bool { return $this->commercialStateDependency; }
    public function anonymizationResolution(): string { return $this->anonymizationResolution; }
    public function sensitiveData(): bool { return $this->sensitiveData; }
    public function policyReference(): ?string { return $this->policyReference; }
    public function key(): string { return $this->policy->dataDomain() . ':' . $this->policy->dataClass(); }
}
