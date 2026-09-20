# Evidencia sintética M5 de integridad de consulta

## Estado

```text
CHAPTER=CLIN-REFORM-PHASE2-M5-EXEC01-RERUN2
EVIDENCE_DATE=2026-09-19
SOURCE_HEAD=115923cac322958ad4f443ab783a8cf19f9c5093
M5_RESULT=PASS
PHASE_2_M5_STATUS=PASS_READY_FOR_DIRECTOR_REVIEW
VALIDATION_SCENARIOS_COUNT=35
VALIDATION_SCENARIOS_PASS_COUNT=35
```

Esta evidencia registra la ejecución física completa de T01–T35 después de aceptar REPAIR01. No acepta el commit documental como nuevo baseline de source, no completa PHASE 2 y no autoriza M6, migración de la base MXMed de trabajo, cutover runtime, producción ni PHASE 3.

## Cronología preservada

| Ejecución | Source HEAD | Resultado |
| --- | --- | --- |
| RUN 1 / EXEC01 | `c1fa6bea057992d35f4ccc7e0f8534cbe8a5a4b7` | T01–T25 `PASS`; T26 `FAIL_QA_HARNESS` por carrera de creación del directorio de barrera; T27–T35 no ejecutados por fail-fast. |
| PREP01-R2 | `2c9d725eeb02bdecaa8dec894ad414862b8d9f82` | Reparación de directorio concurrente aceptada. |
| RUN 2 / EXEC01-RERUN | `2c9d725eeb02bdecaa8dec894ad414862b8d9f82` | T01–T26 `PASS`; T27 bloqueado por traducción HTTP `500/server_error`; T28–T35 no ejecutados por fail-fast. |
| REPAIR01 | `115923cac322958ad4f443ab783a8cf19f9c5093` | Reparación canónica V1 de traducción de errores de dominio aceptada. |
| RUN 3 / EXEC01-RERUN2 | `115923cac322958ad4f443ab783a8cf19f9c5093` | T01–T35 `PASS`; deadlock controlado `PASS`; teardown completo. |

Durante la preparación de RUN 3 se descartó íntegramente un primer entorno efímero porque el recolector TSV del arnés temporal eliminaba la columna terminal vacía cuando `finalize` ganaba T26. La respuesta clínica de esa carrera fue válida (200/409), no se cambió source y no se reutilizó evidencia parcial. El recolector desechable se hizo determinista con el centinela `NULL`; después se crearon nuevas bases, nuevas sesiones, nuevos puertos y una nueva copia exacta para la ejecución completa registrada aquí.

## Aislamiento y fuente

```text
CURRENT_BRANCH=design/physician-crd03-credentials-ui-v1
CHECKPOINT_REF=checkpoint/clinical-pre-phase2-m5-exec01-rerun2-20260919
CHECKPOINT_HEAD=115923cac322958ad4f443ab783a8cf19f9c5093
M5_SOURCE_IS_EPHEMERAL_EXACT_COMMIT_COPY=true
EXPORTED_GIT_BLOBS_VERIFIED=1851
M5_EPHEMERAL_DB_CONFIG_FILE_PRESENT=false
APPLICATION_SOURCE_IS_READ_ONLY=true
MIGRATION_SOURCE_IS_READ_ONLY=true
```

El árbol de ejecución se creó mediante `git archive` del único source HEAD indicado y se verificó blob por blob. El override `api/mxmed-db.config.php` no estaba presente.

## Destino físico desechable

```text
MYSQL_VERSION=8.4.11
MYSQL_SERVER_CLASS=local/development
MYSQL_ENDPOINT=127.0.0.1:3309
MYSQL_INTERNAL_HOSTNAME=cf962b03dc6f
MYSQL_INTERNAL_PORT=3306
M5_MAIN_DB=mxmed_clinical_m5_20260919194646_31211_main
M5_MISSING_SCHEMA_DB=mxmed_clinical_m5_20260919194646_31211_missing
EFFECTIVE_HTTP_DB_IS_WORKING_MXMED=false
PREEXISTED=false
IS_SYNTHETIC=true
IS_DISPOSABLE=true
IS_LOCAL=true
```

Se usaron sólo identidades `doctor_m5_*`, `operator_m5_*`, `p_m5_*` y `appt_m5_*`. No se conectaron datos reales de pacientes, clínica, agenda o facturación.

## Sesiones y concurrencia

```text
PHP_SESSION_BASED_OPERATOR_IDENTITY=true
DISTINCT_OPERATOR_SESSIONS_VERIFIED=true
M5_HTTP_RUNTIME_MULTI_WORKER=true
MAIN_HTTP_PROCESS_COUNT=5
MISSING_SCHEMA_HTTP_PROCESS_COUNT=3
TWO_REAL_INNODB_CONNECTIONS_VERIFIED=true
```

Las sesiones A, B y FOREIGN fueron archivos PHP distintos. T04, T11 y T26 usaron rendezvous determinista; ambos participantes llegaron antes de liberar cada barrera.

| Escenario | Punto de barrera | Connection IDs | Resultado |
| --- | --- | --- | --- |
| T04 | `T04_CONCURRENT_START_BEFORE_INSERT` | `1422`, `1423` | `PASS` |
| T11 | `T11_CONCURRENT_FINALIZE_BEFORE_TERMINAL_LOCK_OR_COMMIT` | `1445`, `1446` | `PASS` |
| T26 | `T26_FINALIZE_VOID_RACE_BEFORE_TERMINAL_LOCK` | `1493`, `1494` | `PASS` |

T26 terminó `closed`, con un solo timestamp terminal y una nota final. Las respuestas concurrentes fueron 200 y 409; no reapareció la carrera del directorio de barrera.

## Matriz T01–T35

| Escenario | Resultado | Escenario | Resultado | Escenario | Resultado |
| --- | --- | --- | --- | --- | --- |
| T01 | PASS | T13 | PASS | T25 | PASS |
| T02 | PASS | T14 | PASS | T26 | PASS |
| T03 | PASS | T15 | PASS | T27 | PASS |
| T04 | PASS | T16 | PASS | T28 | PASS |
| T05 | PASS | T17 | PASS | T29 | PASS |
| T06 | PASS | T18 | PASS | T30 | PASS |
| T07 | PASS | T19 | PASS | T31 | PASS |
| T08 | PASS | T20 | PASS | T32 | PASS |
| T09 | PASS | T21 | PASS | T33 | PASS |
| T10 | PASS | T22 | PASS | T34 | PASS |
| T11 | PASS | T23 | PASS | T35 | PASS |
| T12 | PASS | T24 | PASS |  |  |

## Evidencia crítica

### T27 — reparación física REPAIR01

```text
T27_REPAIR01_PHYSICAL_VERIFIED=true
T27_MISMATCH_HTTP_STATUS=409
T27_RESPONSE_ERROR=DOCUMENT_CONTEXT_MISMATCH
T27_NO_DOCUMENT_INSERTED=true
T27_IDEMPOTENCY_ROW_INSERTED=false
```

La orden pertenecía a E2 y el resultado incompatible se intentó crear en otro encounter. El rechazo mantuvo en cero los documentos del encounter incompatible y no creó una fila de idempotencia para `t27-result`.

### T28 — GET sobre esquema faltante

```text
T28_HTTP_STATUS=503
T28_RESPONSE_ERROR=SCHEMA_NOT_READY
T28_GET_CAUSED_DDL=false
T28_GET_CAUSED_DML=false
T28_BEFORE_HASH=494c8014b8192c0591b9f65e8193593429fa3a3d67f292ad9d0d33f914c95886
T28_AFTER_HASH=494c8014b8192c0591b9f65e8193593429fa3a3d67f292ad9d0d33f914c95886
```

### T29–T35 y T31A

```text
T29_ENCOUNTER_REMAINS_CLOSED=true
T29_FINAL_NOTE_UNCHANGED=true
T29_RESULT_HAS_OWN_DOCUMENT_IDENTITY=true
T29_RESULT_VALIDLY_LINKED=true
T29_LATE_RESULT_CREATED_AMENDMENT=false
T30_ORIGINAL_HISTORICAL_CONTENT_UNCHANGED=true
T31A_RESULT=PASS
T31B_RESULT=NOT_APPLICABLE_PRECONDITION_NOT_MET
T32_SINGLE_OBSERVATION_AND_STABLE_REPLAY_ID=true
T33_SINGLE_RESULT_DOCUMENT_AND_STABLE_REPLAY_UUID=true
T34_ENCOUNTER_AND_DOCUMENT_AMENDMENT_IDEMPOTENCY=true
T34_CHANGED_SEMANTICS_REJECTED_AS_IDEMPOTENCY_KEY_REUSED=true
T35_HISTORICAL_DELETE_RESTRICT=true
```

T31A persistió y devolvió V1 explícitamente, no reescribió payload ni versión al leer y rechazó la versión desconocida como `PAYLOAD_SCHEMA_VERSION_UNSUPPORTED`. T31B permanece no aplicable porque no existe contrato V2 aceptado.

## Deadlock e invariantes

```text
CONTROLLED_INNODB_DEADLOCK_EXERCISED=true
DEADLOCK_MYSQL_ERROR=1213
POST_DEADLOCK_CLINICAL_INVARIANTS_VALID=true
NO_UNEXPECTED_DRIFT_OUTSIDE_FIXTURE=true
```

Dos transacciones bloquearon los encounters en orden opuesto. InnoDB eligió una víctima con error 1213, la otra transacción completó y ambos encounters conservaron el estado `open`. Todas las filas finales pertenecían a la fixture sintética y a las mutaciones previstas por T01–T35.

## Teardown y prohibiciones

```text
M5_RESIDUAL_DATABASE_COUNT=0
M5_RESIDUAL_HTTP_PROCESS_COUNT=0
M5_RESIDUAL_BARRIER_STATE=false
M5_RESIDUAL_TEMP_ROOT_COUNT=0
WORKING_MXMED_DB_CONNECTED=false
WORKING_MXMED_DB_SCHEMA_CHANGED=false
WORKING_MXMED_DB_DATA_CHANGED=false
PATIENT_REAL_DATA_USED=false
CLINICAL_REAL_DATA_USED=false
AGENDA_REAL_DATA_USED=false
BILLING_REAL_DATA_USED=false
RUNTIME_CUTOVER_AUTHORIZED=false
PRODUCTION_EXECUTION_AUTHORIZED=false
M6_AUTHORIZED=false
```

## Siguiente paso permitido

```text
NEXT_AUTHORIZED_STEP=Director/assistant review of complete PHASE 2 M5 T01-T35 RERUN2 evidence; no M6, working-database migration, runtime cutover, production or PHASE 3 action authorized.
```
