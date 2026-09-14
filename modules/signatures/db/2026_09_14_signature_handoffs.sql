-- SIG03B temporary bearer authority. No plaintext tokens or backfill.
CREATE TABLE IF NOT EXISTS physician_signature_handoffs (
 id CHAR(36) NOT NULL,
 token_hash CHAR(64) NOT NULL,
 doctor_id VARCHAR(64) NOT NULL,
 created_by_account_id VARCHAR(191) NOT NULL,
 purpose VARCHAR(48) NOT NULL DEFAULT 'PHYSICIAN_SIGNATURE_HANDOFF',
 status ENUM('PENDING','COMPLETED') NOT NULL DEFAULT 'PENDING',
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 expires_at DATETIME(6) NOT NULL,
 completed_at DATETIME(6) NULL,
 PRIMARY KEY (id),
 UNIQUE KEY uq_signature_handoff_token (token_hash),
 KEY ix_signature_handoff_doctor (doctor_id,purpose,status),
 KEY ix_signature_handoff_expiry (expires_at),
 CONSTRAINT fk_signature_handoff_doctor FOREIGN KEY (doctor_id) REFERENCES profiles_doctors(doctor_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
