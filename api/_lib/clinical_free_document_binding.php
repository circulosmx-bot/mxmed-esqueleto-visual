<?php
declare(strict_types=1);

require_once __DIR__ . '/clinical_consent_signature_binding.php';

/** Field order is the exact JSON projection in free-document-signature-binding.js. */
function clinical_free_document_binding_projection(array $body): array
{
    $p = (array)($body['payload'] ?? []);
    $d = (array)($p['document'] ?? []);
    $patient = (array)($p['patient_snapshot'] ?? []);
    $actor = (array)($p['actor_snapshot'] ?? []);
    $c = 'clinical_consent_binding_clean';
    $sections = (array)($d['sections'] ?? []);
    usort($sections, static fn($a, $b) => (int)($a['section_order'] ?? 0) <=> (int)($b['section_order'] ?? 0));
    $items = [];
    foreach ($sections as $section) {
        $items[] = ['order'=>(int)($section['section_order'] ?? 0),
            'title'=>$c($section['section_title'] ?? ''), 'body'=>$c($section['section_body'] ?? '')];
    }
    return [
        'version'=>1, 'document_type'=>'documento_libre',
        'document_uuid'=>$c($body['draft_ref'] ?? ''),
        'document_version'=>(int)($body['expected_version'] ?? 0),
        'patient'=>['id'=>$c($patient['id'] ?? ''), 'name'=>$c($patient['full_name'] ?? ''),
            'age'=>$c($patient['age'] ?? ''), 'sex'=>$c($patient['sex'] ?? '')],
        'physician'=>['user_id'=>$c($actor['user_id'] ?? ''), 'name'=>$c($actor['full_name'] ?? ''),
            'license'=>$c($actor['license'] ?? ''), 'specialty'=>$c($actor['specialty'] ?? ''),
            'specialty_license'=>$c($actor['specialty_license'] ?? '')],
        'logo_url'=>clinical_legal_document_professional_header_mode($p) === 'shown'
            ? $c($p['branding']['logo_url_resolved'] ?? '') : '',
        'presentation'=>['professional_header'=>clinical_legal_document_professional_header_mode($p)],
        'document'=>['title'=>$c($d['title'] ?? ''), 'purpose'=>$c($d['purpose'] ?? ''),
            'sections'=>$items, 'closing_note'=>$c($d['closing_note'] ?? '')],
    ];
}

function clinical_free_document_binding_fingerprint(array $body): string
{
    return hash('sha256', json_encode(clinical_free_document_binding_projection($body),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
}

function clinical_free_document_binding_authority(array $body, string $doctorId): string
{
    return $doctorId . '|' . (string)($body['payload']['actor_snapshot']['user_id'] ?? '');
}

function clinical_free_document_binding_classify(array $body, string $doctorId, ?PDO $pdo = null): string
{
    $entry = (array)($body['payload']['signatures']['doctor'] ?? []);
    if (!$entry) return 'absent';
    $binding = (array)($entry['binding'] ?? []);
    $source = (string)($entry['source'] ?? '');
    if (($binding['version'] ?? null) !== 1) return 'legacy_unverified_binding';
    $uuid = (string)($body['draft_ref'] ?? '');
    $version = (int)($body['expected_version'] ?? 0);
    if ($uuid === '' || $version < 1 || !in_array($source, ['local_canvas','registered_profile','remote_qr'], true)
        || ($entry['role'] ?? null) !== 'doctor' || ($binding['role'] ?? null) !== 'doctor'
        || ($binding['source'] ?? null) !== $source || !empty($binding['revoked_in_edit'])
        || ($binding['document_type'] ?? null) !== 'documento_libre'
        || ($binding['document_uuid'] ?? null) !== $uuid
        || (int)($binding['document_version'] ?? 0) !== $version
        || ($binding['authority'] ?? null) !== clinical_free_document_binding_authority($body, $doctorId)
        || ($entry['signer_name'] ?? '') !== ($body['payload']['actor_snapshot']['full_name'] ?? '')
        || !hash_equals(clinical_free_document_binding_fingerprint($body), (string)($binding['content_fingerprint'] ?? '')))
        return 'stale_or_unverified_signature';
    $digest = clinical_consent_binding_artifact_digest((string)($entry['image_data'] ?? ''));
    if ($digest === '' || !hash_equals($digest, (string)($binding['artifact_digest'] ?? '')))
        return 'stale_or_unverified_signature';
    if ($source === 'registered_profile') {
        if ($pdo === null) return 'stale_or_unverified_signature';
        $query = $pdo->prepare('SELECT checksum_sha256 FROM physician_signatures WHERE doctor_id=? LIMIT 1');
        $query->execute([$doctorId]);
        if (!hash_equals((string)$query->fetchColumn(), $digest)) return 'stale_or_unverified_signature';
    }
    if ($source === 'remote_qr') {
        if ($pdo === null) return 'stale_or_unverified_signature';
        require_once __DIR__ . '/clinical_free_document_qr.php';
        $qr = clinical_free_document_qr_row($pdo, (string)($entry['token'] ?? ''));
        if (!$qr || !in_array((string)$qr['status'], ['uploaded','consumed'], true)
            || $qr['invalidated_at'] !== null
            || (string)$qr['document_uuid'] !== $uuid || (int)$qr['document_version'] !== $version
            || (string)$qr['doctor_id'] !== $doctorId
            || (string)$qr['actor_user_id'] !== (string)($body['payload']['actor_snapshot']['user_id'] ?? '')
            || (string)$qr['patient_id'] !== (string)($body['context']['patient_id'] ?? '')
            || (string)$qr['role'] !== 'doctor'
            || (string)$qr['signer_authority'] !== clinical_free_document_binding_authority($body, $doctorId)
            || !hash_equals((string)$qr['content_fingerprint'], (string)$binding['content_fingerprint'])
            || !hash_equals((string)$qr['artifact_digest'], $digest)
            || !hash_equals((string)$qr['signature_image_data'], (string)$entry['image_data'])
            || (string)($binding['token'] ?? '') !== (string)$qr['token']
            || ((string)$qr['status'] === 'consumed' && (string)$qr['claimed_document_uuid'] !== $uuid))
            return 'stale_or_unverified_signature';
    }
    return 'valid_bound_signature';
}
