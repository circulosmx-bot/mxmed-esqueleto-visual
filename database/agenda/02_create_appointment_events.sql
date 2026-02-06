-- 02_create_appointment_events.sql
CREATE TABLE IF NOT EXISTS appointment_events (
  event_id VARCHAR(64) PRIMARY KEY,
  appointment_id VARCHAR(64) DEFAULT NULL,
  event_type VARCHAR(64) NOT NULL,
  timestamp DATETIME NOT NULL,
  actor_role VARCHAR(32) NOT NULL,
  actor_id VARCHAR(64) DEFAULT NULL,
  channel_origin VARCHAR(64) NOT NULL,
  from_datetime DATETIME DEFAULT NULL,
  to_datetime DATETIME DEFAULT NULL,
  date DATE DEFAULT NULL,
  motivo_code VARCHAR(64) DEFAULT NULL,
  motivo_text TEXT DEFAULT NULL,
  notify_patient BOOLEAN DEFAULT FALSE,
  contact_method VARCHAR(32) DEFAULT 'whatsapp',
  metadata_json JSON DEFAULT NULL
);
