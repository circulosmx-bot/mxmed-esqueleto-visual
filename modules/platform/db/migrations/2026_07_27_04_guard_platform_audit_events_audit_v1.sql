-- AUDIT-MP01B B4 repository promotion derived from certified R45 + R54; not executed by preparation
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
DROP TRIGGER IF EXISTS platform_audit_events_audit_v1_shadow_no_update;
DELIMITER $$
CREATE TRIGGER platform_audit_events_audit_v1_shadow_no_update BEFORE UPDATE ON platform_audit_events_audit_v1_shadow FOR EACH ROW
BEGIN
 SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='audit_shadow_update_forbidden';
END$$
DELIMITER ;
DROP TRIGGER IF EXISTS platform_audit_events_audit_v1_shadow_no_delete;
DELIMITER $$
CREATE TRIGGER platform_audit_events_audit_v1_shadow_no_delete BEFORE DELETE ON platform_audit_events_audit_v1_shadow FOR EACH ROW
BEGIN
 SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='audit_shadow_delete_forbidden';
END$$
DELIMITER ;
DROP PROCEDURE IF EXISTS audit_mp01b_emit_b4;
DELIMITER $$
CREATE PROCEDURE audit_mp01b_emit_b4(IN p_route VARCHAR(16))
BEGIN
 CALL audit_mp01b_assert_route(p_route);
 IF p_route='EMPTY' THEN
  SELECT CASE WHEN COUNT(*)>0 AND SUM(a.allowed=0)=0 THEN 1 ELSE 0 END INTO @r43__empty__b4__final__permissions_active FROM audit_mp01b_privilege_inventory a WHERE a.principal=CURRENT_USER();
  SELECT CASE WHEN COUNT(*)=0 THEN 'DENIED' WHEN SUM(a.allowed=0)>0 THEN 'CONFIRMED_INACTIVE' ELSE 'CONFIRMED_ACTIVE' END INTO @r43__empty__b4__final__permissions_state FROM audit_mp01b_privilege_inventory a WHERE a.principal=CURRENT_USER();
  CALL audit_mp01b_fold_privileges(@r43__empty__b4__final__privilege_inventory_sha256);
  CALL audit_mp01b_fold_database_identity(@r43__empty__b4__final__database_identity_fingerprint);
  SET @r43__empty__b4__final__runtime_principal=CAST(CURRENT_USER() AS CHAR);
  WITH RECURSIVE reachable_roles(role_name,path) AS (SELECT CAST(CURRENT_USER() AS CHAR(512)),CAST(CONCAT('|',CURRENT_USER(),'|') AS CHAR(8192)) UNION ALL SELECT CAST(CONCAT(r.FROM_USER,'@',r.FROM_HOST) AS CHAR(512)),CAST(CONCAT(rr.path,CONCAT(r.FROM_USER,'@',r.FROM_HOST),'|') AS CHAR(8192)) FROM reachable_roles rr JOIN mysql.role_edges r ON CONCAT(r.TO_USER,'@',r.TO_HOST)=rr.role_name WHERE LOCATE(CONCAT('|',CONCAT(r.FROM_USER,'@',r.FROM_HOST),'|'),rr.path)=0) SELECT COUNT(DISTINCT rr.role_name)-1 INTO @r43__empty__b4__final__effective_role_count FROM reachable_roles rr;
  SELECT COUNT(*) INTO @r43__empty__b4__final__source_trigger_count FROM information_schema.triggers i WHERE i.trigger_schema=DATABASE() AND i.event_object_table='platform_audit_events' AND i.event_manipulation IN ('UPDATE','DELETE') AND i.action_timing='BEFORE';
  SELECT COUNT(*) INTO @r43__empty__b4__final__shadow_trigger_count FROM information_schema.triggers i WHERE i.trigger_schema=DATABASE() AND i.event_object_table='platform_audit_events_audit_v1_shadow' AND i.event_manipulation IN ('UPDATE','DELETE') AND i.action_timing='BEFORE';
  SELECT COUNT(*) INTO @r43__empty__b4__final__source_update_count FROM information_schema.triggers i WHERE i.trigger_schema=DATABASE() AND i.event_object_table='platform_audit_events' AND i.event_manipulation='UPDATE' AND i.action_timing='BEFORE';
  SELECT COUNT(*) INTO @r43__empty__b4__final__source_delete_count FROM information_schema.triggers i WHERE i.trigger_schema=DATABASE() AND i.event_object_table='platform_audit_events' AND i.event_manipulation='DELETE' AND i.action_timing='BEFORE';
  SELECT COUNT(*) INTO @r43__empty__b4__final__shadow_update_count FROM information_schema.triggers i WHERE i.trigger_schema=DATABASE() AND i.event_object_table='platform_audit_events_audit_v1_shadow' AND i.event_manipulation='UPDATE' AND i.action_timing='BEFORE';
  SELECT COUNT(*) INTO @r43__empty__b4__final__shadow_delete_count FROM information_schema.triggers i WHERE i.trigger_schema=DATABASE() AND i.event_object_table='platform_audit_events_audit_v1_shadow' AND i.event_manipulation='DELETE' AND i.action_timing='BEFORE';
  CALL audit_mp01b_fold_source(@r43__empty__b4__final__source_fingerprint);
  CALL audit_mp01b_fold_shadow(@r43__empty__b4__final__shadow_fingerprint);
  CALL audit_mp01b_fold_source_triggers(@r43__empty__b4__final__source_trigger_fingerprint);
  CALL audit_mp01b_fold_shadow_triggers(@r43__empty__b4__final__shadow_trigger_fingerprint);
  CALL audit_mp01b_fold_preserved_event_hash(@r43__empty__b4__final__preserved_event_hash_sha256);
  IF @r43__empty__b4__final__permissions_active IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B4 FINAL permissions_active'; END IF;
  IF @r43__empty__b4__final__permissions_state IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B4 FINAL permissions_state'; END IF;
  IF @r43__empty__b4__final__privilege_inventory_sha256 IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B4 FINAL privilege_inventory_sha256'; END IF;
  IF @r43__empty__b4__final__database_identity_fingerprint IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B4 FINAL database_identity_fingerprint'; END IF;
  IF @r43__empty__b4__final__runtime_principal IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B4 FINAL runtime_principal'; END IF;
  IF @r43__empty__b4__final__effective_role_count IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B4 FINAL effective_role_count'; END IF;
  IF @r43__empty__b4__final__source_trigger_count IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B4 FINAL source_trigger_count'; END IF;
  IF @r43__empty__b4__final__shadow_trigger_count IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B4 FINAL shadow_trigger_count'; END IF;
  IF @r43__empty__b4__final__source_update_count IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B4 FINAL source_update_count'; END IF;
  IF @r43__empty__b4__final__source_delete_count IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B4 FINAL source_delete_count'; END IF;
  IF @r43__empty__b4__final__shadow_update_count IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B4 FINAL shadow_update_count'; END IF;
  IF @r43__empty__b4__final__shadow_delete_count IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B4 FINAL shadow_delete_count'; END IF;
  IF @r43__empty__b4__final__source_fingerprint IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B4 FINAL source_fingerprint'; END IF;
  IF @r43__empty__b4__final__shadow_fingerprint IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B4 FINAL shadow_fingerprint'; END IF;
  IF @r43__empty__b4__final__source_trigger_fingerprint IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B4 FINAL source_trigger_fingerprint'; END IF;
  IF @r43__empty__b4__final__shadow_trigger_fingerprint IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B4 FINAL shadow_trigger_fingerprint'; END IF;
  IF @r43__empty__b4__final__preserved_event_hash_sha256 IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B4 FINAL preserved_event_hash_sha256'; END IF;
  SET @r43_summary_canonical=CONCAT('O17;',CONCAT('F18:permissions_active',CONCAT('I',CAST(@r43__empty__b4__final__permissions_active AS CHAR),';')),CONCAT('F17:permissions_state',CONCAT('S',OCTET_LENGTH(CAST(@r43__empty__b4__final__permissions_state AS CHAR)),':',UPPER(HEX(CAST(@r43__empty__b4__final__permissions_state AS CHAR))))),CONCAT('F26:privilege_inventory_sha256',CONCAT('S',OCTET_LENGTH(CAST(@r43__empty__b4__final__privilege_inventory_sha256 AS CHAR)),':',UPPER(HEX(CAST(@r43__empty__b4__final__privilege_inventory_sha256 AS CHAR))))),CONCAT('F29:database_identity_fingerprint',CONCAT('S',OCTET_LENGTH(CAST(@r43__empty__b4__final__database_identity_fingerprint AS CHAR)),':',UPPER(HEX(CAST(@r43__empty__b4__final__database_identity_fingerprint AS CHAR))))),CONCAT('F17:runtime_principal',CONCAT('S',OCTET_LENGTH(CAST(@r43__empty__b4__final__runtime_principal AS CHAR)),':',UPPER(HEX(CAST(@r43__empty__b4__final__runtime_principal AS CHAR))))),CONCAT('F20:effective_role_count',CONCAT('I',CAST(@r43__empty__b4__final__effective_role_count AS CHAR),';')),CONCAT('F20:source_trigger_count',CONCAT('I',CAST(@r43__empty__b4__final__source_trigger_count AS CHAR),';')),CONCAT('F20:shadow_trigger_count',CONCAT('I',CAST(@r43__empty__b4__final__shadow_trigger_count AS CHAR),';')),CONCAT('F19:source_update_count',CONCAT('I',CAST(@r43__empty__b4__final__source_update_count AS CHAR),';')),CONCAT('F19:source_delete_count',CONCAT('I',CAST(@r43__empty__b4__final__source_delete_count AS CHAR),';')),CONCAT('F19:shadow_update_count',CONCAT('I',CAST(@r43__empty__b4__final__shadow_update_count AS CHAR),';')),CONCAT('F19:shadow_delete_count',CONCAT('I',CAST(@r43__empty__b4__final__shadow_delete_count AS CHAR),';')),CONCAT('F18:source_fingerprint',CONCAT('S',OCTET_LENGTH(CAST(@r43__empty__b4__final__source_fingerprint AS CHAR)),':',UPPER(HEX(CAST(@r43__empty__b4__final__source_fingerprint AS CHAR))))),CONCAT('F18:shadow_fingerprint',CONCAT('S',OCTET_LENGTH(CAST(@r43__empty__b4__final__shadow_fingerprint AS CHAR)),':',UPPER(HEX(CAST(@r43__empty__b4__final__shadow_fingerprint AS CHAR))))),CONCAT('F26:source_trigger_fingerprint',CONCAT('S',OCTET_LENGTH(CAST(@r43__empty__b4__final__source_trigger_fingerprint AS CHAR)),':',UPPER(HEX(CAST(@r43__empty__b4__final__source_trigger_fingerprint AS CHAR))))),CONCAT('F26:shadow_trigger_fingerprint',CONCAT('S',OCTET_LENGTH(CAST(@r43__empty__b4__final__shadow_trigger_fingerprint AS CHAR)),':',UPPER(HEX(CAST(@r43__empty__b4__final__shadow_trigger_fingerprint AS CHAR))))),CONCAT('F27:preserved_event_hash_sha256',CONCAT('S',OCTET_LENGTH(CAST(@r43__empty__b4__final__preserved_event_hash_sha256 AS CHAR)),':',UPPER(HEX(CAST(@r43__empty__b4__final__preserved_event_hash_sha256 AS CHAR))))));
  SET @r43_summary_base64=REPLACE(TO_BASE64(@r43_summary_canonical),'\n','');
  SET @r43_field_order_sha256='7bcc42835d6bd193c5de7c3bb615200182d2e35a12128210d0d9cc97485b4efa';
  SET @r43_payload_canonical=CONCAT('O7;',CONCAT('F6:schema',CONCAT('S',OCTET_LENGTH(CAST('mxmed.audit.mp01b.canonical.v1' AS CHAR)),':',UPPER(HEX(CAST('mxmed.audit.mp01b.canonical.v1' AS CHAR))))),CONCAT('F5:route',CONCAT('S',OCTET_LENGTH(CAST('EMPTY' AS CHAR)),':',UPPER(HEX(CAST('EMPTY' AS CHAR))))),CONCAT('F5:phase',CONCAT('S',OCTET_LENGTH(CAST('B4' AS CHAR)),':',UPPER(HEX(CAST('B4' AS CHAR))))),CONCAT('F5:state',CONCAT('S',OCTET_LENGTH(CAST('FINAL' AS CHAR)),':',UPPER(HEX(CAST('FINAL' AS CHAR))))),CONCAT('F18:field_order_sha256',CONCAT('S',OCTET_LENGTH(CAST(@r43_field_order_sha256 AS CHAR)),':',UPPER(HEX(CAST(@r43_field_order_sha256 AS CHAR))))),CONCAT('F7:summary',CONCAT('S',OCTET_LENGTH(CAST(@r43_summary_base64 AS CHAR)),':',UPPER(HEX(CAST(@r43_summary_base64 AS CHAR))))),CONCAT('F23:previous_receipt_sha256',CONCAT('S',OCTET_LENGTH(CAST(@previous_receipt_sha256_empty AS CHAR)),':',UPPER(HEX(CAST(@previous_receipt_sha256_empty AS CHAR))))));
  SET @r43_payload_base64=REPLACE(TO_BASE64(@r43_payload_canonical),'\n','');
  SET @r43_receipt_sha256=LOWER(SHA2(@r43_payload_canonical,256));
  CALL audit_mp01b_assert_b4_final();
  INSERT INTO audit_mp01b_phase_receipts(route,phase,state,payload_canonical,payload_base64,previous_receipt_sha256,receipt_sha256) VALUES('EMPTY','B4','FINAL',@r43_payload_canonical,@r43_payload_base64,@previous_receipt_sha256_empty,@r43_receipt_sha256);
  SET @previous_receipt_sha256_empty=@r43_receipt_sha256;
 ELSEIF p_route='POPULATED' THEN
  SELECT CASE WHEN COUNT(*)>0 AND SUM(a.allowed=0)=0 THEN 1 ELSE 0 END INTO @r43__populated__b4__final__permissions_active FROM audit_mp01b_privilege_inventory a WHERE a.principal=CURRENT_USER();
  SELECT CASE WHEN COUNT(*)=0 THEN 'DENIED' WHEN SUM(a.allowed=0)>0 THEN 'CONFIRMED_INACTIVE' ELSE 'CONFIRMED_ACTIVE' END INTO @r43__populated__b4__final__permissions_state FROM audit_mp01b_privilege_inventory a WHERE a.principal=CURRENT_USER();
  CALL audit_mp01b_fold_privileges(@r43__populated__b4__final__privilege_inventory_sha256);
  CALL audit_mp01b_fold_database_identity(@r43__populated__b4__final__database_identity_fingerprint);
  SET @r43__populated__b4__final__runtime_principal=CAST(CURRENT_USER() AS CHAR);
  WITH RECURSIVE reachable_roles(role_name,path) AS (SELECT CAST(CURRENT_USER() AS CHAR(512)),CAST(CONCAT('|',CURRENT_USER(),'|') AS CHAR(8192)) UNION ALL SELECT CAST(CONCAT(r.FROM_USER,'@',r.FROM_HOST) AS CHAR(512)),CAST(CONCAT(rr.path,CONCAT(r.FROM_USER,'@',r.FROM_HOST),'|') AS CHAR(8192)) FROM reachable_roles rr JOIN mysql.role_edges r ON CONCAT(r.TO_USER,'@',r.TO_HOST)=rr.role_name WHERE LOCATE(CONCAT('|',CONCAT(r.FROM_USER,'@',r.FROM_HOST),'|'),rr.path)=0) SELECT COUNT(DISTINCT rr.role_name)-1 INTO @r43__populated__b4__final__effective_role_count FROM reachable_roles rr;
  SELECT COUNT(*) INTO @r43__populated__b4__final__source_trigger_count FROM information_schema.triggers i WHERE i.trigger_schema=DATABASE() AND i.event_object_table='platform_audit_events' AND i.event_manipulation IN ('UPDATE','DELETE') AND i.action_timing='BEFORE';
  SELECT COUNT(*) INTO @r43__populated__b4__final__shadow_trigger_count FROM information_schema.triggers i WHERE i.trigger_schema=DATABASE() AND i.event_object_table='platform_audit_events_audit_v1_shadow' AND i.event_manipulation IN ('UPDATE','DELETE') AND i.action_timing='BEFORE';
  SELECT COUNT(*) INTO @r43__populated__b4__final__source_update_count FROM information_schema.triggers i WHERE i.trigger_schema=DATABASE() AND i.event_object_table='platform_audit_events' AND i.event_manipulation='UPDATE' AND i.action_timing='BEFORE';
  SELECT COUNT(*) INTO @r43__populated__b4__final__source_delete_count FROM information_schema.triggers i WHERE i.trigger_schema=DATABASE() AND i.event_object_table='platform_audit_events' AND i.event_manipulation='DELETE' AND i.action_timing='BEFORE';
  SELECT COUNT(*) INTO @r43__populated__b4__final__shadow_update_count FROM information_schema.triggers i WHERE i.trigger_schema=DATABASE() AND i.event_object_table='platform_audit_events_audit_v1_shadow' AND i.event_manipulation='UPDATE' AND i.action_timing='BEFORE';
  SELECT COUNT(*) INTO @r43__populated__b4__final__shadow_delete_count FROM information_schema.triggers i WHERE i.trigger_schema=DATABASE() AND i.event_object_table='platform_audit_events_audit_v1_shadow' AND i.event_manipulation='DELETE' AND i.action_timing='BEFORE';
  CALL audit_mp01b_fold_source(@r43__populated__b4__final__source_fingerprint);
  CALL audit_mp01b_fold_shadow(@r43__populated__b4__final__shadow_fingerprint);
  CALL audit_mp01b_fold_source_triggers(@r43__populated__b4__final__source_trigger_fingerprint);
  CALL audit_mp01b_fold_shadow_triggers(@r43__populated__b4__final__shadow_trigger_fingerprint);
  CALL audit_mp01b_fold_preserved_event_hash(@r43__populated__b4__final__preserved_event_hash_sha256);
  IF @r43__populated__b4__final__permissions_active IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B4 FINAL permissions_active'; END IF;
  IF @r43__populated__b4__final__permissions_state IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B4 FINAL permissions_state'; END IF;
  IF @r43__populated__b4__final__privilege_inventory_sha256 IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B4 FINAL privilege_inventory_sha256'; END IF;
  IF @r43__populated__b4__final__database_identity_fingerprint IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B4 FINAL database_identity_fingerprint'; END IF;
  IF @r43__populated__b4__final__runtime_principal IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B4 FINAL runtime_principal'; END IF;
  IF @r43__populated__b4__final__effective_role_count IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B4 FINAL effective_role_count'; END IF;
  IF @r43__populated__b4__final__source_trigger_count IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B4 FINAL source_trigger_count'; END IF;
  IF @r43__populated__b4__final__shadow_trigger_count IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B4 FINAL shadow_trigger_count'; END IF;
  IF @r43__populated__b4__final__source_update_count IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B4 FINAL source_update_count'; END IF;
  IF @r43__populated__b4__final__source_delete_count IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B4 FINAL source_delete_count'; END IF;
  IF @r43__populated__b4__final__shadow_update_count IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B4 FINAL shadow_update_count'; END IF;
  IF @r43__populated__b4__final__shadow_delete_count IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B4 FINAL shadow_delete_count'; END IF;
  IF @r43__populated__b4__final__source_fingerprint IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B4 FINAL source_fingerprint'; END IF;
  IF @r43__populated__b4__final__shadow_fingerprint IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B4 FINAL shadow_fingerprint'; END IF;
  IF @r43__populated__b4__final__source_trigger_fingerprint IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B4 FINAL source_trigger_fingerprint'; END IF;
  IF @r43__populated__b4__final__shadow_trigger_fingerprint IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B4 FINAL shadow_trigger_fingerprint'; END IF;
  IF @r43__populated__b4__final__preserved_event_hash_sha256 IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B4 FINAL preserved_event_hash_sha256'; END IF;
  SET @r43_summary_canonical=CONCAT('O17;',CONCAT('F18:permissions_active',CONCAT('I',CAST(@r43__populated__b4__final__permissions_active AS CHAR),';')),CONCAT('F17:permissions_state',CONCAT('S',OCTET_LENGTH(CAST(@r43__populated__b4__final__permissions_state AS CHAR)),':',UPPER(HEX(CAST(@r43__populated__b4__final__permissions_state AS CHAR))))),CONCAT('F26:privilege_inventory_sha256',CONCAT('S',OCTET_LENGTH(CAST(@r43__populated__b4__final__privilege_inventory_sha256 AS CHAR)),':',UPPER(HEX(CAST(@r43__populated__b4__final__privilege_inventory_sha256 AS CHAR))))),CONCAT('F29:database_identity_fingerprint',CONCAT('S',OCTET_LENGTH(CAST(@r43__populated__b4__final__database_identity_fingerprint AS CHAR)),':',UPPER(HEX(CAST(@r43__populated__b4__final__database_identity_fingerprint AS CHAR))))),CONCAT('F17:runtime_principal',CONCAT('S',OCTET_LENGTH(CAST(@r43__populated__b4__final__runtime_principal AS CHAR)),':',UPPER(HEX(CAST(@r43__populated__b4__final__runtime_principal AS CHAR))))),CONCAT('F20:effective_role_count',CONCAT('I',CAST(@r43__populated__b4__final__effective_role_count AS CHAR),';')),CONCAT('F20:source_trigger_count',CONCAT('I',CAST(@r43__populated__b4__final__source_trigger_count AS CHAR),';')),CONCAT('F20:shadow_trigger_count',CONCAT('I',CAST(@r43__populated__b4__final__shadow_trigger_count AS CHAR),';')),CONCAT('F19:source_update_count',CONCAT('I',CAST(@r43__populated__b4__final__source_update_count AS CHAR),';')),CONCAT('F19:source_delete_count',CONCAT('I',CAST(@r43__populated__b4__final__source_delete_count AS CHAR),';')),CONCAT('F19:shadow_update_count',CONCAT('I',CAST(@r43__populated__b4__final__shadow_update_count AS CHAR),';')),CONCAT('F19:shadow_delete_count',CONCAT('I',CAST(@r43__populated__b4__final__shadow_delete_count AS CHAR),';')),CONCAT('F18:source_fingerprint',CONCAT('S',OCTET_LENGTH(CAST(@r43__populated__b4__final__source_fingerprint AS CHAR)),':',UPPER(HEX(CAST(@r43__populated__b4__final__source_fingerprint AS CHAR))))),CONCAT('F18:shadow_fingerprint',CONCAT('S',OCTET_LENGTH(CAST(@r43__populated__b4__final__shadow_fingerprint AS CHAR)),':',UPPER(HEX(CAST(@r43__populated__b4__final__shadow_fingerprint AS CHAR))))),CONCAT('F26:source_trigger_fingerprint',CONCAT('S',OCTET_LENGTH(CAST(@r43__populated__b4__final__source_trigger_fingerprint AS CHAR)),':',UPPER(HEX(CAST(@r43__populated__b4__final__source_trigger_fingerprint AS CHAR))))),CONCAT('F26:shadow_trigger_fingerprint',CONCAT('S',OCTET_LENGTH(CAST(@r43__populated__b4__final__shadow_trigger_fingerprint AS CHAR)),':',UPPER(HEX(CAST(@r43__populated__b4__final__shadow_trigger_fingerprint AS CHAR))))),CONCAT('F27:preserved_event_hash_sha256',CONCAT('S',OCTET_LENGTH(CAST(@r43__populated__b4__final__preserved_event_hash_sha256 AS CHAR)),':',UPPER(HEX(CAST(@r43__populated__b4__final__preserved_event_hash_sha256 AS CHAR))))));
  SET @r43_summary_base64=REPLACE(TO_BASE64(@r43_summary_canonical),'\n','');
  SET @r43_field_order_sha256='7bcc42835d6bd193c5de7c3bb615200182d2e35a12128210d0d9cc97485b4efa';
  SET @r43_payload_canonical=CONCAT('O7;',CONCAT('F6:schema',CONCAT('S',OCTET_LENGTH(CAST('mxmed.audit.mp01b.canonical.v1' AS CHAR)),':',UPPER(HEX(CAST('mxmed.audit.mp01b.canonical.v1' AS CHAR))))),CONCAT('F5:route',CONCAT('S',OCTET_LENGTH(CAST('POPULATED' AS CHAR)),':',UPPER(HEX(CAST('POPULATED' AS CHAR))))),CONCAT('F5:phase',CONCAT('S',OCTET_LENGTH(CAST('B4' AS CHAR)),':',UPPER(HEX(CAST('B4' AS CHAR))))),CONCAT('F5:state',CONCAT('S',OCTET_LENGTH(CAST('FINAL' AS CHAR)),':',UPPER(HEX(CAST('FINAL' AS CHAR))))),CONCAT('F18:field_order_sha256',CONCAT('S',OCTET_LENGTH(CAST(@r43_field_order_sha256 AS CHAR)),':',UPPER(HEX(CAST(@r43_field_order_sha256 AS CHAR))))),CONCAT('F7:summary',CONCAT('S',OCTET_LENGTH(CAST(@r43_summary_base64 AS CHAR)),':',UPPER(HEX(CAST(@r43_summary_base64 AS CHAR))))),CONCAT('F23:previous_receipt_sha256',CONCAT('S',OCTET_LENGTH(CAST(@previous_receipt_sha256_populated AS CHAR)),':',UPPER(HEX(CAST(@previous_receipt_sha256_populated AS CHAR))))));
  SET @r43_payload_base64=REPLACE(TO_BASE64(@r43_payload_canonical),'\n','');
  SET @r43_receipt_sha256=LOWER(SHA2(@r43_payload_canonical,256));
  CALL audit_mp01b_assert_b4_final();
  INSERT INTO audit_mp01b_phase_receipts(route,phase,state,payload_canonical,payload_base64,previous_receipt_sha256,receipt_sha256) VALUES('POPULATED','B4','FINAL',@r43_payload_canonical,@r43_payload_base64,@previous_receipt_sha256_populated,@r43_receipt_sha256);
  SET @previous_receipt_sha256_populated=@r43_receipt_sha256;
 ELSE SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='invalid audit route';
 END IF;
END$$
DELIMITER ;
CALL audit_mp01b_emit_b4(@audit_mp01b_route);
-- R53_BEGIN_POST_B4_RAW_GUARD_IDENTITY
SELECT COUNT(*) INTO @r43_pc_guard_identity_count
FROM information_schema.triggers
WHERE trigger_schema=DATABASE()
  AND event_object_table IN ('platform_audit_events','platform_audit_events_audit_v1_shadow')
  AND event_manipulation IN ('UPDATE','DELETE');
-- R53_END_POST_B4_RAW_GUARD_IDENTITY
-- R51_BEGIN_POST_B4_ROUTE_AWARE_BINDING
SELECT CASE
  WHEN @audit_mp01b_route='EMPTY' THEN (@r43__empty__b4__final__permissions_active=1)
  WHEN @audit_mp01b_route='POPULATED' THEN (@r43__populated__b4__final__permissions_active=1)
  ELSE 0 END INTO @r43_pc_permissions_active;
SELECT CASE
  WHEN @audit_mp01b_route='EMPTY' THEN (@r43__empty__b4__final__permissions_state='CONFIRMED_ACTIVE')
  WHEN @audit_mp01b_route='POPULATED' THEN (@r43__populated__b4__final__permissions_state='CONFIRMED_ACTIVE')
  ELSE 0 END INTO @r43_pc_permissions_state_confirmed;
-- R51_END_POST_B4_ROUTE_AWARE_BINDING
IF @r43_pc_guard_identity_count<>4 OR @r43_pc_permissions_active<>1 OR @r43_pc_permissions_state_confirmed<>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='B4 route-aware guard postconditions failed'; END IF;
SET @r43_execution_state='B4_EXECUTED';
