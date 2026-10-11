<?php
declare(strict_types=1);
/** Disposable QA only: exercise local and registered physician signatures for both V2 subtypes. */
require_once dirname(__DIR__, 3) . '/api/_lib/db.php';
$database = (string)(mxmed_load_db_config()['mysql']['dbname'] ?? '');
if (!preg_match('/^mxmed_cert_[a-z0-9_]*_qa_[0-9]{8}$/D', $database)) {
    fwrite(STDERR, "Refusing non-disposable database\n"); exit(2);
}
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['PATH_INFO'] = '/__cert_ext02a';
ob_start(); require dirname(__DIR__, 3) . '/api/clinical/index.php'; ob_end_clean();
require_once dirname(__DIR__, 3) . '/api/_lib/clinical_certificado_write.php';
$pdo = mxmed_pdo();
$doctor = ['doctor_id' => 'cert-qa-doctor', 'user_id' => 'cert-qa-user'];
$patientId = 'cert-qa-patient';
$imageCanvas = imagecreatetruecolor(180, 90);
$white = imagecolorallocate($imageCanvas, 255, 255, 255);
$black = imagecolorallocate($imageCanvas, 0, 0, 0);
imagefilledrectangle($imageCanvas, 0, 0, 179, 89, $white);
imagesetthickness($imageCanvas, 4);
imageline($imageCanvas, 12, 20, 130, 60, $black);
imageline($imageCanvas, 30, 62, 160, 18, $black);
ob_start(); imagepng($imageCanvas); $bytes = ob_get_clean();
$image = 'data:image/png;base64,' . base64_encode($bytes);
$digest = clinical_consent_binding_artifact_digest($image);
if ($digest === '') throw new RuntimeException('Synthetic signature has no validated ink');

function certExtBody(string $type): array {
    $labels = ['aptitud_fisica' => 'Certificado de aptitud física',
        'actividad_deportiva' => 'Certificado para actividad deportiva'];
    $assessment = [
        'activity' => $type === 'aptitud_fisica' ? 'Actividad física habitual QA' : 'Carrera recreativa QA',
        'intensity' => $type === 'actividad_deportiva' ? 'Moderada' : '',
        'participation_purpose' => '', 'current_findings' => 'Valoración sintética estable.',
        'conclusion' => 'apto_para_actividad_indicada',
        'restriction_mode' => 'sin_restricciones_documentadas', 'restriction_detail' => '',
        'general_condition' => '', 'recommendations' => 'Progresar gradualmente.',
        'additional_considerations' => ''
    ];
    $content = [
        'declaration_text' => 'Declaración sintética limitada a la actividad descrita.',
        'care_date' => '', 'rest_days' => '', 'rest_start_date' => '', 'rest_end_date' => '',
        'return_note' => '', 'general_condition' => '', 'general_condition_label' => '',
        'observations_mode' => '', 'observations' => '', 'validity_term' => '7 días',
        'usage_note_type' => '', 'usage_note_custom' => '', 'usage_note' => '',
        'recipient_header' => '', 'validity_note' => '', 'closing_statement' => '',
        'assessment' => $assessment
    ];
    return [
        'type' => 'certificado_medico', 'document_type' => 'certificado_medico',
        'title' => $labels[$type], 'summary' => 'CERT-EXT02A QA sintética',
        'event_datetime' => gmdate('Y-m-d H:i:s'),
        'context' => ['patient_id' => 'cert-qa-patient', 'care_setting' => 'consulta'],
        'actor' => ['user_id' => 'cert-qa-user'],
        'payload' => [
            'contract_version' => 2, 'status' => 'draft',
            'presentation' => ['version' => 1, 'professional_header' => 'shown'],
            'report' => ['emission_date' => gmdate('Y-m-d'), 'issued_at' => gmdate('Y-m-d H:i:s')],
            'patient_snapshot' => ['full_name' => 'Paciente CERT QA', 'age' => '42',
                'sex' => 'Femenino', 'identifier' => 'cert-qa-patient'],
            'actor_snapshot' => ['user_id' => 'cert-qa-user', 'full_name' => 'Dra. CERT QA',
                'license' => 'QA123', 'specialty' => 'QA', 'specialty_license' => '',
                'place' => 'QA', 'institution' => 'QA', 'facility' => 'QA'],
            'branding' => ['logo_url_resolved' => '', 'facility_visible' => 'QA',
                'location_line_visible' => 'QA', 'group_name' => 'QA', 'address_line' => 'QA',
                'consultorio_phone' => 'QA'],
            'certificate' => ['type' => $type, 'type_label' => $labels[$type],
                'purpose' => 'A quien corresponda', 'purpose_category' => 'A quien corresponda',
                'usage_type' => '', 'usage_custom' => ''],
            'content' => $content, 'form_snapshot' => ['type' => $type, 'assessment' => $assessment],
            'signatures' => ['doctor' => null]
        ]
    ];
}

function certExtWrite(PDO $pdo, array $doctor, string $patientId, array $body): array {
    return clinical_certificado_write($pdo, $doctor, $patientId, $body,
        'cert-ext02a:' . bin2hex(random_bytes(12)));
}

foreach (['aptitud_fisica', 'actividad_deportiva'] as $type) {
    foreach (['local_canvas', 'registered_profile'] as $source) {
        $body = certExtBody($type);
        $created = certExtWrite($pdo, $doctor, $patientId, $body);
        if (($created['status'] ?? '') !== 'draft') throw new RuntimeException('Draft failed');
        $body['draft_ref'] = $created['document_uuid'];
        $body['expected_version'] = 1;
        if ($source === 'registered_profile') {
            $pdo->prepare('UPDATE physician_signatures SET checksum_sha256=? WHERE doctor_id=?')
                ->execute([$digest, $doctor['doctor_id']]);
        }
        $body['payload']['signatures']['doctor'] = [
            'type' => 'drawn', 'role' => 'doctor', 'image_data' => $image,
            'signed_at' => gmdate('Y-m-d H:i:s'), 'signer_name' => 'Dra. CERT QA',
            'source' => $source,
            'binding' => ['version' => 2, 'document_type' => 'certificado_medico',
                'document_uuid' => $created['document_uuid'], 'role' => 'doctor',
                'source' => $source, 'authority' => 'cert-qa-doctor|cert-qa-user',
                'artifact_digest' => $digest,
                'content_fingerprint' => clinical_certificado_binding_fingerprint($body),
                'applied_at' => gmdate('c')]
        ];
        if (clinical_certificado_binding_classify($body, $doctor['doctor_id'], $pdo)
            !== 'valid_bound_signature') throw new RuntimeException("$type $source signature invalid");
        $edited = $body;
        $edited['payload']['content']['assessment']['activity'] .= ' cambiada';
        if (clinical_certificado_binding_classify($edited, $doctor['doctor_id'], $pdo)
            !== 'stale_or_unverified_signature') throw new RuntimeException("$type $source edit did not stale");
        $body['payload']['status'] = 'issued';
        $issued = certExtWrite($pdo, $doctor, $patientId, $body);
        if (($issued['status'] ?? '') !== 'generated'
            || $issued['document_uuid'] !== $created['document_uuid'])
            throw new RuntimeException("$type $source emission failed");
        $payload = json_decode((string)$pdo->query('SELECT payload_json FROM clinical_documents WHERE document_uuid='
            . $pdo->quote($created['document_uuid']))->fetchColumn(), true);
        if (($payload['signature_binding_status']['doctor'] ?? '') !== 'valid_bound_signature'
            || !str_contains((string)($payload['certificado_snapshot']['html'] ?? ''), $body['payload']['content']['assessment']['activity']))
            throw new RuntimeException("$type $source snapshot mismatch");
        echo "$type $source draft/signature/stale/emission/snapshot PASS\n";
    }
}
