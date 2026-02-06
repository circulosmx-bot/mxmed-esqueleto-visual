# CONTRATO JSON CANÓNICO — PACIENTES (P-4)

Define el formato estable que usarán los endpoints del dominio Pacientes. Este dominio es administrativo: mantiene identidad, contacto y consentimientos. Lo clínico queda en `ehr_*`, las citas en `agenda_*`.

## Envoltura estándar
- `ok` (bool)
- `error` (string | null)
- `message` (string)
- `data` (object | array | null)
- `meta` (object)

Ejemplos:
```json
{ "ok": true, "error": null, "message": "", "data": { "patient_id": "p_abc123" }, "meta": {} }
```
```json
{ "ok": false, "error": "invalid_params", "message": "display_name required", "data": null, "meta": {} }
```

## Seguridad y privacidad

### Campos sensibles
- `phone`, `email`, `birthdate`, `notes_admin`, `consents`.

### Control canónico de visibilidad
`meta.visibility.contact = "none" | "masked" | "full"`
- `none`: no se expone ningún contacto.
- `masked`: solo aparecen `phone_masked` / `email_masked`.
- `full`: se muestran los valores completos.
`notes_admin` NUNCA sale fuera de contextos administrativos autorizados.
`consents` se expone como resumen vigente, no el historial completo.

## Objetos canónicos

### Patient
- `patient_id`, `display_name`, `status`, `sex?`, `birthdate?`, `contacts: []`, `links?`, `consent_summary`, `audit`.
  - `links` es un arreglo para reflejar múltiples vínculos médico↔paciente; en muchos contextos llega con un solo elemento.

Ejemplo (visibility.contact="masked"):
```json
{
  "patient_id": "p_abc123",
  "display_name": "Ana Pérez",
  "status": "active",
  "sex": "female",
  "contacts": [
    { "contact_id": "c_1", "type": "phone", "value_masked": "*******89", "is_primary": true }
  ],
  "links": [{ "doctor_id": "d_1", "link_status": "active" }],
  "consent_summary": { "current": { "consent_type": "contact_whatsapp", "consent_value": true }, "updated_at": "2026-02-01T12:00:00" },
  "audit": { "created_at": "2026-01-15T09:00:00", "updated_at": "2026-01-30T10:00:00" }
}
```

### Contact
- `contact_id`, `type`, `value` (solo full), `value_masked`, `is_primary`, `preferred_contact_method`, `created_at`.
```json
{ "contact_id": "c_1", "type": "email", "value_masked": "a***@mail.com", "is_primary": true, "preferred_contact_method": "email", "created_at": "2026-01-15T09:00:00" }
```

### Consent
- `consent_id`, `consent_type`, `consent_value` (boolean: true/false en JSON, internamente puede persistirse como 0/1), `version`, `consented_at`, `source`, `actor_id`.
```json
{ "consent_id": "con_1", "consent_type": "contact_whatsapp", "consent_value": true, "version": "2026-01", "consented_at": "2026-01-15T09:00:00", "source": "panel", "actor_id": "u_admin" }
```

### consent_summary
- `current` (resumen o null), `updated_at`.
```json
{ "current": { "consent_type": "contact_email", "consent_value": true }, "updated_at": "2026-01-15T09:00:00" }
```

## Errores permitidos
- `invalid_params`: payload mal formado.
- `not_found`: paciente inexistente.
- `forbidden`: acceso no autorizado.
- `db_error`: falla inesperada.
- `db_not_ready`: base no lista.

Ejemplo error:
```json
{ "ok": false, "error": "not_found", "message": "patient_id unknown", "data": null, "meta": {} }
```

## Endpoints propuestos (contrato)
- `POST /patients`: request con `display_name`, `contacts?`, `doctor_id?`; response `data=Patient`.
- `GET /patients/{patient_id}`: response `data=Patient`.
- `GET /doctors/{doctor_id}/patients`: response `data=[Patient,...]`.
- FUTURO: `PATCH /patients/{id}/contact`, `POST /patients/{id}/consents`.

## Meta estándar
- `meta.visibility.contact` controla contactos.
- `meta.qa_mode_seen` aparece si aplica (QA). 
```json
{ "visibility": { "contact": "masked" }, "qa_mode_seen": "ready" }
```
