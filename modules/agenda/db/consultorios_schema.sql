-- Persistencia base de datos para datos generales de Consultorio (no horarios).
-- Horarios viven en consultorio_schedule.

CREATE TABLE IF NOT EXISTS `consultorios` (
  `doctor_id` VARCHAR(64) NOT NULL,
  `consultorio_id` VARCHAR(64) NOT NULL,
  `group_id` VARCHAR(64) DEFAULT NULL,
  `titulo` VARCHAR(190) DEFAULT NULL,
  `grupo_nombre` VARCHAR(190) DEFAULT NULL,
  `calle` VARCHAR(190) DEFAULT NULL,
  `num_ext` VARCHAR(32) DEFAULT NULL,
  `num_int` VARCHAR(32) DEFAULT NULL,
  `cp` VARCHAR(16) DEFAULT NULL,
  `colonia` VARCHAR(120) DEFAULT NULL,
  `municipio` VARCHAR(120) DEFAULT NULL,
  `estado` VARCHAR(120) DEFAULT NULL,
  `telefonos_json` JSON DEFAULT NULL,
  `whatsapp` VARCHAR(32) DEFAULT NULL,
  `urgencias_json` JSON DEFAULT NULL,
  `logo_url` LONGTEXT DEFAULT NULL,
  `foto_url` LONGTEXT DEFAULT NULL,
  `lat` DECIMAL(10,7) DEFAULT NULL,
  `lng` DECIMAL(10,7) DEFAULT NULL,
  `geocode_source` VARCHAR(32) DEFAULT NULL,
  `geocode_updated_at` DATETIME DEFAULT NULL,
  `document_is_primary` TINYINT(1) NOT NULL DEFAULT 0,
  `document_display_order` SMALLINT UNSIGNED DEFAULT NULL,
  `document_publication_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `document_primary_slot` TINYINT GENERATED ALWAYS AS (
    CASE WHEN `document_is_primary` = 1 THEN 1 ELSE NULL END
  ) STORED,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `chk_consultorios_document_is_primary` CHECK (`document_is_primary` IN (0, 1)),
  CONSTRAINT `chk_consultorios_document_publication` CHECK (`document_publication_enabled` IN (0, 1)),
  CONSTRAINT `chk_consultorios_document_display_order` CHECK (`document_display_order` IS NULL OR `document_display_order` > 0),
  PRIMARY KEY (`doctor_id`, `consultorio_id`),
  UNIQUE KEY `uq_consultorios_document_primary` (`doctor_id`, `document_primary_slot`),
  UNIQUE KEY `uq_consultorios_document_order` (`doctor_id`, `document_display_order`),
  KEY `idx_consultorios_doctor` (`doctor_id`),
  KEY `idx_consultorios_group_id` (`group_id`),
  KEY `idx_consultorios_document_footer` (`doctor_id`, `document_publication_enabled`, `document_is_primary`, `document_display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
