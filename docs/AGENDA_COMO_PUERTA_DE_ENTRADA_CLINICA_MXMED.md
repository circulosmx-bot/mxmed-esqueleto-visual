# Agenda como puerta de entrada clínica - MXMed

Este documento define cómo debe integrarse el módulo Agenda con el flujo clínico principal de México Médico (MXMed).

Su propósito es evitar confusiones entre:

- cita
- paciente
- expediente
- consulta clínica

---

## 1. Principio general

En MXMed, Agenda puede funcionar como puerta de entrada operativa al flujo clínico, pero no debe mezclar automáticamente los estados del sistema.

Agenda puede conducir al expediente del paciente, pero no debe iniciar por sí sola una consulta clínica.

---

## 2. Flujo canónico esperado

Agenda
↓
Selección / existencia de paciente
↓
Apertura de expediente
↓
Acción explícita: Iniciar consulta
↓
Consulta activa
↓
Actividad clínica
↓
Timeline / documentos / órdenes / resultados

---

## 3. Reglas obligatorias

1. Una cita no es lo mismo que una consulta.
2. Abrir una cita no debe iniciar consulta automáticamente.
3. Agenda puede abrir expediente del paciente.
4. Solo una acción explícita del usuario debe iniciar consulta.
5. La consulta activa es la que genera encounter y chip.
6. La actividad clínica real depende de consulta activa.

---

## 4. Agenda y paciente

### Caso A: cita con patient_id existente
- Agenda debe resolver el paciente canónico.
- Puede abrir el expediente del paciente.
- No debe crear consulta automáticamente.

### Caso B: cita sin patient_id canónico aún
- Agenda puede detonar creación/vinculación de paciente por la ruta canónica de Patients.
- Una vez resuelto el patient_id, puede abrir expediente.
- Tampoco debe iniciar consulta automáticamente.

---

## 5. Agenda y expediente

Cuando el usuario entra desde Agenda al contexto clínico:

- debe abrirse el expediente del paciente correcto
- debe existir contexto de patient_id
- puede mostrarse información de cita/origen
- no debe aparecer chip si no existe consulta activa
- el header clínico puede mostrar contexto operativo, pero no “consulta activa” si aún no empezó

---

## 6. Agenda y consulta activa

La consulta comienza solo cuando el usuario pulsa explícitamente:

Iniciar consulta

Ese momento debe:

- crear o resolver encounter
- activar lifecycle clínico
- generar chip
- permitir registrar actividad clínica real

---

## 7. Diferencias canónicas

### Cita
- representa programación operativa
- pertenece al dominio Agenda

### Expediente
- representa el contexto clínico visible del paciente
- pertenece al dominio Expediente / Patients

### Consulta
- representa el acto clínico activo
- pertenece al dominio Clinical Encounters

Estas tres capas deben permanecer separadas.

---

## 8. Riesgos que deben evitarse

- iniciar consulta automáticamente al abrir una cita
- generar chip desde Agenda sin encounter activo
- confundir cita con consulta
- perder patient_id al transicionar de Agenda a Expediente
- registrar actividad clínica sin consulta activa

---

## 9. Uso de este documento

Debe consultarse antes de modificar:

- Agenda
- integración Agenda -> Expediente
- flujos de apertura de paciente
- inicio de consulta desde citas
- chips / encounters ligados a citas

---

## 10. Relación con otros documentos

Este documento complementa:

- docs/PLAN_MAESTRO_MXMED.md
- docs/ARQUITECTURA_ESTADOS_EXPEDIENTE.md
- docs/MAPA_TOTAL_SISTEMA_MXMED.md
- docs/MAPA_INTERCONEXIONES_CLINICAS_MXMED.md
