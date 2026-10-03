# STUDY-NAV-HIER03 — navegación progresiva V2 implementada

**Actualización ORD-COMP01 (2026-10-03):** HIER03 conserva raíz/familias/subfamilias. El nivel final ahora es el compositor inline TAX03C, con múltiples órdenes operativas; véase [contrato vigente ORD-COMP01](ORD_COMP01_INLINE_MULTI_ORDER_COMPOSER_IMPLEMENTED.md). Los conteos de 202 y QA HIER03 de este documento describen su baseline histórico; el catálogo vigente tiene 208.

La autoridad de presentación es [`study-navigation-hierarchy-v2.js`](../../assets/js/clinical/study-navigation-hierarchy-v2.js). La identidad y disponibilidad de cada estudio siguen en `clinical_study_types`; esta configuración solo organiza rutas por `category_key` y `study_type_key`. El selector y la deduplicación por `study_type_id` siguen en TAX03C. ORD-COMP01 coordina la emisión atómica de documentos mediante el writer canónico existente. V1 permanece como base de los atajos dentales y de las listas de claves de LAB-CAT02A, sin que su pantalla plana sea el modelo médico actual.

## Modelo de niveles y límites

`ROOT_FAMILIES → FAMILY → SUBFAMILY → NESTED_GROUP → STUDY_SELECTOR`. La ruta usa IDs estables (`hierPath`) y el título, la miga y el botón Volver se derivan de ella. Solo aparece un Volver por pantalla: opciones en la raíz, familia inmediata en niveles anidados y subfamilia en el selector. El grupo de hijo único se abre directamente; hoy Patología y biopsias abre Citología cervical. La búsqueda global y la lista completa de Laboratorio/Imagenología son salidas explícitas. Los ocho grupos primarios de Laboratorio y cuatro rutas secundarias se muestran en una sola pantalla; los demás niveles no superan ocho opciones primarias.

La raíz médica, sin atajos de especialidad, es: **Laboratorio (105)**, **Imagenología (44)**, **Patología y biopsias (2)**, **Estudios funcionales (35)** y **Procedimientos diagnósticos (13)**. Las tres identidades de categoría `DENTAL` siguen alcanzables en navegación dental y catálogo global: 105 + 44 + 2 + 35 + 13 + 3 = **202**. Las cuatro imágenes dentales de categoría `IMAGEN` permanecen en el listado completo de Imagenología, pero no se promocionan como tarjeta médica. Los perfiles dentales (Dentista, Ortodoncia, Implantología, Endodoncia) conservan su raíz/atajos V1 y la localización FDI del selector. Las demás familias profesionales usan la raíz médica/general.

## Rutas de estudio

| Familia | Grupos visibles y alcance exacto |
| --- | --- |
| Laboratorio | Química clínica, Hematología, Coagulación, Endocrinología y hormonas, Inmunología y serología, Microbiología, Genética y diagnóstico molecular, Orina y otros fluidos. Secundarios: Materia fecal, Marcadores tumorales, Monitoreo de fármacos, Perfiles y paneles. Abarca categorías `LABORATORIO` y `GENETICA`; excluye `PATOLOGIA`. Las listas de claves de cada grupo reutilizan LAB-CAT02A corregido por FIX02A. Química clínica abre el selector inline con búsqueda acotada y catálogo completo en acordeones, inicialmente cerrados. |
| Inmunología y serología | Inmunoglobulinas y complemento (`iga`, `igg`, `igm`, `c3`, `c4`); Autoinmunidad (grupo LAB-CAT02A más `rf`); Serologías de hepatitis; Otras serologías infecciosas; Inflamación y reactantes. Las últimas tres toman las listas exactas `serology`, `infectious_serology` e `inflammation` de LAB-CAT02A. |
| Genética y diagnóstico molecular | Citogenética, Citogenómica y microarreglos, Secuenciación genómica, Tamiz genético prenatal, Genética germinal, Oncología hereditaria, Farmacogenómica y Oncología molecular. Cada una toma las claves FIX02A de LAB-CAT02A; “Biología molecular / PCR” no aparece sin claves respaldadas. |
| Patología y biopsias | Citología cervical: `cyto_pap`, `cyto_liquid_based` de `PATOLOGIA`. No se muestra bajo Laboratorio ni se inventan otros grupos de patología. |
| Imagenología | Radiografía y fluoroscopía, Ultrasonido, Tomografía, Resonancia magnética, Medicina nuclear y PET, Imagen mamaria, Densitometría. Sus claves se enumeran en la configuración V2; el escape completo incluye toda `IMAGEN` más las seis claves cardiovasculares de ultrasonido. |
| Ultrasonido | General (`us_abdomen`, `us_renal`, `us_thyroid`, `us_soft_tissue`, `us_testicular`); obstétrico/ginecológico (`us_pelvic`, `us_obstetric_study`); cardíaco (`echo_tte`, `echo_tes`, `stress_echo`); vascular/Doppler (`carotid_doppler`, `lower_ext_art_doppler`, `lower_ext_venous_doppler`). |
| Estudios funcionales | Cardiovascular: `ecg_12lead`, `ecg_rhythm_strip`, `holter`, `abpm_mapa`, `stress_test`, `tilt_table`, `ankle_brachial_index`; además Neurofisiología (`NEUROFISIOLOGIA`), Función pulmonar (`FUNCION_PULMONAR`), Sueño (`SUENO`), Audiología y función vestibular (`AUDIOLOGIA`). Las seis ecografías/Doppler no entran aquí. |
| Procedimientos diagnósticos | Endoscopia digestiva: `egd_eda_base`, `colonoscopy_base`, `flex_sig_base`, `anoscopy_base`, `proctoscopy_base`, `ercp_cpre_base`, `eus_use_base`, `capsule_base`, `enteroscopy_base`; Broncoscopía: `bronchoscopy_base`, `ebus_base`; Laringoscopía: `laryngoscopy_base`; Pleuroscopía: `pleuroscopy_base`. Todos son `ENDOSCOPIA`. |

La pertenencia se calcula mediante partes declaradas y claves exactas, sin inferencia por palabras del nombre. Una clave puede tener una ruta clínica secundaria (por ejemplo `thrombophilia` en Coagulación y Genética), pero el selector muestra cada ID una sola vez dentro de una lista. Los grupos vacíos se ocultan. Se mantienen diferidas las revisiones de identidad de `non_hdl`, `transferrin_sat`, `ercp_cpre_base`, `dental_study_model` y `capnography`; esta fase no altera esas filas ni infiere versiones.

El resto de la partición de Imagenología usa estas claves exactas de `IMAGEN`:

| Subfamilia | Claves |
| --- | --- |
| Radiografía y fluoroscopía | `rx_chest`, `rx_abdomen`, `rx_pelvis`, `rx_cspine`, `rx_lspine`, `rx_shoulder`, `rx_knee`, `rx_ankle`, `rx_hand`, `fluoro_hsg`, `fluoro_vcug`, `fluoro_ugi`, `fluoro_barium_enema` |
| Tomografía | `ct_head`, `ct_chest`, `ct_abdomen_pelvis`, `ct_uro` |
| Resonancia magnética | `mr_brain`, `mr_knee`, `mr_shoulder`, `mr_abdomen` |
| Medicina nuclear y PET | `nm_bone_scan`, `nm_thyroid_uptake`, `pet_ct` |
| Imagen mamaria | `mammo`, `breast_us` |
| Densitometría | `dexa` |

Las rutas de Laboratorio se definen en las listas exactas de `lab-cat02a-navigation-v1.js` que V2 referencia por clave de grupo; HIER03 agrega `rf` a Autoinmunidad y mueve el grupo de dos citologías a Patología. Las categorías completas de Neurofisiología, Función pulmonar, Sueño y Audiología tienen correspondencia 1:1 con sus subfamilias actuales; sus identidades exactas se leen del catálogo activo.

## Personalización, selección y borradores

La especialidad solo **reordena subfamilias**. Cardiología prioriza Ultrasonido en Imagenología, cardíaco/vascular dentro de Ultrasonido y Cardiovascular en Funcionales. Endocrinología prioriza Hormonas/Química; Neurología, Neurofisiología/Sueño; Gastroenterología, Endoscopia digestiva. El Simulador de Clasificación vuelve a resolver ese orden en la pantalla abierta sin cambiar la raíz. Ningún perfil oculta el catálogo global.

TAX03C recibe el alcance explícito en presentación **EMBEDDED**. El resumen **Ver órdenes en preparación** recupera la selección acumulada. Una sesión puede recorrer varias familias sin pérdida; cada grupo operativo produce su propia orden, con indicación y prioridad independientes. La confirmación se realiza en una pantalla de revisión inline. VIS24 protege toda la composición y sus campos locales al cambiar pestaña, paciente, barra lateral o salir. Volver entre familias no es abandono. La escritura nueva usa `POST .../patients/{patient}/orders/batch`; la escritura individual permanece para compatibilidad y las órdenes mixtas históricas siguen válidas.

## QA y alcance

La prueba histórica `python3 modules/clinical/qa/study_nav_hier03_browser.py` usaba los 202 estudios activos de la BD de revisión solo en lectura y un endpoint de escritura **interceptado**; prueba 1440×900, 1366×768 y 390×844, raíz, grupos anidados, citología, cardiología/ultrasonido, funcionales, procedimientos, perfiles, dental, borrador, estudio personalizado y orden mixta. El runtime real de Dirección en `http://127.0.0.1:18148/` se inspeccionó también a 1440×900 y 1366×768 con barra lateral compacta y expandida. No se escribieron datos de revisión. Los estilos V2 solo afectan la pantalla de elección de grupos; VIS23, VIS24, VIS25-R1, OR03, OR04, RES02A, impresión portátil y el hub de tres intenciones conservan su arquitectura.

HIER03 originalmente no modificó catálogo, alias, esquema, lectores, modelo de escritura, proveedor ni migraciones. ORD-COMP01 agrega la curación de catálogo y el comando de composición descritos en su contrato. Las pruebas vigentes son `ord_comp01_browser.py`, `ord_comp01_disposable_gate.sh` y `ord_comp01_live_review.py`; no debe ejecutarse la prueba histórica con sus expectativas antiguas de una sola orden/modal como gate del flujo actual.
