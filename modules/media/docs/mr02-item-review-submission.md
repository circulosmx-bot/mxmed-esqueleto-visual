# MR02 — individual review submission

Baseline: `cc12081b37e316149ab206df6376c7a406d09471` on
`design/physician-crd03-credentials-ui-v1`; initially clean and equal to remote.

## Authority and schema

Canonical item: `media_review_submissions`, PK `submission_id`; membership FK
`(batch_id, owner_type, owner_id)` references `media_review_batches`.
Existing technical states remain PROCESSING/READY/FAILED; review decisions remain
PENDING_REVIEW/APPROVED/NEEDS_WORK/REJECTED/WITHDRAWN. Existing item timestamps are
created_at, updated_at and review_decided_at, with reason/feedback for replacement.
None was an individual submission instant.

The only new field is nullable `submitted_for_review_at DATETIME(6)`. For new
candidates NULL means unsent; non-NULL means submitted independently of the review
decision. The batch/unsent index supports the remaining-send query. Actor identity
is recorded in canonical audit, so no duplicate actor column or submitted boolean
is needed.

Migration: `2026_09_13_media_review_item_submission.sql`. Apply once, after MR11,
with media writes and the inactivity executor paused. This follows the existing
apply-once DDL convention: duplicate-column failure is not silently ignored.
Backfill itself is NULL-guarded and repeatable. Use schema readiness before
serving new source; do not run DDL in request handlers. The runtime package includes
the new endpoint and services; database migration remains a separate deployment step.

## Historical compatibility

Closed batch membership is immutable. READY/PENDING_REVIEW members of a SUBMITTED
batch can inherit its actual `submitted_at`. The migration changes neither
updated_at nor decision history. It does not infer submission dates for withdrawn
or decided members, or for unbatched pre-MR11 records. No created_at/updated_at or
current date is substituted as historical submission time; no audit is fabricated
for backfill.

`MediaSubmissionState` centralizes compatibility: existing unbatched history and
closed-batch history remain reviewable even when individual dates are unknown.
The NULL=unsent rule applies to new, batched candidates. No new candidate may use
NULL membership to enter the reviewer queue. The service rejects attempts to
submit unbatched legacy records. Existing decision labels take precedence.

## Operations and transactions

`MediaCandidateSubmissionService::submit(id, actor)` consumes a
`MediaSubmissionActor` created from the established server session scope,
including user and physician IDs. Request data cannot choose a physician or actor.
The operation locks the physician, checks owner/purpose/READY/PENDING_REVIEW,
checks OPEN batch ownership, writes one item timestamp and one canonical audit
within the same PDO transaction. A pending submitted item is an idempotent success
without another timestamp/event. Terminal or ineligible records conflict.
Cross-owner and missing IDs share 404; invalid input is 400.

POST `/api/media/review-candidate-submit.php` accepts exactly JSON `csrf` and
`submission_id`. It uses the existing batch CSRF token issued by authenticated
GET `/api/media/review-batch-submit.php`. Session absence is 401, CSRF failure 403,
state conflict 409, infrastructure failure 503. Unexpected fields are rejected.

Canonical `MEDIA_REVIEW_CANDIDATE_SUBMITTED` records actor, physician, candidate,
purpose, batch, transition, submission instant and mode. It uses the existing
MediaReviewAudit writer, policy registry and joined transaction adapter. Owner
requests use USER_REQUEST; the unchanged inactivity job uses its explicit system
identity and SYSTEM_POLICY. No independent log or audit store was introduced.
All pre-existing event policies and operator capabilities are preserved.

## Batches and reviewers

OPEN remains an accepting collection session, not a proxy for item visibility.
An individually submitted item does not close it or submit its siblings, even if
no unsent item remains at that instant. New candidates can still join this session.
Manual batch submission sends all remaining READY/PENDING_REVIEW/NULL-timestamp
items and closes the batch atomically. Already submitted rows and events are
untouched. With no unsent items, manual submission keeps its no-op/conflict response.
The existing 30-minute executor closes eligible all-sent sessions too; it preserves
its limits and one batch-ready signal per closure. No scheduler was activated.

Owner pending count and CTA eligibility count only eligible unsent items.
Reviewer batch discovery/detail, item access and source download are item-aware:
a submitted photo in OPEN is visible; unsent siblings are excluded. Existing
review/approval/correction/replacement capabilities remain required; unsent items
are now ineligible for those operations. The reviewer date uses the first actual
item submission when the batch has not yet closed.

An OPEN session with submitted history must not be retired/detached on withdrawal.
Historical archive/purge still requires a closed batch; a partially visible OPEN
batch is never treated as a completed archive merely because its visible photo
has been approved. Source download contains only review-visible items.

## Photo flow and preservation

Photo candidate creation returns its exact created_submission_id, preventing a
concurrent upload from redirecting auto-submit to a different current candidate.
The existing shared browser upload function submits that ID only, then refreshes
canonical owner state. Intermediate refreshes are suppressed during this sequence.
Successful normal photo flow goes directly to En revisión. Logo/gallery uploads
retain manual submission behavior. No full-batch POST is used for photo auto-submit.

On submit failure the candidate is retained, canonical status is refreshed and a
recoverable message is shown. The existing pending-send action remains available.
A lost acknowledgement is not treated as proof of rollback: the UI refreshes the
actual state and retry is idempotent. Public/Header media, grouped profile save,
layout, themes and admission governance are unchanged.

The local review migration added only NULL values to existing items: there were
no local closed pending batches to backfill. Existing pending media was not
retroactively submitted. Fifteen existing table snapshots and Leticia's profile
matched before/after when excluding the new nullable field. The local review
server remains on 127.0.0.1:18143 with the same document root and session router.

## Validation

Run `node modules/media/tests/ItemSubmissionQa.mjs` from the repository. It creates
an isolated source copy under /tmp/mxmed-mr02, disposable MySQL on port 3313 and
synthetic private/public media. No Director DB configuration or personal media is
copied. It stops its container and browser/server processes after testing.

All 15 suites passed: migration/backfill compatibility; individual submission;
existing batch service and atomicity; empty-batch retirement; photo and logo
approval; logo review; reviewer intervention; replacement requests; gallery;
logo improvement; historical archives; item/item and item/batch races; real HTTP
and Chrome upload/status flow. Tests cover rollback on audit/commit failure,
lost commit acknowledgement, idempotence, CSRF, ownership, legacy access, pending
counts, retained siblings, unchanged public authority, and no browser JS exceptions.
Logs: `/tmp/mxmed-mr02/*.log`.

Canonical audit policy/registry, request correlation and credential policy tests
also pass. Existing policies retain their semantic hashes; catalog size is now 44.
PHP/JS syntax and git diff whitespace checks pass.

## Changed files

- `api/media/owner-review.php`
- `api/media/profile-photo-review-candidate.php`
- `api/media/review-batch-submit.php`
- `api/media/review-candidate-submit.php`
- `assets/js/media-review-inbox.js`
- `assets/js/owner-media-review.js`
- `index.html`
- `modules/media/db/migrations/2026_09_13_media_review_item_submission.sql`
- `modules/media/docs/mr02-item-review-submission.md`
- `modules/media/services/BatchOriginals.php`
- `modules/media/services/GalleryApprovalService.php`
- `modules/media/services/HistoricalOriginalArchive.php`
- `modules/media/services/LogoImprovementService.php`
- `modules/media/services/MediaCandidateSubmissionService.php`
- `modules/media/services/MediaReplacementService.php`
- `modules/media/services/MediaReviewAccessService.php`
- `modules/media/services/MediaReviewAudit.php`
- `modules/media/services/MediaReviewBatchInboxService.php`
- `modules/media/services/MediaReviewBatchService.php`
- `modules/media/services/MediaReviewInterventionService.php`
- `modules/media/services/MediaSubmissionActor.php`
- `modules/media/services/MediaSubmissionState.php`
- `modules/media/services/PhysicianLogoApprovalService.php`
- `modules/media/services/PhysicianMediaReviewCandidateService.php`
- `modules/media/services/ProfilePhotoApprovalService.php`
- `modules/media/services/ProfilePhotoReviewCandidateService.php`
- `modules/media/tests/EmptyReviewBatchTest.php`
- `modules/media/tests/GalleryReviewFixture.php`
- `modules/media/tests/ItemSubmissionHttpTest.mjs`
- `modules/media/tests/ItemSubmissionMigrationTest.php`
- `modules/media/tests/ItemSubmissionQa.mjs`
- `modules/media/tests/ItemSubmissionRaceTest.mjs`
- `modules/media/tests/ItemSubmissionTest.php`
- `modules/media/tests/LogoImprovementFixture.php`
- `modules/media/tests/OriginalArchiveTest.php`
- `modules/media/tests/OwnerMediaReviewBrowserTest.mjs`
- `modules/media/tests/OwnerMediaReviewHttpTest.mjs`
- `modules/media/tests/OwnerMediaReviewInlineBrowserTest.mjs`
- `modules/media/tests/PhysicianLogoApprovalTest.php`
- `modules/media/tests/PhysicianLogoReviewFixture.php`
- `modules/media/tests/PhysicianLogoReviewTest.php`
- `modules/media/tests/ProfilePhotoApprovalFixture.php`
- `modules/media/tests/ProfilePhotoApprovalTest.php`
- `modules/media/tests/ReviewBatchAtomicTest.php`
- `modules/media/tests/ReviewBatchRaceWorker.php`
- `modules/media/tests/ReviewBatchTest.php`
- `modules/media/tests/ReviewBatchVisual.mjs`
- `modules/platform/contracts/CanonicalAuditEventType.php`
- `modules/platform/services/CanonicalAuditPolicyRegistry.php`
- `modules/platform/services/CorrelatableOperationCatalog.php`
- `modules/platform/services/SourceModuleCatalog.php`
- `modules/platform/tests/AuditMp01CD01D10ResolvedTest.php`
- `modules/platform/tests/AuditMp01CFocusedClosureTest.php`
- `modules/platform/tests/AuditMp01CStaticContractsTest.php`
- `modules/platform/tests/AuditMp01DRequestCorrelationContextTest.php`
- `modules/platform/tests/AuditMp01HPostvalidationTest.php`
- `modules/platform/tests/PhysicianCredentialAuditPolicyTest.php`
- `scripts/packaging/runtime-files.json`
- `scripts/packaging/setup-test-db.php`
