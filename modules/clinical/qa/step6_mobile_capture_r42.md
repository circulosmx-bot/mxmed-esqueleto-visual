# Step 6 mobile capture R4.2 — transport alignment

## Source / failure audit

Starting clean local and remote HEAD: `107b22c13b76f45a16e15236962424997e360c33`.

The actual Director HTTP runtime reported `upload_max_filesize=2M`, `post_max_size=8M`, `memory_limit=128M`, `max_file_uploads=20`. A disposable reproduction before edits showed:

- 3 MB multipart file: HTTP 400, `meta.upload_error=1` (`UPLOAD_ERR_INI_SIZE`), “archivo demasiado grande para el límite de carga”.
- 9 MB multipart request: PHP discarded `$_FILES` and `$_POST` before routing. The old endpoint misreported missing file (`upload_error=4`); PHP also printed a warning before the JSON body.

The original failed phone image was not supplied, so its original name, MIME, byte length and pixel dimensions cannot be reconstructed from this evidence. A review-only external router observer records the transmitted file metadata on a subsequent physical upload without retaining extra image copies or bearer tokens.

## Authority preserved

`ClinicalPrivateBinaryStorage::MAX_BYTES` remains **26,214,400 bytes (25 MB)**. Supported images remain JPEG/PNG/WebP; canonical PDF support is unchanged. Token ownership, TTL, single-use transaction, cancellation, expiry and classification storage are unchanged.

**Audit discrepancy:** the current encounter-scoped C21 writer calls `clinical_note_capture_multipart_execute` → `clinical_encounter_multipart_execute` → `ClinicalMultipartDocumentService`. It stages and persists immutable `ORIGINAL` bytes. It does **not** call `clinical_optimize_uploaded_image`. That existing legacy helper checks dimensions ≤10000 per side, fixes orientation, resizes to 2048, and creates a 480px thumbnail, but it is not the accepted canonical C21 route. R4.2 does not substitute the legacy writer or introduce a new image transformation contract. The phone's existing best-effort client optimization is unchanged and is not required for transport acceptance.

Therefore near-limit QA proves canonical acceptance/persistence, not server-side GD optimization. “Optimization runs” cannot be marked PASS for this writer. This is an existing authority difference, separate from the PHP size failure.

## Changes

- Mobile endpoint checks the 25 MB product limit before MIME processing; PHP size errors and absent files caused by `post_max_size` return `UPLOAD_TOO_LARGE` with the requested 25 MB message.
- Distinct `UPLOAD_PARTIAL`, `UPLOAD_NO_FILE`, `UPLOAD_NO_TMP_DIR`, `UPLOAD_CANT_WRITE`, and `UPLOAD_EXTENSION_BLOCKED` codes/messages retain actionable causes. Existing HTTP 400 behavior and response envelope remain; error codes/diagnostics are additive refinements. Token validation still runs first.
- Phone copy shows “Tamaño máximo: 25 MB”; `File.size > 25 MB` prevents a transfer with an early warning. Server validation remains authoritative.
- No product schema, canonical writer, storage, security, accepted MIME, dimension authority, Plan, or Step 7 changes.

## Local Director runtime

Keep its existing session, DB, private-storage, cohort and write-window environment. Preserve `MXMED_CAPTURE_PUBLIC_ORIGIN=http://192.168.1.6:18148` and its restricted LAN phone router. Start with:

```sh
/opt/homebrew/bin/php \
  -d upload_max_filesize=32M \
  -d post_max_size=40M \
  -d display_errors=Off \
  -d session.save_path=/Users/circulodigital/.codex/artifacts/director-expediente-review/sessions \
  -S 0.0.0.0:18148 \
  -t /Users/circulodigital/Documents/GitHub/mxmed-step2-vitals-r1 \
  /Users/circulodigital/.codex/artifacts/director-expediente-review/router-step2-r1.php
```

`display_errors=Off` prevents PHP's pre-routing multipart warning from corrupting JSON; server logging is preserved. Memory remains **128M** unless measured evidence requires a change; do not add a 512M override merely because GD exists in a different route. Exact restart orchestration preserves the current environment and is recorded at `/tmp/mxmed-step6-r42-refresh.py`.

## QA

```sh
php modules/clinical/qa/step6_mobile_capture_r42_errors_test.php
bash modules/clinical/qa/step6_mobile_capture_r42_disposable_gate.sh
FLOW_R1_CAPTURE_R42=1 bash modules/clinical/qa/step6_mobile_capture_r4_disposable_gate.sh
```

Stress tests use randomly named disposable `flow_r1_qa_*` databases and isolated private storage, dropped afterward. Images contain generated pixels only. They exercise a normal 2.75 MB PNG, a valid 23.4 MB PNG, 26 MB product rejection, 33 MB PHP upload rejection and 41 MB PHP post rejection; zero documents for rejected files and no consumed token. Raw-byte hash comparison confirms the existing ORIGINAL persistence contract. WebKit 390×844 verifies copy, early warning/no request and a normal image upload above the old 2M transport limit. Physical-phone confirmation must come from the authorized Director review encounter, not from automated WebKit evidence.

Artifacts: `/Users/circulodigital/.codex/artifacts/step6-mobile-capture-r42/`.

## Production audit

No production deployment was contacted or changed. Actual production `upload_max_filesize`, `post_max_size`, and `memory_limit`: **UNKNOWN_NOT_CHANGED**. The repository's `infra/aws/runtime/app/php/zz-mxmed-production.ini` declares `memory_limit=256M` but no upload/post limits; the effective deployed base-image/proxy limits are not established. `api/media/.user.ini` (10M/12M/256M) is scoped to profile media, not the clinical endpoint. Deployment must allow the canonical 25 MB file plus multipart overhead through PHP and any upstream proxy. No production limit was silently changed.
