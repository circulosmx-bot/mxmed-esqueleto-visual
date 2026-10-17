-- ACT03 preparation only. After ACT02's complete ten-stage package. No seeds/live approval.
ALTER TABLE auth_governance_cases DROP CHECK ck_auth_governance_case_operation,
 ADD CONSTRAINT ck_auth_governance_case_operation CHECK(operation IN
 ('INITIAL_OWNER','OWNER_REPLACEMENT','OWNER_REMOVAL','FIRST_DIRECTOR_BOOTSTRAP','MFA_RECOVERY',
 'PHYSICIAN_EVIDENCE_REVIEW','PHYSICIAN_AUTHORITY_CHANGE','PHYSICIAN_ENROLLMENT_REVIEW','PROFESSIONAL_SOURCE_PROVISION'));
CREATE TABLE auth_professional_source_requests (
 origin_id CHAR(36) NOT NULL PRIMARY KEY,
 account_id VARCHAR(64) NOT NULL,
 profile_doctor_id VARCHAR(64) NOT NULL,
 intake_version BIGINT UNSIGNED NOT NULL,
 version BIGINT UNSIGNED NOT NULL DEFAULT 1,
 state VARCHAR(16) NOT NULL DEFAULT 'COLLECTING',
 collection_case_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 audit_reference CHAR(36) NOT NULL,
 created_at DATETIME(6) NOT NULL,
 UNIQUE KEY uq_source_request_scope(origin_id,account_id,profile_doctor_id),
 FOREIGN KEY(origin_id,account_id) REFERENCES auth_physician_enrollment_origins(origin_id,account_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY(profile_doctor_id) REFERENCES profiles_doctors(doctor_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY(collection_case_id) REFERENCES auth_governance_cases(case_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CHECK(state IN ('COLLECTING','FROZEN','COMPLETE','REVOKED')), CHECK(version>0 AND intake_version>0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE auth_professional_source_files (
 file_id CHAR(36) NOT NULL PRIMARY KEY,
 origin_id CHAR(36) NOT NULL,
 kind VARCHAR(32) NOT NULL,
 storage_key VARCHAR(512) NOT NULL UNIQUE,
 key_id VARCHAR(64) NOT NULL,
 ciphertext_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 mime_type VARCHAR(64) NOT NULL,
 retention_policy_reference VARCHAR(128) NOT NULL,
 created_at DATETIME(6) NOT NULL,
 UNIQUE KEY uq_source_document_kind(origin_id,kind),
 FOREIGN KEY(origin_id) REFERENCES auth_professional_source_requests(origin_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CHECK(kind IN ('OFFICIAL_ID','PROFESSIONAL_CREDENTIAL')),
 CHECK(mime_type IN ('application/pdf','image/jpeg','image/png'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE auth_professional_source_reads (
 file_id CHAR(36) NOT NULL,
 actor_account_id VARCHAR(64) NOT NULL,
 session_id VARCHAR(128) NOT NULL,
 request_version BIGINT UNSIGNED NOT NULL,
 ciphertext_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 audit_reference CHAR(36) NOT NULL,
 created_at DATETIME(6) NOT NULL,
 PRIMARY KEY(file_id,actor_account_id,session_id,request_version),
 FOREIGN KEY(file_id) REFERENCES auth_professional_source_files(file_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY(actor_account_id) REFERENCES internal_staff(account_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE auth_professional_source_operations (
 operation_id CHAR(36) NOT NULL PRIMARY KEY,
 origin_id CHAR(36) NOT NULL UNIQUE,
 state VARCHAR(16) NOT NULL,
 frozen_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 payload_ciphertext MEDIUMBLOB NOT NULL,
 key_id VARCHAR(64) NOT NULL,
 authorization_case_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 reviewer_account_id VARCHAR(64) NOT NULL,
 result_json JSON NULL,
 result_identity_id BIGINT UNSIGNED NULL,
 result_credential_id BIGINT UNSIGNED NULL,
 audit_reference CHAR(36) NOT NULL,
 created_at DATETIME(6) NOT NULL,
 completed_at DATETIME(6) NULL,
 FOREIGN KEY(result_identity_id) REFERENCES profiles_verified_identities(verified_identity_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY(result_credential_id) REFERENCES profiles_doctor_credentials(credential_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY(origin_id) REFERENCES auth_professional_source_requests(origin_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY(authorization_case_id) REFERENCES auth_governance_cases(case_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY(reviewer_account_id) REFERENCES internal_staff(account_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CHECK(state IN ('AUTHORIZED','COMPLETE','REVOKED')),
 CHECK((state='COMPLETE' AND result_json IS NOT NULL AND result_identity_id IS NOT NULL AND result_credential_id IS NOT NULL AND completed_at IS NOT NULL) OR state<>'COMPLETE')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE auth_professional_source_journal (
 event_id CHAR(36) NOT NULL PRIMARY KEY,
 origin_id CHAR(36) NOT NULL,
 operation_id CHAR(36) NULL,
 step VARCHAR(40) NOT NULL,
 audit_reference CHAR(36) NOT NULL,
 created_at DATETIME(6) NOT NULL,
 FOREIGN KEY(origin_id) REFERENCES auth_professional_source_requests(origin_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY(operation_id) REFERENCES auth_professional_source_operations(operation_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CHECK(step IN ('COLLECTION_OPENED','FILE_ADDED','AUTHORIZED','VID01_RECORDED','CRD02_RECORDED','COMPLETE','RECOVERY_AUTHORIZED','REVOKED','C4_REFERENCE_LINKED'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE auth_physician_evidence_files ADD COLUMN professional_source_file_id CHAR(36) NULL,
 ADD UNIQUE KEY uq_c4_source_reference(request_id,professional_source_file_id),
 ADD FOREIGN KEY(professional_source_file_id) REFERENCES auth_professional_source_files(file_id) ON DELETE RESTRICT ON UPDATE RESTRICT;
DELIMITER $$
CREATE TRIGGER source_files_no_update BEFORE UPDATE ON auth_professional_source_files FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='source_evidence_immutable'$$
CREATE TRIGGER source_files_no_delete BEFORE DELETE ON auth_professional_source_files FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='source_evidence_history_required'$$
CREATE TRIGGER source_journal_no_update BEFORE UPDATE ON auth_professional_source_journal FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='source_journal_immutable'$$
CREATE TRIGGER source_journal_no_delete BEFORE DELETE ON auth_professional_source_journal FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='source_journal_history_required'$$
CREATE TRIGGER source_request_binding_guard BEFORE UPDATE ON auth_professional_source_requests FOR EACH ROW
BEGIN
 IF NOT(OLD.origin_id<=>NEW.origin_id) OR NOT(OLD.account_id<=>NEW.account_id) OR NOT(OLD.profile_doctor_id<=>NEW.profile_doctor_id)
 OR NOT(OLD.intake_version<=>NEW.intake_version) OR NOT(OLD.collection_case_id<=>NEW.collection_case_id)
 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='source_request_binding_immutable'; END IF;
END$$
CREATE TRIGGER source_operation_frozen_guard BEFORE UPDATE ON auth_professional_source_operations FOR EACH ROW
BEGIN
 IF NOT(OLD.operation_id<=>NEW.operation_id) OR NOT(OLD.origin_id<=>NEW.origin_id) OR NOT(OLD.frozen_hash<=>NEW.frozen_hash)
 OR NOT(OLD.payload_ciphertext<=>NEW.payload_ciphertext) OR NOT(OLD.key_id<=>NEW.key_id)
 OR (OLD.state='COMPLETE' AND (NEW.state NOT IN ('COMPLETE','REVOKED') OR NOT(CAST(OLD.result_json AS CHAR)<=>CAST(NEW.result_json AS CHAR)) OR NOT(OLD.result_identity_id<=>NEW.result_identity_id) OR NOT(OLD.result_credential_id<=>NEW.result_credential_id) OR NOT(OLD.completed_at<=>NEW.completed_at)))
 OR (OLD.state='REVOKED' AND NEW.state<>'REVOKED')
 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='source_operation_frozen'; END IF;
END$$
CREATE TRIGGER source_c4_reference_guard BEFORE UPDATE ON auth_physician_evidence_files FOR EACH ROW
BEGIN
 IF NOT(OLD.professional_source_file_id<=>NEW.professional_source_file_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='source_c4_reference_immutable'; END IF;
END$$
DELIMITER ;
