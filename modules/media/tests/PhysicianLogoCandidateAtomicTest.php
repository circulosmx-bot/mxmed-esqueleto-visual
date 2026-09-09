<?php
declare(strict_types=1);
require __DIR__.'/PhysicianLogoReviewFixture.php';
use Media\Contracts\PrivateMediaStoragePort;
final class CandidateFaultStorage implements PrivateMediaStoragePort {
 public function __construct(private PrivateMediaStoragePort $inner,private string $mode){}
 public function storeImmutable(string $key,string $path):void{if($this->mode==='store'&&str_contains($key,'/review/'))throw new RuntimeException('synthetic_store_failure');$this->inner->storeImmutable($key,$path);}
 public function exists(string $key):bool{return $this->inner->exists($key);}
 public function delete(string $key):void{$this->inner->delete($key);}
 public function openReadStream(string $key):array{$o=$this->inner->openReadStream($key);if($this->mode==='integrity'){$o['bytes']++;}return $o;}
}
class CandidateFailedCommit extends PDO{public function commit():bool{return false;}}
class CandidateLostAck extends PDO{public function commit():bool{parent::commit();throw new RuntimeException('synthetic_lost_ack');}}
$p=mr5Pdo();[$private,$public]=mr5Storage();$f=mr7Candidate($p);$service=new Media\Services\PhysicianLogoReviewCandidateService($p,$private);
$state=fn()=>json_encode([$p->query('SELECT * FROM profiles_doctors ORDER BY doctor_id')->fetchAll(),$p->query('SELECT * FROM media_assets ORDER BY media_id')->fetchAll(),$p->query('SELECT * FROM media_review_submissions ORDER BY submission_id')->fetchAll(),$p->query('SELECT * FROM media_review_files ORDER BY file_id')->fetchAll()]);
$objects=function(){ $result=[];foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(getenv('MR5_FIXTURE_ROOT').'/private',FilesystemIterator::SKIP_DOTS)) as $file)if($file->isFile())$result[$file->getPathname()]=hash_file('sha256',$file->getPathname());ksort($result);return $result;};
foreach(['store','integrity','insert','commit'] as $mode){
 $before=$state();$files=$objects();$u=mr6File();
 $db=$mode==='commit'?new CandidateFailedCommit('mysql:host=127.0.0.1;port=3309;dbname=mxmed','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]):$p;
 if($mode==='insert')$p->exec("CREATE TRIGGER mr7_candidate_failure BEFORE INSERT ON media_review_files FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic_failure'");
 try{(new Media\Services\PhysicianLogoReviewCandidateService($db,new CandidateFaultStorage($private,$mode)))->upload($f['doctor'],$u);throw new LogicException('unexpected success');}catch(RuntimeException|PDOException $e){if($state()!==$before||$objects()!==$files)throw new LogicException('candidate_rollback_failed_'.$mode);echo "PASS candidate_rollback_$mode\n";}
 finally{unlink($u['tmp_name']);if($mode==='insert')$p->exec('DROP TRIGGER mr7_candidate_failure');}
}
$db=new CandidateLostAck('mysql:host=127.0.0.1;port=3309;dbname=mxmed','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$u=mr6File();
try{(new Media\Services\PhysicianLogoReviewCandidateService($db,$private))->upload($f['doctor'],$u);throw new LogicException('unexpected acknowledgement');}catch(RuntimeException $e){if($e->getMessage()!=='synthetic_lost_ack')throw $e;}finally{unlink($u['tmp_name']);}
$current=$service->current($f['doctor']);if($current['submission_id']===$f['id'])throw new LogicException('lost_ack_not_committed');
foreach(mr6Rows($p,$current['submission_id']) as $row)if(!$private->exists($row['storage_key']))throw new LogicException('committed_candidate_object_deleted');
echo "PASS lost_commit_ack_preserves_authoritative_candidate_files\n";
