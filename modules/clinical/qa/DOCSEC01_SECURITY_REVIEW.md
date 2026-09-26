DOCSEC01 + DOCSEC01-R1 security repair

Accepted starting source: `9c5429b2162c2b3a2f864b37e05d802da8b4b1a5`.
R1 continues the uncommitted authorization patch and preserves its two QA files.
No UI, schema, capture model or document writer was introduced.

The shared issuer now requires `clinical_require_doctor_context`. With an
encounter it resolves canonical ownership, verifies the supplied patient and
requires the active doctor-patient link. Without an encounter it requires that
same active patient scope. Status, cancellation and consumption use the same
physician scope. Public upload/signature retain bearer authority.

All four issuance callers were audited: M7 capture (`m7-ws04.js`), note-modal
capture, consent identity attachment and patient/doctor remote signature
(`app.js`). The latter three accept an optional encounter. The phone only calls
upload/signature; status/cancel/consume are desktop operations. The token remains
128 cryptographic bits, with no weak RNG fallback and failure before insertion.

Routes relative to `/api/clinical/index.php`:

| Operation | Route | Authority |
| --- | --- | --- |
| Issue | POST /note-capture-tokens | Physician + canonical scope |
| Status | GET /note-capture-tokens/{token} | Physician + canonical scope |
| Cancel | POST /note-capture-tokens/{token}/cancel | Physician + canonical scope |
| Consume | POST /note-capture-tokens/{token}/consume | Physician + canonical scope |
| Upload | POST /note-capture-tokens/{token}/upload | Scoped bearer |
| Signature | POST /note-capture-tokens/{token}/signature | Scoped bearer |

Transaction audit and implementation:

| Writer | Existing ownership | R1 integration |
| --- | --- | --- |
| Canonical encounter | Multipart service owns STAGED and clinical transactions; rejects outer transactions | Token completion joins the existing clinical insert callback and therefore its commit/rollback |
| Guarded legacy encounter | Gateway previously autocommitted; schema preparation can issue DDL | Prepare schema before transaction; wrap existing gateway and token completion together; no DDL inside transaction |
| Patient-level legacy | Gateway previously autocommitted | Same transaction wrapper around existing gateway and token completion |
| Remote signature | One autocommit UPDATE | Same statement under the token mutex; SQL failure has no partial signature transition |

A MySQL `GET_LOCK` mutex serializes all token reads that can expire it and all
terminal operations, including consume. This reuses Agenda's connection-scoped
DB mutex pattern. It survives the canonical writer's internal commits, is released
in `finally` and on disconnect, and works across HTTP workers. The name uses the
database and persisted row ID, not the bearer spelling: collation aliases cannot
acquire different locks for one token. A fresh token read and the existing expiry
check happen after acquisition. No new persisted status is needed.

Once a request observes valid pending state under the mutex, it owns that terminal
attempt until success or failure. Expiry before this point rejects capture; expiry
during an already authorized writer does not contradict its eventual commit.
Cancellation returns 409 after upload/signature/consume/expiry, and only replays
200 for an already cancelled token. Concurrent C21 losers may now return terminal
409; canonical idempotency keys, receipts, private storage and reconciliation
remain in place.

Document creation and token completion are one InnoDB transaction. A definite
rollback leaves no document and a pending token. If COMMIT succeeds but its
acknowledgement is lost, both document and uploaded token are visible to the next
locked read. There is no compensating reset to pending. Existing canonical storage
recovery remains responsible for ambiguous filesystem/manifest outcomes; this
repair does not introduce another reconciliation system. Legacy binary cleanup
behavior is unchanged; the atomic guarantee covers the document and token rows.

Focused reproducible gates:

```sh
php modules/clinical/qa/docsec01_entropy_test.php
DOCSEC01_ARTIFACTS=/absolute/external/evidence python3 modules/clinical/qa/docsec01_security.py
DOCSEC01R1_ARTIFACTS=/absolute/external/evidence python3 modules/clinical/qa/docsec01r1_director_races.py
DOCSEC01R1_ARTIFACTS=/absolute/external/evidence python3 modules/clinical/qa/docsec01r1_races.py
php modules/clinical/qa/multi06b_c21_adapter_test.php
php modules/clinical/qa/multi05a_encounter_multipart_adapter_test.php
php modules/clinical/qa/multi03a_private_storage_test.php
```

Director gates require the existing synthetic runtime at 127.0.0.1:18143,
`mxmed_director_review_lon07c`, patient `p_plan02_review`, encounter 1015 and its
existing physician session. Clean UX patient/encounter 1016 is not used for writes.

The isolated race gate streams a clone of that explicitly synthetic database into
`docsec01r1_qa_<pid>`, copies accepted source plus the current patch into a temporary
deployment, and runs four dedicated PHP HTTP worker processes. Its ordinary non-cohort deployment
configuration permits the actual legacy route; it never alters Director's guard
or adds a production bypass. Real multipart requests call the actual gateway.
The controller holds the exact DB mutex until distinct connection IDs are observed
waiting for it. A document-table barrier proves upload ownership before racing
cancel/signature and before crossing expiry. Timing is not the serialization proof.
The temporary server, database and files are removed in `finally`.

The isolated gate also executes the actual transaction helper and canonical
multipart service with CLI-only injected precommit and lost-COMMIT-acknowledgement
faults. A separate SQL connection checks committed state. An actual oversized
signature field causes a database error, leaves pending, and permits a valid retry.
No production fault injection hook exists.

Evidence is sanitized: no bearer URLs or response bodies containing tokens are
written. Isolated access logs are discarded. Director test tokens are terminal
and redacted from its access log. Reports record HTTP codes, row counts, terminal
states and independent DB connection IDs.

The historical `multi06b_c21_static_check.sh` matcher already fails at accepted
HEAD. It was neither changed nor counted as a new regression; functional gates
above verify the required C21 behavior. Repairing that historical matcher is
separate QA maintenance, not necessary for this security evidence.
