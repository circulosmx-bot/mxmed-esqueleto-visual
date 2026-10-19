<?php
declare(strict_types=1);
require_once __DIR__.'/../../../modules/media/services/AdvisorWorkspaceService.php';
header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');header('Cross-Origin-Resource-Policy: same-origin');
try{
    $service=\Media\Services\AdvisorWorkspaceService::fromCookies($_COOKIE);$service->requireMedia();
    if(array_diff(array_keys($_GET),['doctor_id','submission_id','mode'])||!is_string($_GET['doctor_id']??null)||!is_string($_GET['mode']??null))throw new InvalidArgumentException('workspace_invalid_request');
    $mode=$_GET['mode'];$doctor=$_GET['doctor_id'];$method=$_SERVER['REQUEST_METHOD']??'';
    $reads=['detail'=>'submission.php','review'=>'review-image.php','source'=>'source-download.php','options'=>'approval-options.php','improvement-preview'=>'improvement-preview.php'];
    $writes=['replacement'=>'request-replacement.php','approve'=>'approve.php','corrected'=>'corrected.php','improve'=>'improve-logo.php','accept-improvement'=>'accept-improvement.php','discard-improvement'=>'discard-improvement.php'];
    if(!isset($reads[$mode])&&!isset($writes[$mode]))throw new InvalidArgumentException('workspace_invalid_request');
    $expected=isset($reads[$mode])?'GET':'POST';
    if($method!==$expected){http_response_code(405);header('Allow: '.$expected);header('Content-Type: application/json');echo '{"ok":false,"error":"method_not_allowed"}';return;}
    if($mode==='options'){
        if(isset($_GET['submission_id'])||$_POST||$_FILES)throw new InvalidArgumentException('workspace_invalid_request');
        $service->assertPhysician($doctor);$_GET=[];
    }else{
        if($method==='GET'){
            if(!is_string($_GET['submission_id']??null)||$_POST||$_FILES)throw new InvalidArgumentException('workspace_invalid_request');$id=$_GET['submission_id'];
        }elseif($mode==='corrected'){
            if(isset($_GET['submission_id'])||!is_string($_POST['submission_id']??null))throw new InvalidArgumentException('workspace_invalid_request');$id=$_POST['submission_id'];
        }else{
            if(isset($_GET['submission_id'])||$_POST||$_FILES||strtolower(trim(explode(';',$_SERVER['CONTENT_TYPE']??'')[0]))!=='application/json')throw new InvalidArgumentException('workspace_invalid_request');
            $raw=file_get_contents('php://input',false,null,0,8193);
            if($raw===false||strlen($raw)>8192)throw new InvalidArgumentException('workspace_invalid_request');
            $body=json_decode($raw,true);if(!is_array($body)||!is_string($body['submission_id']??null))throw new InvalidArgumentException('workspace_invalid_request');$id=$body['submission_id'];
        }
        $item=$service->assertSubmission($doctor,$id);
        $_GET=$method==='GET'?['submission_id'=>$id]:[];
        if($mode==='approve')$writes['approve']=match($item['purpose']){'DOCTOR_PROFILE_PHOTO'=>'approve.php','PHYSICIAN_PERSONAL_LOGO'=>'approve-logo.php','DOCTOR_GALLERY'=>'approve-gallery.php',default=>throw new InvalidArgumentException('workspace_invalid_request')};
    }
    // Existing endpoint owns request validation, fresh grants, CSRF, locks, writer,
    // audit and binary handling. php://input remains the original request body.
    require __DIR__.'/../media-review/'.($reads[$mode]??$writes[$mode]);
}catch(Throwable $e){
    $status=match($e->getMessage()){'workspace_login_required'=>401,'workspace_staff_required','review_access_denied'=>403,'workspace_invalid_request'=>400,'workspace_not_found'=>404,default=>503};
    if($status===503)error_log('advisor_workspace_media_unavailable');http_response_code($status);header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>false,'error'=>match($status){401=>'authentication_required',403=>'forbidden',400=>'invalid_request',404=>'not_found',default=>'workspace_unavailable'}]);
}
