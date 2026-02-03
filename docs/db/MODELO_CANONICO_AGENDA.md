# Modelo Canónico — Agenda

## 1) Qué es Agenda
- Problema resuelto: coordina la disponibilidad, ocupación y evolución de citas médicas (desde creación hasta no-show) con trazabilidad de cada cambio.
- Lo que NO hace: no es dueña de perfiles, pacientes o consultorios; esos dominios mantienen identidad, direcciones y consentimientos.

## 2) Entidades canónicas
- **Cita (Appointment)**: unidad primera; vincula doctor, consultorio, paciente y ventana reservada.
- **Evento de cita (Appointment Event)**: bitácora append-only (creación, reschedule, cancelación, no_show, pago). Nunca se edita ni borra.
- **Flag de paciente (Patient Flag)**: advertencia/riesgo derivado de la conducta del paciente (no_show, late_cancel). Guardado por `patient_id`.
- **Horario base (Availability Base)**: catálogo semanal de franjas por doctor y consultorio; fuente para generar ventanas disponibles.
- **Override de horario (Availability Override)**: regla puntual que cierra o reabre slots; opcional, habilitado por plan.

## 3) Identidades y referencias
- Identidad canónica de cita: `appointment_id` (string verde). Referencia doctor_id, consultorio_id, patient_id (provistos desde otros dominios).
- Regla: Agenda no valida existencia de doctor/consultorio/paciente; solo registra los identificadores y deja la validación a esos dominios.

## 4) Estados y transiciones
- Estados conocidos: `created`, `rescheduled`, `canceled`, `no_show`, `completed` (más eventuales). Cada transición genera un evento en la bitácora.
- Regla: el estado actual se deriva del registro de la cita + el evento más reciente relevante (ej: si hay cancel, estado es cancelado).
- Append-only: los eventos solo se agregan, no se actualizan ni eliminan; la historia se conserva en `agenda_appointment_events`.

## 5) Operaciones canónicas
- **Crear cita**: recibe doctor, consultorio, paciente, ventana, metadata de canal; produce cita + evento `created`.
- **Reprogramar**: recibe range anterior/nuevo y motivo; produce actualización de horario y evento `rescheduled`.
- **Cancelar**: recibe motivo/notify/contact; marca estado cancelado (si hay columna) + evento `canceled`.
- **Marcar no_show**: recibe motivo/notify/contact + observed_at; produce evento `no_show` y opcional flag.
- **Consultar eventos**: retorna la bitácora ordenada por timestamp para un `appointment_id`.
- **Consultar flags**: retorna flags activos para un `patient_id`, respetando expiración (si existe).
- **Consultar availability**: recibe doctor_id, consultorio_id, date; devuelve ventanas `start_at/end_at` con `source` y meta de overrides/feriados.

## 6) Reglas de tiempo y zona horaria
- Todo está en `America/Mexico_City`.
- `start_at < end_at` por diseño; la validación ocurre en la creación/triggers.
- `weekday` usa 1=Lunes … 7=Domingo para catálogos.
- Si la fecha es feriado MX, availability puede responder sin ventanas (se marca `is_holiday`).

## 7) Flags
- Significado: indicadores de riesgo (no_show, late_cancel). Se crean por reglas de Agenda y se consultan por `patient_id`.
- Regla: no se borran; pueden expirar (campo `expires_at`) o permanecer activos hasta intervención.

## 8) Integraciones futuras
- **Notificaciones (`notifications_*`)**: se disparan por eventos críticos (creación, cancelación, flag).
- **Billing/Pagos (`billing_*`)**: consume estados de cita, receta o estudio para cobrar servicios.
- **Auditoría global (`audit_*`)**: captura el flujo de operaciones críticas que genera Agenda.
- **Plan/Anualidad (`plans_*`)**: define qué overrides o capacidades están habilitadas para cada médico/consultorio.

## 9) Límites y no-alcance
- No hay UI ni cambios en el router global aquí.
- No se implementan migraciones ni features adicionales fuera del contrato establecido.
- Los modos `QA_MODE=ready/not_ready` siguen siendo el patrón de validación básico.
