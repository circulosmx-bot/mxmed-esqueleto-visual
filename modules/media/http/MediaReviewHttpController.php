<?php
declare(strict_types=1);
namespace Media\Http;
require_once __DIR__.'/MediaReviewHttpContext.php';
require_once __DIR__.'/../services/MediaReviewAccessService.php';
require_once __DIR__.'/../private-bootstrap.php';
require_once __DIR__.'/../../../api/_lib/db.php';
use Media\Services\{MediaReviewAuthority,MediaReviewAccessService};

final class MediaReviewHttpController
{
    public static function run(bool $binary): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        header('Cross-Origin-Resource-Policy: same-origin');
        try {
            session_start(['read_and_close'=>true,'use_strict_mode'=>true,'cache_limiter'=>'']);
            $env = [];
            foreach (['MXMED_MEDIA_REVIEW_DEV_OPERATOR_ENABLED','APP_ENV','MXMED_ENV','MXMED_ENVIRONMENT','ENVIRONMENT'] as $name) $env[$name] = (string)getenv($name);
            $context = MediaReviewHttpContext::resolve($_SESSION, session_id(), $_SERVER, $env);
            // Authorize before validating UUID, opening DB or revealing existence.
            MediaReviewAuthority::requireRead($context);
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
                http_response_code(405); header('Allow: GET'); echo '{"ok":false,"error":"method_not_allowed"}'; return;
            }
            if (array_diff(array_keys($_GET), ['submission_id']) !== [] || !is_string($_GET['submission_id'] ?? null)) {
                http_response_code(400); echo '{"ok":false,"error":"invalid_request"}'; return;
            }
            $service = new MediaReviewAccessService(mxmed_pdo(), mxmed_private_media_storage());
            $id = $_GET['submission_id'];
            if ($binary) {
                $bytes = $service->reviewBytes($context,$id);
                header('Content-Type: image/webp');
                header('Content-Length: '.strlen($bytes));
                header('Content-Disposition: inline');
                echo $bytes;
            } else {
                echo json_encode(['ok'=>true,'data'=>$service->metadata($context,$id)], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            }
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            $status = $message === 'review_access_denied' ? 403 : ($message === 'review_not_found' ? 404 : 500);
            if ($status === 500) error_log('media_review_request_failed type='.get_class($e));
            http_response_code($status);
            echo json_encode(['ok'=>false,'error'=>$status===403?'forbidden':($status===404?'not_found':'review_unavailable')]);
        }
    }
}
