<?php
declare(strict_types=1);
namespace Media\Services;
/** New candidates always join a batch. NULL membership is pre-MR11 legacy only
 * (or retired WITHDRAWN history); it must never be used to submit a new item. */
final class MediaSubmissionState
{
    public static function submitted(array $row): bool
    {
        return $row['submitted_for_review_at'] !== null || $row['batch_id'] === null
            || ($row['batch_status'] ?? null) === 'SUBMITTED';
    }
    public static function requireReviewable(\PDO $pdo,array $row,string $conflict): void
    {
        if($row['submitted_for_review_at']!==null || $row['batch_id']===null)return;
        $s=$pdo->prepare("SELECT status FROM media_review_batches WHERE batch_id=?");$s->execute([$row['batch_id']]);
        if($s->fetchColumn()!=='SUBMITTED')throw new \RuntimeException($conflict);
    }
    public static function sql(string $item='s'): string
    {
        return "($item.submitted_for_review_at IS NOT NULL OR $item.batch_id IS NULL OR EXISTS(SELECT 1 FROM media_review_batches legacy_batch WHERE legacy_batch.batch_id=$item.batch_id AND legacy_batch.status='SUBMITTED'))";
    }
}
