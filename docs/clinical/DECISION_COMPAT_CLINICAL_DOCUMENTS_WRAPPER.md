# DECISIÓN ARQUITECTÓNICA: COMPATIBILIDAD `clinical_documents` LEGACY VS WRAPPER ESTÁNDAR

## 1) Contexto
Hoy existe divergencia contractual en la superficie clínica por evolución histórica del sistema:

- `api/clinical-documents.php` y `api/evolution-note-generate.php` nacieron como endpoints específicos del motor `clinical_documents` con respuestas JSON funcionales, pero sin el wrapper estándar completo.
- `api/agenda/index.php` y `api/patients/index.php` sí operan con contrato estandarizado `{ ok, error, message, data, meta }`.
- La línea de consolidación de `modules/clinical` y sus documentos v1 define normalización contractual como objetivo obligatorio de integración futura.

Esta decisión fija la convivencia sin ruptura y la ruta oficial de transición.

## 2) Estado actual (evidencia repo)

### Endpoints clínicos legacy (no wrapper estándar completo)

1. `api/clinical-documents.php?action=save`
- Forma actual típica OK: `{ "ok": true, "document": { ... } }`
- Forma actual típica error: `{ "ok": false, "error": "..." }` o `{ "ok": false, "errors": [ ... ] }`
- No incluye siempre `message`, `data`, `meta`.

2. `api/clinical-documents.php?action=list&patient_id=...`
- Forma actual típica OK: `{ "ok": true, "items": [ ... ] }`
- Sin `message/data/meta` estándar.

3. `api/clinical-documents.php?action=get&id=...`
- Forma actual típica OK: `{ "ok": true, "document": { ... } }`
- Sin `message/data/meta` estándar.

4. `api/evolution-note-generate.php` (POST)
- Forma actual típica OK: `{ "ok": true, "document_id": 123, "document_uuid": "..." }`
- Forma actual típica error: `{ "ok": false, "error": "..." }` o `{ "ok": false, "errors": [ ... ] }`
- Sin `message/data/meta` estándar.

### Endpoints con wrapper estándar (referencia interna)

1. `api/agenda/index.php`
- Normaliza respuesta a `{ ok, error, message, data, meta }`.

2. `api/patients/index.php`
- Opera con `{ ok, error, message, data, meta }` como contrato base.

## 3) Decisión formal (vinculante)

1. Todo endpoint nuevo del dominio clínico (superficie `api/clinical/index.php`) **debe** usar el wrapper estándar:

```json
{
  "ok": true,
  "error": null,
  "message": "",
  "data": {},
  "meta": {}
}
```

2. `api/clinical-documents.php` queda declarado como **LEGACY FUNCIONAL v1**:
- No se rompe.
- No se elimina en esta etapa.
- Se mantiene para compatibilidad de flujos existentes.

3. `api/evolution-note-generate.php` queda en estado **LEGACY DE TRANSICIÓN**:
- Sigue operativo temporalmente.
- Debe migrar su superficie de consumo a contrato estándar vía capa clínica unificada.

## 4) Política de compatibilidad (decisión v1)

Se evalúan dos opciones:

- Opción A: Adapter en `api/clinical/index.php` que envuelve respuestas legacy al wrapper estándar.
- Opción B: Mantener legacy como excepción permanente y exigir que UI nueva no lo use.

### Opción elegida: **A (Adapter en `api/clinical/index.php`)**

Justificación v1:
- Permite normalizar contrato de cara a nuevos consumidores sin romper integraciones existentes.
- Reduce retrabajo en frontend/API al exponer una única forma de respuesta para desarrollo nuevo.
- Mantiene trazabilidad explícita de qué rutas son legacy y cuáles son estándar.
- Facilita deprecación gradual, controlada y medible.

Regla operativa derivada:
- Desarrollo nuevo clínico consume `api/clinical/index.php` (wrapper estándar).
- Consumo directo de `api/clinical-documents.php` queda solo para compatibilidad temporal documentada.

## 5) Reglas mínimas para errores, status codes y `meta`

### 5.1 Wrapper obligatorio en superficie clínica nueva
Toda respuesta en `api/clinical/index.php` debe incluir:
- `ok`: boolean
- `error`: string|null
- `message`: string
- `data`: any
- `meta`: object|null

### 5.2 Códigos de error semánticos mínimos
- `invalid_params`
- `not_found`
- `conflict`
- `forbidden`
- `internal_error`
- `legacy_upstream_error` (cuando la falla proviene del backend legacy envuelto)

### 5.3 HTTP status mínimos
- `200` OK lectura/actualización exitosa.
- `201` creación exitosa.
- `400` `invalid_params`.
- `403` `forbidden`.
- `404` `not_found`.
- `409` `conflict`.
- `422` validación semántica de dominio.
- `500` `internal_error`.
- `502` `legacy_upstream_error` cuando aplique en adapter.

### 5.4 `meta` mínimo recomendado
- `request_id`
- `timestamp` (ISO 8601)
- `contract_version` (ej. `clinical.v1`)
- `legacy_source` (solo si la respuesta fue adaptada desde endpoint legacy)

## 6) Plan por etapas para migración sin ruptura (FUTURO)

### Paso 1: Adapter contractual (FUTURO)
- Exponer rutas clínicas en `api/clinical/index.php` con wrapper estándar.
- Internamente, cuando corresponda, envolver salida de `clinical-documents`/`evolution-note-generate`.

### Paso 2: Deprecation explícita (FUTURO)
- Documentar rutas legacy como “deprecated”.
- Agregar advertencia contractual (`meta.deprecation`) en respuestas adaptadas.

### Paso 3: Back-compat controlada (FUTURO)
- Mantener endpoints legacy durante ventana definida.
- Monitorear consumo para identificar dependencias activas.

### Paso 4: Retiro de `api/evolution-note-generate.php` (FUTURO)
- Cuando todo consumo pase por `api/clinical/index.php`, retirar endpoint legacy.
- `api/clinical-documents.php` se mantiene o se reemplaza por ruta estándar según adopción real.

Criterio transversal:
- Cero ruptura sobre Agenda/Waitlist y sobre flujos clínicos actualmente productivos.

## 7) Fuera de alcance (explícito)
- Reescritura total inmediata de `clinical_documents`.
- Cambios de esquema DB del motor documental en esta decisión.
- Implementación de adapter/endpoints en este documento.
- Cambios de UI en esta etapa.
- Estrategia de versionado mayor (v2/v3) y sunset definitivo con fecha cerrada.

---

**Declaración vinculante:** desde esta decisión, la convivencia se rige por “legacy funcional + adapter estándar obligatorio para desarrollo nuevo”, hasta completar la migración por etapas FUTURO sin ruptura operativa.
