# Arquitectura Clínica Base de MXMed

## Propósito

Este documento describe la arquitectura conceptual del núcleo clínico de MXMed y sirve como referencia permanente para el desarrollo del sistema.

Su objetivo es garantizar que las nuevas funcionalidades clínicas se integren de manera coherente y consistente.

---

## Mapa del núcleo clínico

Agenda
↓
Encounter (consulta)
↓
Actividad clínica
↓
Clinical Documents
↓
Timeline clínico
↓
Expediente del paciente

---

## Capas del sistema clínico

### 1. Contexto clínico

Representado principalmente por **Encounter**.

Define el contexto activo donde se registran las acciones médicas de una atención específica.

Ejemplos de contexto:

* consulta ambulatoria
* seguimiento clínico
* atención hospitalaria
* consulta derivada de cita

---

### 2. Registro clínico

Representado por **Actividad clínica**.

Es la capa donde el médico registra acciones durante la atención.

Ejemplos de acciones registrables:

* nota clínica
* procedimiento
* receta
* orden de estudio
* resultado diagnóstico
* documento clínico adjunto

Todas estas acciones deben persistirse mediante la arquitectura de **clinical_documents**.

---

### 3. Proyección clínica

Representada por el **Timeline clínico**.

El timeline es una proyección cronológica de los eventos clínicos registrados.

Permite visualizar:

* evolución clínica
* intervenciones realizadas
* resultados obtenidos
* documentos asociados

---

## Clinical Documents

El sistema utiliza una estructura unificada para almacenar acciones clínicas.

Estructura base:

patient_id
encounter_key
document_type
payload
summary
rendered_text
created_at

Esto permite que diferentes tipos de eventos clínicos compartan una misma arquitectura de persistencia.

---

## Clasificación clínica

El sistema utiliza varias capas de clasificación.

### clinical_category (semántica principal)

Valores comunes:

consulta
procedimiento
receta
estudio
documento

Esta capa debe guiar la organización de la interfaz de usuario.

---

### document_type (tipo técnico de documento)

Ejemplos:

nota_evolucion
procedure
immunization
prescription
lab_order
lab_result
image
pdf

Esta capa se utiliza para la persistencia técnica del documento clínico.

---

## Catálogo canónico de document_type

Notas
- nota_evolucion
- nota_evolucion_hosp

Procedimientos
- procedure
- immunization
- medication_administration
- wound_care

Recetas
- prescription

Órdenes de estudio
- lab_order
- imaging_order

Resultados de estudio
- lab_result
- imaging_result
- lab_pdf

Documentos clínicos
- image
- pdf
- bundle_clinical

Signos vitales
- vitals

Notas internas del sistema
- note (solo uso interno de auto-note de encounter)

### Compatibilidad legacy

Document types legacy que pueden aparecer en lectura pero no deben generarse en nuevas capturas:

- medical_note
- evolution_note
- rx
- receta
- orders
- order
- results
- result
- vital_signs
- signs
- orden_estudio

Regla de sistema:

Lectura -> aceptar alias legacy  
Captura nueva -> guardar únicamente tipos canónicos

---

## Principio rector del sistema clínico

Todo registro clínico debe entrar por la capa **Actividad clínica**.

Esto evita la dispersión de funcionalidades médicas en múltiples módulos sin relación.

Ejemplos de funcionalidades que deben integrarse en esta capa:

* notas clínicas
* procedimientos
* recetas
* órdenes de estudio
* resultados diagnósticos
* documentos clínicos

---

## Diagnóstico longitudinal (primera entidad longitudinal prioritaria)

MXMed prioriza como primer crecimiento longitudinal:

- **Diagnóstico longitudinal del paciente**

Regla arquitectónica:
- Debe implementarse como proyección derivada sobre `clinical_documents`.
- No debe introducir una fuente de verdad paralela.
- La captura permanece en Actividad clínica.

Fuente inicial de datos:
- `nota_evolucion.diagnosticos`

Campos mínimos sugeridos:
- `patient_id`
- `diagnosis_key`
- `label`
- `code` (opcional)
- `status` (`active`/`resolved`)
- `onset_at` (opcional)
- `resolved_at` (opcional)
- `resolution_note` (opcional)
- `first_seen_at`
- `last_updated_at`
- `source_document_uuid`
- `source_encounter_key` (opcional)

Ruta de implementación propuesta:
- D1 solo lectura
- D2 estado `active`/`resolved` + bitácora
- D3 UX en expediente
- D4 captura explícita vía Actividad clínica
- D5 normalización avanzada

Riesgos a evitar:
- fuente paralela desvinculada de `clinical_documents`
- escritura fuera de Actividad clínica
- duplicación de diagnósticos por falta de normalización
- forzar encounter en todos los casos longitudinales
- ausencia de bitácora/auditoría clínica

---

## Actividad clínica

La capa de actividad clínica funciona como punto de entrada para registrar eventos médicos.

Actualmente incluye:

Actividad clínica F1

* Nota clínica
* Procedimiento
* Receta

Fases futuras previstas:

Actividad clínica F2

* Orden de estudio
* Resultado de estudio
* Adjuntar documento

Actividad clínica F3

* desacoplar procedimientos del iframe embebido
* consolidar catálogo de procedimientos

---

## Deudas técnicas conocidas

* procedimientos actualmente dependen del módulo embebido en iframe
* coexistencia de múltiples capas de clasificación
* algunos contratos documentales requieren actualización

Estas deudas se resolverán progresivamente en fases futuras.

---

## Referencias recientes del sistema

Actividad clínica — Fase 1
commit: 63ec87b

Introducción del launcher **Registrar actividad clínica** dentro de Historial de Atención, reutilizando flujos existentes de notas, procedimientos y receta.

---

## Uso de este documento

Este documento debe utilizarse como guía para:

* intervenciones de Codex en el sistema
* diseño de nuevas funcionalidades clínicas
* revisión de coherencia arquitectónica
* documentación técnica del proyecto
