# CLIN-REFORM-PHASE2-CONTRACT01 — Integridad de la consulta clínica

```text
STATUS=DIRECTOR_DECISIONS_RATIFIED_READY_FOR_FINAL_ACCEPTANCE
PHASE_2_STATUS=IN_PROGRESS
ACCEPTED_BASELINE=8698b1f66466867360651db5fa62e54d28fba797
PHASE_2_CONTRACT01=DIRECTOR_DECISIONS_RATIFIED_READY_FOR_FINAL_ACCEPTANCE
ENCOUNTER_AUTHORITY=clinical_encounters
ENCOUNTER_OWNER=DOCTOR_ID
ACTIVE_LOOKUP_TARGET_SCOPE=DOCTOR_ID+PATIENT_ID+OPEN
MULTI_OPERATOR_RESUME_RULE=AUTHORIZED_OPERATORS_RESOLVE_SAME_OPEN_ENCOUNTER
OPENED_BY_USER_ID_IS_NOT_ENCOUNTER_OWNER=true
START_ENCOUNTER_CREATES_STATUS=OPEN_ONLY
CLIENT_CANNOT_ARBITRARILY_SELECT_ENCOUNTER_STATUS=true
CLIENT_CONTROLLED_ENCOUNTER_STATUS_RISK=OPEN
START_ENCOUNTER_IDEMPOTENCY=REQUIRED
CONCURRENT_START_SAFETY=REQUIRED
CONCURRENT_START_REQUESTS_MUST_NOT_CREATE_DUPLICATE_OPEN_ENCOUNTERS=true
FINALIZE_IDEMPOTENCY=REQUIRED
AUTO_FINAL_NOTE_MAX_ONE_PER_ENCOUNTER=true
CLOSED_TO_OPEN_TRANSITION=false
NORMAL_EDIT_OF_CLOSED_ENCOUNTER=false
VOIDED_TO_OPEN_TRANSITION=false
VOIDED_TO_CLOSED_TRANSITION=false
DELETE_ENCOUNTER_AS_NORMAL_CORRECTION=false
CROSS_ENCOUNTER_OVERWRITE_ALLOWED=false
ACTIVE_ENCOUNTER_RACE_RISK=OPEN_UNTIL_PHYSICAL_ENFORCEMENT_PROVEN
CLINICAL_GET_SCHEMA_SIDE_EFFECT_RISK=OPEN
LEGACY_UNATTRIBUTED_ENCOUNTERS=KNOWN_EXISTING_CONSTRAINT
NO_INFERENCE_OF_LEGACY_DOCTOR_OWNERSHIP=true
WRITE_VALIDATION_EXECUTED=false
WRITE_VALIDATION_AUTHORIZED=false
DIRECTOR_DECISIONS_RATIFIED=5/5
DIRECTOR_DECISIONS_REQUIRED_COUNT=0
```

El Director ratificó D1–D5 como reglas conceptuales; el contrato completo sigue **pendiente de aceptación final**. El plan de prueba controlada permanece sin ejecutar. El [plan vivo](PLAN_REFORMA_EXPEDIENTE_CLINICO.md) gobierna la fase, el [modelo aceptado de PHASE 1](EXPEDIENTE_CLINICAL_INFORMATION_MODEL_UX_CONTRACT.md) fija la titularidad conceptual y la [auditoría de PHASE 0](EXPEDIENTE_CURRENT_STATE_AUDIT_PHASE0.md) distingue evidencia de fuente, datos físicos y pruebas no ejecutadas. El [Plan Maestro](../PLAN_MAESTRO_MXMED.md) conserva autoridad global. **La ratificación no prueba funcionamiento físico ni autoriza esquema, API, migración, UI o validación con escrituras.**

## Evidencia de la implementación actual

`CURRENT_CONFIRMED` significa que la ruta o el esquema se observó en fuente; **no** que se ejecutó contra una base. `PARTIAL` indica ruta existente con una garantía incompleta o no demostrada. `NOT_CONFIRMED` indica que la fuente inspeccionada no prueba el comportamiento. Las líneas corresponden al baseline indicado arriba.

| Hecho inspeccionado | Estado | Evidencia y límite |
| --- | --- | --- |
| `clinical_encounters` es autoridad de consulta; `encounter_id`, `patient_id`, `doctor_id`, `appointment_id` opcional, `encounter_dt`, `opened_by_user_id`, estado y campos de cierre existen en fuente. | CURRENT_CONFIRMED | `api/clinical/index.php:3294-3349,3485-3532`; esquema de referencia `modules/clinical/db/schema_v2.sql:140`. No acredita filas físicas nuevas. |
| `doctor_id` atribuye clínicamente; `opened_by_user_id` registra el actor de apertura. | CURRENT_CONFIRMED | Insert de consulta en `api/clinical/index.php:3485-3524`; migración de atribución `modules/clinical/db/migrations/2026_09_17_clinical_encounter_doctor_attribution.sql`. |
| Contexto de médico/usuario de sesión y vínculo activo médico-paciente exigidos para active, list y create. | CURRENT_CONFIRMED | `api/clinical/index.php:359-390,406-432,5707-5724,5790-5806,5885-5894`. El acceso cruzado físico no se probó. |
| La cita suministrada debe coincidir con médico y paciente. | CURRENT_CONFIRMED | `api/clinical/index.php:392-398,5904-5908,3497-3501`. La cita es opcional en el insert. |
| Active lookup usa `patient_id + doctor_id + opened_by_user_id + status=open` y devuelve la más reciente. | CURRENT_CONFIRMED | `api/clinical/index.php:3559-3579,5724-5725`. Otro usuario para el mismo médico puede no encontrar la consulta abierta; no hay unicidad de médico-paciente en el DDL inspeccionado. |
| El POST de creación devuelve una abierta existente con `redirect_to_active=true` dentro de ese scope. | PARTIAL | `api/clinical/index.php:5933-5959`; es SELECT antes de INSERT sin bloqueo/constraint visible, por lo que no demuestra idempotencia bajo carrera. |
| Existe GET de lista filtrado por paciente+médico y GET de detalle con comprobación de owner y vínculo activo. | CURRENT_CONFIRMED | `api/clinical/index.php:3534-3554,5849,6755-6783`. Detalle reúne documentos directos; si no hay, permite fallback legacy por cita **sólo** para el último encounter de esa cita (`:6788-6798`). No equivale a reconstruir borradores antiguos. |
| Existe POST de finalización con `closed_at`, `closed_by_user_id` y `auto_note_uuid_final`; genera/actualiza nota automática final en `clinical_documents`. | CURRENT_CONFIRMED | `api/clinical/index.php:6444-6486,1714-1790,1550-1695`. La nota final se inserta o se actualiza por búsqueda de snapshot/clave. |
| Repetir finalize de un CLOSED con UUID final retorna el resultado sin rehacer el cierre. | PARTIAL | `api/clinical/index.php:1722-1750`. Si falta UUID, o dos solicitudes leen OPEN a la vez, la ruta no muestra bloqueo de fila ni condición `status=open` en UPDATE (`:1754-1778`). No está probada unicidad de nota final ante carrera. |
| La escritura de documento por ruta de encounter verifica owner y vínculo, y guarda `patient_id` + `encounter_id`. | PARTIAL | `api/clinical/index.php:6493-6748`. La ruta no comprueba `status=open` antes del INSERT; otras rutas de documentos tienen vínculo de encounter opcional. PHASE 0 encontró 402/413 documentos sin `encounter_id` directo, sin inferirlos. |
| Los borradores de Historia/Exploración se guardan por paciente y tipo, sin `encounter_id`/médico por medición. | CURRENT_CONFIRMED | `api/clinical/index.php:3851-3935,4475-4630`; [AUDIT01](EXPEDIENTE_CURRENT_STATE_AUDIT_PHASE0.md). La preservación por consulta no está implementada allí. |
| `clinical_encounters_ensure_schema` ejecuta `CREATE`/`ALTER` y además normaliza `completed` a `open`; rutas GET lo invocan. | CURRENT_CONFIRMED | `api/clinical/index.php:3294-3350,5713,5794,6766`; riesgo físico y semántico, no ejecutar GET como prueba sólo lectura. |
| Estado `VOIDED`, corrección histórica con enmienda, unicidad física de consulta abierta y garantía global de immutabilidad CLOSED. | NOT_CONFIRMED | No hay prueba en las rutas/esquema inspeccionados. `status` es texto libre y el POST recibe `status` del cliente, con valor por defecto `completed` (`api/clinical/index.php:5899,5917-5931`); el ensure posterior normaliza `completed` a `open`. Requiere diseño antes de implementación. |
| La migración histórica de atribución contiene un backfill de `doctor_id` desde cita coincidente. | CURRENT_CONFIRMED | `modules/clinical/db/migrations/2026_09_17_clinical_encounter_doctor_attribution.sql:28-45`. Que exista SQL no prueba que se ejecutó ni que la cita baste como procedencia clínica; su criterio debe revisarse frente a `NO_INFERENCE_OF_LEGACY_DOCTOR_OWNERSHIP` antes de cualquier reutilización. |

La tabla `clinical_encounters` de PHASE 0 tenía 13 encuentros, todos sin `doctor_id` (11 cerrados, 2 abiertos). Esos datos legacy son una restricción histórica, no evidencia de que el flujo actual de creación falle ni permiso para asignar médico por inferencia. `CURRENT_ACTIVE_SCOPE=PATIENT_ID+DOCTOR_ID+OPENED_BY_USER_ID+OPEN` y `CURRENT_START_IDEMPOTENCY=PARTIAL`; `CURRENT_FINALIZE_PRESENT=true`, `CURRENT_FINALIZE_IDEMPOTENCY=PARTIAL`, `CURRENT_AUTO_FINAL_NOTE=CONFIRMED_IN_SOURCE`.

## Titularidad y estados ratificados conceptualmente

`CLINICAL_OWNER=clinical_encounters.doctor_id`; `PATIENT_ID` identifica al sujeto de atención. `ACTOR` es el usuario autenticado que inicia o modifica una operación, incluido `opened_by_user_id` y quien cierra. `OPERATOR` es un actor autorizado a operar en el flujo del médico según capacidades existentes; esa capacidad **no** lo convierte en dueño clínico. Ninguna cita, vínculo de paciente, usuario que abrió ni autor de documento sustituye `doctor_id`. Una consulta nueva debe tener dueño médico explícito. `doctor_id=NULL` legacy permanece `UNATTRIBUTED` hasta conciliación futura basada en evidencia; `NO_INFERENCE_OF_LEGACY_DOCTOR_OWNERSHIP`.

Modelo conceptual ratificado: `OPEN → CLOSED` por finalización explícita o `OPEN → VOIDED / ANULADA` por anulación explícita de una apertura errónea sin atención clínica válida. `VOIDED` conserva identidad, paciente, médico, actor, fecha/hora, razón y rastro auditable; no es cancelación de cita, no-show, consulta completada, eliminación ni registro oculto. `VOIDED_COUNTS_AS_COMPLETED_CLINICAL_ENCOUNTER=false`; `VOIDED_COUNTS_AS_VALID_CONSULTATION=false`. `CLOSED → VOIDED` no es transición ordinaria automática; `VOIDED → OPEN` y `VOIDED → CLOSED` no son flujos normales. Una corrección extraordinaria tras cierre exige política auditada separada. No existe aquí diseño físico ni prueba de esta transición.

```text
          ┌──────────→ VOIDED / ANULADA
          │
NEW → OPEN
          │
          └──────────→ CLOSED
                         │
                         └── enmiendas explícitas
```

**Inicio.** `OPEN_PATIENT_RECORD != START_ENCOUNTER`; `TAB_NAVIGATION != START_ENCOUNTER`; `SEARCH_PATIENT != START_ENCOUNTER`; `VIEW_HISTORICAL_ENCOUNTER != START_ENCOUNTER`. Iniciar exige acción explícita, sesión autenticada, médico actual, paciente existente, vínculo médico-paciente activo y, si hay `appointment_id`, coincidencia de médico y paciente. `START_ENCOUNTER_CREATES_STATUS=OPEN_ONLY` y `CLIENT_CANNOT_ARBITRARILY_SELECT_ENCOUNTER_STATUS=true`: sólo acciones explícitas de ciclo de vida llevan a CLOSED o VOIDED. La cita es contexto administrativo opcional: `APPOINTMENT != ENCOUNTER`; `APPOINTMENT_REQUIRED_FOR_ENCOUNTER=false` permite atención directa válida sin cita. La consulta tiene identidad propia aunque la cita luego cambie.

**Ámbito de consulta abierta y concurrencia.** D1 ratifica `ONE_OPEN_ENCOUNTER_PER_DOCTOR_PATIENT`: para cada pareja médico/paciente existe a lo sumo una OPEN, independientemente de `opened_by_user_id`. La resolución futura busca `doctor_id + patient_id + status=OPEN`, sujeta a autorización canónica, **sin** exigir que el usuario actual sea quien abrió. Operador A inicia D/P; operador B, si está autorizado para ese contexto, reanuda el mismo `encounter_id`. La atribución clínica sigue en D, la autoría de apertura sigue en A. El médico puede tener OPEN para pacientes diferentes; los permisos de operador siguen gobernados por capacidades existentes. Un `SELECT` seguido de `INSERT` no basta: `CONCURRENT_START_REQUESTS_MUST_NOT_CREATE_DUPLICATE_OPEN_ENCOUNTERS=true`. El capítulo de implementación debe elegir y probar bloqueo transaccional, unicidad física aplicable u otro invariante impuesto por la base. `ACTIVE_ENCOUNTER_RACE_RISK=OPEN_UNTIL_PHYSICAL_ENFORCEMENT_PROVEN`.

**Tiempo.** `ENCOUNTER_EFFECTIVE_DATETIME` representa cuándo ocurrió/comenzó la atención; `CREATED_AT`, cuándo se creó el registro; `CLOSED_AT`, cuándo se finalizó formalmente. Pueden diferir. La fuente actual tiene `encounter_dt`, `created_at`, `closed_at`; la correspondencia exacta y correcciones requieren contrato físico posterior, sin nuevas columnas definidas aquí.

**Guardado y reanudación.** Guardar trabajo progresivo conserva `encounter_id`, paciente y médico; no crea otra consulta ni modifica una CLOSED. Componentes clínicos de la consulta pueden estar en borrador mientras el encounter sigue OPEN. El estado guardado debe ser recuperable de una autoridad de servidor tras recarga o retorno, no sólo de DOM, modal, tab, memoria o `localStorage`. Un timeout de guardado debe resolverse leyendo el estado persistido antes de reintentar; ninguna repetición puede crear consulta adicional. El borrador actual por paciente de Historia/Exploración **no** satisface por sí solo esta condición.

**Cambio/cierre de vista.** Cambiar de paciente no finaliza ni borra el encounter de A ni crea el de B. Una abierta de A queda recuperable bajo la política aceptada. `CLOSE_PATIENT_VIEW != FINALIZE_ENCOUNTER`: cerrar expediente o navegador limpia contexto de UI, no cierra atención. No se diseñan prompts en CONTRACT01.

**Finalización y anulación.** Una acción clínica explícita lleva `OPEN → CLOSED` y conserva identidad, médico, paciente, tiempo efectivo, `closed_at`, actor de cierre, contenido/documentos asociados y nota final si aplica. `FINALIZE_IDEMPOTENCY=REQUIRED`: reintentos, incluso concurrentes, devuelven el **primer** cierre sin cambiar `closed_at`, `closed_by_user_id` ni identidad de la nota. `AUTO_FINAL_NOTE_MAX_ONE_PER_ENCOUNTER=true`; la nota pertenece a una sola consulta, no reemplaza sus datos subyacentes y permanece enlazada históricamente. Una acción distinta `OPEN → VOIDED` debe ser explícita e idempotente: el reintento conserva primera razón/actor/fecha, no duplica auditoría y nunca resucita la consulta. Si falla una transacción, el resultado debe ser recuperable y el reintento seguro. Cobro/facturación permanecen separados.

**Historia y enmienda.** Una CLOSED es histórica, de lectura enfocada: fecha, médico atribuible, motivo, mediciones, exploración, valoración, plan y documentos **de esa consulta**. El estado longitudinal actual no reescribe el pasado. D3 ratifica `EXPLICIT_AMENDMENT_ONLY`, `CLOSED_REOPEN_FOR_SILENT_EDIT=false`, `CLOSED_TO_OPEN_TRANSITION=false`, `NORMAL_EDIT_OF_CLOSED_ENCOUNTER=false` y `DELETE_ENCOUNTER_AS_NORMAL_CORRECTION=false`. Una enmienda conserva identidad y contenido original, autor, fecha, razón, contenido adicional/corregido, vínculo al encounter/documento y versiones previas cuando aplica. D5 ratifica `HYBRID_BY_AFFECTED_AUTHORITY`: `ENCOUNTER_AMENDMENT` corrige medición, exploración, valoración, narrativa o plan propios del encuentro; `DOCUMENT_AMENDMENT_OR_REPLACEMENT` corrige receta, certificado, nota firmada, interconsulta u otro documento emitido. Si ambas autoridades cambian, ambas conservan su historia y relación, sin duplicar autoridad. `SIGNED_OR_FINAL_DOCUMENT_NOT_SILENTLY_REWRITTEN=true`; el original sigue recuperable. No se diseña esquema.

**Consulta 1 → Consulta 2.** Para P/D, Consulta 1 guarda peso 78 kg, PA 120/80 y motivo X y se finaliza. Consulta 2 obtiene `encounter_id` distinto; sus campos actuales de peso, PA y motivo nacen vacíos. Una vista de referencia puede mostrar los valores de Consulta 1 con fecha/origen, sin autocopiarlos como captura de hoy. Tras guardar valores diferentes en Consulta 2, Consulta 1 devuelve los originales intactos. `CROSS_ENCOUNTER_OVERWRITE_ALLOWED=false`; `PREVIOUS_VALUE_MUST_NOT_AUTOFILL_AS_CURRENT_MEASUREMENT`; información ausente o exploración no registrada **no** significa normal. Una medición futura exige lógicamente paciente, médico, consulta, tipo, valor, unidad, fecha efectiva, fecha de registro y procedencia/fuente.

**Documentos, casos y cita.** Contextos aceptados: `PATIENT_DOCUMENT`, `ENCOUNTER_DOCUMENT`, `EPISODE_DOCUMENT`. Un documento generado en E (receta, nota, orden, resultado vinculado, interconsulta o certificado pertinente) conserva E como contexto primario; puede verse en detalle, historial documental y revisión longitudinal mediante **una** identidad documental. `DOCUMENT_ENCOUNTER_LINK_REQUIRED=ONLY_WHEN_DOCUMENT_CLINICAL_CONTEXT_IS_ENCOUNTER`: un documento de paciente genuino no exige `encounter_id`; no se retroenlazan documentos legacy por conjetura. La nota automática final, si se retiene, está vinculada a un solo E. Un E puede pertenecer opcionalmente a un «Caso clínico»; añadirlo a C no altera dueño, valores históricos ni crea copia. D4 ratifica que la cita opcional vinculada al inicio queda como contexto histórico fijo de E, aunque A después se reprograme, cancele, cambie hora o estado. `D4_APPOINTMENT_LINK=HISTORICALLY_FIXED_AFTER_ENCOUNTER_START`; `APPOINTMENT_LINK_CORRECTION=AUDITED_EXPLICIT_CORRECTION_ONLY`. Corregir una cita vinculada erróneamente exige conservar referencia anterior/nueva, actor, fecha/hora y razón, sin reemplazo silencioso.

**Borradores y legacy.** `clinical_record_entries` mezcla datos longitudinales y de consulta en borradores mutables por paciente. No se convierten automáticamente en encounters históricos. Opciones futuras por registro y evidencia: `LEGACY_SNAPSHOT`, `MIGRATE_WITH_PROVENANCE_IF_POSSIBLE`, `RETAIN_READ_ONLY`, `UNRESOLVED`. No se elige migración aquí. Los encuentros con `doctor_id=NULL` siguen `UNATTRIBUTED`; no se atribuyen por cita, usuario, vínculo de paciente o autor documental. La migración histórica inspeccionada sí incluye un backfill desde cita: es una brecha contra esta regla aceptada que debe adjudicarse antes de reusarla, no una autorización para aplicarla. `CLINICAL_GET_SCHEMA_SIDE_EFFECT_RISK=OPEN`: ninguna validación física de PHASE 2 empieza hasta disponer de lecturas sin mutación accidental de esquema o de un entorno desechable aislado donde tal efecto esté explícitamente autorizado.

## Matriz de brechas frente a las decisiones ratificadas

`CURRENT_PROOF` nombra evidencia **de fuente** salvo cuando se señala AUDIT02. `FUTURE_ACTION_CLASS` orienta un capítulo posterior, sin autorización de escritura. D1–D5 ya están ratificadas; `HARDEN`/`IMPLEMENT`/`VALIDATE` señalan trabajo físico posterior, no decisiones pendientes.

| INVARIANT | CURRENT_IMPLEMENTATION | CURRENT_PROOF | GAP | FUTURE_ACTION_CLASS |
| --- | --- | --- | --- | --- |
| Doctor ownership | `doctor_id` en fila; rutas de detalle/cierre verifican médico de sesión. | `index.php:3485,6466,6781` | Legacy NULL; migración histórica infiere desde cita; no se probó acceso físico cruzado. | HARDEN |
| Patient ownership | `patient_id` y vínculo activo requerido. | `index.php:359,416,5885` | Validar todas las rutas/mutaciones futuras. | VALIDATE |
| Appointment match / historical link | Cita opcional cotejada con doctor/paciente al crear. | `index.php:392,5904` | D4 exige fijar relación histórica y corrección auditada; protección física no probada. | HARDEN |
| Active lookup | Paciente+médico+usuario de apertura+OPEN. | `index.php:3559-3579,5724` | D1 exige resolver por médico+paciente+OPEN para todo operador autorizado. | HARDEN |
| Active uniqueness | No se observó constraint único para abierta por doctor/paciente. | `index.php:3294-3318` | Puede coexistir más de una OPEN según actor/carrera. | HARDEN |
| Start idempotency | Reutiliza OPEN encontrada dentro del scope actual. | `index.php:5933-5959` | SELECT/INSERT no atómico bajo concurrencia. | HARDEN |
| Concurrent start | Sin bloqueo/constraint visible en creación. | `index.php:5933-5965` | Carrera no probada; no declarar seguro. | HARDEN |
| Resume | GET active desde servidor, pero filtrado por opener. | `index.php:5707-5764` | Recuperación por otro operador y drafts por encounter incompletos. | HARDEN |
| Save/draft | Historia/Exploración usan borrador por paciente. | `index.php:3851-3935,4475-4630`; AUDIT01 | No conserva datos por consulta. | IMPLEMENT |
| State model / VOIDED | `status` es texto libre; no se confirmó VOIDED. | `index.php:3294-3350,5899-5931` | D2 exige OPEN→VOIDED explícito, auditable e idempotente, sin contar como atención válida. | IMPLEMENT |
| Client-controlled start status | POST acepta `status` del cliente, default `completed`; ensure normaliza `completed` a `open`. | `index.php:5899,5917-5931,3294-3350` | `CLIENT_CONTROLLED_ENCOUNTER_STATUS_RISK=OPEN`; start futuro sólo crea OPEN. | HARDEN |
| Finalize | Ruta formal y transacción con nota final. | `index.php:1714-1790,6444-6486` | Garantía física y estado de entrada por validar. | VALIDATE |
| Finalize idempotency | Return temprano de CLOSED con UUID. | `index.php:1722-1750` | Sin prueba para UUID ausente o cierres concurrentes; UPDATE no condiciona OPEN. | HARDEN |
| Closed immutability | El detalle lee CLOSED; documento por encuentro no exige OPEN. | `index.php:6596-6715,6755-6868` | Escritura postcierre y edición normal requieren restricción. | HARDEN |
| Historical correction / amendment | Sin flujo de enmienda confirmado. | Rutas inspeccionadas `index.php:6444-6868` | D3 exige original intacto; D5 exige enmienda de encounter/documento según autoridad afectada. | IMPLEMENT |
| Historical read | Detalle filtra doctor y documentos por encounter, con fallback de cita limitado. | `index.php:6755-6868` | Borradores por paciente no son snapshot histórico; fallback no prueba contexto. | HARDEN |
| Document linkage | Ruta encounter-documents guarda vínculo; catálogo general admite vínculo opcional. | `index.php:6493-6748`; AUDIT02 | 402/413 documentos físicos sin vínculo directo; contexto variable. | VALIDATE |
| Legacy encounters | AUDIT02: 13/13 sin doctor. | [AUDIT02](EXPEDIENTE_CURRENT_STATE_AUDIT_PHASE0.md) | Atribución/visibilidad ambiguas; no inferir. | MIGRATION_DECISION |
| Legacy drafts | `clinical_record_entries` por paciente/tipo. | AUDIT01; `index.php:3851-3935` | No convertir a encuentros históricos sin procedencia. | MIGRATION_DECISION |
| GET side-effect safety | `ensure_schema` realiza DDL/normalización incluso en rutas GET. | `index.php:3294-3350,5713,6766` | Lectura puede mutar esquema/estado; puerta de seguridad abierta. | HARDEN |

## Plan de validación controlada — futuro, no ejecutado

```text
ISOLATED_NONPRODUCTION=true
DEDICATED_QA_DOCTOR=true
DEDICATED_QA_PATIENT=true
DEDICATED_QA_APPOINTMENT_WHEN_NEEDED=true
REAL_PATIENT_DATA=false
BASELINE_DB_GUARD=true
POSTTEST_DB_GUARD=true
CONTROLLED_CLEANUP=true
VALIDATION_SCENARIOS_COUNT=24
WRITE_VALIDATION_EXECUTED=false
WRITE_VALIDATION_AUTHORIZED=false
```

Una fase **posterior y separadamente autorizada** preparará entorno no productivo aislado y datos sintéticos D/P, operador autorizado adicional, médico B, paciente Q y cita QA cuando corresponda. Antes de cada prueba: identificar base/instancia, impedir conexión a producción, comprobar identidad y permisos de QA, guardar baseline de esquema/filas/contadores y resolver el riesgo GET/DDL o aceptar explícitamente sus efectos sólo en un entorno desechable. Registrar request/response saneados, claves de encounter/documento, timestamps, estado y huellas/snapshots de filas; después comparar sólo los registros QA, demostrar ausencia de cambios fuera de scope y limpiar de forma controlada con verificación final. No se usarán datos de Leticia ni de pacientes reales.

| Escenario futuro | Acción sintética | Evidencia/resultado exigido |
| --- | --- | --- |
| T01 START | D inicia Consulta 1 de P explícitamente. | Una OPEN con doctor_id D y patient_id P. |
| T02 START RETRY | Repetir petición equivalente. | Misma clave; ninguna fila duplicada. |
| T03 DOUBLE CLICK / RETRY | Dos inicios rápidos del mismo actor. | Una OPEN. |
| T04 CONCURRENT START | Dos operadores autorizados inician simultáneamente para D/P. | Una sola OPEN para D/P; enforcement físico, no sólo UI. |
| T05 WRONG APPOINTMENT OWNER | Usar cita de otro médico/paciente. | Denegado, sin consulta nueva. |
| T06 NO APPOINTMENT | Iniciar atención válida sin cita. | Admitida según política; appointment_id vacío. |
| T07 SAVE / RESUME | Guardar datos actuales, recargar y reanudar. | Misma clave, contenido y atribución; servidor autoritativo. |
| T08 CHANGE PATIENT | Pasar de P a Q. | P sigue OPEN; Q no se inicia solo. |
| T09 CLOSE EXPEDIENTE | Cerrar vista/contexto de P. | Encounter sigue OPEN. |
| T10 FINALIZE | Finalizar Consulta 1. | Transición explícita OPEN→CLOSED, closed_at/actor; una nota final si aplica. |
| T11 FINALIZE RETRY | Repetir y concurrir cierres. | Mismo CLOSED; primeros campos de cierre estables y una sola nota final. |
| T12 START ENCOUNTER 2 | Iniciar consulta posterior para D/P. | Nuevo encounter_id distinto de Consulta 1. |
| T13 CURRENT FIELDS | Abrir Consulta 2 tras Consulta 1 con peso/PA/motivo. | Campos actuales vacíos; previos sólo referenciados con fecha. |
| T14 SAVE ENCOUNTER 2 | Guardar mediciones y narrativa diferentes. | Sólo Consulta 2 adquiere nuevos valores. |
| T15 HISTORICAL INTEGRITY | Releer Consulta 1. | Hash/snapshot de contenido original igual al baseline. |
| T16 DOCUMENT CONTEXT | Crear documento en Consulta 2. | Una identidad documental enlazada a P/Consulta 2, nunca a Consulta 1. |
| T17 HISTORICAL EDIT ATTEMPT | Edición normal de Consulta 1 CLOSED. | Denegada; sólo enmienda explícita. |
| T18 AMENDMENT | Corregir contenido de encuentro, documento o ambos según D5. | Mecanismo híbrido según autoridad afectada; original intacto, vínculo, autor/fecha/razón. |
| T19 FOREIGN DOCTOR | B intenta leer/mutar encounter de D sin autorización. | Denegado según contrato de permisos; cero cambios. |
| T20 LEGACY NULL DOCTOR | Consultar legacy sin atribución en fixture QA controlado. | Denegación segura o presentación explícita `UNATTRIBUTED` según contrato futuro; nunca atribución inferida ni escritura. |
| T21 VOID ENCOUNTER | Crear OPEN sintética y anularla explícitamente. | `VOIDED / ANULADA`; razón, actor y hora conservados; no cuenta como atención completada/válida. |
| T22 VOID RETRY | Repetir anulación. | Mismo VOIDED, sin resurrección ni evento de auditoría duplicado; primera razón estable. |
| T23 MULTI-OPERATOR RESUME | A inicia para D/P y B autorizado reanuda. | Mismo `encounter_id`; B no crea otra OPEN por tener otro user_id. |
| T24 APPOINTMENT HISTORICAL LINK | Iniciar E con cita A; modificar/reprogramar A en QA aislado. | E conserva referencia histórica a A; cambio de vínculo sólo por corrección explícita auditada. |

**Pruebas de integridad de base.** Snapshot/hash de Consulta 1 antes/después de T12–T18, excluyendo sólo metadatos expresamente autorizados; igualdad de identidad, médico, paciente, fecha efectiva, contenido y documentos originales. Consulta 2 tiene ID distinto y datos propios. Consultar agregados OPEN por doctor/paciente conforme a D1; verificar nota final máxima de una y estabilidad de `closed_at`/`closed_by_user_id` tras retries; comprobar claves de contexto documental y enmienda sin reescritura original. Para T21–T24, comprobar rastro de anulación, conteos clínicos que excluyen VOIDED, una sola OPEN por D/P bajo dos actores y vínculo de cita estable. Si una prueba falla, conservar evidencia, detener escrituras posteriores y activar limpieza/rollback de QA autorizado; no “reparar” datos reales.

**Fallos y reintentos.** Timeout de inicio: consultar por clave/idempotencia o scope aprobado antes de reintentar; jamás INSERT ciego. Timeout de guardado: releer versión/estado servidor y reconciliar, sin crear encounter. Timeout de finalización: releer estado/cierre/UUID de nota antes de retry; preservar el primer cierre. Recarga o cierre de navegador: resolver desde servidor, sin efecto clínico implícito. Doble clic: mismo resultado que un intento. Error de servidor: respuesta explícita sin declarar éxito; reintento seguro sólo tras determinar si la primera operación se confirmó. Las respuestas y el mecanismo físico se definirán después del contrato; no se fijan payloads aquí.

## DIRECTOR_DECISIONS_RATIFIED — D1–D5

```text
D1_OPEN_UNIQUENESS=ONE_OPEN_ENCOUNTER_PER_DOCTOR_PATIENT
D2_VOIDED_STATE=APPROVED
VOIDED_VISIBLE_TERM=ANULADA
D3_CLOSED_CORRECTION=EXPLICIT_AMENDMENT_ONLY
CLOSED_REOPEN_FOR_SILENT_EDIT=false
D4_APPOINTMENT_LINK=HISTORICALLY_FIXED_AFTER_ENCOUNTER_START
APPOINTMENT_LINK_CORRECTION=AUDITED_EXPLICIT_CORRECTION_ONLY
D5_AMENDMENT_REPRESENTATION=HYBRID_BY_AFFECTED_AUTHORITY
DIRECTOR_DECISIONS_RATIFIED=5/5
DIRECTOR_DECISIONS_REQUIRED_COUNT=0
```

| ID | Decisión ratificada | Consecuencia vinculante de diseño futuro |
| --- | --- | --- |
| D1 | Una sola OPEN por médico/paciente. | `opened_by_user_id` no limita unicidad ni reanudación; varios operadores autorizados comparten la misma consulta. La concurrencia debe probarse físicamente. |
| D2 | `VOIDED / ANULADA` para apertura errónea. | Anulación explícita, idempotente y auditada; conserva registro y no cuenta como atención completada ni consulta válida. |
| D3 | CLOSED se corrige sólo por enmienda explícita. | Nunca reabrir para edición silenciosa, editar normalmente ni borrar como corrección ordinaria. |
| D4 | Cita ligada al inicio queda fija históricamente. | Cambios administrativos posteriores no reescriben la referencia; error de vínculo se corrige con auditoría explícita. |
| D5 | Enmienda híbrida por autoridad afectada. | Encounter y documento conservan historias propias; ambos se corrigen si ambos cambian, sin duplicar la autoridad. |

`DIRECTOR_DECISIONS_REQUIRED_COUNT=0`. La fuente actual sigue aceptando un `status` enviado por el cliente y su default histórico `completed`, con normalización posterior a `open`: `CLIENT_CONTROLLED_ENCOUNTER_STATUS_RISK=OPEN`. El contrato ratificado exige start→OPEN solamente; no se corrige la fuente aquí. No se elige tabla, columna, índice, SQL de migración, payload, permiso nuevo, UI final ni esquema de enmienda. La **aceptación final del contrato completo** aún corresponde a una revisión separada; después podría autorizarse otro capítulo de diseño físico/validación. PHASE 3 conserva workspace y navegación finales; especialidades, tendencias completas e integración visual de billing siguen fuera de CONTRACT01A.
