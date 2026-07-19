<?php
declare(strict_types=1);

require_once __DIR__ . '/../policy/MxmedPlanCapabilityPolicy.php';
require_once __DIR__ . '/../services/MxmedPlanCapabilityReadModelBuilder.php';
require_once __DIR__ . '/../../profiles/services/PublicProfilePlanCapabilities.php';

use Profiles\Services\PublicProfilePlanCapabilities;
use Subscriptions\Policy\MxmedPlanCapabilityPolicy as Policy;
use Subscriptions\Services\MxmedPlanCapabilityReadModelBuilder;

$root = dirname(__DIR__, 3);
$assertions = 0;
$check = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$legacy = [
    'entity_type' => 'doctor',
    'entity_id' => 'qa-doctor',
    'contracted_plan_code' => 'professional',
    'effective_plan_code' => 'professional',
    'plan_label' => 'Profesional',
    'billing_period' => 'annual',
    'duration_days' => 365,
    'status' => 'active',
    'contract_accepted_at' => null,
    'starts_at' => '2026-01-01 00:00:00',
    'expires_at' => '2027-01-01 00:00:00',
    'grace_starts_at' => null,
    'grace_ends_at' => null,
    'grace_status' => null,
    'is_free_fallback' => false,
    'is_paid_plan' => true,
    'is_active' => true,
    'is_expired' => false,
    'is_in_grace' => false,
    'days_until_expiration' => 167,
    'source' => 'contract_test',
    'version' => 'current-subscription-readmodel-v1',
];
$subscription = $legacy + [
    'scheduled_plan_code' => 'basic',
    'scheduled_effective_at' => '2027-01-01 00:00:00',
    'scheduled_change_status' => 'scheduled',
];
$context = [
    'profile_type' => 'doctor',
    'approval_state' => 'approved',
    'ownership_state' => 'claimed',
    'purchase_allowed' => true,
    'admin_allowed' => true,
    'actor_scope_allowed' => true,
    'active_addons' => ['call_center_integral'],
    'published_prices' => [
        ['plan_code' => 'basic', 'billing_period' => 'annual', 'amount_cents' => 699000, 'currency' => 'MXN', 'price_version' => 'qa-v1'],
        ['plan_code' => 'basic', 'billing_period' => 'annual', 'amount_cents' => 599000, 'currency' => 'MXN', 'price_version' => 'qa-old'],
    ],
];
$model = (new MxmedPlanCapabilityReadModelBuilder(null, new DateTimeImmutable('2026-07-18 00:00:00')))
    ->build($legacy, $subscription, $context);

foreach ([
    'policy_version', 'profile_approval_state', 'ownership_state', 'current_plan', 'contracted_plan',
    'scheduled_plan', 'scheduled_effective_at', 'commercial_state', 'grace', 'active_addons',
    'addon_eligibility', 'capabilities', 'quota_summaries', 'denial_reasons',
    'archived_module_summaries', 'future_capabilities', 'plan_catalog', 'plan_aliases',
] as $field) {
    $check(array_key_exists($field, $model), 'missing read-model field ' . $field);
}
$check($model['policy_version'] === Policy::version(), 'policy version mismatch');
$check(count($model['plan_catalog']) === 5, 'plan catalog must contain five plans');
$check(count($model['capabilities']) === count(Policy::capabilityRegistry()), 'capability registry/read-model mismatch');
$check($model['purchase_allowed'] === true, 'approved claimed purchase gate');
$check($model['scheduled_plan']['code'] === 'basic', 'scheduled plan missing');
$check($model['cancel_scheduled_change_allowed'] === true, 'scheduled change cancellation unavailable');
$check(count($model['scheduled_addon_impacts']) === 1, 'scheduled add-on impact missing');
$check($model['scheduled_addon_impacts'][0]['status'] === 'cancel_at_period_end', 'incompatible add-on was not scheduled to end');
$check(count($model['future_capabilities']) >= 9, 'future disabled inventory incomplete');
foreach ($model['future_capabilities'] as $future) {
    $check($future['operational'] === false && $future['marketable'] === false && $future['purchasable'] === false, 'future capability opened');
}
$basic = array_values(array_filter($model['plan_catalog'], static fn(array $plan): bool => $plan['code'] === 'basic'))[0];
$check(count($basic['prices']) === 1, 'backend published price not merged');
$check($basic['prices'][0]['amount_cents'] === 699000, 'latest backend price was not preserved');
$check($basic['prices'][0]['source'] === 'subscription_plan_prices_backend', 'frontend price authority leaked');

$encoded = json_encode($model, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
foreach (['client_secret', 'payment_method_details', 'stripe_signature', 'identity_document', 'clinical_content', 'token'] as $forbidden) {
    $check(stripos((string)$encoded, $forbidden) === false, 'sensitive field in read-model: ' . $forbidden);
}

$publicContract = PublicProfilePlanCapabilities::build('professional', [
    'has_public_profile' => true,
    'is_claimed' => true,
    'public_contact_source_ready' => true,
    'commercial_source_ready' => true,
]);
$check(count($publicContract['plan']['capabilities']) === 41, 'public profile did not preserve 41 legacy capabilities');
$check($publicContract['feature_flags']['has_ai_agent'] === false, 'future AI exposed as operational');
$check(PublicProfilePlanCapabilities::normalizePlanCode('pro') === 'professional', 'public adapter alias mismatch');

$app = (string)file_get_contents($root . '/assets/js/app.js');
$policyUi = (string)file_get_contents($root . '/assets/js/subscription-policy-ui.js');
$api = (string)file_get_contents($root . '/api/subscriptions/index.php');
$migration = (string)file_get_contents($root . '/modules/profiles/db/2026_07_18_add_plan_capability_policy_v1_fields.sql');
$check(strpos($app, 'SUBSCRIPTION_PLAN_PRICE_MATRIX') === false, 'frontend price matrix remains');
$check(strpos($app, 'SUBSCRIPTION_PLAN_RANK') === false, 'frontend rank matrix remains');
$check(strpos($app, 'UI_PLAN_TO_BACKEND_PLAN') === false, 'frontend alias matrix remains');
$check(strpos($app, 'plansFromReadModel') !== false, 'frontend does not consume canonical catalog');
$check(strpos($app, 'purchase_allowed') !== false, 'frontend purchase gate missing');
$check(
    preg_match('/function renderCatalog\(\).*?const policyPurchaseAllowed = purchaseAllowedByPolicy\(\);/s', $app) === 1,
    'frontend catalog policy gate is outside its function scope'
);
$check(strpos($app, 'data-subp-archived-read-only') !== false, 'archived read-only UI missing');
$check(strpos($app, 'required_plan_to_reactivate') !== false, 'archived reactivation explanation missing');
$check(
    strpos($app, 'data-subp-grace-notice') !== false && strpos($app, 'data-subp-policy-regularize') !== false,
    'grace deadline or payment action is missing from frontend'
);
$check(strpos($app, 'data-subp-scheduled-addon-impacts') !== false, 'scheduled add-on impact UI missing');
$check(strpos($policyUi, 'quotaFeatureLabels') !== false, 'frontend quota presentation missing');
$check(strpos($app, 'data-subp-addon-eligibility') !== false, 'frontend add-on eligibility summary missing');
$check(strpos($app, 'data-subp-policy-denials') !== false, 'canonical denial messages are not rendered');
$check(strpos($api, "'scheduled-plan'") !== false, 'scheduled change cancel endpoint missing');
$check(strpos($api, '$planRanks = [') === false, 'local API plan rank matrix remains');
$check(stripos($migration, 'TRUNCATE ') === false, 'destructive truncate in migration');
$check(stripos($migration, 'DROP TABLE') === false, 'destructive table drop in migration');
$check(
    strpos($migration, 'grace_extension_days SMALLINT UNSIGNED NULL DEFAULT NULL') !== false,
    'grace extension storage must remain nullable'
);
foreach (Policy::denialReasons() as $reason) {
    $check(strpos($policyUi, $reason) !== false, 'frontend denial mapping missing: ' . $reason);
}

echo json_encode([
    'ok' => true,
    'suite' => 'SubscriptionReadModelContractTest',
    'assertions' => $assertions,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
