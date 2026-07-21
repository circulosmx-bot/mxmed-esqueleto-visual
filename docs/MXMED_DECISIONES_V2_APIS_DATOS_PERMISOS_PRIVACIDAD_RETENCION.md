# PRODUCT-DECISIONS — APIs, datos, permisos, privacidad y retención V2

## Estado

`ACTIVITY_5_AUDIT_DECISIONS_READY_FOR_FAST_FORWARD_INTEGRATION`

Contrato: `MXMED_ACTIVITY05_DIRECTOR_DECISIONS_CLOSURE_V1`
Clasificación: UI-0 — DOCUMENTAL_NO_UI_CHANGE
Aprobación: `APPROVED_BY_DIRECTOR`

La aprobación documental se registró sobre la rama de auditoría derivada de
`bbbab40f3c423bb73e0afa362cd51eea0b504e17`. La integración fast-forward al
programa todavía no ocurrió. El contador oficial continúa en `4/22`; tras la
integración prevista será `5/22`, con 17 pendientes.

## Decisiones aprobadas

### DEC-012A — Frontera única de autorización

`APPROVED_BY_DIRECTOR`

Frontera única de autorización backend, centralizada y fail-closed.

Contratos: deny by default; frontend no es autoridad; headers/query/body no
acreditan actor ni permisos; `transitional_open` no concede acceso; rutas
públicas y system routes explícitas; reason codes estables; acciones R2/R3
auditadas.

### DEC-012B — Registro canónico por dominio

`APPROVED_BY_DIRECTOR`

Registro canónico versionado por dominio, una sola autoridad de escritura, sin
eliminación destructiva antes de reconciliación y rollback.

Contratos: read-models derivados sin autoridad de escritura; clasificación
`canonical_write`, `read`, `projection`, `legacy`, `draft`, `fixture`; dual-write
permanente y fallbacks silenciosos prohibidos; GET no crea schema ni escribe
datos; ninguna eliminación sin conciliación y rollback.

### DEC-012C — Retención por dominio

`APPROVED_BY_DIRECTOR`

Retención por dominio, `retention_unresolved` donde corresponda y sin
eliminación irreversible sin validación especializada.

Contratos: estados `active`, `inactive`, `frozen`, `archived`, `anonymized`,
`deleted`; backups incluidos; datos clínicos separados del estado comercial;
cambios de política clasificados R2/R3.

### DEC-012D — Exportación y disposición

`APPROVED_BY_DIRECTOR`

Exportación y disposición por dominio, sujeto y finalidad; anonimización y
eliminación irreversible R3, con simulación, aprobación y trazabilidad.

Contratos: separar cuenta, perfil, paciente, contenido clínico y datos
financieros; exportación según actor, ownership y scope; anonimización efectiva;
`anonymization_unresolved` cuando no pueda garantizarse; preview, aprobación,
idempotencia, conciliación y audit trail; sin ejecución destructiva en
Actividad 6.

### DEC-012E — Audit trail transversal

`APPROVED_BY_DIRECTOR`

Audit trail transversal unificado, productores por dominio, minimización,
correlación, integridad y fail-closed para R2/R3.

Contratos: evento común con `real_actor`, `effective_actor` y
`affected_subject` separados; `correlation_id` transversal; before/after
minimizado; secretos y datos clínicos completos prohibidos; fail-closed si el
evento crítico no puede escribirse; scopes separados para acceder al trail;
retención propia `unresolved` hasta validación.

### DEC-012F — Support-assisted session y break-glass

`APPROVED_BY_DIRECTOR`

Support-assisted session y break-glass separados, temporales, visibles, scope
mínimo, MFA, caso, auditoría reforzada y fail-closed; ambos deshabilitados hasta
aprobación productiva.

Contratos: impersonación invisible prohibida; caso, motivo, scope, MFA,
reautenticación y expiración; banner visible futuro UI-3; soporte clínico
prohibido por defecto; break-glass sólo para emergencias reales; scopes mínimos;
revisión posterior; separación de funciones; interruptores iniciales `false`;
no habilitación real en Actividad 6.

## Estado de actividades

- Antes de integración: `4/22`, 18 pendientes; Actividad 5
  `READY_FOR_FAST_FORWARD_INTEGRATION`.
- Después de integración prevista: `5/22`, 17 pendientes; Actividad 5
  `CONCLUIDA`.
- Actividad 6: `GATES_RESOLVED_NOT_STARTED`, no iniciada y sin implementación
  autorizada.

## Scope y prohibiciones preservadas

Este cierre no modifica APIs, módulos, UI, prototipos, SQL, migraciones,
configuración, AWS, Stripe ni datos. No ejecuta writes HTTP, SQL real,
exportaciones, anonimización, eliminación, claim, soporte asistido, break-glass
ni Actividad 6.
