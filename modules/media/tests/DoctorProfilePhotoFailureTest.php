<?php
declare(strict_types=1);
require __DIR__.'/../../../api/_lib/db.php';
require __DIR__.'/../bootstrap.php';
require __DIR__.'/../services/DoctorProfilePhotoService.php';
use Media\Contracts\PublicMediaStoragePort;
use Media\Services\DoctorProfilePhotoService;
$p=mxmed_pdo();$storage=mxmed_public_media_storage();$service=new DoctorProfilePhotoService($p,$storage);
if($p->query("SELECT photo_url FROM profiles_doctors WHERE doctor_id='1'")->fetchColumn()!==null)throw new RuntimeException('requires empty QA photo');
$upload=static function(): array {$path=tempnam(sys_get_temp_dir(),'photo-failure-');copy(__DIR__.'/../../../assets/img/doctors/avatars/dr-female.png',$path);return ['tmp_name'=>$path,'name'=>'photo.png','type'=>'image/png','error'=>0];};
$failing=new class($storage) implements PublicMediaStoragePort {
 public ?string $key=null;
 public function __construct(private PublicMediaStoragePort $delegate){}
 public function storeImmutable(string $key,string $path):void{$this->key=$key;$this->delegate->storeImmutable($key,$path);throw new RuntimeException('injected_storage_failure_after_write');}
 public function exists(string $key):bool{return $this->delegate->exists($key);}
 public function delete(string $key):void{$this->delegate->delete($key);}
 public function openReadStream(string $key):array{return $this->delegate->openReadStream($key);}
};
try{
 $service->upload('1',$upload());$before=$service->current('1');
 try{(new DoctorProfilePhotoService($p,$failing))->upload('1',$upload());throw new RuntimeException('expected failure');}
 catch(RuntimeException $e){if($e->getMessage()!=='injected_storage_failure_after_write')throw $e;}
 if($service->current('1')!==$before || $failing->key===null || $storage->exists($failing->key))throw new RuntimeException('rollback did not preserve original or remove new object');
 $n=$p->query("SELECT COUNT(*) FROM media_assets WHERE owner_id='1' AND purpose='DOCTOR_PROFILE_PHOTO' AND status='READY'")->fetchColumn();if((int)$n!==1)throw new RuntimeException('ready count changed');
 echo "PASS: failure after object storage rolls back and cleans new file while preserving the old photo\n";
}finally{$service->delete('1');}
