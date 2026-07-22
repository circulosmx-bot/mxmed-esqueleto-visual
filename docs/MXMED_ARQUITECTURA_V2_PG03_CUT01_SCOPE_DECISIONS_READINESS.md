# MXMed V2 · PG-03 · Readiness de alcance y decisiones CUT-01

## 1. Resumen ejecutivo

Resultado documental: `PASS_ACTIVITY_10_CUT01_SCOPE_DECISIONS_READINESS_COMPLETE`. La Actividad 10 convierte CUT-01 en una propuesta implementable, pero no autoriza código, wiring, datos, SQL ni rollout (`/tmp/mxmed-activity10-cut01-scope-decisions-readiness-preflight-v2/activity10-approved-contract.txt:1`, `/tmp/mxmed-activity10-cut01-scope-decisions-readiness-preflight-v2/director-approval.txt:6`).

Se proponen nueve decisiones, once flags y cuatro subgates; todos permanecen no aprobados. El cutover continúa `NO_GO_BLOCKERS_PRESENT` por los 13 blockers de Actividad 9 (`docs/MXMED_AUDITORIA_V2_PG03_RUNTIME_CUTOVER_READINESS.md:7`, `docs/MXMED_AUDITORIA_V2_PG03_RUNTIME_CUTOVER_READINESS.md:175`).

## 2. Contrato aprobado de Actividad 10

El identificador es `ARCH/MXMed-PG03-CUT01-Scope-Decisions-Readiness-01`, la clasificación es UI-0 y la naturaleza aprobada es arquitectura/read-only (`/tmp/mxmed-activity10-cut01-scope-decisions-readiness-preflight-v2/activity10-approved-contract.txt:1`, `/tmp/mxmed-activity10-cut01-scope-decisions-readiness-preflight-v2/director-approval.txt:1`).

La autorización permite inspección y documentación; excluye adapters, flags runtime, rutas, DDL, DB, migraciones, backfill, OTP, citas, pacientes, merges, `localStorage`, `__all__`, Clinical, UI, R1–R4 y AWS (`/tmp/mxmed-activity10-cut01-scope-decisions-readiness-preflight-v2/activity10-approved-contract.txt:24`).

## 3. Baseline

- Programa y parent: `1e3057f2a12afed9da7e6ce95cd20ae81d645c1f` (`/tmp/mxmed-activity10-cut01-scope-decisions-readiness-preflight-v2/program-baseline.txt:1`).
- Checkpoint anotado `checkpoint/mxmed-product-refinement-v2-activity09`, desreferenciado al mismo commit (`/tmp/mxmed-activity10-cut01-scope-decisions-readiness-preflight-v2/program-baseline.txt:8`).
- PP-311 existe una vez y PP-312 no existía al iniciar (`/tmp/mxmed-activity10-cut01-scope-decisions-readiness-preflight-v2/program-baseline.txt:10`).
- Rollout inicial R0 disabled; cutover `NO_GO_BLOCKERS_PRESENT` (`/tmp/mxmed-activity10-cut01-scope-decisions-readiness-preflight-v2/program-baseline.txt:12`).

## 4. Fuentes

Se inspeccionaron `72` fuentes y se verificaron `103` referencias únicas `ruta:línea`. El inventario SHA-256 clasifica cada fuente como `ACTIVE_RUNTIME`, `CANONICAL_CONTRACT`, `DECLARATIVE_ONLY`, `TEST`, `DOCUMENTATION`, `LEGACY_COMPATIBILITY` o `UNCONFIRMED`; existencia no implica activación (`docs/MXMED_AUDITORIA_V2_PG03_RUNTIME_CUTOVER_READINESS.md:28`).

Las fuentes cubren ambos routers, autoridad/sesión, disponibilidad, OTP, persistencia, rollout, safe return, audit trail y la frontera Clinical, además de los cinco paquetes finales de Actividad 9 (`docs/MXMED_AUDITORIA_V2_PG03_RUNTIME_CUTOVER_READINESS.md:30`, `/tmp/mxmed-activity09-integration-v2-r2/qa-result.json:1`).

## 5. Relación con los 13 blockers

| Blocker | Brecha | Respuesta propuesta | Subgate | Estado |
|---|---|---|---|---|
| F-001 | autoridad Agenda híbrida | composition root + resolver 8B | CUT01-A | PENDING_DIRECTOR_APPROVAL |
| F-002 | claims cliente usados como autoridad | separar input/diagnóstico de autoridad | CUT01-A | PENDING_DIRECTOR_APPROVAL |
| F-004 | Patients sin frontera server-side | composition root Patients + Gate 6B | CUT01-A | PENDING_DIRECTOR_APPROVAL |
| F-006 | fuentes schedule divergentes | adapter lectura + precedencia decidida | CUT01-B | PENDING_DIRECTOR_APPROVAL |
| F-008 | lifecycle legacy divergente | adapter shadow lifecycle | CUT01-B | PENDING_DIRECTOR_APPROVAL |
| F-009 | idempotencia durable ausente | port/store futuro, sin writes ahora | CUT01-D | PENDING_DIRECTOR_APPROVAL |
| F-010 | claims de slot insuficientes | contrato transaccional futuro | CUT01-D | PENDING_DIRECTOR_APPROVAL |
| F-012 | OTP raw/debug | provider port + privacidad + rate limit | CUT01-C | PENDING_DIRECTOR_APPROVAL |
| F-013 | contacto/token claro | referencias opacas + no secrets URL/log | CUT01-C | PENDING_DIRECTOR_APPROVAL |
| F-014 | identidad paciente no conectada | adapter shadow 8F | CUT01-A | PENDING_DIRECTOR_APPROVAL |
| F-017 | persistencia 8G sin adapter | adapter propuesto, siempre off | CUT01-D | PENDING_DIRECTOR_APPROVAL |
| F-018 | DDL durante request | migraciones externas y fail-closed | CUT01-C | PENDING_DIRECTOR_APPROVAL |
| F-023 | flags/kill switch ausentes | registro cerrado default false | CUT01-D | PENDING_DIRECTOR_APPROVAL |

La enumeración y severidad provienen de la matriz F-001–F-030 (`docs/MXMED_AUDITORIA_V2_PG03_RUNTIME_CUTOVER_READINESS.md:82`, `docs/MXMED_AUDITORIA_V2_PG03_RUNTIME_CUTOVER_READINESS.md:175`).

## 6. Decisiones D originales

CUT-01 depende de D-02, D-03, D-04, D-07, D-08, D-09, D-10, D-13 y D-17; Actividad 9 registró propósito, owner y momento, pero no aprobó ninguna (`docs/MXMED_AUDITORIA_V2_PG03_RUNTIME_CUTOVER_READINESS.md:177`, `docs/MXMED_AUDITORIA_V2_PG03_RUNTIME_CUTOVER_READINESS.md:200`).

El documento hermano propone DEC-014A–I y mantiene cada estado `PENDING_DIRECTOR_APPROVAL` (`docs/MXMED_DECISIONES_PROPUESTAS_V2_PG03_CUT01.md:1`).

## 7. Autoridad Agenda

El runtime actual inicia sesión, pero todavía lee rol desde headers y dispone de compatibilidad `compat_dev`; el resolver canónico exige principal, membership activa, profile, scope y `AuthorizationBoundary` (`api/agenda/index.php:41`, `api/agenda/index.php:209`, `api/agenda/index.php:309`, `modules/agenda/security/AgendaActorAuthorityResolver.php:31`).

Contrato propuesto, no implementado:

1. El composition root valida sesión mediante Identity y produce principal real; después carga membership activa y profile/consultorio permitidos (`modules/identity/services/SessionService.php:48`, `modules/agenda/security/AgendaActorAuthorityResolver.php:57`).
2. El actor real proviene de sesión; el actor efectivo sólo puede surgir de una delegación/binding server-side verificable. El `operator_id` cliente nunca basta como autoridad (`modules/agenda/security/OperatorBinding.php:9`, `modules/agenda/security/AgendaActorAuthorityResolver.php:88`).
3. Ownership, profile, consultorio y route policy alimentan `AuthorizationBoundary`; deny/unknown es fail-closed (`modules/agenda/security/AgendaActorAuthorityResolver.php:72`, `modules/platform/services/AuthorizationBoundary.php:31`).
4. Headers/body/query se conservan únicamente como input o diagnóstico sanitizado durante shadow; no deciden identidad, role, ownership ni scope (`modules/agenda/security/ClientAuthorityClaims.php:38`, `modules/agenda/security/AgendaAuthorityResolution.php:46`).
5. `canonical_actor_authority=false` mantiene respuesta legacy en R0; cualquier comparación debe ser side-effect free y no cambiar rutas (`docs/MXMED_PLAN_V2_PG03_RUNTIME_CUTOVER_GATES.md:19`).

| Clase de dato | Ejemplos | Uso permitido propuesto |
|---|---|---|
| Autoridad | session principal, membership, ownership | permitir/denegar server-side |
| Entrada | appointment payload, target IDs | validar operación, nunca autenticar |
| Diagnóstico | claims cliente, correlation ID | mismatch sanitizado, sin PII |
| Compatibilidad | headers legacy, `compat_dev` | sólo shadow temporal, sin elevar privilegios |

## 8. Autoridad Patients

El router Patients construye ocho controllers y enruta IDs de path sin iniciar/validar sesión; Actividad 9 trató sus ocho rutas como privadas por el contenido (`api/patients/index.php:4`, `api/patients/index.php:25`, `docs/MXMED_AUDITORIA_V2_PG03_RUNTIME_CUTOVER_READINESS.md:69`).

Contrato propuesto: composition root único inicia/valida sesión, resuelve actor y membership, aplica ownership doctor/patient con Gate 6B, y considera los IDs de path sólo targets. Cualquier principal, membership, profile o ownership ausente/mismatch deniega antes de construir controller (`modules/platform/services/AuthorizationBoundary.php:31`, `modules/agenda/security/AgendaActorAuthorityResolver.php:57`).

| Ruta Patients auditada | Target de entrada | Autoridad requerida propuesta | Modo R0 |
|---|---|---|---|
| `GET /patients/{id}` | patient ID | doctor membership/ownership o patient propio | legacy, flag false |
| `GET /doctors/{doctor}/patients/{patient}/contacts/editable` | doctor+patient | sesión + membership doctor + ownership | legacy, flag false |
| `GET /doctors/{doctor}/patients/search` | doctor+query | sesión + membership doctor | legacy, flag false |
| `GET /doctors/{doctor}/patients` | doctor | sesión + membership doctor | legacy, flag false |
| `POST /patients` | payload | actor autorizado + scope explícito | legacy, flag false |
| `POST /patients/{id}/address` | patient+payload | ownership doctor/patient aprobado | legacy, flag false |
| `POST /patients/{id}/profile` | patient+payload | ownership doctor/patient aprobado | legacy, flag false |
| `PUT /doctors/{doctor}/patients/{patient}/contacts/editable` | doctor+patient+payload | sesión + membership + ownership | legacy, flag false |

Las rutas y métodos corresponden al switch auditado (`api/patients/index.php:77`, `api/patients/index.php:93`).

## 9. Schedule y availability

El runtime busca cinco nombres de tabla, mientras el dominio canónico selecciona una versión por profile/consultorio y aplica holiday, close/open overrides, colisiones, duración y gap (`modules/agenda/repositories/ScheduleRepository.php:13`, `modules/agenda/availability/CanonicalAvailabilityCalculator.php:15`, `modules/agenda/availability/CanonicalAvailabilityCalculator.php:27`, `modules/agenda/availability/CanonicalAvailabilityCalculator.php:74`).

Propuesta: adapter read-only que normaliza fuentes legacy en versiones canónicas y compara por scope/timezone/slot. La precedencia, valores y comportamiento incompleto dependen de DEC-014A; no se inventan números ni se activa autoridad canónica (`modules/agenda/controllers/AgendaSettingsController.php:88`, `docs/MXMED_DECISIONES_PROPUESTAS_V2_PG03_CUT01.md:34`).

## 10. Consultorio scope

Todo resultado autoritativo debe tener profile y consultorio concretos; registros sin sede se particionan como `unscoped` o `ambiguous`, sin asignación automática. Volumen, owner y reglas de backfill siguen sin resolver y requieren una actividad DB separada (`modules/agenda/availability/AvailabilityCalculationRequest.php:23`, `modules/patients/identity/persistence/PatientIdentityBackfillPlan.php:18`).

## 11. Sentinel `__all__`

Waitlist define `__all__` para scope agregado y ya prohíbe usarlo como consultorio de asignación (`modules/agenda/controllers/WaitlistController.php:21`, `modules/agenda/controllers/WaitlistController.php:233`). La propuesta conserva consultas agregadas, pero obliga a resolver un consultorio real antes de claim/cita/write o rechaza fail-closed; no se cambia runtime ni UI en esta actividad.

## 12. OTP y privacidad

El sender dev registra OTP raw y destinatario, el flow persiste contacto claro y el controller posee caminos de debug; esto sustenta F-012/F-013 (`modules/agenda/services/OtpSender.php:18`, `modules/agenda/controllers/PublicAppointmentsController.php:139`, `modules/agenda/controllers/PublicOtpController.php:123`).

Se propone provider port neutral SMS/email, sandbox aislado, secreto sólo en frontera de entrega, challenge/referencias opacas, rate-limit HMAC, respuestas homogéneas, replay deny, rotación, health y kill switch. Proveedor, límites y ventanas continúan pendientes (`modules/agenda/contracts/PublicOtpContract.php:17`, `docs/MXMED_DECISIONES_PROPUESTAS_V2_PG03_CUT01.md:98`).

## 13. Observabilidad

Métricas propuestas sin PII: allow/deny reason, authority mismatch, schedule/slot diff, lifecycle diff, OTP outcome, identity outcome, audit failure, rollback, latency buckets, checkpoint lag y reconciliation delta; Gate 8G ya declara un subconjunto sin activarlo (`modules/patients/identity/persistence/PatientIdentityRolloutPolicy.php:10`).

Cada señal requiere correlation ID opaco, owner/on-call, dashboard, alerta, health/readiness y retención aprobada. Emisión de métrica puede fallar abierta con alerta; auditoría de autoridad/write debe fallar cerrada; shadow read queda sujeto a matriz de riesgo (`modules/platform/contracts/AuditTrailPort.php:7`, `docs/MXMED_DECISIONES_PROPUESTAS_V2_PG03_CUT01.md:122`).

## 14. Métricas R0–R4

| Etapa | Métricas obligatorias propuestas | Entrada/salida | Hard stop |
|---|---|---|---|
| R0 | cobertura harness, health de ports, flags false | baseline reproducible | cualquier side effect |
| R1 | mismatch actor/scope/schedule, deny reason, latency | baseline y budget aprobados | leakage, PII o breach aprobado |
| R2 | audit append/availability, replay, cobertura | R1 estable | audit crítico unavailable |
| R3 | equality/diff, stale read, fallback, reconcile | datos ensayados | delta fuera de umbral |
| R4 | writes, conflicts, idempotency, rollback, data reconcile | autorización expresa | corrupción, doble reserva o privacy incident |

Las etapas declarativas son R0 disabled, R1 shadow, R2 audit_only, R3 read_compare y R4 enabled (`modules/patients/identity/persistence/PatientIdentityRolloutPolicy.php:8`). Todos los percentiles, porcentajes y duraciones quedan `UNRESOLVED_PENDING_BASELINE_AND_DIRECTOR_APPROVAL`.

## 15. Rollback

Todos los flags son server-side default false. R1/R2 regresan a R0; R3 retorna lectura legacy; R4 detiene writes canónicos, preserva snapshot/checkpoint y exige reconciliación/runbook antes de reintento. Revert Git no sustituye rollback de datos y se prohíben reset/force-push (`docs/MXMED_PLAN_V2_PG03_RUNTIME_CUTOVER_GATES.md:124`).

Actividad 10 no ejecuta rollback SQL ni define RTO/RPO; esos datos requieren fuente y aprobación (`/tmp/mxmed-activity10-cut01-scope-decisions-readiness-preflight-v2/safe-return-points.txt:1`).

## 16. Clinical boundary

El contrato canónico separa cita de encounter. Hoy Agenda hace commit y después invoca un bridge opcional; el bridge consulta por `appointment_id`, realiza POST si falta y el repositorio captura/loguea errores (`modules/agenda/contracts/AppointmentLifecycleContract.php:40`, `modules/agenda/repositories/AppointmentWriteRepository.php:90`, `modules/agenda/services/ClinicalEncounterBridge.php:44`, `modules/agenda/repositories/AppointmentWriteRepository.php:868`).

Opciones: bridge actual contenido; transactional outbox; saga; transacción distribuida. Se recomienda outbox sujeto a aprobación, con evento propiedad de Agenda, `appointment_id` idempotente, orden por agregado, retries/DLQ y observabilidad. Saga sólo con compensaciones aprobadas; transacción distribuida no se recomienda sin soporte real. CUT-01 excluye cambios Clinical (`docs/clinical/CONTRATO_APPOINTMENT_ENCOUNTER_LINKING_V1.md:1`, `docs/MXMED_DECISIONES_PROPUESTAS_V2_PG03_CUT01.md:145`).

## 17. Feature flags candidatos

Los once nombres forman un registro cerrado propuesto. Ninguno existe, está aprobado o se implementa; todos tienen default exacto `false` (`docs/MXMED_PLAN_V2_PG03_RUNTIME_CUTOVER_GATES.md:25`).

| Flag | Propósito / owner | Default | Scope / R0–R4 | Dependencias / datos | Métricas / failure mode | Kill switch / rollback | Rutas elegibles / exclusiones | Decisión |
|---|---|---|---|---|---|---|---|---|
| `canonical_actor_authority` | autoridad; Identity+Agenda | false | profile+consultorio; shadow R1, enforce sólo R4 | sesión/membership; IDs opacos | mismatch/deny; unknown deny | off→legacy | 41 Agenda+8 Patients; no públicas | DEC-014F/H pendiente |
| `canonical_schedule_read` | horario; Agenda | false | profile+consultorio; compare R1–R3 | schedules | schedule diff; fallback | off→legacy read | schedule/settings; no writes | DEC-014A pendiente |
| `canonical_availability_compare` | slots; Agenda+SRE | false | scope+date; compare R1–R3 | schedule/override/holiday/collision | slot diff; fail-open shadow | off→sin compare | availability pública/privada; no respuesta canonical | DEC-014A/G pendiente |
| `canonical_appointment_lifecycle` | lifecycle; Agenda | false | scope; shadow R1–R3 | citas/eventos/idempotencia | transition/conflict; deny unknown | off→legacy | writes cita; no Clinical | DEC-014H/I pendiente |
| `canonical_public_agenda` | flow público; Security+Agenda | false | profile+consultorio; R1 shadow/R4 | challenge/contact opacos | OTP/replay/privacy; fail-closed secret | off→legacy/disable | 9 públicas; excluye maintenance dev | DEC-014D/E pendiente |
| `canonical_patient_identity` | resolución; Patients | false | profile+consultorio; R1–R3 compare | digests/candidatos | outcome/ambiguity; review | off→legacy resolver | 8 Patients; merge excluido | DEC-014B/F pendiente |
| `patient_identity_persistence` | persistencia; Patients+DBA | false | scope; R0–R2 off, R3 compare, R4 | cuatro tablas 8G | tx/audit/idempotency; fail-closed write | stop writes→legacy read | adapter interno; no migración automática | DEC-014H pendiente |
| `legacy_write_disable` | contención; Arquitectura | false | segmento; sólo R4 | rutas write | attempts/deny; unknown deny | false tras reconcile | writes aprobados; no big-bang | DEC-014G/H pendiente |
| `shadow_audit` | auditoría; SRE+Security | false | scope; R1–R4 | audit sink | append/failure; policy por riesgo | off→no shadow | evaluación canonical; sin PII | DEC-014F pendiente |
| `read_compare` | dual read; SRE | false | scope; sólo R3 | legacy+canonical reads | equality/latency; respuesta legacy | off→legacy only | reads aprobados; no writes | DEC-014G/H pendiente |
| `backfill` | batch; Data+DBA | false | dataset/scope; rehearsal y producción separados | snapshot/checkpoint | lag/error/reconcile; abort | stop/preserve checkpoint/restore | job externo; nunca request | DEC-014B/H pendiente |

## 18. Retiro futuro de DDL durante requests

| Clase/método | Ruta alcanzable | DDL / tabla | Sev. | Migración externa futura | Preflight | Tabla ausente / error contract | Orden | Test futuro | Rollback |
|---|---|---|---|---|---|---|---|---|---|
| `AgendaSettingsRepository::ensureTable` | `GET|PUT /settings` | CREATE `agenda_settings` | BLOCKER | create versionada | schema ledger+privileges | 503 `schema_not_ready`, sin DDL | 1 | request no ejecuta DDL | revert código; down sólo sin datos |
| `ConsultoriosRepository::ensureTable` | `GET|PUT /consultorios` | CREATE/ALTER `consultorios` | BLOCKER | create+alters versionados | column diff+backup | 503 `schema_not_ready` | 2 | GET/PUT sin CREATE/ALTER | flag off; restore según runbook |
| `MedicalGroupsRepository::ensureTable` | rutas medical-groups | CREATE `medical_groups` | BLOCKER | create versionada | dependency/charset | 503 `schema_not_ready` | 3 | grupo sin DDL | revert+down pre-data |
| `MedicalGroupMembershipsRepository::ensureTable` | join/approve/reject | CREATE `medical_group_memberships` | BLOCKER | create+FK/index | parent tables ready | 503 `schema_not_ready` | 4 | membership sin DDL | revert+down pre-data |
| `MedicalGroupReviewLogRepository::ensureTable` | approve/reject/merge | CREATE `medical_group_review_log` | BLOCKER | append-only table | audit retention+owner | 503 `audit_schema_not_ready` | 5 | review sin DDL | preserve log; no destructive down |
| `PublicAppointmentsController::ensureOtpTable` | public OTP request/verify | CREATE `agenda_public_otp_requests` | BLOCKER | external OTP migration | privacy+retention+provider | 503 homogéneo, no enumera | 6 | OTP request sin DDL | provider kill switch; preserve evidence |
| `PublicAppointmentsController::ensureFlowTable` | reserve/confirm/cancel | CREATE `agenda_public_appointment_flows` | BLOCKER | external FLOW migration | constraints+reconcile | 503 homogéneo, no cita | 7 | public flow sin DDL | stop flow; reconcile before down |

Las llamadas y sentencias existen en métodos alcanzables (`modules/agenda/repositories/AgendaSettingsRepository.php:16`, `modules/agenda/repositories/ConsultoriosRepository.php:262`, `modules/agenda/repositories/MedicalGroupsRepository.php:205`, `modules/agenda/repositories/MedicalGroupMembershipsRepository.php:155`, `modules/agenda/repositories/MedicalGroupReviewLogRepository.php:83`, `modules/agenda/controllers/PublicAppointmentsController.php:1038`, `modules/agenda/controllers/PublicAppointmentsController.php:1705`). Las rutas se documentaron en el switch activo (`docs/MXMED_AUDITORIA_V2_PG03_RUNTIME_CUTOVER_READINESS.md:62`, `docs/MXMED_AUDITORIA_V2_PG03_RUNTIME_CUTOVER_READINESS.md:65`, `docs/MXMED_AUDITORIA_V2_PG03_RUNTIME_CUTOVER_READINESS.md:74`).

## 19. Inventario propuesto de implementación

La inspección produce una propuesta de 42 archivos versionados: 26 nuevos, 16 modificados y, dentro de los nuevos, 10 pruebas y 4 documentos. Es un alcance candidato, no autorización.

| Archivo propuesto | Acción | Motivo / componente | Decisión / flag | Riesgo | UI | Dependencia | Rollback |
|---|---|---|---|---|---|---|---|
| `modules/agenda/composition/AgendaAuthorityCompositionRoot.php` | nuevo | sesión/autoridad Agenda | DEC-014F / actor | alto | 0 | Identity+6B | flag off+revert |
| `modules/patients/composition/PatientsAuthorityCompositionRoot.php` | nuevo | autoridad Patients | DEC-014F / actor | alto | 0 | Identity+6B | flag off+revert |
| `modules/agenda/adapters/CanonicalScheduleReadAdapter.php` | nuevo | normalizar horario | DEC-014A / schedule | medio | 0 | scope | flag off |
| `modules/agenda/adapters/CanonicalAvailabilityCompareAdapter.php` | nuevo | compare slots | DEC-014A/G / availability | medio | 0 | schedule | flag off |
| `modules/agenda/adapters/CanonicalAppointmentLifecycleAdapter.php` | nuevo | lifecycle shadow | DEC-014H / lifecycle | alto | 0 | idempotencia | flag off |
| `modules/agenda/adapters/CanonicalPublicAgendaAdapter.php` | nuevo | handoff público | DEC-014D/E / public | alto | 0 | OTP port | flag off |
| `modules/patients/identity/adapters/CanonicalPatientIdentityAdapter.php` | nuevo | resolver 8F | DEC-014B / identity | alto | 0 | membership | flag off |
| `modules/patients/identity/persistence/PdoPatientIdentityPersistenceAdapter.php` | nuevo | port 8G | DEC-014H / persistence | alto | 0 | schema externo | flag off+no writes |
| `modules/agenda/contracts/OtpProviderPort.php` | nuevo | proveedor neutral | DEC-014D / public | alto | 0 | Security | rejecting adapter |
| `modules/agenda/contracts/OtpRateLimitPolicy.php` | nuevo | anti-abuso | DEC-014E / public | alto | 0 | baseline Security | deny/off |
| `modules/platform/contracts/Pg03CutoverFeatureFlagPort.php` | nuevo | flags server-side | DEC-014H / todos | alto | 0 | config | defaults false |
| `modules/platform/contracts/Pg03ObservabilityPort.php` | nuevo | métricas/audit | DEC-014F/G / shadow | medio | 0 | sink | disable shadow |
| `api/agenda/index.php` | modificar | composition root | DEC-014F / actor | alto | 0 | Agenda root | flag off+revert |
| `api/patients/index.php` | modificar | sesión/autoridad | DEC-014F / actor | alto | 0 | Patients root | flag off+revert |
| `modules/agenda/controllers/AgendaSettingsController.php` | modificar | schedule adapter | DEC-014A / schedule | medio | 0 | adapter | legacy read |
| `modules/agenda/controllers/AvailabilityController.php` | modificar | compare disponibilidad | DEC-014A/G / availability | medio | 0 | adapter | disable compare |
| `modules/agenda/controllers/WaitlistController.php` | modificar | sentinel | DEC-014C / schedule | alto | 0 | scope resolver | legacy R0 |
| `modules/agenda/controllers/PublicOtpController.php` | modificar | OTP privacy | DEC-014D/E / public | alto | 0 | provider/rate | kill switch |
| `modules/agenda/controllers/PublicAppointmentsController.php` | modificar | flow/DDL containment | DEC-014D/E / public | alto | 0 | migrations externas | disable public canonical |
| `modules/agenda/repositories/AgendaSettingsRepository.php` | modificar | retirar DDL request | DEC-014H / actor | alto | 0 | migration | revert before data |
| `modules/agenda/repositories/ConsultoriosRepository.php` | modificar | retirar CREATE/ALTER | DEC-014B/H / schedule | alto | 0 | migration | snapshot/runbook |
| `modules/agenda/repositories/MedicalGroupsRepository.php` | modificar | retirar DDL request | DEC-014H / actor | alto | 0 | migration | revert pre-data |
| `modules/agenda/repositories/MedicalGroupMembershipsRepository.php` | modificar | retirar DDL request | DEC-014F/H / actor | alto | 0 | migration | revert pre-data |
| `modules/agenda/repositories/MedicalGroupReviewLogRepository.php` | modificar | audit schema externo | DEC-014F/H / shadow | alto | 0 | migration/retention | preserve audit |
| `modules/agenda/repositories/ScheduleRepository.php` | modificar | fuente schedule | DEC-014A / schedule | medio | 0 | adapter | legacy table select |
| `modules/agenda/repositories/AppointmentWriteRepository.php` | modificar | lifecycle/Clinical boundary | DEC-014H/I / lifecycle | alto | 0 | adapter/outbox decision | flag off |
| `modules/agenda/services/OtpSender.php` | modificar | sustituir dev sender runtime | DEC-014D / public | alto | 0 | provider port | rejecting sender |
| `modules/agenda/config/agenda.php` | modificar | registro flags false | DEC-014H / todos | alto | 0 | config owner | all false |
| `modules/agenda/tests/Cut01AAuthorityCompositionRootsTest.php` | nuevo/prueba | Agenda authority | DEC-014F / actor | bajo | 0 | roots | revert test |
| `modules/patients/tests/Cut01APatientsAuthorityRoutesTest.php` | nuevo/prueba | 8 rutas Patients | DEC-014F / actor | bajo | 0 | root | revert test |
| `modules/agenda/tests/Cut01BScheduleScopeSentinelTest.php` | nuevo/prueba | scope/`__all__` | DEC-014A-C / schedule | bajo | 0 | adapters | revert test |
| `modules/agenda/tests/Cut01CAgendaOtpPrivacyTest.php` | nuevo/prueba | OTP/rate | DEC-014D/E / public | bajo | 0 | ports | revert test |
| `modules/agenda/tests/Cut01CRequestDdlContainmentTest.php` | nuevo/prueba | no DDL request | DEC-014H / todos | bajo | 0 | migrations | revert test |
| `modules/platform/tests/Cut01DObservabilityFailurePolicyTest.php` | nuevo/prueba | audit/metrics | DEC-014F / shadow | bajo | 0 | sink | revert test |
| `modules/platform/tests/Cut01DFeatureFlagDefaultsTest.php` | nuevo/prueba | once false | DEC-014H / todos | bajo | 0 | config | revert test |
| `modules/agenda/tests/Cut01DClinicalBoundaryHarnessTest.php` | nuevo/prueba | outbox/bridge | DEC-014I / lifecycle | bajo | 0 | Clinical contract | revert test |
| `modules/agenda/tests/Cut01DRollbackContractTest.php` | nuevo/prueba | rollback stages | DEC-014H / todos | bajo | 0 | runbook | revert test |
| `modules/agenda/tests/Cut01CErrorContractAntiEnumerationTest.php` | nuevo/prueba | errores homogéneos | DEC-014E / public | bajo | 0 | controllers | revert test |
| `docs/MXMED_IMPLEMENTACION_V2_PG03_CUT01_A.md` | nuevo/doc | evidencia subgate A | DEC-014F / actor | bajo | 0 | aprobación | revert doc |
| `docs/MXMED_IMPLEMENTACION_V2_PG03_CUT01_B.md` | nuevo/doc | evidencia subgate B | DEC-014A-C / schedule | bajo | 0 | aprobación | revert doc |
| `docs/MXMED_IMPLEMENTACION_V2_PG03_CUT01_C.md` | nuevo/doc | evidencia subgate C | DEC-014D/E/H / public | bajo | 0 | aprobación | revert doc |
| `docs/MXMED_IMPLEMENTACION_V2_PG03_CUT01_D.md` | nuevo/doc | evidencia subgate D | DEC-014F-I / shadow | bajo | 0 | aprobación | revert doc |

`PROPOSED_NEW_FILES=26`; `PROPOSED_MODIFIED_FILES=16`; `PROPOSED_TEST_FILES=10`; `PROPOSED_TOTAL_VERSIONED_SCOPE=42`. La evidencia runtime que motiva los puntos de integración está en ambos routers y la matriz de hallazgos (`api/agenda/index.php:907`, `api/patients/index.php:27`, `docs/MXMED_AUDITORIA_V2_PG03_RUNTIME_CUTOVER_READINESS.md:82`).

Exclusiones del inventario: SQL/migraciones productivas, cambios `assets/js`, Clinical runtime, AWS, UI, `localStorage`, patient merge, activación R1–R4 y cualquier archivo no revalidado tras aprobación.

## 20. Subgates CUT01-A–D

### CUT01-A — Authority composition roots

- **Objetivo:** roots Agenda/Patients con sesión, membership, ownership, Gate 6B y flags false.
- **Prerrequisitos/decisiones:** DEC-014F/H y threat model aprobados.
- **Archivos propuestos:** ambos composition roots, routers y dos pruebas A.
- **Inclusiones/exclusiones:** authority shadow; sin cambios de respuesta, ruta o UI.
- **Pruebas/PASS:** matriz 49 rutas, deny mismatch, flags false y cero side effects.
- **Rollback/bloqueo:** apagar flag+revert; bloquea B si autoridad no es fail-closed.

La brecha corresponde a F-001/F-002/F-004 (`docs/MXMED_AUDITORIA_V2_PG03_RUNTIME_CUTOVER_READINESS.md:84`, `docs/MXMED_AUDITORIA_V2_PG03_RUNTIME_CUTOVER_READINESS.md:87`). Estado: `PENDING_DIRECTOR_APPROVAL`.

### CUT01-B — Schedule, scope and sentinel adapters

- **Objetivo:** adapter schedule read, consultorio scope y `__all__`, sin activar precedencia nueva.
- **Prerrequisitos/decisiones:** DEC-014A–C aprobadas y A PASS.
- **Archivos propuestos:** adapters schedule/availability, controllers y prueba B.
- **Inclusiones/exclusiones:** compare read-only; no writes ni retiro UI/localStorage.
- **Pruebas/PASS:** paridad por scope/timezone, incomplete fail-closed, sentinel nunca write.
- **Rollback/bloqueo:** flags off+legacy read; bloquea C ante scope ambiguo.

La brecha corresponde a F-006/F-026 (`docs/MXMED_AUDITORIA_V2_PG03_RUNTIME_CUTOVER_READINESS.md:89`, `docs/MXMED_AUDITORIA_V2_PG03_RUNTIME_CUTOVER_READINESS.md:109`). Estado: `PENDING_DIRECTOR_APPROVAL`.

### CUT01-C — OTP, DDL containment and privacy boundaries

- **Objetivo:** provider port, rate-limit contract y retiro planificado de DDL on-request con cero OTP real.
- **Prerrequisitos/decisiones:** DEC-014D/E/H, Security y migraciones externas aprobadas.
- **Archivos propuestos:** OTP ports/controllers/sender, seis componentes DDL y tres pruebas C.
- **Inclusiones/exclusiones:** rejecting/sandbox harness; no provider real, DB ni UI.
- **Pruebas/PASS:** no raw OTP/PII, respuestas homogéneas, request sin DDL, flags false.
- **Rollback/bloqueo:** public kill switch+revert; bloquea D si schema/error contract no es seguro.

La brecha corresponde a F-012/F-013/F-018 (`docs/MXMED_AUDITORIA_V2_PG03_RUNTIME_CUTOVER_READINESS.md:95`, `docs/MXMED_AUDITORIA_V2_PG03_RUNTIME_CUTOVER_READINESS.md:101`). Estado: `PENDING_DIRECTOR_APPROVAL`.

### CUT01-D — Observability and Clinical boundary harness

- **Objetivo:** métricas, audit sink, kill switches y harness Clinical; outbox/saga sólo si se aprueba.
- **Prerrequisitos/decisiones:** DEC-014F–I, A–C PASS y owners/on-call.
- **Archivos propuestos:** observability/flags ports, persistence/lifecycle adapters y cuatro pruebas D.
- **Inclusiones/exclusiones:** harness R0; no Clinical runtime, writes, migration o rollout.
- **Pruebas/PASS:** failure policy, once flags false, rollback contract, idempotency/orden simulados.
- **Rollback/bloqueo:** todo off+revert; Actividad 11 bloqueada hasta PASS y aprobación separada.

La brecha corresponde a F-009/F-010/F-017/F-023/F-030 (`docs/MXMED_AUDITORIA_V2_PG03_RUNTIME_CUTOVER_READINESS.md:92`, `docs/MXMED_AUDITORIA_V2_PG03_RUNTIME_CUTOVER_READINESS.md:100`, `docs/MXMED_AUDITORIA_V2_PG03_RUNTIME_CUTOVER_READINESS.md:113`). Estado: `PENDING_DIRECTOR_APPROVAL`.

## 21. Riesgos

Riesgos principales: elevación por claims cliente, scope cruzado, divergencia schedule, doble reserva, OTP/PII, DDL en request, auditoría incompleta, rollback sin reconciliación y pérdida de evento Clinical. Todos aparecen en la matriz auditada y ninguno se cierra documentalmente (`docs/MXMED_AUDITORIA_V2_PG03_RUNTIME_CUTOVER_READINESS.md:84`, `docs/MXMED_AUDITORIA_V2_PG03_RUNTIME_CUTOVER_READINESS.md:113`).

## 22. Decisiones pendientes

DEC-014A–I requieren aprobación de sus owners. No se fijan valores numéricos de horario, rate limit, percentiles, porcentajes, ventanas, retención, RTO/RPO ni volumen (`docs/MXMED_DECISIONES_PROPUESTAS_V2_PG03_CUT01.md:15`).

## 23. Safe return

El safe return versionado es `1e3057f2a12afed9da7e6ce95cd20ae81d645c1f`; esta actividad sólo agrega dos documentos y PP-312, por lo que un revert aislado debe reproducir su árbol sin SQL (`/tmp/mxmed-activity10-cut01-scope-decisions-readiness-preflight-v2/safe-return-points.txt:1`).

## 24. Exclusiones

Conexiones DB, migraciones, SQL, datos, OTP, citas, pacientes, merges, backfill, R1–R4, runtime, rutas, configuración, PHP, JS/CSS/HTML, UI, Clinical y AWS permanecen en cero por contrato (`/tmp/mxmed-activity10-cut01-scope-decisions-readiness-preflight-v2/activity10-approved-contract.txt:24`).

## 25. Estado final

- Actividad 9: `CLOSED_AND_INTEGRATED`; el paquete de integración registra el commit en programa (`/tmp/mxmed-activity09-integration-v2-r2/qa-result.json:1`).
- Actividad 10: `SCOPE_REVIEW_COMPLETE_READY_FOR_DIRECTOR_DECISIONS_NOT_INTEGRATED`.
- Actividad 11: `BLOCKED`; implementación CUT-01 no autorizada (`/tmp/mxmed-activity10-cut01-scope-decisions-readiness-preflight-v2/director-approval.txt:6`).
- Contador: `9/22`; pendientes: `13`; cutover `NO_GO_BLOCKERS_PRESENT`; readiness `NO_GO_LEGACY_BLOCKERS_PRESENT` (`docs/MXMED_AUDITORIA_V2_PG03_RUNTIME_CUTOVER_READINESS.md:202`).

Hash normalizado de PP-312: `d10ba72afe2cdabcd0464e563b9293c679e77c289569ae60c9f330968af3d1ed`.
