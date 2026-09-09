<?php
declare(strict_types=1);
namespace Media\Services;

use Media\Contracts\PrivateMediaStoragePort;
use PDO;
use RuntimeException;
require_once __DIR__.'/../contracts/PrivateMediaStoragePort.php';
require_once __DIR__.'/GdPublicLogoProcessor.php';
require_once __DIR__.'/LosslessLogoInput.php';
require_once __DIR__.'/MediaReplacementReasons.php';

final class PhysicianMediaReviewCandidateService
{
    public const MAX_BYTES = 10485760;
    public const MAX_PIXELS = 25000000;
    public const MAX_SIDE = 8192;

    public function __construct(private PDO $pdo, private PrivateMediaStoragePort $storage, private string $purpose) {
        if (!in_array($purpose, ['DOCTOR_PROFILE_PHOTO','PHYSICIAN_PERSONAL_LOGO'], true)) throw new RuntimeException('candidate_unsupported_purpose');
    }

    /** Owner metadata only: no physical keys, binary data or delivery URLs. */
    public function current(string $doctor): ?array
    {
        // One selection gives PENDING priority without a race between two status queries.
        $s=$this->pdo->prepare("SELECT submission_id,technical_status,review_status,created_at,updated_at,review_reason_code,review_feedback,review_decided_at FROM media_review_submissions WHERE owner_type='PHYSICIAN' AND owner_id=? AND purpose=? ORDER BY (review_status='PENDING_REVIEW') DESC,created_at DESC,review_decided_at DESC,submission_id DESC LIMIT 1");
        $s->execute([$doctor,$this->purpose]);$row=$s->fetch(PDO::FETCH_ASSOC);
        if(!$row)return null;
        if($row['review_status']==='NEEDS_WORK') {
            return ['submission_id'=>$row['submission_id'],'technical_status'=>$row['technical_status'],'review_status'=>$row['review_status'],
                'reason_code'=>$row['review_reason_code'],'reason_label'=>MediaReplacementReasons::LABELS[$row['review_reason_code']]??'Se requiere otra imagen.',
                'review_feedback'=>$row['review_feedback'],'review_decided_at'=>$row['review_decided_at'],'updated_at'=>$row['updated_at']];
        }
        if($row['review_status']!=='PENDING_REVIEW')return null;
        unset($row['review_reason_code'],$row['review_feedback'],$row['review_decided_at']);
        $s = $this->pdo->prepare('SELECT role,mime_type,format,width,height,byte_size FROM media_review_files WHERE submission_id=? ORDER BY role');
        $s->execute([$row['submission_id']]);
        $row['files'] = $s->fetchAll(PDO::FETCH_ASSOC);
        return $row;
    }

    public function upload(string $doctor, array $upload): void
    {
        $path = (string)($upload['tmp_name'] ?? '');
        if (($upload['error'] ?? -1) !== UPLOAD_ERR_OK || !is_file($path)) throw new RuntimeException('candidate_upload_invalid');
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        $extensions = ['image/jpeg'=>['jpg','jpeg'], 'image/png'=>['png'], 'image/webp'=>['webp']];
        if (!in_array(strtolower(pathinfo((string)($upload['name'] ?? ''), PATHINFO_EXTENSION)), $extensions[$mime] ?? [], true)) {
            throw new RuntimeException('candidate_invalid_extension');
        }
        if ($mime === 'image/jpeg' && !function_exists('exif_read_data')) throw new RuntimeException('candidate_orientation_unavailable');
        $id = self::uuid();
        $prefix = 'private/media-review/'.hash('sha256', 'PHYSICIAN:'.$doctor).'/'.$id;
        $files = [];
        $newKeys = [];
        $review = null;
        $safeCleanup = true;
        try {
            $processor = new GdPublicLogoProcessor(self::MAX_BYTES, self::MAX_SIDE, self::MAX_PIXELS);
            $review = $processor->process($upload, false, function(string $source, string $mime, int $width, int $height, int $bytes) use (&$files, &$newKeys, $prefix): void {
                $format = ['image/jpeg'=>'jpeg','image/png'=>'png','image/webp'=>'webp'][$mime];
                $fileId = self::uuid();
                $key = $prefix.'/source/'.$fileId.'.'.$format;
                $this->storeVerified($key, $source, $newKeys);
                $files[] = ['file_id'=>$fileId,'role'=>'SOURCE','storage_key'=>$key,'mime_type'=>$mime,'format'=>$format,'width'=>$width,'height'=>$height,'byte_size'=>$bytes,'checksum_sha256'=>hash_file('sha256', $source)];
            }, null, $this->purpose==='PHYSICIAN_PERSONAL_LOGO' ? function(\GdImage $working) use (&$files, &$newKeys, $prefix): void {
                $input=LosslessLogoInput::export($working);
                try {
                    $fileId=self::uuid();$key=$prefix.'/improvement_input/'.$fileId.'.png';
                    $this->storeVerified($key,$input['path'],$newKeys);
                    $files[]=array_merge($input,['file_id'=>$fileId,'role'=>'IMPROVEMENT_INPUT','storage_key'=>$key]);
                } finally {unlink($input['path']);}
            } : null);
            $fileId = self::uuid();
            $key = $prefix.'/review/'.$fileId.'.webp';
            $this->storeVerified($key, $review['path'], $newKeys);
            $files[] = array_merge($review, ['file_id'=>$fileId,'role'=>'REVIEW','storage_key'=>$key]);
            $this->switchPending($doctor, $id, $files, $safeCleanup);
        } catch (\Throwable $e) {
            if ($safeCleanup) $this->cleanup($newKeys);
            else error_log('candidate_commit_outcome_requires_reconciliation');
            throw $e;
        } finally {
            if ($review !== null && is_file($review['path']) && !unlink($review['path'])) error_log('candidate_review_temp_cleanup_failed');
        }
        // The request upload temporary file remains owned by PHP, including on error.
    }

    public function withdraw(string $doctor): void { $this->switchPending($doctor, null, []); }

    private function switchPending(string $doctor, ?string $id, array $files, bool &$safeCleanup = true): void
    {
        $oldKeys = [];
        if ($this->pdo->inTransaction()) throw new RuntimeException('candidate_outer_transaction_not_allowed');
        try {
            if (!$this->pdo->beginTransaction()) throw new RuntimeException('candidate_begin_failed');
            // Only locks the physician row: never updates it or any public media row.
            $s = $this->pdo->prepare('SELECT doctor_id FROM profiles_doctors WHERE doctor_id=? FOR UPDATE');
            $s->execute([$doctor]);
            if ($s->fetchColumn() === false) throw new RuntimeException('candidate_profile_not_found');
            $s = $this->pdo->prepare("SELECT submission_id FROM media_review_submissions WHERE owner_type='PHYSICIAN' AND owner_id=? AND purpose=? AND review_status='PENDING_REVIEW' FOR UPDATE");
            $s->execute([$doctor, $this->purpose]);
            $oldIds = $s->fetchAll(PDO::FETCH_COLUMN);
            foreach ($oldIds as $oldId) {
                $s = $this->pdo->prepare('SELECT storage_key FROM media_review_files WHERE submission_id=?');
                $s->execute([$oldId]);
                array_push($oldKeys, ...$s->fetchAll(PDO::FETCH_COLUMN));
                $s = $this->pdo->prepare("UPDATE media_review_submissions SET review_status='WITHDRAWN',updated_at=CURRENT_TIMESTAMP WHERE submission_id=?");
                $s->execute([$oldId]);
            }
            if ($id !== null) {
                $s = $this->pdo->prepare("INSERT INTO media_review_submissions(submission_id,owner_type,owner_id,purpose,technical_status,review_status) VALUES(?,'PHYSICIAN',?,?,'READY','PENDING_REVIEW')");
                $s->execute([$id, $doctor, $this->purpose]);
                $s = $this->pdo->prepare('INSERT INTO media_review_files(file_id,submission_id,role,storage_key,mime_type,format,width,height,byte_size,checksum_sha256) VALUES(?,?,?,?,?,?,?,?,?,?)');
                foreach ($files as $file) $s->execute([$file['file_id'],$id,$file['role'],$file['storage_key'],$file['mime_type'],$file['format'],$file['width'],$file['height'],$file['byte_size'],$file['checksum_sha256']]);
            }
            $safeCleanup = false;
            if (!$this->pdo->commit()) throw new RuntimeException('candidate_commit_failed');
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                try { $safeCleanup = $this->pdo->rollBack(); }
                catch (\Throwable) { $safeCleanup = false; error_log('candidate_rollback_unconfirmed'); }
            }
            throw $e;
        }
        // Authority has switched. Cleanup failures are logged and never undo it.
        $this->cleanup($oldKeys);
    }

    /** Verify immutable storage before replacing any current candidate authority. */
    private function storeVerified(string $key, string $path, array &$keys): void
    {
        if ($this->storage->exists($key)) throw new RuntimeException('candidate_immutable_key_conflict');
        $keys[] = $key;
        $this->storage->storeImmutable($key, $path);
        $object = $this->storage->openReadStream($key);
        try {
            $bytes = stream_get_contents($object['stream'], self::MAX_BYTES + 1);
            if ($bytes === false || $object['bytes'] !== filesize($path) || strlen($bytes) !== filesize($path)
                || !hash_equals(hash_file('sha256', $path), hash('sha256', $bytes))) throw new RuntimeException('candidate_integrity_failed');
        } finally { if (is_resource($object['stream'])) fclose($object['stream']); }
    }

    private function cleanup(array $keys): void
    {
        foreach ($keys as $key) {
            try { $this->storage->delete($key); }
            catch (\Throwable $e) { error_log('candidate_private_cleanup_failed key='.$key.' error='.$e->getMessage()); }
        }
    }

    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 15) | 64);
        $bytes[8] = chr((ord($bytes[8]) & 63) | 128);
        $h = bin2hex($bytes);
        return substr($h,0,8).'-'.substr($h,8,4).'-'.substr($h,12,4).'-'.substr($h,16,4).'-'.substr($h,20);
    }
}
