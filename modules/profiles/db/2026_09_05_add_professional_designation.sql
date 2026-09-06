-- Explicit optional display value; never derived from specialty or gender.
SET @mxmed_designation_exists := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'profiles_doctors'
    AND column_name = 'professional_designation'
);
SET @mxmed_designation_sql := IF(
  @mxmed_designation_exists = 0,
  'ALTER TABLE `profiles_doctors` ADD COLUMN `professional_designation` VARCHAR(120) NULL AFTER `display_name`',
  'SELECT 1'
);
PREPARE mxmed_designation_migration FROM @mxmed_designation_sql;
EXECUTE mxmed_designation_migration;
DEALLOCATE PREPARE mxmed_designation_migration;
