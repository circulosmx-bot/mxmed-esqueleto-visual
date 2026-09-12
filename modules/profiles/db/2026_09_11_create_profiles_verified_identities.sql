-- VID01: identidad oficial aceptada durante admisión, separada de la
-- presentación pública conservada en profiles_doctors.display_name.
-- No contiene backfill: los perfiles heredados permanecen sin identidad
-- verificada hasta que una autoridad interna confiable los aprovisione.
CREATE TABLE IF NOT EXISTS `profiles_verified_identities` (
  `verified_identity_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `doctor_id` VARCHAR(64) NOT NULL,
  `given_names` VARCHAR(190) NOT NULL,
  `first_surname` VARCHAR(120) NOT NULL,
  `second_surname` VARCHAR(120) DEFAULT NULL,
  `source_type` VARCHAR(32) NOT NULL,
  `source_reference` VARCHAR(190) NOT NULL,
  `verified_at` DATETIME(6) NOT NULL,
  `verified_by_account_id` VARCHAR(64) DEFAULT NULL,
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`verified_identity_id`),
  UNIQUE KEY `uniq_profiles_verified_identity_doctor` (`doctor_id`),
  KEY `idx_profiles_verified_identity_source` (`source_type`, `source_reference`),
  KEY `idx_profiles_verified_identity_approver` (`verified_by_account_id`),
  CONSTRAINT `fk_profiles_verified_identity_doctor`
    FOREIGN KEY (`doctor_id`) REFERENCES `profiles_doctors` (`doctor_id`)
    ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_profiles_verified_identity_approver`
    FOREIGN KEY (`verified_by_account_id`) REFERENCES `internal_staff` (`account_id`)
    ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `ck_profiles_verified_identity_source_type`
    CHECK (`source_type` IN ('admission_approved', 'internal_provisioning', 'governed_correction', 'synthetic_test')),
  CONSTRAINT `ck_profiles_verified_identity_names`
    CHECK (
      CHAR_LENGTH(TRIM(`given_names`)) > 0
      AND CHAR_LENGTH(TRIM(`first_surname`)) > 0
      AND (`second_surname` IS NULL OR CHAR_LENGTH(TRIM(`second_surname`)) > 0)
    ),
  CONSTRAINT `ck_profiles_verified_identity_provenance`
    CHECK (
      (`source_type` = 'synthetic_test' AND `verified_by_account_id` IS NULL)
      OR (`source_type` <> 'synthetic_test' AND `verified_by_account_id` IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
