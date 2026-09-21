-- LON04A: additive patient-level problems. No legacy backfill or existing-table alteration.
CREATE TABLE IF NOT EXISTS clinical_patient_problems (
  problem_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  doctor_id VARCHAR(64) NOT NULL,
  patient_id VARCHAR(64) NOT NULL,
  label VARCHAR(500) NOT NULL,
  code_system VARCHAR(64) DEFAULT NULL,
  code_value VARCHAR(128) DEFAULT NULL,
  onset_date DATE DEFAULT NULL,
  status VARCHAR(16) NOT NULL,
  provenance VARCHAR(48) NOT NULL,
  source_encounter_id BIGINT UNSIGNED DEFAULT NULL,
  source_section_id BIGINT UNSIGNED DEFAULT NULL,
  resolution_at DATETIME DEFAULT NULL,
  resolution_by VARCHAR(64) DEFAULT NULL,
  created_by VARCHAR(64) NOT NULL,
  updated_by VARCHAR(64) NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  row_version BIGINT UNSIGNED NOT NULL DEFAULT 1,
  KEY idx_problem_scope (doctor_id,patient_id,status,problem_id),
  KEY idx_problem_source (source_encounter_id,source_section_id),
  CONSTRAINT fk_problem_patient FOREIGN KEY (patient_id) REFERENCES patients_patients(patient_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_problem_source_encounter FOREIGN KEY (source_encounter_id) REFERENCES clinical_encounters(encounter_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_problem_source_section FOREIGN KEY (source_section_id) REFERENCES clinical_encounter_sections(section_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT chk_problem_status CHECK (status IN ('ACTIVE','RESOLVED','INACTIVE')),
  CONSTRAINT chk_problem_provenance CHECK (provenance IN ('EXPLICIT_LONGITUDINAL_ENTRY','ENCOUNTER_DERIVED_EXPLICIT_PROMOTION','PATIENT_REPORTED')),
  CONSTRAINT chk_problem_version CHECK (row_version > 0),
  CONSTRAINT chk_problem_code_pair CHECK ((code_system IS NULL AND code_value IS NULL) OR (code_system IS NOT NULL AND code_value IS NOT NULL)),
  CONSTRAINT chk_problem_source_pair CHECK ((source_encounter_id IS NULL AND source_section_id IS NULL) OR (source_encounter_id IS NOT NULL AND source_section_id IS NOT NULL)),
  CONSTRAINT chk_problem_promotion_source CHECK ((provenance='ENCOUNTER_DERIVED_EXPLICIT_PROMOTION' AND source_encounter_id IS NOT NULL) OR (provenance<>'ENCOUNTER_DERIVED_EXPLICIT_PROMOTION' AND source_encounter_id IS NULL)),
  CONSTRAINT chk_problem_resolution CHECK ((status='RESOLVED' AND resolution_at IS NOT NULL AND resolution_by IS NOT NULL) OR (status<>'RESOLVED' AND resolution_at IS NULL AND resolution_by IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- LON03A's shared audit table has a closed entity CHECK. This additive audit
-- ledger preserves the same immutable/versioned contract without changing it.
CREATE TABLE IF NOT EXISTS clinical_patient_problem_audit_events (
  event_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  doctor_id VARCHAR(64) NOT NULL,
  patient_id VARCHAR(64) NOT NULL,
  problem_id BIGINT UNSIGNED NOT NULL,
  entity_version BIGINT UNSIGNED NOT NULL,
  operation VARCHAR(40) NOT NULL,
  actor_user_id VARCHAR(64) NOT NULL,
  source_encounter_id BIGINT UNSIGNED DEFAULT NULL,
  source_section_id BIGINT UNSIGNED DEFAULT NULL,
  reason VARCHAR(255) DEFAULT NULL,
  before_json JSON DEFAULT NULL,
  after_json JSON NOT NULL,
  occurred_at DATETIME NOT NULL,
  UNIQUE KEY uq_problem_audit_version (problem_id,entity_version),
  KEY idx_problem_audit_scope (doctor_id,patient_id,problem_id,event_id),
  CONSTRAINT fk_problem_audit_problem FOREIGN KEY (problem_id) REFERENCES clinical_patient_problems(problem_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT chk_problem_audit_operation CHECK (operation IN ('CREATE','UPDATE','RESOLVE','MARK_INACTIVE','ACTIVATE','REACTIVATE','EXPLICIT_PROMOTION_FROM_ENCOUNTER'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DELIMITER $$
DROP TRIGGER IF EXISTS trg_problem_no_delete$$
CREATE TRIGGER trg_problem_no_delete BEFORE DELETE ON clinical_patient_problems
FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='LONGITUDINAL_STATE_DELETE_FORBIDDEN'$$
DROP TRIGGER IF EXISTS trg_problem_audit_no_update$$
CREATE TRIGGER trg_problem_audit_no_update BEFORE UPDATE ON clinical_patient_problem_audit_events
FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='IMMUTABLE_LONGITUDINAL_AUDIT'$$
DROP TRIGGER IF EXISTS trg_problem_audit_no_delete$$
CREATE TRIGGER trg_problem_audit_no_delete BEFORE DELETE ON clinical_patient_problem_audit_events
FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='IMMUTABLE_LONGITUDINAL_AUDIT'$$
DELIMITER ;

DELIMITER $$
DROP PROCEDURE IF EXISTS mxmed_validate_lon04a_v1$$
CREATE PROCEDURE mxmed_validate_lon04a_v1()
BEGIN
  DECLARE n INT DEFAULT 0;
  SELECT COUNT(*) INTO n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND
    ((TABLE_NAME='clinical_patient_problems' AND COLUMN_NAME IN ('problem_id','doctor_id','patient_id','label','status','provenance','source_encounter_id','source_section_id','row_version','resolution_at','resolution_by')) OR
     (TABLE_NAME='clinical_patient_problem_audit_events' AND COLUMN_NAME IN ('event_id','doctor_id','patient_id','problem_id','entity_version','operation','before_json','after_json')));
  IF n<>19 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='LON04A_MIGRATION_DRIFT_COLUMNS'; END IF;
  SELECT COUNT(*) INTO n FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE()
    AND TABLE_NAME IN ('clinical_patient_problems','clinical_patient_problem_audit_events') AND DELETE_RULE='RESTRICT' AND UPDATE_RULE='RESTRICT';
  IF n<>4 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='LON04A_MIGRATION_DRIFT_FOREIGN_KEYS'; END IF;
  SELECT COUNT(*) INTO n FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND
    TRIGGER_NAME IN ('trg_problem_no_delete','trg_problem_audit_no_update','trg_problem_audit_no_delete');
  IF n<>3 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='LON04A_MIGRATION_DRIFT_TRIGGERS'; END IF;
  SELECT COUNT(*) INTO n FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE()
    AND TABLE_NAME IN ('clinical_patient_problems','clinical_patient_problem_audit_events') AND CONSTRAINT_TYPE='CHECK';
  IF n<7 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='LON04A_MIGRATION_DRIFT_CHECKS'; END IF;
END$$
CALL mxmed_validate_lon04a_v1()$$
DROP PROCEDURE mxmed_validate_lon04a_v1$$
DELIMITER ;
