-- REC-TPL02: private, patient-independent physician prescription templates.
-- Rehearsable: creation is idempotent and does not touch prescriptions or patients.
CREATE TABLE IF NOT EXISTS clinical_prescription_templates (
  template_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  template_uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  doctor_id VARCHAR(64) NOT NULL,
  template_name VARCHAR(160) NOT NULL,
  content_json JSON NOT NULL,
  status ENUM('active','archived') NOT NULL DEFAULT 'active',
  version INT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (template_id),
  UNIQUE KEY uq_prescription_template_uuid (template_uuid),
  KEY idx_prescription_template_owner (doctor_id, status, updated_at, template_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
