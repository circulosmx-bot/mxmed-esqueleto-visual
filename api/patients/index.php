<?php
declare(strict_types=1);

require_once __DIR__ . '/../_lib/db.php';
require_once __DIR__ . '/../../modules/agenda/helpers/db_helpers.php';
require_once __DIR__ . '/../../modules/patients/controllers/GetPatientController.php';
require_once __DIR__ . '/../../modules/patients/controllers/GetDoctorPatientsController.php';
require_once __DIR__ . '/../../modules/patients/controllers/CreatePatientController.php';

use Patients\Controllers\GetPatientController;
use Patients\Controllers\GetDoctorPatientsController;
use Patients\Controllers\CreatePatientController;
use Agenda\Helpers as DbHelpers;

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = $_SERVER['REQUEST_URI'] ?? '/';

// Normaliza para casos /api/patients/index.php/...
$script = $_SERVER['SCRIPT_NAME'] ?? '';
$base = $script;
if (substr($script, -strlen('index.php')) === 'index.php') {
    $base = substr($script, 0, -strlen('index.php'));
}
$relative = substr($uri, strlen($base));
$relative = trim($relative, '/');
$segments = $relative === '' ? [] : explode('/', $relative);
if (!empty($segments) && $segments[0] === 'index.php') {
    array_shift($segments);
}

// En todos los casos inyectamos qa_mode_seen si existe qa mode
$qaMode = getenv('QA_MODE') ?: ($_SERVER['HTTP_X_QA_MODE'] ?? '');

function respond(array $response, string $qaMode, string $method): void {
    if ($qaMode !== '') {
        if (!isset($response['meta']) || !is_array($response['meta'])) {
            $response['meta'] = [];
        }
        $response['meta']['qa_mode_seen'] = $qaMode;
    }
    if (isset($response['meta']) && is_array($response['meta'])) {
        $response['meta'] = (object)$response['meta'];
    }
    $status = ($response['error'] === 'not_implemented') ? 501 : (($response['ok'] === true && $method === 'POST') ? 201 : 200);
    http_response_code($status);
    echo json_encode($response);
}

$response = ['ok' => false, 'error' => 'not_found', 'message' => 'route not found', 'data' => null, 'meta' => (object)[]];

if ($method === 'GET') {
    if (count($segments) === 2 && $segments[0] === 'patients') {
        $controller = new GetPatientController();
        $response = $controller->handle($segments[1]);
    } elseif (count($segments) === 3 && $segments[0] === 'doctors' && $segments[2] === 'patients') {
        $controller = new GetDoctorPatientsController();
        $query = $_GET ?? [];
        $response = $controller->handle($segments[1], $query);
    }
} elseif ($method === 'POST') {
    if (count($segments) === 1 && $segments[0] === 'patients') {
        $payloadRaw = file_get_contents('php://input');
        $decoded = json_decode($payloadRaw, true);
        if (!is_array($decoded)) {
            $response = ['ok' => false, 'error' => 'invalid_params', 'message' => 'invalid json', 'data' => null, 'meta' => ['visibility' => ['contact' => 'masked']]];
        } else {
            $controller = new CreatePatientController();
            $response = $controller->handle($decoded);
        }
    }
}

respond($response, $qaMode, $method);
