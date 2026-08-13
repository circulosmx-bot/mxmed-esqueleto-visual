-- AUDIT-MP01B B2 repository promotion derived from certified R45 + R54; not executed by preparation
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
DROP PROCEDURE IF EXISTS audit_mp01b_emit_b2;
DELIMITER $$
CREATE PROCEDURE audit_mp01b_emit_b2(IN p_route VARCHAR(16))
BEGIN
 CALL audit_mp01b_assert_route(p_route);
 SET @r43_batch_identifier=UUID();
 INSERT INTO platform_audit_events_audit_v1_shadow SELECT e.*,NULL FROM platform_audit_events e WHERE p_route='POPULATED' AND NOT EXISTS (SELECT 1 FROM platform_audit_events_audit_v1_shadow s WHERE s.stream_key=e.stream_key AND s.sequence_number=e.sequence_number);
 SET @r43_last_inserted_count=ROW_COUNT();
 IF p_route='EMPTY' THEN
  -- R49 EMPTY B2 contract: batch id and inserted count bind to this execution; resume is NO_ROWS.
  SET @r43__empty__b2__complete__inserted_count=@r43_last_inserted_count;
  SET @r43__empty__b2__complete__route=CAST('EMPTY' AS CHAR);
  SET @r43__empty__b2__complete__phase=CAST('B2' AS CHAR);
  SET @r43__empty__b2__complete__state=CAST('COMPLETE' AS CHAR);
  SET @r43__empty__b2__complete__batch_identifier=CAST(@r43_batch_identifier AS CHAR);
  SET @r43__empty__b2__complete__batch_min_stream_key=CAST('NO_ROWS' AS CHAR);
  SET @r43__empty__b2__complete__batch_max_stream_key=CAST('NO_ROWS' AS CHAR);
  SET @r43__empty__b2__complete__resume_stream_key=CAST('NO_ROWS' AS CHAR);
  SET @r43__empty__b2__complete__resume_sequence_number=CAST('NO_ROWS' AS CHAR);
  SELECT COUNT(*) INTO @r43__empty__b2__complete__source_row_count FROM platform_audit_events e;
  SELECT COUNT(DISTINCT e.stream_key) INTO @r43__empty__b2__complete__source_stream_count FROM platform_audit_events e;
  SELECT COUNT(*) INTO @r43__empty__b2__complete__shadow_row_count FROM platform_audit_events_audit_v1_shadow s;
  SELECT COUNT(DISTINCT s.stream_key) INTO @r43__empty__b2__complete__shadow_stream_count FROM platform_audit_events_audit_v1_shadow s;
  SELECT COUNT(DISTINCT e.stream_key,e.sequence_number) INTO @r43__empty__b2__complete__keys_count FROM platform_audit_events e;
  SELECT COUNT(*) INTO @r43__empty__b2__complete__foreign_rows FROM platform_audit_events_audit_v1_shadow s LEFT JOIN platform_audit_events e ON s.stream_key=e.stream_key AND s.sequence_number=e.sequence_number WHERE e.stream_key IS NULL;
  SELECT COUNT(*) INTO @r43__empty__b2__complete__partial_rows FROM platform_audit_events e WHERE e.stream_key IS NULL OR e.sequence_number IS NULL OR e.event_id IS NULL OR e.event_hash IS NULL;
  SELECT COALESCE(SUM(x.excess),0) INTO @r43__empty__b2__complete__ambiguous_violations FROM (SELECT COUNT(*)-1 AS excess FROM platform_audit_events_audit_v1_shadow s GROUP BY s.stream_key,s.sequence_number HAVING COUNT(*)>1) x;
  SELECT COUNT(*) INTO @r43__empty__b2__complete__field_mismatches FROM platform_audit_events_audit_v1_shadow s JOIN platform_audit_events e ON s.stream_key=e.stream_key AND s.sequence_number=e.sequence_number WHERE NOT (s.event_id<=>e.event_id) OR NOT (s.schema_version<=>e.schema_version) OR NOT (s.occurred_at_utc<=>e.occurred_at_utc) OR NOT (s.action<=>e.action) OR NOT (s.risk_level<=>e.risk_level) OR NOT (s.outcome<=>e.outcome) OR NOT (s.reason_code<=>e.reason_code) OR NOT (s.real_actor_reference<=>e.real_actor_reference) OR NOT (s.effective_actor_reference<=>e.effective_actor_reference) OR NOT (s.affected_subject_reference<=>e.affected_subject_reference) OR NOT (s.correlation_id<=>e.correlation_id) OR NOT (s.request_id<=>e.request_id) OR NOT (s.case_reference<=>e.case_reference) OR NOT (s.resource_type<=>e.resource_type) OR NOT (s.resource_reference<=>e.resource_reference) OR NOT (s.previous_hash<=>e.previous_hash) OR NOT (s.event_hash<=>e.event_hash) OR NOT (s.created_at_utc<=>e.created_at_utc);
  SELECT COUNT(*) INTO @r43__empty__b2__complete__json_mismatches FROM platform_audit_events_audit_v1_shadow s JOIN platform_audit_events e ON s.stream_key=e.stream_key AND s.sequence_number=e.sequence_number WHERE NOT (CAST(s.metadata_json AS JSON)<=>CAST(e.metadata_json AS JSON));
  CALL audit_mp01b_fold_source(@r43__empty__b2__complete__source_fingerprint);
  CALL audit_mp01b_fold_shadow(@r43__empty__b2__complete__shadow_fingerprint);
  CALL audit_mp01b_fold_source_triggers(@r43__empty__b2__complete__source_trigger_fingerprint);
  CALL audit_mp01b_fold_shadow_triggers(@r43__empty__b2__complete__shadow_trigger_fingerprint);
  CALL audit_mp01b_fold_preserved_event_hash(@r43__empty__b2__complete__preserved_event_hash_sha256);
  IF @r43__empty__b2__complete__route IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B2 COMPLETE route'; END IF;
  IF @r43__empty__b2__complete__phase IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B2 COMPLETE phase'; END IF;
  IF @r43__empty__b2__complete__state IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B2 COMPLETE state'; END IF;
  IF @r43__empty__b2__complete__batch_identifier IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B2 COMPLETE batch_identifier'; END IF;
  IF @r43__empty__b2__complete__batch_min_stream_key IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B2 COMPLETE batch_min_stream_key'; END IF;
  IF @r43__empty__b2__complete__batch_max_stream_key IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B2 COMPLETE batch_max_stream_key'; END IF;
  IF @r43__empty__b2__complete__resume_stream_key IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B2 COMPLETE resume_stream_key'; END IF;
  IF @r43__empty__b2__complete__resume_sequence_number IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B2 COMPLETE resume_sequence_number'; END IF;
  IF @r43__empty__b2__complete__source_row_count IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B2 COMPLETE source_row_count'; END IF;
  IF @r43__empty__b2__complete__source_stream_count IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B2 COMPLETE source_stream_count'; END IF;
  IF @r43__empty__b2__complete__shadow_row_count IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B2 COMPLETE shadow_row_count'; END IF;
  IF @r43__empty__b2__complete__shadow_stream_count IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B2 COMPLETE shadow_stream_count'; END IF;
  IF @r43__empty__b2__complete__inserted_count IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B2 COMPLETE inserted_count'; END IF;
  IF @r43__empty__b2__complete__keys_count IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B2 COMPLETE keys_count'; END IF;
  IF @r43__empty__b2__complete__foreign_rows IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B2 COMPLETE foreign_rows'; END IF;
  IF @r43__empty__b2__complete__partial_rows IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B2 COMPLETE partial_rows'; END IF;
  IF @r43__empty__b2__complete__ambiguous_violations IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B2 COMPLETE ambiguous_violations'; END IF;
  IF @r43__empty__b2__complete__field_mismatches IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B2 COMPLETE field_mismatches'; END IF;
  IF @r43__empty__b2__complete__json_mismatches IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B2 COMPLETE json_mismatches'; END IF;
  IF @r43__empty__b2__complete__source_fingerprint IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B2 COMPLETE source_fingerprint'; END IF;
  IF @r43__empty__b2__complete__shadow_fingerprint IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B2 COMPLETE shadow_fingerprint'; END IF;
  IF @r43__empty__b2__complete__source_trigger_fingerprint IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B2 COMPLETE source_trigger_fingerprint'; END IF;
  IF @r43__empty__b2__complete__shadow_trigger_fingerprint IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B2 COMPLETE shadow_trigger_fingerprint'; END IF;
  IF @r43__empty__b2__complete__preserved_event_hash_sha256 IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null EMPTY B2 COMPLETE preserved_event_hash_sha256'; END IF;
  SET @r43_summary_canonical=CONCAT('O24;',CONCAT('F5:route',CONCAT('S',OCTET_LENGTH(CAST(@r43__empty__b2__complete__route AS CHAR)),':',UPPER(HEX(CAST(@r43__empty__b2__complete__route AS CHAR))))),CONCAT('F5:phase',CONCAT('S',OCTET_LENGTH(CAST(@r43__empty__b2__complete__phase AS CHAR)),':',UPPER(HEX(CAST(@r43__empty__b2__complete__phase AS CHAR))))),CONCAT('F5:state',CONCAT('S',OCTET_LENGTH(CAST(@r43__empty__b2__complete__state AS CHAR)),':',UPPER(HEX(CAST(@r43__empty__b2__complete__state AS CHAR))))),CONCAT('F16:batch_identifier',CONCAT('S',OCTET_LENGTH(CAST(@r43__empty__b2__complete__batch_identifier AS CHAR)),':',UPPER(HEX(CAST(@r43__empty__b2__complete__batch_identifier AS CHAR))))),CONCAT('F20:batch_min_stream_key',CONCAT('S',OCTET_LENGTH(CAST(@r43__empty__b2__complete__batch_min_stream_key AS CHAR)),':',UPPER(HEX(CAST(@r43__empty__b2__complete__batch_min_stream_key AS CHAR))))),CONCAT('F20:batch_max_stream_key',CONCAT('S',OCTET_LENGTH(CAST(@r43__empty__b2__complete__batch_max_stream_key AS CHAR)),':',UPPER(HEX(CAST(@r43__empty__b2__complete__batch_max_stream_key AS CHAR))))),CONCAT('F17:resume_stream_key',CONCAT('S',OCTET_LENGTH(CAST(@r43__empty__b2__complete__resume_stream_key AS CHAR)),':',UPPER(HEX(CAST(@r43__empty__b2__complete__resume_stream_key AS CHAR))))),CONCAT('F22:resume_sequence_number',CONCAT('S',OCTET_LENGTH(CAST(@r43__empty__b2__complete__resume_sequence_number AS CHAR)),':',UPPER(HEX(CAST(@r43__empty__b2__complete__resume_sequence_number AS CHAR))))),CONCAT('F16:source_row_count',CONCAT('I',CAST(@r43__empty__b2__complete__source_row_count AS CHAR),';')),CONCAT('F19:source_stream_count',CONCAT('I',CAST(@r43__empty__b2__complete__source_stream_count AS CHAR),';')),CONCAT('F16:shadow_row_count',CONCAT('I',CAST(@r43__empty__b2__complete__shadow_row_count AS CHAR),';')),CONCAT('F19:shadow_stream_count',CONCAT('I',CAST(@r43__empty__b2__complete__shadow_stream_count AS CHAR),';')),CONCAT('F14:inserted_count',CONCAT('I',CAST(@r43__empty__b2__complete__inserted_count AS CHAR),';')),CONCAT('F10:keys_count',CONCAT('I',CAST(@r43__empty__b2__complete__keys_count AS CHAR),';')),CONCAT('F12:foreign_rows',CONCAT('I',CAST(@r43__empty__b2__complete__foreign_rows AS CHAR),';')),CONCAT('F12:partial_rows',CONCAT('I',CAST(@r43__empty__b2__complete__partial_rows AS CHAR),';')),CONCAT('F20:ambiguous_violations',CONCAT('I',CAST(@r43__empty__b2__complete__ambiguous_violations AS CHAR),';')),CONCAT('F16:field_mismatches',CONCAT('I',CAST(@r43__empty__b2__complete__field_mismatches AS CHAR),';')),CONCAT('F15:json_mismatches',CONCAT('I',CAST(@r43__empty__b2__complete__json_mismatches AS CHAR),';')),CONCAT('F18:source_fingerprint',CONCAT('S',OCTET_LENGTH(CAST(@r43__empty__b2__complete__source_fingerprint AS CHAR)),':',UPPER(HEX(CAST(@r43__empty__b2__complete__source_fingerprint AS CHAR))))),CONCAT('F18:shadow_fingerprint',CONCAT('S',OCTET_LENGTH(CAST(@r43__empty__b2__complete__shadow_fingerprint AS CHAR)),':',UPPER(HEX(CAST(@r43__empty__b2__complete__shadow_fingerprint AS CHAR))))),CONCAT('F26:source_trigger_fingerprint',CONCAT('S',OCTET_LENGTH(CAST(@r43__empty__b2__complete__source_trigger_fingerprint AS CHAR)),':',UPPER(HEX(CAST(@r43__empty__b2__complete__source_trigger_fingerprint AS CHAR))))),CONCAT('F26:shadow_trigger_fingerprint',CONCAT('S',OCTET_LENGTH(CAST(@r43__empty__b2__complete__shadow_trigger_fingerprint AS CHAR)),':',UPPER(HEX(CAST(@r43__empty__b2__complete__shadow_trigger_fingerprint AS CHAR))))),CONCAT('F27:preserved_event_hash_sha256',CONCAT('S',OCTET_LENGTH(CAST(@r43__empty__b2__complete__preserved_event_hash_sha256 AS CHAR)),':',UPPER(HEX(CAST(@r43__empty__b2__complete__preserved_event_hash_sha256 AS CHAR))))));
  SET @r43_summary_base64=REPLACE(TO_BASE64(@r43_summary_canonical),'\n','');
  SET @r43_field_order_sha256='e650ce203e683a39ae1d8c9da2abaed891241b828953b8586b12bf4b8131eb56';
  SET @r43_payload_canonical=CONCAT('O7;',CONCAT('F6:schema',CONCAT('S',OCTET_LENGTH(CAST('mxmed.audit.mp01b.canonical.v1' AS CHAR)),':',UPPER(HEX(CAST('mxmed.audit.mp01b.canonical.v1' AS CHAR))))),CONCAT('F5:route',CONCAT('S',OCTET_LENGTH(CAST('EMPTY' AS CHAR)),':',UPPER(HEX(CAST('EMPTY' AS CHAR))))),CONCAT('F5:phase',CONCAT('S',OCTET_LENGTH(CAST('B2' AS CHAR)),':',UPPER(HEX(CAST('B2' AS CHAR))))),CONCAT('F5:state',CONCAT('S',OCTET_LENGTH(CAST('COMPLETE' AS CHAR)),':',UPPER(HEX(CAST('COMPLETE' AS CHAR))))),CONCAT('F18:field_order_sha256',CONCAT('S',OCTET_LENGTH(CAST(@r43_field_order_sha256 AS CHAR)),':',UPPER(HEX(CAST(@r43_field_order_sha256 AS CHAR))))),CONCAT('F7:summary',CONCAT('S',OCTET_LENGTH(CAST(@r43_summary_base64 AS CHAR)),':',UPPER(HEX(CAST(@r43_summary_base64 AS CHAR))))),CONCAT('F23:previous_receipt_sha256',CONCAT('S',OCTET_LENGTH(CAST(@previous_receipt_sha256_empty AS CHAR)),':',UPPER(HEX(CAST(@previous_receipt_sha256_empty AS CHAR))))));
  SET @r43_payload_base64=REPLACE(TO_BASE64(@r43_payload_canonical),'\n','');
  SET @r43_receipt_sha256=LOWER(SHA2(@r43_payload_canonical,256));
  CALL audit_mp01b_assert_b2_complete();
  INSERT INTO audit_mp01b_phase_receipts(route,phase,state,payload_canonical,payload_base64,previous_receipt_sha256,receipt_sha256) VALUES('EMPTY','B2','COMPLETE',@r43_payload_canonical,@r43_payload_base64,@previous_receipt_sha256_empty,@r43_receipt_sha256);
  SET @previous_receipt_sha256_empty=@r43_receipt_sha256;
 ELSEIF p_route='POPULATED' THEN
  SET @r43__populated__b2__in_progress__inserted_count=@r43_last_inserted_count;
  SET @r43__populated__b2__in_progress__route=CAST('POPULATED' AS CHAR);
  SET @r43__populated__b2__in_progress__phase=CAST('B2' AS CHAR);
  SET @r43__populated__b2__in_progress__state=CAST('IN_PROGRESS' AS CHAR);
  SET @r43__populated__b2__in_progress__batch_identifier=CAST(@r43_batch_identifier AS CHAR);
  SELECT COALESCE(MIN(e.stream_key),'NO_ROWS') INTO @r43__populated__b2__in_progress__batch_min_stream_key FROM platform_audit_events e WHERE e.stream_key IS NOT NULL;
  SELECT COALESCE(MAX(e.stream_key),'NO_ROWS') INTO @r43__populated__b2__in_progress__batch_max_stream_key FROM platform_audit_events e WHERE e.stream_key IS NOT NULL;
  SELECT COALESCE((SELECT e.stream_key FROM platform_audit_events e ORDER BY e.stream_key DESC,e.sequence_number DESC LIMIT 1),'NO_ROWS') INTO @r43__populated__b2__in_progress__resume_stream_key;
  SELECT COALESCE((SELECT CAST(e.sequence_number AS CHAR) FROM platform_audit_events e ORDER BY e.stream_key DESC,e.sequence_number DESC LIMIT 1),'NO_ROWS') INTO @r43__populated__b2__in_progress__resume_sequence_number;
  SELECT COUNT(*) INTO @r43__populated__b2__in_progress__source_row_count FROM platform_audit_events e;
  SELECT COUNT(DISTINCT e.stream_key) INTO @r43__populated__b2__in_progress__source_stream_count FROM platform_audit_events e;
  SELECT COUNT(*) INTO @r43__populated__b2__in_progress__shadow_row_count FROM platform_audit_events_audit_v1_shadow s;
  SELECT COUNT(DISTINCT s.stream_key) INTO @r43__populated__b2__in_progress__shadow_stream_count FROM platform_audit_events_audit_v1_shadow s;
  SELECT COUNT(DISTINCT e.stream_key,e.sequence_number) INTO @r43__populated__b2__in_progress__keys_count FROM platform_audit_events e;
  SELECT COUNT(*) INTO @r43__populated__b2__in_progress__foreign_rows FROM platform_audit_events_audit_v1_shadow s LEFT JOIN platform_audit_events e ON s.stream_key=e.stream_key AND s.sequence_number=e.sequence_number WHERE e.stream_key IS NULL;
  SELECT COUNT(*) INTO @r43__populated__b2__in_progress__partial_rows FROM platform_audit_events e WHERE e.stream_key IS NULL OR e.sequence_number IS NULL OR e.event_id IS NULL OR e.event_hash IS NULL;
  SELECT COALESCE(SUM(x.excess),0) INTO @r43__populated__b2__in_progress__ambiguous_violations FROM (SELECT COUNT(*)-1 AS excess FROM platform_audit_events_audit_v1_shadow s GROUP BY s.stream_key,s.sequence_number HAVING COUNT(*)>1) x;
  SELECT COUNT(*) INTO @r43__populated__b2__in_progress__field_mismatches FROM platform_audit_events_audit_v1_shadow s JOIN platform_audit_events e ON s.stream_key=e.stream_key AND s.sequence_number=e.sequence_number WHERE NOT (s.event_id<=>e.event_id) OR NOT (s.schema_version<=>e.schema_version) OR NOT (s.occurred_at_utc<=>e.occurred_at_utc) OR NOT (s.action<=>e.action) OR NOT (s.risk_level<=>e.risk_level) OR NOT (s.outcome<=>e.outcome) OR NOT (s.reason_code<=>e.reason_code) OR NOT (s.real_actor_reference<=>e.real_actor_reference) OR NOT (s.effective_actor_reference<=>e.effective_actor_reference) OR NOT (s.affected_subject_reference<=>e.affected_subject_reference) OR NOT (s.correlation_id<=>e.correlation_id) OR NOT (s.request_id<=>e.request_id) OR NOT (s.case_reference<=>e.case_reference) OR NOT (s.resource_type<=>e.resource_type) OR NOT (s.resource_reference<=>e.resource_reference) OR NOT (s.previous_hash<=>e.previous_hash) OR NOT (s.event_hash<=>e.event_hash) OR NOT (s.created_at_utc<=>e.created_at_utc);
  SELECT COUNT(*) INTO @r43__populated__b2__in_progress__json_mismatches FROM platform_audit_events_audit_v1_shadow s JOIN platform_audit_events e ON s.stream_key=e.stream_key AND s.sequence_number=e.sequence_number WHERE NOT (CAST(s.metadata_json AS JSON)<=>CAST(e.metadata_json AS JSON));
  CALL audit_mp01b_fold_source(@r43__populated__b2__in_progress__source_fingerprint);
  CALL audit_mp01b_fold_shadow(@r43__populated__b2__in_progress__shadow_fingerprint);
  CALL audit_mp01b_fold_source_triggers(@r43__populated__b2__in_progress__source_trigger_fingerprint);
  CALL audit_mp01b_fold_shadow_triggers(@r43__populated__b2__in_progress__shadow_trigger_fingerprint);
  CALL audit_mp01b_fold_preserved_event_hash(@r43__populated__b2__in_progress__preserved_event_hash_sha256);
  IF @r43__populated__b2__in_progress__route IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B2 IN_PROGRESS route'; END IF;
  IF @r43__populated__b2__in_progress__phase IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B2 IN_PROGRESS phase'; END IF;
  IF @r43__populated__b2__in_progress__state IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B2 IN_PROGRESS state'; END IF;
  IF @r43__populated__b2__in_progress__batch_identifier IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B2 IN_PROGRESS batch_identifier'; END IF;
  IF @r43__populated__b2__in_progress__batch_min_stream_key IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B2 IN_PROGRESS batch_min_stream_key'; END IF;
  IF @r43__populated__b2__in_progress__batch_max_stream_key IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B2 IN_PROGRESS batch_max_stream_key'; END IF;
  IF @r43__populated__b2__in_progress__resume_stream_key IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B2 IN_PROGRESS resume_stream_key'; END IF;
  IF @r43__populated__b2__in_progress__resume_sequence_number IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B2 IN_PROGRESS resume_sequence_number'; END IF;
  IF @r43__populated__b2__in_progress__source_row_count IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B2 IN_PROGRESS source_row_count'; END IF;
  IF @r43__populated__b2__in_progress__source_stream_count IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B2 IN_PROGRESS source_stream_count'; END IF;
  IF @r43__populated__b2__in_progress__shadow_row_count IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B2 IN_PROGRESS shadow_row_count'; END IF;
  IF @r43__populated__b2__in_progress__shadow_stream_count IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B2 IN_PROGRESS shadow_stream_count'; END IF;
  IF @r43__populated__b2__in_progress__inserted_count IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B2 IN_PROGRESS inserted_count'; END IF;
  IF @r43__populated__b2__in_progress__keys_count IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B2 IN_PROGRESS keys_count'; END IF;
  IF @r43__populated__b2__in_progress__foreign_rows IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B2 IN_PROGRESS foreign_rows'; END IF;
  IF @r43__populated__b2__in_progress__partial_rows IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B2 IN_PROGRESS partial_rows'; END IF;
  IF @r43__populated__b2__in_progress__ambiguous_violations IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B2 IN_PROGRESS ambiguous_violations'; END IF;
  IF @r43__populated__b2__in_progress__field_mismatches IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B2 IN_PROGRESS field_mismatches'; END IF;
  IF @r43__populated__b2__in_progress__json_mismatches IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B2 IN_PROGRESS json_mismatches'; END IF;
  IF @r43__populated__b2__in_progress__source_fingerprint IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B2 IN_PROGRESS source_fingerprint'; END IF;
  IF @r43__populated__b2__in_progress__shadow_fingerprint IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B2 IN_PROGRESS shadow_fingerprint'; END IF;
  IF @r43__populated__b2__in_progress__source_trigger_fingerprint IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B2 IN_PROGRESS source_trigger_fingerprint'; END IF;
  IF @r43__populated__b2__in_progress__shadow_trigger_fingerprint IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B2 IN_PROGRESS shadow_trigger_fingerprint'; END IF;
  IF @r43__populated__b2__in_progress__preserved_event_hash_sha256 IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B2 IN_PROGRESS preserved_event_hash_sha256'; END IF;
  SET @r43_summary_canonical=CONCAT('O24;',CONCAT('F5:route',CONCAT('S',OCTET_LENGTH(CAST(@r43__populated__b2__in_progress__route AS CHAR)),':',UPPER(HEX(CAST(@r43__populated__b2__in_progress__route AS CHAR))))),CONCAT('F5:phase',CONCAT('S',OCTET_LENGTH(CAST(@r43__populated__b2__in_progress__phase AS CHAR)),':',UPPER(HEX(CAST(@r43__populated__b2__in_progress__phase AS CHAR))))),CONCAT('F5:state',CONCAT('S',OCTET_LENGTH(CAST(@r43__populated__b2__in_progress__state AS CHAR)),':',UPPER(HEX(CAST(@r43__populated__b2__in_progress__state AS CHAR))))),CONCAT('F16:batch_identifier',CONCAT('S',OCTET_LENGTH(CAST(@r43__populated__b2__in_progress__batch_identifier AS CHAR)),':',UPPER(HEX(CAST(@r43__populated__b2__in_progress__batch_identifier AS CHAR))))),CONCAT('F20:batch_min_stream_key',CONCAT('S',OCTET_LENGTH(CAST(@r43__populated__b2__in_progress__batch_min_stream_key AS CHAR)),':',UPPER(HEX(CAST(@r43__populated__b2__in_progress__batch_min_stream_key AS CHAR))))),CONCAT('F20:batch_max_stream_key',CONCAT('S',OCTET_LENGTH(CAST(@r43__populated__b2__in_progress__batch_max_stream_key AS CHAR)),':',UPPER(HEX(CAST(@r43__populated__b2__in_progress__batch_max_stream_key AS CHAR))))),CONCAT('F17:resume_stream_key',CONCAT('S',OCTET_LENGTH(CAST(@r43__populated__b2__in_progress__resume_stream_key AS CHAR)),':',UPPER(HEX(CAST(@r43__populated__b2__in_progress__resume_stream_key AS CHAR))))),CONCAT('F22:resume_sequence_number',CONCAT('S',OCTET_LENGTH(CAST(@r43__populated__b2__in_progress__resume_sequence_number AS CHAR)),':',UPPER(HEX(CAST(@r43__populated__b2__in_progress__resume_sequence_number AS CHAR))))),CONCAT('F16:source_row_count',CONCAT('I',CAST(@r43__populated__b2__in_progress__source_row_count AS CHAR),';')),CONCAT('F19:source_stream_count',CONCAT('I',CAST(@r43__populated__b2__in_progress__source_stream_count AS CHAR),';')),CONCAT('F16:shadow_row_count',CONCAT('I',CAST(@r43__populated__b2__in_progress__shadow_row_count AS CHAR),';')),CONCAT('F19:shadow_stream_count',CONCAT('I',CAST(@r43__populated__b2__in_progress__shadow_stream_count AS CHAR),';')),CONCAT('F14:inserted_count',CONCAT('I',CAST(@r43__populated__b2__in_progress__inserted_count AS CHAR),';')),CONCAT('F10:keys_count',CONCAT('I',CAST(@r43__populated__b2__in_progress__keys_count AS CHAR),';')),CONCAT('F12:foreign_rows',CONCAT('I',CAST(@r43__populated__b2__in_progress__foreign_rows AS CHAR),';')),CONCAT('F12:partial_rows',CONCAT('I',CAST(@r43__populated__b2__in_progress__partial_rows AS CHAR),';')),CONCAT('F20:ambiguous_violations',CONCAT('I',CAST(@r43__populated__b2__in_progress__ambiguous_violations AS CHAR),';')),CONCAT('F16:field_mismatches',CONCAT('I',CAST(@r43__populated__b2__in_progress__field_mismatches AS CHAR),';')),CONCAT('F15:json_mismatches',CONCAT('I',CAST(@r43__populated__b2__in_progress__json_mismatches AS CHAR),';')),CONCAT('F18:source_fingerprint',CONCAT('S',OCTET_LENGTH(CAST(@r43__populated__b2__in_progress__source_fingerprint AS CHAR)),':',UPPER(HEX(CAST(@r43__populated__b2__in_progress__source_fingerprint AS CHAR))))),CONCAT('F18:shadow_fingerprint',CONCAT('S',OCTET_LENGTH(CAST(@r43__populated__b2__in_progress__shadow_fingerprint AS CHAR)),':',UPPER(HEX(CAST(@r43__populated__b2__in_progress__shadow_fingerprint AS CHAR))))),CONCAT('F26:source_trigger_fingerprint',CONCAT('S',OCTET_LENGTH(CAST(@r43__populated__b2__in_progress__source_trigger_fingerprint AS CHAR)),':',UPPER(HEX(CAST(@r43__populated__b2__in_progress__source_trigger_fingerprint AS CHAR))))),CONCAT('F26:shadow_trigger_fingerprint',CONCAT('S',OCTET_LENGTH(CAST(@r43__populated__b2__in_progress__shadow_trigger_fingerprint AS CHAR)),':',UPPER(HEX(CAST(@r43__populated__b2__in_progress__shadow_trigger_fingerprint AS CHAR))))),CONCAT('F27:preserved_event_hash_sha256',CONCAT('S',OCTET_LENGTH(CAST(@r43__populated__b2__in_progress__preserved_event_hash_sha256 AS CHAR)),':',UPPER(HEX(CAST(@r43__populated__b2__in_progress__preserved_event_hash_sha256 AS CHAR))))));
  SET @r43_summary_base64=REPLACE(TO_BASE64(@r43_summary_canonical),'\n','');
  SET @r43_field_order_sha256='e650ce203e683a39ae1d8c9da2abaed891241b828953b8586b12bf4b8131eb56';
  SET @r43_payload_canonical=CONCAT('O7;',CONCAT('F6:schema',CONCAT('S',OCTET_LENGTH(CAST('mxmed.audit.mp01b.canonical.v1' AS CHAR)),':',UPPER(HEX(CAST('mxmed.audit.mp01b.canonical.v1' AS CHAR))))),CONCAT('F5:route',CONCAT('S',OCTET_LENGTH(CAST('POPULATED' AS CHAR)),':',UPPER(HEX(CAST('POPULATED' AS CHAR))))),CONCAT('F5:phase',CONCAT('S',OCTET_LENGTH(CAST('B2' AS CHAR)),':',UPPER(HEX(CAST('B2' AS CHAR))))),CONCAT('F5:state',CONCAT('S',OCTET_LENGTH(CAST('IN_PROGRESS' AS CHAR)),':',UPPER(HEX(CAST('IN_PROGRESS' AS CHAR))))),CONCAT('F18:field_order_sha256',CONCAT('S',OCTET_LENGTH(CAST(@r43_field_order_sha256 AS CHAR)),':',UPPER(HEX(CAST(@r43_field_order_sha256 AS CHAR))))),CONCAT('F7:summary',CONCAT('S',OCTET_LENGTH(CAST(@r43_summary_base64 AS CHAR)),':',UPPER(HEX(CAST(@r43_summary_base64 AS CHAR))))),CONCAT('F23:previous_receipt_sha256',CONCAT('S',OCTET_LENGTH(CAST(@previous_receipt_sha256_populated AS CHAR)),':',UPPER(HEX(CAST(@previous_receipt_sha256_populated AS CHAR))))));
  SET @r43_payload_base64=REPLACE(TO_BASE64(@r43_payload_canonical),'\n','');
  SET @r43_receipt_sha256=LOWER(SHA2(@r43_payload_canonical,256));
  INSERT INTO audit_mp01b_phase_receipts(route,phase,state,payload_canonical,payload_base64,previous_receipt_sha256,receipt_sha256) VALUES('POPULATED','B2','IN_PROGRESS',@r43_payload_canonical,@r43_payload_base64,@previous_receipt_sha256_populated,@r43_receipt_sha256);
  SET @previous_receipt_sha256_populated=@r43_receipt_sha256;
  SET @r43__populated__b2__complete__inserted_count=@r43_last_inserted_count;
  SET @r43__populated__b2__complete__route=CAST('POPULATED' AS CHAR);
  SET @r43__populated__b2__complete__phase=CAST('B2' AS CHAR);
  SET @r43__populated__b2__complete__state=CAST('COMPLETE' AS CHAR);
  SET @r43__populated__b2__complete__batch_identifier=CAST(@r43_batch_identifier AS CHAR);
  SELECT COALESCE(MIN(e.stream_key),'NO_ROWS') INTO @r43__populated__b2__complete__batch_min_stream_key FROM platform_audit_events e WHERE e.stream_key IS NOT NULL;
  SELECT COALESCE(MAX(e.stream_key),'NO_ROWS') INTO @r43__populated__b2__complete__batch_max_stream_key FROM platform_audit_events e WHERE e.stream_key IS NOT NULL;
  SELECT COALESCE((SELECT e.stream_key FROM platform_audit_events e ORDER BY e.stream_key DESC,e.sequence_number DESC LIMIT 1),'NO_ROWS') INTO @r43__populated__b2__complete__resume_stream_key;
  SELECT COALESCE((SELECT CAST(e.sequence_number AS CHAR) FROM platform_audit_events e ORDER BY e.stream_key DESC,e.sequence_number DESC LIMIT 1),'NO_ROWS') INTO @r43__populated__b2__complete__resume_sequence_number;
  SELECT COUNT(*) INTO @r43__populated__b2__complete__source_row_count FROM platform_audit_events e;
  SELECT COUNT(DISTINCT e.stream_key) INTO @r43__populated__b2__complete__source_stream_count FROM platform_audit_events e;
  SELECT COUNT(*) INTO @r43__populated__b2__complete__shadow_row_count FROM platform_audit_events_audit_v1_shadow s;
  SELECT COUNT(DISTINCT s.stream_key) INTO @r43__populated__b2__complete__shadow_stream_count FROM platform_audit_events_audit_v1_shadow s;
  SELECT COUNT(DISTINCT e.stream_key,e.sequence_number) INTO @r43__populated__b2__complete__keys_count FROM platform_audit_events e;
  SELECT COUNT(*) INTO @r43__populated__b2__complete__foreign_rows FROM platform_audit_events_audit_v1_shadow s LEFT JOIN platform_audit_events e ON s.stream_key=e.stream_key AND s.sequence_number=e.sequence_number WHERE e.stream_key IS NULL;
  SELECT COUNT(*) INTO @r43__populated__b2__complete__partial_rows FROM platform_audit_events e WHERE e.stream_key IS NULL OR e.sequence_number IS NULL OR e.event_id IS NULL OR e.event_hash IS NULL;
  SELECT COALESCE(SUM(x.excess),0) INTO @r43__populated__b2__complete__ambiguous_violations FROM (SELECT COUNT(*)-1 AS excess FROM platform_audit_events_audit_v1_shadow s GROUP BY s.stream_key,s.sequence_number HAVING COUNT(*)>1) x;
  SELECT COUNT(*) INTO @r43__populated__b2__complete__field_mismatches FROM platform_audit_events_audit_v1_shadow s JOIN platform_audit_events e ON s.stream_key=e.stream_key AND s.sequence_number=e.sequence_number WHERE NOT (s.event_id<=>e.event_id) OR NOT (s.schema_version<=>e.schema_version) OR NOT (s.occurred_at_utc<=>e.occurred_at_utc) OR NOT (s.action<=>e.action) OR NOT (s.risk_level<=>e.risk_level) OR NOT (s.outcome<=>e.outcome) OR NOT (s.reason_code<=>e.reason_code) OR NOT (s.real_actor_reference<=>e.real_actor_reference) OR NOT (s.effective_actor_reference<=>e.effective_actor_reference) OR NOT (s.affected_subject_reference<=>e.affected_subject_reference) OR NOT (s.correlation_id<=>e.correlation_id) OR NOT (s.request_id<=>e.request_id) OR NOT (s.case_reference<=>e.case_reference) OR NOT (s.resource_type<=>e.resource_type) OR NOT (s.resource_reference<=>e.resource_reference) OR NOT (s.previous_hash<=>e.previous_hash) OR NOT (s.event_hash<=>e.event_hash) OR NOT (s.created_at_utc<=>e.created_at_utc);
  SELECT COUNT(*) INTO @r43__populated__b2__complete__json_mismatches FROM platform_audit_events_audit_v1_shadow s JOIN platform_audit_events e ON s.stream_key=e.stream_key AND s.sequence_number=e.sequence_number WHERE NOT (CAST(s.metadata_json AS JSON)<=>CAST(e.metadata_json AS JSON));
  CALL audit_mp01b_fold_source(@r43__populated__b2__complete__source_fingerprint);
  CALL audit_mp01b_fold_shadow(@r43__populated__b2__complete__shadow_fingerprint);
  CALL audit_mp01b_fold_source_triggers(@r43__populated__b2__complete__source_trigger_fingerprint);
  CALL audit_mp01b_fold_shadow_triggers(@r43__populated__b2__complete__shadow_trigger_fingerprint);
  CALL audit_mp01b_fold_preserved_event_hash(@r43__populated__b2__complete__preserved_event_hash_sha256);
  IF @r43__populated__b2__complete__route IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B2 COMPLETE route'; END IF;
  IF @r43__populated__b2__complete__phase IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B2 COMPLETE phase'; END IF;
  IF @r43__populated__b2__complete__state IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B2 COMPLETE state'; END IF;
  IF @r43__populated__b2__complete__batch_identifier IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B2 COMPLETE batch_identifier'; END IF;
  IF @r43__populated__b2__complete__batch_min_stream_key IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B2 COMPLETE batch_min_stream_key'; END IF;
  IF @r43__populated__b2__complete__batch_max_stream_key IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B2 COMPLETE batch_max_stream_key'; END IF;
  IF @r43__populated__b2__complete__resume_stream_key IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B2 COMPLETE resume_stream_key'; END IF;
  IF @r43__populated__b2__complete__resume_sequence_number IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B2 COMPLETE resume_sequence_number'; END IF;
  IF @r43__populated__b2__complete__source_row_count IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B2 COMPLETE source_row_count'; END IF;
  IF @r43__populated__b2__complete__source_stream_count IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B2 COMPLETE source_stream_count'; END IF;
  IF @r43__populated__b2__complete__shadow_row_count IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B2 COMPLETE shadow_row_count'; END IF;
  IF @r43__populated__b2__complete__shadow_stream_count IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B2 COMPLETE shadow_stream_count'; END IF;
  IF @r43__populated__b2__complete__inserted_count IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B2 COMPLETE inserted_count'; END IF;
  IF @r43__populated__b2__complete__keys_count IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B2 COMPLETE keys_count'; END IF;
  IF @r43__populated__b2__complete__foreign_rows IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B2 COMPLETE foreign_rows'; END IF;
  IF @r43__populated__b2__complete__partial_rows IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B2 COMPLETE partial_rows'; END IF;
  IF @r43__populated__b2__complete__ambiguous_violations IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B2 COMPLETE ambiguous_violations'; END IF;
  IF @r43__populated__b2__complete__field_mismatches IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B2 COMPLETE field_mismatches'; END IF;
  IF @r43__populated__b2__complete__json_mismatches IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B2 COMPLETE json_mismatches'; END IF;
  IF @r43__populated__b2__complete__source_fingerprint IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B2 COMPLETE source_fingerprint'; END IF;
  IF @r43__populated__b2__complete__shadow_fingerprint IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B2 COMPLETE shadow_fingerprint'; END IF;
  IF @r43__populated__b2__complete__source_trigger_fingerprint IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B2 COMPLETE source_trigger_fingerprint'; END IF;
  IF @r43__populated__b2__complete__shadow_trigger_fingerprint IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B2 COMPLETE shadow_trigger_fingerprint'; END IF;
  IF @r43__populated__b2__complete__preserved_event_hash_sha256 IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='unauthorized null POPULATED B2 COMPLETE preserved_event_hash_sha256'; END IF;
  SET @r43_summary_canonical=CONCAT('O24;',CONCAT('F5:route',CONCAT('S',OCTET_LENGTH(CAST(@r43__populated__b2__complete__route AS CHAR)),':',UPPER(HEX(CAST(@r43__populated__b2__complete__route AS CHAR))))),CONCAT('F5:phase',CONCAT('S',OCTET_LENGTH(CAST(@r43__populated__b2__complete__phase AS CHAR)),':',UPPER(HEX(CAST(@r43__populated__b2__complete__phase AS CHAR))))),CONCAT('F5:state',CONCAT('S',OCTET_LENGTH(CAST(@r43__populated__b2__complete__state AS CHAR)),':',UPPER(HEX(CAST(@r43__populated__b2__complete__state AS CHAR))))),CONCAT('F16:batch_identifier',CONCAT('S',OCTET_LENGTH(CAST(@r43__populated__b2__complete__batch_identifier AS CHAR)),':',UPPER(HEX(CAST(@r43__populated__b2__complete__batch_identifier AS CHAR))))),CONCAT('F20:batch_min_stream_key',CONCAT('S',OCTET_LENGTH(CAST(@r43__populated__b2__complete__batch_min_stream_key AS CHAR)),':',UPPER(HEX(CAST(@r43__populated__b2__complete__batch_min_stream_key AS CHAR))))),CONCAT('F20:batch_max_stream_key',CONCAT('S',OCTET_LENGTH(CAST(@r43__populated__b2__complete__batch_max_stream_key AS CHAR)),':',UPPER(HEX(CAST(@r43__populated__b2__complete__batch_max_stream_key AS CHAR))))),CONCAT('F17:resume_stream_key',CONCAT('S',OCTET_LENGTH(CAST(@r43__populated__b2__complete__resume_stream_key AS CHAR)),':',UPPER(HEX(CAST(@r43__populated__b2__complete__resume_stream_key AS CHAR))))),CONCAT('F22:resume_sequence_number',CONCAT('S',OCTET_LENGTH(CAST(@r43__populated__b2__complete__resume_sequence_number AS CHAR)),':',UPPER(HEX(CAST(@r43__populated__b2__complete__resume_sequence_number AS CHAR))))),CONCAT('F16:source_row_count',CONCAT('I',CAST(@r43__populated__b2__complete__source_row_count AS CHAR),';')),CONCAT('F19:source_stream_count',CONCAT('I',CAST(@r43__populated__b2__complete__source_stream_count AS CHAR),';')),CONCAT('F16:shadow_row_count',CONCAT('I',CAST(@r43__populated__b2__complete__shadow_row_count AS CHAR),';')),CONCAT('F19:shadow_stream_count',CONCAT('I',CAST(@r43__populated__b2__complete__shadow_stream_count AS CHAR),';')),CONCAT('F14:inserted_count',CONCAT('I',CAST(@r43__populated__b2__complete__inserted_count AS CHAR),';')),CONCAT('F10:keys_count',CONCAT('I',CAST(@r43__populated__b2__complete__keys_count AS CHAR),';')),CONCAT('F12:foreign_rows',CONCAT('I',CAST(@r43__populated__b2__complete__foreign_rows AS CHAR),';')),CONCAT('F12:partial_rows',CONCAT('I',CAST(@r43__populated__b2__complete__partial_rows AS CHAR),';')),CONCAT('F20:ambiguous_violations',CONCAT('I',CAST(@r43__populated__b2__complete__ambiguous_violations AS CHAR),';')),CONCAT('F16:field_mismatches',CONCAT('I',CAST(@r43__populated__b2__complete__field_mismatches AS CHAR),';')),CONCAT('F15:json_mismatches',CONCAT('I',CAST(@r43__populated__b2__complete__json_mismatches AS CHAR),';')),CONCAT('F18:source_fingerprint',CONCAT('S',OCTET_LENGTH(CAST(@r43__populated__b2__complete__source_fingerprint AS CHAR)),':',UPPER(HEX(CAST(@r43__populated__b2__complete__source_fingerprint AS CHAR))))),CONCAT('F18:shadow_fingerprint',CONCAT('S',OCTET_LENGTH(CAST(@r43__populated__b2__complete__shadow_fingerprint AS CHAR)),':',UPPER(HEX(CAST(@r43__populated__b2__complete__shadow_fingerprint AS CHAR))))),CONCAT('F26:source_trigger_fingerprint',CONCAT('S',OCTET_LENGTH(CAST(@r43__populated__b2__complete__source_trigger_fingerprint AS CHAR)),':',UPPER(HEX(CAST(@r43__populated__b2__complete__source_trigger_fingerprint AS CHAR))))),CONCAT('F26:shadow_trigger_fingerprint',CONCAT('S',OCTET_LENGTH(CAST(@r43__populated__b2__complete__shadow_trigger_fingerprint AS CHAR)),':',UPPER(HEX(CAST(@r43__populated__b2__complete__shadow_trigger_fingerprint AS CHAR))))),CONCAT('F27:preserved_event_hash_sha256',CONCAT('S',OCTET_LENGTH(CAST(@r43__populated__b2__complete__preserved_event_hash_sha256 AS CHAR)),':',UPPER(HEX(CAST(@r43__populated__b2__complete__preserved_event_hash_sha256 AS CHAR))))));
  SET @r43_summary_base64=REPLACE(TO_BASE64(@r43_summary_canonical),'\n','');
  SET @r43_field_order_sha256='e650ce203e683a39ae1d8c9da2abaed891241b828953b8586b12bf4b8131eb56';
  SET @r43_payload_canonical=CONCAT('O7;',CONCAT('F6:schema',CONCAT('S',OCTET_LENGTH(CAST('mxmed.audit.mp01b.canonical.v1' AS CHAR)),':',UPPER(HEX(CAST('mxmed.audit.mp01b.canonical.v1' AS CHAR))))),CONCAT('F5:route',CONCAT('S',OCTET_LENGTH(CAST('POPULATED' AS CHAR)),':',UPPER(HEX(CAST('POPULATED' AS CHAR))))),CONCAT('F5:phase',CONCAT('S',OCTET_LENGTH(CAST('B2' AS CHAR)),':',UPPER(HEX(CAST('B2' AS CHAR))))),CONCAT('F5:state',CONCAT('S',OCTET_LENGTH(CAST('COMPLETE' AS CHAR)),':',UPPER(HEX(CAST('COMPLETE' AS CHAR))))),CONCAT('F18:field_order_sha256',CONCAT('S',OCTET_LENGTH(CAST(@r43_field_order_sha256 AS CHAR)),':',UPPER(HEX(CAST(@r43_field_order_sha256 AS CHAR))))),CONCAT('F7:summary',CONCAT('S',OCTET_LENGTH(CAST(@r43_summary_base64 AS CHAR)),':',UPPER(HEX(CAST(@r43_summary_base64 AS CHAR))))),CONCAT('F23:previous_receipt_sha256',CONCAT('S',OCTET_LENGTH(CAST(@previous_receipt_sha256_populated AS CHAR)),':',UPPER(HEX(CAST(@previous_receipt_sha256_populated AS CHAR))))));
  SET @r43_payload_base64=REPLACE(TO_BASE64(@r43_payload_canonical),'\n','');
  SET @r43_receipt_sha256=LOWER(SHA2(@r43_payload_canonical,256));
  CALL audit_mp01b_assert_b2_complete();
  INSERT INTO audit_mp01b_phase_receipts(route,phase,state,payload_canonical,payload_base64,previous_receipt_sha256,receipt_sha256) VALUES('POPULATED','B2','COMPLETE',@r43_payload_canonical,@r43_payload_base64,@previous_receipt_sha256_populated,@r43_receipt_sha256);
  SET @previous_receipt_sha256_populated=@r43_receipt_sha256;
 ELSE SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='invalid audit route';
 END IF;
END$$
DELIMITER ;
CALL audit_mp01b_emit_b2(@audit_mp01b_route);
SET @r43_execution_state='B2_EXECUTED';
