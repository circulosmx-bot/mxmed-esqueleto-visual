<?php
declare(strict_types=1);
namespace Media\Services;

use PDO;
use RuntimeException;
use Media\Contracts\PublicMediaStoragePort;
use Media\Repositories\MediaAssetsRepository;

require_once __DIR__ . '/../repositories/MediaAssetsRepository.php';
require_once __DIR__ . '/GdPublicLogoProcessor.php';

final class DoctorGalleryService
{
    public function __construct(private PDO $pdo, private PublicMediaStoragePort $storage) {}

    public function list(string $doctorId): array
    {
        return (new MediaAssetsRepository($this->pdo))->listDoctorGallery($doctorId);
    }

    public function upload(string $doctorId, array $upload): array
    {
        $extension = strtolower(pathinfo((string)($upload['name'] ?? ''), PATHINFO_EXTENSION));
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file((string)($upload['tmp_name'] ?? ''));
        if (!in_array($extension, ['image/jpeg'=>['jpg','jpeg'], 'image/png'=>['png'], 'image/webp'=>['webp']][$mime] ?? [], true)) {
            throw new RuntimeException('gallery_invalid_extension');
        }
        $image = (new GdPublicLogoProcessor())->process($upload);
        $key = null;
        try {
            $this->pdo->beginTransaction();
            // Serialize uploads per doctor so the 21-image limit cannot race.
            $lock = $this->pdo->prepare('SELECT doctor_id FROM profiles_doctors WHERE doctor_id=? FOR UPDATE');
            $lock->execute([$doctorId]);
            if (!$lock->fetchColumn()) throw new RuntimeException('gallery_profile_not_found');
            $items = $this->list($doctorId);
            if (count($items) >= 21) throw new RuntimeException('gallery_limit_reached');
            $bytes = random_bytes(16);
            $bytes[6] = chr((ord($bytes[6]) & 15) | 64);
            $bytes[8] = chr((ord($bytes[8]) & 63) | 128);
            $hex = bin2hex($bytes);
            $id = substr($hex,0,8).'-'.substr($hex,8,4).'-'.substr($hex,12,4).'-'.substr($hex,16,4).'-'.substr($hex,20);
            $key = 'public/doctor-gallery/'.hash('sha256', 'PHYSICIAN:'.$doctorId).'/'.$id.'.webp';
            $this->storage->storeImmutable($key, $image['path']);
            $asset = array_merge($image, ['media_id'=>$id, 'owner_type'=>'PHYSICIAN', 'owner_id'=>$doctorId,
                'purpose'=>'DOCTOR_GALLERY', 'storage_key'=>$key, 'public_url'=>'/api/media/index.php/public/'.$id, 'alt_text'=>'']);
            (new MediaAssetsRepository($this->pdo))->insertReady($asset);
            $this->pdo->commit();
            return $this->list($doctorId);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            if ($key !== null && $this->storage->exists($key)) $this->storage->delete($key);
            throw $e;
        } finally {
            if (is_file($image['path'])) unlink($image['path']);
        }
    }

    public function delete(string $doctorId, string $id): void
    {
        $stmt = $this->pdo->prepare("SELECT storage_key FROM media_assets WHERE media_id=? AND owner_type='PHYSICIAN' AND owner_id=? AND purpose='DOCTOR_GALLERY' AND status='READY'");
        $stmt->execute([$id, $doctorId]);
        $key = $stmt->fetchColumn();
        if (!$key) throw new RuntimeException('gallery_asset_not_found');
        (new MediaAssetsRepository($this->pdo))->markDeleted($id);
        $this->storage->delete($key);
    }
}
