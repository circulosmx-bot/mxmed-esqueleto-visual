# Arquitectura de Estados del Expediente - MXMed

Este documento define el modelo de estados del expediente clínico en México Médico (MXMed).

El objetivo es separar correctamente:

- Alta administrativa del paciente
- Inicio de consulta clínica

Estos procesos son distintos y nunca deben mezclarse.

---

# Estados del sistema

## Estado 0 - Sistema neutro

No hay paciente seleccionado.

Características:

- sin patient_id activo
- sin encounter activo
- sin chip visible
- header clínico neutro

---

## Estado 1 - Captura nuevo paciente

Se accede mediante:

Pacientes -> Nuevo paciente interno

Características:

- newEntryMode = 1
- formulario limpio
- autosave local permitido
- aún no existe patient_id confirmado
- no hay consulta activa
- no hay chip

Regla UX:

Si el usuario intenta salir con datos capturados debe aparecer advertencia:

"Hay datos sin guardar. ¿Deseas salir y perderlos?"

Aceptar -> descarta captura  
Cancelar -> permanece en captura

---

## Estado 2 - Paciente creado / expediente abierto

Se alcanza al pulsar:

Guardar paciente

Acción técnica:

POST /api/patients/index.php/patients

Resultado:

patient_id confirmado

Características:

- el expediente pertenece al paciente
- los datos permanecen visibles
- no hay consulta activa
- no aparece chip
- aparece el hub `Paciente guardado correctamente`
- el hub ofrece 7 acciones clínicas:
  - Historia clínica
  - Exploración física
  - Historial de atención
  - Estudios diagnósticos
  - Tratamientos y recetas
  - Manejo hospitalario
  - Documentos clínicos
- no existe tarjeta separada `Recetas`; `Tratamientos y recetas` es la ruta clínica canónica desde el hub

Regla crítica:

Guardar paciente NO inicia consulta.
El hub post-guardado NO emite recetas ni crea documentos clínicos automáticamente.

---

## Estado 3 - Consulta activa

Se alcanza mediante:

Iniciar consulta

Acción técnica:

ensureActiveEncounter()

Resultado:

encounter_key activo

Características:

- lifecycle clínico activo
- chip visible
- header clínico contextual
- actividad clínica registrada

---

## Estado 4 - Paciente con expediente sin consulta activa

Se alcanza al cerrar consulta.

Características:

- patient_id sigue vigente
- no existe encounter activo
- no hay chip activo
- expediente sigue consultable

---

# Diagrama de flujo

Sin paciente seleccionado
↓
Pacientes -> Nuevo paciente interno
↓
Captura nuevo paciente
↓ Guardar paciente
Paciente creado
↓ Hub post-guardado
Paciente guardado correctamente
↓ Iniciar consulta
Consulta activa
↓ Cerrar consulta
Paciente sin consulta activa

---

# Reglas obligatorias del sistema

1. Guardar paciente no inicia consulta.
2. Solo iniciar consulta crea chip.
3. Cerrar consulta no elimina paciente.
4. Salir de captura sin guardar debe advertir.
5. Aceptar salir descarta la captura.
6. Después de guardar paciente la navegación no debe advertir cambios sin guardar.
7. `Pacientes` es top-level y abre `p-expediente`.
8. `Recetas` es top-level y abre `p-pac-recetas`.
9. `p-pac-archivo` mantiene activo visualmente `Pacientes`.
10. Receta rápida no puede emitir receta sin `patient_id`.

---

# Uso de este documento

Antes de modificar cualquier código relacionado con:

- pacientes
- expediente
- consultas
- encounters
- chips
- header clínico

se debe respetar este modelo de estados.
