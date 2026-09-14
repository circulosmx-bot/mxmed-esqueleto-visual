# SIG03A — current physician signature

One doctor has at most one current signature. `physician_signatures.doctor_id` is the primary key and references `profiles_doctors`; `asset_id` and `storage_key` identify an immutable private PNG through the existing `LocalPersistentPrivateMediaStorage`. No profile base64 column or media-review purpose was added.

Apply `modules/signatures/db/2026_09_14_physician_signatures.sql` through the normal schema deployment path before activating the endpoint. It performs no backfill or row mutation. Applied locally on 2026-09-14: zero signature rows; Leticia's profile checksum unchanged. The existing canonical audit schema and MP01C restricted routines remain prerequisites; no runtime DDL or new grants.

GET/POST/DELETE `/api/media/physician-signature.php` resolve the established authenticated owner session, reject client scope/asset references, and use no-store responses. POST and DELETE require the session CSRF token. GET returns only the authenticated doctor's current PNG and timestamp plus an opaque owner marker and CSRF token, never storage keys. Anonymous/internal/conflicting sessions are denied.

PNG input is limited to 2 MiB, 4096 px per side and 4 MP before decode. Empty signatures, malformed payloads and invalid formats are rejected. Visible ink is cropped with padding, re-encoded without ancillary metadata, preserving alpha, with maximum output 1000×400 and 153600 bytes. Image contents are neither audit metadata nor error-log content.

The service locks the physician row, persists the new immutable file, updates the current reference and appends a joined canonical audit event in one database transaction. Failed validation/storage/audit preserves the old authority. Cleanup happens after successful commit; cleanup failure records only `signature_asset_cleanup_failed` and can leave an unreferenced private file for later operational cleanup. No asynchronous cleanup job is introduced. DELETE clears the reference and records the event before removing the old file. Concurrent owner mutations serialize on the physician row.

Audit events: PHYSICIAN_SIGNATURE_CREATED, PHYSICIAN_SIGNATURE_REPLACED and PHYSICIAN_SIGNATURE_DELETED. Reuses the shared canonical writer, policies, source-module and operation catalogs; metadata contains only current/previous asset identifiers.

## Consumer trace

| Consumer | Previous source | Current source | Migration |
|---|---|---|---|
| Consentimientos | registered-signature helper: browser/global image candidates | canonical client GET cache | completed |
| Informes | same helper | canonical client GET cache | completed |
| Notas | same helper | canonical client GET cache | completed |
| Altas | same helper | canonical client GET cache | completed |
| Interconsultas | same helper | canonical client GET cache | completed |
| Responsivas | same helper | canonical client GET cache | completed |
| Certificados | same helper | canonical client GET cache | completed |
| Recetas | line plus physician name/license in `ne-rx-read-signature`; no saved-image read | existing presentation preserved | no image consumer to migrate |

`assets/js/app.js` refreshes the seven registered previews when the canonical cache changes and refreshes the owner authority when a clinical document modal opens. Existing per-document captured/remote signature paths are not promoted to current physician authority. Historical `docs/assets/js/perfil/datos-generales.js` is not active product code: it wrote mxmed.signature and mxmed.signatureSession and generated a nonfunctional pseudo-QR. The active app's old unused writer wrote mxmed.signature and mxmed.doctor.signature; these paths are removed from the active implementation.

No legacy localStorage read fallback, no auto-promotion, no compatibility authority. Existing browser keys are untouched; the doctor must explicitly draw/save a new signature. GET failure clears the client cache. The signature has independent immediate Save/Delete and is excluded from grouped PATCH, dirty tracking and save tray. No QR, fullscreen, handoff or polling is implemented.

## QA

`MXMED_SIG03A_DISPOSABLE=1 MXMED_SIG03A_DISPOSABLE_PORT=<dedicated port> php modules/signatures/tests/PhysicianSignaturePhysicalTest.php` refuses an existing mxmed database, builds synthetic fixtures, then drops it. Covers migration idempotence, first save, independent instance read, normalization/alpha/metadata, malformed and oversized rejection, cross-owner mutations, audit-failed replacement preservation, successful replacement cleanup, delete and audit payload exclusion. Included HTTP tests use an isolated PHP root/session store with the disposable DB: anonymous denial, owner GET, CSRF, cross-doctor query/client identity, invalid reference, save, isolation, reload and delete.

`node modules/signatures/tests/PhysicianSignatureBrowserTest.mjs` uses disposable Chrome storage and mocked signature mutations; tests no legacy promotion, desktop 1440×900/1366×768, mobile 390×844, drawing/own save/delete, seven canonical document preview images, no overflow and no runtime exceptions. Screenshot output defaults to `/tmp/mxmed-sig03a-browser`. Live review: `http://127.0.0.1:18143/index.html?review=sig03a`; live GET only was exercised, no Leticia signature writes.

The approved uncommitted tab color/frame trial is preserved and excluded from this phase's commit. SIG03B remains unstarted.
