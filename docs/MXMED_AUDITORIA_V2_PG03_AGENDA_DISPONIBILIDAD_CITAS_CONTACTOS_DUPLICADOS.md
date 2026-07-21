# PRODUCT-AUDIT/MXMed-PG03-Agenda-Availability-Appointments-PatientContacts-Duplicates-Activity07-01

## Resultado

`PASS_ACTIVITY_7_PG03_AUDIT_READY_FOR_DIRECTOR_DECISIONS`

## Resumen ejecutivo

La Actividad 7/22 queda completa como auditoría UI-0 y lista para decisiones del director. No se implementaron correcciones, no se ejecutó SQL, no se escribieron datos, no se solicitaron OTP reales, no se crearon citas, no se fusionaron pacientes y no se levantó un runtime candidato.

Hallazgos confirmados: la ruta Agenda admite autoridad de rol desde cliente en compatibilidad; los controladores aplican scopes de doctor pero no prueban una cuenta/membership canónica en todas las rutas; horarios, overrides, feriados y colisiones son fuentes separadas; la transición de citas es código de aplicación con estados no registrados como máquina; el flujo público conserva PII y crea tablas durante runtime; el modelo de pacientes contiene contactos y enlaces por doctor, pero no tiene deduplicación ni merge; la gestión de schema durante runtime permanece un blocker. Las decisiones DEC-013A–L son propuestas, no aprobaciones.

## Baseline

- Programa: `program/mxmed-product-refinement-22-v2`.
- HEAD oficial: `6fac128e77472d4fafab7088a82c3a4df15c5761`.
- Checkpoint de referencia: `checkpoint/mxmed-product-refinement-v2-activity06`.
- Rama: `audit/mxmed-agenda-availability-appointments-contacts-duplicates-v2`.
- Worktree: `/Users/circulodigital/Documents/GitHub/mxmed-esqueleto-visual-activity07-v2`.
- Inicio: cero commits sobre el programa, upstream 0/0, worktree limpio, HTTP 8091=200 y puertos 8150/8151/6385 libres.
- El baseline global contractual de schema post-Gate6F se conserva como 20 archivos y máximo contractual 21; el detector específico de Agenda encuentra 23 archivos productivos con DDL o guardas `ensureTable`/equivalentes, por lo que permanece un blocker de migración diferida y requiere reconciliación antes de despliegue.

## Superficies

El entrypoint `api/agenda/index.php` carga 16 controladores, `OperatorsRepository` y la capa PDO; clasifica `public` frente a familias privadas, normaliza respuestas y asigna códigos 401/403/409/503. Las superficies Agenda son: citas y eventos; pacientes flags/behavior; consultorios; geocode; availability y blocks; schedule; settings; agenda pública, OTP, reservas, confirmación, cancelación y expiración; medical groups; waitlist; operators.

Los módulos relacionados son `modules/patients` (identidad, perfiles, contactos, domicilios, consentimientos y enlaces doctor-paciente), `modules/clinical` (encounters, casos, records y consents con `patient_id`) y las UIs de Agenda/Perfil/Clinical que consumen `appointment_id`, `patient_id`, `doctor_id` o contactos. No existe `modules/agenda/contracts`; el contrato observado está distribuido entre entrypoint, controladores, repositorios, SQL y QA.

Rutas privadas observadas: `GET /appointments`, `GET /appointments/{id}`, `GET /appointments/{id}/events`, `POST /appointments`, `PATCH /appointments/{id}/reschedule`, `POST /appointments/{id}/cancel`, `POST /appointments/{id}/confirm`, `POST /appointments/{id}/no_show`; `GET /patients/{id}/flags`, `GET /patients/{id}/behavior`; `GET/PUT /consultorios`; `POST /geocode/google`, `GET /geocode/google-js-config`; `GET /availability`, `GET/POST /availability/blocks`, `PATCH /availability/blocks/{id}`; `GET/PUT /schedule`; `GET/PUT /settings`; `GET/POST/PATCH /waitlist`; `GET/POST/PATCH /operators` y migración; `GET/POST medical-groups` con join/approve/reject/merge.

Rutas públicas: `GET /public/availability`, `POST /public/otp/request`, `POST /public/otp/verify`, `POST /public/appointments/request`, `POST /public/appointments/verify`, `POST /public/appointments/reserve`, `POST /public/appointments/confirm`, `POST /public/appointments/cancel`, `POST /public/maintenance/expire`.

Rutas de pacientes: `GET /api/patients/index.php/patients/{id}`, `GET /doctors/{doctor_id}/patients`, `GET /doctors/{doctor_id}/patients/search`, `GET/PUT /doctors/{doctor_id}/patients/{patient_id}/contacts/editable`, `POST /patients`, `POST /patients/{id}/address`, `POST /patients/{id}/profile`. El entrypoint de pacientes no inyecta sesión/membership de Agenda; la autorización observada del contacto depende del enlace doctor-paciente.

## Autoridad

`resolveAgendaActorRole()` prioriza `X-Actor-Role`, después `X-User-Role`, sesión, body, query y finalmente `doctor`. `resolveAgendaActorContext()` permite compatibilidad sin usuario (`compat_dev`), usa `doctor_id` de sesión o query, y sólo declara `is_authoritative=true` en strict mode con role proveniente de sesión. `QA_MODE` puede habilitar override de header/query/body en compatibilidad.

Por ello, las rutas privadas son `partially_server_authoritative` en strict mode y `client_authoritative`/`fallback_authoritative` en compatibilidad. Los controladores comparan doctor scope con la entidad afectada en citas, disponibilidad, consultorios, schedule, settings y waitlist; no se observa validación homogénea de account, membership, ownership y capability para cada ruta. `operator_id` se comprueba contra `agenda_operators` cuando el rol es operator, pero el rol efectivo aún puede provenir del cliente. La ausencia de contexto falla cerrada sólo con `MXMED_AGENDA_AUTH_REQUIRED`/strict; por defecto queda compatibilidad abierta. Las rutas públicas son `unauthenticated_public` por diseño y no deben heredar headers de actor como autoridad.

## Disponibilidad

La fuente base es el primer esquema existente entre `consultorio_schedule`, `consultorio_schedules`, `consultorio_horarios`, `consultorio_horarios_base` y `agenda_consultorio_schedule`, filtrado por `doctor_id`, `consultorio_id` y weekday. La zona horaria es `America/Mexico_City`. `slot_minutes` es parámetro de consulta (5–720); Agenda Settings guarda duración y gap, pero la proyección no demuestra una única lectura canónica de esos valores.

La proyección combina horario base (A), overrides `open/close` de `agenda_availability_overrides` (C), feriados de `HolidayMxProvider`, deduplicación de ventanas y sustracción de intervalos ocupados desde `agenda_appointments`. La agenda pública escanea hasta 90 días para “next” y hasta cuatro semanas (`week_offset` 0–3), elige consultorio solicitado o el primero con disponibilidad. Hay riesgo de que el consultorio cambiado, múltiples consultorios y el fallback de catálogo produzcan una proyección distinta de la configuración editorial; no existe una fuente canónica explícita de reservas temporales.

Los bloqueos parciales y de día completo se persisten como overrides y rechazan conflictos detectados con citas, pero la operación de bloqueo y la lectura de disponibilidad no comparten una transacción. La doble lectura antes de escritura deja una ventana de carrera; el índice `uniq_active_slot` en algunos SQL ayuda sólo cuando está instalado y coincide con la tabla real.

## Citas

El ciclo efectivo observado es `tentative`/`pending_otp` → `confirmed` → `canceled` o `no_show`; también existe reprogramación, waitlist assignment y bridge opcional a Clinical. `AppointmentWriteRepository` envuelve insert/evento y las transiciones principales en transacciones; `AppointmentEventsRepository` conserva historial. Confirmar, cancelar y no-show tienen idempotencia parcial por estado/evento; reprogramar actualiza fechas y puede resetear `confirmed` a `tentative`. No hay catálogo versionado de estados ni matriz de transiciones autorizada en un contrato único; llegada, atención, cierre, restauración/reapertura y vínculo clínico no forman estados Agenda demostrados.

La creación privada genera `appointment_id`, inserta cita y evento, y puede crear paciente si falta `patient_id`; eso expone una bifurcación de identidad. La validación de slot ocurre antes de insertar, mientras la colisión definitiva depende de índice/errores SQL. Hay transacciones locales y algunos `FOR UPDATE` en flujos públicos, pero no existe una política de idempotency key/correlation key para reintentos HTTP. El bridge clínico puede ejecutar un POST posterior para encuentros cuando la cita está completada; no se garantiza outbox ni compensación si el bridge falla.

## Agenda pública y OTP

Hay dos familias públicas coexistentes. `PublicOtpController` guarda hash, contacto, expiración de 600 segundos, contador máximo de cinco intentos y marca verificado; `PublicAppointmentsController` mantiene otra tabla OTP/flow, genera reservas `pending_otp`, usa `cancel_token`, confirma/cancela y expira flows. Se resuelve doctor canónico y alias legacy para almacenamiento numérico.

Hallazgos de riesgo: no se observa rate limit por IP/contacto/doctor ni anti-enumeración uniforme; `QA_MODE` puede devolver código debug y `DevOtpSender` escribe el OTP en `error_log`; los flows guardan payload JSON completo incluyendo datos del paciente/booker; `ensureOtpTable()` y `ensureFlowTable()` contienen CREATE TABLE en request; `expirePendingReservations()` muta tablas desde rutas públicas; la ruta `request` y `verify` no comparten una identidad canónica de paciente; la reserva pública busca coincidencias sólo por teléfono normalizado o correo lower-case y puede crear paciente automáticamente; no hay consentimiento versionado ligado al flujo. No se ejecutó ninguna solicitud OTP real.

## Contactos

La fuente de contactos privados es `patients_contacts` (phone, email, preferred_contact_method, is_primary, timestamps), con `patients_addresses`, perfiles y `patients_consents` separados. El alta acepta hasta cinco entradas phone/email, normaliza nombre y devuelve contactos enmascarados; la edición doctor-paciente sólo permite teléfono primario y normaliza números MX a `+52`. Search acepta nombre, teléfono y correo y redacta la consulta en meta; los endpoints de contactos declaran `editable_private`.

No hay entidad explícita para WhatsApp: aparece como preferencia de contacto y como canal de notificación. No se observa tabla de contacto de emergencia/representante ni procedencia/consentimiento por dato, historial de cambios, borrado o retención. El contacto público del médico vive en perfiles/consultorios, mientras el contacto del paciente vive en pacientes; la separación física existe, pero la agenda pública copia PII a OTP/flow y a `payload_json`, por lo que la separación de visibilidad no está completa.

## Identidad y duplicados

`patients_patients.patient_id` es la identidad canónica local; `patients_doctor_links` acota la relación doctor-paciente y Clinical usa `patient_id` con restricciones `ON DELETE RESTRICT`. Existen alias legacy para `doctor_id`, no para `patient_id`. La búsqueda usa nombre tokenizado, teléfono/dígitos y correo; el flujo público compara teléfono normalizado o correo normalizado para reutilizar el primer paciente enlazado.

No se encontró deduplicación real, advertencia de coincidencia, tabla de alias de pacientes, scoring con fecha de nacimiento/sexo/CURP, merge reversible, undo, mapa de referencias o auditoría de merge. Tampoco se observó CURP en el esquema de pacientes. Crear automáticamente un paciente desde cita o reserva puede producir duplicados exactos/probables y falsos positivos por datos compartidos. Citas, encounters, casos, documentos, consentimientos y suscripciones no tienen un protocolo de reasignación de referencias.

## Runtime schema

El detector estático encontró 23 archivos PHP de Agenda con DDL literal o guardas `ensureTable`/`tableExists` ejecutables desde controladores/repositorios/helpers: `AppointmentWriteController`, `AvailabilityController`, `PublicAppointmentsController`, `doctor_identity.php`, `AgendaSettingsRepository`, `AppointmentCollisionsRepository`, `AppointmentEventsRepository`, `AppointmentWriteRepository`, `AppointmentsRepository`, `AvailabilityRepository`, `ConsultoriosRepository`, `MedicalGroupMembershipsRepository`, `MedicalGroupReviewLogRepository`, `MedicalGroupsRepository`, `OperatorAuditRepository`, `OperatorsRepository`, `OverrideRepository`, `PatientBehaviorRepository`, `PatientFlagsRepository`, `PatientFlagsWriteRepository`, `PatientIncidentsWriteRepository`, `ScheduleRepository` y `WaitlistRepository`. Los DDL explícitos aparecen al menos en `doctor_identity.php`, `PublicAppointmentsController`, `AgendaSettingsRepository`, `MedicalGroupsRepository`, `MedicalGroupReviewLogRepository`, `MedicalGroupMembershipsRepository` y `ConsultoriosRepository`; el resto realiza comprobaciones de existencia durante lectura/escritura.

Esto excede el máximo contractual 21 y conserva `runtime_schema_blocker`. La remediación propuesta es DEC-013I: migraciones/rollback fuera de request, ledger de versión, preflight y cero `CREATE/ALTER` desde GET/POST de negocio. No se ejecutó ninguno.

## Privacidad y retención

Fuentes canónicas identificadas: `patients_patients`, `patients_profiles`, `patients_contacts`, `patients_addresses`, `patients_consents`, `patients_doctor_links`; `agenda_appointments`, `agenda_appointment_events`, `agenda_public_otp_requests`, `agenda_public_appointment_flows`, waitlist, flags/incidents; Clinical tables referenciadas por `patient_id`; perfiles/consultorios para contacto público médico. Copias y proyecciones: eventos con actor/notes, logs de OTP, `payload_json` de flows, respuestas de API y bridge clínico.

No se observa política de retención por tabla, legal hold, anonimización, exportación o disposición. Los logs pueden contener OTP y metadatos de contacto, aunque `DevOtpSender` enmascara el destinatario; `error_log` no constituye audit trail PG-08. Clinical usa `ON DELETE RESTRICT`, por lo que no debe borrarse físicamente una identidad con expediente. Agenda y contactos requieren retención diferenciada, minimización de payload público y disposición auditable.

## Auditoría

Las citas generan `agenda_appointment_events`; operators tienen `agenda_operator_audit_events`; waitlist conserva una envoltura JSON en `notes`; cancelación/no-show pueden agregar flags/incidents. El contexto incluye actor real/efectivo, role, id, channel y warnings, pero no existe correlation/request ID obligatorio ni un trail PG-08 conectado. `error_log` aparece en no-show y OTP y no permite atribución fuerte. Faltan eventos mínimos para lectura sensible, contacto editado, cambio de ownership, merge/undo, disponibilidad bloqueada/desbloqueada, OTP abuse, expiración pública y disposición.

## QA

Existe `modules/agenda/QA.md`, `requests.sh` y scripts públicos de availability, OTP, booking, cancel y expire. Cubren contratos JSON, tablas ausentes, estados básicos y flujos públicos, pero los scripts públicos escriben datos, dependen de tablas/servidor y exponen debug QA; no fueron ejecutados. No hay suite read-only aislada para autoridad server-side, duplicados, merge, privacidad, retención o concurrencia. Se ejecutaron sólo lint/validaciones estáticas y checks de baseline descritos en la evidencia; se omitieron `IdentityPersistenceTest`, seeds, migraciones, fixtures, OTP/citas reales y merges por contrato.

## Propuestas DEC-013

DEC-013A–L se documentan en el documento de decisiones adjunto. Todas tienen estado `PENDING_DIRECTOR_APPROVAL`; ninguna se presenta como aprobada. La secuencia propuesta para Actividad 8 es: primero autoridad y fuentes canónicas; después máquina de citas/idempotencia; luego público/OTP y contactos; luego identidad/deduplicación/merge; finalmente migraciones, auditoría, retención y rollout.

## Evidencia

La carpeta `/tmp/mxmed-activity07-pg03-agenda-audit-v2/` contiene los 28 JSON y cuatro textos exigidos. No contiene datos personales, clínicos, teléfonos/correos reales, OTP, cookies, tokens, credenciales, DSN ni secretos. `changed-files-audit.json` y `pp-number-audit.json` demuestran el alcance de tres archivos versionados y una sola entrada PP-302.

## Git

La rama parte exactamente de `6fac128e77472d4fafab7088a82c3a4df15c5761`, queda con un solo commit documental sobre el programa, upstream 0/0 y worktree limpio. El commit requerido es `docs(product): audita agenda y duplicados PG03 actividad 7`. No se hace amend, rebase, squash, force push, PR, integración ni checkpoint.

## No repetición

PP-302 es la única entrada formal nueva de esta actividad y aparece una sola vez en el Plan Maestro. Las referencias en este documento y en la evidencia son narrativas y no crean entradas adicionales. PP-301 permanece sin modificar; la decisión de readiness de Gate 6F continúa `NO_GO_LEGACY_BLOCKERS_PRESENT`.

## Estado del programa

- Actividad 7:
  AUDIT_COMPLETE_PENDING_DIRECTOR_DECISIONS;

- Actividad 8:
  BLOCKED;

- contador oficial:
  6/22;

- pendientes:
  16;

- readiness productivo:
  NO_GO_LEGACY_BLOCKERS_PRESENT.

No integrar.
No crear checkpoint.
No iniciar Actividad 8.
