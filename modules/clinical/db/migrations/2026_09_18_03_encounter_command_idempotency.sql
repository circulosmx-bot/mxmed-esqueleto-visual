-- CLIN-REFORM-PHASE2-IMPL01A-R2 — review artifact only. DO NOT EXECUTE in this chapter.
-- Guarded durable request ledgers; hashes and typed references only, never clinical bytes.

DELIMITER $$
DROP PROCEDURE IF EXISTS mxmed_migrate_encounter_idempotency_v1$$
CREATE PROCEDURE mxmed_migrate_encounter_idempotency_v1()
BEGIN
  DECLARE n INT DEFAULT 0;
  DECLARE shape_value TEXT DEFAULT NULL;

  SELECT COUNT(*) INTO n FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_encounter_start_requests';
  IF n=0 THEN
    SET @ddl='CREATE TABLE clinical_encounter_start_requests (request_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,doctor_id VARCHAR(64) NOT NULL,idempotency_key VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,canonicalization_version SMALLINT UNSIGNED NOT NULL,request_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,actor_user_id VARCHAR(64) NOT NULL,patient_id VARCHAR(64) NOT NULL,encounter_id BIGINT UNSIGNED NULL,created_at DATETIME NOT NULL,committed_at DATETIME NULL,UNIQUE KEY uq_encounter_start_idempotency (doctor_id,idempotency_key),KEY idx_encounter_start_result (encounter_id),CONSTRAINT fk_encounter_start_patient FOREIGN KEY (patient_id) REFERENCES patients_patients(patient_id) ON UPDATE RESTRICT ON DELETE RESTRICT,CONSTRAINT fk_encounter_start_result FOREIGN KEY (encounter_id) REFERENCES clinical_encounters(encounter_id) ON UPDATE RESTRICT ON DELETE RESTRICT,CONSTRAINT chk_encounter_start_commit_v1 CHECK ((committed_at IS NULL AND encounter_id IS NULL) OR (committed_at IS NOT NULL AND encounter_id IS NOT NULL))) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;
  ELSEIF n<>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: clinical_encounter_start_requests';
  ELSE
    SELECT COUNT(DISTINCT INDEX_NAME),CONCAT(MAX(NON_UNIQUE),'|',GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX)) INTO n,shape_value FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_encounter_start_requests' AND INDEX_NAME='uq_encounter_start_idempotency';
    IF n=0 THEN ALTER TABLE clinical_encounter_start_requests ADD UNIQUE KEY uq_encounter_start_idempotency (doctor_id,idempotency_key);
    ELSEIF n<>1 OR shape_value<>'0|doctor_id,idempotency_key' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: start idempotency index'; END IF;
  END IF;

  SELECT COUNT(DISTINCT INDEX_NAME),CONCAT(MAX(NON_UNIQUE),'|',GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX)) INTO n,shape_value FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_encounter_start_requests' AND INDEX_NAME='idx_encounter_start_result';
  IF n=0 THEN ALTER TABLE clinical_encounter_start_requests ADD KEY idx_encounter_start_result (encounter_id);
  ELSEIF n<>1 OR shape_value<>'1|encounter_id' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: idx_encounter_start_result'; END IF;

  SELECT COUNT(*) INTO n FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='clinical_encounter_start_requests' AND CONSTRAINT_NAME='chk_encounter_start_commit_v1' AND CONSTRAINT_TYPE='CHECK';
  IF n=0 THEN ALTER TABLE clinical_encounter_start_requests ADD CONSTRAINT chk_encounter_start_commit_v1 CHECK ((committed_at IS NULL AND encounter_id IS NULL) OR (committed_at IS NOT NULL AND encounter_id IS NOT NULL));
  ELSE
    SELECT LOWER(CHECK_CLAUSE) INTO shape_value FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='chk_encounter_start_commit_v1';
    IF n<>1 OR shape_value NOT LIKE '%committed_at%' OR shape_value NOT LIKE '%encounter_id%' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: chk_encounter_start_commit_v1'; END IF;
  END IF;

  SELECT COUNT(*) INTO n FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_idempotency_requests';
  IF n=0 THEN
    SET @ddl='CREATE TABLE clinical_idempotency_requests (request_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,operation_type VARCHAR(64) NOT NULL,doctor_id VARCHAR(64) NOT NULL,context_type VARCHAR(16) NOT NULL,context_id VARCHAR(128) NOT NULL,idempotency_key VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,canonicalization_version SMALLINT UNSIGNED NOT NULL,request_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,actor_user_id VARCHAR(64) NOT NULL,observation_id BIGINT UNSIGNED NULL,document_id BIGINT UNSIGNED NULL,encounter_amendment_id BIGINT UNSIGNED NULL,document_revision_id BIGINT UNSIGNED NULL,created_at DATETIME NOT NULL,committed_at DATETIME NULL,UNIQUE KEY uq_clinical_command_idempotency (operation_type,doctor_id,context_type,context_id,idempotency_key),CONSTRAINT chk_idempotency_context_v1 CHECK (context_type IN (''ENCOUNTER'',''PATIENT'')),CONSTRAINT chk_idempotency_operation_v1 CHECK (operation_type IN (''CREATE_OBSERVATION'',''CREATE_ENCOUNTER_DOCUMENT'',''CREATE_POST_ENCOUNTER_RESULT'',''CREATE_ENCOUNTER_AMENDMENT'',''CREATE_DOCUMENT_AMENDMENT_OR_REPLACEMENT'')),CONSTRAINT fk_idempotency_observation FOREIGN KEY (observation_id) REFERENCES clinical_observations(observation_id) ON UPDATE RESTRICT ON DELETE RESTRICT,CONSTRAINT fk_idempotency_encounter_amendment FOREIGN KEY (encounter_amendment_id) REFERENCES clinical_encounter_amendments(amendment_id) ON UPDATE RESTRICT ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;
  ELSEIF n<>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: clinical_idempotency_requests';
  ELSE
    SELECT COUNT(DISTINCT INDEX_NAME),CONCAT(MAX(NON_UNIQUE),'|',GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX)) INTO n,shape_value FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_idempotency_requests' AND INDEX_NAME='uq_clinical_command_idempotency';
    IF n=0 THEN ALTER TABLE clinical_idempotency_requests ADD UNIQUE KEY uq_clinical_command_idempotency (operation_type,doctor_id,context_type,context_id,idempotency_key);
    ELSEIF n<>1 OR shape_value<>'0|operation_type,doctor_id,context_type,context_id,idempotency_key' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: command idempotency index'; END IF;
  END IF;

  SELECT COUNT(*) INTO n FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='clinical_encounter_start_requests' AND CONSTRAINT_NAME='fk_encounter_start_patient';
  IF n=0 THEN ALTER TABLE clinical_encounter_start_requests ADD CONSTRAINT fk_encounter_start_patient FOREIGN KEY (patient_id) REFERENCES patients_patients(patient_id) ON UPDATE RESTRICT ON DELETE RESTRICT;
  ELSEIF n<>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: fk_encounter_start_patient'; END IF;
  SELECT COUNT(*) INTO n FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='clinical_encounter_start_requests' AND CONSTRAINT_NAME='fk_encounter_start_result';
  IF n=0 THEN ALTER TABLE clinical_encounter_start_requests ADD CONSTRAINT fk_encounter_start_result FOREIGN KEY (encounter_id) REFERENCES clinical_encounters(encounter_id) ON UPDATE RESTRICT ON DELETE RESTRICT;
  ELSEIF n<>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: fk_encounter_start_result'; END IF;
  SELECT COUNT(*) INTO n FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='clinical_idempotency_requests' AND CONSTRAINT_NAME='fk_idempotency_observation';
  IF n=0 THEN ALTER TABLE clinical_idempotency_requests ADD CONSTRAINT fk_idempotency_observation FOREIGN KEY (observation_id) REFERENCES clinical_observations(observation_id) ON UPDATE RESTRICT ON DELETE RESTRICT;
  ELSEIF n<>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: fk_idempotency_observation'; END IF;
  SELECT COUNT(*) INTO n FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='clinical_idempotency_requests' AND CONSTRAINT_NAME='fk_idempotency_encounter_amendment';
  IF n=0 THEN ALTER TABLE clinical_idempotency_requests ADD CONSTRAINT fk_idempotency_encounter_amendment FOREIGN KEY (encounter_amendment_id) REFERENCES clinical_encounter_amendments(amendment_id) ON UPDATE RESTRICT ON DELETE RESTRICT;
  ELSEIF n<>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: fk_idempotency_encounter_amendment'; END IF;

  SELECT COUNT(*) INTO n FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='clinical_idempotency_requests' AND CONSTRAINT_NAME='chk_idempotency_context_v1' AND CONSTRAINT_TYPE='CHECK';
  IF n=0 THEN ALTER TABLE clinical_idempotency_requests ADD CONSTRAINT chk_idempotency_context_v1 CHECK (context_type IN ('ENCOUNTER','PATIENT'));
  ELSE
    SELECT LOWER(CHECK_CLAUSE) INTO shape_value FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='chk_idempotency_context_v1';
    IF n<>1 OR shape_value NOT LIKE '%context_type%' OR shape_value NOT LIKE '%encounter%' OR shape_value NOT LIKE '%patient%' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: chk_idempotency_context_v1'; END IF;
  END IF;
  SELECT COUNT(*) INTO n FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='clinical_idempotency_requests' AND CONSTRAINT_NAME='chk_idempotency_operation_v1' AND CONSTRAINT_TYPE='CHECK';
  IF n=0 THEN ALTER TABLE clinical_idempotency_requests ADD CONSTRAINT chk_idempotency_operation_v1 CHECK (operation_type IN ('CREATE_OBSERVATION','CREATE_ENCOUNTER_DOCUMENT','CREATE_POST_ENCOUNTER_RESULT','CREATE_ENCOUNTER_AMENDMENT','CREATE_DOCUMENT_AMENDMENT_OR_REPLACEMENT'));
  ELSE
    SELECT LOWER(CHECK_CLAUSE) INTO shape_value FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='chk_idempotency_operation_v1';
    IF n<>1 OR shape_value NOT LIKE '%create_observation%' OR shape_value NOT LIKE '%create_encounter_document%' OR shape_value NOT LIKE '%create_post_encounter_result%' OR shape_value NOT LIKE '%create_encounter_amendment%' OR shape_value NOT LIKE '%create_document_amendment_or_replacement%' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: chk_idempotency_operation_v1'; END IF;
  END IF;

  SELECT COUNT(*) INTO n FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND DELETE_RULE='RESTRICT' AND UPDATE_RULE='RESTRICT' AND
    ((TABLE_NAME='clinical_encounter_start_requests' AND CONSTRAINT_NAME IN ('fk_encounter_start_patient','fk_encounter_start_result')) OR
     (TABLE_NAME='clinical_idempotency_requests' AND CONSTRAINT_NAME IN ('fk_idempotency_observation','fk_idempotency_encounter_amendment')));
  IF n<>4 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: idempotency FK manifest'; END IF;
END$$
CALL mxmed_migrate_encounter_idempotency_v1()$$
DROP PROCEDURE mxmed_migrate_encounter_idempotency_v1$$
DELIMITER ;
