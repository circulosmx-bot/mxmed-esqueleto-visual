# VITALREF03-R1 — reload restores the active reference context

Starting source: `f869632df773691996577abd35bd358f4bf1a9d6`.

Historical R1 behavior below: [Draft recovery R2](step2_draft_recovery_r2.md)
supersedes the mandatory recovery decision for safe measurement drafts. The R1
reload gates now expect automatic restoration; unsafe and ambiguous states retain
their protections.

## Reproduction and cause

Before modifying source, fresh WebKit contexts tested HR, temperature and BP.
Arrows worked initially. With a real unsaved spinner value, reload preserved the
VIS30 draft and disabled the blank capture controls until Recover/Discard. Recover
restored the actual number and stepping worked once per action. That recovery lock
is intentional and remains in place.

A separate clean reload, with no entered value or clinical draft, reproduced the
loss of the active input context: HR and temperature reset to blood pressure.
Their scalar field became hidden, its placeholder disappeared, and selecting the
type again was necessary. BP itself still worked. This reset also reproduced on
the canonical Director reader without the synthetic value fixture.

Failure class: **OTHER — selected measurement type reset during load**.
The independent real-draft condition is **RECOVERED_DRAFT_LOCK**, preserved.
No missing/duplicate handler or replaced number input was observed. Fresh pages
instantiate one controller; Step 2 → Step 3 → Step 2 retains the same input nodes.

External before-change evidence:
`/Users/circulodigital/.codex/artifacts/vitalref03-r1/reproduction.json`,
`clean-selection-reproduction.json`, `production-clean-selection-reproduction.json`.

## Repair

Remember only the explicitly selected type in
`mxmed.m7.ws03.entry-type:<patient>:<encounter>`. This presentation key contains a
catalog code only, never a value, anchor, reference definition or clinical draft.
A clean load/cancel restores that type. Pass the requested type through catalog
construction so it survives empty/rebuilt select options. The current canonical
used-type filter and actual saved/recovered observation still take precedence.

The existing guarded VITALREF01 request rehydrates placeholders and anchors from
the current patient's reference response. No anchor is serialized. Existing input
listeners remain bound once to the live controls; no extra binding is introduced.
Canonical writer, SERVER_AT_SAVE, prior reuse and real VIS30 recovery are unchanged.
No CSS, schema, reference registry, backend contract or review fixture is changed.

## QA

```sh
VITALREF03_R1_ARTIFACTS=/tmp/mxmed-vitalref03-r1 python3 modules/clinical/qa/vitalref03_r1_logic.py
VITALREF03_R1_ARTIFACTS=/tmp/mxmed-vitalref03-r1 python3 modules/clinical/qa/vitalref03_r1_browser.py
VITALREF03_ARTIFACTS=/tmp/mxmed-vitalref03-r1/regression python3 modules/clinical/qa/vitalref03_logic.py
python3 modules/clinical/qa/vitalref01_logic.py
python3 modules/clinical/qa/step2_vitals_r1_logic.py
STEP2_QA_FUNCTIONAL_ONLY=1 STEP2_VITALS_ARTIFACTS=/tmp/mxmed-vitalref03-r1/functional \
  STEP2_VITALS_REVIEW_BASE='http://127.0.0.1:18148/index.html?review_patient=plan02ux&review_encounter=open&qa_tools=hide' \
  python3 modules/clinical/qa/step2_vitals_r4_browser.py
```

The reload gate does not select/toggle the measurement after reload. WebKit and
Chromium verify both pointer arrows and keyboard arrows for six anchored inputs,
HR → temperature → HR, five complete interaction/cancel/leave/return/reload cycles
per engine, no duplicate increments, manual draft recovery and explicit discard.
The logic gate covers patient/encounter scope, stale response rejection, rebuilt
options, unavailable pediatric anchors, canonical used-type filtering, saved/reused
value priority and a spinner-selected payload with SERVER_AT_SAVE. Existing source
safety tests explicitly choose BP for their BP context checks instead of assuming
cancel/reentry resets the active type.

Director checks hash canonical observations before/after and make no clinical
writes. The existing guarded empty-value review is used without fixture changes.
Evidence: `/Users/circulodigital/.codex/artifacts/vitalref03-r1`.
