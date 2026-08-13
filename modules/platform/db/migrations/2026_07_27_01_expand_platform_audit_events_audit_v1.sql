-- AUDIT-MP01B B1 repository promotion derived from certified R45 + R54; not executed by preparation
-- R43 SECTION 1: BOOTSTRAP
-- R46_LINEAGE: canonical temporary-table folds were runtime-validated as COUNT -> recursive fold -> final accumulator -> final SHA.
-- These repository cursor folds are statement-separated and never reopen a temporary relation in one statement.
SET @r43_execution_state='BOOTSTRAP_TEMP_TABLES';
CREATE TEMPORARY TABLE IF NOT EXISTS audit_mp01b_show_grants_capture(principal VARCHAR(512),grant_text LONGTEXT);
CREATE TEMPORARY TABLE IF NOT EXISTS audit_mp01b_privilege_inventory(principal VARCHAR(255),source_kind VARCHAR(32),privilege_name VARCHAR(128),object_name VARCHAR(512),allowed TINYINT);
CREATE TEMPORARY TABLE IF NOT EXISTS audit_mp01b_effective_privileges(principal VARCHAR(512),privilege_name VARCHAR(128),object_type VARCHAR(32),object_schema VARCHAR(128),object_name VARCHAR(512),allowed TINYINT,source_grant LONGTEXT,source_role_path VARCHAR(8192));
CREATE TEMPORARY TABLE IF NOT EXISTS audit_mp01b_required_privileges(privilege_name VARCHAR(128),object_type VARCHAR(32),object_schema VARCHAR(128),object_name VARCHAR(512),principal_scope VARCHAR(255),required_or_prohibited VARCHAR(16),reason VARCHAR(255));
CREATE TEMPORARY TABLE IF NOT EXISTS audit_mp01b_prohibited_privileges(privilege_name VARCHAR(128),object_type VARCHAR(32),object_schema VARCHAR(128),object_name VARCHAR(512),principal_scope VARCHAR(255),required_or_prohibited VARCHAR(16),reason VARCHAR(255));
INSERT INTO audit_mp01b_required_privileges VALUES
('CREATE','SCHEMA',DATABASE(),'*','CURRENT_USER','required','create additive audit objects'),
('ALTER','TABLE',DATABASE(),'platform_audit_events_audit_v1_shadow','CURRENT_USER','required','add canonical identity'),
('INSERT','TABLE',DATABASE(),'platform_audit_events_audit_v1_shadow','CURRENT_USER','required','backfill shadow only'),
('INSERT','TABLE',DATABASE(),'platform_audit_stream_heads','CURRENT_USER','required','build stream heads'),
('SELECT','TABLE',DATABASE(),'platform_audit_events','CURRENT_USER','required','read legacy source'),
('SELECT','TABLE',DATABASE(),'platform_audit_events_audit_v1_shadow','CURRENT_USER','required','verify shadow'),
('SELECT','TABLE',DATABASE(),'platform_audit_stream_heads','CURRENT_USER','required','verify heads'),
('SELECT','SCHEMA','information_schema','*','CURRENT_USER','required','inspect metadata'),
('SELECT','TABLE','mysql','role_edges','CURRENT_USER','required','compute recursive role closure'),
('TRIGGER','TABLE',DATABASE(),'platform_audit_events','CURRENT_USER','required','restore append-only guards'),
('TRIGGER','TABLE',DATABASE(),'platform_audit_events_audit_v1_shadow','CURRENT_USER','required','guard shadow'),
('CREATE TEMPORARY TABLES','SCHEMA',DATABASE(),'*','CURRENT_USER','required','session evidence tables'),
('EXECUTE','ROUTINE',DATABASE(),'audit_mp01b_%','CURRENT_USER','required','invoke phase procedures');
INSERT INTO audit_mp01b_prohibited_privileges VALUES
('UPDATE','TABLE',DATABASE(),'platform_audit_events','CURRENT_USER','prohibited','legacy source immutable'),
('DELETE','TABLE',DATABASE(),'platform_audit_events','CURRENT_USER','prohibited','legacy source append-only'),
('DROP','TABLE',DATABASE(),'platform_audit_events','CURRENT_USER','prohibited','legacy source retained');
-- R43 SECTION 2: HARNESS INJECTION BOUNDARY
SET @r43_execution_state='WAITING_FOR_HARNESS_INPUT';
-- The harness injects route, normalized principal, SHOW GRANTS rows, privilege rows,
-- expected counts and expected SHA-256 values here in this same connection/session.
DROP PROCEDURE IF EXISTS audit_mp01b_fold_show_grants_capture;
DELIMITER $$
CREATE PROCEDURE audit_mp01b_fold_show_grants_capture(OUT p_count BIGINT,OUT p_sha CHAR(64))
BEGIN
 DECLARE done INT DEFAULT 0; DECLARE v_principal LONGTEXT; DECLARE v_grant LONGTEXT;
 DECLARE cur CURSOR FOR SELECT principal,grant_text FROM audit_mp01b_show_grants_capture ORDER BY principal,grant_text;
 DECLARE CONTINUE HANDLER FOR NOT FOUND SET done=1;
 SET p_count=0; SET p_sha=LOWER(SHA2('mxmed.audit.mp01b.r43.show-grants-capture.v1',256));
 OPEN cur;
 capture_loop: LOOP
  FETCH cur INTO v_principal,v_grant; IF done=1 THEN LEAVE capture_loop; END IF;
  SET p_sha=LOWER(SHA2(CONCAT(
    'mxmed.audit.mp01b.r43.show-grants-row.v1|',
    OCTET_LENGTH(p_sha),':',p_sha,'|',
    OCTET_LENGTH(v_principal),':',v_principal,'|',
    OCTET_LENGTH(v_grant),':',v_grant),256));
  SET p_count=p_count+1;
 END LOOP;
 CLOSE cur;
 SET p_sha=LOWER(SHA2(CONCAT('mxmed.audit.mp01b.r43.show-grants-final.v1|',p_count,'|',OCTET_LENGTH(p_sha),':',p_sha),256));
END$$
DELIMITER ;
DROP PROCEDURE IF EXISTS audit_mp01b_fold_privilege_inventory;
DELIMITER $$
CREATE PROCEDURE audit_mp01b_fold_privilege_inventory(OUT p_count BIGINT,OUT p_sha CHAR(64))
BEGIN
 DECLARE done INT DEFAULT 0; DECLARE v_principal LONGTEXT; DECLARE v_kind LONGTEXT;
 DECLARE v_privilege LONGTEXT; DECLARE v_object LONGTEXT; DECLARE v_allowed BIGINT;
 DECLARE cur CURSOR FOR SELECT principal,source_kind,privilege_name,object_name,allowed
   FROM audit_mp01b_privilege_inventory ORDER BY principal,source_kind,privilege_name,object_name,allowed;
 DECLARE CONTINUE HANDLER FOR NOT FOUND SET done=1;
 SET p_count=0; SET p_sha=LOWER(SHA2('mxmed.audit.mp01b.r43.privilege-inventory.v1',256));
 OPEN cur;
 capture_loop: LOOP
  FETCH cur INTO v_principal,v_kind,v_privilege,v_object,v_allowed; IF done=1 THEN LEAVE capture_loop; END IF;
  SET p_sha=LOWER(SHA2(CONCAT(
    'mxmed.audit.mp01b.r43.privilege-row.v1|',
    OCTET_LENGTH(p_sha),':',p_sha,'|',
    OCTET_LENGTH(v_principal),':',v_principal,'|',
    OCTET_LENGTH(v_kind),':',v_kind,'|',
    OCTET_LENGTH(v_privilege),':',v_privilege,'|',
    OCTET_LENGTH(v_object),':',v_object,'|I',v_allowed,';'),256));
  SET p_count=p_count+1;
 END LOOP;
 CLOSE cur;
 SET p_sha=LOWER(SHA2(CONCAT('mxmed.audit.mp01b.r43.privilege-final.v1|',p_count,'|',OCTET_LENGTH(p_sha),':',p_sha),256));
END$$
DELIMITER ;
-- R43 SECTION 3: INPUT VALIDATION
DROP PROCEDURE IF EXISTS audit_mp01b_validate_execution_inputs;
DELIMITER $$
CREATE PROCEDURE audit_mp01b_validate_execution_inputs()
BEGIN
 SET @r43_runtime_principal=LOWER(CAST(CURRENT_USER() AS CHAR(512)));
 IF @audit_mp01b_route IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='audit route input is null'; END IF;
 IF @audit_mp01b_route NOT IN ('EMPTY','POPULATED') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='audit route input invalid'; END IF;
 IF COALESCE(@r43_inputs_ready,0)<>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='harness inputs not ready'; END IF;
 IF @r43_expected_runtime_principal IS NULL OR @r43_runtime_principal<>LOWER(CAST(@r43_expected_runtime_principal AS CHAR(512))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='runtime principal mismatch'; END IF;
 CALL audit_mp01b_fold_show_grants_capture(@r43_show_grants_capture_count,@r43_show_grants_capture_sha256);
 CALL audit_mp01b_fold_privilege_inventory(@r43_privilege_inventory_count,@r43_privilege_inventory_sha256);
 IF @r43_show_grants_capture_count<1 OR @r43_show_grants_capture_count<>@r43_expected_show_grants_capture_count OR @r43_show_grants_capture_sha256<>@r43_expected_show_grants_capture_sha256 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='show grants capture relation mismatch'; END IF;
 IF @r43_privilege_inventory_count<1 OR @r43_privilege_inventory_count<>@r43_expected_privilege_inventory_count OR @r43_privilege_inventory_sha256<>@r43_expected_privilege_inventory_sha256 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='privilege inventory relation mismatch'; END IF;
 SET @r43_inputs_ready=2;
 SET @r43_execution_state='INPUTS_VALIDATED';
END$$
DELIMITER ;
CALL audit_mp01b_validate_execution_inputs();
SET @r43_show_grants_capture_complete=0;
SET @r43_privilege_inventory_complete=0;
DROP PROCEDURE IF EXISTS audit_mp01b_preflight_authority;
DELIMITER $$
CREATE PROCEDURE audit_mp01b_preflight_authority()
BEGIN
 SELECT CASE WHEN COUNT(*)>0 THEN 1 ELSE 0 END INTO @r43_show_grants_capture_complete FROM audit_mp01b_show_grants_capture WHERE LOWER(CAST(principal AS CHAR(512)))=@r43_runtime_principal;
 SELECT CASE WHEN NOT EXISTS (
   SELECT 1 FROM audit_mp01b_required_privileges rp
   LEFT JOIN audit_mp01b_effective_privileges pi
     ON LOWER(CAST(pi.principal AS CHAR(512)))=@r43_runtime_principal AND pi.privilege_name=rp.privilege_name
    AND pi.object_name=CONCAT(rp.object_schema,'.',rp.object_name) AND pi.allowed=1
   WHERE rp.principal_scope='CURRENT_USER' AND pi.privilege_name IS NULL
 ) AND NOT EXISTS (
   SELECT 1 FROM audit_mp01b_prohibited_privileges pp
   JOIN audit_mp01b_effective_privileges pi
     ON LOWER(CAST(pi.principal AS CHAR(512)))=@r43_runtime_principal AND pi.privilege_name=pp.privilege_name
    AND pi.object_name=CONCAT(pp.object_schema,'.',pp.object_name) AND pi.allowed=1
   WHERE pp.principal_scope='CURRENT_USER'
 ) THEN 1 ELSE 0 END INTO @r43_privilege_inventory_complete;
 IF COALESCE(@r43_show_grants_capture_complete,0)<>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='show grants preflight unconfirmed'; END IF;
 IF COALESCE(@r43_privilege_inventory_complete,0)<>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='privilege inventory preflight unconfirmed'; END IF;
 IF COALESCE(CURRENT_USER(),'')='' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='runtime principal missing'; END IF;
END$$
DELIMITER ;
-- R43 SECTION 4: PREFLIGHT
CALL audit_mp01b_preflight_authority();
SET @r43_execution_state='PREFLIGHT_PASS';
-- R43 SECTION 5: EXECUTION B1-B5
CREATE TABLE IF NOT EXISTS platform_audit_events_audit_v1_shadow LIKE platform_audit_events;
-- R47_BEGIN_MYSQL84_IDEMPOTENT_CANONICAL_EVENT_ID
SELECT COUNT(*)
INTO @r47_canonical_event_id_exists
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND table_name = 'platform_audit_events_audit_v1_shadow'
  AND column_name = 'canonical_event_id';
SET @r47_add_canonical_event_id_sql =
  IF(
    @r47_canonical_event_id_exists = 0,
    'ALTER TABLE `platform_audit_events_audit_v1_shadow` ADD COLUMN `canonical_event_id` VARCHAR(128) NULL',
    'SELECT 1'
  );
PREPARE r47_add_canonical_event_id_stmt FROM @r47_add_canonical_event_id_sql;
EXECUTE r47_add_canonical_event_id_stmt;
DEALLOCATE PREPARE r47_add_canonical_event_id_stmt;
-- R47_END_MYSQL84_IDEMPOTENT_CANONICAL_EVENT_ID
CREATE TABLE IF NOT EXISTS platform_audit_stream_heads(stream_key VARCHAR(191) PRIMARY KEY,last_sequence_number BIGINT NOT NULL,last_event_hash CHAR(64) NOT NULL);
CREATE TEMPORARY TABLE audit_mp01b_phase_receipts(route VARCHAR(16),phase VARCHAR(2),state VARCHAR(32),payload_canonical LONGBLOB,payload_base64 LONGTEXT,previous_receipt_sha256 CHAR(64),receipt_sha256 CHAR(64),PRIMARY KEY(route,phase,state),UNIQUE KEY uq_receipt_sha(receipt_sha256));
CREATE TEMPORARY TABLE audit_mp01b_integrity_counters(route VARCHAR(16),phase VARCHAR(2),state VARCHAR(32),counter_name VARCHAR(96),counter_type VARCHAR(16),counter_value LONGTEXT,PRIMARY KEY(route,phase,state,counter_name));
CREATE TEMPORARY TABLE audit_mp01b_database_identity_capture(component VARCHAR(32),value_text LONGTEXT);
INSERT INTO audit_mp01b_database_identity_capture VALUES('database',DATABASE()),('hostname',@@hostname),('port',CAST(@@port AS CHAR)),('server_uuid',@@server_uuid),('version',VERSION()),('principal',CURRENT_USER());
SET @previous_receipt_sha256_empty=REPEAT('0',64);
SET @previous_receipt_sha256_populated=REPEAT('0',64);
DROP TRIGGER IF EXISTS platform_audit_events_no_update;
DELIMITER $$
CREATE TRIGGER platform_audit_events_no_update BEFORE UPDATE ON platform_audit_events FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_event_update_forbidden';
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS platform_audit_events_no_delete;
DELIMITER $$
CREATE TRIGGER platform_audit_events_no_delete BEFORE DELETE ON platform_audit_events FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_event_delete_forbidden';
END$$
DELIMITER ;
DROP PROCEDURE IF EXISTS audit_mp01b_assert_route;
DELIMITER $$
CREATE PROCEDURE audit_mp01b_assert_route(IN p_route VARCHAR(16))
BEGIN
 IF p_route IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='audit route is null'; END IF;
 IF p_route NOT IN ('EMPTY','POPULATED') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='invalid audit route'; END IF;
END$$
DELIMITER ;




DROP PROCEDURE IF EXISTS audit_mp01b_fold_source;
DELIMITER $$
CREATE PROCEDURE audit_mp01b_fold_source(OUT p_hash CHAR(64))
BEGIN
 DECLARE done INT DEFAULT 0;
 DECLARE v_stream_key LONGTEXT;
DECLARE v_sequence_number LONGTEXT;
DECLARE v_event_hash LONGTEXT;
 DECLARE cur CURSOR FOR SELECT stream_key,sequence_number,event_hash FROM platform_audit_events  ORDER BY stream_key ASC,sequence_number ASC;
 DECLARE CONTINUE HANDLER FOR NOT FOUND SET done=1;
 SET p_hash=SHA2('mxmed.audit.mp01b.source.v1',256);
 OPEN cur;
 read_loop: LOOP
  FETCH cur INTO v_stream_key,v_sequence_number,v_event_hash;
  IF done=1 THEN LEAVE read_loop; END IF;
  SET p_hash=SHA2(CONCAT(p_hash,'|',COALESCE(v_stream_key,'<NULL>'),COALESCE(v_sequence_number,'<NULL>'),COALESCE(v_event_hash,'<NULL>')),256);
 END LOOP;
 CLOSE cur;
END$$
DELIMITER ;
DROP PROCEDURE IF EXISTS audit_mp01b_fold_shadow;
DELIMITER $$
CREATE PROCEDURE audit_mp01b_fold_shadow(OUT p_hash CHAR(64))
BEGIN
 DECLARE done INT DEFAULT 0;
 DECLARE v_stream_key LONGTEXT;
DECLARE v_sequence_number LONGTEXT;
DECLARE v_event_hash LONGTEXT;
 DECLARE cur CURSOR FOR SELECT stream_key,sequence_number,event_hash FROM platform_audit_events_audit_v1_shadow  ORDER BY stream_key ASC,sequence_number ASC;
 DECLARE CONTINUE HANDLER FOR NOT FOUND SET done=1;
 SET p_hash=SHA2('mxmed.audit.mp01b.shadow.v1',256);
 OPEN cur;
 read_loop: LOOP
  FETCH cur INTO v_stream_key,v_sequence_number,v_event_hash;
  IF done=1 THEN LEAVE read_loop; END IF;
  SET p_hash=SHA2(CONCAT(p_hash,'|',COALESCE(v_stream_key,'<NULL>'),COALESCE(v_sequence_number,'<NULL>'),COALESCE(v_event_hash,'<NULL>')),256);
 END LOOP;
 CLOSE cur;
END$$
DELIMITER ;
DROP PROCEDURE IF EXISTS audit_mp01b_fold_preserved_event_hash;
DELIMITER $$
CREATE PROCEDURE audit_mp01b_fold_preserved_event_hash(OUT p_hash CHAR(64))
BEGIN
 DECLARE done INT DEFAULT 0;
 DECLARE v_stream_key LONGTEXT;
DECLARE v_sequence_number LONGTEXT;
DECLARE v_event_hash LONGTEXT;
 DECLARE cur CURSOR FOR SELECT stream_key,sequence_number,event_hash FROM platform_audit_events  ORDER BY stream_key ASC,sequence_number ASC;
 DECLARE CONTINUE HANDLER FOR NOT FOUND SET done=1;
 SET p_hash=SHA2('mxmed.audit.mp01b.preserved-event-hash.v1',256);
 OPEN cur;
 read_loop: LOOP
  FETCH cur INTO v_stream_key,v_sequence_number,v_event_hash;
  IF done=1 THEN LEAVE read_loop; END IF;
  SET p_hash=SHA2(CONCAT(p_hash,'|',COALESCE(v_stream_key,'<NULL>'),COALESCE(v_sequence_number,'<NULL>'),COALESCE(v_event_hash,'<NULL>')),256);
 END LOOP;
 CLOSE cur;
END$$
DELIMITER ;
DROP PROCEDURE IF EXISTS audit_mp01b_fold_source_triggers;
DELIMITER $$
CREATE PROCEDURE audit_mp01b_fold_source_triggers(OUT p_hash CHAR(64))
BEGIN
 DECLARE done INT DEFAULT 0;
 DECLARE v_trigger_name LONGTEXT;
DECLARE v_event_manipulation LONGTEXT;
DECLARE v_action_timing LONGTEXT;
DECLARE v_action_statement LONGTEXT;
 DECLARE cur CURSOR FOR SELECT trigger_name,event_manipulation,action_timing,action_statement FROM information_schema.triggers WHERE trigger_schema=DATABASE() AND event_object_table='platform_audit_events' AND event_manipulation IN ('UPDATE','DELETE') AND action_timing='BEFORE' ORDER BY trigger_name ASC;
 DECLARE CONTINUE HANDLER FOR NOT FOUND SET done=1;
 SET p_hash=SHA2('mxmed.audit.mp01b.source-triggers.v1',256);
 OPEN cur;
 read_loop: LOOP
  FETCH cur INTO v_trigger_name,v_event_manipulation,v_action_timing,v_action_statement;
  IF done=1 THEN LEAVE read_loop; END IF;
  SET p_hash=SHA2(CONCAT(p_hash,'|',COALESCE(v_trigger_name,'<NULL>'),COALESCE(v_event_manipulation,'<NULL>'),COALESCE(v_action_timing,'<NULL>'),COALESCE(v_action_statement,'<NULL>')),256);
 END LOOP;
 CLOSE cur;
END$$
DELIMITER ;
DROP PROCEDURE IF EXISTS audit_mp01b_fold_shadow_triggers;
DELIMITER $$
CREATE PROCEDURE audit_mp01b_fold_shadow_triggers(OUT p_hash CHAR(64))
BEGIN
 DECLARE done INT DEFAULT 0;
 DECLARE v_trigger_name LONGTEXT;
DECLARE v_event_manipulation LONGTEXT;
DECLARE v_action_timing LONGTEXT;
DECLARE v_action_statement LONGTEXT;
 DECLARE cur CURSOR FOR SELECT trigger_name,event_manipulation,action_timing,action_statement FROM information_schema.triggers WHERE trigger_schema=DATABASE() AND event_object_table='platform_audit_events_audit_v1_shadow' AND event_manipulation IN ('UPDATE','DELETE') AND action_timing='BEFORE' ORDER BY trigger_name ASC;
 DECLARE CONTINUE HANDLER FOR NOT FOUND SET done=1;
 SET p_hash=SHA2('mxmed.audit.mp01b.shadow-triggers.v1',256);
 OPEN cur;
 read_loop: LOOP
  FETCH cur INTO v_trigger_name,v_event_manipulation,v_action_timing,v_action_statement;
  IF done=1 THEN LEAVE read_loop; END IF;
  SET p_hash=SHA2(CONCAT(p_hash,'|',COALESCE(v_trigger_name,'<NULL>'),COALESCE(v_event_manipulation,'<NULL>'),COALESCE(v_action_timing,'<NULL>'),COALESCE(v_action_statement,'<NULL>')),256);
 END LOOP;
 CLOSE cur;
END$$
DELIMITER ;
DROP PROCEDURE IF EXISTS audit_mp01b_fold_privileges;
DELIMITER $$
CREATE PROCEDURE audit_mp01b_fold_privileges(OUT p_hash CHAR(64))
BEGIN
 DECLARE done INT DEFAULT 0;
 DECLARE v_principal LONGTEXT;
DECLARE v_source_kind LONGTEXT;
DECLARE v_privilege_name LONGTEXT;
DECLARE v_object_name LONGTEXT;
DECLARE v_allowed LONGTEXT;
 DECLARE cur CURSOR FOR SELECT principal,source_kind,privilege_name,object_name,allowed FROM audit_mp01b_privilege_inventory WHERE principal=CURRENT_USER() ORDER BY source_kind ASC,privilege_name ASC,object_name ASC;
 DECLARE CONTINUE HANDLER FOR NOT FOUND SET done=1;
 SET p_hash=SHA2('mxmed.audit.mp01b.privileges.v1',256);
 OPEN cur;
 read_loop: LOOP
  FETCH cur INTO v_principal,v_source_kind,v_privilege_name,v_object_name,v_allowed;
  IF done=1 THEN LEAVE read_loop; END IF;
  SET p_hash=SHA2(CONCAT(p_hash,'|',COALESCE(v_principal,'<NULL>'),COALESCE(v_source_kind,'<NULL>'),COALESCE(v_privilege_name,'<NULL>'),COALESCE(v_object_name,'<NULL>'),COALESCE(v_allowed,'<NULL>')),256);
 END LOOP;
 CLOSE cur;
END$$
DELIMITER ;
DROP PROCEDURE IF EXISTS audit_mp01b_fold_database_identity;
DELIMITER $$
CREATE PROCEDURE audit_mp01b_fold_database_identity(OUT p_hash CHAR(64))
BEGIN
 DECLARE done INT DEFAULT 0;
 DECLARE v_component LONGTEXT;
DECLARE v_value_text LONGTEXT;
 DECLARE cur CURSOR FOR SELECT component,value_text FROM audit_mp01b_database_identity_capture  ORDER BY component ASC;
 DECLARE CONTINUE HANDLER FOR NOT FOUND SET done=1;
 SET p_hash=SHA2('mxmed.audit.mp01b.database-identity.v1',256);
 OPEN cur;
 read_loop: LOOP
  FETCH cur INTO v_component,v_value_text;
  IF done=1 THEN LEAVE read_loop; END IF;
  SET p_hash=SHA2(CONCAT(p_hash,'|',COALESCE(v_component,'<NULL>'),COALESCE(v_value_text,'<NULL>')),256);
 END LOOP;
 CLOSE cur;
END$$
DELIMITER ;
DROP PROCEDURE IF EXISTS audit_mp01b_emit_b1;
DELIMITER $$
CREATE PROCEDURE audit_mp01b_emit_b1(IN p_route VARCHAR(16))
BEGIN
 CALL audit_mp01b_assert_route(p_route);
 IF p_route='EMPTY' THEN
  SET @r43__empty__b1__final__route=CAST('EMPTY' AS CHAR);
  SET @r43__empty__b1__final__phase=CAST('B1' AS CHAR);
  SET @r43__empty__b1__final__state=CAST('FINAL' AS CHAR);
  SELECT COUNT(*) INTO @r43__empty__b1__final__source_rows FROM platform_audit_events e;
  SELECT COUNT(DISTINCT e.stream_key) INTO @r43__empty__b1__final__source_streams FROM platform_audit_events e;
  CALL audit_mp01b_fold_source(@r43__empty__b1__final__source_fingerprint);
  CALL audit_mp01b_fold_source_triggers(@r43__empty__b1__final__source_trigger_fingerprint);
  SELECT COUNT(*) INTO @r43__empty__b1__final__head_rows FROM platform_audit_stream_heads h;
  SELECT COUNT(DISTINCT h.stream_key) INTO @r43__empty__b1__final__head_streams FROM platform_audit_stream_heads h;
  SELECT COUNT(*) INTO @r43__empty__b1__final__shadow_rows FROM platform_audit_events_audit_v1_shadow s;
  SELECT COUNT(DISTINCT s.stream_key) INTO @r43__empty__b1__final__shadow_streams FROM platform_audit_events_audit_v1_shadow s;
  CALL audit_mp01b_fold_shadow(@r43__empty__b1__final__shadow_fingerprint);
  CALL audit_mp01b_fold_shadow_triggers(@r43__empty__b1__final__shadow_trigger_fingerprint);
-- R48_BEGIN_B1_EMPTY_TEMPORARY_METADATA
  SELECT (COUNT(*) >= 0) INTO @r48_empty_phase_receipts_present FROM audit_mp01b_phase_receipts;
  SELECT (COUNT(*) >= 0) INTO @r48_empty_integrity_counters_present FROM audit_mp01b_integrity_counters;
  SELECT (COUNT(*) >= 0) INTO @r48_empty_show_grants_present FROM audit_mp01b_show_grants_capture;
  SELECT (COUNT(*) >= 0) INTO @r48_empty_privilege_inventory_present FROM audit_mp01b_privilege_inventory;
  SELECT (COUNT(*) >= 0) INTO @r48_empty_database_identity_present FROM audit_mp01b_database_identity_capture;
  SELECT COUNT(DISTINCT i.table_name) INTO @r48_empty_persistent_created_objects FROM information_schema.tables i WHERE i.table_schema=DATABASE() AND i.table_name IN ('platform_audit_events_audit_v1_shadow','platform_audit_stream_heads');
  SET @r43__empty__b1__final__created_objects =
      COALESCE(@r48_empty_persistent_created_objects,0)
    + COALESCE(@r48_empty_phase_receipts_present,0)
    + COALESCE(@r48_empty_integrity_counters_present,0)
    + COALESCE(@r48_empty_show_grants_present,0)
    + COALESCE(@r48_empty_privilege_inventory_present,0)
    + COALESCE(@r48_empty_database_identity_present,0);
  SET @r43__empty__b1__final__support_objects =
      COALESCE(@r48_empty_persistent_created_objects,0)
    + COALESCE(@r48_empty_phase_receipts_present,0)
    + COALESCE(@r48_empty_integrity_counters_present,0)
    + COALESCE(@r48_empty_database_identity_present,0);
  IF @r43__empty__b1__final__support_objects < 5 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='EMPTY B1 support objects incomplete'; END IF;
  -- R48_END_B1_EMPTY_TEMPORARY_METADATA
  CALL audit_mp01b_fold_preserved_event_hash(@r43__empty__b1__final__preserved_event_hash_sha256);
  IF @r43__empty__b1__final__route IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B1 FINAL route'; END IF;
  IF @r43__empty__b1__final__phase IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B1 FINAL phase'; END IF;
  IF @r43__empty__b1__final__state IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B1 FINAL state'; END IF;
  IF @r43__empty__b1__final__source_rows IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B1 FINAL source_rows'; END IF;
  IF @r43__empty__b1__final__source_streams IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B1 FINAL source_streams'; END IF;
  IF @r43__empty__b1__final__source_fingerprint IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B1 FINAL source_fingerprint'; END IF;
  IF @r43__empty__b1__final__source_trigger_fingerprint IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B1 FINAL source_trigger_fingerprint'; END IF;
  IF @r43__empty__b1__final__head_rows IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B1 FINAL head_rows'; END IF;
  IF @r43__empty__b1__final__head_streams IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B1 FINAL head_streams'; END IF;
  IF @r43__empty__b1__final__shadow_rows IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B1 FINAL shadow_rows'; END IF;
  IF @r43__empty__b1__final__shadow_streams IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B1 FINAL shadow_streams'; END IF;
  IF @r43__empty__b1__final__shadow_fingerprint IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B1 FINAL shadow_fingerprint'; END IF;
  IF @r43__empty__b1__final__shadow_trigger_fingerprint IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B1 FINAL shadow_trigger_fingerprint'; END IF;
  IF @r43__empty__b1__final__created_objects IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B1 FINAL created_objects'; END IF;
  IF @r43__empty__b1__final__preserved_event_hash_sha256 IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B1 FINAL preserved_event_hash_sha256'; END IF;
  SET @r43_summary_canonical=CONCAT('O15;',CONCAT('F5:route',CONCAT('S',OCTET_LENGTH(CAST(@r43__empty__b1__final__route AS CHAR)),':',UPPER(HEX(CAST(@r43__empty__b1__final__route AS CHAR))))),CONCAT('F5:phase',CONCAT('S',OCTET_LENGTH(CAST(@r43__empty__b1__final__phase AS CHAR)),':',UPPER(HEX(CAST(@r43__empty__b1__final__phase AS CHAR))))),CONCAT('F5:state',CONCAT('S',OCTET_LENGTH(CAST(@r43__empty__b1__final__state AS CHAR)),':',UPPER(HEX(CAST(@r43__empty__b1__final__state AS CHAR))))),CONCAT('F11:source_rows',CONCAT('I',CAST(@r43__empty__b1__final__source_rows AS CHAR),';')),CONCAT('F14:source_streams',CONCAT('I',CAST(@r43__empty__b1__final__source_streams AS CHAR),';')),CONCAT('F18:source_fingerprint',CONCAT('S',OCTET_LENGTH(CAST(@r43__empty__b1__final__source_fingerprint AS CHAR)),':',UPPER(HEX(CAST(@r43__empty__b1__final__source_fingerprint AS CHAR))))),CONCAT('F26:source_trigger_fingerprint',CONCAT('S',OCTET_LENGTH(CAST(@r43__empty__b1__final__source_trigger_fingerprint AS CHAR)),':',UPPER(HEX(CAST(@r43__empty__b1__final__source_trigger_fingerprint AS CHAR))))),CONCAT('F9:head_rows',CONCAT('I',CAST(@r43__empty__b1__final__head_rows AS CHAR),';')),CONCAT('F12:head_streams',CONCAT('I',CAST(@r43__empty__b1__final__head_streams AS CHAR),';')),CONCAT('F11:shadow_rows',CONCAT('I',CAST(@r43__empty__b1__final__shadow_rows AS CHAR),';')),CONCAT('F14:shadow_streams',CONCAT('I',CAST(@r43__empty__b1__final__shadow_streams AS CHAR),';')),CONCAT('F18:shadow_fingerprint',CONCAT('S',OCTET_LENGTH(CAST(@r43__empty__b1__final__shadow_fingerprint AS CHAR)),':',UPPER(HEX(CAST(@r43__empty__b1__final__shadow_fingerprint AS CHAR))))),CONCAT('F26:shadow_trigger_fingerprint',CONCAT('S',OCTET_LENGTH(CAST(@r43__empty__b1__final__shadow_trigger_fingerprint AS CHAR)),':',UPPER(HEX(CAST(@r43__empty__b1__final__shadow_trigger_fingerprint AS CHAR))))),CONCAT('F15:created_objects',CONCAT('I',CAST(@r43__empty__b1__final__created_objects AS CHAR),';')),CONCAT('F27:preserved_event_hash_sha256',CONCAT('S',OCTET_LENGTH(CAST(@r43__empty__b1__final__preserved_event_hash_sha256 AS CHAR)),':',UPPER(HEX(CAST(@r43__empty__b1__final__preserved_event_hash_sha256 AS CHAR))))));
  SET @r43_summary_base64=REPLACE(TO_BASE64(@r43_summary_canonical),'\n','');
  SET @r43_field_order_sha256='02d88cd7c8556168d17d0fd21969dbc1eddc47e6cfe54c974f92dcf2d30f9711';
  SET @r43_payload_canonical=CONCAT('O7;',CONCAT('F6:schema',CONCAT('S',OCTET_LENGTH(CAST('mxmed.audit.mp01b.canonical.v1' AS CHAR)),':',UPPER(HEX(CAST('mxmed.audit.mp01b.canonical.v1' AS CHAR))))),CONCAT('F5:route',CONCAT('S',OCTET_LENGTH(CAST('EMPTY' AS CHAR)),':',UPPER(HEX(CAST('EMPTY' AS CHAR))))),CONCAT('F5:phase',CONCAT('S',OCTET_LENGTH(CAST('B1' AS CHAR)),':',UPPER(HEX(CAST('B1' AS CHAR))))),CONCAT('F5:state',CONCAT('S',OCTET_LENGTH(CAST('FINAL' AS CHAR)),':',UPPER(HEX(CAST('FINAL' AS CHAR))))),CONCAT('F18:field_order_sha256',CONCAT('S',OCTET_LENGTH(CAST(@r43_field_order_sha256 AS CHAR)),':',UPPER(HEX(CAST(@r43_field_order_sha256 AS CHAR))))),CONCAT('F7:summary',CONCAT('S',OCTET_LENGTH(CAST(@r43_summary_base64 AS CHAR)),':',UPPER(HEX(CAST(@r43_summary_base64 AS CHAR))))),CONCAT('F23:previous_receipt_sha256',CONCAT('S',OCTET_LENGTH(CAST(@previous_receipt_sha256_empty AS CHAR)),':',UPPER(HEX(CAST(@previous_receipt_sha256_empty AS CHAR))))));
  SET @r43_payload_base64=REPLACE(TO_BASE64(@r43_payload_canonical),'\n','');
  SET @r43_receipt_sha256=LOWER(SHA2(@r43_payload_canonical,256));
  CALL audit_mp01b_assert_b1_final();
  INSERT INTO audit_mp01b_phase_receipts(route,phase,state,payload_canonical,payload_base64,previous_receipt_sha256,receipt_sha256) VALUES('EMPTY','B1','FINAL',@r43_payload_canonical,@r43_payload_base64,@previous_receipt_sha256_empty,@r43_receipt_sha256);
  SET @previous_receipt_sha256_empty=@r43_receipt_sha256;
 ELSEIF p_route='POPULATED' THEN
  SET @r43__populated__b1__final__route=CAST('POPULATED' AS CHAR);
  SET @r43__populated__b1__final__phase=CAST('B1' AS CHAR);
  SET @r43__populated__b1__final__state=CAST('FINAL' AS CHAR);
  SELECT COUNT(*) INTO @r43__populated__b1__final__source_rows FROM platform_audit_events e;
  SELECT COUNT(DISTINCT e.stream_key) INTO @r43__populated__b1__final__source_streams FROM platform_audit_events e;
  CALL audit_mp01b_fold_source(@r43__populated__b1__final__source_fingerprint);
  CALL audit_mp01b_fold_source_triggers(@r43__populated__b1__final__source_trigger_fingerprint);
  SELECT COUNT(*) INTO @r43__populated__b1__final__head_rows FROM platform_audit_stream_heads h;
  SELECT COUNT(DISTINCT h.stream_key) INTO @r43__populated__b1__final__head_streams FROM platform_audit_stream_heads h;
  SELECT COUNT(*) INTO @r43__populated__b1__final__shadow_rows FROM platform_audit_events_audit_v1_shadow s;
  SELECT COUNT(DISTINCT s.stream_key) INTO @r43__populated__b1__final__shadow_streams FROM platform_audit_events_audit_v1_shadow s;
  CALL audit_mp01b_fold_shadow(@r43__populated__b1__final__shadow_fingerprint);
  CALL audit_mp01b_fold_shadow_triggers(@r43__populated__b1__final__shadow_trigger_fingerprint);
-- R48_BEGIN_B1_POPULATED_TEMPORARY_METADATA
  SELECT (COUNT(*) >= 0) INTO @r48_populated_phase_receipts_present FROM audit_mp01b_phase_receipts;
  SELECT (COUNT(*) >= 0) INTO @r48_populated_integrity_counters_present FROM audit_mp01b_integrity_counters;
  SELECT (COUNT(*) >= 0) INTO @r48_populated_show_grants_present FROM audit_mp01b_show_grants_capture;
  SELECT (COUNT(*) >= 0) INTO @r48_populated_privilege_inventory_present FROM audit_mp01b_privilege_inventory;
  SELECT (COUNT(*) >= 0) INTO @r48_populated_database_identity_present FROM audit_mp01b_database_identity_capture;
  SELECT COUNT(DISTINCT i.table_name) INTO @r48_populated_persistent_created_objects FROM information_schema.tables i WHERE i.table_schema=DATABASE() AND i.table_name IN ('platform_audit_events_audit_v1_shadow','platform_audit_stream_heads');
  SET @r43__populated__b1__final__created_objects =
      COALESCE(@r48_populated_persistent_created_objects,0)
    + COALESCE(@r48_populated_phase_receipts_present,0)
    + COALESCE(@r48_populated_integrity_counters_present,0)
    + COALESCE(@r48_populated_show_grants_present,0)
    + COALESCE(@r48_populated_privilege_inventory_present,0)
    + COALESCE(@r48_populated_database_identity_present,0);
  SET @r43__populated__b1__final__support_objects =
      COALESCE(@r48_populated_persistent_created_objects,0)
    + COALESCE(@r48_populated_phase_receipts_present,0)
    + COALESCE(@r48_populated_integrity_counters_present,0)
    + COALESCE(@r48_populated_database_identity_present,0);
  IF @r43__populated__b1__final__support_objects < 5 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='POPULATED B1 support objects incomplete'; END IF;
  -- R48_END_B1_POPULATED_TEMPORARY_METADATA
  CALL audit_mp01b_fold_preserved_event_hash(@r43__populated__b1__final__preserved_event_hash_sha256);
  IF @r43__populated__b1__final__route IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B1 FINAL route'; END IF;
  IF @r43__populated__b1__final__phase IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B1 FINAL phase'; END IF;
  IF @r43__populated__b1__final__state IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B1 FINAL state'; END IF;
  IF @r43__populated__b1__final__source_rows IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B1 FINAL source_rows'; END IF;
  IF @r43__populated__b1__final__source_streams IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B1 FINAL source_streams'; END IF;
  IF @r43__populated__b1__final__source_fingerprint IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B1 FINAL source_fingerprint'; END IF;
  IF @r43__populated__b1__final__source_trigger_fingerprint IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B1 FINAL source_trigger_fingerprint'; END IF;
  IF @r43__populated__b1__final__head_rows IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B1 FINAL head_rows'; END IF;
  IF @r43__populated__b1__final__head_streams IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B1 FINAL head_streams'; END IF;
  IF @r43__populated__b1__final__shadow_rows IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B1 FINAL shadow_rows'; END IF;
  IF @r43__populated__b1__final__shadow_streams IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B1 FINAL shadow_streams'; END IF;
  IF @r43__populated__b1__final__shadow_fingerprint IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B1 FINAL shadow_fingerprint'; END IF;
  IF @r43__populated__b1__final__shadow_trigger_fingerprint IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B1 FINAL shadow_trigger_fingerprint'; END IF;
  IF @r43__populated__b1__final__created_objects IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B1 FINAL created_objects'; END IF;
  IF @r43__populated__b1__final__preserved_event_hash_sha256 IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B1 FINAL preserved_event_hash_sha256'; END IF;
  SET @r43_summary_canonical=CONCAT('O15;',CONCAT('F5:route',CONCAT('S',OCTET_LENGTH(CAST(@r43__populated__b1__final__route AS CHAR)),':',UPPER(HEX(CAST(@r43__populated__b1__final__route AS CHAR))))),CONCAT('F5:phase',CONCAT('S',OCTET_LENGTH(CAST(@r43__populated__b1__final__phase AS CHAR)),':',UPPER(HEX(CAST(@r43__populated__b1__final__phase AS CHAR))))),CONCAT('F5:state',CONCAT('S',OCTET_LENGTH(CAST(@r43__populated__b1__final__state AS CHAR)),':',UPPER(HEX(CAST(@r43__populated__b1__final__state AS CHAR))))),CONCAT('F11:source_rows',CONCAT('I',CAST(@r43__populated__b1__final__source_rows AS CHAR),';')),CONCAT('F14:source_streams',CONCAT('I',CAST(@r43__populated__b1__final__source_streams AS CHAR),';')),CONCAT('F18:source_fingerprint',CONCAT('S',OCTET_LENGTH(CAST(@r43__populated__b1__final__source_fingerprint AS CHAR)),':',UPPER(HEX(CAST(@r43__populated__b1__final__source_fingerprint AS CHAR))))),CONCAT('F26:source_trigger_fingerprint',CONCAT('S',OCTET_LENGTH(CAST(@r43__populated__b1__final__source_trigger_fingerprint AS CHAR)),':',UPPER(HEX(CAST(@r43__populated__b1__final__source_trigger_fingerprint AS CHAR))))),CONCAT('F9:head_rows',CONCAT('I',CAST(@r43__populated__b1__final__head_rows AS CHAR),';')),CONCAT('F12:head_streams',CONCAT('I',CAST(@r43__populated__b1__final__head_streams AS CHAR),';')),CONCAT('F11:shadow_rows',CONCAT('I',CAST(@r43__populated__b1__final__shadow_rows AS CHAR),';')),CONCAT('F14:shadow_streams',CONCAT('I',CAST(@r43__populated__b1__final__shadow_streams AS CHAR),';')),CONCAT('F18:shadow_fingerprint',CONCAT('S',OCTET_LENGTH(CAST(@r43__populated__b1__final__shadow_fingerprint AS CHAR)),':',UPPER(HEX(CAST(@r43__populated__b1__final__shadow_fingerprint AS CHAR))))),CONCAT('F26:shadow_trigger_fingerprint',CONCAT('S',OCTET_LENGTH(CAST(@r43__populated__b1__final__shadow_trigger_fingerprint AS CHAR)),':',UPPER(HEX(CAST(@r43__populated__b1__final__shadow_trigger_fingerprint AS CHAR))))),CONCAT('F15:created_objects',CONCAT('I',CAST(@r43__populated__b1__final__created_objects AS CHAR),';')),CONCAT('F27:preserved_event_hash_sha256',CONCAT('S',OCTET_LENGTH(CAST(@r43__populated__b1__final__preserved_event_hash_sha256 AS CHAR)),':',UPPER(HEX(CAST(@r43__populated__b1__final__preserved_event_hash_sha256 AS CHAR))))));
  SET @r43_summary_base64=REPLACE(TO_BASE64(@r43_summary_canonical),'\n','');
  SET @r43_field_order_sha256='02d88cd7c8556168d17d0fd21969dbc1eddc47e6cfe54c974f92dcf2d30f9711';
  SET @r43_payload_canonical=CONCAT('O7;',CONCAT('F6:schema',CONCAT('S',OCTET_LENGTH(CAST('mxmed.audit.mp01b.canonical.v1' AS CHAR)),':',UPPER(HEX(CAST('mxmed.audit.mp01b.canonical.v1' AS CHAR))))),CONCAT('F5:route',CONCAT('S',OCTET_LENGTH(CAST('POPULATED' AS CHAR)),':',UPPER(HEX(CAST('POPULATED' AS CHAR))))),CONCAT('F5:phase',CONCAT('S',OCTET_LENGTH(CAST('B1' AS CHAR)),':',UPPER(HEX(CAST('B1' AS CHAR))))),CONCAT('F5:state',CONCAT('S',OCTET_LENGTH(CAST('FINAL' AS CHAR)),':',UPPER(HEX(CAST('FINAL' AS CHAR))))),CONCAT('F18:field_order_sha256',CONCAT('S',OCTET_LENGTH(CAST(@r43_field_order_sha256 AS CHAR)),':',UPPER(HEX(CAST(@r43_field_order_sha256 AS CHAR))))),CONCAT('F7:summary',CONCAT('S',OCTET_LENGTH(CAST(@r43_summary_base64 AS CHAR)),':',UPPER(HEX(CAST(@r43_summary_base64 AS CHAR))))),CONCAT('F23:previous_receipt_sha256',CONCAT('S',OCTET_LENGTH(CAST(@previous_receipt_sha256_populated AS CHAR)),':',UPPER(HEX(CAST(@previous_receipt_sha256_populated AS CHAR))))));
  SET @r43_payload_base64=REPLACE(TO_BASE64(@r43_payload_canonical),'\n','');
  SET @r43_receipt_sha256=LOWER(SHA2(@r43_payload_canonical,256));
  CALL audit_mp01b_assert_b1_final();
  INSERT INTO audit_mp01b_phase_receipts(route,phase,state,payload_canonical,payload_base64,previous_receipt_sha256,receipt_sha256) VALUES('POPULATED','B1','FINAL',@r43_payload_canonical,@r43_payload_base64,@previous_receipt_sha256_populated,@r43_receipt_sha256);
  SET @previous_receipt_sha256_populated=@r43_receipt_sha256;
 ELSE SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='invalid audit route';
 END IF;
END$$
DELIMITER ;
CALL audit_mp01b_emit_b1(@audit_mp01b_route);
SET @r43_execution_state='B1_EXECUTED';
