# Perfil Publico Medico — Identidad profesional canonica minima (PP-7C)

## 1) Problema actual
- El perfil publico ya puede renderizar consultorio principal y mapa con fuentes reales (`consultorios` + helper de mapa).
- La identidad profesional publica no tiene fuente backend canonica.
- Nombre, prefijo, cedulas, especialidad, bio y foto hoy dependen de seed/localStorage/UI.
- Eso no es valido para perfil publico indexable ni para gobernanza de datos.

## 2) Decision tecnica
- Crear/preparar una fuente canonica minima en dominio `profiles_*`:
  - `profiles_doctors`
- Regla de arquitectura obligatoria:
  - Panel privado -> DB canonica -> endpoint publico DTO sanitizado -> vista SSR.
- Prohibido:
  - Panel privado -> vista publica directa.
  - localStorage/UI como fuente publica de verdad.

## 3) Campos minimos propuestos (`profiles_doctors`)
- `doctor_id` (PK logica, VARCHAR(64))
- `display_name`
- `prefix`
- `gender` o `gender_label`
- `professional_license`
- `specialty_license`
- `specialty_primary`
- `specialty_secondary_json` (opcional)
- `bio_short`
- `photo_url`
- `avatar_url`
- `logo_url`
- `profile_status` (ej. `draft|pending_review|active|hidden|suspended|removed`)
- `is_public_candidate` (boolean)
- `created_at`
- `updated_at`

Opcional futuro recomendado:
- `profiles_doctor_specialties`
- `profiles_doctor_media`
- `profiles_claims`
- `profiles_reviews`
- `profiles_commercial`

## 4) Relacion con fuentes actuales
- `doctor_id`: ya existe transversalmente y se conserva como llave de integracion.
- Consultorios: siguen en `consultorios` (sin duplicar direcciones/mapa en `profiles_doctors`).
- Agenda: se mantiene en `consultorio_schedule` + availability/overrides.
- Endpoint publico: compone DTO con identidad desde `profiles_doctors` y consultorio/mapa desde `consultorios`.

## 5) Regla minima de publicacion
El endpoint solo puede pasar de:
- `profile.status = hidden`
- `profile.is_public = false`

a:
- `profile.status = active`
- `profile.is_public = true`

si existen como minimo:
- `display_name`
- `professional_license`
- `specialty_primary` (o equivalente valido)
- al menos un consultorio publicable/resoluble

Fuera de esta fase (debe seguir apagado):
- contacto publico
- agenda publica
- costo
- aseguradoras
- reviews
- claim real

## 6) Relacion con panel privado
- En fase posterior, el panel privado debe persistir datos de identidad profesional en `profiles_doctors`.
- La vista publica nunca debe leer formulario privado directamente.
- localStorage queda solo para UX local/transicional y nunca como fuente publica canonicamente publicable.
- Seed demo puede existir solo para QA local y con marcacion explicita de no-produccion.

## 7) Impacto esperado en endpoint publico (fase posterior)
`PublicProfileRepository` debera leer primero `profiles_doctors` para:
- `identity.display_name`
- `identity.prefix`
- `identity.photo_url`
- `identity.avatar_url`
- `identity.logo_url`
- `professional.professional_license`
- `professional.specialty_license`
- `professional.bio_short`
- `specialties[]` (derivado de `specialty_primary` o tabla relacionada)

`PublicProfileController` debe:
- mantener comportamiento conservador cuando falten minimos
- mantener allowlist explicita
- no exponer datos privados
- mantener `public_visibility` y `feature_flags` como motor de visibilidad

## 8) Riesgos principales
- publicar perfiles sin cedula verificada
- mezclar seed/localStorage con datos reales
- duplicar identidad en varias tablas sin fuente unica
- exponer datos privados de contacto por error de gating
- romper gating del perfil gratuito
- conectar panel directo a vista sin pasar por DTO
- usar `plan_code` como motor visual

## 9) Plan recomendado para PP-7D
1. Crear schema/migracion minima para `profiles_doctors`.
2. Crear seed demo controlado para `doctor_id=1` (solo QA local).
3. Adaptar `PublicProfileRepository` para resolver identidad/profesional desde `profiles_doctors`.
4. Mantener reglas conservadoras en `PublicProfileController` para publicabilidad.
5. QA endpoint (`200/400/404`, contrato y seguridad).
6. QA vista SSR verificando que consume el DTO nuevo sin romper gating gratuito.

## 10) Criterios de no alcance en PP-7C
- no crear tablas/migraciones
- no cambiar endpoint
- no cambiar vista SSR
- no tocar Agenda
- no activar funciones de plan superior
- no introducir motor de planes en frontend

## 11) Cierre PP-7D y PP-7E (implementacion + cierre documental)

### 11.1 Estado PP-7D
- PP-7D implementado.
- Commit de implementacion:
  - `d095475 feat(profiles): agrega identidad publica canonica`

### 11.2 Archivos creados/modificados en PP-7D
- `modules/profiles/db/profiles_doctors_schema.sql`
- `modules/profiles/db/profiles_doctors_seed_demo.sql`
- `modules/profiles/repositories/PublicProfileRepository.php`
- `modules/profiles/controllers/PublicProfileController.php`

### 11.3 Fuente canonica activa
- Fuente minima activa para identidad profesional publica:
  - `profiles_doctors`
- Alcance de la fuente:
  - identidad profesional (nombre, prefijo, cedulas, especialidad, bio, media publica).
- No duplica ni sustituye:
  - `consultorios` (direccion, mapa, resolucion de consultorio principal);
  - agenda/horarios.

### 11.4 Seed demo controlado (QA local)
- Caso demo:
  - `doctor_id=1`
- Datos:
  - `display_name`: `Dra. Leticia Muñoz Alfaro`
  - `prefix`: `Dra.`
  - `professional_license`: `0123456`
  - `specialty_license`: `6543210`
  - `specialty_primary`: `Medicina Interna`
  - `bio_short`: `Médico especialista con atención profesional y enfoque integral.`
  - `profile_status`: `active`
  - `is_public_candidate`: `1`
- El seed es idempotente (`INSERT ... ON DUPLICATE KEY UPDATE`).
- Uso previsto:
  - QA local/demo; no es fuente editorial final de produccion.

### 11.5 Endpoint publico actualizado
- El endpoint publico por `doctor_id` ahora:
  - lee `profiles_doctors` por `doctor_id`;
  - usa allowlist explicita;
  - no usa `SELECT *`;
  - mapea a DTO:
    - `identity.display_name`
    - `identity.prefix`
    - `professional.professional_license`
    - `professional.specialty_license`
    - `professional.bio_short`
    - `specialties[]` (desde `specialty_primary` + `specialty_secondary_json`).
- Si faltan fuentes, conserva `null` o `[]` segun contrato.

### 11.6 Regla minima de publicacion (vigente tras PP-7D)
Se marca perfil publico activo (`active/is_public=true/has_public_profile=true`) solo si:
- existe `display_name`;
- existe `professional_license`;
- existe `specialty_primary` o equivalente en `specialties[]`;
- existe al menos un consultorio publicable/resoluble;
- `profile_status=active`;
- `is_public_candidate=true`.

Si falta cualquier minimo:
- `profile.status=hidden`;
- `profile.is_public=false`;
- `feature_flags.has_public_profile=false`;
- `seo.robots=noindex,nofollow`.

### 11.7 QA validado en PP-7D
- `php -l` valido en repository, controller, API profiles y vista SSR.
- `doctor_id=1` devuelve identidad profesional canonica minima real:
  - nombre, prefijo, cedulas, especialidad primaria, bio.
- `d_demo_01` permanece conservador:
  - `hidden/is_public=false/has_public_profile=false`.
- Errores esperados:
  - `404 profile_not_found`;
  - `400 invalid_doctor_id`.
- Vista SSR (`/profiles/doctor.php?doctor_id=1`) ya refleja identidad profesional minima.
- No se exponen datos sensibles ni se activan funciones fuera de alcance.

### 11.8 Limites vigentes tras PP-7D
- No hay guardado desde panel privado real todavia.
- No hay formulario de edicion de identidad publica.
- No se activa:
  - contacto;
  - agenda publica;
  - costo;
  - aseguradoras;
  - reviews reales;
  - claim real.
- No se introduce motor de planes en frontend.
- No se toca Agenda.

### 11.9 Siguiente recomendado (PP-7F)
- Recomendacion preferida:
  - `PP-7F` diagnostico del panel privado para definir la seccion exacta que editara identidad publica canonica en `profiles_doctors`.
- Implementacion de guardado desde panel privado debe iniciar solo despues de ese diagnostico.

## 12) PP-7F/PP-7G — Diagnostico de panel y diseno UX/contrato de identidad publica

### 12.1 Resultado PP-7F (diagnostico)
- Se confirmo que el panel privado ya tiene UI de identidad profesional dentro de Mi Perfil, pero con persistencia principal en localStorage/seed (`dp:*`) y catalogos frontend.
- No existe aun endpoint privado canonico para guardar identidad publica profesional en `profiles_doctors`.
- Se confirmo la regla de arquitectura:
  - panel privado -> DB canonica -> endpoint publico -> vista SSR.

### 12.2 Resultado PP-7G (diseno documental)
- Se definio la seccion de panel:
  - Mi Perfil -> Informacion -> Identidad publica profesional.
- Se documentaron campos iniciales, gobernanza por campo, catalogo controlado de prefijos y separacion entre:
  - especialidad medica real;
  - taxonomia SEO/servicios/padecimientos.
- Se definio que `profile_status` e `is_public_candidate` son estados gobernados por backend/plataforma y no por edicion libre del medico.

### 12.3 Documento de referencia PP-7G
- `docs/PERFIL_PUBLICO_MEDICO_PANEL_IDENTIDAD_PUBLICA_MXMED.md`

### 12.4 Siguiente recomendado (PP-7H)
- Implementar endpoint privado minimo de identidad publica (`GET/PATCH`) y conexion del formulario de panel hacia `profiles_doctors`.
- Mantener localStorage solo como respaldo UX transicional (no como fuente publica).
- QA de extremo a extremo:
  - panel privado -> `profiles_doctors` -> endpoint publico DTO -> vista SSR.
