# Plan vivo de reforma del Expediente Clínico

```text
REFORM_STATUS=IN_PROGRESS
CURRENT_ACCEPTED_HEAD=8698b1f66466867360651db5fa62e54d28fba797
REFORM_START_DATE=2026-09-18
CURRENT_PHASE=PHASE_2_ENCOUNTER_INTEGRITY
CURRENT_OBJECTIVE=Definir y demostrar la integridad del ciclo de vida de la consulta antes de implementar el nuevo workspace ambulatorio.
NEXT_AUTHORIZED_STEP=Director/assistant review of Encounter Integrity contract, current gaps, controlled validation plan and material Phase 2 decisions before any implementation.
CLIN-REFORM-PLAN01=ACCEPTED
PHASE_0_AUDIT01=ACCEPTED
PHASE_0_AUDIT02=ACCEPTED
PHASE_0_CURRENT_STATE_AUDIT=COMPLETE
PHASE_0_STATUS=COMPLETE
PHASE_1_STATUS=COMPLETE
PHASE_1_AUTHORIZED=true
PHASE_1_CLINICAL_INFORMATION_MODEL_UX_CONTRACT=COMPLETE
PHASE_1_MODEL01=ACCEPTED
PHASE_1_MODEL01A=ACCEPTED
DIRECTOR_DECISIONS_RATIFIED=6/6
DIRECTOR_DECISIONS_REQUIRED_COUNT=0
PHASE_2_STATUS=IN_PROGRESS
PHASE_2_AUTHORIZED=true
PHASE_2_CONTRACT01=READY_FOR_DIRECTOR_REVIEW
```

Este plan es la autoridad subordinada y viva de la reforma del Expediente Clínico. El [Plan Maestro MXMed](../PLAN_MAESTRO_MXMED.md) conserva la autoridad global del proyecto. El Director/asistente aceptó CLIN-REFORM-PLAN01 en `edfa1326c602a1efcd3c94cfb705c582a170516e`, CLIN-REFORM-PHASE0-AUDIT01 en el baseline `d0602f9c443c1d3e215934c4cc2aa6084e126d12`, la cadena [CLIN-REFORM-PHASE0-AUDIT02](EXPEDIENTE_CURRENT_STATE_AUDIT_PHASE0.md#clin-reform-phase0-audit02--physical-runtime-validation) `504bd136854518d301915d743911c5f0f60c7aa1` → `ef378fddef3edaff07f603d965defa82365a80de`, el cierre de PHASE 0 en `a1dd2860f90094260c08388d363410198e5a7495` y la cadena MODEL01/MODEL01A `0ba07c9d8798ee6ecf03083453f5a587fff812b8` → `63d9e22403ce64ac8a49f2b06afe3f875724baa3`. El cierre de PHASE 1 está aceptado en `8698b1f66466867360651db5fa62e54d28fba797`, registrado como `CURRENT_ACCEPTED_HEAD` **previo a CONTRACT01**. El [modelo de información y contrato UX](EXPEDIENTE_CLINICAL_INFORMATION_MODEL_UX_CONTRACT.md) está aceptado; PHASE 1 está completa. PHASE 2 está `IN_PROGRESS` sólo para diseño y [revisión de CONTRACT01](EXPEDIENTE_ENCOUNTER_INTEGRITY_CONTRACT.md); no se autorizan implementación, esquema, API, datos ni validación con escrituras en este capítulo.

## Visión y problema

> La unidad de trabajo clínico cotidiano es la consulta.
> La unidad de continuidad longitudinal es el paciente.
> La especialidad personaliza ambas sin crear un expediente paralelo.

La interfaz vigente organiza el Expediente principalmente como pestañas de información: Datos Generales, Exploración Física, Historia Clínica, Historial de Atención, Estudios Diagnóstico, Tratamiento / Recetas, Manejo Hospitalario, Documentos Clínicos y Archivo. La auditoría debe determinar si esta organización ayuda al trabajo cotidiano del médico y distinguir qué pertenece al paciente longitudinalmente, a una consulta, a un episodio de varias consultas, a documentos/resultados o a operaciones administrativas y financieras. La apariencia de una pestaña no demuestra por sí sola su autoridad ni su persistencia.

## Principios vinculantes de dominio

Estos principios rigen las decisiones futuras; no son una autorización para modificar datos o contratos en PLAN01.

| Principio | Consecuencia de diseño |
| --- | --- |
| `PATIENT != ENCOUNTER` | Identidad longitudinal y atención puntual tienen ciclos de vida distintos. |
| `OPEN_PATIENT_RECORD != START_ENCOUNTER` | Abrir un paciente no crea una consulta. |
| `CLOSE_PATIENT_VIEW != FINALIZE_ENCOUNTER` | Cerrar la vista no finaliza una consulta clínica. |
| `CURRENT_MEASUREMENT != PREVIOUS_MEASUREMENT` | Cada medición conserva fecha y procedencia. |
| `PREVIOUS_VALUE_MUST_NOT_AUTOFILL_AS_CURRENT_MEASUREMENT` | Un valor previo no se presenta como recién medido. |
| `PRESCRIPTION != CURRENT_MEDICATION` | Prescribir y mantener una lista de medicación vigente son actos distintos. |
| `MISSING_INFORMATION != NORMAL_FINDING` | Ausencia de captura no equivale a hallazgo normal. |
| `CLINICAL_STATUS != BILLING_STATUS` | El estado clínico no deriva del cobro. |
| `CLINICAL_DOCUMENT != FINANCIAL_DOCUMENT` | Documentos clínicos y fiscales mantienen autoridades separadas. |
| `SPECIALTY_MODULE != PARALLEL_PATIENT_RECORD` | La especialidad amplía el núcleo sin duplicar al paciente. |
| `HISTORICAL_SIGNED_CONTENT_MUST_NOT_BE_SILENTLY_REWRITTEN` | El contenido firmado requiere una estrategia explícita de corrección. |
| `PATIENT_IDENTITY_MUST_REMAIN_CANONICAL` | La reforma respeta la identidad canónica del paciente. |
| `DOCTOR_ATTRIBUTION_MUST_REMAIN_CANONICAL` | Toda atención conserva su atribución médica canónica. |
| `NO_INFERENCE_OF_LEGACY_DOCTOR_OWNERSHIP` | No se atribuye retrospectivamente un médico por inferencia. |

## Autoridades actuales que deben preservarse

Esta es la base arquitectónica aceptada para orientar la auditoría, no una conclusión sobre cada control de la UI.

| Dominio | Autoridad vigente |
| --- | --- |
| Identidad del paciente | `patients_patients`, `patients_profiles`, `patients_contacts`, `patients_doctor_links`. |
| Consulta/encounter | `clinical_encounters`; atribución médica en `clinical_encounters.doctor_id`; `clinical_encounters.appointment_id` es opcional. |
| Documentos clínicos | Autoridad existente de `clinical_documents`. |
| Casos clínicos | `clinical_cases` y `clinical_case_items`. |
| Agenda | `agenda_appointments` y autoridades existentes de Agenda. |
| Facturación | Arquitectura de billing separada: paciente no equivale a receptor fiscal y médico no equivale automáticamente a emisor fiscal. |

## Observaciones iniciales y resultado de PHASE 0

Estas fueron preguntas de auditoría iniciales. Su resultado se documenta sin convertir la evidencia limitada en permiso de implementación.

| Observación inicial | Resultado aceptado al cierre |
| --- | --- |
| Historial de Atención mostró una falla local relacionada con `/tmp/.../director-router.php`. | El router temporal ausente causó el fatal local; PHP directo sirve el shell. Los datos de timeline no se ejecutaron por riesgo de DDL en GET. |
| Manejo Hospitalario podía mostrar “Selecciona paciente” y “no disponible” con un paciente visible. | Capacidad local deshabilitada y propagación de paciente inconsistente en ese runtime; son estados distintos. |
| Tratamiento / Recetas delega operaciones a Actividad Clínica. | La receta se emite como documento clínico; la medicación actual mostrada carece de una autoridad canónica única demostrada. |
| Archivo presenta adjuntos clínicos. | La pestaña es un placeholder, sin autoridad de archivo clínico demostrada. |
| La persistencia de Datos Generales ya estaba implementada. | La identidad estructurada del paciente se leyó y conservó tras recarga; no se probó escritura en esta auditoría. |

## Capas de información conceptuales aceptadas

Estas capas describen la clasificación lógica aceptada en MODEL01/MODEL01A; el [contrato detallado](EXPEDIENTE_CLINICAL_INFORMATION_MODEL_UX_CONTRACT.md) resuelve titularidad y ciclos de vida. No son tablas nuevas ni prueba de implementación física.

| Capa | Ejemplos |
| --- | --- |
| Identidad y administración del paciente | Nombre, fecha de nacimiento, contacto, domicilio, responsable/contacto. |
| Resumen clínico longitudinal | Problemas activos, alergias, medicación actual revisada, antecedentes relevantes, seguimiento pendiente. |
| Datos de una consulta | Motivo, padecimiento/evolución actual, signos vitales, antropometría medida en la visita, exploración física, valoración y plan. |
| Episodio/caso clínico | Embarazo, tratamiento dental prolongado, curso posoperatorio u otro proceso de varias consultas. |
| Documentos y resultados clínicos | Recetas, notas, órdenes, resultados, certificados y consentimientos. |
| Administración y finanzas | Cita, pago, recibo y factura. |

Ninguna capa justifica crear esquema únicamente por figurar aquí.

## Hipótesis de flujo cotidiano

**Seguimiento:** abrir paciente → consultar resumen → revisar información activa, consulta anterior y pendientes → iniciar o continuar consulta → registrar datos actuales → valoración y plan → generar documentos clínicos pertinentes → finalizar consulta → gestionar seguimiento → atender pago/facturación por separado cuando corresponda.

**Paciente nuevo:** crear identidad mínima necesaria → abrir contexto clínico del paciente → iniciar primera consulta → completar historia y datos clínicos pertinentes según el caso. No se deben exigir todos los campos administrativos para comenzar la atención salvo regla explícita y validada.

Abrir un paciente o navegar entre pestañas **no** crea una consulta.

## Objetivos de revisión longitudinal

La reforma futura debe permitir responder de forma eficiente y con fecha/procedencia:

- ¿Qué ocurrió en la última consulta? ¿Cuál fue la presión arterial previa? ¿Cuánto pesaba antes y cuál era el valor aproximadamente hace un año?
- ¿Qué medicamento se prescribió la última vez? ¿Qué documentos se generaron? ¿Qué estudios o resultados siguen pendientes?
- ¿Hay una cita futura? ¿Se cobró la consulta previa? ¿Existe recibo? ¿Se emitió factura?

Estas preguntas son metas de aceptación de fases posteriores; PLAN01 no las implementa.

### Valor previo frente a medición actual

Un valor previo puede mostrarse como referencia, pero nunca llenar silenciosamente el encuentro actual como si acabara de medirse:

```text
Anterior: Peso 78 kg — 2026-06-10
Actual:   [vacío hasta medir]
```

Una futura acción explícita de copiar/reutilizar, si se valida clínicamente, debe exigir intención del usuario y conservar la procedencia.

## Estrategia de extensión por especialidad

`NÚCLEO CLÍNICO + CONFIGURACIÓN DE ESPECIALIDAD + TIPO DE CONSULTA + PREFERENCIAS DEL MÉDICO`.

No se crea un expediente de paciente separado por especialidad. Los siguientes workstreams sólo se evaluarán más adelante, cada uno con capítulo propio de diseño y validación clínica:

| Especialidad | Alcance por evaluar |
| --- | --- |
| Pediatría | Crecimiento, desarrollo, cuidadores, inmunizaciones y observaciones pediátricas. |
| Ginecología/obstetricia | Historia ginecológica, episodio de embarazo, seguimiento gestacional y observaciones/documentos específicos. |
| Odontología | Odontograma, hallazgos por diente/superficie, plan de tratamiento, procedimientos, imagen dental y estado longitudinal. |

## Fases y criterios de salida

- [x] **PHASE 0 — CURRENT STATE AUDIT** · `COMPLETE`. AUDIT01 y AUDIT02 aceptados; mapa factual de nueve secciones, autoridades, temporalidad, límites de lectura física y riesgos heredados registrado. Las pruebas que requieren escritura o GET con posible DDL pasan a capítulos posteriores de integridad/implementación; no se requiere AUDIT03.
- [x] **PHASE 1 — CLINICAL INFORMATION MODEL / UX CONTRACT** · `COMPLETE`. MODEL01 y MODEL01A aceptados; cinco dominios de titularidad, vistas derivadas, ciclos de vida, navegación conceptual y seis decisiones del Director cerrados. La representación física permanece diferida.
- [ ] **PHASE 2 — ENCOUNTER INTEGRITY** · `IN_PROGRESS`, [CONTRACT01](EXPEDIENTE_ENCOUNTER_INTEGRITY_CONTRACT.md) listo para revisión del Director/asistente. El contrato propone inicio, guardado, reanudación, finalización, lectura histórica, enmiendas, relación documental y plan de prueba controlada; las decisiones materiales y la implementación siguen pendientes. **Salida futura:** dos o más consultas del mismo paciente se crean y revisan de forma independiente, recuperable y doctor-scoped, sin sobrescritura histórica.
- [ ] **PHASE 3 — AMBULATORY CONSULTATION WORKSPACE** · `NOT_STARTED`. Construir el flujo diario con resumen del paciente, motivo/evolución, exploración/mediciones, valoración, plan, documentos/acciones y revisión/finalización. **Salida:** una consulta ambulatoria normal se completa sin saltos innecesarios entre módulos.
- [ ] **PHASE 4 — LONGITUDINAL FOLLOW-UP** · `NOT_STARTED`. Evaluar comparación anterior/actual, mediciones históricas, tendencias, historial de medicación y recetas, resultados y pendientes clínicos. **Salida:** preguntas centrales de seguimiento se responden rápidamente con fecha y procedencia explícitas.
- [ ] **PHASE 5 — CLINICAL ↔ ADMINISTRATIVE RELATIONSHIP** · `NOT_STARTED`. Permitir localizar cita, pago, recibo y factura sin fusionar autoridades financieras y clínicas. **Salida:** el estado administrativo relacionado con una consulta es localizable y mantiene la separación de dominio.
- [ ] **PHASE 6 — SPECIALTY MODULES** · `NOT_STARTED`. Implementar progresivamente extensiones validadas **después** de estabilizar el núcleo de consulta. Cada especialidad exige análisis de flujo, validación clínica, contratos de datos y UX, estrategia histórica/versionado y QA.

### Entregables de auditoría de PHASE 0 — aceptados

El [capítulo CLIN-REFORM-PHASE0-AUDIT01](EXPEDIENTE_CURRENT_STATE_AUDIT_PHASE0.md), de **sólo lectura para el producto**, presenta una matriz de estado actual con una fila por cada sección: Datos Generales, Exploración Física, Historia Clínica, Historial de Atención, Estudios Diagnóstico, Tratamiento / Recetas, Manejo Hospitalario, Documentos Clínicos y Archivo. AUDIT01 está `ACCEPTED`. [AUDIT02](EXPEDIENTE_CURRENT_STATE_AUDIT_PHASE0.md#clin-reform-phase0-audit02--physical-runtime-validation) agrega validación física segura y está `ACCEPTED`. La matriz original conserva su evidencia histórica; el cierre canónico y los límites de prueba constan aquí.

Cada fila deberá incluir exactamente estas columnas; un dato no comprobado se marcará `NO_VERIFICADO` y no se inferirá:

```text
SECTION | CURRENT_UI | FUNCTIONAL_STATUS | READ_AUTHORITY | WRITE_AUTHORITY |
STORAGE_TABLES | PATIENT_SCOPED | DOCTOR_SCOPED | ENCOUNTER_SCOPED |
CASE_SCOPED | PERSISTS_AFTER_RELOAD | BEHAVIOR_ON_NEXT_ENCOUNTER |
KNOWN_BLOCKERS | LOCAL_FIXTURE_DEPENDENCY | DUPLICATE_AUTHORITY_RISK | NOTES
```

La auditoría contrasta código, contratos, entorno y comportamiento físico disponible; las pruebas impedidas por el contrato de cero escrituras o por GET con potencial DDL conservan `NO_VERIFICADO`. Esos límites ya no impiden cerrar el levantamiento del estado actual; requieren validación controlada en capítulos posteriores.

### CLIN-REFORM-PHASE0-CLOSEOUT — hallazgos aceptados

1. **Identidad del paciente:** la identidad y persistencia canónicas (`patients_*`) son longitudinales/administrativas y distintas de la consulta; Datos Generales no debe duplicarse por encounter.
2. **Exploración Física:** la autoridad vigente es un borrador mutable por paciente en `clinical_record_entries`, sin versión independiente por consulta ni fecha efectiva, médico y encounter canónicos por medición.
3. **Historia Clínica:** antecedentes longitudinales y contenido de una consulta coexisten en el mismo borrador mutable del paciente; PHASE 1 debe resolver esa temporalidad mixta.
4. **Ausente no es normal:** la fuente contiene un estado/fallback «normal» de exploración. `MISSING_INFORMATION != NORMAL_FINDING`; no se atribuye normalidad a una exploración no registrada. Su comportamiento físico de guardado no se probó.
5. **Consulta:** `clinical_encounters` permanece como autoridad canónica; no se creará una autoridad paralela. Los encuentros legacy sin atribución médica son una restricción existente y no se reasignan por inferencia.
6. **Historial:** el fatal local provenía del router de QA ausente; el servidor PHP directo sirve el shell. La timeline con datos quedó `NO_VERIFICADO_SAFETY_GATE` porque sus GET pueden ejecutar DDL; ello no bloquea este cierre.
7. **GET clínico:** algunos GET invocan aseguramiento de esquema con `CREATE`/`ALTER`. `CLINICAL_GET_SCHEMA_SIDE_EFFECT_RISK=OPEN`; no se repara en este capítulo.
8. **Documentos:** `clinical_documents` es una autoridad real y poblada, sin enlace universal a encounter. El futuro contrato distinguirá documento de paciente, de consulta y de episodio/caso.
9. **Receta/medicación:** las recetas documentales y la presentación de medicación vigente no son una única autoridad canónica. `PRESCRIPTION != CURRENT_MEDICATION`.
10. **Hospital:** se comprobó una inconsistencia local de propagación del paciente con la capacidad deshabilitada. La inspección del API hospitalario tampoco estableció autorización por médico/vínculo activo; no se probó acceso cruzado.
11. **Archivo:** la pestaña actual es un placeholder y no representa una autoridad canónica de archivo clínico.
12. **Especialidades:** ninguna arquitectura de expediente paralelo por especialidad está aprobada; las extensiones futuras parten del núcleo paciente + encounter.

Los hallazgos derivados de fuente, los hechos de base agregados y los comportamientos físicos se distinguen en AUDIT01/AUDIT02. Ningún `NO_VERIFICADO` se convierte aquí en prueba funcional.

### Riesgos y restricciones que continúan

```text
CLINICAL_GET_SCHEMA_SIDE_EFFECT_RISK=OPEN
PHYSICAL_EXAM_PATIENT_DRAFT_OVERWRITE_RISK=OPEN
HISTORY_MIXED_TEMPORALITY_RISK=OPEN
DEFAULT_NORMAL_SEMANTICS_RISK=OPEN
DOCUMENT_ENCOUNTER_LINKAGE_GAP=OPEN
PRESCRIPTION_MEDICATION_AUTHORITY_GAP=OPEN
HOSPITAL_CONTEXT_PROPAGATION_RISK=OPEN
HOSPITAL_AUTHORIZATION_SCOPE_RISK=OPEN
ARCHIVE_PLACEHOLDER_GAP=OPEN
LEGACY_UNATTRIBUTED_ENCOUNTERS=KNOWN_EXISTING_CONSTRAINT
```

Estos riesgos alimentan el diseño y las pruebas posteriores; no son todos bloqueos de PHASE 1. Su registro no autoriza migraciones, correcciones ni operaciones clínicas.

### Cierre de PHASE 1 y frontera de PHASE 2

El [capítulo MODEL01/MODEL01A](EXPEDIENTE_CLINICAL_INFORMATION_MODEL_UX_CONTRACT.md) es el contrato conceptual aceptado. `PATIENT` conserva identidad y estado clínico longitudinal revisado; `ENCOUNTER` representa una consulta específica; `EPISODE_CASE` es agrupación opcional visible como «Caso clínico»; `DOCUMENT` conserva artefacto versionado con contexto y procedencia; `ADMINISTRATIVE` mantiene Agenda y finanzas separados de la verdad clínica; `DERIVED_VIEW` es lectura proyectada, nunca autoridad editable paralela. Medicación vigente y problemas activos son longitudinales de primera clase. La nueva consulta obtiene instancia propia; los valores previos siguen históricos y no se autocompletan como mediciones actuales. `MISSING_INFORMATION != NORMAL_FINDING` y `UNRECORDED_PHYSICAL_EXAM != NORMAL`.

### Entregable MODEL01/MODEL01A aceptado

El [contrato de información clínica y UX](EXPEDIENTE_CLINICAL_INFORMATION_MODEL_UX_CONTRACT.md) presenta la matriz de titularidad de 68 conceptos, las reglas de mediciones/exploración, resumen derivado en seis prioridades, distinción receta/medicación, contexto documental paciente/consulta/caso, cinco áreas de navegación, workspace de consulta separado y separación administrativa: finalizar consulta no equivale a cobrar ni facturar. Las seis decisiones del Director quedaron ratificadas y el contrato completo aceptado. Sus filas `PROPUESTA` tienen efecto de **diseño conceptual aceptado**, salvo `PRUEBA`, riesgo técnico no resuelto o detalle de implementación futura. PHASE 0 sigue siendo el insumo factual vinculante; ninguna ruta, tabla ni pantalla cambia por este cierre.

Siguen diferidos, sin impedir PHASE 1 completa: diseño físico de esquema, migración de `clinical_record_entries`/borradores legacy, vínculo físico documento/encounter, reparación GET/DDL, contexto y autorización de Hospital, enmiendas históricas, almacenamiento físico de medicación y problemas activos, especialidades, «Documento libre» y encuentros legacy sin atribución. Los riesgos abiertos de PHASE 0 no se cierran por aceptar el modelo conceptual.

PHASE 2 debe diseñar y demostrar la integridad del ciclo de vida de `clinical_encounters`: iniciar, guardar/borrador, reanudar, finalizar, leer historia, enmendar/corregir, relacionar documentos, atribuir médico/paciente, vincular cita opcional y conservar varias consultas del mismo paciente. La prueba clave es que Consulta 1 permanezca intacta cuando se abre Consulta 2 con datos actuales independientes, valores previos sólo de referencia, documentos en su contexto y recarga/reanudación sin duplicados ni sobrescritura histórica. CONTRACT01 propone ese contrato y plan de validación para revisión, **sin ejecutar pruebas físicas**. Implementación de esquema/API exige autorización posterior separada; el workspace ambulatorio final, navegación final, módulos de especialidad, tendencias completas e integración visual de billing pertenecen a fases posteriores.

### CLIN-REFORM-PHASE2-CONTRACT01 — propuesta para revisión

El [contrato de integridad de consulta](EXPEDIENTE_ENCOUNTER_INTEGRITY_CONTRACT.md) documenta el alcance comprobado en fuente y sus brechas, el ciclo de vida propuesto, reglas de titularidad y procedencia, 20 escenarios sintéticos de validación **no ejecutados** y cinco decisiones materiales del Director. La búsqueda actual de OPEN incluye al usuario que abrió la consulta; la unicidad por médico/paciente, su protección frente a carreras y la idempotencia completa de cierre todavía no están probadas. La lectura clínica con posible DDL continúa bloqueando validación física segura. CONTRACT01 no aprueba cambios de esquema, API, UI, datos ni pruebas con escrituras. `NEXT_AUTHORIZED_STEP` es revisar el contrato, las brechas, el plan y las decisiones antes de cualquier implementación.

## Calidad y aceptación futura

Capturas, inspección visual y ausencia de errores JavaScript no bastan. La QA futura requiere escenarios clínicos completos, límites de autoridad y comprobación de persistencia/historia. Escenario canónico:

```text
PACIENTE NUEVO → PRIMERA CONSULTA → FINALIZAR → CONSULTA DE SEGUIMIENTO
→ COMPARAR DATOS PREVIOS → REVISAR RECETA ANTERIOR → EMITIR DOCUMENTO NUEVO
→ FINALIZAR → LOCALIZAR RECIBO/FACTURA → REABRIR CONSULTA HISTÓRICA
```

La consulta histórica debe permanecer intacta.

## Decisiones aceptadas, bloqueos y decisiones superadas

Los principios de dominio, la separación de autoridades y la secuencia de fases de este plan siguen vigentes. PLAN01 no aprobó una nueva pantalla, esquema, endpoint ni cambio de flujo. Las cinco observaciones iniciales tienen resultado en PHASE 0; los riesgos abiertos constan en la lista de continuidad y cada decisión de implementación posterior requiere su propio capítulo y evidencia.

### SUPERSEDED DECISIONS

Sin entradas iniciales. Una decisión rechazada o sustituida se trasladará aquí con fecha, decisión anterior, motivo y autoridad que aprobó la sustitución; nunca se borrará silenciosamente.

## Update Protocol

1. Cada capítulo de reforma aceptado actualizará este plan si cambia el estado de fase, las decisiones aceptadas, los bloqueos, el commit base o el siguiente paso autorizado.
2. Ninguna fase pasa a `COMPLETE` por un autorreporte de Codex. Requiere aceptación del Director/asistente.
3. Mantener `CURRENT_ACCEPTED_HEAD` en la cabecera. Actualizarlo sólo al aceptar un nuevo baseline.
4. Mantener `NEXT_AUTHORIZED_STEP` explícito; no ejecutar pasos posteriores por inferencia.
5. Mantener un CHANGE LOG conciso con fecha, capítulo, commit y decisión/resultado.
6. Conservar decisiones rechazadas o superadas en **SUPERSEDED DECISIONS**, con fecha y motivo.
7. Antes de dar instrucciones estructurales de Expediente en conversaciones futuras, leer este plan junto al Plan Maestro.

### CHANGE LOG

| DATE | CHAPTER | COMMIT / BASELINE | DECISION / RESULT |
| --- | --- | --- | --- |
| 2026-09-18 | REFORM-PLAN01 | Pre-plan baseline/checkpoint `4bf0d740fe82949dbc0450ff6ee6e65185216aff`; accepted commit `edfa1326c602a1efcd3c94cfb705c582a170516e` | Plan creado y aceptado (`CLIN-REFORM-PLAN01=ACCEPTED`); auditoría de `PHASE_0_CURRENT_STATE_AUDIT` autorizada como siguiente capítulo, aún no iniciada. |
| 2026-09-18 | CLIN-REFORM-PHASE0-AUDIT01 | Baseline aceptado `7a4a29936135d689b6386bff5ac686e42359c145`; commit de auditoría `b31e3aeea63a535ddb0074dbd2d5d3b69be310ef` | Matrices y hallazgos de las nueve secciones, temporalidad, alcance, seguridad y dependencias locales; `READY_FOR_DIRECTOR_REVIEW`. PHASE 0 permanece `IN_PROGRESS`; siguiente paso: revisión del Director/asistente. |
| 2026-09-18 | CLIN-REFORM-PHASE0-AUDIT02 | Baseline aceptado `d0602f9c443c1d3e215934c4cc2aa6084e126d12`; commit de auditoría física `504bd136854518d301915d743911c5f0f60c7aa1` | AUDIT01 aceptado. Servidor local sin router histórico, lectura/recarga de identidad y navegación seguras verificadas; timeline y GET clínicos con posible DDL quedan sin ejecutar. AUDIT02 `READY_FOR_DIRECTOR_REVIEW`; PHASE 0 `IN_PROGRESS`, pendiente de decisión del Director/asistente. |
| 2026-09-18 | CLIN-REFORM-PHASE0-CLOSEOUT | Baseline aceptado previo al cierre `ef378fddef3edaff07f603d965defa82365a80de`; cadena AUDIT02 `504bd136854518d301915d743911c5f0f60c7aa1` → `ef378fddef3edaff07f603d965defa82365a80de` | AUDIT02 aceptado; PHASE 0 `COMPLETE`, mapa factual y riesgos heredados aceptados. PHASE 1 autorizada para diseño/contrato, `NOT_STARTED`. Sin implementación de producto. |
| 2026-09-18 | CLIN-REFORM-PHASE1-MODEL01 | Baseline aceptado/checkpoint `a1dd2860f90094260c08388d363410198e5a7495` | Contrato de titularidad clínica y UX documentado para revisión; `PHASE_1_STATUS=IN_PROGRESS`, `PHASE_1_MODEL01=READY_FOR_DIRECTOR_REVIEW`. Seis decisiones del Director pendientes. Sin implementación ni diseño de esquema. |
| 2026-09-18 | CLIN-REFORM-PHASE1-MODEL01A | Baseline de trabajo/checkpoint `0ba07c9d8798ee6ecf03083453f5a587fff812b8`; último aceptado `a1dd2860f90094260c08388d363410198e5a7495` | Seis decisiones del Director ratificadas e incorporadas a la matriz/contrato; `PHASE_1_MODEL01=DIRECTOR_DECISIONS_RATIFIED_READY_FOR_FINAL_ACCEPTANCE`. PHASE 1 sigue `IN_PROGRESS`, pendiente aceptación final. Sin cambios runtime, API, esquema o datos. |
| 2026-09-18 | CLIN-REFORM-PHASE1-CLOSEOUT | Baseline aceptado previo al cierre `63d9e22403ce64ac8a49f2b06afe3f875724baa3`; MODEL01 `0ba07c9d8798ee6ecf03083453f5a587fff812b8` y MODEL01A `63d9e22403ce64ac8a49f2b06afe3f875724baa3` | MODEL01/MODEL01A aceptados; seis decisiones ratificadas; PHASE 1 `COMPLETE`, titularidad/ciclo de vida conceptual aceptados; PHASE 2 autorizada y `NOT_STARTED` para contrato/plan. Sin implementación. |
| 2026-09-18 | CLIN-REFORM-PHASE2-CONTRACT01 | Baseline aceptado/checkpoint previo `8698b1f66466867360651db5fa62e54d28fba797` | Contrato de ciclo de vida propuesto, implementación actual y brechas mapeadas, plan de 20 pruebas controladas documentado, cinco decisiones del Director identificadas; PHASE 2 `IN_PROGRESS`, CONTRACT01 `READY_FOR_DIRECTOR_REVIEW`. Sin cambios runtime, API, esquema, datos ni validación con escrituras. |
