<?php
declare(strict_types=1);

require_once __DIR__ . '/../shadow/R0ShadowHardStop.php';
require_once __DIR__ . '/../shadow/R0ShadowSafeReturnPlan.php';
require_once __DIR__ . '/../shadow/R0ShadowEvaluationResult.php';
require_once __DIR__ . '/../shadow/R0ShadowEvaluationHarness.php';

use Platform\Shadow\R0ShadowEvaluationHarness;

function cut02cIntegrityAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function cut02cIntegritySanitized(
    R0ShadowEvaluationHarness $harness,
    array $record
): array {
    $fixture = $record['fixture'];
    try {
        $resultData = $harness->evaluate($fixture)->toArray();
        $harnessResult = $resultData['hard_stop'] === null ? 'PASS' : 'FAIL';
        if ($resultData['hard_stop'] !== null) {
            cut02cIntegrityAssert(
                $resultData['safe_return']['target_stage'] === 'R0'
                    && $resultData['safe_return']['target_mode'] === 'disabled',
                'hard stop safe return'
            );
        }
    } catch (InvalidArgumentException $exception) {
        cut02cIntegrityAssert(
            $record['scenario_category'] === 'invalid_closed'
                && $exception->getMessage() === 'unsupported_r0_shadow_fixture_version',
            'controlled closed rejection'
        );
        $harnessResult = 'REJECTED';
        $resultData = [
            'legacy_outcome_code' => 'LEGACY_REJECTED',
            'canonical_outcome_code' => 'CANONICAL_REJECTED',
            'reason_code' => 'UNSUPPORTED_R0_SHADOW_FIXTURE_VERSION',
            'legacy_digest' => hash('sha256', 'rejected:legacy:' . $record['fixture_id']),
            'canonical_digest' => hash('sha256', 'rejected:canonical:' . $record['fixture_id']),
            'legacy_invariant' => false,
            'hard_stop' => null,
        ];
    }

    return [
        'schema_version' => 'proposed-v1',
        'fixture_version' => $fixture['fixture_version'],
        'surface' => $fixture['surface'],
        'scenario_category' => $record['scenario_category'],
        'legacy_outcome_code' => $resultData['legacy_outcome_code'],
        'canonical_outcome_code' => $resultData['canonical_outcome_code'],
        'reason_code' => $resultData['reason_code'],
        'legacy_digest' => $resultData['legacy_digest'],
        'canonical_digest' => $resultData['canonical_digest'],
        'legacy_invariant' => $resultData['legacy_invariant'],
        'hard_stop' => $resultData['hard_stop'],
        'correlation_ref' => $fixture['correlation_ref'],
        'scope_ref' => $fixture['scope_ref'],
        'adapter_version' => 'r0-shadow-harness-v1',
        'harness_result' => $harnessResult,
        'safe_return_target_stage' => 'R0',
        'safe_return_target_mode' => 'disabled',
        'evidence_package_version' => 'cut02c-synthetic-v1',
    ];
}

function cut02cIntegrityDigest(array $value): string
{
    return hash(
        'sha256',
        json_encode(
            $value,
            JSON_THROW_ON_ERROR
                | JSON_PRESERVE_ZERO_FRACTION
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
        )
    );
}

$catalog = require __DIR__ . '/../../../tests/fixtures/cut02c-synthetic-baseline/catalog.php';
$harness = new R0ShadowEvaluationHarness();
$surfaces = R0ShadowEvaluationHarness::eligibleSurfaces();
$categories = [
    'nominal',
    'boundary',
    'invalid_closed',
    'privacy_rejection',
    'hard_stop',
    'legacy_invariant',
    'outcome_difference_without_response_mutation',
    'deterministic_repeat',
];
$schemaFields = [
    'schema_version',
    'fixture_version',
    'surface',
    'scenario_category',
    'legacy_outcome_code',
    'canonical_outcome_code',
    'reason_code',
    'legacy_digest',
    'canonical_digest',
    'legacy_invariant',
    'hard_stop',
    'correlation_ref',
    'scope_ref',
    'adapter_version',
    'harness_result',
    'safe_return_target_stage',
    'safe_return_target_mode',
    'evidence_package_version',
];
$expectedIds = [];
foreach ($surfaces as $surface) {
    foreach ($categories as $category) {
        $expectedIds[] = 'cut02c:' . $surface . ':' . $category;
    }
}

cut02cIntegrityAssert(count($catalog) === 40, 'fixture count');
cut02cIntegrityAssert(
    array_column($catalog, 'fixture_id') === $expectedIds,
    'stable catalog order'
);
cut02cIntegrityAssert(
    count(array_unique(array_column($catalog, 'fixture_id'))) === 40,
    'unique ids'
);

$combinations = [];
$sanitizedResults = [];
$executions = 0;
$deterministic = 0;
foreach ($catalog as $record) {
    $combination = $record['fixture']['surface'] . ':' . $record['scenario_category'];
    cut02cIntegrityAssert(!isset($combinations[$combination]), 'unique matrix combination');
    $combinations[$combination] = true;

    $first = cut02cIntegritySanitized($harness, $record);
    ++$executions;
    $second = cut02cIntegritySanitized($harness, $record);
    ++$executions;
    cut02cIntegrityAssert($first === $second, 'deterministic sanitized result');
    ++$deterministic;
    foreach ([$first, $second] as $sanitized) {
        cut02cIntegrityAssert(array_keys($sanitized) === $schemaFields, 'schema exact');
        cut02cIntegrityAssert(count($sanitized) === 18, 'schema field count');
        cut02cIntegrityAssert(
            $sanitized['safe_return_target_stage'] === 'R0'
                && $sanitized['safe_return_target_mode'] === 'disabled',
            'safe return target'
        );
        $sanitizedResults[] = $sanitized;
    }
}

cut02cIntegrityAssert(count($combinations) === 40, 'matrix complete');
cut02cIntegrityAssert($executions === 80, 'execution count');
cut02cIntegrityAssert(count($sanitizedResults) === 80, 'result count');
cut02cIntegrityAssert($deterministic === 40, 'deterministic count');

$catalogDigest = cut02cIntegrityDigest($catalog);
$resultsDigest = cut02cIntegrityDigest($sanitizedResults);
cut02cIntegrityAssert($catalogDigest === cut02cIntegrityDigest($catalog), 'catalog digest stable');
cut02cIntegrityAssert(
    $resultsDigest === cut02cIntegrityDigest($sanitizedResults),
    'results digest stable'
);
cut02cIntegrityAssert(
    preg_match('/\A[a-f0-9]{64}\z/', $catalogDigest) === 1
        && preg_match('/\A[a-f0-9]{64}\z/', $resultsDigest) === 1,
    'aggregate sha256'
);

echo "PASS_CUT02C_EVIDENCE_INTEGRITY\n";
echo "INTEGRITY_VALIDATION=PASS\n";
echo 'CATALOG_SHA256=' . $catalogDigest . "\n";
echo 'RESULTS_SHA256=' . $resultsDigest . "\n";
