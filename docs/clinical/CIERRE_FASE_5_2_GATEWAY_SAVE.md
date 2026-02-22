# CIERRE FASE 5.2 — CONSOLIDACIÓN SAVE AL GATEWAY

## 1. Contexto
- Proyecto: México Médico (MXMed)
- Rama: `feature/agenda-v1-ready`
- Fase 5: Identidad canónica + migración progresiva al gateway clínico.
- En Fase 5.1 se migró LIST al gateway con fallback legacy.
- En Fase 5.2 se consolida SAVE con estrategia gateway-first.

## 2. Objetivo de Fase 5.2
Migrar el guardado de documentos clínicos al endpoint:
`POST /api/clinical/index.php/documents`

Sin:
- Cambiar el shape del payload legacy.
- Romper compatibilidad hospitalaria.
- Romper localStorage.
- Eliminar legacy.

## 3. Estrategia implementada

### 3.1 Frontend (app.js y manejo-hospitalario.js)
- SAVE ahora es gateway-first.
- Flujo:
  1. Se construye legacy `patient_id`.
  2. Se intenta resolver canonical UUID.
  3. Si canonical existe:
     - Se clona el payload legacy.
     - Se reemplaza `context.patient_id` por UUID canónico.
     - Se agrega `context.legacy_patient_id` (auditoría).
     - Se hace `POST` al gateway.
  4. Si falla o canonical no existe:
     - Fallback automático a `api/clinical-documents.php?action=save`
- Logs de diagnóstico:
  - `SAVE gateway attempt`
  - `SAVE gateway ok`
  - `SAVE fallback legacy`

### 3.2 Backend (clinical gateway)
- Se implementó `POST /documents`.
- Acepta el mismo payload legacy (passthrough).
- Guarda en:
  - `clinical_documents`
  - `clinical_document_participants`
- Respuesta estándar:
  `{ ok, error, message, data, meta }`

## 4. Validación realizada

### 4.1 Console
Confirmación de:
- `SAVE gateway attempt`
- `SAVE gateway ok`

### 4.2 Curl
Health:
`GET /api/clinical/index.php/health`

Save:
`POST /api/clinical/index.php/documents`

List:
`GET /api/clinical/index.php/documents?patient_id=UUID`

### 4.3 Confirmación de almacenamiento
Documentos guardados visibles vía LIST gateway.
Source: `clinical_documents_pdo`.

## 5. Compatibilidad preservada
- Legacy endpoint intacto.
- Fallback funcional.
- No se modificó contrato JSON legacy.
- No se eliminó `clinical-documents.php`.

## 6. Rollback
Revertir commit:
`84e3b68 feat(clinical): gateway-first document save...`

`git revert 84e3b68`

Sistema regresaría a SAVE legacy.

## 7. Estado actual del sistema
- LIST: gateway-first.
- SAVE: gateway-first.
- Legacy: aún operativo.
- Sistema estable.

## 8. Siguiente fase (no incluida aquí)
FASE 6:
Eliminación progresiva de `clinical-documents.php` legacy.

Documento de cierre formal de Fase 5.2.
