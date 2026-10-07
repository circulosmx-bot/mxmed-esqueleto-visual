<?php
declare(strict_types=1);

/** CONS-SIGN02A: the same content-only projection as consent-signature-binding.js. */
function clinical_consent_binding_clean(mixed $value): string
{
    $text = str_replace(["\r\n", "\r"], "\n", (string)($value ?? ''));
    return class_exists(Normalizer::class) ? (Normalizer::normalize($text, Normalizer::FORM_C) ?: $text) : $text;
}

function clinical_consent_binding_projection(array $body): array
{
    $p = is_array($body['payload'] ?? null) ? $body['payload'] : [];
    $form = is_array($p['form_snapshot'] ?? null) ? $p['form_snapshot'] : [];
    $patient = is_array($p['patient_snapshot'] ?? null) ? $p['patient_snapshot'] : [];
    $actor = is_array($p['actor_snapshot'] ?? null) ? $p['actor_snapshot'] : [];
    $consent = is_array($p['consent'] ?? null) ? $p['consent'] : [];
    $signer = is_array($p['firmante'] ?? null) ? $p['firmante'] : [];
    $witnesses = is_array($p['testigos'] ?? null) ? $p['testigos'] : [];
    $refs = is_array($p['signer_identity_attachment_manifest'] ?? null)
        ? $p['signer_identity_attachment_manifest'] : [];
    $context = is_array($p['signature_context'] ?? null) ? $p['signature_context'] : ($body['context'] ?? []);
    $c = 'clinical_consent_binding_clean';
    $attachments = [];
    foreach ($refs as $ref) {
        if (!is_array($ref)) continue;
        $uuid = $c($ref['document_uuid'] ?? ($ref['document_id'] ?? ''));
        $attachments[] = $uuid !== '' ? 'uuid:' . $uuid
            : 'sha256:' . strtolower($c($ref['sha256'] ?? ''));
    }
    sort($attachments, SORT_STRING);
    return [
        'attachments' => $attachments,
        'content' => [
            'alternatives' => $c($form['alternativas'] ?? ''),
            'benefits' => $c($form['beneficios_esperados'] ?? ''),
            'consequences' => $c($form['consecuencias_no_aceptar'] ?? ''),
            'contingency' => !empty($form['autorizacion_contingencias']),
            'motive' => $c($form['motivo'] ?? ''),
            'objective' => $c($form['objetivo'] ?? ''),
            'procedure' => $c($form['procedimiento'] ?? ''),
            'rendered_text' => $c($p['rendered_text'] ?? ''),
            'risk_common' => $c($form['risk_comunes'] ?? ''),
            'risk_infrequent' => $c($form['risk_poco_frecuentes'] ?? ''),
            'risk_rare' => $c($form['risk_raros_graves'] ?? ''),
            'risks' => $c($form['riesgos'] ?? ''),
            'title' => $c($consent['document_title'] ?? ''),
        ],
        'context' => [
            'appointment_id' => $c($context['appointment_id'] ?? ''),
            'encounter_key' => $c($context['encounter_key'] ?? ''),
        ],
        'document_date' => $c($p['signature_document_date'] ?? ''),
        'document_type' => 'consentimiento_informado',
        'informed_confirmation' => !empty($form['confirm_informed']),
        'patient' => [
            'age' => $c($patient['age'] ?? ''),
            'id' => $c($context['patient_id'] ?? ''),
            'name' => $c($patient['full_name'] ?? ''),
            'sex' => $c($patient['sexo'] ?? ''),
        ],
        'physician' => [
            'facility' => $c($p['facility_name'] ?? ''),
            'institution' => $c($p['institution_name'] ?? ''),
            'license' => $c($actor['license'] ?? ''),
            'name' => $c($actor['full_name'] ?? ''),
            'place' => $c($p['place'] ?? ''),
            'user_id' => $c($body['actor_user_id'] ?? ($actor['user_id'] ?? '')),
        ],
        'signer' => [
            'name' => $c($signer['nombre'] ?? ''),
            'relationship' => $c($signer['relacion'] ?? ($signer['parentesco'] ?? '')),
            'type' => $c($signer['tipo'] ?? ''),
        ],
        'version' => 1,
        'witnesses' => [
            $c($witnesses[0]['nombre'] ?? ''),
            $c($witnesses[1]['nombre'] ?? ''),
        ],
    ];
}

function clinical_consent_binding_fingerprint(array $body): string
{
    return hash('sha256', json_encode(clinical_consent_binding_projection($body),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
}

function clinical_consent_binding_artifact_digest(string $data): string
{
    if (!preg_match('#^data:image/png;base64,([A-Za-z0-9+/]+={0,2})$#D', $data, $match)) return '';
    $bytes = base64_decode($match[1], true);
    if ($bytes === false || strlen($bytes) < 1 || strlen($bytes) > 2097152) return '';
    try {
        $info = @getimagesizefromstring($bytes);
        if (!$info || $info[2] !== IMAGETYPE_PNG || $info[0] > 4096 || $info[1] > 4096
            || $info[0] * $info[1] > 4000000) return '';
        $image = @imagecreatefromstring($bytes);
        if (!$image) return '';
        $count = 0; $left = $info[0]; $right = -1; $top = $info[1]; $bottom = -1;
        for ($y = 0; $y < $info[1]; $y++) for ($x = 0; $x < $info[0]; $x++) {
            $rgba = imagecolorsforindex($image, imagecolorat($image, $x, $y));
            if ($rgba['alpha'] < 68 && min($rgba['red'], $rgba['green'], $rgba['blue']) < 245) {
                $count++; $left = min($left, $x); $right = max($right, $x);
                $top = min($top, $y); $bottom = max($bottom, $y);
            }
        }
        if ($count < 12 || $right - $left < 5 || $bottom - $top < 2) return '';
        require_once __DIR__ . '/../../modules/signatures/SignatureImage.php';
        \Signatures\SignatureImage::normalize($data);
    } catch (Throwable) { return ''; }
    return hash('sha256', $bytes);
}

function clinical_consent_binding_classify(array $body, string $role, string $authority,
    ?PDO $pdo = null, string $doctorId = ''): string
{
    $p = is_array($body['payload'] ?? null) ? $body['payload'] : [];
    $entry = is_array($p['signatures'][$role] ?? null) ? $p['signatures'][$role] : [];
    if ($entry === []) return 'absent';
    $binding = is_array($entry['binding'] ?? null) ? $entry['binding'] : [];
    if (($entry['source'] ?? '') === 'remote_qr'
        && (($binding['version'] ?? null) !== 1 || empty($binding['consent_uuid'])
            || (int)($binding['token_id'] ?? 0) < 1))
        return 'legacy_unbound';
    if (($binding['version'] ?? null) !== 1) return 'legacy_unverified_binding';
    if (!empty($binding['revoked_in_edit'])) return 'stale_or_unverified';
    if (!in_array((string)($entry['source'] ?? ''), ['local_canvas', 'registered_profile', 'remote_qr'], true)
        || ($role === 'patient' && !in_array((string)($entry['source'] ?? ''), ['local_canvas', 'remote_qr'], true))
        || ($entry['role'] ?? '') !== ($role === 'patient' ? 'patient_or_representative' : 'doctor')
        || trim((string)($p['signature_document_date'] ?? '')) === '') return 'stale_or_unverified';
    foreach (($p['signer_identity_attachment_manifest'] ?? []) as $ref) {
        if (!is_array($ref) || trim((string)($ref['document_uuid'] ?? ($ref['document_id'] ?? ''))) === '')
            return 'stale_or_unverified';
    }
    $digest = clinical_consent_binding_artifact_digest((string)($entry['image_data'] ?? ''));
    if ($digest === '' || !hash_equals($digest, (string)($binding['artifact_digest'] ?? '')))
        return 'stale_or_unverified';
    if (!hash_equals(clinical_consent_binding_fingerprint($body), (string)($binding['content_fingerprint'] ?? '')))
        return 'stale_or_unverified';
    if (($binding['role'] ?? '') !== $role || ($binding['authority'] ?? '') !== $authority
        || ($binding['source'] ?? '') !== ($entry['source'] ?? '')) return 'stale_or_unverified';
    if (($entry['source'] ?? '') === 'remote_qr') {
        if ($pdo === null || $doctorId === '' || trim((string)($entry['token'] ?? '')) === '')
            return 'stale_or_unverified';
        try {
            $query = $pdo->prepare('SELECT t.id,t.status,t.patient_id,t.signature_image_data,t.note_document_uuid,
                q.consent_uuid,q.patient_id AS qr_patient_id,q.doctor_id,q.actor_user_id,q.role,
                q.signer_authority,q.content_fingerprint,q.fingerprint_version,q.artifact_digest,q.invalidated_at
                FROM clinical_note_capture_tokens t JOIN clinical_consent_qr_sessions q ON q.token_id=t.id
                WHERE t.token=? LIMIT 1');
            $query->execute([(string)$entry['token']]);
            $qr = $query->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable) { return 'stale_or_unverified'; }
        $context = is_array($p['signature_context'] ?? null) ? $p['signature_context'] : [];
        if (!is_array($qr) || !in_array((string)$qr['status'], ['uploaded', 'consumed'], true)
            || (int)$qr['id'] !== (int)($binding['token_id'] ?? 0)
            || (string)$qr['consent_uuid'] !== (string)($p['qr_consent_uuid'] ?? '')
            || (string)$qr['consent_uuid'] !== (string)($binding['consent_uuid'] ?? '')
            || (string)$qr['patient_id'] !== (string)($context['patient_id'] ?? '')
            || (string)$qr['qr_patient_id'] !== (string)($context['patient_id'] ?? '')
            || (string)$qr['doctor_id'] !== $doctorId
            || (string)$qr['actor_user_id'] !== (string)($body['actor_user_id'] ?? ($body['actor']['user_id'] ?? ''))
            || (string)$qr['role'] !== $role || (string)$qr['signer_authority'] !== $authority
            || (int)$qr['fingerprint_version'] !== 1
            || trim((string)($qr['invalidated_at'] ?? '')) !== ''
            || ((string)$qr['status'] === 'consumed'
                && (string)$qr['note_document_uuid'] !== (string)($body['_consent_document_uuid'] ?? ''))
            || !hash_equals((string)$qr['content_fingerprint'], (string)$binding['content_fingerprint'])
            || !hash_equals((string)$qr['artifact_digest'], $digest)
            || !hash_equals((string)$qr['signature_image_data'], (string)$entry['image_data']))
            return 'stale_or_unverified';
    }
    if ($role === 'doctor' && ($entry['source'] ?? '') === 'registered_profile') {
        if ($pdo === null || $doctorId === '') return 'stale_or_unverified';
        try {
            $stmt = $pdo->prepare('SELECT checksum_sha256 FROM physician_signatures WHERE doctor_id=? LIMIT 1');
            $stmt->execute([$doctorId]);
            $ownedDigest = $stmt->fetchColumn();
        } catch (Throwable) { return 'stale_or_unverified'; }
        if (!is_string($ownedDigest) || !hash_equals($ownedDigest, $digest))
            return 'stale_or_unverified';
    }
    return 'valid_bound_signature';
}

function clinical_consent_binding_patient_authority(array $payload): string
{
    $form = is_array($payload['form_snapshot'] ?? null) ? $payload['form_snapshot'] : [];
    return json_encode([
        trim(clinical_consent_binding_clean($form['firmante_tipo'] ?? 'paciente')),
        trim(clinical_consent_binding_clean($form['firmante_nombre'] ?? '')),
        trim(clinical_consent_binding_clean($form['firmante_parentesco'] ?? '')),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}
