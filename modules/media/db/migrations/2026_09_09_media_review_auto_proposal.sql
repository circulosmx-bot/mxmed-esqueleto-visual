ALTER TABLE media_review_files
    MODIFY COLUMN role ENUM('SOURCE','REVIEW','CORRECTED','AUTO_PROPOSAL') NOT NULL,
    ADD COLUMN input_file_id CHAR(36) NULL,
    ADD COLUMN input_checksum_sha256 CHAR(64) NULL,
    ADD CONSTRAINT chk_auto_proposal CHECK (
      (role='AUTO_PROPOSAL' AND input_file_id IS NOT NULL AND input_checksum_sha256 IS NOT NULL
       AND input_checksum_sha256 REGEXP '^[0-9a-f]{64}$'
       AND format='webp' AND mime_type='image/webp' AND width<=800 AND height<=800 AND byte_size<=153600)
      OR (role<>'AUTO_PROPOSAL' AND input_file_id IS NULL AND input_checksum_sha256 IS NULL));
