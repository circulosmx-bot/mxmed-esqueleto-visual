# Perfil Publico Medico — Panel Privado de Identidad publica profesional (PP-7G)

## 1) Ubicacion recomendada en panel
- Ruta UX recomendada:
  - Mi Perfil -> Informacion -> Identidad publica profesional.
- Esta seccion debe verse separada de datos administrativos internos para evitar mezcla de:
  - datos visibles publicamente;
  - datos solo internos.

## 2) Objetivo de la seccion
- Permitir administrar datos que alimentan:
  - `profiles_doctors`;
  - endpoint publico (`/api/profiles/public/doctor/{doctor_id}`);
  - vista SSR (`/profiles/doctor.php?doctor_id=...`).
- Flujo obligatorio:
  - Panel privado -> DB canonica -> endpoint publico DTO sanitizado -> vista SSR.
- Prohibido:
  - panel privado -> vista publica directa.
  - localStorage/seed/UI como fuente publica final.

## 3) Campos iniciales de la seccion (fase minima)

| Campo | Columna destino | Edicion medico | Edicion operador/plataforma | Regla |
|---|---|---|---|---|
| Nombre publico | `display_name` | Si | Si | Requerido para publicacion activa |
| Prefijo profesional | `prefix` | Si (catalogo) | Si | No texto libre |
| Genero / tratamiento | `gender` / `gender_label` | Si | Si | No define por si solo el prefijo |
| Cedula profesional | `professional_license` | Captura/solicita | Verifica/aprueba | Requerida para `active` |
| Cedula de especialidad | `specialty_license` | Captura/solicita | Verifica/aprueba | Requerida para mostrarse como especialista |
| Especialidad principal | `specialty_primary` | Si (catalogo transicional) | Si | Requerida para `active` |
| Especialidades secundarias | `specialty_secondary_json` | Si (catalogo transicional) | Si | Sin mezclar con SEO |
| Bio breve | `bio_short` | Si | Si | Longitud controlada |
| Foto | `photo_url` | Fase posterior | Fase posterior | No confundir con foto de consultorio |
| Avatar | `avatar_url` | Fase posterior | Fase posterior | Fallback visual controlado |
| Logo profesional | `logo_url` | Fase posterior | Fase posterior | Separado de logo de consultorio |
| Estado perfil | `profile_status` | No | Si | Estado gobernado por backend/plataforma |
| Candidato publico | `is_public_candidate` | No | Si | Estado gobernado por backend/plataforma |

## 4) Prefijos profesionales (decision)
- El prefijo no debe ser texto libre.
- Catalogo controlado inicial recomendado:
  - `Dr.`, `Dra.`, `Psic.`, `Psicologa`, `Lic.`, `Mtro.`, `Mtra.`, `C.D.`, `Dent.`, `QFB`, `Nut.`, `Fisio.`.
- Regla:
  - el prefijo puede depender de profesion/especialidad/genero, pero no derivarse solo por genero.
- Transicion:
  - PP-7H puede guardar `prefix` como valor controlado en `profiles_doctors`;
  - fase posterior puede mover a catalogo backend (`profiles_prefix_catalog` o equivalente).

## 5) Especialidades (decision transicional)
- Estado actual:
  - catalogo en frontend hardcodeado (no canonico backend).
- Decision transicional:
  - PP-7H puede guardar `specialty_primary` y `specialty_secondary_json` desde catalogo frontend actual (controlado).
- Decision futura:
  - migrar a catalogo backend canonico de especialidades/profesiones.
- Guardrail:
  - no mezclar especialidad medica real con `seo_category`, servicios, procedimientos o padecimientos.

## 6) Cedulas y gobernanza
- `professional_license` es minimo para perfil publico activo.
- `specialty_license` se requiere para mostrarse como especialista.
- El medico puede capturar/solicitar cambios.
- Operador/plataforma debe validar y aprobar el estado publicable.
- En fase minima se puede guardar captura con estado de revision pendiente.

## 7) Foto/avatar/logo (alcance)
- Foto/avatar/logo del medico son dominio de identidad profesional, no de consultorio.
- Si no existe pipeline canonico de upload, mantener:
  - URL controlada o fallback visual.
- No usar localStorage como fuente publica final para media.

## 8) Estado publicable y gobernanza backend
- `profile_status` e `is_public_candidate` no deben ser editables libremente por medico.
- Mostrar como solo lectura informativa en panel (ejemplos):
  - En revision
  - Requiere datos minimos
  - Publicable
  - Oculto
- Decision final de publicacion queda en backend/plataforma.

## 9) UX propuesta (primera iteracion)
- Encabezado informativo:
  - "Estos datos se mostraran en tu perfil publico."
- Grupos de campos:
  - Identidad publica
  - Credenciales profesionales
  - Especialidad y bio
  - Estado del perfil publico (solo lectura)
- Acciones:
  - Boton futuro `Guardar identidad publica`.
  - Enlace futuro `Ver mi perfil publico`.
- Ayuda contextual:
  - "Los cambios de cedulas y especialidad pueden requerir revision de plataforma."

## 10) Implementacion recomendada para PP-7H
- Endpoint privado minimo (propuesta):
  - `GET /api/profiles/private/doctor/{doctor_id}`
  - `PATCH /api/profiles/private/doctor/{doctor_id}`
- Objetivo:
  - leer/guardar identidad profesional en `profiles_doctors`.
- Regla de persistencia:
  - localStorage solo como respaldo UX transicional, no como fuente publica.
- QA requerido:
  - panel privado -> `profiles_doctors` -> endpoint publico -> vista SSR.

## 11) Riesgos a controlar
- prefijo libre sin catalogo;
- cedulas sin revision;
- especialidad solo hardcodeada en frontend;
- duplicidad localStorage/DB como fuentes de verdad;
- exposicion de datos privados;
- activar perfil publico sin minimos;
- conectar panel directo a vista;
- romper gating y regla de perfil gratuito;
- activar por error contacto/agenda/costo/aseguradoras.

## 12) No alcance de PP-7G
- No implementa endpoints privados.
- No modifica backend ni frontend ejecutable.
- No toca Agenda.
- No activa funciones comerciales superiores.
- No redefine slug/canonical SEO final.

## 13) PP-7H1-C — Deuda UX controlada en navegacion (transicion)

### 13.1 Estado actual de navegacion
- El menu lateral conserva accesos de Perfil Medico por compatibilidad.
- El dropdown superior del usuario ya incluye accesos funcionales a:
  - Informacion datos personales (`p-info`)
  - Consultorio datos y contacto (`p-consultorio`)
  - Opiniones supervision comentarios (`p-opiniones`)
  - Seguridad acceso y privacidad (`p-seguridad`)
  - Suscripcion plan contratado (`p-suscripcion`)
  - Ver perfil publico
- Los paneles reales no se movieron.
- El dropdown reutiliza navegacion existente (misma logica/paneles).

### 13.2 Decision temporal
- Mantener temporalmente menu lateral + dropdown superior coexistiendo.
- No eliminar menu lateral hasta cerrar definicion UX final de navegacion.
- No cambiar aun reglas de auto-apertura/cierre de menus.
- No definir aun sustitucion total del lateral por el dropdown.

### 13.3 Pendientes UX (deuda controlada)
- Definir que grupo queda abierto al navegar desde dropdown.
- Definir si el menu lateral se oculta o permanece visible por modulo.
- Definir comportamiento al cambiar entre Perfil, Agenda y Pacientes.
- Definir si se conserva estado de submenu activo entre navegaciones.
- Definir comportamiento responsive/movil.
- Definir si "Mi Perfil" vivira solo en dropdown o en esquema mixto.
- Definir separacion visual final entre:
  - identidad profesional publica;
  - seguridad;
  - suscripcion.

### 13.4 Guardrail para PP-7H2-A
- PP-7H2-A puede avanzar con endpoint privado minimo GET/PATCH para `profiles_doctors`.
- Restriccion obligatoria:
  - no mover paneles;
  - no cambiar navegacion;
  - no modificar dropdown ni menu lateral;
  - no depender de UX final de menus.
- Objetivo tecnico de PP-7H2-A:
  - solo lectura/guardado canonicos de identidad publica profesional.

### 13.5 Dependencia para PP-7H2-B
- La conexion visual del formulario de panel hacia `profiles_doctors` queda para PP-7H2-B.
- Debe ejecutarse cuando:
  - la UX de menus este mejor definida;
  - o se aplique una conexion minima sin reestructurar navegacion.

## 14) PP-7H2-A — Cierre endpoint privado minimo de identidad publica

### 14.1 Estado
- PP-7H2-A implementado.
- Commit:
  - `23de802 feat(profiles): agrega endpoint privado de identidad`.

### 14.2 Archivos de implementacion
- `api/profiles/index.php`
- `modules/profiles/controllers/PrivateProfileController.php`
- `modules/profiles/repositories/PrivateProfileRepository.php`

### 14.3 Rutas privadas activas
- `GET /api/profiles/private/doctor/{doctor_id}`
- `PATCH /api/profiles/private/doctor/{doctor_id}`

### 14.4 Proposito y alcance
- Permitir lectura/guardado privado minimo de identidad publica profesional en `profiles_doctors`.
- Preparar la futura conexion visual del panel, sin conectar formulario en esta fase.
- No modifica navegacion (dropdown/lateral), ni mueve paneles.

### 14.5 Contrato minimo implementado
- `GET` devuelve:
  - `doctor_id`
  - `identity_public.display_name`
  - `identity_public.prefix`
  - `identity_public.gender`
  - `identity_public.gender_label`
  - `identity_public.professional_license`
  - `identity_public.specialty_license`
  - `identity_public.specialty_primary`
  - `identity_public.specialty_secondary[]`
  - `identity_public.bio_short`
  - `identity_public.photo_url`
  - `identity_public.avatar_url`
  - `identity_public.logo_url`
  - `identity_public.profile_status`
  - `identity_public.is_public_candidate`
- `PATCH` editable:
  - `display_name`, `prefix`, `gender`, `gender_label`,
  - `professional_license`, `specialty_license`,
  - `specialty_primary`, `specialty_secondary`,
  - `bio_short`, `photo_url`, `avatar_url`, `logo_url`.
- `PATCH` bloqueado:
  - `profile_status`
  - `is_public_candidate`
- Regla:
  - solo bloqueados -> `invalid_payload`;
  - editable + bloqueado -> aplica editable e informa bloqueado en `meta`.

### 14.6 Seguridad y guardrails
- Allowlist explicita.
- Sin `SELECT *`.
- Sin exposicion de datos clinicos/pacientes/fiscales/tokens/secretos.
- Sin stack traces en respuesta.
- Sanitizacion de strings en escritura.
- URLs de media limitadas a `http(s)` o rutas relativas con `/`.
- `specialty_secondary` persiste como JSON en `specialty_secondary_json`.
- Sin impacto en consultorios, Agenda, Patients ni Clinical.

### 14.7 Auth transicional
- Modo actual: `transitional_open`.
- Endurecimiento disponible por entorno:
  - `MXMED_PROFILES_PRIVATE_AUTH_REQUIRED`.
- En modo estricto puede validar usuario/scope por sesion/headers.
- No sustituye aun un RBAC final de produccion.

### 14.8 QA validado
- `php -l` correcto en API/controller/repository privado y componentes relacionados.
- `GET private doctor_id=1` devuelve identidad publica canonica.
- `PATCH` editable (ej. `bio_short`) refleja cambios en:
  - `GET private`
  - `GET public`
  - vista SSR.
- `bio_short` restaurado al valor seed.
- Bloqueo de campos no editables validado.
- Errores validados:
  - `400 invalid_doctor_id`
  - `404 profile_identity_not_found`
  - `405 method_not_allowed`
- Publico fuera de alcance se mantiene apagado:
  - contacto/telefono/WhatsApp
  - agenda publica
  - costo/aseguradoras
  - reviews
  - claim

### 14.9 Limites y siguiente recomendado
- No conecta aun formulario visual.
- No toca dropdown/lateral/navegacion.
- No toca Agenda.
- No activa motor de planes ni funciones comerciales superiores.
- No resuelve aun catalogo backend final de prefijos/especialidades ni verificacion documental de cedulas.
- Siguiente recomendado:
  - `PP-7H2-B` conexion minima del formulario visual del panel a endpoint privado (`GET/PATCH`), sin redisenar navegacion.

## 15) PP-7H2-C — Cierre congruencia panel ↔ `profiles_doctors` ↔ perfil publico

### 15.1 Problema detectado
- El perfil publico ya mostraba datos provenientes de `profiles_doctors`.
- El panel visible del medico mantenia campos legacy vacios o desconectados de la identidad canonica.
- Esto rompia confianza operativa: el panel debe sentirse como fuente de trabajo del medico.

### 15.2 Segunda falla detectada
- El autosave legacy/localStorage podia guardar cambios visuales locales.
- Esos cambios locales no publicaban datos en `profiles_doctors` por si solos.
- Resultado: si el usuario no presionaba guardado canonico, el perfil publico no cambiaba.

### 15.3 Decision UX/producto
- Fuente persistente:
  - `profiles_doctors`.
- Regla UX:
  - para datos publicos/publicables no depender de autosave legacy.
- Flujo obligatorio:
  - editar panel visible -> estado \"cambios sin guardar\" -> boton Guardar -> `PATCH` privado -> `profiles_doctors` -> endpoint publico -> SSR.
- Mensajeria obligatoria:
  - cambios sin guardar,
  - cambios guardados,
  - error al guardar.

### 15.4 Correcciones aplicadas (commits `14ba4cf`, `8b29b76`, `e7a2009`)
- Alineacion de campos legacy equivalentes con `identity_public` al hidratar.
- Continuidad del bloque \"Identidad publica profesional\" como guardado canonico.
- Regla de nombre transicional en guardado:
  - si `mxpi-display-name` fue editado explicitamente, se usa ese valor;
  - si se editaron campos legacy de nombre y no se edito explicitamente `mxpi-display-name`, se construye `display_name` con prefijo + nombre legacy.
- Agregado de accion visible de guardado en la zona legacy para evitar confundir autosave local con publicacion.
- Guardrails de payload mantenidos: no se envian
  - `profile_status`,
  - `is_public_candidate`,
  - `first_name`,
  - `last_name_1`,
  - `last_name_2`,
  - `dp-nombres`,
  - `dp-apellido-paterno`,
  - `dp-apellido-materno`.

### 15.5 Regla especial sobre nombre
- La construccion de `display_name` desde Nombre(s)/Apellido Paterno/Apellido Materno es transicional.
- No crea columnas canonicas separadas en BD para nombre administrativo.
- No guarda `first_name`/`last_name_1`/`last_name_2`.
- No convierte localStorage en fuente publica.
- Deuda futura:
  - definir modelo canonico separado para `display_name` publico vs nombre administrativo interno.

### 15.6 Estado final
- Congruencia funcional basica cerrada.
- El panel visible ya no debe quedar vacio en campos equivalentes cuando `profiles_doctors` tiene datos.
- Si el usuario edita datos que impactan perfil publico, debe guardar explicitamente.
- Al guardar, el cambio se refleja en:
  - endpoint privado,
  - `profiles_doctors`,
  - endpoint publico,
  - perfil publico SSR.
- Agenda no fue tocada.

### 15.7 Deuda futura
- Uniformar politica de guardado del panel privado:
  - que usa autosave,
  - que requiere boton Guardar,
  - como mostrar \"cambios sin guardar\",
  - como confirmar \"cambios guardados\",
  - como presentar errores.
- Esta deuda se atiende en fase UX separada, fuera de PP-7H2-C.

## 16) UX-Panel-01A — Separacion de Informacion Verificada e Identidad Publica

### 16.1 Problema detectado
- La seccion `Datos Personales` mezcla en una sola vista:
  - datos administrativos/profesionales formales;
  - datos publicos editables;
  - fotografia/logotipo;
  - autosave legacy/localStorage;
  - guardado canonico a `profiles_doctors`;
  - mensajes y botones de guardado.
- Esta mezcla genera:
  - confusion del usuario sobre que se guarda de verdad;
  - sensacion de espacio desperdiciado y altura excesiva;
  - duplicidad visual entre campos legacy y bloque de identidad publica;
  - ambiguedad sobre que cambios actualizan el perfil publico SSR.

### 16.2 Decision de producto
Se separan dos capas de datos en `Datos Personales`:

1) Informacion personal/profesional verificada
- Ejemplos:
  - Nombre(s), Apellido Paterno, Apellido Materno.
  - Cedula Profesional, Cedula de Especialidad.
  - Universidad/Institucion.
  - Formacion profesional validada y datos legales/profesionales sujetos a revision.
- Regla:
  - una vez aprobado el perfil por operadores/plataforma, estos datos no se editan libremente;
  - cualquier ajuste requiere flujo `Solicitar cambio` + revision/aprobacion.

2) Identidad publica profesional editable
- Ejemplos:
  - Nombre publico.
  - Prefijo profesional.
  - Genero/tratamiento publico.
  - Especialidad principal visible.
  - Especialidades secundarias visibles.
  - Bio breve.
  - Foto publica y logotipo publico (cuando aplique).
  - Datos publicos opcionales segun flags/plan (fases posteriores).
- Regla:
  - se edita desde panel con accion explicita de guardado;
  - no depende de autosave legacy;
  - persiste en `profiles_doctors` y se refleja via endpoint publico + SSR.

### 16.3 Politica de guardado
Para Informacion Verificada:
- solo lectura tras aprobacion;
- boton `Solicitar cambio`;
- estado `Pendiente de revision` cuando aplique;
- aprobacion final por operador/plataforma.

Para Identidad Publica:
- editable;
- estado `Cambios sin guardar`;
- boton `Guardar cambios del perfil publico`;
- estado `Cambios guardados`;
- error visible y entendible si falla `PATCH`.

Para autosave/localStorage:
- solo respaldo visual/transicional o preferencia local;
- no equivale a publicacion, aprobacion ni persistencia canonica.

### 16.4 Implicaciones visuales para futura reorganizacion
La reorganizacion de `Datos Personales` debe:
- reducir altura desperdiciada;
- compactar fotografia/logotipo;
- mejorar grid de campos;
- evitar duplicidad entre legacy e identidad publica;
- homogenizar dropdowns/inputs/cards;
- unificar estados visuales de guardado;
- separar con claridad:
  - Informacion verificada,
  - Identidad publica,
  - Vista previa del perfil publico,
  - Acciones de guardado.

### 16.5 Responsividad, movil y futura app/PWA
Esta decision nace como base responsive y portable:

1) Escritorio / PC
- cards en grid;
- campos en 2-3 columnas segun densidad;
- vista previa lateral cuando el ancho lo permita;
- acciones de guardado agrupadas en zona clara.

2) Tablet
- grid reducido (1-2 columnas segun ancho real);
- cards apilables;
- botones tactiles amplios;
- tabs usables sin saturacion visual.

3) Movil
- una sola columna;
- cards apiladas;
- tabs con scroll horizontal si se requiere;
- boton de guardado visible/facil de alcanzar;
- estados `sin guardar/guardado/error` visibles;
- evitar hover y controles pequenos o muy juntos.

4) Futura app descargable / PWA
- mantener patron tactil consistente;
- evitar layouts rigidos dependientes de escritorio;
- usar bloques reutilizables;
- preparar traslado a app sin rediseno total desde cero.

### 16.6 Deuda futura (micro-fases UX)
- `UX-Panel-01B`: reorganizacion estructural visual de Datos Personales.
- `UX-Panel-01C`: modo solo lectura para informacion verificada + Solicitar cambio.
- `UX-Panel-01D`: politica unificada de guardado explicito.
- `UX-Panel-01E`: homologacion visual de inputs, dropdowns, cards, botones y estados.
- `UX-Panel-01F`: revision de foto/logotipo y vista previa publica.
- `UX-Panel-01G`: revision responsive movil/tablet de la seccion.
- `UX-Panel-01H`: criterios de portabilidad futura a PWA/app.

### 16.7 Guardrails de ejecucion
- No reabrir Agenda.
- No mezclar esta fase UX con backend.
- No cambiar contratos de `profiles_doctors` en esta fase.
- No agregar campos SQL en esta fase.
- No implementar flujo de aprobacion en esta fase.
- No implementar app en esta fase.
- No redisenar todo el sistema en una sola intervencion.
- Primero documentar; luego ejecutar rediseno con micro-parches controlados.

## 17) UX-Panel-01B — Auditoria de autosave/localStorage y politica de guardado

### 17.1 Problema detectado
- El panel mezcla guardado canonico y autosave/localStorage en la misma experiencia.
- localStorage puede generar sensacion falsa de "guardado real" cuando no existe persistencia en BD.
- El autosave global `dp:*` no impacta solo Datos Personales; alcanza multiples controles y tabs.

### 17.2 Hallazgo principal
- No es seguro retirar autosave de golpe.
- Primero debe existir clasificacion por tipo de dato y riesgo de regresion.
- Hay usos correctos de localStorage como preferencia UI o borrador local.
- Hay usos que deben migrar a guardado explicito por impacto en perfil publico o datos verificados.

### 17.3 Clasificacion de usos

A) Puede quedarse en localStorage
- pestaña activa y submenu activo;
- estado de sidebar y preferencias visuales;
- filtros temporales;
- cache tecnica acotada;
- borradores explicitamente etiquetados como borrador local.

B) Debe migrarse a guardado explicito
- identidad publica profesional;
- nombre publico y datos que construyen `display_name`;
- cedulas;
- especialidades visibles;
- datos que actualizan perfil publico;
- datos administrativos/profesionales verificados.

C) No retirar todavia
- chips de formacion;
- servicios;
- enfermedades/tratamientos;
- fotos sin pipeline canonico definido;
- modulos de Agenda;
- modulos de Operadores;
- borradores clinicos activos.

D) Requiere decision de producto
- foto/logotipo publico;
- firma;
- contacto publico/privado;
- servicios/padecimientos visibles en perfil publico;
- datos fiscales en fases futuras.

### 17.4 Reglas oficiales de guardado
- Autosave si:
  - preferencias UI;
  - estado temporal;
  - borradores locales marcados con claridad.
- Autosave no:
  - publicacion;
  - aprobacion;
  - datos verificados;
  - identidad publica;
  - datos clinicos finales;
  - datos administrativos canonicos.
- Guardado explicito:
  - datos publicos;
  - datos administrativos importantes;
  - datos verificados;
  - informacion que impacta BD o perfil publico.
- Solicitar cambio:
  - para datos verificados despues de aprobacion.

### 17.5 Reglas de UX / microcopy
- `Cambios sin guardar`
- `Cambios guardados`
- `Guardado local como borrador`
- `Guardado en perfil publico`
- `Error al guardar`

### 17.6 Riesgos
- Retirar autosave global `dp:*` sin segmentacion puede romper varias pestanas.
- Retirar almacenamiento de chips puede perder borradores utiles.
- Retirar fotos locales sin pipeline canonico puede romper continuidad operativa.
- Tocar Agenda/Operadores queda fuera de alcance en esta fase.
- Borrar claves legacy sin reemplazo controlado puede introducir regresiones.

### 17.7 Migracion progresiva recomendada
- `UX-Panel-01B1`: congelar inventario y clasificacion de claves.
- `UX-Panel-01B2`: microcopy visible de `guardado local` vs `guardado canonico`.
- `UX-Panel-01B3`: restringir autosave `dp:*` solo a borradores permitidos.
- `UX-Panel-01B4`: datos publicos solo por boton guardar explicito.
- `UX-Panel-01B5`: informacion verificada en solo lectura + `Solicitar cambio`.
- `UX-Panel-01B6`: definir persistencia canonica para chips/fotos/servicios.
- `UX-Panel-01B7`: retirar claves legacy no usadas.

### 17.8 Guardrails
- No tocar Agenda.
- No tocar Operadores.
- No retirar localStorage de forma masiva.
- No cambiar contratos backend en esta fase.
- No agregar SQL.
- No romper borradores clinicos.
- No hacer rediseno masivo en una sola intervencion.

## 18) UX-Panel-01C2 — Optimizacion de layout y catalogo transicional de credenciales

### 18.1 Problema detectado
- La seccion `Datos Personales` seguia desperdiciando espacio en layout vertical.
- Foto/logotipo mantenian peso visual alto frente a la zona de informacion verificada.
- Los campos de cedula/universidad/especialidad estaban dispersos y con jerarquia inconsistente.
- Persistia confusion conceptual entre:
  - titulo profesional;
  - especialidad verificada;
  - campos publicos editables;
  - clasificaciones publicas.

### 18.2 Decision de producto (separacion conceptual)
- `Titulo profesional`:
  - asociado a cedula profesional base;
  - dato verificable de plataforma (no clasificacion publica).
- `Especialidad verificada`:
  - asociada a cedula de especialidad o credencial formal;
  - dato verificable (no editable libre como marketing).
- `Clasificaciones publicas / areas visibles`:
  - capa de visibilidad/busqueda publica;
  - no sustituye credenciales verificadas.

Ejemplos de referencia:
- Titulos profesionales: `Medico Cirujano`, `Medico General`, `Medico Cirujano y Partero`, `Cirujano Dentista`, `Licenciado en Nutricion`, `Licenciado en Psicologia`, `Licenciado en Fisioterapia`, `Quimico Farmacobiologo`, `Enfermeria`.
- Especialidades verificadas: `Cirugia General`, `Medicina Interna`, `Anestesiologia`, `Ortodoncia`, `Periodoncia`, `Nutricion Clinica`.
- Clasificaciones publicas: `Clinica del dolor`, `Cuidados paliativos`, `Manejo del dolor cronico`, `Brackets`, `Alineadores`, `Control de peso`.

### 18.3 Cambios implementados
- Layout superior optimizado con separacion visual clara entre media y bloque administrativo.
- Columna izquierda (media) en rol auxiliar y columna derecha (verificada) con mayor protagonismo.
- Foto arriba y logotipo abajo en el bloque de media.
- Campo `Nombre(s)` ampliado para mejorar lectura de nombres compuestos.
- Campo `Fecha de nacimiento` incorporado en informacion verificada.
- Sub-seccion `Credenciales verificadas` con filas compactas:
  - `Cedula profesional | Universidad / Institucion | Titulo profesional`
  - `Cedula de especialidad | Universidad / Institucion | Especialidad verificada`
  - `Otra cedula | Universidad / Institucion | Grado / Subespecialidad / Certificacion`

### 18.4 Catalogo frontend transicional
- Se agrego `MXMED_PROFESSIONAL_TAXONOMY` en frontend.
- Alcance:
  - filtrar especialidades verificadas segun titulo profesional;
  - sugerir clasificaciones publicas segun especialidad verificada.
- Restricciones:
  - no es catalogo canonico definitivo;
  - no sustituye validacion oficial;
  - no agrega SQL;
  - no cambia contratos de `profiles_doctors`.

### 18.5 Reglas de filtrado transicional
- `Titulo profesional -> filtra Especialidad verificada`.
- `Especialidad verificada -> sugiere Clasificaciones publicas`.
- Las opciones de especialidad se presentan en orden alfabetico.
- Para `Medico Cirujano`, si no existe valor valido previo, fallback visual a `Cirugia General`.
- Si el valor hidratado/persistido sigue siendo valido para el titulo seleccionado, no se sobrescribe.
- Si el usuario cambia a un titulo incompatible, se evita borrado agresivo de informacion canonica.

### 18.6 Guardrails tecnicos
- `esp-1`, `esp-2`, `esp-3` quedan como controles visuales/transicionales.
- `esp-1`, `esp-2`, `esp-3` no se envian en `PATCH` a `profiles_doctors`.
- `profiles_doctors` se mantiene como fuente persistente de identidad publica.
- No se agrego backend ni SQL para este catalogo.
- No se tocaron Agenda, Operadores ni `profiles/doctor.php`.
- El catalogo canonico futuro debe migrarse a backend/catalogo administrado.

### 18.7 Estado final
- `UX-Panel-01C2` completado.
- Layout de `Datos Personales` mas compacto y legible.
- Informacion verificada mejor estructurada.
- Catalogo transicional operativo en UI con filtrado activo.
- Diferencia entre credencial verificada y clasificacion publica visible ya explicitada.
- Cambios implementados y publicados en:
  - `ff4c311 style(profiles): optimiza layout de datos personales`
  - `f55e774 feat(profiles): agrega catalogo transicional de credenciales`
  - `3135cd5 fix(profiles): ajusta catalogo transicional de credenciales`

### 18.8 Deuda futura
- Definir catalogo canonico backend de titulos, especialidades verificables y clasificaciones publicas.
- Definir flujo de aprobacion para datos verificados (`solo lectura + Solicitar cambio`).
- Determinar mapeo final de `esp-1`, `esp-2`, `esp-3` (columnas, tabla de credenciales o catalogo dedicado).
- Revisar refinamiento visual adicional de bloque foto/logotipo si se prioriza.
- Auditar integracion futura entre especialidades verificadas, SEO/slugs y clasificaciones publicas.

## 19) UX-Panel-01C3-D — Estado transicional de Informacion verificada

### 19.1 Estado actual UX (C3-B / C3-C)
- La card `Informacion verificada / administrativa` ya muestra badge:
  - `Informacion pendiente de validacion`.
- La card ya muestra microcopy transicional:
  - comunica que los datos forman parte de informacion profesional;
  - comunica que en fase posterior podran revisarse por plataforma;
  - comunica que por ahora permanecen editables.
- Ya existe boton visual transicional:
  - `Solicitar cambio`.
- El boton `Solicitar cambio` en estado actual:
  - solo muestra/oculta mensaje informativo placeholder;
  - no envia datos;
  - no dispara `PATCH`;
  - no modifica `localStorage`;
  - no bloquea campos;
  - no crea solicitud real.

### 19.2 Alcance conceptual del boton transicional
- El flujo `Solicitar cambio` aplica a informacion verificada / administrativa profesional, por ejemplo:
  - `Nombre(s)`
  - `Apellido Paterno`
  - `Apellido Materno`
  - `Fecha de nacimiento`
  - `Genero`
  - `Cedula profesional`
  - `Universidad / Institucion` (cedula profesional)
  - `Titulo profesional`
  - `Cedula de especialidad`
  - `Universidad / Institucion` (cedula de especialidad)
  - `Especialidad verificada`
  - `Otra cedula`
  - `Universidad / Institucion` (otra cedula)
  - `Grado / Subespecialidad / Certificacion`

### 19.3 Exclusiones de esta fase
- Esta fase no cubre aun:
  - email;
  - telefono;
  - WhatsApp;
  - telefonos de consultorio;
  - contacto publico;
  - contacto privado;
  - contacto operativo de agenda;
  - visibilidad por plan comercial.
- Nota de roadmap:
  - separar datos de contacto en fase dedicada, sugerida como `UX-Panel-01D`.

### 19.4 Estado tecnico real (transicional)
- Aun no existe backend de solicitudes de cambio.
- Aun no existe tabla/entidad canonica de `change requests`.
- Aun no existe flujo real de aprobacion/rechazo por operador.
- Aun no existe historial/auditoria de cambios verificados.
- Aun no existe bloqueo real por estado de verificacion.
- Aun no existe verificacion por campo.
- `profile_status` e `is_public_candidate` no son suficientes para bloquear campos de forma segura en esta fase.

### 19.5 Deuda futura explicita
- Definir modelo canonico de Informacion verificada.
- Definir persistencia canonica de campos hoy transicionales.
- Definir solicitudes de cambio (modelo y contratos).
- Definir aprobacion/rechazo por plataforma.
- Definir historial de cambios y auditoria.
- Definir permisos de operador para este flujo.
- Separar formalmente Datos de contacto.
- Definir contacto privado, contacto publico y contacto operativo.
- Definir reglas de visibilidad por plan.
- Definir validacion/verificacion de email/telefono/WhatsApp (si aplica por producto).

## 20) UX-Panel-01D2 — Separacion transicional de Datos de contacto

### 20.1 Estado actual UX (D1)
- Ya existe card separada `Datos de contacto` dentro de `Datos Generales`.
- La card contiene actualmente:
  - `Correo Electronico` (`dp-correo`)
  - `Telefono WhatsApp` (`dp-whatsapp`)
- Estos campos ya no se muestran dentro de `Informacion verificada / administrativa`.

### 20.2 Naturaleza transicional de contacto
- `dp-correo` y `dp-whatsapp` se mantienen como campos transicionales.
- Conservan su comportamiento local/autosave heredado (donde aplica).
- Aun no existe modelo canonico definitivo de contacto para esta seccion.
- No impactan directamente el perfil publico en esta fase.
- No entran al `PATCH` de identidad publica profesional.
- No se publican automaticamente por existir en el panel.

### 20.3 Separacion conceptual objetivo (fases futuras)
- `Contacto privado administrativo`:
  - datos de cuenta/soporte interno del medico.
- `Contacto publico visible para pacientes`:
  - datos que podrian mostrarse en perfil publico bajo reglas explicitas.
- `Contacto operativo`:
  - datos usados para agenda, recordatorios u operacion con operadores.
- `Contacto por consultorio`:
  - datos por sede/ubicacion (telefono, WhatsApp, variantes por consultorio).

### 20.4 Riesgos evitados con la separacion visual
- Evitar mezclar datos profesionales verificados con datos de contacto.
- Evitar interpretar `WhatsApp` como dato publico por defecto.
- Evitar publicar email/telefono sin reglas de visibilidad y plan.
- Evitar mezclar contacto de cuenta con contacto de consultorio.
- Evitar mezclar contacto operativo con contacto publico para pacientes.

### 20.5 Estado tecnico y deuda futura
- Esta fase no agrega backend, SQL ni contratos nuevos.
- Esta fase no cambia DTO publico ni SSR publico.
- Esta fase no cambia `PATCH` de identidad publica.
- Pendiente futuro:
  - definir persistencia canonica de contacto;
  - definir flags de visibilidad publica por tipo de contacto;
  - definir separacion formal privado/publico/operativo/consultorio;
  - definir validacion/verificacion de email, telefono y WhatsApp;
  - alinear contacto con Agenda y Operadores sin acoplar fases.

## 21) UX-Panel-01D3-A — Modelo canonico futuro de Datos de contacto

### 21.1 Estado actual (transicional)
- En `Datos Generales` existe card separada `Datos de contacto` con:
  - `dp-correo`
  - `dp-whatsapp`
- Ambos campos siguen en estado transicional:
  - usan autosave/localStorage heredado (`dp:*`);
  - no se guardan en `profiles_doctors`;
  - no entran al `PATCH` de identidad publica;
  - no se publican automaticamente.
- El contacto publico actual depende mas de fuentes de consultorio + flags de visibilidad (`show_contact_buttons`, `show_phone`, `show_whatsapp`) que de `dp-correo`/`dp-whatsapp`.

### 21.2 Regla base de producto
- Todo email, telefono o WhatsApp capturado debe considerarse `privado por defecto`.
- Solo puede volverse publico si existe:
  - permiso/configuracion explicita;
  - flag de visibilidad publica;
  - regla de plan aplicable;
  - y, si producto lo define, verificacion previa.

### 21.3 Capas conceptuales de contacto
1. `Contacto de seguridad de plataforma`
   - acceso, recuperacion de cuenta, validacion, autenticacion futura, avisos criticos.
   - Nunca debe publicarse.
2. `Contacto privado administrativo`
   - comunicacion Mexico Medico ↔ medico, soporte, facturacion/plataforma, relacion comercial.
   - No publico por defecto.
3. `Contacto publico visible para pacientes`
   - perfil publico, botones de contacto, listados y conversion comercial.
   - Requiere flag explicito + plan.
4. `Contacto operativo`
   - agenda, recordatorios, confirmacion de citas, operadores, call center.
   - No implica visibilidad publica automatica.
5. `Contacto por consultorio`
   - telefono/WhatsApp/email por sede.
   - Visibilidad definida por sede + plan + configuracion.

### 21.4 Modelo tecnico futuro sugerido
- Entidad base sugerida: `contact_points`
- Campos sugeridos:
  - `contact_id`
  - `doctor_id`
  - `user_id`
  - `consultorio_id`
  - `type`
  - `value`
  - `normalized_value`
  - `label`
  - `scope`
  - `is_public`
  - `is_primary`
  - `is_verified`
  - `verification_status`
  - `verified_at`
  - `visibility_plan_min`
  - `use_for_login`
  - `use_for_recovery`
  - `use_for_security_alerts`
  - `use_for_platform_admin`
  - `use_for_appointments`
  - `use_for_reminders`
  - `use_for_public_profile`
  - `use_for_internal_ops`
  - `sort_order`
  - `status`
  - `created_at`
  - `updated_at`
- Capas/entidades futuras complementarias:
  - `contact_verifications`
  - `contact_visibility_rules`
  - `contact_usage_policies`
  - `contact_change_requests`
  - `contact_audit_log`

### 21.5 Reglas de publicacion
- Ningun contacto se publica por defecto.
- Publicacion requiere flag explicito.
- La visibilidad puede depender del plan.
- Contacto de seguridad nunca se publica.
- Contacto privado administrativo no se publica salvo configuracion explicita separada.
- Contacto operativo no implica contacto publico.
- Contacto por consultorio se decide por sede.
- WhatsApp privado nunca debe convertirse automaticamente en WhatsApp publico.

### 21.6 Reglas de edicion y verificacion
- El medico puede editar ciertos datos de contacto, pero los de seguridad deben exigir verificacion.
- Cambios en `value` deben invalidar verificacion previa (`is_verified=false`) hasta nueva validacion.
- Operadores solo editan segun permisos explicitos.
- Cualquier cambio con impacto publico debe auditarse.
- Cambios sensibles deben quedar trazados en historial.

### 21.7 Relacion con modulos
- Cuenta / seguridad de plataforma.
- Perfil publico.
- Agenda.
- Recordatorios.
- Operadores.
- Call center.
- Consultorios.
- Planes comerciales.
- Facturacion / plataforma.

### 21.8 Riesgos evitados por el modelo canonico
- Publicar WhatsApp privado por error.
- Mezclar contacto de seguridad con contacto publico.
- Mezclar contacto operativo con contacto visible al paciente.
- Duplicar telefonos entre doctor y consultorio sin gobernanza.
- Usar localStorage como fuente final.
- Romper diferencias de visibilidad entre perfil gratuito/pago.
- Publicar datos sensibles sin consentimiento explicito.
- Perder auditoria de cambios sensibles.

### 21.9 Microfases relacionadas y futuras recomendadas
- `UX-Panel-01D3-B2`: microcopy visual de privacidad/visibilidad en card Datos de contacto (documentado).
- `UX-Panel-01D3-C`: placeholder visual de categorias seguridad/privado/publico/operativo/consultorio (visual, sin backend).
- `UX-Panel-01D3-D`: diseno backend de `contact_points` + verificaciones como siguiente fase natural.
- `UX-Panel-01D3-E`: flags de visibilidad publica por plan.
- `UX-Panel-01D3-F`: integracion canonica con perfil publico y consultorios.
- `UX-Panel-01D3-G`: verificacion email/telefono/WhatsApp.
- `UX-Panel-01D3-H`: auditoria/permisos operador para cambios de contacto.

## 22) UX-Panel-01D3-B2 — Microcopy visual de privacidad/visibilidad

### 22.1 Estado implementado en UI
- La card `Datos de contacto` ya muestra microcopy explicito de privacidad/visibilidad.
- Mensaje operativo aplicado:
  - los datos de contacto son `privados por defecto`;
  - pueden usarse para seguridad, comunicacion administrativa u operacion;
  - no se muestran a pacientes automaticamente;
  - `WhatsApp` no se publica automaticamente.

### 22.2 Alcance tecnico de la microfase
- Esta microfase fue solo de microcopy visual.
- No se agregaron switches ni flags nuevos en la UI.
- No se agrego backend ni SQL.
- No se cambio persistencia.
- No se cambio autosave/localStorage.
- No se modifico `PATCH` de identidad publica.
- No se publico ningun dato nuevo.

### 22.3 Resultado de producto
- Se reduce ambiguedad para el medico:
  - capturar email/telefono/WhatsApp no implica exposicion publica inmediata.
- Se mantiene la ruta futura:
  - visibilidad publica solo con reglas explicitas de flag + plan (+ verificacion si aplica).

## 23) UX-Panel-01D3-C — Placeholder visual de categorias de contacto

### 23.1 Estado implementado en UI
- En `Mi Perfil > Datos Personales > Datos Generales > Datos de contacto` se agrego un bloque visual transicional.
- El bloque se titula: `Uso previsto de estos datos`.
- Se ubica debajo del microcopy de privacidad/visibilidad y antes de los campos:
  - `dp-correo`
  - `dp-whatsapp`
- Su objetivo es preparar visualmente la futura separacion de usos de contacto sin cambiar el comportamiento actual.

### 23.2 Categorias visibles
- El placeholder muestra cinco categorias futuras:
  - `Seguridad`
  - `Privado administrativo`
  - `Publico`
  - `Operativo`
  - `Consultorio`
- La categoria `Publico` incluye una etiqueta pasiva: `No activo`.
- La etiqueta `No activo` no es boton, no es switch y no activa ningun estado.

### 23.3 Alcance tecnico de la microfase
- Esta microfase es solo visual.
- No agrega logica funcional.
- No publica datos.
- No convierte correo ni WhatsApp en publicos.
- No modifica `dp-correo`.
- No modifica `dp-whatsapp`.
- No toca `PATCH`.
- No toca backend.
- No toca endpoints.
- No toca planes.
- No crea reglas reales de visibilidad.
- No crea verificacion real.
- No cambia la privacidad actual.

### 23.4 Regla futura para contacto publico
- En fases futuras, un dato publico requerira:
  - activacion explicita del profesional;
  - regla de plan aplicable;
  - validacion/verificacion si aplica;
  - politica de visibilidad clara.
- La presencia de `dp-correo` o `dp-whatsapp` en el panel privado no implica publicacion ni disponibilidad para pacientes.

### 23.5 Validacion visual registrada
- Desktop: el placeholder renderiza en 5 columnas sin overflow horizontal.
- Tablet grande: el placeholder renderiza en 3 columnas sin overflow horizontal.
- Movil: el placeholder renderiza en 1 columna sin overflow horizontal.
- `dp-correo` sigue visible y unico.
- `dp-whatsapp` sigue visible y unico.
- Los tabs de `Datos Personales` siguen visibles.
- Sidebar, header y shell no fueron afectados por esta microfase.

## 24) UX-Shell-01C — Acceso inferior Mi Perfil en sidebar (transicional)

### 24.1 Estado implementado
- Se agrego un acceso inferior de `Mi Perfil` dentro del sidebar, con composicion visual tipo perfil/header:
  - avatar/icono de usuario;
  - label de perfil en modo expandido;
  - comportamiento compacto en sidebar contraido.
- El acceso inferior reutiliza destinos actuales de perfil (`data-profile-panel`) sin crear rutas nuevas.

### 24.2 Regla de convivencia de navegacion
- El menu legacy de perfil en sidebar sigue oculto (`menu-main d-none[data-group=\"perfil\"]` y `menu-sub d-none[data-group=\"perfil\"]`).
- El dropdown superior del header sigue activo como respaldo transicional.
- No se retiro aun el acceso duplicado del header en esta fase.

### 24.3 Guardrails de la microfase
- No se modifico logica de navegacion.
- No se toco JS de panel/shell.
- No se tocaron backend, SQL, endpoints ni contratos de datos.
- No hubo cambios en PATCH, localStorage ni fuentes canonicas.

### 24.4 Deuda futura recomendada
- Evaluar simplificacion del header superior cuando exista evidencia de uso del acceso inferior.
- Cerrar validacion responsive final (desktop/tablet/movil) del shell con sidebar expandido/contraido.
- Definir si el dropdown superior se retira o se mantiene como respaldo permanente.

## 25) UX-Shell-01D5 — Control de sidebar y comportamiento responsive de hamburguesa

### 25.1 Estado actual
- El boton/proxy de hamburguesa del header ya no es la referencia visual principal del sidebar.
- El control visible de colapso/expansion queda asociado al sidebar en desktop y tablet grande.
- En movil no se muestra hamburguesa porque todavia no existe overlay real de sidebar.

### 25.2 Regla UX vigente
- Desktop/tablet grande: el control de sidebar debe sentirse parte del sidebar, no del header.
- Movil: no mostrar hamburguesa sin funcion visible.
- Si en el futuro se implementa overlay movil, entonces se podra habilitar una hamburguesa movil especifica.

### 25.3 Guardrails
- No reactivar el menu legacy de perfil del sidebar.
- No volver a colocar el control de sidebar como accion principal del header sin justificacion de fase.
- No romper `data-panel` ni `data-profile-panel`.
- No tocar `openGroup`/`showPanel` fuera de una fase dedicada.
- No convertir el header en menu principal mientras el sidebar cumpla esa funcion.

### 25.4 Deuda futura
- Disenar overlay movil real si se requiere navegacion lateral en movil.
- Revisar si el dropdown superior del header se mantiene o se simplifica.
- Validar responsive final del shell completo.

## 26) UX-Shell-01D10 — Consolidación de Mi Perfil en menú inferior

### 26.1 Estado actual
- `Mi Perfil` vive como acceso inferior del sidebar.
- Las opciones internas de `Mi Perfil` se concentran en su dropdown inferior.
- El menu principal ya no debe mostrar botones grandes duplicados de:
  - Informacion
  - Consultorio
  - Opiniones
  - Seguridad
  - Suscripcion
- El dropdown inferior conserva esas opciones y ahora las muestra con iconos.
- El dropdown inferior funciona en sidebar expandido y contraido.
- En modo compacto, el dropdown se abre fuera del riel lateral para evitar recorte.

### 26.2 Regla UX vigente
- Menu principal del sidebar: modulos generales.
- Dropdown inferior de `Mi Perfil`: opciones internas de cuenta/perfil.
- No duplicar opciones internas de `Mi Perfil` en el menu principal.
- No reactivar menu legacy de perfil.
- Mantener el dropdown superior del header como respaldo transicional hasta una fase dedicada.

### 26.3 Guardrails
- No reactivar `menu-main d-none[data-group="perfil"]`.
- No reactivar `menu-sub d-none[data-group="perfil"]`.
- No romper `data-profile-panel`.
- No tocar `openGroup`/`showPanel` fuera de una fase dedicada.
- No usar el menu principal como sustituto de las opciones internas de `Mi Perfil`.
- En sidebar compacto, no permitir que el dropdown inferior quede recortado.

### 26.4 Deuda futura
- Evaluar retiro o simplificacion del dropdown superior del header.
- Disenar overlay movil real si se requiere navegacion lateral en movil.
- Cerrar QA responsive final del shell/sidebar.
- Documentar patron visual final del sidebar si se consolida como estandar del sistema.

## 27) UX-Shell-01E5-D — Layout dashboard del panel principal de perfil

### 27.1 Estado implementado
- El panel principal de perfil adopta una composicion tipo dashboard.
- La card de completitud queda como bloque principal izquierdo.
- La card `Actividad reciente de mi perfil` queda como bloque lateral derecho.
- La seccion `Indicadores clave` queda debajo como bloque de metricas.
- La hilera de indicadores dejo de vivir dentro de la card superior.
- Se elimino duplicidad visual de indicadores.

### 27.2 Regla UX vigente
- Card superior/izquierda: objetivo principal de completitud del perfil.
- Card lateral derecha: actividad reciente.
- Bloque inferior: indicadores clave.
- El resumen debe funcionar como dashboard informativo, no solo como lista de accesos.
- La actividad reciente no debe competir como modulo principal del sidebar.
- Los indicadores deben mantenerse como datos resumidos del estado del perfil/plataforma.

### 27.3 Guardrails
- No duplicar indicadores entre card superior e indicadores clave.
- No reintroducir `Actividad` como modulo principal si la estrategia final es convertirlo en dashboard/card.
- No tocar navegacion inicial sin fase dedicada.
- No cambiar `p-resumen` ni `p-ag-admin` sin decision explicita.
- No conectar metricas a backend sin contrato definido.
- No modificar `data-panel`, `data-profile-panel`, `openGroup`, `showPanel` fuera de fase dedicada.

### 27.4 Deuda futura
- Definir los 4 indicadores finales y su fuente de datos.
- Definir si Agenda sera el panel inicial por defecto.
- Definir si `Actividad reciente` se alimentara de eventos reales, auditoria o mock transicional.
- Documentar contrato futuro de metricas del dashboard.
- Validar responsive final del panel principal.
- Cerrar decision sobre el rol de `p-resumen`.

## 28) UX-Shell-01H2 — Header global: identidad verificada y estado de plan

### 28.1 Estado implementado
- Commit de implementacion: `75c1321 style(shell): ajusta badge y estado de plan en header`.
- QA posterior: `UX-Shell-01H3 — QA PASS sin cambios`.
- El header global gana mayor altura visual y respiracion vertical.
- El logo se mantiene visible, sin recuadro de fondo, y pulsable hacia el Panel principal.
- El bloque del medico queda desplazado hacia la derecha respecto al logo y con mayor protagonismo.
- El nombre visible del medico queda como `Dra. Leticia Muñoz Romo`.
- El badge de verificacion queda a la derecha del nombre.
- El badge es circular, usa fondo `#015684` y check blanco centrado.
- La especialidad queda como `ENDOCRINÓLOGO`, sin el prefijo visual `Perfil Medico /`.
- La especialidad se presenta como etiqueta turquesa con texto blanco.
- El bloque de plan se muestra sin recuadro ni fondo turquesa, integrado sobre el fondo del header.
- `Plan Óptimo` se muestra en color `#015684` y peso bold.
- `Vigencia 17 de Agosto 2027` se muestra en color `#02adc1` y peso normal.
- `+ Incrementar vigencia` se muestra como accion visual secundaria en color `#015684`, sin funcionalidad real.
- En movil se ocultan vigencia y accion para preservar estabilidad responsive.

### 28.2 Regla UX vigente
- El header comunica identidad profesional y estado visual del plan, no administra planes reales.
- La verificacion visual pertenece al nombre del medico y no sustituye un contrato de verificacion formal.
- La especialidad funciona como etiqueta informativa, no como boton ni filtro.
- El bloque de plan es estado visual transicional; no debe parecer selector de planes.
- La accion `+ Incrementar vigencia` es solamente affordance visual transicional.
- No hay selector de planes en esta fase.
- No se reintroducen `Mi Perfil`, campana ni boton flotante `Restablecer` dentro del header.

### 28.3 Alcance tecnico
- Sin backend.
- Sin JS.
- Sin endpoints.
- Sin planes reales todavia.
- Sin flujo de pago.
- Sin modal.
- Sin navegacion nueva.

### 28.4 Validacion
- Desktop validado en `1440x900`, `1680x1000` y `1920x1080`.
- Tablet validado en `1024x768`.
- Movil validado en `390x844`.
- Sin overflow horizontal observado.
- El logo sigue navegando al Panel principal.
- Los modulos principales siguen abriendo: Panel principal, Agenda, Pacientes, Notificaciones y Mi Perfil > Datos Personales.
- Los tabs de Datos Personales siguen visibles:
  - Datos Generales.
  - Formacion Profesional.
  - Principales Servicios.
  - Enfermedades y Tratamientos.
  - Fotos.

### 28.5 Deuda futura
- Definir contrato real de planes, vigencia, renovacion e incrementos.
- Definir si la verificacion visual se conectara a un proceso formal de validacion.
- Definir fuente canonica futura de especialidad visible en header.
- Definir comportamiento real de `Incrementar vigencia` solo cuando exista contrato comercial.

## 29) UX-Shell-02B — Componente maestro de subheader aplicado a Panel principal

### 29.1 Estado implementado
- Commit de implementación: `da76ef4 style(shell): crea subheader maestro para panel principal`.
- Se crea el primer piloto del componente maestro de subheader: `mx-panel-subheader`.
- El piloto se aplica únicamente al subheader de `Panel principal de mi perfil`.
- Se conserva el contenedor externo `.head` y la estructura del panel `#p-resumen`.
- Se conserva el ícono `dashboard`.
- Se conserva el título exacto `Panel principal de mi perfil`.
- No se agregan descripción, acciones ni tabs en esta fase.

### 29.2 Alcance técnico
- Cambios de implementación limitados a `index.html` y `assets/css/style.css`.
- Sin JS.
- Sin backend.
- Sin navegación nueva.
- Sin cambios en Bootstrap behavior.
- Sin modificación de tabs.
- Sin migración de otros subheaders.

### 29.3 Regla UX vigente
- `mx-panel-subheader` queda como primer piloto del componente maestro de subheaders; futuras migraciones deberán realizarse por microfase separada.
- El piloto no cambia el comportamiento del panel; solo normaliza la estructura visual del encabezado.
- Otros subheaders siguen en el patrón legacy `.mm-card > .head > h5`.

### 29.4 Validación
- QA visual aprobado en desktop `1440x900`, desktop `1680x1000`, tablet `1024x768` y móvil `390x844`.
- Sin overflow horizontal.
- El logo sigue navegando a Panel principal.
- Mi Perfil inferior y su dropdown siguen funcionando.
- Otros subheaders permanecen sin cambios visuales.

## 30) UX-Shell-02D — Segundo piloto de subheader: Opiniones

### 30.1 Estado implementado
- Commit de implementación: `fb6fd0d style(shell): migra subheader de opiniones`.
- Se aplica `mx-panel-subheader` como segundo piloto, únicamente al subheader de `Opiniones recibidas en mi perfil`.
- Se conserva el ícono Bootstrap `bi-chat-quote`.
- Se conserva el título exacto `Opiniones recibidas en mi perfil`.
- No se agrega CSS nuevo.
- No se agregan descripción, acciones ni tabs.

### 30.2 Alcance técnico
- Cambio limitado a `index.html`.
- Sin JS.
- Sin backend.
- Sin navegación nueva.
- Sin cambios en Bootstrap behavior.
- Sin modificación de tabs.
- Sin migración de otros subheaders.

### 30.3 Regla UX vigente
- `mx-panel-subheader` ya tiene dos pilotos simples: Panel principal y Opiniones.
- Las futuras migraciones deben seguir siendo por microfase separada.
- Los subheaders con tabs, acciones o comportamiento sensible siguen pendientes de variante específica.

### 30.4 Validación
- QA visual aprobado en desktop `1440x900`, desktop `1680x1000`, tablet `1024x768` y móvil `390x844`.
- El ícono Bootstrap `bi-chat-quote` quedó alineado con el título.
- Sin overflow horizontal.
- Mi Perfil inferior y su dropdown abren Opiniones correctamente.
- Otros subheaders permanecen sin migración accidental.

## 31) UX-Shell-02G — Diseño maestro de tabs modernos asociados a subheaders

### 31.1 Decisión UX
- La estandarización visual del shell no se limita a `mx-panel-subheader`.
- También se define un sistema maestro para tabs asociados a subheaders.
- Agenda se toma como referencia visual por su patrón más moderno, compacto y responsivo.
- Agenda es referencia visual, no dependencia técnica.
- No se deben reutilizar clases `mx-ag-*`, atributos `data-ag-*` ni lógica propia de Agenda.
- La migración de tabs debe realizarse por pilotos separados y sin migración global.

### 31.2 Arquitectura conceptual propuesta
Se mantiene:
- `mx-panel-subheader`
- `mx-panel-subheader--simple`
- `mx-panel-subheader--with-tabs` o `mx-panel-subheader--tabs`

Se agrega conceptualmente:
- `mx-panel-tabs`
- `mx-panel-tabs-list`
- `mx-panel-tabs-item`
- `mx-panel-tabs-link`
- `mx-panel-tabs-icon`
- `mx-panel-tabs-label`
- `mx-panel-tabs--pills`
- `mx-panel-tabs--compact` como variante futura opcional.

### 31.3 Estrategia de compatibilidad aprobada
La estrategia aprobada es una transición tipo Opción C:
- conservar temporalmente `mm-tabs`;
- conservar `mm-tabs-embed`;
- conservar Bootstrap pills;
- conservar `data-bs-toggle`;
- conservar `data-bs-target`;
- conservar `.tab-content`;
- agregar clases `mx-panel-tabs*` de forma progresiva;
- aplicar overrides acotados por panel piloto;
- no modificar reglas globales de `.mm-tabs`, `.mm-tabs-embed` ni `.mm-tabs-rows`.

### 31.4 Diferencia respecto a Agenda
- Agenda usa clases `mx-ag-*`, JS propio y comportamiento operativo específico.
- El nuevo sistema `mx-panel-tabs` no copia esa implementación literal.
- Sólo toma como referencia visual:
  - contenedor claro;
  - borde sutil;
  - botones compactos;
  - estado activo claro;
  - mejor comportamiento responsive.
- El sistema maestro de tabs debe pertenecer al shell general, no al dominio Agenda.

### 31.5 Piloto futuro recomendado
- Siguiente piloto recomendado: `UX-Shell-02H — Piloto de tabs modernos en Paquetes y Promociones`.
- `Paquetes y Promociones` se mantiene como laboratorio porque:
  - tiene 3 tabs;
  - no tiene acciones en el subheader;
  - no pertenece a Agenda, Expediente, Pacientes ni Notificaciones;
  - usa Bootstrap pills y permite validar compatibilidad sin tocar lógica sensible.
- El piloto debe probar:
  - `mx-panel-subheader--with-tabs`;
  - `mx-panel-tabs`;
  - convivencia con Bootstrap;
  - tabs visualmente más cercanos al patrón moderno de Agenda.

### 31.6 Riesgos y guardrails
- No tocar Agenda.
- No reutilizar `mx-ag-*`.
- No tocar JS.
- No tocar Bootstrap behavior.
- No tocar `data-bs-*`.
- No tocar `.tab-content`.
- No afectar Datos Personales, Consultorio, Seguridad, Facturación ni Expediente.
- No hacer migración global.
- No limpiar estilos legacy hasta que todos los pilotos estén validados.

## 32) UX-Shell-02H — Piloto de tabs modernos en Paquetes y Promociones

### 32.1 Estado implementado
- Commit de implementación: `2ae3b9f style(shell): crea piloto de tabs modernos en paquetes`.
- Se implementa el primer piloto de `mx-panel-tabs` en `Paquetes y Promociones`.
- El subheader del panel usa `mx-panel-subheader--with-tabs`.
- El patrón visual se inspira en Agenda, pero sin tocar Agenda ni reutilizar clases `mx-ag-*`.
- El CSS nuevo queda acotado a `#p-paquetes`.

### 32.2 Compatibilidad preservada
- Se conservan `mm-tabs` y `mm-tabs-embed`.
- Se conservan Bootstrap pills.
- Se conservan `data-bs-toggle`, `data-bs-target` y `.tab-content`.
- Se conserva `selectPaqTab('#paq-crear')`.
- No se toca JS, navegación ni Bootstrap behavior.

### 32.3 Alcance
- Cambios de implementación limitados a `index.html` y `assets/css/style.css`.
- Sin cambios en Agenda, Expediente, Pacientes ni Notificaciones.
- Sin cambios en Datos Personales, Consultorio, Seguridad, Facturación ni Suscripción.
- Sin migración de otros tabs o subheaders.

### 32.4 Validación
- QA visual y funcional aprobado en desktop `1440x900`, desktop `1680x1000`, tablet `1024x768` y móvil `390x844`.
- Sin overflow horizontal.
- Tabs validados: `Activos`, `Crear/Editar`, `Historial`.
- `selectPaqTab('#paq-crear')` sigue activando el tab `Crear/Editar`.
- Otros tabs y subheaders permanecen sin cambios accidentales.

## 33) UX-Shell-02J — Segundo piloto de tabs modernos en Seguridad

### 33.1 Estado implementado
- Commit de implementación: `eed4d92 style(shell): aplica tabs modernos en seguridad`.
- Se aplica `mx-panel-tabs` al panel `Seguridad`.
- El subheader del panel usa `mx-panel-subheader--with-tabs`.
- Es el segundo piloto real de `mx-panel-tabs`, después de `Paquetes y Promociones`.
- El CSS nuevo queda acotado a `#p-seguridad`.

### 33.2 Compatibilidad preservada
- Se conservan `mm-tabs` y `mm-tabs-embed`.
- Se conservan Bootstrap pills.
- Se conservan `data-bs-*` y `.tab-content`.
- No se toca JS ni Bootstrap behavior.
- No se toca Agenda, Paquetes ni clases `mx-ag-*`.

### 33.3 Ajuste fino
- Se agrega ajuste acotado a `#p-seguridad .mx-panel-tabs .tab-ico`.
- El ajuste compacta los íconos internos `verified_user` y `lock_person`.
- El objetivo es acercar visualmente Seguridad al patrón moderno validado en Paquetes.

### 33.4 Alcance
- Cambios de implementación limitados a `index.html` y `assets/css/style.css`.
- Sin cambios en Agenda, Expediente, Pacientes ni Notificaciones.
- Sin cambios en Datos Personales, Consultorio, Facturación ni Suscripción.
- Sin migración de otros tabs o subheaders.

### 33.5 Validación
- QA visual y funcional aprobado en desktop `1440x900`, desktop `1680x1000`, tablet `1024x768` y móvil `390x844`.
- Sin overflow horizontal.
- Tabs validados: `SEGURIDAD` y `PRIVACIDAD`.
- Contenido interno de Seguridad y Privacidad permanece estable.
- Otros tabs y subheaders permanecen sin cambios accidentales.

## 34) UX-Shell-02L — Consolidación mínima de base común mx-panel-tabs

### 34.1 Estado implementado
- Commit de implementación: `ca7322b style(shell): consolida base comun de tabs modernos`.
- Se consolida una base común mínima opt-in para `mx-panel-tabs`.
- La base común sólo afecta elementos que ya usan clases `mx-panel-tabs*`.
- Se reduce duplicación CSS entre `#p-paquetes` y `#p-seguridad`.
- No se convierte todavía en migración global de todos los tabs del sistema.

### 34.2 Alcance técnico
- Cambio limitado a `assets/css/style.css`.
- Sin cambios en HTML.
- Sin cambios en JS.
- Sin cambios en navegación.
- Sin cambios en Bootstrap behavior.
- Sin cambios en Agenda ni clases `mx-ag-*`.
- Sin cambios en tabs legacy que todavía no usan `mx-panel-tabs*`.

### 34.3 Compatibilidad preservada
- Se conservan `mm-tabs` y `mm-tabs-embed`.
- Se conservan Bootstrap pills.
- Se conservan `data-bs-*` y `.tab-content`.
- Se conserva `selectPaqTab('#paq-crear')`.
- No se modifican globalmente `mm-tabs`, `mm-tabs-embed`, `mm-tabs-rows`, `nav`, `nav-pills`, `nav-link` ni `tab-ico`.

### 34.4 Overrides acotados
- Se mantiene el ajuste específico `#p-seguridad .mx-panel-tabs .tab-ico` para compactar los íconos internos `verified_user` y `lock_person`.
- Se conserva un override acotado para neutralizar el ancho legacy de `240px` en los pilotos `#p-paquetes` y `#p-seguridad`.
- Los overrides siguen limitados a paneles ya migrados.

### 34.5 Validación
- QA visual y funcional aprobado en `Paquetes y Promociones` y `Seguridad`.
- Validado en desktop `1440x900`, desktop `1680x1000`, tablet `1024x768` y móvil `390x844`.
- Sin overflow horizontal.
- Bootstrap tabs, `data-bs-*`, `.tab-content` y `selectPaqTab('#paq-crear')` siguen funcionando.
- Datos Personales, Consultorio, Facturación, Expediente, Pacientes y Notificaciones no cambiaron.
