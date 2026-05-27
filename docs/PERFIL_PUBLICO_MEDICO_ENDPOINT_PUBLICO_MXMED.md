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
