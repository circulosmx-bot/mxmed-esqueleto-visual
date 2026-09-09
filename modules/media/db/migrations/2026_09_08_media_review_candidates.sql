CREATE TABLE media_review_submissions (
    submission_id CHAR(36) NOT NULL PRIMARY KEY,
    owner_type ENUM('PHYSICIAN') NOT NULL,
    owner_id VARCHAR(191) NOT NULL,
    purpose ENUM('DOCTOR_PROFILE_PHOTO','PHYSICIAN_PERSONAL_LOGO','DOCTOR_GALLERY') NOT NULL,
    technical_status ENUM('PROCESSING','READY','FAILED') NOT NULL,
    review_status ENUM('PENDING_REVIEW','APPROVED','NEEDS_WORK','REJECTED','WITHDRAWN') NOT NULL,
    active_photo_owner VARCHAR(191) GENERATED ALWAYS AS
      (CASE WHEN purpose='DOCTOR_PROFILE_PHOTO' AND review_status='PENDING_REVIEW'
       THEN owner_id ELSE NULL END) STORED,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_review_active_photo (owner_type, active_photo_owner),
    KEY idx_review_owner_pending (owner_type, owner_id, purpose, review_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE media_review_files (
    file_id CHAR(36) NOT NULL PRIMARY KEY,
    submission_id CHAR(36) NOT NULL,
    role ENUM('SOURCE','REVIEW','CORRECTED') NOT NULL,
    storage_key VARCHAR(512) NOT NULL,
    mime_type ENUM('image/jpeg','image/png','image/webp') NOT NULL,
    format ENUM('jpeg','png','webp') NOT NULL,
    width INT UNSIGNED NOT NULL,
    height INT UNSIGNED NOT NULL,
    byte_size INT UNSIGNED NOT NULL,
    checksum_sha256 CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_review_storage (storage_key),
    UNIQUE KEY uniq_review_file_role (submission_id, role),
    CONSTRAINT fk_review_file_submission FOREIGN KEY (submission_id)
      REFERENCES media_review_submissions(submission_id) ON DELETE CASCADE,
    CONSTRAINT chk_review_file_dimensions CHECK (width > 0 AND height > 0),
    CONSTRAINT chk_review_file_bytes CHECK (byte_size > 0),
    CONSTRAINT chk_review_file_checksum CHECK (checksum_sha256 REGEXP '^[0-9a-f]{64}$'),
    CONSTRAINT chk_review_preview CHECK (role <> 'REVIEW' OR
      (format='webp' AND mime_type='image/webp' AND width<=800 AND height<=800 AND byte_size<=153600))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
