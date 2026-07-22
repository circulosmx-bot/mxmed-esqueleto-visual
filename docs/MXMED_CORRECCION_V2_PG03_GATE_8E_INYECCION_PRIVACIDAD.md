# MXMed PG-03 — Corrección Gate 8E: inyección de privacidad

## Resultado

`PASS_ACTIVITY_8_GATE_8E_PRIVACY_INJECTION_CORRECTED`

## Clasificación y baseline

Corrección `UI-0` limitada al dominio puro y al arnés QA de Gate 8E.

- Rama: `feature/mxmed-pg03-agenda-foundations-v2`.
- HEAD inicial: `3c99bb51be9276fc9a48d721986b4e2491d261b9`.
- Parent Gate 8D: `4d44d14abe743bba0424c1a7856b231c4a9a3dc1`.
- Programa sin integrar: `ee625b0b57c0caa623c4b156cfa2734a6881cf85`.
- Bundle: `/tmp/mxmed-activity08-gate8e-privacy-injection-correction-preflight-v2/activity08-before-gate8e-privacy-injection-correction.bundle`.

## Diagnóstico ejecutable y causa raíz

El diagnóstico previo confirmó tres exposiciones:

- email crudo aceptado dentro de campos de proyección pública;
- teléfono crudo aceptado dentro de campos de proyección pública;
- OTP crudo aceptado en metadatos de eventos de auditoría.

`PublicContactPrivacyProjection` validaba `status`, `next_action` y
`result_code` como identificadores genéricos o sólo comprobaba que no fueran
vacíos. `PublicAuditEvent` validaba `operation_id`, `correlation_id` y
`outcome_code` con el mismo identificador genérico. Esa función sólo protege la
forma básica de IDs opacos; no constituye una frontera de privacidad y debe
permanecer compatible para sus consumidores existentes.

## Fronteras explícitas

Se conserva byte-equivalente `PublicAgendaPolicy::identifier()` y se agregan dos
validadores cerrados.

`publicProjectionToken`:

- aplica `trim` y rechaza cualquier transformación implícita;
- longitud máxima 64;
- forma exacta `^[a-z][a-z0-9_]{0,63}$`;
- rechaza `\d{6,}`;
- falla con `invalid_public_projection_token`, salvo código tipado explícito;
- no redacta, sustituye ni trunca.

`auditMetadataToken`:

- aplica `trim` y rechaza cualquier transformación implícita;
- longitud máxima 128;
- forma exacta `^[A-Za-z][A-Za-z0-9_.:-]{0,127}$`;
- rechaza `\d{6,}`;
- falla con `invalid_audit_metadata`;
- no redacta, sustituye ni trunca.

La regla de seis dígitos consecutivos impide tanto un OTP exacto como su
inyección dentro de un token que, por lo demás, tendría forma válida. No es una
blacklist de fixtures.

## Campos protegidos

En `PublicContactPrivacyProjection`, `status`, `next_action` y `result_code`
pasan por `publicProjectionToken`. `appointment_id` conserva la validación de ID
opaco. Las allow-lists y el orden de las tres proyecciones permanecen iguales.

En `PublicAuditEvent`, `operation_id` y `correlation_id` pasan por
`auditMetadataToken` con `invalid_audit_metadata`; `outcome_code` pasa por
`publicProjectionToken` con `invalid_audit_outcome`. No cambian tipos, digests,
policy, timestamp, intentos, terminalidad, serialización ni algoritmo de event
ID.

## Rechazo fail-closed y no redacción

Email, teléfono, OTP exacto, OTP incrustado, espacios, controles, guiones no
permitidos y prefijos numéricos lanzan `PublicAgendaDomainException`. La
validación ocurre antes de crear la proyección o el evento. No se devuelve un
arreglo parcial, no se crea evento y no se produce una versión enmascarada,
truncada o sustituida.

## Compatibilidad canónica y criptográfica

Las salidas válidas siguen byte-equivalentes:

```json
{"intent_id":"intent-public","challenge_id":"challenge-public","masked_destination":"p***@example.mx","expires_in":480,"next_action":"verify_otp","result_code":"generic_pending"}
```

```json
{"appointment_id":"appointment-public","status":"confirmed","next_action":"done","result_code":"generic_confirmed"}
```

```json
{"result_code":"invalid_code","next_action":"retry"}
```

El evento canónico conserva el ID
`b2ae37248ada8113e61ebe6ab25699277ea897833f36694fad8d38e79b04eb01`
y la misma serialización. No cambian fingerprints de binding, grant digests,
capability digests ni IDs generados con entradas válidas.

## Pruebas y simulaciones

La prueba Gate 8E existente se amplía; no se crea otra. Comprueba inyección en
todos los campos públicos y en operation/correlation/outcome con email,
teléfono, OTP exacto y OTP incrustado, además de espacios y prefijos numéricos.
Cada rechazo exige excepción tipada y ausencia de salida. También fija las tres
proyecciones canónicas y el event ID/serialización previos.

Las regresiones Gate 8A–8E, Gate 6B, Gate 6F, Identity y Subscriptions, el lint
de los 14 dominios y del test, la pureza estática, las simulaciones negativas,
PP-309 futura y rollback deben pasar desde el mismo HEAD corregido.

## Evidencia anterior parcialmente invalidada

La evidencia original en
`/tmp/mxmed-activity08-gate8e-public-agenda-otp-privacy-v2/` permanece sin
modificación: exactamente 10 JSON y tres textos, con los hashes del manifest
preflight preservados.

No se modifica retroactivamente. Quedan `executionally invalidated` únicamente
sus afirmaciones absolutas sobre bloqueo de inyección; el resto permanece como
evidencia histórica. La nueva evidencia correctiva reemplaza sólo esas
afirmaciones y se entrega en
`/tmp/mxmed-activity08-gate8e-privacy-injection-correction-v2/`.

## Preservación y alcance

Se modifican sólo tres dominios Gate 8E y su prueba, y se crea este documento.
PP-308 y Plan Maestro permanecen intactos; su hash normalizado sigue siendo
`cc2c17c0061742e72e234cda7ccfe3efee1fac904e30d1a755fd6f1a236926f4`.
El arnés continúa compatible con PP-309 futura.

Gate 8D, contratos Gate 8A, seguridad Gate 8B, disponibilidad Gate 8C y legado
permanecen byte-equivalentes. Runtime, rutas, SQL, datos reales, OTP reales,
citas reales, contactos reales, merges y AWS permanecen en cero. No se conectó
el runtime ni se inició servidor candidato.

## Safe return, rollback y Git

Rollback principal futuro:
`git revert --no-edit <gate8e_privacy_correction_commit>`.

Retorno alterno:
`git switch -c recovery/activity08-gate8e-privacy-injection 3c99bb51be9276fc9a48d721986b4e2491d261b9`.

El dry-run se ejecuta sólo en worktree detached y debe reconstruir exactamente
el árbol Gate 8E implementado, sin commit. La corrección se entrega como
undécimo commit aditivo, independiente y reversible, sin reescribir historia.
El programa permanece sin integrar y no se crea checkpoint.

## Estado final

- Gate 8E: `IMPLEMENTED_READY_FOR_FINAL_POSTVALIDATION`.
- Gate 8F: `NOT_STARTED`.
- Actividad 8: `IN_PROGRESS`.
- Actividad 9: `BLOCKED`.
- Contador: `7/22`.
- Readiness: `NO_GO_LEGACY_BLOCKERS_PRESENT`.

No integrar. No crear checkpoint. No iniciar Gate 8F.
