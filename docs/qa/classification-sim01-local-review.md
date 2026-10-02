# CLASSIFICATION-SIM01 — local classification simulator

The floating **Clasificación** selector is attached to the existing local role/plan QA panel at `127.0.0.1` or `localhost`. It is disabled when `qa_tools=hide` and absent on nonlocal hosts. Open the physician review runtime without that parameter to use it.

The selector lists the 124 specialty labels and 10 professional-title labels from the version 1 OR05 navigation configuration. Search ignores accents and case. **Real del perfil** is the default and resets the override. The chosen finite classification is stored only in `sessionStorage` as `mxmed.qa.classification.v1`; it never writes a physician profile, credential, clinical document, or database row.

The local `window.mxmedReviewClassification` API provides `current()`, `options()`, `set(value)`, and `resolveNavigation(realProfile)`. A change dispatches `mxmed:review-classification-changed`. The OR05 category screen explicitly opts in to `resolveNavigation` and refreshes its shortcuts when the event fires. Other modules, clinical writers, authorization, and plan entitlement never consume the override. The TAX03C composer stays open with its selected studies if the override changes during an order; its existing unsaved-change confirmation remains active.

Run `python3 modules/clinical/qa/classification_sim01_browser.py` against `http://127.0.0.1:18148/` to check the local control, all 134 options, profile switching and reset, an in-progress order, the three viewport sizes, the hidden-tools gate, and nonlocal absence. The test intercepts its one clinical write with disposable data.
