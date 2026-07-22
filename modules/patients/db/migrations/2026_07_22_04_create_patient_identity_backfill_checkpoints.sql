-- Declarative Gate 8G artifact. This migration is not executed by the gate.
CREATE TABLE IF NOT EXISTS patient_identity_backfill_checkpoints (
    checkpoint_id CHAR(64) NOT NULL,
    job_reference VARCHAR(128) NOT NULL,
    cursor_digest CHAR(64) NULL,
    batch_number BIGINT UNSIGNED NOT NULL,
    last_processed_reference_digest CHAR(64) NULL,
    state VARCHAR(32) NOT NULL DEFAULT 'pending',
    total_evaluated BIGINT UNSIGNED NOT NULL DEFAULT 0,
    already_canonical BIGINT UNSIGNED NOT NULL DEFAULT 0,
    mapped_from_legacy BIGINT UNSIGNED NOT NULL DEFAULT 0,
    create_minimal_required BIGINT UNSIGNED NOT NULL DEFAULT 0,
    review_required BIGINT UNSIGNED NOT NULL DEFAULT 0,
    ambiguous BIGINT UNSIGNED NOT NULL DEFAULT 0,
    conflicts BIGINT UNSIGNED NOT NULL DEFAULT 0,
    checkpoint_digest CHAR(64) NOT NULL,
    error_code VARCHAR(64) NULL,
    retry_count INT UNSIGNED NOT NULL DEFAULT 0,
    started_at DATETIME(6) NULL,
    completed_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (checkpoint_id),
    UNIQUE KEY ux_patient_identity_backfill_job_batch (job_reference, batch_number),
    UNIQUE KEY ux_patient_identity_backfill_checkpoint_digest (checkpoint_digest),
    KEY idx_patient_identity_backfill_state_updated (state, updated_at),
    KEY idx_patient_identity_backfill_job (job_reference),
    CONSTRAINT ck_patient_identity_backfill_state CHECK (state IN ('pending', 'running', 'paused', 'completed', 'failed', 'aborted')),
    CONSTRAINT ck_patient_identity_backfill_hashes CHECK (
        checkpoint_id REGEXP '^[a-f0-9]{64}$'
        AND checkpoint_digest REGEXP '^[a-f0-9]{64}$'
        AND (cursor_digest IS NULL OR cursor_digest REGEXP '^[a-f0-9]{64}$')
        AND (last_processed_reference_digest IS NULL OR last_processed_reference_digest REGEXP '^[a-f0-9]{64}$')
    ),
    CONSTRAINT ck_patient_identity_backfill_completed CHECK (state <> 'completed' OR completed_at IS NOT NULL),
    CONSTRAINT ck_patient_identity_backfill_error CHECK (state NOT IN ('failed', 'aborted') OR error_code IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
