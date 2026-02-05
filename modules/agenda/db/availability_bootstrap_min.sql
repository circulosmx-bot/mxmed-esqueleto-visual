-- Fase II.1.0 — Bootstrap mínimo Availability (Capa A)
-- Tabla base para horarios por doctor / consultorio.

CREATE TABLE IF NOT EXISTS consultorio_schedule (
  schedule_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  doctor_id INT UNSIGNED NOT NULL,
  consultorio_id INT UNSIGNED NOT NULL,
  weekday TINYINT UNSIGNED NOT NULL COMMENT '1=Lunes ... 7=Domingo',
  start_at TIME NOT NULL,
  end_at TIME NOT NULL,
  PRIMARY KEY (schedule_id),
  KEY idx_doctor_consultorio_weekday (doctor_id, consultorio_id, weekday)
);

-- Datos de ejemplo (puedes borrarlos después)
INSERT INTO consultorio_schedule
  (doctor_id, consultorio_id, weekday, start_at, end_at)
VALUES
  (1, 1, 2, '09:00', '13:00'),
  (1, 1, 2, '15:00', '18:00');
