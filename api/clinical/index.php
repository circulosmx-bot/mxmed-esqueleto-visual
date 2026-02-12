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
        if (!is_string($response['error']) || $response['error'] === '') {
            $response['error'] = 'server_error';
        }
        if (!is_string($response['message']) || $response['message'] === '') {
            $response['message'] = ($response['error'] === 'not_found') ? 'route not found' : 'server error';
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

set_error_handler(static function ($severity, $message, $file, $line): void {
    throw new ErrorException((string)$message, 0, (int)$severity, (string)$file, (int)$line);
});

try {
    $method = (string)($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $segments = clinical_route_segments();
    $route = implode('/', $segments);

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
        $bodyResult = clinical_read_json_body();
        if ($bodyResult['ok'] !== true) {
            clinical_send_response([
                'ok' => false,
                'error' => 'bad_request',
                'message' => (string)$bodyResult['error'],
                'data' => null,
                'meta' => [
                    'method' => 'POST',
                    'route' => 'patient-id/resolve',
                ],
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
                    'meta' => [
                        'method' => 'POST',
                        'route' => 'patient-id/resolve',
                    ],
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
                'meta' => [
                    'method' => 'POST',
                    'route' => 'patient-id/resolve',
                ],
            ], 200);
            return;
        }

        if (array_key_exists('legacy', $body)) {
            clinical_send_response([
                'ok' => false,
                'error' => 'not_ready',
                'message' => 'legacy identity resolution is not available in v1 yet',
                'data' => [
                    'required_next' => 'identity_bridge_v2',
                    'received' => true,
                ],
                'meta' => [
                    'method' => 'POST',
                    'route' => 'patient-id/resolve',
                ],
            ], 501);
            return;
        }

        clinical_send_response([
            'ok' => false,
            'error' => 'invalid_params',
            'message' => 'either patient_id or legacy payload is required',
            'data' => null,
            'meta' => [
                'method' => 'POST',
                'route' => 'patient-id/resolve',
            ],
        ], 400);
        return;
    }

    if ($method === 'GET' && ($segments[0] ?? '') === 'documents') {
        if (count($segments) === 1) {
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

        if (count($segments) === 2) {
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
