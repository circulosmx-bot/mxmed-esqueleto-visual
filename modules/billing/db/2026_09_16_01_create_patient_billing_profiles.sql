-- FISC01: current-state fiscal receivers scoped to one doctor/patient relationship.
-- Apply only after inspecting the physical database for existing billing authority.
-- This migration creates no invoice, CFDI, PAC, PDF or XML authority.
CREATE TABLE `billing_patient_profiles` (
  `billing_profile_id` CHAR(36) NOT NULL,
  `doctor_id` VARCHAR(64) NOT NULL,
  `patient_id` VARCHAR(64) NOT NULL,
  `alias` VARCHAR(80) NOT NULL,
  `receiver_legal_name` VARCHAR(254) NOT NULL,
  `rfc` VARCHAR(13) NOT NULL,
  `fiscal_zip_code` CHAR(5) NOT NULL,
  `fiscal_regime_code` CHAR(3) NOT NULL,
  `default_cfdi_use_code` VARCHAR(4) NOT NULL,
  `billing_email` VARCHAR(190) DEFAULT NULL,
  `is_default` TINYINT(1) NOT NULL DEFAULT 0,
  `archived_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `active_default_slot` TINYINT GENERATED ALWAYS AS
    (CASE WHEN `archived_at` IS NULL AND `is_default` = 1 THEN 1 ELSE NULL END) STORED,
  PRIMARY KEY (`billing_profile_id`),
  UNIQUE KEY `uq_billing_patient_active_default` (`doctor_id`, `patient_id`, `active_default_slot`),
  KEY `idx_billing_patient_active` (`doctor_id`, `patient_id`, `archived_at`, `created_at`, `billing_profile_id`),
  CONSTRAINT `fk_billing_patient_link` FOREIGN KEY (`doctor_id`, `patient_id`)
    REFERENCES `patients_doctor_links` (`doctor_id`, `patient_id`),
  CONSTRAINT `fk_billing_patient_identity` FOREIGN KEY (`patient_id`)
    REFERENCES `patients_patients` (`patient_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
