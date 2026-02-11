# DECISIÓN ARQUITECTÓNICA: FUENTES DE VERDAD CLÍNICAS

## 1) Contexto actual del sistema
El sistema hoy convive con cuatro bloques relevantes:

- Dominio Pacientes existente (`modules/patients`):
  - Ya resuelve identidad administrativa del paciente, contacto, vínculos y consentimientos administrativos.
  - Tiene tablas y endpoints activos para el dominio de pacientes.

- Motor de documentos clínicos existente (`clinical_documents`):
  - Ya persiste documentos clínicos estructurados (por ejemplo, notas de evolución y documentos intrahospitalarios) con payload JSON y timeline.
  - Es el motor activo para generación/listado/lectura de documentos clínicos.

- UI clínica avanzada con persistencia parcial:
  - Existen secciones visuales del expediente con madurez alta en captura.
  - No todas las secciones tienen backend estructurado/canónico propio aún.

- Nuevo módulo `modules/clinical`:
  - Nace para ordenar el dominio clínico faltante y normalizar contratos.
  - Debe integrarse con lo existente, sin duplicar fuentes ya canónicas.

## 2) Decisión formal de fuentes canónicas
Se define de forma explícita y obligatoria:

- Paciente canónico: `modules/patients`.
- Documentos clínicos canónicos: `clinical_documents`.
- `modules/clinical` NO duplica paciente.

Implicación directa:
- `modules/clinical` complementa y orquesta el dominio clínico faltante.
- `modules/clinical` no reemplaza ni replica la autoridad de identidad paciente ni la autoridad documental ya existente.

## 3) Qué guardará `modules/clinical` en v1
`modules/clinical` en v1 se enfocará en:

- Consentimiento informado clínico real (distinto del consentimiento administrativo de contacto/privacidad).
- Secciones del expediente que hoy no tienen backend estructurado canónico.
- Normalización de contratos API para el dominio clínico nuevo y su integración con componentes existentes.

Criterio de v1:
- Resolver huecos de persistencia clínica.
- Integrar sin romper flujos actuales.
- Evitar re-trabajo sobre componentes ya funcionales y canónicos.

## 4) Qué NO se debe duplicar
Queda prohibido duplicar en `modules/clinical`:

- Tabla de pacientes (cualquier clon de `patients_*` para identidad del paciente).
- Motor/tablas `clinical_documents` como almacenamiento principal de documentos clínicos.
- `patients_consents` administrativos (consentimientos de comunicación/privacidad del dominio Pacientes).

Regla de diseño:
- Si una fuente canónica ya existe, se referencia; no se replica.

## 5) Relación de IDs
Regla canónica de identidad transversal:

- Todo recurso clínico debe referenciar `patients_patients.patient_id`.
- `clinical_documents` debe mantenerse alineado al mismo `patient_id`.
- No se permiten IDs paralelos de paciente en dominios clínicos.

Esto aplica a:
- nuevas tablas de `modules/clinical`,
- payloads y contratos API,
- procesos de integración/migración.

## 6) Plan de alineación futura
Plan de evolución sin ruptura operativa:

- Unificar formato de respuesta API en toda superficie clínica:
  - `{ ok, error, message, data, meta }`.

- Ejecutar migración suave cuando se requiera:
  - compatibilidad hacia atrás,
  - coexistencia temporal controlada,
  - eliminación progresiva de contratos no normalizados.

- No romper Agenda:
  - preservar contratos y puntos de integración en uso,
  - introducir cambios de forma incremental,
  - aislar cambios clínicos de flujos Agenda/Waitlist.

## 7) Declaración final de arquitectura
A partir de esta decisión:

- `modules/patients` es la única fuente canónica de identidad de paciente.
- `clinical_documents` es la única fuente canónica de documentos clínicos.
- `modules/clinical` es el módulo de consolidación clínica v1 para lo que hoy falta estructurar, sin duplicar dominios existentes.

Esta definición es vinculante para diseño, implementación, integraciones y revisiones futuras.
