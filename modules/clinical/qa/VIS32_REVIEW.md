# VIS32 dedicated consultation mode

Accepted starting HEAD: 31a4e9bfd457b13d3662f24df00003b8629ff967.
Preflight worktree was clean and matched the remote branch
design/physician-crd03-credentials-ui-v1. Director PID 4024 serves this worktree.

## Behavior and boundaries

The general patient record now exposes six primary tabs: Resumen, Historial,
Órdenes y resultados, Documentos, Recetas and Administrativo. Consulta's existing
Bootstrap target remains hidden/inert for established internal callers; there is
one existing workspace and no new browser tab, popup, modal, router or history state.

The patient Consulta card retains canonical START/resume and Finalizar shortcuts.
An explicitly entered, visible OPEN workspace activates body.mx-consultation-mode
through the existing focus-mode synchronizer. The global header/sidebar/footer,
general tab row, patient-switch/exit actions and summary navigation links are
removed from display and keyboard interaction. Patient identity and existing
canonical clinical summaries remain readable. VIS31 pending count uses its same
reader and becomes plain text while dedicated mode is active. Outside the mode its
original task navigation returns.

The six-tab grid has no reserved Consulta slot. The dedicated mode recovers sidebar
and tab-row space with responsive outer gutters. The old focus-mode entry scroll,
designed to move below the large general shell, is replaced with patient-header
positioning and focus on the explicit exit; this keeps identity visible on mobile.
Existing workspace hierarchy, full-width layout and seven-step navigation remain.

Volver al expediente saves dirty eligible capture through saveEligibleCapture
and existing WS03/section writers. It remains in place on save failure/conflict.
It blocks busy clinical operations, uses DOCUX01's existing cancel/close flow for
pending capture, and never calls a terminal encounter command. Clean exit does not
ask for a second OPEN-leave confirmation. Its destination is the most recent valid
non-Consultation primary tab for that patient, falling back to Resumen. Exit focus
returns to that tab.

Plan exposes a narrowly scoped mayLeaveView check: prepared actions persist in
the existing encounter-scoped state/session storage, in-progress actions block,
and the existing modal close rules still apply. Patient-switch and finalization
guards keep their original mayLeave behavior. Resume repaints the existing badge
and pending-only collector after the temporary loading state. No preparation is
confirmed, discarded or written merely by leaving the view. Stored failed/ambiguous
actions retain their existing retry/reconciliation state.

No backend, schema, encounter authority, navigation backend, mobile capture page,
Agenda implementation, measurement writer or terminal writer changed. The three
Plan actions remain Órdenes de estudios, Próxima cita and Seguimiento. No Receta
integration was added.

## Validation

Evidence: /Users/circulodigital/.codex/artifacts/vis32.

- Navigation gate: 24 passing checks. Six direct modules without START; explicit
  START creates one OPEN encounter; three exit/resume cycles create zero additional
  encounters. Last-tab return, clean exit without writes/dialogs, dirty Motivo and
  Plan saved through existing writers, write-window failure retaining local content,
  Plan preparation/badge persistence without exit writes, in-progress action block,
  Finalizar shortcut to Step 7 without terminal effect and no per-step history entries.
- Safety gate: seven passing checks. Canonical pending indicator read-only inside,
  task navigation restored outside, authorized QR cancellation on view exit, upload
  in-flight exit block, VERSION_CONFLICT preserving local draft, anonymous issuance
  still HTTP 401 and zero browser errors.
- Existing DOCUX01-R1 gate: all 33 checks pass, including Plan 4 → 2 → 0, dropzone and
  picker, decoded QR equality, actual anonymous mobile upload, canonical readback,
  delta one, terminal replay/cancel/expiry rejection and responsive dialogs.
- Final visual/focus gate: 1440×900, 1366×768, 820×1180 and 390×844. No horizontal
  overflow or phantom sidebar offset, identity visible on entry, six balanced tabs
  outside, summary navigation absent inside, reachable exit and focus returned to
  the active record tab. Screenshots reviewed for both modes and Steps 1, 5 and 6.
- JavaScript syntax and whitespace checks pass. Backend/Agenda/terminal/measurement
  diffs are empty. The Plan action-bar composition is byte-for-byte unchanged.

Only the explicitly synthetic Director DB mxmed_director_review_lon07c was used.
START tests use review-no-open; the newly created test encounter is explicitly
voided through the canonical API in cleanup, restoring that patient's no-OPEN state.
This cleanup is separate from view-exit semantics. Preexisting encounters 1015 and
1016 remain OPEN. Technical narratives are restored after save tests. A temporary
clean-patient pending task is cancelled after the read-only indicator test; clean
patient 1016 has zero documents and no OPEN QA tasks. QR evidence is saved only
after tokens are terminal, and bearer URLs are redacted from the Director log.

## Reproduction and review

With the existing synthetic Director session/runtime and an absolute external
VIS32_ARTIFACTS directory:

~~~sh
python3 modules/clinical/qa/vis32_browser.py
python3 modules/clinical/qa/vis32_safety.py
python3 modules/clinical/qa/vis32_visual.py
DOCUX01R1_ARTIFACTS="$VIS32_ARTIFACTS/docux-regression" \
  python3 modules/clinical/qa/docux01r1_browser.py
~~~

The DOCUX gate additionally needs DOCUX01R1_QR_DECODER, compiled from the existing
local Swift/Vision helper per DOCUX01_R1_REVIEW.md. Do not run these fixture-writing
gates against a working database.

Expediente review:
<http://127.0.0.1:18143/index.html?review_patient=plan02ux&qa_tools=hide>

Dedicated Consultation review:
<http://127.0.0.1:18143/index.html?review_patient=plan02ux&review_encounter=open&qa_tools=hide>

The same patient and encounter are accessible through either review URL. Normal
product entry and exit are the Consulta card and Volver al expediente.
