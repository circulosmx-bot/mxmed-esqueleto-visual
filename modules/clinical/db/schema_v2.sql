-- =============================================================================
-- Módulo Clinical: schema_v2.sql
--
-- Propósito v2:
--   Alinear el esquema clínico a las fuentes canónicas de verdad del sistema.
--
-- Decisión canónica aplicada:
--   1) Paciente canónico = patients_patients (modules/patients)
--   2) clinical_documents sigue siendo canónico para documentos clínicos
--   3) Este schema cubre dominio clínico faltante/estructurado (no documental)
--
-- Regla clave v2:
--   No se crea ni duplica tabla de identidad de paciente (NO clinical_patients).
--   Todo recurso clínico referencia patients_patients.patient_id.
--
-- Alcance:
--   Solo DDL MySQL para tablas clínicas estructuradas mínimas.
--   SIN endpoints, SIN UI, SIN lógica de aplicación.
-- =============================================================================

-- Motor objetivo: MySQL 8+ (InnoDB, utf8mb4)
-- Compatibilidad CHECK:
--   MySQL 8.0.16+ aplica CHECK constraints.
--   Versiones previas pueden parsearlos sin enforcement.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- -----------------------------------------------------------------------------
-- Tabla clínica estructurada: entradas de expediente (no documental)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS clinical_record_entries (
  entry_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  patient_id VARCHAR(64) NOT NULL,
  entry_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  note_type VARCHAR(64) NOT NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'draft',
  payload_json JSON NULL,
  subjective TEXT NULL,
  objective TEXT NULL,
  assessment TEXT NULL,
  plan TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (entry_id),
  KEY idx_clinical_record_entries_patient (patient_id),
  KEY idx_clinical_record_entries_note_type (note_type),
  KEY idx_clinical_record_entries_status (status),
  KEY idx_clinical_record_entries_patient_type (patient_id, note_type),
  KEY idx_clinical_record_entries_patient_type_status (patient_id, note_type, status),
  KEY idx_clinical_record_entries_patient_entry_date (patient_id, entry_date),
  CONSTRAINT fk_clinical_record_entries_patient_v2
    FOREIGN KEY (patient_id)
    REFERENCES patients_patients (patient_id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
  CONSTRAINT chk_clinical_record_entries_status_v2
    CHECK (status IN ('draft', 'signed', 'archived'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Tabla clínica estructurada: consentimiento informado clínico (no administrativo)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS clinical_consents (
  consent_id VARCHAR(64) NOT NULL,
  patient_id VARCHAR(64) NOT NULL,
  consent_type VARCHAR(64) NOT NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'active',
  granted_at TIMESTAMP NOT NULL,
  revoked_at TIMESTAMP NULL,
  voided_at TIMESTAMP NULL,
  source VARCHAR(64) NULL,
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (consent_id),
  CONSTRAINT fk_clinical_consents_patient_v2
    FOREIGN KEY (patient_id)
    REFERENCES patients_patients (patient_id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
  CONSTRAINT chk_clinical_consents_status_v2
    CHECK (status IN ('active', 'revoked', 'void')),
  CONSTRAINT chk_clinical_consents_state_dates_v2
    CHECK (
      (status = 'active' AND revoked_at IS NULL AND voided_at IS NULL) OR
      (status = 'revoked' AND revoked_at IS NOT NULL AND voided_at IS NULL) OR
      (status = 'void' AND voided_at IS NOT NULL AND revoked_at IS NULL)
    )
  -- Si CHECK no es enforced por versión de MySQL, esta coherencia debe validarse en aplicación.
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_clinical_consents_patient_granted_at
  ON clinical_consents (patient_id, granted_at);

CREATE INDEX idx_clinical_consents_status
  ON clinical_consents (status);

-- -----------------------------------------------------------------------------
-- Casos clínicos v1: 1 caso activo por paciente (enforced por lógica de app)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS clinical_cases (
  case_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  patient_id VARCHAR(64) NOT NULL,
  title VARCHAR(180) NOT NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (case_id),
  KEY idx_clinical_cases_patient_status (patient_id, status),
  CONSTRAINT fk_clinical_cases_patient_v1
    FOREIGN KEY (patient_id)
    REFERENCES patients_patients (patient_id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
  CONSTRAINT chk_clinical_cases_status_v1
    CHECK (status IN ('active', 'inactive', 'closed'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clinical_case_items (
  case_id BIGINT UNSIGNED NOT NULL,
  item_type VARCHAR(24) NOT NULL,
  item_ref VARCHAR(190) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (case_id, item_type, item_ref),
  CONSTRAINT fk_clinical_case_items_case_v1
    FOREIGN KEY (case_id)
    REFERENCES clinical_cases (case_id)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Encounters clínicos v0: entidad central de atención (con o sin appointment_id)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS clinical_encounters (
  encounter_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  patient_id VARCHAR(64) NOT NULL,
  appointment_id VARCHAR(64) DEFAULT NULL,
  encounter_dt DATETIME NOT NULL,
  encounter_type VARCHAR(32) NOT NULL DEFAULT 'outpatient',
  status VARCHAR(16) NOT NULL DEFAULT 'completed',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  KEY idx_patient_dt (patient_id, encounter_dt),
  KEY idx_appt (appointment_id),
  CONSTRAINT fk_encounters_patient_v0
    FOREIGN KEY (patient_id)
    REFERENCES patients_patients (patient_id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
