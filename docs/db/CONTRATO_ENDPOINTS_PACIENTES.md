# CONTRATO JSON — ENDPOINTS PACIENTES (P-5)

Este documento fija el request y response que deberá cumplir cada endpoint del dominio Pacientes sin entrar en implementación. Se apoya en el contrato general (P-4) y le agrega reglas concretas por ruta.

## Convenciones generales
- Envoltura estándar: `{ ok, error, message, data, meta }`.
- IDs: `patient_id`, `contact_id`, `doctor_id` como strings estilo `"p_abc123"`, `"d_1"`.
- Fechas: ISO 8601 (`YYYY-MM-DD` o `YYYY-MM-DDTHH:MM:SS`).
- Privacidad: `meta.visibility.contact = none|masked|full` determina qué contactos luce el cliente.
- Nota: `notes_admin` nunca se acepta desde el cliente; lo escribe el sistema o roles administrativos.

## Endpoint 1 — POST /patients
### Propósito
Crear paciente administrativo y, opcionalmente, registrar vínculo básico con un doctor.

### Request (JSON body)
- Obligatorio: `display_name` (string).
- `display_name` no solo debe existir: se normaliza y valida como nombre de paciente.
  - Normalización: `trim`, colapso de espacios internos, sin autocapitalizar, sin forzar mayúsculas/minúsculas, preservando acentos, `ñ`, guiones y apóstrofes.
  - Se bloquean nombres genéricos simples o compuestos, dígitos y símbolos fuera de letras Unicode, espacios, guion y apóstrofe.
- Opcional: `sex`, `birthdate` (`YYYY-MM-DD`), `contacts` (ary de `ContactInput`), `doctor_id`.
- Prohibido: `notes_admin`, `consent_id`, `audit`, `links`, `patient_id` (el sistema asigna el ID).

ContactInput:
```json
{
  "type": "phone",
  "value": "+5215512345678",
  "is_primary": true,
  "preferred_contact_method": "whatsapp"
}
```

### Response OK
- `data`: objeto `Patient` resumido (ver P-4) con `meta.visibility.contact` recomendado `"masked"`.
```json
{
  "ok": true,
  "error": null,
  "message": "",
  "data": {
    "patient_id": "p_abc123",
    "display_name": "Ana Pérez"
  },
  "meta": {
    "visibility": { "contact": "masked" }
  }
}
```

### Errores esperados
- `invalid_params` (display_name faltante/inválido o contactos mal formados).
- `forbidden` (sin permiso para crear).
- `db_not_ready`, `db_error`.
```json
{
  "ok": false,
  "error": "invalid_params",
  "message": "Captura un nombre de paciente válido.",
  "data": null,
  "meta": {
    "fields": { "display_name": "invalid_name" }
  }
}
```

Cuando la falla corresponde a nombre inválido, el backend responde HTTP `422` y no persiste el paciente.

Ejemplos inválidos:
- `Paciente`, `Paciente Demo`, `Paciente nuevo`, `Paciente rápido`
- `Sin nombre`, `Sin nombre Prueba`, `Sin nombre registrado`, `Sin nombre registrado Demo`
- `No registrado`, `No registrado Demo`, `No especificado`, `No especificado Demo`
- `Prueba`, `Prueba Demo`, `Test`, `Test Demo`
- `xxx`, `xxx Demo`, `abc`, `abc Demo`, `Juan123`, `12345`, `@@@@`

Ejemplos válidos:
- `María José`, `José Luis`, `Ana Sofía`, `María del Carmen`, `Juan Pablo`
- `Ana`, `Luz`, `Ian`
- `De la Torre`, `del Río`, `San Martín`
- `García-López`, `O'Connor`, `Álvarez`, `Núñez`, `Muñoz`, `Peña`
- `Jean Pierre`, `María-José`, `María del Carmen del Río`
- `María-José García-López O'Connor`, `Ana De la Torre Núñez`, `José Luis Álvarez`, `Núñez Muñoz Peña`

## Endpoint 2 — POST /patients/{patient_id}/profile
### Propósito
Actualizar el perfil administrativo del paciente.

### Request
- Path: `patient_id`.
- Campos de nombre validados si vienen presentes:
  - `first_name`
  - `paternal_last_name`
  - `maternal_last_name`
- `maternal_last_name` puede enviarse vacío.
- Campos no relacionados, como ocupación o estado civil, no forman parte de esta validación de nombres.

### Validación de nombres
- Los valores presentes se normalizan con la misma política de `display_name`.
- Se bloquean genéricos, dígitos y símbolos inválidos.
- Se preservan acentos, `ñ`, guiones y apóstrofes.

### Error por nombre inválido
```json
{
  "ok": false,
  "error": "invalid_params",
  "message": "Captura un nombre de paciente válido.",
  "data": null,
  "meta": {
    "fields": { "first_name": "invalid_name" }
  }
}
```

Cuando la falla corresponde a nombre inválido, el backend responde HTTP `422` y no persiste el perfil.

## Endpoint 3 — GET /patients/{patient_id}
### Propósito
Leer la ficha administrativa existente.

### Request
- Path: `patient_id`.
- Query (opcional): `visibility_contact=none|masked|full` (solo si el caller tiene permiso; si no, se degrada a `masked` o `none`).

### Response OK
- `data`: `Patient` resumen con `meta.visibility.contact` reflejando el nivel real.
```json
{
  "ok": true,
  "error": null,
  "message": "",
  "data": {
    "patient_id": "p_abc123",
    "display_name": "Ana Pérez"
  },
  "meta": {
    "visibility": { "contact": "masked" }
  }
}
```

### Errores esperados
- `not_found`, `forbidden`, `db_not_ready`, `db_error`.
```json
{
  "ok": false,
  "error": "not_found",
  "message": "patient_id unknown",
  "data": null,
  "meta": {}
}
```

## Endpoint 4 — GET /doctors/{doctor_id}/patients
### Propósito
Listar los pacientes activos vinculados a un doctor.

### Request
- Path: `doctor_id`.
- Query: `limit` (int, default 50, max 200), `visibility_contact`, `cursor` (opcional/futuro para paginar).

### Response OK
- `data`: array de `Patient` resumidos.
- `meta`: `visibility.contact`, `paging: { limit, next_cursor? }` (el `next_cursor` es opcional y puede omitirse si no hay más páginas).
```json
{
  "ok": true,
  "error": null,
  "message": "",
  "data": [
    { "patient_id": "p_abc123", "display_name": "Ana Pérez" },
    { "patient_id": "p_xyz789", "display_name": "Luis Gómez" }
  ],
  "meta": {
    "visibility": { "contact": "masked" },
    "paging": { "limit": 50 }
  }
}
```

### Errores esperados
- `invalid_params` (limit fuera de rango), `forbidden`, `db_not_ready`, `db_error`.

## Reglas de degradación de visibilidad
- Si se solicita `full` sin permisos, se devuelve `masked` o `none` y se refleja en `meta.visibility.contact`.
- Nunca se expone `notes_admin`.
- `birthdate` puede devolverse `null` si la visibilidad no lo permite.

## Futuro
- NOTA: `PATCH /patients/{id}/contact` y `POST /patients/{id}/consents` son operaciones futuras; solo se documentan en P-4/P-5, sin implementación por ahora.
