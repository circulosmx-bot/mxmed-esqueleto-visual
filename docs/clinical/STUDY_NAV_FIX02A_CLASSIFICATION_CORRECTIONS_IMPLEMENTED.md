# STUDY-NAV-FIX02A — Correcciones de clasificación implementadas

**Estado: IMPLEMENTADO.** Autoridad: [matriz AUDIT02](STUDY_NAV_AUDIT02_FAMILY_SUBFAMILY_REAUDIT.md), 202 filas leídas; 26 correcciones aplicadas (6 familias primarias, 20 subfamilias). Base: `8ec66df6b63ed8f64810570f13abbb8d3b99fc90`.

## Alcance

Se modificaron solo las configuraciones de navegación OR05 y LAB-CAT02A. Las seis ecografías/Doppler cardiovasculares dejaron de pertenecer a la familia funcional y permanecen en Imagenología; Cardiología conserva el atajo clínico para las 13 claves cardiovasculares. Las seis rutas tienen subgrupo visible: Ultrasonido cardiaco o Ultrasonido vascular / Doppler. `stress_echo` conserva la ruta clínica de Cardiología; su ruta secundaria de esfuerzo queda para FIX02B porque no es necesaria para mantener acceso.

El catálogo canónico, claves, ID, alias, escritores, lectores, esquema, órdenes históricas, resultados, FDI, interoperabilidad y portátil permanecen intactos. La prioridad LAB de Cardiología perdió “Marcadores tumorales”; el grupo continúa disponible en el catálogo universal. No se bloquearon especialidades.

## Registro completo de correcciones (26)

| Clave | Nombre visible | Familia anterior | Familia nueva | Grupo(s) anterior(es) | Grupo(s) nuevo(s) | Motivo | AUDIT02 | Prioridad | Identidad intacta |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `uric_acid` | Ácido úrico | LABORATORIO | LABORATORIO | Química clínica; Inmunología | Química clínica | Ácido úrico es química clínica, no ensayo inmunológico. | WRONG_SUBFAMILY | P1 | true |
| `albumin` | Albúmina | LABORATORIO | LABORATORIO | Química clínica; Orina y otros fluidos | Química clínica | La clave no especifica albúmina urinaria; no inferir muestra de orina desde el nombre genérico. | WRONG_SUBFAMILY | P1 | true |
| `crp_hs` | PCR ultrasensible | LABORATORIO | LABORATORIO | Inmunología | Inflamación / reactantes | La PCR ultrasensible mide proteína C reactiva; evitar confundirla con reacción en cadena de la polimerasa. | WRONG_SUBFAMILY | P1 | true |
| `esr` | VSG | LABORATORIO | LABORATORIO | Hematología; Inmunología | Hematología; Inflamación / reactantes | La VSG es reactante inflamatorio inespecífico; Hematología sigue siendo ruta válida. | WRONG_SUBFAMILY | P2 | true |
| `hiv_ag_ac` | VIH Ag/Ac | LABORATORIO | LABORATORIO | Inmunología | Serologías / infecciones | Ensayo de antígeno/anticuerpo infeccioso; buscarlo bajo serologías, no inmunología general. | WRONG_SUBFAMILY | P2 | true |
| `stool_ova_parasites` | Coproparasitoscópico | LABORATORIO | LABORATORIO | Materia fecal; Perfiles y paneles | Materia fecal | Examen parasitológico fecal; no es un preset de panel versionado. | WRONG_SUBFAMILY | P2 | true |
| `echo_tte` | Ecocardiograma transtorácico (ETT) | IMAGENOLOGÍA + ESTUDIOS FUNCIONALES | IMAGENOLOGÍA | Cardiología [atajo] | Ultrasonido cardiaco | La adquisición principal es ecografía/Doppler; Cardiología es prioridad clínica, no modalidad funcional. | WRONG_PRIMARY_FAMILY | P0 | true |
| `echo_tes` | Ecocardiograma transesofágico (ETE) | IMAGENOLOGÍA + ESTUDIOS FUNCIONALES | IMAGENOLOGÍA | Cardiología [atajo] | Ultrasonido cardiaco | La adquisición principal es ecografía/Doppler; Cardiología es prioridad clínica, no modalidad funcional. | WRONG_PRIMARY_FAMILY | P0 | true |
| `stress_echo` | Ecocardiograma de estrés | IMAGENOLOGÍA + ESTUDIOS FUNCIONALES | IMAGENOLOGÍA | Cardiología [atajo] | Ultrasonido cardiaco (ruta secundaria de esfuerzo: FIX02B) | La adquisición principal es ecografía/Doppler; Cardiología es prioridad clínica, no modalidad funcional. | WRONG_PRIMARY_FAMILY | P0 | true |
| `carotid_doppler` | Doppler carotídeo | IMAGENOLOGÍA + ESTUDIOS FUNCIONALES | IMAGENOLOGÍA | Cardiología [atajo] | Ultrasonido vascular / Doppler | La adquisición principal es ecografía/Doppler; Cardiología es prioridad clínica, no modalidad funcional. | WRONG_PRIMARY_FAMILY | P0 | true |
| `lower_ext_art_doppler` | Doppler arterial de miembros inferiores | IMAGENOLOGÍA + ESTUDIOS FUNCIONALES | IMAGENOLOGÍA | Cardiología [atajo] | Ultrasonido vascular / Doppler | La adquisición principal es ecografía/Doppler; Cardiología es prioridad clínica, no modalidad funcional. | WRONG_PRIMARY_FAMILY | P0 | true |
| `lower_ext_venous_doppler` | Doppler venoso de miembros inferiores | IMAGENOLOGÍA + ESTUDIOS FUNCIONALES | IMAGENOLOGÍA | Cardiología [atajo] | Ultrasonido vascular / Doppler | La adquisición principal es ecografía/Doppler; Cardiología es prioridad clínica, no modalidad funcional. | WRONG_PRIMARY_FAMILY | P0 | true |
| `cyto_pap` | Papanicolaou (convencional) | LABORATORIO | LABORATORIO | Otros; Citología [atajo] | Citopatología cervical | Citología cervical procesada en laboratorio; Otros carece de sentido clínico. | WRONG_SUBFAMILY | P1 | true |
| `cyto_liquid_based` | Citología en base líquida | LABORATORIO | LABORATORIO | Otros; Citología [atajo] | Citopatología cervical | Citología cervical en base líquida; Otros carece de sentido clínico. | WRONG_SUBFAMILY | P1 | true |
| `karyotype` | Cariotipo | LABORATORIO | LABORATORIO | Biología molecular / PCR; Genética [atajo] | Citogenética | Cariotipo observa cromosomas; no es PCR. | WRONG_SUBFAMILY | P1 | true |
| `cma_microarray` | Microarreglo cromosómico (CMA / Microarray) | LABORATORIO | LABORATORIO | Biología molecular / PCR; Genética [atajo] | Citogenómica / microarreglos | Microarreglo detecta variación cromosómica; no es PCR. | WRONG_SUBFAMILY | P1 | true |
| `wes` | Exoma clínico (WES) | LABORATORIO | LABORATORIO | Biología molecular / PCR; Genética [atajo] | Secuenciación genómica | La identidad y la semilla describen genética/genómica; técnica y finalidad definen el grupo, no la etiqueta PCR. | WRONG_SUBFAMILY | P2 | true |
| `wgs` | Genoma clínico (WGS) | LABORATORIO | LABORATORIO | Biología molecular / PCR; Genética [atajo] | Secuenciación genómica | La identidad y la semilla describen genética/genómica; técnica y finalidad definen el grupo, no la etiqueta PCR. | WRONG_SUBFAMILY | P2 | true |
| `nipt` | NIPT (tamiz prenatal no invasivo) | LABORATORIO | LABORATORIO | Endocrinología y hormonas; Biología molecular / PCR; Genética [atajo] | Tamiz genético prenatal | El cfDNA prenatal es tamiz genético, no prueba hormonal ni diagnóstico citogenético definitivo. | WRONG_SUBFAMILY | P1 | true |
| `carrier_screening` | Tamiz de portadores (Carrier screening) | LABORATORIO | LABORATORIO | Biología molecular / PCR; Perfiles y paneles; Genética [atajo] | Genética germinal; Perfiles y paneles | La identidad y la semilla describen genética/genómica; técnica y finalidad definen el grupo, no la etiqueta PCR. | WRONG_SUBFAMILY | P2 | true |
| `hereditary_cancer_germline` | Panel de cáncer hereditario (multigénico) | LABORATORIO | LABORATORIO | Biología molecular / PCR; Marcadores tumorales; Perfiles y paneles; Genética [atajo] | Genética germinal; Oncología hereditaria; Perfiles y paneles | Panel germinal de predisposición; no marcador tumoral convencional. | WRONG_SUBFAMILY | P2 | true |
| `brca1_2` | BRCA1/BRCA2 (germinal) | LABORATORIO | LABORATORIO | Biología molecular / PCR; Marcadores tumorales; Genética [atajo] | Genética germinal; Oncología hereditaria | Riesgo germinal hereditario; no marcador tumoral sérico de enfermedad activa. | WRONG_SUBFAMILY | P2 | true |
| `lynch` | Síndrome de Lynch (germinal) | LABORATORIO | LABORATORIO | Biología molecular / PCR; Genética [atajo] | Genética germinal; Oncología hereditaria | La identidad y la semilla describen genética/genómica; técnica y finalidad definen el grupo, no la etiqueta PCR. | WRONG_SUBFAMILY | P2 | true |
| `thrombophilia` | Panel de trombofilia (genético) | LABORATORIO | LABORATORIO | Coagulación; Biología molecular / PCR; Perfiles y paneles; Genética [atajo] | Genética germinal; Coagulación [atajo clínico]; Perfiles y paneles | El nombre canónico especifica panel genético; Coagulación puede ser atajo clínico, sin redefinirlo como ensayo funcional. | WRONG_SUBFAMILY | P2 | true |
| `pgx` | Panel farmacogenómico (PGx) | LABORATORIO | LABORATORIO | Biología molecular / PCR; Perfiles y paneles; Genética [atajo] | Farmacogenómica; Perfiles y paneles | La identidad y la semilla describen genética/genómica; técnica y finalidad definen el grupo, no la etiqueta PCR. | WRONG_SUBFAMILY | P2 | true |
| `somatic_tumor_ngs` | Panel tumoral (NGS) — somático | LABORATORIO | LABORATORIO | Biología molecular / PCR; Marcadores tumorales; Perfiles y paneles; Genética [atajo] | Oncología molecular; Perfiles y paneles | Secuenciación tumoral somática; ruta de oncología molecular, no marcador sérico. | WRONG_SUBFAMILY | P2 | true |

## Recuento y límites

| Familia primaria | Claves activas |
| --- | ---: |
| Laboratorio (`LABORATORIO` + `GENETICA` + `PATOLOGIA`) | 107 |
| Imagenología (`IMAGEN` + seis cardiovasculares) | 44 |
| Estudios funcionales (siete cardiovasculares + neurofisiología, función pulmonar, sueño y audiología) | 35 |
| Procedimientos diagnósticos (`ENDOSCOPIA`) | 13 |
| Dental (`DENTAL`) | 3 |
| **Total** | **202** |

Las cuatro imágenes dentales conservan Imagenología primaria y sus rutas dentales: `dental_cbct`, `dental_panoramic_xray`, `dental_cephalometric_xray`, `tmj_comparative_xray`. `dental_intraoral_scan`, `dental_clinical_photographs` y `dental_study_model` siguen en la categoría canónica `DENTAL`; sus atajos dentales no se alteraron. La ruta de fotografías clínicas aparece en Ortodoncia; el perfil Dentista conserva búsqueda en todo el catálogo. Crear una nueva familia general Dental sería una ruta adicional de FIX02B, no una de las 26 correcciones.

El grupo de configuración “Biología molecular / PCR” se conserva sin claves y el renderer existente lo oculta por estar vacío. Ninguna de las 202 claves activas está respaldada como PCR infecciosa; seguir mostrando los 12 estudios genéticos allí perpetuaría la clasificación errónea. Sus rutas correctas son Citogenética, Citogenómica, Secuenciación genómica, Tamiz genético prenatal, Genética germinal, Oncología hereditaria, Farmacogenómica y Oncología molecular. Los grupos válidos de Hematología, Química clínica, Coagulación, Endocrinología y hormonas, Inmunología, Microbiología, Serologías / Hepatitis, Orina y otros fluidos, Materia fecal, Autoinmunidad, Marcadores tumorales y Perfiles y paneles permanecen disponibles.

Las dos citologías (`cyto_pap`, `cyto_liquid_based`) están en Citopatología cervical; “Otros” ya no se muestra. La antigua ruta de Coagulación para `thrombophilia` sigue como atajo clínico, mientras Genética germinal expresa la técnica primaria. Cinco paneles genéticos conservan Perfiles y paneles donde lo exige la matriz.

**Congelado:** `non_hdl`, `transferrin_sat`, `ercp_cpre_base`, `dental_study_model` (revisión de identidad); `capnography` (incierto). Ninguna identidad se modificó. Las 94 rutas adicionales de AUDIT02 y demás prioridades de especialidad quedan como **STUDY-NAV-FIX02B_DEFERRED**. No se implementaron las 34 membresías opcionales en bloque.

## QA

- Contrato de matriz contra 202 claves activas de la BD de Dirección: 26/26 propuestas, división cardiovascular 6/7, cero grupos nuevos vacíos y cero rutas “Otros” visibles.
- `python3 modules/clinical/qa/study_nav_fix02a_browser.py`: 1440×900, 1366×768 y 390×844; búsqueda global 26/26; una orden de citología con escritura interceptada; accesos de Medicina General, Cardiología, Gastroenterología, Hematología, Genética, Dentista y Ortodoncia. Las seis imágenes están solo en Imagenología primaria; las siete restantes en Funcionales; el atajo Cardiología reúne las 13 sin duplicado; ambos subgrupos de ultrasonido muestran tres claves. Sin errores de página ni desbordamiento horizontal.
- `python3 modules/clinical/qa/lab_cat02a_browser.py`: gate existente actualizado a 24 grupos visibles, trece perfiles, los tres tamaños y tres especialidades dentales: PASS.
- Runtime real `http://127.0.0.1:18148/`: captura visual en los tres tamaños, cuatro familias principales, rutas corregidas servidas desde este checkout, cero desbordamiento horizontal y cero errores JS. La cabecera HTTP de procedencia tiene un SHA fijo antiguo; se verificaron los archivos servidos directamente.
- `bash modules/clinical/qa/lab_cat02a_disposable_gate.sh`: orden canónica mixta, read model portátil, HTML autenticado y PDF autenticado: PASS. La prueba creó y eliminó su propia BD; la BD de revisión no recibió escrituras.
- `bash modules/clinical/qa/dental_cat02_disposable_gate.sh`: catálogo dental, FDI permanente/temporal, localización, rechazo de combinaciones inválidas, snapshot de orden y reemplazo: PASS.
- VIS23, VIS24 y VIS25-R1: archivos de shell sin cambios. En runtime real, expandir la barra lateral en 1440 y 1366 píxeles conservó el layout sin desbordamiento; en 390 píxeles se abrió el drawer móvil sin desbordamiento. No se alteró el flujo de consulta.

AUDIT02 queda **PARTIALLY_IMPLEMENTED** hasta FIX02B.
