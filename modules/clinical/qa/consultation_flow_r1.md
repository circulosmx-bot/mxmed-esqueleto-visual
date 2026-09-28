# Consultation Steps 5–7 Clinical Flow R1

## Scope and authorities

- Step 5 prepares orders, prescriptions, next appointments and general follow-up. An order optionally prepares a separate result-review follow-up: none by default, days, specific date, or next appointment. The last option requires selecting/preparing an eligible appointment first.
- Order-review companions are encounter-scoped orchestration state only. Removing an unconfirmed order removes its companion; editing its title updates the companion. General follow-up remains separate.
- Step 6 offers attachment, secure mobile capture and result registration, one selected tool at a time. Existing upload/capture dialogs, result writer, cancellation, private-file reader and replacement flow remain. Canonical current documents have a four-card preview and a full-reader dialog; patient documents are never a second inline list.
- Step 7 retains preparations and confirmed consequences, verifies successful summaries through canonical readers, and separately confirms unresolved indications and finalizes the encounter. Confirmation is in the desktop footer beside the existing finalize anchor; narrow screens keep it in the review content. No combined writer exists.
- Existing order/prescription, Agenda, FUP01 and encounter terminal endpoints, payloads, authorization and per-action idempotency keys remain. The ordered executors run order → prescription → appointment → follow-up; companions wait for their order and, when linked, the actual resulting appointment ID.
- Generated order/prescription records remain visible without claiming a PDF. Records without a binary open the existing canonical document reader; actual private binaries retain the existing authorized reader.
- Finalization is disabled and independently guarded while preparations are unresolved. Confirmation leaves the encounter OPEN. Existing explicit terminal confirmation, void reason/confirmation, amendment and historical semantics remain.
- No product schema, backend contracts, routes or section keys changed. Steps 1–4, measurement/reference/recovery controllers and shared shell allocations are unchanged.

## Focused QA

```sh
bash modules/clinical/qa/consultation_flow_r1_disposable_gate.sh
STEP3_QA_BROWSER="$PWD/modules/clinical/qa/consultation_save_ux_r6_browser.py" \
  bash modules/clinical/qa/step3_head_neck_disposable_gate.sh
python3 modules/clinical/qa/consultation_flow_r1_visual.py
```

The first gate creates a uniquely named disposable database, private storage directory, review session and PHP process, and removes only those resources on exit. Browser presentation comes from the existing Director; all tested clinical/Agenda writes are proxied to disposable canonical APIs. Uploaded bytes are retained from the actual browser File because WebKit protocol postData does not preserve those bytes. Serialized clinical metadata and idempotency headers remain the original request values.

Checks cover default/no review, days/date preparation, title edits and removal, separate general follow-up, navigation and exit/resume preservation, zero domain writes before confirmation, an independent prescription failure, lost order-response recovery, no duplicate successful actions, guarded finalize, canonical retained summary, upload selection/cancel/save, result ownership, authenticated capture status, cancellation, anonymous token-authorized upload, single-use rejection, next-appointment follow-up linkage, explicit finalization, late results and the secondary void form.

The R6 gate preserves the accepted transition-save/placeholder/draft behavior. Its confirmation expectation now follows the requested Step 7 placement.

The visual gate uses the saved pre-change `visual-baseline.json` in the artifact directory. It compares all shared header/stepper/title anchors, all Step 1–5 controls and the accepted tinted surface; desktop title/subtitle/capture/footer positions remain exact. It additionally checks the pending badge, four-action review, desktop footer action separation, no page scroll at 1440×900 and 1366×768, and no horizontal overflow at 820×1180 and 390×844. Narrow Step 6/7 content naturally stacks. `FLOW_R1_FINAL=1` also requires the runtime source header to equal local HEAD.

Reports and WebKit screenshots default to:
`/Users/circulodigital/.codex/artifacts/step6-7-clinical-flow-r1/`.
Override with `FLOW_R1_ARTIFACTS`.

## Director review fixture

`consultation_flow_r1_review.js` is loaded only by the external Director router for explicit loopback `review_patient=plan02ux&review_encounter=open&review_flow=r1` URLs. It prepares four visual examples in a separate review-only session-storage namespace and prevents all clinical/Agenda writes except the established patient identity resolve operation. Examples never become canonical clinical records or leak into ordinary consultation storage. Existing Director clinical documents remain truthful canonical reader data. Successful canonical status screenshots come from the isolated browser gate, not simulated success.

The external router now permits `review_step=documents` and `review_step=finalize`, serves the guarded fixture, and injects it only on that explicit review URL. No Director clinical fixture records are changed.
