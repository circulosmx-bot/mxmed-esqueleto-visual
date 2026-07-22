-- Declarative Gate 8G artifact. This migration is not executed by the gate.
CREATE TABLE IF NOT EXISTS patient_identity_audit_events (
    stream_key CHAR(64) NOT NULL,
    sequence_number BIGINT UNSIGNED NOT NULL,
    event_id CHAR(64) NOT NULL,
    event_type VARCHAR(64) NOT NULL,
    operation_reference VARCHAR(128) NOT NULL,
    correlation_reference VARCHAR(128) NOT NULL,
    source VARCHAR(32) NOT NULL,
    input_type VARCHAR(32) NOT NULL,
    request_fingerprint CHAR(64) NOT NULL,
    candidate_set_digest CHAR(64) NOT NULL,
    resolved_patient_id_digest CHAR(64) NULL,
    candidate_patient_id_digests_json JSON NOT NULL,
    outcome_code VARCHAR(32) NOT NULL,
    match_tier VARCHAR(32) NOT NULL,
    actor_real_reference VARCHAR(128) NOT NULL,
    actor_effective_reference VARCHAR(128) NOT NULL,
    policy_version INT UNSIGNED NOT NULL,
    occurred_at DATETIME(6) NOT NULL,
    human_review_required TINYINT(1) NOT NULL,
    create_minimal_required TINYINT(1) NOT NULL,
    merge_allowed TINYINT(1) NOT NULL DEFAULT 0,
    previous_hash CHAR(64) NOT NULL,
    event_hash CHAR(64) NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (stream_key, sequence_number),
    UNIQUE KEY ux_patient_identity_audit_events_event_id (event_id),
    UNIQUE KEY ux_patient_identity_audit_events_event_hash (event_hash),
    KEY idx_patient_identity_audit_events_request (request_fingerprint),
    KEY idx_patient_identity_audit_events_correlation (correlation_reference),
    KEY idx_patient_identity_audit_events_occurred (occurred_at),
    KEY idx_patient_identity_audit_events_outcome (outcome_code),
    CONSTRAINT ck_patient_identity_audit_event_type CHECK (event_type IN ('patient_identity_already_canonical', 'patient_identity_mapped_from_legacy', 'patient_identity_create_minimal_required', 'patient_identity_review_required', 'patient_identity_ambiguous', 'patient_identity_not_found', 'patient_identity_candidate_set_invalid')),
    CONSTRAINT ck_patient_identity_audit_source CHECK (source IN ('public_verified', 'private_authenticated', 'legacy_bridge')),
    CONSTRAINT ck_patient_identity_audit_input_type CHECK (input_type IN ('canonical_patient_id', 'legacy_patient_key_hash')),
    CONSTRAINT ck_patient_identity_audit_outcome CHECK (outcome_code IN ('already_canonical', 'mapped_from_legacy', 'create_minimal_required', 'review_required', 'ambiguous', 'not_found', 'invalid_candidate_set')),
    CONSTRAINT ck_patient_identity_audit_match_tier CHECK (match_tier IN ('contact_birthdate_exact', 'contact_name_exact', 'name_birthdate_sex_exact', 'name_birthdate_exact', 'contact_only', 'name_only', 'no_match')),
    CONSTRAINT ck_patient_identity_audit_flags CHECK (human_review_required IN (0, 1) AND create_minimal_required IN (0, 1) AND merge_allowed = 0),
    CONSTRAINT ck_patient_identity_audit_policy CHECK (policy_version >= 1),
    CONSTRAINT ck_patient_identity_audit_hashes CHECK (
        stream_key REGEXP '^[a-f0-9]{64}$'
        AND event_id REGEXP '^[a-f0-9]{64}$'
        AND request_fingerprint REGEXP '^[a-f0-9]{64}$'
        AND candidate_set_digest REGEXP '^[a-f0-9]{64}$'
        AND (resolved_patient_id_digest IS NULL OR resolved_patient_id_digest REGEXP '^[a-f0-9]{64}$')
        AND previous_hash REGEXP '^[a-f0-9]{64}$'
        AND event_hash REGEXP '^[a-f0-9]{64}$'
    ),
    CONSTRAINT ck_patient_identity_audit_candidates_json CHECK (JSON_TYPE(candidate_patient_id_digests_json) = 'ARRAY')
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

DELIMITER //
CREATE TRIGGER reject_patient_identity_audit_events_update
BEFORE UPDATE ON patient_identity_audit_events
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'patient_identity_audit_event_update_forbidden';
END//

CREATE TRIGGER reject_patient_identity_audit_events_delete
BEFORE DELETE ON patient_identity_audit_events
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'patient_identity_audit_event_delete_forbidden';
END//
DELIMITER ;
