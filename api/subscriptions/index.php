<?php
declare(strict_types=1);

require_once __DIR__ . '/../_lib/db.php';
require_once __DIR__ . '/../../modules/subscriptions/repositories/CurrentSubscriptionRepository.php';
require_once __DIR__ . '/../../modules/subscriptions/repositories/ProfileSubscriptionRepository.php';
require_once __DIR__ . '/../../modules/subscriptions/repositories/SubscriptionCheckoutIntentRepository.php';
require_once __DIR__ . '/../../modules/subscriptions/repositories/SubscriptionContractAcceptanceRepository.php';
require_once __DIR__ . '/../../modules/subscriptions/repositories/SubscriptionPaymentEventRepository.php';
require_once __DIR__ . '/../../modules/subscriptions/repositories/SubscriptionPaymentIntentRepository.php';
require_once __DIR__ . '/../../modules/subscriptions/repositories/SubscriptionPaymentRouteRepository.php';
require_once __DIR__ . '/../../modules/subscriptions/repositories/SubscriptionPlanPriceRepository.php';
require_once __DIR__ . '/../../modules/subscriptions/repositories/SubscriptionWriteIdempotencyRepository.php';
require_once __DIR__ . '/../../modules/subscriptions/services/ActivateSubscriptionAfterPaymentService.php';
require_once __DIR__ . '/../../modules/subscriptions/services/BuildSubscriptionPaymentActivationStateService.php';
require_once __DIR__ . '/../../modules/subscriptions/services/BuildSubscriptionPaymentRoutePreviewService.php';
require_once __DIR__ . '/../../modules/subscriptions/services/ConfirmSubscriptionPaymentIntentMockService.php';
require_once __DIR__ . '/../../modules/subscriptions/services/CreateSubscriptionCheckoutIntentService.php';
require_once __DIR__ . '/../../modules/subscriptions/services/CreateSubscriptionPaymentRouteService.php';
require_once __DIR__ . '/../../modules/subscriptions/services/CreateSubscriptionPaymentIntentService.php';
require_once __DIR__ . '/../../modules/subscriptions/services/CreateSubscriptionPendingPaymentAcceptanceService.php';
require_once __DIR__ . '/../../modules/subscriptions/services/CurrentSubscriptionReadModelService.php';
require_once __DIR__ . '/../../modules/subscriptions/services/CreateSubscriptionWithAcceptanceService.php';
require_once __DIR__ . '/../../modules/subscriptions/services/ProcessStripeSubscriptionWebhookService.php';
require_once __DIR__ . '/../../modules/subscriptions/services/StripePaymentIntentProviderService.php';
require_once __DIR__ . '/../../modules/subscriptions/services/StripeWebhookPayloadNormalizer.php';
require_once __DIR__ . '/../../modules/subscriptions/services/StripeWebhookSignatureVerifier.php';
require_once __DIR__ . '/../../modules/subscriptions/services/SubscriptionEntityResolverService.php';
require_once __DIR__ . '/../../modules/subscriptions/services/SubscriptionEntityWriteLockService.php';
require_once __DIR__ . '/../../modules/subscriptions/services/SubscriptionPaymentIntentMockProvider.php';
require_once __DIR__ . '/../../modules/subscriptions/services/SubscriptionPlanPriceResolverService.php';
require_once __DIR__ . '/../../modules/subscriptions/services/SubscriptionWriteIdempotencyService.php';

use Subscriptions\Repositories\CurrentSubscriptionRepository;
use Subscriptions\Repositories\ProfileSubscriptionRepository;
use Subscriptions\Repositories\SubscriptionCheckoutIntentRepository;
use Subscriptions\Repositories\SubscriptionContractAcceptanceRepository;
use Subscriptions\Repositories\SubscriptionPaymentEventRepository;
use Subscriptions\Repositories\SubscriptionPaymentIntentRepository;
use Subscriptions\Repositories\SubscriptionPaymentRouteRepository;
use Subscriptions\Repositories\SubscriptionPlanPriceRepository;
use Subscriptions\Repositories\SubscriptionWriteIdempotencyRepository;
use Subscriptions\Services\ActivateSubscriptionAfterPaymentException;
use Subscriptions\Services\ActivateSubscriptionAfterPaymentService;
use Subscriptions\Services\BuildSubscriptionPaymentActivationStateService;
use Subscriptions\Services\BuildSubscriptionPaymentRoutePreviewException;
use Subscriptions\Services\BuildSubscriptionPaymentRoutePreviewService;
use Subscriptions\Services\ConfirmSubscriptionPaymentIntentMockException;
use Subscriptions\Services\ConfirmSubscriptionPaymentIntentMockService;
use Subscriptions\Services\CreateSubscriptionCheckoutIntentException;
use Subscriptions\Services\CreateSubscriptionCheckoutIntentService;
use Subscriptions\Services\CreateSubscriptionPaymentIntentException;
use Subscriptions\Services\CreateSubscriptionPaymentIntentService;
use Subscriptions\Services\CreateSubscriptionPaymentRouteException;
use Subscriptions\Services\CreateSubscriptionPaymentRouteService;
use Subscriptions\Services\CreateSubscriptionPendingPaymentAcceptanceService;
use Subscriptions\Services\CreateSubscriptionWithAcceptanceService;
use Subscriptions\Services\CurrentSubscriptionReadModelService;
use Subscriptions\Services\ProcessStripeSubscriptionWebhookService;
use Subscriptions\Services\StripePaymentIntentProviderService;
use Subscriptions\Services\StripeWebhookPayloadNormalizer;
use Subscriptions\Services\StripeWebhookSignatureVerifier;
use Subscriptions\Services\SubscriptionEntityResolverService;
use Subscriptions\Services\SubscriptionEntityWriteLockService;
use Subscriptions\Services\SubscriptionPaymentIntentMockProvider;
use Subscriptions\Services\SubscriptionPlanPriceResolverService;
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

function subscriptionPaymentRoutePreviewMeta(string $authMode = 'unknown'): array
{
    return [
        'contract' => 'subscription_payment_route_preview',
        'version' => 'SUB-PAYMENT-ROUTE-PREVIEW-1',
        'generated_at' => gmdate('c'),
        'auth_mode' => $authMode,
        'strict_auth_required' => subscriptionStrictAuthRequired(),
        'source' => 'subscriptions_payment_route_preview',
        'mode' => 'preview_no_write',
    ];
}

function subscriptionPaymentRoutePreviewError(string $code, string $message, string $authMode = 'unknown'): array
{
    return [
        'ok' => false,
        'error' => [
            'code' => $code,
            'message' => $message,
        ],
        'data' => null,
        'meta' => subscriptionPaymentRoutePreviewMeta($authMode),
    ];
}

function subscriptionPaymentRouteCreateMeta(string $authMode = 'unknown'): array
{
    return [
        'contract' => 'subscription_payment_route_create',
        'version' => 'SUB-PAYMENT-ROUTE-CREATE-1',
        'generated_at' => gmdate('c'),
        'auth_mode' => $authMode,
        'strict_auth_required' => true,
        'source' => 'subscriptions_payment_route_create',
        'mode' => 'created_no_provider',
    ];
}

function subscriptionPaymentRouteCreateError(string $code, string $message, string $authMode = 'unknown'): array
{
    return [
        'ok' => false,
        'error' => [
            'code' => $code,
            'message' => $message,
        ],
        'data' => null,
        'meta' => subscriptionPaymentRouteCreateMeta($authMode),
    ];
}

function subscriptionStripeWebhookMeta(): array
{
    return [
        'contract' => 'subscription_stripe_webhook',
        'version' => 'SUB-STRIPE-WEBHOOK-1',
        'generated_at' => gmdate('c'),
        'provider' => 'stripe',
        'source' => 'stripe_webhook_v1',
    ];
}

function subscriptionStripeWebhookError(string $code, string $message): array
{
    return [
        'ok' => false,
        'error' => [
            'code' => $code,
            'message' => $message,
        ],
        'data' => null,
        'meta' => subscriptionStripeWebhookMeta(),
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

function subscriptionStripeWebhookSecret(): string
{
    return subscriptionEnvValue('STRIPE_WEBHOOK_SECRET');
}

function subscriptionStripeWebhookSignatureHeader(array $headers): string
{
    $header = trim((string)($headers['stripe-signature'] ?? ''));
    if ($header !== '') {
        return $header;
    }

    return trim((string)($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? ''));
}

function subscriptionStripeWebhookEnvironment(): string
{
    return subscriptionEnvValue('MXMED_ENV')
        ?: (subscriptionEnvValue('APP_ENV') ?: subscriptionEnvValue('ENVIRONMENT'));
}

function subscriptionStripeWebhookExpectedLivemode(): array
{
    $environment = subscriptionStripeWebhookEnvironment();
    $configured = trim(subscriptionEnvValue('STRIPE_WEBHOOK_EXPECTED_LIVEMODE'));
    if ($configured !== '') {
        $normalized = strtolower($configured);
        if ($normalized === 'true' || $normalized === '1') {
            return [
                'ok' => true,
                'expected_livemode' => true,
                'source' => 'STRIPE_WEBHOOK_EXPECTED_LIVEMODE',
                'environment' => $environment,
            ];
        }
        if ($normalized === 'false' || $normalized === '0') {
            return [
                'ok' => true,
                'expected_livemode' => false,
                'source' => 'STRIPE_WEBHOOK_EXPECTED_LIVEMODE',
                'environment' => $environment,
            ];
        }

        return subscriptionStripeWebhookLivemodeExpectationError(
            'stripe_livemode_expectation_invalid',
            'stripe webhook livemode expectation is invalid',
            $environment,
            'STRIPE_WEBHOOK_EXPECTED_LIVEMODE'
        );
    }

    $environmentKey = strtolower($environment);
    if (subscriptionProductionEnvironmentDetected()) {
        return subscriptionStripeWebhookLivemodeExpectationError(
            'stripe_livemode_expectation_missing',
            'stripe webhook livemode expectation is required',
            $environment,
            'environment_production'
        );
    }

    $devEnvironments = ['local', 'dev', 'development', 'test', 'testing', 'qa', 'sandbox', 'staging'];
    if (in_array($environmentKey, $devEnvironments, true) || subscriptionIsLocalRequest()) {
        return [
            'ok' => true,
            'expected_livemode' => false,
            'source' => $environmentKey !== '' ? 'environment_default' : 'local_request_default',
            'environment' => $environment,
        ];
    }

    return subscriptionStripeWebhookLivemodeExpectationError(
        'stripe_livemode_expectation_missing',
        'stripe webhook livemode expectation is required',
        $environment,
        'environment_unknown'
    );
}

function subscriptionStripeWebhookLivemodeExpectationError(
    string $code,
    string $message,
    string $environment,
    string $source
): array {
    return [
        'ok' => false,
        'code' => $code,
        'http_status' => 500,
        'message' => $message,
        'log_context' => [
            'provider' => 'stripe',
            'error_code' => $code,
            'expectation_source' => $source,
            'environment' => $environment,
        ],
    ];
}

function subscriptionStripeWebhookResponse(array $result): array
{
    $status = (int)($result['http_status_recommended'] ?? 200);
    $data = $result;
    unset($data['http_status_recommended']);

    $ok = $status >= 200 && $status < 300 && !(bool)($data['conflict'] ?? false);
    $response = [
        'ok' => $ok,
        'data' => $data,
        'meta' => subscriptionStripeWebhookMeta(),
    ];
    if (!$ok) {
        $reason = trim((string)($data['reason'] ?? 'stripe_webhook_unprocessable'));
        $response['error'] = [
            'code' => $reason !== '' ? $reason : 'stripe_webhook_unprocessable',
            'message' => 'stripe webhook event could not be processed',
        ];
    }

    return $response;
}

function subscriptionStripeWebhookLog(string $message, array $context = []): void
{
    $safe = [];
    foreach ($context as $key => $value) {
        $safe[(string)$key] = subscriptionSafeLogValue($value);
    }

    error_log('[subscriptions.stripe_webhook] ' . $message . ' ' . json_encode($safe, JSON_UNESCAPED_SLASHES));
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

function subscriptionSafeLogValue($value, int $maxLength = 96): string
{
    $text = trim((string)($value ?? ''));
    if ($text === '') {
        return '';
    }

    $text = preg_replace('/[^A-Za-z0-9_.:\/ -]/', '?', $text) ?? '';
    if (strlen($text) > $maxLength) {
        return substr($text, 0, $maxLength);
    }

    return $text;
}

function subscriptionGenerateUuidV4(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function subscriptionLogDevGuardBlocked(string $action, string $reason, array $context = []): void
{
    $event = [
        'event' => 'subscriptions_dev_guard_blocked',
        'action' => subscriptionSafeLogValue($action, 64),
        'reason' => subscriptionSafeLogValue($reason, 64),
        'route' => subscriptionSafeLogValue($context['route'] ?? '', 128),
        'method' => subscriptionSafeLogValue($_SERVER['REQUEST_METHOD'] ?? '', 16),
        'host' => subscriptionSafeLogValue($_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? ''), 128),
        'app_env' => subscriptionSafeLogValue(subscriptionEnvValue('APP_ENV'), 32),
        'mxmed_env' => subscriptionSafeLogValue(subscriptionEnvValue('MXMED_ENV'), 32),
        'environment' => subscriptionSafeLogValue(subscriptionEnvValue('ENVIRONMENT'), 32),
        'generated_at' => gmdate('c'),
    ];

    $json = json_encode($event, JSON_UNESCAPED_SLASHES);
    error_log('[mxmed-subscriptions] ' . ($json !== false ? $json : 'subscriptions_dev_guard_blocked'));
}

function subscriptionDevGuardFailure(
    string $action,
    string $route,
    string $code,
    string $message,
    int $status
): array {
    subscriptionLogDevGuardBlocked($action, $code, ['route' => $route]);

    return [
        'ok' => false,
        'status' => $status,
        'code' => $code,
        'message' => $message,
    ];
}

function subscriptionAssertDevFixtureAllowed(string $route, string $method): array
{
    if ($method !== 'POST') {
        return subscriptionDevGuardFailure('dev_session_fixture', $route, 'method_not_allowed', 'method not allowed', 405);
    }

    if (!subscriptionDevSessionFixtureEnabled()) {
        return subscriptionDevGuardFailure('dev_session_fixture', $route, 'fixture_disabled', 'dev session fixture disabled', 403);
    }

    if (!subscriptionIsLocalRequest()) {
        return subscriptionDevGuardFailure('dev_session_fixture', $route, 'local_only', 'dev session fixture is local only', 403);
    }

    if (subscriptionProductionEnvironmentDetected()) {
        return subscriptionDevGuardFailure('dev_session_fixture', $route, 'production_blocked', 'dev session fixture is blocked in production', 403);
    }

    return ['ok' => true];
}

function subscriptionAssertConfirmMockAllowed(string $route, string $method): array
{
    if ($method !== 'POST') {
        return subscriptionDevGuardFailure('confirm_mock', $route, 'method_not_allowed', 'method not allowed', 405);
    }

    if (subscriptionProductionEnvironmentDetected()) {
        return subscriptionDevGuardFailure('confirm_mock', $route, 'confirm_mock_production_blocked', 'confirm mock is blocked in production', 403);
    }

    if (!subscriptionIsLocalRequest()) {
        return subscriptionDevGuardFailure('confirm_mock', $route, 'confirm_mock_local_only', 'confirm mock is local/dev only', 403);
    }

    $flag = subscriptionEnvValue('MXMED_SUBSCRIPTIONS_CONFIRM_MOCK_ENABLED');
    if ($flag !== '' && !subscriptionBoolEnvFlag($flag)) {
        return subscriptionDevGuardFailure('confirm_mock', $route, 'confirm_mock_disabled', 'confirm mock is disabled', 403);
    }

    return ['ok' => true];
}

function subscriptionAssertMockProviderAllowed(string $provider, string $route): array
{
    if ($provider !== 'mxmed_mock') {
        return ['ok' => true];
    }

    if (subscriptionProductionEnvironmentDetected()) {
        return subscriptionDevGuardFailure('mock_provider', $route, 'mock_provider_production_blocked', 'mxmed_mock is blocked in production', 403);
    }

    if (!subscriptionIsLocalRequest()) {
        return subscriptionDevGuardFailure('mock_provider', $route, 'mock_provider_local_only', 'mxmed_mock is local/dev only', 403);
    }

    $flag = subscriptionEnvValue('MXMED_SUBSCRIPTIONS_MOCK_PAYMENTS_ENABLED');
    if ($flag !== '' && !subscriptionBoolEnvFlag($flag)) {
        return subscriptionDevGuardFailure('mock_provider', $route, 'mock_provider_disabled', 'mxmed_mock is disabled', 403);
    }

    return ['ok' => true];
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

function subscriptionReadOptionalDevFixtureJsonPayload(): array
{
    $contentType = strtolower(trim((string)($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '')));
    if ($contentType !== '' && strpos($contentType, 'application/json') === false) {
        return [
            'ok' => false,
            'error' => subscriptionDevSessionFixtureError('invalid_payload', 'content-type must be application/json'),
        ];
    }

    $raw = file_get_contents('php://input');
    if (!is_string($raw) || trim($raw) === '') {
        return [
            'ok' => true,
            'payload' => [],
        ];
    }

    $decoded = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded) || array_values($decoded) === $decoded) {
        return [
            'ok' => false,
            'error' => subscriptionDevSessionFixtureError('invalid_payload', 'invalid json payload'),
        ];
    }

    return [
        'ok' => true,
        'payload' => $decoded,
    ];
}

function subscriptionStripePaymentIntentFixtureAllowedDoctorIds(): array
{
    return ['990099', '3', '4', '5', '6', '7', '8', '9', '10', '2'];
}

function subscriptionStripePaymentIntentFixtureEnsureDoctorId(): string
{
    return '990099';
}

function subscriptionNormalizeDevFixtureDoctorId($value): string
{
    if (!is_scalar($value) || is_bool($value)) {
        return '';
    }

    $doctorId = trim((string)$value);
    if ($doctorId === '' || strlen($doctorId) > 20 || !ctype_digit($doctorId)) {
        return '';
    }

    $doctorId = ltrim($doctorId, '0');
    return $doctorId === '' ? '0' : $doctorId;
}

function subscriptionResolveStripePaymentIntentFixtureDoctor(array $payload): array
{
    $allowedFields = ['doctor_id'];
    $unsupportedFields = array_values(array_diff(array_keys($payload), $allowedFields));
    if ($unsupportedFields !== []) {
        return subscriptionDevSessionFixtureError(
            'stripe_fixture_payload_forbidden_fields',
            'stripe payment intent fixture payload contains unsupported fields'
        );
    }

    $allowedDoctorIds = subscriptionStripePaymentIntentFixtureAllowedDoctorIds();
    if (array_key_exists('doctor_id', $payload)) {
        $doctorId = subscriptionNormalizeDevFixtureDoctorId($payload['doctor_id']);
        if ($doctorId === '' || $doctorId === '0' || $doctorId === '900001' || !in_array($doctorId, $allowedDoctorIds, true)) {
            return subscriptionDevSessionFixtureError(
                'fixture_doctor_not_allowed',
                'stripe payment intent fixture doctor is not allowed'
            );
        }
        if ($doctorId === subscriptionStripePaymentIntentFixtureEnsureDoctorId()) {
            $ensuredDoctor = subscriptionEnsureStripePaymentIntentFixtureDoctor();
            if ((bool)($ensuredDoctor['ok'] ?? false)) {
                $ensuredDoctor['selection'] = 'requested_' . (string)($ensuredDoctor['selection'] ?? 'ensured');
            }

            return $ensuredDoctor;
        }
        if (!subscriptionDoctorFixtureExists($doctorId)) {
            return subscriptionDevSessionFixtureError('fixture_doctor_not_found', 'stripe payment intent fixture doctor not found');
        }
        if (subscriptionDoctorHasActiveSubscription($doctorId)) {
            return subscriptionDevSessionFixtureError(
                'fixture_doctor_has_active_subscription',
                'stripe payment intent fixture doctor has active subscription'
            );
        }

        return [
            'ok' => true,
            'doctor_id' => $doctorId,
            'selection' => 'requested',
        ];
    }

    foreach ($allowedDoctorIds as $candidateDoctorId) {
        if ($candidateDoctorId === '900001' || $candidateDoctorId === subscriptionStripePaymentIntentFixtureEnsureDoctorId()) {
            continue;
        }

        try {
            if (!subscriptionDoctorFixtureExists($candidateDoctorId)) {
                continue;
            }
            if (subscriptionDoctorHasActiveSubscription($candidateDoctorId)) {
                continue;
            }
        } catch (Throwable $e) {
            continue;
        }

        return [
            'ok' => true,
            'doctor_id' => $candidateDoctorId,
            'selection' => 'auto',
        ];
    }

    $ensuredDoctor = subscriptionEnsureStripePaymentIntentFixtureDoctor();
    if ((bool)($ensuredDoctor['ok'] ?? false)) {
        return $ensuredDoctor;
    }

    return subscriptionDevSessionFixtureError(
        'stripe_fixture_doctor_unavailable',
        'stripe payment intent fixture doctor is unavailable'
    );
}

function subscriptionFetchStripePaymentIntentFixtureDoctor(string $doctorId): ?array
{
    $stmt = mxmed_pdo()->prepare(
        'SELECT doctor_id, display_name, profile_status, is_public_candidate
         FROM profiles_doctors
         WHERE doctor_id = :doctor_id
         LIMIT 1'
    );
    $stmt->execute(['doctor_id' => $doctorId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
}

function subscriptionStripePaymentIntentFixtureDoctorIsMarked(array $doctor): bool
{
    return (string)($doctor['doctor_id'] ?? '') === subscriptionStripePaymentIntentFixtureEnsureDoctorId()
        && (string)($doctor['display_name'] ?? '') === 'MXMed DEV Stripe Harness Doctor'
        && (string)($doctor['profile_status'] ?? '') === 'hidden'
        && (int)($doctor['is_public_candidate'] ?? 1) === 0;
}

function subscriptionInsertStripePaymentIntentFixtureDoctor(PDO $pdo, string $doctorId): void
{
    $columnsStmt = $pdo->query('SHOW COLUMNS FROM profiles_doctors');
    $columns = [];
    foreach ($columnsStmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
        $field = (string)($column['Field'] ?? '');
        if ($field !== '') {
            $columns[$field] = true;
        }
    }

    if (!isset($columns['doctor_id'])) {
        throw new RuntimeException('profiles_doctors doctor_id column missing');
    }

    $values = ['doctor_id' => $doctorId];
    $fixtureValues = [
        'display_name' => 'MXMed DEV Stripe Harness Doctor',
        'prefix' => 'Dr.',
        'gender_label' => 'No especificado',
        'professional_license' => 'DEV-STRIPE-HARNESS',
        'specialty_license' => 'DEV-STRIPE-HARNESS',
        'specialty_primary' => 'Medicina General',
        'specialty_secondary_json' => '[]',
        'bio_short' => 'Fixture DEV/local para QA de webhook Stripe sintetico.',
        'profile_status' => 'hidden',
        'is_public_candidate' => 0,
    ];

    foreach ($fixtureValues as $column => $value) {
        if (isset($columns[$column])) {
            $values[$column] = $value;
        }
    }

    $insertColumns = array_keys($values);
    $sql = sprintf(
        'INSERT INTO profiles_doctors (%s) VALUES (%s)',
        implode(', ', array_map(static fn(string $column): string => '`' . $column . '`', $insertColumns)),
        implode(', ', array_map(static fn(string $column): string => ':' . $column, $insertColumns))
    );

    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);
}

function subscriptionEnsureStripePaymentIntentFixtureDoctor(): array
{
    $doctorId = subscriptionStripePaymentIntentFixtureEnsureDoctorId();
    try {
        $existingDoctor = subscriptionFetchStripePaymentIntentFixtureDoctor($doctorId);
        if ($existingDoctor !== null) {
            if (!subscriptionStripePaymentIntentFixtureDoctorIsMarked($existingDoctor)) {
                return subscriptionDevSessionFixtureError(
                    'stripe_fixture_doctor_ensure_failed',
                    'stripe payment intent fixture doctor is not marked as dev local'
                );
            }
            if (subscriptionDoctorHasActiveSubscription($doctorId)) {
                return subscriptionDevSessionFixtureError(
                    'fixture_doctor_has_active_subscription',
                    'stripe payment intent fixture doctor has active subscription'
                );
            }

            return [
                'ok' => true,
                'doctor_id' => $doctorId,
                'selection' => 'ensured_existing',
            ];
        }

        subscriptionInsertStripePaymentIntentFixtureDoctor(mxmed_pdo(), $doctorId);
        if (subscriptionDoctorHasActiveSubscription($doctorId)) {
            return subscriptionDevSessionFixtureError(
                'fixture_doctor_has_active_subscription',
                'stripe payment intent fixture doctor has active subscription'
            );
        }

        return [
            'ok' => true,
            'doctor_id' => $doctorId,
            'selection' => 'ensured_created',
        ];
    } catch (Throwable $e) {
        return subscriptionDevSessionFixtureError(
            'stripe_fixture_doctor_ensure_failed',
            'stripe payment intent fixture doctor could not be ensured'
        );
    }
}

function subscriptionCreateStripePaymentIntentFixture(): array
{
    $payloadResult = subscriptionReadOptionalDevFixtureJsonPayload();
    if (!(bool)($payloadResult['ok'] ?? false)) {
        return $payloadResult['error'] ?? subscriptionDevSessionFixtureError('invalid_payload', 'invalid json payload');
    }

    $fixtureDoctor = subscriptionResolveStripePaymentIntentFixtureDoctor(
        is_array($payloadResult['payload'] ?? null) ? $payloadResult['payload'] : []
    );
    if (!(bool)($fixtureDoctor['ok'] ?? false)) {
        return $fixtureDoctor;
    }

    $doctorId = (string)$fixtureDoctor['doctor_id'];
    $userId = $doctorId;
    $fixtureDoctorSelection = (string)($fixtureDoctor['selection'] ?? 'auto');
    $planCode = 'basic';
    $billingPeriod = 'annual';
    $contractVersion = 'mxmed-subscriptions-v1';
    $contractHash = 'sha256:qa-local-dev-stripe-payment-intent-harness';
    $contractSnapshotUrl = '/legal/subscriptions/mxmed-subscriptions-v1.html';
    $contractTitle = 'Contrato de suscripción México Médico';
    $idempotencyKey = 'mxmed-dev-stripe-payment-intent-fixture-doctor-' . $doctorId . '-basic-annual-qa-01';

    $pdo = mxmed_pdo();
    $checkoutRepository = new SubscriptionCheckoutIntentRepository($pdo);
    $paymentIntentRepository = new SubscriptionPaymentIntentRepository($pdo);
    $checkoutService = new CreateSubscriptionCheckoutIntentService(
        $pdo,
        new SubscriptionEntityResolverService($pdo),
        new CurrentSubscriptionRepository($pdo),
        new SubscriptionWriteIdempotencyService(new SubscriptionWriteIdempotencyRepository($pdo)),
        new SubscriptionEntityWriteLockService($pdo),
        new SubscriptionPlanPriceResolverService(new SubscriptionPlanPriceRepository($pdo)),
        new CreateSubscriptionPendingPaymentAcceptanceService(
            new SubscriptionContractAcceptanceRepository($pdo)
        ),
        $checkoutRepository
    );

    try {
        $checkoutResponse = $checkoutService->createCheckoutIntent([
            'entity_type' => 'doctor',
            'entity_id' => $doctorId,
            'intent_type' => 'new_subscription',
            'plan_code' => $planCode,
            'billing_period' => $billingPeriod,
            'contract_version' => $contractVersion,
            'contract_hash' => $contractHash,
            'contract_snapshot_url' => $contractSnapshotUrl,
            'contract_title' => $contractTitle,
            'source' => 'checkout_intent',
            'idempotency_key' => $idempotencyKey,
            'actor_user_id' => $userId,
            'actor_role' => 'doctor',
            'doctor_id' => $doctorId,
            'profile_id' => null,
            'ip_address' => subscriptionRequestIpAddress(),
            'user_agent' => subscriptionRequestUserAgent(),
        ]);
    } catch (CreateSubscriptionCheckoutIntentException $e) {
        return subscriptionDevSessionFixtureError(
            $e->errorCode(),
            'stripe payment intent fixture checkout failed: ' . $e->getMessage()
        );
    } catch (Throwable $e) {
        return subscriptionDevSessionFixtureError(
            'stripe_payment_intent_fixture_unavailable',
            'stripe payment intent fixture is unavailable'
        );
    }

    $checkoutData = is_array($checkoutResponse['data'] ?? null) ? $checkoutResponse['data'] : [];
    $checkoutIntentUuid = trim((string)($checkoutData['checkout_intent_uuid'] ?? ''));
    if ($checkoutIntentUuid === '') {
        return subscriptionDevSessionFixtureError(
            'stripe_payment_intent_fixture_checkout_missing',
            'stripe payment intent fixture checkout is missing'
        );
    }

    try {
        $checkoutIntent = $checkoutRepository->findByUuid($checkoutIntentUuid);
    } catch (Throwable $e) {
        return subscriptionDevSessionFixtureError(
            'stripe_payment_intent_fixture_checkout_lookup_failed',
            'stripe payment intent fixture checkout lookup failed'
        );
    }
    if ($checkoutIntent === null) {
        return subscriptionDevSessionFixtureError(
            'stripe_payment_intent_fixture_checkout_not_found',
            'stripe payment intent fixture checkout was not found'
        );
    }

    $amountCents = (int)($checkoutIntent['amount_cents'] ?? 0);
    $currency = strtoupper(trim((string)($checkoutIntent['currency'] ?? '')));
    if ($amountCents <= 0 || $currency === '') {
        return subscriptionDevSessionFixtureError(
            'stripe_payment_intent_fixture_invalid_checkout_snapshot',
            'stripe payment intent fixture checkout snapshot is invalid'
        );
    }

    $providerPaymentId = 'pi_mxmed_stripe_synthetic_' . substr(hash('sha256', $checkoutIntentUuid), 0, 24);
    $providerCheckoutId = 'cs_mxmed_stripe_synthetic_' . substr(hash('sha256', $checkoutIntentUuid), 0, 24);

    try {
        $existingPaymentIntent = $paymentIntentRepository->findByProviderPaymentId('stripe', $providerPaymentId);
    } catch (Throwable $e) {
        return subscriptionDevSessionFixtureError(
            'stripe_payment_intent_fixture_lookup_failed',
            'stripe payment intent fixture lookup failed'
        );
    }

    if ($existingPaymentIntent !== null) {
        $existingStatus = (string)($existingPaymentIntent['normalized_status'] ?? '');
        if (!in_array($existingStatus, ['created', 'pending', 'pending_provider'], true)) {
            return subscriptionDevSessionFixtureError(
                'stripe_payment_intent_fixture_already_finalized',
                'stripe payment intent fixture is already finalized'
            );
        }

        return subscriptionStripePaymentIntentFixtureResponse(
            $checkoutIntent,
            $existingPaymentIntent,
            true,
            $fixtureDoctorSelection
        );
    }

    try {
        $paymentIntent = $paymentIntentRepository->create([
            'uuid' => subscriptionGenerateUuidV4(),
            'checkout_intent_uuid' => $checkoutIntentUuid,
            'provider' => 'stripe',
            'provider_payment_id' => $providerPaymentId,
            'provider_checkout_id' => $providerCheckoutId,
            'normalized_status' => 'created',
            'provider_status' => 'requires_payment_method',
            'amount_cents' => $amountCents,
            'currency' => $currency,
            'created_at_provider' => gmdate('Y-m-d H:i:s'),
            'source' => 'stripe_payment_intent_test_harness',
            'notes' => json_encode([
                'fixture' => 'stripe-payment-intent',
                'dev_only' => true,
                'intended_use' => 'stripe_webhook_matched_synthetic_qa',
            ], JSON_UNESCAPED_SLASHES),
        ]);
    } catch (Throwable $e) {
        return subscriptionDevSessionFixtureError(
            'stripe_payment_intent_fixture_create_failed',
            'stripe payment intent fixture could not be created'
        );
    }

    return subscriptionStripePaymentIntentFixtureResponse($checkoutIntent, $paymentIntent, false, $fixtureDoctorSelection);
}

function subscriptionStripePaymentIntentFixtureResponse(
    array $checkoutIntent,
    array $paymentIntent,
    bool $idempotentReplay,
    string $fixtureDoctorSelection = 'auto'
): array {
    return [
        'ok' => true,
        'data' => [
            'fixture' => 'stripe-payment-intent',
            'fixture_doctor_selection' => $fixtureDoctorSelection,
            'entity_type' => (string)($checkoutIntent['entity_type'] ?? 'doctor'),
            'entity_id' => (string)($checkoutIntent['entity_id'] ?? ''),
            'doctor_id' => (string)($checkoutIntent['doctor_id'] ?? ''),
            'checkout_intent_uuid' => (string)($checkoutIntent['uuid'] ?? ''),
            'contract_acceptance_uuid' => (string)($checkoutIntent['contract_acceptance_uuid'] ?? ''),
            'payment_intent_uuid' => (string)($paymentIntent['uuid'] ?? ''),
            'provider' => (string)($paymentIntent['provider'] ?? 'stripe'),
            'provider_payment_id' => (string)($paymentIntent['provider_payment_id'] ?? ''),
            'provider_checkout_id' => (string)($paymentIntent['provider_checkout_id'] ?? ''),
            'amount_cents' => (int)($paymentIntent['amount_cents'] ?? 0),
            'currency' => (string)($paymentIntent['currency'] ?? ''),
            'normalized_status' => (string)($paymentIntent['normalized_status'] ?? ''),
            'provider_status' => (string)($paymentIntent['provider_status'] ?? ''),
            'next_step' => 'send_synthetic_stripe_webhook',
            'idempotent_replay' => $idempotentReplay,
        ],
        'meta' => subscriptionDevSessionFixtureMeta(),
    ];
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

function subscriptionFindActiveDoctorSubscriptionFixture(string $doctorId): ?array
{
    $repository = new CurrentSubscriptionRepository(mxmed_pdo());
    return $repository->findActiveByEntity('doctor', $doctorId);
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

function subscriptionCreateCheckoutDoctorSessionFixture(): array
{
    $doctorId = '900001';
    $userId = '900001';
    if (!subscriptionDoctorFixtureExists($doctorId)) {
        return subscriptionDevSessionFixtureError('fixture_doctor_not_found', 'checkout doctor fixture not found');
    }

    $hasActiveSubscription = subscriptionDoctorHasActiveSubscription($doctorId);
    if ($hasActiveSubscription) {
        return subscriptionDevSessionFixtureError('fixture_doctor_has_active_subscription', 'checkout doctor fixture has active subscription');
    }

    subscriptionApplyDevDoctorSessionFixture($doctorId, $userId);

    return [
        'ok' => true,
        'data' => [
            'auth_mode' => 'session_scope',
            'source' => 'dev_session_fixture',
            'route' => 'dev/session-fixture/checkout-doctor',
            'entity_type' => 'doctor',
            'entity_id' => $doctorId,
            'doctor_id' => $doctorId,
            'actor_role' => 'doctor',
            'operator_id' => null,
            'fixture' => 'checkout_doctor',
            'has_active_subscription' => false,
            'warning' => 'DEV/local only',
        ],
        'meta' => subscriptionDevSessionFixtureMeta(),
    ];
}

function subscriptionCreateUpgradeDoctorSessionFixture(): array
{
    $doctorId = '900001';
    $userId = '900001';
    $allowedUpgradeTargets = [
        'standard' => 'optimum',
        'optimum' => 'professional',
    ];
    $planRanks = [
        'basic' => 1,
        'standard' => 2,
        'optimum' => 3,
        'professional' => 4,
    ];
    if (!subscriptionDoctorFixtureExists($doctorId)) {
        return subscriptionDevSessionFixtureError('fixture_doctor_not_found', 'upgrade doctor fixture not found');
    }

    try {
        $activeSubscription = subscriptionFindActiveDoctorSubscriptionFixture($doctorId);
    } catch (Throwable $e) {
        return subscriptionDevSessionFixtureError(
            'fixture_active_subscription_unavailable',
            'upgrade doctor active subscription could not be validated'
        );
    }

    if (!is_array($activeSubscription)) {
        return subscriptionDevSessionFixtureError(
            'fixture_doctor_has_no_active_subscription',
            'upgrade doctor fixture has no active subscription'
        );
    }

    $planCode = strtolower(trim((string)($activeSubscription['plan_code'] ?? '')));
    $billingPeriod = strtolower(trim((string)($activeSubscription['billing_period'] ?? '')));
    $targetPlanCode = $allowedUpgradeTargets[$planCode] ?? null;
    if ($targetPlanCode === null) {
        return subscriptionDevSessionFixtureError(
            'fixture_doctor_active_subscription_not_upgradeable',
            'upgrade doctor active subscription does not have a supported higher target'
        );
    }
    if ($billingPeriod !== 'annual') {
        return subscriptionDevSessionFixtureError(
            'fixture_doctor_active_subscription_not_annual',
            'upgrade doctor active subscription is not annual'
        );
    }
    if (($planRanks[$targetPlanCode] ?? 0) <= ($planRanks[$planCode] ?? 0)) {
        return subscriptionDevSessionFixtureError(
            'fixture_upgrade_target_not_higher',
            'upgrade doctor target plan is not higher than current plan'
        );
    }

    subscriptionApplyDevDoctorSessionFixture($doctorId, $userId);

    return [
        'ok' => true,
        'data' => [
            'auth_mode' => 'session_scope',
            'source' => 'dev_session_fixture',
            'route' => 'dev/session-fixture/upgrade-doctor',
            'entity_type' => 'doctor',
            'entity_id' => $doctorId,
            'doctor_id' => $doctorId,
            'actor_role' => 'doctor',
            'operator_id' => null,
            'fixture' => 'upgrade-doctor',
            'session_scope' => true,
            'intended_use' => 'upgrade_checkout_qa',
            'active_subscription' => [
                'exists' => true,
                'subscription_id' => (string)($activeSubscription['subscription_id'] ?? ''),
                'plan_code' => $planCode,
                'billing_period' => $billingPeriod,
                'status' => (string)($activeSubscription['status'] ?? ''),
                'starts_at' => (string)($activeSubscription['starts_at'] ?? ''),
                'expires_at' => (string)($activeSubscription['expires_at'] ?? ''),
            ],
            'upgrade' => [
                'intent_type' => 'upgrade',
                'current_plan_code' => $planCode,
                'target_plan_code' => $targetPlanCode,
                'current_billing_period' => $billingPeriod,
                'target_billing_period' => $billingPeriod,
                'pricing_strategy' => 'prorated_difference',
            ],
            'warning' => 'DEV/local only',
        ],
        'meta' => subscriptionDevSessionFixtureMeta(),
    ];
}

function subscriptionStripeUpgradeFixturePlanTargets(): array
{
    return [
        'basic' => 'standard',
        'standard' => 'optimum',
        'optimum' => 'professional',
    ];
}

function subscriptionStripeUpgradeFixturePlanRanks(): array
{
    return [
        'basic' => 1,
        'standard' => 2,
        'optimum' => 3,
        'professional' => 4,
    ];
}

function subscriptionStripeUpgradeFixtureCandidateDoctorIds(): array
{
    return array_values(array_unique(subscriptionStripePaymentIntentFixtureAllowedDoctorIds()));
}

function subscriptionNormalizeStripeUpgradeFixturePlan($value): string
{
    $plan = strtolower(trim((string)($value ?? '')));
    $map = [
        'basico' => 'basic',
        'básico' => 'basic',
        'basic' => 'basic',
        'estandar' => 'standard',
        'estándar' => 'standard',
        'standard' => 'standard',
        'optimo' => 'optimum',
        'óptimo' => 'optimum',
        'optimum' => 'optimum',
        'profesional' => 'professional',
        'professional' => 'professional',
    ];

    return $map[$plan] ?? $plan;
}

function subscriptionBuildStripeUpgradeDoctorSessionFixture(
    string $doctorId,
    array $payload,
    string $selection
): array {
    if (!subscriptionDoctorFixtureExists($doctorId)) {
        return subscriptionDevSessionFixtureError(
            'fixture_upgrade_candidate_not_found',
            'stripe upgrade doctor fixture candidate was not found'
        );
    }

    try {
        $activeSubscription = subscriptionFindActiveDoctorSubscriptionFixture($doctorId);
    } catch (Throwable $e) {
        return subscriptionDevSessionFixtureError(
            'fixture_upgrade_candidate_not_found',
            'stripe upgrade doctor fixture candidate could not be validated'
        );
    }

    if (!is_array($activeSubscription)) {
        return subscriptionDevSessionFixtureError(
            'fixture_doctor_has_no_active_subscription',
            'stripe upgrade doctor fixture has no active subscription'
        );
    }

    $targets = subscriptionStripeUpgradeFixturePlanTargets();
    $ranks = subscriptionStripeUpgradeFixturePlanRanks();
    $planCode = subscriptionNormalizeStripeUpgradeFixturePlan($activeSubscription['plan_code'] ?? '');
    $targetPlanCode = $targets[$planCode] ?? null;
    if ($targetPlanCode === null) {
        return subscriptionDevSessionFixtureError(
            'fixture_doctor_active_subscription_plan_unsupported',
            'stripe upgrade doctor active subscription plan is unsupported'
        );
    }

    if (array_key_exists('target_plan_code', $payload)) {
        $requestedTargetPlan = subscriptionNormalizeStripeUpgradeFixturePlan($payload['target_plan_code']);
        if ($requestedTargetPlan === '' || $requestedTargetPlan !== $targetPlanCode) {
            return subscriptionDevSessionFixtureError(
                'fixture_doctor_not_upgradeable',
                'stripe upgrade doctor target is not supported for current plan'
            );
        }
    }

    $billingPeriod = strtolower(trim((string)($activeSubscription['billing_period'] ?? '')));
    if (array_key_exists('billing_period', $payload)) {
        $requestedBillingPeriod = strtolower(trim((string)($payload['billing_period'] ?? '')));
        if ($requestedBillingPeriod === '' || $requestedBillingPeriod !== $billingPeriod) {
            return subscriptionDevSessionFixtureError(
                'fixture_doctor_not_upgradeable',
                'stripe upgrade doctor billing period does not match active subscription'
            );
        }
    }

    if (($ranks[$targetPlanCode] ?? 0) <= ($ranks[$planCode] ?? 0)) {
        return subscriptionDevSessionFixtureError(
            'fixture_doctor_not_upgradeable',
            'stripe upgrade doctor target plan is not higher than current plan'
        );
    }

    subscriptionApplyDevDoctorSessionFixture($doctorId, $doctorId);

    return [
        'ok' => true,
        'data' => [
            'auth_mode' => 'session_scope',
            'source' => 'dev_session_fixture',
            'route' => 'dev/session-fixture/stripe-upgrade-doctor',
            'fixture' => 'stripe-upgrade-doctor',
            'selection' => $selection,
            'entity_type' => 'doctor',
            'entity_id' => $doctorId,
            'doctor_id' => $doctorId,
            'actor_role' => 'doctor',
            'operator_id' => null,
            'session_scope' => true,
            'current_plan_code' => $planCode,
            'target_plan_code' => $targetPlanCode,
            'billing_period' => $billingPeriod,
            'intended_use' => 'stripe_payment_intent_upgrade_qa',
            'dev_only' => true,
            'active_subscription' => [
                'exists' => true,
                'subscription_id' => (string)($activeSubscription['subscription_id'] ?? ''),
                'plan_code' => $planCode,
                'billing_period' => $billingPeriod,
                'status' => (string)($activeSubscription['status'] ?? ''),
                'starts_at' => (string)($activeSubscription['starts_at'] ?? ''),
                'expires_at' => (string)($activeSubscription['expires_at'] ?? ''),
            ],
            'upgrade' => [
                'intent_type' => 'upgrade',
                'current_plan_code' => $planCode,
                'target_plan_code' => $targetPlanCode,
                'current_billing_period' => $billingPeriod,
                'target_billing_period' => $billingPeriod,
                'pricing_strategy' => 'prorated_difference',
            ],
            'warning' => 'DEV/local only',
        ],
        'meta' => subscriptionDevSessionFixtureMeta(),
    ];
}

function subscriptionCreateStripeUpgradeDoctorSessionFixture(): array
{
    $payloadResult = subscriptionReadOptionalDevFixtureJsonPayload();
    if (!(bool)($payloadResult['ok'] ?? false)) {
        return $payloadResult['error'] ?? subscriptionDevSessionFixtureError('invalid_payload', 'invalid json payload');
    }

    $payload = is_array($payloadResult['payload'] ?? null) ? $payloadResult['payload'] : [];
    $allowedFields = ['doctor_id', 'target_plan_code', 'billing_period'];
    $unsupportedFields = array_values(array_diff(array_keys($payload), $allowedFields));
    if ($unsupportedFields !== []) {
        return subscriptionDevSessionFixtureError(
            'fixture_upgrade_candidate_not_found',
            'stripe upgrade doctor fixture payload contains unsupported fields'
        );
    }

    $candidateDoctorIds = subscriptionStripeUpgradeFixtureCandidateDoctorIds();
    if (array_key_exists('doctor_id', $payload)) {
        $doctorId = subscriptionNormalizeDevFixtureDoctorId($payload['doctor_id']);
        if (
            $doctorId === ''
            || $doctorId === '0'
            || ($doctorId !== '900001' && !in_array($doctorId, $candidateDoctorIds, true))
        ) {
            return subscriptionDevSessionFixtureError(
                'fixture_upgrade_candidate_not_found',
                'stripe upgrade doctor fixture candidate was not found'
            );
        }

        return subscriptionBuildStripeUpgradeDoctorSessionFixture($doctorId, $payload, 'requested');
    }

    foreach ($candidateDoctorIds as $candidateDoctorId) {
        if ($candidateDoctorId === '900001') {
            continue;
        }

        $candidate = subscriptionBuildStripeUpgradeDoctorSessionFixture($candidateDoctorId, $payload, 'auto');
        if ((bool)($candidate['ok'] ?? false)) {
            return $candidate;
        }
    }

    return subscriptionDevSessionFixtureError(
        'fixture_upgrade_candidate_not_found',
        'stripe upgrade doctor fixture candidate was not found'
    );
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

function subscriptionCheckoutErrorStatus(string $code, int $fallback): int
{
    $map = [
        'invalid_checkout_intent_payload' => 400,
        'idempotency_key_invalid' => 422,
        'entity_type_invalid' => 422,
        'entity_id_invalid' => 422,
        'entity_not_found' => 404,
        'entity_not_contractable' => 422,
        'active_subscription_exists' => 409,
        'active_subscription_required' => 409,
        'checkout_intent_type_invalid' => 422,
        'upgrade_current_plan_unsupported' => 409,
        'upgrade_target_plan_not_higher' => 409,
        'upgrade_billing_period_change_not_supported' => 409,
        'upgrade_price_unavailable' => 422,
        'upgrade_adjustment_not_positive' => 409,
        'upgrade_period_invalid' => 409,
        'checkout_intent_already_pending' => 409,
        'request_already_processing' => 409,
        'idempotency_key_reused_with_different_payload' => 409,
        'subscription_checkout_lock_timeout' => 409,
        'checkout_lock_timeout' => 409,
        'plan_not_contractable' => 422,
        'billing_period_invalid' => 422,
        'plan_price_not_configured' => 422,
        'pricing_configuration_conflict' => 422,
        'pricing_source_unavailable' => 503,
        'contract_invalid' => 422,
        'acceptance_source_invalid' => 422,
        'contract_acceptance_create_failed' => 500,
        'checkout_intent_create_failed' => 500,
        'checkout_intent_transaction_failed' => 500,
        'checkout_intent_unavailable' => 500,
        'entity_validation_unavailable' => 500,
    ];

    return $map[$code] ?? $fallback;
}

function subscriptionPaymentIntentErrorStatus(string $code, int $fallback): int
{
    $map = [
        'invalid_payment_intent_payload' => 422,
        'idempotency_key_invalid' => 422,
        'idempotency_key_reused_with_different_payload' => 409,
        'request_already_processing' => 409,
        'checkout_intent_uuid_required' => 422,
        'checkout_intent_not_found' => 404,
        'checkout_intent_not_pending_payment' => 409,
        'checkout_intent_expired' => 409,
        'payment_intent_already_exists' => 409,
        'payment_intent_lock_timeout' => 409,
        'lock_acquisition_failed' => 409,
        'payment_intent_provider_invalid' => 422,
        'payment_intent_invalid_checkout_snapshot' => 422,
        'payment_intent_create_failed' => 500,
        'payment_intent_lookup_failed' => 500,
        'payment_intent_unavailable' => 500,
    ];

    return $map[$code] ?? $fallback;
}

function subscriptionPaymentIntentConfirmMockErrorStatus(string $code, int $fallback): int
{
    $map = [
        'invalid_payment_intent_confirm_payload' => 422,
        'idempotency_key_invalid' => 422,
        'idempotency_key_reused_with_different_payload' => 409,
        'idempotency_key_not_reusable' => 409,
        'idempotency_result_unavailable' => 409,
        'request_already_processing' => 409,
        'payment_intent_not_found' => 404,
        'checkout_intent_not_found' => 404,
        'payment_intent_checkout_mismatch' => 409,
        'payment_intent_provider_invalid' => 422,
        'checkout_intent_not_pending_payment' => 409,
        'payment_intent_not_confirmable' => 409,
        'payment_intent_already_paid' => 409,
        'payment_intent_confirm_lock_timeout' => 409,
        'payment_event_create_failed' => 500,
        'payment_event_lookup_failed' => 500,
        'payment_intent_update_failed' => 500,
        'payment_intent_lookup_failed' => 500,
        'payment_intent_confirm_unavailable' => 500,
    ];

    return $map[$code] ?? $fallback;
}

function subscriptionPaymentIntentActivationErrorStatus(string $code, int $fallback): int
{
    $map = [
        'invalid_payment_intent_activation_payload' => 422,
        'idempotency_key_invalid' => 422,
        'idempotency_key_reused_with_different_payload' => 409,
        'idempotency_key_not_reusable' => 409,
        'idempotency_result_unavailable' => 409,
        'request_already_processing' => 409,
        'payment_intent_not_found' => 404,
        'checkout_intent_not_found' => 404,
        'payment_event_not_found' => 404,
        'contract_acceptance_not_found' => 404,
        'payment_intent_not_paid' => 409,
        'payment_event_not_processed' => 409,
        'checkout_intent_not_pending_payment' => 409,
        'contract_acceptance_not_pending_payment' => 409,
        'payment_intent_checkout_mismatch' => 409,
        'payment_event_payment_intent_mismatch' => 409,
        'checkout_intent_entity_mismatch' => 409,
        'checkout_intent_expired' => 409,
        'invalid_checkout_intent_payload' => 422,
        'active_subscription_exists' => 409,
        'profile_subscription_create_failed' => 500,
        'checkout_activation_transition_failed' => 409,
        'contract_acceptance_subscription_link_failed' => 409,
        'payment_intent_activate_subscription_lock_timeout' => 409,
        'payment_intent_activation_unavailable' => 500,
    ];

    return $map[$code] ?? $fallback;
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

    if (subscriptionProductionEnvironmentDetected()) {
        subscriptionLogDevGuardBlocked('write_context', 'session_scope_production_blocked', [
            'route' => implode('/', subscriptionRelativeSegments()),
        ]);
        return [
            'ok' => false,
            'status' => 403,
            'response' => subscriptionWriteError('forbidden', 'subscription writes require production authorization', 'strict'),
        ];
    }

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
        $fixtureGuard = subscriptionAssertDevFixtureAllowed('dev/session-fixture/concurrency-doctor', $method);
        if (!(bool)($fixtureGuard['ok'] ?? false)) {
            subscriptionRespond(
                subscriptionDevSessionFixtureError((string)$fixtureGuard['code'], (string)$fixtureGuard['message']),
                (int)$fixtureGuard['status']
            );
            return;
        }

        $fixtureResponse = subscriptionCreateConcurrencyDoctorSessionFixture();
        $fixtureStatus = (bool)($fixtureResponse['ok'] ?? false) ? 200 : 409;
        subscriptionRespond($fixtureResponse, $fixtureStatus);
        return;
    }

    if (count($segments) === 3 && $segments[0] === 'dev' && $segments[1] === 'session-fixture' && $segments[2] === 'checkout-doctor') {
        $fixtureGuard = subscriptionAssertDevFixtureAllowed('dev/session-fixture/checkout-doctor', $method);
        if (!(bool)($fixtureGuard['ok'] ?? false)) {
            subscriptionRespond(
                subscriptionDevSessionFixtureError((string)$fixtureGuard['code'], (string)$fixtureGuard['message']),
                (int)$fixtureGuard['status']
            );
            return;
        }

        $fixtureResponse = subscriptionCreateCheckoutDoctorSessionFixture();
        $fixtureStatus = (bool)($fixtureResponse['ok'] ?? false) ? 200 : 409;
        subscriptionRespond($fixtureResponse, $fixtureStatus);
        return;
    }

    if (count($segments) === 3 && $segments[0] === 'dev' && $segments[1] === 'session-fixture' && $segments[2] === 'upgrade-doctor') {
        $fixtureGuard = subscriptionAssertDevFixtureAllowed('dev/session-fixture/upgrade-doctor', $method);
        if (!(bool)($fixtureGuard['ok'] ?? false)) {
            subscriptionRespond(
                subscriptionDevSessionFixtureError((string)$fixtureGuard['code'], (string)$fixtureGuard['message']),
                (int)$fixtureGuard['status']
            );
            return;
        }

        $fixtureResponse = subscriptionCreateUpgradeDoctorSessionFixture();
        $fixtureStatus = (bool)($fixtureResponse['ok'] ?? false) ? 200 : 409;
        subscriptionRespond($fixtureResponse, $fixtureStatus);
        return;
    }

    if (count($segments) === 3 && $segments[0] === 'dev' && $segments[1] === 'session-fixture' && $segments[2] === 'stripe-upgrade-doctor') {
        $fixtureGuard = subscriptionAssertDevFixtureAllowed('dev/session-fixture/stripe-upgrade-doctor', $method);
        if (!(bool)($fixtureGuard['ok'] ?? false)) {
            subscriptionRespond(
                subscriptionDevSessionFixtureError((string)$fixtureGuard['code'], (string)$fixtureGuard['message']),
                (int)$fixtureGuard['status']
            );
            return;
        }

        $fixtureResponse = subscriptionCreateStripeUpgradeDoctorSessionFixture();
        $fixtureStatus = (bool)($fixtureResponse['ok'] ?? false) ? 200 : 409;
        subscriptionRespond($fixtureResponse, $fixtureStatus);
        return;
    }

    if (count($segments) === 3 && $segments[0] === 'dev' && $segments[1] === 'session-fixture' && $segments[2] === 'stripe-payment-intent') {
        $fixtureGuard = subscriptionAssertDevFixtureAllowed('dev/session-fixture/stripe-payment-intent', $method);
        if (!(bool)($fixtureGuard['ok'] ?? false)) {
            subscriptionRespond(
                subscriptionDevSessionFixtureError((string)$fixtureGuard['code'], (string)$fixtureGuard['message']),
                (int)$fixtureGuard['status']
            );
            return;
        }

        $fixtureResponse = subscriptionCreateStripePaymentIntentFixture();
        $fixtureStatus = (bool)($fixtureResponse['ok'] ?? false) ? 200 : 409;
        subscriptionRespond($fixtureResponse, $fixtureStatus);
        return;
    }

    if (count($segments) === 3 && $segments[0] === 'dev' && $segments[1] === 'session-fixture' && $segments[2] === 'alternate-doctor') {
        $fixtureGuard = subscriptionAssertDevFixtureAllowed('dev/session-fixture/alternate-doctor', $method);
        if (!(bool)($fixtureGuard['ok'] ?? false)) {
            subscriptionRespond(
                subscriptionDevSessionFixtureError((string)$fixtureGuard['code'], (string)$fixtureGuard['message']),
                (int)$fixtureGuard['status']
            );
            return;
        }

        $fixtureResponse = subscriptionCreateAlternateDoctorSessionFixture();
        $fixtureStatus = (bool)($fixtureResponse['ok'] ?? false) ? 200 : 409;
        subscriptionRespond($fixtureResponse, $fixtureStatus);
        return;
    }

    if (count($segments) === 2 && $segments[0] === 'dev' && $segments[1] === 'session-fixture') {
        $fixtureGuard = subscriptionAssertDevFixtureAllowed('dev/session-fixture', $method);
        if (!(bool)($fixtureGuard['ok'] ?? false)) {
            subscriptionRespond(
                subscriptionDevSessionFixtureError((string)$fixtureGuard['code'], (string)$fixtureGuard['message']),
                (int)$fixtureGuard['status']
            );
            return;
        }

        subscriptionRespond(subscriptionCreateDevSessionFixture(), 200);
        return;
    }

    if (!empty($segments) && $segments[0] === 'dev') {
        subscriptionRespond(subscriptionDevSessionFixtureError('not_found', 'route not found'), 404);
        return;
    }

    if (count($segments) === 2 && $segments[0] === 'webhooks' && $segments[1] === 'stripe') {
        if ($method !== 'POST') {
            subscriptionRespond(subscriptionStripeWebhookError('method_not_allowed', 'method not allowed'), 405);
            return;
        }

        $rawBody = file_get_contents('php://input');
        if (!is_string($rawBody) || trim($rawBody) === '') {
            subscriptionRespond(
                subscriptionStripeWebhookError('stripe_webhook_payload_empty', 'stripe webhook payload is required'),
                400
            );
            return;
        }

        $secret = subscriptionStripeWebhookSecret();
        $headers = subscriptionHeaders();
        $signatureHeader = subscriptionStripeWebhookSignatureHeader($headers);
        $signature = (new StripeWebhookSignatureVerifier())->verify([
            'raw_body' => $rawBody,
            'signature_header' => $signatureHeader,
            'webhook_secret' => $secret,
            'tolerance_seconds' => 300,
        ]);
        if (!(bool)($signature['ok'] ?? false)) {
            $signatureCode = (string)($signature['code'] ?? 'stripe_webhook_signature_invalid');
            $logContext = $signature['log_context'] ?? [];
            $logContext = is_array($logContext) ? $logContext : [];
            $logContext['route'] = 'webhooks/stripe';
            $logName = 'signature_invalid';
            if ($signatureCode === 'stripe_webhook_secret_missing') {
                $logName = 'secret_missing';
            } elseif ($signatureCode === 'stripe_webhook_signature_missing') {
                $logName = 'signature_missing';
            }
            subscriptionStripeWebhookLog($logName, $logContext);
            subscriptionRespond(
                subscriptionStripeWebhookError(
                    $signatureCode,
                    (string)($signature['message'] ?? 'stripe webhook signature is invalid')
                ),
                (int)($signature['http_status'] ?? 401)
            );
            return;
        }

        subscriptionStripeWebhookLog('signature_verified', [
            'route' => 'webhooks/stripe',
            'provider' => $signature['provider'] ?? 'stripe',
            'timestamp' => $signature['timestamp'] ?? null,
            'signature_version' => $signature['signature_version'] ?? 'v1',
        ]);

        $decoded = json_decode($rawBody, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded) || array_values($decoded) === $decoded) {
            subscriptionRespond(
                subscriptionStripeWebhookError('stripe_webhook_payload_invalid', 'stripe webhook payload is invalid'),
                400
            );
            return;
        }

        $livemodeExpectation = subscriptionStripeWebhookExpectedLivemode();
        if (!(bool)($livemodeExpectation['ok'] ?? false)) {
            $expectationCode = (string)($livemodeExpectation['code'] ?? 'stripe_livemode_expectation_missing');
            $logContext = $livemodeExpectation['log_context'] ?? [];
            $logContext = is_array($logContext) ? $logContext : [];
            $logContext['route'] = 'webhooks/stripe';
            subscriptionStripeWebhookLog('livemode_expectation_invalid', $logContext);
            subscriptionRespond(
                subscriptionStripeWebhookError(
                    $expectationCode,
                    (string)($livemodeExpectation['message'] ?? 'stripe webhook livemode expectation is required')
                ),
                (int)($livemodeExpectation['http_status'] ?? 500)
            );
            return;
        }

        $normalizedPayload = (new StripeWebhookPayloadNormalizer())->normalize($decoded, $rawBody, [
            'expected_livemode' => (bool)$livemodeExpectation['expected_livemode'],
            'expected_currency' => 'MXN',
            'environment' => (string)($livemodeExpectation['environment'] ?? subscriptionStripeWebhookEnvironment()),
        ]);
        if (!(bool)($normalizedPayload['ok'] ?? false)) {
            $normalizerCode = (string)($normalizedPayload['code'] ?? 'stripe_payload_invalid');
            $logContext = $normalizedPayload['log_context'] ?? [];
            $logContext = is_array($logContext) ? $logContext : [];
            $logContext['route'] = 'webhooks/stripe';
            subscriptionStripeWebhookLog('payload_invalid', $logContext);
            subscriptionRespond(
                subscriptionStripeWebhookError(
                    $normalizerCode,
                    (string)($normalizedPayload['message'] ?? 'stripe webhook payload is invalid')
                ),
                (int)($normalizedPayload['http_status'] ?? 400)
            );
            return;
        }

        $input = $normalizedPayload['data'] ?? [];
        $input = is_array($input) ? $input : [];
        subscriptionStripeWebhookLog('received', [
            'provider_event_id' => $input['provider_event_id'] ?? null,
            'provider_event_type' => $input['provider_event_type'] ?? null,
            'livemode' => $input['livemode'] ?? null,
        ]);

        try {
            $pdo = mxmed_pdo();
            $service = new ProcessStripeSubscriptionWebhookService(
                $pdo,
                new SubscriptionCheckoutIntentRepository($pdo),
                new SubscriptionPaymentIntentRepository($pdo),
                new SubscriptionPaymentEventRepository($pdo)
            );
            $result = $service->process($input);
        } catch (Throwable $e) {
            subscriptionStripeWebhookLog('processing_unavailable', [
                'provider_event_id' => $input['provider_event_id'] ?? null,
                'provider_event_type' => $input['provider_event_type'] ?? null,
            ]);
            subscriptionRespond(
                subscriptionStripeWebhookError('stripe_webhook_unavailable', 'stripe webhook is unavailable'),
                500
            );
            return;
        }

        $status = (int)($result['http_status_recommended'] ?? 200);
        if ($status < 100 || $status > 599) {
            $status = 500;
        }
        if ($status >= 400 || (bool)($result['conflict'] ?? false)) {
            subscriptionStripeWebhookLog('processed_with_error', [
                'provider_event_id' => $result['provider_event_id'] ?? null,
                'provider_event_type' => $result['provider_event_type'] ?? null,
                'reason' => $result['reason'] ?? null,
            ]);
        }

        subscriptionRespond(subscriptionStripeWebhookResponse($result), $status);
        return;
    }

    if (!empty($segments) && $segments[0] === 'webhooks') {
        subscriptionRespond(subscriptionStripeWebhookError('not_found', 'route not found'), 404);
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
        count($segments) === 5
        && $segments[0] === 'entities'
        && $segments[3] === 'payment-routes'
        && $segments[4] === 'preview'
    ) {
        if ($method !== 'POST') {
            subscriptionRespond(
                subscriptionPaymentRoutePreviewError('method_not_allowed', 'method not allowed'),
                405
            );
            return;
        }

        $entityType = strtolower(trim((string)$segments[1]));
        $entityId = trim((string)$segments[2]);
        if (!subscriptionValidEntityType($entityType) || !subscriptionValidEntityId($entityId)) {
            subscriptionRespond(
                subscriptionPaymentRoutePreviewError('validation_error', 'invalid entity'),
                422
            );
            return;
        }

        $payloadResult = subscriptionReadJsonPayload();
        if (!(bool)($payloadResult['ok'] ?? false)) {
            $message = 'invalid json payload';
            $payloadResponse = (array)($payloadResult['response'] ?? []);
            if (isset($payloadResponse['error']) && is_array($payloadResponse['error'])) {
                $message = (string)($payloadResponse['error']['message'] ?? $message);
            }
            subscriptionRespond(
                subscriptionPaymentRoutePreviewError('invalid_json', $message),
                (int)($payloadResult['status'] ?? 400)
            );
            return;
        }

        $context = subscriptionResolvePrivateContext($entityType, $entityId);
        if (!(bool)($context['ok'] ?? false)) {
            subscriptionRespond((array)($context['response'] ?? []), (int)($context['status'] ?? 403));
            return;
        }
        $authMode = (string)($context['auth_mode'] ?? 'unknown');

        $payload = (array)($payloadResult['payload'] ?? []);
        $headers = subscriptionHeaders();
        $idempotencyKey = trim((string)(
            $headers['idempotency-key']
            ?? ($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? '')
            ?? ''
        ));
        if ($idempotencyKey === '' && array_key_exists('idempotency_key', $payload)) {
            $idempotencyKey = trim((string)$payload['idempotency_key']);
        }

        $pdo = mxmed_pdo();
        $service = new BuildSubscriptionPaymentRoutePreviewService(
            new CurrentSubscriptionReadModelService(new CurrentSubscriptionRepository($pdo)),
            new SubscriptionPlanPriceResolverService(new SubscriptionPlanPriceRepository($pdo))
        );

        try {
            $preview = $service->build([
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'payload' => $payload,
                'idempotency_key' => $idempotencyKey,
            ]);
        } catch (BuildSubscriptionPaymentRoutePreviewException $e) {
            subscriptionRespond(
                subscriptionPaymentRoutePreviewError($e->errorCode(), $e->getMessage(), $authMode),
                $e->status()
            );
            return;
        } catch (Throwable $e) {
            subscriptionRespond(
                subscriptionPaymentRoutePreviewError('internal_error', 'payment route preview is unavailable', $authMode),
                500
            );
            return;
        }

        subscriptionRespond([
            'ok' => true,
            'data' => $preview,
            'meta' => subscriptionPaymentRoutePreviewMeta($authMode),
        ], 200);
        return;
    }

    if (
        count($segments) === 4
        && $segments[0] === 'entities'
        && $segments[3] === 'payment-routes'
    ) {
        if ($method !== 'POST') {
            subscriptionRespond(
                subscriptionPaymentRouteCreateError('method_not_allowed', 'method not allowed'),
                405
            );
            return;
        }

        $entityType = strtolower(trim((string)$segments[1]));
        $entityId = trim((string)$segments[2]);
        if (!subscriptionValidEntityType($entityType) || !subscriptionValidEntityId($entityId)) {
            subscriptionRespond(
                subscriptionPaymentRouteCreateError('validation_error', 'invalid entity'),
                422
            );
            return;
        }

        $payloadResult = subscriptionReadJsonPayload();
        if (!(bool)($payloadResult['ok'] ?? false)) {
            $message = 'invalid json payload';
            $payloadResponse = (array)($payloadResult['response'] ?? []);
            if (isset($payloadResponse['error']) && is_array($payloadResponse['error'])) {
                $message = (string)($payloadResponse['error']['message'] ?? $message);
            }
            subscriptionRespond(
                subscriptionPaymentRouteCreateError('invalid_json', $message),
                (int)($payloadResult['status'] ?? 400)
            );
            return;
        }

        $payload = (array)($payloadResult['payload'] ?? []);
        $forbiddenFields = subscriptionForbiddenPayloadFields($payload, [
            'payment_route_uuid',
            'status',
            'provider',
            'provider_status',
            'provider_payment_id',
            'provider_checkout_id',
            'payment_intent_uuid',
            'payment_event_uuid',
            'checkout_intent_uuid',
            'subscription_id',
            'activated_at',
            'deleted_at',
            'next_action',
            'next_action_type',
            'next_action_enabled',
        ]);
        if ($forbiddenFields !== []) {
            subscriptionRespond(
                subscriptionPaymentRouteCreateError(
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

        $headers = subscriptionHeaders();
        $idempotencyKey = $headers['idempotency-key'] ?? trim((string)($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? ''));

        $pdo = mxmed_pdo();
        $previewService = new BuildSubscriptionPaymentRoutePreviewService(
            new CurrentSubscriptionReadModelService(new CurrentSubscriptionRepository($pdo)),
            new SubscriptionPlanPriceResolverService(new SubscriptionPlanPriceRepository($pdo))
        );
        $createService = new CreateSubscriptionPaymentRouteService(
            $pdo,
            $previewService,
            new SubscriptionPaymentRouteRepository($pdo),
            new SubscriptionWriteIdempotencyService(new SubscriptionWriteIdempotencyRepository($pdo)),
            new SubscriptionEntityWriteLockService($pdo)
        );

        try {
            $paymentRouteResponse = $createService->createPaymentRoute([
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'payload' => $payload,
                'idempotency_key' => $idempotencyKey,
                'actor_user_id' => (string)($context['actor_user_id'] ?? ''),
                'actor_role' => (string)($context['actor_role'] ?? ''),
                'doctor_id' => (string)($context['doctor_id'] ?? ''),
                'profile_id' => $context['profile_id'] ?? null,
            ]);
        } catch (CreateSubscriptionPaymentRouteException $e) {
            subscriptionRespond(
                subscriptionPaymentRouteCreateError($e->errorCode(), $e->getMessage(), $authMode),
                $e->status()
            );
            return;
        } catch (Throwable $e) {
            subscriptionRespond(
                subscriptionPaymentRouteCreateError('internal_error', 'payment route create is unavailable', $authMode),
                500
            );
            return;
        }

        if (isset($paymentRouteResponse['meta']) && is_array($paymentRouteResponse['meta'])) {
            $paymentRouteResponse['meta']['auth_mode'] = $authMode;
        }
        $isReplay = (bool)($paymentRouteResponse['meta']['idempotent_replay'] ?? false);
        subscriptionRespond($paymentRouteResponse, $isReplay ? 200 : 201);
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
        count($segments) === 4
        && $segments[0] === 'entities'
        && $segments[3] === 'checkout-intents'
    ) {
        if ($method !== 'POST') {
            subscriptionRespond(subscriptionWriteError('method_not_allowed', 'method not allowed'), 405);
            return;
        }

        $entityType = strtolower(trim((string)$segments[1]));
        $entityId = trim((string)$segments[2]);
        if ($entityType !== 'doctor' || !subscriptionValidEntityId($entityId)) {
            subscriptionRespond(subscriptionWriteError('invalid_checkout_intent_payload', 'invalid entity'), 422);
            return;
        }

        $payloadResult = subscriptionReadJsonPayload();
        if (!(bool)($payloadResult['ok'] ?? false)) {
            subscriptionRespond((array)($payloadResult['response'] ?? []), (int)($payloadResult['status'] ?? 400));
            return;
        }

        $payload = (array)($payloadResult['payload'] ?? []);
        $forbiddenFields = subscriptionForbiddenPayloadFields($payload, [
            'amount_cents',
            'currency',
            'price_source',
            'price_version',
            'price_uuid',
            'price',
            'adjustment_amount_cents',
            'current_price_period_cents',
            'target_price_period_cents',
            'price_difference_cents',
            'remaining_days',
            'period_days',
            'pricing_strategy',
            'current_subscription_id',
            'source_subscription_id',
            'next_step',
            'status',
            'subscription_id',
            'profile_subscription_id',
            'contract_acceptance_uuid',
            'checkout_intent_uuid',
            'payment_intent_id',
            'pro' . 'vider_' . 'pay' . 'ment_id',
            'pro' . 'vider',
            'payment',
            'capabilities',
            'starts_at',
            'expires_at',
            'accepted_by_user_id',
            'accepted_by_actor_role',
            'accepted_by_operator_id',
            'ip_address',
            'user_agent',
            'deleted_at',
        ]);
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
        $headers = subscriptionHeaders();
        // Idempotency-Key is required by CreateSubscriptionCheckoutIntentService.
        $idempotencyKey = $headers['idempotency-key'] ?? trim((string)($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? ''));
        $pdo = mxmed_pdo();
        $currentSubscriptionRepository = new CurrentSubscriptionRepository($pdo);
        $idempotencyService = new SubscriptionWriteIdempotencyService(
            new SubscriptionWriteIdempotencyRepository($pdo)
        );
        $checkoutService = new CreateSubscriptionCheckoutIntentService(
            $pdo,
            new SubscriptionEntityResolverService($pdo),
            $currentSubscriptionRepository,
            $idempotencyService,
            new SubscriptionEntityWriteLockService($pdo),
            new SubscriptionPlanPriceResolverService(new SubscriptionPlanPriceRepository($pdo)),
            new CreateSubscriptionPendingPaymentAcceptanceService(
                new SubscriptionContractAcceptanceRepository($pdo)
            ),
            new SubscriptionCheckoutIntentRepository($pdo)
        );

        try {
            $checkoutResponse = $checkoutService->createCheckoutIntent([
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'intent_type' => $payload['intent_type'] ?? null,
                'plan_code' => $payload['plan_code'] ?? ($payload['target_plan_code'] ?? null),
                'billing_period' => $payload['billing_period'] ?? null,
                'contract_version' => $payload['contract_version'] ?? null,
                'contract_hash' => $payload['contract_hash'] ?? null,
                'contract_snapshot_url' => $payload['contract_snapshot_url'] ?? null,
                'contract_title' => $payload['contract_title'] ?? null,
                'source' => $payload['source'] ?? 'checkout_intent',
                'idempotency_key' => $idempotencyKey,
                'actor_user_id' => (string)($context['actor_user_id'] ?? ''),
                'actor_role' => (string)($context['actor_role'] ?? ''),
                'operator_id' => $context['operator_id'] ?? null,
                'doctor_id' => (string)($context['doctor_id'] ?? ''),
                'profile_id' => $context['profile_id'] ?? null,
                'ip_address' => subscriptionRequestIpAddress(),
                'user_agent' => subscriptionRequestUserAgent(),
            ]);
        } catch (CreateSubscriptionCheckoutIntentException $e) {
            $status = subscriptionCheckoutErrorStatus($e->errorCode(), $e->status());
            subscriptionRespond(subscriptionWriteError($e->errorCode(), $e->getMessage(), $authMode), $status);
            return;
        } catch (Throwable $e) {
            subscriptionRespond(
                subscriptionWriteError('checkout_intent_unavailable', 'checkout intent is unavailable', $authMode),
                500
            );
            return;
        }

        $isReplay = (bool)($checkoutResponse['meta']['idempotent_replay'] ?? false);
        subscriptionRespond($checkoutResponse, $isReplay ? 200 : 201);
        return;
    }

    if (
        count($segments) === 8
        && $segments[0] === 'entities'
        && $segments[3] === 'checkout-intents'
        && $segments[5] === 'payment-intents'
        && $segments[7] === 'confirm-mock'
    ) {
        $confirmMockGuard = subscriptionAssertConfirmMockAllowed('confirm-mock', $method);
        if (!(bool)($confirmMockGuard['ok'] ?? false)) {
            subscriptionRespond(
                subscriptionWriteError((string)$confirmMockGuard['code'], (string)$confirmMockGuard['message'], 'mock_policy'),
                (int)$confirmMockGuard['status']
            );
            return;
        }

        $entityType = strtolower(trim((string)$segments[1]));
        $entityId = trim((string)$segments[2]);
        $checkoutIntentUuid = trim((string)$segments[4]);
        $paymentIntentUuid = trim((string)$segments[6]);
        if (
            $entityType !== 'doctor'
            || !subscriptionValidEntityId($entityId)
            || $checkoutIntentUuid === ''
            || $paymentIntentUuid === ''
        ) {
            subscriptionRespond(subscriptionWriteError('invalid_payment_intent_confirm_payload', 'invalid request'), 422);
            return;
        }

        $contentType = strtolower(trim((string)($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '')));
        if ($contentType !== '' && strpos($contentType, 'application/json') === false) {
            subscriptionRespond(subscriptionWriteError('invalid_payload', 'content-type must be application/json'), 400);
            return;
        }

        $rawPayload = file_get_contents('php://input');
        $payload = [];
        if (is_string($rawPayload) && trim($rawPayload) !== '') {
            $decodedJson = json_decode($rawPayload);
            $decodedPayload = json_decode($rawPayload, true);
            if (
                json_last_error() !== JSON_ERROR_NONE
                || !is_array($decodedPayload)
                || !is_object($decodedJson)
            ) {
                subscriptionRespond(subscriptionWriteError('invalid_payload', 'invalid json payload'), 400);
                return;
            }
            $payload = $decodedPayload;
        }

        $forbiddenFields = subscriptionForbiddenPayloadFields($payload, [
            'amount_cents',
            'currency',
            'price_source',
            'price_version',
            'price_uuid',
            'price',
            'status',
            'normalized_status',
            'provider_status',
            'provider_payment_id',
            'provider_checkout_id',
            'provider_event_id',
            'event_type',
            'event_hash',
            'processing_status',
            'checkout_intent_uuid',
            'payment_intent_uuid',
            'payment_intent_id',
            'payment_event_uuid',
            'paid_at',
            'failed_at',
            'cancelled_at',
            'created_at_provider',
            'signature_validated_at',
            'received_at',
            'processed_at',
            'ip_address',
            'user_agent',
            'deleted_at',
        ]);
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
        $headers = subscriptionHeaders();
        $idempotencyKey = trim((string)($headers['idempotency-key'] ?? ($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? '')));
        if ($idempotencyKey === '') {
            subscriptionRespond(
                subscriptionWriteError('idempotency_key_invalid', 'Idempotency-Key is required', $authMode),
                422
            );
            return;
        }

        $provider = (string)($payload['provider'] ?? 'mxmed_mock');
        $mockProviderGuard = subscriptionAssertMockProviderAllowed($provider, 'confirm-mock');
        if (!(bool)($mockProviderGuard['ok'] ?? false)) {
            subscriptionRespond(
                subscriptionWriteError((string)$mockProviderGuard['code'], (string)$mockProviderGuard['message'], $authMode),
                (int)$mockProviderGuard['status']
            );
            return;
        }

        $input = [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'checkout_intent_uuid' => $checkoutIntentUuid,
            'payment_intent_uuid' => $paymentIntentUuid,
            'idempotency_key' => $idempotencyKey,
            'provider' => $provider,
            'source' => $payload['source'] ?? 'payment_intent_confirm_mock_endpoint',
        ];
        if (array_key_exists('notes', $payload)) {
            $input['notes'] = $payload['notes'];
        }

        $pdo = mxmed_pdo();
        $confirmMockService = new ConfirmSubscriptionPaymentIntentMockService(
            $pdo,
            new SubscriptionCheckoutIntentRepository($pdo),
            new SubscriptionPaymentIntentRepository($pdo),
            new SubscriptionPaymentEventRepository($pdo),
            new SubscriptionWriteIdempotencyService(new SubscriptionWriteIdempotencyRepository($pdo)),
            new SubscriptionEntityWriteLockService($pdo)
        );

        try {
            $confirmResponse = $confirmMockService->confirmMock($input);
        } catch (ConfirmSubscriptionPaymentIntentMockException $e) {
            $status = subscriptionPaymentIntentConfirmMockErrorStatus($e->errorCode(), $e->status());
            subscriptionRespond(subscriptionWriteError($e->errorCode(), $e->getMessage(), $authMode), $status);
            return;
        } catch (Throwable $e) {
            subscriptionRespond(
                subscriptionWriteError('payment_intent_confirm_unavailable', 'payment intent confirm is unavailable', $authMode),
                500
            );
            return;
        }

        subscriptionRespond($confirmResponse, 200);
        return;
    }

    if (
        count($segments) === 8
        && $segments[0] === 'entities'
        && $segments[3] === 'checkout-intents'
        && $segments[5] === 'payment-intents'
        && $segments[7] === 'activate-after-payment'
    ) {
        if ($method !== 'POST') {
            subscriptionRespond(subscriptionWriteError('method_not_allowed', 'method not allowed'), 405);
            return;
        }

        $entityType = strtolower(trim((string)$segments[1]));
        $entityId = trim((string)$segments[2]);
        $checkoutIntentUuid = trim((string)$segments[4]);
        $paymentIntentUuid = trim((string)$segments[6]);
        if (
            $entityType !== 'doctor'
            || !subscriptionValidEntityId($entityId)
            || $checkoutIntentUuid === ''
            || $paymentIntentUuid === ''
        ) {
            subscriptionRespond(subscriptionWriteError('invalid_payment_intent_activation_payload', 'invalid request'), 422);
            return;
        }

        $payloadResult = subscriptionReadJsonPayload();
        if (!(bool)($payloadResult['ok'] ?? false)) {
            subscriptionRespond((array)($payloadResult['response'] ?? []), (int)($payloadResult['status'] ?? 400));
            return;
        }

        $payload = (array)($payloadResult['payload'] ?? []);
        $forbiddenFields = subscriptionForbiddenPayloadFields($payload, [
            'status',
            'subscription_id',
            'profile_subscription',
            'checkout_status',
            'payment_status',
            'normalized_status',
            'provider_status',
            'paid_at',
            'processed_at',
            'activated_at',
            'starts_at',
            'expires_at',
            'duration_days',
            'plan_code',
            'billing_period',
            'amount_cents',
            'currency',
            'contract_acceptance_uuid',
            'contract_status',
            'event_type',
            'processing_status',
            'provider_payment_id',
            'provider_checkout_id',
            'provider_event_id',
            'checkout_intent_uuid',
            'payment_intent_uuid',
            'payment_intent_id',
            'payment_event_id',
            'price',
            'price_source',
            'price_uuid',
            'price_version',
            'auto_renew',
            'deleted_at',
        ]);
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

        $paymentEventUuid = trim((string)($payload['payment_event_uuid'] ?? ''));
        if ($paymentEventUuid === '' || strlen($paymentEventUuid) > 36) {
            subscriptionRespond(
                subscriptionWriteError(
                    'invalid_payment_intent_activation_payload',
                    'payment_event_uuid is required'
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
        $headers = subscriptionHeaders();
        $idempotencyKey = trim((string)($headers['idempotency-key'] ?? ($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? '')));
        if ($idempotencyKey === '') {
            subscriptionRespond(
                subscriptionWriteError('idempotency_key_invalid', 'Idempotency-Key is required', $authMode),
                422
            );
            return;
        }

        $input = [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'checkout_intent_uuid' => $checkoutIntentUuid,
            'payment_intent_uuid' => $paymentIntentUuid,
            'payment_event_uuid' => $paymentEventUuid,
            'idempotency_key' => $idempotencyKey,
            'user_id' => (string)($context['actor_user_id'] ?? ''),
            'actor_role' => (string)($context['actor_role'] ?? ''),
        ];

        $pdo = mxmed_pdo();
        $service = new ActivateSubscriptionAfterPaymentService(
            $pdo,
            new SubscriptionWriteIdempotencyService(new SubscriptionWriteIdempotencyRepository($pdo)),
            new SubscriptionEntityWriteLockService($pdo),
            new SubscriptionCheckoutIntentRepository($pdo),
            new SubscriptionPaymentIntentRepository($pdo),
            new SubscriptionPaymentEventRepository($pdo),
            new SubscriptionContractAcceptanceRepository($pdo),
            new ProfileSubscriptionRepository($pdo),
            new CurrentSubscriptionRepository($pdo)
        );

        try {
            $activationResponse = $service->activateAfterPayment($input);
        } catch (ActivateSubscriptionAfterPaymentException $e) {
            $status = subscriptionPaymentIntentActivationErrorStatus($e->errorCode(), $e->status());
            subscriptionRespond(subscriptionWriteError($e->errorCode(), $e->getMessage(), $authMode), $status);
            return;
        } catch (Throwable $e) {
            subscriptionRespond(
                subscriptionWriteError('payment_intent_activation_unavailable', 'payment intent activation is unavailable', $authMode),
                500
            );
            return;
        }

        subscriptionRespond($activationResponse, 200);
        return;
    }

    if (
        count($segments) === 6
        && $segments[0] === 'entities'
        && $segments[3] === 'checkout-intents'
        && $segments[5] === 'payment-intents'
    ) {
        if ($method !== 'POST') {
            subscriptionRespond(subscriptionWriteError('method_not_allowed', 'method not allowed'), 405);
            return;
        }

        $entityType = strtolower(trim((string)$segments[1]));
        $entityId = trim((string)$segments[2]);
        $checkoutIntentUuid = trim((string)$segments[4]);
        if ($entityType !== 'doctor' || !subscriptionValidEntityId($entityId) || $checkoutIntentUuid === '') {
            subscriptionRespond(subscriptionWriteError('invalid_payment_intent_payload', 'invalid request'), 422);
            return;
        }

        $payloadResult = subscriptionReadJsonPayload();
        if (!(bool)($payloadResult['ok'] ?? false)) {
            subscriptionRespond((array)($payloadResult['response'] ?? []), (int)($payloadResult['status'] ?? 400));
            return;
        }

        $payload = (array)($payloadResult['payload'] ?? []);
        $forbiddenFields = subscriptionForbiddenPayloadFields($payload, [
            'amount_cents',
            'currency',
            'price_source',
            'price_version',
            'price_uuid',
            'price',
            'status',
            'normalized_status',
            'provider_payment_id',
            'provider_checkout_id',
            'checkout_intent_uuid',
            'payment_intent_uuid',
            'payment_intent_id',
            'paid_at',
            'failed_at',
            'cancelled_at',
            'created_at_provider',
            'ip_address',
            'user_agent',
            'deleted_at',
        ]);
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
        $headers = subscriptionHeaders();
        $idempotencyKey = trim((string)($headers['idempotency-key'] ?? ($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? '')));
        if ($idempotencyKey === '') {
            subscriptionRespond(
                subscriptionWriteError('idempotency_key_invalid', 'Idempotency-Key is required', $authMode),
                422
            );
            return;
        }

        $provider = (string)($payload['provider'] ?? 'mxmed_mock');
        $mockProviderGuard = subscriptionAssertMockProviderAllowed($provider, 'payment-intents');
        if (!(bool)($mockProviderGuard['ok'] ?? false)) {
            subscriptionRespond(
                subscriptionWriteError((string)$mockProviderGuard['code'], (string)$mockProviderGuard['message'], $authMode),
                (int)$mockProviderGuard['status']
            );
            return;
        }

        $pdo = mxmed_pdo();
        $checkoutIntentRepository = new SubscriptionCheckoutIntentRepository($pdo);
        try {
            $checkoutIntent = $checkoutIntentRepository->findByUuid($checkoutIntentUuid);
        } catch (Throwable $e) {
            subscriptionRespond(
                subscriptionWriteError('payment_intent_unavailable', 'payment intent is unavailable', $authMode),
                500
            );
            return;
        }
        if ($checkoutIntent === null) {
            subscriptionRespond(subscriptionWriteError('checkout_intent_not_found', 'checkout intent was not found', $authMode), 404);
            return;
        }
        if (
            (string)($checkoutIntent['entity_type'] ?? '') !== $entityType
            || (string)($checkoutIntent['entity_id'] ?? '') !== $entityId
        ) {
            subscriptionRespond(subscriptionWriteError('forbidden', 'entity scope mismatch', $authMode), 403);
            return;
        }

        $input = [
            'checkout_intent_uuid' => $checkoutIntentUuid,
            'idempotency_key' => $idempotencyKey,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'actor_user_id' => (string)($context['actor_user_id'] ?? ''),
            'actor_role' => (string)($context['actor_role'] ?? ''),
            'operator_id' => $context['operator_id'] ?? null,
            'doctor_id' => (string)($context['doctor_id'] ?? ''),
            'profile_id' => $context['profile_id'] ?? null,
            'provider' => $provider,
        ];
        foreach (['source', 'notes', 'metadata'] as $key) {
            if (array_key_exists($key, $payload)) {
                $input[$key] = $payload[$key];
            }
        }

        $paymentIntentService = new CreateSubscriptionPaymentIntentService(
            $checkoutIntentRepository,
            new SubscriptionPaymentIntentRepository($pdo),
            new SubscriptionWriteIdempotencyService(new SubscriptionWriteIdempotencyRepository($pdo)),
            new SubscriptionEntityWriteLockService($pdo),
            new SubscriptionPaymentIntentMockProvider(),
            new StripePaymentIntentProviderService()
        );

        try {
            $paymentIntentResponse = $paymentIntentService->createPaymentIntent($input);
        } catch (CreateSubscriptionPaymentIntentException $e) {
            $status = subscriptionPaymentIntentErrorStatus($e->errorCode(), $e->status());
            subscriptionRespond(subscriptionWriteError($e->errorCode(), $e->getMessage(), $authMode), $status);
            return;
        } catch (Throwable $e) {
            subscriptionRespond(
                subscriptionWriteError('payment_intent_unavailable', 'payment intent is unavailable', $authMode),
                500
            );
            return;
        }

        $isReplay = (bool)($paymentIntentResponse['meta']['idempotent_replay'] ?? false);
        subscriptionRespond($paymentIntentResponse, $isReplay ? 200 : 201);
        return;
    }

    if (
        count($segments) === 4
        && $segments[0] === 'entities'
        && $segments[3] === 'payment-activation-state'
    ) {
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

        $checkoutIntentUuid = trim((string)($_GET['checkout_intent_uuid'] ?? ''));
        $paymentIntentUuid = trim((string)($_GET['payment_intent_uuid'] ?? ''));
        $audience = trim((string)($_GET['audience'] ?? 'support'));

        $pdo = mxmed_pdo();
        $service = new BuildSubscriptionPaymentActivationStateService(
            new SubscriptionCheckoutIntentRepository($pdo),
            new SubscriptionPaymentIntentRepository($pdo),
            new SubscriptionPaymentEventRepository($pdo),
            new SubscriptionContractAcceptanceRepository($pdo),
            new CurrentSubscriptionRepository($pdo)
        );

        $activationState = $service->build([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'checkout_intent_uuid' => $checkoutIntentUuid,
            'payment_intent_uuid' => $paymentIntentUuid,
            'audience' => $audience !== '' ? $audience : 'support',
        ]);

        subscriptionRespond([
            'ok' => true,
            'data' => $activationState,
            'meta' => subscriptionMeta($authMode),
        ], 200);
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
