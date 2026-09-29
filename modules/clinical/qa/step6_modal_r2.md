# Consultation Step 6 Modal Actions R2

## Scope and authority audit

The main panel has three keyboard-accessible graphic launch cards, a four-document preview, a canonical count/reader, and patient-document access. No operational form is visible by default. Desktop content remains inside the existing Consultation capture allocation. Main icons were measured at 16 px in clean R1; R2 uses 24 px, exactly 150% total scale, in a larger graphic tile. The existing Material Symbols family remains.

Attachment and secure capture reuse the existing dialogs, writer, file picker/dropzone, token issuance, QR renderer, polling and secure cancellation. Results reuse the same form references, canonical eligible order filter, result types and multipart writer in a dedicated dialog. Empty/cancelled dialogs clear their local fields; X/Escape/backdrop ask before discarding meaningful attachment/result input. Explicit Cancelar discards that modal copy. Focus returns to the initiating card. Explicit Tab/Shift+Tab cycling keeps all controls reachable under Safari's default keyboard preferences.

Attachment types remain derived from file MIME (`pdf`/`image`); illustrative prescription/order/note classifications are not added. Accepted MIME values remain PDF, JPEG, PNG and WebP. Canonical binary storage's 25 MiB ceiling and deployment PHP limits are unchanged; the image's illustrative 10 MB limit is not copied. Attachment provenance remains `payload.source=m7_ws04` and image media tag `clinical_attachment`. Result types remain `lab_result`, `imaging_result`, `external_report`, with the existing required provenance and originating order/encounter payload.

Mobile upload already creates one canonical document atomically. The received status/title comes from token status and authorized canonical document readback. “Usar captura” closes that completed session; it sends no second document write. There is no simulated phone connection, filename, PDF, size or receipt. Steps 5 and 7 controllers, Plan preparation state, finalization guards, schema, API contracts and the shared shell remain unchanged.

## Focused verification

```sh
bash modules/clinical/qa/step6_modal_r2_disposable_gate.sh
python3 modules/clinical/qa/step6_modal_r2_visual.py
# After the committed runtime refresh:
STEP6_R2_FINAL=1 python3 modules/clinical/qa/step6_modal_r2_visual.py
```

The disposable gate reuses the accepted R1 database/API isolation and its full Plan/Step 7 regression. Its browser expectations now follow modal result entry. Added checks cover local discard guards, cancellation without document writes, focus restoration and trapping, lost-result-response replay with the same key, token issuance/status authentication, actual anonymous phone form upload, expiry rejection, single-use, canonical receipt, and no duplicate from “Usar captura.” All three modal layouts are checked at 1440×900, 1366×768, 820×1180 and 390×844.

The visual gate compares a baseline captured at clean, remote-matched `b049b9b7d55a60f17d575dc3fed14c752902303a`. It verifies frozen Step 1–5 and Step 7 controls/surfaces, shared anchors across all steps, icon ratio, no default inline form, no desktop page/content scroll, and no horizontal overflow. Attachment/result screenshots use read-only Director data. Capture screenshots use the disposable canonical token flow; QR image bytes are saved only after that token is terminal, and the database is removed on gate exit. Director clinical records remain unchanged.

Reports and fresh WebKit evidence:
`/Users/circulodigital/.codex/artifacts/step6-modal-actions-r2/`.
