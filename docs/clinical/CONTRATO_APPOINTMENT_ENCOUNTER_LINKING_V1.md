# CONTRATO APPOINTMENT ↔ ENCOUNTER LINKING V1

## 1) Contexto
Camino 2 del Historial clínico embebido incluye el CTA **"Ver episodio"** desde cards `item_type=appointment`.

El objetivo funcional es abrir el encounter más reciente asociado al `appointment_id` de ese item.

## 2) Evidencia observada
Durante Camino 2 se observó el siguiente desacople:

- En UI, algunos `appointment` del timeline muestran `encounter_key` con forma `appt:<idA>` (ejemplo: `appt:ae4d...`).
- En API `GET /patients/{patient_id}/encounters`, el `appointment_id` canónico asociado al encounter es distinto (ejemplo: `fe61cdd...`).
- Resultado: la UI no puede correlacionar de forma confiable `latestEncByAppt`, por lo que el CTA de episodio no debe forzarse.

## 3) Definición de ID canónico y contrato
### 3.1 ID canónico de appointment
`appointment_id` es el identificador canónico de cita que debe usarse para correlación cross-endpoint.

### 3.2 Regla de consistencia (obligatoria)
Todo `timeline item_type=appointment` debe incluir:

- `links.appointment_id = <appointment_id canónico>`

y este valor debe ser consistente con:

- `GET /patients/{patient_id}/encounters[].appointment_id`

## 4) Regla UI (fallback seguro)
Si un `appointment` del timeline **no** trae `links.appointment_id` canónico (o no es correlacionable), la UI debe:

- mantener estado **"Sin episodio"**
- no inventar `encounter_key`
- no generar links especulativos a `encounter.php`

## 5) Plan de validación (curl)
Validar correlación entre timeline y encounters para el mismo paciente:

```bash
curl -s "http://127.0.0.1:8091/api/clinical/index.php/patients/<PATIENT_ID>/timeline?include=agenda,clinical&limit=20"
curl -s "http://127.0.0.1:8091/api/clinical/index.php/patients/<PATIENT_ID>/encounters?limit=100"
```

Criterio esperado:

- Para cada item `item_type=appointment` en timeline, `links.appointment_id` debe existir.
- Ese valor debe encontrarse en al menos un `encounters[].appointment_id` cuando exista episodio clínico asociado.

## 6) Alcance de fase
Este Step 21 es **solo documentación/contrato**.

- No reabre ni modifica Steps 18–20.
- No implica cambios de API ni UI en esta fase.
