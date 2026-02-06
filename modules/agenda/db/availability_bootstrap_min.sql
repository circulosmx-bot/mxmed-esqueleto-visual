-- Fase II.1.0 — Bootstrap mínimo Availability (Capa A)
-- Tabla base para horarios por doctor / consultorio (usa start_time/end_time).

CREATE TABLE IF NOT EXISTS consultorio_schedule (
  schedule_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  doctor_id VARCHAR(64) NOT NULL,
  consultorio_id VARCHAR(64) NOT NULL,
  weekday TINYINT UNSIGNED NOT NULL COMMENT '1=Lunes ... 7=Domingo',
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (schedule_id),
  KEY idx_doctor_consultorio_weekday (doctor_id, consultorio_id, weekday)
);

INSERT INTO consultorio_schedule (doctor_id, consultorio_id, weekday, start_time, end_time, is_active)
SELECT '1', '1', 2, '09:00:00', '13:00:00', 1
WHERE NOT EXISTS (
  SELECT 1 FROM consultorio_schedule
  WHERE doctor_id='1' AND consultorio_id='1' AND weekday=2
    AND start_time='09:00:00' AND end_time='13:00:00'
);

INSERT INTO consultorio_schedule (doctor_id, consultorio_id, weekday, start_time, end_time, is_active)
SELECT '1', '1', 2, '15:00:00', '18:00:00', 1
WHERE NOT EXISTS (
  SELECT 1 FROM consultorio_schedule
  WHERE doctor_id='1' AND consultorio_id='1' AND weekday=2
    AND start_time='15:00:00' AND end_time='18:00:00'
);
