-- UX-ALIGN01 DRAFT ONLY: not a migration, not applied, no runtime consumer.
-- Future disposable rehearsal after review; same C4 schema after migrations 01..04.
-- No ALTER/weakening of final evidence, admission, binding or membership rules.
-- Services for account binding, versioning, matching, MFA/audit and handoff are NOT implemented.

CREATE TABLE auth_physician_enrollment_origins (
 origin_id CHAR(36) NOT NULL PRIMARY KEY,
 handle_digest CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 account_id VARCHAR(64) NULL,
 initial_origin VARCHAR(40) NOT NULL,
 original_profile_doctor_id VARCHAR(64) NULL,
 original_public_path VARCHAR(1024) NOT NULL,
 source_provenance JSON NOT NULL,
 version BIGINT UNSIGNED NOT NULL DEFAULT 1,
 status VARCHAR(16) NOT NULL DEFAULT 'PRESENTED',
 expires_at DATETIME(6) NOT NULL,
 bound_at DATETIME(6) NULL,
 created_at DATETIME(6) NOT NULL,
 UNIQUE KEY uq_enrollment_handle (handle_digest),
 FOREIGN KEY (account_id) REFERENCES auth_accounts(account_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 FOREIGN KEY (original_profile_doctor_id) REFERENCES profiles_doctors(doctor_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CHECK (version>0 AND expires_at>created_at),
 CHECK (initial_origin IN ('CLAIM_EXISTING_PROFILE','REQUEST_NEW_PROFILE')),
 CHECK ((initial_origin='CLAIM_EXISTING_PROFILE' AND original_profile_doctor_id IS NOT NULL)
     OR (initial_origin='REQUEST_NEW_PROFILE' AND original_profile_doctor_id IS NULL)),
 CHECK (status IN ('PRESENTED','BOUND','CONSUMED','EXPIRED')),
 CHECK ((account_id IS NULL)=(bound_at IS NULL)),
 CHECK (status NOT IN ('BOUND','CONSUMED') OR account_id IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE auth_physician_enrollment_intake_revisions (
 origin_id CHAR(36) NOT NULL,
 intake_version BIGINT UNSIGNED NOT NULL,
 account_id VARCHAR(64) NOT NULL,
 intake_ciphertext MEDIUMBLOB NOT NULL,
 key_id VARCHAR(64) NOT NULL,
 retention_policy_reference VARCHAR(128) NOT NULL,
 audit_reference CHAR(36) NOT NULL,
 created_at DATETIME(6) NOT NULL,
 PRIMARY KEY (origin_id,intake_version),
 FOREIGN KEY (origin_id) REFERENCES auth_physician_enrollment_origins(origin_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 FOREIGN KEY (account_id) REFERENCES auth_accounts(account_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CHECK (intake_version>0 AND OCTET_LENGTH(intake_ciphertext)>0),
 CHECK (CHAR_LENGTH(TRIM(key_id))>0 AND CHAR_LENGTH(TRIM(retention_policy_reference))>0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Append-only reviewed resolution. It is NOT accreditation or ownership authority.
CREATE TABLE auth_physician_enrollment_resolutions (
 resolution_id CHAR(36) NOT NULL PRIMARY KEY,
 origin_id CHAR(36) NOT NULL,
 intake_version BIGINT UNSIGNED NOT NULL,
 resolution_kind VARCHAR(24) NOT NULL,
 resolved_profile_doctor_id VARCHAR(64) NULL,
 verified_identity_id BIGINT UNSIGNED NULL,
 professional_credential_id BIGINT UNSIGNED NULL,
 identity_revision_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
 credential_revision_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
 reviewer_account_id VARCHAR(64) NOT NULL,
 case_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 review_proof_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 audit_reference CHAR(36) NOT NULL,
 created_at DATETIME(6) NOT NULL,
 FOREIGN KEY (origin_id,intake_version) REFERENCES auth_physician_enrollment_intake_revisions(origin_id,intake_version) ON UPDATE RESTRICT ON DELETE RESTRICT,
 FOREIGN KEY (resolved_profile_doctor_id) REFERENCES profiles_doctors(doctor_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 FOREIGN KEY (verified_identity_id) REFERENCES profiles_verified_identities(verified_identity_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 FOREIGN KEY (resolved_profile_doctor_id,professional_credential_id) REFERENCES profiles_doctor_credentials(doctor_id,credential_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 FOREIGN KEY (reviewer_account_id) REFERENCES internal_staff(account_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 FOREIGN KEY (case_id) REFERENCES auth_governance_cases(case_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 FOREIGN KEY (review_proof_id) REFERENCES auth_staff_mfa_proofs(proof_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CHECK (resolution_kind IN ('EXISTING_PROFILE','NO_MATCH','CONFLICT')),
 CHECK ((resolution_kind='EXISTING_PROFILE' AND resolved_profile_doctor_id IS NOT NULL
     AND verified_identity_id IS NOT NULL AND professional_credential_id IS NOT NULL
     AND identity_revision_hash IS NOT NULL AND credential_revision_hash IS NOT NULL)
   OR (resolution_kind IN ('NO_MATCH','CONFLICT') AND resolved_profile_doctor_id IS NULL
     AND verified_identity_id IS NULL AND professional_credential_id IS NULL
     AND identity_revision_hash IS NULL AND credential_revision_hash IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DELIMITER $$
CREATE TRIGGER enrollment_origin_immutable BEFORE UPDATE ON auth_physician_enrollment_origins FOR EACH ROW
BEGIN
 IF NOT (CAST(OLD.origin_id AS BINARY)<=>CAST(NEW.origin_id AS BINARY))
  OR NOT (CAST(OLD.handle_digest AS BINARY)<=>CAST(NEW.handle_digest AS BINARY))
  OR NOT (CAST(OLD.initial_origin AS BINARY)<=>CAST(NEW.initial_origin AS BINARY))
  OR NOT (CAST(OLD.original_profile_doctor_id AS BINARY)<=>CAST(NEW.original_profile_doctor_id AS BINARY))
  OR NOT (CAST(OLD.original_public_path AS BINARY)<=>CAST(NEW.original_public_path AS BINARY))
  OR NOT (OLD.source_provenance<=>NEW.source_provenance)
  OR NOT (OLD.created_at<=>NEW.created_at)
  OR (OLD.account_id IS NOT NULL AND NOT (CAST(OLD.account_id AS BINARY)<=>CAST(NEW.account_id AS BINARY)))
 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='enrollment_origin_immutable'; END IF;
END$$
CREATE TRIGGER enrollment_resolution_original_profile BEFORE INSERT ON auth_physician_enrollment_resolutions FOR EACH ROW
BEGIN
 DECLARE source_kind VARCHAR(40);
 DECLARE original_id VARCHAR(64);
 SELECT initial_origin,original_profile_doctor_id INTO source_kind,original_id
 FROM auth_physician_enrollment_origins WHERE origin_id=NEW.origin_id;
 IF source_kind='CLAIM_EXISTING_PROFILE' AND NEW.resolution_kind='EXISTING_PROFILE'
   AND NOT (CAST(original_id AS BINARY)<=>CAST(NEW.resolved_profile_doctor_id AS BINARY))
 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='enrollment_original_profile_mismatch'; END IF;
END$$
CREATE TRIGGER enrollment_intake_no_update BEFORE UPDATE ON auth_physician_enrollment_intake_revisions FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='enrollment_intake_append_only'; END$$
CREATE TRIGGER enrollment_intake_no_delete BEFORE DELETE ON auth_physician_enrollment_intake_revisions FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='enrollment_intake_append_only'; END$$
CREATE TRIGGER enrollment_resolution_no_update BEFORE UPDATE ON auth_physician_enrollment_resolutions FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='enrollment_resolution_append_only'; END$$
CREATE TRIGGER enrollment_resolution_no_delete BEFORE DELETE ON auth_physician_enrollment_resolutions FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='enrollment_resolution_append_only'; END$$
DELIMITER ;
