# PROC-CAT01 — Diagnostic procedures domain and Mexico coverage

**AUDIT_ONLY · PROPOSED · DIRECTOR_REVIEW_REQUIRED**

Source HEAD: `c8725748a2c5bfb142d60e3e4a581b6e5a3f70cf`. No catalog, writer, reader, routing, schema or UI implementation is authorized by this document.

## Evidence and current authority

The local review database has **13 active `ENDOSCOPIA` identities**, matching the 13 source rows in `2026_09_30_15_initial_curated_study_catalog.sql`. HIER03 and `study_featured_navigation_v1.json` expose four leaves: Endoscopia digestiva (9), Broncoscopía (2), Laringoscopía (1), Pleuroscopía (1). The digestiva leaf groups upper/biliopancreatic (EGD, EUS, ERCP), small bowel/capsule (enteroscopy, capsule), and lower/anorectal (colonoscopy, flexible sigmoidoscopy, anoscopy, proctoscopy). SEARCH02-R1 has specific EGD/EDA, EUS/USE and CPRE/ERCP abbreviations but lacks several common terms below. Existing order items snapshot a study ID/key/display name; there is **no procedure-specific parameter authority**. The existing order composition batches by `study_order_routing_v1.json`; results link exact order document and item IDs; the portable order prints the saved snapshot and indication/notes. Thus an order may be placed and a PDF report/result linked, but consent, performance, tissue acquisition, pathology accession, and specialized findings are not modeled by the study identity.

The preceding STUDY-COVERAGE01 matrix proposed 1 P1 and 2 P2 procedure gaps. This deeper audit retains **one P1 procedure**, reclassifies its two motility P2 suggestions as *functional studies*, and identifies **two different P2 diagnostic procedures**. This is a proposed product priority, not a prevalence estimate. Mexican sources document the existence of services, not universal availability at every site.

## Exact domain boundary

Each row below has **one primary classification** from the requested taxonomy. A procedure can produce an image or specimen without its identity becoming the imaging interpretation or pathology examination.

| Concept | Primary classification | Decision |
|---|---|---|
| EGD/EDA, colonoscopy, flexible sigmoidoscopy, enteroscopy, EUS, bronchoscopy, EBUS, laryngoscopy, pleuroscopy, anoscopy, proctoscopy, capsule endoscopy | DIAGNOSTIC_PROCEDURE | Existing identities; preserve distinct scopes. |
| Diagnostic CPRE/ERCP | DIAGNOSTIC_PROCEDURE | Existing identity, explicitly restrict to diagnostic intent. |
| Panendoscopia, gastroscopia, endoscopia alta, esofagogastroduodenoscopia, EGD | ALIAS_OF_EXISTING | All resolve to `egd_eda_base`; do not create another upper GI identity. |
| Broncofibroscopia / broncoscopia flexible | ALIAS_OF_EXISTING | `bronchoscopy_base`. |
| Nasofibrolaringoscopia (visualization of larynx) | ALIAS_OF_EXISTING | `laryngoscopy_base`; a purely nasal scope is not automatically this identity. |
| Diagnostic colposcopy | TRUE_MISSING_CANONICAL | P1; observation/documentation, biopsy separate. |
| Simple diagnostic cystoscopy | TRUE_MISSING_CANONICAL | P2; exclude catheter/stent/therapy. |
| Office diagnostic hysteroscopy | TRUE_MISSING_CANONICAL | P2; exclude operative hysteroscopy. |
| Esophageal manometry, esophageal pH monitoring with or without impedance, anorectal manometry | DIAGNOSTIC_STUDY | Gastrointestinal physiology under Functional Studies; distinct from endoscopy. The anorectal item is deferred within that family. |
| EUS-guided FNA, EBUS-guided TBNA, bronchial brushing/washing/BAL, colposcopic cervical biopsy, cystoscopic biopsy | SAMPLE_ACQUISITION_PROCEDURE | A performed acquisition/event; cytology, microbiology or histopathology examination has separate order/result authority. |
| Diagnostic scope with *possible* biopsy during the visit | PROCEDURE_PLUS_OPTIONAL_BIOPSY | The scope is orderable; actual acquisition is recorded only if performed, and a pathology order is separate. |
| Polypectomy, endoscopic resection, operative hysteroscopy, therapeutic bronchoscopy | THERAPEUTIC_PROCEDURE | Future treatments/procedures workflow. |
| ERCP sphincterotomy, stone extraction or stent placement; cystoscopic ureteric stent | INTERVENTIONAL_PROCEDURE | Future intervention authority, never inferred from a diagnostic order. |
| Sedation choice, anesthesia, fasting, bowel preparation, medication adjustment, laterality/site, route, technique | PARAMETER_NOT_IDENTITY | Consent/provider capability/logistics or future versioned preparation metadata; no separate study identity. |
| Separate “colonoscopy with possible biopsy” order, separate high-resolution manometry identity, separate pH-impedance identity before intent evidence | ADVANCED_DEFER | Avoid package and technique proliferation. |
| Pure diagnostic nasal endoscopy/nasopharyngoscopy distinct from laryngeal inspection | UNCERTAIN | Needs explicit order intent and site-level offering proof before a new key. |

## Current 13 identities: disposition

| Canonical key | Current routing | Audit disposition |
|---|---|---|
| `egd_eda_base` | DIGESTIVE_ENDOSCOPY | KEEP; ADD_ALIAS/SEARCH_TERM_GAP: panendoscopia, gastroscopia, endoscopia alta; no duplicate. |
| `colonoscopy_base` | DIGESTIVE_ENDOSCOPY | KEEP; search “colonoscopía”; optional biopsy is an acquisition event, not a second colonoscopy. |
| `flex_sig_base` | DIGESTIVE_ENDOSCOPY | KEEP; SEARCH_TERM_GAP: rectosigmoidoscopia; clinically different extent from total colonoscopy. |
| `anoscopy_base` | DIGESTIVE_ENDOSCOPY | KEEP; narrow anal examination, not a colonoscopy alias. |
| `proctoscopy_base` | DIGESTIVE_ENDOSCOPY | KEEP; rectal scope distinct from anoscopy/sigmoidoscopy. |
| `ercp_cpre_base` | DIGESTIVE_ENDOSCOPY | KEEP with DISPLAY_RENAME_ONLY proposed (“CPRE diagnóstica”); PARAMETER_GAP/scope guard so order cannot promise extraction, sphincterotomy or stent. |
| `eus_use_base` | DIGESTIVE_ENDOSCOPY | KEEP; ADD_ALIAS: ecoendoscopia, ultrasonido endoscópico; FNA separate acquisition. |
| `capsule_base` | DIGESTIVE_ENDOSCOPY | KEEP; ADD_ALIAS “videocápsula endoscópica”; preparation metadata later. |
| `enteroscopy_base` | DIGESTIVE_ENDOSCOPY | KEEP; technique/depth is future parameter, not new identity by default. |
| `bronchoscopy_base` | RESPIRATORY_DIAGNOSTIC_PROCEDURES | KEEP; ADD_ALIAS broncoscopia flexible/broncofibroscopia; BAL/brushing separate acquisition. |
| `ebus_base` | RESPIRATORY_DIAGNOSTIC_PROCEDURES | KEEP; distinct capability from routine bronchoscopy; needle aspiration/cytology separate. |
| `laryngoscopy_base` | ENT_DIAGNOSTICS | KEEP; ADD_ALIAS nasofibrolaringoscopia and fibrolaringoscopia where larynx is the target; do not silently equate every nasal scope. |
| `pleuroscopy_base` | RESPIRATORY_DIAGNOSTIC_PROCEDURES | KEEP; DISPLAY_RENAME_ONLY proposed “Pleuroscopia diagnóstica / toracoscopia médica”; surgical VATS and therapeutic pleurodesis outside this identity. |

No existing row needs a routing change today. The proposed display names and aliases are future changes only.

## Clinical decisions and result boundary

- **Colposcopy:** `colposcopy_diagnostic`, “Colposcopia diagnóstica”, P1. Visual examination and report; biopsy is neither mandatory nor implied. V1 result: report PDF sufficient, structured findings and media optional. The referring indication is existing order text. **No required structured order parameter** and no new parameter authority for this minimum set. Actual biopsy requires an acquisition event and separate pathology request/result. The HGM form itself records whether biopsy occurred, rather than treating it as a prerequisite.
- **Upper GI:** EGD/EDA/endoscopia alta/panendoscopia/gastroscopia are one order intent. A provider's “with pathology” package describes possible downstream work, not a distinct EGD identity. Colonoscopy follows the same rule. Flexible sigmoidoscopy is already a distinct identity and remains P2 specialty coverage.
- **CPRE:** retain existing `ercp_cpre_base` only for explicitly diagnostic CPRE. A future UI/authority must stop the display from promising therapeutic capability. If a service cannot offer a diagnostic-only CPRE, it must not be matched as an eligible provider for that order. The actual therapy needs its own future workflow.
- **EUS and EBUS:** both already distinct from ordinary scope identities. Their ultrasound examination belongs to digestive and respiratory procedure routing respectively. FNA/TBNA is acquisition; cytology/pathology is separate. A general bronchoscopy service does not establish EBUS capability.
- **Pleuroscopy:** existing medical pleuroscopy is specialty diagnostic coverage; do not add a second thoracoscopy identity. Surgical thoracoscopy and pleurodesis are separate acts.
- **Capsule:** existing `capsule_base` is diagnostic visual examination under digestive endoscopy operational routing; it has no therapeutic scope. It needs later preparation metadata, not a new identity.
- **Cystoscopy/hysteroscopy:** Mexican hospital manuals separately describe simple diagnostic cystoscopy versus biopsy/stent, and office diagnostic versus operative hysteroscopy. Both are P2 additions subject to Director approval and safe route ownership. V1 report PDF sufficient; structured findings/media optional.
- **Motility:** manometry and pH/impedance assess physiology, so put future identities in Functional Studies, not the diagnostic-procedure family. High-resolution manometry is a technique/parameter of manometry, not a second default order. pH monitoring with or without impedance may need a versioned technique parameter before activation. The original two P2 concepts remain real *cross-family* gaps, but are excluded from this procedure count.

`BIOPSY_IF_INDICATED` is **not** a V1 order flag. Clinical indication can mention suspected lesions, while the performing provider obtains consent and records actual acquisition. A future cross-authority procedure-to-specimen relationship must preserve specimen source, container IDs and pathology accession. Routine sedation/anesthesia choices belong to provider capability, preprocedure assessment and consent. Fasting, bowel preparation and medication adjustments belong to versioned site/provider preparation metadata, with patient-facing instructions issued by the provider. Do not encode any of these as diagnostic-study identities.

## Navigation, routing and provider matching proposal

Retain the four existing populated leaves. Add Ginecología (colposcopy and hysteroscopy) and Urología (cystoscopy) **only when approved keys are active**. Motilidad/fisiología belongs under Functional Studies; no empty procedure leaf. Proposed new routing owners `GYNECOLOGIC_DIAGNOSTICS` and `UROLOGIC_DIAGNOSTICS` are necessary because none of the existing groups represents these services. This is a proposed configuration change requiring Director approval; no group is created here. The two motility concepts require a later Functional Studies routing decision, not reuse of `DIGESTIVE_ENDOSCOPY` merely because the service may be housed in gastroenterology.

Provider matching must use verified **exact capability** at the location, not a broad specialty or sibling scope: EGD≠colonoscopy, bronchoscopy≠EBUS, diagnostic cystoscopy≠stent placement, diagnostic hysteroscopy≠operative hysteroscopy, colposcopy≠cervical biopsy/pathology. A place may present an order to the patient even when no verified provider match is available; do not infer recommendations from mere service listing.

## Priority and next authority

**Procedure P1 (1):** `colposcopy_diagnostic`. **Procedure P2 (2):** `cystoscopy_diagnostic`, `hysteroscopy_diagnostic`. The latter two are newly identified by procedure-specific Mexican evidence; they replace the two motility rows in the prior *procedure* count. **Cross-family Functional P2 (2):** `esophageal_manometry`, `esophageal_ph_monitoring` (technique could include impedance; naming/contract requires separate Functional approval). Anorectal manometry, pure nasal endoscopy, advanced acquisitions and therapeutic acts are deferred/uncertain. The minimum safe procedure set is three identities. No shared procedure parameter contract is required to order those three; the required future work is approved routing ownership, exact capability matching, preparation responsibility and result provenance.

Next proposed task: **PROC-CAT02A** — Director decision on these three keys, two route owners, diagnostic-only scope guards and exact provider capability; then a separate implementation task. Do not implement from this audit alone.

## Mexico source register

Source IDs and exact claims are in `PROC_CAT01_MEXICO_SOURCE_MATRIX.csv`. Key primary sources: [HGM GI endoscopy manual](https://hgm.salud.gob.mx/normateca/manuales_de_procedimientos/DCM/MAN_PROC_SERV_END_2024.pdf), [HGM pulmonary/bronchoscopy manual](https://hgm.salud.gob.mx/normateca/manuales_de_procedimientos/MP-NEUMOLOGIA-SELLO-28JUNIO2024.pdf), [HGM pathology/colposcopy form](https://hgm.salud.gob.mx/normateca/manuales_de_procedimientos/DCM/MAN_PROC_SERV_PAT_2024.pdf), [HGM urology manual](https://hgm.salud.gob.mx/normateca/manuales_de_procedimientos/DCM/MAN_PROC_SERV_URO_2022.pdf), [HGM gynecology manual](https://hgm.salud.gob.mx/normateca/manuales_de_procedimientos/DCM/MAN_PROC_SERV_GIN_2023.pdf), [HGM ENT manual](https://hgm.salud.gob.mx/normateca/manuales_de_procedimientos/DCM/MAN_PROC_SERV_OTO_2024.pdf), [INER EBUS activity report](https://iner.salud.gob.mx/descargas/informelabores/2023.pdf), [Médica Sur endoscopy unit](https://ensenanza.medicasur.com.mx/es/ms/Unidad_de_Endoscopia), [Médica Sur diagnostic endoscopy packages](https://enfermedadesdigestivas.medicasur.com.mx/es/ms/Paquetes_y_Promociones), [Médica Sur cystoscopy description](https://adultomayor.medicasur.com.mx/es/ms/ms_sal_em_litren_citoscopia_cistouretroscopia), [Médica Sur gynecology services](https://laboratorio.medicasur.com.mx/es/ms/Atencion_Ginecologica_Tlalpan), [IMSS capsule endoscopy](https://www.imss.gob.mx/prensa/archivo/201709/274), [IMSS motility procurement specification](https://reposipot.imss.gob.mx/OOAD/COLIMA/2025/1.%20PRIMER%20TRIMESTRE%202025/LA-50-GYR-050GYR012-N-15-2025/2.-%20CONVOC%20LA-012-N-15-25.pdf). Some HGM manuals are dated source documents; they establish service distinctions, not current scheduling or universal availability.
