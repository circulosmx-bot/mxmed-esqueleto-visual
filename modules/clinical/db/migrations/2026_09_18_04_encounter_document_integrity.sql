-- CLIN-REFORM-PHASE2-IMPL01A-R1 — review artifact only. DO NOT EXECUTE in this chapter.
-- Guarded document linkage. No legacy encounter link is inferred or backfilled.

DELIMITER $$
DROP PROCEDURE IF EXISTS mxmed_migrate_encounter_documents_v1$$
CREATE PROCEDURE mxmed_migrate_encounter_documents_v1()
BEGIN
  DECLARE n INT DEFAULT 0;
  DECLARE shape_value TEXT DEFAULT NULL;

  SELECT COUNT(*) INTO n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_documents' AND COLUMN_NAME='encounter_ref_id';
  IF n=0 THEN ALTER TABLE clinical_documents ADD COLUMN encounter_ref_id BIGINT UNSIGNED NULL AFTER encounter_id;
  ELSE
    SELECT CONCAT(DATA_TYPE,'|',COLUMN_TYPE,'|',IS_NULLABLE) INTO shape_value FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_documents' AND COLUMN_NAME='encounter_ref_id';
    IF n<>1 OR shape_value<>'bigint|bigint unsigned|YES' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: encounter_ref_id'; END IF;
  END IF;

  SELECT COUNT(*) INTO n FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_documents' AND INDEX_NAME='idx_clinical_documents_encounter_ref';
  IF n=0 THEN ALTER TABLE clinical_documents ADD KEY idx_clinical_documents_encounter_ref (encounter_ref_id,event_datetime);
  ELSE
    SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) INTO shape_value FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_documents' AND INDEX_NAME='idx_clinical_documents_encounter_ref';
    IF shape_value<>'encounter_ref_id,event_datetime' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: encounter_ref index'; END IF;
  END IF;

  SELECT COUNT(*) INTO n FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='clinical_documents' AND CONSTRAINT_NAME='fk_clinical_documents_encounter_ref';
  IF n=0 THEN ALTER TABLE clinical_documents ADD CONSTRAINT fk_clinical_documents_encounter_ref FOREIGN KEY (encounter_ref_id) REFERENCES clinical_encounters(encounter_id) ON UPDATE RESTRICT ON DELETE RESTRICT;
  ELSE
    SELECT CONCAT(REFERENCED_TABLE_NAME,'|',UPDATE_RULE,'|',DELETE_RULE) INTO shape_value FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='clinical_documents' AND CONSTRAINT_NAME='fk_clinical_documents_encounter_ref';
    IF n<>1 OR shape_value<>'clinical_encounters|RESTRICT|RESTRICT' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: encounter_ref FK'; END IF;
  END IF;

  SELECT COUNT(*) INTO n FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_encounter_final_notes';
  IF n=0 THEN
    SET @ddl='CREATE TABLE clinical_encounter_final_notes (encounter_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,document_id BIGINT UNSIGNED NOT NULL,created_at DATETIME NOT NULL,UNIQUE KEY uq_encounter_final_note_document (document_id),CONSTRAINT fk_encounter_final_note_encounter FOREIGN KEY (encounter_id) REFERENCES clinical_encounters(encounter_id) ON UPDATE RESTRICT ON DELETE RESTRICT,CONSTRAINT fk_encounter_final_note_document FOREIGN KEY (document_id) REFERENCES clinical_documents(id) ON UPDATE RESTRICT ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;
  ELSEIF n<>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: clinical_encounter_final_notes';
  ELSE
    SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) INTO shape_value FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_encounter_final_notes' AND INDEX_NAME='PRIMARY';
    IF shape_value<>'encounter_id' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: final note PK'; END IF;
    SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) INTO shape_value FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_encounter_final_notes' AND INDEX_NAME='uq_encounter_final_note_document';
    IF shape_value<>'document_id' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: final note document unique'; END IF;
  END IF;

  SELECT COUNT(*) INTO n FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_document_revisions';
  IF n=0 THEN
    SET @ddl='CREATE TABLE clinical_document_revisions (revision_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,original_document_id BIGINT UNSIGNED NOT NULL,supersedes_document_id BIGINT UNSIGNED NULL,new_document_id BIGINT UNSIGNED NOT NULL,reason VARCHAR(1000) NOT NULL,author_user_id VARCHAR(64) NOT NULL,created_at DATETIME NOT NULL,UNIQUE KEY uq_document_revision_new_document (new_document_id),KEY idx_document_revision_original (original_document_id,revision_id),CONSTRAINT chk_document_revision_reason_v1 CHECK (CHAR_LENGTH(TRIM(reason))>0),CONSTRAINT fk_document_revision_original FOREIGN KEY (original_document_id) REFERENCES clinical_documents(id) ON UPDATE RESTRICT ON DELETE RESTRICT,CONSTRAINT fk_document_revision_supersedes FOREIGN KEY (supersedes_document_id) REFERENCES clinical_documents(id) ON UPDATE RESTRICT ON DELETE RESTRICT,CONSTRAINT fk_document_revision_new FOREIGN KEY (new_document_id) REFERENCES clinical_documents(id) ON UPDATE RESTRICT ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;
  ELSEIF n<>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: clinical_document_revisions';
  ELSE
    SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) INTO shape_value FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_document_revisions' AND INDEX_NAME='uq_document_revision_new_document';
    IF shape_value<>'new_document_id' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: document revision unique'; END IF;
  END IF;

  SELECT COUNT(*) INTO n FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='clinical_idempotency_requests' AND CONSTRAINT_NAME='fk_idempotency_document';
  IF n=0 THEN ALTER TABLE clinical_idempotency_requests ADD CONSTRAINT fk_idempotency_document FOREIGN KEY (document_id) REFERENCES clinical_documents(id) ON UPDATE RESTRICT ON DELETE RESTRICT;
  ELSEIF n<>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: idempotency document FK'; END IF;
  SELECT COUNT(*) INTO n FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='clinical_idempotency_requests' AND CONSTRAINT_NAME='fk_idempotency_document_revision';
  IF n=0 THEN ALTER TABLE clinical_idempotency_requests ADD CONSTRAINT fk_idempotency_document_revision FOREIGN KEY (document_revision_id) REFERENCES clinical_document_revisions(revision_id) ON UPDATE RESTRICT ON DELETE RESTRICT;
  ELSEIF n<>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: idempotency revision FK'; END IF;

  SELECT COUNT(*) INTO n FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='clinical_idempotency_requests' AND CONSTRAINT_NAME='chk_idempotency_committed_result_v1' AND CONSTRAINT_TYPE='CHECK';
  IF n=0 THEN ALTER TABLE clinical_idempotency_requests ADD CONSTRAINT chk_idempotency_committed_result_v1 CHECK (
    (committed_at IS NULL AND observation_id IS NULL AND document_id IS NULL AND encounter_amendment_id IS NULL AND document_revision_id IS NULL) OR
    (committed_at IS NOT NULL AND ((operation_type='CREATE_OBSERVATION' AND observation_id IS NOT NULL AND document_id IS NULL AND encounter_amendment_id IS NULL AND document_revision_id IS NULL) OR
      (operation_type IN ('CREATE_ENCOUNTER_DOCUMENT','CREATE_POST_ENCOUNTER_RESULT') AND observation_id IS NULL AND document_id IS NOT NULL AND encounter_amendment_id IS NULL AND document_revision_id IS NULL) OR
      (operation_type='CREATE_ENCOUNTER_AMENDMENT' AND observation_id IS NULL AND document_id IS NULL AND encounter_amendment_id IS NOT NULL AND document_revision_id IS NULL) OR
      (operation_type='CREATE_DOCUMENT_AMENDMENT_OR_REPLACEMENT' AND observation_id IS NULL AND document_id IS NULL AND encounter_amendment_id IS NULL AND document_revision_id IS NOT NULL))));
  ELSEIF n<>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: idempotency result check'; END IF;

  SELECT COUNT(*) INTO n FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE()
    AND DELETE_RULE='RESTRICT' AND UPDATE_RULE='RESTRICT' AND (
      (TABLE_NAME='clinical_documents' AND CONSTRAINT_NAME='fk_clinical_documents_encounter_ref' AND REFERENCED_TABLE_NAME='clinical_encounters') OR
      (TABLE_NAME='clinical_encounter_final_notes' AND CONSTRAINT_NAME='fk_encounter_final_note_encounter' AND REFERENCED_TABLE_NAME='clinical_encounters') OR
      (TABLE_NAME='clinical_encounter_final_notes' AND CONSTRAINT_NAME='fk_encounter_final_note_document' AND REFERENCED_TABLE_NAME='clinical_documents') OR
      (TABLE_NAME='clinical_document_revisions' AND CONSTRAINT_NAME IN ('fk_document_revision_original','fk_document_revision_supersedes','fk_document_revision_new') AND REFERENCED_TABLE_NAME='clinical_documents') OR
      (TABLE_NAME='clinical_idempotency_requests' AND CONSTRAINT_NAME='fk_idempotency_document' AND REFERENCED_TABLE_NAME='clinical_documents') OR
      (TABLE_NAME='clinical_idempotency_requests' AND CONSTRAINT_NAME='fk_idempotency_document_revision' AND REFERENCED_TABLE_NAME='clinical_document_revisions')
    );
  IF n<>8 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: document integrity FK manifest'; END IF;
END$$
CALL mxmed_migrate_encounter_documents_v1()$$
DROP PROCEDURE mxmed_migrate_encounter_documents_v1$$
DELIMITER ;
