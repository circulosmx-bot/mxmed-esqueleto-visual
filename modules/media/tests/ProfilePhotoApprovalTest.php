<?php
declare(strict_types=1);
require __DIR__.'/ProfilePhotoApprovalFixture.php';
use Media\Services\ProfilePhotoApprovalService;
use Media\Contracts\{PrivateMediaStoragePort,PublicMediaStoragePort};
function check(bool $ok,string $name): void { if (!$ok) throw new RuntimeException('FAIL '.$name);echo 'PASS '.$name.PHP_EOL; }
function state(PDO $p,array $f): string {
    $result=[];
    foreach (["SELECT * FROM profiles_doctors WHERE doctor_id=?"=>$f['doctor'],"SELECT * FROM media_assets WHERE owner_id=? ORDER BY media_id"=>$f['doctor'],
        'SELECT * FROM media_review_submissions WHERE submission_id=?'=>$f['id'], 'SELECT * FROM media_review_files WHERE submission_id=? ORDER BY file_id'=>$f['id'],
        'SELECT * FROM platform_audit_events WHERE resource_reference=?'=>$f['id'], 'SELECT * FROM platform_audit_stream_heads WHERE stream_key=?'=>'media_review_submission:'.$f['id']] as $q=>$id) {
        $s=$p->prepare($q);$s->execute([$id]);$result[]=$s->fetchAll();
    }
    return json_encode($result);
}
function publicFiles(): array {
    $root=getenv('MR5_FIXTURE_ROOT').'/public';$files=[];
    if(is_dir($root))foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS)) as $f)if($f->isFile())$files[$f->getPathname()]=hash_file('sha256',$f->getPathname());
    ksort($files);return $files;
}
final class BrokenPrivate implements PrivateMediaStoragePort {
    public function __construct(private PrivateMediaStoragePort $inner,private string $mode){}
    public function storeImmutable(string $k,string $p):void{$this->inner->storeImmutable($k,$p);}
    public function delete(string $k):void{$this->inner->delete($k);}
    public function exists(string $k):bool{return $this->inner->exists($k);}
    public function openReadStream(string $k):array{
        if($this->mode==='missing')throw new RuntimeException('synthetic_missing');
        $o=$this->inner->openReadStream($k);$bytes=stream_get_contents($o['stream']);fclose($o['stream']);
        if($this->mode==='checksum')$bytes[25]=chr(ord($bytes[25])^1);else $bytes.='x';
        $s=fopen('php://temp','w+b');fwrite($s,$bytes);rewind($s);return ['stream'=>$s,'bytes'=>strlen($bytes)];
    }
}
final class BrokenPublic implements PublicMediaStoragePort {
    public function __construct(private PublicMediaStoragePort $inner,private string $mode){}
    public function storeImmutable(string $k,string $p):void{if($this->mode==='store')throw new RuntimeException('synthetic_store_failure');$this->inner->storeImmutable($k,$p);}
    public function delete(string $k):void{if($this->mode==='cleanup')throw new RuntimeException('synthetic_cleanup_failure');$this->inner->delete($k);}
    public function exists(string $k):bool{return $this->inner->exists($k);}
    public function openReadStream(string $k):array{return $this->inner->openReadStream($k);}
}
class FailedCommitPdo extends PDO { public function commit():bool{return false;} }
class LostCommitAcknowledgementPdo extends PDO { public function commit():bool{parent::commit();throw new RuntimeException('synthetic_lost_commit_ack');} }
$p=mr5Pdo();[$private,$public]=mr5Storage();$service=new ProfilePhotoApprovalService($p,$private,$public);
$f=mr5Candidate($p);$before=state($p,$f);$files=publicFiles();
foreach ([null,mr5Context([]),mr5Context(['media_review_read']),mr5Context(['media_review_approve']),mr5Context(['media_review_read','media_review_approve'],'media_review_local_fixture')] as $context) {
    try {$service->approve($context,$f['id']);throw new LogicException('unexpected success');}catch(RuntimeException $e){check($e->getMessage()==='approval_denied','authority_denied');}
    check(state($p,$f)===$before && publicFiles()===$files,'denied_no_mutation');
}
foreach (['missing','checksum','length','store','media_insert','profile_update','candidate_update','audit_insert','audit_cas','commit'] as $failure) {
    $f=mr5Candidate($p);$before=state($p,$f);$files=publicFiles();$trigger=null;
    $failures=['media_insert'=>['INSERT','media_assets'],'profile_update'=>['UPDATE','profiles_doctors'],'candidate_update'=>['UPDATE','media_review_submissions'],'audit_insert'=>['INSERT','platform_audit_events'],'audit_cas'=>['UPDATE','platform_audit_stream_heads']];
    if(isset($failures[$failure])){[$op,$table]=$failures[$failure];$trigger='mr5_failure';$p->exec("CREATE TRIGGER $trigger BEFORE $op ON $table FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic_failure'");}
    $db=$failure==='commit'?new FailedCommitPdo('mysql:host=127.0.0.1;port=3309;dbname=mxmed','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]):$p;
    $sut=new ProfilePhotoApprovalService($db,in_array($failure,['missing','checksum','length'],true)?new BrokenPrivate($private,$failure):$private,$failure==='store'?new BrokenPublic($public,'store'):$public);
    try {$sut->approve(mr5Context(),$f['id']);throw new LogicException('unexpected success '.$failure);}
    catch(RuntimeException|PDOException $e){check(state($p,$f)===$before && publicFiles()===$files,'atomic_failure_'.$failure);}
    finally{if($trigger)$p->exec('DROP TRIGGER '.$trigger);}
}
foreach (['APPROVED','WITHDRAWN','REJECTED','NEEDS_WORK'] as $status) {
    $f=mr5Candidate($p);$p->prepare('UPDATE media_review_submissions SET review_status=? WHERE submission_id=?')->execute([$status,$f['id']]);$before=state($p,$f);
    try{$service->approve(mr5Context(),$f['id']);throw new LogicException('unexpected success');}catch(RuntimeException $e){check($e->getMessage()==='approval_conflict' && state($p,$f)===$before,'terminal_'.$status);}
}
foreach (['purpose','technical','relationship','dimensions'] as $invalid) {
    $f=mr5Candidate($p);
    $sql=match($invalid){'purpose'=>"UPDATE media_review_submissions SET purpose='PHYSICIAN_PERSONAL_LOGO' WHERE submission_id=?",'technical'=>"UPDATE media_review_submissions SET technical_status='FAILED' WHERE submission_id=?",'relationship'=>"DELETE FROM media_review_files WHERE role='SOURCE' AND submission_id=?",default=>"UPDATE media_review_files SET width=width-1 WHERE role='REVIEW' AND submission_id=?"};
    $p->prepare($sql)->execute([$f['id']]);$before=state($p,$f);$files=publicFiles();
    try{$service->approve(mr5Context(),$f['id']);throw new LogicException('unexpected success');}catch(RuntimeException $e){check(state($p,$f)===$before&&publicFiles()===$files,'ineligible_'.$invalid);}
}
foreach (['normal','cleanup'] as $mode) {
    $f=mr5Candidate($p);$s=$p->prepare("SELECT * FROM media_review_files WHERE submission_id=? ORDER BY role");$s->execute([$f['id']]);$history=$s->fetchAll();
    $sut=new ProfilePhotoApprovalService($p,$private,$mode==='cleanup'?new BrokenPublic($public,'cleanup'):$public);
    $result=$sut->approve(mr5Context(),$f['id']);
    check($result['ok'] && $result['review_status']==='APPROVED' && $result['public_url']!==$f['old']['public_url'],'success_'.$mode);
    $s=$p->prepare('SELECT photo_url FROM profiles_doctors WHERE doctor_id=?');$s->execute([$f['doctor']]);check($s->fetchColumn()===$result['public_url'],'profile_canonical_url');
    $s=$p->prepare("SELECT * FROM media_assets WHERE owner_id=? AND status='READY' AND classification='PUBLIC'");$s->execute([$f['doctor']]);$rows=$s->fetchAll();check(count($rows)===1,'one_canonical_asset');
    $review=array_values(array_filter($history,fn($r)=>$r['role']==='REVIEW'))[0];$o=$public->openReadStream($rows[0]['storage_key']);$bytes=stream_get_contents($o['stream']);fclose($o['stream']);
    check(hash('sha256',$bytes)===$review['checksum_sha256'] && strlen($bytes)<=153600 && max($rows[0]['width'],$rows[0]['height'])<=800,'review_equals_public_bytes');
    check($public->exists($f['old']['storage_key'])===($mode==='cleanup'),'old_physical_cleanup_'.$mode);
    $s=$p->prepare('SELECT * FROM media_review_files WHERE submission_id=? ORDER BY role');$s->execute([$f['id']]);check($s->fetchAll()===$history,'private_history_retained');
    foreach($history as $file)check($private->exists($file['storage_key']),'private_file_retained');
    $s=$p->prepare('SELECT * FROM platform_audit_events WHERE resource_reference=?');$s->execute([$f['id']]);$audit=$s->fetchAll();check(count($audit)===1,'one_canonical_audit');$event=$audit[0];$metadata=json_decode($event['metadata_json'],true);
    check($event['action']==='MEDIA_PROFILE_PHOTO_APPROVED' && $event['risk_level']==='R1' && $event['outcome']==='SUCCESS'
        && $event['real_actor_reference']==='account:mr5_synthetic_operator' && $event['effective_actor_reference']===$event['real_actor_reference']
        && $metadata['producer_metadata']==['physician_id'=>$f['doctor'],'published_media_id'=>$result['published_media_id'],'submission_id'=>$f['id']]
        && !str_contains($event['metadata_json'],'private/') && $event['request_id']!=='' && $event['correlation_id']!=='','audit_actor_metadata');
    $s=$p->prepare("SELECT last_event_hash FROM platform_audit_stream_heads WHERE stream_key=?");$s->execute(['media_review_submission:'.$f['id']]);check($s->fetchColumn()===$event['event_hash'],'canonical_chain_advanced');
    $before=state($p,$f);$files=publicFiles();try{$sut->approve(mr5Context(),$f['id']);throw new LogicException('unexpected repeat');}catch(RuntimeException $e){check($e->getMessage()==='approval_conflict' && state($p,$f)===$before && publicFiles()===$files,'repeat_no_duplicate');}
}
$f=mr5Candidate($p);$db=new LostCommitAcknowledgementPdo('mysql:host=127.0.0.1;port=3309;dbname=mxmed','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
try{(new ProfilePhotoApprovalService($db,$private,$public))->approve(mr5Context(),$f['id']);throw new LogicException('unexpected acknowledgement');}
catch(RuntimeException $e){check($e->getMessage()==='synthetic_lost_commit_ack','ambiguous_commit_not_reported_success');}
$s=$p->prepare("SELECT storage_key FROM media_assets WHERE owner_id=? AND status='READY'");$s->execute([$f['doctor']]);$key=$s->fetchColumn();check(is_string($key)&&$public->exists($key),'ambiguous_commit_public_object_preserved');
try{$service->approve(mr5Context(),$f['id']);throw new LogicException('unexpected duplicate');}catch(RuntimeException $e){check($e->getMessage()==='approval_conflict','ambiguous_commit_retry_conflict');}
echo "PROFILE_PHOTO_APPROVAL_SERVICE_QA=PASS\n";
