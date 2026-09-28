# VITALREF02 — inline reference placeholders

Starting source: `ec76ffe67925d7751117c8b4380f0242dedc37a3`, clean and remote-matched.

## Scope and authority

VITALREF01 registry, resolver, sources/version metadata, API, schema and guarded
Director fixtures are unchanged. The frontend extracts compact numeric guidance
from the resolver's authoritative display text only when `reference_available`
and its reference kind allow it. No numeric reference values are duplicated in
production JavaScript. Weight, height and waist retain empty neutral placeholders.
Pediatric/incomplete guidance stays in the existing source tooltip.

| Adult measurement | Value placeholder |
| --- | --- |
| Blood pressure, systolic | `Ref. <120` |
| Blood pressure, diastolic | `Ref. <80` |
| Heart rate | `Ref. 60–100` |
| Respiratory rate | `Ref. 12–18` |
| Temperature | `Ref. 36.5–37.3` |
| Oxygen saturation | `Ref. 95–100` |
| Pain | `Escala 0–10` |
| Weight / height / waist | Empty |

The former visible reference chip is replaced by a 14px info icon beside Medición.
The tooltip retains the full reference, source title/year/version/URL, context and
caveats. It supports pointer, focus, click, Enter/Space and Escape. Applicable inputs
reference that complete metadata through `aria-describedby`, including when a
narrow input cannot display its entire placeholder. Placeholder gray `#657781` has
4.66:1 contrast on white and is lighter than entered clinical text.

Reference rendering assigns only `placeholder` and accessibility/display metadata.
It never assigns `.value`, dispatches input events, changes the measurement snapshot,
stores drafts or contributes fields to a clinical command. Ordinary browser behavior
hides the placeholder on entry and shows it again on clear. Edit mode retains the
saved value. Every render resets all three placeholders before applying current
references, and the existing patient/encounter/epoch guards reject stale responses.

## Geometry

All nine types were checked at 1440×900, 1366×768, 820×1180 and 390×844 against the
accepted source. Patient header, Consultation header, stepper and footer rectangles
are identical at each viewport/type. A small reduction in strip bottom padding
recovers 1px on desktop and 2px on narrow screens; the corresponding outer spacing
preserves the existing allocation and footer. Input dimensions and unit controls
remain unchanged. Desktop capture stays on one line in normal capture mode.

Desktop page scroll: false at 1440×900 and 1366×768. Horizontal page overflow:
false at all four sizes. Existing responsive vertical scrolling is preserved.
No additional full-height reference row remains. Screenshots and measurements are
stored outside the repository in `.codex/artifacts/vitalref02`.

## Verification

```sh
VITALREF02_ARTIFACTS=/tmp/mxmed-vitalref02 python3 modules/clinical/qa/vitalref02_logic.py
VITALREF02_ARTIFACTS=/tmp/mxmed-vitalref02 python3 modules/clinical/qa/vitalref02_browser.py
python3 modules/clinical/qa/vitalref01_logic.py
php modules/clinical/qa/vitalref01_registry_test.php
python3 modules/clinical/qa/step2_vitals_r1_logic.py
STEP2_QA_FUNCTIONAL_ONLY=1 STEP2_VITALS_REVIEW_BASE='http://127.0.0.1:18148/index.html?review_patient=plan02ux&review_encounter=open&qa_tools=hide' python3 modules/clinical/qa/step2_vitals_r4_browser.py
```

The new logic gate uses the actual PHP resolver. It verifies every placeholder's
initial empty value, no dirty state/draft/write, native entry/clear behavior, actual
manual payload using SERVER_AT_SAVE, edit/clear behavior, source-tooltip draft
preservation, dynamic type updates, adult/child/adolescent/infant/missing contexts,
late-response races, reset and read failure. The Director browser gate has 241
checks, rejects placeholder-only submission for every type, compares all four
viewport geometries, verifies source metadata, and hashes canonical observations
before/after. Browser errors and clinical network writes are empty.

Existing create/edit/void/used-type filtering, prior reuse, lost-response retry,
manual Origen validation, VIS32 safe exit/resume, Plan/Receta preparation and
Documentos/Finalizar navigation regressions pass. The legacy VITALREF01 safety gate
now asserts the full reference in its tooltip, retaining its original safety tests.
