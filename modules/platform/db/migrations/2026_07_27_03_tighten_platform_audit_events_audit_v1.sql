-- AUDIT-MP01B B3 repository promotion derived from certified R45 + R54; not executed by preparation
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
DROP PROCEDURE IF EXISTS audit_mp01b_emit_b3;
DELIMITER $$
CREATE PROCEDURE audit_mp01b_emit_b3(IN p_route VARCHAR(16))
BEGIN
 CALL audit_mp01b_assert_route(p_route);
 -- R50_BEGIN_B3_STREAM_HEAD_INSERT_ONLY
 INSERT INTO platform_audit_stream_heads(stream_key,last_sequence_number,last_event_hash)
 SELECT ranked.stream_key,ranked.sequence_number,ranked.event_hash
 FROM (
   SELECT s.stream_key,s.sequence_number,s.event_hash,
          ROW_NUMBER() OVER (PARTITION BY s.stream_key ORDER BY s.sequence_number DESC) AS rn
   FROM platform_audit_events_audit_v1_shadow s
 ) ranked
 LEFT JOIN platform_audit_stream_heads h ON h.stream_key=ranked.stream_key
 WHERE ranked.rn=1 AND h.stream_key IS NULL;
 -- R50_END_B3_STREAM_HEAD_INSERT_ONLY
 IF p_route='EMPTY' THEN
  SELECT COALESCE(SUM(x.excess),0) INTO @r43__empty__b3__final__duplicate_pk FROM (SELECT COUNT(*)-1 AS excess FROM platform_audit_events_audit_v1_shadow s GROUP BY s.stream_key,s.sequence_number HAVING COUNT(*)>1) x;
  SELECT COALESCE(SUM(x.excess),0) INTO @r43__empty__b3__final__duplicate_event_id FROM (SELECT COUNT(*)-1 AS excess FROM platform_audit_events_audit_v1_shadow s GROUP BY s.event_id HAVING COUNT(*)>1) x;
  SELECT COALESCE(SUM(x.excess),0) INTO @r43__empty__b3__final__duplicate_event_hash FROM (SELECT COUNT(*)-1 AS excess FROM platform_audit_events_audit_v1_shadow s GROUP BY s.event_hash HAVING COUNT(*)>1) x;
  -- R54_CANONICAL_NULL_FILTER_1
  SELECT COALESCE(SUM(x.excess),0) INTO @r43__empty__b3__final__duplicate_canonical_event_id FROM (SELECT COUNT(*)-1 AS excess FROM platform_audit_events_audit_v1_shadow s WHERE s.canonical_event_id IS NOT NULL GROUP BY s.canonical_event_id HAVING COUNT(*)>1) x;
  SELECT COUNT(*) INTO @r43__empty__b3__final__missing_source FROM platform_audit_events_audit_v1_shadow s LEFT JOIN platform_audit_events e ON s.stream_key=e.stream_key AND s.sequence_number=e.sequence_number WHERE e.stream_key IS NULL;
  SELECT COUNT(*) INTO @r43__empty__b3__final__missing_shadow FROM platform_audit_events e LEFT JOIN platform_audit_events_audit_v1_shadow s ON s.stream_key=e.stream_key AND s.sequence_number=e.sequence_number WHERE s.stream_key IS NULL;
  SELECT COUNT(DISTINCT s.stream_key) INTO @r43__empty__b3__final__missing_heads FROM platform_audit_events_audit_v1_shadow s LEFT JOIN platform_audit_stream_heads h ON h.stream_key=s.stream_key WHERE h.stream_key IS NULL;
  SELECT COUNT(*) INTO @r43__empty__b3__final__orphan_heads FROM platform_audit_stream_heads h LEFT JOIN platform_audit_events_audit_v1_shadow s ON s.stream_key=h.stream_key WHERE s.stream_key IS NULL;
  SELECT COUNT(*) INTO @r43__empty__b3__final__divergent_heads FROM platform_audit_stream_heads h JOIN (SELECT stream_key,sequence_number,event_hash,ROW_NUMBER() OVER (PARTITION BY stream_key ORDER BY sequence_number DESC) rn FROM platform_audit_events_audit_v1_shadow) latest ON latest.stream_key=h.stream_key AND latest.rn=1 WHERE h.last_sequence_number<>latest.sequence_number OR h.last_event_hash<>latest.event_hash;
  SELECT COUNT(*) INTO @r43__empty__b3__final__partial_rows FROM platform_audit_events_audit_v1_shadow s WHERE s.stream_key IS NULL OR s.sequence_number IS NULL OR s.event_id IS NULL OR s.event_hash IS NULL;
  SELECT COUNT(*) INTO @r43__empty__b3__final__field_mismatches FROM platform_audit_events_audit_v1_shadow s JOIN platform_audit_events e ON s.stream_key=e.stream_key AND s.sequence_number=e.sequence_number WHERE NOT (s.event_id<=>e.event_id) OR NOT (s.schema_version<=>e.schema_version) OR NOT (s.occurred_at_utc<=>e.occurred_at_utc) OR NOT (s.action<=>e.action) OR NOT (s.risk_level<=>e.risk_level) OR NOT (s.outcome<=>e.outcome) OR NOT (s.reason_code<=>e.reason_code) OR NOT (s.real_actor_reference<=>e.real_actor_reference) OR NOT (s.effective_actor_reference<=>e.effective_actor_reference) OR NOT (s.affected_subject_reference<=>e.affected_subject_reference) OR NOT (s.correlation_id<=>e.correlation_id) OR NOT (s.request_id<=>e.request_id) OR NOT (s.case_reference<=>e.case_reference) OR NOT (s.resource_type<=>e.resource_type) OR NOT (s.resource_reference<=>e.resource_reference) OR NOT (s.previous_hash<=>e.previous_hash) OR NOT (s.event_hash<=>e.event_hash) OR NOT (s.created_at_utc<=>e.created_at_utc);
  SELECT COUNT(*) INTO @r43__empty__b3__final__json_mismatches FROM platform_audit_events_audit_v1_shadow s JOIN platform_audit_events e ON s.stream_key=e.stream_key AND s.sequence_number=e.sequence_number WHERE NOT (CAST(s.metadata_json AS JSON)<=>CAST(e.metadata_json AS JSON));
  SELECT COUNT(*) INTO @r43__empty__b3__final__source_rows FROM platform_audit_events e;
  SELECT COUNT(DISTINCT e.stream_key) INTO @r43__empty__b3__final__source_streams FROM platform_audit_events e;
  SELECT COUNT(*) INTO @r43__empty__b3__final__shadow_rows FROM platform_audit_events_audit_v1_shadow s;
  SELECT COUNT(DISTINCT s.stream_key) INTO @r43__empty__b3__final__shadow_streams FROM platform_audit_events_audit_v1_shadow s;
  CALL audit_mp01b_fold_source(@r43__empty__b3__final__source_fingerprint);
  CALL audit_mp01b_fold_shadow(@r43__empty__b3__final__shadow_fingerprint);
  CALL audit_mp01b_fold_source_triggers(@r43__empty__b3__final__source_trigger_fingerprint);
  CALL audit_mp01b_fold_shadow_triggers(@r43__empty__b3__final__shadow_trigger_fingerprint);
  CALL audit_mp01b_fold_preserved_event_hash(@r43__empty__b3__final__preserved_event_hash_sha256);
  IF @r43__empty__b3__final__duplicate_pk IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B3 FINAL duplicate_pk'; END IF;
  IF @r43__empty__b3__final__duplicate_event_id IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B3 FINAL duplicate_event_id'; END IF;
  IF @r43__empty__b3__final__duplicate_event_hash IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B3 FINAL duplicate_event_hash'; END IF;
  IF @r43__empty__b3__final__duplicate_canonical_event_id IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B3 FINAL duplicate_canonical_event_id'; END IF;
  IF @r43__empty__b3__final__missing_source IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B3 FINAL missing_source'; END IF;
  IF @r43__empty__b3__final__missing_shadow IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B3 FINAL missing_shadow'; END IF;
  IF @r43__empty__b3__final__missing_heads IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B3 FINAL missing_heads'; END IF;
  IF @r43__empty__b3__final__orphan_heads IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B3 FINAL orphan_heads'; END IF;
  IF @r43__empty__b3__final__divergent_heads IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B3 FINAL divergent_heads'; END IF;
  IF @r43__empty__b3__final__partial_rows IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B3 FINAL partial_rows'; END IF;
  IF @r43__empty__b3__final__field_mismatches IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B3 FINAL field_mismatches'; END IF;
  IF @r43__empty__b3__final__json_mismatches IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B3 FINAL json_mismatches'; END IF;
  IF @r43__empty__b3__final__source_rows IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B3 FINAL source_rows'; END IF;
  IF @r43__empty__b3__final__source_streams IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B3 FINAL source_streams'; END IF;
  IF @r43__empty__b3__final__shadow_rows IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B3 FINAL shadow_rows'; END IF;
  IF @r43__empty__b3__final__shadow_streams IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B3 FINAL shadow_streams'; END IF;
  IF @r43__empty__b3__final__source_fingerprint IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B3 FINAL source_fingerprint'; END IF;
  IF @r43__empty__b3__final__shadow_fingerprint IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B3 FINAL shadow_fingerprint'; END IF;
  IF @r43__empty__b3__final__source_trigger_fingerprint IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B3 FINAL source_trigger_fingerprint'; END IF;
  IF @r43__empty__b3__final__shadow_trigger_fingerprint IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B3 FINAL shadow_trigger_fingerprint'; END IF;
  IF @r43__empty__b3__final__preserved_event_hash_sha256 IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B3 FINAL preserved_event_hash_sha256'; END IF;
  SET @r43_summary_canonical=CONCAT('O21;',CONCAT('F12:duplicate_pk',CONCAT('I',CAST(@r43__empty__b3__final__duplicate_pk AS CHAR),';')),CONCAT('F18:duplicate_event_id',CONCAT('I',CAST(@r43__empty__b3__final__duplicate_event_id AS CHAR),';')),CONCAT('F20:duplicate_event_hash',CONCAT('I',CAST(@r43__empty__b3__final__duplicate_event_hash AS CHAR),';')),CONCAT('F28:duplicate_canonical_event_id',CONCAT('I',CAST(@r43__empty__b3__final__duplicate_canonical_event_id AS CHAR),';')),CONCAT('F14:missing_source',CONCAT('I',CAST(@r43__empty__b3__final__missing_source AS CHAR),';')),CONCAT('F14:missing_shadow',CONCAT('I',CAST(@r43__empty__b3__final__missing_shadow AS CHAR),';')),CONCAT('F13:missing_heads',CONCAT('I',CAST(@r43__empty__b3__final__missing_heads AS CHAR),';')),CONCAT('F12:orphan_heads',CONCAT('I',CAST(@r43__empty__b3__final__orphan_heads AS CHAR),';')),CONCAT('F15:divergent_heads',CONCAT('I',CAST(@r43__empty__b3__final__divergent_heads AS CHAR),';')),CONCAT('F12:partial_rows',CONCAT('I',CAST(@r43__empty__b3__final__partial_rows AS CHAR),';')),CONCAT('F16:field_mismatches',CONCAT('I',CAST(@r43__empty__b3__final__field_mismatches AS CHAR),';')),CONCAT('F15:json_mismatches',CONCAT('I',CAST(@r43__empty__b3__final__json_mismatches AS CHAR),';')),CONCAT('F11:source_rows',CONCAT('I',CAST(@r43__empty__b3__final__source_rows AS CHAR),';')),CONCAT('F14:source_streams',CONCAT('I',CAST(@r43__empty__b3__final__source_streams AS CHAR),';')),CONCAT('F11:shadow_rows',CONCAT('I',CAST(@r43__empty__b3__final__shadow_rows AS CHAR),';')),CONCAT('F14:shadow_streams',CONCAT('I',CAST(@r43__empty__b3__final__shadow_streams AS CHAR),';')),CONCAT('F18:source_fingerprint',CONCAT('S',OCTET_LENGTH(CAST(@r43__empty__b3__final__source_fingerprint AS CHAR)),':',UPPER(HEX(CAST(@r43__empty__b3__final__source_fingerprint AS CHAR))))),CONCAT('F18:shadow_fingerprint',CONCAT('S',OCTET_LENGTH(CAST(@r43__empty__b3__final__shadow_fingerprint AS CHAR)),':',UPPER(HEX(CAST(@r43__empty__b3__final__shadow_fingerprint AS CHAR))))),CONCAT('F26:source_trigger_fingerprint',CONCAT('S',OCTET_LENGTH(CAST(@r43__empty__b3__final__source_trigger_fingerprint AS CHAR)),':',UPPER(HEX(CAST(@r43__empty__b3__final__source_trigger_fingerprint AS CHAR))))),CONCAT('F26:shadow_trigger_fingerprint',CONCAT('S',OCTET_LENGTH(CAST(@r43__empty__b3__final__shadow_trigger_fingerprint AS CHAR)),':',UPPER(HEX(CAST(@r43__empty__b3__final__shadow_trigger_fingerprint AS CHAR))))),CONCAT('F27:preserved_event_hash_sha256',CONCAT('S',OCTET_LENGTH(CAST(@r43__empty__b3__final__preserved_event_hash_sha256 AS CHAR)),':',UPPER(HEX(CAST(@r43__empty__b3__final__preserved_event_hash_sha256 AS CHAR))))));
  SET @r43_summary_base64=REPLACE(TO_BASE64(@r43_summary_canonical),'\n','');
  SET @r43_field_order_sha256='05112339586747154bae48b092f3f5b35274170d81c16be388c49067bac84954';
  SET @r43_payload_canonical=CONCAT('O7;',CONCAT('F6:schema',CONCAT('S',OCTET_LENGTH(CAST('mxmed.audit.mp01b.canonical.v1' AS CHAR)),':',UPPER(HEX(CAST('mxmed.audit.mp01b.canonical.v1' AS CHAR))))),CONCAT('F5:route',CONCAT('S',OCTET_LENGTH(CAST('EMPTY' AS CHAR)),':',UPPER(HEX(CAST('EMPTY' AS CHAR))))),CONCAT('F5:phase',CONCAT('S',OCTET_LENGTH(CAST('B3' AS CHAR)),':',UPPER(HEX(CAST('B3' AS CHAR))))),CONCAT('F5:state',CONCAT('S',OCTET_LENGTH(CAST('FINAL' AS CHAR)),':',UPPER(HEX(CAST('FINAL' AS CHAR))))),CONCAT('F18:field_order_sha256',CONCAT('S',OCTET_LENGTH(CAST(@r43_field_order_sha256 AS CHAR)),':',UPPER(HEX(CAST(@r43_field_order_sha256 AS CHAR))))),CONCAT('F7:summary',CONCAT('S',OCTET_LENGTH(CAST(@r43_summary_base64 AS CHAR)),':',UPPER(HEX(CAST(@r43_summary_base64 AS CHAR))))),CONCAT('F23:previous_receipt_sha256',CONCAT('S',OCTET_LENGTH(CAST(@previous_receipt_sha256_empty AS CHAR)),':',UPPER(HEX(CAST(@previous_receipt_sha256_empty AS CHAR))))));
  SET @r43_payload_base64=REPLACE(TO_BASE64(@r43_payload_canonical),'\n','');
  SET @r43_receipt_sha256=LOWER(SHA2(@r43_payload_canonical,256));
  CALL audit_mp01b_assert_b3_final();
  INSERT INTO audit_mp01b_phase_receipts(route,phase,state,payload_canonical,payload_base64,previous_receipt_sha256,receipt_sha256) VALUES('EMPTY','B3','FINAL',@r43_payload_canonical,@r43_payload_base64,@previous_receipt_sha256_empty,@r43_receipt_sha256);
  SET @previous_receipt_sha256_empty=@r43_receipt_sha256;
 ELSEIF p_route='POPULATED' THEN
  SELECT COALESCE(SUM(x.excess),0) INTO @r43__populated__b3__final__duplicate_pk FROM (SELECT COUNT(*)-1 AS excess FROM platform_audit_events_audit_v1_shadow s GROUP BY s.stream_key,s.sequence_number HAVING COUNT(*)>1) x;
  SELECT COALESCE(SUM(x.excess),0) INTO @r43__populated__b3__final__duplicate_event_id FROM (SELECT COUNT(*)-1 AS excess FROM platform_audit_events_audit_v1_shadow s GROUP BY s.event_id HAVING COUNT(*)>1) x;
  SELECT COALESCE(SUM(x.excess),0) INTO @r43__populated__b3__final__duplicate_event_hash FROM (SELECT COUNT(*)-1 AS excess FROM platform_audit_events_audit_v1_shadow s GROUP BY s.event_hash HAVING COUNT(*)>1) x;
  -- R54_CANONICAL_NULL_FILTER_2
  SELECT COALESCE(SUM(x.excess),0) INTO @r43__populated__b3__final__duplicate_canonical_event_id FROM (SELECT COUNT(*)-1 AS excess FROM platform_audit_events_audit_v1_shadow s WHERE s.canonical_event_id IS NOT NULL GROUP BY s.canonical_event_id HAVING COUNT(*)>1) x;
  SELECT COUNT(*) INTO @r43__populated__b3__final__missing_source FROM platform_audit_events_audit_v1_shadow s LEFT JOIN platform_audit_events e ON s.stream_key=e.stream_key AND s.sequence_number=e.sequence_number WHERE e.stream_key IS NULL;
  SELECT COUNT(*) INTO @r43__populated__b3__final__missing_shadow FROM platform_audit_events e LEFT JOIN platform_audit_events_audit_v1_shadow s ON s.stream_key=e.stream_key AND s.sequence_number=e.sequence_number WHERE s.stream_key IS NULL;
  SELECT COUNT(DISTINCT s.stream_key) INTO @r43__populated__b3__final__missing_heads FROM platform_audit_events_audit_v1_shadow s LEFT JOIN platform_audit_stream_heads h ON h.stream_key=s.stream_key WHERE h.stream_key IS NULL;
  SELECT COUNT(*) INTO @r43__populated__b3__final__orphan_heads FROM platform_audit_stream_heads h LEFT JOIN platform_audit_events_audit_v1_shadow s ON s.stream_key=h.stream_key WHERE s.stream_key IS NULL;
  SELECT COUNT(*) INTO @r43__populated__b3__final__divergent_heads FROM platform_audit_stream_heads h JOIN (SELECT stream_key,sequence_number,event_hash,ROW_NUMBER() OVER (PARTITION BY stream_key ORDER BY sequence_number DESC) rn FROM platform_audit_events_audit_v1_shadow) latest ON latest.stream_key=h.stream_key AND latest.rn=1 WHERE h.last_sequence_number<>latest.sequence_number OR h.last_event_hash<>latest.event_hash;
  SELECT COUNT(*) INTO @r43__populated__b3__final__partial_rows FROM platform_audit_events_audit_v1_shadow s WHERE s.stream_key IS NULL OR s.sequence_number IS NULL OR s.event_id IS NULL OR s.event_hash IS NULL;
  SELECT COUNT(*) INTO @r43__populated__b3__final__field_mismatches FROM platform_audit_events_audit_v1_shadow s JOIN platform_audit_events e ON s.stream_key=e.stream_key AND s.sequence_number=e.sequence_number WHERE NOT (s.event_id<=>e.event_id) OR NOT (s.schema_version<=>e.schema_version) OR NOT (s.occurred_at_utc<=>e.occurred_at_utc) OR NOT (s.action<=>e.action) OR NOT (s.risk_level<=>e.risk_level) OR NOT (s.outcome<=>e.outcome) OR NOT (s.reason_code<=>e.reason_code) OR NOT (s.real_actor_reference<=>e.real_actor_reference) OR NOT (s.effective_actor_reference<=>e.effective_actor_reference) OR NOT (s.affected_subject_reference<=>e.affected_subject_reference) OR NOT (s.correlation_id<=>e.correlation_id) OR NOT (s.request_id<=>e.request_id) OR NOT (s.case_reference<=>e.case_reference) OR NOT (s.resource_type<=>e.resource_type) OR NOT (s.resource_reference<=>e.resource_reference) OR NOT (s.previous_hash<=>e.previous_hash) OR NOT (s.event_hash<=>e.event_hash) OR NOT (s.created_at_utc<=>e.created_at_utc);
  SELECT COUNT(*) INTO @r43__populated__b3__final__json_mismatches FROM platform_audit_events_audit_v1_shadow s JOIN platform_audit_events e ON s.stream_key=e.stream_key AND s.sequence_number=e.sequence_number WHERE NOT (CAST(s.metadata_json AS JSON)<=>CAST(e.metadata_json AS JSON));
  SELECT COUNT(*) INTO @r43__populated__b3__final__source_rows FROM platform_audit_events e;
  SELECT COUNT(DISTINCT e.stream_key) INTO @r43__populated__b3__final__source_streams FROM platform_audit_events e;
  SELECT COUNT(*) INTO @r43__populated__b3__final__shadow_rows FROM platform_audit_events_audit_v1_shadow s;
  SELECT COUNT(DISTINCT s.stream_key) INTO @r43__populated__b3__final__shadow_streams FROM platform_audit_events_audit_v1_shadow s;
  CALL audit_mp01b_fold_source(@r43__populated__b3__final__source_fingerprint);
  CALL audit_mp01b_fold_shadow(@r43__populated__b3__final__shadow_fingerprint);
  CALL audit_mp01b_fold_source_triggers(@r43__populated__b3__final__source_trigger_fingerprint);
  CALL audit_mp01b_fold_shadow_triggers(@r43__populated__b3__final__shadow_trigger_fingerprint);
  CALL audit_mp01b_fold_preserved_event_hash(@r43__populated__b3__final__preserved_event_hash_sha256);
  IF @r43__populated__b3__final__duplicate_pk IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B3 FINAL duplicate_pk'; END IF;
  IF @r43__populated__b3__final__duplicate_event_id IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B3 FINAL duplicate_event_id'; END IF;
  IF @r43__populated__b3__final__duplicate_event_hash IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B3 FINAL duplicate_event_hash'; END IF;
  IF @r43__populated__b3__final__duplicate_canonical_event_id IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B3 FINAL duplicate_canonical_event_id'; END IF;
  IF @r43__populated__b3__final__missing_source IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B3 FINAL missing_source'; END IF;
  IF @r43__populated__b3__final__missing_shadow IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B3 FINAL missing_shadow'; END IF;
  IF @r43__populated__b3__final__missing_heads IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B3 FINAL missing_heads'; END IF;
  IF @r43__populated__b3__final__orphan_heads IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B3 FINAL orphan_heads'; END IF;
  IF @r43__populated__b3__final__divergent_heads IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B3 FINAL divergent_heads'; END IF;
  IF @r43__populated__b3__final__partial_rows IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B3 FINAL partial_rows'; END IF;
  IF @r43__populated__b3__final__field_mismatches IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B3 FINAL field_mismatches'; END IF;
  IF @r43__populated__b3__final__json_mismatches IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B3 FINAL json_mismatches'; END IF;
  IF @r43__populated__b3__final__source_rows IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B3 FINAL source_rows'; END IF;
  IF @r43__populated__b3__final__source_streams IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B3 FINAL source_streams'; END IF;
  IF @r43__populated__b3__final__shadow_rows IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B3 FINAL shadow_rows'; END IF;
  IF @r43__populated__b3__final__shadow_streams IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B3 FINAL shadow_streams'; END IF;
  IF @r43__populated__b3__final__source_fingerprint IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B3 FINAL source_fingerprint'; END IF;
  IF @r43__populated__b3__final__shadow_fingerprint IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B3 FINAL shadow_fingerprint'; END IF;
  IF @r43__populated__b3__final__source_trigger_fingerprint IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B3 FINAL source_trigger_fingerprint'; END IF;
  IF @r43__populated__b3__final__shadow_trigger_fingerprint IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B3 FINAL shadow_trigger_fingerprint'; END IF;
  IF @r43__populated__b3__final__preserved_event_hash_sha256 IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B3 FINAL preserved_event_hash_sha256'; END IF;
  SET @r43_summary_canonical=CONCAT('O21;',CONCAT('F12:duplicate_pk',CONCAT('I',CAST(@r43__populated__b3__final__duplicate_pk AS CHAR),';')),CONCAT('F18:duplicate_event_id',CONCAT('I',CAST(@r43__populated__b3__final__duplicate_event_id AS CHAR),';')),CONCAT('F20:duplicate_event_hash',CONCAT('I',CAST(@r43__populated__b3__final__duplicate_event_hash AS CHAR),';')),CONCAT('F28:duplicate_canonical_event_id',CONCAT('I',CAST(@r43__populated__b3__final__duplicate_canonical_event_id AS CHAR),';')),CONCAT('F14:missing_source',CONCAT('I',CAST(@r43__populated__b3__final__missing_source AS CHAR),';')),CONCAT('F14:missing_shadow',CONCAT('I',CAST(@r43__populated__b3__final__missing_shadow AS CHAR),';')),CONCAT('F13:missing_heads',CONCAT('I',CAST(@r43__populated__b3__final__missing_heads AS CHAR),';')),CONCAT('F12:orphan_heads',CONCAT('I',CAST(@r43__populated__b3__final__orphan_heads AS CHAR),';')),CONCAT('F15:divergent_heads',CONCAT('I',CAST(@r43__populated__b3__final__divergent_heads AS CHAR),';')),CONCAT('F12:partial_rows',CONCAT('I',CAST(@r43__populated__b3__final__partial_rows AS CHAR),';')),CONCAT('F16:field_mismatches',CONCAT('I',CAST(@r43__populated__b3__final__field_mismatches AS CHAR),';')),CONCAT('F15:json_mismatches',CONCAT('I',CAST(@r43__populated__b3__final__json_mismatches AS CHAR),';')),CONCAT('F11:source_rows',CONCAT('I',CAST(@r43__populated__b3__final__source_rows AS CHAR),';')),CONCAT('F14:source_streams',CONCAT('I',CAST(@r43__populated__b3__final__source_streams AS CHAR),';')),CONCAT('F11:shadow_rows',CONCAT('I',CAST(@r43__populated__b3__final__shadow_rows AS CHAR),';')),CONCAT('F14:shadow_streams',CONCAT('I',CAST(@r43__populated__b3__final__shadow_streams AS CHAR),';')),CONCAT('F18:source_fingerprint',CONCAT('S',OCTET_LENGTH(CAST(@r43__populated__b3__final__source_fingerprint AS CHAR)),':',UPPER(HEX(CAST(@r43__populated__b3__final__source_fingerprint AS CHAR))))),CONCAT('F18:shadow_fingerprint',CONCAT('S',OCTET_LENGTH(CAST(@r43__populated__b3__final__shadow_fingerprint AS CHAR)),':',UPPER(HEX(CAST(@r43__populated__b3__final__shadow_fingerprint AS CHAR))))),CONCAT('F26:source_trigger_fingerprint',CONCAT('S',OCTET_LENGTH(CAST(@r43__populated__b3__final__source_trigger_fingerprint AS CHAR)),':',UPPER(HEX(CAST(@r43__populated__b3__final__source_trigger_fingerprint AS CHAR))))),CONCAT('F26:shadow_trigger_fingerprint',CONCAT('S',OCTET_LENGTH(CAST(@r43__populated__b3__final__shadow_trigger_fingerprint AS CHAR)),':',UPPER(HEX(CAST(@r43__populated__b3__final__shadow_trigger_fingerprint AS CHAR))))),CONCAT('F27:preserved_event_hash_sha256',CONCAT('S',OCTET_LENGTH(CAST(@r43__populated__b3__final__preserved_event_hash_sha256 AS CHAR)),':',UPPER(HEX(CAST(@r43__populated__b3__final__preserved_event_hash_sha256 AS CHAR))))));
  SET @r43_summary_base64=REPLACE(TO_BASE64(@r43_summary_canonical),'\n','');
  SET @r43_field_order_sha256='05112339586747154bae48b092f3f5b35274170d81c16be388c49067bac84954';
  SET @r43_payload_canonical=CONCAT('O7;',CONCAT('F6:schema',CONCAT('S',OCTET_LENGTH(CAST('mxmed.audit.mp01b.canonical.v1' AS CHAR)),':',UPPER(HEX(CAST('mxmed.audit.mp01b.canonical.v1' AS CHAR))))),CONCAT('F5:route',CONCAT('S',OCTET_LENGTH(CAST('POPULATED' AS CHAR)),':',UPPER(HEX(CAST('POPULATED' AS CHAR))))),CONCAT('F5:phase',CONCAT('S',OCTET_LENGTH(CAST('B3' AS CHAR)),':',UPPER(HEX(CAST('B3' AS CHAR))))),CONCAT('F5:state',CONCAT('S',OCTET_LENGTH(CAST('FINAL' AS CHAR)),':',UPPER(HEX(CAST('FINAL' AS CHAR))))),CONCAT('F18:field_order_sha256',CONCAT('S',OCTET_LENGTH(CAST(@r43_field_order_sha256 AS CHAR)),':',UPPER(HEX(CAST(@r43_field_order_sha256 AS CHAR))))),CONCAT('F7:summary',CONCAT('S',OCTET_LENGTH(CAST(@r43_summary_base64 AS CHAR)),':',UPPER(HEX(CAST(@r43_summary_base64 AS CHAR))))),CONCAT('F23:previous_receipt_sha256',CONCAT('S',OCTET_LENGTH(CAST(@previous_receipt_sha256_populated AS CHAR)),':',UPPER(HEX(CAST(@previous_receipt_sha256_populated AS CHAR))))));
  SET @r43_payload_base64=REPLACE(TO_BASE64(@r43_payload_canonical),'\n','');
  SET @r43_receipt_sha256=LOWER(SHA2(@r43_payload_canonical,256));
  CALL audit_mp01b_assert_b3_final();
  INSERT INTO audit_mp01b_phase_receipts(route,phase,state,payload_canonical,payload_base64,previous_receipt_sha256,receipt_sha256) VALUES('POPULATED','B3','FINAL',@r43_payload_canonical,@r43_payload_base64,@previous_receipt_sha256_populated,@r43_receipt_sha256);
  SET @previous_receipt_sha256_populated=@r43_receipt_sha256;
 ELSE SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='invalid audit route';
 END IF;
END$$
DELIMITER ;
CALL audit_mp01b_emit_b3(@audit_mp01b_route);
-- R54_CANONICAL_NULL_FILTER_3: route-independent B3 postcondition.
SELECT COALESCE(SUM(x.excess),0) INTO @r43_pc_duplicate_canonical_event_id FROM (SELECT COUNT(*)-1 AS excess FROM platform_audit_events_audit_v1_shadow s WHERE s.canonical_event_id IS NOT NULL GROUP BY s.canonical_event_id HAVING COUNT(*)>1) x;
IF @r43_pc_duplicate_canonical_event_id<>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='B3 duplicate canonical_event_id'; END IF;
SET @r43_execution_state='B3_EXECUTED';
