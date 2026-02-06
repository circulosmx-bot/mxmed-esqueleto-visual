-- READY MODE schema for Agenda QA
-- tablas mínimas para que QA_MODE=ready pueda ejecutar writes y lecturas.

CREATE TABLE IF NOT EXISTS `agenda_appointments` (
  `appointment_id` VARCHAR(64) NOT NULL,
  `doctor_id` VARCHAR(64) NOT NULL,
  `consultorio_id` VARCHAR(64) NOT NULL,
  `patient_id` VARCHAR(64),
  `start_at` DATETIME NOT NULL,
  `end_at` DATETIME NOT NULL,
  `modality` VARCHAR(32) NOT NULL,
  `status` VARCHAR(32),
  `channel_origin` VARCHAR(64),
  `created_by_role` VARCHAR(32),
  `created_by_id` VARCHAR(64),
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`appointment_id`),
  KEY `idx_appointments_patient` (`patient_id`),
  KEY `idx_appointments_doctor_start` (`doctor_id`, `start_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `agenda_appointment_events` (
  `event_id` VARCHAR(64) NOT NULL,
  `appointment_id` VARCHAR(64) NOT NULL,
  `event_type` VARCHAR(64) NOT NULL,
  `timestamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `from_datetime` DATETIME,
  `to_datetime` DATETIME,
  `from_start_at` DATETIME,
  `from_end_at` DATETIME,
  `to_start_at` DATETIME,
  `to_end_at` DATETIME,
  `observed_at` DATETIME,
  `motivo_code` VARCHAR(64),
  `motivo_text` TEXT,
  `notify_patient` TINYINT(1),
  `contact_method` VARCHAR(32),
  `actor_role` VARCHAR(32),
  `actor_id` VARCHAR(64),
  `channel_origin` VARCHAR(64),
  `notes` TEXT,
  PRIMARY KEY (`event_id`),
  KEY `idx_events_appointment` (`appointment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `agenda_patient_flags` (
  `flag_id` VARCHAR(64) NOT NULL,
  `patient_id` VARCHAR(64) NOT NULL,
  `flag_type` VARCHAR(32) NOT NULL,
  `reason_code` VARCHAR(64),
  `source_appointment_id` VARCHAR(64),
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` DATETIME,
  `actor_role` VARCHAR(32),
  `actor_id` VARCHAR(64),
  `channel_origin` VARCHAR(64),
  `notes` TEXT,
  PRIMARY KEY (`flag_id`),
  KEY `idx_flags_patient` (`patient_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
