# TIMELINE V1 — CONTRATO TÉCNICO (Paciente) — MXMed Clinical + Agenda + (Billing/Fiscal preparado)

## 0) Propósito
El **Timeline Clínico Unificado** es una **vista (read model)** que mezcla eventos de múltiples dominios conectados al paciente:

- **Clinical** (documentos clínicos)
- **Agenda** (citas pasadas y futuras)
- **Billing/Fiscal** (cargos/pagos/recibos/CFDI) — *preparado desde contrato, implementación posterior*

Principio: **Mínimo cambio, máxima solidez**
- No duplicar datos.
- No guardar clínico variable en Agenda.
- Timeline no persiste: solo consulta, agrupa y responde.

---

## 1) Definiciones

### 1.1 Paciente (canonical)
El timeline opera sobre `patient_id` canónico (Identity Bridge ya existente).

### 1.2 Documento clínico
Fuente de verdad clínica: `clinical_documents` (tabla consolidada).  
Campos clave para timeline:
- `patient_id`
- `appointment_id` (nullable)
- `event_datetime` (derivado de `captured_at` cuando aplique)
- `document_uuid`, `document_type`, `summary`

### 1.3 Encuentro (Encounter) — Entidad conceptual (v1)
**Encounter** = unidad clínica agrupadora alrededor de un acto de atención, con tiempo clínico real.

En v1 el Encounter es **virtual** (no requiere tabla nueva). Se construye por reglas de agrupación.

#### EncounterKey (clave estable)
- Si hay cita: `encounter_key = "appt:{appointment_id}"`
- Si NO hay cita (legacy/externo): `encounter_key = "dt:{YYYYMMDDHHmm}:{bucket}"` (bucket temporal)

---

## 2) Endpoint

### 2.1 Ruta
`GET /api/clinical/index.php/patients/{patient_id}/timeline`

### 2.2 Query params
- `limit` (int, default 30, max 100)
- `direction` (`backward` | `forward`, default `backward`)
- `cursor` (string opaco, optional)
- `include` (csv optional)
  - valores: `agenda`, `clinical`, `billing`
  - default recomendado: `agenda,clinical`
  - `billing` puede existir en contrato aun si no hay implementación
- `from` (datetime optional, ISO-like `YYYY-MM-DD HH:MM:SS`) — *solo si no hay cursor*
- `to` (datetime optional) — *solo si no hay cursor*
- `types` (csv optional) filtro de items:
  - `encounter`, `document`, `billing` (y futuro `hospital_stay`)
- `doc_types` (csv optional) filtro para clinical docs (ej `vitals,note,prescription,...`) cuando `include` contiene `clinical`

Notas:
- **Si `cursor` viene**, `from/to` se ignoran.
- Cursor = paginación preferida para histórico masivo.

---

## 3) Orden, paginación y cursor

### 3.1 Orden
Cada item tiene:
- `sort_datetime` (datetime) — base del orden
- `sort_key` (string) — desempate estable (ej. `document_uuid`, `appointment_id`, `invoice_id`)

Orden final:
- `sort_datetime` DESC (backward) o ASC (forward)
- desempate por `sort_key`

### 3.2 Cursor opaco
El cursor debe encapsular como mínimo:
- `direction`
- `last_sort_datetime`
- `last_sort_key`

El cursor es **opaco para el cliente**; solo se reenvía.

### 3.3 Respuesta de paginación
- `cursor_next`: cursor para la siguiente página (según direction)
- `cursor_prev`: cursor para página inversa (opcional si se implementa)

---

## 4) Estructura de respuesta

### 4.1 Respuesta base
```json
{
  "ok": true,
  "error": null,
  "message": "",
  "data": {
    "patient_id": "p_...",
    "range": {
      "mode": "cursor",
      "limit": 30,
      "direction": "backward",
      "cursor_next": "opaque",
      "cursor_prev": null
    },
    "items": []
  }
}
```

### 4.2 Item común (shape mínimo)
Se mantiene el shape mínimo común de item para orden y render de timeline, sin cambios de contrato en esta revisión.

---

## 5) Tipos de item
Se mantiene el contrato v1 para tipos de item (`encounter`, `document`, `billing`) sin cambios en reglas o enums.

---

## 6) Reglas de composición
Se mantienen las reglas de composición/agregación de timeline v1 sin cambios técnicos en esta revisión.

---

## 7) Errores y validaciones
Se mantienen las validaciones y respuestas de error del contrato v1 sin cambios técnicos en esta revisión.

---

## 8) Seguridad y performance
Se mantienen lineamientos de seguridad, paginación y performance del contrato v1 sin cambios técnicos en esta revisión.

---

## 9) QA mínimo
Se mantiene el alcance de QA/curl de contrato v1 sin cambios técnicos en esta revisión.

---

## 10) Próximos pasos
- Scaffold del endpoint timeline (read model) con contrato base.
- Activar `include=agenda` para citas pasadas/futuras.
- Activar `include=clinical` para documentos clínicos consolidados.
- Validar paginación por cursor (`cursor_next`/`cursor_prev`) con curl.
- Verificar orden estable por `sort_datetime` + `sort_key`.
- Documentar filtros (`types`, `doc_types`, `from/to`) para QA funcional.
- Dejar `include=billing` preparado por contrato (feature-flag/placeholder).
- Incorporar dominio billing/fiscal en implementación posterior sin romper v1.
