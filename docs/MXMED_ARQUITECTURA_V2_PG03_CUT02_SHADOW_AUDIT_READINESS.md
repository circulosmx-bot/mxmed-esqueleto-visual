# MXMed V2 · PG-03 · CUT-02 Shadow and Audit-Only Scope, Decisions and Readiness

## 1. Título e identidad

Programa MXMed Product Refinement V2, Actividad 15/22, `CUT-02 — Shadow and Audit-Only Scope, Decisions and Readiness`, identificador `ARCH/MXMed-PG03-CUT02-Shadow-Audit-Only-Scope-Decisions-Readiness-01`, clasificación `UI-0`.

## 2. Estado y límite de autorización

Esta actividad produce arquitectura, readiness y decisiones propuestas. `CUT02_IMPLEMENTATION_AUTHORIZED=false`, `R1_ACTIVATION_AUTHORIZED=false` y `R2_ACTIVATION_AUTHORIZED=false`. El estado efectivo continúa `ROLLOUT_STAGE=R0` y `ROLLOUT_MODE=disabled`.

## 3. Baseline exacto y checkpoint 14

Parent exacto `fa95d7af6c00adb7e2c5070b3279d036d9ff3fcc`; checkpoint anotado `checkpoint/mxmed-product-refinement-v2-activity14`, objeto `fffa278f7d183877ea1a9eb2b7e18bc9bf09d618`, desreferenciado al parent. Rama documental `architecture/mxmed-pg03-cut02-shadow-audit-readiness-v2`.

## 4. Fuentes utilizadas

Se usaron como fuentes primarias el plan de gates de cutover, las decisiones y arquitectura CUT-01, la implementación CUT01-D, la auditoría runtime PG-03, el Plan Maestro, la configuración de Agenda, los ports de flags, observabilidad, auditoría y persistencia, los adapters canónicos, el placeholder de persistencia, `AppointmentWriteRepository` y `ClinicalEncounterBridge`.

Hecho comprobado: existen once flags false, un registro cerrado con efectividad siempre deshabilitada en R0, un port de observabilidad rechazante, adapters puros y wiring lifecycle dormido. Propuesta: las superficies, políticas y procesos descritos aquí. Pendiente: toda selección, parámetro, autorización e implementación CUT-02.

## 5. Objetivo CUT-02

Definir una propuesta implementable para una futura evaluación R1 shadow y una futura etapa R2 audit_only, manteniendo legacy como única fuente de respuesta y escritura. El objetivo de esta actividad es permitir decisión directorial informada, no ejecutar CUT-02.

## 6. Definiciones R0, R1 y R2

- R0 `disabled`: legacy lee, responde y escribe; canonical no procesa tráfico.
- R1 `shadow`: una futura evaluación canonical paralela, pura y cancelable podría comparar resultados sanitizados; legacy seguiría respondiendo.
- R2 `audit_only`: una futura R1 estable podría añadir eventos sanitizados a un sink aprobado; canonical seguiría sin autoridad de respuesta o escritura.

R1 y R2 son definiciones propuestas, no estados activos.

## 7. Invariantes no negociables

Legacy responde siempre; canonical no responde ni escribe; no cambian status, headers o payload; no se abren conexiones nuevas; no se ejecuta SQL o DDL; no se invoca Clinical; no se envía OTP; no se introduce PII; cualquier efecto inesperado detiene shadow y retorna a R0.

## 8. Alcance documental exacto

Dos documentos nuevos y una modificación al final del Plan Maestro: `NEW_FILES=2`, `MODIFIED_FILES=1`, `VERSIONED_SCOPE=3`, `FILE_4_PRESENT=false`.

## 9. Exclusiones

Quedan fuera código, configuración, flags, PHP, JavaScript, CSS, HTML, SQL, migraciones, rutas, controllers, repositories, services, adapters, tests, Clinical, UI, CI, AWS, infraestructura, tráfico, sinks, dashboards, alertas, workers, colas y datos.

## 10. Registro cerrado de once flags

1. `canonical_actor_authority`
2. `canonical_schedule_read`
3. `canonical_availability_compare`
4. `canonical_public_agenda`
5. `canonical_appointment_lifecycle`
6. `canonical_patient_identity`
7. `patient_identity_persistence`
8. `legacy_write_disable`
9. `shadow_audit`
10. `read_compare`
11. `backfill`

Todos permanecen en booleano literal `false`; no se agregan aliases, overrides o defaults.

## 11. Matriz de elegibilidad por flag

| Flag | Clasificación CUT-02 | Motivo y condición |
|---|---|---|
| `canonical_actor_authority` | primera ola R1 candidata, no autorizada | evaluación paralela pura, autoridad sanitizada y legacy inalterado |
| `canonical_schedule_read` | primera ola R1 candidata, no autorizada | snapshot ya disponible, sin lectura DB adicional |
| `canonical_availability_compare` | primera ola R1 candidata, no autorizada | comparación cerrada sin responder |
| `canonical_appointment_lifecycle` | primera ola R1 candidata, no autorizada | plan puro, sin ejecutar mutaciones |
| `canonical_patient_identity` | primera ola R1 candidata, no autorizada | preview puro, sin persistencia, creación o merge |
| `canonical_public_agenda` | condicional y bloqueado | privacidad, canal/proveedor OTP y parámetros de Seguridad pendientes; nunca OTP real |
| `shadow_audit` | condicional y bloqueado | requiere sink, retención, health/readiness, owner/on-call y failure policy aprobados |
| `patient_identity_persistence` | fuera de primera implementación CUT-02 | cruza frontera de writes |
| `legacy_write_disable` | fuera de primera implementación CUT-02 | corresponde a R4 segmentado |
| `read_compare` | fuera de primera implementación CUT-02 | corresponde a R3 |
| `backfill` | fuera de primera implementación CUT-02 | corresponde a CUT-03 |

Ningún candidato se activa sin sink, dashboards, alertas, owners, sampling, budgets y failure policy aprobados.

## 12. Secuencia futura propuesta por olas

- Ola de autoridad: `canonical_actor_authority`, aislado, sólo sobre datos ya resueltos por legacy.
- Ola de lectura Agenda: `canonical_schedule_read` y después `canonical_availability_compare`, conservando la respuesta legacy.
- Ola de dominio puro: `canonical_appointment_lifecycle` y `canonical_patient_identity`, sólo como planes o previews sin operaciones.
- Ola condicional posterior: `shadow_audit` únicamente después de aprobación integral del sink; `canonical_public_agenda` únicamente después de cerrar privacidad y Seguridad.

Cada ola requiere autorización separada y retorno a R0 probado; la secuencia no autoriza ninguna activación.

## 13. Contrato de evaluación shadow

Una futura evaluación recibiría una copia inmutable o normalizada de datos ya disponibles para legacy, sin lecturas DB adicionales salvo autorización separada. No ejecutaría writes, no modificaría la respuesta, status, headers o payload, no lanzaría excepciones al flujo legacy por métricas secundarias, produciría sólo resultados sanitizados, sería cancelable por flag server-side y no haría sampling hasta aprobación explícita.

La auditoría de autoridad requerida que no estuviera disponible frenaría o rechazaría sólo la evaluación canonical conforme a la política aprobada. No se invocaría Clinical ni se crearían outbox, worker, queue, saga o DLQ.

## 14. Invariancia de respuesta legacy

La respuesta observada antes de evaluar shadow debe ser byte y semánticamente la misma que la entregada después: mismo status HTTP, mismos headers, mismo payload, mismo orden contractual y mismos efectos legacy. Canonical no puede ser fallback de respuesta ni alterar el manejo de excepciones.

## 15. Modelo de resultados comparables

La propuesta compara códigos cerrados, booleans, categorías, digests y conjuntos ordenados sanitizados. No compara ni conserva payloads libres. Cada resultado distingue `legacy_outcome_code`, `canonical_outcome_code`, `reason_code`, `surface` y referencias opacas; una diferencia no autoriza cambiar la respuesta.

## 16. Canal de auditoría sanitizado

El canal futuro usaría un schema versionado, allowlist cerrada, referencias opacas y validación previa a append. No existe sink seleccionado o configurado: `AUDIT_SINK_SELECTED=false`, `AUDIT_SINK_CONFIGURED=false`, `AUDIT_EVENTS_WRITTEN=0`.

## 17. Allowlist propuesta de metadata

`schema_version`, `event_name`, `operation`, `rollout_stage`, `rollout_mode`, `surface`, `outcome`, `reason_code`, `legacy_outcome_code`, `canonical_outcome_code`, `correlation_ref`, `request_ref`, `scope_ref`, `adapter_version` y `failure_policy`.

Ningún campo adicional sería admitido sin revisión y aprobación versionadas.

## 18. Denylist de PII y datos clínicos

Se prohíben patient ID, appointment ID, doctor ID u otros identificadores raw; nombre, teléfono, email, dirección, fecha de nacimiento, contacto, OTP, token, notas, diagnósticos, contenido clínico, payload completo, body, query, headers sensibles, cookies, credenciales y stack traces con datos. Una coincidencia activa `PII_OR_CLINICAL_DATA_DETECTED`.

## 19. Referencias opacas y correlation IDs

`correlation_ref`, `request_ref` y `scope_ref` deben ser opacos, no reversibles por simple inspección, estables sólo dentro del propósito aprobado y sin incorporar identificadores raw. Algoritmo, administración de claves y rotación continúan `UNRESOLVED_PENDING_PARAMETER_APPROVAL`.

## 20. Catálogo de métricas

Nombres conceptuales propuestos, no emitidos:

- `shadow_evaluation_total`
- `shadow_evaluation_failure_total`
- `authority_mismatch_total`
- `scope_mismatch_total`
- `schedule_diff_total`
- `availability_diff_total`
- `lifecycle_diff_total`
- `identity_outcome_diff_total`
- `audit_append_failure_total`
- `privacy_violation_total`
- `unexpected_side_effect_total`
- `legacy_response_mutation_total`
- `shadow_latency`

`METRICS_EMITTED=0`; p95/p99, cifras, dashboards y alertas no están definidos o implementados.

## 21. Dimensiones permitidas

Allowlist conceptual: `operation`, `rollout_stage`, `rollout_mode`, `surface`, `outcome`, `reason_code`, `adapter_version` y `failure_policy`. Las referencias opacas sirven para correlación de evidencia, no como labels de cardinalidad abierta.

## 22. Cardinalidad y privacidad

Las dimensiones deben ser cerradas, enumeradas, sin PII y con cardinalidad acotada por diseño. No se autorizan IDs, valores libres o referencias por usuario como labels. Límites concretos y tratamiento de cardinalidad permanecen `UNRESOLVED_PENDING_PARAMETER_APPROVAL`.

## 23. Failure policy por riesgo

- Métrica secundaria no disponible: fail-open para el flujo legacy, con alerta cuando exista infraestructura aprobada.
- Auditoría de autoridad o write requerida no disponible: fail-closed para la evaluación canonical correspondiente.
- Operación desconocida: fail-closed.
- Privacidad o side effect inesperado: hard stop.
- Legacy nunca es sustituido por canonical.
- Ningún write canonical está permitido en R1/R2.

La matriz final de eventos y niveles de riesgo permanece pendiente.

## 24. Opciones de sink y criterios de selección

Familias propuestas: sink centralizado administrado; sink dedicado de seguridad/audit; sink de métricas separado del audit trail; adapter interno sobre infraestructura existente sólo si su existencia y aptitud se demuestran.

Criterios: privacidad, residencia, jurisdicción, integridad, disponibilidad, health, readiness, idempotencia, acceso, cifrado, retención, búsqueda, exportación, costos, operación, on-call, hard stop y trazabilidad de fallos. No se elige proveedor; todo queda `UNRESOLVED_PENDING_PARAMETER_APPROVAL`.

## 25. Health y readiness separados

Health sólo demostraría que el componente responde. Readiness exigiría además configuración válida, credenciales, permisos, schema compatible, capacidad de append/consulta, policy y owners aprobados. Un health positivo nunca implicaría readiness ni autorización de R1/R2. Timeouts siguen pendientes.

## 26. Requisitos de dashboards

Los dashboards futuros deben separar legacy, shadow y audit_only; mostrar cobertura, fallos, mismatches, privacidad, side effects, latencia y salud del sink; permitir drill-down sólo mediante referencias opacas; distinguir ausencia de datos de cero eventos; y conservar versión de schema y policy. `DASHBOARD_IMPLEMENTED=false`.

## 27. Requisitos de alertas

Las alertas futuras deben cubrir sink unavailable, audit append failure, PII, scope leakage, mutación legacy, intento de write, intento DB/SQL/Clinical/OTP, operación desconocida y breach de budget aprobado. Rutas, plataforma, severidades, deduplicación y escalamiento siguen pendientes. `ALERTING_IMPLEMENTED=false`.

## 28. Hard stops

- `PII_OR_CLINICAL_DATA_DETECTED`
- `LEGACY_RESPONSE_CHANGED`
- `HTTP_STATUS_CHANGED`
- `HTTP_HEADERS_CHANGED`
- `PAYLOAD_CHANGED`
- `CANONICAL_WRITE_ATTEMPTED`
- `NEW_DB_CONNECTION_ATTEMPTED`
- `SQL_OR_DDL_ATTEMPTED`
- `REAL_OTP_ATTEMPTED`
- `CLINICAL_REQUEST_ATTEMPTED`
- `SCOPE_LEAKAGE_DETECTED`
- `AUTHORITY_AUDIT_UNAVAILABLE`
- `UNKNOWN_OPERATION`
- `UNEXPECTED_SIDE_EFFECT`
- `BUDGET_BREACH_AFTER_APPROVAL`

`BUDGET_BREACH_AFTER_APPROVAL` aún no tiene cifra. Todo hard stop retorna a R0. No se descarta evidencia sin política, no hay auto-delete o rollback SQL, y no se usa reset, rebase, amend o force-push.

## 29. Proceso de baseline

Secuencia futura obligatoria:

`R0 harness evidence → baseline collection plan → sanitized evidence review → technical director approval → versioned sampling approval → versioned observation window approval → versioned p95/p99 and error-budget approval → implementation authorization → separate R1 activation approval`.

El baseline debe proceder de evidencia sanitizada y no de cifras inventadas.

## 30. Proceso de aprobación de sampling

`sampling=0` mientras no esté aprobado. La propuesta futura debe documentar propósito, key, scope, exclusiones, sesgo, privacidad, costo, versión y safe return; después requiere revisión técnica y aprobación directorial versionada antes de cualquier tráfico.

## 31. Proceso de aprobación de p95/p99 y error budgets

Primero se revisa baseline sanitizado; luego se proponen percentiles, presupuestos y hard stops por superficie; Seguridad/SRE validan observabilidad y Dirección técnica aprueba una versión. Todos permanecen `UNRESOLVED_PENDING_BASELINE_AND_DIRECTOR_APPROVAL`; no hay porcentaje o threshold predeterminado.

## 32. Ventanas de observación

No existe ventana predeterminada. Una propuesta futura debe ligar la ventana a superficie, stage, sampling aprobado, volumen suficiente, disponibilidad del owner/on-call, estacionalidad conocida y criterios de interrupción. `OBSERVATION_WINDOW_APPROVED=false`.

## 33. Categorías de retención

- `metric_aggregation_retention`
- `sanitized_shadow_audit_retention`
- `security_audit_retention`
- `temporary_evidence_retention`
- `incident_evidence_retention`

No se fijan días, meses o años; no hay auto-delete. `SHADOW_RETENTION_APPROVED=false`.

## 34. Owners y on-call

Roles requeridos: `technical_owner`, `security_owner`, `sre_owner`, `agenda_owner`, `patients_owner`, `data_owner`, `clinical_boundary_reviewer`, `primary_on_call`, `secondary_on_call` y `director_approver`. No se asignan personas o equipos. `OWNER_APPROVED=false`, `ON_CALL_APPROVED=false`.

## 35. Runbooks

Se requieren runbooks no ejecutables para iniciar R1, detener R1, escalar a R2, volver R2→R1, volver R1/R2→R0, sink unavailable, privacy incident, scope leakage, unexpected side effect, legacy response mutation y evidence preservation. Ninguno está implementado.

## 36. Kill switches

Cada superficie futura debe tener flag server-side independiente, default false, sin override de request, cliente o environment. El switch debe detener nueva evaluación canonical sin afectar legacy, preservar evidencia según policy y exponer estado verificable. Los once flags actuales no están autorizados para activación.

## 37. Safe return R1/R2 → R0

Ante stop manual o hard stop: impedir nuevas evaluaciones, mantener legacy, comprobar flags efectivos apagados, preservar evidencia sanitizada, verificar cero writes/requests laterales y registrar decisión de retorno. R2 puede volver a R1 sólo con autorización; R1/R2 vuelven a R0 sin rollback SQL.

## 38. Evidencia necesaria para una implementación futura

Contrato de alcance; hashes de fuentes y superficies protegidas; decisión directorial; schema sanitizado; threat/privacy review; sink selection record; health/readiness evidence; dashboard/alert design; owner/on-call acceptance; baseline plan; sampling/window/budget approvals; test matrix; hard-stop harness; safe-return drill y manifest del commit.

## 39. Criterios de entrada futuros para R1

CUT-01 postvalidado; autorización explícita de implementación; autorización separada de activación R1; superficies y sampling versionados; sink requerido listo según risk; dashboards, alertas, owners, on-call y runbooks aprobados; privacy review; failure policy final; baseline y budgets aprobados; flags false antes del cambio y dry-run de retorno a R0.

## 40. Criterios de salida futuros de R1

Ventana aprobada completada; cobertura explicable; cero PII; cero side effects; legacy inalterado; mismatches dentro de budgets aprobados; fallos clasificados; evidence pack íntegro; safe return ejercitado; decisión directorial separada. Esta actividad no satisface esos criterios.

## 41. Criterios de entrada futuros para R2

R1 con salida aprobada; sink audit listo; schema y allowlist versionados; retención aprobada; health/readiness separados; authority audit fail-closed comprobado; owner/on-call y runbooks activos; autorización independiente R2.

## 42. Criterios de salida futuros de R2

Cobertura y reconciliación aprobadas; audit trail sanitizado e íntegro; cero PII, writes o mutación legacy; fallos dentro de budgets aprobados; retención operable; hard stops y retorno R2→R1/R0 ejercitados; decisión directorial posterior. No habilita R3.

## 43. Parámetros diferidos

Todos permanecen `UNRESOLVED_PENDING_PARAMETER_APPROVAL`: `sink`, `retention`, `owner`, `on_call`, `SLO`, `p95`, `p99`, `error_budgets`, `observation_windows`, `sampling`, `sampling_key`, `sampling_scope`, `sampling_exclusions`, `dashboard_platform`, `alert_platform`, `alert_routes`, `hard_stop_numeric_thresholds`, `health_timeout`, `readiness_timeout`, `residence`, `jurisdiction`, `encryption_key_management`, `opaque_reference_algorithm`, `opaque_reference_key_rotation`, `audit_event_schema_version`, `incident_evidence_retention`, `RTO` y `RPO`.

Percentiles, porcentajes y duraciones permanecen además `UNRESOLVED_PENDING_BASELINE_AND_DIRECTOR_APPROVAL`.

## 44. Blockers abiertos

`BLOCKERS_OPEN=13/13`, `CUTOVER_READINESS=NO_GO_BLOCKERS_PRESENT` y `READINESS=NO_GO_LEGACY_BLOCKERS_PRESENT`. La propuesta mapea decisiones a brechas, pero no cierra F-001, F-002, F-004, F-006, F-008, F-009, F-010, F-012, F-013, F-014, F-017, F-018 o F-023.

## 45. Condiciones de detención y expansión

Se detiene cualquier futura ejecución ante necesidad de archivo fuera del alcance aprobado, cambio de runtime/config/flag/ruta/Clinical/UI, selección no aprobada, cifra inventada, side effect, PII, write, DB/SQL/DDL, OTP real o activación R1/R2. Toda expansión requiere nueva aprobación.

## 46. No autorización

Este documento no autoriza código, wiring, tráfico, sampling, sink, métricas, auditoría, dashboard, alertas, flags, R1, R2, writes, migraciones, backfill, OTP, Clinical, UI, AWS, integración o checkpoint.

Impacto real: `RUNTIME_WIRING=0`, `SHADOW_TRAFFIC_PROCESSED=0`, `AUDIT_EVENTS_WRITTEN=0`, `METRICS_EMITTED=0`, `DATABASE_CONNECTIONS_OPENED=0`, `SQL_EXECUTED=0`, `DDL_EXECUTED=0`, `MIGRATIONS_CREATED=0`, `MIGRATIONS_APPLIED=0`, `DATA_MIGRATED=0`, `PERSISTENCE_WRITES=0`, `BACKFILL_EXECUTED=0`, `CANONICAL_RESPONSES=0`, `CANONICAL_WRITES=0`, `REAL_OTP_SENT=0`, `CLINICAL_REQUESTS_EXECUTED=0`, `OUTBOX_CREATED=false`, `SAGA_CREATED=false`, `WORKER_CREATED=false`, `QUEUE_CREATED=false`, `DLQ_CREATED=false`, `COMPENSATIONS_IMPLEMENTED=false`, `ROUTE_CHANGES=0`, `HTTP_CONTRACT_CHANGES=0`, `PAYLOAD_CHANGES=0`, `UI_CHANGES=0`, `CLINICAL_RUNTIME_CHANGES=0`, `AWS_WRITES=0`.

## 47. Estado final de la Actividad 15

`ACTIVITY15=CUT02_DOCUMENTATION_COMPLETE_READY_FOR_POSTVALIDATION_NOT_INTEGRATED`

Actividad 14 cerrada e integrada; Actividad 15 documental completa, lista para postvalidación y no integrada; Actividad 16 bloqueada. Contador oficial `14/22`; pendientes `8`. No se crea checkpoint 15.
