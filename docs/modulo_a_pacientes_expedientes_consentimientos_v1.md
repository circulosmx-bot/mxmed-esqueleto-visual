# Módulo A v1: Pacientes / Expedientes / Consentimientos

## 1) Resumen del módulo v1
Este módulo define el contrato funcional y de datos mínimo para gestionar:
- Registro administrativo de pacientes.
- Entradas clínicas básicas por paciente (expediente).
- Registro de consentimientos del paciente.

Propósito v1:
- Unificar criterios de captura y lectura de información base.
- Establecer contratos JSON estándar para implementación futura.
- Asegurar privacidad por defecto y trazabilidad operativa sin borrado físico.

Alcance v1:
- Solo definición documental de entidades, campos, operaciones y reglas.
- Sin implementación de backend, base de datos, UI ni automatizaciones.

## 2) Entidades v1

### 2.1 Paciente
Entidad administrativa base de la persona atendida. Es la entidad raíz para expediente y consentimientos.

### 2.2 Expediente (entradas/notas clínicas mínimas)
Registro de notas clínicas mínimas asociadas a un paciente. Cada entrada es independiente y auditable.

### 2.3 Consentimiento
Registro de autorización/revocación asociada a un paciente para finalidades específicas.

## 3) Campos mínimos v1 por entidad
Regla global de privacidad v1:
- Todo campo de `Paciente`, `Expediente` y `Consentimiento` es `PRIVADO` por default.
- `PUBLICO` solo existe con justificación explícita; en esta versión v1 no se define ningún campo `PUBLICO`.

### 3.1 Campos mínimos — Paciente
| nombre_tecnico | label_humano | tipo | requerido | ejemplo | privacidad | notas |
|---|---|---|---|---|---|---|
| patient_id | ID de paciente | string | sí | p_01HZX4K7 | PRIVADO | Identificador único del sistema. |
| full_name | Nombre completo | string | sí | María Fernanda López | PRIVADO | Longitud sugerida: 3 a 120 caracteres. |
| birth_date | Fecha de nacimiento | date (YYYY-MM-DD) | no | 1990-08-15 | PRIVADO | Opcional en v1. |
| sex | Sexo | enum | no | female | PRIVADO | Valores permitidos: female, male, other, undisclosed. |
| phone | Teléfono | string | no | +525512345678 | PRIVADO | Opcional; formato E.164 recomendado. |
| email | Correo electrónico | string | no | maria@example.com | PRIVADO | Opcional; validar formato básico. |
| status | Estado del paciente | enum | sí | active | PRIVADO | Valores permitidos: active, archived. |
| created_at | Fecha de creación | datetime ISO 8601 | sí | 2026-02-11T10:30:00Z | INTERNO | Timestamp de sistema. |
| updated_at | Última actualización | datetime ISO 8601 | sí | 2026-02-11T11:00:00Z | INTERNO | Timestamp de sistema. |

### 3.2 Campos mínimos — Expediente (entrada clínica)
| nombre_tecnico | label_humano | tipo | requerido | ejemplo | privacidad | notas |
|---|---|---|---|---|---|---|
| entry_id | ID de entrada | string | sí | e_01HZX5B2 | PRIVADO | Identificador único del sistema. |
| patient_id | ID de paciente | string | sí | p_01HZX4K7 | PRIVADO | Relación obligatoria con Paciente. |
| entry_date | Fecha de nota | datetime ISO 8601 | sí | 2026-02-11T10:45:00Z | PRIVADO | Fecha clínica de registro. |
| note_type | Tipo de nota | enum | sí | evolucion | PRIVADO | Valores permitidos v1: evolucion, ingreso, seguimiento, alta, otro. |
| subjective | Subjetivo | string | no | Dolor de cabeza desde ayer. | PRIVADO | Texto clínico breve. |
| objective | Objetivo | string | no | TA 120/80, FC 72 lpm. | PRIVADO | Texto clínico breve. |
| assessment | Impresión diagnóstica | string | no | Cefalea tensional probable. | PRIVADO | Texto clínico breve. |
| plan | Plan terapéutico | string | no | Hidratación y analgésico PRN. | PRIVADO | Texto clínico breve. |
| status | Estado de la entrada | enum | sí | active | PRIVADO | Valores permitidos: active, amended, archived. |
| created_at | Fecha de creación | datetime ISO 8601 | sí | 2026-02-11T10:45:10Z | INTERNO | Timestamp de sistema. |
| updated_at | Última actualización | datetime ISO 8601 | sí | 2026-02-11T11:02:00Z | INTERNO | Timestamp de sistema. |

### 3.3 Campos mínimos — Consentimiento
| nombre_tecnico | label_humano | tipo | requerido | ejemplo | privacidad | notas |
|---|---|---|---|---|---|---|
| consent_id | ID de consentimiento | string | sí | c_01HZX6Q9 | PRIVADO | Identificador único del sistema. |
| patient_id | ID de paciente | string | sí | p_01HZX4K7 | PRIVADO | Relación obligatoria con Paciente. |
| consent_type | Tipo de consentimiento | enum | sí | tratamiento_general | PRIVADO | Catálogo v1 definido por operación clínica/legal. |
| status | Estado del consentimiento | enum | sí | active | PRIVADO | Valores permitidos: active, revoked, void. |
| granted_at | Fecha de otorgamiento | datetime ISO 8601 | sí | 2026-02-11T09:00:00Z | PRIVADO | Fecha/hora del acto de consentimiento. |
| revoked_at | Fecha de revocación | datetime ISO 8601 | no | 2026-02-20T12:00:00Z | PRIVADO | Requerido cuando `status=revoked`. |
| voided_at | Fecha de anulación | datetime ISO 8601 | no | 2026-02-21T08:00:00Z | PRIVADO | Requerido cuando `status=void`. |
| source | Origen del registro | enum | no | presencial | PRIVADO | Valores sugeridos: presencial, telefonico, portal, otro. |
| notes | Notas | string | no | Revocado por solicitud del paciente. | PRIVADO | Comentario breve opcional. |
| created_at | Fecha de creación | datetime ISO 8601 | sí | 2026-02-11T09:00:10Z | INTERNO | Timestamp de sistema. |
| updated_at | Última actualización | datetime ISO 8601 | sí | 2026-02-11T09:00:10Z | INTERNO | Timestamp de sistema. |

## 4) Contratos JSON estándar (formato global)
En todos los endpoints del módulo se debe responder con la misma envoltura:

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
- `ok`: `boolean`.
- `error`: `string | null` (código de error estable).
- `message`: `string` (mensaje legible).
- `data`: `any` (objeto, arreglo o `null`).
- `meta`: `object | null` (paginación, trazabilidad ligera u otros metadatos no sensibles).

### Ejemplo de respuesta OK
```json
{
  "ok": true,
  "error": null,
  "message": "Paciente creado",
  "data": {
    "patient_id": "p_01HZX4K7",
    "full_name": "María Fernanda López",
    "status": "active"
  },
  "meta": {
    "request_id": "req_7f8a",
    "timestamp": "2026-02-11T10:30:00Z"
  }
}
```

### Ejemplo de respuesta ERROR
```json
{
  "ok": false,
  "error": "invalid_params",
  "message": "patient_id es requerido",
  "data": null,
  "meta": {
    "request_id": "req_7f8a",
    "timestamp": "2026-02-11T10:31:00Z"
  }
}
```

Códigos de error recomendados v1:
- `invalid_params`
- `not_found`
- `forbidden`
- `conflict`
- `internal_error`

## 5) Operaciones v1 por entidad (solo describir, no implementar)
Las siguientes operaciones son contractuales para implementación futura.

### 5.1 Pacientes

#### POST create
- Propósito: crear paciente.
- Entrada mínima: `full_name`.
- Opcionales: `birth_date`, `sex`, `phone`, `email`.
- Salida: objeto `Paciente`.

#### GET get by id
- Propósito: consultar un paciente por `patient_id`.
- Salida: objeto `Paciente`.

#### GET list (paginado básico)
- Propósito: listar pacientes.
- Query sugerida: `page` (default 1), `limit` (default 20, max 100), `status` opcional.
- Salida: arreglo de `Paciente`.
- `meta` sugerida: `pagination: { page, limit, total, total_pages }`.

#### PATCH update
- Propósito: actualizar campos permitidos de paciente.
- Campos actualizables v1: `full_name`, `birth_date`, `sex`, `phone`, `email`.
- Salida: objeto `Paciente` actualizado.

#### PATCH archive/unarchive (status)
- Propósito: cambiar estado lógico sin borrar registro.
- Transiciones válidas: `active -> archived`, `archived -> active`.
- Salida: objeto `Paciente` con `status` actualizado.

### 5.2 Expedientes

#### POST create entry (ligado a patient_id)
- Propósito: crear entrada clínica para un paciente.
- Entrada mínima: `patient_id`, `entry_date`, `note_type`.
- Opcionales: `subjective`, `objective`, `assessment`, `plan`.
- Salida: objeto `Expediente` (entrada).

#### GET list entries by patient_id
- Propósito: listar entradas de expediente por `patient_id`.
- Query sugerida: `page`, `limit`, `status` opcional.
- Salida: arreglo de entradas de `Expediente`.

#### GET get entry by id
- Propósito: consultar entrada por `entry_id`.
- Salida: objeto `Expediente` (entrada).

#### PATCH update entry
- Propósito: actualizar entrada clínica existente.
- Campos actualizables v1: `note_type`, `subjective`, `objective`, `assessment`, `plan`, `status`.
- Salida: objeto `Expediente` actualizado.

### 5.3 Consentimientos

#### POST create record
- Propósito: crear registro de consentimiento.
- Entrada mínima: `patient_id`, `consent_type`, `status`, `granted_at`.
- Opcionales: `source`, `notes`.
- Salida: objeto `Consentimiento`.

#### GET list by patient_id
- Propósito: listar consentimientos por `patient_id`.
- Query sugerida: `page`, `limit`, `status` opcional.
- Salida: arreglo de `Consentimiento`.

#### GET get by id
- Propósito: consultar consentimiento por `consent_id`.
- Salida: objeto `Consentimiento`.

#### PATCH status (active/revoked/void)
- Propósito: cambiar estado del consentimiento.
- Reglas: 
  - `status=revoked` requiere `revoked_at`.
  - `status=void` requiere `voided_at`.
- Salida: objeto `Consentimiento` actualizado.

## 6) Reglas mínimas v1

### 6.1 Relaciones
- `patient_id` es obligatorio en toda entrada de `Expediente`.
- `patient_id` es obligatorio en todo `Consentimiento`.
- No se permite crear `Expediente` o `Consentimiento` para `patient_id` inexistente.

### 6.2 Estados permitidos
- Paciente: `active`, `archived`.
- Expediente (entrada): `active`, `amended`, `archived`.
- Consentimiento: `active`, `revoked`, `void`.

### 6.3 Validaciones mínimas
- `full_name`: 3 a 120 caracteres.
- `email`: opcional; si existe, validar formato básico `local@dominio`.
- `phone`: opcional; si existe, 8 a 20 caracteres, recomendado formato E.164.
- Fechas:
  - `birth_date` en formato `YYYY-MM-DD`.
  - `entry_date`, `granted_at`, `revoked_at`, `voided_at` en ISO 8601.
- Longitudes sugeridas de texto clínico (`subjective`, `objective`, `assessment`, `plan`, `notes`): 0 a 5000 caracteres.
- Reglas de coherencia de consentimiento:
  - Si `status=active`, `revoked_at` y `voided_at` deben ser `null`/ausentes.
  - Si `status=revoked`, `revoked_at` obligatorio y `voided_at` ausente.
  - Si `status=void`, `voided_at` obligatorio y `revoked_at` ausente.

### 6.4 No borrar (soft delete / status)
- No hay borrado físico en v1 para Paciente, Expediente o Consentimiento.
- Toda baja lógica se resuelve por `status`.
- El histórico se conserva para trazabilidad.

## 7) Fuera de alcance (FUTURO)
Todo lo siguiente queda explícitamente fuera de alcance de v1 y se considera `FUTURO`:
- Adjuntos de archivos (PDF, imagen, audio, video).
- Firma digital avanzada y sello legal.
- Automatizaciones y recordatorios.
- Integraciones con terceros (laboratorios, hospitales, aseguradoras, mensajería).
- OCR, IA o plantillas inteligentes.
- Flujos de aprobación complejos y motores de reglas avanzadas.
- Versionado clínico avanzado (diff estructural de notas).
- Reporting analítico y tableros ejecutivos.
- Notificaciones omnicanal.
- Portal paciente y autoservicio.

---

Este documento es el contrato maestro v1 para implementación futura del Módulo A. No implica implementación técnica en esta fase.
