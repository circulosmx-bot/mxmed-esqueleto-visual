CIERRE — Fase II · Motor de Disponibilidad (A/B/C + colisiones)
===============================================================

1) Alcance y estado
- Motor de disponibilidad real (sin UI, sin writes nuevos).
- Capas: A (horario base), B (feriados MX), C (overrides close/open).
- Filtra colisiones con citas existentes del día.
- Genera slots atómicos (slot_minutes configurable).
- Estado: CERRADA ✅ en rama feature/agenda-impl-v1.

2) Prioridad de capas
- C > B > A.
- Feriado (B) cierra día salvo que C abra.
- Overrides (C) close restan ventanas; open agrega ventanas.
- Colisiones (citas) se restan después de A/B/C.

3) Entrada / Salida del motor
- Input: doctor_id, consultorio_id, date (YYYY-MM-DD), slot_minutes (por query).
- Output: JSON con:
  - data.windows (ventanas finales)
  - data.slots (slots de slot_minutes)
  - meta: overrides_enabled, collisions_enabled, busy_count, windows_before_collisions, slots_count, slot_minutes, is_holiday, override_types, qa_mode_seen.

4) Casos cubiertos (ejemplos)
- Día normal: genera slots según ventanas base.
- Feriado: 0 slots salvo override open.
- Override close: elimina ventanas/rangos.
- Override open: agrega ventanas (source C).
- Colisiones: citas activas restan intervalos y reducen slots.
- Guardas contra loops: slots limitados, se descartan ventanas inválidas; si no hay base/overrides/colisiones aplicables, retorna rápido con windows/slots vacíos sin cuelgues.

5) Mensajes y errores
- db_not_ready:
  - availability base schedule not ready
  - availability overrides not ready
  - availability appointments not ready
- db_error: “database error” (sin detalles).
- invalid_params: doctor_id/consultorio_id/date/slot_minutes inválidos.
- not_implemented: rutas no soportadas (501).

6) QA
- Script Fase II: docs/qa/availability_engine_qa.sh (curl, valida JSON, slots/windows presentes).
- QA pack existente (modules/agenda/qa/requests.sh) sigue pasando (ready/not_ready).
- QA_MODE: not_ready no fuerza errores; si no hay fallos reales puede devolver listas vacías.

7) Checklist de no-regresión
- Contrato JSON estable (ok/error/message/data/meta).
- meta siempre objeto; qa_mode_seen cuando aplica.
- No fatales/HTML.
- Sin cambios en endpoints write ni UI.

8) Sin alcance
- No FullCalendar.
- No creación/edición de citas.
- No nuevos estados ni roles.
- No cambios en capas B/C más allá de lectura.
