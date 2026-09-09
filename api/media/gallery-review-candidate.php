<?php
declare(strict_types=1);
require_once __DIR__.'/../_lib/db.php';
require_once __DIR__.'/../../modules/media/private-bootstrap.php';
require_once __DIR__.'/../../modules/media/services/GallerySessionScope.php';
require_once __DIR__.'/../../modules/media/services/GalleryReviewCandidateService.php';
session_start();
header('Content-Type: application/json; charset=UTF-8');header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');
function galleryCandidateReply(int $status,array $body):never{http_response_code($status);echo json_encode($body,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
$allowDevFixture=getenv('MXMED_SUBSCRIPTIONS_DEV_SESSION_FIXTURE_ENABLED')==='1'&&in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true);
foreach(['APP_ENV','MXMED_ENV','MXMED_ENVIRONMENT','ENVIRONMENT'] as $name)if(in_array(strtolower((string)getenv($name)),['prod','production'],true))$allowDevFixture=false;
$scope=\Media\Services\GallerySessionScope::resolve($_SESSION,$allowDevFixture);
if($scope===null)galleryCandidateReply(401,['ok'=>false,'error'=>'unauthorized']);
$method=$_SERVER['REQUEST_METHOD']??'GET';if(!in_array($method,['GET','POST','DELETE'],true))galleryCandidateReply(405,['ok'=>false,'error'=>'method_not_allowed']);
$_SESSION['gallery_review_candidate_csrf']??=bin2hex(random_bytes(32));$token=$_SESSION['gallery_review_candidate_csrf'];
if($method!=='GET'&&!hash_equals($token,(string)($_SERVER['HTTP_X_GALLERY_REVIEW_CANDIDATE_CSRF']??'')))galleryCandidateReply(403,['ok'=>false,'error'=>'csrf_failed']);session_write_close();
try{
    $service=new \Media\Services\GalleryReviewCandidateService(mxmed_pdo(),mxmed_private_media_storage());$doctor=$scope['doctor_id'];$offset=0;
    if($method==='GET'){
        if(array_diff(array_keys($_GET),['offset'])!==[]||$_POST!==[]||$_FILES!==[])throw new RuntimeException('gallery_invalid_request');
        if(isset($_GET['offset'])){if(!is_string($_GET['offset'])||!preg_match('/^\d{1,7}$/D',$_GET['offset']))throw new RuntimeException('gallery_invalid_request');$offset=(int)$_GET['offset'];}
    }elseif($method==='POST'){
        if($_GET!==[]||$_POST!==[]||array_keys($_FILES)!==['image'])throw new RuntimeException('gallery_invalid_request');
        $upload=$_FILES['image'];if(!is_string($upload['tmp_name']??null)||!is_string($upload['name']??null)||!is_uploaded_file($upload['tmp_name']))throw new RuntimeException('candidate_upload_invalid');
        $service->upload($doctor,$upload);
    }else{
        if($_GET!==[]||$_POST!==[]||$_FILES!==[]||strtolower(trim(explode(';',$_SERVER['CONTENT_TYPE']??'')[0]))!=='application/json')throw new RuntimeException('gallery_invalid_request');
        $raw=file_get_contents('php://input',false,null,0,1025);$input=json_decode($raw?:'',true);
        if(!$raw||strlen($raw)>1024||!is_array($input)||array_keys($input)!==['submission_id']||!is_string($input['submission_id']))throw new RuntimeException('gallery_invalid_request');
        $service->withdraw($doctor,$input['submission_id']);
    }
    galleryCandidateReply(200,['ok'=>true,'data'=>[...$service->listing($doctor,$offset),'csrf_token'=>$token]]);
}catch(Throwable $e){$error=$e->getMessage();$status=match($error){'gallery_invalid_request'=>400,'gallery_candidate_not_found','candidate_profile_not_found'=>404,'gallery_candidate_conflict','gallery_limit_reached'=>409,default=>(str_starts_with($error,'logo_upload_')||in_array($error,['candidate_upload_invalid','candidate_invalid_extension'],true)?422:503)};galleryCandidateReply($status,['ok'=>false,'error'=>$status===503?'candidate_unavailable':$error]);}
