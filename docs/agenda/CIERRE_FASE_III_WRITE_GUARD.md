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

## PR / Commit
- PR #4
- Commit `ef48f30`

## No reabrir decisiones
Este cierre se considera estable y no se re-disena en Agenda v1.
