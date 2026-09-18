# CLIN-REFORM-PHASE1-MODEL01 — Modelo de información clínica y contrato UX del Expediente

```text
CHAPTER=CLIN-REFORM-PHASE1-MODEL01
STATUS=DIRECTOR_DECISIONS_RATIFIED_READY_FOR_FINAL_ACCEPTANCE
PHASE_1_MODEL01=DIRECTOR_DECISIONS_RATIFIED_READY_FOR_FINAL_ACCEPTANCE
DATE=2026-09-18
STARTING_ACCEPTED_HEAD=a1dd2860f90094260c08388d363410198e5a7495
CONCEPTS_CLASSIFIED=68
DIRECTOR_DECISIONS_RATIFIED=6/6
DIRECTOR_DECISIONS_REQUIRED_COUNT=0
PRODUCT_IMPLEMENTATION=NONE
SCHEMA_MIGRATION=NONE
```

Este documento propone la titularidad lógica y el acceso médico a los datos. Las seis decisiones de producto del Director quedaron ratificadas en MODEL01A, pero el documento completo será autoridad detallada de PHASE 1 **sólo tras aceptación final del Director/asistente**; el [plan vivo](PLAN_REFORMA_EXPEDIENTE_CLINICO.md) conserva la autoridad de gobierno. Su base factual es [PHASE 0 AUDIT01/AUDIT02](EXPEDIENTE_CURRENT_STATE_AUDIT_PHASE0.md) y los principios del [Plan Maestro](../PLAN_MAESTRO_MXMED.md). Las columnas «destino» describen el contrato futuro, **no** tablas, endpoints, permisos ni migraciones existentes. `NO_VERIFICADO` no se eleva a hecho.

## Dominios y límites

| Dominio | Titularidad lógica | Regla de ciclo de vida |
| --- | --- | --- |
| `PATIENT` | Identidad y contexto clínico longitudinal del mismo paciente | Sobrevive a consultas; cambios vigentes conservan procedencia e historia cuando el dato clínico la exige. No se copia la identidad por consulta. |
| `ENCOUNTER` | Observación, juicio y plan de una atención concreta | Cada consulta crea su propia instancia clínica bajo `clinical_encounters`; lo previo es referencia histórica, no dato actual editable por defecto. |
| `EPISODE_CASE` | Proceso clínico significativo que agrupa varias consultas | Agrupa sin reemplazar encuentros individuales; embarazo, curso dental y posoperatorio son ejemplos. |
| `DOCUMENT` | Artefacto clínico versionado con autor, fecha, estado y contexto primario | Un documento conserva su identidad y procedencia aunque aparezca en varias vistas. Correcciones/firma requieren lifecycle explícito; nunca reescritura silenciosa de contenido histórico firmado. |
| `ADMINISTRATIVE` | Citas, cobro, recibos, facturas y operación | Puede relacionarse con la consulta, pero no determina la verdad ni la finalización clínica. Facturación permanece en su dominio fiscal. |

`DERIVED_VIEW` designa una proyección de sólo lectura compuesta desde las autoridades anteriores. `MIXED_REQUIRES_SPLIT` designa una superficie vigente que combina ciclos de vida distintos; no es una sexta autoridad. La autoridad clínica de consulta vigente es `clinical_encounters`; la identidad reside en `patients_*`; `clinical_documents` y `clinical_cases`/`clinical_case_items` siguen siendo autoridades existentes. La arquitectura del Plan Maestro `Agenda → Encounter → Actividad clínica → Clinical Documents → Timeline → Expediente` se conserva: los registros clínicos nuevos deben entrar por Actividad clínica y persistirse como documento clínico o converger a esa arquitectura. La matriz siguiente es lógica y deja la representación física para capítulos posteriores.

## Matriz de titularidad de campos y conceptos

**Lectura de la matriz.** `Actual` nombra autoridad comprobada en PHASE 0; `sin autoridad probada` significa que la UI o la idea existe sin persistencia canónica demostrada. `Nuevo encuentro`: `vacío` significa instancia nueva sin arrastre automático; `ref.` significa valor anterior visible sólo como referencia; `vigente` mantiene dato longitudinal; `n/a` no depende de iniciar consulta. `Histórico`: `versión` exige conservar versiones/correcciones; `instancia` exige preservar cada consulta; `fecha` exige fecha/procedencia; `actual` no acredita historia pasada. `Comparación`: `sí` sólo cuando aporta valor clínico, siempre con fecha y origen. `Documento`: `puede` expresa relación posible, no que todo dato sea hoy un documento. `Administración`: `enlace` nunca fusiona autoridades. `Especialidad`: `ext.` permite extensión validada; `base` pertenece al núcleo. `Estado`: `REGLA` sigue principios aceptados; `RATIFICADO` corresponde a las seis decisiones del Director incorporadas en MODEL01A, sin significar aceptación final del contrato; `PROPUESTA` espera esa aceptación final; `PRUEBA` necesita validación posterior. Ninguna fila autoriza un cambio de esquema.

| CONCEPT | CURRENT_SURFACE | CURRENT_AUTHORITY | TARGET_OWNER | TEMPORALITY | NEW_ENCOUNTER_BEHAVIOR | HISTORICAL_REQUIREMENT | COMPARISON_VALUE | DOCUMENT_RELATIONSHIP | ADMIN_RELATIONSHIP | SPECIALTY_EXTENSIBLE | DECISION_STATUS | NOTES |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Nombre estructurado | Datos Generales | `patients_patients`/`patients_profiles` | PATIENT | Identidad longitudinal | vigente | historial de corrección por definir | no | identifica autoría/sujeto | enlace demográfico | base | REGLA | No duplicar en cada consulta. |
| Fecha de nacimiento | Datos Generales | `patients_*` | PATIENT | Identidad longitudinal | vigente | historial de corrección por definir | no | referencia de sujeto | enlace demográfico | base | REGLA | La edad visible es derivada. |
| Sexo/género registrados | Datos Generales | `patients_*` | PATIENT | Identidad longitudinal | vigente | historial de corrección por definir | contextual | referencia de sujeto | no fusionar | ext. | PROPUESTA | No inferir atributos clínicos por etiqueta administrativa. |
| Contactos | Datos Generales | `patients_contacts` | PATIENT | Longitudinal mutable | vigente | versión/procedencia por definir | no | no primario | comunicación | base | PROPUESTA | Visibilidad/consentimiento siguen su autoridad. |
| Domicilio | Datos Generales | `patients_addresses` | PATIENT | Longitudinal mutable | vigente | versión/procedencia por definir | no | no primario | administración | base | PROPUESTA | No snapshot clínico automático. |
| Responsable/persona de contacto | Datos Generales | sin autoridad específica probada en PHASE 0 | PATIENT | Longitudinal mutable | vigente | versión/procedencia | no | posible consentimiento | comunicación | ext. | PRUEBA | Verificar representación actual antes de migrar. |
| Alergias revisadas | Historia/encabezado | borrador `clinical_record_entries`; proyección UI | PATIENT | Longitudinal clínica revisable | vigente + fecha de revisión | versión/autor | sí, si cambian | citar en receta/nota | no | base | PROPUESTA | No asumir que chip actual es autoridad canónica. |
| Medicación actual revisada | Historia/receta | chips de Historia o borrador de receta | PATIENT | Longitudinal clínica revisable | vigente + fecha de revisión | versión/autor/estado | sí | distinta de receta | no | base | RATIFICADO | Concepto de primera clase; sólo médico confirma actividad tras revisión explícita. |
| Problemas/condiciones activos | Historia/encabezado/timeline | sin autoridad única probada | PATIENT | Longitudinal clínica revisable | vigente + fecha de revisión | versión/autor/estado | sí | diagnósticos pueden aportar evidencia | no | ext. | RATIFICADO | Primera clase; diagnóstico de consulta no se promueve automáticamente. |
| Alertas clínicas relevantes | Encabezado/resumen candidato | sin autoridad única probada | DERIVED_VIEW | Vigente calculada con procedencia | recalcular | fuente y fecha visibles | contextual | enlace a fuente | no | base | RATIFICADO | Resumen de alertas confirmadas con fuente; sin segundo write store. |
| Seguimiento pendiente | Plan/documentos/citas | fuentes mezcladas; autoridad única no probada | MIXED_REQUIRES_SPLIT | Abierto hasta resolución | derivar o abrir tarea explícita | cierre/fecha/origen | sí | orden/plan fuente | cita enlazada | ext. | RATIFICADO | Estado derivado sin duplicado; tarea real con lifecycle OPEN/RESOLVED/CANCELED. |
| Antecedentes patológicos personales | Historia Clínica | borrador `clinical_record_entries` | PATIENT | Longitudinal revisable | vigente + revisión | versión/autor | sí | cita en nota | no | ext. | PROPUESTA | No sustituir antecedente por motivo actual. |
| Antecedentes familiares | Historia Clínica | borrador `clinical_record_entries` | PATIENT | Longitudinal revisable | vigente + revisión | versión/autor | sí | cita en nota | no | ext. | PROPUESTA | Contexto familiar, no acto de consulta. |
| Tabaquismo | Historia Clínica | borrador `clinical_record_entries` | PATIENT | Longitudinal revisable | vigente + revisión | versión/fecha | sí | cita en nota | no | ext. | PROPUESTA | Registrar cambios declarados. |
| Alcohol | Historia Clínica | borrador `clinical_record_entries` | PATIENT | Longitudinal revisable | vigente + revisión | versión/fecha | sí | cita en nota | no | ext. | PROPUESTA | Igual frontera que hábitos. |
| Sustancias | Historia Clínica | borrador `clinical_record_entries` | PATIENT | Longitudinal revisable | vigente + revisión | versión/fecha | sí | cita en nota | no | ext. | PROPUESTA | Sensibilidad/acceso requieren diseño posterior. |
| Dieta | Historia Clínica | borrador `clinical_record_entries` | PATIENT | Longitudinal revisable | vigente + revisión | versión/fecha | sí | cita en nota | no | ext. | PROPUESTA | Una recomendación de hoy pertenece al plan de consulta. |
| Actividad física | Historia Clínica | borrador `clinical_record_entries` | PATIENT | Longitudinal revisable | vigente + revisión | versión/fecha | sí | cita en nota | no | ext. | PROPUESTA | Distinguir hábito de indicación. |
| Vacunación | Historia Clínica | borrador `clinical_record_entries` | PATIENT | Longitudinal revisable | vigente + revisión | fecha/fuente | sí | eventual documento | no | ext. | PROPUESTA | No inferir dosis desde texto libre. |
| Historia ginecológica aplicable | Historia Clínica | borrador `clinical_record_entries` | PATIENT | Longitudinal revisable | vigente + revisión | versión/fecha | sí | cita en nota | no | ext. | PROPUESTA | Episodio obstétrico se modela aparte. |
| Motivo de consulta | Historia/encabezado/Actividad Clínica | borrador de paciente y fuentes derivadas | ENCOUNTER | Consulta puntual | vacío; previo ref. | instancia/autor/fecha | sí | nota del encuentro | cita opcional | base | REGLA | No reutilizar motivo previo como actual. |
| Padecimiento actual/evolución | Historia Clínica | borrador `clinical_record_entries` | ENCOUNTER | Consulta puntual | vacío; previo ref. | instancia/autor/fecha | sí | nota del encuentro | no | ext. | REGLA | El borrador actual mezcla ciclos de vida. |
| Interrogatorio por sistemas | Historia Clínica | borrador `clinical_record_entries` | ENCOUNTER | Consulta puntual | vacío; previo ref. | instancia/autor/fecha | sí | nota del encuentro | no | ext. | REGLA | Sin autofill como hallazgo de hoy. |
| Presión arterial | Exploración Física | borrador `clinical_record_entries` | ENCOUNTER | Medición puntual | vacío; previo ref. | fecha efectiva/autor | sí | nota/documento clínico | no | base | REGLA | No equiparar anterior con actual. |
| Frecuencia cardiaca | Exploración Física | borrador `clinical_record_entries` | ENCOUNTER | Medición puntual | vacío; previo ref. | fecha efectiva/autor | sí | nota/documento clínico | no | base | REGLA | Unidad explícita. |
| Frecuencia respiratoria | Exploración Física | borrador `clinical_record_entries` | ENCOUNTER | Medición puntual | vacío; previo ref. | fecha efectiva/autor | sí | nota/documento clínico | no | base | REGLA | Unidad explícita. |
| Temperatura | Exploración Física | borrador `clinical_record_entries` | ENCOUNTER | Medición puntual | vacío; previo ref. | fecha efectiva/autor | sí | nota/documento clínico | no | base | REGLA | Unidad y método si aplica. |
| Saturación de oxígeno | Exploración Física | borrador `clinical_record_entries` | ENCOUNTER | Medición puntual | vacío; previo ref. | fecha efectiva/autor | sí | nota/documento clínico | no | base | REGLA | Unidad explícita. |
| Dolor | Exploración Física | borrador `clinical_record_entries` | ENCOUNTER | Medición puntual | vacío; previo ref. | fecha efectiva/autor | sí | nota/documento clínico | no | base | REGLA | Escala/método deben conservarse. |
| Peso | Exploración Física | borrador `clinical_record_entries` | ENCOUNTER | Medición puntual | vacío; previo ref. | fecha efectiva/autor | sí | nota/documento clínico | no | base | REGLA | Tendencia longitudinal derivada. |
| Talla | Exploración Física | borrador `clinical_record_entries` | ENCOUNTER | Medición puntual | vacío; previo ref. | fecha efectiva/autor | sí | nota/documento clínico | no | ext. | REGLA | Repetición según contexto clínico. |
| Cintura | Exploración Física | borrador `clinical_record_entries` | ENCOUNTER | Medición puntual | vacío; previo ref. | fecha efectiva/autor | sí | nota/documento clínico | no | ext. | REGLA | Unidad explícita. |
| IMC | Exploración Física | cálculo en cliente de peso/talla | DERIVED_VIEW | Derivada de mediciones puntuales | recalcular sólo con datos válidos actuales | origen/fórmula/fecha | sí | mostrar en nota si procede | no | base | PROPUESTA | No mezclar peso actual con talla de origen oculto. |
| Hallazgo por sistema | Exploración Física | borrador `clinical_record_entries` | ENCOUNTER | Examen puntual | NOT_REVIEWED hasta acción | instancia/autor/fecha | sí | nota del encuentro | no | ext. | REGLA | `UNRECORDED != NORMAL`. |
| Normal/anormal por sistema | Exploración Física | radios/fallback `normal` en UI | ENCOUNTER | Estado explícito puntual | NOT_REVIEWED | instancia/autor/fecha | sí | nota del encuentro | no | ext. | REGLA | Sólo acción explícita establece NORMAL. |
| Hallazgo libre de exploración | Exploración Física | borrador `clinical_record_entries` | ENCOUNTER | Examen puntual | vacío | instancia/autor/fecha | sí | nota del encuentro | no | ext. | REGLA | No sobrescribir hallazgo previo. |
| Diagnóstico de consulta | Actividad Clínica/nota | `clinical_documents` y fuentes mixtas | ENCOUNTER | Juicio puntual | vacío; previo ref. | instancia/autor/fecha | sí | nota/documento fuente | no | ext. | PROPUESTA | No equivale automáticamente a problema activo. |
| Impresión clínica | Historia/nota | borrador/documento según flujo | ENCOUNTER | Juicio puntual | vacío; previo ref. | instancia/autor/fecha | sí | nota del encuentro | no | ext. | PROPUESTA | Conserva autor y evidencia. |
| Problemas abordados hoy | Historia/Actividad Clínica | fuentes mixtas | ENCOUNTER | Juicio puntual | vacío; previos ref. | instancia/autor/fecha | sí | nota/documento fuente | no | ext. | PROPUESTA | Puede referir problema longitudinal sin duplicarlo. |
| Plan terapéutico | Historia/Actividad Clínica | borrador/documento según flujo | ENCOUNTER | Plan de consulta | vacío; previo ref. | instancia/autor/fecha | sí | nota y acciones derivadas | no | ext. | REGLA | Versionar correcciones. |
| Recomendaciones | Actividad Clínica/nota | `clinical_documents` o texto de borrador | ENCOUNTER | Indicación puntual | vacío; previo ref. | instancia/autor/fecha | sí | nota/instrucción | no | ext. | PROPUESTA | Separar educación habitual de indicación actual. |
| Seguimiento indicado hoy | Plan/Actividad Clínica | borrador/documentos; cita aparte | ENCOUNTER | Plan de consulta | vacío; previos ref. | instancia/autor/fecha | sí | nota/orden | cita posible | ext. | PROPUESTA | Pendiente longitudinal es proyección del plan no resuelto. |
| Referencia/interconsulta indicada | Tratamiento/Documentos | `clinical_documents` si emitida | ENCOUNTER | Acción de consulta | nueva acción | documento/autor/fecha | sí | interconsulta única | cita posible | ext. | PROPUESTA | Artefacto emitido tiene dueño DOCUMENT. |
| Orden indicada | Estudios/Actividad Clínica | `clinical_documents` para lab/imagen | ENCOUNTER | Acción de consulta | nueva acción | orden/autor/fecha | sí | orden única | no | ext. | PROPUESTA | Orden emitida se registra como DOCUMENT. |
| Medicamento prescrito | Receta/Actividad Clínica | `clinical_documents` `prescription` | DOCUMENT | Artefacto emitido | nueva receta si procede | versión/autor/fecha | sí | receta única | no | base | REGLA | Prescribir no confirma consumo vigente. |
| Medicamento reportado por paciente | Historia/receta | chips/borrador sin autoridad separada | PATIENT | Longitudinal reportada | vigente + revisión | fuente/fecha/estado | sí | puede citarse | no | base | RATIFICADO | `REPORTED_BY_PATIENT` conserva origen; no equivale a `ACTIVE_CONFIRMED`. |
| Medicamento suspendido | Historia/receta | autoridad separada no probada | PATIENT | Longitudinal con cierre | estado cerrado | fecha/motivo/autor | sí | receta previa intacta | no | base | RATIFICADO | `DISCONTINUED` preserva recetas e historia anteriores. |
| Medicamento completado | Historia/receta | autoridad separada no probada | PATIENT | Longitudinal con cierre | estado cerrado | fecha/fuente | sí | receta previa intacta | no | base | RATIFICADO | `COMPLETED` preserva procedencia histórica. |
| Orden de laboratorio | Estudios Diagnóstico | `clinical_documents` en ruta inspeccionada | DOCUMENT | Artefacto de consulta | nueva orden | versión/autor/fecha | sí | orden única, vínculo encounter cuando proceda | no | ext. | PROPUESTA | No confundir orden con resultado. |
| Orden de imagen | Estudios Diagnóstico | `clinical_documents` en ruta inspeccionada | DOCUMENT | Artefacto de consulta | nueva orden | versión/autor/fecha | sí | orden única, vínculo encounter cuando proceda | no | ext. | PROPUESTA | Igual frontera que laboratorio. |
| Resultado de estudio | Estudios/Documentos | `clinical_documents`; vínculo variable | DOCUMENT | Artefacto recibido | adjuntar nuevo resultado | fecha/fuente/estado | sí | resultado distinto de orden | no | ext. | PROPUESTA | El resultado no sustituye la orden. |
| Resultado pendiente | Estudios/resumen candidato | órdenes/resultados sin proyección única probada | DERIVED_VIEW | Estado derivado | recalcular | orden y fecha fuente | sí | enlaza orden/resultado | no | ext. | RATIFICADO | Derivar de orden sin resultado; no crear registro pendiente duplicado. |
| Receta | Tratamiento/Actividad Clínica | `clinical_documents` | DOCUMENT | Artefacto emitido | nueva emisión | versión/autor/firma | sí | contexto encounter primario si procede | no | base | REGLA | Visible desde varias vistas, un registro. |
| Nota médica | Documentos/Actividad Clínica | `clinical_documents` | DOCUMENT | Artefacto de atención | nueva nota | versión/autor/firma | sí | contexto encounter primario | no | ext. | PROPUESTA | Nota no sustituye encounter. |
| Certificado | Documentos Clínicos | `clinical_documents` si generado | DOCUMENT | Artefacto emitido | nueva emisión | versión/autor/firma | contextual | contexto según motivo | no | ext. | PROPUESTA | Subtipos especiales actuales son placeholder. |
| Consentimiento informado | Documentos Clínicos | `clinical_documents` si generado | DOCUMENT | Artefacto autorizado | nuevo consentimiento | versión/firmas/fecha | contextual | paciente/encounter/episodio según acto | no | ext. | PROPUESTA | Multimedia actual es placeholder. |
| Interconsulta documental | Documentos Clínicos | `clinical_documents` si generada | DOCUMENT | Artefacto de referencia | nueva emisión | versión/autor/fecha | sí | encuentro origen | cita posible | ext. | PROPUESTA | Una sola identidad documental. |
| Informe médico | Documentos Clínicos | `clinical_documents` si generado | DOCUMENT | Artefacto emitido | nueva emisión | versión/autor/firma | contextual | contexto primario explícito | no | ext. | PROPUESTA | No asumir vínculo de encuentro universal. |
| Nota de alta | Documentos Clínicos | `clinical_documents` si generada | DOCUMENT | Artefacto de cierre | nueva emisión | versión/autor/firma | contextual | encounter/episodio según alcance | no | ext. | PROPUESTA | No equiparar alta a cobro. |
| Documento libre | Documentos Clínicos | placeholder de configuración | DOCUMENT | Artefacto futuro posible | n/a hasta contrato | versión/autor/firma | contextual | contexto por definir | no | ext. | PRUEBA | Subtipo futuro fuera de las seis decisiones; no tratar placeholder como flujo existente. |
| Embarazo | Casos/Historia aplicable | `clinical_cases`/`clinical_case_items` como autoridad genérica | EPISODE_CASE | Multi-encuentro | relacionar consulta si procede | caso + encuentros intactos | sí | documentos del caso | citas enlazables | ext. | PROPUESTA | Módulo obstétrico posterior. |
| Curso de tratamiento dental | Casos futuros | `clinical_cases` genérico; detalle no probado | EPISODE_CASE | Multi-encuentro | relacionar procedimientos | caso + encuentros intactos | sí | documentos del caso | citas enlazables | ext. | PROPUESTA | Sin expediente dental paralelo. |
| Curso posoperatorio | Casos futuros | `clinical_cases` genérico; detalle no probado | EPISODE_CASE | Multi-encuentro | relacionar seguimiento | caso + encuentros intactos | sí | documentos del caso | citas enlazables | ext. | PROPUESTA | No sustituye la visita. |
| Otro caso multi-encuentro | Casos/Historial | `clinical_cases`/`clinical_case_items` | EPISODE_CASE | Multi-encuentro | asociar si tiene sentido clínico | caso + encuentros intactos | sí | documentos del caso | citas enlazables | ext. | RATIFICADO | Término visible «Caso clínico»; no es obligatorio por consulta. |
| Cita | Agenda/Historial derivado | `agenda_appointments` | ADMINISTRATIVE | Operación fechada | referencia opcional | estado/fecha Agenda | contextual | no es documento clínico | autoridad Agenda | base | RATIFICADO | Estado secundario sólo lectura con enlace y permisos; no inicia consulta. |
| Pago/cargo | Finanzas/billing | autoridad financiera separada | ADMINISTRATIVE | Transacción | sin efecto clínico | ledger/fecha | no | no es documento clínico | autoridad financiera | base | RATIFICADO | Estado secundario sólo lectura; pago no finaliza consulta. |
| Recibo | Facturación/finanzas | autoridad fiscal/financiera separada | ADMINISTRATIVE | Artefacto fiscal | sin efecto clínico | folio/fecha | no | no es documento clínico | autoridad fiscal | base | RATIFICADO | Enlace contextual autorizado; no es documento clínico. |
| Factura | Facturación | autoridad billing/CFDI separada | ADMINISTRATIVE | Artefacto fiscal | sin efecto clínico | folio/fecha/estado | no | no es documento clínico | autoridad fiscal | base | RATIFICADO | Enlace contextual autorizado; CFDI pendiente no mantiene consulta abierta. |

Las superficies mixtas actuales requieren separación semántica: Historia Clínica = antecedentes `PATIENT` + narrativa `ENCOUNTER`; Exploración Física = observaciones `ENCOUNTER` + tendencia `DERIVED_VIEW`; Tratamiento/Recetas = plan `ENCOUNTER` + receta `DOCUMENT` + medicación vigente `PATIENT`; Estudios = orden y resultado `DOCUMENT` + pendientes `DERIVED_VIEW`; Encabezado/resumen = proyección `DERIVED_VIEW`; Agenda/Historial = relación `ADMINISTRATIVE`/`ENCOUNTER`; hospitalización = proceso que requiere contrato posterior sin equipararse automáticamente a consulta ni a caso. Estas divisiones no mueven datos actuales.

## Reglas de consulta, mediciones y exploración

Una nueva consulta abre una instancia clínica nueva. Motivo, evolución, interrogatorio, mediciones, exploración, valoración y plan comienzan sin valor actual. Cada consulta previa permanece históricamente identificable; una corrección posterior exige un mecanismo explícito de versión/enmienda que preserve el contenido anterior, sobre todo si está firmado. Se puede **mostrar** un valor anterior con fecha, médico y procedencia; no copiarlo silenciosamente como captura de hoy. Cerrar la vista del paciente no finaliza el encounter y abrir el expediente no lo inicia.

El contrato lógico de cada medición de encuentro incluye `patient`, `doctor`, `encounter`, `measurement_type`, `value`, `unit`, `effective_datetime`, `recorded_datetime` y `provenance/source`; una corrección conserva historial. No prescribe tablas. La validación física de dos consultas y del almacenamiento final corresponde a fases posteriores. El IMC es derivado de peso y talla con procedencia compatible, no una medición independiente sin fuente.

Ejemplo de presentación:

```text
Peso anterior: 78.0 kg · 10 jun 2026 · consulta anterior
Peso de hoy: [vacío hasta registrar]
```

Una futura acción «usar anterior como referencia/copiar intencionalmente», si se aprueba clínicamente, exige acción explícita y conserva la procedencia; copiar no afirma que se midió hoy. En exploración por sistema el estado lógico es `NOT_REVIEWED`, `NORMAL` o `ABNORMAL`. El control intacto queda `NOT_REVIEWED`, jamás `NORMAL`; texto libre sin revisión explícita tampoco demuestra normalidad. Esta regla corrige conceptualmente el riesgo de fallback «normal» observado en fuente, sin cambiarlo todavía.

## Resumen longitudinal y medicación

`PATIENT_SUMMARY=DERIVED_VIEW`. Al abrir un paciente existente, antes de iniciar consulta, el resumen muestra conceptualmente y **en este orden aprobado**:

1. **Alertas clínicas/alergias:** sólo alertas significativas confirmadas o validadas, con fuente y fecha. Sin evidencia confirmada, mostrar «SIN DATO CONFIRMADO», nunca «normal» o «ninguna» por inferencia.
2. **Última consulta:** fecha, médico si es atribuible, motivo o conclusión breve si existe y acceso a esa consulta histórica. Un encuentro legacy sin médico conserva atribución desconocida.
3. **Medicación actual:** sólo estado longitudinal revisado/confirmado por la futura autoridad canónica; la receta más reciente no lo prueba.
4. **Problemas activos:** problemas longitudinales promovidos explícitamente; un diagnóstico de consulta no se vuelve activo automáticamente.
5. **Pendientes:** resultados sin resolver derivados de sus fuentes y tareas clínicas verdaderas con lifecycle explícito cuando corresponda; no se duplican fuentes para llenar el resumen.
6. **Próxima cita:** cita futura si existe, identificada como autoridad de Agenda; no es consulta hasta que exista un encounter.

Cada elemento enlaza a su autoridad original y muestra procedencia, revisión y estado cuando aplica. Todos los cambios se hacen en la autoridad correspondiente: no se aprueba tabla de resumen ni otro write store clínico.

`PRESCRIPTION` es la orden/documento emitido en una consulta. `CURRENT_MEDICATION` es un **concepto clínico longitudinal de primera clase** distinto de la receta y del chip de un borrador. Sus estados lógicos aprobados son `ACTIVE_CONFIRMED`, `REPORTED_BY_PATIENT`, `PRESCRIBED_NOT_CONFIRMED_ACTIVE`, `DISCONTINUED` y `COMPLETED`. Cada estado conserva medicamento, fuente/procedencia, `reviewed_at` y `reviewed_by`; transiciones y estados históricos clínicamente relevantes no se sobrescriben en un único valor mutable. `CURRENT_MEDICATION_CONFIRMATION_AUTHORITY=PHYSICIAN`: la confirmación de actividad exige revisión clínica explícita del médico. Una delegación futura a personal clínico requiere decisión y auditoría propias. Emitir receta no confirma uso vigente; el reporte del paciente conserva su origen; suspensión y finalización no eliminan recetas ni historial.

Los **problemas activos** también son un concepto longitudinal de primera clase. Promover un diagnóstico de consulta requiere acción clínica intencional; nunca ocurre automáticamente. Cada problema conserva condición, estado, inicio si se conoce, fuente, última revisión, médico revisor y resolución/inactivación cuando corresponda. La revisión y los cambios de estado deben ser recuperables históricamente; no se define almacenamiento físico aquí.

`PENDING_ITEMS=SOURCE_LINKED_WITH_EXPLICIT_LIFECYCLE_WHEN_TASK_REQUIRED`. Un **pendiente derivado** (por ejemplo orden de laboratorio sin resultado) se calcula desde orden/resultado y no crea otro registro editable para mostrar «RESULTADO PENDIENTE». Una **tarea clínica real** (revisar resultado, contactar al paciente, programar seguimiento clínico o verificar respuesta terapéutica) puede requerir una instancia explícita con `OPEN`, `RESOLVED` o `CANCELED`, fuente, responsable, fecha de creación, fecha objetivo si aplica y procedencia de resolución. La cita relacionada continúa en Agenda; el modelo físico de tareas queda para otra fase.

## Documentos, episodios y administración

Cada documento clínico tiene una identidad única, autor/atribución, fechas, estado, versión y un **contexto clínico primario** conceptual: `PATIENT_DOCUMENT`, `ENCOUNTER_DOCUMENT` o `EPISODE_DOCUMENT`. Puede aparecer en detalle de consulta, búsqueda documental del paciente y timeline sin duplicar registros. Una receta de la consulta A sigue siendo el mismo documento en esas tres vistas. El contexto primario no niega relaciones secundarias de consulta/caso/paciente, pero no debe inventarse para legacy sin evidencia. El vínculo a encuentro hoy no es universal en `clinical_documents`; la política de retroenlace, firma, corrección y migración queda abierta.

Un `EPISODE_CASE` se presenta al médico con el término general aprobado **«Caso clínico»**. Agrupa varias consultas sólo cuando un proceso lo justifique; embarazo, rehabilitación dental y seguimiento posoperatorio son ejemplos, no subexpedientes. Un encuentro ambulatorio aislado es válido sin caso: `CASE_REQUIRES_EVERY_ENCOUNTER=false`. Cada consulta del caso conserva fecha, médico, observaciones, valoración, plan y documentos propios; el caso no sustituye el historial de consultas. `clinical_cases`/`clinical_case_items` son la autoridad genérica actual; membresía y almacenamiento físico requieren contrato posterior.

Una consulta **puede referenciar** una cita y mostrar un estado operativo compacto, secundario y **sólo de lectura**, con enlaces a las autoridades respectivas: cita, pago, recibo y factura. Por ejemplo, «Cita hoy 10:30 · Pago registrado · Recibo disponible · Factura pendiente»; esta vista no incluye formularios fiscales ni cambia estados clínicos. Los enlaces permiten ver la cita, el recibo o la factura en su módulo. La cita sigue siendo Agenda; cobro y CFDI siguen siendo finanzas/facturación. La visibilidad depende de permisos/capacidades canónicos ya existentes; MODEL01A no crea roles nuevos.

`FINALIZE_ENCOUNTER != COLLECT_PAYMENT`; `FINALIZE_ENCOUNTER != ISSUE_INVOICE`; `INVOICE_PENDING != ENCOUNTER_OPEN`; `PAYMENT_COMPLETED != CLINICAL_COMPLETION`; `RECEIPT != CLINICAL_DOCUMENT`; `INVOICE != CLINICAL_DOCUMENT`. La ausencia de datos administrativos no impide registrar o finalizar atención clínica.

## Estados UX para el trabajo cotidiano

| Estado | Propósito y contenido conceptual | Acción / límite |
| --- | --- | --- |
| `PATIENT_OPEN_NO_ACTIVE_ENCOUNTER` | Orientación en orden aprobado: alertas/alergias, última consulta con médico atribuible, medicación confirmada, problemas activos, pendientes y próxima cita. Estado neutro «SIN DATO CONFIRMADO» cuando falta evidencia. | Acción principal «Iniciar consulta». Abrir paciente, navegar o cambiar pestaña no crea encounter. |
| `ACTIVE_ENCOUNTER` | Workspace de hoy: motivo/evolución, mediciones, exploración, valoración, plan, documentos/acciones y finalización. Lo anterior se consulta en panel de referencia identificado. | Guardado/finalización según contrato posterior; no precargar observaciones como actuales. |
| `HISTORICAL_ENCOUNTER` | Lectura de fecha, médico, motivo, mediciones, hallazgos, valoración, plan y documentos **propios de esa consulta**. Legacy sin doctor permanece ambiguo. | Lectura enfocada; enmienda explícita futura, nunca edición silenciosa ni copia automática a hoy. |
| `LONGITUDINAL_REVIEW` | Timeline del paciente, tendencias de medidas, prescripciones, documentos/resultados y episodios con filtros y procedencia. | Navega a autoridad original; no crea una segunda autoridad de escritura. |

Estos estados no son pantallas aprobadas ni especificación visual final; describen propósito, titularidad y límites de interacción.

## Navegación conceptual frente a las pestañas actuales

`CURRENT_NAVIGATION_PROBLEM`: las pestañas actuales mezclan identidad de paciente, capturas de consulta, documentos, episodios y placeholders al mismo nivel; Historia/Exploración pueden presentar borradores del paciente como si fueran de una visita. `TARGET_NAVIGATION_PRINCIPLE`: la navegación del paciente responde «¿qué necesito saber de este paciente a lo largo del tiempo?»; el workspace de consulta responde «¿qué estoy documentando y haciendo en esta consulta?». Son propósitos separados. Ningún cambio de pestañas se hace en MODEL01A.

| Área de alto nivel ratificada conceptualmente | Propósito | Relación con pestañas actuales |
| --- | --- | --- |
| Resumen | Orientación longitudinal derivada | Reúne contexto sin duplicar Datos Generales ni Historia. |
| Consultas / Historial | Consulta activa y encuentros históricos | Historial conserva autoridad; Exploración y narrativa de hoy pasarían conceptualmente al workspace de consulta. |
| Antecedentes | Información clínica longitudinal revisable | Separa la parte longitudinal de Historia Clínica de la narrativa de hoy. |
| Estudios y documentos | Órdenes, resultados y artefactos con búsqueda | Une acceso, no almacenamiento; receta sigue documento único. |
| Administración | Identidad/contacto y vínculos operativos según permisos | Datos Generales permanece longitudinal; Agenda y billing conservan autoridad. |

`ACTIVE_ENCOUNTER_WORKSPACE=SEPARATE_CONTEXT`: el workspace de consulta activa es un contexto de captura distinto de las cinco áreas del paciente, no un sexto tab. Contiene conceptualmente motivo/evolución, mediciones, exploración, valoración, plan, documentos/acciones y finalización. Los riesgos de implementación siguen siendo descubribilidad del historial, continuidad de rutas/deep links, permisos doctor/paciente, traducción de borradores legacy, accesibilidad, carga cognitiva y falsa equivalencia de «documento» con «dato clínico». La ratificación conceptual no autoriza prototipo ni cambio de runtime.

`RATIFIED_HIGH_LEVEL_AREAS=RESUMEN,CONSULTAS_HISTORIAL,ANTECEDENTES,ESTUDIOS_DOCUMENTOS,ADMINISTRACION`. `RATIONALE`: separar orientación longitudinal, captura de la consulta y acceso a artefactos sin duplicar autoridades. `RISKS`: transición de rutas, descubribilidad, permisos, legado y accesibilidad; requieren validación antes de cualquier cambio de navegación.

| Pestaña actual | Destino conceptual ratificado; sin reubicación de runtime |
| --- | --- |
| Datos Generales | Identidad/contacto `PATIENT` en área de paciente/administración, no dentro de cada consulta. |
| Exploración Física | Captura `ENCOUNTER` en workspace de consulta; tendencias visibles en revisión longitudinal. El borrador actual requiere estrategia legacy. |
| Historia Clínica | Dividir antecedentes longitudinales `PATIENT` de motivo/evolución/interrogatorio `ENCOUNTER`; no duplicar el borrador vigente. |
| Tratamiento / Recetas | Dividir plan `ENCOUNTER`, receta `DOCUMENT` y medicación vigente `PATIENT`. |
| Manejo Hospitalario | Workflow/capacidad especializada, no tab universal del paciente por defecto; contexto y autorización siguen abiertos. |
| Archivo | Placeholder; acceso futuro converge con búsqueda real de documentos/archivos, sin autoridad paralela. |

Nombres y áreas conceptuales están ratificados por el Director. Implementación visual, rutas, accesibilidad, permisos y transición legacy todavía requieren capítulos separados; ninguna pestaña se elimina o mueve en MODEL01A.

## Extensión por especialidad

Frontera: `CORE PATIENT MODEL + CORE ENCOUNTER MODEL + SPECIALTY MODULE + ENCOUNTER TYPE + PHYSICIAN PREFERENCES`. Pediatría puede añadir vistas de crecimiento/desarrollo sobre medidas con fecha; obstetricia puede agrupar encuentros en episodio de embarazo; odontología puede relacionar odontograma/procedimientos con un curso de tratamiento y consultas individuales. Ninguna especialidad crea identidad paralela, encuentro alterno o documento duplicado. Modelo físico, esquema, reglas clínicas, validación por especialidad y UX específicos quedan fuera de MODEL01.

## Tratamiento futuro de autoridades actuales y datos legacy

| Autoridad / superficie actual | Clasificación de tratamiento | Motivo y prueba pendiente |
| --- | --- | --- |
| `patients_*` identidad/contacto | KEEP_AS_IS | Preservar autoridad canónica; diseñar historia de correcciones clínicas sólo si corresponde. |
| `clinical_encounters` | KEEP_AS_IS | Consulta canónica; probar integridad y atribución en PHASE 2. |
| `clinical_documents` | KEEP_AND_REPRESENT | Documento único con varias vistas; vínculo primario y versiones requieren contrato/QA posterior. |
| `clinical_cases`/`clinical_case_items` | KEEP_AND_REPRESENT | Caso genérico actual; término visible «Caso clínico» ratificado, membresía y persistencia física pendientes. |
| `clinical_record_entries` Historia/Exploración | SPLIT_CONCEPT | Mezcla ciclos de vida; los borradores legacy no se transforman sin prueba de integridad/autor. |
| Datos longitudinales clínicos sin autoridad propia, incluida medicación vigente | MIGRATE_LATER | Medicación y problemas activos son conceptos de primera clase ratificados; migración sólo con evidencia, sin asumir chips como estado confirmado. |
| `agenda_appointments` | KEEP_AS_IS | La cita conserva su autoridad administrativa; vínculo a encounter opcional. |
| Billing, pagos, recibos y CFDI | KEEP_AS_IS | Autoridad fiscal/financiera separada. |
| Hospital stay API/UI | REQUIRES_PHASE2_PROOF | Resolver scope médico/vínculo y contexto antes de integrar; capacidad local deshabilitada. |
| Pestaña Archivo sin flujo | PLACEHOLDER_REMOVE_LATER | Retiro condicionado a navegación y búsqueda documental aprobadas. |
| Pestañas de captura longitudinal/consulta mezcladas | DEPRECATE_LATER | Destino conceptual ratificado; sólo tras probar reemplazo, acceso legacy y migración. No se eliminan ahora. |

Los 13 encuentros legacy sin `doctor_id` observados en PHASE 0 conservan atribución **ambigua**; no se infiere médico a partir de cita, sesión o documento. Los borradores clínicos por paciente pueden requerir migración posterior o mantenerse como snapshots legacy identificados, nunca convertirse automáticamente en encuentros históricos. Preguntas abiertas: cuál es la procedencia verificable de cada borrador; si existe una fecha efectiva fiable por dato; cómo exponer consultas sin médico sin atribución falsa; cómo vincular los documentos sin encuentro; qué hacer con firma/enmiendas; y qué controles hacen segura la ejecución de GET clínicos antes de QA física. Ninguna respuesta se inventa en MODEL01.

## DIRECTOR_DECISIONS_RATIFIED

```text
DECISION_01_PATIENT_SUMMARY=APPROVED
PATIENT_SUMMARY=DERIVED_VIEW
DECISION_02_NAVIGATION=APPROVED
ACTIVE_ENCOUNTER_WORKSPACE=SEPARATE_CONTEXT
DECISION_03_CURRENT_MEDICATION=APPROVED_FIRST_CLASS_LONGITUDINAL_CONCEPT
CURRENT_MEDICATION_CONFIRMATION_AUTHORITY=PHYSICIAN
DECISION_04_ACTIVE_PROBLEMS=APPROVED_FIRST_CLASS_LONGITUDINAL_CONCEPT
PENDING_ITEMS=SOURCE_LINKED_WITH_EXPLICIT_LIFECYCLE_WHEN_TASK_REQUIRED
DECISION_05_EPISODE_VISIBLE_TERM=CASO_CLINICO
CASE_REQUIRES_EVERY_ENCOUNTER=false
DECISION_06_ADMIN_IN_CLINICAL_WORKSPACE=COMPACT_READ_ONLY_STATUS_WITH_LINKS
DIRECTOR_DECISIONS_RATIFIED=6/6
DIRECTOR_DECISIONS_REQUIRED_COUNT=0
```

Estas seis decisiones de producto están ratificadas; no quedan decisiones materiales **de este grupo** pendientes. «Documento libre» sigue siendo placeholder y requiere prueba/contrato posterior de subtipo, no una séptima decisión de MODEL01A. Los riesgos técnicos de PHASE 0 siguen abiertos en el plan vivo. `PHASE_1_MODEL01=DIRECTOR_DECISIONS_RATIFIED_READY_FOR_FINAL_ACCEPTANCE`: la ratificación de decisiones no equivale a aceptación final de todo MODEL01 ni al cierre de PHASE 1. No se diseña esquema, migración, API ni UI ejecutable.
