# DENTAL-CAT01 — catálogo diagnóstico odontológico V1

**Estado: PROPOSED — NOT IMPLEMENTED.** Auditoría documental para revisión del Director. Corte: 2026-10-02; HEAD de origen `10e7f5e388845530ae38f28769c173050261c99d`. Ninguna fila, contrato, navegación, interfaz, migración ni base de datos se modifica aquí.

## Autoridades, fuentes y método

- Fuentes primarias: dos hojas reales del proveedor, inspeccionadas visualmente. **Hoja 1, Cone Beam:** `/Users/circulodigital/Downloads/WhatsApp Image 2026-10-02 at 09.30.25.jpeg` (SHA-256 `99310d70f0ced080709a7dc8242a4fa35be2aaf2ad7a472b9204e01854afca67`). **Hoja 2, ortodoncia/radiografías/escaneo/modelos:** `/Users/circulodigital/Downloads/WhatsApp Image 2026-10-02 at 09.30.26.jpeg` (SHA-256 `b80f0ebfac6a5e34c6829096415642ec10a552fecb621ffb23f418944b59e825`). `/Users/circulodigital/Downloads/WhatsApp Image 2026-10-02 at 09.19.00.jpeg` es una vista más completa del frente de la **misma hoja 1**, no una tercera hoja independiente. Las marcas manuscritas no se interpretan como opciones clínicas nuevas.
- Autoridad de catálogo: `clinical_study_types` de la BD de revisión `mxmed_director_review_lon07c`, cotejada con `modules/clinical/catalog/tax03b_source_curation.json` y `modules/clinical/db/migrations/2026_09_30_15_initial_curated_study_catalog.sql`. Su contrato está en `modules/clinical/db/migrations/2026_09_30_14_study_type_authority.sql`: `study_type_key`, `display_name_es`, `category_key`, `aliases_json`, `is_active`. La categoría `DENTAL` ya existe. Las clasificaciones y navegación vigentes están documentadas en `docs/clinical/OR05_NAVMAP01_SPECIALTY_NAVIGATION_MATRIX_V1_PROPOSED.md` y configuradas en `assets/js/clinical/or05-specialty-navigation-v1.js`.
- El catálogo tiene **183 filas activas de 183**, **0 en DENTAL**. Se revisaron las entradas vigentes de todas las categorías, con búsqueda de nombre/alias y comparación de modalidad, anatomía y propósito; no se considera equivalencia clínica por compartir una palabra. Hay **0 equivalentes dentales exactos**. `ct_head` (`TAC Cráneo`) se aproxima sólo anatómicamente a una solicitud maxilofacial; **no es CBCT dental**. `rx_hand` (`RX Mano`) puede aproximarse a “Carpal”, pero no confirma protocolo de mano/muñeca para edad ósea. Son **2 cercanos que exigen validación**, no reemplazos autorizados. Las dos filas `PATOLOGIA` son citología cervical (`cyto_pap`, `cyto_liquid_based`), no patología bucal.
- Referencias externas, usadas para nomenclatura y límites clínicos, no como autorización de nuevos estudios: [ADA/AAOMR 2026, recurso de selección radiográfica y CBCT](https://www.ada.org/resources/ada-library/oral-health-topics/x-rays-radiographs); [resumen ADA de las recomendaciones 2026](https://adanews.ada.org/ada-news/2026/january/new-ada-recommendations-confirm-dental-imaging-most-effectively-used-in-moderation/); [glosario de registros de ortodoncia de AAO](https://aaoinfo.org/resources/glossary-of-orthodontic-terms/); [AAE/AAOMR, CBCT en endodoncia](https://newsroom.aae.org/press-releases/aae-and-aaomr-release-joint-position-statement-on-the-use-of-cbct-in-endodontics/page/2/); [AAOMR, imagen en implantes](https://aaomr.org/common/Uploaded%20files/Position%20Papers/aaomr_implants_position_paper.pdf); [DICOM, imágenes dentales y series](https://dicom.nema.org/medical/dicom/current/output/chtml/part17/sect_uuuu.3.2.6.4.html); [DICOM, módulo de imagen intraoral y referencia ISO 3950](https://dicom.nema.org/MEDICAL/DICOM/current/output/chtml/part03/sect_C.8.11.9.html); [ISO 3950:2016](https://www.iso.org/standard/68292.html). Estas fuentes distinguen imagen 2D de CBCT 3D, registros ortodóncicos compuestos, indicación individualizada y formato de imagen frente a estudio clínico.

### Regla de clasificación

`A` estudio diagnóstico canónico; `B` parámetro de solicitud; `C` preset/paquete; `D` artefacto/formato/entrega; `E` configuración del proveedor o equipo; `F` servicio profesional complementario; `G` fuera del catálogo clínico. `M` = ausente del catálogo; `N:ct_head`/`N:rx_hand` = cercano **no equivalente**; `—` = no aplica. `CBCT`, `PAN`, `CEF`, `CRAN`, `ATM`, `SCAN`, `PHOTO`, `MODEL` son candidatos de la tabla de estudios posterior. `ORT3M`, `ORT3S`, `ORT2M`, `ORT2S` son los cuatro presets de la hoja. Esta matriz registra cada concepto seleccionable o solicitables y los campos/leyendas que afectan su interpretación. La pertenencia a paquete no significa que el componente se deba ordenar automáticamente.

## Inventario íntegro de conceptos de las hojas

| ID / sección | SOURCE_TERM | NORMALIZED_NAME | CLASSIFICATION | CANONICAL_STUDY_PROPOSED | PROPOSED_CATEGORY | ALIASES | PARAMETERS | PACKAGE_MEMBERSHIP | OUTPUT_ARTIFACTS | SPECIALTY_AFFINITY | EXISTING_CATALOG_COLLISION | NOTES |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| F01 Cone Beam | Tomografía CONE BEAM | Tomografía dental de haz cónico | A | CBCT | IMAGEN | CBCT, Cone Beam | cobertura, arco, zona | ORT3M/ORT3S | imagen, DICOM | endodoncia, implantes, cirugía, ortodoncia | N:ct_head | IMADENT Norte es marca/proveedor, no identidad. |
| F02 Cone Beam | Alta Resolución (3 - 4 órganos dentales) | Cobertura localizada | B | CBCT | IMAGEN | campo limitado | cobertura=localizada, dientes | — | — | endodoncia, implantes | — | Resolución publicitaria/técnica; el alcance clínico es 3–4 dientes. |
| F03 Cone Beam | 3x3 | Campo 3×3 | E | CBCT | IMAGEN | — | FOV técnico opcional | — | — | — | — | Dimensión del equipo; no estudio propio. |
| F04 Cone Beam | 5x5 | Campo 5×5 | E | CBCT | IMAGEN | — | FOV técnico opcional | — | — | — | — | Misma regla. |
| F05 Cone Beam | Un Maxilar (Hasta 2do. molar) | Un arco | B | CBCT | IMAGEN | — | cobertura=arco, límite anatómico | — | — | implantes, ortodoncia | — | El límite describe alcance solicitado. |
| F06 Cone Beam | 8x5 | Campo 8×5 | E | CBCT | IMAGEN | — | FOV técnico opcional | — | — | — | — | No identidad universal. |
| F07 Cone Beam | Maxilar | Arco maxilar | B | CBCT | IMAGEN | superior | arco=maxilar | — | — | dental | — | Elección bajo “Un Maxilar”. |
| F08 Cone Beam | Mandíbula | Arco mandibular | B | CBCT | IMAGEN | inferior | arco=mandibular | — | — | dental | — | Elección bajo “Un Maxilar”. |
| F09 Cone Beam | Ambos Maxilares | Ambos arcos | B | CBCT | IMAGEN | maxilar y mandíbula | cobertura=ambos_arcos | — | — | implantes, ortodoncia | — | No son dos estudios por defecto. |
| F10 Cone Beam | 8x8 (Hasta 2do. molar) | Campo 8×8 | E | CBCT | IMAGEN | — | FOV técnico; alcance como B | — | — | — | — | Separar medida de cobertura clínica. |
| F11 Cone Beam | 12x9 (Detrás del 3er. molar) | Campo 12×9 | E | CBCT | IMAGEN | — | FOV técnico; alcance como B | — | — | — | — | La frase anatómica no define nueva modalidad. |
| F12 Cone Beam | Maxilofacial | Cobertura maxilofacial | B | CBCT | IMAGEN | — | cobertura=maxilofacial | — | — | cirugía maxilofacial | N:ct_head | No colapsar en TAC cráneo. |
| F13 Cone Beam | 15x15 | Campo 15×15 | E | CBCT | IMAGEN | — | FOV técnico opcional | — | — | — | — | No identidad universal. |
| F14 Cone Beam | 20x17 (Sucursal Norte) | Campo 20×17 | E | CBCT | IMAGEN | — | FOV técnico opcional | — | — | — | — | “Sucursal Norte” prueba acoplamiento al proveedor. |
| F15 Cone Beam | Observaciones | Nota de solicitud | B | CBCT | IMAGEN | — | indicación libre complementaria | — | — | dental | — | No sustituye los campos estructurados. |
| F16 Zona de interés | Zona de Interés | Región/dientes de interés | B | CBCT | IMAGEN | — | sistema de numeración, dientes, región | — | — | dental | — | Diagrama con 1.8–1.1, 2.1–2.8, 4.8–4.1, 3.1–3.8; marcas manuscritas no transcritas como pedido. |
| F17 Visor | Ez3d (Estándar) | Visor Ez3D | E | — | — | — | — | — | visor | proveedor | — | Software de entrega. |
| F18 Visor | Ez3di (Avanzado) | Visor Ez3Di | E | — | — | — | — | — | visor | proveedor | — | Software de entrega. |
| F19 Visor | Romexis | Visor Romexis | E | — | — | — | — | — | visor | proveedor | — | Software de entrega. |
| F20 Visor | Windows / Mac | Plataforma del visor | E | — | — | — | — | — | ejecutable | proveedor | — | Iconos de compatibilidad, no estudio. |
| F21 Archivos | Archivos DICOM | Exportación DICOM | D | — | — | DICOM | formato=Dicom | — | DICOM | radiología | — | Formato/serie de imagen, no estudio. |
| F22 Archivos | Archivos STL | Malla STL | D | — | — | STL | formato=STL | — | malla/modelo | odontología digital | — | Exportación 3D, no estudio. |
| F23 Complementos | Interpretación por Radiólogo Bucal (Costo extra) | Informe por radiólogo bucal | F | — | — | interpretación radiológica | interpretación solicitada | — | informe/PDF | radiología | — | Servicio profesional; “costo extra” comercial. |
| F24 Información | IMADENT Norte / sucursales / QR | Proveedor y ubicación | G | — | — | — | — | — | — | proveedor | — | Direcciones/mapa/QR, no catálogo. |
| F25 Información | Instrucciones de cita / firma / autorización | Operación/consentimiento | G | — | — | — | — | — | — | proveedor | — | No solicitud diagnóstica. |
| B01 Ortodoncia | Estudio Ortodoncia 3D Modelo de Estudio | Preset ortodoncia 3D + modelo | C | CBCT+PAN+CEF+PHOTO+MODEL; análisis | mixto | — | cobertura CBCT, modelo | ORT3M | imágenes, reporte, modelo | ortodoncia | M | “Presentación digital” es entrega. |
| B02 Ortodoncia | Estudio Ortodoncia 3D Escaneo Intraoral | Preset ortodoncia 3D + escaneo | C | CBCT+PAN+CEF+PHOTO+SCAN; análisis | mixto | — | cobertura CBCT, arco escaneo | ORT3S | imágenes, reporte, escaneo | ortodoncia | M | Fuente enumera componentes, no equivalencia de modelos/scan. |
| B03 Ortodoncia | Estudio Ortodoncia 2D Modelo de Estudio | Preset ortodoncia 2D + modelo | C | PAN+CEF+PHOTO+MODEL; análisis | mixto | — | modelo | ORT2M | imágenes, reporte, modelo | ortodoncia | M | Sin CBCT. |
| B04 Ortodoncia | Estudio Ortodoncia 2D Escaneo Intraoral | Preset ortodoncia 2D + escaneo | C | PAN+CEF+PHOTO+SCAN; análisis | mixto | — | arco escaneo | ORT2S | imágenes, reporte, escaneo | ortodoncia | M | Sin CBCT. |
| B05 Componentes | Rx Panorámica | Radiografía panorámica dental | A | PAN | IMAGEN | Ortopantomografía | — | ORT3M/3S/2M/2S | imagen | ortodoncia, odontología | M | Componente repetido en 4 presets. |
| B06 Componentes | Rx Lateral | Radiografía cefalométrica lateral | A | CEF | IMAGEN | lateral de cráneo | vista=lateral | ORT3M/3S/2M/2S | imagen | ortodoncia | M | Adquisición, distinta de trazado. |
| B07 Componentes | Trazado Cefalométrico | Trazado/análisis cefalométrico | F | — | — | cefalometría analítica | análisis requerido | ORT3M/3S/2M/2S | trazado, informe | ortodoncia | M | Servicio/análisis separado de captura de Rx. |
| B08 Componentes | Fotografías Digitales e impresas | Fotografías clínicas odontológicas | A | PHOTO | DENTAL | — | tipo, fondo | ORT3M/3S/2M/2S | fotografías; impreso opcional | ortodoncia | M | “Digitales e impresas” son formatos de entrega. |
| B09 Componentes | Presentación Digital | Presentación del expediente de registros | D | — | — | — | medio de entrega | ORT3M/3S/2M/2S | presentación | ortodoncia | — | No estudio. |
| B10 Componentes | Favor de especificar detalles del estudio | Detalles de solicitud | B | — | — | — | notas | ORT3M/3S/2M/2S | — | ortodoncia | — | Campo libre complementario. |
| B11 Modelos | Modelos de Estudio | Modelo de estudio dental | A | MODEL | DENTAL | modelos dentales | material/soporte según contexto | ORT3M/2M | modelo físico/digital | ortodoncia, prótesis | M | Validar uso diagnóstico frente a fabricación protésica. |
| B12 Modelos | Yeso vélmix para montaje | Material de modelo | E | MODEL | DENTAL | yeso Velmix | material | ORT3M/2M | modelo físico | ortodoncia | — | Material/proveedor, no estudio. |
| B13 Modelos | Yeso blanco para montaje | Material de modelo | E | MODEL | DENTAL | — | material | ORT3M/2M | modelo físico | ortodoncia | — | No estudio independiente. |
| B14 Modelos | Zócalo 3D | Base/terminación de modelo | E | MODEL | DENTAL | — | presentación de modelo | ORT3M/2M | modelo | ortodoncia | — | Terminación de laboratorio. |
| B15 Modelos | Modelo anatómico 3D | Variante de modelo | E | MODEL | DENTAL | — | tipo de modelo | ORT3M/2M | modelo digital/físico | ortodoncia | — | No identidad material nueva sin validación. |
| B16 Modelos | Para Montaje 3D | Preparación para montaje | E | MODEL | DENTAL | — | destino de modelo | ORT3M/2M | modelo | ortodoncia | — | Especificación de fabricación. |
| B17 Modelos | Especificaciones | Notas de modelo | B | MODEL | DENTAL | — | notas | ORT3M/2M | — | ortodoncia | — | Texto libre. |
| B18 Envío | Método de Envío: Electrónico | Entrega electrónica | D | — | — | — | medio_entrega | — | electrónico | general | — | Preferencia logística. |
| B19 Envío | Método de Envío: Impreso | Entrega impresa | D | — | — | — | medio_entrega | — | impreso | general | — | Preferencia logística. |
| B20 Alineadores | Invisalign | Marca/flujo de alineadores | E | SCAN | DENTAL | — | propósito/compatibilidad | — | escaneo/modelo | ortodoncia | — | Marca comercial; no estudio. |
| B21 Alineadores | Otros Alineadores | Flujo de alineadores genérico | E | SCAN | DENTAL | — | propósito/compatibilidad | — | escaneo/modelo | ortodoncia | — | No estudio distinto. |
| B22 Ortodoncia | Trazado: | Instrucción de trazado | B | — | — | — | protocolo/análisis solicitado | ORT3M/3S/2M/2S | trazado | ortodoncia | — | Campo para servicio B07. |
| B23 Radiografías | Radiografías Digitales | Encabezado de sección | G | — | — | — | — | — | — | dental | — | No estudio agregado. |
| B24 Radiografías | Impreso | Copia impresa de Rx | D | — | — | — | medio_entrega | — | impresión | dental | — | No segunda imagen. |
| B25 Radiografías | Electrónico | Copia electrónica de Rx | D | — | — | — | medio_entrega | — | archivo | dental | — | No segunda imagen. |
| B26 Radiografías | Panorámica (Ortopantomografía) | Radiografía panorámica dental | A | PAN | IMAGEN | ortopantomografía | — | ORT3M/3S/2M/2S | imagen | dental | M | Mismo PAN de B05; alias, no nuevo estudio. |
| B27 Radiografías | Lateral de cráneo | Radiografía cefalométrica lateral | A | CEF | IMAGEN | Rx lateral | vista=lateral | ORT3M/3S/2M/2S | imagen | ortodoncia | M | Mismo CEF de B06. |
| B28 Radiografías | Lateral de cráneo Full | Radiografía cefalométrica lateral | B | CEF | IMAGEN | lateral full | cobertura/amplitud | — | imagen | ortodoncia | M | “Full” requiere validación de protocolo; no nuevo estudio aún. |
| B29 Radiografías | ATM comparativa vista Lateral | Radiografía comparativa de ATM | A | ATM | IMAGEN | ATM lateral | vista=lateral, bilateralidad | — | imagen | cirugía, ATM | M | Una identidad ATM con vista parametrizada. |
| B30 Radiografías | ATM comparativa vista PA | Radiografía comparativa de ATM | B | ATM | IMAGEN | ATM PA | vista=PA, bilateralidad | — | imagen | cirugía, ATM | M | No segundo estudio. |
| B31 Radiografías | Tomografía (Al reverso) | Remisión a formulario Cone Beam | G | CBCT | IMAGEN | — | — | — | — | dental | M | Referencia a hoja 1, no otra modalidad demostrada. |
| B32 Radiografías | Anteroposterior A.P. | Radiografía craneofacial de proyección | A | CRAN | IMAGEN | AP | vista=AP | — | imagen | cirugía, ortodoncia | M | No confundir con PA. |
| B33 Radiografías | Posteroanterior P.A. | Radiografía craneofacial de proyección | B | CRAN | IMAGEN | PA | vista=PA | — | imagen | cirugía, ortodoncia | M | Misma familia de adquisición, vista distinta. |
| B34 Radiografías | Carpal | Radiografía carpal/edad ósea | A | DEFER: rx_hand validation | IMAGEN | mano-muñeca | protocolo/objetivo | — | imagen | ortodoncia | N:rx_hand | No duplicar ni reutilizar `rx_hand` hasta confirmar cobertura y protocolo. |
| B35 Radiografías | Senos Maxilares | Radiografía craneofacial de proyección | B | CRAN | IMAGEN | proyección de senos | vista=senos_maxilares | — | imagen | cirugía | M | Validar protocolo específico antes de implementación. |
| B36 Radiografías | Submentón / Vertex | Radiografía craneofacial de proyección | B | CRAN | IMAGEN | submentovértice | vista=SMV | — | imagen | cirugía | M | Proyección, no identidad por orientación. |
| B37 Radiografías | Cadena | Opción “Cadena” no interpretada | G | — | — | — | — | — | — | ortodoncia | — | Aparece dos veces, junto a “Lateral de cráneo” y “Lateral de cráneo Full”; significado/protocolo incierto, consultar al proveedor antes de modelar. |
| B38 Fotografías | Fotografías Intraorales y Extraorales | Fotografías clínicas odontológicas | A | PHOTO | DENTAL | registros fotográficos | tipo=intra/extra/ambos | ORT3M/3S/2M/2S | fotografías | ortodoncia, dental | M | Un servicio de registros con tipo. |
| B39 Fotografías | Fondo Blanco | Fondo fotográfico blanco | E | PHOTO | DENTAL | — | fondo | — | fotografías | dental | — | Configuración de captura. |
| B40 Fotografías | Fondo Negro | Fondo fotográfico negro | E | PHOTO | DENTAL | — | fondo | — | fotografías | dental | — | Configuración de captura. |
| B41 Fotografías | Digitales | Fotos digitales | D | PHOTO | DENTAL | — | medio_entrega | — | archivos | dental | — | No estudio distinto. |
| B42 Fotografías | Impresas | Fotos impresas | D | PHOTO | DENTAL | — | medio_entrega | — | impresión | dental | — | No estudio distinto. |
| B43 Fotografías | Observaciones | Nota fotográfica | B | PHOTO | DENTAL | — | notas | — | — | dental | — | Campo libre. |
| B44 Escaneo | Escaneo Intraoral | Escaneo intraoral dental | A | SCAN | DENTAL | impresión digital | arco | ORT3S/2S | malla/modelo | ortodoncia, prótesis | M | Captura, no archivo STL por definición. |
| B45 Escaneo | Maxilar Superior | Escaneo arco superior | B | SCAN | DENTAL | maxilar | arco=maxilar | ORT3S/2S | malla/modelo | dental | — | Parámetro, no estudio. |
| B46 Escaneo | Maxilar Inferior | Escaneo arco inferior | B | SCAN | DENTAL | mandíbula | arco=mandibular | ORT3S/2S | malla/modelo | dental | — | Parámetro, no estudio. |
| B47 Escaneo | Ambos Maxilares | Escaneo ambos arcos | B | SCAN | DENTAL | — | arco=ambos | ORT3S/2S | malla/modelo | dental | — | Parámetro, no dos estudios automáticamente. |
| B48 Escaneo | Invisalign | Uso con Invisalign | E | SCAN | DENTAL | — | compatibilidad/destino | — | escaneo | ortodoncia | — | Marca; no identidad canónica. |
| B49 Entrega | Entregar al paciente | Destinatario paciente | D | — | — | — | destinatario | — | entrega | general | — | Preferencia, no estudio. |
| B50 Entrega | Enviar al consultorio (únicamente estudio completo) DENTRO DE AGS. | Destinatario consultorio | D | — | — | — | destinatario/restricción local | — | entrega | general | — | La limitación geográfica es política del proveedor. |
| B51 Entrega | ¿Desea Contribuir al Cuidado del Medio Ambiente? … Sí / No | Sólo entrega electrónica | D | — | — | — | preferencia electrónica | — | entrega | general | — | No modifica la solicitud clínica. |

**Conteo:** 25 conceptos/leyendas del frente + 51 del reverso = **76 filas auditadas**. B05/B26, B06/B27 y F01/B31 son referencias repetidas entre secciones; el conteo mide conceptos visibles por lugar, no estudios únicos. Las cuatro cabeceras de paquete se mantienen separadas porque son cuatro selecciones distintas. “Cadena” y “Lateral Full” quedan expresamente pendientes de aclaración.

## Colisiones y catálogo canónico propuesto

| Concepto buscado | Estado entre 183 activos | Decisión |
|---|---|---|
| Panorámica / ortopantomografía | MISSING | Nuevo PAN con alias. |
| Lateral cefalométrica | MISSING | Nuevo CEF; “Rx lateral” alias. |
| CBCT dental/maxilofacial | EXISTING_NEAR_EQUIVALENT `ct_head` | Nuevo CBCT; TAC convencional de cráneo no lo representa. |
| ATM comparativa | MISSING | Nuevo ATM con vista. |
| Radiografías craneofaciales AP/PA/senos/submentovértice | MISSING | Nuevo CRAN con vista; verificar protocolos. |
| Carpal | EXISTING_NEAR_EQUIVALENT `rx_hand` | Resolver equivalencia de mano/muñeca antes de sembrar o reutilizar. |
| Escaneo intraoral | MISSING | Nuevo SCAN. |
| Fotografías odontológicas | MISSING | Nuevo PHOTO. |
| Modelo de estudio dental | MISSING | Nuevo MODEL. |
| Patología bucal | MISSING | Futuro: biopsia/histopatología oral requiere auditoría separada; citología cervical no equivale. |

`DENTAL_CANONICAL_STUDY_CANDIDATES` = **8**; son claves *propuestas*, no IDs ni filas creadas. Las cinco adquisiciones por imagen se distinguen de los tres registros odontológicos no radiográficos por la modalidad, no por la especialidad que los solicita. La categoría clínica sigue siendo independiente de la agrupación de navegación.

| key propuesto | display_name_es | categoría | alias de búsqueda | descripción y parámetros de solicitud | evidencia |
|---|---|---|---|---|---|
| `dental_cbct` | Tomografía dental y maxilofacial de haz cónico | IMAGEN | Cone Beam, CBCT, tomografía dental | Captura volumétrica 3D; cobertura, arco, región/dientes, indicación; FOV exacto sólo técnico opcional. | F01–F16, B31; ADA/AAOMR. |
| `dental_panoramic_xray` | Radiografía panorámica dental | IMAGEN | Ortopantomografía, Rx panorámica | Imagen panorámica de maxilares/dentición. | B05, B26; AAO. |
| `dental_cephalometric_xray` | Radiografía cefalométrica lateral | IMAGEN | Rx lateral, lateral de cráneo | Adquisición lateral; extensión “Full” como alcance a validar. No incluye trazado por identidad. | B06, B27–B28; AAO. |
| `craniofacial_projection_xray` | Radiografía craneofacial por proyección | IMAGEN | AP, PA, senos maxilares, submentovértice | Proyección solicitada obligatoria; validar que protocolos particulares pueden agruparse sin pérdida. Si no, separar antes de sembrar. | B32–B33, B35–B36. |
| `tmj_comparative_xray` | Radiografía comparativa de articulaciones temporomandibulares | IMAGEN | Rx ATM, ATM lateral, ATM PA | Vistas lateral/PA y bilateralidad; protocolo comparativo. | B29–B30. |
| `dental_intraoral_scan` | Escaneo intraoral dental | DENTAL | impresión digital intraoral | Captura digital de uno o ambos arcos; destino comercial no cambia identidad. | B44–B48. |
| `dental_clinical_photographs` | Fotografías clínicas odontológicas | DENTAL | fotos intraorales, fotos extraorales | Registro fotográfico intraoral, extraoral o ambos; fondo/medio de entrega como opciones. | B08, B38–B43; AAO. |
| `dental_study_model` | Modelo de estudio dental | DENTAL | modelo dental, modelo de estudio | Registro/modelo de estudio; material, base y fabricación se especifican sin multiplicar identidades. | B11–B17; AAO. |

**Pendientes fuera del V1 propuesto:** `Carpal` exige comparación clínica con `rx_hand`; no se suma a los ocho ni se presupone equivalencia. No hay periapical/intraoral dental explícita en estas hojas: es una brecha probable del catálogo universal, pero requiere fuente/protocolo adicional antes de un nuevo candidato. Histopatología oral también exige fuente separada. Ninguna cifra de “conceptos ausentes” debe confundirse con el número de claves nuevas: hay siete identidades clínicas nuevas inequívocas, más la identidad `CRAN` condicionada a validar agrupación de proyecciones; los demás conceptos faltantes son parámetros, presets, servicios o artefactos.

## Modelo de parámetros de pedido

`DENTAL_ORDER_PARAMETER_MODEL` tiene **11 familias**, con versión/esquema de solicitud por ítem para preservar la identidad histórica cuando una orden tenga sucesor. No se debe reinterpretar retroactivamente una nota libre o el ítem V1 como una configuración V2.

| Parámetro | Tipo | Alcance | Valores/validación propuestos | Regla |
|---|---|---|---|---|
| `coverage_scope` | específico | CBCT | localized, single_arch, both_arches, maxillofacial | Semántica clínica primaria; no número FOV. |
| `arch` | reutilizable dental | CBCT, SCAN, MODEL | maxillary, mandibular, both | Requerido para single_arch/SCAN según caso. |
| `side` | reutilizable genérico | ATM, región cuando proceda | left, right, bilateral | No reemplaza vista. |
| `numbering_system` | reutilizable dental | dientes | explícito, p. ej. ISO_3950 si se adopta | Nunca inferir de un número suelto; autoridad aún ausente. |
| `selected_teeth` | reutilizable dental | CBCT localizado y futuras solicitudes dentales | lista de códigos validada contra sistema | Puede coexistir con región anatómica. |
| `region_of_interest` | reutilizable genérico | CBCT y otras imágenes | región anatómica estructurada + texto complementario | Para zonas sin dientes seleccionables. |
| `projection_view` | reutilizable en radiografía | CEF, ATM, CRAN | lateral, AP, PA, SMV, senos; vocabulario por estudio | Evita identidades AP/PA duplicadas. |
| `acquisition_extent` | específico | CEF/CBCT | “Full” a validar; 2D/3D proviene de identidad, no selector intercambiable | CBCT es 3D y Rx es 2D; no convertir modalidad en casilla libre. |
| `fov_cm` | específico técnico opcional | CBCT | dimensión exacta si justificada, junto a cobertura clínica | No obligatoria ni atada a una sucursal/equipo. |
| `record_variant` | específico por estudio | PHOTO, SCAN, MODEL | tipo foto intra/extra, fondo, alcance escaneo, clase/modelo | Separar parámetro clínico de acabado del proveedor. |
| `analysis_report_requirement` | reutilizable de servicio | CEF/CBCT/otros | trazado o interpretación profesional solicitado | Representar servicio/informe separado; no confundir con adquisición. |

**CBCT:** una identidad canónica con clases de cobertura `localized`, `single_arch`, `both_arches`, `maxillofacial`; arco y región/dientes donde aplique. Las dimensiones 3×3, 5×5, 8×5, 8×8, 12×9, 15×15 y 20×17 se conservan como dato técnico opcional si el médico realmente necesita pedir una medida, sujeto a validación de unidades y compatibilidad por proveedor; la indicación clínica se expresa por cobertura. Las recomendaciones ADA/AAOMR favorecen selección individualizada y campo apropiado, no convertir tamaños de una máquina en estudios universales. No se recomendará CBCT automáticamente en perfiles de endodoncia, periodoncia ni pediatría.

**Zona dental:** la hoja muestra un diagrama en numeración de dos dígitos compatible visualmente con FDI/ISO 3950. DICOM referencia ISO 3950 para conceptos de diente; [ISO 3950](https://www.iso.org/standard/68292.html) define el sistema de designación de dos dígitos. La inspección del repo no encontró autoridad implementada de odontograma ni numeración dental. Futuro contrato: `numbering_system` + `selected_teeth` + `region_of_interest` + texto de aclaración; validar dentición permanente/temporal y forma de código antes de adoptar ISO. No convertir las X manuscritas en selección electrónica. **DENTAL_NUMBERING_AUTHORITY_STATUS = MISSING; source form FDI/ISO-3950-like, not yet MXMED authority.**

## Presets, servicios y artefactos

`DENTAL_ORDER_PRESET_CANDIDATES` = **4**. Los componentes se proponen como preselección editable del médico, no como una sola identidad, indicación automática ni producto vendido por un proveedor. La frase de la hoja enumera fotografía y presentación digital en las cuatro variantes. El trazado es un servicio asociado a la cefalométrica; radiología bucal interpretativa no consta como componente obligatorio de esos paquetes.

| Preset / etiqueta fuente | Componentes canónicos que preselecciona | Complemento y parámetros | Opcionales/artefactos | Autoridad |
|---|---|---|---|---|
| ORT3M — Estudio Ortodoncia 3D Modelo de Estudio | CBCT, PAN, CEF, PHOTO, MODEL | trazado cefalométrico; cobertura CBCT/modelo | presentación digital, formato impreso | Preset médico propuesto; paquete comercial del proveedor permanece separado. |
| ORT3S — Estudio Ortodoncia 3D Escaneo Intraoral | CBCT, PAN, CEF, PHOTO, SCAN | trazado cefalométrico; cobertura CBCT/arco escaneo | presentación digital, formato impreso | Igual. |
| ORT2M — Estudio Ortodoncia 2D Modelo de Estudio | PAN, CEF, PHOTO, MODEL | trazado cefalométrico; modelo | presentación digital, formato impreso | Igual; no CBCT. |
| ORT2S — Estudio Ortodoncia 2D Escaneo Intraoral | PAN, CEF, PHOTO, SCAN | trazado cefalométrico; arco escaneo | presentación digital, formato impreso | Igual; no CBCT. |

`PROPOSED_COMPLEMENTARY_SERVICE_COUNT` = **2**: (1) trazado/análisis cefalométrico, solicitables junto a la imagen, con resultado analítico propio; (2) interpretación/informe por radiólogo bucal, que puede ser prestación complementaria o componente de informe del resultado. “Costo extra” es condición comercial y no una clave clínica. Definir autoridad/contrato de servicios antes de automatizar cobro o proveedor. El modelo de orden puede expresar necesidad de informe aunque la contratación del profesional quede fuera del catálogo de estudios.

`DENTAL_RESULT_ARTIFACT_MODEL` = **6 tipos**: (1) imagen radiográfica 2D o reconstrucción visual, (2) informe/trazado/presentación PDF, (3) serie/archivo DICOM, (4) malla STL, (5) fotografías clínicas, (6) modelo digital. Modelo físico/impreso es salida material y entrega, no objeto digital almacenado. DICOM es el estándar de interoperabilidad y archivo de imágenes, no un acto diagnóstico; STL es representación geométrica/exportación. Un artefacto debe mantener origen en **orden exacta + ítem exacto + versión exacta**, relación con resultado y procedencia del proveedor, siguiendo el límite de publicación B3/INTEROP y OR02B-REL01. No se propone implementar hoy almacenamiento DICOM/STL.

## Categoría y navegación dental propuesta

Asignar `IMAGEN` a CBCT, PAN, CEF, ATM y CRAN: son adquisiciones radiológicas aunque las ordene un dentista. Asignar `DENTAL` a SCAN, PHOTO y MODEL por ser registros diagnósticos odontológicos no radiológicos. Carpal mantiene su categoría `IMAGEN` si se confirma equivalencia o se crea clave propia. Trazado/interpretación requieren contrato de servicio; no forzar el enum de estudios para representar honorarios. `PATOLOGIA` futura puede contener histopatología oral tras auditoría específica; las citologías cervicales actuales no son útiles como atajo de Patología Bucal.

Grupos mínimos de navegación V1: **Radiología dental 2D** (PAN, CEF, ATM, CRAN; Carpal sólo tras resolver colisión), **Cone Beam / CBCT** (CBCT), **Registros ortodóncicos** (PHOTO, SCAN, MODEL y cuatro presets sólo cuando exista autoridad), y **Escaneo y modelos** (SCAN, MODEL). El último podría ser enlace inferior si el límite visual de accesos rápidos obliga a tres. Son agrupadores sobre claves canónicas de `IMAGEN` y `DENTAL`, **no** nueva taxonomía clínica. “Ortodoncia”, “Fotografía”, “ATM/Maxilofacial” pueden servir como encabezados/filtros contextuales, pero siete accesos separados crearían ruido y repetición. No activar enlaces vacíos antes de tener filas. Búsqueda y acceso al catálogo completo nunca se limitan por especialidad.

### Matriz para las 16 clasificaciones actuales

En todas las filas: enlace inferior **Todos los estudios**, búsqueda universal y órdenes mixtas/custom vigentes; el perfil sólo ordena accesos. `R2` = Radiología dental 2D, `CB` = Cone Beam, `RO` = Registros ortodóncicos, `EM` = Escaneo y modelos. Presets se muestran sólo después de implementar su autoridad; los nombres indicados no son recomendaciones automáticas de exposición.

| Etiqueta exacta | Accesos rápidos propuestos (orden) | Promociones contextuales | Enlace inferior / fallback |
|---|---|---|---|
| Cirujano Dentista (título) | R2, CB, EM, RO | PAN, CBCT, SCAN | Todos; perfil dental general. |
| Dentista | R2, EM, CB, RO | PAN, SCAN, CBCT | Todos; general. |
| Odontología | R2, EM, CB, RO | PAN, SCAN, CBCT | Todos; general. |
| Odontología Estética | EM, RO, R2, CB | PHOTO, SCAN, MODEL | Todos; CBCT sólo si se busca. |
| Ortodoncia | RO, R2, EM, CB | PAN, CEF, trazado, PHOTO, SCAN/MODEL; ORT3M/3S/2M/2S tras autoridad | Todos; CBCT opcional según indicación. |
| Ortopedia Dental | RO, R2, EM, CB | PAN, CEF, PHOTO, MODEL; Carpal pendiente de protocolo | Todos; no convertir RX Mano en carpal sin validación. |
| Implantología | CB, R2, EM, RO | CBCT, PAN, SCAN, MODEL | Todos; priorización, no indicación automática. |
| Implantología Dental | CB, R2, EM, RO | CBCT, PAN, SCAN, MODEL | Todos; misma clave canónica. |
| Endodoncia | R2, CB, EM, RO | PAN; CBCT localizado sólo cuando se elija; futura radiografía periapical requiere fuente | Todos; no inferir CBCT rutinario. |
| Periodoncia | R2, EM, CB, RO | PAN, PHOTO; CBCT accesible sin promoción agresiva | Todos; no inferir CBCT rutinario. |
| Cirugía Maxilofacial | CB, R2, EM, RO | CBCT maxilofacial, PAN, ATM, CRAN | Todos; cobertura/vistas explícitas. |
| Cirugía Oral y Maxilofacial | CB, R2, EM, RO | CBCT maxilofacial, PAN, ATM, CRAN | Todos; mismo catálogo universal. |
| Odontopediatría | R2, RO, EM, CB | PAN/CEF según indicación y edad; PHOTO | Todos; selección individualizada y evitar duplicado pediátrico. |
| Patología Bucal | R2, CB, EM, RO | Imagen según caso; **sin** promoción de citología cervical | Todos; futuras biopsia/histopatología oral requieren auditoría. |
| Prótesis Bucal | EM, R2, CB, RO | SCAN, MODEL, PHOTO, PAN | Todos; fabricación no equivale a estudio. |
| Rehabilitación Oral | EM, R2, CB, RO | SCAN, MODEL, PHOTO, PAN | Todos; mismos registros universales. |

AAO distingue radiografías, fotografías y modelos/escaneos como registros; por eso los presets no aplanan todo a una “imagen de ortodoncia”. ADA/AAOMR y AAE/AAOMR respaldan una selección individualizada de radiografía/CBCT, especialmente en endodoncia y pacientes pediátricos; AAOMR diferencia imagen inicial y planificación de implantes. Estos principios justifican **accesibilidad de navegación**, no reglas de decisión clínica o autorización.

## Impacto arquitectónico y secuencia recomendada

| Clasificación del cambio futuro | Necesidad | Estado de CAT01 |
|---|---|---|
| `CATALOG_SEED_ONLY` | Validar protocolos y sembrar hasta ocho claves inequívocas, con categoría única; resolver CRAN antes de sembrarlo. | Propuesta, sin cambios. |
| `ALIASES_ONLY` | Alias de búsqueda como “Ortopantomografía”, “Cone Beam”, “Rx lateral”; revisar ambigüedad de “tomografía” genérica. | Propuesta, sin cambios. |
| `NAVIGATION_CONFIG` | Enlaces dentales sobre claves canónicas ya activas; preservar fallback y catálogo universal. | Propuesta, sin cambios. |
| `ORDER_PARAMETER_CONTRACT` | Datos tipados por ítem, validados, versionados e inmutables con la orden histórica; lectura/impresión/resultado preservan versión exacta. El contrato actual distingue `study_type_id/key` y notas, pero no ofrece autoridad tipada para arco/cobertura/dientes/vista. | **Necesario para un V1 seguro** de CBCT/SCAN/ATM; diseño y QA futuros. |
| `PACKAGE/PRESET_AUTHORITY` | Plantillas médicas editables con componentes versionados y trazabilidad por ítem; distintas de paquetes/precios de proveedor. | Diferir después del V1 mínimo. |
| `RESULT_ARTIFACT_FUTURE` | DICOM/STL/fotos/modelos e informe con metadatos, autorización, procedencia y descarga privada; conservar frontera B3. | Diferir. |
| `NEW_DOMAIN_AUTHORITY` | Sistema de numeración/odontograma, servicios profesionales, protocolos carpal y patología oral; gobernanza de vocabularios. | Diferir y estudiar por separado. |

**Mínimo seguro V1 implementable en fase posterior:** (1) ratificar PAN, CEF, CBCT, ATM, SCAN, PHOTO y MODEL; resolver CRAN por validación de proyecciones antes de incluirlo; (2) semilla/alias sin duplicados y pruebas de búsqueda; (3) contrato pequeño de parámetros tipados por ítem para cobertura/arco/vista y región anatómica; dientes sólo si se ratifica `numbering_system`; (4) navegación por especialidad a estudios existentes con fallback completo; (5) preservar orden/ítem/versión exactos y flujos actuales de custom/mixed orders. Si el contrato de parámetros aún no existe, no ofrecer un selector CBCT que pierda cobertura en nota libre: aplazar esa vía específica o mostrar un flujo explícito de estudio personalizado, sin afirmar que el V1 dental está completo. `CRAN` y Carpal son pendientes de validación clínica, no atajos de implementación. No se requieren presets, integración de proveedor, DICOM/STL ni nuevas tablas de odontograma para ese primer corte.

**Modelo futuro completo:** ubicación dental con sistema de numeración explícito, dentición y áreas no dentarias; parámetros clínicos CBCT diferenciados de FOV de equipo; protocolos por vista y edad cuando proceda; presets médicos versionados, editables y separados de bundles comerciales; servicios de trazado/interpretación con responsable, informe y autorización; artefactos DICOM/STL/fotográficos/modelos con procedencia, formato, permisos, retención y entrega; coordinación con proveedores sin marca en identidad canónica; orden exacta e ítems exactos propagados a referencia/servicio/resultado, sin inferir cobertura del sucesor. Revisión de periapical, Carpal e histopatología oral con nuevas fuentes antes de ampliar catálogo.

**Decisiones pendientes del Director antes de implementación:** ratificar ocho claves/categorías; validar la agrupación CRAN y protocolo de Carpal; decidir adopción formal de ISO 3950 y alcance de dentición temporal; confirmar el significado de “Cadena”/“Lateral Full” con proveedor; definir si PHOTO/MODEL se tratan como estudios diagnósticos en el contrato vigente o si requieren autoridad de servicio distinta; aprobar contrato de parámetros e identidad histórica. Ninguna decisión altera hoy la base de datos ni la UI.
