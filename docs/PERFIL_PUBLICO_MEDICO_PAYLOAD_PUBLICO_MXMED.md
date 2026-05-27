# Perfil Publico Medico — Contrato tecnico de payload publico MVP

## 1) Objetivo
Definir el contrato tecnico canonico de salida publica (read-only) para el Perfil Publico Medico MVP, alineado a `docs/PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md` y al estado actual del repo.

Este documento no implementa endpoint ni UI. Solo define estructura, reglas de sanitizacion, gating y decisiones tecnicas pendientes.

## 2) Endpoint objetivo (propuesto, no implementado)
Opcion objetivo:
- `GET /api/profiles/public/{slug}`

Opcion transicional:
- `GET /api/profiles/public/doctor/{doctor_id}`

Reglas del endpoint:
- Publico read-only.
- Sin sesion obligatoria.
- Solo datos sanitizados.
- Sin datos privados internos.
- Sin datos clinicos.
- Sin datos de pacientes.
- Con gating por plan y estado del perfil.

## 3) Forma de respuesta propuesta
```json
{
  "ok": true,
  "data": {
    "profile": {},
    "plan": {},
    "public_visibility": {},
    "identity": {},
    "professional": {},
    "specialties": [],
    "consultorios": [],
    "schedule": {},
    "contact": {},
    "agenda_public": {},
    "reviews": {},
    "claim": {},
    "seo": {},
    "json_ld": {},
    "feature_flags": {}
  },
  "meta": {}
}
```

## 4) Contrato por bloque

### 4.1 `profile`
Campos propuestos:
- `profile_id`
- `doctor_id` (solo si se decide publico; ver pendientes)
- `slug`
- `canonical_url`
- `profile_type`
- `status` (ej. active, suspended, hidden)
- `ownership_status` (ej. unclaimed, claimed, pending_review)
- `is_claimed`
- `is_public`
- `created_origin` (seed/manual/import/claim)
- `last_public_update_at`

### 4.2 `plan`
Campos propuestos:
- `plan_code`
- `plan_label`
- `is_paid`
- `is_active`
- `expires_at`
- `grace_status`
- `features` (objeto resumido de capacidades publicas)

Restriccion:
- Nunca exponer montos, facturas, pagos, referencias financieras ni datos fiscales.

### 4.3 `public_visibility`
Resumen tecnico de visibilidad efectiva:
- `show_contact_buttons`
- `show_phone`
- `show_whatsapp`
- `show_internal_message`
- `show_public_agenda`
- `show_map_gps`
- `show_reviews`
- `show_promotions`
- `show_claim_button`
- `show_video_consultation`
- `show_ai_claims`

### 4.4 `identity`
Campos publicos:
- `display_name`
- `prefix`
- `gender_label` (si aplica)
- `photo_url`
- `avatar_url`
- `logo_url`

No incluir:
- email privado
- username interno
- telefonos privados no publicables
- tokens o secretos

### 4.5 `professional`
Campos propuestos:
- `professional_license`
- `specialty_license`
- `bio_short`
- `bio_long`
- `education`
- `certifications`
- `professional_associations`
- `languages`
- `years_experience` (si existe)
- `services`
- `conditions_treated`

MVP recomendado:
- `professional_license`, `specialty_license`, `bio_short`, `services`, `conditions_treated`.

Futuro:
- `bio_long`, asociaciones, certificaciones avanzadas.

### 4.6 `specialties` (array)
Cada item:
- `specialty_id`
- `name_es`
- `name_plural_es`
- `slug`
- `schema_medical_specialty`
- `is_primary`

### 4.7 `consultorios` (array)
Cada consultorio publico:
- `consultorio_id`
- `public_name`
- `address`
- `city`
- `state`
- `municipality`
- `postal_code`
- `phone_public`
- `whatsapp_public`
- `lat`
- `lng`
- `map_embed_url`
- `map_can_open_gps`
- `is_public`
- `is_active`
- `photos`
- `schedule_summary`
- `modalities`

Reglas:
- Excluir consultorios privados/inactivos.
- Excluir metadatos internos de geocode.
- Usar coordenadas confirmadas cuando existan.

### 4.8 `schedule`
Campos propuestos:
- `timezone`
- `source` (`schedule`, `availability`, `mixed`)
- `by_day` (resumen semanal)
- `by_consultorio` (ventanas por sede)
- `public_notes` (opcional)

Nota:
- Definir en PP-3 si se expone horario general o disponibilidad real por slot.

### 4.9 `contact`
Campos:
- `phone`
- `whatsapp`
- `internal_message_enabled`
- `contact_cta_label`
- `contact_restriction_reason`

Regla:
- Si el plan no permite contacto, devolver flags y razon, no datos privados.

### 4.10 `agenda_public`
Campos:
- `enabled`
- `availability_endpoint`
- `booking_flow`
- `requires_otp`
- `allowed_consultorios`
- `allowed_modalities`
- `blocked_by_plan_reason`

Reglas:
- Solo habilitada cuando plan y estado lo permitan.
- Debe usar disponibilidad publica vigente.
- Debe respetar bloqueos canonicamente (BLOQ-F2/BLOQ-F3).
- No representa encounter clinico.

### 4.11 `reviews`
Campos:
- `enabled`
- `visible`
- `rating_avg`
- `review_count`
- `reviews_preview`
- `doctor_can_reply`
- `doctor_can_archive`

Nota MVP:
- Puede devolverse apagado hasta contar con backend consolidado de reviews.

### 4.12 `claim`
Campos:
- `show_claim_button`
- `claim_url`
- `claim_status`
- `claim_allowed`
- `claim_blocked_reason`

Reglas:
- Solo perfil gratuito sin propietario titular.
- No aplica a perfil pagado o ya reclamado.
- Sujeto a verificacion humana.

### 4.13 `seo`
Campos:
- `title`
- `description`
- `h1`
- `canonical_url`
- `robots`
- `og_title`
- `og_description`
- `og_image`
- `breadcrumb`

### 4.14 `json_ld`
Puede ser:
- objeto JSON-LD listo para render, o
- `null` si faltan datos criticos.

Campos minimos esperados:
- `@context`
- `@type`
- `name`
- `url`
- `telephone` (si publicable)
- `identifier`
- `address`
- `geo`
- `openingHoursSpecification`
- `medicalSpecialty`
- `aggregateRating` (si aplica)

### 4.15 `feature_flags`
Campos:
- `has_public_profile`
- `has_public_contact`
- `has_public_agenda`
- `has_reviews`
- `has_promotions`
- `has_video_consultation`
- `has_ai_agent`
- `has_ai_profile_writer`
- `has_ai_prescription_safety`

## 5) Gating tecnico por plan (MVP de reglas)

### 5.1 Gratuito
- Identidad base visible.
- Sin botones de contacto directo.
- Sin agenda publica.
- Mapa visible sin GPS activo (si se decide asi).
- Boton de reclamo solo si no reclamado.

### 5.2 Basico
- Contacto visible segun configuracion.
- Horarios visibles.
- Mapa con GPS si aplica.
- Sin agenda publica (segun regla vigente de contrato).

### 5.3 Estandar
- Agenda publica habilitable.
- Promociones/reviews segun modulo y regla comercial.

### 5.4 Optimo
- Comunicacion avanzada.
- WhatsApp segun consentimiento y configuracion.
- Capacidades internas ampliadas (no publicar datos clinicos).

### 5.5 Profesional
- Capas avanzadas (agente IA opcional, automatizaciones).
- Sin exponer logica clinica privada en payload publico.

## 6) Campos prohibidos (never expose)
El endpoint publico nunca debe devolver:
- `patient_id` o datos de pacientes
- motivos de consulta
- expediente clinico
- diagnosticos/tratamientos/recetas
- documentos clinicos
- flags de riesgo
- bitacoras internas
- datos fiscales
- pagos/facturas
- emails privados
- tokens/API keys
- notas internas
- configuracion de operadores
- IDs internos sensibles sin necesidad publica

## 7) Fuentes actuales probables (mapeo tecnico)
- `consultorios`: `GET /api/agenda/index.php/consultorios`
- `schedule`: `GET /api/agenda/index.php/schedule`
- `agenda_public`: `GET /api/agenda/index.php/public/availability` + flujo publico de cita existente
- `availability_blocks`: uso indirecto via calculo de availability publica
- `identity` medico: fuente canonica pendiente
- `plan/gating`: fuente canonica pendiente
- `reviews`: pendiente
- `slug`: pendiente
- `claim`: pendiente
- `json_ld`: derivado nuevo en capa de presentacion/perfil

## 8) Reglas de sanitizacion y seguridad
- Build de respuesta desde DTO publico explicito, no `SELECT *`.
- Lista allowlist por campo y por plan.
- Nunca exponer campos privados por default.
- Resolver visibilidad por plan + estado perfil + configuracion de consentimiento.
- Incluir `meta` con version de contrato y trazabilidad no sensible.

## 9) Decisiones pendientes
1. Ruta final del endpoint (`slug` vs `doctor_id` transicional).
2. Fuente canonica de identidad profesional.
3. Estrategia de `slug` unico/canonico.
4. Modelo de ownership/reclamo y estados publicos.
5. Dominio final `profiles_*`.
6. Resolucion tecnica del plan actual.
7. Catalogo canonico de especialidades/subespecialidades.
8. Validacion de cedulas y politica de publicacion.
9. Moderacion de foto/logo.
10. Politica default de datos publicos por consultorio.
11. Momento de activacion de reviews.
12. Momento de activacion de claim profile.
13. Estrategia JSON-LD cuando faltan datos.
14. Exponer o no `doctor_id` en respuesta publica.

## 10) No objetivos de PP-2B
- No crea endpoint.
- No crea vista publica.
- No cambia backend.
- No cambia Agenda.
- No cambia contratos clinicos.

## 11) Siguiente paso recomendado
PP-3 — Definir contrato de endpoint read-only ejecutable:
- request/response final versionado;
- matriz de errores (`404`, `410`, `423`, etc.);
- criterios de gating en backend;
- estrategia de slug/canonical.
