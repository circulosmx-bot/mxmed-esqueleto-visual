# CIERRE — Fase III · Write Guard (Agenda v1)
**Fecha:** 2026-02-05

## Contexto
El flujo de escritura devolvia `db_error` generico en validaciones de disponibilidad, aun cuando el caso era semantico (colision o fuera de horario).

## Cambios implementados
- `AppointmentWriteController`: el write guard ahora retorna errores semanticos (`collision`, `outside_schedule`, `slot_unavailable`) en lugar de `db_error`.
- Colisiones: se usan intervalos ocupados via `AppointmentCollisionsRepository`.

## Contratos de error
- `collision`
- `outside_schedule`
- `slot_unavailable`
- `db_not_ready` (cuando aplica)
- `invalid_params` (cuando aplica)

## QA
Scripts que deben pasar:
- `docs/qa/availability_engine_qa.sh`
- `docs/qa/availability_write_guard_qa.sh`
Nota: el QA write_guard es idempotente y realiza cleanup previo de fixtures.

## QA Pack — Como correr
**Variables requeridas (ejemplo):**
```bash
export BASE_URL="http://127.0.0.1:8089"
export QA_MODE="ready"
export MYSQL_HOST="127.0.0.1"
export MYSQL_USER="mxmed"
export MYSQL_PASS="*****"
```

**Cómo validar (Fase III)**
```bash
bash docs/qa/reset_agenda_env_qa.sh
bash docs/qa/availability_engine_qa.sh
bash docs/qa/availability_write_guard_qa.sh
```
Este reset desactiva overrides de QA y asegura schedule base en weekday=2 para doctor_id=1/consultorio_id=1.

**Orden de ejecucion (criterio de exito: `RESULT: PASS` en cada uno):**
1) `bash docs/qa/availability_engine_qa.sh`
2) `bash docs/qa/availability_write_guard_qa.sh`
3) `bash docs/qa/availability_cancel_flow_qa.sh`
4) `bash docs/qa/availability_no_show_flow_qa.sh`
5) `bash docs/qa/availability_late_cancel_flow_qa.sh`
6) `bash docs/qa/availability_read_list_qa.sh`
7) `bash docs/qa/availability_read_events_qa.sh`

**Nota:** si availability devuelve 0 slots, normalmente hay overrides activos; ejecutar reset y reintentar.

**Que cubre cada script (resumen):**
- `availability_engine_qa.sh`: ventanas/slots A/B/C + feriados y colisiones.
- `availability_write_guard_qa.sh`: write guard (collision/outside_schedule/slot_unavailable).
- `availability_cancel_flow_qa.sh`: cancel idempotente + evento.
- `availability_no_show_flow_qa.sh`: no_show + flag black + idempotencia.
- `availability_late_cancel_flow_qa.sh`: late_cancel + flag grey + idempotencia.
- `availability_read_list_qa.sh`: GET appointments (read list) devuelve la cita creada.
- `availability_read_events_qa.sh`: GET events devuelve eventos tras crear cita.

## PR / Commit
- PR #4
- Commit `ef48f30`

## No reabrir decisiones
Este cierre se considera estable y no se re-disena en Agenda v1.
Para el QA pack completo ver `docs/qa/README.md`.
