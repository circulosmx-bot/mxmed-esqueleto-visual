<?php
declare(strict_types=1);

require_once __DIR__ . '/clinical_consent_signature_binding.php';

/** Field order is mirrored by prescription-signature-binding.js. */
function clinical_prescription_binding_projection(array $body): array
{
    $p = (array)($body['payload'] ?? []);
    $patient = (array)($p['patient_snapshot'] ?? []);
    $doctor = (array)($p['physician_snapshot'] ?? []);
    $professional = (array)($p['professional_context'] ?? []);
    $consultorio = (array)($professional['consultorio'] ?? []);
    $diagnosis = (array)($p['diagnostic_context'] ?? []);
    $c = 'clinical_consent_binding_clean';
    $items = [];
    foreach ((array)($p['prescription']['items'] ?? []) as $item) {
        $item = (array)$item;
        $value = [];
        foreach (['name','presentation','dose','route','frequency','duration','quantity','instructions'] as $key)
            $value[$key] = $c($item[$key] ?? '');
        $items[] = $value;
    }
    $headerShown = ($p['presentation']['professional_header'] ?? 'shown') !== 'hidden';
    return [
        'version'=>1, 'document_type'=>'prescription',
        'document_uuid'=>$c($body['draft_ref'] ?? ''),
        'document_version'=>(int)($body['expected_version'] ?? 0),
        'patient'=>['id'=>$c($patient['id'] ?? ''),'name'=>$c($patient['full_name'] ?? ''),
            'age'=>$c($patient['age'] ?? ''),'sex'=>$c($patient['sex'] ?? '')],
        'physician'=>['user_id'=>$c($doctor['user_id'] ?? ''),'doctor_id'=>$c($doctor['doctor_id'] ?? ''),
            'name'=>$c($doctor['full_name'] ?? ''),'prefix'=>$headerShown ? $c($doctor['prefix'] ?? '') : '',
            'designation'=>$headerShown ? $c($doctor['designation'] ?? '') : '',
            'specialty'=>$headerShown ? $c($doctor['specialty'] ?? '') : '',
            'license'=>$c($doctor['license'] ?? ''),'specialty_license'=>$c($doctor['specialty_license'] ?? '')],
        'issue_date'=>$c($p['issue_date'] ?? ''),
        'diagnostic_context'=>['mode'=>$c($diagnosis['mode'] ?? ''),'text'=>$c($diagnosis['text'] ?? '')],
        'encounter_reference'=>['encounter_id'=>(int)($p['encounter_reference']['encounter_id'] ?? 0),
            'appointment_id'=>$c($p['encounter_reference']['appointment_id'] ?? '')],
        'medication_count'=>count($items), 'medications'=>$items,
        'observations'=>$c($p['prescription']['observaciones'] ?? ''),
        'presentation'=>['professional_header'=>(($p['presentation']['professional_header'] ?? '') === 'hidden' ? 'hidden' : 'shown')],
        'professional_context'=>['logo_url'=>$headerShown ? $c($professional['logo_url'] ?? '') : '',
            'consultorio'=>['name'=>$headerShown ? $c($consultorio['name'] ?? '') : '',
                'address'=>$headerShown ? $c($consultorio['address'] ?? '') : '']],
    ];
}

function clinical_prescription_binding_fingerprint(array $body): string
{
    return hash('sha256', json_encode(clinical_prescription_binding_projection($body),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
}

function clinical_prescription_binding_authority(array $body, string $doctorId): string
{
    return $doctorId . '|' . (string)($body['payload']['physician_snapshot']['user_id'] ?? '');
}

function clinical_prescription_binding_classify(array $body, string $doctorId, ?PDO $pdo = null): string
{
    $entry = (array)($body['payload']['signatures']['doctor'] ?? []);
    if (!$entry) return 'absent';
    $binding = (array)($entry['binding'] ?? []);
    $source = (string)($entry['source'] ?? '');
    $uuid = (string)($body['draft_ref'] ?? '');
    $version = (int)($body['expected_version'] ?? 0);
    if (($binding['version'] ?? null) !== 1) return 'legacy_unverified_binding';
    if ($uuid === '' || $version < 1 || !in_array($source, ['local_canvas','registered_profile','remote_qr'], true)
        || ($entry['role'] ?? null) !== 'doctor' || ($binding['role'] ?? null) !== 'doctor'
        || ($binding['source'] ?? null) !== $source || !empty($binding['revoked_in_edit'])
        || ($binding['document_type'] ?? null) !== 'prescription'
        || ($binding['document_uuid'] ?? null) !== $uuid
        || (int)($binding['document_version'] ?? 0) !== $version
        || ($binding['authority'] ?? null) !== clinical_prescription_binding_authority($body, $doctorId)
        || ($entry['signer_name'] ?? '') !== ($body['payload']['physician_snapshot']['full_name'] ?? '')
        || !hash_equals(clinical_prescription_binding_fingerprint($body), (string)($binding['content_fingerprint'] ?? '')))
        return 'stale_or_unverified_signature';
    $digest = clinical_consent_binding_artifact_digest((string)($entry['image_data'] ?? ''));
    if ($digest === '' || !hash_equals($digest, (string)($binding['artifact_digest'] ?? '')))
        return 'stale_or_unverified_signature';
    if ($source === 'registered_profile') {
        if ($pdo === null) return 'stale_or_unverified_signature';
        $stmt = $pdo->prepare('SELECT checksum_sha256 FROM physician_signatures WHERE doctor_id=? LIMIT 1');
        $stmt->execute([$doctorId]);
        if (!hash_equals((string)$stmt->fetchColumn(), $digest)) return 'stale_or_unverified_signature';
    }
    if ($source === 'remote_qr') {
        if ($pdo === null) return 'stale_or_unverified_signature';
        require_once __DIR__ . '/clinical_prescription_qr.php';
        $qr = clinical_prescription_qr_row($pdo, (string)($entry['token'] ?? ''));
        if (!$qr || !in_array((string)$qr['status'], ['uploaded','consumed'], true)
            || $qr['invalidated_at'] !== null || (string)$qr['document_uuid'] !== $uuid
            || (int)$qr['document_version'] !== $version || (string)$qr['doctor_id'] !== $doctorId
            || (string)$qr['actor_user_id'] !== (string)($body['payload']['physician_snapshot']['user_id'] ?? '')
            || (string)$qr['patient_id'] !== (string)($body['context']['patient_id'] ?? '')
            || (string)$qr['role'] !== 'doctor'
            || (string)$qr['signer_authority'] !== clinical_prescription_binding_authority($body, $doctorId)
            || !hash_equals((string)$qr['content_fingerprint'], (string)$binding['content_fingerprint'])
            || !hash_equals((string)$qr['artifact_digest'], $digest)
            || !hash_equals((string)$qr['signature_image_data'], (string)$entry['image_data'])
            || (string)($binding['token'] ?? '') !== (string)$qr['token']
            || ((string)$qr['status'] === 'consumed' && (string)$qr['claimed_document_uuid'] !== $uuid))
            return 'stale_or_unverified_signature';
    }
    return 'valid_bound_signature';
}
