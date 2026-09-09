<?php
declare(strict_types=1);
require __DIR__.'/../../../api/_lib/db.php';
require __DIR__.'/../private-bootstrap.php';
require __DIR__.'/ReviewCandidateFixture.php';
use Media\Contracts\PrivateMediaStoragePort;
use Media\Services\ProfilePhotoReviewCandidateService as Service;
function check(bool $value,string $message):void {if(!$value)throw new RuntimeException($message);}
$p=mxmed_pdo();$storage=mxmed_private_media_storage();$service=new Service($p,$storage);
check($service->current('1')===null,'Refusing to replace an existing candidate');
$publicSnapshot=static function()use($p):array{
 $assets=$p->query('SELECT * FROM media_assets ORDER BY media_id')->fetchAll();
 $photos=$p->query('SELECT doctor_id,photo_url,logo_url,updated_at FROM profiles_doctors ORDER BY doctor_id')->fetchAll();
 $hashes=[];$pub=mxmed_public_media_storage();
 foreach($assets as $a)if($a['status']==='READY'){$f=$pub->openReadStream($a['storage_key']);$h=hash_init('sha256');hash_update_stream($h,$f['stream']);fclose($f['stream']);$hashes[$a['media_id']]=hash_final($h);}
 return [$assets,$photos,$hashes];
};
$before=$publicSnapshot();$ids=[];$temps=[];
$upload=static function(array $f,?Service $s=null)use(&$temps,$service){$temps[]=$f['tmp_name'];($s??$service)->upload('1',$f);};
$keys=static function(string $id)use($p){$s=$p->prepare('SELECT * FROM media_review_files WHERE submission_id=? ORDER BY role');$s->execute([$id]);return $s->fetchAll();};
$remember=static function()use($service,&$ids){$a=$service->current('1');$ids[]=$a['submission_id'];return $a;};
$expectFailure=static function(callable $call,string $contains){try{$call();}catch(Throwable $e){check(str_contains($e->getMessage(),$contains),'Unexpected failure: '.$e->getMessage());return;}throw new RuntimeException('Expected '.$contains);};
try{
 foreach(['jpeg','png','webp'] as $format){
  $f=candidateFixture($format);$original=hash_file('sha256',$f['tmp_name']);$upload($f);$a=$remember();
  check($a['technical_status']==='READY'&&$a['review_status']==='PENDING_REVIEW','states');
  check(!str_contains(json_encode($a),'storage_key')&&!str_contains(json_encode($a),'public_url'),'metadata privacy');
  foreach($keys($a['submission_id']) as $file){
   $opened=$storage->openReadStream($file['storage_key']);$bytes=stream_get_contents($opened['stream']);fclose($opened['stream']);
   check(hash('sha256',$bytes)===$file['checksum_sha256'],'integrity');
   if($file['role']==='SOURCE')check(hash('sha256',$bytes)===$original,'exact original retained');
   else{
    check($file['width']<=800&&$file['height']<=800&&strlen($bytes)<=153600,'review limits');
    check(!str_contains($bytes,'Exif')&&!str_contains($bytes,'EXIF')&&!str_contains($bytes,'XMP '),'metadata stripped');
    if($format==='jpeg')check((int)$file['width']===120&&(int)$file['height']===240,'orientation');
    else{$image=imagecreatefromstring($bytes);check(((imagecolorat($image,0,0)>>24)&127)>0,'alpha');}
   }
  }
  check($publicSnapshot()===$before,'public changed');
 }
 $previous=$service->current('1');
 foreach([[5001,5000,'pixel_count'],[8193,1,'dimensions']] as [$w,$h,$error]){$f=candidateFixture('png',$w,$h);$expectFailure(fn()=>$upload($f),$error);}
 $f=candidateFixture();$handle=fopen($f['tmp_name'],'ab');ftruncate($handle,10485761);fclose($handle);$expectFailure(fn()=>$upload($f),'bytes_exceeded');
 $f=candidateFixture();$f['type']='image/jpeg';$expectFailure(fn()=>$upload($f),'media_type_mismatch');
 $f=candidateFixture();$f['name']='bad.svg';$expectFailure(fn()=>$upload($f),'invalid_extension');
 $f=candidateFixture();file_put_contents($f['tmp_name'],substr(file_get_contents($f['tmp_name']),0,70));$expectFailure(fn()=>$upload($f),'logo_upload_');
 $f=candidateFixture();$f['error']=UPLOAD_ERR_PARTIAL;$expectFailure(fn()=>$upload($f),'upload_invalid');
 check($service->current('1')===$previous,'invalid input replaced candidate');
 // SOURCE / REVIEW store failures, including a store that writes before throwing.
 foreach([1,2] as $failAt){
  $fault=new class($storage,$failAt) implements PrivateMediaStoragePort {
   public array $written=[];public function __construct(private PrivateMediaStoragePort $s,private int $failAt){}
   public function storeImmutable(string $k,string $p):void{$this->written[]=$k;$this->s->storeImmutable($k,$p);if(count($this->written)===$this->failAt)throw new RuntimeException('injected_store_failure');}
   public function delete(string $k):void{$this->s->delete($k);}public function exists(string $k):bool{return $this->s->exists($k);}public function openReadStream(string $k):array{return $this->s->openReadStream($k);}
  };
  $expectFailure(fn()=>$upload(candidateFixture(),new Service($p,$fault)),'injected_store_failure');
  foreach($fault->written as $key)check(!$storage->exists($key),'new object leaked');
  check($service->current('1')===$previous,'store failure changed old candidate');
 }
 $faultPdo=new class($p) extends PDO {
  public function __construct(private PDO $inner){}
  public function beginTransaction():bool{return $this->inner->beginTransaction();}
  public function prepare(string $query,array $options=[]):PDOStatement|false{return $this->inner->prepare($query,$options);}
  public function commit():bool{throw new RuntimeException('injected_commit_failure');}
  public function inTransaction():bool{return $this->inner->inTransaction();}
  public function rollBack():bool{return $this->inner->rollBack();}
 };
 $recording=new class($storage) implements PrivateMediaStoragePort {
  public array $written=[];public function __construct(private PrivateMediaStoragePort $s){}
  public function storeImmutable(string $k,string $p):void{$this->written[]=$k;$this->s->storeImmutable($k,$p);}
  public function delete(string $k):void{$this->s->delete($k);}public function exists(string $k):bool{return $this->s->exists($k);}public function openReadStream(string $k):array{return $this->s->openReadStream($k);}
 };
 $expectFailure(fn()=>$upload(candidateFixture(),new Service($faultPdo,$recording)),'injected_commit_failure');
 foreach($recording->written as $key)check(!$storage->exists($key),'commit failure leaked object');
 check($service->current('1')===$previous,'commit failure changed old candidate');
 $f=candidateFixture('jpeg',4000,3000);$upload($f);$a=$remember();check($a['submission_id']!==$previous['submission_id'],'replacement');
 foreach($keys($previous['submission_id']) as $file)check(!$storage->exists($file['storage_key']),'old file not retired');
 $expectFailure(fn()=>$storage->exists('../unsafe'),'invalid_private_media_storage_key');
 $expectFailure(fn()=>new Media\Storage\LocalPersistentPrivateMediaStorage(dirname(__DIR__,3).'/storage/private'),'overlaps_public');
 $file=$keys($a['submission_id'])[0];$expectFailure(fn()=>$storage->storeImmutable($file['storage_key'],$f['tmp_name']),'immutable_store_failed');
 check($service->current('2')===null,'other owner isolation');
 $service->withdraw('2');check($service->current('1')['submission_id']===$a['submission_id'],'other owner withdrawal');
 // Post-commit cleanup failure must preserve the new authoritative candidate.
 $oldFiles=$keys($a['submission_id']);
 $noDelete=new class($storage) implements PrivateMediaStoragePort {
  public function __construct(private PrivateMediaStoragePort $s){}
  public function storeImmutable(string $k,string $p):void{$this->s->storeImmutable($k,$p);}
  public function delete(string $k):void{throw new RuntimeException('injected_retirement_failure');}
  public function exists(string $k):bool{return $this->s->exists($k);}
  public function openReadStream(string $k):array{return $this->s->openReadStream($k);}
 };
 $upload(candidateFixture(),new Service($p,$noDelete));$next=$remember();
 check($next['submission_id']!==$a['submission_id'],'cleanup failure undid commit');
 foreach($oldFiles as $file){check($storage->exists($file['storage_key']),'failure fixture');$storage->delete($file['storage_key']);}
 // A second pending photo is rejected even outside the service lock.
 $p->beginTransaction();
 try {
  $expectFailure(function()use($p){$p->exec("INSERT INTO media_review_submissions(submission_id,owner_type,owner_id,purpose,technical_status,review_status) VALUES(UUID(),'PHYSICIAN','1','DOCTOR_PROFILE_PHOTO','READY','PENDING_REVIEW')");},'Duplicate');
 }finally{$p->rollBack();}
 $service->withdraw('1');check($service->current('1')===null,'withdrawal');
 check($publicSnapshot()===$before,'public regression');
 echo "PASS: JPEG/PNG/WebP original retention; private metadata; orientation/alpha/stripping; 12MP; input limits; storage/commit failures; replacement/withdrawal; immutable safe storage; exact public rows/references/files unchanged\n";
}finally{
 $current=$service->current('1');if($current && in_array($current['submission_id'],$ids,true))$service->withdraw('1');
 foreach($ids as $id){$s=$p->prepare('DELETE FROM media_review_submissions WHERE submission_id=?');$s->execute([$id]);}
 foreach($temps as $path)if(is_file($path))unlink($path);
}
