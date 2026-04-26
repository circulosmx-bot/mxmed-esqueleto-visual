CREATE TABLE IF NOT EXISTS `catalog_cp_colonias` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cp` VARCHAR(5) NOT NULL,
  `colonia` VARCHAR(190) NOT NULL,
  `municipio` VARCHAR(190) NOT NULL,
  `estado` VARCHAR(190) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_catalog_cp_colonia` (`cp`, `colonia`),
  KEY `idx_catalog_cp` (`cp`),
  KEY `idx_catalog_cp_active` (`cp`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
