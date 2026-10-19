-- PANEL08: case-scoped portrait before Identity binding. No existing asset rewrite.
-- NULL remains immutable capture provenance; the exact case carries the later binding.
ALTER TABLE auth_staff_media_assets MODIFY account_id VARCHAR(64) NULL,
 ADD CONSTRAINT ck_staff_media_prebinding CHECK(account_id IS NOT NULL OR purpose='INTERNAL_PRESENTATION_PORTRAIT');
