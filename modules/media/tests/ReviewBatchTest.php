<?php
declare(strict_types=1);
require __DIR__.'/ReviewBatchFixture.php';
use Media\Services\{MediaReviewBatchService as Batch,MediaReviewBatchInboxService as Inbox};
function checkBatch(bool $v,string $n):void{if(!$v)throw new RuntimeException($n);echo "PASS $n\n";}
function rejectsBatch(callable $fn,string $n):void{try{$fn();}catch(Throwable){echo "PASS $n\n";return;}throw new RuntimeException($n);}
$p=mr5Pdo();$b=new Batch($p);$inbox=new Inbox($p);$ctx=mr11ReadContext();$f=mr11Batch();$d=$f['doctor'];$id=$f['batch_id'];
checkBatch($b->current($d)['item_count']===18,'18 independent items in one open batch');
checkBatch((int)$p->query("SELECT COUNT(*) FROM media_review_batches WHERE owner_id='$d' AND status='OPEN'")->fetchColumn()===1,'one open batch');
checkBatch(!in_array($id,array_column($inbox->listing($ctx,50)['items'],'batch_id'),true),'open hidden');
checkBatch(!in_array($f['id'],array_column((new Media\Services\MediaReviewInboxService($p))->pending($ctx,50)['items'],'submission_id'),true),'open members not flat');
rejectsBatch(fn()=>$inbox->detail($ctx,$id),'open detail hidden');
checkBatch($b->submit($d)&&!$b->submit($d),'manual idempotency');
$detail=$inbox->detail($ctx,$id);checkBatch($detail['batch']['item_count']===18&&$detail['batch']['pending_count']===18&&count($detail['items'])===18,'18-member detail');
checkBatch(count(array_filter($inbox->listing($ctx,50)['items'],fn($r)=>$r['batch_id']===$id))===1,'one top level card');
checkBatch((int)$p->query("SELECT COUNT(*) FROM media_review_batch_ready_events WHERE batch_id='$id'")->fetchColumn()===1,'single signal');
$gallery=array_values(array_filter($detail['items'],fn($r)=>$r['purpose']==='DOCTOR_GALLERY'));[$private,$public]=mr5Storage();
for($i=0;$i<3;$i++)(new Media\Services\GalleryApprovalService($p,$private,$public))->approve(mr5Context(),$gallery[$i]['submission_id']);
(new Media\Services\MediaReplacementService($p,$private))->request(mr9Context(),$gallery[3]['submission_id'],'OTHER');
$counts=$inbox->detail($ctx,$id)['batch'];checkBatch($counts['item_count']===18&&$counts['pending_count']===14&&$counts['approved_count']===3&&$counts['needs_work_count']===1,'derived partial counts');
$new=mr11Candidate($d);checkBatch($new['batch_id']!==$id&&$b->current($d)['item_count']===1,'replacement joins new open batch');
checkBatch(array_column($inbox->detail($ctx,$id)['items'],'submission_id')===array_column($detail['items'],'submission_id')&&$inbox->detail($ctx,$id)['batch']['item_count']===18,'submitted membership unchanged by later upload');
rejectsBatch(fn()=>$p->exec("INSERT INTO media_review_batches(batch_id,owner_type,owner_id) VALUES(UUID(),'PHYSICIAN','$d')"),'database single open');
$other=mr11Batch(1,false);
rejectsBatch(fn()=>$p->exec("INSERT INTO media_review_submissions(submission_id,owner_type,owner_id,purpose,technical_status,review_status,batch_id) VALUES(UUID(),'PHYSICIAN','$d','DOCTOR_GALLERY','READY','PENDING_REVIEW','".$other['batch_id']."')"),'database cross owner rejected');
foreach([29,31] as $minutes){$x=mr11Batch(1,false);$p->exec("UPDATE media_review_batches SET last_activity_at=DATE_SUB(CURRENT_TIMESTAMP(6),INTERVAL $minutes MINUTE) WHERE batch_id='".$x['batch_id']."'");$b->submitInactive();checkBatch($b->current($x['doctor'])['has_open_batch']===($minutes===29),'inactivity '.$minutes);}
$signals=$p->query('SELECT COUNT(*) FROM media_review_batch_ready_events')->fetchColumn();$b->submitInactive();checkBatch($signals===$p->query('SELECT COUNT(*) FROM media_review_batch_ready_events')->fetchColumn(),'executor idempotent');
$x=mr11Batch(1,false);(new Media\Services\GalleryReviewCandidateService($p,$private))->withdraw($x['doctor'],$x['id']);$p->exec("UPDATE media_review_batches SET last_activity_at=DATE_SUB(CURRENT_TIMESTAMP(6),INTERVAL 31 MINUTE) WHERE batch_id='".$x['batch_id']."'");$b->submitInactive();checkBatch(!$b->submit($x['doctor'])&&!$b->current($x['doctor'])['has_open_batch'],'all withdrawn retired');
$legacy=mr11Legacy();checkBatch(in_array($legacy['id'],array_column((new Media\Services\MediaReviewInboxService($p))->pending($ctx,50)['items'],'submission_id'),true),'legacy visible');
rejectsBatch(fn()=>$inbox->listing(null),'batch requires capability');
checkBatch(!preg_match('/storage_key|SOURCE|feedback|csrf|session|checksum/',json_encode($detail)),'safe batch metadata');
$p->exec("UPDATE media_review_submissions SET review_status='WITHDRAWN' WHERE batch_id='$id' AND review_status='PENDING_REVIEW'");checkBatch(!in_array($id,array_column($inbox->listing($ctx,50)['items'],'batch_id'),true),'resolved absent active queue');
$full=mr11Batch(16,false);rejectsBatch(fn()=>mr11Candidate($full['doctor']),'open gallery consumes capacity');

for($i=0;$i<3;$i++){$x=mr11Batch(1,false);$p->exec("UPDATE media_review_batches SET last_activity_at='2001-01-01' WHERE batch_id='".$x['batch_id']."'");}
$run=$b->submitInactive(2);checkBatch($run['examined']===2&&$run['submitted']===2,'bounded executor run');
$page=$inbox->listing($ctx,1);checkBatch(count($page['items'])===1&&$page['pagination']['has_more'],'bounded batch queue');
$page=$inbox->detail($ctx,$id,5);checkBatch(count($page['items'])===5&&$page['pagination']['next_offset']===5,'bounded batch detail');
echo "MR11_SERVICE_QA=PASS\n";
