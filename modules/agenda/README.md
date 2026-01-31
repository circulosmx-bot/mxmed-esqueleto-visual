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
- **Capa C (overrides actual):** si existen overrides para el consultorio/doctor/fecha se aplican con mayor precedencia que feriados y horarios base (C > B > A). Las reglas pueden cerrar todo el día o rangos (type `close`) y reabrir (type `open`). Los overrides agregan una marca `meta.is_override` y `meta.override_types`. Si no se identifica la tabla real de overrides, la ruta responde `db_not_ready` con el mensaje exacto `availability overrides not ready`.
