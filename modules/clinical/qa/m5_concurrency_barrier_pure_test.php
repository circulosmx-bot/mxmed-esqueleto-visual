<?php
declare(strict_types=1);

require_once __DIR__ . '/m5_concurrency_barrier.php';

$passed = 0;
function m5_check(bool $condition, string $name): void
{
    global $passed;
    if (!$condition) throw new RuntimeException('FAIL: ' . $name);
    $passed++;
    echo "PASS: {$name}\n";
}

$validEnvironment = [
    'MXMED_CLINICAL_M5_QA_MODE' => '1',
    'MXMED_CLINICAL_M5_QA_ENVIRONMENT_ID' => 'm5-disposable-pure-test',
    'MXMED_BUILD' => 'dev',
];
m5_check(!clinical_m5_qa_mode_requested_from([]), 'M5 QA barrier defaults off');
m5_check(!clinical_m5_qa_barrier_enabled_from([
    'MXMED_CLINICAL_M5_QA_ENVIRONMENT_ID' => 'm5-disposable-pure-test',
    'MXMED_BUILD' => 'dev',
], ['HTTP_HOST' => '127.0.0.1']), 'explicit QA enable is required');
m5_check(clinical_m5_qa_barrier_enabled_from($validEnvironment, ['SERVER_ADDR' => '127.0.0.1']), 'local disposable QA environment is allowed');
m5_check(!clinical_m5_qa_environment_allowed_from(array_replace($validEnvironment, ['MXMED_BUILD' => 'prod']), ['SERVER_ADDR' => '127.0.0.1']), 'production build is denied even on local host');
m5_check(!clinical_m5_qa_environment_allowed_from([
    'MXMED_CLINICAL_M5_QA_MODE' => '1',
    'MXMED_CLINICAL_M5_QA_ENVIRONMENT_ID' => 'working-mxmed',
    'MXMED_BUILD' => 'dev',
], ['SERVER_ADDR' => '127.0.0.1']), 'non-disposable environment identity is denied');
m5_check(clinical_m5_qa_barrier_points() === [
    'T04_CONCURRENT_START_BEFORE_INSERT',
    'T11_CONCURRENT_FINALIZE_BEFORE_TERMINAL_LOCK_OR_COMMIT',
    'T26_FINALIZE_VOID_RACE_BEFORE_TERMINAL_LOCK',
], 'only accepted M5 barrier points are exposed');

$previousMode = getenv('MXMED_CLINICAL_M5_QA_MODE');
putenv('MXMED_CLINICAL_M5_QA_MODE');
$before = glob(sys_get_temp_dir() . '/mxmed-m5-mode-off-*') ?: [];
m5_check(clinical_m5_qa_barrier_reach(clinical_m5_qa_barrier_points()) === false, 'mode off has zero barrier effect');
$after = glob(sys_get_temp_dir() . '/mxmed-m5-mode-off-*') ?: [];
m5_check($before === $after, 'mode off creates no barrier filesystem state');
if ($previousMode === false) putenv('MXMED_CLINICAL_M5_QA_MODE');
else putenv('MXMED_CLINICAL_M5_QA_MODE=' . $previousMode);

echo "M5_PREP01_PURE_TESTS_PASSED={$passed}\n";
