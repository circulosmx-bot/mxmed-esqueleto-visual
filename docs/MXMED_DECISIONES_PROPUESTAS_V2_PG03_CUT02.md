# MXMed V2 · PG-03 · Decisiones propuestas para CUT-02

## Límite de autoridad

Este registro contiene ocho decisiones técnicas propuestas para revisión directorial. Ninguna está aprobada y ninguna autoriza implementación, wiring, tráfico, R1 o R2. Rollout efectivo: R0 disabled.

## DEC-015A — Superficies y secuencia de shadow

- **ID / mapeo:** DEC-015A / D-09, D-10 y F-023/F-024.
- **Problema:** CUT-02 necesita una secuencia controlada sin mezclar reads puros, privacidad pública o fronteras de write.
- **Evidencia:** existen once flags false y adapters/harnesses R0; el plan de gates define R1 shadow y R2 audit_only, pero no hay activación autorizada.
- **Opciones:** una ola simultánea; olas por riesgo; mantener R0.
- **Ventajas:** olas por riesgo aíslan autoridad, lectura Agenda y dominio puro, y facilitan safe return.
- **Riesgos:** una ola simultánea amplía blast radius; clasificar un flag no elimina sus blockers.
- **Recomendación técnica:** primera ola candidata con `canonical_actor_authority`, `canonical_schedule_read`, `canonical_availability_compare`, `canonical_appointment_lifecycle` y `canonical_patient_identity`; `canonical_public_agenda` y `shadow_audit` condicionales/bloqueados; `patient_identity_persistence`, `legacy_write_disable`, `read_compare` y `backfill` excluidos de la primera implementación.
- **Información no resuelta:** sampling, scope, exclusiones, secuencia final, sink y budgets — `UNRESOLVED_PENDING_PARAMETER_APPROVAL`.
- **Impacto CUT-02:** propuesta de orden; todos los flags permanecen false y no hay activación.
- **Gate futuro:** autorización de implementación CUT-02 y aprobación separada de R1.
- **Responsable de aprobación:** Dirección técnica, Seguridad, SRE, Agenda y Patients.
- **Estado:** `PROPOSED_PENDING_DIRECTOR_APPROVAL`.
- **Límite de la propuesta:** no autoriza implementación, wiring, R1 o R2.

## DEC-015B — Invariancia legacy y contrato de evaluación

- **ID / mapeo:** DEC-015B / F-007, F-008, F-015 y F-027.
- **Problema:** una evaluación paralela puede introducir latencia, excepciones o mutaciones si no existe un contrato estricto.
- **Evidencia:** los adapters CUT-01 actuales son puros/dormidos y el registro de flags fuerza efectividad false; legacy continúa respondiendo.
- **Opciones:** canonical responde en fallback; canonical sólo diagnostica; no evaluar.
- **Ventajas:** diagnóstico puro permite medir sin transferir autoridad.
- **Riesgos:** cualquier lectura adicional, write o excepción puede alterar el flujo legacy.
- **Recomendación técnica:** copiar datos ya disponibles, no abrir lecturas adicionales sin autorización, no responder, no escribir y preservar exactamente status, headers y payload legacy.
- **Información no resuelta:** modelo comparable por superficie, costo y budgets — `UNRESOLVED_PENDING_PARAMETER_APPROVAL`.
- **Impacto CUT-02:** define el contrato futuro de R1/R2; hoy `CANONICAL_RESPONSES=0` y `CANONICAL_WRITES=0`.
- **Gate futuro:** harness de invariancia y hard stops bajo autorización separada.
- **Responsable de aprobación:** Arquitectura y owners de Agenda/Patients.
- **Estado:** `PROPOSED_PENDING_DIRECTOR_APPROVAL`.
- **Límite de la propuesta:** no autoriza implementación, wiring, R1 o R2.

## DEC-015C — Auditoría sanitizada y privacidad

- **ID / mapeo:** DEC-015C / D-09 y F-019/F-028.
- **Problema:** R2 requiere evidencia sin convertir PII, datos clínicos o payloads en metadata.
- **Evidencia:** `Pg03ObservabilityPort` acepta referencias opacas y rechaza dimensiones sensibles; no existe sink configurado.
- **Opciones:** payload libre; allowlist cerrada; sólo métricas agregadas.
- **Ventajas:** allowlist cerrada preserva evidencia diagnóstica y reduce fuga/cardinalidad.
- **Riesgos:** referencias reversibles, labels libres o stack traces pueden filtrar datos.
- **Recomendación técnica:** schema versionado, allowlist conceptual cerrada, denylist explícita de PII/Clinical, correlation IDs opacos y hard stop de privacidad antes del append.
- **Información no resuelta:** algoritmo opaco, key management/rotation, schema version y retención — `UNRESOLVED_PENDING_PARAMETER_APPROVAL`.
- **Impacto CUT-02:** define el envelope propuesto; `AUDIT_EVENTS_WRITTEN=0`.
- **Gate futuro:** privacy/threat review y aprobación del schema antes de R2.
- **Responsable de aprobación:** Seguridad, Privacidad y clinical boundary reviewer.
- **Estado:** `PROPOSED_PENDING_DIRECTOR_APPROVAL`.
- **Límite de la propuesta:** no autoriza implementación, wiring, R1 o R2.

## DEC-015D — Sink, health/readiness y failure policy

- **ID / mapeo:** DEC-015D / DEC-014F y D-09.
- **Problema:** no hay sink seleccionado/configurado ni criterios aprobados para distinguir health de readiness.
- **Evidencia:** el port rechazante reporta `unavailable`; la policy existente deja métrica secundaria fail-open y authority/write audit fail-closed.
- **Opciones:** sink administrado central; sink audit dedicado; métricas y audit separados; adapter sobre infraestructura demostrada.
- **Ventajas:** criterios explícitos permiten evaluar privacidad, residencia, integridad, disponibilidad y operación sin elegir proveedor prematuramente.
- **Riesgos:** health positivo puede ocultar permisos/schema inválidos; fail-open universal pierde auditoría y fail-closed universal degrada legacy.
- **Recomendación técnica:** seleccionar después de due diligence; health y readiness separados; secondary metric fail-open para legacy; authority/write audit y operación desconocida fail-closed para canonical; privacidad/side effect como hard stop.
- **Información no resuelta:** sink, timeouts, residencia, jurisdicción, acceso, cifrado, retención, owner/on-call y matriz final — `UNRESOLVED_PENDING_PARAMETER_APPROVAL`.
- **Impacto CUT-02:** `AUDIT_SINK_SELECTED=false` y `AUDIT_SINK_CONFIGURED=false`.
- **Gate futuro:** decisión de proveedor/arquitectura y readiness postvalidado antes de R1/R2.
- **Responsable de aprobación:** SRE, Seguridad y Dirección técnica.
- **Estado:** `PROPOSED_PENDING_DIRECTOR_APPROVAL`.
- **Límite de la propuesta:** no autoriza implementación, wiring, R1 o R2.

## DEC-015E — Catálogo de métricas y dimensiones

- **ID / mapeo:** DEC-015E / DEC-014G y F-024/F-028.
- **Problema:** R1/R2 no puede gobernarse sin señales, pero labels abiertos crean PII y cardinalidad no controlada.
- **Evidencia:** CUT01-D sólo define referencias y failure policy; no emite métricas ni implementa dashboards.
- **Opciones:** métricas por ID; catálogo cerrado agregado; sólo logs.
- **Ventajas:** catálogo cerrado sin PII permite mismatch, fallos, privacidad, side effects y latencia por superficie.
- **Riesgos:** dimensiones libres y IDs como labels degradan privacidad/costo; una métrica sin contexto puede inducir decisiones falsas.
- **Recomendación técnica:** usar el catálogo conceptual de trece métricas CUT-02 y dimensiones enumeradas cerradas; referencias opacas sólo para correlación, nunca labels de cardinalidad abierta; dashboards y alertas obligatorios antes de activación.
- **Información no resuelta:** cardinalidad, plataforma, rutas de alerta, p95/p99, SLO y budgets — `UNRESOLVED_PENDING_PARAMETER_APPROVAL`.
- **Impacto CUT-02:** `METRICS_EMITTED=0`, `DASHBOARD_IMPLEMENTED=false`, `ALERTING_IMPLEMENTED=false`.
- **Gate futuro:** diseño observable versionado y aprobación directorial de cifras.
- **Responsable de aprobación:** SRE, Seguridad y owners de superficies.
- **Estado:** `PROPOSED_PENDING_DIRECTOR_APPROVAL`.
- **Límite de la propuesta:** no autoriza implementación, wiring, R1 o R2.

## DEC-015F — Baseline, sampling, ventanas y budgets

- **ID / mapeo:** DEC-015F / DEC-014G y D-10.
- **Problema:** no existe baseline aprobado del cual derivar sampling, ventanas, p95/p99 o error budgets.
- **Evidencia:** el contrato CUT01-D deja todas las cifras pendientes y R0 procesa cero tráfico shadow.
- **Opciones:** fijar defaults anticipados; medir baseline sanitizado; permanecer R0.
- **Ventajas:** baseline y aprobaciones progresivas evitan garantías y hard stops arbitrarios.
- **Riesgos:** sampling sesgado o ventanas insuficientes producen conclusiones incorrectas; cifras inventadas crean falsa seguridad.
- **Recomendación técnica:** seguir `R0 harness evidence → baseline collection plan → sanitized evidence review → technical director approval → versioned sampling approval → versioned observation window approval → versioned p95/p99 and error-budget approval → implementation authorization → separate R1 activation approval`.
- **Información no resuelta:** sampling/key/scope/exclusions, observation windows, p95, p99, error budgets y thresholds — `UNRESOLVED_PENDING_BASELINE_AND_DIRECTOR_APPROVAL`.
- **Impacto CUT-02:** `sampling=0`, `SAMPLING_APPROVED=false`, `THRESHOLDS_APPROVED=false`.
- **Gate futuro:** aprobación progresiva versionada antes de implementación y activación.
- **Responsable de aprobación:** Dirección técnica con revisión SRE/Seguridad.
- **Estado:** `PROPOSED_PENDING_DIRECTOR_APPROVAL`.
- **Límite de la propuesta:** no autoriza implementación, wiring, R1 o R2.

## DEC-015G — Owners, on-call, retención y runbooks

- **ID / mapeo:** DEC-015G / D-05, D-06, D-12, D-13 y D-14.
- **Problema:** no hay responsables nominales, guardias, duraciones de retención o runbooks aprobados para R1/R2.
- **Evidencia:** preflight confirma owner/on-call/retención no aprobados y cero infraestructura operativa.
- **Opciones:** owner único; ownership por rol y superficie; operación ad hoc.
- **Ventajas:** roles por riesgo/superficie y escalamiento primario/secundario distribuyen responsabilidad de forma verificable.
- **Riesgos:** operación ad hoc retrasa hard stops; retención arbitraria puede perder evidencia o conservarla indebidamente.
- **Recomendación técnica:** exigir technical/security/SRE/Agenda/Patients/data owners, clinical boundary reviewer, primary/secondary on-call y director approver; aprobar categorías de retención sin defaults; versionar runbooks de inicio, detención, escalamiento, incidentes y preservación.
- **Información no resuelta:** personas/equipos, rotaciones, RTO/RPO, duraciones y plataformas — `UNRESOLVED_PENDING_PARAMETER_APPROVAL`.
- **Impacto CUT-02:** `OWNER_APPROVED=false`, `ON_CALL_APPROVED=false`, `SHADOW_RETENTION_APPROVED=false`.
- **Gate futuro:** aceptación operativa y runbook drill antes de R1.
- **Responsable de aprobación:** Dirección, SRE, Seguridad y owners de dominio.
- **Estado:** `PROPOSED_PENDING_DIRECTOR_APPROVAL`.
- **Límite de la propuesta:** no autoriza implementación, wiring, R1 o R2.

## DEC-015H — Hard stops, kill switches y safe return

- **ID / mapeo:** DEC-015H / DEC-014H y D-13/D-14.
- **Problema:** R1/R2 requieren contención inmediata sin reset destructivo, rollback SQL o mutación de legacy.
- **Evidencia:** los once flags están false y efectivamente apagados; el safe return conceptual de R1/R2 es volver a R0.
- **Opciones:** sólo revert Git; sólo flags; flags, hard stops, evidence preservation y runbook.
- **Ventajas:** la combinación permite detener evaluación sin afectar legacy y conserva trazabilidad.
- **Riesgos:** revert no sustituye reconciliación; eliminar evidencia o usar reset/force-push rompe auditoría.
- **Recomendación técnica:** kill switch server-side independiente; hard stops mínimos de privacidad, respuesta, HTTP, payload, writes, DB/SQL, OTP, Clinical, scope, audit, unknown y side effects; R2→R1 o R1/R2→R0; preservar evidencia; nunca reset, rebase, amend, force-push o rollback SQL.
- **Información no resuelta:** RTO/RPO, alert routes, budgets, ownership y evidencia retention — `UNRESOLVED_PENDING_PARAMETER_APPROVAL`.
- **Impacto CUT-02:** safe return documental; cero ejecución.
- **Gate futuro:** hard-stop harness y drill aprobado antes de cualquier activación.
- **Responsable de aprobación:** SRE, Seguridad, Arquitectura y Dirección técnica.
- **Estado:** `PROPOSED_PENDING_DIRECTOR_APPROVAL`.
- **Límite de la propuesta:** no autoriza implementación, wiring, R1 o R2.

## Parámetros diferidos obligatorios

Permanecen no aprobados con marca `UNRESOLVED_PENDING_PARAMETER_APPROVAL`: `sink`, `retention`, `owner`, `on_call`, `SLO`, `p95`, `p99`, `error_budgets`, `observation_windows`, `sampling`, `sampling_key`, `sampling_scope`, `sampling_exclusions`, `dashboard_platform`, `alert_platform`, `alert_routes`, `hard_stop_numeric_thresholds`, `health_timeout`, `readiness_timeout`, `residence`, `jurisdiction`, `encryption_key_management`, `opaque_reference_algorithm`, `opaque_reference_key_rotation`, `audit_event_schema_version`, `incident_evidence_retention`, `RTO` y `RPO`.

Percentiles, porcentajes y duración permanecen `UNRESOLVED_PENDING_BASELINE_AND_DIRECTOR_APPROVAL`. No se fijan números, proveedores, personas, duraciones o defaults operativos.

## Estado del registro

Decisiones propuestas: 8. Decisiones aprobadas: 0. Implementación autorizada: false. R1 autorizado: false. R2 autorizado: false. `BLOCKERS_OPEN=13/13`, `CUTOVER_READINESS=NO_GO_BLOCKERS_PRESENT`, `READINESS=NO_GO_LEGACY_BLOCKERS_PRESENT`.
