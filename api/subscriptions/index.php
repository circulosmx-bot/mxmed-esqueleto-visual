<?php
declare(strict_types=1);

require_once __DIR__ . '/../_lib/db.php';
require_once __DIR__ . '/../../modules/subscriptions/repositories/CurrentSubscriptionRepository.php';
require_once __DIR__ . '/../../modules/subscriptions/services/CurrentSubscriptionReadModelService.php';

use Subscriptions\Repositories\CurrentSubscriptionRepository;
use Subscriptions\Services\CurrentSubscriptionReadModelService;

header('Content-Type: application/json; charset=UTF-8');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function subscriptionRespond(array $response, int $status = 200): void
{
    if (isset($response['meta']) && is_array($response['meta'])) {
        $response['meta'] = (object)$response['meta'];
    } elseif (!isset($response['meta']) || !is_object($response['meta'])) {
        $response['meta'] = (object)[];
    }

    http_response_code($status);
    $json = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        http_response_code(500);
        echo json_encode([
            'ok' => false,
            'error' => [
                'code' => 'subscription_readmodel_unavailable',
                'message' => 'internal error',
            ],
            'data' => null,
            'meta' => (object)subscriptionMeta('error'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return;
    }

    echo $json;
}

function subscriptionMeta(string $authMode = 'unknown', ?bool $strictAuthRequired = null): array
{
    $strictAuthRequired ??= subscriptionStrictAuthRequired();

    return [
        'contract' => 'subscription_current_readmodel_private',
        'version' => 'SUB-READ-1',
        'generated_at' => gmdate('c'),
        'auth_mode' => $authMode,
        'strict_auth_required' => $strictAuthRequired,
    ];
}

function subscriptionError(string $code, string $message, string $authMode = 'unknown'): array
{
    return [
        'ok' => false,
        'error' => [
            'code' => $code,
            'message' => $message,
        ],
        'data' => null,
        'meta' => subscriptionMeta($authMode),
    ];
}

function subscriptionRelativeSegments(): array
{
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($uri, PHP_URL_PATH);
    $path = is_string($path) ? $path : '';

    $marker = '/api/subscriptions/index.php';
    $relative = '';
    $pos = strpos($path, $marker);
    if ($pos !== false) {
        $relative = substr($path, $pos + strlen($marker));
    } elseif (strpos($path, '/api/subscriptions/') === 0) {
        $relative = substr($path, strlen('/api/subscriptions'));
    } else {
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $relative = (string)substr($path, strlen((string)$script));
    }

    $relative = trim((string)$relative, '/');
    if ($relative === '') {
        return [];
    }

    $segments = explode('/', $relative);
    if (!empty($segments) && $segments[0] === 'index.php') {
        array_shift($segments);
    }

    return array_values(array_filter($segments, static fn($segment) => $segment !== ''));
}

function subscriptionHeaders(): array
{
    $headers = function_exists('getallheaders') ? (array)getallheaders() : [];
    $normalized = [];
    foreach ($headers as $key => $value) {
        $normalized[strtolower((string)$key)] = trim((string)$value);
    }
    return $normalized;
}

function subscriptionBoolEnvFlag($value): bool
{
    $raw = strtolower(trim((string)($value ?? '')));
    return in_array($raw, ['1', 'true', 'yes', 'on'], true);
}

function subscriptionStrictAuthRequired(): bool
{
    $name = 'MXMED_SUBSCRIPTIONS_PRIVATE_AUTH_REQUIRED';
    $value = getenv($name);
    if ($value !== false && trim((string)$value) !== '') {
        return subscriptionBoolEnvFlag($value);
    }

    foreach ([$_ENV[$name] ?? null, $_SERVER[$name] ?? null] as $candidate) {
        if ($candidate !== null && trim((string)$candidate) !== '') {
            return subscriptionBoolEnvFlag($candidate);
        }
    }

    return false;
}

function subscriptionIsLocalRequest(): bool
{
    $host = trim((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
    if ($host === '') {
        return false;
    }

    $host = strtolower((string)preg_replace('/:\d+$/', '', $host));
    $host = trim($host, '[]');
    return in_array($host, ['127.0.0.1', 'localhost', '::1'], true);
}

function subscriptionResolvePrivateContext(string $entityType, string $entityId): array
{
    $strict = subscriptionStrictAuthRequired();
    $headers = subscriptionHeaders();

    $headerUserId = trim((string)($headers['x-user-id'] ?? ''));
    $sessionUserId = trim((string)(
        $_SESSION['user_id']
        ?? $_SESSION['mxmed_user_id']
        ?? $_SESSION['auth_user_id']
        ?? ''
    ));
    $userId = $sessionUserId !== '' ? $sessionUserId : $headerUserId;

    $headerDoctorId = trim((string)($headers['x-doctor-id'] ?? ''));
    $sessionDoctorId = trim((string)(
        $_SESSION['doctor_id']
        ?? $_SESSION['active_doctor_id']
        ?? $_SESSION['mxmed_doctor_id']
        ?? ''
    ));
    $scopeDoctorId = $sessionDoctorId !== '' ? $sessionDoctorId : $headerDoctorId;

    $headerEntityType = trim((string)($headers['x-entity-type'] ?? ''));
    $headerEntityId = trim((string)($headers['x-entity-id'] ?? ''));
    $sessionEntityType = trim((string)(
        $_SESSION['entity_type']
        ?? $_SESSION['active_entity_type']
        ?? ''
    ));
    $sessionEntityId = trim((string)(
        $_SESSION['entity_id']
        ?? $_SESSION['active_entity_id']
        ?? ''
    ));
    $scopeEntityType = $sessionEntityType !== '' ? $sessionEntityType : $headerEntityType;
    $scopeEntityId = $sessionEntityId !== '' ? $sessionEntityId : $headerEntityId;

    $isLocal = subscriptionIsLocalRequest();
    if ($sessionUserId !== '') {
        $authMode = 'session_scope';
    } elseif ($headerUserId !== '') {
        $authMode = 'header_scope';
    } else {
        $authMode = ($isLocal && !$strict) ? 'local_dev_open' : 'strict';
    }

    if ($strict && $userId === '') {
        return [
            'ok' => false,
            'status' => 401,
            'response' => subscriptionError('unauthorized', 'authentication required', $authMode),
        ];
    }

    if ($strict && !$isLocal && $sessionUserId === '') {
        return [
            'ok' => false,
            'status' => 401,
            'response' => subscriptionError('unauthorized', 'session authentication required', $authMode),
        ];
    }

    if (!$isLocal && $userId === '') {
        return [
            'ok' => false,
            'status' => 401,
            'response' => subscriptionError('unauthorized', 'authentication required', $authMode),
        ];
    }

    if ($strict && $userId !== '') {
        $hasDoctorScope = ($entityType === 'doctor' && $scopeDoctorId !== '');
        $hasEntityScope = ($scopeEntityType !== '' && $scopeEntityId !== '');
        if (!$hasDoctorScope && !$hasEntityScope) {
            return [
                'ok' => false,
                'status' => 403,
                'response' => subscriptionError('forbidden', 'entity scope required', $authMode),
            ];
        }
    }

    if ($entityType === 'doctor' && $scopeDoctorId !== '' && $scopeDoctorId !== $entityId) {
        return [
            'ok' => false,
            'status' => 403,
            'response' => subscriptionError('forbidden', 'doctor scope mismatch', $authMode),
        ];
    }

    if ($scopeEntityType !== '' && $scopeEntityId !== '') {
        if ($scopeEntityType !== $entityType || $scopeEntityId !== $entityId) {
            return [
                'ok' => false,
                'status' => 403,
                'response' => subscriptionError('forbidden', 'entity scope mismatch', $authMode),
            ];
        }
    }

    return [
        'ok' => true,
        'auth_mode' => $authMode,
        'actor_user_id' => $userId,
        'actor_doctor_id' => $scopeDoctorId,
        'actor_entity_type' => $scopeEntityType,
        'actor_entity_id' => $scopeEntityId,
    ];
}

function subscriptionValidEntityType(string $entityType): bool
{
    return in_array($entityType, [
        'doctor',
        'dental',
        'hospital',
        'clinic',
        'laboratory',
        'diagnostic',
        'insurer',
        'pharmaceutical',
        'service',
    ], true);
}

function subscriptionValidEntityId(string $entityId): bool
{
    if ($entityId === '' || strlen($entityId) > 64) {
        return false;
    }

    return preg_match('/^[A-Za-z0-9._:-]+$/', $entityId) === 1;
}

try {
    $method = strtoupper(trim((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')));
    $segments = subscriptionRelativeSegments();

    if (
        count($segments) !== 4
        || $segments[0] !== 'entities'
        || $segments[3] !== 'current'
    ) {
        subscriptionRespond(subscriptionError('not_found', 'route not found'), 404);
        return;
    }

    if ($method !== 'GET') {
        subscriptionRespond(subscriptionError('method_not_allowed', 'method not allowed'), 405);
        return;
    }

    $entityType = strtolower(trim((string)$segments[1]));
    $entityId = trim((string)$segments[2]);
    if (!subscriptionValidEntityType($entityType) || !subscriptionValidEntityId($entityId)) {
        subscriptionRespond(subscriptionError('invalid_request', 'invalid entity'), 422);
        return;
    }

    $context = subscriptionResolvePrivateContext($entityType, $entityId);
    if (!(bool)($context['ok'] ?? false)) {
        subscriptionRespond((array)($context['response'] ?? []), (int)($context['status'] ?? 403));
        return;
    }
    $authMode = (string)($context['auth_mode'] ?? 'unknown');

    $repository = new CurrentSubscriptionRepository(mxmed_pdo());
    $service = new CurrentSubscriptionReadModelService($repository);
    $readModel = $service->resolveForEntity($entityType, $entityId);

    subscriptionRespond([
        'ok' => true,
        'data' => $readModel,
        'meta' => subscriptionMeta($authMode),
    ], 200);
} catch (InvalidArgumentException $e) {
    subscriptionRespond(subscriptionError('invalid_request', 'invalid request'), 422);
} catch (Throwable $e) {
    subscriptionRespond(subscriptionError('subscription_readmodel_unavailable', 'internal error'), 500);
}
