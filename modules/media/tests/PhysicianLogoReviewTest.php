<?php
declare(strict_types=1);
require __DIR__.'/PhysicianLogoReviewFixture.php';
require_once __DIR__.'/../services/MediaReviewAccessService.php';
require_once __DIR__.'/../services/MediaReviewInboxService.php';
function ok(bool $v,string $name):void{if(!$v)throw new RuntimeException('FAIL '.$name);echo "PASS $name\n";}
function bytes($storage,array $file):string{$o=$storage->openReadStream($file['storage_key']);try{return stream_get_contents($o['stream']);}finally{fclose($o['stream']);}}
$p=mr5Pdo();[$private,$public]=mr5Storage();$f=mr7Candidate($p);$doctor=$f['doctor'];$id=$f['id'];
// Historical document payload fixture uses the existing resolved-logo snapshot key.
$p->exec('CREATE TEMPORARY TABLE mr7_document_snapshot (payload_json JSON NOT NULL)');
$snapshot=json_encode(['branding'=>['logo_url_resolved'=>$f['old']['public_url']]]);
$p->prepare('INSERT INTO mr7_document_snapshot VALUES(?)')->execute([$snapshot]);
$historical=$p->query('SELECT payload_json FROM mr7_document_snapshot')->fetchColumn();
$candidates=new Media\Services\PhysicianLogoReviewCandidateService($p,$private);
$pubState=fn()=>json_encode([$p->query('SELECT * FROM media_assets ORDER BY media_id')->fetchAll(),$p->query('SELECT * FROM profiles_doctors ORDER BY doctor_id')->fetchAll()]);
$before=$pubState();$files=mr6Rows($p,$id);$source=bytes($private,$files['SOURCE']);$review=bytes($private,$files['REVIEW']);
ok(hash('sha256',$source)===$f['source_hash'],'exact_private_source');
$im=imagecreatefromstring($review);ok(((imagecolorat($im,0,0)>>24)&127)>100,'meaningful_alpha_preserved');imagedestroy($im);
ok($files['REVIEW']['width']==800 && $files['REVIEW']['height']==267 && strlen($review)<=153600,'horizontal_aspect_optimized');
// Independent pending photo and logo for same physician; database uniqueness cannot be bypassed.
$u=mr6File();try{(new Media\Services\ProfilePhotoReviewCandidateService($p,$private))->upload($doctor,$u);}finally{unlink($u['tmp_name']);}
foreach(['PHYSICIAN_PERSONAL_LOGO','DOCTOR_PROFILE_PHOTO'] as $purpose){
 try{$p->prepare("INSERT INTO media_review_submissions(submission_id,owner_type,owner_id,purpose,technical_status,review_status) VALUES(UUID(),'PHYSICIAN',?,?,'READY','PENDING_REVIEW')")->execute([$doctor,$purpose]);throw new LogicException('duplicate permitted');}catch(PDOException $e){ok($e->getCode()==='23000','database_pending_unique_'.$purpose);}
}
$photo=(new Media\Services\ProfilePhotoReviewCandidateService($p,$private))->current($doctor);
$actor=new Platform\Contracts\ActorReference('account','mr7_reader');
$read=Platform\Contracts\TrustedAuthorizationContext::fromBackend(new Platform\Contracts\AuthorizationContext(realActor:$actor,effectiveActor:$actor,sessionReference:new Platform\Contracts\SessionReference('mr7_session'),accountId:'mr7_reader',credentialVersion:1,capabilities:new Platform\Contracts\CapabilitySet(['media_review_read']),action:'read',resource:'media_review_submission',authorizationPlane:Platform\Contracts\AuthorizationPlane::INTERNAL_OPERATOR,riskLevel:Platform\Contracts\RiskLevel::R0),'canonical_internal_operator','active',true,false);
$access=new Media\Services\MediaReviewAccessService($p,$private);ok($access->reviewBytes($read,$id)===$review,'shared_review_delivery');
$metadata=json_encode($access->metadata($read,$id));ok(!str_contains($metadata,'SOURCE')&&!str_contains($metadata,'storage_key')&&!str_contains($metadata,'private/'),'no_private_metadata_leak');
$seen=[];for($offset=0;;$offset+=50){$page=(new Media\Services\MediaReviewInboxService($p))->pending($read,50,$offset);foreach($page['items'] as $item)if($item['owner_id']===$doctor)$seen[]=$item['purpose'];if(!$page['pagination']['has_more'])break;}
sort($seen);ok($seen===['DOCTOR_PROFILE_PHOTO','PHYSICIAN_PERSONAL_LOGO'],'inbox_both_purposes');
foreach([['jpeg',4000,3000],['png',5000,5000],['webp',8192,100]] as [$format,$w,$h]){
 $u=$w===4000?mr6ModernFile():mr6File($format,$w,$h);
 try{$hash=hash_file('sha256',$u['tmp_name']);$candidates->upload($doctor,$u);}finally{unlink($u['tmp_name']);}
 $next=$candidates->current($doctor);$rows=mr6Rows($p,$next['submission_id']);
 ok($rows['SOURCE']['checksum_sha256']===$hash,'large_source_exact_'.$w.'x'.$h);
 ok(max($rows['REVIEW']['width'],$rows['REVIEW']['height'])<=800 && $rows['REVIEW']['byte_size']<=153600,'output_limits');
 if($format==='jpeg'){$output=bytes($private,$rows['REVIEW']);ok($rows['REVIEW']['height']===800 && $rows['REVIEW']['width']===600 && !str_contains($output,'Exif')&&!str_contains($output,'GPS=TEST')&&!str_contains($output,'DEVICE=TEST'),'orientation_and_metadata');}
 ok($pubState()===$before,'large_candidate_keeps_public');
}
$pending=$candidates->current($doctor);
foreach(['bytes','pixels','side','extension','mime','corrupt','svg'] as $bad){
 $u=match($bad){'pixels'=>mr6File('png',5001,5000),'side'=>mr6File('png',8193,50),default=>mr6File()};
 if($bad==='bytes')file_put_contents($u['tmp_name'],str_pad(file_get_contents($u['tmp_name']),10485761,'x'));
 if($bad==='extension')$u['name']='wrong.jpeg';if($bad==='mime')$u['type']='image/jpeg';
 if($bad==='corrupt')file_put_contents($u['tmp_name'],substr(file_get_contents($u['tmp_name']),0,40));
 if($bad==='svg'){file_put_contents($u['tmp_name'],'<svg xmlns="http://www.w3.org/2000/svg"></svg>');$u['name']='logo.svg';}
 try{$candidates->upload($doctor,$u);throw new LogicException('accepted '.$bad);}catch(RuntimeException $e){ok($candidates->current($doctor)===$pending && $pubState()===$before,'invalid_preserves_pending_'.$bad);}finally{unlink($u['tmp_name']);}
}
$id=$pending['submission_id'];$original=mr6Rows($p,$id)['SOURCE'];$intervention=new Media\Services\MediaReviewInterventionService($p,$private);
ok(hash('sha256',$intervention->download(mr6Context('download_source',['media_review_source_download']),$id)['bytes'])===$original['checksum_sha256'],'audited_source_exact');
foreach(['png','webp','jpeg'] as $format){$old=mr6Rows($p,$id);$u=mr6File($format);$hash=hash_file('sha256',$u['tmp_name']);try{$intervention->corrected(mr6Context('upload_corrected',['media_review_corrected_upload']),$id,$u);}finally{unlink($u['tmp_name']);}$rows=mr6Rows($p,$id);ok($rows['SOURCE']===$original && $rows['CORRECTED']['checksum_sha256']===$hash,'corrected_exact_source_immutable_'.$format);ok(!$private->exists($old['REVIEW']['storage_key']) && $pubState()===$before,'atomic_review_replacement_public_unchanged');}
$result=(new Media\Services\PhysicianLogoApprovalService($p,$private,$public))->approve(mr5Context(),$id);
$asset=(new Media\Repositories\MediaAssetsRepository($p))->findByPublicUrl($result['public_url']);ok(bytes($public,$asset)===bytes($private,$rows['REVIEW']),'publication_is_new_review_bytes');
$saved=$p->query('SELECT payload_json FROM mr7_document_snapshot')->fetchColumn();ok($saved===$historical,'historical_document_payload_unchanged');
$old=(new Media\Repositories\MediaAssetsRepository($p))->findByPublicUrl(json_decode($saved,true)['branding']['logo_url_resolved']);ok($old['status']==='READY' && hash('sha256',bytes($public,$old))===$f['old']['checksum_sha256'],'historical_snapshot_url_still_resolves');
ok((new Media\Repositories\LogoReferenceRepository($p))->physician($doctor)['logo_url']===$result['public_url'],'existing_canonical_branding_reader_new_logo');
ok((new Media\Services\ProfilePhotoReviewCandidateService($p,$private))->current($doctor)===$photo,'photo_candidate_unchanged');
$u=mr6File();try{$candidates->upload($doctor,$u);}finally{unlink($u['tmp_name']);}$before=$pubState();$candidates->withdraw($doctor);ok($candidates->current($doctor)===null && $pubState()===$before,'withdraw_private_only');
echo "PHYSICIAN_LOGO_REVIEW_QA=PASS\n";
