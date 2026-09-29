# Consultation Step 6 Visual Fidelity R3

## Scope

R3 starts from clean, remote-matched `c959679a6abe12dfc9c5b7c57c223daeff93f962`, the accepted R2 modal architecture. Four approved 28 September 17:17:40 images guide the main panel and attachment, capture and result dialogs. The Director's 18:21:47 live screenshot identifies the small icon and document clipping gaps; it is not the visual target.

Only Step 6 presentation changes. The redundant white frame around the action/document composition is removed. Primary cards are 108 px tall at 1440×900 and 88 px at 1366×768. The current document preview shows three items, retaining the full canonical count and existing “Ver todos” reader. Patient access is secondary, with a count from existing authorized document readback. Document cards remain white. Every desktop region fits above the fixed footer. Tablet/phone internal sizing retains the accepted responsive content allocations (504 px / 759.27 px), so the shared footer remains at its R2 position while the cards adapt. No shared shell positioning rule changes.

The actual R2 glyph was about 20 px tall inside a 48 px tile. Its nominal 150% pass remained visibly undersized versus the reference. The final reference match uses a 72 px Material Symbols glyph in a 76 px tile, approximately 59 px visible attachment glyph height; compact desktop uses 60 px in a 64 px tile. This is an effective rendering correction to the reference's large graphic zone. Final title/subtitle are 18/15 px, compact desktop 17/13 px. Existing Material Symbols are retained; the phone's camera is a decorative glyph from the same family.

## Modals and canonical authority

Attachment/capture/result widths are 570/540/530 px. Shared white surfaces, 14 px corners, 28 px padding, 72 px header glyphs, 28 px titles, 14 px explanations, 110 px dropzones, and 44 px footer buttons match the guide hierarchy. Small viewports stack and scroll inside the modal.

Attachment type and provenance are truthful read-only presentation: PDF or clinical image, attached in this consultation. MIME-derived types, existing source payload, supported MIME values, private storage and size validation are unchanged. No illustrative prescription/order/note categories or new source controls are added. Result type remains the canonical select in its original field order, with identical writer payloads.

Capture presents three numbered connected instructions and balanced QR/phone columns. A real pending session displays its actual QR and canonical pending status. No connected-device claim is invented. After upload the existing authority removes the now-terminal bearer link; the receipt displays the actual canonical title and document glyph. No unavailable preview, size or filename is fabricated. “Usar captura” still acknowledges the already persisted document without a second write.

The only R3 JavaScript differences are rendering three preview cards and displaying the patient document count. Writers, token issuance/poll/cancellation, canonical order filters, result idempotency, Plan and Step 7 controllers, schema and API contracts are unchanged.

## Verification

```sh
bash modules/clinical/qa/step6_visual_r3_disposable_gate.sh
python3 modules/clinical/qa/step6_visual_r3.py
# Exact committed source after runtime refresh:
STEP6_R3_FINAL=1 python3 modules/clinical/qa/step6_visual_r3.py
```

The canonical gate uses only a disposable database and secure phone upload environment. It verifies attachment/result cancellations, dirty guards, focus trapping/return, result lost-response replay, anonymous phone upload, physician-only issuance/status, single-use/expiry, canonical receipt without duplicate writes, late results, Plan preparation/confirmation, finalization and void. Screenshot-only blur removes incidental keyboard rings without changing accessibility or focus tests. Capture evidence is saved only after the test token is terminal.

The visual gate compares fresh clean R2 metrics for all seven steps at four viewport sizes, including the responsive Step 6 footer. It checks exact shared header/stepper/title/footer geometry, frozen Step 1–5 and Step 7 controls/surfaces, desktop page/content scroll, horizontal overflow, card bounds above the footer, white document interiors, modal labels/focus/return and reference icon scale. Canonical generated document buttons are loaded before Step 7 snapshots. Hidden zero boxes are normalized independently of browser scroll offset. No Director records are written.

Artifacts, before/after screenshots, comparison metrics and source proof:
`/Users/circulodigital/.codex/artifacts/step6-visual-fidelity-r3/`.
