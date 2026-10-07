<?php
declare(strict_types=1);
require __DIR__ . '/../../../api/_lib/clinical_consent_signature_binding.php';

function check_binding(string $name, bool $ok): void {
    if (!$ok) throw new RuntimeException($name . '=FAIL');
    echo $name . "=PASS\n";
}
function signature_png(bool $draw, bool $tiny = false): string {
    $image = imagecreatetruecolor(220, 90);
    $white = imagecolorallocate($image, 255, 255, 255);
    $dark = imagecolorallocate($image, 15, 23, 42);
    imagefill($image, 0, 0, $white);
    if ($draw) imageline($image, 20, 25, 170, 65, $dark);
    if ($tiny) imagesetpixel($image, 20, 25, $dark);
    ob_start(); imagepng($image); $bytes = ob_get_clean();
    return 'data:image/png;base64,' . base64_encode($bytes);
}
$vectors = json_decode(file_get_contents(__DIR__ . '/consent_signature_binding_vectors.json'), true, 512, JSON_THROW_ON_ERROR);
$body = $vectors[0]['body'];
$patientAuthority = clinical_consent_binding_patient_authority($body['payload']);
$doctorAuthority = '1|u-med-1';
$image = signature_png(true);
$blank = signature_png(false);
check_binding('BLANK_ARTIFACT_REJECTED', clinical_consent_binding_artifact_digest($blank) === '');
check_binding('TINY_POINT_REJECTED', clinical_consent_binding_artifact_digest(signature_png(false, true)) === '');
check_binding('REAL_ARTIFACT_ACCEPTED', clinical_consent_binding_artifact_digest($image) !== '');
$fingerprint = clinical_consent_binding_fingerprint($body);
$make = static fn(string $role, string $source, string $authority): array => [
    'source' => $source, 'image_data' => $image,
    'role' => $role === 'patient' ? 'patient_or_representative' : 'doctor',
    'binding' => ['version' => 1, 'content_fingerprint' => $fingerprint,
        'artifact_digest' => clinical_consent_binding_artifact_digest($image),
        'source' => $source, 'role' => $role, 'authority' => $authority,
        'applied_at' => '2026-10-07 11:32:00'],
];
$body['payload']['signatures'] = [
    'patient' => $make('patient', 'local_canvas', $patientAuthority),
    'doctor' => $make('doctor', 'local_canvas', $doctorAuthority),
];
check_binding('PATIENT_BOUND', clinical_consent_binding_classify($body, 'patient', $patientAuthority) === 'valid_bound_signature');
check_binding('DOCTOR_BOUND', clinical_consent_binding_classify($body, 'doctor', $doctorAuthority) === 'valid_bound_signature');
foreach ([
    'CONTENT_CHANGE' => ['form_snapshot', 'procedimiento', 'Nueva cirugía'],
    'SIGNER_CHANGE' => ['firmante', 'nombre', 'Otra persona'],
    'WITNESS_CHANGE' => ['testigos', 0, ['nombre' => 'Otro testigo']],
    'ATTACHMENT_CHANGE' => ['signer_identity_attachment_manifest', 0, ['document_uuid' => '33333333-3333-4333-8333-333333333333']],
] as $name => [$section, $key, $value]) {
    $changed = $body;
    $changed['payload'][$section][$key] = $value;
    check_binding($name . '_STALE', clinical_consent_binding_classify($changed, 'patient', $patientAuthority) === 'stale_or_unverified'
        && clinical_consent_binding_classify($changed, 'doctor', $doctorAuthority) === 'stale_or_unverified');
}
check_binding('PHYSICIAN_CHANGE_STALE', clinical_consent_binding_classify($body, 'doctor', '2|other-user') === 'stale_or_unverified');
$legacy = $body; unset($legacy['payload']['signatures']['patient']['binding']);
check_binding('LEGACY_UNVERIFIED', clinical_consent_binding_classify($legacy, 'patient', $patientAuthority) === 'legacy_unverified_binding');
$remote = $body; $remote['payload']['signatures']['patient']['source'] = 'remote_qr';
check_binding('REMOTE_LEGACY_UNBOUND', clinical_consent_binding_classify($remote, 'patient', $patientAuthority) === 'legacy_unbound');
$pdo = new PDO('sqlite::memory:');
$pdo->exec('CREATE TABLE physician_signatures (doctor_id TEXT PRIMARY KEY, checksum_sha256 TEXT NOT NULL)');
$registered = $body;
$registered['payload']['signatures']['doctor'] = $make('doctor', 'registered_profile', $doctorAuthority);
check_binding('REGISTERED_NOT_OWNED', clinical_consent_binding_classify($registered, 'doctor', $doctorAuthority, $pdo, '1') === 'stale_or_unverified');
$pdo->prepare('INSERT INTO physician_signatures VALUES (?,?)')->execute(['1', clinical_consent_binding_artifact_digest($image)]);
check_binding('REGISTERED_OWNED', clinical_consent_binding_classify($registered, 'doctor', $doctorAuthority, $pdo, '1') === 'valid_bound_signature');
check_binding('REGISTERED_DIFFERENT_DOCTOR_REJECTED', clinical_consent_binding_classify($registered, 'doctor', $doctorAuthority, $pdo, '2') === 'stale_or_unverified');
