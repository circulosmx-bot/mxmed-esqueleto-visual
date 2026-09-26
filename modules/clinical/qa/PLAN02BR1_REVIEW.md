# PLAN02B-R1: compact capture and Step 6 collector

Plan now has a fixed action bar. One native dialog captures one order, the next
appointment, or the follow-up without leaving the current workspace. Opening uses
an isolated editing copy and adds nothing to the persisted preparation state.
Accepting adds/updates the original logical item; Cancel rejects only the copy.
Escape, backdrop and close resolve dirty input explicitly. Keyboard focus is
contained and returns to the opener. Plan's old tasks-navigation entry is hidden
only in Plan; standalone longitudinal creation remains available elsewhere.

`plan02b-next-steps.js` remains the sole preparation authority for the bar, modal,
Step 6 collector and badge. It reads existing session storage under the same
physician/patient/encounter key without clearing prior preparations or successful
IDs. IN_PROGRESS from an interrupted session restores as an uncertain FAILED
attempt with the same key/payload. Unknown outcomes, including HTTP 5xx and
unverifiable success responses, require reconciliation before editing or discard.
A definite collision can be edited; selecting another slot gets a new identity.
Successful actions remain read-only and cannot be removed as drafts.

The Step 6 badge counts accepted logical elements only. It does not read document
or task counts. Repeated new order capture adds separate items; appointment and
follow-up reopening edits their existing preparation. A linked appointment cannot
be removed while its follow-up depends on it: unlink explicitly or remove the
unsubmitted follow-up first. No deadline is inferred from the appointment.

Internal step navigation preserves prepared actions and uses the existing shared
M7 pathway, including narrative safe-save. In-flight actions block navigation.
Leaving the workspace/patient retains the existing explicit safety. Actual
finalization first resolves preparations and refuses unresolved write outcomes;
entering Step 7 executes no action. Successful identifiers remain stored.

The canonical endpoints, request payload semantics and booking guarantees are
unchanged. The existing PLAN02B date/consultorio/slot selector calls the same Agenda
readers; no slot logic or full Agenda screen is duplicated. Only Step 6's explicit
Confirmar acciones calls the existing three writers. No schema, migration,
lifecycle, printable output, notifications or new domain writer is introduced.

## Disposable review and tests

Director URL:
`http://127.0.0.1:18143/index.html?review_patient=plan02&review_encounter=open&qa_tools=hide`

Use canonical synthetic patient `p_plan02_review`, open encounter `1015`. Before
running write QA verify the Director's effective database is
`mxmed_director_review_lon07c` and its document root is this worktree. These tests
require the existing local Director fixture; never use working MXMED storage.
No schema setup is performed by this block.

Set `PLAN02BR1_ARTIFACTS` to an output directory and run:

- `python3 modules/clinical/qa/plan02br1_browser.py`: compact bar, modal cancellation,
  two independent orders, edit/cancel, badge 4, four viewport sizes, keyboard focus,
  internal stepper/footer navigation, pending finalization guard, session restore,
  real canonical full-flow write/readback with no separate deadline.
- `python3 modules/clinical/qa/plan02br1_recovery_browser.py`: set separate future
  `PLAN02BR1_COLLISION_DATE` (two free slots) and `PLAN02BR1_RECOVERY_DATE` (one free
  slot). Real competing booking, dependency block, slot replacement, successful
  order retention. All writers persist before deliberate client response loss;
  retries reconcile original IDs and exact keys/payloads. Unknown attempts cannot
  be discarded or edited, including after legacy interrupted-state restoration.
- `python3 modules/clinical/qa/plan02br1_supplement_browser.py`: explicit dependency
  removal, repeated accept protection, follow-up-before-appointment linkage,
  preserved deadline, real narrative save, same-tab patient isolation, existing
  Step 6 order writer, zero count for unrelated documents, six baseline slots.
- Reuse `plan02b_agenda_browser.py` with `PLAN02B_ARTIFACTS` set to the R1 output
  directory after the full test to verify the real appointment/follow-up card,
  Seguimientos, attention count and canonical OPEN encounter.

The full fixture is date-anchored to 2026-09-25 (10 days → 2026-10-05). Separate
synthetic dates are used for collision and recovery. Never consume September 28.
Successful synthetic rows remain as evidence; reruns consume additional slots.
The tests use real writer responses as success proof. Only the recovery scenario
intercepts responses, after persistence, to test loss and replay.

Focused FUP01, AGF01, AGF02 and ALR01 disposable gates remain unchanged and pass.
The accepted PLAN02A integrity migration and all backend files are unchanged.
Review screenshots cover compact Plan, each modal, Step 6 with four preparations,
success and partial failure. The application may scroll; preparing more actions
no longer adds inline forms or lists to Plan.
