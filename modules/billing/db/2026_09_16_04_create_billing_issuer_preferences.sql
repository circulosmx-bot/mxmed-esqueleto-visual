-- FISC-UX02. Forward-only per-issuer presentation defaults; no historical rows changed.
CREATE TABLE `billing_issuer_preferences` (
  `issuer_profile_id` CHAR(36) NOT NULL,
  `doctor_id` VARCHAR(64) NOT NULL,
  `default_concept_description` VARCHAR(1000) NOT NULL,
  `habitual_unit_price` DECIMAL(18,6) DEFAULT NULL,
  `invoice_logo_mode` VARCHAR(24) NOT NULL DEFAULT 'NONE',
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`issuer_profile_id`),
  KEY `idx_billing_issuer_preferences_doctor` (`doctor_id`),
  CONSTRAINT `fk_billing_issuer_preferences_issuer` FOREIGN KEY (`issuer_profile_id`,`doctor_id`)
    REFERENCES `billing_issuer_profiles` (`issuer_profile_id`,`doctor_id`),
  CONSTRAINT `ck_billing_issuer_preferences_logo_mode` CHECK (`invoice_logo_mode` IN ('PROFESSIONAL_LOGO','NONE')),
  CONSTRAINT `ck_billing_issuer_preferences_price` CHECK (`habitual_unit_price` IS NULL OR `habitual_unit_price`>=0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
