# FASE IV — Flujos de escritura (Agenda v1)
**Fecha:** 2026-02-06  
**Alcance:** especificación técnica mínima de flujos write (crear / reagendar / cancelar / no_show).  
**Estado:** Documento operativo (sin UI).

## 1) Endpoints existentes

### 1.1 POST /api/agenda/index.php/appointments
**Propósito:** crear cita.  
**Payload mínimo (ejemplo):**
```json
{
  "doctor_id": "1",
  "consultorio_id": "1",
  "patient_id": "p_demo",
  "start_at": "2026-02-03 09:00:00",
  "end_at": "2026-02-03 09:30:00",
  "slot_minutes": 30,
  "modality": "presencial",
  "channel_origin": "panel",
  "created_by_role": "operator",
  "created_by_id": "op_1"
}
```
**Response OK (resumen):**
```json
{
  "ok": true,
  "error": null,
  "message": "",
  "data": {
    "appointment_id": "a_123",
    "status": "created",
    "start_at": "2026-02-03 09:00:00",
    "end_at": "2026-02-03 09:30:00",
    "doctor_id": "1",
    "consultorio_id": "1",
    "patient_id": "p_demo",
    "created_at": "2026-02-06 00:00:00"
  },
  "meta": {
    "write": "create",
    "events_appended": 1
  }
}
```

### 1.2 PATCH /api/agenda/index.php/appointments/{id}/reschedule
**Propósito:** reagendar una cita existente.  
**Payload mínimo (ejemplo):**
```json
{
  "motivo_code": "reagenda_paciente",
  "motivo_text": "Cambia de horario",
  "from_start_at": "2026-02-03 09:00:00",
  "from_end_at": "2026-02-03 09:30:00",
  "to_start_at": "2026-02-03 11:00:00",
  "to_end_at": "2026-02-03 11:30:00",
  "slot_minutes": 30,
  "channel_origin": "panel",
  "actor_role": "operator",
  "actor_id": "op_1"
}
```
**Response OK (resumen):**
```json
{
  "ok": true,
  "error": null,
  "message": "",
  "data": {
    "appointment_id": "a_123",
    "status": "rescheduled",
    "from_start_at": "2026-02-03 09:00:00",
    "from_end_at": "2026-02-03 09:30:00",
    "to_start_at": "2026-02-03 11:00:00",
    "to_end_at": "2026-02-03 11:30:00",
    "motivo_code": "reagenda_paciente",
    "motivo_text": "Cambia de horario"
  },
  "meta": {
    "write": "reschedule",
    "events_appended": 1
  }
}
```

### 1.3 POST /api/agenda/index.php/appointments/{id}/cancel
**Propósito:** cancelar cita.  
**Payload mínimo (ejemplo):**
```json
{
  "motivo_code": "cancel_paciente",
  "motivo_text": "No puede asistir",
  "notify_patient": 1,
  "contact_method": "whatsapp",
  "channel_origin": "panel",
  "actor_role": "operator",
  "actor_id": "op_1"
}
```
**Response OK (resumen):**
```json
{
  "ok": true,
  "error": null,
  "message": "",
  "data": {
    "appointment_id": "a_123",
    "status": "canceled",
    "start_at": "2026-02-03 09:00:00",
    "end_at": "2026-02-03 09:30:00",
    "motivo_code": "cancel_paciente",
    "motivo_text": "No puede asistir"
  },
  "meta": {
    "write": "cancel",
    "events_appended": 1,
    "notify_patient": 1,
    "contact_method": "whatsapp"
  }
}
```

### 1.4 POST /api/agenda/index.php/appointments/{id}/no_show
**Propósito:** marcar no_show.  
**Payload mínimo (ejemplo):**
```json
{
  "motivo_code": "no_show",
  "motivo_text": "No asistio",
  "notify_patient": 0,
  "contact_method": "whatsapp",
  "observed_at": "2026-02-03 09:45:00",
  "channel_origin": "panel",
  "actor_role": "operator",
  "actor_id": "op_1"
}
```
**Response OK (resumen):**
```json
{
  "ok": true,
  "error": null,
  "message": "",
  "data": {
    "appointment_id": "a_123",
    "status": "no_show",
    "start_at": "2026-02-03 09:00:00",
    "end_at": "2026-02-03 09:30:00",
    "motivo_code": "no_show",
    "motivo_text": "No asistio",
    "observed_at": "2026-02-03 09:45:00"
  },
  "meta": {
    "write": "no_show",
    "events_appended": 1,
    "flag_appended": 0,
    "notify_patient": 0,
    "contact_method": "whatsapp"
  }
}
```

## 2) Reglas de guardas (write guard)
- `slot_minutes` default: 30 si no se especifica.
- Validacion de rango: `start_at < end_at` (si no, `invalid_time_range`).
- `outside_schedule`: el rango no cabe en ventanas A/B/C.
- `slot_unavailable`: no existe un slot exacto para ese rango.
- `collision`: el rango se empalma con citas existentes del dia.

## 3) Bitacora / eventos (alto nivel)
Cada operacion agrega un registro en `agenda_appointment_events`:
- create: `appointment_created`
- reschedule: `appointment_rescheduled` (con from/to)
- cancel: `appointment_canceled`
- no_show: `appointment_no_show`

## 4) Operacion y trazabilidad
Campos de origen que deben viajar en cada write:
- `created_by_role` / `created_by_id` (create)
- `actor_role` / `actor_id` (reschedule, cancel, no_show)
- `channel_origin` (panel, doctor_panel, operator_panel, import, etc.)
- `notify_patient` / `contact_method` son *placeholders* para envio futuro (no implican envio automatico).

## 5) Tabla de casos (Given / When / Then)

### 5.1 Crear
- **Given:** ventanas disponibles y sin colisiones.
- **When:** POST /appointments con rango valido.
- **Then:** ok:true + evento `appointment_created`.

### 5.2 Reagendar
- **Given:** cita existente y slot destino disponible.
- **When:** PATCH /appointments/{id}/reschedule.
- **Then:** ok:true + evento `appointment_rescheduled`.

### 5.3 Cancelar
- **Given:** cita existente.
- **When:** POST /appointments/{id}/cancel.
- **Then:** ok:true + evento `appointment_canceled`.

### 5.4 No show
- **Given:** cita existente.
- **When:** POST /appointments/{id}/no_show.
- **Then:** ok:true + evento `appointment_no_show`.
