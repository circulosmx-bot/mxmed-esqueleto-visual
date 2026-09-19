-- CLIN-REFORM-PHASE2-IMPL01A-R2 — review artifact only. DO NOT EXECUTE in this chapter.
-- Each whole-table CREATE is atomic; an existing table must match the guarded manifest.

DELIMITER $$
DROP PROCEDURE IF EXISTS mxmed_migrate_encounter_content_v1$$
CREATE PROCEDURE mxmed_migrate_encounter_content_v1()
BEGIN
  DECLARE n INT DEFAULT 0;
  DECLARE shape_value TEXT DEFAULT NULL;

  SELECT COUNT(*) INTO n FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_encounter_sections';
  IF n=0 THEN
    SET @ddl='CREATE TABLE clinical_encounter_sections (section_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,encounter_id BIGINT UNSIGNED NOT NULL,section_type VARCHAR(40) NOT NULL,payload_schema_version SMALLINT UNSIGNED NOT NULL,payload_json JSON NOT NULL,narrative_text TEXT NULL,created_by_user_id VARCHAR(64) NOT NULL,updated_by_user_id VARCHAR(64) NOT NULL,row_version BIGINT UNSIGNED NOT NULL DEFAULT 1,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,CONSTRAINT chk_encounter_section_type_v1 CHECK (section_type IN (''reason_evolution'',''review_of_systems'',''physical_exam'',''assessment'',''plan'',''follow_up'')),CONSTRAINT chk_encounter_section_version_v1 CHECK (payload_schema_version>=1 AND row_version>=1),UNIQUE KEY uq_encounter_section_concept (encounter_id,section_type),CONSTRAINT fk_encounter_sections_encounter FOREIGN KEY (encounter_id) REFERENCES clinical_encounters(encounter_id) ON UPDATE RESTRICT ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;
  ELSEIF n<>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: clinical_encounter_sections';
  ELSE
    SELECT COUNT(*) INTO n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_encounter_sections' AND COLUMN_NAME IN ('section_id','encounter_id','section_type','payload_schema_version','payload_json','row_version');
    IF n<>6 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: clinical_encounter_sections columns'; END IF;
    SELECT COUNT(DISTINCT INDEX_NAME),CONCAT(MAX(NON_UNIQUE),'|',GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX)) INTO n,shape_value FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_encounter_sections' AND INDEX_NAME='uq_encounter_section_concept';
    IF n=0 THEN ALTER TABLE clinical_encounter_sections ADD UNIQUE KEY uq_encounter_section_concept (encounter_id,section_type);
    ELSEIF n<>1 OR shape_value<>'0|encounter_id,section_type' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: uq_encounter_section_concept'; END IF;
  END IF;

  SELECT COUNT(*) INTO n FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='clinical_encounter_sections' AND CONSTRAINT_NAME='chk_encounter_section_type_v1' AND CONSTRAINT_TYPE='CHECK';
  IF n=0 THEN ALTER TABLE clinical_encounter_sections ADD CONSTRAINT chk_encounter_section_type_v1 CHECK (section_type IN ('reason_evolution','review_of_systems','physical_exam','assessment','plan','follow_up'));
  ELSE
    SELECT LOWER(CHECK_CLAUSE) INTO shape_value FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='chk_encounter_section_type_v1';
    IF n<>1 OR shape_value NOT LIKE '%reason_evolution%' OR shape_value NOT LIKE '%physical_exam%' OR shape_value NOT LIKE '%follow_up%' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: chk_encounter_section_type_v1'; END IF;
  END IF;
  SELECT COUNT(*) INTO n FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='clinical_encounter_sections' AND CONSTRAINT_NAME='chk_encounter_section_version_v1' AND CONSTRAINT_TYPE='CHECK';
  IF n=0 THEN ALTER TABLE clinical_encounter_sections ADD CONSTRAINT chk_encounter_section_version_v1 CHECK (payload_schema_version>=1 AND row_version>=1);
  ELSE
    SELECT LOWER(CHECK_CLAUSE) INTO shape_value FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='chk_encounter_section_version_v1';
    IF n<>1 OR shape_value NOT LIKE '%payload_schema_version%' OR shape_value NOT LIKE '%row_version%' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: chk_encounter_section_version_v1'; END IF;
  END IF;

  SELECT COUNT(*) INTO n FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_observations';
  IF n=0 THEN
    SET @ddl='CREATE TABLE clinical_observations (observation_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,encounter_id BIGINT UNSIGNED NOT NULL,code VARCHAR(64) NOT NULL,value_numeric DECIMAL(18,6) NULL,value_text VARCHAR(1000) NULL,unit VARCHAR(32) NOT NULL,systolic_mm_hg DECIMAL(6,2) NULL,diastolic_mm_hg DECIMAL(6,2) NULL,effective_at DATETIME NOT NULL,recorded_at DATETIME NOT NULL,recorded_by_user_id VARCHAR(64) NOT NULL,source VARCHAR(32) NOT NULL,provenance_json JSON NOT NULL,row_version BIGINT UNSIGNED NOT NULL DEFAULT 1,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,CONSTRAINT chk_observation_version_v1 CHECK (row_version>=1),CONSTRAINT chk_observation_source_v1 CHECK (source IN (''direct_measurement'',''patient_report'',''import'')),CONSTRAINT chk_observation_bp_v1 CHECK ((code=''blood_pressure'' AND systolic_mm_hg>0 AND diastolic_mm_hg>0 AND value_numeric IS NULL AND (value_text IS NULL OR TRIM(value_text)='''') AND unit=''mmHg'') OR (code<>''blood_pressure'' AND value_numeric IS NOT NULL AND systolic_mm_hg IS NULL AND diastolic_mm_hg IS NULL)),KEY idx_observation_encounter_code_effective (encounter_id,code,effective_at),CONSTRAINT fk_observations_encounter FOREIGN KEY (encounter_id) REFERENCES clinical_encounters(encounter_id) ON UPDATE RESTRICT ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;
  ELSEIF n<>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: clinical_observations';
  ELSE
    SELECT COUNT(*) INTO n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_observations' AND COLUMN_NAME IN ('observation_id','encounter_id','code','row_version','systolic_mm_hg','diastolic_mm_hg');
    IF n<>6 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: clinical_observations columns'; END IF;
  END IF;

  SELECT COUNT(*) INTO n FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='clinical_observations' AND CONSTRAINT_NAME='chk_observation_version_v1' AND CONSTRAINT_TYPE='CHECK';
  IF n=0 THEN ALTER TABLE clinical_observations ADD CONSTRAINT chk_observation_version_v1 CHECK (row_version>=1);
  ELSE
    SELECT LOWER(CHECK_CLAUSE) INTO shape_value FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='chk_observation_version_v1';
    IF n<>1 OR shape_value NOT LIKE '%row_version%' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: chk_observation_version_v1'; END IF;
  END IF;

  SELECT COUNT(DISTINCT INDEX_NAME),CONCAT(MAX(NON_UNIQUE),'|',GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX)) INTO n,shape_value FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_observations' AND INDEX_NAME='idx_observation_encounter_code_effective';
  IF n=0 THEN ALTER TABLE clinical_observations ADD KEY idx_observation_encounter_code_effective (encounter_id,code,effective_at);
  ELSEIF n<>1 OR shape_value<>'1|encounter_id,code,effective_at' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: idx_observation_encounter_code_effective'; END IF;
  SELECT COUNT(*) INTO n FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='clinical_observations' AND CONSTRAINT_NAME='chk_observation_source_v1' AND CONSTRAINT_TYPE='CHECK';
  IF n=0 THEN ALTER TABLE clinical_observations ADD CONSTRAINT chk_observation_source_v1 CHECK (source IN ('direct_measurement','patient_report','import'));
  ELSE
    SELECT LOWER(CHECK_CLAUSE) INTO shape_value FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='chk_observation_source_v1';
    IF n<>1 OR shape_value NOT LIKE '%direct_measurement%' OR shape_value NOT LIKE '%patient_report%' OR shape_value NOT LIKE '%import%' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: chk_observation_source_v1'; END IF;
  END IF;
  SELECT COUNT(*) INTO n FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='clinical_observations' AND CONSTRAINT_NAME='chk_observation_bp_v1' AND CONSTRAINT_TYPE='CHECK';
  IF n=0 THEN ALTER TABLE clinical_observations ADD CONSTRAINT chk_observation_bp_v1 CHECK ((code='blood_pressure' AND systolic_mm_hg>0 AND diastolic_mm_hg>0 AND value_numeric IS NULL AND (value_text IS NULL OR TRIM(value_text)='') AND unit='mmHg') OR (code<>'blood_pressure' AND value_numeric IS NOT NULL AND systolic_mm_hg IS NULL AND diastolic_mm_hg IS NULL));
  ELSE
    SELECT LOWER(CHECK_CLAUSE) INTO shape_value FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='chk_observation_bp_v1';
    IF n<>1 OR shape_value NOT LIKE '%blood_pressure%' OR shape_value NOT LIKE '%systolic_mm_hg%' OR shape_value NOT LIKE '%diastolic_mm_hg%' OR shape_value NOT LIKE '%mmhg%' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: chk_observation_bp_v1'; END IF;
  END IF;

  SELECT COUNT(*) INTO n FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_encounter_amendments';
  IF n=0 THEN
    SET @ddl='CREATE TABLE clinical_encounter_amendments (amendment_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,encounter_id BIGINT UNSIGNED NOT NULL,target_type VARCHAR(40) NOT NULL,target_id VARCHAR(128) NULL,target_field VARCHAR(128) NULL,reason VARCHAR(1000) NOT NULL,author_user_id VARCHAR(64) NOT NULL,amended_at DATETIME NOT NULL,correction_payload_json JSON NOT NULL,previous_effective_reference VARCHAR(255) NULL,CONSTRAINT chk_encounter_amendment_reason_v1 CHECK (CHAR_LENGTH(TRIM(reason))>0),KEY idx_encounter_amendment_history (encounter_id,amended_at,amendment_id),CONSTRAINT fk_encounter_amendments_encounter FOREIGN KEY (encounter_id) REFERENCES clinical_encounters(encounter_id) ON UPDATE RESTRICT ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;
  ELSEIF n<>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: clinical_encounter_amendments';
  ELSE
    SELECT COUNT(*) INTO n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_encounter_amendments' AND COLUMN_NAME IN ('amendment_id','encounter_id','target_type','reason','correction_payload_json');
    IF n<>5 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: clinical_encounter_amendments columns'; END IF;
  END IF;

  SELECT COUNT(*) INTO n FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='clinical_encounter_amendments' AND CONSTRAINT_NAME='chk_encounter_amendment_reason_v1' AND CONSTRAINT_TYPE='CHECK';
  IF n=0 THEN ALTER TABLE clinical_encounter_amendments ADD CONSTRAINT chk_encounter_amendment_reason_v1 CHECK (CHAR_LENGTH(TRIM(reason))>0);
  ELSE
    SELECT LOWER(CHECK_CLAUSE) INTO shape_value FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='chk_encounter_amendment_reason_v1';
    IF n<>1 OR shape_value NOT LIKE '%char_length%' OR shape_value NOT LIKE '%reason%' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: chk_encounter_amendment_reason_v1'; END IF;
  END IF;

  SELECT COUNT(DISTINCT INDEX_NAME),CONCAT(MAX(NON_UNIQUE),'|',GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX)) INTO n,shape_value FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_encounter_amendments' AND INDEX_NAME='idx_encounter_amendment_history';
  IF n=0 THEN ALTER TABLE clinical_encounter_amendments ADD KEY idx_encounter_amendment_history (encounter_id,amended_at,amendment_id);
  ELSEIF n<>1 OR shape_value<>'1|encounter_id,amended_at,amendment_id' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: idx_encounter_amendment_history'; END IF;

  SELECT COUNT(*) INTO n FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='clinical_encounter_sections' AND CONSTRAINT_NAME='fk_encounter_sections_encounter';
  IF n=0 THEN ALTER TABLE clinical_encounter_sections ADD CONSTRAINT fk_encounter_sections_encounter FOREIGN KEY (encounter_id) REFERENCES clinical_encounters(encounter_id) ON UPDATE RESTRICT ON DELETE RESTRICT;
  ELSEIF n<>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: fk_encounter_sections_encounter'; END IF;
  SELECT COUNT(*) INTO n FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='clinical_observations' AND CONSTRAINT_NAME='fk_observations_encounter';
  IF n=0 THEN ALTER TABLE clinical_observations ADD CONSTRAINT fk_observations_encounter FOREIGN KEY (encounter_id) REFERENCES clinical_encounters(encounter_id) ON UPDATE RESTRICT ON DELETE RESTRICT;
  ELSEIF n<>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: fk_observations_encounter'; END IF;
  SELECT COUNT(*) INTO n FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='clinical_encounter_amendments' AND CONSTRAINT_NAME='fk_encounter_amendments_encounter';
  IF n=0 THEN ALTER TABLE clinical_encounter_amendments ADD CONSTRAINT fk_encounter_amendments_encounter FOREIGN KEY (encounter_id) REFERENCES clinical_encounters(encounter_id) ON UPDATE RESTRICT ON DELETE RESTRICT;
  ELSEIF n<>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: fk_encounter_amendments_encounter'; END IF;

  SELECT COUNT(*) INTO n FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE()
    AND DELETE_RULE='RESTRICT' AND UPDATE_RULE='RESTRICT' AND REFERENCED_TABLE_NAME='clinical_encounters' AND
    ((TABLE_NAME='clinical_encounter_sections' AND CONSTRAINT_NAME='fk_encounter_sections_encounter') OR
     (TABLE_NAME='clinical_observations' AND CONSTRAINT_NAME='fk_observations_encounter') OR
     (TABLE_NAME='clinical_encounter_amendments' AND CONSTRAINT_NAME='fk_encounter_amendments_encounter'));
  IF n<>3 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: encounter content FK manifest'; END IF;
END$$
CALL mxmed_migrate_encounter_content_v1()$$
DROP PROCEDURE mxmed_migrate_encounter_content_v1$$
DELIMITER ;
