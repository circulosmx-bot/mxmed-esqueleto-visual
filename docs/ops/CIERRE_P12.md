# Cierre P12 — Quick Reference Operativo

Tag de cierre: `mxmed-p12-context-bridge-v1`  
Commit de referencia: `55a9369`

## Qué problema resolvió P12

- Antes: distintos módulos podían depender de fuentes dispersas para resolver el `encounter_key` activo.
- Ahora: el `encounter_key` activo se propaga con fuente única en frontend host:
  - `window.mxmedStore.activeEncounterKey`

## Bridge implementado

- Helpers globales:
  - `window.getActiveEncounterKey()`
  - `window.setEncounterContextOnPane(encounterKey, patientId)`
- Eventos escuchados:
  - `encounter:active`
  - `mxmed:encounter-changed`
- Sincronización resultante:
  - Store: `mxmedStore.activeEncounterKey`
  - Pane expediente (dataset): `data-encounter-key`, `data-active-encounter-key`

## Integración mínima de consumo

- `nota_evolucion` agrega `encounter_key` al `context` de guardado.
- Si no hay `encounter_key` activo:
  - warning controlado
  - aborta acción de guardado (sin tronar UI)

## Validación rápida (Safari DevTools)

- Abrir expediente y confirmar en `Console`:
  - `window.mxmedDebug.getEncounterKey()` devuelve valor no vacío (ejemplo validado: `"enc:18"`).
- Verificar en `Elements` sobre `#p-expediente`:
  - presencia de `data-encounter-key` y `data-active-encounter-key`.
- Verificar en `Console`/store:
  - `window.mxmedStore.activeEncounterKey` coincide con dataset del pane.
