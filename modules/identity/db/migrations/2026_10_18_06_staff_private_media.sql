-- ASE-ADM02-IMP03. Requires IMP01/IMP02 on an explicitly authorized target.
-- No historical migration edits, case/profile writes, seeds or operational installation.
CREATE TABLE auth_staff_media_assets (
 asset_id VARCHAR(64) NOT NULL PRIMARY KEY,
 case_id VARCHAR(64) NOT NULL,
 account_id VARCHAR(64) NOT NULL,
 purpose VARCHAR(40) NOT NULL,
 asset_version INT UNSIGNED NOT NULL,
 previous_version INT UNSIGNED NULL,
 case_version INT UNSIGNED NOT NULL,
 storage_key VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 key_id VARCHAR(64) NOT NULL,
 cipher_algorithm VARCHAR(40) NOT NULL,
 ciphertext_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 ciphertext_bytes INT UNSIGNED NOT NULL,
 mime_type VARCHAR(32) NOT NULL,
 source_mime VARCHAR(32) NOT NULL,
 byte_length INT UNSIGNED NOT NULL,
 width INT UNSIGNED NOT NULL,
 height INT UNSIGNED NOT NULL,
 source_actor_account_id VARCHAR(64) NOT NULL,
 source_credential_version INT UNSIGNED NOT NULL,
 custody_reference VARCHAR(128) NOT NULL,
 retention_reference VARCHAR(128) NOT NULL,
 audit_event_id CHAR(36) NOT NULL,
 recorded_at DATETIME(6) NOT NULL,
 UNIQUE KEY uq_staff_media_version(case_id,purpose,asset_version),
 FOREIGN KEY(case_id,case_version) REFERENCES auth_staff_admission_versions(case_id,version),
 FOREIGN KEY(account_id) REFERENCES auth_accounts(account_id),
 FOREIGN KEY(source_actor_account_id) REFERENCES auth_accounts(account_id),
 FOREIGN KEY(case_id,purpose,previous_version) REFERENCES auth_staff_media_assets(case_id,purpose,asset_version),
 CONSTRAINT ck_staff_media_purpose CHECK(purpose IN ('INTERNAL_PRESENTATION_PORTRAIT','STAFF_IDENTITY_EVIDENCE')),
 CONSTRAINT ck_staff_media_version CHECK(asset_version>=1 AND ((asset_version=1 AND previous_version IS NULL) OR (previous_version IS NOT NULL AND previous_version=asset_version-1))),
 CONSTRAINT ck_staff_media_cipher CHECK(cipher_algorithm='XCHACHA20-POLY1305-v1' AND ciphertext_bytes=byte_length+40),
 CONSTRAINT ck_staff_media_bytes CHECK(byte_length>0 AND width>0 AND height>0 AND source_credential_version>=1),
 CONSTRAINT ck_staff_media_mime CHECK(source_mime IN ('image/jpeg','image/png') AND
  ((purpose='INTERNAL_PRESENTATION_PORTRAIT' AND mime_type='image/webp') OR (purpose='STAFF_IDENTITY_EVIDENCE' AND mime_type=source_mime)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE auth_staff_media_operations (
 actor_account_id VARCHAR(64) NOT NULL,
 operation_id CHAR(36) NOT NULL,
 case_id VARCHAR(64) NOT NULL,
 asset_id VARCHAR(64) NOT NULL UNIQUE,
 request_digest CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 audit_event_id CHAR(36) NOT NULL,
 recorded_at DATETIME(6) NOT NULL,
 PRIMARY KEY(actor_account_id,operation_id),
 FOREIGN KEY(actor_account_id) REFERENCES auth_accounts(account_id),
 FOREIGN KEY(case_id) REFERENCES auth_staff_admission_cases(case_id),
 FOREIGN KEY(asset_id) REFERENCES auth_staff_media_assets(asset_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Current/historical is derived from MAX(asset_version) per exact case/purpose, never rewrites history.
-- Retention/disposal of rows, bytes, backups and audit requires a later governed policy, not eternal retention.
DELIMITER $$
CREATE TRIGGER staff_media_asset_no_update BEFORE UPDATE ON auth_staff_media_assets FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='immutable_staff_media_asset'; END$$
CREATE TRIGGER staff_media_asset_no_delete BEFORE DELETE ON auth_staff_media_assets FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='immutable_staff_media_asset'; END$$
CREATE TRIGGER staff_media_operation_no_update BEFORE UPDATE ON auth_staff_media_operations FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='immutable_staff_media_operation'; END$$
CREATE TRIGGER staff_media_operation_no_delete BEFORE DELETE ON auth_staff_media_operations FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='immutable_staff_media_operation'; END$$
DELIMITER ;
