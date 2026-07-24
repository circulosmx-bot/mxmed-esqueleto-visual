# MXMed V2 · PG-03 · CUT-02A R0 Shadow Harness

## Identidad

- Programa: MXMed Product Refinement V2.
- Actividad: 17/22.
- Nombre: CUT-02A — R0 Shadow Harness and Safe-Return Readiness.
- Identificador: `BE-ARCH/MXMed-PG03-CUT02-A-R0-Shadow-Harness-Safe-Return-01`.
- Fecha: 2026-07-23.
- Clasificación: UI-0.
- Tipo: implementación offline determinista, sin wiring runtime.

## Baseline y checkpoint

- Parent: `11c42909c3b077c3171932242fb1de08fbcafa21`.
- Checkpoint 16: `checkpoint/mxmed-product-refinement-v2-activity16`.
- Objeto anotado: `78a7747ca9b5128685ad060bee05a1b39f75a6cf`.
- Rama: `feature/mxmed-pg03-cut02a-r0-shadow-harness-v2`.
- Safe return Git: revert sin commit en worktree detached temporal hacia el tree del parent.

## Autorización y límite

La autorización cubre exclusivamente un harness de prueba offline con fixtures manuales. No autoriza implementación general de CUT-02, wiring, tráfico, baseline, sampling, R1 o R2.

```text
R0_HARNESS_IMPLEMENTED=true
R0_HARNESS_RUNTIME_WIRED=false
R0_HARNESS_REAL_TRAFFIC=false
CUT02_IMPLEMENTATION_AUTHORIZED=false
R1_ACTIVATION_AUTHORIZED=false
R2_ACTIVATION_AUTHORIZED=false
BASELINE_COLLECTION_AUTHORIZED=false
SAMPLING=0
ROLLOUT_STAGE=R0
ROLLOUT_MODE=disabled
```

## Alcance versionado

El alcance es exactamente `8 nuevos + 1 modificado = 9`.

Archivos nuevos:

1. `modules/platform/shadow/R0ShadowEvaluationHarness.php`
2. `modules/platform/shadow/R0ShadowEvaluationResult.php`
3. `modules/platform/shadow/R0ShadowHardStop.php`
4. `modules/platform/shadow/R0ShadowSafeReturnPlan.php`
5. `modules/platform/tests/Cut02AR0ShadowHarnessTest.php`
6. `modules/platform/tests/Cut02ALegacyInvarianceTest.php`
7. `modules/platform/tests/Cut02AHardStopSafeReturnTest.php`
8. `docs/MXMED_IMPLEMENTACION_V2_PG03_CUT02_A_R0_SHADOW_HARNESS.md`

Único archivo modificado: `docs/PLAN_MAESTRO_MXMED.md` para PP-319. No existe archivo décimo.

## Arquitectura de cuatro clases

`R0ShadowHardStop` mantiene el catálogo cerrado, ordenado y validable de códigos. `R0ShadowSafeReturnPlan` es un valor inmutable que proyecta únicamente el retorno lógico R0. `R0ShadowEvaluationResult` conserva campos enumerados, referencias opacas de prueba, digests y, cuando corresponde, el hard stop con su plan. `R0ShadowEvaluationHarness` valida y compara snapshots sin importar composition roots, adapters, configuración ni servicios existentes.

Las cuatro clases son `final`, usan tipos estrictos y carecen de I/O, reloj, aleatoriedad, ambiente y estado global.

## Superficies elegibles

La allowlist cerrada contiene exactamente:

```text
canonical_actor_authority
canonical_schedule_read
canonical_availability_compare
canonical_appointment_lifecycle
canonical_patient_identity
```

`canonical_public_agenda`, `shadow_audit`, `patient_identity_persistence`, `legacy_write_disable`, `read_compare`, `backfill` y cualquier otro valor producen `UNKNOWN_OPERATION`; no se incorporan como superficies.

## Catálogo de hard stops

El catálogo contiene, en orden estable, exactamente:

```text
PII_OR_CLINICAL_DATA_DETECTED
LEGACY_RESPONSE_CHANGED
HTTP_STATUS_CHANGED
HTTP_HEADERS_CHANGED
PAYLOAD_CHANGED
CANONICAL_WRITE_ATTEMPTED
NEW_DB_CONNECTION_ATTEMPTED
SQL_OR_DDL_ATTEMPTED
REAL_OTP_ATTEMPTED
CLINICAL_REQUEST_ATTEMPTED
SCOPE_LEAKAGE_DETECTED
AUTHORITY_AUDIT_UNAVAILABLE
UNKNOWN_OPERATION
UNEXPECTED_SIDE_EFFECT
BUDGET_BREACH_AFTER_APPROVAL
```

`BUDGET_BREACH_AFTER_APPROVAL` es sólo declarativo: no fija cifra, porcentaje, percentil, threshold o budget. Una diferencia única de status, headers o payload produce el código específico; múltiples dimensiones de respuesta diferentes producen `LEGACY_RESPONSE_CHANGED`.

## Schema cerrado de fixture

El fixture exige versión `1` y exactamente:

```text
fixture_version
surface
correlation_ref
scope_ref
legacy
canonical
side_effects
authority_audit_available
```

Cada snapshot `legacy` y `canonical` contiene exclusivamente:

```text
status
headers
payload
outcome_code
```

Las referencias cumplen el patrón opaco de pruebas `test:*`. Los códigos de outcome y reason usan el patrón cerrado uppercase. El resultado no conserva payloads, headers ni identificadores de entrada: sólo códigos, referencias de prueba, digests, invariancia y safe return.

## Invariancia legacy

La comparación normaliza headers con keys lowercase, orden lexical y valores preservados. No descarta headers. El payload se canonicaliza recursivamente: los objetos se ordenan por key, los arrays conservan orden y los scalars conservan tipo y valor. Se rechazan objetos arbitrarios, closures, resources y floats no finitos.

La invariancia exige igualdad estricta de status, headers normalizados y payload canonicalizado:

```text
same_status=true
same_headers=true
same_payload=true
legacy_invariant=true
canonical_response_allowed=false
canonical_write_allowed=false
```

Los outcomes diagnóstico legacy y canonical pueden diferir sin alterar la respuesta. El digest SHA-256 representa la respuesta normalizada, no el código diagnóstico.

## Sanitización y privacidad

Una denylist cerrada inspecciona keys a cualquier profundidad y detiene el fixture ante `patient_id`, `appointment_id`, `doctor_id`, `name`, `phone`, `email`, `address`, `birthdate`, `contact`, `otp`, `token`, `diagnosis`, `clinical`, `notes`, `cookies`, `authorization`, `password` o `secret`.

No se usa NLP ni se infiere sensibilidad por texto libre. Al detectar una key prohibida, el resultado usa códigos redactados y digests deterministas de redacción; el valor sensible no se serializa ni se conserva.

## Determinismo

Los snapshots de entrada no se mutan. La serialización tiene orden fijo, los objetos JSON ordenan sus keys, los arrays preservan posición y los digests usan SHA-256 lowercase. Un mismo fixture produce byte-for-byte el mismo arreglo de resultado. No existen timestamps automáticos, UUID aleatorio, reloj, filesystem, red, environment ni estado global.

## Side effects declarativos

El mapa cerrado contiene siete booleanos:

```text
canonical_write_attempted
new_db_connection_attempted
sql_or_ddl_attempted
real_otp_attempted
clinical_request_attempted
scope_leakage_detected
unexpected_side_effect
```

Cada booleano `true` se traduce al hard stop homónimo en prioridad estable. Todos deben permanecer `false` para un resultado normal. `authority_audit_available=false` sólo detiene `canonical_actor_authority`; no introduce esa dependencia en las otras cuatro superficies. No se escribe auditoría.

## Safe return

Cada hard stop proyecta:

```text
source_stage=R0
source_mode=disabled
target_stage=R0
target_mode=disabled
new_evaluations_allowed=false
legacy_continues=true
canonical_response_allowed=false
canonical_write_allowed=false
preserve_sanitized_evidence=true
sql_rollback_required=false
database_action=none
clinical_action=none
otp_action=none
```

El plan es declarativo, inmutable y determinista. No ejecuta flags, rollback SQL, mutaciones ni llamadas externas.

## Pruebas

`Cut02AR0ShadowHarnessTest` valida superficies, versión, referencias, schema, determinismo, digests, inmutabilidad, serialización, sampling cero y R0 disabled. `Cut02ALegacyInvarianceTest` valida igualdad y diferencias de status, headers y payload, canonicalización y continuidad legacy. `Cut02AHardStopSafeReturnTest` valida los quince códigos, prioridad, privacidad, side effects y el plan de retorno.

Resultado requerido:

```text
ACTIVITY17_NEW_TESTS=3/3
REGRESSION_TESTS=22/22
TOTAL_TESTS=25/25
NEW_PHP_LINT=7/7
BASELINE_PHP_LINT=11/11
TOTAL_PHP_LINT=18/18
```

## Límites y parámetros diferidos

Sampling continúa en cero. Baseline collection y observation window no están aprobados. Sink, provider, thresholds, p95, p99, SLO, error budgets, retención, owners, on-call, plataformas, rutas, timeouts, residencia, jurisdicción, key management, RTO y RPO continúan `UNRESOLVED_PENDING_PARAMETER_APPROVAL` o `UNRESOLVED_PENDING_BASELINE_AND_DIRECTOR_APPROVAL`, según corresponda.

Los once feature flags permanecen false y efectivamente deshabilitados. El harness no lee ni altera su configuración.

## Cero efectos reales

```text
RUNTIME_WIRING=0
SHADOW_TRAFFIC_PROCESSED=0
AUDIT_EVENTS_WRITTEN=0
METRICS_EMITTED=0
DATABASE_CONNECTIONS_OPENED=0
SQL_EXECUTED=0
DDL_EXECUTED=0
PERSISTENCE_WRITES=0
BACKFILL_EXECUTED=0
REAL_OTP_SENT=0
CLINICAL_REQUESTS_EXECUTED=0
```

No hay requests, datos reales, DB, SQL, DDL, persistencia, backfill, OTP, Clinical, métricas o auditoría real.

## Git, rollback y estado final

El cambio se entrega como un commit atómico sobre el parent, con push normal y sin integración, PR o checkpoint 17. El rollback se verifica en worktree detached temporal aplicando el commit y ejecutando revert sin commit; el tree debe volver exactamente al parent.

Los trece blockers siguen abiertos. Cutover continúa `NO_GO_BLOCKERS_PRESENT` y readiness `NO_GO_LEGACY_BLOCKERS_PRESENT`.

```text
ACTIVITY17=CUT02A_R0_HARNESS_IMPLEMENTED_READY_FOR_POSTVALIDATION_NOT_INTEGRATED
ACTIVITY18=BLOCKED
OFFICIAL_COUNTER=16/22
PENDING_ACTIVITIES=6
INTEGRATED=false
CHECKPOINT_ACTIVITY17_CREATED=false
```
