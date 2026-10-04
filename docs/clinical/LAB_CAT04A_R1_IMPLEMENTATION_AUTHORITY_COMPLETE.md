# LAB-CAT04A-R1 — implementation authority for CAT04B

**AUDIT_ONLY · IMPLEMENTATION_AUTHORITY_COMPLETE · DIRECTOR_REVIEW_REQUIRED**

Implementation status: **PARTIALLY_IMPLEMENTED** by [LAB-CAT04B](LAB_CAT04B_COMMON_LAB_MINIMUM_SAFE_IMPLEMENTED.md). This R1 document remains the decision record for the exact 28 implemented targets and the deferred concepts.

Baseline: `c9b48adcc589b6e6a6847a8f57bef418960096d9`. This repairs the CAT04A decision table; it creates no catalog rows, aliases, navigation, routing, specimen config, order or result records. CAT04B remains a separate implementation task. The original 168 audit rows and classifications are preserved in the repaired matrix. The 28 target rows are also extracted to the machine-readable authority CSV.

## Contract and reconciliation

- Exactly **20** new measured identities and **8** existing-identity fixes. `troponin_i` and `nt_probnp` remain outside the minimum set. The two panels, four derived concepts and one uncertain concept remain deferred.
- All 20 new rows use existing DB category `LABORATORIO`, HIER03 root `laboratory`, operational routing `CLINICAL_LAB`, one primary FEATURED02 leaf, and `NOT_FEATURED`. No new routing group or leaf is proposed.
- The new group keys `endocrine__adrenal`, `endocrine__parathyroid`, and `tumor__markers` are **proposed configuration additions within existing leaves**. The first two keep adrenal and parathyroid assays out of unrelated endocrine groups. The tumor leaf crosses the FEATURED02 small/large threshold and needs `tumor__markers` for its seven new keys **and existing `bhcg`**. These groups are deterministic contract values, not runtime changes in this task.
- Each new study uses `FIXED` specimen type (`SERUM` except `acth_plasma`=`PLASMA`), collection mode `NONE`, paired specimen `NONE`. These are physician-order request values. Tube, preservative, transport, actual time and accession handling remain with the performing provider/lab. The fixed specimen appears in the existing CAT03B immutable order-item snapshot once CAT04B implements the config.
- Every new order item remains a single canonical study identity under current `order_item_id` and exact-source result linkage. No new calculated result, panel component or result writer is authorized. Display/alias changes to existing identities do not rewrite issued-order snapshots.
- Proposed aliases are **additions**. `aliases_to_remove=[]` for all 28 targets. Search terms include key, display and aliases; display/key text is not duplicated as an alias when unnecessary. `A1c` resolves by substring of `HbA1c`; an extra literal alias is unnecessary.

## A. Exact 20 new identities

| Stable key | Spanish display | Category / leaf / full-catalog group | Routing | Specimen | Exact aliases to add | Priority | Source |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `crp_standard` | Proteína C reactiva convencional | `LABORATORIO` / `inflammation` / `DIRECT_DISPLAY` | `CLINICAL_LAB` | `FIXED:SERUM` | CRP convencional, Proteína C reactiva estándar | `P1_COMMON_GENERAL` | C1; N1; M1; R1; CAT04A_R1_CONTRACT |
| `psa_total` | Antígeno prostático específico total | `LABORATORIO` / `tumor` / `tumor__markers` | `CLINICAL_LAB` | `FIXED:SERUM` | PSA total, APE total, Antígeno prostático total | `P1_COMMON_GENERAL` | C1; N1; M1; M4; R2; CAT04A_R1_CONTRACT |
| `insulin_serum` | Insulina sérica | `LABORATORIO` / `endocrine` / `endocrine__glycemic` | `CLINICAL_LAB` | `FIXED:SERUM` | Insulina, Insulina en suero | `P1_COMMON_GENERAL` | C1; N1; M1; M3; CAT04A_R1_CONTRACT |
| `cortisol_serum` | Cortisol sérico | `LABORATORIO` / `endocrine` / `endocrine__adrenal` | `CLINICAL_LAB` | `FIXED:SERUM` | Cortisol, Cortisol en suero | `P1_COMMON_GENERAL` | C1; N1; M1; M3; CAT04A_R1_CONTRACT |
| `syphilis_vdrl_serum` | VDRL sérico | `LABORATORIO` / `infectious_serology` / `DIRECT_DISPLAY` | `CLINICAL_LAB` | `FIXED:SERUM` | VDRL en suero, Prueba VDRL sérica | `P1_COMMON_GENERAL` | C1; N1; M1; CAT04A_R1_CONTRACT |
| `ldh_serum` | Lactato deshidrogenasa sérica | `LABORATORIO` / `chemistry` / `chemistry__enzymes` | `CLINICAL_LAB` | `FIXED:SERUM` | LDH sérica, DHL sérica, Lactato deshidrogenasa en suero | `P1_COMMON_GENERAL` | C1; N1; M5; CAT04A_R1_CONTRACT |
| `psa_free` | Antígeno prostático específico libre | `LABORATORIO` / `tumor` / `tumor__markers` | `CLINICAL_LAB` | `FIXED:SERUM` | PSA libre, APE libre, Antígeno prostático libre | `P2_COMMON_SPECIALTY` | C1; N1; M4; R2; CAT04A_R1_CONTRACT |
| `afp_serum` | Alfa-fetoproteína sérica | `LABORATORIO` / `tumor` / `tumor__markers` | `CLINICAL_LAB` | `FIXED:SERUM` | AFP, Alfa fetoproteína | `P2_COMMON_SPECIALTY` | C1; N1; M2; CAT04A_R1_CONTRACT |
| `cea_serum` | Antígeno carcinoembrionario sérico | `LABORATORIO` / `tumor` / `tumor__markers` | `CLINICAL_LAB` | `FIXED:SERUM` | CEA, Antígeno carcinoembrionario | `P2_COMMON_SPECIALTY` | C1; N1; M1; M2; CAT04A_R1_CONTRACT |
| `ca125_serum` | Antígeno CA 125 sérico | `LABORATORIO` / `tumor` / `tumor__markers` | `CLINICAL_LAB` | `FIXED:SERUM` | CA 125, CA-125 | `P2_COMMON_SPECIALTY` | C1; N1; M1; M2; CAT04A_R1_CONTRACT |
| `ca199_serum` | Antígeno CA 19-9 sérico | `LABORATORIO` / `tumor` / `tumor__markers` | `CLINICAL_LAB` | `FIXED:SERUM` | CA 19-9, CA19-9 | `P2_COMMON_SPECIALTY` | C1; N1; M1; M2; CAT04A_R1_CONTRACT |
| `ca153_serum` | Antígeno CA 15-3 sérico | `LABORATORIO` / `tumor` / `tumor__markers` | `CLINICAL_LAB` | `FIXED:SERUM` | CA 15-3, CA15-3 | `P2_COMMON_SPECIALTY` | C1; N1; M2; CAT04A_R1_CONTRACT |
| `acth_plasma` | Hormona adrenocorticotropa plasmática | `LABORATORIO` / `endocrine` / `endocrine__adrenal` | `CLINICAL_LAB` | `FIXED:PLASMA` | ACTH, Corticotropina plasmática | `P2_COMMON_SPECIALTY` | C1; N1; M3; CAT04A_R1_CONTRACT |
| `pth_intact` | Paratohormona intacta | `LABORATORIO` / `endocrine` / `endocrine__parathyroid` | `CLINICAL_LAB` | `FIXED:SERUM` | PTH intacta, Hormona paratiroidea intacta | `P2_COMMON_SPECIALTY` | C1; N1; M3; CAT04A_R1_CONTRACT |
| `dheas_serum` | Sulfato de dehidroepiandrosterona | `LABORATORIO` / `endocrine` / `endocrine__adrenal` | `CLINICAL_LAB` | `FIXED:SERUM` | DHEA-S, DHEAS | `P2_COMMON_SPECIALTY` | C1; N1; M5; CAT04A_R1_CONTRACT |
| `anti_ds_dna` | Anticuerpos anti-DNA de doble cadena | `LABORATORIO` / `autoimmunity` / `DIRECT_DISPLAY` | `CLINICAL_LAB` | `FIXED:SERUM` | anti-dsDNA, anti-DNA nativo | `P2_COMMON_SPECIALTY` | C1; N1; M3; CAT04A_R1_CONTRACT |
| `procalcitonin_serum` | Procalcitonina sérica | `LABORATORIO` / `inflammation` / `DIRECT_DISPLAY` | `CLINICAL_LAB` | `FIXED:SERUM` | PCT, Procalcitonina en suero | `P2_COMMON_SPECIALTY` | C1; N1; M1; CAT04A_R1_CONTRACT |
| `total_t3` | Triyodotironina total | `LABORATORIO` / `endocrine` / `endocrine__thyroid` | `CLINICAL_LAB` | `FIXED:SERUM` | T3 total | `P2_COMMON_SPECIALTY` | C1; N1; M3; CAT04A_R1_CONTRACT |
| `bicarbonate_serum` | Bicarbonato / CO2 total sérico | `LABORATORIO` / `chemistry` / `chemistry__electrolytes` | `CLINICAL_LAB` | `FIXED:SERUM` | HCO3 sérico, CO2 total sérico, Bicarbonato sérico | `P2_COMMON_SPECIALTY` | C1; N1; M3; CAT04A_R1_CONTRACT |
| `apo_b` | Apolipoproteína B | `LABORATORIO` / `chemistry` / `chemistry__lipids` | `CLINICAL_LAB` | `FIXED:SERUM` | ApoB, Apo B, Apolipoproteína B en suero | `P2_COMMON_SPECIALTY` | C1; N1; M3; CAT04A_R1_CONTRACT |

Leaf labels and group labels are exact in the machine-readable CSV. `DIRECT_DISPLAY` is the explicit group value in leaves that stay at six or fewer studies. `tumor__markers` is the required group after the tumor leaf becomes large. Every row has `collection_rule=NONE`, `paired_specimen_rule=NONE`, and `featured_eligibility=NOT_FEATURED`.

## B. Exact eight existing-identity fixes

| Key | Old display → new display | Aliases to add | Leaf/group/routing | Source |
| --- | --- | --- | --- | --- |
| `ft4` | T4 libre → T4 libre | T4L | unchanged / unchanged / unchanged | C1; N1; H1; CAT04A_R1_CONTRACT |
| `ft3` | T3 libre → T3 libre | T3L | unchanged / unchanged / unchanged | C1; N1; H1; CAT04A_R1_CONTRACT |
| `hiv_ag_ac` | VIH Ag/Ac → VIH Ag/Ac | Virus de inmunodeficiencia humana | unchanged / unchanged / unchanged | C1; N1; H1; CAT04A_R1_CONTRACT |
| `anti_ccp` | Anti-CCP → Anti-CCP | Anticuerpos anti-CCP | unchanged / unchanged / unchanged | C1; N1; H1; CAT04A_R1_CONTRACT |
| `ogtt` | Curva de glucosa (OGTT) → Curva de glucosa (OGTT) | Curva de tolerancia oral a la glucosa | unchanged / unchanged / unchanged | C1; N1; H1; CAT04A_R1_CONTRACT |
| `hba1c` | HbA1c → Hemoglobina glicosilada (HbA1c) | Hemoglobina glucosilada, Hemoglobina A1c | unchanged / unchanged / unchanged | C1; N1; H1; CAT04A_R1_CONTRACT |
| `esr` | VSG → Velocidad de sedimentación globular (VSG) | Velocidad de sedimentación globular | unchanged / unchanged / unchanged | C1; N1; H1; CAT04A_R1_CONTRACT |
| `crp_hs` | PCR ultrasensible → Proteína C reactiva ultrasensible (PCR-us) | hs-CRP, PCR-us | unchanged / unchanged / unchanged | C1; N1; H1; CAT04A_R1_CONTRACT |

The combined display+alias targets are `hba1c`, `esr`, and `crp_hs`; historical snapshot impact is **NONE**. The other five change aliases only: `ft4`, `ft3`, `hiv_ag_ac`, `anti_ccp`, `ogtt`. All eight keep existing category, routing, leaf/group membership and prior aliases. The optional lower-priority `ft4` display adjustment from the audit is **not** part of the accepted three, so `T4 libre` remains its display.

**HbA1c final search vocabulary:** existing `HbA1c` display, existing `Hemoglobina glicosilada` alias, plus new `Hemoglobina glucosilada` and `Hemoglobina A1c` aliases. `A1c` matches the display. Accent/case folding yields one key, `hba1c`; no new canonical study. No normalized exact alias collision was found for these additions.

## Specimen and result boundaries

**ACTH plasma:** `FIXED:PLASMA`; physician collection mode `NONE`; paired specimen `NONE`. The clinician orders plasma ACTH but does not choose tube, preservative or transport. Mayo’s test instructions specify chilled EDTA plasma and prompt cold processing; these are provider/laboratory preparation and collection steps, not fields in CAT03B’s physician request object. The preferred morning timing is guidance, not a forced `TIMED` collection. No new physician prompt or contract version is proposed. [Mayo ACTH](https://www.mayocliniclabs.com/test-catalog/overview/8411); [CAT03B contract](URINE_FLUIDS_CAT03B_SPECIMEN_COLLECTION_CONTRACT_V1_IMPLEMENTED.md).

**Conventional CRP:** `crp_standard`, display `Proteína C reactiva convencional`, category `LABORATORIO`, leaf `inflammation`, group `DIRECT_DISPLAY`, routing `CLINICAL_LAB`, fixed serum. Add `CRP convencional` and `Proteína C reactiva estándar`; do not add unqualified `PCR`. The current `crp_hs` remains a separate identity and receives the approved full display and `hs-CRP`/`PCR-us` aliases. Mayo distinguishes conventional CRP for inflammation from hs-CRP used for cardiovascular risk. [Mayo CRP](https://www.mayocliniclabs.com/test-catalog/Overview/9731), [Mayo hs-CRP](https://www.mayocliniclabs.com/test-catalog/Overview/800024).

**PSA:** `psa_total` and `psa_free` are separate fixed-serum orderables in `tumor` → `tumor__markers`, routing `CLINICAL_LAB`. Total aliases: `PSA total`, `APE total`, `Antígeno prostático total`; free aliases: `PSA libre`, `APE libre`, `Antígeno prostático libre`. No unqualified `PSA` or `APE` alias is assigned to either. Query `PSA` still finds both via keys and presents distinct displays. `psa_free_total_ratio` stays a derived result; `psa_total_free_panel` stays deferred. [Mayo PSA total/free](https://www.mayocliniclabs.com/test-catalog/overview/81944).

**Other specimen boundaries:** `syphilis_vdrl_serum` remains distinct from `csf_vdrl`; `ldh_serum` remains distinct from `body_fluid_ldh`. The new serum identity does not change existing CSF/body-fluid requests. Generic test name or source package is not a new analyte.

## FEATURED02 affected leaf counts

FEATURED02 renders all active studies directly when a leaf has **≤6**. Counts below use distinct active study keys in each leaf, preserving existing cross-leaf membership. No new study is added to `featured`.

| Leaf | Before | After | Final rendering |
| --- | ---: | ---: | --- |
| `autoimmunity` | 5 | 6 | small: DIRECT_DISPLAY |
| `chemistry` | 34 | 37 | large: full-catalog groups |
| `endocrine` | 15 | 21 | large: full-catalog groups |
| `infectious_serology` | 1 | 2 | small: DIRECT_DISPLAY |
| `inflammation` | 2 | 4 | small: DIRECT_DISPLAY |
| `tumor` | 1 | 8 | large: full-catalog groups |

Only **`tumor` transitions SMALL → LARGE** (1 → 8). Its full-catalog group `tumor__markers` must contain existing `bhcg` and all seven new tumor keys. Endocrine remains large and receives two new groups; chemistry remains large and reuses its enzyme, electrolyte and lipid groups. Except for `bhcg` moving from direct display into the required tumor group, no other existing study moves leaf or changes group.

## Search collisions and disambiguation

The final-query simulation uses the current physician embedded-search rule: NFD accent folding, lowercase and substring matching across key, display and aliases. **No exact new alias collides with another identity.** These six short queries match more than one canonical key:

| Query | Resulting keys | Decision |
| --- | --- | --- |
| `PCR` | `crp_hs`, `urine_protein_creatinine_panel` | Pre-existing unrelated ambiguity; unqualified PCR alias BLOCKED. Physician must use CRP convencional, PCR-us, or relación proteína/creatinina urinaria. |
| `CRP` | `crp_hs`, `crp_standard` | Two distinct CRP assays; both displays explicitly say convencional or ultrasensible. Generic CRP alias BLOCKED. |
| `PSA` | `psa_free`, `psa_total` | Two measured PSA forms; displays/aliases say total or libre. Generic PSA alias BLOCKED. |
| `T3` | `ft3`, `total_t3` | Free versus total; exact displays disambiguate. Generic T3 alias BLOCKED. |
| `VDRL` | `csf_vdrl`, `syphilis_vdrl_serum` | Serum versus LCR; specimen in exact displays. Generic VDRL alias BLOCKED. |
| `LDH` | `body_fluid_ldh`, `ldh_serum` | Serum versus body fluid; specimen in exact displays. Generic LDH alias BLOCKED. |

`A1c`/`HbA1c` → `hba1c`; `T4L`/`FT4` → `ft4`; `anti-CCP` → `anti_ccp`; `OGTT` → `ogtt`. The six multi-hit queries are **intentional or pre-existing substring search ambiguity**, not newly duplicated exact aliases. CAT04B must preserve explicit display names in the results; no generic abbreviation is added as a new alias. The pre-existing `PCR` collision is outside the eight-fix scope and requires its own search-UX decision if the Director wants an unqualified query to be unique.

## Frozen concepts and implementability gate

- Panels: `psa_total_free_panel`, `abo_rh`.
- Derived/calculated: `psa_free_total_ratio`, `non_hdl_result`, `transferrin_saturation_result`, `bilirubin_indirect_result`.
- Uncertain: `vitamin_d_25oh`. Duplicate-risk `crp_pcr` and `tibc_uibc`, and advanced-defer rows remain unchanged.
- All 28 target rows have stable key, action, display, category, routing, leaf, full-catalog group or `DIRECT_DISPLAY`, specimen rule, alias addition/removal, featured decision and source authority. **Implementable target count: 28.** This means the contract is deterministic; Director review remains required before CAT04B implementation.

## Authority sources

- Internal: active `clinical_study_types` in read-only director review DB; `study_featured_navigation_v1.json`; HIER03 and FEATURED02 docs/config; `study_order_routing_v1.json`; `study_specimen_requirements_v1.json`; CAT04A matrix and report.
- External: [Mayo ACTH plasma](https://www.mayocliniclabs.com/test-catalog/overview/8411), [Mayo conventional CRP](https://www.mayocliniclabs.com/test-catalog/Overview/9731), [Mayo hs-CRP](https://www.mayocliniclabs.com/test-catalog/Overview/800024), [Mayo PSA total/free](https://www.mayocliniclabs.com/test-catalog/overview/81944), [Mayo ApoB serum](https://www.mayocliniclabs.com/test-catalog/Overview/624438), [Mayo procalcitonin serum](https://www.mayocliniclabs.com/test-catalog/overview/40947), [Mayo PTH serum](https://www.mayocliniclabs.com/test-catalog/Overview/800172), [Mayo bicarbonate serum](https://www.mayocliniclabs.com/test-catalog/Overview/800005), [Mayo total T3 serum](https://www.mayocliniclabs.com/test-catalog/overview/8613), and Mexican-provider sources already linked in CAT04A. Provider handling may differ by method/location; the fixed physician-request specimen does not prescribe routine tube/container details.

**Next proposed task:** `LAB-CAT04B-RERUN`. No clinical schema migration or runtime implementation was performed in R1.
