<?php
declare(strict_types=1);

require_once __DIR__ . '/clinical_consent_signature_binding.php';
require_once __DIR__ . '/clinical_legal_document_presentation.php';

/** Content-only V1 projection. Keep key order in sync with responsiva-signature-binding.js. */
function clinical_responsiva_binding_projection(array $body): array
{
    $p = is_array($body['payload'] ?? null) ? $body['payload'] : [];
    $patient = is_array($p['patient_snapshot'] ?? null) ? $p['patient_snapshot'] : [];
    $actor = is_array($p['actor_snapshot'] ?? null) ? $p['actor_snapshot'] : [];
    $brand = is_array($p['branding'] ?? null) ? $p['branding'] : [];
    $type = is_array($p['responsiva'] ?? null) ? $p['responsiva'] : [];
    $content = is_array($p['content'] ?? null) ? $p['content'] : [];
    $signer = is_array($p['signer'] ?? null) ? $p['signer'] : [];
    $context = is_array($body['context'] ?? null) ? $body['context'] : [];
    $c = 'clinical_consent_binding_clean';
    return [
        'version' => 1,
        'document_type' => 'responsiva_medica',
        'document_date' => $c($p['report']['emission_date'] ?? ''),
        'patient' => [
            'id' => $c($context['patient_id'] ?? ''),
            'name' => $c($patient['full_name'] ?? ''),
            'age' => $c($patient['age'] ?? ''),
            'sex' => $c($patient['sex'] ?? ''),
        ],
        'physician' => [
            'user_id' => $c($actor['user_id'] ?? ''),
            'name' => $c($actor['full_name'] ?? ''),
            'license' => $c($actor['license'] ?? ''),
            'specialty' => $c($actor['specialty'] ?? ''),
            'specialty_license' => $c($actor['specialty_license'] ?? ''),
            'place' => $c($actor['place'] ?? ''),
            'institution' => $c($actor['institution'] ?? ''),
            'facility' => $c($actor['facility'] ?? ''),
        ],
        'visible_branding' => [
            'logo_url' => $c($brand['logo_url_resolved'] ?? ''),
            'facility' => $c($brand['facility_visible'] ?? ($actor['facility'] ?? '')),
            'location' => $c($brand['location_line_visible'] ?? ($actor['place'] ?? '')),
        ],
        ...(clinical_legal_document_professional_header_mode($p) === 'hidden'
            ? ['presentation' => ['professional_header' => 'hidden']] : []),
        'type' => [
            'key' => $c($type['type'] ?? ''),
            'other' => $c($type['type_other'] ?? ''),
            'label' => $c($type['type_label'] ?? ''),
        ],
        'content' => [
            'clinical_situation' => $c($content['clinical_situation'] ?? ''),
            'indicated_conduct' => $c($content['indicated_conduct'] ?? ''),
            'relevant_risk' => $c($content['relevant_risk'] ?? ''),
            'declaration_text' => $c($content['declaration_text'] ?? ''),
            'additional_manifestation' => $c($content['additional_manifestation'] ?? ''),
            'closing_statement' => $c($content['closing_statement'] ?? ''),
        ],
        'signer' => [
            'role' => $c($signer['role'] ?? ''),
            'name' => $c($signer['name'] ?? ''),
            'character' => $c($signer['character'] ?? ''),
            'relationship' => $c($signer['relationship'] ?? ''),
        ],
    ];
}

function clinical_responsiva_binding_fingerprint(array $body): string
{
    return hash('sha256', json_encode(clinical_responsiva_binding_projection($body),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
}

function clinical_responsiva_binding_authority(array $body, string $role, string $doctorId): string
{
    $p = (array)($body['payload'] ?? []);
    if ($role === 'doctor') return $doctorId . '|' . (string)($p['actor_snapshot']['user_id'] ?? '');
    $s = (array)($p['signer'] ?? []);
    return json_encode([(string)($s['role'] ?? ''), (string)($s['name'] ?? ''),
        (string)($s['character'] ?? ''), (string)($s['relationship'] ?? '')],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}

function clinical_responsiva_binding_classify(array $body, string $role, string $doctorId, ?PDO $pdo = null): string
{
    $p = (array)($body['payload'] ?? []);
    $entry = is_array($p['signatures'][$role] ?? null) ? $p['signatures'][$role] : [];
    if ($entry === [] || empty($entry['image_data'])) return 'absent';
    $binding = is_array($entry['binding'] ?? null) ? $entry['binding'] : [];
    if (($binding['version'] ?? null) !== 1) return 'legacy_unverified_binding';
    if (!empty($binding['revoked_in_edit'])) return 'stale_or_unverified_signature';
    $source = (string)($entry['source'] ?? '');
    if (!in_array($source, ['local_canvas', 'registered_profile', 'remote_qr'], true)
        || ($role === 'signer' && !in_array($source, ['local_canvas', 'remote_qr'], true))
        || (string)($entry['role'] ?? '') !== $role
        || (string)($binding['role'] ?? '') !== $role
        || (string)($binding['source'] ?? '') !== $source
        || (string)($binding['authority'] ?? '') !== clinical_responsiva_binding_authority($body, $role, $doctorId)
        || !hash_equals(clinical_responsiva_binding_fingerprint($body), (string)($binding['content_fingerprint'] ?? '')))
        return 'stale_or_unverified_signature';
    $digest = clinical_consent_binding_artifact_digest((string)$entry['image_data']);
    if ($digest === '' || !hash_equals($digest, (string)($binding['artifact_digest'] ?? '')))
        return 'stale_or_unverified_signature';
    if ($source === 'remote_qr') {
        if ($pdo === null || $doctorId === '' || trim((string)($entry['token'] ?? '')) === '')
            return 'stale_or_unverified_signature';
        try {
            $stmt = $pdo->prepare('SELECT * FROM clinical_responsiva_qr_sessions WHERE token=? LIMIT 1');
            $stmt->execute([(string)$entry['token']]);
            $qr = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable) { return 'stale_or_unverified_signature'; }
        $documentUuid = (string)($body['draft_ref'] ?? ($body['_responsiva_document_uuid'] ?? ''));
        if (!is_array($qr) || !in_array((string)$qr['status'], ['uploaded', 'consumed'], true)
            || $qr['invalidated_at'] !== null
            || (string)$qr['document_uuid'] !== $documentUuid
            || (string)($binding['document_uuid'] ?? '') !== $documentUuid
            || (int)$qr['document_version'] !== (int)($binding['document_version'] ?? 0)
            || (string)$qr['doctor_id'] !== $doctorId
            || (string)$qr['actor_user_id'] !== (string)($p['actor_snapshot']['user_id'] ?? '')
            || (string)$qr['patient_id'] !== (string)($body['context']['patient_id'] ?? '')
            || (string)$qr['role'] !== $role
            || (string)$qr['signer_authority'] !== clinical_responsiva_binding_authority($body, $role, $doctorId)
            || (int)$qr['fingerprint_version'] !== 1
            || !hash_equals((string)$qr['content_fingerprint'], (string)($binding['content_fingerprint'] ?? ''))
            || !hash_equals((string)$qr['artifact_digest'], $digest)
            || !hash_equals((string)$qr['signature_image_data'], (string)$entry['image_data'])
            || (string)($binding['token'] ?? '') !== (string)$qr['token']
            || ((string)$qr['status'] === 'uploaded'
                && (int)$qr['document_version'] !== (int)($body['expected_version'] ?? 0))
            || ((string)$qr['status'] === 'consumed'
                && (string)$qr['claimed_document_uuid'] !== $documentUuid))
            return 'stale_or_unverified_signature';
    }
    if ($source === 'registered_profile') {
        if ($pdo === null || $doctorId === '') return 'stale_or_unverified_signature';
        $stmt = $pdo->prepare('SELECT checksum_sha256 FROM physician_signatures WHERE doctor_id=? LIMIT 1');
        $stmt->execute([$doctorId]);
        if (!hash_equals((string)$stmt->fetchColumn(), $digest)) return 'stale_or_unverified_signature';
    }
    return 'valid_bound_signature';
}
