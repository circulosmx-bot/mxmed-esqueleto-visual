<?php
declare(strict_types=1);

require_once __DIR__ . '/../contracts/Pg03CutoverFeatureFlagPort.php';

use Platform\Contracts\ClosedPg03CutoverFeatureFlagRegistry;
use Platform\Contracts\Pg03CutoverFeatureFlagPort;

function cut01dFlagAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function cut01dFlagPublicMethods(string $class): array
{
    $methods = array_map(
        static fn(ReflectionMethod $method): string => $method->getName(),
        (new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC)
    );
    $methods = array_values(array_filter($methods, static fn(string $method): bool => $method !== '__construct'));
    sort($methods, SORT_STRING);
    return $methods;
}

function cut01dFlagSource(string $path): string
{
    $lines = file($path);
    cut01dFlagAssert(is_array($lines), 'source readable');
    return implode('', $lines);
}

$root = dirname(__DIR__, 3);
$expected = [
    'canonical_actor_authority',
    'canonical_schedule_read',
    'canonical_availability_compare',
    'canonical_public_agenda',
    'canonical_appointment_lifecycle',
    'canonical_patient_identity',
    'patient_identity_persistence',
    'legacy_write_disable',
    'shadow_audit',
    'read_compare',
    'backfill',
];
$config = require $root . '/modules/agenda/config/agenda.php';
cut01dFlagAssert(array_keys($config['feature_flags']) === $expected, 'eleven flag names and order exact');
cut01dFlagAssert(count($config['feature_flags']) === 11, 'eleven flags exact');
foreach ($config['feature_flags'] as $value) {
    cut01dFlagAssert($value === false, 'all defaults literal false');
}

$registry = new ClosedPg03CutoverFeatureFlagRegistry($config);
cut01dFlagAssert($registry instanceof Pg03CutoverFeatureFlagPort, 'registry implements port');
cut01dFlagAssert($registry->knownFlags() === $expected, 'closed registry exact');
foreach ($expected as $flag) {
    cut01dFlagAssert(!$registry->configuredValue($flag), 'real config remains false');
    cut01dFlagAssert(!$registry->effectiveEnabled($flag), 'effective flag remains disabled');
}

$unsafeConfig = ['feature_flags' => array_fill_keys($expected, true)];
$diagnostic = new ClosedPg03CutoverFeatureFlagRegistry($unsafeConfig);
cut01dFlagAssert($diagnostic->configuredValue($expected[0]), 'literal true detected for diagnostics');
foreach ($expected as $flag) {
    cut01dFlagAssert(!$diagnostic->effectiveEnabled($flag), 'literal true never becomes effective');
}
foreach ([null, 'true', '1', 1, 1.0, [], new stdClass()] as $invalid) {
    $fixture = new ClosedPg03CutoverFeatureFlagRegistry(['feature_flags' => [$expected[0] => $invalid]]);
    cut01dFlagAssert(!$fixture->configuredValue($expected[0]), 'invalid value fails closed');
}
cut01dFlagAssert(!$registry->configuredValue('unknown_flag'), 'unknown configured flag fails closed');
cut01dFlagAssert(!$registry->effectiveEnabled('unknown_flag'), 'unknown effective flag fails closed');

cut01dFlagAssert(cut01dFlagPublicMethods(Pg03CutoverFeatureFlagPort::class) === [
    'configuredValue',
    'effectiveEnabled',
    'knownFlags',
    'readiness',
], 'feature port API exact');
cut01dFlagAssert(cut01dFlagPublicMethods(ClosedPg03CutoverFeatureFlagRegistry::class) === [
    'configuredValue',
    'effectiveEnabled',
    'knownFlags',
    'readiness',
], 'registry API exact');
cut01dFlagAssert($registry->readiness() === [
    'mode' => 'r0_registry_only',
    'known_flags' => 11,
    'configured_true_flags' => [],
    'activation_authorized' => false,
    'all_effective_disabled' => true,
    'ready' => false,
], 'registry readiness exact');
cut01dFlagAssert($diagnostic->readiness()['configured_true_flags'] === $expected, 'unsafe configuration diagnosed');

$source = cut01dFlagSource($root . '/modules/platform/contracts/Pg03CutoverFeatureFlagPort.php')
    . cut01dFlagSource($root . '/modules/agenda/config/agenda.php');
foreach ([
    '$' . '_GET',
    '$' . '_POST',
    '$' . '_REQUEST',
    '$' . '_SERVER',
    '$' . '_COOKIE',
    'get' . 'env(',
    'local' . 'Storage',
] as $forbidden) {
    cut01dFlagAssert(!str_contains($source, $forbidden), 'no request, client, or environment override');
}

echo "Cut01DFeatureFlagDefaultsTest PASS\n";
