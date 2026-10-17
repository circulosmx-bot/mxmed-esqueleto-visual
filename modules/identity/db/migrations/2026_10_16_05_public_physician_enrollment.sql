-- PUB-ENR02: disposable synthetic targets ONLY. No normal-local deployment authorization.
-- Initial intent/intake, never final evidence, physician master, membership or ownership.
CREATE TABLE auth_physician_enrollment_origins (
 origin_id CHAR(36) NOT NULL PRIMARY KEY,
 handle_digest CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 browser_digest CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 start_digest CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 initial_origin VARCHAR(40) NOT NULL,
 original_profile_doctor_id VARCHAR(64) NULL,
 original_public_path VARCHAR(1024) NOT NULL,
 source_provenance JSON NOT NULL,
 account_id VARCHAR(64) NULL,
 status VARCHAR(16) NOT NULL DEFAULT 'PRESENTED',
 revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
 intake_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
 intake_state VARCHAR(16) NOT NULL DEFAULT 'DRAFT',
 account_profile_key VARCHAR(64) GENERATED ALWAYS AS (COALESCE(original_profile_doctor_id,'@new')) STORED,
 audit_reference VARCHAR(64) NULL,
 created_at DATETIME(6) NOT NULL,
 expires_at DATETIME(6) NOT NULL,
 bound_at DATETIME(6) NULL,
 UNIQUE KEY uq_enrollment_handle(handle_digest),
 UNIQUE KEY uq_enrollment_start(start_digest),
 UNIQUE KEY uq_enrollment_applicant(account_id,initial_origin,account_profile_key),
 UNIQUE KEY uq_enrollment_account(origin_id,account_id),
 KEY ix_enrollment_browser(browser_digest,created_at),
 FOREIGN KEY(account_id) REFERENCES auth_accounts(account_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 FOREIGN KEY(original_profile_doctor_id) REFERENCES profiles_doctors(doctor_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CHECK(initial_origin IN ('CLAIM_EXISTING_PROFILE','REQUEST_NEW_PROFILE')),
 CHECK((initial_origin='CLAIM_EXISTING_PROFILE' AND original_profile_doctor_id IS NOT NULL)
    OR (initial_origin='REQUEST_NEW_PROFILE' AND original_profile_doctor_id IS NULL)),
 CHECK(status IN ('PRESENTED','BOUND') AND intake_state IN ('DRAFT','SUBMITTED') AND revision>0),
 CHECK(expires_at>created_at AND TIMESTAMPDIFF(SECOND,created_at,expires_at)<=1800),
 CHECK((status='PRESENTED' AND account_id IS NULL AND bound_at IS NULL AND intake_version=0 AND audit_reference IS NULL)
    OR (status='BOUND' AND account_id IS NOT NULL AND bound_at IS NOT NULL AND audit_reference IS NOT NULL)),
 CHECK(intake_state<>'SUBMITTED' OR intake_version>0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE auth_physician_enrollment_intake_revisions (
 origin_id CHAR(36) NOT NULL,
 account_id VARCHAR(64) NOT NULL,
 intake_version BIGINT UNSIGNED NOT NULL,
 submission_state VARCHAR(16) NOT NULL,
 intake_ciphertext MEDIUMBLOB NOT NULL,
 key_id VARCHAR(64) NOT NULL,
 retention_policy_reference VARCHAR(128) NOT NULL,
 audit_reference VARCHAR(64) NOT NULL,
 created_at DATETIME(6) NOT NULL,
 PRIMARY KEY(origin_id,intake_version),
 FOREIGN KEY(origin_id,account_id) REFERENCES auth_physician_enrollment_origins(origin_id,account_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CHECK(intake_version>0 AND submission_state IN ('DRAFT','SUBMITTED')),
 CHECK(OCTET_LENGTH(intake_ciphertext)>40 AND CHAR_LENGTH(key_id)>0 AND CHAR_LENGTH(retention_policy_reference)>0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE auth_physician_enrollment_commands (
 origin_id CHAR(36) NOT NULL,
 account_id VARCHAR(64) NOT NULL,
 command_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 input_digest CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 result_version BIGINT UNSIGNED NOT NULL,
 result_state VARCHAR(16) NOT NULL,
 created_at DATETIME(6) NOT NULL,
 PRIMARY KEY(origin_id,command_id),
 FOREIGN KEY(origin_id,account_id) REFERENCES auth_physician_enrollment_origins(origin_id,account_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 FOREIGN KEY(origin_id,result_version) REFERENCES auth_physician_enrollment_intake_revisions(origin_id,intake_version) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CHECK(result_state IN ('DRAFT','SUBMITTED'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DELIMITER $$
CREATE TRIGGER enrollment_origin_guard BEFORE UPDATE ON auth_physician_enrollment_origins FOR EACH ROW
BEGIN
 IF NOT(CAST(OLD.origin_id AS BINARY)<=>CAST(NEW.origin_id AS BINARY))
 OR NOT(CAST(OLD.handle_digest AS BINARY)<=>CAST(NEW.handle_digest AS BINARY))
 OR NOT(CAST(OLD.browser_digest AS BINARY)<=>CAST(NEW.browser_digest AS BINARY))
 OR NOT(CAST(OLD.start_digest AS BINARY)<=>CAST(NEW.start_digest AS BINARY))
 OR NOT(CAST(OLD.initial_origin AS BINARY)<=>CAST(NEW.initial_origin AS BINARY))
 OR NOT(CAST(OLD.original_profile_doctor_id AS BINARY)<=>CAST(NEW.original_profile_doctor_id AS BINARY))
 OR NOT(CAST(OLD.original_public_path AS BINARY)<=>CAST(NEW.original_public_path AS BINARY))
 OR NOT(OLD.source_provenance<=>NEW.source_provenance)
 OR NOT(OLD.created_at<=>NEW.created_at) OR NOT(OLD.expires_at<=>NEW.expires_at)
 OR NEW.revision<>OLD.revision+1
 OR (OLD.account_id IS NOT NULL AND (NOT(CAST(OLD.account_id AS BINARY)<=>CAST(NEW.account_id AS BINARY)) OR NOT(OLD.bound_at<=>NEW.bound_at)))
 OR (OLD.status='BOUND' AND NEW.status<>'BOUND')
 OR (OLD.status='PRESENTED' AND (NEW.status<>'BOUND' OR NEW.intake_version<>0 OR NEW.bound_at>=NEW.expires_at))
 OR (OLD.status='BOUND' AND (NEW.intake_version<>OLD.intake_version+1 OR OLD.intake_state<>'DRAFT'))
 OR (OLD.status='BOUND' AND NOT EXISTS(SELECT 1 FROM auth_physician_enrollment_intake_revisions r WHERE r.origin_id=OLD.origin_id
     AND r.account_id=OLD.account_id AND r.intake_version=NEW.intake_version AND r.submission_state=NEW.intake_state AND r.audit_reference=NEW.audit_reference))
 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='enrollment_origin_transition_denied'; END IF;
END$$
CREATE TRIGGER enrollment_origin_no_delete BEFORE DELETE ON auth_physician_enrollment_origins FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='enrollment_origin_history_preserved'; END$$
CREATE TRIGGER enrollment_intake_guard BEFORE INSERT ON auth_physician_enrollment_intake_revisions FOR EACH ROW
BEGIN
 DECLARE v BIGINT UNSIGNED; DECLARE s VARCHAR(16);
 SELECT intake_version,intake_state INTO v,s FROM auth_physician_enrollment_origins WHERE origin_id=NEW.origin_id AND account_id=NEW.account_id AND status='BOUND';
 IF v IS NULL OR NEW.intake_version<>v+1 OR s<>'DRAFT'
 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='enrollment_intake_revision_denied'; END IF;
END$$
CREATE TRIGGER enrollment_intake_no_update BEFORE UPDATE ON auth_physician_enrollment_intake_revisions FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='enrollment_intake_append_only'; END$$
CREATE TRIGGER enrollment_intake_no_delete BEFORE DELETE ON auth_physician_enrollment_intake_revisions FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='enrollment_intake_append_only'; END$$
CREATE TRIGGER enrollment_command_no_update BEFORE UPDATE ON auth_physician_enrollment_commands FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='enrollment_command_append_only'; END$$
CREATE TRIGGER enrollment_command_no_delete BEFORE DELETE ON auth_physician_enrollment_commands FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='enrollment_command_append_only'; END$$
DELIMITER ;
