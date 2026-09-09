<?php
declare(strict_types=1);
require __DIR__.'/ReviewBatchFixture.php';
class Mr11FailedCommit extends PDO{public function commit():bool{return false;}}
class Mr11LostAck extends PDO{public function commit():bool{parent::commit();throw new RuntimeException('lost_ack');}}
function checkAtomic(bool $v,string $n):void{if(!$v)throw new RuntimeException($n);echo "PASS $n\n";}
$p=mr5Pdo();
$state=function()use($p){$r=[];foreach(['media_review_batches','media_review_batch_ready_events','media_review_submissions','media_review_files','media_assets','profiles_doctors'] as $t)$r[$t]=$p->query("SELECT * FROM $t ORDER BY 1")->fetchAll();return json_encode($r);};
$files=function(){$r=[];foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(getenv('MR5_FIXTURE_ROOT'),FilesystemIterator::SKIP_DOTS)) as $f)if($f->isFile())$r[$f->getPathname()]=hash_file('sha256',$f->getPathname());ksort($r);return $r;};
foreach(['candidate','submit'] as $action)foreach(['insert','commit'] as $failure){
 $d=mr11Doctor();if($action==='submit')mr11Candidate($d);$before=$state();$beforeFiles=$files();
 if($failure==='insert')$p->exec("CREATE TRIGGER mr11_failure BEFORE INSERT ON ".($action==='candidate'?'media_review_submissions':'media_review_batch_ready_events')." FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic_failure'");
 $db=$failure==='commit'?new Mr11FailedCommit('mysql:host=127.0.0.1;port=3309;dbname=mxmed;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]):$p;
 $failed=false;
 try{if($action==='candidate')mr11Candidate($d,'DOCTOR_GALLERY',$db);else (new Media\Services\MediaReviewBatchService($db))->submit($d);}catch(Throwable){$failed=true;}finally{if($failure==='insert')$p->exec('DROP TRIGGER mr11_failure');}
 checkAtomic($failed&&$state()===$before&&$files()===$beforeFiles,$action.' '.$failure.' atomic rollback');
}
$d=mr11Doctor();$f=mr11Candidate($d);$db=new Mr11LostAck('mysql:host=127.0.0.1;port=3309;dbname=mxmed;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
try{(new Media\Services\MediaReviewBatchService($db))->submit($d);}catch(Throwable){}
checkAtomic(!(new Media\Services\MediaReviewBatchService($p))->submit($d),'lost acknowledgement retry no-op');
checkAtomic((int)$p->query("SELECT COUNT(*) FROM media_review_batch_ready_events WHERE batch_id='".$f['batch_id']."'")->fetchColumn()===1,'lost acknowledgement one durable signal');
echo "MR11_ATOMIC_QA=PASS\n";
