-- ACT02: prepared only. No assignments, subscription backfill or implicit FREE default.
-- An independently reviewed mapping must supply all three fields together later.
ALTER TABLE profiles_doctors
 ADD COLUMN preloaded_free_plan VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NULL,
 ADD COLUMN preloaded_free_reference VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NULL,
 ADD COLUMN preloaded_free_recorded_at DATETIME(6) NULL,
 ADD CONSTRAINT ck_profile_explicit_preloaded_free CHECK (
  (preloaded_free_plan IS NULL AND preloaded_free_reference IS NULL AND preloaded_free_recorded_at IS NULL)
  OR (preloaded_free_plan IS NOT NULL AND preloaded_free_plan='free' AND preloaded_free_reference IS NOT NULL
      AND CHAR_LENGTH(preloaded_free_reference)>=8 AND preloaded_free_recorded_at IS NOT NULL));
