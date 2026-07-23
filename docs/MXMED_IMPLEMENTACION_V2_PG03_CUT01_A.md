# MXMed PG-03 — Implementación CUT01-A

## 1. Identificador

`BE-ARCH/MXMed-PG03-CUT01-A-Authority-Composition-Roots-01`.

## 2. Baseline

Parent vinculante `9f3a3187192ddd4f9307841807f207365b2cd529`; Actividad 10 cerrada e integrada. El programa conserva contador oficial `10/22`, doce actividades pendientes, `CUTOVER_READINESS=NO_GO_BLOCKERS_PRESENT` y `READINESS=NO_GO_LEGACY_BLOCKERS_PRESENT`.

## 3. Checkpoint

`checkpoint/mxmed-product-refinement-v2-activity10` desreferencia exactamente al baseline. El bundle de retorno seguro es `/tmp/mxmed-activity11-cut01a-authority-composition-roots-preflight-v2-r4/program-before-activity11.bundle`.

## 4. Aprobación directorial

Dirección aprobó CUT01-A y la ampliación explícita de ocho a nueve archivos para incorporar `modules/agenda/config/agenda.php`. La aprobación no autoriza activación, tráfico canónico, shadow traffic, R1 ni CUT01-B–D.

## 5. Clasificación UI-0

La actividad es backend/arquitectura UI-0: `UI_CHANGES=0`, sin copy, CSS, JavaScript, localStorage, Clinical runtime ni AWS.

## 6. Objetivo

Componer autoridad canónica reutilizable para Agenda y Patients, manteniéndola completamente dormida frente al tráfico real:

```text
CUT01_A_IMPLEMENTED=true
CUT01_A_ACTIVATED=false
```

## 7. Alcance revisado

Alcance exacto: cinco archivos nuevos, cuatro modificados, nueve versionados en total. No se amplió el inventario candidato de 42 archivos.

## 8. Archivos

Nuevos: `AgendaAuthorityCompositionRoot.php`, `PatientsAuthorityCompositionRoot.php`, dos pruebas CUT01-A y este documento. Modificados: los routers Agenda/Patients, la configuración Agenda y `PLAN_MAESTRO_MXMED.md`.

## 9. Autoridad Agenda

`AgendaAuthorityCompositionRoot` compone explícitamente `AuthorizationBoundary`, `AuditTrailPort` y `AgendaActorAuthorityResolver`; reutiliza `AuthenticatedAccessContext`, `AccountMembership`, `CanonicalProfileReference`, `AgendaAuthorizationTarget`, `OperatorBinding`, `ClientAuthorityClaims` y `AgendaAuthorityResolution`. Principal, actor real y actor efectivo permanecen separados; los claims cliente sólo producen diagnóstico y nunca autoridad.

## 10. Autoridad Patients

`PatientsAuthorityCompositionRoot` recibe identidad y membership server-side, valida que el doctor target corresponda al profile de membership, representa al paciente como `SubjectReference('patient', ...)`, compone `TrustedAuthorizationContext` y obtiene una `AuthorizationDecision` mediante `AuthorizationBoundary`. Doctor ID y patient ID son targets, no actores. No se ejecutan Gate 8F, Gate 8G, identity resolution, persistencia ni merge.

## 11. Claims cliente

Headers de rol, IDs, doctor, profile, consultorio, operator, patient, body, query, path, cookies, QA mode y `compat_dev` no son fuentes de autoridad. No existen request overrides ni client overrides; una coincidencia no vuelve confiable un claim y una divergencia no eleva privilegios.

## 12. Feature flag

Fuente única server-side: `modules/agenda/config/agenda.php`.

```text
CANONICAL_ACTOR_AUTHORITY_IMPLEMENTED=true
CANONICAL_ACTOR_AUTHORITY_DEFAULT=false
CANONICAL_ACTOR_AUTHORITY_ACTIVATED=false
CANONICAL_ACTOR_AUTHORITY_APPROVED_FOR_ACTIVATION=false
```

Sólo el booleano literal `true` es elegible. Ausencia, `null`, strings, números, arrays, objetos y cualquier valor inválido producen `false`. No hay override por request, cliente o environment.

## 13. Estado R0

```text
ROLLOUT_STAGE=R0
ROLLOUT_MODE=disabled
```

Los routers sólo cargan el root y preparan una referencia de clase dentro de `canonical_actor_authority === true`; con el default false siguen íntegramente el path legacy. No se instancia el root ni se procesa autoridad canónica o shadow en requests reales.

## 14. Rutas Patients

Se preservan ocho rutas privadas: cuatro lecturas (`GET /patients/{id}`, contactos editables, búsqueda y listado por doctor) y cuatro escrituras (`POST /patients`, address, profile y `PUT` de contactos editables). Agenda preserva 41 privadas y nueve públicas. Resultado contractual: `PRIVATE_ROUTE_BEHAVIOR_PRESERVED=49/49`, con cero cambios de rutas, controllers, dispatch, status HTTP y payload.

## 15. Pruebas

Dos pruebas CUT01-A validan roots, dependencias explícitas, autoridad server-side, claims no confiables, fail-closed del flag, ocho rutas Patients, wiring dormido y ausencia de DB/SQL/writes. Se ejecutan además las doce regresiones heredadas de Gates 8A–8G, Gate 6B, Gate 6F, Identity y Subscriptions: `ACTIVITY11_TESTS=14/14`; todos los PHP nuevos o modificados pasan lint.

## 16. Blockers

F-001, F-002 y F-004 quedan `IMPLEMENTATION_PARTIAL_FLAG_OFF_BLOCKER_OPEN`. F-006, F-008, F-009, F-010, F-012, F-013, F-014, F-017, F-018 y F-023 continúan `DECISION_RATIFIED_BLOCKER_OPEN`. `BLOCKERS_OPEN=13/13`; no hay blocker resuelto o cerrado.

## 17. Safe return y rollback

Retorno seguro: parent/checkpoint `9f3a3187192ddd4f9307841807f207365b2cd529`. El rollback validable es `git revert --no-commit <ACTIVITY11_COMMIT>` en worktree detached; debe recuperar el tree `943e67d7338b7c810182b695cdb125479f171904` sin commit de rollback, reset, rebase, amend ni force-push.

## 18. Exclusiones

CUT01-B–D, schedule, availability, holidays, overrides, `__all__`, backfill, OTP/provider/rate limiting, DDL, migraciones, SQL, observability/shadow audit, adapters de lifecycle/persistence, outbox, saga, Clinical, R1–R4, UI, Subscriptions, AWS y datos reales quedan fuera.

## 19. Evidencia

Evidencia de implementación: `/tmp/mxmed-activity11-cut01a-authority-composition-roots-v2/`, exactamente seis JSON y cinco TXT. El preflight `/tmp/mxmed-activity11-cut01a-authority-composition-roots-preflight-v2-r4/` y toda evidencia histórica permanecen intactos.

## 20. Estado final

`ACTIVITY11=CUT01_A_IMPLEMENTED_FLAG_OFF_READY_FOR_POSTVALIDATION_NOT_INTEGRATED`; `ACTIVITY12=BLOCKED`. La actividad no queda cerrada, integrada, lista para R1 ni production ready. Impacto: cero DB, conexiones, SQL, DDL, migraciones, datos, OTP, citas, pacientes, merges, backfill, tráfico canónico, shadow traffic, cambios runtime activos, rutas, HTTP, payload, UI, Clinical y AWS.
