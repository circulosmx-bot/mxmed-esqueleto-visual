# MXMed V2 · PG-03 · Auditoría de readiness de cutover runtime

## 1. Resumen ejecutivo

Resultado: `PASS_ACTIVITY_9_PG03_RUNTIME_CUTOVER_READINESS_AUDIT_COMPLETE`.

Se confrontaron los contratos postvalidados de Gates 8A–8G con el runtime legacy de Agenda y Patients mediante inspección estática, sin abrir conexiones de base de datos ni invocar rutas con escritura. Se verificaron 58 rutas (49 privadas y 9 públicas) y 30 categorías. El resultado es `NO_GO_BLOCKERS_PRESENT`: 13 BLOCKER, 11 HIGH, 4 MEDIUM, 1 LOW y 1 INFORMATIONAL. Hay 18 decisiones directoriales pendientes.

Las causas dominantes son: autoridad legacy híbrida que aún acepta claims cliente; falta de frontera server-side en Patients; contratos canónicos sin wiring; idempotencia de Gate 8D no persistida; OTP legacy que puede aparecer en logs/debug; DDL ejecutable durante requests; y Gate 8G sin adaptador, tablas activas ni rollout. El readiness general sigue `NO_GO_LEGACY_BLOCKERS_PRESENT`.

## 2. Contrato aprobado

Identificador `AUDIT/MXMed-PG03-Runtime-Cutover-Readiness-01`, UI-0, aprobado por dirección el 2026-07-22. Autoriza documentación y PP-311, no adapters, wiring, SQL, migraciones, backfill, datos, OTP, citas, pacientes, merges, cambios de rutas, UI, rollout R1–R4 ni AWS. Fuente: `/tmp/mxmed-activity09-runtime-cutover-readiness-preflight-v2/activity09-approved-contract.txt:1` y `/tmp/mxmed-activity09-runtime-cutover-readiness-preflight-v2/director-approval.txt:1`.

## 3. Baseline

- Parent: `4072dff286bcf0de05e845f4eb9cf354c059b028`.
- Programa: `program/mxmed-product-refinement-22-v2`.
- Checkpoint Activity 8: tag anotado `checkpoint/mxmed-product-refinement-v2-activity08`, cuyo objeto commit es el baseline.
- Bundle preflight válido y completo; worktree inicial limpio, upstream 0/0 y sin `REVERT_HEAD`.
- PP-310 inicial único; PP-311 ausente.
- 8091 respondió HTTP 200; 8150, 8151 y 6385 estaban libres.

La fuente vinculante de baseline es `/tmp/mxmed-activity09-runtime-cutover-readiness-preflight-v2/program-baseline.txt:1`.

## 4. Metodología

Se siguieron entrypoints, `require_once`, construcción de controllers, servicios y repositorios; después se rastrearon SQL estáticos, tablas, transacciones, locks, claims, sesión, fallbacks, efectos laterales y llamadas frontend. `VERIFIED` significa evidencia directa; `INFERRED_WITH_EVIDENCE`, conclusión apoyada sin ejecución; `UNRESOLVED`, dato que exigiría DB/telemetría; `NOT_APPLICABLE`, ausencia justificada. No se atribuye actividad a un archivo sólo por existir.

## 5. Fuentes

Se auditaron los árboles canónicos `modules/agenda/{contracts,security,availability,appointments,publicflow}/`, `modules/patients/identity/` y `modules/patients/identity/persistence/`, sus siete pruebas Gate y documentos. Del runtime se auditaron ambos routers, UI Agenda, controllers, services, repositories, config, schemas y migraciones declarativas; el shell y JavaScript alcanzado; y los mapas legacy requeridos. El inventario con SHA-256, clasificación, actividad y motivo está en `/tmp/mxmed-activity09-runtime-cutover-readiness-audit-v2/source-manifest.txt:1`.

## 6. Inventario canónico

| Gate | Autoridad canónica | Estado runtime |
|---|---|---|
| 8A | autoridad server-side, disponibilidad calculada, lifecycle, idempotencia, OTP, identidad, auditoría, retención y rollout | contratos puros; el documento niega wiring (`docs/MXMED_IMPLEMENTACION_V2_PG03_GATE_8A_CONTRATOS_CANONICOS.md:86`) |
| 8B | `AgendaActorAuthorityResolver`, Identity + membership + ownership + Gate 6B | no aparece instanciado fuera de pruebas; legacy sigue pendiente (`docs/MXMED_IMPLEMENTACION_V2_PG03_GATE_8B_AUTORIDAD_SERVER_SIDE.md:168`) |
| 8C | schedule versionado por profile/consultorio y disponibilidad determinista | dominio no conectado (`docs/MXMED_IMPLEMENTACION_V2_PG03_GATE_8C_HORARIO_DISPONIBILIDAD_CANONICOS.md:106`) |
| 8D | lifecycle versionado, idempotency key/fingerprint, claims y plan transaccional | sin tabla/adapter runtime de idempotencia (`docs/MXMED_IMPLEMENTACION_V2_PG03_GATE_8D_CICLO_CITAS_IDEMPOTENCIA_CONCURRENCIA.md:266`) |
| 8E | intención, challenge hash-only, grant opaco y handoff | dominio puro no conectado (`docs/MXMED_IMPLEMENTACION_V2_PG03_GATE_8E_AGENDA_PUBLICA_OTP_PRIVACIDAD_CONTACTO.md:141`) |
| 8F | resolución exacta/ambigua, revisión humana, no-merge | dominio puro no conectado (`docs/MXMED_IMPLEMENTACION_V2_PG03_GATE_8F_IDENTIDAD_PACIENTE_DUPLICADOS.md:173`) |
| 8G | port, manifest, cuatro tablas, retención, backfill, rollout R0 | declarativo; ejecución y writes false (`modules/patients/identity/persistence/PatientIdentityRolloutPolicy.php:8`) |

Hash normalizado PP-310: `c3c0339ad05b127b08288f3a026f2122f9af130061369db2c1a4c0c8d4a17459`. Hash normalizado PP-311: `e36d72058cd054d784d91983f1fbc3f7f0ceddb907c65ef9889ecd82d4c8b147`.

## 7. Inventario runtime

`api/agenda/index.php` es entrypoint activo: carga 16 controllers y `OperatorsRepository` (`api/agenda/index.php:2`), inicia sesión (`api/agenda/index.php:39`) y enruta por segmentos (`api/agenda/index.php:907`). `api/patients/index.php` carga ocho controllers (`api/patients/index.php:4`) y enruta sin iniciar ni validar sesión (`api/patients/index.php:27`). `modules/agenda/routes.php` es inventario parcial/documental: omite la mayoría de rutas realmente alcanzadas por el switch.

Los controllers construyen PDO y repositorios; `AgendaService` es placeholder (`modules/agenda/services/AgendaService.php:4`). Los repositorios Agenda y Patients son dependencias activas. `modules/agenda/db/*.sql`, `modules/agenda/sql/ready_schema.sql`, `modules/patients/db/ready_schema.sql` y migraciones 8G son declarativos, pero cinco repositorios/controllers contienen además auto-bootstrap DDL activo.

## 8. Matriz de rutas

La matriz JSON contiene método, path, visibilidad, router, controller, service, repository, actor, claims, scopes, tablas, efectos, auditoría, idempotencia, concurrencia, contrato, gap, severidad, estado y bloqueo. Evidencia router Agenda: `api/agenda/index.php:1037`; Patients: `api/patients/index.php:77`.

Rutas privadas Agenda verificadas (41):

- `GET /appointments`, `POST /appointments`, `GET /appointments/{id}`, `GET /appointments/{id}/events`, `PATCH /appointments/{id}/reschedule`, `POST /appointments/{id}/cancel`, `POST /appointments/{id}/confirm`, `POST /appointments/{id}/no_show` (`api/agenda/index.php:1038`).
- `GET /patients/{id}/flags`, `GET /patients/{id}/behavior` (`api/agenda/index.php:1102`).
- `GET|PUT /consultorios`; `POST /geocode/google`; `GET /geocode/google-js-config` (`api/agenda/index.php:1125`).
- `GET /availability`, `GET|POST /availability/blocks`, `PATCH /availability/blocks/{id}` (`api/agenda/index.php:1163`).
- `GET|PUT /schedule`; `GET|PUT /settings` (`api/agenda/index.php:1193`).
- `GET /medical-groups/search`, `POST /medical-groups`, `GET /medical-groups/pending`, y `POST /medical-groups/{id}/{join|approve|reject|merge}` (`api/agenda/index.php:1269`). Este merge es de grupos médicos, no de pacientes.
- `GET|POST /waitlist`, `PATCH /waitlist/{id}`, `POST /waitlist/{id}/assign` (`api/agenda/index.php:1298`).
- `GET|POST /operators`, `POST /operators/migration/{preview|apply}`, `PATCH /operators/{id}/{pause|reactivate|archive|restore}` (`api/agenda/index.php:1323`).

Rutas Patients tratadas como privadas por su contenido (8), aunque el router no impone autenticación:

- `GET /patients/{id}`, `GET /doctors/{doctor}/patients/{patient}/contacts/editable`, `GET /doctors/{doctor}/patients/search`, `GET /doctors/{doctor}/patients` (`api/patients/index.php:77`).
- `POST /patients`, `POST /patients/{id}/address`, `POST /patients/{id}/profile`, `PUT /doctors/{doctor}/patients/{patient}/contacts/editable` (`api/patients/index.php:93`).

Rutas públicas (9): `GET /public/availability`, `POST /public/otp/{request|verify}`, `POST /public/maintenance/expire`, y `POST /public/appointments/{reserve|confirm|cancel|request|verify}` (`api/agenda/index.php:1231`). La maintenance pública y los dos flows OTP superpuestos requieren contención antes de R1.

Rutas dev/QA dentro del inventario: `/public/maintenance/expire`, `/operators/migration/preview`, `/operators/migration/apply`; el debug OTP se expone por las rutas normales si QA mode está habilitado (`modules/agenda/controllers/PublicOtpController.php:123`). No se confirmaron rutas adicionales fuera de ambos switches.

## 9. Matriz contrato-runtime y hallazgos

Cada fila incluye ID; categoría; severidad; comprobación; contrato; legacy; evidencia; flujo; riesgo; R1; recomendación; gate; decisión.

| ID | Categoría | Sev. | Estado | Contrato / legacy y evidencia | Flujo y riesgo | R1 | Recomendación | Gate | Dec. |
|---|---|---|---|---|---|---|---|---|---|
| F-001 | ACTOR_AUTHORITY | BLOCKER | VERIFIED | 8B exige contexto server-side; legacy prioriza `X-Actor-Role`/`X-User-Role` (`api/agenda/index.php:206`) y no instancia el resolver | todas privadas; suplantación | sí | adapter shadow del resolver, fail-closed | CUT-01 | D-15 |
| F-002 | CLIENT_TRUST | BLOCKER | VERIFIED | claims no autoritativos; legacy acepta body/query role e IDs (`api/agenda/index.php:229`, `api/agenda/index.php:488`) | writes Agenda; atribución falsa | sí | ignorar claims para autoridad y conservarlos sólo como diagnóstico | CUT-01 | D-15 |
| F-003 | OPERATOR_BINDING | HIGH | VERIFIED | 8B exige membership; legacy consulta operator pero expone `operator_identity_db_not_ready` y guard por rutas (`api/agenda/index.php:547`, `api/agenda/index.php:649`) | operador; dependencia DB/fail-closed desigual | sí | unificar binding vía 8B y validar sesión UI real | CUT-01 | D-09 |
| F-004 | AUTHORIZATION_BOUNDARY | BLOCKER | VERIFIED | Gate 6B/8B; Patients no inicia sesión ni autoriza (`api/patients/index.php:25`) | 8 rutas Patients; lectura/escritura no acotada | sí | composition root server-side antes de controllers | CUT-01 | D-15 |
| F-005 | PROFILE_AND_CONSULTORIO_SCOPE | HIGH | VERIFIED | scope exacto; compat usa doctor query y `compat_dev` (`api/agenda/index.php:265`, `api/agenda/index.php:308`) | Agenda privada; acceso cruzado | sí | resolver profile/consultorio desde membership y ownership | CUT-01 | D-03 |
| F-006 | SCHEDULE_SOURCE | BLOCKER | VERIFIED | 8C versionado; legacy descubre cinco nombres de tabla (`modules/agenda/repositories/ScheduleRepository.php:13`) y UI conserva `mxmed_cons_schedules` (`assets/js/app.js:32091`) | Semana/Día; dos fuentes | sí | shadow adapter y regla explícita de precedencia | CUT-01 | D-02 |
| F-007 | AVAILABILITY_PROJECTION | HIGH | VERIFIED | 8C determinista; legacy compone schedule, overrides, holiday y collisions (`modules/agenda/controllers/AvailabilityController.php:187`) sin versión canónica | slots público/privado; divergencia | sí | compare por slot/scope/timezone antes de autoridad | CUT-02 | D-10 |
| F-008 | APPOINTMENT_LIFECYCLE | BLOCKER | VERIFIED | 8D catálogo; runtime usa guard híbrido y estados extra como `rescheduled` (`modules/agenda/repositories/AppointmentWriteRepository.php:1048`) | writes de cita; transiciones divergentes | sí | adapter al machine, primero shadow | CUT-01 | D-15 |
| F-009 | IDEMPOTENCY | BLOCKER | VERIFIED | 8D key/fingerprint; no hay header, tabla ni port runtime de idempotencia; sólo idempotencia por estado/evento (`modules/agenda/repositories/AppointmentWriteRepository.php:907`) | create/reschedule/confirm/cancel | sí | store durable + replay original en misma tx | CUT-01 | D-15 |
| F-010 | CONCURRENCY_AND_SLOT_CLAIMS | BLOCKER | VERIFIED | 8D reclama intervalos; schema sólo unique por inicio y estados activos (`modules/agenda/db/ready_schema.sql:15`) y availability se comprueba antes del insert (`modules/agenda/controllers/PublicAppointmentsController.php:333`) | doble reserva solapada | sí | claim/lock transaccional e índice/estrategia aprobada | CUT-01 | D-15 |
| F-011 | PUBLIC_AGENDA | HIGH | VERIFIED | 8E handoff opaco; legacy tiene dos flows request/verify y reserve/confirm (`api/agenda/index.php:1235`) | duplicación y estados distintos | sí | elegir un único adapter/handoff público | CUT-01 | D-07 |
| F-012 | OTP_AND_REPLAY | BLOCKER | VERIFIED | 8E prohíbe raw OTP; `DevOtpSender` registra OTP (`modules/agenda/services/OtpSender.php:18`) y QA responde debug (`modules/agenda/controllers/PublicOtpController.php:123`) | secreto en logs/respuesta | sí | retirar debug/log raw; canal real y replay de un consumo | CUT-01 | D-07,D-08 |
| F-013 | CONTACT_PRIVACY | BLOCKER | VERIFIED | 8E usa referencia opaca; legacy persiste contacto y nombre claros (`modules/agenda/controllers/PublicAppointmentsController.php:138`) y cancel token via URL UI (`assets/js/public/agenda-wizard.js:471`) | PII/token exposición | sí | vault/reference opaca, no secretos en URL/log | CUT-01 | D-05 |
| F-014 | PATIENT_IDENTITY | BLOCKER | VERIFIED | 8F resolver exacto; runtime crea paciente directo por payload (`api/patients/index.php:94`) y no carga dominio 8F | duplicados y creación sin decisión canónica | sí | adapter shadow y human-review boundary | CUT-01 | D-16 |
| F-015 | DUPLICATE_RESOLUTION | HIGH | VERIFIED | 8F ambiguity/review; `PatientsRepository` lista/busca pero no ejecuta resolver 8F (`modules/patients/repositories/PatientsRepository.php:385`) | búsqueda/creación; false match | sí | compare de candidatos sólo lectura | CUT-02 | D-16 |
| F-016 | PATIENT_MERGE | INFORMATIONAL | VERIFIED | merge paciente siempre disabled (`modules/agenda/contracts/PatientMergeContract.php:18`); no ruta real hallada | no aplica hoy; evitar confundir medical-group merge | no | mantener disabled hasta gate separado | CUT-05 | D-16 |
| F-017 | PERSISTENCE | BLOCKER | VERIFIED | port 8G existe (`modules/patients/identity/persistence/PatientIdentityPersistencePort.php:6`); no implementación ni wiring y las cuatro tablas faltan de ready schema | identidad durable inexistente | sí | adapter concreto sólo tras aprobación/migración | CUT-01 | D-11,D-15 |
| F-018 | RUNTIME_DDL | BLOCKER | VERIFIED | migraciones deben ser externas; GET settings puede crear tabla (`modules/agenda/repositories/AgendaSettingsRepository.php:53`) y consultorios puede CREATE/ALTER (`modules/agenda/repositories/ConsultoriosRepository.php:260`) | request HTTP muta schema | sí | eliminar auto-bootstrap mediante migración aprobada | CUT-01 | D-11 |
| F-019 | AUDIT_TRAIL | HIGH | VERIFIED | 8A/8G append-only; eventos legacy varían y flags/incidents pueden fallar sin bloquear (`modules/agenda/repositories/AppointmentWriteRepository.php:41`) | trazabilidad incompleta | sí | audit port unificado, failure policy y correlation IDs | CUT-01 | D-09 |
| F-020 | RETENTION | MEDIUM | VERIFIED | Gate 8G deja plazos unresolved (`modules/patients/identity/persistence/PatientIdentityRetentionPolicy.php:8`); flows/contactos legacy no muestran política cerrada | conservación indefinida | no | aprobar matrices de resolución/checkpoint/OTP | CUT-03 | D-05,D-06 |
| F-021 | MIGRATION | HIGH | VERIFIED | 8G declara ocho scripts; no runner/referencia de ejecución fuera de tests/manifest (`modules/patients/identity/persistence/PatientIdentityPersistenceManifest.php:13`) | schema no preparado | sí | preflight, ledger, checksum, rehearsal | CUT-03 | D-11 |
| F-022 | BACKFILL | HIGH | VERIFIED | plan declarativo no ejecutable (`modules/patients/identity/persistence/PatientIdentityBackfillPlan.php:16`); volumen requiere DB | mapping legacy desconocido | sí | snapshot, batch, checkpoint y reconcile con owner | CUT-03 | D-12 |
| F-023 | FEATURE_FLAGS | BLOCKER | VERIFIED | no flags PG03 runtime halladas; rollout 8G niega activación (`modules/patients/identity/persistence/PatientIdentityRolloutPolicy.php:15`) | no hay kill switch/segmentación | sí | flags server-side default off | CUT-01 | D-10 |
| F-024 | ROLLOUT | HIGH | VERIFIED | R0–R4 sólo declarativo (`modules/patients/identity/persistence/PatientIdentityRolloutPolicy.php:8`) | transición no gobernada | sí | métricas, budgets y aprobaciones por etapa | CUT-02 | D-10 |
| F-025 | LOCALSTORAGE | MEDIUM | VERIFIED | shell escribe horarios/bloqueos (`assets/js/app.js:7231`, `assets/js/app.js:32095`) | stale state y precedencia ambigua | no | conservar en R0, etiquetar fallback y reconciliar | CUT-04 | D-18 |
| F-026 | LEGACY_SENTINEL_ALL | HIGH | VERIFIED | `__all__` existe en waitlist (`modules/agenda/controllers/WaitlistController.php:21`) y UI (`assets/js/app.js:15942`) | destino no concreto al asignar | sí | resolver a consultorio concreto antes de claim/cita | CUT-01 | D-04 |
| F-027 | ERROR_CONTRACTS | MEDIUM | VERIFIED | router normaliza a `db_error` y status map parcial (`api/agenda/index.php:1355`, `api/agenda/index.php:1393`) | clientes no distinguen blocker/retry | no | catálogo estable y anti-enumeración | CUT-02 | D-08 |
| F-028 | OBSERVABILITY | MEDIUM | VERIFIED | métricas 8G sólo catálogo (`modules/patients/identity/persistence/PatientIdentityRolloutPolicy.php:10`); no sinks/dashboards PG03 hallados | R1 no medible | sí | counters sin PII, traces y alerts | CUT-01 | D-09 |
| F-029 | SAFE_RETURN | LOW | VERIFIED | Git/bundle permiten revert documental; no existe rollback productivo aprobado | recovery operativo no probado | no | runbook y drill previo a R3/R4 | CUT-03 | D-13,D-14 |
| F-030 | CLINICAL_BOUNDARY | HIGH | VERIFIED | canónico separa cita/encounter (`modules/agenda/contracts/AppointmentLifecycleContract.php:40`); repo construye bridge y lo llama tras commit (`modules/agenda/repositories/AppointmentWriteRepository.php:56`, `modules/agenda/repositories/AppointmentWriteRepository.php:99`) | efecto Clinical desacoplado/fallo no transaccional | sí | decisión explícita; mantener fuera de cutover PG03 | CUT-01 | D-17 |

## 10. Autoridad

La autoridad Agenda actual es híbrida. En strict exige `user_id` y `doctor_id`, pero el rol se resuelve antes desde headers/body/query y el modo compat inventa `compat_dev` (`api/agenda/index.php:206`, `api/agenda/index.php:308`). `is_authoritative` sólo es true con strict+session (`api/agenda/index.php:478`), pero controllers reciben el contexto también en compat. Strict operator sí bloquea rutas elegibles y devuelve 503 ante `operator_identity_db_not_ready` (`api/agenda/index.php:720`), pero configuración usa una prohibición separada y el resolver 8B no está conectado.

Patients acepta `doctorId`/`patientId` desde path sin sesión, membership u ownership. Componentes que deben usar `AgendaActorAuthorityResolver`: todas las 41 rutas privadas Agenda y cualquier bridge Agenda→Patients. Componentes que ya lo usan en runtime: ninguno confirmado; sólo tests lo instancian.

## 11. Disponibilidad

El runtime usa tablas candidatas legacy, `agenda_availability_overrides`, `HolidayMxProvider`, consultas de colisiones y timezone `America/Mexico_City` (`modules/agenda/controllers/AvailabilityController.php:396`). `db/ready_schema.sql` y `sql/ready_schema.sql` tienen las mismas tablas pero diferencias de defaults/documentación (`modules/agenda/db/ready_schema.sql:1`, `modules/agenda/sql/ready_schema.sql:1`); ninguno contiene `consultorio_schedule`, que vive en scripts separados. Full/partial blocks se almacenan como overrides y el PATCH desactiva el bloque completo; el split de desbloqueo no está demostrado.

Para volver backend autoritativo sin romper Semana/Día ni domingo/feriado: conservar localStorage como fallback R0; normalizar cada sede y día a intervalos; shadow-read del schedule versionado; comparar slots con timezone/duration/gap; preservar cierres por domingo/feriado y reopen overrides; exigir consultorio concreto; medir diferencias; y sólo entonces cambiar precedencia con kill switch.

## 12. Citas

El runtime persiste cita+evento en transacción para create (`modules/agenda/repositories/AppointmentWriteRepository.php:86`) y varias transiciones, pero no usa el agregado/version/idempotency record de 8D. El índice `uniq_active_slot` evita dos citas activas con igual inicio, no cualquier solapamiento; `tentative`, `rescheduled`, `no_show` y otros estados tienen semántica distinta. Algunas rutas hacen `FOR UPDATE`, especialmente public flows, pero el create privado no bloquea slot ni key idempotente.

Conclusión: hoy no puede garantizar de forma canónica y uniforme replay seguro, conflicto determinista, transición versionada, claim de intervalo ni rollback que incluya todos los efectos. Sí existe una defensa parcial de inicio único si el schema exacto fue instalado, dato de producción `UNRESOLVED` sin DB.

## 13. Agenda pública y OTP

Hay dos implementaciones legacy. `PublicOtpController` guarda `contact_value` y hash, permite debug code en QA y considera un OTP ya verificado como éxito idempotente (`modules/agenda/controllers/PublicOtpController.php:174`). `PublicAppointmentsController` guarda nombre/contacto/last4, usa `DevOtpSender`, crea citas y flows, y devuelve cancel token (`modules/agenda/controllers/PublicAppointmentsController.php:117`). El sender enmascara destinatario pero registra el OTP crudo (`modules/agenda/services/OtpSender.php:18`). La UI coloca cancel token en query string (`assets/js/public/agenda-wizard.js:471`). No se envió OTP durante esta auditoría.

## 14. Identidad

Patients opera con `patient_id` y tablas `patients_*`; puede crear paciente mínimo, contactos, dirección y perfil. No carga `PatientIdentityResolver`, no crea duplicate review ni maneja ambigüedad 8F. No existe ruta de merge de paciente. `POST /medical-groups/{id}/merge` sólo fusiona grupos médicos. Clinical no fue modificado por Gates 8F/8G ni por esta auditoría.

## 15. Persistencia y DDL

No existe clase que implemente `PatientIdentityPersistencePort`, ni wiring/runtime include del dominio 8F/8G, ni runner que ejecute las ocho migraciones. Las cuatro tablas Gate 8G existen sólo en migraciones/Manifest, no en los schemas declarativos `ready_schema.sql`. Los SQL Gate 8G no contienen PII clara; usan referencias/digests.

Señales DDL clasificadas:

| Señal | Clasificación | Evidencia |
|---|---|---|
| `AgendaSettingsRepository::ensureTable` | runtime activo, GET/PUT | `modules/agenda/repositories/AgendaSettingsRepository.php:16` |
| `ConsultoriosRepository` CREATE/ALTER | runtime activo, GET/PUT y dependencias públicas | `modules/agenda/repositories/ConsultoriosRepository.php:260` |
| MedicalGroups/Memberships/ReviewLog CREATE | runtime activo por controllers | `modules/agenda/repositories/MedicalGroupsRepository.php:203`; `modules/agenda/repositories/MedicalGroupMembershipsRepository.php:153`; `modules/agenda/repositories/MedicalGroupReviewLogRepository.php:81` |
| Public OTP/FLOW CREATE | runtime activo por requests públicas | `modules/agenda/controllers/PublicAppointmentsController.php:1044`; `modules/agenda/controllers/PublicAppointmentsController.php:1705` |
| `db/*.sql`, `sql/*.sql` | script manual/declarativo | `modules/agenda/db/README.md:8` |
| Gate 8G migrations | migración declarativa no ejecutada | `modules/patients/identity/persistence/PatientIdentityPersistenceManifest.php:13` |
| CREATE en tests/docs | test/documentación | manifest de fuentes |

## 16. Migración y backfill

| Fuente | Destino | ID | Volumen | Transformación / PII | Idempotencia/checkpoint | Rollback | Bloqueo/aprobación |
|---|---|---|---|---|---|---|---|
| `patients_patients` | input canónico 8F | `patient_id` | UNRESOLVED | referencia, posible PII en fuente | fingerprint | no plan ejecutable | DB preflight + owner |
| referencias legacy Agenda/Patients | `patient_identity_legacy_links` | SHA-256 opaco | UNRESOLVED | adapter confiable; no raw | unique digest + checkpoint | rollback/reconcile | adapter y D-03 |
| resoluciones shadow | `patient_identity_resolutions` | request fingerprint | UNRESOLVED | digests/resultados | processing/completed/failed | migración down sólo antes de datos | D-05,D-11 |
| auditoría identidad | `patient_identity_audit_events` | event/stream sequence | UNRESOLVED | metadata cerrada sin PII | append-only/hash chain | conservar/exportar | D-05 |
| progreso batch | `patient_identity_backfill_checkpoints` | job reference | UNRESOLVED | sin registros completos | batch/checkpoint/reconcile | snapshot externo | D-06,D-12 |

No se inventan volúmenes, ventanas ni fechas. Todos requieren acceso DB y permanecen `UNRESOLVED`.

## 17. Flags y rollout

Los once nombres evaluados son candidatos, no contratos aprobados: `canonical_actor_authority`, `canonical_schedule_read`, `canonical_availability_compare`, `canonical_appointment_lifecycle`, `canonical_public_agenda`, `canonical_patient_identity`, `patient_identity_persistence`, `legacy_write_disable`, `shadow_audit`, `read_compare`, `backfill`. Todos deben ser server-side, default off, scoped por profile/consultorio y con kill switch. La matriz completa R0–R4 y budgets está en el documento de gates.

## 18. Blockers

Hay 13 BLOCKER: F-001, F-002, F-004, F-006, F-008, F-009, F-010, F-012, F-013, F-014, F-017, F-018 y F-023. Dependencias externas: Identity/sesión/membership real, política de retención, proveedor OTP, observabilidad, owner y ventana de migración, medición de volumen, y decisión Clinical. Unknowns principales: schemas/datos instalados, cardinalidad, distribución de scopes, tasa de colisión, latencia y RTO/RPO.

## 19. Decisiones

| ID | Decisión y opciones | Impacto / recomendación | Evidencia | Responsable | Momento |
|---|---|---|---|---|---|
| D-01 | grey/black flags: conservar, retirar, redefinir | reglas afectan pacientes; conservar hasta política | `modules/agenda/repositories/AppointmentWriteRepository.php:824` | Dirección producto/privacidad | antes CUT-02 |
| D-02 | duration/gap y override por sede/global | aprobar sede específica y precedencia explícita | `modules/agenda/controllers/AgendaSettingsController.php:88` | Producto Agenda | CUT-01 |
| D-03 | `consultorio_scope` y backfill | exigir scope concreto; mapear legacy en shadow | `modules/agenda/db/ready_schema.sql:91` | Arquitectura/datos | antes CUT-03 |
| D-04 | retiro de `__all__` | no permitirlo como destino; resolver antes de cita | `modules/agenda/controllers/WaitlistController.php:21` | Producto Agenda | CUT-01 |
| D-05 | retención de resoluciones | no inventar TTL; aprobar por categoría | `modules/patients/identity/persistence/PatientIdentityRetentionPolicy.php:8` | Legal/privacidad | antes migración |
| D-06 | retención de checkpoints | mantener hasta reconciliación aprobada | misma fuente | Datos/privacidad | CUT-03 |
| D-07 | canal OTP real | elegir proveedor/canales; quitar DevOtpSender | `modules/agenda/services/OtpSender.php:9` | Seguridad/operaciones | CUT-01 |
| D-08 | reintentos/rate limit/anti-enumeración | adoptar 8E por IP/contact/profile sin PII labels | `modules/agenda/contracts/PublicOtpContract.php:17` | Seguridad | CUT-01 |
| D-09 | observabilidad | counters/traces/audit y policy de fallo | `modules/patients/identity/persistence/PatientIdentityRolloutPolicy.php:10` | SRE/seguridad | CUT-01 |
| D-10 | umbrales R1–R4 | aprobar budgets antes de cada stage | misma fuente | Dirección técnica | cada gate |
| D-11 | ventana de migración | rehearsal + snapshot + ledger; fecha pendiente | `modules/patients/identity/persistence/PatientIdentityPersistenceManifest.php:13` | DBA/dirección | CUT-03 |
| D-12 | owner de backfill | un owner y on-call explícitos | `modules/patients/identity/persistence/PatientIdentityBackfillPlan.php:8` | Datos | CUT-03 |
| D-13 | rollback | kill switch primero; revert/migration sólo con runbook | `/tmp/mxmed-activity09-runtime-cutover-readiness-preflight-v2/safe-return-points.txt:1` | SRE/DBA | antes CUT-02 |
| D-14 | RTO/RPO | UNRESOLVED: no existe fuente aprobada hallada | manifest de fuentes | SRE/dirección | antes R3 |
| D-15 | habilitación de writes | no antes de R4 y postvalidación | `modules/patients/identity/persistence/PatientIdentityRolloutPolicy.php:16` | Dirección | CUT-05 |
| D-16 | merge de pacientes | mantener disabled; gate R3 separado con reauth | `modules/agenda/contracts/PatientMergeContract.php:18` | Dirección/Clinical | fuera de este plan |
| D-17 | impacto Clinical | mantener bridge fuera o definir saga/outbox | `modules/agenda/repositories/AppointmentWriteRepository.php:99` | Clinical/arquitectura | CUT-01 |
| D-18 | retiro localStorage | sólo tras compare estable y plan de migración UI | `assets/js/app.js:32091` | Producto/UI | CUT-04 |

Ninguna decisión se aprueba en esta auditoría.

## 20. Readiness

Clasificación única: `NO_GO_BLOCKERS_PRESENT`. Condiciones mínimas para pasar a `CONDITIONAL_GO_R1_SHADOW_ONLY`: cerrar F-001/F-002/F-004 mediante composition root server-side; eliminar DDL on-request; definir flags y observabilidad; retirar OTP crudo; disponer de adapters de lectura sin writes; resolver scope `__all__`; y aprobar D-07, D-09, D-10, D-13 y D-17. R1 no se activa aquí.

## 21. Safe return

Punto seguro versionado: baseline Activity 8. Esta auditoría sólo agrega dos documentos y PP-311; su retorno es revertir el commit único en worktree detached y confirmar árbol idéntico al baseline. Para stages futuros: R1/R2 vuelven a R0 apagando flags; R3 vuelve a lectura legacy; R4 requiere kill switch, snapshot, reconciliation y runbook DB aprobado. Ningún rollback SQL fue ejecutado.

## 22. Evidencias

Directorio `/tmp/mxmed-activity09-runtime-cutover-readiness-audit-v2/`: diez JSON válidos y cuatro TXT. `route-runtime-matrix.json` contiene 58 rutas; `blocker-decision-register.json` contiene conteos, 13 blockers y 18 decisiones; `source-manifest.txt` registra SHA-256. PP310 hash stable=true, PP311 count=1, PP311 hash=`e36d72058cd054d784d91983f1fbc3f7f0ceddb907c65ef9889ecd82d4c8b147`, PP312 real=0.

## 23. Exclusiones

DB connections 0; migrations 0; SQL 0; data writes 0; OTP 0; appointments 0; patients 0; merges 0; backfill 0; R1–R4 0; runtime wiring 0; route changes 0; UI 0; AWS 0. No se invocaron endpoints de fixture, QA o escritura. No se modificó PHP, SQL, JavaScript, CSS, HTML, rutas ni configuración.

## 24. Estado final

- Activity 9: `AUDIT_COMPLETE_READY_FOR_POSTVALIDATION_NOT_INTEGRATED`.
- Activity 10: `BLOCKED`.
- Contador: `8/22`; pendientes: `14`.
- Cutover: `NO_GO_BLOCKERS_PRESENT`.
- Programa: `NO_GO_LEGACY_BLOCKERS_PRESENT`.
- CUT-01–CUT-05: propuesta no aprobada.
