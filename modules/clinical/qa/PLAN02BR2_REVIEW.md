# PLAN02B-R2: pending queue and clean UX review

The Step 6 collector and secondary badge now derive from the same unresolved
subset of the existing PLAN02B state. DRAFT, FAILED, BLOCKED_BY_DEPENDENCY and active
IN_PROGRESS remain visible; SUCCESS disappears from the queue and count. Stored
success IDs, payloads and idempotency keys remain intact. All-success rendering is
a single compact message with no confirm button or action rows. Orders remain in
the canonical document surface; appointments and tasks remain in their domains.
The existing document refresh control refreshes the lower list without changing
its layout or tools. There is no new history or combined workflow record.

The shared dialog shell uses the existing `--mm-acc-activo` teal token (#00738F),
existing clinical radii and neutral footer styling. White primary text has a
contrast ratio above 4.5:1. Patient display comes only from the established
patient-ID-keyed label cache; missing/generic/technical labels are omitted, never
replaced by an ID or a previous patient's header. Selected-slot instructions
replace the unselected prompt. Follow-up linkage remains explicit, with the
prepared appointment displayed separately and no deadline inferred.

No canonical writer, scheduling logic, lifecycle, schema, migration, lower Step 6
tool or Órdenes y resultados implementation changes in this block.

## Separate Director contexts

- Technical QA: `http://127.0.0.1:18143/index.html?review_patient=plan02&review_encounter=open&qa_tools=hide`
  Patient `p_plan02_review`, encounter `1015`; prior stress evidence remains intact.
- Clean UX: `http://127.0.0.1:18143/index.html?review_patient=plan02ux&review_encounter=open&qa_tools=hide`
  Patient `p_plan02ux_review`, encounter `1016`, physician `1`.
  Display name: Elena Rivera Demostración. All identity data is synthetic.
  One OPEN encounter with a short Plan, zero seeded orders/tasks/appointments.

The running loopback Director serves the same worktree and disposable database
`mxmed_director_review_lon07c` for both query-selected contexts. Only the clean
patient's own records are seeded; no evidence cleanup or schema statements run.
`plan02br2_seed_ux.py` is insert-only, asserts the dedicated database, and refuses
an inconsistent existing encounter. It is opt-in setup, not a product endpoint.
`plan02br2_review_context.php` reuses the existing strict loopback/database/cohort
checks and adds only the new synthetic physician/patient pair to the review
allowlist. It is never loaded by product entrypoints. The local Director router
calls it instead of `plan02a_review_context()` and maps `review_patient=plan02ux`
to the clean patient; all product API requests still use canonical handlers.

Verify the Director's effective database and document root before running write
QA. Do not point these scripts at working MXMED storage. No credentials or database
contents belong in the repository.

## Focused proofs

Set `PLAN02BR2_ARTIFACTS` to an output directory. Requires the local Director fixture
and Python Playwright/Chromium; fixture verification additionally uses PyMySQL.

1. Run `plan02br2_browser.py` with `PLAN02BR2_FULL_DATE` set to a future canonical
   day with a free slot. Real two-order/appointment/linked-task confirmation:
   badge 4 → hidden; no pending success rows; canonical order readback and lower
   list refresh; restored SUCCESS remains excluded; finalization confirmation is
   reached and dismissed, leaving the encounter OPEN. Covers all four widths,
   keyboard focus, safe cancellation and internal navigation without action writes.
2. Run `plan02br2_recovery_browser.py` with separate `PLAN02BR2_COLLISION_DATE`
   (two free slots) and `PLAN02BR2_RECOVERY_DATE` (one free slot). A real competing
   booking leaves exactly two pending items after two order successes; retry
   clears the queue. Each writer also persists before deliberate response loss;
   identical-key/payload recovery retains IDs and legacy state restoration.
3. Run `plan02br2_ux_browser.py` for the clean context: four viewport sizes, actual
   patient name, unified teal controls, selected-slot copy, focus return, zero
   domain writes and zero accumulated orders/tasks/appointments. The visual test
   uses an unreserved November 2 search date. It does not redirect stress tests.

These are the R2 acceptance scripts. R1 scripts remain historical proofs of the
prior badge semantics; their success-count expectations are superseded by R2.
FUP01, AGF01, AGF02 and ALR01 focused disposable gates remain unchanged.
Keep the six-slot September 28 baseline unused. Write tests intentionally retain
their evidence under the technical patient; reruns consume additional QA slots.

For Director UX review: open the clean URL, choose Plan, prepare two orders in
separate modals, select an available appointment, then explicitly link a follow-up
without a separate deadline. Step 6 shows four pending elements. Confirm there;
the queue and badge clear after success while the canonical records remain.
The lower document workspace may scroll and is intentionally not redesigned.
