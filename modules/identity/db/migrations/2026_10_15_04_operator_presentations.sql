-- OP-IMP07: rehearsal-only, never normal-local or existing QA environments.
CREATE TEMPORARY TABLE op07_disposable_guard(n INT CHECK(n=1));
INSERT INTO op07_disposable_guard VALUES(IF(DATABASE() REGEXP '^mxmed_op01_[a-f0-9]{10}$' OR DATABASE()='mxmed_gate4d_preview_opimp07',1,0));
DROP TEMPORARY TABLE op07_disposable_guard;
SET @op07_sql=IF(EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='auth_account_memberships' AND index_name='uq_op07_membership_profile'),'DO 0','ALTER TABLE auth_account_memberships ADD UNIQUE KEY uq_op07_membership_profile(membership_id,profile_doctor_id)');
PREPARE op07_stmt FROM @op07_sql; EXECUTE op07_stmt; DEALLOCATE PREPARE op07_stmt;
SET @op07_sql=IF(EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='auth_account_memberships' AND index_name='uq_op07_creator_profile'),'DO 0','ALTER TABLE auth_account_memberships ADD UNIQUE KEY uq_op07_creator_profile(membership_id,profile_doctor_id,account_id)');
PREPARE op07_stmt FROM @op07_sql; EXECUTE op07_stmt; DEALLOCATE PREPARE op07_stmt;
SET @op07_sql=IF(EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='auth_profile_operator_invitations' AND index_name='uq_op07_invitation_profile'),'DO 0','ALTER TABLE auth_profile_operator_invitations ADD UNIQUE KEY uq_op07_invitation_profile(invitation_id,profile_doctor_id)');
PREPARE op07_stmt FROM @op07_sql; EXECUTE op07_stmt; DEALLOCATE PREPARE op07_stmt;

CREATE TABLE IF NOT EXISTS auth_profile_operator_presentations (
 presentation_id VARCHAR(64) PRIMARY KEY,
 profile_doctor_id VARCHAR(64) NOT NULL,
 invitation_id VARCHAR(64) NOT NULL UNIQUE,
 membership_id VARCHAR(64) NULL UNIQUE,
 alias_display VARCHAR(96) NULL,
 alias_key VARBINARY(192) NULL,
 seat_position TINYINT UNSIGNED NOT NULL,
 revision INT UNSIGNED NOT NULL DEFAULT 1,
 created_at DATETIME(6) NOT NULL,
 updated_at DATETIME(6) NOT NULL,
 released_at DATETIME(6) NULL,
 active_alias_key VARBINARY(192) GENERATED ALWAYS AS (CASE WHEN released_at IS NULL THEN alias_key ELSE NULL END) STORED,
 active_position TINYINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN released_at IS NULL THEN seat_position ELSE NULL END) STORED,
 UNIQUE KEY uq_op07_profile_alias(profile_doctor_id,active_alias_key),
 UNIQUE KEY uq_op07_profile_position(profile_doctor_id,active_position),
 UNIQUE KEY uq_op07_assignment_member_profile(presentation_id,membership_id,profile_doctor_id),
 FOREIGN KEY(profile_doctor_id) REFERENCES profiles_doctors(doctor_id),
 FOREIGN KEY(invitation_id,profile_doctor_id) REFERENCES auth_profile_operator_invitations(invitation_id,profile_doctor_id),
 FOREIGN KEY(membership_id,profile_doctor_id) REFERENCES auth_account_memberships(membership_id,profile_doctor_id),
 CHECK(seat_position IN (1,2)), CHECK(revision>0),
 CHECK((alias_display IS NULL)=(alias_key IS NULL)),
 CHECK(alias_display IS NULL OR (CHAR_LENGTH(alias_display) BETWEEN 1 AND 24 AND OCTET_LENGTH(alias_display)<=96)),
 CHECK(updated_at>=created_at), CHECK(released_at IS NULL OR released_at>=created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TRIGGER IF NOT EXISTS op07_assignment_identity_guard BEFORE UPDATE ON auth_profile_operator_presentations FOR EACH ROW
BEGIN
 IF NOT (NEW.presentation_id <=> OLD.presentation_id) OR NOT (NEW.profile_doctor_id <=> OLD.profile_doctor_id) OR NOT (NEW.invitation_id <=> OLD.invitation_id) OR NOT (NEW.seat_position <=> OLD.seat_position) OR NOT (NEW.created_at <=> OLD.created_at)
 OR (OLD.membership_id IS NOT NULL AND NOT (NEW.membership_id <=> OLD.membership_id))
 OR (OLD.released_at IS NOT NULL AND (NOT (NEW.released_at <=> OLD.released_at) OR NOT (BINARY NEW.alias_display <=> BINARY OLD.alias_display) OR NOT (NEW.alias_key <=> OLD.alias_key))) THEN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='immutable_operator_assignment_identity';
 END IF;
END;
