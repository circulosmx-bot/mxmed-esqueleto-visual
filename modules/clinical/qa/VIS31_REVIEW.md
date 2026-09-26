# VIS31 consultation width and patient pending navigation

Accepted starting source: `5493b454e656342faaf60989ae92a51e01139aeb`.
Preflight: clean worktree, local HEAD equals remote on
`design/physician-crd03-credentials-ui-v1`. Director PID 4024 serves this worktree.

The shared Consultation context aside is removed from the HTML. Its two-track grid
now has one `minmax(0,1fr)` track, preserving container padding, stepper, progression
and focus mode. All seven steps use the recovered width. Measurement, Plan and
Document workspaces keep their existing content and command handlers.

Patient context remains in the four existing subheader cards. Allergy, medication,
active-problem and last-consultation canonical readers and display semantics are
unchanged. The obsolete duplicate rail renderer is removed; no canonical backend
reader or clinical data is removed. Focus mode stops dimming the three critical
summary cards while leaving the surrounding VIS22 shell secondary.

A conditional compact button sits inside the existing Consulta card, without a
fifth card. The reused canonical reader is:

`GET /api/clinical/index.php/patients/{patient_id}/longitudinal/tasks`

The count uses precisely the former rail's `items.filter(row => row.state === 'OPEN')`
semantics, including both applicable task types. It has no due-date filter and does
not use the physician-wide Agenda badge or the Step 6 prepared-action count. There
is no persisted counter or new backend reader. Copy is `1 pendiente clínico` or
`N pendientes clínicos`; zero, loading and failed reads omit the indicator. The task
request is independent of the other header requests, so a slow/error response does
not delay those summaries. Patient changes clear the projection and invalidate old
responses. Header reconstruction is guarded before refreshing its DOM.

The button uses existing `data-vis01-open="#t-tareas-longitudinal"` navigation.
Bootstrap/M7 retain their existing navigation guards. The existing header action
returns through `mxmedM7OpenFromHeader(patient, 'resume')`; no START, finalize,
pause or appointment mutation is added. The indicator has an accessible count and
patient-specific destination label, keyboard activation and visible focus.

## Focused QA

Evidence directory: `/Users/circulodigital/.codex/artifacts/vis31`.

`VIS31_ARTIFACTS=/absolute/external/evidence python3 modules/clinical/qa/vis31_browser.py`

The gate requires the existing synthetic Director configuration and session. It
creates two clearly named synthetic tasks through the existing authenticated task
API and cancels them in cleanup. It tests zero/one/multiple OPEN counts with terminal
tasks excluded, keyboard navigation, same OPEN encounter on return, late prior-patient
responses and failed reads. Encounter and appointment identity/status snapshots are
unchanged. The final run reports 12 checks passed and zero browser errors.

All seven steps have no context aside and their primary capture width equals the
shared grid width, at 1440×900, 1366×768, 820×1180 and 390×844. No horizontal page
overflow. Screenshots include Steps 2, 5 and 6 at every viewport, the patient header
with pending counts and its zero state. Critical cards have opacity 1 and no filter;
existing dark text on the light card background remains readable. The compact
indicator and existing Consulta actions fit the fixed desktop card height.

The existing DOCUX01-R1 browser gate passed all 33 checks against this patch, with evidence under
`docux-regression`: Plan 4 → 2 → 0 and handoff, modal workflows, desktop picker and
drop uploads, QR decode equality, anonymous mobile upload, canonical document delta
one, replay/cancel/expiry rejection, context changes and responsive modal access.
Unauthenticated token issuance is separately checked as HTTP 401.

JavaScript syntax and git whitespace checks pass. Backend, schema, M7 command
handlers, measurement semantics, Agenda code, Plan module, document upload/capture
module and mobile capture security code are unchanged. Only explicitly synthetic
Director data is used; the working MXMED database is not connected or mutated.

Clean UX remains encounter 1016 OPEN with zero documents and no OPEN VIS31 QA tasks.
No clinical encounter is created or finalized by this work. Screenshots of QR from
the reused regression gate are saved only after their tokens become terminal.

Review URL:
<http://127.0.0.1:18143/index.html?review_patient=plan02ux&review_encounter=open&qa_tools=hide>
