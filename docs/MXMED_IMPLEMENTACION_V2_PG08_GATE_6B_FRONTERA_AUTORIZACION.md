# PRODUCT-IMPLEMENTATION — PG-08 Gate 6B: frontera central de autorización

## Resultado

`PASS_GATE_6B_AUTHORIZATION_BOUNDARY_READY_FOR_REVIEW`

Contrato: `MXMED_PG08_CENTRAL_FAIL_CLOSED_AUTHORIZATION_BOUNDARY_GATE_6B_V1`
Clasificación: UI-0 — `BACKEND_AUTHORIZATION_SERVICE_NO_ENDPOINT_WIRING`

Gate 6B implementa una frontera backend pura, determinista y fail-closed sobre
los contratos de Gate 6A. Recibe un `TrustedAuthorizationContext` y un
`AuthorizationRequirement`, evalúa requisitos en orden estable y produce una
`AuthorizationDecision` sanitizada.

## Baseline y arquitectura

- Programa: `program/mxmed-product-refinement-22-v2`
- Programa HEAD: `be9cd4f067096a7798d1ea6941ec291981039823`
- HEAD inicial Gate 6B: `300c7adcd9e2e9d78343b0c2e064d7cf51c5240c`
- Rama: `feature/mxmed-apis-data-permissions-privacy-foundations-v2`
- Servicio: `modules/platform/services/AuthorizationBoundary.php`
- Contrato de solicitud: `modules/platform/contracts/AuthorizationRequirement.php`
- Contexto confiable: `modules/platform/contracts/TrustedAuthorizationContext.php`
- Adaptadores de prueba: `modules/platform/adapters/`
- Prueba: `modules/platform/tests/Gate6BAuthorizationBoundaryTest.php`

No se reemplazan contratos de Identity o Capabilities. No se modificó
autoload; no hay wiring productivo.

## Frontera de autorización

La frontera sólo permite evaluación desde contexto backend confiable. Un
`AuthorizationContext` plano o un contexto marcado como client-originated se
deniega con `CLIENT_IDENTITY_NOT_AUTHORITATIVE`. `transitional_open` siempre
produce deny.

La evaluación no consulta HTTP, globals, base de datos, planes comerciales ni
estado de negocio, y no renderiza mensajes.

## Orden de denegación

La precedencia implementada es determinista:

1. contexto confiable;
2. transitional/client identity;
3. sesión;
4. credential version;
5. cuenta activa;
6. membership activa;
7. entity/profile;
8. authorization plane;
9. ownership;
10. role;
11. scopes;
12. capabilities;
13. action;
14. resource;
15. risk;
16. case;
17. reautenticación;
18. MFA;
19. aprobación/doble aprobación;
20. audit trail obligatorio.

Sólo después de satisfacer todo se emite allow con regla satisfecha y
correlación sanitizada.

## Role, scope y capability

Se evalúan de forma acumulativa e independiente. Professional no es operador
interno; planes comerciales no sustituyen roles; Agenda no equivale a
Clinical; ownership, role, scope y capability no se convierten implícitamente.
Los comodines globales son rechazados.

## Public/system

`public_system` requiere declaración explícita de ruta pública o de sistema.
Una operación no declarada produce `PUBLIC_ROUTE_NOT_DECLARED` o
`SYSTEM_ROUTE_NOT_DECLARED`. No se modificaron rutas existentes ni se creó
ningún middleware.

## Riesgo y auditoría

R2 y R3 exigen audit trail. Los adaptadores de Gate 6B son exclusivamente de
prueba: accepted, rejected y unavailable. Para R2/R3, rejected/unavailable o
la ausencia del puerto producen deny (`AUDIT_REQUIRED` o
`AUDIT_UNAVAILABLE`). Gate 6D implementará persistencia.

## Estado

- Contador oficial: `5/22`; 17 pendientes.
- Actividad 6: `IN_PROGRESS_INTERNAL`; no concluida.
- Gate 6B: `CLOSED_INTERNAL_READY_FOR_GATE_6C_REVIEW`.
- Gate 6C: `NOT_STARTED`.
- Actividad 7: bloqueada.

## No conexión

Endpoints, routers, middleware HTTP, perfiles, Agenda, Clinical,
Suscripciones, Stripe, webhooks, identidad productiva, UI, SQL, migraciones,
AWS, 8091 y datos reales no fueron modificados ni conectados.
