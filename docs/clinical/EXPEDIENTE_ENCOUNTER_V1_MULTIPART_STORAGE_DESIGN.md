# Expediente Clínico — V1 multipart clinical document storage design

```text
CHAPTER=CLIN-REFORM-PHASE2-M6-MULTI01
DESIGN_SCOPE=DOCUMENTATION_ONLY
BASELINE_HEAD=f01b2f60c6363b10b43b931b20f42fb3348acdf1
PHASE_2_M6_MULTI01=ACCEPTED
M6_MULTI01_ACCEPTED_HEAD=e61f1c9fe0ba0bedad3f33e99399c5321f3ac5aa
MULTIPART_DESIGN=ACCEPTED
MULTIPART_DESIGN_STATUS=ACCEPTED
PHASE_2_M6_MULTI02A=ACCEPTED
M6_MULTI02A_ACCEPTED_HEAD=3e06decd0f96771248da567d7e5c92186506f34f
MULTIPART_SCHEMA_FOUNDATION=ACCEPTED
PHASE_2_M6_MULTI02B=ACCEPTED
M6_MULTI02B_EVIDENCE_COMMIT=4c125344b97009c236e243b86c4290844c229ed6
MIGRATION_05_STATUS=ACCEPTED_REPOSITORY_PHYSICAL_REHEARSAL
MIGRATION_05_PHYSICAL_REHEARSAL=ACCEPTED
MIGRATION_05_TARGET_MYSQL96=PASS
MULTIPART_SCHEMA_READINESS_PHYSICAL=PASS
CURRENT_ACCEPTED_HEAD=99422dfb601282a5c5eceaafa6a02c34dc8b183b
PHASE_2_M6_MULTI03A=ACCEPTED
M6_MULTI03A_CANDIDATE_HEAD=1e948d5c6250259b54f72e92122a42e246de999d
MULTI03A_BLOCKER=NONE
PHASE_2_M6_MULTI03A_R1=ACCEPTED
M6_MULTI03A_R1_ACCEPTED_HEAD=683f99fabbd6617f58fff50eb8fb78b891b26213
PHASE_2_M6_MULTI03B=ACCEPTED
M6_MULTI03B_CANDIDATE_HEAD=b86bf10499e7e745e31ac48d6cb41f9bc45e8597
MULTI03B_BLOCKER=NONE
PHASE_2_M6_MULTI03B_R1=ACCEPTED
M6_MULTI03B_R1_ACCEPTED_HEAD=77eff8ba515b1a5b26a6d8c30b403325f4aafb8f
PHASE_2_M6_MULTI03C=ACCEPTED
M6_MULTI03C_EVIDENCE_COMMIT=a614f7347ebca01f94a43da48bc987b0d8a6984c
PHASE_2_M6_MULTI04A=ACCEPTED
M6_MULTI04A_ACCEPTED_HEAD=99422dfb601282a5c5eceaafa6a02c34dc8b183b
PHASE_2_M6_MULTI04B=READY_FOR_CODE_REVIEW
PRIVATE_BINARY_HTTP_CONTROLLER=IMPLEMENTED_PENDING_REVIEW
PRIVATE_BINARY_HTTP_ROUTE=IMPLEMENTED_PENDING_REVIEW
PRIVATE_BINARY_HTTP_RANGE_SUPPORT=false
MULTI04B_PHYSICAL_HTTP_QA_EXECUTED=false
PRIVATE_BINARY_ROUTE_WORKING_DB_ACTIVE=false
PRIVATE_BINARY_AUTHORIZATION_SERVICE=ACCEPTED
PRIVATE_BINARY_INTEGRITY_RETRIEVAL=ACCEPTED
MULTI04A_HTTP_WIRING_ACTIVE=GATED_SOURCE_ONLY
MULTIPART_COORDINATION_PHYSICAL_QA=ACCEPTED
MULTIPART_REAL_MYSQL_TRANSACTION_QA=PASS
MULTIPART_REAL_FILESYSTEM_COORDINATION_QA=PASS
STAGING_WITHOUT_COORDINATION_DETECTED=true
FINALIZED_RESOURCE_WITH_STAGING_RETAINED_DETECTED=true
CONFIRMED_INITIAL_COORDINATION_ROLLBACK_CLEANS_STAGING=true
AMBIGUOUS_INITIAL_COORDINATION_COMMIT_PRESERVES_STAGING=true
MULTIPART_COORDINATION_REPOSITORY=ACCEPTED
MULTIPART_DURABLE_IDEMPOTENCY_INTEGRATION=ACCEPTED
MULTIPART_TRANSACTION_ORCHESTRATION=ACCEPTED
FINALIZATION_PRESERVES_STAGING=true
STAGING_CLEANUP_SEPARATE_FROM_FINALIZATION=true
PRIVATE_BINARY_STORAGE_ADAPTER=ACCEPTED
PRIVATE_STAGING_PRIMITIVE=ACCEPTED
CREATE_ONLY_FINALIZATION_PRIMITIVE=ACCEPTED
RECONCILIATION_PRIMITIVES=ACCEPTED
V1_MULTIPART_DOCUMENT_WRITE=DEFERRED_FAIL_CLOSED
MULTIPART_ACTIVE_CALLER_BLOCKER=true
M6_COHORT_RUNTIME_ROUTING_ACTIVE=false
WORKING_MXMED_DB_CONNECTED=false
DB_SCHEMA_PHYSICALLY_CHANGED=false
DB_DATA_CHANGED=false
```

MULTI01 defines the accepted physical contract needed before V1 may accept a multipart clinical document. MULTI02A adds only the repository migration and read-only schema-readiness authority for the two accepted tables, and MULTI02B supplies accepted disposable MySQL 9.6 physical evidence. Accepted MULTI03A/R1 provides repository-only primitives for private filesystem staging, create-only finalization, read-only access, quarantine, inventory and pure reconciliation. It does not write coordination/manifest tables, add routes, activate cohort routing or remove the current `503/V1_MULTIPART_STORAGE_NOT_READY` response. The design separates the database document authority from the binary object authority and requires both to agree before a document becomes visible.

## 1. Current legacy upload behavior

The source audit covered `clinical_uploads_root_dir()`, `clinical_uploads_relative_dir()`, `clinical_optimize_uploaded_image()`, `clinical_store_uploaded_file()` and `clinical_documents_gateway_save_upload()` in `api/clinical/index.php`.

- The absolute root is `<repository>/storage/clinical_uploads`; the returned relative root is `/storage/clinical_uploads`.
- The application-level limit is 25 MiB (`25 * 1024 * 1024`) for images and PDFs. The documented local server script currently starts PHP with `upload_max_filesize=10M` and `post_max_size=12M`, so that runtime can reject earlier; a future implementation must align transport and application limits rather than silently advertise more than the server accepts.
- MIME is detected from temporary-file contents with `finfo(FILEINFO_MIME_TYPE)`. Allowed types are JPEG, PNG, WEBP and PDF.
- PDFs move the PHP temporary upload directly to `YYYY/MM/<document_uuid>-orig.pdf`. The payload stores `path`, `url`, MIME, bytes and the original filename.
- Images are decoded with GD, JPEG EXIF orientation is applied when available, the long edge is limited to 2048 px, and a 480 px thumbnail is generated. Output is transparent PNG when required, otherwise WEBP when supported or JPEG as fallback. Quality is 80 for the optimized image and 75 for the thumbnail.
- The image pipeline writes `<document_uuid>-opt.<ext>` and `<document_uuid>-thumb.<ext>`. It records original byte count, dimensions and MIME, but does not retain the original image bytes or a path to them.
- GD re-encoding normally omits EXIF, GPS, IPTC and XMP from generated derivatives. Source currently reads only JPEG orientation; it does not explicitly inspect, preserve or prove removal of all metadata classes. The original image temporary file is not retained.
- The gateway generates a document UUID, writes the binary or derivatives, embeds their relative paths in `payload_json`, and only then performs the `clinical_documents` `INSERT`. There is no database transaction spanning storage, no SHA-256 manifest and no compensation if the database insert or a later variant write fails.
- The documented local servers use the repository root as `php -S -t <repository>`, and the root `.htaccess` contains no deny rule for `storage`. Therefore a created `/storage/clinical_uploads/...` path is directly addressable in that runtime. Production packaging excludes uploads, so the presence of the same files in a deployed image is not proved; the legacy contract nevertheless treats a browser path as storage identity.

```text
CURRENT_LEGACY_UPLOAD_PUBLIC_PATH_RISK=true
CURRENT_LEGACY_IMAGE_ORIGINAL_RETAINED=false
CURRENT_LEGACY_BINARY_SHA256_PERSISTED=false
CURRENT_LEGACY_STORAGE_COMPENSATION=false
```

## 2. Identified risks

1. A PDF or image derivative can remain without a database document when the later insert fails.
2. The optimized image can remain alone if thumbnail generation fails.
3. A committed row can later point to a missing or altered file without a stored hash detecting it.
4. Relative public paths bypass document-level authorization when the web server serves the path directly.
5. Images lose their original evidentiary bytes, while the derivative becomes the only retained content.
6. Original filenames are persisted without a documented privacy or display-sanitization contract.
7. No durable idempotency binds the uploaded bytes; a lost response can create a second document and second binary.
8. No lifecycle distinguishes temporary, finalized and orphaned objects.
9. The filesystem and MySQL cannot commit atomically; crash windows are unclassified.
10. Clinical storage is mixed conceptually with profile/public-media paths despite stronger clinical privacy and immutability requirements.

## 3. V1 multipart authority model

A V1 multipart document has two coordinated durable authorities:

```text
DATABASE DOCUMENT AUTHORITY
  clinical_documents + encounter/patient/doctor context + idempotency result

BINARY OBJECT AUTHORITY
  private immutable object + relational integrity manifest
```

The final invariant is:

```text
COMMITTED_MULTIPART_DOCUMENT
  => DB_ROW_EXISTS
  AND FINAL_BINARY_MANIFEST_EXISTS
  AND BINARY_EXISTS
  AND BINARY_SHA256_MATCHES
  AND PATIENT_ENCOUNTER_CONTEXT_MATCHES
```

The binary belongs to the document. Context always follows `binary -> document UUID -> patient -> encounter when encounter-owned`; a storage record cannot redefine doctor, patient or encounter authority. A browser URL is never canonical identity.

```text
PARTIAL_UPLOAD_VISIBLE_AS_CLINICAL_DOCUMENT=false
CLINICAL_BINARY_STORAGE_PRIVATE=true
PRIVATE_STORAGE_REQUIRED=true
```

## 4. Staging lifecycle

The primary design is **durable private staging with transactional metadata reservation and immutable finalization**.

Storage lifecycle states are operational, not clinical statuses:

| State | Meaning | Clinical visibility |
| --- | --- | --- |
| `STAGED` | Validated upload exists under a random private staging key; hash and bounded metadata are recorded. | none |
| `FINALIZED` | Immutable final object exists and the committed manifest links it to one committed document. | authorized retrieval only |
| `ORPHANED` | An object exists without a committed authoritative document/manifest. | none |
| `RECONCILIATION_REQUIRED` | Storage and database observations disagree or immediate compensation was inconclusive. | fail closed |

Staging is outside every web document root, uses server-generated random names, rejects symlinks, and grants only the application service account access. It has a bounded lifetime and a durable internal coordination record. `STAGED` data is neither returned by document APIs nor accepted by the download controller.

```text
STAGING_FILES_NOT_PUBLIC=true
STAGING_IS_NON_AUTHORITATIVE=true
SERVER_GENERATED_STORAGE_KEY=true
PATH_TRAVERSAL_FROM_FILENAME=false
```

## 5. Finalization and commit sequence

1. Authenticate the actor and authorize the doctor/patient/encounter operation before accepting durable content.
2. Require and validate `Idempotency-Key`; normalize logical metadata and the server-selected operation.
3. Stream the upload into private staging with a hard 25 MiB limit while calculating SHA-256 and byte length. Detect MIME from the staged bytes and validate image dimensions/decodability when applicable. Generate image derivatives in staging and calculate integrity metadata before holding the main document transaction.
4. Persist an internal staging coordination row correlated to the scoped idempotency command. The row contains the semantic request hash, binary hash, staging key, planned final key, expiry and `STAGED` state; it is not a clinical document and cannot decide replay or conflict. The existing `clinical_idempotency_requests` ledger remains the sole durable command-idempotency authority.
5. For a replay already committed with the same semantic hash, discard any redundant temporary stream and return the existing resource. A changed hash or semantics fails with `IDEMPOTENCY_KEY_REUSED`.
6. Begin the canonical database transaction and lock/reserve the durable idempotency command. Revalidate doctor/patient/encounter authority and operation policy inside the transaction.
7. Create the document metadata and uncommitted final binary manifest. Neither row is visible outside the transaction.
8. Finalize the original to its immutable private key with create-if-absent semantics. On one filesystem this is an atomic rename on the same volume after fsync; object storage uses conditional server-side copy/put and verifies length/hash. Generate and finalize derivatives as separate immutable variants.
9. Verify every required finalized object from storage, update the manifest and coordination state to `FINALIZED`, bind the idempotency result, and commit the database transaction.
10. Return the canonical document. Remove the staging object only after successful commit; cleanup failure is telemetry, not document rollback, because the final object and row are already authoritative.

Final keys are opaque and contain no PHI, for example `clinical/YYYY/MM/<document_uuid>/<binary_uuid>-original`. Finalization must refuse overwrite. If the exact key already exists, its hash must match a proven replay; otherwise classify an unexpected duplicate and fail closed.

## 6. Compensation for failure windows

| Window | Required outcome | Compensation / recovery |
| --- | --- | --- |
| F1 validation fails before staging | no DB row; no final binary | delete the temporary stream immediately; record only non-PHI rejection telemetry |
| F2 staging succeeds, DB transaction cannot start | no committed document | retain `STAGED` coordination briefly for safe retry, or delete it; bounded cleanup removes it after TTL |
| F3 DB operation fails before final object | DB rollback; no final binary | return coordination to retryable `STAGED`; remove immediately on permanent validation failure, otherwise TTL cleanup |
| F4 final move/copy fails | DB rollback; no committed document | verify no final object; keep or clean staging according to retryability; mark `RECONCILIATION_REQUIRED` if storage result is uncertain |
| F5 final object succeeds but DB commit fails | final object is not authoritative | rollback; attempt conditional deletion or quarantine of the exact uncommitted final key; persist `ORPHANED/RECONCILIATION_REQUIRED` in a new short transaction if cleanup is not proved; retrieval cannot expose it because no committed manifest exists |
| F6 response is lost after commit | same document and binary returned | retry calculates the same SHA and semantic hash; durable idempotency returns the committed document without staging/finalizing a second object |
| F7 process crashes during storage/DB coordination | no partial clinical visibility | reconciler compares staging coordination, final keyspace and committed manifests; it classifies stale staged, orphan final, missing final and mismatch states deterministically |

The implementation must use narrow, idempotent compensation steps. It must never delete a final object referenced by a committed clinical manifest. Uncertain storage results always fail closed and enter reconciliation.

## 7. SHA-256 and idempotency contract

SHA-256 is calculated over the exact accepted upload bytes before finalization. The canonical semantic request hash includes at least:

```text
doctor_id
patient_id
encounter_id or authorized patient context
operation
document_type
normalized logical metadata
binary_sha256
binary_byte_length
binary_mime
canonicalization_version
```

The original filename is not identity. Same scoped key plus the same semantics and bytes returns the same committed document UUID and storage manifest. The same key with a changed hash, length, MIME or logical semantics returns the canonical `IDEMPOTENCY_KEY_REUSED` conflict. A retry after restart uses the durable ledger/coordination state, never process memory.

Identical bytes under a different idempotency command may intentionally represent a different clinical document. Content addressing can support integrity, but global document deduplication is not required or authorized.

```text
BINARY_SHA256_REQUIRED=true
IDEMPOTENCY_INCLUDES_BINARY_HASH=true
GLOBAL_DOCUMENT_DEDUPLICATION_NOT_REQUIRED=true
```

## 8. Private storage model

Final clinical storage is configured outside the static document root. The storage adapter exposes operations such as `stage`, `finalizeCreateOnly`, `stat`, `openReadStream`, `quarantine` and `deleteUncommitted`; application code never constructs a public URL.

Each key is server-generated and opaque. Suggested shape:

```text
clinical/YYYY/MM/<document_uuid>/<binary_uuid>-<variant>
```

Keys cannot include patient/doctor names, diagnosis, title or client filename. Permissions deny direct web-server access, directory listing and execution. PDF/image bytes are delivered only through the authenticated controller. Encryption at rest and backup policy belong to the future infrastructure implementation review; they must use the same private-authority boundary and cannot weaken immutability.

## 9. Authenticated retrieval model

The future conceptual route is `GET /clinical/documents/{document_uuid}/binary/{variant}`. It must:

1. require an authenticated application session;
2. resolve the document and stored context without trusting client doctor/patient values;
3. authorize the actor against the document's doctor/patient/encounter scope;
4. require a `FINALIZED` manifest and exact object existence;
5. verify stored length and, according to bounded verification policy, SHA-256 before or while streaming;
6. set a safe server-selected `Content-Type`, `X-Content-Type-Options: nosniff`, private cache policy and sanitized `Content-Disposition`;
7. return a generic denial/not-found response without exposing absolute paths or private keys.

Missing binary or hash mismatch fails closed, emits non-PHI correlation telemetry and queues reconciliation. Signed/public static URLs are outside this initial contract and require separate authorization.

```text
AUTHENTICATED_BINARY_RETRIEVAL_DESIGNED=true
```

## 10. Image and PDF handling

The accepted initial types remain PDF, JPEG, PNG and WEBP, with a 25 MiB maximum. MIME comes from content, not browser headers or filename extension. Images also require safe decode, positive dimensions and accepted maximum dimensions; decompression/resource limits must bound processing.

For images:

- `original` is the exact accepted upload bytes, SHA-bound and immutable; it is the evidentiary binary.
- `display` is auto-oriented, bounded and re-encoded for UI use.
- `thumbnail` is a smaller derivative.
- Every variant receives its own storage key, MIME, byte length and SHA-256. Derivatives reference the same document and original binary.
- Display/thumbnail output must strip EXIF, GPS, IPTC and XMP. The future implementation must prove this with fixtures rather than assume GD behavior.
- The immutable original may contain metadata needed to prove original bytes, including sensitive GPS. It remains private, is never served as the default preview, and is available only under explicit authorized-original retrieval/retention policy. Metadata extraction into logs or user-visible payloads is prohibited.

For PDFs, retain the exact validated original, persist hash/length/MIME, store it immutably, prevent execution and derive no path from filename. This design does not add OCR, parsing or content extraction. Malware/content-scanning policy can be added by a later security review without changing the core authority invariant; until accepted, validation is MIME/size/structure policy only.

## 11. Immutable storage rules

Every final key is create-only. Application code cannot overwrite, mutate or reuse it for changed bytes. A binary attached to a committed generated/signed/historical document remains immutable even if the document is later superseded or voided.

Original filename may be retained as sanitized display metadata only: bounded length, control/path characters removed, never used for MIME, key construction or authorization. The authoritative fields are opaque storage key, variant, SHA-256, MIME and byte length.

```text
BINARY_IMMUTABLE_AFTER_COMMIT=true
FINAL_STORAGE_OVERWRITE_ALLOWED=false
```

## 12. Post-close result behavior

The storage pipeline delegates clinical policy to the accepted operation contract. A result class allowed by policy may use `CREATE_POST_ENCOUNTER_RESULT` after an encounter is CLOSED. The document and binary are new append-only artifacts linked to the closed encounter; they do not reopen it, mutate its terminal audit fields or rewrite earlier content. Same-command retry returns one result document and one binary set.

An ordinary CLOSED-encounter document remains prohibited unless its accepted operation policy permits it. Binary availability cannot bypass clinical lifecycle policy.

```text
LATE_RESULT_BINARY_DOES_NOT_REOPEN_ENCOUNTER=true
```

## 13. Amendment and replacement behavior

`CREATE_DOCUMENT_AMENDMENT_OR_REPLACEMENT` creates a new document/revision and new immutable binary identity. The original document, manifest and object remain unchanged. `clinical_document_revisions` supplies append-only lineage between original/superseded/new document rows. The replacement's idempotency semantics include its new binary SHA and the target document identity.

No amendment changes a storage key in place. A display derivative can be regenerated only as a separately versioned variant under a new key with explicit provenance; silent replacement is prohibited.

## 14. Orphan reconciliation

A read-only reconciliation job inventories database manifests, coordination rows and private storage metadata. It classifies, without auto-repair:

- final binary with no committed document/manifest;
- committed document manifest whose binary is missing;
- stored length or SHA mismatch;
- stale `STAGED` upload;
- duplicate/unexpected final storage key;
- `FINALIZED` coordination without matching committed idempotency result;
- committed multipart document missing a required original variant.

Each finding records type, opaque IDs, timestamps and correlation ID without names, diagnoses, titles or filenames. A separately authorized operator workflow decides quarantine, restore or repair. The reconciler cannot auto-delete any binary associated with a committed clinical row.

```text
ORPHAN_RECONCILIATION_DESIGNED=true
RECONCILER_DESTRUCTIVE_CLINICAL_CLEANUP=false
```

## 15. Stale-staging cleanup

A scheduled cleanup may remove only coordination rows still `STAGED` beyond a configured TTL after proving all of the following: no committed idempotency resource, no committed document binary manifest, no final authoritative object and no active lease/heartbeat. Cleanup uses a compare-and-set state/lease to avoid racing an upload or retry. If any proof is unavailable, it marks `RECONCILIATION_REQUIRED` and preserves bytes.

TTL duration, job cadence and retry/backoff are deployment settings to approve during implementation review; they do not affect clinical status. The job emits aggregate counts and opaque correlation IDs only.

```text
STALE_STAGING_CLEANUP_DESIGNED=true
```

## 16. Failure telemetry

Required counters and structured events:

- multipart validation/staging failures by non-PHI reason;
- finalization failures;
- final-object-success/DB-commit-failure events;
- orphan detections;
- committed manifest with missing binary;
- hash/length mismatch;
- stale staging detected/cleaned/deferred;
- idempotency replay;
- idempotency conflict;
- unauthorized retrieval denial;
- duplicate final-key refusal.

Logs use request/correlation, document UUID or binary UUID only where operationally required. They exclude patient/doctor names, diagnosis, title, original filename, raw storage path, clinical content and idempotency secret. Alerts must distinguish expected validation rejections from integrity incidents.

## 17. Database and schema impact

The current `payload_json` can carry paths and loose metadata, but it cannot enforce uniqueness, immutability, referential integrity, reconciliation queries or hash-based completeness. A schema change is required. A future additive migration should propose these exact logical structures (final SQL and engine-specific checks remain a separately reviewed implementation):

### `clinical_binary_uploads` — internal coordination, never a clinical artifact

| Column / constraint | Contract |
| --- | --- |
| `upload_id CHAR(36)` primary key | opaque server UUID |
| `operation_type VARCHAR(64)`, `doctor_id VARCHAR(64)`, `context_type VARCHAR(16)`, `context_id VARCHAR(128)` | same command scope vocabulary as the existing ledger |
| `idempotency_key_digest CHAR(64)` | one-way SHA-256 correlation only; never store/log the raw key here |
| `idempotency_request_id BIGINT UNSIGNED NULL` FK `RESTRICT` | link to `clinical_idempotency_requests.request_id` once reserved; the existing ledger remains authoritative |
| `semantic_request_hash CHAR(64)` | canonical request SHA-256 including binary integrity fields |
| `binary_sha256 CHAR(64)`, `byte_length BIGINT UNSIGNED`, `mime_type VARCHAR(100)` | accepted source integrity |
| `staging_key VARCHAR(512)` unique, `planned_final_prefix VARCHAR(512)` | opaque private keys/prefix; no PHI |
| `storage_state VARCHAR(32)` | checked to `STAGED`, `FINALIZED`, `ORPHANED`, `RECONCILIATION_REQUIRED` |
| `document_id BIGINT UNSIGNED NULL` FK `RESTRICT` | set only for a successful finalized command |
| `created_at`, `updated_at`, `expires_at`, `lease_until` DATETIME; `last_error_code VARCHAR(64) NULL` | bounded cleanup/retry metadata without clinical content |

Multiple processes may stage the same command concurrently. The unique accepted command still comes from `clinical_idempotency_requests`; losers that observe a committed replay remove their redundant staging object. The coordination table never returns a resource or changes the existing conflict rule.

### `clinical_document_binaries` — immutable committed manifest

| Column / constraint | Contract |
| --- | --- |
| `binary_id BIGINT UNSIGNED` primary key; `binary_uuid CHAR(36)` unique | durable server identity |
| `document_id BIGINT UNSIGNED` FK `RESTRICT` | owning canonical `clinical_documents.id` |
| `variant_role VARCHAR(16)`, `variant_version SMALLINT UNSIGNED` | checked role `ORIGINAL`, `DISPLAY`, `THUMBNAIL`; unique `(document_id, variant_role, variant_version)` |
| `storage_key VARCHAR(512)` unique | private opaque final key |
| `sha256 CHAR(64)`, `byte_length BIGINT UNSIGNED`, `mime_type VARCHAR(100)` | mandatory immutable integrity metadata |
| `width_px INT UNSIGNED NULL`, `height_px INT UNSIGNED NULL` | image variant metadata |
| `source_filename VARCHAR(255) NULL` | sanitized display metadata only; normally populated on `ORIGINAL` |
| `created_at DATETIME`, `finalized_at DATETIME` | immutable audit timestamps |

The committed manifest contains only finalized binaries; operational `STAGED/ORPHANED` state lives in the coordination table. `payload_json` may project non-authoritative UI metadata but cannot replace these rows. The migration must add foreign keys with `ON UPDATE RESTRICT ON DELETE RESTRICT`, drift checks, constrained states/roles and indexes for reconciliation. It must not backfill a hash or private key by guessing from legacy browser URLs.

```text
MULTIPART_SCHEMA_CHANGE_REQUIRED=true
PROPOSED_SCHEMA_IMPACT=ADD_CLINICAL_BINARY_UPLOADS_AND_CLINICAL_DOCUMENT_BINARIES
```

## 18. Caller mapping for C04, C05 and C21

| Caller | Current role | Future canonical operation and adapter |
| --- | --- | --- |
| C04 consent identity attachment | legacy multipart scoped document create | `CREATE_ENCOUNTER_DOCUMENT` when encounter-owned; the adapter supplies the accepted consent/identity document class and canonical encounter context, then uses this one storage service |
| C05 order upload | legacy scoped create/replace | new order attachment uses `CREATE_ENCOUNTER_DOCUMENT`; a replacement uses `CREATE_DOCUMENT_AMENDMENT_OR_REPLACEMENT` and preserves the original binary |
| C05 result/diagnostic upload | legacy result/order upload | result permitted after close uses `CREATE_POST_ENCOUNTER_RESULT`; otherwise accepted policy selects `CREATE_ENCOUNTER_DOCUMENT` or rejects it |
| C21 note/signature capture | token upload routed to legacy document gateway | remains a source adapter (mobile/QR/canvas) into the same canonical V1 storage service; it does not own a parallel binary persistence architecture |

All adapters must resolve doctor authority from session/service authorization and patient/encounter authority from canonical stored context. None may reactivate client-controlled doctor scope or fall back to a legacy write after a V1 failure.

## 19. Implementation chapter boundaries

MULTI01 is accepted at `e61f1c9fe0ba0bedad3f33e99399c5321f3ac5aa`. Its implementation boundary is split into separately reviewed chapters, in order:

1. additive schema/migration plus drift/readiness checks for the two proposed structures;
2. private storage adapter, staging/finalization and reconciliation command;
3. V1 multipart service integrated with the accepted idempotency transaction;
4. authenticated retrieval controller;
5. C04/C05/C21 adapters with legacy guards retained until each is accepted;
6. isolated synthetic QA for MPU01–MPU20, including crash injection and two-client retry;
7. packaging/private-volume and operational cleanup/monitoring configuration;
8. Director acceptance before any routing activation, working-database migration or cutover.

The implementation must preserve `503/V1_MULTIPART_STORAGE_NOT_READY` until schema, service, adapters and all multipart QA are accepted. `GLOBAL_CLINICAL_GET_DDL_REMOVAL` remains a separate later blocker.

### MULTI02A/MULTI02B accepted foundation and MULTI03A/R1 accepted storage and MULTI03B coordination candidate

MULTI02A is accepted at `3e06decd0f96771248da567d7e5c92186506f34f`. `2026_09_19_05_clinical_binary_storage.sql` defines the additive `clinical_binary_uploads` coordination table and immutable `clinical_document_binaries` manifest, with explicit drift checks, named constraints and `RESTRICT` foreign keys. `clinical_multipart_storage_assert_schema_ready()` inspects tables, columns, indexes, foreign keys and checks using `information_schema`; it performs no DDL or DML and is not wired into existing JSON V1 readiness or multipart routing.

MULTI02B physically rehearsed migration 05 on local MySQL `9.6.0` at `127.0.0.1:3306` using only new isolated synthetic databases. Clean apply and identical second apply passed. The physical 20-column upload table, 14-column binary table, all required indexes, `RESTRICT` foreign keys, named checks, positive rows, negative CHECK cases, unique constraints and delete restrictions passed. Readiness passed without schema or row-count change; missing schema and representative column/index/FK/CHECK drift failed closed. The working `mxmed` database was never selected, every disposable database was removed and no target-engine incompatibility was observed.

The first drift-only harness attempt tried to reuse an FK name in the same `ALTER TABLE`; MySQL rejected that harness statement before applying the drift, and teardown completed. The two remaining probes were then executed on fresh disposable databases with separate `DROP` and `ADD` statements and passed. This was not a migration-05 or readiness defect. MULTI02B and that evidence commit are now accepted.

MULTI03A implements only the private binary storage primitives in `api/_lib/clinical_private_binary_storage.php`. The root is caller-supplied, absolute and rejected when equal to or inside the effective document root. Opaque storage keys reject traversal. Staging retains exact bytes with a 25 MiB limit, content MIME detection, SHA-256 and byte count. Finalization uses create-only same-root hard-link semantics, verifies source and final integrity and never overwrites. Read-only stat/stream/inventory, staging-only deletion, final-object quarantine and a pure non-mutating reconciliation classifier are included. Synthetic QA uses only an OS temporary root and removes it completely.

Review of candidate `1e948d5c6250259b54f72e92122a42e246de999d` found that finalization removed staging before the future database transaction could commit. That blocker is resolved by R1; MULTI03A/R1 are accepted at `683f99fabbd6617f58fff50eb8fb78b891b26213`. R1 repairs the accepted distributed-commit order: successful finalization preserves both staging and final paths and returns `staging_retained=true`; only the coordinating service may call `deleteUncommitted()` after successful commit. Finalization failure, integrity failure and final-key collision preserve staging. Quarantine removes the supplied orphan-final path while retaining staging. The adapter still makes no database, authorization or replay decision.

The adapter has no DB or runtime wiring and does not decide authorization, document policy, ownership or command replay. It generates no image derivatives and exposes no public locator. MULTI03B implements table coordination/service integration pending review. Authenticated retrieval, reconciliation scheduling, C04/C05/C21 adapters and multipart HTTP acceptance remain pending. Physical schema success and this storage candidate do not activate multipart.

```text
PHASE_2_M6_MULTI01=ACCEPTED
M6_MULTI01_ACCEPTED_HEAD=e61f1c9fe0ba0bedad3f33e99399c5321f3ac5aa
PHASE_2_M6_MULTI02A=ACCEPTED
M6_MULTI02A_ACCEPTED_HEAD=3e06decd0f96771248da567d7e5c92186506f34f
MULTIPART_SCHEMA_FOUNDATION=ACCEPTED
PHASE_2_M6_MULTI02B=ACCEPTED
M6_MULTI02B_EVIDENCE_COMMIT=4c125344b97009c236e243b86c4290844c229ed6
MIGRATION_05_PHYSICAL_REHEARSAL=ACCEPTED
MIGRATION_05_TARGET_MYSQL96=PASS
MULTIPART_SCHEMA_READINESS_PHYSICAL=PASS
PHASE_2_M6_MULTI03A=ACCEPTED
M6_MULTI03A_CANDIDATE_HEAD=1e948d5c6250259b54f72e92122a42e246de999d
MULTI03A_BLOCKER=NONE
PHASE_2_M6_MULTI03A_R1=ACCEPTED
M6_MULTI03A_R1_ACCEPTED_HEAD=683f99fabbd6617f58fff50eb8fb78b891b26213
PHASE_2_M6_MULTI03B=ACCEPTED
M6_MULTI03B_CANDIDATE_HEAD=b86bf10499e7e745e31ac48d6cb41f9bc45e8597
MULTI03B_BLOCKER=NONE
PHASE_2_M6_MULTI03B_R1=ACCEPTED
M6_MULTI03B_R1_ACCEPTED_HEAD=77eff8ba515b1a5b26a6d8c30b403325f4aafb8f
PHASE_2_M6_MULTI03C=ACCEPTED
M6_MULTI03C_EVIDENCE_COMMIT=a614f7347ebca01f94a43da48bc987b0d8a6984c
PHASE_2_M6_MULTI04A=ACCEPTED
M6_MULTI04A_ACCEPTED_HEAD=99422dfb601282a5c5eceaafa6a02c34dc8b183b
PHASE_2_M6_MULTI04B=READY_FOR_CODE_REVIEW
PRIVATE_BINARY_HTTP_CONTROLLER=IMPLEMENTED_PENDING_REVIEW
PRIVATE_BINARY_HTTP_ROUTE=IMPLEMENTED_PENDING_REVIEW
PRIVATE_BINARY_HTTP_RANGE_SUPPORT=false
MULTI04B_PHYSICAL_HTTP_QA_EXECUTED=false
PRIVATE_BINARY_ROUTE_WORKING_DB_ACTIVE=false
PRIVATE_BINARY_AUTHORIZATION_SERVICE=ACCEPTED
PRIVATE_BINARY_INTEGRITY_RETRIEVAL=ACCEPTED
MULTI04A_HTTP_WIRING_ACTIVE=GATED_SOURCE_ONLY
MULTIPART_COORDINATION_PHYSICAL_QA=ACCEPTED
MULTIPART_REAL_MYSQL_TRANSACTION_QA=PASS
MULTIPART_REAL_FILESYSTEM_COORDINATION_QA=PASS
STAGING_WITHOUT_COORDINATION_DETECTED=true
FINALIZED_RESOURCE_WITH_STAGING_RETAINED_DETECTED=true
CONFIRMED_INITIAL_COORDINATION_ROLLBACK_CLEANS_STAGING=true
AMBIGUOUS_INITIAL_COORDINATION_COMMIT_PRESERVES_STAGING=true
MULTIPART_COORDINATION_REPOSITORY=ACCEPTED
MULTIPART_DURABLE_IDEMPOTENCY_INTEGRATION=ACCEPTED
MULTIPART_TRANSACTION_ORCHESTRATION=ACCEPTED
FINALIZATION_PRESERVES_STAGING=true
STAGING_CLEANUP_SEPARATE_FROM_FINALIZATION=true
PRIVATE_BINARY_STORAGE_ADAPTER=ACCEPTED
PRIVATE_STAGING_PRIMITIVE=ACCEPTED
CREATE_ONLY_FINALIZATION_PRIMITIVE=ACCEPTED
RECONCILIATION_PRIMITIVES=ACCEPTED
MULTI03A_RUNTIME_WIRING_ACTIVE=false
MULTIPART_HTTP_ACCEPTANCE=false
MULTIPART_STORAGE_SERVICE_INTEGRATION=ACCEPTED
C04_MULTIPART_ADAPTER=false
C05_MULTIPART_ADAPTER=false
C21_MULTIPART_ADAPTER=false
PHASE_2_M6_EXECUTION_AUTHORIZED=false
M6_AUTHORIZED=false
WORKING_DB_SCHEMA_STATE=PRE_MIGRATION
BACKUP_RESTORABLE=NOT_YET_PROVEN
CLONE_MIGRATION_REHEARSAL=NOT_YET_EXECUTED
MIGRATION_ACCOUNT_READY=false
WRITE_WINDOW_READY=false
WORKING_MXMED_DB_MIGRATION_AUTHORIZED=false
RUNTIME_CUTOVER_AUTHORIZED=false
PRODUCTION_EXECUTION_AUTHORIZED=false
GLOBAL_CLINICAL_GET_DDL_REMOVAL=PENDING_LATER_IMPL_STAGE
```

## 20. Future implementation QA scenarios

| ID | Scenario | Required proof |
| --- | --- | --- |
| MPU01 | valid PDF create | one committed document, one immutable original, matching MIME/bytes/SHA |
| MPU02 | valid image create | one document plus original/display/thumbnail manifests; original bytes retained |
| MPU03 | MIME mismatch reject | content authority rejects before final object or clinical row |
| MPU04 | oversized reject | more than 25 MiB rejected before final object/clinical row |
| MPU05 | wrong patient/encounter | authorization/context rejection before final commit; no visible artifact |
| MPU06 | same key + same binary replay | same document UUID and binary keys; no duplicates |
| MPU07 | same key + changed binary | `IDEMPOTENCY_KEY_REUSED`; original resource unchanged |
| MPU08 | lost response retry | one document/one binary set after retry and restart |
| MPU09 | staging failure | no DB document, no final binary |
| MPU10 | DB failure before final | transaction rolled back and staging safely cleaned/retained for bounded retry |
| MPU11 | final-storage failure | DB rollback, no visible document, deterministic coordination state |
| MPU12 | final succeeds / DB commit fails | inaccessible orphan classified and reconciler detects it |
| MPU13 | committed DB / binary missing | retrieval fails closed; reconciler and telemetry detect it |
| MPU14 | hash mismatch | retrieval fails closed; reconciler reports mismatch without destructive cleanup |
| MPU15 | valid late result on CLOSED | accepted once with `CREATE_POST_ENCOUNTER_RESULT`; encounter stays CLOSED |
| MPU16 | prohibited normal document on CLOSED | policy rejection before final commit |
| MPU17 | amendment with binary | new document/new key/new hash and lineage; original bytes unchanged |
| MPU18 | unauthorized retrieval | denied without key/path disclosure |
| MPU19 | emergency retry/restart | durable staging/idempotency resumes or replays safely, with one resource |
| MPU20 | stale staging cleanup | expired uncommitted staging removed; committed/final binary untouched |

Each scenario must additionally assert no public static path, no PHI in telemetry and no fallback legacy write. Crash-window scenarios require controllable barriers around staging, finalization and DB commit rather than timing-only tests.

```text
MULTIPART_QA_SCENARIOS_COUNT=20
MULTIPART_DESIGN_COMPLETE=true
MULTIPART_DESIGN=ACCEPTED
MULTIPART_DESIGN_STATUS=ACCEPTED
M6_GO_NO_GO=NO_GO_BLOCKED
```


### MULTI03B — repository-only coordination candidate (2026-09-19)

MULTI03A/R1 is accepted at `683f99fabbd6617f58fff50eb8fb78b891b26213`;
Historical MULTI03B evidence: candidate `b86bf10499e7e745e31ac48d6cb41f9bc45e8597` required R1. MULTI03B/R1 are now accepted at `77eff8ba515b1a5b26a6d8c30b403325f4aafb8f`; physical MULTI03C evidence below passes pending review. Runtime remains inactive.
`clinical_multipart_document_service.php` reuses `ClinicalIdempotencyRepository`
and the accepted private storage adapter. The explicit caller supplies canonical
doctor/patient/context, document metadata, expiration and authorized creation/read
callbacks; it retains clinical policy and readiness responsibility. Callbacks must
not commit or roll back the service-owned transaction.

The service stages bytes and commits a separate STAGED coordination row before
claiming the canonical ledger in the main transaction. Its semantic hash includes
binary SHA-256, size and MIME but excludes generated identifiers. Coordination
stores only the key digest. Creation, verified finalization, immutable ORIGINAL
manifest insertion, FINALIZED coordination and ledger completion precede the same
commit. Staging deletion occurs afterward. Duplicate claims use canonical replay;
changed bytes conflict, and redundant candidates receive narrowly guarded cleanup.

Confirmed rollback after final creation quarantines the final object while retaining
staging. An ambiguous commit outcome preserves both paths for reconciliation rather
than risking a committed final object. Recovery writes are separate, bounded and
best effort. Post-commit staging cleanup failure returns the committed resource with
`cleanup_pending=true` and attempts RECONCILIATION_REQUIRED; it cannot undo success.
Raw database failures are surfaced as a stable coordination error. No derivatives,
public URLs, arbitrary manifest mutation or new ownership authority are introduced.

Evidence consists of semantic pure tests, comment-independent static call-order and
authority checks, and an in-memory PDO spy using synthetic temporary filesystem
objects. The spy is not evidence of actual MySQL locking, SQL execution, isolation,
crash durability or commit behavior. No database connection or physical coordination
QA was performed. Migration 05 and the existing JSON executor/readiness are unchanged.
The accepted MULTI02B evidence remains `4c125344b97009c236e243b86c4290844c229ed6`.

M6 remains `NO_GO_BLOCKED`; HTTP wiring, C04/C05/C21 adapters, downloads, working DB
migration and cohort activation remain unauthorized. V1 multipart still returns
`503/V1_MULTIPART_STORAGE_NOT_READY`. Next: review MULTI03B, then only after acceptance
and separate authorization perform disposable MULTI03C physical coordination QA.

MULTI03B semantic QA: PASS (26 assertions). Orchestration spy: PASS (12 scenarios,
including compensation-DB failure), zero residual temporary roots. Static call-order
and authority QA: PASS. Existing MULTI03A filesystem (32 cases), reconciliation and
static checks; MULTI02A readiness/static; M6 CTRL, GUARD, CALLER01, ROUTE01;
encounter-integrity pure/static; M5 barrier pure/static/concurrent-directory checks:
all PASS. PHP lint and `git diff --check`: PASS. These are repository/pure/filesystem
results only; actual SQL transaction and crash validation remains MULTI03C work.


### MULTI03B-R1 — staging recovery completeness (2026-09-19)

Review found two inventory blind spots and unconditional staging retention after a
confirmed initial coordination rollback in MULTI03B. At the R1 chapter, the accepted baseline remained
`683f99fabbd6617f58fff50eb8fb78b891b26213`. MULTI03C authorization now accepts R1
at `77eff8ba515b1a5b26a6d8c30b403325f4aafb8f`.

The initial short transaction now records whether INSERT and COMMIT were attempted.
Failure before any INSERT/COMMIT (including failed BEGIN), or positively confirmed
rollback, permits `deleteUncommitted(staging_key)`. Unknown commit or rollback
outcomes preserve staging and fail closed without entering the main transaction.
Cleanup failure also returns `MULTIPART_STAGED_COORDINATION_FAILED`; it performs no
final-object deletion or automatic DB repair.

Pure reconciliation correlates every inventory `staging/` key against all supplied
coordination rows, including RECONCILIATION_REQUIRED. Unreferenced objects report
`STAGING_WITHOUT_COORDINATION`. Referenced existing staging with supplied committed
resource/manifest evidence reports `STAGING_RETAINED_AFTER_FINALIZATION`, independently
of `HEALTHY_FINALIZED`. Expired, unleased, uncommitted coordinated STAGED rows still
report `STALE_STAGED`. These findings never delete files or mutate database state.
The future evidence supplier remains responsible for canonical committed-resource
facts; the classifier does not query a database.

R1 QA covers failed BEGIN, failed INSERT with rollback, failed short COMMIT with
rollback, ambiguous short COMMIT, failed cleanup and unconfirmed rollback (six spy
scenarios), plus R1-05–R1-08 reconciliation and manifest-evidence coverage. Existing
12 orchestration scenarios and 26 semantic assertions remain. Static protection
compares the main transaction/replay/F5 service code and all storage primitives
byte-for-byte with MULTI03B; only the pure classifier changes in the storage file.
No MySQL connection or physical coordination QA was performed during R1. Migration 05,
idempotency authority, HTTP callers and the fail-closed 503 remain unchanged.
M6 remains NO_GO_BLOCKED. Review R1 before authorizing any separate MULTI03C work.

R1 verification: PASS for initial-coordination spies (6), R1-05–R1-08 pure
reconciliation, original orchestration spies (12), semantic QA (26 assertions),
static authority/order protections, MULTI03A filesystem/reconciliation/static,
MULTI02A readiness/static, M6 CTRL/GUARD/CALLER01/ROUTE01, encounter-integrity,
and M5 barrier suites. PHP lint, shell syntax and `git diff --check`: PASS.
Temporary test storage was removed; no database was connected.


### MULTI03C — isolated physical coordination evidence (2026-09-20)

MULTI03B/R1 is accepted at `77eff8ba515b1a5b26a6d8c30b403325f4aafb8f`.
MULTI03C is accepted with evidence `a614f7347ebca01f94a43da48bc987b0d8a6984c`; it does not activate multipart or M6.
The checkpoint `checkpoint/clinical-pre-multi03c-20260919` was pushed at that exact
source commit. The rehearsal used an exact temporary Git archive, with full file
hashes equal before/after. No application or migration source changed.

Physical target: MySQL **9.6.0**, hostname `192.168.1.10`, endpoint
`127.0.0.1:3306`. Initial `SELECT DATABASE()` returned NULL. The unique database
`mxmed_multi03c_vpeczam1_mysql96` was proven absent before creation and selected
explicitly afterward. No connection selected `mxmed`; no real data or dump was used.
The minimal synthetic prerequisite authorities were `patients_patients`,
`clinical_encounters`, `clinical_documents` and `clinical_document_participants`;
legacy DDL was extracted from frozen source without running the HTTP router.
Migrations 01–05 ran once as prerequisites, excluding the 2026-09-17 attribution
migration. No migration re-audit was performed. Both readiness checks passed and
SHOW CREATE TABLE snapshots were unchanged across readiness calls.

The PHP harness used the accepted service, real PDO/InnoDB, tiny synthetic PDFs
(finfo: application/pdf), and private OS-temporary storage outside the repository
and document root. Its bounded document callback used the service transaction and
supplied UUID, inserted one synthetic document and never committed or rolled back.
No HTTP server, request, network-kill fault, caller adapter or download route ran.

| Scenario | Physical result |
|---|---|
| PC01 | PASS: one document, committed ledger row, FINALIZED coordination with result/ledger references, ORIGINAL version-1 manifest; one final, no staging/quarantine; SHA-256, bytes and MIME matched. |
| PC02 | PASS: response discarded; same bytes/key replayed the database resource. Full row/storage snapshot unchanged; no redundant coordination or second final. |
| PC03 | PASS: changed PDF with same key returned IDEMPOTENCY_KEY_REUSED. Row/storage snapshot unchanged. |
| PC04 | PASS: callback failure rolled back command; one retryable STAGED coordination and staged object remained, no document/ledger/manifest/final added. |
| PC05 | PASS: disposable BEFORE INSERT trigger raised SQLSTATE 45000 at manifest insertion. Document/ledger/manifest rolled back; final absent, staging and quarantine present, coordination ORPHANED. Trigger dropped immediately. |
| PC06 | PASS: real inventory, coordination, manifests and committed-ledger evidence yielded HEALTHY_FINALIZED for PC01; PC05 was not healthy. Full snapshots proved classifier non-mutation. |
| PC07 | PASS: adapter-created staging without coordination yielded STAGING_WITHOUT_COORDINATION; only the QA staging artifact was then removed. |
| PC08 | PASS: temporary duplicate staging plus synthetic finalized coordination referencing PC01 yielded both HEALTHY_FINALIZED and STAGING_RETAINED_AFTER_FINALIZATION; cleanup preserved original DB/final state. |

Baseline counts (documents, ledger, uploads, manifests): **0,0,0,0**.
Final pre-teardown counts: **1,1,3,1**. Upload states: FINALIZED=1, STAGED=1,
ORPHANED=1. Ledger: CREATE_ENCOUNTER_DOCUMENT=1 committed. Manifest: ORIGINAL=1.
Storage baseline (staging, final, quarantine): **0,0,0**; final pre-teardown:
**2,1,1**. The two staged objects are the explained PC04/PC05 recovery candidates.
No failed command committed a clinical resource. Manifest keys remained opaque;
no public URL was created.

Preparation initially hit a local Python archive-option incompatibility before
creating any database or executing a scenario. Only the temporary extraction
harness was corrected to use `git archive`/`tar`; accepted source was untouched.
All mandatory physical scenarios then passed without source repairs or reruns.

Teardown removed the QA trigger, disposable database, private storage, source archive
and temporary harness. Verified residual database count=0, trigger count=0 and
temporary-root count=0. No working DB schema/data, patient, clinical, Agenda or
billing data changed. Multipart retains `503/V1_MULTIPART_STORAGE_NOT_READY`;
C04/C05/C21 adapters remain false and M6 remains `NO_GO_BLOCKED`.

Next is Director/assistant review of this evidence. Only after acceptance and
separate authorization may repository-only authenticated retrieval and controlled
multipart adapters begin. Working-DB migration, feature activation, cutover,
production and PHASE 3 remain unauthorized.


### MULTI04A — internal authorized private-binary retrieval candidate (2026-09-20)

MULTI03C physical evidence is accepted at `a614f7347ebca01f94a43da48bc987b0d8a6984c`.
The accepted implementation head stays `77eff8ba515b1a5b26a6d8c30b403325f4aafb8f`.
`clinical_private_binary_retrieval.php` is READY_FOR_CODE_REVIEW, with no HTTP wiring.

The service accepts server-authoritative doctor/user identifiers and numeric document
ID or UUID. A bounded SELECT-only equivalent of the router's canonical token lookup
projects only required document fields, without loading the router. Active
`patients_doctor_links` is mandatory. Encounter-linked documents additionally require
matching canonical encounter doctor and patient; NULL legacy doctor fails closed.
Patient-level documents require the active link without an invented encounter.
All scope denials return DOCUMENT_BINARY_NOT_FOUND before manifest/storage access.
Voided status remains descriptor metadata, not a storage deletion/visibility policy.

Manifest selection uses document ID, exact ORIGINAL/DISPLAY/THUMBNAIL role and
version 1, without fallback to another variant, payload URLs or legacy files.
Retrieval refuses an existing caller transaction to avoid exposing uncommitted rows.
The final namespace, allowed manifest MIME, byte length and SHA-256 are checked before
stream opening. The opened handle is hashed again, rewound and returned only when
its bytes match; failed handles are closed. The caller must close successful streams.
Missing directories/objects produce DOCUMENT_BINARY_MISSING; integrity failures
produce DOCUMENT_BINARY_INTEGRITY_MISMATCH without quarantine, repair or mutation.

The descriptor contains canonical document context/status and binary metadata, with
sanitized display filename. It excludes keys, absolute paths, private roots and URLs.
The storage primitives, write service, idempotency authority and migrations are unchanged.

Future controller contract (not implemented): authenticated server context; generic
404 for unauthorized access; Content-Type from the allowed manifest MIME;
X-Content-Type-Options: nosniff; Cache-Control: private, no-store;
Content-Disposition: inline|attachment with safely encoded sanitized filename.
No header emission, streaming response, download route or HTTP session extraction
is implemented here. Only SELECT statements run through the injected PDO.

QA uses a PDO fake and synthetic temporary filesystem, never MySQL or real data.
The 22 retrieval cases include R01–R14 plus UUID lookup, voided status, patient mismatch,
missing encounter/document, invalid MIME/variant and uncommitted-caller rejection.
Static checks cover authorization/integrity order, descriptor privacy, no writes/DDL,
no HTTP wiring, and the preserved multipart 503. Physical authentication/streaming
validation remains future work after review and separate authorization.
M6 remains NO_GO_BLOCKED; multipart writes, C04/C05/C21, working-DB migration,
cohort activation, cutover, production and PHASE 3 remain unauthorized.

MULTI04A verification: retrieval QA (22 cases), static QA, MULTI03B semantic/spy/static,
MULTI03A filesystem/reconciliation/static, MULTI02A readiness, M6 CTRL/GUARD/CALLER01/ROUTE01,
encounter-integrity and M5 barrier suites all PASS. PHP lint, shell syntax and
`git diff --check`: PASS. No DB connected; synthetic temporary storage fully removed.


### MULTI04B — gated read-only binary HTTP candidate (2026-09-20)

MULTI04A is accepted at `99422dfb601282a5c5eceaafa6a02c34dc8b183b`, now the accepted
implementation head. MULTI04B is READY_FOR_CODE_REVIEW, not physically HTTP-tested.
One non-overlapping branch in the existing documents block recognizes exactly
GET `/documents/{id_or_uuid}/binary/{variant}` (four segments). Other methods and
existing document routes are unchanged. Server context comes from
`clinical_require_doctor_context`; the existing `clinical_m6_document_route_uses_v1`
gate runs before retrieval loading. Gate false returns generic 404; malformed cohort
configuration continues through the existing fail-closed handling.

The route reuses `clinical_documents_pdo`, then requires explicit
MXMED_CLINICAL_PRIVATE_STORAGE_ROOT. Missing/invalid configuration maps to a path-free
503 PRIVATE_BINARY_STORAGE_NOT_CONFIGURED. No root fallback is introduced.
A necessary additive constructor option opens only existing private storage without
mkdir/chmod: GET cannot use the upload constructor's directory creation behavior.
The default upload construction behavior and all storage methods remain unchanged.

`clinical_private_binary_http.php` isolates pure headers/error mapping and bounded
transfer. Metadata comes only from the accepted verified retrieval descriptor.
Headers: manifest Content-Type, nosniff, private/no-store, verified Content-Length,
and inline disposition with generated document.pdf/jpg/png/webp. Stored filenames
are never concatenated into headers. Retrieval authorization remains solely MULTI04A.
The controller sends the verified stream in at most 64-KiB chunks without reopening
paths; finally closes it on success, disconnect and errors. Transfer failures log
only CLINICAL_BINARY_TRANSFER_FAILED; no partial binary response becomes JSON.
Before transfer, not-found maps to generic 404, missing/integrity to bounded 503,
and unknown errors to generic 500. No Range, conditional cache, signed/static URL,
DB/storage mutation, UI or multipart-write behavior is added.

QA: 15 pure cases verify allowed MIME/extensions, injection-proof generated disposition,
error mapping, exact/chunked transfer, short stream, disconnect, emitter failure,
stream closure and no creation/permission changes in read-only construction.
Static QA verifies authentication/gate order, exact GET shape, no client doctor
input, configuration authority and original router equality after removing only the
new branch. Existing static guards now delegate these two precisely bounded changes
to MULTI04B checks; unrelated source and migration protection remains intact.
All requested retrieval, multipart, readiness, M6, encounter and M5 regressions pass.
PHP lint, shell syntax and diff checks pass. No MySQL connection or HTTP server/request
was used. Temporary synthetic QA storage was removed.

The route exists only as gated source capability. Working DB remains PRE_MIGRATION;
master/cohort runtime activation is unchanged and false. Multipart write 503,
C04/C05/C21=false, M6 NO_GO_BLOCKED and all migration/cutover/production boundaries
remain. Next: Director/assistant review, then separately authorized MULTI04C physical
HTTP QA. No physical route invocation or activation occurred in this chapter.
