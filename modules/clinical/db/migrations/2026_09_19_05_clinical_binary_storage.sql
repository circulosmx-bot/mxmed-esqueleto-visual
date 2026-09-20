-- CLIN-REFORM-PHASE2-M6-MULTI02A
-- REPOSITORY ARTIFACT ONLY
-- DO NOT EXECUTE ON WORKING DB IN THIS CHAPTER
-- Additive binary coordination and immutable manifest foundation. No legacy backfill.
-- clinical_binary_uploads is operational only; clinical_documents and
-- clinical_idempotency_requests remain the clinical and command authorities.
-- clinical_document_binaries contains finalized immutable manifests only.

DELIMITER $$
DROP PROCEDURE IF EXISTS mxmed_migrate_clinical_binary_storage_v1$$
CREATE PROCEDURE mxmed_migrate_clinical_binary_storage_v1()
BEGIN
  DECLARE n INT DEFAULT 0;
  DECLARE valid_n INT DEFAULT 0;
  DECLARE shape_value TEXT DEFAULT NULL;

  SELECT COUNT(*) INTO n
    FROM information_schema.TABLES
   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_binary_uploads';
  IF n=0 THEN
    SET @ddl='CREATE TABLE clinical_binary_uploads (
      upload_id CHAR(36) NOT NULL,
      operation_type VARCHAR(64) NOT NULL,
      doctor_id VARCHAR(64) NOT NULL,
      context_type VARCHAR(16) NOT NULL,
      context_id VARCHAR(128) NOT NULL,
      idempotency_key_digest CHAR(64) NOT NULL,
      idempotency_request_id BIGINT UNSIGNED NULL,
      semantic_request_hash CHAR(64) NOT NULL,
      binary_sha256 CHAR(64) NOT NULL,
      byte_length BIGINT UNSIGNED NOT NULL,
      mime_type VARCHAR(100) NOT NULL,
      staging_key VARCHAR(512) NOT NULL,
      planned_final_prefix VARCHAR(512) NOT NULL,
      storage_state VARCHAR(32) NOT NULL,
      document_id BIGINT UNSIGNED NULL,
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL,
      expires_at DATETIME NOT NULL,
      lease_until DATETIME NULL,
      last_error_code VARCHAR(64) NULL,
      PRIMARY KEY (upload_id),
      UNIQUE KEY uq_binary_upload_staging_key (staging_key),
      KEY idx_binary_upload_state_expiry (storage_state,expires_at),
      KEY idx_binary_upload_idempotency (idempotency_request_id),
      KEY idx_binary_upload_document (document_id),
      KEY idx_binary_upload_sha256 (binary_sha256),
      CONSTRAINT chk_binary_upload_state_v1 CHECK (storage_state IN (''STAGED'',''FINALIZED'',''ORPHANED'',''RECONCILIATION_REQUIRED'')),
      CONSTRAINT chk_binary_upload_hashes_v1 CHECK (
        CHAR_LENGTH(idempotency_key_digest)=64 AND idempotency_key_digest REGEXP ''^[0-9A-Fa-f]{64}$'' AND
        CHAR_LENGTH(semantic_request_hash)=64 AND semantic_request_hash REGEXP ''^[0-9A-Fa-f]{64}$'' AND
        CHAR_LENGTH(binary_sha256)=64 AND binary_sha256 REGEXP ''^[0-9A-Fa-f]{64}$''
      ),
      CONSTRAINT chk_binary_upload_integrity_v1 CHECK (
        byte_length>0 AND CHAR_LENGTH(TRIM(mime_type))>0 AND
        CHAR_LENGTH(TRIM(staging_key))>0 AND CHAR_LENGTH(TRIM(planned_final_prefix))>0
      ),
      CONSTRAINT fk_binary_upload_idempotency FOREIGN KEY (idempotency_request_id)
        REFERENCES clinical_idempotency_requests(request_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
      CONSTRAINT fk_binary_upload_document FOREIGN KEY (document_id)
        REFERENCES clinical_documents(id) ON UPDATE RESTRICT ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;
  ELSEIF n<>1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: clinical_binary_uploads';
  ELSE
    SELECT COUNT(*), SUM(CASE WHEN
      (COLUMN_NAME='upload_id' AND COLUMN_TYPE='char(36)' AND IS_NULLABLE='NO' AND EXTRA='') OR
      (COLUMN_NAME='operation_type' AND COLUMN_TYPE='varchar(64)' AND IS_NULLABLE='NO' AND EXTRA='') OR
      (COLUMN_NAME='doctor_id' AND COLUMN_TYPE='varchar(64)' AND IS_NULLABLE='NO' AND EXTRA='') OR
      (COLUMN_NAME='context_type' AND COLUMN_TYPE='varchar(16)' AND IS_NULLABLE='NO' AND EXTRA='') OR
      (COLUMN_NAME='context_id' AND COLUMN_TYPE='varchar(128)' AND IS_NULLABLE='NO' AND EXTRA='') OR
      (COLUMN_NAME='idempotency_key_digest' AND COLUMN_TYPE='char(64)' AND IS_NULLABLE='NO' AND EXTRA='') OR
      (COLUMN_NAME='idempotency_request_id' AND COLUMN_TYPE='bigint unsigned' AND IS_NULLABLE='YES' AND EXTRA='') OR
      (COLUMN_NAME='semantic_request_hash' AND COLUMN_TYPE='char(64)' AND IS_NULLABLE='NO' AND EXTRA='') OR
      (COLUMN_NAME='binary_sha256' AND COLUMN_TYPE='char(64)' AND IS_NULLABLE='NO' AND EXTRA='') OR
      (COLUMN_NAME='byte_length' AND COLUMN_TYPE='bigint unsigned' AND IS_NULLABLE='NO' AND EXTRA='') OR
      (COLUMN_NAME='mime_type' AND COLUMN_TYPE='varchar(100)' AND IS_NULLABLE='NO' AND EXTRA='') OR
      (COLUMN_NAME='staging_key' AND COLUMN_TYPE='varchar(512)' AND IS_NULLABLE='NO' AND EXTRA='') OR
      (COLUMN_NAME='planned_final_prefix' AND COLUMN_TYPE='varchar(512)' AND IS_NULLABLE='NO' AND EXTRA='') OR
      (COLUMN_NAME='storage_state' AND COLUMN_TYPE='varchar(32)' AND IS_NULLABLE='NO' AND EXTRA='') OR
      (COLUMN_NAME='document_id' AND COLUMN_TYPE='bigint unsigned' AND IS_NULLABLE='YES' AND EXTRA='') OR
      (COLUMN_NAME='created_at' AND COLUMN_TYPE='datetime' AND IS_NULLABLE='NO' AND EXTRA='') OR
      (COLUMN_NAME='updated_at' AND COLUMN_TYPE='datetime' AND IS_NULLABLE='NO' AND EXTRA='') OR
      (COLUMN_NAME='expires_at' AND COLUMN_TYPE='datetime' AND IS_NULLABLE='NO' AND EXTRA='') OR
      (COLUMN_NAME='lease_until' AND COLUMN_TYPE='datetime' AND IS_NULLABLE='YES' AND EXTRA='') OR
      (COLUMN_NAME='last_error_code' AND COLUMN_TYPE='varchar(64)' AND IS_NULLABLE='YES' AND EXTRA='')
      THEN 1 ELSE 0 END) INTO n,valid_n
      FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_binary_uploads';
    IF n<>20 OR valid_n<>20 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: clinical_binary_uploads columns';
    END IF;
  END IF;

  SELECT COUNT(DISTINCT INDEX_NAME),CONCAT(MAX(NON_UNIQUE),'|',GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX)) INTO n,shape_value
    FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_binary_uploads' AND INDEX_NAME='PRIMARY';
  IF n=0 THEN ALTER TABLE clinical_binary_uploads ADD PRIMARY KEY (upload_id);
  ELSEIF n<>1 OR shape_value<>'0|upload_id' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: clinical_binary_uploads primary key'; END IF;

  SELECT COUNT(DISTINCT INDEX_NAME),CONCAT(MAX(NON_UNIQUE),'|',GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX)) INTO n,shape_value
    FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_binary_uploads' AND INDEX_NAME='uq_binary_upload_staging_key';
  IF n=0 THEN ALTER TABLE clinical_binary_uploads ADD UNIQUE KEY uq_binary_upload_staging_key (staging_key);
  ELSEIF n<>1 OR shape_value<>'0|staging_key' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: uq_binary_upload_staging_key'; END IF;

  SELECT COUNT(DISTINCT INDEX_NAME),CONCAT(MAX(NON_UNIQUE),'|',GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX)) INTO n,shape_value
    FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_binary_uploads' AND INDEX_NAME='idx_binary_upload_state_expiry';
  IF n=0 THEN ALTER TABLE clinical_binary_uploads ADD KEY idx_binary_upload_state_expiry (storage_state,expires_at);
  ELSEIF n<>1 OR shape_value<>'1|storage_state,expires_at' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: idx_binary_upload_state_expiry'; END IF;

  SELECT COUNT(DISTINCT INDEX_NAME),CONCAT(MAX(NON_UNIQUE),'|',GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX)) INTO n,shape_value
    FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_binary_uploads' AND INDEX_NAME='idx_binary_upload_idempotency';
  IF n=0 THEN ALTER TABLE clinical_binary_uploads ADD KEY idx_binary_upload_idempotency (idempotency_request_id);
  ELSEIF n<>1 OR shape_value<>'1|idempotency_request_id' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: idx_binary_upload_idempotency'; END IF;

  SELECT COUNT(DISTINCT INDEX_NAME),CONCAT(MAX(NON_UNIQUE),'|',GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX)) INTO n,shape_value
    FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_binary_uploads' AND INDEX_NAME='idx_binary_upload_document';
  IF n=0 THEN ALTER TABLE clinical_binary_uploads ADD KEY idx_binary_upload_document (document_id);
  ELSEIF n<>1 OR shape_value<>'1|document_id' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: idx_binary_upload_document'; END IF;

  SELECT COUNT(DISTINCT INDEX_NAME),CONCAT(MAX(NON_UNIQUE),'|',GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX)) INTO n,shape_value
    FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_binary_uploads' AND INDEX_NAME='idx_binary_upload_sha256';
  IF n=0 THEN ALTER TABLE clinical_binary_uploads ADD KEY idx_binary_upload_sha256 (binary_sha256);
  ELSEIF n<>1 OR shape_value<>'1|binary_sha256' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: idx_binary_upload_sha256'; END IF;

  SELECT COUNT(*) INTO n FROM information_schema.REFERENTIAL_CONSTRAINTS
   WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='clinical_binary_uploads' AND CONSTRAINT_NAME='fk_binary_upload_idempotency';
  IF n=0 THEN ALTER TABLE clinical_binary_uploads ADD CONSTRAINT fk_binary_upload_idempotency FOREIGN KEY (idempotency_request_id) REFERENCES clinical_idempotency_requests(request_id) ON UPDATE RESTRICT ON DELETE RESTRICT;
  ELSE
    SELECT CONCAT(k.COLUMN_NAME,'|',k.REFERENCED_TABLE_NAME,'|',k.REFERENCED_COLUMN_NAME,'|',r.UPDATE_RULE,'|',r.DELETE_RULE) INTO shape_value
      FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r
        ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME
     WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME='clinical_binary_uploads' AND k.CONSTRAINT_NAME='fk_binary_upload_idempotency';
    IF n<>1 OR shape_value<>'idempotency_request_id|clinical_idempotency_requests|request_id|RESTRICT|RESTRICT' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: fk_binary_upload_idempotency'; END IF;
  END IF;

  SELECT COUNT(*) INTO n FROM information_schema.REFERENTIAL_CONSTRAINTS
   WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='clinical_binary_uploads' AND CONSTRAINT_NAME='fk_binary_upload_document';
  IF n=0 THEN ALTER TABLE clinical_binary_uploads ADD CONSTRAINT fk_binary_upload_document FOREIGN KEY (document_id) REFERENCES clinical_documents(id) ON UPDATE RESTRICT ON DELETE RESTRICT;
  ELSE
    SELECT CONCAT(k.COLUMN_NAME,'|',k.REFERENCED_TABLE_NAME,'|',k.REFERENCED_COLUMN_NAME,'|',r.UPDATE_RULE,'|',r.DELETE_RULE) INTO shape_value
      FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r
        ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME
     WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME='clinical_binary_uploads' AND k.CONSTRAINT_NAME='fk_binary_upload_document';
    IF n<>1 OR shape_value<>'document_id|clinical_documents|id|RESTRICT|RESTRICT' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: fk_binary_upload_document'; END IF;
  END IF;

  SELECT COUNT(*) INTO n FROM information_schema.TABLE_CONSTRAINTS
   WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='clinical_binary_uploads' AND CONSTRAINT_NAME='chk_binary_upload_state_v1' AND CONSTRAINT_TYPE='CHECK';
  IF n=0 THEN ALTER TABLE clinical_binary_uploads ADD CONSTRAINT chk_binary_upload_state_v1 CHECK (storage_state IN ('STAGED','FINALIZED','ORPHANED','RECONCILIATION_REQUIRED'));
  ELSE
    SELECT LOWER(CHECK_CLAUSE) INTO shape_value FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='chk_binary_upload_state_v1';
    IF n<>1 OR shape_value NOT LIKE '%staged%' OR shape_value NOT LIKE '%finalized%' OR shape_value NOT LIKE '%orphaned%' OR shape_value NOT LIKE '%reconciliation_required%' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: chk_binary_upload_state_v1'; END IF;
  END IF;

  SELECT COUNT(*) INTO n FROM information_schema.TABLE_CONSTRAINTS
   WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='clinical_binary_uploads' AND CONSTRAINT_NAME='chk_binary_upload_hashes_v1' AND CONSTRAINT_TYPE='CHECK';
  IF n=0 THEN ALTER TABLE clinical_binary_uploads ADD CONSTRAINT chk_binary_upload_hashes_v1 CHECK (
    CHAR_LENGTH(idempotency_key_digest)=64 AND idempotency_key_digest REGEXP '^[0-9A-Fa-f]{64}$' AND
    CHAR_LENGTH(semantic_request_hash)=64 AND semantic_request_hash REGEXP '^[0-9A-Fa-f]{64}$' AND
    CHAR_LENGTH(binary_sha256)=64 AND binary_sha256 REGEXP '^[0-9A-Fa-f]{64}$');
  ELSE
    SELECT LOWER(CHECK_CLAUSE) INTO shape_value FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='chk_binary_upload_hashes_v1';
    IF n<>1 OR shape_value NOT LIKE '%idempotency_key_digest%' OR shape_value NOT LIKE '%semantic_request_hash%' OR shape_value NOT LIKE '%binary_sha256%' OR shape_value NOT LIKE '%regexp%' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: chk_binary_upload_hashes_v1'; END IF;
  END IF;

  SELECT COUNT(*) INTO n FROM information_schema.TABLE_CONSTRAINTS
   WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='clinical_binary_uploads' AND CONSTRAINT_NAME='chk_binary_upload_integrity_v1' AND CONSTRAINT_TYPE='CHECK';
  IF n=0 THEN ALTER TABLE clinical_binary_uploads ADD CONSTRAINT chk_binary_upload_integrity_v1 CHECK (
    byte_length>0 AND CHAR_LENGTH(TRIM(mime_type))>0 AND CHAR_LENGTH(TRIM(staging_key))>0 AND CHAR_LENGTH(TRIM(planned_final_prefix))>0);
  ELSE
    SELECT LOWER(CHECK_CLAUSE) INTO shape_value FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='chk_binary_upload_integrity_v1';
    IF n<>1 OR shape_value NOT LIKE '%byte_length%' OR shape_value NOT LIKE '%mime_type%' OR shape_value NOT LIKE '%staging_key%' OR shape_value NOT LIKE '%planned_final_prefix%' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: chk_binary_upload_integrity_v1'; END IF;
  END IF;

  SELECT COUNT(*) INTO n
    FROM information_schema.TABLES
   WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_document_binaries';
  IF n=0 THEN
    SET @ddl='CREATE TABLE clinical_document_binaries (
      binary_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      binary_uuid CHAR(36) NOT NULL,
      document_id BIGINT UNSIGNED NOT NULL,
      variant_role VARCHAR(16) NOT NULL,
      variant_version SMALLINT UNSIGNED NOT NULL,
      storage_key VARCHAR(512) NOT NULL,
      sha256 CHAR(64) NOT NULL,
      byte_length BIGINT UNSIGNED NOT NULL,
      mime_type VARCHAR(100) NOT NULL,
      width_px INT UNSIGNED NULL,
      height_px INT UNSIGNED NULL,
      source_filename VARCHAR(255) NULL,
      created_at DATETIME NOT NULL,
      finalized_at DATETIME NOT NULL,
      PRIMARY KEY (binary_id),
      UNIQUE KEY uq_document_binary_uuid (binary_uuid),
      UNIQUE KEY uq_document_binary_storage_key (storage_key),
      UNIQUE KEY uq_document_binary_variant (document_id,variant_role,variant_version),
      CONSTRAINT chk_document_binary_role_v1 CHECK (variant_role IN (''ORIGINAL'',''DISPLAY'',''THUMBNAIL'')),
      CONSTRAINT chk_document_binary_hash_v1 CHECK (CHAR_LENGTH(sha256)=64 AND sha256 REGEXP ''^[0-9A-Fa-f]{64}$''),
      CONSTRAINT chk_document_binary_integrity_v1 CHECK (variant_version>0 AND byte_length>0 AND CHAR_LENGTH(TRIM(mime_type))>0 AND CHAR_LENGTH(TRIM(storage_key))>0),
      CONSTRAINT chk_document_binary_dimensions_v1 CHECK ((width_px IS NULL AND height_px IS NULL) OR (width_px IS NOT NULL AND height_px IS NOT NULL AND width_px>0 AND height_px>0)),
      CONSTRAINT fk_document_binary_document FOREIGN KEY (document_id)
        REFERENCES clinical_documents(id) ON UPDATE RESTRICT ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    PREPARE s FROM @ddl; EXECUTE s; DEALLOCATE PREPARE s;
  ELSEIF n<>1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: clinical_document_binaries';
  ELSE
    SELECT COUNT(*), SUM(CASE WHEN
      (COLUMN_NAME='binary_id' AND COLUMN_TYPE='bigint unsigned' AND IS_NULLABLE='NO' AND EXTRA='auto_increment') OR
      (COLUMN_NAME='binary_uuid' AND COLUMN_TYPE='char(36)' AND IS_NULLABLE='NO' AND EXTRA='') OR
      (COLUMN_NAME='document_id' AND COLUMN_TYPE='bigint unsigned' AND IS_NULLABLE='NO' AND EXTRA='') OR
      (COLUMN_NAME='variant_role' AND COLUMN_TYPE='varchar(16)' AND IS_NULLABLE='NO' AND EXTRA='') OR
      (COLUMN_NAME='variant_version' AND COLUMN_TYPE='smallint unsigned' AND IS_NULLABLE='NO' AND EXTRA='') OR
      (COLUMN_NAME='storage_key' AND COLUMN_TYPE='varchar(512)' AND IS_NULLABLE='NO' AND EXTRA='') OR
      (COLUMN_NAME='sha256' AND COLUMN_TYPE='char(64)' AND IS_NULLABLE='NO' AND EXTRA='') OR
      (COLUMN_NAME='byte_length' AND COLUMN_TYPE='bigint unsigned' AND IS_NULLABLE='NO' AND EXTRA='') OR
      (COLUMN_NAME='mime_type' AND COLUMN_TYPE='varchar(100)' AND IS_NULLABLE='NO' AND EXTRA='') OR
      (COLUMN_NAME='width_px' AND COLUMN_TYPE='int unsigned' AND IS_NULLABLE='YES' AND EXTRA='') OR
      (COLUMN_NAME='height_px' AND COLUMN_TYPE='int unsigned' AND IS_NULLABLE='YES' AND EXTRA='') OR
      (COLUMN_NAME='source_filename' AND COLUMN_TYPE='varchar(255)' AND IS_NULLABLE='YES' AND EXTRA='') OR
      (COLUMN_NAME='created_at' AND COLUMN_TYPE='datetime' AND IS_NULLABLE='NO' AND EXTRA='') OR
      (COLUMN_NAME='finalized_at' AND COLUMN_TYPE='datetime' AND IS_NULLABLE='NO' AND EXTRA='')
      THEN 1 ELSE 0 END) INTO n,valid_n
      FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_document_binaries';
    IF n<>14 OR valid_n<>14 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: clinical_document_binaries columns';
    END IF;
  END IF;

  SELECT COUNT(DISTINCT INDEX_NAME),CONCAT(MAX(NON_UNIQUE),'|',GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX)) INTO n,shape_value
    FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_document_binaries' AND INDEX_NAME='PRIMARY';
  IF n=0 THEN ALTER TABLE clinical_document_binaries ADD PRIMARY KEY (binary_id);
  ELSEIF n<>1 OR shape_value<>'0|binary_id' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: clinical_document_binaries primary key'; END IF;

  SELECT COUNT(DISTINCT INDEX_NAME),CONCAT(MAX(NON_UNIQUE),'|',GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX)) INTO n,shape_value
    FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_document_binaries' AND INDEX_NAME='uq_document_binary_uuid';
  IF n=0 THEN ALTER TABLE clinical_document_binaries ADD UNIQUE KEY uq_document_binary_uuid (binary_uuid);
  ELSEIF n<>1 OR shape_value<>'0|binary_uuid' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: uq_document_binary_uuid'; END IF;

  SELECT COUNT(DISTINCT INDEX_NAME),CONCAT(MAX(NON_UNIQUE),'|',GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX)) INTO n,shape_value
    FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_document_binaries' AND INDEX_NAME='uq_document_binary_storage_key';
  IF n=0 THEN ALTER TABLE clinical_document_binaries ADD UNIQUE KEY uq_document_binary_storage_key (storage_key);
  ELSEIF n<>1 OR shape_value<>'0|storage_key' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: uq_document_binary_storage_key'; END IF;

  SELECT COUNT(DISTINCT INDEX_NAME),CONCAT(MAX(NON_UNIQUE),'|',GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX)) INTO n,shape_value
    FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_document_binaries' AND INDEX_NAME='uq_document_binary_variant';
  IF n=0 THEN ALTER TABLE clinical_document_binaries ADD UNIQUE KEY uq_document_binary_variant (document_id,variant_role,variant_version);
  ELSEIF n<>1 OR shape_value<>'0|document_id,variant_role,variant_version' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: uq_document_binary_variant'; END IF;

  SELECT COUNT(*) INTO n FROM information_schema.REFERENTIAL_CONSTRAINTS
   WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='clinical_document_binaries' AND CONSTRAINT_NAME='fk_document_binary_document';
  IF n=0 THEN ALTER TABLE clinical_document_binaries ADD CONSTRAINT fk_document_binary_document FOREIGN KEY (document_id) REFERENCES clinical_documents(id) ON UPDATE RESTRICT ON DELETE RESTRICT;
  ELSE
    SELECT CONCAT(k.COLUMN_NAME,'|',k.REFERENCED_TABLE_NAME,'|',k.REFERENCED_COLUMN_NAME,'|',r.UPDATE_RULE,'|',r.DELETE_RULE) INTO shape_value
      FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r
        ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME
     WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.TABLE_NAME='clinical_document_binaries' AND k.CONSTRAINT_NAME='fk_document_binary_document';
    IF n<>1 OR shape_value<>'document_id|clinical_documents|id|RESTRICT|RESTRICT' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: fk_document_binary_document'; END IF;
  END IF;

  SELECT COUNT(*) INTO n FROM information_schema.TABLE_CONSTRAINTS
   WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='clinical_document_binaries' AND CONSTRAINT_NAME='chk_document_binary_role_v1' AND CONSTRAINT_TYPE='CHECK';
  IF n=0 THEN ALTER TABLE clinical_document_binaries ADD CONSTRAINT chk_document_binary_role_v1 CHECK (variant_role IN ('ORIGINAL','DISPLAY','THUMBNAIL'));
  ELSE
    SELECT LOWER(CHECK_CLAUSE) INTO shape_value FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='chk_document_binary_role_v1';
    IF n<>1 OR shape_value NOT LIKE '%original%' OR shape_value NOT LIKE '%display%' OR shape_value NOT LIKE '%thumbnail%' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: chk_document_binary_role_v1'; END IF;
  END IF;

  SELECT COUNT(*) INTO n FROM information_schema.TABLE_CONSTRAINTS
   WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='clinical_document_binaries' AND CONSTRAINT_NAME='chk_document_binary_hash_v1' AND CONSTRAINT_TYPE='CHECK';
  IF n=0 THEN ALTER TABLE clinical_document_binaries ADD CONSTRAINT chk_document_binary_hash_v1 CHECK (CHAR_LENGTH(sha256)=64 AND sha256 REGEXP '^[0-9A-Fa-f]{64}$');
  ELSE
    SELECT LOWER(CHECK_CLAUSE) INTO shape_value FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='chk_document_binary_hash_v1';
    IF n<>1 OR shape_value NOT LIKE '%sha256%' OR shape_value NOT LIKE '%regexp%' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: chk_document_binary_hash_v1'; END IF;
  END IF;

  SELECT COUNT(*) INTO n FROM information_schema.TABLE_CONSTRAINTS
   WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='clinical_document_binaries' AND CONSTRAINT_NAME='chk_document_binary_integrity_v1' AND CONSTRAINT_TYPE='CHECK';
  IF n=0 THEN ALTER TABLE clinical_document_binaries ADD CONSTRAINT chk_document_binary_integrity_v1 CHECK (variant_version>0 AND byte_length>0 AND CHAR_LENGTH(TRIM(mime_type))>0 AND CHAR_LENGTH(TRIM(storage_key))>0);
  ELSE
    SELECT LOWER(CHECK_CLAUSE) INTO shape_value FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='chk_document_binary_integrity_v1';
    IF n<>1 OR shape_value NOT LIKE '%variant_version%' OR shape_value NOT LIKE '%byte_length%' OR shape_value NOT LIKE '%mime_type%' OR shape_value NOT LIKE '%storage_key%' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: chk_document_binary_integrity_v1'; END IF;
  END IF;

  SELECT COUNT(*) INTO n FROM information_schema.TABLE_CONSTRAINTS
   WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='clinical_document_binaries' AND CONSTRAINT_NAME='chk_document_binary_dimensions_v1' AND CONSTRAINT_TYPE='CHECK';
  IF n=0 THEN ALTER TABLE clinical_document_binaries ADD CONSTRAINT chk_document_binary_dimensions_v1 CHECK ((width_px IS NULL AND height_px IS NULL) OR (width_px IS NOT NULL AND height_px IS NOT NULL AND width_px>0 AND height_px>0));
  ELSE
    SELECT LOWER(CHECK_CLAUSE) INTO shape_value FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='chk_document_binary_dimensions_v1';
    IF n<>1 OR shape_value NOT LIKE '%width_px%' OR shape_value NOT LIKE '%height_px%' OR shape_value NOT LIKE '%is null%' OR shape_value NOT LIKE '%is not null%' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MIGRATION_DRIFT: chk_document_binary_dimensions_v1'; END IF;
  END IF;
END$$
CALL mxmed_migrate_clinical_binary_storage_v1()$$
DROP PROCEDURE mxmed_migrate_clinical_binary_storage_v1$$
DELIMITER ;
