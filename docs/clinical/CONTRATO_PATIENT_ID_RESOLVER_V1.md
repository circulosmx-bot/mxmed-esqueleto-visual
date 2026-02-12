# CONTRATO PATIENT ID RESOLVER V1

## 1) Propósito
Definir el contrato documental v1 para resolver identidad de paciente en transición `legacy -> canonical`, alineado a:

- `docs/clinical/DECISION_IDENTITY_BRIDGE_PATIENT_ID.md` (vinculante).
- Dominio canónico de pacientes: `modules/patients` (`patients_patients.patient_id`).
- Wrapper estándar de respuestas: `{ ok, error, message, data, meta }`.

Este contrato es solo documental. No implica implementación en este paso.

## 2) Tipos de entrada

### 2.1 `canonical_patient_id`
- Formato esperado: string con prefijo `p_...`.
- Fuente canónica: `patients_patients.patient_id`.

### 2.2 `legacy_patient_key`
- Forma legacy histórica: `nombre|YYYY-MM-DD|sexo`.
- Puede llegar normalizada desde UI.
- Regla de seguridad: **NO guardar crudo** el legacy key; solo hash (`sha256`) si se requiere trazabilidad.

## 3) Endpoint propuesto (documental)
`POST /api/clinical/index.php/patient-id/resolve`

- Naturaleza: contractual/documental.
- Estado: no implementado en este paso.

## 4) Request JSON

## 4.1 Body
```json
{
  "patient_ref": "string",
  "patient_min": {
    "display_name": "string",
    "birthdate": "YYYY-MM-DD",
    "sex": "female|male|other|undisclosed",
    "phone": "string",
    "email": "string"
  },
  "actor": {
    "doctor_id": "string",
    "user_id": "string",
    "role": "doctor|operator|system"
  }
}
```

## 4.2 Reglas de request
- `patient_ref`: requerido.
- `patient_min`: opcional.
- `patient_min.display_name`: requerido **si `patient_ref` es legacy** y se necesita `create minimal patient`.
- `birthdate`, `sex`, `phone`, `email`: opcionales.
- `actor`: opcional (contexto de auditoría).

## 5) Response JSON (wrapper estándar)

## 5.1 Shape
```json
{
  "ok": true,
  "error": null,
  "message": "",
  "data": {
    "patient_id": "p_abc123",
    "resolution_mode": "already_canonical",
    "bridge": {
      "input_type": "canonical_patient_id",
      "legacy_hash": null
    },
    "created_patient": false
  },
  "meta": {
    "request_id": "req_01HXYZ",
    "timestamp": "2026-02-12T16:00:00Z"
  }
}
```

## 5.2 Campos de `data`
- `patient_id`: `canonical_patient_id` resuelto.
- `resolution_mode`: `already_canonical | mapped_from_legacy | created_minimal_patient`.
- `bridge.input_type`: `canonical_patient_id | legacy_patient_key`.
- `bridge.legacy_hash`: hash `sha256` del legacy key cuando aplique; `null` en entrada canonical.
- `created_patient` (opcional): `true|false` cuando se ejecutó o no creación mínima.

## 6) Errores v1
- `invalid_params`
- `not_found`
- `conflict`
- `forbidden`

Criterios:
- `invalid_params`: payload inválido, `patient_ref` vacío, formato inválido, o falta `patient_min.display_name` cuando se requiere crear mínimo desde legacy.
- `not_found`: `patient_ref` es canonical y no existe en `patients_patients`.
- `conflict`: legado ambiguo contra múltiples candidatos canónicos sin criterio de desambiguación suficiente.
  - Nota v1: se prefiere `no match -> create minimal patient`; `conflict` se reserva para ambigüedad real que pueda provocar duplicidad no controlada.
- `forbidden`: actor no autorizado a resolver/crear identidad.

## 7) Reglas v1
1. Si input es canonical y existe -> retorna el mismo `patient_id`.
2. Si input es canonical y NO existe -> error `not_found`.
3. Si input es legacy -> **SIEMPRE** retorna un `canonical_patient_id`:
   - mapea a existente, o
   - crea paciente mínimo si no hay mapping confiable.
4. Nunca persistir `legacy_patient_key` crudo.
5. Si se requiere trazabilidad de legado, persistir solo `sha256(legacy_patient_key_normalized)`.

## 8) Ejemplos

## 8.1 Canonical OK
Request:
```json
{
  "patient_ref": "p_9f3ab21c"
}
```

Response:
```json
{
  "ok": true,
  "error": null,
  "message": "patient resolved",
  "data": {
    "patient_id": "p_9f3ab21c",
    "resolution_mode": "already_canonical",
    "bridge": {
      "input_type": "canonical_patient_id",
      "legacy_hash": null
    },
    "created_patient": false
  },
  "meta": {
    "request_id": "req_001",
    "timestamp": "2026-02-12T16:10:00Z"
  }
}
```

## 8.2 Legacy OK (`created_minimal_patient`)
Request:
```json
{
  "patient_ref": "ana perez|1990-01-20|F",
  "patient_min": {
    "display_name": "Ana Perez",
    "birthdate": "1990-01-20",
    "sex": "female",
    "phone": "+525512345678",
    "email": "ana@example.com"
  },
  "actor": {
    "doctor_id": "d_01",
    "user_id": "u_01",
    "role": "doctor"
  }
}
```

Response:
```json
{
  "ok": true,
  "error": null,
  "message": "patient created and resolved",
  "data": {
    "patient_id": "p_c2d4e6f8",
    "resolution_mode": "created_minimal_patient",
    "bridge": {
      "input_type": "legacy_patient_key",
      "legacy_hash": "6a3f5e7d2c9b4a1f8e0d4b6c7a9e2f1d3c5b7a9d1e3f5b7a9c1d3e5f7a9b1c3"
    },
    "created_patient": true
  },
  "meta": {
    "request_id": "req_002",
    "timestamp": "2026-02-12T16:11:00Z"
  }
}
```

## 8.3 Error `invalid_params`
Response:
```json
{
  "ok": false,
  "error": "invalid_params",
  "message": "patient_ref is required",
  "data": null,
  "meta": {
    "request_id": "req_003",
    "timestamp": "2026-02-12T16:12:00Z"
  }
}
```

## 8.4 Error `not_found`
Response:
```json
{
  "ok": false,
  "error": "not_found",
  "message": "canonical patient_id not found",
  "data": null,
  "meta": {
    "request_id": "req_004",
    "timestamp": "2026-02-12T16:13:00Z"
  }
}
```

## 9) Fuera de alcance (FUTURO)
- Deduplicación probabilística avanzada de identidad.
- Tabla de mapping avanzada (`legacy -> canonical`) con reglas de scoring/versionado.
- Migración/backfill batch de históricos.

---

Base canónica y vinculante para este contrato:
- `docs/clinical/DECISION_IDENTITY_BRIDGE_PATIENT_ID.md`
- `modules/patients/db/ready_schema.sql`
