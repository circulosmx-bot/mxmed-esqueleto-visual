# SIG03B — secure signature device handoff

Baseline: `97f85f3bccc6c3ba25acf1d8c8ed8b6ade921488`, branch `design/physician-crd03-credentials-ui-v1`. Pre-worktree contains only the approved-to-trial tab color/frame changes; these remain uncommitted.

## Authority and security

`physician_signature_handoffs` is temporary delegation, not a second signature store. It binds a random session UUID and unique SHA-256 token hash to the authenticated creator account and doctor, purpose PHYSICIAN_SIGNATURE_HANDOFF, PENDING/COMPLETED state, creation/expiration/completion timestamps. Token is 32 random bytes, URL-safe base64 (256 bits), returned only in the creation response. TTL is exactly 300 seconds, never extended.

Creating a new handoff expires all previous live PENDING handoffs for the same doctor/purpose, including another owner account's handoff. Status requires the originating authenticated account and current doctor. No client doctor/account/asset reference is accepted. Creation and desktop cancellation require the session's X-Signature-Handoff-CSRF token; no development session fixture bypass. Ownership/active-context verification uses the same established server session scope as SIG03A, without introducing admission or account authorities.

The QR points to `/signature-handoff.php#<token>`. No identities or asset IDs are encoded. Fragment is never sent in the HTTP route or referrer; the device removes it from the visible URL and submits it only in a JSON body to `/api/media/signature-handoff-device.php`. Device requests omit account credentials. There is no generic login, cookie privilege elevation, Admin script or arbitrary profile operation. Invalid, expired, superseded and consumed tokens are denied identically. Validated signing controls stay hidden until the server accepts the token. The page uses no-store, no-referrer and a restrictive self-only CSP with no framing.

The existing QR approach elsewhere sends values to an external QR service; it was not reused for this secret. A pinned local QRCode.js encoder is vendored under assets/vendor/qrcodejs (MIT, upstream revision recorded there), with a white quiet zone. No token goes to an external QR service.

## Atomic save and races

Canonical current signature table, format, normalization, private storage, audit, replacement and consumers remain SIG03A's authority. Its existing save is delegated to saveAtomically, whose internal-only transaction hooks allow the handoff service to join the SAME transaction, not nest or independently commit.

All owner handoff mutations acquire the doctor row first. Completion derives doctor/account only from the token hash record, then the canonical service locks that doctor, rechecks/locks the live PENDING handoff, saves and audits the signature, marks COMPLETED conditionally before expiration, and audits completion before ONE commit. Failure anywhere rolls back both authorities and cleans the uncommitted new file. Old file cleanup stays after commit. A competing completion rechecks the now consumed token and cannot overwrite the winner. Existing signature is untouched on create, cancel, abandon, expiry or failed save.

Canonical audit events PHYSICIAN_SIGNATURE_HANDOFF_CREATED / COMPLETED / INVALIDATED use the shared writer, source and operation catalogs. Metadata contains only the opaque handoff ID; image and raw token are excluded. Supersession/desktop cancellation explicitly expire PENDING sessions with invalidation audit. Expiration is derived, without adding EXPIRED/CANCELLED statuses or repeated read-triggered audit events.

## Endpoints and UX

- GET `/api/media/signature-handoff.php`: initialize desktop CSRF.
- POST same endpoint with `{}`: authenticated owner creates session and gets opaque id, TTL and QR URL once.
- GET same endpoint `?id=<opaque UUID>`: authenticated creator sees only PENDING, COMPLETED or EXPIRED.
- DELETE same endpoint `{id}`: authenticated creator expires the pending session.
- GET `/signature-handoff.php`: isolated device shell; fragment probe validates token server-side before showing signing controls.
- POST `/api/media/signature-handoff-device.php` `{token}`: bearer validation; `{token,image_data}`: one-use canonical completion.

Desktop preserves direct canvas signing and adds “Firmar desde teléfono o tablet”. The Bootstrap modal has QR, concise instruction, expiry/waiting status and cancellation. Polling is 2500 ms, serial and bounded by the original five-minute deadline; completion, expiry, hiding, page exit and denial stop timers/abort the status request. Successful completion refreshes the existing canonical GET preview. No grouped save/dirty/save tray integration.

Device supports touch, stylus and mouse via Pointer Events, clear/cancel/save, and optional Fullscreen API from a user gesture. Unavailable/denied fullscreen uses a larger in-page canvas; orientation is not locked. Mobile cancel clears local canvas/token without server signature mutation; its session remains pending until the original TTL (or desktop cancellation/new-session invalidation).

## Deployment and cleanup

Migration: `modules/signatures/db/2026_09_14_signature_handoffs.sql`, no backfill. Applied to the local review DB with zero handoff rows, and unchanged checksums for Leticia’s profile, current signature and media. Normal deployment applies it before endpoint activation; no runtime DDL or grants. SIG03A table, shared audit tables/routines and private media storage remain prerequisites.

A bounded opportunistic cleanup on create removes at most 100 rows expired more than one day earlier, preserving canonical audit history and signatures. For idle deployments, include `DELETE FROM physician_signature_handoffs WHERE expires_at < DATE_SUB(UTC_TIMESTAMP(6), INTERVAL 1 DAY) LIMIT 100` in normal database maintenance and repeat bounded batches as needed; no infrastructure/job is introduced.

Set MXMED_SIGNATURE_HANDOFF_BASE_URL to the environment's approved HTTPS application origin/base path, reachable by desktop and recipient, with both endpoints sharing the SAME database/private storage. Unconfigured creation is allowed only on loopback HTTP for automated local review. It rejects an unconfigured non-loopback Host and non-loopback HTTP configuration. No forwarded-host trust or LAN HTTP security exception. Current runtime binds 127.0.0.1:18143: a phone's 127.0.0.1 points to the phone. Physical-device QA needs a LAN-reachable HTTPS development host or staging, not a loopback QR. No network exposure, DNS, AWS, staging deployment or production changes performed.

## QA

Physical fixture command: `MXMED_SIG03A_DISPOSABLE=1 MXMED_SIG03A_DISPOSABLE_PORT=<dedicated port> php modules/signatures/tests/SignatureHandoffPhysicalTest.php`. It refuses an existing mxmed database, builds only synthetic physicians, verifies SIG03A regressions plus TTL/hash-only, invalid/expired/superseded/replayed tokens, cross-owner create/status/mutation denial, existing-signature preservation, malformed save, audit-failed atomic completion, two concurrent worker completions with exactly one success/event, cancellation and cleanup. Isolated HTTP checks prove CSRF, bearer-only delegation, no Admin login, minimal status/route, canonical GET after completion and no token/image in logs/audit. Disposable DB/session/files are removed.

Browser QA: `node modules/signatures/tests/SignatureHandoffBrowserTest.mjs`, isolated Chrome profile with mocked signature/handoff mutations, no Leticia writes. Optional macOS real QR verification: compile DecodeHandoffQr.swift, then set MXMED_SIG03B_QR_DECODER to that binary; expected synthetic URL passes only through stdin, never logs. Browser checks desktop 1440/1366, device 390/tablet 820, polling stop/preview refresh, touch/stylus, clear/save/fullscreen fallback, overflow and exceptions. Screenshots and results use /tmp/mxmed-sig03b-browser.

Leticia's existing profile/media/signature are not used for mutation QA, and no browser legacy signature is promoted. Stop after Director review; no next phase initiated.

Observed final QA: physical service/HTTP/race/audit tests PASS; browser 1440×900, 1366×768, 390×844 and 820×1180 PASS; QR decoded locally at both desktop widths; actual Chrome Fullscreen API PASS; explicit denied-API fallback PASS; no overflow or JS exceptions. No physical phone/network test was attempted.
