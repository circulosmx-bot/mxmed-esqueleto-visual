# IMG-CAT01 — Imaging domain model and Mexico coverage authority

**Status:** `AUDIT_ONLY | PROPOSED | DIRECTOR_REVIEW_REQUIRED`
**Source HEAD:** `d300a2d793aa31d9c8ea5ddc5475a88b05d24ca1`
**Scope:** Contract audit only. No catalog, alias, navigation, routing, schema, writer, reader, frontend or backend mutation.

## Director summary

| Decision | Proposal |
| --- | --- |
| Current imaging active count | **34** general `IMAGEN` keys. The live `IMAGEN` category has 38 keys including four dental images; six `CARDIOVASCULAR` echo/Doppler keys are additional navigation cross-routes, not part of the 34. |
| Imaging concepts reviewed | **82** matrix rows: 34 current general, 6 current cardiovascular cross-route, 15 missing P1/P2, and 27 parameter, alias, deferred, provider or boundary concepts. |
| True missing P1 / P2 | **7 / 8**, after reassessment of COVERAGE01's 3 / 5. Fifteen proposed new identities remain below the ≤20 target. |
| Identity | Stable modality + anatomical region or specialized clinically distinct technique; parameters carry side, contrast, views, purpose and territory. No body-part × side × contrast × view multiplication. |
| Contrast | Hybrid: angiographic and named contrast examinations may be distinct identities; contrast intent/route are structured, modality-constrained fields on an identity. |
| Laterality / projections | Structured conditional fields; no default bilateral, no default no-contrast, no per-view identity. |
| Vascular territory | Structured and required for CTA/MRA/Doppler where the identity does not already fix it; no inferred combined head+neck scope. |
| Minimum safe identities | The 15 `TRUE_MISSING_CANONICAL` rows in the implementation matrix are **proposed for Director approval**, not yet activated. |
| Parameter contract | **Required first**: `imaging_order_parameters_v1`, scoped to stable physician intent. Next proposed task: **IMG-CAT02A**. |

The two CSVs are the row-level authority for this proposal: [implementation matrix](IMG_CAT01_IMPLEMENTATION_MATRIX.csv) and [parameter matrix](IMG_CAT01_PARAMETER_MATRIX.csv). Priority means proposed implementation sequence, not a prevalence claim. A named offering at one provider does not prove availability at every location.

## Baseline and evidence method

The 34-key baseline comes from the active catalog inventory in `STUDY_COVERAGE01_GAP_MATRIX.csv` and agrees with the `GENERAL_IMAGING` routing count. HIER03 has a medical Imagenología root with Radiografía y fluoroscopía, Ultrasonido (general, obstétrico/ginecológico, cardíaco, vascular), Tomografía, Resonancia magnética, Medicina nuclear y PET, Imagen mamaria, and Densitometría. FEATURED02 is a presentation layer over those keys. The complete imaging escape includes four dental images; their operational routing remains `DENTAL_DIAGNOSTICS`. The six cardiovascular studies keep `CARDIOVASCULAR_DIAGNOSTICS` despite physical access through Ultrasonido. This preserves STUDY-NAV-FIX02A and HIER03 semantics. No new leaf is proposed until it has a real catalog member.

Mexican evidence is used at the **named service** level: [Chopo radiography](https://www.chopo.com.mx/default/estudios/radiologia), [Chopo CT](https://www.chopo.com.mx/default/estudios/tomografia), [Chopo MRI](https://www.chopo.com.mx/morelia/estudios/resonancia-magnetica), [Médica Sur imaging](https://radiologiaeimagen.medicasur.com.mx/es/ms/Info_estudios_imagen), [Médica Sur mammography/tomosynthesis](https://laboratorio.medicasur.com.mx/es/ms/ms_sal_em_radima_mastografia_digital), and [Médica Sur nuclear medicine](https://medicasur.com.mx/es/ms/Medicina_Nuclear_diagnostica). [Salud Digna's booking service](https://www.salud-digna.org/citas) corroborates imaging family availability but does not establish each proposed item. [RadiologyInfo on contrast](https://www.radiologyinfo.org/en/info/safety-), [MRA](https://www.radiologyinfo.org/en/info/angiomr), and [breast screening](https://www.radiologyinfo.org/en/info/screening-breast) inform clinical boundaries only; they are not evidence of Mexico service availability. Existing COVERAGE01 source register remains supporting context. Local provider menus change by city, so activation and matching require location-level capability validation.

### Current 34 canonical imaging keys

`breast_us`, `ct_abdomen_pelvis`, `ct_chest`, `ct_head`, `ct_uro`, `dexa`, `fluoro_barium_enema`, `fluoro_hsg`, `fluoro_ugi`, `fluoro_vcug`, `mammo`, `mr_abdomen`, `mr_brain`, `mr_knee`, `mr_shoulder`, `nm_bone_scan`, `nm_thyroid_uptake`, `pet_ct`, `rx_abdomen`, `rx_ankle`, `rx_chest`, `rx_cspine`, `rx_hand`, `rx_knee`, `rx_lspine`, `rx_pelvis`, `rx_shoulder`, `us_abdomen`, `us_obstetric_study`, `us_pelvic`, `us_renal`, `us_soft_tissue`, `us_testicular`, `us_thyroid`.

Every one has a `CURRENT` row in the implementation matrix. The four active dental image keys and six cardiovascular cross-route keys are explicitly separate there.

## Identity and modality authority

| Family | Classification | Boundary |
| --- | --- | --- |
| Radiografía, ultrasonido, tomografía computada, resonancia magnética, mastografía, densitometría, medicina nuclear, PET/CT | Primary modalities or service families | Keep region/clinically distinct technique in the canonical key. |
| Fluoroscopia, Doppler vascular, angio-TC, angio-RM | Submodalities/techniques | Named examinations or vascular acquisition differ enough from plain radiography, generic US, CT or MRI to retain distinct identities. |
| Contrast choice, IV/oral route, view set, weight bearing, obstetric purpose, DXA sites, PET tracer | Protocol/preset or structured intent | Do not create identities for each combination. |
| Technical scanner sequence, kernel, field strength, radiation dose, positioning | Provider workflow | Excluded from physician order authority. |

A region belongs in the key when it defines a stable orderable and provider capability: head vs sinus CT, brain vs lumbar MRI, hip vs pelvis radiograph. An exact laterality, contrast state, projection or vascular subterritory belongs in a validated parameter. `ct_abdomen_pelvis` is a combined anatomical order, not an automatic substitute for isolated abdomen or pelvis. Similarly, `rx_pelvis` does not fulfill `rx_hip`. Source display names may be improved without key migration; changes to aliases or names require a later task.

## Modality findings

- **CT and CTA.** Retain `ct_head`, `ct_chest`, `ct_abdomen_pelvis`, and specialized `ct_uro`. Add `ct_sinuses` and `ct_neck` as regional orders. `cta_head_neck` is a proposed vascular technique with mandatory `HEAD`, `NECK`, or `HEAD_AND_NECK` territory; a neck CTA order must never imply head coverage. [Chopo lists sinus CT](https://www.chopo.com.mx/estudios/tomografia/tomografia-computada-de-senos-paranasales) and [carotid neck CTA](https://www.chopo.com.mx/toluca/angiotomografia-del-cuello-carotidas). Isolated abdomen/pelvis, spine and extremity CT need further named-service and demand proof before P1/P2 activation.
- **MRI and MRA.** Retain brain, shoulder, knee, abdomen. Add lumbar and cervical spine, pelvis, and `mra_brain`. MRA is vascular acquisition, not brain MRI with a search alias. Gadolinium is an explicit choice: [Chopo offers contrast MRA](https://www.chopo.com.mx/tijuana_ensenada/angioresonancia-de-craneo-con-contraste) and [a noncontrast MRA](https://www.chopo.com.mx/yucatan/angioresonancia-1reg-simple). Thoracic spine, pituitary, hip/ankle, breast MRI and MRCP remain P3 candidates pending named offering and workflow details.
- **Radiography.** Retain nine current plain RX regions. Add hip, foot, wrist and elbow at P1, thoracic spine at P2. Skull, sinuses, long bones and pediatric presets remain deferred. AP/PA/lateral/oblique, weight bearing and special views are requested view sets/presets. A different view is not a new study key. The [Chopo radiography menu](https://www.chopo.com.mx/default/estudios/radiologia) and [hip radiograph listing](https://www.chopo.com.mx/puebla/rx-de-articulacion-coxofemoral-bilateral) support dedicated region orders.
- **Ultrasound and Doppler.** General US identities cover abdomen, renal, thyroid, soft tissue, testicular, pelvic, obstetric and breast. Transvaginal is an explicit approach on `us_pelvic`; morphology/trimester and optional obstetric Doppler are explicit intent on `us_obstetric_study`. This does not imply all providers can perform every approach. Echo TTE/TEE/stress and carotid/lower-extremity arterial/venous Doppler are existing cardiovascular identities with cross-navigation only. Upper-extremity, renal, portal and obstetric Doppler need separate territory/service validation. Do not route all ultrasound to general imaging merely because the physical modality is US.
- **Breast imaging.** `mammo` remains one key with mandatory screening vs diagnostic purpose and unilateral/bilateral intent. `breast_us` remains separate. Tomosynthesis is a separate proposed capability because acquisition and matching differ from standard mammography, but [Médica Sur describes it alongside mammography](https://laboratorio.medicasur.com.mx/es/ms/ms_sal_em_radima_mastografia_digital); provider co-performance and billing must be modeled, not silently doubled. BI-RADS is a result finding, never an order identity.
- **DXA.** Retain `dexa` with lumbar/hip/whole-body site set. Whole-body capability must be matched separately; it is not automatically provided by any DXA site.
- **Nuclear/PET.** Retain bone scan, thyroid uptake and PET/CT. Add renal scan and myocardial perfusion at P2 based on [Médica Sur's named nuclear services](https://medicasur.com.mx/es/ms/Medicina_Nuclear_diagnostica). Hepatobiliary is P3 pending provider-level proof. PET/CT FDG is a controlled tracer choice, not permission to offer every tracer; specialty tracers require curated codes and location capability.
- **Fluoroscopy/contrast radiology.** Keep existing HSG, UGI, VCUG and barium enema as named examination identities; these are not simple projection variants. Esophagram, standalone cystography and arthrography remain P3. Contrast agent/route for a named exam is not the CT/MR IV enum. Biopsy, drainage, access and catheter angiography are separate interventional procedure authority, not imaging study catalog extensions.

## Reassessed missing set

| Priority | Proposed key | Physician-facing name | Reason |
| --- | --- | --- | --- |
| P1 | `rx_hip` | Radiografía de cadera | Hip joint is not pelvis; laterality/views are parameters. |
| P1 | `ct_sinuses` | TAC de senos paranasales | Distinct sinus region/protocol from `ct_head`. |
| P1 | `mr_lumbar_spine` | Resonancia magnética de columna lumbar | Distinct spinal region from existing brain/joint MRI. |
| P1 | `rx_foot`, `rx_wrist`, `rx_elbow` | Radiografía de pie, muñeca, codo | Common discrete regions on Mexican radiography menus, not covered by ankle/hand/shoulder. |
| P1 | `mr_cervical_spine` | Resonancia magnética de columna cervical | Distinct spinal region on Mexican MRI menus. |
| P2 | `ct_neck`, `cta_head_neck` | TAC de cuello, angio-TC de cabeza/cuello | Regional CT versus vascular technique; CTA territory required. |
| P2 | `mra_brain`, `mr_pelvis` | Angio-RM cerebral, RM de pelvis | Vascular technique and separate anatomic region. |
| P2 | `breast_tomosynthesis` | Tomosíntesis mamaria | Separate acquisition/capability, with mammography relationship. |
| P2 | `rx_tspine` | Radiografía de columna torácica | Region missing from cervical/lumbar RX. |
| P2 | `nm_renal_scan`, `nm_myocardial_perfusion` | Gammagrama renal, perfusión miocárdica nuclear | Named nuclear services with different purpose/protocol. |

These are 7 P1 and 8 P2 identities. COVERAGE01's eight original proposals remain present. The seven additions were selected only where a named Mexican provider menu/service and distinct region or technique are present. P3 rows are **not** declared covered by existing identities: they remain unimplemented candidates. There is no clinical inference from `rx_hand` to wrist or from `mr_brain` to cervical spine.

## Proposed `imaging_order_parameters_v1`

The future contract is a versioned, allowlisted item-level snapshot tied to the exact order item and source order version. It validates by canonical study key: applicable fields, requiredness, allowed values, combinations and safe labels. No free-text substitutes for a required value; `UNSPECIFIED` remains unresolved where material. Relevant fields are laterality, contrast intent and route, projection/view set, weight bearing, vascular territory and flow type, protocol class, US approach, obstetric context, breast purpose/tomosynthesis relationship, DXA sites and tracer. The [parameter matrix](IMG_CAT01_PARAMETER_MATRIX.csv) specifies each. It is distinct from specimen and pathology parameter authorities and must not weaken their validation.

An order's study, region, laterality, contrast intent, requested views/protocol and vascular territory must be rendered on the portable order when applicable. Provider-controlled agent/dose, CT reconstruction, MRI sequences, radiotracer dose, scanner type and positioning stay in provider workflow. Provider matching must use parameters that materially affect capability, preparation or price; a generic modality match is insufficient. Published result metadata/report/PDF can remain V1. DICOM storage/viewing/interchange is a separate future boundary, not a prerequisite for catalog completeness.

A result must continue to carry the **exact source order document/version and exact related item IDs**. A later successor order is only lineage/navigation context; no predecessor result covers successor items by inference. Imaging parameter snapshots must follow the same immutable source-version rule. Existing replaced-order write restrictions remain. This proposal changes none of those semantics.

## Navigation, search and routing proposal

Retain HIER03's existing leaves. Radiography/fluoroscopy and nuclear/PET can later be split into separate leaves only when populated and design-approved; avoid empty leaves. New general keys route to `GENERAL_IMAGING`; current echo/Doppler keys remain `CARDIOVASCULAR_DIAGNOSTICS`; dental imaging stays `DENTAL_DIAGNOSTICS`. A future interventional authority must be reviewed separately. No routing configuration changes in this audit.

Search terms proposed for later curated alias work: `TAC`, `TC`, `tomografía`, `CT`; `RM`, `RMN`, `resonancia`, `MRI`; `US`, `USG`, `ultrasonido`, `ecografía`; `RX`, `rayos X`, `radiografía`; `mastografía`, `mamografía`; `densitometría`; `Doppler`; `angio-TC`, `angioTAC`, `CTA`; `angio-RM`, `MRA`. These are search synonyms, not order identity or coverage claims. SEARCH02-R1 behavior is untouched.

## Implementation gate and open decisions

**Implementation authority is not complete until Director approval of this proposal and a versioned parameter contract.** IMG-CAT02A should implement and test `imaging_order_parameters_v1` first, including exact-version persistence, patient/provider portable labels, and applicability validation. A subsequent focused catalog task may activate the 15 identities after checking current provider location capabilities, clinical labels and route/nav placement. The Director must decide whether tomosynthesis always accompanies 2D mammography in MXMED, whether specific advanced P3 modalities move forward, and how provider matching represents contrast/tracer/whole-body DXA capability. No review DB mutation or implementation was performed here.
