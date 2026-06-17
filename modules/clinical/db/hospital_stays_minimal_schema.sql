-- =============================================================================
-- Módulo Clinical: hospital_stays_minimal_schema.sql
--
-- Propósito:
--   Tabla mínima para episodios de estancia hospitalaria.
--
-- Alcance:
--   Soporta current/start/close para api/hospital-stays.php sin modelar todavía
--   un dominio hospitalario amplio.
--
-- Notas:
--   - patient_id referencia la identidad canónica patients_patients.patient_id
--     por convención de aplicación.
--   - No se agrega FK aquí porque las tablas runtime clínicas no usan FKs de
--     forma uniforme y patient_id se conserva en VARCHAR(128) para compatibilidad
--     con clinical_documents.
-- =============================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS hospital_stays (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  hospital_stay_id VARCHAR(64) NOT NULL,
  patient_id VARCHAR(128) NOT NULL,
  service VARCHAR(160) NULL,
  room VARCHAR(80) NULL,
  bed VARCHAR(80) NULL,
  attending_user_id VARCHAR(128) NULL,
  admission_diagnosis TEXT NULL,
  admission_reason TEXT NULL,
  started_at DATETIME NOT NULL,
  closed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY uq_hospital_stays_stay_id (hospital_stay_id),
  KEY idx_hospital_stays_patient (patient_id),
  KEY idx_hospital_stays_patient_closed (patient_id, closed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
