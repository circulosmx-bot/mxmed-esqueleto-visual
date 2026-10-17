-- PUB-COR02. Prepared only, after Profiles PUB-COR01 and canonical governance/MFA bindings.
-- No seeds, live DDL/activation, backfill, changes to PUB-COR01 history or clinical sources.
CREATE TABLE profiles_contact_correction_versions (
 contact_point_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 version BIGINT UNSIGNED NOT NULL,
 FOREIGN KEY(contact_point_id) REFERENCES doctor_contact_points(contact_point_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CHECK(version>0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE profiles_correction_application_commands (
 command_id CHAR(36) NOT NULL PRIMARY KEY,
 report_id CHAR(36) NOT NULL,
 decision_id CHAR(36) NOT NULL,
 report_version BIGINT UNSIGNED NOT NULL,
 profile_doctor_id VARCHAR(64) NOT NULL,
 field_family VARCHAR(32) NOT NULL,
 contact_point_id BIGINT UNSIGNED NOT NULL,
 consultorio_id VARCHAR(64) NULL,
 expected_version CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 command_digest CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 command_ciphertext BLOB NOT NULL,
 key_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 retention_policy VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 actor_account_id VARCHAR(64) NOT NULL,
 credential_version BIGINT UNSIGNED NOT NULL,
 grant_reference CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 case_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 created_at DATETIME(6) NOT NULL,
 FOREIGN KEY(report_id) REFERENCES profiles_correction_reports(report_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY(decision_id) REFERENCES profiles_correction_decisions(decision_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY(profile_doctor_id) REFERENCES profiles_doctors(doctor_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY(contact_point_id) REFERENCES doctor_contact_points(contact_point_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY(actor_account_id) REFERENCES internal_staff(account_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY(case_id) REFERENCES auth_governance_cases(case_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CHECK(field_family='PUBLIC_PROFILE_PHONE' AND consultorio_id IS NULL AND report_version>1 AND credential_version>0),
 KEY ix_correction_application_report(report_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE profiles_correction_application_authorizations (
 command_id CHAR(36) NOT NULL PRIMARY KEY,
 proof_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 proof_ciphertext BLOB NOT NULL,
 key_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 created_at DATETIME(6) NOT NULL,
 FOREIGN KEY(command_id) REFERENCES profiles_correction_application_commands(command_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY(proof_id) REFERENCES auth_staff_mfa_proofs(proof_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE profiles_correction_application_journal (
 event_id CHAR(36) NOT NULL PRIMARY KEY,
 command_id CHAR(36) NOT NULL,
 sequence_number BIGINT UNSIGNED NOT NULL,
 state VARCHAR(32) NOT NULL,
 actor_account_id VARCHAR(64) NOT NULL,
 audit_reference CHAR(36) NOT NULL,
 created_at DATETIME(6) NOT NULL,
 UNIQUE KEY uq_correction_application_sequence(command_id,sequence_number),
 FOREIGN KEY(command_id) REFERENCES profiles_correction_application_commands(command_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY(actor_account_id) REFERENCES internal_staff(account_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CHECK(state IN ('REQUESTED','AUTHORIZED','APPLY_STARTED','APPLIED','NO_CHANGE','RETRYABLE_FAILURE','CONFLICT','RECONCILIATION_REQUIRED','RECONCILED_RETRYABLE'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE profiles_correction_application_results (
 command_id CHAR(36) NOT NULL PRIMARY KEY,
 report_id CHAR(36) NOT NULL UNIQUE,
 status VARCHAR(16) NOT NULL,
 target_version BIGINT UNSIGNED NOT NULL,
 after_fingerprint CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 result_ciphertext BLOB NOT NULL,
 key_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 audit_reference CHAR(36) NOT NULL,
 created_at DATETIME(6) NOT NULL,
 FOREIGN KEY(command_id) REFERENCES profiles_correction_application_commands(command_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY(report_id) REFERENCES profiles_correction_reports(report_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CHECK(status IN ('APPLIED','NO_CHANGE'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE auth_governance_cases DROP CHECK ck_auth_governance_case_operation,
 ADD CONSTRAINT ck_auth_governance_case_operation CHECK(operation IN
 ('INITIAL_OWNER','OWNER_REPLACEMENT','OWNER_REMOVAL','FIRST_DIRECTOR_BOOTSTRAP','MFA_RECOVERY',
 'PHYSICIAN_EVIDENCE_REVIEW','PHYSICIAN_AUTHORITY_CHANGE','PHYSICIAN_ENROLLMENT_REVIEW',
 'PUBLIC_PROFILE_CORRECTION_REVIEW','PUBLIC_PROFILE_CORRECTION_APPLY'));

DELIMITER $$
CREATE TRIGGER correction_contact_version_insert AFTER INSERT ON doctor_contact_points FOR EACH ROW
BEGIN INSERT INTO profiles_contact_correction_versions VALUES(NEW.contact_point_id,1); END$$
CREATE TRIGGER correction_contact_version_update AFTER UPDATE ON doctor_contact_points FOR EACH ROW
BEGIN INSERT INTO profiles_contact_correction_versions VALUES(NEW.contact_point_id,1) ON DUPLICATE KEY UPDATE version=version+1; END$$
CREATE TRIGGER correction_contact_version_guard BEFORE UPDATE ON profiles_contact_correction_versions FOR EACH ROW
BEGIN
 IF NEW.contact_point_id<>OLD.contact_point_id OR NEW.version<>OLD.version+1
 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='correction_contact_version_monotonic'; END IF;
END$$
CREATE TRIGGER correction_contact_version_no_delete BEFORE DELETE ON profiles_contact_correction_versions FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='correction_contact_version_immutable'; END$$
CREATE TRIGGER correction_application_command_guard BEFORE INSERT ON profiles_correction_application_commands FOR EACH ROW
BEGIN
 IF NOT EXISTS(SELECT 1 FROM profiles_correction_decisions d JOIN profiles_correction_reports r ON r.report_id=d.report_id
 JOIN doctor_contact_points p ON p.doctor_id=r.profile_doctor_id AND p.contact_point_id=NEW.contact_point_id
 JOIN auth_governance_cases c ON c.case_id=NEW.case_id
 WHERE d.decision_id=NEW.decision_id AND d.report_id=NEW.report_id AND d.version=NEW.report_version
 AND r.profile_doctor_id=NEW.profile_doctor_id AND d.status='ACCEPTED_FOR_APPLICATION'
 AND r.category IN ('CHANGED_PHONE','OUTDATED_CONTACT_INFORMATION')
 AND NOT EXISTS(SELECT 1 FROM profiles_correction_decisions n WHERE n.report_id=d.report_id AND n.version>d.version)
 AND p.consultorio_id IS NULL AND p.type='phone' AND p.scope='public_profile' AND p.deleted_at IS NULL
 AND c.operation='PUBLIC_PROFILE_CORRECTION_APPLY' AND c.status='OPEN'
 AND c.requester_account_id=NEW.actor_account_id AND c.profile_doctor_id=NEW.profile_doctor_id)
 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='correction_application_scope_denied'; END IF;
END$$
CREATE TRIGGER correction_application_journal_guard BEFORE INSERT ON profiles_correction_application_journal FOR EACH ROW
BEGIN
 DECLARE v BIGINT UNSIGNED; DECLARE s VARCHAR(32);
 SELECT COALESCE(MAX(sequence_number),0) INTO v FROM profiles_correction_application_journal WHERE command_id=NEW.command_id;
 SET s=COALESCE((SELECT state FROM profiles_correction_application_journal WHERE command_id=NEW.command_id AND sequence_number=v),'NONE');
 IF NEW.sequence_number<>v+1 OR s IN ('APPLIED','NO_CHANGE')
 OR (v=0 AND NEW.state<>'REQUESTED')
 OR (NEW.state='AUTHORIZED' AND (s<>'REQUESTED' OR NOT EXISTS(SELECT 1 FROM profiles_correction_application_authorizations a WHERE a.command_id=NEW.command_id)))
 OR (NEW.state='APPLY_STARTED' AND s NOT IN ('AUTHORIZED','RETRYABLE_FAILURE','RECONCILED_RETRYABLE','APPLY_STARTED'))
 OR (NEW.state IN ('APPLIED','NO_CHANGE','RETRYABLE_FAILURE') AND s<>'APPLY_STARTED')
 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='correction_application_transition_denied'; END IF;
END$$
CREATE TRIGGER correction_application_result_guard BEFORE INSERT ON profiles_correction_application_results FOR EACH ROW
BEGIN
 IF NOT EXISTS(SELECT 1 FROM profiles_correction_application_commands c
 JOIN profiles_correction_application_authorizations a ON a.command_id=c.command_id
 JOIN auth_staff_mfa_proofs p ON p.proof_id=a.proof_id
 JOIN auth_governance_cases g ON g.case_id=c.case_id
 JOIN profiles_correction_application_journal j ON j.command_id=c.command_id
 WHERE c.command_id=NEW.command_id AND c.report_id=NEW.report_id AND p.status='CONSUMED'
 AND p.operation='PUBLIC_PROFILE_CORRECTION_APPLY' AND p.case_id=c.case_id AND p.actor_account_id=c.actor_account_id
 AND p.profile_doctor_id=c.profile_doctor_id AND p.consumed_by_reference=c.command_id AND g.status='CONSUMED'
 AND j.sequence_number=(SELECT MAX(x.sequence_number) FROM profiles_correction_application_journal x WHERE x.command_id=c.command_id)
 AND j.state=NEW.status)
 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='correction_application_result_denied'; END IF;
END$$
CREATE TRIGGER correction_application_command_no_update BEFORE UPDATE ON profiles_correction_application_commands FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='correction_application_immutable'; END$$
CREATE TRIGGER correction_application_command_no_delete BEFORE DELETE ON profiles_correction_application_commands FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='correction_application_immutable'; END$$
CREATE TRIGGER correction_application_auth_no_update BEFORE UPDATE ON profiles_correction_application_authorizations FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='correction_application_immutable'; END$$
CREATE TRIGGER correction_application_auth_no_delete BEFORE DELETE ON profiles_correction_application_authorizations FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='correction_application_immutable'; END$$
CREATE TRIGGER correction_application_journal_no_update BEFORE UPDATE ON profiles_correction_application_journal FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='correction_application_append_only'; END$$
CREATE TRIGGER correction_application_journal_no_delete BEFORE DELETE ON profiles_correction_application_journal FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='correction_application_append_only'; END$$
CREATE TRIGGER correction_application_result_no_update BEFORE UPDATE ON profiles_correction_application_results FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='correction_application_immutable'; END$$
CREATE TRIGGER correction_application_result_no_delete BEFORE DELETE ON profiles_correction_application_results FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='correction_application_immutable'; END$$
DELIMITER ;
