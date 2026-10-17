-- PUB-ENR03. Prepared for NEW disposable targets only, after 01 through 05.
-- No seeds, live activation, source provisioning, membership or ownership writer.
CREATE TABLE auth_physician_enrollment_resolutions (
 resolution_id CHAR(36) NOT NULL PRIMARY KEY,
 origin_id CHAR(36) NOT NULL,
 account_id VARCHAR(64) NOT NULL,
 intake_version BIGINT UNSIGNED NOT NULL,
 resolution_version BIGINT UNSIGNED NOT NULL,
 previous_state VARCHAR(32) NOT NULL,
 state VARCHAR(32) NOT NULL,
 initial_origin VARCHAR(40) NOT NULL,
 original_profile_doctor_id VARCHAR(64) NULL,
 resolved_profile_doctor_id VARCHAR(64) NULL,
 matching_snapshot JSON NOT NULL,
 facts_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 verified_identity_id BIGINT UNSIGNED NULL,
 professional_credential_id BIGINT UNSIGNED NULL,
 identity_revision_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
 credential_revision_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
 reviewer_account_id VARCHAR(64) NOT NULL,
 reviewer_credential_version BIGINT UNSIGNED NOT NULL,
 case_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 proof_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 command_digest CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 audit_reference CHAR(36) NOT NULL,
 created_at DATETIME(6) NOT NULL,
 UNIQUE KEY uq_enrollment_resolution_version(origin_id,resolution_version),
 UNIQUE KEY uq_enrollment_resolution_case(case_id),
 UNIQUE KEY uq_enrollment_resolution_proof(proof_id),
 FOREIGN KEY(origin_id,account_id) REFERENCES auth_physician_enrollment_origins(origin_id,account_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY(origin_id,intake_version) REFERENCES auth_physician_enrollment_intake_revisions(origin_id,intake_version) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY(original_profile_doctor_id) REFERENCES profiles_doctors(doctor_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY(resolved_profile_doctor_id) REFERENCES profiles_doctors(doctor_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY(verified_identity_id) REFERENCES profiles_verified_identities(verified_identity_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY(resolved_profile_doctor_id,professional_credential_id) REFERENCES profiles_doctor_credentials(doctor_id,credential_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY(reviewer_account_id) REFERENCES internal_staff(account_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY(case_id) REFERENCES auth_governance_cases(case_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY(proof_id) REFERENCES auth_staff_mfa_proofs(proof_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CHECK(resolution_version>0 AND intake_version>0 AND reviewer_credential_version>0 AND reviewer_account_id<>account_id),
 CHECK(state IN ('PENDING_REVIEW','NEEDS_INFORMATION','PENDING_SOURCES','PENDING_NEW_PROFILE','CONFLICT','REJECTED','APPROVED','REVOKED')),
 CHECK((initial_origin='CLAIM_EXISTING_PROFILE' AND original_profile_doctor_id IS NOT NULL
     AND (resolved_profile_doctor_id IS NULL OR resolved_profile_doctor_id=original_profile_doctor_id))
   OR (initial_origin='REQUEST_NEW_PROFILE' AND original_profile_doctor_id IS NULL)),
 CHECK(state<>'APPROVED' OR (resolved_profile_doctor_id IS NOT NULL AND verified_identity_id IS NOT NULL
   AND professional_credential_id IS NOT NULL AND identity_revision_hash IS NOT NULL AND credential_revision_hash IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE auth_governance_cases DROP CHECK ck_auth_governance_case_operation,
 ADD CONSTRAINT ck_auth_governance_case_operation CHECK(operation IN
 ('INITIAL_OWNER','OWNER_REPLACEMENT','OWNER_REMOVAL','FIRST_DIRECTOR_BOOTSTRAP','MFA_RECOVERY',
  'PHYSICIAN_EVIDENCE_REVIEW','PHYSICIAN_AUTHORITY_CHANGE','PHYSICIAN_ENROLLMENT_REVIEW'));

-- Migration 03's anonymous shape check is located by its exact column contract.
-- Assert the expected single constraint before touching the C4 shape.
SET @enrollment_shape_count=(SELECT COUNT(*) FROM information_schema.CHECK_CONSTRAINTS c
 JOIN information_schema.TABLE_CONSTRAINTS t USING(CONSTRAINT_SCHEMA,CONSTRAINT_NAME)
 WHERE t.CONSTRAINT_SCHEMA=DATABASE() AND t.TABLE_NAME='auth_physician_evidence_requests'
 AND c.CHECK_CLAUSE LIKE '%`origin`%' AND c.CHECK_CLAUSE LIKE '%`verified_identity_id`%'
 AND c.CHECK_CLAUSE LIKE '%`professional_credential_id`%');
CREATE TEMPORARY TABLE enrollment_shape_guard(ok INT NOT NULL CHECK(ok=1));
INSERT INTO enrollment_shape_guard VALUES(@enrollment_shape_count);
DROP TEMPORARY TABLE enrollment_shape_guard;
SET @enrollment_shape_name=(SELECT t.CONSTRAINT_NAME FROM information_schema.CHECK_CONSTRAINTS c
 JOIN information_schema.TABLE_CONSTRAINTS t USING(CONSTRAINT_SCHEMA,CONSTRAINT_NAME)
 WHERE t.CONSTRAINT_SCHEMA=DATABASE() AND t.TABLE_NAME='auth_physician_evidence_requests'
 AND c.CHECK_CLAUSE LIKE '%`origin`%' AND c.CHECK_CLAUSE LIKE '%`verified_identity_id`%'
 AND c.CHECK_CLAUSE LIKE '%`professional_credential_id`%');
SET @enrollment_shape_ddl=CONCAT('ALTER TABLE auth_physician_evidence_requests DROP CHECK `',REPLACE(@enrollment_shape_name,'`','``'),'`');
PREPARE enrollment_shape_statement FROM @enrollment_shape_ddl;
EXECUTE enrollment_shape_statement;
DEALLOCATE PREPARE enrollment_shape_statement;
ALTER TABLE auth_physician_evidence_requests
 ADD COLUMN enrollment_resolution_id CHAR(36) NULL,
 ADD UNIQUE KEY uq_evidence_enrollment_resolution(enrollment_resolution_id),
 ADD FOREIGN KEY(enrollment_resolution_id) REFERENCES auth_physician_enrollment_resolutions(resolution_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 ADD CONSTRAINT ck_c4_explicit_resolved_origin CHECK(
  (origin='REQUEST_NEW_PROFILE' AND profile_doctor_id IS NULL AND verified_identity_id IS NULL AND professional_credential_id IS NULL AND enrollment_resolution_id IS NULL)
  OR (origin='REQUEST_NEW_PROFILE' AND enrollment_resolution_id IS NOT NULL AND profile_doctor_id IS NOT NULL AND verified_identity_id IS NOT NULL AND professional_credential_id IS NOT NULL)
  OR (origin<>'REQUEST_NEW_PROFILE' AND profile_doctor_id IS NOT NULL AND verified_identity_id IS NOT NULL AND professional_credential_id IS NOT NULL));

CREATE TABLE auth_physician_enrollment_handoffs (
 origin_id CHAR(36) NOT NULL PRIMARY KEY,
 resolution_id CHAR(36) NOT NULL UNIQUE,
 evidence_request_id CHAR(36) NOT NULL UNIQUE,
 audit_reference CHAR(36) NOT NULL,
 created_at DATETIME(6) NOT NULL,
 FOREIGN KEY(origin_id) REFERENCES auth_physician_enrollment_origins(origin_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY(resolution_id) REFERENCES auth_physician_enrollment_resolutions(resolution_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY(evidence_request_id) REFERENCES auth_physician_evidence_requests(request_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DELIMITER $$
CREATE TRIGGER enrollment_resolution_guard BEFORE INSERT ON auth_physician_enrollment_resolutions FOR EACH ROW
BEGIN
 DECLARE v BIGINT UNSIGNED; DECLARE s VARCHAR(32);
 SELECT MAX(resolution_version) INTO v FROM auth_physician_enrollment_resolutions WHERE origin_id=NEW.origin_id;
 SET v=COALESCE(v,0);
 SET s=COALESCE((SELECT state FROM auth_physician_enrollment_resolutions WHERE origin_id=NEW.origin_id AND resolution_version=v),'PENDING_REVIEW');
 IF NEW.resolution_version<>v+1 OR NEW.previous_state<>s
 OR s IN ('REJECTED','REVOKED') OR (s='APPROVED' AND NEW.state<>'REVOKED') OR (s<>'APPROVED' AND NEW.state='REVOKED')
 OR NOT EXISTS(SELECT 1 FROM auth_physician_enrollment_origins o WHERE o.origin_id=NEW.origin_id
   AND o.account_id=NEW.account_id AND o.intake_version=NEW.intake_version AND o.intake_state='SUBMITTED'
   AND o.initial_origin=NEW.initial_origin AND o.original_profile_doctor_id<=>NEW.original_profile_doctor_id)
 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='enrollment_resolution_transition_denied'; END IF;
END$$
CREATE TRIGGER enrollment_resolution_no_update BEFORE UPDATE ON auth_physician_enrollment_resolutions FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='enrollment_resolution_append_only'; END$$
CREATE TRIGGER enrollment_resolution_no_delete BEFORE DELETE ON auth_physician_enrollment_resolutions FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='enrollment_resolution_append_only'; END$$
CREATE TRIGGER c4_enrollment_link_guard BEFORE INSERT ON auth_physician_evidence_requests FOR EACH ROW
BEGIN
 IF NEW.enrollment_resolution_id IS NOT NULL AND NOT EXISTS(SELECT 1 FROM auth_physician_enrollment_resolutions r
 WHERE r.resolution_id=NEW.enrollment_resolution_id AND r.state='APPROVED' AND r.account_id=NEW.account_id
 AND r.initial_origin=NEW.origin AND r.resolved_profile_doctor_id=NEW.profile_doctor_id
 AND r.verified_identity_id=NEW.verified_identity_id AND r.professional_credential_id=NEW.professional_credential_id
 AND r.identity_revision_hash=NEW.identity_revision_hash AND r.credential_revision_hash=NEW.credential_revision_hash
 AND NOT EXISTS(SELECT 1 FROM auth_physician_enrollment_resolutions n WHERE n.origin_id=r.origin_id AND n.resolution_version>r.resolution_version))
 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='c4_enrollment_exact_resolution_required'; END IF;
END$$
CREATE TRIGGER c4_enrollment_link_immutable BEFORE UPDATE ON auth_physician_evidence_requests FOR EACH ROW
BEGIN
 IF NOT(OLD.enrollment_resolution_id<=>NEW.enrollment_resolution_id)
 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='c4_enrollment_resolution_immutable'; END IF;
END$$
CREATE TRIGGER enrollment_handoff_guard BEFORE INSERT ON auth_physician_enrollment_handoffs FOR EACH ROW
BEGIN
 IF NOT EXISTS(SELECT 1 FROM auth_physician_enrollment_resolutions r JOIN auth_physician_evidence_requests e
 ON e.enrollment_resolution_id=r.resolution_id AND e.account_id=r.account_id AND e.origin=r.initial_origin
 AND e.profile_doctor_id=r.resolved_profile_doctor_id WHERE r.resolution_id=NEW.resolution_id
 AND r.origin_id=NEW.origin_id AND e.request_id=NEW.evidence_request_id AND r.state='APPROVED')
 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='enrollment_handoff_scope_denied'; END IF;
END$$
CREATE TRIGGER enrollment_handoff_no_update BEFORE UPDATE ON auth_physician_enrollment_handoffs FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='enrollment_handoff_immutable'; END$$
CREATE TRIGGER enrollment_handoff_no_delete BEFORE DELETE ON auth_physician_enrollment_handoffs FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='enrollment_handoff_immutable'; END$$
DELIMITER ;
