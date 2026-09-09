<?php
declare(strict_types=1);
require __DIR__.'/MediaReplacementFixture.php';
class Mr9CommitFailure extends PDO{public function commit():bool{return false;}}
class Mr9LostAck extends PDO{public function commit():bool{parent::commit();throw new RuntimeException('lost_ack');}}
function verify(bool $v,string $name):void{if(!$v)throw new RuntimeException('FAIL '.$name);echo "PASS $name\n";}
$p=mr5Pdo();[$storage]=mr5Storage();
foreach(['audit','update','delete','commit','lost_ack'] as $mode){
 $f=mr8Candidate();(new Media\Services\LogoImprovementService($p,$storage))->mutate(mr8Context('generate'),'generate',$f['id']);$files=mr6Rows($p,$f['id']);
 $snapshot=fn()=>json_encode([$p->query("SELECT * FROM media_review_submissions WHERE submission_id='".$f['id']."'")->fetch(),mr6Rows($p,$f['id']),$p->query('SELECT * FROM platform_audit_events ORDER BY event_id')->fetchAll(),$p->query('SELECT * FROM platform_audit_stream_heads ORDER BY stream_key')->fetchAll()]);$before=$snapshot();
 $db=match($mode){'commit'=>new Mr9CommitFailure('mysql:host=127.0.0.1;port=3309;dbname=mxmed','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]),'lost_ack'=>new Mr9LostAck('mysql:host=127.0.0.1;port=3309;dbname=mxmed','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]),default=>$p};
 $trigger=match($mode){'audit'=>'BEFORE INSERT ON platform_audit_events','update'=>'BEFORE UPDATE ON media_review_submissions','delete'=>'BEFORE DELETE ON media_review_files',default=>null};
 if($trigger)$p->exec("CREATE TRIGGER mr9_atomic_failure $trigger FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic_failure'");
 try{(new Media\Services\MediaReplacementService($db,$storage))->request(mr9Context(),$f['id'],'OTHER');throw new LogicException('unexpected_success');}
 catch(RuntimeException|PDOException $e){
  if($mode==='lost_ack')verify($e->getMessage()==='lost_ack'&&$p->query("SELECT review_status FROM media_review_submissions WHERE submission_id='".$f['id']."'")->fetchColumn()==='NEEDS_WORK','lost_ack_committed_state');
  else verify($snapshot()===$before,'atomic_rollback_'.$mode);
  foreach($files as $file)verify($storage->exists($file['storage_key']),'no_binary_cleanup_before_confirmed_commit_'.$mode);
 }finally{if($trigger)$p->exec('DROP TRIGGER mr9_atomic_failure');}
}
foreach(['APPROVED','WITHDRAWN','REJECTED','NEEDS_WORK'] as $state){$f=mr9Candidate();$p->prepare('UPDATE media_review_submissions SET review_status=? WHERE submission_id=?')->execute([$state,$f['id']]);try{(new Media\Services\MediaReplacementService($p,$storage))->request(mr9Context(),$f['id'],'OTHER');throw new LogicException('reopened');}catch(RuntimeException $e){verify($e->getMessage()==='replacement_conflict','ineligible_'.$state);}}
