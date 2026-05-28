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
