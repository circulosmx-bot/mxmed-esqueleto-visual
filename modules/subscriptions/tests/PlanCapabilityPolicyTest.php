<?php
declare(strict_types=1);

require_once __DIR__ . '/../policy/MxmedPlanCapabilityPolicy.php';
require_once __DIR__ . '/../services/ProfileApprovalOwnershipAdapter.php';
require_once __DIR__ . '/../services/MxmedCommercialLifecycleService.php';
require_once __DIR__ . '/../services/MxmedPlanCapabilityResolverService.php';
require_once __DIR__ . '/../services/MxmedPlanCapabilityReadModelBuilder.php';
require_once __DIR__ . '/../services/CreateSubscriptionCheckoutIntentService.php';
require_once __DIR__ . '/../services/BuildSubscriptionPaymentRoutePreviewService.php';
require_once __DIR__ . '/../services/BuildSubscriptionPaymentActivationStateService.php';
require_once __DIR__ . '/../services/ActivateSubscriptionAfterPaymentService.php';

use Subscriptions\Policy\MxmedPlanCapabilityPolicy as Policy;
use Subscriptions\Services\MxmedCommercialLifecycleService;
use Subscriptions\Services\MxmedPlanCapabilityReadModelBuilder;
use Subscriptions\Services\MxmedPlanCapabilityResolverService;
use Subscriptions\Services\ProfileApprovalOwnershipAdapter;

$assertions = 0;
$assertSame = static function ($expected, $actual, string $label) use (&$assertions): void {
    $assertions++;
    if ($expected !== $actual) {
        throw new RuntimeException($label . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
};
$assertTrue = static function ($actual, string $label) use ($assertSame): void {
    $assertSame(true, (bool)$actual, $label);
};

$assertSame('MXMED_PLAN_CAPABILITY_POLICY_V1', Policy::version(), 'policy version');
$assertSame(['free', 'basic', 'standard', 'optimum', 'professional'], Policy::planCodes(), 'five canonical plans');
foreach ([
    'gratis' => 'free', 'gratuito' => 'free', 'básico' => 'basic', 'basico' => 'basic',
    'estándar' => 'standard', 'estandar' => 'standard', 'óptimo' => 'optimum',
    'optimo' => 'optimum', 'profesional' => 'professional', 'pro' => 'professional',
] as $alias => $canonical) {
    $assertSame($canonical, Policy::normalizePlanCode($alias), 'alias ' . $alias);
    $assertSame($canonical, Policy::normalizePlanCode($canonical), 'idempotent ' . $canonical);
}
$assertSame(null, Policy::normalizePlanCode('free_default'), 'free_default is not a plan');
$assertSame('free', Policy::normalizePlanCode('free_default', true), 'technical fallback is explicit');
$assertSame(null, Policy::normalizePlanCode('premium'), 'unknown plan fails closed');

$matrixMinimums = [
    'free' => ['profile_publication', 'profile_management', 'profile_photo', 'public_reviews'],
    'basic' => ['public_contact', 'public_gallery', 'internal_inbox'],
    'standard' => ['agenda', 'patient_contact', 'review_replies'],
    'optimum' => ['clinical_record', 'prescriptions', 'clinical_files'],
    'professional' => ['ai_agenda_agent'],
];
foreach ($matrixMinimums as $plan => $capabilities) {
    $actual = Policy::planCapabilities($plan);
    foreach ($capabilities as $capability) {
        $assertTrue(in_array($capability, $actual, true), $plan . ' includes ' . $capability);
    }
}
$assertTrue(!in_array('agenda', Policy::planCapabilities('basic'), true), 'basic excludes agenda');
$assertTrue(!in_array('clinical_record', Policy::planCapabilities('standard'), true), 'standard excludes clinical');
$assertSame(41, count(Policy::legacyCapabilityCrosswalk()), '41 legacy capabilities preserved');

$quotas = Policy::quotas();
$assertSame(1, $quotas['profile_photo']['plans']['free'], 'one main photo');
$assertSame(0, $quotas['public_gallery']['plans']['free'], 'free gallery zero');
$assertSame(21, $quotas['public_gallery']['plans']['basic'], 'paid gallery 21');
$assertSame(300000, $quotas['public_image_size']['maximumExclusive'], 'public image under 300KB');
$assertSame([3, 10, 20, 30], array_values(array_diff_key($quotas['ai_image_generation']['plans'], ['free' => true])), 'image AI quotas');
$assertSame([15, 30, 60, 100], array_values(array_diff_key($quotas['ai_content_writing']['plans'], ['free' => true])), 'writing AI quotas');
$assertSame('unlimited', $quotas['agenda']['plans']['standard'], 'agenda unlimited Standard+');
$assertSame('unlimited', $quotas['call_center']['value'], 'call center commercial unlimited');
$assertTrue($quotas['call_center']['fairUse'], 'call center fair use');

$addOns = Policy::addOns();
$assertSame(2, count($addOns), 'two call center add-ons');
$assertSame(199900, $addOns['call_center_complementary']['tentativePriceCents'], 'complementary tentative price');
$assertSame(299900, $addOns['call_center_integral']['tentativePriceCents'], 'integral tentative price');
$assertSame(false, $addOns['call_center_integral']['purchasable'], 'add-on not purchasable');
$assertSame(false, $addOns['call_center_integral']['operational'], 'add-on not operational');
$assertSame(false, Policy::addOnEligibility('call_center_integral', 'basic')['eligible'], 'basic ineligible add-on');
$assertSame(true, Policy::addOnEligibility('call_center_integral', 'standard')['eligible'], 'standard eligible contractually');

$adapter = new ProfileApprovalOwnershipAdapter();
$unapproved = $adapter->adapt(['profile_status' => 'hidden', 'is_public_candidate' => 0]);
$assertSame(false, $unapproved['admin_allowed'], 'unapproved cannot admin');
$assertSame(false, $unapproved['purchase_allowed'], 'unapproved cannot purchase');
$unclaimed = $adapter->adapt(['profile_status' => 'active', 'is_public_candidate' => 1]);
$assertSame('approved', $unclaimed['approval_state'], 'legacy public approval evidence');
$assertSame(false, $unclaimed['purchase_allowed'], 'unclaimed cannot purchase');
$claimed = $adapter->adapt(
    ['profile_status' => 'active', 'is_public_candidate' => 1],
    ['authenticated' => true, 'doctor_scope_matches' => true, 'actor_role' => 'doctor']
);
$assertSame(true, $claimed['purchase_allowed'], 'approved claimed can purchase');
$operatorClaim = $adapter->adapt(
    ['approval_status' => 'approved', 'ownership_status' => 'claimed'],
    ['authenticated' => true, 'doctor_scope_matches' => true, 'actor_role' => 'operator']
);
$assertSame(false, $operatorClaim['purchase_allowed'], 'operator read scope cannot purchase as doctor');
$claimedWithoutScope = $adapter->adapt([
    'approval_status' => 'approved',
    'ownership_status' => 'claimed',
]);
$assertSame(false, $claimedWithoutScope['purchase_allowed'], 'claimed without authenticated scope cannot purchase');
$assertSame('actor_scope_not_allowed', $claimedWithoutScope['denial_reason'], 'claimed scope denial');
foreach (['disputed', 'suspended'] as $ownershipState) {
    $gate = $adapter->adapt([
        'approval_status' => 'approved',
        'ownership_status' => $ownershipState,
    ]);
    $assertSame(false, $gate['purchase_allowed'], $ownershipState . ' fails closed');
}

$resolver = new MxmedPlanCapabilityResolverService();
$baseContext = [
    'plan_code' => 'standard',
    'profile_type' => 'doctor',
    'approval_state' => 'approved',
    'ownership_state' => 'claimed',
    'commercial_state' => 'active',
    'actor_scope_allowed' => true,
];
$agenda = $resolver->resolve('agenda', $baseContext);
$assertSame(true, $agenda['allowed'], 'agenda enabled');
$assertSame('plan', $agenda['source'], 'capability source plan');
$assertSame('standard', $agenda['source_id'], 'capability source plan id');
$future = $resolver->resolve('ai_agenda_agent', array_merge($baseContext, ['plan_code' => 'professional']));
$assertSame(false, $future['allowed'], 'future AI disabled');
$assertSame('implementation_not_available', $future['denial_reason'], 'future denial');
$addOnSource = $resolver->resolve('call_center_human_service', array_merge($baseContext, [
    'active_addons' => ['call_center_integral'],
]));
$assertSame('addon', $addOnSource['source'], 'capability source add-on');
$assertSame('implementation_not_available', $addOnSource['denial_reason'], 'add-on still disabled');
$ineligibleAddOn = $resolver->resolve('call_center_human_service', array_merge($baseContext, [
    'plan_code' => 'basic',
    'active_addons' => ['call_center_integral'],
]));
$assertSame('addon_not_eligible', $ineligibleAddOn['denial_reason'], 'ineligible add-on denial');
$notApplicable = $resolver->resolve('agenda', array_merge($baseContext, ['profile_type' => 'hospital']));
$assertSame('not_applicable', $notApplicable['state'], 'not applicable state');
$hidden = $resolver->resolve('agenda', array_merge($baseContext, ['security_hidden' => true]));
$assertSame('hidden_security', $hidden['state'], 'hidden security state');
$suspended = $resolver->resolve('agenda', array_merge($baseContext, ['security_suspended' => true]));
$assertSame('suspended_policy', $suspended['state'], 'suspended policy state');
$quota = $resolver->resolve('public_gallery', array_merge($baseContext, [
    'plan_code' => 'basic',
    'quota_usage' => ['public_gallery' => 21],
]));
$assertSame('quota_exhausted', $quota['denial_reason'], 'quota exhaustion');
$graceLimited = $resolver->resolve('agenda', array_merge($baseContext, [
    'commercial_state' => 'grace',
    'grace_limited_capabilities' => ['agenda'],
]));
$assertSame(false, $graceLimited['allowed'], 'grace capability write blocked');
$assertSame('capability_grace_limited', $graceLimited['denial_reason'], 'grace capability denial');
$assertSame('regularize_payment', $graceLimited['next_action'], 'grace capability next action');
$priority = $resolver->resolve('agenda', array_merge($baseContext, [
    'approval_state' => 'pending_review',
    'ownership_state' => 'disputed',
    'security_hidden' => true,
]));
$assertSame('profile_not_approved', $priority['denial_reason'], 'denial priority');
$assertSame(MxmedPlanCapabilityResolverService::EVALUATION_ORDER, [
    'profile_approval', 'ownership', 'applicability', 'security_policy', 'subscription_state',
    'entitlement', 'implementation_availability', 'dependency', 'quota', 'actor_role_scope', 'operational_state',
], 'resolver order');

$expiry = '2026-01-01 00:00:00+00:00';
foreach ([
    '2026-01-01 00:00:01+00:00' => 'past_due',
    '2026-01-04 00:00:00+00:00' => 'past_due',
    '2026-01-04 00:00:01+00:00' => 'grace',
    '2026-01-16 00:00:00+00:00' => 'grace',
    '2026-01-16 00:00:01+00:00' => 'restricted',
] as $now => $expectedState) {
    $lifecycle = new MxmedCommercialLifecycleService(new DateTimeImmutable($now));
    $resolved = $lifecycle->resolve(['status' => 'active', 'expires_at' => $expiry, 'payment_retry_count' => 99]);
    $assertSame($expectedState, $resolved['state'], 'grace boundary ' . $now);
    $assertSame(false, $resolved['retry_resets_grace'], 'retry never resets grace');
    $assertSame('2026-01-01 00:00:00', $resolved['original_expires_at'], 'original expiration preserved');
}
foreach (['draft', 'pending_payment', 'pending_activation', 'active', 'cancelled', 'superseded', 'failed'] as $state) {
    $resolved = (new MxmedCommercialLifecycleService(new DateTimeImmutable('2025-12-01')))
        ->resolve(['status' => $state]);
    $assertSame($state, $resolved['state'], 'commercial state ' . $state);
}
$extensionService = new MxmedCommercialLifecycleService(new DateTimeImmutable('2026-01-20'));
$assertSame(7, $extensionService->validateExtension('ordinary', 7, 'approved')['approved_days'], 'ordinary extension max');
$assertSame(15, $extensionService->validateExtension('exceptional', 15, 'approved')['approved_days'], 'exceptional extension max');
$extended = $extensionService->resolve([
    'status' => 'active',
    'expires_at' => $expiry,
    'grace_extension_type' => 'ordinary',
    'grace_extension_days' => 7,
    'grace_extension_status' => 'approved',
]);
$assertSame('grace', $extended['state'], 'approved extension expands grace without changing expiry');

$scheduled = (new MxmedCommercialLifecycleService(new DateTimeImmutable('2026-01-01')))->schedulePlanChange(
    'standard',
    'basic',
    new DateTimeImmutable('2026-02-01'),
    ['call_center_integral']
);
$assertSame('downgrade', $scheduled['change_type'], 'scheduled downgrade');
$assertSame('cancel_at_period_end', $scheduled['incompatible_addons'][0]['status'], 'incompatible add-on cancellation');
$assertSame(true, $scheduled['data_preserved'], 'downgrade preserves data');
$assertSame(
    'cancelled',
    (new MxmedCommercialLifecycleService(new DateTimeImmutable('2026-01-01')))
        ->cancelScheduledChange($scheduled)['status'],
    'scheduled change cancellation overrides status'
);

$builder = new MxmedPlanCapabilityReadModelBuilder($resolver, new DateTimeImmutable('2026-01-20'));
$model = $builder->build([
    'status' => 'expired',
    'effective_plan_code' => 'free',
    'contracted_plan_code' => 'optimum',
], [
    'status' => 'expired',
    'contracted_plan_code' => 'optimum',
    'effective_plan_code' => 'free',
], [
    'profile_type' => 'doctor',
    'approval_state' => 'approved',
    'ownership_state' => 'claimed',
]);
$assertSame('archived_read_only', $model['archived_module_summaries'][0]['state'], 'archived read-only');
$assertSame(true, $model['archived_module_summaries'][0]['data_preserved'], 'archived data preserved');
$assertSame(false, $model['archived_module_summaries'][0]['write_allowed'], 'archived write blocked');
$canonicalOverride = $builder->build([
    'status' => 'free_default',
    'effective_plan_code' => 'free',
    'policy_version' => 'legacy_conflict',
], [], [
    'profile_type' => 'doctor',
    'approval_state' => 'approved',
    'ownership_state' => 'claimed',
    'purchase_allowed' => true,
    'admin_allowed' => true,
    'actor_scope_allowed' => true,
]);
$assertSame(Policy::version(), $canonicalOverride['policy_version'], 'canonical read-model overrides legacy collision');

$assertSame(19, count(Policy::denialReasons()), 'canonical denial count');
foreach (Policy::denialReasons() as $reason) {
    $assertTrue(preg_match('/^[a-z0-9_]+$/', $reason) === 1, 'sanitized denial ' . $reason);
}

$canonicalAdapters = [
    [Subscriptions\Services\CreateSubscriptionCheckoutIntentService::class, 'canonicalPlanCode'],
    [Subscriptions\Services\BuildSubscriptionPaymentRoutePreviewService::class, 'normalizePlanCode'],
    [Subscriptions\Services\BuildSubscriptionPaymentActivationStateService::class, 'canonicalPlanCode'],
    [Subscriptions\Services\ActivateSubscriptionAfterPaymentService::class, 'canonicalPlanCode'],
];
foreach ($canonicalAdapters as [$className, $methodName]) {
    $reflection = new ReflectionClass($className);
    $instance = $reflection->newInstanceWithoutConstructor();
    $method = $reflection->getMethod($methodName);
    foreach (['basico' => 'basic', 'estándar' => 'standard', 'óptimo' => 'optimum', 'profesional' => 'professional'] as $alias => $canonical) {
        $assertSame($canonical, $method->invoke($instance, $alias), $className . ' canonical adapter ' . $alias);
    }
}
foreach ([
    Subscriptions\Services\CreateSubscriptionCheckoutIntentService::class,
    Subscriptions\Services\BuildSubscriptionPaymentActivationStateService::class,
    Subscriptions\Services\ActivateSubscriptionAfterPaymentService::class,
] as $className) {
    $reflection = new ReflectionClass($className);
    $instance = $reflection->newInstanceWithoutConstructor();
    $method = $reflection->getMethod('planRank');
    foreach (['basic' => 1, 'standard' => 2, 'optimum' => 3, 'professional' => 4] as $plan => $rank) {
        $assertSame($rank, $method->invoke($instance, $plan), $className . ' protected rank parity ' . $plan);
    }
}

echo json_encode([
    'ok' => true,
    'suite' => 'PlanCapabilityPolicyTest',
    'assertions' => $assertions,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
