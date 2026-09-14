<?php
declare(strict_types=1);
namespace Media\Services;
require_once __DIR__.'/MediaSubmissionActor.php';
require_once __DIR__.'/MediaReviewAudit.php';
use PDO;
use RuntimeException;
use Platform\Contracts\{CanonicalAuditEventInput,TrustedAuditContext};
use Platform\Services\RandomAuditUuidProvider;

final class MediaCandidateSubmissionService
{
    public function __construct(private PDO $pdo) {}
    public function submit(string $id,MediaSubmissionActor $actor): array
    {
        if($actor->system)throw new RuntimeException('submission_unauthorized');
        if(!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D',$id))throw new RuntimeException('submission_invalid_request');
        if($this->pdo->inTransaction())throw new RuntimeException('submission_outer_transaction');
        try {
            if(!$this->pdo->beginTransaction())throw new RuntimeException('submission_begin_failed');
            $s=$this->pdo->prepare('SELECT doctor_id FROM profiles_doctors WHERE doctor_id=? FOR UPDATE');$s->execute([$actor->doctor]);
            if($s->fetchColumn()===false)throw new RuntimeException('submission_not_found');
            $s=$this->pdo->prepare("SELECT * FROM media_review_submissions WHERE submission_id=? AND owner_type='PHYSICIAN' AND owner_id=? FOR UPDATE");$s->execute([$id,$actor->doctor]);$row=$s->fetch(PDO::FETCH_ASSOC);
            if(!$row)throw new RuntimeException('submission_not_found');
            if($row['technical_status']!=='READY'||$row['review_status']!=='PENDING_REVIEW')throw new RuntimeException('submission_conflict');
            // A retry after a committed response was lost must not append a second event.
            if($row['submitted_for_review_at']!==null){$this->pdo->rollBack();return ['submission_id'=>$id,'submitted_for_review_at'=>$row['submitted_for_review_at'],'already_submitted'=>true];}
            if($row['batch_id']===null)throw new RuntimeException('submission_legacy_already_reviewable');
            $s=$this->pdo->prepare("SELECT status FROM media_review_batches WHERE batch_id=? AND owner_type='PHYSICIAN' AND owner_id=? FOR UPDATE");$s->execute([$row['batch_id'],$actor->doctor]);
            if($s->fetchColumn()!=='OPEN')throw new RuntimeException('submission_conflict');
            $at=$this->submitLocked($row,$actor,'ITEM');
            // OPEN remains an accepting collection session, even if currently all sent.
            if(!$this->pdo->commit())throw new RuntimeException('submission_commit_failed');
            return ['submission_id'=>$id,'submitted_for_review_at'=>$at,'already_submitted'=>false];
        } catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
    /** Caller holds physician, batch and item locks, with an outer transaction. */
    public function submitLocked(array $row,MediaSubmissionActor $actor,string $mode): string
    {
        if(!$this->pdo->inTransaction() || $row['owner_type']!=='PHYSICIAN' || $row['owner_id']!==$actor->doctor
            || $row['batch_id']===null || $row['technical_status']!=='READY' || $row['review_status']!=='PENDING_REVIEW'
            || $row['submitted_for_review_at']!==null || !in_array($mode,['ITEM','BATCH','INACTIVITY'],true)
            || ($actor->system !== ($mode==='INACTIVITY'))
            || !in_array($row['purpose'],['DOCTOR_PROFILE_PHOTO','PHYSICIAN_PERSONAL_LOGO','DOCTOR_GALLERY'],true))throw new RuntimeException('submission_conflict');
        $s=$this->pdo->prepare('UPDATE media_review_submissions SET submitted_for_review_at=CURRENT_TIMESTAMP(6),updated_at=CURRENT_TIMESTAMP WHERE submission_id=? AND submitted_for_review_at IS NULL');$s->execute([$row['submission_id']]);
        if($s->rowCount()!==1)throw new RuntimeException('submission_conflict');
        $s=$this->pdo->prepare('SELECT submitted_for_review_at FROM media_review_submissions WHERE submission_id=?');$s->execute([$row['submission_id']]);$at=$s->fetchColumn();
        $uuid=new RandomAuditUuidProvider();
        $trusted=TrustedAuditContext::fromServer($actor->id,$actor->system?'system':'account',$actor->system?'executor':'doctor','physician:'.$actor->doctor,
            $uuid->generateCanonicalUuid(),$uuid->generateCanonicalUuid(),$actor->session,null,null,'MEDIA',
            $mode==='ITEM'?'POST /api/media/review-candidate-submit.php':($mode==='BATCH'?'POST /api/media/review-batch-submit.php':'media-review-batch-inactivity-executor'));
        MediaReviewAudit::writer($this->pdo)->append(new CanonicalAuditEventInput('MEDIA_REVIEW_CANDIDATE_SUBMITTED','SUCCESS',$actor->system?'SYSTEM_POLICY':'USER_REQUEST',
            'PHYSICIAN',$actor->doctor,'media_review_submission',$row['submission_id'],
            ['submission_id'=>$row['submission_id'],'physician_id'=>$actor->doctor,'purpose'=>$row['purpose'],'batch_id'=>$row['batch_id'],
             'transition'=>'UNSUBMITTED_TO_SUBMITTED','submitted_for_review_at'=>$at,'submission_mode'=>$mode]),$trusted);
        return $at;
    }
}
