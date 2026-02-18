# Glosario UI MXMed

## Propósito
Este documento define la terminología oficial de UI en MXMed para evitar ambigüedades entre producto, diseño, QA y desarrollo.

## Reglas de uso
- Referencia obligatoria en PRD, tickets, QA y cambios de UI.
- Si un término no está aquí, usar "Por definir" y proponer alta en este glosario.
- En caso de conflicto, prevalece este documento hasta que se actualice explícitamente.

## Término: Tab
- Término oficial: Tab
- Sinónimos comunes: pestaña, tab principal
- No confundir con: SubTab, botón de navegación externa
- Ubicación exacta en la UI: `index.html` sección `#p-expediente`, botones con `data-bs-target` y `data-tab-key` (ej. `#t-datos`, `#t-historia`)
- Fuente de verdad: `index.html` (estructura de tabs en Expediente)

## Término: SubTab
- Término oficial: SubTab
- Sinónimos comunes: pestaña secundaria, tab interno
- No confundir con: Tab (nivel principal), filtro
- Ubicación exacta en la UI: zonas con tabs embebidos como `#tabs-info` (`mm-tabs-embed`) dentro de paneles
- Fuente de verdad: `index.html` (bloques `mm-tabs mm-tabs-embed`)

## Término: Panel
- Término oficial: Panel
- Sinónimos comunes: panel de módulo, vista interna
- No confundir con: Card (componente), sección/tab completa
- Ubicación exacta en la UI: contenedores embebidos clínicos `div.clinical-panel` (modo `embed=1`)
- Fuente de verdad: `modules/clinical/ui/historial.php`, `modules/clinical/ui/encounter.php`, `modules/clinical/ui/document.php`

## Término: ContentArea
- Término oficial: ContentArea
- Sinónimos comunes: área de contenido, zona principal
- No confundir con: Shell global (`header-top`, `mm-sidebar`)
- Ubicación exacta en la UI: `<main class="mm-main">` del layout MXMed
- Fuente de verdad: `modules/_partials/mm_shell_top.php`

## Término: EmbedView
- Término oficial: EmbedView
- Sinónimos comunes: modo embebido, vista embebible
- No confundir con: standalone, shell completo
- Ubicación exacta en la UI: query param `embed=1`; wrapper `div.clinical-embed`
- Fuente de verdad: `modules/clinical/ui/historial.php`, `modules/clinical/ui/encounter.php`, `modules/clinical/ui/document.php`, `modules/_partials/clinical_embed.php`

## Término: Historia Clínica
- Término oficial: Historia Clínica
- Sinónimos comunes: historia, expediente clínico estructurado
- No confundir con: Historial de Atención
- Ubicación exacta en la UI: Tab `data-tab-key="t-historia"` en `#p-expediente`
- Fuente de verdad: `index.html` (tab label “Historia Clínica”)

## Término: Historial de Atención
- Término oficial: Historial de Atención
- Sinónimos comunes: timeline clínico, historial de eventos
- No confundir con: Historia Clínica
- Ubicación exacta en la UI: botón con `data-action="open-historial-atencion"` y vistas `modules/clinical/ui/historial.php`
- Fuente de verdad: `index.html`, `docs/clinical/TIMELINE_V1_CONTRACT.md`, `docs/clinical/CIERRE_TIMELINE_V1.md`

## NO CONFUNDIR: Historia Clínica vs Historial de Atención
- Historia Clínica: captura/consulta de información clínica estructurada por secciones del expediente.
- Historial de Atención: línea temporal de eventos (encounters, documentos, agenda) centrada en secuencia temporal.
- Regla de naming: ambos términos coexisten; no usar uno como alias del otro en UI, QA o documentación.

## Término: Chip
- Término oficial: Chip
- Sinónimos comunes: píldora, etiqueta interactiva
- No confundir con: Badge (estado), Tag genérico
- Ubicación exacta en la UI: componentes `.chip-*` y `.hc-chip-*`; en historial clínico indicadores `.mm-chip`
- Fuente de verdad: `index.html` (chips en Historia Clínica), `modules/clinical/ui/historial.php` (`.mm-chip`)

## Término: Badge
- Término oficial: Badge
- Sinónimos comunes: insignia de estado
- No confundir con: Chip
- Ubicación exacta en la UI: elementos con clase Bootstrap `.badge` (ej. estados en seguridad y módulos clínicos legacy)
- Fuente de verdad: `index.html`, `docs/assets/partials/*.html`

## Término: Card
- Término oficial: Card
- Sinónimos comunes: tarjeta, bloque de contenido
- No confundir con: Panel (contenedor de vista embebida)
- Ubicación exacta en la UI: componente MXMed `.mm-card` con sub-bloques `.head` y `.body`
- Fuente de verdad: `index.html`, `docs/assets/css/style.css`, vistas clínicas UI

## Término: Toolbar
- Término oficial: Toolbar
- Sinónimos comunes: barra de acciones, action bar
- No confundir con: header global del sistema
- Ubicación exacta en la UI: grupos de acciones locales en vista (ej. botones de filtro y navegación dentro de cada pantalla)
- Fuente de verdad: Por definir
- Nota: Formalizar componente toolbar reusable en diseño sistema si se estandariza en más módulos.

## Términos legacy ↔ oficiales
- Por definir (sin mapeos adicionales registrados en esta versión).
