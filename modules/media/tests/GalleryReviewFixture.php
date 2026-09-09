<?php
declare(strict_types=1);
require_once __DIR__.'/MediaReplacementFixture.php';
require_once __DIR__.'/../services/GalleryReviewCandidateService.php';
require_once __DIR__.'/../services/GalleryApprovalService.php';
require_once __DIR__.'/../services/DoctorGalleryService.php';
function mr10Doctor():string{$p=mr5Pdo();$f=mr5Candidate($p);(new Media\Services\PhysicianMediaReviewCandidateService($p,mr5Storage()[0],'DOCTOR_PROFILE_PHOTO'))->withdraw($f['doctor']);return $f['doctor'];}
function mr10Candidate(?string $doctor=null,string $kind='photo'):array{
 $doctor??=mr10Doctor();$p=mr5Pdo();$u=mr8Upload($kind);
 try{(new Media\Services\GalleryReviewCandidateService($p,mr5Storage()[0]))->upload($doctor,$u);}finally{unlink($u['tmp_name']);}
 $s=$p->prepare("SELECT submission_id FROM media_review_submissions WHERE owner_id=? AND purpose='DOCTOR_GALLERY' ORDER BY created_at DESC,submission_id DESC LIMIT 1");$s->execute([$doctor]);return ['doctor'=>$doctor,'id'=>$s->fetchColumn()];
}
/** Seed historical public assets, including over-limit fixtures, without exercising new upload authority. */
function mr10SeedPublic(string $doctor,int $count):void{
 $p=mr5Pdo();[, $storage]=mr5Storage();$u=mr8Upload();$image=(new Media\Services\GdPublicLogoProcessor())->process($u);
 try{for($i=0;$i<$count;$i++){$id=(new Platform\Services\RandomAuditUuidProvider())->generateCanonicalUuid();$key='public/doctor-gallery/'.hash('sha256','PHYSICIAN:'.$doctor).'/'.$id.'.webp';$storage->storeImmutable($key,$image['path']);(new Media\Repositories\MediaAssetsRepository($p))->insertReady([...$image,'media_id'=>$id,'owner_type'=>'PHYSICIAN','owner_id'=>$doctor,'purpose'=>'DOCTOR_GALLERY','storage_key'=>$key,'public_url'=>'/api/media/index.php/public/'.$id,'alt_text'=>'Synthetic historical gallery']);}}finally{unlink($image['path']);}
}
if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__&&($argv[1]??'')==='candidate')echo json_encode(mr10Candidate(($argv[2]??'')?:null,$argv[3]??'photo'));
