-- Fuente canonica futura para contacto del medico.
-- No reemplaza consultorios.telefonos_json ni consultorios.whatsapp.
-- consultorio_id queda reservado para casos futuros explicitos; no debe usarse
-- inicialmente para duplicar telefonos/WhatsApp de sede.
-- Los contactos capturados son privados por defecto y solo se publican con
-- opt-in explicito, reglas de visibilidad, estado activo y gating de plan.
-- Los usos se modelan con flags (use_for_*) sobre un mismo contacto activo,
-- no duplicando filas por scope.
-- dp-correo y dp-whatsapp son legacy/localStorage y no se migran
-- automaticamente a esta tabla.
-- Rollback conceptual, no ejecutar sin aprobacion:
-- DROP TABLE IF EXISTS `doctor_contact_points`;

CREATE TABLE IF NOT EXISTS `doctor_contact_points` (
  `contact_point_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `doctor_id` VARCHAR(64) NOT NULL,
  `consultorio_id` VARCHAR(64) DEFAULT NULL,

  `type` VARCHAR(32) NOT NULL,
  `value` VARCHAR(255) NOT NULL,
  `normalized_value` VARCHAR(255) NOT NULL,
  `label` VARCHAR(120) DEFAULT NULL,

  `scope` VARCHAR(32) NOT NULL DEFAULT 'private',
  `is_public` TINYINT(1) NOT NULL DEFAULT 0,
  `is_verified` TINYINT(1) NOT NULL DEFAULT 0,
  `verification_status` VARCHAR(32) NOT NULL DEFAULT 'unverified',

  `use_for_security` TINYINT(1) NOT NULL DEFAULT 0,
  `use_for_platform_admin` TINYINT(1) NOT NULL DEFAULT 0,
  `use_for_public_profile` TINYINT(1) NOT NULL DEFAULT 0,
  `use_for_appointments` TINYINT(1) NOT NULL DEFAULT 0,

  `visibility_plan_min` VARCHAR(32) DEFAULT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'active',
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 100,

  `source` VARCHAR(64) NOT NULL DEFAULT 'manual',
  `metadata_json` JSON DEFAULT NULL,

  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,

  `active_normalized_value` VARCHAR(255)
    GENERATED ALWAYS AS (
      CASE
        WHEN `deleted_at` IS NULL THEN `normalized_value`
        ELSE NULL
      END
    ) STORED,

  PRIMARY KEY (`contact_point_id`),

  UNIQUE KEY `uniq_dcp_active_value`
    (`doctor_id`, `type`, `active_normalized_value`),

  KEY `idx_dcp_doctor_status_scope`
    (`doctor_id`, `status`, `scope`, `sort_order`),

  KEY `idx_dcp_public_candidates`
    (`doctor_id`, `use_for_public_profile`, `is_public`, `status`, `verification_status`),

  KEY `idx_dcp_consultorio`
    (`doctor_id`, `consultorio_id`),

  KEY `idx_dcp_deleted`
    (`doctor_id`, `deleted_at`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
