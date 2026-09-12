-- CRD02: trusted credential authority. No legacy backfill.
CREATE TABLE IF NOT EXISTS profiles_doctor_credentials (
 credential_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 doctor_id VARCHAR(64) NOT NULL,
 credential_type VARCHAR(16) NOT NULL,
 license_number VARCHAR(64) COLLATE utf8mb4_bin NOT NULL,
 professional_area_label VARCHAR(190) NULL,
 institution_name VARCHAR(190) NULL,
 verification_status VARCHAR(20) NOT NULL DEFAULT 'PENDING_REVIEW',
 lifecycle_status VARCHAR(16) NOT NULL DEFAULT 'ACTIVE',
 verified_at DATETIME(6) NULL,
 verified_by_account_id VARCHAR(64) NULL,
 source_type VARCHAR(32) NOT NULL,
 source_reference VARCHAR(190) NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 active_verified_professional VARCHAR(64) GENERATED ALWAYS AS
   (CASE WHEN credential_type='PROFESSIONAL' AND verification_status='VERIFIED' AND lifecycle_status='ACTIVE' THEN doctor_id ELSE NULL END) STORED,
 PRIMARY KEY (credential_id),
 UNIQUE KEY uq_credential_owner (doctor_id, credential_id),
 UNIQUE KEY uq_credential_license (doctor_id, license_number),
 UNIQUE KEY uq_active_verified_professional (active_verified_professional),
 CONSTRAINT fk_credential_doctor FOREIGN KEY (doctor_id) REFERENCES profiles_doctors(doctor_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT fk_credential_approver FOREIGN KEY (verified_by_account_id) REFERENCES internal_staff(account_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CONSTRAINT ck_credential_type CHECK (credential_type IN ('PROFESSIONAL','SPECIALTY')),
 CONSTRAINT ck_credential_verification CHECK (verification_status IN ('PENDING_REVIEW','VERIFIED','REJECTED')),
 CONSTRAINT ck_credential_lifecycle CHECK (lifecycle_status IN ('ACTIVE','INACTIVE','REVOKED')),
 CONSTRAINT ck_credential_license CHECK (CHAR_LENGTH(TRIM(license_number))>0),
 CONSTRAINT ck_credential_source CHECK (source_type IN ('admission_approved','internal_provisioning','governed_correction','synthetic_test')),
 CONSTRAINT ck_credential_reference CHECK (source_type='internal_provisioning' OR (source_reference IS NOT NULL AND CHAR_LENGTH(TRIM(source_reference))>0)),
 CONSTRAINT ck_credential_verified CHECK (verification_status<>'VERIFIED' OR
   (professional_area_label IS NOT NULL AND CHAR_LENGTH(TRIM(professional_area_label))>0 AND
    institution_name IS NOT NULL AND CHAR_LENGTH(TRIM(institution_name))>0 AND
    verified_at IS NOT NULL AND verified_by_account_id IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SET @crd02_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='profiles_doctors' AND column_name='primary_specialty_credential_id');
SET @crd02_sql := IF(@crd02_exists=0,'ALTER TABLE profiles_doctors ADD COLUMN primary_specialty_credential_id BIGINT UNSIGNED NULL','DO 0');
PREPARE crd02_stmt FROM @crd02_sql;
EXECUTE crd02_stmt;
DEALLOCATE PREPARE crd02_stmt;
SET @crd02_fk_exists := (SELECT COUNT(*) FROM information_schema.table_constraints WHERE constraint_schema=DATABASE() AND table_name='profiles_doctors' AND constraint_name='fk_profile_primary_credential_owner');
SET @crd02_sql := IF(@crd02_fk_exists=0,'ALTER TABLE profiles_doctors ADD CONSTRAINT fk_profile_primary_credential_owner FOREIGN KEY (doctor_id, primary_specialty_credential_id) REFERENCES profiles_doctor_credentials(doctor_id, credential_id) ON DELETE RESTRICT ON UPDATE RESTRICT','DO 0');
PREPARE crd02_stmt FROM @crd02_sql;
EXECUTE crd02_stmt;
DEALLOCATE PREPARE crd02_stmt;
