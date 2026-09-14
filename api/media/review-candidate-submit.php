<?php
declare(strict_types=1);
require_once __DIR__.'/../_lib/db.php';
require_once __DIR__.'/../../modules/media/services/MediaCandidateSubmissionService.php';
header('Content-Type: application/json; charset=UTF-8');header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');
session_start();
function itemSubmitReply(int $status,array $body):never{http_response_code($status);echo json_encode($body,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
try{$actor=Media\Services\MediaSubmissionActor::fromSession($_SESSION,session_id());}catch(Throwable){itemSubmitReply(401,['ok'=>false,'error'=>'unauthorized']);}
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')itemSubmitReply(405,['ok'=>false,'error'=>'method_not_allowed']);
// Token issued by the existing authenticated batch metadata GET; no extra GET contract.
$csrf=$_SESSION['media_review_batch_csrf']??null;session_write_close();
try {
    if($_GET||$_POST||$_FILES||strtolower(trim(explode(';',$_SERVER['CONTENT_TYPE']??'')[0]))!=='application/json')throw new InvalidArgumentException();
    $raw=file_get_contents('php://input',false,null,0,1025);$input=json_decode($raw?:'',true);
    if(!$raw||strlen($raw)>1024||!is_array($input)||count($input)!==2||array_diff(array_keys($input),['csrf','submission_id'])||!is_string($input['csrf']??null)||!is_string($input['submission_id']??null))throw new InvalidArgumentException();
    if(!is_string($csrf)||!hash_equals($csrf,$input['csrf']))itemSubmitReply(403,['ok'=>false,'error'=>'csrf_failed']);
    $data=(new Media\Services\MediaCandidateSubmissionService(mxmed_pdo()))->submit($input['submission_id'],$actor);
    itemSubmitReply(200,['ok'=>true,'data'=>$data]);
} catch(Throwable $e) {
    $status=match($e->getMessage()){'submission_not_found'=>404,'submission_invalid_request'=>400,'submission_unauthorized'=>403,'submission_conflict','submission_legacy_already_reviewable'=>409,default=>$e instanceof InvalidArgumentException?400:503};
    itemSubmitReply($status,['ok'=>false,'error'=>match($status){400=>'invalid_request',403=>'forbidden',404=>'not_found',409=>'submission_conflict',default=>'submission_unavailable'}]);
}
