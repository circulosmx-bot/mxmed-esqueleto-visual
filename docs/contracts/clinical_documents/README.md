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
