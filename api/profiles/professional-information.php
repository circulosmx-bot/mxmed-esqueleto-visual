<?php
declare(strict_types=1);
require_once __DIR__.'/../_lib/db.php';
require_once __DIR__.'/../../modules/media/services/GallerySessionScope.php';
require_once __DIR__.'/../../modules/profiles/services/ProfessionalInformationService.php';
header('Content-Type: application/json; charset=UTF-8');header('Cache-Control: no-store');
session_start();
function professionalReply(int $status,array $body): never { http_response_code($status);echo json_encode($body,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit; }
$scope=\Media\Services\GallerySessionScope::resolve($_SESSION);
if(!$scope)professionalReply(401,['ok'=>false,'error'=>'unauthorized']);
if($_GET)professionalReply(403,['ok'=>false,'error'=>'doctor_scope_forbidden']);
$method=$_SERVER['REQUEST_METHOD']??'GET';
if(!in_array($method,['GET','PUT'],true))professionalReply(405,['ok'=>false,'error'=>'method_not_allowed']);
$owner=hash('sha256',$scope['user_id'].'|'.$scope['doctor_id']);
if(($_SESSION['professional_information_owner']??null)!==$owner){
    $_SESSION['professional_information_owner']=$owner;
    $_SESSION['professional_information_csrf']=bin2hex(random_bytes(32));
}
$_SESSION['professional_information_csrf']??=bin2hex(random_bytes(32));$csrf=$_SESSION['professional_information_csrf'];
if($method==='PUT'&&!hash_equals($csrf,(string)($_SERVER['HTTP_X_PROFESSIONAL_INFORMATION_CSRF']??'')))professionalReply(403,['ok'=>false,'error'=>'csrf_failed']);
session_write_close();
try{
    $service=new \Profiles\Services\ProfessionalInformationService(mxmed_pdo());
    if($method==='PUT'){
        if(strtolower(trim(explode(';',$_SERVER['CONTENT_TYPE']??'')[0]))!=='application/json')professionalReply(415,['ok'=>false,'error'=>'json_required']);
        $raw=file_get_contents('php://input',false,null,0,2097153);
        if(strlen($raw)>2097152)professionalReply(413,['ok'=>false,'error'=>'payload_too_large']);
        $draft=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
        if(!is_array($draft))throw new InvalidArgumentException('Borrador profesional inválido.');
        if(array_intersect(['doctor_id','user_id','owner_id'],array_keys($draft)))professionalReply(403,['ok'=>false,'error'=>'doctor_scope_forbidden']);
        $current=$service->save($scope['doctor_id'],$draft);
    }else $current=$service->current($scope['doctor_id']);
    professionalReply(200,['ok'=>true,'data'=>['professional_information'=>$current,'csrf_token'=>$csrf]]);
}catch(InvalidArgumentException|JsonException $e){professionalReply(422,['ok'=>false,'error'=>'validation_error','message'=>$e->getMessage()]);}
catch(Throwable $e){professionalReply(503,['ok'=>false,'error'=>'professional_information_unavailable','message'=>'No se pudo guardar o cargar la información profesional. Tus cambios pendientes se conservan.']);}
