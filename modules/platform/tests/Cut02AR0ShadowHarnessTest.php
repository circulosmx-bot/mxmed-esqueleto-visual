<?php
declare(strict_types=1);

require_once __DIR__ . '/../shadow/R0ShadowHardStop.php';
require_once __DIR__ . '/../shadow/R0ShadowSafeReturnPlan.php';
require_once __DIR__ . '/../shadow/R0ShadowEvaluationResult.php';
require_once __DIR__ . '/../shadow/R0ShadowEvaluationHarness.php';

use Platform\Shadow\R0ShadowEvaluationHarness;
use Platform\Shadow\R0ShadowEvaluationResult;

function cut02aHarnessAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function cut02aHarnessThrows(callable $callback, string $reason, string $message): void
{
    try {
        $callback();
    } catch (InvalidArgumentException $exception) {
        cut02aHarnessAssert($exception->getMessage() === $reason, $message . ' reason');
        return;
    }
    throw new RuntimeException($message);
}

function cut02aHarnessFixture(string $surface): array
{
    return [
        'fixture_version' => 1,
        'surface' => $surface,
        'correlation_ref' => 'test:correlation:alpha',
        'scope_ref' => 'test:scope:alpha',
        'legacy' => [
            'status' => 200,
            'headers' => ['X-Mode' => 'legacy', 'Content-Type' => 'application/json'],
            'payload' => ['allowed' => true, 'items' => ['alpha', 'beta']],
            'outcome_code' => 'LEGACY_OK',
        ],
        'canonical' => [
            'status' => 200,
            'headers' => ['content-type' => 'application/json', 'x-mode' => 'legacy'],
            'payload' => ['items' => ['alpha', 'beta'], 'allowed' => true],
            'outcome_code' => 'CANONICAL_MATCH',
        ],
        'side_effects' => [
            'canonical_write_attempted' => false,
            'new_db_connection_attempted' => false,
            'sql_or_ddl_attempted' => false,
            'real_otp_attempted' => false,
            'clinical_request_attempted' => false,
            'scope_leakage_detected' => false,
            'unexpected_side_effect' => false,
        ],
        'authority_audit_available' => true,
    ];
}

$harness = new R0ShadowEvaluationHarness();
$surfaces = [
    'canonical_actor_authority',
    'canonical_schedule_read',
    'canonical_availability_compare',
    'canonical_appointment_lifecycle',
    'canonical_patient_identity',
];
cut02aHarnessAssert(R0ShadowEvaluationHarness::eligibleSurfaces() === $surfaces, 'five surfaces exact');

foreach ($surfaces as $surface) {
    $fixture = cut02aHarnessFixture($surface);
    $before = $fixture;
    $result = $harness->evaluate($fixture);
    cut02aHarnessAssert($result instanceof R0ShadowEvaluationResult, 'result type exact');
    cut02aHarnessAssert($result->legacyInvariant(), 'eligible fixture invariant');
    cut02aHarnessAssert($result->hardStop() === null, 'eligible fixture has no hard stop');
    cut02aHarnessAssert($result->safeReturn() === null, 'eligible fixture needs no safe return');
    cut02aHarnessAssert($fixture === $before, 'fixture snapshot remains immutable');
}

$unknown = cut02aHarnessFixture('canonical_schedule_read');
$unknown['surface'] = 'canonical_public_agenda';
cut02aHarnessThrows(
    static fn() => $harness->evaluate($unknown),
    'UNKNOWN_OPERATION',
    'unknown surface rejected'
);

$missingVersion = cut02aHarnessFixture('canonical_schedule_read');
unset($missingVersion['fixture_version']);
cut02aHarnessThrows(
    static fn() => $harness->evaluate($missingVersion),
    'unsupported_r0_shadow_fixture_version',
    'fixture version required'
);

foreach (['raw reference', 'https://example.test/value', 'test/path', ''] as $unsafe) {
    $fixture = cut02aHarnessFixture('canonical_schedule_read');
    $fixture['correlation_ref'] = $unsafe;
    cut02aHarnessThrows(
        static fn() => $harness->evaluate($fixture),
        'invalid_r0_shadow_test_reference',
        'unsafe reference rejected'
    );
}

$fixture = cut02aHarnessFixture('canonical_schedule_read');
$first = $harness->evaluate($fixture)->toArray();
$second = $harness->evaluate($fixture)->toArray();
cut02aHarnessAssert($first === $second, 'repeated fixture deterministic');
cut02aHarnessAssert(
    array_keys($first) === [
        'surface',
        'legacy_outcome_code',
        'canonical_outcome_code',
        'reason_code',
        'correlation_ref',
        'scope_ref',
        'legacy_digest',
        'canonical_digest',
        'legacy_invariant',
        'hard_stop',
        'safe_return',
    ],
    'result serialization order exact'
);
cut02aHarnessAssert(
    preg_match('/\A[a-f0-9]{64}\z/', $first['legacy_digest']) === 1,
    'legacy digest sha256'
);
cut02aHarnessAssert(
    preg_match('/\A[a-f0-9]{64}\z/', $first['canonical_digest']) === 1,
    'canonical digest sha256'
);
cut02aHarnessAssert($first['legacy_digest'] === $first['canonical_digest'], 'equal response digests');

$invalidPayloads = [
    new stdClass(),
    static fn(): bool => true,
];
foreach ($invalidPayloads as $invalidPayload) {
    $fixture = cut02aHarnessFixture('canonical_schedule_read');
    $fixture['canonical']['payload'] = $invalidPayload;
    cut02aHarnessThrows(
        static fn() => $harness->evaluate($fixture),
        'non_serializable_r0_shadow_payload',
        'arbitrary payload rejected'
    );
}

cut02aHarnessAssert(R0ShadowEvaluationHarness::fixtureSchema() === [
    'fixture_version' => 1,
    'fixture_keys' => [
        'fixture_version',
        'surface',
        'correlation_ref',
        'scope_ref',
        'legacy',
        'canonical',
        'side_effects',
        'authority_audit_available',
    ],
    'snapshot_keys' => ['status', 'headers', 'payload', 'outcome_code'],
    'side_effect_keys' => [
        'canonical_write_attempted',
        'new_db_connection_attempted',
        'sql_or_ddl_attempted',
        'real_otp_attempted',
        'clinical_request_attempted',
        'scope_leakage_detected',
        'unexpected_side_effect',
    ],
], 'fixture schema closed');

cut02aHarnessAssert($harness->readiness() === [
    'mode' => 'offline_deterministic',
    'sampling' => 0,
    'baseline_collection_authorized' => false,
    'observation_window_approved' => false,
    'rollout_stage' => 'R0',
    'rollout_mode' => 'disabled',
    'runtime_wired' => false,
    'real_traffic' => false,
], 'R0 readiness exact');

$root = dirname(__DIR__, 3);
$productionFiles = [
    'modules/platform/shadow/R0ShadowEvaluationHarness.php',
    'modules/platform/shadow/R0ShadowEvaluationResult.php',
    'modules/platform/shadow/R0ShadowHardStop.php',
    'modules/platform/shadow/R0ShadowSafeReturnPlan.php',
];
$source = '';
foreach ($productionFiles as $productionFile) {
    $lines = file($root . '/' . $productionFile);
    cut02aHarnessAssert(is_array($lines), 'production source readable');
    $source .= implode('', $lines);
}
foreach ([
    'Pg03CutoverFeatureFlagPort',
    'ClosedPg03CutoverFeatureFlagRegistry',
    'effectiveEnabled(',
    'require_once',
    'Composer',
] as $runtimeDependency) {
    cut02aHarnessAssert(!str_contains($source, $runtimeDependency), 'zero runtime dependency');
}

cut02aHarnessAssert((new ReflectionClass(R0ShadowEvaluationResult::class))->isReadOnly(), 'result readonly');
cut02aHarnessAssert((new ReflectionClass(R0ShadowEvaluationResult::class))->isFinal(), 'result final');
cut02aHarnessAssert((new ReflectionClass(R0ShadowEvaluationHarness::class))->isFinal(), 'harness final');

echo "Cut02AR0ShadowHarnessTest PASS\n";
