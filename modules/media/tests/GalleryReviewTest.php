<?php
declare(strict_types=1);
require __DIR__.'/GalleryReviewFixture.php';
use Media\Services\{GalleryReviewCandidateService as Candidate,GalleryCapacity,GalleryApprovalService as Approval};
function ok(bool $v,string $name):void{if(!$v)throw new RuntimeException('FAIL '.$name);echo "PASS $name\n";}
$p=mr5Pdo();[$private,$public]=mr5Storage();$candidate=new Candidate($p,$private);$approval=new Approval($p,$private,$public);
$state=fn()=>json_encode([$p->query('SELECT * FROM profiles_doctors ORDER BY doctor_id')->fetchAll(),$p->query('SELECT * FROM media_assets ORDER BY media_id')->fetchAll()]);
foreach([0,8] as $publicCount){$doctor=mr10Doctor();mr10SeedPublic($doctor,$publicCount);$old=$state();$ids=[];for($i=0;$i<16-$publicCount;$i++)$ids[]=mr10Candidate($doctor)['id'];
 ok(count(array_unique($ids))===16-$publicCount&&$state()===$old,'independent_private_candidates_'.$publicCount);
 foreach($ids as $id){$files=mr6Rows($p,$id);ok(array_keys($files)===['SOURCE','REVIEW'],'source_review_only');}
 $before=$candidate->listing($doctor);try{mr10Candidate($doctor);throw new LogicException('over_limit');}catch(RuntimeException $e){ok($e->getMessage()==='gallery_limit_reached'&&$candidate->listing($doctor)===$before,'combined_limit_16_'.$publicCount);}
 (new Media\Services\MediaReplacementService($p,$private))->request(mr9Context(),$ids[0],'OTHER');$new=mr10Candidate($doctor);ok($p->query("SELECT review_status FROM media_review_submissions WHERE submission_id='".$ids[0]."'")->fetchColumn()==='NEEDS_WORK'&&GalleryCapacity::counts($p,$doctor)===['public'=>$publicCount,'pending'=>16-$publicCount],'needs_work_frees_slot');
 $candidate->withdraw($doctor,$new['id']);ok(GalleryCapacity::counts($p,$doctor)['pending']===15-$publicCount,'withdraw_one_only');
 try{$candidate->withdraw('unrelated',$ids[1]);throw new LogicException('cross_owner');}catch(RuntimeException $e){ok($e->getMessage()==='gallery_candidate_not_found','withdraw_owner_scope');}
}
$f=mr10Candidate();$before=$state();$files=mr6Rows($p,$f['id']);$intervention=new Media\Services\MediaReviewInterventionService($p,$private);
$download=$intervention->download(mr6Context('download_source',['media_review_source_download']),$f['id']);ok(hash('sha256',$download['bytes'])===$files['SOURCE']['checksum_sha256'],'audited_source_download');
$u=mr8Upload('white');try{$intervention->corrected(mr6Context('upload_corrected',['media_review_corrected_upload']),$f['id'],$u);}finally{unlink($u['tmp_name']);}$after=mr6Rows($p,$f['id']);ok($after['SOURCE']===$files['SOURCE']&&count($after)===3&&isset($after['CORRECTED'])&&$state()===$before,'corrected_private_no_lossless');
try{(new Media\Services\LogoImprovementService($p,$private))->mutate(mr8Context('generate'),'generate',$f['id']);throw new LogicException('gallery_improved');}catch(RuntimeException $e){ok($e->getMessage()==='improvement_conflict','gallery_improvement_rejected');}
$profile=$p->query("SELECT * FROM profiles_doctors WHERE doctor_id='".$f['doctor']."'")->fetch();$result=$approval->approve(mr5Context(),$f['id']);$asset=(new Media\Repositories\MediaAssetsRepository($p))->findByPublicUrl($result['public_url']);
ok($asset['purpose']==='DOCTOR_GALLERY'&&$asset['checksum_sha256']===$after['REVIEW']['checksum_sha256']&&GalleryCapacity::counts($p,$f['doctor'])===['public'=>1,'pending'=>0],'exact_review_publication_0_to_1');
ok($p->query("SELECT * FROM profiles_doctors WHERE doctor_id='".$f['doctor']."'")->fetch()===$profile,'no_profile_field_switch');
$audit=$p->query("SELECT * FROM platform_audit_events WHERE action='MEDIA_DOCTOR_GALLERY_APPROVED' AND resource_reference='".$f['id']."'")->fetchAll();
ok(count($audit)===1,'one_required_gallery_approval_audit');
$metadata=json_decode($audit[0]['metadata_json'],true)['producer_metadata'];ksort($metadata);
ok($metadata===['physician_id'=>$f['doctor'],'published_media_id'=>$result['published_media_id'],'submission_id'=>$f['id']],'gallery_audit_exact_resource_metadata');

try{$approval->approve(mr5Context(),$f['id']);throw new LogicException('duplicate');}catch(RuntimeException $e){ok($e->getMessage()==='approval_conflict','no_duplicate_approval');}
foreach([15,16,19] as $n){$f=mr10Candidate();mr10SeedPublic($f['doctor'],$n);$list=(new Media\Repositories\MediaAssetsRepository($p))->listDoctorGallery($f['doctor']);if($n===15){$approval->approve(mr5Context(),$f['id']);ok(GalleryCapacity::counts($p,$f['doctor'])['public']===16,'approval_15_to_16');}else{
 try{$approval->approve(mr5Context(),$f['id']);throw new LogicException('overflow');}catch(RuntimeException $e){ok($e->getMessage()==='approval_conflict'&&GalleryCapacity::counts($p,$f['doctor'])['pending']===1,'approval_rechecks_full_'.$n);}
 try{mr10Candidate($f['doctor']);throw new LogicException('overflow');}catch(RuntimeException $e){ok($e->getMessage()==='gallery_limit_reached','existing_overlimit_blocks_new');}
 ok((new Media\Repositories\MediaAssetsRepository($p))->listDoctorGallery($f['doctor'])===$list,'existing_overlimit_not_truncated');
}}
// The direct-public flow also obeys the same 16 combined slots, with no input-size policy change.
$f=mr10Candidate();mr10SeedPublic($f['doctor'],15);$direct=new Media\Services\DoctorGalleryService($p,$public);$before=$direct->list($f['doctor']);$u=mr8Upload();
try{$direct->upload($f['doctor'],$u);throw new LogicException('direct_consumed_reserved_slot');}catch(RuntimeException $e){ok($e->getMessage()==='gallery_limit_reached'&&$direct->list($f['doctor'])===$before,'direct_respects_pending_reservation');}
$d=mr10Doctor();mr10SeedPublic($d,19);$before=$direct->list($d);$u=mr8Upload();try{$direct->upload($d,$u);throw new LogicException('historical_limit_bypass');}catch(RuntimeException $e){ok($e->getMessage()==='gallery_limit_reached'&&$direct->list($d)===$before,'direct_preserves_all_19_historical_assets');}
foreach(array_slice($before,0,4) as $asset)$direct->delete($d,$asset['media_id']);$u=mr8Upload();$direct->upload($d,$u);ok(count($direct->list($d))===16,'voluntary_deletion_below_limit_allows_upload');

echo "MR10_SERVICE_QA=PASS\n";
