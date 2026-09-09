<?php
declare(strict_types=1);
namespace Media\Http;
require_once __DIR__.'/../services/LogoImprovementService.php';
use Identity\Http\{IdentityHttpComposition,CanonicalHttpSessionResolver};
use Identity\Repositories\InternalOperatorGrantRepository;
use Identity\Services\InternalOperatorAuthority;
use Media\Services\LogoImprovementService;
use Platform\Contracts\RiskLevel;
final class LogoImprovementHttp
{
    public static function run(string $action):void
    {
        header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');header('Cross-Origin-Resource-Policy: same-origin');
        try{
            $preview=$action==='preview';
            if(!is_string($_COOKIE['__Host-mxmed_session']??null))throw new \RuntimeException('improvement_denied');
            $identity=IdentityHttpComposition::fromProcessEnvironment();$resolver=new CanonicalHttpSessionResolver($identity->sessions());$session=$resolver->resolve($_COOKIE);
            $context=(new InternalOperatorAuthority($resolver,new InternalOperatorGrantRepository($identity->pdo())))->resolve($_COOKIE,$preview?'read':'improvement_'.$action,'media_review_submission',$preview?RiskLevel::R0:RiskLevel::R1);
            if(!$session||!$context||$context->context()->accountId()!==$session->accountId()||!$context->context()->capabilities()->contains($preview?'media_review_read':LogoImprovementService::CAPABILITY))throw new \RuntimeException('improvement_denied');
            $method=$preview?'GET':'POST';if(($_SERVER['REQUEST_METHOD']??'')!==$method){header('Allow: '.$method);self::reply(405,['ok'=>false,'error'=>'method_not_allowed']);return;}
            if($preview){if(array_keys($_GET)!==['submission_id']||!is_string($_GET['submission_id']))throw new \RuntimeException('improvement_invalid_request');$id=$_GET['submission_id'];}
            else{
                if($_GET!==[]||strtolower(trim(explode(';',$_SERVER['CONTENT_TYPE']??'')[0]))!=='application/json')throw new \RuntimeException('improvement_invalid_request');
                $raw=file_get_contents('php://input',false,null,0,2049);if($raw===false||strlen($raw)>2048)throw new \RuntimeException('improvement_invalid_request');$input=json_decode($raw,true);
                if(!is_array($input)||array_diff(array_keys($input),['submission_id','csrf'])!==[]||!is_string($input['submission_id']??null))throw new \RuntimeException('improvement_invalid_request');
                if(!is_string($input['csrf']??null)||!$identity->csrf()->validAuthenticated($input['csrf'],$session->session()->tokenDigest()))throw new \RuntimeException('improvement_denied');$id=$input['submission_id'];
            }
            require_once __DIR__.'/../../../api/_lib/db.php';require_once __DIR__.'/../private-bootstrap.php';$service=new LogoImprovementService(\mxmed_pdo(),\mxmed_private_media_storage());
            if($preview){$bytes=$service->preview($context,$id);header('Content-Type: image/webp');header('Content-Length: '.strlen($bytes));echo $bytes;}
            else self::reply(200,$service->mutate($context,$action,$id));
        }catch(\Throwable $e){$status=match($e->getMessage()){'improvement_denied','review_access_denied'=>403,'improvement_invalid_request'=>400,'improvement_not_found'=>404,'improvement_conflict'=>409,default=>503};if($status===503)error_log('logo_improvement_unavailable');self::reply($status,['ok'=>false,'error'=>match($status){403=>'forbidden',400=>'invalid_request',404=>'not_found',409=>'stale_or_ineligible',default=>'unavailable'}]);}
    }
    private static function reply(int $status,array $body):void{http_response_code($status);header('Content-Type: application/json; charset=UTF-8');echo json_encode($body,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
}
