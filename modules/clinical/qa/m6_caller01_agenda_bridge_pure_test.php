<?php
declare(strict_types=1);

require_once __DIR__ . '/../../agenda/services/ClinicalEncounterBridge.php';

use Agenda\Services\ClinicalEncounterBridge;

$passed = 0;

function m6_caller01_bridge_check(bool $condition, string $name): void
{
    global $passed;
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $name);
    }
    $passed++;
    echo "PASS: {$name}\n";
}

function m6_caller01_bridge_env(string $mode, string $pairs, string $emergencyOff = '0'): void
{
    putenv('AGENDA_ENABLE_CLINICAL_ENCOUNTER_BRIDGE=1');
    putenv('MXMED_CLINICAL_M6_COHORT_MODE=' . $mode);
    putenv('MXMED_CLINICAL_M6_COHORT_PAIRS=' . $pairs);
    putenv('MXMED_CLINICAL_M6_EMERGENCY_OFF=' . $emergencyOff);
}

function m6_caller01_bridge_assert_method(ClinicalEncounterBridge $bridge): ReflectionMethod
{
    return new ReflectionMethod($bridge, 'assertM6ClinicalBridgeAllowed');
}

m6_caller01_bridge_env('allowlist', 'doctor_1|p_cohort', '0');
$bridge = new ClinicalEncounterBridge(['clinical_api_base' => 'http://127.0.0.1:1']);
$assertAllowed = m6_caller01_bridge_assert_method($bridge);

try {
    $bridge->syncCompletedAppointment([
        'status' => 'completed',
        'patient_id' => 'p_cohort',
        'appointment_id' => 'apt_1',
    ]);
    m6_caller01_bridge_check(false, 'cohort bridge is blocked before HTTP');
} catch (RuntimeException $e) {
    m6_caller01_bridge_check($e->getMessage() === 'M6_AGENDA_CLINICAL_BRIDGE_BLOCKED', 'cohort bridge has stable block error');
}

m6_caller01_bridge_env('allowlist', 'doctor_1|p_cohort', '1');
try {
    $assertAllowed->invoke($bridge, 'p_cohort');
    m6_caller01_bridge_check(false, 'emergency OFF cannot enable cohort bridge');
} catch (ReflectionException $e) {
    throw $e;
} catch (RuntimeException $e) {
    m6_caller01_bridge_check($e->getMessage() === 'M6_AGENDA_CLINICAL_BRIDGE_BLOCKED', 'bridge block persists during emergency OFF');
}

m6_caller01_bridge_env('allowlist', 'doctor_1||p_cohort', '1');
try {
    $assertAllowed->invoke($bridge, 'p_cohort');
    m6_caller01_bridge_check(false, 'malformed cohort config cannot enable bridge');
} catch (ReflectionException $e) {
    throw $e;
} catch (RuntimeException $e) {
    m6_caller01_bridge_check($e->getMessage() === 'M6_AGENDA_CLINICAL_BRIDGE_BLOCKED', 'malformed config fails closed with stable bridge error');
}

m6_caller01_bridge_env('allowlist', 'doctor_1|p_cohort', '0');
$assertAllowed->invoke($bridge, 'p_noncohort');
m6_caller01_bridge_check(true, 'non-cohort patient remains bridge-compatible');

m6_caller01_bridge_env('off', '', '0');
$assertAllowed->invoke($bridge, 'p_any');
m6_caller01_bridge_check(true, 'default OFF preserves current bridge behavior');

$windowRoot = sys_get_temp_dir() . '/mxmed-agenda-window-' . bin2hex(random_bytes(5));
mkdir($windowRoot, 0700, true);
$windowPath = $windowRoot . '/state.json';
clinical_m6_write_window_initialize_file($windowPath);
putenv('MXMED_CLINICAL_WRITE_WINDOW_CONTROL=FILE');
putenv('MXMED_CLINICAL_WRITE_WINDOW_STATE_PATH=' . $windowPath);
clinical_m6_write_window_set_state('BLOCK_WRITES');
try {
    $assertAllowed->invoke($bridge, 'p_any');
    m6_caller01_bridge_check(false, 'write window pauses Agenda clinical bridge');
} catch (ReflectionException $e) {
    throw $e;
} catch (RuntimeException $e) {
    m6_caller01_bridge_check($e->getMessage() === 'M6_AGENDA_CLINICAL_BRIDGE_PAUSED', 'Agenda bridge uses shared write-window authority');
}
unlink($windowPath);
rmdir($windowPath . '.leases');
rmdir($windowRoot);

putenv('AGENDA_ENABLE_CLINICAL_ENCOUNTER_BRIDGE');
putenv('MXMED_CLINICAL_M6_COHORT_MODE');
putenv('MXMED_CLINICAL_M6_COHORT_PAIRS');
putenv('MXMED_CLINICAL_M6_EMERGENCY_OFF');
putenv('MXMED_CLINICAL_WRITE_WINDOW_CONTROL');
putenv('MXMED_CLINICAL_WRITE_WINDOW_STATE_PATH');

echo "M6_CALLER01_AGENDA_BRIDGE_PURE_TESTS_PASSED={$passed}\n";
