# Cierre P11 — Quick Reference Operativo

Tag de cierre: `mxmed-p11-ensure-active-encounter-v1`

## Qué problema resolvió P11

- Antes: al abrir expediente podían existir estados sin `encounter` activo, generando contexto clínico incompleto.
- Ahora: al abrir expediente desde botón `[data-pid]` se garantiza un `encounter` activo para el paciente.

## Flujo aplicado (host)

1. `GET /api/clinical/index.php/patients/{pid}/encounters/active`
2. Si la respuesta viene con `data=null`, ejecutar:
   - `POST /api/clinical/index.php/patients/{pid}/encounters`
   - Payload:
```json
{
  "status": "open",
  "encounter_dt": "YYYY-MM-DD HH:MM:SS"
}
```

## Eventos emitidos

- `encounter:active` con `detail: { patient_id, encounter_key }`
- `mxmed:encounter-changed` con `detail: { patient_id, encounter_key }`

## Keys de store usadas

- `mxmedStore.activePatientId`
- `mxmedStore.activeEncounterKey`

## Validación rápida en Safari DevTools

- En `Network`, abrir expediente (`[data-pid]`) y confirmar secuencia: `GET .../encounters/active`; si no hay activa, `POST .../encounters` con `status=open` y `encounter_dt`.
- En `Network`, validar que el request incluye header `x-user-id` (shim activo) y que el `POST` responde `ok:true` con `encounter_key`.
- En `Console`, verificar que después de abrir expediente se actualizan `mxmedStore.activePatientId` y `mxmedStore.activeEncounterKey`, y que se despachan los eventos de cambio de encounter.
