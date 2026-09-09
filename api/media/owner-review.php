<?php
declare(strict_types=1);
require_once __DIR__.'/../_lib/db.php';
require_once __DIR__.'/../../modules/media/private-bootstrap.php';
require_once __DIR__.'/../../modules/media/services/GallerySessionScope.php';
require_once __DIR__.'/../../modules/media/services/MediaReplacementReasons.php';
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header('Cross-Origin-Resource-Policy: same-origin');
session_start(['read_and_close'=>true]);
$scope=Media\Services\GallerySessionScope::resolve($_SESSION,false);
function ownerReviewReply(int $code,array $data):never {http_response_code($code);header('Content-Type: application/json; charset=UTF-8');echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
if(!$scope)ownerReviewReply(401,['ok'=>false,'error'=>'unauthorized']);
if(($_SERVER['REQUEST_METHOD']??'')!=='GET')ownerReviewReply(405,['ok'=>false,'error'=>'method_not_allowed']);
try {
    if(array_diff(array_keys($_GET),['preview'])!==[])ownerReviewReply(400,['ok'=>false,'error'=>'invalid_request']);
    $pdo=mxmed_pdo();
    if(isset($_GET['preview'])) {
        $id=$_GET['preview'];
        if(!is_string($id)||!preg_match('/^[0-9a-f-]{36}$/D',$id))ownerReviewReply(400,['ok'=>false,'error'=>'invalid_request']);
        $s=$pdo->prepare("SELECT f.storage_key,f.mime_type,f.checksum_sha256 FROM media_review_submissions s JOIN media_review_files f ON f.submission_id=s.submission_id AND f.role='REVIEW' WHERE s.submission_id=? AND s.owner_type='PHYSICIAN' AND s.owner_id=? AND s.technical_status='READY' AND s.review_status IN ('PENDING_REVIEW','NEEDS_WORK')");
        $s->execute([$id,$scope['doctor_id']]);$row=$s->fetch(PDO::FETCH_ASSOC);
        if(!$row)ownerReviewReply(404,['ok'=>false,'error'=>'not_found']);
        $object=mxmed_private_media_storage()->openReadStream($row['storage_key']);
        try {$bytes=stream_get_contents($object['stream'],10485761);} finally {fclose($object['stream']);}
        if(!is_string($bytes)||strlen($bytes)>10485760||!hash_equals($row['checksum_sha256'],hash('sha256',$bytes)))throw new RuntimeException('invalid_preview');
        header('Content-Type: image/webp');header('Content-Length: '.strlen($bytes));echo $bytes;exit;
    }
    $s=$pdo->prepare("SELECT s.submission_id,s.purpose,s.review_status,s.review_reason_code,s.review_feedback,b.status batch_status FROM media_review_submissions s LEFT JOIN media_review_batches b ON b.batch_id=s.batch_id WHERE s.owner_type='PHYSICIAN' AND s.owner_id=? AND s.review_status IN ('PENDING_REVIEW','NEEDS_WORK') ORDER BY s.created_at,s.submission_id");
    $s->execute([$scope['doctor_id']]);$items=[];
    $current=[];
    foreach(['DOCTOR_PROFILE_PHOTO','PHYSICIAN_PERSONAL_LOGO'] as $purpose){
        $candidate=(new Media\Services\PhysicianMediaReviewCandidateService($pdo,mxmed_private_media_storage(),$purpose))->current($scope['doctor_id']);
        $current[$purpose]=$candidate['submission_id']??null;
    }
    foreach($s->fetchAll(PDO::FETCH_ASSOC) as $r){
        if(array_key_exists($r['purpose'],$current)&&$current[$r['purpose']]!==$r['submission_id'])continue;
        $needs=$r['review_status']==='NEEDS_WORK';
        $items[]=['id'=>$r['submission_id'],'purpose'=>$r['purpose'],'state'=>$needs?'NEEDS_WORK':($r['batch_status']==='OPEN'?'OPEN':'SUBMITTED'),'reason'=>$needs?(Media\Services\MediaReplacementReasons::LABELS[$r['review_reason_code']]??'Se requiere otra imagen.'):null,'feedback'=>$needs?$r['review_feedback']:null,'preview_url'=>'/api/media/owner-review.php?preview='.rawurlencode($r['submission_id'])];
    }
    ownerReviewReply(200,['ok'=>true,'data'=>['items'=>$items]]);
} catch(Throwable $e){ownerReviewReply(503,['ok'=>false,'error'=>'review_unavailable']);}
