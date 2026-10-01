<?php
declare(strict_types=1);

require_once __DIR__.'/../../modules/identity/http/IdentityHttpComposition.php';
require_once __DIR__.'/../../modules/identity/http/CanonicalHttpSessionResolver.php';
require_once __DIR__.'/../../modules/agenda/http/ProviderManagementApi.php';

use Identity\Http\IdentityHttpComposition;
use Identity\Http\CanonicalHttpSessionResolver;
use Agenda\Http\ProviderManagementApi;
use Agenda\Http\ProviderManagementHttpException;
use Agenda\Services\HealthcareOrganizationTeamException;

function providerJson(int $status,array $payload): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');header('Pragma: no-cache');header('X-Content-Type-Options: nosniff');
    header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
    echo json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);exit;
}
try {
    IdentityHttpComposition::registerAutoloader();
    $identity=IdentityHttpComposition::fromProcessEnvironment();
    $method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));
    if (!in_array($method,['GET','POST','PATCH'],true)) providerJson(405,['ok'=>false,'error'=>'METHOD_NOT_ALLOWED']);
    $path=parse_url((string)($_SERVER['REQUEST_URI']??''),PHP_URL_PATH)?:'';
    $prefix='/api/provider/index.php/';
    if (!str_starts_with($path,$prefix) || str_contains($path,'%')) providerJson(404,['ok'=>false,'error'=>'NOT_FOUND']);
    $segments=explode('/',trim(substr($path,strlen($prefix)),'/'));
    $actor=(new CanonicalHttpSessionResolver($identity->sessions()))->resolve($_COOKIE);
    if ($actor===null) providerJson(401,['ok'=>false,'error'=>'UNAUTHENTICATED']);
    $body=[];
    if ($method!=='GET') {
        $origin=rtrim((string)($_SERVER['HTTP_ORIGIN']??''),'/');$referer=(string)($_SERVER['HTTP_REFERER']??'');$allowed=$identity->allowedOrigin();
        if (($origin!==''&&$origin!==$allowed)||($origin===''&&($referer===''||!str_starts_with($referer,$allowed.'/'))))
            providerJson(403,['ok'=>false,'error'=>'INVALID_ORIGIN']);
        if (stripos((string)($_SERVER['CONTENT_TYPE']??''),'application/json')===false) providerJson(415,['ok'=>false,'error'=>'INVALID_CONTENT_TYPE']);
        $raw=file_get_contents('php://input',false,null,0,65537);
        if ($raw===false||strlen($raw)>65536) providerJson(413,['ok'=>false,'error'=>'REQUEST_TOO_LARGE']);
        $decoded=json_decode($raw);
        if (!$decoded instanceof stdClass) providerJson(400,['ok'=>false,'error'=>'INVALID_JSON']);
        $body=(array)$decoded;
        $csrf=(string)($body['csrf_token']??($_SERVER['HTTP_X_CSRF_TOKEN']??''));unset($body['csrf_token']);
        if (!$identity->csrf()->validAuthenticated($csrf,$actor->session()->tokenDigest())) providerJson(403,['ok'=>false,'error'=>'INVALID_CSRF']);
    }
    [$status,$data]=(new ProviderManagementApi($identity->pdo()))->handle($method,$segments,$_GET,$body,$actor);
    providerJson($status,['ok'=>true,'data'=>$data]);
} catch (ProviderManagementHttpException $e) {
    providerJson($e->status,['ok'=>false,'error'=>$e->error]);
} catch (HealthcareOrganizationTeamException $e) {
    $status=match($e->reason) {
        'authentication_required'=>401,'invitation_not_found','organization_not_found','member_not_manageable'=>404,
        'team_management_denied'=>403,'invitee_resolution_rate_limited'=>429,
        'invalid_invitation_role','invalid_submission_key','self_invitation_denied','verified_account_required'=>422,
        default=>409,
    };
    providerJson($status,['ok'=>false,'error'=>strtoupper($e->reason)]);
} catch (InvalidArgumentException $e) {
    $reason=$e->getMessage();
    providerJson(str_contains($reason,'already_exists')?409:422,['ok'=>false,'error'=>'VALIDATION_FAILED','reason'=>$reason]);
} catch (RuntimeException $e) {
    if (str_ends_with($e->getMessage(),'_not_found')) providerJson(404,['ok'=>false,'error'=>'NOT_FOUND']);
    error_log('PROVIDER_MANAGEMENT_ERROR: '.get_class($e));providerJson(503,['ok'=>false,'error'=>'TEMPORARILY_UNAVAILABLE']);
} catch (Throwable $e) {
    error_log('PROVIDER_MANAGEMENT_ERROR: '.get_class($e));providerJson(503,['ok'=>false,'error'=>'TEMPORARILY_UNAVAILABLE']);
}
