<?php
declare(strict_types=1);
require __DIR__.'/LogoImprovementFixture.php';
use Media\Services\LogoImprovementService as Service;
final class Mr8FailStorage implements Media\Contracts\PrivateMediaStoragePort {
 public function __construct(private Media\Contracts\PrivateMediaStoragePort $inner,private string $mode){}
 public function exists(string $k):bool{return $this->inner->exists($k);}
 public function delete(string $k):void{$this->inner->delete($k);}
 public function storeImmutable(string $k,string $p):void{if($this->mode==='store')throw new RuntimeException('synthetic_store_failure');$this->inner->storeImmutable($k,$p);}
 public function openReadStream(string $k):array{$o=$this->inner->openReadStream($k);if($this->mode==='integrity')$o['bytes']++;return $o;}
}
class Mr8FailCommit extends PDO{public function commit():bool{return false;}}
class Mr8LostAck extends PDO{public function commit():bool{parent::commit();throw new RuntimeException('lost_ack');}}
$p=mr5Pdo();[$private]=mr5Storage();$service=new Service($p,$private);
$state=fn()=>json_encode([$p->query('SELECT * FROM media_review_files ORDER BY file_id')->fetchAll(),$p->query('SELECT * FROM media_review_submissions ORDER BY submission_id')->fetchAll(),$p->query('SELECT * FROM platform_audit_events ORDER BY event_id')->fetchAll(),$p->query('SELECT * FROM platform_audit_stream_heads ORDER BY stream_key')->fetchAll()]);
foreach(['generate','accept','discard'] as $action)foreach(['integrity','store','insert','commit'] as $mode){
 if($action==='discard'&&in_array($mode,['integrity','store','insert'],true))continue;
 $f=mr8Candidate();if($action!=='generate')$service->mutate(mr8Context('generate'),'generate',$f['id']);$before=$state();
 $db=$mode==='commit'?new Mr8FailCommit('mysql:host=127.0.0.1;port=3309;dbname=mxmed','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]):$p;
 if($mode==='insert')$p->exec("CREATE TRIGGER mr8_file_failure BEFORE INSERT ON media_review_files FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic_failure'");
 try{(new Service($db,new Mr8FailStorage($private,$mode)))->mutate(mr8Context($action),$action,$f['id']);throw new LogicException('unexpected success');}catch(RuntimeException|PDOException $e){if($state()!==$before)throw new LogicException('atomic_failure_'.$action.'_'.$mode);echo "PASS atomic_{$action}_{$mode}\n";}finally{if($mode==='insert')$p->exec('DROP TRIGGER mr8_file_failure');}
}
$f=mr8Candidate();$service->mutate(mr8Context('generate'),'generate',$f['id']);$db=new Mr8LostAck('mysql:host=127.0.0.1;port=3309;dbname=mxmed','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
try{(new Service($db,$private))->mutate(mr8Context('accept'),'accept',$f['id']);throw new LogicException('unexpected ack');}catch(RuntimeException $e){if($e->getMessage()!=='lost_ack')throw $e;}
foreach(mr6Rows($p,$f['id']) as $row)if(!$private->exists($row['storage_key']))throw new LogicException('authoritative_file_deleted');echo "PASS ambiguous_commit_preserves_files\n";
