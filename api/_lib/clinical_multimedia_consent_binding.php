<?php
declare(strict_types=1);

require_once __DIR__ . '/clinical_consent_signature_binding.php';
require_once __DIR__ . '/clinical_multimedia_consent_render.php';

/** Content-only V1 projection; key order is shared with multimedia-consent-signature-binding.js. */
function clinical_multimedia_binding_projection(array $body): array
{
    $p = (array)($body['payload'] ?? []);
    $consent = clinical_multimedia_consent_normalize((array)($p['consent'] ?? []));
    $patient = (array)($p['patient_snapshot'] ?? []);
    $actor = (array)($p['actor_snapshot'] ?? []);
    $signer = (array)($p['signer'] ?? []);
    $context = (array)($body['context'] ?? []);
    $c = 'clinical_consent_binding_clean';
    return [
        'version' => 1,
        'document_type' => 'consentimiento_multimedia',
        'document_uuid' => $c($body['draft_ref'] ?? ''),
        'document_version' => (int)($body['expected_version'] ?? 0),
        'document_date' => $c($p['document_date'] ?? ''),
        'patient' => ['id' => $c($context['patient_id'] ?? ''), 'name' => $c($patient['full_name'] ?? ''),
            'age' => $c($patient['age'] ?? ''), 'sex' => $c($patient['sex'] ?? '')],
        'physician' => ['user_id' => $c($actor['user_id'] ?? ''), 'name' => $c($actor['full_name'] ?? ''),
            'license' => $c($actor['license'] ?? ''), 'specialty' => $c($actor['specialty'] ?? ''),
            'facility' => $c($actor['facility'] ?? ''), 'place' => $c($actor['place'] ?? '')],
        'signer' => ['mode' => $c($signer['mode'] ?? ''), 'name' => $c($signer['name'] ?? ''),
            'character' => $c($signer['character'] ?? ''), 'relationship' => $c($signer['relationship'] ?? ''),
            'authority' => $c($signer['authority'] ?? '')],
        'consent' => [
            'media_types' => $consent['media_types'], 'capture_allowed' => $consent['capture_allowed'],
            'use_purposes' => $consent['use_purposes'],
            'identification_scope' => $c($consent['identification_scope']),
            'authorization_scope' => $c($consent['authorization_scope']),
            'custom_scope_description' => $c($consent['custom_scope_description']),
            'validity' => ['mode' => $c($consent['validity']['mode']),
                'valid_from' => $c($consent['validity']['valid_from']),
                'valid_to' => $c($consent['validity']['valid_to'])],
            'context' => ['clinical_event_or_procedure' => $c($consent['context']['clinical_event_or_procedure']),
                'anatomical_area' => $c($consent['context']['anatomical_area']),
                'clinical_purpose_note' => $c($consent['context']['clinical_purpose_note']),
                'event_date' => $c($consent['context']['event_date'])],
            'declaration' => $c(clinical_multimedia_consent_declaration($consent)),
        ],
        'presentation' => ['professional_header' => clinical_legal_document_professional_header_mode($p)],
    ];
}

function clinical_multimedia_binding_fingerprint(array $body): string
{
    return hash('sha256', json_encode(clinical_multimedia_binding_projection($body),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
}

function clinical_multimedia_binding_authority(array $body, string $role, string $doctorId): string
{
    $p = (array)($body['payload'] ?? []);
    if ($role === 'doctor') return $doctorId . '|' . (string)($p['actor_snapshot']['user_id'] ?? '');
    $s = (array)($p['signer'] ?? []);
    if (($s['mode'] ?? '') === 'patient') return json_encode(['patient',
        (string)($body['context']['patient_id'] ?? ''), (string)($p['patient_snapshot']['full_name'] ?? '')],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    return json_encode(['representative', (string)($s['name'] ?? ''), (string)($s['character'] ?? ''),
        (string)($s['relationship'] ?? ''), (string)($s['authority'] ?? '')],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}

function clinical_multimedia_binding_classify(array $body, string $role, string $doctorId, ?PDO $pdo = null): string
{
    $p = (array)($body['payload'] ?? []);
    $entry = (array)($p['signatures'][$role] ?? []);
    if (!$entry || empty($entry['image_data'])) return 'absent';
    $b = (array)($entry['binding'] ?? []);
    $source = (string)($entry['source'] ?? '');
    $uuid = (string)($body['draft_ref'] ?? '');
    $version = (int)($body['expected_version'] ?? 0);
    $name = $role === 'doctor' ? (string)($p['actor_snapshot']['full_name'] ?? '')
        : (($p['signer']['mode'] ?? '') === 'patient' ? (string)($p['patient_snapshot']['full_name'] ?? '')
            : (string)($p['signer']['name'] ?? ''));
    if (($b['version'] ?? null) !== 1 || !in_array($source, ['local_canvas', 'remote_qr', 'registered_profile'], true)
        || ($role === 'signer' && $source === 'registered_profile')
        || ($b['document_type'] ?? '') !== 'consentimiento_multimedia'
        || ($b['document_uuid'] ?? '') !== $uuid || (int)($b['document_version'] ?? 0) !== $version
        || ($entry['role'] ?? '') !== $role || ($b['role'] ?? '') !== $role
        || ($b['source'] ?? '') !== $source || !empty($b['revoked_in_edit'])
        || $name === '' || ($entry['signer_name'] ?? '') !== $name
        || ($b['authority'] ?? '') !== clinical_multimedia_binding_authority($body, $role, $doctorId)
        || !hash_equals(clinical_multimedia_binding_fingerprint($body), (string)($b['content_fingerprint'] ?? '')))
        return 'stale_or_unverified_signature';
    $digest = clinical_consent_binding_artifact_digest((string)$entry['image_data']);
    if ($digest === '' || !hash_equals($digest, (string)($b['artifact_digest'] ?? '')))
        return 'stale_or_unverified_signature';
    if ($source === 'remote_qr') {
        if ($pdo === null || trim((string)($entry['token'] ?? '')) === '') return 'stale_or_unverified_signature';
        try {
            $stmt = $pdo->prepare('SELECT * FROM clinical_multimedia_qr_sessions WHERE token=? LIMIT 1');
            $stmt->execute([(string)$entry['token']]);
            $qr = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable) { return 'stale_or_unverified_signature'; }
        if (!is_array($qr) || !in_array((string)$qr['status'], ['uploaded', 'consumed'], true)
            || $qr['invalidated_at'] !== null || (string)$qr['document_uuid'] !== $uuid
            || (int)$qr['document_version'] !== $version
            || (string)$qr['doctor_id'] !== $doctorId
            || (string)$qr['actor_user_id'] !== (string)($p['actor_snapshot']['user_id'] ?? '')
            || (string)$qr['patient_id'] !== (string)($body['context']['patient_id'] ?? '')
            || (string)$qr['role'] !== $role
            || (string)$qr['signer_authority'] !== clinical_multimedia_binding_authority($body, $role, $doctorId)
            || !hash_equals((string)$qr['content_fingerprint'], (string)$b['content_fingerprint'])
            || !hash_equals((string)$qr['artifact_digest'], $digest)
            || !hash_equals((string)$qr['signature_image_data'], (string)$entry['image_data'])
            || (string)($b['token'] ?? '') !== (string)$qr['token']
            || ((string)$qr['status'] === 'consumed' && (string)$qr['claimed_document_uuid'] !== $uuid))
            return 'stale_or_unverified_signature';
    }
    if ($source === 'registered_profile') {
        if ($pdo === null) return 'stale_or_unverified_signature';
        $stmt = $pdo->prepare('SELECT checksum_sha256 FROM physician_signatures WHERE doctor_id=? LIMIT 1');
        $stmt->execute([$doctorId]);
        if (!hash_equals((string)$stmt->fetchColumn(), $digest)) return 'stale_or_unverified_signature';
    }
    return 'valid_bound_signature';
}
