# Registro Maestro de document_type MXMed (v1)

## Propósito

Este documento define la fuente de verdad documental inicial para `document_type` en MXMed.

Resuelve tres problemas operativos:

1. Evitar capturas no canónicas o dispersas.
2. Mantener compatibilidad de lectura con tipos legacy sin promoverlos en captura nueva.
3. Alinear la semántica entre Actividad clínica, Clinical Documents, Timeline y Expediente.

Marco arquitectónico:

Actividad clínica -> Clinical Documents -> Timeline -> Expediente

Este registry no implementa backend ni UI por sí mismo. Es una referencia versionada para decisiones de implementación.

---

## Estructura estándar del registro

Cada `document_type` debe definirse con esta estructura:

- `key`: clave canónica técnica del documento.
- `label`: nombre visible recomendado para UI.
- `family`: familia funcional (`consulta`, `estudios`, `procedimientos`, `adjuntos`, `hospitalario`, `clinico_administrativo`, `interno`).
- `status`: estado del tipo (`active`, `planned`, `legacy_readonly`, `deprecated`).
- `capture_surface`: superficies válidas de captura (`actividad_clinica`, `historial_atencion`, `hospitalario`, `sistema_auto`).
- `requires_patient`: si requiere `patient_id` obligatorio.
- `encounter_policy`: política de `encounter_key` (`required`, `optional`, `forbidden`, `system_only`).
- `timeline_visible`: si debe proyectarse en timeline clínico.
- `expediente_visible`: si debe verse en expediente.
- `default_clinical_category`: categoría semántica visible (`consulta`, `receta`, `procedimiento`, `estudio`, `documento`, `administrativo`).
- `default_study_role`: fase semántica de estudio (`order`, `result`, `null`).
- `legacy_aliases`: alias legacy aceptados solo en lectura/mapeo.
- `summary_strategy`: estrategia de resumen (`payload_field`, `generated`, `title_fallback`, `none`).
- `notes`: observaciones operativas.

---

## Catálogo v1 (tipos prioritarios)

```yaml
version: v1
updated_at: 2026-03-13
items:
  - key: nota_evolucion
    label: Nota de evolución
    family: consulta
    status: active
    capture_surface: [actividad_clinica]
    requires_patient: true
    encounter_policy: optional
    timeline_visible: true
    expediente_visible: true
    default_clinical_category: consulta
    default_study_role: null
    legacy_aliases: [medical_note, evolution_note]
    summary_strategy: payload_field
    notes: Documento clínico principal de evolución ambulatoria.

  - key: prescription
    label: Receta
    family: consulta
    status: active
    capture_surface: [actividad_clinica]
    requires_patient: true
    encounter_policy: optional
    timeline_visible: true
    expediente_visible: true
    default_clinical_category: receta
    default_study_role: null
    legacy_aliases: [rx, receta]
    summary_strategy: generated
    notes: Debe funcionar con y sin encounter activo.

  - key: procedure
    label: Procedimiento
    family: procedimientos
    status: active
    capture_surface: [actividad_clinica, historial_atencion]
    requires_patient: true
    encounter_policy: optional
    timeline_visible: true
    expediente_visible: true
    default_clinical_category: procedimiento
    default_study_role: null
    legacy_aliases: []
    summary_strategy: generated
    notes: Tipo base para procedimientos genéricos.

  - key: image
    label: Imagen clínica
    family: adjuntos
    status: active
    capture_surface: [actividad_clinica]
    requires_patient: true
    encounter_policy: optional
    timeline_visible: true
    expediente_visible: true
    default_clinical_category: documento
    default_study_role: null
    legacy_aliases: []
    summary_strategy: title_fallback
    notes: Adjuntos de evidencia clínica.

  - key: pdf
    label: PDF clínico
    family: adjuntos
    status: active
    capture_surface: [actividad_clinica]
    requires_patient: true
    encounter_policy: optional
    timeline_visible: true
    expediente_visible: true
    default_clinical_category: documento
    default_study_role: null
    legacy_aliases: []
    summary_strategy: title_fallback
    notes: Documento adjunto canónico en host.

  - key: lab_order
    label: Orden de laboratorio
    family: estudios
    status: active
    capture_surface: [actividad_clinica]
    requires_patient: true
    encounter_policy: optional
    timeline_visible: true
    expediente_visible: true
    default_clinical_category: estudio
    default_study_role: order
    legacy_aliases: [order, orders]
    summary_strategy: generated
    notes: Orden diagnóstica de laboratorio.

  - key: imaging_order
    label: Orden de imagen
    family: estudios
    status: active
    capture_surface: [actividad_clinica]
    requires_patient: true
    encounter_policy: optional
    timeline_visible: true
    expediente_visible: true
    default_clinical_category: estudio
    default_study_role: order
    legacy_aliases: [order, orders]
    summary_strategy: generated
    notes: Orden diagnóstica por imagen.

  - key: lab_result
    label: Resultado de laboratorio
    family: estudios
    status: active
    capture_surface: [actividad_clinica]
    requires_patient: true
    encounter_policy: optional
    timeline_visible: true
    expediente_visible: true
    default_clinical_category: estudio
    default_study_role: result
    legacy_aliases: [result, results]
    summary_strategy: payload_field
    notes: Resultado clínico estructurado.

  - key: imaging_result
    label: Resultado de imagen
    family: estudios
    status: active
    capture_surface: [actividad_clinica]
    requires_patient: true
    encounter_policy: optional
    timeline_visible: true
    expediente_visible: true
    default_clinical_category: estudio
    default_study_role: result
    legacy_aliases: [result, results]
    summary_strategy: payload_field
    notes: Resultado diagnóstico por imagen.

  - key: consentimiento_informado
    label: Consentimiento informado
    family: clinico_administrativo
    status: planned
    capture_surface: [actividad_clinica]
    requires_patient: true
    encounter_policy: optional
    timeline_visible: true
    expediente_visible: true
    default_clinical_category: administrativo
    default_study_role: null
    legacy_aliases: []
    summary_strategy: payload_field
    notes: Prioridad alta para converger en ruta canónica única.

  - key: insurance_medical_report
    label: Informe médico para aseguradora
    family: clinico_administrativo
    status: planned
    capture_surface: [actividad_clinica]
    requires_patient: true
    encounter_policy: optional
    timeline_visible: true
    expediente_visible: true
    default_clinical_category: administrativo
    default_study_role: null
    legacy_aliases: []
    summary_strategy: generated
    notes: Documento clínico-administrativo formal para trámites.
```

---

## Reglas del catálogo

1. Captura nueva:
- Solo debe generar `document_type` canónicos.

2. Legacy:
- Alias legacy se aceptan en lectura y mapeo.
- Alias legacy no deben usarse para nuevas capturas.

3. Semántica:
- `default_clinical_category` es la capa visible para UX.
- `default_study_role` define si es orden o resultado dentro de estudios.

4. Proyección:
- Timeline y Expediente deben usar este registry para clasificar y rotular.

5. Gobernanza:
- Todo `document_type` nuevo debe entrar primero por este registry y luego implementarse en runtime.

---

## Ruta de evolución del registry

1. Documento técnico versionado (estado actual).
2. Helper/config compartida backend/frontend para consumo de runtime.
3. Catálogo en BD solo si se requiere gestión dinámica o gobernanza por roles.

---

## Prioridades operativas

Tipos ya resueltos (operativos en flujo principal):
- `nota_evolucion`
- `prescription`
- `procedure` (y variantes de procedimiento)
- `image`
- `pdf`

Siguiente prioridad funcional:
- `consentimiento_informado` (ruta canónica host)
- consolidación host de `lab_order`, `imaging_order`, `lab_result`, `imaging_result`

Tipos previstos por ahora:
- `insurance_medical_report`
- otros clínico-administrativos (constancia/incapacidad/referencia/contrarreferencia/resumen_atencion)

---

## Notas de compatibilidad

Legacy lectura contemplada para:
- `rx`
- `vital_signs`, `signs`
- `order`, `orders`
- `result`, `results`

Regla general:
- Lectura: tolerante con alias legacy.
- Captura: estrictamente canónica.

---

## CONS-2 — Contrato canónico mínimo de consentimiento_informado

Estado documental:
- definido en arquitectura y registry v1
- sin implementación backend/UI canónica en esta fase

Reglas base:
- `patient_id` canónico obligatorio
- `encounter_key` opcional
- `appointment_id` opcional
- visible en Timeline y Expediente
- ruta objetivo: converger al modelo clínico canónico (`clinical_documents`)

### Estructura general propuesta

```json
{
  "document_type": "consentimiento_informado",
  "title": "Consentimiento informado — <tipo|procedimiento>",
  "summary": "<estado> · <tipo> · <fecha>",
  "context": {
    "patient_id": "p_xxx",
    "encounter_key": "enc:123",
    "appointment_id": "a_xxx",
    "care_setting": "consulta"
  },
  "payload": {
    "contract_version": 1,
    "consent": {},
    "patient_snapshot": {},
    "actor_snapshot": {},
    "template_snapshot": {},
    "legal": {},
    "signatures": {},
    "observations": ""
  },
  "event_datetime": "YYYY-MM-DD HH:mm:ss"
}
```

### Payload mínimo sugerido (`payload`)

```json
{
  "contract_version": 1,
  "consent": {
    "consent_type": "procedimiento|anestesia|transfusion|investigacion|otro",
    "document_title": "Consentimiento para ...",
    "status": "draft|granted|revoked",
    "granted_at": "YYYY-MM-DD HH:mm:ss",
    "revoked_at": null
  },
  "patient_snapshot": {
    "full_name": "Nombre Paciente",
    "identifier": "opcional",
    "contact": "opcional"
  },
  "actor_snapshot": {
    "user_id": "u_xxx",
    "full_name": "Médico Tratante",
    "license": "opcional"
  },
  "template_snapshot": {
    "template_id": "opcional",
    "template_name": "opcional",
    "body_text": "texto base mostrado/aceptado"
  },
  "legal": {
    "risks_explained": true,
    "alternatives_explained": true,
    "questions_resolved": true,
    "voluntary_acceptance": true
  },
  "signatures": {
    "patient_signed": true,
    "doctor_signed": true,
    "witness_signed": false,
    "signature_mode": "digital|captured|manual|none"
  },
  "observations": "opcional"
}
```

### Regla de mapeo operativo

- `title`: nombre legible del consentimiento (tipo/procedimiento).
- `summary`: estado breve para cards y listado (`<estado> · <tipo> · <fecha>`).
- `context`: claves clínicas de enlace (`patient_id` obligatorio; `encounter_key`/`appointment_id` opcionales).
- `payload`: detalle legal, snapshots y firmas.

### Proyección esperada

- Timeline: item de categoría `administrativo` con estado visible.
- Expediente: listado/consulta de consentimientos y acceso a detalle.
- Visor: texto/plantilla, confirmaciones legales y firmas.
