ALTER TABLE media_review_files
    MODIFY COLUMN role ENUM('SOURCE','REVIEW','CORRECTED','AUTO_PROPOSAL','IMPROVEMENT_INPUT') NOT NULL,
    ADD CONSTRAINT chk_review_improvement_input CHECK (
      role<>'IMPROVEMENT_INPUT' OR
      (format='png' AND mime_type='image/png' AND width BETWEEN 1 AND 800
       AND height BETWEEN 1 AND 800 AND byte_size BETWEEN 1 AND 4194304));
