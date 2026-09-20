# Plan vivo de reforma del Expediente Clínico

```text
REFORM_STATUS=IN_PROGRESS
CURRENT_ACCEPTED_HEAD=bcb2606ba7a95818673b402a9e006ecc0431ff73
REFORM_START_DATE=2026-09-18
CURRENT_PHASE=PHASE_2_ENCOUNTER_INTEGRITY
CURRENT_OBJECTIVE=Definir y demostrar la integridad del ciclo de vida de la consulta antes de implementar el nuevo workspace ambulatorio.
NEXT_AUTHORIZED_STEP=Director/assistant review of M6 CALLER01. If accepted, caller compatibility reaches zero uncontrolled cohort writers; next address cohort runtime routing and remaining M6 infrastructure blockers before any backup/clone/migration authorization.
CLIN-REFORM-PLAN01=ACCEPTED
PHASE_0_AUDIT01=ACCEPTED
PHASE_0_AUDIT02=ACCEPTED
PHASE_0_CURRENT_STATE_AUDIT=COMPLETE
PHASE_0_STATUS=COMPLETE
PHASE_1_STATUS=COMPLETE
PHASE_1_AUTHORIZED=true
PHASE_1_CLINICAL_INFORMATION_MODEL_UX_CONTRACT=COMPLETE
PHASE_1_MODEL01=ACCEPTED
PHASE_1_MODEL01A=ACCEPTED
PHASE_1_DIRECTOR_DECISIONS_RATIFIED=6/6
PHASE_2_DIRECTOR_DECISIONS_RATIFIED=5/5
DIRECTOR_DECISIONS_RATIFIED=5/5
DIRECTOR_DECISIONS_REQUIRED_COUNT=0
PHASE_2_STATUS=IN_PROGRESS
PHASE_2_AUTHORIZED=true
PHASE_2_CONTRACT01=ACCEPTED
PHASE_2_CONTRACT01A=ACCEPTED
PHASE_2_PHYSICAL_DESIGN_AUTHORIZED=true
PHASE_2_PHYS01=ACCEPTED
PHASE_2_PHYS01A=ACCEPTED
PHASE_2_PHYS01B=ACCEPTED
PHYSICAL_DESIGN_ACCEPTED=true
G1_PHYSICAL_SCHEMA_DESIGN_ACCEPTED=true
G2_API_CONTRACT_ACCEPTED=true
G3_LEGACY_PLAN_ACCEPTED=true
G4_GET_DDL_REMOVAL_PLAN_ACCEPTED=true
G5_SAFE_RETURN_ACCEPTED=true
G6_SYNTHETIC_QA_PLAN_ACCEPTED=true
PHASE_2_IMPL01_AUTHORIZED=true
PHASE_2_IMPL01_STATUS=IN_PROGRESS
PHASE_2_IMPL01A=ACCEPTED
PHASE_2_IMPL01B=ACCEPTED
IMPL01B_ACCEPTED_HEAD=ccd2a4aa553c841ce72cb77897dd02bf8ba305bc
IMPL01B_SCOPE=DOCUMENT_AMENDMENT_REPLACEMENT_COMMAND_ONLY
IMPL01A_ACCEPTED_HEAD=09022adffd4e3ad0824cb923893b4b2ae0e8ec42
IMPL01A_R2_REPOSITORY_FOUNDATION_ACCEPTED=true
IMPL01A_ACCEPTED_AS=REPOSITORY_FOUNDATION
PHASE_2_IMPL01_SCOPE=REPOSITORY_IMPLEMENTATION_ONLY
IMPLEMENTATION_AUTHORIZED=REPOSITORY_ONLY_NOT_EXECUTION
IMPLEMENTATION_REPOSITORY_CHANGES_AUTHORIZED=true
DOCUMENT_AMENDMENT_CANONICAL_ROUTE=POST_/documents/{id}/amendments
DOCUMENT_AMENDMENT_OR_REPLACEMENT_IS_APPEND_ONLY=true
ORIGINAL_DOCUMENT_MUTATED=false
DOCUMENT_REVISION_OPERATION=CREATE_DOCUMENT_AMENDMENT_OR_REPLACEMENT
CANONICAL_DOCUMENT_COMPOSITION_REUSED=true
CANONICAL_TRANSACTIONAL_PERSISTENCE_REUSED=true
NEW_DOCUMENT_INITIAL_STATUS=generated
NEW_DOCUMENT_SIGNED_AT=NULL
DOCUMENT_REVISION_LINEAGE_CREATED=true
LINEAGE_APPEND_ONLY=true
DOCUMENT_REVISION_IDEMPOTENCY_ACCEPTED=true
DOCUMENT_REVISION_CREATE_IDEMPOTENCY=IMPLEMENTED_ACCEPTED
T34_DOCUMENT_AMENDMENT_RETRY_REPOSITORY_PREREQUISITE=SATISFIED
ENCOUNTER_V1_GET_DDL_REMOVAL=IMPLEMENTED_FOR_V1_ENCOUNTER_PATHS
GLOBAL_CLINICAL_GET_DDL_REMOVAL=PENDING_LATER_IMPL_STAGE
V1_MULTIPART_DOCUMENT_WRITE=DEFERRED_FAIL_CLOSED
PHASE_2_MIG01A_AUTHORIZED=true
PHASE_2_MIG01A_STATUS=ACCEPTED
PHASE_2_MIG01A_SCOPE=DISPOSABLE_MIGRATION_REHEARSAL_ONLY
MIG01A_FIRST_REHEARSAL_RESULT=BLOCKED
MIG01A_FIRST_BLOCKER=MYSQL_1295_CREATE_TRIGGER_PREPARE_UNSUPPORTED
MIG01A_FIRST_REHEARSAL_MYSQL_VERSION=8.4.11
MIG01A_FIRST_REHEARSAL_TARGET_CLASS=DISPOSABLE_SYNTHETIC_LOCAL_ONLY
MIG01A_RESIDUAL_DATABASE_COUNT=0
PHASE_2_MIG01A_R1=ACCEPTED
MIG01A_R1_ACCEPTED_HEAD=cc8bcf502f3953942ba67cc655490d49813401fc
PHASE_2_MIG01A_RERUN_AUTHORIZED=false
PHASE_2_MIG01A_RERUN_EXECUTED=true
MIG01A_RERUN_RESULT=PASS
MIG01A_RERUN_MYSQL_VERSION=8.4.11
MIG01A_RERUN_TARGET_CLASS=DISPOSABLE_SYNTHETIC_LOCAL_ONLY
MIG01A_RERUN_RESIDUAL_DATABASE_COUNT=0
MIG01A_ACCEPTED=true
MIG01A_ACCEPTED_HEAD=da31ed437fed8867ac0cb45342f4eb03c2c476e1
MIG01A_PHYSICAL_REHEARSAL_ACCEPTED=true
DB_MIGRATION_EXECUTION_AUTHORIZED=false
WORKING_MXMED_DB_MIGRATION_AUTHORIZED=false
WRITE_VALIDATION_EXECUTED=true
HTTP_WRITE_QA_EXECUTED=true
RUNTIME_CUTOVER_AUTHORIZED=false
RUNTIME_CUTOVER_EXECUTED=false
PRODUCTION_EXECUTION_AUTHORIZED=false
M5_SYNTHETIC_QA_AUTHORIZED=true
PHASE_2_M5_STATUS=ACCEPTED
PHASE_2_M5=ACCEPTED
M5_ACCEPTED=true
M5_ACCEPTED_SOURCE_HEAD=115923cac322958ad4f443ab783a8cf19f9c5093
M5_EVIDENCE_COMMIT=b069fe7cfa66fc71a2aaf323676dc74a411f1ec2
PHASE_2_M5_SCOPE=T01_T35_DISPOSABLE_SYNTHETIC_QA_ONLY
PHASE_2_M5_PREP01=ACCEPTED
M5_PREP01_ACCEPTED_HEAD=5f7aa2224a9af4d6ad6bb4e091d084e7d52e8f1d
M5_CONCURRENCY_BARRIER=ACCEPTED
M5_PREP01_R1_REASON=REMOVE_UNCONDITIONAL_RUNTIME_DEPENDENCY_ON_QA_TREE
PHASE_2_M5_ADJ01=ACCEPTED
M5_ADJ01_ACCEPTED_HEAD=c1fa6bea057992d35f4ccc7e0f8534cbe8a5a4b7
M5_EXEC01_FIRST_RUN=BLOCKED
M5_EXEC01_FIRST_RUN_SOURCE_HEAD=c1fa6bea057992d35f4ccc7e0f8534cbe8a5a4b7
M5_EXEC01_FIRST_BLOCKER=T26_BARRIER_DIRECTORY_CREATION_RACE
M5_EXEC01_T01_T25=PASS
M5_EXEC01_T26=FAIL_QA_HARNESS
M5_EXEC01_T27_T35=NOT_EXECUTED_FAIL_FAST
M5_EXEC01_VALIDATION_SCENARIOS_EXECUTED_COUNT=26
M5_EXEC01_VALIDATION_SCENARIOS_PASS_COUNT=25
M5_EXEC01_WORKING_MXMED_DB_CONNECTED=false
M5_EXEC01_RESIDUAL_DATABASE_COUNT=0
M5_EXEC01_RESIDUAL_HTTP_PROCESS_COUNT=0
M5_EXEC01_RESIDUAL_BARRIER_STATE=false
PHASE_2_M5_PREP01_R2=ACCEPTED
M5_PREP01_R2_ACCEPTED_HEAD=2c9d725eeb02bdecaa8dec894ad414862b8d9f82
M5_BARRIER_DIRECTORY_RACE_REPAIR=ACCEPTED
M5_EXEC01_RERUN=BLOCKED
M5_EXEC01_RERUN_SOURCE_HEAD=2c9d725eeb02bdecaa8dec894ad414862b8d9f82
M5_EXEC01_RERUN_FIRST_BLOCKER=T27_HTTP_DOMAIN_ERROR_MAPPING
M5_EXEC01_RERUN_T01_T26=PASS
M5_EXEC01_RERUN_T27=FAIL_HTTP_MAPPING
M5_EXEC01_RERUN_T28_T35=NOT_EXECUTED_FAIL_FAST
M5_EXEC01_RERUN_VALIDATION_SCENARIOS_EXECUTED_COUNT=27
M5_EXEC01_RERUN_VALIDATION_SCENARIOS_PASS_COUNT=26
M5_EXEC01_RERUN_WORKING_MXMED_DB_CONNECTED=false
M5_EXEC01_RERUN_RESIDUAL_DATABASE_COUNT=0
M5_EXEC01_RERUN_RESIDUAL_HTTP_PROCESS_COUNT=0
M5_EXEC01_RERUN_RESIDUAL_BARRIER_STATE=false
PHASE_2_M5_REPAIR01=ACCEPTED
M5_REPAIR01_ACCEPTED_HEAD=115923cac322958ad4f443ab783a8cf19f9c5093
M5_T27_HTTP_MAPPING_REPAIR=ACCEPTED
M5_RERUN_AUTHORIZED=true
M5_NEXT_RERUN_AUTHORIZED=false
M5_EXEC01_RERUN2_EXECUTED=true
M5_EXEC01_RERUN2_SOURCE_HEAD=115923cac322958ad4f443ab783a8cf19f9c5093
M5_EXEC01_RERUN2_T01_T35=PASS
M5_EXEC01_RERUN2_VALIDATION_SCENARIOS_EXECUTED_COUNT=35
M5_EXEC01_RERUN2_VALIDATION_SCENARIOS_PASS_COUNT=35
M5_EXEC01_RERUN2_WORKING_MXMED_DB_CONNECTED=false
M5_EXEC01_RERUN2_RESIDUAL_DATABASE_COUNT=0
M5_EXEC01_RERUN2_RESIDUAL_HTTP_PROCESS_COUNT=0
M5_EXEC01_RERUN2_RESIDUAL_BARRIER_STATE=false
M5_FINAL_RERUN_RANGE=T01_T35
FINAL_M5_EVIDENCE_MUST_USE_ONE_SOURCE_HEAD=true
T31_SCENARIO=SECTION_SCHEMA_VERSION_HISTORY
T31_ADJUDICATION=V1_NOW_REAL_V2_LATER
T31A_CURRENT_V1_VERSION_SAFETY=REQUIRED_FOR_CURRENT_M5
T31A_V1_VERSION_PERSISTED=REQUIRED
T31A_V1_VERSION_RETURNED_EXPLICITLY=REQUIRED
T31A_READ_DOES_NOT_REWRITE_PAYLOAD=REQUIRED
T31A_READ_DOES_NOT_CHANGE_SCHEMA_VERSION=REQUIRED
T31A_UNKNOWN_VERSION_REJECTED=REQUIRED
T31A_UNKNOWN_VERSION_NOT_TREATED_AS_LATEST=REQUIRED
T31A_NO_SILENT_SCHEMA_MIGRATION=REQUIRED
T31B_REAL_V2_BACKWARD_COMPATIBILITY=DEFERRED_CONDITIONAL
T31B_TRIGGER=SECTION_SCHEMA_V2_CONTRACT_ACCEPTED
T31B_CURRENT_APPLICABILITY=NOT_APPLICABLE_PRECONDITION_NOT_MET
T31B_NOT_REQUIRED_FOR_CURRENT_M5_PASS=true
T31B_RESULT=NOT_APPLICABLE_PRECONDITION_NOT_MET
T31A_RESULT=PASS
T31A_RESULT_ACCEPTED=true
SECTION_SCHEMA_V2_CONTRACT=NOT_DEFINED
SECTION_SCHEMA_V2_IMPLEMENTED=false
SECTION_SCHEMA_V2_IMPLEMENTATION_AUTHORIZED=false
HISTORICAL_PAYLOAD_INTERPRETATION_MUST_BE_VERSIONED=true
VALIDATION_SCENARIOS_COUNT=35
VALIDATION_SCENARIOS_PASS_COUNT=35
T31_CURRENT_EXECUTION_COMPONENT=T31A
T31_OVERALL_CURRENT_M5_RESULT=PASS_WITH_FUTURE_T31B_CONDITIONAL_OBLIGATION
M5_QA_BARRIER_DEFAULT_OFF=true
M5_QA_BARRIER_EXPLICIT_ENABLE_REQUIRED=true
M5_QA_BARRIER_LOCAL_DEV_ONLY=true
M5_QA_BARRIER_PRODUCTION_DENIED=true
M5_QA_BARRIER_LAZY_LOAD=true
M5_QA_MODE_OFF_QA_FILE_REQUIRED=false
M5_QA_MODE_ON_MISSING_IMPLEMENTATION_FAILS_CLOSED=true
M5_QA_MODE_OFF_ZERO_FILESYSTEM_EFFECT=true
BARRIER_USES_DETERMINISTIC_RENDEZVOUS=true
BARRIER_USES_SLEEP_ONLY=false
BARRIER_STATE_STORED_IN_CLINICAL_DB=false
T04_CONCURRENT_START_BARRIER_ACCEPTED=true
T11_CONCURRENT_FINALIZE_BARRIER_ACCEPTED=true
T26_FINALIZE_VOID_BARRIER_ACCEPTED=true
T04_REAL_CONCURRENCY=ACCEPTED
T11_REAL_CONCURRENCY=ACCEPTED
T26_REAL_CONCURRENCY=ACCEPTED
TWO_REAL_INNODB_CONNECTIONS_VERIFIED=true
SYNCHRONIZED_CONCURRENCY_BARRIER_VALIDATED=true
T26_TERMINAL_WINNER_COUNT=1
T26_FINAL_NOTE_COUNT=1
T26_BARRIER_DIRECTORY_RACE_RECURRED=false
T27_REPAIR01_PHYSICAL_VERIFIED=true
T27_HTTP_STATUS=409
T27_ERROR_CODE=DOCUMENT_CONTEXT_MISMATCH
T27_INVALID_DOCUMENT_INSERTED=false
T27_IDEMPOTENCY_ROW_INSERTED=false
T28_SCHEMA_NOT_READY_HTTP_STATUS=503
T28_GET_CAUSED_DDL=false
T28_GET_CAUSED_DML=false
T32_OBSERVATION_IDEMPOTENCY=ACCEPTED
T33_POST_CLOSE_RESULT_IDEMPOTENCY=ACCEPTED
T34_ENCOUNTER_AMENDMENT_IDEMPOTENCY=ACCEPTED
T34_DOCUMENT_REVISION_IDEMPOTENCY=ACCEPTED
T35_HISTORICAL_DELETE_INTEGRITY=ACCEPTED
CONTROLLED_INNODB_DEADLOCK_EXERCISED=true
CONTROLLED_INNODB_DEADLOCK_RESULT=ACCEPTED
POST_DEADLOCK_CLINICAL_INVARIANTS_VALID=true
FEATURE_GATE_ACTIVATION_AUTHORIZED_FOR_M5_DISPOSABLE_QA_ONLY=true
FEATURE_GATE_ACTIVATION_AUTHORIZED_FOR_WORKING_MXMED=false
FEATURE_GATE_ACTIVATION_AUTHORIZED_FOR_PRODUCTION=false
WRITE_VALIDATION_AUTHORIZED_FOR_M5_DISPOSABLE_QA_ONLY=true
WRITE_VALIDATION_AUTHORIZED_FOR_WORKING_MXMED=false
HTTP_WRITE_QA_AUTHORIZED_FOR_M5_DISPOSABLE_QA_ONLY=true
HTTP_WRITE_QA_AUTHORIZED_FOR_WORKING_MXMED=false
T01_T35_EXECUTION_AUTHORIZED_FOR_M5_DISPOSABLE_QA_ONLY=true
T01_T35_EXECUTED=true
T01_T35_CURRENT_M5_PASS=true
T01_T35_PHYSICAL_QA=ACCEPTED
M5_EXECUTED=true
M5_RESULT=PASS
PHP_SESSION_BASED_OPERATOR_IDENTITY=true
TWO_INDEPENDENT_HTTP_CLIENTS=true
TWO_AUTHORIZED_OPERATOR_SESSIONS=true
TWO_REAL_INNODB_CONNECTIONS=true
SYNCHRONIZED_CONCURRENCY_BARRIER=true
M5_REAL_CONCURRENCY_SCENARIOS=T04,T11,T26
NO_INFERENCE_OF_LEGACY_DOCTOR_OWNERSHIP=true
MIGRATIONS_EXECUTED=DISPOSABLE_REHEARSAL_ONLY
WORKING_MXMED_DB_MIGRATIONS_EXECUTED=NONE
M5_TARGET_CLASS=DISPOSABLE_SYNTHETIC_LOCAL_ONLY
WORKING_MXMED_DB_CONNECTED=false
WORKING_MXMED_DB_SCHEMA_CHANGED=false
WORKING_MXMED_DB_DATA_CHANGED=false
PATIENT_REAL_DATA_USED=false
CLINICAL_REAL_DATA_USED=false
AGENDA_REAL_DATA_USED=false
BILLING_REAL_DATA_USED=false
NO_UNEXPECTED_DRIFT_OUTSIDE_FIXTURE=true
M5_RESIDUAL_DATABASE_COUNT=0
M5_RESIDUAL_HTTP_PROCESS_COUNT=0
M5_RESIDUAL_BARRIER_STATE=false
M5_RESIDUAL_TEMP_ROOT_COUNT=0
M5_TEARDOWN=ACCEPTED
PHASE_2_M6_PLAN_AUTHORIZED=true
PHASE_2_M6_PLAN01=ACCEPTED
M6_PLAN01_ACCEPTED_HEAD=7dc615c772ef611a49229e3e1da91b569d6cf73d
PHASE_2_M6_STATUS=PLANNING
PHASE_2_M6_CTRL01=ACCEPTED
PHASE_2_M6_CTRL01_R1=ACCEPTED
M6_CTRL01_R1_ACCEPTED_HEAD=05369176c3fc6a8b89043ee79c6b431161b3d5f4
M6_SAFE_RETURN_MEMBERSHIP_SEMANTICS=ACCEPTED
M6_COHORT_CONTROL_PLANE=ACCEPTED
M6_COHORT_SCOPING_CAPABILITY=AVAILABLE_REPOSITORY_CONTROL
M6_COHORT_RUNTIME_ROUTING_ACTIVE=false
LEGACY_WRITER_BLOCKING_ACTIVE=false
PHASE_2_M6_GUARD01=ACCEPTED
M6_GUARD01_ACCEPTED_HEAD=bcb2606ba7a95818673b402a9e006ecc0431ff73
M6_LEGACY_WRITER_GUARDS=ACCEPTED
M6_LEGACY_WRITE_BLOCK_ERROR=M6_LEGACY_WRITE_BLOCKED
M6_LEGACY_WRITE_BLOCK_HTTP_STATUS=409
GUARDED_LEGACY_WRITER_FAMILIES=8
REMAINING_UNCONTROLLED_WRITER_FAMILIES=3
UNCONTROLLED_LEGACY_CLINICAL_WRITER_COUNT=3
PHASE_2_M6_CALLER01=READY_FOR_CODE_REVIEW
C02_ADAPTED=true
C03_ADAPTED=true
C14_RESOLVED=true
C14_RESOLUTION=BLOCK_FOR_CONFIGURED_M6_COHORT
CANDIDATE_UNCONTROLLED_LEGACY_CLINICAL_WRITER_COUNT=0
PHASE_2_M6_EXECUTION_AUTHORIZED=false
M6_AUTHORIZED=false
M6_GO_NO_GO=NO_GO_BLOCKED
M6_FIRST_BLOCKER=CALLER01_PENDING_REVIEW
M6_CALLER_INVENTORY_COMPLETE=true
M6_TOTAL_CLINICAL_RUNTIME_CALLERS=21
M6_WRITE_CAPABLE_CALLERS=17
M6_UNCONTROLLED_LEGACY_CLINICAL_WRITER_COUNT=3
MULTIPART_ACTIVE_CALLER_BLOCKER=true
M6_WORKING_DB_PREFLIGHT_EXECUTED=true
M6_WORKING_DB_PREFLIGHT_MODE=READ_ONLY
M6_WORKING_DB_IDENTITY_PROVEN=true
M6_WORKING_DB_SCHEMA_STATE=PRE_MIGRATION
M6_BACKUP_RESTORABLE=NOT_YET_PROVEN
M6_CLONE_MIGRATION_REHEARSAL=NOT_YET_EXECUTED
M6_MIGRATION_ACCOUNT_READY=false
```

Este plan es la autoridad subordinada y viva de la reforma del Expediente Clínico. El [Plan Maestro MXMed](../PLAN_MAESTRO_MXMED.md) conserva la autoridad global del proyecto. El Director/asistente aceptó CLIN-REFORM-PLAN01 en `edfa1326c602a1efcd3c94cfb705c582a170516e`, CLIN-REFORM-PHASE0-AUDIT01 en el baseline `d0602f9c443c1d3e215934c4cc2aa6084e126d12`, la cadena [CLIN-REFORM-PHASE0-AUDIT02](EXPEDIENTE_CURRENT_STATE_AUDIT_PHASE0.md#clin-reform-phase0-audit02--physical-runtime-validation) `504bd136854518d301915d743911c5f0f60c7aa1` → `ef378fddef3edaff07f603d965defa82365a80de`, el cierre de PHASE 0 en `a1dd2860f90094260c08388d363410198e5a7495` y la cadena MODEL01/MODEL01A `0ba07c9d8798ee6ecf03083453f5a587fff812b8` → `63d9e22403ce64ac8a49f2b06afe3f875724baa3`. El cierre de PHASE 1 está aceptado en `8698b1f66466867360651db5fa62e54d28fba797`. El baseline aceptado de CONTRACT01/CONTRACT01A es `4161120ad2c8a54b3e1455019f4ba994a6a9fd26`; el diseño físico PHYS01/PHYS01A/PHYS01B quedó aceptado en `51518d0fb0875e338a20be865ff2394075993a55`; la base de repositorio IMPL01A-R2 quedó aceptada en `09022adffd4e3ad0824cb923893b4b2ae0e8ec42`; la reparación MIG01A-R1 quedó aceptada en `cc8bcf502f3953942ba67cc655490d49813401fc`; la evidencia física MIG01A quedó aceptada en `da31ed437fed8867ac0cb45342f4eb03c2c476e1`; IMPL01B quedó aceptado en `ccd2a4aa553c841ce72cb77897dd02bf8ba305bc`; PREP01 y su reparación R1 quedaron aceptados en `5f7aa2224a9af4d6ad6bb4e091d084e7d52e8f1d`; ADJ01 quedó aceptado en `c1fa6bea057992d35f4ccc7e0f8534cbe8a5a4b7`; PREP01-R2 quedó aceptado en `2c9d725eeb02bdecaa8dec894ad414862b8d9f82`. El primer intento físico M5 pasó T01–T25 y se bloqueó en T26 por una carrera del directorio del arnés QA antes del row lock; T27–T35 no se ejecutaron por fail-fast. El segundo intento completo desde T01 pasó T01–T26 y se bloqueó en T27 porque la validación `DOCUMENT_CONTEXT_MISMATCH` evitó correctamente la inserción, pero el `catch` exterior respondió `500/server_error` en lugar del rechazo canónico `409`; T28–T35 no se ejecutaron por fail-fast. REPAIR01 quedó aceptado como fuente clínica M5 en `115923cac322958ad4f443ab783a8cf19f9c5093`, y el RERUN2 físico sobre una copia exacta de ese commit pasó T01–T35, las barreras concurrentes T04/T11/T26 y el deadlock InnoDB controlado. T27 respondió el rechazo canónico `409/DOCUMENT_CONTEXT_MISMATCH` sin documento ni idempotencia persistidos; T28 no causó DDL ni DML. Todo ocurrió sólo en bases locales sintéticas y desechables, con teardown completo y sin conectar la base MXMed de trabajo. El commit de evidencia `b069fe7cfa66fc71a2aaf323676dc74a411f1ec2` y M5 quedan aceptados. CTRL01/R1 queda aceptado en `05369176c3fc6a8b89043ee79c6b431161b3d5f4`; GUARD01 queda aceptado en `bcb2606ba7a95818673b402a9e006ecc0431ff73`, ahora `CURRENT_ACCEPTED_HEAD`, con ocho familias legacy contenidas y tres writers no controlados aceptados. CALLER01 adapta C02/C03 y bloquea C14 para cohorte como candidato con cuenta cero pendiente de revisión, sin routing activo. El [modelo de información y contrato UX](EXPEDIENTE_CLINICAL_INFORMATION_MODEL_UX_CONTRACT.md) está aceptado; PHASE 1 está completa. PHASE 2 continúa `IN_PROGRESS`. PLAN01 de M6 sigue aceptado en `7dc615c772ef611a49229e3e1da91b569d6cf73d` con resultado `NO_GO_BLOCKED`. Backups/restores, clones, migración de la base de trabajo, activación del feature gate, cutover, producción y PHASE 3 siguen no autorizados.

## Adjudicación T31 para M5 V1 y compatibilidad V2 futura

T31 conserva un único lugar dentro de los 35 escenarios aceptados. En el M5 actual se ejecuta T31A: escribir una sección V1 por la API aceptada, registrar antes de la lectura `payload_schema_version`, hash de `payload_json`, `row_version` y `updated_at`, leerla y demostrar igualdad exacta de esos cuatro valores. También debe probarse que una versión desconocida falla explícitamente como `PAYLOAD_SCHEMA_VERSION_UNSUPPORTED` y nunca se interpreta como la versión más reciente ni se migra en silencio.

T31B no se ejecuta ni se califica PASS/FAIL mientras no exista `SECTION_SCHEMA_V2_CONTRACT=ACCEPTED`; su resultado actual es `NOT_APPLICABLE_PRECONDITION_NOT_MET`. Cuando exista un contrato V2 real, T31B deberá demostrar soporte simultáneo V1/V2, lectura de la fila histórica con intérprete V1 y ausencia de reescritura o migración automática. Esta separación no elimina, omite ni renumera escenarios y no autoriza campos, parsers, renderers, constraints ni escrituras V2.

## Frontera autorizada para el plan M6

El siguiente capítulo puede preparar y someter a revisión el plan/preflight del cutover clínico backend. Debe inventariar todos los callers START/finalize/write y demostrar que son compatibles o quedan bloqueados explícitamente; comenzar con preflight de sólo lectura de la base de trabajo; exigir prueba de backup restaurable antes de autorizar migración; ensayar la migración sobre un clon; separar la cuenta de migración; controlar la ventana de escritura; definir la activación del feature gate, monitoreo de invariantes y retorno seguro. El trigger de cutover requiere gates aceptados, QA física aceptada y clientes compatibles.

La UI anterior debe conservar lecturas y borradores legacy aislados. El retorno seguro nunca puede ejecutar `DROP` o `TRUNCATE` sobre historia clínica nueva, inferir titularidad médica legacy ni purgar recursos o filas de idempotencia ya confirmados. Estas condiciones son requisitos del plan M6; no prueban preflight, backup, compatibilidad ni autorización de ejecución en este closeout.

## Estado PLAN01 de M6

El [plan de cutover backend M6](EXPEDIENTE_ENCOUNTER_M6_CUTOVER_PLAN.md) queda `ACCEPTED` en `7dc615c772ef611a49229e3e1da91b569d6cf73d`. El inventario cubre 21 familias runtime, 17 con capacidad de escritura; 11 requieren adaptador o bloqueo explícito antes de M6. El preflight autorizado fue estrictamente de sólo lectura y confirmó la identidad de la base de trabajo, 13 encounters legacy sin atribución médica y esquema `PRE_MIGRATION` con migraciones 01–04 `NOT_APPLIED`. No se infirió titularidad ni se cambió esquema o dato alguno. CTRL01/R1 queda aceptado en `05369176c3fc6a8b89043ee79c6b431161b3d5f4`; GUARD01 y su contención fail-closed para C04, C05, C11, C12, C16, C17, C20 y C21 quedan aceptados en `bcb2606ba7a95818673b402a9e006ecc0431ff73`.

M6 permanece `NO_GO_BLOCKED`: tras GUARD01 el valor aceptado es 3 writers no controlados (C02/C03/C14). CALLER01 propone adaptar C02/C03 y bloquear C14 para cohorte, con cuenta candidata cero pendiente de revisión. Multipart activo continúa incompatible con el V1 fail-closed, el routing de cohorte no está activo, no existe cuenta de migración separada, no se ha probado backup restaurable ni ensayo de clon de datos de trabajo, no está lista la ventana integral de escrituras y continúa DDL runtime legacy global. PLAN01 define migración, backup/restauración, clon, privilegios, ventana, activación, monitoreo, abort y retorno seguro, pero no ejecuta ni autoriza ninguno de esos pasos.

## Visión y problema

> La unidad de trabajo clínico cotidiano es la consulta.
> La unidad de continuidad longitudinal es el paciente.
> La especialidad personaliza ambas sin crear un expediente paralelo.

La interfaz vigente organiza el Expediente principalmente como pestañas de información: Datos Generales, Exploración Física, Historia Clínica, Historial de Atención, Estudios Diagnóstico, Tratamiento / Recetas, Manejo Hospitalario, Documentos Clínicos y Archivo. La auditoría debe determinar si esta organización ayuda al trabajo cotidiano del médico y distinguir qué pertenece al paciente longitudinalmente, a una consulta, a un episodio de varias consultas, a documentos/resultados o a operaciones administrativas y financieras. La apariencia de una pestaña no demuestra por sí sola su autoridad ni su persistencia.

## Principios vinculantes de dominio

Estos principios rigen las decisiones futuras; no son una autorización para modificar datos o contratos en PLAN01.

| Principio | Consecuencia de diseño |
| --- | --- |
| `PATIENT != ENCOUNTER` | Identidad longitudinal y atención puntual tienen ciclos de vida distintos. |
| `OPEN_PATIENT_RECORD != START_ENCOUNTER` | Abrir un paciente no crea una consulta. |
| `CLOSE_PATIENT_VIEW != FINALIZE_ENCOUNTER` | Cerrar la vista no finaliza una consulta clínica. |
| `CURRENT_MEASUREMENT != PREVIOUS_MEASUREMENT` | Cada medición conserva fecha y procedencia. |
| `PREVIOUS_VALUE_MUST_NOT_AUTOFILL_AS_CURRENT_MEASUREMENT` | Un valor previo no se presenta como recién medido. |
| `PRESCRIPTION != CURRENT_MEDICATION` | Prescribir y mantener una lista de medicación vigente son actos distintos. |
| `MISSING_INFORMATION != NORMAL_FINDING` | Ausencia de captura no equivale a hallazgo normal. |
| `CLINICAL_STATUS != BILLING_STATUS` | El estado clínico no deriva del cobro. |
| `CLINICAL_DOCUMENT != FINANCIAL_DOCUMENT` | Documentos clínicos y fiscales mantienen autoridades separadas. |
| `SPECIALTY_MODULE != PARALLEL_PATIENT_RECORD` | La especialidad amplía el núcleo sin duplicar al paciente. |
| `HISTORICAL_SIGNED_CONTENT_MUST_NOT_BE_SILENTLY_REWRITTEN` | El contenido firmado requiere una estrategia explícita de corrección. |
| `PATIENT_IDENTITY_MUST_REMAIN_CANONICAL` | La reforma respeta la identidad canónica del paciente. |
| `DOCTOR_ATTRIBUTION_MUST_REMAIN_CANONICAL` | Toda atención conserva su atribución médica canónica. |
| `NO_INFERENCE_OF_LEGACY_DOCTOR_OWNERSHIP` | No se atribuye retrospectivamente un médico por inferencia. |

## Autoridades actuales que deben preservarse

Esta es la base arquitectónica aceptada para orientar la auditoría, no una conclusión sobre cada control de la UI.

| Dominio | Autoridad vigente |
| --- | --- |
| Identidad del paciente | `patients_patients`, `patients_profiles`, `patients_contacts`, `patients_doctor_links`. |
| Consulta/encounter | `clinical_encounters`; atribución médica en `clinical_encounters.doctor_id`; `clinical_encounters.appointment_id` es opcional. |
| Documentos clínicos | Autoridad existente de `clinical_documents`. |
| Casos clínicos | `clinical_cases` y `clinical_case_items`. |
| Agenda | `agenda_appointments` y autoridades existentes de Agenda. |
| Facturación | Arquitectura de billing separada: paciente no equivale a receptor fiscal y médico no equivale automáticamente a emisor fiscal. |

## Observaciones iniciales y resultado de PHASE 0

Estas fueron preguntas de auditoría iniciales. Su resultado se documenta sin convertir la evidencia limitada en permiso de implementación.

| Observación inicial | Resultado aceptado al cierre |
| --- | --- |
| Historial de Atención mostró una falla local relacionada con `/tmp/.../director-router.php`. | El router temporal ausente causó el fatal local; PHP directo sirve el shell. Los datos de timeline no se ejecutaron por riesgo de DDL en GET. |
| Manejo Hospitalario podía mostrar “Selecciona paciente” y “no disponible” con un paciente visible. | Capacidad local deshabilitada y propagación de paciente inconsistente en ese runtime; son estados distintos. |
| Tratamiento / Recetas delega operaciones a Actividad Clínica. | La receta se emite como documento clínico; la medicación actual mostrada carece de una autoridad canónica única demostrada. |
| Archivo presenta adjuntos clínicos. | La pestaña es un placeholder, sin autoridad de archivo clínico demostrada. |
| La persistencia de Datos Generales ya estaba implementada. | La identidad estructurada del paciente se leyó y conservó tras recarga; no se probó escritura en esta auditoría. |

## Capas de información conceptuales aceptadas

Estas capas describen la clasificación lógica aceptada en MODEL01/MODEL01A; el [contrato detallado](EXPEDIENTE_CLINICAL_INFORMATION_MODEL_UX_CONTRACT.md) resuelve titularidad y ciclos de vida. No son tablas nuevas ni prueba de implementación física.

| Capa | Ejemplos |
| --- | --- |
| Identidad y administración del paciente | Nombre, fecha de nacimiento, contacto, domicilio, responsable/contacto. |
| Resumen clínico longitudinal | Problemas activos, alergias, medicación actual revisada, antecedentes relevantes, seguimiento pendiente. |
| Datos de una consulta | Motivo, padecimiento/evolución actual, signos vitales, antropometría medida en la visita, exploración física, valoración y plan. |
| Episodio/caso clínico | Embarazo, tratamiento dental prolongado, curso posoperatorio u otro proceso de varias consultas. |
| Documentos y resultados clínicos | Recetas, notas, órdenes, resultados, certificados y consentimientos. |
| Administración y finanzas | Cita, pago, recibo y factura. |

Ninguna capa justifica crear esquema únicamente por figurar aquí.

## Hipótesis de flujo cotidiano

**Seguimiento:** abrir paciente → consultar resumen → revisar información activa, consulta anterior y pendientes → iniciar o continuar consulta → registrar datos actuales → valoración y plan → generar documentos clínicos pertinentes → finalizar consulta → gestionar seguimiento → atender pago/facturación por separado cuando corresponda.

**Paciente nuevo:** crear identidad mínima necesaria → abrir contexto clínico del paciente → iniciar primera consulta → completar historia y datos clínicos pertinentes según el caso. No se deben exigir todos los campos administrativos para comenzar la atención salvo regla explícita y validada.

Abrir un paciente o navegar entre pestañas **no** crea una consulta.

## Objetivos de revisión longitudinal

La reforma futura debe permitir responder de forma eficiente y con fecha/procedencia:

- ¿Qué ocurrió en la última consulta? ¿Cuál fue la presión arterial previa? ¿Cuánto pesaba antes y cuál era el valor aproximadamente hace un año?
- ¿Qué medicamento se prescribió la última vez? ¿Qué documentos se generaron? ¿Qué estudios o resultados siguen pendientes?
- ¿Hay una cita futura? ¿Se cobró la consulta previa? ¿Existe recibo? ¿Se emitió factura?

Estas preguntas son metas de aceptación de fases posteriores; PLAN01 no las implementa.

### Valor previo frente a medición actual

Un valor previo puede mostrarse como referencia, pero nunca llenar silenciosamente el encuentro actual como si acabara de medirse:

```text
Anterior: Peso 78 kg — 2026-06-10
Actual:   [vacío hasta medir]
```

Una futura acción explícita de copiar/reutilizar, si se valida clínicamente, debe exigir intención del usuario y conservar la procedencia.

## Estrategia de extensión por especialidad

`NÚCLEO CLÍNICO + CONFIGURACIÓN DE ESPECIALIDAD + TIPO DE CONSULTA + PREFERENCIAS DEL MÉDICO`.

No se crea un expediente de paciente separado por especialidad. Los siguientes workstreams sólo se evaluarán más adelante, cada uno con capítulo propio de diseño y validación clínica:

| Especialidad | Alcance por evaluar |
| --- | --- |
| Pediatría | Crecimiento, desarrollo, cuidadores, inmunizaciones y observaciones pediátricas. |
| Ginecología/obstetricia | Historia ginecológica, episodio de embarazo, seguimiento gestacional y observaciones/documentos específicos. |
| Odontología | Odontograma, hallazgos por diente/superficie, plan de tratamiento, procedimientos, imagen dental y estado longitudinal. |

## Fases y criterios de salida

- [x] **PHASE 0 — CURRENT STATE AUDIT** · `COMPLETE`. AUDIT01 y AUDIT02 aceptados; mapa factual de nueve secciones, autoridades, temporalidad, límites de lectura física y riesgos heredados registrado. Las pruebas que requieren escritura o GET con posible DDL pasan a capítulos posteriores de integridad/implementación; no se requiere AUDIT03.
- [x] **PHASE 1 — CLINICAL INFORMATION MODEL / UX CONTRACT** · `COMPLETE`. MODEL01 y MODEL01A aceptados; cinco dominios de titularidad, vistas derivadas, ciclos de vida, navegación conceptual y seis decisiones del Director cerrados. La representación física permanece diferida.
- [ ] **PHASE 2 — ENCOUNTER INTEGRITY** · `IN_PROGRESS`, [CONTRACT01 y CONTRACT01A](EXPEDIENTE_ENCOUNTER_INTEGRITY_CONTRACT.md) aceptados conceptualmente con D1–D5 y 24 escenarios sin ejecutar. Contiene inicio, guardado, reanudación, finalización, anulación, lectura histórica, enmiendas, relación documental y plan de prueba controlada; el diseño físico es el siguiente capítulo autorizado, mientras implementación y validación con escrituras siguen pendientes. **Salida futura:** dos o más consultas del mismo paciente se crean y revisan de forma independiente, recuperable y doctor-scoped, sin sobrescritura histórica.
- [ ] **PHASE 3 — AMBULATORY CONSULTATION WORKSPACE** · `NOT_STARTED`. Construir el flujo diario con resumen del paciente, motivo/evolución, exploración/mediciones, valoración, plan, documentos/acciones y revisión/finalización. **Salida:** una consulta ambulatoria normal se completa sin saltos innecesarios entre módulos.
- [ ] **PHASE 4 — LONGITUDINAL FOLLOW-UP** · `NOT_STARTED`. Evaluar comparación anterior/actual, mediciones históricas, tendencias, historial de medicación y recetas, resultados y pendientes clínicos. **Salida:** preguntas centrales de seguimiento se responden rápidamente con fecha y procedencia explícitas.
- [ ] **PHASE 5 — CLINICAL ↔ ADMINISTRATIVE RELATIONSHIP** · `NOT_STARTED`. Permitir localizar cita, pago, recibo y factura sin fusionar autoridades financieras y clínicas. **Salida:** el estado administrativo relacionado con una consulta es localizable y mantiene la separación de dominio.
- [ ] **PHASE 6 — SPECIALTY MODULES** · `NOT_STARTED`. Implementar progresivamente extensiones validadas **después** de estabilizar el núcleo de consulta. Cada especialidad exige análisis de flujo, validación clínica, contratos de datos y UX, estrategia histórica/versionado y QA.

### Entregables de auditoría de PHASE 0 — aceptados

El [capítulo CLIN-REFORM-PHASE0-AUDIT01](EXPEDIENTE_CURRENT_STATE_AUDIT_PHASE0.md), de **sólo lectura para el producto**, presenta una matriz de estado actual con una fila por cada sección: Datos Generales, Exploración Física, Historia Clínica, Historial de Atención, Estudios Diagnóstico, Tratamiento / Recetas, Manejo Hospitalario, Documentos Clínicos y Archivo. AUDIT01 está `ACCEPTED`. [AUDIT02](EXPEDIENTE_CURRENT_STATE_AUDIT_PHASE0.md#clin-reform-phase0-audit02--physical-runtime-validation) agrega validación física segura y está `ACCEPTED`. La matriz original conserva su evidencia histórica; el cierre canónico y los límites de prueba constan aquí.

Cada fila deberá incluir exactamente estas columnas; un dato no comprobado se marcará `NO_VERIFICADO` y no se inferirá:

```text
SECTION | CURRENT_UI | FUNCTIONAL_STATUS | READ_AUTHORITY | WRITE_AUTHORITY |
STORAGE_TABLES | PATIENT_SCOPED | DOCTOR_SCOPED | ENCOUNTER_SCOPED |
CASE_SCOPED | PERSISTS_AFTER_RELOAD | BEHAVIOR_ON_NEXT_ENCOUNTER |
KNOWN_BLOCKERS | LOCAL_FIXTURE_DEPENDENCY | DUPLICATE_AUTHORITY_RISK | NOTES
```

La auditoría contrasta código, contratos, entorno y comportamiento físico disponible; las pruebas impedidas por el contrato de cero escrituras o por GET con potencial DDL conservan `NO_VERIFICADO`. Esos límites ya no impiden cerrar el levantamiento del estado actual; requieren validación controlada en capítulos posteriores.

### CLIN-REFORM-PHASE0-CLOSEOUT — hallazgos aceptados

1. **Identidad del paciente:** la identidad y persistencia canónicas (`patients_*`) son longitudinales/administrativas y distintas de la consulta; Datos Generales no debe duplicarse por encounter.
2. **Exploración Física:** la autoridad vigente es un borrador mutable por paciente en `clinical_record_entries`, sin versión independiente por consulta ni fecha efectiva, médico y encounter canónicos por medición.
3. **Historia Clínica:** antecedentes longitudinales y contenido de una consulta coexisten en el mismo borrador mutable del paciente; PHASE 1 debe resolver esa temporalidad mixta.
4. **Ausente no es normal:** la fuente contiene un estado/fallback «normal» de exploración. `MISSING_INFORMATION != NORMAL_FINDING`; no se atribuye normalidad a una exploración no registrada. Su comportamiento físico de guardado no se probó.
5. **Consulta:** `clinical_encounters` permanece como autoridad canónica; no se creará una autoridad paralela. Los encuentros legacy sin atribución médica son una restricción existente y no se reasignan por inferencia.
6. **Historial:** el fatal local provenía del router de QA ausente; el servidor PHP directo sirve el shell. La timeline con datos quedó `NO_VERIFICADO_SAFETY_GATE` porque sus GET pueden ejecutar DDL; ello no bloquea este cierre.
7. **GET clínico:** algunos GET invocan aseguramiento de esquema con `CREATE`/`ALTER`. `CLINICAL_GET_SCHEMA_SIDE_EFFECT_RISK=OPEN`; no se repara en este capítulo.
8. **Documentos:** `clinical_documents` es una autoridad real y poblada, sin enlace universal a encounter. El futuro contrato distinguirá documento de paciente, de consulta y de episodio/caso.
9. **Receta/medicación:** las recetas documentales y la presentación de medicación vigente no son una única autoridad canónica. `PRESCRIPTION != CURRENT_MEDICATION`.
10. **Hospital:** se comprobó una inconsistencia local de propagación del paciente con la capacidad deshabilitada. La inspección del API hospitalario tampoco estableció autorización por médico/vínculo activo; no se probó acceso cruzado.
11. **Archivo:** la pestaña actual es un placeholder y no representa una autoridad canónica de archivo clínico.
12. **Especialidades:** ninguna arquitectura de expediente paralelo por especialidad está aprobada; las extensiones futuras parten del núcleo paciente + encounter.

Los hallazgos derivados de fuente, los hechos de base agregados y los comportamientos físicos se distinguen en AUDIT01/AUDIT02. Ningún `NO_VERIFICADO` se convierte aquí en prueba funcional.

### Riesgos y restricciones que continúan

```text
CLINICAL_GET_SCHEMA_SIDE_EFFECT_RISK=OPEN
PHYSICAL_EXAM_PATIENT_DRAFT_OVERWRITE_RISK=OPEN
HISTORY_MIXED_TEMPORALITY_RISK=OPEN
DEFAULT_NORMAL_SEMANTICS_RISK=OPEN
DOCUMENT_ENCOUNTER_LINKAGE_GAP=OPEN
PRESCRIPTION_MEDICATION_AUTHORITY_GAP=OPEN
HOSPITAL_CONTEXT_PROPAGATION_RISK=OPEN
HOSPITAL_AUTHORIZATION_SCOPE_RISK=OPEN
ARCHIVE_PLACEHOLDER_GAP=OPEN
LEGACY_UNATTRIBUTED_ENCOUNTERS=KNOWN_EXISTING_CONSTRAINT
```

Estos riesgos alimentan el diseño y las pruebas posteriores; no son todos bloqueos de PHASE 1. Su registro no autoriza migraciones, correcciones ni operaciones clínicas.

### Cierre de PHASE 1 y frontera de PHASE 2

El [capítulo MODEL01/MODEL01A](EXPEDIENTE_CLINICAL_INFORMATION_MODEL_UX_CONTRACT.md) es el contrato conceptual aceptado. `PATIENT` conserva identidad y estado clínico longitudinal revisado; `ENCOUNTER` representa una consulta específica; `EPISODE_CASE` es agrupación opcional visible como «Caso clínico»; `DOCUMENT` conserva artefacto versionado con contexto y procedencia; `ADMINISTRATIVE` mantiene Agenda y finanzas separados de la verdad clínica; `DERIVED_VIEW` es lectura proyectada, nunca autoridad editable paralela. Medicación vigente y problemas activos son longitudinales de primera clase. La nueva consulta obtiene instancia propia; los valores previos siguen históricos y no se autocompletan como mediciones actuales. `MISSING_INFORMATION != NORMAL_FINDING` y `UNRECORDED_PHYSICAL_EXAM != NORMAL`.

### Entregable MODEL01/MODEL01A aceptado

El [contrato de información clínica y UX](EXPEDIENTE_CLINICAL_INFORMATION_MODEL_UX_CONTRACT.md) presenta la matriz de titularidad de 68 conceptos, las reglas de mediciones/exploración, resumen derivado en seis prioridades, distinción receta/medicación, contexto documental paciente/consulta/caso, cinco áreas de navegación, workspace de consulta separado y separación administrativa: finalizar consulta no equivale a cobrar ni facturar. Las seis decisiones del Director quedaron ratificadas y el contrato completo aceptado. Sus filas `PROPUESTA` tienen efecto de **diseño conceptual aceptado**, salvo `PRUEBA`, riesgo técnico no resuelto o detalle de implementación futura. PHASE 0 sigue siendo el insumo factual vinculante; ninguna ruta, tabla ni pantalla cambia por este cierre.

Siguen diferidos, sin impedir PHASE 1 completa: diseño físico de esquema, migración de `clinical_record_entries`/borradores legacy, vínculo físico documento/encounter, reparación GET/DDL, contexto y autorización de Hospital, enmiendas históricas, almacenamiento físico de medicación y problemas activos, especialidades, «Documento libre» y encuentros legacy sin atribución. Los riesgos abiertos de PHASE 0 no se cierran por aceptar el modelo conceptual.

PHASE 2 debe diseñar y demostrar la integridad del ciclo de vida de `clinical_encounters`: iniciar, guardar/borrador, reanudar, finalizar, leer historia, enmendar/corregir, relacionar documentos, atribuir médico/paciente, vincular cita opcional y conservar varias consultas del mismo paciente. La prueba clave es que Consulta 1 permanezca intacta cuando se abre Consulta 2 con datos actuales independientes, valores previos sólo de referencia, documentos en su contexto y recarga/reanudación sin duplicados ni sobrescritura histórica. CONTRACT01 y CONTRACT01A establecen el contrato conceptual aceptado y el plan de validación, **sin ejecutar pruebas físicas**. Implementación de esquema/API exige autorización posterior separada; el workspace ambulatorio final, navegación final, módulos de especialidad, tendencias completas e integración visual de billing pertenecen a fases posteriores.

### CLIN-REFORM-PHASE2-CONTRACT01/CONTRACT01A — contrato conceptual aceptado

El [contrato de integridad de consulta](EXPEDIENTE_ENCOUNTER_INTEGRITY_CONTRACT.md) documenta el alcance comprobado en fuente y sus brechas. El Director/asistente aceptó CONTRACT01 y CONTRACT01A, incluidas D1–D5: una OPEN por médico/paciente con reanudación multioperador autorizado, `VOIDED / ANULADA` auditada, enmienda explícita de CLOSED, vínculo de cita fijo históricamente y representación híbrida de enmiendas. El plan conceptual contiene 24 escenarios sintéticos aceptados **no ejecutados**. La búsqueda actual de OPEN aún incluye al usuario que abrió; concurrencia, estado de creación controlado por cliente y cierre completamente idempotente continúan como riesgos de implementación. La lectura clínica con posible DDL bloquea validación física segura. PHYS01/PHYS01A/PHYS01B incorporan el diseño físico aceptado y amplían a 35 escenarios sin ejecutar; sólo la creación de código y archivos de migración para IMPL01 queda autorizada como siguiente capítulo.

### CLIN-REFORM-PHASE2-PHYS01 — diseño físico aceptado

El [diseño físico PHYS01](EXPEDIENTE_ENCOUNTER_PHYSICAL_DESIGN.md), aceptado sobre el candidato PHYS01B `51518d0fb0875e338a20be865ff2394075993a55`, se apoya en inspección de MySQL/InnoDB local y de las rutas/esquemas actuales. Fija unicidad OPEN por índice, inicio/cierre/anulación transaccionales, contenido y mediciones por encuentro, control de versión multioperador, enmiendas append-only, contexto documental, tratamiento legacy, eliminación de DDL en GET, ocho etapas de migración y retorno seguro. PHYS01A admite resultados nuevos relacionados con CLOSED sin mutar historia; PHYS01B añade `payload_schema_version` persistida, idempotencia durable para creaciones no START y FK históricas sin borrado en cascada. T01–T35 están **diseñadas y aceptadas, no ejecutadas**. `PHASE_2_PHYS01=ACCEPTED`, `PHASE_2_PHYS01A=ACCEPTED`, `PHASE_2_PHYS01B=ACCEPTED` y `PHYSICAL_DESIGN_ACCEPTED=true`; PHASE 2 permanece `IN_PROGRESS`.

### CLIN-REFORM-PHASE2-IMPL01 — base de repositorio aceptada; fase en progreso

IMPL01A creó la base revisable de `REPOSITORY_IMPLEMENTATION_ONLY` en `6fe5fec2156df87649668343d167ca409f0e60fb`; IMPL01A-R1 quedó en `377381ce70af2525113975eacd5ec316458a2370`; IMPL01A-R2 quedó aceptado como `REPOSITORY_FOUNDATION` en `09022adffd4e3ad0824cb923893b4b2ae0e8ec42`. R2 congela físicamente la primera auditoría CLOSED/VOIDED, completa los 12 CHECK críticos en migraciones y readiness, vuelve server-authoritative la clasificación documental, reutiliza el builder y escritor transaccional canónicos de `clinical_documents`, conserva participantes y deja documentos ordinarios en `generated` sin firma implícita. `V1_MULTIPART_DOCUMENT_WRITE=DEFERRED_FAIL_CLOSED`: bajo el gate, multipart termina con `V1_MULTIPART_STORAGE_NOT_READY` antes de mover archivos; una etapa posterior debe diseñar staging/finalización o compensación, retries, SHA idempotente, detección de huérfanos e integridad de almacenamiento privado. IMPL01B queda aceptado en `ccd2a4aa553c841ce72cb77897dd02bf8ba305bc`: `POST /documents/{id}/amendments` crea un documento nuevo, conserva el original, registra linaje append-only y usa idempotencia durable; `DOCUMENT_REVISION_CREATE_IDEMPOTENCY=IMPLEMENTED_ACCEPTED` y el prerrequisito de repositorio de T34 está satisfecho. `ENCOUNTER_V1_GET_DDL_REMOVAL=IMPLEMENTED_FOR_V1_ENCOUNTER_PATHS`; `GLOBAL_CLINICAL_GET_DDL_REMOVAL=PENDING_LATER_IMPL_STAGE`. Los encounters legacy con `doctor_id=NULL` permanecen `UNATTRIBUTED`; conciliarlos con evidencia exige otra migración autorizada que gestione de forma segura el trigger de propiedad, nunca un UPDATE runtime normal. M5 T01–T35 queda autorizado únicamente como siguiente capítulo en un ambiente nuevo, aislado, sintético y desechable; requiere dos clientes HTTP, dos sesiones autorizadas, dos conexiones InnoDB y barrera sincronizada para T04, T11 y T26. La base MXMed de trabajo, cutover, producción y PHASE 3 siguen prohibidos. `PHASE_2_IMPL01_STATUS=IN_PROGRESS`, `PHASE_2_IMPL01A=ACCEPTED`, `PHASE_2_IMPL01B=ACCEPTED` y PHASE 2 permanece `IN_PROGRESS`.

El primer ensayo MIG01A en una base MySQL **nueva, aislada, sintética y desechable** quedó `BLOCKED` por `MYSQL_1295_CREATE_TRIGGER_PREPARE_UNSUPPORTED`. MIG01A-R1 corrigió únicamente la compatibilidad del DDL de triggers y quedó `ACCEPTED` en `cc8bcf502f3953942ba67cc655490d49813401fc`. El reensayo completo desde cero en MySQL 8.4.11 terminó `PASS`: aplicación limpia y segunda ejecución de 01–04, triggers físicos, `open_guard`, 12 CHECK críticos, inmutabilidad terminal, FK históricas, recuperación parcial, deriva de esquema/trigger y readiness fueron verificados; todas las bases desechables se eliminaron (`MIG01A_RERUN_RESIDUAL_DATABASE_COUNT=0`). La evidencia fue revisada y aceptada por el Director/asistente; PHASE 2 permanece `IN_PROGRESS` y este cierre no autoriza ningún paso técnico adicional. No se autorizó conexión ni cambios sobre la base MXMed de trabajo. `modules/clinical/db/migrations/2026_09_17_clinical_encounter_doctor_attribution.sql` permanece en cuarentena y fuera de MIG01A: su atribución/backfill derivado de citas no pertenece al contrato de encounter aceptado.

El alcance futuro de MIG01A queda limitado exactamente a:

```text
modules/clinical/db/migrations/2026_09_18_01_encounter_lifecycle_integrity.sql
modules/clinical/db/migrations/2026_09_18_02_encounter_structured_content.sql
modules/clinical/db/migrations/2026_09_18_03_encounter_command_idempotency.sql
modules/clinical/db/migrations/2026_09_18_04_encounter_document_integrity.sql
```

`modules/clinical/db/migrations/2026_09_17_clinical_encounter_doctor_attribution.sql` no forma parte de ese manifiesto y continúa en cuarentena.

## Calidad y aceptación futura

Capturas, inspección visual y ausencia de errores JavaScript no bastan. La QA futura requiere escenarios clínicos completos, límites de autoridad y comprobación de persistencia/historia. Escenario canónico:

```text
PACIENTE NUEVO → PRIMERA CONSULTA → FINALIZAR → CONSULTA DE SEGUIMIENTO
→ COMPARAR DATOS PREVIOS → REVISAR RECETA ANTERIOR → EMITIR DOCUMENTO NUEVO
→ FINALIZAR → LOCALIZAR RECIBO/FACTURA → REABRIR CONSULTA HISTÓRICA
```

La consulta histórica debe permanecer intacta.

## Decisiones aceptadas, bloqueos y decisiones superadas

Los principios de dominio, la separación de autoridades y la secuencia de fases de este plan siguen vigentes. PLAN01 no aprobó una nueva pantalla, esquema, endpoint ni cambio de flujo. Las cinco observaciones iniciales tienen resultado en PHASE 0; los riesgos abiertos constan en la lista de continuidad y cada decisión de implementación posterior requiere su propio capítulo y evidencia.

### SUPERSEDED DECISIONS

Sin entradas iniciales. Una decisión rechazada o sustituida se trasladará aquí con fecha, decisión anterior, motivo y autoridad que aprobó la sustitución; nunca se borrará silenciosamente.

## Update Protocol

1. Cada capítulo de reforma aceptado actualizará este plan si cambia el estado de fase, las decisiones aceptadas, los bloqueos, el commit base o el siguiente paso autorizado.
2. Ninguna fase pasa a `COMPLETE` por un autorreporte de Codex. Requiere aceptación del Director/asistente.
3. Mantener `CURRENT_ACCEPTED_HEAD` en la cabecera. Actualizarlo sólo al aceptar un nuevo baseline.
4. Mantener `NEXT_AUTHORIZED_STEP` explícito; no ejecutar pasos posteriores por inferencia.
5. Mantener un CHANGE LOG conciso con fecha, capítulo, commit y decisión/resultado.
6. Conservar decisiones rechazadas o superadas en **SUPERSEDED DECISIONS**, con fecha y motivo.
7. Antes de dar instrucciones estructurales de Expediente en conversaciones futuras, leer este plan junto al Plan Maestro.

### CHANGE LOG

| DATE | CHAPTER | COMMIT / BASELINE | DECISION / RESULT |
| --- | --- | --- | --- |
| 2026-09-18 | REFORM-PLAN01 | Pre-plan baseline/checkpoint `4bf0d740fe82949dbc0450ff6ee6e65185216aff`; accepted commit `edfa1326c602a1efcd3c94cfb705c582a170516e` | Plan creado y aceptado (`CLIN-REFORM-PLAN01=ACCEPTED`); auditoría de `PHASE_0_CURRENT_STATE_AUDIT` autorizada como siguiente capítulo, aún no iniciada. |
| 2026-09-18 | CLIN-REFORM-PHASE0-AUDIT01 | Baseline aceptado `7a4a29936135d689b6386bff5ac686e42359c145`; commit de auditoría `b31e3aeea63a535ddb0074dbd2d5d3b69be310ef` | Matrices y hallazgos de las nueve secciones, temporalidad, alcance, seguridad y dependencias locales; `READY_FOR_DIRECTOR_REVIEW`. PHASE 0 permanece `IN_PROGRESS`; siguiente paso: revisión del Director/asistente. |
| 2026-09-18 | CLIN-REFORM-PHASE0-AUDIT02 | Baseline aceptado `d0602f9c443c1d3e215934c4cc2aa6084e126d12`; commit de auditoría física `504bd136854518d301915d743911c5f0f60c7aa1` | AUDIT01 aceptado. Servidor local sin router histórico, lectura/recarga de identidad y navegación seguras verificadas; timeline y GET clínicos con posible DDL quedan sin ejecutar. AUDIT02 `READY_FOR_DIRECTOR_REVIEW`; PHASE 0 `IN_PROGRESS`, pendiente de decisión del Director/asistente. |
| 2026-09-18 | CLIN-REFORM-PHASE0-CLOSEOUT | Baseline aceptado previo al cierre `ef378fddef3edaff07f603d965defa82365a80de`; cadena AUDIT02 `504bd136854518d301915d743911c5f0f60c7aa1` → `ef378fddef3edaff07f603d965defa82365a80de` | AUDIT02 aceptado; PHASE 0 `COMPLETE`, mapa factual y riesgos heredados aceptados. PHASE 1 autorizada para diseño/contrato, `NOT_STARTED`. Sin implementación de producto. |
| 2026-09-18 | CLIN-REFORM-PHASE1-MODEL01 | Baseline aceptado/checkpoint `a1dd2860f90094260c08388d363410198e5a7495` | Contrato de titularidad clínica y UX documentado para revisión; `PHASE_1_STATUS=IN_PROGRESS`, `PHASE_1_MODEL01=READY_FOR_DIRECTOR_REVIEW`. Seis decisiones del Director pendientes. Sin implementación ni diseño de esquema. |
| 2026-09-18 | CLIN-REFORM-PHASE1-MODEL01A | Baseline de trabajo/checkpoint `0ba07c9d8798ee6ecf03083453f5a587fff812b8`; último aceptado `a1dd2860f90094260c08388d363410198e5a7495` | Seis decisiones del Director ratificadas e incorporadas a la matriz/contrato; `PHASE_1_MODEL01=DIRECTOR_DECISIONS_RATIFIED_READY_FOR_FINAL_ACCEPTANCE`. PHASE 1 sigue `IN_PROGRESS`, pendiente aceptación final. Sin cambios runtime, API, esquema o datos. |
| 2026-09-18 | CLIN-REFORM-PHASE1-CLOSEOUT | Baseline aceptado previo al cierre `63d9e22403ce64ac8a49f2b06afe3f875724baa3`; MODEL01 `0ba07c9d8798ee6ecf03083453f5a587fff812b8` y MODEL01A `63d9e22403ce64ac8a49f2b06afe3f875724baa3` | MODEL01/MODEL01A aceptados; seis decisiones ratificadas; PHASE 1 `COMPLETE`, titularidad/ciclo de vida conceptual aceptados; PHASE 2 autorizada y `NOT_STARTED` para contrato/plan. Sin implementación. |
| 2026-09-18 | CLIN-REFORM-PHASE2-CONTRACT01 | Baseline aceptado/checkpoint previo `8698b1f66466867360651db5fa62e54d28fba797` | Contrato de ciclo de vida propuesto, implementación actual y brechas mapeadas, plan de 20 pruebas controladas documentado, cinco decisiones del Director identificadas; PHASE 2 `IN_PROGRESS`, CONTRACT01 `READY_FOR_DIRECTOR_REVIEW`. Sin cambios runtime, API, esquema, datos ni validación con escrituras. |
| 2026-09-18 | CLIN-REFORM-PHASE2-CONTRACT01A | Baseline de trabajo/checkpoint `2966cda3b4c95b97a8698fa1ea84e144a144fe8c`; último aceptado `8698b1f66466867360651db5fa62e54d28fba797` | D1–D5 ratificadas: scope OPEN médico/paciente, `VOIDED / ANULADA`, corrección CLOSED por enmienda, cita histórica fija y enmienda híbrida. Plan ampliado a 24 escenarios sin ejecutar; CONTRACT01 `DIRECTOR_DECISIONS_RATIFIED_READY_FOR_FINAL_ACCEPTANCE`, PHASE 2 `IN_PROGRESS`. Sin cambios runtime, API, esquema o datos. |
| 2026-09-18 | CLIN-REFORM-PHASE2-CONTRACT01B | Baseline aceptado previo al cierre `8c8a34314c39a34d43c5b34d5af580c5e4358d72` | CONTRACT01 y CONTRACT01A aceptados como autoridad conceptual; D1–D5 finales y 24 escenarios de validación aceptados, no ejecutados. PHASE 2 sigue `IN_PROGRESS`. Autorizado sólo el siguiente capítulo de diseño físico, migración y validación controlada; implementación y validación con escrituras no autorizadas. |
| 2026-09-18 | CLIN-REFORM-PHASE2-PHYS01 | Baseline aceptado/checkpoint `4161120ad2c8a54b3e1455019f4ba994a6a9fd26` | Arquitectura física, persistencia por consulta, observaciones, concurrencia/idempotencia, enmiendas, legacy, retiro de DDL en GET, migración/corte/retorno seguro y QA sintética documentados; `READY_FOR_DIRECTOR_REVIEW`, 28 escenarios sin ejecutar. Sin implementación, API, esquema ni datos modificados. |
| 2026-09-18 | CLIN-REFORM-PHASE2-PHYS01A | Candidato PHYS01 `76fda0529840eb51c7c28b106b43a8e2069f7052`; último baseline aceptado `4161120ad2c8a54b3e1455019f4ba994a6a9fd26` | Arquitectura PHYS01 preservada; separación entre mutación histórica prohibida y artefacto relacionado posterior, con resultados elegibles vinculables a CLOSED, nota final estable y pendiente derivado de orden/resultado. T29–T30 amplían a 30 escenarios sin ejecutar. `PHASE_2_PHYS01=REPAIRED_READY_FOR_DIRECTOR_REVIEW`, `PHASE_2_PHYS01A=READY_FOR_DIRECTOR_REVIEW`. Sin implementación, API, esquema, migraciones ni datos modificados. |
| 2026-09-18 | CLIN-REFORM-PHASE2-PHYS01B | Candidato PHYS01A `cd9350e66e528e7d0220c3621f62632ae7014af8`; último baseline aceptado `4161120ad2c8a54b3e1455019f4ba994a6a9fd26` | Versión de esquema del payload persistida; idempotencia durable de comandos de creación no START con ledger transaccional; política FK/delete no destructiva. T31–T35 amplían a 35 escenarios sin ejecutar. Arquitectura física restante intacta; PHYS01 `FINAL_REPAIRED_READY_FOR_DIRECTOR_REVIEW`, PHYS01A `INCORPORATED`, PHYS01B `READY_FOR_DIRECTOR_REVIEW`. Sin implementación, API, esquema, migraciones ni datos modificados. |
| 2026-09-18 | CLIN-REFORM-PHASE2-PHYS01C | Diseño candidato aceptado `51518d0fb0875e338a20be865ff2394075993a55` | PHYS01/PHYS01A/PHYS01B y 35 escenarios físicos aceptados; gates G1–G6 de diseño aceptados. PHASE 2 sigue `IN_PROGRESS`. IMPL01 autorizado sólo para cambios en repositorio y permanece `NOT_STARTED`; ejecución de migraciones, QA con escrituras y cutover runtime no autorizados. Sin cambios runtime/API/esquema/datos ni migraciones ejecutadas. |
| 2026-09-18 | CLIN-REFORM-PHASE2-IMPL01A | Baseline aceptado `6730a59cfdaf92ddd45ddb271c1a0c7a2a3a7ec8` | Base de implementación en repositorio creada para revisión: migraciones no ejecutadas, V1 bajo gate apagado, secciones/observaciones/enmiendas, idempotencia, lifecycle/finalize/void, política/lineage documental y QA estática/pura. UI vigente sin cutover; cero mutación DB y cero QA HTTP con escrituras. `READY_FOR_CODE_REVIEW`. |
| 2026-09-19 | CLIN-REFORM-PHASE2-IMPL01A-CLOSEOUT | Candidato aceptado `09022adffd4e3ad0824cb923893b4b2ae0e8ec42` | IMPL01A-R2 aceptado exclusivamente como `REPOSITORY_FOUNDATION`; PHASE 2 e IMPL01 siguen `IN_PROGRESS`. No se ejecutaron migraciones y el feature gate permanece apagado/sin activar. MIG01A queda autorizado como siguiente capítulo sólo para ensayo de migración en una base MySQL nueva, aislada, sintética y desechable; estado `NOT_STARTED`. |
| 2026-09-19 | CLIN-REFORM-PHASE2-MIG01A-R1 | Baseline aceptado `09022adffd4e3ad0824cb923893b4b2ae0e8ec42`; reparación candidata sobre `601fd17de2c31475645369882210e6d2380b8901` | El primer ensayo físico desechable MIG01A se bloqueó en MySQL 8.4.11 con error 1295 porque `CREATE TRIGGER` se intentó mediante `PREPARE/EXECUTE`. La base MXMed de trabajo no se tocó y todas las bases desechables se eliminaron. R1 repara únicamente la compatibilidad del DDL de triggers y queda pendiente de revisión de código del Director/asistente; no autoriza repetir el ensayo físico. |
| 2026-09-19 | CLIN-REFORM-PHASE2-MIG01A-R1-CLOSEOUT | R1 aceptado `cc8bcf502f3953942ba67cc655490d49813401fc` | Reparación del bloqueo de DDL de triggers en MySQL 8.4 aceptada. Este cierre no ejecutó reintento físico; autoriza un nuevo ensayo MIG01A completo desde cero sólo en bases locales aisladas, sintéticas y desechables. La base MXMed de trabajo permanece prohibida. |
| 2026-09-19 | CLIN-REFORM-PHASE2-MIG01A-RERUN | Fuente R1 aceptada `cc8bcf502f3953942ba67cc655490d49813401fc`; autorización `77389a60ce007287f8f62ac30d88c1ea6eee22c1` | Reensayo físico completo en MySQL 8.4.11 `PASS` sobre bases locales aisladas, sintéticas y desechables: aplicación y rerun 01–04, triggers R1, invariantes, recuperación parcial, derivas fail-closed y readiness verificados. Teardown completo con cero bases residuales. Evidencia `READY_FOR_DIRECTOR_REVIEW`; la base MXMed de trabajo permanece prohibida. |
| 2026-09-19 | CLIN-REFORM-PHASE2-MIG01A-CLOSEOUT | Evidencia aceptada `da31ed437fed8867ac0cb45342f4eb03c2c476e1` | El primer ensayo quedó históricamente bloqueado por MySQL 1295; R1 reparó el DDL de triggers y el reensayo completo posterior pasó. Aplicación, convergencia e invariantes de 01–04 se probaron únicamente en MySQL local, sintético y desechable; todas las bases se eliminaron. MIG01A `ACCEPTED`; la base MXMed de trabajo sigue sin migrar y no autorizada. PHASE 2 permanece `IN_PROGRESS`. |
| 2026-09-19 | CLIN-REFORM-PHASE2-IMPL01B | MIG01A aceptado `da31ed437fed8867ac0cb45342f4eb03c2c476e1`; candidato sobre `40a55d339f957780fdb19b50fed1936a5a666642` | Se implementó para revisión el restante conocido de T34: ruta canónica de enmienda/reemplazo documental con documento nuevo, linaje append-only e idempotencia durable. No se ejecutaron migraciones, escrituras físicas, T01–T35 ni activación del feature gate; M5 permanece no autorizado pendiente de revisión. |
| 2026-09-19 | CLIN-REFORM-PHASE2-IMPL01B-CLOSEOUT | Candidato aceptado `ccd2a4aa553c841ce72cb77897dd02bf8ba305bc` | Comando canónico append-only e idempotencia durable de revisión documental aceptados; prerrequisito de repositorio T34 satisfecho. M5 T01–T35 queda autorizado como siguiente capítulo sólo en QA sintética desechable con concurrencia real; no se ejecutó M5 y la base MXMed de trabajo permanece prohibida. PHASE 2 sigue `IN_PROGRESS`. |
| 2026-09-19 | CLIN-REFORM-PHASE2-M5-PREP01 | Baseline aceptado `ccd2a4aa553c841ce72cb77897dd02bf8ba305bc`; candidato sobre `5cec02501076267d7bf28e949f7bf1872f77fa2f` | Instrumentación QA-only de rendezvous determinista preparada para T04, T11 y T26; apagada por defecto, limitada a entorno local/dev desechable explícito y sin estado clínico. `READY_FOR_CODE_REVIEW`; no se ejecutaron M5, T01–T35, HTTP writes, migraciones ni conexión a la base MXMed de trabajo. |
| 2026-09-19 | CLIN-REFORM-PHASE2-M5-PREP01-R1 | Hallazgo de revisión sobre `5646cfa5386f2363dc47e32b78b4accf0b8974de` | Eliminada la dependencia runtime incondicional del árbol QA: con modo M5 apagado no se carga ni requiere la implementación; con activación explícita se carga de forma diferida y su ausencia falla cerrada. Barreras, guardas y estado M5 `NOT_STARTED` preservados; sin DB, HTTP writes, migraciones ni ejecución T01–T35. |
| 2026-09-19 | CLIN-REFORM-PHASE2-M5-PREP01-CLOSEOUT | PREP01 original y reparación R1 aceptados en `5f7aa2224a9af4d6ad6bb4e091d084e7d52e8f1d` | Aceptadas las barreras deterministas T04/T11/T26 y la carga diferida R1 que elimina la dependencia runtime incondicional del árbol QA: modo OFF no requiere el archivo y modo ON falla cerrado si falta. M5 sigue autorizado pero `NOT_STARTED`; T01–T35 no ejecutados y base MXMed de trabajo prohibida. |
| 2026-09-19 | CLIN-REFORM-PHASE2-M5-ADJ01 | Adjudicación del Director sobre T31 antes de M5 | El diseño pedía leer V1 bajo soporte V2, pero no existe contrato V2 real y se rechazó inventarlo para QA. T31A exige ahora seguridad/versionado V1 y rechazo fail-closed de versiones desconocidas; T31B conserva la obligación real V1/V2 cuando exista contrato aceptado. Se mantienen 35 escenarios, no se autoriza V2 y M5 no fue ejecutado. |
| 2026-09-19 | CLIN-REFORM-PHASE2-M5-EXEC01 | Fuente ADJ01 aceptada `c1fa6bea057992d35f4ccc7e0f8534cbe8a5a4b7` | Primer intento físico M5: T01–T25 `PASS`; T26 `FAIL_QA_HARNESS` por carrera de creación del directorio compartido antes del row lock; T27–T35 no ejecutados por fail-fast. Teardown completo: cero bases, procesos HTTP o estado de barrera residuales. La base MXMed de trabajo no se conectó. Resultado histórico `BLOCKED`, no aceptación final parcial. |
| 2026-09-19 | CLIN-REFORM-PHASE2-M5-PREP01-R2 | Baseline/checkpoint previo `c1fa6bea057992d35f4ccc7e0f8534cbe8a5a4b7`; reparación aceptada `2c9d725eeb02bdecaa8dec894ad414862b8d9f82` | Aceptada la creación idempotente y segura ante concurrencia del directorio compartido, con error real fail-closed y regresión física de dos procesos. La aceptación autorizó el segundo intento M5 completo desde T01 en un único source HEAD. |
| 2026-09-19 | CLIN-REFORM-PHASE2-M5-EXEC01-RERUN | PREP01-R2 aceptado y fuente única `2c9d725eeb02bdecaa8dec894ad414862b8d9f82` | Segundo intento físico M5 desde T01: T01–T26 `PASS`; T27 `FAIL_HTTP_MAPPING` porque `DOCUMENT_CONTEXT_MISMATCH` evitó documento e idempotencia pero fue traducido a `500/server_error`; T28–T35 no ejecutados por fail-fast. Teardown completo con cero bases, procesos HTTP o barreras residuales; base MXMed de trabajo no conectada. |
| 2026-09-19 | CLIN-REFORM-PHASE2-M5-REPAIR01 | Baseline aceptado/checkpoint `2c9d725eeb02bdecaa8dec894ad414862b8d9f82` | Reparación limitada al `catch` compartido de `POST /encounters/{encounter_key}/documents`: la rama V1 delega código/estado a los mapeadores canónicos y conserva el comportamiento legacy. `READY_FOR_CODE_REVIEW`; M5 permanece `BLOCKED` y otro rerun T01–T35 no está autorizado hasta revisión. |
| 2026-09-19 | CLIN-REFORM-PHASE2-M5-EXEC01-RERUN2 | REPAIR01 aceptado y fuente única `115923cac322958ad4f443ab783a8cf19f9c5093` | Tercer intento físico M5 completo desde T01: T01–T35 `PASS`; T04/T11/T26 probaron concurrencia real con dos conexiones InnoDB; T27 devolvió `409/DOCUMENT_CONTEXT_MISMATCH` sin insertar documento ni idempotencia; T28 no produjo DDL/DML; deadlock controlado e invariantes posteriores `PASS`. Teardown completo con cero bases, procesos HTTP, barreras o raíces temporales residuales; la base MXMed de trabajo no se conectó. `PHASE_2_M5_STATUS=PASS_READY_FOR_DIRECTOR_REVIEW`; M6, migración de base de trabajo, cutover, producción y PHASE 3 continúan no autorizados. |
| 2026-09-19 | CLIN-REFORM-PHASE2-M5-CLOSEOUT | Evidencia aceptada `b069fe7cfa66fc71a2aaf323676dc74a411f1ec2`; source probado y aceptado `115923cac322958ad4f443ab783a8cf19f9c5093` | M5 y la matriz física T01–T35 quedan `ACCEPTED`; concurrencia T04/T11/T26, reparación T27, ausencia de DDL/DML en T28, T31A, idempotencia T32–T34, integridad T35, deadlock controlado, aislamiento y teardown aceptados. PHASE 2 continúa `IN_PROGRESS`; sólo queda autorizada la preparación/revisión del plan y preflight M6. Ejecución M6, migración de la base de trabajo, feature gate, cutover, producción y PHASE 3 permanecen no autorizados. |
| 2026-09-19 | CLIN-REFORM-PHASE2-M6-PLAN01 | Baseline/checkpoint `4d99237e3fe87679bfb74cb30ac213d7530a30f5`; source clínico aceptado `115923cac322958ad4f443ab783a8cf19f9c5093` | Inventario completo de 21 familias runtime y preflight de sólo lectura de la base de trabajo. Esquema `PRE_MIGRATION`; 13 encounters legacy `UNATTRIBUTED`; migraciones 01–04 no aplicadas. Plan de backup/restore, clon, cuenta separada, ventana, gate, monitoreo, abort y retorno seguro documentado. `READY_FOR_DIRECTOR_REVIEW`, `NO_GO_BLOCKED`; cero cambios DB/runtime y ejecución M6 no autorizada. |
| 2026-09-19 | CLIN-REFORM-PHASE2-M6-CTRL01 | PLAN01 aceptado `7dc615c772ef611a49229e3e1da91b569d6cf73d` | La revisión detectó que emergency OFF borraba la membresía configurada por paciente y podía reabrir futuros writers legacy durante retorno seguro. CTRL01 queda `BLOCKED_PENDING_R1_REVIEW`; sin wiring runtime, bloqueo legacy activo, conexión DB, migración, gate o cutover. |
| 2026-09-19 | CLIN-REFORM-PHASE2-M6-CTRL01-R1 | Reparación sobre `0c527528847ae425f373c062baf6b9d89821101b` | Separadas membresía configurada, autorización activa V1 y decisión futura de bloqueo legacy. Emergency OFF detiene routing M6 pero conserva membresía y bloqueo; configuración activa inválida sigue fallando con `M6_COHORT_CONFIG_INVALID` y no puede desbloquear legacy. `READY_FOR_CODE_REVIEW`; sin wiring runtime ni cambios DB. M6 continúa `NO_GO_BLOCKED`. |
| 2026-09-19 | CLIN-REFORM-PHASE2-M6-GUARD01 | CTRL01/R1 aceptado `05369176c3fc6a8b89043ee79c6b431161b3d5f4` | Candidato repository-only de contención para C04/C05/C11/C12/C16/C17/C20/C21 con error estable `409/M6_LEGACY_WRITE_BLOCKED`, paciente como autoridad, emergency OFF seguro, configuración inválida fail-closed y lecturas preservadas. Sin routing V1, activación normal de cohorte, DB, migración ni cutover. `READY_FOR_CODE_REVIEW`; cuenta aceptada permanece 11 y M6 sigue `NO_GO_BLOCKED`. |
| 2026-09-19 | CLIN-REFORM-PHASE2-M6-CALLER01 | GUARD01 aceptado `bcb2606ba7a95818673b402a9e006ecc0431ff73` | Candidato repository-only: C02 añade idempotencia estable y manejo 200/201 sin fallback; C03 exige encounter y usa la ruta documental canónica sin autoridad doctor del cliente ni fallback legacy; C14 bloquea el bridge antes de HTTP para pacientes M6, incluso emergency OFF o configuración inválida. Cuenta aceptada 3, candidata 0. Sin routing, gate, DB, migración ni cutover. `READY_FOR_CODE_REVIEW`; M6 sigue `NO_GO_BLOCKED`. |
