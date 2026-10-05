# IMG-CAT02A — Imaging order parameter contract V1

**Status:** `PARAMETER_CONTRACT_IMPLEMENTED_PENDING_CATALOG`
**Baseline:** `7a6e038a9ff7a828585100869e243b2a789019e4`
**Scope:** Versioned physician-intent contract only. No new study identities, aliases, routing, navigation, schema or frontend changes.

## Authority and item model

`modules/clinical/catalog/imaging_order_parameters_v1.json` is the additive `imaging_order_parameters` authority, version **1**. It has 55 per-key rules: 34 active general imaging identities, 15 future inactive IMG-CAT01 identities, and six existing cardiovascular imaging cross-routes. The future keys are configuration only; `clinical_study_types` still controls what can be ordered. All 15 future keys have an explicit rule. Existing 34 keys accept an absent parameter object unchanged, with no historical rewrite.

An item may carry `imaging_order_parameters: {version: 1, ...selected values}`. The canonical writer validates it in `clinical_study_order_snapshot`, stores a normalized order-item snapshot and stores `imaging_order_parameters_label` at issuance. Reusing an issued `order_item_id` in a successor version requires the entire parameter object and label to match exactly. This preserves the exact source-version/item identity used by linked results; no result model or read linkage changed. The order composition writer also admits the same field to the existing canonical writer. Custom/non-imaging studies reject imaging parameters.

## Controlled physician intent

| Field | V1 authority and boundary |
| --- | --- |
| Laterality | `LEFT`, `RIGHT`, `BILATERAL`, `NOT_APPLICABLE`; only allowed by compatible study rule. CT head/chest and brain MRI have no laterality field. |
| Contrast | `WITHOUT_CONTRAST`, `WITH_IV_CONTRAST`, `WITH_AND_WITHOUT_IV_CONTRAST`; abdominal/pelvic CT additionally permits `WITH_ORAL_CONTRAST`, `WITH_IV_AND_ORAL_CONTRAST`. CTA fixes IV intent; MRA allows explicit without/with IV. Routes are constrained to intent and study (`IV`, `ORAL`, `RECTAL`, `INTRA_ARTICULAR`, `INTRACAVITARY` in the authority; only applicable subsets are accepted). No contrast brand, dose or injection rate. |
| Radiography views | Version 1: `AP`, `PA`, `LATERAL`, `OBLIQUE`, `AXIAL`, `SPECIAL`. Explicit view list or one preset, never both. Presets: `CHEST_STANDARD` = PA+lateral, `JOINT_STANDARD` = AP+lateral, `SPINE_STANDARD` = AP+lateral. Presets are per-key allowlisted. |
| Weight bearing | `YES`, `NO`, `NOT_APPLICABLE` in authority; only supported regional RX rules accept meaningful choices. Omission stays unspecified, not `NO`. |
| Vascular territory | Version 1: `HEAD`, `NECK`, `HEAD_AND_NECK`, `CAROTID`, `UPPER_EXTREMITY`, `LOWER_EXTREMITY`, `RENAL`, `PORTAL_HEPATIC`. Per-key subset enforced. CTA head/neck requires exact territory; `HEAD_AND_NECK` is explicit, never inferred. |
| Flow type | `ARTERIAL`, `VENOUS`, `ARTERIAL_AND_VENOUS`; existing Doppler arterial/venous identities fix their type, so a contradictory type is rejected. |
| Ultrasound approach | `TRANSABDOMINAL`, `TRANSVAGINAL`, `TRANSRECTAL`, `COMBINED`; `us_pelvic` accepts only compatible choices. Other current US keys do not acquire a universal approach field. |
| Breast | `SCREENING` or `DIAGNOSTIC` and explicit side. Future tomosynthesis additionally requires `WITH_2D_MAMMOGRAPHY` or `STANDALONE_IF_PROVIDER_VALIDATED`; its key remains inactive. |
| DXA sites | `LUMBAR_SPINE`, `HIP_LEFT`, `HIP_RIGHT`, `BILATERAL_HIPS`, `WHOLE_BODY`; duplicate/conflicting combinations are rejected. No per-site identity multiplication. |
| Tracer | Version 1: `FDG` only, on PET/CT. No arbitrary tracer text or presumed provider capability. |
| Physician protocol | Controlled `UROGRAPHY`, `MORPHOLOGY`, `STRESS_REST`, `DYNAMIC`, `STATIC`, constrained per key. Obstetric trimester has its own controlled field. CT reconstruction, MR sequences/field strength, camera timing, radiation dose, positioning, agents and dosages remain provider technical workflow. |

These values implement the IMG-CAT01 parameter matrix. Some enumerated values are reserved for later identities but are accepted only when a per-study rule permits them. A version-only object, unknown field/value, duplicate list value, wrong version, invalid modality combination or contradiction with a fixed technique is rejected by the server. No clinical intent or anatomy is written to public logs.

## Future identity parameter map

| Inactive key | Required V1 fields | Optional permitted intent |
| --- | --- | --- |
| `rx_hip`, `rx_foot`, `rx_wrist`, `rx_elbow` | laterality | views or applicable joint preset; weight bearing only for foot |
| `rx_tspine` | parameter object V1 | views or spine preset; weight bearing if requested |
| `ct_sinuses`, `ct_neck` | contrast intent | IV route when compatible |
| `mr_lumbar_spine`, `mr_cervical_spine`, `mr_pelvis` | contrast intent | IV route when compatible |
| `cta_head_neck` | fixed IV contrast intent; vascular territory | IV route; exact `HEAD`, `NECK` or `HEAD_AND_NECK` |
| `mra_brain` | contrast intent; fixed `HEAD` territory | IV route only with IV contrast |
| `breast_tomosynthesis` | laterality; breast purpose; mammography relation | none |
| `nm_renal_scan` | `DYNAMIC` or `STATIC` physician protocol | none |
| `nm_myocardial_perfusion` | fixed `STRESS_REST` physician protocol | none |

Anatomical region stays in the canonical key for `ct_sinuses`, `mr_lumbar_spine`, `rx_hip` and other approved candidates. This contract does not make a generic region selector orderable.

## Existing imaging and portable order

The 34 active general imaging rules are classified as `OPTIONAL_PARAMETER_ENHANCEMENT`, `NO_PARAMETER_NEEDED` or `FUTURE_MIGRATION_CANDIDATE`; all accept omitted parameters. Six existing echo/Doppler cross-routes retain cardiovascular routing. The rule file is the exact per-key classification, not a migration instruction.

Portable HTML/PDF print uses the **stored Spanish snapshot label** beside each study when imaging parameters were supplied. The label includes selected laterality, contrast/route, views or preset, weight bearing, vascular territory/flow, protocol, ultrasound approach, breast purpose, DXA sites and tracer as applicable. Internal enum keys are not printed. Historical orders without the field render as before. The portable reader requires an imaging parameter object and nonempty stored label together; it does not rederive labels from the current authority. The existing doctor authorization, exact-version print rules and PDF process remain unchanged.

## QA and boundaries

`php modules/clinical/qa/img_cat02a_contract_gate.php` passed. It validates V1 metadata, all 55 per-key rules, all 15 future scenarios, omitted fields for all 34 current imaging keys, invalid side/route/territory/view/weight-bearing/tracer/key/version, incompatible breast/CTA combinations, snapshot immutability, non-imaging writer examples, and portable display of the stored label. PHP lint passed for every changed PHP file. No database migration was created or run. The catalog remains at the published **263 active studies** (including 34 general imaging identities); no row changed in this task. IMG-CAT02B may activate approved identities separately after provider capability and Director review.
