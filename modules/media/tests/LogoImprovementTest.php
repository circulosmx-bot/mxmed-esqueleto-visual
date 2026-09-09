<?php
declare(strict_types=1);
require __DIR__.'/LogoImprovementFixture.php';
use Media\Services\LogoImprovementService as Service;
function ok(bool $v,string $name):void{if(!$v)throw new RuntimeException('FAIL '.$name);echo "PASS $name\n";}
function readBytes($storage,array $f):string{$o=$storage->openReadStream($f['storage_key']);try{return stream_get_contents($o['stream']);}finally{fclose($o['stream']);}}
$p=mr5Pdo();[$private,$public]=mr5Storage();$s=new Service($p,$private);
$pub=fn()=>json_encode([$p->query('SELECT * FROM profiles_doctors ORDER BY doctor_id')->fetchAll(),$p->query('SELECT * FROM media_assets ORDER BY media_id')->fetchAll()]);
foreach(['generate','accept','discard'] as $action){
 $f=mr8Candidate();$before=$pub();$old=mr6Rows($p,$f['id']);
 foreach([null,mr8Context($action,[]),mr8Context($action,['media_review_read']),mr8Context($action,['media_review_approve']),mr8Context($action,['media_review_corrected_upload'])] as $context){try{$s->mutate($context,$action,$f['id']);throw new LogicException('allowed');}catch(RuntimeException $e){ok($e->getMessage()==='improvement_denied','exact_capability_'.$action);}}
 if($action!=='generate')$s->mutate(mr8Context('generate'),'generate',$f['id']);
 $files=mr6Rows($p,$f['id']);$audit=$p->query('SELECT COUNT(*) FROM platform_audit_events')->fetchColumn();
 $p->exec("CREATE TRIGGER mr8_audit_failure BEFORE INSERT ON platform_audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic_failure'");
 try{$s->mutate(mr8Context($action),$action,$f['id']);throw new LogicException('audit bypass');}catch(RuntimeException|PDOException $e){ok(mr6Rows($p,$f['id'])===$files&&$pub()===$before&&$p->query('SELECT COUNT(*) FROM platform_audit_events')->fetchColumn()===$audit,'audit_failure_atomic_'.$action);}finally{$p->exec('DROP TRIGGER mr8_audit_failure');}
}
foreach(['white','offwhite','thin','gradient','photo','variance','edge','transparent'] as $kind){
 $f=mr8Candidate($kind);$before=$pub();$old=mr6Rows($p,$f['id']);$result=$s->mutate(mr8Context('generate'),'generate',$f['id']);$files=mr6Rows($p,$f['id']);
 ok($files['SOURCE']===$old['SOURCE']&&$files['REVIEW']===$old['REVIEW']&&$pub()===$before,'generation_keeps_source_review_public_'.$kind);
 if(!in_array($kind,['white','offwhite','thin'],true)){ok(!isset($files['AUTO_PROPOSAL'])&&in_array($result['status'],['NO_SAFE_IMPROVEMENT','ALREADY_TRANSPARENT'],true),'no_broken_proposal_'.$kind);continue;}
 $proposal=$files['AUTO_PROPOSAL'];ok($proposal['input_file_id']===$files['SOURCE']['file_id']&&$proposal['input_checksum_sha256']===$files['SOURCE']['checksum_sha256'],'source_fingerprint');
 $s->mutate(mr8Context('generate'),'generate',$f['id']);$files=mr6Rows($p,$f['id']);ok(count($files)===3&&!$private->exists($proposal['storage_key']),'bounded_repeated_proposal');$proposal=$files['AUTO_PROPOSAL'];
 $result=$s->mutate(mr8Context('accept'),'accept',$f['id']);$files=mr6Rows($p,$f['id']);ok(!isset($files['AUTO_PROPOSAL'])&&$files['REVIEW']['checksum_sha256']===$proposal['checksum_sha256']&&$files['SOURCE']===$old['SOURCE']&&$pub()===$before,'accept_private_only');
 ok($p->query("SELECT review_status FROM media_review_submissions WHERE submission_id='".$f['id']."'")->fetchColumn()==='PENDING_REVIEW','accept_stays_pending');
 $result=(new Media\Services\PhysicianLogoApprovalService($p,$private,$public))->approve(mr5Context(),$f['id']);$asset=(new Media\Repositories\MediaAssetsRepository($p))->findByPublicUrl($result['public_url']);ok(readBytes($public,$asset)===readBytes($private,$files['REVIEW']),'explicit_approval_exact_improved_review');
 foreach(['generate','accept','discard'] as $a)try{$s->mutate(mr8Context($a),$a,$f['id']);throw new LogicException('reopened');}catch(RuntimeException $e){ok($e->getMessage()==='improvement_conflict','approved_blocks_'.$a);}
}
$f=mr8Candidate();$before=$pub();$original=mr6Rows($p,$f['id']);$s->mutate(mr8Context('generate'),'generate',$f['id']);$s->mutate(mr8Context('discard'),'discard',$f['id']);ok(mr6Rows($p,$f['id'])===$original&&$pub()===$before,'discard_keeps_review_public');
$correct=new Media\Services\MediaReviewInterventionService($p,$private);$u=mr8Upload('offwhite');try{$correct->corrected(mr6Context('upload_corrected',['media_review_corrected_upload']),$f['id'],$u);}finally{unlink($u['tmp_name']);}
$s->mutate(mr8Context('generate'),'generate',$f['id']);$files=mr6Rows($p,$f['id']);ok($files['AUTO_PROPOSAL']['input_file_id']===$files['CORRECTED']['file_id']&&$files['AUTO_PROPOSAL']['input_checksum_sha256']===$files['CORRECTED']['checksum_sha256'],'corrected_precedence');
$p->prepare("UPDATE media_review_files SET input_file_id=UUID() WHERE submission_id=? AND role='AUTO_PROPOSAL'")->execute([$f['id']]);
try{$s->mutate(mr8Context('accept'),'accept',$f['id']);throw new LogicException('stale accepted');}catch(RuntimeException $e){ok($e->getMessage()==='improvement_conflict','fingerprint_stale_blocked');}
$s->mutate(mr8Context('generate'),'generate',$f['id']);$u=mr8Upload();try{$correct->corrected(mr6Context('upload_corrected',['media_review_corrected_upload']),$f['id'],$u);}finally{unlink($u['tmp_name']);}$files=mr6Rows($p,$f['id']);
try{$s->mutate(mr8Context('accept'),'accept',$f['id']);throw new LogicException('stale accepted');}catch(RuntimeException $e){ok($e->getMessage()==='improvement_conflict'&&!isset($files['AUTO_PROPOSAL']),'new_corrected_invalidates_proposal');}
$s->mutate(mr8Context('generate'),'generate',$f['id']);$beforeFiles=mr6Rows($p,$f['id']);$s->mutate(mr8Context('accept'),'accept',$f['id']);$afterFiles=mr6Rows($p,$f['id']);ok($afterFiles['SOURCE']===$beforeFiles['SOURCE']&&$afterFiles['CORRECTED']===$beforeFiles['CORRECTED'],'accept_retains_source_corrected');
$photo=mr5Candidate($p);try{$s->mutate(mr8Context('generate'),'generate',$photo['id']);throw new LogicException('photo improved');}catch(RuntimeException $e){ok($e->getMessage()==='improvement_conflict','photo_not_in_scope');}
$rows=$p->query("SELECT * FROM platform_audit_events WHERE action LIKE 'MEDIA_LOGO_IMPROVEMENT_%'")->fetchAll();foreach($rows as $row){ok($row['risk_level']==='R1'&&!str_contains($row['metadata_json'],'private/')&&$row['real_actor_reference']==='account:mr6_synthetic_operator','safe_canonical_audit');}
echo "MR8_SERVICE_QA=PASS\n";
