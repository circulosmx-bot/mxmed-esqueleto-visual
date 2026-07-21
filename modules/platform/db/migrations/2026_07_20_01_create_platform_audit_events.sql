-- Versioned Gate 6D artifact. This file is not executed by the gate.
CREATE TABLE platform_audit_events (
    stream_key VARCHAR(191) NOT NULL,
    sequence_number BIGINT UNSIGNED NOT NULL,
    event_id CHAR(64) NOT NULL,
    schema_version VARCHAR(32) NOT NULL,
    occurred_at_utc DATETIME(6) NOT NULL,
    action VARCHAR(128) NOT NULL,
    risk_level VARCHAR(2) NOT NULL,
    outcome VARCHAR(16) NOT NULL,
    reason_code VARCHAR(128) NULL,
    real_actor_reference VARCHAR(191) NULL,
    effective_actor_reference VARCHAR(191) NULL,
    affected_subject_reference VARCHAR(191) NULL,
    correlation_id VARCHAR(191) NOT NULL,
    request_id VARCHAR(191) NOT NULL,
    case_reference VARCHAR(191) NULL,
    resource_type VARCHAR(128) NULL,
    resource_reference VARCHAR(191) NULL,
    metadata_json JSON NOT NULL,
    previous_hash CHAR(64) NOT NULL,
    event_hash CHAR(64) NOT NULL,
    created_at_utc DATETIME(6) NOT NULL,
    PRIMARY KEY (stream_key, sequence_number),
    UNIQUE KEY uq_platform_audit_event_id (event_id),
    UNIQUE KEY uq_platform_audit_event_hash (event_hash),
    KEY idx_platform_audit_correlation (correlation_id),
    KEY idx_platform_audit_request (request_id),
    KEY idx_platform_audit_occurred (occurred_at_utc)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

DELIMITER //
CREATE TRIGGER platform_audit_events_no_update
BEFORE UPDATE ON platform_audit_events
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_event_update_forbidden';
END//

CREATE TRIGGER platform_audit_events_no_delete
BEFORE DELETE ON platform_audit_events
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_event_delete_forbidden';
END//
DELIMITER ;
