# Cierre P13 — Quick Reference Operativo

Tag de cierre: `mxmed-p13-encounter-payload-v1`  
Commit de referencia: `4fb31d0`

## Qué problema resolvió P13

- Antes: había `encounter_key` activo, pero no se precargaba automáticamente el detalle del encounter en host.
- Ahora:
  - se precarga el payload con `GET /api/clinical/index.php/encounters/{encounter_key}`
  - se conserva en memoria el último JSON (`lastEncounterPayload`)

## Integración aplicada (host)

- Archivo: `assets/js/app.js` (solo host).
- Fuente de key:
  - `window.getActiveEncounterKey()`
- Eventos que disparan sincronización/carga:
  - `encounter:active`
  - `mxmed:encounter-changed`

## Hooks de QA

- `window.mxmedDebug.getEncounterKey()`
- `window.mxmedDebug.getEncounterPayload()`

## Validación rápida (Safari DevTools)

- En `Network`, abrir expediente y confirmar:
  - `GET /api/clinical/index.php/encounters/<encounter_key_url_encoded>`
  - respuesta `status 200` con `ok:true`
- En `Console`, confirmar log:
  - `[P13] Encounter payload loaded ...`
- En `Console`, confirmar hook:
  - `window.mxmedDebug.getEncounterPayload()` devuelve el último JSON cargado.
