-- AUDIT-MP01B B5 repository promotion derived from certified R45 + R54; not executed by preparation
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
DROP PROCEDURE IF EXISTS audit_mp01b_emit_b5;
DELIMITER $$
CREATE PROCEDURE audit_mp01b_emit_b5(IN p_route VARCHAR(16))
BEGIN
 CALL audit_mp01b_assert_route(p_route);
 IF p_route='EMPTY' THEN
  SELECT COUNT(DISTINCT i.table_name) INTO @r43__empty__b5__final__tables_count FROM information_schema.tables i WHERE i.table_schema=DATABASE() AND i.table_name IN ('platform_audit_events_audit_v1_shadow','platform_audit_stream_heads','audit_mp01b_phase_receipts','audit_mp01b_integrity_counters','audit_mp01b_show_grants_capture','audit_mp01b_privilege_inventory','audit_mp01b_database_identity_capture');
  SELECT COUNT(*) INTO @r43__empty__b5__final__columns_count FROM information_schema.columns i WHERE i.table_schema=DATABASE() AND i.table_name IN ('platform_audit_events_audit_v1_shadow','platform_audit_stream_heads','audit_mp01b_phase_receipts','audit_mp01b_integrity_counters','audit_mp01b_show_grants_capture','audit_mp01b_privilege_inventory','audit_mp01b_database_identity_capture');
  SELECT COUNT(DISTINCT i.table_name,i.index_name) INTO @r43__empty__b5__final__indexes_count FROM information_schema.statistics i WHERE i.table_schema=DATABASE() AND i.table_name IN ('platform_audit_events_audit_v1_shadow','platform_audit_stream_heads','audit_mp01b_phase_receipts','audit_mp01b_integrity_counters','audit_mp01b_show_grants_capture','audit_mp01b_privilege_inventory','audit_mp01b_database_identity_capture');
  SELECT COUNT(*) INTO @r43__empty__b5__final__constraints_count FROM information_schema.table_constraints i WHERE i.constraint_schema=DATABASE() AND i.table_name IN ('platform_audit_events_audit_v1_shadow','platform_audit_stream_heads','audit_mp01b_phase_receipts','audit_mp01b_integrity_counters','audit_mp01b_show_grants_capture','audit_mp01b_privilege_inventory','audit_mp01b_database_identity_capture');
  SELECT COUNT(*) INTO @r43__empty__b5__final__triggers_count FROM information_schema.triggers i WHERE i.trigger_schema=DATABASE() AND i.event_object_table IN ('platform_audit_events','platform_audit_events_audit_v1_shadow');
  SELECT COUNT(*) INTO @r43__empty__b5__final__privilege_source_count FROM audit_mp01b_privilege_inventory a WHERE a.principal=CURRENT_USER();
  SELECT COUNT(*) INTO @r43__empty__b5__final__show_grants_count FROM audit_mp01b_show_grants_capture a WHERE a.principal=CURRENT_USER() AND @r43_show_grants_capture_complete=1;
  WITH RECURSIVE reachable_roles(role_name,path) AS (SELECT CAST(CURRENT_USER() AS CHAR(512)),CAST(CONCAT('|',CURRENT_USER(),'|') AS CHAR(8192)) UNION ALL SELECT CAST(CONCAT(r.FROM_USER,'@',r.FROM_HOST) AS CHAR(512)),CAST(CONCAT(rr.path,CONCAT(r.FROM_USER,'@',r.FROM_HOST),'|') AS CHAR(8192)) FROM reachable_roles rr JOIN mysql.role_edges r ON CONCAT(r.TO_USER,'@',r.TO_HOST)=rr.role_name WHERE LOCATE(CONCAT('|',CONCAT(r.FROM_USER,'@',r.FROM_HOST),'|'),rr.path)=0) SELECT COUNT(*) INTO @r43__empty__b5__final__role_edge_count FROM mysql.role_edges e JOIN reachable_roles rr ON CONCAT(e.TO_USER,'@',e.TO_HOST)=rr.role_name;
  SELECT COUNT(*) INTO @r43__empty__b5__final__phase_receipt_count FROM audit_mp01b_phase_receipts a WHERE a.route IN ('EMPTY','POPULATED') AND a.phase IN ('B1','B2','B3','B4') AND a.state IN ('FINAL','IN_PROGRESS','COMPLETE');
  CALL audit_mp01b_fold_database_identity(@r43__empty__b5__final__database_identity_fingerprint);
  CALL audit_mp01b_fold_preserved_event_hash(@r43__empty__b5__final__preserved_event_hash_sha256);
  SET @r43__empty__b5__final__raw_component_count=COALESCE(@r43__empty__b5__final__tables_count,0)+COALESCE(@r43__empty__b5__final__columns_count,0)+COALESCE(@r43__empty__b5__final__indexes_count,0)+COALESCE(@r43__empty__b5__final__constraints_count,0)+COALESCE(@r43__empty__b5__final__triggers_count,0)+COALESCE(@r43__empty__b5__final__privilege_source_count,0)+COALESCE(@r43__empty__b5__final__show_grants_count,0)+COALESCE(@r43__empty__b5__final__role_edge_count,0)+COALESCE(@r43__empty__b5__final__phase_receipt_count,0);
 ELSEIF p_route='POPULATED' THEN
  SELECT COUNT(DISTINCT i.table_name) INTO @r43__populated__b5__final__tables_count FROM information_schema.tables i WHERE i.table_schema=DATABASE() AND i.table_name IN ('platform_audit_events_audit_v1_shadow','platform_audit_stream_heads','audit_mp01b_phase_receipts','audit_mp01b_integrity_counters','audit_mp01b_show_grants_capture','audit_mp01b_privilege_inventory','audit_mp01b_database_identity_capture');
  SELECT COUNT(*) INTO @r43__populated__b5__final__columns_count FROM information_schema.columns i WHERE i.table_schema=DATABASE() AND i.table_name IN ('platform_audit_events_audit_v1_shadow','platform_audit_stream_heads','audit_mp01b_phase_receipts','audit_mp01b_integrity_counters','audit_mp01b_show_grants_capture','audit_mp01b_privilege_inventory','audit_mp01b_database_identity_capture');
  SELECT COUNT(DISTINCT i.table_name,i.index_name) INTO @r43__populated__b5__final__indexes_count FROM information_schema.statistics i WHERE i.table_schema=DATABASE() AND i.table_name IN ('platform_audit_events_audit_v1_shadow','platform_audit_stream_heads','audit_mp01b_phase_receipts','audit_mp01b_integrity_counters','audit_mp01b_show_grants_capture','audit_mp01b_privilege_inventory','audit_mp01b_database_identity_capture');
  SELECT COUNT(*) INTO @r43__populated__b5__final__constraints_count FROM information_schema.table_constraints i WHERE i.constraint_schema=DATABASE() AND i.table_name IN ('platform_audit_events_audit_v1_shadow','platform_audit_stream_heads','audit_mp01b_phase_receipts','audit_mp01b_integrity_counters','audit_mp01b_show_grants_capture','audit_mp01b_privilege_inventory','audit_mp01b_database_identity_capture');
  SELECT COUNT(*) INTO @r43__populated__b5__final__triggers_count FROM information_schema.triggers i WHERE i.trigger_schema=DATABASE() AND i.event_object_table IN ('platform_audit_events','platform_audit_events_audit_v1_shadow');
  SELECT COUNT(*) INTO @r43__populated__b5__final__privilege_source_count FROM audit_mp01b_privilege_inventory a WHERE a.principal=CURRENT_USER();
  SELECT COUNT(*) INTO @r43__populated__b5__final__show_grants_count FROM audit_mp01b_show_grants_capture a WHERE a.principal=CURRENT_USER() AND @r43_show_grants_capture_complete=1;
  WITH RECURSIVE reachable_roles(role_name,path) AS (SELECT CAST(CURRENT_USER() AS CHAR(512)),CAST(CONCAT('|',CURRENT_USER(),'|') AS CHAR(8192)) UNION ALL SELECT CAST(CONCAT(r.FROM_USER,'@',r.FROM_HOST) AS CHAR(512)),CAST(CONCAT(rr.path,CONCAT(r.FROM_USER,'@',r.FROM_HOST),'|') AS CHAR(8192)) FROM reachable_roles rr JOIN mysql.role_edges r ON CONCAT(r.TO_USER,'@',r.TO_HOST)=rr.role_name WHERE LOCATE(CONCAT('|',CONCAT(r.FROM_USER,'@',r.FROM_HOST),'|'),rr.path)=0) SELECT COUNT(*) INTO @r43__populated__b5__final__role_edge_count FROM mysql.role_edges e JOIN reachable_roles rr ON CONCAT(e.TO_USER,'@',e.TO_HOST)=rr.role_name;
  SELECT COUNT(*) INTO @r43__populated__b5__final__phase_receipt_count FROM audit_mp01b_phase_receipts a WHERE a.route IN ('EMPTY','POPULATED') AND a.phase IN ('B1','B2','B3','B4') AND a.state IN ('FINAL','IN_PROGRESS','COMPLETE');
  CALL audit_mp01b_fold_database_identity(@r43__populated__b5__final__database_identity_fingerprint);
  CALL audit_mp01b_fold_preserved_event_hash(@r43__populated__b5__final__preserved_event_hash_sha256);
  SET @r43__populated__b5__final__raw_component_count=COALESCE(@r43__populated__b5__final__tables_count,0)+COALESCE(@r43__populated__b5__final__columns_count,0)+COALESCE(@r43__populated__b5__final__indexes_count,0)+COALESCE(@r43__populated__b5__final__constraints_count,0)+COALESCE(@r43__populated__b5__final__triggers_count,0)+COALESCE(@r43__populated__b5__final__privilege_source_count,0)+COALESCE(@r43__populated__b5__final__show_grants_count,0)+COALESCE(@r43__populated__b5__final__role_edge_count,0)+COALESCE(@r43__populated__b5__final__phase_receipt_count,0);
 ELSE SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='invalid audit route';
 END IF;
END$$
DELIMITER ;
CALL audit_mp01b_emit_b5(@audit_mp01b_route);
SET @r43_execution_state='B5_EXECUTED';
DROP PROCEDURE IF EXISTS audit_mp01b_finalize_b5;
DELIMITER $$
CREATE PROCEDURE audit_mp01b_finalize_b5()
BEGIN
 -- R43 SECTION 6: FINAL POSTCONDITIONS
 IF @r43_show_grants_capture_complete<>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='show grants unconfirmed'; END IF;
 IF @r43_privilege_inventory_complete<>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='privilege inventory unconfirmed'; END IF;
 SELECT COUNT(*) INTO @r43_pc_missing_source FROM platform_audit_events e LEFT JOIN platform_audit_events_audit_v1_shadow s ON s.stream_key=e.stream_key AND s.sequence_number=e.sequence_number WHERE s.stream_key IS NULL;
 SELECT COUNT(*) INTO @r43_pc_missing_shadow FROM platform_audit_events_audit_v1_shadow s LEFT JOIN platform_audit_events e ON e.stream_key=s.stream_key AND e.sequence_number=s.sequence_number WHERE e.stream_key IS NULL;
 SELECT COUNT(*) INTO @r43_pc_missing_heads FROM platform_audit_events_audit_v1_shadow s LEFT JOIN platform_audit_stream_heads h ON h.stream_key=s.stream_key WHERE h.stream_key IS NULL;
 SELECT COUNT(*) INTO @r43_pc_orphan_heads FROM platform_audit_stream_heads h LEFT JOIN platform_audit_events_audit_v1_shadow s ON s.stream_key=h.stream_key WHERE s.stream_key IS NULL;
 SELECT COUNT(*) INTO @r43_pc_divergent_heads FROM platform_audit_stream_heads h JOIN (SELECT stream_key,MAX(sequence_number) AS sequence_number FROM platform_audit_events_audit_v1_shadow GROUP BY stream_key) x ON x.stream_key=h.stream_key JOIN platform_audit_events_audit_v1_shadow s ON s.stream_key=x.stream_key AND s.sequence_number=x.sequence_number WHERE h.last_sequence_number<>s.sequence_number OR h.last_event_hash<>s.event_hash;
 SELECT COALESCE(SUM(n-1),0) INTO @r43_pc_duplicate_pk FROM (SELECT COUNT(*) n FROM platform_audit_events_audit_v1_shadow GROUP BY stream_key,sequence_number HAVING COUNT(*)>1) q;
 SELECT COALESCE(SUM(n-1),0) INTO @r43_pc_duplicate_event_id FROM (SELECT COUNT(*) n FROM platform_audit_events_audit_v1_shadow GROUP BY event_id HAVING COUNT(*)>1) q;
 SELECT COALESCE(SUM(n-1),0) INTO @r43_pc_duplicate_event_hash FROM (SELECT COUNT(*) n FROM platform_audit_events_audit_v1_shadow GROUP BY event_hash HAVING COUNT(*)>1) q;
 -- R54_CANONICAL_NULL_FILTER_4: final bundle postcondition.
 SELECT COALESCE(SUM(n-1),0) INTO @r43_pc_duplicate_canonical_event_id FROM (SELECT COUNT(*) n FROM platform_audit_events_audit_v1_shadow s WHERE s.canonical_event_id IS NOT NULL GROUP BY s.canonical_event_id HAVING COUNT(*)>1) q;
 SELECT COUNT(*) INTO @r43_pc_field_mismatches
 FROM platform_audit_events e JOIN platform_audit_events_audit_v1_shadow s
   ON s.stream_key=e.stream_key AND s.sequence_number=e.sequence_number
 WHERE NOT (s.event_id<=>e.event_id) OR NOT (s.schema_version<=>e.schema_version)
    OR NOT (s.occurred_at_utc<=>e.occurred_at_utc) OR NOT (s.action<=>e.action)
    OR NOT (s.risk_level<=>e.risk_level) OR NOT (s.outcome<=>e.outcome)
    OR NOT (s.reason_code<=>e.reason_code) OR NOT (s.real_actor_reference<=>e.real_actor_reference)
    OR NOT (s.effective_actor_reference<=>e.effective_actor_reference)
    OR NOT (s.affected_subject_reference<=>e.affected_subject_reference)
    OR NOT (s.correlation_id<=>e.correlation_id) OR NOT (s.request_id<=>e.request_id)
    OR NOT (s.case_reference<=>e.case_reference) OR NOT (s.resource_type<=>e.resource_type)
    OR NOT (s.resource_reference<=>e.resource_reference) OR NOT (s.previous_hash<=>e.previous_hash)
    OR NOT (s.event_hash<=>e.event_hash) OR NOT (s.created_at_utc<=>e.created_at_utc);
 SELECT COUNT(*) INTO @r43_pc_json_mismatches
 FROM platform_audit_events e JOIN platform_audit_events_audit_v1_shadow s
   ON s.stream_key=e.stream_key AND s.sequence_number=e.sequence_number
 WHERE NOT (CAST(s.metadata_json AS JSON)<=>CAST(e.metadata_json AS JSON));
 SELECT COUNT(*) INTO @r43_pc_source_update_count FROM information_schema.triggers WHERE trigger_schema=DATABASE() AND event_object_table='platform_audit_events' AND event_manipulation='UPDATE';
 SELECT COUNT(*) INTO @r43_pc_source_delete_count FROM information_schema.triggers WHERE trigger_schema=DATABASE() AND event_object_table='platform_audit_events' AND event_manipulation='DELETE';
 SELECT COUNT(*) INTO @r43_pc_shadow_update_count FROM information_schema.triggers WHERE trigger_schema=DATABASE() AND event_object_table='platform_audit_events_audit_v1_shadow' AND event_manipulation='UPDATE';
 SELECT COUNT(*) INTO @r43_pc_shadow_delete_count FROM information_schema.triggers WHERE trigger_schema=DATABASE() AND event_object_table='platform_audit_events_audit_v1_shadow' AND event_manipulation='DELETE';
 IF @r43_pc_missing_source<>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='postcondition missing_source'; END IF;
 IF @r43_pc_missing_shadow<>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='postcondition missing_shadow'; END IF;
 IF @r43_pc_missing_heads<>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='postcondition missing_heads'; END IF;
 IF @r43_pc_orphan_heads<>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='postcondition orphan_heads'; END IF;
 IF @r43_pc_divergent_heads<>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='postcondition divergent_heads'; END IF;
 IF @r43_pc_duplicate_pk<>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='postcondition duplicate_pk'; END IF;
 IF @r43_pc_duplicate_event_id<>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='postcondition duplicate_event_id'; END IF;
 IF @r43_pc_duplicate_event_hash<>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='postcondition duplicate_event_hash'; END IF;
 IF @r43_pc_duplicate_canonical_event_id<>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='postcondition duplicate_canonical_event_id'; END IF;
 IF @r43_pc_field_mismatches<>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='postcondition field_mismatches'; END IF;
 IF @r43_pc_json_mismatches<>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='postcondition json_mismatches'; END IF;
 IF @r43_pc_source_update_count<>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='postcondition source_update_count'; END IF;
 IF @r43_pc_source_delete_count<>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='postcondition source_delete_count'; END IF;
 IF @r43_pc_shadow_update_count<>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='postcondition shadow_update_count'; END IF;
 IF @r43_pc_shadow_delete_count<>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='postcondition shadow_delete_count'; END IF;
 INSERT INTO audit_mp01b_integrity_counters SELECT route,phase,state,'receipt_count','integer',CAST(COUNT(*) AS CHAR) FROM audit_mp01b_phase_receipts GROUP BY route,phase,state;
END$$
DELIMITER ;
CALL audit_mp01b_finalize_b5();
SET @r43_execution_state='FINAL_POSTCONDITIONS_PASS';
DROP PROCEDURE IF EXISTS audit_mp01b_emit_b1;
DROP PROCEDURE IF EXISTS audit_mp01b_emit_b2;
DROP PROCEDURE IF EXISTS audit_mp01b_emit_b3;
DROP PROCEDURE IF EXISTS audit_mp01b_emit_b4;
DROP PROCEDURE IF EXISTS audit_mp01b_emit_b5;
DROP PROCEDURE IF EXISTS audit_mp01b_fold_source;
DROP PROCEDURE IF EXISTS audit_mp01b_fold_shadow;
DROP PROCEDURE IF EXISTS audit_mp01b_fold_preserved_event_hash;
DROP PROCEDURE IF EXISTS audit_mp01b_fold_source_triggers;
DROP PROCEDURE IF EXISTS audit_mp01b_fold_shadow_triggers;
DROP PROCEDURE IF EXISTS audit_mp01b_fold_privileges;
DROP PROCEDURE IF EXISTS audit_mp01b_fold_database_identity;
DROP PROCEDURE IF EXISTS audit_mp01b_assert_route;
