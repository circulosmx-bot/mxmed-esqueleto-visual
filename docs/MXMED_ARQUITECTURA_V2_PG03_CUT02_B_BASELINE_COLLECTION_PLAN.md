# MXMed V2 · PG-03 · CUT-02B Baseline Collection Plan

## 1. Identidad

- Programa: MXMed Product Refinement V2.
- Actividad: 18/22.
- Nombre: CUT-02B — Baseline Collection Plan and Sanitized Evidence Readiness.
- Identificador: `ARCH/MXMed-PG03-CUT02-B-Baseline-Sanitized-Evidence-Readiness-01`.
- Fecha: 2026-07-23.
- Clasificación: UI-0.
- Tipo: documentación y readiness, sin implementación runtime.

## 2. Parent y checkpoint

- Parent: `2964d2f1a1c51cd94b3c3eb7df1caa0abc792904`.
- Checkpoint 17: `checkpoint/mxmed-product-refinement-v2-activity17`.
- Objeto anotado: `484350569709330499c7cf9acdc4a9d0aeff315a`.
- Rama: `architecture/mxmed-pg03-cut02b-baseline-sanitized-evidence-v2`.

## 3. Autorización y límites

Está autorizada únicamente la documentación del plan. No están autorizadas su ejecución, la revisión de un paquete futuro, la recopilación de baseline, el uso de tráfico o datos reales, el wiring, CUT-02, R1 o R2.

```text
BASELINE_COLLECTION_PLAN_AUTHORIZED=true
BASELINE_COLLECTION_EXECUTION_AUTHORIZED=false
SANITIZED_EVIDENCE_REVIEW_AUTHORIZED=false
SAMPLING=0
CUT02_IMPLEMENTATION_AUTHORIZED=false
R1_ACTIVATION_AUTHORIZED=false
R2_ACTIVATION_AUTHORIZED=false
ROLLOUT_STAGE=R0
ROLLOUT_MODE=disabled
```

## 4. Objetivo

Definir cómo una actividad futura, separada y expresamente autorizada podría preparar fixtures sintéticos, ejecutar offline el harness R0 y producir un paquete sanitizado para revisión. Este documento no crea fixtures, resultados, manifests de paquete ni evidencia de baseline.

## 5. Dependencias

Las dependencias documentales son la arquitectura CUT-02, las decisiones DEC-015A–H ratificadas y el harness R0 de CUT-02A. Los cuatro archivos de producción y las tres pruebas del harness permanecen byte-equivalent. No se importa, configura, ejecuta o conecta ninguna dependencia runtime.

## 6. Cinco superficies

La allowlist cubre exactamente:

```text
canonical_actor_authority
canonical_schedule_read
canonical_availability_compare
canonical_appointment_lifecycle
canonical_patient_identity
```

Quedan fuera `canonical_public_agenda`, `shadow_audit`, `patient_identity_persistence`, `legacy_write_disable`, `read_compare` y `backfill`. Una superficie no listada invalida el paquete.

## 7. Secuencia futura

La única secuencia admisible para una actividad posterior es:

```text
synthetic_fixture_catalog
→ offline_harness_execution_plan
→ sanitized_result_package
→ privacy_static_validation
→ representativeness_and_bias_review
→ evidence_integrity_review
→ technical_director_review
→ future_sampling_parameter_proposal
```

La última etapa sólo permite elaborar una propuesta futura. No aprueba sampling, ventanas, cifras, R1 o R2.

## 8. Catálogo de fixtures

Un catálogo futuro deberá ser cerrado, versionado, ordenado y reproducible. Todos sus fixtures serán sintéticos, nunca derivados de producción; usarán referencias `test:`, versión explícita, outcomes enumerados y valores no sensibles. El catálogo declarará sus exclusiones y no establecerá una cantidad total obligatoria.

## 9. Categorías de escenario

Cada superficie deberá considerar estas categorías cerradas:

```text
nominal
boundary
invalid_closed
privacy_rejection
hard_stop
legacy_invariant
outcome_difference_without_response_mutation
deterministic_repeat
```

Las categorías describen intención de prueba; no expresan volumen, prioridad productiva ni cobertura aprobada.

## 10. Matriz mínima por superficie

| Superficie | Categorías sintéticas futuras |
|---|---|
| `canonical_actor_authority` | nominal; boundary; invalid_closed; privacy_rejection; hard_stop; legacy_invariant; outcome_difference_without_response_mutation; deterministic_repeat |
| `canonical_schedule_read` | nominal; boundary; invalid_closed; privacy_rejection; hard_stop; legacy_invariant; outcome_difference_without_response_mutation; deterministic_repeat |
| `canonical_availability_compare` | nominal; boundary; invalid_closed; privacy_rejection; hard_stop; legacy_invariant; outcome_difference_without_response_mutation; deterministic_repeat |
| `canonical_appointment_lifecycle` | nominal; boundary; invalid_closed; privacy_rejection; hard_stop; legacy_invariant; outcome_difference_without_response_mutation; deterministic_repeat |
| `canonical_patient_identity` | nominal; boundary; invalid_closed; privacy_rejection; hard_stop; legacy_invariant; outcome_difference_without_response_mutation; deterministic_repeat |

La matriz obliga a justificar presencia o ausencia de escenarios, pero no fija cuotas ni convierte una ausencia en riesgo cero.

## 11. Comparación legacy/canonical

La ejecución futura sólo podrá comparar snapshots sintéticos e inmutables. El resultado canonical será diagnóstico: nunca reemplazará la respuesta legacy. La comparación separará status, headers normalizados, payload canonicalizado y outcome codes. Una diferencia de outcome sin mutación de respuesta seguirá siendo evidencia de invariancia.

## 12. Invariancia

La invariancia requiere igualdad estricta de status, headers normalizados y payload canonicalizado. Legacy continúa siendo la única respuesta; canonical no responde ni escribe. Cualquier diferencia se clasifica con el catálogo de hard stops y produce safe return R0.

## 13. Normalización

Las keys de headers se convierten a lowercase y se ordenan lexicalmente; los valores se preservan y ninguna diferencia se descarta. Los objetos del payload se ordenan por key; los arrays conservan orden; los scalars conservan tipo y valor. Objetos arbitrarios, closures, resources o valores no serializables se rechazan.

## 14. Digests

Cada artefacto conceptual y cada respuesta normalizada futura usarán SHA-256 en hexadecimal lowercase de 64 caracteres. El digest permite comprobar integridad, no autoriza reidentificación ni sustituye la revisión de privacidad. No se incluye payload raw como material de evidencia.

## 15. Referencias opacas

`correlation_ref` y `scope_ref` deberán cumplir `test:[a-z0-9][a-z0-9._:-]*`. Sólo se permiten referencias de prueba no reversibles por inspección simple. IDs reales, UUIDs copiados de producción y query strings reales están prohibidos.

## 16. Representatividad

Los fixtures sintéticos no equivalen a tráfico productivo. No permiten inferir percentiles reales, calcular SLO, aprobar error budgets, aprobar sampling, declarar production readiness ni acreditar comportamiento bajo carga. Toda revisión futura distinguirá explícitamente evidencia sintética de evidencia productiva.

## 17. Sesgo

Un paquete futuro deberá declarar origen sintético, supuestos, exclusiones, escenarios ausentes y límites de generalización. La selección manual puede sobrerrepresentar casos conocidos y omitir combinaciones no anticipadas. Ausencia de un escenario no equivale a ausencia de riesgo.

## 18. Cobertura

La cobertura se expresará sólo como relación trazable entre superficies, categorías y fixtures sintéticos declarados. No se fija objetivo numérico, tasa, porcentaje o volumen. Cualquier propuesta posterior deberá explicar huecos sin promoverlos a aceptación implícita.

## 19. Integridad

El paquete futuro deberá usar SHA-256 por artefacto, manifest ordenado, conteos exactos, detección de duplicados y versiones declaradas de schema, fixture y harness. Se rechazará si falta un archivo esperado, aparece uno extra, cambia un hash, existe PII o un mismo fixture no produce resultado determinista.

## 20. Privacidad

La denylist cerrada de evidencia prohibida contiene exactamente:

```text
patient_id
appointment_id
doctor_id
name
phone
email
address
birthdate
contact
otp
token
diagnosis
clinical_notes
real_request_bodies
sensitive_headers
cookies
credentials
stack_traces_with_data
production_payloads
real_query_strings
```

Cualquier aparición en cualquier profundidad detiene la preparación y rechaza el paquete. No se seleccionan algoritmo criptográfico, key management ni método productivo de seudonimización.

## 21. Revisión

Una revisión futura deberá validar estáticamente la denylist, ausencia de payload raw y headers sensibles, patrón de referencias opacas y resistencia a reidentificación por inspección simple. Ante cualquier duda se detiene. Después revisará representatividad, sesgo, integridad y decisión técnica; esta actividad no autoriza esa revisión.

## 22. Versionado

El schema conceptual se identifica como `mxmed.cut02b.sanitized_baseline_evidence` y exclusivamente `schema_version=proposed-v1`. Fixture, adapter, harness y paquete deberán declarar versiones cerradas en sus manifests futuros. `proposed-v1` no es productiva, implementada o aprobada.

## 23. Paquete de evidencia

Un paquete futuro debería declarar únicamente estos componentes conceptuales:

```text
package_metadata
fixture_catalog_manifest
sanitized_results
privacy_validation
integrity_manifest
coverage_notes
bias_and_exclusions
review_decisions
safe_return_evidence
```

Esta actividad no crea esos archivos. Las 12 categorías de evidencia permitida son:

```text
synthetic_fixture_catalog
enumerated_outcome_codes
enumerated_reason_codes
sha256_digests
opaque_test_references
synthetic_counts
harness_pass_fail_results
fixture_versions
schema_versions
hard_stops
legacy_invariance_evidence
safe_return_r0_evidence
```

## 24. Criterios de aceptación

Un paquete futuro sólo podrá avanzar a revisión si la superficie y categoría pertenecen a sus enums, el schema es el propuesto aplicable, todos los campos están en allowlist, los códigos y refs cumplen sus patrones, los hashes verifican, no hay duplicados o extras, la privacidad estática pasa, la ejecución es determinista, legacy permanece invariante o existe hard stop válido y safe return apunta a R0 disabled.

## 25. Criterios de rechazo

Se rechaza ante evidencia prohibida, campo extra, string libre, superficie o categoría desconocida, versión ausente, digest inválido, referencia no opaca, resultado no determinista, mutación legacy, side effect, archivo faltante o extra, hash distinto, revisión dudosa o intento de convertir evidencia sintética en aprobación productiva.

## 26. Safe return

Ante rechazo o hard stop: detener preparación y nuevas evaluaciones, mantener R0 disabled, conservar sólo evidencia sanitizada permitida, no responder ni escribir por canonical, no ejecutar rollback SQL y volver al parent mediante revert Git sin commit cuando se valide reversibilidad. Legacy continúa sin cambios.

## 27. Condiciones de detención

Se detiene ante archivo cuarto, cambio de código/harness/tests/config/flags, tráfico o datos reales, PII, DB, SQL, DDL, persistencia, backfill, OTP, Clinical, métricas/auditoría real, sink seleccionado, sampling distinto de cero, cifra inventada, parámetro aprobado, PP histórico alterado, test/lint fallido o safe return no idéntico al parent.

## 28. Parámetros diferidos

```text
sampling=0
sampling_key=UNRESOLVED_PENDING_PARAMETER_APPROVAL
sampling_scope=UNRESOLVED_PENDING_PARAMETER_APPROVAL
sampling_exclusions=UNRESOLVED_PENDING_PARAMETER_APPROVAL
observation_windows=UNRESOLVED_PENDING_PARAMETER_APPROVAL
p95=UNRESOLVED_PENDING_BASELINE_AND_DIRECTOR_APPROVAL
p99=UNRESOLVED_PENDING_BASELINE_AND_DIRECTOR_APPROVAL
SLO=UNRESOLVED_PENDING_PARAMETER_APPROVAL
error_budgets=UNRESOLVED_PENDING_BASELINE_AND_DIRECTOR_APPROVAL
hard_stop_numeric_thresholds=UNRESOLVED_PENDING_PARAMETER_APPROVAL
sink=UNRESOLVED_PENDING_PARAMETER_APPROVAL
retention=UNRESOLVED_PENDING_PARAMETER_APPROVAL
owners=UNRESOLVED_PENDING_PARAMETER_APPROVAL
on_call=UNRESOLVED_PENDING_PARAMETER_APPROVAL
dashboard_platform=UNRESOLVED_PENDING_PARAMETER_APPROVAL
alert_platform=UNRESOLVED_PENDING_PARAMETER_APPROVAL
alert_routes=UNRESOLVED_PENDING_PARAMETER_APPROVAL
health_timeout=UNRESOLVED_PENDING_PARAMETER_APPROVAL
readiness_timeout=UNRESOLVED_PENDING_PARAMETER_APPROVAL
RTO=UNRESOLVED_PENDING_PARAMETER_APPROVAL
RPO=UNRESOLVED_PENDING_PARAMETER_APPROVAL
```

No se asignan personas, proveedores, plataformas, ventanas, percentiles, budgets, timeouts, RTO o RPO.

## 29. Cero efectos reales

```text
REAL_TRAFFIC=0
DATABASE_CONNECTIONS_OPENED=0
SQL_EXECUTED=0
DDL_EXECUTED=0
PERSISTENCE_WRITES=0
BACKFILL_EXECUTED=0
REAL_OTP_SENT=0
CLINICAL_REQUESTS_EXECUTED=0
METRICS_EMITTED=0
AUDIT_EVENTS_WRITTEN=0
```

No se ejecutaron requests, fixtures, harness, baseline, revisión, DB, SQL, persistencia, OTP, Clinical, métricas o auditoría.

## 30. Estado final

Los once flags permanecen false y efectivamente deshabilitados. Los trece blockers siguen abiertos; cutover es `NO_GO_BLOCKERS_PRESENT` y readiness `NO_GO_LEGACY_BLOCKERS_PRESENT`.

```text
BASELINE_COLLECTION_PLAN_AUTHORIZED=true
BASELINE_COLLECTION_EXECUTION_AUTHORIZED=false
SANITIZED_EVIDENCE_REVIEW_AUTHORIZED=false
SAMPLING=0
CUT02_IMPLEMENTATION_AUTHORIZED=false
R1_ACTIVATION_AUTHORIZED=false
R2_ACTIVATION_AUTHORIZED=false
FEATURE_FLAGS=11/11
FEATURE_FLAGS_FALSE=11/11
RUNTIME_TRUE_FLAGS=0
ROLLOUT_STAGE=R0
ROLLOUT_MODE=disabled
ACTIVITY18=CUT02_B_BASELINE_PLAN_READY_FOR_POSTVALIDATION_NOT_INTEGRATED
ACTIVITY19=BLOCKED
OFFICIAL_COUNTER=17/22
PENDING_ACTIVITIES=5
```
