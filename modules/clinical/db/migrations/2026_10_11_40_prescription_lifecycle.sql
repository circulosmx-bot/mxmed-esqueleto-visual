-- REC-IMP03. Original document content and signature remain immutable.
CREATE TABLE IF NOT EXISTS clinical_prescription_lifecycle (
  original_document_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  replacement_document_id BIGINT UNSIGNED NULL,
  relation_type ENUM('simple_void','corrected_reissue') NOT NULL,
  lifecycle_state ENUM('pending','completed') NOT NULL,
  reason_code VARCHAR(64) NOT NULL,
  reason_detail VARCHAR(1000) NULL,
  explanatory_note VARCHAR(1000) NULL,
  actor_user_id VARCHAR(128) NOT NULL,
  created_at DATETIME NOT NULL,
  voided_at DATETIME NULL,
  previous_status VARCHAR(32) NOT NULL,
  UNIQUE KEY uq_prescription_replacement (replacement_document_id),
  CONSTRAINT fk_prescription_lifecycle_original FOREIGN KEY (original_document_id)
    REFERENCES clinical_documents(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_prescription_lifecycle_replacement FOREIGN KEY (replacement_document_id)
    REFERENCES clinical_documents(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT chk_prescription_lifecycle_shape CHECK (
    (relation_type='simple_void' AND replacement_document_id IS NULL AND lifecycle_state='completed' AND voided_at IS NOT NULL)
    OR (relation_type='corrected_reissue' AND replacement_document_id IS NOT NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
