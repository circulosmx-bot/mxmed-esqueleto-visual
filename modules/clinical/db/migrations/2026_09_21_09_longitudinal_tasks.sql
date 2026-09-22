-- LON06A: explicit patient-level clinical tasks. No legacy, encounter or Agenda backfill.
CREATE TABLE IF NOT EXISTS clinical_patient_tasks (
  task_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  doctor_id VARCHAR(64) NOT NULL,
  patient_id VARCHAR(64) NOT NULL,
  task_type VARCHAR(32) NOT NULL,
  title VARCHAR(500) NOT NULL,
  responsible_user_id VARCHAR(64) DEFAULT NULL,
  due_at DATETIME DEFAULT NULL,
  state VARCHAR(16) NOT NULL DEFAULT 'OPEN',
  provenance VARCHAR(48) NOT NULL,
  source_encounter_id BIGINT UNSIGNED DEFAULT NULL,
  appointment_id VARCHAR(64) DEFAULT NULL,
  ended_at DATETIME DEFAULT NULL,
  ended_by VARCHAR(64) DEFAULT NULL,
  end_reason VARCHAR(255) DEFAULT NULL,
  created_by VARCHAR(64) NOT NULL,
  updated_by VARCHAR(64) NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  row_version BIGINT UNSIGNED NOT NULL DEFAULT 1,
  KEY idx_task_scope (doctor_id,patient_id,state,task_type,due_at,task_id),
  KEY idx_task_source (source_encounter_id),
  KEY idx_task_appointment (appointment_id),
  CONSTRAINT fk_task_patient FOREIGN KEY (patient_id) REFERENCES patients_patients(patient_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_task_encounter FOREIGN KEY (source_encounter_id) REFERENCES clinical_encounters(encounter_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_task_appointment FOREIGN KEY (appointment_id) REFERENCES agenda_appointments(appointment_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT chk_task_type CHECK (task_type IN ('CLINICAL_ACTION','FOLLOW_UP')),
  CONSTRAINT chk_task_state CHECK (state IN ('OPEN','RESOLVED','CANCELED')),
  CONSTRAINT chk_task_provenance CHECK (provenance IN ('EXPLICIT_LONGITUDINAL_ENTRY','ENCOUNTER_DERIVED_EXPLICIT_ENTRY')),
  CONSTRAINT chk_task_source CHECK ((provenance='EXPLICIT_LONGITUDINAL_ENTRY' AND source_encounter_id IS NULL) OR (provenance='ENCOUNTER_DERIVED_EXPLICIT_ENTRY' AND source_encounter_id IS NOT NULL)),
  CONSTRAINT chk_task_version CHECK (row_version>0),
  CONSTRAINT chk_task_terminal CHECK ((state='OPEN' AND ended_at IS NULL AND ended_by IS NULL AND end_reason IS NULL) OR
    (state IN ('RESOLVED','CANCELED') AND ended_at IS NOT NULL AND ended_by IS NOT NULL AND end_reason IS NOT NULL AND CHAR_LENGTH(TRIM(end_reason))>0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clinical_patient_task_audit_events (
  event_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  doctor_id VARCHAR(64) NOT NULL,
  patient_id VARCHAR(64) NOT NULL,
  task_id BIGINT UNSIGNED NOT NULL,
  entity_version BIGINT UNSIGNED NOT NULL,
  operation VARCHAR(16) NOT NULL,
  actor_user_id VARCHAR(64) NOT NULL,
  reason VARCHAR(255) DEFAULT NULL,
  before_json JSON DEFAULT NULL,
  after_json JSON NOT NULL,
  occurred_at DATETIME NOT NULL,
  UNIQUE KEY uq_task_audit_version (task_id,entity_version),
  KEY idx_task_audit_scope (doctor_id,patient_id,task_id,event_id),
  CONSTRAINT fk_task_audit_task FOREIGN KEY (task_id) REFERENCES clinical_patient_tasks(task_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT chk_task_audit_operation CHECK (operation IN ('CREATE','UPDATE','RESOLVE','CANCEL'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DELIMITER $$
DROP TRIGGER IF EXISTS trg_task_no_delete$$
CREATE TRIGGER trg_task_no_delete BEFORE DELETE ON clinical_patient_tasks
FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='LONGITUDINAL_TASK_DELETE_FORBIDDEN'$$
DROP TRIGGER IF EXISTS trg_task_terminal_no_update$$
CREATE TRIGGER trg_task_terminal_no_update BEFORE UPDATE ON clinical_patient_tasks
FOR EACH ROW
BEGIN
  IF OLD.state IN ('RESOLVED','CANCELED') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='TERMINAL_CLINICAL_TASK_IMMUTABLE';
  END IF;
END$$
DROP TRIGGER IF EXISTS trg_task_audit_no_update$$
CREATE TRIGGER trg_task_audit_no_update BEFORE UPDATE ON clinical_patient_task_audit_events
FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='IMMUTABLE_LONGITUDINAL_TASK_AUDIT'$$
DROP TRIGGER IF EXISTS trg_task_audit_no_delete$$
CREATE TRIGGER trg_task_audit_no_delete BEFORE DELETE ON clinical_patient_task_audit_events
FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='IMMUTABLE_LONGITUDINAL_TASK_AUDIT'$$
DELIMITER ;

DELIMITER $$
DROP PROCEDURE IF EXISTS mxmed_validate_lon06a_v1$$
CREATE PROCEDURE mxmed_validate_lon06a_v1()
BEGIN
  DECLARE n INT DEFAULT 0;
  SELECT COUNT(*) INTO n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND
    ((TABLE_NAME='clinical_patient_tasks' AND COLUMN_NAME IN ('task_id','doctor_id','patient_id','task_type','title','responsible_user_id','due_at','state','provenance','source_encounter_id','appointment_id','row_version')) OR
     (TABLE_NAME='clinical_patient_task_audit_events' AND COLUMN_NAME IN ('event_id','doctor_id','patient_id','task_id','entity_version','operation','before_json','after_json')));
  IF n<>20 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='LON06A_MIGRATION_DRIFT_COLUMNS'; END IF;
  SELECT COUNT(*) INTO n FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE()
    AND TABLE_NAME IN ('clinical_patient_tasks','clinical_patient_task_audit_events') AND CONSTRAINT_TYPE='PRIMARY KEY';
  IF n<>2 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='LON06A_MIGRATION_DRIFT_PRIMARY_KEYS'; END IF;
  SELECT COUNT(*) INTO n FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE()
    AND TABLE_NAME IN ('clinical_patient_tasks','clinical_patient_task_audit_events') AND DELETE_RULE='RESTRICT' AND UPDATE_RULE='RESTRICT';
  IF n<>4 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='LON06A_MIGRATION_DRIFT_FOREIGN_KEYS'; END IF;
  SELECT COUNT(*) INTO n FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE()
    AND TABLE_NAME IN ('clinical_patient_tasks','clinical_patient_task_audit_events') AND CONSTRAINT_TYPE='CHECK';
  IF n<>7 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='LON06A_MIGRATION_DRIFT_CHECKS'; END IF;
  SELECT COUNT(*) INTO n FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME IN
    ('trg_task_no_delete','trg_task_terminal_no_update','trg_task_audit_no_update','trg_task_audit_no_delete');
  IF n<>4 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='LON06A_MIGRATION_DRIFT_TRIGGERS'; END IF;
  SELECT COUNT(*) INTO n FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='clinical_patient_task_audit_events' AND INDEX_NAME='uq_task_audit_version';
  IF n<>2 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='LON06A_MIGRATION_DRIFT_AUDIT_VERSION'; END IF;
END$$
CALL mxmed_validate_lon06a_v1()$$
DROP PROCEDURE mxmed_validate_lon06a_v1$$
DELIMITER ;
