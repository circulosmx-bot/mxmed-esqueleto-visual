# Mapa de Interconexiones Clínicas - MXMed

Este documento define cómo se relacionan entre sí los módulos clínicos del sistema México Médico (MXMed).

Su propósito es evitar integraciones incorrectas y mantener una arquitectura coherente entre pacientes, expediente, consulta, timeline, documentos, órdenes y resultados.

---

## 1. Entidades núcleo

Las entidades centrales del sistema son:

- Paciente
- Expediente
- Consulta (Encounter)
- Actividad clínica
- Documento clínico
- Orden médica
- Resultado clínico
- Timeline clínico

---

## 2. Flujo principal de interconexión

Paciente
↓
Expediente
↓
Consulta activa
↓
Actividad clínica
↓
Órdenes / documentos / resultados
↓
Timeline clínico consolidado

---

## 3. Interconexiones obligatorias

### Paciente → Expediente
- Todo expediente pertenece a un paciente.
- No puede existir contexto clínico útil sin patient_id.

### Expediente → Consulta
- El expediente puede existir sin consulta activa.
- La consulta solo puede iniciarse de forma explícita.
- Guardar paciente no inicia consulta.

### Consulta → Actividad clínica
- La actividad clínica real solo debe registrarse si existe consulta activa.
- last_activity_at depende de acciones clínicas exitosas.

### Consulta → Órdenes
- Las órdenes médicas deben originarse dentro de una consulta o quedar claramente asociadas a ella.

### Consulta → Documentos
- Todo documento clínico relevante debe poder vincularse a una consulta y a un paciente.

### Orden → Resultado
- Un resultado clínico debe relacionarse con la orden que le dio origen, cuando aplique.

### Consulta / Documentos / Resultados → Timeline
- El timeline es la vista consolidada del historial clínico del paciente.
- No debe ser fuente primaria de captura; debe ser fuente consolidada de visualización.

---

## 4. Reglas de dependencia

1. Paciente es base de identidad.
2. Expediente organiza la vista clínica del paciente.
3. Consulta representa el acto clínico activo.
4. Actividad clínica depende de consulta activa.
5. Órdenes, documentos y resultados deben colgarse del paciente y, cuando corresponda, de una consulta.
6. Timeline consolida, no reemplaza, a los módulos origen.

---

## 5. Fuentes de verdad esperadas

- Identidad del paciente:
  patients_patients / módulo pacientes

- Estado del expediente activo:
  frontend expediente + contexto activo del paciente

- Consulta activa:
  activeEncounters / currentEncounterKey / clinical encounters

- Actividad clínica:
  acciones clínicas exitosas ligadas a encounter

- Timeline:
  consolidación de eventos clínicos del paciente

---

## 6. Riesgos arquitectónicos que deben evitarse

- Crear consulta automáticamente al guardar paciente
- Generar chip sin encounter activo
- Registrar actividad clínica sin consulta activa
- Mostrar timeline como si fuera fuente de captura primaria
- Asociar documentos sin patient_id
- Perder relación entre orden y resultado

---

## 7. Uso de este documento

Consultar este documento antes de modificar:

- agenda clínica
- expediente
- consultas
- timeline
- documentos clínicos
- órdenes médicas
- resultados clínicos
- integración entre módulos

---

## 8. Relación con otros documentos

Este documento debe leerse junto con:

- docs/PLAN_MAESTRO_MXMED.md
- docs/ARQUITECTURA_ESTADOS_EXPEDIENTE.md
- docs/MAPA_TOTAL_SISTEMA_MXMED.md
