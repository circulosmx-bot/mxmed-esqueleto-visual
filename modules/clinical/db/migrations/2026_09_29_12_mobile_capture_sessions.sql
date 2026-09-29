-- R43A: temporary session/page authority; finalized content uses existing media bundles.
-- No token, document or binary table changes. Repeated application is idempotent.
CREATE TABLE IF NOT EXISTS clinical_mobile_capture_sessions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  session_uuid CHAR(36) NOT NULL,
  patient_id VARCHAR(128) NOT NULL,
  encounter_key VARCHAR(191) NOT NULL,
  classification_key VARCHAR(80) NOT NULL,
  classification_label_snapshot VARCHAR(160) NOT NULL,
  title VARCHAR(160) NOT NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'OPEN',
  continuation_hash CHAR(64) NULL,
  expires_at DATETIME NOT NULL,
  finalized_at DATETIME NULL,
  cancelled_at DATETIME NULL,
  media_bundle_id CHAR(36) NULL,
  created_by_user_id VARCHAR(64) NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_mobile_session_uuid (session_uuid),
  KEY idx_mobile_patient_encounter (patient_id,encounter_key),
  KEY idx_mobile_session_expiry (status,expires_at),
  CONSTRAINT chk_mobile_session_status CHECK (status IN ('OPEN','FINALIZING','COMPLETED','CANCELLED','EXPIRED'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS clinical_mobile_capture_pages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  page_uuid CHAR(36) NOT NULL,
  capture_session_id BIGINT UNSIGNED NOT NULL,
  token_id INT NOT NULL,
  page_order INT UNSIGNED NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'PENDING',
  optimized_manifest_json JSON NULL,
  thumbnail_manifest_json JSON NULL,
  original_audit_json JSON NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  removed_at DATETIME NULL,
  UNIQUE KEY uq_mobile_page_uuid (page_uuid),
  UNIQUE KEY uq_mobile_page_token (token_id),
  UNIQUE KEY uq_mobile_page_order (capture_session_id,page_order),
  KEY idx_mobile_page_status (capture_session_id,status),
  CONSTRAINT fk_mobile_page_session FOREIGN KEY (capture_session_id) REFERENCES clinical_mobile_capture_sessions(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  -- token_id references existing clinical_note_capture_tokens.id. The legacy table is
  -- runtime-provisioned; use application referential checks instead of creating it here.
  CONSTRAINT chk_mobile_page_status CHECK (status IN ('PENDING','READY','REMOVED')),
  CONSTRAINT chk_mobile_page_order CHECK ((status='REMOVED' AND page_order IS NULL) OR (status IN ('PENDING','READY') AND page_order IS NOT NULL AND page_order>0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
