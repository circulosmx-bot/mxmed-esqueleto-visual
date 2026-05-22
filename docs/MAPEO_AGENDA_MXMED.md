# MAPEO AGENDA MXMED

## Adenda de actualización (2026-05-18)

Estado real verificado en shell principal:
- Semana custom operativa.
- Día custom operativo (mini calendario, reloj/contexto, KPIs, Mañana/Tarde).
- Vista Mes oculta.
- Bloqueo parcial + bloqueo de día completo operativos en UX shell.
- Desbloqueo funcional con refresco en Día/Semana.
- Modal “Siguiente cita disponible” rediseñado y activo.

Este documento conserva contexto histórico, pero las secciones de “maqueta” deben interpretarse como referencia previa y no como estado actual.

## 1. Objetivo del módulo
Levantar el estado real implementado del módulo Agenda en MXMed para documentar:
- archivos reales involucrados
- endpoints reales
- tablas reales
- flujo funcional actual
- dependencias con Patients / Expediente / Clinical Encounters
- divergencias contra la arquitectura documental vigente

Este documento es descriptivo (sin cambios funcionales).

## 2. Inventario de archivos

### API Gateway y ruteo
- `api/agenda/index.php`
  - Propósito: router HTTP real de Agenda (`appointments`, `waitlist`, `availability`, `patients/{id}/flags`, `public/*`).
  - Relación: punto de entrada principal de endpoints Agenda.
- `modules/agenda/routes.php`
  - Propósito: mapa simple legacy (`GET /agenda/appointments`, `GET /agenda/appointments/{id}`, `GET /agenda/consultorios`).
  - Relación: referencia de rutas, no refleja todo el router real actual.

### Controladores Agenda
- `modules/agenda/controllers/AppointmentsController.php`
  - Lista/consulta citas por rango e id.
- `modules/agenda/controllers/AppointmentWriteController.php`
  - Writes de citas: crear, cancelar, no_show, reprogramar.
  - Incluye auto-create de paciente si llega payload sin `patient_id`.
- `modules/agenda/controllers/WaitlistController.php`
  - CRUD operativo de lista de espera + asignación de slot a cita.
  - Puede crear paciente canónico al asignar, si no existe `patient_id`.
- `modules/agenda/controllers/AvailabilityController.php`
  - Capa de disponibilidad (windows/slots), overrides y colisiones.
  - Expone también `/public/availability`.
- `modules/agenda/controllers/AppointmentEventsController.php`
  - Timeline de eventos por cita.
- `modules/agenda/controllers/PatientFlagsController.php`
  - Lectura de flags de paciente.
- `modules/agenda/controllers/ConsultoriosController.php`
  - Catálogo de consultorios por doctor.
- `modules/agenda/controllers/PublicAppointmentsController.php`
  - Flujos públicos (reserve/confirm/cancel/expire/request/verify).
- `modules/agenda/controllers/PublicOtpController.php`
  - OTP para flujos públicos.

### Repositorios y servicios
- `modules/agenda/repositories/*`
  - Capa SQL de citas, eventos, waitlist, flags, disponibilidad, colisiones, OTP.
- `modules/agenda/services/ClinicalEncounterBridge.php`
  - Bridge opcional Agenda -> Clinical Encounter cuando cita queda `completed`.
- `modules/agenda/services/AgendaService.php`, `HolidayMxProvider.php`, `OtpSender.php`
  - Servicios de soporte operativo.
- `modules/agenda/helpers/patients_client.php`
  - Puente directo a `Patients\Controllers\CreatePatientController`.

### Config y DB
- `modules/agenda/config/agenda.php`
  - Define tablas activas: `agenda_appointments`, `agenda_appointment_events`, `agenda_patient_flags`, `agenda_waitlist_entries`, `agenda_availability_overrides`.
- `modules/agenda/sql/ready_schema.sql`
  - Schema READY (incluye waitlist y public flows).
- `modules/agenda/db/ready_schema.sql`
  - Schema READY alterno (appointments/events/flags/public flows).
- `modules/agenda/db/*.sql`
  - Scripts de disponibilidad, OTP público, booking público.

### UI Agenda server-rendered (operativa de Agenda)
- `api/agenda/ui/index.php`, `day.php`, `appointment.php`, `waitlist.php`, `waitlist_assign_pick_day.php`, `waitlist_assign_pick_slot.php`, `action.php`, `lib/AgendaApiClient.php`
  - Front operativo que consume `api/agenda/index.php`.

### UI Shell principal (index.html + assets/js)
- `index.html` paneles `#p-ag-admin`, `#p-ag-ajustes`, `#p-ag-operadores`
  - Estado actual: paneles operativos para Semana/Día custom y flujos de cita/bloqueo.
- `assets/js/app.js`
  - Consume API Agenda para lecturas de disponibilidad/citas y writes operativos de cita.
  - Mantiene coexistencia con front legacy `api/agenda/ui/*` en algunos subflujos.

## 3. Endpoints reales

### Agenda base (`api/agenda/index.php`)
- `GET /api/agenda/index.php/appointments`
  - Archivo: `AppointmentsController@index`.
  - Propósito: listar citas por rango.
  - Parámetros principales: `from`, `to`, `doctor_id`, `consultorio_id`, `limit`.
  - Respuesta: wrapper `{ok,error,message,data,meta}`.

- `GET /api/agenda/index.php/appointments/{id}`
  - Archivo: `AppointmentsController@show`.
  - Propósito: detalle de cita.

- `POST /api/agenda/index.php/appointments`
  - Archivo: `AppointmentWriteController@create`.
  - Propósito: crear cita.
  - Parámetros principales: `doctor_id`, `consultorio_id`, `start_at`, `end_at`, `slot_minutes`, `modality`, `channel_origin`, `created_by_role`, `created_by_id`, opcional `patient_id` o `patient`.
  - Nota clave: si no llega `patient_id` y sí llega `patient.display_name`, intenta crear paciente canónico.

- `PATCH /api/agenda/index.php/appointments/{id}/reschedule`
  - Archivo: `AppointmentWriteController@reschedule`.

- `POST /api/agenda/index.php/appointments/{id}/cancel`
  - Archivo: `AppointmentWriteController@cancel`.

- `POST /api/agenda/index.php/appointments/{id}/no_show` (y alias `no-show`)
  - Archivo: `AppointmentWriteController@noShow`.

- `GET /api/agenda/index.php/appointments/{id}/events`
  - Archivo: `AppointmentEventsController@index`.

- `GET /api/agenda/index.php/patients/{patient_id}/flags`
  - Archivo: `PatientFlagsController@index`.

- `GET /api/agenda/index.php/consultorios`
  - Archivo: `ConsultoriosController@index`.

- `GET /api/agenda/index.php/availability`
  - Archivo: `AvailabilityController@index`.

### Waitlist
- `GET /api/agenda/index.php/waitlist`
  - Archivo: `WaitlistController@index`.
- `POST /api/agenda/index.php/waitlist`
  - Archivo: `WaitlistController@store`.
- `PATCH /api/agenda/index.php/waitlist/{id}`
  - Archivo: `WaitlistController@update`.
- `POST /api/agenda/index.php/waitlist/{id}/assign`
  - Archivo: `WaitlistController@assign`.
  - Nota clave: puede crear paciente canónico si la entrada no trae `patient_id`.

### Públicos (`public/*`)
- `GET /api/agenda/index.php/public/availability`
- `POST /api/agenda/index.php/public/otp/request`
- `POST /api/agenda/index.php/public/otp/verify`
- `POST /api/agenda/index.php/public/maintenance/expire`
- `POST /api/agenda/index.php/public/appointments/reserve`
- `POST /api/agenda/index.php/public/appointments/confirm`
- `POST /api/agenda/index.php/public/appointments/cancel`
- `POST /api/agenda/index.php/public/appointments/request`
- `POST /api/agenda/index.php/public/appointments/verify`
  - Archivo principal: `PublicAppointmentsController` + `PublicOtpController`.

## 4. Tablas reales relacionadas

### Agenda núcleo
- `agenda_appointments`
  - Uso: registro principal de citas.
  - Campos clave: `appointment_id`, `doctor_id`, `consultorio_id`, `patient_id`, `start_at`, `end_at`, `status`, `channel_origin`.
- `agenda_appointment_events`
  - Uso: bitácora de eventos por cita.
  - Campos clave: `event_id`, `appointment_id`, `event_type`, `timestamp`, campos de motivo/contacto/actor.
- `agenda_patient_flags`
  - Uso: flags clínico-operativos del paciente desde eventos de agenda (`late_cancel`, `no_show`).
  - Campos clave: `flag_id`, `patient_id`, `flag_type`, `reason_code`, `source_appointment_id`.
- `agenda_waitlist_entries`
  - Uso: lista de espera.
  - Campos clave: `id`, `doctor_id`, `consultorio_id`, `status`, `patient_id`, `patient_name`, `patient_phone`.

### Agenda pública / soporte
- `agenda_public_appointment_flows`
  - Uso: flujo transaccional de reserva pública (pending_otp/confirm/cancel/expire).
- `agenda_public_otp_requests` (en controller público) y `agenda_public_otps` (en repositorio OTP)
  - Uso: OTP de validación pública.
  - Observación: hay coexistencia de nomenclatura en código.

### Disponibilidad / catálogo consultorio
- `consultorio_schedule` (y candidatos: `consultorio_schedules`, `consultorio_horarios`, `agenda_consultorio_schedule`)
  - Uso: ventanas base de disponibilidad.
- `agenda_availability_overrides`
  - Uso: aperturas/cierres puntuales sobre disponibilidad.
- `consultorios`
  - Uso: catálogo de consultorios por doctor (si existe en DB activa).

### Relación indirecta con otros módulos
- `patients_*` (módulo Patients)
  - Agenda crea/vincula paciente vía `CreatePatientController`.
- `clinical_encounters` (módulo Clinical)
  - Solo vía bridge opcional de agenda cuando cita llega en estado `completed`.

## 5. Flujo funcional actual (real implementado)

1. **Carga de agenda**
   - UI operativa dual:
     - shell principal (`index.html` + `assets/js/app.js`) para Semana/Día custom;
     - front legacy en `/api/agenda/ui/*`.
   - Ambos consumen `/availability` y `/appointments`.

2. **Creación de cita**
   - `action.php` -> `POST /appointments`.
   - Si llega `patient_id`, cita queda vinculada.
   - Si no hay `patient_id` y llega `patient`/`display_name`, `AppointmentWriteController` intenta crear paciente en módulo Patients y usa ese `patient_id`.

3. **Gestión de cita**
   - detalle (`/appointments/{id}`), eventos (`/appointments/{id}/events`), cancelar, no_show, reprogramar.
   - no_show/late_cancel pueden anexar flags en `agenda_patient_flags`.

4. **Waitlist**
   - alta de entrada (`/waitlist`), cambio de estado (`PATCH /waitlist/{id}`), asignación a cita (`POST /waitlist/{id}/assign`).
   - en assign, si no hay `patient_id`, intenta resolverlo creando paciente con `patient_name + patient_phone`.

5. **Flujo público**
   - disponibilidad pública -> OTP -> reservar/confirmar/cancelar/expirar.
   - confirma cita y actualiza estado según OTP y flow.

6. **Empuje a consulta activa**
   - No hay inicio automático de consulta desde Agenda (regla clínica preservada).
   - Existe bridge opcional Agenda -> Clinical Encounter solo para citas `completed` y con feature flag (`AGENDA_ENABLE_CLINICAL_ENCOUNTER_BRIDGE=1`).

## 6. Interconexiones y dependencias

### Con Patients
- Dependencia directa: `agenda_patients_create()` llama `Patients\Controllers\CreatePatientController`.
- Ocurre en:
  - `AppointmentWriteController@createFromPayload`
  - `WaitlistController@resolvePatientId`

### Con Expediente
- Arquitectónicamente esperado: Agenda puede abrir expediente.
- Implementación actual:
  - Shell principal ya opera Semana/Día custom de Agenda.
  - Persiste deuda de integración explícita para handoff Agenda -> Expediente con CTA formal (sin inicio automático de consulta).

### Con Encounters / Clinical
- Dependencia opcional via `ClinicalEncounterBridge`:
  - si cita queda `completed` y bridge habilitado, intenta crear encounter en `/api/clinical/index.php/patients/{patient_id}/encounters`.
- En flujo normal de Agenda (`create` típico) el status inicial no es `completed`; no crea encounter automáticamente.

### Con Timeline / Documents / Orders / Results
- No hay acoplamiento directo fuerte desde Agenda a timeline/documentos/órdenes/resultados en este módulo.
- El vínculo principal es por `patient_id` y, opcionalmente, por encounter creado vía bridge clínico.

## 7. Divergencias detectadas (solo reporte)

1. **Agenda shell vs Agenda operativa**
   - Coexisten dos frentes operativos: shell custom y legacy `api/agenda/ui/*`.
   - La divergencia actual es de cobertura homogénea, no de ausencia funcional en shell.

2. **Arquitectura deseada vs implementación actual**
   - Regla deseada: Agenda abre expediente pero no inicia consulta automática.
   - Implementación actual: no hay auto-inicio de consulta; falta cerrar UX de handoff explícito Agenda -> Expediente.

3. **Bridge clínico con potencial de acoplamiento**
   - Existe bridge Agenda -> Encounter para citas `completed` (feature flag).
   - Debe gobernarse para no romper la regla de inicio explícito de consulta en frontend.

4. **Nomenclatura OTP pública**
   - Coexisten referencias `agenda_public_otp_requests` y `agenda_public_otps` según componente.
   - Riesgo de confusión operativa/documental si la DB activa no está alineada.

5. **Dependencia de tablas candidatas**
   - Disponibilidad depende de localización de tabla entre varios nombres candidatos (`consultorio_schedule`, etc.).

## 8. Preguntas abiertas / zonas grises

1. ¿Cuál es la ruta canónica de UX para “abrir expediente desde cita” dentro del shell principal?
2. ¿El bridge Agenda->Encounter debe permanecer solo para estados `completed` post-atención (backoffice) o migrarse a otro gatillo?
3. ¿Cuál tabla OTP pública queda como canónica (`agenda_public_otp_requests` vs `agenda_public_otps`)?
4. ¿Se consolidará la UI Agenda del shell (`p-ag-*`) contra API Agenda o se mantendrá como capa separada?
5. ¿Cuál es la estrategia final para propagar `appointment_id` hacia contexto clínico visible (header/historial/encounter) en flujo integrado?

## 9. RBAC F2.3 (cierre parcial)

Documento de referencia:
- `docs/AGENDA_RBAC_MATRIZ_ACTORES_MXMED.md`

Estado actual:
- Existe trazabilidad de actor/origen en múltiples flujos (`actor_role`, `created_by_role`, `channel_origin`).
- Existe validación de `doctor_scope` en rutas privadas.
- Frontend gating médico vs operador activo implementado (F2.2).
- Backend enforcement mínimo implementado (F2.3A/F2.3B):
  - `operator` bloqueado en `/operators/*`.
  - `operator` bloqueado en rutas de Configuración de Agenda (`settings`, `schedule`, `PUT /consultorios`, `geocode/*`).
  - `operator` permitido en operación de Agenda (`appointments`, `availability`, `waitlist`, `GET /consultorios`).

Regla de negocio explícita vigente:
- `operator` activo interno **sí** puede ejecutar `no_show` dentro de la operación de Agenda.

Nota:
- F2.3 queda cerrado de forma parcial: ya existe enforcement mínimo de Operadores + Configuración.
- Pendientes F2.4+:
  - actores externos (`patient`, `call_center`, `ai_operator`);
  - identidad autoritativa (sesión/JWT/API key);
  - auditoría unificada por actor en todas las acciones.

## 10. Adenda F2.5 (auditoria / actor attribution)

Referencia:
- `docs/AGENDA_AUDITORIA_ACTOR_ATTRIBUTION_MXMED.md`

Estado actualizado:
- F2.5B cerrado:
  - payload frontend normalizado para create/reschedule/cancel/no_show/waitlist assign/alta equivalente.
  - compatibilidad legacy preservada (`created_by_*`, `channel_origin`).
  - campos canónicos agregados (`actor_*`, `action`, `entity_*`, `occurred_at`, `metadata`).
- F2.5C cerrado:
  - persistencia backend de actor en writes de citas, incluyendo `appointment_rescheduled`.
  - conservación de trazabilidad `from_consultorio_id` / `to_consultorio_id`.
- F2.5D cerrado:
  - `GET /appointments/{id}/events` con DTO uniforme aditivo.
  - preserva campos raw/legacy.
  - agrega `action`, `entity_type`, `entity_id`, `occurred_at`, `created_by_role`, `created_by_id`, `actor_display_name`, `metadata`.
  - `notes` sigue string raw; `metadata` deriva de `notes` JSON o `notes_text` cuando no es JSON.
- F2.5E1 cerrado:
  - `POST /waitlist` acepta y persiste actor attribution compatible.
  - `PATCH /waitlist/{id}` acepta y persiste actor attribution compatible.
  - payload legacy sin actor sigue funcionando.
  - si la tabla no tiene columnas actor, se usa fallback seguro en `notes` JSON.
  - respuestas waitlist hidratan campos canónicos de actor de forma aditiva.
- F2.5E2 cerrado:
  - `POST /waitlist/{id}/assign` mantiene flujo operativo (crea cita y confirma entry).
  - genera `appointment_created` y `appointment_reassigned_from_waitlist` sin duplicidad.
  - `appointment_reassigned_from_waitlist` ahora guarda `notes` JSON estructurado.
  - metadata esperada incluye:
    - `source=waitlist_assign`
    - `waitlist_entry_id`
    - `consultorio_id`
    - `assigned_slot.start_at`
    - `assigned_slot.end_at`
    - `assigned_slot.slot_minutes`
    - `actor_display_name` si aplica
    - `linked_cancelled_appointment_id` y `override/override_reason` si aplica
  - status `confirmed` de waitlist recibe audit payload compatible (`waitlist_assigned`).

Decision documental:
- Mantener compatibilidad backward.
- Converger a contrato canonico por fases (F2.5B-F2.5F) sin romper flujos estabilizados.

Commits relevantes F2.5:
- `7d00d52` normalización frontend de payload actor (F2.5B).
- `62e170a` persistencia actor en eventos de reprogramación (F2.5C mínimo).
- `3df2255` DTO uniforme aditivo en events (F2.5D).
- `be3f86c` actor attribution compatible en `POST/PATCH /waitlist` (F2.5E1).
- `1e455cb` estandariza auditoría explícita de `waitlist assign` (F2.5E2).
- `8af3e7b` fix Semana corte horario (relacionado por QA, fuera del contrato de auditoría).

## 11. Pendiente inmediato F2.5E

- E3+: auditoría de bloqueos/desbloqueos de disponibilidad.
- Definición de persistencia backend para eventos de bloqueo (si aplica).
- Actor attribution integral para availability/block events.
- Política de visibilidad de auditoría por rol.

## 12. Cierre F2.6 (QA integral RBAC + auditoría)

Resultado general: **PASS**.

Bloques cubiertos:
- Preflight (repo limpio, rama correcta, apertura del sistema).
- RBAC frontend doctor/operator.
- RBAC backend operator (deny en configuración/operadores, allow en operación Agenda).
- Actor attribution en writes de citas y lectura de eventos.
- Actor attribution en waitlist create/update/assign.
- Compatibilidad legacy.
- Smoke mínimo Agenda.

IDs QA de referencia:
- `doctor_id`: `1`
- `appointment_id`: `93730ced68f31f7d5e545ff9`, `2c4a2b508e970bb30c37f8c3`, `7e525e13e8117b07677e760f`
- `waitlist_id`: `05b249d5f734d24b378b4a74`, `720d267fe240ef76f4627ba7`

Notas:
- Se conservaron datos QA en tablas de citas/waitlist (sin limpieza destructiva en esta fase).
- `409 Conflict` ocasional sigue visible en entorno QA; no bloqueó los flujos validados.
- `bloqueo parcial` y `domingo sin horario` no se re-probaron exhaustivamente en F2.6, pero mantienen PASS previo dedicado.

Pendiente posterior recomendado:
- Diseñar backend de bloqueos/desbloqueos con auditoría canónica.
- Definir fuente autoritativa de actor para endurecer RBAC y evitar spoofing.
