<?php
declare(strict_types=1);

require_once __DIR__ . '/../contracts/OtpProviderPort.php';
require_once __DIR__ . '/../contracts/OtpRateLimitPolicy.php';
require_once __DIR__ . '/../adapters/CanonicalPublicAgendaAdapter.php';

use Agenda\Adapters\CanonicalPublicAgendaAdapter;
use Agenda\Contracts\OtpDeliveryResult;
use Agenda\Contracts\OtpProviderPort;
use Agenda\Contracts\OtpRateLimitDecision;
use Agenda\Contracts\OtpRateLimitPolicy;
use Agenda\Contracts\RejectingOtpProvider;

function cut01cOtpAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function cut01cPublicMethods(string $class): array
{
    $methods = array_map(
        static fn(ReflectionMethod $method): string => $method->getName(),
        (new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC)
    );
    $methods = array_values(array_filter($methods, static fn(string $method): bool => $method !== '__construct'));
    sort($methods, SORT_STRING);
    return $methods;
}

$root = dirname(__DIR__, 3);
$config = require $root . '/modules/agenda/config/agenda.php';
cut01cOtpAssert(($config['feature_flags']['canonical_public_agenda'] ?? null) === false, 'canonical public agenda defaults false');
foreach ([
    [],
    ['feature_flags' => []],
    ['feature_flags' => ['canonical_public_agenda' => null]],
    ['feature_flags' => ['canonical_public_agenda' => 'true']],
    ['feature_flags' => ['canonical_public_agenda' => '1']],
    ['feature_flags' => ['canonical_public_agenda' => 1]],
    ['feature_flags' => ['canonical_public_agenda' => []]],
] as $fixture) {
    cut01cOtpAssert(!CanonicalPublicAgendaAdapter::canonicalPublicAgendaEnabled($fixture), 'flag fails closed');
}
cut01cOtpAssert(CanonicalPublicAgendaAdapter::canonicalPublicAgendaEnabled(['feature_flags' => ['canonical_public_agenda' => true]]), 'literal true is eligible');

cut01cOtpAssert(cut01cPublicMethods(CanonicalPublicAgendaAdapter::class) === ['canonicalPublicAgendaEnabled', 'homogeneousError', 'readiness'], 'adapter API exact');
cut01cOtpAssert(cut01cPublicMethods(OtpProviderPort::class) === ['configured', 'deliver', 'providerId'], 'provider port API exact');
cut01cOtpAssert(cut01cPublicMethods(OtpDeliveryResult::class) === ['accepted', 'providerReference', 'reason', 'toArray'], 'delivery result API exact');
cut01cOtpAssert(cut01cPublicMethods(RejectingOtpProvider::class) === ['configured', 'deliver', 'providerId'], 'rejecting provider API exact');
cut01cOtpAssert(cut01cPublicMethods(OtpRateLimitPolicy::class) === ['approvedParametersPresent', 'evaluate'], 'rate policy API exact');
cut01cOtpAssert(cut01cPublicMethods(OtpRateLimitDecision::class) === ['allowed', 'httpStatus', 'reason', 'toArray'], 'rate decision API exact');

$provider = new RejectingOtpProvider();
cut01cOtpAssert($provider->providerId() === 'rejecting' && !$provider->configured(), 'rejecting provider is stable and unconfigured');
$deliveryOne = $provider->deliver('sms', '+525500000000', '123456', ['free' => 'payload']);
$deliveryTwo = $provider->deliver('email', 'person@example.test', '999999', ['other' => 'value']);
cut01cOtpAssert(!$deliveryOne->accepted() && $deliveryOne->reason() === 'provider_not_configured' && $deliveryOne->providerReference() === null, 'delivery rejects safely');
cut01cOtpAssert($deliveryOne->toArray() === $deliveryTwo->toArray(), 'rejecting delivery is deterministic');
$deliverySerialized = serialize($deliveryOne->toArray());
foreach (['123456', '999999', '+525500000000', 'person@example.test', 'payload'] as $secret) {
    cut01cOtpAssert(!str_contains($deliverySerialized, $secret), 'delivery result excludes input');
}

$policy = new OtpRateLimitPolicy();
$approved = [
    'max_attempts' => 3,
    'window_seconds' => 90,
    'lock_seconds' => 180,
    'dimensions' => ['challenge', 'ip_digest', 'contact_digest', 'profile'],
];
cut01cOtpAssert(!$policy->approvedParametersPresent([]), 'missing policy denied');
foreach (['max_attempts', 'window_seconds', 'lock_seconds', 'dimensions'] as $key) {
    $partial = $approved;
    unset($partial[$key]);
    cut01cOtpAssert(!$policy->approvedParametersPresent($partial), 'partial policy denied: ' . $key);
}
foreach ([null, '3', 0, -1, []] as $invalid) {
    $fixture = $approved;
    $fixture['max_attempts'] = $invalid;
    cut01cOtpAssert(!$policy->approvedParametersPresent($fixture), 'invalid numeric policy denied');
}
cut01cOtpAssert($policy->approvedParametersPresent($approved), 'explicit complete fixture accepted');
$unconfigured = $policy->evaluate([], ['attempts' => 0]);
cut01cOtpAssert(!$unconfigured->allowed() && $unconfigured->reason() === 'rate_limit_policy_unconfigured' && $unconfigured->httpStatus() === 503, 'unconfigured policy denies');
$eligible = $policy->evaluate($approved, ['attempts' => 2]);
$limited = $policy->evaluate($approved, ['attempts' => 3]);
cut01cOtpAssert($eligible->allowed() && $eligible->reason() === 'eligible' && $eligible->httpStatus() === 200, 'explicit fixture eligible decision');
cut01cOtpAssert(!$limited->allowed() && $limited->reason() === 'rate_limited' && $limited->httpStatus() === 429, 'explicit fixture limited decision');
cut01cOtpAssert($eligible->toArray() === $policy->evaluate($approved, ['attempts' => 2])->toArray(), 'rate decision deterministic');
foreach ([$unconfigured->toArray(), $eligible->toArray(), $limited->toArray()] as $decision) {
    cut01cOtpAssert(array_keys($decision) === ['allowed', 'reason', 'http_status'], 'decision contains no labels or PII');
}

$adapter = new CanonicalPublicAgendaAdapter();
$readiness = $adapter->readiness($provider, []);
cut01cOtpAssert($readiness === [
    'mode' => 'dormant_readiness_only',
    'provider_id' => 'rejecting',
    'provider_configured' => false,
    'rate_limit_configured' => false,
    'activation_authorized' => false,
    'ready' => false,
], 'dormant readiness closes');
$approvedReadiness = $adapter->readiness($provider, $approved);
cut01cOtpAssert($approvedReadiness['rate_limit_configured'] === true && $approvedReadiness['ready'] === false, 'provider remains required');

$runtimeFiles = [
    'modules/agenda/controllers/PublicOtpController.php',
    'modules/agenda/controllers/PublicAppointmentsController.php',
    'modules/agenda/services/OtpSender.php',
];
$runtime = '';
foreach ($runtimeFiles as $relative) {
    $value = file_get_contents($root . '/' . $relative);
    cut01cOtpAssert(is_string($value), 'runtime source readable');
    $runtime .= $value;
}
foreach (['otp=%s', 'debug_code', 'otp_debug'] as $forbidden) {
    cut01cOtpAssert(!str_contains($runtime, $forbidden), 'raw OTP exposure removed: ' . $forbidden);
}
$sender = file_get_contents($root . '/modules/agenda/services/OtpSender.php');
cut01cOtpAssert(str_contains($sender, 'delivery_mode=dev_compatibility') && str_contains($sender, 'secret_logged=false'), 'dev sender logs non-sensitive state only');
cut01cOtpAssert(!str_contains($sender, 'maskRecipient') && !str_contains($sender, 'request_id=%s') && !str_contains($sender, 'doctor_id=%s'), 'dev sender excludes recipient and identifiers');

foreach (['PublicOtpController.php', 'PublicAppointmentsController.php'] as $file) {
    $source = file_get_contents($root . '/modules/agenda/controllers/' . $file);
    cut01cOtpAssert(str_contains($source, 'CanonicalPublicAgendaAdapter::canonicalPublicAgendaEnabled'), 'controller evaluates flag');
    cut01cOtpAssert(str_contains($source, 'CanonicalPublicAgendaAdapter::class'), 'controller retains class reference');
    cut01cOtpAssert(!str_contains($source, 'new CanonicalPublicAgendaAdapter'), 'controller does not instantiate adapter');
    cut01cOtpAssert(!str_contains($source, '->readiness(') && !str_contains($source, '->homogeneousError('), 'controller does not execute adapter');
}
$adapterSource = file_get_contents($root . '/modules/agenda/adapters/CanonicalPublicAgendaAdapter.php');
cut01cOtpAssert(!str_contains($adapterSource, 'PublicOtpRepository'), 'canonical adapter has no legacy repository dependency');
cut01cOtpAssert(hash_file('sha256', $root . '/modules/agenda/repositories/PublicOtpRepository.php') === 'f1e18f70ffb41a349efba420ba02ed12c879b5a9679119e6382ce8f13746e1cb', 'legacy OTP repository unchanged');

echo "Cut01CAgendaOtpPrivacyTest PASS\n";
