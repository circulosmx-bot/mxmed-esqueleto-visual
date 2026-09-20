<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../api/_lib/clinical_m6_cutover.php';

$passed = 0;

function route01_check(bool $condition, string $name): void
{
    global $passed;
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $name);
    }
    $passed++;
    echo "PASS: {$name}\n";
}

function route01_env(?string $mode = null, ?string $pairs = null, ?string $emergencyOff = null): void
{
    foreach ([
        'MXMED_CLINICAL_M6_COHORT_MODE' => $mode,
        'MXMED_CLINICAL_M6_COHORT_PAIRS' => $pairs,
        'MXMED_CLINICAL_M6_EMERGENCY_OFF' => $emergencyOff,
    ] as $name => $value) {
        putenv($value === null ? $name : $name . '=' . $value);
    }
}

route01_env();
route01_check(!clinical_m6_v1_route_enabled_for_pair(false, 'doctor_1', 'p_abc123'), 'master OFF mode OFF is legacy');
route01_check(clinical_m6_v1_route_enabled_for_pair(true, 'doctor_1', 'p_abc123'), 'master ON mode OFF preserves global V1');

route01_env('allowlist', 'doctor_1|p_abc123');
route01_check(!clinical_m6_v1_route_enabled_for_pair(false, 'doctor_1', 'p_abc123'), 'master OFF exact allowlist pair cannot enable V1');
route01_check(clinical_m6_v1_route_enabled_for_pair(true, 'doctor_1', 'p_abc123'), 'master ON exact allowlist pair selects V1');
route01_check(!clinical_m6_v1_route_enabled_for_pair(true, 'doctor_1', 'p_other'), 'master ON non-pair remains legacy');
route01_check(!clinical_m6_v1_route_enabled_for_pair(true, 'doctor_2', 'p_abc123'), 'same patient under another doctor remains legacy');
route01_check(!clinical_m6_v1_route_enabled_for_pair(true, '', 'p_abc123'), 'legacy null doctor cannot select exact pair');

route01_env('allowlist', 'doctor_1|p_abc123', 'on');
route01_check(!clinical_m6_v1_route_enabled_for_pair(true, 'doctor_1', 'p_abc123'), 'emergency OFF disables selected pair');

route01_env('allowlist', 'invalid-pair');
route01_check(!clinical_m6_v1_route_enabled_for_pair(false, 'doctor_1', 'p_abc123'), 'master OFF short-circuits malformed allowlist');
try {
    clinical_m6_v1_route_enabled_for_pair(true, 'doctor_1', 'p_abc123');
    route01_check(false, 'master ON malformed allowlist fails closed');
} catch (ClinicalM6CohortConfigException $e) {
    route01_check($e->getMessage() === 'M6_COHORT_CONFIG_INVALID', 'master ON malformed allowlist has stable failure');
}

route01_env();
echo "M6_ROUTE01_SELECTOR_PURE_TESTS_PASSED={$passed}\n";
