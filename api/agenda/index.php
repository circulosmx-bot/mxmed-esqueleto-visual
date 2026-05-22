<?php
require_once __DIR__ . '/../../modules/agenda/controllers/AppointmentsController.php';
require_once __DIR__ . '/../../modules/agenda/controllers/ConsultoriosController.php';
require_once __DIR__ . '/../../modules/agenda/controllers/GoogleGeocodeController.php';
require_once __DIR__ . '/../../modules/agenda/controllers/AppointmentEventsController.php';
require_once __DIR__ . '/../../modules/agenda/controllers/PatientFlagsController.php';
require_once __DIR__ . '/../../modules/agenda/controllers/PatientBehaviorController.php';
require_once __DIR__ . '/../../modules/agenda/controllers/MedicalGroupsController.php';
require_once __DIR__ . '/../../modules/agenda/controllers/AvailabilityController.php';
require_once __DIR__ . '/../../modules/agenda/controllers/ScheduleController.php';
require_once __DIR__ . '/../../modules/agenda/controllers/AgendaSettingsController.php';
require_once __DIR__ . '/../../modules/agenda/controllers/AppointmentWriteController.php';
require_once __DIR__ . '/../../modules/agenda/controllers/WaitlistController.php';
require_once __DIR__ . '/../../modules/agenda/controllers/OperatorsController.php';
require_once __DIR__ . '/../../modules/agenda/controllers/PublicAppointmentsController.php';
require_once __DIR__ . '/../../modules/agenda/controllers/PublicOtpController.php';
require_once __DIR__ . '/../../modules/agenda/repositories/OperatorsRepository.php';
require_once __DIR__ . '/../_lib/db.php';

use Agenda\Controllers\AppointmentsController;
use Agenda\Controllers\ConsultoriosController;
use Agenda\Controllers\GoogleGeocodeController;
use Agenda\Controllers\AppointmentEventsController;
use Agenda\Controllers\PatientFlagsController;
use Agenda\Controllers\PatientBehaviorController;
use Agenda\Controllers\MedicalGroupsController;
use Agenda\Controllers\AvailabilityController;
use Agenda\Controllers\ScheduleController;
use Agenda\Controllers\AgendaSettingsController;
use Agenda\Controllers\AppointmentWriteController;
use Agenda\Controllers\WaitlistController;
use Agenda\Controllers\OperatorsController;
use Agenda\Controllers\PublicAppointmentsController;
use Agenda\Controllers\PublicOtpController;
use Agenda\Repositories\OperatorsRepository;

header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

set_error_handler(function ($severity, $message, $file, $line) {
    throw new \ErrorException($message, 0, $severity, $file, $line);
});

$qaMode = getenv('QA_MODE') ?: ($_SERVER['HTTP_X_QA_MODE'] ?? '');

function normalize_response($response): array
{
    if (!is_array($response)) {
        $response = [
            'ok' => false,
            'error' => 'db_error',
            'message' => 'database error',
            'data' => null,
            'meta' => (object)[],
        ];
    }

    $defaults = [
        'ok' => null,
        'error' => null,
        'message' => '',
        'data' => null,
        'meta' => (object)[],
    ];
    $response = array_merge($defaults, $response);

    if (!array_key_exists('meta', $response)) {
        $response['meta'] = (object)[];
    } elseif (is_array($response['meta'])) {
        $response['meta'] = (object)$response['meta'];
    } elseif (!is_object($response['meta'])) {
        $response['meta'] = (object)[];
    }

    if (!isset($response['ok']) || !is_bool($response['ok'])) {
        $response['ok'] = ($response['error'] === null);
    }

    if ($response['error'] === null) {
        if (!isset($response['message']) || $response['message'] === null) {
            $response['message'] = '';
        }
    } else {
        if ($response['error'] === 'db_error' && ($response['message'] === '' || $response['message'] === null)) {
            $response['message'] = 'database error';
        }
    }

    return $response;
}

function read_json_body(): array
{
    static $decodedCache = null;
    static $loaded = false;
    if ($loaded) {
        return is_array($decodedCache) ? $decodedCache : [];
    }

    $loaded = true;
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || trim($raw) === '') {
        $decodedCache = [];
        return [];
    }
    $decoded = json_decode($raw, true);
    $decodedCache = is_array($decoded) ? $decoded : [];
    return $decodedCache;
}

function bool_env_flag($value): bool
{
    $raw = strtolower(trim((string)($value ?? '')));
    return in_array($raw, ['1', 'true', 'yes', 'on'], true);
}

function is_public_agenda_route(array $segments): bool
{
    return (($segments[0] ?? '') === 'public');
}

function is_private_agenda_route(array $segments): bool
{
    return in_array(($segments[0] ?? ''), [
        'appointments',
        'patients',
        'consultorios',
        'availability',
        'schedule',
        'settings',
        'waitlist',
        'operators',
        'medical-groups',
        'geocode',
    ], true);
}

function unauthorized_response(string $message = 'unauthorized', array $meta = []): array
{
    return [
        'ok' => false,
        'error' => 'unauthorized',
        'message' => $message,
        'data' => null,
        'meta' => (object)$meta,
    ];
}

function forbidden_response(string $message = 'forbidden', array $meta = []): array
{
    return [
        'ok' => false,
        'error' => 'forbidden',
        'message' => $message,
        'data' => null,
        'meta' => (object)$meta,
    ];
}

function resolveHeaderValue(array $headers, string $name): string
{
    $target = strtolower(trim($name));
    if ($target === '') {
        return '';
    }
    foreach ($headers as $key => $value) {
        if (strtolower(trim((string)$key)) === $target) {
            return trim((string)$value);
        }
    }
    return '';
}

function normalizeAgendaActorRole($raw): string
{
    $value = strtolower(trim((string)($raw ?? '')));
    if ($value === '') {
        return '';
    }
    $value = str_replace([' ', '-'], '_', $value);
    $map = [
        'doctor' => 'doctor',
        'medico' => 'doctor',
        'owner' => 'doctor',
        'operator' => 'operator',
        'operador' => 'operator',
        'assistant' => 'operator',
        'asistente' => 'operator',
        'patient' => 'patient',
        'paciente' => 'patient',
        'call_center' => 'call_center',
        'callcenter' => 'call_center',
        'ai_operator' => 'ai_operator',
        'operator_ia' => 'ai_operator',
        'operador_ia' => 'ai_operator',
        'system' => 'system',
        'sistema' => 'system',
    ];
    return $map[$value] ?? '';
}

function resolveAgendaActorRole(array $query = [], array $body = []): array
{
    $headers = function_exists('getallheaders') ? (array)getallheaders() : [];
    $headerActorRole = normalizeAgendaActorRole(resolveHeaderValue($headers, 'X-Actor-Role'));
    if ($headerActorRole !== '') {
        return ['role' => $headerActorRole, 'source' => 'header:x-actor-role'];
    }

    $headerUserRole = normalizeAgendaActorRole(resolveHeaderValue($headers, 'X-User-Role'));
    if ($headerUserRole !== '') {
        return ['role' => $headerUserRole, 'source' => 'header:x-user-role'];
    }

    $sessionRole = normalizeAgendaActorRole(
        $_SESSION['user_role']
            ?? $_SESSION['role']
            ?? $_SESSION['mxmed_user_role']
            ?? ''
    );
    if ($sessionRole !== '') {
        return ['role' => $sessionRole, 'source' => 'session'];
    }

    $bodyRole = normalizeAgendaActorRole($body['actor_role'] ?? ($body['created_by_role'] ?? ''));
    if ($bodyRole !== '') {
        return ['role' => $bodyRole, 'source' => 'body'];
    }

    $queryRole = normalizeAgendaActorRole($query['actor_role'] ?? '');
    if ($queryRole !== '') {
        return ['role' => $queryRole, 'source' => 'query'];
    }

    return ['role' => 'doctor', 'source' => 'fallback'];
}

function resolveAgendaActorContext(array $segments, array $query = [], array $actorRoleContext = []): array
{
    $modeRaw = strtolower(trim((string)(getenv('MXMED_AGENDA_AUTH_MODE') ?: '')));
    $strictMode = bool_env_flag(getenv('MXMED_AGENDA_AUTH_REQUIRED'))
        || in_array($modeRaw, ['strict', 'enforce'], true);
    $compatMode = !$strictMode;

    $headers = function_exists('getallheaders') ? (array)getallheaders() : [];
    $headerUserId = trim((string)($headers['X-User-Id'] ?? $headers['x-user-id'] ?? ''));

    $sessionUserId = trim((string)(
        $_SESSION['user_id']
        ?? $_SESSION['mxmed_user_id']
        ?? $_SESSION['auth_user_id']
        ?? ''
    ));
    $sessionDoctorId = trim((string)(
        $_SESSION['doctor_id']
        ?? $_SESSION['active_doctor_id']
        ?? $_SESSION['mxmed_doctor_id']
        ?? ''
    ));

    $queryDoctorId = trim((string)($query['doctor_id'] ?? ''));
    $contextUserId = $sessionUserId !== '' ? $sessionUserId : $headerUserId;
    $contextDoctorId = $sessionDoctorId !== '' ? $sessionDoctorId : $queryDoctorId;

    if ($contextUserId === '' && $strictMode) {
        return [
            'ok' => false,
            'response' => unauthorized_response('authentication required', [
                'auth_mode' => 'strict',
                'route' => ($segments[0] ?? ''),
            ]),
        ];
    }

    if ($contextDoctorId === '' && $strictMode) {
        return [
            'ok' => false,
            'response' => forbidden_response('doctor scope required', [
                'auth_mode' => 'strict',
                'route' => ($segments[0] ?? ''),
            ]),
        ];
    }

    $warnings = [];
    if ($sessionDoctorId !== '' && $queryDoctorId !== '' && $sessionDoctorId !== $queryDoctorId) {
        if ($strictMode) {
            return [
                'ok' => false,
                'response' => forbidden_response('doctor scope mismatch', [
                    'auth_mode' => 'strict',
                    'doctor_id_session' => $sessionDoctorId,
                    'doctor_id_requested' => $queryDoctorId,
                ]),
            ];
        }
        $warnings[] = [
            'type' => 'doctor_scope_mismatch',
            'doctor_id_session' => $sessionDoctorId,
            'doctor_id_requested' => $queryDoctorId,
        ];
    }

    if ($contextUserId === '' && $compatMode) {
        $contextUserId = 'compat_dev';
    }

    return [
        'ok' => true,
        'context' => [
            'mode' => $compatMode ? 'compat' : 'strict',
            'strict' => $strictMode,
            'compat' => $compatMode,
            'user_id' => $contextUserId,
            'doctor_id' => $contextDoctorId,
            'actor_role' => normalizeAgendaActorRole($actorRoleContext['role'] ?? '') ?: 'doctor',
            'actor_role_source' => trim((string)($actorRoleContext['source'] ?? 'fallback')),
            'warnings' => $warnings,
        ],
    ];
}

function resolveAgendaQaMode(): string
{
    $headers = function_exists('getallheaders') ? (array)getallheaders() : [];
    $qaHeader = resolveHeaderValue($headers, 'X-QA-Mode');
    if ($qaHeader !== '') {
        return strtolower(trim($qaHeader));
    }

    $qaEnv = strtolower(trim((string)(getenv('QA_MODE') ?: '')));
    return $qaEnv;
}

function isAgendaQaActorOverrideAllowed(): bool
{
    $qaMode = resolveAgendaQaMode();
    if ($qaMode === '') {
        return false;
    }
    if (in_array($qaMode, ['0', 'false', 'off', 'disabled', 'none'], true)) {
        return false;
    }
    return true;
}

function mapAgendaActorRoleSourceToAuthSource(string $roleSource): string
{
    $value = strtolower(trim($roleSource));
    if ($value === '') {
        return 'compat';
    }
    if (str_starts_with($value, 'header:')) {
        return 'header';
    }
    if (in_array($value, ['session', 'body', 'query'], true)) {
        return $value;
    }
    return 'compat';
}

function resolveAgendaChannelOriginFallback(string $actorRole): string
{
    $role = normalizeAgendaActorRole($actorRole);
    if ($role === 'patient') {
        return 'public_profile';
    }
    if ($role === 'call_center') {
        return 'call_center';
    }
    if ($role === 'ai_operator') {
        return 'ai_operator';
    }
    if ($role === 'system') {
        return 'system';
    }
    if ($role === 'operator') {
        return 'operator';
    }
    return 'doctor';
}

function resolveEffectiveAgendaActor(array $segments, string $method, array $query, array $body): array
{
    $actorRoleContext = resolveAgendaActorRole($query, $body);

    if (is_public_agenda_route($segments)) {
        $headers = function_exists('getallheaders') ? (array)getallheaders() : [];
        $actorRole = normalizeAgendaActorRole($actorRoleContext['role'] ?? '') ?: 'patient';
        $actorRoleSource = trim((string)($actorRoleContext['source'] ?? 'fallback'));
        $authSource = 'public';

        $operatorId = trim((string)(
            $body['operator_id']
            ?? $query['operator_id']
            ?? resolveHeaderValue($headers, 'X-Operator-Id')
            ?? ''
        ));

        $actorId = trim((string)(
            $body['actor_id']
            ?? $body['created_by_id']
            ?? $query['actor_id']
            ?? $query['created_by_id']
            ?? resolveHeaderValue($headers, 'X-Actor-Id')
            ?? resolveHeaderValue($headers, 'X-User-Id')
            ?? ''
        ));

        $doctorId = trim((string)(
            $query['doctor_id']
            ?? $body['doctor_id']
            ?? $_SESSION['doctor_id']
            ?? $_SESSION['active_doctor_id']
            ?? $_SESSION['mxmed_doctor_id']
            ?? ''
        ));

        $channelOrigin = trim((string)($body['channel_origin'] ?? ($query['channel_origin'] ?? '')));
        if ($channelOrigin === '') {
            $channelOrigin = resolveAgendaChannelOriginFallback($actorRole);
        }

        return [
            'ok' => true,
            'context' => [
                'actor_role' => $actorRole,
                'actor_role_source' => $actorRoleSource,
                'actor_id' => $actorId,
                'doctor_id' => $doctorId,
                'operator_id' => $operatorId !== '' ? $operatorId : null,
                'channel_origin' => $channelOrigin,
                'auth_source' => $authSource,
                'auth_mode' => 'public_flow',
                'is_authoritative' => false,
                'mode' => 'public_flow',
                'strict' => false,
                'compat' => false,
                'user_id' => $actorId,
                'warnings' => [],
            ],
            'role_context' => $actorRoleContext,
        ];
    }

    $contextResult = resolveAgendaActorContext($segments, $query, $actorRoleContext);
    if (!(bool)($contextResult['ok'] ?? false)) {
        return [
            'ok' => false,
            'response' => $contextResult['response'] ?? forbidden_response('forbidden'),
            'context' => [],
            'role_context' => $actorRoleContext,
        ];
    }

    $context = (array)($contextResult['context'] ?? []);
    $headers = function_exists('getallheaders') ? (array)getallheaders() : [];

    $actorRole = normalizeAgendaActorRole($context['actor_role'] ?? '') ?: 'doctor';
    $actorRoleSource = trim((string)($context['actor_role_source'] ?? ($actorRoleContext['source'] ?? 'fallback')));
    $authSource = mapAgendaActorRoleSourceToAuthSource($actorRoleSource);

    $strict = (bool)($context['strict'] ?? false);
    $compat = (bool)($context['compat'] ?? false);
    $qaOverride = !$strict && isAgendaQaActorOverrideAllowed() && in_array($authSource, ['header', 'query', 'body'], true);
    $authMode = $strict ? 'strict' : ($qaOverride ? 'qa_override' : 'compat');

    $sessionUserId = trim((string)(
        $_SESSION['user_id']
        ?? $_SESSION['mxmed_user_id']
        ?? $_SESSION['auth_user_id']
        ?? ''
    ));
    $isAuthoritative = $strict && $authSource === 'session' && $sessionUserId !== '';

    $doctorId = trim((string)(
        $context['doctor_id']
        ?? $query['doctor_id']
        ?? $body['doctor_id']
        ?? ''
    ));
    $userId = trim((string)($context['user_id'] ?? ''));

    $actorId = trim((string)(
        $body['actor_id']
        ?? $body['created_by_id']
        ?? $query['actor_id']
        ?? $query['created_by_id']
        ?? resolveHeaderValue($headers, 'X-Actor-Id')
        ?? ''
    ));
    if ($actorId === '') {
        $actorId = trim((string)(
            $userId
            ?: resolveHeaderValue($headers, 'X-User-Id')
            ?: $doctorId
        ));
    }

    $operatorId = trim((string)(
        $body['operator_id']
        ?? $query['operator_id']
        ?? resolveHeaderValue($headers, 'X-Operator-Id')
        ?? ''
    ));

    $channelOrigin = trim((string)($body['channel_origin'] ?? ($query['channel_origin'] ?? '')));
    if ($channelOrigin === '') {
        $channelOrigin = resolveAgendaChannelOriginFallback($actorRole);
    }

    $context['actor_role'] = $actorRole;
    $context['actor_role_source'] = $actorRoleSource;
    $context['actor_id'] = $actorId;
    $context['doctor_id'] = $doctorId;
    $context['operator_id'] = $operatorId !== '' ? $operatorId : null;
    $context['channel_origin'] = $channelOrigin;
    $context['auth_source'] = $authSource;
    $context['auth_mode'] = $authMode;
    $context['is_authoritative'] = $isAuthoritative;
    $context['mode'] = trim((string)($context['mode'] ?? ($compat ? 'compat' : 'strict')));
    $context['strict'] = $strict;
    $context['compat'] = $compat;
    $context['user_id'] = $userId;
    $context['warnings'] = is_array($context['warnings'] ?? null) ? $context['warnings'] : [];

    return [
        'ok' => true,
        'context' => $context,
        'role_context' => $actorRoleContext,
    ];
}

function appendAgendaWarning(array $context, string $warningType, array $extra = []): array
{
    $warnings = is_array($context['warnings'] ?? null) ? $context['warnings'] : [];
    $entry = array_merge(['type' => $warningType], $extra);
    $warnings[] = $entry;
    $context['warnings'] = $warnings;
    return $context;
}

function resolveAgendaOperatorIdentity(array $actorContext): array
{
    $context = $actorContext;
    $role = normalizeAgendaActorRole($context['actor_role'] ?? '');
    if ($role !== 'operator') {
        $context['operator_identity_checked'] = false;
        $context['operator_identity_found'] = false;
        $context['operator_status'] = null;
        $context['operator_is_active'] = null;
        $context['operator_identity_warning'] = '';
        return $context;
    }

    $context['operator_identity_checked'] = true;
    $context['operator_identity_found'] = false;
    $context['operator_status'] = null;
    $context['operator_is_active'] = null;
    $context['operator_identity_warning'] = '';

    $operatorId = trim((string)($context['operator_id'] ?? ''));
    if ($operatorId === '') {
        $context['operator_identity_warning'] = 'operator_id_missing';
        return appendAgendaWarning($context, 'operator_id_missing');
    }

    try {
        $pdo = mxmed_pdo();
        $repository = new OperatorsRepository($pdo);
        $identity = $repository->findOperatorIdentity(
            trim((string)($context['doctor_id'] ?? '')),
            $operatorId
        );
    } catch (\Throwable $e) {
        $context['operator_identity_warning'] = 'operator_identity_db_not_ready';
        return appendAgendaWarning($context, 'operator_identity_db_not_ready');
    }

    $context['operator_identity_found'] = (bool)($identity['found'] ?? false);
    $context['operator_status'] = trim((string)($identity['status'] ?? '')) ?: null;
    $context['operator_is_active'] = (bool)($identity['is_active'] ?? false);
    if (!$context['operator_identity_found']) {
        $context['operator_identity_warning'] = 'operator_not_found';
        return appendAgendaWarning($context, 'operator_not_found', [
            'operator_id' => $operatorId,
        ]);
    }

    $doctorMatch = $identity['doctor_match'] ?? null;
    if ($doctorMatch === false) {
        $context['operator_identity_warning'] = 'operator_doctor_mismatch';
        return appendAgendaWarning($context, 'operator_doctor_mismatch', [
            'operator_id' => trim((string)($identity['operator_id'] ?? $operatorId)),
            'doctor_id_operator' => trim((string)($identity['doctor_id'] ?? '')),
            'doctor_id_context' => trim((string)($context['doctor_id'] ?? '')),
        ]);
    }

    if (!$context['operator_is_active']) {
        $context['operator_identity_warning'] = 'operator_not_active';
        return appendAgendaWarning($context, 'operator_not_active', [
            'operator_id' => trim((string)($identity['operator_id'] ?? $operatorId)),
            'status' => trim((string)($identity['status'] ?? '')),
        ]);
    }

    $context['operator_identity_warning'] = 'operator_identity_valid';
    return appendAgendaWarning($context, 'operator_identity_valid', [
        'operator_id' => trim((string)($identity['operator_id'] ?? $operatorId)),
        'status' => trim((string)($identity['status'] ?? '')),
    ]);
}

function isAgendaConfigRouteForOperator(array $segments, string $method): bool
{
    $resource = strtolower(trim((string)($segments[0] ?? '')));
    if ($resource === '') {
        return false;
    }
    $verb = strtoupper(trim((string)$method));

    if ($resource === 'settings') {
        return in_array($verb, ['GET', 'PUT'], true);
    }
    if ($resource === 'schedule') {
        return in_array($verb, ['GET', 'PUT'], true);
    }
    if ($resource === 'consultorios') {
        return $verb === 'PUT';
    }
    if ($resource === 'geocode') {
        $sub = strtolower(trim((string)($segments[1] ?? '')));
        if ($sub === 'google' && $verb === 'POST') {
            return true;
        }
        if ($sub === 'google-js-config' && $verb === 'GET') {
            return true;
        }
    }

    return false;
}

function apply_actor_context($controller, array $actorContext): void
{
    if (is_object($controller) && method_exists($controller, 'setActorContext')) {
        $controller->setActorContext($actorContext);
    }
}

try {
    $path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    $script = trim($_SERVER['SCRIPT_NAME'] ?? '', '/');
    $relative = $path;
    if ($script !== '' && str_starts_with($path, $script)) {
        $relative = substr($path, strlen($script));
    } elseif (str_starts_with($path, 'api/agenda')) {
        $relative = substr($path, strlen('api/agenda'));
    }
    $segments = array_values(array_filter(explode('/', trim($relative, '/'))));
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    $actorContext = null;
    $actorRoleContext = ['role' => 'doctor', 'source' => 'fallback'];
    if (is_private_agenda_route($segments) && !is_public_agenda_route($segments)) {
        $payloadForRole = read_json_body();
        $effectiveActorResult = resolveEffectiveAgendaActor($segments, (string)$method, $_GET, $payloadForRole);
        $contextResult = [
            'ok' => (bool)($effectiveActorResult['ok'] ?? false),
            'response' => $effectiveActorResult['response'] ?? null,
            'context' => $effectiveActorResult['context'] ?? [],
        ];
        $actorRoleContext = (array)($effectiveActorResult['role_context'] ?? $actorRoleContext);
        if (!$contextResult['ok']) {
            $response = normalize_response($contextResult['response']);
            $errorCode = (string)($response['error'] ?? '');
            $status = $errorCode === 'unauthorized' ? 401 : 403;
            http_response_code($status);
            $json = json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($json === false) {
                $json = json_encode([
                    'ok' => false,
                    'error' => 'db_error',
                    'message' => 'database error',
                    'data' => null,
                    'meta' => (object)[],
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
            echo $json;
            exit;
        }
        $actorContext = (array)($contextResult['context'] ?? []);
        $actorContext = resolveAgendaOperatorIdentity($actorContext);

        if (($segments[0] ?? '') === 'operators' && (($actorContext['actor_role'] ?? '') === 'operator')) {
            $response = normalize_response(forbidden_response('forbidden for actor role', [
                'actor_role' => 'operator',
                'actor_role_source' => trim((string)($actorContext['actor_role_source'] ?? ($actorRoleContext['source'] ?? ''))),
                'route' => 'operators',
            ]));
            http_response_code(403);
            $json = json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($json === false) {
                $json = json_encode([
                    'ok' => false,
                    'error' => 'db_error',
                    'message' => 'database error',
                    'data' => null,
                    'meta' => (object)[],
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
            echo $json;
            exit;
        }

        if ((($actorContext['actor_role'] ?? '') === 'operator') && isAgendaConfigRouteForOperator($segments, $method)) {
            $response = normalize_response(forbidden_response('forbidden for actor role', [
                'actor_role' => 'operator',
                'actor_role_source' => trim((string)($actorContext['actor_role_source'] ?? ($actorRoleContext['source'] ?? ''))),
                'route' => strtolower(trim((string)($segments[0] ?? ''))),
                'method' => strtoupper(trim((string)$method)),
            ]));
            http_response_code(403);
            $json = json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($json === false) {
                $json = json_encode([
                    'ok' => false,
                    'error' => 'db_error',
                    'message' => 'database error',
                    'data' => null,
                    'meta' => (object)[],
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
            echo $json;
            exit;
        }
    }

    $controller = new AppointmentsController();
    if (is_array($actorContext)) {
        apply_actor_context($controller, $actorContext);
    }
    $response = [
        'ok' => false,
        'error' => 'not_implemented',
        'message' => 'Route not implemented',
        'data' => null,
        'meta' => (object)[],
    ];

    switch ($segments[0] ?? '') {
        case 'appointments':
            if ($method === 'POST' && !isset($segments[1])) {
                $writes = new AppointmentWriteController();
                if (is_array($actorContext)) {
                    apply_actor_context($writes, $actorContext);
                }
                $response = $writes->create();
                break;
            }
            if (isset($segments[1]) && $segments[1] !== '') {
                $sub = $segments[2] ?? '';
                if ($method === 'PATCH' && $sub === 'reschedule') {
                    $writes = new AppointmentWriteController();
                    if (is_array($actorContext)) {
                        apply_actor_context($writes, $actorContext);
                    }
                    $response = $writes->reschedule($segments[1]);
                    break;
                }
                if ($method === 'POST' && $sub === 'cancel') {
                    $writes = new AppointmentWriteController();
                    if (is_array($actorContext)) {
                        apply_actor_context($writes, $actorContext);
                    }
                    $response = $writes->cancel($segments[1]);
                    break;
                }
                if ($method === 'POST' && ($sub === 'no_show' || $sub === 'no-show')) {
                    $writes = new AppointmentWriteController();
                    if (is_array($actorContext)) {
                        apply_actor_context($writes, $actorContext);
                    }
                    $response = $writes->noShow($segments[1]);
                    break;
                }
                if ($sub === '') {
                    $response = $controller->show($segments[1]);
                } elseif ($sub === 'events') {
                    $events = new AppointmentEventsController();
                    if (is_array($actorContext)) {
                        apply_actor_context($events, $actorContext);
                    }
                    $response = $events->index($segments[1], $_GET);
                } else {
                    $response = [
                        'ok' => false,
                        'error' => 'not_found',
                        'message' => 'route not found',
                        'data' => null,
                        'meta' => (object)[],
                    ];
                }
            } else {
                $response = $controller->index($_GET);
            }
            break;
        case 'patients':
            if (isset($segments[1]) && $segments[1] !== '' && ($segments[2] ?? '') === 'flags') {
                $flags = new PatientFlagsController();
                if (is_array($actorContext)) {
                    apply_actor_context($flags, $actorContext);
                }
                $response = $flags->index($segments[1], $_GET);
            } elseif (isset($segments[1]) && $segments[1] !== '' && ($segments[2] ?? '') === 'behavior' && $method === 'GET') {
                $behavior = new PatientBehaviorController();
                if (is_array($actorContext)) {
                    apply_actor_context($behavior, $actorContext);
                }
                $response = $behavior->show($segments[1], $_GET);
            } else {
                $response = [
                    'ok' => false,
                    'error' => 'not_found',
                    'message' => 'route not found',
                    'data' => null,
                    'meta' => (object)[],
                ];
            }
            break;
        case 'consultorios':
            $consultorios = new ConsultoriosController();
            if (is_array($actorContext)) {
                apply_actor_context($consultorios, $actorContext);
            }
            if ($method === 'GET') {
                $response = $consultorios->index($_GET);
            } elseif ($method === 'PUT') {
                $response = $consultorios->update(read_json_body());
            } else {
                $response = [
                    'ok' => false,
                    'error' => 'not_found',
                    'message' => 'route not found',
                    'data' => null,
                    'meta' => (object)[],
                ];
            }
            break;
        case 'geocode':
            $geocode = new GoogleGeocodeController();
            if (is_array($actorContext)) {
                apply_actor_context($geocode, $actorContext);
            }
            if ($method === 'POST' && ($segments[1] ?? '') === 'google') {
                $response = $geocode->search(read_json_body());
            } elseif ($method === 'GET' && ($segments[1] ?? '') === 'google-js-config') {
                $response = $geocode->mapsJsConfig($_GET);
            } else {
                $response = [
                    'ok' => false,
                    'error' => 'not_found',
                    'message' => 'route not found',
                    'data' => null,
                    'meta' => (object)[],
                ];
            }
            break;
        case 'availability':
            $availability = new AvailabilityController();
            if (is_array($actorContext)) {
                apply_actor_context($availability, $actorContext);
            }
            $response = $availability->index($_GET);
            break;
        case 'schedule':
            $schedule = new ScheduleController();
            if (is_array($actorContext)) {
                apply_actor_context($schedule, $actorContext);
            }
            if ($method === 'GET') {
                $response = $schedule->index($_GET);
            } elseif ($method === 'PUT') {
                $response = $schedule->update(read_json_body());
            } else {
                $response = [
                    'ok' => false,
                    'error' => 'not_found',
                    'message' => 'route not found',
                    'data' => null,
                    'meta' => (object)[],
                ];
            }
            break;
        case 'settings':
            $settings = new AgendaSettingsController();
            if (is_array($actorContext)) {
                apply_actor_context($settings, $actorContext);
            }
            if ($method === 'GET') {
                $response = $settings->index($_GET);
            } elseif ($method === 'PUT') {
                $response = $settings->update(read_json_body());
            } else {
                $response = [
                    'ok' => false,
                    'error' => 'not_found',
                    'message' => 'route not found',
                    'data' => null,
                    'meta' => (object)[],
                ];
            }
            break;
        case 'public':
            if ($method === 'GET' && ($segments[1] ?? '') === 'availability' && !isset($segments[2])) {
                $availability = new AvailabilityController();
                $response = $availability->publicAvailability($_GET);
            } elseif ($method === 'POST' && ($segments[1] ?? '') === 'otp' && ($segments[2] ?? '') === 'request' && !isset($segments[3])) {
                $publicOtp = new PublicOtpController();
                $response = $publicOtp->request(read_json_body());
            } elseif ($method === 'POST' && ($segments[1] ?? '') === 'otp' && ($segments[2] ?? '') === 'verify' && !isset($segments[3])) {
                $publicOtp = new PublicOtpController();
                $response = $publicOtp->verify(read_json_body());
            } elseif ($method === 'POST' && ($segments[1] ?? '') === 'maintenance' && ($segments[2] ?? '') === 'expire' && !isset($segments[3])) {
                $publicAppointments = new PublicAppointmentsController();
                $response = $publicAppointments->expireReservations(read_json_body());
            } elseif ($method === 'POST' && ($segments[1] ?? '') === 'appointments' && ($segments[2] ?? '') === 'reserve' && !isset($segments[3])) {
                $publicAppointments = new PublicAppointmentsController();
                $response = $publicAppointments->reserve(read_json_body());
            } elseif ($method === 'POST' && ($segments[1] ?? '') === 'appointments' && ($segments[2] ?? '') === 'confirm' && !isset($segments[3])) {
                $publicAppointments = new PublicAppointmentsController();
                $response = $publicAppointments->confirm(read_json_body());
            } elseif ($method === 'POST' && ($segments[1] ?? '') === 'appointments' && ($segments[2] ?? '') === 'cancel' && !isset($segments[3])) {
                $publicAppointments = new PublicAppointmentsController();
                $response = $publicAppointments->cancel(read_json_body());
            } elseif ($method === 'POST' && ($segments[1] ?? '') === 'appointments' && ($segments[2] ?? '') === 'request' && !isset($segments[3])) {
                $publicAppointments = new PublicAppointmentsController();
                $response = $publicAppointments->request(read_json_body());
            } elseif ($method === 'POST' && ($segments[1] ?? '') === 'appointments' && ($segments[2] ?? '') === 'verify' && !isset($segments[3])) {
                $publicAppointments = new PublicAppointmentsController();
                $response = $publicAppointments->verify(read_json_body());
            } else {
                $response = [
                    'ok' => false,
                    'error' => 'not_found',
                    'message' => 'route not found',
                    'data' => null,
                    'meta' => (object)[],
                ];
            }
            break;
        case 'medical-groups':
            $medicalGroups = new MedicalGroupsController();
            if (is_array($actorContext)) {
                apply_actor_context($medicalGroups, $actorContext);
            }
            if ($method === 'GET' && ($segments[1] ?? '') === 'search') {
                $response = $medicalGroups->search($_GET);
            } elseif ($method === 'POST' && !isset($segments[1])) {
                $response = $medicalGroups->create(read_json_body());
            } elseif ($method === 'GET' && ($segments[1] ?? '') === 'pending') {
                $response = $medicalGroups->pending($_GET);
            } elseif ($method === 'POST' && isset($segments[1]) && ($segments[2] ?? '') === 'join') {
                $response = $medicalGroups->join((string)$segments[1], read_json_body());
            } elseif ($method === 'POST' && isset($segments[1]) && ($segments[2] ?? '') === 'approve') {
                $response = $medicalGroups->approve((string)$segments[1], read_json_body());
            } elseif ($method === 'POST' && isset($segments[1]) && ($segments[2] ?? '') === 'reject') {
                $response = $medicalGroups->reject((string)$segments[1], read_json_body());
            } elseif ($method === 'POST' && isset($segments[1]) && ($segments[2] ?? '') === 'merge') {
                $response = $medicalGroups->merge((string)$segments[1], read_json_body());
            } else {
                $response = [
                    'ok' => false,
                    'error' => 'not_found',
                    'message' => 'route not found',
                    'data' => null,
                    'meta' => (object)[],
                ];
            }
            break;
        case 'waitlist':
            $waitlist = new WaitlistController();
            if (is_array($actorContext)) {
                apply_actor_context($waitlist, $actorContext);
            }
            $entryId = $segments[1] ?? '';
            $sub = $segments[2] ?? '';
            if ($method === 'GET' && !$entryId) {
                $response = $waitlist->index($_GET);
            } elseif ($method === 'POST' && !$entryId) {
                $response = $waitlist->store();
            } elseif ($method === 'PATCH' && $entryId && $sub === '') {
                $response = $waitlist->update($entryId);
            } elseif ($method === 'POST' && $entryId && $sub === 'assign') {
                $response = $waitlist->assign($entryId);
            } else {
                $response = [
                    'ok' => false,
                    'error' => 'not_found',
                    'message' => 'route not found',
                    'data' => null,
                    'meta' => (object)[],
                ];
            }
            break;
        case 'operators':
            $operators = new OperatorsController();
            if (is_array($actorContext)) {
                apply_actor_context($operators, $actorContext);
            }
            if ($method === 'GET' && !isset($segments[1])) {
                $response = $operators->index($_GET);
            } elseif ($method === 'POST' && !isset($segments[1])) {
                $response = $operators->store(read_json_body());
            } elseif ($method === 'POST' && ($segments[1] ?? '') === 'migration' && ($segments[2] ?? '') === 'preview') {
                $response = $operators->migrationPreview(read_json_body());
            } elseif ($method === 'POST' && ($segments[1] ?? '') === 'migration' && ($segments[2] ?? '') === 'apply') {
                $response = $operators->migrationApply(read_json_body());
            } elseif ($method === 'PATCH' && isset($segments[1]) && ($segments[2] ?? '') === 'pause') {
                $response = $operators->pause((string)$segments[1], read_json_body());
            } elseif ($method === 'PATCH' && isset($segments[1]) && ($segments[2] ?? '') === 'reactivate') {
                $response = $operators->reactivate((string)$segments[1], read_json_body());
            } elseif ($method === 'PATCH' && isset($segments[1]) && ($segments[2] ?? '') === 'archive') {
                $response = $operators->archive((string)$segments[1], read_json_body());
            } elseif ($method === 'PATCH' && isset($segments[1]) && ($segments[2] ?? '') === 'restore') {
                $response = $operators->restore((string)$segments[1], read_json_body());
            } else {
                $response = [
                    'ok' => false,
                    'error' => 'not_found',
                    'message' => 'route not found',
                    'data' => null,
                    'meta' => (object)[],
                ];
            }
            break;
    }
} catch (\Throwable $e) {
    $response = [
        'ok' => false,
        'error' => 'db_error',
        'message' => 'database error',
        'data' => null,
        'meta' => (object)[],
    ];
}

    $response = normalize_response($response);

    if ($qaMode === 'not_ready'
        && isset($response['error'], $response['message'])
        && $response['error'] === 'db_not_ready') {
        $msg = (string)$response['message'];
        if (stripos($msg, 'sqlstate') !== false || stripos($msg, 'connection refused') !== false) {
            if (in_array('events', $segments ?? [], true)) {
                $response['message'] = 'appointment events not ready';
            } elseif (in_array('flags', $segments ?? [], true)) {
                $response['message'] = 'patient flags not ready';
            } elseif (in_array('availability', $segments ?? [], true)) {
                $response['message'] = 'availability base schedule not ready';
            }
        }
    }

    if ($qaMode !== '') {
        $metaArr = (array)$response['meta'];
        $metaArr['qa_mode_seen'] = $qaMode;
        $response['meta'] = (object)$metaArr;
    }

    $errorCode = (string)($response['error'] ?? '');
    $statusMap = [
        'unauthorized' => 401,
        'forbidden' => 403,
        'invalid_params' => 400,
        'invalid_verification_code' => 400,
        'conflict' => 409,
        'not_found' => 404,
        'not_implemented' => 501,
    ];
    $status = $statusMap[$errorCode] ?? 200;
    http_response_code($status);

    $json = json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($json === false) {
    $fallback = [
        'ok' => false,
        'error' => 'db_error',
        'message' => 'database error',
        'data' => null,
        'meta' => (object)[],
    ];
    $json = json_encode($fallback, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}
echo $json;
