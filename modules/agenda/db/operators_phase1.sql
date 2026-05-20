-- Agenda · Operadores Backend Fase 1 (schema base)
-- Este script agrega almacenamiento mínimo para:
-- - operadores
-- - permisos de operador
-- - auditoría de acciones de operador

CREATE TABLE IF NOT EXISTS `agenda_operators` (
  `operator_id` VARCHAR(64) NOT NULL,
  `doctor_id` VARCHAR(64) NOT NULL,
  `operator_label` VARCHAR(64) DEFAULT NULL,
  `alias` VARCHAR(32) NOT NULL,
  `alias_normalized` VARCHAR(32) NOT NULL,
  `full_name` VARCHAR(160) NOT NULL,
  `phone` VARCHAR(32) DEFAULT NULL,
  `email` VARCHAR(190) DEFAULT NULL,
  `gender` VARCHAR(24) DEFAULT NULL,
  `role` VARCHAR(32) NOT NULL DEFAULT 'operator',
  `status` VARCHAR(24) NOT NULL DEFAULT 'pending',
  `login` VARCHAR(80) NOT NULL,
  `login_normalized` VARCHAR(80) NOT NULL,
  `temp_password_hash` VARCHAR(255) DEFAULT NULL,
  `force_password_change` TINYINT(1) NOT NULL DEFAULT 1,
  `invitation_status` VARCHAR(32) NOT NULL DEFAULT 'pending',
  `operator_credentials_sent_at` DATETIME DEFAULT NULL,
  `last_access` DATETIME DEFAULT NULL,
  `archived_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `alias_active_key` VARCHAR(32) GENERATED ALWAYS AS (
    CASE
      WHEN `status` <> 'archived' THEN `alias_normalized`
      ELSE NULL
    END
  ) STORED,
  `login_active_key` VARCHAR(80) GENERATED ALWAYS AS (
    CASE
      WHEN `status` <> 'archived' THEN `login_normalized`
      ELSE NULL
    END
  ) STORED,
  PRIMARY KEY (`operator_id`),
  KEY `idx_operators_doctor_status` (`doctor_id`, `status`),
  KEY `idx_operators_doctor_created` (`doctor_id`, `created_at`),
  UNIQUE KEY `uniq_operator_alias_active` (`doctor_id`, `alias_active_key`),
  UNIQUE KEY `uniq_operator_login_active` (`doctor_id`, `login_active_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `agenda_operator_permissions` (
  `permission_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `operator_id` VARCHAR(64) NOT NULL,
  `doctor_id` VARCHAR(64) NOT NULL,
  `permission_key` VARCHAR(64) NOT NULL,
  `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`permission_id`),
  UNIQUE KEY `uniq_operator_permission` (`operator_id`, `permission_key`),
  KEY `idx_operator_permissions_doctor` (`doctor_id`, `operator_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `agenda_operator_audit_events` (
  `event_id` VARCHAR(64) NOT NULL,
  `doctor_id` VARCHAR(64) NOT NULL,
  `operator_id` VARCHAR(64) NOT NULL,
  `event_type` VARCHAR(64) NOT NULL,
  `module_name` VARCHAR(64) NOT NULL DEFAULT 'Operadores',
  `action_label` VARCHAR(160) NOT NULL,
  `entity_label` VARCHAR(190) DEFAULT NULL,
  `actor_role` VARCHAR(32) DEFAULT NULL,
  `actor_id` VARCHAR(64) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`event_id`),
  KEY `idx_operator_audit_doctor_at` (`doctor_id`, `at`),
  KEY `idx_operator_audit_operator_at` (`operator_id`, `at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
