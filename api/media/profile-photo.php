<?php
declare(strict_types=1);
require_once __DIR__ . '/../_lib/db.php';
require_once __DIR__ . '/../../modules/media/bootstrap.php';
require_once __DIR__ . '/../../modules/media/services/DoctorProfilePhotoService.php';
require_once __DIR__ . '/../../modules/media/services/GallerySessionScope.php';
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
session_start();
function photoReply(int $status, array $body): never {
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
// Never accept identity headers or client-supplied doctor IDs.
$allowDevFixture = getenv('MXMED_SUBSCRIPTIONS_DEV_SESSION_FIXTURE_ENABLED') === '1'
    && in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
foreach (['APP_ENV', 'MXMED_ENV', 'MXMED_ENVIRONMENT', 'ENVIRONMENT'] as $name) {
    if (in_array(strtolower((string)getenv($name)), ['prod', 'production'], true)) $allowDevFixture = false;
}
$scope = \Media\Services\GallerySessionScope::resolve($_SESSION, $allowDevFixture);
if ($scope === null) photoReply(401, ['ok'=>false,'error'=>'unauthorized','message'=>'Inicia sesión con un perfil médico autorizado para administrar tus fotos.']);
$doctor = $scope['doctor_id'];
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET','POST','DELETE'], true)) photoReply(405, ['ok'=>false,'error'=>'method_not_allowed']);
$_SESSION['profile_photo_csrf'] ??= bin2hex(random_bytes(32));
$token = $_SESSION['profile_photo_csrf'];
if ($method !== 'GET' && !hash_equals($token, (string)($_SERVER['HTTP_X_PROFILE_PHOTO_CSRF'] ?? ''))) photoReply(403, ['ok'=>false,'error'=>'csrf_failed']);
session_write_close();
try {
    $service = new \Media\Services\DoctorProfilePhotoService(mxmed_pdo(), mxmed_public_media_storage());
    if ($method === 'POST') {
        $upload = $_FILES['image'] ?? [];
        if (!is_uploaded_file((string)($upload['tmp_name'] ?? ''))) throw new RuntimeException('photo_upload_invalid');
        $service->upload($doctor, $upload);
    } elseif ($method === 'DELETE') {
        $service->delete($doctor);
    }
    photoReply(200, ['ok'=>true, 'data'=>['photo'=>$service->current($doctor), 'csrf_token'=>$token]]);
} catch (Throwable $e) {
    $error = $e->getMessage();
    $status = $error === 'photo_profile_not_found' ? 404 : ((str_starts_with($error,'logo_') || str_starts_with($error,'photo_')) ? 422 : 500);
    photoReply($status, ['ok'=>false, 'error'=>$status===500?'profile_photo_unavailable':$error,
        'message'=>$status===500?'No se pudo completar el cambio. Recarga para comprobar la foto guardada e intenta nuevamente.':'Usa JPG, PNG o WebP válido de hasta 10 MiB, 8192 px por lado y 25 megapíxeles.']);
}
