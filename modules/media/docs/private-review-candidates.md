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
