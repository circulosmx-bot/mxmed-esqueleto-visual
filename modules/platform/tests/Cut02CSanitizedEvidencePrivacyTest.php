<?php
declare(strict_types=1);

require_once __DIR__ . '/../shadow/R0ShadowHardStop.php';
require_once __DIR__ . '/../shadow/R0ShadowSafeReturnPlan.php';
require_once __DIR__ . '/../shadow/R0ShadowEvaluationResult.php';
require_once __DIR__ . '/../shadow/R0ShadowEvaluationHarness.php';

use Platform\Shadow\R0ShadowEvaluationHarness;

function cut02cPrivacyAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function cut02cPrivacySanitized(R0ShadowEvaluationHarness $harness, array $record): array
{
    $fixture = $record['fixture'];
    try {
        $resultData = $harness->evaluate($fixture)->toArray();
        $harnessResult = $resultData['hard_stop'] === null ? 'PASS' : 'FAIL';
    } catch (InvalidArgumentException $exception) {
        cut02cPrivacyAssert(
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

function cut02cPrivacyInspect(
    mixed $value,
    array $prohibited,
    string $path = 'result',
    string $currentKey = ''
): void {
    if (is_array($value)) {
        foreach ($value as $key => $nested) {
            if (is_string($key)) {
                cut02cPrivacyAssert(
                    !in_array(strtolower($key), $prohibited, true),
                    'prohibited evidence key at ' . $path
                );
                cut02cPrivacyAssert(
                    !in_array(strtolower($key), ['payload', 'headers', 'fixture', 'side_effects'], true),
                    'raw evidence key at ' . $path
                );
            }
            cut02cPrivacyInspect(
                $nested,
                $prohibited,
                $path . '.value',
                is_string($key) ? $key : ''
            );
        }
        return;
    }
    if (!is_string($value)) {
        return;
    }
    cut02cPrivacyAssert(
        preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $value) !== 1,
        'email absent'
    );
    cut02cPrivacyAssert(
        preg_match(
            '/\A[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/i',
            $value
        ) !== 1,
        'uuid absent'
    );
    if (!in_array($currentKey, ['legacy_digest', 'canonical_digest'], true)) {
        cut02cPrivacyAssert(
            preg_match('/(?:\+?[0-9][0-9 .()-]{8,}[0-9])/', $value) !== 1,
            'phone absent'
        );
    }
}

$prohibited = [
    'patient_id',
    'appointment_id',
    'doctor_id',
    'name',
    'phone',
    'email',
    'address',
    'birthdate',
    'contact',
    'otp',
    'token',
    'diagnosis',
    'clinical_notes',
    'real_request_bodies',
    'sensitive_headers',
    'cookies',
    'credentials',
    'stack_traces_with_data',
    'production_payloads',
    'real_query_strings',
];
$catalog = require __DIR__ . '/../../../tests/fixtures/cut02c-synthetic-baseline/catalog.php';
$harness = new R0ShadowEvaluationHarness();
$privacyRejections = 0;

cut02cPrivacyAssert(count($prohibited) === 20, 'prohibited evidence category count');
cut02cPrivacyAssert(count(array_unique($prohibited)) === 20, 'prohibited categories unique');

foreach ($catalog as $record) {
    $sanitized = cut02cPrivacySanitized($harness, $record);
    cut02cPrivacyInspect($sanitized, $prohibited);
    cut02cPrivacyAssert(
        str_starts_with($sanitized['correlation_ref'], 'test:')
            && str_starts_with($sanitized['scope_ref'], 'test:'),
        'opaque test references'
    );
    if ($record['scenario_category'] === 'privacy_rejection') {
        ++$privacyRejections;
        $encoded = json_encode($sanitized, JSON_THROW_ON_ERROR);
        cut02cPrivacyAssert(
            !str_contains($encoded, '"patient_id":')
                && !str_contains($encoded, 'synthetic-forbidden-value'),
            'privacy rejection redacted'
        );
        cut02cPrivacyAssert(
            $sanitized['harness_result'] === 'FAIL'
                && $sanitized['hard_stop'] === 'PII_OR_CLINICAL_DATA_DETECTED',
            'privacy hard stop'
        );
    }
}

cut02cPrivacyAssert(count($catalog) === 40, 'catalog count');
cut02cPrivacyAssert($privacyRejections === 5, 'privacy rejection coverage');

echo "PASS_CUT02C_SANITIZED_EVIDENCE_PRIVACY\n";
echo "PROHIBITED_EVIDENCE_TYPES=20/20\n";
echo "PRIVACY_VALIDATION=PASS\n";
