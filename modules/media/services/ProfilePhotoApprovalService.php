<?php
declare(strict_types=1);
namespace Media\Services;
require_once __DIR__.'/ProfilePhotoApprovalAudit.php';
require_once __DIR__.'/../repositories/MediaAssetsRepository.php';
require_once __DIR__.'/../contracts/PrivateMediaStoragePort.php';
require_once __DIR__.'/../contracts/PublicMediaStoragePort.php';
use Media\Contracts\{PrivateMediaStoragePort,PublicMediaStoragePort};
use Media\Repositories\MediaAssetsRepository;
use Platform\Contracts\{TrustedAuthorizationContext,AuthorizationRequirement,AuthorizationPlane,RiskLevel,CapabilitySet};
use Platform\Services\{AuthorizationBoundary,RandomAuditUuidProvider};
use PDO;
use RuntimeException;

final class ProfilePhotoApprovalService
{
    public const CAPABILITY = 'media_review_approve';
    public function __construct(private PDO $pdo, private PrivateMediaStoragePort $private, private PublicMediaStoragePort $public) {}
    public static function requirement(): AuthorizationRequirement
    {
        return new AuthorizationRequirement(authorizationPlane:AuthorizationPlane::INTERNAL_OPERATOR,riskLevel:RiskLevel::R1,
            action:'approve',resourceType:'media_review_submission',actorAuthenticatedRequired:true,
            capabilitiesRequired:new CapabilitySet([MediaReviewAuthority::CAPABILITY,self::CAPABILITY]),auditTrailRequired:true);
    }
    public function approve(?TrustedAuthorizationContext $context, string $id): array
    {
        if ($context === null || $context->trustSource() !== 'canonical_internal_operator') throw new RuntimeException('approval_denied');
        $key = null;$temporary = null;$commitAttempted = false;$old = [];$candidate = null;$review = null;$bytes = null;
        $mediaId = (new RandomAuditUuidProvider())->generateCanonicalUuid();
        if ($this->pdo->inTransaction()) throw new RuntimeException('approval_outer_transaction_not_allowed');
        try {
            if (!$this->pdo->beginTransaction()) throw new RuntimeException('approval_begin_failed');
            $audit = new ProfilePhotoApprovalAudit($this->pdo,$context,function() use ($id,$mediaId,&$candidate,&$review,&$bytes,&$old): array {
                [$candidate,$review,$bytes] = $this->lockAndVerify($id);
                $s=$this->pdo->prepare("SELECT media_id,storage_key FROM media_assets WHERE owner_type='PHYSICIAN' AND owner_id=? AND purpose='DOCTOR_PROFILE_PHOTO' AND status='READY' FOR UPDATE");
                $s->execute([$candidate['owner_id']]);$old=$s->fetchAll(PDO::FETCH_ASSOC);
                return ['submission_id'=>$id,'physician_id'=>(string)$candidate['owner_id'],'published_media_id'=>$mediaId];
            });
            if (!(new AuthorizationBoundary())->authorize($context,self::requirement(),$audit)->allowed()) throw new RuntimeException('approval_denied');
            // Authorization and actual canonical audit append have succeeded; both remain uncommitted.
            $proposedKey='public/doctor-profile-photo/'.hash('sha256','PHYSICIAN:'.$candidate['owner_id']).'/'.$mediaId.'.webp';
            if ($this->public->exists($proposedKey)) throw new RuntimeException('approval_immutable_key_conflict');
            $key=$proposedKey;
            $temporary=tempnam(sys_get_temp_dir(),'mxmed-approval-');
            if ($temporary===false || !chmod($temporary,0600) || file_put_contents($temporary,$bytes)!==strlen($bytes)) throw new RuntimeException('approval_prepare_failed');
            $this->public->storeImmutable($key,$temporary);
            // Verify storage persisted the exact reviewed bytes before making the URL authoritative.
            $this->verifiedBytes($this->public,$key,$review);
            $url='/api/media/index.php/public/'.$mediaId;
            $repository=new MediaAssetsRepository($this->pdo);
            $repository->insertReady(array_merge($review,['media_id'=>$mediaId,'owner_type'=>'PHYSICIAN','owner_id'=>$candidate['owner_id'],
                'purpose'=>'DOCTOR_PROFILE_PHOTO','storage_key'=>$key,'public_url'=>$url,'alt_text'=>'Fotografía de perfil']));
            $s=$this->pdo->prepare('UPDATE profiles_doctors SET photo_url=?,updated_at=CURRENT_TIMESTAMP WHERE doctor_id=?');
            $s->execute([$url,$candidate['owner_id']]);if ($s->rowCount()!==1) throw new RuntimeException('approval_profile_switch_failed');
            foreach ($old as $asset) $repository->markDeleted($asset['media_id']);
            $s=$this->pdo->prepare("UPDATE media_review_submissions SET review_status='APPROVED',updated_at=CURRENT_TIMESTAMP WHERE submission_id=? AND technical_status='READY' AND review_status='PENDING_REVIEW'");
            $s->execute([$id]);if ($s->rowCount()!==1) throw new RuntimeException('approval_conflict');
            $commitAttempted=true;
            if (!$this->pdo->commit()) throw new RuntimeException('approval_commit_failed');
        } catch (\Throwable $e) {
            $safeToDelete=!$commitAttempted;
            if ($this->pdo->inTransaction()) {
                try { $safeToDelete=$this->pdo->rollBack(); } catch (\Throwable) { $safeToDelete=false;error_log('media_approval_rollback_unconfirmed'); }
            }
            // Never remove an object when a lost COMMIT acknowledgement makes authority uncertain.
            if ($safeToDelete && $key!==null) $this->cleanup($key);
            elseif ($key!==null) error_log('media_approval_commit_outcome_requires_reconciliation');
            throw $e;
        } finally {
            if (is_string($temporary) && is_file($temporary)) unlink($temporary);
        }
        foreach ($old as $asset) $this->cleanup($asset['storage_key']);
        return ['ok'=>true,'submission_id'=>$id,'review_status'=>'APPROVED','published_media_id'=>$mediaId,'public_url'=>$url];
    }
    private function lockAndVerify(string $id): array
    {
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D',$id)) throw new RuntimeException('approval_invalid_request');
        $s=$this->pdo->prepare('SELECT owner_id FROM media_review_submissions WHERE submission_id=?');$s->execute([$id]);$owner=$s->fetchColumn();
        if ($owner===false) throw new RuntimeException('approval_not_found');
        // Same lock order as MR1 upload/replacement: physician first, then submission.
        $s=$this->pdo->prepare('SELECT doctor_id FROM profiles_doctors WHERE doctor_id=? FOR UPDATE');$s->execute([$owner]);
        if ($s->fetchColumn()===false) throw new RuntimeException('approval_conflict');
        $s=$this->pdo->prepare('SELECT * FROM media_review_submissions WHERE submission_id=? FOR UPDATE');$s->execute([$id]);$candidate=$s->fetch(PDO::FETCH_ASSOC);
        if (!$candidate || (string)$candidate['owner_id']!==(string)$owner || $candidate['owner_type']!=='PHYSICIAN'
            || $candidate['purpose']!=='DOCTOR_PROFILE_PHOTO' || $candidate['technical_status']!=='READY'
            || $candidate['review_status']!=='PENDING_REVIEW') throw new RuntimeException('approval_conflict');
        $s=$this->pdo->prepare('SELECT * FROM media_review_files WHERE submission_id=? ORDER BY role FOR UPDATE');$s->execute([$id]);$files=$s->fetchAll(PDO::FETCH_ASSOC);
        if (!in_array(count($files),[2,3],true)) throw new RuntimeException('approval_integrity_failed');
        $roles=[];$prefix='private/media-review/'.hash('sha256','PHYSICIAN:'.$owner).'/'.$id.'/';
        foreach ($files as $file) {
            $role=$file['role'];$format=$file['format'];
            if (!in_array($role,['SOURCE','REVIEW','CORRECTED'],true) || isset($roles[$role])
                || !isset(['jpeg'=>1,'png'=>1,'webp'=>1][$format]) || $file['mime_type']!=='image/'.$format
                || !preg_match('/^[0-9a-f-]{36}$/D',$file['file_id'])
                || $file['storage_key']!==$prefix.strtolower($role).'/'.$file['file_id'].'.'.$format
                || !preg_match('/^[0-9a-f]{64}$/D',$file['checksum_sha256'])
                || (int)$file['width']<1 || (int)$file['height']<1 || (int)$file['byte_size']<1) throw new RuntimeException('approval_integrity_failed');
            $roles[$role]=$file;
        }
        if (!isset($roles['SOURCE'],$roles['REVIEW'])) throw new RuntimeException('approval_integrity_failed');
        if (isset($roles['CORRECTED'])) {
            $corrected=$roles['CORRECTED'];
            if ((int)$corrected['byte_size']>10485760 || max((int)$corrected['width'],(int)$corrected['height'])>8192
                || (int)$corrected['width']*(int)$corrected['height']>25000000 || !$this->private->exists($corrected['storage_key'])) throw new RuntimeException('approval_integrity_failed');
        }
        $source=$roles['SOURCE'];$review=$roles['REVIEW'];
        if ((int)$source['byte_size']>10485760 || max((int)$source['width'],(int)$source['height'])>8192
            || (int)$source['width']*(int)$source['height']>25000000 || !$this->private->exists($source['storage_key'])
            || $review['mime_type']!=='image/webp' || $review['format']!=='webp'
            || (int)$review['byte_size']>153600 || max((int)$review['width'],(int)$review['height'])>800) throw new RuntimeException('approval_integrity_failed');
        $bytes=$this->verifiedBytes($this->private,$review['storage_key'],$review);
        $size=getimagesizefromstring($bytes);
        if (!$size || ($size['mime']??'')!=='image/webp' || $size[0]!=(int)$review['width'] || $size[1]!=(int)$review['height']) throw new RuntimeException('approval_integrity_failed');
        return [$candidate,$review,$bytes];
    }
    private function verifiedBytes(PrivateMediaStoragePort|PublicMediaStoragePort $storage,string $key,array $review): string
    {
        $object=$storage->openReadStream($key);$stream=$object['stream'];
        try {
            $bytes=stream_get_contents($stream,153601);
            if ($bytes===false || $object['bytes']!==(int)$review['byte_size'] || strlen($bytes)!==(int)$review['byte_size']
                || !hash_equals($review['checksum_sha256'],hash('sha256',$bytes))) throw new RuntimeException('approval_integrity_failed');
            return $bytes;
        } finally { if (is_resource($stream)) fclose($stream); }
    }
    private function cleanup(string $key): void
    {
        try { $this->public->delete($key); }
        catch (\Throwable) { error_log('media_approval_public_cleanup_failed'); }
    }
}
