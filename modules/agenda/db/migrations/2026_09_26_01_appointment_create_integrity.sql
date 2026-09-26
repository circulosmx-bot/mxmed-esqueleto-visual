-- PLAN02A: run once with application writes paused. MySQL 8 atomic ALTER.
-- Preflight duplicate occupied starts (must return no rows; never auto-delete):
-- SELECT doctor_id, consultorio_id, start_at, COUNT(*) FROM agenda_appointments
-- WHERE status IN ('tentative','pending_otp','confirmed','pending','scheduled')
-- GROUP BY doctor_id, consultorio_id, start_at HAVING COUNT(*) > 1;
-- Existing IDs, lifecycle states, events and patient links are unchanged.
-- Duplicate keys cause the entire ALTER to fail; reconcile explicitly first.
ALTER TABLE agenda_appointments
  ADD COLUMN create_request_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  ADD COLUMN create_request_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  ADD COLUMN create_result_json JSON DEFAULT NULL,
  ADD UNIQUE KEY uniq_appointment_create_request (create_request_key),
  DROP INDEX uniq_active_slot,
  MODIFY COLUMN active_slot_key VARCHAR(255) GENERATED ALWAYS AS (
    CASE WHEN status IN ('tentative','pending_otp','confirmed','pending','scheduled')
    THEN CONCAT(doctor_id,'|',consultorio_id,'|',DATE_FORMAT(start_at,'%Y-%m-%d %H:%i:%s'))
    ELSE NULL END
  ) STORED,
  ADD UNIQUE KEY uniq_active_slot (active_slot_key);
