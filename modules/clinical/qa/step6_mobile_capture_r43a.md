# Step 6 R4.3A — classified multipage capture

## Authority

Migration `2026_09_29_12_mobile_capture_sessions.sql` adds only sessions and pages.
Existing DOCSEC01 tokens, clinical documents, binary manifests and media bundles are
unchanged. The page's unique token reference is application checked in the same
transaction as issuance (the legacy token table is runtime provisioned).

Classification uses existing clinical activity media categories in `media_tag_key` /
`media_tag_label`: documento_externo, evidencia_clinica, receta_previa,
consentimiento_formato, bitacora_hospitalaria, otro. Study results are excluded.
Classification and optional title are physician selected and immutable for a session.
PDF continues through the existing single-file document authority.

## Phone continuation

The QR contains one existing 128-bit page token. It accepts one upload only.
Successful upload returns a separate opaque 256-bit continuation; only its SHA-256
is stored in the session. The phone retains it in sessionStorage, sends it in
`X-Capture-Continuation`, and never receives physician authentication. It is scoped
only to that session's minimal page review, thumbnail reads, issuance, reorder,
remove and finalization. It cannot create sessions, cancel them, change metadata,
read patient identity or access arbitrary documents. It expires with the session.
A phone already holding valid continuation retains it across further uploads; a new
phone authorized by another desktop-issued page QR replaces that continuation.
A lost first upload response requires a new physician-issued QR; a consumed upload
bearer never becomes a reusable continuation endpoint.

Session TTL: one hour. Each page token: up to 15 minutes, bounded by session TTL.
Maximum active pages: 30. Expired pending page slots are retired on next issuance.
There may be distinct concurrently valid page authorizations; each is single use.

## Transactions and storage

Mutation lock order: session, page/token, encounter. Session serializes upload,
reorder, remove, expiry, cancel and finalization. The existing token mutex is not
acquired by new session operations. Finalization is idempotent under session lock:
OPEN → FINALIZING → COMPLETED, with all page documents/manifests in one transaction.
READY page order is explicit; reorder validates the complete READY set, then assigns
contiguous positions, with any pending authorizations after READY pages.

The shared `clinical_optimize_uploaded_image` transformation runs immediately:
MIME/25MB/dimension validation, JPEG EXIF orientation, main max 2048px, thumb max
480px, re-encoding without EXIF/IPTC/XMP. Main WebP quality 80, thumb 75; JPEG fallback
uses the same qualities, PNG alpha/fallback uses compression 6. Private transient
outputs are staged through the existing private-storage primitives. Raw input is
never staged; audit stores only byte count, dimensions, MIME and SHA-256. Normal
canonical image creation/replacement uses this same optimizer; PDFs are unchanged.

Each final page is `document_type=image`, with ORIGINAL holding the accepted
normalized image and THUMBNAIL holding the preview. No raw original is retained.
All pages share a server-generated media_bundle_id/title and carry server-derived
page number/count, capture_session_uuid and capture_method=mobile.
The existing bundle viewer reads private ORIGINAL and orders by media_page_number.
Step 6 groups these rows only in its display projection and counts the bundle once.

A precommit failure rolls back all clinical rows and leaves staged pages retryable.
Created immutable final objects follow the existing quarantine recovery semantics.
An ambiguous commit never deletes potentially committed files. Postcommit staged
cleanup is retried opportunistically on session access. Cancel/expiry revoke pending
tokens, remove draft page associations and clean both staged variants. No worker.

## Rehearsal

Run `FLOW_R1_ARTIFACTS=/absolute/evidence/path bash
modules/clinical/qa/step6_mobile_capture_r43a_gate.sh`.
The gate creates/destroys a randomly named disposable DB and private store; four PHP
workers exercise real concurrency. Director serves frontend fixtures read-only;
all frontend clinical mutations are routed to the disposable backend.

Checks cover two migration applications, authorization/scope, immutable metadata,
replay, per-page concurrency, finalization concurrency/race/rollback, removal,
cancel/expiry cleanup, 23.4MB upload optimization, EXIF orientation, legacy capture,
PDF byte preservation, actual WebKit phone flow, QR decoding, existing bundle
viewer, logical document count, cancellation failure/retry, focus and main-panel
scroll at 1440×900, 1366×768, 1440×810. Synthetic printed text retains all 36 lines
under Vision OCR at the accepted 2048px policy.

Physical three-page capture, reorder 3,1,2 and photographed-text readability require
Director phone confirmation and persisted server evidence; browser simulation does
not substitute for this acceptance. Runtime remains restricted to the authorized
Director review database; working MXMED database is never connected.
