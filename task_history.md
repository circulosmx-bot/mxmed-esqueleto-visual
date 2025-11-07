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
  Tarea: Versionar SVGs de sección Fotos  
  Descripción: Se añaden al repositorio los SVGs provistos para el icono y el botón de carga de imágenes en la sección Fotos.  
  Archivos/Refs: `assets/icons/carga-de-imagenes.svg`, `assets/icons/boton-carga-de-imagenes.svg`  
  Estado: completado
