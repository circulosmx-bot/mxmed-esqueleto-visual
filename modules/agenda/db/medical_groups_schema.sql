-- Base persistente para grupos médicos (sin conexión UI todavía).
-- Incluye:
--   1) medical_groups
--   2) medical_group_memberships
--   3) medical_group_review_log
--   4) group_id nullable en consultorios (snapshot rápido)

CREATE TABLE IF NOT EXISTS `medical_groups` (
  `group_id` VARCHAR(64) NOT NULL,
  `canonical_name` VARCHAR(190) NOT NULL,
  `display_name` VARCHAR(190) NOT NULL,
  `logo_url_original` TEXT DEFAULT NULL,
  `logo_url_approved` TEXT DEFAULT NULL,
  `status` ENUM('pending','verified','rejected','merged') NOT NULL DEFAULT 'pending',
  `source` ENUM('user_submitted','operator_created','imported') NOT NULL DEFAULT 'user_submitted',
  `created_by_user_id` VARCHAR(64) DEFAULT NULL,
  `reviewed_by_user_id` VARCHAR(64) DEFAULT NULL,
  `reviewed_at` DATETIME DEFAULT NULL,
  `rejection_reason` TEXT DEFAULT NULL,
  `merged_into_group_id` VARCHAR(64) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`group_id`),
  KEY `idx_medical_groups_status` (`status`),
  KEY `idx_medical_groups_canonical_name` (`canonical_name`),
  KEY `idx_medical_groups_display_name` (`display_name`),
  KEY `idx_medical_groups_merged_into` (`merged_into_group_id`),
  CONSTRAINT `fk_medical_groups_merged_into`
    FOREIGN KEY (`merged_into_group_id`) REFERENCES `medical_groups` (`group_id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `medical_group_memberships` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `doctor_id` VARCHAR(64) NOT NULL,
  `consultorio_id` VARCHAR(64) NOT NULL,
  `group_id` VARCHAR(64) NOT NULL,
  `status` ENUM('pending','verified','rejected','unlinked') NOT NULL DEFAULT 'pending',
  `submitted_group_name` VARCHAR(190) DEFAULT NULL,
  `submitted_logo_url` TEXT DEFAULT NULL,
  `display_name_override` VARCHAR(190) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_medical_group_membership_scope` (`doctor_id`, `consultorio_id`, `group_id`),
  KEY `idx_medical_group_memberships_group_status` (`group_id`, `status`),
  KEY `idx_medical_group_memberships_doctor_consultorio` (`doctor_id`, `consultorio_id`),
  CONSTRAINT `fk_medical_group_memberships_group`
    FOREIGN KEY (`group_id`) REFERENCES `medical_groups` (`group_id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `medical_group_review_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_id` VARCHAR(64) NOT NULL,
  `action` VARCHAR(64) NOT NULL,
  `notes` TEXT DEFAULT NULL,
  `actor_user_id` VARCHAR(64) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_medical_group_review_log_group_created` (`group_id`, `created_at`),
  KEY `idx_medical_group_review_log_action` (`action`),
  CONSTRAINT `fk_medical_group_review_log_group`
    FOREIGN KEY (`group_id`) REFERENCES `medical_groups` (`group_id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- group_id en consultorios (nullable) para acceso rápido/snapshot.
SET @has_consultorios := (
  SELECT COUNT(*)
    FROM information_schema.tables
   WHERE table_schema = DATABASE()
     AND table_name = 'consultorios'
);
SET @has_group_id := (
  SELECT COUNT(*)
    FROM information_schema.columns
   WHERE table_schema = DATABASE()
     AND table_name = 'consultorios'
     AND column_name = 'group_id'
);
SET @sql_add_group_id := IF(
  @has_consultorios = 1 AND @has_group_id = 0,
  'ALTER TABLE consultorios ADD COLUMN group_id VARCHAR(64) NULL AFTER consultorio_id',
  'SELECT 1'
);
PREPARE stmt_add_group_id FROM @sql_add_group_id;
EXECUTE stmt_add_group_id;
DEALLOCATE PREPARE stmt_add_group_id;

SET @has_idx_group_id := (
  SELECT COUNT(*)
    FROM information_schema.statistics
   WHERE table_schema = DATABASE()
     AND table_name = 'consultorios'
     AND index_name = 'idx_consultorios_group_id'
);
SET @sql_add_idx_group_id := IF(
  @has_consultorios = 1 AND @has_idx_group_id = 0,
  'ALTER TABLE consultorios ADD KEY idx_consultorios_group_id (group_id)',
  'SELECT 1'
);
PREPARE stmt_add_idx_group_id FROM @sql_add_idx_group_id;
EXECUTE stmt_add_idx_group_id;
DEALLOCATE PREPARE stmt_add_idx_group_id;

