-- CONS-SIGN02B: immutable, short-lived consent review attached to a capture token.
CREATE TABLE IF NOT EXISTS clinical_consent_qr_sessions (
  token_id INT NOT NULL PRIMARY KEY,
  consent_uuid CHAR(36) NOT NULL,
  draft_ref CHAR(36) DEFAULT NULL,
  draft_version INT DEFAULT NULL,
  patient_id VARCHAR(128) NOT NULL,
  doctor_id VARCHAR(64) NOT NULL,
  actor_user_id VARCHAR(64) NOT NULL,
  role VARCHAR(32) NOT NULL,
  signer_authority VARCHAR(512) NOT NULL,
  signer_name VARCHAR(191) NOT NULL,
  content_fingerprint CHAR(64) NOT NULL,
  fingerprint_version TINYINT NOT NULL DEFAULT 1,
  document_date VARCHAR(32) NOT NULL,
  review_html MEDIUMTEXT NOT NULL,
  review_html_sha256 CHAR(64) NOT NULL,
  artifact_digest CHAR(64) DEFAULT NULL,
  reviewed_at DATETIME DEFAULT NULL,
  invalidated_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL,
  -- The capture-token table is provisioned by its existing runtime schema guard.
  -- Keep this migration deployable before the first capture token is created.
  KEY idx_consent_qr_identity (consent_uuid, patient_id, doctor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
