<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../api/_lib/clinical_consent_signature_binding.php';
$db = (string)($argv[1] ?? '');
if (preg_match('/^flow_r1_qa_[0-9a-f]{12}$/', $db) !== 1) throw new RuntimeException('Disposable QA database required');
$pdo = new PDO('mysql:host=localhost;dbname=' . $db, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$row = $pdo->query("SELECT document_uuid,patient_id,created_by_user_id,payload_json FROM clinical_documents
    WHERE document_type='consentimiento_informado' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!is_array($row)) throw new RuntimeException('Consent draft missing');
$body = [
    'payload' => json_decode((string)$row['payload_json'], true, 512, JSON_THROW_ON_ERROR),
    'actor' => ['user_id' => $row['created_by_user_id']],
    'context' => ['patient_id' => $row['patient_id']],
    '_consent_document_uuid' => $row['document_uuid'],
];
$check = static function (string $name, bool $pass): void {
    if (!$pass) throw new RuntimeException($name . '=FAIL');
    echo $name . "=PASS\n";
};
$patientAuthority = clinical_consent_binding_patient_authority($body['payload']);
$doctorAuthority = '1|review-user';
$classify = static fn(array $candidate, string $role, string $authority, string $doctorId): string =>
    clinical_consent_binding_classify($candidate, $role, $authority, $pdo, $doctorId);
$check('REMOTE_PATIENT_PROVENANCE_VALID', $classify($body, 'patient', $patientAuthority, '1') === 'valid_bound_signature');
$check('REMOTE_DOCTOR_PROVENANCE_VALID', $classify($body, 'doctor', $doctorAuthority, '1') === 'valid_bound_signature');
$changed = $body;
$changed['payload']['signatures']['patient']['binding']['token_id'] = 999999;
$check('REMOTE_TOKEN_ID_TAMPER_REJECTED', $classify($changed, 'patient', $patientAuthority, '1') === 'stale_or_unverified');
$changed = $body;
$changed['payload']['signatures']['patient']['binding']['consent_uuid'] = '00000000-0000-4000-8000-000000000000';
$check('REMOTE_CONSENT_UUID_TAMPER_REJECTED', $classify($changed, 'patient', $patientAuthority, '1') === 'stale_or_unverified');
$changed = $body;
$changed['payload']['signatures']['patient']['image_data'] = 'data:image/png;base64,AA==';
$check('REMOTE_ARTIFACT_TAMPER_REJECTED', $classify($changed, 'patient', $patientAuthority, '1') === 'stale_or_unverified');
$check('REMOTE_WRONG_DOCTOR_AUTHORITY_REJECTED', $classify($body, 'doctor', '2|other-user', '2') === 'stale_or_unverified');
$changed = $body;
$changed['_consent_document_uuid'] = '00000000-0000-4000-8000-000000000000';
$check('REMOTE_SECOND_DOCUMENT_REPLAY_REJECTED', $classify($changed, 'patient', $patientAuthority, '1') === 'stale_or_unverified');
