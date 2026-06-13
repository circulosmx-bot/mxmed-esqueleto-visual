<?php
declare(strict_types=1);

require_once __DIR__ . '/../_lib/db.php';
require_once __DIR__ . '/../../modules/agenda/helpers/db_helpers.php';
require_once __DIR__ . '/../../modules/patients/controllers/GetPatientController.php';
require_once __DIR__ . '/../../modules/patients/controllers/GetDoctorPatientsController.php';
require_once __DIR__ . '/../../modules/patients/controllers/SearchDoctorPatientsController.php';
require_once __DIR__ . '/../../modules/patients/controllers/GetEditablePatientContactsController.php';
require_once __DIR__ . '/../../modules/patients/controllers/CreatePatientController.php';
require_once __DIR__ . '/../../modules/patients/controllers/UpsertPatientAddressController.php';
require_once __DIR__ . '/../../modules/patients/controllers/UpsertPatientProfileController.php';

use Patients\Controllers\GetPatientController;
use Patients\Controllers\GetDoctorPatientsController;
use Patients\Controllers\SearchDoctorPatientsController;
use Patients\Controllers\GetEditablePatientContactsController;
use Patients\Controllers\CreatePatientController;
use Patients\Controllers\UpsertPatientAddressController;
use Patients\Controllers\UpsertPatientProfileController;
use Agenda\Helpers as DbHelpers;

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = $_SERVER['REQUEST_URI'] ?? '/';

// Preferimos recortar desde un marcador fijo porque SCRIPT_NAME puede variar según el server.
$path = parse_url($uri, PHP_URL_PATH);
$path = is_string($path) ? $path : '';
$marker = '/api/patients/index.php';
$pos = strpos($path, $marker);
if ($pos !== false) {
    $relative = substr($path, $pos + strlen($marker));
} else {
    // Fallback legado para mantener compatibilidad en despliegues con rutas distintas.
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $base = $script;
    if (substr($script, -strlen('index.php')) === 'index.php') {
        $base = substr($script, 0, -strlen('index.php'));
    }
    $relative = substr($uri, strlen($base));
}
$relative = trim($relative, '/');
$segments = $relative === '' ? [] : explode('/', $relative);
// Compat con llamadas donde aún llega index.php como primer segmento.
if (!empty($segments) && $segments[0] === 'index.php') {
    array_shift($segments);
}

// En todos los casos inyectamos qa_mode_seen si existe qa mode
$qaMode = getenv('QA_MODE') ?: ($_SERVER['HTTP_X_QA_MODE'] ?? '');

function respond(array $response, string $qaMode, string $method): void {
    $statusOverride = isset($response['http_status']) ? (int)$response['http_status'] : null;
    unset($response['http_status']);
    if ($qaMode !== '') {
        if (!isset($response['meta']) || !is_array($response['meta'])) {
            $response['meta'] = [];
        }
        $response['meta']['qa_mode_seen'] = $qaMode;
    }
    if (isset($response['meta']) && is_array($response['meta'])) {
        $response['meta'] = (object)$response['meta'];
    }
    $status = ($statusOverride !== null && $statusOverride >= 100 && $statusOverride <= 599)
        ? $statusOverride
        : (($response['error'] === 'not_implemented') ? 501 : (($response['ok'] === true && $method === 'POST') ? 201 : 200));
    http_response_code($status);
    echo json_encode($response);
}

$response = ['ok' => false, 'error' => 'not_found', 'message' => 'route not found', 'data' => null, 'meta' => (object)[]];

if ($method === 'GET') {
    if (count($segments) === 2 && $segments[0] === 'patients') {
        $controller = new GetPatientController();
        $response = $controller->handle($segments[1]);
    } elseif (count($segments) === 6 && $segments[0] === 'doctors' && $segments[2] === 'patients' && $segments[4] === 'contacts' && $segments[5] === 'editable') {
        $controller = new GetEditablePatientContactsController();
        $response = $controller->handle($segments[1], $segments[3]);
    } elseif (count($segments) === 4 && $segments[0] === 'doctors' && $segments[2] === 'patients' && $segments[3] === 'search') {
        $controller = new SearchDoctorPatientsController();
        $query = $_GET ?? [];
        $response = $controller->handle($segments[1], $query);
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
    } elseif (count($segments) === 3 && $segments[0] === 'patients' && $segments[2] === 'address') {
        $payloadRaw = file_get_contents('php://input');
        $decoded = json_decode($payloadRaw, true);
        if (!is_array($decoded)) {
            $response = ['ok' => false, 'error' => 'invalid_params', 'message' => 'invalid json', 'data' => null, 'meta' => ['visibility' => ['contact' => 'masked']]];
        } else {
            $controller = new UpsertPatientAddressController();
            $response = $controller->handle($segments[1], $decoded);
        }
    } elseif (count($segments) === 3 && $segments[0] === 'patients' && $segments[2] === 'profile') {
        $payloadRaw = file_get_contents('php://input');
        $decoded = json_decode($payloadRaw, true);
        if (!is_array($decoded)) {
            $response = ['ok' => false, 'error' => 'invalid_params', 'message' => 'invalid json', 'data' => null, 'meta' => ['visibility' => ['contact' => 'masked']]];
        } else {
            $controller = new UpsertPatientProfileController();
            $response = $controller->handle($segments[1], $decoded);
        }
    }
}

respond($response, $qaMode, $method);
