CIERRE · Fase I.6 · Semántica y contrato frontend · Agenda Médica v1
=================================================================

Este documento es el contrato semántico oficial y canónico de Agenda Médica v1, y debe servir como fuente de verdad única para backend, frontend e IA futura.

1) Glosario de términos
- Appointment: cita agendada entre paciente, médico y consultorio.
- AppointmentEvent: bitácora append-only que registra cambios (crea, confirma, reprograma, cancela, no_show, pagos, notas).
- Flag: advertencia administrativa asociada a un patient_id (grey/black).
- Slot: instancia de disponibilidad derivada de ventanas; aquí no se calcula en backend.
- Window: intervalo de tiempo (start_at/end_at) generado por capas A/B/C.
- Layer A: horario base semanal del consultorio (consultorio_schedule).
- Layer B: feriados oficiales MX (HolidayMxProvider).
- Layer C: overrides (agenda_availability_overrides) con close/open.
- QA_MODE: modo de prueba (ready vs not_ready) que controla respuestas db_not_ready.
- Collision: superposición entre ventanas disponibles y citas reales.
- Source: atributo textual que indica si una ventana viene de A o C.
- meta: objeto con datos adicionales (counts, overrides_enabled, collisions_enabled, qa_mode_seen, etc.).
- db_not_ready: error cuando la tabla fuente no está lista.
- db_error: error genérico de base de datos (mensaje “database error”).
- not_found: el recurso solicitado no existe.
- invalid_params: request mal formado o IDs no numéricos.
- AppointmentWriteController: maneja create/reschedule/cancel/no_show.
- AvailabilityRepository: construye windows base (A).
- OverrideRepository: consulta la tabla de overrides (C).
- AppointmentCollisionsRepository: lee citas del día para restar intervalos ocupados.
- meta.qa_mode_seen: indica si el servidor recibió header/env QA_MODE.
- windows_before_collisions: cantidad de ventanas antes de aplicar busy intervals.
- busy_intervals: citas actuales del día que bloquean availability.
- alerts: mensajes que el frontend debe mostrar (db_not_ready, etc.).
- reason_code: motivo seleccionado al cancelar o reprogramar.
- notify_patient/contact_method: información para notificar al paciente (WhatsApp por default).
- status: estado actual de la cita; uno de los estados listados abajo.
- overrides_enabled: indica si la tabla de overrides está activa.
- collisions_enabled: active si se pudo leer citas del día.
- meta.write & meta.events_appended: metadata relacionada a writes en scripts de QA.

2) Modelo semántico de Appointment
- Campos canónicos:
  - appointment_id (string): identidad principal persistente.
  - doctor_id, consultorio_id (string): referencias sin validación cruzada.
  - patient_id (string, puede generarse vía CreatePatientController cuando no viene).
  - start_at / end_at (datetime en America/Mexico_City): hora de atención deseada.
  - status (tentative/confirmed/pending_payment/pending_review/rescheduled/cancelled/no_show/completed)
  - modality (presencial/virtual/etc.), channel_origin (panel/qa_script/public_profile), created_by_role/created_by_id.
  - price_amount (opcional), motivo_code/motivo_text (cuando corresponde), notify_patient/contact_method.
  - created_at/updated_at automáticos en DB.
- Semántica:
  - start_at/end_at representan ventana reservada; no se generan slots automáticos en backend.
  - La fecha de creación (created_at) es auditiva; start_at define cuándo se atenderá.
  - channel_origin describe canal origen (public profile, operator panel, doctor panel, import_csv, external_future).
  - created_by_role/created_by_id registran quién creó la cita.

3) Appointment Events
- Propósito: bitácora append-only que registra cada transición importante.
- Tipos conocidos: created, confirmed, payment_requested, payment_proof_uploaded, payment_verified, rescheduled, cancelled, no_show, note_added (futuro/eventual).
- Inmutables: nunca se editan ni borran; la historia completa se guarda en appointment_events.
- Cada evento registra actor_role/actor_id, channel_origin, timestamp, from_datetime/to_datetime (para reschedule), motivo_code/text cuando aplica, notify_patient/contact_method cuando se solicita notificación.

4) Estados y transiciones
- Estados oficiales: tentative → confirmed → pending_payment → pending_review → rescheduled → completed.
- `rescheduled` es una transición registrada en la bitácora; no representa el estado final de la cita, sino que precede a otro estado operativo (confirmed, pending_payment, etc.).
- Cancelled y no_show son terminales (no se sale de ahí).
- Rescheduled mantiene appointment_id y registra from/to datetime en events; puede regresar a confirmed/pending_payment según contexto.
- Transiciones inválidas: no se permite una creación directa a completed sin pasar por confirmación, ni cambiar cancelled/no_show a otro estado sin reprocesamiento manual (no expuesto).

5) Acciones write disponibles
5.1 Create
- Genera cita nueva (tentative/confirmed según validaciones) y evento appointment_created.
- Precondiciones: datos mínimos (doctor_id, consultorio_id, start_at, end_at, modality, channel_origin, created_by_role/id). Se conecta a Pacientes si no hay patient_id.
5.2 Reschedule
- Actualiza start_at/end_at; genera evento appointment_rescheduled con from/to datetimes y motivo_code/text obligatorios; agrega meta.events_appended=1.
- Precondiciones: appointment_id válido, payload con to_start_at/to_end_at; mantiene history (same appointment_id).
5.3 Cancel
- Marca status cancelled, evento appointment_canceled con motivo; puede crear flag grey si late_cancel.
- Precondiciones: appointment_id válido, motivo_code (o texto). Notifica si notify_patient=1/contact_method.
5.4 No_show
- Marca status no_show, evento appointment_no_show, opcional flag black (history de flags); registrar observed_at si se provee.
- Precondiciones: appointment_id, motivo_code/text, puede indicar notify_patient/contact_method.

6) Contrato para frontend
- Mostrar por estado:
  - tentative: “Pendiente de confirmación” + botón confirmar/cancelar.
  - confirmed: “Confirmada” + botones reprogramar/cancelar, mostrar indicador pending_payment si aplica.
  - pending_payment: mostrar “Pendiente de pago” con CTA subir comprobante/recordatorio.
  - pending_review: “En revisión” + CTA “Solicitar revisión”.
  - rescheduled: “Reprogramada” y mostrar historial; habilitar botones según nuevo estado.
  - cancelled/no_show: resaltar en rojo, deshabilitar acciones excepto “Registrar nueva cita”.
  - completed: marcar como finalizada (sin acciones).
- Acciones habilitadas:
  - Confirmar/reschedule/cancel/no_show: condicional según estado (no permitir reprogramar si cancelled).
  - Mostrar flags y eventos en un panel de bitácora.
  - Indicador collisions_enabled y overrides_enabled para explicar por qué algunas ventanas no aparecen.
- Campos “human-friendly”: mostrar windows_abc_count, busy_count, summaries de meta (overrides, collisions) junto a la tabla de disponibilidad.

7) Matriz de errores/respuesta
- db_not_ready: falta tabla; frontend debe mostrar banner “Servicio temporalmente sin datos” y bloquear acciones de availability o writes.
- db_error: error genérico; frontend muestra mensaje “Error del servidor, intente más tarde”; no exponer detalles.
- not_found: recurso inexistente; mostrar inline “No se encontró la cita”.
- invalid_params: mostrar campos incorrectos y deshabilitar submit; mostrar el campo faltante (IDs numéricos o payload inválido).
- not_implemented: ruta no soportada; devolver 501 (solo aparece en front controller para rutas extra).
- qa_mode responses: meta.qa_mode_seen indica si se ejecutó bajo QA_MODE; útil para debugging/manual QA.

8) No-regression checklist
- QA_MODE ready y not_ready continúan cubiertos por `modules/agenda/qa/requests.sh` (script ya existente).
- Se verificó que los endpoints read-only no rompieron contrato; no se tocaron renders UI ni FullCalendar.
- No se reabrieron fases I.1–I.5; los cambios son documentales.
- Micro-ajustes pendientes: 0 ajustes.
