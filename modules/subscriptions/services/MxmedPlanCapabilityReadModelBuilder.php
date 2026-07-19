<?php
declare(strict_types=1);

namespace Subscriptions\Services;

require_once __DIR__ . '/../policy/MxmedPlanCapabilityPolicy.php';
require_once __DIR__ . '/ProfileApprovalOwnershipAdapter.php';
require_once __DIR__ . '/MxmedCommercialLifecycleService.php';
require_once __DIR__ . '/MxmedPlanCapabilityResolverService.php';

use DateTimeImmutable;
use Subscriptions\Policy\MxmedPlanCapabilityPolicy;

final class MxmedPlanCapabilityReadModelBuilder
{
    private MxmedPlanCapabilityResolverService $resolver;
    private ?DateTimeImmutable $now;

    public function __construct(
        ?MxmedPlanCapabilityResolverService $resolver = null,
        ?DateTimeImmutable $now = null
    ) {
        $this->resolver = $resolver ?? new MxmedPlanCapabilityResolverService();
        $this->now = $now;
    }

    public function build(array $legacyModel, array $subscription = [], array $profileContext = []): array
    {
        $lifecycle = new MxmedCommercialLifecycleService($this->now);
        $commercial = $lifecycle->resolve($subscription + ['status' => $legacyModel['status'] ?? 'free_default']);
        $contractedPlan = MxmedPlanCapabilityPolicy::normalizePlanCode(
            $subscription['contracted_plan_code'] ?? ($legacyModel['contracted_plan_code'] ?? null),
            true
        );
        $currentPlan = MxmedPlanCapabilityPolicy::normalizePlanCode(
            $legacyModel['effective_plan_code'] ?? ($subscription['effective_plan_code'] ?? null),
            true
        ) ?? 'free';
        if (($commercial['scheduled_change_effective'] ?? false) && ($commercial['scheduled_plan'] ?? null) !== null) {
            $currentPlan = (string)$commercial['scheduled_plan'];
        }

        $approval = $this->normalizedProfileContext($profileContext);
        $activeAddOns = $this->activeAddOns($subscription, $profileContext);
        $resolverContext = [
            'plan_code' => $currentPlan,
            'profile_type' => $approval['profile_type'],
            'approval_state' => $approval['approval_state'],
            'ownership_state' => $approval['ownership_state'],
            'commercial_state' => $commercial['state'],
            'active_addons' => array_column($activeAddOns, 'code'),
            'temporary_grants' => (array)($profileContext['temporary_grants'] ?? []),
            'contractual_overrides' => (array)($profileContext['contractual_overrides'] ?? []),
            'missing_dependencies' => (array)($profileContext['missing_dependencies'] ?? []),
            'quota_usage' => (array)($profileContext['quota_usage'] ?? []),
            'grace_limited_capabilities' => (array)($profileContext['grace_limited_capabilities'] ?? []),
            'actor_role' => $profileContext['actor_role'] ?? null,
            'allowed_roles' => (array)($profileContext['allowed_roles'] ?? []),
            'actor_scope_allowed' => $profileContext['actor_scope_allowed'] ?? $approval['purchase_allowed'],
            'security_hidden' => $profileContext['security_hidden'] ?? false,
            'security_suspended' => $profileContext['security_suspended'] ?? false,
        ];

        $capabilities = [];
        $denials = [];
        $future = [];
        foreach (MxmedPlanCapabilityPolicy::capabilityRegistry() as $code => $definition) {
            $resolved = $this->resolver->resolve($code, $resolverContext);
            $capabilities[$code] = $resolved;
            if ($resolved['denial_reason'] !== null) {
                $denials[$resolved['denial_reason']] = true;
            }
            if (($definition['implementationState'] ?? null) === 'documented_disabled') {
                $future[] = [
                    'code' => $code,
                    'label' => $definition['label'],
                    'state' => $resolved['state'],
                    'operational' => false,
                    'marketable' => false,
                    'purchasable' => false,
                    'denial_reason' => 'implementation_not_available',
                ];
            }
        }

        $planCatalog = $this->mergePublishedPrices(
            MxmedPlanCapabilityPolicy::planCatalog(),
            (array)($profileContext['published_prices'] ?? [])
        );
        $archived = $this->archivedModules($contractedPlan, $currentPlan, (string)$commercial['state']);

        return array_merge($legacyModel, [
            'policy_version' => MxmedPlanCapabilityPolicy::version(),
            'profile_approval_state' => $approval['approval_state'],
            'profile_approval_source' => $approval['approval_source'],
            'ownership_state' => $approval['ownership_state'],
            'ownership_source' => $approval['ownership_source'],
            'admin_allowed' => $approval['admin_allowed'],
            'purchase_allowed' => $approval['purchase_allowed'],
            'current_plan' => $this->planSummary($currentPlan),
            'contracted_plan' => $contractedPlan !== null ? $this->planSummary($contractedPlan) : null,
            'scheduled_plan' => $commercial['scheduled_plan'] !== null
                ? $this->planSummary((string)$commercial['scheduled_plan'])
                : null,
            'scheduled_effective_at' => $commercial['scheduled_effective_at'],
            'scheduled_change_status' => $commercial['scheduled_change_status'],
            'cancel_scheduled_change_allowed' => $commercial['cancel_scheduled_change_allowed'],
            'replace_scheduled_change_allowed' => $commercial['replace_scheduled_change_allowed'],
            'scheduled_addon_impacts' => $this->scheduledAddOnImpacts(
                $activeAddOns,
                $commercial['scheduled_plan'],
                $approval['profile_type']
            ),
            'commercial_state' => $commercial['state'],
            'commercial_lifecycle' => $commercial,
            'grace' => [
                'starts_at' => $commercial['grace_starts_at'],
                'ends_at' => $commercial['grace_ends_at'],
                'restricted_at' => $commercial['restricted_at'],
                'days_past_due' => $commercial['days_past_due'],
                'extension' => $commercial['extension'],
            ],
            'active_addons' => $activeAddOns,
            'addon_eligibility' => $this->addOnEligibility($currentPlan, $approval['profile_type']),
            'capabilities' => $capabilities,
            'quota_summaries' => $this->quotaSummaries($capabilities),
            'denial_reasons' => array_keys($denials),
            'denial_catalog' => array_map(
                static fn(string $code): array => ['code' => $code, 'safe_for_ui' => true],
                MxmedPlanCapabilityPolicy::denialReasons()
            ),
            'archived_module_summaries' => $archived,
            'future_capabilities' => $future,
            'plan_catalog' => $planCatalog,
            'plan_aliases' => MxmedPlanCapabilityPolicy::planAliases(true),
            'policy_evaluation_order' => MxmedPlanCapabilityResolverService::EVALUATION_ORDER,
            'compatibility' => [
                'legacy_fields_present' => true,
                'legacy_fields_deprecated' => [
                    'status',
                    'plan_label',
                    'grace_status',
                    'is_free_fallback',
                    'is_paid_plan',
                    'is_active',
                    'is_expired',
                    'is_in_grace',
                ],
                'legacy_fields_source' => MxmedPlanCapabilityPolicy::version(),
            ],
        ]);
    }

    private function normalizedProfileContext(array $context): array
    {
        if (isset($context['approval_state'], $context['ownership_state'])) {
            $approvalState = strtolower(trim((string)$context['approval_state']));
            $ownershipState = strtolower(trim((string)$context['ownership_state']));
            $policyGateSatisfied = $approvalState === 'approved' && $ownershipState === 'claimed';
            $purchaseAllowed = array_key_exists('purchase_allowed', $context)
                ? $context['purchase_allowed'] === true
                : ($policyGateSatisfied && ($context['actor_scope_allowed'] ?? false) === true);
            $adminAllowed = array_key_exists('admin_allowed', $context)
                ? $context['admin_allowed'] === true
                : $purchaseAllowed;
            return [
                'profile_type' => strtolower(trim((string)($context['profile_type'] ?? 'doctor'))),
                'approval_state' => $approvalState,
                'approval_source' => $context['approval_source'] ?? 'caller_context',
                'ownership_state' => $ownershipState,
                'ownership_source' => $context['ownership_source'] ?? 'caller_context',
                'admin_allowed' => $adminAllowed,
                'purchase_allowed' => $purchaseAllowed,
            ];
        }

        return (new ProfileApprovalOwnershipAdapter())->adapt($context, $context);
    }

    private function activeAddOns(array $subscription, array $context): array
    {
        $source = $context['active_addons'] ?? ($subscription['active_addons_json'] ?? []);
        if (is_string($source)) {
            $decoded = json_decode($source, true);
            $source = is_array($decoded) ? $decoded : [];
        }
        $active = [];
        foreach ((array)$source as $key => $item) {
            $code = is_array($item) ? trim((string)($item['code'] ?? '')) : trim((string)$item);
            if ($code === '' && is_string($key)) {
                $code = $key;
            }
            $definition = MxmedPlanCapabilityPolicy::addOns()[$code] ?? null;
            if ($definition === null) {
                continue;
            }
            $status = is_array($item) ? (string)($item['status'] ?? 'active') : 'active';
            $active[] = [
                'code' => $code,
                'status' => $status,
                'operational' => false,
                'purchasable' => false,
                'implementation_state' => $definition['implementationState'],
            ];
        }
        return $active;
    }

    private function addOnEligibility(string $planCode, string $profileType): array
    {
        $result = [];
        foreach (MxmedPlanCapabilityPolicy::addOns() as $code => $definition) {
            $result[$code] = MxmedPlanCapabilityPolicy::addOnEligibility($code, $planCode, $profileType) + [
                'code' => $code,
                'label' => $definition['label'],
                'purchasable' => false,
                'operational' => false,
                'price_status' => $definition['priceStatus'],
            ];
        }
        return $result;
    }

    private function scheduledAddOnImpacts(array $activeAddOns, ?string $scheduledPlan, string $profileType): array
    {
        if ($scheduledPlan === null) {
            return [];
        }
        $impacts = [];
        foreach ($activeAddOns as $activeAddOn) {
            $code = (string)($activeAddOn['code'] ?? '');
            $definition = MxmedPlanCapabilityPolicy::addOns()[$code] ?? null;
            if ($definition === null) {
                continue;
            }
            $eligibility = MxmedPlanCapabilityPolicy::addOnEligibility($code, $scheduledPlan, $profileType);
            if (($eligibility['eligible'] ?? false) === true) {
                continue;
            }
            $impacts[] = [
                'code' => $code,
                'label' => $definition['label'],
                'status' => 'cancel_at_period_end',
                'auto_renew' => false,
                'data_preserved' => true,
                'reason' => $eligibility['reason'] ?? 'addon_not_eligible',
            ];
        }
        return $impacts;
    }

    private function quotaSummaries(array $capabilities): array
    {
        $summaries = [];
        foreach ($capabilities as $resolved) {
            $quota = $resolved['quota_summary'] ?? null;
            if (is_array($quota) && isset($quota['key'])) {
                $summaries[(string)$quota['key']] = $quota;
            }
        }
        return $summaries;
    }

    private function archivedModules(?string $contractedPlan, string $currentPlan, string $commercialState): array
    {
        if ($contractedPlan === null) {
            return [];
        }
        $contractedRank = MxmedPlanCapabilityPolicy::planRank($contractedPlan) ?? 0;
        $currentRank = MxmedPlanCapabilityPolicy::planRank($currentPlan) ?? 0;
        if ($contractedRank <= $currentRank && !in_array($commercialState, ['restricted', 'expired'], true)) {
            return [];
        }

        $modules = [
            'gallery' => ['capability' => 'public_gallery', 'required_plan' => 'basic', 'files_may_be_subject_to_future_deletion' => true],
            'agenda' => ['capability' => 'agenda', 'required_plan' => 'standard', 'files_may_be_subject_to_future_deletion' => false],
            'clinical' => ['capability' => 'clinical_record', 'required_plan' => 'optimum', 'files_may_be_subject_to_future_deletion' => false],
        ];
        $archived = [];
        foreach ($modules as $module => $definition) {
            if (!MxmedPlanCapabilityPolicy::planMeetsMinimum($contractedPlan, $definition['required_plan'])) {
                continue;
            }
            if (MxmedPlanCapabilityPolicy::planMeetsMinimum($currentPlan, $definition['required_plan']) && $commercialState !== 'restricted') {
                continue;
            }
            $archived[] = [
                'module' => $module,
                'state' => 'archived_read_only',
                'data_preserved' => true,
                'read_allowed' => true,
                'write_allowed' => false,
                'required_plan_to_reactivate' => $definition['required_plan'],
                'retention' => $module === 'clinical' ? 'deferred_legal_retention_policy' : 'deferred',
                'files_may_be_subject_to_future_deletion' => $definition['files_may_be_subject_to_future_deletion'],
            ];
        }
        return $archived;
    }

    private function planSummary(string $planCode): array
    {
        foreach (MxmedPlanCapabilityPolicy::planCatalog() as $plan) {
            if (($plan['code'] ?? null) === $planCode) {
                return [
                    'code' => $planCode,
                    'label' => $plan['label'],
                    'rank' => $plan['rank'],
                ];
            }
        }
        return ['code' => $planCode, 'label' => $planCode, 'rank' => null];
    }

    private function mergePublishedPrices(array $catalog, array $publishedPrices): array
    {
        $byPlan = [];
        foreach ($publishedPrices as $price) {
            if (!is_array($price)) {
                continue;
            }
            $plan = MxmedPlanCapabilityPolicy::normalizePlanCode($price['plan_code'] ?? null);
            if ($plan === null || (int)($price['amount_cents'] ?? 0) < 0) {
                continue;
            }
            $billingPeriod = strtolower(trim((string)($price['billing_period'] ?? 'annual')));
            if ($billingPeriod === '' || isset($byPlan[$plan][$billingPeriod])) {
                continue;
            }
            $byPlan[$plan][$billingPeriod] = [
                'billing_period' => $billingPeriod,
                'amount_cents' => (int)$price['amount_cents'],
                'currency' => strtoupper((string)($price['currency'] ?? 'MXN')),
                'price_version' => (string)($price['price_version'] ?? ''),
                'source' => 'subscription_plan_prices_backend',
            ];
        }
        foreach ($catalog as &$plan) {
            $plan['prices'] = array_values($byPlan[$plan['code']] ?? []);
        }
        unset($plan);
        return $catalog;
    }
}
