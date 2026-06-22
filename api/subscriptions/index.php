<?php
declare(strict_types=1);

require_once __DIR__ . '/../_lib/db.php';
require_once __DIR__ . '/../../modules/subscriptions/repositories/CurrentSubscriptionRepository.php';
require_once __DIR__ . '/../../modules/subscriptions/repositories/SubscriptionContractAcceptanceRepository.php';
require_once __DIR__ . '/../../modules/subscriptions/repositories/SubscriptionWriteIdempotencyRepository.php';
require_once __DIR__ . '/../../modules/subscriptions/services/CurrentSubscriptionReadModelService.php';
require_once __DIR__ . '/../../modules/subscriptions/services/CreateSubscriptionWithAcceptanceService.php';
require_once __DIR__ . '/../../modules/subscriptions/services/SubscriptionEntityWriteLockService.php';
require_once __DIR__ . '/../../modules/subscriptions/services/SubscriptionWriteIdempotencyService.php';

use Subscriptions\Repositories\CurrentSubscriptionRepository;
use Subscriptions\Repositories\SubscriptionContractAcceptanceRepository;
use Subscriptions\Repositories\SubscriptionWriteIdempotencyRepository;
use Subscriptions\Services\CreateSubscriptionWithAcceptanceService;
use Subscriptions\Services\CurrentSubscriptionReadModelService;
use Subscriptions\Services\SubscriptionEntityWriteLockService;
use Subscriptions\Services\SubscriptionWriteIdempotencyService;
use Subscriptions\Services\SubscriptionWriteException;

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

function subscriptionWriteMeta(string $authMode = 'unknown'): array
{
    return [
        'contract' => 'subscription_contract_acceptance_write_private',
        'version' => 'SUB-WRITE-1',
        'generated_at' => gmdate('c'),
        'auth_mode' => $authMode,
        'strict_auth_required' => true,
        'source' => 'subscriptions_write_v1',
    ];
}

function subscriptionWriteError(string $code, string $message, string $authMode = 'unknown'): array
{
    return [
        'ok' => false,
        'error' => [
            'code' => $code,
            'message' => $message,
        ],
        'data' => null,
        'meta' => subscriptionWriteMeta($authMode),
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

function subscriptionEnvValue(string $name): string
{
    $value = getenv($name);
    if ($value !== false && trim((string)$value) !== '') {
        return trim((string)$value);
    }

    foreach ([$_ENV[$name] ?? null, $_SERVER[$name] ?? null] as $candidate) {
        if ($candidate !== null && trim((string)$candidate) !== '') {
            return trim((string)$candidate);
        }
    }

    return '';
}

function subscriptionStrictAuthRequired(): bool
{
    $name = 'MXMED_SUBSCRIPTIONS_PRIVATE_AUTH_REQUIRED';
    return subscriptionBoolEnvFlag(subscriptionEnvValue($name));
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

function subscriptionDevSessionFixtureMeta(): array
{
    return [
        'contract' => 'subscription_dev_session_fixture_private',
        'version' => 'SUB-DEV-SESSION-FIXTURE-1',
        'generated_at' => gmdate('c'),
        'source' => 'subscriptions_dev_session_fixture_v1',
        'dev_only' => true,
    ];
}

function subscriptionDevSessionFixtureError(string $code, string $message): array
{
    return [
        'ok' => false,
        'error' => [
            'code' => $code,
            'message' => $message,
        ],
        'data' => null,
        'meta' => subscriptionDevSessionFixtureMeta(),
    ];
}

function subscriptionDevSessionFixtureEnabled(): bool
{
    return subscriptionEnvValue('MXMED_SUBSCRIPTIONS_DEV_SESSION_FIXTURE_ENABLED') === '1';
}

function subscriptionProductionEnvironmentDetected(): bool
{
    foreach (['APP_ENV', 'MXMED_ENV', 'ENVIRONMENT'] as $name) {
        $value = strtolower(subscriptionEnvValue($name));
        if (in_array($value, ['prod', 'production'], true)) {
            return true;
        }
    }

    return false;
}

function subscriptionApplyDevDoctorSessionFixture(string $doctorId, string $userId): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }

    unset(
        $_SESSION['operator_id'],
        $_SESSION['operator_permissions'],
        $_SESSION['permissions'],
        $_SESSION['mxmed_permissions'],
        $_SESSION['scopes'],
        $_SESSION['user_role'],
        $_SESSION['role'],
        $_SESSION['mxmed_user_role']
    );

    $_SESSION['user_id'] = $userId;
    $_SESSION['doctor_id'] = $doctorId;
    $_SESSION['entity_type'] = 'doctor';
    $_SESSION['entity_id'] = $doctorId;
    $_SESSION['actor_role'] = 'doctor';
    $_SESSION['subscriptions_dev_session_fixture'] = '1';
}

function subscriptionCreateDevSessionFixture(): array
{
    subscriptionApplyDevDoctorSessionFixture('1', '1');

    return [
        'ok' => true,
        'data' => [
            'auth_mode' => 'session_scope',
            'entity_type' => 'doctor',
            'entity_id' => '1',
            'doctor_id' => '1',
            'fixture' => 'subscriptions_dev_session_fixture',
        ],
        'meta' => subscriptionDevSessionFixtureMeta(),
    ];
}

function subscriptionDoctorFixtureExists(string $doctorId): bool
{
    $stmt = mxmed_pdo()->prepare(
        'SELECT COUNT(*) AS total
         FROM profiles_doctors
         WHERE doctor_id = :doctor_id'
    );
    $stmt->execute(['doctor_id' => $doctorId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return (int)($row['total'] ?? 0) > 0;
}

function subscriptionDoctorHasActiveSubscription(string $doctorId): bool
{
    $stmt = mxmed_pdo()->prepare(
        'SELECT COUNT(*) AS total
         FROM profile_subscriptions
         WHERE entity_type = \'doctor\'
           AND entity_id = :doctor_id
           AND deleted_at IS NULL
           AND status IN (\'active\', \'expiring_soon\', \'grace_period\')
           AND (starts_at IS NULL OR starts_at <= UTC_TIMESTAMP())
           AND (expires_at IS NULL OR expires_at >= UTC_TIMESTAMP())'
    );
    $stmt->execute(['doctor_id' => $doctorId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return (int)($row['total'] ?? 0) > 0;
}

function subscriptionCreateAlternateDoctorSessionFixture(): array
{
    $doctorId = '2';
    $userId = '2';
    if (!subscriptionDoctorFixtureExists($doctorId)) {
        return subscriptionDevSessionFixtureError('fixture_doctor_not_found', 'alternate doctor fixture not found');
    }

    $hasActiveSubscription = subscriptionDoctorHasActiveSubscription($doctorId);
    if ($hasActiveSubscription) {
        return subscriptionDevSessionFixtureError('fixture_doctor_has_active_subscription', 'alternate doctor fixture has active subscription');
    }

    subscriptionApplyDevDoctorSessionFixture($doctorId, $userId);

    return [
        'ok' => true,
        'data' => [
            'auth_mode' => 'session_scope',
            'entity_type' => 'doctor',
            'entity_id' => $doctorId,
            'doctor_id' => $doctorId,
            'operator_id' => null,
            'fixture' => 'alternate_doctor',
            'has_active_subscription' => false,
        ],
        'meta' => subscriptionDevSessionFixtureMeta(),
    ];
}

function subscriptionCreateConcurrencyDoctorSessionFixture(): array
{
    $doctorId = '3';
    $userId = '3';
    if (!subscriptionDoctorFixtureExists($doctorId)) {
        return subscriptionDevSessionFixtureError('fixture_doctor_not_found', 'concurrency doctor fixture not found');
    }

    $hasActiveSubscription = subscriptionDoctorHasActiveSubscription($doctorId);
    if ($hasActiveSubscription) {
        return subscriptionDevSessionFixtureError('fixture_doctor_has_active_subscription', 'concurrency doctor fixture has active subscription');
    }

    subscriptionApplyDevDoctorSessionFixture($doctorId, $userId);

    return [
        'ok' => true,
        'data' => [
            'auth_mode' => 'session_scope',
            'entity_type' => 'doctor',
            'entity_id' => $doctorId,
            'doctor_id' => $doctorId,
            'operator_id' => null,
            'fixture' => 'concurrency_doctor',
            'has_active_subscription' => false,
        ],
        'meta' => subscriptionDevSessionFixtureMeta(),
    ];
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

function subscriptionReadJsonPayload(): array
{
    $contentType = strtolower(trim((string)($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '')));
    if ($contentType !== '' && strpos($contentType, 'application/json') === false) {
        return [
            'ok' => false,
            'status' => 400,
            'response' => subscriptionWriteError('invalid_payload', 'content-type must be application/json', 'unknown'),
        ];
    }

    $raw = file_get_contents('php://input');
    if (!is_string($raw) || trim($raw) === '') {
        return [
            'ok' => false,
            'status' => 400,
            'response' => subscriptionWriteError('invalid_payload', 'request body is required', 'unknown'),
        ];
    }

    $decoded = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded) || $decoded === [] || array_values($decoded) === $decoded) {
        return [
            'ok' => false,
            'status' => 400,
            'response' => subscriptionWriteError('invalid_payload', 'invalid json payload', 'unknown'),
        ];
    }

    return [
        'ok' => true,
        'payload' => $decoded,
    ];
}

function subscriptionForbiddenPayloadFields(array $payload, array $forbidden, string $prefix = ''): array
{
    $found = [];
    foreach ($payload as $key => $value) {
        $key = (string)$key;
        $path = $prefix === '' ? $key : $prefix . '.' . $key;
        if (in_array($key, $forbidden, true)) {
            $found[] = $path;
        }
        if (is_array($value)) {
            $found = array_merge($found, subscriptionForbiddenPayloadFields($value, $forbidden, $path));
        }
    }

    return array_values(array_unique($found));
}

function subscriptionRequestIpAddress(): ?string
{
    $remoteAddr = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    if ($remoteAddr === '' || strlen($remoteAddr) > 45) {
        return null;
    }

    return $remoteAddr;
}

function subscriptionRequestUserAgent(): ?string
{
    $userAgent = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if ($userAgent === '') {
        return null;
    }

    return strlen($userAgent) > 512 ? substr($userAgent, 0, 512) : $userAgent;
}

function subscriptionResolveWriteContext(string $entityType, string $entityId): array
{
    $headers = subscriptionHeaders();
    $isLocal = subscriptionIsLocalRequest();
    $sessionUserId = subscriptionSessionValue(['user_id', 'mxmed_user_id', 'auth_user_id']);
    $hasHeaderIdentity = (
        trim((string)($headers['x-user-id'] ?? '')) !== ''
        || trim((string)($headers['x-doctor-id'] ?? '')) !== ''
        || trim((string)($headers['x-entity-type'] ?? '')) !== ''
        || trim((string)($headers['x-entity-id'] ?? '')) !== ''
    );

    if (!$isLocal) {
        return [
            'ok' => false,
            'status' => 403,
            'response' => subscriptionWriteError('forbidden', 'subscription writes are limited to local/dev', 'strict'),
        ];
    }

    if ($hasHeaderIdentity) {
        return [
            'ok' => false,
            'status' => 403,
            'response' => subscriptionWriteError('forbidden', 'header scope does not authorize writes', 'header_scope'),
        ];
    }

    if ($sessionUserId === '') {
        return [
            'ok' => false,
            'status' => 403,
            'response' => subscriptionWriteError('forbidden', 'local_dev_open does not authorize writes', 'local_dev_open'),
        ];
    }

    if (!ctype_digit($sessionUserId)) {
        return [
            'ok' => false,
            'status' => 401,
            'response' => subscriptionWriteError('unauthorized', 'valid session authentication required', 'session_scope'),
        ];
    }

    if ($entityType !== 'doctor') {
        return [
            'ok' => false,
            'status' => 422,
            'response' => subscriptionWriteError('invalid_entity', 'only doctor subscriptions are supported', 'session_scope'),
        ];
    }

    $sessionDoctorId = subscriptionSessionValue(['doctor_id', 'active_doctor_id', 'mxmed_doctor_id']);
    $sessionEntityType = strtolower(subscriptionSessionValue(['entity_type', 'active_entity_type']));
    $sessionEntityId = subscriptionSessionValue(['entity_id', 'active_entity_id']);
    if ($sessionDoctorId === '' && $sessionEntityType === 'doctor') {
        $sessionDoctorId = $sessionEntityId;
    }

    if ($sessionDoctorId === '') {
        return [
            'ok' => false,
            'status' => 403,
            'response' => subscriptionWriteError('forbidden', 'doctor scope required', 'session_scope'),
        ];
    }

    if ($sessionEntityType !== '' && $sessionEntityType !== 'doctor') {
        return [
            'ok' => false,
            'status' => 403,
            'response' => subscriptionWriteError('forbidden', 'entity scope mismatch', 'session_scope'),
        ];
    }

    if ($sessionEntityType !== '' && $sessionEntityId !== '' && $sessionEntityId !== $sessionDoctorId) {
        return [
            'ok' => false,
            'status' => 403,
            'response' => subscriptionWriteError('forbidden', 'entity scope mismatch', 'session_scope'),
        ];
    }

    if ($sessionDoctorId !== $entityId) {
        return [
            'ok' => false,
            'status' => 403,
            'response' => subscriptionWriteError('forbidden', 'doctor scope mismatch', 'session_scope'),
        ];
    }

    $operatorId = subscriptionSessionValue(['operator_id']);
    $rawActorRole = strtolower(subscriptionSessionValue(['actor_role', 'user_role', 'role', 'mxmed_user_role']));
    if (
        $operatorId !== ''
        || in_array($rawActorRole, ['operator', 'operador', 'assistant', 'asistente'], true)
    ) {
        return [
            'ok' => false,
            'status' => 403,
            'response' => subscriptionWriteError('forbidden', 'operator subscription writes are not enabled', 'session_scope'),
        ];
    }
    if (
        $rawActorRole !== ''
        && !in_array($rawActorRole, ['doctor', 'medico', 'principal', 'owner'], true)
    ) {
        return [
            'ok' => false,
            'status' => 403,
            'response' => subscriptionWriteError('forbidden', 'actor role is not enabled for subscription writes', 'session_scope'),
        ];
    }

    $profileId = subscriptionSessionValue(['profile_id', 'active_profile_id', 'mxmed_profile_id']);

    return [
        'ok' => true,
        'auth_mode' => 'session_scope',
        'actor_user_id' => $sessionUserId,
        'actor_role' => 'doctor',
        'operator_id' => null,
        'doctor_id' => $sessionDoctorId,
        'profile_id' => $profileId !== '' ? $profileId : null,
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

    if (count($segments) === 3 && $segments[0] === 'dev' && $segments[1] === 'session-fixture' && $segments[2] === 'concurrency-doctor') {
        if ($method !== 'POST') {
            subscriptionRespond(subscriptionDevSessionFixtureError('method_not_allowed', 'method not allowed'), 405);
            return;
        }

        if (!subscriptionDevSessionFixtureEnabled()) {
            subscriptionRespond(subscriptionDevSessionFixtureError('fixture_disabled', 'dev session fixture disabled'), 403);
            return;
        }

        if (!subscriptionIsLocalRequest()) {
            subscriptionRespond(subscriptionDevSessionFixtureError('local_only', 'dev session fixture is local only'), 403);
            return;
        }

        if (subscriptionProductionEnvironmentDetected()) {
            subscriptionRespond(subscriptionDevSessionFixtureError('production_blocked', 'dev session fixture is blocked in production'), 403);
            return;
        }

        $fixtureResponse = subscriptionCreateConcurrencyDoctorSessionFixture();
        $fixtureStatus = (bool)($fixtureResponse['ok'] ?? false) ? 200 : 409;
        subscriptionRespond($fixtureResponse, $fixtureStatus);
        return;
    }

    if (count($segments) === 3 && $segments[0] === 'dev' && $segments[1] === 'session-fixture' && $segments[2] === 'alternate-doctor') {
        if ($method !== 'POST') {
            subscriptionRespond(subscriptionDevSessionFixtureError('method_not_allowed', 'method not allowed'), 405);
            return;
        }

        if (!subscriptionDevSessionFixtureEnabled()) {
            subscriptionRespond(subscriptionDevSessionFixtureError('fixture_disabled', 'dev session fixture disabled'), 403);
            return;
        }

        if (!subscriptionIsLocalRequest()) {
            subscriptionRespond(subscriptionDevSessionFixtureError('local_only', 'dev session fixture is local only'), 403);
            return;
        }

        if (subscriptionProductionEnvironmentDetected()) {
            subscriptionRespond(subscriptionDevSessionFixtureError('production_blocked', 'dev session fixture is blocked in production'), 403);
            return;
        }

        $fixtureResponse = subscriptionCreateAlternateDoctorSessionFixture();
        $fixtureStatus = (bool)($fixtureResponse['ok'] ?? false) ? 200 : 409;
        subscriptionRespond($fixtureResponse, $fixtureStatus);
        return;
    }

    if (count($segments) === 2 && $segments[0] === 'dev' && $segments[1] === 'session-fixture') {
        if ($method !== 'POST') {
            subscriptionRespond(subscriptionDevSessionFixtureError('method_not_allowed', 'method not allowed'), 405);
            return;
        }

        if (!subscriptionDevSessionFixtureEnabled()) {
            subscriptionRespond(subscriptionDevSessionFixtureError('fixture_disabled', 'dev session fixture disabled'), 403);
            return;
        }

        if (!subscriptionIsLocalRequest()) {
            subscriptionRespond(subscriptionDevSessionFixtureError('local_only', 'dev session fixture is local only'), 403);
            return;
        }

        if (subscriptionProductionEnvironmentDetected()) {
            subscriptionRespond(subscriptionDevSessionFixtureError('production_blocked', 'dev session fixture is blocked in production'), 403);
            return;
        }

        subscriptionRespond(subscriptionCreateDevSessionFixture(), 200);
        return;
    }

    if (!empty($segments) && $segments[0] === 'dev') {
        subscriptionRespond(subscriptionDevSessionFixtureError('not_found', 'route not found'), 404);
        return;
    }

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
        count($segments) === 4
        && $segments[0] === 'entities'
        && $segments[3] === 'subscriptions'
    ) {
        if ($method !== 'POST') {
            subscriptionRespond(subscriptionWriteError('method_not_allowed', 'method not allowed'), 405);
            return;
        }

        $entityType = strtolower(trim((string)$segments[1]));
        $entityId = trim((string)$segments[2]);
        if ($entityType !== 'doctor' || !subscriptionValidEntityId($entityId)) {
            subscriptionRespond(subscriptionWriteError('invalid_entity', 'invalid entity'), 422);
            return;
        }

        $payloadResult = subscriptionReadJsonPayload();
        if (!(bool)($payloadResult['ok'] ?? false)) {
            subscriptionRespond((array)($payloadResult['response'] ?? []), (int)($payloadResult['status'] ?? 400));
            return;
        }

        $payload = (array)($payloadResult['payload'] ?? []);
        $forbiddenFields = subscriptionForbiddenPayloadFields($payload, [
            'subscription_id',
            'starts_at',
            'expires_at',
            'status',
            'accepted_by_user_id',
            'accepted_by_actor_role',
            'accepted_by_operator_id',
            'ip_address',
            'user_agent',
            'duration_days',
            'price',
            'capabilities',
            'deleted_at',
            'contract_acceptance_uuid',
            'contract_acceptance_id',
        ]);
        if (array_key_exists('source', $payload)) {
            $forbiddenFields[] = 'source';
        }
        if ($forbiddenFields !== []) {
            subscriptionRespond(
                subscriptionWriteError(
                    'forbidden_fields',
                    'payload contains backend-controlled fields: ' . implode(', ', array_values(array_unique($forbiddenFields)))
                ),
                422
            );
            return;
        }

        $context = subscriptionResolveWriteContext($entityType, $entityId);
        if (!(bool)($context['ok'] ?? false)) {
            subscriptionRespond((array)($context['response'] ?? []), (int)($context['status'] ?? 403));
            return;
        }

        $authMode = (string)($context['auth_mode'] ?? 'session_scope');
        $pdo = mxmed_pdo();
        $repository = new CurrentSubscriptionRepository($pdo);
        $readModelService = new CurrentSubscriptionReadModelService($repository);
        $acceptanceRepository = new SubscriptionContractAcceptanceRepository($pdo);
        $idempotencyService = new SubscriptionWriteIdempotencyService(
            new SubscriptionWriteIdempotencyRepository($pdo)
        );
        $lockService = new SubscriptionEntityWriteLockService($pdo);
        $writeService = new CreateSubscriptionWithAcceptanceService(
            $pdo,
            $repository,
            $readModelService,
            $acceptanceRepository
        );
        $headers = subscriptionHeaders();
        $idempotencyDecision = $idempotencyService->begin(
            $headers['idempotency-key'] ?? null,
            [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'doctor_id' => (string)($context['doctor_id'] ?? ''),
                'profile_id' => $context['profile_id'] ?? null,
                'user_id' => (string)($context['actor_user_id'] ?? ''),
                'actor_role' => (string)($context['actor_role'] ?? ''),
            ],
            $payload
        );

        if ($idempotencyDecision->shouldReject()) {
            subscriptionRespond(
                subscriptionWriteError($idempotencyDecision->errorCode(), $idempotencyDecision->message(), $authMode),
                $idempotencyDecision->httpStatus()
            );
            return;
        }

        if ($idempotencyDecision->shouldReplay()) {
            subscriptionRespond($idempotencyDecision->response(), $idempotencyDecision->httpStatus());
            return;
        }

        $writeLockName = null;
        try {
            $writeLockName = $lockService->acquire($entityType, $entityId, 2);
            if ($writeLockName === null) {
                if ($idempotencyDecision->shouldProceed() && $idempotencyDecision->record() !== null) {
                    $idempotencyService->markFailed($idempotencyDecision->record(), 409);
                }
                subscriptionRespond(
                    subscriptionWriteError(
                        'subscription_write_lock_timeout',
                        'subscription write already in progress for this entity',
                        $authMode
                    ),
                    409
                );
                return;
            }

            $result = $writeService->create([
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'doctor_id' => (string)($context['doctor_id'] ?? ''),
                'profile_id' => $context['profile_id'] ?? null,
                'actor_user_id' => (string)($context['actor_user_id'] ?? ''),
                'actor_role' => (string)($context['actor_role'] ?? ''),
                'operator_id' => $context['operator_id'] ?? null,
                'ip_address' => subscriptionRequestIpAddress(),
                'user_agent' => subscriptionRequestUserAgent(),
                'payload' => $payload,
            ]);
        } catch (SubscriptionWriteException $e) {
            if ($idempotencyDecision->shouldProceed() && $idempotencyDecision->record() !== null) {
                $idempotencyService->markFailed($idempotencyDecision->record(), $e->status());
            }
            subscriptionRespond(subscriptionWriteError($e->errorCode(), $e->getMessage(), $authMode), $e->status());
            return;
        } catch (Throwable $e) {
            if ($idempotencyDecision->shouldProceed() && $idempotencyDecision->record() !== null) {
                $idempotencyService->markFailed($idempotencyDecision->record(), 500);
            }
            throw $e;
        } finally {
            $lockService->release($writeLockName);
        }

        $writeResponse = [
            'ok' => true,
            'data' => $result,
            'meta' => subscriptionWriteMeta($authMode),
        ];
        if ($idempotencyDecision->shouldProceed() && $idempotencyDecision->record() !== null) {
            $idempotencyService->markCompleted($idempotencyDecision->record(), $writeResponse, 201);
        }
        subscriptionRespond($writeResponse, 201);
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
