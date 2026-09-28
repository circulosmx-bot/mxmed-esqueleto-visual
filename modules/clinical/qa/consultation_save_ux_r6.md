# Consultation save UX R6

Starting source: `fc5cd810004dc2ef09379bb573b18e70604aaf3b`, clean and matched
to `origin/ux/consultation-step2-vitals-r1` before editing.

## UI cleanup

- The shared narrative editor no longer contains `Guardar ahora` or its event
  binding. Motivo, Valoración and Plan retain the canonical narrative writer.
- Routine dirty feedback `Cambios sin guardar` is removed from the narrative
  editor and Exploración. Internal dirty detection is unchanged.
- The existing narrative feedback container collapses completely when its status
  is hidden. There is no empty badge row or unused margin. Existing loading,
  transition, failure, conflict and read-only feedback remain available.
- Exploración recovery/validation copy describes continuing or changing steps,
  without suggesting a separate manual save.
- The accepted Plan placeholder, section backgrounds, typography, field sizes,
  title anchors, header, stepper and normal footer anchors are unchanged.

The old dirty Plan row was 44px plus a 5px margin. Removing it moves Próximos
pasos upward by 49px. At tablet/mobile sizes that row previously moved the footer
down by 49px when typing. Dirty Plan now keeps the accepted clean Plan footer
anchor instead of introducing that displacement. Desktop footer anchors were
already fixed and remain identical.

## Generic save UI audit

| Step | Generic step persistence | Explicit domain actions retained |
| --- | --- | --- |
| 1 Motivo | No manual save / routine unsaved badge | Existing clinical actions and failure/recovery paths |
| 2 Signos vitales | No generic step save | Agregar a esta consulta; Guardar cambios when editing an observation; canonical reuse/delete controls |
| 3 Exploración | Guardar exploración remains absent; no routine unsaved badge | Finding validation, conflict/recovery controls and transition-save |
| 4 Valoración | No manual save / routine unsaved badge | Agregar a problemas where eligible; failure/recovery paths |
| 5 Plan | Guardar ahora removed; no routine unsaved badge | Órdenes de estudios, Receta, Próxima cita, Seguimiento |
| 6 Documentos | No generic step save | Confirmar acciones and document/result/version writers |
| 7 Finalizar | No generic step save | Finalizar consulta, Anular consulta and Añadir enmienda |

Other application screens and explicit domain-save buttons are unchanged.

## Writer and behavior preservation

The existing `isDirty`, `eligibleCaptureIsDirty`, `saveEligibleCapture` and
`transitionToSection` functions are unchanged. `saveSection` is unchanged apart
from deleting assignments to the removed UI button. Endpoint, payload, version
handling, conflict handling and failure handling are identical. Plan preparation,
document and lifecycle controllers are unchanged. No schema/API change is made.

## Focused QA

```sh
STEP3_QA_BROWSER="$PWD/modules/clinical/qa/consultation_save_ux_r6_browser.py" \
  bash modules/clinical/qa/step3_head_neck_disposable_gate.sh
STEP3_QA_ARTIFACTS=/tmp/mxmed-save-ux-r6-step3 \
  bash modules/clinical/qa/step3_head_neck_disposable_gate.sh
```

The R6 WebKit gate uses only a disposable canonical DB for Plan commands. It:

- checks clean Next, Previous and stepper transitions with zero writes;
- holds each dirty Plan API response and verifies exactly one writer call,
  no early navigation and no duplicate pending save;
- releases the real canonical response, checks the requested destination and
  reloads the saved narrative;
- forces write-window failure for all three routes, retaining text, draft and
  canonical baseline with no manual-save fallback;
- retries through the existing transition writer;
- opens/cancels all four Plan preparation flows and verifies that a prepared
  order survives narrative autosave without a domain write;
- checks Step 6 Confirmar acciones and Step 7 lifecycle actions;
- audits all seven steps at 1440×900, 1366×768, 820×1180 and 390×844, including
  dirty narratives and examination, without horizontal overflow or extra dialogs.

The existing Step 3 canonical gate also verifies finding validation, clean/dirty
navigation, failure retention, retry and historical reads at four sizes. Prior
QA assertions that depended on the removed generic controls/status were updated
to the accepted transition-save UI.

Visual evidence compares all seven clean steps plus dirty Plan at four sizes to
the pre-change baseline. The status/button row has zero occupied geometry, the
textarea stays the same size, and Próximos pasos uses the recovered space.
Normal shell anchors and placeholder paint remain identical. Desktop pages stay
without scroll; existing narrow page behavior remains stable.

Evidence directory:
`/Users/circulodigital/.codex/artifacts/consultation-save-ux-r6/`

Director section/observation hashes are verified unchanged; the tests never write
Director clinical records. Director is refreshed to the published source HEAD
after commit/push verification.
