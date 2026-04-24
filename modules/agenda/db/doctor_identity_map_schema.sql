-- Mapa de compatibilidad doctor_id legacy -> doctor_id canónico.
-- Fase 1: coexistencia segura sin migración destructiva.

CREATE TABLE IF NOT EXISTS `doctor_identity_map` (
  `legacy_doctor_id` VARCHAR(64) NOT NULL,
  `canonical_doctor_id` VARCHAR(64) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`legacy_doctor_id`),
  KEY `idx_doctor_identity_map_canonical` (`canonical_doctor_id`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
