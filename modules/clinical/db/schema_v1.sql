-- =============================================================================
-- Módulo A v1 (Clinical): schema_v1.sql
-- Propósito:
--   Definir el esquema SQL mínimo v1 para Pacientes, Expedientes y Consentimientos.
-- Alcance v1:
--   Solo estructura de tablas/índices/constraints mínimos.
--   SIN implementación de endpoints, UI o lógica de aplicación.
-- FUTURO:
--   Catálogos extensos, auditoría avanzada, particionamiento, optimizaciones,
--   y reglas de negocio adicionales fuera del alcance de este archivo.
-- =============================================================================

-- Motor objetivo: MySQL 8+ (InnoDB, utf8mb4)
-- Nota de compatibilidad CHECK:
--   MySQL 8.0.16+ aplica CHECK constraints.
--   Versiones previas pueden parsearlos pero no hacer enforcement.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS clinical_patients (
  patient_id VARCHAR(64) NOT NULL,
  full_name VARCHAR(120) NOT NULL,
  birth_date DATE NULL,
  sex VARCHAR(16) NULL,
  phone VARCHAR(32) NULL,
  email VARCHAR(254) NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (patient_id),
  CONSTRAINT chk_clinical_patients_sex
    CHECK (sex IS NULL OR sex IN ('female', 'male', 'other', 'undisclosed')),
  CONSTRAINT chk_clinical_patients_status
    CHECK (status IN ('active', 'archived')),
  CONSTRAINT chk_clinical_patients_full_name_len
    CHECK (CHAR_LENGTH(full_name) BETWEEN 3 AND 120)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_clinical_patients_status ON clinical_patients (status);
CREATE INDEX idx_clinical_patients_email ON clinical_patients (email);
CREATE INDEX idx_clinical_patients_phone ON clinical_patients (phone);

CREATE TABLE IF NOT EXISTS clinical_record_entries (
  entry_id VARCHAR(64) NOT NULL,
  patient_id VARCHAR(64) NOT NULL,
  entry_date TIMESTAMP NOT NULL,
  note_type VARCHAR(24) NOT NULL,
  subjective TEXT NULL,
  objective TEXT NULL,
  assessment TEXT NULL,
  plan TEXT NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (entry_id),
  CONSTRAINT fk_clinical_record_entries_patient
    FOREIGN KEY (patient_id)
    REFERENCES clinical_patients (patient_id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
  CONSTRAINT chk_clinical_record_entries_note_type
    CHECK (note_type IN ('evolucion', 'ingreso', 'seguimiento', 'alta', 'otro')),
  CONSTRAINT chk_clinical_record_entries_status
    CHECK (status IN ('active', 'amended', 'archived'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_clinical_record_entries_patient_entry_date
  ON clinical_record_entries (patient_id, entry_date);

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
  CONSTRAINT fk_clinical_consents_patient
    FOREIGN KEY (patient_id)
    REFERENCES clinical_patients (patient_id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
  CONSTRAINT chk_clinical_consents_status
    CHECK (status IN ('active', 'revoked', 'void')),
  CONSTRAINT chk_clinical_consents_state_dates
    CHECK (
      (status = 'active' AND revoked_at IS NULL AND voided_at IS NULL) OR
      (status = 'revoked' AND revoked_at IS NOT NULL AND voided_at IS NULL) OR
      (status = 'void' AND voided_at IS NOT NULL AND revoked_at IS NULL)
    )
  -- Si CHECK no es enforced por versión de MySQL, esta regla debe validarse en aplicación.
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_clinical_consents_patient_granted_at
  ON clinical_consents (patient_id, granted_at);
CREATE INDEX idx_clinical_consents_status
  ON clinical_consents (status);
