# PLAN02B: próximos pasos

Step 5 preserves the narrative editor and adds three optional compact components.
The dedicated `plan02b-next-steps.js` coordinates existing canonical writers:

- `POST /api/clinical/index.php/encounters/{key}/documents`: one generated order per draft, current encounter, existing `m7_ws04` payload.
- `POST /api/agenda/index.php/appointments`: authenticated booking, existing idempotency, mutex and tentative slot uniqueness.
- `POST /api/clinical/index.php/patients/{id}/longitudinal/tasks`: explicit follow-up, real appointment ID only after successful booking; independent optional deadline.

There is no combined backend object or transaction. Each action retains its key,
payload, result and DRAFT / IN_PROGRESS / SUCCESS / FAILED / BLOCKED_BY_DEPENDENCY
state. Unchanged retries use the saved key and payload. Semantic edits to pending
actions create a new identity. Successful actions are never resubmitted. A collision
refreshes canonical availability and requires explicit selection of another slot.
Session storage is scoped to physician, patient and encounter; interrupted requests
restore as retryable and reconcile through server idempotency. Storage is local to
the browser tab, not a cross-device workflow service.

Step, patient and workspace departure use the explicit pending-action guard.
Confirming actions does not save the Plan narrative or finalize the encounter.
The standalone follow-up control and Step 6 remain available.

Agenda references are rendered only on persisted appointment cards, using existing
authorized appointment-detail and patient-task readers. The minimized AGF01
projection is unchanged. Task state remains canonical; per-refresh caches avoid
repeated reads. Reader failure omits the reference and does not block Agenda.
`Sin fecha límite` changes only the label; ALR01 still counts overdue plus today.
No direct order-task relationship, schema, migration, PDF, signature or notification
was added.

## Director review

Runtime: `http://127.0.0.1:18143/index.html?review_patient=plan02&review_encounter=open&qa_tools=hide`

Canonical synthetic patient `p_plan02_review`, open encounter `1015`, physician `1`.
The Director must use disposable database `mxmed_director_review_lon07c`; never
point these write scenarios at working MXMED. Existing Director router and session
setup are prerequisites, not product authentication bypasses.

1. Open Plan, preserve its text, and add two study orders.
2. Choose a new appointment, relative target 10 days, Consultorio Norte, then an
   explicitly selected canonical available slot.
3. Add a follow-up and select the displayed next appointment. Leave the independent
   deadline unset. Review and confirm actions.
4. Verify orders, appointment and follow-up individually; the encounter stays open.
5. In Agenda, the booked card and Seguimientos show the same pending follow-up.

The original six-slot baseline on 2026-09-28 is reserved and must not be consumed.
This fixture's relative-date proof is anchored to 2026-09-25 → 2026-10-05.

## Reproduce focused browser proofs

Requires Python Playwright/Chromium, the running disposable Director and mysql CLI
for canonical encounter readback. Set `PLAN02B_ARTIFACTS` to an existing output
folder. Run `plan02b_full_browser.py`, then `plan02b_agenda_browser.py`; these prove
four responsive widths and canonical readback. `plan02b_optional_browser.py` covers
six additional combinations, existing-appointment reuse, deadline preservation
and navigation discard. `plan02b_narrative_browser.py` verifies an unsaved Plan
narrative survives action confirmation.

For `plan02b_recovery_browser.py`, set `PLAN02B_COLLISION_DATE` and
`PLAN02B_RECOVERY_DATE` to separate future dates with at least two and one canonical
available slots respectively. The collision proof creates a competing real booking.
Response-loss proof lets each writer persist, aborts the client response, then
asserts identical-key/payload retries return the original IDs. Synthetic records
are intentionally retained as evidence; reruns consume additional slots.

Accepted regressions: FUP01, AGF01, AGF02, ALR01 disposable gates; PLAN02A Director
integrity gate on separate dates; PLAN01 draft/modal, Resolve/Cancel and return
context; AGR baseline availability and booked-card rendering. AGF01's label
expectation is updated to the explicitly required `Sin fecha límite`.

Disposable setup repair: the existing consultorios migration's two LONGTEXT
normalizations for `logo_url` and `foto_url` were applied to the review database,
whose TEXT columns prevented the canonical consultorio reader from operating.
No migration file or product domain schema was changed.
