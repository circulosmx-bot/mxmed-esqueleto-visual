-- Bootstrap mínimo de overrides de disponibilidad (Capa C)
-- Tabla esperada por configuración: agenda_availability_overrides
-- Ejemplo de prueba:
-- curl -s "http://127.0.0.1:8089/api/agenda/index.php/availability?doctor_id=1&consultorio_id=1&date=2026-02-05"

CREATE TABLE IF NOT EXISTS `agenda_availability_overrides` (
  `override_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `doctor_id` VARCHAR(64) NOT NULL,
  `consultorio_id` VARCHAR(64) NOT NULL,
  `date_ymd` DATE NOT NULL,
  `type` ENUM('open','close') NOT NULL,
  `start_at` DATETIME NOT NULL,
  `end_at` DATETIME NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`override_id`),
  KEY `idx_override_doctor_consultorio_date` (`doctor_id`,`consultorio_id`,`date_ymd`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Datos de ejemplo: cerrar toda la fecha y reabrir un rango
INSERT INTO `agenda_availability_overrides` (doctor_id, consultorio_id, date_ymd, type, start_at, end_at, is_active)
SELECT '1','1','2026-02-05','close','2026-02-05 00:00:00','2026-02-05 23:59:59',1
WHERE NOT EXISTS (
    SELECT 1 FROM agenda_availability_overrides
    WHERE doctor_id='1' AND consultorio_id='1' AND date_ymd='2026-02-05'
      AND type='close' AND start_at='2026-02-05 00:00:00' AND end_at='2026-02-05 23:59:59'
);

INSERT INTO `agenda_availability_overrides` (doctor_id, consultorio_id, date_ymd, type, start_at, end_at, is_active)
SELECT '1','1','2026-02-05','open','2026-02-05 10:00:00','2026-02-05 12:00:00',1
WHERE NOT EXISTS (
    SELECT 1 FROM agenda_availability_overrides
    WHERE doctor_id='1' AND consultorio_id='1' AND date_ymd='2026-02-05'
      AND type='open' AND start_at='2026-02-05 10:00:00' AND end_at='2026-02-05 12:00:00'
);
