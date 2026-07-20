<?php
declare(strict_types=1);

namespace Subscriptions\Services;

use Subscriptions\Contracts\ExistingCapabilityDecision;

require_once __DIR__ . '/../contracts/ExistingCapabilityDecision.php';

final class ExistingCapabilityAuthorityService
{
    private const SOURCE = 'subscriptions.existing_capability_authority_v2';

    private const PLAN_RANKS = [
        'free' => 0,
        'basic' => 1,
        'standard' => 2,
        'optimum' => 3,
        'professional' => 4,
    ];

    private const CAPABILITY_CATALOG = [
        'profile_directory_basic' => [
            'minimum_plan' => 'free',
            'operational_state' => 'operational',
        ],
        'public_contact' => [
            'minimum_plan' => 'basic',
            'operational_state' => 'operational',
        ],
        'gallery' => [
            'minimum_plan' => 'basic',
            'operational_state' => 'operational',
        ],
        'agenda_appointments' => [
            'minimum_plan' => 'standard',
            'operational_state' => 'operational',
        ],
        'patients' => [
            'minimum_plan' => 'optimum',
            'operational_state' => 'operational',
        ],
        'clinical_record' => [
            'minimum_plan' => 'optimum',
            'operational_state' => 'operational',
        ],
        'prescriptions' => [
            'minimum_plan' => 'optimum',
            'operational_state' => 'operational',
        ],
    ];

    /**
     * Resolve one existing capability from subscription context.
     *
     * This method is deliberately independent from prices, copy, UI and DB
     * writes. The reason code remains an internal decision; callers exposing a
     * read-model should use ExistingCapabilityDecision::publicArray().
     */
    public function resolve(string $capabilityId, array $context): ExistingCapabilityDecision
    {
        $capabilityId = trim($capabilityId);
        $definition = self::CAPABILITY_CATALOG[$capabilityId] ?? null;
        if (!is_array($definition)) {
            return $this->decision($capabilityId, false, 'unknown_capability', $context, 'operational');
        }

        $planCode = $this->canonicalPlanCode(
            $context['plan_code']
                ?? $context['effective_plan_code']
                ?? $context['contracted_plan_code']
                ?? null
        );
        if ($planCode === null || !isset(self::PLAN_RANKS[$planCode])) {
            return $this->decision($capabilityId, false, 'context_missing', $context, (string)$definition['operational_state']);
        }

        $operationalState = (string)$definition['operational_state'];
        $status = $this->normalizeStatus($context['subscription_status'] ?? ($context['status'] ?? null));
        $freeDefault = $planCode === 'free' && (
            $status === 'free_default'
            || ($context['is_free_fallback'] ?? false) === true
        );
        $active = ($context['is_active'] ?? null) === true;
        if (!$freeDefault && !$active && !in_array($status, ['active', 'expiring_soon', 'grace_period'], true)) {
            return $this->decision($capabilityId, false, 'subscription_inactive', $context, $operationalState, $planCode);
        }

        $minimumPlan = (string)$definition['minimum_plan'];
        if (self::PLAN_RANKS[$planCode] < self::PLAN_RANKS[$minimumPlan]) {
            return $this->decision($capabilityId, false, 'plan_not_entitled', $context, $operationalState, $planCode);
        }

        if ($operationalState !== 'operational') {
            return $this->decision($capabilityId, false, 'capability_not_operational', $context, $operationalState, $planCode);
        }

        return $this->decision($capabilityId, true, 'allowed', $context, $operationalState, $planCode);
    }

    /**
     * @param list<string> $capabilityIds
     * @return array<string, ExistingCapabilityDecision>
     */
    public function resolveMany(array $capabilityIds, array $context): array
    {
        $decisions = [];
        foreach ($capabilityIds as $capabilityId) {
            $normalizedId = trim((string)$capabilityId);
            if ($normalizedId === '') {
                continue;
            }
            $decisions[$normalizedId] = $this->resolve($normalizedId, $context);
        }
        return $decisions;
    }

    /**
     * @return array<string, array{minimum_plan:string, operational_state:string}>
     */
    public function catalog(): array
    {
        return self::CAPABILITY_CATALOG;
    }

    private function decision(
        string $capabilityId,
        bool $available,
        string $reasonCode,
        array $context,
        string $operationalState,
        ?string $planCode = null
    ): ExistingCapabilityDecision {
        return new ExistingCapabilityDecision(
            $capabilityId,
            $available,
            $reasonCode,
            self::SOURCE,
            $planCode ?? $this->canonicalPlanCode($context['plan_code'] ?? null),
            $operationalState
        );
    }

    private function canonicalPlanCode($value): ?string
    {
        $code = strtolower(trim((string)($value ?? '')));
        if ($code === '') {
            return null;
        }

        $aliases = [
            'gratuito' => 'free',
            'basico' => 'basic',
            'básico' => 'basic',
            'estandar' => 'standard',
            'estándar' => 'standard',
            'optimo' => 'optimum',
            'óptimo' => 'optimum',
            'profesional' => 'professional',
        ];

        return $aliases[$code] ?? $code;
    }

    private function normalizeStatus($value): string
    {
        return strtolower(trim((string)($value ?? '')));
    }
}
