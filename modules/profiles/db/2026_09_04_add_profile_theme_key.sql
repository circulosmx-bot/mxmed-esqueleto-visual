-- THEME-01A: persist only an approved catalog key; application validation owns the allowlist.
SET @mxmed_theme_column_exists := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'profiles_doctors'
    AND column_name = 'profile_theme_key'
);
SET @mxmed_theme_migration_sql := IF(
  @mxmed_theme_column_exists = 0,
  'ALTER TABLE `profiles_doctors` ADD COLUMN `profile_theme_key` VARCHAR(64) NULL AFTER `logo_url`',
  'SELECT 1'
);
PREPARE mxmed_theme_migration FROM @mxmed_theme_migration_sql;
EXECUTE mxmed_theme_migration;
DEALLOCATE PREPARE mxmed_theme_migration;
