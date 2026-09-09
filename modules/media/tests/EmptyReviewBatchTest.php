<?php
declare(strict_types=1);
require __DIR__.'/ReviewBatchFixture.php';
function emptyCheck(bool $v,string $name):void{if(!$v)throw new RuntimeException($name);echo "PASS $name\n";}
$p=mr5Pdo();[$private]=mr5Storage();$batch=new Media\Services\MediaReviewBatchService($p);
foreach(['DOCTOR_PROFILE_PHOTO','PHYSICIAN_PERSONAL_LOGO','DOCTOR_GALLERY'] as $purpose){
 $d=mr11Doctor();$a=mr11Candidate($d,$purpose);$files=mr6Rows($p,$a['id']);
 if($purpose==='DOCTOR_GALLERY')(new Media\Services\GalleryReviewCandidateService($p,$private))->withdraw($d,$a['id']);
 else (new Media\Services\PhysicianMediaReviewCandidateService($p,$private,$purpose))->withdraw($d);
 $row=$p->query("SELECT * FROM media_review_submissions WHERE submission_id='".$a['id']."'")->fetch();
 emptyCheck($row['review_status']==='WITHDRAWN'&&$row['batch_id']===null&&!$batch->current($d)['has_open_batch'],$purpose.' final withdrawal retires grouping, preserves submission');
 emptyCheck(mr6Rows($p,$a['id'])===$files,'private file history metadata preserved');
 if($purpose==='DOCTOR_GALLERY')foreach($files as $file)emptyCheck($private->exists($file['storage_key']),'gallery bytes retained');
 $b=mr11Candidate($d,$purpose);emptyCheck($b['batch_id']!==$a['batch_id'],'new session has new batch id');
 emptyCheck((int)$p->query("SELECT COUNT(*) FROM media_review_batch_ready_events WHERE batch_id='".$a['batch_id']."'")->fetchColumn()===0,'retirement signals zero');
}
$d=mr11Doctor();$a=mr11Candidate($d);$b=mr11Candidate($d);$gallery=new Media\Services\GalleryReviewCandidateService($p,$private);$gallery->withdraw($d,$a['id']);
emptyCheck($batch->current($d)['has_open_batch']&&$batch->current($d)['item_count']===2,'partial withdrawal keeps grouping');
$gallery->withdraw($d,$b['id']);emptyCheck(!$batch->current($d)['has_open_batch'],'last member retires grouping');
emptyCheck((int)$p->query("SELECT COUNT(*) FROM media_review_submissions WHERE owner_id='$d' AND batch_id IS NULL AND review_status='WITHDRAWN'")->fetchColumn()===2,'both historical members detached');
$f=mr11Batch(1,false);$batch->submit($f['doctor']);$later=mr11Candidate($f['doctor']);$gallery->withdraw($f['doctor'],$f['id']);
emptyCheck($p->query("SELECT batch_id FROM media_review_submissions WHERE submission_id='".$f['id']."'")->fetchColumn()===$f['batch_id'],'submitted membership preserved');
emptyCheck($batch->current($f['doctor'])['has_open_batch'],'unrelated current open preserved');
emptyCheck((int)$p->query("SELECT COUNT(*) FROM media_review_batch_ready_events WHERE batch_id='".$f['batch_id']."'")->fetchColumn()===1,'normal signal remains one');
// Failed retirement must roll back withdrawal and membership together.
$f=mr11Batch(1,false);$before=$p->query("SELECT * FROM media_review_submissions WHERE submission_id='".$f['id']."'")->fetch();
$p->exec("CREATE TRIGGER empty_batch_failure BEFORE DELETE ON media_review_batches FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic_failure'");
$failed=false;try{$gallery->withdraw($f['doctor'],$f['id']);}catch(Throwable){$failed=true;}finally{$p->exec('DROP TRIGGER empty_batch_failure');}
emptyCheck($failed&&$p->query("SELECT * FROM media_review_submissions WHERE submission_id='".$f['id']."'")->fetch()===$before&&$batch->current($f['doctor'])['has_open_batch'],'retirement failure rolls back withdrawal');
echo "EMPTY_BATCH_QA=PASS\n";
