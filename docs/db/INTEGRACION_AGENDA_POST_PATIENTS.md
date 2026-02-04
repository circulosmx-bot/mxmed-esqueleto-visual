# INTEGRACIÓN — AGENDA → POST /patients (P-10)

## 1) Propósito
Documentar el flujo en el que Agenda, al crear una cita, auto-crea un paciente a través del dominio Pacientes cuando no se proporciona un `patient_id`, y cómo se propagan los errores resultantes.

## 2) Principio operativo
- Si **no** viene `patient_id` en el payload de `POST /appointments`:
  - Agenda intenta construir `patientInput` desde `payload.patient` (preferido).
  - Si `payload.patient` no existe, usa fallback con campos en el nivel raíz: `display_name`, `sex`, `birthdate`, `contacts`.
  - Si `patientInput.display_name` existe y no está vacío → Agenda intenta crear paciente vía `POST /patients`.
  - Si el payload de cita incluye `doctor_id` y `patientInput` no lo trae → Agenda lo inyecta en `patientInput`.
- Si viene `patient_id`, Agenda **no** crea paciente.

## 3) Payload preferido (recomendado) para POST /appointments
Campos mínimos para permitir auto-creación: `doctor_id`, `consultorio_id`, `start_at`, `end_at`, `modality`, `channel_origin`, `created_by_role`, `created_by_id`, y `patient { display_name, contacts[] }`.
`start_at`/`end_at` aceptan formatos `"YYYY-MM-DD HH:MM:SS"` y `"YYYY-MM-DDTHH:MM:SS"`.

Ejemplo 1 (con `T`):
```json
{
  "doctor_id": "d_1",
  "consultorio_id": "1",
  "start_at": "2026-02-04T10:00:00",
  "end_at": "2026-02-04T10:30:00",
  "modality": "presencial",
  "channel_origin": "qa_script",
  "created_by_role": "system",
  "created_by_id": "qa",
  "patient": {
    "display_name": "Ana Pérez",
    "contacts": [
      { "type": "phone", "value": "+5215512345678", "is_primary": true }
    ]
  }
}
```

Ejemplo 2 (con espacio):
```json
{
  "doctor_id": "d_1",
  "consultorio_id": "1",
  "start_at": "2026-02-04 10:00:00",
  "end_at": "2026-02-04 10:30:00",
  "modality": "presencial",
  "channel_origin": "qa_script",
  "created_by_role": "system",
  "created_by_id": "qa",
  "patient": {
    "display_name": "Luis Gómez",
    "contacts": [
      { "type": "email", "value": "luis@example.com" }
    ]
  }
}
```

## 4) Fallback permitido (compatibilidad)
Si no viene `patient{}`, Agenda puede leer campos en el nivel raíz (`display_name`, `sex`, `birthdate`, `contacts`).
```json
{
  "doctor_id": "d_1",
  "consultorio_id": "1",
  "start_at": "2026-02-04 11:00:00",
  "end_at": "2026-02-04 11:30:00",
  "modality": "presencial",
  "channel_origin": "qa_script",
  "created_by_role": "system",
  "created_by_id": "qa",
  "display_name": "Paciente Fallback",
  "contacts": [
    { "type": "phone", "value": "+5215511111111" }
  ]
}
```

## 5) Cómo se llama a Pacientes (conceptual)
- Agenda arma `patientInput` y lo envía internamente a `POST /patients`.
- Pacientes responde con `patient_id` y `meta.visibility.contact="masked"`.
- Agenda inyecta `patient_id` y continúa el flujo normal de creación de cita.

Ejemplo request mínimo a `POST /patients`:
```json
{
  "display_name": "Ana Pérez",
  "contacts": [
    { "type": "phone", "value": "+5215512345678" }
  ],
  "doctor_id": "d_1"
}
```

Ejemplo response OK de `POST /patients`:
```json
{
  "ok": true,
  "error": null,
  "message": "",
  "data": {
    "patient_id": "p_a1b2c3d4e5f6",
    "display_name": "Ana Pérez",
    "status": "active",
    "contacts": [
      { "contact_id": "c_abc123", "type": "phone", "value_masked": "+52155******78", "is_primary": true }
    ],
    "links": [
      { "doctor_id": "d_1", "link_status": "active" }
    ],
    "audit": { "created_at": "2026-02-01 10:00:00", "updated_at": null }
  },
  "meta": {
    "visibility": { "contact": "masked" }
  }
}
```

## 6) Propagación de errores
- Si `POST /patients` responde `ok:false`:
  - Agenda detiene la creación de la cita.
  - Propaga `error` y `message` tal cual.
  - Conserva `meta.visibility.contact="masked"` si viene desde Pacientes.

Ejemplo error `invalid_params`:
```json
{
  "ok": false,
  "error": "invalid_params",
  "message": "invalid params",
  "data": null,
  "meta": {
    "visibility": { "contact": "masked" },
    "fields": { "display_name": "required" }
  }
}
```

Ejemplo error `db_not_ready`:
```json
{
  "ok": false,
  "error": "db_not_ready",
  "message": "patients db not ready",
  "data": null,
  "meta": {
    "visibility": { "contact": "masked" }
  }
}
```

## 7) Notas de seguridad / privacidad
- Las respuestas en este flujo siempre van con contactos en modo `masked`.
- Agenda no debe solicitar ni persistir contactos en modo `full`.
- Agenda no almacena PII adicional; solo el `patient_id` y el snapshot mínimo necesario para la cita.
