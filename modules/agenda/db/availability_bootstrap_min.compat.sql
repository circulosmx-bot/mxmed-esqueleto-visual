-- Compatibilidad con schema real detectado (consultorio_schedule ya existe)
-- Inserta horarios mínimos para doctor_id=1, consultorio_id=1, weekday=2

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
