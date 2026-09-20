<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../api/_lib/clinical_m6_cutover.php';

$passed = 0;

function m6_check(bool $condition, string $name): void
{
    global $passed;
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $name);
    }
    $passed++;
    echo "PASS: {$name}\n";
}

function m6_env(?string $mode = null, ?string $pairs = null, ?string $emergencyOff = null): void
{
    foreach ([
        'MXMED_CLINICAL_M6_COHORT_MODE' => $mode,
        'MXMED_CLINICAL_M6_COHORT_PAIRS' => $pairs,
        'MXMED_CLINICAL_M6_EMERGENCY_OFF' => $emergencyOff,
    ] as $name => $value) {
        putenv($value === null ? $name : $name . '=' . $value);
    }
}

m6_env();
m6_check(clinical_m6_cohort_mode() === 'off', 'default mode is OFF');
m6_check(clinical_m6_cohort_pairs() === [], 'OFF mode has no active pair');
m6_check(!clinical_m6_cohort_pair_configured('doctor_1', 'p_abc123'), 'OFF mode configures no pair');
m6_check(!clinical_m6_cohort_authorized('doctor_1', 'p_abc123'), 'OFF mode authorizes no pair');
m6_check(!clinical_m6_patient_in_any_cohort('p_abc123'), 'OFF mode reports no patient membership');
m6_check(!clinical_m6_legacy_write_block_required('p_abc123'), 'OFF mode requires no legacy-write block');

m6_env('off', 'doctor_1|p_abc123', '1');
m6_check(!clinical_m6_cohort_pair_configured('doctor_1', 'p_abc123'), 'emergency OFF does not create a pair in OFF mode');
m6_check(!clinical_m6_patient_in_any_cohort('p_abc123'), 'emergency OFF does not create patient membership in OFF mode');
m6_check(!clinical_m6_cohort_authorized('doctor_1', 'p_abc123'), 'emergency OFF keeps routing denied in OFF mode');
m6_check(!clinical_m6_legacy_write_block_required('p_abc123'), 'emergency OFF does not create a legacy-write block in OFF mode');

m6_env('allowlist', " doctor_1|p_abc123\n doctor_2|p_def456 ");
m6_check(clinical_m6_cohort_pair_configured('doctor_1', 'p_abc123'), 'allowlist exact pair is configured');
m6_check(clinical_m6_cohort_authorized('doctor_1', 'p_abc123'), 'allowlist exact pair matches');
m6_check(!clinical_m6_cohort_pair_configured('doctor_2', 'p_abc123'), 'same patient under another doctor is not the configured pair');
m6_check(!clinical_m6_cohort_authorized('doctor_2', 'p_abc123'), 'same patient under another doctor is denied');
m6_check(!clinical_m6_cohort_authorized('doctor_1', 'p_def456'), 'same doctor with another patient is denied');
m6_check(clinical_m6_patient_in_any_cohort('p_abc123'), 'patient membership is derived from any exact pair');
m6_check(clinical_m6_legacy_write_block_required('p_abc123'), 'configured patient requires legacy-write block');
m6_check(!clinical_m6_patient_in_any_cohort('p_unknown'), 'unknown patient has no cohort membership');
m6_check(!clinical_m6_legacy_write_block_required('p_unknown'), 'unknown patient requires no legacy-write block');

m6_env('allowlist', 'doctor_1|p_abc123, doctor_1|p_abc123;doctor_2|p_def456');
$deduplicated = clinical_m6_cohort_pairs();
m6_check(count($deduplicated) === 2, 'duplicate pair is deduplicated');
m6_check($deduplicated[0] === ['doctor_id' => 'doctor_1', 'patient_id' => 'p_abc123'], 'pair parsing is deterministic');

$invalidValues = [
    '',
    'doctor_1',
    'doctor_1|',
    '|p_abc123',
    'doctor_1||p_abc123',
    'doctor_1|p_abc123,,doctor_2|p_def456',
    'doctor 1|p_abc123',
];
foreach ($invalidValues as $invalid) {
    m6_env('allowlist', $invalid);
    try {
        clinical_m6_cohort_authorized('doctor_1', 'p_abc123');
        m6_check(false, 'malformed active allowlist fails closed: ' . var_export($invalid, true));
    } catch (ClinicalM6CohortConfigException $e) {
        m6_check($e->getMessage() === 'M6_COHORT_CONFIG_INVALID', 'malformed active allowlist has stable failure');
    }
}

m6_env('invalid-mode', 'doctor_1|p_abc123');
try {
    clinical_m6_cohort_authorized('doctor_1', 'p_abc123');
    m6_check(false, 'invalid mode fails closed');
} catch (ClinicalM6CohortConfigException $e) {
    m6_check($e->getMessage() === 'M6_COHORT_CONFIG_INVALID', 'invalid mode has stable failure');
}

m6_env('allowlist', 'doctor_1||p_abc123', '1');
m6_check(!clinical_m6_cohort_authorized('doctor_1', 'p_abc123'), 'emergency OFF overrides malformed allowlist');
try {
    clinical_m6_legacy_write_block_required('p_abc123');
    m6_check(false, 'malformed allowlist cannot unblock legacy writes during emergency OFF');
} catch (ClinicalM6CohortConfigException $e) {
    m6_check($e->getMessage() === 'M6_COHORT_CONFIG_INVALID', 'malformed allowlist still fails closed for legacy blocking during emergency OFF');
}

m6_env('allowlist', 'doctor_1|p_abc123', 'on');
m6_check(clinical_m6_cohort_pair_configured('doctor_1', 'p_abc123'), 'emergency OFF preserves configured pair');
m6_check(!clinical_m6_cohort_authorized('doctor_1', 'p_abc123'), 'emergency OFF overrides valid allowlist');
m6_check(clinical_m6_patient_in_any_cohort('p_abc123'), 'emergency OFF preserves patient membership');
m6_check(clinical_m6_legacy_write_block_required('p_abc123'), 'emergency OFF preserves legacy-write block');
m6_check(!clinical_m6_cohort_pair_configured('doctor_2', 'p_abc123'), 'emergency OFF does not broaden configured pair');
m6_check(!clinical_m6_cohort_authorized('doctor_2', 'p_abc123'), 'emergency OFF denies another doctor pair');

m6_env('allowlist', 'doctor_1|p_abc123', 'invalid');
try {
    clinical_m6_cohort_authorized('doctor_1', 'p_abc123');
    m6_check(false, 'invalid emergency setting fails closed');
} catch (ClinicalM6CohortConfigException $e) {
    m6_check($e->getMessage() === 'M6_COHORT_CONFIG_INVALID', 'invalid emergency setting has stable failure');
}

m6_env();
$_SERVER['HTTP_X_MXMED_CLINICAL_M6_COHORT'] = 'allowlist';
$_GET['m6_cohort'] = 'doctor_1|p_abc123';
$_POST['m6_cohort'] = 'doctor_1|p_abc123';
$_COOKIE['m6_cohort'] = 'doctor_1|p_abc123';
m6_check(!clinical_m6_cohort_authorized('doctor_1', 'p_abc123'), 'client input cannot define cohort');
m6_check(!clinical_m6_legacy_write_block_required('p_abc123'), 'client input cannot require legacy-write blocking');

m6_env();
echo "M6_COHORT_PURE_TESTS_PASSED={$passed}\n";
