<?php
require_once __DIR__ . '/../../modules/agenda/controllers/AppointmentsController.php';
require_once __DIR__ . '/../../modules/agenda/controllers/ConsultoriosController.php';
require_once __DIR__ . '/../../modules/agenda/controllers/AppointmentEventsController.php';
require_once __DIR__ . '/../../modules/agenda/controllers/PatientFlagsController.php';
require_once __DIR__ . '/../../modules/agenda/controllers/AvailabilityController.php';
require_once __DIR__ . '/../../modules/agenda/controllers/AppointmentWriteController.php';

use Agenda\Controllers\AppointmentsController;
use Agenda\Controllers\ConsultoriosController;
use Agenda\Controllers\AppointmentEventsController;
use Agenda\Controllers\PatientFlagsController;
use Agenda\Controllers\AvailabilityController;
use Agenda\Controllers\AppointmentWriteController;

header('Content-Type: application/json');
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

$controller = new AppointmentsController();
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
        $response = $writes->create();
        break;
    }
    if (isset($segments[1]) && $segments[1] !== '') {
        $sub = $segments[2] ?? '';
        if ($method === 'PATCH' && $sub === 'reschedule') {
            $writes = new AppointmentWriteController();
            $response = $writes->reschedule($segments[1]);
            break;
        }
        if ($method === 'POST' && $sub === 'cancel') {
            $writes = new AppointmentWriteController();
            $response = $writes->cancel($segments[1]);
            break;
        }
        if ($method === 'POST' && $sub === 'no_show') {
            $writes = new AppointmentWriteController();
            $response = $writes->noShow($segments[1]);
            break;
        }
        if ($sub === '') {
            $response = $controller->show($segments[1]);
        } elseif ($sub === 'events') {
            $events = new AppointmentEventsController();
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
            $response = $flags->index($segments[1], $_GET);
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
        $response = $consultorios->index($_GET);
        break;
    case 'availability':
        $availability = new AvailabilityController();
        $response = $availability->index($_GET);
        break;
}

if ((getenv('QA_MODE') ?: '') === 'not_ready'
    && isset($response['error'], $response['message'])
    && $response['error'] === 'db_not_ready') {
    $msg = (string)$response['message'];
    if (stripos($msg, 'sqlstate') !== false || stripos($msg, 'connection refused') !== false) {
        if (in_array('events', $segments, true)) {
            $response['message'] = 'appointment events not ready';
        } elseif (in_array('flags', $segments, true)) {
            $response['message'] = 'patient flags not ready';
        } elseif (in_array('availability', $segments, true)) {
            $response['message'] = 'availability base schedule not ready';
        }
    }
}
$status = ($response['error'] === 'not_implemented') ? 501 : 200;
http_response_code($status);
echo json_encode($response);
