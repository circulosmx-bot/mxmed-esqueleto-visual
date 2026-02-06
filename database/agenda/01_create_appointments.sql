-- 01_create_appointments.sql
CREATE TABLE IF NOT EXISTS appointments (
  appointment_id VARCHAR(64) PRIMARY KEY,
  doctor_id VARCHAR(64) NOT NULL,
  consultorio_id VARCHAR(64) NOT NULL,
  patient_id VARCHAR(64),
  start_at DATETIME NOT NULL,
  end_at DATETIME NOT NULL,
  modality VARCHAR(32) NOT NULL,
  status VARCHAR(32) NOT NULL,
  price_amount DECIMAL(10,2) DEFAULT 0,
  created_at DATETIME NOT NULL,
  channel_origin VARCHAR(64) NOT NULL,
  created_by_role VARCHAR(32) NOT NULL,
  created_by_id VARCHAR(64) DEFAULT NULL
);
