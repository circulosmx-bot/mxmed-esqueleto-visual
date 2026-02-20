# PATIENT GATE V1

## 1) Propósito
Formalizar una regla de acceso y contexto de paciente para la UI de MXMed, de forma consistente y verificable, antes de implementar cambios visuales o funcionales.

## 2) Definición de paciente activo (`patient_id`)
`patient_id` es el identificador del paciente actualmente seleccionado en la sesión de trabajo del módulo.

Criterios mínimos para considerarlo válido en UI:
- No vacío.
- No valor placeholder (`anon`, `anonymous`, `-`).
- Corresponde a un paciente resoluble en el contexto actual (legacy/canonical según flujo vigente).

Estado lógico:
- `Gate ON`: existe `patient_id` activo válido.
- `Gate OFF`: no existe `patient_id` activo válido.

## 3) Regla Patient Gate ON/OFF
### Gate OFF
- El usuario puede navegar por secciones administrativas/operativas generales.
- Se deben bloquear acciones y vistas clínicas que dependan de paciente.
- La UI debe mostrar instrucción clara: seleccionar o registrar paciente.

### Gate ON
- Se habilitan secciones y acciones clínicas dependientes de paciente.
- La navegación clínica utiliza el `patient_id` activo de forma consistente.

## 4) Rutas de entrada A–H
Las rutas de entrada representan cómo se llega al estado de expediente/paciente. Se documentan para alinear comportamiento sin acoplarse aún a una sola implementación.

- A) Desde búsqueda/listado de pacientes con selección explícita.
- B) Desde agenda al abrir expediente de cita.
- C) Desde historial/clinical con `patient_id` en querystring.
- D) Desde resolver por `encounter_key` (cuando aplique) hacia `patient_id`.
- E) Desde resolver por `appointment_id` (cuando aplique) hacia `patient_id`.
- F) Desde contexto persistido de sesión (state/store) con paciente activo.
- G) Desde identidad legacy/canonical (bridge) con resolución previa.
- H) Sin contexto de paciente (entrada directa o deep link incompleto).

Regla transversal:
- A–G deben terminar en `Gate ON` si la resolución de paciente es válida.
- H debe permanecer en `Gate OFF` hasta que haya selección válida.

## 5) Comportamiento UX por estado
### UX con Gate OFF
- Mostrar estado vacío guiado: “Selecciona o registra un paciente para continuar”.
- Ocultar o deshabilitar CTAs clínicos dependientes de paciente.
- Evitar navegación a vistas clínicas con identificadores inválidos.

### UX con Gate ON
- Mostrar contexto del paciente activo de manera persistente.
- Habilitar tabs/subtabs clínicas según permisos/reglas existentes.
- Mantener consistencia al cambiar de paciente (limpiar contexto derivado cuando aplique).

## 6) Encabezado fijo (Gate ON)
Cuando `Gate ON` esté activo, mostrar un encabezado fijo de contexto clínico con:
- Nombre completo del paciente.
- Edad.

Objetivo:
- Reducir ambigüedad de contexto y errores de captura sobre paciente incorrecto.

## 7) Regla Gineco
La sección/tab gineco debe ser visible solo si el género del paciente corresponde a mujer.

- Si no corresponde: ocultar o deshabilitar según patrón UI vigente.
- Si cambia el paciente y cambia el criterio de género: la visibilidad debe recalcularse.

## 8) Alineación con Clinical
Si `Gate OFF`:
- Bloquear accesos funcionales a Clinical (historial, encounter, documentos y acciones clínicas relacionadas).
- No intentar operaciones clínicas sin `patient_id` válido.

Si `Gate ON`:
- Permitir acceso a Clinical con el `patient_id` activo y mantener trazabilidad de contexto.

## 9) Alcance de esta versión (v1)
Este documento define política y comportamiento esperado.

No incluye todavía:
- Refactor de estado global.
- Cambios de backend/API.
- Implementación UI final del gate.

