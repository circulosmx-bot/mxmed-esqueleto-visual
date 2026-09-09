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
