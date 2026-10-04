# LAB-CAT04B — common clinical laboratory minimum implemented

**LAB-CAT04A / R1 status: PARTIALLY_IMPLEMENTED.** This release implements the exact 28 approved authority rows. The remaining audited panels, derived values, uncertain and duplicate-risk concepts stay deferred.

Authority: [LAB_CAT04A_R1_IMPLEMENTATION_AUTHORITY.csv](LAB_CAT04A_R1_IMPLEMENTATION_AUTHORITY.csv). The data-only migration is `2026_10_04_22_lab_cat04b_minimum.sql`. Active canonical studies: 232 → 252; no clinical schema DDL or historical snapshot rewrite.

## Twenty new canonical identities

| Key | Display | Category / leaf / group | Routing | Specimen | Aliases |
| --- | --- | --- | --- | --- | --- |
| `crp_standard` | Proteína C reactiva convencional | `LABORATORIO` / `inflammation` / `DIRECT_DISPLAY` | `CLINICAL_LAB` | `FIXED:SERUM` | CRP convencional, Proteína C reactiva estándar |
| `psa_total` | Antígeno prostático específico total | `LABORATORIO` / `tumor` / `tumor__markers` | `CLINICAL_LAB` | `FIXED:SERUM` | PSA total, APE total, Antígeno prostático total |
| `insulin_serum` | Insulina sérica | `LABORATORIO` / `endocrine` / `endocrine__glycemic` | `CLINICAL_LAB` | `FIXED:SERUM` | Insulina, Insulina en suero |
| `cortisol_serum` | Cortisol sérico | `LABORATORIO` / `endocrine` / `endocrine__adrenal` | `CLINICAL_LAB` | `FIXED:SERUM` | Cortisol, Cortisol en suero |
| `syphilis_vdrl_serum` | VDRL sérico | `LABORATORIO` / `infectious_serology` / `DIRECT_DISPLAY` | `CLINICAL_LAB` | `FIXED:SERUM` | VDRL en suero, Prueba VDRL sérica |
| `ldh_serum` | Lactato deshidrogenasa sérica | `LABORATORIO` / `chemistry` / `chemistry__enzymes` | `CLINICAL_LAB` | `FIXED:SERUM` | LDH sérica, DHL sérica, Lactato deshidrogenasa en suero |
| `psa_free` | Antígeno prostático específico libre | `LABORATORIO` / `tumor` / `tumor__markers` | `CLINICAL_LAB` | `FIXED:SERUM` | PSA libre, APE libre, Antígeno prostático libre |
| `afp_serum` | Alfa-fetoproteína sérica | `LABORATORIO` / `tumor` / `tumor__markers` | `CLINICAL_LAB` | `FIXED:SERUM` | AFP, Alfa fetoproteína |
| `cea_serum` | Antígeno carcinoembrionario sérico | `LABORATORIO` / `tumor` / `tumor__markers` | `CLINICAL_LAB` | `FIXED:SERUM` | CEA, Antígeno carcinoembrionario |
| `ca125_serum` | Antígeno CA 125 sérico | `LABORATORIO` / `tumor` / `tumor__markers` | `CLINICAL_LAB` | `FIXED:SERUM` | CA 125, CA-125 |
| `ca199_serum` | Antígeno CA 19-9 sérico | `LABORATORIO` / `tumor` / `tumor__markers` | `CLINICAL_LAB` | `FIXED:SERUM` | CA 19-9, CA19-9 |
| `ca153_serum` | Antígeno CA 15-3 sérico | `LABORATORIO` / `tumor` / `tumor__markers` | `CLINICAL_LAB` | `FIXED:SERUM` | CA 15-3, CA15-3 |
| `acth_plasma` | Hormona adrenocorticotropa plasmática | `LABORATORIO` / `endocrine` / `endocrine__adrenal` | `CLINICAL_LAB` | `FIXED:PLASMA` | ACTH, Corticotropina plasmática |
| `pth_intact` | Paratohormona intacta | `LABORATORIO` / `endocrine` / `endocrine__parathyroid` | `CLINICAL_LAB` | `FIXED:SERUM` | PTH intacta, Hormona paratiroidea intacta |
| `dheas_serum` | Sulfato de dehidroepiandrosterona | `LABORATORIO` / `endocrine` / `endocrine__adrenal` | `CLINICAL_LAB` | `FIXED:SERUM` | DHEA-S, DHEAS |
| `anti_ds_dna` | Anticuerpos anti-DNA de doble cadena | `LABORATORIO` / `autoimmunity` / `DIRECT_DISPLAY` | `CLINICAL_LAB` | `FIXED:SERUM` | anti-dsDNA, anti-DNA nativo |
| `procalcitonin_serum` | Procalcitonina sérica | `LABORATORIO` / `inflammation` / `DIRECT_DISPLAY` | `CLINICAL_LAB` | `FIXED:SERUM` | PCT, Procalcitonina en suero |
| `total_t3` | Triyodotironina total | `LABORATORIO` / `endocrine` / `endocrine__thyroid` | `CLINICAL_LAB` | `FIXED:SERUM` | T3 total |
| `bicarbonate_serum` | Bicarbonato / CO2 total sérico | `LABORATORIO` / `chemistry` / `chemistry__electrolytes` | `CLINICAL_LAB` | `FIXED:SERUM` | HCO3 sérico, CO2 total sérico, Bicarbonato sérico |
| `apo_b` | Apolipoproteína B | `LABORATORIO` / `chemistry` / `chemistry__lipids` | `CLINICAL_LAB` | `FIXED:SERUM` | ApoB, Apo B, Apolipoproteína B en suero |

Each new identity uses existing specimen contract V1, with collection and paired-specimen rules `NONE`. None is promoted to COMUNES. New accordion groups come directly from R1: `endocrine__adrenal`, `endocrine__parathyroid`, and `tumor__markers`.

## Eight existing identity fixes

| Key | Display change | Aliases added |
| --- | --- | --- |
| `ft4` | T4 libre → T4 libre | T4L |
| `ft3` | T3 libre → T3 libre | T3L |
| `hiv_ag_ac` | VIH Ag/Ac → VIH Ag/Ac | Virus de inmunodeficiencia humana |
| `anti_ccp` | Anti-CCP → Anti-CCP | Anticuerpos anti-CCP |
| `ogtt` | Curva de glucosa (OGTT) → Curva de glucosa (OGTT) | Curva de tolerancia oral a la glucosa |
| `hba1c` | HbA1c → Hemoglobina glicosilada (HbA1c) | Hemoglobina glucosilada, Hemoglobina A1c |
| `esr` | VSG → Velocidad de sedimentación globular (VSG) | Velocidad de sedimentación globular |
| `crp_hs` | PCR ultrasensible → Proteína C reactiva ultrasensible (PCR-us) | hs-CRP, PCR-us |

Stable keys and existing leaf, group, routing and specimen assignments remain intact. The three display-plus-alias fixes are `hba1c`, `esr`, and `crp_hs`. Current catalog/search labels change; issued order-item snapshots, portable orders, clinical documents and results do not.

## Clinical identity boundaries

- `hba1c` remains one canonical study. HbA1c, A1c, Hemoglobina glicosilada, Hemoglobina glucosilada and Hemoglobina A1c search the same identity.
- `psa_total` and `psa_free` are separate measured identities. Generic `PSA` is not an exact alias. The PSA total/free panel and the free/total ratio are deferred.
- `crp_standard` and `crp_hs` are distinct. Generic `CRP` and `PCR` are not added as exact aliases; a short query may return specifically labeled choices. The same principle applies to `T3`, `VDRL` and `LDH`.
- `acth_plasma` snapshots `FIXED:PLASMA`. Tube choice, EDTA, transport temperature and processing remain with the performing laboratory; the physician composer does not request them.

## Navigation and deferred concepts

Affected leaf counts: `autoimmunity` 5→6, `chemistry` 34→37, `endocrine` 15→21, `infectious_serology` 1→2, `inflammation` 2→4, `tumor` 1→8. `tumor` now uses the existing large-leaf layout: search, no COMUNES, full-catalog accordion, and custom-study fallback after expansion. No new leaf or routing group was created.

Deferred panels: `psa_total_free_panel`, `abo_rh`. Deferred calculated/derived canonical studies: `psa_free_total_ratio`, `non_hdl_result`, `transferrin_saturation_result`, `bilirubin_indirect_result`. Deferred uncertain identity: `vitamin_d_25oh`. No unresolved duplicate-risk identity was added.

## Verification

The 28 authority search-term sets were tested against the authenticated study-types API. A disposable database exercised representative order creation, fixed specimen snapshots, priority, exact order-item result linkage and portable HTML/PDF for PSA, ACTH and CRP. The six affected leaves were browser-tested at 1440×900, 1366×768 and 390×844.
