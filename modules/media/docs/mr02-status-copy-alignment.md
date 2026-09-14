# MR02 follow-up — candidate status copy

Baseline: d556d42ae8632e2abefb049687125e93932fe86f;
design/physician-crd03-credentials-ui-v1; worktree initially clean and remote equal.

The thumbnail badge is rendered by owner-media-review.js from owner-review.php's
item-aware canonical DTO. profile-photo.js separately wrote the literal
“Pendiente de enviar” to #mxpi-photo-status after the shared upload completed.
This second renderer used neither batch nor item state. It contradicted a
successfully auto-submitted photo's “En revisión” badge.

app.js had the equivalent literal success message in #mx-dg-logo-feedback after
logo upload. Pending is valid immediately after a logo upload, but that duplicate
message could persist after later batch submission. Both upload success handlers
now clear their transient progress feedback and leave candidate state to the
existing canonical badge. Validation errors, upload errors and failure recovery
messages are preserved. No CSS hiding, profile special case, backend contract,
schema, auto-submit behavior or data authority changed. Script cache versions
are refreshed for the local review.

Read-only local audit: Leticia has an approved portrait and a PENDING_REVIEW photo
candidate with submitted_for_review_at=2026-09-13 22:38:33.296274. She has no logo
candidate; there is no live contradictory logo state to repair. The generic logo
renderer bug is fixed prospectively; legitimate unsent logo/gallery badges and
manual batch controls remain unchanged.

OwnerMediaReviewInlineBrowserTest.mjs intercepts media mutations and blocks
unexpected real writes. It exercises the actual photo and logo file-input
handlers, verifies empty duplicate success feedback, checks photo unsent/submitted,
logo unsent/submitted, mixed submitted-photo/unsent-logo and submitted-photo-only.
The matrix checks badge semantics, counters and send CTA. Existing review actions,
error recovery, target selection, public gallery and dirty/save isolation checks
also pass. Viewports: 1440x900, 1366x768, 390x844; no horizontal overflow and no
uncaught browser exceptions. Existing data snapshots match before/after.

QA command:
QA_OUTPUT=/tmp/mxmed-mr02-status-copy node modules/media/tests/OwnerMediaReviewInlineBrowserTest.mjs

Artifacts: /tmp/mxmed-mr02-status-copy/inline-report.json and screenshots in the
same folder. Review: http://127.0.0.1:18143/index.html?review=mr02-status-copy&qa_tools=hide

Files changed: assets/js/profile-photo.js, assets/js/app.js, index.html,
modules/media/tests/OwnerMediaReviewInlineBrowserTest.mjs, this document.
