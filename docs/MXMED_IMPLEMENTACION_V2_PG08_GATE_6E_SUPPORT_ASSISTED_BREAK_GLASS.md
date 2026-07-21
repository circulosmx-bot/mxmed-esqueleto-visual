# PRODUCT-IMPLEMENTATION — PG-08 Gate 6E: soporte asistido y break-glass

## Resultado

`PASS_GATE_6E_SUPPORT_ASSISTED_BREAK_GLASS_READY_FOR_REVIEW`

Contrato: `MXMED_PG08_SUPPORT_ASSISTED_BREAK_GLASS_DISABLED_FOUNDATIONS_GATE_6E_V1`
Clasificación: UI-0 — `PURE_PRIVILEGED_ACCESS_POLICY_NO_RUNTIME_ACTIVATION`

Gate 6E traduce DEC-012F a contratos y evaluadores puros. Support-assisted y
break-glass permanecen separados, deshabilitados y no activables; no se crean
sesiones, credenciales, cookies, endpoints ni registros persistentes.

## Contratos y arquitectura

- Modos: `PrivilegedAccessMode` (`support_assisted`, `break_glass`).
- Petición inmutable: `PrivilegedAccessRequest`.
- Aprobación: `PrivilegedAccessApprovalEvidence` con separación de funciones.
- Decisión sanitizada: `PrivilegedAccessDecision`, siempre `activatable=false`.
- Razones: `PrivilegedAccessReason`, registro acotado sin mensajes arbitrarios.
- Servicios puros: `SupportAssistedAccessEvaluator`,
  `BreakGlassAccessEvaluator`, `PrivilegedAccessActivationGate`,
  `SupportAccessLifecyclePlanner` y `PrivilegedAccessAuditEventFactory`.

Se reutilizan los contratos de Gate 6A y los servicios/adapters de Gates 6B–6D
sin modificar sus firmas o comportamiento.

## Support-assisted

Requiere `internal_operator`, riesgo R2/R3, estado approved, actor real y
efectivo distintos, sujeto, caso, motivo por referencia, scopes explícitos,
expiración vigente, MFA, reautenticación, aprobación independiente,
visibilidad, autorización central y auditoría aceptada. Acceso clínico es
`clinical_access_denied`.

## Break-glass

Requiere `governance_emergency`, riesgo exacto R3, emergencia confirmada,
caso, motivo, scopes mínimos, expiración finita, MFA, reautenticación, dos
aprobaciones independientes, visibilidad, revisión posterior, autorización
central y auditoría. El plano de emergencia no concede privilegios; una
solicitud clínica nunca abre datos ni activa acceso.

## Alcance, temporalidad y separación

Scopes vacíos o comodines (`*`, `all`, `admin.everything`, `support.all`) se
deniegan. No se derivan scopes desde plan, pertenencia u ownership. Las fechas
son UTC explícitas y finitas; `maximum_duration_policy=unresolved` no vuelve
permanente una petición. No existe impersonación invisible: actor real,
efectivo y sujeto permanecen separados. `visibility_required=true`,
`ui_implemented=false` e `invisible_access=false` quedan registrados.

## Auditoría fail-closed

`PrivilegedAccessAuditEventFactory` emite sólo eventos minimizados compatibles
con el allow-list de Gate 6D: `support_assisted_policy_evaluated`,
`break_glass_policy_evaluated` y `privileged_access_transition_planned`.
No incluye scopes completos, motivos textuales, sesión, credenciales ni datos
clínicos. Audit ausente/unavailable/rejected bloquea la política; accepted sólo
permite marcarla satisfecha, nunca activarla.

## Activation hard-stop y ciclo de vida

`PrivilegedAccessActivationGate::mayActivate()` retorna siempre `false`, aun
si se solicitan flags verdaderos. No se leen query params, headers, cookies,
sesiones, payloads ni variables de entorno. El lifecycle planner modela las
transiciones contractuales sin persistirlas: `transition_real=false` y
`executable=false`; closed es terminal, expired/revoked no se reactivan y
requested no salta a active.

## No conexión

Sesiones reales, tokens, cookies, impersonaciones, privilegios elevados,
endpoint/runtime/session wiring, UI, SQL/migraciones, AWS, datos reales y
accesos clínicos: `0`. Gate 6D permanece intacto y Gate 6F aún no inicia.

## Estado

- Contador oficial: `5/22`; pendientes: `17`.
- Gate 6E cerrado internamente, listo para revisión de Gate 6F.
- Actividad 7 bloqueada.
