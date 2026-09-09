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

Production composition remains fail-closed. Inspection of `IdentityHttpComposition`
and `FailClosedAuthorizationService` found account/profile/group authorization,
not an established internal-operator grant resolver. MR2 does not relabel those
memberships as staff or create another authentication system. A production staff
session/account/capability resolver is required before real reviewer activation.

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
