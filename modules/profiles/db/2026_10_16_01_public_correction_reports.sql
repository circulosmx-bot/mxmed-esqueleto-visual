-- PUB-COR01: prepared only. No normal-local DDL or activation authorized.
-- Prerequisites: canonical Identity governance runtime + 2026_10_16_01 security bindings.
-- Also compatible with prepared Identity migrations 02..06; never creates accounts/grants.
CREATE TABLE profiles_correction_reports (
 report_id CHAR(36) NOT NULL PRIMARY KEY,
 profile_doctor_id VARCHAR(64) NOT NULL,
 original_route VARCHAR(256) NOT NULL,
 catalog_version SMALLINT UNSIGNED NOT NULL,
 category VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 payload_ciphertext MEDIUMBLOB NOT NULL,
 key_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 retention_policy VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 intent_digest CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 command_digest CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 abuse_reference CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 audit_reference CHAR(36) NOT NULL,
 created_at DATETIME(6) NOT NULL,
 FOREIGN KEY(profile_doctor_id) REFERENCES profiles_doctors(doctor_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CHECK(catalog_version=1),
 CHECK(category IN ('NEW_OFFICE_ADDRESS','INCORRECT_OFFICE_ADDRESS','CHANGED_PHONE','OUTDATED_CONTACT_INFORMATION',
 'INCORRECT_HOURS','DUPLICATE_PROFILE','PHYSICIAN_RETIRED','PHYSICIAN_DECEASED','WRONG_PERSON_OR_PROFILE','OTHER_FACTUAL_CORRECTION')),
 CHECK(OCTET_LENGTH(payload_ciphertext) BETWEEN 41 AND 16384),
 KEY ix_correction_profile(profile_doctor_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Initial state/version is immutable PENDING_REVIEW/1. Current state/version is the
-- latest decision (or the initial state). CAS locks the aggregate before appending.
CREATE TABLE profiles_correction_decisions (
 decision_id CHAR(36) NOT NULL PRIMARY KEY,
 report_id CHAR(36) NOT NULL,
 version BIGINT UNSIGNED NOT NULL,
 previous_status VARCHAR(32) NOT NULL,
 status VARCHAR(32) NOT NULL,
 reviewer_account_id VARCHAR(64) NOT NULL,
 credential_version BIGINT UNSIGNED NOT NULL,
 case_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 proof_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 reason_code VARCHAR(32) NOT NULL,
 evidence_ciphertext BLOB NOT NULL,
 key_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 audit_reference CHAR(36) NOT NULL,
 created_at DATETIME(6) NOT NULL,
 UNIQUE KEY uq_correction_version(report_id,version),
 FOREIGN KEY(report_id) REFERENCES profiles_correction_reports(report_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY(reviewer_account_id) REFERENCES internal_staff(account_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY(case_id) REFERENCES auth_governance_cases(case_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY(proof_id) REFERENCES auth_staff_mfa_proofs(proof_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CHECK(version>1 AND credential_version>0),
 CHECK(previous_status IN ('PENDING_REVIEW','NEEDS_CLARIFICATION')),
 CHECK(status IN ('NEEDS_CLARIFICATION','REJECTED','ACCEPTED_FOR_APPLICATION')),
 CHECK((status='ACCEPTED_FOR_APPLICATION' AND reason_code='FACTS_SUPPORTED')
 OR(status='NEEDS_CLARIFICATION' AND reason_code='NEEDS_DETAILS')
 OR(status='REJECTED' AND reason_code IN ('NOT_SUPPORTED','OUT_OF_SCOPE','DUPLICATE_REPORT')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE profiles_correction_review_intents (
 case_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
 report_id CHAR(36) NOT NULL,
 report_version BIGINT UNSIGNED NOT NULL,
 command_digest CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 command_ciphertext BLOB NOT NULL,
 key_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 grant_reference CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 FOREIGN KEY(case_id) REFERENCES auth_governance_cases(case_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY(report_id) REFERENCES profiles_correction_reports(report_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Purpose-specific bounded buckets. No auth_rate_limits, login/reset operations or raw IP.
CREATE TABLE profiles_correction_rate_buckets (
 operation VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 dimension_digest CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 window_start BIGINT UNSIGNED NOT NULL,
 hits INT UNSIGNED NOT NULL,
 PRIMARY KEY(operation,dimension_digest,window_start),
 CHECK(operation='PUBLIC_PROFILE_CORRECTION_SUBMIT'), CHECK(hits<=1000)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE auth_governance_cases DROP CHECK ck_auth_governance_case_operation,
 ADD CONSTRAINT ck_auth_governance_case_operation CHECK(operation IN
 ('INITIAL_OWNER','OWNER_REPLACEMENT','OWNER_REMOVAL','FIRST_DIRECTOR_BOOTSTRAP','MFA_RECOVERY',
 'PHYSICIAN_EVIDENCE_REVIEW','PHYSICIAN_AUTHORITY_CHANGE','PHYSICIAN_ENROLLMENT_REVIEW','PUBLIC_PROFILE_CORRECTION_REVIEW'));

DELIMITER $$
CREATE TRIGGER correction_report_no_update BEFORE UPDATE ON profiles_correction_reports FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='correction_report_immutable'; END$$
CREATE TRIGGER correction_report_no_delete BEFORE DELETE ON profiles_correction_reports FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='correction_report_immutable'; END$$
CREATE TRIGGER correction_decision_guard BEFORE INSERT ON profiles_correction_decisions FOR EACH ROW
BEGIN
 DECLARE v BIGINT UNSIGNED; DECLARE s VARCHAR(32);
 SELECT COALESCE(MAX(version),1) INTO v FROM profiles_correction_decisions WHERE report_id=NEW.report_id;
 SET s=COALESCE((SELECT status FROM profiles_correction_decisions WHERE report_id=NEW.report_id AND version=v),'PENDING_REVIEW');
 IF NEW.version<>v+1 OR NEW.previous_status<>s OR s IN ('REJECTED','ACCEPTED_FOR_APPLICATION')
 OR NOT EXISTS(SELECT 1 FROM profiles_correction_review_intents i JOIN auth_governance_cases c ON c.case_id=i.case_id
 JOIN profiles_correction_reports r ON r.report_id=i.report_id
 JOIN auth_staff_mfa_proofs p ON p.proof_id=NEW.proof_id
 WHERE i.case_id=NEW.case_id AND i.report_id=NEW.report_id AND i.report_version=v
 AND c.operation='PUBLIC_PROFILE_CORRECTION_REVIEW' AND c.status='CONSUMED'
 AND c.requester_account_id=NEW.reviewer_account_id AND c.profile_doctor_id=r.profile_doctor_id
 AND p.case_id=c.case_id AND p.operation=c.operation AND p.actor_account_id=NEW.reviewer_account_id
 AND p.credential_version=NEW.credential_version AND p.profile_doctor_id=r.profile_doctor_id
 AND p.status='CONSUMED' AND p.consumed_by_reference=NEW.decision_id)
 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='correction_decision_scope_or_version_denied'; END IF;
END$$
CREATE TRIGGER correction_decision_no_update BEFORE UPDATE ON profiles_correction_decisions FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='correction_history_append_only'; END$$
CREATE TRIGGER correction_decision_no_delete BEFORE DELETE ON profiles_correction_decisions FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='correction_history_append_only'; END$$
CREATE TRIGGER correction_intent_no_update BEFORE UPDATE ON profiles_correction_review_intents FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='correction_intent_immutable'; END$$
CREATE TRIGGER correction_intent_no_delete BEFORE DELETE ON profiles_correction_review_intents FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='correction_intent_immutable'; END$$
DELIMITER ;
