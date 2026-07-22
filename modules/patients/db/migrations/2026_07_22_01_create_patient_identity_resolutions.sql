-- Declarative Gate 8G artifact. This migration is not executed by the gate.
CREATE TABLE IF NOT EXISTS patient_identity_resolutions (
    request_fingerprint CHAR(64) NOT NULL,
    operation_reference VARCHAR(128) NOT NULL,
    correlation_reference VARCHAR(128) NOT NULL,
    resolution_source VARCHAR(32) NOT NULL,
    input_type VARCHAR(32) NOT NULL,
    identity_reference_digest CHAR(64) NOT NULL,
    legacy_lock_digest CHAR(64) GENERATED ALWAYS AS (CASE WHEN input_type = 'legacy_patient_key_hash' THEN identity_reference_digest ELSE NULL END) STORED,
    candidate_set_digest CHAR(64) NULL,
    status VARCHAR(32) NULL,
    reason_code VARCHAR(64) NULL,
    resolved_patient_id VARCHAR(64) NULL,
    duplicate_review_id CHAR(64) NULL,
    decision_digest CHAR(64) NULL,
    audit_event_id CHAR(64) NULL,
    policy_version INT UNSIGNED NOT NULL,
    transaction_state VARCHAR(16) NOT NULL DEFAULT 'processing',
    failure_code VARCHAR(64) NULL,
    occurred_at DATETIME(6) NOT NULL,
    started_at DATETIME(6) NOT NULL,
    completed_at DATETIME(6) NULL,
    failed_at DATETIME(6) NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (request_fingerprint),
    UNIQUE KEY ux_patient_identity_resolutions_operation (operation_reference),
    UNIQUE KEY ux_patient_identity_resolutions_legacy_lock (legacy_lock_digest),
    UNIQUE KEY ux_patient_identity_resolutions_decision (decision_digest),
    UNIQUE KEY ux_patient_identity_resolutions_audit_event (audit_event_id),
    KEY idx_patient_identity_resolutions_correlation (correlation_reference),
    KEY idx_patient_identity_resolutions_status_created (status, created_at),
    KEY idx_patient_identity_resolutions_patient (resolved_patient_id),
    KEY idx_patient_identity_resolutions_state_updated (transaction_state, updated_at),
    CONSTRAINT ck_patient_identity_resolutions_source CHECK (resolution_source IN ('public_verified', 'private_authenticated', 'legacy_bridge')),
    CONSTRAINT ck_patient_identity_resolutions_input_type CHECK (input_type IN ('canonical_patient_id', 'legacy_patient_key_hash')),
    CONSTRAINT ck_patient_identity_resolutions_status CHECK (status IS NULL OR status IN ('already_canonical', 'mapped_from_legacy', 'create_minimal_required', 'review_required', 'ambiguous', 'not_found', 'invalid_candidate_set')),
    CONSTRAINT ck_patient_identity_resolutions_reason CHECK (reason_code IS NULL OR reason_code IN ('already_canonical', 'canonical_patient_not_found', 'candidate_not_eligible', 'unique_strong_identity_match', 'multiple_strong_candidates', 'identity_evidence_conflict', 'weak_identity_evidence', 'no_identity_candidate', 'invalid_candidate_set')),
    CONSTRAINT ck_patient_identity_resolutions_state CHECK (transaction_state IN ('processing', 'completed', 'failed')),
    CONSTRAINT ck_patient_identity_resolutions_policy CHECK (policy_version >= 1),
    CONSTRAINT ck_patient_identity_resolutions_hashes CHECK (
        request_fingerprint REGEXP '^[a-f0-9]{64}$'
        AND identity_reference_digest REGEXP '^[a-f0-9]{64}$'
        AND (candidate_set_digest IS NULL OR candidate_set_digest REGEXP '^[a-f0-9]{64}$')
        AND (duplicate_review_id IS NULL OR duplicate_review_id REGEXP '^[a-f0-9]{64}$')
        AND (decision_digest IS NULL OR decision_digest REGEXP '^[a-f0-9]{64}$')
        AND (audit_event_id IS NULL OR audit_event_id REGEXP '^[a-f0-9]{64}$')
    ),
    CONSTRAINT ck_patient_identity_resolutions_completed CHECK (
        transaction_state <> 'completed'
        OR (status IS NOT NULL AND reason_code IS NOT NULL AND candidate_set_digest IS NOT NULL AND decision_digest IS NOT NULL AND audit_event_id IS NOT NULL AND completed_at IS NOT NULL)
    ),
    CONSTRAINT ck_patient_identity_resolutions_failed CHECK (
        transaction_state <> 'failed' OR (failure_code IS NOT NULL AND failed_at IS NOT NULL)
    ),
    CONSTRAINT ck_patient_identity_resolutions_patient_shape CHECK (
        (status IN ('already_canonical', 'mapped_from_legacy') AND resolved_patient_id IS NOT NULL)
        OR ((status IS NULL OR status NOT IN ('already_canonical', 'mapped_from_legacy')) AND resolved_patient_id IS NULL)
    )
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
