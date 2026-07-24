<?php
declare(strict_types=1);

require_once __DIR__ . '/../shadow/R0ShadowHardStop.php';
require_once __DIR__ . '/../shadow/R0ShadowSafeReturnPlan.php';
require_once __DIR__ . '/../shadow/R0ShadowEvaluationResult.php';
require_once __DIR__ . '/../shadow/R0ShadowEvaluationHarness.php';

use Platform\Shadow\R0ShadowEvaluationHarness;

function cut02cExecutionAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function cut02cExecutionSanitized(
    R0ShadowEvaluationHarness $harness,
    array $record
): array {
    $fixture = $record['fixture'];
    try {
        $result = $harness->evaluate($fixture);
        $resultData = $result->toArray();
        $harnessResult = $resultData['hard_stop'] === null ? 'PASS' : 'FAIL';
        if ($resultData['hard_stop'] === null) {
            cut02cExecutionAssert($resultData['safe_return'] === null, 'pass safe return null');
        } else {
            cut02cExecutionAssert(is_array($resultData['safe_return']), 'hard stop safe return');
            cut02cExecutionAssert(
                $resultData['safe_return']['target_stage'] === 'R0'
                    && $resultData['safe_return']['target_mode'] === 'disabled',
                'hard stop safe return target'
            );
        }
    } catch (InvalidArgumentException $exception) {
        cut02cExecutionAssert(
            $record['scenario_category'] === 'invalid_closed',
            'only closed fixture rejected'
        );
        cut02cExecutionAssert(
            $exception->getMessage() === 'unsupported_r0_shadow_fixture_version',
            'closed rejection reason'
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

cut02cExecutionAssert(count($catalog) === 40, 'fixture count');
$ids = [];
$matrix = [];
$executions = 0;
$deterministic = 0;
$summary = ['PASS' => 0, 'FAIL' => 0, 'REJECTED' => 0];

foreach ($catalog as $record) {
    cut02cExecutionAssert(
        array_keys($record) === [
            'fixture_id',
            'scenario_category',
            'expected_harness_result',
            'expected_hard_stop',
            'fixture',
        ],
        'catalog record schema'
    );
    $fixture = $record['fixture'];
    $expectedId = 'cut02c:' . $fixture['surface'] . ':' . $record['scenario_category'];
    cut02cExecutionAssert($record['fixture_id'] === $expectedId, 'fixture id');
    cut02cExecutionAssert(!isset($ids[$record['fixture_id']]), 'fixture id unique');
    $ids[$record['fixture_id']] = true;
    $combination = $fixture['surface'] . ':' . $record['scenario_category'];
    cut02cExecutionAssert(!isset($matrix[$combination]), 'matrix combination unique');
    $matrix[$combination] = true;

    $first = cut02cExecutionSanitized($harness, $record);
    ++$executions;
    $second = cut02cExecutionSanitized($harness, $record);
    ++$executions;
    cut02cExecutionAssert($first === $second, 'serialized result deterministic');
    ++$deterministic;

    cut02cExecutionAssert(array_keys($first) === $schemaFields, 'schema fields exact');
    cut02cExecutionAssert($first['schema_version'] === 'proposed-v1', 'schema version');
    cut02cExecutionAssert(
        $first['evidence_package_version'] === 'cut02c-synthetic-v1',
        'package version'
    );
    cut02cExecutionAssert(
        $first['adapter_version'] === 'r0-shadow-harness-v1',
        'adapter version'
    );
    cut02cExecutionAssert(
        $first['harness_result'] === $record['expected_harness_result'],
        'expected harness result'
    );
    cut02cExecutionAssert(
        $first['hard_stop'] === $record['expected_hard_stop'],
        'expected hard stop'
    );
    cut02cExecutionAssert(
        preg_match('/\A[a-f0-9]{64}\z/', $first['legacy_digest']) === 1
            && preg_match('/\A[a-f0-9]{64}\z/', $first['canonical_digest']) === 1,
        'sha256 digests'
    );
    cut02cExecutionAssert(
        preg_match('/\Atest:[a-z0-9][a-z0-9._:-]*\z/', $first['correlation_ref']) === 1
            && preg_match('/\Atest:[a-z0-9][a-z0-9._:-]*\z/', $first['scope_ref']) === 1,
        'opaque test references'
    );
    cut02cExecutionAssert(
        $first['safe_return_target_stage'] === 'R0'
            && $first['safe_return_target_mode'] === 'disabled',
        'sanitized safe return target'
    );
    if ($record['expected_harness_result'] === 'PASS') {
        cut02cExecutionAssert($first['legacy_invariant'] === true, 'pass invariant');
    }
    if ($record['scenario_category'] === 'outcome_difference_without_response_mutation') {
        cut02cExecutionAssert(
            $first['legacy_outcome_code'] !== $first['canonical_outcome_code'],
            'outcome difference preserved'
        );
    }
    ++$summary[$first['harness_result']];
}

foreach ($surfaces as $surface) {
    foreach ($categories as $category) {
        cut02cExecutionAssert(isset($matrix[$surface . ':' . $category]), 'matrix complete');
    }
}

cut02cExecutionAssert(count($ids) === 40, 'unique fixture ids');
cut02cExecutionAssert(count($matrix) === 40, 'matrix size');
cut02cExecutionAssert($executions === 80, 'execution count');
cut02cExecutionAssert($deterministic === 40, 'deterministic result count');
cut02cExecutionAssert($summary === ['PASS' => 25, 'FAIL' => 10, 'REJECTED' => 5], 'summary');

echo "PASS_CUT02C_SYNTHETIC_BASELINE_EXECUTION\n";
echo "FIXTURES=40/40\n";
echo "EXECUTIONS=80/80\n";
echo "DETERMINISTIC_RESULTS=40/40\n";
