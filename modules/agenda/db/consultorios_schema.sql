-- Persistencia base de datos para datos generales de Consultorio (no horarios).
-- Horarios viven en consultorio_schedule.

CREATE TABLE IF NOT EXISTS `consultorios` (
  `doctor_id` VARCHAR(64) NOT NULL,
  `consultorio_id` VARCHAR(64) NOT NULL,
  `group_id` VARCHAR(64) DEFAULT NULL,
  `titulo` VARCHAR(190) DEFAULT NULL,
  `grupo_nombre` VARCHAR(190) DEFAULT NULL,
  `calle` VARCHAR(190) DEFAULT NULL,
  `num_ext` VARCHAR(32) DEFAULT NULL,
  `num_int` VARCHAR(32) DEFAULT NULL,
  `cp` VARCHAR(16) DEFAULT NULL,
  `colonia` VARCHAR(120) DEFAULT NULL,
  `municipio` VARCHAR(120) DEFAULT NULL,
  `estado` VARCHAR(120) DEFAULT NULL,
  `telefonos_json` JSON DEFAULT NULL,
  `whatsapp` VARCHAR(32) DEFAULT NULL,
  `urgencias_json` JSON DEFAULT NULL,
  `logo_url` TEXT DEFAULT NULL,
  `foto_url` TEXT DEFAULT NULL,
  `lat` DECIMAL(10,7) DEFAULT NULL,
  `lng` DECIMAL(10,7) DEFAULT NULL,
  `geocode_source` VARCHAR(32) DEFAULT NULL,
  `geocode_updated_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`doctor_id`, `consultorio_id`),
  KEY `idx_consultorios_doctor` (`doctor_id`),
  KEY `idx_consultorios_group_id` (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
