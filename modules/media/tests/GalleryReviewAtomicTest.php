<?php
declare(strict_types=1);
require __DIR__.'/GalleryReviewFixture.php';
class Mr10FailedCommit extends PDO {public function commit():bool{return false;}}
class Mr10LostAck extends PDO {public function commit():bool{parent::commit();throw new RuntimeException('lost_ack');}}
class Mr10FailStorage implements Media\Contracts\PrivateMediaStoragePort,Media\Contracts\PublicMediaStoragePort {
 public function __construct(private object $inner,private string $mode){}
 public function exists(string $k):bool{return $this->inner->exists($k);}
 public function delete(string $k):void{$this->inner->delete($k);}
 public function openReadStream(string $k):array{$o=$this->inner->openReadStream($k);if($this->mode==='integrity')$o['bytes']++;return $o;}
 public function storeImmutable(string $k,string $p):void{$this->inner->storeImmutable($k,$p);if($this->mode==='store')throw new RuntimeException('synthetic_failure');}
}
function check(bool $v,string $n):void{if(!$v)throw new RuntimeException('FAIL '.$n);echo "PASS $n\n";}
$p=mr5Pdo();[$private,$public]=mr5Storage();
$state=function()use($p){$a=[];foreach(['media_assets','profiles_doctors','media_review_submissions','media_review_files','platform_audit_events','platform_audit_stream_heads'] as $t)$a[$t]=$p->query("SELECT * FROM $t ORDER BY 1")->fetchAll();return json_encode($a);};
foreach(['candidate','approve'] as $action)foreach(['store','integrity','insert','commit'] as $mode){
 $f=mr10Candidate();$before=$state();$keys=[];$root=getenv('MR5_FIXTURE_ROOT');foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS)) as $file)if($file->isFile())$keys[$file->getPathname()]=hash_file('sha256',$file->getPathname());
 $db=$mode==='commit'?new Mr10FailedCommit('mysql:host=127.0.0.1;port=3309;dbname=mxmed','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]):$p;
 if($mode==='insert')$p->exec('CREATE TRIGGER mr10_failure BEFORE INSERT ON '.($action==='candidate'?'media_review_files':'platform_audit_events')." FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic_failure'");
 $u=null;try{
  if($action==='candidate'){$u=mr8Upload();(new Media\Services\GalleryReviewCandidateService($db,new Mr10FailStorage($private,$mode)))->upload($f['doctor'],$u);}
  else (new Media\Services\GalleryApprovalService($db,$private,new Mr10FailStorage($public,$mode)))->approve(mr5Context(),$f['id']);
  throw new LogicException('unexpected_success');
 }catch(RuntimeException|PDOException $e){check($state()===$before,'atomic_rows_'.$action.'_'.$mode);$after=[];foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS)) as $file)if($file->isFile())$after[$file->getPathname()]=hash_file('sha256',$file->getPathname());ksort($keys);ksort($after);check($after===$keys,'atomic_files_'.$action.'_'.$mode);}
 finally{if($u)unlink($u['tmp_name']);if($mode==='insert')$p->exec('DROP TRIGGER mr10_failure');}
}
$f=mr10Candidate();$db=new Mr10LostAck('mysql:host=127.0.0.1;port=3309;dbname=mxmed','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
try{(new Media\Services\GalleryApprovalService($db,$private,$public))->approve(mr5Context(),$f['id']);throw new LogicException('unexpected_ack');}catch(RuntimeException $e){check($e->getMessage()==='lost_ack','lost_ack');}
$assets=(new Media\Repositories\MediaAssetsRepository($p))->listDoctorGallery($f['doctor']);check(count($assets)===1,'lost_ack_one_public');$asset=(new Media\Repositories\MediaAssetsRepository($p))->findByPublicUrl($assets[0]['public_url']);check($public->exists($asset['storage_key']),'lost_ack_preserves_public_bytes');
