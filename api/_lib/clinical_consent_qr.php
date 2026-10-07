<?php
declare(strict_types=1);

require_once __DIR__ . '/clinical_consent_signature_binding.php';

function clinical_consent_qr_session(PDO $pdo, int $tokenId): ?array
{
    $query = $pdo->prepare('SELECT * FROM clinical_consent_qr_sessions WHERE token_id=? LIMIT 1');
    $query->execute([$tokenId]);
    $row = $query->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function clinical_consent_qr_prepare(array $body, array $doctor, string $patientId, string $noteContext): array
{
    $consent = is_array($body['consent'] ?? null) ? $body['consent'] : [];
    $payload = is_array($consent['payload'] ?? null) ? $consent['payload'] : [];
    $context = is_array($consent['context'] ?? null) ? $consent['context'] : [];
    $uuid = trim((string)($body['consent_uuid'] ?? ''));
    $draftRef = trim((string)($body['draft_ref'] ?? ''));
    $draftVersion = (int)($body['draft_version'] ?? 0);
    $role = $noteContext === 'consentimiento_firma_remota:doctor' ? 'doctor' : 'patient';
    $expectedContext = $role === 'doctor' ? 'consentimiento_firma_remota:doctor' : 'consentimiento_firma_remota:patient';
    $html = (string)($payload['frozen_snapshot']['html'] ?? '');
    $date = trim((string)($payload['signature_document_date'] ?? ''));
    $actor = (string)($doctor['user_id'] ?? '');
    $bodyActor = (string)($consent['actor_user_id'] ?? ($consent['actor']['user_id'] ?? ''));
    if ($noteContext !== $expectedContext || preg_match('/^[0-9a-f-]{36}$/i', $uuid) !== 1
        || ($consent['document_type'] ?? '') !== 'consentimiento_informado'
        || ($payload['consent']['status'] ?? '') !== 'draft'
        || ($draftRef !== '' && (preg_match('/^[0-9a-f-]{36}$/i', $draftRef) !== 1 || $draftVersion < 1))
        || (string)($context['patient_id'] ?? '') !== $patientId
        || $bodyActor !== $actor
        || (string)($payload['qr_consent_uuid'] ?? '') !== $uuid
        || $html === '' || strlen($html) > 500000 || $date === ''
        || !clinical_consent_qr_review_safe($html)) {
        throw new InvalidArgumentException('CONSENT_QR_CONTEXT_INVALID');
    }
    foreach (($payload['signer_identity_attachment_manifest'] ?? []) as $ref) {
        if (!is_array($ref) || trim((string)($ref['document_uuid'] ?? '')) === '') {
            throw new InvalidArgumentException('CONSENT_QR_ATTACHMENTS_UNSAVED');
        }
    }
    $authority = $role === 'doctor'
        ? (string)$doctor['doctor_id'] . '|' . $actor
        : clinical_consent_binding_patient_authority($payload);
    $signerName = trim((string)($role === 'doctor'
        ? ($payload['actor_snapshot']['full_name'] ?? '')
        : ($payload['form_snapshot']['firmante_nombre'] ?? '')));
    if ($authority === '' || ($role === 'patient'
        && $signerName === '')) {
        throw new InvalidArgumentException('CONSENT_QR_SIGNER_INVALID');
    }
    $fingerprint = clinical_consent_binding_fingerprint($consent);
    if (!preg_match('/^[a-f0-9]{64}$/', (string)($body['content_fingerprint'] ?? ''))
        || !hash_equals($fingerprint, (string)$body['content_fingerprint'])) {
        throw new InvalidArgumentException('CONSENT_QR_FINGERPRINT_MISMATCH');
    }
    return [
        'consent_uuid' => $uuid, 'draft_ref' => $draftRef ?: null,
        'draft_version' => $draftRef !== '' ? $draftVersion : null,
        'patient_id' => $patientId,
        'doctor_id' => (string)$doctor['doctor_id'], 'actor_user_id' => $actor,
        'role' => $role, 'signer_authority' => $authority, 'signer_name' => $signerName,
        'content_fingerprint' => $fingerprint,
        'fingerprint_version' => 1, 'document_date' => $date,
        'review_html' => $html, 'review_html_sha256' => hash('sha256', $html),
    ];
}

function clinical_consent_qr_review_safe(string $html): bool
{
    // Frozen HTML is composed by the existing consent renderer. Never execute markup on the phone.
    return preg_match('/<\s*(?:script|iframe|form|input|button|meta|base)\b/i', $html) !== 1
        && preg_match('/\bon\w+\s*=/i', $html) !== 1
        && preg_match('/(?:javascript|data:text\/html)\s*:/i', $html) !== 1;
}

function clinical_consent_qr_draft_current(PDO $pdo, array $session): bool
{
    $ref = trim((string)($session['draft_ref'] ?? ''));
    if ($ref === '') return true;
    $query = $pdo->prepare('SELECT id,patient_id,created_by_user_id,status,version,payload_json,signed_at FROM clinical_documents
        WHERE document_uuid=? AND document_type=\'consentimiento_informado\' LIMIT 1');
    $query->execute([$ref]);
    $row = $query->fetch(PDO::FETCH_ASSOC);
    $legacyDraft = false;
    if (is_array($row) && (string)$row['status'] === 'generated' && $row['signed_at'] === null) {
        $payload = json_decode((string)$row['payload_json'], true);
        if (is_array($payload) && (string)($payload['consent']['status'] ?? '') === 'draft') {
            $revision = $pdo->prepare('SELECT revision_id FROM clinical_document_revisions
                WHERE original_document_id=? OR supersedes_document_id=? OR new_document_id=? LIMIT 1');
            $revision->execute([(int)$row['id'], (int)$row['id'], (int)$row['id']]);
            $legacyDraft = $revision->fetchColumn() === false;
        }
    }
    return is_array($row) && (string)$row['patient_id'] === (string)$session['patient_id']
        && (string)$row['created_by_user_id'] === (string)$session['actor_user_id']
        && ((string)$row['status'] === 'draft' || $legacyDraft)
        && (int)$row['version'] === (int)$session['draft_version'];
}

function clinical_consent_qr_insert(PDO $pdo, int $tokenId, array $session): void
{
    $query = $pdo->prepare('INSERT INTO clinical_consent_qr_sessions
        (token_id,consent_uuid,draft_ref,draft_version,patient_id,doctor_id,actor_user_id,role,signer_authority,signer_name,
        content_fingerprint,fingerprint_version,document_date,review_html,review_html_sha256,created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP())');
    $query->execute([$tokenId, $session['consent_uuid'], $session['draft_ref'], $session['draft_version'],
        $session['patient_id'], $session['doctor_id'],
        $session['actor_user_id'], $session['role'], $session['signer_authority'], $session['signer_name'],
        $session['content_fingerprint'], 1, $session['document_date'],
        $session['review_html'], $session['review_html_sha256']]);
}

function clinical_consent_qr_binding(PDO $pdo, array $tokenRow): ?array
{
    $session = clinical_consent_qr_session($pdo, (int)$tokenRow['id']);
    if ($session === null || trim((string)($session['invalidated_at'] ?? '')) !== ''
        || !in_array((string)$tokenRow['status'], ['uploaded', 'consumed'], true)) return null;
    $digest = clinical_consent_binding_artifact_digest((string)($tokenRow['signature_image_data'] ?? ''));
    if ($digest === '' || !hash_equals($digest, (string)($session['artifact_digest'] ?? ''))) return null;
    return [
        'version' => 1, 'content_fingerprint' => $session['content_fingerprint'],
        'artifact_digest' => $digest, 'source' => 'remote_qr', 'role' => $session['role'],
        'authority' => $session['signer_authority'],
        'consent_uuid' => $session['consent_uuid'],
        'token_id' => (int)$tokenRow['id'],
        'applied_at' => (string)($tokenRow['signature_signed_at'] ?? $tokenRow['uploaded_at'] ?? ''),
    ];
}

function clinical_consent_qr_desktop_allowed(PDO $pdo, array $tokenRow, array $doctor): bool
{
    if (!str_starts_with((string)($tokenRow['note_context'] ?? ''), 'consentimiento_firma_remota')) return true;
    $session = clinical_consent_qr_session($pdo, (int)$tokenRow['id']);
    return $session !== null && (string)$session['doctor_id'] === (string)($doctor['doctor_id'] ?? '')
        && (string)$session['actor_user_id'] === (string)($doctor['user_id'] ?? '');
}

function clinical_consent_qr_status(PDO $pdo, array $tokenRow): array
{
    $data = clinical_note_capture_status_data($tokenRow);
    if (!str_starts_with((string)($tokenRow['note_context'] ?? ''), 'consentimiento_firma_remota')) return $data;
    $session = clinical_consent_qr_session($pdo, (int)$tokenRow['id']);
    $draftCurrent = $session !== null && clinical_consent_qr_draft_current($pdo, $session);
    $binding = $session !== null && ((string)$tokenRow['status'] === 'consumed' || $draftCurrent)
        ? clinical_consent_qr_binding($pdo, $tokenRow) : null;
    if ($session !== null && isset($data['signature'])) {
        $data['signature']['role'] = $session['role'] === 'doctor' ? 'doctor' : 'patient_or_representative';
        $data['signature']['signer_name'] = (string)$session['signer_name'];
        if ($binding !== null) $data['signature']['binding'] = $binding;
    }
    if ($session !== null && (trim((string)($session['invalidated_at'] ?? '')) !== ''
        || ((string)$tokenRow['status'] !== 'consumed' && !$draftCurrent))) $data['qr_stale'] = true;
    return $data;
}
