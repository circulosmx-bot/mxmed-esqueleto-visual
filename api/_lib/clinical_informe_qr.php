<?php
declare(strict_types=1);

require_once __DIR__ . '/clinical_informe_binding.php';
require_once __DIR__ . '/clinical_informe_render.php';

/** The phone reviews the exact unsigned content; an earlier signature is never carried into replacement. */
function clinical_informe_qr_review_html(array $payload): string
{
    $review = $payload;
    $review['signatures']['doctor'] = null;
    return clinical_informe_render_html($review);
}

/** The table is independent of Consentimiento's QR authority and of clinical_documents schema. */
function clinical_informe_qr_ensure_schema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS clinical_informe_qr_sessions (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
      token CHAR(64) NOT NULL, document_uuid CHAR(36) NOT NULL, document_version INT NOT NULL,
      patient_id VARCHAR(128) NOT NULL, doctor_id VARCHAR(64) NOT NULL,
      actor_user_id VARCHAR(64) NOT NULL, role VARCHAR(16) NOT NULL,
      signer_authority VARCHAR(512) NOT NULL, signer_name VARCHAR(191) NOT NULL,
      signer_role_label VARCHAR(64) NOT NULL, fingerprint_version TINYINT NOT NULL DEFAULT 1,
      content_fingerprint CHAR(64) NOT NULL, review_html MEDIUMTEXT NOT NULL,
      review_html_sha256 CHAR(64) NOT NULL, signature_image_data MEDIUMTEXT DEFAULT NULL,
      artifact_digest CHAR(64) DEFAULT NULL, status VARCHAR(16) NOT NULL DEFAULT 'pending',
      created_at DATETIME NOT NULL, expires_at DATETIME NOT NULL,
      reviewed_at DATETIME DEFAULT NULL, uploaded_at DATETIME DEFAULT NULL,
      consumed_at DATETIME DEFAULT NULL, invalidated_at DATETIME DEFAULT NULL,
      claimed_document_uuid CHAR(36) DEFAULT NULL,
      UNIQUE KEY uq_informe_qr_token (token),
      KEY idx_informe_qr_document (document_uuid,role,status),
      KEY idx_informe_qr_expiry (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function clinical_informe_qr_row(PDO $pdo, string $token, bool $lock = false): ?array
{
    if (preg_match('/^[a-f0-9]{64}$/D', $token) !== 1) return null;
    $stmt = $pdo->prepare('SELECT * FROM clinical_informe_qr_sessions WHERE token=? LIMIT 1' . ($lock ? ' FOR UPDATE' : ''));
    $stmt->execute([$token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function clinical_informe_qr_document(PDO $pdo, string $uuid, bool $lock = false): ?array
{
    $stmt = $pdo->prepare("SELECT document_uuid,version,status,patient_id,created_by_user_id,payload_json
      FROM clinical_documents WHERE document_uuid=? AND document_type='informe_medico' LIMIT 1" . ($lock ? ' FOR UPDATE' : ''));
    $stmt->execute([$uuid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function clinical_informe_qr_current(PDO $pdo, array $session): bool
{
    $document = clinical_informe_qr_document($pdo, (string)$session['document_uuid']);
    $payload = is_array($document) ? json_decode((string)$document['payload_json'], true) : null;
    if (!is_array($document) || !is_array($payload)
        || (string)$document['status'] !== 'draft'
        || (int)$document['version'] !== (int)$session['document_version']
        || (string)$document['patient_id'] !== (string)$session['patient_id']
        || (string)$document['created_by_user_id'] !== (string)$session['actor_user_id']
        || (string)($payload['actor_snapshot']['user_id'] ?? '') !== (string)$session['actor_user_id']) return false;
    $body = ['draft_ref' => (string)$document['document_uuid'],
        'context' => ['patient_id' => $document['patient_id']], 'payload' => $payload];
    return hash_equals((string)$session['content_fingerprint'], clinical_informe_binding_fingerprint($body))
        && hash_equals((string)$session['signer_authority'],
            clinical_informe_binding_authority($body, (string)$session['doctor_id']))
        && hash_equals((string)$session['review_html_sha256'], hash('sha256', (string)$session['review_html']))
        && hash_equals((string)$session['review_html_sha256'], hash('sha256', clinical_informe_qr_review_html($payload)));
}

function clinical_informe_qr_status(PDO $pdo, array $session, bool $includeSignature = false): array
{
    $status = (string)$session['status'];
    if (in_array($status, ['pending', 'uploaded'], true)
        && ((string)$session['expires_at'] <= gmdate('Y-m-d H:i:s') || !clinical_informe_qr_current($pdo, $session)
            || $session['invalidated_at'] !== null)) {
        $status = (string)$session['expires_at'] <= gmdate('Y-m-d H:i:s') ? 'expired' : 'stale';
    }
    $data = ['status' => $status, 'expires_at' => gmdate('c', strtotime((string)$session['expires_at'] . ' UTC')),
        'role' => $session['role'], 'role_label' => $session['signer_role_label'],
        'signer_name' => $session['signer_name']];
    if ($includeSignature && $status === 'uploaded') {
        $data['signature'] = [
            'type' => 'drawn', 'source' => 'remote_qr', 'role' => $session['role'],
            'image_data' => $session['signature_image_data'], 'token' => $session['token'],
            'signed_at' => $session['uploaded_at'], 'signer_name' => $session['signer_name'],
            'binding' => clinical_informe_qr_binding($session),
        ];
    }
    return $data;
}

function clinical_informe_qr_binding(array $session): array
{
    return ['version' => 1, 'document_type' => 'informe_medico', 'source' => 'remote_qr', 'role' => $session['role'],
        'authority' => $session['signer_authority'], 'content_fingerprint' => $session['content_fingerprint'],
        'artifact_digest' => $session['artifact_digest'], 'document_uuid' => $session['document_uuid'],
        'document_version' => (int)$session['document_version'], 'token' => $session['token'],
        'applied_at' => $session['uploaded_at']];
}

function clinical_informe_qr_claim(PDO $pdo, array $body, array $entry, string $role,
    string $doctorId, string $documentUuid, int $expectedVersion): void
{
    $token = trim((string)($entry['token'] ?? ''));
    $session = clinical_informe_qr_row($pdo, $token, true);
    $binding = is_array($entry['binding'] ?? null) ? $entry['binding'] : [];
    if ($session === null || (string)$session['status'] !== 'uploaded'
        || $session['invalidated_at'] !== null || (string)$session['expires_at'] <= gmdate('Y-m-d H:i:s')
        || (string)$session['document_uuid'] !== $documentUuid
        || (int)$session['document_version'] !== $expectedVersion
        || (string)$session['doctor_id'] !== $doctorId
        || (string)$session['patient_id'] !== (string)($body['context']['patient_id'] ?? '')
        || (string)$session['role'] !== $role
        || (string)$session['signer_authority'] !== clinical_informe_binding_authority($body, $doctorId)
        || !hash_equals((string)$session['content_fingerprint'], clinical_informe_binding_fingerprint($body))
        || !hash_equals((string)$session['artifact_digest'], clinical_consent_binding_artifact_digest((string)($entry['image_data'] ?? '')))
        || !hash_equals((string)$session['signature_image_data'], (string)($entry['image_data'] ?? ''))
        || $binding !== clinical_informe_qr_binding($session))
        throw new InvalidArgumentException('INFORME_QR_STALE');
    $stmt = $pdo->prepare("UPDATE clinical_informe_qr_sessions SET status='consumed',consumed_at=UTC_TIMESTAMP(),
        claimed_document_uuid=? WHERE id=? AND status='uploaded'");
    $stmt->execute([$documentUuid, (int)$session['id']]);
    if ($stmt->rowCount() !== 1) throw new InvalidArgumentException('INFORME_QR_REPLAY');
}

function clinical_informe_qr_route(PDO $pdo, string $method, array $segments): void
{
    clinical_informe_qr_ensure_schema($pdo);
    $action = (string)($segments[2] ?? '');
    $desktop = ($method === 'POST' && count($segments) === 1)
        || ($method === 'GET' && $action === 'status')
        || ($method === 'POST' && in_array($action, ['cancel', 'invalidate'], true));
    $doctor = $desktop ? clinical_require_doctor_context('informe-qr-sessions') : null;
    if ($desktop && $doctor === null) return;
    if ($method === 'POST' && count($segments) === 1) {
        $body = clinical_read_json_body();
        $data = is_array($body['data'] ?? null) ? $body['data'] : [];
        $uuid = trim((string)($data['document_uuid'] ?? ''));
        $version = (int)($data['document_version'] ?? 0);
        $role = (string)($data['role'] ?? '');
        $document = clinical_informe_qr_document($pdo, $uuid);
        $payload = is_array($document) ? json_decode((string)$document['payload_json'], true) : null;
        if (($body['ok'] ?? false) !== true || $role !== 'doctor'
            || !is_array($document) || !is_array($payload) || (string)$document['status'] !== 'draft'
            || (int)$document['version'] !== $version
            || (string)$document['created_by_user_id'] !== (string)$doctor['user_id']
            || (string)($payload['actor_snapshot']['user_id'] ?? '') !== (string)$doctor['user_id']
            || !clinical_has_active_doctor_patient_link($pdo, (string)$doctor['doctor_id'], (string)$document['patient_id'])) {
            clinical_send_response(['ok' => false, 'error' => 'INFORME_QR_CONTEXT_INVALID'], 409);
            return;
        }
        $documentBody = ['draft_ref' => (string)$document['document_uuid'],
            'context' => ['patient_id' => $document['patient_id']], 'payload' => $payload];
        $authority = clinical_informe_binding_authority($documentBody, (string)$doctor['doctor_id']);
        $name = trim((string)($payload['actor_snapshot']['full_name'] ?? ''));
        if ($name === '' || $authority === '') {
            clinical_send_response(['ok' => false, 'error' => 'INFORME_QR_SIGNER_REQUIRED'], 422);
            return;
        }
        $label = 'Médico responsable';
        $html = clinical_informe_qr_review_html($payload);
        if (strlen($html) > 500000
            || preg_match('/<\s*(?:script|iframe|form|input|button|meta|base)\b/i', $html) === 1
            || preg_match('/\bon\w+\s*=/i', $html) === 1
            || preg_match('/(?:javascript|data:text\/html)\s*:/i', $html) === 1) {
            clinical_send_response(['ok' => false, 'error' => 'INFORME_QR_REVIEW_INVALID'], 422);
            return;
        }
        $token = bin2hex(random_bytes(32));
        $pdo->beginTransaction();
        try {
            $invalidate = $pdo->prepare("UPDATE clinical_informe_qr_sessions SET invalidated_at=UTC_TIMESTAMP(),status='stale'
                WHERE document_uuid=? AND role=? AND status IN ('pending','uploaded')");
            $invalidate->execute([$uuid, $role]);
            $stmt = $pdo->prepare("INSERT INTO clinical_informe_qr_sessions
                (token,document_uuid,document_version,patient_id,doctor_id,actor_user_id,role,signer_authority,
                signer_name,signer_role_label,fingerprint_version,content_fingerprint,review_html,review_html_sha256,
                status,created_at,expires_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,'pending',UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 900 SECOND))");
            $stmt->execute([$token, $uuid, $version, $document['patient_id'], $doctor['doctor_id'],
                $doctor['user_id'], $role, $authority, $name, $label, 1,
                clinical_informe_binding_fingerprint($documentBody), $html, hash('sha256', $html)]);
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
        $path = '/public/note-capture.html?mode=informe-signature&token=' . rawurlencode($token);
        clinical_send_response(['ok' => true, 'data' => ['token' => $token, 'status' => 'pending',
            'mobile_url' => $path, 'qr_value' => $path, 'expires_at' => gmdate('c', time() + 900)]], 201);
        return;
    }
    $token = trim(rawurldecode((string)($segments[1] ?? '')));
    $session = clinical_informe_qr_row($pdo, $token);
    if ($session === null) {
        clinical_send_response(['ok' => false, 'error' => 'not_found'], 404);
        return;
    }
    if ($desktop && ((string)$session['doctor_id'] !== (string)$doctor['doctor_id']
        || (string)$session['actor_user_id'] !== (string)$doctor['user_id']
        || !clinical_has_active_doctor_patient_link($pdo, (string)$doctor['doctor_id'], (string)$session['patient_id']))) {
        clinical_send_response(['ok' => false, 'error' => 'forbidden'], 403);
        return;
    }
    if ($method === 'GET' && $action === 'status') {
        clinical_send_response(['ok' => true, 'data' => clinical_informe_qr_status($pdo, $session, true)], 200);
        return;
    }
    if ($method === 'GET' && $action === 'mobile-context') {
        $status = clinical_informe_qr_status($pdo, $session)['status'];
        if ($status !== 'pending') {
            clinical_send_response(['ok' => false, 'error' => 'INFORME_QR_UNAVAILABLE',
                'message' => 'Este código expiró o el informe cambió. Solicita uno nuevo.'], 409);
            return;
        }
        header('Cache-Control: no-store');
        clinical_send_response(['ok' => true, 'data' => [
            'status' => 'pending', 'role_label' => $session['signer_role_label'],
            'signer_name' => $session['signer_name'], 'review_html' => $session['review_html']]], 200);
        return;
    }
    if ($method === 'POST' && in_array($action, ['cancel', 'invalidate'], true)) {
        $newStatus = $action === 'invalidate' ? 'stale' : 'cancelled';
        $stmt = $pdo->prepare("UPDATE clinical_informe_qr_sessions SET status=?,invalidated_at=UTC_TIMESTAMP()
            WHERE token=? AND status IN ('pending','uploaded')");
        $stmt->execute([$newStatus, $token]);
        clinical_send_response(['ok' => true, 'data' => [$action => $stmt->rowCount() === 1]], 200);
        return;
    }
    if ($method === 'POST' && in_array($action, ['review', 'signature'], true)) {
        $pdo->beginTransaction();
        try {
            $locked = clinical_informe_qr_row($pdo, $token, true);
            $status = is_array($locked) ? clinical_informe_qr_status($pdo, $locked)['status'] : 'stale';
            if ($status !== 'pending') {
                $pdo->rollBack();
                clinical_send_response(['ok' => false, 'error' => 'INFORME_QR_STALE',
                    'message' => $status === 'expired' ? 'El código expiró. Genera uno nuevo.'
                        : 'El informe cambió. Genera un nuevo código para firmar la versión actual.'],
                    $status === 'expired' ? 410 : 409);
                return;
            }
            if ($action === 'review') {
                $stmt = $pdo->prepare('UPDATE clinical_informe_qr_sessions SET reviewed_at=COALESCE(reviewed_at,UTC_TIMESTAMP()) WHERE id=?');
                $stmt->execute([(int)$locked['id']]);
                $pdo->commit();
                clinical_send_response(['ok' => true, 'data' => ['reviewed' => true]], 200);
                return;
            }
            if ($locked['reviewed_at'] === null) {
                $pdo->rollBack();
                clinical_send_response(['ok' => false, 'error' => 'INFORME_QR_REVIEW_REQUIRED',
                    'message' => 'Revisa el informe antes de firmar.'], 409);
                return;
            }
            $request = clinical_read_json_body();
            $data = is_array($request['data'] ?? null) ? $request['data'] : [];
            $image = trim((string)($data['signature_data'] ?? ''));
            $digest = strlen($image) <= 2_000_000 ? clinical_consent_binding_artifact_digest($image) : '';
            if (($request['ok'] ?? false) !== true || $digest === '') {
                $pdo->rollBack();
                clinical_send_response(['ok' => false, 'error' => 'INFORME_QR_SIGNATURE_INVALID',
                    'message' => 'Dibuja una firma legible antes de confirmar.'], 422);
                return;
            }
            $stmt = $pdo->prepare("UPDATE clinical_informe_qr_sessions SET status='uploaded',uploaded_at=UTC_TIMESTAMP(),
                signature_image_data=?,artifact_digest=? WHERE id=? AND status='pending' AND expires_at>UTC_TIMESTAMP()");
            $stmt->execute([$image, $digest, (int)$locked['id']]);
            if ($stmt->rowCount() !== 1) throw new RuntimeException('INFORME_QR_REPLAY');
            $pdo->commit();
            clinical_send_response(['ok' => true, 'data' => ['received' => true]], 200);
            return;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }
    clinical_send_response(['ok' => false, 'error' => 'not_found'], 404);
}
