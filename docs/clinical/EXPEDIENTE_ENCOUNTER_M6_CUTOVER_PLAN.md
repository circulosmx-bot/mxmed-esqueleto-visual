# Expediente Clínico — M6 backend clinical cutover plan

```text
CHAPTER=CLIN-REFORM-PHASE2-M6-PLAN01
PLAN_STATUS=ACCEPTED
PLAN_ACCEPTED_HEAD=7dc615c772ef611a49229e3e1da91b569d6cf73d
CTRL01_STATUS=READY_FOR_CODE_REVIEW
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
| Accepted clinical source | `115923cac322958ad4f443ab783a8cf19f9c5093` |
| Accepted M5 evidence | `b069fe7cfa66fc71a2aaf323676dc74a411f1ec2` |
| M5 result | `T01_T35=PASS`, accepted |
| M6 execution | not authorized |

M5 proves the accepted repository behavior against isolated, synthetic, disposable databases. It does not prove compatibility of every current client or readiness of the working database.

## 3. Complete runtime caller inventory

Inventory unit: one independently actionable runtime operation family. A row can aggregate repeated call sites only when they use the same endpoint, authority and response contract. `YES (session)` means the server derives the doctor from the authenticated PHP session; a doctor ID supplied by a client is not considered canonical doctor authority.

| CALLER_ID | FILE | RUNTIME_ROLE | OPERATION | ENDPOINT_OR_DIRECT_DB_PATH | V1_GATE_AWARE | USES_CANONICAL_DOCTOR_CONTEXT | USES_CANONICAL_PATIENT_ID | USES_IDEMPOTENCY_KEY | EXPECTED_RESPONSE_CONTRACT | LEGACY_WRITE_CAPABILITY | M6_COMPATIBILITY | ACTION_REQUIRED |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| C01 | `assets/js/app.js` | Expediente context bridge | Resolve active encounter and read encounter detail | `GET /patients/{patient}/encounters/active`; `GET /encounters/{key}` | yes | yes (session) | yes | n/a | `{ok,data,meta}` | no | `READ_ONLY_NO_CUTOVER_IMPACT` | Retain and regression-test under gate ON. |
| C02 | `assets/js/app.js` | Expediente open-patient flow | START when no active encounter exists | `POST /patients/{patient}/encounters` | endpoint switches, caller is not V1-contract complete | yes (session) | yes | **no** | legacy `{ok,data.encounter_key}` assumption | yes | `REQUIRES_ADAPTER_BEFORE_M6` | Add accepted V1 START request/idempotency contract and explicit V1 errors before cohort activation. |
| C03 | `assets/js/app.js` | Evolution note, prescription and generic document composer | Create JSON clinical documents | `POST /doctors/{doctor}/patients/{patient}/documents` | no | no; path doctor ID is client-resolved | yes after bridge | no | legacy scoped document response | yes | `REQUIRES_ADAPTER_BEFORE_M6` | Route encounter-owned writes through `/encounters/{key}/documents`, session doctor authority and idempotency. |
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
| C14 | `modules/agenda/services/ClinicalEncounterBridge.php` | Agenda completion bridge | Find or START encounter after appointment completion | HTTP `GET/POST /patients/{patient}/encounters` | no | **no session/cookie propagation** | patient ID supplied by Agenda | no | legacy list/create response | yes | `REQUIRES_ADAPTER_BEFORE_M6` | Adapt to authenticated canonical command with idempotency or disable bridge during M6. |
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
REQUIRES_ADAPTER_CALLERS=3
MUST_BLOCK_AT_M6_CALLERS=8
READ_ONLY_NO_CUTOVER_IMPACT_CALLERS=4
LEGACY_ONLY_DEFERRED_CALLERS=4
UNCONTROLLED_LEGACY_CLINICAL_WRITER_COUNT=11
UNKNOWN_WRITE_CAPABLE_CALLERS=0
```

The current uncontrolled count is the three active families requiring adapters plus the eight families requiring explicit blocking. Deferred legacy families are not counted as controlled until cohort isolation is proved; if that proof fails they must move to `MUST_BE_EXPLICITLY_BLOCKED_AT_M6`, increasing the count. The mandatory future gate is exactly `UNCONTROLLED_LEGACY_CLINICAL_WRITER_COUNT=0`.

Supporting sources were audited but are not counted as separate clinical-state caller families: `modules/clinical/ui/timeline.php` is covered by the timeline read family C10; `modules/patients/repositories/PatientsRepository.php` only reads attributed/unattributed encounter aggregates for archive metrics and is covered by C08; `assets/js/core/identity.js` calls the patient-identity resolver, which does not write the encounter, record-entry or document authorities listed in the inventory. The latter can still invoke the legacy identity-bridge schema helper and is therefore included in the global runtime-DDL blocker. Repository libraries in `api/_lib/` are implementations reached by the inventoried endpoints, not independent runtime entry callers.

### Old UI compatibility map

| Classification | Current screens/functions | M6 rule |
| --- | --- | --- |
| `READ_ONLY_COMPATIBLE` | Encounter detail/history reads, timeline reads, safe document views | May remain if GET has no DDL/DML and doctor/patient scope is enforced. |
| `LEGACY_DRAFT_ONLY` | Historia Clínica and Exploración Física patient-level drafts; isolated hospital draft work | May remain outside encounter authority; cannot overwrite or masquerade as new encounter history. |
| `NEW_ENCOUNTER_WRITE` | V1 START/FINALIZE/VOID, section, observation, document and amendment commands | Only canonical session authority and accepted V1 contracts after all gates. |
| `UNSAFE_FOR_M6` | Legacy document create/PATCH/replace/replicate, standalone writers, note-capture persistence, unauthenticated Agenda START, active multipart encounter uploads | Adapt or explicitly block before activation. |

### Multipart deferral

`V1_MULTIPART_DOCUMENT_WRITE=DEFERRED_FAIL_CLOSED`. Active consent attachments, order/result uploads and diagnostic uploads use multipart legacy document routes. They are a current cutover blocker because the V1 encounter-document and document-amendment surfaces reject multipart with `503/V1_MULTIPART_STORAGE_NOT_READY`. `MULTIPART_ACTIVE_CALLER_BLOCKER=true`. They may remain only if the initial cohort is demonstrably isolated from those actions; current global-only gating cannot prove that isolation.

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
```

- **01 encounter lifecycle integrity:** the table lacks `voided_at`, `voided_by_user_id`, `void_reason`, `open_guard`, the accepted one-open unique index, lifecycle checks and accepted triggers.
- **02 structured content:** sections, observations and encounter amendments are absent.
- **03 command idempotency:** start-request and general idempotency tables are absent.
- **04 document integrity:** `encounter_ref_id`, final-note and document-revision structures are absent.

The only future migration sequence permitted by the accepted plan is:

1. `2026_09_18_01_encounter_lifecycle_integrity.sql`
2. `2026_09_18_02_encounter_structured_content.sql`
3. `2026_09_18_03_encounter_command_idempotency.sql`
4. `2026_09_18_04_encounter_document_integrity.sql`

`2026_09_17_clinical_encounter_doctor_attribution.sql` remains explicitly prohibited. The 13 NULL doctor rows must remain unattributed.

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

CTRL01 provides a repository-only candidate control-plane primitive in `api/_lib/clinical_m6_cutover.php`. It reads only server environment configuration, defaults OFF, requires exact canonical doctor/patient pairs, derives patient-level membership for future legacy-write blocking, fails closed on malformed active configuration and gives emergency OFF highest precedence.

```text
M6_COHORT_SCOPING_CAPABILITY=CANDIDATE_AVAILABLE_PENDING_REVIEW
M6_COHORT_CONTROL_PLANE=IMPLEMENTED_PENDING_CODE_REVIEW
M6_COHORT_RUNTIME_ROUTING_ACTIVE=false
LEGACY_WRITER_BLOCKING_ACTIVE=false
COHORT_STATE_STORED_IN_CLINICAL_DB=false
```

The candidate does not determine identity, does not read client-controlled cohort enrollment, is not included by the router, and does not activate `MXMED_CLINICAL_ENCOUNTER_INTEGRITY_V1`. After acceptance, a separate caller-hardening chapter must wire canonical session doctor/patient evaluation and patient-level legacy-write guards. Until then, global runtime behavior and the uncontrolled writer count remain unchanged.

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
| `UNCONTROLLED_LEGACY_CLINICAL_WRITER_COUNT=0` | `FAIL` | Current count 11. |
| `WORKING_DB_IDENTITY_PROVEN` | `PASS` | Environment source and read-only identity query recorded. |
| `WORKING_DB_PREFLIGHT_PASS` | `PASS` | Authorized read-only inspection completed; its result is pre-migration and therefore not schema readiness. |
| `DUPLICATE_OPEN_DOCTOR_PATIENT_GROUP_COUNT=0` | `PASS` | Aggregate zero for attributed groups; all legacy rows remain unattributed. |
| `LEGACY_STATUS_BLOCKERS=0` | `PASS` | No noncanonical status observed. |
| `BACKUP_RESTORABLE=true` | `NOT_YET_PROVEN` | No backup/restore rehearsal performed. |
| `CLONE_MIGRATION_REHEARSAL=PASS` | `NOT_YET_PROVEN` | Not executed for working-data clone. |
| `MIGRATION_ACCOUNT_READY=true` | `FAIL` | Runtime is broad-privilege root; no distinct account. |
| `WRITE_WINDOW_READY=true` | `FAIL` | No comprehensive writer pause/block mechanism rehearsed. |
| `SCHEMA_READINESS_REHEARSAL=PASS` | `NOT_YET_PROVEN` | Only synthetic M5/MIG rehearsal, not working-data clone. |
| `FEATURE_GATE_PLAN_ACCEPTED=true` | `NOT_YET_PROVEN` | PLAN01 accepted; CTRL01 cohort control is a candidate pending code review and is not wired. |
| `MONITORING_PLAN_ACCEPTED=true` | `NOT_YET_PROVEN` | Invariants defined; acceptance/operationalization pending. |
| `SAFE_RETURN_PLAN_ACCEPTED=true` | `NOT_YET_PROVEN` | Procedure defined; acceptance/rehearsal pending. |
| `CALLER_COMPATIBILITY_PASS=true` | `FAIL` | Adapters/blocks not implemented. |

`M6_GO_NO_GO=NO_GO_BLOCKED`.

## 17. Unresolved blockers

The first blocker is `UNCONTROLLED_LEGACY_CLINICAL_WRITERS_PRESENT`.

Known blockers, in actionable order:

1. 11 active caller families can bypass or fail the hardened V1 contract; adapters/explicit blocks are absent.
2. Active multipart clinical writes conflict with V1's fail-closed multipart deferral.
3. The candidate cohort control plane is pending code review and is not wired to runtime routing or legacy guards.
4. The working schema is pre-migration (01–04 not applied).
5. No restorable backup has been proved.
6. No isolated working-data clone migration rehearsal has been executed.
7. No distinct least-privilege migration account exists; runtime uses broad-privilege root.
8. No complete writer-control window mechanism has been implemented/rehearsed.
9. Global legacy clinical runtime DDL remains outside the accepted V1 encounter paths.
10. Monitoring and safe-return plans await Director acceptance and operational rehearsal.

## 18. Exact next authorized action

```text
NEXT_AUTHORIZED_STEP=Director/assistant review of M6 CTRL01 fail-closed cohort control plane before wiring caller adapters and explicit legacy-write blocks. No backup, clone, migration, feature-gate activation or cutover authorized.
```
