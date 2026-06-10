-- Schema mínimo para el dominio Pacientes
-- Tablas: patients_patients, patients_profiles, patients_contacts, patients_addresses, patients_consents, patients_doctor_links

CREATE TABLE IF NOT EXISTS `patients_patients` (
  `patient_id` VARCHAR(64) NOT NULL,
  `display_name` VARCHAR(160) NOT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'active',
  `birthdate` DATE DEFAULT NULL,
  `sex` VARCHAR(16) DEFAULT NULL,
  `notes_admin` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`patient_id`),
  KEY `idx_patients_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `patients_profiles` (
  `profile_id` VARCHAR(64) NOT NULL,
  `patient_id` VARCHAR(64) NOT NULL,
  `first_name` VARCHAR(120) DEFAULT NULL,
  `paternal_last_name` VARCHAR(120) DEFAULT NULL,
  `maternal_last_name` VARCHAR(120) DEFAULT NULL,
  `marital_status` VARCHAR(64) DEFAULT NULL,
  `occupation` VARCHAR(190) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`profile_id`),
  UNIQUE KEY `uq_profiles_patient` (`patient_id`),
  KEY `idx_profiles_names` (`first_name`, `paternal_last_name`, `maternal_last_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `patients_contacts` (
  `contact_id` VARCHAR(64) NOT NULL,
  `patient_id` VARCHAR(64) NOT NULL,
  `phone` VARCHAR(32) DEFAULT NULL,
  `email` VARCHAR(190) DEFAULT NULL,
  `preferred_contact_method` VARCHAR(32) DEFAULT NULL,
  `is_primary` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`contact_id`),
  KEY `idx_contacts_patient` (`patient_id`),
  KEY `idx_contacts_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `patients_addresses` (
  `address_id` VARCHAR(64) NOT NULL,
  `patient_id` VARCHAR(64) NOT NULL,
  `address_type` VARCHAR(32) NOT NULL DEFAULT 'home',
  `is_primary` TINYINT(1) NOT NULL DEFAULT 1,
  `country` VARCHAR(2) NOT NULL DEFAULT 'MX',
  `postal_code` VARCHAR(16) DEFAULT NULL,
  `colony` VARCHAR(190) DEFAULT NULL,
  `state` VARCHAR(190) DEFAULT NULL,
  `municipality` VARCHAR(190) DEFAULT NULL,
  `locality` VARCHAR(190) DEFAULT NULL,
  `street` VARCHAR(190) DEFAULT NULL,
  `exterior_number` VARCHAR(32) DEFAULT NULL,
  `interior_number` VARCHAR(32) DEFAULT NULL,
  `floor` VARCHAR(32) DEFAULT NULL,
  `catalog_cp_colonia_id` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`address_id`),
  KEY `idx_addresses_patient` (`patient_id`),
  KEY `idx_addresses_patient_primary` (`patient_id`, `is_primary`),
  KEY `idx_addresses_postal_code` (`postal_code`),
  KEY `idx_addresses_catalog_cp_colonia` (`catalog_cp_colonia_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `patients_consents` (
  `consent_id` VARCHAR(64) NOT NULL,
  `patient_id` VARCHAR(64) NOT NULL,
  `consent_type` VARCHAR(64) NOT NULL,
  `consent_value` TINYINT(1) NOT NULL,
  `version` VARCHAR(64) DEFAULT NULL,
  `consented_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `source` VARCHAR(32) DEFAULT NULL,
  `actor_id` VARCHAR(64) DEFAULT NULL,
  PRIMARY KEY (`consent_id`),
  KEY `idx_consents_patient` (`patient_id`),
  KEY `idx_consents_patient_type_time` (`patient_id`, `consent_type`, `consented_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `patients_doctor_links` (
  `link_id` VARCHAR(64) NOT NULL,
  `doctor_id` VARCHAR(64) NOT NULL,
  `patient_id` VARCHAR(64) NOT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ended_at` DATETIME DEFAULT NULL,
  `created_by_role` VARCHAR(32) DEFAULT NULL,
  `created_by_id` VARCHAR(64) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  PRIMARY KEY (`link_id`),
  KEY `idx_links_doctor_status` (`doctor_id`, `status`),
  KEY `idx_links_patient` (`patient_id`),
  UNIQUE KEY `uq_links_doctor_patient` (`doctor_id`, `patient_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
