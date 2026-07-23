-- Declarative CUT01-C artifact. This migration is not executed by Activity 13.
CREATE TABLE IF NOT EXISTS agenda_public_appointment_flows (
    flow_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    appointment_id VARCHAR(64) NOT NULL,
    doctor_id VARCHAR(64) NOT NULL,
    consultorio_id VARCHAR(64) NOT NULL,
    start_at DATETIME NOT NULL,
    end_at DATETIME NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'pending_otp',
    otp_id BIGINT UNSIGNED DEFAULT NULL,
    otp_channel VARCHAR(16) DEFAULT NULL,
    otp_external_id VARCHAR(64) DEFAULT NULL,
    otp_verified_at DATETIME DEFAULT NULL,
    expires_at DATETIME NOT NULL,
    cancel_token VARCHAR(64) NOT NULL,
    payload_json JSON DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (flow_id),
    UNIQUE KEY uniq_public_flow_appointment (appointment_id),
    KEY idx_public_flow_status_expires (status, expires_at),
    KEY idx_public_flow_slot (doctor_id, consultorio_id, start_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
