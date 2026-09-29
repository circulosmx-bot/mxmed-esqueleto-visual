# Step 6 mobile capture R4

## Authority audit (before implementation)

Starting local and remote HEAD: `17daee4d3708a3569e1197d8667675fa88088348`; clean worktree.

- Authenticated issuance: `POST /api/clinical/index.php/note-capture-tokens`.
- Phone page: `/public/note-capture.html?token={bearer}`.
- Anonymous upload: `POST /api/clinical/index.php/note-capture-tokens/{token}/upload`.
- Status/cancel/consume require physician authentication and canonical patient/encounter scope.
- `clinical_note_capture_multipart_execute` delegates to the existing canonical multipart service. Its insert callback atomically completes the token in the same document transaction. The legacy patient-level gateway remains separate and unchanged.
- Upload already creates one canonical document. Step 6's “Usar captura” only closes the modal; it does not commit. The legacy `/consume` endpoint links a capture to a note and is not a Step 6 requirement.
- Existing generic capture emits `image` or `pdf`, selected from upload metadata, with title `Imagen clínica (captura móvil)`. The dedicated result workflow owns order/result linkage. Prescription/note generation have structured content semantics and are not interchangeable with captured binaries.
- Safe generic capture choices: **Imagen clínica** → `image`; **Documento clínico (PDF)** → `pdf`. These are the binary types already used by Step 6 upload and the existing display catalog. Results, generated notes, prescriptions and orders are not exposed as generic capture choices.
- `clinical_note_capture_tokens.note_context` is a `VARCHAR(80)` purpose identifier; existing purposes include note attachment and consent identity/signature. A versioned `step6_capture_r4:<whitelisted-id>` safely binds classification without DDL. Existing contexts keep their behavior; unknown R4 contexts fail closed.
- There is no issuance-time title field/storage. R4 keeps server-derived default titles, with no required or optional title input. It does not overload unrelated token fields or truncate arbitrary titles into the 80-character context.
- Tokens retain `random_bytes(16)`, the existing TTL bounds and the existing token mutex/transaction authority.

## R4 contract

Authenticated issuance adds `capture_classification`. The whitelist lives on the server; `GET /note-capture-tokens/classifications` exposes only selectable labels and accepted formats to the authenticated desktop. A token stores its immutable purpose/classification in `note_context`. The phone cannot choose classification, title, patient or encounter. Actual file MIME must match the issued classification.

`GET /note-capture-tokens/{token}/mobile-context` exposes only token state, expiry and a read-only classification/accepted-format description. It does not expose patient, encounter, doctor or private document metadata.

R4 issuance returns an absolute canonical `mobile_url`; the QR and link use that exact value. Optional deployment setting `MXMED_CAPTURE_PUBLIC_ORIGIN` supplies the phone-accessible origin; otherwise the validated request origin is used. A phone must be able to reach that origin. Legacy issuance retains its relative URL contract.

Physical-write QA uses a disposable database with an asserted `flow_r1_qa_` name and isolated private storage. Director presentation/visual fixtures never persist synthetic documents. The working MXMED database is not connected or mutated.

## Token fields audited

Existing table fields: `id`, `token`, `patient_id`, `encounter_key`, `note_context`, `status`, `expires_at`, `uploaded_at`, `consumed_at`, `cancelled_at`, `document_id`, `document_uuid`, `note_document_id`, `note_document_uuid`, `preview_url`, `signature_image_data`, `signature_signer_name`, `signature_signed_at`, `created_at`, `updated_at`. R4 changes no fields, indexes, or migrations.

## Focused QA and review

Run the complete R4 disposable gate:

```sh
bash modules/clinical/qa/step6_mobile_capture_r4_disposable_gate.sh
```

Run the existing Plan / documents / results / Finalizar regression:

```sh
bash modules/clinical/qa/consultation_flow_r1_disposable_gate.sh
```

Both passed. R4 verifies unauthenticated and foreign-scope rejection, classification tampering and isolation, MIME mismatch, cancellation/retry, expiration/renewal, exact QR decoding via Vision, anonymous phone preview/send, exactly one canonical document, rejected second upload, stopped terminal polling, private readback, live list refresh, focus trap and focus return. The full regression preserves Plan, result linkage, finalization and legacy capture callers. DOCSEC01 entropy and C21 adapter gates also passed.

Native Director Safari: viewport **1440×810**, QR modal **540×758.39**, QR container **216×216**, fully visible with no page, horizontal or internal modal overflow. WebKit also covers **1440×900**, **1366×768**, and phone **390×844**.

Artifacts: `/Users/circulodigital/.codex/artifacts/step6-mobile-capture-r4/` (canonical report, native Safari geometry, review report and screenshots). Physical-write evidence comes only from dropped disposable databases. Director review QA compares table digests, including capture tokens, and asserts zero changes.

The existing external Director router injects `step6_mobile_capture_r4_review.js` only for explicit `review_capture_r4` fixtures. Append `&review_capture_r4=selection`, `qr`, `waiting`, `received`, `expired`, or `change` to the canonical Step 6 review URL. These are clearly marked visual simulations with invalid synthetic tokens and no persisted documents. Phone visual preview: `/public/note-capture.html?review_capture_r4=mobile&token=r4-visual-only-0`.

The Director runtime remains bound to `127.0.0.1`. End-to-end upload was tested in anonymous WebKit against the disposable server; no physical handset scan is claimed. A physical phone needs a reachable deployment origin configured through `MXMED_CAPTURE_PUBLIC_ORIGIN`; a loopback URL cannot be opened from another device.
