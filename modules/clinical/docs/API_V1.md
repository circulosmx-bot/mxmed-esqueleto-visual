# API v1 (Contrato) - Módulo Clinical

## Propósito
Este documento define el contrato API v1 del módulo Clinical para:
- Pacientes
- Expediente (entradas clínicas)
- Consentimientos

Alcance:
- Documentación contractual únicamente.
- Sin implementación real de endpoints, UI o lógica de negocio.

Fuentes de verdad:
- `/docs/modulo_a_pacientes_expedientes_consentimientos_v1.md`
- `modules/clinical/db/schema_v1.sql`

## 1) Base path propuesto (solo contractual)
`/api/clinical/index.php`

## 2) Convención de respuesta estándar
Toda respuesta debe usar esta envoltura:

```json
{
  "ok": true,
  "error": null,
  "message": "",
  "data": {},
  "meta": {}
}
```

Definición:
- `ok`: `boolean`
- `error`: `string | null`
- `message`: `string`
- `data`: `any` (`object | array | null`)
- `meta`: `object | null`

### Ejemplo OK
```json
{
  "ok": true,
  "error": null,
  "message": "Operación exitosa",
  "data": {
    "patient_id": "p_01HZX4K7"
  },
  "meta": {
    "request_id": "req_abc123",
    "timestamp": "2026-02-11T10:30:00Z"
  }
}
```

### Ejemplo ERROR
```json
{
  "ok": false,
  "error": "invalid_params",
  "message": "patient_id es requerido",
  "data": null,
  "meta": {
    "request_id": "req_abc123",
    "timestamp": "2026-02-11T10:31:00Z"
  }
}
```

Errores típicos v1:
- `invalid_params`
- `not_found`
- `conflict`

## 3) Endpoints v1 (solo describir)

## PACIENTES

### POST /patients
Propósito:
- Crear un paciente en `clinical_patients`.

Request body (mínimo):
```json
{
  "full_name": "María Fernanda López",
  "birth_date": "1990-08-15",
  "sex": "female",
  "phone": "+525512345678",
  "email": "maria@example.com"
}
```

Response `data` (shape):
```json
{
  "patient_id": "p_01HZX4K7",
  "full_name": "María Fernanda López",
  "birth_date": "1990-08-15",
  "sex": "female",
  "phone": "+525512345678",
  "email": "maria@example.com",
  "status": "active",
  "created_at": "2026-02-11T10:30:00Z",
  "updated_at": "2026-02-11T10:30:00Z"
}
```

Errores típicos:
- `invalid_params` (faltan campos obligatorios o formato inválido)
- `conflict` (ID o dato único en conflicto, si aplica)

### GET /patients/{patient_id}
Propósito:
- Obtener un paciente por `patient_id`.

Request body (mínimo):
- No aplica.

Response `data` (shape):
```json
{
  "patient_id": "p_01HZX4K7",
  "full_name": "María Fernanda López",
  "birth_date": "1990-08-15",
  "sex": "female",
  "phone": "+525512345678",
  "email": "maria@example.com",
  "status": "active",
  "created_at": "2026-02-11T10:30:00Z",
  "updated_at": "2026-02-11T10:45:00Z"
}
```

Errores típicos:
- `invalid_params` (path inválido)
- `not_found` (patient_id inexistente)

### GET /patients?status=&page=&limit=
Propósito:
- Listar pacientes con paginado básico.

Request body (mínimo):
- No aplica.
- Query params:
  - `status` opcional: `active|archived`
  - `page` opcional: entero, default 1
  - `limit` opcional: entero, default 20

Response `data` (shape):
```json
[
  {
    "patient_id": "p_01HZX4K7",
    "full_name": "María Fernanda López",
    "status": "active",
    "updated_at": "2026-02-11T10:45:00Z"
  }
]
```

`meta` sugerida:
```json
{
  "pagination": {
    "page": 1,
    "limit": 20,
    "total": 1,
    "total_pages": 1
  }
}
```

Errores típicos:
- `invalid_params` (paginación o status inválido)

### PATCH /patients/{patient_id}
Propósito:
- Actualizar campos permitidos del paciente.

Request body (mínimo):
```json
{
  "full_name": "María F. López",
  "birth_date": "1990-08-15",
  "sex": "female",
  "phone": "+525512345679",
  "email": "mariaf@example.com"
}
```

Response `data` (shape):
```json
{
  "patient_id": "p_01HZX4K7",
  "full_name": "María F. López",
  "birth_date": "1990-08-15",
  "sex": "female",
  "phone": "+525512345679",
  "email": "mariaf@example.com",
  "status": "active",
  "created_at": "2026-02-11T10:30:00Z",
  "updated_at": "2026-02-11T11:00:00Z"
}
```

Errores típicos:
- `invalid_params`
- `not_found`
- `conflict`

### PATCH /patients/{patient_id}/status
Propósito:
- Cambiar estado lógico (`active|archived`) sin borrado físico.

Request body (mínimo):
```json
{
  "status": "archived"
}
```

Response `data` (shape):
```json
{
  "patient_id": "p_01HZX4K7",
  "status": "archived",
  "updated_at": "2026-02-11T11:05:00Z"
}
```

Errores típicos:
- `invalid_params`
- `not_found`
- `conflict` (transición de estado inválida)

## EXPEDIENTE

### POST /records
Propósito:
- Crear entrada de expediente en `clinical_record_entries` asociada a un paciente.

Request body (mínimo):
```json
{
  "patient_id": "p_01HZX4K7",
  "entry_date": "2026-02-11T10:45:00Z",
  "note_type": "evolucion",
  "subjective": "Dolor de cabeza desde ayer.",
  "objective": "TA 120/80, FC 72 lpm.",
  "assessment": "Cefalea tensional probable.",
  "plan": "Hidratación y analgésico PRN."
}
```

Response `data` (shape):
```json
{
  "entry_id": "e_01HZX5B2",
  "patient_id": "p_01HZX4K7",
  "entry_date": "2026-02-11T10:45:00Z",
  "note_type": "evolucion",
  "subjective": "Dolor de cabeza desde ayer.",
  "objective": "TA 120/80, FC 72 lpm.",
  "assessment": "Cefalea tensional probable.",
  "plan": "Hidratación y analgésico PRN.",
  "status": "active",
  "created_at": "2026-02-11T10:45:10Z",
  "updated_at": "2026-02-11T10:45:10Z"
}
```

Errores típicos:
- `invalid_params`
- `not_found` (patient_id inexistente)
- `conflict`

### GET /patients/{patient_id}/records?page=&limit=&status=
Propósito:
- Listar entradas de expediente por paciente.

Request body (mínimo):
- No aplica.
- Query params:
  - `page` opcional
  - `limit` opcional
  - `status` opcional: `active|amended|archived`

Response `data` (shape):
```json
[
  {
    "entry_id": "e_01HZX5B2",
    "patient_id": "p_01HZX4K7",
    "entry_date": "2026-02-11T10:45:00Z",
    "note_type": "evolucion",
    "status": "active",
    "updated_at": "2026-02-11T10:45:10Z"
  }
]
```

Errores típicos:
- `invalid_params`
- `not_found`

### GET /records/{entry_id}
Propósito:
- Obtener una entrada de expediente por `entry_id`.

Request body (mínimo):
- No aplica.

Response `data` (shape):
```json
{
  "entry_id": "e_01HZX5B2",
  "patient_id": "p_01HZX4K7",
  "entry_date": "2026-02-11T10:45:00Z",
  "note_type": "evolucion",
  "subjective": "Dolor de cabeza desde ayer.",
  "objective": "TA 120/80, FC 72 lpm.",
  "assessment": "Cefalea tensional probable.",
  "plan": "Hidratación y analgésico PRN.",
  "status": "active",
  "created_at": "2026-02-11T10:45:10Z",
  "updated_at": "2026-02-11T10:45:10Z"
}
```

Errores típicos:
- `invalid_params`
- `not_found`

### PATCH /records/{entry_id}
Propósito:
- Actualizar entrada de expediente existente.

Request body (mínimo):
```json
{
  "note_type": "seguimiento",
  "subjective": "Mejoría parcial.",
  "objective": "Sin datos de alarma.",
  "assessment": "Evolución favorable.",
  "plan": "Continuar manejo.",
  "status": "amended"
}
```

Response `data` (shape):
```json
{
  "entry_id": "e_01HZX5B2",
  "status": "amended",
  "updated_at": "2026-02-11T11:20:00Z"
}
```

Errores típicos:
- `invalid_params`
- `not_found`
- `conflict`

## CONSENTIMIENTOS

### POST /consents
Propósito:
- Crear consentimiento en `clinical_consents`.

Request body (mínimo):
```json
{
  "patient_id": "p_01HZX4K7",
  "consent_type": "tratamiento_general",
  "status": "active",
  "granted_at": "2026-02-11T09:00:00Z",
  "source": "presencial",
  "notes": "Consentimiento inicial."
}
```

Response `data` (shape):
```json
{
  "consent_id": "c_01HZX6Q9",
  "patient_id": "p_01HZX4K7",
  "consent_type": "tratamiento_general",
  "status": "active",
  "granted_at": "2026-02-11T09:00:00Z",
  "revoked_at": null,
  "voided_at": null,
  "source": "presencial",
  "notes": "Consentimiento inicial.",
  "created_at": "2026-02-11T09:00:10Z",
  "updated_at": "2026-02-11T09:00:10Z"
}
```

Errores típicos:
- `invalid_params`
- `not_found` (patient_id inexistente)
- `conflict`

### GET /patients/{patient_id}/consents?page=&limit=&status=
Propósito:
- Listar consentimientos por paciente.

Request body (mínimo):
- No aplica.
- Query params:
  - `page` opcional
  - `limit` opcional
  - `status` opcional: `active|revoked|void`

Response `data` (shape):
```json
[
  {
    "consent_id": "c_01HZX6Q9",
    "patient_id": "p_01HZX4K7",
    "consent_type": "tratamiento_general",
    "status": "active",
    "granted_at": "2026-02-11T09:00:00Z",
    "updated_at": "2026-02-11T09:00:10Z"
  }
]
```

Errores típicos:
- `invalid_params`
- `not_found`

### GET /consents/{consent_id}
Propósito:
- Obtener consentimiento por `consent_id`.

Request body (mínimo):
- No aplica.

Response `data` (shape):
```json
{
  "consent_id": "c_01HZX6Q9",
  "patient_id": "p_01HZX4K7",
  "consent_type": "tratamiento_general",
  "status": "active",
  "granted_at": "2026-02-11T09:00:00Z",
  "revoked_at": null,
  "voided_at": null,
  "source": "presencial",
  "notes": "Consentimiento inicial.",
  "created_at": "2026-02-11T09:00:10Z",
  "updated_at": "2026-02-11T09:00:10Z"
}
```

Errores típicos:
- `invalid_params`
- `not_found`

### PATCH /consents/{consent_id}/status
Propósito:
- Cambiar estado del consentimiento (`active|revoked|void`) respetando coherencia de fechas.

Request body (mínimo):
```json
{
  "status": "revoked",
  "revoked_at": "2026-02-20T12:00:00Z"
}
```

Response `data` (shape):
```json
{
  "consent_id": "c_01HZX6Q9",
  "status": "revoked",
  "revoked_at": "2026-02-20T12:00:00Z",
  "voided_at": null,
  "updated_at": "2026-02-20T12:00:05Z"
}
```

Errores típicos:
- `invalid_params` (fechas no coherentes con status)
- `not_found`
- `conflict`

## 4) Reglas de mapeo de datos (schema -> API)
- `clinical_patients` <-> recurso `patients`
  - Campos: `patient_id`, `full_name`, `birth_date`, `sex`, `phone`, `email`, `status`, `created_at`, `updated_at`.
- `clinical_record_entries` <-> recurso `records`
  - Campos: `entry_id`, `patient_id`, `entry_date`, `note_type`, `subjective`, `objective`, `assessment`, `plan`, `status`, `created_at`, `updated_at`.
- `clinical_consents` <-> recurso `consents`
  - Campos: `consent_id`, `patient_id`, `consent_type`, `status`, `granted_at`, `revoked_at`, `voided_at`, `source`, `notes`, `created_at`, `updated_at`.

## 5) FUTURO (fuera de alcance v1)
- Auth y roles granulares.
- Portal paciente/autoservicio.
- Adjuntos y firma digital avanzada.
- Automatizaciones, recordatorios e integraciones externas.
- Catálogo formal de `consent_type` y versionado legal.
- Auditoría extendida y trazabilidad avanzada.
