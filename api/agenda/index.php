<?php
require_once __DIR__ . '/../../modules/agenda/controllers/AppointmentsController.php';
require_once __DIR__ . '/../../modules/agenda/controllers/ConsultoriosController.php';
require_once __DIR__ . '/../../modules/agenda/controllers/AppointmentEventsController.php';
require_once __DIR__ . '/../../modules/agenda/controllers/PatientFlagsController.php';
require_once __DIR__ . '/../../modules/agenda/controllers/AvailabilityController.php';

use Agenda\Controllers\AppointmentsController;
use Agenda\Controllers\ConsultoriosController;

header('Content-Type: application/json');
$path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$script = trim(str_replace($_SERVER['SCRIPT_NAME'], '', $_SERVER['SCRIPT_NAME']), '/');
$segments = array_values(array_filter(explode('/', str_replace('api/agenda', '', $path))));

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
    if (isset($segments[1]) && $segments[1] !== '') {
            $sub = $segments[2] ?? '';
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

http_response_code(501);
echo json_encode($response);
