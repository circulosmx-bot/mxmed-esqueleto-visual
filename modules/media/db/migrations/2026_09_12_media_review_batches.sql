-- Apply once after MR10. Existing submissions remain unbatched (NULL).
CREATE TABLE media_review_batches (
 batch_id CHAR(36) NOT NULL PRIMARY KEY,
 owner_type ENUM('PHYSICIAN') NOT NULL,
 owner_id VARCHAR(191) NOT NULL,
 status ENUM('OPEN','SUBMITTED') NOT NULL DEFAULT 'OPEN',
 opened_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 last_activity_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 submitted_at DATETIME(6) NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 open_owner VARCHAR(191) GENERATED ALWAYS AS (CASE WHEN status='OPEN' THEN owner_id ELSE NULL END) STORED,
 UNIQUE KEY uniq_batch_open_owner(owner_type,open_owner),
 UNIQUE KEY uniq_batch_owner(batch_id,owner_type,owner_id),
 KEY idx_batch_inactivity(status,last_activity_at,batch_id),
 KEY idx_batch_queue(status,submitted_at,batch_id),
 CONSTRAINT chk_batch_submitted CHECK ((status='OPEN' AND submitted_at IS NULL) OR (status='SUBMITTED' AND submitted_at IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE media_review_submissions
 ADD COLUMN batch_id CHAR(36) NULL,
 ADD KEY idx_review_batch(batch_id,owner_type,owner_id),
 ADD CONSTRAINT fk_review_batch_owner FOREIGN KEY(batch_id,owner_type,owner_id) REFERENCES media_review_batches(batch_id,owner_type,owner_id);
CREATE TABLE media_review_batch_ready_events (
 batch_id CHAR(36) NOT NULL PRIMARY KEY,
 event_type ENUM('MEDIA_REVIEW_BATCH_READY') NOT NULL DEFAULT 'MEDIA_REVIEW_BATCH_READY',
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 consumed_at DATETIME(6) NULL,
 CONSTRAINT fk_batch_ready FOREIGN KEY(batch_id) REFERENCES media_review_batches(batch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- batch_id is the entire safe signal payload; owner/time/count can be resolved from canonical tables.
-- Membership writes are confined to candidate creation under the physician/batch lock.
-- No update/move/delete batch-membership operation is exposed; SUBMITTED batches never join.
