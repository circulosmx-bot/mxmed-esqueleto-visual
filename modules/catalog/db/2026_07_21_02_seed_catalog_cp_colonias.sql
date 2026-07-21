INSERT INTO `catalog_cp_colonias` (`cp`, `colonia`, `municipio`, `estado`, `is_active`) VALUES
  ('20230', 'Colonia 1', 'Aguascalientes', 'Aguascalientes', 1),
  ('20230', 'Colonia 2', 'Aguascalientes', 'Aguascalientes', 1),
  ('20230', 'Colonia 3', 'Aguascalientes', 'Aguascalientes', 1)
ON DUPLICATE KEY UPDATE
  `municipio` = VALUES(`municipio`),
  `estado` = VALUES(`estado`),
  `is_active` = VALUES(`is_active`);
