# Step 3: Cabeza y cuello

`head_neck` is the eighth entry in the existing exploration system catalog. The
desktop grid keeps its original seven items in place and fills the fourth row of
the right column. No specialized subfields or prepopulated findings are added.

The existing `physical_exam` reader, snapshot, dirty detection and PUT writer
handle the new key. Stored states remain `NORMAL` and `ABNORMAL`; `NOT_REVIEWED`
uses the established omitted-system representation. A missing historical key
therefore displays **Sin revisión** without a write, backfill or schema change.
The existing API validator already permits nonblank system identifiers.

On narrow screens the systems retain one column. A measured cap preserves the
previous seven-row allocation and footer coordinates; the new row is reachable
by scrolling or focusing its controls. Measurements update after resize/font
layout in an animation frame. Desktop row dimensions are unchanged.

## Canonical regression gate

Run with local MySQL, PHP, Python and Playwright WebKit available, plus the
existing Director presentation runtime on port 18148:

```sh
STEP3_QA_ARTIFACTS=/tmp/mxmed-step3-head-neck \
  bash modules/clinical/qa/step3_head_neck_disposable_gate.sh
```

The gate creates and removes its own uniquely named disposable database. Its
browser uses the real shell and canonical API against that database. It does not
write Director clinical records. It checks:

- Exact options and finding semantics against Cardiovascular.
- Accessible labels, no prefilled finding and no false dirty state.
- Required abnormal findings and disabled/cleared normal or unreviewed findings.
- Actual save/reload, finding-only edits and omitted unreviewed storage.
- Save before navigation; failed saves block navigation and retain the draft.
- All seven historical systems unchanged; missing `head_neck` defaults to
  `NOT_REVIEWED` without a canonical write or record mutation.
- The narrow stacked field remains editable and saves/reloads through the same
  writer; no JavaScript errors or unexpected clinical writes.

## Director review

The external loopback review router supports `review_step=exam` only for the
existing synthetic `review_patient=plan02ux&review_encounter=open` context. It
opens the real examination step after the established consultation bootstrap.
It adds no fixture data or clinical requests.

<http://127.0.0.1:18148/index.html?review_patient=plan02ux&review_encounter=open&qa_tools=hide&review_step=exam>

Focused before/after WebKit evidence lives under
`/Users/circulodigital/.codex/artifacts/step3-head-neck-r1`. The visual check
compares the first seven controls, patient/consultation headers, stepper, title,
save action and footer rectangles to the starting source at 1440×900,
1440×880, 1366×768, 820×1180 and 390×844. The desktop viewports retain no page
scroll; narrow viewports retain their existing page extent and scroll behavior.
All five retain no horizontal overflow.
