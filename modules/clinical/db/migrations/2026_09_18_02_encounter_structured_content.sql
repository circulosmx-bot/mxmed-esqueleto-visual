-- CLIN-REFORM-PHASE2-IMPL01A — review artifact only. DO NOT EXECUTE in this chapter.
-- Additive encounter content. Historical children always use ON DELETE RESTRICT.

CREATE TABLE clinical_encounter_sections (
  section_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  encounter_id BIGINT UNSIGNED NOT NULL,
  section_type VARCHAR(40) NOT NULL,
  payload_schema_version SMALLINT UNSIGNED NOT NULL,
  payload_json JSON NOT NULL,
  narrative_text TEXT NULL,
  created_by_user_id VARCHAR(64) NOT NULL,
  updated_by_user_id VARCHAR(64) NOT NULL,
  row_version BIGINT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT chk_encounter_section_type_v1 CHECK (section_type IN
    ('reason_evolution','review_of_systems','physical_exam','assessment','plan','follow_up')),
  CONSTRAINT chk_encounter_section_version_v1 CHECK (payload_schema_version >= 1 AND row_version >= 1),
  UNIQUE KEY uq_encounter_section_concept (encounter_id, section_type),
  CONSTRAINT fk_encounter_sections_encounter FOREIGN KEY (encounter_id)
    REFERENCES clinical_encounters (encounter_id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE clinical_observations (
  observation_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  encounter_id BIGINT UNSIGNED NOT NULL,
  code VARCHAR(64) NOT NULL,
  value_numeric DECIMAL(18,6) NULL,
  value_text VARCHAR(1000) NULL,
  unit VARCHAR(32) NOT NULL,
  systolic_mm_hg DECIMAL(6,2) NULL,
  diastolic_mm_hg DECIMAL(6,2) NULL,
  effective_at DATETIME NOT NULL,
  recorded_at DATETIME NOT NULL,
  recorded_by_user_id VARCHAR(64) NOT NULL,
  source VARCHAR(32) NOT NULL,
  provenance_json JSON NOT NULL,
  row_version BIGINT UNSIGNED NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT chk_observation_version_v1 CHECK (row_version >= 1),
  CONSTRAINT chk_observation_source_v1 CHECK (source IN ('direct_measurement','patient_report','import')),
  CONSTRAINT chk_observation_bp_v1 CHECK (
    (code = 'blood_pressure' AND systolic_mm_hg > 0 AND diastolic_mm_hg > 0
      AND value_numeric IS NULL AND (value_text IS NULL OR TRIM(value_text) = '') AND unit = 'mmHg')
    OR
    (code <> 'blood_pressure' AND value_numeric IS NOT NULL
      AND systolic_mm_hg IS NULL AND diastolic_mm_hg IS NULL)
  ),
  KEY idx_observation_encounter_code_effective (encounter_id, code, effective_at),
  CONSTRAINT fk_observations_encounter FOREIGN KEY (encounter_id)
    REFERENCES clinical_encounters (encounter_id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE clinical_encounter_amendments (
  amendment_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  encounter_id BIGINT UNSIGNED NOT NULL,
  target_type VARCHAR(40) NOT NULL,
  target_id VARCHAR(128) NULL,
  target_field VARCHAR(128) NULL,
  reason VARCHAR(1000) NOT NULL,
  author_user_id VARCHAR(64) NOT NULL,
  amended_at DATETIME NOT NULL,
  correction_payload_json JSON NOT NULL,
  previous_effective_reference VARCHAR(255) NULL,
  CONSTRAINT chk_encounter_amendment_reason_v1 CHECK (CHAR_LENGTH(TRIM(reason)) > 0),
  KEY idx_encounter_amendment_history (encounter_id, amended_at, amendment_id),
  CONSTRAINT fk_encounter_amendments_encounter FOREIGN KEY (encounter_id)
    REFERENCES clinical_encounters (encounter_id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
