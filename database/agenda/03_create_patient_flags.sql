-- 03_create_patient_flags.sql
CREATE TABLE IF NOT EXISTS patient_flags (
  flag_id VARCHAR(64) PRIMARY KEY,
  patient_id VARCHAR(64) NOT NULL,
  flag_type VARCHAR(16) NOT NULL,
  reason_code VARCHAR(64) NOT NULL,
  source_appointment_id VARCHAR(64) DEFAULT NULL,
  created_at DATETIME NOT NULL,
  actor_role VARCHAR(32) NOT NULL,
  actor_id VARCHAR(64) DEFAULT NULL,
  channel_origin VARCHAR(64) NOT NULL,
  notes TEXT DEFAULT NULL,
  expires_at DATETIME DEFAULT NULL
);
