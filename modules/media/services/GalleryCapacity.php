<?php
declare(strict_types=1);
namespace Media\Services;
use PDO;
/** Call capacity checks only while holding the physician row lock. Existing over-limit assets are never truncated. */
final class GalleryCapacity
{
    public const MAX_DOCTOR_GALLERY_IMAGES=16;
    public static function counts(PDO $pdo,string $doctor,bool $current=false):array
    {
        $suffix=$current?' FOR UPDATE':'';
        $s=$pdo->prepare("SELECT media_id FROM media_assets WHERE owner_type='PHYSICIAN' AND owner_id=? AND purpose='DOCTOR_GALLERY' AND classification='PUBLIC' AND status='READY'".$suffix);$s->execute([$doctor]);$public=count($s->fetchAll(PDO::FETCH_COLUMN));
        $s=$pdo->prepare("SELECT submission_id FROM media_review_submissions WHERE owner_type='PHYSICIAN' AND owner_id=? AND purpose='DOCTOR_GALLERY' AND technical_status='READY' AND review_status='PENDING_REVIEW'".$suffix);$s->execute([$doctor]);
        return ['public'=>$public,'pending'=>count($s->fetchAll(PDO::FETCH_COLUMN))];
    }
    public static function requireCandidateSlot(PDO $pdo,string $doctor):void
    {
        $counts=self::counts($pdo,$doctor,true);
        if($counts['public']+$counts['pending']>=self::MAX_DOCTOR_GALLERY_IMAGES)throw new \RuntimeException('gallery_limit_reached');
    }
    public static function requireApprovalSlot(PDO $pdo,string $doctor):void
    {
        if(self::counts($pdo,$doctor,true)['public']>=self::MAX_DOCTOR_GALLERY_IMAGES)throw new \RuntimeException('approval_conflict');
    }
}
