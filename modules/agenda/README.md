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

## Patient flags (write-ready plumbing)

- El toggle `patient_flags_table` en `modules/agenda/config/agenda.php` activa tanto la lectura como la futura escritura de flags; mientras sea `null` la ruta responde `db_not_ready` con `message: "patient flags not ready"`.  
- `PatientFlagsWriteRepository` descubre las columnas reales y solo inserta las que existen, generando IDs y timestamps cuando se requieren (flag_id, created_at).  
- El repositorio será reutilizado por la Parte 3-B para agregar flags tras cancelaciones/no shows.  
- **Importante:** la introducción de flags no bloquea ni cancela citas automáticamente; son registros append-only para auditoría/manual review.

## Write endpoint skeletons (Fase III Parte 2-A prep)

- **POST** `/api/agenda/index.php/appointments`  
  - Espera payload JSON con `doctor_id`, `consultorio_id`, `start_at`, `end_at`, `modality`, `patient_id`, `channel_origin`, `created_by_role`, `created_by_id`.  
  - Valida fechas (`YYYY-MM-DD HH:MM:SS`), `start_at < end_at` y campos requeridos, pero en esta fase aún no escribe nada.  
  - Responde `not_implemented` con mensaje `"write operations not enabled yet"` mientras el flujo de escritura no esté activo.  

- **PATCH** `/api/agenda/index.php/appointments/{appointment_id}/reschedule`  
  - Body con `from_start_at`, `from_end_at`, `to_start_at`, `to_end_at`, `motivo_code`, `motivo_text`, `notify_patient`, `contact_method`.  
  - Valida rangos y al menos un motivo; por ahora retorna `not_implemented`.  

- **POST** `/api/agenda/index.php/appointments/{appointment_id}/cancel`  
  - Body con `motivo_code`, `motivo_text`, `notify_patient`, `contact_method`.  
  - Exige motivo y responde `not_implemented`.  

> Nota: estas rutas solo validan request y devuelven el error `not_implemented` hasta que la Parte 2-B y siguientes habiliten las operaciones de escritura.

## Appointment create (Fase III Parte 2-B habilitada)

- Si `modules/agenda/config/agenda.php` define `appointments_table` y `appointment_events_table` con tablas reales, el POST `/appointments` inserta la cita y un evento `appointment_created` en una sola transacción.  
- Si `appointments_table` está `null` o la tabla no existe, responde `db_not_ready` con mensaje exacto `appointments table not ready`.  
- Si `appointment_events_table` está `null` o no existe, responde `db_not_ready` con mensaje exacto `appointment events not ready`.  
- El payload sigue validándose; en caso de éxito regresa `write=create` y `events_appended=1`.  
- Reprogramaciones y cancelaciones siguen devolviendo `not_implemented` hasta las siguientes partes.

## Appointment reschedule (Fase III Parte 2-C habilitada)

- Requiere las mismas tablas (`appointments_table`, `appointment_events_table`). Si falta alguna, la ruta responde `db_not_ready` con `appointments table not ready` o `appointment events not ready` respectivamente.  
- Actualiza `start_at`/`end_at` de la cita dentro de una transacción y añade un evento `appointment_rescheduled` con la bitácora de `from_*` y `to_*`.  
- La respuesta exitosa incluye el rango anterior y el nuevo, junto con `meta.write=reschedule`, `meta.events_appended=1` y los campos `notify_patient`/`contact_method`.  
- Si no existe la cita, responde `not_found` con `message: "appointment not found"`.  
- Las cancelaciones aún devolvemos `not_implemented`.

## Appointment cancel (Fase III Parte 2-D habilitada)

- Utiliza las mismas configuraciones (`appointments_table`, `appointment_events_table`). Si las tablas faltan, el endpoint responde `db_not_ready` con el mensaje exacto `appointments table not ready` o `appointment events not ready`.  
- Registra el `appointment_canceled` event append-only, guarda los motivos y el contacto, y responde con `meta.write=cancel`, `meta.events_appended=1`.  
- Si la cita no existe se devuelve `not_found` con `message: "appointment not found"`.  
- Las cancelaciones solo actualizan el campo `status` si existe en la tabla; si no está presente, el evento registra la cancelación pero no bloquea la consulta.  
- No se genera lógica de flags/no show en esta fase.
