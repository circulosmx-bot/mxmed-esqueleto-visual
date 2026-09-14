<?php
declare(strict_types=1);
require __DIR__.'/ReviewBatchFixture.php';
require_once __DIR__.'/../services/BatchOriginals.php';
require_once __DIR__.'/../services/HistoricalOriginalArchive.php';
require_once __DIR__.'/../services/MediaReviewAccessService.php';
use Media\Services\{MediaCandidateSubmissionService as Submit,MediaReviewBatchService as Batch,MediaReviewBatchInboxService as Inbox,MediaReviewAccessService as Access,MediaSubmissionActor as Actor,BatchOriginals};
function mr02Check(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
function mr02Deny(callable $fn,string $error):void{try{$fn();}catch(Throwable $e){mr02Check($e->getMessage()===$error,$error);return;}throw new RuntimeException('expected '.$error);}
$p=mr5Pdo();$submit=new Submit($p);$batch=new Batch($p);$inbox=new Inbox($p);$ctx=mr11ReadContext();[$private,$public]=mr5Storage();
$row=function(string $id)use($p){$s=$p->prepare('SELECT * FROM media_review_submissions WHERE submission_id=?');$s->execute([$id]);return $s->fetch(PDO::FETCH_ASSOC);};
$events=function(string $id)use($p){$s=$p->prepare("SELECT * FROM platform_audit_events WHERE resource_reference=? AND action='MEDIA_REVIEW_CANDIDATE_SUBMITTED' ORDER BY sequence_number");$s->execute([$id]);return $s->fetchAll(PDO::FETCH_ASSOC);};
foreach([[],['PHYSICIAN_PERSONAL_LOGO'],['DOCTOR_GALLERY'],['PHYSICIAN_PERSONAL_LOGO','DOCTOR_GALLERY']] as $purposes){
 $d=mr11Doctor();$actor=mr11Actor($d);$f=mr11Candidate($d,'DOCTOR_PROFILE_PHOTO');$siblings=[];
 foreach($purposes as $purpose){$g=mr11Candidate($d,$purpose);$siblings[$g['id']]=$row($g['id']);}
 $publicBefore=$p->query("SELECT * FROM profiles_doctors WHERE doctor_id='$d'")->fetch();
 mr02Deny(fn()=>(new Access($p,$private))->metadata($ctx,$f['id']),'review_not_found');
 mr02Deny(fn()=>(new Media\Services\ProfilePhotoApprovalService($p,$private,$public))->approve(mr5Context(),$f['id']),'approval_conflict');
 $result=$submit->submit($f['id'],$actor);$submittedRow=$row($f['id']);
 mr02Check($result['submitted_for_review_at']!==null&&!$result['already_submitted'],'individual submitted');
 foreach($siblings as $id=>$before)mr02Check($before===$row($id),'sibling untouched '.$before['purpose']);
 mr02Check($batch->current($d)['item_count']===count($siblings),'pending counter excludes photo');
 mr02Check($p->query("SELECT status FROM media_review_batches WHERE batch_id='{$f['batch_id']}'")->fetchColumn()==='OPEN','accepting session remains open');
 $detail=$inbox->detail($ctx,$f['batch_id']);mr02Check(array_column($detail['items'],'submission_id')===[$f['id']],'reviewer sees photo only in OPEN batch');
 mr02Check((new Access($p,$private))->metadata($ctx,$f['id'])['submission_id']===$f['id'],'review bytes access eligible');
 mr02Check(count(BatchOriginals::load($p,$f['batch_id'])['items'])===1,'archive excludes unsent siblings');
 $archive=BatchOriginals::load($p,$f['batch_id']);foreach($archive['items'] as &$item)$item['review_status']='APPROVED';unset($item);mr02Check(Media\Services\HistoricalOriginalArchive::resolved($archive)===null,'OPEN partial batch never archival/purge eligible');
 $event=$events($f['id']);mr02Check(count($event)===1&&$event[0]['real_actor_reference']==='account:synthetic_owner','one canonical actor event');
 $meta=json_decode($event[0]['metadata_json'],true)['producer_metadata'];mr02Check($meta['physician_id']===$d&&$meta['purpose']==='DOCTOR_PROFILE_PHOTO'&&$meta['submitted_for_review_at']===$result['submitted_for_review_at'],'audit transition matches durable item');
 mr02Check($submit->submit($f['id'],$actor)['already_submitted']&&count($events($f['id']))===1&&$row($f['id'])===$submittedRow,'idempotent retry');
 if($siblings){mr02Check($batch->submit($d,null,$actor),'remaining batch submits');foreach($siblings as $id=>$before)mr02Check($row($id)['submitted_for_review_at']!==null&&count($events($id))===1,'remaining item submitted once');}
 else {$p->exec("UPDATE media_review_batches SET last_activity_at='2001-01-01' WHERE batch_id='{$f['batch_id']}'");$batch->submitInactive();}
 mr02Check($row($f['id'])===$submittedRow&&count($events($f['id']))===1,'batch leaves submitted photo unchanged');
 mr02Check(!$batch->current($d)['has_open_batch'],'completed batch closed');
 mr02Check($p->query("SELECT * FROM profiles_doctors WHERE doctor_id='$d'")->fetch()===$publicBefore,'public/header profile authority untouched');
}
$d=mr11Doctor();$f=mr11Candidate($d);$actor=mr11Actor($d);
mr02Deny(fn()=>Actor::fromSession([],''),'submission_unauthorized');
mr02Deny(fn()=>Actor::fromSession(['user_id'=>'x','doctor_id'=>$d,'active_doctor_id'=>'other'],'s'),'submission_unauthorized');
mr02Deny(fn()=>Actor::fromSession(['user_id'=>'x','doctor_id'=>$d,'role'=>'advisor'],'s'),'submission_unauthorized');
mr02Deny(fn()=>$submit->submit($f['id'],mr11Actor(mr11Doctor())),'submission_not_found');
mr02Deny(fn()=>$submit->submit('not-a-uuid',$actor),'submission_invalid_request');
mr02Deny(fn()=>$submit->submit('00000000-0000-4000-8000-000000000000',$actor),'submission_not_found');
$before=$row($f['id']);
$p->exec("CREATE TRIGGER mr02_audit_failure BEFORE INSERT ON platform_audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='mr02_synthetic_failure'");
try{$submit->submit($f['id'],$actor);throw new LogicException('audit failure accepted');}catch(PDOException){}finally{$p->exec('DROP TRIGGER mr02_audit_failure');}
mr02Check($row($f['id'])===$before&&$events($f['id'])===[],'audit failure rolls back timestamp; candidate recoverable');
mr02Check($submit->submit($f['id'],$actor)['submitted_for_review_at']!==null,'retry after audit failure succeeds');
foreach(['APPROVED','NEEDS_WORK','REJECTED','WITHDRAWN'] as $status){$p->prepare('UPDATE media_review_submissions SET review_status=? WHERE submission_id=?')->execute([$status,$f['id']]);mr02Deny(fn()=>$submit->submit($f['id'],$actor),'submission_conflict');}
// Full-batch audit failure rolls back all items, batch closure, signal and chain heads.
$d=mr11Doctor();$a=mr11Candidate($d,'DOCTOR_PROFILE_PHOTO');$b=mr11Candidate($d,'PHYSICIAN_PERSONAL_LOGO');$ids=[$a['id'],$b['id']];
$p->exec("CREATE TRIGGER mr02_batch_failure BEFORE INSERT ON platform_audit_events FOR EACH ROW BEGIN IF NEW.resource_reference='".max($ids)."' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='mr02_second_item_failure'; END IF; END");
try{$batch->submit($d,null,mr11Actor($d));throw new LogicException('batch audit failure accepted');}catch(PDOException){}finally{$p->exec('DROP TRIGGER mr02_batch_failure');}
foreach($ids as $id)mr02Check($row($id)['submitted_for_review_at']===null&&$events($id)===[],'whole batch rollback');
mr02Check($batch->current($d)['item_count']===2,'failed batch stays pending');
mr02Check($batch->submit($d,null,mr11Actor($d)),'full batch retry succeeds');
$legacy=mr11Legacy();mr02Check($row($legacy['id'])['submitted_for_review_at']===null,'legacy date unknown preserved');
mr02Check((new Access($p,$private))->metadata($ctx,$legacy['id'])['submission_id']===$legacy['id'],'unbatched legacy still readable');
mr02Deny(fn()=>$submit->submit($legacy['id'],mr11Actor($legacy['doctor'])),'submission_legacy_already_reviewable');
class Mr02FailedCommit extends PDO{public function commit():bool{return false;}}
class Mr02LostCommitAck extends PDO{public function commit():bool{parent::commit();throw new RuntimeException('synthetic_lost_ack');}}
foreach([Mr02FailedCommit::class,Mr02LostCommitAck::class] as $class){
 $f=mr11Candidate(mr11Doctor());$db=new $class('mysql:host=127.0.0.1;port=3309;dbname=mxmed','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 try{(new Submit($db))->submit($f['id'],mr11Actor($f['doctor']));throw new LogicException('failure expected');}catch(RuntimeException){}
 $committed=$class===Mr02LostCommitAck::class;mr02Check(($row($f['id'])['submitted_for_review_at']!==null)===$committed&&count($events($f['id']))===($committed?1:0),'item commit/ack outcome '.$class);
 mr02Check($submit->submit($f['id'],mr11Actor($f['doctor']))['already_submitted']===$committed&&count($events($f['id']))===1,'commit/ack retry one event');
}
echo "MR02_ITEM_SUBMISSION_QA=PASS\n";
