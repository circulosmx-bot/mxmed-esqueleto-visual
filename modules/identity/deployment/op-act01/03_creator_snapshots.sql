-- OP-ACT01 separately reviewed deployment artifact; original rehearsals remain guarded.
CREATE TEMPORARY TABLE act01_target_guard(n INT CHECK(n=1));
INSERT INTO act01_target_guard VALUES(IF(@mxmed_operator_deploy_target=DATABASE() AND @mxmed_operator_deploy_server_uuid=@@server_uuid AND @mxmed_operator_deploy_version=VERSION() AND LENGTH(@mxmed_operator_deploy_authorization)=64,1,0));
DROP TEMPORARY TABLE act01_target_guard;
SET @op07_sql=IF(EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='agenda_appointments' AND index_name='uq_op07_appointment_profile'),'DO 0','ALTER TABLE agenda_appointments ADD UNIQUE KEY uq_op07_appointment_profile(appointment_id,doctor_id)');
PREPARE op07_stmt FROM @op07_sql; EXECUTE op07_stmt; DEALLOCATE PREPARE op07_stmt;

CREATE TABLE IF NOT EXISTS agenda_appointment_creator_snapshots (
 appointment_id VARCHAR(64) PRIMARY KEY,
 profile_doctor_id VARCHAR(64) NOT NULL,
 snapshot_version SMALLINT UNSIGNED NOT NULL,
 creator_account_id VARCHAR(64) NOT NULL,
 creator_membership_id VARCHAR(64) NOT NULL,
 creator_presentation_id VARCHAR(64) NULL,
 creator_alias VARCHAR(96) NULL,
 creator_position TINYINT UNSIGNED NULL,
 origin_visual_key VARCHAR(24) NOT NULL,
 created_at_utc DATETIME(6) NOT NULL,
 FOREIGN KEY(appointment_id,profile_doctor_id) REFERENCES agenda_appointments(appointment_id,doctor_id),
 FOREIGN KEY(creator_membership_id,profile_doctor_id,creator_account_id) REFERENCES auth_account_memberships(membership_id,profile_doctor_id,account_id),
 FOREIGN KEY(creator_presentation_id,creator_membership_id,profile_doctor_id) REFERENCES auth_profile_operator_presentations(presentation_id,membership_id,profile_doctor_id),
 CHECK(snapshot_version=1), CHECK(creator_position IS NULL OR creator_position IN (1,2)),
 CHECK((creator_position IS NULL AND origin_visual_key IN ('user','operator')) OR (creator_position=1 AND origin_visual_key='operator-01') OR (creator_position=2 AND origin_visual_key='operator-02')),
 CHECK(creator_alias IS NULL OR (CHAR_LENGTH(creator_alias) BETWEEN 1 AND 24 AND OCTET_LENGTH(creator_alias)<=96))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TRIGGER IF NOT EXISTS op07_snapshot_no_update BEFORE UPDATE ON agenda_appointment_creator_snapshots FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='immutable_appointment_creator';
CREATE TRIGGER IF NOT EXISTS op07_snapshot_no_delete BEFORE DELETE ON agenda_appointment_creator_snapshots FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='immutable_appointment_creator';
CREATE TRIGGER IF NOT EXISTS op07_original_creator_guard BEFORE UPDATE ON agenda_appointments FOR EACH ROW
BEGIN
 IF EXISTS(SELECT 1 FROM agenda_appointment_creator_snapshots WHERE appointment_id=OLD.appointment_id) AND
 (NOT (NEW.appointment_id <=> OLD.appointment_id) OR NOT (NEW.doctor_id <=> OLD.doctor_id) OR NOT (NEW.created_by_id <=> OLD.created_by_id) OR NOT (NEW.created_by_role <=> OLD.created_by_role) OR NOT (NEW.channel_origin <=> OLD.channel_origin) OR NOT (NEW.created_at <=> OLD.created_at)) THEN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='immutable_original_appointment_creator';
 END IF;
END;
CREATE TRIGGER IF NOT EXISTS op07_snapshot_insert_binding BEFORE INSERT ON agenda_appointment_creator_snapshots FOR EACH ROW
BEGIN
 IF NOT EXISTS(SELECT 1 FROM agenda_appointments a WHERE a.appointment_id=NEW.appointment_id AND a.doctor_id=NEW.profile_doctor_id AND a.created_by_id=NEW.creator_account_id)
 OR (NEW.creator_presentation_id IS NOT NULL AND NOT EXISTS(SELECT 1 FROM auth_profile_operator_presentations p WHERE p.presentation_id=NEW.creator_presentation_id AND p.membership_id=NEW.creator_membership_id AND p.profile_doctor_id=NEW.profile_doctor_id AND p.seat_position=NEW.creator_position AND (BINARY p.alias_display <=> BINARY NEW.creator_alias) AND p.released_at IS NULL)) THEN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='invalid_appointment_creator_binding';
 END IF;
END;
