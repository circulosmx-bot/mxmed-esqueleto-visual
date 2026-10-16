-- C4-EV01. PREPARED ONLY. No normal-local authorization, seeds or backfill.
-- Apply after 2026_10_16_01 and _02, only to a newly owned disposable DB for QA.
-- Contact/evidence are distinct from admission, binding, ownership and signing.
CREATE TABLE auth_account_phone_verifications (
 account_id VARCHAR(64) NOT NULL PRIMARY KEY,
 contact_version BIGINT UNSIGNED NOT NULL,
 contact_digest CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 contact_ciphertext BLOB NOT NULL,
 key_id VARCHAR(64) NOT NULL,
 status VARCHAR(16) NOT NULL,
 challenge_id CHAR(36) NOT NULL,
 challenge_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 session_context JSON NOT NULL,
 expires_at DATETIME(6) NOT NULL,
 attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
 delivery_accepted TINYINT UNSIGNED NOT NULL DEFAULT 0,
 send_window_at DATETIME(6) NOT NULL,
 send_count TINYINT UNSIGNED NOT NULL,
 last_sent_at DATETIME(6) NOT NULL,
 verified_at DATETIME(6) NULL,
 audit_reference CHAR(36) NOT NULL,
 FOREIGN KEY (account_id) REFERENCES auth_accounts(account_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CHECK (contact_version>0 AND attempts<=5 AND send_count BETWEEN 1 AND 5),
 CHECK (delivery_accepted IN (0,1)),
 CHECK (status IN ('PENDING','VERIFIED','REVOKED')),
 CHECK ((status='VERIFIED')=(verified_at IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE auth_physician_evidence_requests (
 request_id CHAR(36) NOT NULL PRIMARY KEY,
 account_id VARCHAR(64) NOT NULL,
 origin VARCHAR(40) NOT NULL,
 profile_doctor_id VARCHAR(64) NULL,
 verified_identity_id BIGINT UNSIGNED NULL,
 professional_credential_id BIGINT UNSIGNED NULL,
 identity_revision_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
 credential_revision_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
 version BIGINT UNSIGNED NOT NULL DEFAULT 1,
 policy_version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
 status VARCHAR(16) NOT NULL DEFAULT 'DRAFT',
 phone_version BIGINT UNSIGNED NULL,
 case_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NULL,
 reviewer_account_id VARCHAR(64) NULL,
 reviewer_credential_version BIGINT UNSIGNED NULL,
 review_proof_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
 review_audit_reference CHAR(36) NULL,
 confirmation_audit_reference CHAR(36) NULL,
 reviewed_at DATETIME(6) NULL,
 reviewed_version BIGINT UNSIGNED NULL,
 review_reason VARCHAR(40) NULL,
 valid_until DATETIME(6) NULL,
 confirmation_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
 confirmation_context JSON NULL,
 confirmation_expires_at DATETIME(6) NULL,
 confirmed_at DATETIME(6) NULL,
 audit_reference CHAR(36) NOT NULL,
 created_at DATETIME(6) NOT NULL,
 active_request_key VARCHAR(150) GENERATED ALWAYS AS
   (CASE WHEN status IN ('DRAFT','SUBMITTED','APPROVED','CONFIRMED','SUSPENDED')
    THEN CONCAT(account_id,':',origin,':',COALESCE(profile_doctor_id,'NEW')) ELSE NULL END) STORED,
 UNIQUE KEY uq_c4_evidence_active_request (active_request_key),
 UNIQUE KEY uq_c4_evidence_case (case_id),
 FOREIGN KEY (account_id) REFERENCES auth_accounts(account_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 FOREIGN KEY (profile_doctor_id) REFERENCES profiles_doctors(doctor_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 FOREIGN KEY (verified_identity_id) REFERENCES profiles_verified_identities(verified_identity_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 FOREIGN KEY (profile_doctor_id,professional_credential_id) REFERENCES profiles_doctor_credentials(doctor_id,credential_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 FOREIGN KEY (reviewer_account_id) REFERENCES internal_staff(account_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 FOREIGN KEY (case_id) REFERENCES auth_governance_cases(case_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 FOREIGN KEY (review_proof_id) REFERENCES auth_staff_mfa_proofs(proof_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CHECK (version>0 AND policy_version=1),
 CHECK (origin IN ('CLAIM_EXISTING_PROFILE','REQUEST_NEW_PROFILE','CONFLICT_OR_TRANSFER_REVIEW')),
 CHECK ((origin='REQUEST_NEW_PROFILE' AND profile_doctor_id IS NULL AND verified_identity_id IS NULL AND professional_credential_id IS NULL)
    OR (origin<>'REQUEST_NEW_PROFILE' AND profile_doctor_id IS NOT NULL AND verified_identity_id IS NOT NULL AND professional_credential_id IS NOT NULL)),
 CHECK (status IN ('DRAFT','SUBMITTED','APPROVED','CONFIRMED','REJECTED','SUSPENDED','REVOKED','EXPIRED')),
 CHECK (reviewer_account_id IS NULL OR reviewer_account_id<>account_id),
 CHECK (status NOT IN ('APPROVED','CONFIRMED') OR
    (reviewer_account_id IS NOT NULL AND reviewer_credential_version IS NOT NULL AND reviewed_at IS NOT NULL AND case_id IS NOT NULL AND phone_version IS NOT NULL)),
 CHECK (status<>'CONFIRMED' OR confirmed_at IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE auth_physician_evidence_files (
 file_id CHAR(36) NOT NULL PRIMARY KEY,
 request_id CHAR(36) NOT NULL,
 kind VARCHAR(32) NOT NULL,
 storage_key VARCHAR(190) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 key_id VARCHAR(64) NOT NULL,
 ciphertext_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 mime_type VARCHAR(32) NOT NULL,
 retention_policy_reference VARCHAR(128) NOT NULL,
 source_expires_at DATETIME(6) NULL,
 review_expires_at DATETIME(6) NULL,
 review_access_context JSON NULL,
 created_at DATETIME(6) NOT NULL,
 UNIQUE KEY uq_c4_evidence_storage (storage_key),
 FOREIGN KEY (request_id) REFERENCES auth_physician_evidence_requests(request_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CHECK (kind IN ('OFFICIAL_ID','PROFESSIONAL_CREDENTIAL')),
 CHECK (mime_type IN ('application/pdf','image/jpeg','image/png')),
 CHECK (CHAR_LENGTH(TRIM(retention_policy_reference))>0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE auth_governance_cases DROP CHECK ck_auth_governance_case_operation,
 ADD CONSTRAINT ck_auth_governance_case_operation CHECK
 (operation IN ('INITIAL_OWNER','OWNER_REPLACEMENT','OWNER_REMOVAL','FIRST_DIRECTOR_BOOTSTRAP','MFA_RECOVERY','PHYSICIAN_EVIDENCE_REVIEW'));

DELIMITER $$
CREATE TRIGGER c4_evidence_origin_immutable BEFORE UPDATE ON auth_physician_evidence_requests FOR EACH ROW
BEGIN
 IF NOT (CAST(OLD.account_id AS BINARY)<=>CAST(NEW.account_id AS BINARY)) OR NOT (CAST(OLD.origin AS BINARY)<=>CAST(NEW.origin AS BINARY))
  OR NOT (CAST(OLD.profile_doctor_id AS BINARY)<=>CAST(NEW.profile_doctor_id AS BINARY)) OR NOT (CAST(OLD.request_id AS BINARY)<=>CAST(NEW.request_id AS BINARY))
  OR NOT (OLD.verified_identity_id<=>NEW.verified_identity_id)
  OR NOT (OLD.professional_credential_id<=>NEW.professional_credential_id)
  OR NOT (OLD.identity_revision_hash<=>NEW.identity_revision_hash)
  OR NOT (OLD.credential_revision_hash<=>NEW.credential_revision_hash)
 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='c4_evidence_origin_immutable'; END IF;
END$$
CREATE TRIGGER c4_evidence_object_immutable BEFORE UPDATE ON auth_physician_evidence_files FOR EACH ROW
BEGIN
 IF NOT (CAST(OLD.file_id AS BINARY)<=>CAST(NEW.file_id AS BINARY)) OR NOT (CAST(OLD.request_id AS BINARY)<=>CAST(NEW.request_id AS BINARY))
  OR NOT (CAST(OLD.kind AS BINARY)<=>CAST(NEW.kind AS BINARY)) OR NOT (CAST(OLD.storage_key AS BINARY)<=>CAST(NEW.storage_key AS BINARY))
  OR NOT (CAST(OLD.key_id AS BINARY)<=>CAST(NEW.key_id AS BINARY)) OR NOT (OLD.ciphertext_hash<=>NEW.ciphertext_hash)
  OR NOT (CAST(OLD.mime_type AS BINARY)<=>CAST(NEW.mime_type AS BINARY)) OR NOT (CAST(OLD.retention_policy_reference AS BINARY)<=>CAST(NEW.retention_policy_reference AS BINARY))
  OR NOT (OLD.source_expires_at<=>NEW.source_expires_at) OR NOT (OLD.created_at<=>NEW.created_at)
 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='c4_evidence_object_immutable'; END IF;
END$$
DELIMITER ;
