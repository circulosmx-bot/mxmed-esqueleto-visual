<?php
declare(strict_types=1);

namespace Platform\Services;

use Platform\Contracts\RetentionPolicyRegistration;

final class RetentionPolicyRegistry
{
    /** @var array<string,RetentionPolicyRegistration> */
    private array $policies = [];

    /** @param list<RetentionPolicyRegistration> $policies */
    public function __construct(array $policies = [])
    {
        foreach ($policies as $policy) $this->register($policy);
    }

    public function register(RetentionPolicyRegistration $registration): void
    {
        if (isset($this->policies[$registration->key()])) throw new \InvalidArgumentException('duplicate_retention_policy');
        $this->policies[$registration->key()] = $registration;
    }

    public function resolve(string $domain, string $dataClass): ?RetentionPolicyRegistration
    {
        return $this->policies[trim($domain) . ':' . trim($dataClass)] ?? null;
    }

    /** @return list<array<string,mixed>> */
    public function snapshot(): array
    {
        $snapshot = [];
        foreach ($this->policies as $registration) {
            $policy = $registration->policy();
            $snapshot[] = ['data_domain' => $policy->dataDomain(), 'data_class' => $policy->dataClass(), 'purpose' => $policy->purpose(), 'retention_trigger' => $policy->retentionTrigger(), 'retention_period_seconds' => $policy->retentionPeriodSeconds(), 'retention_unresolved' => $policy->retentionUnresolved(), 'current_state' => $policy->currentState(), 'archive_state' => $policy->archiveState(), 'disposition_method' => $policy->dispositionMethod(), 'legal_hold' => $policy->legalHold(), 'approval_level' => $policy->approvalLevel(), 'policy_owner' => $policy->policyOwner(), 'implementation_status' => $policy->implementationStatus(), 'clinical_data' => $registration->clinicalData(), 'commercial_state_dependency' => $registration->commercialStateDependency(), 'anonymization_resolution' => $registration->anonymizationResolution(), 'sensitive_data' => $registration->sensitiveData()];
        }
        usort($snapshot, static fn (array $left, array $right): int => strcmp($left['data_domain'] . ':' . $left['data_class'], $right['data_domain'] . ':' . $right['data_class']));
        return $snapshot;
    }
}
