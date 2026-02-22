# Agenda Pública P1 (solo lectura) — Disponibilidad

## Objetivo
Exponer disponibilidad del médico para pacientes sin login:
- Vista rápida: próximos 3 días con slots
- Vista extendida: navegación semanal (hasta 4 semanas)

## Endpoint
GET /api/agenda/index.php/public/availability

### Query params soportados
- doctor_id (required)
- consultorio_id (optional; si no viene se resuelve default)
- mode = next | week
- days (solo mode=next)
- week_offset (solo mode=week, clamp 0..3)
- limit_per_day (default público 12; 0 = sin límite)

## Respuesta (shape)
- data.days[].slots[] con {start_at, end_at}
- meta.consultorio_id_used siempre presente

## UI demo (temporal)
public-agenda.html?doctor_id=1
- Consume el endpoint público
- No crea citas (P2)
- Modal “Continuar” deshabilitado (placeholder)

## QA reproducible
BASE_URL=http://127.0.0.1:8090 DOCTOR_ID=1 modules/agenda/qa/public_availability_p1.sh
