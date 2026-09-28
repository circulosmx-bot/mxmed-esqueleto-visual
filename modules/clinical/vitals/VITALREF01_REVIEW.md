# VITALREF01 clinical reference map

Registry: `modules/clinical/vitals/reference_registry.php`, version `VITALREF01.v1`.
Resolver: `api/_lib/clinical_vital_references.php`.
Sources verified on 2026-09-27. Publication/review versions below are distinct from verification date.

## Clinical map

| Measurement | Population / context | Visible reference | Source / version | Limitations |
| --- | --- | --- | --- | --- |
| BP | Adult, ≥18 completed years | Referencia adulta: normal <120/<80 mmHg | [AHA/ACC guideline](https://www.ahajournals.org/doi/10.1161/CIR.0000000000001356), 2025; [AHA category explanation](https://www.heart.org/en/health-topics/high-blood-pressure/blood-pressure-explained) | Both components must be below their respective limits. A category, not a suggested individual measurement, diagnosis, or treatment target. |
| HR | Healthy adult, resting | Referencia en reposo: 60–100 bpm | [MedlinePlus Vital signs](https://medlineplus.gov/ency/article/002341.htm), review 2025-01-01 | Informational resting context, not universal across activity or clinical conditions. |
| RR | Healthy adult, resting | Referencia en reposo: 12–18 rpm | MedlinePlus Vital signs, review 2025-01-01 | Same adult resting context; no interpretation. |
| Temperature | Healthy adult; site dependent | Referencia habitual: 36.5–37.3 °C | MedlinePlus Vital signs, review 2025-01-01 | Varies with age, time of day, and measurement site. Pediatric numeric reference not activated. |
| SpO₂ | Healthy baseline; altitude and pulmonary context | Referencia habitual: 95–100 % | [MedlinePlus Pulse Oximetry](https://medlineplus.gov/lab-tests/pulse-oximetry/), updated 2024-09-12 | Baseline may differ with pulmonary disease, altitude, or clinical context; device conditions affect accuracy. No mismatch alerts. |
| Pain | Numeric self-report tool; V1 conservatively requires ≥8 completed years and suitable capacity | Escala: 0–10 | [NCI Cancer Pain PDQ, Pain Assessment](https://www.cancer.gov/about-cancer/treatment/side-effects/pain/pain-hp-pdq), updated 2025-04-24 | Tool definition only. Subjective scale, no expected/normal score. Source discusses adults and children older than 7; younger/nonverbal patients may need another tool. No oncology recommendations imported. |
| BP | Adolescent, 13 to <18 years | Referencia adolescente: normal <120/<80 mmHg | [AAP pediatric BP guideline](https://publications.aap.org/pediatrics/article/140/3/e20171904/38358/Clinical-Practice-Guideline-for-Screening-and), 2017, table 3, DOI 10.1542/peds.2017-1904 | Distinct AAP source. No automated classification. |
| BP | Child, 1 to <13 years | Referencia pediátrica: requiere edad, sexo y talla. | AAP 2017 | Incomplete reference. Validated age/biological sex/height percentile tables are not implemented; no percentile thresholds computed, even if raw demographic fields exist. |
| BP | Infant, <1 year | Referencia para menores de 1 año no disponible. | AAP 2017 scope distinction | Neither adult thresholds nor the ≥1-year percentile tables are applied. |
| BP, HR, RR, temperature | Missing, invalid, or future canonical DOB | Referencia: requiere fecha de nacimiento válida. | No active age-specific numeric authority | No adult default. SpO₂ is a general healthy reference and independent of age; pain requires the tool's age context. |
| Weight / height / waist | All ages | No hint | No individual reference activated | No expected measurement, growth table, percentile, ethnicity inference, or waist risk threshold. |

## Pediatric HR / RR audit decision

Both statuses: `DEFERRED_ROUTINE_OUTPATIENT_AUTHORITY_NOT_VALIDATED`.

[MedlinePlus Pulse](https://medlineplus.gov/ency/article/003399.htm), review 2025-01-01, is an authoritative candidate with pediatric age bands and an explicit resting preparation (at least 10 minutes). This source exists; deferral does not imply that pediatric pulse references are unavailable in the literature. V1 does not activate its age-band protocol. A consistent pediatric routine outpatient capture protocol covering the applicable age boundaries and HR/RR contexts has not been established for this block. The initial Vital signs source establishes the requested adult resting set, not pediatric respiratory age bands.

[NICE NG143](https://www.nice.org.uk/guidance/ng143) concerns fever in children under 5 and acute illness assessment. Fever/PEWS/emergency thresholds are not imported as routine resting references. V1 shows a neutral unavailable state for pediatric HR/RR and preserves measured inputs. Activation requires a separately validated context-specific pediatric authority, rather than combining unrelated tables. Pediatric temperature is also unavailable in V1.

## Read authority and demographic safety

`GET /api/clinical/index.php/patients/{patient_id}/vital-references` uses the canonical authenticated doctor context and active physician/patient link. Only `patients_patients.birthdate` is selected. Strict `Y-m-d` validation and completed birthdays use the project timezone, America/Mexico_City. No supplied age/DOB/sex/context query parameters are accepted. The endpoint returns population, canonical age authority availability, evaluation date, and definitions; no raw DOB, name, sex, individual age, or observations.

The route executes before the legacy gateway startup schema initialization. It has no INSERT/UPDATE/DELETE/DDL, write lease, observation service, or schema initialization. No product schema changes. Existing measurement writer contracts are unchanged; the backend adds only this GET contract.

No sex field is read in V1. AAP child metadata declares biological sex and validated height requirements but the percentile engine is unavailable, so the resolver does not substitute the patient's generic sex/gender field or infer demographics from names/photos/pronouns.

## UI and state separation

The hint is a focusable informational span associated with the active input. All copy and ranges come from the server registry. It is absent from `form.elements`, measurement snapshots, drafts, and payload serialization. No reference acceptance action, prefill, placeholder measurement, diagnostic flag, or observation is provided. Hover/focus exposes source title, year, version, URL, context, and caveats; Escape closes the tooltip.

Every context load clears references, aborts the previous request, and refetches with `cache: no-store`; epoch, encounter, patient, and current-patient checks discard late replies. Reset clears references and prevents pending replies from restoring them. Read failure hides references without blocking ordinary measurement entry or replacing a genuine existing draft.

Desktop uses unused space directly above the active value field inside the accepted capture row. No extra row allocation, footer displacement, or modification to the shared shell. Tablet BP uses the unused grid cell beside the pressure/unit controls; numeric hints use the unused heading space directly above the active value field. Phone BP wraps in the unused cell below the pressure controls; numeric hints use the existing capture heading space and remain associated with the active input through aria-describedby. These placements preserve the accepted field and footer geometry without adding a flow row. Weight/height/waist hide the hint.

## Director review

Adult: `http://127.0.0.1:18148/index.html?review_patient=plan02ux&review_encounter=open&qa_tools=hide&review_vitalref=adult`

Pediatric fallback: same URL with `review_vitalref=child`. Also supports `adolescent`, `infant`, and `missing` contexts.

`modules/clinical/qa/vitalref01_review.php` is loaded only by the external local Director router. It checks loopback, the existing disposable review DB, cohort mode, and review physician session; non-GET requests and unknown contexts are rejected. Only reference resolution receives a review-only DOB; actual patient details and canonical observations remain unchanged. The JS fixture never synthesizes clinical measurements or clinical reference values. Adult resolution uses the production endpoint and real canonical DOB. Pediatric resolution invokes the same PHP resolver and registry. Product API demographic overrides remain forbidden.

## Reproducible focused QA

```sh
php modules/clinical/qa/vitalref01_registry_test.php
bash modules/clinical/qa/vitalref01_disposable_gate.sh
python3 modules/clinical/qa/vitalref01_logic.py
python3 modules/clinical/qa/step2_vitals_r1_logic.py
bash modules/clinical/qa/step2_prior_reuse_r3a_disposable_gate.sh
VITALREF_ARTIFACTS=/tmp/mxmed-vitalref01 python3 modules/clinical/qa/vitalref01_browser.py
STEP2_VITALS_REVIEW_BASE='http://127.0.0.1:18148/index.html?review_patient=plan02ux&review_encounter=open&qa_tools=hide' python3 modules/clinical/qa/step2_vitals_r4_browser.py
```

The registry gate verifies the nine-type map, versions, contexts, exact 1/13/18-year birthday boundaries, invalid/future/missing DOB, no demographic disclosure, distinct AAP/AHA authority, and absence of pediatric adult-range leakage. The disposable HTTP gate verifies access scope, rejected overrides, GET-only behavior, unchanged table inventory, and unchanged patient/link data.

The isolated WebKit safety gate checks all nine types for empty clinical inputs, no dirty state, drafts, submissions, writes, or chips; tests late-response patient switches/reset, fetch failure, and source tooltip effects on a genuine draft. Existing regression gates cover SERVER_AT_SAVE, manual origin, filtering, edit/void, canonical reuse, history modal, fixed shell, navigation, and supported viewports.

Director browser evidence uses canonical observations without fixture measurements, compares fixed shell coordinates with the accepted starting HEAD, verifies all supported measurement hints at all four sizes, captures the four required viewports and A–G cases, checks source hover/focus/Escape, verifies no JS errors or clinical writes, and hashes canonical observations before/after. Evidence is stored outside Git under `/Users/circulodigital/.codex/artifacts/vitalref01`.
