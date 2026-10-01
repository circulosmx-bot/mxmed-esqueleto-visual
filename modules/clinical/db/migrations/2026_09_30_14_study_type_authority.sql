-- TAX03A. Additive global study taxonomy; historical document payloads are untouched.
CREATE TABLE IF NOT EXISTS clinical_study_types (
  study_type_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  study_type_key VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  display_name_es VARCHAR(255) NOT NULL,
  category_key VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  aliases_json JSON NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_study_type_key (study_type_key),
  KEY idx_study_type_category_active (category_key,is_active),
  CONSTRAINT ck_study_type_key CHECK (study_type_key REGEXP '^[a-z][a-z0-9_]{1,99}$'),
  CONSTRAINT ck_study_type_name CHECK (CHAR_LENGTH(TRIM(display_name_es))>0),
  CONSTRAINT ck_study_type_category CHECK (category_key IN ('LABORATORIO','IMAGEN','CARDIOVASCULAR','OFTALMOLOGIA','NEUROFISIOLOGIA','FUNCION_PULMONAR','AUDIOLOGIA','DENTAL','PATOLOGIA','ENDOSCOPIA','SUENO','OTROS')),
  CONSTRAINT ck_study_type_aliases CHECK (JSON_TYPE(aliases_json)='ARRAY'),
  CONSTRAINT ck_study_type_active CHECK (is_active IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clinical_study_type_external_codes (
  mapping_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  study_type_id BIGINT UNSIGNED NOT NULL,
  code_system VARCHAR(190) NOT NULL,
  code VARCHAR(128) NOT NULL,
  code_version VARCHAR(80) NULL,
  code_version_norm VARCHAR(80) GENERATED ALWAYS AS (COALESCE(code_version,'')) STORED,
  display VARCHAR(255) NULL,
  provenance VARCHAR(255) NOT NULL,
  verified_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_study_external_mapping (study_type_id,code_system,code,code_version_norm),
  KEY idx_study_external_lookup (code_system,code),
  CONSTRAINT fk_study_external_type FOREIGN KEY (study_type_id) REFERENCES clinical_study_types(study_type_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT ck_study_external_code CHECK (CHAR_LENGTH(TRIM(code_system))>0 AND CHAR_LENGTH(TRIM(code))>0 AND CHAR_LENGTH(TRIM(provenance))>0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- An earlier local rehearsal used an empty-string sentinel for code_version.
-- Upgrade that shape in place, then retain NULL for the optional version.
SET @tax03a_needs_nullable_version := (
  SELECT COUNT(*)=0 FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_study_type_external_codes' AND COLUMN_NAME='code_version_norm'
);
SET @tax03a_upgrade_sql := IF(@tax03a_needs_nullable_version,
  'ALTER TABLE clinical_study_type_external_codes DROP INDEX uq_study_external_mapping, MODIFY code_version VARCHAR(80) NULL, ADD COLUMN code_version_norm VARCHAR(80) GENERATED ALWAYS AS (COALESCE(code_version,'''') ) STORED, ADD UNIQUE KEY uq_study_external_mapping (study_type_id,code_system,code,code_version_norm)',
  'DO 0');
PREPARE tax03a_upgrade FROM @tax03a_upgrade_sql;
EXECUTE tax03a_upgrade;
DEALLOCATE PREPARE tax03a_upgrade;

DELIMITER $$
DROP TRIGGER IF EXISTS trg_study_type_identity_immutable$$
CREATE TRIGGER trg_study_type_identity_immutable BEFORE UPDATE ON clinical_study_types FOR EACH ROW
BEGIN
  IF NOT (NEW.study_type_id <=> OLD.study_type_id) OR NOT (NEW.study_type_key <=> OLD.study_type_key) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='STUDY_TYPE_IDENTITY_IMMUTABLE';
  END IF;
END$$
DROP TRIGGER IF EXISTS trg_study_type_no_delete$$
CREATE TRIGGER trg_study_type_no_delete BEFORE DELETE ON clinical_study_types FOR EACH ROW
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='STUDY_TYPE_DELETE_FORBIDDEN'$$
DELIMITER ;
