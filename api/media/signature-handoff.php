<?php
declare(strict_types=1);
require_once __DIR__.'/../../modules/signatures/SignatureHandoffHttp.php';
require_once __DIR__.'/../../modules/media/services/GallerySessionScope.php';
signatureHandoffHeaders();session_start();$scope=\Media\Services\GallerySessionScope::resolve($_SESSION);
if(!$scope)signatureHandoffReply(401,['ok'=>false,'error'=>'unauthorized']);
$method=$_SERVER['REQUEST_METHOD']??'GET';if(!in_array($method,['GET','POST','DELETE'],true))signatureHandoffReply(405,['ok'=>false,'error'=>'method_not_allowed']);
$_SESSION['physician_signature_handoff_csrf']??=bin2hex(random_bytes(32));$csrf=$_SESSION['physician_signature_handoff_csrf'];
if($method!=='GET'&&!hash_equals($csrf,(string)($_SERVER['HTTP_X_SIGNATURE_HANDOFF_CSRF']??'')))signatureHandoffReply(403,['ok'=>false,'error'=>'csrf_failed']);
$sessionReference=hash('sha256',session_id());session_write_close();
try{
 $uuid=new \Platform\Services\RandomAuditUuidProvider();$context=\Platform\Contracts\TrustedAuditContext::fromServer($scope['user_id'],'account','doctor','signature:'.$scope['doctor_id'],$uuid->generateCanonicalUuid(),$uuid->generateCanonicalUuid(),$sessionReference,null,null,'PROFILE','/api/media/signature-handoff.php');
 if($method==='GET'&&!$_GET)signatureHandoffReply(200,['ok'=>true,'data'=>['csrf_token'=>$csrf]]);
 $service=signatureHandoffService();
 if($method==='GET'){
  if(array_keys($_GET)!==['id']||!is_string($_GET['id']))throw new RuntimeException('handoff_owner_denied');
  signatureHandoffReply(200,['ok'=>true,'data'=>$service->status($_GET['id'],$scope['doctor_id'],$context)]);
 }
 if($_GET)throw new RuntimeException('handoff_owner_denied');$body=signatureHandoffBody();
 if($method==='POST'){
  if($body!==[])throw new RuntimeException('handoff_invalid_payload');
  // Validate deployment URL before persisting or invalidating a session.
  signatureHandoffUrl(str_repeat('A',43));$created=$service->create($scope['doctor_id'],$context);$url=signatureHandoffUrl($created['token']);unset($created['token']);
  signatureHandoffReply(200,['ok'=>true,'data'=>$created+['url'=>$url]]);
 }
 if(array_keys($body)!==['id']||!is_string($body['id']))throw new RuntimeException('handoff_invalid_payload');$service->cancel($body['id'],$scope['doctor_id'],$context);
 signatureHandoffReply(200,['ok'=>true,'data'=>['status'=>'EXPIRED']]);
}catch(Throwable $e){signatureHandoffError($e);}
