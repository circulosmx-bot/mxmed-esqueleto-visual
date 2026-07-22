# QA/MXMed-PG03-Activity08-Gate8D-PP307-HashStability-Correction-01

## Resultado

`PASS_ACTIVITY_8_GATE_8D_PP307_HASH_STABILITY_CORRECTED`

## Clasificación

`UI-0`. Corrección exclusiva del arnés QA. No modifica dominio, runtime,
rutas, persistencia, interfaz o Plan Maestro.

## Baseline

- Rama: `feature/mxmed-pg03-agenda-foundations-v2`.
- Worktree: `/Users/circulodigital/Documents/GitHub/mxmed-esqueleto-visual-activity08-v2`.
- Programa oficial sin integrar: `ee625b0b57c0caa623c4b156cfa2734a6881cf85`.
- Gate 8C postvalidado: `196ab0e28b3c4ed73f7caee9306a2d19239af9ae`.
- Gate 8D implementado: `fe9e22b37f6db3daa8e231f3dfb97ab0765d86e3`.
- Arnés acumulativo corregido: `7c668d737e30a451daba2d3ece2033570ff27090`.
- HEAD inicial: `7c668d737e30a451daba2d3ece2033570ff27090`.
- Ocho commits sobre el programa al iniciar esta corrección.

## Diagnóstico

Gate 8D extrae PP-307 con una expresión acumulativa que termina ante cualquier
PP posterior o el fin del archivo:

```text
/### PP-307 .*?(?=### PP-[0-9]+ —|\z)/s
```

Cuando PP-307 es el último bloque, la captura mide 1737 bytes y termina con un
salto de línea. Cuando se agrega PP-308 con el separador Markdown habitual, la
captura mide 1738 bytes y termina con dos saltos de línea. El segundo salto no
pertenece al contenido interno de PP-307; es el separador previo al bloque
posterior.

Resultados reproducidos:

- `PP307_ORIGINAL_MATCH_BYTES=1737`.
- `PP307_SYNTHETIC_MATCH_BYTES=1738`.
- `PP307_ORIGINAL_TRAILING_NEWLINES=1`.
- `PP307_SYNTHETIC_TRAILING_NEWLINES=2`.
- Hash crudo original:
  `9b8fcb0498d2c764fc8e39d1f7a2d6d5bb2a1bb1b00cbdd938e9e653a7420b60`.
- Hash crudo futuro:
  `759d8069827ec691774e1c1fc40e5fa769d19084261a5ace7f3bede91daf63d2`.
- Hash normalizado de ambas capturas:
  `9b8fcb0498d2c764fc8e39d1f7a2d6d5bb2a1bb1b00cbdd938e9e653a7420b60`.

## Causa raíz

La expresión acumulativa incluye los saltos de línea situados entre el final
semántico de PP-307 y el encabezado siguiente. El hash crudo trataba ese
separador externo como contenido contractual. Así, una PP posterior podía
romper Gate 8D sin cambiar un solo carácter interno de PP-307.

## Estrategia de normalización

La representación canónica se obtiene exclusivamente así:

1. retirar todos los caracteres `\r` y `\n` terminales de la captura;
2. agregar exactamente un `\n`;
3. calcular SHA-256 sobre ese valor.

La implementación equivalente es:

```php
$pp307Normalized = rtrim($pp307[0], "\r\n") . "\n";
```

El hash esperado no cambia:
`9b8fcb0498d2c764fc8e39d1f7a2d6d5bb2a1bb1b00cbdd938e9e653a7420b60`.

## Límites exactos y preservación interna

La normalización sólo tolera `\r` y `\n` al final de la captura. No normaliza:

- espacios internos o iniciales;
- texto, encabezados o puntuación;
- sangría;
- saltos de línea internos;
- orden;
- contenido semántico.

Una mutación de carácter, eliminación de línea o alteración de espacios dentro
de PP-307 continúa cambiando el hash y falla con `PP-307 byte-equivalent`. Una
PP-307 duplicada falla por el conteo exacto y una ausente falla con
`PP-307 block present`.

## Simulación PP-308 antes y después

Gate 8C pasa con una PP-308 sintética porque su arnés acumulativo protege sólo
hasta PP-306. Antes de esta corrección, Gate 8D falla con PP-308 y un separador
Markdown real por `FAIL_PP307_SEPARATOR_HASH`. Después de normalizar únicamente
los saltos terminales, Gate 8D pasa con la misma PP-308 sintética.

La simulación se realiza en un worktree detached, sin commit. PP-308 no se crea
en la rama y el Plan Maestro real permanece intacto.

## Evidencia anterior executionally invalidated

La evidencia histórica en
`/tmp/mxmed-activity08-gate8d-regression-harness-correction-v2/` afirmó que Gate
8D pasaba con PP-308 sintética, pero la reproducción ejecutable con el
separador real demuestra lo contrario. Esa afirmación queda
`executionally invalidated`.

Hashes protegidos de la evidencia anterior:

- `future-pp-simulation-audit.json`:
  `648eb9b4e9661e9ce273874f33f51e454aeae55d95a34e47c3701774a55f6f41`.
- `qa-result.json`:
  `7ebda8fa41e72110c740406754bdfdbe3221cec55a944b4b7daa4bf732969907`.

La evidencia anterior permanece sin modificación. No se corrige
retroactivamente. La nueva evidencia sustituye únicamente su afirmación falsa
sobre la simulación PP-308 y conserva el historial de diagnóstico.

## Nueva evidencia correctiva

La evidencia vigente se entrega en
`/tmp/mxmed-activity08-gate8d-pp307-hash-stability-correction-v2/`, con ocho JSON
válidos y tres textos. Registra hashes crudos/normalizados, simulación positiva,
mutaciones negativas, regresiones, safe return, rollback y estado Git.

## Scope exacto

Se cambian exactamente dos archivos versionados:

1. modificación de
   `modules/agenda/tests/Gate8DAppointmentLifecycleIdempotencyTest.php`;
2. creación de
   `docs/MXMED_CORRECCION_V2_PG03_GATE_8D_HASH_PP307_ESTABLE.md`.

Gate 8C test, Plan Maestro, PP-304, PP-305, PP-306 y PP-307 permanecen
byte-equivalentes. No se crea PP-308.

## Dominio y superficies preservadas

Gate 8D de dominio no cambia. Permanecen byte-equivalentes:

- `modules/agenda/appointments/**`;
- `modules/agenda/contracts/**`;
- `modules/agenda/security/**`;
- `modules/agenda/availability/**`;
- prueba Gate 8C;
- documento original Gate 8D;
- documento de corrección acumulativa anterior;
- Plan Maestro.

PP-307 permanece byte-equivalente y PP-308 real está ausente.

## Pruebas y simulaciones negativas

Las nueve regresiones obligatorias pasan desde el mismo HEAD. Gate 8D también
pasa PHP lint.

Las copias temporales no versionadas comprueban:

- carácter interno mutado: rechazado;
- línea interna eliminada: rechazada;
- espacio interno alterado: rechazado;
- encabezado PP-307 duplicado: rechazado por conteo;
- variante terminal CR/LF: permitida;
- PP-308 sintética: Gate 8C y Gate 8D pasan.

No se crean commits en las simulaciones.

## Ausencia de cambios productivos

- Runtime wiring: `0`.
- Route behavior changes: `0`.
- SQL: `0`.
- Datos reales: `0`.
- OTP real: `0`.
- Citas reales: `0`.
- Merges reales: `0`.
- AWS writes: `0`.
- Servidores candidatos: `0`.

## Safe return y bundle

- Programa: `ee625b0b57c0caa623c4b156cfa2734a6881cf85`.
- Gate 8C: `196ab0e28b3c4ed73f7caee9306a2d19239af9ae`.
- Gate 8D implementado: `fe9e22b37f6db3daa8e231f3dfb97ab0765d86e3`.
- Arnés corregido: `7c668d737e30a451daba2d3ece2033570ff27090`.
- Bundle preflight:
  `/tmp/mxmed-activity08-gate8d-pp307-hash-stability-correction-preflight-v2/activity08-before-gate8d-pp307-hash-stability.bundle`.

## Rollback

Rollback principal futuro:

```sh
git revert --no-edit <pp307_hash_stability_commit>
```

Retorno alterno:

```sh
git switch -c recovery/activity08-gate8d-pp307-hash-stability \
  7c668d737e30a451daba2d3ece2033570ff27090
```

El dry-run se ejecuta sólo en worktree detached y debe producir exactamente el
árbol del arnés anterior, sin commit de rollback.

## Git

La corrección se entrega como noveno commit independiente, aditivo y reversible
sobre el programa con mensaje exacto:

`fix(agenda): estabiliza hash PP-307 gate 8D`

No se usa amend, rebase, squash, force push, merge, cherry-pick ni reset
destructivo. El programa oficial permanece sin integrar y no se crea
checkpoint.

## Estado final

- Gate 8D: `IMPLEMENTED_READY_FOR_FINAL_POSTVALIDATION`.
- Gate 8E: `NOT_STARTED`.
- Actividad 8: `IN_PROGRESS`.
- Actividad 9: `BLOCKED`.
- Contador: `7/22`.
- Readiness: `NO_GO_LEGACY_BLOCKERS_PRESENT`.

No integrar. No crear checkpoint. No iniciar Gate 8E. No iniciar Actividad 9.
