<?php
declare(strict_types=1);
namespace Media\Services;
require_once __DIR__.'/MediaReviewAuthority.php';
require_once __DIR__.'/../contracts/PrivateMediaStoragePort.php';
use Media\Contracts\PrivateMediaStoragePort;
use Platform\Contracts\{AuthorizationContext,TrustedAuthorizationContext};
use PDO;
use RuntimeException;

/** Read-only access; storage authority never leaves this service. */
final class MediaReviewAccessService
{
    public function __construct(private PDO $pdo, private PrivateMediaStoragePort $storage) {}

    public function metadata(AuthorizationContext|TrustedAuthorizationContext|null $context, string $id): array
    {
        MediaReviewAuthority::requireRead($context);
        $row = $this->resolve($id);
        return [
            'submission_id'=>$row['submission_id'],'owner_type'=>$row['owner_type'],'owner_id'=>$row['owner_id'],
            'purpose'=>$row['purpose'],'technical_status'=>$row['technical_status'],'review_status'=>$row['review_status'],
            'created_at'=>$row['created_at'],'updated_at'=>$row['updated_at'],
            'review'=>['role'=>'REVIEW','mime_type'=>$row['mime_type'],'format'=>$row['format'],
                'width'=>(int)$row['width'],'height'=>(int)$row['height'],'byte_size'=>(int)$row['byte_size']]
        ];
    }

    /** Bounded buffering verifies every byte before any HTTP image output. */
    public function reviewBytes(AuthorizationContext|TrustedAuthorizationContext|null $context, string $id): string
    {
        MediaReviewAuthority::requireRead($context);
        $row = $this->resolve($id);
        $stream = null;
        try {
            $object = $this->storage->openReadStream($row['storage_key']);
            $stream = $object['stream'];
            $bytes = stream_get_contents($stream, 153601);
            if ($bytes === false || $object['bytes'] !== (int)$row['byte_size']
                || strlen($bytes) !== (int)$row['byte_size']
                || !hash_equals($row['checksum_sha256'], hash('sha256',$bytes))) {
                throw new RuntimeException('review_integrity_failed');
            }
            return $bytes;
        } catch (\Throwable $e) {
            error_log('media_review_binary_failed submission='.$id.' type='.get_class($e));
            throw new RuntimeException('review_unavailable');
        } finally {
            if (is_resource($stream)) fclose($stream);
        }
    }

    private function resolve(string $id): array
    {
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D',$id)) throw new RuntimeException('review_not_found');
        $s = $this->pdo->prepare("SELECT s.submission_id,s.owner_type,s.owner_id,s.purpose,s.technical_status,s.review_status,s.created_at,s.updated_at,f.storage_key,f.mime_type,f.format,f.width,f.height,f.byte_size,f.checksum_sha256 FROM media_review_submissions s JOIN media_review_files f ON f.submission_id=s.submission_id AND f.role='REVIEW' WHERE s.submission_id=? AND s.owner_type='PHYSICIAN' AND s.purpose IN ('DOCTOR_PROFILE_PHOTO','PHYSICIAN_PERSONAL_LOGO') AND s.technical_status='READY' AND s.review_status='PENDING_REVIEW'");
        $s->execute([$id]);
        $row = $s->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new RuntimeException('review_not_found');
        if ($row['mime_type'] !== 'image/webp' || $row['format'] !== 'webp'
            || (int)$row['byte_size'] < 1 || (int)$row['byte_size'] > 153600
            || (int)$row['width'] < 1 || (int)$row['height'] < 1 || max((int)$row['width'],(int)$row['height']) > 800
            || !str_contains($row['storage_key'],'/'.$id.'/review/')) throw new RuntimeException('review_unavailable');
        return $row;
    }
}
