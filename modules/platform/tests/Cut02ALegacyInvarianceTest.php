<?php
declare(strict_types=1);

require_once __DIR__ . '/../shadow/R0ShadowHardStop.php';
require_once __DIR__ . '/../shadow/R0ShadowSafeReturnPlan.php';
require_once __DIR__ . '/../shadow/R0ShadowEvaluationResult.php';
require_once __DIR__ . '/../shadow/R0ShadowEvaluationHarness.php';

use Platform\Shadow\R0ShadowEvaluationHarness;

function cut02aInvarianceAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function cut02aInvarianceFixture(): array
{
    return [
        'fixture_version' => 1,
        'surface' => 'canonical_schedule_read',
        'correlation_ref' => 'test:correlation:invariance',
        'scope_ref' => 'test:scope:invariance',
        'legacy' => [
            'status' => 200,
            'headers' => ['X-Trace' => 'opaque', 'Content-Type' => 'application/json'],
            'payload' => [
                'result' => ['b' => 2, 'a' => 1],
                'sequence' => ['first', 'second'],
                'scalar' => 1.0,
            ],
            'outcome_code' => 'LEGACY_ACCEPTED',
        ],
        'canonical' => [
            'status' => 200,
            'headers' => ['content-type' => 'application/json', 'x-trace' => 'opaque'],
            'payload' => [
                'scalar' => 1.0,
                'sequence' => ['first', 'second'],
                'result' => ['a' => 1, 'b' => 2],
            ],
            'outcome_code' => 'CANONICAL_DIAGNOSTIC_DIFFERENT',
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

function cut02aInvarianceHardStop(R0ShadowEvaluationHarness $harness, array $fixture): array
{
    $result = $harness->evaluate($fixture);
    cut02aInvarianceAssert(!$result->legacyInvariant(), 'changed response not invariant');
    cut02aInvarianceAssert($result->safeReturn() !== null, 'changed response has safe return');
    $serialized = $result->toArray();
    cut02aInvarianceAssert($serialized['safe_return']['legacy_continues'] === true, 'legacy continues');
    cut02aInvarianceAssert(
        $serialized['safe_return']['canonical_response_allowed'] === false,
        'canonical response forbidden'
    );
    cut02aInvarianceAssert(
        $serialized['safe_return']['canonical_write_allowed'] === false,
        'canonical write forbidden'
    );
    return $serialized;
}

$harness = new R0ShadowEvaluationHarness();
$fixture = cut02aInvarianceFixture();
$result = $harness->evaluate($fixture)->toArray();
cut02aInvarianceAssert($result['legacy_invariant'] === true, 'same response passes');
cut02aInvarianceAssert($result['reason_code'] === 'LEGACY_INVARIANT', 'invariance reason exact');
cut02aInvarianceAssert($result['hard_stop'] === null, 'same response no hard stop');
cut02aInvarianceAssert($result['safe_return'] === null, 'same response no safe return');
cut02aInvarianceAssert(
    $result['legacy_outcome_code'] !== $result['canonical_outcome_code'],
    'diagnostic outcomes may differ'
);
cut02aInvarianceAssert(
    $result['legacy_digest'] === $result['canonical_digest'],
    'canonical response digest equal'
);

$statusChanged = cut02aInvarianceFixture();
$statusChanged['canonical']['status'] = 201;
$statusResult = cut02aInvarianceHardStop($harness, $statusChanged);
cut02aInvarianceAssert($statusResult['hard_stop'] === 'HTTP_STATUS_CHANGED', 'status hard stop');

$headersChanged = cut02aInvarianceFixture();
$headersChanged['canonical']['headers']['x-trace'] = 'different';
$headersResult = cut02aInvarianceHardStop($harness, $headersChanged);
cut02aInvarianceAssert($headersResult['hard_stop'] === 'HTTP_HEADERS_CHANGED', 'header hard stop');

$payloadChanged = cut02aInvarianceFixture();
$payloadChanged['canonical']['payload']['sequence'] = ['second', 'first'];
$payloadResult = cut02aInvarianceHardStop($harness, $payloadChanged);
cut02aInvarianceAssert($payloadResult['hard_stop'] === 'PAYLOAD_CHANGED', 'payload hard stop');

$multipleChanged = cut02aInvarianceFixture();
$multipleChanged['canonical']['status'] = 201;
$multipleChanged['canonical']['payload']['sequence'] = ['second', 'first'];
$multipleResult = cut02aInvarianceHardStop($harness, $multipleChanged);
cut02aInvarianceAssert(
    $multipleResult['hard_stop'] === 'LEGACY_RESPONSE_CHANGED',
    'multiple response dimensions use umbrella hard stop'
);

cut02aInvarianceAssert(
    $statusResult['legacy_digest'] !== $statusResult['canonical_digest'],
    'status difference changes digest'
);
cut02aInvarianceAssert(
    $headersResult['legacy_digest'] !== $headersResult['canonical_digest'],
    'header difference changes digest'
);
cut02aInvarianceAssert(
    $payloadResult['legacy_digest'] !== $payloadResult['canonical_digest'],
    'payload difference changes digest'
);

$caseAndOrderOnly = cut02aInvarianceFixture();
$caseAndOrderOnly['canonical']['headers'] = [
    'X-TRACE' => 'opaque',
    'CONTENT-TYPE' => 'application/json',
];
cut02aInvarianceAssert(
    $harness->evaluate($caseAndOrderOnly)->legacyInvariant(),
    'header key case and order normalized'
);

$payloadObjectOrderOnly = cut02aInvarianceFixture();
$payloadObjectOrderOnly['canonical']['payload']['result'] = ['b' => 2, 'a' => 1];
cut02aInvarianceAssert(
    $harness->evaluate($payloadObjectOrderOnly)->legacyInvariant(),
    'object keys sorted'
);

echo "Cut02ALegacyInvarianceTest PASS\n";
