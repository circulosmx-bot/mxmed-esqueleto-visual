<?php
declare(strict_types=1);

namespace Subscriptions\Services;

require_once __DIR__ . '/../policy/MxmedPlanCapabilityPolicy.php';

use Subscriptions\Policy\MxmedPlanCapabilityPolicy;

final class MxmedPlanCapabilityResolverService
{
    public const EVALUATION_ORDER = [
        'profile_approval',
        'ownership',
        'applicability',
        'security_policy',
        'subscription_state',
        'entitlement',
        'implementation_availability',
        'dependency',
        'quota',
        'actor_role_scope',
        'operational_state',
    ];

    public function resolve(string $capabilityCode, array $context): array
    {
        $capability = MxmedPlanCapabilityPolicy::capability($capabilityCode);
        if ($capability === null) {
            return $this->denied($capabilityCode, 'not_applicable', 'profile_type_not_supported', [], $context);
        }

        if (($capability['approvalRequired'] ?? true) && ($context['approval_state'] ?? null) !== 'approved') {
            return $this->denied($capabilityCode, 'suspended_policy', 'profile_not_approved', $capability, $context);
        }

        if ($capability['ownershipRequired'] ?? true) {
            $ownership = strtolower(trim((string)($context['ownership_state'] ?? 'unclaimed')));
            if ($ownership === 'disputed') {
                return $this->denied($capabilityCode, 'suspended_policy', 'ownership_disputed', $capability, $context);
            }
            if (in_array($ownership, ['suspended', 'revoked'], true)) {
                return $this->denied($capabilityCode, 'suspended_policy', 'ownership_suspended', $capability, $context);
            }
            if ($ownership !== 'claimed') {
                return $this->denied($capabilityCode, 'suspended_policy', 'ownership_required', $capability, $context);
            }
        }

        $profileType = strtolower(trim((string)($context['profile_type'] ?? 'doctor')));
        if (!in_array($profileType, $capability['applicableProfileTypes'] ?? [], true)) {
            return $this->denied($capabilityCode, 'not_applicable', 'profile_type_not_supported', $capability, $context);
        }

        if ($this->boolean($context['security_hidden'] ?? false)) {
            return $this->denied($capabilityCode, 'hidden_security', 'capability_suspended', $capability, $context);
        }
        if ($this->boolean($context['security_suspended'] ?? false)) {
            return $this->denied($capabilityCode, 'suspended_policy', 'capability_suspended', $capability, $context);
        }

        $commercialState = strtolower(trim((string)($context['commercial_state'] ?? 'free')));
        if ($commercialState === 'pending_payment' || $commercialState === 'draft') {
            return $this->denied($capabilityCode, 'pending_activation', 'subscription_pending_payment', $capability, $context);
        }
        if ($commercialState === 'pending_activation') {
            return $this->denied($capabilityCode, 'pending_activation', 'capability_pending_activation', $capability, $context);
        }
        if (in_array($commercialState, ['restricted', 'expired', 'cancelled', 'superseded', 'failed'], true)) {
            return $this->denied($capabilityCode, 'read_only', 'capability_read_only', $capability, $context);
        }

        $entitlement = $this->entitlement($capabilityCode, $context);
        if (!$entitlement['included']) {
            $reason = $entitlement['denial_reason']
                ?? (str_starts_with($capabilityCode, 'call_center_') ? 'addon_required' : 'capability_not_in_plan');
            return $this->denied($capabilityCode, 'locked_upsell', $reason, $capability, $context, $entitlement);
        }

        if (($capability['implementationState'] ?? 'documented_disabled') !== 'implemented_core') {
            return $this->denied(
                $capabilityCode,
                'blocked_dependency',
                'implementation_not_available',
                $capability,
                $context,
                $entitlement
            );
        }

        $dependencies = array_values(array_filter((array)($context['missing_dependencies'][$capabilityCode] ?? [])));
        if ($dependencies !== []) {
            return $this->denied(
                $capabilityCode,
                'blocked_dependency',
                'dependency_missing',
                $capability,
                $context,
                $entitlement,
                $dependencies
            );
        }

        $quota = $this->quotaSummary($capability, $context);
        if (($quota['exhausted'] ?? false) === true) {
            return $this->denied(
                $capabilityCode,
                'suspended_policy',
                'quota_exhausted',
                $capability,
                $context,
                $entitlement,
                [],
                $quota
            );
        }

        $allowedRoles = (array)($context['allowed_roles'][$capabilityCode] ?? []);
        $actorRole = strtolower(trim((string)($context['actor_role'] ?? '')));
        if ($allowedRoles !== [] && !in_array($actorRole, $allowedRoles, true)) {
            return $this->denied($capabilityCode, 'suspended_policy', 'actor_role_not_allowed', $capability, $context, $entitlement, [], $quota);
        }
        if (array_key_exists('actor_scope_allowed', $context) && !$this->boolean($context['actor_scope_allowed'])) {
            return $this->denied($capabilityCode, 'suspended_policy', 'actor_scope_not_allowed', $capability, $context, $entitlement, [], $quota);
        }

        if (!($capability['operational'] ?? false)) {
            return $this->denied($capabilityCode, 'blocked_dependency', 'implementation_not_available', $capability, $context, $entitlement, [], $quota);
        }

        if (in_array($commercialState, ['past_due', 'grace'], true)) {
            if (in_array($capabilityCode, (array)($context['grace_limited_capabilities'] ?? []), true)) {
                return $this->denied(
                    $capabilityCode,
                    'grace_limited',
                    'capability_grace_limited',
                    $capability,
                    $context,
                    $entitlement,
                    [],
                    $quota
                );
            }
            return $this->result(
                $capabilityCode,
                true,
                'grace_limited',
                'subscription_in_grace',
                $capability,
                $entitlement,
                $quota,
                [],
                'regularize_payment'
            );
        }

        return $this->result(
            $capabilityCode,
            true,
            'enabled',
            null,
            $capability,
            $entitlement,
            $quota,
            [],
            null
        );
    }

    private function entitlement(string $capabilityCode, array $context): array
    {
        $plan = MxmedPlanCapabilityPolicy::normalizePlanCode($context['plan_code'] ?? null, true) ?? 'free';
        if (in_array($capabilityCode, MxmedPlanCapabilityPolicy::planCapabilities($plan), true)) {
            return ['included' => true, 'source' => 'plan', 'source_id' => $plan];
        }
        foreach ((array)($context['active_addons'] ?? []) as $addOnCode) {
            $addOn = MxmedPlanCapabilityPolicy::addOns()[(string)$addOnCode] ?? null;
            if ($addOn !== null && in_array($capabilityCode, $addOn['capabilities'] ?? [], true)) {
                $eligibility = MxmedPlanCapabilityPolicy::addOnEligibility(
                    (string)$addOnCode,
                    $plan,
                    (string)($context['profile_type'] ?? 'doctor')
                );
                if (!($eligibility['eligible'] ?? false)) {
                    return [
                        'included' => false,
                        'source' => 'addon',
                        'source_id' => (string)$addOnCode,
                        'denial_reason' => (string)($eligibility['reason'] ?? 'addon_not_eligible'),
                    ];
                }
                return ['included' => true, 'source' => 'addon', 'source_id' => (string)$addOnCode];
            }
        }
        foreach (['temporary_grants', 'contractual_overrides'] as $sourceKey) {
            if (in_array($capabilityCode, (array)($context[$sourceKey] ?? []), true)) {
                return [
                    'included' => true,
                    'source' => $sourceKey === 'temporary_grants' ? 'temporary_grant' : 'contractual_override',
                    'source_id' => $capabilityCode,
                ];
            }
        }
        return ['included' => false, 'source' => null, 'source_id' => null, 'denial_reason' => null];
    }

    private function quotaSummary(array $capability, array $context): ?array
    {
        $quotaKey = $capability['quotaPolicy'] ?? null;
        if ($quotaKey === null) {
            return null;
        }
        $plan = MxmedPlanCapabilityPolicy::normalizePlanCode($context['plan_code'] ?? null, true) ?? 'free';
        $quota = MxmedPlanCapabilityPolicy::quotaFor((string)$quotaKey, $plan);
        if ($quota === null) {
            return null;
        }
        $used = max(0, (int)($context['quota_usage'][$quotaKey] ?? 0));
        $limit = $quota['value'] ?? null;
        $exhausted = is_int($limit) && $used >= $limit;
        return $quota + [
            'used' => $used,
            'remaining' => is_int($limit) ? max(0, $limit - $used) : $limit,
            'exhausted' => $exhausted,
        ];
    }

    private function denied(
        string $code,
        string $state,
        string $reason,
        array $capability,
        array $context,
        array $entitlement = [],
        array $dependencies = [],
        ?array $quota = null
    ): array {
        return $this->result(
            $code,
            false,
            $state,
            $reason,
            $capability,
            $entitlement,
            $quota ?? $this->quotaSummary($capability, $context),
            $dependencies,
            $this->nextAction($reason)
        );
    }

    private function result(
        string $code,
        bool $allowed,
        string $state,
        ?string $reason,
        array $capability,
        array $entitlement,
        ?array $quota,
        array $dependencies,
        ?string $nextAction
    ): array {
        return [
            'capability_code' => $code,
            'allowed' => $allowed,
            'state' => $state,
            'denial_reason' => $reason,
            'source' => $entitlement['source'] ?? null,
            'source_id' => $entitlement['source_id'] ?? null,
            'quota_summary' => $quota,
            'dependencies' => $dependencies !== [] ? $dependencies : (array)($capability['dependencies'] ?? []),
            'next_action' => $nextAction,
            'marketable' => (bool)($capability['marketable'] ?? false),
            'operational' => (bool)($capability['operational'] ?? false),
            'implementation_state' => $capability['implementationState'] ?? 'unknown',
        ];
    }

    private function nextAction(string $reason): ?string
    {
        $map = [
            'profile_not_approved' => 'wait_for_profile_approval',
            'ownership_required' => 'claim_profile',
            'ownership_disputed' => 'contact_support',
            'ownership_suspended' => 'contact_support',
            'capability_not_in_plan' => 'compare_plans',
            'addon_required' => 'review_addon_when_available',
            'addon_not_eligible' => 'review_eligible_plan',
            'implementation_not_available' => 'none',
            'subscription_pending_payment' => 'complete_payment',
            'capability_pending_activation' => 'wait_for_activation',
            'dependency_missing' => 'complete_dependency',
            'quota_exhausted' => 'wait_for_quota_reset',
            'actor_role_not_allowed' => 'request_authorized_role',
            'actor_scope_not_allowed' => 'switch_authorized_profile',
            'subscription_in_grace' => 'regularize_payment',
            'capability_grace_limited' => 'regularize_payment',
            'capability_read_only' => 'reactivate_required_plan',
            'capability_suspended' => 'contact_support',
            'profile_type_not_supported' => 'none',
        ];
        return $map[$reason] ?? null;
    }

    private function boolean($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
    }
}
