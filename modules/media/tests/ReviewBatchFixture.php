<?php
declare(strict_types=1);
require_once __DIR__.'/GalleryReviewFixture.php';
require_once __DIR__.'/../services/MediaReviewBatchService.php';
require_once __DIR__.'/../services/MediaReviewBatchInboxService.php';
require_once __DIR__.'/../services/MediaReviewInboxService.php';
function mr11ReadContext():Platform\Contracts\TrustedAuthorizationContext {
 return Platform\Contracts\TrustedAuthorizationContext::fromBackend(new Platform\Contracts\AuthorizationContext(sessionReference:new Platform\Contracts\SessionReference('mr11_read'),accountId:'mr11_operator',credentialVersion:1,capabilities:new Platform\Contracts\CapabilitySet(['media_review_read']),action:'read',resource:'media_review_submission',authorizationPlane:Platform\Contracts\AuthorizationPlane::INTERNAL_OPERATOR,riskLevel:'R0'));
}
function mr11Doctor():string{$id='mr11_'.bin2hex(random_bytes(6));mr5Pdo()->prepare('INSERT INTO profiles_doctors(doctor_id,display_name) VALUES(?,?)')->execute([$id,'Dra. Prueba MR11']);return $id;}
function mr11Candidate(string $doctor,string $purpose='DOCTOR_GALLERY',?PDO $pdo=null):array{
 $pdo??=mr5Pdo();$u=mr8Upload();try{(new Media\Services\PhysicianMediaReviewCandidateService($pdo,mr5Storage()[0],$purpose))->upload($doctor,$u);}finally{unlink($u['tmp_name']);}
 $s=$pdo->prepare('SELECT submission_id id,batch_id,owner_id doctor FROM media_review_submissions WHERE owner_id=? AND purpose=? ORDER BY created_at DESC,submission_id DESC LIMIT 1');$s->execute([$doctor,$purpose]);return $s->fetch();
}
function mr11Batch(int $gallery=16,bool $mixed=true):array{
 $d=mr11Doctor();if($mixed){mr11Candidate($d,'DOCTOR_PROFILE_PHOTO');mr11Candidate($d,'PHYSICIAN_PERSONAL_LOGO');}
 for($i=0;$i<$gallery;$i++)$f=mr11Candidate($d);return $f;
}
function mr11Legacy():array{
 $d=mr11Doctor();$id=(new Platform\Services\RandomAuditUuidProvider())->generateCanonicalUuid();$p=mr5Pdo();$p->prepare("INSERT INTO media_review_submissions(submission_id,owner_type,owner_id,purpose,technical_status,review_status) VALUES(?,'PHYSICIAN',?,'DOCTOR_GALLERY','READY','PENDING_REVIEW')")->execute([$id,$d]);
 $u=mr8Upload();$im=(new Media\Services\GdPublicLogoProcessor())->process($u,false);[$storage]=mr5Storage();
 try{foreach(['SOURCE','REVIEW'] as $role){$fid=(new Platform\Services\RandomAuditUuidProvider())->generateCanonicalUuid();$key='private/media-review/'.hash('sha256','PHYSICIAN:'.$d).'/'.$id.'/'.strtolower($role).'/'.$fid.'.webp';$storage->storeImmutable($key,$im['path']);$p->prepare("INSERT INTO media_review_files(file_id,submission_id,role,storage_key,mime_type,format,width,height,byte_size,checksum_sha256) VALUES(?,?,?,?,'image/webp','webp',?,?,?,?)")->execute([$fid,$id,$role,$key,$im['width'],$im['height'],$im['byte_size'],$im['checksum_sha256']]);}}finally{unlink($u['tmp_name']);unlink($im['path']);}return ['id'=>$id,'doctor'=>$d];
}
if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){$mode=$argv[1]??'';if($mode==='batch')echo json_encode(mr11Batch((int)($argv[2]??16),($argv[3]??'1')==='1'));elseif($mode==='legacy')echo json_encode(mr11Legacy());}
