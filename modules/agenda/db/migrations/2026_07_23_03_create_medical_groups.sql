-- Declarative CUT01-C artifact. This migration is not executed by Activity 13.
CREATE TABLE IF NOT EXISTS medical_groups (
    group_id VARCHAR(64) NOT NULL,
    canonical_name VARCHAR(190) NOT NULL,
    display_name VARCHAR(190) NOT NULL,
    logo_url_original TEXT DEFAULT NULL,
    logo_url_approved TEXT DEFAULT NULL,
    status ENUM('pending','verified','rejected','merged') NOT NULL DEFAULT 'pending',
    source ENUM('user_submitted','operator_created','imported') NOT NULL DEFAULT 'user_submitted',
    created_by_user_id VARCHAR(64) DEFAULT NULL,
    reviewed_by_user_id VARCHAR(64) DEFAULT NULL,
    reviewed_at DATETIME DEFAULT NULL,
    rejection_reason TEXT DEFAULT NULL,
    merged_into_group_id VARCHAR(64) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (group_id),
    KEY idx_medical_groups_status (status),
    KEY idx_medical_groups_canonical_name (canonical_name),
    KEY idx_medical_groups_display_name (display_name),
    KEY idx_medical_groups_merged_into (merged_into_group_id),
    CONSTRAINT fk_medical_groups_merged_into
      FOREIGN KEY (merged_into_group_id) REFERENCES medical_groups(group_id)
      ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
