# MXMed V2 · PG-03 · CUT-02B Sanitized Baseline Evidence Schema

## Identidad documental

- Nombre conceptual: `mxmed.cut02b.sanitized_baseline_evidence`.
- Versión: `schema_version=proposed-v1`.
- Actividad: 18/22.
- Identificador: `ARCH/MXMed-PG03-CUT02-B-Baseline-Sanitized-Evidence-Readiness-01`.
- Estado: propuesta documental no implementada y no aprobada para producción.

## Límite de autorización

Este schema define una forma futura de evidencia sintética sanitizada. No crea storage, archivos de paquete, serializadores, tráfico, resultados, métricas o auditoría.

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

## Allowlist cerrada de campos

El registro conceptual acepta exactamente y en este orden:

```text
schema_version
fixture_version
surface
scenario_category
legacy_outcome_code
canonical_outcome_code
reason_code
legacy_digest
canonical_digest
legacy_invariant
hard_stop
correlation_ref
scope_ref
adapter_version
harness_result
safe_return_target_stage
safe_return_target_mode
evidence_package_version
```

Cualquier campo adicional rechaza el registro y el paquete.

## Reglas por campo

| Campo | Regla documental |
|---|---|
| `schema_version` | valor exacto `proposed-v1` |
| `fixture_version` | valor presente en el catálogo versionado y cerrado |
| `surface` | enum cerrado de cinco superficies |
| `scenario_category` | enum cerrado de ocho categorías |
| `legacy_outcome_code` | patrón `[A-Z][A-Z0-9_]*` |
| `canonical_outcome_code` | patrón `[A-Z][A-Z0-9_]*` |
| `reason_code` | patrón `[A-Z][A-Z0-9_]*` |
| `legacy_digest` | SHA-256 lowercase hex de 64 caracteres |
| `canonical_digest` | SHA-256 lowercase hex de 64 caracteres |
| `legacy_invariant` | boolean |
| `hard_stop` | null o miembro del catálogo CUT-02A existente |
| `correlation_ref` | patrón `test:[a-z0-9][a-z0-9._:-]*` |
| `scope_ref` | patrón `test:[a-z0-9][a-z0-9._:-]*` |
| `adapter_version` | miembro del catálogo cerrado declarado en el manifest |
| `harness_result` | enum `PASS`, `FAIL` o `REJECTED` |
| `safe_return_target_stage` | valor exacto `R0` |
| `safe_return_target_mode` | valor exacto `disabled` |
| `evidence_package_version` | miembro del catálogo cerrado declarado en el manifest |

No se permiten strings libres. Las únicas referencias variables son las refs opacas de prueba bajo patrón cerrado; códigos y versiones deben estar validados por patrones o catálogos cerrados.

## Enums de superficie

```text
canonical_actor_authority
canonical_schedule_read
canonical_availability_compare
canonical_appointment_lifecycle
canonical_patient_identity
```

No se admite ninguna superficie adicional.

## Enums de categoría

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

## Catálogo de hard stops

`hard_stop` puede ser null o uno de:

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

El último código es declarativo y no incorpora cifra o threshold.

## Canonicalización y orden

El orden de campos es el de la allowlist. Los objetos se ordenan lexicalmente, los arrays preservan posición y los scalars preservan tipo. El mismo input sintético debe producir la misma representación. Se rechazan timestamps automáticos, UUIDs aleatorios, objetos arbitrarios y valores no serializables.

## Digests e integridad

Los digests son SHA-256 en hexadecimal lowercase de 64 caracteres. Un paquete futuro deberá incluir hash por artefacto, manifest ordenado, conteos exactos, versiones de schema/fixture/harness, detección de duplicados y rechazo ante faltantes, extras o cambios de hash.

## Privacidad y denylist

Estas categorías están prohibidas en cualquier profundidad:

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

También están prohibidos payload raw, headers raw, IDs raw, texto clínico, timestamps automáticos, query strings reales, stack traces con datos y cualquier valor que permita reidentificación simple. Una duda de privacidad produce `REJECTED` y detención.

## Package manifest conceptual

Un paquete futuro podría declarar:

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

Esta actividad no crea esos componentes ni define rutas, storage, sink o infraestructura.

## Validación conceptual

La validación futura deberá comprobar allowlist exacta, enums, patrones, versiones, ausencia de campos prohibidos, referencias opacas, digests, determinismo, manifest, duplicados, faltantes y extras. Sólo después podrían realizarse revisiones separadas de privacidad, sesgo, representatividad e integridad.

## Representatividad y límites

Un registro válido demuestra conformidad sintética del harness, no representatividad productiva. No permite inferir percentiles, calcular SLO, aprobar error budgets o sampling, declarar production readiness ni autorizar R1/R2. Ausencia de un resultado no equivale a riesgo cero.

## Safe return

Todo hard stop o rechazo exige `safe_return_target_stage=R0` y `safe_return_target_mode=disabled`; impide nuevas evaluaciones y mantiene legacy. No autoriza respuesta o write canonical, rollback SQL, DB, Clinical u OTP.

## Parámetros diferidos

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

## Estado final

El schema continúa `proposed-v1`; no está implementado, revisado o aprobado. Los once flags permanecen false y R0 disabled.

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
```
