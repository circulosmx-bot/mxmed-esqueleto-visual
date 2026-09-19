# CLIN-REFORM-PHASE2-MIG01A — ensayo físico desechable

```text
DATE=2026-09-19
FIRST_REHEARSAL=BLOCKED_MYSQL_1295
RERUN_AFTER_R1=PASS
MYSQL_VERSION=8.4.11
MYSQL_SERVER_CLASS=LOCAL_DEVELOPMENT_DOCKER
MYSQL_SERVER_HOSTNAME=cf962b03dc6f
MYSQL_ENDPOINT=127.0.0.1:3309
TARGET_CLASS=DISPOSABLE_SYNTHETIC_LOCAL_ONLY
WORKING_MXMED_DB_CONNECTED_FOR_MIGRATION=false
REAL_DATA_USED=false
MIG01A_RERUN_RESIDUAL_DATABASE_COUNT=0
```

## Alcance y seguridad

El reensayo utilizó el servidor MySQL 8.4.11 del contenedor local de desarrollo expuesto en `127.0.0.1:3309`. El nombre de la base MXMed normal se obtuvo sólo de metadatos de configuración para excluirla; no se consultaron sus filas ni se ejecutó DDL/DML contra ella. Todos los identificadores y datos fueron sintéticos.

Se crearon desde cero y se eliminaron al terminar únicamente estas bases:

```text
CLEAN_DB=mxmed_clinical_mig01a_20260919_154716_clean
PARTIAL_DB=mxmed_clinical_mig01a_20260919_154716_partial
DRIFT_DB=mxmed_clinical_mig01a_20260919_154716_drift
```

La comprobación previa confirmó que no existían. El conteo exacto después del teardown fue cero. No se importó ningún dump ni se utilizó información de pacientes, médicos, citas, documentos o facturación reales.

## Manifiesto ejecutado

Se ejecutaron exclusivamente, en este orden, los archivos aceptados:

1. `2026_09_18_01_encounter_lifecycle_integrity.sql`
2. `2026_09_18_02_encounter_structured_content.sql`
3. `2026_09_18_03_encounter_command_idempotency.sql`
4. `2026_09_18_04_encounter_document_integrity.sql`

`2026_09_17_clinical_encounter_doctor_attribution.sql` permaneció en cuarentena y no fue leído como fixture ni ejecutado. No hubo inferencia o backfill de médico.

## Historial del primer intento y reparación R1

El primer ensayo se bloqueó en MySQL 8.4.11 con error 1295 porque la migración 01 intentaba crear triggers mediante `PREPARE/EXECUTE`. Sus bases desechables se eliminaron y el conteo residual fue cero.

El reensayo utilizó la reparación R1 aceptada en `cc8bcf502f3953942ba67cc655490d49813401fc`. La migración 01 aplicó correctamente. La inspección física confirmó dos triggers `BEFORE` sobre `clinical_encounters`:

- `trg_clinical_encounters_v1_before_insert`, evento `INSERT`, con `START_MUST_CREATE_OPEN` y `DOCTOR_ID_REQUIRED`;
- `trg_clinical_encounters_v1_before_update`, evento `UPDATE`, con `ENCOUNTER_OWNERSHIP_IMMUTABLE`, `ENCOUNTER_TRANSITION_FORBIDDEN`, `FIRST_CLOSE_IMMUTABLE` y `FIRST_VOID_IMMUTABLE`.

```text
MYSQL_1295_TRIGGER_BLOCKER_RESOLVED=true
BEFORE_INSERT_TRIGGER_PHYSICALLY_CREATED=true
BEFORE_UPDATE_TRIGGER_PHYSICALLY_CREATED=true
```

## Resultados físicos

### Aplicación limpia y convergencia

Las migraciones 01–04 aplicaron en orden sin error. Una segunda ejecución completa, sin reiniciar la base limpia, volvió a aplicar las cuatro con resultado `PASS`; no produjo objetos duplicados ni reescrituras destructivas.

```text
MIGRATION_01_CLEAN_APPLY=PASS
MIGRATION_02_CLEAN_APPLY=PASS
MIGRATION_03_CLEAN_APPLY=PASS
MIGRATION_04_CLEAN_APPLY=PASS
MIGRATION_01_SECOND_RUN=PASS
MIGRATION_02_SECOND_RUN=PASS
MIGRATION_03_SECOND_RUN=PASS
MIGRATION_04_SECOND_RUN=PASS
FULL_MIGRATION_RERUN_CONVERGES=true
```

### `open_guard` y conservación legacy

La columna quedó como `TINYINT`, nullable y `STORED GENERATED`, dependiente del estado canónico `open`. El índice único físico fue `doctor_id,patient_id,open_guard`.

Un segundo OPEN para el mismo médico/paciente falló con error 1062. Tras cerrar correctamente el primero se pudo crear otro OPEN, y los estados terminales usaron `open_guard=NULL`. El encounter legacy sintético preexistente conservó `doctor_id=NULL`.

```text
OPEN_GUARD_GENERATED_VERIFIED=true
ONE_OPEN_PER_DOCTOR_PATIENT_VERIFIED=true
TERMINAL_NULL_UNIQUENESS_VERIFIED=true
LEGACY_NULL_DOCTOR_PRESERVED=true
LEGACY_DOCTOR_INFERENCE_EXECUTED=false
```

### CHECK, inmutabilidad y relaciones históricas

MySQL rechazó físicamente casos inválidos de ciclo de vida, tipo y versión de sección, versión y fuente de observación, representación de presión arterial, razón vacía de enmienda, contexto/operación de idempotencia y razón vacía de revisión documental. Los 12 CHECK críticos del manifiesto de readiness estaban presentes.

Las mutaciones individuales de `closed_at`, `closed_by_user_id` y `auto_note_uuid_final` devolvieron `FIRST_CLOSE_IMMUTABLE`. Las mutaciones de `voided_at`, `voided_by_user_id` y `void_reason` devolvieron `FIRST_VOID_IMMUTABLE`; la reapertura terminal devolvió `ENCOUNTER_TRANSITION_FORBIDDEN`.

La eliminación destructiva fue rechazada para encounter con sección/observación, encounter con nota final y documento original con revisión. La relación preexistente de participantes conservó su semántica `CASCADE` aceptada.

```text
SCHEMA_READY_CRITICAL_CHECK_COUNT=12
CHECK_CONSTRAINTS_PHYSICALLY_ENFORCED=true
FIRST_CLOSE_FIELDS_DB_IMMUTABLE=true
AUTO_NOTE_UUID_FINAL_DB_IMMUTABLE=true
FIRST_VOID_FIELDS_DB_IMMUTABLE=true
ILLEGAL_TERMINAL_TRANSITION_REJECTED=true
HISTORICAL_CHILD_DELETE_RESTRICT_VERIFIED=true
```

### Recuperación parcial y deriva fail-closed

En la base parcial se creó una tabla de secciones compatible pero incompleta, sin sus índices, FK ni CHECK aceptados. El manifiesto 01–04 añadió los objetos ausentes y readiness terminó en `PASS`.

En la base de deriva, una columna `open_guard` ordinaria e incompatible provocó `MIGRATION_DRIFT: incompatible open_guard`. Tras recrear esa base, un trigger con nombre canónico y cuerpo incompatible provocó `MIGRATION_DRIFT: insert trigger`; el trigger no fue sustituido ni sobrescrito.

```text
PARTIAL_MIGRATION_RECOVERY_VERIFIED=true
MISSING_COMPATIBLE_OBJECTS_CAN_CONVERGE=true
INCOMPATIBLE_MIGRATION_DRIFT_REJECTED=true
DRIFT_FAIL_CLOSED=true
INCOMPATIBLE_EXISTING_TRIGGER_REJECTED=true
TRIGGER_DRIFT_FAIL_CLOSED=true
```

### Readiness y teardown

`clinical_encounter_integrity_assert_schema_ready()` devolvió `PASS` con una conexión PDO explícita a la base limpia. Contra la base incompleta/de deriva devolvió el fallo esperado `SCHEMA_NOT_READY`.

```text
SCHEMA_READINESS_SUCCESS_VERIFIED=true
SCHEMA_READINESS_FAIL_CLOSED_VERIFIED=true
MIG01A_RERUN_RESIDUAL_DATABASE_COUNT=0
```

No se ejecutaron T01–T35, QA HTTP con escrituras, activación del feature gate, cutover, IMPL01B ni acción de producción. Esta evidencia queda `READY_FOR_DIRECTOR_REVIEW`; no autoriza migrar la base MXMed de trabajo.
