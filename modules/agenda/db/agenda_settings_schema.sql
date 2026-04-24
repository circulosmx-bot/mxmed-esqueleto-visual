-- Persistencia operativa de "Mi Agenda · Configuración".
-- No incluye horarios: esos viven en consultorio_schedule.

CREATE TABLE IF NOT EXISTS `agenda_settings` (
  `doctor_id` VARCHAR(64) NOT NULL,
  `consultorio_id` VARCHAR(64) NOT NULL,
  `appointment_duration_min` INT NOT NULL DEFAULT 30,
  `gap_between_appointments_min` INT NOT NULL DEFAULT 0,
  `channels_json` JSON DEFAULT NULL,
  `cancellation_policy_hours` INT DEFAULT NULL,
  `reminder_template` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`doctor_id`, `consultorio_id`),
  KEY `idx_agenda_settings_doctor` (`doctor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

