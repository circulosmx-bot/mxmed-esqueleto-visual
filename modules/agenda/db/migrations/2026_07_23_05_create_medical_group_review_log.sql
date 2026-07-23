-- Declarative CUT01-C artifact. This migration is not executed by Activity 13.
CREATE TABLE IF NOT EXISTS medical_group_review_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    group_id VARCHAR(64) NOT NULL,
    action VARCHAR(64) NOT NULL,
    notes TEXT DEFAULT NULL,
    actor_user_id VARCHAR(64) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_medical_group_review_log_group_created (group_id, created_at),
    KEY idx_medical_group_review_log_action (action),
    CONSTRAINT fk_medical_group_review_log_group
      FOREIGN KEY (group_id) REFERENCES medical_groups(group_id)
      ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
