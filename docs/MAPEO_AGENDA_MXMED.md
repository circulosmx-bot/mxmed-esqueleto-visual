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

## 13. Adenda F3.1 (fuente autoritativa de actor)

Estado documental:
- Se agrega contrato formal en `AGENDA_ACTOR_AUTORITATIVO_MXMED.md`.
- No hay cambios funcionales en esta fase; solo definición de estrategia.

Contrato objetivo de actor efectivo (rutas privadas):
- `actor_role`
- `actor_id`
- `doctor_id`
- `operator_id`
- `channel_origin`
- `auth_source`
- `is_authoritative`
- `auth_mode`

Modos definidos:
- `strict`: producción, identidad fuerte, sin override spoofeable.
- `compat`: compatibilidad local/dev durante transición.
- `qa_override`: override permitido solo en QA/dev habilitado.
- `public_flow`: rutas públicas separadas del RBAC privado.

Regla operativa futura para `operator` en modo estricto:
- Debe existir en `agenda_operators`.
- Debe estar `active`.
- Debe coincidir con `doctor_id` efectivo.
- `paused`, `pending`, `archived` no operan rutas privadas.

Plan vinculado:
- F3.2 helper backend con metadatos `auth_source/is_authoritative`.
- F3.3 validación de operador activo.
- F3.4 endurecimiento de overrides por entorno.
- F3.5 consumo frontend de actor efectivo.
- F3.6 QA integral strict/compat/public/spoofing.

## 14. Adenda F3.2A (implementación backend actor efectivo)

Estado: **cerrado**.

Implementación:
- `api/agenda/index.php`
- helper: `resolveEffectiveAgendaActor(...)`
- commit: `82f6320`

Contexto efectivo ya resuelto en backend (aditivo):
- `actor_role`
- `actor_id`
- `doctor_id`
- `operator_id`
- `channel_origin`
- `auth_source`
- `auth_mode`
- `is_authoritative`
- `actor_role_source`
- `warnings`
- `mode/strict/compat/user_id`

Compatibilidad funcional validada:
- fallback `doctor` sin actor explícito.
- header/query/body siguen operativos en compat/QA.
- RBAC F2.3 sin cambios de comportamiento.
- `public/*` permanece estable (`public_flow`).

Pendiente post F3.2A:
- Validar `operator` activo contra `agenda_operators`.
- Endurecer strict/anti-spoofing.
- Exponer observabilidad adicional en meta si se decide F3.2B.

## 15. Adenda F3.3B (validación observacional operator)

Estado: **cerrado** (observacional, sin bloqueo nuevo).

Implementación:
- commit `15d23a4`
- `api/agenda/index.php`: `resolveAgendaOperatorIdentity(...)`
- `modules/agenda/repositories/OperatorsRepository.php`: `findOperatorIdentity(...)`

Alcance:
- Con `actor_role=operator`, backend valida identidad contra `agenda_operators`.
- En `compat` y `qa_override`:
  - no bloquea rutas operativas;
  - agrega warnings/campos observacionales al `actorContext`.

Warnings posibles:
- `operator_id_missing`
- `operator_not_found`
- `operator_doctor_mismatch`
- `operator_not_active`
- `operator_identity_db_not_ready`
- `operator_identity_valid`

Campos observacionales:
- `operator_identity_checked`
- `operator_identity_found`
- `operator_status`
- `operator_is_active`
- `operator_identity_warning`
- `warnings[]`

Compatibilidad validada:
- operación Agenda permitida para operator en compat aunque identity falle;
- RBAC F2.3 sin regresión;
- rutas públicas sin afectación.

Pendiente F3.3C:
- enforcement en `strict` con validación obligatoria de operador activo y scope de doctor.

## 16. Adenda MVP Waitlist integrado en “Buscar siguiente cita disponible” (2026-05-23)

Estado: **implementado y validado en QA funcional real**.

Commits base del MVP:
- `d934f8a` `fix(agenda): soporta lista de espera para todos los consultorios`
- `d176ee0` `feat(agenda): agrega entrada UI a lista de espera`

### 16.1 Qué es el MVP Waitlist
- Integración mínima y segura de Lista de espera dentro del flujo ya operativo de Agenda.
- Objetivo: permitir alta de waitlist cuando no se encuentra horario conveniente, sin crear cita inmediata.
- Alcance MVP: entrada UI + términos + formulario modo waitlist + `POST /waitlist`.

### 16.2 Dónde nace en UI
- Modal: **“Buscar siguiente cita disponible”**.
- CTA visible en la parte baja del modal: **“Inscribir en lista de espera”**.
- Modal de términos previo a la captura.
- Reuso del modal “Nueva cita” en modo waitlist (sin reutilizar submit de creación de cita).

### 16.3 Concepto UX aplicado
- “Entrar en lista de espera” **no** representa cita confirmada.
- **No** garantiza disponibilidad anticipada.
- El paciente se considera/contacta si aparece una oportunidad compatible por cancelación o reprogramación.

### 16.4 Flujo operativo implementado
1. Usuario abre “Buscar siguiente cita disponible”.
2. Si no encuentra horario adecuado, usa CTA “Inscribir en lista de espera”.
3. Se muestra modal de términos (cancelar no crea nada).
4. “Entiendo, continuar” abre formulario modo waitlist.
5. Se captura paciente:
   - nuevo, o
   - registrado (con búsqueda/selección).
6. Submit en modo waitlist ejecuta `POST /waitlist`.
7. Se muestra confirmación explícita: no cita confirmada.

### 16.5 Regla “Todos los consultorios”
- En modo “Todos”, frontend envía sentinel temporal:
  - `consultorio_id="__all__"`.
- Backend ajustado para operación end-to-end:
  - `GET /waitlist?consultorio_id={real}` incluye entries de ese consultorio **y** `__all__`.
  - `POST /waitlist/{id}/assign` permite asignar entries `__all__` a consultorio real.
- Resultado de assign:
  - la cita creada usa `consultorio_id` real del slot destino;
  - **no** se crea cita con `consultorio_id="__all__"`.

### 16.6 Endpoints usados por el MVP
- `POST /api/agenda/index.php/waitlist`
- `GET /api/agenda/index.php/waitlist`
- `POST /api/agenda/index.php/waitlist/{id}/assign` (validación de compatibilidad y recuperación de hueco)

### 16.7 QA funcional final (resumen)
Resultado global: **PASS**.

Cobertura validada:
- CTA waitlist visible y operativo desde “Buscar siguiente cita disponible”.
- Términos:
  - cancelar no crea entry,
  - continuar abre formulario modo waitlist.
- Paciente nuevo:
  - crea `POST /waitlist`.
- Paciente registrado:
  - crea `POST /waitlist` con `patient_id`.
- Modo Todos:
  - payload con `consultorio_id="__all__"`.
- Consultorio específico:
  - payload con `consultorio_id` real.
- `GET /waitlist` devuelve entries creadas (incluyendo regla `__all__` en filtro específico).
- Assign de entry `__all__` a consultorio real:
  - permitido,
  - cita creada con consultorio real.
- Confirmado en Network:
  - **sin** `POST /appointments` accidental desde alta waitlist UI.
- Sin regresión funcional observada en “Nueva cita” normal.

### 16.8 Deudas y siguientes pasos
- Sentinel `__all__`:
  - solución temporal válida; deuda semántica a formalizar (`consultorio_scope` canónico o equivalente).
- Investigar `409 Conflict` intermitente observado en consola QA (no bloqueante en este ciclo).
- Ejecutar validación dedicada de `strict operator active` en sesión real de operador (no solo `compat`).
- Definir limpieza de datos QA creados en waitlist/citas según política de entorno.
- Fase posterior recomendada:
  - UI completa de Lista de espera,
  - flujo “Resolver hueco” contextual desde cancelación/reprogramación.

## 17. Adenda MVP “Resolver hueco” post-cancelación (2026-05-23)

Estado: **implementado, validado y estabilizado**.

Commit base:
- `5ea0d54` `feat(agenda): mejora flujo resolver hueco post-cancelacion`

### 17.1 Qué es “Resolver hueco”
- Flujo contextual para recuperación operativa de un slot liberado tras cancelación.
- Objetivo: reducir pérdida de disponibilidad usando candidatos existentes en lista de espera.

### 17.2 Dónde nace en UI
- Nace inmediatamente después de cancelar una cita, dentro del modal de acciones de cita.
- No abre módulo nuevo: reutiliza bloque post-cancel ya existente y lo orienta a recuperación del hueco.

### 17.3 Qué muestra
- Resumen del hueco liberado:
  - fecha/día,
  - hora inicio-fin,
  - duración,
  - consultorio.
- Lista de candidatos waitlist compatibles para ese contexto.
- Soporta candidatos:
  - de consultorio específico,
  - y `consultorio_id="__all__"` (cualquier consultorio).

### 17.4 Qué hace assign
- Acción principal: `POST /api/agenda/index.php/waitlist/{id}/assign`.
- Resultado esperado:
  - crea cita en el slot liberado,
  - actualiza waitlist a `confirmed`,
  - refresca Agenda (flujo diferido al cierre del modal para preservar feedback UX).
- La cita resultante se crea con consultorio real del slot destino.

### 17.5 Reglas importantes del MVP
- Nunca usa `__all__` como consultorio de cita final.
- Límite visual de candidatos: máximo 5.
- Confirmación obligatoria antes de asignar.
- Si no se resuelve el hueco, la cancelación permanece (no se revierte ni crea cita nueva).

### 17.6 QA funcional final (resumen)
Resultado global: **PASS**.

Cobertura validada:
- cancelación QA futura -> aparece bloque “Resolver hueco”.
- resumen de hueco correcto.
- candidatos waitlist cargan automáticamente.
- aparecen candidatos de consultorio específico y `__all__`.
- tope visual de 5 candidatos.
- confirmación previa a assign.
- assign exitoso con waitlist en `confirmed`.
- cita creada con consultorio real (nunca `__all__`).
- sin `POST /appointments` accidental desde frontend durante assign.
- cerrar sin resolver conserva cancelación y no crea cita.
- strict operator:
  - operador activo permitido,
  - operador inválido bloqueado (`403` esperado).
- sin regresión en “Nueva cita” normal.
- sin regresión en “Buscar siguiente cita disponible” + waitlist MVP.

### 17.7 Deudas futuras
- Mejorar ranking/priorización de candidatos.
- Validar estado vacío en entorno con baja densidad de waitlist.
- Evolucionar a contenedor UX más completo (drawer o subtab de Lista de espera).
- Formalizar sentinel `__all__` hacia modelo canónico de `consultorio_scope`.

### 17.8 Mejora A1 de “Resolver hueco” (2026-05-23)

Estado: **implementado, validado y estabilizado**.

Commit:
- `b3d99a6` `feat(agenda): mejora ranking y estado vacio de resolver hueco`

Qué mejora A1:
- Ordena mejor la toma de decisión operativa al resolver huecos post-cancelación.
- Evita listas ambiguas y añade salidas claras cuando no hay candidatos.
- Refuerza mensajes de recuperación ante colisión de slot.

Ranking MVP (frontend):
- Primero candidatos con consultorio exacto del hueco liberado.
- Después candidatos con `consultorio_id="__all__"`.
- Desempate por prioridad en metadata (si existe): alta antes que normal.
- Luego por antigüedad (`created_at` más antiguo primero).
- Desempate final estable por `id`.
- Máximo visual: **5 candidatos**.

Etiquetas visuales agregadas:
- `Compatible con este consultorio`
- `Cualquier consultorio`
- `Prioridad alta` / `Prioridad normal` (solo si la metadata trae prioridad interpretable)

Estado vacío mejorado:
- Mensaje: `No hay pacientes compatibles en lista de espera para este hueco.`
- Acciones inline:
  - `Buscar siguiente cita disponible`
  - `Cerrar`

Colisión de assign:
- Mensaje extendido:
  - `El hueco ya no está disponible. La agenda pudo actualizarse antes de asignar.`
- Mantiene flujo recuperable sin dejar el modal en estado roto.

Telemetría mínima:
- Se extiende `logAgendaCancelFlow(...)` para registrar:
  - carga de candidatos (`total`, `exactCount`, `allCount`, `renderedCount`),
  - estado vacío,
  - colisión en assign,
  - éxito de assign (`waitlist_id`, `appointment_id` si está disponible).

QA final (A1 + cierre de pendiente Nueva cita):
- `Resolver hueco`: **PASS**.
- `Nueva cita` normal: **PASS** end-to-end.
- Confirmado en network para Nueva cita normal:
  - `POST /appointments` **sí**,
  - `POST /waitlist` **no** (sin alta accidental).
- Sin errores JS nuevos bloqueantes.
- `409` conocido en `patient-id/resolve`: no bloqueante en este ciclo.

Limpieza QA A1:
- Citas QA de la fase quedaron canceladas.
- Cita creada por assign (`dd65b7482dd6a6d98557be80`) cancelada con motivo `qa_cleanup`.
- Entrada waitlist confirmada (`181fb72108c652b38a599803`) **conservada** por trazabilidad histórica.

Deudas abiertas tras A1:
- Validar caso real con `__all__` cuando haya baja densidad de candidatos exactos.
- Prioridad en metadata aún no canónica (heurística frontend).
- Evaluar ranking backend/inteligente en fase posterior.
- Evolucionar contenedor UX a drawer/subtab completo de Lista de espera.

### 17.9 Hardening B1: integridad de `__all__` en assign waitlist (2026-05-23)

Estado: **implementado y validado**.

Commit:
- `2135d29` `fix(agenda): bloquea assign waitlist con consultorio sentinel all`

Semántica formal de `__all__`:
- `consultorio_id="__all__"` representa alcance de waitlist “cualquier consultorio”.
- Se permite en la **entrada de waitlist** como sentinel temporal de alcance.
- No se permite como consultorio destino al materializar una cita.

Regla de asignación (`POST /waitlist/{id}/assign`):
- Entry `__all__` + payload con consultorio real: **permitido**.
- Payload con `consultorio_id="__all__"`: **bloqueado**.
- Entry específica + payload con mismo consultorio: **permitido**.
- Entry específica + payload con otro consultorio: **bloqueado** (regla previa preservada).

Error formalizado para payload inválido:
- `error`: `invalid_consultorio_id`
- HTTP: `400`
- `message`: `consultorio_id must be a real consultorio for assignment`

QA resumido (B1):
- assign válido `__all__ -> consultorio real`: **PASS**.
- assign inválido `__all__ -> __all__`: **PASS** (bloqueado con `400`).
- entry específica + mismo consultorio: **PASS**.
- entry específica + otro consultorio: **PASS** (bloqueado).

Deuda futura asociada:
- Formalizar `consultorio_scope` canónico en schema/backend para sustituir sentinel `__all__` sin ambigüedad semántica.

### 17.10 B2-A: `consultorio_scope` compatible en waitlist (2026-05-23)

Estado: **implementado y validado**.

Commit:
- `d8f50c9` `feat(agenda): agrega consultorio_scope compatible en waitlist`

Qué es B2-A:
- Introduce `consultorio_scope` formal con transición compatible para waitlist.
- Mantiene `consultorio_id="__all__"` vigente para no romper flujos existentes.

Motivo:
- Reducir deuda semántica: separar alcance (`all|single`) del identificador de consultorio.
- Permitir migración gradual sin bloquear operaciones en ambientes con schema mixto.

Modelo transicional:
- Cualquier consultorio:
  - `consultorio_scope="all"`
  - `consultorio_id="__all__"` (sentinel de compatibilidad)
- Consultorio específico:
  - `consultorio_scope="single"`
  - `consultorio_id=<id_real>`

Compatibilidad y fallback:
- `__all__` sigue siendo válido.
- Si la columna `consultorio_scope` no existe, backend deriva scope desde `consultorio_id`.
- Regla defensiva de transición:
  - si `consultorio_id="__all__"` y `consultorio_scope` llega inconsistente (`single`/vacío), **gana `__all__`** y se interpreta como `all`.

Reglas por operación:
- Create (`POST /waitlist`):
  - normaliza `consultorio_scope`;
  - en modo all conserva `consultorio_id="__all__"` por compatibilidad.
- List (`GET /waitlist?consultorio_id={real}`):
  - incluye entries exactas,
  - incluye entries sentinel `__all__`,
  - e incluye entries con `consultorio_scope="all"` cuando columna existe.
- Assign (`POST /waitlist/{id}/assign`):
  - permite entry all -> consultorio real;
  - mantiene bloqueo de destino `consultorio_id="__all__"` con `400 invalid_consultorio_id`.

Migración recomendada para ambientes existentes:
```sql
ALTER TABLE agenda_waitlist_entries
  ADD COLUMN consultorio_scope VARCHAR(16) NOT NULL DEFAULT 'single';

UPDATE agenda_waitlist_entries
SET consultorio_scope = 'all'
WHERE consultorio_id = '__all__';

UPDATE agenda_waitlist_entries
SET consultorio_scope = 'single'
WHERE consultorio_id <> '__all__'
  AND (consultorio_scope IS NULL OR consultorio_scope = '' OR consultorio_scope <> 'single');
```

QA resumido (B2-A):
- create all: **PASS**.
- create single: **PASS**.
- GET por consultorio específico incluye all: **PASS**.
- assign entry all -> consultorio real: **PASS**.
- assign destino `__all__`: **PASS** (bloqueado).
- entry single conserva regla de mismatch por consultorio: **PASS**.

Deuda futura:
- Ejecutar migración real en ambientes existentes.
- Evaluar retiro gradual o encapsulación completa del sentinel `__all__`.
- Evolucionar reportes/filtros para uso canónico de `consultorio_scope`.

## 18. Adenda B3-A: contrato de transiciones de cita (2026-05-24)

Estado: **documentado (sin enforcement nuevo todavía)**.

Objetivo:
- Formalizar la matriz permitida/denegada para transiciones críticas de cita:
  - cancelar,
  - reprogramar,
  - marcar no_show.
- Mantener comportamiento actual en runtime mientras se prepara hardening por fases.

### 18.1 Alcance y reglas base

- Este contrato **no cambia** aún el comportamiento de endpoints.
- Se preserva F3.3C (strict operator enforcement y actorContext vigente).
- El contrato define:
  - estados de cita reconocidos;
  - temporalidad canónica;
  - transiciones permitidas/denegadas;
  - códigos de error objetivo para enforcement futuro.

### 18.2 Estados de cita detectados (canon operativo)

- `pending`
- `pending_otp`
- `tentative`
- `confirmed`
- `scheduled`
- `rescheduled`
- `canceled` / `cancelled`
- `no_show`
- `in_progress` / `in_consulta`
- `finished` / `finalizada`
- `completed`

Notas:
- Existen valores legacy/sinónimos en frontend y eventos; el contrato usa el canon anterior para decisiones de transición.
- `canceled` y `cancelled` se consideran equivalentes de estado terminal cancelado.

### 18.3 Temporalidad canónica

- `futura`: `now < start_at`
- `en curso`: `start_at <= now < end_at`
- `pasada`: `end_at <= now`
- zona horaria canónica: `America/Mexico_City`
- tolerancia: **sin tolerancia adicional por ahora** (0 minutos)

### 18.4 Matriz: cancelar cita

Permitido:
- `pending` / `tentative` / `confirmed` / `scheduled` / `rescheduled` cuando la cita es `futura`.

Idempotente:
- `canceled` / `cancelled` -> `appointment_already_canceled`.

Denegado:
- `no_show` / `finished` / `completed`.
- cita `en curso` o `pasada`.

Errores sugeridos:
- `invalid_transition`
- `appointment_already_canceled`
- `appointment_past_not_cancellable`

### 18.5 Matriz: reprogramar cita

Permitido:
- `pending` / `tentative` / `confirmed` / `scheduled` / `rescheduled` cuando la cita es `futura` y el slot destino es válido.

Denegado:
- `canceled` / `cancelled`
- `no_show`
- `finished` / `completed`
- cita `en curso` o `pasada`
- colisión o fuera de horario

Errores sugeridos:
- `invalid_transition`
- `appointment_past_not_reschedulable`
- `slot_conflict`
- `outside_schedule`
- `slot_unavailable`

### 18.6 Matriz: no_show

Permitido:
- cita `en curso` o `pasada` con estado no terminal.

Idempotente:
- ya `no_show` -> `appointment_already_no_show`.

Denegado:
- cita `futura`.
- `canceled` / `cancelled`.
- `finished` / `completed`.

Errores sugeridos:
- `appointment_future_not_no_show`
- `appointment_already_no_show`
- `invalid_transition`

### 18.7 Actor / strict (preservado)

- `doctor` en `strict`: permitido dentro de su scope efectivo.
- `operator` activo en `strict`: permitido.
- `operator` inválido en `strict`: bloqueado antes del controller por guard de identidad.
- `compat`: se preserva comportamiento compatible vigente (sin hardening nuevo por esta adenda).
- `qa_override`: se preserva en su rol actual de compatibilidad/observación.
- Esta adenda **no modifica** F3.3C ni su semántica de enforcement.

### 18.8 Contrato de errores objetivo (enforcement futuro)

| error_code | HTTP esperado | message corto sugerido | metadata segura sugerida |
| --- | --- | --- | --- |
| `invalid_transition` | 409 | `invalid status transition` | `action`, `current_status` |
| `appointment_already_canceled` | 200 (idempotente) | `already canceled` | `appointment_id`, `status` |
| `appointment_already_no_show` | 200 (idempotente) | `already no_show` | `appointment_id`, `status` |
| `appointment_past_not_reschedulable` | 409 | `appointment cannot be rescheduled after start` | `start_at`, `now_at` |
| `appointment_past_not_cancellable` | 409 | `appointment cannot be canceled after start` | `start_at`, `now_at` |
| `appointment_future_not_no_show` | 409 | `appointment cannot be marked no_show before end` | `end_at`, `now_at` |
| `slot_conflict` | 409 | `collision detected` | `doctor_id`, `consultorio_id`, `date` |
| `outside_schedule` | 409 | `outside schedule` | `doctor_id`, `consultorio_id`, `date` |
| `slot_unavailable` | 409 | `slot unavailable` | `doctor_id`, `consultorio_id`, `date` |
| `forbidden` | 403 | `forbidden` | sin fuga de datos sensibles cruzados |
| `not_found` | 404 | `appointment not found` | `appointment_id` |

Nota de implementación:
- Antes de enforcement real, actualizar `statusMap` de `api/agenda/index.php` para evitar que códigos nuevos caigan en HTTP `200` por default.

### 18.9 Riesgos de hardening

- romper compatibilidad de QA/histórico si se endurece sin fase observacional.
- afectar flujo post-cancelación (`Resolver hueco`) si cancelación se bloquea en casos hoy permitidos.
- afectar reprogramación entre consultorios en casos válidos limítrofes.
- afectar señalización `no_show` y flags si temporalidad se aplica sin transición gradual.
- afectar UX si frontend no mapea mensajes/códigos nuevos de forma explícita.

### 18.10 Plan por fases (B3)

- **B3-A**: contrato documental (esta adenda).
- **B3-B**: dry-run/observacional sin bloqueo (telemetría + warnings/meta).
- **B3-C**: enforcement real controlado por feature flag y validación QA.
