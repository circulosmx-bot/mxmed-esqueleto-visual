# Agenda Module (bootstrapping v1)

Esta carpeta agrupa los componentes técnicos básicos del módulo Agenda Médica v1.
- `routes.php`: describe los endpoints planificados.
- `controllers/`: controladores que responden 501 (no implementado aún).
- `services/`: servicios compartidos vacíos.
- `repositories/`: repositorios con stubs para futuros accesos.
- `validators/`: validadores de requests pendientes.

 El objetivo es preparar la base y dejar guardadas las convenciones antes de implementar la lógica.

- El módulo reutiliza `api/_lib/db.php` y su helper `mxmed_pdo()` para obtener la conexión PDO sin reconfigurar el proyecto.

## Availability endpoint (Capa A + fundación)

- **Ruta:** `GET /api/agenda/index.php/availability?doctor_id={id}&consultorio_id={id}&date=YYYY-MM-DD`
- **Devuelve:** ventanas (`windows`) con `start_at`/`end_at` en `America/Mexico_City`.
- **No hace:** no calcula slots, no aplica feriados (Capa B), no considera overrides (Capa C) ni colisiones con citas.
- **Errores:** `invalid_params` (meta con doctor_id/consultorio_id/date), `db_not_ready` (mensaje exacto `availability base schedule not ready`), `db_error`.
- **Feriados (Capa B):** si `date` cae en un feriado oficial de México (Año Nuevo, Constitución, Natalicio de Benito Juárez, Día del Trabajo, Independencia, Revolución Mexicana, Navidad), `windows` estará vacío y `meta.is_holiday` será `true` con el nombre del feriado.
- **Capa C:** más adelante se podrán definir excepciones para reabrir o cerrar fechas específicas (no implementado en v1).
- **Overrides (C):** cuando la configuración `overrides_table` es `null`, el endpoint continúa funcionando sobre las capas A/B y `meta.overrides_enabled` es `false`. Si se configura un nombre de tabla pero esta aún no existe, la ruta falla con `db_not_ready` y mensaje `availability overrides not ready`. Cuando la tabla está disponible, los overrides se aplican con prioridad sobre feriados y horarios base (C > B > A), pueden cerrar días completos o rangos (`close`) y reabrirlos (`open`), y añaden `meta.is_override`/`meta.override_types` en la respuesta.

## Appointment events (Fase III Parte 1 – read-only)

- **Ruta:** `GET /api/agenda/index.php/appointments/{appointment_id}/events?limit=200`
- **Qué devuelve:** todos los eventos append-only asociados al `appointment_id`, ordenados por `timestamp` asc (bitácora del appointment). Controla `limit` (default 200, max 500).
- **Configuración:** exige que `modules/agenda/config/agenda.php` exponga `appointment_events_table` con el nombre real de la tabla. Mientras sea `null`, el endpoint responde `db_not_ready` con el mensaje exacto `appointment events not ready`. Cuando la tabla existe, se devuelven los eventos reales (solo lectura).
- **Errores:** `invalid_params`, `db_not_ready` (`appointment events not ready`), `db_error`.

## Patient flags (Fase III Parte 1 – read-only)

- **Ruta:** `GET /api/agenda/index.php/patients/{patient_id}/flags?active_only=1&limit=200`
- **Qué devuelve:** flags del paciente (`patient_id`) ordenados por `created_at` desc; `active_only=1` filtra flags en vigencia (`expires_at` nulo o en el futuro). `limit` 200 por defecto, max 500.
- **Configuración:** exige que `modules/agenda/config/agenda.php` tenga `patient_flags_table` con el nombre real de la tabla. Si queda `null`, el endpoint responde `db_not_ready` con el mensaje exacto `patient flags not ready`.
- **Errores:** `invalid_params`, `db_not_ready` (`patient flags not ready`), `db_error`.

> Nota: Fase III Parte 1 es completamente de solo lectura; la edición de citas, eventos o flags llegará en la Parte 2.
