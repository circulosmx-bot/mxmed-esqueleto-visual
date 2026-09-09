<?php
declare(strict_types=1);
require_once __DIR__.'/../_lib/db.php';
require_once __DIR__.'/../../modules/media/services/GallerySessionScope.php';
require_once __DIR__.'/../../modules/media/services/MediaReviewBatchService.php';
header('Content-Type: application/json; charset=UTF-8');header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');
session_start();
function batchReply(int $status,array $body):never{http_response_code($status);echo json_encode($body,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
$scope=Media\Services\GallerySessionScope::resolve($_SESSION,false);
if($scope===null)batchReply(401,['ok'=>false,'error'=>'unauthorized']);
$method=$_SERVER['REQUEST_METHOD']??'GET';
if(!in_array($method,['GET','POST'],true))batchReply(405,['ok'=>false,'error'=>'method_not_allowed']);
$_SESSION['media_review_batch_csrf']??=bin2hex(random_bytes(32));$csrf=$_SESSION['media_review_batch_csrf'];session_write_close();
try{
    if($_GET!==[]||$_FILES!==[]||$_POST!==[])throw new InvalidArgumentException();
    if($method==='POST'){
        if(strtolower(trim(explode(';',$_SERVER['CONTENT_TYPE']??'')[0]))!=='application/json')throw new InvalidArgumentException();
        $raw=file_get_contents('php://input',false,null,0,1025);$input=json_decode($raw?:'',true);
        if(!$raw||strlen($raw)>1024||!is_array($input)||array_keys($input)!==['csrf']||!is_string($input['csrf']))throw new InvalidArgumentException();
        if(!hash_equals($csrf,$input['csrf']))batchReply(403,['ok'=>false,'error'=>'csrf_failed']);
    }
    $service=new Media\Services\MediaReviewBatchService(mxmed_pdo());
    if($method==='POST'&&!$service->submit($scope['doctor_id']))batchReply(409,['ok'=>false,'error'=>'nothing_to_submit']);
    batchReply(200,['ok'=>true,'data'=>[...$service->current($scope['doctor_id']),'csrf'=>$csrf]]);
}catch(Throwable $e){batchReply($e instanceof InvalidArgumentException?400:503,['ok'=>false,'error'=>$e instanceof InvalidArgumentException?'invalid_request':'batch_unavailable']);}
