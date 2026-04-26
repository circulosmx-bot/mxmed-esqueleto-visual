-- Corrección seed QA semana futura 2026-04-27 .. 2026-05-02
-- Scope exacto: appointment_id qa_aprmay26_d1c1_*
-- No toca semana 2026-04-20 .. 2026-04-25

START TRANSACTION;

-- 1) Corregir estado incoherente en citas futuras seed.
UPDATE agenda_appointments
SET status = 'confirmed'
WHERE appointment_id LIKE 'qa_aprmay26_d1c1_%'
  AND start_at >= '2026-04-27 00:00:00'
  AND start_at < '2026-05-03 00:00:00'
  AND status IN ('no_show', 'finished', 'finalizada');

-- 2) Eliminar eventos no_show en esa semana seed futura.
DELETE e
FROM agenda_appointment_events e
INNER JOIN agenda_appointments a
  ON a.appointment_id = e.appointment_id
WHERE a.appointment_id LIKE 'qa_aprmay26_d1c1_%'
  AND a.start_at >= '2026-04-27 00:00:00'
  AND a.start_at < '2026-05-03 00:00:00'
  AND e.event_type = 'appointment_no_show';

-- 3) Eliminar flags black/no_show asociados a esas citas seed futuras.
DELETE f
FROM agenda_patient_flags f
INNER JOIN agenda_appointments a
  ON a.appointment_id = f.source_appointment_id
WHERE a.appointment_id LIKE 'qa_aprmay26_d1c1_%'
  AND a.start_at >= '2026-04-27 00:00:00'
  AND a.start_at < '2026-05-03 00:00:00'
  AND f.flag_type = 'black'
  AND f.reason_code = 'no_show';

-- 4) Eliminar incidentes no_show asociados a esas citas seed futuras.
DELETE i
FROM agenda_patient_incidents i
INNER JOIN agenda_appointments a
  ON a.appointment_id = i.appointment_id
WHERE a.appointment_id LIKE 'qa_aprmay26_d1c1_%'
  AND a.start_at >= '2026-04-27 00:00:00'
  AND a.start_at < '2026-05-03 00:00:00'
  AND i.incident_type = 'no_show';

COMMIT;
