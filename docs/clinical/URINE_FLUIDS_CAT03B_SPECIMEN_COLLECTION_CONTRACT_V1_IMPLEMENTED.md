# URINE-FLUIDS-CAT03B — specimen and collection requirements V1

Baseline `cb0bcc122c29c5ea6c164547796bef338fb8c64d`. The physician order now snapshots a validated, versioned `specimen_collection_requirements` object inside each canonical V2 `order_item`. No relational schema, catalog row, new study identity, result writer, provider UI, collection event, or LIS workflow is added. Historical order items without this object remain valid.

## Domain boundary and storage

The existing V2 order-item payload is the canonical snapshot. The server validates it with `clinical_specimen_validate` alongside the existing typed dental-location parameter. The declarative study authority is `modules/clinical/catalog/study_specimen_requirements_v1.json`, consumed by server and inline compositor. It covers all 16 currently active urine/fluid/semen studies; the other 199 studies have no specimen parameter in CAT03B. Fixed specimen fields are server-filled even when the client sends no object. `order_item_id`, study identity, sequence, note, and dental fields retain their existing meaning. A replacement that claims the same item ID must present identical specimen requirements; changed requirements require a new item ID and the existing document successor lineage.

The V1 request object permits only `version`, `specimen_type_key`, `source_site_key`, `source_site_text`, `collection_mode`, `requested_duration_minutes`, and `paired_specimen`. All keys are bounded by the authority and by the study rule. `requested_duration_minutes` is an integer from that study's allowed list and only applies to `TIMED`. The paired rule is a controlled counterpart specimen and relationship, copied from the study rule, not free-form clinician input. `source_site_text` is a bounded supplement to a controlled site. No generic JSON or unrelated chart information is accepted.

Physician request values describe what should be collected. They do not claim collection happened. **Future `SPECIMEN_COLLECTION_EVENT`**, a separate operational object, would own actual specimen received, collection start/end, measured volume, actual container/preservative, collector/provider, accession ID, and rejection/quality status. CAT03B has no such event. Requested minimum/target volume is also excluded from V1 because none of the 16 configured studies needs it as a physician parameter. Routine container and preservative choices belong to test instructions, provider offering/preparation and future collection workflow.

## Controlled vocabulary

Specimen keys: `URINE`, `CSF`, `PLEURAL_FLUID`, `ASCITIC_PERITONEAL_FLUID`, `SYNOVIAL_FLUID`, `PERICARDIAL_FLUID`, `SEMEN`, `SALIVA`, `SPUTUM`, `BRONCHOALVEOLAR_LAVAGE`, `BRONCHIAL_WASHING`, `TRACHEAL_ASPIRATE`, `WHOLE_BLOOD`, `SERUM`, `PLASMA`. The last three are future compatible; no blood-test retrofit was performed. All have Spanish display labels in the authority.

Collection modes: `SPOT` (muestra aislada, including random spot where source semantics do not justify a second mode), `TIMED` (recolección cronometrada), `FIRST_MORNING`, `MIDSTREAM`. A study may offer only a subset. The source-site vocabulary starts with `JOINT` and `OTHER_ANATOMICAL_SITE`; `synovial_crystals` permits an optional site and never requires invented laterality. Paired relationship V1 is `SAME_COLLECTION_EPISODE`. No current production study is configured to require a paired specimen; the contract was tested with a disposable synthetic CSF-plus-serum rule.

If the optional joint/source-site field is used, a bounded site description identifies the joint or other anatomical location. The physician may omit the site entirely for the current synovial-crystal order.

## Configured current studies

- Urine fixed: `urinalysis`, `microalbumin`, `urine_culture`, `urine_albumin_creatinine_panel`, `urine_protein_creatinine_panel`, `urine_osmolality`, `urine_creatinine_spot`, `urine_sodium_spot`, `urine_potassium_spot`, `urine_pregnancy_qualitative`.
- CSF fixed: `csf_cell_count`, `csf_glucose`, `csf_total_protein`.
- Synovial fixed with optional joint/source-site input: `synovial_crystals`.
- Semen fixed: `semen_analysis`, `post_vasectomy_semen_check`.

Spot mode is fixed automatically for the ACR/PCR panels and three explicitly spot urine analytes. Other urine tests have fixed urine specimen but no falsely asserted collection mode. None of the 16 requires a physician prompt. Future configurable study rules can require a specimen choice, collection mode/duration, source site or paired counterpart without multiplying analyte × specimen × duration identities.

## UI, issuance and portable order

ORD-COMP01 loads the same authority and the TAX03C selection panel renders compact inline fields only for a rule requiring or allowing physician input. Required fields show native labels/required state and an explicit “Completar datos” state. Incomplete items block review; server validation repeats before any batch insert, inside the existing atomic transaction. Fixed values are snapshotted by the server without asking the physician. Optional site data never blocks issuance. The mobile editor stacks with the prepared-order panel; no modal was added.

Idempotency replays the original issued document and exact specimen snapshot. The portable HTML/PDF projection prints human Spanish specimen, timed duration, source site or paired sample only when useful, omitting fixed specimen already obvious from the study name. Exact result linkage remains order version plus `order_item_id`; the requested specimen is available in the immutable item snapshot for future provider service-order reads. Actual collected specimen belongs to the future collection event.

## Navigation and deferred catalog

The six full-catalog groups are now: Estudios generales y renales; Electrolitos y minerales urinarios; Microbiología urinaria; Semen; Líquido cefalorraquídeo (LCR); Líquido sinovial. The same six featured studies and all 215 canonical identities remain unchanged. CAT03C still evaluates stone-risk profiles, body-fluid chemistry, paired CSF studies, molecular panels, pathology/cytology and additional orderables. CAT03B does not seed them.

## QA

The ORD-COMP01 disposable gate uses a temporary schema and authentic API/portable endpoints: 215 rows unchanged, fixed specimen snapshots, exact batch replay, zero writes after specimen rejection, existing multi-order rollback, result linkage, portable HTML/PDF and legacy compatibility. A separate PHP gate checks all 16 fixed configurations, immutable replacement meaning, prohibited actual-collection fields, a QA-only 24-hour timed rule, a QA-only paired CSF/serum rule, and optional joint-site validation. A second disposable HTTP server injects a QA-only timed rule into its own process: one incomplete item in a two-order batch yields zero new orders; completion yields two issued orders, a 24-hour snapshot, exact replay, and portable HTML/PDF with the requested duration. No production catalog rule or review patient data is changed. Browser QA uses bundled Chromium with a QA-only timed rule on an existing study; it verifies required inline fields, blocking, snapshot, keyboard-compatible controls, Semen group order, desktop/mobile widths and no horizontal overflow. Live review on port 18148 validates the current checkout at desktop compact/expanded and mobile layouts without clinical writes.
