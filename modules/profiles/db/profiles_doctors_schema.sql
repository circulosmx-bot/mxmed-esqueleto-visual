-- Fuente canonica minima para identidad profesional publica de medico.
-- Nota: specialty_secondary_json se guarda como LONGTEXT con JSON serializado
-- para maximizar compatibilidad MySQL/MariaDB entre entornos.

CREATE TABLE IF NOT EXISTS `profiles_doctors` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `doctor_id` VARCHAR(64) NOT NULL,
  `display_name` VARCHAR(190) DEFAULT NULL,
  `professional_designation` VARCHAR(120) DEFAULT NULL,
  `prefix` VARCHAR(32) DEFAULT NULL,
  `gender` VARCHAR(32) DEFAULT NULL,
  `gender_label` VARCHAR(64) DEFAULT NULL,
  `professional_license` VARCHAR(64) DEFAULT NULL,
  `specialty_license` VARCHAR(64) DEFAULT NULL,
  `specialty_primary` VARCHAR(190) DEFAULT NULL,
  `specialty_secondary_json` LONGTEXT DEFAULT NULL,
  `bio_short` TEXT DEFAULT NULL,
  `photo_url` TEXT DEFAULT NULL,
  `avatar_url` TEXT DEFAULT NULL,
  `logo_url` TEXT DEFAULT NULL,
  `profile_theme_key` VARCHAR(64) DEFAULT NULL,
  `profile_status` VARCHAR(32) NOT NULL DEFAULT 'hidden',
  `is_public_candidate` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_profiles_doctors_doctor_id` (`doctor_id`),
  KEY `idx_profiles_doctors_status` (`profile_status`, `is_public_candidate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
