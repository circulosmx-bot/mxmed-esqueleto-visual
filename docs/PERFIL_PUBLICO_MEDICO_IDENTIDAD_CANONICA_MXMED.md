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
