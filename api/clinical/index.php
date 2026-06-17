<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../modules/clinical/src/timeline_catalog.php';

function clinical_normalize_response($response): array
{
    if (!is_array($response)) {
        $response = [];
    }

    $defaults = [
        'ok' => false,
        'error' => null,
        'message' => '',
        'data' => null,
        'meta' => (object)[],
    ];

    $response = array_merge($defaults, $response);

    if (!is_bool($response['ok'])) {
        $response['ok'] = ($response['error'] === null);
    }

    if ($response['ok'] === true) {
        $response['error'] = null;
        if (!is_string($response['message']) || $response['message'] === '') {
            $response['message'] = 'ok';
        }
    } else {
        if (is_array($response['error'])) {
            $errorCode = trim((string)($response['error']['code'] ?? ''));
            $errorMessage = trim((string)($response['error']['message'] ?? ''));
            if ($errorCode === '') {
                $errorCode = 'server_error';
            }
            if ($errorMessage === '') {
                $errorMessage = ($errorCode === 'not_found') ? 'route not found' : 'server error';
            }
            $response['error'] = [
                'code' => $errorCode,
                'message' => $errorMessage,
            ];
            if (!is_string($response['message']) || $response['message'] === '') {
                $response['message'] = $errorMessage;
            }
        } else {
            if (!is_string($response['error']) || $response['error'] === '') {
                $response['error'] = 'server_error';
            }
            if (!is_string($response['message']) || $response['message'] === '') {
                $response['message'] = ($response['error'] === 'not_found') ? 'route not found' : 'server error';
            }
        }
    }

    if (!array_key_exists('meta', $response) || $response['meta'] === null) {
        $response['meta'] = (object)[];
    } elseif (is_array($response['meta'])) {
        $response['meta'] = (object)$response['meta'];
    } elseif (!is_object($response['meta'])) {
        $response['meta'] = (object)[];
    }

    return $response;
}

function clinical_send_response($response, ?int $status = null): void
{
    $response = clinical_normalize_response($response);

    if ($status === null) {
        if (($response['error'] ?? null) === 'not_found') {
            $status = 404;
        } elseif (($response['error'] ?? null) === 'server_error') {
            $status = 500;
        } else {
            $status = ($response['ok'] === true) ? 200 : 500;
        }
    }

    http_response_code($status);

    $json = json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        http_response_code(500);
        echo '{"ok":false,"error":"server_error","message":"json_encode failed","data":null,"meta":{}}';
        return;
    }

    echo $json;
}

class ClinicalDocumentsPatientWriteException extends RuntimeException
{
    private $statusCode;
    private $errorCode;

    public function __construct(string $errorCode, string $message, int $statusCode)
    {
        parent::__construct($message);
        $this->errorCode = $errorCode;
        $this->statusCode = $statusCode;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }
}

function clinical_documents_send_patient_write_error(ClinicalDocumentsPatientWriteException $e, array $meta): void
{
    clinical_send_response([
        'ok' => false,
        'error' => $e->errorCode(),
        'message' => $e->getMessage(),
        'data' => null,
        'meta' => $meta,
    ], $e->statusCode());
}

function clinical_route_segments(): array
{
    $routeFromQuery = trim((string)($_GET['route'] ?? $_GET['path'] ?? ''), '/');
    if ($routeFromQuery !== '') {
        return array_values(array_filter(explode('/', $routeFromQuery), static function ($value) {
            return $value !== '';
        }));
    }

    $uriPath = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
    $scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '');

    $relative = $uriPath;
    if ($scriptName !== '' && strpos($uriPath, $scriptName) === 0) {
        $relative = (string)substr($uriPath, strlen($scriptName));
    } elseif (strpos($uriPath, '/api/clinical/index.php') === 0) {
        $relative = (string)substr($uriPath, strlen('/api/clinical/index.php'));
    } elseif (strpos($uriPath, '/api/clinical') === 0) {
        $relative = (string)substr($uriPath, strlen('/api/clinical'));
    }

    $relative = trim($relative, '/');
    if ($relative === '') {
        return [];
    }

    $segments = array_values(array_filter(explode('/', $relative), static function ($value) {
        return $value !== '';
    }));

    if (($segments[0] ?? '') === 'index.php') {
        array_shift($segments);
    }

    return array_values($segments);
}

function clinical_read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false) {
        return [
            'ok' => false,
            'error' => 'unable to read request body',
            'data' => null,
        ];
    }

    if (trim($raw) === '') {
        return [
            'ok' => false,
            'error' => 'request body must be valid json',
            'data' => null,
        ];
    }

    $decoded = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        return [
            'ok' => false,
            'error' => 'invalid json body',
            'data' => null,
        ];
    }

    return [
        'ok' => true,
        'error' => null,
        'data' => $decoded,
    ];
}

function clinical_documents_read_create_request(): array
{
    $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? ''));
    $isMultipart = (!empty($_FILES) || strpos($contentType, 'multipart/form-data') !== false);
    $payload = [];
    $uploadFile = null;

    if ($isMultipart) {
        $payload = is_array($_POST) ? $_POST : [];
        if (isset($payload['payload']) && is_string($payload['payload'])) {
            $payloadDecoded = json_decode((string)$payload['payload'], true);
            if (is_array($payloadDecoded)) {
                $payload['payload'] = $payloadDecoded;
            }
        }
        if (isset($payload['context']) && is_string($payload['context'])) {
            $contextDecoded = json_decode((string)$payload['context'], true);
            if (is_array($contextDecoded)) {
                $payload['context'] = $contextDecoded;
            }
        }

        $uploadCandidate = $_FILES['file'] ?? null;
        if (is_array($uploadCandidate) && (($uploadCandidate['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE)) {
            $uploadFile = $uploadCandidate;
            $uploadError = (int)($uploadFile['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($uploadError !== UPLOAD_ERR_OK) {
                throw new InvalidArgumentException('upload inválido');
            }
        }

        return [
            'payload' => $payload,
            'upload_file' => $uploadFile,
            'is_multipart' => true,
        ];
    }

    $bodyResult = clinical_read_json_body();
    if ($bodyResult['ok'] !== true) {
        throw new InvalidArgumentException((string)$bodyResult['error']);
    }

    return [
        'payload' => is_array($bodyResult['data'] ?? null) ? (array)$bodyResult['data'] : [],
        'upload_file' => null,
        'is_multipart' => false,
    ];
}

function clinical_documents_collect_patient_id_candidate(array &$values, $candidate): void
{
    if ($candidate === null) {
        return;
    }
    if (!is_scalar($candidate)) {
        $values[] = '__invalid_patient_id__';
        return;
    }

    $value = trim((string)$candidate);
    if ($value !== '') {
        $values[] = $value;
    }
}

function clinical_documents_request_patient_id_values(array $payload): array
{
    $values = [];
    clinical_documents_collect_patient_id_candidate($values, $payload['patient_id'] ?? null);

    $context = is_array($payload['context'] ?? null) ? $payload['context'] : [];
    clinical_documents_collect_patient_id_candidate($values, $context['patient_id'] ?? null);

    $documentPayload = is_array($payload['payload'] ?? null) ? $payload['payload'] : [];
    clinical_documents_collect_patient_id_candidate($values, $documentPayload['patient_id'] ?? null);

    $nestedContext = is_array($documentPayload['context'] ?? null) ? $documentPayload['context'] : [];
    clinical_documents_collect_patient_id_candidate($values, $nestedContext['patient_id'] ?? null);

    return array_values(array_unique($values));
}

function clinical_documents_request_has_patient_mismatch(array $payload, string $routePatientId): bool
{
    $safeRoutePatientId = trim($routePatientId);
    foreach (clinical_documents_request_patient_id_values($payload) as $candidate) {
        if ($candidate !== $safeRoutePatientId) {
            return true;
        }
    }
    return false;
}

function clinical_documents_force_request_patient_id(array $payload, string $patientId): array
{
    $safePatientId = trim($patientId);
    $payload['patient_id'] = $safePatientId;
    $context = is_array($payload['context'] ?? null) ? $payload['context'] : [];
    $context['patient_id'] = $safePatientId;
    $payload['context'] = $context;
    return $payload;
}

function clinical_documents_save_create_request(PDO $pdo, array $payload, ?array $uploadFile, bool $isMultipart, bool $requireCanonicalPatient = false): array
{
    $looksLikeUploadDocument = $isMultipart && (
        is_array($uploadFile)
        || trim((string)($payload['document_type'] ?? '')) !== ''
    );

    if ($looksLikeUploadDocument) {
        return clinical_documents_gateway_save_upload($pdo, $payload, $uploadFile, $requireCanonicalPatient);
    }

    return clinical_documents_save_passthrough($pdo, $payload, $requireCanonicalPatient);
}

function clinical_request_actor_user_id(?array $payload = null): string
{
    $payload = is_array($payload) ? $payload : [];
    $actor = is_array($payload['actor'] ?? null) ? $payload['actor'] : [];
    $candidates = [
        $actor['user_id'] ?? null,
        $_SERVER['HTTP_X_USER_ID'] ?? null,
        $_SESSION['user_id'] ?? null,
        $_SERVER['PHP_AUTH_USER'] ?? null,
    ];
    foreach ($candidates as $candidate) {
        $value = trim((string)($candidate ?? ''));
        if ($value !== '') {
            return $value;
        }
    }
    return 'qa';
}

function clinical_request_actor_user_id_strict(?array $payload = null): string
{
    $payload = is_array($payload) ? $payload : [];
    $actor = is_array($payload['actor'] ?? null) ? $payload['actor'] : [];
    $candidates = [
        $actor['user_id'] ?? null,
        $_SERVER['HTTP_X_USER_ID'] ?? null,
        $_SESSION['user_id'] ?? null,
        $_SERVER['PHP_AUTH_USER'] ?? null,
    ];
    foreach ($candidates as $candidate) {
        $value = trim((string)($candidate ?? ''));
        if ($value !== '') {
            return $value;
        }
    }
    return '';
}

function clinical_has_active_doctor_patient_link(PDO $pdo, string $doctorId, string $patientId): bool
{
    $safeDoctorId = trim($doctorId);
    $safePatientId = trim($patientId);
    if ($safeDoctorId === '' || $safePatientId === '') {
        return false;
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM patients_doctor_links
        WHERE doctor_id = :doctor_id
          AND patient_id = :patient_id
          AND status = 'active'
    ");
    $stmt->execute([
        ':doctor_id' => $safeDoctorId,
        ':patient_id' => $safePatientId,
    ]);
    return (int)$stmt->fetchColumn() > 0;
}

function clinical_is_local_host(): bool
{
    $host = strtolower(trim((string)($_SERVER['HTTP_HOST'] ?? '')));
    if ($host === '') {
        return false;
    }
    return (strpos($host, '127.0.0.1') !== false) || (strpos($host, 'localhost') !== false);
}

function clinical_build_tag(): string
{
    $build = trim((string)(getenv('MXMED_BUILD') ?: ''));
    if ($build === '') {
        $build = clinical_is_local_host() ? 'dev' : 'prod';
    }
    return strtolower($build);
}

function clinical_is_local_or_dev(): bool
{
    return clinical_is_local_host() || clinical_build_tag() === 'dev';
}

function clinical_cors_allowed_origin(?string $origin): ?string
{
    $origin = trim((string)$origin);
    if ($origin === '') {
        return null;
    }

    $allowlist = [
        'http://127.0.0.1:8092',
        'http://localhost:8092',
    ];
    if (in_array($origin, $allowlist, true)) {
        return $origin;
    }

    if (!clinical_is_local_or_dev()) {
        return null;
    }

    $parts = parse_url($origin);
    if (!is_array($parts)) {
        return null;
    }
    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    $host = strtolower((string)($parts['host'] ?? ''));
    $port = (int)($parts['port'] ?? 0);
    if ($scheme !== 'http' && $scheme !== 'https') {
        return null;
    }
    if (($host !== '127.0.0.1' && $host !== 'localhost') || $port <= 0) {
        return null;
    }
    return $origin;
}

function clinical_apply_cors_headers(?string $origin): void
{
    $allowedOrigin = clinical_cors_allowed_origin($origin);
    if ($allowedOrigin === null) {
        return;
    }

    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
    header('Vary: Origin');
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Accept, Authorization, X-Requested-With, X-User-Id');
    header('Access-Control-Max-Age: 600');
}

function clinical_debug_enabled(): bool
{
    return trim((string)getenv('MXMED_DEBUG')) === '1';
}

function clinical_ensure_identity_bridge_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS clinical_patient_identity_bridge (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            legacy_patient_id VARCHAR(128) NOT NULL,
            canonical_patient_id VARCHAR(64) NOT NULL,
            strategy VARCHAR(50) NOT NULL,
            confidence DECIMAL(3,2) NOT NULL DEFAULT 1.00,
            created_at DATETIME NOT NULL,
            UNIQUE KEY uniq_legacy (legacy_patient_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
}

function clinical_is_uuid_v4(string $value): bool
{
    return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value) === 1;
}

function clinical_is_canonical_patient_id_pattern(string $value): bool
{
    // Pattern generated in modules/patients (PatientsRepository::generateId with prefix p_).
    if (preg_match('/^p_[a-f0-9]{12}$/i', $value) === 1) {
        return true;
    }

    // Tolerant canonical prefix for existing IDs in environments with prior formats.
    return preg_match('/^p_[A-Za-z0-9_-]{6,62}$/', $value) === 1;
}

function clinical_has_multiple_legacy_tokens(string $value): bool
{
    $parts = preg_split('/[\|,;\/\s]+/', trim($value), -1, PREG_SPLIT_NO_EMPTY);
    if (!is_array($parts)) {
        return false;
    }
    return count($parts) >= 3;
}

function clinical_inspect_patient_id_kind(string $patientId): array
{
    $notes = [];

    if (clinical_is_uuid_v4($patientId)) {
        $notes[] = 'matches UUID v4 format';
        return ['kind' => 'canonical', 'notes' => $notes];
    }

    if (clinical_is_canonical_patient_id_pattern($patientId)) {
        $notes[] = 'matches canonical patients pattern (prefix p_)';
        return ['kind' => 'canonical', 'notes' => $notes];
    }

    if (strpos($patientId, '|') !== false) {
        $notes[] = 'contains legacy separator "|"';
    }
    if (strlen($patientId) > 64) {
        $notes[] = 'length greater than 64 characters';
    }
    if (clinical_has_multiple_legacy_tokens($patientId)) {
        $notes[] = 'contains multiple separated tokens';
    }

    if (count($notes) > 0) {
        return ['kind' => 'legacy', 'notes' => $notes];
    }

    return [
        'kind' => 'unknown',
        'notes' => ['no canonical or legacy heuristic matched'],
    ];
}

function clinical_documents_validate_canonical_patient_id_for_write(PDO $pdo, $patientId): string
{
    if (!is_scalar($patientId)) {
        throw new ClinicalDocumentsPatientWriteException('invalid_params', 'patient_id must be canonical', 400);
    }

    $safePatientId = trim((string)$patientId);
    if ($safePatientId === '') {
        throw new ClinicalDocumentsPatientWriteException('invalid_params', 'patient_id must be canonical', 400);
    }

    $kind = clinical_inspect_patient_id_kind($safePatientId);
    if (($kind['kind'] ?? '') !== 'canonical') {
        throw new ClinicalDocumentsPatientWriteException('invalid_params', 'patient_id must be canonical', 400);
    }

    if (!clinical_patient_exists($pdo, $safePatientId)) {
        throw new ClinicalDocumentsPatientWriteException('not_found', 'patient not found', 404);
    }

    return $safePatientId;
}

function clinical_identity_bridge_admin_meta(string $method, string $route): array
{
    return [
        'method' => $method,
        'route' => $route,
        'bridge' => 'clinical_patient_identity_bridge',
        'admin' => true,
    ];
}

function clinical_documents_pdo(): PDO
{
    require_once __DIR__ . '/../_lib/db.php';
    return mxmed_pdo();
}

function clinical_note_capture_tokens_ensure_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS clinical_note_capture_tokens (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            token VARCHAR(80) NOT NULL,
            patient_id VARCHAR(128) NOT NULL,
            encounter_key VARCHAR(191) DEFAULT NULL,
            note_context VARCHAR(80) NOT NULL DEFAULT 'nota_clinica_modal',
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            expires_at DATETIME NOT NULL,
            uploaded_at DATETIME DEFAULT NULL,
            consumed_at DATETIME DEFAULT NULL,
            cancelled_at DATETIME DEFAULT NULL,
            document_id INT DEFAULT NULL,
            document_uuid VARCHAR(64) DEFAULT NULL,
            note_document_id INT DEFAULT NULL,
            note_document_uuid VARCHAR(64) DEFAULT NULL,
            preview_url VARCHAR(600) DEFAULT NULL,
            signature_image_data MEDIUMTEXT DEFAULT NULL,
            signature_signer_name VARCHAR(191) DEFAULT NULL,
            signature_signed_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uniq_note_capture_token (token),
            KEY idx_note_capture_patient (patient_id),
            KEY idx_note_capture_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    try {
        $pdo->exec("ALTER TABLE clinical_note_capture_tokens ADD COLUMN consumed_at DATETIME DEFAULT NULL");
    } catch (Throwable $e) {
    }
    try {
        $pdo->exec("ALTER TABLE clinical_note_capture_tokens ADD COLUMN note_document_id INT DEFAULT NULL");
    } catch (Throwable $e) {
    }
    try {
        $pdo->exec("ALTER TABLE clinical_note_capture_tokens ADD COLUMN note_document_uuid VARCHAR(64) DEFAULT NULL");
    } catch (Throwable $e) {
    }
    try {
        $pdo->exec("ALTER TABLE clinical_note_capture_tokens ADD COLUMN signature_image_data MEDIUMTEXT DEFAULT NULL");
    } catch (Throwable $e) {
    }
    try {
        $pdo->exec("ALTER TABLE clinical_note_capture_tokens ADD COLUMN signature_signer_name VARCHAR(191) DEFAULT NULL");
    } catch (Throwable $e) {
    }
    try {
        $pdo->exec("ALTER TABLE clinical_note_capture_tokens ADD COLUMN signature_signed_at DATETIME DEFAULT NULL");
    } catch (Throwable $e) {
    }
}

function clinical_note_capture_datetime_to_iso(?string $value): ?string
{
    $raw = trim((string)$value);
    if ($raw === '') {
        return null;
    }
    $ts = strtotime($raw . ' UTC');
    if ($ts === false) {
        $ts = strtotime($raw);
    }
    if ($ts === false) {
        return null;
    }
    return gmdate('c', $ts);
}

function clinical_note_capture_normalize_url(string $value): string
{
    $raw = trim($value);
    if ($raw === '') {
        return '';
    }
    if (preg_match('/^data:/i', $raw) === 1) {
        return $raw;
    }
    if (preg_match('/^https?:\/\//i', $raw) === 1) {
        return $raw;
    }
    if (strpos($raw, '/') === 0) {
        return $raw;
    }
    return '/' . ltrim($raw, '/');
}

function clinical_note_capture_token_generate(): string
{
    try {
        return bin2hex(random_bytes(16));
    } catch (Throwable $e) {
        return sha1(uniqid('note_capture_', true));
    }
}

function clinical_note_capture_token_fetch(PDO $pdo, string $token): ?array
{
    $safeToken = trim($token);
    if ($safeToken === '') {
        return null;
    }
    $stmt = $pdo->prepare("
        SELECT *
        FROM clinical_note_capture_tokens
        WHERE token = :token
        LIMIT 1
    ");
    $stmt->bindValue(':token', $safeToken, PDO::PARAM_STR);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function clinical_note_capture_mark_expired_if_needed(PDO $pdo, array $row): array
{
    $status = strtolower(trim((string)($row['status'] ?? '')));
    $expiresAt = trim((string)($row['expires_at'] ?? ''));
    if ($status !== 'pending' || $expiresAt === '') {
        return $row;
    }
    $expiresTs = strtotime($expiresAt . ' UTC');
    if ($expiresTs === false) {
        $expiresTs = strtotime($expiresAt);
    }
    if ($expiresTs === false || $expiresTs > time()) {
        return $row;
    }
    $token = trim((string)($row['token'] ?? ''));
    if ($token !== '') {
        $stmt = $pdo->prepare("
            UPDATE clinical_note_capture_tokens
            SET status = 'expired', updated_at = :updated_at
            WHERE token = :token
        ");
        $stmt->bindValue(':updated_at', gmdate('Y-m-d H:i:s'), PDO::PARAM_STR);
        $stmt->bindValue(':token', $token, PDO::PARAM_STR);
        $stmt->execute();
    }
    $row['status'] = 'expired';
    return $row;
}

function clinical_note_capture_extract_preview_url(array $document): string
{
    $payload = is_array($document['content']['payload'] ?? null) ? $document['content']['payload'] : [];
    $file = is_array($payload['file'] ?? null) ? $payload['file'] : [];
    $candidates = [
        $file['optimized']['url'] ?? null,
        $file['optimized']['path'] ?? null,
        $file['original']['url'] ?? null,
        $file['original']['path'] ?? null,
        $file['thumb']['url'] ?? null,
        $file['thumb']['path'] ?? null,
        $file['url'] ?? null,
        $file['path'] ?? null,
    ];
    foreach ($candidates as $candidate) {
        $raw = trim((string)($candidate ?? ''));
        if ($raw !== '') {
            return clinical_note_capture_normalize_url($raw);
        }
    }
    return '';
}

function clinical_note_capture_status_data(array $row): array
{
    $status = strtolower(trim((string)($row['status'] ?? 'pending')));
    if ($status === '') {
        $status = 'pending';
    }
    $data = [
        'token' => trim((string)($row['token'] ?? '')),
        'status' => $status,
        'expires_at' => clinical_note_capture_datetime_to_iso((string)($row['expires_at'] ?? '')),
        'note_context' => trim((string)($row['note_context'] ?? '')),
    ];
    if ($status === 'uploaded' || $status === 'consumed') {
        $data['uploaded_at'] = clinical_note_capture_datetime_to_iso((string)($row['uploaded_at'] ?? ''));
        $documentId = (int)($row['document_id'] ?? 0);
        $data['document_id'] = ($documentId > 0) ? $documentId : null;
        $data['document_uuid'] = trim((string)($row['document_uuid'] ?? ''));
        $data['preview_url'] = clinical_note_capture_normalize_url((string)($row['preview_url'] ?? ''));
    }
    if ($status === 'consumed') {
        $data['consumed_at'] = clinical_note_capture_datetime_to_iso((string)($row['consumed_at'] ?? ''));
        $noteDocumentId = (int)($row['note_document_id'] ?? 0);
        $data['note_document_id'] = ($noteDocumentId > 0) ? $noteDocumentId : null;
        $data['note_document_uuid'] = trim((string)($row['note_document_uuid'] ?? ''));
    }
    $signatureImageData = trim((string)($row['signature_image_data'] ?? ''));
    if ($signatureImageData !== '') {
        $data['signature'] = [
            'type' => 'drawn',
            'source' => 'remote_qr',
            'role' => 'patient_or_representative',
            'image_data' => $signatureImageData,
            'signer_name' => trim((string)($row['signature_signer_name'] ?? '')),
            'signed_at' => clinical_note_capture_datetime_to_iso((string)($row['signature_signed_at'] ?? ($row['uploaded_at'] ?? ''))),
        ];
    }
    return $data;
}

function clinical_documents_list_fetch(PDO $pdo, string $patientId, string $documentType, string $hospitalStayId, int $limit): array
{
    $sql = "
        SELECT
            id,
            document_uuid,
            title,
            document_type,
            summary,
            event_datetime,
            printable,
            payload_json,
            JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.snapshot.medico.nombre_completo')) AS doctor_name
        FROM clinical_documents
        WHERE patient_id = :patient_id
    ";
    $params = [':patient_id' => $patientId];
    if ($documentType !== '') {
        $sql .= " AND document_type = :type";
        $params[':type'] = $documentType;
    }
    if ($hospitalStayId !== '') {
        $sql .= " AND hospital_stay_id = :hospital_stay_id";
        $params[':hospital_stay_id'] = $hospitalStayId;
    }
    $sql .= " ORDER BY event_datetime DESC, id DESC LIMIT {$limit}";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll();
    return is_array($items) ? $items : [];
}

function clinical_documents_get_fetch(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM clinical_documents WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!$row || !is_array($row)) {
        return null;
    }

    $pstmt = $pdo->prepare("SELECT user_id, role, participation_type, signed_at FROM clinical_document_participants WHERE clinical_document_id = :id ORDER BY id ASC");
    $pstmt->execute([':id' => $id]);
    $participants = $pstmt->fetchAll();
    if (!is_array($participants)) {
        $participants = [];
    }

    $payload = json_decode((string)$row['payload_json'], true);
    if (!is_array($payload)) {
        $payload = [];
    }

    return [
        'document_db_id' => (int)$row['id'],
        'document_id' => $row['document_uuid'],
        'document_type' => $row['document_type'],
        'title' => $row['title'],
        'version' => (int)$row['version'],
        'context' => [
            'patient_id' => $row['patient_id'],
            'encounter_id' => $row['encounter_id'],
            'hospital_stay_id' => $row['hospital_stay_id'],
            'care_setting' => $row['care_setting'],
            'service' => $row['service'],
        ],
        'status' => $row['status'],
        'timestamps' => [
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'generated_at' => $row['generated_at'],
            'signed_at' => $row['signed_at'],
        ],
        'audit' => [
            'created_by_user_id' => $row['created_by_user_id'],
            'updated_by_user_id' => $row['updated_by_user_id'],
        ],
        'participants' => $participants,
        'content' => [
            'payload' => $payload,
            'rendered_text' => $row['rendered_text'],
            'summary' => $row['summary'],
            'edited_flag' => (int)$row['edited_flag'],
        ],
        'ui' => [
            'event_datetime' => $row['event_datetime'],
            'widget_group' => $row['widget_group'],
            'printable' => (bool)$row['printable'],
        ],
    ];
}

function clinical_documents_get_by_uuid_fetch(PDO $pdo, string $uuid): ?array
{
    $stmt = $pdo->prepare("SELECT id FROM clinical_documents WHERE document_uuid = :uuid LIMIT 1");
    $stmt->execute([':uuid' => $uuid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || !is_array($row)) {
        return null;
    }

    $id = (int)($row['id'] ?? 0);
    if ($id <= 0) {
        return null;
    }

    return clinical_documents_get_fetch($pdo, $id);
}

function clinical_documents_get_by_token_fetch(PDO $pdo, string $token): ?array
{
    $safe = trim((string)$token);
    if ($safe === '') {
        return null;
    }
    if (preg_match('/^\d+$/', $safe) === 1) {
        return clinical_documents_get_fetch($pdo, (int)$safe);
    }
    return clinical_documents_get_by_uuid_fetch($pdo, $safe);
}

function clinical_sanitize_rendered_text_html(string $raw): string
{
    $html = trim($raw);
    if ($html === '') {
        return '';
    }

    $html = (string)preg_replace('#<\s*(script|style)\b[^>]*>.*?<\s*/\s*\1\s*>#is', '', $html);
    $html = strip_tags($html, '<p><br><strong><em>');
    $html = (string)preg_replace('/<(p|strong|em)\b[^>]*>/i', '<$1>', $html);
    $html = (string)preg_replace('/<br\s*\/?>/i', '<br>', $html);
    $html = trim($html);

    if ($html === '') {
        return '';
    }

    return $html;
}

function clinical_document_extract_related_order_refs(array $payload): array
{
    $candidates = [
        $payload['related_order_document_id'] ?? null,
        $payload['related_order_document_uuid'] ?? null,
        $payload['related_document_id'] ?? null,
        $payload['related_document_uuid'] ?? null,
        $payload['related_order_id'] ?? null,
        is_array($payload['context'] ?? null) ? ($payload['context']['related_order_document_id'] ?? null) : null,
        is_array($payload['context'] ?? null) ? ($payload['context']['related_order_document_uuid'] ?? null) : null,
        is_array($payload['context'] ?? null) ? ($payload['context']['related_document_id'] ?? null) : null,
        is_array($payload['context'] ?? null) ? ($payload['context']['related_document_uuid'] ?? null) : null,
    ];
    $out = [];
    foreach ($candidates as $candidate) {
        $value = trim((string)($candidate ?? ''));
        if ($value === '') {
            continue;
        }
        if (!in_array($value, $out, true)) {
            $out[] = $value;
        }
    }
    return $out;
}

function clinical_document_has_linked_result(PDO $pdo, string $patientId, string $orderId, string $orderUuid): bool
{
    $patientId = trim($patientId);
    if ($patientId === '') {
        return false;
    }
    $stmt = $pdo->prepare("
        SELECT id, payload_json
        FROM clinical_documents
        WHERE patient_id = :patient_id
          AND document_type IN ('lab_result', 'imaging_result', 'result', 'lab_pdf')
        ORDER BY id DESC
        LIMIT 300
    ");
    $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_STR);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!is_array($rows)) {
        return false;
    }
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $payload = json_decode((string)($row['payload_json'] ?? ''), true);
        if (!is_array($payload)) {
            $payload = [];
        }
        $refs = clinical_document_extract_related_order_refs($payload);
        if ($orderId !== '' && in_array($orderId, $refs, true)) {
            return true;
        }
        if ($orderUuid !== '' && in_array($orderUuid, $refs, true)) {
            return true;
        }
    }
    return false;
}

function clinical_encounter_documents_direct_fetch(PDO $pdo, string $patientId, int $encounterId, string $orderDir = 'ASC'): array
{
    if ($encounterId <= 0 || $patientId === '') {
        return [];
    }
    $dir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';
    $stmt = $pdo->prepare("
        SELECT document_uuid, document_type, title, event_datetime, summary, patient_id, appointment_id, payload_json, hospital_stay_id
        FROM clinical_documents
        WHERE patient_id = :patient_id
          AND encounter_id = :encounter_id
        ORDER BY event_datetime {$dir}, document_uuid {$dir}
    ");
    $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_STR);
    $stmt->bindValue(':encounter_id', (string)$encounterId, PDO::PARAM_STR);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return is_array($rows) ? $rows : [];
}

function clinical_encounter_documents_legacy_by_appointment_fetch(PDO $pdo, string $patientId, string $appointmentId, string $orderDir = 'ASC'): array
{
    if ($appointmentId === '' || $patientId === '') {
        return [];
    }
    $dir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';
    $stmt = $pdo->prepare("
        SELECT document_uuid, document_type, title, event_datetime, summary, patient_id, appointment_id, payload_json, hospital_stay_id
        FROM clinical_documents
        WHERE patient_id = :patient_id
          AND appointment_id = :appointment_id
          AND (encounter_id IS NULL OR TRIM(encounter_id) = '')
        ORDER BY event_datetime {$dir}, document_uuid {$dir}
    ");
    $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_STR);
    $stmt->bindValue(':appointment_id', $appointmentId, PDO::PARAM_STR);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return is_array($rows) ? $rows : [];
}

function clinical_encounter_auto_note_type_label(string $documentType): string
{
    switch (strtolower(trim($documentType))) {
        case 'procedure':
            return 'Procedimiento';
        case 'immunization':
            return 'Vacunación';
        case 'medication_administration':
            return 'Aplicación de medicamento';
        case 'wound_care':
            return 'Curación';
        case 'prescription':
        case 'rx':
            return 'Receta';
        case 'orders':
        case 'order':
        case 'lab_order':
        case 'imaging_order':
            return 'Orden';
        case 'results':
        case 'result':
        case 'lab_result':
        case 'imaging_result':
            return 'Resultado';
        case 'vitals':
        case 'vital_signs':
        case 'signs':
            return 'Signos vitales';
        case 'image':
            return 'Imagen clínica';
        case 'bundle_clinical':
            return 'Interpretación del estudio';
        case 'note':
        case 'medical_note':
        case 'evolution_note':
            return 'Nota clínica';
        default:
            return 'Documento clínico';
    }
}

function clinical_encounter_auto_note_matches_row(array $row): bool
{
    if (strtolower(trim((string)($row['document_type'] ?? ''))) !== 'note') {
        return false;
    }
    $payload = json_decode((string)($row['payload_json'] ?? ''), true);
    if (!is_array($payload)) {
        return false;
    }
    return trim((string)($payload['snapshot_type'] ?? '')) === 'encounter_auto';
}

function clinical_encounter_auto_note_filter_documents(array $rows): array
{
    $filtered = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        if (clinical_encounter_auto_note_matches_row($row)) {
            continue;
        }
        $filtered[] = $row;
    }
    return $filtered;
}

function clinical_encounter_document_buckets(array $rows): array
{
    $buckets = [
        'documents' => [],
        'vitals' => [],
        'notes' => [],
        'prescriptions' => [],
        'orders' => [],
        'results' => [],
        'procedures' => [],
        'images' => [],
    ];

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $type = strtolower(trim((string)($row['document_type'] ?? '')));
        $buckets['documents'][] = $row;
        if (in_array($type, ['vitals', 'vital_signs', 'signs'], true)) {
            $buckets['vitals'][] = $row;
            continue;
        }
        if (in_array($type, ['note', 'medical_note', 'evolution_note'], true)) {
            $buckets['notes'][] = $row;
            continue;
        }
        if (in_array($type, ['prescription', 'rx'], true)) {
            $buckets['prescriptions'][] = $row;
            continue;
        }
        if (in_array($type, ['orders', 'order', 'lab_order', 'imaging_order'], true)) {
            $buckets['orders'][] = $row;
            continue;
        }
        if (in_array($type, ['results', 'result', 'lab_result', 'imaging_result'], true)) {
            $buckets['results'][] = $row;
            continue;
        }
        if (in_array($type, ['procedure', 'immunization', 'medication_administration', 'wound_care'], true)) {
            $buckets['procedures'][] = $row;
            continue;
        }
        if (in_array($type, ['image', 'pdf', 'bundle_clinical'], true)) {
            $buckets['images'][] = $row;
            continue;
        }
    }

    return $buckets;
}

function clinical_encounter_auto_note_rendered_text(
    string $patientId,
    string $encounterKey,
    string $eventDatetime,
    ?string $appointmentId,
    int $encounterId,
    array $snapshotDocuments,
    array $counts
): string {
    $lines = [];
    $lines[] = 'NOTA CLÍNICA AUTO';
    $lines[] = 'Consulta: ' . ($eventDatetime !== '' ? $eventDatetime : gmdate('Y-m-d H:i:s'));
    $lines[] = 'Paciente: ' . $patientId;
    $lines[] = 'Encounter: ' . ($encounterKey !== '' ? $encounterKey : ('enc:' . $encounterId));
    if ($appointmentId !== null && trim($appointmentId) !== '') {
        $lines[] = 'Cita: ' . trim($appointmentId);
    }
    $lines[] = '';
    $lines[] = 'Resumen capturado en esta consulta:';
    $lines[] = '- Documentos: ' . (string)($counts['documents'] ?? 0);
    $lines[] = '- Signos vitales: ' . (string)($counts['vitals'] ?? 0);
    $lines[] = '- Notas clínicas: ' . (string)($counts['notes'] ?? 0);
    $lines[] = '- Recetas: ' . (string)($counts['prescriptions'] ?? 0);
    $lines[] = '- Órdenes: ' . (string)($counts['orders'] ?? 0);
    $lines[] = '- Resultados: ' . (string)($counts['results'] ?? 0);
    $lines[] = '- Procedimientos: ' . (string)($counts['procedures'] ?? 0);
    $lines[] = '- Imágenes / bundles: ' . (string)($counts['images'] ?? 0);
    if ($snapshotDocuments !== []) {
        $lines[] = '';
        $lines[] = 'Elementos capturados:';
        foreach ($snapshotDocuments as $doc) {
            if (!is_array($doc)) {
                continue;
            }
            $label = clinical_encounter_auto_note_type_label((string)($doc['document_type'] ?? ''));
            $title = trim((string)($doc['title'] ?? ''));
            $summary = trim((string)($doc['summary'] ?? ''));
            $parts = [];
            if ($title !== '') {
                $parts[] = $title;
            } else {
                $parts[] = $label;
            }
            if ($summary !== '') {
                $parts[] = $summary;
            }
            $lines[] = '- ' . implode(' · ', $parts);
        }
    }
    return implode("\n", $lines);
}

function clinical_encounter_auto_note_summary(array $counts): string
{
    $parts = [];
    $parts[] = 'Snapshot AUTO de consulta';
    $parts[] = 'documentos ' . (string)($counts['documents'] ?? 0);
    if (($counts['procedures'] ?? 0) > 0) {
        $parts[] = 'procedimientos ' . (string)$counts['procedures'];
    }
    if (($counts['results'] ?? 0) > 0) {
        $parts[] = 'resultados ' . (string)$counts['results'];
    }
    if (($counts['prescriptions'] ?? 0) > 0) {
        $parts[] = 'recetas ' . (string)$counts['prescriptions'];
    }
    return implode(' · ', $parts);
}

function clinical_encounter_auto_note_find_existing(
    PDO $pdo,
    string $patientId,
    string $encounterKey,
    ?string $appointmentId,
    int $encounterId
): ?array {
    $appointmentId = trim((string)$appointmentId);
    $stmt = $pdo->prepare("
        SELECT id, document_uuid, created_at, created_by_user_id
        FROM clinical_documents
        WHERE patient_id = :patient_id
          AND document_type = 'note'
          AND JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.snapshot_type')) = 'encounter_auto'
          AND (
            JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.context.encounter_key')) = :encounter_key
            OR (
              JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.context.encounter_id')) = :encounter_id
              AND JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.context.appointment_id')) = :appointment_id
            )
          )
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_STR);
    $stmt->bindValue(':encounter_key', $encounterKey, PDO::PARAM_STR);
    $stmt->bindValue(':encounter_id', (string)$encounterId, PDO::PARAM_STR);
    $stmt->bindValue(':appointment_id', $appointmentId, PDO::PARAM_STR);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function clinical_encounter_auto_note_upsert(PDO $pdo, array $encounterRow): ?array
{
    $encounterId = (int)($encounterRow['encounter_id'] ?? 0);
    $patientId = trim((string)($encounterRow['patient_id'] ?? ''));
    $appointmentId = trim((string)($encounterRow['appointment_id'] ?? ''));
    $eventDatetime = trim((string)($encounterRow['encounter_dt'] ?? ''));
    if ($encounterId <= 0 || $patientId === '') {
        return null;
    }

    $encounterKey = clinical_encounter_key($encounterId, $appointmentId !== '' ? $appointmentId : null);
    $isLatestByAppointment = false;
    if ($appointmentId !== '') {
        $latest = clinical_encounter_get_latest_by_appointment($pdo, $appointmentId);
        $isLatestByAppointment = ((int)($latest['encounter_id'] ?? 0) === $encounterId);
    }

    $rows = clinical_timeline_encounter_documents_fetch($pdo, $patientId, $encounterId, $appointmentId, $isLatestByAppointment);
    $rows = clinical_encounter_auto_note_filter_documents($rows);
    $buckets = clinical_encounter_document_buckets($rows);
    $counts = [
        'documents' => count($buckets['documents']),
        'vitals' => count($buckets['vitals']),
        'notes' => count($buckets['notes']),
        'prescriptions' => count($buckets['prescriptions']),
        'orders' => count($buckets['orders']),
        'results' => count($buckets['results']),
        'procedures' => count($buckets['procedures']),
        'images' => count($buckets['images']),
    ];

    $snapshotDocuments = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $payload = json_decode((string)($row['payload_json'] ?? ''), true);
        $snapshotDocuments[] = [
            'document_uuid' => (string)($row['document_uuid'] ?? ''),
            'document_type' => (string)($row['document_type'] ?? ''),
            'title' => (string)($row['title'] ?? ''),
            'event_datetime' => (string)($row['event_datetime'] ?? ''),
            'summary' => (string)($row['summary'] ?? ''),
            'payload' => is_array($payload) ? $payload : [],
        ];
    }

    $now = gmdate('Y-m-d H:i:s');
    $payloadData = [
        'auto_generated' => true,
        'snapshot_type' => 'encounter_auto',
        'context' => [
            'patient_id' => $patientId,
            'appointment_id' => ($appointmentId !== '' ? $appointmentId : null),
            'encounter_id' => (string)$encounterId,
            'encounter_key' => $encounterKey,
        ],
        'snapshot' => [
            'captured_at' => $now,
            'consultation_datetime' => ($eventDatetime !== '' ? $eventDatetime : $now),
            'counts' => $counts,
            'documents' => $snapshotDocuments,
        ],
    ];
    $payloadJson = json_encode($payloadData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($payloadJson)) {
        throw new RuntimeException('invalid auto note payload');
    }

    $renderedText = clinical_encounter_auto_note_rendered_text(
        $patientId,
        $encounterKey,
        ($eventDatetime !== '' ? $eventDatetime : $now),
        ($appointmentId !== '' ? $appointmentId : null),
        $encounterId,
        $snapshotDocuments,
        $counts
    );
    $summary = clinical_encounter_auto_note_summary($counts);
    $title = 'Nota clínica AUTO';
    $cols = clinical_table_columns($pdo, 'clinical_documents');
    $hasApptCol = isset($cols['appointment_id']);
    $existing = clinical_encounter_auto_note_find_existing($pdo, $patientId, $encounterKey, ($appointmentId !== '' ? $appointmentId : null), $encounterId);

    if (is_array($existing) && (int)($existing['id'] ?? 0) > 0) {
        $updateParts = [
            'title = :title',
            'encounter_id = :encounter_id',
            'payload_json = :payload_json',
            'rendered_text = :rendered_text',
            'summary = :summary',
            'event_datetime = :event_datetime',
            'updated_at = :updated_at',
            'generated_at = :generated_at',
            'signed_at = :signed_at',
            'updated_by_user_id = :updated_by_user_id',
        ];
        if ($hasApptCol) {
            $updateParts[] = 'appointment_id = :appointment_id';
        }
        $stmt = $pdo->prepare("
            UPDATE clinical_documents
            SET " . implode(', ', $updateParts) . "
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->bindValue(':id', (int)$existing['id'], PDO::PARAM_INT);
        $stmt->bindValue(':title', $title, PDO::PARAM_STR);
        $stmt->bindValue(':encounter_id', (string)$encounterId, PDO::PARAM_STR);
        $stmt->bindValue(':payload_json', $payloadJson, PDO::PARAM_STR);
        $stmt->bindValue(':rendered_text', $renderedText, PDO::PARAM_STR);
        $stmt->bindValue(':summary', $summary, PDO::PARAM_STR);
        $stmt->bindValue(':event_datetime', ($eventDatetime !== '' ? $eventDatetime : $now), PDO::PARAM_STR);
        $stmt->bindValue(':updated_at', $now, PDO::PARAM_STR);
        $stmt->bindValue(':generated_at', $now, PDO::PARAM_STR);
        $stmt->bindValue(':signed_at', $now, PDO::PARAM_STR);
        $stmt->bindValue(':updated_by_user_id', 'system_auto', PDO::PARAM_STR);
        if ($hasApptCol) {
            if ($appointmentId !== '') {
                $stmt->bindValue(':appointment_id', $appointmentId, PDO::PARAM_STR);
            } else {
                $stmt->bindValue(':appointment_id', null, PDO::PARAM_NULL);
            }
        }
        $stmt->execute();
        return [
            'action' => 'updated',
            'document_uuid' => (string)($existing['document_uuid'] ?? ''),
        ];
    }

    $documentUuid = clinical_generate_document_uuid();
    $values = [
        'document_uuid' => $documentUuid,
        'document_type' => 'note',
        'title' => $title,
        'version' => 1,
        'status' => 'signed',
        'patient_id' => $patientId,
        'appointment_id' => ($appointmentId !== '' ? $appointmentId : null),
        'encounter_id' => (string)$encounterId,
        'hospital_stay_id' => null,
        'care_setting' => 'consulta',
        'service' => null,
        'payload_json' => $payloadJson,
        'rendered_text' => $renderedText,
        'summary' => $summary,
        'edited_flag' => 0,
        'event_datetime' => ($eventDatetime !== '' ? $eventDatetime : $now),
        'widget_group' => 'documentos_clinicos',
        'printable' => 1,
        'created_at' => $now,
        'updated_at' => $now,
        'generated_at' => $now,
        'signed_at' => $now,
        'created_by_user_id' => 'system_auto',
        'updated_by_user_id' => 'system_auto',
    ];

    $insertCols = [];
    $placeholders = [];
    $params = [];
    foreach ($values as $col => $val) {
        if (!isset($cols[$col])) {
            continue;
        }
        $insertCols[] = '`' . $col . '`';
        $ph = ':auto_' . $col;
        $placeholders[] = $ph;
        $params[$ph] = $val;
    }
    $stmt = $pdo->prepare('INSERT INTO clinical_documents (' . implode(', ', $insertCols) . ') VALUES (' . implode(', ', $placeholders) . ')');
    foreach ($params as $ph => $val) {
        if ($val === null) {
            $stmt->bindValue($ph, null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue($ph, (string)$val, PDO::PARAM_STR);
        }
    }
    $stmt->execute();

    return [
        'action' => 'created',
        'document_uuid' => $documentUuid,
    ];
}

function clinical_encounter_final_note_upsert(PDO $pdo, array $encounterRow, string $closedByUserId): ?array
{
    $encounterId = (int)($encounterRow['encounter_id'] ?? 0);
    $patientId = trim((string)($encounterRow['patient_id'] ?? ''));
    $appointmentId = trim((string)($encounterRow['appointment_id'] ?? ''));
    $eventDatetime = trim((string)($encounterRow['encounter_dt'] ?? ''));
    if ($encounterId <= 0 || $patientId === '') {
        return null;
    }

    $encounterKey = clinical_encounter_key($encounterId, $appointmentId !== '' ? $appointmentId : null);
    $isLatestByAppointment = false;
    if ($appointmentId !== '') {
        $latest = clinical_encounter_get_latest_by_appointment($pdo, $appointmentId);
        $isLatestByAppointment = ((int)($latest['encounter_id'] ?? 0) === $encounterId);
    }

    $rows = clinical_timeline_encounter_documents_fetch($pdo, $patientId, $encounterId, $appointmentId, $isLatestByAppointment);
    $snapshotRows = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $payload = json_decode((string)($row['payload_json'] ?? ''), true);
        $snapshotType = trim((string)($payload['snapshot_type'] ?? ''));
        if (strtolower(trim((string)($row['document_type'] ?? ''))) === 'note' && in_array($snapshotType, ['encounter_auto', 'encounter_auto_final'], true)) {
            continue;
        }
        $snapshotRows[] = $row;
    }
    $buckets = clinical_encounter_document_buckets($snapshotRows);
    $counts = [
        'vitals' => count($buckets['vitals']),
        'notes' => count($buckets['notes']),
        'prescriptions' => count($buckets['prescriptions']),
        'orders' => count($buckets['orders']),
        'results' => count($buckets['results']),
        'procedures' => count($buckets['procedures']),
        'documents' => count($buckets['documents']),
        'images' => count($buckets['images']),
    ];

    $snapshotDocuments = [];
    foreach ($snapshotRows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $snapshotDocuments[] = [
            'document_uuid' => (string)($row['document_uuid'] ?? ''),
            'document_type' => (string)($row['document_type'] ?? ''),
            'title' => (string)($row['title'] ?? ''),
            'event_datetime' => (string)($row['event_datetime'] ?? ''),
            'summary' => (string)($row['summary'] ?? ''),
        ];
    }

    $now = gmdate('Y-m-d H:i:s');
    $payloadData = [
        'auto_generated' => true,
        'snapshot_type' => 'encounter_auto_final',
        'finalized' => true,
        'context' => [
            'patient_id' => $patientId,
            'appointment_id' => ($appointmentId !== '' ? $appointmentId : null),
            'encounter_id' => (string)$encounterId,
            'encounter_key' => $encounterKey,
        ],
        'snapshot' => [
            'captured_at' => $now,
            'consultation_datetime' => ($eventDatetime !== '' ? $eventDatetime : $now),
            'counts' => $counts,
            'documents' => $snapshotDocuments,
        ],
    ];
    $payloadJson = json_encode($payloadData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($payloadJson)) {
        throw new RuntimeException('invalid final auto note payload');
    }

    $renderedText = clinical_encounter_auto_note_rendered_text(
        $patientId,
        $encounterKey,
        ($eventDatetime !== '' ? $eventDatetime : $now),
        ($appointmentId !== '' ? $appointmentId : null),
        $encounterId,
        $snapshotDocuments,
        $counts
    );
    $summary = 'Cierre AUTO de consulta · ' . clinical_encounter_auto_note_summary($counts);
    $title = 'Nota clínica AUTO (Cierre)';
    $cols = clinical_table_columns($pdo, 'clinical_documents');
    $hasApptCol = isset($cols['appointment_id']);
    $stmt = $pdo->prepare("
        SELECT id, document_uuid
        FROM clinical_documents
        WHERE patient_id = :patient_id
          AND document_type = 'note'
          AND JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.snapshot_type')) = 'encounter_auto_final'
          AND JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.context.encounter_key')) = :encounter_key
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_STR);
    $stmt->bindValue(':encounter_key', $encounterKey, PDO::PARAM_STR);
    $stmt->execute();
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if (is_array($existing) && (int)($existing['id'] ?? 0) > 0) {
        $updateParts = [
            'title = :title',
            'encounter_id = :encounter_id',
            'payload_json = :payload_json',
            'rendered_text = :rendered_text',
            'summary = :summary',
            'event_datetime = :event_datetime',
            'updated_at = :updated_at',
            'generated_at = :generated_at',
            'signed_at = :signed_at',
            'updated_by_user_id = :updated_by_user_id',
        ];
        if ($hasApptCol) {
            $updateParts[] = 'appointment_id = :appointment_id';
        }
        $updateStmt = $pdo->prepare("
            UPDATE clinical_documents
            SET " . implode(', ', $updateParts) . "
            WHERE id = :id
            LIMIT 1
        ");
        $updateStmt->bindValue(':id', (int)$existing['id'], PDO::PARAM_INT);
        $updateStmt->bindValue(':title', $title, PDO::PARAM_STR);
        $updateStmt->bindValue(':encounter_id', (string)$encounterId, PDO::PARAM_STR);
        $updateStmt->bindValue(':payload_json', $payloadJson, PDO::PARAM_STR);
        $updateStmt->bindValue(':rendered_text', $renderedText, PDO::PARAM_STR);
        $updateStmt->bindValue(':summary', $summary, PDO::PARAM_STR);
        $updateStmt->bindValue(':event_datetime', ($eventDatetime !== '' ? $eventDatetime : $now), PDO::PARAM_STR);
        $updateStmt->bindValue(':updated_at', $now, PDO::PARAM_STR);
        $updateStmt->bindValue(':generated_at', $now, PDO::PARAM_STR);
        $updateStmt->bindValue(':signed_at', $now, PDO::PARAM_STR);
        $updateStmt->bindValue(':updated_by_user_id', $closedByUserId, PDO::PARAM_STR);
        if ($hasApptCol) {
            if ($appointmentId !== '') {
                $updateStmt->bindValue(':appointment_id', $appointmentId, PDO::PARAM_STR);
            } else {
                $updateStmt->bindValue(':appointment_id', null, PDO::PARAM_NULL);
            }
        }
        $updateStmt->execute();
        return [
            'action' => 'updated',
            'document_uuid' => (string)($existing['document_uuid'] ?? ''),
            'counts' => $counts,
        ];
    }

    $documentUuid = clinical_generate_document_uuid();
    $values = [
        'document_uuid' => $documentUuid,
        'document_type' => 'note',
        'title' => $title,
        'version' => 1,
        'status' => 'signed',
        'patient_id' => $patientId,
        'appointment_id' => ($appointmentId !== '' ? $appointmentId : null),
        'encounter_id' => (string)$encounterId,
        'hospital_stay_id' => null,
        'care_setting' => 'consulta',
        'service' => null,
        'payload_json' => $payloadJson,
        'rendered_text' => $renderedText,
        'summary' => $summary,
        'edited_flag' => 0,
        'event_datetime' => ($eventDatetime !== '' ? $eventDatetime : $now),
        'widget_group' => 'documentos_clinicos',
        'printable' => 1,
        'created_at' => $now,
        'updated_at' => $now,
        'generated_at' => $now,
        'signed_at' => $now,
        'created_by_user_id' => $closedByUserId,
        'updated_by_user_id' => $closedByUserId,
    ];
    $insertCols = [];
    $placeholders = [];
    $params = [];
    foreach ($values as $col => $val) {
        if (!isset($cols[$col])) {
            continue;
        }
        $insertCols[] = '`' . $col . '`';
        $ph = ':final_' . $col;
        $placeholders[] = $ph;
        $params[$ph] = $val;
    }
    $insertStmt = $pdo->prepare('INSERT INTO clinical_documents (' . implode(', ', $insertCols) . ') VALUES (' . implode(', ', $placeholders) . ')');
    foreach ($params as $ph => $val) {
        if ($val === null) {
            $insertStmt->bindValue($ph, null, PDO::PARAM_NULL);
        } else {
            $insertStmt->bindValue($ph, (string)$val, PDO::PARAM_STR);
        }
    }
    $insertStmt->execute();

    return [
        'action' => 'created',
        'document_uuid' => $documentUuid,
        'counts' => $counts,
    ];
}

function clinical_encounter_finalize(PDO $pdo, array $encounterRow, string $closedByUserId): array
{
    $encounterId = (int)($encounterRow['encounter_id'] ?? 0);
    $patientId = trim((string)($encounterRow['patient_id'] ?? ''));
    $appointmentId = trim((string)($encounterRow['appointment_id'] ?? ''));
    $status = strtolower(trim((string)($encounterRow['status'] ?? 'open')));
    $closedAt = trim((string)($encounterRow['closed_at'] ?? ''));
    $autoNoteUuidFinal = trim((string)($encounterRow['auto_note_uuid_final'] ?? ''));

    if ($encounterId <= 0 || $patientId === '') {
        throw new RuntimeException('encounter inválido');
    }

    $encounterKey = clinical_encounter_key($encounterId, $appointmentId !== '' ? $appointmentId : null);
    if ($status === 'closed' && $autoNoteUuidFinal !== '') {
        $finalDoc = clinical_documents_get_by_uuid_fetch($pdo, $autoNoteUuidFinal);
        $payload = is_array($finalDoc['content']['payload'] ?? null) ? $finalDoc['content']['payload'] : [];
        $counts = is_array($payload['snapshot']['counts'] ?? null) ? $payload['snapshot']['counts'] : [
            'vitals' => 0,
            'notes' => 0,
            'prescriptions' => 0,
            'orders' => 0,
            'results' => 0,
            'procedures' => 0,
        ];
        return [
            'encounter_key' => $encounterKey,
            'status' => 'closed',
            'closed_at' => ($closedAt !== '' ? $closedAt : null),
            'auto_note_uuid_final' => $autoNoteUuidFinal,
            'counts' => $counts,
            'primary_prescription_uuid' => null, // TODO: wire direct prescription uuid when prescription linkage is formalized.
        ];
    }

    $pdo->beginTransaction();
    try {
        $finalNote = clinical_encounter_final_note_upsert($pdo, $encounterRow, $closedByUserId);
        $finalUuid = trim((string)($finalNote['document_uuid'] ?? ''));
        $counts = is_array($finalNote['counts'] ?? null) ? $finalNote['counts'] : [];
        $now = gmdate('Y-m-d H:i:s');
        $stmt = $pdo->prepare("
            UPDATE clinical_encounters
            SET status = 'closed',
                closed_at = :closed_at,
                closed_by_user_id = :closed_by_user_id,
                auto_note_uuid_final = :auto_note_uuid_final,
                updated_at = NOW()
            WHERE encounter_id = :encounter_id
            LIMIT 1
        ");
        $stmt->bindValue(':closed_at', $now, PDO::PARAM_STR);
        $stmt->bindValue(':closed_by_user_id', $closedByUserId, PDO::PARAM_STR);
        if ($finalUuid !== '') {
            $stmt->bindValue(':auto_note_uuid_final', $finalUuid, PDO::PARAM_STR);
        } else {
            $stmt->bindValue(':auto_note_uuid_final', null, PDO::PARAM_NULL);
        }
        $stmt->bindValue(':encounter_id', $encounterId, PDO::PARAM_INT);
        $stmt->execute();
        $pdo->commit();

        return [
            'encounter_key' => $encounterKey,
            'status' => 'closed',
            'closed_at' => $now,
            'auto_note_uuid_final' => ($finalUuid !== '' ? $finalUuid : null),
            'counts' => [
                'vitals' => (int)($counts['vitals'] ?? 0),
                'notes' => (int)($counts['notes'] ?? 0),
                'prescriptions' => (int)($counts['prescriptions'] ?? 0),
                'orders' => (int)($counts['orders'] ?? 0),
                'results' => (int)($counts['results'] ?? 0),
                'procedures' => (int)($counts['procedures'] ?? 0),
            ],
            'primary_prescription_uuid' => null, // TODO: return direct uuid when prescription documents are linked 1:1.
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function clinical_documents_save_passthrough(PDO $pdo, array $args, bool $requireCanonicalPatient = false): array
{
    require_once __DIR__ . '/../_lib/clinical_documents.php';

    mxmed_ensure_clinical_docs_schema($pdo);

    $safePatientId = null;
    if ($requireCanonicalPatient) {
        $context = is_array($args['context'] ?? null) ? $args['context'] : [];
        $safePatientId = clinical_documents_validate_canonical_patient_id_for_write(
            $pdo,
            $context['patient_id'] ?? null
        );
    }

    $doc = mxmed_build_clinical_document($args);
    $apptId = trim((string)($doc['context']['appointment_id'] ?? ''));
    if ($apptId !== '') {
        $payload = is_array($doc['content']['payload'] ?? null) ? $doc['content']['payload'] : [];
        $payloadContext = is_array($payload['context'] ?? null) ? $payload['context'] : [];
        $payloadContext['appointment_id'] = $apptId;
        $payload['context'] = $payloadContext;
        $doc['content']['payload'] = $payload;
    }

    if ($safePatientId !== null) {
        $doc['context']['patient_id'] = $safePatientId;
    }

    if (($doc['document_type'] ?? '') === 'nota_evolucion') {
        $errs = mxmed_evolution_note_validate_to_generate((array)($doc['content']['payload'] ?? []));
        if (count($errs) > 0) {
            throw new InvalidArgumentException(implode(' ', $errs));
        }
    }
    if (($doc['document_type'] ?? '') === 'nota_evolucion_hosp') {
        $errs = mxmed_hosp_evolution_note_validate_to_generate((array)($doc['content']['payload'] ?? []));
        if (count($errs) > 0) {
            throw new InvalidArgumentException(implode(' ', $errs));
        }
    }

    $payloadJson = json_encode((array)$doc['content']['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($payloadJson)) {
        throw new RuntimeException('invalid payload json');
    }

    $pdo->beginTransaction();
    try {
        $cols = clinical_table_columns($pdo, 'clinical_documents');
        $hasApptCol = isset($cols['appointment_id']);
        $insertMap = [
            'document_uuid' => ':uuid',
            'document_type' => ':type',
            'title' => ':title',
            'version' => ':version',
            'status' => ':status',
            'patient_id' => ':patient_id',
        ];
        if ($hasApptCol) {
            $insertMap['appointment_id'] = ':appointment_id';
        }
        $insertMap += [
            'encounter_id' => ':encounter_id',
            'hospital_stay_id' => ':hospital_stay_id',
            'care_setting' => ':care_setting',
            'service' => ':service',
            'payload_json' => ':payload_json',
            'rendered_text' => ':rendered_text',
            'summary' => ':summary',
            'edited_flag' => ':edited_flag',
            'event_datetime' => ':event_datetime',
            'widget_group' => ':widget_group',
            'printable' => ':printable',
            'created_at' => ':created_at',
            'updated_at' => ':updated_at',
            'generated_at' => ':generated_at',
            'signed_at' => ':signed_at',
            'created_by_user_id' => ':created_by_user_id',
            'updated_by_user_id' => ':updated_by_user_id',
        ];

        $sqlColumns = implode(', ', array_keys($insertMap));
        $sqlPlaceholders = implode(', ', array_values($insertMap));
        $stmt = $pdo->prepare("
            INSERT INTO clinical_documents (
                {$sqlColumns}
            ) VALUES (
                {$sqlPlaceholders}
            )
        ");

        $params = [
            ':uuid' => $doc['document_id'],
            ':type' => $doc['document_type'],
            ':title' => $doc['title'],
            ':version' => (int)$doc['version'],
            ':status' => $doc['status'],
            ':patient_id' => $doc['context']['patient_id'],
            ':encounter_id' => $doc['context']['encounter_id'],
            ':hospital_stay_id' => $doc['context']['hospital_stay_id'],
            ':care_setting' => $doc['context']['care_setting'],
            ':service' => $doc['context']['service'],
            ':payload_json' => $payloadJson,
            ':rendered_text' => $doc['content']['rendered_text'],
            ':summary' => $doc['content']['summary'],
            ':edited_flag' => (int)($doc['content']['edited_flag'] ?? 0),
            ':event_datetime' => $doc['ui']['event_datetime'],
            ':widget_group' => $doc['ui']['widget_group'],
            ':printable' => !empty($doc['ui']['printable']) ? 1 : 0,
            ':created_at' => $doc['timestamps']['created_at'],
            ':updated_at' => $doc['timestamps']['updated_at'],
            ':generated_at' => $doc['timestamps']['generated_at'],
            ':signed_at' => $doc['timestamps']['signed_at'],
            ':created_by_user_id' => $doc['audit']['created_by_user_id'],
            ':updated_by_user_id' => $doc['audit']['updated_by_user_id'],
        ];
        if ($hasApptCol) {
            $params[':appointment_id'] = ($apptId !== '' ? $apptId : null);
        }

        $stmt->execute($params);

        $docId = (int)$pdo->lastInsertId();

        $pstmt = $pdo->prepare("
            INSERT INTO clinical_document_participants (
                clinical_document_id, user_id, role, participation_type, signed_at, created_at
            ) VALUES (
                :doc_id, :user_id, :role, :ptype, :signed_at, :created_at
            )
        ");

        $participants = (array)($doc['participants'] ?? []);
        foreach ($participants as $p) {
            if (!is_array($p)) {
                continue;
            }
            $pstmt->execute([
                ':doc_id' => $docId,
                ':user_id' => (string)($p['user_id'] ?? ''),
                ':role' => (string)($p['role'] ?? ''),
                ':ptype' => (string)($p['participation_type'] ?? ''),
                ':signed_at' => $p['signed_at'] ?? null,
                ':created_at' => $doc['timestamps']['created_at'],
            ]);
        }

        $triggerEncounterId = (int)($doc['context']['encounter_id'] ?? 0);
        $triggerPayload = is_array($doc['content']['payload'] ?? null) ? $doc['content']['payload'] : [];
        $isAutoEncounterNote = (
            strtolower(trim((string)($doc['document_type'] ?? ''))) === 'note'
            && trim((string)($triggerPayload['snapshot_type'] ?? '')) === 'encounter_auto'
        );
        if ($triggerEncounterId > 0 && !$isAutoEncounterNote) {
            clinical_encounters_ensure_schema($pdo);
            $encounterRow = clinical_encounter_get_by_id($pdo, $triggerEncounterId);
            if (is_array($encounterRow) && $encounterRow !== []) {
                clinical_encounter_auto_note_upsert($pdo, $encounterRow);
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        try {
            $pdo->rollBack();
        } catch (Throwable $e2) {
        }
        throw $e;
    }

    $doc['document_db_id'] = $docId;
    return $doc;
}

function clinical_generate_document_uuid(): string
{
    try {
        $bytes = random_bytes(16);
        $hex = bin2hex($bytes);
        return substr($hex, 0, 8) . '-' .
            substr($hex, 8, 4) . '-' .
            substr($hex, 12, 4) . '-' .
            substr($hex, 16, 4) . '-' .
            substr($hex, 20, 12);
    } catch (Throwable $e) {
        return 'dbg-' . str_replace('.', '', (string)microtime(true)) . '-' . substr(md5((string)mt_rand()), 0, 12);
    }
}

function clinical_table_columns(PDO $pdo, string $tableName): array
{
    $stmt = $pdo->prepare('SHOW COLUMNS FROM `' . str_replace('`', '', $tableName) . '`');
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!is_array($rows)) {
        return [];
    }

    $cols = [];
    foreach ($rows as $row) {
        $name = trim((string)($row['Field'] ?? ''));
        if ($name !== '') {
            $cols[$name] = true;
        }
    }
    return $cols;
}

function clinical_uploads_root_dir(): string
{
    return dirname(__DIR__, 2) . '/storage/clinical_uploads';
}

function clinical_uploads_relative_dir(): string
{
    return '/storage/clinical_uploads';
}

function clinical_gd_supports_webp(): bool
{
    if (!function_exists('gd_info')) {
        return false;
    }
    $info = gd_info();
    return (bool)($info['WebP Support'] ?? false);
}

function clinical_is_image_handle($value): bool
{
    return is_resource($value) || is_object($value);
}

function clinical_image_has_alpha($image): bool
{
    if (!clinical_is_image_handle($image)) {
        return false;
    }
    $w = imagesx($image);
    $h = imagesy($image);
    if ($w <= 0 || $h <= 0) {
        return false;
    }
    $stepX = max(1, (int)floor($w / 48));
    $stepY = max(1, (int)floor($h / 48));
    for ($y = 0; $y < $h; $y += $stepY) {
        for ($x = 0; $x < $w; $x += $stepX) {
            $rgba = imagecolorat($image, $x, $y);
            $alpha = ($rgba & 0x7F000000) >> 24;
            if ($alpha > 0) {
                return true;
            }
        }
    }
    return false;
}

function clinical_image_load_resource(string $tmpPath, string $mime)
{
    if ($mime === 'image/jpeg') {
        return @imagecreatefromjpeg($tmpPath);
    }
    if ($mime === 'image/png') {
        return @imagecreatefrompng($tmpPath);
    }
    if ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) {
        return @imagecreatefromwebp($tmpPath);
    }
    return false;
}

function clinical_prepare_png_for_webp($image): bool
{
    if (!clinical_is_image_handle($image)) {
        return false;
    }
    if (function_exists('imageistruecolor') && !imageistruecolor($image)) {
        if (!function_exists('imagepalettetotruecolor')) {
            return false;
        }
        $ok = @imagepalettetotruecolor($image);
        if (!$ok) {
            return false;
        }
    }
    imagealphablending($image, false);
    imagesavealpha($image, true);
    return true;
}

function clinical_image_fix_orientation($image, string $tmpPath, string $mime)
{
    if ($mime !== 'image/jpeg' || !function_exists('exif_read_data')) {
        return $image;
    }
    $exif = @exif_read_data($tmpPath);
    $orientation = (int)($exif['Orientation'] ?? 1);
    if ($orientation === 1) {
        return $image;
    }

    switch ($orientation) {
        case 2:
            imageflip($image, IMG_FLIP_HORIZONTAL);
            return $image;
        case 3:
            return imagerotate($image, 180, 0);
        case 4:
            imageflip($image, IMG_FLIP_VERTICAL);
            return $image;
        case 5:
            $rot = imagerotate($image, -90, 0);
            imageflip($rot, IMG_FLIP_HORIZONTAL);
            return $rot;
        case 6:
            return imagerotate($image, -90, 0);
        case 7:
            $rot = imagerotate($image, 90, 0);
            imageflip($rot, IMG_FLIP_HORIZONTAL);
            return $rot;
        case 8:
            return imagerotate($image, 90, 0);
        default:
            return $image;
    }
}

function clinical_image_resize($source, int $maxSide)
{
    $srcW = imagesx($source);
    $srcH = imagesy($source);
    if ($srcW <= 0 || $srcH <= 0) {
        return false;
    }
    $long = max($srcW, $srcH);
    if ($long <= $maxSide) {
        return $source;
    }
    $ratio = $maxSide / $long;
    $dstW = max(1, (int)round($srcW * $ratio));
    $dstH = max(1, (int)round($srcH * $ratio));
    $dst = imagecreatetruecolor($dstW, $dstH);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
    imagefilledrectangle($dst, 0, 0, $dstW, $dstH, $transparent);
    imagecopyresampled($dst, $source, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
    return $dst;
}

function clinical_save_image_variant($image, string $absPath, string $format, int $quality): bool
{
    if ($format === 'webp' && function_exists('imagewebp')) {
        return (bool)@imagewebp($image, $absPath, $quality);
    }
    if ($format === 'jpeg') {
        $bg = imagecreatetruecolor(imagesx($image), imagesy($image));
        $white = imagecolorallocate($bg, 255, 255, 255);
        imagefilledrectangle($bg, 0, 0, imagesx($bg), imagesy($bg), $white);
        imagecopy($bg, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
        $ok = (bool)@imagejpeg($bg, $absPath, $quality);
        return $ok;
    }
    if ($format === 'png') {
        return (bool)@imagepng($image, $absPath, 6);
    }
    return false;
}

function clinical_optimize_uploaded_image(array $file, string $documentUuid): array
{
    $tmpPath = (string)($file['tmp_name'] ?? '');
    $rawBytes = (int)($file['size'] ?? 0);
    if ($tmpPath === '' || !is_file($tmpPath)) {
        throw new RuntimeException('archivo temporal inválido');
    }
    if ($rawBytes <= 0 || $rawBytes > (25 * 1024 * 1024)) {
        throw new RuntimeException('tamaño de imagen inválido (máximo 25MB)');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = strtolower(trim((string)$finfo->file($tmpPath)));
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        throw new RuntimeException('solo se permiten imágenes jpeg/png/webp');
    }

    $size = @getimagesize($tmpPath);
    if (!is_array($size)) {
        throw new RuntimeException('imagen inválida');
    }
    $origW = (int)($size[0] ?? 0);
    $origH = (int)($size[1] ?? 0);
    if ($origW <= 0 || $origH <= 0 || $origW > 10000 || $origH > 10000) {
        throw new RuntimeException('dimensiones de imagen inválidas');
    }

    $source = clinical_image_load_resource($tmpPath, $mime);
    if ($source === false) {
        throw new RuntimeException('no se pudo decodificar imagen');
    }
    $oriented = clinical_image_fix_orientation($source, $tmpPath, $mime);
    $source = $oriented;

    $maxImage = clinical_image_resize($source, 2048);
    if ($maxImage === false) {
        throw new RuntimeException('no se pudo redimensionar imagen');
    }
    $source = $maxImage;

    $keepPng = ($mime === 'image/png') && clinical_image_has_alpha($source);
    $supportsWebp = clinical_gd_supports_webp();
    if ($mime === 'image/png' && !$keepPng && $supportsWebp) {
        // PNG palettized -> convert to truecolor before WebP encode.
        if (!clinical_prepare_png_for_webp($source)) {
            // Safe fallback: keep optimized PNG when conversion is unavailable/fails.
            $keepPng = true;
        }
    }
    $targetFormat = $keepPng ? 'png' : ($supportsWebp ? 'webp' : 'jpeg');
    $targetMime = $targetFormat === 'png' ? 'image/png' : ($targetFormat === 'webp' ? 'image/webp' : 'image/jpeg');
    $targetExt = $targetFormat === 'png' ? 'png' : ($targetFormat === 'webp' ? 'webp' : 'jpg');

    $thumbSource = clinical_image_resize($source, 480);
    if ($thumbSource === false) {
        throw new RuntimeException('no se pudo generar thumbnail');
    }

    $thumbFormat = $keepPng ? 'png' : ($supportsWebp ? 'webp' : 'jpeg');
    $thumbMime = $thumbFormat === 'png' ? 'image/png' : ($thumbFormat === 'webp' ? 'image/webp' : 'image/jpeg');
    $thumbExt = $thumbFormat === 'png' ? 'png' : ($thumbFormat === 'webp' ? 'webp' : 'jpg');

    $year = gmdate('Y');
    $month = gmdate('m');
    $baseDir = rtrim(clinical_uploads_root_dir(), '/');
    $relDir = rtrim(clinical_uploads_relative_dir(), '/');
    $folderAbs = $baseDir . '/' . $year . '/' . $month;
    $folderRel = $relDir . '/' . $year . '/' . $month;
    if (!is_dir($folderAbs) && !@mkdir($folderAbs, 0775, true) && !is_dir($folderAbs)) {
        throw new RuntimeException('no se pudo crear directorio de uploads');
    }

    $optFilename = $documentUuid . '-opt.' . $targetExt;
    $thumbFilename = $documentUuid . '-thumb.' . $thumbExt;
    $optAbs = $folderAbs . '/' . $optFilename;
    $thumbAbs = $folderAbs . '/' . $thumbFilename;
    $optRel = $folderRel . '/' . $optFilename;
    $thumbRel = $folderRel . '/' . $thumbFilename;

    $savedOpt = clinical_save_image_variant($source, $optAbs, $targetFormat, 80);
    if (!$savedOpt) {
        throw new RuntimeException('no se pudo guardar imagen optimizada');
    }
    $savedThumb = clinical_save_image_variant($thumbSource, $thumbAbs, $thumbFormat, 75);
    if (!$savedThumb) {
        throw new RuntimeException('no se pudo guardar thumbnail');
    }

    $optW = imagesx($source);
    $optH = imagesy($source);
    $thumbW = imagesx($thumbSource);
    $thumbH = imagesy($thumbSource);

    return [
        'render_mode' => 'image',
        'optimized' => [
            'path' => $optRel,
            'mime' => $targetMime,
            'bytes' => (int)(@filesize($optAbs) ?: 0),
            'w' => (int)$optW,
            'h' => (int)$optH,
        ],
        'thumb' => [
            'path' => $thumbRel,
            'mime' => $thumbMime,
            'bytes' => (int)(@filesize($thumbAbs) ?: 0),
            'w' => (int)$thumbW,
            'h' => (int)$thumbH,
        ],
        'original' => [
            'bytes' => (int)$rawBytes,
            'w' => (int)$origW,
            'h' => (int)$origH,
            'mime' => $mime,
        ],
    ];
}

function clinical_store_uploaded_file(array $file, string $documentUuid): array
{
    $tmpPath = (string)($file['tmp_name'] ?? '');
    $rawBytes = (int)($file['size'] ?? 0);
    if ($tmpPath === '' || !is_file($tmpPath)) {
        throw new RuntimeException('archivo temporal inválido');
    }
    if ($rawBytes <= 0 || $rawBytes > (25 * 1024 * 1024)) {
        throw new RuntimeException('tamaño de archivo inválido (máximo 25MB)');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = strtolower(trim((string)$finfo->file($tmpPath)));
    if (in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        return clinical_optimize_uploaded_image($file, $documentUuid);
    }
    if ($mime !== 'application/pdf') {
        throw new RuntimeException('solo se permiten imágenes jpeg/png/webp o PDF');
    }

    $year = gmdate('Y');
    $month = gmdate('m');
    $baseDir = rtrim(clinical_uploads_root_dir(), '/');
    $relDir = rtrim(clinical_uploads_relative_dir(), '/');
    $folderAbs = $baseDir . '/' . $year . '/' . $month;
    $folderRel = $relDir . '/' . $year . '/' . $month;
    if (!is_dir($folderAbs) && !@mkdir($folderAbs, 0775, true) && !is_dir($folderAbs)) {
        throw new RuntimeException('no se pudo crear directorio de uploads');
    }

    $origFilename = $documentUuid . '-orig.pdf';
    $abs = $folderAbs . '/' . $origFilename;
    $rel = $folderRel . '/' . $origFilename;
    if (!@move_uploaded_file($tmpPath, $abs)) {
        throw new RuntimeException('no se pudo guardar PDF');
    }

    return [
        'render_mode' => 'pdf',
        'original' => [
            'path' => $rel,
            'url' => $rel,
            'mime' => 'application/pdf',
            'bytes' => $rawBytes,
            'filename' => trim((string)($file['name'] ?? '')),
        ],
    ];
}

function clinical_documents_gateway_save_upload(PDO $pdo, array $payload, ?array $uploadFile, bool $requireCanonicalPatient = false): array
{
    $documentType = strtolower(trim((string)($payload['document_type'] ?? '')));
    $title = trim((string)($payload['title'] ?? ''));
    $summary = trim((string)($payload['summary'] ?? ''));
    $eventDatetime = trim((string)($payload['event_datetime'] ?? ''));
    $payloadData = $payload['payload'] ?? [];
    if (!is_array($payloadData)) {
        $payloadData = [];
    }

    if ($documentType === '') {
        throw new InvalidArgumentException('document_type requerido');
    }

    $requiredMediaTagKey = trim((string)($payload['media_tag_key'] ?? (($payloadData['media_tag_key'] ?? ''))));
    if ($documentType === 'image' && $requiredMediaTagKey === '') {
        throw new RuntimeException('MEDIA_TAG_REQUIRED');
    }

    if ($title === '') {
        $title = 'Documento clínico (' . $documentType . ')';
    }
    if ($eventDatetime === '') {
        $eventDatetime = gmdate('Y-m-d H:i:s');
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}$/', $eventDatetime) !== 1) {
        throw new InvalidArgumentException('event_datetime inválido (YYYY-MM-DD HH:MM:SS)');
    }

    $context = is_array($payload['context'] ?? null) ? $payload['context'] : [];
    $encounterKey = trim((string)($payload['encounter_key'] ?? ($context['encounter_key'] ?? '')));
    $patientIdRaw = $payload['patient_id'] ?? ($context['patient_id'] ?? '');
    $patientId = is_scalar($patientIdRaw) ? trim((string)$patientIdRaw) : '';
    $appointmentId = trim((string)($payload['appointment_id'] ?? ($context['appointment_id'] ?? '')));
    $encounterId = (int)($payload['encounter_id'] ?? ($context['encounter_id'] ?? 0));
    $hospitalStayId = trim((string)($payload['hospital_stay_id'] ?? ($context['hospital_stay_id'] ?? '')));
    $careSetting = trim((string)($payload['care_setting'] ?? ($context['care_setting'] ?? 'consulta')));
    if ($careSetting === '') {
        $careSetting = 'consulta';
    }

    if ($encounterKey !== '') {
        clinical_encounters_ensure_schema($pdo);
        $resolved = clinical_resolve_encounter_key($pdo, $encounterKey);
        if (($resolved['ok'] ?? false) !== true) {
            throw new InvalidArgumentException((string)($resolved['error_message'] ?? 'encounter inválido'));
        }
        $encounterRow = is_array($resolved['row'] ?? null) ? $resolved['row'] : [];
        $encounterId = (int)($encounterRow['encounter_id'] ?? 0);
        $patientId = trim((string)($encounterRow['patient_id'] ?? $patientId));
        $appointmentId = trim((string)($encounterRow['appointment_id'] ?? $appointmentId));
    }

    if ($patientId === '') {
        throw new InvalidArgumentException('patient_id requerido');
    }
    if ($requireCanonicalPatient) {
        $patientId = clinical_documents_validate_canonical_patient_id_for_write($pdo, $patientId);
    }

    $renderedText = null;
    if (is_string($payloadData['text'] ?? null)) {
        $renderedText = (string)$payloadData['text'];
    }

    $documentUuid = clinical_generate_document_uuid();
    if (is_array($uploadFile)) {
        $fileMeta = clinical_store_uploaded_file($uploadFile, $documentUuid);
        $payloadData['render_mode'] = (string)($fileMeta['render_mode'] ?? 'image');
        $payloadData['file'] = $fileMeta;
        if ($summary === '') {
            $summary = ($payloadData['render_mode'] === 'pdf') ? 'PDF clínico' : 'Imagen clínica';
        }
    }

    if ($documentType === 'image' || $documentType === 'pdf') {
        $mediaMeta = clinical_media_meta_from_payload([
            'media_tag_key' => ($payload['media_tag_key'] ?? ($payloadData['media_tag_key'] ?? null)),
            'media_tag_label' => ($payload['media_tag_label'] ?? ($payloadData['media_tag_label'] ?? null)),
            'media_caption' => ($payload['media_caption'] ?? ($payloadData['media_caption'] ?? null)),
            'media_bundle_id' => ($payload['media_bundle_id'] ?? ($payloadData['media_bundle_id'] ?? null)),
            'media_bundle_title' => ($payload['media_bundle_title'] ?? ($payloadData['media_bundle_title'] ?? null)),
            'media_bundle_note' => ($payload['media_bundle_note'] ?? ($payloadData['media_bundle_note'] ?? null)),
        ]);
        foreach ($mediaMeta as $mediaKey => $mediaValue) {
            if ($mediaValue !== null) {
                $payloadData[$mediaKey] = $mediaValue;
            }
        }
    }

    $payloadJson = json_encode($payloadData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($payloadJson)) {
        $payloadJson = '{}';
    }

    $now = gmdate('Y-m-d H:i:s');
    $cols = clinical_table_columns($pdo, 'clinical_documents');
    $values = [
        'document_uuid' => $documentUuid,
        'document_type' => $documentType,
        'title' => $title,
        'version' => 1,
        'status' => 'signed',
        'patient_id' => $patientId,
        'appointment_id' => ($appointmentId !== '' ? $appointmentId : null),
        'encounter_id' => ($encounterId > 0 ? (string)$encounterId : null),
        'hospital_stay_id' => ($hospitalStayId !== '' ? $hospitalStayId : null),
        'care_setting' => $careSetting,
        'service' => null,
        'payload_json' => $payloadJson,
        'rendered_text' => $renderedText,
        'summary' => $summary,
        'edited_flag' => 0,
        'event_datetime' => $eventDatetime,
        'widget_group' => 'documentos_clinicos',
        'printable' => 1,
        'created_at' => $now,
        'updated_at' => $now,
        'generated_at' => $now,
        'signed_at' => $now,
        'created_by_user_id' => 'qa',
        'updated_by_user_id' => 'qa',
    ];

    $insertCols = [];
    $placeholders = [];
    $params = [];
    foreach ($values as $col => $val) {
        if (!isset($cols[$col])) {
            continue;
        }
        $insertCols[] = '`' . $col . '`';
        $ph = ':g_' . $col;
        $placeholders[] = $ph;
        $params[$ph] = $val;
    }

    $sql = 'INSERT INTO clinical_documents (' . implode(', ', $insertCols) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $stmt = $pdo->prepare($sql);
    foreach ($params as $ph => $val) {
        if ($val === null) {
            $stmt->bindValue($ph, null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue($ph, (string)$val, PDO::PARAM_STR);
        }
    }
    $stmt->execute();

    return clinical_documents_get_by_uuid_fetch($pdo, $documentUuid) ?? [
        'document_id' => $documentUuid,
        'document_uuid' => $documentUuid,
    ];
}

function clinical_parse_include_csv(?string $raw): array
{
    $value = trim((string)$raw);
    if ($value === '') {
        return ['clinical'];
    }

    $parts = preg_split('/\s*,\s*/', $value, -1, PREG_SPLIT_NO_EMPTY);
    if (!is_array($parts) || $parts === []) {
        return ['clinical'];
    }

    $normalized = [];
    foreach ($parts as $part) {
        $key = strtolower(trim((string)$part));
        if ($key === '') {
            continue;
        }
        $normalized[$key] = true;
    }

    $list = array_keys($normalized);
    return ($list === []) ? ['clinical'] : $list;
}

function clinical_timeline_encode_cursor(string $eventDatetime, string $documentUuid): string
{
    $payload = [
        'dt' => $eventDatetime,
        'uuid' => $documentUuid,
    ];
    return base64_encode((string)json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function clinical_timeline_decode_cursor(string $cursor): array
{
    $decoded = base64_decode($cursor, true);
    if ($decoded === false || trim($decoded) === '') {
        return ['ok' => false, 'error' => 'cursor inválido'];
    }

    $data = json_decode($decoded, true);
    if (!is_array($data)) {
        return ['ok' => false, 'error' => 'cursor inválido'];
    }

    $dt = trim((string)($data['dt'] ?? ''));
    $uuid = trim((string)($data['uuid'] ?? ''));
    if ($dt === '' || $uuid === '') {
        return ['ok' => false, 'error' => 'cursor inválido'];
    }

    return [
        'ok' => true,
        'error' => null,
        'dt' => $dt,
        'uuid' => $uuid,
    ];
}

function clinical_timeline_date_only(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    return substr($value, 0, 10);
}

function clinical_media_meta_from_payload($payload): array
{
    $data = is_array($payload) ? $payload : [];
    $tagKey = trim((string)($data['media_tag_key'] ?? ''));
    $tagLabel = trim((string)($data['media_tag_label'] ?? ''));
    $caption = trim((string)($data['media_caption'] ?? ''));
    $bundleId = trim((string)($data['media_bundle_id'] ?? ''));
    $bundleTitle = trim((string)($data['media_bundle_title'] ?? ''));
    $bundleNote = trim((string)($data['media_bundle_note'] ?? ''));

    return [
        'media_tag_key' => ($tagKey !== '' ? $tagKey : null),
        'media_tag_label' => ($tagLabel !== '' ? $tagLabel : null),
        'media_caption' => ($caption !== '' ? $caption : null),
        'media_bundle_id' => ($bundleId !== '' ? $bundleId : null),
        'media_bundle_title' => ($bundleTitle !== '' ? $bundleTitle : null),
        'media_bundle_note' => ($bundleNote !== '' ? $bundleNote : null),
    ];
}

function clinical_media_meta_from_row(array $row): array
{
    $payloadJson = (string)($row['payload_json'] ?? '');
    if ($payloadJson === '') {
        return [
            'media_tag_key' => null,
            'media_tag_label' => null,
            'media_caption' => null,
            'media_bundle_id' => null,
            'media_bundle_title' => null,
            'media_bundle_note' => null,
        ];
    }

    $payload = json_decode($payloadJson, true);
    if (!is_array($payload)) {
        $payload = [];
    }

    return clinical_media_meta_from_payload($payload);
}

function clinical_bundle_clinical_block_from_row(array $row): ?array
{
    if (trim((string)($row['document_type'] ?? '')) !== 'bundle_clinical') {
        return null;
    }

    $payload = json_decode((string)($row['payload_json'] ?? ''), true);
    if (!is_array($payload)) {
        $payload = [];
    }

    $bundlePayload = is_array($payload['bundle_clinical'] ?? null) ? (array)$payload['bundle_clinical'] : [];
    $summary = trim((string)($bundlePayload['summary'] ?? ($payload['summary'] ?? ($row['summary'] ?? ''))));
    $interpretation = trim((string)($bundlePayload['interpretation'] ?? ($payload['interpretation'] ?? '')));
    $observations = trim((string)($bundlePayload['observations'] ?? ($payload['observations'] ?? '')));
    $schemaVersion = trim((string)($bundlePayload['schema_version'] ?? ($payload['schema_version'] ?? '')));

    if ($summary === '' && $interpretation === '' && $observations === '') {
        return null;
    }

    $block = [
        'summary' => ($summary !== '' ? $summary : null),
        'interpretation' => ($interpretation !== '' ? $interpretation : null),
        'observations' => ($observations !== '' ? $observations : null),
    ];
    if ($schemaVersion !== '') {
        $block['schema_version'] = $schemaVersion;
    }

    return $block;
}

function clinical_bundle_notes_excerpt(string $value, int $limit = 140): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($value, 'UTF-8') <= $limit) {
            return $value;
        }
        return rtrim(mb_substr($value, 0, $limit, 'UTF-8')) . '...';
    }

    if (strlen($value) <= $limit) {
        return $value;
    }

    return rtrim(substr($value, 0, $limit)) . '...';
}

function clinical_timeline_bundle_notes_map(PDO $pdo, string $patientId, array $bundleIds): array
{
    $normalizedBundleIds = [];
    foreach ($bundleIds as $bundleIdRaw) {
        $bundleId = trim((string)$bundleIdRaw);
        if ($bundleId === '') {
            continue;
        }
        $normalizedBundleIds[$bundleId] = true;
    }
    $bundleIds = array_keys($normalizedBundleIds);
    if ($patientId === '' || $bundleIds === []) {
        return [];
    }

    $placeholders = [];
    $params = [':patient_id' => $patientId];
    foreach ($bundleIds as $idx => $bundleId) {
        $paramKey = ':bundle_id_' . $idx;
        $placeholders[] = $paramKey;
        $params[$paramKey] = $bundleId;
    }

    $sql = "
        SELECT payload_json, summary, document_type
        FROM clinical_documents
        WHERE document_type = 'bundle_clinical'
          AND patient_id = :patient_id
          AND JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.media_bundle_id')) IN (" . implode(', ', $placeholders) . ")
        ORDER BY id DESC
    ";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!is_array($rows) || $rows === []) {
        return [];
    }

    $notesByBundleId = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $mediaMeta = clinical_media_meta_from_row($row);
        $bundleId = trim((string)($mediaMeta['media_bundle_id'] ?? ''));
        if ($bundleId === '' || isset($notesByBundleId[$bundleId])) {
            continue;
        }

        $block = clinical_bundle_clinical_block_from_row($row);
        $summary = trim((string)($block['summary'] ?? ''));
        $interpretation = trim((string)($block['interpretation'] ?? ''));
        $observations = trim((string)($block['observations'] ?? ''));
        $excerptSource = $summary !== '' ? $summary : ($interpretation !== '' ? $interpretation : $observations);

        $notesByBundleId[$bundleId] = [
            'has_notes' => ($summary !== '' || $interpretation !== '' || $observations !== ''),
            'excerpt' => clinical_bundle_notes_excerpt($excerptSource, 140),
        ];
    }

    return $notesByBundleId;
}

function clinical_timeline_documents_fetch(PDO $pdo, string $patientId, int $limit, ?string $cursorDt, ?string $cursorUuid): array
{
    $baseSelect = "
        SELECT
            id,
            document_uuid,
            document_type,
            title,
            summary,
            event_datetime,
            payload_json,
            hospital_stay_id
        FROM clinical_documents
        WHERE patient_id = :patient_id
    ";
    $baseSelectWithAppointment = "
        SELECT
            id,
            document_uuid,
            document_type,
            title,
            summary,
            event_datetime,
            appointment_id,
            payload_json,
            hospital_stay_id
        FROM clinical_documents
        WHERE patient_id = :patient_id
    ";

    $paramsBase = [':patient_id' => $patientId];
    $cursorClause = '';
    $orderLimit = " ORDER BY event_datetime DESC, document_uuid DESC LIMIT :limit";

    if ($cursorDt !== null && $cursorUuid !== null) {
        $cursorClause = "
          AND (
            event_datetime < :cursor_dt
            OR (event_datetime = :cursor_dt AND document_uuid < :cursor_uuid)
          )
        ";
    }

    $run = static function (string $sql, array $params) use ($pdo, $limit): array {
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    };

    $sqlWithAppointment = $baseSelectWithAppointment . " AND (appointment_id IS NULL OR document_type = 'procedure') " . $cursorClause . $orderLimit;
    $params = $paramsBase;
    if ($cursorDt !== null && $cursorUuid !== null) {
        $params[':cursor_dt'] = $cursorDt;
        $params[':cursor_uuid'] = $cursorUuid;
    }

    try {
        return $run($sqlWithAppointment, $params);
    } catch (Throwable $e) {
        $msg = strtolower(trim((string)$e->getMessage()));
        if (strpos($msg, "unknown column 'appointment_id'") === false) {
            throw $e;
        }
    }

    // Backward-compatible fallback for legacy schemas without appointment_id.
    $sqlLegacy = $baseSelect . $cursorClause . $orderLimit;
    return $run($sqlLegacy, $params);
}

function clinical_timeline_encounter_key_from_datetime(string $eventDatetime): string
{
    $ts = strtotime($eventDatetime);
    if ($ts === false) {
        return 'dt:00000000T0000:bucket60';
    }
    return 'dt:' . gmdate('Ymd\THi', $ts) . ':bucket60';
}

function clinical_timeline_document_item_from_row(array $row, string $patientId, ?int $encounterId = null): array
{
    $eventDatetime = (string)($row['event_datetime'] ?? '');
    $documentId = (int)($row['id'] ?? 0);
    $documentUuid = (string)($row['document_uuid'] ?? '');
    $documentType = (string)($row['document_type'] ?? '');
    $docType = strtolower(trim($documentType));
    $documentTitle = (string)($row['title'] ?? '');
    $appointmentId = trim((string)($row['appointment_id'] ?? ''));
    $resolvedEncounterId = (int)($encounterId ?? 0);
    $mediaMeta = clinical_media_meta_from_row($row);
    $clinicalDocument = [
        'id' => ($documentUuid !== '' ? $documentUuid : ($documentId > 0 ? (string)$documentId : null)),
        'document_uuid' => $documentUuid,
        'document_type' => $documentType,
        'title' => $documentTitle,
        'occurred_at' => ($eventDatetime !== '' ? $eventDatetime : null),
        'summary' => (string)($row['summary'] ?? ''),
        'media_tag_label' => $mediaMeta['media_tag_label'],
        'media_caption' => $mediaMeta['media_caption'],
        'media_bundle_id' => $mediaMeta['media_bundle_id'],
        'media_bundle_title' => $mediaMeta['media_bundle_title'],
        'media_bundle_note' => $mediaMeta['media_bundle_note'],
    ];
    if ($appointmentId !== '') {
        $clinicalDocument['context'] = [
            'appointment_id' => $appointmentId,
        ];
    }
    if (
        $docType === 'immunization'
        || $docType === 'medication_administration'
        || $docType === 'wound_care'
        || $docType === 'procedure'
        || $docType === 'lab_order'
        || $docType === 'imaging_order'
        || $docType === 'order'
        || $docType === 'lab_result'
        || $docType === 'imaging_result'
        || $docType === 'result'
        || $docType === 'lab_pdf'
    ) {
        $payload = json_decode((string)($row['payload_json'] ?? ''), true);
        $clinicalDocument['payload'] = is_array($payload) ? $payload : [];
    }

    return [
        'item_type' => 'document',
        'id' => ($documentUuid !== '' ? $documentUuid : ($documentId > 0 ? (string)$documentId : null)),
        'document_type' => $documentType,
        'occurred_at' => ($eventDatetime !== '' ? $eventDatetime : null),
        'title' => ($documentTitle !== '' ? $documentTitle : null),
        'ref' => ($documentUuid !== '' ? ('doc:' . $documentUuid) : null),
        'encounter_key' => clinical_timeline_encounter_key_from_datetime($eventDatetime),
        'event_datetime' => $eventDatetime,
        'sort_datetime' => $eventDatetime,
        'sort_key' => 'doc:' . $documentUuid,
        'links' => [
            'patient_id' => $patientId,
            'appointment_id' => ($appointmentId !== '' ? $appointmentId : null),
            'document_uuid' => $documentUuid,
            'encounter_id' => ($resolvedEncounterId > 0 ? $resolvedEncounterId : null),
            'hospital_stay_id' => $row['hospital_stay_id'] ?? null,
        ],
        'clinical_document' => $clinicalDocument,
        'media_tag_label' => $mediaMeta['media_tag_label'],
        'media_caption' => $mediaMeta['media_caption'],
        'media_bundle_id' => $mediaMeta['media_bundle_id'],
        'media_bundle_title' => $mediaMeta['media_bundle_title'],
        'media_bundle_note' => $mediaMeta['media_bundle_note'],
    ];
}

function clinical_timeline_semantic_classify(array $item): array
{
    $itemType = strtolower(trim((string)($item['item_type'] ?? '')));
    if ($itemType === 'appointment') {
        $agenda = is_array($item['agenda'] ?? null) ? $item['agenda'] : [];
        $reasonCode = strtolower(trim((string)($agenda['reason_code'] ?? '')));
        if ($reasonCode === 'procedure') {
            return [
                'clinical_category' => 'procedimiento',
                'study_role' => null,
            ];
        }
        return [
            'clinical_category' => 'cita',
            'study_role' => null,
        ];
    }

    $document = is_array($item['clinical_document'] ?? null) ? $item['clinical_document'] : [];
    $documentType = strtolower(trim((string)($item['clinical_document_type'] ?? '')));
    if ($documentType === '') {
        $documentType = strtolower(trim((string)($document['document_type'] ?? '')));
    }
    if ($documentType === '') {
        $documentType = strtolower(trim((string)($item['document_type'] ?? '')));
    }
    $mediaBundleId = trim((string)($item['media_bundle_id'] ?? ($document['media_bundle_id'] ?? '')));
    $bundleNotes = is_array($item['bundle_notes'] ?? null) ? $item['bundle_notes'] : [];
    $bundleHasNotes = (bool)($bundleNotes['has_notes'] ?? false);

    if ($documentType === 'prescription') {
        return [
            'clinical_category' => 'receta',
            'study_role' => null,
        ];
    }
    if ($documentType === 'orders') {
        return [
            'clinical_category' => 'estudio',
            'study_role' => 'orden',
        ];
    }
    if ($documentType === 'lab_order' || $documentType === 'imaging_order' || $documentType === 'order') {
        return [
            'clinical_category' => 'estudio',
            'study_role' => 'orden',
        ];
    }
    if ($documentType === 'results') {
        return [
            'clinical_category' => 'estudio',
            'study_role' => 'resultado',
        ];
    }
    if ($documentType === 'lab_result' || $documentType === 'imaging_result' || $documentType === 'result' || $documentType === 'lab_pdf') {
        return [
            'clinical_category' => 'estudio',
            'study_role' => 'resultado',
        ];
    }
    if ($documentType === 'immunization' || $documentType === 'medication_administration' || $documentType === 'wound_care' || $documentType === 'procedure') {
        return [
            'clinical_category' => 'procedimiento',
            'study_role' => null,
        ];
    }
    if ($mediaBundleId !== '' || $bundleHasNotes) {
        return [
            'clinical_category' => 'estudio',
            'study_role' => 'resultado',
        ];
    }
    if ($documentType === 'image') {
        return [
            'clinical_category' => 'documento',
            'study_role' => null,
        ];
    }
    if ($documentType === 'note') {
        return [
            'clinical_category' => 'consulta',
            'study_role' => null,
        ];
    }

    return [
        'clinical_category' => 'documento',
        'study_role' => null,
    ];
}

function clinical_bundle_documents_fetch(PDO $pdo, string $bundleId, string $patientId = ''): array
{
    if ($bundleId === '') {
        return [];
    }

    // QA manual: insertar bundle_clinical en payload_json.bundle_clinical.summary|interpretation|observations
    // con el mismo media_bundle_id y document_type='bundle_clinical'; payload_json.summary|... en raíz sigue soportado por compatibilidad.

    $sql = "
        SELECT
            id,
            document_uuid,
            document_type,
            title,
            summary,
            event_datetime,
            appointment_id,
            encounter_id,
            hospital_stay_id,
            patient_id,
            payload_json
        FROM clinical_documents
        WHERE JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.media_bundle_id')) = :bundle_id
    ";
    $params = [':bundle_id' => $bundleId];
    if ($patientId !== '') {
        $sql .= " AND patient_id = :patient_id";
        $params[':patient_id'] = $patientId;
    }
    // bundle_clinical debe abrir el payload clínico del bundle; las imágenes conservan su orden estable actual.
    $sql .= " ORDER BY CASE WHEN document_type = 'bundle_clinical' THEN 0 ELSE 1 END ASC, event_datetime ASC, id ASC, document_uuid ASC";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!is_array($rows)) {
        return [];
    }

    $items = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $uuid = trim((string)($row['document_uuid'] ?? ''));
        if ($uuid === '') {
            continue;
        }
        $mediaMeta = clinical_media_meta_from_row($row);
        $bundleClinical = clinical_bundle_clinical_block_from_row($row);
        $items[] = [
            'id' => (int)($row['id'] ?? 0),
            'document_uuid' => $uuid,
            'document_type' => (string)($row['document_type'] ?? ''),
            'event_datetime' => (string)($row['event_datetime'] ?? ''),
            'summary' => (string)($row['summary'] ?? ''),
            'media_tag_label' => $mediaMeta['media_tag_label'],
            'media_caption' => $mediaMeta['media_caption'],
            'media_bundle_id' => $mediaMeta['media_bundle_id'],
            'media_bundle_title' => $mediaMeta['media_bundle_title'],
            'media_bundle_note' => $mediaMeta['media_bundle_note'],
            'bundle_clinical' => $bundleClinical,
            'links' => [
                'patient_id' => (string)($row['patient_id'] ?? ''),
                'appointment_id' => ($row['appointment_id'] ?? null),
                'document_uuid' => $uuid,
                'encounter_id' => ($row['encounter_id'] ?? null),
                'hospital_stay_id' => ($row['hospital_stay_id'] ?? null),
                'viewer_url' => '/modules/clinical/ui/viewer.php?uuid=' . rawurlencode($uuid),
                'document_url' => '/api/clinical/index.php/documents/' . rawurlencode($uuid),
            ],
        ];
    }

    return $items;
}

function clinical_timeline_extract_appointment_id(array $timelineItem): string
{
    $links = is_array($timelineItem['links'] ?? null) ? $timelineItem['links'] : [];
    $appointmentId = trim((string)($links['appointment_id'] ?? ''));
    if ($appointmentId !== '') {
        return $appointmentId;
    }

    // encounter_key can be "appt:{id}" or "appt:{id}#enc:{n}".
    $encounterKey = trim((string)($timelineItem['encounter_key'] ?? ''));
    if ($encounterKey === '' || strpos($encounterKey, 'appt:') !== 0) {
        return '';
    }

    $ref = substr($encounterKey, 5);
    if ($ref === false || $ref === '') {
        return '';
    }

    $hashPos = strpos($ref, '#enc:');
    if ($hashPos !== false) {
        $ref = substr($ref, 0, $hashPos);
    }

    return trim((string)$ref);
}

function clinical_timeline_encounters_fetch(PDO $pdo, string $patientId, int $limit): array
{
    $stmt = $pdo->prepare("
        SELECT encounter_id, appointment_id, encounter_dt, encounter_type, status
        FROM clinical_encounters
        WHERE patient_id = :patient_id
        ORDER BY encounter_dt DESC, encounter_id DESC
        LIMIT :limit
    ");
    $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return is_array($rows) ? $rows : [];
}

function clinical_timeline_encounter_documents_fetch(
    PDO $pdo,
    string $patientId,
    int $encounterId,
    string $appointmentId,
    bool $isLatestByAppointment
): array {
    $direct = clinical_encounter_documents_direct_fetch($pdo, $patientId, $encounterId, 'ASC');
    if ($direct !== []) {
        return $direct;
    }

    if ($isLatestByAppointment && $appointmentId !== '') {
        return clinical_encounter_documents_legacy_by_appointment_fetch($pdo, $patientId, $appointmentId, 'ASC');
    }

    return [];
}

function clinical_timeline_encounter_flags(array $documents): array
{
    $flags = [
        'has_vitals' => false,
        'has_note' => false,
        'has_prescription' => false,
        'has_orders' => false,
        'has_results' => false,
    ];

    foreach ($documents as $doc) {
        $type = strtolower(trim((string)($doc['document_type'] ?? '')));
        if ($type === '') {
            continue;
        }

        if (in_array($type, ['vitals', 'vital_signs', 'signs'], true)) {
            $flags['has_vitals'] = true;
        }
        if (in_array($type, ['note', 'medical_note', 'evolution_note'], true)) {
            $flags['has_note'] = true;
        }
        if (in_array($type, ['prescription', 'rx'], true)) {
            $flags['has_prescription'] = true;
        }
        if (in_array($type, ['orders', 'order', 'lab_order', 'imaging_order'], true)) {
            $flags['has_orders'] = true;
        }
        if (in_array($type, ['results', 'result', 'lab_result', 'imaging_result'], true)) {
            $flags['has_results'] = true;
        }
    }

    return $flags;
}

function clinical_timeline_legacy_patient_ids(PDO $pdo, string $canonicalPatientId): array
{
    $stmt = $pdo->prepare("
        SELECT legacy_patient_id
        FROM clinical_patient_identity_bridge
        WHERE canonical_patient_id = :canonical_patient_id
        ORDER BY legacy_patient_id ASC
    ");
    $stmt->bindValue(':canonical_patient_id', $canonicalPatientId, PDO::PARAM_STR);
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!is_array($rows)) {
        return [];
    }

    $result = [];
    foreach ($rows as $row) {
        $legacyId = trim((string)($row['legacy_patient_id'] ?? ''));
        if ($legacyId !== '') {
            $result[$legacyId] = true;
        }
    }

    return array_keys($result);
}

function clinical_timeline_agenda_appointments_fetch(PDO $pdo, array $legacyPatientIds, int $limit, string $direction): array
{
    if ($legacyPatientIds === []) {
        return [];
    }

    $placeholders = [];
    $params = [];
    foreach (array_values($legacyPatientIds) as $idx => $legacyId) {
        $ph = ':pid' . $idx;
        $placeholders[] = $ph;
        $params[$ph] = $legacyId;
    }

    $orderDir = ($direction === 'forward') ? 'ASC' : 'DESC';
    $sql = "
        SELECT
            appointment_id,
            doctor_id,
            consultorio_id,
            patient_id,
            reason_code,
            reason_text,
            start_at,
            end_at,
            modality,
            status,
            channel_origin,
            created_by_role,
            created_by_id
        FROM agenda_appointments
        WHERE patient_id IN (" . implode(',', $placeholders) . ")
        ORDER BY start_at {$orderDir}, appointment_id {$orderDir}
        LIMIT :limit
    ";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $ph => $value) {
        $stmt->bindValue($ph, $value, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return is_array($rows) ? $rows : [];
}

function clinical_cases_ensure_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS clinical_cases (
            case_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            patient_id VARCHAR(64) NOT NULL,
            title VARCHAR(180) NOT NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_clinical_cases_patient_status (patient_id, status),
            CONSTRAINT fk_clinical_cases_patient
                FOREIGN KEY (patient_id) REFERENCES patients_patients (patient_id)
                ON UPDATE CASCADE
                ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS clinical_case_items (
            case_id BIGINT UNSIGNED NOT NULL,
            item_type VARCHAR(24) NOT NULL,
            item_ref VARCHAR(190) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (case_id, item_type, item_ref),
            KEY idx_clinical_case_items_case (case_id),
            CONSTRAINT fk_clinical_case_items_case
                FOREIGN KEY (case_id) REFERENCES clinical_cases (case_id)
                ON UPDATE CASCADE
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function clinical_encounters_ensure_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS clinical_encounters (
            encounter_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            patient_id VARCHAR(64) NOT NULL,
            appointment_id VARCHAR(64) DEFAULT NULL,
            encounter_dt DATETIME NOT NULL,
            encounter_type VARCHAR(32) NOT NULL DEFAULT 'outpatient',
            opened_by_user_id VARCHAR(64) DEFAULT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'open',
            closed_at DATETIME DEFAULT NULL,
            closed_by_user_id VARCHAR(64) DEFAULT NULL,
            auto_note_uuid_final VARCHAR(64) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_patient_dt (patient_id, encounter_dt),
            KEY idx_appt (appointment_id),
            CONSTRAINT fk_encounters_patient
                FOREIGN KEY (patient_id) REFERENCES patients_patients (patient_id)
                ON UPDATE CASCADE
                ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $alterStatements = [
        "ALTER TABLE clinical_encounters ADD COLUMN opened_by_user_id VARCHAR(64) NULL AFTER encounter_type",
        "ALTER TABLE clinical_encounters MODIFY COLUMN status VARCHAR(32) NOT NULL DEFAULT 'open'",
        "ALTER TABLE clinical_encounters ADD COLUMN closed_at DATETIME NULL AFTER status",
        "ALTER TABLE clinical_encounters ADD COLUMN closed_by_user_id VARCHAR(64) NULL AFTER closed_at",
        "ALTER TABLE clinical_encounters ADD COLUMN auto_note_uuid_final VARCHAR(64) NULL AFTER closed_by_user_id",
    ];
    foreach ($alterStatements as $sql) {
        try {
            $pdo->exec($sql);
        } catch (Throwable $e) {
            // backward-compatible migration: ignore duplicate/missing-column ALTER failures.
        }
    }
    try {
        $pdo->exec("
            UPDATE clinical_encounters
            SET status = 'open'
            WHERE status IS NULL
               OR TRIM(status) = ''
               OR LOWER(TRIM(status)) = 'completed'
        ");
    } catch (Throwable $e) {
        // best effort normalization for legacy rows
    }
}

function clinical_encounter_get_by_id(PDO $pdo, int $encounterId): ?array
{
    $stmt = $pdo->prepare("
        SELECT
            encounter_id, patient_id, appointment_id, encounter_dt,
            encounter_type, opened_by_user_id, status, closed_at, closed_by_user_id, auto_note_uuid_final, created_at, updated_at
        FROM clinical_encounters
        WHERE encounter_id = :encounter_id
        LIMIT 1
    ");
    $stmt->bindValue(':encounter_id', $encounterId, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return (is_array($row) && $row !== []) ? $row : null;
}

function clinical_encounter_get_latest_by_appointment(PDO $pdo, string $appointmentId): ?array
{
    $stmt = $pdo->prepare("
        SELECT
            encounter_id, patient_id, appointment_id, encounter_dt,
            encounter_type, opened_by_user_id, status, closed_at, closed_by_user_id, auto_note_uuid_final, created_at, updated_at
        FROM clinical_encounters
        WHERE appointment_id = :appointment_id
        ORDER BY encounter_dt DESC, encounter_id DESC
        LIMIT 1
    ");
    $stmt->bindValue(':appointment_id', $appointmentId, PDO::PARAM_STR);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return (is_array($row) && $row !== []) ? $row : null;
}

function clinical_encounter_key(int $encounterId, ?string $appointmentId): string
{
    $appt = trim((string)$appointmentId);
    if ($appt !== '') {
        return 'appt:' . $appt . '#enc:' . $encounterId;
    }
    return 'enc:' . $encounterId;
}

function clinical_resolve_encounter_key(PDO $pdo, string $encounterKey): array
{
    $encounterKey = urldecode(trim($encounterKey));
    if ($encounterKey === '') {
        return [
            'ok' => false,
            'error_code' => 'bad_request',
            'error_message' => 'encounter_key requerido',
        ];
    }

    $encounterRow = null;
    if (strpos($encounterKey, 'enc:') === 0) {
        $encounterId = (int)trim(substr($encounterKey, 4));
        if ($encounterId <= 0) {
            return [
                'ok' => false,
                'error_code' => 'bad_request',
                'error_message' => 'encounter_id inválido',
            ];
        }
        $encounterRow = clinical_encounter_get_by_id($pdo, $encounterId);
        if ($encounterRow === null) {
            return [
                'ok' => false,
                'error_code' => 'not_found',
                'error_message' => 'encounter no encontrado',
            ];
        }
    } elseif (strpos($encounterKey, 'appt:') === 0) {
        $rest = trim(substr($encounterKey, 5));
        if ($rest === '') {
            return [
                'ok' => false,
                'error_code' => 'bad_request',
                'error_message' => 'appointment_id inválido',
            ];
        }

        $hashPos = strpos($rest, '#enc:');
        if ($hashPos !== false) {
            $apptCandidate = trim(substr($rest, 0, $hashPos));
            $encCandidate = (int)trim(substr($rest, $hashPos + 5));
            if ($apptCandidate === '' || $encCandidate <= 0) {
                return [
                    'ok' => false,
                    'error_code' => 'bad_request',
                    'error_message' => 'encounter_key inválido',
                ];
            }
            $encounterRow = clinical_encounter_get_by_id($pdo, $encCandidate);
            if ($encounterRow === null) {
                return [
                    'ok' => false,
                    'error_code' => 'not_found',
                    'error_message' => 'encounter no encontrado',
                ];
            }
            $rowAppt = trim((string)($encounterRow['appointment_id'] ?? ''));
            if ($rowAppt !== $apptCandidate) {
                return [
                    'ok' => false,
                    'error_code' => 'not_found',
                    'error_message' => 'encounter no encontrado',
                ];
            }
        } else {
            $encounterRow = clinical_encounter_get_latest_by_appointment($pdo, $rest);
            if ($encounterRow === null) {
                return [
                    'ok' => false,
                    'error_code' => 'not_found',
                    'error_message' => 'encounter no encontrado',
                ];
            }
        }
    } else {
        return [
            'ok' => false,
            'error_code' => 'bad_request',
            'error_message' => 'encounter_key no soportado',
        ];
    }

    $encounterId = (int)($encounterRow['encounter_id'] ?? 0);
    $appointmentId = trim((string)($encounterRow['appointment_id'] ?? ''));
    return [
        'ok' => true,
        'row' => $encounterRow,
        'encounter_key' => clinical_encounter_key($encounterId, $appointmentId),
    ];
}

function clinical_encounters_create(
    PDO $pdo,
    string $patientId,
    ?string $appointmentId,
    string $encounterDt,
    string $encounterType,
    string $status,
    ?string $openedByUserId = null
): array {
    $stmt = $pdo->prepare("
        INSERT INTO clinical_encounters
            (patient_id, appointment_id, encounter_dt, encounter_type, opened_by_user_id, status, created_at, updated_at)
        VALUES
            (:patient_id, :appointment_id, :encounter_dt, :encounter_type, :opened_by_user_id, :status, NOW(), NOW())
    ");
    $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_STR);
    if ($appointmentId === null || $appointmentId === '') {
        $stmt->bindValue(':appointment_id', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':appointment_id', $appointmentId, PDO::PARAM_STR);
    }
    $stmt->bindValue(':encounter_dt', $encounterDt, PDO::PARAM_STR);
    $stmt->bindValue(':encounter_type', $encounterType, PDO::PARAM_STR);
    $openedByUserId = trim((string)$openedByUserId);
    if ($openedByUserId === '') {
        $stmt->bindValue(':opened_by_user_id', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':opened_by_user_id', $openedByUserId, PDO::PARAM_STR);
    }
    $stmt->bindValue(':status', $status, PDO::PARAM_STR);
    $stmt->execute();

    $encounterId = (int)$pdo->lastInsertId();
    $row = clinical_encounter_get_by_id($pdo, $encounterId);
    if ($row === null) {
        throw new RuntimeException('no se pudo crear encounter');
    }
    return $row;
}

function clinical_encounters_list_fetch(PDO $pdo, string $patientId, int $limit): array
{
    $stmt = $pdo->prepare("
        SELECT
            encounter_id, patient_id, appointment_id, encounter_dt,
            encounter_type, opened_by_user_id, status, closed_at, closed_by_user_id, auto_note_uuid_final, created_at, updated_at
        FROM clinical_encounters
        WHERE patient_id = :patient_id
        ORDER BY encounter_dt DESC, encounter_id DESC
        LIMIT :limit
    ");
    $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return is_array($rows) ? $rows : [];
}

function clinical_encounter_open_fetch(PDO $pdo, string $patientId, string $openedByUserId): ?array
{
    $patientId = trim($patientId);
    $openedByUserId = trim($openedByUserId);
    if ($patientId === '' || $openedByUserId === '') {
        return null;
    }

    $stmt = $pdo->prepare("
        SELECT
            encounter_id, patient_id, appointment_id, encounter_dt,
            encounter_type, opened_by_user_id, status, closed_at, closed_by_user_id, auto_note_uuid_final, created_at, updated_at
        FROM clinical_encounters
        WHERE patient_id = :patient_id
          AND opened_by_user_id = :opened_by_user_id
          AND LOWER(TRIM(status)) = 'open'
        ORDER BY encounter_dt DESC, encounter_id DESC
        LIMIT 1
    ");
    $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_STR);
    $stmt->bindValue(':opened_by_user_id', $openedByUserId, PDO::PARAM_STR);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return (is_array($row) && $row !== []) ? $row : null;
}

function clinical_cases_active_fetch(PDO $pdo, string $patientId): ?array
{
    $stmt = $pdo->prepare("
        SELECT
            c.case_id,
            c.patient_id,
            c.title,
            c.status,
            c.created_at,
            c.updated_at,
            (
                SELECT COUNT(*)
                FROM clinical_case_items i
                WHERE i.case_id = c.case_id
            ) AS items_count
        FROM clinical_cases c
        WHERE c.patient_id = :patient_id AND c.status = 'active'
        ORDER BY updated_at DESC, case_id DESC
        LIMIT 1
    ");
    $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_STR);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (is_array($row) && $row !== []) {
        $row['items_count'] = (int)($row['items_count'] ?? 0);
    }
    return (is_array($row) && $row !== []) ? $row : null;
}

function clinical_case_get_by_id(PDO $pdo, int $caseId): ?array
{
    $stmt = $pdo->prepare("
        SELECT case_id, patient_id, title, status, created_at, updated_at
        FROM clinical_cases
        WHERE case_id = :case_id
        LIMIT 1
    ");
    $stmt->bindValue(':case_id', $caseId, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return (is_array($row) && $row !== []) ? $row : null;
}

function clinical_cases_create(PDO $pdo, string $patientId, string $title): array
{
    $pdo->beginTransaction();
    try {
        $stmtOff = $pdo->prepare("
            UPDATE clinical_cases
            SET status = 'inactive', updated_at = NOW()
            WHERE patient_id = :patient_id AND status = 'active'
        ");
        $stmtOff->bindValue(':patient_id', $patientId, PDO::PARAM_STR);
        $stmtOff->execute();

        $stmtCreate = $pdo->prepare("
            INSERT INTO clinical_cases (patient_id, title, status, created_at, updated_at)
            VALUES (:patient_id, :title, 'active', NOW(), NOW())
        ");
        $stmtCreate->bindValue(':patient_id', $patientId, PDO::PARAM_STR);
        $stmtCreate->bindValue(':title', $title, PDO::PARAM_STR);
        $stmtCreate->execute();
        $caseId = (int)$pdo->lastInsertId();

        $pdo->commit();
    } catch (Throwable $e) {
        try {
            $pdo->rollBack();
        } catch (Throwable $e2) {
        }
        throw $e;
    }

    $created = clinical_case_get_by_id($pdo, $caseId);
    if ($created === null) {
        throw new RuntimeException('no se pudo crear case');
    }
    return $created;
}

function clinical_cases_activate(PDO $pdo, int $caseId): ?array
{
    $target = clinical_case_get_by_id($pdo, $caseId);
    if ($target === null) {
        return null;
    }

    $patientId = trim((string)($target['patient_id'] ?? ''));
    if ($patientId === '') {
        return null;
    }

    $pdo->beginTransaction();
    try {
        $stmtOff = $pdo->prepare("
            UPDATE clinical_cases
            SET status = 'inactive', updated_at = NOW()
            WHERE patient_id = :patient_id AND status = 'active' AND case_id <> :case_id
        ");
        $stmtOff->bindValue(':patient_id', $patientId, PDO::PARAM_STR);
        $stmtOff->bindValue(':case_id', $caseId, PDO::PARAM_INT);
        $stmtOff->execute();

        $stmtOn = $pdo->prepare("
            UPDATE clinical_cases
            SET status = 'active', updated_at = NOW()
            WHERE case_id = :case_id
        ");
        $stmtOn->bindValue(':case_id', $caseId, PDO::PARAM_INT);
        $stmtOn->execute();

        $pdo->commit();
    } catch (Throwable $e) {
        try {
            $pdo->rollBack();
        } catch (Throwable $e2) {
        }
        throw $e;
    }

    return clinical_case_get_by_id($pdo, $caseId);
}

function clinical_cases_rename(PDO $pdo, int $caseId, string $title): ?array
{
    $stmt = $pdo->prepare("
        UPDATE clinical_cases
        SET title = :title, updated_at = NOW()
        WHERE case_id = :case_id
    ");
    $stmt->bindValue(':title', $title, PDO::PARAM_STR);
    $stmt->bindValue(':case_id', $caseId, PDO::PARAM_INT);
    $stmt->execute();
    if ($stmt->rowCount() <= 0) {
        return clinical_case_get_by_id($pdo, $caseId);
    }
    return clinical_case_get_by_id($pdo, $caseId);
}

function clinical_case_item_type_allowed(string $itemType): bool
{
    return in_array($itemType, ['encounter', 'document', 'appointment'], true);
}

function clinical_case_item_find_owner_case(PDO $pdo, string $patientId, string $itemType, string $itemRef): ?int
{
    $patientId = trim($patientId);
    if ($patientId === '') {
        return null;
    }
    $stmt = $pdo->prepare("
        SELECT i.case_id
        FROM clinical_case_items i
        INNER JOIN clinical_cases c ON c.case_id = i.case_id
        WHERE c.patient_id = :patient_id
          AND i.item_type = :t
          AND i.item_ref = :r
        LIMIT 1
    ");
    $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_STR);
    $stmt->bindValue(':t', $itemType, PDO::PARAM_STR);
    $stmt->bindValue(':r', $itemRef, PDO::PARAM_STR);
    $stmt->execute();
    $caseId = $stmt->fetchColumn();

    return $caseId === false ? null : (int)$caseId;
}

function clinical_case_item_insert(PDO $pdo, int $caseId, string $itemType, string $itemRef): bool
{
    $sql = "
        INSERT INTO clinical_case_items (case_id, item_type, item_ref, created_at)
        VALUES (:case_id, :item_type, :item_ref, NOW())
        ON DUPLICATE KEY UPDATE created_at = created_at
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':case_id', $caseId, PDO::PARAM_INT);
    $stmt->bindValue(':item_type', $itemType, PDO::PARAM_STR);
    $stmt->bindValue(':item_ref', $itemRef, PDO::PARAM_STR);
    $stmt->execute();
    return $stmt->rowCount() > 0;
}

function clinical_case_items_fetch(PDO $pdo, int $caseId): array
{
    $stmt = $pdo->prepare("
        SELECT item_type, item_ref
        FROM clinical_case_items
        WHERE case_id = :case_id
    ");
    $stmt->bindValue(':case_id', $caseId, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return is_array($rows) ? $rows : [];
}

function clinical_case_items_list_fetch(PDO $pdo, int $caseId, int $limit): array
{
    $stmt = $pdo->prepare("
        SELECT case_id, item_type, item_ref, created_at
        FROM clinical_case_items
        WHERE case_id = :case_id
        ORDER BY created_at DESC, item_type ASC, item_ref ASC
        LIMIT :limit
    ");
    $stmt->bindValue(':case_id', $caseId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return is_array($rows) ? $rows : [];
}

function clinical_patient_exists(PDO $pdo, string $patientId): bool
{
    $stmt = $pdo->prepare("SELECT patient_id FROM patients_patients WHERE patient_id = :patient_id LIMIT 1");
    $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_STR);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) && trim((string)($row['patient_id'] ?? '')) !== '';
}

function clinical_history_payload_is_object(array $payload): bool
{
    return clinical_record_payload_is_object($payload);
}

function clinical_record_payload_is_object(array $payload): bool
{
    if ($payload === []) {
        return true;
    }
    return array_keys($payload) !== range(0, count($payload) - 1);
}

function clinical_history_record_from_row(?array $row): ?array
{
    return clinical_record_entry_from_row($row);
}

function clinical_record_entry_from_row(?array $row): ?array
{
    if (!is_array($row)) {
        return null;
    }

    $payload = [];
    $payloadRaw = $row['payload_json'] ?? null;
    if ($payloadRaw !== null && trim((string)$payloadRaw) !== '') {
        $decoded = json_decode((string)$payloadRaw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $payload = $decoded;
        }
    }

    return [
        'entry_id' => (int)($row['entry_id'] ?? 0),
        'patient_id' => (string)($row['patient_id'] ?? ''),
        'entry_date' => (string)($row['entry_date'] ?? ''),
        'note_type' => (string)($row['note_type'] ?? ''),
        'status' => (string)($row['status'] ?? ''),
        'payload' => $payload,
        'subjective' => $row['subjective'] ?? null,
        'objective' => $row['objective'] ?? null,
        'assessment' => $row['assessment'] ?? null,
        'plan' => $row['plan'] ?? null,
        'created_at' => (string)($row['created_at'] ?? ''),
        'updated_at' => (string)($row['updated_at'] ?? ''),
    ];
}

function clinical_history_fetch_draft(PDO $pdo, string $patientId): ?array
{
    return clinical_record_entry_fetch_draft($pdo, $patientId, 'historia_clinica');
}

function clinical_record_entry_fetch_draft(PDO $pdo, string $patientId, string $noteType): ?array
{
    $stmt = $pdo->prepare("
        SELECT entry_id, patient_id, entry_date, note_type, status, payload_json,
               subjective, objective, assessment, plan, created_at, updated_at
        FROM clinical_record_entries
        WHERE patient_id = :patient_id
          AND note_type = :note_type
          AND status = 'draft'
        ORDER BY updated_at DESC, entry_id DESC
        LIMIT 1
    ");
    $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_STR);
    $stmt->bindValue(':note_type', $noteType, PDO::PARAM_STR);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return clinical_record_entry_from_row(is_array($row) ? $row : null);
}

function clinical_history_upsert_draft(PDO $pdo, string $patientId, array $payload): array
{
    return clinical_record_entry_upsert_draft($pdo, $patientId, 'historia_clinica', $payload, 'no se pudo guardar historia clinica');
}

function clinical_record_entry_upsert_draft(PDO $pdo, string $patientId, string $noteType, array $payload, string $errorMessage): array
{
    $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($payloadJson)) {
        $payloadJson = '{}';
    }

    $stmt = $pdo->prepare("
        SELECT entry_id
        FROM clinical_record_entries
        WHERE patient_id = :patient_id
          AND note_type = :note_type
          AND status = 'draft'
        ORDER BY updated_at DESC, entry_id DESC
        LIMIT 1
    ");
    $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_STR);
    $stmt->bindValue(':note_type', $noteType, PDO::PARAM_STR);
    $stmt->execute();
    $entryId = $stmt->fetchColumn();

    if ($entryId !== false && (int)$entryId > 0) {
        $update = $pdo->prepare("
            UPDATE clinical_record_entries
            SET payload_json = :payload_json,
                updated_at = NOW()
            WHERE entry_id = :entry_id
        ");
        $update->bindValue(':payload_json', $payloadJson, PDO::PARAM_STR);
        $update->bindValue(':entry_id', (int)$entryId, PDO::PARAM_INT);
        $update->execute();
    } else {
        $insert = $pdo->prepare("
            INSERT INTO clinical_record_entries
                (patient_id, note_type, status, payload_json, entry_date, created_at, updated_at)
            VALUES
                (:patient_id, :note_type, 'draft', :payload_json, NOW(), NOW(), NOW())
        ");
        $insert->bindValue(':patient_id', $patientId, PDO::PARAM_STR);
        $insert->bindValue(':note_type', $noteType, PDO::PARAM_STR);
        $insert->bindValue(':payload_json', $payloadJson, PDO::PARAM_STR);
        $insert->execute();
    }

    $record = clinical_record_entry_fetch_draft($pdo, $patientId, $noteType);
    if ($record === null) {
        throw new RuntimeException($errorMessage);
    }
    return $record;
}

function clinical_cases_list_fetch(PDO $pdo, string $patientId): array
{
    $stmt = $pdo->prepare("
        SELECT case_id, patient_id, title, status, created_at, updated_at
        FROM clinical_cases
        WHERE patient_id = :patient_id
        ORDER BY
            CASE WHEN status = 'active' THEN 0 ELSE 1 END ASC,
            updated_at DESC,
            case_id DESC
    ");
    $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_STR);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return is_array($rows) ? $rows : [];
}

set_error_handler(static function ($severity, $message, $file, $line): void {
    throw new ErrorException((string)$message, 0, (int)$severity, (string)$file, (int)$line);
});

try {
    $method = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
    clinical_apply_cors_headers($origin);
    if ($method === 'OPTIONS') {
        http_response_code(204);
        exit;
    }

    $segments = clinical_route_segments();
    $route = implode('/', $segments);
    $isTimelineRoute = ($method === 'GET'
        && ($segments[0] ?? '') === 'patients'
        && ($segments[2] ?? '') === 'timeline'
        && count($segments) === 3);

    // Ensure bridge schema at gateway startup (best-effort to avoid breaking non-DB routes).
    if (!$isTimelineRoute) {
        try {
            $bridgePdo = clinical_documents_pdo();
            clinical_ensure_identity_bridge_schema($bridgePdo);
            clinical_note_capture_tokens_ensure_schema($bridgePdo);
        } catch (Throwable $e) {
        }
    }

    if ($isTimelineRoute) {
        $patientId = trim((string)$segments[1]);
        if ($patientId === '') {
            clinical_send_response([
                'ok' => false,
                'error' => [
                    'code' => 'bad_request',
                    'message' => 'patient_id requerido',
                ],
                'message' => '',
                'data' => null,
                'meta' => [
                    'method' => 'GET',
                    'route' => 'patients/{patient_id}/timeline',
                ],
            ], 400);
            return;
        }

        $limit = 30;
        $limitRaw = $_GET['limit'] ?? null;
        if ($limitRaw !== null && trim((string)$limitRaw) !== '') {
            $limitText = trim((string)$limitRaw);
            if (preg_match('/^\d+$/', $limitText) !== 1) {
                clinical_send_response([
                    'ok' => false,
                    'error' => [
                        'code' => 'bad_request',
                        'message' => 'limit debe ser int entre 1 y 100',
                    ],
                    'message' => '',
                    'data' => null,
                    'meta' => [
                        'method' => 'GET',
                        'route' => 'patients/{patient_id}/timeline',
                    ],
                ], 400);
                return;
            }

            $limit = (int)$limitText;
            if ($limit < 1 || $limit > 100) {
                clinical_send_response([
                    'ok' => false,
                    'error' => [
                        'code' => 'bad_request',
                        'message' => 'limit debe ser int entre 1 y 100',
                    ],
                    'message' => '',
                    'data' => null,
                    'meta' => [
                        'method' => 'GET',
                        'route' => 'patients/{patient_id}/timeline',
                    ],
                ], 400);
                return;
            }
        }

        $direction = trim((string)($_GET['direction'] ?? 'backward'));
        if ($direction === '') {
            $direction = 'backward';
        }
        if ($direction !== 'backward' && $direction !== 'forward') {
            clinical_send_response([
                'ok' => false,
                'error' => [
                    'code' => 'bad_request',
                    'message' => 'direction inválido; usa backward|forward',
                ],
                'message' => '',
                'data' => null,
                'meta' => [
                    'method' => 'GET',
                    'route' => 'patients/{patient_id}/timeline',
                ],
            ], 400);
            return;
        }

        $includeProvided = array_key_exists('include', $_GET);
        $includeRaw = (string)($_GET['include'] ?? '');
        if (!$includeProvided) {
            $include = ['clinical'];
        } elseif (trim($includeRaw) === '') {
            $include = [];
        } else {
            $include = clinical_parse_include_csv($includeRaw);
        }
        $includeClinical = in_array('clinical', $include, true);
        $includeAgenda = in_array('agenda', $include, true);
        $includeDocsRaw = strtolower(trim((string)($_GET['include_docs'] ?? '0')));
        $includeDocs = in_array($includeDocsRaw, ['1', 'true', 'yes', 'on'], true);

        $cursorDt = null;
        $cursorUuid = null;
        $cursor = trim((string)($_GET['cursor'] ?? ''));
        if ($cursor !== '') {
            $cursorDecoded = clinical_timeline_decode_cursor($cursor);
            if (($cursorDecoded['ok'] ?? false) !== true) {
                clinical_send_response([
                    'ok' => false,
                    'error' => [
                        'code' => 'bad_request',
                        'message' => (string)($cursorDecoded['error'] ?? 'cursor inválido'),
                    ],
                    'message' => '',
                    'data' => null,
                    'meta' => [
                        'method' => 'GET',
                        'route' => 'patients/{patient_id}/timeline',
                    ],
                ], 400);
                return;
            }
            $cursorDt = (string)$cursorDecoded['dt'];
            $cursorUuid = (string)$cursorDecoded['uuid'];
        }

        $items = [];
        $cursorNext = null;
        $isScaffold = !($includeClinical || $includeAgenda);
        $activeCase = null;
        if ($includeClinical || $includeAgenda) {
            $encounters = [];
            $rows = [];
            $agendaAppointments = [];
            try {
                $pdo = clinical_documents_pdo();
                clinical_cases_ensure_schema($pdo);
                clinical_encounters_ensure_schema($pdo);
                $activeCase = clinical_cases_active_fetch($pdo, $patientId);
                if ($includeClinical) {
                    $encounters = clinical_timeline_encounters_fetch($pdo, $patientId, $limit);
                    $rows = clinical_timeline_documents_fetch($pdo, $patientId, $limit, $cursorDt, $cursorUuid);
                }
                if ($includeAgenda) {
                    $legacyPatientIds = clinical_timeline_legacy_patient_ids($pdo, $patientId);
                    $agendaAppointments = clinical_timeline_agenda_appointments_fetch($pdo, $legacyPatientIds, $limit, $direction);
                }
            } catch (Throwable $e) {
                $msg = trim((string)$e->getMessage());
                clinical_send_response([
                    'ok' => false,
                    'error' => [
                        'code' => 'server_error',
                        'message' => ($msg !== '') ? $msg : 'server error',
                    ],
                    'message' => '',
                    'data' => null,
                    'meta' => [
                        'method' => 'GET',
                        'route' => 'patients/{patient_id}/timeline',
                    ],
                ], 500);
                return;
            }

            $encounterItems = [];
            $appointmentItems = [];
            $documentItems = [];
            $documentItemSeen = [];
            $encounterAppointmentMap = [];
            $latestEncounterIdByAppointment = [];

            if ($includeClinical) {
                foreach ($encounters as $encounter) {
                    $appt = trim((string)($encounter['appointment_id'] ?? ''));
                    $eid = (int)($encounter['encounter_id'] ?? 0);
                    if ($appt !== '' && $eid > 0 && !isset($latestEncounterIdByAppointment[$appt])) {
                        $latestEncounterIdByAppointment[$appt] = $eid;
                    }
                }
            }

            if ($includeClinical) {
                foreach ($encounters as $encounter) {
                    $appointmentId = trim((string)($encounter['appointment_id'] ?? ''));
                    $encounterId = (int)($encounter['encounter_id'] ?? 0);
                    $encounterDt = (string)($encounter['encounter_dt'] ?? '');
                    if ($encounterDt === '') {
                        continue;
                    }
                    $encounterKey = clinical_encounter_key($encounterId, $appointmentId);
                    if ($appointmentId === '') {
                        $appointmentId = clinical_timeline_extract_appointment_id([
                            'encounter_key' => $encounterKey,
                            'links' => ['appointment_id' => null],
                        ]);
                    }
                    $sortKey = $encounterKey;

                    if ($appointmentId !== '') {
                        $encounterAppointmentMap[$appointmentId] = true;
                    }

                    $isLatestByAppointment = ($appointmentId !== ''
                        && ((int)($latestEncounterIdByAppointment[$appointmentId] ?? 0) === $encounterId));
                    $encounterDocs = clinical_timeline_encounter_documents_fetch(
                        $pdo,
                        $patientId,
                        $encounterId,
                        $appointmentId,
                        $isLatestByAppointment
                    );
                    $encounterDate = clinical_timeline_date_only($encounterDt);
                    $docItems = [];
                    foreach ($encounterDocs as $docRow) {
                        $docItem = [
                            'document_uuid' => (string)($docRow['document_uuid'] ?? ''),
                            'document_type' => (string)($docRow['document_type'] ?? ''),
                            'event_datetime' => (string)($docRow['event_datetime'] ?? ''),
                            'summary' => (string)($docRow['summary'] ?? ''),
                        ];
                        $docDate = clinical_timeline_date_only((string)($docRow['event_datetime'] ?? ''));
                        if ($encounterDate !== '' && $docDate !== '' && $encounterDate === $docDate) {
                            $docItems[] = $docItem;
                            continue;
                        }

                        $documentUuid = trim((string)($docRow['document_uuid'] ?? ''));
                        if ($documentUuid !== '' && isset($documentItemSeen[$documentUuid])) {
                            continue;
                        }
                        $documentItems[] = clinical_timeline_document_item_from_row($docRow, $patientId, $encounterId);
                        if ($documentUuid !== '') {
                            $documentItemSeen[$documentUuid] = true;
                        }
                    }
                    $docPreview = array_slice($docItems, 0, 3);
                    $flags = clinical_timeline_encounter_flags($docItems);

                    $clinicalPayload = [
                        'has_vitals' => $flags['has_vitals'],
                        'has_note' => $flags['has_note'],
                        'has_prescription' => $flags['has_prescription'],
                        'has_orders' => $flags['has_orders'],
                        'has_results' => $flags['has_results'],
                        'documents_count' => count($docItems),
                        'documents_preview' => $docPreview,
                    ];
                    if ($includeDocs) {
                        $clinicalPayload['documents'] = $docItems;
                    }

                    $encounterItems[] = [
                        'item_type' => 'encounter',
                        'has_encounter' => true,
                        'latest_encounter_key' => $encounterKey,
                        'ref' => $encounterKey,
                        'encounter_key' => $encounterKey,
                        'event_datetime' => $encounterDt,
                        'sort_datetime' => $encounterDt,
                        'sort_key' => $sortKey,
                        'links' => [
                            'patient_id' => $patientId,
                            'appointment_id' => ($appointmentId !== '' ? $appointmentId : null),
                            'encounter_id' => ($encounterId > 0 ? $encounterId : null),
                            'hospital_stay_id' => null,
                        ],
                        'clinical' => $clinicalPayload,
                    ];
                }
            }

            if ($includeAgenda) {
                foreach ($agendaAppointments as $appt) {
                    $appointmentId = trim((string)($appt['appointment_id'] ?? ''));
                    if ($appointmentId === '') {
                        continue;
                    }
                    if (isset($encounterAppointmentMap[$appointmentId])) {
                        continue;
                    }

                    $appointmentItems[] = [
                        'item_type' => 'appointment',
                        'has_encounter' => false,
                        'latest_encounter_key' => null,
                        'ref' => 'appt:' . $appointmentId,
                        'encounter_key' => 'appt:' . $appointmentId,
                        'event_datetime' => (string)($appt['start_at'] ?? ''),
                        'sort_datetime' => (string)($appt['start_at'] ?? ''),
                        'sort_key' => 'appt:' . $appointmentId,
                        'links' => [
                            'patient_id' => $patientId,
                            'appointment_id' => $appointmentId,
                            'encounter_id' => null,
                            'hospital_stay_id' => null,
                        ],
                        'agenda' => [
                            'patient_id_legacy' => (string)($appt['patient_id'] ?? ''),
                            'doctor_id' => $appt['doctor_id'] ?? null,
                            'consultorio_id' => $appt['consultorio_id'] ?? null,
                            'reason_code' => $appt['reason_code'] ?? null,
                            'reason_text' => $appt['reason_text'] ?? null,
                            'start_at' => (string)($appt['start_at'] ?? ''),
                            'end_at' => (string)($appt['end_at'] ?? ''),
                            'modality' => $appt['modality'] ?? null,
                            'status' => $appt['status'] ?? null,
                            'channel_origin' => $appt['channel_origin'] ?? null,
                            'created_by_role' => $appt['created_by_role'] ?? null,
                            'created_by_id' => $appt['created_by_id'] ?? null,
                        ],
                    ];
                }
            }

            if ($includeClinical) {
                foreach ($rows as $row) {
                    $documentUuid = (string)($row['document_uuid'] ?? '');
                    if ($documentUuid !== '' && isset($documentItemSeen[$documentUuid])) {
                        continue;
                    }
                    $documentItems[] = clinical_timeline_document_item_from_row($row, $patientId);
                    if ($documentUuid !== '') {
                        $documentItemSeen[$documentUuid] = true;
                    }
                }
            }

            if ($includeClinical && $encounterItems !== []) {
                $dedupedEncounterItems = [];
                $byAppointment = [];
                foreach ($encounterItems as $encItem) {
                    if (!is_array($encItem)) {
                        continue;
                    }
                    $links = is_array($encItem['links'] ?? null) ? $encItem['links'] : [];
                    $apptId = trim((string)($links['appointment_id'] ?? ''));
                    if ($apptId === '') {
                        $dedupedEncounterItems[] = $encItem;
                        continue;
                    }
                    $encounterDt = trim((string)($encItem['event_datetime'] ?? ''));
                    $encounterTs = strtotime($encounterDt);
                    if ($encounterTs === false) {
                        $encounterTs = 0;
                    }
                    $encounterId = (int)($links['encounter_id'] ?? 0);
                    $current = $byAppointment[$apptId] ?? null;
                    if (!is_array($current)) {
                        $byAppointment[$apptId] = [
                            'item' => $encItem,
                            'ts' => $encounterTs,
                            'id' => $encounterId,
                        ];
                        continue;
                    }
                    $replace = false;
                    if ($encounterTs > (int)$current['ts']) {
                        $replace = true;
                    } elseif ($encounterTs === (int)$current['ts'] && $encounterId > (int)$current['id']) {
                        $replace = true;
                    }
                    if ($replace) {
                        $byAppointment[$apptId] = [
                            'item' => $encItem,
                            'ts' => $encounterTs,
                            'id' => $encounterId,
                        ];
                    }
                }
                foreach ($byAppointment as $group) {
                    if (is_array($group) && is_array($group['item'] ?? null)) {
                        $dedupedEncounterItems[] = $group['item'];
                    }
                }
                $encounterItems = $dedupedEncounterItems;
            }

            $items = array_merge($encounterItems, $appointmentItems, $documentItems);

            $activeCaseId = (int)($activeCase['case_id'] ?? 0);
            $activeCaseTitle = (string)($activeCase['title'] ?? '');
            $appointmentCaseSet = [];
            if ($activeCaseId > 0) {
                $caseItems = clinical_case_items_fetch($pdo, $activeCaseId);
                foreach ($caseItems as $ci) {
                    $ciType = strtolower(trim((string)($ci['item_type'] ?? '')));
                    $ciRef = trim((string)($ci['item_ref'] ?? ''));
                    if ($ciType !== 'appointment' || $ciRef === '') {
                        continue;
                    }
                    $appointmentCaseSet[$ciRef] = true;
                }
            }

            foreach ($items as &$timelineItem) {
                if (!is_array($timelineItem)) {
                    continue;
                }

                $appointmentId = clinical_timeline_extract_appointment_id($timelineItem);
                $appointmentRef = ($appointmentId !== '') ? ('appt:' . $appointmentId) : '';
                $inActiveCase = ($appointmentRef !== '' && isset($appointmentCaseSet[$appointmentRef]));

                // case_id/case_title are set only when item is in active case; membership is explicit with is_in_active_case.
                $timelineItem['case_id'] = $inActiveCase && $activeCaseId > 0 ? $activeCaseId : null;
                $timelineItem['case_title'] = $inActiveCase && $activeCaseTitle !== '' ? $activeCaseTitle : null;
                $timelineItem['is_in_active_case'] = $inActiveCase;

                $classification = classify_timeline_item($timelineItem);
                $timelineItem['category'] = (string)($classification['category'] ?? 'other');
                $timelineItem['subtype'] = (string)($classification['subtype'] ?? 'unknown');
                $timelineItem['category_label'] = (string)($classification['category_label'] ?? 'Otros');
                $timelineItem['subtype_label'] = (string)($classification['subtype_label'] ?? 'Sin clasificar');

                $catalogV11 = classify_catalog_v11($timelineItem);
                $timelineItem['catalog_group'] = (string)($catalogV11['catalog_group'] ?? 'other');
                $timelineItem['catalog_group_label'] = (string)($catalogV11['catalog_group_label'] ?? 'Otros');
                $timelineItem['catalog_phase'] = $catalogV11['catalog_phase'] ?? null;
                $timelineItem['catalog_phase_label'] = $catalogV11['catalog_phase_label'] ?? null;
                $timelineItem['catalog_priority'] = (int)($catalogV11['catalog_priority'] ?? 999);

                $semantic = clinical_timeline_semantic_classify($timelineItem);
                $timelineItem['clinical_category'] = (string)($semantic['clinical_category'] ?? 'documento');
                $timelineItem['study_role'] = $semantic['study_role'] ?? null;
            }
            unset($timelineItem);

            $bundleIds = [];
            foreach ($items as $timelineItem) {
                if (!is_array($timelineItem)) {
                    continue;
                }
                $bundleId = trim((string)($timelineItem['media_bundle_id'] ?? ''));
                if ($bundleId !== '') {
                    $bundleIds[$bundleId] = true;
                }
            }

            $bundleNotesById = [];
            if ($includeClinical && $bundleIds !== []) {
                $bundleNotesById = clinical_timeline_bundle_notes_map($pdo, $patientId, array_keys($bundleIds));
            }
            foreach ($items as &$timelineItem) {
                if (!is_array($timelineItem)) {
                    continue;
                }
                $bundleId = trim((string)($timelineItem['media_bundle_id'] ?? ''));
                if ($bundleId === '') {
                    continue;
                }
                $timelineItem['bundle_notes'] = $bundleNotesById[$bundleId] ?? [
                    'has_notes' => false,
                    'excerpt' => '',
                ];
            }
            unset($timelineItem);

            if ($includeAgenda) {
                // TODO(timeline-v1): unify cursor across agenda + clinical streams.
                $cursorNext = null;
            } elseif (count($encounters) > 0) {
                // TODO(timeline-v1): unify cursor across mixed encounter + document streams.
                $cursorNext = null;
            } elseif (count($rows) === $limit && $rows !== []) {
                $last = $rows[count($rows) - 1];
                $cursorNext = clinical_timeline_encode_cursor(
                    (string)($last['event_datetime'] ?? ''),
                    (string)($last['document_uuid'] ?? '')
                );
            }
        }

        clinical_send_response([
            'ok' => true,
            'error' => null,
            'message' => '',
            'data' => [
                'patient_id' => $patientId,
                'range' => [
                    'mode' => 'cursor',
                    'limit' => $limit,
                    'direction' => $direction,
                    'cursor_next' => $cursorNext,
                    'cursor_prev' => null,
                ],
                'items' => $items,
            ],
            'meta' => [
                'method' => 'GET',
                'route' => 'patients/{patient_id}/timeline',
                'scaffold' => $isScaffold,
            ],
        ], 200);
        return;
    }

    if (($segments[0] ?? '') === 'patients' && ($segments[2] ?? '') === 'history' && count($segments) === 3) {
        $historyMeta = [
            'method' => $method,
            'route' => 'patients/{patient_id}/history',
            'source' => 'clinical_record_entries',
        ];

        if ($method !== 'GET' && $method !== 'PUT') {
            clinical_send_response([
                'ok' => false,
                'error' => 'not_found',
                'message' => 'route not found',
                'data' => null,
                'meta' => $historyMeta,
            ], 404);
            return;
        }

        $patientId = trim((string)$segments[1]);
        if ($patientId === '') {
            clinical_send_response([
                'ok' => false,
                'error' => ['code' => 'bad_request', 'message' => 'patient_id requerido'],
                'message' => '',
                'data' => null,
                'meta' => $historyMeta,
            ], 400);
            return;
        }

        try {
            $pdo = clinical_documents_pdo();
            if (!clinical_patient_exists($pdo, $patientId)) {
                clinical_send_response([
                    'ok' => false,
                    'error' => ['code' => 'not_found', 'message' => 'patient no encontrado'],
                    'message' => '',
                    'data' => null,
                    'meta' => $historyMeta,
                ], 404);
                return;
            }
        } catch (Throwable $e) {
            $msg = trim((string)$e->getMessage());
            clinical_send_response([
                'ok' => false,
                'error' => ['code' => 'server_error', 'message' => ($msg !== '' ? $msg : 'server error')],
                'message' => '',
                'data' => null,
                'meta' => $historyMeta,
            ], 500);
            return;
        }

        if ($method === 'GET') {
            try {
                $record = clinical_history_fetch_draft($pdo, $patientId);
            } catch (Throwable $e) {
                $msg = trim((string)$e->getMessage());
                clinical_send_response([
                    'ok' => false,
                    'error' => ['code' => 'server_error', 'message' => ($msg !== '' ? $msg : 'server error')],
                    'message' => '',
                    'data' => null,
                    'meta' => $historyMeta,
                ], 500);
                return;
            }

            clinical_send_response([
                'ok' => true,
                'error' => null,
                'message' => '',
                'data' => [
                    'record' => $record,
                ],
                'meta' => $historyMeta,
            ], 200);
            return;
        }

        $bodyResult = clinical_read_json_body();
        if (($bodyResult['ok'] ?? false) !== true) {
            clinical_send_response([
                'ok' => false,
                'error' => ['code' => 'bad_request', 'message' => (string)($bodyResult['error'] ?? 'invalid body')],
                'message' => '',
                'data' => null,
                'meta' => $historyMeta,
            ], 400);
            return;
        }

        $body = is_array($bodyResult['data'] ?? null) ? (array)$bodyResult['data'] : [];
        $status = trim((string)($body['status'] ?? 'draft'));
        if ($status === '') {
            $status = 'draft';
        }
        if ($status !== 'draft') {
            clinical_send_response([
                'ok' => false,
                'error' => ['code' => 'invalid_params', 'message' => 'status sólo puede ser draft en esta fase'],
                'message' => '',
                'data' => null,
                'meta' => $historyMeta,
            ], 400);
            return;
        }

        if (!array_key_exists('payload', $body) || !is_array($body['payload'])) {
            clinical_send_response([
                'ok' => false,
                'error' => ['code' => 'invalid_params', 'message' => 'payload debe ser objeto JSON'],
                'message' => '',
                'data' => null,
                'meta' => $historyMeta,
            ], 400);
            return;
        }

        $payload = (array)$body['payload'];
        if (!clinical_history_payload_is_object($payload)) {
            clinical_send_response([
                'ok' => false,
                'error' => ['code' => 'invalid_params', 'message' => 'payload debe ser objeto JSON'],
                'message' => '',
                'data' => null,
                'meta' => $historyMeta,
            ], 400);
            return;
        }

        try {
            $record = clinical_history_upsert_draft($pdo, $patientId, $payload);
        } catch (Throwable $e) {
            $msg = trim((string)$e->getMessage());
            clinical_send_response([
                'ok' => false,
                'error' => ['code' => 'server_error', 'message' => ($msg !== '' ? $msg : 'server error')],
                'message' => '',
                'data' => null,
                'meta' => $historyMeta,
            ], 500);
            return;
        }

        clinical_send_response([
            'ok' => true,
            'error' => null,
            'message' => 'historia clinica guardada',
            'data' => [
                'record' => $record,
            ],
            'meta' => $historyMeta,
        ], 200);
        return;
    }

    if (($segments[0] ?? '') === 'patients' && ($segments[2] ?? '') === 'physical-exam' && count($segments) === 3) {
        $physicalExamMeta = [
            'method' => $method,
            'route' => 'patients/{patient_id}/physical-exam',
            'source' => 'clinical_record_entries',
        ];

        if ($method !== 'GET' && $method !== 'PUT') {
            clinical_send_response([
                'ok' => false,
                'error' => 'not_found',
                'message' => 'route not found',
                'data' => null,
                'meta' => $physicalExamMeta,
            ], 404);
            return;
        }

        $patientId = trim((string)$segments[1]);
        if ($patientId === '') {
            clinical_send_response([
                'ok' => false,
                'error' => ['code' => 'bad_request', 'message' => 'patient_id requerido'],
                'message' => '',
                'data' => null,
                'meta' => $physicalExamMeta,
            ], 400);
            return;
        }

        try {
            $pdo = clinical_documents_pdo();
            if (!clinical_patient_exists($pdo, $patientId)) {
                clinical_send_response([
                    'ok' => false,
                    'error' => ['code' => 'not_found', 'message' => 'patient no encontrado'],
                    'message' => '',
                    'data' => null,
                    'meta' => $physicalExamMeta,
                ], 404);
                return;
            }
        } catch (Throwable $e) {
            $msg = trim((string)$e->getMessage());
            clinical_send_response([
                'ok' => false,
                'error' => ['code' => 'server_error', 'message' => ($msg !== '' ? $msg : 'server error')],
                'message' => '',
                'data' => null,
                'meta' => $physicalExamMeta,
            ], 500);
            return;
        }

        if ($method === 'GET') {
            try {
                $record = clinical_record_entry_fetch_draft($pdo, $patientId, 'exploracion_fisica');
            } catch (Throwable $e) {
                $msg = trim((string)$e->getMessage());
                clinical_send_response([
                    'ok' => false,
                    'error' => ['code' => 'server_error', 'message' => ($msg !== '' ? $msg : 'server error')],
                    'message' => '',
                    'data' => null,
                    'meta' => $physicalExamMeta,
                ], 500);
                return;
            }

            clinical_send_response([
                'ok' => true,
                'error' => null,
                'message' => '',
                'data' => [
                    'record' => $record,
                ],
                'meta' => $physicalExamMeta,
            ], 200);
            return;
        }

        $bodyResult = clinical_read_json_body();
        if (($bodyResult['ok'] ?? false) !== true) {
            clinical_send_response([
                'ok' => false,
                'error' => ['code' => 'bad_request', 'message' => (string)($bodyResult['error'] ?? 'invalid body')],
                'message' => '',
                'data' => null,
                'meta' => $physicalExamMeta,
            ], 400);
            return;
        }

        $body = is_array($bodyResult['data'] ?? null) ? (array)$bodyResult['data'] : [];
        $status = trim((string)($body['status'] ?? 'draft'));
        if ($status === '') {
            $status = 'draft';
        }
        if ($status !== 'draft') {
            clinical_send_response([
                'ok' => false,
                'error' => ['code' => 'invalid_params', 'message' => 'status sólo puede ser draft en esta fase'],
                'message' => '',
                'data' => null,
                'meta' => $physicalExamMeta,
            ], 400);
            return;
        }

        if (!array_key_exists('payload', $body) || !is_array($body['payload'])) {
            clinical_send_response([
                'ok' => false,
                'error' => ['code' => 'invalid_params', 'message' => 'payload debe ser objeto JSON'],
                'message' => '',
                'data' => null,
                'meta' => $physicalExamMeta,
            ], 400);
            return;
        }

        $payload = (array)$body['payload'];
        if (!clinical_record_payload_is_object($payload)) {
            clinical_send_response([
                'ok' => false,
                'error' => ['code' => 'invalid_params', 'message' => 'payload debe ser objeto JSON'],
                'message' => '',
                'data' => null,
                'meta' => $physicalExamMeta,
            ], 400);
            return;
        }

        try {
            $record = clinical_record_entry_upsert_draft($pdo, $patientId, 'exploracion_fisica', $payload, 'no se pudo guardar exploracion fisica');
        } catch (Throwable $e) {
            $msg = trim((string)$e->getMessage());
            clinical_send_response([
                'ok' => false,
                'error' => ['code' => 'server_error', 'message' => ($msg !== '' ? $msg : 'server error')],
                'message' => '',
                'data' => null,
                'meta' => $physicalExamMeta,
            ], 500);
            return;
        }

        clinical_send_response([
            'ok' => true,
            'error' => null,
            'message' => 'exploracion fisica guardada',
            'data' => [
                'record' => $record,
            ],
            'meta' => $physicalExamMeta,
        ], 200);
        return;
    }

    if ($method === 'GET' && $route === 'health') {
        clinical_send_response([
            'ok' => true,
            'error' => null,
            'message' => 'clinical service healthy',
            'data' => [
                'service' => 'clinical',
                'status' => 'up',
            ],
            'meta' => [
                'route' => 'health',
                'method' => 'GET',
            ],
        ], 200);
        return;
    }

    if ($method === 'GET' && $route === 'version') {
        clinical_send_response([
            'ok' => true,
            'error' => null,
            'message' => 'clinical gateway version',
            'data' => [
                'service' => 'clinical',
                'version' => 'v1',
                'build' => clinical_build_tag(),
            ],
            'meta' => [
                'route' => 'version',
                'method' => 'GET',
            ],
        ], 200);
        return;
    }
    if ($method === 'GET' && $route === 'patient-id/inspect') {
        $patientId = trim((string)($_GET['patient_id'] ?? ''));
        if ($patientId === '') {
            clinical_send_response([
                'ok' => false,
                'error' => 'bad_request',
                'message' => 'patient_id requerido',
                'data' => null,
                'meta' => [
                    'method' => 'GET',
                    'route' => 'patient-id/inspect',
                ],
            ], 400);
            return;
        }

        $inspection = clinical_inspect_patient_id_kind($patientId);
        clinical_send_response([
            'ok' => true,
            'error' => null,
            'message' => 'patient_id inspected',
            'data' => [
                'patient_id' => $patientId,
                'kind' => $inspection['kind'],
                'notes' => $inspection['notes'],
            ],
            'meta' => [
                'method' => 'GET',
                'route' => 'patient-id/inspect',
            ],
        ], 200);
        return;
    }

    if ($method === 'POST' && $route === 'patient-id/resolve') {
        $resolveMeta = [
            'method' => 'POST',
            'route' => 'patient-id/resolve',
            'resolver_version' => 'v2',
            'bridge' => 'clinical_patient_identity_bridge',
        ];

        $bodyResult = clinical_read_json_body();
        if ($bodyResult['ok'] !== true) {
            clinical_send_response([
                'ok' => false,
                'error' => 'bad_request',
                'message' => (string)$bodyResult['error'],
                'data' => null,
                'meta' => $resolveMeta,
            ], 400);
            return;
        }

        $body = (array)$bodyResult['data'];

        if (array_key_exists('patient_id', $body)) {
            if (!is_scalar($body['patient_id'])) {
                clinical_send_response([
                    'ok' => false,
                    'error' => 'invalid_params',
                    'message' => 'patient_id must be a scalar string',
                    'data' => null,
                    'meta' => $resolveMeta,
                ], 400);
                return;
            }

            $patientId = trim((string)$body['patient_id']);
            if ($patientId === '' || strlen($patientId) < 8) {
                clinical_send_response([
                    'ok' => false,
                    'error' => 'invalid_params',
                    'message' => 'patient_id must be a non-empty string with length >= 8',
                    'data' => null,
                    'meta' => $resolveMeta,
                ], 400);
                return;
            }

            $patientInspection = clinical_inspect_patient_id_kind($patientId);
            if (($patientInspection['kind'] ?? '') !== 'canonical') {
                clinical_send_response([
                    'ok' => false,
                    'error' => 'invalid_params',
                    'message' => 'patient_id must be canonical',
                    'data' => null,
                    'meta' => $resolveMeta,
                ], 400);
                return;
            }

            try {
                $pdo = clinical_documents_pdo();
                if (!clinical_patient_exists($pdo, $patientId)) {
                    clinical_send_response([
                        'ok' => false,
                        'error' => 'not_found',
                        'message' => 'patient not found',
                        'data' => null,
                        'meta' => $resolveMeta,
                    ], 404);
                    return;
                }
            } catch (Throwable $e) {
                $msg = trim((string)$e->getMessage());
                clinical_send_response([
                    'ok' => false,
                    'error' => 'server_error',
                    'message' => ($msg !== '') ? $msg : 'server error',
                    'data' => null,
                    'meta' => $resolveMeta,
                ], 500);
                return;
            }

            clinical_send_response([
                'ok' => true,
                'error' => null,
                'message' => 'patient resolved',
                'data' => [
                    'patient_id' => $patientId,
                    'confidence' => 1.0,
                    'strategy' => 'already_canonical',
                ],
                'meta' => $resolveMeta,
            ], 200);
            return;
        }

        if (array_key_exists('legacy', $body)) {
            $legacy = $body['legacy'];
            if (!is_array($legacy)) {
                clinical_send_response([
                    'ok' => false,
                    'error' => 'invalid_params',
                    'message' => 'legacy debe ser objeto',
                    'data' => null,
                    'meta' => $resolveMeta,
                ], 400);
                return;
            }

            if (!array_key_exists('legacy_patient_id', $legacy) || !is_scalar($legacy['legacy_patient_id'])) {
                clinical_send_response([
                    'ok' => false,
                    'error' => 'invalid_params',
                    'message' => 'legacy.legacy_patient_id debe ser string',
                    'data' => null,
                    'meta' => $resolveMeta,
                ], 400);
                return;
            }

            $legacyPatientId = trim((string)$legacy['legacy_patient_id']);
            if ($legacyPatientId === '') {
                clinical_send_response([
                    'ok' => false,
                    'error' => 'invalid_params',
                    'message' => 'legacy.legacy_patient_id requerido',
                    'data' => null,
                    'meta' => $resolveMeta,
                ], 400);
                return;
            }

            try {
                $pdo = clinical_documents_pdo();
                clinical_ensure_identity_bridge_schema($pdo);

                $stmt = $pdo->prepare(
                    'SELECT canonical_patient_id, strategy, confidence
                     FROM clinical_patient_identity_bridge
                     WHERE legacy_patient_id = :legacy
                     LIMIT 1'
                );
                $stmt->execute([':legacy' => $legacyPatientId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (Throwable $e) {
                $msg = trim((string)$e->getMessage());
                clinical_send_response([
                    'ok' => false,
                    'error' => 'server_error',
                    'message' => ($msg !== '') ? $msg : 'server error',
                    'data' => null,
                    'meta' => $resolveMeta,
                ], 500);
                return;
            }

            if (is_array($row)) {
                $canonicalPatientId = trim((string)($row['canonical_patient_id'] ?? ''));
                $canonicalInspection = clinical_inspect_patient_id_kind($canonicalPatientId);
                if ($canonicalPatientId === '' || ($canonicalInspection['kind'] ?? '') !== 'canonical') {
                    clinical_send_response([
                        'ok' => false,
                        'error' => 'invalid_bridge_mapping',
                        'message' => 'identity bridge mapping invalid',
                        'data' => null,
                        'meta' => $resolveMeta,
                    ], 409);
                    return;
                }

                try {
                    if (!clinical_patient_exists($pdo, $canonicalPatientId)) {
                        clinical_send_response([
                            'ok' => false,
                            'error' => 'not_found',
                            'message' => 'mapped patient not found',
                            'data' => null,
                            'meta' => $resolveMeta,
                        ], 404);
                        return;
                    }
                } catch (Throwable $e) {
                    $msg = trim((string)$e->getMessage());
                    clinical_send_response([
                        'ok' => false,
                        'error' => 'server_error',
                        'message' => ($msg !== '') ? $msg : 'server error',
                        'data' => null,
                        'meta' => $resolveMeta,
                    ], 500);
                    return;
                }

                clinical_send_response([
                    'ok' => true,
                    'error' => null,
                    'message' => 'legacy mapped via identity bridge',
                    'data' => [
                        'patient_id' => $canonicalPatientId,
                        'confidence' => (float)$row['confidence'],
                        'strategy' => (string)$row['strategy'],
                        'legacy_patient_id' => $legacyPatientId,
                    ],
                    'meta' => $resolveMeta,
                ], 200);
                return;
            }

            clinical_send_response([
                'ok' => false,
                'error' => 'not_ready',
                'message' => 'legacy identity not mapped yet',
                'data' => [
                    'required_next' => 'bridge_mapping_required',
                    'received' => true,
                ],
                'meta' => $resolveMeta,
            ], 409);
            return;
        }

        clinical_send_response([
            'ok' => false,
            'error' => 'invalid_params',
            'message' => 'patient_id o legacy requerido',
            'data' => null,
            'meta' => $resolveMeta,
        ], 400);
        return;
    }

    if ($route === 'identity-bridge/lookup') {
        $meta = clinical_identity_bridge_admin_meta((string)$method, 'identity-bridge/lookup');
        if ($method !== 'GET') {
            clinical_send_response([
                'ok' => false,
                'error' => 'not_found',
                'message' => 'route not found',
                'data' => null,
                'meta' => $meta,
            ], 404);
            return;
        }

        if (!clinical_is_local_or_dev()) {
            clinical_send_response([
                'ok' => false,
                'error' => 'forbidden',
                'message' => 'dev_only',
                'data' => null,
                'meta' => $meta,
            ], 403);
            return;
        }

        $legacyPatientId = trim((string)($_GET['legacy_patient_id'] ?? ''));
        if ($legacyPatientId === '') {
            clinical_send_response([
                'ok' => false,
                'error' => 'bad_request',
                'message' => 'legacy_patient_id requerido',
                'data' => null,
                'meta' => $meta,
            ], 400);
            return;
        }

        try {
            $pdo = clinical_documents_pdo();
            clinical_ensure_identity_bridge_schema($pdo);
            $stmt = $pdo->prepare(
                'SELECT legacy_patient_id, canonical_patient_id, strategy, confidence, created_at
                 FROM clinical_patient_identity_bridge
                 WHERE legacy_patient_id = :l
                 LIMIT 1'
            );
            $stmt->execute([':l' => $legacyPatientId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $msg = trim((string)$e->getMessage());
            clinical_send_response([
                'ok' => false,
                'error' => 'server_error',
                'message' => ($msg !== '') ? $msg : 'server error',
                'data' => null,
                'meta' => $meta,
            ], 500);
            return;
        }

        if (!$row || !is_array($row)) {
            clinical_send_response([
                'ok' => false,
                'error' => 'not_found',
                'message' => 'mapping not found',
                'data' => null,
                'meta' => $meta,
            ], 404);
            return;
        }

        clinical_send_response([
            'ok' => true,
            'error' => null,
            'message' => 'mapping found',
            'data' => [
                'mapping' => [
                    'legacy_patient_id' => (string)$row['legacy_patient_id'],
                    'canonical_patient_id' => (string)$row['canonical_patient_id'],
                    'strategy' => (string)$row['strategy'],
                    'confidence' => (float)$row['confidence'],
                    'created_at' => $row['created_at'],
                ],
            ],
            'meta' => $meta,
        ], 200);
        return;
    }

    if ($route === 'identity-bridge/upsert') {
        $meta = clinical_identity_bridge_admin_meta((string)$method, 'identity-bridge/upsert');
        if ($method !== 'POST') {
            clinical_send_response([
                'ok' => false,
                'error' => 'not_found',
                'message' => 'route not found',
                'data' => null,
                'meta' => $meta,
            ], 404);
            return;
        }

        if (!clinical_is_local_or_dev()) {
            clinical_send_response([
                'ok' => false,
                'error' => 'forbidden',
                'message' => 'dev_only',
                'data' => null,
                'meta' => $meta,
            ], 403);
            return;
        }

        $bodyResult = clinical_read_json_body();
        if ($bodyResult['ok'] !== true) {
            clinical_send_response([
                'ok' => false,
                'error' => 'bad_request',
                'message' => (string)$bodyResult['error'],
                'data' => null,
                'meta' => $meta,
            ], 400);
            return;
        }

        $body = (array)$bodyResult['data'];
        $legacyPatientId = trim((string)($body['legacy_patient_id'] ?? ''));
        $canonicalPatientId = trim((string)($body['canonical_patient_id'] ?? ''));
        $strategy = trim((string)($body['strategy'] ?? ''));
        if ($strategy === '') {
            $strategy = 'manual';
        }

        $confidence = 1.0;
        if (array_key_exists('confidence', $body) && is_numeric($body['confidence'])) {
            $confidence = (float)$body['confidence'];
        }
        if ($confidence < 0.0) {
            $confidence = 0.0;
        } elseif ($confidence > 1.0) {
            $confidence = 1.0;
        }
        $confidence = round($confidence, 2);

        if ($legacyPatientId === '' || $canonicalPatientId === '') {
            clinical_send_response([
                'ok' => false,
                'error' => 'bad_request',
                'message' => 'legacy_patient_id y canonical_patient_id requeridos',
                'data' => null,
                'meta' => $meta,
            ], 400);
            return;
        }

        try {
            $pdo = clinical_documents_pdo();
            clinical_ensure_identity_bridge_schema($pdo);
            $stmt = $pdo->prepare(
                'INSERT INTO clinical_patient_identity_bridge
                    (legacy_patient_id, canonical_patient_id, strategy, confidence, created_at)
                 VALUES
                    (:legacy_patient_id, :canonical_patient_id, :strategy, :confidence, :created_at)
                 ON DUPLICATE KEY UPDATE
                    canonical_patient_id = VALUES(canonical_patient_id),
                    strategy = VALUES(strategy),
                    confidence = VALUES(confidence)'
            );
            $stmt->execute([
                ':legacy_patient_id' => $legacyPatientId,
                ':canonical_patient_id' => $canonicalPatientId,
                ':strategy' => $strategy,
                ':confidence' => $confidence,
                ':created_at' => gmdate('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $e) {
            $msg = trim((string)$e->getMessage());
            clinical_send_response([
                'ok' => false,
                'error' => 'server_error',
                'message' => ($msg !== '') ? $msg : 'server error',
                'data' => null,
                'meta' => $meta,
            ], 500);
            return;
        }

        clinical_send_response([
            'ok' => true,
            'error' => null,
            'message' => 'mapping upserted',
            'data' => [
                'legacy_patient_id' => $legacyPatientId,
                'canonical_patient_id' => $canonicalPatientId,
                'strategy' => $strategy,
                'confidence' => $confidence,
            ],
            'meta' => $meta,
        ], 200);
        return;
    }

    if ($route === 'identity-bridge/delete') {
        $meta = clinical_identity_bridge_admin_meta((string)$method, 'identity-bridge/delete');
        if ($method !== 'DELETE') {
            clinical_send_response([
                'ok' => false,
                'error' => 'not_found',
                'message' => 'route not found',
                'data' => null,
                'meta' => $meta,
            ], 404);
            return;
        }

        if (!clinical_is_local_or_dev()) {
            clinical_send_response([
                'ok' => false,
                'error' => 'forbidden',
                'message' => 'dev_only',
                'data' => null,
                'meta' => $meta,
            ], 403);
            return;
        }

        $legacyPatientId = trim((string)($_GET['legacy_patient_id'] ?? ''));
        if ($legacyPatientId === '') {
            clinical_send_response([
                'ok' => false,
                'error' => 'bad_request',
                'message' => 'legacy_patient_id requerido',
                'data' => null,
                'meta' => $meta,
            ], 400);
            return;
        }

        $deleted = false;
        try {
            $pdo = clinical_documents_pdo();
            clinical_ensure_identity_bridge_schema($pdo);
            $stmt = $pdo->prepare('DELETE FROM clinical_patient_identity_bridge WHERE legacy_patient_id = :l');
            $stmt->execute([':l' => $legacyPatientId]);
            $deleted = ($stmt->rowCount() > 0);
        } catch (Throwable $e) {
            $msg = trim((string)$e->getMessage());
            clinical_send_response([
                'ok' => false,
                'error' => 'server_error',
                'message' => ($msg !== '') ? $msg : 'server error',
                'data' => null,
                'meta' => $meta,
            ], 500);
            return;
        }

        clinical_send_response([
            'ok' => true,
            'error' => null,
            'message' => 'mapping deleted',
            'data' => [
                'deleted' => $deleted,
            ],
            'meta' => $meta,
        ], 200);
        return;
    }

    if ($route === 'debug/seed_document') {
        if ($method !== 'POST' || !clinical_debug_enabled()) {
            clinical_send_response([
                'ok' => false,
                'error' => 'not_found',
                'message' => 'route not found',
                'data' => null,
                'meta' => ['method' => $method, 'route' => $route],
            ], 404);
            return;
        }

        $body = clinical_read_json_body();
        if (($body['ok'] ?? false) !== true) {
            clinical_send_response([
                'ok' => false,
                'error' => ['code' => 'bad_request', 'message' => (string)($body['error'] ?? 'invalid body')],
                'message' => '',
                'data' => null,
                'meta' => ['method' => 'POST', 'route' => 'debug/seed_document'],
            ], 400);
            return;
        }

        $payload = is_array($body['data'] ?? null) ? $body['data'] : [];
        $patientId = trim((string)($payload['patient_id'] ?? ''));
        $appointmentId = trim((string)($payload['appointment_id'] ?? ''));
        $documentType = strtolower(trim((string)($payload['document_type'] ?? '')));
        $eventDatetime = trim((string)($payload['event_datetime'] ?? ''));
        $summary = trim((string)($payload['summary'] ?? ''));
        $contentPayload = $payload['payload'] ?? [];
        if (!is_array($contentPayload)) {
            $contentPayload = [];
        }

        if ($patientId === '' || $documentType === '') {
            clinical_send_response([
                'ok' => false,
                'error' => ['code' => 'bad_request', 'message' => 'patient_id y document_type requeridos'],
                'message' => '',
                'data' => null,
                'meta' => ['method' => 'POST', 'route' => 'debug/seed_document'],
            ], 400);
            return;
        }
        if ($eventDatetime === '') {
            $eventDatetime = gmdate('Y-m-d H:i:s');
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}$/', $eventDatetime) !== 1) {
            clinical_send_response([
                'ok' => false,
                'error' => ['code' => 'bad_request', 'message' => 'event_datetime inválido (YYYY-MM-DD HH:MM:SS)'],
                'message' => '',
                'data' => null,
                'meta' => ['method' => 'POST', 'route' => 'debug/seed_document'],
            ], 400);
            return;
        }
        if ($summary === '') {
            $summary = 'Documento demo seeded';
        }
        if (mb_strlen($summary) > 500) {
            $summary = mb_substr($summary, 0, 500);
        }

        try {
            $pdo = clinical_documents_pdo();
            $cols = clinical_table_columns($pdo, 'clinical_documents');
            if ($cols === []) {
                throw new RuntimeException('no se pudieron leer columnas de clinical_documents');
            }

            $documentUuid = clinical_generate_document_uuid();
            $now = gmdate('Y-m-d H:i:s');
            $payloadJson = json_encode($contentPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_string($payloadJson)) {
                $payloadJson = '{}';
            }

            $values = [
                'document_uuid' => $documentUuid,
                'document_type' => $documentType,
                'title' => 'Seed ' . $documentType,
                'version' => 1,
                'status' => 'signed',
                'patient_id' => $patientId,
                'encounter_id' => null,
                'hospital_stay_id' => null,
                'care_setting' => 'consulta',
                'service' => null,
                'payload_json' => $payloadJson,
                'rendered_text' => null,
                'summary' => $summary,
                'edited_flag' => 0,
                'event_datetime' => $eventDatetime,
                'widget_group' => 'timeline',
                'printable' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'generated_at' => $now,
                'signed_at' => $now,
                'created_by_user_id' => 'debug',
                'updated_by_user_id' => 'debug',
                'appointment_id' => ($appointmentId !== '' ? $appointmentId : null),
            ];

            $insertCols = [];
            $placeholders = [];
            $params = [];
            foreach ($values as $col => $val) {
                if (!isset($cols[$col])) {
                    continue;
                }
                $insertCols[] = '`' . $col . '`';
                $ph = ':c_' . $col;
                $placeholders[] = $ph;
                $params[$ph] = $val;
            }
            if ($insertCols === []) {
                throw new RuntimeException('clinical_documents sin columnas compatibles para seed');
            }

            $sql = 'INSERT INTO clinical_documents (' . implode(', ', $insertCols) . ') VALUES (' . implode(', ', $placeholders) . ')';
            $stmt = $pdo->prepare($sql);
            foreach ($params as $ph => $val) {
                if ($val === null) {
                    $stmt->bindValue($ph, null, PDO::PARAM_NULL);
                } else {
                    $stmt->bindValue($ph, (string)$val, PDO::PARAM_STR);
                }
            }
            $stmt->execute();
        } catch (Throwable $e) {
            $msg = trim((string)$e->getMessage());
            clinical_send_response([
                'ok' => false,
                'error' => ['code' => 'server_error', 'message' => ($msg !== '' ? $msg : 'server error')],
                'message' => '',
                'data' => null,
                'meta' => ['method' => 'POST', 'route' => 'debug/seed_document'],
            ], 500);
            return;
        }

        clinical_send_response([
            'ok' => true,
            'error' => null,
            'message' => 'seed document created',
            'data' => [
                'document_uuid' => $documentUuid,
                'document_type' => $documentType,
                'event_datetime' => $eventDatetime,
                'summary' => $summary,
            ],
            'meta' => ['method' => 'POST', 'route' => 'debug/seed_document'],
        ], 201);
        return;
    }

    if ($route === 'debug/seed_encounter') {
        if ($method !== 'POST' || !clinical_debug_enabled()) {
            clinical_send_response([
                'ok' => false,
                'error' => 'not_found',
                'message' => 'route not found',
                'data' => null,
                'meta' => ['method' => $method, 'route' => $route],
            ], 404);
            return;
        }

        $body = clinical_read_json_body();
        if (($body['ok'] ?? false) !== true) {
            clinical_send_response([
                'ok' => false,
                'error' => ['code' => 'bad_request', 'message' => (string)($body['error'] ?? 'invalid body')],
                'message' => '',
                'data' => null,
                'meta' => ['method' => 'POST', 'route' => 'debug/seed_encounter'],
            ], 400);
            return;
        }

        $payload = is_array($body['data'] ?? null) ? $body['data'] : [];
        $patientId = trim((string)($payload['patient_id'] ?? ''));
        $appointmentId = trim((string)($payload['appointment_id'] ?? '9001'));
        $encounterDt = trim((string)($payload['encounter_dt'] ?? ''));
        $encounterType = trim((string)($payload['encounter_type'] ?? 'outpatient'));
        $status = trim((string)($payload['status'] ?? 'open'));

        if ($patientId === '') {
            clinical_send_response([
                'ok' => false,
                'error' => ['code' => 'bad_request', 'message' => 'patient_id requerido'],
                'message' => '',
                'data' => null,
                'meta' => ['method' => 'POST', 'route' => 'debug/seed_encounter'],
            ], 400);
            return;
        }

        if ($encounterDt === '') {
            $encounterDt = gmdate('Y-m-d H:i:s');
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}$/', $encounterDt) !== 1) {
            clinical_send_response([
                'ok' => false,
                'error' => ['code' => 'bad_request', 'message' => 'encounter_dt inválido (YYYY-MM-DD HH:MM:SS)'],
                'message' => '',
                'data' => null,
                'meta' => ['method' => 'POST', 'route' => 'debug/seed_encounter'],
            ], 400);
            return;
        }

        if ($encounterType === '') $encounterType = 'outpatient';
        if ($status === '') $status = 'completed';

        try {
            $pdo = clinical_documents_pdo();
            clinical_encounters_ensure_schema($pdo);

            if (!clinical_patient_exists($pdo, $patientId)) {
                clinical_send_response([
                    'ok' => false,
                    'error' => ['code' => 'not_found', 'message' => 'patient no encontrado'],
                    'message' => '',
                    'data' => null,
                    'meta' => ['method' => 'POST', 'route' => 'debug/seed_encounter'],
                ], 404);
                return;
            }

            $created = clinical_encounters_create($pdo, $patientId, ($appointmentId !== '' ? $appointmentId : null), $encounterDt, $encounterType, $status);
            $encounterId = (int)($created['encounter_id'] ?? 0);
            $appt = trim((string)($created['appointment_id'] ?? ''));
            $key = clinical_encounter_key($encounterId, $appt);
        } catch (Throwable $e) {
            $msg = trim((string)$e->getMessage());
            clinical_send_response([
                'ok' => false,
                'error' => ['code' => 'server_error', 'message' => ($msg !== '' ? $msg : 'server error')],
                'message' => '',
                'data' => null,
                'meta' => ['method' => 'POST', 'route' => 'debug/seed_encounter'],
            ], 500);
            return;
        }

        clinical_send_response([
            'ok' => true,
            'error' => null,
            'message' => 'seed encounter created',
            'data' => [
                'encounter_id' => $encounterId,
                'patient_id' => $patientId,
                'appointment_id' => ($appt !== '' ? $appt : null),
                'encounter_dt' => $encounterDt,
                'encounter_type' => $encounterType,
                'status' => $status,
                'encounter_key' => $key,
            ],
            'meta' => ['method' => 'POST', 'route' => 'debug/seed_encounter'],
        ], 201);
        return;
    }

    if (($segments[0] ?? '') === 'patients' && ($segments[2] ?? '') === 'cases' && count($segments) === 4 && $segments[3] === 'active') {
        if ($method !== 'GET') {
            clinical_send_response([
                'ok' => false,
                'error' => 'not_found',
                'message' => 'route not found',
                'data' => null,
                'meta' => ['method' => $method, 'route' => $route],
            ], 404);
            return;
        }

        $patientId = trim((string)$segments[1]);
        if ($patientId === '') {
            clinical_send_response([
                'ok' => false,
                'error' => ['code' => 'bad_request', 'message' => 'patient_id requerido'],
                'message' => '',
                'data' => null,
                'meta' => ['method' => 'GET', 'route' => 'patients/{patient_id}/cases/active'],
            ], 400);
            return;
        }

        try {
            $pdo = clinical_documents_pdo();
            clinical_cases_ensure_schema($pdo);
            $active = clinical_cases_active_fetch($pdo, $patientId);
        } catch (Throwable $e) {
            $msg = trim((string)$e->getMessage());
            clinical_send_response([
                'ok' => false,
                'error' => ['code' => 'server_error', 'message' => ($msg !== '' ? $msg : 'server error')],
                'message' => '',
                'data' => null,
                'meta' => ['method' => 'GET', 'route' => 'patients/{patient_id}/cases/active'],
            ], 500);
            return;
        }

        clinical_send_response([
            'ok' => true,
            'error' => null,
            'message' => 'active case fetched',
            'data' => $active,
            'meta' => ['method' => 'GET', 'route' => 'patients/{patient_id}/cases/active'],
        ], 200);
        return;
    }

    if (($segments[0] ?? '') === 'patients' && ($segments[2] ?? '') === 'encounters' && count($segments) === 4 && ($segments[3] ?? '') === 'active') {
        if ($method !== 'GET') {
            clinical_send_response([
                'ok' => false,
                'error' => 'not_found',
                'message' => 'route not found',
                'data' => null,
                'meta' => ['method' => $method, 'route' => $route],
            ], 404);
            return;
        }

        $patientId = trim((string)$segments[1]);
        if ($patientId === '') {
            clinical_send_response([
                'ok' => false,
                'error' => ['code' => 'bad_request', 'message' => 'patient_id requerido'],
                'message' => '',
                'data' => null,
                'meta' => ['method' => 'GET', 'route' => 'patients/{patient_id}/encounters/active'],
            ], 400);
            return;
        }

        $currentUserId = clinical_request_actor_user_id_strict();
        if ($currentUserId === '') {
            clinical_send_response([
                'ok' => false,
                'error' => ['code' => 'bad_request', 'message' => 'missing user_id'],
                'message' => 'missing user_id',
                'data' => null,
                'meta' => ['method' => 'GET', 'route' => 'patients/{patient_id}/encounters/active'],
            ], 400);
            return;
        }

        try {
            $pdo = clinical_documents_pdo();
            clinical_encounters_ensure_schema($pdo);
            if (!clinical_patient_exists($pdo, $patientId)) {
                clinical_send_response([
                    'ok' => false,
                    'error' => ['code' => 'not_found', 'message' => 'patient no encontrado'],
                    'message' => '',
                    'data' => null,
                    'meta' => ['method' => 'GET', 'route' => 'patients/{patient_id}/encounters/active'],
                ], 404);
                return;
            }
            $active = clinical_encounter_open_fetch($pdo, $patientId, $currentUserId);
        } catch (Throwable $e) {
            $msg = trim((string)$e->getMessage());
            clinical_send_response([
                'ok' => false,
                'error' => ['code' => 'server_error', 'message' => ($msg !== '' ? $msg : 'server error')],
                'message' => '',
                'data' => null,
                'meta' => ['method' => 'GET', 'route' => 'patients/{patient_id}/encounters/active'],
            ], 500);
            return;
        }

        if (!is_array($active) || $active === []) {
            clinical_send_response([
                'ok' => true,
                'error' => null,
                'message' => 'no active encounter',
                'data' => null,
                'meta' => ['method' => 'GET', 'route' => 'patients/{patient_id}/encounters/active'],
            ], 200);
            return;
        }

        clinical_send_response([
            'ok' => true,
            'error' => null,
            'message' => '',
            'data' => [
                'encounter_key' => clinical_encounter_key((int)($active['encounter_id'] ?? 0), (string)($active['appointment_id'] ?? '')),
                'patient_id' => (string)($active['patient_id'] ?? ''),
                'appointment_id' => (($active['appointment_id'] ?? null) !== '' ? $active['appointment_id'] : null),
                'event_datetime' => (string)($active['encounter_dt'] ?? ''),
                'status' => 'open',
                'opened_by_user_id' => (string)($active['opened_by_user_id'] ?? ''),
            ],
            'meta' => ['method' => 'GET', 'route' => 'patients/{patient_id}/encounters/active'],
        ], 200);
        return;
    }

    if (($segments[0] ?? '') === 'patients' && ($segments[2] ?? '') === 'encounters' && count($segments) === 3) {
        if ($method !== 'GET' && $method !== 'POST') {
            clinical_send_response([
                'ok' => false,
                'error' => 'not_found',
                'message' => 'route not found',
                'data' => null,
                'meta' => ['method' => $method, 'route' => $route],
            ], 404);
            return;
        }

        $patientId = trim((string)$segments[1]);
        if ($patientId === '') {
            clinical_send_response([
                'ok' => false,
                'error' => ['code' => 'bad_request', 'message' => 'patient_id requerido'],
                'message' => '',
                'data' => null,
                'meta' => ['method' => $method, 'route' => 'patients/{patient_id}/encounters'],
            ], 400);
            return;
        }

        try {
            $pdo = clinical_documents_pdo();
            clinical_encounters_ensure_schema($pdo);
            if (!clinical_patient_exists($pdo, $patientId)) {
                clinical_send_response([
                    'ok' => false,
                    'error' => ['code' => 'not_found', 'message' => 'patient no encontrado'],
                    'message' => '',
                    'data' => null,
                    'meta' => ['method' => $method, 'route' => 'patients/{patient_id}/encounters'],
                ], 404);
                return;
            }
        } catch (Throwable $e) {
            $msg = trim((string)$e->getMessage());
            clinical_send_response([
                'ok' => false,
                'error' => ['code' => 'server_error', 'message' => ($msg !== '' ? $msg : 'server error')],
                'message' => '',
                'data' => null,
                'meta' => ['method' => $method, 'route' => 'patients/{patient_id}/encounters'],
            ], 500);
            return;
        }

        if ($method === 'GET') {
            $limit = 20;
            $limitRaw = $_GET['limit'] ?? null;
            if ($limitRaw !== null && trim((string)$limitRaw) !== '') {
                $limitText = trim((string)$limitRaw);
                if (preg_match('/^\d+$/', $limitText) !== 1) {
                    clinical_send_response([
                        'ok' => false,
                        'error' => ['code' => 'bad_request', 'message' => 'limit debe ser int entre 1 y 100'],
                        'message' => '',
                        'data' => null,
                        'meta' => ['method' => 'GET', 'route' => 'patients/{patient_id}/encounters'],
                    ], 400);
                    return;
                }
                $limit = (int)$limitText;
                if ($limit < 1 || $limit > 100) {
                    clinical_send_response([
                        'ok' => false,
                        'error' => ['code' => 'bad_request', 'message' => 'limit debe ser int entre 1 y 100'],
                        'message' => '',
                        'data' => null,
                        'meta' => ['method' => 'GET', 'route' => 'patients/{patient_id}/encounters'],
                    ], 400);
                    return;
                }
            }

            try {
                $list = clinical_encounters_list_fetch($pdo, $patientId, $limit);
            } catch (Throwable $e) {
                $msg = trim((string)$e->getMessage());
                clinical_send_response([
                    'ok' => false,
                    'error' => ['code' => 'server_error', 'message' => ($msg !== '' ? $msg : 'server error')],
                    'message' => '',
                    'data' => null,
                    'meta' => ['method' => 'GET', 'route' => 'patients/{patient_id}/encounters'],
                ], 500);
                return;
            }

            clinical_send_response([
                'ok' => true,
                'error' => null,
                'message' => '',
                'data' => $list,
                'meta' => ['method' => 'GET', 'route' => 'patients/{patient_id}/encounters'],
            ], 200);
            return;
        }

        $body = clinical_read_json_body();
        if (($body['ok'] ?? false) !== true) {
            clinical_send_response([
                'ok' => false,
                'error' => ['code' => 'bad_request', 'message' => (string)($body['error'] ?? 'invalid body')],
                'message' => '',
                'data' => null,
                'meta' => ['method' => 'POST', 'route' => 'patients/{patient_id}/encounters'],
            ], 400);
            return;
        }

        $payload = is_array($body['data'] ?? null) ? $body['data'] : [];
        $openedByUserId = clinical_request_actor_user_id_strict($payload);
        $encounterDt = trim((string)($payload['encounter_dt'] ?? ''));
        $appointmentId = trim((string)($payload['appointment_id'] ?? ''));
        $encounterType = trim((string)($payload['encounter_type'] ?? 'outpatient'));
        $status = trim((string)($payload['status'] ?? 'completed'));

        if ($encounterDt === '' || preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}$/', $encounterDt) !== 1) {
            clinical_send_response([
                'ok' => false,
                'error' => ['code' => 'bad_request', 'message' => 'encounter_dt requerido con formato YYYY-MM-DD HH:MM:SS'],
                'message' => '',
                'data' => null,
                'meta' => ['method' => 'POST', 'route' => 'patients/{patient_id}/encounters'],
            ], 400);
            return;
        }
        if (mb_strlen($appointmentId) > 64) {
            $appointmentId = mb_substr($appointmentId, 0, 64);
        }
        if ($encounterType === '') {
            $encounterType = 'outpatient';
        }
        if (mb_strlen($encounterType) > 32) {
            $encounterType = mb_substr($encounterType, 0, 32);
        }
        if ($status === '') {
            $status = 'open';
        }
        if (mb_strlen($status) > 32) {
            $status = mb_substr($status, 0, 32);
        }

        if (strtolower($status) === 'open' && $openedByUserId === '') {
            clinical_send_response([
                'ok' => false,
                'error' => ['code' => 'bad_request', 'message' => 'missing user_id'],
                'message' => 'missing user_id',
                'data' => null,
                'meta' => ['method' => 'POST', 'route' => 'patients/{patient_id}/encounters'],
            ], 400);
            return;
        }

        if (strtolower($status) === 'open') {
            try {
                $existingOpen = clinical_encounter_open_fetch($pdo, $patientId, $openedByUserId);
            } catch (Throwable $e) {
                $msg = trim((string)$e->getMessage());
                clinical_send_response([
                    'ok' => false,
                    'error' => ['code' => 'server_error', 'message' => ($msg !== '' ? $msg : 'server error')],
                    'message' => '',
                    'data' => null,
                    'meta' => ['method' => 'POST', 'route' => 'patients/{patient_id}/encounters'],
                ], 500);
                return;
            }

            if (is_array($existingOpen) && $existingOpen !== []) {
                clinical_send_response([
                    'ok' => true,
                    'error' => null,
                    'message' => 'encounter already active',
                    'data' => $existingOpen + [
                        'encounter_key' => clinical_encounter_key((int)($existingOpen['encounter_id'] ?? 0), (string)($existingOpen['appointment_id'] ?? '')),
                        'redirect_to_active' => true,
                    ],
                    'meta' => ['method' => 'POST', 'route' => 'patients/{patient_id}/encounters'],
                ], 200);
                return;
            }
        }

        try {
            $created = clinical_encounters_create(
                $pdo,
                $patientId,
                ($appointmentId !== '' ? $appointmentId : null),
                $encounterDt,
                $encounterType,
                $status,
                ($openedByUserId !== '' ? $openedByUserId : null)
            );
        } catch (Throwable $e) {
            $msg = trim((string)$e->getMessage());
            clinical_send_response([
                'ok' => false,
                'error' => ['code' => 'server_error', 'message' => ($msg !== '' ? $msg : 'server error')],
                'message' => '',
                'data' => null,
                'meta' => ['method' => 'POST', 'route' => 'patients/{patient_id}/encounters'],
            ], 500);
            return;
        }

        clinical_send_response([
            'ok' => true,
            'error' => null,
            'message' => 'encounter created',
            'data' => $created + [
                'encounter_key' => clinical_encounter_key((int)($created['encounter_id'] ?? 0), (string)($created['appointment_id'] ?? '')),
                'redirect_to_active' => false,
            ],
            'meta' => ['method' => 'POST', 'route' => 'patients/{patient_id}/encounters'],
        ], ($status === 'open' ? 200 : 201));
        return;
    }

    if (($segments[0] ?? '') === 'patients' && ($segments[2] ?? '') === 'cases' && count($segments) === 3) {
        if ($method !== 'POST' && $method !== 'GET') {
            clinical_send_response([
                'ok' => false,
                'error' => 'not_found',
                'message' => 'route not found',
                'data' => null,
                'meta' => ['method' => $method, 'route' => $route],
            ], 404);
            return;
        }

        $patientId = trim((string)$segments[1]);
        if ($patientId === '') {
            clinical_send_response([
                'ok' => false,
                'error' => ['code' => 'bad_request', 'message' => 'patient_id requerido'],
                'message' => '',
                'data' => null,
                'meta' => ['method' => $method, 'route' => 'patients/{patient_id}/cases'],
            ], 400);
            return;
        }

        try {
            $pdo = clinical_documents_pdo();
            clinical_cases_ensure_schema($pdo);
            if (!clinical_patient_exists($pdo, $patientId)) {
                clinical_send_response([
                    'ok' => false,
                    'error' => ['code' => 'not_found', 'message' => 'patient no encontrado'],
                    'message' => '',
                    'data' => null,
                    'meta' => ['method' => $method, 'route' => 'patients/{patient_id}/cases'],
                ], 404);
                return;
            }
        } catch (Throwable $e) {
            $msg = trim((string)$e->getMessage());
            clinical_send_response([
                'ok' => false,
                'error' => ['code' => 'server_error', 'message' => ($msg !== '' ? $msg : 'server error')],
                'message' => '',
                'data' => null,
                'meta' => ['method' => $method, 'route' => 'patients/{patient_id}/cases'],
            ], 500);
            return;
        }

        if ($method === 'GET') {
            try {
                $items = clinical_cases_list_fetch($pdo, $patientId);
            } catch (Throwable $e) {
                $msg = trim((string)$e->getMessage());
                clinical_send_response([
                    'ok' => false,
                    'error' => ['code' => 'server_error', 'message' => ($msg !== '' ? $msg : 'server error')],
                    'message' => '',
                    'data' => null,
                    'meta' => ['method' => 'GET', 'route' => 'patients/{patient_id}/cases'],
                ], 500);
                return;
            }

            clinical_send_response([
                'ok' => true,
                'error' => null,
                'message' => '',
                'data' => $items,
                'meta' => ['method' => 'GET', 'route' => 'patients/{patient_id}/cases'],
            ], 200);
            return;
        }

        $body = clinical_read_json_body();
        if (($body['ok'] ?? false) !== true) {
            clinical_send_response([
                'ok' => false,
                'error' => ['code' => 'bad_request', 'message' => (string)($body['error'] ?? 'invalid body')],
                'message' => '',
                'data' => null,
                'meta' => ['method' => 'POST', 'route' => 'patients/{patient_id}/cases'],
            ], 400);
            return;
        }
        $payload = is_array($body['data'] ?? null) ? $body['data'] : [];
        $title = trim((string)($payload['title'] ?? 'Caso clínico'));
        if ($title === '') {
            $title = 'Caso clínico';
        }
        if (mb_strlen($title) > 180) {
            $title = mb_substr($title, 0, 180);
        }

        try {
            $created = clinical_cases_create($pdo, $patientId, $title);
        } catch (Throwable $e) {
            $msg = trim((string)$e->getMessage());
            clinical_send_response([
                'ok' => false,
                'error' => ['code' => 'server_error', 'message' => ($msg !== '' ? $msg : 'server error')],
                'message' => '',
                'data' => null,
                'meta' => ['method' => 'POST', 'route' => 'patients/{patient_id}/cases'],
            ], 500);
            return;
        }

        clinical_send_response([
            'ok' => true,
            'error' => null,
            'message' => 'case created',
            'data' => $created,
            'meta' => ['method' => 'POST', 'route' => 'patients/{patient_id}/cases'],
        ], 201);
        return;
    }

    if (($segments[0] ?? '') === 'cases' && count($segments) === 2 && $method === 'PATCH') {
        $caseId = (int)trim((string)$segments[1]);
        if ($caseId <= 0) {
            clinical_send_response([
                'ok' => false,
                'error' => ['code' => 'bad_request', 'message' => 'case_id inválido'],
                'message' => '',
                'data' => null,
                'meta' => ['method' => 'PATCH', 'route' => 'cases/{case_id}'],
            ], 400);
            return;
        }

        $body = clinical_read_json_body();
        if (($body['ok'] ?? false) !== true) {
            clinical_send_response([
                'ok' => false,
                'error' => ['code' => 'bad_request', 'message' => (string)($body['error'] ?? 'invalid body')],
                'message' => '',
                'data' => null,
                'meta' => ['method' => 'PATCH', 'route' => 'cases/{case_id}'],
            ], 400);
            return;
        }
        $payload = is_array($body['data'] ?? null) ? $body['data'] : [];
        $title = trim((string)($payload['title'] ?? ''));
        if ($title === '') {
            clinical_send_response([
                'ok' => false,
                'error' => ['code' => 'bad_request', 'message' => 'title requerido'],
                'message' => '',
                'data' => null,
                'meta' => ['method' => 'PATCH', 'route' => 'cases/{case_id}'],
            ], 400);
            return;
        }
        if (mb_strlen($title) > 180) {
            $title = mb_substr($title, 0, 180);
        }

        try {
            $pdo = clinical_documents_pdo();
            clinical_cases_ensure_schema($pdo);
            $updated = clinical_cases_rename($pdo, $caseId, $title);
        } catch (Throwable $e) {
            $msg = trim((string)$e->getMessage());
            clinical_send_response([
                'ok' => false,
                'error' => ['code' => 'server_error', 'message' => ($msg !== '' ? $msg : 'server error')],
                'message' => '',
                'data' => null,
                'meta' => ['method' => 'PATCH', 'route' => 'cases/{case_id}'],
            ], 500);
            return;
        }

        if ($updated === null) {
            clinical_send_response([
                'ok' => false,
                'error' => ['code' => 'not_found', 'message' => 'case no encontrado'],
                'message' => '',
                'data' => null,
                'meta' => ['method' => 'PATCH', 'route' => 'cases/{case_id}'],
            ], 404);
            return;
        }

        clinical_send_response([
            'ok' => true,
            'error' => null,
            'message' => 'case updated',
            'data' => $updated,
            'meta' => ['method' => 'PATCH', 'route' => 'cases/{case_id}'],
        ], 200);
        return;
    }

    if (($segments[0] ?? '') === 'cases' && count($segments) === 3 && $segments[2] === 'activate') {
        if ($method !== 'POST') {
            clinical_send_response([
                'ok' => false,
                'error' => 'not_found',
                'message' => 'route not found',
                'data' => null,
                'meta' => ['method' => $method, 'route' => $route],
            ], 404);
            return;
        }

        $caseId = (int)trim((string)$segments[1]);
        if ($caseId <= 0) {
            clinical_send_response([
                'ok' => false,
                'error' => ['code' => 'bad_request', 'message' => 'case_id inválido'],
                'message' => '',
                'data' => null,
                'meta' => ['method' => 'POST', 'route' => 'cases/{case_id}/activate'],
            ], 400);
            return;
        }

        try {
            $pdo = clinical_documents_pdo();
            clinical_cases_ensure_schema($pdo);
            $updated = clinical_cases_activate($pdo, $caseId);
        } catch (Throwable $e) {
            $msg = trim((string)$e->getMessage());
            clinical_send_response([
                'ok' => false,
                'error' => ['code' => 'server_error', 'message' => ($msg !== '' ? $msg : 'server error')],
                'message' => '',
                'data' => null,
                'meta' => ['method' => 'POST', 'route' => 'cases/{case_id}/activate'],
            ], 500);
            return;
        }

        if ($updated === null) {
            clinical_send_response([
                'ok' => false,
                'error' => ['code' => 'not_found', 'message' => 'case no encontrado'],
                'message' => '',
                'data' => null,
                'meta' => ['method' => 'POST', 'route' => 'cases/{case_id}/activate'],
            ], 404);
            return;
        }

        clinical_send_response([
            'ok' => true,
            'error' => null,
            'message' => 'case activated',
            'data' => $updated,
            'meta' => ['method' => 'POST', 'route' => 'cases/{case_id}/activate'],
        ], 200);
        return;
    }

    if (($segments[0] ?? '') === 'cases' && count($segments) === 3 && $segments[2] === 'items') {
        if ($method !== 'POST' && $method !== 'GET') {
            clinical_send_response([
                'ok' => false,
                'error' => 'not_found',
                'message' => 'route not found',
                'data' => null,
                'meta' => ['method' => $method, 'route' => $route],
            ], 404);
            return;
        }

        $caseId = (int)trim((string)$segments[1]);
        if ($caseId <= 0) {
            clinical_send_response([
                'ok' => false,
                'error' => ['code' => 'bad_request', 'message' => 'case_id invalido'],
                'message' => '',
                'data' => null,
                'meta' => ['method' => $method, 'route' => 'cases/{case_id}/items'],
            ], 400);
            return;
        }

        if ($method === 'GET') {
            $limit = 50;
            $limitRaw = $_GET['limit'] ?? null;
            if ($limitRaw !== null && trim((string)$limitRaw) !== '') {
                $limitText = trim((string)$limitRaw);
                if (preg_match('/^\d+$/', $limitText) !== 1) {
                    clinical_send_response([
                        'ok' => false,
                        'error' => ['code' => 'bad_request', 'message' => 'limit debe ser int entre 1 y 200'],
                        'message' => '',
                        'data' => null,
                        'meta' => ['method' => 'GET', 'route' => 'cases/{case_id}/items'],
                    ], 400);
                    return;
                }
                $limit = (int)$limitText;
                if ($limit < 1 || $limit > 200) {
                    clinical_send_response([
                        'ok' => false,
                        'error' => ['code' => 'bad_request', 'message' => 'limit debe ser int entre 1 y 200'],
                        'message' => '',
                        'data' => null,
                        'meta' => ['method' => 'GET', 'route' => 'cases/{case_id}/items'],
                    ], 400);
                    return;
                }
            }

            try {
                $pdo = clinical_documents_pdo();
                clinical_cases_ensure_schema($pdo);
                $existingCase = clinical_case_get_by_id($pdo, $caseId);
                if ($existingCase === null) {
                    clinical_send_response([
                        'ok' => false,
                        'error' => 'not_found',
                        'message' => 'case not found',
                        'data' => null,
                        'meta' => ['method' => 'GET', 'route' => 'cases/{case_id}/items'],
                    ], 404);
                    return;
                }
                $items = clinical_case_items_list_fetch($pdo, $caseId, $limit);
            } catch (Throwable $e) {
                $msg = trim((string)$e->getMessage());
                clinical_send_response([
                    'ok' => false,
                    'error' => ['code' => 'server_error', 'message' => ($msg !== '' ? $msg : 'server error')],
                    'message' => '',
                    'data' => null,
                    'meta' => ['method' => 'GET', 'route' => 'cases/{case_id}/items'],
                ], 500);
                return;
            }

            clinical_send_response([
                'ok' => true,
                'error' => null,
                'message' => 'ok',
                'data' => $items,
                'meta' => ['method' => 'GET', 'route' => 'cases/{case_id}/items'],
            ], 200);
            return;
        }

        $body = clinical_read_json_body();
        if (($body['ok'] ?? false) !== true) {
            clinical_send_response([
                'ok' => false,
                'error' => ['code' => 'bad_request', 'message' => (string)($body['error'] ?? 'invalid body')],
                'message' => '',
                'data' => null,
                'meta' => ['method' => 'POST', 'route' => 'cases/{case_id}/items'],
            ], 400);
            return;
        }

        $payload = is_array($body['data'] ?? null) ? $body['data'] : [];
        $itemType = strtolower(trim((string)($payload['item_type'] ?? '')));
        $itemRef = trim((string)($payload['item_ref'] ?? ''));
        if (!clinical_case_item_type_allowed($itemType) || $itemRef === '') {
            clinical_send_response([
                'ok' => false,
                'error' => ['code' => 'bad_request', 'message' => 'item_type/item_ref inválidos'],
                'message' => '',
                'data' => null,
                'meta' => ['method' => 'POST', 'route' => 'cases/{case_id}/items'],
            ], 400);
            return;
        }

        try {
            $pdo = clinical_documents_pdo();
            clinical_cases_ensure_schema($pdo);
            $existingCase = clinical_case_get_by_id($pdo, $caseId);
            if ($existingCase === null) {
                clinical_send_response([
                    'ok' => false,
                    'error' => ['code' => 'not_found', 'message' => 'case no encontrado'],
                    'message' => '',
                    'data' => null,
                    'meta' => ['method' => 'POST', 'route' => 'cases/{case_id}/items'],
                ], 404);
                return;
            }
            $ownerCaseId = clinical_case_item_find_owner_case(
                $pdo,
                (string)($existingCase['patient_id'] ?? ''),
                $itemType,
                $itemRef
            );
            if ($ownerCaseId !== null && $ownerCaseId !== $caseId) {
                clinical_send_response([
                    'ok' => false,
                    'error' => ['code' => 'conflict', 'message' => 'item ya pertenece a otro caso'],
                    'message' => "Este elemento ya está integrado en el caso #{$ownerCaseId}. Usa 'Cambiar' para moverlo.",
                    'data' => ['owner_case_id' => $ownerCaseId],
                    'meta' => ['method' => 'POST', 'route' => 'cases/{case_id}/items'],
                ], 409);
                return;
            }
            if ($ownerCaseId === $caseId) {
                clinical_send_response([
                    'ok' => true,
                    'error' => null,
                    'message' => 'item already assigned',
                    'data' => [
                        'case_id' => $caseId,
                        'item_type' => $itemType,
                        'item_ref' => $itemRef,
                        'created' => false,
                    ],
                    'meta' => ['method' => 'POST', 'route' => 'cases/{case_id}/items'],
                ], 200);
                return;
            }
            $created = clinical_case_item_insert($pdo, $caseId, $itemType, $itemRef);
        } catch (Throwable $e) {
            $msg = trim((string)$e->getMessage());
            clinical_send_response([
                'ok' => false,
                'error' => ['code' => 'server_error', 'message' => ($msg !== '' ? $msg : 'server error')],
                'message' => '',
                'data' => null,
                'meta' => ['method' => 'POST', 'route' => 'cases/{case_id}/items'],
            ], 500);
            return;
        }

        clinical_send_response([
            'ok' => true,
            'error' => null,
            'message' => 'item assigned',
            'data' => [
                'case_id' => $caseId,
                'item_type' => $itemType,
                'item_ref' => $itemRef,
                'created' => $created,
            ],
            'meta' => ['method' => 'POST', 'route' => 'cases/{case_id}/items'],
        ], 200);
        return;
    }

    if (($segments[0] ?? '') === 'encounters') {
        if (count($segments) === 3 && ($segments[2] ?? '') === 'finalize' && $method === 'POST') {
            $encounterKey = urldecode(trim((string)$segments[1]));
            $body = clinical_read_json_body();
            $payload = (($body['ok'] ?? false) === true && is_array($body['data'] ?? null)) ? $body['data'] : [];
            $closedByUserId = clinical_request_actor_user_id($payload);

            try {
                $pdo = clinical_documents_pdo();
                clinical_encounters_ensure_schema($pdo);
                $resolved = clinical_resolve_encounter_key($pdo, $encounterKey);
                if (($resolved['ok'] ?? false) !== true) {
                    $status = (($resolved['error_code'] ?? '') === 'not_found') ? 404 : 400;
                    clinical_send_response([
                        'ok' => false,
                        'error' => (string)($resolved['error_code'] ?? 'bad_request'),
                        'message' => (string)($resolved['error_message'] ?? 'encounter inválido'),
                        'data' => null,
                        'meta' => ['method' => 'POST', 'route' => 'encounters/{encounter_key}/finalize'],
                    ], $status);
                    return;
                }
                $encounterRow = is_array($resolved['row'] ?? null) ? $resolved['row'] : [];
                $finalized = clinical_encounter_finalize($pdo, $encounterRow, $closedByUserId);
            } catch (Throwable $e) {
                $msg = trim((string)$e->getMessage());
                clinical_send_response([
                    'ok' => false,
                    'error' => 'server_error',
                    'message' => ($msg !== '' ? $msg : 'server error'),
                    'data' => null,
                    'meta' => ['method' => 'POST', 'route' => 'encounters/{encounter_key}/finalize'],
                ], 500);
                return;
            }

            clinical_send_response([
                'ok' => true,
                'error' => null,
                'message' => 'encounter finalized',
                'data' => $finalized,
                'meta' => ['method' => 'POST', 'route' => 'encounters/{encounter_key}/finalize'],
            ], 200);
            return;
        }

        if (count($segments) === 3 && ($segments[2] ?? '') === 'documents' && $method === 'POST') {
            $encounterKey = urldecode(trim((string)$segments[1]));
            $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? ''));
            $isMultipart = (strpos($contentType, 'multipart/form-data') !== false);
            $payload = [];
            $uploadFile = null;
            if ($isMultipart) {
                $payload = is_array($_POST) ? $_POST : [];
                if (isset($payload['payload']) && is_string($payload['payload'])) {
                    $payloadDecoded = json_decode((string)$payload['payload'], true);
                    if (is_array($payloadDecoded)) {
                        $payload['payload'] = $payloadDecoded;
                    }
                }
                $uploadCandidate = $_FILES['file'] ?? null;
                if (is_array($uploadCandidate) && (($uploadCandidate['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE)) {
                    $uploadFile = $uploadCandidate;
                    $uploadError = (int)($uploadFile['error'] ?? UPLOAD_ERR_NO_FILE);
                    if ($uploadError !== UPLOAD_ERR_OK) {
                        clinical_send_response([
                            'ok' => false,
                            'error' => ['code' => 'bad_request', 'message' => 'upload inválido'],
                            'message' => '',
                            'data' => null,
                            'meta' => ['method' => 'POST', 'route' => 'encounters/{encounter_key}/documents'],
                        ], 400);
                        return;
                    }
                }
            } else {
                $body = clinical_read_json_body();
                if (($body['ok'] ?? false) !== true) {
                    clinical_send_response([
                        'ok' => false,
                        'error' => ['code' => 'bad_request', 'message' => (string)($body['error'] ?? 'invalid body')],
                        'message' => '',
                        'data' => null,
                        'meta' => ['method' => 'POST', 'route' => 'encounters/{encounter_key}/documents'],
                    ], 400);
                    return;
                }
                $payload = is_array($body['data'] ?? null) ? $body['data'] : [];
            }

            $documentType = strtolower(trim((string)($payload['document_type'] ?? '')));
            $title = trim((string)($payload['title'] ?? ''));
            $summary = trim((string)($payload['summary'] ?? ''));
            $payloadData = $payload['payload'] ?? [];
            $eventDatetime = trim((string)($payload['event_datetime'] ?? ''));
            if (!is_array($payloadData)) {
                $payloadData = [];
            }
            if ($documentType === '') {
                clinical_send_response([
                    'ok' => false,
                    'error' => ['code' => 'bad_request', 'message' => 'document_type requerido'],
                    'message' => '',
                    'data' => null,
                    'meta' => ['method' => 'POST', 'route' => 'encounters/{encounter_key}/documents'],
                ], 400);
                return;
            }
            if ($documentType === 'image') {
                $requiredMediaTagKey = trim((string)($payload['media_tag_key'] ?? (($payloadData['media_tag_key'] ?? ''))));
                if ($requiredMediaTagKey === '') {
                    clinical_send_response([
                        'ok' => false,
                        'error' => ['code' => 'MEDIA_TAG_REQUIRED', 'message' => 'Selecciona una etiqueta para esta imagen.'],
                        'message' => '',
                        'data' => null,
                        'meta' => ['method' => 'POST', 'route' => 'encounters/{encounter_key}/documents'],
                    ], 400);
                    return;
                }
            }
            if ($title === '') {
                $title = 'Documento clínico (' . $documentType . ')';
            }
            if ($eventDatetime === '') {
                $eventDatetime = gmdate('Y-m-d H:i:s');
            }
            if (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}$/', $eventDatetime) !== 1) {
                clinical_send_response([
                    'ok' => false,
                    'error' => ['code' => 'bad_request', 'message' => 'event_datetime inválido (YYYY-MM-DD HH:MM:SS)'],
                    'message' => '',
                    'data' => null,
                    'meta' => ['method' => 'POST', 'route' => 'encounters/{encounter_key}/documents'],
                ], 400);
                return;
            }

            try {
                $pdo = clinical_documents_pdo();
                clinical_encounters_ensure_schema($pdo);
                $resolved = clinical_resolve_encounter_key($pdo, $encounterKey);
                if (($resolved['ok'] ?? false) !== true) {
                    $status = (($resolved['error_code'] ?? '') === 'not_found') ? 404 : 400;
                    clinical_send_response([
                        'ok' => false,
                        'error' => (string)($resolved['error_code'] ?? 'bad_request'),
                        'message' => (string)($resolved['error_message'] ?? 'encounter inválido'),
                        'data' => null,
                        'meta' => ['method' => 'POST', 'route' => 'encounters/{encounter_key}/documents'],
                    ], $status);
                    return;
                }

                $encounterRow = is_array($resolved['row'] ?? null) ? $resolved['row'] : [];
                $encounterId = (int)($encounterRow['encounter_id'] ?? 0);
                $patientId = trim((string)($encounterRow['patient_id'] ?? ''));
                $appointmentId = trim((string)($encounterRow['appointment_id'] ?? ''));
                if ($encounterId <= 0 || $patientId === '') {
                    clinical_send_response([
                        'ok' => false,
                        'error' => 'server_error',
                        'message' => 'encounter inválido',
                        'data' => null,
                        'meta' => ['method' => 'POST', 'route' => 'encounters/{encounter_key}/documents'],
                    ], 500);
                    return;
                }

                $payloadJson = json_encode($payloadData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if (!is_string($payloadJson)) {
                    $payloadJson = '{}';
                }
                $renderedText = null;
                if (is_string($payloadData['text'] ?? null)) {
                    $renderedText = (string)$payloadData['text'];
                }

                $documentUuid = clinical_generate_document_uuid();
                if (is_array($uploadFile)) {
                    $fileMeta = clinical_store_uploaded_file($uploadFile, $documentUuid);
                    $payloadData['render_mode'] = (string)($fileMeta['render_mode'] ?? 'image');
                    $payloadData['file'] = $fileMeta;
                    if ($summary === '') {
                        $summary = ($payloadData['render_mode'] === 'pdf') ? 'PDF clínico' : 'Imagen clínica';
                    }
                }
                if ($documentType === 'image' || $documentType === 'pdf') {
                    $mediaMeta = clinical_media_meta_from_payload([
                        'media_tag_key' => ($payload['media_tag_key'] ?? ($payloadData['media_tag_key'] ?? null)),
                        'media_tag_label' => ($payload['media_tag_label'] ?? ($payloadData['media_tag_label'] ?? null)),
                        'media_caption' => ($payload['media_caption'] ?? ($payloadData['media_caption'] ?? null)),
                        'media_bundle_id' => ($payload['media_bundle_id'] ?? ($payloadData['media_bundle_id'] ?? null)),
                        'media_bundle_title' => ($payload['media_bundle_title'] ?? ($payloadData['media_bundle_title'] ?? null)),
                        'media_bundle_note' => ($payload['media_bundle_note'] ?? ($payloadData['media_bundle_note'] ?? null)),
                    ]);
                    if ($mediaMeta['media_tag_key'] !== null) {
                        $payloadData['media_tag_key'] = $mediaMeta['media_tag_key'];
                    }
                    if ($mediaMeta['media_tag_label'] !== null) {
                        $payloadData['media_tag_label'] = $mediaMeta['media_tag_label'];
                    }
                    if ($mediaMeta['media_caption'] !== null) {
                        $payloadData['media_caption'] = $mediaMeta['media_caption'];
                    }
                    if ($mediaMeta['media_bundle_id'] !== null) {
                        $payloadData['media_bundle_id'] = $mediaMeta['media_bundle_id'];
                    }
                    if ($mediaMeta['media_bundle_title'] !== null) {
                        $payloadData['media_bundle_title'] = $mediaMeta['media_bundle_title'];
                    }
                    if ($mediaMeta['media_bundle_note'] !== null) {
                        $payloadData['media_bundle_note'] = $mediaMeta['media_bundle_note'];
                    }
                }
                $payloadJson = json_encode($payloadData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if (!is_string($payloadJson)) {
                    $payloadJson = '{}';
                }
                $now = gmdate('Y-m-d H:i:s');
                $cols = clinical_table_columns($pdo, 'clinical_documents');
                $values = [
                    'document_uuid' => $documentUuid,
                    'document_type' => $documentType,
                    'title' => $title,
                    'version' => 1,
                    'status' => 'signed',
                    'patient_id' => $patientId,
                    'appointment_id' => ($appointmentId !== '' ? $appointmentId : null),
                    'encounter_id' => (string)$encounterId,
                    'hospital_stay_id' => null,
                    'care_setting' => 'consulta',
                    'service' => null,
                    'payload_json' => $payloadJson,
                    'rendered_text' => $renderedText,
                    'summary' => $summary,
                    'edited_flag' => 0,
                    'event_datetime' => $eventDatetime,
                    'widget_group' => 'documentos_clinicos',
                    'printable' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'generated_at' => $now,
                    'signed_at' => $now,
                    'created_by_user_id' => 'qa',
                    'updated_by_user_id' => 'qa',
                ];

                $insertCols = [];
                $placeholders = [];
                $params = [];
                foreach ($values as $col => $val) {
                    if (!isset($cols[$col])) {
                        continue;
                    }
                    $insertCols[] = '`' . $col . '`';
                    $ph = ':c_' . $col;
                    $placeholders[] = $ph;
                    $params[$ph] = $val;
                }
                $sql = 'INSERT INTO clinical_documents (' . implode(', ', $insertCols) . ') VALUES (' . implode(', ', $placeholders) . ')';
                $stmt = $pdo->prepare($sql);
                $pdo->beginTransaction();
                foreach ($params as $ph => $val) {
                    if ($val === null) {
                        $stmt->bindValue($ph, null, PDO::PARAM_NULL);
                    } else {
                        $stmt->bindValue($ph, (string)$val, PDO::PARAM_STR);
                    }
                }
                $stmt->execute();
                clinical_encounter_auto_note_upsert($pdo, $encounterRow);
                $pdo->commit();
            } catch (Throwable $e) {
                if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $msg = trim((string)$e->getMessage());
                clinical_send_response([
                    'ok' => false,
                    'error' => 'server_error',
                    'message' => ($msg !== '' ? $msg : 'server error'),
                    'data' => null,
                    'meta' => ['method' => 'POST', 'route' => 'encounters/{encounter_key}/documents'],
                ], 500);
                return;
            }

            clinical_send_response([
                'ok' => true,
                'error' => null,
                'message' => 'document created',
                'data' => [
                    'document_uuid' => $documentUuid,
                    'encounter_key' => (string)($resolved['encounter_key'] ?? $encounterKey),
                    'encounter_id' => $encounterId,
                    'appointment_id' => ($appointmentId !== '' ? $appointmentId : null),
                    'patient_id' => $patientId,
                ],
                'meta' => ['method' => 'POST', 'route' => 'encounters/{encounter_key}/documents'],
            ], 201);
            return;
        }

        if (count($segments) === 2 && $method === 'GET') {
            $encounterKey = urldecode(trim((string)$segments[1]));
            $appointmentId = null;
            $patientId = null;
            $eventDatetime = null;
            $encounterId = null;
            $responseEncounterKey = $encounterKey;
            $rows = [];

            try {
                $pdo = clinical_documents_pdo();
                clinical_encounters_ensure_schema($pdo);
                $resolved = clinical_resolve_encounter_key($pdo, $encounterKey);
                if (($resolved['ok'] ?? false) !== true) {
                    $status = (($resolved['error_code'] ?? '') === 'not_found') ? 404 : 400;
                    clinical_send_response([
                        'ok' => false,
                        'error' => (string)($resolved['error_code'] ?? 'bad_request'),
                        'message' => (string)($resolved['error_message'] ?? 'encounter inválido'),
                        'data' => null,
                        'meta' => ['method' => 'GET', 'route' => 'encounters/{encounter_key}'],
                    ], $status);
                    return;
                }

                $encounterRow = is_array($resolved['row'] ?? null) ? $resolved['row'] : [];
                $encounterId = (int)($encounterRow['encounter_id'] ?? 0);
                $appointmentId = trim((string)($encounterRow['appointment_id'] ?? ''));
                $patientId = trim((string)($encounterRow['patient_id'] ?? ''));
                $eventDatetime = trim((string)($encounterRow['encounter_dt'] ?? ''));
                $responseEncounterKey = (string)($resolved['encounter_key'] ?? $encounterKey);

                $rows = clinical_encounter_documents_direct_fetch($pdo, $patientId, $encounterId, 'DESC');
                if ($rows === [] && $appointmentId !== '') {
                    $latestForAppt = clinical_encounter_get_latest_by_appointment($pdo, $appointmentId);
                    $isLatestForAppt = ((int)($latestForAppt['encounter_id'] ?? 0) === $encounterId);
                    if ($isLatestForAppt) {
                        $rows = clinical_encounter_documents_legacy_by_appointment_fetch($pdo, $patientId, $appointmentId, 'DESC');
                    }
                }
            } catch (Throwable $e) {
                $msg = trim((string)$e->getMessage());
                clinical_send_response([
                    'ok' => false,
                    'error' => 'server_error',
                    'message' => ($msg !== '') ? $msg : 'server error',
                    'data' => null,
                    'meta' => [
                        'method' => 'GET',
                        'route' => 'encounters/{encounter_key}',
                    ],
                ], 500);
                return;
            }

            $documents = [];
            $bucketSource = clinical_encounter_auto_note_filter_documents($rows);
            $buckets = clinical_encounter_document_buckets($bucketSource);
            foreach ($rows as $row) {
                $documents[] = [
                    'document_uuid' => (string)($row['document_uuid'] ?? ''),
                    'document_type' => (string)($row['document_type'] ?? ''),
                    'title' => (string)($row['title'] ?? ''),
                    'event_datetime' => (string)($row['event_datetime'] ?? ''),
                    'summary' => (string)($row['summary'] ?? ''),
                    'hospital_stay_id' => $row['hospital_stay_id'] ?? null,
                ];
            }

            $mapList = static function (array $bucketRows): array {
                $mapped = [];
                foreach ($bucketRows as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $mapped[] = [
                        'document_uuid' => (string)($row['document_uuid'] ?? ''),
                        'document_type' => (string)($row['document_type'] ?? ''),
                        'title' => (string)($row['title'] ?? ''),
                        'event_datetime' => (string)($row['event_datetime'] ?? ''),
                        'summary' => (string)($row['summary'] ?? ''),
                    ];
                }
                return $mapped;
            };

            clinical_send_response([
                'ok' => true,
                'error' => null,
                'message' => 'encounter retrieved',
                'data' => [
                    'encounter_key' => $responseEncounterKey,
                    'encounter_id' => $encounterId,
                    'appointment_id' => ($appointmentId !== '' ? $appointmentId : null),
                    'patient_id' => $patientId,
                    'event_datetime' => $eventDatetime,
                    'status' => (string)($encounterRow['status'] ?? 'open'),
                    'closed_at' => ($encounterRow['closed_at'] ?? null),
                    'closed_by_user_id' => ($encounterRow['closed_by_user_id'] ?? null),
                    'auto_note_uuid_final' => ($encounterRow['auto_note_uuid_final'] ?? null),
                    'documents' => $documents,
                    'vitals' => $mapList($buckets['vitals']),
                    'notes' => $mapList($buckets['notes']),
                    'prescriptions' => $mapList($buckets['prescriptions']),
                    'orders' => $mapList($buckets['orders']),
                    'results' => $mapList($buckets['results']),
                    'procedures' => $mapList($buckets['procedures']),
                ],
                'meta' => [
                    'method' => 'GET',
                    'route' => 'encounters/{encounter_key}',
                ],
            ], 200);
            return;
        }

        clinical_send_response([
            'ok' => false,
            'error' => 'not_found',
            'message' => 'route not found',
            'data' => null,
            'meta' => [
                'method' => $method,
                'route' => $route,
            ],
        ], 404);
        return;
    }

    if (($segments[0] ?? '') === 'note-capture-tokens') {
        try {
            $pdo = clinical_documents_pdo();
            clinical_note_capture_tokens_ensure_schema($pdo);
        } catch (Throwable $e) {
            clinical_send_response([
                'ok' => false,
                'error' => 'server_error',
                'message' => trim((string)$e->getMessage()) ?: 'server error',
                'data' => null,
                'meta' => [
                    'method' => $method,
                    'route' => $route,
                ],
            ], 500);
            return;
        }

        if ($method === 'POST' && count($segments) === 1) {
            $bodyResult = clinical_read_json_body();
            if (($bodyResult['ok'] ?? false) !== true) {
                clinical_send_response([
                    'ok' => false,
                    'error' => 'bad_request',
                    'message' => (string)($bodyResult['error'] ?? 'invalid body'),
                    'data' => null,
                    'meta' => [
                        'method' => 'POST',
                        'route' => 'note-capture-tokens',
                    ],
                ], 400);
                return;
            }
            $body = is_array($bodyResult['data'] ?? null) ? (array)$bodyResult['data'] : [];
            $patientId = trim((string)($body['patient_id'] ?? ''));
            $encounterKey = trim((string)($body['encounter_key'] ?? ''));
            $noteContext = trim((string)($body['note_context'] ?? 'nota_clinica_modal'));
            if ($patientId === '') {
                clinical_send_response([
                    'ok' => false,
                    'error' => 'bad_request',
                    'message' => 'patient_id requerido',
                    'data' => null,
                    'meta' => [
                        'method' => 'POST',
                        'route' => 'note-capture-tokens',
                    ],
                ], 400);
                return;
            }
            if ($noteContext === '') {
                $noteContext = 'nota_clinica_modal';
            }
            $expiresInSec = (int)($body['expires_in_sec'] ?? 900);
            if ($expiresInSec <= 0) {
                $expiresInSec = 900;
            }
            $expiresInSec = max(60, min(3600, $expiresInSec));

            $token = clinical_note_capture_token_generate();
            $now = gmdate('Y-m-d H:i:s');
            $expiresAt = gmdate('Y-m-d H:i:s', time() + $expiresInSec);
            $noteContextNorm = strtolower($noteContext);
            $isConsentRemoteSignatureContext = strpos($noteContextNorm, 'consentimiento_firma_remota') === 0;
            $mobilePath = '/public/note-capture.html?token=' . rawurlencode($token);
            if ($isConsentRemoteSignatureContext) {
                $mobilePath .= '&mode=signature';
            }
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO clinical_note_capture_tokens (
                        token,
                        patient_id,
                        encounter_key,
                        note_context,
                        status,
                        expires_at,
                        uploaded_at,
                        cancelled_at,
                        document_id,
                        document_uuid,
                        preview_url,
                        created_at,
                        updated_at
                    ) VALUES (
                        :token,
                        :patient_id,
                        :encounter_key,
                        :note_context,
                        'pending',
                        :expires_at,
                        NULL,
                        NULL,
                        NULL,
                        NULL,
                        NULL,
                        :created_at,
                        :updated_at
                    )
                ");
                $stmt->bindValue(':token', $token, PDO::PARAM_STR);
                $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_STR);
                if ($encounterKey === '') {
                    $stmt->bindValue(':encounter_key', null, PDO::PARAM_NULL);
                } else {
                    $stmt->bindValue(':encounter_key', $encounterKey, PDO::PARAM_STR);
                }
                $stmt->bindValue(':note_context', $noteContext, PDO::PARAM_STR);
                $stmt->bindValue(':expires_at', $expiresAt, PDO::PARAM_STR);
                $stmt->bindValue(':created_at', $now, PDO::PARAM_STR);
                $stmt->bindValue(':updated_at', $now, PDO::PARAM_STR);
                $stmt->execute();
            } catch (Throwable $e) {
                clinical_send_response([
                    'ok' => false,
                    'error' => 'server_error',
                    'message' => trim((string)$e->getMessage()) ?: 'server error',
                    'data' => null,
                    'meta' => [
                        'method' => 'POST',
                        'route' => 'note-capture-tokens',
                    ],
                ], 500);
                return;
            }

            clinical_send_response([
                'ok' => true,
                'error' => null,
                'message' => 'note capture token created',
                'data' => [
                    'token' => $token,
                    'status' => 'pending',
                    'expires_at' => clinical_note_capture_datetime_to_iso($expiresAt),
                    'mobile_url' => $mobilePath,
                    'qr_value' => $mobilePath,
                ],
                'meta' => [
                    'method' => 'POST',
                    'route' => 'note-capture-tokens',
                ],
            ], 201);
            return;
        }

        if ($method === 'GET' && count($segments) === 2) {
            $token = trim(rawurldecode((string)$segments[1]));
            if ($token === '') {
                clinical_send_response([
                    'ok' => false,
                    'error' => 'bad_request',
                    'message' => 'token requerido',
                    'data' => null,
                    'meta' => [
                        'method' => 'GET',
                        'route' => 'note-capture-tokens/{token}',
                    ],
                ], 400);
                return;
            }
            $row = clinical_note_capture_token_fetch($pdo, $token);
            if (!is_array($row)) {
                clinical_send_response([
                    'ok' => false,
                    'error' => 'not_found',
                    'message' => 'token no encontrado',
                    'data' => null,
                    'meta' => [
                        'method' => 'GET',
                        'route' => 'note-capture-tokens/{token}',
                    ],
                ], 404);
                return;
            }
            $row = clinical_note_capture_mark_expired_if_needed($pdo, $row);
            clinical_send_response([
                'ok' => true,
                'error' => null,
                'message' => 'note capture token status',
                'data' => clinical_note_capture_status_data($row),
                'meta' => [
                    'method' => 'GET',
                    'route' => 'note-capture-tokens/{token}',
                ],
            ], 200);
            return;
        }

        if ($method === 'POST' && count($segments) === 3 && ($segments[2] ?? '') === 'cancel') {
            $token = trim(rawurldecode((string)$segments[1]));
            if ($token === '') {
                clinical_send_response([
                    'ok' => false,
                    'error' => 'bad_request',
                    'message' => 'token requerido',
                    'data' => null,
                    'meta' => [
                        'method' => 'POST',
                        'route' => 'note-capture-tokens/{token}/cancel',
                    ],
                ], 400);
                return;
            }
            $row = clinical_note_capture_token_fetch($pdo, $token);
            if (!is_array($row)) {
                clinical_send_response([
                    'ok' => false,
                    'error' => 'not_found',
                    'message' => 'token no encontrado',
                    'data' => null,
                    'meta' => [
                        'method' => 'POST',
                        'route' => 'note-capture-tokens/{token}/cancel',
                    ],
                ], 404);
                return;
            }
            $row = clinical_note_capture_mark_expired_if_needed($pdo, $row);
            $status = strtolower(trim((string)($row['status'] ?? 'pending')));
            if ($status === 'pending') {
                $cancelledAt = gmdate('Y-m-d H:i:s');
                $stmt = $pdo->prepare("
                    UPDATE clinical_note_capture_tokens
                    SET
                        status = 'cancelled',
                        cancelled_at = :cancelled_at,
                        updated_at = :updated_at
                    WHERE token = :token
                ");
                $stmt->bindValue(':cancelled_at', $cancelledAt, PDO::PARAM_STR);
                $stmt->bindValue(':updated_at', $cancelledAt, PDO::PARAM_STR);
                $stmt->bindValue(':token', $token, PDO::PARAM_STR);
                $stmt->execute();
                $row = clinical_note_capture_token_fetch($pdo, $token) ?? $row;
            }
            clinical_send_response([
                'ok' => true,
                'error' => null,
                'message' => 'note capture token cancelled',
                'data' => clinical_note_capture_status_data($row),
                'meta' => [
                    'method' => 'POST',
                    'route' => 'note-capture-tokens/{token}/cancel',
                ],
            ], 200);
            return;
        }

        if ($method === 'POST' && count($segments) === 3 && ($segments[2] ?? '') === 'consume') {
            $token = trim(rawurldecode((string)$segments[1]));
            if ($token === '') {
                clinical_send_response([
                    'ok' => false,
                    'error' => 'bad_request',
                    'message' => 'token requerido',
                    'data' => null,
                    'meta' => [
                        'method' => 'POST',
                        'route' => 'note-capture-tokens/{token}/consume',
                    ],
                ], 400);
                return;
            }
            $bodyResult = clinical_read_json_body();
            if (($bodyResult['ok'] ?? false) !== true) {
                clinical_send_response([
                    'ok' => false,
                    'error' => 'bad_request',
                    'message' => (string)($bodyResult['error'] ?? 'invalid body'),
                    'data' => null,
                    'meta' => [
                        'method' => 'POST',
                        'route' => 'note-capture-tokens/{token}/consume',
                    ],
                ], 400);
                return;
            }
            $body = is_array($bodyResult['data'] ?? null) ? (array)$bodyResult['data'] : [];
            $noteDocumentIdRaw = trim((string)($body['note_document_id'] ?? ''));
            $noteDocumentUuid = trim((string)($body['note_document_uuid'] ?? ''));
            $noteDocumentId = (preg_match('/^\d+$/', $noteDocumentIdRaw) === 1) ? (int)$noteDocumentIdRaw : 0;
            if ($noteDocumentId <= 0 && $noteDocumentUuid === '') {
                clinical_send_response([
                    'ok' => false,
                    'error' => 'bad_request',
                    'message' => 'note_document_id o note_document_uuid requerido',
                    'data' => null,
                    'meta' => [
                        'method' => 'POST',
                        'route' => 'note-capture-tokens/{token}/consume',
                    ],
                ], 400);
                return;
            }
            $row = clinical_note_capture_token_fetch($pdo, $token);
            if (!is_array($row)) {
                clinical_send_response([
                    'ok' => false,
                    'error' => 'not_found',
                    'message' => 'token no encontrado',
                    'data' => null,
                    'meta' => [
                        'method' => 'POST',
                        'route' => 'note-capture-tokens/{token}/consume',
                    ],
                ], 404);
                return;
            }
            $row = clinical_note_capture_mark_expired_if_needed($pdo, $row);
            $status = strtolower(trim((string)($row['status'] ?? 'pending')));
            if ($status === 'consumed') {
                clinical_send_response([
                    'ok' => true,
                    'error' => null,
                    'message' => 'note capture token already consumed',
                    'data' => clinical_note_capture_status_data($row),
                    'meta' => [
                        'method' => 'POST',
                        'route' => 'note-capture-tokens/{token}/consume',
                    ],
                ], 200);
                return;
            }
            if ($status !== 'uploaded') {
                clinical_send_response([
                    'ok' => false,
                    'error' => 'conflict',
                    'message' => 'token no disponible para consumo (' . $status . ')',
                    'data' => null,
                    'meta' => [
                        'method' => 'POST',
                        'route' => 'note-capture-tokens/{token}/consume',
                    ],
                ], 409);
                return;
            }

            $consumedAt = gmdate('Y-m-d H:i:s');
            $stmt = $pdo->prepare("
                UPDATE clinical_note_capture_tokens
                SET
                    status = 'consumed',
                    consumed_at = :consumed_at,
                    note_document_id = :note_document_id,
                    note_document_uuid = :note_document_uuid,
                    updated_at = :updated_at
                WHERE token = :token
            ");
            $stmt->bindValue(':consumed_at', $consumedAt, PDO::PARAM_STR);
            if ($noteDocumentId > 0) {
                $stmt->bindValue(':note_document_id', $noteDocumentId, PDO::PARAM_INT);
            } else {
                $stmt->bindValue(':note_document_id', null, PDO::PARAM_NULL);
            }
            if ($noteDocumentUuid !== '') {
                $stmt->bindValue(':note_document_uuid', $noteDocumentUuid, PDO::PARAM_STR);
            } else {
                $stmt->bindValue(':note_document_uuid', null, PDO::PARAM_NULL);
            }
            $stmt->bindValue(':updated_at', $consumedAt, PDO::PARAM_STR);
            $stmt->bindValue(':token', $token, PDO::PARAM_STR);
            $stmt->execute();

            $latest = clinical_note_capture_token_fetch($pdo, $token) ?? $row;
            clinical_send_response([
                'ok' => true,
                'error' => null,
                'message' => 'note capture token consumed',
                'data' => clinical_note_capture_status_data($latest),
                'meta' => [
                    'method' => 'POST',
                    'route' => 'note-capture-tokens/{token}/consume',
                ],
            ], 200);
            return;
        }

        if ($method === 'POST' && count($segments) === 3 && ($segments[2] ?? '') === 'signature') {
            $token = trim(rawurldecode((string)$segments[1]));
            if ($token === '') {
                clinical_send_response([
                    'ok' => false,
                    'error' => 'bad_request',
                    'message' => 'token requerido',
                    'data' => null,
                    'meta' => [
                        'method' => 'POST',
                        'route' => 'note-capture-tokens/{token}/signature',
                    ],
                ], 400);
                return;
            }
            $row = clinical_note_capture_token_fetch($pdo, $token);
            if (!is_array($row)) {
                clinical_send_response([
                    'ok' => false,
                    'error' => 'not_found',
                    'message' => 'token no encontrado',
                    'data' => null,
                    'meta' => [
                        'method' => 'POST',
                        'route' => 'note-capture-tokens/{token}/signature',
                    ],
                ], 404);
                return;
            }
            $row = clinical_note_capture_mark_expired_if_needed($pdo, $row);
            $status = strtolower(trim((string)($row['status'] ?? 'pending')));
            if ($status !== 'pending') {
                $statusCode = ($status === 'expired') ? 410 : 409;
                clinical_send_response([
                    'ok' => false,
                    'error' => 'conflict',
                    'message' => 'token no disponible para firma (' . $status . ')',
                    'data' => null,
                    'meta' => [
                        'method' => 'POST',
                        'route' => 'note-capture-tokens/{token}/signature',
                    ],
                ], $statusCode);
                return;
            }
            $noteContext = strtolower(trim((string)($row['note_context'] ?? '')));
            if (strpos($noteContext, 'consentimiento_firma_remota') !== 0) {
                clinical_send_response([
                    'ok' => false,
                    'error' => 'conflict',
                    'message' => 'token no corresponde a firma remota',
                    'data' => null,
                    'meta' => [
                        'method' => 'POST',
                        'route' => 'note-capture-tokens/{token}/signature',
                    ],
                ], 409);
                return;
            }
            $bodyResult = clinical_read_json_body();
            if (($bodyResult['ok'] ?? false) !== true) {
                clinical_send_response([
                    'ok' => false,
                    'error' => 'bad_request',
                    'message' => (string)($bodyResult['error'] ?? 'invalid body'),
                    'data' => null,
                    'meta' => [
                        'method' => 'POST',
                        'route' => 'note-capture-tokens/{token}/signature',
                    ],
                ], 400);
                return;
            }
            $body = is_array($bodyResult['data'] ?? null) ? (array)$bodyResult['data'] : [];
            $signatureData = trim((string)($body['signature_data'] ?? ''));
            if ($signatureData === '' || preg_match('/^data:image\//i', $signatureData) !== 1) {
                clinical_send_response([
                    'ok' => false,
                    'error' => 'bad_request',
                    'message' => 'signature_data requerido en formato data:image',
                    'data' => null,
                    'meta' => [
                        'method' => 'POST',
                        'route' => 'note-capture-tokens/{token}/signature',
                    ],
                ], 400);
                return;
            }
            if (strlen($signatureData) > 2_000_000) {
                clinical_send_response([
                    'ok' => false,
                    'error' => 'bad_request',
                    'message' => 'signature_data demasiado grande',
                    'data' => null,
                    'meta' => [
                        'method' => 'POST',
                        'route' => 'note-capture-tokens/{token}/signature',
                    ],
                ], 400);
                return;
            }
            $signerName = trim((string)($body['signer_name'] ?? ''));
            $signedAtRaw = trim((string)($body['signed_at'] ?? ''));
            if ($signedAtRaw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $signedAtRaw) === 1) {
                $signedAtRaw = str_replace('T', ' ', $signedAtRaw) . ':00';
            }
            if ($signedAtRaw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}$/', $signedAtRaw) === 1) {
                $signedAtRaw .= ':00';
            }
            if ($signedAtRaw === '' || preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}$/', $signedAtRaw) !== 1) {
                $signedAtRaw = gmdate('Y-m-d H:i:s');
            }
            $uploadedAt = gmdate('Y-m-d H:i:s');
            try {
                $stmt = $pdo->prepare("
                    UPDATE clinical_note_capture_tokens
                    SET
                        status = 'uploaded',
                        uploaded_at = :uploaded_at,
                        signature_image_data = :signature_image_data,
                        signature_signer_name = :signature_signer_name,
                        signature_signed_at = :signature_signed_at,
                        preview_url = NULL,
                        updated_at = :updated_at
                    WHERE token = :token
                ");
                $stmt->bindValue(':uploaded_at', $uploadedAt, PDO::PARAM_STR);
                $stmt->bindValue(':signature_image_data', $signatureData, PDO::PARAM_STR);
                if ($signerName !== '') {
                    $stmt->bindValue(':signature_signer_name', $signerName, PDO::PARAM_STR);
                } else {
                    $stmt->bindValue(':signature_signer_name', null, PDO::PARAM_NULL);
                }
                $stmt->bindValue(':signature_signed_at', $signedAtRaw, PDO::PARAM_STR);
                $stmt->bindValue(':updated_at', $uploadedAt, PDO::PARAM_STR);
                $stmt->bindValue(':token', $token, PDO::PARAM_STR);
                $stmt->execute();
            } catch (Throwable $e) {
                clinical_send_response([
                    'ok' => false,
                    'error' => 'server_error',
                    'message' => trim((string)$e->getMessage()) ?: 'no se pudo guardar la firma remota',
                    'data' => null,
                    'meta' => [
                        'method' => 'POST',
                        'route' => 'note-capture-tokens/{token}/signature',
                    ],
                ], 500);
                return;
            }

            $latest = clinical_note_capture_token_fetch($pdo, $token);
            if (!is_array($latest)) {
                $latest = [
                    'token' => $token,
                    'status' => 'uploaded',
                    'expires_at' => ($row['expires_at'] ?? ''),
                    'uploaded_at' => $uploadedAt,
                    'preview_url' => '',
                    'signature_image_data' => $signatureData,
                    'signature_signer_name' => $signerName,
                    'signature_signed_at' => $signedAtRaw,
                ];
            }
            clinical_send_response([
                'ok' => true,
                'error' => null,
                'message' => 'note capture signature uploaded',
                'data' => clinical_note_capture_status_data($latest),
                'meta' => [
                    'method' => 'POST',
                    'route' => 'note-capture-tokens/{token}/signature',
                ],
            ], 200);
            return;
        }

        if ($method === 'POST' && count($segments) === 3 && ($segments[2] ?? '') === 'upload') {
            $token = trim(rawurldecode((string)$segments[1]));
            if ($token === '') {
                clinical_send_response([
                    'ok' => false,
                    'error' => 'bad_request',
                    'message' => 'token requerido',
                    'data' => null,
                    'meta' => [
                        'method' => 'POST',
                        'route' => 'note-capture-tokens/{token}/upload',
                    ],
                ], 400);
                return;
            }
            $row = clinical_note_capture_token_fetch($pdo, $token);
            if (!is_array($row)) {
                clinical_send_response([
                    'ok' => false,
                    'error' => 'not_found',
                    'message' => 'token no encontrado',
                    'data' => null,
                    'meta' => [
                        'method' => 'POST',
                        'route' => 'note-capture-tokens/{token}/upload',
                    ],
                ], 404);
                return;
            }
            $row = clinical_note_capture_mark_expired_if_needed($pdo, $row);
            $status = strtolower(trim((string)($row['status'] ?? 'pending')));
            if ($status !== 'pending') {
                $statusCode = ($status === 'expired') ? 410 : 409;
                clinical_send_response([
                    'ok' => false,
                    'error' => 'conflict',
                    'message' => 'token no disponible para carga (' . $status . ')',
                    'data' => null,
                    'meta' => [
                        'method' => 'POST',
                        'route' => 'note-capture-tokens/{token}/upload',
                    ],
                ], $statusCode);
                return;
            }

            $uploadFile = $_FILES['file'] ?? null;
            $uploadError = is_array($uploadFile) ? (int)($uploadFile['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
            if (!is_array($uploadFile) || $uploadError !== UPLOAD_ERR_OK) {
                $uploadMessage = 'archivo requerido';
                if ($uploadError === UPLOAD_ERR_INI_SIZE || $uploadError === UPLOAD_ERR_FORM_SIZE) {
                    $uploadMessage = 'archivo demasiado grande para el límite de carga';
                } elseif ($uploadError === UPLOAD_ERR_PARTIAL) {
                    $uploadMessage = 'la carga del archivo fue parcial, intenta nuevamente';
                } elseif ($uploadError === UPLOAD_ERR_NO_TMP_DIR || $uploadError === UPLOAD_ERR_CANT_WRITE) {
                    $uploadMessage = 'no se pudo procesar la carga en el servidor';
                } elseif ($uploadError === UPLOAD_ERR_EXTENSION) {
                    $uploadMessage = 'la carga fue bloqueada por extensión del servidor';
                }
                clinical_send_response([
                    'ok' => false,
                    'error' => 'bad_request',
                    'message' => $uploadMessage,
                    'data' => null,
                    'meta' => [
                        'method' => 'POST',
                        'route' => 'note-capture-tokens/{token}/upload',
                        'upload_error' => $uploadError,
                    ],
                ], 400);
                return;
            }

            $summary = trim((string)($_POST['summary'] ?? ''));
            $eventDatetime = trim((string)($_POST['event_datetime'] ?? ''));
            if ($eventDatetime !== '' && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $eventDatetime) === 1) {
                $eventDatetime = str_replace('T', ' ', $eventDatetime) . ':00';
            }
            if ($eventDatetime !== '' && preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}$/', $eventDatetime) === 1) {
                $eventDatetime .= ':00';
            }
            if ($eventDatetime === '' || preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}$/', $eventDatetime) !== 1) {
                $eventDatetime = gmdate('Y-m-d H:i:s');
            }

            $patientId = trim((string)($row['patient_id'] ?? ''));
            $encounterKey = trim((string)($row['encounter_key'] ?? ''));
            $noteContext = trim((string)($row['note_context'] ?? 'nota_clinica_modal'));
            $noteContextNorm = strtolower($noteContext);
            $isConsentIdentityContext = strpos($noteContextNorm, 'consentimiento_identidad_firmante') === 0;
            $identityKind = 'otro';
            if ($isConsentIdentityContext) {
                $parts = explode(':', $noteContextNorm, 2);
                $identityKindCandidate = trim((string)($parts[1] ?? ''));
                if (in_array($identityKindCandidate, ['ine', 'pasaporte', 'otro'], true)) {
                    $identityKind = $identityKindCandidate;
                }
            }
            $uploadMime = strtolower(trim((string)($uploadFile['type'] ?? '')));
            $uploadName = strtolower(trim((string)($uploadFile['name'] ?? '')));
            $resolvedDocumentType = (strpos($uploadMime, 'pdf') !== false || preg_match('/\.pdf$/i', $uploadName) === 1) ? 'pdf' : 'image';
            $identityKindLabelMap = [
                'ine' => 'Credencial de elector / INE',
                'pasaporte' => 'Pasaporte',
                'otro' => 'Documento de identidad',
            ];
            $identityKindLabel = $identityKindLabelMap[$identityKind] ?? 'Documento de identidad';
            $defaultTitle = $isConsentIdentityContext
                ? ('Anexo identidad firmante — ' . $identityKindLabel)
                : 'Imagen clínica (captura móvil)';
            $defaultSummary = $isConsentIdentityContext
                ? 'Anexo de identidad del firmante'
                : 'Imagen clínica adjunta desde celular';
            $payloadSource = $isConsentIdentityContext ? 'consentimiento_identidad_qr_v1' : 'nota_modal_qr_v1';
            $payload = [
                'patient_id' => $patientId,
                'document_type' => $resolvedDocumentType,
                'title' => $defaultTitle,
                'summary' => ($summary !== '') ? $summary : $defaultSummary,
                'event_datetime' => $eventDatetime,
                'payload' => [
                    'source' => $payloadSource,
                    'note_capture_token' => $token,
                    'note_context' => ($noteContext !== '' ? $noteContext : 'nota_clinica_modal'),
                ],
            ];
            if ($isConsentIdentityContext) {
                $payload['payload']['identity_doc_kind'] = $identityKind;
                $payload['payload']['identity_doc_label'] = $identityKindLabel;
                if ($resolvedDocumentType === 'image') {
                    $payload['media_tag_key'] = 'identidad_firmante';
                    $payload['media_tag_label'] = 'Identidad del firmante';
                }
            } else {
                $payload['media_tag_key'] = 'evidencia_clinica';
                $payload['media_tag_label'] = 'Evidencia clínica';
            }
            if ($encounterKey !== '') {
                $payload['encounter_key'] = $encounterKey;
            }

            try {
                $document = clinical_documents_gateway_save_upload($pdo, $payload, $uploadFile);
            } catch (InvalidArgumentException $e) {
                clinical_send_response([
                    'ok' => false,
                    'error' => 'invalid_params',
                    'message' => trim((string)$e->getMessage()) ?: 'invalid params',
                    'data' => null,
                    'meta' => [
                        'method' => 'POST',
                        'route' => 'note-capture-tokens/{token}/upload',
                    ],
                ], 400);
                return;
            } catch (RuntimeException $e) {
                $message = trim((string)$e->getMessage());
                if ($message === 'MEDIA_TAG_REQUIRED') {
                    $message = 'Selecciona una etiqueta para esta imagen.';
                }
                clinical_send_response([
                    'ok' => false,
                    'error' => 'server_error',
                    'message' => $message !== '' ? $message : 'server error',
                    'data' => null,
                    'meta' => [
                        'method' => 'POST',
                        'route' => 'note-capture-tokens/{token}/upload',
                    ],
                ], 500);
                return;
            } catch (Throwable $e) {
                clinical_send_response([
                    'ok' => false,
                    'error' => 'server_error',
                    'message' => trim((string)$e->getMessage()) ?: 'server error',
                    'data' => null,
                    'meta' => [
                        'method' => 'POST',
                        'route' => 'note-capture-tokens/{token}/upload',
                    ],
                ], 500);
                return;
            }

            $uploadedAt = gmdate('Y-m-d H:i:s');
            $documentId = (int)($document['document_db_id'] ?? 0);
            $documentUuid = trim((string)($document['document_id'] ?? ($document['document_uuid'] ?? '')));
            $previewUrl = clinical_note_capture_extract_preview_url($document);
            $update = $pdo->prepare("
                UPDATE clinical_note_capture_tokens
                SET
                    status = 'uploaded',
                    uploaded_at = :uploaded_at,
                    document_id = :document_id,
                    document_uuid = :document_uuid,
                    preview_url = :preview_url,
                    updated_at = :updated_at
                WHERE token = :token
            ");
            $update->bindValue(':uploaded_at', $uploadedAt, PDO::PARAM_STR);
            if ($documentId > 0) {
                $update->bindValue(':document_id', $documentId, PDO::PARAM_INT);
            } else {
                $update->bindValue(':document_id', null, PDO::PARAM_NULL);
            }
            if ($documentUuid !== '') {
                $update->bindValue(':document_uuid', $documentUuid, PDO::PARAM_STR);
            } else {
                $update->bindValue(':document_uuid', null, PDO::PARAM_NULL);
            }
            if ($previewUrl !== '') {
                $update->bindValue(':preview_url', $previewUrl, PDO::PARAM_STR);
            } else {
                $update->bindValue(':preview_url', null, PDO::PARAM_NULL);
            }
            $update->bindValue(':updated_at', $uploadedAt, PDO::PARAM_STR);
            $update->bindValue(':token', $token, PDO::PARAM_STR);
            $update->execute();

            $latest = clinical_note_capture_token_fetch($pdo, $token);
            if (!is_array($latest)) {
                $latest = [
                    'token' => $token,
                    'status' => 'uploaded',
                    'expires_at' => ($row['expires_at'] ?? ''),
                    'uploaded_at' => $uploadedAt,
                    'document_id' => $documentId,
                    'document_uuid' => $documentUuid,
                    'preview_url' => $previewUrl,
                ];
            }
            clinical_send_response([
                'ok' => true,
                'error' => null,
                'message' => 'note capture uploaded',
                'data' => clinical_note_capture_status_data($latest),
                'meta' => [
                    'method' => 'POST',
                    'route' => 'note-capture-tokens/{token}/upload',
                ],
            ], 201);
            return;
        }

        clinical_send_response([
            'ok' => false,
            'error' => 'not_found',
            'message' => 'route not found',
            'data' => null,
            'meta' => [
                'method' => $method,
                'route' => $route,
            ],
        ], 404);
        return;
    }

    if (($segments[0] ?? '') === 'doctors') {
        if ($method === 'POST' && count($segments) === 5 && ($segments[2] ?? '') === 'patients' && ($segments[4] ?? '') === 'documents') {
            $doctorId = trim(rawurldecode((string)$segments[1]));
            $patientId = trim(rawurldecode((string)$segments[3]));
            $meta = [
                'method' => 'POST',
                'route' => 'doctors/{doctor_id}/patients/{patient_id}/documents',
                'source' => 'clinical_documents_pdo',
                'scope' => 'doctor_patient',
            ];
            if ($doctorId === '' || $patientId === '') {
                clinical_send_response([
                    'ok' => false,
                    'error' => 'bad_request',
                    'message' => 'doctor_id and patient_id required',
                    'data' => null,
                    'meta' => $meta,
                ], 400);
                return;
            }

            try {
                $pdo = clinical_documents_pdo();
                if (!clinical_patient_exists($pdo, $patientId)) {
                    clinical_send_response([
                        'ok' => false,
                        'error' => 'not_found',
                        'message' => 'patient not found',
                        'data' => null,
                        'meta' => $meta,
                    ], 404);
                    return;
                }
                if (!clinical_has_active_doctor_patient_link($pdo, $doctorId, $patientId)) {
                    clinical_send_response([
                        'ok' => false,
                        'error' => 'forbidden',
                        'message' => 'doctor patient link required',
                        'data' => null,
                        'meta' => $meta,
                    ], 403);
                    return;
                }

                $request = clinical_documents_read_create_request();
                $payload = is_array($request['payload'] ?? null) ? $request['payload'] : [];
                if (clinical_documents_request_has_patient_mismatch($payload, $patientId)) {
                    clinical_send_response([
                        'ok' => false,
                        'error' => 'invalid_params',
                        'message' => 'patient_id mismatch',
                        'data' => null,
                        'meta' => $meta,
                    ], 400);
                    return;
                }

                $payload = clinical_documents_force_request_patient_id($payload, $patientId);
                $uploadFile = is_array($request['upload_file'] ?? null) ? $request['upload_file'] : null;
                $isMultipart = ($request['is_multipart'] ?? false) === true;
                $document = clinical_documents_save_create_request($pdo, $payload, $uploadFile, $isMultipart);
            } catch (InvalidArgumentException $e) {
                $msg = trim((string)$e->getMessage());
                clinical_send_response([
                    'ok' => false,
                    'error' => 'invalid_params',
                    'message' => ($msg !== '') ? $msg : 'invalid params',
                    'data' => null,
                    'meta' => $meta,
                ], 400);
                return;
            } catch (RuntimeException $e) {
                $msg = trim((string)$e->getMessage());
                if ($msg === 'MEDIA_TAG_REQUIRED') {
                    clinical_send_response([
                        'ok' => false,
                        'error' => [
                            'code' => 'MEDIA_TAG_REQUIRED',
                            'message' => 'Selecciona una etiqueta para esta imagen.',
                        ],
                        'message' => 'Selecciona una etiqueta para esta imagen.',
                        'data' => null,
                        'meta' => $meta,
                    ], 400);
                    return;
                }
                clinical_send_response([
                    'ok' => false,
                    'error' => 'server_error',
                    'message' => ($msg !== '') ? $msg : 'server error',
                    'data' => null,
                    'meta' => $meta,
                ], 500);
                return;
            } catch (Throwable $e) {
                $msg = trim((string)$e->getMessage());
                clinical_send_response([
                    'ok' => false,
                    'error' => 'server_error',
                    'message' => ($msg !== '') ? $msg : 'server error',
                    'data' => null,
                    'meta' => $meta,
                ], 500);
                return;
            }

            clinical_send_response([
                'ok' => true,
                'error' => null,
                'message' => 'document saved',
                'data' => [
                    'document_id' => $document['document_id'] ?? ($document['document_uuid'] ?? null),
                    'document' => $document,
                ],
                'meta' => $meta,
            ], 201);
            return;
        }

        if ($method === 'GET' && count($segments) === 5 && ($segments[2] ?? '') === 'patients' && ($segments[4] ?? '') === 'documents') {
            $doctorId = trim(rawurldecode((string)$segments[1]));
            $patientId = trim(rawurldecode((string)$segments[3]));
            $meta = [
                'method' => 'GET',
                'route' => 'doctors/{doctor_id}/patients/{patient_id}/documents',
                'source' => 'clinical_documents_pdo',
                'scope' => 'doctor_patient',
            ];
            if ($doctorId === '' || $patientId === '') {
                clinical_send_response([
                    'ok' => false,
                    'error' => 'bad_request',
                    'message' => 'doctor_id and patient_id required',
                    'data' => null,
                    'meta' => $meta,
                ], 400);
                return;
            }

            $limit = (int)($_GET['limit'] ?? 30);
            if ($limit <= 0) {
                $limit = 30;
            } elseif ($limit > 200) {
                $limit = 200;
            }
            $documentType = trim((string)($_GET['document_type'] ?? ''));
            $hospitalStayId = trim((string)($_GET['hospital_stay_id'] ?? ''));

            try {
                $pdo = clinical_documents_pdo();
                if (!clinical_patient_exists($pdo, $patientId)) {
                    clinical_send_response([
                        'ok' => false,
                        'error' => 'not_found',
                        'message' => 'patient not found',
                        'data' => null,
                        'meta' => $meta,
                    ], 404);
                    return;
                }
                if (!clinical_has_active_doctor_patient_link($pdo, $doctorId, $patientId)) {
                    clinical_send_response([
                        'ok' => false,
                        'error' => 'forbidden',
                        'message' => 'doctor patient link required',
                        'data' => null,
                        'meta' => $meta,
                    ], 403);
                    return;
                }
                $items = clinical_documents_list_fetch($pdo, $patientId, $documentType, $hospitalStayId, $limit);
            } catch (Throwable $e) {
                $msg = trim($e->getMessage());
                clinical_send_response([
                    'ok' => false,
                    'error' => 'server_error',
                    'message' => ($msg !== '') ? $msg : 'server error',
                    'data' => null,
                    'meta' => $meta,
                ], 500);
                return;
            }

            clinical_send_response([
                'ok' => true,
                'error' => null,
                'message' => 'documents listed',
                'data' => [
                    'items' => $items,
                ],
                'meta' => $meta,
            ], 200);
            return;
        }

        if ($method === 'GET' && count($segments) === 4 && ($segments[2] ?? '') === 'documents') {
            $doctorId = trim(rawurldecode((string)$segments[1]));
            $documentToken = trim(rawurldecode((string)$segments[3]));
            $meta = [
                'method' => 'GET',
                'route' => 'doctors/{doctor_id}/documents/{id_or_uuid}',
                'source' => 'clinical_documents_pdo',
                'scope' => 'doctor_patient',
            ];
            if ($doctorId === '' || $documentToken === '') {
                clinical_send_response([
                    'ok' => false,
                    'error' => 'bad_request',
                    'message' => 'doctor_id and document id_or_uuid required',
                    'data' => null,
                    'meta' => $meta,
                ], 400);
                return;
            }

            try {
                $pdo = clinical_documents_pdo();
                $document = clinical_documents_get_by_token_fetch($pdo, $documentToken);
                if ($document === null) {
                    clinical_send_response([
                        'ok' => false,
                        'error' => 'not_found',
                        'message' => 'Documento no encontrado',
                        'data' => null,
                        'meta' => $meta,
                    ], 404);
                    return;
                }

                $patientId = trim((string)($document['context']['patient_id'] ?? ''));
                if ($patientId === '' || !clinical_has_active_doctor_patient_link($pdo, $doctorId, $patientId)) {
                    clinical_send_response([
                        'ok' => false,
                        'error' => 'forbidden',
                        'message' => 'doctor patient link required',
                        'data' => null,
                        'meta' => $meta,
                    ], 403);
                    return;
                }
            } catch (Throwable $e) {
                $msg = trim($e->getMessage());
                clinical_send_response([
                    'ok' => false,
                    'error' => 'server_error',
                    'message' => ($msg !== '') ? $msg : 'server error',
                    'data' => null,
                    'meta' => $meta,
                ], 500);
                return;
            }

            clinical_send_response([
                'ok' => true,
                'error' => null,
                'message' => 'document retrieved',
                'data' => [
                    'document' => $document,
                ],
                'meta' => $meta,
            ], 200);
            return;
        }

        if ($method === 'PATCH' && count($segments) === 4 && ($segments[2] ?? '') === 'documents') {
            $doctorId = trim(rawurldecode((string)$segments[1]));
            $documentToken = trim(rawurldecode((string)$segments[3]));
            $meta = [
                'method' => 'PATCH',
                'route' => 'doctors/{doctor_id}/documents/{id_or_uuid}',
                'source' => 'clinical_documents_pdo',
                'scope' => 'doctor_patient',
            ];
            if ($doctorId === '' || $documentToken === '') {
                clinical_send_response([
                    'ok' => false,
                    'error' => 'bad_request',
                    'message' => 'doctor_id and document id_or_uuid required',
                    'data' => null,
                    'meta' => $meta,
                ], 400);
                return;
            }

            try {
                $pdo = clinical_documents_pdo();
                $document = clinical_documents_get_by_token_fetch($pdo, $documentToken);
                if ($document === null) {
                    clinical_send_response([
                        'ok' => false,
                        'error' => 'not_found',
                        'message' => 'Documento no encontrado',
                        'data' => null,
                        'meta' => $meta,
                    ], 404);
                    return;
                }

                $patientId = trim((string)($document['context']['patient_id'] ?? ''));
                if ($patientId === '' || !clinical_has_active_doctor_patient_link($pdo, $doctorId, $patientId)) {
                    clinical_send_response([
                        'ok' => false,
                        'error' => 'forbidden',
                        'message' => 'doctor patient link required',
                        'data' => null,
                        'meta' => $meta,
                    ], 403);
                    return;
                }
            } catch (Throwable $e) {
                $msg = trim($e->getMessage());
                clinical_send_response([
                    'ok' => false,
                    'error' => 'server_error',
                    'message' => ($msg !== '') ? $msg : 'server error',
                    'data' => null,
                    'meta' => $meta,
                ], 500);
                return;
            }

            $segments = ['documents', $documentToken];
        }

        if ($method === 'POST' && count($segments) === 5 && ($segments[2] ?? '') === 'documents' && ($segments[4] ?? '') === 'replace') {
            $doctorId = trim(rawurldecode((string)$segments[1]));
            $documentToken = trim(rawurldecode((string)$segments[3]));
            $meta = [
                'method' => 'POST',
                'route' => 'doctors/{doctor_id}/documents/{id_or_uuid}/replace',
                'source' => 'clinical_documents_pdo',
                'scope' => 'doctor_patient',
            ];
            if ($doctorId === '' || $documentToken === '') {
                clinical_send_response([
                    'ok' => false,
                    'error' => 'bad_request',
                    'message' => 'doctor_id and document id_or_uuid required',
                    'data' => null,
                    'meta' => $meta,
                ], 400);
                return;
            }

            try {
                $pdo = clinical_documents_pdo();
                $document = clinical_documents_get_by_token_fetch($pdo, $documentToken);
                if ($document === null) {
                    clinical_send_response([
                        'ok' => false,
                        'error' => 'not_found',
                        'message' => 'source document not found',
                        'data' => null,
                        'meta' => $meta,
                    ], 404);
                    return;
                }

                $patientId = trim((string)($document['context']['patient_id'] ?? ''));
                if ($patientId === '' || !clinical_has_active_doctor_patient_link($pdo, $doctorId, $patientId)) {
                    clinical_send_response([
                        'ok' => false,
                        'error' => 'forbidden',
                        'message' => 'doctor patient link required',
                        'data' => null,
                        'meta' => $meta,
                    ], 403);
                    return;
                }
            } catch (Throwable $e) {
                $msg = trim($e->getMessage());
                clinical_send_response([
                    'ok' => false,
                    'error' => 'server_error',
                    'message' => ($msg !== '') ? $msg : 'server error',
                    'data' => null,
                    'meta' => $meta,
                ], 500);
                return;
            }

            $segments = ['documents', $documentToken, 'replace'];
        }

        if ($method === 'POST' && count($segments) === 5 && ($segments[2] ?? '') === 'documents' && ($segments[4] ?? '') === 'replicate') {
            $doctorId = trim(rawurldecode((string)$segments[1]));
            $documentToken = trim(rawurldecode((string)$segments[3]));
            $meta = [
                'method' => 'POST',
                'route' => 'doctors/{doctor_id}/documents/{uuid}/replicate',
                'source' => 'clinical_documents_pdo',
                'scope' => 'doctor_patient',
            ];
            if ($doctorId === '' || $documentToken === '') {
                clinical_send_response([
                    'ok' => false,
                    'error' => 'bad_request',
                    'message' => 'doctor_id and document uuid required',
                    'data' => null,
                    'meta' => $meta,
                ], 400);
                return;
            }

            try {
                $pdo = clinical_documents_pdo();
                $document = clinical_documents_get_by_token_fetch($pdo, $documentToken);
                if ($document === null) {
                    clinical_send_response([
                        'ok' => false,
                        'error' => 'not_found',
                        'message' => 'source document not found',
                        'data' => null,
                        'meta' => $meta,
                    ], 404);
                    return;
                }

                $patientId = trim((string)($document['context']['patient_id'] ?? ''));
                if ($patientId === '' || !clinical_has_active_doctor_patient_link($pdo, $doctorId, $patientId)) {
                    clinical_send_response([
                        'ok' => false,
                        'error' => 'forbidden',
                        'message' => 'doctor patient link required',
                        'data' => null,
                        'meta' => $meta,
                    ], 403);
                    return;
                }

                $sourceUuid = trim((string)($document['document_id'] ?? ($document['document_uuid'] ?? '')));
                if ($sourceUuid === '') {
                    clinical_send_response([
                        'ok' => false,
                        'error' => 'bad_request',
                        'message' => 'source document uuid requerido',
                        'data' => null,
                        'meta' => $meta,
                    ], 400);
                    return;
                }
            } catch (Throwable $e) {
                $msg = trim($e->getMessage());
                clinical_send_response([
                    'ok' => false,
                    'error' => 'server_error',
                    'message' => ($msg !== '') ? $msg : 'server error',
                    'data' => null,
                    'meta' => $meta,
                ], 500);
                return;
            }

            $segments = ['documents', $sourceUuid, 'replicate'];
        }
    }

    if (($segments[0] ?? '') === 'documents') {
        if ($method === 'POST' && count($segments) === 1) {
            $meta = [
                'method' => 'POST',
                'route' => 'clinical_gateway',
                'resource' => 'documents',
                'fallback_used' => false,
                'source' => 'clinical_documents_pdo',
            ];
            $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? ''));
            $isMultipart = (!empty($_FILES) || strpos($contentType, 'multipart/form-data') !== false);
            $payload = [];
            $uploadFile = null;
            if ($isMultipart) {
                $payload = is_array($_POST) ? $_POST : [];
                if (isset($payload['payload']) && is_string($payload['payload'])) {
                    $payloadDecoded = json_decode((string)$payload['payload'], true);
                    if (is_array($payloadDecoded)) {
                        $payload['payload'] = $payloadDecoded;
                    }
                }
                $uploadCandidate = $_FILES['file'] ?? null;
                if (is_array($uploadCandidate) && (($uploadCandidate['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE)) {
                    $uploadFile = $uploadCandidate;
                    $uploadError = (int)($uploadFile['error'] ?? UPLOAD_ERR_NO_FILE);
                    if ($uploadError !== UPLOAD_ERR_OK) {
                        clinical_send_response([
                            'ok' => false,
                            'error' => 'bad_request',
                            'message' => 'upload inválido',
                            'data' => null,
                            'meta' => $meta,
                        ], 400);
                        return;
                    }
                }
            } else {
                $bodyResult = clinical_read_json_body();
                if ($bodyResult['ok'] !== true) {
                    clinical_send_response([
                        'ok' => false,
                        'error' => 'bad_request',
                        'message' => (string)$bodyResult['error'],
                        'data' => null,
                        'meta' => $meta,
                    ], 400);
                    return;
                }
                $payload = is_array($bodyResult['data'] ?? null) ? (array)$bodyResult['data'] : [];
            }

            try {
                $pdo = clinical_documents_pdo();
                $looksLikeUploadDocument = $isMultipart && (
                    is_array($uploadFile)
                    || trim((string)($payload['document_type'] ?? '')) !== ''
                );
                if ($looksLikeUploadDocument) {
                    $document = clinical_documents_gateway_save_upload($pdo, $payload, $uploadFile, true);
                } else {
                    $document = clinical_documents_save_passthrough($pdo, $payload, true);
                }
            } catch (ClinicalDocumentsPatientWriteException $e) {
                clinical_documents_send_patient_write_error($e, $meta);
                return;
            } catch (InvalidArgumentException $e) {
                $msg = trim((string)$e->getMessage());
                clinical_send_response([
                    'ok' => false,
                    'error' => 'invalid_params',
                    'message' => ($msg !== '') ? $msg : 'invalid params',
                    'data' => null,
                    'meta' => $meta,
                ], 400);
                return;
            } catch (RuntimeException $e) {
                $msg = trim((string)$e->getMessage());
                if ($msg === 'MEDIA_TAG_REQUIRED') {
                    clinical_send_response([
                        'ok' => false,
                        'error' => [
                            'code' => 'MEDIA_TAG_REQUIRED',
                            'message' => 'Selecciona una etiqueta para esta imagen.',
                        ],
                        'message' => 'Selecciona una etiqueta para esta imagen.',
                        'data' => null,
                        'meta' => $meta,
                    ], 400);
                    return;
                }
                clinical_send_response([
                    'ok' => false,
                    'error' => 'server_error',
                    'message' => ($msg !== '') ? $msg : 'server error',
                    'data' => null,
                    'meta' => $meta,
                ], 500);
                return;
            } catch (Throwable $e) {
                $msg = trim((string)$e->getMessage());
                clinical_send_response([
                    'ok' => false,
                    'error' => 'server_error',
                    'message' => ($msg !== '') ? $msg : 'server error',
                    'data' => null,
                    'meta' => $meta,
                ], 500);
                return;
            }

            clinical_send_response([
                'ok' => true,
                'error' => null,
                'message' => 'document saved',
                'data' => [
                    'document_id' => $document['document_id'] ?? ($document['document_uuid'] ?? null),
                    'document' => $document,
                ],
                'meta' => $meta,
            ], 201);
            return;
        }

        if ($method === 'POST' && count($segments) === 3 && ($segments[2] ?? '') === 'replicate') {
            $sourceUuid = trim(rawurldecode((string)$segments[1]));
            if ($sourceUuid === '') {
                clinical_send_response([
                    'ok' => false,
                    'error' => 'bad_request',
                    'message' => 'source document uuid requerido',
                    'data' => null,
                    'meta' => [
                        'method' => 'POST',
                        'route' => 'documents/{uuid}/replicate',
                    ],
                ], 400);
                return;
            }

            $bodyResult = clinical_read_json_body();
            if (($bodyResult['ok'] ?? false) !== true) {
                clinical_send_response([
                    'ok' => false,
                    'error' => 'bad_request',
                    'message' => (string)($bodyResult['error'] ?? 'invalid body'),
                    'data' => null,
                    'meta' => [
                        'method' => 'POST',
                        'route' => 'documents/{uuid}/replicate',
                    ],
                ], 400);
                return;
            }
            $body = is_array($bodyResult['data'] ?? null) ? (array)$bodyResult['data'] : [];
            $titleOverride = trim((string)($body['title_override'] ?? ''));
            $summaryOverride = trim((string)($body['summary_override'] ?? ''));
            $targetEncounterKey = trim((string)($body['target_encounter_key'] ?? ''));
            $targetPatientId = trim((string)($body['target_patient_id'] ?? ''));

            try {
                $pdo = clinical_documents_pdo();
                clinical_encounters_ensure_schema($pdo);
                $sourceDoc = clinical_documents_get_by_uuid_fetch($pdo, $sourceUuid);
                if (!is_array($sourceDoc)) {
                    clinical_send_response([
                        'ok' => false,
                        'error' => 'not_found',
                        'message' => 'source document not found',
                        'data' => null,
                        'meta' => [
                            'method' => 'POST',
                            'route' => 'documents/{uuid}/replicate',
                        ],
                    ], 404);
                    return;
                }

                $sourceStmt = $pdo->prepare("
                    SELECT patient_id, appointment_id, encounter_id, hospital_stay_id, document_type, title, summary, payload_json, created_by_user_id, care_setting
                    FROM clinical_documents
                    WHERE document_uuid = :uuid
                    LIMIT 1
                ");
                $sourceStmt->bindValue(':uuid', $sourceUuid, PDO::PARAM_STR);
                $sourceStmt->execute();
                $sourceRow = $sourceStmt->fetch(PDO::FETCH_ASSOC);
                if (!is_array($sourceRow)) {
                    clinical_send_response([
                        'ok' => false,
                        'error' => 'not_found',
                        'message' => 'source document not found',
                        'data' => null,
                        'meta' => [
                            'method' => 'POST',
                            'route' => 'documents/{uuid}/replicate',
                        ],
                    ], 404);
                    return;
                }

                $patientId = trim((string)($sourceRow['patient_id'] ?? ''));
                if ($patientId === '') {
                    clinical_send_response([
                        'ok' => false,
                        'error' => 'bad_request',
                        'message' => 'source document without patient_id',
                        'data' => null,
                        'meta' => [
                            'method' => 'POST',
                            'route' => 'documents/{uuid}/replicate',
                        ],
                    ], 400);
                    return;
                }
                try {
                    $patientId = clinical_documents_validate_canonical_patient_id_for_write($pdo, $patientId);
                } catch (ClinicalDocumentsPatientWriteException $e) {
                    clinical_documents_send_patient_write_error($e, [
                        'method' => 'POST',
                        'route' => 'documents/{uuid}/replicate',
                    ]);
                    return;
                }

                if ($targetPatientId !== '' && $targetPatientId !== $patientId) {
                    clinical_send_response([
                        'ok' => false,
                        'error' => 'bad_request',
                        'message' => 'target_patient_id must match source patient_id',
                        'data' => null,
                        'meta' => [
                            'method' => 'POST',
                            'route' => 'documents/{uuid}/replicate',
                        ],
                    ], 400);
                    return;
                }

                $appointmentId = trim((string)($sourceRow['appointment_id'] ?? ''));
                $encounterId = (int)trim((string)($sourceRow['encounter_id'] ?? '0'));
                $hospitalStayId = trim((string)($sourceRow['hospital_stay_id'] ?? ''));
                if ($targetEncounterKey !== '') {
                    $resolved = clinical_resolve_encounter_key($pdo, $targetEncounterKey);
                    if (($resolved['ok'] ?? false) !== true) {
                        clinical_send_response([
                            'ok' => false,
                            'error' => (string)($resolved['error_code'] ?? 'bad_request'),
                            'message' => (string)($resolved['error_message'] ?? 'target_encounter_key inválido'),
                            'data' => null,
                            'meta' => [
                                'method' => 'POST',
                                'route' => 'documents/{uuid}/replicate',
                            ],
                        ], 400);
                        return;
                    }
                    $encounterRow = is_array($resolved['row'] ?? null) ? $resolved['row'] : [];
                    $resolvedPatientId = trim((string)($encounterRow['patient_id'] ?? ''));
                    if ($resolvedPatientId === '' || $resolvedPatientId !== $patientId) {
                        clinical_send_response([
                            'ok' => false,
                            'error' => 'bad_request',
                            'message' => 'target_encounter_key patient mismatch',
                            'data' => null,
                            'meta' => [
                                'method' => 'POST',
                                'route' => 'documents/{uuid}/replicate',
                            ],
                        ], 400);
                        return;
                    }
                    $encounterId = (int)($encounterRow['encounter_id'] ?? 0);
                    $appointmentId = trim((string)($encounterRow['appointment_id'] ?? ''));
                }

                $payloadData = json_decode((string)($sourceRow['payload_json'] ?? ''), true);
                if (!is_array($payloadData)) {
                    $payloadData = [];
                }
                $careSetting = trim((string)($sourceRow['care_setting'] ?? ''));
                if ($careSetting === '') {
                    $careSetting = trim((string)($payloadData['care_setting'] ?? (($payloadData['context']['care_setting'] ?? ''))));
                }
                if ($careSetting === '') {
                    $careSetting = 'consulta';
                }
                $metaPayload = is_array($payloadData['meta'] ?? null) ? (array)$payloadData['meta'] : [];
                $metaPayload['source_document_uuid'] = $sourceUuid;
                $metaPayload['replicated_at'] = gmdate('Y-m-d H:i:s');
                $payloadData['meta'] = $metaPayload;

                $payloadJson = json_encode($payloadData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if (!is_string($payloadJson)) {
                    $payloadJson = '{}';
                }

                $sourceType = trim((string)($sourceRow['document_type'] ?? ''));
                $sourceTitle = trim((string)($sourceRow['title'] ?? ''));
                $sourceSummary = trim((string)($sourceRow['summary'] ?? ''));
                $title = ($titleOverride !== '') ? $titleOverride : (($sourceTitle !== '') ? ($sourceTitle . ' (replicado)') : 'Documento replicado');
                $summary = ($summaryOverride !== '') ? $summaryOverride : $sourceSummary;
                $now = gmdate('Y-m-d H:i:s');
                $newUuid = clinical_generate_document_uuid();
                $cols = clinical_table_columns($pdo, 'clinical_documents');
                $statusValue = 'generated';
                try {
                    $statusStmt = $pdo->prepare("SHOW COLUMNS FROM `clinical_documents` LIKE 'status'");
                    $statusStmt->execute();
                    $statusRow = $statusStmt->fetch(PDO::FETCH_ASSOC);
                    $statusType = strtolower(trim((string)($statusRow['Type'] ?? '')));
                    if ($statusType !== '' && strpos($statusType, "'draft'") !== false) {
                        $statusValue = 'draft';
                    }
                } catch (Throwable $e) {
                    $statusValue = 'generated';
                }
                $values = [
                    'document_uuid' => $newUuid,
                    'document_type' => $sourceType,
                    'title' => $title,
                    'version' => 1,
                    'status' => $statusValue,
                    'patient_id' => $patientId,
                    'appointment_id' => ($appointmentId !== '' ? $appointmentId : null),
                    'encounter_id' => ($encounterId > 0 ? (string)$encounterId : null),
                    'hospital_stay_id' => ($hospitalStayId !== '' ? $hospitalStayId : null),
                    'care_setting' => $careSetting,
                    'service' => null,
                    'payload_json' => $payloadJson,
                    'rendered_text' => null,
                    'summary' => $summary,
                    'edited_flag' => 0,
                    'event_datetime' => $now,
                    'widget_group' => 'documentos_clinicos',
                    'printable' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'generated_at' => $now,
                    'signed_at' => null,
                    'created_by_user_id' => 'system',
                    'updated_by_user_id' => 'system',
                ];
                $insertCols = [];
                $placeholders = [];
                $params = [];
                foreach ($values as $col => $val) {
                    if (!isset($cols[$col])) {
                        continue;
                    }
                    $insertCols[] = '`' . $col . '`';
                    $ph = ':r_' . $col;
                    $placeholders[] = $ph;
                    $params[$ph] = $val;
                }
                $sql = 'INSERT INTO clinical_documents (' . implode(', ', $insertCols) . ') VALUES (' . implode(', ', $placeholders) . ')';
                $stmt = $pdo->prepare($sql);
                foreach ($params as $ph => $val) {
                    if ($val === null) {
                        $stmt->bindValue($ph, null, PDO::PARAM_NULL);
                    } else {
                        $stmt->bindValue($ph, (string)$val, PDO::PARAM_STR);
                    }
                }
                $stmt->execute();

                clinical_send_response([
                    'ok' => true,
                    'error' => null,
                    'message' => 'document replicated',
                    'data' => [
                        'document_uuid' => $newUuid,
                        'source_document_uuid' => $sourceUuid,
                        'patient_id' => $patientId,
                        'encounter_id' => ($encounterId > 0 ? $encounterId : null),
                        'appointment_id' => ($appointmentId !== '' ? $appointmentId : null),
                    ],
                    'meta' => [
                        'method' => 'POST',
                        'route' => 'documents/{uuid}/replicate',
                    ],
                ], 200);
                return;
            } catch (Throwable $e) {
                clinical_send_response([
                    'ok' => false,
                    'error' => 'server_error',
                    'message' => trim((string)$e->getMessage()) ?: 'server error',
                    'data' => null,
                    'meta' => [
                        'method' => 'POST',
                        'route' => 'documents/{uuid}/replicate',
                    ],
                ], 500);
                return;
            }
        }

        if ($method === 'POST' && count($segments) === 3 && ($segments[2] ?? '') === 'replace') {
            $sourceToken = trim(rawurldecode((string)$segments[1]));
            if ($sourceToken === '') {
                clinical_send_response([
                    'ok' => false,
                    'error' => 'bad_request',
                    'message' => 'source document id_or_uuid requerido',
                    'data' => null,
                    'meta' => [
                        'method' => 'POST',
                        'route' => 'documents/{id_or_uuid}/replace',
                    ],
                ], 400);
                return;
            }

            $bodyResult = clinical_read_json_body();
            if (($bodyResult['ok'] ?? false) !== true) {
                clinical_send_response([
                    'ok' => false,
                    'error' => 'bad_request',
                    'message' => (string)($bodyResult['error'] ?? 'invalid body'),
                    'data' => null,
                    'meta' => [
                        'method' => 'POST',
                        'route' => 'documents/{id_or_uuid}/replace',
                    ],
                ], 400);
                return;
            }
            $body = is_array($bodyResult['data'] ?? null) ? (array)$bodyResult['data'] : [];

            try {
                $pdo = clinical_documents_pdo();
                $sourceDoc = clinical_documents_get_by_token_fetch($pdo, $sourceToken);
                if (!is_array($sourceDoc)) {
                    clinical_send_response([
                        'ok' => false,
                        'error' => 'not_found',
                        'message' => 'source document not found',
                        'data' => null,
                        'meta' => [
                            'method' => 'POST',
                            'route' => 'documents/{id_or_uuid}/replace',
                        ],
                    ], 404);
                    return;
                }

                $sourceId = trim((string)($sourceDoc['document_db_id'] ?? ''));
                $sourceUuid = trim((string)($sourceDoc['document_id'] ?? ''));
                $sourceType = trim((string)($sourceDoc['document_type'] ?? ''));
                if (!in_array($sourceType, ['lab_order', 'imaging_order'], true)) {
                    clinical_send_response([
                        'ok' => false,
                        'error' => 'bad_request',
                        'message' => 'solo se puede reemplazar lab_order o imaging_order',
                        'data' => null,
                        'meta' => [
                            'method' => 'POST',
                            'route' => 'documents/{id_or_uuid}/replace',
                        ],
                    ], 400);
                    return;
                }

                $sourceStmt = $pdo->prepare("
                    SELECT *
                    FROM clinical_documents
                    WHERE id = :id
                    LIMIT 1
                ");
                $sourceStmt->bindValue(':id', (int)$sourceId, PDO::PARAM_INT);
                $sourceStmt->execute();
                $sourceRow = $sourceStmt->fetch(PDO::FETCH_ASSOC);
                if (!is_array($sourceRow)) {
                    clinical_send_response([
                        'ok' => false,
                        'error' => 'not_found',
                        'message' => 'source document not found',
                        'data' => null,
                        'meta' => [
                            'method' => 'POST',
                            'route' => 'documents/{id_or_uuid}/replace',
                        ],
                    ], 404);
                    return;
                }

                $patientId = trim((string)($sourceRow['patient_id'] ?? ''));
                if ($patientId === '') {
                    clinical_send_response([
                        'ok' => false,
                        'error' => 'bad_request',
                        'message' => 'source document without patient_id',
                        'data' => null,
                        'meta' => [
                            'method' => 'POST',
                            'route' => 'documents/{id_or_uuid}/replace',
                        ],
                    ], 400);
                    return;
                }
                try {
                    $patientId = clinical_documents_validate_canonical_patient_id_for_write($pdo, $patientId);
                } catch (ClinicalDocumentsPatientWriteException $e) {
                    clinical_documents_send_patient_write_error($e, [
                        'method' => 'POST',
                        'route' => 'documents/{id_or_uuid}/replace',
                    ]);
                    return;
                }
                $sourcePayload = json_decode((string)($sourceRow['payload_json'] ?? ''), true);
                if (!is_array($sourcePayload)) {
                    $sourcePayload = [];
                }
                $sourceStatus = strtolower(trim((string)($sourcePayload['status'] ?? '')));
                $sourceReplacedById = trim((string)($sourcePayload['replaced_by_document_id'] ?? ''));
                $sourceReplacedByUuid = trim((string)($sourcePayload['replaced_by_document_uuid'] ?? ''));
                $alreadyReplaced = ($sourceStatus === 'replaced') || $sourceReplacedById !== '' || $sourceReplacedByUuid !== '';
                if ($alreadyReplaced) {
                    clinical_send_response([
                        'ok' => false,
                        'error' => 'conflict',
                        'message' => 'la orden ya fue reemplazada',
                        'data' => null,
                        'meta' => [
                            'method' => 'POST',
                            'route' => 'documents/{id_or_uuid}/replace',
                        ],
                    ], 409);
                    return;
                }

                if (clinical_document_has_linked_result($pdo, $patientId, $sourceId, $sourceUuid)) {
                    clinical_send_response([
                        'ok' => false,
                        'error' => 'conflict',
                        'message' => 'no se puede reemplazar una orden con resultado cargado',
                        'data' => null,
                        'meta' => [
                            'method' => 'POST',
                            'route' => 'documents/{id_or_uuid}/replace',
                        ],
                    ], 409);
                    return;
                }

                $requestedStudies = [];
                $fromBodyStudies = $body['requested_studies'] ?? null;
                if (is_array($fromBodyStudies)) {
                    foreach ($fromBodyStudies as $study) {
                        $value = trim((string)$study);
                        if ($value !== '') {
                            $requestedStudies[] = $value;
                        }
                    }
                }
                if (!$requestedStudies) {
                    $fromSource = is_array($sourcePayload['requested_studies'] ?? null) ? $sourcePayload['requested_studies'] : [];
                    foreach ($fromSource as $study) {
                        $value = trim((string)$study);
                        if ($value !== '') {
                            $requestedStudies[] = $value;
                        }
                    }
                }
                $requestedStudies = array_values(array_unique($requestedStudies));
                if (!$requestedStudies) {
                    clinical_send_response([
                        'ok' => false,
                        'error' => 'bad_request',
                        'message' => 'requested_studies requerido',
                        'data' => null,
                        'meta' => [
                            'method' => 'POST',
                            'route' => 'documents/{id_or_uuid}/replace',
                        ],
                    ], 400);
                    return;
                }

                $orderArea = trim((string)($body['order_area'] ?? ($sourcePayload['order_area'] ?? '')));
                if ($orderArea === '') {
                    $orderArea = ($sourceType === 'imaging_order') ? 'Imagenología' : 'Laboratorio';
                }
                $bodyType = strtolower(trim((string)($body['document_type'] ?? '')));
                if ($bodyType === 'lab_order' || $bodyType === 'imaging_order') {
                    $newType = $bodyType;
                } else {
                    $normalizedArea = strtolower($orderArea);
                    $newType = (strpos($normalizedArea, 'imagen') !== false) ? 'imaging_order' : 'lab_order';
                }
                $priority = trim((string)($body['priority'] ?? ($sourcePayload['priority'] ?? '')));
                $indication = trim((string)($body['indication'] ?? ($sourcePayload['indication'] ?? '')));
                $flags = is_array($body['flags'] ?? null) ? array_values(array_filter(array_map('strval', $body['flags']), static function ($v) {
                    return trim($v) !== '';
                })) : (is_array($sourcePayload['flags'] ?? null) ? $sourcePayload['flags'] : []);
                $replacementReason = trim((string)($body['replacement_reason'] ?? ''));
                $eventDatetime = trim((string)($body['event_datetime'] ?? ''));
                if ($eventDatetime === '') {
                    $eventDatetime = gmdate('Y-m-d H:i:s');
                }
                if (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}$/', $eventDatetime) !== 1) {
                    $eventDatetime = gmdate('Y-m-d H:i:s');
                }

                $title = trim((string)($body['title_override'] ?? ''));
                if ($title === '') {
                    $title = ($newType === 'imaging_order') ? 'Orden de imagen' : 'Orden de laboratorio';
                }
                $summary = trim((string)($body['summary_override'] ?? ''));
                if ($summary === '') {
                    $summaryParts = [count($requestedStudies) . ' estudio' . (count($requestedStudies) === 1 ? '' : 's')];
                    if ($priority !== '') {
                        $summaryParts[] = $priority;
                    }
                    if ($indication !== '') {
                        $summaryParts[] = $indication;
                    }
                    $summary = implode(' · ', $summaryParts);
                }

                $replacementPayload = $sourcePayload;
                $replacementPayload['source'] = 'estudios_host_replace';
                $replacementPayload['status'] = 'active';
                $replacementPayload['replacement_mode'] = 'replacement';
                $replacementPayload['replacement_source_document_id'] = $sourceId;
                $replacementPayload['replacement_source_document_uuid'] = $sourceUuid;
                $replacementPayload['order_area'] = $orderArea;
                $replacementPayload['priority'] = ($priority !== '') ? $priority : null;
                $replacementPayload['indication'] = ($indication !== '') ? $indication : null;
                $replacementPayload['requested_studies'] = $requestedStudies;
                $replacementPayload['selection_count'] = count($requestedStudies);
                $replacementPayload['flags'] = $flags;
                if (isset($replacementPayload['replaced_by_document_id'])) unset($replacementPayload['replaced_by_document_id']);
                if (isset($replacementPayload['replaced_by_document_uuid'])) unset($replacementPayload['replaced_by_document_uuid']);
                if (isset($replacementPayload['replacement_at'])) unset($replacementPayload['replacement_at']);
                if (isset($replacementPayload['replacement_reason'])) unset($replacementPayload['replacement_reason']);

                $payloadJson = json_encode($replacementPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if (!is_string($payloadJson)) {
                    $payloadJson = '{}';
                }
                $now = gmdate('Y-m-d H:i:s');
                $newUuid = clinical_generate_document_uuid();
                $cols = clinical_table_columns($pdo, 'clinical_documents');
                $actorUserId = clinical_request_actor_user_id_strict($body);
                if ($actorUserId === '') {
                    $actorUserId = clinical_request_actor_user_id($body);
                }
                $insertValues = [
                    'document_uuid' => $newUuid,
                    'document_type' => $newType,
                    'title' => $title,
                    'version' => 1,
                    'status' => 'signed',
                    'patient_id' => $patientId,
                    'appointment_id' => trim((string)($sourceRow['appointment_id'] ?? '')) !== '' ? (string)$sourceRow['appointment_id'] : null,
                    'encounter_id' => trim((string)($sourceRow['encounter_id'] ?? '')) !== '' ? (string)$sourceRow['encounter_id'] : null,
                    'hospital_stay_id' => trim((string)($sourceRow['hospital_stay_id'] ?? '')) !== '' ? (string)$sourceRow['hospital_stay_id'] : null,
                    'care_setting' => trim((string)($sourceRow['care_setting'] ?? 'consulta')) ?: 'consulta',
                    'service' => trim((string)($sourceRow['service'] ?? '')) !== '' ? (string)$sourceRow['service'] : null,
                    'payload_json' => $payloadJson,
                    'rendered_text' => null,
                    'summary' => $summary,
                    'edited_flag' => 0,
                    'event_datetime' => $eventDatetime,
                    'widget_group' => 'ordenes_diagnosticas',
                    'printable' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'generated_at' => $now,
                    'signed_at' => $now,
                    'created_by_user_id' => $actorUserId,
                    'updated_by_user_id' => $actorUserId,
                ];

                $pdo->beginTransaction();
                $insertCols = [];
                $insertVals = [];
                $insertParams = [];
                foreach ($insertValues as $col => $val) {
                    if (!isset($cols[$col])) {
                        continue;
                    }
                    $insertCols[] = '`' . $col . '`';
                    $ph = ':n_' . $col;
                    $insertVals[] = $ph;
                    $insertParams[$ph] = $val;
                }
                $sql = 'INSERT INTO clinical_documents (' . implode(', ', $insertCols) . ') VALUES (' . implode(', ', $insertVals) . ')';
                $insertStmt = $pdo->prepare($sql);
                foreach ($insertParams as $ph => $val) {
                    if ($val === null) {
                        $insertStmt->bindValue($ph, null, PDO::PARAM_NULL);
                    } else {
                        $insertStmt->bindValue($ph, (string)$val, PDO::PARAM_STR);
                    }
                }
                $insertStmt->execute();
                $newId = (int)$pdo->lastInsertId();
                if ($newId <= 0) {
                    throw new RuntimeException('no se pudo crear orden reemplazante');
                }

                $sourcePayload['status'] = 'replaced';
                $sourcePayload['replaced_by_document_id'] = (string)$newId;
                $sourcePayload['replaced_by_document_uuid'] = $newUuid;
                $sourcePayload['replacement_reason'] = ($replacementReason !== '') ? $replacementReason : null;
                $sourcePayload['replacement_at'] = $now;
                $sourcePayloadJson = json_encode($sourcePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if (!is_string($sourcePayloadJson)) {
                    $sourcePayloadJson = '{}';
                }
                $updateStmt = $pdo->prepare("
                    UPDATE clinical_documents
                    SET payload_json = :payload_json,
                        updated_at = :updated_at,
                        updated_by_user_id = :updated_by_user_id
                    WHERE id = :id
                ");
                $updateStmt->bindValue(':payload_json', $sourcePayloadJson, PDO::PARAM_STR);
                $updateStmt->bindValue(':updated_at', $now, PDO::PARAM_STR);
                $updateStmt->bindValue(':updated_by_user_id', $actorUserId, PDO::PARAM_STR);
                $updateStmt->bindValue(':id', (int)$sourceId, PDO::PARAM_INT);
                $updateStmt->execute();

                $pdo->commit();
                $replacementDocument = clinical_documents_get_fetch($pdo, $newId);
                clinical_send_response([
                    'ok' => true,
                    'error' => null,
                    'message' => 'document replaced',
                    'data' => [
                        'replacement_document_id' => $newId,
                        'replacement_document_uuid' => $newUuid,
                        'source_document_id' => (int)$sourceId,
                        'source_document_uuid' => $sourceUuid,
                        'replacement_document' => $replacementDocument,
                    ],
                    'meta' => [
                        'method' => 'POST',
                        'route' => 'documents/{id_or_uuid}/replace',
                    ],
                ], 200);
                return;
            } catch (Throwable $e) {
                if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                    try { $pdo->rollBack(); } catch (Throwable $ignore) {}
                }
                clinical_send_response([
                    'ok' => false,
                    'error' => 'server_error',
                    'message' => trim((string)$e->getMessage()) ?: 'server error',
                    'data' => null,
                    'meta' => [
                        'method' => 'POST',
                        'route' => 'documents/{id_or_uuid}/replace',
                    ],
                ], 500);
                return;
            }
        }

        if ($method === 'GET' && count($segments) === 1) {
            $patientId = trim((string)($_GET['patient_id'] ?? ''));
            if ($patientId === '') {
                clinical_send_response([
                    'ok' => false,
                    'error' => 'bad_request',
                    'message' => 'patient_id requerido',
                    'data' => null,
                    'meta' => [
                        'method' => 'GET',
                        'route' => 'documents',
                        'source' => 'clinical_documents_pdo',
                    ],
                ], 400);
                return;
            }

            $limit = (int)($_GET['limit'] ?? 30);
            if ($limit <= 0) {
                $limit = 30;
            } elseif ($limit > 200) {
                $limit = 200;
            }

            $documentType = trim((string)($_GET['document_type'] ?? ''));
            $hospitalStayId = trim((string)($_GET['hospital_stay_id'] ?? ''));

            try {
                $pdo = clinical_documents_pdo();
                $items = clinical_documents_list_fetch($pdo, $patientId, $documentType, $hospitalStayId, $limit);
            } catch (Throwable $e) {
                $msg = trim($e->getMessage());
                clinical_send_response([
                    'ok' => false,
                    'error' => 'server_error',
                    'message' => ($msg !== '') ? $msg : 'server error',
                    'data' => null,
                    'meta' => [
                        'method' => 'GET',
                        'route' => 'documents',
                        'source' => 'clinical_documents_pdo',
                    ],
                ], 500);
                return;
            }

            clinical_send_response([
                'ok' => true,
                'error' => null,
                'message' => 'documents listed',
                'data' => [
                    'items' => $items,
                ],
                'meta' => [
                    'method' => 'GET',
                    'route' => 'documents',
                    'source' => 'clinical_documents_pdo',
                ],
            ], 200);
            return;
        }

        if ($method === 'GET' && count($segments) === 2) {
            $documentToken = trim((string)$segments[1]);
            if ($documentToken === '') {
                clinical_send_response([
                    'ok' => false,
                    'error' => 'bad_request',
                    'message' => 'document id or uuid required',
                    'data' => null,
                    'meta' => [
                        'method' => 'GET',
                        'route' => 'documents/{id_or_uuid}',
                        'source' => 'clinical_documents_pdo',
                    ],
                ], 400);
                return;
            }

            try {
                $pdo = clinical_documents_pdo();
                if (preg_match('/^\d+$/', $documentToken) === 1) {
                    $document = clinical_documents_get_fetch($pdo, (int)$documentToken);
                } else {
                    $document = clinical_documents_get_by_uuid_fetch($pdo, $documentToken);
                }
            } catch (Throwable $e) {
                $msg = trim($e->getMessage());
                clinical_send_response([
                    'ok' => false,
                    'error' => 'server_error',
                    'message' => ($msg !== '') ? $msg : 'server error',
                    'data' => null,
                    'meta' => [
                        'method' => 'GET',
                        'route' => 'documents/{id_or_uuid}',
                        'source' => 'clinical_documents_pdo',
                    ],
                ], 500);
                return;
            }

            if ($document === null) {
                clinical_send_response([
                    'ok' => false,
                    'error' => 'not_found',
                    'message' => 'Documento no encontrado',
                    'data' => null,
                    'meta' => [
                        'method' => 'GET',
                        'route' => 'documents/{id_or_uuid}',
                        'source' => 'clinical_documents_pdo',
                    ],
                ], 404);
                return;
            }

            clinical_send_response([
                'ok' => true,
                'error' => null,
                'message' => 'document retrieved',
                'data' => [
                    'document' => $document,
                ],
                'meta' => [
                    'method' => 'GET',
                    'route' => 'documents/{id_or_uuid}',
                    'source' => 'clinical_documents_pdo',
                ],
            ], 200);
            return;
        }

        if ($method === 'PATCH' && count($segments) === 2) {
            $documentToken = trim((string)$segments[1]);
            if ($documentToken === '') {
                clinical_send_response([
                    'ok' => false,
                    'error' => 'bad_request',
                    'message' => 'document id or uuid required',
                    'data' => null,
                    'meta' => [
                        'method' => 'PATCH',
                        'route' => 'documents/{id_or_uuid}',
                        'source' => 'clinical_documents_pdo',
                    ],
                ], 400);
                return;
            }

            $bodyResult = clinical_read_json_body();
            if (($bodyResult['ok'] ?? false) !== true) {
                clinical_send_response([
                    'ok' => false,
                    'error' => 'bad_request',
                    'message' => (string)($bodyResult['error'] ?? 'invalid body'),
                    'data' => null,
                    'meta' => [
                        'method' => 'PATCH',
                        'route' => 'documents/{id_or_uuid}',
                        'source' => 'clinical_documents_pdo',
                    ],
                ], 400);
                return;
            }
            $body = is_array($bodyResult['data'] ?? null) ? (array)$bodyResult['data'] : [];
            $renderedTextRaw = (string)($body['rendered_text'] ?? '');
            $renderedText = clinical_sanitize_rendered_text_html($renderedTextRaw);

            try {
                $pdo = clinical_documents_pdo();
                $document = clinical_documents_get_by_token_fetch($pdo, $documentToken);
                if (!is_array($document)) {
                    clinical_send_response([
                        'ok' => false,
                        'error' => 'not_found',
                        'message' => 'Documento no encontrado',
                        'data' => null,
                        'meta' => [
                            'method' => 'PATCH',
                            'route' => 'documents/{id_or_uuid}',
                            'source' => 'clinical_documents_pdo',
                        ],
                    ], 404);
                    return;
                }
                $docId = (int)($document['document_db_id'] ?? 0);
                if ($docId <= 0) {
                    clinical_send_response([
                        'ok' => false,
                        'error' => 'not_found',
                        'message' => 'Documento no encontrado',
                        'data' => null,
                        'meta' => [
                            'method' => 'PATCH',
                            'route' => 'documents/{id_or_uuid}',
                            'source' => 'clinical_documents_pdo',
                        ],
                    ], 404);
                    return;
                }
                $patientId = trim((string)($document['context']['patient_id'] ?? ''));
                if ($patientId === '') {
                    clinical_send_response([
                        'ok' => false,
                        'error' => 'bad_request',
                        'message' => 'source document without patient_id',
                        'data' => null,
                        'meta' => [
                            'method' => 'PATCH',
                            'route' => 'documents/{id_or_uuid}',
                            'source' => 'clinical_documents_pdo',
                        ],
                    ], 400);
                    return;
                }
                try {
                    clinical_documents_validate_canonical_patient_id_for_write($pdo, $patientId);
                } catch (ClinicalDocumentsPatientWriteException $e) {
                    clinical_documents_send_patient_write_error($e, [
                        'method' => 'PATCH',
                        'route' => 'documents/{id_or_uuid}',
                        'source' => 'clinical_documents_pdo',
                    ]);
                    return;
                }
                $actorUserId = clinical_request_actor_user_id($body);
                if ($actorUserId === '') {
                    $actorUserId = 'viewer_editor';
                }
                $now = gmdate('Y-m-d H:i:s');
                $stmt = $pdo->prepare("
                    UPDATE clinical_documents
                    SET rendered_text = :rendered_text,
                        edited_flag = 1,
                        updated_at = :updated_at,
                        updated_by_user_id = :updated_by_user_id
                    WHERE id = :id
                ");
                $stmt->bindValue(':rendered_text', $renderedText, PDO::PARAM_STR);
                $stmt->bindValue(':updated_at', $now, PDO::PARAM_STR);
                $stmt->bindValue(':updated_by_user_id', $actorUserId, PDO::PARAM_STR);
                $stmt->bindValue(':id', $docId, PDO::PARAM_INT);
                $stmt->execute();

                $updated = clinical_documents_get_fetch($pdo, $docId);
                clinical_send_response([
                    'ok' => true,
                    'error' => null,
                    'message' => 'document updated',
                    'data' => [
                        'document' => $updated,
                    ],
                    'meta' => [
                        'method' => 'PATCH',
                        'route' => 'documents/{id_or_uuid}',
                        'source' => 'clinical_documents_pdo',
                    ],
                ], 200);
                return;
            } catch (Throwable $e) {
                $msg = trim((string)$e->getMessage());
                clinical_send_response([
                    'ok' => false,
                    'error' => 'server_error',
                    'message' => ($msg !== '') ? $msg : 'server error',
                    'data' => null,
                    'meta' => [
                        'method' => 'PATCH',
                        'route' => 'documents/{id_or_uuid}',
                        'source' => 'clinical_documents_pdo',
                    ],
                ], 500);
                return;
            }
        }

        clinical_send_response([
            'ok' => false,
            'error' => 'not_found',
            'message' => 'route not found',
            'data' => null,
            'meta' => [
                'method' => $method,
                'route' => $route,
            ],
        ], 404);
        return;
    }

    if (($segments[0] ?? '') === 'bundles') {
        if ($method === 'GET' && count($segments) === 3 && ($segments[2] ?? '') === 'documents') {
            $bundleId = trim(rawurldecode((string)$segments[1]));
            if ($bundleId === '') {
                clinical_send_response([
                    'ok' => false,
                    'error' => 'bad_request',
                    'message' => 'bundle_id requerido',
                    'data' => null,
                    'meta' => [
                        'method' => 'GET',
                        'route' => 'bundles/{bundle_id}/documents',
                        'source' => 'clinical_documents_pdo',
                    ],
                ], 400);
                return;
            }

            $patientId = trim((string)($_GET['patient_id'] ?? ''));

            try {
                $pdo = clinical_documents_pdo();
                $items = clinical_bundle_documents_fetch($pdo, $bundleId, $patientId);
            } catch (Throwable $e) {
                $msg = trim((string)$e->getMessage());
                clinical_send_response([
                    'ok' => false,
                    'error' => 'server_error',
                    'message' => ($msg !== '') ? $msg : 'server error',
                    'data' => null,
                    'meta' => [
                        'method' => 'GET',
                        'route' => 'bundles/{bundle_id}/documents',
                        'source' => 'clinical_documents_pdo',
                    ],
                ], 500);
                return;
            }

            if ($items === []) {
                clinical_send_response([
                    'ok' => false,
                    'error' => 'not_found',
                    'message' => 'bundle not found',
                    'data' => null,
                    'meta' => [
                        'method' => 'GET',
                        'route' => 'bundles/{bundle_id}/documents',
                        'source' => 'clinical_documents_pdo',
                    ],
                ], 404);
                return;
            }

            $responsePatientId = $patientId;
            $responseBundleTitle = '';
            $responseBundleNote = '';
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                if ($responsePatientId === '') {
                    $responsePatientId = trim((string)($item['links']['patient_id'] ?? ''));
                }
                if ($responseBundleTitle === '') {
                    $responseBundleTitle = trim((string)($item['media_bundle_title'] ?? ''));
                }
                if ($responseBundleNote === '') {
                    $responseBundleNote = trim((string)($item['media_bundle_note'] ?? ''));
                }
            }
            clinical_send_response([
                'ok' => true,
                'error' => null,
                'message' => 'bundle documents listed',
                'data' => [
                    'bundle_id' => $bundleId,
                    'patient_id' => $responsePatientId,
                    'bundle_title' => $responseBundleTitle,
                    'bundle_note' => $responseBundleNote,
                    'items' => $items,
                ],
                'meta' => [
                    'method' => 'GET',
                    'route' => 'bundles/{bundle_id}/documents',
                    'source' => 'clinical_documents_pdo',
                ],
            ], 200);
            return;
        }
    }

    clinical_send_response([
        'ok' => false,
        'error' => 'not_found',
        'message' => 'route not found',
        'data' => null,
        'meta' => [
            'method' => $method,
            'route' => $route,
        ],
    ], 404);
} catch (Throwable $e) {
    $meta = [
        'exception' => get_class($e),
    ];
    if (clinical_is_local_or_dev()) {
        $meta['exception_message'] = $e->getMessage();
        $meta['exception_file'] = $e->getFile();
        $meta['exception_line'] = $e->getLine();
    }

    clinical_send_response([
        'ok' => false,
        'error' => 'server_error',
        'message' => 'server error',
        'data' => null,
        'meta' => $meta,
    ], 500);
}
