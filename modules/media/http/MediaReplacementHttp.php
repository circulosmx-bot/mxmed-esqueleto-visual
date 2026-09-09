<?php
declare(strict_types=1);
namespace Media\Http;
require_once __DIR__.'/../services/MediaReplacementService.php';
use Identity\Http\{IdentityHttpComposition,CanonicalHttpSessionResolver};
use Identity\Repositories\InternalOperatorGrantRepository;
use Identity\Services\InternalOperatorAuthority;
use Media\Services\MediaReplacementService;
use Platform\Contracts\RiskLevel;
final class MediaReplacementHttp
{
    public static function run():void
    {
        header('Content-Type: application/json; charset=UTF-8');header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');header('Cross-Origin-Resource-Policy: same-origin');
        try {
            if(!is_string($_COOKIE['__Host-mxmed_session']??null))throw new \RuntimeException('replacement_denied');
            $identity=IdentityHttpComposition::fromProcessEnvironment();$resolver=new CanonicalHttpSessionResolver($identity->sessions());$session=$resolver->resolve($_COOKIE);
            $context=(new InternalOperatorAuthority($resolver,new InternalOperatorGrantRepository($identity->pdo())))->resolve($_COOKIE,'request_replacement','media_review_submission',RiskLevel::R1);
            if(!$session||!$context||$context->context()->accountId()!==$session->accountId()||!$context->context()->capabilities()->contains(MediaReplacementService::CAPABILITY))throw new \RuntimeException('replacement_denied');
            if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){header('Allow: POST');self::reply(405,['ok'=>false,'error'=>'method_not_allowed']);return;}
            if($_GET!==[]||strtolower(trim(explode(';',$_SERVER['CONTENT_TYPE']??'')[0]))!=='application/json')throw new \RuntimeException('replacement_invalid_request');
            // Accommodates 400 Unicode characters even when JSON encodes surrogate pairs.
            $raw=file_get_contents('php://input',false,null,0,8193);if($raw===false||strlen($raw)>8192)throw new \RuntimeException('replacement_invalid_request');
            $input=json_decode($raw,true);
            if(!is_array($input)||array_diff(array_keys($input),['submission_id','reason_code','feedback','csrf'])!==[]||!is_string($input['submission_id']??null))throw new \RuntimeException('replacement_invalid_request');
            if(!is_string($input['csrf']??null)||!$identity->csrf()->validAuthenticated($input['csrf'],$session->session()->tokenDigest()))throw new \RuntimeException('replacement_denied');
            require_once __DIR__.'/../../../api/_lib/db.php';require_once __DIR__.'/../private-bootstrap.php';
            self::reply(200,(new MediaReplacementService(\mxmed_pdo(),\mxmed_private_media_storage()))->request($context,$input['submission_id'],$input['reason_code']??null,array_key_exists('feedback',$input)?$input['feedback']:''));
        }catch(\Throwable $e){
            $status=match($e->getMessage()){'replacement_denied'=>403,'replacement_invalid_request'=>400,'replacement_not_found'=>404,'replacement_conflict'=>409,default=>503};
            if($status===503)error_log('media_replacement_unavailable');
            self::reply($status,['ok'=>false,'error'=>match($status){403=>'forbidden',400=>'invalid_request',404=>'not_found',409=>'already_processed_or_ineligible',default=>'unavailable'}]);
        }
    }
    private static function reply(int $status,array $body):void{http_response_code($status);echo json_encode($body,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
}
