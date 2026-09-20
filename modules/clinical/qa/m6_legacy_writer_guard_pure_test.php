<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../api/_lib/clinical_m6_cutover.php';

$passed = 0;

function m6_guard_check(bool $condition, string $name): void
{
    global $passed;
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $name);
    }
    $passed++;
    echo "PASS: {$name}\n";
}

function m6_guard_env(?string $mode = null, ?string $pairs = null, ?string $emergencyOff = null): void
{
    foreach ([
        'MXMED_CLINICAL_M6_COHORT_MODE' => $mode,
        'MXMED_CLINICAL_M6_COHORT_PAIRS' => $pairs,
        'MXMED_CLINICAL_M6_EMERGENCY_OFF' => $emergencyOff,
    ] as $name => $value) {
        putenv($value === null ? $name : $name . '=' . $value);
    }
}

m6_guard_env();
clinical_m6_assert_legacy_write_allowed('p_abc123');
m6_guard_check(true, 'cohort OFF allows legacy write');

m6_guard_env('allowlist', 'doctor_1|p_abc123', '0');
try {
    clinical_m6_assert_legacy_write_allowed('p_abc123');
    m6_guard_check(false, 'configured cohort patient is blocked');
} catch (ClinicalM6LegacyWriteBlockedException $e) {
    m6_guard_check($e->getMessage() === 'M6_LEGACY_WRITE_BLOCKED', 'cohort patient has stable block error');
    m6_guard_check($e->httpStatus() === 409, 'cohort patient block uses HTTP 409 contract');
}

clinical_m6_assert_legacy_write_allowed('p_noncohort');
m6_guard_check(true, 'non-cohort patient keeps legacy behavior');

m6_guard_env('allowlist', 'doctor_1|p_abc123', '1');
try {
    clinical_m6_assert_legacy_write_allowed('p_abc123');
    m6_guard_check(false, 'emergency OFF cannot re-enable cohort legacy write');
} catch (ClinicalM6LegacyWriteBlockedException $e) {
    m6_guard_check($e->getMessage() === 'M6_LEGACY_WRITE_BLOCKED', 'legacy block persists during emergency OFF');
}

m6_guard_env('allowlist', 'doctor_1|p_abc123', 'on');
try {
    clinical_m6_assert_legacy_write_allowed('p_abc123');
    m6_guard_check(false, 'different request doctor cannot bypass patient-level block');
} catch (ClinicalM6LegacyWriteBlockedException $e) {
    m6_guard_check($e->getMessage() === 'M6_LEGACY_WRITE_BLOCKED', 'patient-level block ignores request doctor authority');
}

m6_guard_env('allowlist', 'doctor_1||p_abc123', '1');
try {
    clinical_m6_assert_legacy_write_allowed('p_abc123');
    m6_guard_check(false, 'malformed allowlist cannot permit legacy write');
} catch (ClinicalM6CohortConfigException $e) {
    m6_guard_check($e->getMessage() === 'M6_COHORT_CONFIG_INVALID', 'malformed allowlist fails closed with stable error');
}

m6_guard_env();
echo "M6_LEGACY_WRITER_GUARD_PURE_TESTS_PASSED={$passed}\n";
