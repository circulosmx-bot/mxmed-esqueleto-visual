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
require_once __DIR__ . '/../../modules/agenda/controllers/PublicAppointmentsController.php';
require_once __DIR__ . '/../../modules/agenda/controllers/PublicOtpController.php';

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
use Agenda\Controllers\PublicAppointmentsController;
use Agenda\Controllers\PublicOtpController;

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
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
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

function resolveAgendaActorContext(array $segments, array $query = []): array
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
            'warnings' => $warnings,
        ],
    ];
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
    if (is_private_agenda_route($segments) && !is_public_agenda_route($segments)) {
        $contextResult = resolveAgendaActorContext($segments, $_GET);
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
