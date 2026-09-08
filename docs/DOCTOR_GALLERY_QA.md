# Canonical doctor gallery — 2026-09-08

Baseline: `44c934e3a3aa01602cff31a69fc073a0ace32f90`, branch
`program/mxmed-product-completion-v1`, clean, upstream equal, ahead/behind 0/0.

## Persistence authority

`media_assets` has UUID media_id, owner_type (PHYSICIAN/CONSULTORIO), owner_id,
purpose, PUBLIC classification, storage_key/public_url, WebP MIME/format,
dimensions/bytes/checksum, alt_text, READY/DELETED status and timestamps.
Owner/purpose/status are indexed. Existing purposes were an ENUM containing
only PHYSICIAN_PERSONAL_LOGO and CONSULTORIO_GROUP_LOGO, so the additive
`modules/media/db/migrations/2026_09_08_doctor_gallery.sql` adds DOCTOR_GALLERY.
Applied to local DB; deploy it before deploying the gallery API. No new table.

Gallery owner is PHYSICIAN + the canonical doctor ID from the authenticated
PHP session. The new endpoint does not use transitional identity headers,
query doctor IDs, or browser storage; it requires user and doctor session
identity, plus a session CSRF token for mutations. Existing logo routes remain
unchanged. A local QA page without an authenticated session cannot upload;
it displays a sign-in message rather than accepting a fabricated doctor ID.

`DoctorGalleryService` reuses `MediaAssetsRepository`, `GdPublicLogoProcessor`,
and `LocalPersistentPublicMediaStorage`. Storage remains
`MXMED_PUBLIC_MEDIA_ROOT`, default `$HOME/.local/share/mxmed/public-media`,
under `public/doctor-gallery/{owner-hash}/{uuid}.webp`. Public URLs use
`/api/media/index.php/public/{uuid}` with the existing checksum verification.
Delete marks the row DELETED and removes the stored object. List uses stable
created_at/media_id ordering; the previous UI had no manual ordering feature.

Validation: actual MIME/decode and extension consistency; 2 MB, 4096 px per
dimension, 4 megapixels. Existing GD normalization preserves JPEG orientation
and re-encodes without source metadata. Existing single optimized WebP variant
is reused: maximum edge 800 px, maximum 150 KB. No separate thumbnail variant
is introduced. Original user filenames never determine storage paths.
The current 21-image limit is serialized with a per-profile row lock.

Admin list/upload/delete use GET/POST/DELETE `/api/media/gallery.php`
(DELETE adds `?media_id=...`). `assets/js/fotos.js` renders the server response;
`mxmed_fotos` is left untouched, orphaned and non-canonical. No automatic import.

## Phase 1 QA

- DoctorGalleryHttpTest.mjs: PASS — real HTTP upload, canonical row/file,
  list/reload, second session same doctor, public read, cross-doctor delete 404,
  unauthenticated 401, missing CSRF 403, deletion and public 404.
- PublicLogoProcessorTest.php, PublicLogoPersistenceTest.php,
  PublicMediaStorageTest.php: PASS.
- PHP/JS syntax and diff checks: PASS.
- Test media and sessions are removed after QA. No existing media is changed.

## Existing public entitlement

`PublicProfilePlanCapabilities::show_gallery` is false for Free/Basic and true
from Standard upward. Preserve this rule; no new commercial restriction.
