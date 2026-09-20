<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../api/_lib/clinical_encounter_integrity.php';
require_once __DIR__ . '/../../../api/_lib/clinical_documents.php';

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

putenv('MXMED_CLINICAL_ENCOUNTER_INTEGRITY_V1=1');
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/api/clinical/__qa_mapping_probe__';
$_SERVER['SCRIPT_NAME'] = '/api/clinical/index.php';
ob_start();
require_once __DIR__ . '/../../../api/clinical/index.php';
ob_end_clean();

$documentContextMismatch = new RuntimeException('DOCUMENT_CONTEXT_MISMATCH');
check(clinical_v1_error_status($documentContextMismatch) === 409, 'document context mismatch maps to HTTP 409');
check(clinical_v1_error_code($documentContextMismatch) === 'DOCUMENT_CONTEXT_MISMATCH', 'document context mismatch preserves canonical error code');
$unknownV1Error = new RuntimeException('unexpected internal failure');
check(clinical_v1_error_status($unknownV1Error) === 500, 'unknown V1 exception maps to HTTP 500');
check(clinical_v1_error_code($unknownV1Error) === 'server_error', 'unknown V1 exception maps to server_error');

putenv('MXMED_CLINICAL_ENCOUNTER_INTEGRITY_V1');
check(clinical_encounter_integrity_v1_enabled() === false, 'feature gate defaults OFF');

check(clinical_encounter_transition_allowed('open', 'closed'), 'OPEN -> CLOSED allowed');
check(clinical_encounter_transition_allowed('open', 'voided'), 'OPEN -> VOIDED allowed');
check(!clinical_encounter_transition_allowed('closed', 'open'), 'CLOSED -> OPEN denied');
check(!clinical_encounter_transition_allowed('OPEN', 'closed'), 'noncanonical uppercase lifecycle state rejected');
check(!clinical_encounter_status_is_canonical(' open'), 'noncanonical spaced lifecycle state rejected');
check(clinical_encounter_attribution_classification(null) === 'UNATTRIBUTED', 'legacy NULL doctor remains unattributed');
check(clinical_terminal_audit_change_code(
    ['status'=>'closed','closed_at'=>'2026-09-18 12:00:00','closed_by_user_id'=>'u1','auto_note_uuid_final'=>'d1'],
    ['status'=>'closed','closed_at'=>'2026-09-18 12:00:01','closed_by_user_id'=>'u1','auto_note_uuid_final'=>'d1']
) === 'FIRST_CLOSE_IMMUTABLE', 'first close audit values are immutable');
check(clinical_terminal_audit_change_code(
    ['status'=>'closed','closed_at'=>'2026-09-18 12:00:00','closed_by_user_id'=>'u1','auto_note_uuid_final'=>'d1'],
    ['status'=>'closed','closed_at'=>'2026-09-18 12:00:00','closed_by_user_id'=>'u1','auto_note_uuid_final'=>'d2']
) === 'FIRST_CLOSE_IMMUTABLE', 'final auto note identity is immutable after close');
check(clinical_terminal_audit_change_code(
    ['status'=>'voided','voided_at'=>'2026-09-18 12:00:00','voided_by_user_id'=>'u1','void_reason'=>'duplicate'],
    ['status'=>'voided','voided_at'=>'2026-09-18 12:00:00','voided_by_user_id'=>'u1','void_reason'=>'changed']
) === 'FIRST_VOID_IMMUTABLE', 'first void audit values are immutable');

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
check(clinical_canonical_document_class('lab_order') === 'ORDER', 'lab_order maps to canonical ORDER');
check(clinical_canonical_document_class('imaging_order') === 'ORDER', 'imaging_order maps to canonical ORDER');
check(clinical_canonical_document_class('lab_pdf') === 'LAB_RESULT', 'lab_pdf maps to canonical LAB_RESULT');
check(clinical_canonical_document_class('image') === 'ENCOUNTER_DOCUMENT', 'generic image is ordinary document');
check(clinical_canonical_document_class('pdf') === 'ENCOUNTER_DOCUMENT', 'generic pdf is ordinary document');
check(clinical_document_type_is_order('orders'), 'canonical order aliases are recognized');
try {
    clinical_assert_document_class(['document_type'=>'prescription','document_class'=>'LAB_RESULT']);
    check(false, 'spoofed client document class rejected');
} catch (InvalidArgumentException $e) {
    check($e->getMessage()==='DOCUMENT_CLASS_MISMATCH', 'spoofed client document class rejected');
}
check(clinical_assert_document_class(['document_type'=>'lab_result','document_class'=>'LAB_RESULT'])==='LAB_RESULT', 'matching class assertion accepted');
check(clinical_v1_multipart_document_write_allowed(true,true)===false, 'V1 multipart write fails closed');
check(clinical_v1_multipart_document_write_allowed(false,false)===true, 'V1 JSON document write remains eligible');
$initialState=mxmed_clinical_document_initial_state();
check($initialState['status']==='generated'&&$initialState['signed_at']===null, 'canonical document initial state is generated and unsigned');

$semanticA = clinical_document_semantic_request([
    'document_type' => 'lab_result',
    'document_class' => 'LAB_RESULT',
    'title' => 'Química sanguínea',
    'payload' => ['related_order_document_uuid' => 'order-1'],
    'tmp_name' => '/tmp/php-a',
], str_repeat('a', 64));
$semanticB = clinical_document_semantic_request([
    'document_type' => 'lab_result',
    'title' => 'Química sanguínea',
    'payload' => ['related_order_document_uuid' => 'order-1'],
    'tmp_name' => '/tmp/php-b',
], str_repeat('a', 64));
check(hash_equals(clinical_idempotency_request_hash($semanticA), clinical_idempotency_request_hash($semanticB)), 'document semantic hash ignores volatile upload temp path');
check(($semanticA['content_sha256'] ?? null) === str_repeat('a', 64), 'document semantic hash includes stable content digest');

check(clinical_document_amendment_reason_validate('  corrige dosis  ') === 'corrige dosis', 'document amendment reason is trimmed and required');
try {
    clinical_document_amendment_reason_validate('   ');
    check(false, 'empty document amendment reason rejected');
} catch (InvalidArgumentException $e) {
    check($e->getMessage() === 'AMENDMENT_REASON_REQUIRED', 'empty document amendment reason rejected');
}
$lineageDocument = ['patient_id' => 'patient-1', 'encounter_ref_id' => 17];
check(clinical_document_lineage_context_matches($lineageDocument, ['patient_id' => 'patient-1', 'encounter_ref_id' => '17']), 'document lineage accepts identical patient and encounter context');
check(!clinical_document_lineage_context_matches($lineageDocument, ['patient_id' => 'patient-2', 'encounter_ref_id' => 17]), 'document lineage rejects cross-patient context');
check(!clinical_document_lineage_context_matches($lineageDocument, ['patient_id' => 'patient-1', 'encounter_ref_id' => 18]), 'document lineage rejects cross-encounter context');

$amendmentOriginal = ['id' => 41, 'document_uuid' => 'doc-41', 'patient_id' => 'patient-1', 'encounter_ref_id' => 17];
$amendmentReplacement = [
    'document_type' => 'prescription',
    'title' => 'Receta corregida',
    'summary' => 'Corrección de dosis',
    'event_datetime' => '2026-09-19 12:00:00',
    'payload' => ['items' => [['medicine' => 'A', 'dose' => '10 mg']]],
    'provenance' => 'physician-correction',
];
$amendmentSemanticA = clinical_document_amendment_semantic_request('doctor-1', $amendmentOriginal, $amendmentReplacement, 'Corrige dosis');
$amendmentSemanticB = clinical_document_amendment_semantic_request('doctor-1', $amendmentOriginal, $amendmentReplacement, 'Corrige dosis');
$amendmentChanged = $amendmentReplacement;
$amendmentChanged['payload']['items'][0]['dose'] = '20 mg';
check(hash_equals(clinical_idempotency_request_hash($amendmentSemanticA), clinical_idempotency_request_hash($amendmentSemanticB)), 'same document amendment semantics produce replay hash');
check(!hash_equals(clinical_idempotency_request_hash($amendmentSemanticA), clinical_idempotency_request_hash(
    clinical_document_amendment_semantic_request('doctor-1', $amendmentOriginal, $amendmentChanged, 'Corrige dosis')
)), 'material document amendment content changes request hash');
check(($amendmentSemanticA['operation'] ?? null) === 'CREATE_DOCUMENT_AMENDMENT_OR_REPLACEMENT', 'document amendment binds canonical idempotency operation');

$amendOpen = clinical_document_operation_policy('AMEND_DOCUMENT', 'PRESCRIPTION', 'open');
$amendClosed = clinical_document_operation_policy('AMEND_DOCUMENT', 'PRESCRIPTION', 'closed');
$amendVoided = clinical_document_operation_policy('AMEND_DOCUMENT', 'PRESCRIPTION', 'voided');
check($amendOpen['allowed'] === true, 'document amendment is supported for OPEN encounter');
check($amendClosed['allowed'] === true, 'document amendment is supported for CLOSED encounter');
check($amendVoided['allowed'] === false && $amendVoided['code'] === 'ENCOUNTER_VOIDED', 'document amendment is denied for VOIDED encounter');

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
