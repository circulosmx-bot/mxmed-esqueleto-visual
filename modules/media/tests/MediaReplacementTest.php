<?php
declare(strict_types=1);
require __DIR__.'/MediaReplacementFixture.php';
use Media\Services\{MediaReplacementService as Service,PhysicianMediaReviewCandidateService as Owner};
function ok(bool $v,string $name):void{if(!$v)throw new RuntimeException('FAIL '.$name);echo "PASS $name\n";}
$p=mr5Pdo();[$private]=mr5Storage();$s=new Service($p,$private);
$state=fn()=>json_encode([$p->query('SELECT * FROM media_review_submissions ORDER BY submission_id')->fetchAll(),$p->query('SELECT * FROM media_review_files ORDER BY file_id')->fetchAll(),$p->query('SELECT * FROM platform_audit_events ORDER BY event_id')->fetchAll()]);
$public=fn()=>json_encode([$p->query('SELECT * FROM profiles_doctors ORDER BY doctor_id')->fetchAll(),$p->query('SELECT * FROM media_assets ORDER BY media_id')->fetchAll()]);
foreach(['photo','logo'] as $kind){
 $f=mr9Candidate($kind);$before=$public();$rows=mr6Rows($p,$f['id']);$purpose=$kind==='photo'?'DOCTOR_PROFILE_PHOTO':'PHYSICIAN_PERSONAL_LOGO';$owner=new Owner($p,$private,$purpose);
 foreach([null,mr9Context([]),mr9Context(['media_review_read']),mr9Context(['media_review_approve']),mr9Context(['media_review_improve']),mr9Context(['media_review_corrected_upload'])] as $c){$old=$state();try{$s->request($c,$f['id'],'OTHER');throw new LogicException('allowed');}catch(RuntimeException $e){ok($e->getMessage()==='replacement_denied'&&$state()===$old,'separate_capability_'.$kind);}}
 foreach([['',''],['UNKNOWN',''],['OTHER',[]],['OTHER',str_repeat('á',401)],['OTHER',"\xff"]] as [$reason,$feedback]){$old=$state();try{$s->request(mr9Context(),$f['id'],$reason,$feedback);throw new LogicException('invalid accepted');}catch(RuntimeException $e){ok($e->getMessage()==='replacement_invalid_request'&&$state()===$old,'invalid_feedback_no_mutation');}}
 $old=$state();$p->exec("CREATE TRIGGER mr9_audit_failure BEFORE INSERT ON platform_audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic_failure'");
 try{$s->request(mr9Context(),$f['id'],'OTHER','mensaje');throw new LogicException('audit bypass');}catch(RuntimeException|PDOException $e){ok($state()===$old&&$public()===$before,'audit_failure_blocks_all_fields');}finally{$p->exec('DROP TRIGGER mr9_audit_failure');}
 $feedback='Por favor envía otra imagen. <script>alert(1)</script>';
 $s->request(mr9Context(),$f['id'],'WRONG_MEDIA_TYPE','  '.$feedback.'  ');$status=$owner->current($f['doctor']);
 ok($status['review_status']==='NEEDS_WORK'&&$status['technical_status']==='READY'&&$status['reason_code']==='WRONG_MEDIA_TYPE'&&$status['review_feedback']===$feedback&&$status['review_decided_at']!==null,'owner_safe_feedback_'.$kind);
 $p->prepare("UPDATE media_review_submissions SET created_at='2026-09-01 12:00:00' WHERE owner_id=? AND purpose=?")->execute([$f['doctor'],$purpose]);
 ok($owner->current($f['doctor'])['submission_id']===$f['id'],'legacy_timestamp_tie_uses_decision');
 ok(array_keys($status)===['submission_id','technical_status','review_status','reason_code','reason_label','review_feedback','review_decided_at','updated_at'],'owner_allowlist');
 ok($rows===mr6Rows($p,$f['id'])&&$public()===$before,'evidence_public_unchanged');
 $old=$state();try{$s->request(mr9Context(),$f['id'],'OTHER');throw new LogicException('reopened');}catch(RuntimeException $e){ok($e->getMessage()==='replacement_conflict'&&$state()===$old,'duplicate_no_audit_timestamp');}
 $u=mr8Upload();try{$owner->upload($f['doctor'],$u);}finally{unlink($u['tmp_name']);}$new=$owner->current($f['doctor']);
 ok($new['submission_id']!==$f['id']&&$new['review_status']==='PENDING_REVIEW','new_pending_priority_'.$kind);
 ok($p->query("SELECT review_status FROM media_review_submissions WHERE submission_id='".$f['id']."'")->fetchColumn()==='NEEDS_WORK'&&$public()===$before,'historical_needs_work_preserved');
 $newFiles=mr6Rows($p,$new['submission_id']);ok(count($newFiles)===($kind==='photo'?2:3)&&isset($newFiles['SOURCE'],$newFiles['REVIEW'])&&isset($newFiles['IMPROVEMENT_INPUT'])===($kind==='logo'),'new_candidate_roles');
 try{$p->exec("INSERT INTO media_review_submissions(submission_id,owner_type,owner_id,purpose,technical_status,review_status) VALUES(UUID(),'PHYSICIAN','".$f['doctor']."','$purpose','READY','PENDING_REVIEW')");throw new LogicException('duplicate pending');}catch(PDOException $e){ok($e->errorInfo[1]===1062,'pending_uniqueness_'.$kind);}
 foreach($rows as $file){$o=$private->openReadStream($file['storage_key']);$bytes=stream_get_contents($o['stream']);fclose($o['stream']);ok(hash('sha256',$bytes)===$file['checksum_sha256'],'historical_evidence_bytes_retained');}
 $owner->withdraw($f['doctor']);ok($owner->current($f['doctor'])===null,'latest_withdrawn_does_not_resurface_old_feedback');
 $audit=$p->query("SELECT metadata_json FROM platform_audit_events WHERE resource_reference='".$f['id']."' AND action='MEDIA_REVIEW_REPLACEMENT_REQUESTED'")->fetchColumn();ok(!str_contains($audit,'script')&&!str_contains($audit,'feedback')&&str_contains($audit,'WRONG_MEDIA_TYPE'),'safe_audit_no_feedback');
}
$f=mr8Candidate();$improve=new Media\Services\LogoImprovementService($p,$private);$improve->mutate(mr8Context('generate'),'generate',$f['id']);$before=mr6Rows($p,$f['id']);$s->request(mr9Context(),$f['id'],'OTHER',str_repeat('😀',400));$after=mr6Rows($p,$f['id']);$proposal=$before['AUTO_PROPOSAL'];unset($before['AUTO_PROPOSAL']);ok($after===$before&&!$private->exists($proposal['storage_key']),'proposal_cleanup_only_after_commit');
foreach(['generate','accept','discard'] as $a)try{$improve->mutate(mr8Context($a),$a,$f['id']);throw new LogicException('reopened');}catch(RuntimeException $e){ok($e->getMessage()==='improvement_conflict','needs_work_blocks_'.$a);}
echo "MR9_SERVICE_QA=PASS\n";
