# Clinical documents contracts

## Versioning
- v1
- `contract_version` integer in payload (default 1)

## Supported documents
- `nota_evolucion`
- `nota_evolucion_hosp`
- `hoja_indicaciones`

## Contract shape
Each clinical document stored in DB uses:
- `context`: identifiers and care setting (stable)
- `payload`: clinical content (versioned by this contract)
- `snapshot`: audit/display only

Recommended top-level fields inside `payload`:
- `section_id`
- `standard`
- `ambito`
- `...` (document-specific)

## Rules
- Do not remove or rename existing fields.
- Add new fields as optional only (must tolerate missing or null).
- Prefer additive changes: new objects/arrays over changing meaning of old fields.
- `snapshot` is for audit/display only, not for clinical logic or validations.
- Dates/times must be ISO 8601 when stored in payload/snapshot.

## Storage mapping
In `clinical_documents`:
- `payload_json` = full payload (this contract)
- `rendered_text` = human-readable legal/printable text derived from payload
- `summary` = short UX label derived from payload (timeline)

## Notes
- When calling list endpoints, always URL-encode `patient_id` (use `encodeURIComponent`) because it may contain `|`.

## OR02B REL01 — exact order version in Orders/Results

`clinical_documents.payload_json` retains the submitted exact order reference (`related_order_document_id` or UUID) and `related_order_item_ids`. The result writer validates item IDs against that exact order version; no study-name or cross-version inference is permitted. `clinical_document_revisions` identifies the lineage head separately.

The OR02B projection keeps `related_order_document_id` as the **display lineage head** for existing OR02C consumers. It adds immutable-source fields on each result: `result_source_order_document_id`, `result_source_order_document_uuid`, `result_source_order_version`, plus `order_lineage_head_document_id`, `order_lineage_head_document_uuid`, `order_lineage_head_version`, and `result_order_relationship` (`DIRECT_CURRENT_VERSION` or `PREDECESSOR_VERSION`; unresolved references remain explicit). `related_order_item_ids` remain the exact persisted source item IDs.

The logical order list still collapses successors to the latest head. Each entry in the head's `versions` array now includes its own `order_items` and coverage. A result contributes coverage only to its exact source version; predecessor results remain visible under the lineage head but do not cover successor items. This is an additive read contract. The physician UI has not migrated to the new fields yet.

Current canonical writers reject replacing an order that already has linked results. The encounter and patient result paths also require a current, nonvoided source when accepting a new result. REL01 does not relax those write rules; historical and successor cases in its disposable read-model test exercise the projection of already persisted records.
