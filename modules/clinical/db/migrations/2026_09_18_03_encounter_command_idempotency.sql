-- CLIN-REFORM-PHASE2-IMPL01A — review artifact only. DO NOT EXECUTE in this chapter.
-- Idempotency rows are durable and contain hashes/references, never clinical payloads.

CREATE TABLE clinical_encounter_start_requests (
  request_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  doctor_id VARCHAR(64) NOT NULL,
  idempotency_key VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  canonicalization_version SMALLINT UNSIGNED NOT NULL,
  request_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  actor_user_id VARCHAR(64) NOT NULL,
  patient_id VARCHAR(64) NOT NULL,
  encounter_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  committed_at DATETIME NULL,
  UNIQUE KEY uq_encounter_start_idempotency (doctor_id, idempotency_key),
  KEY idx_encounter_start_result (encounter_id),
  CONSTRAINT fk_encounter_start_patient FOREIGN KEY (patient_id)
    REFERENCES patients_patients (patient_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT fk_encounter_start_result FOREIGN KEY (encounter_id)
    REFERENCES clinical_encounters (encounter_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT chk_encounter_start_commit_v1 CHECK (
    (committed_at IS NULL AND encounter_id IS NULL) OR (committed_at IS NOT NULL AND encounter_id IS NOT NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE clinical_idempotency_requests (
  request_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  operation_type VARCHAR(64) NOT NULL,
  doctor_id VARCHAR(64) NOT NULL,
  context_type VARCHAR(16) NOT NULL,
  context_id VARCHAR(128) NOT NULL,
  idempotency_key VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  canonicalization_version SMALLINT UNSIGNED NOT NULL,
  request_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  actor_user_id VARCHAR(64) NOT NULL,
  observation_id BIGINT UNSIGNED NULL,
  document_id BIGINT UNSIGNED NULL,
  encounter_amendment_id BIGINT UNSIGNED NULL,
  document_revision_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  committed_at DATETIME NULL,
  UNIQUE KEY uq_clinical_command_idempotency
    (operation_type, doctor_id, context_type, context_id, idempotency_key),
  CONSTRAINT chk_idempotency_context_v1 CHECK (context_type IN ('ENCOUNTER','PATIENT')),
  CONSTRAINT chk_idempotency_operation_v1 CHECK (operation_type IN
    ('CREATE_OBSERVATION','CREATE_ENCOUNTER_DOCUMENT','CREATE_POST_ENCOUNTER_RESULT',
     'CREATE_ENCOUNTER_AMENDMENT','CREATE_DOCUMENT_AMENDMENT_OR_REPLACEMENT')),
  CONSTRAINT fk_idempotency_observation FOREIGN KEY (observation_id)
    REFERENCES clinical_observations (observation_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT fk_idempotency_encounter_amendment FOREIGN KEY (encounter_amendment_id)
    REFERENCES clinical_encounter_amendments (amendment_id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- document_id and document_revision_id FKs/check are added by migration 04 after the lineage table exists.
