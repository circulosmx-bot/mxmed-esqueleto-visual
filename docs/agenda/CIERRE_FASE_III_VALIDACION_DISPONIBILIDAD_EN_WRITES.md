# CIERRE — Fase III · Validacion de disponibilidad en writes (Agenda v1)

## Alcance
- Create y reschedule validan contra disponibilidad real (A/B/C) y colisiones.
- No se agregan endpoints nuevos ni UI.
- No se persisten slots; solo se validan rangos.

## Reglas aplicadas
- invalid_time_range: end_at <= start_at.
- outside_schedule: el rango no cabe en ninguna ventana disponible.
- slot_unavailable: no existe slot exacto para start_at/end_at con slot_minutes.
- collision: el rango se empalma con una cita existente del dia.

## Meta en errores
Las respuestas de error incluyen meta con:
- reason
- slot_minutes
- date
- doctor_id
- consultorio_id
- overrides_enabled
- collisions_enabled

## Ejemplos curl

Crear cita en un slot valido:
```bash
curl -s -X POST "http://127.0.0.1:8009/api/agenda/index.php/appointments" \
  -H "Content-Type: application/json" \
  -d '{
    "doctor_id":"1",
    "consultorio_id":"1",
    "patient_id":"p_demo",
    "start_at":"2026-02-03 09:00:00",
    "end_at":"2026-02-03 09:30:00",
    "slot_minutes":30,
    "modality":"presencial",
    "channel_origin":"qa_script",
    "created_by_role":"system",
    "created_by_id":"qa"
  }'
```

Crear cita en colision (debe fallar con collision):
```bash
curl -s -X POST "http://127.0.0.1:8009/api/agenda/index.php/appointments" \
  -H "Content-Type: application/json" \
  -d '{
    "doctor_id":"1",
    "consultorio_id":"1",
    "patient_id":"p_demo",
    "start_at":"2026-02-03 09:00:00",
    "end_at":"2026-02-03 09:30:00",
    "slot_minutes":30,
    "modality":"presencial",
    "channel_origin":"qa_script",
    "created_by_role":"system",
    "created_by_id":"qa"
  }'
```

Reprogramar a un slot valido:
```bash
curl -s -X PATCH "http://127.0.0.1:8009/api/agenda/index.php/appointments/ID/reschedule" \
  -H "Content-Type: application/json" \
  -d '{
    "motivo_code":"qa_reschedule",
    "motivo_text":"QA reschedule",
    "from_start_at":"2026-02-03 09:00:00",
    "from_end_at":"2026-02-03 09:30:00",
    "to_start_at":"2026-02-03 11:00:00",
    "to_end_at":"2026-02-03 11:30:00",
    "slot_minutes":30,
    "channel_origin":"qa_script",
    "actor_role":"system",
    "actor_id":"qa"
  }'
```

## Matriz de errores
- invalid_time_range: rango invalido (end_at <= start_at).
- outside_schedule: fuera de ventanas disponibles (A/B/C).
- slot_unavailable: no hay slot exacto para el rango solicitado.
- collision: empalme con cita existente.
- db_not_ready: faltan tablas necesarias (availability base, overrides o citas).
- db_error: fallo general de base de datos sin detalles tecnicos.

## QA
- Script: `docs/qa/availability_write_guard_qa.sh`
- Requisitos: schedule base y appointments listos en DB.
