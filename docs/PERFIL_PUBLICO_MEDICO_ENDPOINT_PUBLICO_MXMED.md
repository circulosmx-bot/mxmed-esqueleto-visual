# Perfil Publico Medico — Contrato de endpoint publico read-only

## 1) Objetivo
Definir el contrato tecnico ejecutable del endpoint publico de Perfil Publico Medico, tomando como base:
- `docs/PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md` (PP-1)
- `docs/PERFIL_PUBLICO_MEDICO_PAYLOAD_PUBLICO_MXMED.md` (PP-2B)

Este documento no implementa endpoint ni UI.

## 2) Rutas objetivo (propuestas, no implementadas)
Ruta canonica futura:
- `GET /api/profiles/public/{slug}`

Ruta transicional opcional:
- `GET /api/profiles/public/doctor/{doctor_id}`

Reglas:
- `{slug}` debe ser la ruta final publica.
- `{doctor_id}` solo para transicion interna/QA.
- Ambas rutas deben responder el mismo contrato publico sanitizado.
- El perfil publico final no debe depender de IDs internos visibles cuando exista `slug`.

## 3) Request contract

### 3.1 Path params
- `slug` (canonico)
- `doctor_id` (transicional)

### 3.2 Query params (opcionales)
- `include=availability`
- `include=json_ld`
- `include=reviews_preview`
- `include=commercial_visibility`
- `include=accepted_insurances`
- `preview=1` (solo futuro/admin; no publico general)
- `lang=es-MX`

Nota:
- `include` puede aceptarse como lista separada por comas o repetido; decision final en implementacion.

### 3.3 Headers
- `Accept: application/json`

### 3.4 Auth
- Endpoint publico de lectura: no requiere sesion.

## 4) Respuesta exitosa (HTTP 200)
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
    "commercial_visibility": {},
    "reviews": {},
    "claim": {},
    "seo": {},
    "json_ld": {},
    "ecosystem_links": {},
    "feature_flags": {}
  },
  "meta": {
    "contract": "profile_public_mvp",
    "version": "PP-3",
    "generated_at": "YYYY-MM-DDTHH:mm:ssZ"
  }
}
```

Reglas:
- Payload read-only.
- Payload sanitizado.
- Sin datos clinicos ni privados.

## 5) Matriz de errores y estados HTTP

### 5.1 Wrapper de error canonico
```json
{
  "ok": false,
  "error": "profile_not_found",
  "message": "Perfil publico no encontrado.",
  "data": null,
  "meta": {}
}
```

### 5.2 Errores definidos
- `400 invalid_slug`
  - Slug con formato invalido.
- `403 profile_not_available_by_plan`
  - Perfil existe, pero plan/estado no permite visibilidad publica en ese contexto.
- `404 profile_not_found`
  - No existe perfil resoluble por slug/id.
- `410 profile_removed`
  - Perfil retirado permanentemente (si politica SEO aplica).
- `423 profile_not_public`
  - Perfil existe pero no esta habilitado publicamente (hidden/suspended/pending_review).
- `500 profile_public_unavailable`
  - Error interno no detallable.

Regla de seguridad:
- Los mensajes no deben filtrar existencia historica sensible de IDs internos.

## 6) Estados de perfil y ownership

### 6.1 Estados de perfil
- `active`
- `hidden`
- `suspended`
- `pending_review`
- `removed`

### 6.2 Ownership
- `unclaimed`
- `claim_pending`
- `claimed`

### 6.3 Reglas operativas
- Perfil gratuito no reclamado puede ser publico en modo directorio.
- Si es `unclaimed`, puede mostrar `claim.show_claim_button=true`.
- Si es `claimed`, el boton de reclamo no debe mostrarse.
- `suspended` no debe exponer informacion sensible.
- `removed` puede responder `410` para manejo SEO controlado.

## 7) Gating por plan (salida publica)

### 7.1 Gratuito
- Identidad base.
- Cedulas.
- Domicilio/mapa (sin GPS activo si asi se define).
- Sin botones de contacto directo.
- Sin agenda publica.
- Boton de reclamo si aplica.

### 7.2 Basico
- Contacto visible si configurado.
- Horarios visibles.
- Mapa interactivo/GPS si aplica.
- Sin agenda publica.

### 7.3 Estandar
- Agenda publica habilitable.
- Contacto visible.
- Reviews/promociones segun modulo y regla comercial.

### 7.4 Optimo
- Comunicacion avanzada.
- WhatsApp segun consentimiento.
- Funciones internas ampliadas (no publicar datos clinicos).

### 7.5 Profesional
- Capacidades avanzadas (p.ej. agente IA opcional) solo como claim publico controlado.
- Nunca exponer logica clinica privada ni decisiones internas.

## 8) Sanitizacion y allowlist
Reglas obligatorias:
- Construir respuesta mediante DTO allowlist.
- No usar `SELECT *` para salida publica.
- Nunca exponer:
  - datos de pacientes;
  - datos clinicos;
  - pagos/facturas;
  - tokens/keys;
  - notas internas;
  - configuracion de operadores.
- `phone`/`whatsapp` solo si `public_visibility` lo permite.
- `doctor_id` solo si se aprueba explicitamente para publico.

## 9) SEO y robots
Reglas propuestas:
- `seo.robots = "index,follow"` solo en perfil publico activo y completo.
- `seo.robots = "noindex,nofollow"` para:
  - hidden;
  - suspended;
  - pending_review;
  - preview;
  - payload insuficiente.
- `canonical_url` obligatorio cuando exista `slug`.
- `json_ld` puede ser `null` si faltan datos criticos.

## 10) Cache y rendimiento
Propuesta tecnica:
- Permitir cache publico de corta duracion.
- Invalidar cache cuando cambie:
  - perfil;
  - consultorios;
  - horarios;
  - plan/gating;
  - reviews;
  - agenda publica;
  - estado de reclamo.
- No cachear datos privados.
- Incluir `meta.generated_at`.

## 11) Integracion con Agenda publica
Reglas:
- `agenda_public.enabled` depende de plan + configuracion.
- `availability_endpoint` puede usar:
  - `/api/agenda/index.php/public/availability`
- Debe respetar:
  - horarios reales;
  - bloqueos backend;
  - consultorios activos;
  - reglas OTP/confirmacion vigentes.
- Agendar desde perfil publico no inicia encounter clinico.

## 12) Integracion con Reclamo de perfil
Reglas:
- `claim.show_claim_button` depende de:
  - plan gratuito;
  - ownership `unclaimed`;
  - perfil visible/publico;
  - no suspendido;
  - no removed.
- `claim_url` queda definida en fase futura.
- El flujo completo de reclamo queda fuera del MVP de endpoint.

## 13) Ejemplos abreviados

### 13.1 Perfil gratuito no reclamado
```json
{
  "ok": true,
  "data": {
    "profile": {"status": "active", "ownership_status": "unclaimed"},
    "public_visibility": {
      "show_contact_buttons": false,
      "show_public_agenda": false,
      "show_claim_button": true
    },
    "contact": {"phone": null, "whatsapp": null},
    "claim": {"show_claim_button": true, "claim_allowed": true}
  },
  "meta": {"contract": "profile_public_mvp", "version": "PP-3"}
}
```

### 13.2 Perfil basico reclamado
```json
{
  "ok": true,
  "data": {
    "profile": {"status": "active", "ownership_status": "claimed"},
    "public_visibility": {
      "show_contact_buttons": true,
      "show_public_agenda": false,
      "show_claim_button": false
    },
    "contact": {"phone": "+52...", "whatsapp": "+52..."},
    "claim": {"show_claim_button": false, "claim_allowed": false}
  },
  "meta": {"contract": "profile_public_mvp", "version": "PP-3"}
}
```

### 13.3 Perfil estandar con agenda publica
```json
{
  "ok": true,
  "data": {
    "profile": {"status": "active", "ownership_status": "claimed"},
    "public_visibility": {
      "show_contact_buttons": true,
      "show_public_agenda": true
    },
    "agenda_public": {
      "enabled": true,
      "availability_endpoint": "/api/agenda/index.php/public/availability",
      "requires_otp": true
    },
    "json_ld": {"@context": "https://schema.org", "@type": "Physician"}
  },
  "meta": {"contract": "profile_public_mvp", "version": "PP-3"}
}
```

## 14) No objetivos de PP-3
- No implementar endpoint.
- No implementar vista publica.
- No crear tablas ni migraciones.
- No implementar slug definitivo.
- No implementar claim completo.
- No implementar reviews backend.
- No implementar gating real.
- Solo formalizar contrato ejecutable de endpoint.

## 15) Proximo paso recomendado
PP-4 — Diagnostico de fuentes reales para DTO ejecutable y endpoint read-only MVP.

Razon:
- El repo ya tiene piezas reutilizables (consultorios/schedule/public availability),
- pero aun faltan fuentes canonicas cerradas para identidad profesional, plan/gating, reviews, claim y slug.

## 16) Adenda PP-Decisiones 01 — Ajustes operativos del contrato

### 16.1 Render y SEO tecnico
- La pagina publica del perfil se define SSR PHP para contenido principal indexable.
- Este endpoint read-only es fuente de datos sanitizada para SSR + JS progresivo.
- JS solo para interacciones dinamicas (agenda, OTP, reserva, metricas, mapa, opiniones).

### 16.2 Regla de URL publica
- URL publica final del perfil:
  - `/{seo_category}/{ciudad}/{slug-medico}`
- Variante de desambiguacion:
  - `/{seo_category}/{estado}/{ciudad}/{slug-medico}`
- Ruta API puede seguir recibiendo slug canonicamente en PP-3; la capa web resuelve categoria/ciudad y canonical.
- `doctor_id` permanece como ruta transicional de QA/desarrollo.

### 16.3 Slug e historial
- Mantener historial de slugs para redireccion 301.
- Cambio de ciudad puede implicar cambio de URL.

## 17) Adenda PP-7E — Cierre documental de identidad canonica publica

### 17.1 Estado
- PP-7D implementado y validado:
  - `d095475 feat(profiles): agrega identidad publica canonica`
- PP-7E documenta el cierre tecnico de esa implementacion.

### 17.2 Fuente canonica en uso por endpoint
- Fuente minima de identidad profesional publica:
  - `profiles_doctors`
- El endpoint transicional (`GET /api/profiles/public/doctor/{doctor_id}`):
  - lee `profiles_doctors` por `doctor_id`;
  - aplica allowlist explicita;
  - no usa `SELECT *`;
  - compone DTO con `consultorios` desde su fuente vigente (sin duplicacion de direccion/mapa).

### 17.3 Mapeo efectivo en DTO (tras PP-7D)
- `identity.display_name`
- `identity.prefix`
- `identity.photo_url`
- `identity.avatar_url`
- `identity.logo_url`
- `professional.professional_license`
- `professional.specialty_license`
- `professional.bio_short`
- `specialties[]` (desde `specialty_primary` + `specialty_secondary_json`)

### 17.4 Regla minima de publicacion activa
El endpoint marca:
- `profile.status=active`
- `profile.is_public=true`
- `feature_flags.has_public_profile=true`

solo si existen:
- `display_name`;
- `professional_license`;
- `specialty_primary` o equivalente;
- al menos un consultorio publicable/resoluble;
- `profile_status=active`;
- `is_public_candidate=true`.

En caso contrario, conserva:
- `profile.status=hidden`;
- `profile.is_public=false`;
- `feature_flags.has_public_profile=false`;
- `seo.robots=noindex,nofollow`.

### 17.5 Seed demo controlado (QA local)
- `doctor_id=1` con identidad profesional minima:
  - `Dra. Leticia Muñoz Alfaro`
  - `Dra.`
  - `0123456`
  - `6543210`
  - `Medicina Interna`
  - bio breve
- Seed idempotente para QA/demo local.

### 17.6 Validaciones de seguridad y alcance
- Se mantiene sin activacion:
  - contacto, telefono, WhatsApp;
  - agenda publica;
  - costo, medios de pago, aseguradoras;
  - reviews reales;
  - claim real.
- Se mantiene `seo.robots=noindex,nofollow` en fase transicional.
- No se altera Agenda ni reglas comerciales por `plan_code` en la vista.

### 17.7 Siguiente recomendado
- `PP-7H2-A` completado:
  - endpoint privado minimo de identidad publica (`GET/PATCH`) en `profiles_doctors` (`23de802`).
- Siguiente recomendado:
  - `PP-7H2-B` conexion minima del formulario visual de panel al endpoint privado, sin redisenar navegacion.
- Rutas secundarias/contextuales deben canonicalizar a la URL principal salvo estrategia futura de landings diferenciadas.

### 16.4 Claim y ownership
- Extender estados de ownership/claim para contrato operativo:
  - `unclaimed`
  - `claim_pending`
  - `claimed`
  - `rejected`
  - `needs_info`
- Perfil gratuito publicado puede seguir visible durante `claim_pending`.
- Perfil nuevo no se publica hasta aprobacion.

### 16.5 Reglas de plan/contacto/agenda
- Gratuito:
  - sin contacto directo;
  - sin agenda publica;
  - claim visible si corresponde.
- Basico:
  - contacto habilitable;
  - horarios visibles;
  - costo/medios de pago publicables segun configuracion;
  - sin reserva publica.
- Estandar+:
  - reserva publica (OTP) habilitable.
- En cualquier plan, gating se resuelve backend y se expresa via `public_visibility` + `agenda_public`.

### 16.6 Cedulas y publicacion
- Perfil medico individual no debe publicarse sin cedula profesional valida.
- Clasificacion como especialista requiere cedula de especialidad valida.
- Cedulas certificadas solo editables por operador plataforma.

### 16.7 Opiniones desde MVP
- `reviews` se considera en alcance MVP contractual.
- Moderacion obligatoria.
- Promedio puede alimentar JSON-LD cuando exista base suficiente.

### 16.8 Seguridad y privacidad reforzada
- Nunca exponer datos clinicos/pacientes/flags/tokens/API keys/notas internas.
- Motivo de consulta capturado en agenda publica: visible al medico, no a operador en contrato publico.
- `doctor_id` publico solo si se aprueba explicitamente.
- Campos comerciales solo por allowlist (`consultation_fee`, `payment_methods`, `accepted_insurances`) y segun visibilidad efectiva.

### 16.9 Robots y estado
- `removed` puede responder `410` para gestion SEO.
- `suspended` y estados no publicables deben resolver `423` o `noindex` segun ruta/regla.
- `seo.robots` se deriva de estado + completitud + visibilidad.

### 16.10 Recomendacion de fase siguiente
- PP-4 recomendado: diagnostico de fuentes reales para construir DTO ejecutable sin exponer datos sensibles.

## 17) Adenda PP-Decisiones 02 — Datos comerciales, aseguradoras y ecosistema ampliado

### 17.1 Campos comerciales del endpoint
- El contrato debe incluir `commercial_visibility` con:
  - `consultation_fee`
  - `payment_methods`
  - `accepted_insurances`
- Si no hay fuente canonica en PP-4B, el endpoint devuelve:
  - `consultation_fee = null`
  - `payment_methods = []`
  - `accepted_insurances = []`

### 17.2 Regla de visibilidad comercial
- El precio de consulta solo se publica cuando el medico lo habilita explicitamente.
- `json_ld.priceRange` solo debe generarse cuando el costo sea publico.
- Los medios de pago deben salir de catalogo controlado backend.

### 17.3 Aseguradoras aceptadas
- Debe preverse catalogo de aseguradoras con nombre/slug/logo/estado activo.
- La seleccion del medico se publica solo si plan/politica y configuracion lo permiten.
- Este bloque no depende de implementar de inmediato perfiles completos de aseguradoras.

### 17.4 Ecosistema ampliado
- Reservar `ecosystem_links` para futuras vinculaciones con:
  - aseguradoras
  - laboratorios clinicos
  - gabinetes de imagen
  - laboratorios farmaceuticos
  - grupos medicos
- Estos vinculos representan afiliacion, no propiedad ni permiso de edicion del perfil medico.

### 17.5 Seguridad
- No exponer datos clinicos/pacientes en ningun bloque comercial/ecosistema.
- No permitir que entidades externas (aseguradoras/labs/pharma) editen perfil medico.
- Toda comunicacion futura entre perfiles debe operar con opt-in y trazabilidad.

### 17.6 Scope PP-4B
- PP-4B mantiene enfoque en endpoint transicional read-only por `doctor_id`.
- No se implementa vista publica en esta fase.

## 18) Cierre PP-4B — Endpoint minimo transicional (implementado)

### 18.1 Estado
- PP-4B implementado y publicado.
- Endpoint transicional disponible:
  - `GET /api/profiles/public/doctor/{doctor_id}`
- Commit de implementacion:
  - `2398549 feat(profiles): agrega endpoint publico minimo por doctor`

### 18.2 Archivos creados por PP-4B
- `api/profiles/.htaccess`
- `api/profiles/index.php`
- `modules/profiles/controllers/PublicProfileController.php`
- `modules/profiles/repositories/PublicProfileRepository.php`

### 18.3 Alcance real implementado
- Endpoint publico read-only.
- DTO publico sanitizado con wrapper `ok/data/meta`.
- `meta.contract = profile_public_mvp`.
- `meta.version = PP-4B`.
- Bloques de salida implementados:
  - `profile`
  - `plan`
  - `public_visibility`
  - `identity`
  - `professional`
  - `specialties`
  - `consultorios`
  - `schedule`
  - `contact`
  - `agenda_public`
  - `commercial_visibility`
  - `reviews`
  - `claim`
  - `seo`
  - `json_ld`
  - `ecosystem_links`
  - `feature_flags`

### 18.4 Comportamiento conservador aplicado
- Si faltan datos minimos criticos (`identity.display_name` y `professional.professional_license`):
  - `profile.status = hidden`
  - `profile.is_public = false`
  - `feature_flags.has_public_profile = false`
  - `seo.robots = noindex,nofollow`
- No se inventan:
  - nombre publico;
  - cedulas;
  - especialidades;
  - costo de consulta;
  - aseguradoras;
  - reviews.
- Campos sin fuente canonica salen como `null`, `false` o `[]`.

### 18.5 Datos reales ya reutilizados
- Consultorios desde fuente real de Agenda.
- Horarios/schedule cuando hay fuente disponible.
- Coordenadas/mapa solo si existen datos publicos seguros.
- Integracion preparada para disponibilidad publica existente, sin habilitar agenda publica por gating real.

### 18.6 Seguridad aplicada
- Sin `SELECT *` en la implementacion del endpoint.
- DTO por allowlist explicita.
- No expone:
  - datos de pacientes;
  - motivos de consulta;
  - diagnosticos;
  - recetas;
  - documentos clinicos;
  - tokens/API keys;
  - flags clinicos;
  - datos fiscales;
  - passwords/secrets;
  - notas internas.

### 18.7 QA ejecutado (resumen)
- Sintaxis PHP (`php -l`) sin errores en:
  - `api/profiles/index.php`
  - `modules/profiles/controllers/PublicProfileController.php`
  - `modules/profiles/repositories/PublicProfileRepository.php`
- `git diff --check` limpio en PP-4B.
- Curl validado:
  - `doctor_id` valido/demo -> `200 OK`
  - `doctor_id` invalido -> `400 invalid_doctor_id`
  - `doctor_id` inexistente -> `404 profile_not_found`
- La respuesta valida contiene todos los bloques requeridos.
- La respuesta valida no contiene claves prohibidas.

### 18.8 Fuera de alcance de PP-4B
- No vista publica.
- No SSR PHP visual.
- No slug final ni URL SEO publica.
- No claim completo.
- No reviews backend.
- No gating real por plan.
- No modulo de aseguradoras ni catalogos comerciales finales.
- No perfiles de laboratorios/gabinetes/farmaceuticas.
- No cambios en Agenda.

### 18.9 Siguiente paso recomendado
- Opcion 1:
  - `PP-4C` QA ampliado del endpoint (mas doctores/demo, cobertura de contratos y validaciones negativas adicionales).
- Opcion 2:
  - `PP-5` preparacion de primera vista publica SSR PHP (sin diseno final ni slug definitivo).

Recomendacion:
- Ejecutar `PP-4C` primero antes de construir la vista publica.

## 19) Cierre PP-4C — QA ampliado del endpoint publico (validado)

### 19.1 Estado
- PP-4C ejecutado y validado como QA ampliado sin cambios de codigo.
- Endpoint validado:
  - `GET /api/profiles/public/doctor/{doctor_id}`

### 19.2 Casos probados
- `doctor_id` validos/resolubles:
  - `1`
  - `d_demo_01`
  - `d_1`
  - `qa_doc_late_cancel`
- `doctor_id` inexistentes:
  - `901101`
  - `doctor_not_found_999`
- `doctor_id` invalido:
  - `bad$id`

### 19.3 Resultados HTTP
- Validos/resolubles -> `200 OK`
- Inexistentes -> `404 profile_not_found`
- Invalido -> `400 invalid_doctor_id`

### 19.4 Validaciones de contrato (respuestas 200)
- Se confirmo presencia de:
  - `ok`
  - `data`
  - `meta`
  - `profile`
  - `plan`
  - `public_visibility`
  - `identity`
  - `professional`
  - `specialties`
  - `consultorios`
  - `schedule`
  - `contact`
  - `agenda_public`
  - `commercial_visibility`
  - `reviews`
  - `claim`
  - `seo`
  - `json_ld`
  - `ecosystem_links`
  - `feature_flags`

### 19.5 Comportamiento conservador (validado)
- Cuando faltan `identity.display_name` y `professional.professional_license`:
  - `profile.status = hidden`
  - `profile.is_public = false`
  - `feature_flags.has_public_profile = false`
  - `seo.robots = noindex,nofollow`

### 19.6 Campos comerciales (validado)
- `commercial_visibility` existe siempre.
- Sin fuente canonica:
  - `consultation_fee = null`
  - `payment_methods = []`
  - `accepted_insurances = []`
  - `commercial_restriction_reason = source_not_ready`

### 19.7 Ecosystem links (validado)
- `medical_groups = []`
- `insurers = []`
- `labs = []`
- `imaging_centers = []`
- `pharma_partners = []`

### 19.8 Seguridad (validado)
- En respuestas `200` no aparecen:
  - `patient_id`
  - `motivo`
  - `diagnostico`
  - `diagnóstico`
  - `receta`
  - `token`
  - `api_key`
  - `flag_type`
  - `datos_fiscales`
  - `password`
  - `secret`
  - `notas internas`

### 19.9 Sintaxis PHP (validado)
- `php -l api/profiles/index.php`
- `php -l modules/profiles/controllers/PublicProfileController.php`
- `php -l modules/profiles/repositories/PublicProfileRepository.php`

Resultado:
- Sin errores de sintaxis.

## 20) Cierre PP-5B / PP-5C — Vista publica SSR transicional (implementado + QA)

### 20.1 Estado PP-5B
- PP-5B implementado.
- Vista publica SSR transicional creada en:
  - `profiles/doctor.php`
- CSS dedicado creado en:
  - `assets/css/public-profile.css`
- URL transicional:
  - `/profiles/doctor.php?doctor_id=1`
- Commit de implementacion:
  - `77f3a1a feat(profiles): agrega vista publica SSR transicional`

### 20.2 Alcance real implementado
- Primera vista SSR minima del Perfil Medico.
- Consume server-side el endpoint:
  - `GET /api/profiles/public/doctor/{doctor_id}`
- Renderiza unicamente el DTO publico sanitizado.
- No consulta DB directamente en flujo normal (consumo HTTP del endpoint).
- No duplica el contrato de salida del endpoint.
- Mantiene fallback local para `php -S` de un solo worker, acotado a QA local, reutilizando:
  - `PublicProfileController`
  - `PublicProfileRepository`

### 20.3 Estructura visual implementada
- Header publico Mexico Medico.
- Buscador placeholder.
- Bloque principal del perfil.
- Foto/avatar.
- Consultorio principal.
- Direccion.
- Mapa cuando existe `map_embed_url`.
- Estado conservador "Informacion publica limitada" cuando `profile.is_public=false`.
- Bloques de consultorios y horarios.
- Footer basico.

### 20.4 Gating validado
- PP-5 no implementa motor de planes.
- La vista no usa condiciones por `plan_code`.
- `data.plan` se usa solo como informativo.
- La visibilidad depende de:
  - `data.public_visibility`
  - `data.feature_flags`
- Con flags conservadores de PP-4B se ocultan:
  - contacto;
  - telefono;
  - WhatsApp;
  - agenda publica;
  - costo;
  - aseguradoras.

### 20.5 SEO/SSR validado
- HTML renderizado desde servidor.
- `title` renderizado.
- `meta description` renderizado.
- `meta robots = noindex,nofollow` cuando viene asi.
- `canonical` omitido cuando `canonical_url` es `null`.
- JSON-LD omitido cuando `json_ld` es `null`.
- No se imprime JSON crudo completo.

### 20.6 Seguridad validada
- No se exponen claves prohibidas:
  - `patient_id`
  - `motivo`
  - `diagnostico`
  - `diagnóstico`
  - `receta`
  - `token`
  - `api_key`
  - `flag_type`
  - `datos_fiscales`
  - `password`
  - `secret`
  - `stack`
  - `trace`
- Output HTML escapado.
- Sin errores internos visibles en la pagina publica.

### 20.7 QA PP-5C ejecutado (resumen)
- Sintaxis PHP validada:
  - `php -l profiles/doctor.php`
  - `php -l api/profiles/index.php`
  - `php -l modules/profiles/controllers/PublicProfileController.php`
  - `php -l modules/profiles/repositories/PublicProfileRepository.php`
- URLs probadas:
  - `/profiles/doctor.php?doctor_id=1` -> `200 OK`
  - `/profiles/doctor.php?doctor_id=d_demo_01` -> `200 OK`
  - `/profiles/doctor.php?doctor_id=doctor_not_found_999` -> `404 Not Found`
  - `/profiles/doctor.php?doctor_id=bad%24id` -> `400 Bad Request`
  - `/profiles/doctor.php` -> `400 Bad Request`
- Estructura HTML validada.
- Gating visual validado.
- SEO/SSR validado.
- Seguridad validada.
- Responsive basico validado por CSS:
  - desktop en dos columnas;
  - movil en una columna.

### 20.8 Fuera de alcance vigente
- No slug final.
- No canonical SEO definitivo.
- No home publica.
- No listados por estado/especialidad.
- No diseno final pixel-perfect.
- No agenda interactiva completa.
- No reserva de cita.
- No OTP.
- No claim completo.
- No reviews reales.
- No catalogo real de aseguradoras.
- No motor real de planes.

### 20.9 Siguiente paso recomendado
- Opcion 1:
  - `PP-5D` micro-ajustes visuales del perfil gratuito SSR.
- Opcion 2:
  - `PP-6` acercamiento visual progresivo al boceto (sin cerrar slug/canonical finales).

## 21) Adenda PP-7C — Identidad profesional canonica minima (decision tecnica)

### 21.1 Estado actual
- El endpoint ya resuelve consultorios/mapa desde fuentes reales.
- La identidad profesional publica sigue en modo conservador cuando no existe fuente backend canonica.
- Semillas/localStorage/UI no son fuente valida para publicacion indexable.

### 21.2 Decision de fuente canonica
- Preparar tabla/fuente minima `profiles_doctors` dentro de `profiles_*`.
- El endpoint debe leer identidad profesional desde esa fuente de verdad y no desde formulario privado.

### 21.3 Campos minimos a mapear en DTO
- `identity.display_name`
- `identity.prefix`
- `identity.photo_url`
- `identity.avatar_url`
- `identity.logo_url`
- `professional.professional_license`
- `professional.specialty_license`
- `professional.bio_short`
- `specialties[]` (desde `specialty_primary`/fuente derivada)

### 21.4 Regla minima para estado publico
- Solo pasar a `profile.status=active` y `profile.is_public=true` cuando existan:
  - `display_name`
  - `professional_license`
  - `specialty_primary` (o equivalente)
  - al menos un consultorio publicable/resoluble

### 21.5 Guardrails
- Mantener allowlist explicita.
- No exponer datos privados/sensibles.
- Mantener `public_visibility`/`feature_flags` como motor de visibilidad.
- No usar `plan_code` para decisiones visuales en la vista SSR.

### 21.6 Proxima fase sugerida
- PP-7D:
  - schema minima `profiles_doctors`
  - seed demo controlado para QA local
  - adaptacion de `PublicProfileRepository`/`PublicProfileController`
  - QA de endpoint y vista SSR
