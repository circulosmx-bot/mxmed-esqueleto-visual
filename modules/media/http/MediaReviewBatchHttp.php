<?php
declare(strict_types=1);
namespace Media\Http;
require_once __DIR__.'/MediaReviewHttpContext.php';
require_once __DIR__.'/../services/MediaReviewBatchInboxService.php';
require_once __DIR__.'/../../../api/_lib/db.php';
final class MediaReviewBatchHttp
{
    public static function run(bool $detail=false):void
    {
        header('Content-Type: application/json; charset=UTF-8');header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');header('Cross-Origin-Resource-Policy: same-origin');
        try{
            $context=MediaReviewHttpContext::fromRequest($_COOKIE,$_SERVER);\Media\Services\MediaReviewAuthority::requireRead($context);
            if(($_SERVER['REQUEST_METHOD']??'')!=='GET'){http_response_code(405);echo '{"ok":false,"error":"method_not_allowed"}';return;}
            if(array_diff(array_keys($_GET),$detail?['batch_id','limit','offset']:['limit','offset'])||$_POST||$_FILES)throw new \InvalidArgumentException();
            $values=['limit'=>$detail?50:25,'offset'=>0];foreach($values as $key=>$default)if(isset($_GET[$key])){if(!is_string($_GET[$key])||!preg_match('/^[0-9]{1,7}$/D',$_GET[$key]))throw new \InvalidArgumentException();$values[$key]=(int)$_GET[$key];}
            $service=new \Media\Services\MediaReviewBatchInboxService(\mxmed_pdo());
            if($detail&&!is_string($_GET['batch_id']??null))throw new \InvalidArgumentException();
            $data=$detail?$service->detail($context,$_GET['batch_id'],$values['limit'],$values['offset']):$service->listing($context,$values['limit'],$values['offset']);
            echo json_encode(['ok'=>true,'data'=>$data],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
        }catch(\Throwable $e){$status=$e->getMessage()==='review_access_denied'?403:($e->getMessage()==='batch_not_found'?404:($e instanceof \InvalidArgumentException?400:503));http_response_code($status);echo json_encode(['ok'=>false,'error'=>$status===503?'batch_unavailable':($status===403?'forbidden':($status===404?'not_found':'invalid_request'))]);}
    }
}
