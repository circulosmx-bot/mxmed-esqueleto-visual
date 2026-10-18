-- ASE-ADM02-IMP01. Fresh migration only; no backfill, staff writes or activation.
CREATE TABLE auth_staff_admission_cases (
 case_id VARCHAR(64) NOT NULL PRIMARY KEY,
 channel VARCHAR(32) NOT NULL,
 creator_account_id VARCHAR(64) NOT NULL,
 recipient_reference CHAR(36) NOT NULL,
 proposed_category VARCHAR(32) NOT NULL,
 state VARCHAR(32) NOT NULL DEFAULT 'PREPARING',
 version INT UNSIGNED NOT NULL DEFAULT 1,
 current_profile_version INT UNSIGNED NULL,
 bound_account_id VARCHAR(64) NULL,
 binding_credential_version INT UNSIGNED NULL,
 binding_reference VARCHAR(128) NULL,
 created_at DATETIME(6) NOT NULL,
 updated_at DATETIME(6) NOT NULL,
 active_recipient CHAR(36) GENERATED ALWAYS AS (CASE WHEN state <> 'REVOKED' THEN recipient_reference ELSE NULL END) STORED,
 UNIQUE KEY ux_staff_admission_recipient(active_recipient),
 FOREIGN KEY (creator_account_id) REFERENCES auth_accounts(account_id),
 FOREIGN KEY (bound_account_id) REFERENCES auth_accounts(account_id),
 CONSTRAINT ck_staff_admission_channel CHECK(channel IN ('ASSISTED','REMOTE_PREAUTHORIZED')),
 CONSTRAINT ck_staff_admission_category CHECK(proposed_category IN ('ADVISOR','MASTER_ADMIN')),
 CONSTRAINT ck_staff_admission_state CHECK(state IN ('PREPARING','PERSON_CONFIRMED','REVOKED')),
 CONSTRAINT ck_staff_admission_version CHECK(version>=1 AND (current_profile_version IS NULL OR current_profile_version=version)),
 CONSTRAINT ck_staff_admission_binding CHECK(
 (bound_account_id IS NULL AND binding_credential_version IS NULL AND binding_reference IS NULL)
 OR (bound_account_id IS NOT NULL AND binding_credential_version IS NOT NULL AND binding_credential_version>=1 AND binding_reference IS NOT NULL)),
 CONSTRAINT ck_staff_admission_confirmation CHECK(state<>'PERSON_CONFIRMED' OR bound_account_id IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE auth_staff_admission_versions (
 case_id VARCHAR(64) NOT NULL,
 version INT UNSIGNED NOT NULL,
 previous_version INT UNSIGNED NULL,
 given_names VARCHAR(120) NOT NULL,
 first_surname VARCHAR(120) NOT NULL,
 second_surname VARCHAR(120) NOT NULL,
 operation_state VARCHAR(120) NOT NULL,
 operation_city VARCHAR(120) NOT NULL,
 provenance VARCHAR(32) NOT NULL,
 source_actor_account_id VARCHAR(64) NOT NULL,
 source_credential_version INT UNSIGNED NOT NULL,
 recorded_at DATETIME(6) NOT NULL,
 PRIMARY KEY(case_id,version),
 FOREIGN KEY(case_id) REFERENCES auth_staff_admission_cases(case_id),
 FOREIGN KEY(case_id,previous_version) REFERENCES auth_staff_admission_versions(case_id,version),
 FOREIGN KEY(source_actor_account_id) REFERENCES auth_accounts(account_id),
 CONSTRAINT ck_staff_profile_version CHECK(version>=1 AND ((version=1 AND previous_version IS NULL) OR (previous_version IS NOT NULL AND previous_version=version-1))),
 CONSTRAINT ck_staff_profile_source CHECK(provenance IN ('ADMIN_PREPARED','APPLICANT_CORRECTED','APPLICANT_CONFIRMED','INTENTION_REVOKED')),
 CONSTRAINT ck_staff_profile_credential CHECK(source_credential_version>=1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE auth_staff_admission_cases ADD CONSTRAINT fk_staff_admission_current
 FOREIGN KEY(case_id,current_profile_version) REFERENCES auth_staff_admission_versions(case_id,version);
CREATE TABLE auth_staff_admission_operations (
 actor_account_id VARCHAR(64) NOT NULL,
 operation_id CHAR(36) NOT NULL,
 case_id VARCHAR(64) NOT NULL,
 resulting_version INT UNSIGNED NOT NULL,
 command VARCHAR(16) NOT NULL,
 request_digest CHAR(64) NOT NULL,
 audit_event_id CHAR(36) NOT NULL,
 recorded_at DATETIME(6) NOT NULL,
 PRIMARY KEY(actor_account_id,operation_id),
 UNIQUE KEY ux_staff_admission_version_result(case_id,resulting_version),
 FOREIGN KEY(actor_account_id) REFERENCES auth_accounts(account_id),
 FOREIGN KEY(case_id,resulting_version) REFERENCES auth_staff_admission_versions(case_id,version),
 CONSTRAINT ck_staff_admission_command CHECK(command IN ('prepare','update','correct','confirm','revoke'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Immutable history; disposal/retention requires a separately approved maintenance contract.
DELIMITER $$
CREATE TRIGGER staff_admission_versions_no_update BEFORE UPDATE ON auth_staff_admission_versions FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='immutable_staff_profile_version'; END$$
CREATE TRIGGER staff_admission_versions_no_delete BEFORE DELETE ON auth_staff_admission_versions FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='immutable_staff_profile_version'; END$$
CREATE TRIGGER staff_admission_operations_no_update BEFORE UPDATE ON auth_staff_admission_operations FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='immutable_staff_operation'; END$$
CREATE TRIGGER staff_admission_operations_no_delete BEFORE DELETE ON auth_staff_admission_operations FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='immutable_staff_operation'; END$$
DELIMITER ;
