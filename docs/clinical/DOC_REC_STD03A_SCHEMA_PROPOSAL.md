# DOC-REC-STD03A schema proposal

Status: prepared and rehearsed; not authorized for normal-local application.

The existing `consultorios` aggregate and `PUT /api/agenda/index.php/consultorios` remain the canonical administration surface. The proposed additive fields are:

- `document_is_primary`: explicit primary decision, default `0`.
- `document_display_order`: explicit positive order, default `NULL`.
- `document_publication_enabled`: explicit document publication opt-in, default `0`.
- `document_primary_slot`: generated internal key used to enforce at most one primary per physician.

Database constraints enforce boolean values, positive ordering, one primary per physician and unique configured order per physician. Legacy rows remain private, unordered and non-primary.

After approval, the existing consultorio writer should accept these three administrative values. A primary change must lock every consultorio row for the effective physician in `consultorio_id` order, clear the prior primary and select the requested location in one transaction. The existing Identity-derived owner/administrator scope must continue to reject cross-physician writes.

The current private Agenda policy permits consultorio writes only for `owner` and `administrator`. The normal-local Director membership for doctor 1 is currently `collaborator`, so it can read this configuration but cannot save it. Implementation must preserve that fail-closed policy; Director Safari mutation QA will require an already-authorized owner/administrator account or a separately approved membership-policy decision.

The document resolver should select only rows with `document_publication_enabled=1`, place an explicitly configured primary first, then sort authorized secondaries by `document_display_order`, and limit the result to three. It may render `telefonos_json` because those fields are labeled and managed as regular professional consultorio phones. It must exclude `whatsapp` and `urgencias_json`. A location without an explicit publication opt-in contributes no name, address or phone to documents.

Future Prescription snapshots must freeze the selected location name, full address and eligible regular phones. The signature binding must gain a backward-compatible new projection version before footer fields participate in signed output. Existing V1 bindings and issued snapshots remain unchanged.

The lower-right footer area remains structurally reserved for DOC-REC-STD03B. No QR or empty placeholder is emitted in STD03A. Once a canonical public route and public origin are enabled, its visible caption must be exactly `AGENDA UNA CITA`; `Perfil público` is not an approved document caption.

Rollback is safe before application code writes configuration. Once settings are in use, export the three configured values before running the rollback because dropping the columns intentionally removes those decisions. The rollback does not modify existing address, contact, Agenda, schedule or appointment fields.

