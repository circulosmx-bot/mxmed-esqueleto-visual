# BE/MXMed-PG03-Activity08-Gate8D-AppointmentLifecycle-Idempotency-Concurrency-01

## Resultado

`PASS_ACTIVITY_8_GATE_8D_APPOINTMENT_LIFECYCLE_IDEMPOTENCY_IMPLEMENTED`

## Resumen ejecutivo

Gate 8D implementa una autoridad de dominio UI-0, pura, determinista,
inmutable y fail-closed para el ciclo de vida de citas de Agenda. La capa
define el catálogo versionado, la matriz exhaustiva, optimistic aggregate
version, comandos tipados, idempotencia, identidad canónica de slot, claims,
conflictos de superposición, eventos append-only y un plan transaccional
declarativo.

Gate 8D define invariantes y contratos. No implementa aún persistencia real,
concurrencia de base de datos, `FOR UPDATE` real, índice unique real ni
transacciones ejecutables. La simulación de doble reserva comprueba la
invariancia que deberá aplicar un adaptador transaccional futuro; no prueba
concurrencia real de MySQL.

## Baseline

- Rama: `feature/mxmed-pg03-agenda-foundations-v2`.
- Worktree: `/Users/circulodigital/Documents/GitHub/mxmed-esqueleto-visual-activity08-v2`.
- HEAD inicial: `196ab0e28b3c4ed73f7caee9306a2d19239af9ae`.
- Programa oficial no integrado: `ee625b0b57c0caa623c4b156cfa2734a6881cf85`.
- Checkpoint base: `checkpoint/mxmed-product-refinement-v2-activity07`.
- Seis commits sobre el programa al iniciar Gate 8D.
- Gate 8A: `POSTVALIDATED_COMPLETE`.
- Gate 8B: `POSTVALIDATED_COMPLETE_WITH_ROUTE_POLICY_CORRECTED`.
- Gate 8C: `POSTVALIDATED_COMPLETE_WITH_CONTRACT_HARDENING`.
- Gate 8D al inicio: `IN_PROGRESS`.

## Autoridad DEC-013C

DEC-013C está `APPROVED_BY_DIRECTOR`. La implementación formaliza un catálogo
versionado y una matriz exhaustiva antes de reconciliar estados históricos o
conectar mutaciones productivas. Cada transición aceptada conserva motivo,
actor real, actor efectivo, correlation ID y un evento append-only.

## Autoridad DEC-013D

DEC-013D está `APPROVED_BY_DIRECTOR`. La implementación representa key de
idempotencia, fingerprint SHA-256, replay seguro, conflicto por reutilización
incompatible, optimistic version, identidad de slot, unicidad activa requerida
y el orden futuro de locks/transacción. No crea schema ni afirma que esas
garantías estén activas en base de datos.

## Dependencias Gate 8A, Gate 8B y Gate 8C

`AppointmentLifecycleDefinition` obtiene estados y decisiones de transición
directamente de `AppointmentLifecycleContract`, autoridad de Gate 8A. El
contrato permanece byte-equivalente y la definición falla cerrada si el
catálogo base deja de contener exactamente los siete estados aprobados.

Gate 8B permanece intacto. Los IDs de actor real y efectivo se reciben como
valores tipados ya resueltos; Gate 8D no lee sesión, headers ni claims de
cliente. Gate 8C permanece intacto. El slot de Gate 8D preserva perfil,
consultorio y timezone IANA, pero no conecta todavía el cálculo de
disponibilidad ni una fuente legacy.

## Catálogo canónico y versión del lifecycle

- Lifecycle ID: `pg03-appointment-lifecycle`.
- Lifecycle version: `1`.
- Clinical encounter: `false`.

Estados canónicos, en orden exacto:

1. `tentative`;
2. `pending_otp`;
3. `pending`;
4. `scheduled`;
5. `confirmed`;
6. `canceled`;
7. `no_show`.

Estados terminales:

- `canceled`;
- `no_show`.

Estados que ocupan slot:

- `tentative`;
- `pending_otp`;
- `pending`;
- `scheduled`;
- `confirmed`.

`rescheduled`, `in_progress` y `finished` no son estados canónicos de Gate 8D.

## Matriz exhaustiva

| Desde | Transiciones permitidas |
|---|---|
| `tentative` | `confirmed`, `canceled` |
| `pending_otp` | `confirmed`, `canceled` |
| `pending` | `confirmed`, `canceled` |
| `scheduled` | `confirmed`, `canceled` |
| `confirmed` | `tentative`, `canceled`, `no_show` |
| `canceled` | ninguna |
| `no_show` | ninguna |

La matriz contiene 11 combinaciones permitidas y 38 denegadas sobre las 49
combinaciones posibles. Toda combinación no listada devuelve
`invalid_transition`, HTTP 409, sin evento, sin siguiente versión y sin claim.
Un estado desconocido devuelve `unknown_appointment_state` y falla cerrado.

## Snapshot

`AppointmentSnapshot` es readonly y contiene únicamente `appointment_id`,
`profile_id`, `consultorio_id`, estado, aggregate version, slot y lifecycle
version. Exige IDs presentes, estado canónico, aggregate version mayor o igual
a uno, lifecycle version exacta e identidad de slot coincidente. No contiene
paciente, contacto, payload libre ni motivo clínico.

La aggregate version pertenece a la instancia de cita. La lifecycle version
pertenece al catálogo; ambas versiones no son intercambiables.

## Optimistic aggregate version

El comando declara `expected_aggregate_version`. Un valor distinto de la
versión del snapshot devuelve `aggregate_version_conflict`, HTTP 409, sin
evento y sin siguiente versión. Una transición aceptada produce exactamente
`current_version + 1`. Un replay conserva la versión original del resultado y
no la incrementa.

## Comando tipado

`AppointmentTransitionCommand` contiene exclusivamente:

- `operation_id`;
- `idempotency_key`;
- `correlation_id`;
- `appointment_id`;
- `expected_aggregate_version`;
- `from_state`;
- `to_state`;
- `reason_code`;
- `actor_real_id`;
- `actor_effective_id`;
- `occurred_at` explícito;
- `requested_slot` opcional y tipado.

Los identificadores operativos aplican
`^[A-Za-z0-9][A-Za-z0-9_.:-]{0,127}$`. `profile_id` y `consultorio_id` usan una
validación no vacía y sin controles, sin imponer esa regex a identificadores
legacy. El comando no acepta payload genérico, PII, motivo clínico libre,
headers o sesión global.

## Idempotency key y fingerprint

La key es obligatoria, tiene máximo 128 caracteres y aplica la política segura
de identificadores. `AppointmentOperationFingerprint` usa SHA-256 sobre una
serialización de campos tipados en orden canónico. No usa payload libre, reloj
global, aleatoriedad ni orden incidental de arrays.

## Record de idempotencia

`AppointmentIdempotencyRecord` conserva únicamente:

- idempotency key;
- operation ID;
- fingerprint;
- appointment ID;
- outcome code;
- original HTTP status;
- result digest;
- aggregate version result;
- recorded at explícito.

No conserva payload, nombre, teléfono, email, motivo clínico, headers, cookies
o tokens. Gate 8D no inventa TTL ni plazo de retención.

## Replay e idempotency conflict

Sin record previo, la decisión es `new_operation` y puede continuar. Misma key
y mismo fingerprint produce `replay`: devuelve original HTTP status, result
digest y aggregate version result, sin mutación, evento, incremento o claim.

Misma key con fingerprint distinto produce `idempotency_conflict`, HTTP 409,
sin evento, mutación o claim. La idempotencia se resuelve antes de verificar el
aggregate; así un retry de una operación ya aplicada no se convierte
erróneamente en version conflict.

## Slot canónico y timezone

`AppointmentSlotIdentity` contiene perfil, consultorio, timezone IANA,
`start_at` y `end_at`. Ambos timestamps requieren RFC3339 con offset explícito;
el offset debe coincidir con la timezone IANA para ese instante. Se preserva la
timezone y se derivan instantes UTC sólo para comparación y hashing. No se usa
timezone global, fecha implícita ni hora actual.

La slot key, lock scope y unique claim key derivan de campos tipados. El lock
scope es común al par perfil/consultorio para que el adaptador futuro pueda
serializar también rangos superpuestos, no sólo slots exactos.

## Intervalos semiabiertos

Los slots usan `[start, end)`. Dos slots adyacentes no se superponen. Dos rangos
con intersección estricta sí entran en conflicto. La comparación se realiza por
instantes UTC y exige `start_at < end_at`.

## Claims activos

`AppointmentSlotClaim` contiene appointment ID, slot, estado, aggregate
version y flag active. Un claim activo sólo puede corresponder a uno de los
cinco estados ocupantes. `canceled` y `no_show` nunca pueden tener claim activo.
Los claims inactivos y terminales no bloquean. Un claim desconocido, sin tipo o
terminal activo devuelve `invalid_claim`.

Al evaluar una reubicación se excluye la cita actual por appointment ID. Claims
de otro perfil o consultorio no afectan la decisión.

## Guard de concurrencia

`AppointmentConcurrencyGuard` devuelve `allowed`, reason, HTTP status,
identificador conflictivo minimizado mediante SHA-256, requested slot key,
lock scope y unique claim key. Mismo perfil, mismo consultorio, rangos
superpuestos y claim activo producen `slot_conflict`/409. La colección se
ordena por una clave canónica antes de evaluar; permutar claims produce la
misma decisión.

## Simulación de doble reserva

La prueba usa dos comandos, dos idempotency keys, dos citas sintéticas en
`tentative`, el mismo perfil, consultorio y slot. La primera evaluación se
acepta y genera un claim activo. La segunda recibe ese claim y devuelve
`slot_conflict`/409, sin evento ni segundo claim. El resultado contiene una
sola cita activa para el slot.

Es una verificación pura y determinista de la invariancia contractual. No se
ejecuta concurrencia real ni se afirma haber probado MySQL.

## Plan transaccional

`AppointmentMutationPlan` declara exactamente la futura secuencia:

1. `begin_transaction`;
2. `lock_idempotency_key`;
3. `resolve_idempotency`;
4. `lock_appointment`;
5. `lock_slot_scope`;
6. `verify_expected_version`;
7. `verify_lifecycle_transition`;
8. `verify_active_slot_uniqueness`;
9. `persist_appointment`;
10. `append_lifecycle_event`;
11. `persist_idempotency_result`;
12. `commit`.

Ante cualquier fallo declara `rollback`. También declara:

- transaction required: `true`;
- idempotency lock required: `true`;
- appointment lock required: `true`;
- slot lock required: `true`;
- active slot unique constraint required: `true`;
- append event in same transaction: `true`;
- idempotency result in same transaction: `true`.

El plan no importa PDO, no contiene SQL y no ejecuta ninguna operación.

## Locks e índice unique requeridos

Gate 8D documenta como obligaciones futuras un lock de idempotency key, un
lock de cita, un lock del scope de slot y un unique active slot claim. No
implementa `FOR UPDATE` real, no crea el índice unique y no ejecuta
transacciones. La resolución de rangos superpuestos deberá mantenerse bajo el
mismo límite transaccional que cita, evento y record idempotente.

## Eventos append-only y atribución de actores

`AppointmentLifecycleEvent` tiene event ID determinista derivado por hash,
appointment ID, sequence, lifecycle version, estados, operation/correlation,
actor real, actor efectivo, reason code, occurred at, slot key y event type
`appointment_lifecycle_transition`. `sequence` coincide con la siguiente
aggregate version.

El evento es inmutable, append-only, no clínico y minimizado. No contiene PII,
payload libre ni datos clínicos. Replay, transición inválida, version conflict,
slot conflict e idempotency conflict no crean evento.

## Separación Clinical

`agendaAppointmentIsClinicalEncounter() === false`. Una cita de Agenda y un
encuentro clínico son entidades distintas. Gate 8D no crea encounter, nota
médica, estado clínico, apertura/cierre de expediente ni bridge automático con
Clinical. El evento de lifecycle de Agenda no es un evento clínico.

## Fail-closed

La capa distingue al menos:

- `unknown_appointment_state`;
- `invalid_transition`;
- `appointment_mismatch`;
- `state_mismatch`;
- `aggregate_version_conflict`;
- `invalid_idempotency_key`;
- `idempotency_conflict`;
- `invalid_slot`;
- `slot_conflict`;
- `invalid_actor`;
- `invalid_reason`;
- `invalid_timestamp`;
- `invalid_claim`;
- `lifecycle_version_mismatch`.

Ningún error se transforma en allow. Las validaciones de value objects usan
una excepción de dominio tipada; las evaluaciones públicas de la máquina y los
guards producen decisiones tipadas.

## Determinismo

Todas las entradas son argumentos explícitos. Hashes, orden de claims,
event IDs, fingerprints, result digests y claves de slot son reproducibles. No
se usa reloj global, aleatoriedad, UUID, entorno, sesión, red, filesystem o
estado mutable compartido.

## Seguridad y privacidad

La nueva capa no recibe paciente, contacto, motivo clínico libre, cookies,
tokens, credenciales, headers o payloads. Los IDs conflictivos se exponen sólo
como digest. Los records y eventos tienen allow-list cerrada. Las pruebas usan
fixtures sintéticos.

## Compatibilidad legacy

`rescheduled`, `in_progress` y `finished` aparecen en código legacy. No son
estados canónicos de Gate 8D, no se eliminan todavía y no se normalizan
automáticamente dentro de la nueva autoridad. El adapter de compatibilidad
queda pendiente. No existe cutover, dual write ni shadow execution.

El router legacy permanece byte-equivalente. No cambia el comportamiento
actual de `confirm`, `cancel`, `no_show` o `reschedule`.

## Límites de Gate 8D

- No runtime wiring.
- No concurrency claim productiva.
- No persistencia real.
- No `FOR UPDATE` real.
- No índice unique real.
- No transacciones ejecutadas.
- No creación de citas.
- No SQL ni migraciones.
- No reconciliación legacy.
- Compatibility adapter no iniciado.
- Shadow mode no iniciado.
- Dual write no iniciado.
- Runtime wiring: `0`.
- Route behavior changes: `0`.
- SQL: `0`.
- Datos reales: `0`.
- Citas reales: `0`.
- OTP real: `0`.
- Merges reales: `0`.
- AWS writes: `0`.
- Puerto 8091 intacto.

## Pruebas

`modules/agenda/tests/Gate8DAppointmentLifecycleIdempotencyTest.php` cubre
catálogo/matriz, snapshot, inmutabilidad, optimistic version, eventos,
idempotencia, fingerprint, replay/conflict, slots RFC3339/IANA, intervalos
semiabiertos, claims, permutaciones, doble reserva sintética, plan de 12 pasos,
QA estática y byte-equivalencia de Gates 8A–8C.

También se ejecutan las regresiones Gate 8A, Gate 8B, Gate 8C, Gate 6B, Gate
6F, Identity y las dos pruebas exigidas de Subscriptions, además de PHP lint de
todos los archivos nuevos.

## Rollback

Rollback principal:

```sh
git revert --no-edit <gate8d_commit>
```

Retorno alterno de inspección:

```sh
git switch -c recovery/activity08-gate8d \
  196ab0e28b3c4ed73f7caee9306a2d19239af9ae
```

No se ejecuta rollback.

## Puntos seguros

- Programa: `ee625b0b57c0caa623c4b156cfa2734a6881cf85`.
- Gate 8A: `9bb7d8f8ec448edd8a0d77dabd44834b9d1f98af`.
- Gate 8B pre-correction: `1000e5860702212f5303f5095bf0ec901276bae6`.
- Gate 8B postvalidated: `73852741f02a56d943269f45e2153bfe5eb0a03d`.
- Gate 8C pre-correction: `6345b42a8a0170e293347a6f60ce959d39e2be94`.
- Gate 8C postvalidated: `196ab0e28b3c4ed73f7caee9306a2d19239af9ae`.
- Bundle preflight verificado:
  `/tmp/mxmed-activity08-gate8d-preflight-v2/activity08-before-gate8d.bundle`.

Los seis puntos anteriores y el bundle se preservan; la historia no se
reescribe.

## Evidencia

`/tmp/mxmed-activity08-gate8d-appointment-lifecycle-idempotency-v2/` contiene
exactamente diez JSON válidos y tres textos: baseline, archivos, puntos
seguros, catálogo, matriz, idempotencia, slots/concurrencia, mutation plan,
resultados, QA, estado final Git, rollback y ausencia de cambio runtime.

## Git

Gate 8D se entrega en un séptimo commit aditivo y reversible sobre el programa
con mensaje exacto:

`feat(agenda): implementa ciclo de citas e idempotencia gate 8D`

No se usa amend, rebase, squash, force push, merge, cherry-pick ni reset
destructivo. El programa oficial permanece sin integrar y no se crea
checkpoint.

## Estado del programa

- Gate 8D: `COMPLETE`.
- Gate 8E: `NOT_STARTED`.
- Actividad 8: `IN_PROGRESS_GATE_8D_COMPLETE`.
- Actividad 9: `BLOCKED`.
- Contador oficial: `7/22`.
- Readiness: `NO_GO_LEGACY_BLOCKERS_PRESENT`.

No integrar. No crear checkpoint. No iniciar Gate 8E. No iniciar Actividad 9.
