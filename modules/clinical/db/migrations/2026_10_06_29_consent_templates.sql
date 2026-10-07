-- CONS-TPL01: private, patient-independent physician consent templates.
-- Rehearsable: creation is idempotent and does not touch clinical documents.
CREATE TABLE IF NOT EXISTS clinical_consent_templates (
  template_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  template_uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  doctor_id VARCHAR(64) NOT NULL,
  template_name VARCHAR(160) NOT NULL,
  document_type VARCHAR(64) NOT NULL DEFAULT 'consentimiento_informado',
  content_json JSON NOT NULL,
  status ENUM('active','archived') NOT NULL DEFAULT 'active',
  version INT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (template_id),
  UNIQUE KEY uq_consent_template_uuid (template_uuid),
  KEY idx_consent_template_owner (doctor_id, status, updated_at, template_id),
  CONSTRAINT ck_consent_template_type CHECK (document_type = 'consentimiento_informado')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
