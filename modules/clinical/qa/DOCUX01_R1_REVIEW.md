# DOCUX01-R1 document intake review

Accepted source: `99a07bfa40956e217c92c81893b77d666aefcfc8`.
Branch: `design/physician-crd03-credentials-ui-v1`.

Current documents now use the explicit consultation label and empty copy. General
patient documents retain their existing filtering and appear as a secondary section
only when populated. Two compact actions open native dialogs using the established
Plan shell. Orders, results, replacement forms, Plan collector and badge are preserved.

The attachment modal reuses the normal file input and canonical multipart writer.
Picker/drop only selects; explicit Save writes. JPEG, PDF, PNG and WebP retain their
existing support. Busy state prevents save re-entry; failures use fixed physician copy.
Success refreshes canonical readers without an optimistic duplicate.

The capture modal issues only on explicit launch through the existing authenticated
endpoint. The local QRCode bundle encodes exactly the resolved returned mobile URL.
Status polling waits 2500 ms between completed requests and stops on terminal state,
close, hidden document or context loss. Mobile upload still uses its limited bearer
without a physician login. No backend, schema, mobile page, token model or security
code changed; no external QR service was introduced.

Close waits for in-flight issuance, reads status and cancels pending sessions through
the authenticated operation. Already observed uploads are not cancelled; terminal
races retain the server's 409 contract. Failed cancellation leaves the modal visible
with retry instructions and removes the QR. Forced context loss closes the stale
modal and attempts cancellation; failure informs the user that the link expires.
An unconfirmed issuance response similarly reports possible automatic expiry.
Page unload uses best-effort keepalive cancellation; network loss cannot guarantee
server receipt, and existing bounded server expiry remains authoritative.

## Validation

Evidence: `/Users/circulodigital/.codex/artifacts/docux01r1`.
Only the explicitly synthetic Director database and disposable clones were used.
Technical writes use patient `p_plan02_review`, encounter 1015. Clean UX patient
`p_plan02ux_review`, encounter 1016 remains OPEN with zero documents.

- Browser gate: 33 passing checks. Includes actual picker/drop saves, double save,
  zero writes before Save/on Cancel, generic failure, decoded QR equality, real
  anonymous mobile upload, canonical patient/encounter readback, delta one, terminal
  rejection, cancel/expiry, context changes, and Plan 4 → 2 → 0 with successful handoff.
- Lifecycle gate: four passing checks covering issuance failure, cancellation retry,
  visibility polling and application view loss.
- Responsive review: 1440×900, 1366×768, 820×1180, 390×844. Modal horizontal bounds,
  Tab containment, Escape and focus return checked; screenshots reviewed.
- DOCSEC01: 35 security checks pass; unauthenticated issuance remains 401 and
  authorized issuance 201. Entropy and C21 adapter gates pass.
- Isolated atomic matrix passes with dedicated HTTP workers, distinct DB connections,
  real multipart writers, terminal races, expiry and rollback/ambiguous-commit checks.
- Director race harness is scheduling-sensitive on its shared PHP listener: several
  attempts failed to bring all four requests to its barrier (one observed 3 of 4).
  An instrumented run reached barriers 4/4, 2/2, 2/2, 2/2 and passed all four races.
  No security assertion was relaxed. The deterministic isolated matrix is also green.
- JavaScript syntax and git whitespace checks pass. Backend, QR vendor bundle and
  Plan module diffs are empty. Existing order/result/replacement form markup is equal
  to accepted source. The existing DOCSEC01 browser gate changes only its visibility
  selector from the former inline upload form to its new launch button.

QR images were written to evidence only after canonical upload/cancellation made
the depicted token terminal. Logs are redacted; no active bearer is published.

## Reproduction

Requires the existing synthetic Director session/runtime, Python Playwright/PyMySQL,
and macOS Vision for local QR decoding. Use an absolute external artifact directory:

```sh
swiftc modules/clinical/qa/docux01r1_qr_decode.swift -o "$DOCUX01R1_ARTIFACTS/qr-decode"
export DOCUX01R1_QR_DECODER="$DOCUX01R1_ARTIFACTS/qr-decode"
python3 modules/clinical/qa/docux01r1_browser.py
python3 modules/clinical/qa/docux01r1_lifecycle.py
php modules/clinical/qa/docsec01_entropy_test.php
php modules/clinical/qa/multi06b_c21_adapter_test.php
DOCSEC01_ARTIFACTS="$DOCUX01R1_ARTIFACTS/security" python3 modules/clinical/qa/docsec01_security.py
DOCSEC01R1_ARTIFACTS="$DOCUX01R1_ARTIFACTS/security" python3 modules/clinical/qa/docsec01r1_races.py
node --check assets/js/clinical/m7-ws04.js
git diff --check
```

Review URL:
<http://127.0.0.1:18143/index.html?review_patient=plan02ux&review_encounter=open&qa_tools=hide>
