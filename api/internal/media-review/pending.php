<?php
declare(strict_types=1);
require_once __DIR__.'/../../../modules/media/http/MediaReviewHttpContext.php';
require_once __DIR__.'/../../../modules/media/services/MediaReviewInboxService.php';
require_once __DIR__.'/../../_lib/db.php';
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header('Cross-Origin-Resource-Policy: same-origin');
try {
    $context = \Media\Http\MediaReviewHttpContext::fromRequest($_COOKIE,$_SERVER);
    \Media\Services\MediaReviewAuthority::requireRead($context);
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {http_response_code(405);header('Allow: GET');echo '{"ok":false,"error":"method_not_allowed"}';exit;}
    if (array_diff(array_keys($_GET),['limit','offset']) !== []) throw new InvalidArgumentException('invalid_pagination');
    $values = ['limit'=>25,'offset'=>0];
    foreach ($values as $key=>$default) {
        if (isset($_GET[$key])) {
            if (!is_string($_GET[$key]) || !preg_match('/^[0-9]{1,7}$/D',$_GET[$key])) throw new InvalidArgumentException('invalid_pagination');
            $values[$key]=(int)$_GET[$key];
        }
    }
    $data=(new \Media\Services\MediaReviewInboxService(mxmed_pdo()))->pending($context,$values['limit'],$values['offset']);
    echo json_encode(['ok'=>true,'data'=>$data],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    $status=$e->getMessage()==='review_access_denied'?403:($e instanceof InvalidArgumentException?400:500);
    if($status===500)error_log('media_review_pending_unavailable');
    http_response_code($status);echo json_encode(['ok'=>false,'error'=>$status===403?'forbidden':($status===400?'invalid_request':'review_unavailable')]);
}
