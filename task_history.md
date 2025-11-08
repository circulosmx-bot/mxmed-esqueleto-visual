# Registro de Tareas (Codex)

Formato sugerido por entrada:
- Fecha: YYYY-MM-DD HH:MM (zona local)
- Tarea: nombre lógico y breve
- Descripción: qué se hizo y dónde
- Archivos/Refs: archivos tocados o referencias (líneas, commit)
- Estado: completado/pending

---

- Fecha: 2025-11-06  
  Tarea: Crear registro de tareas  
  Descripción: Se crea este archivo `task_history.md` para documentar las tareas completadas por Codex.  
  Archivos/Refs: `task_history.md`  
  Estado: completado

- Fecha: 2025-11-06  
  Tarea: Verificar acceso a GitHub y estado del repo  
  Descripción: Se comprobó `git` instalado, remotos `origin/target`, rama actual `feature/sidebar-acordeon-paleta-sutil`, y `git fetch` exitoso hacia GitHub.  
  Archivos/Refs: `.git/config`, salida de `git fetch`, HEAD `bcff1bf`  
  Estado: completado

- Fecha: 2025-11-06  
  Tarea: Identificar último cambio reciente  
  Descripción: Se revisaron archivos recientes y diferencias con `index.bak2.html`. Último cambio visible en el bloque de Fotos (CTA como botón primario, limpieza de `</div>` extra) y estilos asociados en `assets/css/style.css`; nuevo módulo `assets/js/fotos.js` referenciado en `index.html`.  
  Archivos/Refs: `index.html:452-456`, `assets/css/style.css:949-959, 977-998`, `assets/js/fotos.js`, HEAD `bcff1bf`  
  Estado: completado

- Fecha: 2025-11-06  
  Tarea: Publicar registro de tareas en GitHub  
  Descripción: Se añade la primera versión de `task_history.md` y se realiza commit y push en la rama actual para que quede disponible en remoto.  
  Archivos/Refs: `task_history.md`, rama `feature/sidebar-acordeon-paleta-sutil`  
  Estado: completado

- Fecha: 2025-11-06  
  Tarea: Definir carpeta para íconos e imágenes  
  Descripción: Se crea la estructura para assets gráficos estáticos: `assets/icons/` (SVGs de íconos UI) y `assets/img/` (imágenes/raster u otros gráficos). Se agregan `.gitkeep` para versionado.  
  Archivos/Refs: `assets/icons/.gitkeep`, `assets/img/.gitkeep`  
  Estado: completado

- Fecha: 2025-11-06  
  Tarea: Ajuste UI del tab de Fotos (layout)  
  Descripción: Se replica el layout proporcionado: borde punteado grueso y redondeado, icono central con flecha de carga, texto guía, botón primario “cargar imágenes” y contador debajo. Se centra vertical/horizontalmente y se afinan tamaños/colores.  
  Archivos/Refs: `index.html:451-460`, `assets/css/style.css` (bloques `.fotos-drop`, `.fotos-ico`, `.fotos-title`, `.fotos-counter`, `.btn-primary`), `assets/js/fotos.js` (sin cambios funcionales).  
  Estado: completado

- Fecha: 2025-11-06  
  Tarea: Usar SVGs provistos en sección Fotos  
  Descripción: Se reemplazan íconos de Material por SVGs del proyecto: `carga-de-imagenes.svg` como icono central y `boton-carga-de-imagenes.svg` como botón gráfico clicable (manteniendo funcionalidad de abrir el file picker). Se fijan tamaños y centrado.  
  Archivos/Refs: `index.html:451-460`, `assets/css/style.css` (bloques `.fotos-ico-img`, `.fotos-btn-img`)  
  Estado: completado

- Fecha: 2025-11-06  
  Tarea: Ajuste fino de tamaños en Fotos  
  Descripción: Reducido botón -25% (210px → 158px), icono central +20% (58px → 70px) y contador duplicado (font-size 2rem).  
  Archivos/Refs: `assets/css/style.css` (`.fotos-btn-img img`, `.fotos-ico-img`, `.fotos-counter`)  
  Estado: completado

- Fecha: 2025-11-06  
  Tarea: Efecto hover en dropzone (icono a 50% alfa)  
  Descripción: Al pasar el mouse o arrastrar (dragover) sobre la caja `.fotos-drop`, solo el icono central reduce su opacidad a ~0.5 con transición suave. Aplica a SVG (`.fotos-ico-img`) y fallback de Material (`.fotos-ico`).  
  Archivos/Refs: `assets/css/style.css` (reglas de hover/dragover y `transition`)  
  Estado: completado

- Fecha: 2025-11-06  
  Tarea: Aumentar límite de fotos a 21  
  Descripción: Se ajusta el límite máximo de imágenes permitidas a 21 y se actualiza el texto del UI.  
  Archivos/Refs: `assets/js/fotos.js` (`MAX = 21`), `index.html:451, 461` ("Hasta 21 imágenes", "0 / 21").  
  Estado: completado

- Fecha: 2025-11-06  
  Tarea: Miniaturas con botón X estilo chips  
  Descripción: La cuadrícula debajo del dropzone muestra miniaturas; el botón de eliminar en cada miniatura replica color, tamaño y forma de las X de chips (20x20, fondo #EF5070, borde redondo).  
  Archivos/Refs: `assets/css/style.css` (`.foto-item`, `.foto-x`)  
  Estado: completado

- Fecha: 2025-11-06  
  Tarea: Alerta de límite al intentar subir >21 fotos  
  Descripción: Se agrega contenedor de mensajes en el drop y lógica JS para mostrar alerta accesible (ARIA `role="alert"`) cuando se supera el máximo. Indica cuántas se pueden agregar y cuántas se omiten. Auto-oculta en ~3.2s.  
  Archivos/Refs: `index.html` (`#fotos-msg`), `assets/css/style.css` (`.fotos-msg`), `assets/js/fotos.js` (`showMsg`, validación en `addFiles`)  
  Estado: completado

- Fecha: 2025-11-06  
  Tarea: Señal visual de límite (borde rojo y contador)  
  Descripción: Cuando hay alerta de límite, la dropzone cambia a borde rojo temporalmente; además el contador adopta estilo destacado al llegar al máximo (`.max`).  
  Archivos/Refs: `assets/css/style.css` (`.fotos-drop.error`, `.fotos-counter.max`), `assets/js/fotos.js` (toggle de clases en `showMsg` y `updateCount`).  
  Estado: completado

- Fecha: 2025-11-06  
  Tarea: Igualar subtítulo en Fotos al de Servicios  
  Descripción: Se cambia el texto y estilo del subtítulo en el tab de Fotos para usar el mismo tamaño de letra que el de “Principales Servicios”, dejando el texto exactamente: “Publica fotografías de tu práctica médica”.  
  Archivos/Refs: `index.html` (usa clase `sp-note` en el subtítulo de Fotos)  
  Estado: completado

- Fecha: 2025-11-06  
  Tarea: Ajuste de copy en subtítulo de Fotos  
  Descripción: Se reemplaza “Publica” por “Agrega” en el subtítulo: “Agrega fotografías de tu práctica médica”.  
  Archivos/Refs: `index.html` (subtítulo de Fotos)  
  Estado: completado

- Fecha: 2025-11-06  
  Tarea: Consultorio — tabs y confirmación para agregar  
  Descripción: En la sección Consultorio se muestran solo 2 tabs: “Consultorio” (activo) y “Agregar Consultorio”. Al hacer clic en “Agregar Consultorio” se muestra una ventana de confirmación: “¿Deseas agregar otro consultorio?” (sí/no).  
  Archivos/Refs: `index.html` (tabs de Consultorio), `assets/js/app.js` (handler `#btn-consul-add`)  
  Estado: completado

- Fecha: 2025-11-06  
  Tarea: Consultorio — modal Bootstrap y iconos en tabs  
  Descripción: Se cambia a modal Bootstrap para confirmar “¿Deseas agregar otro consultorio?” y se agregan iconos en tabs (edificio para Consultorio y “+” grande para Agregar). Tamaños replican los de tabs de Datos Personales.  
  Archivos/Refs: `index.html` (markup de tabs y modal `#modalConsulAdd`), `assets/css/style.css` (`.ico-plus`), `assets/js/app.js` (abrir/cerrar modal).  
  Estado: completado

- Fecha: 2025-11-06  
  Tarea: Consultorio — ajustar tamaño del + y habilitar tab 2  
  Descripción: Se reduce el icono “+” ~15% (3.4rem → 2.9rem). Si el usuario confirma en el modal, se crea/activa dinámicamente el tab “Consultorio 2” clonando el formulario base y limpiando sus campos.  
  Archivos/Refs: `assets/css/style.css` (`.ico-plus`), `assets/js/app.js` (`createSede2IfNeeded`)  
  Estado: completado

- Fecha: 2025-11-06  
  Tarea: CP → Colonias con API SEPOMEX  
  Descripción: Se pide primero el CP; al ingresar 5 dígitos, se consulta la API pública SEPOMEX y se llenan colonias en `<select id="colonia">`, además de municipio y estado. Si no hay resultados, muestra “Código postal no válido” en `#mensaje-cp`. Vanilla JS con comentarios y manejo de errores.  
  Archivos/Refs: `index.html` (inputs `cp`, `colonia`, `municipio`, `estado`, `mensaje-cp`), `assets/js/app.js` (funciones `setupCpAuto` y fetch).  
  Estado: completado

- Fecha: 2025-11-06  
  Tarea: Fix parse SEPOMEX (settlement)  
  Descripción: Se ajusta el parser para usar `data.response.settlement` como fuente principal de colonias y se mantienen alias (colonias/asentamientos). Mejora robustez y logging de depuración.  
  Archivos/Refs: `assets/js/app.js` (`fetchSepomex`)  
  Estado: completado

- Fecha: 2025-11-06  
  Tarea: SEPOMEX con fallback CORS y estado de carga  
  Descripción: Se intenta fetch directo y, si falla, proxy vía AllOrigins; se muestra “Buscando colonias…” mientras carga y se selecciona automáticamente la primera colonia disponible.  
  Archivos/Refs: `assets/js/app.js` (`fetchSepomex`, `fillSelect`, handlers).  
  Estado: completado

- Fecha: 2025-11-06  
  Tarea: Proxy PHP para SEPOMEX (evitar CORS)  
  Descripción: Se agrega `sepomex-proxy.php` para consultar SEPOMEX desde el servidor (WAMP) y evitar errores de CORS/red desde el navegador. JS intenta primero el proxy local, luego directo y por último AllOrigins.  
  Archivos/Refs: `sepomex-proxy.php`, `assets/js/app.js` (orden de intentos).  
  Estado: completado

- Fecha: 2025-11-06  
  Tarea: Robustecer proxy (SSL/HTTP/AllOrigins)  
  Descripción: El proxy ahora desactiva verificación SSL en cURL (entornos Windows), sigue redirecciones, reintenta por HTTP y finalmente vía AllOrigins del lado servidor antes de devolver 502.  
  Archivos/Refs: `sepomex-proxy.php`  
  Estado: completado

- Fecha: 2025-11-06  
  Tarea: Fallback local para CP (demo sin conexión)  
  Descripción: Se agrega `assets/data/sepomex-fallback.json` con ejemplo para 20230; el JS usa este dataset si todos los intentos de red fallan y muestra aviso “Usando datos locales de prueba”.  
  Archivos/Refs: `assets/data/sepomex-fallback.json`, `assets/js/app.js`  
  Estado: completado

- Fecha: 2025-11-06  
  Tarea: Habilitar select de colonias con seguridad  
  Descripción: Se asegura que el `<select id="colonia">` se habilite quitando también el atributo `disabled` y manteniendo “Selecciona…” como primera opción sin autoselect, para que el cambio sea visible.  
  Archivos/Refs: `assets/js/app.js` (`fillSelect`)  
  Estado: completado

- Fecha: 2025-11-06  
  Tarea: Endpoint local de BD (MySQL/SQLite) para SEPOMEX  
  Descripción: Se agrega `sepomex-local.php` que consulta una BD local (configurable en `sepomex-db.config.php`), devuelve `response.settlement` y municipio/estado. El JS intenta primero este endpoint.  
  Archivos/Refs: `sepomex-local.php`, `sepomex-db.config.sample.php`, `assets/js/app.js`  
  Estado: completado

- Fecha: 2025-11-06  
  Tarea: Crear config por defecto y mejorar importador  
  Descripción: Se agrega `sepomex-db.config.php` con valores por defecto (MySQL root sin contraseña, DB `sepomex`) y se mejora `sepomex-import.php` para crear la base si no existe y habilitar `LOAD DATA LOCAL INFILE` (PDO `MYSQL_ATTR_LOCAL_INFILE`).  
  Archivos/Refs: `sepomex-db.config.php`, `sepomex-import.php`  
  Estado: completado

- Fecha: 2025-11-06  
  Tarea: Fix importador SEPOMEX (latin1 y 15 columnas)  
  Descripción: Ajuste de `LOAD DATA` a `CHARACTER SET latin1`, `LINES TERMINATED BY '\r\n'` y omitir columna `c_CP` con variable `@c_cp`. En inserciones, mapeo explícito 15→14 columnas y conversión a UTF‑8 para evitar HY093 y errores de codificación.  
  Archivos/Refs: `sepomex-import.php`  
  Estado: completado

- Fecha: 2025-11-06  
  Tarea: Alineación de campos y etiqueta CP en dos líneas  
  Descripción: “PRIMERO” aparece arriba de “ingresa aquí tu código postal”, sin desalinear los inputs de la fila. Se usa `.cp-label` con `PRIMERO` posicionado de forma absoluta para que las casillas queden alineadas.  
  Archivos/Refs: `index.html` (estructura de label), `assets/css/style.css` (`.cp-label`, `.cp-over`, `.cp-title`).  
  Estado: completado

- Fecha: 2025-11-06  
  Tarea: Versionar SVGs de sección Fotos  
  Descripción: Se añaden al repositorio los SVGs provistos para el icono y el botón de carga de imágenes en la sección Fotos.  
  Archivos/Refs: `assets/icons/carga-de-imagenes.svg`, `assets/icons/boton-carga-de-imagenes.svg`  
  Estado: completado
- Fecha: 2025-11-06  
  Tarea: Actualizar credenciales MySQL para SEPOMEX  
  Descripción: Se configura `sepomex-db.config.php` para usar el usuario `mxmed` con la contraseña provista y la base `sepomex`.  
  Archivos/Refs: `sepomex-db.config.php`  
  Estado: completado
- Fecha: 2025-11-06  
  Tarea: Consultorio — nuevos campos, horarios y foto  
  Descripción: Se añaden campos de título, dirección detallada (calle, número ext/int, piso), teléfonos (3 consultorio, WhatsApp, 2 urgencias), facilidades (estacionamiento, accesibilidad), widget de horarios por día (dos turnos) con copy/clear y previsualización de foto. Se mantiene mapa embebido como fallback y se deja hook para Leaflet si está disponible.  
  Archivos/Refs: `index.html` (bloque adicional en #sede1), `assets/js/app.js` (sched/foto/map), `assets/css/style.css` (estilos de horarios).  
  Estado: completado

- Fecha: 2025-11-06  
  Tarea: Ajuste de columna y longitudes (dirección)  
  Descripción: Se reorganizan los campos en una línea con mayor ancho a “Calle”; “Número Int.” y “Número Ext.” abreviados y con `maxlength` (5 y 6 respectivamente), “Piso” `maxlength` 3.  
  Archivos/Refs: `index.html` (layout y atributos de inputs).  
  Estado: completado

- Fecha: 2025-11-06  
  Tarea: Grupo médico — radios y campo condicional  
  Descripción: Se agrega la pregunta “¿Tu consultorio está en un Grupo Médico?” con radios Sí/No; si elige Sí, se habilita el input “Nombre del grupo médico” y, en la misma línea, queda el campo “Dale un título a este consultorio”.  
  Archivos/Refs: `index.html` (radios y campos), `assets/js/app.js` (toggle habilitado).  
  Estado: completado

- Fecha: 2025-11-06  
  Tarea: Validación de teléfonos y sincronización de WhatsApp  
  Descripción: Validación genérica para teléfonos (7–15 dígitos, admite +()- y espacios) con feedback visual; agrega checkbox “Usar WhatsApp de Datos Generales” que sincroniza por defecto y permite override al desmarcar.  
  Archivos/Refs: `index.html` (data-validate, invalid-feedback, checkbox), `assets/js/app.js` (setupPhoneValidation, setupWhatsAppSync).  
  Estado: completado
