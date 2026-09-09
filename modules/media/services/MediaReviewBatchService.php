<?php
declare(strict_types=1);
namespace Media\Services;
use PDO;
use RuntimeException;
final class MediaReviewBatchService
{
    public const MEDIA_REVIEW_BATCH_INACTIVITY_MINUTES = 30;
    public const MAX_EXECUTOR_BATCHES = 100;
    public function __construct(private PDO $pdo) {}

    /** Candidate transaction already holds the canonical physician row lock. */
    public function joinLocked(string $doctor): string
    {
        if (!$this->pdo->inTransaction()) throw new RuntimeException('batch_transaction_required');
        $s=$this->pdo->prepare("SELECT batch_id FROM media_review_batches WHERE owner_type='PHYSICIAN' AND owner_id=? AND status='OPEN' FOR UPDATE");
        $s->execute([$doctor]);$id=$s->fetchColumn();
        if ($id===false) {
            $b=random_bytes(16);$b[6]=chr((ord($b[6])&15)|64);$b[8]=chr((ord($b[8])&63)|128);$h=bin2hex($b);
            $id=substr($h,0,8).'-'.substr($h,8,4).'-'.substr($h,12,4).'-'.substr($h,16,4).'-'.substr($h,20);
            $s=$this->pdo->prepare("INSERT INTO media_review_batches(batch_id,owner_type,owner_id) VALUES(?,'PHYSICIAN',?)");$s->execute([$id,$doctor]);
        }
        $this->pdo->prepare('UPDATE media_review_batches SET last_activity_at=CURRENT_TIMESTAMP(6),updated_at=CURRENT_TIMESTAMP(6) WHERE batch_id=?')->execute([$id]);
        return $id;
    }
    /** Owner withdrawal holds the physician lock; never detach submitted history. */
    public function retireEmptyOpenLocked(string $doctor,string $withdrawnId): bool
    {
        if(!$this->pdo->inTransaction())throw new RuntimeException('batch_transaction_required');
        $s=$this->pdo->prepare("SELECT b.batch_id FROM media_review_batches b WHERE b.owner_type='PHYSICIAN' AND b.owner_id=? AND b.status='OPEN' AND EXISTS(SELECT 1 FROM media_review_submissions s WHERE s.batch_id=b.batch_id AND s.submission_id=? AND s.review_status='WITHDRAWN') FOR UPDATE");
        $s->execute([$doctor,$withdrawnId]);$id=$s->fetchColumn();if($id===false)return false;
        $s=$this->pdo->prepare("SELECT submission_id FROM media_review_submissions WHERE batch_id=? AND technical_status='READY' AND review_status='PENDING_REVIEW' FOR UPDATE");
        $s->execute([$id]);if($s->fetchColumn()!==false)return false;
        $this->pdo->prepare('UPDATE media_review_submissions SET batch_id=NULL,updated_at=updated_at WHERE batch_id=?')->execute([$id]);
        $s=$this->pdo->prepare("DELETE FROM media_review_batches WHERE batch_id=? AND status='OPEN'");$s->execute([$id]);
        if($s->rowCount()!==1)throw new RuntimeException('batch_retirement_conflict');
        return true;
    }
    public function current(string $doctor): array
    {
        $s=$this->pdo->prepare("SELECT b.opened_at,b.last_activity_at,DATE_ADD(b.last_activity_at,INTERVAL ".self::MEDIA_REVIEW_BATCH_INACTIVITY_MINUTES." MINUTE) auto_submit_after,(SELECT COUNT(*) FROM media_review_submissions s WHERE s.batch_id=b.batch_id) item_count,(SELECT COUNT(*) FROM media_review_submissions s WHERE s.batch_id=b.batch_id AND s.technical_status='READY' AND s.review_status='PENDING_REVIEW') pending_count FROM media_review_batches b WHERE b.owner_type='PHYSICIAN' AND b.owner_id=? AND b.status='OPEN'");
        $s->execute([$doctor]);$r=$s->fetch(PDO::FETCH_ASSOC);
        return ['has_open_batch'=>$r!==false,'item_count'=>$r?(int)$r['item_count']:0,'opened_at'=>$r['opened_at']??null,'last_activity_at'=>$r['last_activity_at']??null,'auto_submit_after'=>$r['auto_submit_after']??null,'can_submit_now'=>$r&&(int)$r['pending_count']>0];
    }
    /** $eligibleId is executor-only, never an owner-supplied selector. */
    public function submit(string $doctor,?string $eligibleId=null): bool
    {
        if($this->pdo->inTransaction())throw new RuntimeException('batch_outer_transaction');
        try {
            if(!$this->pdo->beginTransaction())throw new RuntimeException('batch_begin_failed');
            $s=$this->pdo->prepare('SELECT doctor_id FROM profiles_doctors WHERE doctor_id=? FOR UPDATE');$s->execute([$doctor]);
            if($s->fetchColumn()===false)throw new RuntimeException('batch_owner_not_found');
            $s=$this->pdo->prepare("SELECT batch_id,(last_activity_at<=DATE_SUB(CURRENT_TIMESTAMP(6),INTERVAL ".self::MEDIA_REVIEW_BATCH_INACTIVITY_MINUTES." MINUTE)) eligible FROM media_review_batches WHERE owner_type='PHYSICIAN' AND owner_id=? AND status='OPEN' FOR UPDATE");$s->execute([$doctor]);$row=$s->fetch(PDO::FETCH_ASSOC);
            if(!$row||($eligibleId!==null&&($row['batch_id']!==$eligibleId||!(bool)$row['eligible']))){$this->pdo->rollBack();return false;}
            // Locking read avoids a stale REPEATABLE READ snapshot after waiting on the owner.
            $s=$this->pdo->prepare("SELECT submission_id FROM media_review_submissions WHERE batch_id=? AND technical_status='READY' AND review_status='PENDING_REVIEW' FOR UPDATE");$s->execute([$row['batch_id']]);
            if(!$s->fetchColumn()){$this->pdo->rollBack();return false;}
            $this->pdo->prepare("UPDATE media_review_batches SET status='SUBMITTED',submitted_at=CURRENT_TIMESTAMP(6),updated_at=CURRENT_TIMESTAMP(6) WHERE batch_id=?")->execute([$row['batch_id']]);
            $this->pdo->prepare('INSERT INTO media_review_batch_ready_events(batch_id) VALUES(?)')->execute([$row['batch_id']]);
            if(!$this->pdo->commit())throw new RuntimeException('batch_commit_failed');
            return true;
        }catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
    public function submitInactive(int $limit=self::MAX_EXECUTOR_BATCHES): array
    {
        if($limit<1||$limit>self::MAX_EXECUTOR_BATCHES)throw new RuntimeException('batch_invalid_limit');
        $s=$this->pdo->prepare("SELECT batch_id,owner_id FROM media_review_batches b WHERE status='OPEN' AND last_activity_at<=DATE_SUB(CURRENT_TIMESTAMP(6),INTERVAL ".self::MEDIA_REVIEW_BATCH_INACTIVITY_MINUTES." MINUTE) AND EXISTS(SELECT 1 FROM media_review_submissions s WHERE s.batch_id=b.batch_id AND s.technical_status='READY' AND s.review_status='PENDING_REVIEW') ORDER BY last_activity_at,batch_id LIMIT ?");$s->bindValue(1,$limit,PDO::PARAM_INT);$s->execute();$rows=$s->fetchAll(PDO::FETCH_ASSOC);$submitted=0;
        foreach($rows as $row)if($this->submit($row['owner_id'],$row['batch_id']))$submitted++;
        return ['examined'=>count($rows),'submitted'=>$submitted,'skipped'=>count($rows)-$submitted];
    }
}
