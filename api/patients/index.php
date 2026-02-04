<?php
declare(strict_types=1);

require_once __DIR__ . '/../_lib/db.php';
require_once __DIR__ . '/../../modules/agenda/helpers/db_helpers.php';
require_once __DIR__ . '/../../modules/patients/controllers/GetPatientController.php';
require_once __DIR__ . '/../../modules/patients/controllers/GetDoctorPatientsController.php';

use Patients\Controllers\GetPatientController;
use Patients\Controllers\GetDoctorPatientsController;
use Agenda\Helpers as DbHelpers;

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = $_SERVER['REQUEST_URI'] ?? '/';

// Normaliza para casos /api/patients/index.php/...
$script = $_SERVER['SCRIPT_NAME'] ?? '';
$base = rtrim($script, 'index.php');
$relative = substr($uri, strlen($base));
$relative = trim($relative, '/');
$segments = $relative === '' ? [] : explode('/', $relative);

// En todos los casos inyectamos qa_mode_seen si existe qa mode
$qaMode = getenv('QA_MODE') ?: ($_SERVER['HTTP_X_QA_MODE'] ?? '');

function respond(array $response, string $qaMode): void {
    if ($qaMode !== '') {
        if (!isset($response['meta']) || !is_array($response['meta'])) {
            $response['meta'] = [];
        }
        $response['meta']['qa_mode_seen'] = $qaMode;
    }
    if (isset($response['meta']) && is_array($response['meta'])) {
        $response['meta'] = (object)$response['meta'];
    }
    $status = ($response['error'] === 'not_implemented') ? 501 : 200;
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
}

respond($response, $qaMode);
