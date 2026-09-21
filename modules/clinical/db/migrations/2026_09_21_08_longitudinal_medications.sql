-- LON05A: additive medication episodes and explicit list reconciliation.
CREATE TABLE IF NOT EXISTS clinical_patient_medication_lists (
  doctor_id VARCHAR(64) NOT NULL,
  patient_id VARCHAR(64) NOT NULL,
  list_version BIGINT UNSIGNED NOT NULL DEFAULT 1,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (doctor_id,patient_id),
  CONSTRAINT fk_med_list_patient FOREIGN KEY (patient_id) REFERENCES patients_patients(patient_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT chk_med_list_version CHECK (list_version > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clinical_patient_medications (
  medication_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  doctor_id VARCHAR(64) NOT NULL,
  patient_id VARCHAR(64) NOT NULL,
  medication_name VARCHAR(500) NOT NULL,
  code_system VARCHAR(64) DEFAULT NULL,
  code_value VARCHAR(128) DEFAULT NULL,
  dose VARCHAR(128) DEFAULT NULL,
  dose_unit VARCHAR(64) DEFAULT NULL,
  route VARCHAR(128) DEFAULT NULL,
  frequency VARCHAR(255) DEFAULT NULL,
  started_at DATETIME DEFAULT NULL,
  ended_at DATETIME DEFAULT NULL,
  state VARCHAR(40) NOT NULL,
  provenance VARCHAR(48) NOT NULL,
  source_encounter_id BIGINT UNSIGNED DEFAULT NULL,
  source_document_id BIGINT UNSIGNED DEFAULT NULL,
  external_source VARCHAR(255) DEFAULT NULL,
  created_by VARCHAR(64) NOT NULL,
  updated_by VARCHAR(64) NOT NULL,
  ended_by VARCHAR(64) DEFAULT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  row_version BIGINT UNSIGNED NOT NULL DEFAULT 1,
  KEY idx_med_scope (doctor_id,patient_id,state,medication_id),
  KEY idx_med_source (source_encounter_id,source_document_id),
  CONSTRAINT fk_med_patient FOREIGN KEY (patient_id) REFERENCES patients_patients(patient_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_med_encounter FOREIGN KEY (source_encounter_id) REFERENCES clinical_encounters(encounter_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_med_document FOREIGN KEY (source_document_id) REFERENCES clinical_documents(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT chk_med_state CHECK (state IN ('ACTIVE_CONFIRMED','REPORTED_BY_PATIENT','PRESCRIBED_NOT_CONFIRMED_ACTIVE','DISCONTINUED','COMPLETED')),
  CONSTRAINT chk_med_provenance CHECK (provenance IN ('EXPLICIT_LONGITUDINAL_ENTRY','PATIENT_REPORTED','PRESCRIPTION_DERIVED_EXPLICIT_ENTRY','ENCOUNTER_DERIVED_EXPLICIT_ENTRY')),
  CONSTRAINT chk_med_version CHECK (row_version > 0),
  CONSTRAINT chk_med_code CHECK ((code_system IS NULL AND code_value IS NULL) OR (code_system IS NOT NULL AND code_value IS NOT NULL)),
  CONSTRAINT chk_med_terminal CHECK ((state IN ('DISCONTINUED','COMPLETED') AND ended_at IS NOT NULL AND ended_by IS NOT NULL) OR (state NOT IN ('DISCONTINUED','COMPLETED') AND ended_at IS NULL AND ended_by IS NULL)),
  CONSTRAINT chk_med_prescription_source CHECK ((provenance='PRESCRIPTION_DERIVED_EXPLICIT_ENTRY' AND source_document_id IS NOT NULL) OR (provenance<>'PRESCRIPTION_DERIVED_EXPLICIT_ENTRY' AND source_document_id IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clinical_medication_reconciliations (
  reconciliation_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  doctor_id VARCHAR(64) NOT NULL,
  patient_id VARCHAR(64) NOT NULL,
  actor_user_id VARCHAR(64) NOT NULL,
  starting_list_version BIGINT UNSIGNED NOT NULL,
  resulting_list_version BIGINT UNSIGNED NOT NULL,
  note VARCHAR(1000) DEFAULT NULL,
  created_at DATETIME NOT NULL,
  KEY idx_med_reconcile_scope (doctor_id,patient_id,reconciliation_id),
  CONSTRAINT fk_med_reconcile_patient FOREIGN KEY (patient_id) REFERENCES patients_patients(patient_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT chk_med_reconcile_versions CHECK (resulting_list_version=starting_list_version+1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clinical_medication_reconciliation_items (
  item_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  reconciliation_id BIGINT UNSIGNED NOT NULL,
  medication_id BIGINT UNSIGNED DEFAULT NULL,
  decision VARCHAR(32) NOT NULL,
  before_json JSON DEFAULT NULL,
  after_json JSON DEFAULT NULL,
  reason VARCHAR(255) DEFAULT NULL,
  KEY idx_med_reconcile_item (reconciliation_id,item_id),
  CONSTRAINT fk_med_reconcile_item_parent FOREIGN KEY (reconciliation_id) REFERENCES clinical_medication_reconciliations(reconciliation_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_med_reconcile_item_medication FOREIGN KEY (medication_id) REFERENCES clinical_patient_medications(medication_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT chk_med_reconcile_decision CHECK (decision IN ('ADD','CONFIRM_ACTIVE','UPDATE_REGIMEN','DISCONTINUE','COMPLETE','KEEP_UNCONFIRMED','UNRESOLVED'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clinical_patient_medication_audit_events (
  event_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  doctor_id VARCHAR(64) NOT NULL,
  patient_id VARCHAR(64) NOT NULL,
  medication_id BIGINT UNSIGNED NOT NULL,
  entity_version BIGINT UNSIGNED NOT NULL,
  operation VARCHAR(40) NOT NULL,
  actor_user_id VARCHAR(64) NOT NULL,
  reconciliation_id BIGINT UNSIGNED DEFAULT NULL,
  reason VARCHAR(255) DEFAULT NULL,
  before_json JSON DEFAULT NULL,
  after_json JSON NOT NULL,
  occurred_at DATETIME NOT NULL,
  UNIQUE KEY uq_med_audit_version (medication_id,entity_version),
  KEY idx_med_audit_scope (doctor_id,patient_id,medication_id,event_id),
  CONSTRAINT fk_med_audit_medication FOREIGN KEY (medication_id) REFERENCES clinical_patient_medications(medication_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_med_audit_reconciliation FOREIGN KEY (reconciliation_id) REFERENCES clinical_medication_reconciliations(reconciliation_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT chk_med_audit_operation CHECK (operation IN ('CREATE','PATIENT_REPORTED_CREATE','PRESCRIPTION_DERIVED_CREATE','CONFIRM_ACTIVE','UPDATE_REGIMEN','DISCONTINUE','COMPLETE','RECONCILIATION_DECISION'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DELIMITER $$
DROP TRIGGER IF EXISTS trg_med_list_no_delete$$
CREATE TRIGGER trg_med_list_no_delete BEFORE DELETE ON clinical_patient_medication_lists
FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='LONGITUDINAL_LIST_DELETE_FORBIDDEN'$$
DROP TRIGGER IF EXISTS trg_med_no_delete$$
CREATE TRIGGER trg_med_no_delete BEFORE DELETE ON clinical_patient_medications
FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='LONGITUDINAL_STATE_DELETE_FORBIDDEN'$$
DROP TRIGGER IF EXISTS trg_med_terminal_no_update$$
CREATE TRIGGER trg_med_terminal_no_update BEFORE UPDATE ON clinical_patient_medications
FOR EACH ROW
BEGIN
  IF OLD.state IN ('DISCONTINUED','COMPLETED') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='TERMINAL_MEDICATION_EPISODE_IMMUTABLE';
  END IF;
END$$
DROP TRIGGER IF EXISTS trg_med_audit_no_update$$
CREATE TRIGGER trg_med_audit_no_update BEFORE UPDATE ON clinical_patient_medication_audit_events
FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='IMMUTABLE_LONGITUDINAL_AUDIT'$$
DROP TRIGGER IF EXISTS trg_med_audit_no_delete$$
CREATE TRIGGER trg_med_audit_no_delete BEFORE DELETE ON clinical_patient_medication_audit_events
FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='IMMUTABLE_LONGITUDINAL_AUDIT'$$
DROP TRIGGER IF EXISTS trg_med_reconcile_no_update$$
CREATE TRIGGER trg_med_reconcile_no_update BEFORE UPDATE ON clinical_medication_reconciliations
FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='IMMUTABLE_RECONCILIATION'$$
DROP TRIGGER IF EXISTS trg_med_reconcile_no_delete$$
CREATE TRIGGER trg_med_reconcile_no_delete BEFORE DELETE ON clinical_medication_reconciliations
FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='IMMUTABLE_RECONCILIATION'$$
DROP TRIGGER IF EXISTS trg_med_reconcile_item_no_update$$
CREATE TRIGGER trg_med_reconcile_item_no_update BEFORE UPDATE ON clinical_medication_reconciliation_items
FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='IMMUTABLE_RECONCILIATION'$$
DROP TRIGGER IF EXISTS trg_med_reconcile_item_no_delete$$
CREATE TRIGGER trg_med_reconcile_item_no_delete BEFORE DELETE ON clinical_medication_reconciliation_items
FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='IMMUTABLE_RECONCILIATION'$$
DELIMITER ;

DELIMITER $$
DROP PROCEDURE IF EXISTS mxmed_validate_lon05a_v1$$
CREATE PROCEDURE mxmed_validate_lon05a_v1()
BEGIN
  DECLARE n INT DEFAULT 0;
  SELECT COUNT(*) INTO n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND
    ((TABLE_NAME='clinical_patient_medication_lists' AND COLUMN_NAME IN ('doctor_id','patient_id','list_version')) OR
     (TABLE_NAME='clinical_patient_medications' AND COLUMN_NAME IN ('medication_id','doctor_id','patient_id','medication_name','state','provenance','source_document_id','row_version')) OR
     (TABLE_NAME='clinical_medication_reconciliations' AND COLUMN_NAME IN ('reconciliation_id','starting_list_version','resulting_list_version')) OR
     (TABLE_NAME='clinical_medication_reconciliation_items' AND COLUMN_NAME IN ('item_id','decision','medication_id')) OR
     (TABLE_NAME='clinical_patient_medication_audit_events' AND COLUMN_NAME IN ('event_id','medication_id','operation','before_json','after_json')));
  IF n<>22 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='LON05A_MIGRATION_DRIFT_COLUMNS'; END IF;
  SELECT COUNT(*) INTO n FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE()
    AND TABLE_NAME IN ('clinical_patient_medication_lists','clinical_patient_medications','clinical_medication_reconciliations','clinical_medication_reconciliation_items','clinical_patient_medication_audit_events') AND CONSTRAINT_TYPE='PRIMARY KEY';
  IF n<>5 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='LON05A_MIGRATION_DRIFT_PRIMARY_KEYS'; END IF;
  SELECT COUNT(*) INTO n FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE()
    AND TABLE_NAME IN ('clinical_patient_medication_lists','clinical_patient_medications','clinical_medication_reconciliations','clinical_medication_reconciliation_items','clinical_patient_medication_audit_events') AND DELETE_RULE='RESTRICT' AND UPDATE_RULE='RESTRICT';
  IF n<>9 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='LON05A_MIGRATION_DRIFT_FOREIGN_KEYS'; END IF;
  SELECT COUNT(*) INTO n FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE()
    AND TABLE_NAME IN ('clinical_patient_medication_lists','clinical_patient_medications','clinical_medication_reconciliations','clinical_medication_reconciliation_items','clinical_patient_medication_audit_events') AND CONSTRAINT_TYPE='CHECK';
  IF n<10 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='LON05A_MIGRATION_DRIFT_CHECKS'; END IF;
  SELECT COUNT(*) INTO n FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME IN
    ('trg_med_list_no_delete','trg_med_no_delete','trg_med_terminal_no_update','trg_med_audit_no_update','trg_med_audit_no_delete','trg_med_reconcile_no_update','trg_med_reconcile_no_delete','trg_med_reconcile_item_no_update','trg_med_reconcile_item_no_delete');
  IF n<>9 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='LON05A_MIGRATION_DRIFT_TRIGGERS'; END IF;
END$$
CALL mxmed_validate_lon05a_v1()$$
DROP PROCEDURE mxmed_validate_lon05a_v1$$
DELIMITER ;
