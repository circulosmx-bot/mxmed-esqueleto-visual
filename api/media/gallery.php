<?php
declare(strict_types=1);
require_once __DIR__ . '/../_lib/db.php';
require_once __DIR__ . '/../../modules/media/bootstrap.php';
require_once __DIR__ . '/../../modules/media/services/DoctorGalleryService.php';
require_once __DIR__ . '/../../modules/media/services/GallerySessionScope.php';
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
session_start();
function galleryReply(int $status, array $body): never {
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
if ($scope === null) galleryReply(401, ['ok'=>false,'error'=>'unauthorized','message'=>'Inicia sesión con un perfil médico autorizado para administrar tus fotos.']);
$doctor = $scope['doctor_id'];
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET','POST','DELETE','PATCH'], true)) galleryReply(405, ['ok'=>false,'error'=>'method_not_allowed']);
$_SESSION['gallery_csrf'] ??= bin2hex(random_bytes(32));
$token = $_SESSION['gallery_csrf'];
if ($method !== 'GET' && !hash_equals($token, (string)($_SERVER['HTTP_X_GALLERY_CSRF'] ?? ''))) galleryReply(403, ['ok'=>false,'error'=>'csrf_failed']);
session_write_close();
try {
    $service = new \Media\Services\DoctorGalleryService(mxmed_pdo(), mxmed_public_media_storage());
    if ($method === 'POST') {
        $upload = $_FILES['image'] ?? [];
        if (!is_uploaded_file((string)($upload['tmp_name'] ?? ''))) throw new RuntimeException('gallery_invalid_upload');
        $service->upload($doctor, $upload);
    } elseif ($method === 'DELETE') {
        $service->delete($doctor, trim((string)($_GET['media_id'] ?? '')));
    } elseif ($method === 'PATCH') {
        if ($_GET !== [] || $_POST !== [] || $_FILES !== [] || strtolower(trim(explode(';', (string)($_SERVER['CONTENT_TYPE'] ?? ''))[0])) !== 'application/json') {
            throw new RuntimeException('gallery_invalid_order');
        }
        $raw = file_get_contents('php://input', false, null, 0, 65537);
        $input = json_decode($raw ?: '', true);
        if (!$raw || strlen($raw) > 65536 || !is_array($input) || array_keys($input) !== ['media_ids'] || !is_array($input['media_ids'])) {
            throw new RuntimeException('gallery_invalid_order');
        }
        $service->reorder($doctor, $input['media_ids']);
    }
    galleryReply(200, ['ok'=>true, 'data'=>['images'=>$service->list($doctor), 'csrf_token'=>$token]]);
} catch (Throwable $e) {
    $error = $e->getMessage();
    $status = match ($error) {
        'gallery_invalid_order' => 400,
        'gallery_order_conflict' => 409,
        'gallery_asset_not_found', 'gallery_profile_not_found' => 404,
        default => (str_starts_with($error,'logo_') || str_starts_with($error,'gallery_')) ? 422 : 500,
    };
    $message = match ($error) {
        'gallery_invalid_order' => 'El orden enviado no es válido. Recarga la galería e intenta nuevamente.',
        'gallery_order_conflict' => 'La galería cambió antes de guardar. Recárgala y vuelve a ordenar las fotografías.',
        default => $status===500 ? 'No se pudieron guardar las fotos. Intenta nuevamente.' : 'No se pudo completar la operación. Usa JPG, PNG o WebP de hasta 2 MB y 4 megapíxeles (máximo 16 fotos).',
    };
    galleryReply($status, ['ok'=>false, 'error'=>$status===500?'gallery_unavailable':$error, 'message'=>$message]);
}
