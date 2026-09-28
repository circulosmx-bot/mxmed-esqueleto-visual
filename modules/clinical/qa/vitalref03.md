# VITALREF03 — explicit number navigation

Starting source: `57e9e509092e7595acdd492b428e860d8376da87`.

The WS03 frontend keeps `entry_anchor` and the originating VITALREF01 `reference_id`
in a transient map keyed by the number input. The existing resolver response is
unchanged. BP anchors use its two category boundaries; range anchors use the
midpoint rounded to the configured UI step. Rendering, source guidance, hover,
and focus never set a value or emit an input event. The guarded reference loader
clears this map immediately on context changes and rejects stale responses.

| Field | Anchor | Step | Empty Up / Down |
| --- | --- | --- | --- |
| BP systolic | 120 | 1 | 121 / 119 |
| BP diastolic | 80 | 1 | 81 / 79 |
| Heart rate | 80 | 1 | 81 / 79 |
| Respiratory rate | 15 | 1 | 16 / 14 |
| Temperature | 36.9 | 0.1 | 37.0 / 36.8 |
| Oxygen saturation | 98 | 1 | 99 / 97 |
| Pain | none | 1 | 1 / 0, native minimum behavior after explicit interaction |
| Weight / height / waist | none | any, preserved | existing native/manual behavior |

Small labelled arrow buttons occupy the existing number input footprint. The
input retains `type=number`, its accessible label, manual/mobile editing, native
step alignment and the existing broad `min=0` without a reference-derived maximum.
ArrowUp/ArrowDown and button activation share the same handler. Once there is a
value, native `stepUp`/`stepDown` uses that actual value. Temperature arrow results
retain one decimal place. Pain's scale is never treated as a midpoint reference.

Only explicit input changes enter the existing draft/dirty flow. Draft snapshots
and canonical payloads contain actual input values, never anchor metadata. Save
eligibility also requires the active numeric input(s) and Origen. Existing create,
edit, cancel, patient guards, SERVER_AT_SAVE and canonical historical reuse remain
in place; their backend contracts and the reference registry are unchanged.

## Reproducible QA

```sh
VITALREF03_ARTIFACTS=/tmp/mxmed-vitalref03 python3 modules/clinical/qa/vitalref03_logic.py
VITALREF03_ARTIFACTS=/tmp/mxmed-vitalref03 python3 modules/clinical/qa/vitalref03_browser.py
STEP2_QA_FUNCTIONAL_ONLY=1 STEP2_VITALS_ARTIFACTS=/tmp/mxmed-vitalref03/regression \
  STEP2_VITALS_REVIEW_BASE='http://127.0.0.1:18148/index.html?review_patient=plan02ux&review_encounter=open&qa_tools=hide' \
  python3 modules/clinical/qa/step2_vitals_r4_browser.py
python3 modules/clinical/qa/step2_vitals_r1_logic.py
php modules/clinical/qa/vitalref01_registry_test.php
```

The logic gate uses the actual PHP resolver and WS03 with controlled writer
responses, including explicit spinner submission, saved-value stepping and
patient response races. The browser gate checks real pointer/keyboard input in
WebKit and Chromium, phone manual input, and all nine types at 1440×900,
1366×768 and 390×844 against the committed starting source. Patient header,
Consultation header, stepper, title, current values, footer and input rectangles
must be identical; page height must be unchanged and no horizontal overflow may
appear. Desktop content must still fit without page scrolling.

The Director browser gate uses the existing guarded review fixtures for empty
capture, blocks writes and hashes canonical observations before/after. The R4
functional gate covers successful/failed/duplicate/idempotent historical reuse,
provenance tooltips, retained manual drafts and navigation. These checks do not
alter canonical patient data or fixtures.

Evidence directory for this delivery:
`/Users/circulodigital/.codex/artifacts/vitalref03`.
