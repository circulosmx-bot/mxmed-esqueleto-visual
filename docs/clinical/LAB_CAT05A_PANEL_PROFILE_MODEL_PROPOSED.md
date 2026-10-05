# LAB-CAT05A — Laboratory panel and profile model

**AUDIT_ONLY · PROPOSED · DIRECTOR_REVIEW_REQUIRED**
**Source HEAD:** `0dc5d269e18a8bead0fa5bf3bfd3082e4d2e6f7f`
**Scope:** Contract design. No catalog, runtime, schema, writer, reader or review database mutation.

## Inventory and current architecture

The current active Laboratory family has **152 identities: 140 `LABORATORIO` plus 12 `GENETICA`**. We compared all 152 active keys against `clinical_study_types`, routing, SEARCH02-R1, specimen rules and the COVERAGE01 matrices. Three active keys explicitly contain `panel`: `urine_albumin_creatinine_panel`, `urine_protein_creatinine_panel` and `csf_meningitis_encephalitis_panel`. `cbc` and `ogtt` are also multi-observation orderables. These are **one canonical order item each**, not composer presets. Their names and result contents do not establish a generic panel-component snapshot facility. Hepatic, thyroid and chemistry navigation groupings are not orderable panel identities.

The current composer issues one immutable `order_item_id` per selected canonical identity. Its batch writer rejects duplicate identities and separates routing groups; results link to exact order document/version and item IDs. The portable reader prints issued item names and stored context. CAT03B permits `SERUM`, `PLASMA` and `WHOLE_BLOOD`, but has **no `ARTERIAL_BLOOD`**, blood-gas collection context, or cross-item preset-provenance snapshot. SEARCH02-R1 indexes active canonical studies, not a separate preset catalog. These are contract gaps for a later implementation, not grounds to disguise a panel as a custom free-text order.

## Product terms and decision rule

| Term | Meaning in MXMED |
| --- | --- |
| Individual study | A distinct physician-orderable test with one canonical key/item; its report may contain multiple observations. |
| Panel | One clinical orderable service whose component observations belong to a single order/result item. Its issued version snapshots component definitions and labels. |
| Profile | A Mexican commercial/clinical name for a collection. The name alone does not determine whether the collection is one panel, a preset, or a provider package. |
| Preset | A versioned composer shortcut that visibly expands into existing canonical order items. It is not a result-bearing study and never gets a fabricated `order_item_id`. |

Choose a true panel only when the physician intent, specimen/workflow and primary component set are stable enough to map one orderable provider service and one result relationship. A price bundle alone does not suffice. Choose a preset when composition is a transparent combination of independent tests, especially when the generic profile name varies by provider. Version provider-specific offerings separately from the MXMED clinical preset. [LOINC distinguishes primary measurements, derived observations and order-entry context](https://loinc.org/kb/users-guide/panels/types-of-results); it does not make every reported value a separate order item.

## Evidence and decisions

The [provider composition matrix](LAB_CAT05A_PROVIDER_COMPOSITION_MATRIX.csv) records exact public claims and their limits. These are named offerings, not a Mexico-wide provider capability guarantee.

| Candidate | Decision | Variation | Reason |
| --- | --- | --- | --- |
| `panel_quimica_3` | **VERSIONED_PRESET variants; reject one generic composition** | **HIGH** | [Chopo](https://www.chopo.com.mx/queretaro/quimica-de-3-elementos) lists glucose, urea and creatinine with BUN reported; [Salud Digna](https://www.salud-digna.org/terminos-condiciones-redes-sociales) calls glucose, cholesterol and triglycerides “QS3”. The same label cannot safely select either set by inference. |
| `panel_quimica_6` | **TRUE_CANONICAL_PANEL**, conditional on component-snapshot contract | **LOW** for six primary analytes; **MODERATE** reporting variation | [Humalab](https://humalab.com.mx/estudios/quimica-sanguinea-6-glu-ure-crea-uri-col-trig/), [Chopo](https://www.chopo.com.mx/metro/quimica-6), [ZAGA](https://www.laboratoriozaga.com/pagina-analisis/qu%C3%ADmica-sangu%C3%ADnea-de-6-elementos) and [Corregidora](https://laboratoriocorregidora.com.mx/products/bioquimica-clinica-quimica-sanguinea-de-6-elementos-en-sangre) converge on glucose, urea, creatinine, uric acid, total cholesterol and triglycerides. Some list BUN as a seventh reported value. Do not make BUN mandatory or count it as a seventh ordered element. |
| `panel_hepatic` | **VERSIONED_PRESET** with an explicitly named basic composition | **HIGH** | [Chopo's Perfil Hepático II](https://www.chopo.com.mx/metro/perfil-hepatico-ii) and [Polanco's integral profile](https://lmpolanco.com/perfil-funcion-hepatica-integral/) share AST, ALT, ALP, GGT, protein/albumin and bilirubin measurements. Polanco additionally lists LDH and prothrombin time, which requires a different specimen/handling path. No unqualified universal “perfil hepático” component set is justified. |
| `panel_thyroid` | **DEFER generic profile** | **HIGH** | [Polanco](https://lmpolanco.com/perfil-tiroideo/) lists five hormones, its [expanded profile](https://lmpolanco.com/perfil-tiroides/) adds thyroid antibodies, and [Chopo](https://www.chopo.com.mx/perfil-tiroideo) includes uptake/T7 in its profile. Only TSH, total T3, total T4 and free T4 clearly overlap in these offers. `total_t4` is absent from MXMED's current catalog, so neither five-test profile can be represented exactly today. |
| `arterial_blood_gas` | **TRUE_CANONICAL_PANEL** | **LOW** core gas identity; **MODERATE** extended observations | [Chopo sells arterial blood gases](https://www.chopo.com.mx/gases-en-sangre-arterial). pH, PaCO₂, PaO₂, oxygen saturation and bicarbonate are panel observations, with base excess when reported, not five or six order items. [MedlinePlus](https://medlineplus.gov/lab-tests/arterial-blood-gas-abg-test/) describes the core test; [LOINC](https://loinc.org/kb/users-guide/panels/types-of-results) treats inspired oxygen as order-entry context. Electrolytes, lactate and co-oximetry are not silently included; they need a distinct named expanded service or separate order if clinically requested. |

### Exact proposed minimum-safe identities and presets

**True identities, not yet active:**

- `panel_quimica_6` — **Química sanguínea de 6 elementos**; serum/venous collection; `CLINICAL_LAB`; one order item and one linked panel result. V1 primary component snapshot: `glucose`, `urea`, `creatinine`, `uric_acid`, `chol_total`, `triglycerides`, with display labels from the issuing catalog. BUN may be a separately flagged *reported observation* or an independently ordered `bun`; it is never silently a seventh primary element. Search: `química sanguínea 6`, `QS6`, `química 6`. Exact provider offering must be matched to the snapshot.
- `arterial_blood_gas` — **Gasometría arterial**; proposed fixed `ARTERIAL_BLOOD`, with arterial collection time and oxygen-delivery/FiO₂ context where known; `CLINICAL_LAB`; one order item and one linked result with gas observations. V1 core result vocabulary: pH, PaCO₂, PaO₂, calculated/reported HCO₃⁻, oxygen saturation and base excess when supplied; each result states measured versus calculated where applicable. Search: `gasometría arterial`, `gases arteriales`, `GSA`, `ABG`. This key must not be used for venous/capillary gases. A new CAT03B specimen/collection contract is mandatory before activation.

**Preset definitions, not yet active:**

- `preset_qs3_renal_v1` — **QS3: glucosa, urea y creatinina**; version 1; exact keys `glucose|urea|creatinine`; serum/venous; search `QS3 renal`, `química 3 glucosa urea creatinina`. A Chopo-like BUN observation is provider output, not an implicit fourth item.
- `preset_qs3_lipids_v1` — **QS3: glucosa, colesterol y triglicéridos**; version 1; exact keys `glucose|chol_total|triglycerides`; serum/venous; search `QS3 glucosa colesterol triglicéridos`, `química 3 lípidos`.
- `preset_hepatic_basic_v1` — **Perfil hepático básico (8 estudios)**; version 1; exact keys `ast|alt|alp|ggt|total_protein|albumin|bilirubin_total|bilirubin_direct`; serum/venous. Search `perfil hepático básico`, `pruebas de función hepática`, `PFH`. Bilirubin indirect, globulin and A/G ratio are reported/derived when available, not extra preset items. Polanco's LDH and prothrombin-time extension is a different composition; prothrombin time requires a separate citrated-plasma specimen warning.

No generic `panel_quimica_3`, `panel_hepatic` or `panel_thyroid` key is approved by this proposal. The two QS3 variants and hepatic preset are clinical composition shortcuts, **not claims of the same provider SKU or price**. All component keys above exist in the current 152-key family. Each preset carries display labels, version and exact keys; no provider may remap those keys at issuance.

Search should discover the qualified QS3 variants, QS6, “perfil hepático”, “pruebas de función hepática”/PFH, “perfil tiroideo”, and “gasometría arterial”/GSA/ABG. A bare `QS3` or `PFT` must open a disambiguation choice: `PFT` can mean a thyroid profile or pulmonary function testing. These are proposed search semantics only; SEARCH02-R1 remains unchanged.

## Issuance, UX, specimens and results

Selecting a preset should show its exact component names/count and specimen requirements before issue. Deduplicate by canonical `study_type_id` across the entire composition: already-selected tests remain one item; show “already included” versus “new to add”, preserve existing item intent, and never remove a physician's explicit selection. Reapply the existing one-group `CLINICAL_LAB` routing and batch idempotency. The order snapshot should retain preset key/version, expanded key+display-name list and component-to-item-ID map as *provenance*, while each underlying item retains its own immutable identity. A later preset revision must never reinterpret an issued order.

For a true panel, the issued item must snapshot its panel authority version, primary component definitions/labels, specimen context and provider mapping. Results link to the **single panel item ID** and contain observations under that item; they do not auto-cover separately ordered glucose, bicarbonate or other analytes. For a preset, results link to the **individual expanded item IDs**. A result PDF may include multiple studies, but its exact IDs must identify each study covered. Existing successor-order exact-source rules continue to apply.

Portable orders show a true panel name plus its stored component summary. A preset-expanded order shows the underlying canonical studies once, optionally grouped under a small “Agregados desde…” label; it must not print a second fake panel order. If specimen requirements differ, show each item's requirement and a visible multiple-specimen warning. These rules also prevent `bicarbonate_serum` (venous serum/CO₂ total) from being mistaken for calculated arterial HCO₃⁻ within gasometry.

## Remaining P2 gaps and implementation gate

COVERAGE01's two P2 gaps are `tb_igra` (TB interferon-gamma release assay) and `amh_serum` (anti-Müllerian hormone). [Quest México lists Quantiferón and AMH](https://questdiagnostics.com.mx/hospitales-y-laboratorios/), supporting distinct orderable concepts; both are **outside this panel phase**. LAB-CAT05B should require exact local orderable pages, specimen/handling and provider capability before deciding activation. FIT versus `fecal_occult_blood` remains a separate **UNCERTAIN** assay-method question, not a third approved P2 identity.

**Implementation authority is incomplete.** LAB-CAT05B must design and test: (1) versioned panel-component snapshots and portable/result projection; (2) preset expansion/provenance and item-level deduplication; (3) `ARTERIAL_BLOOD` plus gas collection/FiO₂ context; (4) exact provider offering mapping and result-component validation; and (5) a separate thyroid composition decision after `total_t4` and uptake/T7 boundaries are reviewed. No catalog activation is authorized by this audit.

**Freeze:** `CATALOG_ROWS_CHANGED=false`, `ALIASES_CHANGED=false`, `NAVIGATION_CONFIG_CHANGED=false`, `FRONTEND_CHANGED=false`, `BACKEND_CHANGED=false`, `SCHEMA_CHANGED=false`, `WRITE_MODEL_CHANGED=false`, `READ_MODEL_CHANGED=false`, `ROUTING_CONFIG_CHANGED=false`.
