-- LON03A: additive, patient-scoped longitudinal authority. No legacy backfill.
-- Re-running converges on these objects; readiness QA rejects incompatible drift.
CREATE TABLE IF NOT EXISTS clinical_patient_antecedent_facts (
  fact_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  doctor_id VARCHAR(64) NOT NULL,
  patient_id VARCHAR(64) NOT NULL,
  category VARCHAR(48) NOT NULL,
  content TEXT NOT NULL,
  state VARCHAR(16) NOT NULL DEFAULT 'CURRENT',
  provenance VARCHAR(48) NOT NULL,
  source_type VARCHAR(24) DEFAULT NULL,
  source_id VARCHAR(128) DEFAULT NULL,
  created_by VARCHAR(64) NOT NULL,
  updated_by VARCHAR(64) NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  row_version BIGINT UNSIGNED NOT NULL DEFAULT 1,
  KEY idx_antecedent_scope (doctor_id, patient_id, category, state),
  CONSTRAINT fk_antecedent_patient FOREIGN KEY (patient_id) REFERENCES patients_patients(patient_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT chk_antecedent_category CHECK (category IN ('PERSONAL_PATHOLOGICAL','PERSONAL_NON_PATHOLOGICAL','SURGICAL','FAMILY','HABITS','VACCINATION','GYNECOLOGICAL','OTHER')),
  CONSTRAINT chk_antecedent_state CHECK (state IN ('CURRENT','INACTIVE')),
  CONSTRAINT chk_antecedent_provenance CHECK (provenance IN ('EXPLICIT_LONGITUDINAL_ENTRY','ENCOUNTER_DERIVED_EXPLICIT_PROMOTION','PATIENT_REPORTED','LEGACY_IMPORTED_CONFIRMED','EXTERNAL_SOURCE')),
  CONSTRAINT chk_antecedent_version CHECK (row_version > 0),
  CONSTRAINT chk_antecedent_source CHECK ((source_type IS NULL AND source_id IS NULL) OR (source_type IS NOT NULL AND source_id IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clinical_patient_antecedent_reviews (
  review_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  doctor_id VARCHAR(64) NOT NULL,
  patient_id VARCHAR(64) NOT NULL,
  category VARCHAR(48) NOT NULL,
  review_state VARCHAR(24) NOT NULL,
  reviewed_by VARCHAR(64) NOT NULL,
  reviewed_at DATETIME NOT NULL,
  row_version BIGINT UNSIGNED NOT NULL DEFAULT 1,
  UNIQUE KEY uq_antecedent_review_scope (doctor_id, patient_id, category),
  CONSTRAINT fk_antecedent_review_patient FOREIGN KEY (patient_id) REFERENCES patients_patients(patient_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT chk_antecedent_review_category CHECK (category IN ('PERSONAL_PATHOLOGICAL','PERSONAL_NON_PATHOLOGICAL','SURGICAL','FAMILY','HABITS','VACCINATION','GYNECOLOGICAL','OTHER')),
  CONSTRAINT chk_antecedent_review_state CHECK (review_state IN ('REVIEWED_WITH_FACTS','CONFIRMED_NONE','NEEDS_REVIEW')),
  CONSTRAINT chk_antecedent_review_version CHECK (row_version > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clinical_patient_allergies (
  allergy_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  doctor_id VARCHAR(64) NOT NULL,
  patient_id VARCHAR(64) NOT NULL,
  substance VARCHAR(255) NOT NULL,
  reaction TEXT DEFAULT NULL,
  state VARCHAR(16) NOT NULL DEFAULT 'CURRENT',
  validation_state VARCHAR(24) NOT NULL,
  provenance VARCHAR(48) NOT NULL,
  source_type VARCHAR(24) DEFAULT NULL,
  source_id VARCHAR(128) DEFAULT NULL,
  reviewed_by VARCHAR(64) DEFAULT NULL,
  reviewed_at DATETIME DEFAULT NULL,
  created_by VARCHAR(64) NOT NULL,
  updated_by VARCHAR(64) NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  row_version BIGINT UNSIGNED NOT NULL DEFAULT 1,
  KEY idx_allergy_scope (doctor_id, patient_id, state),
  CONSTRAINT fk_allergy_patient FOREIGN KEY (patient_id) REFERENCES patients_patients(patient_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT chk_allergy_state CHECK (state IN ('CURRENT','INACTIVE')),
  CONSTRAINT chk_allergy_validation CHECK (validation_state IN ('REPORTED','CLINICIAN_REVIEWED')),
  CONSTRAINT chk_allergy_provenance CHECK (provenance IN ('EXPLICIT_LONGITUDINAL_ENTRY','ENCOUNTER_DERIVED_EXPLICIT_PROMOTION','PATIENT_REPORTED','LEGACY_IMPORTED_CONFIRMED','EXTERNAL_SOURCE')),
  CONSTRAINT chk_allergy_version CHECK (row_version > 0),
  CONSTRAINT chk_allergy_review_metadata CHECK ((reviewed_by IS NULL AND reviewed_at IS NULL) OR (reviewed_by IS NOT NULL AND reviewed_at IS NOT NULL)),
  CONSTRAINT chk_allergy_clinician_review CHECK (validation_state <> 'CLINICIAN_REVIEWED' OR reviewed_by IS NOT NULL),
  CONSTRAINT chk_allergy_source CHECK ((source_type IS NULL AND source_id IS NULL) OR (source_type IS NOT NULL AND source_id IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clinical_patient_allergy_reviews (
  review_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  doctor_id VARCHAR(64) NOT NULL,
  patient_id VARCHAR(64) NOT NULL,
  review_state VARCHAR(24) NOT NULL,
  reviewed_by VARCHAR(64) NOT NULL,
  reviewed_at DATETIME NOT NULL,
  row_version BIGINT UNSIGNED NOT NULL DEFAULT 1,
  UNIQUE KEY uq_allergy_review_scope (doctor_id, patient_id),
  CONSTRAINT fk_allergy_review_patient FOREIGN KEY (patient_id) REFERENCES patients_patients(patient_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT chk_allergy_review_state CHECK (review_state IN ('REVIEWED_WITH_ALLERGIES','CONFIRMED_NONE','NEEDS_REVIEW')),
  CONSTRAINT chk_allergy_review_version CHECK (row_version > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clinical_longitudinal_audit_events (
  event_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  doctor_id VARCHAR(64) NOT NULL,
  patient_id VARCHAR(64) NOT NULL,
  entity_type VARCHAR(32) NOT NULL,
  entity_id BIGINT UNSIGNED NOT NULL,
  entity_version BIGINT UNSIGNED NOT NULL,
  operation VARCHAR(32) NOT NULL,
  actor_user_id VARCHAR(64) NOT NULL,
  provenance VARCHAR(48) DEFAULT NULL,
  source_type VARCHAR(24) DEFAULT NULL,
  source_id VARCHAR(128) DEFAULT NULL,
  reason VARCHAR(255) DEFAULT NULL,
  before_json JSON DEFAULT NULL,
  after_json JSON NOT NULL,
  occurred_at DATETIME NOT NULL,
  UNIQUE KEY uq_longitudinal_entity_version (entity_type, entity_id, entity_version),
  KEY idx_longitudinal_history (doctor_id, patient_id, entity_type, entity_id, event_id),
  CONSTRAINT fk_longitudinal_audit_patient FOREIGN KEY (patient_id) REFERENCES patients_patients(patient_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT chk_longitudinal_entity CHECK (entity_type IN ('ANTECEDENT_FACT','ANTECEDENT_REVIEW','ALLERGY','ALLERGY_REVIEW'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clinical_longitudinal_idempotency (
  request_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  doctor_id VARCHAR(64) NOT NULL,
  patient_id VARCHAR(64) NOT NULL,
  operation VARCHAR(48) NOT NULL,
  idempotency_key VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  request_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  response_json JSON NOT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_longitudinal_command (doctor_id, patient_id, operation, idempotency_key),
  CONSTRAINT fk_longitudinal_idempotency_patient FOREIGN KEY (patient_id) REFERENCES patients_patients(patient_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit is append-only even when a privileged caller bypasses the service.
DELIMITER $$
DROP TRIGGER IF EXISTS trg_longitudinal_audit_no_update$$
CREATE TRIGGER trg_longitudinal_audit_no_update BEFORE UPDATE ON clinical_longitudinal_audit_events
FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='IMMUTABLE_LONGITUDINAL_AUDIT'$$
DROP TRIGGER IF EXISTS trg_longitudinal_audit_no_delete$$
CREATE TRIGGER trg_longitudinal_audit_no_delete BEFORE DELETE ON clinical_longitudinal_audit_events
FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='IMMUTABLE_LONGITUDINAL_AUDIT'$$
DROP TRIGGER IF EXISTS trg_antecedent_fact_no_delete$$
CREATE TRIGGER trg_antecedent_fact_no_delete BEFORE DELETE ON clinical_patient_antecedent_facts
FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='LONGITUDINAL_STATE_DELETE_FORBIDDEN'$$
DROP TRIGGER IF EXISTS trg_antecedent_review_no_delete$$
CREATE TRIGGER trg_antecedent_review_no_delete BEFORE DELETE ON clinical_patient_antecedent_reviews
FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='LONGITUDINAL_STATE_DELETE_FORBIDDEN'$$
DROP TRIGGER IF EXISTS trg_allergy_no_delete$$
CREATE TRIGGER trg_allergy_no_delete BEFORE DELETE ON clinical_patient_allergies
FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='LONGITUDINAL_STATE_DELETE_FORBIDDEN'$$
DROP TRIGGER IF EXISTS trg_allergy_review_no_delete$$
CREATE TRIGGER trg_allergy_review_no_delete BEFORE DELETE ON clinical_patient_allergy_reviews
FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='LONGITUDINAL_STATE_DELETE_FORBIDDEN'$$
DELIMITER ;

-- Existing objects must match the minimum authority manifest; IF NOT EXISTS alone
-- must not silently accept a same-named legacy/incomplete table.
DELIMITER $$
DROP PROCEDURE IF EXISTS mxmed_validate_lon03a_v1$$
CREATE PROCEDURE mxmed_validate_lon03a_v1()
BEGIN
  DECLARE n INT DEFAULT 0;
  SELECT COUNT(*) INTO n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND
    ((TABLE_NAME='clinical_patient_antecedent_facts' AND COLUMN_NAME IN ('fact_id','doctor_id','patient_id','category','content','state','provenance','row_version')) OR
     (TABLE_NAME='clinical_patient_antecedent_reviews' AND COLUMN_NAME IN ('review_id','doctor_id','patient_id','category','review_state','row_version')) OR
     (TABLE_NAME='clinical_patient_allergies' AND COLUMN_NAME IN ('allergy_id','doctor_id','patient_id','substance','reaction','validation_state','provenance','row_version')) OR
     (TABLE_NAME='clinical_patient_allergy_reviews' AND COLUMN_NAME IN ('review_id','doctor_id','patient_id','review_state','row_version')) OR
     (TABLE_NAME='clinical_longitudinal_audit_events' AND COLUMN_NAME IN ('event_id','doctor_id','patient_id','entity_type','entity_id','entity_version','before_json','after_json')) OR
     (TABLE_NAME='clinical_longitudinal_idempotency' AND COLUMN_NAME IN ('request_id','doctor_id','patient_id','operation','idempotency_key','request_hash','response_json')));
  IF n<>42 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='LON03A_MIGRATION_DRIFT_COLUMNS'; END IF;
  SELECT COUNT(*) INTO n FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE()
    AND TABLE_NAME IN ('clinical_patient_antecedent_facts','clinical_patient_antecedent_reviews','clinical_patient_allergies','clinical_patient_allergy_reviews','clinical_longitudinal_audit_events','clinical_longitudinal_idempotency')
    AND DELETE_RULE='RESTRICT' AND UPDATE_RULE='RESTRICT';
  IF n<>6 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='LON03A_MIGRATION_DRIFT_FOREIGN_KEYS'; END IF;
  SELECT COUNT(*) INTO n FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE()
    AND TRIGGER_NAME IN ('trg_longitudinal_audit_no_update','trg_longitudinal_audit_no_delete','trg_antecedent_fact_no_delete','trg_antecedent_review_no_delete','trg_allergy_no_delete','trg_allergy_review_no_delete');
  IF n<>6 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='LON03A_MIGRATION_DRIFT_TRIGGERS'; END IF;
  SELECT COUNT(*) INTO n FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE()
    AND TABLE_NAME IN ('clinical_patient_antecedent_facts','clinical_patient_antecedent_reviews','clinical_patient_allergies','clinical_patient_allergy_reviews') AND CONSTRAINT_TYPE='CHECK';
  IF n<12 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='LON03A_MIGRATION_DRIFT_CHECKS'; END IF;
END$$
CALL mxmed_validate_lon03a_v1()$$
DROP PROCEDURE mxmed_validate_lon03a_v1$$
DELIMITER ;
