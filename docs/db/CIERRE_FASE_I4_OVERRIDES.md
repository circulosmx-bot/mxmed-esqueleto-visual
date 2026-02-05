CIERRE — Fase I.4 · Availability Overrides (Capa C) · Agenda Médica v1
=====================================================================

1) Título y estado
- Fase: I.4 — Availability Overrides (Capa C)
- Estado: CERRADA ✅
- Fecha: 2026-02-05
- Rama: feature/agenda-impl-v1

2) Objetivo
Habilitar la aplicación de Overrides de disponibilidad (Capa C) sobre el endpoint read-only `/availability`,
manteniendo el contrato JSON estable y sin introducir nuevas rutas.

3) Alcance de la fase
- Qué sí se hizo:
  - Se habilitó `overrides_table` en config (`agenda_availability_overrides`).
  - Se integró lectura de overrides en `AvailabilityController` usando `OverrideRepository`.
  - Se aplicaron overrides tipo:
    - `close`: resta intervalos sobre ventanas base.
    - `open`: agrega ventanas adicionales (source 'C').
  - Meta enriquecida:
    - `meta.overrides_enabled=true` cuando la tabla está disponible.
    - `meta.is_override=true` cuando hay overrides aplicables.
    - `meta.override_types=[...]` con tipos detectados.
  - Se agregaron scripts SQL mínimos versionados para bootstrap de horarios base y overrides.
  - Se validó comportamiento con pruebas manuales vía curl.

- Qué NO se hizo:
  - No se agregaron nuevas rutas ni nuevos endpoints.
  - No se implementó UI.
  - No se implementó motor de slots ni colisiones contra citas.
  - No se implementó gestión de overrides desde panel (solo lectura).

4) Manejo de errores confirmado
- Si `overrides_table` está configurada pero la tabla no existe:
  - `error=db_not_ready`, `message="availability overrides not ready"`.
- Errores DB genéricos:
  - `error=db_error`, `message="database error"`.
- `meta` siempre es objeto en éxitos y errores.

5) QA / Evidencia
Pruebas manuales (doctor_id=1, consultorio_id=1):

- 2026-02-02 (feriado oficial + override open):
  - Respuesta OK con ventanas base (A) y ventana override (C).
  - `meta.is_holiday=true`, `meta.overrides_enabled=true`, `meta.override_types=["open"]`.

- 2026-02-03 (override close parcial):
  - Ventana base recortada por cierre.
  - `meta.override_types=["close"]`.

- 2026-02-04 (override close día completo):
  - `windows=[]` y `meta.override_types=["close"]`.

6) Archivos clave
- modules/agenda/controllers/AvailabilityController.php
- modules/agenda/repositories/OverrideRepository.php
- modules/agenda/config/agenda.php
- modules/agenda/db/availability_bootstrap_min.sql
- modules/agenda/db/availability_bootstrap_min.compat.sql
- modules/agenda/db/availability_overrides_min.sql

7) Estado final
- Fase I.4: CERRADA ✅
- Overrides (Capa C) activos en `/availability`, contrato JSON estable.

RESULT: PASS
