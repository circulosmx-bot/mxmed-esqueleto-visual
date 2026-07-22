# MXMed PG-03 — Gate 8E: Agenda pública, OTP y privacidad de contacto

## Resultado

`PASS_ACTIVITY_8_GATE_8E_PUBLIC_AGENDA_OTP_CONTACT_PRIVACY_IMPLEMENTED`

## Resumen ejecutivo

Gate 8E define una autoridad canónica, versionada, determinista y fail-closed
para la intención de reserva pública, el desafío OTP, su verificación, replay,
grant, cancelación pública, privacidad, auditoría y handoff declarativo hacia
Gate 8D. Es una capa de dominio pura y nueva; el legado queda contenido y sin
conexión de runtime.

## Baseline y preflight

- Rama: `feature/mxmed-pg03-agenda-foundations-v2`.
- HEAD inicial: `4d44d14abe743bba0424c1a7856b231c4a9a3dc1`.
- Programa oficial sin integrar: `ee625b0b57c0caa623c4b156cfa2734a6881cf85`.
- Gate 8C postvalidado: `196ab0e28b3c4ed73f7caee9306a2d19239af9ae`.
- Gate 8D postvalidado: `4d44d14abe743bba0424c1a7856b231c4a9a3dc1`.
- Bundle: `/tmp/mxmed-activity08-gate8e-preflight-v2/activity08-before-gate8e.bundle`.
- Preflight: `PASS_ACTIVITY_8_GATE_8E_PREFLIGHT_READY`.
- UI-0; contador 7/22; Actividad 8 en progreso; Actividad 9 bloqueada.

El preflight inventarió 16 superficies públicas, nueve señales QA/debug OTP,
una señal de DevOtpSender por defecto, cuatro señales de SQL directo, 30
señales de persistencia de contacto y 15 señales de contacto en respuestas.
También confirmó cero OTP reales, cero citas reales, cero datos de contacto
modificados y cero SQL ejecutado.

## Autoridad canónica y catálogo

- Contract ID: `pg03-public-agenda-otp-privacy`.
- Versión: `1`.
- Dependencia: `pg03-appointment-lifecycle`, versión `1`.
- Canales exactos: `sms`, `email`.
- Flujo público autoritativo: `false`.
- Handoff server-authoritative requerido: `true`.
- Clinical encounter: `false`.
- Identidad de paciente: `PATIENT_IDENTITY_RESOLUTION_DEFERRED_TO_GATE_8F`.

Los estados OTP son exactamente `pending`, `verified`, `expired`, `locked` y
`consumed`. Los estados terminales no vuelven a `pending`.

## Contacto opaco y binding

`PublicContactReference` recibe únicamente canal, referencia opaca keyed-digest
de 64 hexadecimales y destino enmascarado de máximo 128 caracteres. No recibe ni
conserva teléfono, email, nombre, domicilio o datos clínicos crudos.

`PublicBookingIntent` es readonly e inmutable. Liga intent ID, perfil,
consultorio, slot `AppointmentSlotIdentity` de Gate 8D, contacto, timestamps
RFC3339 con offset y policy version. Su fingerprint SHA-256 determinista usa,
en orden fijo: intent ID, profile ID, consultorio ID, slot key, canal, contact
reference y policy version. Cualquier cambio modifica el fingerprint.

## Política, challenge y verificación

La política exige seis dígitos, TTL de 600 segundos, máximo cinco intentos, un
desafío activo por intención, raw OTP nunca persistido/respondido/registrado/en
eventos, debug OTP canónico deshabilitado y una decisión tipada de rate-limit.
La ausencia o denegación de esa decisión falla cerrado.

`PublicOtpChallenge` conserva sólo el credential hash llegado por un adaptador,
estado, intentos, timestamps, binding, policy version y digest opcional del
grant. `PublicOtpVerificationCommand` mantiene el OTP transitorio y no lo
expone en `toArray`, decisiones, eventos, fingerprints ni excepciones.

`PublicOtpVerifier` aplica el orden de policy, rate-limit, identificadores,
binding, replay, estado terminal, expiración, presupuesto, credencial, snapshot,
grant, evento y handoff. Los resultados tipados incluyen `verified`, `replay`,
`invalid_code`, `locked`, `expired`, `rate_limited`, `binding_mismatch`,
`challenge_mismatch`, `intent_mismatch`, `already_consumed`, `invalid_state`,
`policy_version_mismatch`, `rate_limit_decision_required` e
`idempotency_conflict`.

## Replay, idempotencia y grant

La misma clave, desafío, intención y binding ya verificados produce `replay`, el
mismo grant digest, cero intentos adicionales, cero evento nuevo y cero handoff
nuevo. Una clave incompatible produce `idempotency_conflict` con HTTP 409.

`PublicVerificationGrant` es readonly, mínimo, determinista, ligado a intención
y binding, expira con el desafío y sólo puede consumirse una vez. No contiene
OTP, credential hash, contacto, paciente ni máscara.

`PublicCancellationCapability` modela sólo digest y lifecycle. La capability
cruda no se persiste, no aparece en logs/eventos y su consumo es único; una
capability vencida o de otra intención se rechaza.

## Privacidad y auditoría

`PublicContactPrivacyProjection` usa allow-list cerrada. Antes de confirmar sólo
proyecta intent ID, challenge ID, máscara, expiración, siguiente acción y código
genérico. Después sólo proyecta appointment ID opaco, estado canónico, siguiente
acción y código genérico. No hay enumeración de contactos.

`PublicAuditEvent` es readonly y append-only. Sus ocho tipos permitidos usan
digests de intención/desafío, operation/correlation IDs, outcome, canal, policy,
timestamp, intentos y terminalidad. Nunca incluyen OTP, credenciales, PII,
tokens, cookies, headers, IP cruda, user agent crudo o payload libre.

## Handoff Gate 8D y Gate 8B

Los cinco handoffs son `create_pending_otp_appointment`,
`confirm_verified_appointment`, `cancel_expired_appointment`,
`cancel_locked_appointment` y `cancel_by_public_capability`. Todos declaran
`server_authoritative_required=true`, lifecycle version 1 y reason code cerrado.
No aceptan actor, estado final, razón libre, versión o autoridad del cliente.
El adaptador futuro debe resolver actor por Gate 8B y delegar la mutación a Gate
8D; Gate 8E no escribe citas ni ejecuta operaciones.

## Plan transaccional declarativo

`PublicBookingMutationPlan` declara exactamente 16 pasos: iniciar transacción,
bloquear intención, scope de rate-limit, desafío, resolver idempotencia,
verificar binding/estado/expiración/intentos/credencial, persistir resultado,
persistir o reproducir grant, delegar Gate 8D, anexar auditoría, persistir
idempotencia y commit. Los errores requieren rollback; el plan no ejecuta nada,
no permite escritura directa de cita ni SQL directo.

## Gate 8F y Clinical

No se decide existencia de paciente, duplicados, matching, merge, creación de
identidad o consolidación. No se crea paciente. La cita de Agenda no es Clinical
encounter y no crea expediente, nota, diagnóstico, receta o documento clínico.

## Pureza, determinismo y compatibilidad

Los 14 PHP de `modules/agenda/publicflow/` no contienen acceso a persistencia,
red, entorno, sesión, superglobals, reloj global, aleatoriedad, servidores,
controladores o repositorios legacy. Todos los timestamps e IDs se reciben o se
derivan determinísticamente. La prueba no exige que PP-309 permanezca ausente:
la sección actual PP-308 se normaliza con `rtrim($raw, "\\r\\n") . "\\n"` y el
arnés sigue compatible con una PP-309 futura.

PP-308 actual: 4211 bytes, un salto terminal, hash normalizado
`cc2c17c0061742e72e234cda7ccfe3efee1fac904e30d1a755fd6f1a236926f4`.

## Legado contenido y limitaciones

Las superficies públicas existentes permanecen
`LEGACY_CONTAINED_PENDING_ADAPTER_AND_ROLLOUT`. No se afirma que el legado use
esta capa, que se hayan enviado OTP, que se haya creado una cita, que exista
rate-limiting productivo, HMAC productivo, persistencia canónica, SMS/email real
o concurrencia real. No hay runtime wiring, rutas, SQL, migraciones ni cambios
productivos.

## Pruebas y evidencia

La prueba Gate 8E cubre catálogo, canales, binding, estados, código incorrecto,
quinto intento, expiración, replay, conflicto 409, grant, capability,
proyección, auditoría, handoffs, plan de 16 pasos, Gate 8F, Clinical y pureza.
Las regresiones de Gate 8A–8D, Gate 6B, Gate 6F, Identity y Subscriptions deben
pasar junto con PHP lint de los 14 dominios y el test Gate 8E.

La evidencia temporal se entrega en
`/tmp/mxmed-activity08-gate8e-public-agenda-otp-privacy-v2/` con 10 JSON y tres
textos. Incluye pruebas negativas para código incorrecto, quinto intento,
expiración, bindings incompatibles, rate-limit, grant/capability vencidos o
consumidos, idempotencia incompatible y exposición de contacto/OTP.

## Safe return, rollback y Git

Retorno principal: `git revert --no-edit <gate8e_commit>`.
Retorno alterno:
`git switch -c recovery/activity08-gate8e 4d44d14abe743bba0424c1a7856b231c4a9a3dc1`.
El rollback se ensaya únicamente en worktree detached y debe reconstruir el
árbol Gate 8D postvalidado sin crear commit.

La implementación se entrega como décimo commit independiente, aditivo y
reversible, sin amend, rebase, squash, merge, cherry-pick, force push ni reset
destructivo. El programa oficial sigue sin integrar.

## Estado final declarado

- Gate 8A: `POSTVALIDATED_COMPLETE`.
- Gate 8B: `POSTVALIDATED_COMPLETE_WITH_ROUTE_POLICY_CORRECTED`.
- Gate 8C: `POSTVALIDATED_COMPLETE_WITH_CONTRACT_HARDENING`.
- Gate 8D: `POSTVALIDATED_COMPLETE_WITH_CUMULATIVE_HARNESS_AND_STABLE_PP307_HASH`.
- Gate 8E: `IMPLEMENTED_READY_FOR_POSTVALIDATION`.
- Gate 8F: `NOT_STARTED`.
- Actividad 8: `IN_PROGRESS`.
- Actividad 9: `BLOCKED`.
- Contador: `7/22`.
- Readiness: `NO_GO_LEGACY_BLOCKERS_PRESENT`.

No integrar. No crear checkpoint. No iniciar Gate 8F ni Actividad 9.
