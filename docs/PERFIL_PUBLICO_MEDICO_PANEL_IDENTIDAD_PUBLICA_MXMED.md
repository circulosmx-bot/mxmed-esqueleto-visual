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
