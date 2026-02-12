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
                'build' => 'dev',
            ],
            'meta' => [
                'route' => 'version',
                'method' => 'GET',
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
    clinical_send_response([
        'ok' => false,
        'error' => 'server_error',
        'message' => 'server error',
        'data' => null,
        'meta' => [
            'exception' => get_class($e),
        ],
    ], 500);
}
