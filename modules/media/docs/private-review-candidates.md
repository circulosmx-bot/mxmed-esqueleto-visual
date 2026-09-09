# MR1: private photo candidates

`media_assets` remains the PUBLIC representation table. Private review files never
enter it. SOURCE (original validated bytes), REVIEW (normalized WebP) and PUBLIC
are separate lifecycle roles. MR1 implements only SOURCE and REVIEW for a photo
candidate, without publishing or changing the existing photo control.

Apply `db/migrations/2026_09_08_media_review_candidates.sql` once using the existing
DB connection. It adds `media_review_submissions` and `media_review_files`, with
an owner/purpose/pending index, a generated unique active-photo-owner constraint,
unique storage keys and submission/role pairs, and a cascading file relationship.
Technical states and future editorial states are defined, but runtime only creates
READY/PENDING_REVIEW and retires pending submissions as WITHDRAWN. CORRECTED is a
reserved file role; no correction or approval transition is exposed.

The isolated `/api/media/profile-photo-review-candidate.php` supports authenticated
GET, multipart POST (`image`) and DELETE. It uses GallerySessionScope and a separate
`X-Profile-Photo-Candidate-CSRF` token returned by GET. Client owner identifiers
are ignored. GET returns only the owner's candidate metadata, with no keys,
URLs or binary content. There is no private binary HTTP endpoint in MR1.

`MXMED_PRIVATE_MEDIA_ROOT` defaults to `$HOME/.local/share/mxmed/private-media`.
The adapter rejects overlap with the application tree, configured document root,
and public media root. Operators must not map this root into any web server alias.
Files are 0600, directories 0700; objects are materialized atomically without
clobbering existing keys. No AWS wiring is introduced.

The shared processor's optional validated-source callback runs only after bounds,
MIME and decode checks, before orientation and encoding. The candidate stores the
exact original there, then stores the stripped WebP review. The request temp source
remains PHP-owned. Limits: 10 MiB, 25 MP, 8192 source side; WebP review <=800 side
and <=153600 bytes. Original metadata is private; review metadata is stripped.

A physician-row lock serializes candidate switches. Both new files are completed
before the DB transaction withdraws the old candidate and inserts the new one.
Pre-commit failures roll back rows and clean new objects. Old file deletion happens
only after commit; failures are logged and do not undo the new authority. Withdrawn
rows/file metadata remain as retirement records; their binaries are removed.
Filesystem and DB are not a distributed transaction: a process crash can leave
private orphan objects. No cleanup worker or batch scheduler is added in MR1.

Local rollback: stop using the candidate endpoint, retire/remove candidate private
objects through the private adapter, then drop `media_review_files` followed by
`media_review_submissions`. These operations must not touch public tables/files.
Do not apply rollback to retained real submissions without a retention decision.

Focused QA (synthetic images only):

- `php modules/media/tests/PrivateMediaStorageTest.php`
- `php modules/media/tests/ProfilePhotoReviewCandidateTest.php`
- Run PHP HTTP server with upload/post limits above 10 MiB, then
  `PUBLIC_URL=http://127.0.0.1:8097 node modules/media/tests/ProfilePhotoReviewCandidateHttpTest.mjs`

Integration tests refuse to replace an existing physician-1 pending candidate.
They compare public rows, references and files, and never upload/delete a public
photo, logo or gallery image. HTTP QA normally withdraws/removes its own fixtures;
`MR1_RETAIN_SYNTHETIC_CANDIDATE=1` explicitly retains its last synthetic pending
candidate for inspection. No advisor UI, role or global moderation activation.

## MR2: internal REVIEW reads

Owner upload, internal review and public delivery use distinct authority:

- PHYSICIAN OWNER: the existing candidate endpoint accepts private submissions.
- INTERNAL REVIEWER: GET `/api/internal/media-review/submission.php` and
  `/api/internal/media-review/review-image.php`, each with `submission_id`.
- PUBLIC USER: only existing `media_assets` READY/PUBLIC delivery.

`MediaReviewAuthority` delegates to the existing platform `AuthorizationBoundary`.
The requirement fixes plane `internal_operator`, action `read`, resource
`media_review_submission` and exact capability `media_review_read`. R0 is the
lowest existing risk with no mandatory per-preview audit; authentication is
explicitly required even at R0. Trusted backend context, active account/session,
credential version, plane and capability checks remain mandatory. Generic roles,
physician ownership, client context and transitional_open never grant this access.
SOURCE download remains unimplemented and should later have a separate, stronger
capability/risk/audit requirement; these endpoints cannot select a file role.

At MR2, production composition remained fail-closed. Inspection of `IdentityHttpComposition`
and `FailClosedAuthorizationService` found account/profile/group authorization,
not an established internal-operator grant resolver. MR2 does not relabel those
memberships as staff or create another authentication system. MR3 below adds the canonical account/session/grant resolver; real grants still
require a separate authorized operational step.

HTTP QA uses existing PHP sessions created by CLI, with a distinct server-owned
`media_review_dev_operator` record, fixed synthetic account, explicit capability
grant, account state, session state and expiry. There is no fixture login endpoint.
All gates must hold: `MXMED_MEDIA_REVIEW_DEV_OPERATOR_ENABLED=1`,
`MXMED_ENVIRONMENT=local|development`, direct loopback REMOTE_ADDR, and no
non-development environment declaration in APP_ENV/MXMED_ENV/ENVIRONMENT.
Missing environment declarations alone never enable access. No headers, query or
body fields supply operator identity or capabilities. Production/default requests
are denied even if a fixture session exists.

`MediaReviewAccessService` only SELECTs READY/PENDING_REVIEW profile-photo
submissions and their REVIEW row. Metadata is an allowlist without keys, URLs,
SOURCE attributes or physical paths. Binary reads use the existing private port,
with a bounded <=153600-byte buffer so length and SHA-256 can be verified before
any bytes are emitted. Missing/corrupt files fail closed; no fallback or repair.
Errors never change candidate/public authority. Responses use private, no-store,
nosniff, same-origin resource policy and no CORS grant. Private bytes get no public
URL, shared cache or signed-link mechanism. Only GET is supported.

MR2 read-only integration QA preserves retained synthetic submission
`42adffaa-3344-47cf-9bf5-6831c969cbd5` and compares full public/private row digests
plus file hashes. It must not run the MR1 replacement/withdrawal tests against it.

- `php modules/media/tests/MediaReviewAccessTest.php`
- `node modules/media/tests/MediaReviewHttpTest.mjs`

The HTTP test starts/stops local PHP servers, creates/destroys only synthetic
sessions, and uses a separate disposable private root for missing/tampered-file
checks. It never modifies the retained candidate, its SOURCE/REVIEW, or public
media. No migration, staff panel, editorial transition or publishing operation
is part of MR2.

## MR3: canonical internal operator authority

Canonical account → canonical session → active internal operator grant → backend
TrustedAuthorizationContext → existing AuthorizationBoundary. Customer/professional
roles, ownership and paid plans never imply staff authority. An operator needs no
physician profile or professional membership.

Authentication remains `CredentialAuthenticationService::authenticate`, followed by
`SessionService::create`. The opaque token lives in the existing secure, HttpOnly,
SameSite=Lax `__Host-mxmed_session` cookie. `CanonicalHttpSessionResolver` delegates
validation to SessionService: session-store validity, idle/absolute expiry,
revocation/supersession, current account state and credential version are checked
before consulting grants. Production uses the existing Valkey composition with
`PdoSessionAccountStateAdapter`; PHP `$_SESSION` is not productive authority.

`IdentityHttpComposition::fromProcessEnvironment` centralizes the existing selector
used by login and media reads. APP_ENV and the established configuration determine
productive staging/production or explicit local preview. Requests cannot select
that composition. This extraction does not change login/session behavior.

Migration `modules/identity/db/migrations/2026_09_08_07_create_internal_operator_grants.sql`
requires the existing canonical `auth_accounts` migration. It adds grant UUID,
canonical account FK, case-sensitive explicit capability, ACTIVE/REVOKED status,
created/updated/revoked timestamps and generated active-slot uniqueness per
account/capability. There are no secrets, tokens, passwords, doctor IDs or seeds.
Revoked rows preserve history and permit a later distinct active grant. The paired
rollback drops only the grant table; existing canonical account schema is not
owned by this rollback.

`InternalOperatorGrantRepository` reads active grants on every request, without a
session capability cache. `InternalOperatorAuthority` builds actor/session/account/
credential facts from canonical validation and capabilities solely from those
backend grants. MR2's exact `media_review_read`, internal_operator, read,
media_review_submission and R0 requirements are unchanged. The repository has no
productive grant mutation endpoint. Future real grant/revoke operations require
explicit administration and separate security auditing; MR3 assigns no real grant.

A supplied canonical cookie always takes precedence: invalid session, configuration
or grant lookup never falls back to the MR2 fixture. With no canonical cookie,
production/staging return denial before consulting PHP fixture state or its flag.
The existing fixture remains opt-in, explicit local/development and loopback only.
No SOURCE route or editorial transition is introduced.

QA: `ProductiveOperatorHttpTest.mjs` uses canonical password authentication,
SessionService and Valkey through the existing explicit preview composition, plus
an isolated canonical identity DB. It exercises the same grant/context resolver
used by productive composition, without the MR2 operator fixture. It verifies
revocation, session/account/credential failures, exact REVIEW bytes and unchanged
public/candidate snapshots. Productive selector/session contracts are tested
separately; this QA does not deploy or connect to AWS.

Run with a disposable MySQL server and Valkey instance (default Valkey port 6387):

```sh
MR3_TEST_DB_HOST=127.0.0.1 MR3_TEST_DB_PORT=3309 \
MR3_TEST_DB_USER=root MR3_TEST_DB_PASS='' \
node modules/media/tests/ProductiveOperatorHttpTest.mjs
```

Use these credentials only for an isolated test server. The CLI fixture requires
an `mxmed_gate4d_preview_mr3_*` database name and drops its own database afterward.
Local application schema receives no synthetic accounts or grants. No real advisor
is enabled until a separately authorized grant is provisioned in canonical identity.

## MR4: read-only inbox

`/internal/media-review/` authorizes through MR3 before emitting its HTML shell.
`/api/internal/media-review/pending.php` independently enforces the same
internal_operator / media_review_read requirement before querying protected data.
No real operator grant is provisioned. SOURCE and editorial mutation remain absent.

`MediaReviewInboxService` performs one metadata-only joined query against submissions,
REVIEW files and `profiles_doctors.display_name` (the canonical public display-name
field). It lists only PHYSICIAN / DOCTOR_PROFILE_PHOTO / READY / PENDING_REVIEW,
ordered by created_at ASC, submission_id ASC. Offset pagination defaults to 25,
caps at 50 and rejects offsets beyond 1,000,000. A limit+1 lookahead returns
has_more/next_offset without an unbounded response or total-count scan. Offset
pagination is deterministic per query; changes to the live queue can move rows
between pages. No batch semantics are implied.

Responses expose an explicit safe allowlist, no contact/account data or storage
keys. List assembly never opens private files. Cards and a native dialog request
only the existing authenticated review-image endpoint, with lazy thumbnails,
reserved image space, object-fit:contain and a neutral transparency background.
The UI displays recorded submission date, REVIEW dimensions/weight, and Spanish
purpose/status labels. Empty, list-error and individual-image-error states are
independent. Escape closes the dialog and restores focus. All UI requests are GET;
there are no approval, publication, SOURCE or placeholder mutation controls.

Focused tests:

- `php modules/media/tests/MediaReviewInboxTest.php` uses an in-memory SQLite fixture
  for ordering, pagination, excluded states/purposes, identity and metadata privacy.
- The existing `ProductiveOperatorHttpTest.mjs` now covers the inbox page and list
  with canonical synthetic accounts/grants, including denials and revocation.
- `node modules/media/tests/MediaReviewInboxBrowserTest.mjs` uses an isolated browser
  context on the local CDP endpoint (default port 9348) and a restricted MR2 fixture.
  It captures 1440×900, 1366×768, 390×844 and 320×740 under `/tmp/mxmed-mr4-visual`,
  verifies dialog/focus, empty/error behavior and GET-only private REVIEW requests.
  Error fixtures are browser interceptions; retained media is not modified.

MR4 changes no schema, grants, public media or candidate state. The inbox remains
inaccessible to real accounts until a separate authorized grant assignment.

## MR5: audited profile-photo approval

READ remains `media_review_read` / R0. APPROVE requires canonical MR3 identity,
both `media_review_read` and `media_review_approve`, INTERNAL_OPERATOR, action
`approve`, resource `media_review_submission`, and R1 with required audit.
The MR2 development fixture cannot approve. No real approve grant is seeded.

`GET /api/internal/media-review/approval-options.php` exposes only the current
read-authorized operator's approval availability and, when allowed, a short-lived
canonical identity CSRF token bound to the validated session digest. It changes no
editorial state. `POST /api/internal/media-review/approve.php` accepts JSON with
only `submission_id` and `csrf`; each POST resolves session/account/credential and
active grants anew. Query/body/header identities are never authority. All internal
responses are private/no-store; error responses never include exception strings.

The confirmation in the existing detail dialog offers Cancelar/Aprobar, disables
controls while publishing, and refreshes the DB-backed queue after success or a
409 conflict. Read-only operators have no active approve control. The only new
editorial transition is `READY/PENDING_REVIEW → READY/APPROVED + PUBLIC` for
`PHYSICIAN/DOCTOR_PROFILE_PHOTO`.

`ProfilePhotoApprovalService` owns one PDO transaction. The authorization boundary
checks trusted canonical identity and all requirements before invoking its audit
port. That port locks the physician first (same order as MR1), re-locks/rechecks the
candidate and private file relationship, verifies bounded REVIEW length/hash/WebP
dimensions, locks the previous public photo rows, and appends a real canonical
audit event. Only a successful append returns ACCEPTED to the boundary. Publication
then copies those exact REVIEW bytes into a new immutable public UUID/key, verifies
the stored copy, inserts a canonical media_asset, switches `photo_url`, retires old
rows and marks the candidate APPROVED. A single outer commit confirms all DB changes,
including audit. No SOURCE image is decoded or published during approval.

The existing canonical writer, serializer, hash chain, physical mapper and controlled
SQL lock/CAS calls are reused. `JoinedPdoCanonicalAuditTransactionAdapter` requires
an active outer transaction and never commits it; ordinary canonical writer callers
retain their existing transaction behavior. The additive canonical event
`MEDIA_PROFILE_PHOTO_APPROVED` has WARN/R1, SUCCESS/ADMIN_DECISION, required actor
and session, and allowlisted submission/physician/published-media identifiers.
Real/effective actor is the internal account; the submission is the affected resource.
Request/correlation UUIDs are server-generated. No private key, path, binary, EXIF,
credential or session token is recorded. The original 28 event policies retain their
semantic hash and MP01E/MP01F producer scopes stay 13/15; MR5 is a dedicated producer.
No schema or migration changes are needed. Runtime requires the existing canonical
audit INSERT/stream-head INSERT and controlled procedure EXECUTE privileges on the
same database transaction; failure is closed, never a separate best-effort append.

On a confirmed rollback, a newly staged public object is cleaned. Missing/tampered
REVIEW, storage/DB/audit errors leave previous authority intact. If the connection
loses the COMMIT acknowledgement, its outcome can be uncertain: preserve the new
object, return a safe unavailable response and log reconciliation need; never delete
an object that may already be canonical. A retry observes terminal state as 409 if
the commit succeeded. Old physical files are deleted only after confirmed commit;
cleanup failure is logged and cannot undo the new canonical state. Private SOURCE
and REVIEW rows/files remain as history; no SOURCE HTTP route exists.

### Isolated MR5 QA

Use disposable MySQL 8 on **127.0.0.1:3309** (root/empty password only in this test
container) and Valkey on **127.0.0.1:6387**, plus Chrome CDP on port 9348. The media
fixture creates a fresh `mxmed` database on that fixed disposable server; it fails
if the database already exists. It copies only current table DDL, never actual
physician rows/files. Its controlled SQL procedures are test-only implementations
of the canonical lock/CAS contract, not a deployment of production procedures.

```sh
MR5_FIXTURE_ROOT=/tmp/mxmed-mr5-qa php modules/media/tests/ProfilePhotoApprovalFixture.php setup
MR5_FIXTURE_ROOT=/tmp/mxmed-mr5-qa php modules/media/tests/ProfilePhotoApprovalTest.php
MR5_FIXTURE_ROOT=/tmp/mxmed-mr5-qa node modules/media/tests/ProfilePhotoApprovalHttpTest.mjs
```

The service suite verifies authority, eligibility, integrity, publication/audit
atomicity, injected storage/insert/update/audit/CAS/commit failures, lost commit
acknowledgement, repeat conflict, retained private history and post-commit cleanup
failure. The HTTP suite uses synthetic canonical authenticated accounts, real
SessionService/Valkey, active/revoked grants and session-bound CSRF. Two distinct
reviewers concurrently produce one 200 and one 409 with one audit/public asset.
It runs a copied application with an isolated media config; the actual application
config is never replaced. Browser QA performs actual isolated approvals at
1440×900, 1366×768, 390×844 and 320×740, including keyboard confirmation/cancel,
busy/double-click protection, success, queue removal and read-only controls.
Screenshots go to `/tmp/mxmed-mr5-visual`. Stop/remove the disposable containers and
fixture storage after QA. These tests do not deploy productive identity or assign
any real reviewer grant. Actual Director table/file snapshots must match before
and after; the retained candidate stays READY/PENDING_REVIEW and public counts 1/1/8.

## MR6: manual design intervention (profile photos only)

The private lifecycle is now SOURCE (unchanged user original), CORRECTED (exact
validated staff raster), REVIEW (normalized proposal), then MR5 PUBLIC (approved
media_asset). CORRECTED never publishes directly and has no download/public URL.
There is no designer assignment, design state, automatic processing workflow,
logo/gallery intervention or real grant provisioning.

- `GET /api/internal/media-review/source-download.php?submission_id=<uuid>` requires
  canonical INTERNAL_OPERATOR authority with `media_review_source_download`, R1 and
  required canonical audit. Endpoint semantics fix SOURCE; no role/key/path/filename
  parameters are accepted. Eligibility is PHYSICIAN / DOCTOR_PROFILE_PHOTO /
  READY / PENDING_REVIEW. The full bounded original (at most 10 MiB) is verified
  against stored length/SHA-256 before the audit commits and before attachment
  bytes/headers are emitted. JPEG/PNG/WebP MIME and a server-generated filename
  are used with private/no-store, nosniff, same-origin resource policy and no CORS
  wildcard. This GET changes no editorial state and requires no CSRF; it does not
  reserve the candidate or hold physician/submission locks. Another reviewer may
  approve after the source has been resolved/downloaded.
- `POST /api/internal/media-review/corrected.php` accepts only multipart
  `submission_id`, `csrf`, and file `corrected`. It requires canonical
  `media_review_corrected_upload`, R1 and required audit; read/approve/source grants
  do not imply this capability. CSRF is the existing canonical session-bound token.
  Ownership is resolved from the submission, never from client fields. The route's
  `.user.ini` uses the same 10M upload / 12M request / 256M memory envelope as the
  existing profile-photo route; the built-in dev servers already use these limits.

Both endpoints re-resolve active canonical account/session/credential/grants and
reject the MR2 local operator fixture. UI options expose only `can_approve`,
`can_download_source`, `can_upload_corrected` and CSRF where needed. The inbox still
requires read authority; each operation independently requires its own capability.
No real SOURCE-download or corrected-upload grant is seeded.

Correction locks physician -> submission -> files, matching MR1/MR5. Under verified
capability, it validates finfo MIME/extension, successful decode, 10 MiB/25 MP/8192
side limits, then retains the exact CORRECTED input and generates/stores/validates
its REVIEW with the existing processor (WebP, <=800 side, <=153600 bytes, normalized
orientation, stripped metadata, preserved aspect/valid transparency). Unique current
CORRECTED/REVIEW rows are replaced in one transaction with required audit; SOURCE
is never deleted or updated. Submission remains READY/PENDING_REVIEW. Public media
and `profiles_doctors` are not updated. Old private binaries are cleaned only after
commit; confirmed rollback cleans staged files. An uncertain commit acknowledgement
preserves potentially authoritative files and signals reconciliation. No versioning
schema is added. MR5 now accepts SOURCE + REVIEW with optional valid CORRECTED and
still publishes only the current verified REVIEW. Shared row locks serialize
correction/approval: correction first means the new REVIEW is published; approval
first means correction fails 409 without reopening anything.

`MediaReviewAudit` shares the existing canonical writer/joined transaction machinery
with MR5. Only two canonical events are added: `MEDIA_REVIEW_SOURCE_DOWNLOADED` and
`MEDIA_REVIEW_CORRECTED_UPLOADED`, both WARN/R1. Safe metadata identifies submission,
physician and, for correction, the new corrected/review file IDs. Actor is the
canonical internal account, request/correlation IDs are server-generated; no binary,
EXIF, filename, storage key, path or token enters audit metadata. Source success means
verified authorized delivery is about to occur. Correction success is committed with
its new private authority. Audit failure delivers no SOURCE bytes and commits no
correction. The preceding 29 policy rows retain their semantic hash and legacy
MP01E/MP01F scopes remain unchanged.

The detail dialog shows capability-specific Descargar original/Subir versión
corregida actions. The native JPEG/PNG/WebP picker displays a text-only basename and
requires Guardar versión corregida. Busy state blocks duplicate upload and approval.
After success the existing REVIEW endpoint is fetched with no-store and displayed
through a new local Blob URL in the modal/cards, avoiding stale decoded-image reuse
for the unchanged endpoint URL. CSP permits these REVIEW Blob images. Approval stays
disabled until the refreshed image loads; a failed refresh hides the stale image and
requires reload/inspection. There is no second corrected preview endpoint and no
automatic approval.

### Isolated MR6 QA

Use the same disposable MySQL/Valkey/CDP setup documented for MR5. All mutation
fixtures use the fixed disposable MySQL port 3309, a copied app config, synthetic
canonical accounts and disjoint private/public roots. Existing table DDL is copied
without application data; audit lock/CAS procedures are test contract implementations.
Nothing here deploys production procedures or enables a real operator.

```sh
MR5_FIXTURE_ROOT=/tmp/mxmed-mr5-mr6qa php modules/media/tests/ProfilePhotoApprovalFixture.php setup
php modules/media/tests/MediaReviewInterventionPolicyTest.php
MR5_FIXTURE_ROOT=/tmp/mxmed-mr5-mr6qa php -d memory_limit=256M modules/media/tests/MediaReviewInterventionTest.php
MR5_FIXTURE_ROOT=/tmp/mxmed-mr5-mr6qa node modules/media/tests/MediaReviewInterventionRaceTest.mjs
MR5_FIXTURE_ROOT=/tmp/mxmed-mr5-mr6qa node modules/media/tests/MediaReviewInterventionHttpTest.mjs
```

Coverage includes exact audited SOURCE attachment, capability/session/revocation/CSRF
failures, private integrity failures, JPEG/PNG/WebP normalization and rejected inputs,
repeated correction, storage/DB/audit/commit rollback, unchanged public authority,
12 MP / near-10 MiB HTTP upload, both deterministically gated approval races, and
MR5 publication of the corrected REVIEW. Browser QA covers 1440×900, 1366×768,
390×844 and 320×740: actual synthetic download, native picker, busy/duplicate guard,
refreshed image/specs, explicit MR5 approval, capability-specific controls, keyboard
and failed-preview approval blocking. Captures are in `/tmp/mxmed-mr6-visual`.
Director table/file snapshots must match; retained candidate remains unchanged.

## MR7 — physician personal logo lifecycle

MR7 adds `PHYSICIAN_PERSONAL_LOGO` for owner `PHYSICIAN`, with the same private
submission/file tables and SOURCE → REVIEW → PENDING_REVIEW → APPROVED → PUBLIC
lifecycle. `PhysicianMediaReviewCandidateService` supplies the shared candidate
normalization and atomic pending replacement; explicit photo/logo wrappers fix
the purpose server-side. Stored objects are verified before switching pending
authority. A lost commit acknowledgement preserves potentially authoritative
objects for reconciliation.

`/api/media/physician-logo-review-candidate.php` supports GET/POST/DELETE using
`GallerySessionScope`. GET returns owner metadata and `csrf_token`; mutations
require `X-Physician-Logo-Candidate-CSRF`. POST takes multipart `image` only.
Client owner identifiers are rejected and never used as authority. A replacement
first completes SOURCE and REVIEW, then withdraws the prior pending logo in a
transaction. DELETE withdraws only the current owner's pending logo. Neither
operation writes public assets or `profiles_doctors.logo_url`.

JPEG, PNG and WebP inputs allow 10,485,760 bytes, 25,000,000 pixels and 8192 pixels
per side. SVG is rejected. Exact validated SOURCE remains private, including any
original metadata. REVIEW is re-encoded WebP, at most 800 pixels per side and
153,600 bytes, preserving aspect ratio, EXIF orientation and existing alpha while
stripping metadata. No transparency is invented for opaque images.

Apply `db/migrations/2026_09_08_media_review_pending_logo.sql` once before enabling
the candidate endpoint. It adds only generated `active_logo_owner` and unique
`uniq_review_active_logo(owner_type, active_logo_owner)`, preserving the existing
photo slot. Photo and logo can each have one pending submission independently.

The existing pending inbox and fixed-role `review-image.php` support both
purposes. The advisor label is **Logotipo profesional**. The existing contain-fit
preview and checkerboard show the complete image and alpha without cropping.
Dimensions, optimized weight and WebP remain visible. Existing capabilities apply:
`media_review_read`, `media_review_approve`, `media_review_source_download`, and
`media_review_corrected_upload`. No real grants are seeded.

The fixed `/api/internal/media-review/approve-logo.php` endpoint shares canonical
session/CSRF checks with photo approval but delegates to the explicit
`PhysicianLogoApprovalService`. It accepts only `submission_id` and `csrf`.
It locks physician → submission → files, verifies eligibility and REVIEW
integrity, writes `MEDIA_PHYSICIAN_LOGO_APPROVED` through the canonical R1 audit
writer in the same transaction, stores/verifies exact REVIEW bytes publicly,
inserts the PUBLIC/READY logo asset, switches `profiles_doctors.logo_url`, and
marks the candidate APPROVED. Audit failure rolls back publication. Existing
photo approval remains purpose-specific. Both correction and approval share the
same lock order: correction-first publishes the new REVIEW; approval-first makes
correction conflict. Duplicate approval cannot publish twice.

### Historical reference policy

MR7 **retains every previous immutable public logo row/file as READY**. Canonical
profile references alone are not exhaustive reference counts:
`LogoReferenceRepository::countReferences()` counts profiles and consultorios,
while `assets/js/app.js` records `logo_url_resolved` and legacy localStorage
branding; clinical viewers consume these snapshots. The database also has JSON
payloads in `clinical_documents`, document backups, and `clinical_record_entries`,
and separate consultorio/group/membership logo fields. Browser storage and
historical JSON references cannot be exhaustively counted by that repository.
No historical snapshots, clinical rendering, group branding or legacy precedence
are rewritten. Existing current branding readers see the new canonical URL;
historical URLs continue to resolve. MR7 does not change the legacy direct-public
uploader's separate retirement behavior; switching that UI requires a later gate.

Manual intervention uses the existing audited SOURCE download and CORRECTED
upload endpoints, exact capabilities and R1 semantics. SOURCE → CORRECTED → REVIEW
→ explicit APPROVAL publishes the new REVIEW; SOURCE stays immutable, CORRECTED
stays private, and correction alone never changes the public logo.

### Later user-control activation

The accepted `uploadPhysicianLogo` and delete handlers in `assets/js/app.js`
continue calling `${buildPrivateEndpoint(state.doctorId)}/logo` immediately.
A later activation must route uploads to the candidate endpoint with its GET-issued
CSRF token and multipart `image`, show pending/withdrawal state separately from the
approved logo, and keep the current logo displayed until approval. Candidate
withdrawal must not be confused with deletion of the approved public logo.

At the MR7 baseline automatic logo improvement was unimplemented. The isolated
MR8 proposal pilot below adds conservative removal; SVG/vectorization remain absent.

### Isolated verification

MR7 tests use only synthetic physicians/images in disposable MySQL on port 3309,
Valkey on 6387, copied application configuration and separate private/public roots.
`PhysicianLogoReviewTest.php`, `PhysicianLogoCandidateAtomicTest.php`,
`PhysicianLogoApprovalTest.php`, `PhysicianLogoReviewPolicyTest.php`,
`PhysicianLogoReviewRaceTest.mjs`, and `PhysicianLogoReviewHttpTest.mjs` cover the
lifecycle, input boundaries, alpha/orientation, atomic failures, canonical audit,
authority, historical public URLs, and both race orders. HTTP QA includes the owner
endpoint, mixed inbox and existing public delivery. Its browser helper tests all
four shapes at 1440×900, 1366×768, 390×844 and 320×740, native file selection,
manual correction followed by explicit approval, stale-preview failure and
capability-specific controls. Existing photo MR5/MR6 tests remain regression gates.

## MR8 — conservative automatic logo proposals

Only pending `PHYSICIAN_PERSONAL_LOGO` submissions can use this pilot. The server
uses current IMPROVEMENT_INPUT (MR8.1) exclusively; clients cannot select
roles, owners, thresholds or authority. The isolated user-facing logo uploader
remains unchanged. No real `media_review_improve` grants are created.

Bounded lossless IMPROVEMENT_INPUT → private AUTO_PROPOSAL → human comparison → explicit
acceptance → REVIEW → separate audited approval → PUBLIC. Generating or discarding
a proposal never changes REVIEW. Acceptance never publishes and leaves
READY/PENDING_REVIEW, SOURCE and CORRECTED intact.

### Deterministic algorithm and bounds

The PHP GD processor decodes the integrity-verified lossless PNG within 4 MiB /
640000 pixels / 800-side limits. Source normalization already applied orientation
and bounded resizing at upload time. An optional internal working-image transform
runs before the usual WebP encoder. Ordinary processor callers do not use this
hook and retain their prior behavior.

`ConservativeLogoBackground` uses this fixed server policy:

- Minimum working side 16 pixels; maximum 800. Any existing nonzero alpha returns
  ALREADY_TRANSPARENT without modification, including sparse transparency.
- Compute per-channel median of every outer-border pixel, including all corners.
  Every border pixel must be within maximum-channel RGB distance 2 of that color;
  mean squared maximum-channel distance must be at most 1.
- Seed a four-connected flood fill from all image edges. Only pixels **exactly**
  equal to the detected dominant RGB color are traversable/removable. There is no
  global color replacement. Enclosed matching white letters remain opaque.
- Require a removable exterior of 5–95% of image pixels and at least 1% foreground
  with maximum-channel distance at least 24. Otherwise return NO_SAFE_IMPROVEMENT.
- Do not partially fade nonmatching pixels. Near-background thin lines and
  anti-aliased edges remain opaque; conservative residual edges are preferable to
  foreground loss. No uncalibrated confidence percentage appears in the UI.

This accepts exact white, off-white and other solid colors. Near-solid borders
may qualify, but only exact dominant-color connected pixels are removed. The
pilot deliberately abstains on ambiguous borders/gradients and does not perform
photographic segmentation. The working mask is at most 640,000 bytes; the packed
queue at most 2,560,000 bytes. Source decode still consumes bounded GD native RAM.
Output uses the existing metadata-stripping WebP envelope: 800-side / 153600 bytes,
preserved aspect ratio and alpha. No external service, paid API, generative model,
SVG, vectorization or new runtime dependency is used. External per-image service
cost is zero; local CPU/RAM and storage still have infrastructure cost.

### Endpoints, authority and audit

All mutations require a canonical INTERNAL_OPERATOR session, the exact
`media_review_improve` capability, R1 authorization and canonical session-bound
CSRF. Read/approve/corrected capabilities never imply improvement. POST JSON accepts
only `submission_id` and `csrf`:

- `improve-logo.php`: generate/atomically replace one proposal.
- `accept-improvement.php`: verify current input fingerprint and atomically copy
  exact proposal bytes into a new REVIEW authority; retire proposal after commit.
- `discard-improvement.php`: remove only the proposal, with post-commit cleanup.

These routes live under `/api/internal/media-review/`. The fixed GET
`improvement-preview.php?submission_id=...` requires canonical read authority and
always resolves AUTO_PROPOSAL, with full bounded integrity checks, image/webp,
private/no-store, nosniff and no storage-key disclosure. Arbitrary role/path/key
parameters are rejected. Existing REVIEW delivery is unchanged.

The canonical events are `MEDIA_LOGO_IMPROVEMENT_PROPOSED`,
`MEDIA_LOGO_IMPROVEMENT_ACCEPTED`, and `MEDIA_LOGO_IMPROVEMENT_DISCARDED`; safe
metadata contains submission/physician identifiers only. All three use the shared
canonical writer joined to the candidate transaction. No-safe analysis is a
successful no-mutation outcome, not a generated-proposal event. Audit or commit
failure rolls back authority; ambiguous commit acknowledgement preserves possibly
committed objects for reconciliation.

Apply `2026_09_09_media_review_auto_proposal.sql` once. It extends the existing
role enum and adds nullable `input_file_id`/`input_checksum_sha256` fields plus a
proposal constraint. The existing submission+role unique key bounds proposal
count. Fingerprints are required only for AUTO_PROPOSAL. Repeated generation uses
atomic replacement. No new table or public asset is created.

All mutations lock physician → submission → files, matching MR6/MR7. Human
correction invalidates/removes any old AUTO_PROPOSAL in its own transaction.
Acceptance checks both effective input file ID and checksum; timestamps confer no
authority. MR7 approval ignores an unaccepted proposal and publishes only current
REVIEW. Approval-first makes later generation/acceptance conflict; acceptance-first
makes approval publish the improved REVIEW. Historical public-logo retention is
unchanged. Rollback to code without AUTO_PROPOSAL support requires first retiring
all proposal rows through the authorized workflow; do not truncate enum values or
drop fingerprint fields while proposals exist.

### Advisor UI and verification

The capability-gated logo-only control creates a side-by-side comparison on wide
screens and a stacked comparison on narrow screens, with equal contain-fit image
areas, checkerboard backgrounds and explicit Versión actual / Propuesta labels.
Only Usar versión mejorada changes REVIEW. Discard preserves it. A fresh private
REVIEW is fetched after acceptance; failed refresh disables approval. Every busy
mutation blocks overlapping local actions. Public approval remains a separate step.

`LogoImprovementAlgorithmTest.php`, `LogoImprovementTest.php`,
`LogoImprovementAtomicTest.php`, `LogoImprovementPolicyTest.php`,
`LogoImprovementRaceTest.mjs` and `LogoImprovementHttpTest.mjs` use only synthetic
media and disposable MySQL/Valkey/storage. They verify enclosed white and near-white
thin-line protection, abstention, effective-input fingerprints, private delivery,
audited rollback, lost commit acknowledgement and five deterministic race orders.
Browser QA covers seven fixture categories at all four required viewports, actual
alpha/interior pixels, keyboard actions, discard, acceptance and separate approval.
MR8 stops here; user-facing moderation activation and MR9 remain separate work.

### MR8.1 — bounded lossless logo analysis

Automated improvement uses only private `IMPROVEMENT_INPUT`, a PNG with maximum
800-pixel sides, 640000 pixels and 4194304 bytes. Logo candidate creation and human
logo correction export the already oriented, bounded working raster before WebP
encoding in the same source-decode pass. The PNG is decoded for bounded validation,
never by decoding the original a second time. RGB and alpha are preserved losslessly
from that working raster; metadata is not copied. Resampling still occurs when the
original exceeds 800 pixels. Profile photo candidates do not get this derivative.
Original upload limits remain 10 MiB / 25 MP / 8192 sides; REVIEW and public output
remain WebP, maximum 800 sides / 153600 bytes.

Apply `2026_09_10_media_review_improvement_input.sql` after the MR8 migration. It
adds one role and a PNG bounds CHECK, retaining the submission+role unique key and
existing fingerprint columns. There is no backfill. Legacy candidates without the
lossless input return `IMPROVEMENT_INPUT_UNAVAILABLE`; generation never falls back
to SOURCE, CORRECTED or REVIEW. Candidate replacement or corrected upload creates
the input naturally. No new route, public asset or download of this derivative is
introduced. Logo approval ignores analysis/proposal roles and publishes only REVIEW.

Generation reads IMPROVEMENT_INPUT, writes and verifies AUTO_PROPOSAL, and leaves
REVIEW unchanged. Proposal input ID and SHA-256 identify current IMPROVEMENT_INPUT.
Correction atomically replaces CORRECTED, IMPROVEMENT_INPUT and REVIEW and removes
any old proposal. Acceptance verifies the fingerprint and copies proposal bytes to
new REVIEW, keeping SOURCE/CORRECTED/IMPROVEMENT_INPUT and pending status unchanged.
Discard keeps REVIEW unchanged. Repeating an accepted improvement returns
`ALREADY_IMPROVED` without a new proposal. Existing lock order and canonical audit
semantics are unchanged. New keys are cleaned on confirmed rollback; uncertain
commit outcomes retain potentially authoritative files for reconciliation.

Attempt-1 diff classification, performed before editing:
- Reused unchanged: removal of the high-resolution generation benchmark from the
  algorithm test; existing conservative algorithm and thin-line safety assertions.
- Reworked: bounded service reads, fingerprint tests, storage spy, standalone
  benchmark and seven race orders now target IMPROVEMENT_INPUT instead of REVIEW.
- Obsolete: REVIEW-as-analysis authority and its blocked-draft documentation.
No intermediate commit was created and no whole-worktree reset was used.

The original RGB254-on-RGB255 blocker goes through actual candidate normalization.
Its private PNG preserves the distinction; exterior removal leaves the thin line
opaque. Tests also cover enclosed white, off-white, gradient/photo abstention,
existing alpha, orientation, metadata stripping, unchanged REVIEW encoding, legacy
candidates, duplicate avoidance, authorization/CSRF, private delivery, audit rollback
and candidate/corrected storage/insert failures with exact file+row preservation.
`LogoImprovementReviewInputTest.php` retains the original blocker assertion and
instruments every binary open. `LosslessLogoInputTest.php` compares every RGB/alpha
pixel against the bounded fixture. `LosslessLogoAtomicTest.php` tests the new role's
transactional lifecycle, including that profile photos do not acquire it.

For a reproducible generation-only resource sample, create `candidate white 800 800`
with `LogoImprovementFixture.php` in one process, then time
`LogoImprovementBenchmark.php <submission-id>` in another against disposable QA
storage/database. The 800×800 PNG was 4362 bytes; generation took 324.38 ms and the
process peaked at 44384256 bytes RSS (42.33 MiB). Original creation/normalization is
excluded from the measured process. No full-resolution source is reopened.
User-facing moderation activation and MR9 remain separate work.

QA note: `AuditMp01HStaticReadinessTest.php` reports the pre-existing
`hidden_productive_wiring_zero` failure on both this worktree and a separate
archive of accepted HEAD eeea182. MR8 event-policy regression and MP01F/G contract
checks pass; no audit implementation or catalog changed in MR8.1.
The additive migration was applied locally; Director row and file snapshots
remain byte-for-byte identical before/after.

## MR9 — audited replacement requests

An internal reviewer can request another image for a READY/PENDING_REVIEW photo
or physician personal logo. `POST /api/internal/media-review/request-replacement.php`
requires canonical active account/session/current credentials, INTERNAL_OPERATOR,
exact `media_review_request_replacement`, R1, canonical CSRF and mandatory audit.
Read/approve/improve/design capabilities alone never authorize the decision. No real
grant is provisioned and physician/customer sessions cannot make this decision.

The transaction locks physician → submission → AUTO_PROPOSAL relationships, checks
eligibility, then changes only editorial authority to READY/NEEDS_WORK and persists
reason, optional user-facing feedback and decision time. The canonical
`MEDIA_REVIEW_REPLACEMENT_REQUESTED` event records submission, physician, purpose and
controlled reason code, never arbitrary feedback. Audit failure rolls back the
whole decision. A repeated request returns 409 without another event or timestamp.
SOURCE, CORRECTED, IMPROVEMENT_INPUT and REVIEW remain history. An AUTO_PROPOSAL
relationship is removed atomically; its binary is deleted only after confirmed
commit. Uncertain commits preserve bytes for reconciliation. No public URL or
media_asset changes, and the existing pending query naturally excludes NEEDS_WORK.

Apply `2026_09_11_media_review_replacement_feedback.sql` once. It adds nullable
`review_reason_code VARCHAR(32)`, `review_feedback VARCHAR(400)` and
`review_decided_at DATETIME(6)`, plus a controlled reason CHECK. Existing created_at
is widened to DATETIME(6) with CURRENT_TIMESTAMP(6) so successive replacement
uploads cannot be misordered by random UUIDs within one second. Legacy ties use
decision time before UUID; an active legacy pending decision outranks its withdrawn
predecessors. This precision correction was required by the real owner HTTP test.
There are no new
indexes, workflow tables or parent/supersedes relationships. The server validates
exact codes WRONG_MEDIA_TYPE, QUALITY_INSUFFICIENT, CONTENT_NOT_APPROPRIATE and
OTHER. Feedback is trimmed valid UTF-8 with at most 400 Unicode code points. It is
plain text, including any literal angle brackets, and must never be inserted with
innerHTML. HTML-like text is returned as JSON text; the advisor textarea uses value.

Owner candidate GET prioritizes active PENDING. Without one, the latest relevant
NEEDS_WORK submission returns only its ID, technical/review status, controlled
reason and Spanish label, feedback and timestamps. No reviewer identity, audit,
capabilities, storage paths or SOURCE metadata is returned in this feedback state.
The existing authenticated owner scope remains authority; a client doctor ID cannot
select another physician's feedback. A new owner upload creates a NEW pending
submission and the old NEEDS_WORK row/files remain historical. Existing one-pending
uniqueness stays enforced independently for photo and logo. Photos get SOURCE and
REVIEW; logos additionally get IMPROVEMENT_INPUT.

The advisor detail includes a neutral Solicitar reemplazo action gated by capability,
a required reason selector and optional Indicaciones para el usuario. Cancel makes
no change; busy state prevents overlapping decisions. Success closes the detail,
reloads the database-backed queue and says “Se solicitó una nueva imagen al usuario.”
This does not claim notification delivery. Current physician upload UIs are not
activated for moderation. REJECTED remains reserved/unimplemented. Notifications,
email, batching, gallery moderation and MR10 are not implemented.

Focused MR9 QA uses disposable MySQL/Valkey, synthetic accounts and synthetic media.
It covers both owner replacement lifecycles, a photograph-like logo with human
WRONG_MEDIA_TYPE reason, 400-character Unicode boundaries, escaped/plain text,
capability/session/CSRF denial, audit and commit failures, proposal cleanup,
14 deterministic race orders, and eight viewport/purpose combinations at
1440×900, 1366×768, 390×844 and 320×740. The prior 35 audit policy rows are checked
by SHA-256; the one new event extends catalog counts without changing those rows.

The migration is applied locally. Director baseline comparison removes only the
three newly added NULL fields and normalizes the `.000000` date representation;
all pre-existing row data and public/private file hashes match exactly. A separate
check confirms zero populated feedback fields in real submissions and unchanged
real grant rows, with zero active replacement grants. No real candidate decision
was used for QA. MR8 HTTP/visual regression also passes after MR9.
