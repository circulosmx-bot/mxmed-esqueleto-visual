-- Catalog schema mínimo para Availability
-- Tabla semanal de horarios por consultorio y doctor.

CREATE TABLE IF NOT EXISTS `consultorio_schedule` (
  `schedule_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `doctor_id` VARCHAR(64) NOT NULL,
  `consultorio_id` VARCHAR(64) NOT NULL,
  `weekday` TINYINT UNSIGNED NOT NULL COMMENT '1=Lunes ... 7=Domingo',
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`schedule_id`),
  KEY `idx_schedule_doctor_consultorio_weekday` (`doctor_id`, `consultorio_id`, `weekday`),
  KEY `idx_schedule_consultorio_weekday` (`consultorio_id`, `weekday`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
