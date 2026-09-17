<?php
declare(strict_types=1);

require_once __DIR__.'/../_lib/db.php';
require_once __DIR__.'/../../modules/media/services/GallerySessionScope.php';
require_once __DIR__.'/../../modules/patients/repositories/PatientsRepository.php';
require_once __DIR__.'/../../modules/billing/services/PatientBillingProfilesService.php';

use Billing\Repositories\PatientBillingProfilesRepository;
use Billing\Services\PatientBillingProfilesService;
use Billing\Services\SatCfdiCatalog;
use Media\Services\GallerySessionScope;
use Patients\Repositories\PatientsRepository;

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
function billingProfilesReply(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

session_start(['use_strict_mode'=>true]);
$scope = GallerySessionScope::resolve($_SESSION);
if ($scope === null) billingProfilesReply(401, ['ok'=>false,'error'=>'unauthorized']);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET','POST','PUT','DELETE'], true)) billingProfilesReply(405, ['ok'=>false,'error'=>'method_not_allowed']);
$owner = hash('sha256', $scope['user_id'].'|'.$scope['doctor_id']);
if (($_SESSION['billing_profiles_owner'] ?? null) !== $owner) {
    $_SESSION['billing_profiles_owner'] = $owner;
    $_SESSION['billing_profiles_csrf'] = bin2hex(random_bytes(32));
}
$csrf = (string)$_SESSION['billing_profiles_csrf'];
if ($method !== 'GET' && !hash_equals($csrf, (string)($_SERVER['HTTP_X_BILLING_PROFILES_CSRF'] ?? ''))) {
    billingProfilesReply(403, ['ok'=>false,'error'=>'csrf_failed']);
}
session_write_close();

try {
    $catalog = new SatCfdiCatalog();
    if ($method === 'GET' && ($_GET['action'] ?? '') === 'catalog') {
        if (array_keys($_GET) !== ['action']) billingProfilesReply(400, ['ok'=>false,'error'=>'invalid_request']);
        billingProfilesReply(200, ['ok'=>true,'data'=>['catalog'=>$catalog->publicData(),'csrf_token'=>$csrf]]);
    }
    $pdo = mxmed_pdo();
    if ($method === 'GET' && ($_GET['action'] ?? '') === 'search') {
        if (array_diff(array_keys($_GET), ['action','q'])) billingProfilesReply(400, ['ok'=>false,'error'=>'invalid_request']);
        if (!is_string($_GET['q'] ?? null)) billingProfilesReply(400, ['ok'=>false,'error'=>'invalid_request']);
        $query = trim((string)($_GET['q'] ?? ''));
        if (mb_strlen($query) < 2 || mb_strlen($query) > 100) billingProfilesReply(422, ['ok'=>false,'error'=>'invalid_search_query']);
        $patients = (new PatientsRepository($pdo))->searchPatientsByDoctorId($scope['doctor_id'], $query, 25);
        $items = array_map(static fn(array $row): array => ['patient_id'=>(string)$row['patient_id'], 'display_name'=>(string)$row['display_name'], 'sex'=>is_string($row['sex']??null)?$row['sex']:null], $patients);
        billingProfilesReply(200, ['ok'=>true,'data'=>['patients'=>$items,'csrf_token'=>$csrf]]);
    }
    if ($method === 'GET' && array_diff(array_keys($_GET), ['patient_id'])) billingProfilesReply(400, ['ok'=>false,'error'=>'invalid_request']);
    if ($method !== 'GET' && $_GET !== []) billingProfilesReply(400, ['ok'=>false,'error'=>'invalid_request']);
    $service = new PatientBillingProfilesService(new PatientBillingProfilesRepository($pdo), $catalog);
    if ($method === 'GET') {
        if (!is_string($_GET['patient_id'] ?? null)) billingProfilesReply(400, ['ok'=>false,'error'=>'invalid_request']);
        $patientId = (string)($_GET['patient_id'] ?? '');
        billingProfilesReply(200, ['ok'=>true,'data'=>['profiles'=>$service->list($scope['doctor_id'], $patientId),'csrf_token'=>$csrf]]);
    }
    if (strtolower(trim(explode(';', (string)($_SERVER['CONTENT_TYPE'] ?? ''))[0])) !== 'application/json') {
        billingProfilesReply(415, ['ok'=>false,'error'=>'json_required']);
    }
    $raw = file_get_contents('php://input', false, null, 0, 8193);
    if ($raw === false || strlen($raw) > 8192) billingProfilesReply(413, ['ok'=>false,'error'=>'payload_too_large']);
    $body = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($body) || array_is_list($body) || array_diff(array_keys($body), ['patient_id','billing_profile_id','action','profile'])) {
        billingProfilesReply(400, ['ok'=>false,'error'=>'invalid_request']);
    }
    if (!is_string($body['patient_id'] ?? null) || (isset($body['billing_profile_id']) && !is_string($body['billing_profile_id']))) {
        billingProfilesReply(400, ['ok'=>false,'error'=>'invalid_request']);
    }
    $patientId = (string)($body['patient_id'] ?? '');
    $profileId = (string)($body['billing_profile_id'] ?? '');
    if ($method === 'POST' && count($body) === 3 && ($body['action'] ?? '') === 'set_default') {
        $profile = $service->makeDefault($scope['doctor_id'], $patientId, $profileId);
        billingProfilesReply(200, ['ok'=>true,'data'=>['profile'=>$profile]]);
    }
    if ($method === 'POST' && count($body) === 2 && $profileId === '' && is_array($body['profile'] ?? null)) {
        $profile = $service->create($scope['doctor_id'], $patientId, $body['profile']);
        billingProfilesReply(201, ['ok'=>true,'data'=>['profile'=>$profile]]);
    }
    if ($method === 'PUT' && count($body) === 3 && $profileId !== '' && is_array($body['profile'] ?? null) && !isset($body['action'])) {
        $profile = $service->update($scope['doctor_id'], $patientId, $profileId, $body['profile']);
        billingProfilesReply(200, ['ok'=>true,'data'=>['profile'=>$profile]]);
    }
    if ($method === 'DELETE' && $profileId !== '' && count($body) === 2) {
        $service->archive($scope['doctor_id'], $patientId, $profileId);
        billingProfilesReply(200, ['ok'=>true,'data'=>['archived'=>true]]);
    }
    billingProfilesReply(400, ['ok'=>false,'error'=>'invalid_request']);
} catch (\DomainException $error) {
    $code = $error->getMessage();
    billingProfilesReply($code === 'patient_scope_denied' ? 403 : 404, ['ok'=>false,'error'=>$code]);
} catch (\InvalidArgumentException|\JsonException $error) {
    billingProfilesReply(422, ['ok'=>false,'error'=>$error->getMessage()]);
} catch (\Throwable $error) {
    billingProfilesReply(503, ['ok'=>false,'error'=>'billing_profiles_unavailable']);
}
