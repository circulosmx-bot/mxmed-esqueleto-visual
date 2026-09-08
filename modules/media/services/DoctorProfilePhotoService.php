<?php
declare(strict_types=1);
namespace Media\Services;

use PDO;
use RuntimeException;
use Media\Contracts\PublicMediaStoragePort;
use Media\Repositories\MediaAssetsRepository;

require_once __DIR__.'/../repositories/MediaAssetsRepository.php';
require_once __DIR__.'/GdPublicLogoProcessor.php';

final class DoctorProfilePhotoService
{
    public function __construct(private PDO $pdo, private PublicMediaStoragePort $storage) {}

    public function current(string $doctor): ?array
    {
        $s=$this->pdo->prepare("SELECT m.media_id,m.public_url,m.width,m.height,m.mime_type,m.byte_size FROM profiles_doctors p JOIN media_assets m ON m.public_url=p.photo_url AND m.owner_id=p.doctor_id WHERE p.doctor_id=? AND m.owner_type='PHYSICIAN' AND m.purpose='DOCTOR_PROFILE_PHOTO' AND m.classification='PUBLIC' AND m.status='READY'");
        $s->execute([$doctor]);return $s->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function upload(string $doctor, array $upload): void
    {
        $path=(string)($upload['tmp_name'] ?? '');
        if (($upload['error'] ?? UPLOAD_ERR_OK)!==UPLOAD_ERR_OK || !is_file($path)) throw new RuntimeException('photo_upload_invalid');
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($path);
        $ext=strtolower(pathinfo((string)($upload['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($ext, ['image/jpeg'=>['jpg','jpeg'],'image/png'=>['png'],'image/webp'=>['webp']][$mime] ?? [], true)) throw new RuntimeException('photo_invalid_extension');
        if ($mime==='image/jpeg' && !function_exists('exif_read_data')) throw new RuntimeException('safe_photo_orientation_unavailable');
        // Re-encode decoded pixels only; existing processor handles JPEG EXIF orientation.
        $image=(new GdPublicLogoProcessor())->process($upload);
        try { $this->replace($doctor,$image); }
        finally { if(is_file($image['path'])) unlink($image['path']); }
    }

    public function delete(string $doctor): void { $this->replace($doctor,null); }

    private function replace(string $doctor, ?array $image): void
    {
        $key=null;$committed=false;$old=[];
        try {
            $this->pdo->beginTransaction();
            // The physician row serializes upload/replacement/removal for this owner.
            $s=$this->pdo->prepare('SELECT doctor_id FROM profiles_doctors WHERE doctor_id=? FOR UPDATE');
            $s->execute([$doctor]);if(!$s->fetchColumn()) throw new RuntimeException('photo_profile_not_found');
            $s=$this->pdo->prepare("SELECT media_id,storage_key FROM media_assets WHERE owner_type='PHYSICIAN' AND owner_id=? AND purpose='DOCTOR_PROFILE_PHOTO' AND status='READY' FOR UPDATE");
            $s->execute([$doctor]);$old=$s->fetchAll(PDO::FETCH_ASSOC);
            $url=null;
            if($image!==null){
                $bytes=random_bytes(16);$bytes[6]=chr((ord($bytes[6])&15)|64);$bytes[8]=chr((ord($bytes[8])&63)|128);
                $h=bin2hex($bytes);$id=substr($h,0,8).'-'.substr($h,8,4).'-'.substr($h,12,4).'-'.substr($h,16,4).'-'.substr($h,20);
                $key='public/doctor-profile-photo/'.hash('sha256','PHYSICIAN:'.$doctor).'/'.$id.'.webp';
                $this->storage->storeImmutable($key,$image['path']);
                $url='/api/media/index.php/public/'.$id;
                (new MediaAssetsRepository($this->pdo))->insertReady(array_merge($image,['media_id'=>$id,'owner_type'=>'PHYSICIAN','owner_id'=>$doctor,'purpose'=>'DOCTOR_PROFILE_PHOTO','storage_key'=>$key,'public_url'=>$url,'alt_text'=>'Fotografía de perfil']));
            }
            $s=$this->pdo->prepare('UPDATE profiles_doctors SET photo_url=?,updated_at=CURRENT_TIMESTAMP WHERE doctor_id=?');$s->execute([$url,$doctor]);
            foreach($old as $asset)(new MediaAssetsRepository($this->pdo))->markDeleted($asset['media_id']);
            $this->pdo->commit();$committed=true;
        } catch(\Throwable $e){
            if($this->pdo->inTransaction())$this->pdo->rollBack();
            if(!$committed && $key!==null && $this->storage->exists($key))$this->storage->delete($key);
            throw $e;
        }
        // Never undo the newly committed reference if physical retirement fails.
        foreach($old as $asset)$this->storage->delete($asset['storage_key']);
    }
}
