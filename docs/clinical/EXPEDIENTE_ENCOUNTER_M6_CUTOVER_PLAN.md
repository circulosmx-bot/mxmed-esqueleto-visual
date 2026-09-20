# Expediente Clínico — M6 backend clinical cutover plan

```text
CHAPTER=CLIN-REFORM-PHASE2-M6-PLAN01
PLAN_STATUS=ACCEPTED
PLAN_ACCEPTED_HEAD=7dc615c772ef611a49229e3e1da91b569d6cf73d
CTRL01_STATUS=ACCEPTED
CTRL01_R1_STATUS=ACCEPTED
GUARD01_STATUS=ACCEPTED
GUARD01_ACCEPTED_HEAD=bcb2606ba7a95818673b402a9e006ecc0431ff73
PHASE_2_M6_GUARD01=ACCEPTED
M6_GUARD01_ACCEPTED_HEAD=bcb2606ba7a95818673b402a9e006ecc0431ff73
CALLER01_STATUS=ACCEPTED
M6_CALLER01_ACCEPTED_HEAD=0baeefb8eff98f9de80429d0ad9d164190508fbc
PHASE_2_M6_ROUTE01=ACCEPTED
M6_ROUTE01_ACCEPTED_HEAD=f01b2f60c6363b10b43b931b20f42fb3348acdf1
CURRENT_ACCEPTED_HEAD=0daa1e52dd2bda11cad5d50f09ac0516725ef004
M6_COHORT_ROUTING_CAPABILITY=ACCEPTED
M6_COHORT_RUNTIME_ROUTING_ACTIVE=false
PHASE_2_M6_MULTI01=ACCEPTED
M6_MULTI01_ACCEPTED_HEAD=e61f1c9fe0ba0bedad3f33e99399c5321f3ac5aa
MULTIPART_DESIGN=ACCEPTED
PHASE_2_M6_MULTI02A=ACCEPTED
M6_MULTI02A_ACCEPTED_HEAD=3e06decd0f96771248da567d7e5c92186506f34f
MULTIPART_SCHEMA_FOUNDATION=ACCEPTED
PHASE_2_M6_MULTI02B=ACCEPTED
M6_MULTI02B_EVIDENCE_COMMIT=4c125344b97009c236e243b86c4290844c229ed6
MIGRATION_05_STATUS=ACCEPTED_REPOSITORY_PHYSICAL_REHEARSAL
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
PHASE_2_M6_MULTI04B=ACCEPTED
M6_MULTI04B_ACCEPTED_HEAD=0daa1e52dd2bda11cad5d50f09ac0516725ef004
PHASE_2_M6_MULTI04C=ACCEPTED
M6_MULTI04C_EVIDENCE_COMMIT=10c09aa8fa0960fd5d1d57d72c765755026c2c06
PHASE_2_M6_MULTI05A=READY_FOR_CODE_REVIEW
CANONICAL_V1_ENCOUNTER_MULTIPART_WRITE=IMPLEMENTED_PENDING_REVIEW
MULTI05A_PHYSICAL_MULTIPART_QA_EXECUTED=false
PRIVATE_BINARY_HTTP_PHYSICAL_QA=ACCEPTED
PRIVATE_BINARY_AUTHENTICATION_PHYSICAL_QA=PASS
PRIVATE_BINARY_STREAMING_PHYSICAL_QA=PASS
PRIVATE_BINARY_HTTP_CONTROLLER=ACCEPTED
PRIVATE_BINARY_HTTP_ROUTE=ACCEPTED
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
V1_MULTIPART_DOCUMENT_WRITE=CANONICAL_ENCOUNTER_ROUTE_IMPLEMENTED_PENDING_REVIEW
MULTIPART_ACTIVE_CALLER_BLOCKER=true
PLANNING=true
REPOSITORY_INVENTORY=true
WORKING_DB_PREFLIGHT=READ_ONLY_ONLY
PHASE_2_M6_EXECUTION_AUTHORIZED=false
M6_AUTHORIZED=false
M6_GO_NO_GO=NO_GO_BLOCKED
```

## 1. M6 objective and authorization boundary

M6 is the backend clinical cutover. This plan inventories the current runtime callers, records a read-only preflight of the working MXMed database, and defines the future migration, backup, rehearsal, activation, monitoring, abort and safe-return procedure.

This chapter does **not** authorize or perform a backup, restore, database clone, migration, feature-gate activation, runtime cutover, production execution or PHASE 3 UI work. No application source, SQL or migration file is changed.

## 2. Accepted baseline

| Authority | Value |
| --- | --- |
| Branch | `design/physician-crd03-credentials-ui-v1` |
| PLAN01 pre-head and checkpoint | `4d99237e3fe87679bfb74cb30ac213d7530a30f5` |
| Accepted M5 clinical source | `115923cac322958ad4f443ab783a8cf19f9c5093` |
| Current accepted repository head / ROUTE01 | `f01b2f60c6363b10b43b931b20f42fb3348acdf1` |
| Accepted M5 evidence | `b069fe7cfa66fc71a2aaf323676dc74a411f1ec2` |
| M5 result | `T01_T35=PASS`, accepted |
| M6 execution | not authorized |

M5 proves the accepted repository behavior against isolated, synthetic, disposable databases. It does not prove compatibility of every current client or readiness of the working database.

## 3. Complete runtime caller inventory

Inventory unit: one independently actionable runtime operation family. A row can aggregate repeated call sites only when they use the same endpoint, authority and response contract. `YES (session)` means the server derives the doctor from the authenticated PHP session; a doctor ID supplied by a client is not considered canonical doctor authority.

| CALLER_ID | FILE | RUNTIME_ROLE | OPERATION | ENDPOINT_OR_DIRECT_DB_PATH | V1_GATE_AWARE | USES_CANONICAL_DOCTOR_CONTEXT | USES_CANONICAL_PATIENT_ID | USES_IDEMPOTENCY_KEY | EXPECTED_RESPONSE_CONTRACT | LEGACY_WRITE_CAPABILITY | M6_COMPATIBILITY | ACTION_REQUIRED |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| C01 | `assets/js/app.js` | Expediente context bridge | Resolve active encounter and read encounter detail | `GET /patients/{patient}/encounters/active`; `GET /encounters/{key}` | yes | yes (session) | yes | n/a | `{ok,data,meta}` | no | `READ_ONLY_NO_CUTOVER_IMPACT` | Retain and regression-test under gate ON. |
| C02 | `assets/js/app.js` | Expediente open-patient flow | START when no active encounter exists | `POST /patients/{patient}/encounters` | pair-aware endpoint routing; CALLER01 client contract accepted | yes (session) | yes | yes, stable per logical command | canonical `200/201` plus explicit canonical errors | yes | `ADAPTED_ACCEPTED` | Stable command nonce, in-flight dedupe, lost-response retry and no secondary START fallback; routing remains inactive. |
| C03 | `assets/js/app.js` | Evolution note, prescription and encounter-owned JSON composer | Create encounter-owned JSON clinical documents | `POST /encounters/{encounter_key}/documents` | pair-aware capability accepted; runtime routing inactive | yes (session); no client doctor path | authoritative encounter plus canonical patient context | yes, stable per logical content | canonical `200/201`; canonical failures do not fall back | yes | `ADAPTED_ACCEPTED` | Active encounter required; JSON only. Multipart remains deferred and fail-closed. |
| C04 | `assets/js/app.js` | Consent identity attachment UI | Create uploaded identity attachment | multipart `POST /doctors/{doctor}/patients/{patient}/documents` | no | no; path doctor ID is client-resolved | yes | no | uploaded legacy document | yes | `MUST_BE_EXPLICITLY_BLOCKED_AT_M6` | Block for M6 cohort until accepted V1 multipart storage exists. |
| C05 | `assets/js/app.js` | Orders/results/diagnostic uploads | Create result/order upload and legacy replacement | multipart scoped document create; `POST .../replace` | no | no; path doctor ID is client-resolved | yes | no | legacy document/replace response | yes | `MUST_BE_EXPLICITLY_BLOCKED_AT_M6` | Block encounter-owned upload/replace; later adapt to V1 document and amendment commands. |
| C06 | `assets/js/app.js` | Historia Clínica editor | Update longitudinal patient history | `PUT /patients/{patient}/history` → `clinical_record_entries` | no | session check at gateway, not encounter command | yes | no | legacy history payload | yes | `LEGACY_ONLY_DEFERRED` | Keep patient-level draft isolated; prohibit use as encounter-history overwrite. |
| C07 | `assets/js/app.js` | Exploración Física editor | Update legacy patient physical exam | `PUT /patients/{patient}/physical-exam` → `clinical_record_entries` | no | session check at gateway, not encounter command | yes | no | legacy physical-exam payload | yes | `LEGACY_ONLY_DEFERRED` | Keep isolated from V1 encounter sections; do not present it as a current encounter write. |
| C08 | `assets/js/app.js` | Timeline/history/read widgets | Read timeline, history, physical exam and supporting clinical data | clinical `GET` routes | mixed but read-only | yes where scoped | yes | n/a | legacy/read contracts | no | `READ_ONLY_NO_CUTOVER_IMPACT` | Retain only reads that do not invoke runtime DDL; validate under gate ON. |
| C09 | `modules/clinical/ui/encounter.php` | Canonical encounter detail UI | Read and FINALIZE encounter | `GET /encounters/{key}`; `POST /encounters/{key}/finalize` | yes | yes (same-origin session) | resolved from authorized encounter | n/a for finalize | canonical `{ok,error,data,meta}` | yes | `COMPATIBLE_AS_IS` | Regression-test with working schema ready; no adapter required. |
| C10 | `modules/clinical/ui/historial.php` | Clinical history UI | Read encounter/timeline/active state | clinical `GET` routes | yes for V1 reads | yes (session) | yes | n/a | read contracts | no | `READ_ONLY_NO_CUTOVER_IMPACT` | Keep read-only; prove no GET DDL for routes used by cohort. |
| C11 | `modules/clinical/ui/document.php` | Document viewer | Read and replicate document | `GET /doctors/{doctor}/documents/{id}`; `POST .../replicate` | no | path doctor plus link check | derived from document | no | legacy replicated document | yes | `MUST_BE_EXPLICITLY_BLOCKED_AT_M6` | Keep viewing; suppress replication for M6 cohort until mapped to canonical create/amendment. |
| C12 | `modules/clinical/ui/viewer.php` | Certificate/document editor | PATCH rendered document content | `PATCH /doctors/{doctor}/documents/{id}` | no | path doctor plus link check | derived from document | no | mutable legacy document | yes | `MUST_BE_EXPLICITLY_BLOCKED_AT_M6` | Disable editing of encounter-linked documents; use append-only V1 amendment later. |
| C13 | `assets/js/manejo-hospitalario.js` | Hospital episode UI | Create/list hospital documents | scoped doctor/patient document routes | no | no; client-resolved doctor path | yes | no | legacy hospital document | yes | `LEGACY_ONLY_DEFERRED` | Keep hospital-only and outside initial M6 cohort; prove isolation before activation. |
| C14 | `modules/agenda/services/ClinicalEncounterBridge.php` | Agenda completion bridge | Find or START encounter after appointment completion | non-cohort HTTP `GET/POST /patients/{patient}/encounters`; blocked before HTTP for configured cohort | no routing activation | no invented authentication | patient ID supplied by Agenda and checked against accepted membership | no | stable `M6_AGENDA_CLINICAL_BRIDGE_BLOCKED` for cohort | yes outside cohort | `BLOCKED_FOR_M6_COHORT_ACCEPTED` | Configured cohort, emergency OFF and malformed active configuration fail closed before bridge GET/POST; appointment state remains unchanged. |
| C15 | `modules/agenda/services/AmbiguousPatientReconciliationService.php` | Patient reconciliation guard | Check clinical references before patient reconciliation | direct aggregate reads of `clinical_encounters`, `clinical_record_entries`, `clinical_documents` | n/a | canonical Agenda doctor check | yes | n/a | throws on any clinical reference | no clinical-table write | `READ_ONLY_NO_CUTOVER_IMPACT` | Preserve fail-closed reference checks; no clinical rewrite is allowed. |
| C16 | `api/clinical-documents.php` | Standalone legacy document endpoint | Direct create document/participants; list/get | direct `clinical_documents`, `clinical_document_participants`; runtime DDL helper | no | no canonical session doctor authority | validates patient only | no | legacy `{ok,document}` | yes | `MUST_BE_EXPLICITLY_BLOCKED_AT_M6` | Deny write access for M6 cohort and remove caller dependencies before cutover. |
| C17 | `api/evolution-note-generate.php` | Standalone legacy evolution-note endpoint | Direct create evolution note/participants | direct `clinical_documents`, `clinical_document_participants`; runtime DDL helper | no | no canonical session doctor authority | validates patient only | no | legacy generated document | yes | `MUST_BE_EXPLICITLY_BLOCKED_AT_M6` | Deny for M6 cohort; canonical encounter document creation replaces it. |
| C18 | `api/clinical/index.php` V1 handlers and integrity services | Hardened V1 server surface | START/ACTIVE/FINALIZE/VOID, sections, observations, documents, encounter/document amendments | canonical V1 routes and repositories | yes | yes (PHP session) | yes and encounter cross-check | yes where creation contract requires it | canonical `{ok,error,data,meta}` with 409/503 mapping | yes | `COMPATIBLE_AS_IS` | Enable only after schema, callers, monitoring and cohort gates pass. |
| C19 | `api/clinical/index.php` legacy history/physical-exam handlers | Legacy patient-draft server surface | PUT patient history and physical exam | direct `clinical_record_entries` | no | scoped session/link checks but no encounter ownership | yes | no | legacy patient draft | yes | `LEGACY_ONLY_DEFERRED` | Keep isolated as patient-level draft; exclude from encounter history authority. |
| C20 | `api/clinical/index.php` legacy document handlers | Legacy document server surface | Generic/scoped create, PATCH, replace and replicate | direct legacy `clinical_documents` paths | partial route sharing, not V1 write policy | mixed; several path-doctor checks | generally yes | no | mutable legacy document contracts | yes | `MUST_BE_EXPLICITLY_BLOCKED_AT_M6` | Block encounter-linked legacy mutations for cohort; leave safe reads only. |
| C21 | `api/clinical/index.php` note-capture token handlers plus `assets/js/app.js` clients | QR/device note/signature capture | Create/cancel/consume token and persist resulting document | note-capture token tables and legacy document persistence | no | token/session flow, not V1 encounter command | payload patient context | no V1 command idempotency | token and legacy document contracts | yes | `MUST_BE_EXPLICITLY_BLOCKED_AT_M6` | Block encounter-owned consume/write for cohort until an accepted V1 adapter exists. |

Inventory totals:

```text
TOTAL_CLINICAL_RUNTIME_CALLERS=21
WRITE_CAPABLE_CALLERS=17
COMPATIBLE_AS_IS_CALLERS=2
REQUIRES_ADAPTER_CALLERS_AT_PLAN01=3
GUARDED_LEGACY_WRITER_FAMILIES=8
READ_ONLY_NO_CUTOVER_IMPACT_CALLERS=4
LEGACY_ONLY_DEFERRED_CALLERS=4
UNCONTROLLED_LEGACY_CLINICAL_WRITER_COUNT=0
CANDIDATE_UNCONTROLLED_LEGACY_CLINICAL_WRITER_COUNT=0
UNKNOWN_WRITE_CAPABLE_CALLERS=0
```

CALLER01 is accepted at `0baeefb8eff98f9de80429d0ad9d164190508fbc`: C02 and C03 are adapted and C14 is blocked for configured cohort patients, so `UNCONTROLLED_LEGACY_CLINICAL_WRITER_COUNT=0` and `M6_CALLER_COMPATIBILITY=ACCEPTED`. Deferred legacy families remain subject to the documented cohort-isolation proof. Zero uncontrolled writers does not authorize M6.

Supporting sources were audited but are not counted as separate clinical-state caller families: `modules/clinical/ui/timeline.php` is covered by the timeline read family C10; `modules/patients/repositories/PatientsRepository.php` only reads attributed/unattributed encounter aggregates for archive metrics and is covered by C08; `assets/js/core/identity.js` calls the patient-identity resolver, which does not write the encounter, record-entry or document authorities listed in the inventory. The latter can still invoke the legacy identity-bridge schema helper and is therefore included in the global runtime-DDL blocker. Repository libraries in `api/_lib/` are implementations reached by the inventoried endpoints, not independent runtime entry callers.

### Old UI compatibility map

| Classification | Current screens/functions | M6 rule |
| --- | --- | --- |
| `READ_ONLY_COMPATIBLE` | Encounter detail/history reads, timeline reads, safe document views | May remain if GET has no DDL/DML and doctor/patient scope is enforced. |
| `LEGACY_DRAFT_ONLY` | Historia Clínica and Exploración Física patient-level drafts; isolated hospital draft work | May remain outside encounter authority; cannot overwrite or masquerade as new encounter history. |
| `NEW_ENCOUNTER_WRITE` | V1 START/FINALIZE/VOID, section, observation, document and amendment commands | Only canonical session authority and accepted V1 contracts after all gates. |
| `UNSAFE_FOR_M6` | Legacy document create/PATCH/replace/replicate, standalone writers, note-capture persistence, unauthenticated Agenda START, active multipart encounter uploads | Adapt or explicitly block before activation. |

### Multipart deferral

`V1_MULTIPART_DOCUMENT_WRITE=CANONICAL_ENCOUNTER_ROUTE_IMPLEMENTED_PENDING_REVIEW`. Active consent attachments, order/result uploads and diagnostic uploads use multipart legacy document routes. They are a current cutover blocker because the V1 encounter-document and document-amendment surfaces reject multipart with `503/V1_MULTIPART_STORAGE_NOT_READY`. `MULTIPART_ACTIVE_CALLER_BLOCKER=true`. The [MULTI01 physical storage design](EXPEDIENTE_ENCOUNTER_V1_MULTIPART_STORAGE_DESIGN.md) is accepted. MULTI02A and the isolated migration-05 evidence MULTI02B are accepted, as is MULTI03A/R1 private storage. MULTI03B/R1 internal coordination is accepted; MULTI03C physical coordination evidence is accepted. Caller adapters remain absent. Migration 05 has not been applied to the working database. These repository stages do not clear the active caller blocker.

## 4. Legacy writers and runtime DDL

The write-capable legacy surfaces are C02–C07, C11–C14 and C16–C21 as classified above. The highest-risk bypasses are the standalone document writers, mutable generic document routes, QR/note-capture persistence, the Agenda bridge without session propagation, and active multipart uploads.

The accepted V1 encounter handlers do not execute runtime DDL. Global clinical runtime DDL remains in:

- `api/_lib/clinical_documents.php::mxmed_ensure_clinical_docs_schema()` (`CREATE TABLE IF NOT EXISTS` and `ALTER TABLE ... ADD INDEX`);
- legacy startup in `api/clinical/index.php` for identity bridge and note-capture tokens while the V1 gate is OFF;
- legacy identity bridge, note-capture token, cases and encounter schema helpers in `api/clinical/index.php`;
- `api/clinical-documents.php` and `api/evolution-note-generate.php`, which call the clinical-document schema helper on requests.

```text
ENCOUNTER_V1_GET_DDL_REMOVAL=IMPLEMENTED_FOR_V1_ENCOUNTER_PATHS
GLOBAL_CLINICAL_GET_DDL_REMOVAL=PENDING_LATER_IMPL_STAGE
```

Before M6, every cohort-accessible GET must be proven DDL-free. Legacy paths that can trigger DDL must be blocked or moved outside the cutover path; PLAN01 does not remove them.

## 5. Working database read-only preflight

Preflight observed the database configured by the active local review runtime on 2026-09-19. Connection values came from `MXMED_DB_*` environment variables. No password or secret was printed. The runtime uses `MXMED_DB_HOST=127.0.0.1`, port `3306`, database `mxmed` and user `root`; no current-repository `api/mxmed-db.config.php` is present.

Every preflight data/metadata block used a read-only transaction and ended with `ROLLBACK`. Only `SELECT`, information-schema inspection and `SHOW GRANTS` were used.

```text
WORKING_DB_IDENTITY_PROVEN=true
WORKING_DB_PREFLIGHT_MODE=READ_ONLY
MYSQL_ENDPOINT=127.0.0.1:3306
WORKING_DB_NAME=mxmed
MYSQL_VERSION=9.6.0
MYSQL_SERVER_HOSTNAME=192.168.1.10
MYSQL_SERVER_PORT=3306
MYSQL_GLOBAL_READ_ONLY=0
EFFECTIVE_DB_USER=root@localhost
SQL_MODE=ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION
DB_SCHEMA_PHYSICALLY_CHANGED=false
DB_DATA_CHANGED=false
```

The server reports `@@read_only=0`; therefore safety came from the read-only session/transaction, not from a globally read-only server. `SHOW GRANTS` shows that the effective runtime account has broad/global privileges including DDL. That identity is unsuitable as the distinct least-privilege migration account and also demonstrates that the runtime account currently has more privilege than the target model permits.

### Presence and aggregate evidence

Present required tables: `clinical_encounters`, `clinical_record_entries`, `clinical_documents`, `clinical_document_participants`, `patients_patients`, `patients_doctor_links`, `agenda_appointments`.

Absent required tables: `clinical_encounter_sections`, `clinical_observations`, `clinical_encounter_amendments`, `clinical_encounter_start_requests`, `clinical_idempotency_requests`, `clinical_encounter_final_notes`, `clinical_document_revisions`.

| Aggregate | Observed value |
| --- | ---: |
| `TOTAL_ENCOUNTERS` | 13 |
| `OPEN_COUNT` | 2 |
| `CLOSED_COUNT` | 11 |
| `VOIDED_COUNT` | 0 |
| `LEGACY_OTHER_STATUS_COUNT` | 0 |
| `DOCTOR_ID_NULL_COUNT` | 13 |
| `DOCTOR_ID_NON_NULL_COUNT` | 0 |
| `DUPLICATE_OPEN_DOCTOR_PATIENT_GROUP_COUNT` | 0 |
| `APPOINTMENT_ID_NULL_COUNT` | 13 |
| `APPOINTMENT_ID_NON_NULL_COUNT` | 0 |
| `CLOSED_MISSING_TERMINAL_FIELDS` | 0 |
| `clinical_record_entries` | 18 |
| `clinical_documents` | 413 |
| `clinical_document_participants` | 236 |
| `patients_patients` | 169 |
| `patients_doctor_links` | 171 |
| `agenda_appointments` | 206 |

All 13 encounters are legacy `UNATTRIBUTED`; no doctor is inferred from appointment, user, clinic, Agenda, document or patient link.

```text
NO_INFERENCE_OF_LEGACY_DOCTOR_OWNERSHIP=true
DUPLICATE_OPEN_DOCTOR_PATIENT_GROUP_COUNT=0
```

The duplicate-open query only evaluates doctor-attributed groups, matching the future unique key. With all rows unattributed, zero does not authorize attribution or lifecycle repair.

### Document compatibility aggregates

The current `clinical_documents` table has legacy nullable text `encounter_id` and does not have canonical `encounter_ref_id`.

| Aggregate | Observed value |
| --- | ---: |
| Total documents | 413 |
| Legacy nonempty `encounter_id` | 11 |
| Legacy numeric IDs matching an existing encounter | 11 |
| Legacy links matching CLOSED encounters | 11 |
| Documents without encounter relation | 402 |
| Canonical `encounter_ref_id` relations | unavailable; column absent |

No document content or patient/doctor clinical row was emitted. All values are aggregate only.

## 6. Schema and migration prerequisite state

```text
WORKING_DB_SCHEMA_STATE=PRE_MIGRATION
MIGRATION_01_STATE=NOT_APPLIED
MIGRATION_02_STATE=NOT_APPLIED
MIGRATION_03_STATE=NOT_APPLIED
MIGRATION_04_STATE=NOT_APPLIED
MIGRATION_05_STATE=NOT_APPLIED_WORKING_DB_PHYSICAL_DISPOSABLE_REHEARSAL_PASS
```

- **01 encounter lifecycle integrity:** the table lacks `voided_at`, `voided_by_user_id`, `void_reason`, `open_guard`, the accepted one-open unique index, lifecycle checks and accepted triggers.
- **02 structured content:** sections, observations and encounter amendments are absent.
- **03 command idempotency:** start-request and general idempotency tables are absent.
- **04 document integrity:** `encounter_ref_id`, final-note and document-revision structures are absent.
- **05 clinical binary storage:** accepted repository artifact with a passing isolated disposable MySQL 9.6 physical rehearsal; it remains unapplied and unauthorized on the working database.

The only future migration sequence permitted by the accepted plan is:

1. `2026_09_18_01_encounter_lifecycle_integrity.sql`
2. `2026_09_18_02_encounter_structured_content.sql`
3. `2026_09_18_03_encounter_command_idempotency.sql`
4. `2026_09_18_04_encounter_document_integrity.sql`
5. `2026_09_19_05_clinical_binary_storage.sql`

Migration 05 passed its isolated disposable rehearsal but is not accepted for or applied to the working database. `2026_09_17_clinical_encounter_doctor_attribution.sql` remains explicitly prohibited. The 13 NULL doctor rows must remain unattributed.

## 7. Backup and restore plan

No repository mechanism was found that proves a restorable backup of this working database. AWS/infrastructure backup material does not prove the local working database can be restored. PLAN01 creates no backup.

Before any migration authorization, an operator must:

1. identify the exact source endpoint/database and freeze its identity evidence;
2. create a consistent timestamped backup with a tool/version appropriate to MySQL 9.6.0;
3. hash the backup and store the hash independently;
4. record start/end time, server identity, schema name and tool version without secrets;
5. restore to a newly named isolated database with no application traffic;
6. compare schema fingerprint and critical aggregate counts with the source snapshot;
7. execute representative reads against the restore;
8. preserve the evidence and destroy the rehearsal restore only after review.

Required future proof:

```text
BACKUP_CREATED=true
BACKUP_HASHED=true
BACKUP_TIMESTAMPED=true
BACKUP_DB_IDENTITY_MATCHED=true
RESTORE_REHEARSAL_SUCCEEDED=true
RESTORED_SCHEMA_FINGERPRINT_MATCHED=true
RESTORED_CRITICAL_COUNTS_MATCHED=true
BACKUP_RESTORABLE=true
```

Current state: `BACKUP_RESTORABLE=NOT_YET_PROVEN`.

## 8. Isolated clone rehearsal plan

After separate authorization for backup/restore plus clone rehearsal:

1. take the proven read-only working-DB snapshot/backup;
2. restore it into a new isolated local database with a unique name and no runtime connection;
3. capture pre-migration schema fingerprint, status aggregates, NULL doctor rows, document relation aggregates and critical row counts;
4. apply migrations 01–04 in exact order with the dedicated migration account;
5. rerun 01–04 to prove convergence/idempotent deployment behavior;
6. run schema-readiness checks and read-only invariants;
7. exercise only separately authorized controlled validation against the clone;
8. compare pre/post counts and hashes, proving no unexpected rewrite;
9. rehearse safe application return with gate OFF and additive schema retained;
10. destroy the clone and prove zero residual clone databases/processes.

Acceptance gates:

```text
CLONE_MIGRATION_01_04=PASS
LEGACY_NULL_DOCTOR_PRESERVED=true
DUPLICATE_OPEN_BLOCKER_COUNT=0
NO_UNEXPECTED_DATA_REWRITE=true
SCHEMA_READINESS=PASS
POST_MIGRATION_EXISTING_HISTORY_READABLE=true
SAFE_RETURN_REHEARSAL=PASS
```

Current state: `CLONE_MIGRATION_REHEARSAL=NOT_YET_EXECUTED`.

## 9. Migration account and privilege model

The future migration must use a distinct, time-bounded account:

```text
RUNTIME_DB_ACCOUNT != MIGRATION_DB_ACCOUNT
```

The migration account receives only the DDL/DML/trigger privileges proven necessary for migrations 01–04 on the named schema, for the approved window, and is removed or disabled afterward. The runtime account must retain ordinary application DML and lose DDL privileges. Account creation/privilege changes require a separate authorized operational chapter.

The current effective runtime identity is `root@localhost` with broad privileges. No distinct migration account was demonstrated. `MIGRATION_ACCOUNT_READY=false`.

## 10. Future write-control window

The future M2/M3 migration and cutover window requires a maintenance control that prevents every writer, not just the UI:

| Operation | Migration/schema window | Validation/cutover window |
| --- | --- | --- |
| START encounter | `BLOCKED` | V1 only for authorized cohort after GO |
| ACTIVE resolution/read | `READ_ONLY` | `READ_ONLY`, then normal V1 read |
| FINALIZE / VOID | `BLOCKED` | V1 only after readiness/monitoring |
| Section / observation writes | `BLOCKED` | V1 only after readiness/monitoring |
| Encounter documents / results / amendments | `BLOCKED` | JSON V1 only; multipart remains blocked |
| Legacy document/history/physical-exam writes | `BLOCKED` for cohort | isolated legacy-only outside cohort |
| Agenda-to-clinical START bridge | `PAUSED` | disabled until adapted and verified |
| Unrelated non-clinical reads | `UNAFFECTED` | `UNAFFECTED` |

The window must verify all web workers, jobs, Agenda bridge and direct endpoints are quiescent before migration; a UI banner alone is insufficient. Current state: `WRITE_WINDOW_READY=false` because no enforcement mechanism covering all writers is implemented or rehearsed.

## 11. Feature-gate activation plan

`MXMED_CLINICAL_ENCOUNTER_INTEGRITY_V1` remains OFF for the working database. The activation order is:

1. callers adapted or explicitly blocked; uncontrolled legacy writer count zero;
2. restorable backup and isolated clone rehearsal accepted;
3. distinct migration account and write-control window ready;
4. working schema migrated 01–04 under separate authorization;
5. schema-readiness and read-only invariants pass;
6. monitoring/alerts active and staffed;
7. safe-return command/runbook verified;
8. enable only for the accepted initial cohort;
9. verify critical invariants and error rates before expansion.

```text
FLAG_OFF_BEFORE_WORKING_SCHEMA_READY=true
FLAG_ON_ONLY_AFTER=SCHEMA_READY+CALLER_COMPATIBILITY_PASS+MONITORING_ACTIVE
FEATURE_GATE_PLAN_READY=true
```

No flag was enabled by PLAN01.

## 12. Cohort/patient scoping capability

CTRL01/R1 is accepted at `05369176c3fc6a8b89043ee79c6b431161b3d5f4`. The repository control plane reads only server environment configuration, defaults OFF, requires exact canonical doctor/patient pairs, derives patient-level membership for legacy-write blocking and fails closed on malformed active configuration.

R1 separates configured membership from active V1 routing authorization. `clinical_m6_cohort_pair_configured()` evaluates the exact configured doctor/patient pair without consulting emergency OFF. `clinical_m6_patient_in_any_cohort()` derives configured patient membership the same way, and `clinical_m6_legacy_write_block_required()` preserves that membership as the future legacy-write block decision. `clinical_m6_cohort_authorized()` alone applies emergency OFF before permitting V1 routing. A malformed active allowlist continues to raise `M6_COHORT_CONFIG_INVALID` in membership and legacy-block paths even while emergency OFF is active; it cannot silently reopen a legacy writer.

```text
M6_COHORT_SCOPING_CAPABILITY=AVAILABLE_REPOSITORY_CONTROL
M6_COHORT_CONTROL_PLANE=ACCEPTED
M6_SAFE_RETURN_MEMBERSHIP_SEMANTICS=ACCEPTED
EMERGENCY_OFF_MEANS_STOP_M6_ROUTING=true
EMERGENCY_OFF_MEANS_REENABLE_LEGACY_WRITES=false
M6_COHORT_RUNTIME_ROUTING_ACTIVE=false
LEGACY_WRITER_BLOCKING_ACTIVE=false
COHORT_STATE_STORED_IN_CLINICAL_DB=false
M6_LEGACY_WRITER_GUARDS=ACCEPTED
M6_LEGACY_WRITE_BLOCK_ERROR=M6_LEGACY_WRITE_BLOCKED
M6_LEGACY_WRITE_BLOCK_HTTP_STATUS=409
GUARDED_LEGACY_WRITER_FAMILIES=8
REMAINING_UNCONTROLLED_WRITER_FAMILIES=0
UNCONTROLLED_LEGACY_CLINICAL_WRITER_COUNT=0
PHASE_2_M6_CALLER01=ACCEPTED
M6_CALLER01_ACCEPTED_HEAD=0baeefb8eff98f9de80429d0ad9d164190508fbc
M6_CALLER_COMPATIBILITY=ACCEPTED
C02_ADAPTED=true
C03_ADAPTED=true
C14_RESOLVED=true
C14_RESOLUTION=BLOCK_FOR_CONFIGURED_M6_COHORT
CANDIDATE_UNCONTROLLED_LEGACY_CLINICAL_WRITER_COUNT=0
```

GUARD01 is accepted at `bcb2606ba7a95818673b402a9e006ecc0431ff73`. Its eight legacy writer families remain contained with patient authority, stable `409/M6_LEGACY_WRITE_BLOCKED`, emergency-OFF persistence and malformed-config fail-closed behavior. It does not activate cohort routing or change `MXMED_CLINICAL_ENCOUNTER_INTEGRITY_V1`. CALLER01 subsequently accepted the remaining C02/C03 adapters and C14 cohort block; the accepted uncontrolled-writer count is now 0.

### GUARD01 coverage matrix

| Family | File | Route/action | Authoritative `patient_id` derivation | Guard location | Mutation prevented |
| --- | --- | --- | --- | --- | --- |
| C04 | `api/clinical/index.php` | scoped multipart create; note-capture identity upload | doctor/patient route parameter forced into payload; otherwise stored token row | shared upload save before `clinical_store_uploaded_file()`; note-capture upload catch maps 409 | uploaded-file storage and clinical document INSERT |
| C05 | `api/clinical/index.php` | scoped/generic multipart create; `POST /documents/{id_or_uuid}/replace` | validated create context or stored source-document patient | shared upload guard before file storage; replace guard after stored patient lookup and before transaction | file storage, replacement INSERT and source UPDATE |
| C11 | `api/clinical/index.php` | `POST /doctors/{doctor}/documents/{uuid}/replicate` → `POST /documents/{uuid}/replicate` | stored source-document patient; doctor path is not cohort authority | after canonical patient validation and before replication payload/INSERT | replicated document INSERT |
| C12 | `api/clinical/index.php` | `PATCH /doctors/{doctor}/documents/{id_or_uuid}` → `PATCH /documents/{id_or_uuid}` | stored document patient | after canonical patient validation and before UPDATE | mutable `rendered_text` UPDATE |
| C16 | `api/clinical-documents.php?action=save` | standalone save | validated request `context.patient_id` confirmed against patient storage | after patient validation and before document build/transaction | document and participant INSERTs |
| C17 | `api/evolution-note-generate.php` | legacy evolution-note POST | validated request `context.patient_id` confirmed against patient storage | after patient validation and before document build/transaction | evolution document and participant INSERTs |
| C20 | `api/clinical/index.php` | generic/scoped create, PATCH, replace and replicate | validated create context/route patient or stored source document | shared create guards plus operation-specific stored-context guards | all mapped legacy document mutations |
| C21 | `api/clinical/index.php` | `POST /note-capture-tokens/{token}/upload` | stored token-row patient | shared upload guard before file/document persistence | clinical file and document persistence; token reads/status remain allowed |

```text
C04=GUARDED
C05=GUARDED
C11=GUARDED
C12=GUARDED
C16=GUARDED
C17=GUARDED
C20=GUARDED
C21=GUARDED
COHORT_READ_COMPATIBILITY_PRESERVED=true
DEFAULT_OFF_ZERO_RUNTIME_BEHAVIOR_CHANGE=true
NON_COHORT_LEGACY_BEHAVIOR_PRESERVED=true
LEGACY_WRITE_BLOCK_PERSISTS_DURING_EMERGENCY_OFF=true
MALFORMED_CONFIG_CAN_ALLOW_LEGACY_WRITE=false
```

### CALLER01 accepted coverage

| Family | Candidate behavior | Authority and failure rule | Routing state |
| --- | --- | --- | --- |
| C02 | START sends a stable per-command `Idempotency-Key`, coalesces the same in-flight command, retains key and payload across lost-response retry, accepts 200/201 and replaces the key only after an authoritative result. | Doctor remains PHP-session authority; canonical failures never invoke another START mechanism. | unchanged; endpoint remains shared and cohort routing is inactive |
| C03 | Encounter-owned JSON note/prescription commands require an active encounter and use `POST /encounters/{encounter_key}/documents` with stable idempotency. | Encounter is authoritative; no client doctor path and no legacy fallback after canonical intent. Multipart is not adapted. | unchanged; V1 gate is not activated |
| C14 | Configured M6 cohort patients fail with `M6_AGENDA_CLINICAL_BRIDGE_BLOCKED` before Agenda bridge GET/POST. | Patient membership comes from accepted server configuration; emergency OFF preserves block and malformed configuration fails closed. No service identity is invented. | non-cohort bridge preserved; appointment status semantics unchanged |

```text
C02_START_IDEMPOTENCY_HEADER_PRESENT=true
C02_SAME_LOGICAL_RETRY_REUSES_KEY=true
C02_NEW_COMMAND_CAN_USE_NEW_KEY=true
C02_CLIENT_DOCTOR_AUTHORITY=false
C02_NO_SECONDARY_LEGACY_START_FALLBACK=true
C03_ENCOUNTER_OWNED_JSON_USES_CANONICAL_ROUTE=true
C03_IDEMPOTENCY_HEADER_PRESENT=true
C03_REQUIRES_ENCOUNTER_KEY=true
C03_CANONICAL_FAILURE_LEGACY_FALLBACK=false
C03_MULTIPART_NOT_ADAPTED=true
C14_NON_COHORT_BRIDGE_PRESERVED=true
C14_COHORT_BRIDGE_BLOCKED=true
C14_EMERGENCY_OFF_COHORT_BRIDGE_BLOCKED=true
C14_MALFORMED_CONFIG_FAILS_CLOSED=true
C14_DOES_NOT_INVENT_CLINICAL_AUTHENTICATION=true
M6_COHORT_RUNTIME_ROUTING_ACTIVE=false
```

## 12A. ROUTE01 accepted — cohort-aware V1 route selection

CALLER01 is accepted at `0baeefb8eff98f9de80429d0ad9d164190508fbc` with C02/C03 adapted, C14 resolved and zero uncontrolled legacy clinical writers. ROUTE01 is accepted at `f01b2f60c6363b10b43b931b20f42fb3348acdf1`. It adds a repository-only pair selector layered on the unchanged global V1 master flag. Master OFF short-circuits to legacy behavior; master ON with cohort mode OFF preserves M5 global V1 behavior; allowlist mode selects only the exact authenticated doctor/stored patient pair; emergency OFF disables V1 selection. Malformed active configuration fails closed.

Patient routes bind session doctor plus route patient. Encounter-key routes use the read-only encounter resolver, require stored doctor to equal session doctor and never infer ownership for legacy `doctor_id=NULL`. Document amendments use a minimal common-schema `document token -> patient_id` lookup before V1 schema readiness. Route selection performs no DDL or DML. V1-only subroutes remain unavailable when the pair is not selected; the shared encounter-document create route retains its legacy-compatible branch for non-cohort pairs, while configured patients remain protected by the accepted legacy-write guard during emergency OFF.

```text
PHASE_2_M6_ROUTE01=ACCEPTED
M6_ROUTE01_ACCEPTED_HEAD=f01b2f60c6363b10b43b931b20f42fb3348acdf1
M6_COHORT_ROUTING_CAPABILITY=ACCEPTED
M6_COHORT_RUNTIME_ROUTING_ACTIVE=false
GLOBAL_V1_MASTER_GATE_SEMANTICS_CHANGED=false
M5_GLOBAL_V1_BEHAVIOR_PRESERVED=true
DEFAULT_RUNTIME_BEHAVIOR_UNCHANGED=true
ROUTE_SELECTION_CAUSES_DDL=false
ROUTE_SELECTION_CAUSES_DML=false
GLOBAL_CLINICAL_GET_DDL_REMOVAL=PENDING_LATER_IMPL_STAGE
```

## 12B. MULTI01 accepted design — V1 multipart storage remains fail-closed

MULTI01 audits the legacy public-path filesystem write and defines the future V1 contract: private bounded staging, SHA-256-bound idempotency, an additive relational binary manifest, create-only immutable final storage, authenticated retrieval, explicit compensation, stale-staging cleanup and read-only orphan reconciliation. It maps C04, C05 and C21 into one canonical binary service and defines MPU01–MPU20 for a later isolated implementation chapter.

The design is accepted at `e61f1c9fe0ba0bedad3f33e99399c5321f3ac5aa`. The existing `503/V1_MULTIPART_STORAGE_NOT_READY` response and all accepted legacy-writer guards remain mandatory. Runtime routing is still inactive.

```text
PHASE_2_M6_MULTI01=ACCEPTED
M6_MULTI01_ACCEPTED_HEAD=e61f1c9fe0ba0bedad3f33e99399c5321f3ac5aa
MULTIPART_DESIGN_COMPLETE=true
MULTIPART_DESIGN=ACCEPTED
MULTIPART_DESIGN_STATUS=ACCEPTED
MULTIPART_SCHEMA_CHANGE_REQUIRED=true
V1_MULTIPART_DOCUMENT_WRITE=CANONICAL_ENCOUNTER_ROUTE_IMPLEMENTED_PENDING_REVIEW
MULTIPART_ACTIVE_CALLER_BLOCKER=true
M6_COHORT_RUNTIME_ROUTING_ACTIVE=false
```

## 12C. MULTI02A/MULTI02B accepted foundation and MULTI03A/R1 accepted storage and MULTI03B coordination candidate

Migration 05 defines `clinical_binary_uploads` and `clinical_document_binaries` as an additive repository artifact with explicit drift validation, immutable manifest keys, named checks and historical `RESTRICT` foreign keys. A separate read-only readiness authority catalogs and inspects the critical tables, columns, indexes, foreign keys and checks. MULTI02A is accepted at `3e06decd0f96771248da567d7e5c92186506f34f`; it is not wired into existing JSON V1 readiness or multipart routes.

```text
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
PHASE_2_M6_MULTI04B=ACCEPTED
M6_MULTI04B_ACCEPTED_HEAD=0daa1e52dd2bda11cad5d50f09ac0516725ef004
PHASE_2_M6_MULTI04C=ACCEPTED
M6_MULTI04C_EVIDENCE_COMMIT=10c09aa8fa0960fd5d1d57d72c765755026c2c06
PHASE_2_M6_MULTI05A=READY_FOR_CODE_REVIEW
CANONICAL_V1_ENCOUNTER_MULTIPART_WRITE=IMPLEMENTED_PENDING_REVIEW
MULTI05A_PHYSICAL_MULTIPART_QA_EXECUTED=false
PRIVATE_BINARY_HTTP_PHYSICAL_QA=ACCEPTED
PRIVATE_BINARY_AUTHENTICATION_PHYSICAL_QA=PASS
PRIVATE_BINARY_STREAMING_PHYSICAL_QA=PASS
PRIVATE_BINARY_HTTP_CONTROLLER=ACCEPTED
PRIVATE_BINARY_HTTP_ROUTE=ACCEPTED
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
MULTIPART_STORAGE_SERVICE_INTEGRATION=ACCEPTED
MULTIPART_HTTP_ACCEPTANCE=false
MULTIPART_PHYSICAL_QA=PENDING
```

MULTI02B used a new disposable database on local MySQL `9.6.0` at `127.0.0.1:3306`; the selected target was `mxmed_multi02b_20260919224322_80852_mysql96`. Migrations 01–04 provided only the prerequisite synthetic baseline. Migration 05 passed clean and second application, exact shape inspection, CHECK enforcement, uniqueness, FK restrictions, valid synthetic inserts, read-only readiness, missing-schema failure and representative column/index/FK/CHECK drift detection. All temporary databases and harness files were removed. `mxmed` was never selected or changed, and no real patient, clinical, Agenda or billing data was used.

MULTI03A adds the isolated private filesystem primitives for exact-byte staging, create-only finalization, stat/read streams, staging-only cleanup, quarantine, inventory and pure reconciliation. Review found that candidate `1e948d5c6250259b54f72e92122a42e246de999d` unlinked staging immediately after finalization, before the future database commit boundary required by MULTI01. MULTI03A and R1 are now accepted at `683f99fabbd6617f58fff50eb8fb78b891b26213`. R1 preserves staging after successful final creation and verification; explicit `deleteUncommitted()` remains separate for the coordinating service to call only after commit. Failure, integrity rejection, collision and quarantine paths retain the retryable staging object.

The root remains explicit and outside the document root; storage keys remain opaque; no DB, router or HTTP surface loads the adapter. It does not coordinate `clinical_binary_uploads` or `clinical_document_binaries`, decide idempotent replay, authorize retrieval, schedule reconciliation or adapt callers. MULTI03B adds the internal coordination service for review; authenticated retrieval, scheduled reconciliation/cleanup, C04/C05/C21 adapters and multipart activation remain absent. The accepted `503/V1_MULTIPART_STORAGE_NOT_READY` therefore remains mandatory.

## 13. Monitoring invariants

All live checks are read-only aggregate queries and must be parameter-free operational monitors with no clinical content output.

| Invariant / metric | Query intent | M6 abort threshold |
| --- | --- | --- |
| More than one OPEN per doctor/patient | group attributed OPEN rows, `HAVING COUNT(*)>1` | any group (`>0`) |
| New encounter missing doctor | count `doctor_id IS NULL` for rows created after cutover timestamp | any new row (`>0`) |
| Illegal lifecycle status | aggregate status outside open/closed/voided | any row (`>0`) |
| CLOSED missing terminal fields | closed with NULL `closed_at` or `closed_by_user_id` | any row (`>0`) |
| VOIDED missing terminal fields | voided with NULL `voided_at`, actor or empty reason | any row (`>0`) |
| Multiple final notes | group final-note rows by encounter | any group with count not equal to one for finalized encounter |
| Idempotency/resource inconsistency | committed ledger without resource, hash mismatch or conflicting resource | any row (`>0`) |
| Document context mismatch | document patient differs from referenced encounter patient/doctor authority | any row (`>0`) |
| Schema readiness | accepted readiness function/check | any failure |
| V1 HTTP conflicts/errors | route-labelled 409/503/500 rates | any integrity 409 burst above baseline; any schema 503 after activation; any 500 attributable to V1; exact traffic-rate threshold must be approved before execution |

Integrity violations trigger write pause and safe return, never automatic repair. Monitoring must distinguish expected user conflicts from integrity defects and retain correlation IDs without clinical content.

## 14. Abort criteria

Immediate pause and flag OFF are required for:

- any critical invariant count above zero;
- schema-readiness failure or any V1 schema `503` after activation;
- a V1 `500` attributable to the cutover path;
- an unrecognized write caller or any observed bypass of V1 authority;
- failure of the writer-quiescence control;
- missing monitoring visibility;
- backup/restore evidence becoming invalid for the identified database;
- cross-patient/cross-doctor authorization discrepancy;
- unexpected row-count/data-hash drift during migration validation.

No automated data repair is permitted. Current state: `MONITORING_PLAN_READY=true`; operational dashboards/alerts still require implementation and acceptance before execution.

## 15. Safe-return procedure

1. stop cohort expansion and pause all new clinical writes;
2. set the V1 feature gate OFF through the accepted operational mechanism;
3. verify the flag is OFF in every worker/process;
4. restore the last application source compatible with both legacy data and the additive migrated schema;
5. retain migrations 01–04 schema and all valid rows in place;
6. permit only proven-safe legacy reads and isolated patient-level drafts;
7. capture read-only invariant/error evidence and adjudicate before resuming;
8. restore from backup only under a separate incident decision when additive safe return is insufficient and the restore point is proven.

Never drop/truncate clinical history, delete new encounters/documents, purge committed idempotency rows, infer doctor ownership or rewrite CLOSED history.

```text
SCHEMA_ROLLBACK_BY_DESTRUCTIVE_DROP=false
SAFE_RETURN_PLAN_READY=true
```

## 16. M6 go/no-go matrix

| Gate | Current state | Evidence / unmet requirement |
| --- | --- | --- |
| `M5_ACCEPTED` | `PASS` | M5 accepted; T01–T35 passed. |
| `CALLER_INVENTORY_COMPLETE` | `PASS` | 21 operation families; no unknown write-capable row. |
| `UNCONTROLLED_LEGACY_CLINICAL_WRITER_COUNT=0` | `PASS` | CALLER01 accepted C02/C03 adapters and the C14 cohort block. |
| `WORKING_DB_IDENTITY_PROVEN` | `PASS` | Environment source and read-only identity query recorded. |
| `WORKING_DB_PREFLIGHT_PASS` | `PASS` | Authorized read-only inspection completed; its result is pre-migration and therefore not schema readiness. |
| `DUPLICATE_OPEN_DOCTOR_PATIENT_GROUP_COUNT=0` | `PASS` | Aggregate zero for attributed groups; all legacy rows remain unattributed. |
| `LEGACY_STATUS_BLOCKERS=0` | `PASS` | No noncanonical status observed. |
| `BACKUP_RESTORABLE=true` | `NOT_YET_PROVEN` | No backup/restore rehearsal performed. |
| `CLONE_MIGRATION_REHEARSAL=PASS` | `NOT_YET_PROVEN` | Not executed for working-data clone. |
| `MIGRATION_ACCOUNT_READY=true` | `FAIL` | Runtime is broad-privilege root; no distinct account. |
| `WRITE_WINDOW_READY=true` | `FAIL` | No comprehensive writer pause/block mechanism rehearsed. |
| `SCHEMA_READINESS_REHEARSAL=PASS` | `NOT_YET_PROVEN` | Only synthetic M5/MIG rehearsal, not working-data clone. |
| `FEATURE_GATE_PLAN_ACCEPTED=true` | `NOT_YET_PROVEN` | PLAN01, CTRL01/R1, GUARD01, CALLER01 and ROUTE01 capability are accepted; runtime routing remains inactive and activation is not authorized. |
| `MONITORING_PLAN_ACCEPTED=true` | `NOT_YET_PROVEN` | Invariants defined; acceptance/operationalization pending. |
| `SAFE_RETURN_PLAN_ACCEPTED=true` | `NOT_YET_PROVEN` | Procedure defined; acceptance/rehearsal pending. |
| `CALLER_COMPATIBILITY_PASS=true` | `PASS` | C02/C03 adapted and C14 cohort-blocked in accepted CALLER01; no routing activation. |

`M6_GO_NO_GO=NO_GO_BLOCKED`.

## 17. Unresolved blockers

ROUTE01 is accepted; M6 remains blocked independently by multipart implementation and infrastructure gates.

Known blockers, in actionable order:

1. MULTI01, MULTI02A and MULTI02B are accepted. MULTI03A/R1 are accepted; MULTI03B/R1 internal coordination is accepted and MULTI03C physical QA is accepted, while authenticated retrieval and caller adapters remain absent, so the active caller blocker and fail-closed `503` remain.
2. ROUTE01 capability is accepted, but cohort runtime routing remains inactive and unauthorized.
3. The accepted uncontrolled-writer count is 0, but that does not authorize M6.
4. The working schema is pre-migration (01–05 not applied; 05 remains repository-only).
5. No restorable backup has been proved.
6. No isolated working-data clone migration rehearsal has been executed.
7. No distinct least-privilege migration account exists; runtime uses broad-privilege root.
8. No complete writer-control window mechanism has been implemented/rehearsed.
9. Global legacy clinical runtime DDL remains outside the accepted V1 encounter paths.
10. Monitoring and safe-return plans await Director acceptance and operational rehearsal.

## 18. Exact next authorized action

```text
NEXT_AUTHORIZED_STEP=Run a separate isolated MULTI05B physical HTTP multipart QA against disposable MySQL 9.6 and private temporary storage, proving canonical encounter-document create, replay, changed-binary conflict, policy denial before staging, storage/schema fail-closed and exact private binary retrieval. C04/C05/C21 remain untouched until that physical path is accepted.
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

Historical MULTI04B implementation record: MULTI04A was accepted at
`99422dfb601282a5c5eceaafa6a02c34dc8b183b`. At creation, MULTI04B was
READY_FOR_CODE_REVIEW and had no physical HTTP QA. MULTI04B is now accepted at
`0daa1e52dd2bda11cad5d50f09ac0516725ef004`; MULTI04C evidence follows below.
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
remain. At that chapter close, the next step was review followed by authorized
MULTI04C physical HTTP QA. No physical invocation occurred during MULTI04B.


### MULTI04C — physical HTTP evidence ready for review (2026-09-20)

MULTI04B is accepted at `0daa1e52dd2bda11cad5d50f09ac0516725ef004`.
MULTI04C passed HC01–HC10 on its exact ephemeral archive using MySQL 9.6.0,
real PHP sessions, private temporary storage and raw HTTP streaming.
Database `mxmed_multi04c_87d850ea9b_mysql96` was synthetic and disposable;
`mxmed` was never selected. Migrations 01–05 served only as QA prerequisites.
The 77-byte PDF matched client-side SHA-256 and all required headers.
Denied/missing/corrupt/configuration/gate-off requests returned the expected bounded
401/404/503 responses without binary or private-path disclosure.
Six table row-count/hash baselines and private storage/source hashes were unchanged.
Residual database/process/session/temp-root counts are all zero.

Full scenario, header, hash and teardown evidence:
[Multipart storage design — MULTI04C](EXPEDIENTE_ENCOUNTER_V1_MULTIPART_STORAGE_DESIGN.md#multi04c--isolated-physical-http-rehearsal-2026-09-20).

MULTI04C evidence is accepted in MULTI05A; the following boundaries remain.
Authentication, streaming and HTTP physical QA are PASS. This does not accept
multipart HTTP writes: `V1_MULTIPART_DOCUMENT_WRITE=CANONICAL_ENCOUNTER_ROUTE_IMPLEMENTED_PENDING_REVIEW`,
`MULTIPART_ACTIVE_CALLER_BLOCKER=true`, `MULTIPART_HTTP_ACCEPTANCE=false`, and
C04/C05/C21 adapters remain false. M6 remains `NO_GO_BLOCKED`; working schema
remains `PRE_MIGRATION`. Backup/clone/migration-account/write-window prerequisites
remain unproven/unready. Working-DB migration, feature-gate activation, runtime
cutover, production and PHASE 3 remain unauthorized. No UI, caller adapter or
application source was changed. The next repository-only write integration step
is a candidate after Director/assistant review, not executed or auto-started here.


### MULTI05A — canonical encounter multipart adapter candidate (2026-09-20)

MULTI04C evidence `10c09aa8fa0960fd5d1d57d72c765755026c2c06` is accepted.
The accepted implementation baseline remains `0daa1e52dd2bda11cad5d50f09ac0516725ef004`
until review. Checkpoint `checkpoint/clinical-pre-multi05a-20260920` was pushed
at the exact pre-head. MULTI05A is repository-only `READY_FOR_CODE_REVIEW`.

Only the existing V1 branch of POST `/encounters/{encounter_key}/documents` now
calls `ClinicalMultipartDocumentService` for multipart requests. The existing
session/gate/encounter-owner/patient-scope authority is reused. Existing early
media-tag/event syntax checks remain in place; canonical class, server-derived
operation and operation policy must pass before entering the adapter. JSON retains
its original `ClinicalEncounterIntegrityService::idempotentCreate` path. Legacy
code and the private GET binary route are byte-for-byte protected.

The adapter requires exactly one PHP-uploaded `file` with UPLOAD_ERR_OK, a regular
`tmp_name` passing `is_uploaded_file`, and string display filename. No file cannot
fall back to JSON. Client MIME/path/operation are not authorities. Configuration
requires explicit `MXMED_CLINICAL_PRIVATE_STORAGE_ROOT` and integer
`MXMED_CLINICAL_STAGING_TTL_SECONDS` in 1–86400; no default is created. The bound is
an implementation limit, not a product retention promise. UTC now plus TTL is passed
to the accepted service; default writable storage mode is used. Storage continues
to own finfo MIME, SHA, 25-MiB maximum, staging, manifest, idempotency and recovery.
PHP upload/post limits remain deployment prerequisites for later physical QA.

Service context uses authenticated doctor, canonical encounter/patient, derived
operation/type and `clinical_document_semantic_request($payload, null)` metadata.
Only HTTP Idempotency-Key is forwarded. The callback locks the encounter FOR UPDATE,
rechecks stored doctor/patient, rechecks originating-order authority for late results,
and reruns the same operation policy before persistence. It never owns the transaction.
The existing insert helper accepts an optional server UUID, replacing only the
builder-generated identity when supplied; default JSON generation is unchanged.
Both paths retain the canonical builder and transactional writer. Fetch remains
`clinical_v1_document_fetch`; no second SQL document writer or response model exists.

Create/replay return 201/200 with meta.idempotency_replay. Cleanup status moves out
of data into meta.binary_cleanup_pending. Configuration/schema errors become bounded
503 V1_MULTIPART_STORAGE_NOT_READY; upload-validation errors are bounded 400; canonical
idempotency/policy mapping is preserved. Unexpected coordination failures return a
non-sensitive 500 and never fall back to legacy, JSON or public storage. Amendment/
replacement multipart and other unadapted surfaces retain their fail-closed guards.

QA: W01–W15 covered by bounded pure/PDO-fake and structural checks, without a PDO
driver connection. Actual canonical builder/writer parameters prove supplied UUID
and unchanged default UUID behavior. Tests cover missing file, arbitrary local path,
missing root, TTL bounds, safe error mapping, replay and cleanup metadata. Structural
checks prove context/operation/semantics, callback locking/policy/persistence order,
no transaction ownership, and exact protected source equivalence. MULTI04B's old
whole-router guard now delegates only the exact MULTI05A changes; MULTI03B's old
no-wiring guard permits only this adapter under the same structural checks.

All requested MULTI04B/A, MULTI03B/A, MULTI02A, M6 CTRL/GUARD/CALLER01/ROUTE01,
encounter-integrity and M5 barrier regressions PASS. PHP lint and git diff checks PASS.
No database connection, physical multipart HTTP request, UI invocation, migration,
working configuration change or C04/C05/C21 adaptation occurred.

```text
PHASE_2_M6_MULTI05A=READY_FOR_CODE_REVIEW
CANONICAL_V1_ENCOUNTER_MULTIPART_WRITE=IMPLEMENTED_PENDING_REVIEW
V1_MULTIPART_DOCUMENT_WRITE=CANONICAL_ENCOUNTER_ROUTE_IMPLEMENTED_PENDING_REVIEW
MULTI05A_PHYSICAL_MULTIPART_QA_EXECUTED=false
CANONICAL_MULTIPART_WRITE_WORKING_DB_ACTIVE=false
MULTIPART_HTTP_ACCEPTANCE=false
MULTIPART_ACTIVE_CALLER_BLOCKER=true
C04_MULTIPART_ADAPTER=false
C05_MULTIPART_ADAPTER=false
C21_MULTIPART_ADAPTER=false
ANY_DATABASE_CONNECTED=false
M6_GO_NO_GO=NO_GO_BLOCKED
WORKING_DB_SCHEMA_STATE=PRE_MIGRATION
M6_COHORT_RUNTIME_ROUTING_ACTIVE=false
PHASE_2_M6_EXECUTION_AUTHORIZED=false
M6_AUTHORIZED=false
WORKING_MXMED_DB_MIGRATION_AUTHORIZED=false
FEATURE_GATE_ACTIVATION_AUTHORIZED_FOR_WORKING_MXMED=false
RUNTIME_CUTOVER_AUTHORIZED=false
PRODUCTION_EXECUTION_AUTHORIZED=false
```

Global GET-DDL removal remains pending; backup/clone readiness, migration account and
write window remain unproven/unready. The next-step candidate is MULTI05B only after
Director/assistant acceptance of this code. It is not started automatically.
