<?php
declare(strict_types=1);

require_once __DIR__ . '/clinical_consent_signature_binding.php';

function clinical_prescription_fingerprint_version(array $body): int
{
    $prescription = (array)($body['payload']['prescription'] ?? []);
    $schema = $prescription['medication_schema_version'] ?? 1;
    $hasRegimen = false;
    foreach ((array)($prescription['items'] ?? []) as $item) {
        if (is_array($item) && array_key_exists('regimen', $item)) $hasRegimen = true;
    }
    if ($schema === 2) {
        foreach ((array)($prescription['items'] ?? []) as $item) {
            if (!is_array($item) || !is_array($item['regimen'] ?? null))
                throw new InvalidArgumentException('PRESCRIPTION_FINGERPRINT_SCHEMA_INVALID');
        }
        return 2;
    }
    if ($schema !== 1 || $hasRegimen) throw new InvalidArgumentException('PRESCRIPTION_FINGERPRINT_SCHEMA_DOWNGRADE');
    return 1;
}

function clinical_prescription_binding_regimen_projection(array $regimen): array
{
    $c = 'clinical_consent_binding_clean';
    $administration = (array)($regimen['administration'] ?? []);
    $duration = (array)($regimen['duration'] ?? []);
    $a = ['pattern'=>$c($administration['pattern'] ?? '')];
    foreach (['instruction','indication','repeat_mode'] as $key)
        if (array_key_exists($key, $administration)) $a[$key] = $c($administration[$key]);
    if (isset($administration['minimum_interval'])) $a['minimum_interval'] = [
        'value'=>$c($administration['minimum_interval']['value'] ?? ''),
        'unit'=>$c($administration['minimum_interval']['unit'] ?? '')];
    if (isset($administration['maximum_dose_per_period'])) {
        $maximum = (array)$administration['maximum_dose_per_period'];
        $a['maximum_dose_per_period'] = ['value'=>$c($maximum['value'] ?? ''),'unit'=>$c($maximum['unit'] ?? ''),
            'period'=>['value'=>$c($maximum['period']['value'] ?? ''),'unit'=>$c($maximum['period']['unit'] ?? '')]];
    }
    if (isset($administration['maximum_administrations_per_period'])) {
        $maximum = (array)$administration['maximum_administrations_per_period'];
        $a['maximum_administrations_per_period'] = ['count'=>(int)($maximum['count'] ?? 0),
            'period'=>['value'=>$c($maximum['period']['value'] ?? ''),'unit'=>$c($maximum['period']['unit'] ?? '')]];
    }
    $d = ['mode'=>$c($duration['mode'] ?? '')];
    foreach (['value','unit','date','instruction'] as $key)
        if (array_key_exists($key, $duration)) $d[$key] = $c($duration[$key]);
    return ['administration'=>$a,'duration'=>$d];
}

/** Field order is mirrored by prescription-signature-binding.js. */
function clinical_prescription_binding_projection(array $body): array
{
    $fingerprintVersion = clinical_prescription_fingerprint_version($body);
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
        if ($fingerprintVersion === 2) $value['regimen'] = clinical_prescription_binding_regimen_projection(
            (array)($item['regimen'] ?? []));
        $items[] = $value;
    }
    $presentation = (array)($p['presentation'] ?? []);
    $presentationV2 = ($presentation['version'] ?? null) === 2;
    $headerMode = $presentationV2 && in_array($presentation['professional_header'] ?? null,
        ['hidden','data_only','logo_and_data'], true)
        ? $presentation['professional_header']
        : (($presentation['professional_header'] ?? 'shown') === 'hidden' ? 'hidden' : 'shown');
    $headerShown = $headerMode !== 'hidden';
    $logoShown = $headerMode === 'shown' || $headerMode === 'logo_and_data';
    $projection = [
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
        'presentation'=>$presentationV2
            ? ['version'=>2,'professional_header'=>$headerMode]
            : ['professional_header'=>$headerMode],
        'professional_context'=>['logo_url'=>$logoShown ? $c($professional['logo_url'] ?? '') : '',
            'consultorio'=>['name'=>$headerShown ? $c($consultorio['name'] ?? '') : '',
                'address'=>$headerShown ? $c($consultorio['address'] ?? '') : '']],
    ];
    $footer = (array)($p['professional_presentation'] ?? []);
    if ((int)($footer['version'] ?? 0) >= 2) {
        $offices = [];
        foreach (array_slice((array)($footer['offices'] ?? []), 0, 3) as $office) {
            if (!is_array($office)) continue;
            $phones = [];
            foreach ((array)($office['phones'] ?? []) as $phone) $phones[] = $c($phone);
            $offices[] = [
                'name'=>$c($office['name'] ?? ''),
                'address'=>$c($office['address'] ?? ''),
                'phones'=>$phones,
                'is_primary'=>($office['is_primary'] ?? false) === true,
                'display_order'=>($office['display_order'] ?? null) === null
                    ? null : (int)$office['display_order'],
            ];
        }
        $projection['version'] = 2;
        $projection['professional_context']['consultorio'] = ['name'=>'', 'address'=>''];
        $projection['professional_presentation'] = ['version'=>2, 'offices'=>$offices];
    }
    if ($fingerprintVersion === 2) {
        $projection['fingerprint_version'] = 2;
        $projection['medication_schema_version'] = 2;
    }
    return $projection;
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
    $fingerprintVersion = clinical_prescription_fingerprint_version($body);
    if (($binding['version'] ?? null) !== $fingerprintVersion) return 'legacy_unverified_binding';
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
            || (int)$qr['fingerprint_version'] !== $fingerprintVersion
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
