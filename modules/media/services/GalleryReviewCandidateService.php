<?php
declare(strict_types=1);
namespace Media\Services;
require_once __DIR__.'/PhysicianMediaReviewCandidateService.php';
use Media\Contracts\PrivateMediaStoragePort;
use PDO;
use RuntimeException;
final class GalleryReviewCandidateService
{
    public function __construct(private PDO $pdo,private PrivateMediaStoragePort $storage){}
    public function upload(string $doctor,array $upload):void
    {
        (new PhysicianMediaReviewCandidateService($this->pdo,$this->storage,'DOCTOR_GALLERY'))->upload($doctor,$upload);
    }
    public function listing(string $doctor,int $offset=0):array
    {
        if($offset<0||$offset>1000000)throw new RuntimeException('gallery_invalid_request');
        $s=$this->pdo->prepare("SELECT submission_id,technical_status,review_status,review_reason_code,review_feedback,review_decided_at,created_at,updated_at FROM media_review_submissions WHERE owner_type='PHYSICIAN' AND owner_id=? AND purpose='DOCTOR_GALLERY' AND review_status IN ('PENDING_REVIEW','NEEDS_WORK') ORDER BY created_at ASC,submission_id ASC LIMIT 51 OFFSET ?");
        $s->bindValue(1,$doctor);$s->bindValue(2,$offset,PDO::PARAM_INT);$s->execute();$rows=$s->fetchAll(PDO::FETCH_ASSOC);$more=count($rows)>50;$items=[];
        foreach(array_slice($rows,0,50) as $row)$items[]=['submission_id'=>$row['submission_id'],'technical_status'=>$row['technical_status'],'review_status'=>$row['review_status'],
            'reason_code'=>$row['review_reason_code'],'reason_label'=>MediaReplacementReasons::LABELS[$row['review_reason_code']??'']??null,'review_feedback'=>$row['review_feedback'],
            'review_decided_at'=>$row['review_decided_at'],'created_at'=>$row['created_at'],'updated_at'=>$row['updated_at']];
        return ['items'=>$items,'pagination'=>['limit'=>50,'offset'=>$offset,'has_more'=>$more,'next_offset'=>$more?$offset+50:null],'capacity'=>GalleryCapacity::counts($this->pdo,$doctor),'max_total_active'=>GalleryCapacity::MAX_DOCTOR_GALLERY_IMAGES];
    }
    public function withdraw(string $doctor,string $id):void
    {
        if(!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D',$id))throw new RuntimeException('gallery_invalid_request');
        if($this->pdo->inTransaction())throw new RuntimeException('gallery_outer_transaction');
        try{
            if(!$this->pdo->beginTransaction())throw new RuntimeException('gallery_begin_failed');
            $s=$this->pdo->prepare('SELECT doctor_id FROM profiles_doctors WHERE doctor_id=? FOR UPDATE');$s->execute([$doctor]);if($s->fetchColumn()===false)throw new RuntimeException('gallery_candidate_not_found');
            $s=$this->pdo->prepare("SELECT review_status FROM media_review_submissions WHERE submission_id=? AND owner_type='PHYSICIAN' AND owner_id=? AND purpose='DOCTOR_GALLERY' FOR UPDATE");$s->execute([$id,$doctor]);$state=$s->fetchColumn();
            if($state===false)throw new RuntimeException('gallery_candidate_not_found');if($state!=='PENDING_REVIEW')throw new RuntimeException('gallery_candidate_conflict');
            $this->pdo->prepare("UPDATE media_review_submissions SET review_status='WITHDRAWN',updated_at=CURRENT_TIMESTAMP WHERE submission_id=?")->execute([$id]);
            (new MediaReviewBatchService($this->pdo))->retireEmptyOpenLocked($doctor,$id);
            if(!$this->pdo->commit())throw new RuntimeException('gallery_commit_failed');
        }catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
        // Retain private history; no binary cleanup policy or public mutation is needed for withdrawal.
    }
}
