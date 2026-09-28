# Consultation placeholder normalization R2

Starting source: `f1e81a8a0b038ff09e6baa1ad09b0457a426fad9`, clean and matched to
`origin/ux/consultation-step2-vitals-r1` before editing.

## Semantics and presentation

The Plan narrative now has a native placeholder:
`Solicitar estudios de control y revisar los resultados en la próxima consulta.`
The presentation controller assigns only `placeholder`; the existing M7 controller
still owns values, saved narratives, drafts, dirty detection and canonical writes.
The accepted shared Consultation placeholder CSS is reused without modification.
No input geometry or shell styles change.

Measured Step 1 in WebKit:

| Viewport | Family | Size | Weight | Line height | Color | Opacity |
| --- | --- | --- | --- | --- | --- | --- |
| 1440×900 / 1366×768 | Carlito, IBM Plex Sans, system fallbacks | 18px | 400 | 22.5px | rgb(138,149,153) | 1 |
| 820×1180 | Same | 16px | 400 | 20px | Same | 1 |
| 390×844 | Same | 14px | 400 | 17.5px | Same | 1 |

WebKit reports native single-line input placeholder line height as `normal`;
the author rule remains `1.25`. Narrative placeholders match Step 1 exactly.
Numeric reference copies fit the existing controls at all four sizes, including
the separate blood pressure inputs; no compact font override is needed.

## Audit map

| Step | Guidance | Real values preserved |
| --- | --- | --- |
| 1 Motivo | `Ejemplo: …` is `PLACEHOLDER_GUIDANCE` | Saved narrative / recovered physician draft are `REAL_CLINICAL_VALUE` |
| 2 Signos vitales | VITALREF numeric references are `PLACEHOLDER_GUIDANCE` | Measurement/code/unit/source selections and genuine capture drafts remain real values |
| 3 Exploración | Eight finding inputs: `Describe el hallazgo` is `PLACEHOLDER_GUIDANCE` | Actual examination findings and review-state selections remain real values; `Sin revisión` retains select styling |
| 4 Valoración | Diagnostic guidance is `PLACEHOLDER_GUIDANCE`, clean value empty | Saved assessment / recovered draft remain real values |
| 5 Plan | Plan sample is `PLACEHOLDER_GUIDANCE`, clean value empty | Saved Plan / recovered draft remain real values; order, prescription, appointment and follow-up controls retain genuine prepared values |
| 6 Documentos | No injected sample narrative; empty description/title fields have no shown text | Existing documents, order selections and current upload drafts remain real values |
| 7 Finalizar | No injected sample; empty void/correction/adenda fields have no shown text | Genuine reasons/corrections remain real values; original content stays read-only |

The browser audit includes all workspace input/textarea/select controls, including
conditional forms, and detached order, prescription, appointment (existing/new),
follow-up and upload dialogs. The JSON evidence records each field's value,
placeholder, visibility and classification. Empty fields with no shown text have
no classification; select prompts are guidance with unchanged select styling.

## Clean Director review without canonical data mutation

The old `plan02br2_seed_ux.py` inserted the sample as a saved Plan. Future clean
seeds no longer insert a Plan section. The existing Director row is retained.

`consultation_placeholder_r2_review.js` is QA-only and injected by the external
Director router only for explicit loopback `review_placeholders=clean` URLs.
It projects an empty narrative only for the identified synthetic seed:
patient `p_plan02ux_review`, open encounter `1016`, Plan version `1`, exact sample,
empty payload, and creation/update timestamp `2026-09-25 22:48:46`.
It preserves all row metadata, including the writer's version token. There is no
database update, API change or product filtering by sample text. Later genuine
saved text, even the same wording, is retained. Other patients/encounters and
commands are untouched. The legacy review URLs keep their original data view.

## Focused QA

```sh
STEP3_QA_BROWSER="$PWD/modules/clinical/qa/consultation_placeholder_r2_browser.py" \
  bash modules/clinical/qa/step3_head_neck_disposable_gate.sh
python3 modules/clinical/qa/consultation_placeholder_r2_audit.py
```

- WebKit compares Step 1/4/5 placeholder paint at four approved sizes.
- Clean focus/navigation creates no draft, dirty state, writer call or section.
- Typing/clearing uses native placeholder visibility and darker real text.
- Existing physician draft is recoverable without writing.
- `Solicitar resonancia de rodilla.` saves and reloads through the existing Plan
  endpoint in a disposable database; no Director clinical writes occur.
- Same-wording genuine saved text remains an actual value after reload.
- The clean-review projection preserves version metadata and genuine records.
- VITALREF reference copies fit existing inputs without value injection.
- All seven steps at four viewports are compared to a pre-change baseline:
  field geometry, title, header, stepper, footer, panel background and page extent
  are identical. Desktop pages remain without scroll; narrow viewport page scroll
  is the existing behavior and unchanged. No horizontal overflow is introduced.
- Director section/observation hashes remain unchanged.

Evidence: `/Users/circulodigital/.codex/artifacts/consultation-placeholder-r2/`.
The external router adjustment adds clean-review injection and allowlisted
Step 1/5 landing routes; it is separate from committed product source.
