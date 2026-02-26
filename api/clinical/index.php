<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

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
    header('Access-Control-Allow-Headers: Content-Type, Accept, Authorization, X-Requested-With');
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

function clinical_documents_list_fetch(PDO $pdo, string $patientId, string $documentType, string $hospitalStayId, int $limit): array
{
    $sql = "
        SELECT
            id,
            title,
            document_type,
            summary,
            event_datetime,
            printable,
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

function clinical_encounter_documents_direct_fetch(PDO $pdo, string $patientId, int $encounterId, string $orderDir = 'ASC'): array
{
    if ($encounterId <= 0 || $patientId === '') {
        return [];
    }
    $dir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';
    $stmt = $pdo->prepare("
        SELECT document_uuid, document_type, title, event_datetime, summary, patient_id, appointment_id, hospital_stay_id
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
        SELECT document_uuid, document_type, title, event_datetime, summary, patient_id, appointment_id, hospital_stay_id
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

function clinical_documents_save_passthrough(PDO $pdo, array $args): array
{
    require_once __DIR__ . '/../_lib/clinical_documents.php';

    mxmed_ensure_clinical_docs_schema($pdo);

    $doc = mxmed_build_clinical_document($args);

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
        $stmt = $pdo->prepare("
            INSERT INTO clinical_documents (
                document_uuid, document_type, title, version, status,
                patient_id, encounter_id, hospital_stay_id, care_setting, service,
                payload_json, rendered_text, summary, edited_flag,
                event_datetime, widget_group, printable,
                created_at, updated_at, generated_at, signed_at,
                created_by_user_id, updated_by_user_id
            ) VALUES (
                :uuid, :type, :title, :version, :status,
                :patient_id, :encounter_id, :hospital_stay_id, :care_setting, :service,
                :payload_json, :rendered_text, :summary, :edited_flag,
                :event_datetime, :widget_group, :printable,
                :created_at, :updated_at, :generated_at, :signed_at,
                :created_by_user_id, :updated_by_user_id
            )
        ");

        $stmt->execute([
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
        ]);

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

function clinical_timeline_documents_fetch(PDO $pdo, string $patientId, int $limit, ?string $cursorDt, ?string $cursorUuid): array
{
    $baseSelect = "
        SELECT
            document_uuid,
            document_type,
            summary,
            event_datetime,
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

    $sqlWithAppointment = $baseSelect . " AND appointment_id IS NULL " . $cursorClause . $orderLimit;
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
            status VARCHAR(16) NOT NULL DEFAULT 'completed',
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
}

function clinical_encounter_get_by_id(PDO $pdo, int $encounterId): ?array
{
    $stmt = $pdo->prepare("
        SELECT
            encounter_id, patient_id, appointment_id, encounter_dt,
            encounter_type, status, created_at, updated_at
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
            encounter_type, status, created_at, updated_at
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
    string $status
): array {
    $stmt = $pdo->prepare("
        INSERT INTO clinical_encounters
            (patient_id, appointment_id, encounter_dt, encounter_type, status, created_at, updated_at)
        VALUES
            (:patient_id, :appointment_id, :encounter_dt, :encounter_type, :status, NOW(), NOW())
    ");
    $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_STR);
    if ($appointmentId === null || $appointmentId === '') {
        $stmt->bindValue(':appointment_id', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':appointment_id', $appointmentId, PDO::PARAM_STR);
    }
    $stmt->bindValue(':encounter_dt', $encounterDt, PDO::PARAM_STR);
    $stmt->bindValue(':encounter_type', $encounterType, PDO::PARAM_STR);
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
            encounter_type, status, created_at, updated_at
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
                    $docItems = [];
                    foreach ($encounterDocs as $docRow) {
                        $docItems[] = [
                            'document_uuid' => (string)($docRow['document_uuid'] ?? ''),
                            'document_type' => (string)($docRow['document_type'] ?? ''),
                            'event_datetime' => (string)($docRow['event_datetime'] ?? ''),
                            'summary' => (string)($docRow['summary'] ?? ''),
                        ];
                    }
                    $flags = clinical_timeline_encounter_flags($docItems);

                    $encounterItems[] = [
                        'item_type' => 'encounter',
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
                        'clinical' => [
                            'has_vitals' => $flags['has_vitals'],
                            'has_note' => $flags['has_note'],
                            'has_prescription' => $flags['has_prescription'],
                            'has_orders' => $flags['has_orders'],
                            'has_results' => $flags['has_results'],
                            'documents' => $docItems,
                        ],
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
                    $eventDatetime = (string)($row['event_datetime'] ?? '');
                    $documentUuid = (string)($row['document_uuid'] ?? '');
                    $documentItems[] = [
                        'item_type' => 'document',
                        'ref' => ($documentUuid !== '' ? ('doc:' . $documentUuid) : null),
                        'encounter_key' => clinical_timeline_encounter_key_from_datetime($eventDatetime),
                        'event_datetime' => $eventDatetime,
                        'sort_datetime' => $eventDatetime,
                        'sort_key' => 'doc:' . $documentUuid,
                        'links' => [
                            'patient_id' => $patientId,
                            'appointment_id' => null,
                            'document_uuid' => $documentUuid,
                            'encounter_id' => null,
                            'hospital_stay_id' => $row['hospital_stay_id'] ?? null,
                        ],
                        'clinical_document' => [
                            'document_uuid' => $documentUuid,
                            'document_type' => (string)($row['document_type'] ?? ''),
                            'summary' => (string)($row['summary'] ?? ''),
                        ],
                    ];
                }
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

            clinical_send_response([
                'ok' => true,
                'error' => null,
                'message' => 'patient_id passthrough accepted',
                'data' => [
                    'patient_id' => $patientId,
                    'confidence' => 1.0,
                    'strategy' => 'passthrough',
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

            $legacyPatientId = trim((string)($legacy['legacy_patient_id'] ?? ''));
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
                clinical_send_response([
                    'ok' => true,
                    'error' => null,
                    'message' => 'legacy mapped via identity bridge',
                    'data' => [
                        'patient_id' => (string)$row['canonical_patient_id'],
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
                    'legacy_patient_id' => $legacyPatientId,
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
        $status = trim((string)($payload['status'] ?? 'completed'));

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
            $status = 'completed';
        }
        if (mb_strlen($status) > 16) {
            $status = mb_substr($status, 0, 16);
        }

        try {
            $created = clinical_encounters_create(
                $pdo,
                $patientId,
                ($appointmentId !== '' ? $appointmentId : null),
                $encounterDt,
                $encounterType,
                $status
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
            'data' => $created,
            'meta' => ['method' => 'POST', 'route' => 'patients/{patient_id}/encounters'],
        ], 201);
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
        if (count($segments) === 3 && ($segments[2] ?? '') === 'documents' && $method === 'POST') {
            $encounterKey = urldecode(trim((string)$segments[1]));
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

            clinical_send_response([
                'ok' => true,
                'error' => null,
                'message' => 'encounter retrieved',
                'data' => [
                    'encounter_key' => $responseEncounterKey,
                    'appointment_id' => ($appointmentId !== '' ? $appointmentId : null),
                    'patient_id' => $patientId,
                    'event_datetime' => $eventDatetime,
                    'documents' => $documents,
                    'prescriptions' => [],
                    'orders' => [],
                    'results' => [],
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

    if (($segments[0] ?? '') === 'documents') {
        if ($method === 'POST' && count($segments) === 1) {
            $meta = [
                'method' => 'POST',
                'route' => 'clinical_gateway',
                'resource' => 'documents',
                'fallback_used' => false,
                'source' => 'clinical_documents_pdo',
            ];

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

            try {
                $pdo = clinical_documents_pdo();
                $document = clinical_documents_save_passthrough($pdo, (array)$bodyResult['data']);
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
                    'document_id' => $document['document_id'] ?? null,
                    'document' => $document,
                ],
                'meta' => $meta,
            ], 201);
            return;
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
