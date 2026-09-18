# CLIN-REFORM-PHASE2-CONTRACT01 — Integridad de la consulta clínica

```text
STATUS=READY_FOR_DIRECTOR_REVIEW
PHASE_2_STATUS=IN_PROGRESS
ACCEPTED_BASELINE=8698b1f66466867360651db5fa62e54d28fba797
ENCOUNTER_AUTHORITY=clinical_encounters
ENCOUNTER_OWNER=DOCTOR_ID
START_ENCOUNTER_IDEMPOTENCY=REQUIRED
FINALIZE_IDEMPOTENCY=REQUIRED
AUTO_FINAL_NOTE_MAX_ONE_PER_ENCOUNTER=true
CROSS_ENCOUNTER_OVERWRITE_ALLOWED=false
ACTIVE_ENCOUNTER_RACE_RISK=OPEN_UNTIL_PHYSICAL_ENFORCEMENT_PROVEN
CLINICAL_GET_SCHEMA_SIDE_EFFECT_RISK=OPEN
WRITE_VALIDATION_EXECUTED=false
DIRECTOR_DECISIONS_REQUIRED_COUNT=5
```

Este capítulo **propone** el contrato detallado de PHASE 2 y el plan de prueba controlada. Será autoridad detallada sólo tras revisión del Director/asistente. El [plan vivo](PLAN_REFORMA_EXPEDIENTE_CLINICO.md) gobierna la fase, el [modelo aceptado de PHASE 1](EXPEDIENTE_CLINICAL_INFORMATION_MODEL_UX_CONTRACT.md) fija la titularidad conceptual y la [auditoría de PHASE 0](EXPEDIENTE_CURRENT_STATE_AUDIT_PHASE0.md) distingue evidencia de fuente, datos físicos y pruebas no ejecutadas. El [Plan Maestro](../PLAN_MAESTRO_MXMED.md) conserva autoridad global. **Ninguna propuesta de este documento es prueba de funcionamiento físico ni autoriza esquema, API, migración, UI o validación con escrituras.**

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

## Titularidad y estados propuestos

`CLINICAL_OWNER=clinical_encounters.doctor_id`; `PATIENT_ID` identifica al sujeto de atención. `ACTOR` es el usuario autenticado que inicia o modifica una operación, incluido `opened_by_user_id` y quien cierra. `OPERATOR` es un actor autorizado a operar en el flujo del médico según capacidades existentes; esa capacidad **no** lo convierte en dueño clínico. Ninguna cita, vínculo de paciente, usuario que abrió ni autor de documento sustituye `doctor_id`. Una consulta nueva debe tener dueño médico explícito. `doctor_id=NULL` legacy permanece `UNATTRIBUTED` hasta conciliación futura basada en evidencia; `NO_INFERENCE_OF_LEGACY_DOCTOR_OWNERSHIP`.

Modelo mínimo candidato: `OPEN → CLOSED` por finalización explícita. Se propone evaluar `OPEN → VOIDED` para una apertura errónea sin atención válida; `VOIDED` preservaría razón, actor, tiempo e historia y **no** equivale a cita cancelada. CLOSED no vuelve silenciosamente a OPEN. La decisión sobre incluir VOIDED sigue pendiente (D2); ni el estado ni sus transiciones físicas existen por aprobar aquí.

**Inicio.** `OPEN_PATIENT_RECORD != START_ENCOUNTER`; `TAB_NAVIGATION != START_ENCOUNTER`; `SEARCH_PATIENT != START_ENCOUNTER`; `VIEW_HISTORICAL_ENCOUNTER != START_ENCOUNTER`. Iniciar exige acción explícita, sesión autenticada, médico actual, paciente existente, vínculo médico-paciente activo y, si hay `appointment_id`, coincidencia de médico y paciente. La cita es contexto administrativo opcional: `APPOINTMENT != ENCOUNTER`; una atención sin cita puede ser válida. La consulta tiene identidad propia aunque la cita luego cambie. Fijar o corregir el vínculo histórico con la cita requiere D4.

**Ámbito de consulta abierta y concurrencia.** Candidato D1: `PROPOSED_OPEN_ENCOUNTER_SCOPE=ONE_OPEN_ENCOUNTER_PER_DOCTOR_PATIENT`. Bajo esta regla, dos operadores autorizados del mismo médico resolverían la **misma** consulta abierta; `opened_by_user_id` sería procedencia, no clave de unicidad. El médico puede mantener consultas OPEN de pacientes distintos. Mientras D1 no se ratifique, esta es propuesta, no regla operativa. Aun ratificada, un `SELECT` seguido de `INSERT` no basta: `CONCURRENT_START_REQUESTS_MUST_NOT_CREATE_DUPLICATE_OPEN_ENCOUNTERS`. El capítulo de implementación debe elegir y probar bloqueo transaccional, unicidad física aplicable u otro invariante impuesto por la base. `ACTIVE_ENCOUNTER_RACE_RISK=OPEN_UNTIL_PHYSICAL_ENFORCEMENT_PROVEN`.

**Tiempo.** `ENCOUNTER_EFFECTIVE_DATETIME` representa cuándo ocurrió/comenzó la atención; `CREATED_AT`, cuándo se creó el registro; `CLOSED_AT`, cuándo se finalizó formalmente. Pueden diferir. La fuente actual tiene `encounter_dt`, `created_at`, `closed_at`; la correspondencia exacta y correcciones requieren contrato físico posterior, sin nuevas columnas definidas aquí.

**Guardado y reanudación.** Guardar trabajo progresivo conserva `encounter_id`, paciente y médico; no crea otra consulta ni modifica una CLOSED. Componentes clínicos de la consulta pueden estar en borrador mientras el encounter sigue OPEN. El estado guardado debe ser recuperable de una autoridad de servidor tras recarga o retorno, no sólo de DOM, modal, tab, memoria o `localStorage`. Un timeout de guardado debe resolverse leyendo el estado persistido antes de reintentar; ninguna repetición puede crear consulta adicional. El borrador actual por paciente de Historia/Exploración **no** satisface por sí solo esta condición.

**Cambio/cierre de vista.** Cambiar de paciente no finaliza ni borra el encounter de A ni crea el de B. Una abierta de A queda recuperable bajo la política aceptada. `CLOSE_PATIENT_VIEW != FINALIZE_ENCOUNTER`: cerrar expediente o navegador limpia contexto de UI, no cierra atención. No se diseñan prompts en CONTRACT01.

**Finalización.** Una acción clínica explícita lleva `OPEN → CLOSED` y conserva identidad, médico, paciente, tiempo efectivo, `closed_at`, actor de cierre, contenido/documentos asociados y nota final si aplica. `FINALIZE_IDEMPOTENCY=REQUIRED`: reintento devuelve el cierre existente sin cambiar `closed_at`/actor ni crear segunda nota. `AUTO_FINAL_NOTE_MAX_ONE_PER_ENCOUNTER=true`; la nota pertenece a una sola consulta, no reemplaza sus datos subyacentes y permanece enlazada históricamente. Si falla la transacción, el resultado debe ser recuperable y el reintento seguro. Cobro/facturación permanecen separados.

**Historia y enmienda.** Una CLOSED es histórica, de lectura enfocada: fecha, médico atribuible, motivo, mediciones, exploración, valoración, plan y documentos **de esa consulta**. El estado longitudinal actual no reescribe el pasado. `CLOSED_ENCOUNTER_NOT_REOPENED_FOR_SILENT_EDIT=true` es el candidato operativo D3 compatible con PHASE 1. Una corrección explícita conserva original, autor, fecha, motivo, contenido adicional/corregido y vínculo al encounter/documento. D5 decidirá si la enmienda es registro de consulta, documento o híbrida; no se diseña esquema. El contenido firmado original sigue recuperable.

**Consulta 1 → Consulta 2.** Para P/D, Consulta 1 guarda peso 78 kg, PA 120/80 y motivo X y se finaliza. Consulta 2 obtiene `encounter_id` distinto; sus campos actuales de peso, PA y motivo nacen vacíos. Una vista de referencia puede mostrar los valores de Consulta 1 con fecha/origen, sin autocopiarlos como captura de hoy. Tras guardar valores diferentes en Consulta 2, Consulta 1 devuelve los originales intactos. `CROSS_ENCOUNTER_OVERWRITE_ALLOWED=false`; `PREVIOUS_VALUE_MUST_NOT_AUTOFILL_AS_CURRENT_MEASUREMENT`; información ausente o exploración no registrada **no** significa normal. Una medición futura exige lógicamente paciente, médico, consulta, tipo, valor, unidad, fecha efectiva, fecha de registro y procedencia/fuente.

**Documentos, casos y cita.** Contextos aceptados: `PATIENT_DOCUMENT`, `ENCOUNTER_DOCUMENT`, `EPISODE_DOCUMENT`. Un documento generado en E (receta, nota, orden, resultado vinculado, interconsulta o certificado pertinente) conserva E como contexto primario; puede verse en detalle, historial documental y revisión longitudinal mediante **una** identidad documental. `DOCUMENT_ENCOUNTER_LINK_REQUIRED=ONLY_WHEN_DOCUMENT_CLINICAL_CONTEXT_IS_ENCOUNTER`: un documento de paciente genuino no exige `encounter_id`; no se retroenlazan documentos legacy por conjetura. La nota automática final, si se retiene, está vinculada a un solo E. Un E puede pertenecer opcionalmente a un «Caso clínico»; añadirlo a C no altera dueño, valores históricos ni crea copia. La cita sigue administrativamente separada y opcional; su relación histórica pendiente es D4.

**Borradores y legacy.** `clinical_record_entries` mezcla datos longitudinales y de consulta en borradores mutables por paciente. No se convierten automáticamente en encounters históricos. Opciones futuras por registro y evidencia: `LEGACY_SNAPSHOT`, `MIGRATE_WITH_PROVENANCE_IF_POSSIBLE`, `RETAIN_READ_ONLY`, `UNRESOLVED`. No se elige migración aquí. Los encuentros con `doctor_id=NULL` siguen `UNATTRIBUTED`; no se atribuyen por cita, usuario, vínculo de paciente o autor documental. La migración histórica inspeccionada sí incluye un backfill desde cita: es una brecha contra esta regla aceptada que debe adjudicarse antes de reusarla, no una autorización para aplicarla. `CLINICAL_GET_SCHEMA_SIDE_EFFECT_RISK=OPEN`: ninguna validación física de PHASE 2 empieza hasta disponer de lecturas sin mutación accidental de esquema o de un entorno desechable aislado donde tal efecto esté explícitamente autorizado.

## Matriz de brechas frente al contrato propuesto

`CURRENT_PROOF` nombra evidencia **de fuente** salvo cuando se señala AUDIT02. `FUTURE_ACTION_CLASS` orienta un capítulo posterior, sin autorización de escritura. `DIRECTOR_DECISION` exige resolución antes de fijar la regla correspondiente.

| INVARIANT | CURRENT_IMPLEMENTATION | CURRENT_PROOF | GAP | FUTURE_ACTION_CLASS |
| --- | --- | --- | --- | --- |
| Doctor ownership | `doctor_id` en fila; rutas de detalle/cierre verifican médico de sesión. | `index.php:3485,6466,6781` | Legacy NULL; migración histórica infiere desde cita; no se probó acceso físico cruzado. | HARDEN |
| Patient ownership | `patient_id` y vínculo activo requerido. | `index.php:359,416,5885` | Validar todas las rutas/mutaciones futuras. | VALIDATE |
| Appointment match | Cita opcional cotejada con doctor/paciente al crear. | `index.php:392,5904` | Política de vínculo/corrección postinicio sin definir. | DIRECTOR_DECISION |
| Active lookup | Paciente+médico+usuario de apertura+OPEN. | `index.php:3559-3579,5724` | Otro operador no resuelve la misma abierta. | DIRECTOR_DECISION |
| Active uniqueness | No se observó constraint único para abierta por doctor/paciente. | `index.php:3294-3318` | Puede coexistir más de una OPEN según actor/carrera. | HARDEN |
| Start idempotency | Reutiliza OPEN encontrada dentro del scope actual. | `index.php:5933-5959` | SELECT/INSERT no atómico bajo concurrencia. | HARDEN |
| Concurrent start | Sin bloqueo/constraint visible en creación. | `index.php:5933-5965` | Carrera no probada; no declarar seguro. | HARDEN |
| Resume | GET active desde servidor, pero filtrado por opener. | `index.php:5707-5764` | Recuperación por otro operador y drafts por encounter incompletos. | HARDEN |
| Save/draft | Historia/Exploración usan borrador por paciente. | `index.php:3851-3935,4475-4630`; AUDIT01 | No conserva datos por consulta. | IMPLEMENT |
| Finalize | Ruta formal y transacción con nota final. | `index.php:1714-1790,6444-6486` | Garantía física y estado de entrada por validar. | VALIDATE |
| Finalize idempotency | Return temprano de CLOSED con UUID. | `index.php:1722-1750` | Sin prueba para UUID ausente o cierres concurrentes; UPDATE no condiciona OPEN. | HARDEN |
| Closed immutability | El detalle lee CLOSED; documento por encuentro no exige OPEN. | `index.php:6596-6715,6755-6868` | Escritura postcierre y edición normal requieren restricción. | HARDEN |
| Amendment | Sin flujo de enmienda confirmado. | Rutas inspeccionadas `index.php:6444-6868` | Original/autor/fecha/razón no modelados. | DIRECTOR_DECISION |
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
VALIDATION_SCENARIOS_COUNT=20
WRITE_VALIDATION_EXECUTED=false
```

Una fase **posterior y separadamente autorizada** preparará entorno no productivo aislado y datos sintéticos D/P, operador autorizado adicional, médico B, paciente Q y cita QA cuando corresponda. Antes de cada prueba: identificar base/instancia, impedir conexión a producción, comprobar identidad y permisos de QA, guardar baseline de esquema/filas/contadores y resolver el riesgo GET/DDL o aceptar explícitamente sus efectos sólo en un entorno desechable. Registrar request/response saneados, claves de encounter/documento, timestamps, estado y huellas/snapshots de filas; después comparar sólo los registros QA, demostrar ausencia de cambios fuera de scope y limpiar de forma controlada con verificación final. No se usarán datos de Leticia ni de pacientes reales.

| Escenario futuro | Acción sintética | Evidencia/resultado exigido |
| --- | --- | --- |
| T01 START | D inicia Consulta 1 de P explícitamente. | Una OPEN con doctor_id D y patient_id P. |
| T02 START RETRY | Repetir petición equivalente. | Misma clave; ninguna fila duplicada. |
| T03 DOUBLE CLICK / RETRY | Dos inicios rápidos del mismo actor. | Una OPEN. |
| T04 CONCURRENT START | Dos operadores autorizados inician simultáneamente para D/P. | Una OPEN bajo scope aprobado; demuestra enforcement físico, no sólo UI. |
| T05 WRONG APPOINTMENT OWNER | Usar cita de otro médico/paciente. | Denegado, sin consulta nueva. |
| T06 NO APPOINTMENT | Iniciar atención válida sin cita. | Admitida según política; appointment_id vacío. |
| T07 SAVE / RESUME | Guardar datos actuales, recargar y reanudar. | Misma clave, contenido y atribución; servidor autoritativo. |
| T08 CHANGE PATIENT | Pasar de P a Q. | P sigue OPEN; Q no se inicia solo. |
| T09 CLOSE EXPEDIENTE | Cerrar vista/contexto de P. | Encounter sigue OPEN. |
| T10 FINALIZE | Finalizar Consulta 1. | CLOSED, closed_at/actor; una nota final si aplica. |
| T11 FINALIZE RETRY | Repetir y concurrir cierres. | Campos de cierre estables; cero notas finales duplicadas. |
| T12 START ENCOUNTER 2 | Iniciar consulta posterior para D/P. | Nuevo encounter_id distinto de Consulta 1. |
| T13 CURRENT FIELDS | Abrir Consulta 2 tras Consulta 1 con peso/PA/motivo. | Campos actuales vacíos; previos sólo referenciados con fecha. |
| T14 SAVE ENCOUNTER 2 | Guardar mediciones y narrativa diferentes. | Sólo Consulta 2 adquiere nuevos valores. |
| T15 HISTORICAL INTEGRITY | Releer Consulta 1. | Hash/snapshot de contenido original igual al baseline. |
| T16 DOCUMENT CONTEXT | Crear documento en Consulta 2. | Una identidad documental enlazada a P/Consulta 2, nunca a Consulta 1. |
| T17 HISTORICAL EDIT ATTEMPT | Edición normal de Consulta 1 CLOSED. | Denegada; se exige enmienda. |
| T18 AMENDMENT | Crear corrección válida según D5. | Original intacto, vínculo, autor/fecha/razón y contenido nuevo. |
| T19 FOREIGN DOCTOR | B intenta leer/mutar encounter de D sin autorización. | Denegado según contrato de permisos; cero cambios. |
| T20 LEGACY NULL DOCTOR | Consultar legacy sin atribución en fixture QA controlado. | Denegación segura o presentación explícita `UNATTRIBUTED` según contrato futuro; nunca atribución inferida ni escritura. |

**Pruebas de integridad de base.** Snapshot/hash de Consulta 1 antes/después de T12–T18, excluyendo sólo metadatos expresamente autorizados; igualdad de identidad, médico, paciente, fecha efectiva, contenido y documentos originales. Consulta 2 tiene ID distinto y datos propios. Consultar agregados OPEN por doctor/paciente conforme a D1 una vez ratificada; verificar nota final máxima de una y estabilidad de `closed_at`/`closed_by_user_id` tras retries; comprobar claves de contexto documental y enmienda sin reescritura original. Si una prueba falla, conservar evidencia, detener escrituras posteriores y activar limpieza/rollback de QA autorizado; no “reparar” datos reales.

**Fallos y reintentos.** Timeout de inicio: consultar por clave/idempotencia o scope aprobado antes de reintentar; jamás INSERT ciego. Timeout de guardado: releer versión/estado servidor y reconciliar, sin crear encounter. Timeout de finalización: releer estado/cierre/UUID de nota antes de retry; preservar el primer cierre. Recarga o cierre de navegador: resolver desde servidor, sin efecto clínico implícito. Doble clic: mismo resultado que un intento. Error de servidor: respuesta explícita sin declarar éxito; reintento seguro sólo tras determinar si la primera operación se confirmó. Las respuestas y el mecanismo físico se definirán después del contrato; no se fijan payloads aquí.

## DIRECTOR_DECISIONS_REQUIRED — cinco decisiones materiales

| ID | Decisión pendiente | Candidato y razón |
| --- | --- | --- |
| D1 | Alcance único de OPEN | Aprobar `ONE_OPEN_ENCOUNTER_PER_DOCTOR_PATIENT`, en lugar del scope actual con opener. Evita dos abiertas para D/P por operadores distintos; permite abiertas de pacientes diferentes. Debe acompañarse de prueba de concurrencia y permisos de operador. |
| D2 | Anulación | Decidir si incluir `VOIDED / ANULADA` para apertura errónea sin atención válida, con razón, actor, fecha y rastro; nunca equivalente a cancelar cita. |
| D3 | Corrección de CLOSED | Confirmar política operativa: no reabrir para edición silenciosa; toda corrección por enmienda explícita con original recuperable. El principio de no sobrescritura histórica ya está aceptado en PHASE 1. |
| D4 | Vínculo de cita tras inicio | Decidir si el vínculo histórico de cita queda fijo al iniciar y sólo cambia mediante corrección auditada. Una cita reprogramada/cancelada no cambia identidad de consulta. |
| D5 | Representación de enmienda | Elegir enmienda de encounter, documental o híbrida según contenido, manteniendo una historia legible y sin duplicar autoridad. |

`DIRECTOR_DECISIONS_REQUIRED_COUNT=5`. No se elige aquí tabla, columna, índice, SQL de migración, payload, permiso nuevo, UI final ni esquema de enmienda. Tras aceptación del contrato y decisiones, un capítulo posterior podrá diseñar implementación/QA física y solicitar autorización separada. PHASE 3 conserva el workspace ambulatorio y navegación finales; especialidades, tendencias longitudinales completas e integración visual de billing siguen fuera de CONTRACT01.
