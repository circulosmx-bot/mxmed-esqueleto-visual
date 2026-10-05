# LAB-CAT05B: versioned laboratory panel, preset, and arterial gas contract

## Status and authority

This phase defines **future inactive** contracts. No canonical study row, alias, routing entry, navigation entry, or physician catalog option was activated. The active catalog remains at 278 studies. LAB-CAT05C must make the separate activation decision.

- `modules/clinical/catalog/lab_panel_definitions_v1.json` is version 1 of the true-panel definition authority.
- `modules/clinical/catalog/lab_order_presets_v1.json` is version 1 of the additive preset authority.
- `modules/clinical/catalog/study_specimen_requirements_v1.json` remains version 1 and additively defines `ARTERIAL_BLOOD` and the dormant `arterial_blood_gas` specimen rule.

The server rejects both future panel keys and all three future presets in ordinary requests. Contract QA can opt into dormant definitions inside its own process; that override cannot be supplied over HTTP.

## True panels

`panel_quimica_6` is one future canonical order item with an immutable versioned component snapshot: glucose, urea, creatinine, uric acid, total cholesterol, and triglycerides. These are six canonical analyte identities, with BUN only an optional reported observation. It is not six separately requested items. A result links to the one panel `order_item_id`. A future provider match must support the panel identity or an explicitly verified compatible offering; support for six individual analytes alone is insufficient.

`arterial_blood_gas` is also one future order item. Its result-observation definition includes arterial pH, PaCO₂, PaO₂, HCO₃⁻, arterial oxygen saturation, and optional base excess. These observations are not six catalog study identities. The order snapshot holds the component semantic keys and display labels, so later authority edits cannot reinterpret an issued order. Existing panels such as CBC, OGTT, urine ratio panels, and CSF meningitis/encephalitis retain their current meanings; historical order snapshots are untouched.

## Presets and ordering

The renal QS3 preset expands to glucose, urea, and creatinine. The lipids QS3 preset expands to glucose, total cholesterol, and triglycerides. The basic hepatic preset expands to AST, ALT, ALP, GGT, total protein, albumin, total bilirubin, and direct bilirubin. All current components resolve to active canonical laboratory studies in the `CLINICAL_LAB` routing group.

Presets are additive draft conveniences. Expansion deduplicates by canonical `study_type_id` against already selected studies and previously expanded presets; reapplying the same preset adds nothing. Removing a future visual preset marker must leave the ordinary selected study items intact, so manually retained items cannot disappear. Presets create no pseudo-panel item. The issued order stores server-generated provenance with preset key/version, component key/label, and exact resulting `order_item_id` for each component. Source-order successors retain that provenance only while those exact item IDs and identities remain. Results link to the actual component order items.

Specimen requirements are aggregated from each underlying study's CAT03B rule and remain attached to the resulting items. There is no invented preset-wide specimen. All current preset components share a route; a future cross-route preset must be split into independent routing-group orders by ORD-COMP. Preset-expanded items enter the existing ORD-COMP validation and transaction, so a failed component or later write rolls back the batch. The existing batch UUID and child request hashes remain the retry authority. Future provider matching uses the resulting canonical items, without requiring the provider to advertise the MXMED preset label. Individual study matching remains unchanged.

## Arterial blood gas

The dormant gas rule fixes the specimen to `ARTERIAL_BLOOD`; venous material is rejected. Physician order context permits `ROOM_AIR`, `SUPPLEMENTAL_OXYGEN`, or `UNKNOWN`. Supplemental oxygen requires an allowed delivery device or FiO₂ percentage (strictly above 21 and at most 100), with both accepted when known. Room air and unknown cannot carry supplemental details. Unexpected keys and versions fail validation. The order snapshot stores this context and a Spanish print label. Exact collection time belongs to the specimen-collection runtime, since an order cannot know a future collection timestamp. Result observations and collection events remain separate from the physician's order context.

The portable order renders an issued true panel as one item, optionally followed by its **stored** component labels. It prints the actual individual studies for a preset and no fake preset line. An arterial gas order can print the stored oxygen summary. These additions do not reinterpret old snapshots.

## Deferrals and compatibility

The thyroid profile remains deferred: no generic thyroid panel or preset is introduced, and the current canonical catalog lacks `total_t4`. A distinct total-T4 identity and verified provider composition are prerequisites. The other P2 gaps, `tb_igra` and `amh_serum`, remain deferred to their own explicit contracts and activation review; this phase makes no new clinical equivalence claim.

The write model is extended additively: optional panel requests, preset applications, arterial oxygen context, and server snapshots. Ordinary orders without these fields keep their prior behavior. The clinical schema and order routing configuration did not change.
