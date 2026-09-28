# Consultation UX R5

## Exploration validation and transitions

The existing `physical_exam` writer validates all eight systems before a dirty
transition. Each empty or whitespace-only abnormal finding receives the existing
`is-invalid` border convention, `aria-invalid="true"`, and an associated inline
message: **Describe el hallazgo para continuar.** The first invalid input receives
focus and its finding cell scrolls into view with `block: nearest`. No state or
finding is discarded. A valid finding, Normal or Sin revisión clears that row's
error through the existing input/state handlers.

The manual Guardar exploración control is removed. The existing status message
uses its former layout allocation, preserving the footer when dirty, validation
or save-error status appears. The narrow examination list measures its ordinary
controls without error helpers; helpers can grow rows inside that list without
growing its shell allocation. The existing desktop four-by-two grid is retained.

The canonical endpoint, payload structure, version checks, dirty detection and
draft recovery remain unchanged. Valid dirty Next, Previous and step transitions
save before navigation. Failed writes retain the draft and block navigation.
Clean transitions make no write.

Run the expanded canonical gate with its own disposable database:

```sh
STEP3_QA_ARTIFACTS=/tmp/mxmed-consultation-r5 \
  bash modules/clinical/qa/step3_head_neck_disposable_gate.sh
```

It checks all states, single and multiple invalid rows, whitespace, accessible
error associations, first-invalid focus, immediate cleanup, successful correction
and save, dirty Next/Previous, clean navigation, failed writes, persistence and
historical compatibility. Validation is checked at 1440×900, 1366×768, 820×1180
and 390×844, including shell rectangles and horizontal overflow. Director records
are checked before/after and are never the target of the canonical writer gate.

## Shared placeholder authority

The Step 1 baseline is Carlito with the existing fallback family, weight 400,
gray `#8a9599`, opacity 1 and line height 1.25. Font sizes remain 18px desktop,
16px at the existing 1200px breakpoint and 14px at 700px. One shared declaration
applies to input/textarea placeholders in all seven steps and the detached Plan
and Document dialogs. Existing placeholder wording and VITALREF values/anchors
are unchanged. Select values and entered clinical text keep their existing styles.

WebKit reports input placeholder line height as `normal` even when a line height
is explicitly declared; textarea placeholders report the baseline pixel height.
The visual comparison accounts for this observed native input behavior. Family,
size, weight, color and opacity match the Step 1 authority exactly. The common
CSS line-height declaration remains 1.25 without changing any component size.

Before/after WebKit evidence for all 28 step/viewport combinations is stored in
`/Users/circulodigital/.codex/artifacts/consultation-ux-r5`. It compares placeholder
wording, control geometry, backgrounds, page extents, header, stepper, title and
footer against starting source `3971847ac4f8c9c1613ef5412ef692ec771c1925`.
Steps 1, 3, 4 and 5 have desktop screenshot comparisons; responsive Step 3
validation screenshots are emitted by the canonical gate.

The existing VITALREF lifecycle logic gate also covers reload/rebind, stepping,
patient/encounter isolation, genuine draft recovery, reused values and server
capture-time payloads:

```sh
VITALREF03_R1_ARTIFACTS=/tmp/mxmed-consultation-r5-vitalref \
  python3 modules/clinical/qa/vitalref03_r1_logic.py
```
