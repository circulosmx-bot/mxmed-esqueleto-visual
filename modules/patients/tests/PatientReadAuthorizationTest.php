<?php
declare(strict_types=1);

require_once __DIR__ . '/../repositories/PatientsRepository.php';
require_once __DIR__ . '/../controllers/GetDoctorPatientsController.php';
require_once __DIR__ . '/../controllers/SearchDoctorPatientsController.php';
require_once __DIR__ . '/../controllers/GetPatientController.php';

use Patients\Controllers\GetDoctorPatientsController;
use Patients\Controllers\GetPatientController;
use Patients\Controllers\SearchDoctorPatientsController;
use Patients\Repositories\PatientsRepository;

final class PatientReadFakeRepository extends PatientsRepository
{
    public array $calls = [];

    public function __construct() {}

    public function findPatientsByDoctorId(string $doctorId, int $limit = 50): array
    {
        $this->calls[] = ['list', $doctorId];
        return [['patient_id' => 'patient-a']];
    }

    public function browsePatientsByDoctorId(string $doctorId, array $filters): array
    {
        $this->calls[] = ['archive', $doctorId];
        return ['items' => [['patient_id' => 'patient-a']], 'total' => 1, 'filtered_total' => 1];
    }

    public function searchPatientsByDoctorId(string $doctorId, string $query, int $limit = 25): array
    {
        $this->calls[] = ['search', $doctorId];
        return [['patient_id' => 'patient-a']];
    }

    public function hasActiveDoctorPatientLink(string $doctorId, string $patientId): bool
    {
        $this->calls[] = ['link', $doctorId, $patientId];
        return $doctorId === 'doctor-a' && $patientId === 'patient-a';
    }

    public function findPatientById(string $patientId): ?array
    {
        $this->calls[] = ['detail', $patientId];
        return $patientId === 'patient-a' ? ['patient_id' => $patientId] : null;
    }
}

function patientReadAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function patientReadController(string $class, PatientReadFakeRepository $repo): object
{
    $reflection = new ReflectionClass($class);
    $controller = $reflection->newInstanceWithoutConstructor();
    $reflection->getProperty('repo')->setValue($controller, $repo);
    return $controller;
}

session_start();
$repo = new PatientReadFakeRepository();
$list = patientReadController(GetDoctorPatientsController::class, $repo);
$search = patientReadController(SearchDoctorPatientsController::class, $repo);
$detail = patientReadController(GetPatientController::class, $repo);

$_SESSION = [];
foreach ([$list->handle('doctor-a'), $list->handle('doctor-a', ['view' => 'archive']), $search->handle('doctor-a', ['q' => 'Ana']), $detail->handle('patient-a')] as $response) {
    patientReadAssert($response['http_status'] === 401 && $response['data'] === null, 'anonymous patient read must be 401 with no data');
}
patientReadAssert($repo->calls === [], 'anonymous reads must not query patients');

$_SESSION = ['doctor_id' => 'doctor-a'];
patientReadAssert($list->handle('doctor-a')['http_status'] === 401, 'doctor context without user session must be 401');
$_SESSION = ['user_id' => 'user-a'];
patientReadAssert($detail->handle('patient-a')['http_status'] === 401, 'user session without doctor context must be 401');
patientReadAssert($repo->calls === [], 'partial sessions must not query patients');

$_SESSION = ['doctor_id' => 'doctor-a', 'user_id' => 'user-a'];
foreach ([$list->handle('doctor-b'), $list->handle('doctor-b', ['view' => 'archive']), $search->handle('doctor-b', ['q' => 'Ana'])] as $response) {
    patientReadAssert($response['http_status'] === 403 && $response['data'] === null, 'other doctor path must be 403 with no data');
}
patientReadAssert($repo->calls === [], 'other doctor path must not query patients');

patientReadAssert($list->handle('doctor-a')['ok'] === true, 'same-doctor list allowed');
patientReadAssert($list->handle('doctor-a', ['view' => 'archive'])['meta']->filtered_total === 1, 'same-doctor archive allowed');
patientReadAssert($search->handle('doctor-a', ['q' => 'Ana'])['ok'] === true, 'same-doctor search allowed');
patientReadAssert($detail->handle('patient-a')['data']['patient_id'] === 'patient-a', 'linked patient detail allowed');

$beforeForeign = count($repo->calls);
$foreign = $detail->handle('patient-b');
$unknown = $detail->handle('random-id');
patientReadAssert($foreign['http_status'] === 404 && $foreign['data'] === null, 'foreign patient detail denied');
patientReadAssert($unknown == $foreign, 'foreign and unknown patient responses indistinguishable');
foreach (array_slice($repo->calls, $beforeForeign) as $call) {
    patientReadAssert($call[0] === 'link', 'patient payload must not be queried without active link');
}

echo "PatientReadAuthorizationTest PASS\n";
