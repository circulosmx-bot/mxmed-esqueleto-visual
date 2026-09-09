<?php
declare(strict_types=1);
require __DIR__.'/MediaReviewInterventionFixture.php';
use Media\Services\{MediaReviewInterventionService as Service,ProfilePhotoApprovalService};
use Media\Contracts\PrivateMediaStoragePort;
function mr6Check(bool $ok,string $label):void{if(!$ok)throw new RuntimeException('FAIL '.$label);echo 'PASS '.$label.PHP_EOL;}
function mr6State(PDO $p):string{$all=[];foreach(['media_review_submissions','media_review_files','media_assets','profiles_doctors','platform_audit_events','platform_audit_stream_heads'] as $t)$all[$t]=$p->query('SELECT * FROM '.$t)->fetchAll();return json_encode($all);}
function mr6PrivateFiles():array{$root=getenv('MR5_FIXTURE_ROOT').'/private';$out=[];if(is_dir($root))foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS)) as $f)if($f->isFile())$out[$f->getPathname()]=hash_file('sha256',$f->getPathname());ksort($out);return $out;}
class Mr6BrokenStorage implements PrivateMediaStoragePort {
    public function __construct(private PrivateMediaStoragePort $inner,private string $mode){}
    public function exists(string $k):bool{return $this->inner->exists($k);}
    public function delete(string $k):void{if($this->mode==='cleanup')throw new RuntimeException('synthetic_cleanup_failure');$this->inner->delete($k);}
    public function storeImmutable(string $k,string $p):void{if(($this->mode==='store_corrected'&&str_contains($k,'/corrected/'))||($this->mode==='store_review'&&str_contains($k,'/review/')))throw new RuntimeException('synthetic_storage_failure');$this->inner->storeImmutable($k,$p);}
    public function openReadStream(string $k):array{if($this->mode==='missing')throw new RuntimeException('synthetic_missing');$o=$this->inner->openReadStream($k);if(!in_array($this->mode,['checksum','length'],true))return $o;$b=stream_get_contents($o['stream']);fclose($o['stream']);if($this->mode==='checksum')$b[20]=chr(ord($b[20])^1);else $b.='x';$s=fopen('php://temp','w+b');fwrite($s,$b);rewind($s);return ['stream'=>$s,'bytes'=>strlen($b)];}
}
class Mr6FailedCommit extends PDO{public function commit():bool{return false;}}
$p=mr5Pdo();[$private,$public]=mr5Storage();$service=new Service($p,$private);$f=mr5Candidate($p);$upload=mr6File();
$download=mr6Context('download_source',[Service::DOWNLOAD]);$correct=mr6Context('upload_corrected',[Service::CORRECT]);
foreach(['download_source','upload_corrected'] as $action)foreach([[],['media_review_read'],['media_review_approve'],[$action==='download_source'?Service::CORRECT:Service::DOWNLOAD]] as $caps){
    $before=mr6State($p);$files=mr6PrivateFiles();try{if($action==='download_source')$service->download(mr6Context($action,$caps),$f['id']);else $service->corrected(mr6Context($action,$caps),$f['id'],$upload);throw new LogicException('unexpected authority');}
    catch(RuntimeException $e){mr6Check($e->getMessage()==='intervention_denied'&&mr6State($p)===$before&&mr6PrivateFiles()===$files,'separate_capability_'.$action);}
}
$rows=mr6Rows($p,$f['id']);$result=$service->download($download,$f['id']);mr6Check(hash('sha256',$result['bytes'])===$rows['SOURCE']['checksum_sha256']&&$result['mime']==='image/png'&&preg_match('/^foto-original-[a-f0-9]{8}\.png$/',$result['filename'])===1,'download_exact_attachment');
foreach(['missing','checksum','length'] as $failure){$before=mr6State($p);try{(new Service($p,new Mr6BrokenStorage($private,$failure)))->download($download,$f['id']);throw new LogicException('unexpected download');}catch(RuntimeException){mr6Check(mr6State($p)===$before,'source_integrity_'.$failure);}}
foreach(['store_corrected','store_review','db_insert','audit','commit'] as $failure){
    $before=mr6State($p);$files=mr6PrivateFiles();$db=$failure==='commit'?new Mr6FailedCommit('mysql:host=127.0.0.1;port=3309;dbname=mxmed','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]):$p;
    if(in_array($failure,['db_insert','audit'],true))$p->exec("CREATE TRIGGER mr6_failure BEFORE INSERT ON ".($failure==='audit'?'platform_audit_events':'media_review_files')." FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic_failure'");
    try{(new Service($db,new Mr6BrokenStorage($private,$failure)))->corrected($correct,$f['id'],$upload);throw new LogicException('unexpected correction');}catch(RuntimeException){mr6Check(mr6State($p)===$before&&mr6PrivateFiles()===$files,'atomic_failure_'.$failure);}
    if($failure==='audit'){try{$service->download($download,$f['id']);throw new LogicException('unexpected download');}catch(RuntimeException){mr6Check(mr6State($p)===$before,'audit_blocks_source');}}
    if(in_array($failure,['db_insert','audit'],true))$p->exec('DROP TRIGGER mr6_failure');
}
$publicBefore=json_encode([$p->query('SELECT * FROM media_assets')->fetchAll(),$p->query('SELECT * FROM profiles_doctors')->fetchAll()]);$source=$rows['SOURCE'];
foreach(['jpeg','png','webp'] as $format){
    $u=mr6File($format);$input=file_get_contents($u['tmp_name']);$previous=mr6Rows($p,$f['id']);$result=$service->corrected($correct,$f['id'],$u);unlink($u['tmp_name']);$current=mr6Rows($p,$f['id']);
    mr6Check(count($current)===3&&$current['SOURCE']===$source&&$current['CORRECTED']['checksum_sha256']===hash('sha256',$input),'private_exact_corrected_'.$format);
    mr6Check($current['REVIEW']['checksum_sha256']!==$previous['REVIEW']['checksum_sha256']&&!$private->exists($previous['REVIEW']['storage_key']),'current_review_replaced_'.$format);
    if(isset($previous['CORRECTED']))mr6Check(!$private->exists($previous['CORRECTED']['storage_key']),'old_corrected_retired');
    $o=$private->openReadStream($current['REVIEW']['storage_key']);$b=stream_get_contents($o['stream']);fclose($o['stream']);$size=getimagesizefromstring($b);
    mr6Check(strlen($b)<=153600&&max($size[0],$size[1])<=800&&!str_contains($b,'Exif')&&!str_contains($b,'GPS=')&&!str_contains($b,'XMP '),'normalized_metadata_stripped');
    if($format==='jpeg')mr6Check($size[0]===360&&$size[1]===640,'EXIF_orientation');
    else{$im=imagecreatefromstring($b);mr6Check((imagecolorat($im,0,0)>>24&127)>100&&$size[0]/$size[1]===640/360,'transparency_aspect');}
    mr6Check($result['technical_status']==='READY'&&$result['review_status']==='PENDING_REVIEW','pending_retained');
}
mr6Check($publicBefore===json_encode([$p->query('SELECT * FROM media_assets')->fetchAll(),$p->query('SELECT * FROM profiles_doctors')->fetchAll()]),'public_tables_unchanged');
$auditSubmission=$f['id'];$current=mr6Rows($p,$f['id']);$published=(new ProfilePhotoApprovalService($p,$private,$public))->approve(mr5Context(),$f['id']);$s=$p->prepare('SELECT checksum_sha256 FROM media_assets WHERE media_id=?');$s->execute([$published['published_media_id']]);mr6Check($s->fetchColumn()===$current['REVIEW']['checksum_sha256'],'MR5_publishes_corrected_REVIEW');
foreach(['download','corrected'] as $op){try{if($op==='download')$service->download($download,$f['id']);else $service->corrected($correct,$f['id'],$upload);throw new LogicException('reopened');}catch(RuntimeException $e){mr6Check($e->getMessage()==='intervention_conflict','approved_conflict_'.$op);}}
unlink($upload['tmp_name']);
$f=mr5Candidate($p);
foreach(['mime','extension','malformed','bytes','pixels','side','svg'] as $invalid){
    $u=mr6File('jpeg',$invalid==='pixels'?5001:($invalid==='side'?8193:64),$invalid==='pixels'?5000:32);
    if($invalid==='mime')file_put_contents($u['tmp_name'],'not an image');
    if($invalid==='extension')$u['name']='photo.png';
    if($invalid==='malformed'){ $b=file_get_contents($u['tmp_name']);file_put_contents($u['tmp_name'],substr($b,0,200)); }
    if($invalid==='bytes'){$h=fopen($u['tmp_name'],'ab');ftruncate($h,10485761);fclose($h);}
    if($invalid==='svg'){file_put_contents($u['tmp_name'],'<svg xmlns="http://www.w3.org/2000/svg"></svg>');$u['name']='file.svg';}
    $before=mr6State($p);$files=mr6PrivateFiles();try{$service->corrected($correct,$f['id'],$u);throw new LogicException('invalid accepted '.$invalid);}catch(RuntimeException){mr6Check(mr6State($p)===$before&&mr6PrivateFiles()===$files,'reject_'.$invalid);}finally{unlink($u['tmp_name']);}
}
$events=$p->query("SELECT action,risk_level,real_actor_reference,effective_actor_reference,metadata_json FROM platform_audit_events WHERE resource_reference='$auditSubmission' AND action IN ('MEDIA_REVIEW_SOURCE_DOWNLOADED','MEDIA_REVIEW_CORRECTED_UPLOADED')")->fetchAll();
foreach($events as $e)mr6Check($e['risk_level']==='R1'&&$e['real_actor_reference']==='account:mr6_synthetic_operator'&&$e['effective_actor_reference']===$e['real_actor_reference']&&!str_contains($e['metadata_json'],'private/')&&!str_contains($e['metadata_json'],'storage_key'),'canonical_audit_safe_actor');
echo "MR6_SERVICE_QA=PASS\n";
