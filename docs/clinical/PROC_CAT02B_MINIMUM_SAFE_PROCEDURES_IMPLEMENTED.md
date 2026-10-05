# PROC-CAT02B — Minimum safe diagnostic procedures

Source baseline: `02dcbc4e1a2ff5eb618de79825814a613e340e66` on `ux/consultation-step2-vitals-r1`.

## Activated contract

The active review catalog moves from 284 to 287 studies and from 13 to 16 diagnostic procedures. Exactly three identities were added:

| Canonical identity | Physician and portable name | Category | Route | Navigation leaf |
|---|---|---|---|---|
| `colposcopy_diagnostic` | Colposcopia diagnóstica | `PROCEDIMIENTOS_DIAGNOSTICOS` | `GYNECOLOGY_DIAGNOSTICS` | Ginecología |
| `hysteroscopy_diagnostic` | Histeroscopia diagnóstica | `PROCEDIMIENTOS_DIAGNOSTICOS` | `GYNECOLOGY_DIAGNOSTICS` | Ginecología |
| `cystoscopy_diagnostic` | Cistoscopia diagnóstica | `PROCEDIMIENTOS_DIAGNOSTICOS` | `UROLOGY_DIAGNOSTICS` | Urología |

The routing authority is version 2. The two new group labels are “Diagnóstico ginecológico” and “Diagnóstico urológico”. Existing study assignments and group labels remain identical to the source baseline, including all 13 `ENDOSCOPIA` studies. Previously issued order snapshots retain their saved version. The approved category needed an additive expansion of the database `ck_study_type_category` check constraint; the CAT02A assumption that no DDL was needed was incorrect for the real schema. The column type, existing allowed values and historical rows did not change. The migration is repeatable and inserts only the three approved rows.

The server-owned `diagnostic_procedure_scope_v1.json` allows only these three keys, each with `DIAGNOSTIC_ONLY`, `ON_SITE`, and `NONE_V1` biopsy intent. The order writer denies unknown procedure identities, custom orders in the new category, and any procedure-specific field. No therapeutic, biopsy, sedation or preparation selector was introduced. A diagnostic order does not attest that a procedure occurred or authorize a biopsy, stent, resection or other intervention. Actual performed acts, consent, specimens and provider instructions remain separate future authorities. Private canonical PDF reports are sufficient for V1; results retain exact source order/item linkage through the existing result model.

Provider capability remains an exact active, verified location offering for the canonical `study_type_id`, backed by the existing master service and provider/location checks. Creating, updating or verifying one of these offerings in `HOME_SERVICE` or `MOBILE` is rejected. Matching excludes these modes and refuses a new-category identity without a policy row. Referral send and accept also fail closed on a missing procedure policy. A verified colposcopy offering does not cover hysteroscopy, and a cystoscopy offering does not cover gynecology. Verification of the offered *diagnostic-only* clinical service is an operational attestation; the system does not infer it from a specialty, a route or free text.

HIER03 and Featured navigation expose populated Ginecología (2) and Urología (1) leaves directly. SEARCH02 retains deterministic matching, family-wide procedure search and cross-family rescue. Approved aliases cover accented and video/common variants; `panendoscopia`, `gastroscopia` and `endoscopia alta` now discover existing `egd_eda_base` without creating another identity. The three new studies share the normal composer: two gynecologic items produce one gynecology order, cystoscopy produces a separate urology order, and mixed groups remain atomic and idempotent.

## QA evidence

- `bash modules/clinical/qa/proc_cat02b_disposable_http.sh`: PASS against a disposable MySQL schema, authenticated real PHP endpoints, three single-study orders, gynecology two-item order, urology order, mixed three- and four-study batches, idempotent replay, exact result-item link, private PDF rendering and text extraction for both new routes, invalid parameter zero-write rejection, exact provider offering and ON_SITE guards. The disposable database is removed by trap.
- `PROCCAT02B_REVIEW_SESSION=<local session> python3 modules/clinical/qa/proc_cat02b_live_browser.py`: PASS against the served runtime at `127.0.0.1:18148`, with all API writes intercepted and rejected. Ginecología/Urología leaves, direct selection, prepared order labels, procedure-wide search, cross-family laboratory rescue and no horizontal overflow passed at 1440×900, 1366×768 compact, 1366×768 expanded and 390×844.
- `php modules/clinical/qa/study_search02_gate.php`: PASS, including the historical 47-query fixture.
- `php modules/clinical/qa/lab_cat05b_contract_gate.php`, `path_cat02a_contract_gate.php`, `img_cat02a_contract_gate.php`, `dental_cat03b_contract_gate.php`: PASS.
- `bash modules/clinical/qa/b3_interop01_disposable_gate.sh`: PASS, including exact-source result lineage and existing referral acceptance.
- Review DB migration executed once and rerun idempotently: 287 active studies and exactly 3 new-category rows remain. No patient or encounter fixture was written to the review DB.

The runtime process uses the correct checkout but has an older `X-MXMED-Director-Source-Head` value baked into its environment. Served assets and API reads were verified against the current working files and migrated review catalog; the stale header is runtime metadata, not the source used for this QA.
