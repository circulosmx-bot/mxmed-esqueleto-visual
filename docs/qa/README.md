# QA Pack Agenda v1 — Quickstart

## Propósito
Guía corta para ejecutar el QA pack de Agenda en entorno local.

## Variables requeridas (ejemplo)
```bash
export BASE_URL="http://127.0.0.1:8089"
export QA_MODE="ready"
export MYSQL_HOST="127.0.0.1"
export MYSQL_USER="mxmed"
export MYSQL_PASS="*****"
```

## Paso 0 (reset del entorno)
```bash
bash docs/qa/reset_agenda_env_qa.sh
```

## Ejecutar QA pack (orden recomendado)
```bash
bash docs/qa/availability_engine_qa.sh
bash docs/qa/availability_write_guard_qa.sh
bash docs/qa/availability_cancel_flow_qa.sh
bash docs/qa/availability_no_show_flow_qa.sh
bash docs/qa/availability_late_cancel_flow_qa.sh
bash docs/qa/availability_read_list_qa.sh
bash docs/qa/availability_read_events_qa.sh
```

## Criterio de éxito
Cada script debe terminar con `RESULT: PASS`.

## Qué cubre cada script
- `availability_engine_qa.sh`: ventanas/slots A/B/C + feriados y colisiones.
- `availability_write_guard_qa.sh`: guardia de disponibilidad (collision/outside_schedule/slot_unavailable).
- `availability_cancel_flow_qa.sh`: cancel idempotente y evento `appointment_canceled`.
- `availability_no_show_flow_qa.sh`: no_show + flag black y control de duplicados.
- `availability_late_cancel_flow_qa.sh`: late_cancel + flag grey y verificación de umbral.
- `availability_read_list_qa.sh`: GET /appointments retorna la cita creada.
- `availability_read_events_qa.sh`: GET /appointments/{id}/events muestra eventos post-creación.

## Nota
Si availability devuelve 0 slots, normalmente hay overrides activos; ejecutar reset y reintentar.

## Referencia
Documento de cierre con contexto y cobertura:
- `docs/agenda/CIERRE_FASE_III_WRITE_GUARD.md`
