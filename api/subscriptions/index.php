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

function subscriptionContextMeta(string $source = 'none'): array
{
    return [
        'contract' => 'subscription_active_entity_context_private',
        'version' => 'active-entity-context-v1',
        'generated_at' => gmdate('c'),
        'source' => $source,
        'strict_auth_required' => subscriptionStrictAuthRequired(),
    ];
}

function subscriptionContextError(string $code, string $message, string $source = 'none'): array
{
    return [
        'ok' => false,
        'error' => [
            'code' => $code,
            'message' => $message,
        ],
        'data' => null,
        'meta' => subscriptionContextMeta($source),
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

function subscriptionSessionValue(array $keys): string
{
    foreach ($keys as $key) {
        $value = trim((string)($_SESSION[$key] ?? ''));
        if ($value !== '') {
            return $value;
        }
    }
    return '';
}

function subscriptionSessionHasPermission(string $permission): bool
{
    $permission = strtolower(trim($permission));
    if ($permission === '') {
        return false;
    }

    $sources = [
        $_SESSION['permissions'] ?? null,
        $_SESSION['mxmed_permissions'] ?? null,
        $_SESSION['scopes'] ?? null,
        $_SESSION['operator_permissions'] ?? null,
    ];
    $aliases = [
        $permission,
        str_replace('.', ':', $permission),
        str_replace('.', '_', $permission),
        'subscriptions',
    ];

    foreach (array_merge($aliases, ['can_read_subscriptions']) as $sessionKey) {
        $sessionValue = $_SESSION[$sessionKey] ?? null;
        if ($sessionValue === true || $sessionValue === 1 || $sessionValue === '1') {
            return true;
        }
    }

    foreach ($sources as $source) {
        if (is_string($source)) {
            $items = preg_split('/[\s,;|]+/', strtolower($source)) ?: [];
        } elseif (is_array($source)) {
            $items = [];
            foreach ($source as $key => $value) {
                if (is_string($key) && ($value === true || $value === 1 || $value === '1')) {
                    $items[] = strtolower(trim($key));
                }
                if (is_string($value) && trim($value) !== '') {
                    $items[] = strtolower(trim($value));
                }
            }
        } else {
            $items = [];
        }

        foreach ($items as $item) {
            $item = strtolower(trim((string)$item));
            if ($item !== '' && in_array($item, $aliases, true)) {
                return true;
            }
        }
    }

    return false;
}

function subscriptionNormalizeActorRole(string $actorRole, string $operatorId = ''): string
{
    $normalized = strtolower(trim($actorRole));

    if (in_array($normalized, ['operator', 'operador', 'assistant', 'asistente'], true) || $operatorId !== '') {
        return 'operator';
    }

    if (in_array($normalized, ['doctor', 'medico', 'principal', 'owner'], true)) {
        return 'doctor';
    }

    return 'doctor';
}

function subscriptionJsonId(string $value)
{
    return ctype_digit($value) ? (int)$value : $value;
}

function subscriptionResolveActiveEntityContext(): array
{
    $strict = subscriptionStrictAuthRequired();
    $isLocal = subscriptionIsLocalRequest();
    $headers = subscriptionHeaders();
    $allowHeaderScope = $isLocal;

    $sessionUserId = subscriptionSessionValue(['user_id', 'mxmed_user_id', 'auth_user_id']);
    $headerUserId = $allowHeaderScope ? trim((string)($headers['x-user-id'] ?? '')) : '';
    $userId = $sessionUserId !== '' ? $sessionUserId : $headerUserId;

    $sessionDoctorId = subscriptionSessionValue(['doctor_id', 'active_doctor_id', 'mxmed_doctor_id']);
    $headerDoctorId = $allowHeaderScope ? trim((string)($headers['x-doctor-id'] ?? '')) : '';

    $sessionEntityType = strtolower(subscriptionSessionValue(['entity_type', 'active_entity_type']));
    $sessionEntityId = subscriptionSessionValue(['entity_id', 'active_entity_id']);
    $headerEntityType = $allowHeaderScope ? strtolower(trim((string)($headers['x-entity-type'] ?? ''))) : '';
    $headerEntityId = $allowHeaderScope ? trim((string)($headers['x-entity-id'] ?? '')) : '';

    $doctorId = $sessionDoctorId !== '' ? $sessionDoctorId : $headerDoctorId;
    if ($doctorId === '' && $sessionEntityType === 'doctor') {
        $doctorId = $sessionEntityId;
    }
    if ($doctorId === '' && $headerEntityType === 'doctor') {
        $doctorId = $headerEntityId;
    }

    $source = 'none';
    if ($sessionUserId !== '') {
        $source = 'session_scope';
    } elseif ($allowHeaderScope && $headerUserId !== '') {
        $source = 'header_scope';
    }

    if ($userId === '') {
        return [
            'ok' => false,
            'status' => 401,
            'response' => subscriptionContextError('unauthorized', 'authentication required', $source),
        ];
    }

    if ($strict && !$isLocal && $sessionUserId === '') {
        return [
            'ok' => false,
            'status' => 401,
            'response' => subscriptionContextError('unauthorized', 'session authentication required', $source),
        ];
    }

    if ($source === 'header_scope' && !$isLocal) {
        return [
            'ok' => false,
            'status' => 401,
            'response' => subscriptionContextError('unauthorized', 'session authentication required', $source),
        ];
    }

    if ($doctorId === '') {
        return [
            'ok' => false,
            'status' => 403,
            'response' => subscriptionContextError('forbidden', 'doctor scope required', $source),
        ];
    }

    if ($sessionEntityType !== '' && $sessionEntityType !== 'doctor') {
        return [
            'ok' => false,
            'status' => 403,
            'response' => subscriptionContextError('forbidden', 'entity scope mismatch', $source),
        ];
    }

    if ($sessionEntityType !== '' && $sessionEntityId !== '' && $sessionEntityId !== $doctorId) {
        return [
            'ok' => false,
            'status' => 403,
            'response' => subscriptionContextError('forbidden', 'entity scope mismatch', $source),
        ];
    }

    if ($headerEntityType !== '' && $headerEntityType !== 'doctor') {
        return [
            'ok' => false,
            'status' => 403,
            'response' => subscriptionContextError('forbidden', 'entity scope mismatch', $source),
        ];
    }

    if ($headerEntityType !== '' && $headerEntityId !== '' && $headerEntityId !== $doctorId) {
        return [
            'ok' => false,
            'status' => 403,
            'response' => subscriptionContextError('forbidden', 'entity scope mismatch', $source),
        ];
    }

    if (!subscriptionValidEntityId($doctorId)) {
        return [
            'ok' => false,
            'status' => 422,
            'response' => subscriptionContextError('invalid_request', 'invalid context', $source),
        ];
    }

    $operatorId = subscriptionSessionValue(['operator_id']);
    $actorRole = subscriptionNormalizeActorRole(
        subscriptionSessionValue(['actor_role', 'user_role', 'role', 'mxmed_user_role']),
        $operatorId
    );
    $subscriptionsRead = true;

    if ($actorRole === 'operator') {
        $subscriptionsRead = subscriptionSessionHasPermission('subscriptions.read');
        if (!$subscriptionsRead) {
            return [
                'ok' => false,
                'status' => 403,
                'response' => subscriptionContextError('forbidden', 'operator subscription scope required', $source),
            ];
        }
    }

    return [
        'ok' => true,
        'status' => 200,
        'response' => [
            'ok' => true,
            'data' => [
                'user_id' => subscriptionJsonId($userId),
                'doctor_id' => subscriptionJsonId($doctorId),
                'entity_type' => 'doctor',
                'entity_id' => $doctorId,
                'actor_role' => $actorRole,
                'operator_id' => $operatorId !== '' ? subscriptionJsonId($operatorId) : null,
                'permissions' => [
                    'subscriptions_read' => $subscriptionsRead,
                ],
                'can_read_subscriptions' => $subscriptionsRead,
            ],
            'meta' => subscriptionContextMeta($source),
        ],
    ];
}

function subscriptionResolvePrivateContext(string $entityType, string $entityId): array
{
    $strict = subscriptionStrictAuthRequired();
    $isLocal = subscriptionIsLocalRequest();
    $headers = subscriptionHeaders();

    $allowHeaderScope = $isLocal;
    $headerUserId = $allowHeaderScope ? trim((string)($headers['x-user-id'] ?? '')) : '';
    $sessionUserId = subscriptionSessionValue(['user_id', 'mxmed_user_id', 'auth_user_id']);
    $userId = $sessionUserId !== '' ? $sessionUserId : $headerUserId;

    $headerDoctorId = $allowHeaderScope ? trim((string)($headers['x-doctor-id'] ?? '')) : '';
    $sessionDoctorId = subscriptionSessionValue(['doctor_id', 'active_doctor_id', 'mxmed_doctor_id']);
    $scopeDoctorId = $sessionDoctorId !== '' ? $sessionDoctorId : $headerDoctorId;

    $headerEntityType = $allowHeaderScope ? trim((string)($headers['x-entity-type'] ?? '')) : '';
    $headerEntityId = $allowHeaderScope ? trim((string)($headers['x-entity-id'] ?? '')) : '';
    $sessionEntityType = subscriptionSessionValue(['entity_type', 'active_entity_type']);
    $sessionEntityId = subscriptionSessionValue(['entity_id', 'active_entity_id']);
    $scopeEntityType = $sessionEntityType !== '' ? $sessionEntityType : $headerEntityType;
    $scopeEntityId = $sessionEntityId !== '' ? $sessionEntityId : $headerEntityId;

    $actorRole = strtolower(subscriptionSessionValue(['actor_role', 'user_role', 'role', 'mxmed_user_role']));
    $operatorId = subscriptionSessionValue(['operator_id']);
    $isOperator = ($actorRole === 'operator' || $operatorId !== '');

    if ($sessionUserId !== '') {
        $authMode = 'session_scope';
    } elseif ($allowHeaderScope && $headerUserId !== '') {
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

    if ($strict && $entityType !== 'doctor') {
        return [
            'ok' => false,
            'status' => 403,
            'response' => subscriptionError('forbidden', 'entity ownership not available', $authMode),
        ];
    }

    if ($strict && $userId !== '') {
        $hasDoctorScope = ($scopeDoctorId !== '');
        $hasEntityScope = ($scopeEntityType !== '' && $scopeEntityId !== '');
        if (!$hasDoctorScope && !$hasEntityScope) {
            return [
                'ok' => false,
                'status' => 403,
                'response' => subscriptionError('forbidden', 'entity scope required', $authMode),
            ];
        }
    }

    if ($strict && $isOperator && !subscriptionSessionHasPermission('subscriptions.read')) {
        return [
            'ok' => false,
            'status' => 403,
            'response' => subscriptionError('forbidden', 'operator subscription scope required', $authMode),
        ];
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

    if (count($segments) === 2 && $segments[0] === 'context' && $segments[1] === 'current') {
        if ($method !== 'GET') {
            subscriptionRespond(subscriptionContextError('method_not_allowed', 'method not allowed'), 405);
            return;
        }

        $context = subscriptionResolveActiveEntityContext();
        subscriptionRespond((array)($context['response'] ?? []), (int)($context['status'] ?? 500));
        return;
    }

    if (!empty($segments) && $segments[0] === 'context') {
        subscriptionRespond(subscriptionContextError('not_found', 'route not found'), 404);
        return;
    }

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
