# AUDITORÍA EXPEDIENTE CHECKLIST MXMED

## Propósito
Definir una auditoría detallada por bloques del módulo Expediente para revisar comportamiento real antes de cualquier cambio de implementación.

Alcance de esta fase:
- solo revisión funcional/UX/arquitectónica,
- sin refactor,
- sin cambios de lógica,
- basada en evidencia del repo y en `docs/MAPEO_EXPEDIENTE_MXMED.md`.

---

## Bloque 1 - Apertura de expediente y carga de patient_id

- Objetivo de revisión:
  - Validar que abrir expediente fije correctamente contexto de paciente sin mezclar estados clínicos.
- Qué validar manualmente:
  - Selección desde “Archivo de pacientes” (`Abrir expediente`).
  - Persistencia de `data-patient-id` / `data-active-patient-id` en `#p-expediente`.
  - Emisión de eventos `patient:selected`, `expediente:patient_changed`, `expediente:patient-changed`.
  - Confirmar que abrir desde búsqueda general no inicia consulta automáticamente.
- Archivos involucrados:
  - `assets/js/app.js`
  - `index.html`
- Endpoints participantes:
  - `GET /api/patients/index.php/doctors/{doctor_id}/patients`
- Estado esperado:
  - `patient_id` activo sincronizado en UI/store/dataset.
  - Expediente abierto en contexto de paciente.
  - Sin encounter nuevo salvo origen clínico explícito.
- Hallazgos típicos:
  - `patient_id` no sincronizado en todos los nodos.
  - Inicio de consulta accidental por ruta no explícita.
  - Divergencia entre store y dataset.
- Resultado: `OK / ajuste / duda`
- Observaciones:

---

## Bloque 2 - Header clínico y contexto visual

- Objetivo de revisión:
  - Verificar consistencia visual entre identidad paciente y estado clínico actual.
- Qué validar manualmente:
  - Render de nombre/edad/sexo.
  - Estado neutro “Sin consulta activa” cuando corresponde.
  - Badge `CONSULTA ACTIVA`, origen, hora de inicio y botones `Iniciar/Cerrar consulta`.
  - Banda de consultas activas y etiqueta correcta por chip (`Actual`/`Activa`).
- Archivos involucrados:
  - `index.html` (markup header)
  - `assets/js/app.js` (`syncExpedienteHeaderContext`, strip de activas)
- Endpoints participantes:
  - `GET /api/clinical/index.php/patients/{patient_id}/encounters/active`
  - `GET /api/clinical/index.php/encounters/{encounter_key}`
- Estado esperado:
  - Header refleja contexto real vigente (paciente visible + encounter actual).
- Hallazgos típicos:
  - Header pegado a paciente previo.
  - Badge activo sin encounter real.
  - Origen/hora vacíos o desfasados.
- Resultado: `OK / ajuste / duda`
- Observaciones:

---

## Bloque 3 - Tabs y gating del expediente

- Objetivo de revisión:
  - Confirmar navegación de tabs y reglas de habilitación según contexto de paciente.
- Qué validar manualmente:
  - Activación de tabs al click (Bootstrap/fallback).
  - `activeTab` sincronizado en dataset del panel.
  - Gating: sin paciente activo, tabs clínicos ocultos o no interactivos.
  - Nuevo paciente (`newEntryMode=1`) abre en `Datos Generales`.
- Archivos involucrados:
  - `index.html` (`#p-expediente`, tabs)
  - `assets/js/app.js` (`applyPatientGate`, activación tabs)
- Endpoints participantes:
  - N/A directo (comportamiento de shell/UI).
- Estado esperado:
  - Tabs estables y consistentes con contexto de paciente.
- Hallazgos típicos:
  - Tab activo visual distinto al pane visible.
  - Tab clínico accesible sin contexto válido.
  - Interferencia entre handlers manuales y Bootstrap.
- Resultado: `OK / ajuste / duda`
- Observaciones:

---

## Bloque 4 - Historial embebido

- Objetivo de revisión:
  - Validar estabilidad y coherencia del iframe embebido en modos historial/episodio/documento.
- Qué validar manualmente:
  - Carga inicial por `patient_id`.
  - Cambio de modo (`historial`, `encounter`, `document`).
  - Resolución de `patient_id` desde `encounter_key` cuando aplica.
  - Ausencia de loops de recarga o congelamientos en navegación.
- Archivos involucrados:
  - `index.html` (script inline P10/embed)
  - `modules/clinical/ui/historial.php`
  - `modules/clinical/ui/encounter.php`
  - `modules/clinical/ui/document.php`
  - `modules/_partials/clinical_embed.php`
- Endpoints participantes:
  - `GET /api/clinical/index.php/patients/{patient_id}/timeline`
  - `GET /api/clinical/index.php/encounters/{encounter_key}`
  - `GET /api/clinical/index.php/documents/{id_or_uuid}`
- Estado esperado:
  - Iframe carga subvista correcta según modo y contexto actual.
- Hallazgos típicos:
  - `src` no cambia cuando cambia contexto.
  - Pane correcto sin contenido correcto.
  - Desacople entre tab activo y subvista embebida.
- Resultado: `OK / ajuste / duda`
- Observaciones:

---

## Bloque 5 - Inicio de consulta

- Objetivo de revisión:
  - Confirmar que iniciar consulta sea explícito y consistente con reglas UX.
- Qué validar manualmente:
  - Click en `Iniciar consulta` desde P10/header.
  - Llamada a `POST /patients/{patient_id}/encounters`.
  - Emisión de lifecycle (`consulta_activa`) y sincronización inmediata de UI.
  - Cambio de modo embebido hacia episodio cuando corresponde.
- Archivos involucrados:
  - `index.html` (P10 start)
  - `assets/js/app.js` (estado multi-activo/lifecycle)
- Endpoints participantes:
  - `POST /api/clinical/index.php/patients/{patient_id}/encounters`
  - `GET /api/clinical/index.php/patients/{patient_id}/encounters/active`
- Estado esperado:
  - La consulta inicia solo por acción explícita y queda visible como activa.
- Hallazgos típicos:
  - Doble creación o estado activo fantasma.
  - Inicio silencioso sin intención explícita.
- Resultado: `OK / ajuste / duda`
- Observaciones:

---

## Bloque 6 - Estado de consulta activa

- Objetivo de revisión:
  - Auditar consistencia del estado activo entre store, datasets, header, chips y P10.
- Qué validar manualmente:
  - `activeEncounters`, `currentEncounterKey`, `currentPatientId`.
  - Compatibilidad temporal con `activeEncounterState`.
  - Reacción de header/strip/P10 ante `mxmed:encounter-lifecycle` y `mxmed:encounter-activity`.
- Archivos involucrados:
  - `assets/js/app.js`
  - `index.html`
- Endpoints participantes:
  - `GET /api/clinical/index.php/patients/{patient_id}/encounters/active`
  - `GET /api/clinical/index.php/encounters/{encounter_key}`
- Estado esperado:
  - Fuente de verdad clínica coherente en toda la superficie visible.
- Hallazgos típicos:
  - Store y UI muestran encounters distintos.
  - Estado “activa” pegado tras cambios de contexto.
- Resultado: `OK / ajuste / duda`
- Observaciones:

---

## Bloque 7 - Cierre de consulta

- Objetivo de revisión:
  - Verificar cierre seguro y sincronización inmediata de estado tras finalize.
- Qué validar manualmente:
  - Confirmación de cierre y llamada a finalize.
  - Emisión de `consulta_pendiente_cierre` -> `consulta_cerrada`.
  - Limpieza/selección correcta de encounter actual post-cierre.
  - Header y P10 regresan a estado neutro si no queda consulta activa para paciente visible.
- Archivos involucrados:
  - `index.html` (P10 finalize)
  - `assets/js/app.js` (sync multi-activo)
  - `modules/clinical/ui/encounter.php` (finalize en vista episodio)
- Endpoints participantes:
  - `POST /api/clinical/index.php/encounters/{encounter_key}/finalize`
  - `GET /api/clinical/index.php/patients/{patient_id}/encounters/active`
- Estado esperado:
  - Cierre consistente sin requerir “salir y volver a entrar”.
- Hallazgos típicos:
  - Header mantiene “consulta activa” tras cierre.
  - Encounter cerrado sigue marcado como actual.
- Resultado: `OK / ajuste / duda`
- Observaciones:

---

## Bloque 8 - Cambio de paciente y limpieza de estado

- Objetivo de revisión:
  - Validar limpieza/rehidratación de contexto visual al cambiar paciente.
- Qué validar manualmente:
  - `setActivePatientId` resetea identidad visible del paciente previo.
  - Rehidratación de draft visual por paciente al regresar.
  - Guard de abandono para “nuevo registrar paciente” solo al salir del flujo.
  - Neutralización completa al entrar a captura de nuevo paciente.
- Archivos involucrados:
  - `assets/js/app.js`
  - `assets/js/perfil/datos-generales.js`
- Endpoints participantes:
  - `GET /api/patients/index.php/doctors/{doctor_id}/patients`
  - `POST /api/patients/index.php/patients` (en guardado explícito)
- Estado esperado:
  - Sin contaminación cruzada A/B de identidad y con navegación protegida en modo captura.
- Hallazgos típicos:
  - Nombre/edad/sexo del paciente previo permanecen visibles.
  - Advertencia de abandono dispara en rutas incorrectas.
- Resultado: `OK / ajuste / duda`
- Observaciones:

---

## Bloque 9 - Integración Agenda -> Expediente

- Objetivo de revisión:
  - Revisar consistencia de transición Agenda/Pacientes hacia Expediente sin mezclar cita con consulta.
- Qué validar manualmente:
  - Apertura de expediente desde contexto de búsqueda/agenda.
  - Que `patient_id` viaje correctamente al contexto del expediente.
  - Que abrir contexto operativo no cree consulta activa automática salvo origen clínico explícito.
- Archivos involucrados:
  - `assets/js/app.js` (abrir expediente / `open_origin`)
  - `index.html` (navegación shell)
  - `docs/AGENDA_COMO_PUERTA_DE_ENTRADA_CLINICA_MXMED.md` (contrato arquitectónico)
- Endpoints participantes:
  - `GET /api/patients/index.php/doctors/{doctor_id}/patients`
  - `GET /api/agenda/index.php/appointments*` (según flujo operativo)
  - `POST /api/clinical/index.php/patients/{patient_id}/encounters` (solo si explícito)
- Estado esperado:
  - Cita/agenda no equivale automáticamente a consulta activa.
- Hallazgos típicos:
  - Apertura desde agenda provoca start encounter implícito.
  - Falta de traspaso de contexto paciente/cita.
- Resultado: `OK / ajuste / duda`
- Observaciones:

---

## Bloque 10 - Riesgos UX/arquitectónicos observables

- Objetivo de revisión:
  - Consolidar riesgos transversales detectables sin cambiar código.
- Qué validar manualmente:
  - Coherencia entre estado DOM, store global y eventos.
  - Dependencias inline críticas en shell.
  - Frontera clara Expediente (contexto) vs Consulta (acto clínico).
  - Contratos entre vistas embebidas y API.
- Archivos involucrados:
  - `index.html`
  - `assets/js/app.js`
  - `modules/clinical/ui/*`
  - `api/clinical/index.php`
- Endpoints participantes:
  - Todos los de Clinical/Pacientes usados por expediente embebido.
- Estado esperado:
  - Responsabilidades separadas y trazabilidad de estado entendible.
- Hallazgos típicos:
  - Doble fuente de verdad en frontend.
  - Reglas UX incumplidas por atajos de integración.
  - Ambigüedad entre timeline, historial, episodio y documentos.
- Resultado: `OK / ajuste / duda`
- Observaciones:

---

## Registro de ejecución de auditoría

| Bloque | Responsable | Fecha | Resultado | Evidencia breve |
|---|---|---|---|---|
| 1 |  |  |  |  |
| 2 |  |  |  |  |
| 3 |  |  |  |  |
| 4 |  |  |  |  |
| 5 |  |  |  |  |
| 6 |  |  |  |  |
| 7 |  |  |  |  |
| 8 |  |  |  |  |
| 9 |  |  |  |  |
| 10 |  |  |  |  |
