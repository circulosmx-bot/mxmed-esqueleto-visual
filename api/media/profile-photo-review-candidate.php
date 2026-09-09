<?php
declare(strict_types=1);
require_once __DIR__.'/../_lib/db.php';
require_once __DIR__.'/../../modules/media/private-bootstrap.php';
require_once __DIR__.'/../../modules/media/services/GallerySessionScope.php';
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
session_start();
function candidateReply(int $status, array $body): never {
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
$allowDevFixture = getenv('MXMED_SUBSCRIPTIONS_DEV_SESSION_FIXTURE_ENABLED') === '1'
    && in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
foreach (['APP_ENV','MXMED_ENV','MXMED_ENVIRONMENT','ENVIRONMENT'] as $name) {
    if (in_array(strtolower((string)getenv($name)), ['prod','production'], true)) $allowDevFixture = false;
}
$scope = \Media\Services\GallerySessionScope::resolve($_SESSION, $allowDevFixture);
if ($scope === null) candidateReply(401, ['ok'=>false,'error'=>'unauthorized']);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET','POST','DELETE'], true)) candidateReply(405, ['ok'=>false,'error'=>'method_not_allowed']);
$_SESSION['profile_photo_candidate_csrf'] ??= bin2hex(random_bytes(32));
$token = $_SESSION['profile_photo_candidate_csrf'];
if ($method !== 'GET' && !hash_equals($token, (string)($_SERVER['HTTP_X_PROFILE_PHOTO_CANDIDATE_CSRF'] ?? ''))) candidateReply(403, ['ok'=>false,'error'=>'csrf_failed']);
session_write_close();
try {
    $service = new \Media\Services\ProfilePhotoReviewCandidateService(mxmed_pdo(), mxmed_private_media_storage());
    $doctor = $scope['doctor_id'];
    if ($method === 'POST') {
        $upload = $_FILES['image'] ?? [];
        if (!is_uploaded_file((string)($upload['tmp_name'] ?? ''))) throw new RuntimeException('candidate_upload_invalid');
        $service->upload($doctor, $upload);
    } elseif ($method === 'DELETE') {
        $service->withdraw($doctor);
    }
    candidateReply(200, ['ok'=>true,'data'=>['candidate'=>$service->current($doctor),'csrf_token'=>$token]]);
} catch (Throwable $e) {
    $error = $e->getMessage();
    $inputErrors = ['candidate_upload_invalid','candidate_invalid_extension'];
    $status = $error === 'candidate_profile_not_found' ? 404 : ((in_array($error,$inputErrors,true) || str_starts_with($error,'logo_upload_')) ? 422 : 500);
    if ($status === 500) error_log('profile_photo_candidate_failed: '.$error);
    candidateReply($status, ['ok'=>false,'error'=>$status===500?'candidate_unavailable':$error]);
}
