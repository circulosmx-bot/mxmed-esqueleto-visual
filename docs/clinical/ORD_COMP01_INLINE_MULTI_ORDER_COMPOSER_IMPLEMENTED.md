# ORD-COMP01 — compositor inline y emisión de órdenes independientes

Implementado sobre `b156e92deec3294648510bd773af7afad472d615`, rama `ux/consultation-step2-vitals-r1`, 2026-10-03. El preflight encontró HEAD/remoto iguales y worktree limpio. Runtime de revisión: <http://127.0.0.1:18148/>.

## Autoridades auditadas antes de implementar

| Autoridad | Hallazgo y preservación |
| --- | --- |
| Catálogo | `clinical_study_types`, reader `clinical_study_catalog_read.php`; 202 activas antes y 208 después de seis adiciones de datos. |
| HIER03 | Configuración V2 de raíz/familias/subfamilias y reordenamiento por especialidad; se mantiene. |
| TAX03C | `mxmedStudyComposer.mount` tenía búsqueda, selección, deduplicación y FDI dentro de un modal OR05. Ahora soporta EMBEDDED y mantiene compatibilidad para callers existentes. |
| Escritor individual | `POST doctors/{doctor}/patients/{patient}/documents`, `ClinicalEncounterIntegrityService::idempotentCreate`, `clinical_v1_document_insert`. Continúa disponible sin cambios semánticos. |
| Transacción | `ClinicalIdempotentCreateExecutor` abre una transacción; `mxmed_persist_clinical_document_in_transaction` usa la misma conexión y no hace commit propio. |
| Ítems | `clinical_study_order_snapshot` verifica ID/key activo y parámetros; `clinical_study_normalize_order_payload` genera UUIDs de ítem y payload V2. |
| Impresión | `clinical_portable_issue_payload` congela emisor; HTML/PDF usan los endpoints y renderer ORDPRINT existentes. |
| Resultados | RES02A y OR02B/REL01 conservan fuente exacta, versión e ítems. No se introduce vínculo de resultado al lote. |
| Proveedor | `HealthcareStudyInteropService::sendReferral` verifica un UUID/versión e ítems exactos y oferta real del proveedor. No usa el grupo del compositor para elegibilidad. |
| Guard | VIS24 registra la composición `ordcomp01-composition`; selección, personalizados y parámetros son progreso local. |

## Selector y estado

En el flujo nuevo de Expediente, todas las hojas médicas y dentales abren inline. Se eliminó el wrapper modal de creación de OR05. TAX03C conserva **una sola** colección `selected`, deduplicación por ID y edición FDI. Otros callers pueden seguir montándolo en su propio modal. HIER03 no se aplana.

Escritorio: CSS grid `minmax(0,1.62fr) minmax(0,1fr)` (61.83% / 38.17%, sin contar gutter), sin anchuras dependientes de una pantalla. Resumen sticky con `--mm-global-header-h` del shell; catálogo en flujo normal, sin el límite de 190 px del modal. Móvil <768 px: una columna, barra sticky inferior con conteos y alternancia catálogo/resumen **inline**, sin bottom sheet. La barra ocupa espacio en el flujo. El resumen no tapa el pie ni el encabezado fijo.

Cada hoja muestra contexto, búsqueda y una salida explícita al catálogo global. El texto buscado no amplía automáticamente su alcance. Si hay destacados curados se muestran hasta seis; hoy Orina tiene seis. Las demás hojas comienzan con búsqueda y “Ver catálogo completo”, cerrado. Los grupos usan las hojas HIER03 de descubrimiento (partición de presentación con claves ya conocidas), y categorías solo para claves no cubiertas, como dental. Orina tiene cinco grupos específicos declarativos. Se filtran por disponibilidad activa. Abrir un grupo cierra el anterior; cerrar un acordeón no altera la selección.

El panel **ÓRDENES EN PREPARACIÓN** agrupa por autoridad operativa, una tarjeta por documento futuro. De una a tres órdenes aparecen expandidas; con cuatro o más comienzan compactas. Una tarjeta con edición dental abierta permanece expandida. Retirar usa nombres accesibles específicos; FDI/CBCT se edita inline. No hay confirmación por familia ni impresión de borradores. Desde ORD-COMP02 cada tarjeta muestra “Revisar orden”; con varias órdenes se ofrece además “Revisar todas las órdenes”. Se retiró el CTA global “Continuar con N órdenes”.

**Actualización ORD-COMP02 (2026-10-03).** El módulo y la pestaña de Expediente muestran “Estudios de diagnóstico”; `orders`, `t-estudios`, rutas y tipos documentales mantienen sus identificadores. El hub conserva las tres intenciones “Solicitar estudios”, “Revisar órdenes pendientes” y “Ver resultados e historial”. “+ Agregar estudios” abre un selector inline: primero ofrece más estudios de la familia HIER03 actual (obtenida de su ID estable), y luego las otras familias raíz activas. La primera opción vuelve a la pantalla de subfamilias de esa familia; las otras usan la navegación HIER03 existente. En perfil dental ofrece “+ Más estudios dentales” y la salida al catálogo global, sin imponer la raíz médica. “Cerrar” recupera el foco en el disparador. La selección, datos de muestra, parámetros dentales, estudio personalizado en borrador e indicaciones capturadas permanecen al navegar. Son transiciones internas y no disparan VIS24; salir del espacio del paciente continúa protegido.

**Actualización ORD-COMP02-R1 (2026-10-03).** La revisión individual y “Revisar todas las órdenes” abren un `<dialog>` modal sobre el compositor, que permanece montado con su hoja, acordeones y selección. El título nombra el servicio o el número de órdenes. ×, Escape y clic en el fondo cierran la revisión y devuelven foco y posición de página al compositor. Indicación y prioridad se actualizan en `metadata` al editar, por lo que cerrar el modal conserva el borrador sin un guardado adicional. El diálogo tiene ciclo explícito de Tab/Shift+Tab para mantener el foco dentro. Las muestras incompletas se advierten en el modal y se validan otra vez antes de emitir. La emisión de una orden o del lote completo sigue en el mismo writer y la vista de éxito conserva Imprimir/Descargar PDF. El selector contextual reemplaza el botón “+ Agregar estudios” mientras está abierto; “Cerrar” repone exactamente ese botón. Abrir/cerrar el selector no modifica el borrador.

**Actualización ORD-COMP02-R2 (2026-10-03).** En el compositor inline, “¿No encuentras el estudio? Agregar estudio no catalogado” aparece como vínculo discreto al final de “Ver catálogo completo” solo mientras este está expandido; al cerrarlo se oculta. El formulario y su borrador siguen montados durante búsquedas y cambios de hoja. Los estudios elegidos tienen nombre oscuro en negrita, acento y fondo tenues, información secundaria debajo cuando aplica, y el control × conserva su área táctil. La revisión presenta un grupo nativo de radios “Rutinaria” / “Urgente” por orden, con “Rutinaria” como valor inicial; el borrador y el escritor siguen usando exactamente esos valores. Una prioridad previa no compatible exige una selección explícita. La impresión histórica sigue interpretando `priority_stat` como “Prioridad (STAT)”, sin añadirlo como opción para nuevas órdenes. QA de navegador en 1440×900, 1366×768 y 390×844, más writer/impresión en base desechable.

La revisión de una tarjeta muestra solo su orden operativa y permite editar indicación/prioridad sin emitir. Si hay varias tarjetas, esta vista es de revisión y edición: “Revisar todas las órdenes” conduce a la emisión atómica existente; “Volver a seleccionar estudios” conserva las demás órdenes. Con una sola tarjeta, “Generar orden” usa el mismo endpoint de lote con una orden. El body/UUID congelado y la recuperación idempotente tras un resultado incierto permanecen iguales. No hay emisión parcial de un lote de varias órdenes.

Volver a familias conserva selección, personalizados aún no agregados y parámetros. El resumen de la jerarquía permite recuperar la sesión. VIS24 protege navegación externa, pestañas, paciente y unload; descartar limpia solo estado local. Un fallo de resultado incierto bloquea edición/salida hasta reintentar el mismo lote: evita ofrecer una nueva emisión cuando la primera pudo haber confirmado. Un 4xx definitivo de validación permite corregir, sin perder la revisión. Los errores llevan la clave del grupo y se muestran en su tarjeta.

## Autoridad operativa V1

`modules/clinical/catalog/study_order_routing_v1.json` es **la misma autoridad** consumida por PHP y navegador. Enumera cada clave explícitamente: no hay inferencia por nombre libre ni un fallback silencioso por categoría. Toda clave activa debe resolver a un grupo. Cero claves sin asignar, cero asignaciones primarias múltiples, ninguna `ROUTING_REVIEW_REQUIRED`.

Los grupos definen separación de órdenes, **no elegibilidad** del proveedor. La futura elegibilidad sigue requiriendo ofertas exactas `study_type_id`, ubicación, área de servicio y verificación vigente. Ecocardiogramas/Doppler se descubren en imagenología y se emiten en cardiovascular. Imágenes dentales se emiten en dental. CPET/oximetría nocturna permanecen con función pulmonar por su ejecución funcional; PSG/HSAT/MSLT/MWT en sueño. Broncoscopía/EBUS/pleuroscopía se separan de endoscopia digestiva, y laringoscopía corresponde a ORL. No se implementa descubrimiento/envío de proveedor.

| Grupo | Nombre | Estudios activos |
| --- | --- | ---: |
| `CLINICAL_LAB` | Laboratorio clínico | 99 |
| `GENETICS_MOLECULAR` | Genética y diagnóstico molecular | 12 |
| `PATHOLOGY_CYTOLOGY` | Patología y citología | 2 |
| `GENERAL_IMAGING` | Imagenología | 34 |
| `CARDIOVASCULAR_DIAGNOSTICS` | Cardiología diagnóstica | 13 |
| `NEUROPHYSIOLOGY` | Neurofisiología | 9 |
| `PULMONARY_FUNCTION` | Función pulmonar | 9 |
| `SLEEP_DIAGNOSTICS` | Medicina del sueño | 5 |
| `AUDIOLOGY_VESTIBULAR` | Audiología y función vestibular | 5 |
| `DIGESTIVE_ENDOSCOPY` | Endoscopia digestiva | 9 |
| `RESPIRATORY_DIAGNOSTIC_PROCEDURES` | Procedimientos respiratorios | 3 |
| `ENT_DIAGNOSTICS` | Diagnóstico otorrinolaringológico | 1 |
| `DENTAL_DIAGNOSTICS` | Diagnóstico dental | 7 |

Un estudio personalizado debe confirmar un grupo de la lista controlada al agregarlo. Si toda la hoja comparte un servicio, se sugiere ese grupo; puede cambiarse. En contexto global o ambiguo no se sugiere ninguno: selección explícita obligatoria. La categoría del estudio no decide por sí sola el servicio. La solicitud envía `custom_routing_confirmed=true`; el servidor la exige. No se analiza el nombre libre para decidir proveedor/grupo.

## Revisión y emisión

Cada orden tiene indicación (hasta 2000 caracteres) y prioridad `Rutinaria`/`Urgente` independientes. El médico puede volver a editar. Se mantiene el alcance **patient-level** del creador general existente; no toma una consulta abierta, cita u hospitalización ambientales. El servidor rechaza campos ajenos al contrato. No se altera creación desde otros contextos.

Endpoint aditivo:

`POST /api/clinical/index.php/doctors/{doctor_id}/patients/{patient_id}/orders/batch`

Requiere sesión del doctor de la ruta, vínculo activo con paciente, JSON y `Idempotency-Key` igual al UUID V4 `order_composition_batch_uuid`. Body:

```json
{
  "order_composition_batch_uuid": "<uuid-v4>",
  "order_routing_version": 1,
  "orders": [{
    "order_routing_group_key": "CLINICAL_LAB",
    "priority": "Rutinaria",
    "indication": "Motivo clínico",
    "order_items": [{"study_type_key": "urinalysis"}]
  }]
}
```

El coordinador valida versión, grupos únicos, estudios activos, ID/key coherente, parámetros dentales, deduplicación global, personalizados y límites (100 estudios en toda la composición). Valida todas las órdenes antes de insertar. Reutiliza el writer canónico para cada documento en la misma transacción. Una falla antes del commit, incluso después de la primera inserción, revierte documentos y ledgers.

Idempotencia usa el ledger existente, operación canónica `CREATE_ENCOUNTER_DOCUMENT`, con espacios de clave separados: `ordcomp01.batch.<uuid>` para el conjunto y `ordcomp01.order.<sha256(uuid:index)>` para cada orden. La clave del conjunto referencia el primer documento; el conjunto completo se resuelve desde las referencias durables de los hijos y sus hashes. No se usa una búsqueda aproximada por payload, fecha o título. Repetir el mismo body devuelve IDs/UUIDs idénticos; cambiarlo con la misma clave da 409. El replay lee documentos ya emitidos sin reinterpretar el catálogo, incluso si una prueba fue desactivada después. El cliente conserva body y clave durante reintentos inciertos.

Cada documento congela `order_routing_group_key`, `order_routing_version`, `order_composition_batch_uuid` en su payload extensible existente. Conserva UUID propio, versión 1 inicial, emisor, ítems canónicos y parámetros. El batch UUID es asociación de auditoría; nunca identidad clínica ni enlace de cobertura de resultados. No hay tablas nuevas ni cambios relacionales. Sí hay extensión aditiva de contrato de producto/API y modelo de escritura.

La pantalla “Órdenes generadas” muestra referencia, estudios y estado por documento; solo allí aparecen Imprimir/Descargar PDF de ORDPRINT. Ningún botón de proveedor.

## Compatibilidad y curación

Las órdenes mixtas históricas no se reescriben ni se invalidan. La prueba genera una orden mixta por el endpoint individual existente, guarda su snapshot, agrega un resultado exacto y verifica lectura OR02B, HTML/PDF y snapshot idéntico. Es un espécimen histórico reproducible desechable, no una mutación del expediente de Dirección. Una orden nueva independiente recibe resultado por RES02A sin modificar relaciones canónicas. Las pruebas también comprueban los pre requisitos estáticos de interoperabilidad de las órdenes emitidas; no se ejecuta un flujo proveedor vivo.

[Curación de orina y fluidos](URINE_FLUIDS_CAT01_SAFE_CURATION_IMPLEMENTED.md): 59 candidatos, 6 adiciones, 3 existentes, 50 diferidos. Los perfiles ACR/PCR se emiten atómicamente, no como componentes numéricos. No se crea infraestructura de muestra/colección. Las seis filas de catálogo se aplicaron al runtime de revisión después de probarlas en esquema desechable; no se escribieron datos clínicos de QA en la base de revisión.

## Evidencia QA

| Gate | Resultado / alcance |
| --- | --- |
| `bash modules/clinical/qa/ord_comp01_disposable_gate.sh` | PASS: esquema desechable con seeds, 208 claves/rutas, éxito de 4 órdenes, replay exacto, conflicto, replay con catálogo desactivado, fallo validación, fallo SQL después del primer insert y rollback total, recuperación, duplicados, ruta incorrecta, custom, sin sesión/doctor ajeno. |
| Resultados e histórico en el mismo gate | PASS: uploads RES02A a orden independiente y mixta, fuente exacta/ítems y snapshot histórico preservados. |
| HTML/PDF en el mismo gate | PASS: laboratorio, imagen, patología, cardiovascular, dental CBCT/FDI y mixta, artefactos independientes válidos. Chromium local; no sustituye el gate Linux pendiente. |
| `python3 modules/clinical/qa/ord_comp01_browser.py` | PASS: 1440×900, 1366×768, 390×844; seis ramas inline, featured/alcance/acordeones, selección multifamilia/mismo grupo, custom controlado, indicaciones independientes, error por grupo, guard/descarte/unload, retry incierto con body/UUID idénticos, FDI preservado entre hojas, éxito sin modal. APIs de escritura interceptadas. |
| Actualización ORD-COMP02 en `ord_comp01_browser.py` | PASS: selector inline/cierre y foco, misma familia y cambio a imagenología sin VIS24, borrador conservado, copy de las cinco familias médicas y dental, revisión de una tarjeta sin emisión, regreso con las demás órdenes, revisión conjunta y accesos a pendientes e historial. |
| Actualización ORD-COMP02-R1 en `ord_comp01_browser.py` | PASS: diálogo individual y conjunto, cierre por ×/Escape/fondo, borrador de indicación/prioridad conservado, compositor DOM intacto, selector que sustituye y restaura el disparador, advertencia de muestra incompleta y ausencia de escritura previa a emisión. |
| `python3 modules/clinical/qa/dental_cat02_browser.py` | PASS: regresión del selector compartido en modalidad anterior, FDI, dentición mixta, proyecciones, arco, CBCT, escaneo, modelos, fotografías y perfiles. |
| `ORD_COMP01_REVIEW_SESSION=<sesión local> python3 modules/clinical/qa/ord_comp01_live_review.py` | PASS: shell real 18148, escritorio compacto y expandido/push, móvil, salida a Resumen bloqueada por VIS24; llamadas API mutantes bloqueadas. |
| Actualización ORD-COMP02 en `ord_comp01_live_review.py` | PASS: pestaña completa “Estudios de diagnóstico” visible y sin overflow a 1440×900 y 1366×768 con sidebar compacto y expandido/push; selector inline y “Revisar orden” accesibles también a 390×844. |
| Actualización ORD-COMP02-R1 en `ord_comp01_live_review.py` | PASS: modal real con foco interno, cierre que devuelve foco y posición vertical al compositor, sin overflow a 1440×900/1366×768 en sidebar compacto/expandido ni a 390×844. Solo lecturas; escrituras bloqueadas. |
| Accesibilidad | PASS: details/summary nativos, aria-pressed en estudios, estado live, botones Retirar con nombre específico, selección por teclado, toggle móvil aria-expanded. |

Anchos reales del selector/resumen: 1440 compacto 776.61/479.39 px, expandido 680.14/419.86; 1366 compacto 730.84/451.16, expandido 634.39/391.61. Todos con gutter 16 px y sin overflow horizontal. Móvil: una columna de 338 px interiores. El catálogo largo desplaza la página normalmente; no se promete ausencia de scroll vertical. El resumen usa el offset del encabezado del shell.

Capturas locales (no versionadas): `/tmp/ordcomp01_live_1440_compact.png`, `/tmp/ordcomp01_live_1440_expanded.png`, `/tmp/ordcomp01_live_1366_compact.png`, `/tmp/ordcomp01_live_1366_expanded.png`, `/tmp/ordcomp01_live_390_mobile.png`. Lanzador: Playwright bundled Chromium headless. No modificación de llavero ni advertencia observada.

Deudas conservadas: `ORDPRINT02B_LINUX_RUNTIME_GATE=PENDING_ENVIRONMENT`; `PROV04E_REAL_VALKEY_RUNTIME_GATE=PENDING_ENVIRONMENT`; fixtures mínimos TAX03A preexistentes. Este trabajo no declara esos gates completados.

## Matriz exacta de las 202 identidades del baseline

El grupo único se declara en JSON por clave; la tabla es su inventario de auditoría. Los IDs numéricos permanecen en el catálogo de cada entorno, sin reenumerarlos ni remapearlos.

| Clave | Nombre | Categoría canónica | Grupo operativo |
| --- | --- | --- | --- |
| `abpm_mapa` | MAPA (presión arterial ambulatoria / ABPM) | `CARDIOVASCULAR` | `CARDIOVASCULAR_DIAGNOSTICS` |
| `albumin` | Albúmina | `LABORATORIO` | `CLINICAL_LAB` |
| `alp` | Fosfatasa alcalina (ALP) | `LABORATORIO` | `CLINICAL_LAB` |
| `alt` | ALT (TGP) | `LABORATORIO` | `CLINICAL_LAB` |
| `amylase` | Amilasa | `LABORATORIO` | `CLINICAL_LAB` |
| `ana` | ANA | `LABORATORIO` | `CLINICAL_LAB` |
| `anca` | ANCA | `LABORATORIO` | `CLINICAL_LAB` |
| `ankle_brachial_index` | Índice tobillo-brazo (ITB/ABI) | `CARDIOVASCULAR` | `CARDIOVASCULAR_DIAGNOSTICS` |
| `anoscopy_base` | Anoscopia | `ENDOSCOPIA` | `DIGESTIVE_ENDOSCOPY` |
| `anti_ccp` | Anti-CCP | `LABORATORIO` | `CLINICAL_LAB` |
| `anti_hbc` | Anti-HBc | `LABORATORIO` | `CLINICAL_LAB` |
| `anti_hbs` | Anti-HBs | `LABORATORIO` | `CLINICAL_LAB` |
| `anti_tg` | Anti-tiroglobulina | `LABORATORIO` | `CLINICAL_LAB` |
| `anti_tpo` | Anti-TPO | `LABORATORIO` | `CLINICAL_LAB` |
| `aptt` | TTPa (aPTT) | `LABORATORIO` | `CLINICAL_LAB` |
| `ast` | AST (TGO) | `LABORATORIO` | `CLINICAL_LAB` |
| `audiometry_speech` | Audiometría verbal | `AUDIOLOGIA` | `AUDIOLOGY_VESTIBULAR` |
| `audiometry_tonal` | Audiometría tonal | `AUDIOLOGIA` | `AUDIOLOGY_VESTIBULAR` |
| `bhcg` | β-hCG | `LABORATORIO` | `CLINICAL_LAB` |
| `bilirubin_direct` | Bilirrubina directa | `LABORATORIO` | `CLINICAL_LAB` |
| `bilirubin_indirect` | Bilirrubina indirecta | `LABORATORIO` | `CLINICAL_LAB` |
| `bilirubin_total` | Bilirrubina total | `LABORATORIO` | `CLINICAL_LAB` |
| `blood_culture` | Hemocultivo | `LABORATORIO` | `CLINICAL_LAB` |
| `brca1_2` | BRCA1/BRCA2 (germinal) | `GENETICA` | `GENETICS_MOLECULAR` |
| `breast_us` | US Mama | `IMAGEN` | `GENERAL_IMAGING` |
| `bronchoscopy_base` | Broncoscopía | `ENDOSCOPIA` | `RESPIRATORY_DIAGNOSTIC_PROCEDURES` |
| `bun` | Nitrógeno ureico (BUN) | `LABORATORIO` | `CLINICAL_LAB` |
| `c3` | C3 | `LABORATORIO` | `CLINICAL_LAB` |
| `c4` | C4 | `LABORATORIO` | `CLINICAL_LAB` |
| `calcium` | Calcio | `LABORATORIO` | `CLINICAL_LAB` |
| `capnography` | Capnografía | `FUNCION_PULMONAR` | `PULMONARY_FUNCTION` |
| `capsule_base` | Cápsula endoscópica | `ENDOSCOPIA` | `DIGESTIVE_ENDOSCOPY` |
| `carotid_doppler` | Doppler carotídeo | `CARDIOVASCULAR` | `CARDIOVASCULAR_DIAGNOSTICS` |
| `carrier_screening` | Tamiz de portadores (Carrier screening) | `GENETICA` | `GENETICS_MOLECULAR` |
| `cbc` | Biometría hemática (BH / CBC) | `LABORATORIO` | `CLINICAL_LAB` |
| `chloride` | Cloro | `LABORATORIO` | `CLINICAL_LAB` |
| `chol_total` | Colesterol total | `LABORATORIO` | `CLINICAL_LAB` |
| `cma_microarray` | Microarreglo cromosómico (CMA / Microarray) | `GENETICA` | `GENETICS_MOLECULAR` |
| `colonoscopy_base` | Colonoscopia | `ENDOSCOPIA` | `DIGESTIVE_ENDOSCOPY` |
| `cpet` | Prueba de esfuerzo cardiopulmonar (CPET) | `FUNCION_PULMONAR` | `PULMONARY_FUNCTION` |
| `creatinine` | Creatinina | `LABORATORIO` | `CLINICAL_LAB` |
| `crp_hs` | PCR ultrasensible | `LABORATORIO` | `CLINICAL_LAB` |
| `ct_abdomen_pelvis` | TAC Abdomen y pelvis | `IMAGEN` | `GENERAL_IMAGING` |
| `ct_chest` | TAC Tórax | `IMAGEN` | `GENERAL_IMAGING` |
| `ct_head` | TAC Cráneo | `IMAGEN` | `GENERAL_IMAGING` |
| `ct_uro` | UroTAC (vías urinarias) | `IMAGEN` | `GENERAL_IMAGING` |
| `cyto_liquid_based` | Citología en base líquida | `PATOLOGIA` | `PATHOLOGY_CYTOLOGY` |
| `cyto_pap` | Papanicolaou (convencional) | `PATOLOGIA` | `PATHOLOGY_CYTOLOGY` |
| `d_dimer` | Dímero D | `LABORATORIO` | `CLINICAL_LAB` |
| `dental_cbct` | Tomografía dental y maxilofacial de haz cónico | `IMAGEN` | `DENTAL_DIAGNOSTICS` |
| `dental_cephalometric_xray` | Radiografía cefalométrica lateral | `IMAGEN` | `DENTAL_DIAGNOSTICS` |
| `dental_clinical_photographs` | Fotografías clínicas odontológicas | `DENTAL` | `DENTAL_DIAGNOSTICS` |
| `dental_intraoral_scan` | Escaneo intraoral dental | `DENTAL` | `DENTAL_DIAGNOSTICS` |
| `dental_panoramic_xray` | Radiografía panorámica dental | `IMAGEN` | `DENTAL_DIAGNOSTICS` |
| `dental_study_model` | Modelo de estudio dental | `DENTAL` | `DENTAL_DIAGNOSTICS` |
| `dexa` | Densitometría ósea (DEXA) | `IMAGEN` | `GENERAL_IMAGING` |
| `dlco` | DLCO (difusión de CO) | `FUNCION_PULMONAR` | `PULMONARY_FUNCTION` |
| `ebus_base` | EBUS (ultrasonido endobronquial) | `ENDOSCOPIA` | `RESPIRATORY_DIAGNOSTIC_PROCEDURES` |
| `ecg_12lead` | ECG 12 derivaciones | `CARDIOVASCULAR` | `CARDIOVASCULAR_DIAGNOSTICS` |
| `ecg_rhythm_strip` | Tira de ritmo | `CARDIOVASCULAR` | `CARDIOVASCULAR_DIAGNOSTICS` |
| `echo_tes` | Ecocardiograma transesofágico (ETE) | `CARDIOVASCULAR` | `CARDIOVASCULAR_DIAGNOSTICS` |
| `echo_tte` | Ecocardiograma transtorácico (ETT) | `CARDIOVASCULAR` | `CARDIOVASCULAR_DIAGNOSTICS` |
| `eeg_routine` | EEG rutinario | `NEUROFISIOLOGIA` | `NEUROPHYSIOLOGY` |
| `eeg_sleep_deprived` | EEG con privación de sueño | `NEUROFISIOLOGIA` | `NEUROPHYSIOLOGY` |
| `egd_eda_base` | EGD/EDA (endoscopia alta) | `ENDOSCOPIA` | `DIGESTIVE_ENDOSCOPY` |
| `emg_ncs` | EMG + Velocidades de conducción nerviosa (VCN) | `NEUROFISIOLOGIA` | `NEUROPHYSIOLOGY` |
| `ena` | ENA | `LABORATORIO` | `CLINICAL_LAB` |
| `enteroscopy_base` | Enteroscopia | `ENDOSCOPIA` | `DIGESTIVE_ENDOSCOPY` |
| `ercp_cpre_base` | CPRE / ERCP | `ENDOSCOPIA` | `DIGESTIVE_ENDOSCOPY` |
| `esr` | VSG | `LABORATORIO` | `CLINICAL_LAB` |
| `estradiol` | Estradiol | `LABORATORIO` | `CLINICAL_LAB` |
| `eus_use_base` | EUS/USE (ultrasonido endoscópico) | `ENDOSCOPIA` | `DIGESTIVE_ENDOSCOPY` |
| `evoked_auditory_baep` | Potenciales auditivos de tronco (PEAT/BAEP) | `NEUROFISIOLOGIA` | `NEUROPHYSIOLOGY` |
| `evoked_ssep` | Potenciales somatosensoriales (PESS/SSEP) | `NEUROFISIOLOGIA` | `NEUROPHYSIOLOGY` |
| `evoked_visual` | Potenciales evocados visuales (PEV) | `NEUROFISIOLOGIA` | `NEUROPHYSIOLOGY` |
| `fecal_occult_blood` | Sangre oculta en heces | `LABORATORIO` | `CLINICAL_LAB` |
| `feno` | FeNO (óxido nítrico exhalado) | `FUNCION_PULMONAR` | `PULMONARY_FUNCTION` |
| `ferritin` | Ferritina | `LABORATORIO` | `CLINICAL_LAB` |
| `fibrinogen` | Fibrinógeno | `LABORATORIO` | `CLINICAL_LAB` |
| `flex_sig_base` | Sigmoidoscopia flexible | `ENDOSCOPIA` | `DIGESTIVE_ENDOSCOPY` |
| `fluoro_barium_enema` | Enema baritado | `IMAGEN` | `GENERAL_IMAGING` |
| `fluoro_hsg` | Histerosalpingografía (HSG) | `IMAGEN` | `GENERAL_IMAGING` |
| `fluoro_ugi` | Tránsito esófago-gastro-duodenal (UGI) | `IMAGEN` | `GENERAL_IMAGING` |
| `fluoro_vcug` | Cistouretrografía miccional (VCUG) | `IMAGEN` | `GENERAL_IMAGING` |
| `folate` | Ácido fólico | `LABORATORIO` | `CLINICAL_LAB` |
| `fructosamine` | Fructosamina | `LABORATORIO` | `CLINICAL_LAB` |
| `fsh` | FSH | `LABORATORIO` | `CLINICAL_LAB` |
| `ft3` | T3 libre | `LABORATORIO` | `CLINICAL_LAB` |
| `ft4` | T4 libre | `LABORATORIO` | `CLINICAL_LAB` |
| `full_pft` | Pruebas funcionales respiratorias completas (PFR completo) | `FUNCION_PULMONAR` | `PULMONARY_FUNCTION` |
| `ggt` | GGT | `LABORATORIO` | `CLINICAL_LAB` |
| `glucose` | Glucosa | `LABORATORIO` | `CLINICAL_LAB` |
| `hba1c` | HbA1c | `LABORATORIO` | `CLINICAL_LAB` |
| `hbsag` | HBsAg | `LABORATORIO` | `CLINICAL_LAB` |
| `hcv_ab` | VHC (anticuerpos) | `LABORATORIO` | `CLINICAL_LAB` |
| `hdl` | HDL | `LABORATORIO` | `CLINICAL_LAB` |
| `hereditary_cancer_germline` | Panel de cáncer hereditario (multigénico) | `GENETICA` | `GENETICS_MOLECULAR` |
| `hiv_ag_ac` | VIH Ag/Ac | `LABORATORIO` | `CLINICAL_LAB` |
| `holter` | Holter / monitorización ECG ambulatoria | `CARDIOVASCULAR` | `CARDIOVASCULAR_DIAGNOSTICS` |
| `hsat` | Estudio domiciliario de apnea (HSAT) | `SUENO` | `SLEEP_DIAGNOSTICS` |
| `iga` | IgA | `LABORATORIO` | `CLINICAL_LAB` |
| `igg` | IgG | `LABORATORIO` | `CLINICAL_LAB` |
| `igm` | IgM | `LABORATORIO` | `CLINICAL_LAB` |
| `iron` | Hierro sérico | `LABORATORIO` | `CLINICAL_LAB` |
| `karyotype` | Cariotipo | `GENETICA` | `GENETICS_MOLECULAR` |
| `lab_ag_helicobacter_pylori` | Antígeno de Helicobacter pylori en heces | `LABORATORIO` | `CLINICAL_LAB` |
| `lab_amonio` | Amonio | `LABORATORIO` | `CLINICAL_LAB` |
| `lab_calprotectina_cuantificada` | Calprotectina fecal cuantitativa | `LABORATORIO` | `CLINICAL_LAB` |
| `lab_ck_mb` | Creatina cinasa MB | `LABORATORIO` | `CLINICAL_LAB` |
| `lab_coombs_directo` | Coombs directo | `LABORATORIO` | `CLINICAL_LAB` |
| `lab_coombs_indirecto` | Coombs indirecto | `LABORATORIO` | `CLINICAL_LAB` |
| `lab_cpk` | Creatina cinasa total | `LABORATORIO` | `CLINICAL_LAB` |
| `lab_frotis_sanguineo` | Frotis sanguíneo | `LABORATORIO` | `CLINICAL_LAB` |
| `lab_litio` | Litio | `LABORATORIO` | `CLINICAL_LAB` |
| `lab_reticulocitos` | Reticulocitos | `LABORATORIO` | `CLINICAL_LAB` |
| `lab_tiempo_de_protrombina` | Tiempo de protrombina | `LABORATORIO` | `CLINICAL_LAB` |
| `lab_tiempo_de_trombina` | Tiempo de trombina | `LABORATORIO` | `CLINICAL_LAB` |
| `laryngoscopy_base` | Laringoscopía flexible | `ENDOSCOPIA` | `ENT_DIAGNOSTICS` |
| `ldl` | LDL | `LABORATORIO` | `CLINICAL_LAB` |
| `lh` | LH | `LABORATORIO` | `CLINICAL_LAB` |
| `lipase` | Lipasa | `LABORATORIO` | `CLINICAL_LAB` |
| `lower_ext_art_doppler` | Doppler arterial de miembros inferiores | `CARDIOVASCULAR` | `CARDIOVASCULAR_DIAGNOSTICS` |
| `lower_ext_venous_doppler` | Doppler venoso de miembros inferiores | `CARDIOVASCULAR` | `CARDIOVASCULAR_DIAGNOSTICS` |
| `lynch` | Síndrome de Lynch (germinal) | `GENETICA` | `GENETICS_MOLECULAR` |
| `magnesium` | Magnesio | `LABORATORIO` | `CLINICAL_LAB` |
| `mammo` | Mamografía | `IMAGEN` | `GENERAL_IMAGING` |
| `microalbumin` | Microalbuminuria | `LABORATORIO` | `CLINICAL_LAB` |
| `mr_abdomen` | RM Abdomen | `IMAGEN` | `GENERAL_IMAGING` |
| `mr_brain` | RM Cerebro | `IMAGEN` | `GENERAL_IMAGING` |
| `mr_knee` | RM Rodilla | `IMAGEN` | `GENERAL_IMAGING` |
| `mr_shoulder` | RM Hombro | `IMAGEN` | `GENERAL_IMAGING` |
| `mslt` | MSLT (latencias múltiples del sueño) | `SUENO` | `SLEEP_DIAGNOSTICS` |
| `mwt` | MWT (mantenimiento de la vigilia) | `SUENO` | `SLEEP_DIAGNOSTICS` |
| `nipt` | NIPT (tamiz prenatal no invasivo) | `GENETICA` | `GENETICS_MOLECULAR` |
| `nm_bone_scan` | Gammagrama óseo | `IMAGEN` | `GENERAL_IMAGING` |
| `nm_thyroid_uptake` | Captación tiroidea | `IMAGEN` | `GENERAL_IMAGING` |
| `non_hdl` | Colesterol no-HDL | `LABORATORIO` | `CLINICAL_LAB` |
| `ogtt` | Curva de glucosa (OGTT) | `LABORATORIO` | `CLINICAL_LAB` |
| `otoacoustic_emissions` | Emisiones otoacústicas (OEA) | `AUDIOLOGIA` | `AUDIOLOGY_VESTIBULAR` |
| `overnight_oximetry` | Oximetría nocturna | `FUNCION_PULMONAR` | `PULMONARY_FUNCTION` |
| `pet_ct` | PET-CT | `IMAGEN` | `GENERAL_IMAGING` |
| `pgx` | Panel farmacogenómico (PGx) | `GENETICA` | `GENETICS_MOLECULAR` |
| `phosphorus` | Fósforo | `LABORATORIO` | `CLINICAL_LAB` |
| `platelets` | Plaquetas | `LABORATORIO` | `CLINICAL_LAB` |
| `plethysmography` | Volúmenes pulmonares (pletismografía corporal) | `FUNCION_PULMONAR` | `PULMONARY_FUNCTION` |
| `pleuroscopy_base` | Pleuroscopía / Toracoscopía médica | `ENDOSCOPIA` | `RESPIRATORY_DIAGNOSTIC_PROCEDURES` |
| `potassium` | Potasio | `LABORATORIO` | `CLINICAL_LAB` |
| `proctoscopy_base` | Proctoscopia | `ENDOSCOPIA` | `DIGESTIVE_ENDOSCOPY` |
| `progesterone` | Progesterona | `LABORATORIO` | `CLINICAL_LAB` |
| `prolactin` | Prolactina | `LABORATORIO` | `CLINICAL_LAB` |
| `psg_diagnostic` | Polisomnografía (PSG) diagnóstica | `SUENO` | `SLEEP_DIAGNOSTICS` |
| `psg_titration` | PSG con titulación (CPAP/BiPAP) | `SUENO` | `SLEEP_DIAGNOSTICS` |
| `repetitive_nerve_stimulation` | Estimulación repetitiva | `NEUROFISIOLOGIA` | `NEUROPHYSIOLOGY` |
| `rf` | Factor reumatoide | `LABORATORIO` | `CLINICAL_LAB` |
| `rx_abdomen` | RX Abdomen | `IMAGEN` | `GENERAL_IMAGING` |
| `rx_ankle` | RX Tobillo | `IMAGEN` | `GENERAL_IMAGING` |
| `rx_chest` | RX Tórax | `IMAGEN` | `GENERAL_IMAGING` |
| `rx_cspine` | RX Columna cervical | `IMAGEN` | `GENERAL_IMAGING` |
| `rx_hand` | RX Mano | `IMAGEN` | `GENERAL_IMAGING` |
| `rx_knee` | RX Rodilla | `IMAGEN` | `GENERAL_IMAGING` |
| `rx_lspine` | RX Columna lumbar | `IMAGEN` | `GENERAL_IMAGING` |
| `rx_pelvis` | RX Pelvis | `IMAGEN` | `GENERAL_IMAGING` |
| `rx_shoulder` | RX Hombro | `IMAGEN` | `GENERAL_IMAGING` |
| `sfemg` | EMG de fibra única (SFEMG) | `NEUROFISIOLOGIA` | `NEUROPHYSIOLOGY` |
| `six_min_walk` | Caminata 6 minutos (6MWT) | `FUNCION_PULMONAR` | `PULMONARY_FUNCTION` |
| `sodium` | Sodio | `LABORATORIO` | `CLINICAL_LAB` |
| `somatic_tumor_ngs` | Panel tumoral (NGS) — somático | `GENETICA` | `GENETICS_MOLECULAR` |
| `spirometry` | Espirometría | `FUNCION_PULMONAR` | `PULMONARY_FUNCTION` |
| `stool_culture` | Coprocultivo | `LABORATORIO` | `CLINICAL_LAB` |
| `stool_ova_parasites` | Coproparasitoscópico | `LABORATORIO` | `CLINICAL_LAB` |
| `stress_echo` | Ecocardiograma de estrés | `CARDIOVASCULAR` | `CARDIOVASCULAR_DIAGNOSTICS` |
| `stress_test` | Prueba de esfuerzo (ECG de esfuerzo) | `CARDIOVASCULAR` | `CARDIOVASCULAR_DIAGNOSTICS` |
| `testosterone_free` | Testosterona libre | `LABORATORIO` | `CLINICAL_LAB` |
| `testosterone_total` | Testosterona total | `LABORATORIO` | `CLINICAL_LAB` |
| `throat_swab` | Exudado faríngeo | `LABORATORIO` | `CLINICAL_LAB` |
| `thrombophilia` | Panel de trombofilia (genético) | `GENETICA` | `GENETICS_MOLECULAR` |
| `tibc` | CTFH (TIBC) | `LABORATORIO` | `CLINICAL_LAB` |
| `tilt_table` | Prueba de mesa basculante (Tilt table) | `CARDIOVASCULAR` | `CARDIOVASCULAR_DIAGNOSTICS` |
| `tmj_comparative_xray` | Radiografía comparativa de articulaciones temporomandibulares | `IMAGEN` | `DENTAL_DIAGNOSTICS` |
| `total_protein` | Proteínas totales | `LABORATORIO` | `CLINICAL_LAB` |
| `transferrin_sat` | % Saturación transferrina | `LABORATORIO` | `CLINICAL_LAB` |
| `triglycerides` | Triglicéridos | `LABORATORIO` | `CLINICAL_LAB` |
| `tsh` | TSH | `LABORATORIO` | `CLINICAL_LAB` |
| `tympanometry` | Timpanometría (impedanciometría) | `AUDIOLOGIA` | `AUDIOLOGY_VESTIBULAR` |
| `urea` | Urea | `LABORATORIO` | `CLINICAL_LAB` |
| `uric_acid` | Ácido úrico | `LABORATORIO` | `CLINICAL_LAB` |
| `urinalysis` | EGO (examen general de orina) | `LABORATORIO` | `CLINICAL_LAB` |
| `urine_culture` | Urocultivo | `LABORATORIO` | `CLINICAL_LAB` |
| `us_abdomen` | US Abdomen | `IMAGEN` | `GENERAL_IMAGING` |
| `us_obstetric_study` | US Obstétrico | `IMAGEN` | `GENERAL_IMAGING` |
| `us_pelvic` | US Pélvico | `IMAGEN` | `GENERAL_IMAGING` |
| `us_renal` | US Renal | `IMAGEN` | `GENERAL_IMAGING` |
| `us_soft_tissue` | US Partes blandas | `IMAGEN` | `GENERAL_IMAGING` |
| `us_testicular` | US Testicular | `IMAGEN` | `GENERAL_IMAGING` |
| `us_thyroid` | US Tiroides | `IMAGEN` | `GENERAL_IMAGING` |
| `vaginal_swab` | Exudado vaginal | `LABORATORIO` | `CLINICAL_LAB` |
| `video_eeg` | Video-EEG (prolongado) | `NEUROFISIOLOGIA` | `NEUROPHYSIOLOGY` |
| `vitamin_b12` | Vitamina B12 | `LABORATORIO` | `CLINICAL_LAB` |
| `vitamin_d` | Vitamina D | `LABORATORIO` | `CLINICAL_LAB` |
| `vng` | Videonistagmografía (VNG) | `AUDIOLOGIA` | `AUDIOLOGY_VESTIBULAR` |
| `wes` | Exoma clínico (WES) | `GENETICA` | `GENETICS_MOLECULAR` |
| `wgs` | Genoma clínico (WGS) | `GENETICA` | `GENETICS_MOLECULAR` |

## Seis adiciones de ORD-COMP01

| Clave | Nombre | Categoría | Grupo |
| --- | --- | --- | --- |
| `urine_albumin_creatinine_panel` | Albúmina y creatinina urinarias con relación (muestra aislada) | `LABORATORIO` | `CLINICAL_LAB` |
| `urine_protein_creatinine_panel` | Proteínas y creatinina urinarias con relación (muestra aislada) | `LABORATORIO` | `CLINICAL_LAB` |
| `urine_osmolality` | Osmolalidad urinaria | `LABORATORIO` | `CLINICAL_LAB` |
| `csf_cell_count` | Recuento celular y diferencial en LCR | `LABORATORIO` | `CLINICAL_LAB` |
| `synovial_crystals` | Cristales en líquido sinovial | `LABORATORIO` | `CLINICAL_LAB` |
| `semen_analysis` | Espermatobioscopía básica | `LABORATORIO` | `CLINICAL_LAB` |
