-- WORKSPACE02. Additive request integration; rehearse only on a NEW owned disposable DB.
-- PUB-ENR02 intake and C4 evidence remain canonical. No account/profile data seeds.
CREATE TABLE auth_physician_profile_request_evidence (
 origin_id CHAR(36) NOT NULL PRIMARY KEY,
 account_id VARCHAR(64) NOT NULL,
 evidence_request_id CHAR(36) NOT NULL UNIQUE,
 audit_reference CHAR(36) NOT NULL,
 created_at DATETIME(6) NOT NULL,
 FOREIGN KEY(origin_id,account_id) REFERENCES auth_physician_enrollment_origins(origin_id,account_id),
 FOREIGN KEY(evidence_request_id) REFERENCES auth_physician_evidence_requests(request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE auth_physician_profile_request_decisions (
 resolution_id CHAR(36) NOT NULL PRIMARY KEY,
 origin_id CHAR(36) NOT NULL,
 account_id VARCHAR(64) NOT NULL,
 intake_version BIGINT UNSIGNED NOT NULL,
 resolution_version BIGINT UNSIGNED NOT NULL,
 previous_state VARCHAR(32) NOT NULL,
 state VARCHAR(32) NOT NULL,
 request_type VARCHAR(40) NOT NULL,
 profile_doctor_id VARCHAR(64) NULL,
 reviewer_account_id VARCHAR(64) NOT NULL,
 reviewer_credential_version BIGINT UNSIGNED NOT NULL,
 case_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 proof_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 facts_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 command_digest CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 audit_reference CHAR(36) NOT NULL,
 reason_ciphertext MEDIUMBLOB NOT NULL,
 key_id VARCHAR(64) NOT NULL,
 created_at DATETIME(6) NOT NULL,
 UNIQUE KEY uq_profile_request_version(origin_id,resolution_version),
 FOREIGN KEY(origin_id,account_id) REFERENCES auth_physician_enrollment_origins(origin_id,account_id),
 FOREIGN KEY(origin_id,intake_version) REFERENCES auth_physician_enrollment_intake_revisions(origin_id,intake_version),
 FOREIGN KEY(profile_doctor_id) REFERENCES profiles_doctors(doctor_id),
 FOREIGN KEY(reviewer_account_id) REFERENCES internal_staff(account_id),
 FOREIGN KEY(case_id) REFERENCES auth_governance_cases(case_id),
 FOREIGN KEY(proof_id) REFERENCES auth_staff_mfa_proofs(proof_id),
 CHECK(resolution_version>0 AND intake_version>0 AND reviewer_account_id<>account_id),
 CHECK(state IN ('APPROVED','REJECTED','NEEDS_INFORMATION')),
 CHECK(request_type IN ('CLAIM_EXISTING_PROFILE','REQUEST_NEW_PROFILE')),
 CHECK(state<>'APPROVED' OR profile_doctor_id IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE auth_physician_profile_request_profiles (
 origin_id CHAR(36) NOT NULL PRIMARY KEY,
 account_id VARCHAR(64) NOT NULL,
 profile_doctor_id VARCHAR(64) NOT NULL UNIQUE,
 resolution_id CHAR(36) NOT NULL UNIQUE,
 audit_reference CHAR(36) NOT NULL,
 created_at DATETIME(6) NOT NULL,
 FOREIGN KEY(origin_id,account_id) REFERENCES auth_physician_enrollment_origins(origin_id,account_id),
 FOREIGN KEY(profile_doctor_id) REFERENCES profiles_doctors(doctor_id),
 FOREIGN KEY(resolution_id) REFERENCES auth_physician_profile_request_decisions(resolution_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE auth_physician_profile_request_reads (
 origin_id CHAR(36) NOT NULL,
 file_id CHAR(36) NOT NULL,
 actor_account_id VARCHAR(64) NOT NULL,
 session_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 evidence_version BIGINT UNSIGNED NOT NULL,
 ciphertext_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 audit_reference CHAR(36) NOT NULL,
 created_at DATETIME(6) NOT NULL,
 PRIMARY KEY(origin_id,file_id,actor_account_id,session_id,evidence_version),
 FOREIGN KEY(origin_id) REFERENCES auth_physician_enrollment_origins(origin_id),
 FOREIGN KEY(file_id) REFERENCES auth_physician_evidence_files(file_id),
 FOREIGN KEY(actor_account_id) REFERENCES internal_staff(account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE auth_physician_profile_request_responses (
 response_id CHAR(36) NOT NULL PRIMARY KEY,
 origin_id CHAR(36) NOT NULL,
 resolution_id CHAR(36) NOT NULL UNIQUE,
 account_id VARCHAR(64) NOT NULL,
 response_ciphertext MEDIUMBLOB NOT NULL,
 key_id VARCHAR(64) NOT NULL,
 audit_reference CHAR(36) NOT NULL,
 created_at DATETIME(6) NOT NULL,
 FOREIGN KEY(origin_id,account_id) REFERENCES auth_physician_enrollment_origins(origin_id,account_id),
 FOREIGN KEY(resolution_id) REFERENCES auth_physician_profile_request_decisions(resolution_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE auth_physician_profile_request_ownership (
 origin_id CHAR(36) NOT NULL,
 link_version BIGINT UNSIGNED NOT NULL DEFAULT 1,
 resolution_id CHAR(36) NOT NULL,
 case_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 membership_id VARCHAR(64) NOT NULL,
 created_at DATETIME(6) NOT NULL,
 PRIMARY KEY(origin_id,link_version),
 FOREIGN KEY(origin_id) REFERENCES auth_physician_enrollment_origins(origin_id),
 FOREIGN KEY(resolution_id) REFERENCES auth_physician_profile_request_decisions(resolution_id),
 FOREIGN KEY(case_id) REFERENCES auth_governance_cases(case_id),
 FOREIGN KEY(membership_id) REFERENCES auth_account_memberships(membership_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- A transaction-scoped lock serializes declared-license duplicate checks/materialization.
CREATE TABLE auth_physician_profile_request_locks (
 lock_name VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY
) ENGINE=InnoDB;
INSERT INTO auth_physician_profile_request_locks VALUES('profile_materialization');
ALTER TABLE auth_governance_cases DROP CHECK ck_auth_governance_case_operation,
 ADD CONSTRAINT ck_auth_governance_case_operation CHECK(operation IN (
 'FIRST_DIRECTOR_BOOTSTRAP','INITIAL_OWNER','INTERNAL_ADVISOR_MANAGEMENT_GRANT','INTERNAL_ADVISOR_MANAGEMENT_REVOKE',
 'INTERNAL_CAPABILITY_DELEGATE','INTERNAL_CAPABILITY_DELEGATION_REVOKE','INTERNAL_MASTER_APPOINT','INTERNAL_MASTER_REMOVE',
 'MFA_RECOVERY','OWNER_REMOVAL','OWNER_REPLACEMENT','PHYSICIAN_AUTHORITY_CHANGE','PHYSICIAN_ENROLLMENT_REVIEW',
 'PHYSICIAN_EVIDENCE_REVIEW','PROFESSIONAL_SOURCE_PROVISION','PUBLIC_PROFILE_CORRECTION_APPLY','PUBLIC_PROFILE_CORRECTION_REVIEW',
 'STAFF_ADMISSION_REVIEW','PHYSICIAN_CLAIM_RESOLVE','PHYSICIAN_NEW_PROFILE_RESOLVE'));
DELIMITER $$
CREATE TRIGGER profile_request_decision_guard BEFORE INSERT ON auth_physician_profile_request_decisions FOR EACH ROW
BEGIN
 DECLARE v BIGINT UNSIGNED; DECLARE s VARCHAR(32);
 SELECT COALESCE(MAX(resolution_version),0) INTO v FROM auth_physician_profile_request_decisions WHERE origin_id=NEW.origin_id;
 SET s=COALESCE((SELECT state FROM auth_physician_profile_request_decisions WHERE origin_id=NEW.origin_id AND resolution_version=v),'PENDING_REVIEW');
 IF NEW.resolution_version<>v+1 OR NEW.previous_state<>s OR s IN ('APPROVED','REJECTED')
 OR NOT EXISTS(SELECT 1 FROM auth_physician_enrollment_origins WHERE origin_id=NEW.origin_id AND account_id=NEW.account_id
 AND intake_state='SUBMITTED' AND intake_version=NEW.intake_version AND initial_origin=NEW.request_type)
 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='profile_request_transition_denied'; END IF;
END$$
CREATE TRIGGER profile_request_decision_update BEFORE UPDATE ON auth_physician_profile_request_decisions FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='profile_request_append_only'; END$$
CREATE TRIGGER profile_request_decision_delete BEFORE DELETE ON auth_physician_profile_request_decisions FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='profile_request_append_only'; END$$
CREATE TRIGGER profile_request_profile_update BEFORE UPDATE ON auth_physician_profile_request_profiles FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='profile_request_profile_immutable'; END$$
CREATE TRIGGER profile_request_profile_delete BEFORE DELETE ON auth_physician_profile_request_profiles FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='profile_request_profile_immutable'; END$$
CREATE TRIGGER profile_request_evidence_update BEFORE UPDATE ON auth_physician_profile_request_evidence FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='profile_request_evidence_immutable'; END$$
CREATE TRIGGER profile_request_evidence_delete BEFORE DELETE ON auth_physician_profile_request_evidence FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='profile_request_evidence_immutable'; END$$
CREATE TRIGGER profile_request_response_update BEFORE UPDATE ON auth_physician_profile_request_responses FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='profile_request_response_immutable'; END$$
CREATE TRIGGER profile_request_response_delete BEFORE DELETE ON auth_physician_profile_request_responses FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='profile_request_response_immutable'; END$$
DELIMITER ;
ALTER TABLE auth_profile_ownership_approval_endorsements DROP CHECK ck_auth_ownership_endorsement_capability,
 ADD CONSTRAINT ck_auth_ownership_endorsement_capability CHECK(capability IN ('profile_initial_ownership_approve','physician_claim_resolve','physician_new_profile_resolve'));
DELIMITER $$
CREATE TRIGGER profile_request_ownership_update BEFORE UPDATE ON auth_physician_profile_request_ownership FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='profile_request_ownership_immutable'; END$$
CREATE TRIGGER profile_request_ownership_delete BEFORE DELETE ON auth_physician_profile_request_ownership FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='profile_request_ownership_immutable'; END$$
DELIMITER ;
