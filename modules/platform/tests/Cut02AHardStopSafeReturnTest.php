<?php
declare(strict_types=1);

require_once __DIR__ . '/../shadow/R0ShadowHardStop.php';
require_once __DIR__ . '/../shadow/R0ShadowSafeReturnPlan.php';
require_once __DIR__ . '/../shadow/R0ShadowEvaluationResult.php';
require_once __DIR__ . '/../shadow/R0ShadowEvaluationHarness.php';

use Platform\Shadow\R0ShadowEvaluationHarness;
use Platform\Shadow\R0ShadowHardStop;
use Platform\Shadow\R0ShadowSafeReturnPlan;

function cut02aHardStopAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function cut02aHardStopFixture(string $surface = 'canonical_schedule_read'): array
{
    return [
        'fixture_version' => 1,
        'surface' => $surface,
        'correlation_ref' => 'test:correlation:hard-stop',
        'scope_ref' => 'test:scope:hard-stop',
        'legacy' => [
            'status' => 200,
            'headers' => ['Content-Type' => 'application/json'],
            'payload' => ['allowed' => true],
            'outcome_code' => 'LEGACY_OK',
        ],
        'canonical' => [
            'status' => 200,
            'headers' => ['content-type' => 'application/json'],
            'payload' => ['allowed' => true],
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

$catalog = [
    'PII_OR_CLINICAL_DATA_DETECTED',
    'LEGACY_RESPONSE_CHANGED',
    'HTTP_STATUS_CHANGED',
    'HTTP_HEADERS_CHANGED',
    'PAYLOAD_CHANGED',
    'CANONICAL_WRITE_ATTEMPTED',
    'NEW_DB_CONNECTION_ATTEMPTED',
    'SQL_OR_DDL_ATTEMPTED',
    'REAL_OTP_ATTEMPTED',
    'CLINICAL_REQUEST_ATTEMPTED',
    'SCOPE_LEAKAGE_DETECTED',
    'AUTHORITY_AUDIT_UNAVAILABLE',
    'UNKNOWN_OPERATION',
    'UNEXPECTED_SIDE_EFFECT',
    'BUDGET_BREACH_AFTER_APPROVAL',
];
cut02aHardStopAssert(R0ShadowHardStop::all() === $catalog, 'hard stop catalog exact and stable');

foreach ($catalog as $hardStop) {
    cut02aHardStopAssert(R0ShadowHardStop::isEligible($hardStop), 'hard stop eligible');
    $plan = (new R0ShadowSafeReturnPlan($hardStop))->toArray();
    cut02aHardStopAssert($plan === [
        'trigger' => $hardStop,
        'source_stage' => 'R0',
        'source_mode' => 'disabled',
        'target_stage' => 'R0',
        'target_mode' => 'disabled',
        'new_evaluations_allowed' => false,
        'legacy_continues' => true,
        'canonical_response_allowed' => false,
        'canonical_write_allowed' => false,
        'preserve_sanitized_evidence' => true,
        'sql_rollback_required' => false,
        'database_action' => 'none',
        'clinical_action' => 'none',
        'otp_action' => 'none',
    ], 'safe return exact for each hard stop');
}

cut02aHardStopAssert(
    preg_match('/[0-9%]/', 'BUDGET_BREACH_AFTER_APPROVAL') === 0,
    'budget hard stop has no figure'
);
cut02aHardStopAssert(!R0ShadowHardStop::isEligible('FREE_TEXT'), 'free text ineligible');
try {
    new R0ShadowSafeReturnPlan('FREE_TEXT');
    throw new RuntimeException('unknown hard stop accepted');
} catch (InvalidArgumentException $exception) {
    cut02aHardStopAssert(
        $exception->getMessage() === 'unknown_r0_shadow_hard_stop',
        'unknown hard stop rejected'
    );
}

$harness = new R0ShadowEvaluationHarness();
$sideEffectMap = [
    'canonical_write_attempted' => 'CANONICAL_WRITE_ATTEMPTED',
    'new_db_connection_attempted' => 'NEW_DB_CONNECTION_ATTEMPTED',
    'sql_or_ddl_attempted' => 'SQL_OR_DDL_ATTEMPTED',
    'real_otp_attempted' => 'REAL_OTP_ATTEMPTED',
    'clinical_request_attempted' => 'CLINICAL_REQUEST_ATTEMPTED',
    'scope_leakage_detected' => 'SCOPE_LEAKAGE_DETECTED',
    'unexpected_side_effect' => 'UNEXPECTED_SIDE_EFFECT',
];
foreach ($sideEffectMap as $field => $expectedHardStop) {
    $fixture = cut02aHardStopFixture();
    $fixture['side_effects'][$field] = true;
    $result = $harness->evaluate($fixture)->toArray();
    cut02aHardStopAssert($result['hard_stop'] === $expectedHardStop, 'side effect mapping exact');
    cut02aHardStopAssert($result['safe_return']['target_stage'] === 'R0', 'side effect returns R0');
}

$authority = cut02aHardStopFixture('canonical_actor_authority');
$authority['authority_audit_available'] = false;
cut02aHardStopAssert(
    $harness->evaluate($authority)->hardStop() === 'AUTHORITY_AUDIT_UNAVAILABLE',
    'authority audit hard stop'
);

$otherSurface = cut02aHardStopFixture('canonical_schedule_read');
$otherSurface['authority_audit_available'] = false;
cut02aHardStopAssert(
    $harness->evaluate($otherSurface)->legacyInvariant(),
    'authority audit not invented for other surface'
);

$forbiddenKeys = [
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
    'clinical',
    'notes',
    'cookies',
    'authorization',
    'password',
    'secret',
];
foreach ($forbiddenKeys as $forbiddenKey) {
    $fixture = cut02aHardStopFixture();
    $fixture['canonical']['payload']['nested'] = [$forbiddenKey => 'redacted-input'];
    $result = $harness->evaluate($fixture)->toArray();
    cut02aHardStopAssert(
        $result['hard_stop'] === 'PII_OR_CLINICAL_DATA_DETECTED',
        'forbidden key detected at depth'
    );
    cut02aHardStopAssert(
        !str_contains(serialize($result), 'redacted-input'),
        'sensitive input not retained'
    );
}

$priority = cut02aHardStopFixture();
$priority['canonical']['payload']['name'] = 'redacted-input';
$priority['side_effects']['canonical_write_attempted'] = true;
cut02aHardStopAssert(
    $harness->evaluate($priority)->hardStop() === 'PII_OR_CLINICAL_DATA_DETECTED',
    'privacy has stable first priority'
);

$sideEffectPriority = cut02aHardStopFixture();
$sideEffectPriority['side_effects']['canonical_write_attempted'] = true;
$sideEffectPriority['side_effects']['unexpected_side_effect'] = true;
cut02aHardStopAssert(
    $harness->evaluate($sideEffectPriority)->hardStop() === 'CANONICAL_WRITE_ATTEMPTED',
    'side effect priority stable'
);

cut02aHardStopAssert((new ReflectionClass(R0ShadowHardStop::class))->isFinal(), 'catalog final');
cut02aHardStopAssert((new ReflectionClass(R0ShadowSafeReturnPlan::class))->isFinal(), 'plan final');
cut02aHardStopAssert((new ReflectionClass(R0ShadowSafeReturnPlan::class))->isReadOnly(), 'plan readonly');

echo "Cut02AHardStopSafeReturnTest PASS\n";
