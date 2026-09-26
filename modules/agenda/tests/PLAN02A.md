# PLAN02A booking integrity prerequisites

## Existing authority and changes

`agenda_appointments` remains the only appointment record. Authenticated creation
uses `AppointmentWriteController::createFromPayload` and
`AppointmentWriteRepository::createAppointment`. `AppointmentCollisionsRepository`
feeds both canonical availability and the controller's overlap check. It treats
valid intervals as busy except canceled/cancelled/no_show, cancellation flags,
and expired public pending-OTP flows. Tentative therefore already occupied capacity.
Before PLAN02A, `uniq_active_slot` covered pending_otp/confirmed/pending/scheduled,
but not tentative. The appointment/event transaction began after availability was
read, permitting two tentative creates to pass that read concurrently.

Agenda already had an `IdempotencyContract` evaluator and an appointment lifecycle
idempotency domain guard. Neither persisted/reconciled authenticated creation.
PLAN02A reuses the contract evaluator with a receipt on the appointment row itself.
No second booking table, reservation model or workflow authority is added.

## Create protocol

Send `Idempotency-Key` (8–128 ASCII letters, digits, dot, underscore, colon or dash).
The existing physician booking form retains the key after failure, changes it for
semantic edits and clears it after acknowledged success. Transport `occurred_at`
is excluded from the fingerprint. Object keys and supported date representations
are canonicalized; actor-normalized request data supplies the remaining meaning.

The key is hashed with physician and authenticated actor scope. After existing
scope/ingress admission, creation acquires a MySQL connection lock scoped to the
current database, appointment table and physician. Under this lock it reconciles
an existing receipt *before* availability or patient auto-creation, otherwise
checks availability and runs the existing appointment/event transaction. A receipt
is inserted in that same transaction. The lock is released in `finally` and on
connection loss. A 10-second acquisition timeout fails closed with 503.

- First success returns appointment ID and actual lifecycle state (`tentative`
  for the normal physician flow); `meta.write=create` remains the operation marker.
- Exact replay returns the original creation data, `idempotency_replay=true`, and
  `events_appended=0`, even if availability has since changed.
- Same scoped key with another fingerprint returns 409 `idempotency_conflict`.
- GET appointment detail remains the current-state authority after later changes;
  a replay is the original creation result, not a fresh lifecycle snapshot.
- Callers without a key remain compatible but cannot claim keyed recovery.

The lock also prevents concurrent overlapping authenticated creates from both
passing the availability read. The existing database unique start-slot index now
includes tentative, providing exact-slot protection against other writers too.
This change does not claim a new cross-writer interval-exclusion constraint,
change public-flow expiry rules, or redesign rescheduling.

## Migration and compatibility

Apply `modules/agenda/db/migrations/2026_09_26_01_appointment_create_integrity.sql`
once, before the new frontend/backend, with appointment writes paused. Fresh
bootstrap `ready_schema.sql` already contains the final schema and must not also
receive this migration. Existing installations use the migration, not bootstrap.

The migration adds nullable `create_request_key`, `create_request_hash` and
`create_result_json` on existing appointments, a unique request-key index, and
adds tentative to `active_slot_key`. Existing IDs, states, references and events
are unchanged. Run the duplicate preflight printed in the migration. Existing
conflicting occupied starts must be explicitly reconciled; migration never picks
a winner or deletes records. MySQL 8 atomic ALTER fails completely on duplicates.
Rehearsal verified this failure and a successful upgrade preserving existing rows.

Prefer rolling application code back while retaining the additive schema and
stronger slot index. Older code tolerates nullable receipt columns, but cannot
reconcile keyed retries. Do not remove receipts while attempts may be retried.
A schema rollback would explicitly drop the request index/three columns and
restore the old generated expression/index with writes paused; it loses retry
history and reopens tentative concurrency risk. It is not an automatic rollback.
No migration was applied to working MXMED data.

## Follow-up rules

Canonical create/update validation, Agenda projection and existing browser
selection/detail validation now also accept future `tentative` appointments.
The existing eligible states, exact doctor/patient ownership, future-time and
terminal rejection checks remain. Linking does not write the appointment.
Subsequent cancellation leaves the task OPEN with its appointment ID retained;
the no-longer-valid scheduled-appointment display disappears. UI shows ordinary
date/time/location, never the internal tentative code or appointment ID.

Orders remain free-text title/indication canonical documents for the current
encounter. PLAN02 V1 uses source encounter only: there is no specific-order
relationship on the follow-up, structured study catalog or printable/signed output.

## Disposable Director setup and checks

Dedicated patient `p_plan02_review` has an active doctor-1 link and OPEN encounter
1015 in the verified local `mxmed_director_review_lon07c`. Existing Director
fixtures are preserved. The local development router explicitly calls
`plan02a_review_context()` from `modules/clinical/qa/plan02a_review_context.php`
before the PLAN01 routing helper. It adds only `1|p_plan02_review` to the existing
review cohort after checking effective database config, local server and loopback
client; it does not disable authorization, emergency-off or write-window checks.
For `review_patient=plan02`, select `p_plan02_review` in the existing bootstrap.
Review URL: `http://127.0.0.1:18143/index.html?review_patient=plan02&review_encounter=open&qa_tools=hide`.

`plan02a_migration_rehearsal.py` creates a uniquely named disposable schema,
rehearses both migration outcomes and deletes only its own schema on success.
`plan02a_director_gate.py` requires the explicit Director fixture (or a
`plan02a_qa_` database), multi-worker PHP, and two synthetic sessions named
`plan02a-parallel-a/b` with doctor 1 / review-user. Set `PLAN02A_ARTIFACTS` outside
the repository. For a subsequent run choose fresh `PLAN02A_PRIMARY_DATE`,
`PLAN02A_RECOVERY_DATE`, `PLAN02A_RACE_DATE` and `PLAN02A_RUN`. Dates must have real
availability. It fails before writing if the selected race date is occupied.

The concurrency gate holds the actual creation lock until two independent MySQL
connections from separate HTTP workers are observed waiting. Releasing it yields
one success and one HTTP 409 collision. Same-key concurrent requests yield one
creation and one replay. Another request deliberately discards a real successful
response; retry recovers one persisted appointment. No success is fabricated.

Browser QA additionally discarded a real booking response, retried the unchanged
form with the same key, linked the resulting tentative appointment through the
existing follow-up modal, and checked its human-readable Agenda projection.
Focused FUP01/AGF01/AGF02/ALR01 gates cover original eligible states, ownership,
terminal behavior, actions and badge arithmetic. Director checks retain the
September 28 six-slot baseline, Plan draft, open encounter and order capability.
No integrated PLAN02B UI is included.

The optional historical `Gate8DAppointmentLifecycleIdempotencyTest.php` still
fails its fixed byte-hash checkpoint for `api/agenda/index.php`; that checkpoint
already differs at accepted PLAN01 HEAD. Its behavioral assertions execute
successfully before the historical checkpoint. The test/manifest was not rewritten
to conceal that pre-existing limitation; the PLAN02A HTTP/concurrency gates prove
these newly authorized product changes directly.
