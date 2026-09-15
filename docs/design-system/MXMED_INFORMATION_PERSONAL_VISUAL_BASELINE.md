# México Médico — baseline visual canónico de Información Personal

## Propósito y autoridad

Este documento registra la implementación aprobada de **INFORMACIÓN PERSONAL** como autoridad visual canónica del Admin médico. Es un inventario de la interfaz renderizada en el commit `d3be220860221cc57f62c680fa7b401844f2e09c`; no propone una reinterpretación ni corrige diferencias existentes.

La auditoría se ejecutó el 15 de septiembre de 2026 sobre el runtime real, con estilos computados y revisión visual en `1440×900`, `1366×768`, `820×1180` y `390×844`. Se verificaron estados normal, `hover`, `focus-visible`, seleccionado, deshabilitado, modal y formulario sucio. En los cuatro viewports se obtuvo `scrollWidth <= clientWidth`, sin errores de consola y bloqueando cualquier solicitud de escritura durante la inspección.

Los valores se expresan en píxeles computados cuando el navegador resuelve `rem`, porcentajes o variables. Los colores se incluyen en hexadecimal y, cuando la transparencia forma parte del diseño, en `rgba()`.

## Fuentes canónicas

| Familia | Fuente principal | Selectores o ámbito |
|---|---|---|
| Estructura, tabs, controles, tarjetas, medios, contacto, firma, modales y bandeja | `assets/css/style.css` | `#p-info`, `#tabs-info`, `#t-info-datos` y descendientes |
| Botones compartidos | `assets/css/datos-generales-buttons.css` | `:is(#t-info-datos, #mxpi-floating-save, ... ) .btn` |
| Encabezados de sección y composición de medios | `assets/css/professional-information-layout.css` | `.mx-section-heading`, `.mf-title.mx-section-heading` |
| Chips | `assets/css/professional-information-layout.css` | `#t-info-profesional .chip`, `.chip-x`, `.chip-list` |
| Selector de tema | `assets/css/profile-theme-admin.css`, con composición final en `assets/css/style.css` | `.mx-theme-admin__*`, `#mx-profile-theme-*` |
| Estados de revisión de medios | `assets/css/owner-media-review.css` | `.mx-media-review-*`, `.mx-media-public-badge` |
| Foto y logotipo | `assets/css/profile-photo.css`, `assets/css/professional-logo.css`, con geometría final en `assets/css/style.css` | `#mxpi-photo-*`, `#mx-dg-logo-*` |

La cascada final y los estilos computados prevalecen sobre declaraciones históricas anteriores del mismo selector.

## 1. Página y panel

**Propósito:** superficie principal que contiene tabs y módulos de perfil.

| Elemento | Ejemplo / selector | Valor computado de escritorio | Móvil `390×844` |
|---|---|---|---|
| Fondo exterior | `body` | `#00B0C5` | Igual |
| Tipografía base | `body` | Carlito, `16px / 24px`, 400, `#212529` | Igual |
| Panel exterior | `#p-info.mm-card` | transparente, sin borde ni sombra | radio exterior `14.4px` |
| Superficie principal | `#p-info.mm-card .body` | `#F7FBFC`; borde `1px solid rgba(7,59,90,.10)`; radio `16px`; sombra `0 7px 22px rgba(7,59,90,.07)` | radio `14px`; padding `12px` |
| Padding de panel | `.body` | `18px 20px 22px` | `12px` |
| Ancho | `.mm-card` / `.body` | fluido dentro del shell; `1346px` a 1440 y `1272px` a 1366 | `372px` dentro del viewport de 390 |
| Ritmo mayor | `#t-info-datos.active` | `gap: 8px` | `gap: 10px` |

El panel usa todo el ancho disponible del shell. El contenido debe crecer de forma fluida; no se fija un ancho absoluto de pantalla.

## 2. Tipografía

| Rol | Tamaño / línea | Peso | Color | Ejemplo |
|---|---:|---:|---|---|
| Título de página | `32px / 35.84px` | 700 | `#FFFFFF` | encabezado del panel |
| Encabezado de sección | `20px / 24px` | 700 | `#06AEB8` | COLOR DEL PERFIL |
| Encabezado compacto de medios | `16px / 19.2px` | 700 | `#06536E` | FOTOGRAFÍA DE PERFIL |
| Etiqueta de campo | `16px / 24px` | 650 | `#073B5A` | Prefijo profesional |
| Texto corporal | `16px / 24px` | 400 | `#212529` | texto de modal y contenido general |
| Ayuda de medios | `13.432px / 16.118px` | 400 | `#597482` | formatos de imagen |
| Ayuda de contacto | `15.456px / 20.402px` | 400 | `#5F7685` | propósito de contacto |
| Ayuda de firma | `16.1px / 24.15px` | 400 | `#5F7685` | uso de firma |
| Etiqueta de botón | `12px / 14.4px` | 600 | según variante | Cambiar, Eliminar, Guardar |
| Badge de campo | `9.76px / 11.224px` | 800 | `#057391` | Público |
| Badge de medio | `10.4px / 11.96px` | 700 | `#FFFFFF` | Publicada, En revisión |
| Placeholder | `16px / 24px` | 400 | `#CCCCCC` | entradas vacías |

Toda esta superficie hereda `Carlito, "IBM Plex Sans", system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif`.

## 3. Encabezados de sección

El sistema general usa `.mx-section-heading`:

- fuente heredada, `20px`, peso 700, línea 1.2;
- color de texto e icono `#06AEB8`;
- icono Material Symbols Rounded de `50px` (`2.5em`), peso visual 100, `FILL 0`, `GRAD -50`, `opsz 24`;
- fila flex centrada, `gap: 8px`, sin padding propio;
- margen lo decide el bloque que lo contiene.

Ejemplos: **COLOR DEL PERFIL**, **DATOS DE CONTACTO** y **DIGITALIZAR FIRMA**. La firma agrupa título y subtítulo con `gap: 9px`.

La excepción de medios está limitada a `.mf-title.mx-section-heading`: `16px`, línea 1.2, `#06536E`, icono `40px`, `gap: 6px`, una sola línea en escritorio. No se debe propagar esta variante azul a otros encabezados.

## 4. Etiquetas de formulario

Las etiquetas normales (`.form-label`) se renderizan a `16px / 24px`, peso 650 y `#073B5A`, con `margin-bottom: 4px` en Información Personal. Los badges adyacentes mantienen aproximadamente `4.5px` de separación. La etiqueta queda inmediatamente antes del control y no adopta el color ni el tamaño de un encabezado de sección.

Ejemplos canónicos: Prefijo profesional, Denominación profesional, Nombre(s), Primer apellido, Segundo apellido, Correo electrónico, Teléfono y WhatsApp.

## 5. Texto auxiliar y secundario

Existen niveles intencionalmente distintos:

| Nivel | Selector / ejemplo | Tamaño | Línea | Peso | Color / separación |
|---|---|---:|---:|---:|---|
| Medio compacto | `.mf-sub` | `13.432px` | `16.118px` | 400 | `#597482`; debajo de preview/acciones |
| Tema | `.mx-theme-admin__head .small` | `16.1px` | heredada | 400 | `#5F7685`; texto breve |
| Contacto | `.mx-dg-contact-helper` | `15.456px` | `20.402px` | 400 | `#5F7685`; margen superior `2.4px` |
| Firma | `.mx-signature-heading > p` | `16.1px` | `24.15px` | 400 | `#5F7685`; `gap: 9px` desde título |
| Feedback de tema | `#mx-profile-theme-feedback` | `16.1px` | heredada | 700 | color secundario del bloque |

En escritorio, la ayuda de contacto procura una línea; debajo de 768px puede envolver. La copia concisa no se sustituye con párrafos permanentes.

## 6. Controles editables

Fuente: la base compartida de controles en `assets/css/style.css` para `#t-info-datos` y `#t-info-profesional`.

| Propiedad normal | Valor computado |
|---|---|
| Fondo | `#FFFFFF` |
| Borde | `1px solid #A9C2CE` |
| Radio | `9px` |
| Altura mínima | `42px` |
| Texto | `16px / 24px`, 400, `#123F56` |
| Placeholder | `#CCCCCC`, opacidad 1 |
| Padding | `6px 12px`; select reserva `36px` a la derecha |
| Sombra | `0 1px 2px rgba(7,59,90,.04)` |

En foco: borde `1px solid #008A98`, `box-shadow: 0 0 0 3px rgba(0,174,190,.16)` y `outline: 0`. La transición de borde y sombra dura `.15s`.

La Bio breve conserva `textarea`: en escritorio (`>=992px`) tiene `38px` de alto con `padding: 6px 96px 6px 12px` para el contador interno; a 820 y 390 se muestra a `88px`. El límite, validación y contador no forman parte de la presentación.

Un estado inválido debe conservar el patrón Bootstrap existente (`:invalid`/`.is-invalid`) y mensaje asociado; no debe sustituirse el halo de error con el halo turquesa de foco. VIS01 no observó un error activo, por lo que no se crea un nuevo valor canónico.

## 7. Controles de solo lectura y verificados

Los valores protegidos se distinguen de los editables:

- fondo `#FFFFFF`, borde `1px solid #CBDDE4`, radio `8px`;
- altura `42px`, padding `7px 11px`;
- texto `#173F54`, peso 650;
- sin sombra y sin halo de edición;
- los controles deshabilitados de Información Profesional usan `#F2F6F8`, texto `#405D6C`, borde `#DCE5E9` e inset izquierdo `3px #A9BDC7`, con opacidad 1.

La autoridad se refuerza con badges, no reduciendo la legibilidad. Un campo protegido no debe recibir el mismo tratamiento de foco que uno editable.

## 8. Sistema de botones

Fuente canónica: `assets/css/datos-generales-buttons.css`.

**Geometría compartida:** altura `32px` en escritorio y `42px` debajo de 768px; radio `7px`; `12px / 14.4px`, peso 600; padding horizontal `10px`; icono `16px`; `gap: 6px`; sin sombra. `focus-visible`: outline `3px solid #123D59`, offset `3px`. Deshabilitado: opacidad `.55`, cursor default.

| Variante | Normal | Hover | Active |
|---|---|---|---|
| Primario / Guardar | fondo `#00C040`, texto/icono blanco, borde transparente | `#00A936` | `#00972F` |
| Secundario | blanco, texto/icono `#008391`, borde `#01AFB9` | fondo `#EFFAFB` | `#DCF3F5` |
| Destructivo | blanco, texto/icono `#D5213B`, borde `#E6384F` | `#FFF2F3` | `#FFE4E7` |
| Sutil / utilidad | `#F8FBFC`, texto `#008391`, borde `#B5CBD2` | `#EEF6F8` | `#E2F0F3` |

Los iconos heredan el color de la variante. Guardar conserva verde por semántica; Restablecer no usa esta geometría y se documenta en la sección 14.

## 9. Tabs

Fuente: `assets/css/style.css`, `#tabs-info.mx-panel-tabs`.

- Contenedor: `#EEF5F7`, radio `12px`, padding y gap `7px`, borde transparente; altura `60px`.
- Escritorio/tablet: grid de tres columnas iguales.
- Tab: `44px` de alto, radio `9px`, borde transparente, padding `6px 10px`, gap icono-texto `8px`, peso 700.
- Activo: fondo exacto `#01AFB9`, texto e icono blancos, sin marco decorativo.
- Inactivo: fondo blanco, texto e icono `#073B5A`.
- Hover/foco inactivo: `#E7F8FA` y `#07536E`.
- `focus-visible`: `3px solid rgba(0,174,190,.24)`, offset `2px`.
- Etiqueta: `12.48px / 13.728px` en escritorio; alrededor de `11.52px` en móvil.
- Móvil: fila horizontal desplazable, elementos de aproximadamente `154px`; no comprime el texto hasta hacerlo ilegible.

## 10. Chips y tags

Fuente: `assets/css/professional-information-layout.css`. Aunque pertenecen a la familia compartida que consume Información Profesional, se catalogan para futuras comparaciones del Admin.

- cápsula: `inline-grid`, alto mínimo `30px`, fondo `#EFFAFB`, borde `1px solid #B9E4EB`, radio `16px`;
- texto `14.4px / 20px`, peso 400, `#003152`;
- padding `2px 4px 2px 8px`, gap interno `4px`;
- lista: gap horizontal `8px`, vertical `6px`, margen superior `6px` cuando contiene elementos;
- quitar: área `24×24px`, radio `6px`, transparente, `19px`, color `#00738F`;
- hover/foco de quitar: fondo `#E2F3F5`, color `#003152`; foco `2px solid #003152`, offset `1px`.

## 11. Badges y estados

| Semántica | Fondo | Texto | Borde / radio | Tipografía |
|---|---|---|---|---|
| Campo público | `#EDFAFD` | `#057391` | `1px #C4E8EF`, radio `999px` | `9.76px`, 800, padding `1.28px 6.08px` |
| Medio publicado | `#07536E` | blanco | sin borde; franja inferior | `10.4px / 11.96px`, 700, padding `3px 4px` |
| Revisión abierta | `#5F4283` | blanco | igual a badge de medio | igual |
| Requiere cambios | `#805300` | blanco | igual a badge de medio | igual |
| Verificado | superficie clara del control | `#176948` | contextual | `12.8px` en estado verificado |
| Error/feedback | transparente | `#8B3B28` | sin cápsula obligatoria | `12px / 15.6px` |

No se intercambian estos colores por conveniencia estética: expresan estados distintos.

## 12. Tarjetas y contenedores

La única superficie dominante es `.body` (`#F7FBFC`, radio 16px, borde y sombra suaves). Los grandes bloques de Información Personal (`#mx-public-identity-card`, `#mx-dg-contact-card`, `#dg-signature-card`) conservan radio `14px` y geometría de tarjeta, pero sus fondos, bordes internos y sombras computan transparentes/ningunos.

El agrupamiento se logra mediante encabezados, guías compartidas y ritmo vertical. No se deben reintroducir tarjetas anidadas con tintes y bordes alrededor de cada subsección. Los cuerpos mayores usan `--dg-content-inset: 30px` en escritorio y `26px` en móvil.

## 13. Tarjetas de medios

Fuente: reglas finales de `assets/css/style.css`, más `profile-photo.css`, `professional-logo.css` y `owner-media-review.css`.

- Columna de medios: `460px` en escritorio; la identidad ocupa el resto (`766px` a 1440, `692px` a 1366), con gap `16px`.
- Foto y logotipo se apilan y comparten ancho; el contenedor no añade fondo/borde anidado.
- Cabecera: variante compacta azul de sección 3.
- Preview: `120×120px`, fondo blanco, borde `1px solid #D3E3E9`, radio `10px`, padding `5px`; imagen con `object-fit: contain`.
- Grid observado: `120px 120px minmax(0,1fr)` cuando existen foto pública y candidata; acciones en la última columna.
- Acciones: stack con `row-gap: 10px`; botones de `32px` (`42px` móvil).
- Estado: franja sobre la base del thumbnail; el status pertenece a la imagen correspondiente.
- Móvil/tablet estrecha: la columna se apila con identidad, manteniendo thumbnails de 120px y acciones accesibles.

## 14. Selector de color del perfil

Fuentes: `assets/css/profile-theme-admin.css` y composición final en `assets/css/style.css`.

- Escritorio: selector y preview en una fila; columnas `594px + flexible` (`622px` a 1440, `548px` a 1366), gap `24px`.
- Paleta: siete columnas de `78px`; gap `8px` horizontal y `4px` vertical.
- Click target: `78×44px`; rectángulo visible `72×34px`; radio `5px`; borde `1px solid rgba(7,59,90,.16)`.
- Seleccionado: borde visible blanco, halo `0 0 0 3px #123D59` y marca de verificación; la selección no depende solo del color.
- Restablecer: mismo target y rectángulo visible, fondo `#01AFB9`, texto blanco de `11px`, único borde exterior `1px solid #B3B3B3`, hover `#009DA7`; foco `3px solid #123D59`, offset `3px`.
- Preview: radio `9px`, borde y fondo derivados del tema. En el tema auditado: `rgba(44,122,123,.14)` y borde `rgba(44,122,123,.42)`.
- Móvil: selector y preview se apilan; paleta de tres columnas de `78px`, sin scroll horizontal.

Restablecer es una excepción deliberada al sistema de botones ordinarios y no representa un color número 21.

## 15. Área de contacto

- Encabezado y helper usan las reglas de secciones 3 y 5.
- Escritorio y tablet `>=768px`: tres columnas iguales para correo, teléfono y WhatsApp, alineadas sobre una misma guía.
- Cada campo usa el sistema editable de la sección 6.
- Debajo de 768px: columnas apiladas, ancho completo y separación Bootstrap existente.
- El bloque se mantiene abierto, sin tarjeta interior coloreada; body con inset compartido.

## 16. Área de firma

- Encabezado general `#06AEB8`, icono `50px`; subtítulo secundario y gap `9px`.
- Autoridad visual: columna flex con `gap: 10px`.
- Canvas: ancho 100%, alto `180px`, fondo blanco, borde `1px solid #A9C2CE`, radio `9px`, `touch-action: none`.
- Foco del canvas: outline `3px solid #008A98`, offset `2px`.
- Preview existente: máximo `140px` de alto, `object-fit: contain`, alineado a la izquierda.
- Acciones usan botones compartidos; en móvil alcanzan 42px de alto.
- QR: contenedor máximo `272px`, padding `16px`, margen inferior `14px`, fondo blanco; imagen/canvas responsivo.

## 17. Modales

Ejemplo medido: confirmación de cambios sin guardar.

- Superficie: blanco opaco, ancho `500px` en desktop/tablet; en móvil `calc(100% - 16px)` (`374px` a 390); borde `1px solid rgba(0,49,82,.12)`, radio `8px`, sombra `0 8px 26px rgba(0,49,82,.16)`.
- Overlay: negro con opacidad `.5`.
- Título: `20px / 30px`, peso 500; header Bootstrap con padding `16px`.
- Body: `16px / 24px`, padding `16px`.
- Footer: padding `12px`, gap `8px`, borde superior `#DEE2E6`; botones alineados a la derecha.
- Orden visual: Salir sin guardar, Seguir editando, Guardar y continuar. El orden DOM y la navegación de teclado permanecen intactos.
- Móvil: footer en columna; botones de ancho disponible y mínimo `42px` por el sistema compartido; contenido medido `374×288px`.
- Prefix confirmation y asistente de firma reutilizan el mismo lenguaje de superficie y botones según su semántica.

## 18. Bandeja flotante de guardado

- Estado limpio: oculta. Estado sucio: aparece después del retardo funcional vigente de 4000ms.
- Posición escritorio/tablet: fija a `right: 32px; bottom: 32px`.
- Posición móvil: `left/right/bottom: 16px` más safe area; ancho `358px` a 390.
- Fondo: `rgba(129,226,223,.50)`; borde `1px solid rgba(0,49,82,.12)`; radio `12px`; sombra `0 8px 26px rgba(0,49,82,.16)`.
- Padding `12px`, gap vertical `8px`.
- Advertencia: `#C85C5C`, `14.25px / 15.675px`, peso 700, centrada.
- Guardar: primario verde compartido; `32px` escritorio y `42px` móvil, ancho completo dentro de la bandeja.
- Al estar sucio, el panel reserva espacio inferior para evitar que la bandeja tape contenido.

## 19. Sistema de iconos

- Encabezados: Google Material Symbols Rounded, outline (`FILL 0`), peso 100, gradiente -50; 50px general y 40px en medios.
- Botones: Bootstrap Icons o Material Symbols según la acción; caja `16×16px`, color heredado, `gap: 6px`; Bootstrap recibe un trazo sutil de `.2px`.
- Tabs: iconos de `24px` en caja de `25×25px`, color heredado.
- Los iconos semánticos no sustituyen el texto de acciones críticas.
- Todo icono decorativo hereda color; controles solo-icono requieren nombre accesible y foco visible.

## 20. Espaciado, grid y alineación

| Regla repetida | Valor canónico |
|---|---|
| Padding horizontal del panel | `20px` escritorio; `12px` móvil |
| Inset de bloques personales | `30px` escritorio; `26px` móvil |
| Separación de secciones mayores | `8px` escritorio/tablet; `10px` móvil |
| Etiqueta a control | `4px` |
| Filas internas de identidad | `8px` |
| Columna medios-identidad | `460px / minmax(0,1fr)`, gap `16px` |
| Selector-preview | `594px / minmax(0,1fr)`, gap `24px` |
| Acciones apiladas de medios | `10px` |
| Botón icono-texto | `6px` |
| Tabs | padding/gap `7px`; icono-texto `8px` |

Las guías horizontales de identidad, contacto y firma provienen del mismo inset. Se prefieren grid/flex y gaps compartidos; no offsets negativos ni posicionamiento absoluto para composición.

## 21. Reglas responsivas

1. `>=992px`: medios e identidad comparten fila; bio compacta de 38px; selector y preview comparten fila.
2. `1440×900`: contenido medido de 1304px; columnas identidad `460/766px`; tema `594/622px`.
3. `1366×768`: contenido medido de 1230px; columnas identidad `460/692px`; tema `594/548px`.
4. `820×1180`: medios e identidad se apilan; tabs aún ocupan tres columnas; contacto conserva tres columnas porque supera 768px.
5. `<768px`: contacto apila columnas, botones ordinarios suben de 32 a 42px, inset baja de 30 a 26px.
6. `390×844`: panel padding 12px; tabs desplazables; identidad, medios, tema, contacto y firma se apilan.
7. En 390px la paleta usa tres columnas y conserva swatches de 78px.
8. La bio vuelve a 88px en tablet/móvil para edición cómoda.
9. La bandeja ocupa el ancho disponible menos 32px y respeta safe area.
10. El modal usa margen lateral de 8px y footer vertical en móvil.
11. La ayuda de contacto puede envolver en móvil.
12. Ningún viewport auditado presentó overflow horizontal.

## 22. Estados de interacción

| Estado | Patrón canónico |
|---|---|
| Hover | cambio suave de fondo; no desplazamiento, escala ni sombra nueva |
| Focus visible | halo turquesa de controles o outline navy de botones, siempre distinguible del hover |
| Disabled | conserva texto legible; botón a `.55`; campo bloqueado a opacidad 1 y superficie gris |
| Invalid/error | mensaje persistente y semántica de error; no se elimina para ahorrar espacio |
| Selected | fondo activo en tabs; halo + check en swatches, nunca solo color |
| Dirty | bandeja flotante tras 4000ms y reserva de espacio inferior |
| Modal/blocking | overlay `.5`, foco contenido y acciones con orden visual semántico |
| Media review | badge unido al thumbnail: publicado, abierto o requiere cambios |

## 23. Excepciones de diseño aprobadas

1. **Guardar cambios es verde** (`#00C040`), no turquesa, por semántica de confirmación.
2. **Restablecer es una celda de paleta** de `78×44px`, no un botón ordinario.
3. **Los encabezados de medios son `#06536E` y 16px**, mientras otros encabezados son `#06AEB8` y 20px.
4. **Los iconos de encabezado son deliberadamente grandes**: 50px general, 40px medios.
5. **La bandeja de guardado conserva alpha `.50`**, mientras los modales usan blanco opaco.
6. **La bio es de una línea visual en desktop** y más alta en tablet/móvil.
7. **El panel evita tarjetas anidadas**; las secciones internas se ven abiertas sobre `#F7FBFC`.
8. **El status de medio es una franja sobre el thumbnail**, no un badge suelto junto al título.
9. **El tab activo no tiene marco azul decorativo**; solo conserva indicación al recibir foco real.
10. **Los controles protegidos mantienen peso 650 y borde propio**, sin imitar un input editable.
11. **El modal ordena visualmente sus tres acciones** sin alterar DOM, teclado ni comportamiento.
12. **Los tamaños móviles de botón son 42px** aunque desktop permanezca compacto a 32px.

## Paleta canónica observada

Los nombres siguientes describen el uso actual. Cuando existe una variable real, se muestra en la primera columna; los demás nombres son etiquetas documentales, no nuevos tokens CSS.

| Token / referencia | Propósito | Valor | Ejemplo |
|---|---|---|---|
| `--dg01-page` | superficie principal | `#F7FBFC` | body del panel |
| `--dg01-surface` | superficie blanca | `#FFFFFF` | inputs, modal, previews |
| `--dg01-ink` | tinta navy | `#073B5A` | labels, tabs inactivos |
| `--dg01-muted` | texto secundario | `#5F7685` | ayudas |
| `--dg01-primary` | turquesa de sistema | `#00AEBE` | acento base heredado |
| active tab | tab activo | `#01AFB9` | INFORMACIÓN PERSONAL |
| section heading | encabezado general | `#06AEB8` | COLOR DEL PERFIL |
| media heading | encabezado de medios | `#06536E` | FOTOGRAFÍA DE PERFIL |
| body exterior | fondo de aplicación | `#00B0C5` | shell |
| editable text | texto de input | `#123F56` | valor editable |
| input border | definición de control | `#A9C2CE` | inputs/canvas |
| input focus | foco de control | `#008A98` | borde focal |
| focus halo | halo de control | `rgba(0,174,190,.16)` | focus input |
| strong focus | outline accesible | `#123D59` | botones y swatches |
| save green | acción Guardar | `#00C040` | botón primario |
| save hover | hover Guardar | `#00A936` | botón primario |
| save active | active Guardar | `#00972F` | botón primario |
| danger red | acción destructiva | `#D5213B` | Eliminar |
| danger border | contorno destructivo | `#E6384F` | Eliminar |
| secondary ink | acción secundaria | `#008391` | Cambiar |
| soft teal | fondo secundario | `#EFFAFB` | hover/chips |
| panel border | separación externa | `rgba(7,59,90,.10)` | panel principal |
| protected border | control protegido | `#CBDDE4` | valor verificado |
| public badge | estado público | `#057391` sobre `#EDFAFD` | badge Público |
| published status | medio publicado | `#07536E` | franja Publicada |
| pending status | revisión abierta | `#5F4283` | En revisión |
| needs-work status | requiere cambios | `#805300` | estado de revisión |
| verified status | verificado | `#176948` | estado protegido |
| feedback error | observación/error | `#8B3B28` | feedback de medio |
| dirty surface | bandeja con alpha | `rgba(129,226,223,.50)` | guardado pendiente |
| dirty warning | aviso sin guardar | `#C85C5C` | bandeja |
| modal overlay | oscurecimiento | `rgba(0,0,0,.50)` | modal abierto |

**Colores catalogados: 32 entradas de uso.** Esta tabla no incluye los colores seleccionables del catálogo de temas, porque son contenido configurable y no tokens estructurales de la interfaz.

## Contrato de uso futuro

> “When normalizing another physician Admin module, Información Personal is the default visual authority unless the Director explicitly chooses another model.
>
> Future normalization must compare the target module against this baseline and reuse existing shared styles before creating new CSS.”

Antes de crear una nueva regla, se debe identificar la familia equivalente, confirmar el estilo computado final y reutilizar su clase o fuente compartida. Las excepciones de la sección 23 requieren una decisión explícita; no se deben generalizar ni eliminar durante una normalización.

## Checklist reutilizable de comparación

- [ ] typography
- [ ] heading/icon
- [ ] form labels
- [ ] inputs
- [ ] focus
- [ ] buttons
- [ ] chips
- [ ] cards
- [ ] spacing
- [ ] responsive
- [ ] states
- [ ] accessibility

## Registro de auditoría

| Viewport | Estructura revisada | Estados revisados | Resultado |
|---|---|---|---|
| `1440×900` | panel, tabs, identidad/medios, tema, contacto, firma | normal, foco, hover, seleccionado, modal, sucio | PASS; sin overflow |
| `1366×768` | mismas familias y proporciones desktop | mismos estados | PASS; sin overflow |
| `820×1180` | apilado tablet y contacto en columnas | mismos estados | PASS; sin overflow |
| `390×844` | tabs desplazables, stacks, modal y bandeja móvil | mismos estados | PASS; sin overflow |

La inspección cubrió **23 familias**, **12 reglas responsivas**, **8 patrones de interacción** y **12 excepciones aprobadas**. No se ejecutaron escrituras de datos ni se modificó producto para producir este catálogo.
