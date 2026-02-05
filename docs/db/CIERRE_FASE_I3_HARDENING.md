CIERRE — Fase I.3 · Hardening técnico read-only · Agenda Médica v1
==================================================================

1) Título y estado
- Fase: I.3 — Hardening técnico read-only
- Estado: CERRADA ✅
- Fecha: 2026-02-05
- Rama: feature/agenda-impl-v1

2) Alcance de la fase
- Qué sí se hizo:
  - Endurecimiento de manejo de errores en front controller y controladores read-only.
  - Normalización de respuestas JSON (sin fatales ni HTML).
  - Garantía de `meta` como objeto y agregado de `qa_mode_seen` cuando aplica.
  - Validación temprana de parámetros (IDs numéricos) en endpoints críticos.
  - QA manual/script para rutas existentes.
- Qué NO se hizo:
  - No se agregaron nuevas rutas ni funcionalidades.
  - No se modificó lógica de negocio ni payloads OK.
  - No se incorporó UI ni flujos de escritura adicionales.
  - No se alteraron capas de disponibilidad A/B/C ni overrides.

3) Cambios realizados
3.1 Hardening del front controller (api/agenda/index.php)
- Normalización central de respuestas (ok/error/message/data/meta).
- set_error_handler + try/catch global para convertir warnings/notices/exceptions en JSON estable.
- Fallback seguro cuando la respuesta no es array o falla json_encode.
- Conserva status: 501 solo para not_implemented; 200 en demás casos.
- Inyección consistente de `qa_mode_seen` en `meta` y saneo de mensajes db_not_ready.

3.2 Hardening de controladores read-only
- AppointmentsController (list/show): try/catch \RuntimeException/\PDOException; db_not_ready/not_found/db_error sin detalles técnicos; meta objeto; validación de IDs numéricos.
- AppointmentEventsController: captura not_ready/db_error; meta objeto; evita fatales.
- ConsultoriosController: captura not_ready/db_error con mensajes estables; meta objeto.
- AvailabilityController: captura not_ready/db_error; meta objeto.
- PatientFlagsController: validación de patient_id numérico; captura not_ready/db_error; meta objeto.

3.3 QA_MODE
- Respeta QA_MODE vía env/header; agrega `qa_mode_seen` en meta.
- No fuerza errores artificiales: si no hay fallo real, endpoints pueden devolver OK (listas vacías).
- Mensajes db_not_ready saneados para no exponer SQLSTATE.

4) Manejo de errores confirmado
- db_not_ready: fuentes/tablas no listas (mensajes estables por endpoint).
- not_found: recurso inexistente (ej. appointment not found).
- invalid_params: parámetros faltantes o con formato inválido (incluye IDs no numéricos).
- db_error: mensaje genérico “database error” (sin filtración técnica).
- meta siempre como objeto en éxitos y errores.

5) QA y validación
- Script manual: docs/qa/agenda_readonly_qa.sh con batería de curls y validación ligera de contrato.
- Casos validados: rutas inexistentes (501), appointments rango/ID inválido/not found, events, consultorios sin params, availability sin params, patient flags con patient_id inválido y active_only inválido.
- Resultado: respuestas JSON controladas, sin fatal errors.

6) Fuera de alcance
- Escritura avanzada de citas/pacientes.
- Motor completo de disponibilidad A/B/C y overrides.
- UI o front-end.
- Automatizaciones, notificaciones, pagos u otras integraciones.

7) Estado final
- Fase I.3: CERRADA ✅
- Lista para fases siguientes.
