# CUT-02C — Paquete de baseline sintético y evidencia sanitizada

## Identidad

- Fecha: 2026-07-23.
- Actividad: 19/22.
- Identificador: `QA-ARCH/MXMed-PG03-CUT02-C-Synthetic-Baseline-Evidence-Package-01`.
- Parent: `d765b3270b8085f1f6bec1f321932d82b7dd0f77`.
- Checkpoint 18: `checkpoint/mxmed-product-refinement-v2-activity18`.
- Objeto checkpoint 18: `77cf0686933c8c876bb6644e4d74fb5ef79f7886`.
- Rama: `feature/mxmed-pg03-cut02c-synthetic-baseline-package-v2`.
- Clasificación: UI-0.

## Autorización y alcance exacto

La autorización se limita a ejecutar un baseline sintético offline mediante el harness R0 existente y crear evidencia sanitizada agregada. El alcance versionado es `7 nuevos + 1 modificado = 8`: un catálogo, tres pruebas, tres documentos y PP-321 en el Plan Maestro. No existe archivo noveno.

Los cuatro archivos del harness CUT-02A, sus tres pruebas previas y los dos documentos CUT-02B permanecen protegidos, `PROTECTED_SURFACES=9/9`. No se modifica configuración, flags, runtime, rutas, controllers, repositories, services, adapters, UI, Clinical, SQL, AWS o infraestructura.

## Catálogo y matriz

`tests/fixtures/cut02c-synthetic-baseline/catalog.php` devuelve un catálogo puro, estable y cerrado. Sus cinco superficies y ocho categorías forman exactamente una matriz `5x8`, con 40 IDs sintéticos únicos. Cada fixture usa referencias opacas `test:` y el schema exacto del harness.

```text
ELIGIBLE_SURFACES=5/5
SCENARIO_CATEGORIES=8/8
SYNTHETIC_FIXTURES=40/40
DUPLICATE_COMBINATIONS=0
MISSING_COMBINATIONS=0
EXTRA_COMBINATIONS=0
```

Los hard stops sintéticos elegidos por superficie son `AUTHORITY_AUDIT_UNAVAILABLE`, `CANONICAL_WRITE_ATTEMPTED`, `NEW_DB_CONNECTION_ATTEMPTED`, `CLINICAL_REQUEST_ATTEMPTED` y `SCOPE_LEAKAGE_DETECTED`. La categoría de privacidad utiliza `PII_OR_CLINICAL_DATA_DETECTED`. Estas señales son entradas declarativas; no ejecutan los efectos que nombran.

## Ejecución y schema sanitizado

La prueba principal ejecuta cada fixture exactamente dos veces, sin tercera evaluación. Las 80 ejecuciones se proyectan en memoria al schema cerrado de 18 campos `proposed-v1`, con adapter `r0-shadow-harness-v1` y paquete `cut02c-synthetic-v1`. No se escriben resultados raw.

```text
EXECUTIONS_PER_FIXTURE=2
TOTAL_OFFLINE_EXECUTIONS=80/80
DETERMINISTIC_RESULTS=40/40
SCHEMA_FIELDS=18/18
PASS=25/40
FAIL=10/40
REJECTED=5/40
```

Los FAIL preservan safe return `R0` / `disabled`. Los rechazos convierten la excepción cerrada esperada en un registro `REJECTED` sin fixture, payload, headers, side effects o stack.

## Pruebas, privacidad e integridad

- `Cut02CSyntheticBaselineExecutionTest.php` valida catálogo, matriz, 80 ejecuciones, determinismo, schema, invariancia, hard stops y safe return.
- `Cut02CSanitizedEvidencePrivacyTest.php` inspecciona recursivamente veinte categorías prohibidas y confirma que el rechazo de privacidad no filtra key o valor.
- `Cut02CEvidenceIntegrityTest.php` valida orden, unicidad, cobertura, schema y repetibilidad de los digests agregados.

```text
NEW_TESTS=3/3
REGRESSION_TESTS=25/25
TOTAL_TESTS=28/28
NEW_PHP_LINT=4/4
BASELINE_PHP_LINT=7/7
TOTAL_PHP_LINT=11/11
PRIVACY_VALIDATION=PASS
INTEGRITY_VALIDATION=PASS
PROHIBITED_EVIDENCE_TYPES=20/20
CATALOG_SHA256=5d7934acbdcbb3dd45dfaa2442de467a4e40beb9828103c84acb55220e5d9a96
RESULTS_SHA256=b7c5f323cf38e02d970e33673bb0f943c16c23363466ea02e9f50a0f59c861f7
```

## Safe return y efectos reales

El safe return funcional de cada hard stop permanece dentro de R0 disabled, mantiene legacy e impide respuesta o write canónico. El safe return Git se valida en worktree detached: aplicar el commit, revertirlo sin commit y comprobar tree idéntico al parent.

```text
REAL_TRAFFIC=0
DATABASE_CONNECTIONS_OPENED=0
SQL_EXECUTED=0
DDL_EXECUTED=0
PERSISTENCE_WRITES=0
BACKFILL_EXECUTED=0
REAL_OTP_SENT=0
CLINICAL_REQUESTS_EXECUTED=0
NETWORK_CALLS=0
METRICS_EMITTED=0
AUDIT_EVENTS_WRITTEN=0
```

No se selecciona sink ni se procesa request, tráfico o dato real. No hay escritura DB, SQL/DDL, persistencia, backfill, OTP, Clinical, métricas, auditoría, AWS o infraestructura.

## Parámetros diferidos

Permanecen sin resolver sampling key/scope/exclusions, ventanas, p95, p99, SLO, error budgets, thresholds, sink, retención, owners, on-call, plataformas y rutas de dashboard/alerta, timeouts, residencia, jurisdicción, key management, schema y retención de auditoría, RTO y RPO. El baseline sintético no permite fijar cifras, proveedores, personas, porcentajes o percentiles productivos.

## Git, rollback y estado

Se usa un único commit con mensaje `test(platform): agrega baseline sintético CUT-02`, push normal y sin integración, PR, tag o checkpoint 19. El rollback contractual no usa reset, rebase, amend o force push.

```text
SYNTHETIC_BASELINE_EXECUTION_AUTHORIZED=true
REAL_BASELINE_COLLECTION_AUTHORIZED=false
SANITIZED_EVIDENCE_PACKAGE_CREATION_AUTHORIZED=true
SANITIZED_EVIDENCE_TECHNICAL_REVIEW_AUTHORIZED=false
SAMPLING=0
CUT02_IMPLEMENTATION_AUTHORIZED=false
R1_ACTIVATION_AUTHORIZED=false
R2_ACTIVATION_AUTHORIZED=false
ROLLOUT_STAGE=R0
ROLLOUT_MODE=disabled
ACTIVITY19=CUT02_C_SYNTHETIC_BASELINE_PACKAGE_READY_FOR_POSTVALIDATION_NOT_INTEGRATED
```

Los trece blockers siguen abiertos, cutover permanece `NO_GO_BLOCKERS_PRESENT` y readiness general `NO_GO_LEGACY_BLOCKERS_PRESENT`. La Actividad 18 está cerrada e integrada; la Actividad 20 continúa bloqueada. Contador oficial `18/22`, pendientes `4`.
