<?php
declare(strict_types=1);
require_once __DIR__.'/../_lib/db.php';
require_once __DIR__.'/../../modules/signatures/PhysicianSignatureService.php';
require_once __DIR__.'/../../modules/media/private-bootstrap.php';
require_once __DIR__.'/../../modules/media/services/GallerySessionScope.php';
header('Content-Type: application/json; charset=UTF-8');header('Cache-Control: no-store');
session_start();
function signatureReply(int $status,array $body): never {http_response_code($status);echo json_encode($body,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
$scope=\Media\Services\GallerySessionScope::resolve($_SESSION);
if(!$scope)signatureReply(401,['ok'=>false,'error'=>'unauthorized']);
$method=$_SERVER['REQUEST_METHOD']??'GET';
if(!in_array($method,['GET','POST','DELETE'],true))signatureReply(405,['ok'=>false,'error'=>'method_not_allowed']);
if($_GET)signatureReply(403,['ok'=>false,'error'=>'signature_request_scope_forbidden']);
$_SESSION['physician_signature_csrf']??=bin2hex(random_bytes(32));$csrf=$_SESSION['physician_signature_csrf'];
if($method!=='GET'&&!hash_equals($csrf,(string)($_SERVER['HTTP_X_SIGNATURE_CSRF']??'')))signatureReply(403,['ok'=>false,'error'=>'csrf_failed']);
$sessionReference=hash('sha256',session_id());session_write_close();
try {
    $service=new \Signatures\PhysicianSignatureService(mxmed_pdo(),mxmed_private_media_storage());
    $uuid=new \Platform\Services\RandomAuditUuidProvider();
    $context=\Platform\Contracts\TrustedAuditContext::fromServer($scope['user_id'],'account','doctor','signature:'.$scope['doctor_id'],$uuid->generateCanonicalUuid(),$uuid->generateCanonicalUuid(),$sessionReference,null,null,'PROFILE','/api/media/physician-signature.php');
    if($method==='POST'){
        if((int)($_SERVER['CONTENT_LENGTH']??0)>2800000)throw new RuntimeException('signature_input_too_large');
        if(strtolower(trim(explode(';',(string)($_SERVER['CONTENT_TYPE']??''))[0]))!=='application/json')throw new RuntimeException('signature_invalid_payload');
        $raw=file_get_contents('php://input',false,null,0,2800001);
        if(strlen($raw)>2800000)throw new RuntimeException('signature_input_too_large');
        $body=json_decode($raw,true);
        if(!is_array($body)||array_keys($body)!==['image_data']||!is_string($body['image_data']))throw new RuntimeException('signature_invalid_payload');
        $service->save($scope['doctor_id'],$body['image_data'],$context);
    }elseif($method==='DELETE'){
        if((int)($_SERVER['CONTENT_LENGTH']??0)>0)throw new RuntimeException('signature_request_scope_forbidden');
        $service->delete($scope['doctor_id'],$context);
    }
    signatureReply(200,['ok'=>true,'data'=>['signature'=>$service->current($scope['doctor_id']),'csrf_token'=>$csrf,'owner_scope'=>hash('sha256',$scope['user_id'].'|'.$scope['doctor_id'])]]);
}catch(Throwable $e){
    $known=in_array($e->getMessage(),['signature_invalid_payload','signature_input_too_large','signature_invalid_image','signature_decode_failed','signature_empty','signature_output_too_large','signature_request_scope_forbidden'],true);
    signatureReply($known?422:503,['ok'=>false,'error'=>$known?$e->getMessage():'signature_unavailable','message'=>$known?'Usa una firma PNG válida de hasta 2 MiB, 4096 px por lado y 4 megapíxeles.':'No fue posible completar el cambio de firma. La firma vigente se conserva; vuelve a consultar su estado.']);
}
