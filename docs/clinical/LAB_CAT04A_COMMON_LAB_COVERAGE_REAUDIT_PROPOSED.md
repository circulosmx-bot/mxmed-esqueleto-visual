# LAB-CAT04A — Common clinical laboratory coverage reaudit

**AUDIT_ONLY · PROPOSED · DIRECTOR_REVIEW_REQUIRED**
Baseline: `3368acc0cb8d64195dab597cdcbae4987885c7cc` on `ux/consultation-step2-vitals-r1` (4 October 2026). No catalog, alias, navigation, reader, writer, schema or UI implementation is included.

## Director summary

| Measure | Finding |
| --- | ---: |
| Active canonical studies in review database | 232 |
| Active laboratory-root identities (LABORATORIO 120 + GENETICA 12) | 132 |
| Reviewed concepts / matrix rows | 168 |
| Existing identities audited in full | 132 |
| Exact existing rows | 124 |
| Existing rows with a high-confidence alias gap | 8 |
| Existing rows with wrong leaf / missing route / only global search | 0 / 0 / 0 |
| True missing measured canonical identities | 22 |
| True missing panels | 2 |
| Derived / component / duplicate-risk / uncertain / advanced-defer rows | 4 / 1 / 2 / 1 / 4 |
| Priority P1 / P2 / P3 candidate rows | 7 / 17 / 4 |
| Minimum CAT04B scope | 20 new measured identities + 8 existing alias fixes (3 of those also display fixes) = 28 affected identities |

These are **audit classifications**, not clinical orders or approvals to deploy. All 132 current laboratory-root studies are in [the companion matrix](LAB_CAT04A_COMMON_LAB_MATRIX.csv), including urine/body-fluid CAT03 identities and the 12 genetics identities. The five pathology studies were checked as an adjacent HIER03 root boundary and are excluded from the laboratory-root count. No existing identity lacks a visible FEATURED02 leaf. `featured=false` means absent from the short “Más solicitados” list, not hidden; every such identity remains in its leaf's full list or accordion.

**HbA1c is already present:** key `hba1c`, display `HbA1c`, leaf `endocrine`, group `endocrine__glycemic`, and featured list. `HbA1c`, `A1c` and `Hemoglobina glicosilada` find that record. `Hemoglobina glucosilada` does not; propose that alias and a physician-facing display `Hemoglobina glicosilada (HbA1c)` on the *same* identity. No new HbA1c key and no navigation correction.

**PSA is genuinely absent:** no canonical key/display/alias for antígeno prostático específico, antígeno prostático, PSA total, PSA libre, or relación PSA libre/total. Propose distinct measured `psa_total` and `psa_free` identities in `tumor`. A combined total+free request is a **panel**, deferred until its component/result contract is designed. Free/total percentage is a **calculated result**, not a third measured orderable identity. Chopo lists total, free and combined offerings; Mayo specifies the two measured analytes and conditional calculated ratio. [Chopo offerings](https://www.chopo.com.mx/alianza-farmacias-del-ahorro), [Quest México](https://questdiagnostics.com.mx/promociones-en-salud-masculina/), [Mayo PSA total/free](https://www.mayocliniclabs.com/test-catalog/overview/81944/prostate-specific-antigen-psa-total-and-free-serum).

## Authorities and method

- **C1**: active rows from `mxmed_director_review_lon07c.clinical_study_types`, fields `study_type_key`, `display_name_es`, `category_key`, `aliases_json`; 232 active on this baseline. This audit used read-only SQL and did not mutate the review database.
- **N1**: `modules/clinical/catalog/study_featured_navigation_v1.json`; every active laboratory-root key was reconciled to `study_keys`, `featured`, and `groups`. Multiple valid leaves are preserved where present.
- **H1**: `assets/js/clinical/study-navigation-hierarchy-v2.js`, `assets/js/clinical/lab-cat02a-navigation-v1.js`, and `docs/clinical/STUDY_NAV_HIER03_PROGRESSIVE_NAVIGATION_V2_IMPLEMENTED.md`. LABORATORIO and GENETICA map to the laboratory root; PATOLOGIA has a separate root.
- Historical context read: LAB-CAT01/CAT02, FEATURED01/FEATURED02, CAT03A/B/C documentation. The **current database and FEATURED02 config** decide present-tense status; older proposal tables do not.
- Embedded catalog search in `assets/js/clinical/tax03c-study-composer.js` normalizes Unicode NFD, removes diacritics and lowercases, then matches key/display/aliases as substrings. The API `api/_lib/clinical_study_catalog_read.php` uses SQL `LIKE` on those stored fields. The search verdicts here model the physician-facing embedded search; SQL collation may produce different accent behavior and should be regression checked in CAT04B.
- Every proposed key was checked against all 232 current keys, displays and aliases, plus the FEATURED02 leaf/group map. External provider offerings establish that a concept is orderable in Mexico; they do **not** decide MXMED key, specimen or result semantics by themselves.

### Explicit query audit

| Query | Current match | Interpretation |
| --- | --- | --- |
| `Hemoglobina glicosilada` | `hba1c` | Existing alias |
| `Hemoglobina glucosilada` | none | Missing high-confidence spelling variant |
| `HbA1c` / `A1c` | `hba1c` | Display substring; explicit `A1c` alias unnecessary |
| `Antígeno prostático específico` / `Antígeno prostático` | none | Missing PSA identity; not an alias for β-hCG |
| `PSA total` / `PSA libre` / `Relación PSA libre/total` | none | Two measured candidates, one derived result |
| `TSH` / `FT4` / `T3` | `tsh` / `ft4` / `ft3` | Existing; `T4L` and `T3L` do not match |
| `VSG` / `ESR` | `esr` | Existing by display/key; full Spanish name absent |
| `PCR` | `crp_hs`, `urine_protein_creatinine_panel` | Collision already exists. Do not add generic PCR alias to a new CRP identity. |

The `hba1c` example illustrates why a missing search result must not automatically create a canonical identity. The plain CRP candidate is a genuine *different* assay purpose from high-sensitivity CRP: [Mayo conventional CRP](https://www.mayocliniclabs.com/test-catalog/Overview/800164), [Mayo hs-CRP](https://www.mayocliniclabs.com/test-catalog/Overview/800024).

## Domain reconciliation

| Domain | Existing coverage | Material gap or boundary |
| --- | --- | --- |
| General chemistry | Glucose, HbA1c, urea/BUN, creatinine, uric acid, protein/albumin, bilirubins, ALT/AST/ALP/GGT, amylase/lipase | Serum LDH and bicarbonate/CO2 are absent; distinguish fluid LDH. |
| Lipids | Total/HDL/LDL/non-HDL cholesterol, triglycerides | ApoB absent; non-HDL already canonical and may be a calculated result. |
| Electrolytes/minerals | Na/K/Cl/Ca/P/Mg | Bicarbonate absent; commercial chemistry bundles are not extra canonical analytes. |
| Hematology/iron | CBC, reticulocytes, smear, ESR, ferritin, iron, TIBC, saturation | Full ESR Spanish alias absent; UIBC needs measured-versus-derived review. |
| Coagulation | PT, aPTT, thrombin time, fibrinogen, D-dimer | Lupus anticoagulant is a specialty algorithm; defer. |
| Thyroid | TSH, FT4, FT3, anti-TPO, anti-Tg | Total T3 absent; never alias it to free T3. |
| Endocrine | Prolactin, FSH, LH, estradiol, progesterone, total/free testosterone, β-hCG, OGTT | Insulin, cortisol, ACTH, intact PTH, DHEA-S absent; AMH deferred. |
| Tumor markers | β-hCG only | PSA total/free, AFP, CEA and CA 125/19-9/15-3 absent. Do not treat markers as screening recommendations. |
| Cardiac | CK-MB | Troponin I and NT-proBNP absent. Assay naming needs confirmation before launch. |
| Inflammation | hs-CRP and ESR | Conventional CRP and procalcitonin absent. |
| Vitamins/nutrition | Vitamin D, B12, folate, iron/ferritin | `vitamin_d` analyte is generic; verify 25-OH before changing its display or alias. |
| Infectious serology | HIV Ag/Ac, hepatitis B/C | Serum VDRL absent; LCR VDRL exists and must stay distinct. RPR deferred. |
| Autoimmunity | ANA, ENA, ANCA, anti-CCP, RF, C3/C4 | anti-dsDNA absent; generic ANA/ENA cannot substitute. |
| Urine/body fluids | CAT03 urine, LCR, body-fluid identities | All current routes present; specimen architecture remains frozen. |
| Genetics | 12 active studies within laboratory root | Existing coverage, no routine expansion proposed here. |

## Proposed CAT04B sets

**NEW_CANONICAL_IDENTITIES (20 in minimum safe set):** `crp_standard`, `psa_total`, `insulin_serum`, `cortisol_serum`, `syphilis_vdrl_serum`, `ldh_serum`, `psa_free`, `afp_serum`, `cea_serum`, `ca125_serum`, `ca199_serum`, `ca153_serum`, `acth_plasma`, `pth_intact`, `dheas_serum`, `anti_ds_dna`, `procalcitonin_serum`, `total_t3`, `bicarbonate_serum`, `apo_b`. These are *proposed keys*, not authoritative schema names. Two additional true-missing identities, `troponin_i` and `nt_probnp`, remain out of the minimum set until assay nomenclature is confirmed. New `tumor` identities would need placement in that existing leaf as part of their creation; that is not an existing-study navigation fix.

**ALIAS_ONLY_FIXES (five existing identities):** `ft4` → `T4L`; `ft3` → `T3L`; `hiv_ag_ac` → `virus de inmunodeficiencia humana`; `anti_ccp` → `anticuerpos anti-CCP`; `ogtt` → `curva de tolerancia oral a la glucosa`. Confirm no semantic collisions before application. Do not add “cuarta generación” to HIV without assay-generation evidence.

**DISPLAY_NAME_ONLY_FIXES:** no pure display-only row is required. Three existing identities merit a combined display+alias update: `hba1c` → `Hemoglobina glicosilada (HbA1c)` plus `hemoglobina glucosilada`; `esr` → `Velocidad de sedimentación globular (VSG)` plus full-name alias; `crp_hs` → `Proteína C reactiva ultrasensible (PCR-us)` plus `hs-CRP`/`PCR-us`. These are the other three of the eight existing affected identities. Keep current canonical keys and prior aliases.

**NAVIGATION_ONLY_FIXES:** none. All 132 existing laboratory-root identities have a visible leaf. HbA1c is already featured in its glycemic group. Do not add duplicate routes merely to increase featured density.

**Panels, calculations and deferrals:** `psa_total_free_panel` and `abo_rh` require explicit multi-component result contracts. PSA free/total ratio is calculated, not a third assay. Existing `non_hdl`, `transferrin_sat` and `bilirubin_indirect` identities must not be duplicated as calculated variants. `vitamin_d` 25-OH specificity, UIBC measurement semantics, lupus-anticoagulant workflow, AMH, BNP and RPR await focused review. No commercial “química de N elementos” or “perfil de marcadores” package is proposed as a single analyte.

**Collision ledger:** `PCR` already resolves to hs-CRP and urine protein/creatinine and also denotes molecular PCR in clinical language; do not use as an unqualified alias. `PSA`/`APE` may be aliases for *total* only after the UI distinguishes total/free/panel. `T3` without “libre” or “total” is ambiguous once total T3 is introduced. `BNP` and `NT-proBNP` are distinct. `VDRL` serum and LCR, and LDH serum and body fluid, preserve specimen identity.

## Source register

| ID | Primary source | Use |
| --- | --- | --- |
| M1 | [Chopo, estudios frecuentes](https://www.chopo.com.mx/nuevo_leon/estudios/frecuentes) | Common Mexican offerings, including HbA1c, PSA total, insulin, cortisol, VDRL and tumor markers. |
| M2 | [Chopo, perfil de marcadores tumorales III](https://www.chopo.com.mx/perfil-de-marcadores-tumorales-iii) | AFP, CEA, CA 125/19-9/15-3 as distinct measured components; commercial panel itself is not a canonical analyte. |
| M3 | [Chopo, alliance study listing](https://www.chopo.com.mx/alianza-farmacias-del-ahorro) | Standalone serum CRP versus hs-CRP, PSA total/free, insulin, PTH, ACTH, bicarbonate, ApoB, anti-DNA and other specialty offerings. |
| M4 | [Quest Diagnostics México, male-health offerings](https://questdiagnostics.com.mx/promociones-en-salud-masculina/) | PSA total+free request availability. |
| M5 | [Quest Diagnostics México, study list](https://questdiagnostics.com.mx/nuestros-estudios/) | HbA1c, DHEA-S and chemistry panel components such as LDH, CO2 and UIBC; cross-provider corroboration. |
| R1 | [Mayo conventional CRP](https://www.mayocliniclabs.com/test-catalog/Overview/800164) and [Mayo hs-CRP](https://www.mayocliniclabs.com/test-catalog/Overview/800024) | Distinct intended uses; do not merge identity via abbreviation. |
| R2 | [Mayo PSA total/free](https://www.mayocliniclabs.com/test-catalog/overview/81944/prostate-specific-antigen-psa-total-and-free-serum) | Measured total and free; ratio calculated conditionally. |

Provider pages are time-sensitive public catalogs; availability varies by branch. The matrix uses source IDs so every external claim resolves to a direct URL here. Where a source shows only a family name (e.g. “PRO-BNP” or “troponina I”), the exact assay variant is held for confirmation rather than asserted.

## Required next task

**LAB-CAT04B**, only after Director review: validate key names, specimen and assay semantics; implement the agreed 20 measured identities and 8 existing-identity presentation/search fixes; test embedded and API search, leaf/group reachability, collision behavior, and current order-item identity preservation. The two panels and deferred rows need their own contracts. This audit introduces **zero** catalog/config/source/database changes.
