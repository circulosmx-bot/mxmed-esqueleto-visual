# DECISIÓN: APPOINTMENT_ID CANÓNICO = AGENDA (V1)

## 1) Decisión
**Fuente de verdad del `appointment_id` canónico = módulo Agenda.**

Clinical debe consumir y propagar ese mismo ID para correlación entre citas (appointment) y episodios clínicos (encounter).

## 2) Motivación
- Evitar duplicidad o divergencia de IDs entre Agenda y Clinical.
- Permitir correlación confiable de episodios desde citas en UI (por ejemplo, CTA "Ver episodio").
- Reducir ambigüedad operativa al depurar timeline/encounters cross-módulo.

## 3) Reglas de integración
### 3.1 Timeline
En `item_type=appointment`, el payload debe exponer:

- `links.appointment_id = appointment_id canónico (Agenda)`

### 3.2 Encounters
En `GET /patients/{patient_id}/encounters`, cada row debe exponer:

- `appointment_id` con el mismo valor canónico de Agenda

### 3.3 Correlación
La correlación `appointment ↔ encounter` se considera válida únicamente cuando ambos endpoints comparten el mismo `appointment_id` canónico.

## 4) Compatibilidad / migración
Si existen encounters con `appointment_id` alterno/no canónico:

- se requiere bridge o migración de datos,
- **no** se implementa en este Step 22.

## 5) Plan de implementación posterior (no ejecutar en este step)
- Ajustar puntos de escritura donde Clinical persiste `appointment_id` en encounters/documentos.
- Alinear generación de items en timeline para propagar siempre `links.appointment_id` canónico.
- Validar correlación con pruebas:
  - `curl` timeline + encounters para mismo paciente
  - `bash modules/clinical/qa/clinical_encounters_smoke.sh`
  - `bash modules/clinical/qa/embed_contract_check.sh`

## 6) Alcance de Step 22
Step 22 es **solo documentación/decisión arquitectónica**.

- No tocar API.
- No tocar UI.
- No reabrir fases previas.
