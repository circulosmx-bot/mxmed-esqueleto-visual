-- C4-ADM02-IMP02. PREPARED ONLY, separate deployment review required.
-- QA order: 01 -> 02 -> 03 -> 04. No seeds, legacy adoption or mxmed authorization.
-- Abort BEFORE altering existing authority tables if a prior issuer/fixture populated 02.
CREATE TEMPORARY TABLE c4_issuer_empty_authorities_guard (ok TINYINT NOT NULL CHECK (ok=1));
INSERT INTO c4_issuer_empty_authorities_guard
 SELECT IF((SELECT COUNT(*) FROM profiles_physician_admissions)=0
       AND (SELECT COUNT(*) FROM auth_account_professional_bindings)=0,1,0);
DROP TEMPORARY TABLE c4_issuer_empty_authorities_guard;

ALTER TABLE profiles_physician_admissions
 DROP INDEX uq_physician_admission_profile,
 ADD account_id VARCHAR(64) NOT NULL,
 ADD evidence_request_id CHAR(36) NOT NULL,
 ADD evidence_version BIGINT UNSIGNED NOT NULL,
 ADD active_doctor VARCHAR(64) GENERATED ALWAYS AS
   (CASE WHEN status IN ('PENDING','APPROVED','SUSPENDED') THEN doctor_id ELSE NULL END) STORED,
 ADD UNIQUE KEY uq_physician_admission_effective_profile (active_doctor),
 ADD FOREIGN KEY (account_id) REFERENCES auth_accounts(account_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 ADD FOREIGN KEY (evidence_request_id) REFERENCES auth_physician_evidence_requests(request_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 ADD CHECK (evidence_version>0);

ALTER TABLE auth_account_professional_bindings
 ADD evidence_request_id CHAR(36) NOT NULL,
 ADD evidence_version BIGINT UNSIGNED NOT NULL,
 ADD UNIQUE KEY uq_professional_binding_effective_profile (active_profile),
 ADD FOREIGN KEY (evidence_request_id) REFERENCES auth_physician_evidence_requests(request_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 ADD CHECK (evidence_version>0);

-- Written only from the successful, consumed C4 confirmation transaction.
-- Its ID is the request UUID; clients never supply account/profile/source fields.
CREATE TABLE auth_physician_issuance_workflows (
 command_id CHAR(36) NOT NULL PRIMARY KEY,
 evidence_request_id CHAR(36) NOT NULL,
 evidence_version BIGINT UNSIGNED NOT NULL,
 account_id VARCHAR(64) NOT NULL,
 profile_doctor_id VARCHAR(64) NOT NULL,
 payload_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 staged_result JSON NULL,
 issued_result JSON NULL,
 created_at DATETIME(6) NOT NULL,
 UNIQUE KEY uq_physician_issuance_evidence (evidence_request_id,evidence_version),
 FOREIGN KEY (evidence_request_id) REFERENCES auth_physician_evidence_requests(request_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY (account_id) REFERENCES auth_accounts(account_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY (profile_doctor_id) REFERENCES profiles_doctors(doctor_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CHECK (command_id=evidence_request_id AND evidence_version>0),
 CHECK (payload_hash REGEXP '^[a-f0-9]{64}$'),
 CHECK (issued_result IS NULL OR staged_result IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Append-only revision journal; original approval attribution never changes.
CREATE TABLE auth_physician_authority_history (
 history_id CHAR(36) NOT NULL PRIMARY KEY,
 authority_type VARCHAR(16) NOT NULL,
 authority_id CHAR(36) NOT NULL,
 authority_version BIGINT UNSIGNED NOT NULL,
 previous_status VARCHAR(16) NOT NULL,
 status VARCHAR(16) NOT NULL,
 actor_account_id VARCHAR(64) NOT NULL,
 evidence_request_id CHAR(36) NOT NULL,
 evidence_version BIGINT UNSIGNED NOT NULL,
 case_reference VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 decision_reference VARCHAR(128) NOT NULL,
 audit_reference CHAR(36) NOT NULL,
 audit_physical_id CHAR(64) GENERATED ALWAYS AS (SHA2(audit_reference,256)) STORED,
 command_reference VARCHAR(128) NOT NULL,
 created_at DATETIME(6) NOT NULL,
 UNIQUE KEY uq_physician_authority_revision (authority_type,authority_id,authority_version),
 UNIQUE KEY uq_physician_authority_command (authority_type,authority_id,command_reference),
 FOREIGN KEY (actor_account_id) REFERENCES auth_accounts(account_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY (evidence_request_id) REFERENCES auth_physician_evidence_requests(request_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY (case_reference) REFERENCES auth_governance_cases(case_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY (audit_physical_id) REFERENCES platform_audit_events(event_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CHECK (authority_type IN ('ADMISSION','BINDING') AND authority_version>0 AND evidence_version>0),
 CHECK (status IN ('PENDING','APPROVED','ACTIVE','REJECTED','SUSPENDED','REVOKED','EXPIRED'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE auth_governance_cases DROP CHECK ck_auth_governance_case_operation,
 ADD CONSTRAINT ck_auth_governance_case_operation CHECK
 (operation IN ('INITIAL_OWNER','OWNER_REPLACEMENT','OWNER_REMOVAL','FIRST_DIRECTOR_BOOTSTRAP','MFA_RECOVERY','PHYSICIAN_EVIDENCE_REVIEW','PHYSICIAN_AUTHORITY_CHANGE'));

DELIMITER $$
CREATE TRIGGER physician_workflow_immutable BEFORE UPDATE ON auth_physician_issuance_workflows FOR EACH ROW
BEGIN
 IF NOT (CAST(OLD.command_id AS BINARY)<=>CAST(NEW.command_id AS BINARY))
  OR NOT (CAST(OLD.evidence_request_id AS BINARY)<=>CAST(NEW.evidence_request_id AS BINARY))
  OR OLD.evidence_version<>NEW.evidence_version
  OR NOT (CAST(OLD.account_id AS BINARY)<=>CAST(NEW.account_id AS BINARY))
  OR NOT (CAST(OLD.profile_doctor_id AS BINARY)<=>CAST(NEW.profile_doctor_id AS BINARY))
  OR OLD.payload_hash<>NEW.payload_hash OR OLD.created_at<>NEW.created_at
  OR (OLD.staged_result IS NOT NULL AND NOT (OLD.staged_result<=>NEW.staged_result))
  OR (OLD.issued_result IS NOT NULL AND NOT (OLD.issued_result<=>NEW.issued_result))
 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='physician_workflow_immutable'; END IF;
END$$
CREATE TRIGGER physician_admission_attribution_immutable BEFORE UPDATE ON profiles_physician_admissions FOR EACH ROW
BEGIN
 IF NOT (CAST(OLD.admission_id AS BINARY)<=>CAST(NEW.admission_id AS BINARY))
  OR NOT (CAST(OLD.doctor_id AS BINARY)<=>CAST(NEW.doctor_id AS BINARY))
  OR NOT (CAST(OLD.account_id AS BINARY)<=>CAST(NEW.account_id AS BINARY))
  OR OLD.verified_identity_id<>NEW.verified_identity_id OR OLD.professional_credential_id<>NEW.professional_credential_id
  OR NOT (CAST(OLD.reviewed_by_account_id AS BINARY)<=>CAST(NEW.reviewed_by_account_id AS BINARY))
  OR NOT (CAST(OLD.case_reference AS BINARY)<=>CAST(NEW.case_reference AS BINARY))
  OR NOT (CAST(OLD.decision_reference AS BINARY)<=>CAST(NEW.decision_reference AS BINARY))
  OR NOT (CAST(OLD.evidence_reference AS BINARY)<=>CAST(NEW.evidence_reference AS BINARY))
  OR NOT (CAST(OLD.evidence_request_id AS BINARY)<=>CAST(NEW.evidence_request_id AS BINARY))
  OR OLD.evidence_version<>NEW.evidence_version OR NOT (OLD.approved_at<=>NEW.approved_at)
  OR OLD.valid_from<>NEW.valid_from OR NOT (OLD.valid_until<=>NEW.valid_until) OR NOT (OLD.reverify_at<=>NEW.reverify_at)
 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='physician_admission_attribution_immutable'; END IF;
END$$
CREATE TRIGGER physician_binding_attribution_immutable BEFORE UPDATE ON auth_account_professional_bindings FOR EACH ROW
BEGIN
 IF NOT (CAST(OLD.binding_id AS BINARY)<=>CAST(NEW.binding_id AS BINARY))
  OR NOT (CAST(OLD.account_id AS BINARY)<=>CAST(NEW.account_id AS BINARY))
  OR NOT (CAST(OLD.profile_doctor_id AS BINARY)<=>CAST(NEW.profile_doctor_id AS BINARY))
  OR NOT (CAST(OLD.admission_id AS BINARY)<=>CAST(NEW.admission_id AS BINARY))
  OR OLD.verified_identity_id<>NEW.verified_identity_id OR OLD.identity_revision_hash<>NEW.identity_revision_hash
  OR OLD.credential_revision_hash<>NEW.credential_revision_hash
  OR NOT (CAST(OLD.relationship_evidence_reference AS BINARY)<=>CAST(NEW.relationship_evidence_reference AS BINARY))
  OR NOT (CAST(OLD.acceptance_reference AS BINARY)<=>CAST(NEW.acceptance_reference AS BINARY))
  OR NOT (CAST(OLD.evidence_request_id AS BINARY)<=>CAST(NEW.evidence_request_id AS BINARY))
  OR OLD.evidence_version<>NEW.evidence_version OR OLD.valid_from<>NEW.valid_from
  OR NOT (OLD.valid_until<=>NEW.valid_until) OR NOT (OLD.reverify_at<=>NEW.reverify_at)
 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='physician_binding_attribution_immutable'; END IF;
END$$
CREATE TRIGGER physician_history_no_update BEFORE UPDATE ON auth_physician_authority_history FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='physician_history_append_only'; END$$
CREATE TRIGGER physician_history_no_delete BEFORE DELETE ON auth_physician_authority_history FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='physician_history_append_only'; END$$
CREATE TRIGGER physician_admission_no_delete BEFORE DELETE ON profiles_physician_admissions FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='physician_admission_history_required'; END$$
CREATE TRIGGER physician_binding_no_delete BEFORE DELETE ON auth_account_professional_bindings FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='physician_binding_history_required'; END$$
CREATE TRIGGER physician_workflow_no_delete BEFORE DELETE ON auth_physician_issuance_workflows FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='physician_workflow_history_required'; END$$
DELIMITER ;
