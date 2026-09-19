-- CLIN-REFORM-PHASE2-IMPL01A-R2 — review artifact only. DO NOT EXECUTE in this chapter.
-- Guarded, additive lifecycle migration. It never infers doctor ownership or rewrites status.

DELIMITER $$
DROP PROCEDURE IF EXISTS mxmed_migrate_encounter_lifecycle_v1$$
CREATE PROCEDURE mxmed_migrate_encounter_lifecycle_v1()
BEGIN
  DECLARE object_count INT DEFAULT 0;
  DECLARE object_shape TEXT DEFAULT NULL;

  SELECT COUNT(*) INTO object_count FROM information_schema.TABLES
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_encounters';
  IF object_count<>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: clinical_encounters missing'; END IF;

  IF EXISTS (SELECT 1 FROM clinical_encounters WHERE BINARY status NOT IN (BINARY 'open',BINARY 'closed',BINARY 'voided')) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='LEGACY_STATE_REQUIRES_ADJUDICATION';
  END IF;
  IF EXISTS (SELECT 1 FROM clinical_encounters WHERE doctor_id IS NOT NULL AND BINARY status=BINARY 'open'
             GROUP BY doctor_id,patient_id HAVING COUNT(*)>1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='DUPLICATE_ATTRIBUTED_OPEN_REQUIRES_ADJUDICATION';
  END IF;
  IF EXISTS (SELECT 1 FROM clinical_encounters WHERE doctor_id IS NOT NULL AND (
      (BINARY status=BINARY 'open' AND (closed_at IS NOT NULL OR closed_by_user_id IS NOT NULL))
      OR (BINARY status=BINARY 'closed' AND (closed_at IS NULL OR closed_by_user_id IS NULL)))) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='ATTRIBUTED_LIFECYCLE_CONTRADICTION';
  END IF;

  SELECT COUNT(*) INTO object_count FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_encounters' AND COLUMN_NAME='voided_at';
  IF object_count=0 THEN ALTER TABLE clinical_encounters ADD COLUMN voided_at DATETIME NULL AFTER closed_by_user_id;
  ELSE
    SELECT CONCAT(DATA_TYPE,'|',IS_NULLABLE) INTO object_shape FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_encounters' AND COLUMN_NAME='voided_at';
    IF object_count<>1 OR object_shape<>'datetime|YES' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: voided_at'; END IF;
  END IF;
  SELECT COUNT(*) INTO object_count FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_encounters' AND COLUMN_NAME='voided_by_user_id';
  IF object_count=0 THEN ALTER TABLE clinical_encounters ADD COLUMN voided_by_user_id VARCHAR(64) NULL AFTER voided_at;
  ELSE
    SELECT CONCAT(DATA_TYPE,'|',CHARACTER_MAXIMUM_LENGTH,'|',IS_NULLABLE) INTO object_shape FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_encounters' AND COLUMN_NAME='voided_by_user_id';
    IF object_count<>1 OR object_shape<>'varchar|64|YES' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: voided_by_user_id'; END IF;
  END IF;
  SELECT COUNT(*) INTO object_count FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_encounters' AND COLUMN_NAME='void_reason';
  IF object_count=0 THEN ALTER TABLE clinical_encounters ADD COLUMN void_reason VARCHAR(1000) NULL AFTER voided_by_user_id;
  ELSE
    SELECT CONCAT(DATA_TYPE,'|',CHARACTER_MAXIMUM_LENGTH,'|',IS_NULLABLE) INTO object_shape FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_encounters' AND COLUMN_NAME='void_reason';
    IF object_count<>1 OR object_shape<>'varchar|1000|YES' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: void_reason'; END IF;
  END IF;

  SELECT COUNT(*),MAX(CONCAT(DATA_TYPE,'|',GENERATION_EXPRESSION)) INTO object_count,object_shape
    FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_encounters' AND COLUMN_NAME='open_guard';
  IF object_count=0 THEN
    ALTER TABLE clinical_encounters ADD COLUMN open_guard TINYINT GENERATED ALWAYS AS
      (CASE WHEN BINARY status=BINARY 'open' THEN 1 ELSE NULL END) STORED;
  ELSEIF object_count<>1 OR SUBSTRING_INDEX(object_shape,'|',1)<>'tinyint'
      OR LOWER(SUBSTRING_INDEX(object_shape,'|',-1)) LIKE '%concat%'
      OR LOWER(SUBSTRING_INDEX(object_shape,'|',-1)) NOT LIKE '%status%'
      OR LOWER(SUBSTRING_INDEX(object_shape,'|',-1)) NOT LIKE '%open%' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: incompatible open_guard';
  END IF;

  SELECT COUNT(*),MAX(cols) INTO object_count,object_shape FROM (
    SELECT INDEX_NAME,CONCAT(NON_UNIQUE,'|',GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX)) cols
    FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_encounters'
      AND INDEX_NAME='uq_clinical_encounter_one_open' GROUP BY INDEX_NAME,NON_UNIQUE
  ) x;
  IF object_count=0 THEN ALTER TABLE clinical_encounters ADD UNIQUE KEY uq_clinical_encounter_one_open (doctor_id,patient_id,open_guard);
  ELSEIF object_count<>1 OR object_shape<>'0|doctor_id,patient_id,open_guard' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: uq_clinical_encounter_one_open'; END IF;

  SELECT COUNT(*) INTO object_count FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE()
    AND TABLE_NAME='clinical_encounters' AND CONSTRAINT_NAME='chk_clinical_encounter_lifecycle_v1' AND CONSTRAINT_TYPE='CHECK';
  IF object_count=0 THEN
    ALTER TABLE clinical_encounters ADD CONSTRAINT chk_clinical_encounter_lifecycle_v1 CHECK (
      doctor_id IS NULL OR
      (BINARY status=BINARY 'open' AND closed_at IS NULL AND closed_by_user_id IS NULL AND voided_at IS NULL AND voided_by_user_id IS NULL AND void_reason IS NULL) OR
      (BINARY status=BINARY 'closed' AND closed_at IS NOT NULL AND closed_by_user_id IS NOT NULL AND voided_at IS NULL AND voided_by_user_id IS NULL AND void_reason IS NULL) OR
      (BINARY status=BINARY 'voided' AND voided_at IS NOT NULL AND voided_by_user_id IS NOT NULL AND CHAR_LENGTH(TRIM(void_reason))>0 AND closed_at IS NULL AND closed_by_user_id IS NULL)
    );
  ELSE
    SELECT LOWER(cc.CHECK_CLAUSE) INTO object_shape FROM information_schema.CHECK_CONSTRAINTS cc WHERE cc.CONSTRAINT_SCHEMA=DATABASE() AND cc.CONSTRAINT_NAME='chk_clinical_encounter_lifecycle_v1';
    IF object_count<>1 OR object_shape NOT LIKE '%doctor_id%' OR object_shape NOT LIKE '%void_reason%' OR object_shape NOT LIKE '%closed_at%' OR object_shape NOT LIKE '%voided_at%'
    THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: lifecycle check'; END IF;
  END IF;

  SELECT COUNT(*),MAX(CONCAT(EVENT_OBJECT_TABLE,'|',ACTION_TIMING,'|',EVENT_MANIPULATION,'|',ACTION_STATEMENT))
    INTO object_count,object_shape FROM information_schema.TRIGGERS
    WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME='trg_clinical_encounters_v1_before_insert';
  IF object_count<>0 AND (object_count<>1 OR object_shape NOT LIKE 'clinical_encounters|BEFORE|INSERT|%'
      OR LOWER(object_shape) NOT LIKE '%start_must_create_open%' OR LOWER(object_shape) NOT LIKE '%doctor_id_required%')
  THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: insert trigger'; END IF;

  SELECT COUNT(*),MAX(CONCAT(EVENT_OBJECT_TABLE,'|',ACTION_TIMING,'|',EVENT_MANIPULATION,'|',ACTION_STATEMENT))
    INTO object_count,object_shape FROM information_schema.TRIGGERS
    WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME='trg_clinical_encounters_v1_before_update';
  IF object_count<>0 AND (object_count<>1 OR object_shape NOT LIKE 'clinical_encounters|BEFORE|UPDATE|%'
      OR LOWER(object_shape) NOT LIKE '%encounter_ownership_immutable%' OR LOWER(object_shape) NOT LIKE '%encounter_transition_forbidden%'
      OR LOWER(object_shape) NOT LIKE '%first_close_immutable%' OR LOWER(object_shape) NOT LIKE '%first_void_immutable%'
  ) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: update trigger'; END IF;
END$$
CALL mxmed_migrate_encounter_lifecycle_v1()$$
DROP PROCEDURE mxmed_migrate_encounter_lifecycle_v1$$

CREATE TRIGGER IF NOT EXISTS trg_clinical_encounters_v1_before_insert
BEFORE INSERT ON clinical_encounters
FOR EACH ROW
BEGIN
  IF BINARY NEW.status<>BINARY 'open' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='START_MUST_CREATE_OPEN';
  END IF;
  IF NEW.doctor_id IS NULL OR TRIM(NEW.doctor_id)='' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='DOCTOR_ID_REQUIRED';
  END IF;
END$$

CREATE TRIGGER IF NOT EXISTS trg_clinical_encounters_v1_before_update
BEFORE UPDATE ON clinical_encounters
FOR EACH ROW
BEGIN
  IF NOT (NEW.patient_id<=>OLD.patient_id)
      OR NOT (NEW.doctor_id<=>OLD.doctor_id)
      OR NOT (NEW.appointment_id<=>OLD.appointment_id)
      OR NOT (NEW.opened_by_user_id<=>OLD.opened_by_user_id)
      OR NOT (NEW.encounter_dt<=>OLD.encounter_dt)
      OR NOT (NEW.encounter_type<=>OLD.encounter_type) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='ENCOUNTER_OWNERSHIP_IMMUTABLE';
  END IF;
  IF BINARY NEW.status<>BINARY OLD.status
      AND NOT (BINARY OLD.status=BINARY 'open' AND BINARY NEW.status IN (BINARY 'closed',BINARY 'voided')) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='ENCOUNTER_TRANSITION_FORBIDDEN';
  END IF;
  IF BINARY OLD.status=BINARY 'closed'
      AND (NOT (NEW.closed_at<=>OLD.closed_at)
        OR NOT (NEW.closed_by_user_id<=>OLD.closed_by_user_id)
        OR NOT (NEW.auto_note_uuid_final<=>OLD.auto_note_uuid_final)) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FIRST_CLOSE_IMMUTABLE';
  END IF;
  IF BINARY OLD.status=BINARY 'voided'
      AND (NOT (NEW.voided_at<=>OLD.voided_at)
        OR NOT (NEW.voided_by_user_id<=>OLD.voided_by_user_id)
        OR NOT (NEW.void_reason<=>OLD.void_reason)) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FIRST_VOID_IMMUTABLE';
  END IF;
END$$
DELIMITER ;

-- Legacy doctor_id=NULL rows intentionally remain UNATTRIBUTED. Evidence-based
-- ownership reconciliation requires a separately authorized migration that
-- safely manages this trigger; normal runtime UPDATE may never assign ownership.
