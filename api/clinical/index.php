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

set_error_handler(static function ($severity, $message, $file, $line): void {
    throw new ErrorException((string)$message, 0, (int)$severity, (string)$file, (int)$line);
});

try {
    $method = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET');
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

        $include = clinical_parse_include_csv((string)($_GET['include'] ?? ''));
        $includeClinical = in_array('clinical', $include, true);

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
        if ($includeClinical) {
            try {
                $pdo = clinical_documents_pdo();
                $rows = clinical_timeline_documents_fetch($pdo, $patientId, $limit, $cursorDt, $cursorUuid);
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

            foreach ($rows as $row) {
                $eventDatetime = (string)($row['event_datetime'] ?? '');
                $documentUuid = (string)($row['document_uuid'] ?? '');
                $items[] = [
                    'item_type' => 'document',
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

            if (count($rows) === $limit && $rows !== []) {
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
                'scaffold' => true,
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
            $id = (int)$segments[1];
            if ($id <= 0) {
                clinical_send_response([
                    'ok' => false,
                    'error' => 'bad_request',
                    'message' => 'document id must be a positive integer',
                    'data' => null,
                    'meta' => [
                        'method' => 'GET',
                        'route' => 'documents/{id}',
                        'source' => 'clinical_documents_pdo',
                    ],
                ], 400);
                return;
            }

            try {
                $pdo = clinical_documents_pdo();
                $document = clinical_documents_get_fetch($pdo, $id);
            } catch (Throwable $e) {
                $msg = trim($e->getMessage());
                clinical_send_response([
                    'ok' => false,
                    'error' => 'server_error',
                    'message' => ($msg !== '') ? $msg : 'server error',
                    'data' => null,
                    'meta' => [
                        'method' => 'GET',
                        'route' => 'documents/{id}',
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
                        'route' => 'documents/{id}',
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
                    'route' => 'documents/{id}',
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
