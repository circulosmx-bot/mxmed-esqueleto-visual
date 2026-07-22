# MXMed V2 · PG-03 · Decisiones propuestas para CUT-01

## Límite de autoridad

Este registro formula nueve decisiones técnicas para la Actividad 10 UI-0; la aprobación recibida cubre el análisis de alcance, pero no autoriza implementación ni resuelve las decisiones (`/tmp/mxmed-activity10-cut01-scope-decisions-readiness-preflight-v2/activity10-approved-contract.txt:1`, `/tmp/mxmed-activity10-cut01-scope-decisions-readiness-preflight-v2/director-approval.txt:1`).

Todas las recomendaciones permanecen `PENDING_DIRECTOR_APPROVAL`. No se implementan adapters, flags, wiring, SQL, migraciones, backfill, OTP, cambios Clinical, UI, rutas ni rollout (`/tmp/mxmed-activity10-cut01-scope-decisions-readiness-preflight-v2/activity10-approved-contract.txt:24`).

## Tabla resumen

| Decisión | D original | Recomendación técnica | Blocker | Gate futuro | Aprobador requerido | Estado |
|---|---|---|---|---|---|---|
| DEC-014A | D-02 | schedule versionado por profile+consultorio y precedencia explícita | F-006 | CUT01-B | Producto Agenda + Arquitectura | PENDING_DIRECTOR_APPROVAL |
| DEC-014B | D-03 | scope concreto; partición de legacy ambiguo antes de backfill | F-005,F-022 | CUT01-B | Arquitectura + Datos | PENDING_DIRECTOR_APPROVAL |
| DEC-014C | D-04 | `__all__` sólo agregado; resolver o rechazar antes de write | F-026 | CUT01-B | Producto Agenda | PENDING_DIRECTOR_APPROVAL |
| DEC-014D | D-07 | port neutral SMS/email, sandbox y kill switch | F-011,F-012 | CUT01-C | Seguridad + Operaciones | PENDING_DIRECTOR_APPROVAL |
| DEC-014E | D-08 | límites opacos y respuestas homogéneas; cifras sin resolver | F-012,F-027 | CUT01-C | Seguridad | PENDING_DIRECTOR_APPROVAL |
| DEC-014F | D-09 | métricas sin PII y auditoría con política de fallo por riesgo | F-003,F-019,F-028 | CUT01-D | SRE + Seguridad | PENDING_DIRECTOR_APPROVAL |
| DEC-014G | D-10 | medir baseline antes de umbrales y aprobar cada etapa | F-023,F-024,F-028 | CUT01-D | Dirección técnica | PENDING_DIRECTOR_APPROVAL |
| DEC-014H | D-13 | flags false, kill switch y rollback por etapa | F-023,F-029 | CUT01-D | SRE + DBA | PENDING_DIRECTOR_APPROVAL |
| DEC-014I | D-17 | outbox como objetivo; bridge actual contenido hasta aprobación | F-030 | CUT01-D | Clinical + Arquitectura | PENDING_DIRECTOR_APPROVAL |

Los mapeos D, blockers y owners proceden del registro auditado de Actividad 9 (`docs/MXMED_AUDITORIA_V2_PG03_RUNTIME_CUTOVER_READINESS.md:175`, `docs/MXMED_AUDITORIA_V2_PG03_RUNTIME_CUTOVER_READINESS.md:179`).

## DEC-014A — Duración, gap y precedencia de horario

- **ID / mapeo:** DEC-014A / D-02.
- **Problema:** el controller legacy aporta defaults y listas numéricas, mientras el contrato canónico calcula slots con duración y gap de una versión de horario; no existe una precedencia de cutover aprobada (`modules/agenda/controllers/AgendaSettingsController.php:88`, `modules/agenda/availability/CanonicalAvailabilityCalculator.php:74`).
- **Evidencia:** el repositorio busca entre cinco nombres de tabla y consulta por doctor+consultorio (`modules/agenda/repositories/ScheduleRepository.php:13`, `modules/agenda/repositories/ScheduleRepository.php:31`); la auditoría clasifica el conflicto como F-006 BLOCKER (`docs/MXMED_AUDITORIA_V2_PG03_RUNTIME_CUTOVER_READINESS.md:89`).
- **Opciones:** A) configuración global; B) configuración por profile; C) versión por profile+consultorio con override de sede; D) mantener legacy sin autoridad canónica.
- **Ventajas:** C hace explícitos scope, timezone, holiday closure, close override, reopen override y colisiones dentro de una selección versionada.
- **Riesgos:** C requiere definir orden global/sede y fallo ante configuración incompleta; A/B pueden filtrar configuración entre sedes.
- **Recomendación técnica:** C; sede explícita sobre global sólo tras aprobación, cierre festivo antes de reopen explícito, colisiones siempre restan, y ausencia/ambigüedad produce error fail-closed. No se fijan duración, gap ni timezone nuevos.
- **Información no resuelta:** valores, catálogo de timezone, precedencia final y política de configuración incompleta.
- **Impacto CUT-01:** habilita el contrato del adapter de lectura, no su wiring.
- **Gate futuro:** CUT01-B.
- **Responsable de aprobación:** Producto Agenda + Arquitectura.
- **Estado:** `PENDING_DIRECTOR_APPROVAL`.

## DEC-014B — `consultorio_scope` y frontera de backfill

- **ID / mapeo:** DEC-014B / D-03.
- **Problema:** el contrato canónico exige consultorio y el runtime conserva registros con scope agregado; Actividad 9 no midió volúmenes porque hacerlo requeriría DB (`modules/agenda/availability/AvailabilityCalculationRequest.php:23`, `docs/MXMED_AUDITORIA_V2_PG03_RUNTIME_CUTOVER_READINESS.md:167`).
- **Evidencia:** el schema declarativo contiene `consultorio_scope` y la auditoría exige scope concreto antes del backfill (`docs/MXMED_AUDITORIA_V2_PG03_RUNTIME_CUTOVER_READINESS.md:183`).
- **Opciones:** A) asignación automática; B) partición `resolved`, `ambiguous`, `unscoped`; C) rechazo total del legacy sin sede.
- **Ventajas:** B preserva incertidumbre y permite revisión sin inventar sedes.
- **Riesgos:** A puede atribuir datos incorrectamente; C bloquea compatibilidad legítima.
- **Recomendación técnica:** exigir scope concreto para autoridad y writes; separar legacy sin sede o ambiguo en revisión; no promoverlo hasta evidencia y reconciliación.
- **Información no resuelta:** volumen, reglas de atribución, owner, ventana y política de scopes ambiguos.
- **Impacto CUT-01:** define preflight y puerto de clasificación; prohíbe writes, migraciones y backfill en Actividad 10.
- **Gate futuro:** CUT01-B para contrato; ejecución sólo en gate de datos posterior aprobado.
- **Responsable de aprobación:** Arquitectura + Datos.
- **Estado:** `PENDING_DIRECTOR_APPROVAL`.

## DEC-014C — Retiro seguro de `__all__`

- **ID / mapeo:** DEC-014C / D-04.
- **Problema:** Waitlist admite `__all__` al crear scope agregado, aunque ya lo rechaza como consultorio de asignación (`modules/agenda/controllers/WaitlistController.php:21`, `modules/agenda/controllers/WaitlistController.php:233`).
- **Evidencia:** la UI también conserva el sentinel y F-026 lo clasifica HIGH (`docs/MXMED_AUDITORIA_V2_PG03_RUNTIME_CUTOVER_READINESS.md:109`).
- **Opciones:** A) conservar para todo; B) conservar sólo para consultas agregadas; C) retirarlo inmediatamente.
- **Ventajas:** B mantiene búsquedas compatibles sin convertir el sentinel en identidad persistible.
- **Riesgos:** A permite destinos no concretos; C puede romper Waitlist/UI antes de un adapter.
- **Recomendación técnica:** B; prohibirlo como destino de cita, claim o write, resolver a consultorio concreto antes de persistir y rechazar fail-closed si no existe resolución única.
- **Información no resuelta:** UX de selección, compatibilidad temporal y plazo de retiro.
- **Impacto CUT-01:** contrato de sentinel adapter y casos negativos; cero cambio actual a `__all__` o UI.
- **Gate futuro:** CUT01-B.
- **Responsable de aprobación:** Producto Agenda.
- **Estado:** `PENDING_DIRECTOR_APPROVAL`.

## DEC-014D — Canal y proveedor OTP

- **ID / mapeo:** DEC-014D / D-07.
- **Problema:** el sender por defecto registra destinatario y OTP crudo, y el controller puede devolver debug en QA (`modules/agenda/services/OtpSender.php:18`, `modules/agenda/controllers/PublicOtpController.php:123`).
- **Evidencia:** F-012 es BLOCKER y el contrato 8E prohíbe secretos raw fuera del límite de entrega (`docs/MXMED_AUDITORIA_V2_PG03_RUNTIME_CUTOVER_READINESS.md:95`, `modules/agenda/contracts/PublicOtpContract.php:17`).
- **Opciones:** A) SMS; B) email; C) ambos por política; D) sender dev sólo en harness aislado.
- **Ventajas:** un port neutral permite cambiar proveedor y separar sandbox/producción.
- **Riesgos:** elegir marca ahora elude procurement, residencia, SLA y revisión de privacidad.
- **Recomendación técnica:** port server-side neutral para SMS y/o email según contrato aprobado; secreto nunca retornado ni registrado; challenge opaco; telemetría sin PII; errores homogéneos; rotación, health, sandbox y kill switch obligatorios.
- **Información no resuelta:** proveedor, canal por caso, SLA, jurisdicción, credenciales y owner operativo.
- **Impacto CUT-01:** definir port y adapter rejecting; cero envío real.
- **Gate futuro:** CUT01-C.
- **Responsable de aprobación:** Seguridad + Operaciones.
- **Estado:** `PENDING_DIRECTOR_APPROVAL`.

## DEC-014E — Reintentos, rate limiting y anti-enumeración

- **ID / mapeo:** DEC-014E / D-08.
- **Problema:** el flow legacy expone intentos restantes y usa límites numéricos locales; la decisión requerida no tiene cifras aprobadas (`modules/agenda/controllers/PublicAppointmentsController.php:242`, `docs/MXMED_PLAN_V2_PG03_RUNTIME_CUTOVER_GATES.md:43`).
- **Evidencia:** el dominio 8E modela replay, expiración y bloqueo sin autorizar wiring (`docs/MXMED_IMPLEMENTACION_V2_PG03_GATE_8E_AGENDA_PUBLICA_OTP_PRIVACIDAD_CONTACTO.md:141`).
- **Opciones:** límites por challenge; claves opacas derivadas de IP/contact/profile; ventanas escalonadas; bloqueo temporal; combinación de las anteriores.
- **Ventajas:** combinación multicapa reduce abuso sin usar PII como label.
- **Riesgos:** cifras arbitrarias causan lockout o protección insuficiente; mensajes distintos permiten enumeración.
- **Recomendación técnica:** respuestas homogéneas, consumo único, expiración, replay deny, bloqueo temporal y claves HMAC opacas por challenge/IP/contact/profile.
- **Información no resuelta:** intentos y ventanas quedan `UNRESOLVED_PENDING_SECURITY_APPROVAL`.
- **Impacto CUT-01:** contrato y pruebas deterministas; sin activar rate limits runtime.
- **Gate futuro:** CUT01-C.
- **Responsable de aprobación:** Seguridad.
- **Estado:** `PENDING_DIRECTOR_APPROVAL`.

## DEC-014F — Observabilidad, audit sink y política de fallo

- **ID / mapeo:** DEC-014F / D-09.
- **Problema:** Gate 8G sólo enumera métricas y el runtime no confirma sinks/dashboards PG-03 (`modules/patients/identity/persistence/PatientIdentityRolloutPolicy.php:10`, `docs/MXMED_AUDITORIA_V2_PG03_RUNTIME_CUTOVER_READINESS.md:111`).
- **Evidencia:** el audit trail transversal define un port y adapters separados (`modules/platform/contracts/AuditTrailPort.php:7`, `docs/MXMED_IMPLEMENTACION_V2_PG08_GATE_6D_AUDIT_TRAIL_TRANSVERSAL.md:81`).
- **Opciones:** métricas best-effort; auditoría fail-closed; política por evento/riesgo.
- **Ventajas:** política por riesgo evita que una métrica secundaria derribe lecturas, sin perder trazabilidad de autoridad o writes.
- **Riesgos:** fail-open universal pierde evidencia; fail-closed universal puede degradar disponibilidad.
- **Recomendación técnica:** correlation IDs opacos, métricas sin PII, dashboard/alertas y owner/on-call; fallo de métrica fail-open con alerta, auditoría de autoridad/write fail-closed, shadow read según matriz aprobada; health y readiness separados.
- **Información no resuelta:** sink, retención, on-call, SLO y matriz final de eventos.
- **Impacto CUT-01:** ports/harness únicamente.
- **Gate futuro:** CUT01-D.
- **Responsable de aprobación:** SRE + Seguridad.
- **Estado:** `PENDING_DIRECTOR_APPROVAL`.

## DEC-014G — Métricas y umbrales R1–R4

- **ID / mapeo:** DEC-014G / D-10.
- **Problema:** las etapas existen de forma declarativa, pero no hay baseline ni budgets aprobados (`modules/patients/identity/persistence/PatientIdentityRolloutPolicy.php:8`, `docs/MXMED_PLAN_V2_PG03_RUNTIME_CUTOVER_GATES.md:17`).
- **Evidencia:** F-023/F-024 bloquean activación sin flags, métricas y aprobaciones (`docs/MXMED_AUDITORIA_V2_PG03_RUNTIME_CUTOVER_READINESS.md:106`, `docs/MXMED_AUDITORIA_V2_PG03_RUNTIME_CUTOVER_READINESS.md:107`).
- **Opciones:** umbral fijo anticipado; baseline observado; aprobación progresiva por scope.
- **Ventajas:** baseline + aprobación progresiva vincula budgets al comportamiento medido.
- **Riesgos:** cifras inventadas crean garantías falsas y hard stops inadecuados.
- **Recomendación técnica:** R0 instrumenta harness; R1 mide mismatch/deny/latencia; R2 añade audit health; R3 añade diff/reconciliación; R4 añade writes/conflicts/rollback. Cada etapa necesita ventana, error budget, hard stop y aprobación.
- **Información no resuelta:** todo p95, p99, porcentaje y duración queda `UNRESOLVED_PENDING_BASELINE_AND_DIRECTOR_APPROVAL`.
- **Impacto CUT-01:** catálogo de métricas y contratos, no activación.
- **Gate futuro:** CUT01-D y aprobación nuevamente antes de cada R.
- **Responsable de aprobación:** Dirección técnica.
- **Estado:** `PENDING_DIRECTOR_APPROVAL`.

## DEC-014H — Kill switch, rollback y runbook

- **ID / mapeo:** DEC-014H / D-13.
- **Problema:** R0–R4 son declarativos y no existe rollback productivo aprobado (`modules/patients/identity/persistence/PatientIdentityRolloutPolicy.php:8`, `docs/MXMED_AUDITORIA_V2_PG03_RUNTIME_CUTOVER_READINESS.md:112`).
- **Evidencia:** el safe return propuesto vuelve R1/R2 a R0, R3 a lectura legacy y exige snapshot/reconciliación para R4 (`docs/MXMED_PLAN_V2_PG03_RUNTIME_CUTOVER_GATES.md:124`).
- **Opciones:** revert de código; flags; runbook por etapa; combinación controlada.
- **Ventajas:** flags default false y runbooks independientes limitan blast radius.
- **Riesgos:** revert Git no reconcilia datos; force-push/reset destruye trazabilidad.
- **Recomendación técnica:** flags server-side `false`; R1/R2→R0; R3→lectura legacy; R4 detiene writes, preserva snapshot/checkpoint, reconcilia y sólo después decide retorno; owner primario/secundario y drill obligatorios; prohibidos reset/force-push.
- **Información no resuelta:** owners, RTO/RPO, snapshot y criterios de reconciliación.
- **Impacto CUT-01:** contrato de kill switch y runbook; no rollback SQL en esta actividad.
- **Gate futuro:** CUT01-D.
- **Responsable de aprobación:** SRE + DBA.
- **Estado:** `PENDING_DIRECTOR_APPROVAL`.

## DEC-014I — Frontera Agenda → Clinical

- **ID / mapeo:** DEC-014I / D-17.
- **Problema:** Agenda confirma su transacción y después llama un bridge Clinical cuyo error se registra sin revertir la cita (`modules/agenda/repositories/AppointmentWriteRepository.php:86`, `modules/agenda/repositories/AppointmentWriteRepository.php:99`, `modules/agenda/repositories/AppointmentWriteRepository.php:868`).
- **Evidencia:** el contrato declara que cita Agenda no equivale a encounter Clinical; el bridge usa `appointment_id` y consulta antes de POST (`modules/agenda/contracts/AppointmentLifecycleContract.php:40`, `modules/agenda/services/ClinicalEncounterBridge.php:38`, `modules/agenda/services/ClinicalEncounterBridge.php:76`).
- **Opciones:** 1) bridge post-commit actual; 2) transactional outbox; 3) coordinación/saga; 4) transacción distribuida.
- **Ventajas:** outbox conserva ownership del evento en Agenda, permite idempotencia/retries/orden y observa fallo post-commit; saga sirve si se aprueban compensaciones entre dominios.
- **Riesgos:** bridge puede perder entrega; outbox requiere persistencia/worker; saga aumenta estados; transacción distribuida carece de soporte real demostrado.
- **Recomendación técnica:** outbox como objetivo sujeto a aprobación; evento propiedad de Agenda con `appointment_id` como clave de idempotencia, retries acotados, orden por agregado y DLQ/alerta; compensación sólo por saga aprobada. No recomendar transacción distribuida.
- **Información no resuelta:** owner consumidor, esquema de evento, retención, retry/DLQ y compensaciones.
- **Impacto CUT-01:** harness y contrato; Clinical queda excluido de cambios.
- **Gate futuro:** CUT01-D.
- **Responsable de aprobación:** Clinical + Arquitectura.
- **Estado:** `PENDING_DIRECTOR_APPROVAL`.

## Estado del registro

Decisiones propuestas: 9. Decisiones aprobadas: 0. El siguiente paso permitido es revisión directorial; Actividad 11 y la implementación de CUT-01 siguen bloqueadas (`/tmp/mxmed-activity10-cut01-scope-decisions-readiness-preflight-v2/director-approval.txt:6`).

Hash normalizado de PP-312: `d10ba72afe2cdabcd0464e563b9293c679e77c289569ae60c9f330968af3d1ed`.
