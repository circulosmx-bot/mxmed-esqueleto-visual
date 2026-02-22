# DECISIÓN ARQUITECTÓNICA VINCULANTE: IDENTITY BRIDGE DE PATIENT_ID (LEGACY -> CANONICAL)

## 1) Contexto y problema
El sistema clínico actual convive con dos formas de identificar paciente:

- Identidad canónica del dominio Pacientes:
  - `modules/patients/db/ready_schema.sql` define `patients_patients.patient_id` como PK (`VARCHAR(64)`).
- Identidad legacy en UI clínica:
  - En `assets/js/manejo-hospitalario.js` y `docs/assets/js/manejo-hospitalario.js` se genera `patient_id` a partir de `nombre|YYYY-MM-DD|sexo` (normalizado en frontend).
- Persistencia documental actual:
  - `api/_lib/clinical_documents.php` crea `clinical_documents.patient_id VARCHAR(128) NOT NULL` sin FK a `patients_patients`.

Problema:
- Esta coexistencia permite IDs paralelos para el mismo paciente.
- La ausencia de FK en `clinical_documents` facilita compatibilidad, pero también riesgo de divergencia.
- La decisión canónica ya vigente (`docs/clinical/DECISION_FUENTES_DE_VERDAD.md`) exige una sola fuente de identidad de paciente.

## 2) Definiciones
### 2.1 `canonical_patient_id`
ID oficial y único de paciente en el sistema.

- Fuente de verdad: `patients_patients.patient_id`.
- Dominio dueño: `modules/patients`.
- Es el único ID válido para nuevas integraciones clínicas.

### 2.2 `legacy_patient_key`
Clave histórica de compatibilidad proveniente de UI clínica antigua.

- Forma lógica legacy: `nombre|YYYY-MM-DD|sexo`.
- Puede llegar normalizada por frontend y no conservar delimitadores literalmente.
- No es identidad canónica.
- Debe tratarse como señal transicional para resolución a `canonical_patient_id`.

## 3) Decisión formal (obligatoria)
Se establece de manera vinculante:

1. El identificador canónico de paciente es **exclusivamente** `patients_patients.patient_id`.
2. `legacy_patient_key` se tolera solo como compatibilidad temporal de transición.
3. Queda prohibido introducir nuevas fuentes de identidad paralelas para paciente.
4. Todo diseño nuevo en dominio clínico debe operar en escritura con `canonical_patient_id`.

## 4) Reglas de compatibilidad v1 (sin romper)
Para preservar continuidad operativa durante transición:

1. `clinical_documents` mantiene compatibilidad de lectura/escritura con valores legacy mientras exista deuda histórica.
2. Todo desarrollo clínico nuevo (DB/API/UI) debe resolver primero a `canonical_patient_id` antes de persistir.
3. Si llega input legacy y no hay resolución directa, se permite creación mínima de paciente en dominio canónico para obtener `canonical_patient_id`.
4. Ninguna ruta nueva debe depender de `legacy_patient_key` como identificador primario.

## 5) Mecanismo de resolución (descrito, no implementado)
Se define el componente lógico `PatientIdResolver`.

## 5.1 Contrato conceptual
Entrada:
- `patient_ref` (puede ser `canonical_patient_id` o `legacy_patient_key`).

Salida:
- `canonical_patient_id` (obligatorio).
- `resolution_mode` (`already_canonical | mapped_from_legacy | created_minimal_patient`).
- `resolution_meta` (trazabilidad mínima, sin exponer PII innecesaria).

## 5.2 Flujo de resolución
1. Detectar tipo de entrada:
   - Si ya corresponde a `patients_patients.patient_id`, devolver directo.
   - Si es legacy, iniciar mapeo.
2. Intentar mapeo legacy -> paciente existente:
   - Buscar coincidencias por atributos disponibles de paciente en dominio canónico (con normalización compatible).
3. Si no existe match confiable:
   - Crear paciente mínimo en `modules/patients`.
   - Retornar nuevo `canonical_patient_id`.
4. Registrar metadatos de resolución para auditoría.

## 6) Persistencia y trazabilidad
Objetivo: auditar transición sin duplicar PII.

1. Campo principal de identidad clínica objetivo:
   - `clinical_documents.patient_id` debe converger a `canonical_patient_id`.
2. Donde conservar rastreo de legado (temporal):
   - En `payload_json` (context/meta interno del documento), no en nuevas columnas de identidad.
3. Recomendación de trazabilidad mínima:
   - `identity_bridge.input_type`
   - `identity_bridge.resolution_mode`
   - `identity_bridge.legacy_patient_key_hash` (preferente)
   - `identity_bridge.resolved_at`
4. Reglas de privacidad:
   - Evitar guardar múltiples copias de nombre/fecha/sexo solo para puente.
   - Si se requiere referencia del legado, preferir hash del key legacy sobre valor crudo.

## 7) Plan de migración por etapas (Paso 1..4)
## Paso 1: Congelar criterio de identidad
Acción:
- Declarar oficialmente canónico `patients_patients.patient_id` para todo nuevo alcance clínico.

Cierre:
- Documentación técnica alineada y aprobada.
- Nuevos contratos clínicos definen `canonical_patient_id` como obligatorio en escritura.

## Paso 2: Resolución en writes nuevos (compatibilidad activa)
Acción:
- Toda entrada clínica nueva pasa por `PatientIdResolver`.
- Se conserva compatibilidad de entrada legacy en borde, pero se persiste ID canónico.

Cierre:
- Nuevos registros clínicos ya no crean IDs legacy en campo principal `patient_id`.
- Trazabilidad de resolución disponible en `payload_json`.

## Paso 3: Backfill de históricos
Acción:
- Migrar registros históricos de `clinical_documents` con `patient_id` legacy a `canonical_patient_id`.
- Mantener evidencia de mapeo para auditoría.

Cierre:
- Históricos legacy migrados según criterio definido.
- Reporte de excepciones sin resolver documentado.

## Paso 4: Endurecimiento
Acción:
- Deshabilitar uso de legacy como identidad primaria en rutas clínicas nuevas.
- Legacy queda solo para lectura histórica controlada, no para crear identidad.

Cierre:
- Todos los writes clínicos usan canónico.
- No existen nuevos documentos con `patient_id` legacy en campo principal.

## 8) Fuera de alcance (FUTURO)
Queda explícitamente fuera de alcance en esta decisión:

- Implementación técnica del `PatientIdResolver`.
- Scripts SQL de migración/backfill.
- Cambios de código en endpoints o UI.
- Nuevas tablas de mapeo, colas o procesos batch automatizados.
- Reglas avanzadas de deduplicación probabilística de identidad.

---

## Evidencia de repo usada para esta decisión
- `api/_lib/clinical_documents.php`: `clinical_documents.patient_id VARCHAR(128) NOT NULL`, sin FK a `patients_patients`.
- `assets/js/manejo-hospitalario.js`: construcción de `patient_id` legacy desde `nombre|dob|sexo` (normalizado).
- `docs/assets/js/manejo-hospitalario.js`: mismo patrón legacy.
- `modules/patients/db/ready_schema.sql`: tabla canónica `patients_patients` con PK `patient_id`.
- `docs/clinical/DECISION_FUENTES_DE_VERDAD.md`: decisión canónica previa de fuentes de verdad.
