ALTER TABLE media_review_submissions
    MODIFY COLUMN created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    ADD COLUMN review_reason_code VARCHAR(32) NULL,
    ADD COLUMN review_feedback VARCHAR(400) NULL,
    ADD COLUMN review_decided_at DATETIME(6) NULL,
    ADD CONSTRAINT chk_review_reason_code CHECK (review_reason_code IS NULL OR review_reason_code IN
      ('WRONG_MEDIA_TYPE','QUALITY_INSUFFICIENT','CONTENT_NOT_APPROPRIATE','OTHER'));
