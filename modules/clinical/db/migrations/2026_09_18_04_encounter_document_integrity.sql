-- CLIN-REFORM-PHASE2-IMPL01A — review artifact only. DO NOT EXECUTE in this chapter.
-- No legacy document link is inferred or backfilled.

ALTER TABLE clinical_documents
  ADD COLUMN encounter_ref_id BIGINT UNSIGNED NULL AFTER encounter_id,
  ADD KEY idx_clinical_documents_encounter_ref (encounter_ref_id, event_datetime),
  ADD CONSTRAINT fk_clinical_documents_encounter_ref FOREIGN KEY (encounter_ref_id)
    REFERENCES clinical_encounters (encounter_id) ON UPDATE RESTRICT ON DELETE RESTRICT;

CREATE TABLE clinical_encounter_final_notes (
  encounter_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  document_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_encounter_final_note_document (document_id),
  CONSTRAINT fk_encounter_final_note_encounter FOREIGN KEY (encounter_id)
    REFERENCES clinical_encounters (encounter_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT fk_encounter_final_note_document FOREIGN KEY (document_id)
    REFERENCES clinical_documents (id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE clinical_document_revisions (
  revision_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  original_document_id BIGINT UNSIGNED NOT NULL,
  supersedes_document_id BIGINT UNSIGNED NULL,
  new_document_id BIGINT UNSIGNED NOT NULL,
  reason VARCHAR(1000) NOT NULL,
  author_user_id VARCHAR(64) NOT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_document_revision_new_document (new_document_id),
  KEY idx_document_revision_original (original_document_id, revision_id),
  CONSTRAINT chk_document_revision_reason_v1 CHECK (CHAR_LENGTH(TRIM(reason)) > 0),
  CONSTRAINT fk_document_revision_original FOREIGN KEY (original_document_id)
    REFERENCES clinical_documents (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT fk_document_revision_supersedes FOREIGN KEY (supersedes_document_id)
    REFERENCES clinical_documents (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT fk_document_revision_new FOREIGN KEY (new_document_id)
    REFERENCES clinical_documents (id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE clinical_idempotency_requests
  ADD CONSTRAINT fk_idempotency_document FOREIGN KEY (document_id)
    REFERENCES clinical_documents (id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  ADD CONSTRAINT fk_idempotency_document_revision FOREIGN KEY (document_revision_id)
    REFERENCES clinical_document_revisions (revision_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  ADD CONSTRAINT chk_idempotency_committed_result_v1 CHECK (
    (committed_at IS NULL AND observation_id IS NULL AND document_id IS NULL
      AND encounter_amendment_id IS NULL AND document_revision_id IS NULL)
    OR
    (committed_at IS NOT NULL AND (
      (operation_type = 'CREATE_OBSERVATION' AND observation_id IS NOT NULL
        AND document_id IS NULL AND encounter_amendment_id IS NULL AND document_revision_id IS NULL)
      OR
      (operation_type IN ('CREATE_ENCOUNTER_DOCUMENT','CREATE_POST_ENCOUNTER_RESULT')
        AND observation_id IS NULL AND document_id IS NOT NULL
        AND encounter_amendment_id IS NULL AND document_revision_id IS NULL)
      OR
      (operation_type = 'CREATE_ENCOUNTER_AMENDMENT' AND observation_id IS NULL
        AND document_id IS NULL AND encounter_amendment_id IS NOT NULL AND document_revision_id IS NULL)
      OR
      (operation_type = 'CREATE_DOCUMENT_AMENDMENT_OR_REPLACEMENT' AND observation_id IS NULL
        AND document_id IS NULL AND encounter_amendment_id IS NULL AND document_revision_id IS NOT NULL)
    ))
  );
