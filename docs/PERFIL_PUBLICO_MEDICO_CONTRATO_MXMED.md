# Perfil Publico Medico — Contrato funcional inicial

## 1) Objetivo del modulo
Definir el contrato funcional inicial del Perfil Publico Medico para MXMed, alineado al estado actual del sistema y sin implementar funcionalidad en esta fase.

## 2) Relacion con Plan Maestro
- Agenda queda cerrada como: Hecho v1 funcional consolidado, con deudas documentadas.
- Este documento abre formalmente la siguiente fase:
  - PP-1 — Contrato funcional inicial del Perfil Publico Medico.
- No reabre pendientes funcionales de Agenda.

## 3) Relacion con dominio `profiles_*`
- El Perfil Publico Medico debe converger a un dominio canonico `profiles_*`.
- En esta fase se define contrato, no schema final ni endpoints finales.
- Debe integrar datos de Agenda y Consultorios sin duplicar fuentes de verdad.

## 4) Tipos de plan
- Gratuito
- Basico
- Estandar
- Optimo
- Profesional

## 5) Visibilidad publica por plan
- Gratuito: visibilidad base del profesional y sede, sin contacto directo ni agenda publica.
- Basico: visibilidad ampliada con imagen, horarios y canales de contacto permitidos.
- Estandar+: visibilidad con interaccion (agenda publica, reputacion/engagement segun gating).
- Optimo y Profesional: mayor visibilidad operativa y capacidades avanzadas segun contrato vigente.

## 6) Funcionalidades internas por plan
- Gratuito: perfil visible y flujo de reclamo (si aplica).
- Basico: gestion de perfil enriquecido + contacto habilitable por plan.
- Estandar: agenda publica y capacidades de conversion adicionales segun gating.
- Optimo: herramientas ampliadas de operacion/comunicacion.
- Profesional: stack operativo maximo (incluyendo capacidades avanzadas sujetas a governance).

## 7) Campos publicos minimos
Campos candidatos para exposicion publica (segun plan y configuracion):
- nombre publico del medico
- fotografia/avatar
- especialidad principal
- subespecialidades
- cedula profesional
- cedula de especialidad
- resena profesional
- formacion profesional
- servicios o padecimientos atendidos
- consultorios visibles
- horarios visibles
- ubicacion geografica publica
- telefono publico
- WhatsApp publico
- agenda publica
- opiniones visibles
- paquetes/promociones

## 8) Campos privados / no publicables
No deben exponerse en el perfil publico:
- datos internos de usuario
- configuracion administrativa
- datos fiscales
- reportes internos
- expediente clinico
- datos clinicos de pacientes
- motivo de consulta
- notas, diagnosticos, tratamientos, recetas
- documentos clinicos
- bitacoras internas
- flags de riesgo de pacientes
- datos privados de operadores
- tokens, credenciales o integraciones API

Regla invariable:
- Ningun dato clinico de paciente se publica en perfil publico.

## 9) Relacion con Consultorios
El perfil publico consume datos de consultorios:
- nombre visible del consultorio
- direccion
- ciudad/estado
- telefono publico (si aplica)
- lat/lng confirmados
- mapa
- horarios
- modalidad presencial/videoconsulta (si aplica)

Reglas:
- consultorios privados/inactivos no se publican
- hasta 3 sedes pueden mostrarse por bloques independientes
- prioridad a coordenadas confirmadas para mapa publico
- no exponer metadatos privados no autorizados

### 9.1) `geo_context` publico
El DTO publico del perfil medico puede exponer un contexto geografico explicito para preparar header geografico, rutas SEO y navegacion contextual futura.

Campos iniciales:
- `country_label`
- `state_name`
- `state_slug`
- `city_name`
- `city_slug`
- `source`
- `source_consultorio_id`
- `is_national`
- `available_locations`

Fuente actual:
- Si hay consultorios publicos visibles, se usa el primer consultorio visible como fuente inicial (`profile_consultorio_primary`).
- `available_locations` agrupa consultorios visibles por estado y ciudad.
- Si no hay datos publicos suficientes, se devuelve contexto nacional (`national_default`).

Notas:
- Los slugs de estado/ciudad son transitorios y se generan desde el texto disponible.
- No sustituyen un catalogo geografico canonico.
- No implican todavia rutas SEO funcionales ni canonical publico definitivo.

## 10) Relacion con Agenda publica
- Agenda publica se habilita solo si el plan lo permite.
- Debe usar la disponibilidad publica existente.
- Debe respetar horarios, bloqueos y consultorio real.
- Agendar desde perfil publico NO inicia encounter clinico automaticamente.
- La cita publica es un evento de Agenda.
- Creacion de cita: crea/vincula paciente segun flujos existentes.
- Debe respetar bloqueos canonicos backend (BLOQ-F2/BLOQ-F3).

## 11) Relacion con Reclamo de perfil
Aplica solo a perfiles gratuitos sin administrador titular:
- boton visible: Reclamar perfil / Reclamar este perfil
- no aplica a perfiles de pago
- no aplica a perfiles ya reclamados
- solicitante crea credenciales y adjunta documentacion
- acceso administrativo queda pendiente de verificacion humana
- estado del perfil: pendiente de verificacion
- aprobacion: asigna administrador titular, oculta boton, habilita panel
- rechazo: mantiene perfil gratuito no reclamado
- debe existir trazabilidad del origen y proceso de reclamo

## 12) Relacion con Notificaciones
Eventos previstos:
- confirmacion de cita
- recordatorio de cita
- cancelacion/reprogramacion
- invitacion a resena
- nueva resena al medico
- vencimiento de plan
- verificacion/reclamo de perfil

Canales y reglas:
- buzon interno: canal universal
- correo: canal universal
- WhatsApp: solo si plan + consentimiento + configuracion lo permiten
- push: futuro (si app instalada)
- despacho condicionado por plan, consentimiento y prioridad del evento

## 13) Relacion con SEO / JSON-LD
### 13.1 URL canonica objetivo
- listados en plural:
  - `/pediatras`
  - `/pediatras/aguascalientes`
  - `/pediatras/aguascalientes/centro`
- perfil individual en singular:
  - `/pediatra/aguascalientes/dr-nombre-apellido`
  - o patron equivalente por definir

Regla:
- plural para listados
- singular para perfil individual
- slug unico
- ciudad/estado integrados a arquitectura SEO

### 13.2 JSON-LD parametrizable
- `@type` dinamico (`Physician`, `Dentist`, `Psychologist`, `MedicalClinic`, `MedicalLaboratory`, etc.)
- `medicalSpecialty` estandarizado en ingles
- contenido textual en espanol (`es-MX`)
- multiples especialidades en array
- incluir: `name`, `url`, `telephone` (si publicable), `identifier`, `address`, `geo`, `openingHoursSpecification`, `aggregateRating` (si aplica), `priceRange` (si aplica)

### 13.3 Metadatos
Requeridos a futuro:
- title
- meta description
- H1
- canonical
- Open Graph
- imagen publica

## 14) Relacion con IA futura
Capacidades de soporte futuras (segun plan/gobernanza):
- redaccion de descripcion, resena y formacion
- sugerencias SEO/metatags
- sugerencias de contenido
- generador de imagenes para promociones/articulos
- con moderacion y responsabilidad del usuario

## 15) Relacion con IA clinica en recetas
Capacidad futura no-MVP:
- soporte de revision clinica (interacciones, duplicidad, alergias, dosis inusuales, contraindicaciones)
- salida en modo sugerencia/alerta, nunca decision automatica
- emision/firma final: exclusiva del Medico Titular
- trazabilidad cuando haya alertas relevantes
- puede comunicarse como capacidad avanzada segun plan, sin claims absolutos

Fuera del MVP:
- no implementar motor de interacciones
- no implementar IA clinica en esta fase
- no modificar modulo de recetas

## 16) Relacion con Videoconsulta
- Modulo contratado (planes de pago).
- Configuracion: Agenda -> Configuracion -> Canales de Videoconsulta.
- Canales: WhatsApp manual, Zoom, Microsoft Teams, Google Meet.
- Link se libera solo con pago acreditado (si aplica contrato comercial).
- Perfil publico solo muestra modalidad video si:
  - plan lo permite
  - medico lo configuro
  - canal esta activo
- Fuera de MVP si no hay contrato cerrado de pagos/canales.

## 17) MVP recomendado
Incluir en v1:
- ruta publica basica
- nombre
- fotografia/avatar
- especialidad
- cedulas
- resena profesional (si existe)
- consultorios visibles
- mapa/ubicacion
- horarios
- boton reclamar perfil (si aplica)
- botones de contacto segun plan
- agenda publica segun plan
- JSON-LD basico
- meta title/description basicos

## 18) Fuera de alcance del MVP
- implementacion completa de planes/suscripcion
- facturacion a pacientes/plataforma
- IA clinica real
- agente IA real
- videoconsulta con pagos
- reviews avanzadas (respuesta/archivo/moderacion completa)
- blog/articulos
- grupos medicos o por padecimiento
- backoffice completo de verificacion
- SEO masivo por ciudad/especialidad

## 19) Deuda futura
- dominio `profiles_*` real
- API publica de perfil
- slug canonico
- ownership/reclamo
- gating tecnico por plan
- integracion suscripcion
- integracion agenda publica
- integracion reviews
- integracion notificaciones
- JSON-LD avanzado
- moderacion de fotos/logos
- preferencias de comunicacion y opt-in
- SEO de listados
- IA de redaccion/metatags
- IA clinica en recetas
- Agente IA profesional
- Videoconsulta

## 20) Preguntas abiertas
1. Cual sera la URL canonica final de perfil individual?
2. Perfil publico inicial: nuevo dominio `profiles_*` o composicion temporal desde dominios actuales?
3. Fuente canonica de especialidades/subespecialidades?
4. Donde viviran cedula profesional y cedula de especialidad de forma canonica?
5. Que campos edita medico y cuales solo superadmin?
6. Como se valida/modera fotografia y logotipo?
7. Reglas exactas por plan para contacto, agenda, promociones y reviews?
8. Fecha/alcance del flujo completo de reclamo?
9. Las resenas existentes se migran o inician desde cero?
10. Que mapa de rutas SEO se aprueba oficialmente?
11. Se publicara o no costo de consulta (`priceRange`)?
12. Que claims comerciales se permitiran para IA clinica?
13. Como comunicar seguridad clinica sin prometer diagnostico automatico?

## Adenda PP-Decisiones 01 — Identidad, URL, contacto, agenda, reclamo, SEO y MVP

### A) Estrategia tecnica de render
- El perfil publico se define en SSR PHP para contenido principal indexable.
- JS progresivo solo para interacciones dinamicas: agenda semanal, reserva, OTP, buzon, metricas de clic, mapa, opiniones, formularios.
- El contenido SEO (nombre, especialidad, cedulas, domicilio, consultorios, horarios, resumen de opiniones, title/description/canonical/JSON-LD) debe salir del servidor.
- JS no puede ser la unica fuente de contenido indexable.

### B) Identidad publica
- El sistema conserva nombre completo certificado e inamovible.
- El medico puede elegir nombre visible (si tiene varios) y un solo apellido publico.
- Prefijo obligatorio predefinido por operador plataforma (Dr., Dra., Psic., Lic., etc.).
- Foto disponible desde plan Gratuito; si no hay, avatar generico masculino/femenino.
- Perfil individual puede mostrar logo del medico y logos de grupos medicos asociados cuando aplique.
- En perfil medico individual la identidad principal es el medico, no la marca comercial.
- Badge de perfil verificado visible.

### C) Cedulas
- Cedula profesional obligatoria para publicar perfil medico individual.
- Cedula de especialidad obligatoria para clasificarse como especialista.
- Perfiles creados por plataforma requieren verificacion previa.
- Si un perfil gratuito de plataforma queda sin cedula confirmada, operador debe darlo de baja.
- Cedulas certificadas: solo editables por operador plataforma.
- Se permiten multiples cedulas.
- Institucion emisora puede mostrarse en formacion/descripcion.
- No es obligatorio texto "cedula validada"; basta mostrar cedula.

### D) Catalogo / taxonomia SEO
- Especialidad principal obligatoria.
- Se permiten multiples especialidades y subespecialidades visibles.
- Catalogo controlado/cerrado aprobado por operador.
- El medico puede solicitar alta de nuevos padecimientos/servicios.
- Campos manuales deben normalizarse contra catalogo cuando aplique.
- Preparar arquitectura para listados por especialidad, ciudad, estado, tratamiento, enfermedad, padecimiento y grupo medico.
- Concepto tecnico de categoria publica: `seo_category` (especialidad, subespecialidad, procedimiento, padecimiento, tratamiento o grupo medico).

### E) URL / slug
- Estructura base aprobada:
  - `/{seo_category}/{ciudad}/{slug-medico}`
- Ejemplo:
  - `/ginecologos/aguascalientes/dr-alberto-rodriguez-zaragoza`
- Categoria no plural estricta valida:
  - `/colposcopia/aguascalientes/dr-alberto-rodriguez-zaragoza`
- Desambiguacion opcional:
  - `/{seo_category}/{estado}/{ciudad}/{slug-medico}`
- Si cambia ciudad, nueva URL + redireccion 301 desde URL anterior.
- Mantener historial de slugs.
- Slug controlado por plataforma (cambio via solicitud/revision).
- Resolver duplicados con apellido/especialidad/ciudad/sufijo controlado.
- Un medico puede tener URLs de entrada contextuales, pero debe existir URL canonica principal.
- Rutas secundarias deben canonicalizar a la principal salvo fase futura de landings diferenciadas.
- Ruta transicional por `doctor_id` queda solo para QA/desarrollo.

### F) Contacto publico
- CTAs publicos potenciales:
  - Llamar
  - WhatsApp
  - Buzon interno
  - Ver horarios disponibles
  - Enviar mensaje
- Gratuito oculta contacto.
- Basico habilita contacto.
- Telefonos pueden ser de consultorio o medico (incluyendo multiples por consultorio).
- WhatsApp configurable (consultorio/medico/operador) y con mensaje prellenado.
- Buzon interno requiere datos basicos + captcha.
- Registrar metricas de clic en telefono/WhatsApp.
- El medico puede desactivar WhatsApp aunque plan lo permita.
- Operador no puede editar telefonos publicos.
- CTA de agenda publica:
  - "Ver horarios disponibles"
  - "Reservar cita" tras seleccionar horario.

### G) Consultorios
- Maximo 3 consultorios visibles.
- Publicos por defecto, salvo ocultamiento explicito por medico.
- Un consultorio activo para agenda no debe quedar oculto publicamente.
- Mostrar por consultorio: nombre, direccion, telefonos, WhatsApp, horario, mapa, foto.
- Mapa abre Google Maps.
- Gratuito: mapa sin guia GPS activa.
- Basico: GPS activo si aplica.
- Si no hay coordenadas confirmadas, ocultar mapa (mostrar direccion textual).
- Nombre por defecto si falta: "Consultorio principal".
- Renombre publico permitido.
- Fotos de consultorio con moderacion.

### H) Horarios publicos
- Mostrar horarios generales por consultorio en Gratuito/Basico.
- Estandar habilita ademas disponibilidad para reservar.
- Horario publico respeta bloqueos.
- Ocultar dias sin disponibilidad.
- UX sugerida: Hoy, Manana, proximos dias.
- Permitir etiqueta "previa cita" o equivalente.

### I) Agenda publica
- Reserva habilitada desde Estandar.
- Requiere OTP.
- Paciente ve consultorio en card/preview.
- Modalidad default presencial (videoconsulta opcional por configuracion).
- Motivo de consulta opcional.
- Motivo desde perfil publico visible solo para medico.
- Operador no ve motivo (solo datos generales del paciente).
- Cancelacion publica via URL del resumen de cita.
- Reprogramacion publica: fuera de alcance actual.
- Waitlist publica opcional si medico la habilita.
- Reusar flujo publico existente de Agenda.
- Posible widget de primera cita disponible.
- Costo visible solo si medico lo habilita.
- Si agenda esta cerrada temporalmente, no se muestra.

### J) Reclamo de perfil
- Claim funcional desde MVP.
- Boton sugerido:
  - "Yo soy Dr. X, quiero administrar mi perfil"
- Solo titular puede reclamar.
- Datos iniciales: celular, correo, password, confirmacion, nombre.
- Documentos: identificacion, cedula profesional, cedula de especialidad (si desea clasificacion especialista).
- Sin acceso a panel hasta aprobacion de operador plataforma.
- Estados de claim:
  - `unclaimed`
  - `claim_pending`
  - `claimed`
  - `rejected`
  - `needs_info`
- Perfil gratuito publicado puede seguir visible durante revision.
- Perfil nuevo sin aprobar no debe publicarse.
- Rechazo se notifica por email.
- Conflicto de doble reclamo se resuelve por titularidad acreditada.
- Origen del perfil visible solo internamente para operadores.

### K) Opiniones
- Opiniones incluidas desde MVP.
- Gratuito muestra reseñas.
- Desde Basico se habilita respuesta del medico.
- Soporte de archivar/restaurar y moderacion.
- Sin encuesta privada separada.
- Fuentes de opinion:
  - formulario publico + captcha
  - enlace post-cita
- Si plan/setting lo requiere, restringir a pacientes con cita.
- Invitacion sugerida: +24h y reenvio +48h.
- Promedio incluido en JSON-LD.
- Sin anonimato.

### L) SEO / Schema
- Regla plural en listados confirmada.
- Ejemplos:
  - `mexicomedico.com/ginecologos/aguascalientes`
  - `mexicomedico.com/ginecologos/aguascalientes/dr-alberto-rodriguez-zaragoza`
- Meta title/description automaticos.
- Edicion SEO por operador plataforma y superadmin.
- Medico puede editar descripcion SEO con apoyo IA (si acepta y segun plan).
- JSON-LD desde MVP con `@type` segun especialidad (`Physician`, `Dentist`, `Psychologist`, etc.).
- `medicalSpecialty` en ingles cuando aplique recomendacion Schema.org.
- `priceRange` solo si usuario habilita costo.
- Perfil no reclamado puede indexar.
- Perfil suspendido debe ser `noindex`.
- Perfil incompleto puede indexar si cumple minimo (nombre, clasificacion, cedula, domicilio).
- Permitir entradas SEO contextuales por categoria con control estricto de canonical.

### M) Gating / suscripcion
- Gating debe resolver backend, no frontend.
- Vigencia de plan anual.
- Avisos de renovacion previos (excepto cobro automatico).
- En gracia: mensaje persistente + enlace de pago.
- Fin de gracia: desactivar funciones avanzadas sin borrar datos.
- Downgrade Estandar -> Basico:
  - advertir perdida de administracion de agenda publica y accesorios.
  - conservar citas ya existentes.
- Agenda interna puede mantenerse aunque se oculte agenda publica, pero sin accesorios restringidos por plan.
- Modulos congelados no borran historico.
- Operador puede pagar, no cambiar plan.
- No mostrar etiqueta de plan en perfil publico.
- Paquetes/promociones de pago arrancan desde Basico.

### N) Videoconsulta
- Informativa desde Basico si usuario la activa.
- Reservable desde Estandar.
- Canales: WhatsApp, Zoom, Teams, Meet.
- Pago previo obligatorio si aplica contrato.
- Precio visible opcional.
- Confirmacion por correo con fecha/hora/canal.
- Si no hay canal conectado operativo, medico debe resolver reprogramacion/reembolso.
- Para MVP puede quedar fuera de ejecucion funcional, pero prevista en contrato.

### O) IA
- No anunciar uso interno de IA en publico por defecto.
- IA siempre bajo activacion/aprobacion del usuario.
- IA redactora desde Basico (bio, metatags, SEO de especialidades/servicios/padecimientos).
- Contenido IA requiere revision/moderacion antes de publicar.
- IA clinica en recetas: solo Optimo/Profesional, uso interno, no claim publico inicial.
- Agente IA llamadas/chat en Profesional puede mostrarse como "Asistente virtual disponible".
- Agente IA puede agendar/cancelar segun servicio activo.
- Toda accion IA debe quedar auditada como "Agente IA".

### P) Datos privados / seguridad
- Nunca exponer datos de pacientes, flags, recetas, diagnosticos, documentos clinicos, tokens, API keys.
- Email del medico oculto por defecto.
- IDs internos no visibles salvo necesidad tecnica estricta.
- Campos con moderacion:
  - foto, fotos de consultorio, logotipo, cedulas, especialidad, bio, formacion, servicios/padecimientos, promociones, respuestas publicas, metatags, claims.
- Causas de suspension (entre otras):
  - cedula falsa/no acreditada
  - especialidad sin cedula valida
  - suplantacion
  - foto no correspondiente
  - contenido enganoso/promesas medicas absolutas
  - publicacion de datos de pacientes
  - fraude en reclamo
  - violacion de privacidad
  - datos de contacto falsos

### Q) MVP real acordado
- Endpoint transicional por `doctor_id` permitido solo QA/desarrollo.
- Ruta publica final por slug/categoria/ciudad.
- Secuencia recomendada:
  - primero endpoint backend read-only,
  - despues vista publica.
- Render principal SSR PHP.
- JS progresivo solo para interacciones.
- CSS separado con tokens/base.
- Vista fuera de `index.html`.
- Prueba con 5 medicos demo (uno por plan).
- Reusar datos reales de consultorio cuando existan.
- Agenda publica real + JSON-LD desde inicio.
- Claim funcional desde arranque MVP.

## Adenda PP-Decisiones 02 — Datos comerciales, aseguradoras y ecosistema ampliado

### A) Datos comerciales y medios de pago
- Cada medico podra configurar costo de consulta y decidir si se muestra publicamente.
- Debe existir catalogo controlado de medios de pago publicables:
  - efectivo
  - tarjetas bancarias
  - transferencia
  - otros futuros controlados por plataforma
- Si el costo no se habilita publicamente, la salida publica debe entregar valor nulo o bandera de ocultamiento.
- `priceRange` en JSON-LD solo aplica cuando el costo sea publico.

### B) Aseguradoras aceptadas
- El perfil debe prever desde inicio un catalogo precargado de aseguradoras (MX) con:
  - nombre publico
  - slug
  - logotipo
  - estado activo
  - distintivo/verificacion futura
- El medico selecciona aseguradoras por checkboxes en su panel.
- La visibilidad publica de aseguradoras depende de configuracion del medico y reglas de plan/politica.
- Esta capacidad no depende del modulo completo de perfiles de aseguradoras en la fase actual.

### C) Relacion futura con perfiles de aseguradoras
- A futuro, aseguradoras tendran perfiles propios y podran gestionar su red de prestadores.
- La aseguradora no puede editar datos del perfil medico.
- La relacion aseguradora-medico se modela como afiliacion/vinculacion, no propiedad del perfil.
- Fases futuras deben contemplar aceptacion/rechazo/revision por parte del medico.

### D) Ecosistema ampliado
- El modelo de perfiles debe contemplar crecimiento hacia:
  - laboratorios de analisis clinicos
  - gabinetes de imagenes medicas
  - laboratorios farmaceuticos
  - aseguradoras
  - grupos medicos
  - hospitales/clinicas (si aplica)
- Estas entidades quedan fuera del MVP actual, pero influyen en el contrato para evitar redisenos incompatibles.

### E) Seguridad y privacidad
- No exponer datos clinicos ni de pacientes en ningun perfil publico.
- No convertir ordenes clinicas en comunicacion comercial publica.
- No permitir que aseguradoras/laboratorios/farmaceuticas modifiquen datos del medico.
- Comunicaciones futuras entre entidades deben respetar opt-in, trazabilidad y gating por plan.

### F) Alcance PP-4B
- PP-4B mantiene enfoque en endpoint transicional read-only por `doctor_id`.
- Los campos comerciales/aseguradoras/ecosistema pueden salir como `null`, `false` o arrays vacios cuando no exista fuente canonica aun.
- No se implementa vista publica en esta fase.

## Adenda PP-Decisiones 03 — Boceto visual, estructura por plan y navegacion publica

### A) Referencia de boceto visual
- Existen bocetos guia para:
  - perfil gratuito;
  - perfil mejorado por plan;
  - plataforma/listado publico de busqueda.
- Los bocetos son referencia funcional/visual y de jerarquia de contenido.
- No constituyen especificacion pixel-perfect.
- PP-5 debe inspirarse en estos bocetos, sin forzar diseno final exacto en esta etapa.

### B) Perfil gratuito (ficha de directorio + reclamo)
- El perfil gratuito se entiende como ficha publica minima, util para descubrimiento y reclamo.
- Debe contemplar:
  - header/logo Mexico Medico;
  - navegacion superior basica con buscador simple;
  - foto o avatar;
  - nombre visible del medico;
  - badge/verificacion si aplica;
  - especialidad principal;
  - cedula profesional;
  - cedula de especialidad si aplica;
  - consultorio principal;
  - direccion;
  - mapa o referencia de ubicacion cuando existan coordenadas;
  - opiniones/resumen si existen;
  - enlace para sugerir correccion;
  - bloque de reclamo: "Yo soy este medico y quiero administrar mi perfil";
  - CTA comercial futuro tipo "Destacar mi perfil".
- Regla:
  - el gratuito no muestra contacto directo ni agenda publica cuando el gating no lo permita;
  - el gratuito no debe verse roto o vacio.

### C) Perfil mejorado por plan
- Los planes de pago agregan bloques de conversion y confianza.
- Capas progresivas:
  - foto real;
  - consultorios multiples;
  - nombre publico del consultorio;
  - logo de hospital/grupo asociado;
  - botones "Sobre mi", "Detalles de la consulta", "Llamar", "WhatsApp", "Ver horarios disponibles";
  - CTA "Reservar cita" dentro del flujo de agenda;
  - costo de consulta (si medico lo habilita);
  - medios de pago;
  - aseguradoras aceptadas;
  - agenda publica (si plan lo permite);
  - fotos/carrusel;
  - opiniones;
  - mapa/como llegar.
- Regla por plan:
  - Basico: contacto + presentacion mejorada + horarios + costo/medios/aseguradoras (si aplica).
  - Estandar: agenda publica/reserva.
  - Optimo/Profesional: visibilidad y recursos avanzados futuros.

### D) Agenda publica visual (direccion PP-5)
- El boceto preve un bloque "Agenda una cita" con:
  - Hoy / proximos dias;
  - horarios disponibles;
  - boton para expandir horarios;
  - busqueda de primera cita disponible.
- Para PP-5:
  - no implementar agenda interactiva completa;
  - no reabrir Agenda;
  - preparar bloque visual SSR o placeholder segun gating y datos disponibles.

### E) Aseguradoras como bloque visual
- Las aseguradoras aceptadas deben presentarse como bloque de valor publico:
  - logos;
  - nombre/slug/logo desde catalogo futuro;
  - seleccion declarada por medico desde panel.
- Diferenciar claramente:
  - declaracion del medico;
  - vinculacion verificada futura.
- Si no hay fuente canonica, el bloque puede quedar vacio/no disponible sin romper la vista.

### F) Plataforma/listado publico (fase separada)
- El boceto de busqueda por estado/especialidad corresponde a una fase distinta al perfil individual.
- Incluye a futuro:
  - home publica;
  - selector de estado;
  - selector de especialidad;
  - medicos destacados;
  - listados por estado/especialidad;
  - CTA "Eres medico? Crea tu perfil".
- Regla:
  - no mezclar esta home/listado con PP-5 inicial;
  - PP-5 se enfoca en perfil individual SSR.

### G) Estructura sugerida para PP-5 (primera vista SSR)
- Header publico Mexico Medico.
- Buscador/navegacion basica (estatica o placeholder inicial).
- Bloque principal:
  - columna izquierda: foto/avatar, consultorio principal, direccion, sugerir correccion, reclamar perfil;
  - columna derecha: nombre, badge, opiniones, especialidad, cedulas, descripcion breve.
- Bloques inferiores:
  - consultorios;
  - horarios;
  - contacto segun plan;
  - agenda segun plan;
  - `commercial_visibility`;
  - `accepted_insurances`;
  - opiniones;
  - footer.

### H) Limites explicitos de PP-5
- No diseno final pixel-perfect.
- No slug final ni canonical SEO definitivo.
- No home publica ni listados por estado/especialidad.
- No agenda interactiva completa.
- No claim completo.
- No reviews reales si no existe backend consolidado.
- No catalogo real de aseguradoras en esta etapa.
- No tocar `index.html`.
- No tocar Agenda.

## Adenda PP-Decisiones 04 — Identidad profesional canonica minima (PP-7C)

### A) Problema actual
- El perfil publico ya puede renderizar consultorio principal y mapa desde fuentes reales.
- La identidad profesional publica (nombre, prefijo, cedulas, especialidad, bio, foto) no tiene fuente backend canonica.
- Hoy esos datos viven en seed/localStorage/UI y no son validos como fuente publica indexable.

### B) Decision
- Preparar una fuente canonica minima del dominio `profiles_*`:
  - `profiles_doctors`
- Regla de arquitectura:
  - panel privado -> DB canonica -> endpoint publico DTO sanitizado -> vista SSR.
- Prohibido:
  - panel privado -> vista publica directa.

### C) Campos minimos de la fuente canonica
- `doctor_id`
- `display_name`
- `prefix`
- `gender` / `gender_label`
- `professional_license`
- `specialty_license`
- `specialty_primary`
- `specialty_secondary_json`
- `bio_short`
- `photo_url`
- `avatar_url`
- `logo_url`
- `profile_status`
- `is_public_candidate`
- `created_at`
- `updated_at`

### D) Regla minima de publicacion
- Un perfil puede pasar de `hidden/is_public=false` a `active/is_public=true` solo si existen:
  - `display_name`
  - `professional_license`
  - `specialty_primary` (o equivalente)
  - al menos un consultorio publicable/resoluble
- Esta fase no activa:
  - contacto publico
  - agenda publica
  - costo
  - aseguradoras
  - reviews
  - claim real

### E) Implementacion posterior recomendada
- Fase siguiente: PP-7D
  - schema/migracion minima `profiles_doctors`
  - seed demo controlado (QA local)
  - adaptacion endpoint publico para leer primero `profiles_doctors`
  - QA endpoint + QA vista SSR

## Adenda PP-Decisiones 05 — Panel privado para Identidad publica profesional (PP-7G)

### A) Alcance de producto/arquitectura
- La edicion de identidad publica profesional debe vivir en panel privado, separada de datos internos no publicos.
- Flujo obligatorio:
  - Panel privado -> `profiles_doctors` -> endpoint publico DTO sanitizado -> vista SSR.
- Prohibido:
  - panel privado -> vista publica directa.
  - localStorage/seed/UI como fuente publica final.

### B) Ubicacion recomendada en panel
- Ruta UX recomendada:
  - Mi Perfil -> Informacion -> Identidad publica profesional.
- Debe incluir un bloque explicito:
  - "Estos datos se mostraran en tu perfil publico".

### C) Regla de edicion/gobernanza
- `display_name`, `prefix`, `specialty_primary`, `bio_short`: editables por medico (con validaciones).
- `professional_license` y `specialty_license`: capturables por medico, sujetas a revision de plataforma.
- `profile_status` e `is_public_candidate`: no editables libremente por medico; decision final backend/plataforma.
- Prefijo profesional: no texto libre; catalogo controlado.

### D) Siguiente fase
- PP-7H recomendado:
  - endpoint privado minimo (`GET/PATCH`) para lectura/guardado en `profiles_doctors`;
  - conexion de formulario de panel privado a fuente canonica;
  - mantener localStorage solo como respaldo UX transicional.

### E) Referencia documental
- Detalle UX/contrato tecnico de PP-7G:
  - `docs/PERFIL_PUBLICO_MEDICO_PANEL_IDENTIDAD_PUBLICA_MXMED.md`

## Adenda PP-Decisiones 06 — Taxonomia controlada de navegacion publica

### A) Campo DTO publico
- El perfil publico expone `public_navigation_taxonomy` como read-model controlado para alimentar el header publico.
- Fuente actual: `controlled_navigation_taxonomy`.
- Version actual: `nav-taxonomy-v1`.
- `route_generation`: `disabled`.

### B) Secciones
- `medical_specialists`
- `dental_specialists`
- `other_services`
- `hospitals`
- `clinics`
- `laboratories`

Cada seccion incluye:
- `key`
- `label`
- `enabled`
- `sort_order`
- `source`
- `items`

### C) Items
Cada item incluye:
- `label`
- `slug`
- `profile_type`
- `source`
- `enabled`
- `sort_order`
- `route_enabled`
- `url`

Regla vigente:
- `source`: `controlled_navigation_taxonomy`
- `route_enabled`: `false`
- `url`: `null`

### D) Limites vigentes
- Los slugs son controlados por esta taxonomia visual inicial, pero aun no son rutas reales ni canonicals definitivos.
- No se alimenta desde `profiles_doctors.specialty_primary` porque ese campo es texto libre y no representa catalogo publico estable.
- No hay selector de estado, rutas SEO, listados publicos ni navegacion funcional en esta fase.
- La generacion futura de URL debe combinar esta taxonomia con `geo_context`, por ejemplo `/{state_slug}/{city_slug}/{item_slug}`, en una microfase posterior.

---

## Adenda PP-Decisiones 07 — Contexto candidato de URL publica

### A) Campo DTO publico
- El perfil publico expone `public_url_context` como read-model derivado para preparar URLs publicas futuras.
- Fuente actual: `derived_public_url_builder`.
- Version actual: `public-url-v1`.
- `route_generation`: `candidate_only`.
- `route_enabled`: `false`.
- `canonical_enabled`: `false`.

### B) Proposito
- Concentrar candidatos de URL para listados, perfil individual y breadcrumbs potenciales sin activar rutas reales.
- Preparar el futuro `PublicUrlBuilder` con datos provenientes de:
  - `geo_context`;
  - `public_navigation_taxonomy`;
  - especialidad principal;
  - nombre publico del perfil.

### C) Patrones candidatos
- Listados geo-first:
  - `/{estado}/{ciudad}/{item_slug}`
  - Ejemplo: `/aguascalientes/aguascalientes/endocrinologos`
- Perfil individual:
  - candidato preferente singular si se puede derivar de forma transitoria:
    - `/{estado}/{ciudad}/{especialidad-singular}/{slug-medico}`
  - fallback mas estable:
    - `/{estado}/{ciudad}/medicos/{slug-medico}`
- Patrones legacy solo como referencia:
  - `/{seo_category}/{ciudad}/{slug-medico}`
  - `/{seo_category}/{estado}/{ciudad}/{slug-medico}`

### D) Slugs y warnings
- El slug de perfil se genera de forma transitoria desde `identity.display_name`.
- Los slugs geograficos siguen siendo transitorios desde `geo_context`.
- Los slugs de especialidad/listado vienen de `public_navigation_taxonomy`.
- La especialidad singular no es canonica todavia; si se deriva, debe reportarse con warnings.
- Warnings esperados:
  - `seo_routes_not_implemented`
  - `canonical_url_missing`
  - `profile_slug_transient`
  - `geo_slug_transient`
  - `specialty_slug_transient`
  - `slug_history_missing`
  - `singular_specialty_not_canonical`
  - `legacy_url_pattern_detected`
  - `canonical_pending`

### E) Breadcrumbs candidatos
- `public_url_context.breadcrumbs` puede incluir una ruta potencial:
  - Mexico Medico;
  - estado;
  - ciudad;
  - listado/especialidad;
  - perfil actual.
- Estos breadcrumbs no se renderizan todavia.
- `seo.breadcrumb` permanece vacio hasta una microfase especifica de breadcrumb read-model/canonical.

### F) Limites vigentes
- No modifica `.htaccess`.
- No crea rutas SEO reales.
- No activa navegacion real desde el header.
- No modifica `public_navigation_taxonomy.items[].url`, que permanece `null`.
- No modifica `profile.canonical_url`.
- No modifica `seo.canonical_url`.
- No modifica `seo.robots`.
- No agrega JSON-LD.

---

## Adenda PP-Decisiones 08 — Read-model de breadcrumbs publicos

### A) Campo DTO publico
- El perfil publico expone `public_breadcrumbs` como read-model formal derivado de `public_url_context.breadcrumbs`.
- Fuente actual: `public_url_context`.
- Version actual: `breadcrumb-v1`.
- `render_enabled`: `true` desde la Adenda PP-Decisiones 09 para render visual controlado.
- `json_ld_enabled`: `false`.
- `route_enabled`: `false`.

### B) Proposito
- Normalizar breadcrumbs candidatos para una futura microfase visual.
- Mantener una capa separada de `seo.breadcrumb` para no activar SEO indexable antes de tener rutas reales y canonical.
- Permitir que el SSR futuro consuma una estructura estable sin depender directamente de `public_url_context`.

### C) Estructura de items
Cada item de `public_breadcrumbs.items` incluye:
- `label`: texto candidato visible.
- `candidate_url`: URL candidata derivada desde `public_url_context.breadcrumbs`.
- `url`: siempre `null` en esta fase.
- `route_enabled`: siempre `false` en esta fase.
- `is_current`: `true` solo para el item actual cuando aplique.
- `position`: posicion 1-based.

### D) Warnings
Warnings minimos esperados:
- `seo_routes_not_implemented`
- `canonical_pending`
- `route_disabled`
- `json_ld_not_enabled`

Puede conservar warnings relevantes derivados de `public_url_context.warnings`, por ejemplo:
- `profile_slug_transient`
- `geo_slug_transient`
- `specialty_slug_transient`
- `slug_history_missing`
- `singular_specialty_not_canonical`

### E) Limites vigentes
- `public_breadcrumbs` solo habilita render visual SSR cuando `render_enabled=true`.
- `public_breadcrumbs` no genera JSON-LD todavia.
- `public_breadcrumbs` no llena `seo.breadcrumb` todavia.
- `public_breadcrumbs` no activa rutas reales.
- `public_breadcrumbs` no cambia canonical.
- `public_breadcrumbs` no cambia robots.
- `public_breadcrumbs.items[].url` permanece `null`.
- `public_breadcrumbs.items[].route_enabled` permanece `false`.

---

## Adenda PP-Decisiones 09 — Breadcrumb visual publico

### A) Activacion visual controlada
- El perfil publico puede renderizar un breadcrumb visual alimentado por `public_breadcrumbs.items`.
- `public_breadcrumbs.render_enabled` queda en `true` para habilitar solo la presentacion SSR.
- `public_breadcrumbs.json_ld_enabled` permanece `false`.
- `public_breadcrumbs.route_enabled` permanece `false`.

### B) Render SSR
- El SSR consume `public_breadcrumbs.items` de forma defensiva.
- El breadcrumb se muestra como texto no enlazado.
- El item actual se marca visualmente y con `aria-current="page"`.
- Los separadores son visuales y no representan rutas activas.

### C) URLs candidatas
- `items[].candidate_url` sigue siendo una URL candidata.
- `items[].candidate_url` no se usa como `href`.
- `items[].url` permanece `null`.
- `items[].route_enabled` permanece `false`.

### D) Limites SEO vigentes
- No se genera JSON-LD `BreadcrumbList`.
- No se llena `seo.breadcrumb`; permanece `[]`.
- No se cambia `seo.canonical_url`; permanece `null`.
- No se cambia `seo.robots`; permanece `noindex,nofollow`.
- No se activa ninguna ruta real ni rewrite SEO.
- No se modifica `.htaccess`.

---

## Adenda PP-Decisiones 10 — Politica de breadcrumbs geograficos deduplicados

### A) Objetivo de la politica
- `public_breadcrumbs` puede deduplicar niveles geograficos repetidos para mejorar UX y preparar una jerarquia futura mas limpia.
- La deduplicacion aplica al read-model formal `public_breadcrumbs.items`.
- `public_url_context.breadcrumbs` puede conservar los candidatos crudos derivados del builder.

### B) Regla aplicada
- Si `geo_context.state_slug` y `geo_context.city_slug` existen y son iguales, se muestra un solo nivel geografico.
- Si `geo_context.state_slug` y `geo_context.city_slug` son distintos, se conservan Estado y Ciudad.
- Si no hay slugs comparables, el read-model puede usar labels consecutivos normalizados como respaldo defensivo.

Ejemplo con estado y ciudad iguales:
- Crudo: `Mexico Medico / Aguascalientes / Aguascalientes / Endocrinologos / Dra. Leticia Munoz Romo`.
- Read-model: `Mexico Medico / Aguascalientes / Endocrinologos / Dra. Leticia Munoz Romo`.

Ejemplo con estado y ciudad distintos:
- `Mexico Medico / Jalisco / Guadalajara / Cardiologos / Dr. Nombre Apellido`.
- En este caso no se deduplica.

### C) Senales del contrato
- `public_breadcrumbs.display_policy` indica la politica aplicada.
- Cuando se deduplica, puede reportar `display_policy=deduplicate_same_geo`.
- Cuando no se deduplica, puede reportar `display_policy=standard_geo_hierarchy`.
- El warning `same_geo_breadcrumb_deduplicated` aparece solo cuando la deduplicacion aplica.
- Las posiciones se recalculan en orden 1-based despues de deduplicar.

### D) Limites vigentes
- `items[].candidate_url` sigue siendo candidata.
- `items[].url` permanece `null`.
- `items[].route_enabled` permanece `false`.
- `public_breadcrumbs.route_enabled` permanece `false`.
- `public_breadcrumbs.json_ld_enabled` permanece `false`.
- `json_ld` permanece `null`.
- `seo.breadcrumb` permanece `[]`.
- `seo.canonical_url` permanece `null`.
- `seo.robots` permanece `noindex,nofollow`.
- No se activan rutas reales.
- No se renderiza JSON-LD.
- No se modifica `.htaccess`.

---

## Adenda PP-Decisiones 11 — JSON-LD candidato de breadcrumbs deduplicados

### A) Campo candidato
- `public_breadcrumbs` puede incluir `json_ld_candidate`.
- La fuente es `public_breadcrumbs.items`, no `public_url_context.breadcrumbs`.
- Por lo tanto, usa el breadcrumb ya normalizado y deduplicado cuando `display_policy=deduplicate_same_geo`.
- Version actual: `breadcrumb-jsonld-candidate-v1`.

### B) Estado de activacion
- `json_ld_candidate.enabled` permanece `false`.
- `json_ld_candidate.script_render_enabled` permanece `false`.
- `public_breadcrumbs.json_ld_enabled` permanece `false`.
- `json_ld` real permanece `null`.
- No se renderiza `<script type="application/ld+json">` en SSR.

### C) Estructura
- `schema_type`: `BreadcrumbList`.
- `context`: `https://schema.org`.
- Cada item incluye:
  - `position`: posicion derivada de `public_breadcrumbs.items[].position`.
  - `name`: texto derivado de `public_breadcrumbs.items[].label`.
  - `candidate_item`: URL candidata derivada de `public_breadcrumbs.items[].candidate_url`.
  - `item`: siempre `null` en esta fase.
  - `route_enabled`: siempre `false`.

### D) Limites vigentes
- `candidate_item` conserva la URL candidata y no se usa como URL real.
- `item` permanece `null`.
- `route_enabled` permanece `false`.
- `seo.breadcrumb` permanece `[]`.
- `seo.canonical_url` permanece `null`.
- `seo.robots` permanece `noindex,nofollow`.
- No hay canonical.
- No hay rutas reales.
- No se modifica `.htaccess`.

---

## Adenda PP-Decisiones 12 — Tabla canonical de rutas SEO publicas

### A) Tabla definida
- Se agrega el SQL idempotente `modules/profiles/db/2026_06_19_create_public_profile_seo_routes.sql`.
- La tabla definida es `public_profile_seo_routes`.
- Proposito: guardar la ruta canonica actual por entidad publicable.
- Esta tabla no guarda aliases, historial ni redirects 301.

### B) Compatibilidad multi-tipo
- `entity_type` permite distinguir entidades futuras como `doctor`, `dentist`, `hospital`, `clinic`, `laboratory`, `diagnostic_center`, `insurer`, `pharma` y `service_provider`.
- `profile_type` permite distinguir la presentacion publica: `doctor`, `dental`, `hospital`, `clinic`, `laboratory`, `diagnostic`, `insurer`, `pharma` y `service`.
- `entity_id` es `VARCHAR(64)` porque `profiles_doctors.doctor_id` ya usa identificadores tipo texto.
- La tabla no agrega columnas a `profiles_doctors` para evitar acoplar SEO solo al dominio medico.

### C) Estado inicial
- `status` inicia como `candidate`.
- `route_enabled` inicia en `0`.
- `canonical_enabled` inicia en `0`.
- `source` inicia como `derived_public_url_builder`.
- `version` inicia como `seo-route-v1`.
- No se activa router.
- No se activa canonical real.
- No se activa JSON-LD real.
- No se cambia `seo.robots`.

### D) Ruta conceptual para doctor_id=1
- `entity_type=doctor`.
- `entity_id=1`.
- `profile_type=doctor`.
- `profile_slug=dra-leticia-munoz-romo`.
- `canonical_path=/aguascalientes/aguascalientes/medicos/dra-leticia-munoz-romo`.
- `canonical_state_slug=aguascalientes`.
- `canonical_city_slug=aguascalientes`.
- `canonical_specialty_slug=NULL` en la primera fase.
- `status=candidate`.
- `route_enabled=0`.
- `canonical_enabled=0`.

### E) Limites vigentes
- No se puebla la tabla en esta fase.
- No se conecta el DTO publico a la tabla todavia.
- `public_url_context` sigue usando candidatos derivados.
- `profile.slug` sigue sin activarse como slug productivo.
- `profile.canonical_url` y `seo.canonical_url` permanecen `null`.
- `json_ld` real permanece `null`.
- `seo.breadcrumb` permanece `[]`.
- La tabla de aliases, historial y redirects 301 queda para una fase posterior.

---

## Adenda PP-Decisiones 13 — Read-model de ruta canonica publica

### A) Campo del DTO
- El DTO publico expone `public_canonical_route`.
- La fuente es `public_profile_seo_routes`.
- Version actual del read-model: `canonical-route-readmodel-v1`.
- El objetivo es informar si existe una ruta canonica persistida para la entidad publica.

### B) Cuando no existe fila persistida
- `found=false`.
- `canonical_path=null`.
- `canonical_url=null`.
- `route_enabled=false`.
- `canonical_enabled=false`.
- `can_route=false`.
- `can_render_canonical=false`.
- `candidate_path_from_builder` puede mostrar la ruta candidata derivada desde `public_url_context.profile.fallback_candidate_url`.
- El warning `canonical_route_not_persisted` indica que la tabla aun no tiene ruta para esa entidad.

### C) Cuando existe fila persistida
- `found=true`.
- El read-model expone `status`, `profile_slug`, `canonical_path`, `canonical_state_slug`, `canonical_city_slug` y `canonical_specialty_slug` desde la tabla.
- `route_enabled` y `canonical_enabled` salen como booleanos.
- Aunque exista fila, `canonical_url` permanece `null` hasta una fase explicita de activacion.
- Aunque `route_enabled` llegue a estar activo en datos, `can_route` permanece `false` mientras no exista router SEO.
- Aunque `canonical_enabled` llegue a estar activo en datos, `can_render_canonical` permanece `false` mientras no exista fase de canonical real.

### D) Limites vigentes
- No hay insert automatico en `public_profile_seo_routes`.
- No se puebla `doctor_id=1` en esta fase.
- No se cambia `profile.canonical_url`.
- No se cambia `seo.canonical_url`.
- `seo.robots` permanece `noindex,nofollow`.
- `seo.breadcrumb` permanece `[]`.
- `json_ld` real permanece `null`.
- No se renderiza `<link rel="canonical">`.
- No se renderiza JSON-LD real.
- No se activa router.
- No se crean aliases ni redirects 301.
- No se modifica `.htaccess`.

---

## Adenda PP-Decisiones 14 — Seed candidato de ruta canonica doctor 1

### A) Seed controlado
- Se agrega el SQL idempotente `modules/profiles/db/2026_06_19_seed_public_profile_seo_route_doctor_1_candidate.sql`.
- El seed crea o actualiza un unico registro candidato para `doctor_id=1`.
- El registro vive en `public_profile_seo_routes`.
- El seed existe para validar que el read-model `public_canonical_route` lea rutas persistidas.

### B) Ruta candidata
- `entity_type=doctor`.
- `entity_id=1`.
- `profile_type=doctor`.
- `profile_slug=dra-leticia-munoz-romo`.
- `canonical_path=/aguascalientes/aguascalientes/medicos/dra-leticia-munoz-romo`.
- `canonical_state_slug=aguascalientes`.
- `canonical_city_slug=aguascalientes`.
- `canonical_specialty_slug=NULL`.

### C) Estado desactivado
- `status=candidate`.
- `route_enabled=0`.
- `canonical_enabled=0`.
- `source=derived_public_url_builder`.
- `version=seo-route-v1`.
- No se usa `status=active`.
- No se activa router.
- No se activa canonical real.
- No se activa JSON-LD real.
- No se cambia `seo.robots`.
- No se genera `<link rel="canonical">` en SSR.
- No se generan redirects 301.
- El seed no crea aliases ni historial.

### D) Limites vigentes
- `profile.canonical_url` permanece `null`.
- `seo.canonical_url` permanece `null`.
- `seo.robots` permanece `noindex,nofollow`.
- `json_ld` real permanece `null`.
- El router SEO sigue sin existir.
- `.htaccess` no cambia.

---

## Adenda PP-Decisiones 15 — Estados de activacion de ruta canonica publica

### A) Estado del read-model
- `public_canonical_route` diferencia explicitamente el estado de activacion de una ruta canonica publica.
- Campos agregados al contrato:
  - `activation_state`.
  - `is_persisted`.
  - `is_candidate`.
  - `is_active`.
  - `is_blocked`.
  - `blocking_reasons`.
- Estos campos son diagnosticos y no activan router, canonical, JSON-LD real ni robots index.

### B) Estados soportados
- `not_persisted`: no existe fila en `public_profile_seo_routes`.
- `persisted_candidate`: existe fila con `status=candidate`.
- `persisted_reserved`: existe fila reservada, pero no activa.
- `persisted_active_pending_router`: existe fila `active`, pero aun falta fase explicita de router/canonical real.
- `persisted_blocked`: existe fila bloqueada.
- `persisted_retired`: existe fila retirada.
- `unknown_status`: existe fila con estado no reconocido.

### C) Estado actual de doctor_id=1
- `found=true`.
- `is_persisted=true`.
- `activation_state=persisted_candidate`.
- `status=candidate`.
- `is_candidate=true`.
- `is_active=false`.
- `is_blocked=false`.
- `route_enabled=false`.
- `canonical_enabled=false`.
- `can_route=false`.
- `can_render_canonical=false`.
- `blocking_reasons` incluye `status_candidate`, `route_disabled`, `canonical_disabled`, `robots_noindex_active` y `seo_router_not_implemented`.

### D) Limites vigentes
- `found=true` no implica canonical activo.
- `status=candidate` no habilita router ni canonical.
- `route_enabled=0` mantiene `can_route=false`.
- `canonical_enabled=0` mantiene `can_render_canonical=false`.
- `seo.robots` permanece `noindex,nofollow` y sigue bloqueando indexacion.
- Aunque una fila futura llegue como `status=active`, esta fase mantiene `can_route=false` y `can_render_canonical=false` hasta una activacion explicita.
- No se cambia `profile.canonical_url`.
- No se cambia `seo.canonical_url`.
- No se renderiza `<link rel="canonical">`.
- No se activa JSON-LD real.
- No se modifica `.htaccess`.

---

## Adenda PP-Decisiones 16 — Canonical Render Guard

### A) Campo del DTO
- El DTO publico expone `canonical_render_guard` como llave de primer nivel.
- La fuente del guard es `public_canonical_route`.
- Version actual: `canonical-render-guard-v1`.
- El objetivo es centralizar las condiciones futuras para renderizar `<link rel="canonical">`.

### B) Condiciones futuras requeridas
- Ruta persistida en `public_profile_seo_routes`.
- `status=active`.
- `route_enabled=1`.
- `canonical_enabled=1`.
- `robots` debe permitir indexacion.
- Router SEO publico implementado.
- Renderer canonical habilitado por fase explicita.

### C) Estado actual de doctor_id=1
- `enabled=false`.
- `can_render=false`.
- `candidate_path=/aguascalientes/aguascalientes/medicos/dra-leticia-munoz-romo`.
- `canonical_url=null`.
- `requires.route_persisted=true`.
- `requires.status_active=false`.
- `requires.route_enabled=false`.
- `requires.canonical_enabled=false`.
- `requires.robots_index_allowed=false`.
- `requires.seo_router_enabled=false`.
- `requires.canonical_renderer_enabled=false`.
- `blocking_reasons` incluye `status_candidate`, `route_disabled`, `canonical_disabled`, `robots_noindex_active`, `seo_router_not_implemented` y `canonical_renderer_not_enabled`.

### D) Limites vigentes
- Ruta persistida no significa canonical activo.
- `status=candidate` bloquea canonical.
- `route_enabled=0` bloquea router.
- `canonical_enabled=0` bloquea canonical.
- `robots=noindex,nofollow` bloquea indexacion.
- `seo_router_enabled=false` bloquea rutas/canonical reales.
- `canonical_renderer_enabled=false` bloquea `<link rel="canonical">`.
- No se modifica SSR.
- No se modifica DB.
- No se activa canonical.
- No se activa JSON-LD real.
- No se cambia `seo.robots`.
- No se modifica `.htaccess`.

---

## Adenda PP-Decisiones 17 — JSON-LD Render Guard

### A) Campo del DTO
- El DTO publico expone `json_ld_render_guard` como llave de primer nivel.
- La fuente del guard es `public_breadcrumbs`.
- Version actual: `jsonld-render-guard-v1`.
- El objetivo es centralizar las condiciones futuras para renderizar JSON-LD real.

### B) Candidatos detectados
- `candidate_sources.breadcrumb_list.available=true` cuando existe `public_breadcrumbs.json_ld_candidate` con items.
- `candidate_sources.breadcrumb_list.source=public_breadcrumbs.json_ld_candidate`.
- `candidate_sources.breadcrumb_list.enabled=false`.
- `candidate_sources.breadcrumb_list.script_render_enabled=false`.
- Para `doctor_id=1`, `candidate_sources.breadcrumb_list.item_count=4`.
- `candidate_sources.profile.available=false`.
- `candidate_sources.profile.reason=profile_jsonld_not_implemented`.

### C) Requisitos futuros
- `canonical_ready`.
- `canonical_render_enabled`.
- `route_enabled`.
- `robots_index_allowed`.
- `breadcrumb_jsonld_enabled`.
- `jsonld_renderer_enabled`.

### D) Estado actual de doctor_id=1
- `enabled=false`.
- `can_render=false`.
- `json_ld=null`.
- `script_render_enabled=false`.
- `requires.canonical_ready=false`.
- `requires.canonical_render_enabled=false`.
- `requires.route_enabled=false`.
- `requires.robots_index_allowed=false`.
- `requires.breadcrumb_jsonld_enabled=false`.
- `requires.jsonld_renderer_enabled=false`.
- `blocking_reasons` incluye `canonical_not_ready`, `canonical_render_disabled`, `route_disabled`, `robots_noindex_active`, `breadcrumb_jsonld_disabled` y `jsonld_renderer_not_enabled`.

### E) Limites vigentes
- `json_ld` real permanece `null`.
- `public_breadcrumbs.json_ld_enabled` permanece `false`.
- `public_breadcrumbs.json_ld_candidate.enabled` permanece `false`.
- `public_breadcrumbs.json_ld_candidate.script_render_enabled` permanece `false`.
- `canonical_render_guard.can_render` permanece `false`.
- `profile.canonical_url` permanece `null`.
- `seo.canonical_url` permanece `null`.
- `seo.robots` permanece `noindex,nofollow`.
- No se renderiza `<script type="application/ld+json">`.
- No se renderiza `<link rel="canonical">`.
- No se modifica SSR.
- No se modifica DB.
- No se activa canonical.
- No se activa JSON-LD real.
- No se modifica `.htaccess`.

---

## Adenda PP-Decisiones 18 — Public Route Guard

### A) Campo del DTO
- El DTO publico expone `public_route_guard` como llave de primer nivel.
- La fuente del guard es `public_canonical_route`.
- Version actual: `public-route-guard-v1`.
- El objetivo es centralizar las condiciones futuras para servir una URL publica SEO real.

### B) Condiciones futuras requeridas
- Ruta canonica persistida en `public_profile_seo_routes`.
- `status=active`.
- `route_enabled=1`.
- Router SEO publico implementado.
- Canonical listo.
- `robots` debe permitir indexacion.
- Politica de redirects/aliases lista.

### C) Estado actual de doctor_id=1
- `enabled=false`.
- `can_route=false`.
- `route_url=null`.
- `route_type=profile`.
- `candidate_path=/aguascalientes/aguascalientes/medicos/dra-leticia-munoz-romo`.
- `current_url=/profiles/doctor.php?doctor_id=1`.
- `route_generation=candidate_only`.
- `requires.route_persisted=true`.
- `requires.status_active=false`.
- `requires.route_enabled=false`.
- `requires.seo_router_enabled=false`.
- `requires.canonical_ready=false`.
- `requires.robots_index_allowed=false`.
- `requires.redirect_policy_ready=false`.
- `blocking_reasons` incluye `status_candidate`, `route_disabled`, `seo_router_not_implemented`, `canonical_not_ready`, `robots_noindex_active` y `redirect_policy_not_implemented`.

### D) Limites vigentes
- La existencia de `canonical_path` candidato no implica ruta publica real.
- `route_url` permanece `null`.
- `status=candidate` bloquea la ruta.
- `route_enabled=false` bloquea la ruta.
- `seo_router_enabled=false` bloquea rutas SEO reales.
- `robots=noindex,nofollow` bloquea indexacion.
- `redirect_policy_ready=false` bloquea aliases y redirects 301.
- `public_url_context.route_enabled` permanece `false`.
- `public_canonical_route.can_route` permanece `false`.
- `canonical_render_guard.can_render` permanece `false`.
- `json_ld_render_guard.can_render` permanece `false`.
- `profile.canonical_url` permanece `null`.
- `seo.canonical_url` permanece `null`.
- `json_ld` real permanece `null`.
- `seo.breadcrumb` permanece `[]`.
- No se modifica SSR.
- No se modifica DB.
- No se modifica `.htaccess`.
- No se crea router.
- No se crean redirects.
- No se activa canonical.
- No se activa JSON-LD real.
- No se cambia `seo.robots`.

---

## Adenda PP-Decisiones 19 — SEO Activation Summary

### A) Campo del DTO
- El DTO publico expone `seo_activation_summary` como llave de primer nivel.
- La fuente del resumen es `seo_activation_guards`.
- Version actual: `seo-activation-summary-v1`.
- El objetivo es concentrar el estado global de activacion SEO del perfil publico.

### B) Componentes resumidos
- Ruta publica SEO desde `public_route_guard`.
- Canonical desde `canonical_render_guard`.
- JSON-LD desde `json_ld_render_guard`.
- Robots desde `seo.robots`.
- Breadcrumb visual y candidato JSON-LD desde `public_breadcrumbs`.
- Bloqueos principales para QA y futuras activaciones.

### C) Estado actual de doctor_id=1
- `overall_state=not_active`.
- `is_indexable=false`.
- `is_public_route_active=false`.
- `is_canonical_active=false`.
- `is_json_ld_active=false`.
- `robots=noindex,nofollow`.
- `current_url=/profiles/doctor.php?doctor_id=1`.
- `candidate_route=/aguascalientes/aguascalientes/medicos/dra-leticia-munoz-romo`.
- `active_url=null`.
- `components.route.state=blocked`.
- `components.canonical.state=blocked`.
- `components.json_ld.state=blocked`.
- `components.breadcrumbs.visual_render_enabled=true`.
- `components.breadcrumbs.json_ld_enabled=false`.
- `components.breadcrumbs.route_enabled=false`.
- `blocking_reasons` incluye `public_route_not_active`, `canonical_not_active`, `json_ld_not_active`, `robots_noindex_active` y `seo_router_not_implemented`.

### D) Limites vigentes
- La existencia de candidatos no implica activacion SEO.
- `robots=noindex,nofollow` bloquea indexacion.
- Los guards siguen bloqueando route, canonical y JSON-LD.
- `public_route_guard.can_route` permanece `false`.
- `canonical_render_guard.can_render` permanece `false`.
- `json_ld_render_guard.can_render` permanece `false`.
- `public_canonical_route.can_route` permanece `false`.
- `profile.canonical_url` permanece `null`.
- `seo.canonical_url` permanece `null`.
- `json_ld` real permanece `null`.
- `seo.breadcrumb` permanece `[]`.
- No se modifica SSR.
- No se modifica DB.
- No se modifica `.htaccess`.
- No se crea router.
- No se crean redirects.
- No se activa canonical.
- No se activa JSON-LD real.
- No se cambia `seo.robots`.

---

## Adenda PP-Decisiones 20 — Runbook de activación SEO pública

### A) Estado actual
- El estado global actual del SEO publico del perfil permanece en `overall_state=not_active`.
- `is_indexable=false`.
- `is_public_route_active=false`.
- `is_canonical_active=false`.
- `is_json_ld_active=false`.
- `robots=noindex,nofollow`.
- `active_url=null`.
- Actualmente existen solo capas preparatorias de contrato y diagnostico:
  - `public_canonical_route`.
  - `canonical_render_guard`.
  - `json_ld_render_guard`.
  - `public_route_guard`.
  - `seo_activation_summary`.

### B) Orden futuro de activacion
1. Router SEO real.
   - Primero debe existir un router publico real para URLs como `/aguascalientes/aguascalientes/medicos/dra-leticia-munoz-romo`.
   - La URL SEO debe resolver hacia el perfil correcto sin romper `/profiles/doctor.php?doctor_id=1`.
   - No se deben usar rutas candidatas como productivas antes de tener router.
   - No se deben cambiar `robots`, canonical, JSON-LD ni redirects en esta etapa.

2. Activación controlada de route_enabled.
   - Despues de implementar y probar el router se podra evaluar `public_profile_seo_routes.route_enabled=1`.
   - Requisitos minimos:
     - `status=active`.
     - Router probado.
     - Perfil resuelve por URL SEO.
     - No hay ambiguedad de slug.
     - No hay colision de `canonical_path`.
     - Fallback legacy sigue funcionando.

3. Canonical real.
   - Solo despues de router estable se podra evaluar `canonical_enabled=1`.
   - Esta etapa habilitaria, en una microfase futura, `canonical_render_guard.can_render=true`, `seo.canonical_url != null`, `profile.canonical_url != null` y `<link rel="canonical">`.
   - Requisitos minimos:
     - `route_enabled=1`.
     - `status=active`.
     - Router operativo.
     - Robots con indexacion permitida.
     - `canonical_path` validado.
     - URL canonica absoluta definida si aplica.

4. Robots index.
   - `robots=index,follow` solo debe evaluarse despues de validar router y canonical.
   - Cambiar robots antes de router y canonical puede exponer URLs inestables, duplicadas o candidatas.

5. JSON-LD real.
   - JSON-LD real debe activarse al final, despues de:
     - Canonical listo.
     - Robots con indexacion permitida.
     - `BreadcrumbList` validado.
     - Schema de perfil definido.
     - Renderer de script habilitado.
   - La activacion futura esperada seria `json_ld_render_guard.can_render=true`, `json_ld != null` y `<script type="application/ld+json">`.

6. Redirects / aliases / 301.
   - Redirects, aliases, historial de slugs y 301 deben vivir en una fase separada posterior.
   - No deben mezclarse con la primera activacion de router/canonical.

### C) Riesgos principales
- Indexar rutas candidatas antes de validar router.
- Usar slug singular o generizado de especialidad como canonical sin catalogo formal.
- Activar JSON-LD sin canonical real.
- Activar robots index antes de resolver duplicidad.
- Activar redirects sin historial de slugs.
- Cambiar `.htaccess` sin QA de rutas legacy.
- Confundir `candidate_path` con `active_url`.

### D) Checklist futuro de activacion
- [ ] Tabla `public_profile_seo_routes` con `status=active` para la entidad.
- [ ] `route_enabled=1` solo despues de router probado.
- [ ] `canonical_enabled=1` solo despues de `route_enabled`.
- [ ] `canonical_render_guard.can_render=true`.
- [ ] `public_route_guard.can_route=true`.
- [ ] `seo_activation_summary.is_public_route_active=true`.
- [ ] `seo_activation_summary.is_canonical_active=true`.
- [ ] `robots` cambia a `index,follow` solo despues de canonical.
- [ ] `json_ld_render_guard.can_render=true` solo al final.
- [ ] SSR renderiza canonical link solo cuando el guard lo permite.
- [ ] SSR renderiza JSON-LD solo cuando el guard lo permite.
- [ ] No hay POST reales de agenda, OTP ni reservas en QA SEO.
- [ ] No se rompen URLs legacy actuales.

### E) Limites vigentes
- Este runbook no implementa router.
- No modifica `.htaccess`.
- No crea redirects.
- No activa canonical.
- No activa JSON-LD real.
- No cambia `seo.robots`.
- No modifica SSR.
- No modifica DB.

---

## Adenda PP-Decisiones 21 — Ciclo de vida de suscripciones y vigencia contractual de planes

### A) Diagnostico actual
- No existe en el repo una tabla canonica de suscripciones de planes medicos.
- No existe tabla canonica de catalogo de planes comerciales conectada a backend.
- No existe tabla canonica de pagos de suscripcion de plataforma.
- No existe tabla canonica de aceptacion de contrato comercial para planes.
- No existen campos persistidos y canonicos para `contract_accepted_at`, `starts_at`, `expires_at`, `grace_starts_at` o `grace_ends_at`.
- No existe API real para contratar, renovar, cambiar o cancelar plan.
- No existe job programado de recordatorios, vencimiento, periodo de gracia o inactivacion por plan.
- La UI `p-suscripcion` actual existe como maqueta en `docs/index.html` y `docs/assets/js/app.js`; muestra plan, vigencia, renovacion, facturacion e historial con datos demo.
- `PublicProfilePlanCapabilities` resuelve capacidades publicas por `plan_code`, pero hoy opera como contrato/read-model transicional:
  - normaliza planes `free`, `basic`, `standard`, `optimum` y `professional`;
  - expone `expires_at=null`;
  - expone `grace_status=null`;
  - no consulta una suscripcion vigente real.
- `PublicProfileRepository` busca fuente de plan en columnas candidatas de `profiles_doctors` si existen: `plan_code`, `profile_plan`, `plan_name`, `subscription_plan` o `commercial_plan`.
- `profiles/doctor.php` conserva un selector temporal `mxmed_plan` solo para QA/dev local.
- `doctor_contact_points` ya contempla `visibility_plan_min`, pero eso es gating por visibilidad de contacto, no contrato de suscripcion ni vigencia comercial.

### B) Principio central
- Una mejora de plan no es solo un cambio visual ni un override de QA.
- Al contratar un plan mejorado debe generarse un registro formal de suscripcion con fechas inamovibles.
- La suscripcion vigente debe ser la fuente futura para calcular capacidades publicas, avisos, vencimiento, periodo de gracia e inactivacion.
- Las fechas contractuales deben quedar visibles para el usuario medico.

### C) Evento de contratacion
Cuando el usuario medico elige un plan mejorado:

1. Selecciona plan.
2. Revisa condiciones comerciales y contrato de plataforma.
3. Acepta contrato.
4. Se registra `contract_accepted_at`.
5. Se fija `starts_at`.
6. Se calcula `expires_at` segun duracion contratada.
7. Para plan anual, `expires_at = starts_at + 365 dias`.
8. Se guarda la suscripcion como `active`.
9. Las fechas `starts_at` y `expires_at` quedan visibles para el usuario.

### D) Campos minimos futuros
Campos conceptuales para una tabla futura de suscripciones, sin crear DB en esta fase:

- `subscription_id`.
- `doctor_id` / `profile_id` / `entity_id`.
- `plan_code`.
- `plan_label`.
- `billing_period`.
- `duration_days`.
- `contract_accepted_at`.
- `starts_at`.
- `expires_at`.
- `grace_starts_at`.
- `grace_ends_at`.
- `status`.
- `auto_renew`.
- `source`.
- `created_at`.
- `updated_at`.

### E) Estados minimos
- `draft`: seleccion o preparacion antes de aceptacion contractual.
- `active`: suscripcion vigente y beneficios aplicables.
- `expiring_soon`: ventana de aviso previa al vencimiento.
- `grace_period`: vencida pero dentro de periodo de gracia definido.
- `expired`: vencida y pendiente de resolver politica final.
- `inactive`: sin beneficios premium despues de vencimiento/gracia.
- `cancelled`: cancelada por usuario, soporte o regla administrativa.
- `renewed`: renovada y ligada a una vigencia posterior.

### F) Reglas de calculo
- `starts_at` se fija al momento valido de contratacion y aceptacion.
- `expires_at` se calcula una vez y no debe recalcularse dinamicamente por consultas de lectura.
- Para anualidad, la duracion base es 365 dias.
- `expires_at` debe mostrarse en UI al usuario medico.
- Los beneficios del plan dependen de `status`, fechas y politica de gracia.
- El sistema debe impedir que un usuario conserve capacidades premium despues de vencimiento y gracia.
- Las fechas contractuales no deben editarse manualmente desde UI comun.

### G) Acciones automaticas futuras
Antes de vencimiento:
- Recordatorio 30 dias antes.
- Recordatorio 15 dias antes.
- Recordatorio 7 dias antes.
- Recordatorio 1 dia antes.

En vencimiento:
- Marcar como `expired` o `grace_period` segun politica comercial vigente.

Durante periodo de gracia:
- Mostrar aviso persistente.
- Permitir renovacion.
- Limitar o mantener temporalmente capacidades segun politica.

Despues de gracia:
- Cambiar a `inactive`.
- Retirar capacidades premium.
- Conservar datos historicos.
- No borrar perfil, agenda, contactos, configuracion ni expediente.

### H) Relacion con perfil publico
- `PublicProfilePlanCapabilities` debe resolverse en el futuro desde la suscripcion vigente, no desde `mxmed_plan` ni solo desde columnas candidatas.
- El contrato futuro debe distinguir:
  - plan contratado;
  - suscripcion vigente;
  - periodo de gracia;
  - plan efectivo;
  - capacidades publicas.
- Si no hay suscripcion vigente, el plan efectivo debe caer a la politica definida para gratuito/inactivo.
- La agenda publica, contacto, reseñas, promociones, mapa GPS y otras capacidades deben depender del plan efectivo, no de una etiqueta visual.

### I) Reglas de seguridad y auditoria
- No borrar datos al vencer una suscripcion.
- No borrar contactos, agenda, configuracion ni historial clinico.
- Solo cambiar capacidades, visibilidad o acceso segun plan efectivo.
- Mantener historial contractual.
- Mantener evidencia de aceptacion de contrato.
- Mantener trazabilidad de fuente (`source`) y timestamps.
- No permitir edicion manual de fechas inamovibles desde UI comun.
- Cualquier ajuste administrativo debe auditar actor, motivo y fecha.

### J) Brechas para implementacion futura
- Definir schema canonico de suscripciones.
- Definir catalogo canonico de planes.
- Definir endpoint de contratacion/renovacion con aceptacion contractual.
- Definir integracion con pagos/facturacion sin mezclarla con perfil publico.
- Definir jobs de recordatorio, vencimiento, gracia e inactivacion.
- Conectar `PublicProfilePlanCapabilities` a la suscripcion vigente.
- Sustituir `mxmed_plan` por una fuente real fuera de QA/dev.

### K) Limites de esta adenda
- No crea tablas.
- No crea SQL.
- No modifica DB.
- No implementa API.
- No activa planes reales.
- No cambia capacidades productivas.
- No modifica SEO productivo.

---

## Adenda PP-Decisiones 22 — Decisión de schema para suscripciones de planes

### A) Contexto
- Actualmente no existe un modelo formal de suscripciones de planes.
- No existen tablas canonicas de suscripciones, planes, pagos/facturacion de plataforma ni aceptacion contractual.
- La UI `p-suscripcion` existe como maqueta/demo, sin API real de contratacion, renovacion, cancelacion o vigencia.
- `PublicProfilePlanCapabilities` aun no resuelve capacidades desde una suscripcion vigente.
- La decision funcional requiere vigencias contractuales inamovibles, visibles para el usuario y usadas como eje de beneficios, recordatorios, periodo de gracia e inactivacion.

### B) Decision tecnica
La primera fase futura de schema debe usar dos tablas:

- `subscription_plans`.
- `profile_subscriptions`.

Esta fase no crea SQL todavia. La decision queda como contrato tecnico previo a una microfase DB explicita.

### C) Justificacion
- Separa el catalogo de planes de los contratos/vigencias de suscripcion.
- Evita duplicar definicion de planes en cada suscripcion.
- Permite un modelo multi-entidad desde la primera fase.
- Permite que perfiles futuros usen la misma base: medico, dental, hospital, clinica, laboratorio, diagnostico, aseguradora, farmaceutica y servicio.
- Conserva la aceptacion contractual embebida en `profile_subscriptions` para la primera implementacion segura.
- Evita sobrediseno con eventos, aceptaciones multiples o pagos antes de tener el flujo base estable.

### D) Tabla `subscription_plans`
Proposito conceptual:
- Catalogo canonico de planes disponibles.
- Debe representar, como minimo, `free`, `basic`, `standard`, `optimum` y `professional`.
- Debe permitir activar/desactivar planes sin borrar historico.

Campos conceptuales minimos:
- `id`.
- `plan_code`.
- `plan_label`.
- `billing_period`.
- `duration_days`.
- `is_active`.
- `sort_order`.
- `source`.
- `created_at`.
- `updated_at`.

### E) Tabla `profile_subscriptions`
Proposito conceptual:
- Fuente canonica de suscripcion contratada o vigente por entidad publicable.
- Debe soportar medico, dental, hospital, clinica, laboratorio, diagnostico, aseguradora, farmaceutica y servicio.
- Para medico, debe respetar que `doctor_id` / `entity_id` puede ser `VARCHAR(64)`.

Identidad:
- `id`.
- `subscription_id`.
- `entity_type`.
- `entity_id`.
- `doctor_id`.
- `profile_id`.

Plan:
- `plan_code`.
- `plan_label`.
- `billing_period`.
- `duration_days`.
- `contracted_plan_code`.
- `effective_plan_code`.

Contrato:
- `contract_version`.
- `contract_accepted_at`.
- `contract_accepted_by_user_id`.
- `contract_acceptance_source`.
- `contract_acceptance_ip`.
- `contract_acceptance_user_agent`.

Vigencia:
- `starts_at`.
- `expires_at`.
- `grace_starts_at`.
- `grace_ends_at`.

Estado:
- `status`.
- `auto_renew`.
- `cancelled_at`.
- `renewed_from_subscription_id`.
- `renewed_to_subscription_id`.

Auditoria:
- `source`.
- `notes`.
- `created_at`.
- `updated_at`.
- `deleted_at`.

### F) Estados conceptuales
- `draft`.
- `active`.
- `expiring_soon`.
- `grace_period`.
- `expired`.
- `inactive`.
- `cancelled`.
- `renewed`.

Nota: `expiring_soon` puede ser estado calculado y no necesariamente persistido, segun se defina en la microfase de implementacion.

### G) Reglas de negocio
- Una suscripcion inicia como `draft` hasta aceptacion contractual valida.
- Al aceptar contrato se fija `contract_accepted_at`.
- `starts_at` se fija al contratar/aceptar.
- `expires_at` se calcula una sola vez.
- Anualidad base: 365 dias.
- Las fechas contractuales no deben recalcularse dinamicamente por lecturas.
- Renovaciones crean una nueva fila y enlazan con `renewed_from_subscription_id`.
- La fila anterior puede enlazarse con `renewed_to_subscription_id`.
- Cancelar no borra datos.
- Vencer no borra perfil, agenda, contactos, expediente ni configuracion.
- Despues de gracia se retiran capacidades premium segun `effective_plan_code`.

### H) Indices futuros sugeridos
Decision conceptual, sin SQL en esta fase:

- `subscription_id` unico.
- `entity_type + entity_id + status`.
- `entity_type + entity_id + starts_at + expires_at`.
- `plan_code + status`.
- `status + expires_at`.
- `status + grace_ends_at`.
- `renewed_from_subscription_id`.
- `renewed_to_subscription_id`.

La unicidad de una sola suscripcion vigente por entidad requiere validacion backend y/o estrategia MySQL posterior. Si se requiere enforcement fuerte, puede evaluarse columna generada o constraint compatible con el motor disponible.

### I) Elementos postergados
- `profile_subscription_events`.
- `profile_subscription_contract_acceptances`.
- Pagos/facturacion real.
- Recibos/facturas.
- Jobs de recordatorios.
- Integracion real con `PublicProfilePlanCapabilities`.
- Conexion UI `p-suscripcion`.
- Endpoint de contratacion.
- Endpoint de renovacion.
- Endpoint de cancelacion.

### J) Puntos pendientes de decision funcional
- Duracion exacta del periodo de gracia.
- Capacidades disponibles durante gracia.
- Corte exacto de `expires_at`: timestamp exacto o fin de dia local.
- Fallback post-vencimiento: `free`, `inactive` u otro.
- Relacion definitiva entre `doctor_id` y `profile_id` para tipos no medicos.
- Integracion futura con pagos y evidencia de pago.

### K) Limites de esta decision
- No crea SQL.
- No crea tablas.
- No modifica DB.
- No modifica PHP, JS, CSS ni UI.
- No activa planes reales.
- No cambia capacidades publicas.
- No modifica SEO productivo.

---

## Fuentes de referencia entregadas para este contrato
- 00-YA-FSD_Parcial_Perfiles_Medicos.pdf
- 00-YA-Funcionalidades por Tipo de Perfil.pdf
- 01FLUJO DE RECLAMO DE PERFILES GRATUITOS.pdf
- 01NORMATIVA DE DELEGABILIDAD Y VISIBILIDAD PERFIL MEDICO.pdf
- MODULO A2 SUSCRIPCION Y PLANES DE NEGOCIO GATING.pdf
- MODULO A3 NOTIFICACIONES Y TRIGGERS.pdf
- MODULO B1 PERFIL MEDICO INDIVIDUAL FSD DETALLADO.pdf
- FUNCIONES Y ROLES DE LOS AGENTES DE INTELIGENCIA ARTIFICIAL.pdf
- MODULOS DE INTELIGENCIA ARTIFICIAL CONTEMPLADOS.pdf
- INTEGRACION DE VIDEOCONSULTAS FLUJO TECNICO Y FINANCIERO.pdf
- SNIPPET DE PLANTILLA JSON-LD PARAMETRIZABLE.pdf
