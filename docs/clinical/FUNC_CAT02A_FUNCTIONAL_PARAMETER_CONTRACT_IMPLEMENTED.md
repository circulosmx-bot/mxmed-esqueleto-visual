# FUNC-CAT02A — Functional order parameter contract V1

**Status:** `PARAMETER_CONTRACT_IMPLEMENTED_PENDING_QUALITY_AND_GI_ACTIVATION`
**Baseline:** `53a2dd50136eefe5583d9cf571f52a556998a74f`
**Scope:** Versioned physician-intent contract for 35 current functional studies and two dormant GI rules. No new canonical study, navigation leaf, routing group, provider offering, database migration, or order-composer UI was activated.

## Authority and classification

`modules/clinical/catalog/functional_order_parameters_v1.json` is the machine-readable `functional_order_parameters` authority, version **1**. It defines **37** per-key rules: 24 current `NO_PARAMETER_NEEDED`, 11 current `OPTIONAL_PARAMETER_ENHANCEMENT`, and two `FUTURE_REQUIRED_PARAMETER` rules for `esophageal_manometry` and `esophageal_ph_monitoring`. Those two keys are **not** catalog rows and remain impossible to issue through the canonical writer until a later approved activation. The table below classifies every one of the 35 currently visible Functional Studies keys. Six cardiovascular imaging identities in the old count of 41 remain governed by the imaging contract and their Imaging leaves.

| Classification | Current keys |
| --- | --- |
| Optional parameter enhancement (11) | `holter`, `video_eeg`, `emg_ncs`, `evoked_auditory_baep`, `evoked_ssep`, `evoked_visual`, `spirometry`, `audiometry_tonal`, `audiometry_speech`, `otoacoustic_emissions`, `vng` |
| No parameter needed (24) | `abpm_mapa`, `ankle_brachial_index`, `ecg_12lead`, `ecg_rhythm_strip`, `stress_test`, `tilt_table`, `eeg_routine`, `eeg_sleep_deprived`, `repetitive_nerve_stimulation`, `sfemg`, `capnography`, `cpet`, `dlco`, `feno`, `full_pft`, `overnight_oximetry`, `plethysmography`, `six_min_walk`, `hsat`, `mslt`, `mwt`, `psg_diagnostic`, `psg_titration`, `tympanometry` |

## Physician-intent choices

| Study/rule | Controlled V1 choice and boundary |
| --- | --- |
| Holter | `duration`: `24_HOURS`, `48_HOURS`, `72_HOURS`. The order remains `holter`; no event-monitor or arbitrary longer-duration setting is implied. |
| MAPA | No parameter in V1. The existing MAPA identity is separate from Holter, and no second evidence-backed duration/protocol choice is required for physician intent now. |
| Exercise ECG | No parameter in V1. It remains `stress_test`; treadmill brand, stage timing and acquisition settings are provider-owned. Stress echo remains an imaging identity. |
| EEG | `eeg_routine` and `eeg_sleep_deprived` remain distinct and need no V1 parameter. `video_eeg` permits `duration` 24/48/72 hours; this does not create an ambulatory EEG identity or assert that every provider offers every duration. |
| EMG/NCS | `body_site`: `UPPER_EXTREMITY`, `LOWER_EXTREMITY`, `FACE`; paired `side`: `LEFT`, `RIGHT`, `BILATERAL`. If either is supplied, both are required. No nerve/muscle electrode map, device setting or generic unspecified region is accepted. A physician may still use the existing clinical note for a different site pending a future bounded model. |
| Evoked potentials | BAEP/visual permit optional side; SSEP permits paired upper/lower extremity region and side. BAEP stays `NEUROPHYSIOLOGY`; no audiology duplicate is created. |
| Spirometry | `spirometry_protocol`: `BASELINE` or `PRE_POST_BRONCHODILATOR`. No bronchodilator drug brand or dose. `full_pft`, `dlco` and `plethysmography` stay separate identities without new parameters. |
| Sleep | PSG diagnostic, PSG titration, HSAT, MSLT and MWT retain distinct canonical keys. No therapeutic PAP setup or additional acquisition setting is added. |
| Audiology | Tonal and speech audiometry and otoacoustic emissions permit an optional side. Tympanometry needs no V1 parameter. |
| VNG | `caloric_intent`: `WITH_CALORIC` or `WITHOUT_CALORIC`, only when the physician explicitly specifies it. Omission makes no assertion about provider protocol. vHIT and posturography remain out of scope. |
| Future esophageal manometry | Required `gi_technique`: `CONVENTIONAL` or `HIGH_RESOLUTION`. No new identity is active. |
| Future esophageal pH monitoring | Required `gi_technique`: `PH_ONLY` or `PH_IMPEDANCE`; `duration`: `24_HOURS`; `acid_suppression`: `ON_THERAPY` or `OFF_THERAPY`. No pH-only offering can automatically satisfy a pH-impedance request. No new identity is active. |

The shared duration label maps to a **per-study allowlist**, not a universal duration field. The authority contains `NOT_APPLICABLE` as a reserved side label but no current rule accepts it; side is omitted when irrelevant. The GI technique enum is shared as a vocabulary but each future GI key accepts only its own subset. Current studies accept omission of the entire parameter object; a version-only object, unknown field, wrong key/field pair, invalid value, wrong version, and an incomplete EMG/SSEP site-side pair are rejected by the server.

## Write, snapshot and portable order

An order item may carry `functional_order_parameters: {version: 1, ...selected values}`. `clinical_study_order_snapshot` validates the key and values, writes the normalized V1 object, and persists `functional_order_parameters_label` as a Spanish human-readable snapshot. `clinical_order_composition` admits the field through its existing order-item allowlist. Reusing an issued `order_item_id` in a successor requires the exact parameter object **and label** to match; historical orders are not rewritten or reinterpreted. Missing parameters on any current identity preserve the old order shape. A future GI key without required parameters is rejected if it is ever activated; standalone result taxonomy may omit order-time intent.

The private portable-order reader checks that the V1 object and nonempty label coexist, and passes the **stored label** to the HTML/PDF template. The template prints, for example, “Duración: 24 horas”, “Protocolo: Pre y post broncodilatador”, “Región: Extremidad superior · Lado: Derecho”, or “Técnica: Alta resolución”. Internal enum keys and current authority labels are not used to regenerate historical print text. Existing orders without the field print as before. No result schema, result file format, exact source-order linkage or `related_order_item_ids` logic changed.

## Provider and search boundaries

Exact `study_type_id` offering remains the primary provider match. This contract does **not** implement parameter-level provider matching. Before matching or dispatching parameterized orders automatically, a later phase must explicitly verify compatible duration, manometry technique, pH-only versus pH-impedance capability, and any other capability-sensitive choice. A routing group alone never proves capability. The future `FUNCTIONAL_GI_PHYSIOLOGY` route is not defined or activated here.

SEARCH02-R1 is unchanged. In the current authority, `PFT` is an equivalent alias of `full_pft` and merely a discovery term for `spirometry`; exact-alias ranking puts full PFT first. This corrects the FUNC-CAT01 prose that described two equivalent aliases. `PFP` is not yet an active term; “pruebas de función pulmonar” currently finds full PFT by token-prefix matching. BAEP and PEAT are already discoverable on the neurophysiology identity; adding ABR/PEATC or audiology-context entry belongs to FUNC-CAT02B, without changing its route.

## QA and activation boundary

`php modules/clinical/qa/func_cat02a_contract_gate.php` passes authority completeness and uniqueness, all 35 current keys, both dormant GI keys, valid/invalid Holter, video EEG, EMG/NCS, evoked-potential, spirometry, audiology, VNG and GI choices, optional current-order omission, item/label immutability in successor versions, authenticated portable-reader projection, rejection of an incomplete stored label, portable Spanish label and hidden enum. Existing lab, pathology, imaging, procedure and dental order snapshots remain free of functional parameters. Existing IMG-CAT02A, PATH-CAT02A, LAB-CAT05B and DENTAL-CAT03B PHP gates also pass. The review DB was queried read-only: **287 active studies** and **zero** rows for the two proposed GI keys before and after this task.

FUNC-CAT02B may separately activate the two GI identities, add the dedicated functional GI route/leaf and exact provider capability checks, apply bounded search quality fixes, and expose appropriate current-study parameter controls in the ordering UI. No current UI sends these new optional fields yet.
