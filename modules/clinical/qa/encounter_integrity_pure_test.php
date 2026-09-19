<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../api/_lib/clinical_encounter_integrity.php';

$passed = 0;

function check(bool $condition, string $name): void
{
    global $passed;
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $name);
    }
    $passed++;
    echo "PASS: {$name}\n";
}

putenv('MXMED_CLINICAL_ENCOUNTER_INTEGRITY_V1');
check(clinical_encounter_integrity_v1_enabled() === false, 'feature gate defaults OFF');

check(clinical_encounter_transition_allowed('open', 'closed'), 'OPEN -> CLOSED allowed');
check(clinical_encounter_transition_allowed('open', 'voided'), 'OPEN -> VOIDED allowed');
check(!clinical_encounter_transition_allowed('closed', 'open'), 'CLOSED -> OPEN denied');

check(clinical_encounter_section_type_validate('assessment') === 'assessment', 'known section accepted');
try {
    clinical_encounter_section_payload_validate('assessment', 2, []);
    check(false, 'unknown payload schema rejected');
} catch (ClinicalSectionValidationException $e) {
    check($e->getMessage() === 'PAYLOAD_SCHEMA_VERSION_UNSUPPORTED', 'unknown payload schema rejected');
}

$physical = clinical_encounter_section_payload_validate('physical_exam', 1, [
    'systems' => ['cardiovascular' => ['state' => 'NORMAL']],
]);
check(clinical_physical_exam_state($physical, 'respiratory') === 'NOT_REVIEWED', 'absent physical system is NOT_REVIEWED');

$bp = clinical_observation_validate([
    'code' => 'blood_pressure',
    'unit' => 'mmHg',
    'systolic_mm_hg' => 120,
    'diastolic_mm_hg' => 80,
    'source' => 'direct_measurement',
]);
check($bp['systolic_mm_hg'] === 120 && $bp['diastolic_mm_hg'] === 80, 'blood pressure is structured');

$hashA = clinical_idempotency_request_hash(['b' => 2, 'a' => ['y' => 4, 'x' => 3]]);
$hashB = clinical_idempotency_request_hash(['a' => ['x' => 3, 'y' => 4], 'b' => 2]);
$hashC = clinical_idempotency_request_hash(['a' => ['x' => 3, 'y' => 5], 'b' => 2]);
check(hash_equals($hashA, $hashB), 'semantic equality produces same request hash');
check(!hash_equals($hashA, $hashC), 'semantic change produces different request hash');

$ordinaryClosed = clinical_document_operation_policy('CREATE_ENCOUNTER_DOCUMENT', 'PRESCRIPTION', 'closed');
check($ordinaryClosed['allowed'] === false && $ordinaryClosed['code'] === 'ENCOUNTER_TERMINAL', 'ordinary document denied after close');

$resultClosed = clinical_document_operation_policy('CREATE_LAB_RESULT', 'LAB_RESULT', 'closed', [
    'same_patient' => true,
    'valid_originating_order' => true,
    'effective_at' => '2026-09-18 10:00:00',
    'provenance' => 'laboratory-import',
]);
check($resultClosed['allowed'] === true, 'qualified lab result allowed after close');

$voided = clinical_document_operation_policy('CREATE_LAB_RESULT', 'LAB_RESULT', 'voided', [
    'same_patient' => true,
    'valid_originating_order' => true,
    'effective_at' => '2026-09-18 10:00:00',
    'provenance' => 'laboratory-import',
]);
check($voided['allowed'] === false && $voided['code'] === 'ENCOUNTER_VOIDED', 'ordinary document denied for VOIDED');

check(!clinical_document_content_rewrite_allowed('signed'), 'signed document rewrite blocked');
check(!clinical_document_content_rewrite_allowed('generated', true), 'final encounter note rewrite blocked');

echo "PURE_TESTS_PASSED={$passed}\n";
