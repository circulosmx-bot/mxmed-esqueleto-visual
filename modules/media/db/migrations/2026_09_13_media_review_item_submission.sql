-- Apply once after MR11, with media writes/executor paused (same migration convention).
-- Do not re-run DDL. Backfill is NULL-guarded and preserves updated_at.
ALTER TABLE media_review_submissions
 ADD COLUMN submitted_for_review_at DATETIME(6) NULL,
 ADD KEY idx_review_batch_unsent(batch_id,submitted_for_review_at,technical_status,review_status);

-- Membership of a closed batch is immutable. Its submission instant is canonical
-- for eligible pending members. Do not infer dates for withdrawn/decided items.
UPDATE media_review_submissions s
 JOIN media_review_batches b ON b.batch_id=s.batch_id
 SET s.submitted_for_review_at=b.submitted_at,s.updated_at=s.updated_at
 WHERE b.status='SUBMITTED' AND b.submitted_at IS NOT NULL
 AND s.technical_status='READY' AND s.review_status='PENDING_REVIEW'
 AND s.submitted_for_review_at IS NULL;
-- Unbatched legacy and decided historical rows retain unknown individual dates.
-- Compatibility readers use their pre-existing batch provenance, never created_at.
