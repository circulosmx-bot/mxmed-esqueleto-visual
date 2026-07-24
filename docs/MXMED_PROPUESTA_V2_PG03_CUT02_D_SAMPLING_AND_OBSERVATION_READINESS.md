# CUT-02D — Propuesta de sampling y readiness de observación

## Identidad y naturaleza

Actividad 20/22. Identificador `ARCH-QA/MXMed-PG03-CUT02-D-Synthetic-Evidence-Technical-Review-Sampling-Proposal-01`. Parent `d63bb02544cd00807ca931456991e692f3115f6f`; checkpoint 19 `checkpoint/mxmed-product-refinement-v2-activity19`, objeto `f88f5396a6bedf50c26b17c9c843165cb25e9afb`.

Este documento propone una estructura futura pendiente de aprobación. No aprueba sampling, ventana, baseline real, operación, activación o implementación.

## Contrato propuesto

```text
proposal_version=cut02d-proposed-v1
purpose=STRUCTURE_FUTURE_OFFLINE_COMPARISON_REVIEW
eligible_surfaces=CLOSED_FIVE_SURFACE_CATALOG
surface_order_candidate=AUTHORITY_THEN_SCHEDULE_THEN_AVAILABILITY_THEN_LIFECYCLE_THEN_IDENTITY
sampling_unit=ELIGIBLE_SURFACE_EVALUATION_CANDIDATE
sampling_key_candidate=UNRESOLVED_PENDING_DIRECTOR_APPROVAL
sampling_scope_candidate=ELIGIBLE_SURFACES_ONLY_PENDING_PRIVACY_REVIEW
sampling_exclusions_candidate=PRIVACY_HARD_STOPS_AND_UNAPPROVED_OPERATIONS
initial_stage=R0
legacy_response_invariant=true
canonical_write_allowed=false
privacy_requirements=SANITIZED_ALLOWLIST_AND_SEPARATE_REAL_TRAFFIC_REVIEW
hard_stops=REUSE_CUT02A_CLOSED_CATALOG
required_sink_state=SELECTED_AND_CONFIGURED_BEFORE_SEPARATE_APPROVAL
required_owner_state=APPROVED_BEFORE_SEPARATE_APPROVAL
required_on_call_state=APPROVED_BEFORE_SEPARATE_APPROVAL
required_dashboard_state=IMPLEMENTED_BEFORE_SEPARATE_APPROVAL
required_alert_state=IMPLEMENTED_BEFORE_SEPARATE_APPROVAL
required_safe_return_state=R0_DISABLED
approval_status=PROPOSED_PENDING_DIRECTOR_APPROVAL
```

Los valores describen estados candidatos y prerrequisitos. No seleccionan proveedor, plataforma, persona, equipo o configuración.

## Superficies y orden candidato

1. `canonical_actor_authority`
2. `canonical_schedule_read`
3. `canonical_availability_compare`
4. `canonical_appointment_lifecycle`
5. `canonical_patient_identity`

El orden es una dependencia de revisión candidata: autoridad antes de lecturas, lecturas antes de comparación, y las superficies de lifecycle e identidad después de sus dependencias. No autoriza activación.

## Unidad, key, scope y exclusiones candidatas

La unidad candidata es una evaluación elegible de superficie que preserve la respuesta legacy y prohíba writes canónicos. La key permanece sin resolver y debe ser opaca, estable, no identificable y revisada antes de cualquier uso. El scope candidato se limita al catálogo cerrado de superficies.

Las exclusiones candidatas abarcan operaciones no elegibles, referencias no opacas, contenido sensible, ausencia de authority audit, writes, conexiones nuevas, SQL/DDL, OTP, Clinical, fuga de scope, side effects inesperados y cualquier estado sin controles operativos aprobados.

## Requisitos de privacidad

Todo diseño futuro debe conservar schema allowlisted, referencias opacas, ausencia de payload/headers raw y rechazo recursivo de categorías prohibidas. El tratamiento de tráfico real requiere una revisión separada; la validación sintética no la sustituye.

```text
PRIVACY_REVIEW_FOR_REAL_TRAFFIC_APPROVED=false
REAL_BASELINE_COLLECTION_AUTHORIZED=false
```

## Hard stops y safe return

```text
HARD_STOPS_REUSED=15/15
TARGET_STAGE=R0
TARGET_MODE=disabled
LEGACY_CONTINUES=true
CANONICAL_RESPONSE_ALLOWED=false
CANONICAL_WRITE_ALLOWED=false
SQL_ROLLBACK_REQUIRED=false
```

Todo hard stop candidato detiene nuevas evaluaciones, conserva legacy y mantiene R0 disabled. No se proponen thresholds numéricos.

## Prerrequisitos operativos

```text
AUDIT_SINK_SELECTED=false
AUDIT_SINK_CONFIGURED=false
DASHBOARD_IMPLEMENTED=false
ALERTING_IMPLEMENTED=false
OWNER_APPROVED=false
ON_CALL_APPROVED=false
RETENTION_APPROVED=false
PRIVACY_REVIEW_FOR_REAL_TRAFFIC_APPROVED=false
REAL_BASELINE_COLLECTION_AUTHORIZED=false
R1_ACTIVATION_AUTHORIZED=false
```

Sink, dashboard, alertas, owner, on-call y retención permanecen sin selección o aprobación.

## Propuesta de observación

Una futura observación sólo podría plantearse después de aprobar por separado privacidad, sink, retención, ownership, on-call, dashboard, alertas, safe return y autorización de baseline real. La estructura candidata separa entrada, evaluación sanitizada, detección de hard stop, revisión agregada y retorno seguro. La duración efectiva permanece sin resolver.

## Parámetros no resueltos

```text
sampling_rate=UNRESOLVED_PENDING_DIRECTOR_APPROVAL
observation_window=UNRESOLVED_PENDING_DIRECTOR_APPROVAL
minimum_volume=UNRESOLVED_PENDING_DIRECTOR_APPROVAL
p95_threshold=UNRESOLVED_PENDING_REAL_BASELINE
p99_threshold=UNRESOLVED_PENDING_REAL_BASELINE
error_budget=UNRESOLVED_PENDING_REAL_BASELINE
latency_budget=UNRESOLVED_PENDING_REAL_BASELINE
```

No existe tasa, porcentaje, volumen, duración, percentil, budget o threshold efectivo.

## Criterios de entrada candidatos

- decisiones DEC-020A–H revisadas mediante autorización separada;
- privacidad para tráfico real aprobada separadamente;
- sink seleccionado y configurado;
- retención aprobada;
- owner y on-call aprobados;
- dashboard y alertas implementados;
- safe return validado;
- baseline real autorizado;
- parámetros numéricos respaldados por baseline real y aprobación directorial;
- once flags todavía false hasta una activación separada.

## Criterios de rechazo y detención

Se rechaza cualquier propuesta con PII, payload/headers raw, referencias no opacas, superficie no elegible, write canónico, cambio de respuesta legacy, efecto no permitido o parámetro sin aprobación.

Se detiene ante cualquier hard stop CUT-02A, falta de sink o control operativo, pérdida de invariancia, incidente de privacidad, degradación no evaluable, discrepancia de integridad o imposibilidad de retorno seguro. La detención conserva R0 disabled.

## Dependencia de baseline real y estado

La evidencia sintética no soporta inferencias productivas. Antes de proponer valores efectivos se requiere un baseline real autorizado y revisado por separado.

```text
PRODUCTION_REPRESENTATIVENESS_PROVEN=false
REAL_BASELINE_REQUIRED_BEFORE_NUMERIC_APPROVAL=true
SAMPLING=0
SAMPLING_APPROVED=false
OBSERVATION_WINDOW_APPROVED=false
ROLLOUT_STAGE=R0
ROLLOUT_MODE=disabled
approval_status=PROPOSED_PENDING_DIRECTOR_APPROVAL
```
