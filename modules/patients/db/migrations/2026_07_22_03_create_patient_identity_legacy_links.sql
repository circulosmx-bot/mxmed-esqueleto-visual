-- Declarative Gate 8G artifact. This migration is not executed by the gate.
CREATE TABLE IF NOT EXISTS patient_identity_legacy_links (
    legacy_patient_key_hash CHAR(64) NOT NULL,
    canonical_patient_id VARCHAR(64) NOT NULL,
    resolution_decision_digest CHAR(64) NOT NULL,
    audit_event_id CHAR(64) NOT NULL,
    policy_version INT UNSIGNED NOT NULL,
    link_state VARCHAR(16) NOT NULL DEFAULT 'active',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    ended_at DATETIME(6) NULL,
    PRIMARY KEY (legacy_patient_key_hash),
    UNIQUE KEY ux_patient_identity_legacy_links_decision (resolution_decision_digest),
    UNIQUE KEY ux_patient_identity_legacy_links_audit_event (audit_event_id),
    KEY idx_patient_identity_legacy_links_patient_state (canonical_patient_id, link_state),
    KEY idx_patient_identity_legacy_links_state_created (link_state, created_at),
    CONSTRAINT ck_patient_identity_legacy_links_state CHECK (link_state IN ('active', 'revoked')),
    CONSTRAINT ck_patient_identity_legacy_links_policy CHECK (policy_version >= 1),
    CONSTRAINT ck_patient_identity_legacy_links_hashes CHECK (
        legacy_patient_key_hash REGEXP '^[a-f0-9]{64}$'
        AND resolution_decision_digest REGEXP '^[a-f0-9]{64}$'
        AND audit_event_id REGEXP '^[a-f0-9]{64}$'
    ),
    CONSTRAINT ck_patient_identity_legacy_links_ended CHECK (
        (link_state = 'active' AND ended_at IS NULL) OR (link_state = 'revoked' AND ended_at IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
