# QA/MXMed-PG03-Activity08-Gate8D-CumulativeRegressionHarness-Correction-01

## Resultado

`PASS_ACTIVITY_8_GATE_8D_REGRESSION_HARNESS_CORRECTED`

## Clasificación

`UI-0`. Esta corrección modifica exclusivamente el arnés QA acumulativo. No
cambia el dominio de Gate 8D, comportamiento productivo, rutas, persistencia o
interfaz.

## Baseline

- Rama: `feature/mxmed-pg03-agenda-foundations-v2`.
- Worktree: `/Users/circulodigital/Documents/GitHub/mxmed-esqueleto-visual-activity08-v2`.
- Programa oficial sin integrar: `ee625b0b57c0caa623c4b156cfa2734a6881cf85`.
- Gate 8C postvalidado: `196ab0e28b3c4ed73f7caee9306a2d19239af9ae`.
- HEAD inicial obligatorio: `fe9e22b37f6db3daa8e231f3dfb97ab0765d86e3`.
- Siete commits sobre el programa al iniciar la corrección.

## Diagnóstico confirmado

En el HEAD de Gate 8D antes de esta corrección,
`Gate8DAppointmentLifecycleIdempotencyTest` pasa y
`Gate8CCanonicalScheduleAvailabilityTest` falla. Gate 8C pasa en su baseline
postvalidado, por lo que la falla no pertenece al dominio de disponibilidad ni
al dominio de lifecycle/idempotencia.

La falla Gate 8C en el HEAD Gate 8D se reproduce en el assert que exige a la
vez PP-306 única y PP-307 ausente. PP-307 fue creada legítimamente por Gate 8D.
Gate 8D, a su vez, congelaba mediante hash la versión histórica de la prueba
Gate 8C y exigía la ausencia de PP-308.

## Causa raíz

El arnés confundía preservación del contrato propio con ausencia de evolución
posterior. Una prueba histórica protegía el espacio de numeración del gate
siguiente mediante un forward-negative guard, en vez de proteger sólo su
propio bloque y ejecutar los gates anteriores como regresiones.

## Forward-negative guard PP-307

Gate 8C comprobaba que PP-307 estuviera ausente. Este guard hacía imposible
que Gate 8C pasara en el HEAD acumulado legítimo de Gate 8D. La condición se
retira sin reducir validaciones de catálogo, disponibilidad, hashes PP-304,
PP-305 o PP-306.

## Forward-negative guard PP-308

Gate 8D comprobaba que PP-308 estuviera ausente. Esa condición habría roto el
arnés al iniciar legítimamente Gate 8E. Se retira; Gate 8D no exige presencia
ni ausencia de PP futuras.

## Regla de arnés acumulativo

Cada test protege las decisiones y bloques de su propio gate y de contratos
previos ya establecidos. Los gates posteriores pueden agregar PP nuevas. La
integridad de un test anterior corregido se valida ejecutándolo desde el mismo
HEAD acumulado, no inmovilizando para siempre su archivo mediante un hash en
el test del gate siguiente.

- Gate 8C protege hasta PP-306 y permite PP posteriores.
- Gate 8D protege hasta PP-307 y permite PP posteriores.
- Los asserts siguen siendo obligatorios; ninguno se convierte en warning.

## Corrección Gate 8C

`Gate8CCanonicalScheduleAvailabilityTest.php` extrae PP-306 con:

```text
/### PP-306 .*?(?=### PP-[0-9]+ —|\z)/s
```

Comprueba PP-304, PP-305 y PP-306 byte-equivalentes, además de PP-306
exactamente una vez. Ya no contiene ninguna condición sobre PP-307, PP-308 o
gates futuros. El resto de sus pruebas funcionales y contractuales permanece
sin cambios.

## Corrección Gate 8D

`Gate8DAppointmentLifecycleIdempotencyTest.php` deja de congelar mediante hash
el archivo de prueba Gate 8C. Los hashes de router, contratos Gate 8A y demás
superficies productivas protegidas permanecen.

Gate 8D extrae PP-307 con:

```text
/### PP-307 .*?(?=### PP-[0-9]+ —|\z)/s
```

Comprueba PP-304, PP-305, PP-306 y PP-307 byte-equivalentes, y PP-307
exactamente una vez. No impone condiciones sobre PP-308 o cualquier PP futura.

## Hashes de bloques

- PP-306 SHA-256:
  `30501ff147af8d92266893b048d01616419208d88d9e7bad22895790de34f444`.
- PP-307 SHA-256 calculado antes de modificar las pruebas:
  `9b8fcb0498d2c764fc8e39d1f7a2d6d5bb2a1bb1b00cbdd938e9e653a7420b60`.

PP-307 permanece byte-equivalente.

## Razón para retirar el hash de la prueba Gate 8C

La prueba Gate 8C es un artefacto QA, no una superficie productiva. Una
corrección legítima de su arnés debe poder realizarse sin romper Gate 8D. Su
integridad funcional se comprueba ejecutándola como regresión en el mismo HEAD.
Gate 8D conserva los hashes de contratos y código productivo que sí deben
permanecer inmutables.

## Preservación de contratos y dominio

Permanecen byte-equivalentes todos los PHP de:

- `modules/agenda/appointments/**`;
- `modules/agenda/contracts/**`;
- `modules/agenda/security/**`;
- `modules/agenda/availability/**`.

También permanecen byte-equivalentes el Plan Maestro y el documento original
de implementación Gate 8D. Esta corrección no cambia Gate 8D de dominio.

## Simulación temporal de PP-308

Después del commit correctivo se usa un worktree detached y no versionado para
agregar una única cabecera sintética
`### PP-308 — Simulación temporal de compatibilidad futura` al final de una
copia de Plan Maestro. Gate 8C y Gate 8D pasan con esa adición. La simulación
no crea commit, se elimina al finalizar y no modifica el Plan real.

PP-308 no fue creada en la rama. La simulación PP-308 es temporal y no
versionada; no autoriza iniciar Gate 8E.

## Regresiones

Desde el mismo HEAD corregido pasan Gate 8A, Gate 8B, Gate 8C, Gate 8D, Gate
6B, Gate 6F, Identity y las dos regresiones exigidas de Subscriptions. Gate 8C
ya no se ejecuta únicamente desde su baseline.

## Scope exacto

Se modifican exactamente dos archivos y se crea uno:

1. `modules/agenda/tests/Gate8CCanonicalScheduleAvailabilityTest.php`;
2. `modules/agenda/tests/Gate8DAppointmentLifecycleIdempotencyTest.php`;
3. `docs/MXMED_CORRECCION_V2_PG03_GATE_8D_ARNES_REGRESION_ACUMULATIVO.md`.

No se modifica Plan Maestro. No se crea PP nueva.

## Ausencia de cambios productivos

- Runtime wiring: `0`.
- Cambio de rutas: `0`.
- SQL: `0`.
- Datos reales: `0`.
- Citas reales: `0`.
- OTP real: `0`.
- Merges reales: `0`.
- AWS writes: `0`.
- Servidores candidatos: `0`.

## Safe return y bundle preflight

- Programa: `ee625b0b57c0caa623c4b156cfa2734a6881cf85`.
- Gate 8C postvalidado: `196ab0e28b3c4ed73f7caee9306a2d19239af9ae`.
- Gate 8D implementado:
  `fe9e22b37f6db3daa8e231f3dfb97ab0765d86e3`.
- Bundle verificado:
  `/tmp/mxmed-activity08-gate8d-regression-harness-correction-preflight-v2/activity08-before-gate8d-harness-correction.bundle`.

## Rollback

Rollback principal futuro:

```sh
git revert --no-edit <gate8d_harness_correction_commit>
```

Retorno alterno:

```sh
git switch -c recovery/activity08-gate8d-harness-correction \
  fe9e22b37f6db3daa8e231f3dfb97ab0765d86e3
```

El rollback no se ejecuta sobre la rama real. El ensayo detached confirma que
revertir el commit correctivo reproduce exactamente el árbol de Gate 8D
implementado.

## Git

La corrección se entrega como octavo commit independiente, aditivo y reversible
sobre el programa, con mensaje exacto:

`fix(agenda): corrige arnes acumulativo gate 8D`

No se usa amend, rebase, squash, force push, merge, cherry-pick ni reset
destructivo. El programa oficial permanece sin integrar y no se crea
checkpoint.

## Estado final

- Gate 8D: `IMPLEMENTED_READY_FOR_POSTVALIDATION`.
- Gate 8E: `NOT_STARTED`.
- Actividad 8: `IN_PROGRESS`.
- Actividad 9: `BLOCKED`.
- Contador oficial: `7/22`.
- Readiness: `NO_GO_LEGACY_BLOCKERS_PRESENT`.

No integrar. No crear checkpoint. No iniciar Gate 8E. No iniciar Actividad 9.
