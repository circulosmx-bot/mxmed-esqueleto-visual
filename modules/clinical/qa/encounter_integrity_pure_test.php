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
check(!clinical_encounter_transition_allowed('OPEN', 'closed'), 'noncanonical uppercase lifecycle state rejected');
check(!clinical_encounter_status_is_canonical(' open'), 'noncanonical spaced lifecycle state rejected');
check(clinical_encounter_attribution_classification(null) === 'UNATTRIBUTED', 'legacy NULL doctor remains unattributed');

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

$duplicate = new PDOException('duplicate', 23000);
check(clinical_section_write_error_code($duplicate) === 'VERSION_CONFLICT', 'concurrent section create maps duplicate to VERSION_CONFLICT');

check(clinical_document_create_operation('LAB_RESULT') === 'CREATE_POST_ENCOUNTER_RESULT', 'post-close result selects result idempotency operation');
check(clinical_document_create_operation('PRESCRIPTION') === 'CREATE_ENCOUNTER_DOCUMENT', 'ordinary document selects encounter document operation');

$semanticA = clinical_document_semantic_request([
    'document_class' => 'LAB_RESULT',
    'title' => 'Química sanguínea',
    'payload' => ['related_order_document_uuid' => 'order-1'],
    'tmp_name' => '/tmp/php-a',
], str_repeat('a', 64));
$semanticB = clinical_document_semantic_request([
    'document_class' => 'LAB_RESULT',
    'title' => 'Química sanguínea',
    'payload' => ['related_order_document_uuid' => 'order-1'],
    'tmp_name' => '/tmp/php-b',
], str_repeat('a', 64));
check(hash_equals(clinical_idempotency_request_hash($semanticA), clinical_idempotency_request_hash($semanticB)), 'document semantic hash ignores volatile upload temp path');
check(($semanticA['content_sha256'] ?? null) === str_repeat('a', 64), 'document semantic hash includes stable content digest');

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

$finalized = clinical_finalize_result_normalize([
    'encounter_id' => 7,
    'status' => 'closed',
    'closed_at' => '2026-09-18 12:00:00',
    'closed_by_user_id' => 'doctor-user',
    'auto_note_uuid_final' => 'doc-final-uuid',
], ['document_id' => 91, 'document_uuid' => 'doc-final-uuid']);
check($finalized['auto_note_uuid_final'] === 'doc-final-uuid' && $finalized['final_document_id'] === 91, 'finalize result preserves compatibility UUID mirror and relation');

echo "PURE_TESTS_PASSED={$passed}\n";
