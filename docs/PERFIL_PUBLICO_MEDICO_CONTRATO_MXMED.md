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

## Adenda PP-Decisiones 23 — Constraints y compatibilidad MySQL para suscripciones

### A) Contexto
- Esta adenda formaliza las decisiones tecnicas posteriores al draft `modules/profiles/db/2026_06_19_create_subscription_plan_lifecycle_draft.sql`.
- El objetivo es preparar una futura migracion ejecutable sin activar planes reales ni conectar backend, UI o capacidades publicas.
- Las decisiones aplican al modelo inicial `subscription_plans` + `profile_subscriptions`.

### B) FK hacia `subscription_plans`
Decision:
- No usar FK real en la primera migracion.
- Mantener indice y validacion backend por `plan_code + billing_period`.

Motivo:
- El catalogo comercial aun esta inmaduro.
- Evita bloquear migraciones tempranas por cambios de catalogo.
- Mantiene separacion entre definicion de planes y vigencias contractuales.
- Reduce acoplamiento operativo mientras pagos, UI y contratacion real siguen postergados.

### C) FK hacia entidad, medico o perfil
Decision:
- No usar FKs reales por ahora para `entity_type + entity_id`, `doctor_id` ni `profile_id`.
- `entity_type + entity_id` debe seguir como identificador multi-entidad.
- `doctor_id` y `profile_id` quedan como auxiliares nullable.

Motivo:
- No existe una tabla universal de entidades publicables.
- El modelo debe soportar medico, dental, hospital, clinica, laboratorio, diagnostico, aseguradora, farmaceutica y servicio.
- `doctor_id` puede ser `VARCHAR(64)` y no debe forzar una solucion solo para medicos.

### D) Unicidad de suscripcion vigente por entidad
Decision:
- Primera fase: validacion backend obligatoria para impedir multiples suscripciones vigentes por entidad.
- Estrategia DB futura: evaluar columna generada tipo `active_subscription_entity_key` con indice `UNIQUE`.
- Referencia conceptual: patron similar al usado en `doctor_contact_points` para unicidad condicional.
- No implementar todavia en esta adenda.

Implicacion:
- La futura migracion ejecutable debe documentar si deja esta unicidad solo en backend o si agrega una columna generada compatible con el motor disponible.

### E) Estados permitidos
Decision:
- Usar `VARCHAR(32)` con validacion backend.
- No usar `ENUM` inicial.
- No usar `CHECK` inicial.
- No crear tabla catalogo de estados todavia.

Estados conceptuales:
- `draft`.
- `active`.
- `expiring_soon`.
- `grace_period`.
- `expired`.
- `inactive`.
- `cancelled`.
- `renewed`.

Nota:
- `expiring_soon` debe ser preferentemente calculado y no persistido como estado contractual principal.

### F) `effective_plan_code`
Decision:
- Mantener `effective_plan_code` como snapshot/read-model en la suscripcion.
- No tratarlo como unica fuente de verdad permanente.
- En lecturas efectivas debe recalcularse o validarse contra `status`, `starts_at`, `expires_at`, `grace_starts_at` y `grace_ends_at`.

Contrato semantico:
- `contracted_plan_code` representa el plan contratado.
- `effective_plan_code` representa el plan que se debe aplicar al perfil publico en un momento dado.

Riesgo:
- Puede quedar stale si no se recalcula o valida durante la lectura.

### G) Tipo de fechas contractuales
Decision:
- Usar `DATETIME` para:
  - `contract_accepted_at`.
  - `starts_at`.
  - `expires_at`.
  - `grace_starts_at`.
  - `grace_ends_at`.
  - `cancelled_at`.
  - `deleted_at`.
- Mantener timestamps tecnicos `created_at` y `updated_at` como `TIMESTAMP`.

Motivo:
- Evita conversion implicita de zona horaria de `TIMESTAMP` en fechas contractuales.
- Conserva fechas inamovibles y visibles para el usuario medico.

### H) Soft delete
Decision:
- Mantener `deleted_at` desde la primera fase.

Motivo:
- Preserva historial contractual.
- Evita borrado accidental.
- El borrado logico no debe borrar perfil, agenda, contactos, expediente ni configuracion.

### I) Pagos y facturacion
Decision:
- No incluir campos de pago en la primera migracion ejecutable.
- No incluir todavia `payment_status`, `payment_reference` ni `billing_account_id`.

Motivo:
- Pagos y facturacion requieren modelo separado, evidencia propia y QA dedicado.
- Quedan postergadas tablas de pagos, recibos, facturas y evidencia de pago.

### J) Seeds de planes
Decision:
- La migracion ejecutable de tablas no debe incluir seeds.
- Crear seed idempotente separado posterior para:
  - `free`.
  - `basic`.
  - `standard`.
  - `optimum`.
  - `professional`.

Motivo:
- Separa estructura de datos base y datos iniciales.
- Evita activar planes por accidente durante la creacion de tablas.

### K) Archivo ejecutable futuro
Decision:
- Conservar el draft:
  - `modules/profiles/db/2026_06_19_create_subscription_plan_lifecycle_draft.sql`.
- Crear archivo ejecutable futuro separado:
  - `modules/profiles/db/2026_06_19_create_subscription_plan_lifecycle.sql`.
- No renombrar el draft.
- No crear el ejecutable en esta microfase.

### L) Implicaciones para el SQL ejecutable futuro
El SQL ejecutable futuro debe:
- Mantener `subscription_plans`.
- Mantener `profile_subscriptions`.
- Mantener campos contractuales y de vigencia.
- Mantener indices base.
- Mantener `deleted_at`.
- No incluir seeds.
- No incluir pagos.
- No conectar aun backend, UI ni capacidades publicas.
- Documentar claramente cualquier estrategia de unicidad vigente.

### M) Limites de esta adenda
- No crea SQL.
- No edita el draft SQL.
- No modifica DB.
- No ejecuta SQL.
- No modifica backend, UI ni capacidades publicas.
- No activa planes reales.
- No modifica SEO productivo.

---

## Adenda PP-Decisiones 24 — Plan gratuito permanente y fallback post-vencimiento

### A) Regla funcional
- El plan Gratuito / `free` no tiene limite de tiempo.
- El plan Gratuito / `free` no vence.
- El plan Gratuito / `free` es el plan base permanente de la plataforma.
- Miles de perfiles iniciales pueden estar en plan gratuito.
- No deben crearse suscripciones contractuales masivas para todos los perfiles gratuitos.
- Un perfil puede operar como gratuito sin fila activa en `profile_subscriptions`.

### B) Representacion recomendada en catalogo
En `subscription_plans`, el plan gratuito debe representarse como:

- `plan_code = free`.
- `plan_label = Gratuito`.
- `billing_period = lifetime`.
- `duration_days = 0`.
- `is_active = 1`.
- `sort_order = 10`.

Motivo:
- `free` no corresponde a una anualidad.
- `duration_days=0` expresa que no hay ventana contractual de vencimiento.
- `billing_period=lifetime` distingue el plan base permanente de los planes pagados anuales.

### C) Planes pagados iniciales
Los planes pagados iniciales conservan:

- `billing_period = annual`.
- `duration_days = 365`.

Aplica para:
- `basic`.
- `standard`.
- `optimum`.
- `professional`.

### D) Relacion con `profile_subscriptions`
- No deben crearse filas en `profile_subscriptions` para todos los perfiles gratuitos.
- `profile_subscriptions` debe reservarse para eventos contractuales de suscripcion, contratacion, aceptacion, vigencia, renovacion, cancelacion o historial relevante.
- Un perfil gratuito puede existir sin suscripcion contractual activa.
- La ausencia de suscripcion vigente debe resolverse como plan efectivo gratuito, salvo que una politica futura indique otro fallback.

### E) Fallback post-vencimiento
Cuando un perfil con plan pagado vence y termina su periodo de gracia:

- El plan efectivo debe caer a `free`.
- El plan contratado historico debe conservarse en la suscripcion vencida o inactiva.
- No deben borrarse perfil, agenda, contactos, configuracion, expediente ni historial.
- Solo deben retirarse capacidades premium segun `effective_plan_code=free`.

### F) Plan contratado vs plan efectivo
- `contracted_plan_code` conserva el plan que el medico contrato.
- `effective_plan_code` refleja el plan aplicable en lectura.
- Si una suscripcion pagada vencio y ya no esta en gracia:
  - `contracted_plan_code` puede seguir siendo `standard`, `optimum`, `professional` u otro plan pagado historico;
  - `effective_plan_code` debe resolverse como `free`.
- `effective_plan_code` debe calcularse o validarse en lectura contra `status`, `starts_at`, `expires_at`, `grace_starts_at` y `grace_ends_at`.

### G) Correccion pendiente del seed
Queda pendiente una microfase posterior para corregir:

- `modules/profiles/db/2026_06_19_seed_subscription_plans_catalog.sql`.

Correccion requerida:
- Cambiar solo `free` de `annual/365` a `lifetime/0`.
- Mantener los planes pagados como `annual/365`.
- Reaplicar el seed idempotentemente en DB local.
- Validar que `subscription_plans` siga con 5 filas.
- Validar que `profile_subscriptions` siga en 0.

### H) Que no cambia todavia
Esta decision no implica todavia:

- Conectar backend.
- Conectar UI.
- Conectar `PublicProfilePlanCapabilities`.
- Activar capacidades desde DB.
- Crear suscripciones reales.
- Crear pagos.
- Crear facturacion.
- Activar SEO productivo.

### I) Limites de esta adenda
- No modifica DB.
- No ejecuta SQL.
- No edita seed.
- No edita schema.
- No crea SQL nuevo.
- No modifica backend, UI ni capacidades publicas.
- No activa planes reales.
- No modifica SEO productivo.

---

## Adenda PP-Decisiones 25 — Read-model de suscripción vigente y API de sólo lectura

### A) Objetivo del read-model
- El sistema necesita un read-model central para resolver la suscripcion vigente y el plan efectivo de una entidad publicable.
- El read-model debe concentrar:
  - suscripcion vigente;
  - plan contratado;
  - plan efectivo;
  - vigencia contractual;
  - estado;
  - periodo de gracia;
  - fallback gratuito permanente;
  - contexto que luego podran usar perfil publico, UI de Suscripcion y APIs futuras.
- Esta decision no conecta todavia backend, UI ni capacidades publicas.

### B) Nombre conceptual y ubicacion futura
Nombre sugerido:

- `CurrentSubscriptionReadModel`.

Ubicacion futura sugerida:

- `modules/subscriptions/`.

Componentes futuros posibles:

- `CurrentSubscriptionRepository`.
- `CurrentSubscriptionService`.
- `SubscriptionPlanCatalogRepository`.
- Controller API en `api/subscriptions/index.php`.

Estos archivos no se crean en esta fase.

### C) Campos conceptuales recomendados
El read-model debe exponer, como minimo:

- `entity_type`.
- `entity_id`.
- `contracted_plan_code`.
- `effective_plan_code`.
- `plan_label`.
- `billing_period`.
- `duration_days`.
- `status`.
- `contract_accepted_at`.
- `starts_at`.
- `expires_at`.
- `grace_starts_at`.
- `grace_ends_at`.
- `grace_status`.
- `is_free_fallback`.
- `is_paid_plan`.
- `is_active`.
- `is_expired`.
- `is_in_grace`.
- `days_until_expiration`.
- `source`.
- `version`.

### D) Caso sin suscripcion
Si no existe una suscripcion real en `profile_subscriptions`, el read-model debe devolver el plan gratuito permanente desde `subscription_plans`:

- `effective_plan_code = free`.
- `plan_label = Gratuito`.
- `billing_period = lifetime`.
- `duration_days = 0`.
- `status = free_default`.
- `expires_at = null`.
- `grace_status = null`.
- `source = subscription_plans/default_free`.

Notas:
- No debe crearse fila automatica en `profile_subscriptions`.
- `is_free_fallback` puede ser `false` para el caso base sin suscripcion o ajustarse segun politica de lectura; debe distinguirse del fallback posterior a una suscripcion vencida.
- La ausencia de suscripcion contractual no es error para perfiles gratuitos.

### E) Caso plan pagado vigente
Si existe una suscripcion pagada activa y dentro de vigencia:

- `contracted_plan_code` conserva el plan contratado.
- `effective_plan_code` debe ser el plan contratado aplicable.
- `is_paid_plan = true`.
- `is_active = true`.
- `expires_at` debe ser visible para el usuario medico.
- `days_until_expiration` puede calcularse en lectura.
- Las fechas contractuales no deben recalcularse dinamicamente.

### F) Caso vencido en gracia
Si una suscripcion pagada vencio pero sigue dentro del periodo de gracia:

- Debe aplicarse la politica de gracia pendiente de definicion funcional.
- `is_in_grace = true`.
- `grace_status = active` o valor equivalente.
- No deben borrarse datos.
- No deben recalcularse `starts_at`, `expires_at`, `grace_starts_at` ni `grace_ends_at`.
- La politica exacta de capacidades durante gracia queda pendiente para una microfase posterior.

### G) Caso vencido fuera de gracia
Si una suscripcion pagada vencio y ya no esta en gracia:

- `contracted_plan_code` conserva el plan historico contratado.
- `effective_plan_code = free`.
- `is_free_fallback = true`.
- `is_expired = true`.
- No deben borrarse perfil, agenda, contactos, expediente, configuracion ni historial.
- Deben retirarse capacidades premium segun la lectura efectiva del plan `free`.

### H) Algoritmo conceptual
1. Buscar la suscripcion relevante por `entity_type + entity_id`, excluyendo filas con `deleted_at`.
2. Si no existe suscripcion, devolver el plan `free` desde `subscription_plans`.
3. Si existe suscripcion activa y vigente, aplicar el plan contratado.
4. Si existe suscripcion vencida dentro de gracia, aplicar la politica de gracia.
5. Si existe suscripcion vencida fuera de gracia, resolver `effective_plan_code=free`.
6. Nunca crear fila `free` automatica para perfiles gratuitos.
7. Nunca borrar datos al vencer una suscripcion.
8. Separar siempre `contracted_plan_code` de `effective_plan_code`.

### I) Relacion con `PublicProfilePlanCapabilities`
- `PublicProfilePlanCapabilities` no debe consultar DB directamente.
- Debe recibir `effective_plan_code` ya resuelto.
- En una fase posterior podria recibir tambien:
  - `expires_at`;
  - `grace_status`;
  - `subscription_context`.
- No se modifican capacidades en esta fase.
- No se conecta productivamente todavia.

### J) Relacion con `PublicProfileRepository` y DTO publico
- `PublicProfileRepository` hoy usa fallback desde columnas legacy de `profiles_doctors`: `plan_code`, `profile_plan`, `plan_name`, `subscription_plan` y `commercial_plan`.
- `PublicProfileRepository` no debe contener toda la logica contractual.
- En el futuro, el controller o un servicio intermedio debe consultar el read-model y enriquecer el DTO.
- El DTO publico podria exponer un bloque seguro `subscription_context`.
- Ese bloque no debe exponer datos sensibles como:
  - IP de aceptacion;
  - user-agent de aceptacion;
  - datos privados del contrato;
  - datos de pago;
  - referencias internas de facturacion.

### K) Override DEV `mxmed_plan`
- `mxmed_plan` debe seguir existiendo solo como override local/dev.
- No debe confundirse con una suscripcion contractual.
- Debe marcarse como `dev_override`.
- En produccion no debe ser fuente de verdad.
- El read-model real debe prevalecer fuera de entornos controlados.

### L) API futura de solo lectura
Ruta recomendada:

- `GET /api/subscriptions/index.php/entities/{entity_type}/{entity_id}/current`.

Contrato esperado:
- Debe ser autenticada.
- No debe ser publica.
- Debe ser multi-entidad.
- Debe validar scope del usuario sobre la entidad.
- Debe estar separada de `api/profiles`.
- No debe exponer datos contractuales sensibles.

Alternativas descartadas:
- `GET /api/profiles/index.php/me/subscription`: util como alias futuro para UI, pero no como nucleo multi-entidad.
- `GET /api/profiles/index.php/doctors/{doctor_id}/subscription`: acopla el contrato a medicos.
- Exponerlo dentro del endpoint publico de perfil: riesgo de exposicion publica de datos privados.

### M) UI `p-suscripcion`
- La UI `p-suscripcion` actual es maqueta/demo.
- No tiene API real.
- Primero necesita endpoint de solo lectura para mostrar plan, vigencia, gracia y estado efectivo.
- La escritura de contratacion, renovacion y cancelacion queda para fases posteriores.
- La aceptacion contractual queda para fases posteriores.

### N) Riesgos de integracion temprana
- Activar premium desde `plan_code` legacy.
- Confundir plan contratado con plan efectivo.
- Tratar `free` como anual.
- Crear suscripciones gratuitas masivas.
- Consultar DB desde `PublicProfilePlanCapabilities`.
- Exponer datos privados de suscripcion en perfil publico.
- Activar capacidades antes de tener reglas de vencimiento y gracia.
- Acoplar la solucion solo a medicos y romper multi-entidad futura.

### O) Que no se activa todavia
Esta decision no activa:

- Endpoints reales.
- Backend de suscripciones.
- UI real.
- Contratacion.
- Renovacion.
- Cancelacion.
- Aceptacion de contrato.
- Capacidades desde DB.
- SEO productivo.
- Cambios en perfil publico.

### P) Limites de esta adenda
- No modifica codigo.
- No modifica DB.
- No ejecuta SQL.
- No crea endpoints.
- No conecta backend.
- No toca UI.
- No cambia capacidades publicas.
- No modifica SEO productivo.

---

## Adenda PP-Decisiones 26 — Endpoint privado de sólo lectura para suscripción actual

### A) Endpoint implementado
Ruta implementada:

- `GET /api/subscriptions/index.php/entities/{entity_type}/{entity_id}/current`.

Proposito:
- Exponer el `CurrentSubscriptionReadModel`.
- Devolver la suscripcion actual y el plan efectivo de una entidad.
- Operar como endpoint privado de solo lectura.
- No crear, modificar ni borrar suscripciones.
- No activar capacidades productivas.

### B) Archivo implementado y dependencias
Archivo implementado:

- `api/subscriptions/index.php`.

Dependencias usadas:
- `modules/subscriptions/repositories/CurrentSubscriptionRepository.php`.
- `modules/subscriptions/services/CurrentSubscriptionReadModelService.php`.
- `api/_lib/db.php` para obtener la conexion local/proyecto mediante `mxmed_pdo`.

### C) Formato de respuesta exitosa
Formato conceptual:

```json
{
  "ok": true,
  "data": {
    "...": "read-model"
  },
  "meta": {
    "...": "contexto de ejecucion"
  }
}
```

Ejemplo conceptual para entidad sin suscripcion:

- `entity_type = doctor`.
- `entity_id = 1`.
- `effective_plan_code = free`.
- `plan_label = Gratuito`.
- `billing_period = lifetime`.
- `duration_days = 0`.
- `status = free_default`.
- `expires_at = null`.
- `source = subscription_plans.default_free`.
- `version = current-subscription-readmodel-v1`.

### D) Formato de error
Formato conceptual:

```json
{
  "ok": false,
  "error": {
    "code": "...",
    "message": "..."
  },
  "data": null,
  "meta": {
    "...": "contexto de ejecucion"
  }
}
```

Errores validados:
- `401` sin autorizacion fuera de entorno local.
- `403` por mismatch de scope.
- `404` por ruta invalida.
- `405` por metodo no permitido.
- `422` por request invalido.

### E) Auth y guard
- El endpoint es privado.
- En entorno local permite `local_dev_open` solamente para QA local.
- `local_dev_open` queda acotado a:
  - `127.0.0.1`.
  - `localhost`.
  - `::1`.
- Fuera de local, sin usuario o scope, responde `401`.
- Si hay mismatch de doctor o entidad, responde `403`.
- Antes de exponer en entorno real debe endurecerse con:
  - `MXMED_SUBSCRIPTIONS_PRIVATE_AUTH_REQUIRED=1`;
  - o la politica final de autenticacion/scope que defina el proyecto.

### F) Seguridad y privacidad
El endpoint no debe exponer:

- `contract_acceptance_ip`.
- `contract_acceptance_user_agent`.
- Datos de pago.
- Datos privados sensibles.
- Informacion publica SEO.
- Capacidades calculadas para perfil publico.

Puede exponer timestamps contractuales minimos porque es un endpoint privado, pero no debe convertirse en fuente publica.

### G) Relacion con perfil publico
- No esta conectado a `profiles/doctor.php`.
- No esta conectado a `api/profiles/index.php`.
- No modifica `PublicProfileRepository`.
- No modifica `PublicProfilePlanCapabilities`.
- No activa capacidades publicas.
- No cambia SSR publico.
- No cambia SEO productivo.

### H) Casos validados
QA validado:

- `GET /api/subscriptions/index.php/entities/doctor/1/current` en local devuelve fallback `free_default`.
- `POST` sobre la misma ruta devuelve `405`.
- Ruta incompleta devuelve `404`.
- `entity_type` invalido devuelve `422`.
- `entity_id` raro devuelve `422`.
- Entidad inexistente devuelve fallback `free_default` sin crear registros.
- Host no-local sin auth devuelve `401`.
- Mismatch de `X-Doctor-Id` devuelve `403`.
- Mismatch de `X-Entity-Type` / `X-Entity-Id` devuelve `403`.
- `subscription_plans` permanece en `5`.
- `profile_subscriptions` permanece en `0`.

### I) Pendientes
- Endurecer auth/scope antes de uso productivo.
- Crear QA con sesion real.
- Conectar UI `p-suscripcion` en fase posterior.
- Crear endpoint de contratacion en fase posterior.
- Crear aceptacion contractual en fase posterior.
- Crear renovacion y cancelacion en fases posteriores.
- Conectar capacidades publicas solo despues de resolver reglas de vencimiento y gracia.
- No conectar todavia perfil publico.

### J) Que no se activa todavia
Esta decision no activa:

- Planes reales.
- Capacidades premium.
- Perfil publico.
- UI real de suscripcion.
- Contratacion.
- Renovacion.
- Cancelacion.
- Pagos.
- Facturacion.
- SEO productivo.

### K) Limites de esta adenda
- No modifica DB.
- No ejecuta SQL.
- No crea endpoints adicionales.
- No modifica PHP, JS, CSS ni UI.
- No modifica `PublicProfilePlanCapabilities`.
- No modifica `PublicProfileRepository`.
- No modifica perfil publico.
- No activa planes reales.
- No cambia capacidades productivas.
- No modifica SEO productivo.

---

## Adenda PP-Decisiones 27 — Hardening de auth y scope para endpoint privado de suscripciones

### A) Estado actual del endpoint
Endpoint existente:

- `GET /api/subscriptions/index.php/entities/{entity_type}/{entity_id}/current`.

Estado validado:
- Es un endpoint privado de solo lectura.
- No modifica DB.
- No crea suscripciones.
- No crea filas `free`.
- No activa capacidades productivas.
- No conecta perfil publico.
- No conecta UI.
- Usa `local_dev_open` para QA local.
- Responde `401` fuera de local cuando no hay identidad.
- Responde `403` cuando hay mismatch de scope.

### B) Politica `local_dev_open`
- `local_dev_open` existe solo para QA local controlado.
- Debe estar limitado a:
  - `127.0.0.1`.
  - `localhost`.
  - `::1`.
- No debe tratarse como seguridad productiva.
- No debe habilitarse en staging ni produccion.
- Debe poder bloquearse con modo estricto antes de cualquier uso real.

### C) Politica estricta
Politica futura:

- `MXMED_SUBSCRIPTIONS_PRIVATE_AUTH_REQUIRED=1`.

Cuando este activa:
- No se permite `local_dev_open`.
- Se exige identidad real.
- Se exige scope valido.
- Sin identidad debe responder `401`.
- Con identidad pero sin scope suficiente debe responder `403`.
- Los headers QA no deben bastar por si solos salvo entorno local controlado.

### D) Headers QA
Headers actualmente reconocidos por el endpoint:

- `X-User-Id`.
- `X-Doctor-Id`.
- `X-Entity-Type`.
- `X-Entity-Id`.

Regla:
- Solo deben permitirse para QA local/dev.
- No deben ser fuente productiva de verdad.
- En modo estricto deben ignorarse o requerir validacion adicional.
- No deben permitir acceso si no hay sesion real o scope real suficiente.

### E) Sesion real
La fuente primaria futura debe ser la sesion real del sistema, usando patrones existentes cuando se formalicen:

- `user_id`.
- `mxmed_user_id`.
- `auth_user_id`.
- `doctor_id`.
- `active_doctor_id`.
- `mxmed_doctor_id`.

Decision:
- Aun falta un helper central reutilizable de autenticacion/scope.
- El endpoint de suscripciones no debe depender de headers QA como identidad productiva.
- La sesion real debe resolver usuario, doctor principal, operador y alcance autorizado antes de conectar UI real.

### F) Scope doctor
Para `entity_type=doctor`:

- El doctor principal solo puede consultar su propio `doctor_id`.
- Un operador solo puede consultar un `doctor_id` autorizado.
- Un mismatch debe responder `403`.
- Un admin interno podria tener alcance amplio si existe un rol futuro.
- No se debe asumir admin global todavia.

### G) Scope operador
Los operadores deben validarse contra doctor o entidad autorizada.

Aprendizajes reutilizables de agenda:
- `actor_role`.
- `operator_id`.
- `actorContext`.
- Roles normalizados.

Decision:
- La UI `p-suscripcion` no debe conectarse para uso real hasta que exista scope suficiente para doctor/operador.
- El scope de operador debe poder distinguir operador autorizado, doctor propietario y futuros roles internos.

### H) Scope multi-entidad
Para futuros tipos:

- `dental`.
- `hospital`.
- `clinic`.
- `laboratory`.
- `diagnostic`.
- `insurer`.
- `pharmaceutical`.
- `service`.

Regla:
- Cada tipo requiere resolver ownership/scope especifico antes de habilitar acceso real.
- El endpoint puede conservar contrato multi-entidad, pero no debe asumir que todo scope equivale a `doctor_id`.

### I) Datos permitidos y prohibidos
Datos permitidos en el endpoint privado de lectura:

- `effective_plan_code`.
- `plan_label`.
- `billing_period`.
- `duration_days`.
- `status`.
- `starts_at`.
- `expires_at`.
- `grace_status`.

Datos prohibidos:

- `contract_acceptance_ip`.
- `contract_acceptance_user_agent`.
- Datos de pago.
- Datos administrativos privados.
- Datos SEO publicos.
- Capacidades publicas productivas calculadas.

### J) UI `p-suscripcion`
- No debe conectarse para uso real antes del hardening.
- Puede conectarse solo en QA local controlado si se autoriza explicitamente.
- Antes de uso real debe existir guard estricto.
- La primera integracion UI debe ser solo lectura.
- La contratacion, renovacion, cancelacion y aceptacion contractual quedan fuera de esta decision.

### K) Secuencia recomendada
Secuencia segura:

1. `DOCS-Suscripciones-PrivateEndpoint-AuthHardening-01`.
2. `BE-Suscripciones-PrivateEndpoint-StrictAuthFlag-01`.
3. `QA-Suscripciones-PrivateEndpoint-StrictAuthFlag-01`.
4. `BE/DIAG-Suscripciones-PrivateEndpoint-SessionScopeGuard-01`.
5. `FE-Suscripciones-PanelReadOnly-ConnectEndpoint-01`.

### L) Que no se activa todavia
Esta adenda no activa:

- UI real.
- Contratacion.
- Renovacion.
- Cancelacion.
- Aceptacion contractual.
- Capacidades productivas.
- Perfil publico.
- SEO productivo.
- Pagos.
- Facturacion.

### M) Limites de esta adenda
- No modifica DB.
- No ejecuta SQL.
- No modifica `api/subscriptions/index.php`.
- No crea endpoints.
- No modifica PHP, JS, CSS ni UI.
- No modifica `PublicProfilePlanCapabilities`.
- No modifica `PublicProfileRepository`.
- No cambia capacidades productivas.
- No modifica SEO productivo.

---

## Adenda PP-Decisiones 28 — Decision de guard sesion/scope para endpoint privado de suscripciones

### A) Contexto
Ya existe el endpoint privado de solo lectura:

- `GET /api/subscriptions/index.php/entities/{entity_type}/{entity_id}/current`.

Tambien existe la bandera estricta:

- `MXMED_SUBSCRIPTIONS_PRIVATE_AUTH_REQUIRED`.

Estado validado:
- El endpoint ya paso QA post-push.
- El endpoint sigue aislado.
- No esta conectado a UI.
- No esta conectado a perfil publico.
- No esta conectado a `PublicProfilePlanCapabilities`.
- No activa capacidades productivas.
- No modifica DB.
- No crea suscripciones.

### B) Diagnostico aprobado
El diagnostico `DIAG-Suscripciones-PrivateEndpoint-SessionScopeGuard-01` concluye:

- No existe un helper central reutilizable de auth/scope.
- Existen patrones parciales en perfiles y agenda.
- Agenda maneja `actorContext`, `actor_role`, `operator_id` y `doctor_id` activo.
- Agenda ya tiene permisos de operador, pero no existe todavia un permiso especifico `subscriptions.read`.
- El ownership multi-entidad no esta formalizado.
- El guard actual basta para QA local.
- El guard actual no basta para uso real/productivo de UI.

### C) Decision funcional
Decisiones obligatorias antes de integrar UI real:

- No conectar `p-suscripcion` todavia.
- No conectar UI real al endpoint hasta tener guard real.
- No conectar capacidades publicas a suscripciones hasta resolver scope real.
- No confiar en headers QA como seguridad productiva.
- Permitir headers QA solo en local/dev y pruebas controladas.
- Con `strict ON`, bloquear `local_dev_open`.
- Exigir identidad y scope suficiente para leer suscripcion actual.
- El medico principal solo puede consultar su propio `doctor_id`.
- Un operador solo podra consultar si pertenece al doctor y tiene permiso explicito futuro.
- La multi-entidad queda bloqueada para uso real hasta definir ownership formal por tipo.

### D) Matriz conceptual de acceso
Caso A - Medico principal:
- Permitir `entity_type=doctor`.
- Permitir `entity_id` igual al `doctor_id` activo de sesion.
- Bloquear `doctor_id` ajeno.
- Bloquear `entity_id` ajeno.
- Bloquear `entity_type` no soportado.

Caso B - Operador autorizado:
- Permitir solo si `operator_id` esta activo.
- Permitir solo si pertenece al doctor solicitado.
- Permitir solo si tiene permiso futuro `subscriptions.read` o equivalente.
- Permitir solo si el scope coincide con doctor/entity solicitado.
- Bloquear operador de otro doctor.
- Bloquear operador sin permiso.
- Bloquear mismatch de doctor/entity.

Caso C - Headers QA:
- Permitir solo en local/dev.
- Permitir solo para pruebas controladas.
- Bloquear su uso como seguridad productiva.
- Bloquear host no-local con solo headers QA.

Caso D - Multi-entidad futura:
- Bloquear por ahora `clinic`.
- Bloquear por ahora `hospital`.
- Bloquear por ahora `laboratory`.
- Bloquear por ahora `insurer`.
- Bloquear por ahora `pharmaceutical_lab` y cualquier equivalente `pharmaceutical` sin ownership formal.
- Bloquear cualquier otra entidad sin ownership formal.

### E) Riesgos documentados
Riesgos antes de conectar cualquier UI:

- Confiar solo en `X-User-Id`.
- Confiar en headers QA fuera de local.
- No validar ownership usuario-doctor.
- No distinguir medico principal de operador.
- No tener permiso futuro `subscriptions.read`.
- Conectar `p-suscripcion` antes del guard real.
- Exponer suscripcion de una entidad ajena.
- Activar capacidades desde un scope incompleto.
- Habilitar multi-entidad sin ownership formal.

### F) Secuencia recomendada
Secuencia segura recomendada:

1. Documentar esta decision.
2. Implementar guard minimo real para endpoint privado de suscripciones.
3. Ejecutar QA del guard real sin conectar UI.
4. Solo despues diagnosticar integracion read-only de `p-suscripcion`.
5. Mantener bloqueada contratacion, renovacion, cancelacion y pagos hasta microfases posteriores.
6. Mantener bloqueada la conexion con `PublicProfilePlanCapabilities` hasta resolver plan efectivo productivo y scope suficiente.

### G) Limites de esta adenda
Esta adenda no implica:

- Implementacion nueva en backend.
- Modificacion de `api/subscriptions/index.php`.
- Conexion de `p-suscripcion`.
- Conexion de perfil publico.
- Conexion de `PublicProfilePlanCapabilities`.
- Activacion de capacidades productivas.
- Soporte real multi-entidad.
- Creacion de permisos `subscriptions.read`.
- Cambios de DB.
- Ejecucion SQL.
- Cambios SEO productivos.

---

## Adenda PP-Decisiones 29 — Cierre del guard sesion/scope para endpoint privado de suscripciones

### A) Contexto
Ya existe el endpoint privado de solo lectura:

- `GET /api/subscriptions/index.php/entities/{entity_type}/{entity_id}/current`.

Tambien existen:

- Read-model de suscripcion actual.
- Bandera estricta `MXMED_SUBSCRIPTIONS_PRIVATE_AUTH_REQUIRED`.

Estado de integracion:
- El endpoint no esta conectado todavia a UI real.
- El endpoint no esta conectado a `p-suscripcion`.
- El endpoint no esta conectado a perfil publico.
- El endpoint no esta conectado a `PublicProfilePlanCapabilities`.
- El endpoint no activa capacidades productivas.

### B) Implementacion cerrada
En el commit `3173ce8 fix(suscripciones): refuerza guard de sesion del endpoint privado` se reforzo el guard del endpoint privado con:

- Sesion como fuente primaria.
- `session_scope` para sesion valida.
- Medico principal limitado a su propio `doctor_id`.
- Headers QA limitados a local/dev.
- `local_dev_open` solo con strict OFF y host local.
- Strict ON bloqueando `local_dev_open`.
- `X-User-Id` solo sin autorizar.
- `401` sin identidad valida.
- `403` con identidad pero scope insuficiente, mismatch o operador sin permiso.
- Operador bloqueado si no tiene permiso explicito futuro o equivalente.
- Soporte de operador con permiso explicito si la estructura de sesion lo provee.
- Meta conservando `auth_mode` y `strict_auth_required`.

### C) QA post-implementacion cerrado
La microfase `QA-Suscripciones-PrivateEndpoint-SessionScopeGuard-PostImplementacion-01` cerro con PASS y sin cambios.

Resumen de QA:
- Rama limpia y alineada.
- HEAD `3173ce8`.
- Lint PASS en:
  - `api/subscriptions/index.php`.
  - `CurrentSubscriptionRepository.php`.
  - `CurrentSubscriptionReadModelService.php`.
- Endpoint sigue siendo solo lectura.
- Sin escrituras SQL.
- DB intacta:
  - `subscription_plans = 5`.
  - `profile_subscriptions = 0`.
  - `free = Gratuito / lifetime / 0`.

Strict OFF:
- GET local respondio HTTP 200.
- `auth_mode=local_dev_open`.
- `strict_auth_required=false`.

Strict ON:
- Sin headers/sesion respondio HTTP 401.
- Headers validos locales respondieron HTTP 200 con `auth_mode=header_scope`.
- `X-User-Id` solo respondio HTTP 403.
- Doctor mismatch respondio HTTP 403.
- Entity mismatch respondio HTTP 403.
- Host no-local con headers QA respondio HTTP 401.

Sesion simulada:
- Medico principal valido respondio HTTP 200 con `auth_mode=session_scope`.
- Doctor mismatch respondio HTTP 403.
- Sin doctor scope respondio HTTP 403.
- Operador sin permiso respondio HTTP 403.
- Operador con permiso explicito respondio HTTP 200 con `auth_mode=session_scope`, cuando la estructura de sesion lo provee.

HTTP general:
- POST respondio HTTP 405.
- Ruta invalida no respondio 500.
- Entity type invalido respondio HTTP 422.
- Entity id raro no respondio 500.
- Entidad inexistente en strict OFF devolvio fallback `free_default`.
- DB post-QA siguio sin cambios.

### D) Estado funcional final
Queda permitido:

- QA local controlado.
- Consulta privada read-only con sesion valida y scope suficiente.
- Consulta local/dev con headers QA validos bajo reglas estrictas.
- Consulta de medico principal solo para su propio `doctor_id`.
- Consulta de operador solo si existe permiso explicito en sesion.

Sigue bloqueado:

- `p-suscripcion` real.
- UI real.
- `PublicProfilePlanCapabilities`.
- Perfil publico.
- Capacidades productivas.
- Contratacion real.
- Aceptacion contractual real.
- Renovacion.
- Cancelacion.
- Pagos.
- Facturacion.
- Multi-entidad sin ownership formal.
- SEO productivo.

### E) Riesgos mitigados
Este cierre mitiga:

- `X-User-Id` como autorizacion unica.
- Headers QA fuera de local.
- `local_dev_open` con strict ON.
- Mismatch doctor/entity.
- Operador sin permiso explicito.
- Acceso a entidad ajena via endpoint privado.

### F) Riesgos pendientes
Siguen pendientes:

- Permiso canonico persistido `subscriptions.read`.
- Ownership formal multi-entidad.
- Integracion real con `p-suscripcion` read-only.
- Definicion de flujo contractual real.
- Aceptacion de contrato.
- Creacion de suscripcion.
- Renovacion y cancelacion.
- Pagos y facturacion.
- Conexion futura con capacidades publicas.

### G) Secuencia recomendada
Siguiente camino seguro:

1. Cerrar documentalmente el guard sesion/scope.
2. Diagnosticar integracion read-only de `p-suscripcion`.
3. Disenar consumo UI solo lectura del endpoint privado.
4. Mantener bloqueados writes de suscripcion.
5. Mantener bloqueadas capacidades productivas.
6. Antes de conectar capacidades, resolver plan efectivo productivo y ownership/scope suficiente.

Siguiente microfase recomendada:

- `DIAG-Suscripciones-PanelReadOnly-IntegrationReadiness-01`.

### H) Limites de esta adenda
Esta adenda no activa:

- UI real.
- `p-suscripcion`.
- Perfil publico.
- `PublicProfilePlanCapabilities`.
- Capacidades productivas.
- Contratacion.
- Renovacion.
- Cancelacion.
- Pagos.
- Facturacion.
- Multi-entidad productiva.
- SEO productivo.

---

## Adenda PP-Decisiones 30 — Readiness de integración read-only para panel Suscripción

### A) Contexto
Ya existe el endpoint privado de solo lectura:

- `GET /api/subscriptions/index.php/entities/{entity_type}/{entity_id}/current`.

Tambien existen:

- Read-model de suscripcion actual.
- Guard sesion/scope reforzado y validado.
- Bandera estricta `MXMED_SUBSCRIPTIONS_PRIVATE_AUTH_REQUIRED`.

Estado de integracion:

- El panel `p-suscripcion` existe.
- El panel `p-suscripcion` no esta conectado todavia al endpoint privado.
- Esta decision prepara una futura integracion read-only.
- Esta decision no implementa conexion UI.

### B) Diagnostico aprobado de `p-suscripcion`
Hallazgos de la microfase `DIAG-Suscripciones-PanelReadOnly-IntegrationReadiness-01`:

- `p-suscripcion` existe en `index.html`.
- La navegacion llega al panel mediante dropdown superior y menu lateral.
- La logica relacionada vive en `assets/js/app.js`, IIFE `SUSCRIPCION (maqueta)`.
- La navegacion usa `showPanel()` en `assets/js/core/navigation.js`.
- El panel actual es maqueta/demo.
- El panel muestra datos hardcoded:
  - Plan `Optimo`.
  - Vigencia demo.
  - Catalogo de planes.
  - Historial.
  - Cupones.
  - Facturacion.
- Acciones como renovar, seleccionar plan, aplicar cupon, facturacion e historial son placeholders/demo.
- No existen writes reales desde este panel.
- El panel no usa API actualmente.
- El panel no tiene manejo propio para errores `401`/`403`.

### C) Decision funcional
Queda decidido:

- No conectar todavia `p-suscripcion` en esta fase.
- La primera integracion futura, si se autoriza, debe ser solo read-only.
- No crear suscripciones desde `p-suscripcion`.
- No crear filas `free`.
- No contratar planes.
- No aceptar contrato.
- No renovar.
- No cancelar.
- No pagar.
- No emitir facturas.
- No activar capacidades productivas.
- No conectar `PublicProfilePlanCapabilities`.
- No alterar perfil publico ni SEO.
- Las acciones comerciales deben quedar deshabilitadas o marcadas como placeholder hasta microfases futuras.

### D) Contrato read-only requerido
El panel podria consumir estos campos del read-model actual:

- `effective_plan_code`.
- `contracted_plan_code`.
- `plan_label`.
- `billing_period`.
- `duration_days`.
- `status`.
- `starts_at`.
- `expires_at`.
- `grace_status`.
- `is_free_fallback`.
- `is_paid_plan`.
- `is_active`.
- `is_expired`.
- `is_in_grace`.
- `days_until_expiration`.
- `source`.
- `version`.

Estos campos ya estan disponibles en el read-model actual.

Campos pendientes para versiones futuras:

- Catalogo comercial completo.
- Precios.
- Moneda.
- Siguiente cobro.
- Autorenovacion visible.
- Historial de movimientos.
- Facturas.
- Cupones reales.
- Metodo de pago.
- Contrato aceptado visible para usuario.

### E) Resolucion doctor/entity
Fuentes actuales detectadas en UI:

- `body[data-doctor-id]`.
- `window.mxmedStore.doctor_id`.
- `doctorId`.
- `activeProfessionalContext.doctor_id`.
- `window.mxmedDoctor.doctor_id`.
- `window.mxmedResolveActiveProfessionalContext()`.

Riesgos:

- Algunos valores pueden ser demo o hardcoded.
- El contexto local/dev puede no equivaler a sesion productiva.
- El operador todavia no tiene scope visual formal.
- No existe endpoint canonico de entidad activa.
- Para uso productivo real conviene resolver antes un contexto activo confiable.

Decision:

- Para una futura integracion DEV-only podria usarse el doctor activo existente con guardas estrictas.
- Para integracion real/productiva se recomienda resolver antes un contexto activo canonico y confiable.

### F) Matriz UI read-only propuesta
Caso A - Sin suscripcion real:

- Mostrar plan `Gratuito`.
- Mostrar "Plan base permanente".
- Vencimiento: "No aplica".
- No crear fila `free`.
- Mantener acciones comerciales deshabilitadas o como placeholder.

Caso B - Plan pagado vigente:

- Mostrar plan contratado.
- Mostrar plan efectivo.
- Mostrar inicio y vencimiento si existen.
- Mostrar dias restantes.
- No permitir acciones reales todavia.

Caso C - Vencido en gracia:

- Mostrar estado de gracia.
- Mostrar limite de gracia.
- No definir todavia capacidades durante gracia.

Caso D - Vencido fuera de gracia:

- Mostrar plan efectivo `Gratuito`.
- Mostrar que el plan contratado anterior vencio.
- No borrar datos.
- No ejecutar writes.

Caso E - `401`/`403`:

- `401`: sesion no valida o iniciar sesion.
- `403`: sin permiso para ver esta suscripcion.
- No mostrar datos sensibles.

Caso F - Error backend:

- Mostrar mensaje controlado.
- No bloquear navegacion general del panel.

### G) Riesgos documentados
Riesgos antes de conectar UI:

- Llamar el endpoint con `doctor_id` incorrecto.
- Mostrar plan efectivo como si fuera plan contratado editable.
- Dejar botones de contratar, pagar o renovar como acciones reales.
- Confundir plan gratuito permanente con plan vencido.
- Crear suscripciones `free` por error.
- Activar capacidades desde UI.
- Usar headers QA en UI productiva.
- Exponer datos a operador sin scope.
- Romper navegacion actual de paneles.
- Generar expectativa de pagos o renovacion antes de existir flujo.

### H) Secuencia recomendada
Camino seguro recomendado:

1. Documentar readiness read-only.
2. Definir si la primera conexion sera DEV-only o si requiere contexto activo canonico.
3. Si se autoriza DEV-only, implementar conexion read-only con:
   - Sin writes.
   - Sin botones reales.
   - Sin contratacion.
   - Sin pagos.
   - Sin capacidades.
   - Manejo visual `401`/`403`.
   - Fallback visual seguro.
4. Si hay incertidumbre sobre doctor/entity activo, diagnosticar primero endpoint o contexto activo.
5. Mantener bloqueada conexion con `PublicProfilePlanCapabilities`.
6. Mantener bloqueadas capacidades productivas.
7. Mantener bloqueados writes contractuales.

Siguiente microfase recomendada con maxima seguridad:

- `BE/DIAG-Suscripciones-ActiveEntityContext-Readiness-01`.

Alternativa si el usuario autoriza conexion controlada local:

- `FE-Suscripciones-PanelReadOnly-DevIntegration-01`.

### I) Limites de esta adenda
Esta adenda no activa:

- Conexion de `p-suscripcion`.
- UI real.
- Backend nuevo.
- Modificacion de `api/subscriptions/index.php`.
- Modificacion de `modules/subscriptions`.
- Writes de suscripcion.
- Contratacion.
- Aceptacion contractual.
- Renovacion.
- Cancelacion.
- Pagos.
- Facturacion.
- `PublicProfilePlanCapabilities`.
- Capacidades productivas.
- Perfil publico.
- SEO productivo.

---

## Adenda PP-Decisiones 31 — Readiness de contexto activo doctor/entity para Suscripciones

### A) Contexto
Ya existe el endpoint privado de suscripcion actual:

- `GET /api/subscriptions/index.php/entities/{entity_type}/{entity_id}/current`

Tambien existe:

- Read-model de suscripcion actual.
- Guard sesion/scope reforzado para el endpoint privado.
- Strict flag `MXMED_SUBSCRIPTIONS_PRIVATE_AUTH_REQUIRED`.
- Documentacion de readiness read-only de `p-suscripcion`.

Antes de conectar cualquier UI se diagnostico si existe una fuente confiable para resolver:

- `doctor_id`.
- `entity_type`.
- `entity_id`.
- Actor u operador.
- Permisos y scope.

Esta adenda documenta la decision de readiness. No conecta `p-suscripcion`, no crea endpoint auxiliar y no activa writes ni capacidades productivas.

### B) Diagnostico aprobado
Hallazgos de la microfase `BE/DIAG-Suscripciones-ActiveEntityContext-Readiness-01`:

- No existe endpoint canonico tipo `/api/me/context`.
- No existe endpoint canonico tipo `/api/subscriptions/index.php/context/current`.
- No existe helper global backend de contexto activo.
- Existen fuentes frontend utiles como pista visual o DEV/local, pero no como autoridad productiva.
- La fuente mas segura hoy es la sesion validada por `api/subscriptions/index.php`.
- Agenda tiene un patron parcial robusto con `actorContext`, `actor_role`, `operator_id`, `doctor_id` y validacion de operador.
- Ese patron de agenda todavia no es un helper global reutilizable.
- Suscripciones ya valida sesion/scope en su endpoint privado.
- Para UI productiva hace falta contexto activo backend con sesion real, ownership/scope y permisos formales.

### C) Fuentes actuales doctor/entity
Fuentes detectadas y nivel de confianza:

- `body[data-doctor-id]`:
  - Existe en `index.html`.
  - Hoy contiene `doctor_id=1`.
  - Es util como pista visual o DEV/local.
  - No es confiable para uso productivo.
- `window.mxmedStore.doctor_id`:
  - Se alimenta desde `body.dataset.doctorId`.
  - Es manipulable en cliente.
  - Puede usarse como sugerencia, no como autoridad.
- `doctorId`:
  - Es alias en `window.mxmedStore.doctorId`.
  - Tiene el mismo nivel de confianza que `window.mxmedStore.doctor_id`.
- `activeProfessionalContext.doctor_id`:
  - Se resuelve en `assets/js/app.js`.
  - Es la mejor fuente frontend actual.
  - Sigue siendo estado cliente, no fuente backend de verdad.
- `window.mxmedDoctor.doctor_id`:
  - Global frontend poblado desde dataset/store.
  - No es fuente productiva de verdad.
- `window.mxmedResolveActiveProfessionalContext()`:
  - Helper frontend existente.
  - Consolida doctor, usuario, rol y operador.
  - Es util para DEV/local y como UI hint.
  - No es autoridad backend.
- Sesion backend:
  - Presente en `api/subscriptions`, `api/profiles` y `api/agenda`.
  - Usa aliases como `user_id`, `mxmed_user_id`, `auth_user_id`, `doctor_id`, `active_doctor_id` y `mxmed_doctor_id`.
  - Es la fuente mas segura actual cuando el endpoint valida scope.
- `actorContext` de agenda:
  - Patron parcial robusto.
  - Maneja `actor_role`, `operator_id` y `doctor_id`.
  - No esta convertido en helper global.
- Otras fuentes:
  - `window.mxmedStore.currentActor`.
  - `body[data-user-id]`.
  - `body[data-user-role]`.
  - `body[data-operator-slot]`.

### D) Matriz de confianza
Nivel 0 - No confiable:

- Valores hardcoded.
- Datos demo.
- Datos visibles y manipulables sin validacion backend.

Nivel 1 - Util solo para DEV/local:

- `body[data-doctor-id]`.
- Variables globales frontend.
- Contexto visual no validado.

Nivel 2 - Util para UI read-only si backend valida:

- `doctor_id` visual usado solo como sugerencia.
- Endpoint privado valida sesion/scope.
- Errores `401`/`403` son manejados visualmente.
- No hay writes.

Nivel 3 - Confiable productivo:

- Contexto activo obtenido desde backend con sesion real.
- Ownership/scope validado.
- Operador y permisos resueltos formalmente.
- Permisos canonicos persistidos.

Estado actual de MXMed para `p-suscripcion`:

- Nivel 1 si se mira solo la fuente visual.
- Nivel 2 si el `doctor_id` visual se usa solo como sugerencia y el endpoint privado valida sesion/scope.
- Todavia no llega a Nivel 3 productivo.

### E) Opciones evaluadas
Opcion A - DEV-only sin endpoint auxiliar:

- Viable con condiciones.
- Usar `doctor_id` visual solo como sugerencia.
- Consumir el endpoint privado de suscripcion actual.
- El backend debe bloquear con `401`/`403` si no hay identidad o scope suficiente.
- Solo local/dev.
- Sin writes.
- Riesgo: el contexto visual puede ser demo.

Opcion B - Endpoint auxiliar de contexto activo:

- Recomendado antes de UI productiva.
- Posibles rutas futuras:
  - `GET /api/me/context`.
  - `GET /api/subscriptions/index.php/context/current`.
- Debe devolver como minimo:
  - `ok`.
  - `user_id`.
  - `doctor_id` activo.
  - `entity_type`.
  - `entity_id`.
  - `actor_role`.
  - `operator_id`.
  - Permisos minimos o flags.
  - `source`.
  - `version`.
- No debe exponer datos sensibles.

Opcion C - Reutilizar patron existente:

- Reutilizar conceptos de agenda y perfiles.
- No existe helper listo.
- Requiere extraccion y normalizacion futura.

Opcion D - Bloquear FE:

- Necesaria para productivo si no hay contexto canonico.
- No es imprescindible para DEV-only controlado.

### F) Decision funcional
Queda decidido:

- No existe contexto activo canonico productivo hoy.
- No se debe conectar `p-suscripcion` para uso productivo todavia.
- Se puede considerar una conexion read-only DEV/local con condiciones estrictas:
  - Usar `doctor_id` visual solo como sugerencia.
  - El endpoint privado debe validar sesion/scope.
  - Manejar visualmente `401`/`403`.
  - No ejecutar writes.
  - No habilitar botones reales.
  - No activar capacidades productivas.
- Para produccion se recomienda crear o formalizar contexto activo backend.
- Los writes de suscripcion siguen bloqueados.
- Las capacidades productivas siguen bloqueadas.
- `PublicProfilePlanCapabilities` sigue desconectado.

### G) Riesgos documentados
Riesgos antes de conectar UI:

- Tratar `body[data-doctor-id]` como autoridad productiva.
- Confiar en variables frontend manipulables.
- Conectar operador sin scope visual/backend formal.
- No distinguir contexto demo de contexto real.
- Llamar el endpoint con doctor incorrecto.
- Ocultar errores `401`/`403`.
- Activar UI como si fuera productiva.
- Extender a multi-entidad sin ownership formal.
- Conectar capacidades antes de resolver contexto activo productivo.

### H) Secuencia recomendada
Camino seguro recomendado:

1. Documentar readiness de contexto activo.
2. Si se desea avanzar visualmente, hacer integracion `DEV-only` read-only de `p-suscripcion` con guardas estrictas.
3. Mantener `doctor_id` visual como sugerencia, no como autoridad.
4. Hacer que el backend sea quien autorice o bloquee.
5. Manejar visualmente `401`/`403`.
6. Mantener deshabilitados writes, contratacion, pagos, renovacion y cancelacion.
7. Antes de produccion, disenar endpoint/contexto activo canonico.
8. Mantener bloqueadas capacidades productivas y `PublicProfilePlanCapabilities`.

Siguiente microfase recomendada si se desea avanzar de forma controlada:

- `FE-Suscripciones-PanelReadOnly-DevIntegration-01`.

Siguiente microfase alternativa mas backend/productiva:

- `BE/DIAG-Suscripciones-ActiveEntityContext-EndpointDesign-01`.

### I) Limites de esta adenda
Esta adenda no activa:

- Conexion de `p-suscripcion`.
- Endpoint auxiliar.
- Backend nuevo.
- UI real.
- Writes de suscripcion.
- Contratacion.
- Aceptacion contractual.
- Renovacion.
- Cancelacion.
- Pagos.
- Facturacion.
- `PublicProfilePlanCapabilities`.
- Capacidades productivas.
- Perfil publico.
- SEO productivo.

---

## Adenda PP-Decisiones 32 — Cierre de integracion read-only DEV/local del panel Suscripcion

### A) Contexto
Ya existe el endpoint privado read-only:

- `GET /api/subscriptions/index.php/entities/doctor/{doctor_id}/current`

Tambien existe:

- Read-model actual de suscripcion.
- Guard sesion/scope reforzado.
- Documentacion de readiness read-only de `p-suscripcion`.
- Decision documentada de que no existe contexto activo productivo canonico.

Se autorizo una integracion DEV/local read-only del panel `p-suscripcion`, con el backend como autoridad de sesion/scope y sin activar writes ni capacidades productivas.

### B) Implementacion cerrada
En el commit `bffcdef feat(suscripciones): conecta panel read-only en dev` se integro `p-suscripcion` en modo DEV/local read-only desde:

- `assets/js/app.js`

La integracion:

- Usa `doctor_id` frontend solo como sugerencia.
- Usa unicamente `entity_type=doctor`.
- Llama al endpoint privado con metodo `GET`.
- Usa `credentials: same-origin`.
- Usa `Accept: application/json`.
- No usa headers QA frontend:
  - No `X-User-Id`.
  - No `X-Doctor-Id`.
  - No `X-Entity-Type`.
  - No `X-Entity-Id`.
- Deja al backend como autoridad de sesion/scope.
- Renderiza el read-model actual.
- Muestra plan efectivo.
- Muestra plan contratado si existe.
- Mapea `free_default` a copy visual comprensible.
- Muestra `No aplica` para vencimiento y dias restantes cuando el plan efectivo es `free/lifetime/0`.
- Muestra modo lectura.
- Mantiene acciones comerciales bloqueadas.

### C) Estado visual esperado actual
Con la DB local actual:

- `subscription_plans = 5`.
- `profile_subscriptions = 0`.
- `free = Gratuito / lifetime / 0`.

El panel debe mostrar:

- Plan: `Gratuito`.
- Estado: `Plan base permanente`.
- Vigencia/Vencimiento: `No aplica`.
- Dias restantes: `No aplica`.
- Sin plan contratado vigente.
- Modo lectura.
- Acciones comerciales deshabilitadas, placeholder o proximamente.

### D) Acciones que siguen bloqueadas
Siguen bloqueadas:

- Contratar.
- Aceptar contrato.
- Renovar.
- Cancelar.
- Cambiar plan.
- Pagar.
- Aplicar cupon real.
- Facturacion real.
- Historial real de pagos/facturas.
- Creacion de suscripciones.
- Creacion de filas `free`.
- Conexion con `PublicProfilePlanCapabilities`.
- Activacion de capacidades productivas.
- Perfil publico.
- SEO productivo.

### E) QA post-push cerrado
La microfase `QA-Suscripciones-PanelReadOnly-DevIntegration-PostPush-01` cerro con PASS sin cambios.

Resumen de QA:

- Rama limpia y alineada.
- HEAD: `bffcdef`.
- JS parse PASS via `osascript/JXA`.
- Node no disponible en el entorno local.
- PHP lint PASS en:
  - `api/subscriptions/index.php`.
  - `CurrentSubscriptionRepository.php`.
  - `CurrentSubscriptionReadModelService.php`.
- Fetch read-only PASS.
- Sin writes hacia suscripciones.
- Sin headers QA frontend para suscripciones.
- `/index.html` HTTP 200.
- `/assets/js/app.js` HTTP 200.
- Backend:
  - `GET doctor/1/current` HTTP 200.
  - `effective_plan_code=free`.
  - `status=free_default`.
  - `auth_mode=local_dev_open`.
  - `strict_auth_required=false`.
- DB intacta:
  - `subscription_plans=5`.
  - `profile_subscriptions=0`.
- Sin cambios en backend, DB, perfil publico, SEO ni capacidades.
- QA visual manual en navegador interactivo queda pendiente.

### F) Riesgos mitigados
La integracion mitiga:

- Uso de headers QA desde frontend.
- Writes accidentales desde el panel.
- Creacion accidental de suscripciones `free`.
- Activacion prematura de capacidades.
- Conexion prematura con `PublicProfilePlanCapabilities`.
- Confundir acciones comerciales con acciones reales.
- Tratar el frontend como autoridad de scope.

### G) Riesgos pendientes
Siguen pendientes:

- QA visual manual en navegador interactivo.
- Contexto activo canonico productivo.
- Endpoint tipo `/api/me/context` o equivalente, si se decide.
- Permiso persistido/canonico `subscriptions.read`.
- Ownership multi-entidad.
- Flujo contractual real.
- Aceptacion de contrato.
- Creacion de suscripcion.
- Renovacion/cancelacion.
- Pagos/facturacion.
- Conexion futura con capacidades publicas.

### H) Secuencia recomendada
Camino seguro recomendado:

1. Cerrar documentalmente la integracion read-only DEV/local.
2. Ejecutar QA visual manual del panel `Suscripcion` en navegador.
3. Si la UX read-only es correcta, decidir entre:
   - Disenar contexto activo productivo.
   - Seguir refinando solo DEV/local.
4. Mantener writes contractuales bloqueados.
5. Mantener capacidades productivas bloqueadas.
6. Mantener `PublicProfilePlanCapabilities` desconectado.
7. Antes de uso productivo, resolver contexto activo backend, ownership/scope y permisos.

Siguiente microfase recomendada:

- `QA-Suscripciones-PanelReadOnly-VisualManual-01`.

Alternativa backend/productiva:

- `BE/DIAG-Suscripciones-ActiveEntityContext-EndpointDesign-01`.

### I) Limites de esta adenda
Esta adenda no activa:

- Uso productivo de `p-suscripcion`.
- Writes contractuales.
- Contratacion.
- Aceptacion contractual.
- Renovacion.
- Cancelacion.
- Pagos.
- Facturacion.
- `PublicProfilePlanCapabilities`.
- Capacidades productivas.
- Perfil publico.
- SEO productivo.

---

## Adenda PP-Decisiones 33 — Cierre QA visual del panel Suscripcion read-only

### A) Contexto
Ya existe una integracion DEV/local read-only del panel `p-suscripcion`.

La integracion fue implementada en:

- `assets/js/app.js`

El endpoint consultado es:

- `GET /api/subscriptions/index.php/entities/doctor/{doctor_id}/current`

La integracion sigue sin writes y sin acciones comerciales reales. Esta adenda documenta el cierre de la QA visual/manual del panel `Suscripcion`, cerrada con PASS.

### B) QA visual/manual cerrada
La microfase `QA-Suscripciones-PanelReadOnly-VisualManual-01` cerro con PASS sin cambios.

Resumen tecnico:

- Rama limpia y alineada.
- HEAD: `fe019e0`.
- PHP lint PASS:
  - Endpoint `api/subscriptions/index.php`.
  - Repository `CurrentSubscriptionRepository.php`.
  - Service `CurrentSubscriptionReadModelService.php`.
- JS parse PASS via JXA.
- Node no disponible.
- `/index.html` HTTP 200.
- `/assets/js/app.js` HTTP 200.
- Endpoint `doctor/1/current` HTTP 200.
- `effective_plan_code=free`.
- `status=free_default`.
- `auth_mode=local_dev_open`.
- `strict_auth_required=false`.

### C) Resultado visual validado
En el panel `Suscripcion` se valido:

- El panel abre correctamente.
- `p-suscripcion` queda activo y visible.
- La navegacion queda intacta.
- El layout queda intacto.
- No hay ruptura visual observada por DOM/Safari.
- Plan mostrado: `Gratuito`.
- Estado mostrado: `Plan base permanente`.
- Vigencia/vencimiento: `No aplica`.
- Dias restantes: `No aplica`.
- Plan contratado: `Sin plan contratado vigente`.
- Modo lectura visible.
- Copy comprensible.
- El copy indica read-model y acciones comerciales deshabilitadas.

### D) Acciones comerciales bloqueadas
Siguen bloqueadas:

- Contratar: bloqueado / `Proximamente`.
- Renovar: deshabilitado.
- Cambiar plan: botones de catalogo deshabilitados.
- Cancelar: sin accion real activa.
- Pagar: sin accion real activa.
- Cupon: input y boton deshabilitados.
- Facturacion: deshabilitada en modo lectura.
- Historial/facturas: refresh deshabilitado; filas en modo lectura.

Validaciones explicitas:

- Ninguna accion ejecuta write.
- No se ejecutan `POST`/`PUT`/`PATCH`/`DELETE` hacia `/api/subscriptions`.
- La llamada de suscripcion es `GET`.
- No se usan headers QA frontend para suscripciones.

### E) Estados de error
Los estados de error no se forzaron modificando codigo ni servidor.

Queda documentado:

- `401` no fue simulado, pero existe copy controlado en codigo.
- `403` no fue simulado, pero existe copy controlado en codigo.
- Error backend no fue simulado, pero existe copy controlado en codigo.
- No se modifico codigo ni servidor para forzar errores.

### F) DB y aislamiento
La QA visual/manual confirmo:

- `subscription_plans count = 5`.
- `profile_subscriptions count = 0`.
- DB intacta.
- No se ejecuto SQL de escritura.
- No se modificaron archivos.
- No se modifico backend.
- No se modifico UI.
- No se conecto `PublicProfilePlanCapabilities`.
- No se activaron capacidades productivas.
- No se implemento contratacion.
- No se implemento renovacion.
- No se implemento cancelacion.
- No se implementaron pagos.
- No se toco SEO productivo.

### G) Estado funcional final del bloque
El bloque read-only DEV/local del panel `Suscripcion` queda visualmente cerrado para DEV/local.

Permitido:

- Mostrar suscripcion actual desde read-model.
- Mostrar plan gratuito permanente.
- Mostrar modo lectura.
- Mostrar acciones comerciales bloqueadas.
- Usar backend como autoridad.

Bloqueado:

- Uso productivo sin contexto activo canonico.
- Writes de suscripcion.
- Crear suscripciones.
- Crear filas `free`.
- Contratar.
- Aceptar contrato.
- Renovar.
- Cancelar.
- Pagar.
- Facturar.
- Conectar `PublicProfilePlanCapabilities`.
- Activar capacidades productivas.
- Perfil publico.
- SEO productivo.

### H) Secuencia recomendada
Opciones siguientes:

1. `FE/UX-Suscripciones-PanelReadOnly-VisualPolish-01`
   - Objetivo: afinar copy, jerarquia visual y estados del panel, sin writes ni capacidades.
2. `BE/DIAG-Suscripciones-ActiveEntityContext-EndpointDesign-01`
   - Objetivo: disenar contexto activo canonico antes de uso productivo.
3. `DIAG-Suscripciones-ContractFlow-Readiness-01`
   - Objetivo: diagnosticar que falta para contratacion real, aceptacion de contrato y vigencia contractual, sin implementar todavia.

Recomendacion:

- Si el panel visual ya es aceptable en DEV/local, priorizar diseno backend productivo de contexto activo antes de uso real.

### I) Limites de esta adenda
Esta adenda no activa:

- Uso productivo.
- Writes de suscripcion.
- Contratacion.
- Aceptacion contractual.
- Renovacion.
- Cancelacion.
- Pagos.
- Facturacion.
- `PublicProfilePlanCapabilities`.
- Capacidades productivas.
- Perfil publico.
- SEO productivo.

---

## Adenda PP-Decisiones 34 — Diseño de endpoint de contexto activo para Suscripciones

### A) Contexto
Ya existe el endpoint privado de suscripcion actual:

`GET /api/subscriptions/index.php/entities/{entity_type}/{entity_id}/current`

Tambien existen:

- Guard sesion/scope reforzado para el endpoint privado.
- Integracion DEV/local read-only de `p-suscripcion`.
- QA visual/manual del panel read-only cerrada con PASS.

Para uso productivo todavia falta un contexto activo canonico backend. El diagnostico `BE/DIAG-Suscripciones-ActiveEntityContext-EndpointDesign-01` confirmo:

- No existe endpoint canonico `/api/me/context`.
- No existe endpoint `/api/subscriptions/index.php/context/current`.
- No existe helper global backend reutilizable de auth/contexto activo.
- Las fuentes frontend (`body[data-doctor-id]`, `window.mxmedStore`, `window.mxmedDoctor`, `activeProfessionalContext`) son pistas visuales o DEV/local, no autoridad productiva.

Esta adenda documenta el diseno. No implementa endpoint, no crea helper, no conecta UI productiva y no activa writes ni capacidades productivas.

### B) Decision de alcance
Decision:

- No crear todavia un endpoint global `/api/me/context`.
- Disenar primero un endpoint bajo suscripciones.

Endpoint recomendado:

`GET /api/subscriptions/index.php/context/current`

Motivo:

- Menor alcance.
- Aprovecha el guard ya endurecido de suscripciones.
- Evita crear prematuramente arquitectura global.
- Cubre la necesidad inmediata de `p-suscripcion`.
- Permite evolucionar despues hacia un contexto global si otros modulos lo requieren.

### C) Naturaleza del endpoint futuro
El endpoint futuro debe ser:

- Read-only.
- Privado.
- Minimo.
- Sin datos sensibles.
- Sin writes.
- Sin creacion de sesiones.
- Sin creacion de suscripciones.
- Sin activacion de capacidades.
- Sin pagos.
- Sin facturacion.
- Sin tocar perfil publico.
- Sin tocar SEO productivo.

Su unica funcion sera permitir que la UI conozca el contexto activo autorizado y despues consulte el endpoint actual de suscripcion.

Version propuesta:

- `active-entity-context-v1`

### D) Contrato OK propuesto
Contrato conceptual:

```json
{
  "ok": true,
  "data": {
    "user_id": 1,
    "doctor_id": 1,
    "entity_type": "doctor",
    "entity_id": "1",
    "actor_role": "doctor",
    "operator_id": null,
    "permissions": {
      "subscriptions_read": true
    },
    "can_read_subscriptions": true
  },
  "meta": {
    "source": "session_scope",
    "version": "active-entity-context-v1"
  }
}
```

Este contrato es conceptual y puede ajustarse durante la implementacion, siempre que mantenga alcance minimo, privacidad y doble validacion.

### E) Contrato error propuesto
Contrato conceptual de error:

```json
{
  "ok": false,
  "error": {
    "code": "...",
    "message": "..."
  },
  "data": null,
  "meta": {
    "version": "active-entity-context-v1"
  }
}
```

Codigos esperados:

- `unauthorized` para ausencia de identidad valida.
- `forbidden` para identidad con scope insuficiente.
- `context_unavailable` si no se puede resolver contexto activo de forma segura.

### F) Campos minimos
Campos minimos propuestos:

- `user_id`
- `doctor_id`
- `entity_type`
- `entity_id`
- `actor_role`
- `operator_id`
- `permissions.subscriptions_read`
- `can_read_subscriptions`
- `source`
- `version`

### G) Campos excluidos
El endpoint no debe devolver:

- Tokens.
- Sesion cruda.
- IP.
- User-agent contractual.
- Datos administrativos privados.
- Pagos.
- Facturacion.
- Metodos de pago.
- Capacidades productivas.
- Datos SEO.
- Datos clinicos.
- Permisos amplios innecesarios.
- Informacion sensible del usuario.

### H) Reglas de autorizacion
Medico principal:

- Puede resolver solo `entity_type=doctor`.
- Puede resolver solo `entity_id=doctor_id` activo de sesion.
- Debe bloquear doctor ajeno.
- Debe bloquear entidad ajena.
- Debe bloquear `entity_type` no soportado.

Operador:

- Solo podra resolver contexto si `operator_id` existe.
- Debe pertenecer al doctor.
- Debe estar activo.
- Debe tener permiso explicito futuro `subscriptions.read`, `subscriptions_read` o equivalente documentado.
- Debe bloquear operador sin permiso.
- Debe bloquear operador de otro doctor.
- Debe bloquear operador con scope ambiguo.

DEV/local:

- Puede informar pista DEV/local solo marcada como tal.
- No debe tratarse como autoridad productiva.
- Los endpoints protegidos deben seguir validando sesion/scope.

Multi-entidad:

- Queda pendiente y bloqueada hasta definir ownership formal para clinica, hospital, laboratorio, aseguradora, laboratorio farmaceutico y otras entidades publicables.

### I) Relacion con endpoint actual de suscripcion
Flujo futuro:

1. UI llama a `GET /api/subscriptions/index.php/context/current`.
2. Backend devuelve contexto autorizado minimo.
3. UI llama a `GET /api/subscriptions/index.php/entities/{entity_type}/{entity_id}/current`.
4. El endpoint de suscripcion vuelve a validar sesion/scope.
5. UI renderiza read-only.
6. Writes siguen bloqueados hasta microfases futuras.

Aclaraciones:

- El endpoint de contexto no reemplaza la autorizacion del endpoint de suscripcion.
- Debe existir doble validacion.
- El contexto ayuda a resolver la ruta; no autoriza writes.
- No habilita contratacion, renovacion, cancelacion, pagos ni facturacion.
- No habilita capacidades productivas.
- No conecta `PublicProfilePlanCapabilities`.

### J) Riesgos documentados
Riesgos a controlar:

- Confiar en contexto frontend.
- Duplicar reglas de auth en varios endpoints.
- Devolver permisos excesivos.
- Exponer datos personales innecesarios.
- Autorizar operador sin ownership o permiso formal.
- Abrir multi-entidad sin modelo de ownership.
- Confundir contexto con autorizacion final.
- Usar contexto para writes contractuales antes de tiempo.
- Activar capacidades desde este contexto antes de resolver plan efectivo productivo.

### K) Opciones futuras documentadas
Opcion A - Implementar endpoint bajo subscriptions:

- Microfase: `BE-Suscripciones-ActiveEntityContext-Endpoint-01`.
- Objetivo: crear `GET /api/subscriptions/index.php/context/current`, read-only, sin conectar uso productivo amplio.

Opcion B - Diagnosticar endpoint global:

- Microfase: `BE/DIAG-Auth-ActiveContext-GlobalEndpoint-01`.
- Objetivo: evaluar si conviene crear un `/api/me/context` compartido para todo MXMed.

Opcion C - Refinar UI read-only:

- Microfase: `FE/UX-Suscripciones-PanelReadOnly-VisualPolish-01`.
- Objetivo: mejorar jerarquia, copy y estados del panel, manteniendo read-only.

Opcion D - Diagnosticar flujo contractual:

- Microfase: `DIAG-Suscripciones-ContractFlow-Readiness-01`.
- Objetivo: diagnosticar contratacion real, aceptacion de contrato, vigencia, renovacion, cancelacion y pagos, sin implementar.

### L) Recomendacion
Ruta recomendada:

1. Documentar este diseno.
2. Implementar despues el endpoint bajo `subscriptions`, no global.
3. Mantener doble validacion.
4. Mantener writes bloqueados.
5. Mantener capacidades productivas bloqueadas.
6. Mantener `PublicProfilePlanCapabilities` desconectado.
7. Mantener SEO productivo intacto.

Siguiente microfase recomendada:

- `BE-Suscripciones-ActiveEntityContext-Endpoint-01`

### M) Limites de esta adenda
Esta adenda no implementa:

- Endpoint de contexto.
- Helper global.
- UI productiva.
- Writes de suscripcion.
- Contratacion.
- Aceptacion contractual.
- Renovacion.
- Cancelacion.
- Pagos.
- Facturacion.
- `PublicProfilePlanCapabilities`.
- Capacidades productivas.
- Perfil publico.
- SEO productivo.

---

## Adenda PP-Decisiones 35 — Cierre del endpoint de contexto activo para Suscripciones

### A) Contexto
Ya existe el endpoint privado de suscripcion actual:

`GET /api/subscriptions/index.php/entities/{entity_type}/{entity_id}/current`

Tambien existe la integracion DEV/local read-only del panel `p-suscripcion` y ya quedo documentado el diseno del endpoint de contexto activo para suscripciones.

En la microfase `BE-Suscripciones-ActiveEntityContext-Endpoint-01` se implemento el endpoint:

`GET /api/subscriptions/index.php/context/current`

Archivo de implementacion:

- `api/subscriptions/index.php`

Version del contrato:

- `active-entity-context-v1`

### B) Implementacion cerrada
En el commit `7e5cfc3 feat(suscripciones): agrega contexto activo privado` se agrego el endpoint:

`GET /api/subscriptions/index.php/context/current`

Caracteristicas cerradas:

- Privado.
- Read-only.
- Minimo.
- Orientado a suscripciones.
- No global todavia.
- No reemplaza la autorizacion del endpoint de suscripcion actual.
- Mantiene doble validacion.
- No escribe DB.
- No crea sesiones.
- No crea suscripciones.
- No activa capacidades.
- No conecta `PublicProfilePlanCapabilities`.
- No toca UI.
- No toca perfil publico.
- No toca SEO productivo.

### C) Contrato funcional documentado
En caso OK, el endpoint devuelve contexto minimo:

- `user_id`
- `doctor_id`
- `entity_type=doctor`
- `entity_id`
- `actor_role`
- `operator_id`
- `permissions.subscriptions_read`
- `can_read_subscriptions`
- `meta.source`
- `meta.version=active-entity-context-v1`

En caso de error, devuelve:

- `ok=false`
- `error.code`
- `error.message`
- `data=null`
- `meta.version=active-entity-context-v1`

### D) Campos excluidos
El endpoint no devuelve:

- Tokens.
- Sesion cruda.
- IP.
- User-agent contractual.
- Pagos.
- Facturacion.
- Metodos de pago.
- Datos clinicos.
- Datos SEO.
- Capacidades productivas.
- Permisos amplios.
- Informacion sensible innecesaria.

### E) QA post-push cerrada
La microfase `QA-Suscripciones-ActiveEntityContext-Endpoint-PostPush-01` cerro con PASS sin cambios.

Resumen:

- Rama limpia y alineada.
- HEAD: `7e5cfc3`.
- PHP lint PASS en:
  - `api/subscriptions/index.php`
  - `CurrentSubscriptionRepository.php`
  - `CurrentSubscriptionReadModelService.php`
- Sin dependencias de perfil publico.
- Sin dependencias de `PublicProfilePlanCapabilities`.
- Sin writes SQL ejecutables.
- DB intacta:
  - `subscription_plans=5`
  - `profile_subscriptions=0`

### F) QA contexto strict OFF
Prueba:

`GET /api/subscriptions/index.php/context/current`

Strict OFF local sin sesion:

- HTTP 401.
- `ok=false`.
- `error.code=unauthorized`.
- `meta.version=active-entity-context-v1`.

Decision importante:

- El endpoint no entrega contexto productivo solo por estar en local.
- Este comportamiento es seguro y aceptado.

### G) QA contexto strict ON
Sin headers/sesion:

- HTTP 401.
- `ok=false`.

Headers validos locales:

- `X-User-Id: 1`
- `X-Doctor-Id: 1`
- HTTP 200.
- `entity_type=doctor`.
- `entity_id=1`.
- `doctor_id=1`.
- `meta.source=header_scope`.

`X-User-Id` solo:

- HTTP 403.
- `ok=false`.

Host no-local con headers QA:

- HTTP 401.
- `ok=false`.
- Sin `header_scope`.

### H) QA sesion simulada
Medico principal valido:

- HTTP 200.
- `source=session_scope`.
- `can_read_subscriptions=true`.

Sesion sin `doctor_id`:

- HTTP 403.
- No inventa `doctor_id`.

Operador sin permiso:

- HTTP 403.

Operador con permiso explicito:

- HTTP 200.
- `actor_role=operator`.
- `operator_id=1`.

### I) QA ruta existente no rota
La ruta existente sigue funcionando:

`GET /api/subscriptions/index.php/entities/doctor/1/current`

Resultado:

- HTTP 200.
- `effective_plan_code=free`.
- `status=free_default`.
- `auth_mode=local_dev_open`.

### J) QA general
Metodo no permitido:

- `POST /api/subscriptions/index.php/context/current`
- HTTP 405.

Ruta invalida:

- `GET /api/subscriptions/index.php/context`
- HTTP 404.

Resultado:

- No hubo 500.

### K) Estado frontend
Estado posterior al cierre:

- `p-suscripcion` aun no usa `context/current`.
- UI productiva no esta conectada al endpoint nuevo.
- La integracion DEV/local read-only existente sigue funcionando con el endpoint de suscripcion actual.
- No se modifico `assets/js/app.js`.
- No se modifico `index.html`.

### L) Bloqueos vigentes
Siguen bloqueados:

- Writes de suscripcion.
- Crear suscripciones.
- Crear filas `free`.
- Contratacion.
- Aceptacion contractual.
- Renovacion.
- Cancelacion.
- Pagos.
- Facturacion.
- Conexion con `PublicProfilePlanCapabilities`.
- Capacidades productivas.
- Perfil publico.
- SEO productivo.
- Uso productivo sin politica completa.

### M) Riesgos pendientes
Siguen pendientes:

- Decidir si `p-suscripcion` DEV/local debe migrar a usar `context/current`.
- Disenar uso productivo del contexto activo.
- Permiso canonico persistido `subscriptions.read`.
- Ownership multi-entidad.
- Endpoint global `/api/me/context`, si otros modulos lo requieren.
- Flujo contractual real.
- Aceptacion de contrato.
- Alta de suscripcion.
- Renovacion/cancelacion.
- Pagos/facturacion.
- Conexion futura con capacidades publicas.

### N) Secuencia recomendada
Opcion A - Conectar `p-suscripcion` DEV/local al endpoint de contexto:

- Microfase: `FE-Suscripciones-PanelReadOnly-UseActiveContext-Dev-01`.
- Objetivo: hacer que el panel read-only consulte primero `context/current` y luego `entities/{entity_type}/{entity_id}/current`, manteniendo DEV/local, sin writes y sin capacidades.

Opcion B - Documentar diseno productivo global:

- Microfase: `BE/DIAG-Auth-ActiveContext-GlobalEndpoint-01`.
- Objetivo: evaluar `/api/me/context` si otros modulos necesitan contexto activo comun.

Opcion C - Diagnostico de flujo contractual:

- Microfase: `DIAG-Suscripciones-ContractFlow-Readiness-01`.
- Objetivo: diagnosticar contratacion real, aceptacion de contrato, vigencia, renovacion/cancelacion y pagos.

Recomendacion:

- Antes de writes contractuales o capacidades productivas, primero conectar `p-suscripcion` read-only al contexto activo en DEV/local o cerrar diseno global si se decide ir a productivo.

### O) Limites de esta adenda
Esta adenda no activa:

- UI productiva.
- Writes de suscripcion.
- Contratacion.
- Aceptacion contractual.
- Renovacion.
- Cancelacion.
- Pagos.
- Facturacion.
- `PublicProfilePlanCapabilities`.
- Capacidades productivas.
- Perfil publico.
- SEO productivo.

---

## Adenda PP-Decisiones 36 — Cierre de uso de contexto activo en panel Suscripción read-only

### A) Contexto
Ya existe el endpoint privado de contexto activo:

`GET /api/subscriptions/index.php/context/current`

Ya existe el endpoint privado de suscripcion actual:

`GET /api/subscriptions/index.php/entities/{entity_type}/{entity_id}/current`

Tambien existe el panel `p-suscripcion` en modo DEV/local read-only. En esta fase el panel fue actualizado para consultar primero el contexto activo y, despues, la suscripcion actual. La integracion sigue sin writes, sin acciones comerciales reales y sin uso productivo.

### B) Implementacion cerrada
En el commit `7558562 feat(suscripciones): usa contexto activo en panel read-only` se actualizo `assets/js/app.js` para que el flujo del panel sea:

1. Llamar primero a `GET /api/subscriptions/index.php/context/current`.
2. Si el contexto devuelve `ok=true`, usar `entity_type` y `entity_id`.
3. Llamar despues a `GET /api/subscriptions/index.php/entities/{entity_type}/{entity_id}/current`.
4. Renderizar el panel en modo read-only.
5. Si `context/current` responde 401 en local/dev, usar fallback DEV/local controlado `dev_only` solo cuando existe `doctor_id` visual.
6. Mantener al backend como autoridad final de sesion/scope.

El flujo conserva estas reglas:

- `context/current` resuelve contexto minimo autorizado.
- `entities/{entity_type}/{entity_id}/current` vuelve a validar sesion/scope.
- El frontend no usa headers QA.
- El frontend no ejecuta writes hacia suscripciones.
- El fallback `dev_only` no promete comportamiento productivo.

### C) QA post-push cerrada
La microfase `QA-Suscripciones-PanelReadOnly-UseActiveContext-Dev-PostPush-01` cerro con PASS sin cambios.

Resumen:

- Rama limpia y alineada.
- HEAD: `7558562`.
- JS parse PASS via JXA.
- Node no disponible.
- PHP lint PASS en:
  - `api/subscriptions/index.php`
  - `CurrentSubscriptionRepository.php`
  - `CurrentSubscriptionReadModelService.php`
- `context/current` presente como primer endpoint del flujo.
- `entities/{entity_type}/{entity_id}/current` presente como segundo endpoint.
- Solo metodos GET para suscripciones.
- `credentials: same-origin`.
- Sin headers QA frontend para suscripciones.
- Sin POST/PUT/PATCH/DELETE hacia `/api/subscriptions`.
- `context/current` strict OFF sin sesion devuelve HTTP 401 y `active-entity-context-v1`.
- `entities/doctor/1/current` devuelve HTTP 200, `effective_plan_code=free`, `status=free_default`, `auth_mode=local_dev_open`.
- `/index.html` HTTP 200.
- `/assets/js/app.js` HTTP 200.
- DB intacta:
  - `subscription_plans=5`
  - `profile_subscriptions=0`

### D) Estado UI validado
Estado documentado para el panel `Suscripcion`:

- El panel abre.
- No hay evidencia de ruptura de navegacion/layout.
- El flujo contexto primero esta confirmado en codigo.
- En local sin sesion, el fallback DEV/local `dev_only` esta confirmado.
- El plan esperado sigue siendo `Gratuito`.
- Estado esperado: `Plan base permanente`.
- Vencimiento esperado: `No aplica`.
- Modo lectura presente.
- Fuente contexto: `dev_only` en fallback.
- Acciones comerciales siguen bloqueadas.
- No hay error de parse JS.
- Consola navegador interactiva no fue revisada en esta QA.

### E) Bloqueos vigentes
Siguen bloqueados:

- Writes de suscripcion.
- Crear suscripciones.
- Crear filas `free`.
- Contratacion.
- Aceptacion contractual.
- Renovacion.
- Cancelacion.
- Pagos.
- Facturacion.
- Conexion con `PublicProfilePlanCapabilities`.
- Capacidades productivas.
- Perfil publico.
- SEO productivo.
- Uso productivo sin contexto completo, ownership y permisos formales.

### F) Riesgos pendientes
Siguen pendientes:

- QA visual interactiva con DevTools/Network para confirmar llamadas en navegador.
- Definir uso productivo del contexto activo.
- Permiso canonico persistido `subscriptions.read`.
- Ownership multi-entidad.
- Decidir si crear endpoint global `/api/me/context` en el futuro.
- Flujo contractual real.
- Aceptacion de contrato.
- Alta de suscripcion.
- Renovacion/cancelacion.
- Pagos/facturacion.
- Conexion futura con capacidades publicas.

### G) Secuencia recomendada
Opcion A - QA visual/network del flujo contexto primero:

- Microfase: `QA-Suscripciones-PanelReadOnly-UseActiveContext-VisualNetwork-01`.
- Objetivo: validar en navegador/DevTools que el panel llama primero `context/current`, luego `entities/.../current`, sin headers QA y sin writes.

Opcion B - Refinamiento UX read-only:

- Microfase: `FE/UX-Suscripciones-PanelReadOnly-VisualPolish-01`.
- Objetivo: mejorar jerarquia visual, copy y estados del panel, manteniendo read-only.

Opcion C - Diagnostico de flujo contractual:

- Microfase: `DIAG-Suscripciones-ContractFlow-Readiness-01`.
- Objetivo: diagnosticar contratacion real, aceptacion de contrato, vigencia, renovacion/cancelacion y pagos, sin implementar.

Opcion D - Diseno global de contexto activo:

- Microfase: `BE/DIAG-Auth-ActiveContext-GlobalEndpoint-01`.
- Objetivo: evaluar `/api/me/context` si otros modulos requieren contexto activo comun.

Recomendacion:

- Antes de activar writes o capacidades productivas, validar visualmente el flujo contexto primero con Network y despues diagnosticar flujo contractual.

### H) Limites de esta adenda
Esta adenda no activa:

- Uso productivo.
- Writes de suscripcion.
- Contratacion.
- Aceptacion contractual.
- Renovacion.
- Cancelacion.
- Pagos.
- Facturacion.
- `PublicProfilePlanCapabilities`.
- Capacidades productivas.
- Perfil publico.
- SEO productivo.

---

## Adenda PP-Decisiones 37 — Cierre QA visual/network del flujo contexto primero en Suscripción

### A) Contexto
Ya existe el endpoint de contexto activo:

`GET /api/subscriptions/index.php/context/current`

Ya existe el endpoint de suscripcion actual:

`GET /api/subscriptions/index.php/entities/{entity_type}/{entity_id}/current`

El panel `p-suscripcion` esta en modo DEV/local read-only y fue actualizado para consultar primero el contexto activo. Esta adenda documenta el cierre de la QA visual/network que valido el flujo real del navegador.

### B) QA visual/network cerrada
La microfase `QA-Suscripciones-PanelReadOnly-UseActiveContext-VisualNetwork-01` cerro con PASS sin cambios.

Resumen:

- Rama limpia y alineada.
- HEAD: `ba26ad2`.
- PHP lint PASS en:
  - Endpoint.
  - Repository.
  - Service.
- JS parse PASS via JXA.
- Node no disponible.
- `/index.html` HTTP 200.
- `/assets/js/app.js` HTTP 200.
- DB intacta:
  - `subscription_plans=5`
  - `profile_subscriptions=0`

### C) Flujo Network validado
En Chrome/Playwright/Network se observo el orden correcto:

1. `GET /api/subscriptions/index.php/context/current`
2. `GET /api/subscriptions/index.php/entities/doctor/1/current`

Resultado de `context/current`:

- Metodo: GET.
- Status: HTTP 401.
- Respuesta: `ok=false`.
- `error.code=unauthorized`.
- `version=active-entity-context-v1`.
- Comportamiento esperado en local sin sesion.

Resultado de `entities/doctor/1/current`:

- Metodo: GET.
- Status: HTTP 200.
- Respuesta: `ok=true`.
- `effective_plan_code=free`.
- `status=free_default`.
- `auth_mode=local_dev_open`.

Decision validada:

- El fallback `dev_only` quedo confirmado.
- No habia sesion real en esta QA.
- El flujo validado fue local sin sesion con fallback controlado.

### D) Headers y metodos
Se confirmo que la UI no envio headers QA frontend a `/api/subscriptions`:

- No `X-User-Id`.
- No `X-Doctor-Id`.
- No `X-Entity-Type`.
- No `X-Entity-Id`.

Tambien se confirmo:

- No hubo POST a `/api/subscriptions`.
- No hubo PUT a `/api/subscriptions`.
- No hubo PATCH a `/api/subscriptions`.
- No hubo DELETE a `/api/subscriptions`.
- Solo se usaron metodos GET para suscripciones.

### E) Estado visual validado
Estado observado del panel:

- El panel abre correctamente.
- No se observo ruptura visual de navegacion/layout.
- `p-suscripcion` se activo via `showPanel('p-suscripcion')`.
- El boton lateral no estaba visible para click directo en headless; esto no afecto la QA del panel.
- Plan mostrado: `Gratuito`.
- Estado mostrado: `Plan base permanente`.
- Vigencia/vencimiento: `No aplica`.
- Dias restantes: `No aplica`.
- Plan contratado: `Sin plan contratado vigente`.
- Modo lectura visible.
- Fuente contexto visible: `dev_only`.

### F) Acciones comerciales
Se revisaron 7 controles comerciales y estaban:

- Deshabilitados.
- En modo lectura.
- O bloqueados.

Resultado:

- No ejecutaron writes.
- No dispararon llamadas de escritura a `/api/subscriptions`.
- Las acciones comerciales siguen bloqueadas.

### G) Consola y errores
Resultado de consola:

- No hubo error JS critico.
- El unico 401 observado fue el esperado de `context/current`.
- Hubo logs/debug y warnings no bloqueantes preexistentes.
- No hubo impacto en navegacion ni render del panel.

### H) Bloqueos vigentes
Siguen bloqueados:

- Writes de suscripcion.
- Crear suscripciones.
- Crear filas `free`.
- Contratacion.
- Aceptacion contractual.
- Renovacion.
- Cancelacion.
- Pagos.
- Facturacion.
- Conexion con `PublicProfilePlanCapabilities`.
- Capacidades productivas.
- Perfil publico.
- SEO productivo.
- Uso productivo sin sesion/contexto completo, ownership y permisos formales.

### I) Riesgos pendientes
Siguen pendientes:

- Validar flujo con sesion real productiva.
- Validar operador real con permiso persistido/canonico.
- Permiso persistido `subscriptions.read`.
- Ownership multi-entidad.
- Endpoint global `/api/me/context`, si se decide.
- Flujo contractual real.
- Aceptacion de contrato.
- Alta de suscripcion.
- Renovacion/cancelacion.
- Pagos/facturacion.
- Conexion futura con capacidades publicas.

### J) Secuencia recomendada
Opcion A - Refinamiento UX read-only:

- Microfase: `FE/UX-Suscripciones-PanelReadOnly-VisualPolish-01`.
- Objetivo: mejorar jerarquia visual, copy, mensajes y claridad del modo lectura, sin writes.

Opcion B - Diagnostico de flujo contractual:

- Microfase: `DIAG-Suscripciones-ContractFlow-Readiness-01`.
- Objetivo: diagnosticar contratacion real, aceptacion de contrato, vigencia, renovacion/cancelacion y pagos, sin implementar.

Opcion C - Diseno global de contexto activo:

- Microfase: `BE/DIAG-Auth-ActiveContext-GlobalEndpoint-01`.
- Objetivo: evaluar `/api/me/context` si otros modulos requieren contexto activo comun.

Opcion D - QA con sesion/operador real:

- Microfase: `QA-Suscripciones-PanelReadOnly-UseActiveContext-SessionOperator-01`.
- Objetivo: validar el flujo con sesion simulada/real y operador con/sin permiso, sin modificar UI.

Recomendacion:

- Antes de avanzar a writes contractuales, priorizar diagnostico de flujo contractual o QA de sesion/operador, manteniendo capacidades productivas bloqueadas.

### K) Limites de esta adenda
Esta adenda no activa:

- Uso productivo.
- Writes de suscripcion.
- Contratacion.
- Aceptacion contractual.
- Renovacion.
- Cancelacion.
- Pagos.
- Facturacion.
- `PublicProfilePlanCapabilities`.
- Capacidades productivas.
- Perfil publico.
- SEO productivo.

---

## Adenda PP-Decisiones 38 — Readiness del flujo contractual real de suscripciones

### A) Contexto
El bloque de suscripciones ya cuenta con piezas preparatorias importantes:

- Catalogo de planes `subscription_plans`.
- Tabla contractual base `profile_subscriptions`.
- Read-model actual de suscripcion.
- Endpoint privado de suscripcion actual:
  - `GET /api/subscriptions/index.php/entities/{entity_type}/{entity_id}/current`.
- Endpoint privado de contexto activo:
  - `GET /api/subscriptions/index.php/context/current`.
- Panel `p-suscripcion` en modo DEV/local read-only.
- QA visual/network del panel read-only con flujo contexto primero.

Todavia no existen writes reales de contratacion, aceptacion contractual, renovacion, cancelacion, pagos ni facturacion.

### B) Estado actual del modelo
Tablas existentes:

- `subscription_plans`:
  - Catalogo canonico de planes.
  - Incluye `free`, `basic`, `standard`, `optimum` y `professional`.
  - `free = lifetime / 0`.
  - Planes pagados = `annual / 365`.
- `profile_subscriptions`:
  - Tabla contractual/vigencia por entidad.
  - Actualmente sin registros reales.
  - No se crean filas `free`.

Campos existentes o conceptuales cubiertos por el schema actual:

- `plan_code`.
- `billing_period`.
- `duration_days`.
- `contract_version`.
- `contract_accepted_at`.
- `contract_accepted_by_user_id`.
- `contract_acceptance_source`.
- `contract_acceptance_ip`.
- `contract_acceptance_user_agent`.
- `starts_at`.
- `expires_at`.
- `grace_starts_at`.
- `grace_ends_at`.
- `status`.
- `auto_renew`.
- `cancelled_at`.
- `renewed_from_subscription_id`.
- `renewed_to_subscription_id`.
- `source`.
- `notes`.
- `deleted_at`.

### C) Gaps identificados
Antes de habilitar un flujo contractual real faltan:

- Endpoints write.
- Storage/auditoria separada de aceptacion contractual.
- Politica final de gracia.
- Politica de capacidades durante gracia.
- Unicidad fuerte de suscripcion vigente.
- Transacciones de alta.
- Permisos persistidos `subscriptions.read`.
- Pagos.
- Facturacion.
- Comprobantes.
- Eventos/historial contractual.
- Jobs de vencimiento y recordatorios.
- Conexion productiva con capacidades publicas.

### D) Read-model actual
Casos ya resueltos por el read-model:

- Sin suscripcion real devuelve `free_default`.
- Plan activo/candidato si existiera en `profile_subscriptions`.
- Vencido fuera de gracia resuelve `effective_plan_code=free`.
- Ventana de gracia si existen fechas/estado.
- Endpoint privado read-only de suscripcion actual.
- Endpoint contexto activo read-only.

Casos que siguen conceptuales o pendientes:

- Contratacion real.
- Aceptacion contractual real.
- Pago real.
- Creacion transaccional de suscripcion pagada.
- Renovacion.
- Cancelacion.
- Historial contractual.
- Capacidades publicas.
- UI productiva.

### E) UI actual
Datos reales que ya muestra `p-suscripcion`:

- Contexto activo.
- Suscripcion actual read-only.
- `effective_plan_code`.
- Estado.
- Vigencia.
- Gracia.
- Fuente/version.

Datos demo/placeholders:

- Catalogo visual.
- Precios `$0`.
- Features de planes.
- Frecuencia mensual/anual.
- Cupones.
- Facturacion.
- Historial.

Acciones bloqueadas:

- Renovar.
- Seleccionar plan.
- Cupon.
- Facturacion.
- Historial.
- Autorrenovacion.

Riesgo UI:

- Textos como `Mejora tu plan`, precios y facturacion pueden parecer comerciales reales si se desbloquean antes de tener backend write, contrato, pago y auditoria.

### F) Flujo contractual futuro propuesto
Seleccion de plan:

- Validar que el plan este activo en `subscription_plans`.
- Impedir contratar `free`.
- No crear filas `free`.
- Validar `plan_code`, `billing_period` y `duration_days`.
- Validar scope del medico u operador.

Aceptacion de contrato:

- Antes de activar plan, el usuario debe aceptar condiciones/contrato.
- Persistir:
  - Version de contrato.
  - Fecha de aceptacion.
  - Usuario que acepta.
  - Origen.
  - IP.
  - User-agent.
  - Preferiblemente hash o snapshot del contrato aceptado.

Inicio de vigencia:

- Fijar `starts_at`.
- Calcular `expires_at` una sola vez.
- Para plan anual pagado: 365 dias.
- Para plan `free`: `lifetime/0`, sin `expires_at`.

Plan efectivo:

- Si el plan pagado esta vigente:
  - `contracted_plan_code = plan pagado`.
  - `effective_plan_code = plan pagado`.
- Si vence fuera de gracia:
  - Conservar `contracted_plan_code` historico.
  - Resolver `effective_plan_code=free`.
  - No borrar datos.

Periodo de gracia:

- Falta definir si aplica por plan.
- Falta definir cuantos dias dura.
- Falta definir si se persiste o se calcula.
- Falta definir que capacidades se mantienen.
- Falta definir que capacidades se retiran.
- Falta definir copy para el usuario.

Renovacion:

- Falta definir renovacion manual.
- Falta definir auto-renew futuro.
- Falta definir si renueva desde vencimiento o desde fecha de pago.
- Falta definir si crea nueva fila o actualiza la existente.
- Falta definir relacion con `renewed_from_subscription_id` / `renewed_to_subscription_id`.

Cancelacion:

- Falta definir cancelacion inmediata.
- Falta definir cancelacion al final del periodo.
- Falta definir cancelacion administrativa.
- Falta definir efecto en `effective_plan_code`.
- Falta definir copy mostrado al usuario.

Pagos/facturacion:

- Falta definir tabla de pagos.
- Falta definir tabla de facturas/invoices.
- Falta definir comprobantes.
- Falta definir pago offline/manual.
- Falta definir pasarela futura.
- Falta definir conciliacion.
- Falta definir QA fiscal.
- Pagos/facturacion quedan fuera de la etapa inicial.

### G) Endpoints futuros propuestos
Read-only existentes:

- `GET /api/subscriptions/index.php/context/current`.
- `GET /api/subscriptions/index.php/entities/{entity_type}/{entity_id}/current`.

Endpoints futuros conceptuales, no implementados:

- `POST /api/subscriptions/index.php/entities/{entity_type}/{entity_id}/checkout-intent`:
  - Write preparatorio.
  - Requiere plan activo, scope y contrato de pagos.
  - No implementar todavia.
- `POST /api/subscriptions/index.php/entities/{entity_type}/{entity_id}/contract-acceptance`:
  - Write de aceptacion contractual.
  - Requiere version contractual y auditoria.
  - No implementar todavia.
- `POST /api/subscriptions/index.php/entities/{entity_type}/{entity_id}/subscriptions`:
  - Write de alta.
  - Requiere aceptacion y pago/criterio comercial.
  - No implementar todavia.
- `POST /api/subscriptions/index.php/subscriptions/{subscription_id}/renew`:
  - Write futuro.
  - Requiere politica de renovacion.
- `POST /api/subscriptions/index.php/subscriptions/{subscription_id}/cancel`:
  - Write futuro.
  - Requiere politica de cancelacion.
- `GET /api/subscriptions/index.php/entities/{entity_type}/{entity_id}/history`:
  - Read-only futuro.
  - Requiere eventos/historial.

### H) Tablas futuras propuestas
`subscription_contract_acceptances`:

- Recomendable antes de writes reales.
- Objetivo: auditar aceptacion contractual.
- Campos minimos: entidad, usuario, `contract_version`, `accepted_at`, `source`, IP, user-agent y snapshot/hash.

`subscription_payments`:

- Necesaria antes de pagos.
- Objetivo: registrar pagos, estado, referencia, metodo y conciliacion.

`subscription_invoices`:

- Necesaria antes de facturacion.
- Objetivo: registrar facturas/invoices, estado y datos CFDI/fiscales si aplica.

`subscription_events`:

- Recomendable para auditoria/historial.
- Objetivo: registrar alta, renovacion, cancelacion, vencimiento, gracia y fallback.

`subscription_coupons`:

- Posterior, solo si habra descuentos reales.

`subscription_plan_prices`:

- Posterior, si precios/moneda cambian por periodo.

`subscription_renewals`:

- Opcional.
- Puede iniciar con links en `profile_subscriptions`.

### I) Relacion con capacidades publicas
Decision vigente:

- `PublicProfilePlanCapabilities` no debe conectarse todavia.
- En el futuro debe recibir un `effective_plan_code` confiable.
- Perfil publico no debe consumir datos privados ni plan legacy como contrato.
- Capacidades productivas deben esperar:
  - Plan efectivo productivo.
  - Vigencia/gracia definida.
  - Ownership/scope suficiente.
  - QA.
- SEO productivo no debe tocarse todavia.

### J) Riesgos documentados
Riesgos antes de habilitar writes:

- Activar plan sin aceptacion contractual.
- Recalcular `expires_at` dinamicamente.
- Tratar `free` como anual.
- Crear filas `free`.
- Borrar datos al vencer.
- Activar capacidades antes de pago/contrato.
- Desbloquear botones antes del backend.
- Permitir operador sin permiso.
- No auditar aceptacion.
- No conservar historico.
- Confundir plan contratado con plan efectivo.
- Implementar pagos sin facturacion clara.
- Activar renovaciones sin politica definida.

### K) Secuencia de microfases recomendada
Secuencia segura propuesta:

1. `DOCS-Suscripciones-ContractFlow-Readiness-01`.
2. `DB/DIAG-Suscripciones-ContractAcceptance-StorageDecision-01`.
3. `DB-Suscripciones-ContractAcceptance-CreateSchemaDraft-01`.
4. `BE/DIAG-Suscripciones-CheckoutIntent-Design-01`.
5. `BE-Suscripciones-ContractAcceptance-ReadWriteEndpoint-Guarded-01`.
6. `BE-Suscripciones-SubscriptionCreate-Guarded-01`.
7. `QA-Suscripciones-SubscriptionCreate-NoFreeRows-01`.
8. `FE-Suscripciones-PanelContractFlow-DevOnly-01`.
9. `DOCS-Suscripciones-ContractFlow-Cierre-01`.

### L) Conclusion
El flujo no esta listo para writes.

Antes de crear suscripciones reales falta:

- Storage/auditoria de aceptacion.
- Endpoint write con guard.
- Validacion de plan activo.
- Transacciones.
- Permisos.
- Garantia de no crear filas `free`.

Antes de pagos falta:

- Modelo de pagos.
- Facturas/comprobantes.
- Politica offline/pasarela.
- Conciliacion.
- QA fiscal.

Antes de capacidades falta:

- Plan efectivo productivo confiable.
- Gracia definida.
- Ownership/scope.
- Conexion controlada con `PublicProfilePlanCapabilities`.

Siguiente microfase recomendada:

- `DB/DIAG-Suscripciones-ContractAcceptance-StorageDecision-01`.

### M) Limites de esta adenda
Esta adenda no implementa:

- Backend.
- UI.
- DB.
- SQL.
- Endpoints nuevos.
- Writes.
- Pagos.
- Facturacion.
- `PublicProfilePlanCapabilities`.
- Capacidades productivas.
- Contratacion.
- Renovacion.
- Cancelacion.
- SEO productivo.

---

## Adenda PP-Decisiones 39 — Decisión de almacenamiento de aceptación contractual de suscripciones

### A) Contexto
El bloque de suscripciones ya cuenta con las piezas read-only y documentales necesarias para decidir el almacenamiento de la aceptacion contractual antes de cualquier write real:

- Ya existe `subscription_plans`.
- Ya existe `profile_subscriptions`.
- Ya existe read-model actual de suscripcion.
- Ya existe endpoint privado de suscripcion actual:
  - `GET /api/subscriptions/index.php/entities/{entity_type}/{entity_id}/current`.
- Ya existe endpoint privado de contexto activo:
  - `GET /api/subscriptions/index.php/context/current`.
- Ya existe panel `p-suscripcion` read-only DEV/local.
- Todavia no existen writes reales de contratacion.
- Todavia no existe tabla separada de aceptacion contractual.

La microfase `DB/DIAG-Suscripciones-ContractAcceptance-StorageDecision-01` cerro con PASS y diagnostico donde conviene almacenar la aceptacion contractual antes de crear cualquier endpoint write.

### B) Estado actual del schema
`profile_subscriptions` ya contempla campos embebidos de aceptacion contractual:

- `contract_version`.
- `contract_accepted_at`.
- `contract_accepted_by_user_id`.
- `contract_acceptance_source`.
- `contract_acceptance_ip`.
- `contract_acceptance_user_agent`.
- `source`.
- `notes`.
- `created_at`.
- `updated_at`.
- `deleted_at`.

Limitaciones del enfoque actual si se usa como unico storage:

- Una sola aceptacion embebida por suscripcion.
- No conserva reaceptaciones.
- No hay `contract_hash`.
- No hay snapshot o URL del contrato aceptado.
- No hay actor/operator detallado.
- No hay tabla auditable separada.
- El read-model actual solo expone `contract_accepted_at`.

### C) Opciones evaluadas
Opcion A — Solo campos embebidos en `profile_subscriptions`:

Ventajas:

- Menor complejidad.
- No requiere tabla adicional.
- Menos joins.
- Suficiente para una primera lectura operativa simple.

Riesgos:

- Auditoria legal debil.
- Reaceptaciones dificiles.
- Cambios de contrato dificiles.
- Operador/admin poco trazable.
- Snapshot/hash no natural.
- Mezcla aceptacion con alta/renovacion.

Conclusion:

- Es suficiente solo para un MVP muy simple.
- Es insuficiente para renovacion, cambio de condiciones, operador, soporte administrativo y evidencia legal robusta.

Opcion B — Tabla separada `subscription_contract_acceptances`:

Tabla candidata:

- `subscription_contract_acceptances`.

Campos conceptuales:

- `id`.
- `uuid`.
- `entity_type`.
- `entity_id`.
- `doctor_id`.
- `profile_id`.
- `subscription_id`.
- `plan_code`.
- `billing_period`.
- `contract_version`.
- `contract_hash`.
- `contract_snapshot_url`.
- `accepted_at`.
- `accepted_by_user_id`.
- `accepted_by_actor_role`.
- `accepted_by_operator_id`.
- `acceptance_source`.
- `ip_address`.
- `user_agent`.
- `status`.
- `source`.
- `notes`.
- `created_at`.
- `updated_at`.
- `deleted_at`.

Ventajas:

- Auditoria fuerte.
- Multiples aceptaciones.
- Cambios de contrato.
- Renovacion/reaceptacion.
- Evidencia legal.
- Relacion futura con pagos, checkout y eventos.

Riesgos:

- Mas complejidad.
- Requiere transacciones.
- Requiere evitar aceptaciones huerfanas.
- Requiere definir relacion con suscripcion.
- Requiere definir fuente de verdad.

Conclusion:

- Es suficiente para auditoria legal.
- Como unica fuente puede complicar el read-model operativo.

Opcion C — Hibrida:

Descripcion:

- `profile_subscriptions` conserva snapshot minimo operativo.
- `subscription_contract_acceptances` guarda evidencia/auditoria completa.

Ventajas:

- Read-model simple.
- Auditoria fuerte.
- Trazabilidad legal.
- Soporte de reaceptaciones.
- Balance entre MVP y crecimiento contractual.

Riesgos:

- Duplicacion controlada.
- Requiere sincronia transaccional.
- Requiere reglas claras de consistencia.

Fuente de verdad:

- `subscription_contract_acceptances` = evidencia legal/auditoria.
- `profile_subscriptions` = snapshot operacional de vigencia y lectura.

### D) Decision recomendada
Decision:

- Adoptar Opcion C — enfoque hibrido.

Motivo:

- Mexico Medico manejara planes comerciales.
- La aceptacion debe ocurrir antes de activar un plan.
- Debe existir evidencia contractual.
- En el futuro puede haber operadores/admin.
- En el futuro habra pagos/facturacion.
- En el futuro pueden cambiar condiciones/contratos.
- No conviene sacrificar auditoria legal.
- Tampoco conviene complicar el read-model operativo.

### E) Snapshot minimo en `profile_subscriptions`
`profile_subscriptions` debe conservar un snapshot minimo para operacion y lectura:

- `contract_version`.
- `contract_accepted_at`.
- `contract_accepted_by_user_id`.
- `contract_acceptance_source`.

Campo opcional futuro:

- `contract_acceptance_id`.

Proposito:

- Lectura rapida.
- Read-model simple.
- Vigencia operativa.
- Mostrar datos minimos al usuario.

### F) Evidencia completa en `subscription_contract_acceptances`
La tabla separada debe guardar evidencia completa:

- Entidad.
- Usuario.
- Actor/operator.
- Plan.
- Periodo.
- Version de contrato.
- Hash/snapshot.
- IP.
- User-agent.
- Timestamps.
- Estado.
- Source.
- Notas.

Proposito:

- Evidencia legal.
- Auditoria.
- Soporte de reaceptaciones.
- Soporte de cambios de contrato.
- Relacion futura con pagos/checkout/eventos.

### G) Lo que no se implementa todavia
Esta decision no implementa:

- SQL.
- Tabla.
- Endpoints write.
- Contratacion.
- Aceptacion real.
- Renovacion.
- Cancelacion.
- Pagos.
- Facturacion.
- Conexion con `PublicProfilePlanCapabilities`.
- Capacidades productivas.
- Perfil publico.
- SEO productivo.

### H) Relacion con flujo futuro
Flujo conceptual futuro:

1. Usuario selecciona plan pagado.
2. Backend valida que el plan este activo.
3. Se impide contratar `free`.
4. No se crean filas `free`.
5. Usuario acepta contrato.
6. Se crea evidencia en `subscription_contract_acceptances`.
7. Se crea `profile_subscriptions` transaccionalmente con snapshot y vigencia congelada.
8. Read-model sigue leyendo `profile_subscriptions`.
9. Auditoria consulta `subscription_contract_acceptances`.

### I) Riesgos documentados
Riesgos que debe controlar el diseno futuro:

- Activar plan sin aceptacion.
- No guardar IP/user-agent/hash.
- Perder auditoria si solo se embebe.
- Crear aceptacion sin suscripcion.
- Crear suscripcion sin aceptacion.
- Permitir multiples activas sin control.
- Aceptar por operador sin permiso.
- Recalcular fechas contractuales.
- Tratar `free` como contrato pagado.
- Mezclar pagos con aceptacion sin diseno claro.
- Conectar capacidades antes de aceptacion/vigencia.

### J) Secuencia recomendada
Secuencia segura propuesta:

1. `DOCS-Suscripciones-ContractAcceptance-StorageDecision-01`.
2. `DB-Suscripciones-ContractAcceptance-CreateSchemaDraft-01`.
3. `DB/DIAG-Suscripciones-ContractAcceptance-ConstraintsDecision-01`.
4. `DB-Suscripciones-ContractAcceptance-CreateSchemaExecutable-01`.
5. `QA-Suscripciones-ContractAcceptance-SchemaStaticReview-01`.
6. `BE/DIAG-Suscripciones-ContractAcceptance-EndpointDesign-01`.

### K) Conclusion
Decision de cierre:

- Si hace falta tabla separada.
- Si conviene enfoque hibrido.
- No esta listo para SQL ejecutable todavia.
- Primero debe documentarse esta decision.

Siguiente microfase recomendada:

- `DB-Suscripciones-ContractAcceptance-CreateSchemaDraft-01`.

### L) Limites de esta adenda
Esta adenda no implementa:

- Backend.
- UI.
- DB.
- SQL.
- Endpoints.
- Writes.
- Pagos.
- Facturacion.
- `PublicProfilePlanCapabilities`.
- Capacidades productivas.
- Contratacion.
- Renovacion.
- Cancelacion.
- SEO productivo.

---

## Adenda PP-Decisiones 40 — Constraints de aceptación contractual de suscripciones

### A) Contexto
Ya existe la decision de almacenamiento hibrido para aceptacion contractual de suscripciones:

- `profile_subscriptions` conserva el snapshot operativo/read-model.
- `subscription_contract_acceptances` conserva la auditoria/evidencia legal completa.

Tambien existe el draft SQL versionado:

- `modules/profiles/db/2026_06_20_create_subscription_contract_acceptances_draft.sql`.

Ese draft no fue ejecutado, no creo tablas y no modifico DB local. Antes de convertirlo en SQL ejecutable, la microfase `DB/DIAG-Suscripciones-ContractAcceptance-ConstraintsDecision-01` diagnostico constraints, tipos, nullability, indices y reglas de integridad. La conclusion fue que el draft requiere ajustes antes de convertirse en ejecutable.

### B) Decision sobre FKs reales
La primera version ejecutable no debe usar FKs reales.

Reglas:

- Validar relaciones por backend.
- Mantener indices para relaciones futuras.
- Evitar fragilidad mientras evolucionan multi-entidad, ownership, operadores, linkage perfil/suscripcion y migraciones futuras.

Aplica para:

- `subscription_id`.
- `doctor_id`.
- `profile_id`.
- `accepted_by_user_id`.
- `accepted_by_operator_id`.

### C) Tipos alineados con `profile_subscriptions`
Antes del ejecutable se debe alinear el draft con el patron vigente de `profile_subscriptions`:

- `subscription_id` debe ser `CHAR(36) NULL`, porque apunta conceptualmente a `profile_subscriptions.subscription_id`.
- `entity_id` debe ser `VARCHAR(64) NOT NULL`, alineado con `profile_subscriptions.entity_id`.
- `doctor_id` debe ser `VARCHAR(64) NULL`, alineado con `profile_subscriptions.doctor_id`.
- `profile_id` debe ser `VARCHAR(64) NULL`, alineado con `profile_subscriptions.profile_id`.

Esta decision reemplaza el uso inicial de `BIGINT UNSIGNED` en el draft para esos campos.

### D) `subscription_id` nullable
`subscription_id` debe mantenerse nullable en la primera version.

Motivo:

- La aceptacion puede crearse antes o dentro de la misma transaccion que crea `profile_subscriptions`.
- El backend futuro debe enlazarla transaccionalmente cuando la suscripcion se cree.

Riesgo:

- Aceptaciones huerfanas.

Mitigaciones futuras:

- Estado `pending_link`.
- Validacion transaccional.
- Cleanup controlado.
- No activar plan sin suscripcion enlazada.

### E) Estados conceptuales
`status` debe mantenerse como `VARCHAR`, sin `ENUM` y sin `CHECK`.

Estados conceptuales iniciales:

- `accepted`.
- `pending_link`.
- `superseded`.
- `void`.
- `expired`.
- `cancelled`.

Significado:

- `accepted`: evidencia activa/principal.
- `pending_link`: aceptacion aun no enlazada a suscripcion.
- `superseded`: reaceptacion o cambio de contrato posterior.
- `void`: anulacion controlada.
- `expired`: evidencia vencida por contexto contractual.
- `cancelled`: flujo cancelado.

Las reglas de transicion deben validarse por backend.

### F) `uuid`
Decision:

- `uuid CHAR(36) NOT NULL`.
- `UNIQUE KEY ux_subscription_contract_acceptances_uuid`.

`uuid` es suficiente como identificador publico/externo del registro. No se requiere otro identificador publico en esta fase.

### G) Unicidad
No se deben agregar unicidades adicionales en la primera version:

- No unique por `entity_type/entity_id/contract_version`.
- No unique por `subscription_id`.
- No unique por entidad/contrato.

Motivo:

- Pueden existir multiples aceptaciones.
- Puede existir reaceptacion.
- Pueden cambiar condiciones.
- Una suscripcion podria tener mas de una aceptacion asociada si cambian terminos.

La aceptacion principal se resolvera por backend/status, no por unique constraint inicial.

### H) Plan, periodo y duracion
Decision:

- `plan_code` y `billing_period` deben mantenerse `NOT NULL`.
- Se validan contra `subscription_plans` por backend.
- No debe existir FK real inicial.
- Sirven como snapshot historico aunque cambie el catalogo.
- `duration_days INT UNSIGNED NOT NULL DEFAULT 0`.

Reglas:

- Planes pagados anuales actuales usan 365 dias.
- `free` no debe pasar por contratacion.
- Backend futuro debe bloquear `plan_code=free` en el flujo normal.

### I) Contrato: hash, snapshot y titulo
Nullability inicial:

- `contract_hash` nullable.
- `contract_snapshot_url` nullable.
- `contract_title` nullable.

Para produccion real, el endpoint write deberia exigir:

- `contract_version`.
- `contract_hash`.
- Snapshot o evidencia equivalente.

El schema inicial puede permitir null para facilitar migraciones y etapas controladas, pero el uso productivo debe endurecerse en backend.

### J) Evidencia tecnica
Decision:

- `ip_address VARCHAR(45) NULL`.
- `user_agent VARCHAR(512) NULL`.

`ip_address` cubre IPv4 e IPv6. `user_agent` de 512 caracteres es aceptable para la fase inicial. No deben almacenarse datos sensibles adicionales innecesarios.

### K) Actor y fuente
`accepted_by_actor_role VARCHAR(32) NULL`.

Valores conceptuales:

- `doctor`.
- `operator`.
- `admin`.
- `system`.

`acceptance_source VARCHAR(64) NOT NULL`.

Valores conceptuales:

- `panel_subscription`.
- `admin_panel`.
- `checkout`.
- `migration`.
- `system`.

No usar `ENUM`. La validacion corresponde al backend. Los operadores requeriran permisos futuros explicitos.

### L) Soft delete
Mantener `deleted_at`.

Reglas:

- La evidencia legal no debe borrarse fisicamente en el flujo normal.
- `deleted_at` solo sirve para ocultamiento logico o correccion controlada.
- Anulaciones reales deben quedar auditadas con `status`, `notes` y timestamps.

### M) Timestamps
Decision:

- `accepted_at DATETIME NOT NULL`.
- `created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP`.
- `updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`.

Compatibilidad:

- Compatible con MySQL/MariaDB usado localmente.
- `accepted_at` representa el momento contractual.
- `created_at` y `updated_at` representan auditoria tecnica.

### N) Indices
Indices base:

- Unique `uuid`.
- `entity_type`, `entity_id`.
- `doctor_id`.
- `profile_id`.
- `subscription_id`.
- `plan_code`, `billing_period`.
- `contract_version`.
- `accepted_at`.
- `accepted_by_user_id`.
- `status`.
- `deleted_at`.

Indices compuestos adicionales recomendados antes del ejecutable:

- `(entity_type, entity_id, accepted_at)`.
- `(subscription_id, status)`.
- `(doctor_id, accepted_at)`.

Convencion:

- Acortar nombres a `idx_sub_contract_acceptances_*`.
- Mantener `ux_subscription_contract_acceptances_uuid` si no excede el limite.
- Cuidar el limite de 64 caracteres de MySQL.

### O) Relacion con `profile_subscriptions`
Decision:

- No agregar todavia `contract_acceptance_id` a `profile_subscriptions`.
- Evaluarlo en microfase posterior si se necesita link inverso rapido.
- Por ahora, `subscription_id` en `subscription_contract_acceptances` sera el enlace principal hacia `profile_subscriptions.subscription_id`.
- El read-model seguira leyendo `profile_subscriptions`.
- La auditoria legal consultara `subscription_contract_acceptances`.

### P) Relacion con pagos y checkout
No incluir campos de pago en `subscription_contract_acceptances`.

Reglas:

- Aceptacion contractual y pago son dominios distintos.
- Futuras tablas de pago o checkout deberan enlazar por `subscription_id`, `acceptance_id` o checkout intent, segun diseno posterior.
- No implementar pagos todavia.

### Q) Relacion con `free`
Decision vigente:

- `free` no se contrata.
- No se crean aceptaciones para `free` por default.
- No se crean filas `free`.
- Si aparece `plan_code=free`, debe ser caso excepcional/migracion y no flujo normal.
- Backend futuro debe bloquearlo.

### R) Riesgos documentados
Riesgos a controlar antes de SQL ejecutable y writes:

- FK real prematura.
- Aceptacion huerfana.
- Multiples aceptaciones sin estado claro.
- Falta de hash/snapshot en produccion.
- Operador sin permiso.
- Datos sensibles excesivos.
- Mezclar pagos con aceptacion.
- No relacionar aceptacion con suscripcion.
- Borrar evidencia legal.
- Crear aceptacion para `free`.
- No auditar cambios de contrato.
- No alinear tipos con `profile_subscriptions`.

### S) Conclusion
El draft requiere ajustes antes de ejecutable.

Ajustes requeridos:

- Alinear tipos con `profile_subscriptions`.
- Cambiar `subscription_id` a `CHAR(36) NULL`.
- Cambiar `entity_id`, `doctor_id` y `profile_id` a `VARCHAR(64)`.
- Acortar nombres de indices.
- Agregar indices compuestos.
- Documentar estados conceptuales.
- Mantener sin FKs reales.
- Mantener sin `ENUM` ni `CHECK`.

El draft no esta listo para SQL ejecutable todavia.

Siguiente microfase recomendada:

- `DB-Suscripciones-ContractAcceptance-UpdateSchemaDraft-01`.

### T) Limites de esta adenda
Esta adenda no implementa:

- Backend.
- UI.
- DB.
- SQL.
- Cambios al draft SQL.
- Endpoints.
- Writes.
- Pagos.
- Facturacion.
- `PublicProfilePlanCapabilities`.
- Capacidades productivas.
- Contratacion.
- Renovacion.
- Cancelacion.
- SEO productivo.

---

## Adenda PP-Decisiones 41 — Readiness para SQL ejecutable de aceptación contractual de suscripciones

### A) Microfase diagnóstica cerrada
La microfase `DB/DIAG-Suscripciones-ContractAcceptance-ExecutableReadiness-01` cerró con PASS sin cambios.

Conclusiones:

- El draft actual puede ser base para un SQL ejecutable final.
- No se requiere ajuste adicional del draft.
- No se identificó riesgo bloqueante.
- Conviene documentar esta decisión antes de crear el SQL ejecutable.
- Esta adenda no crea el SQL ejecutable y no ejecuta SQL.

### B) Archivo draft evaluado
Archivo evaluado:

- `modules/profiles/db/2026_06_20_create_subscription_contract_acceptances_draft.sql`.

Estado confirmado:

- Sigue marcado como `DRAFT ONLY`.
- No fue ejecutado.
- No modificó DB.
- No crea seeds.
- No crea FKs reales.
- No usa `ENUM`.
- No usa `CHECK`.
- No activa contratación.
- No activa pagos.
- No activa capacidades productivas.

### C) Decisión de readiness
El draft queda aprobado como base para una futura microfase de creación del SQL ejecutable final.

Condiciones:

- El SQL ejecutable se creará en una microfase separada.
- El SQL ejecutable no deberá ejecutarse automáticamente.
- La ejecución contra DB local o remota requerirá una microfase posterior explícita.
- La tabla futura seguirá siendo `subscription_contract_acceptances`.
- La tabla futura será auditoría/evidencia legal de aceptación contractual.
- `profile_subscriptions` seguirá siendo el snapshot operativo/read-model.

### D) Decisiones confirmadas
Se confirma el enfoque híbrido:

- `subscription_contract_acceptances` = evidencia legal/auditoría.
- `profile_subscriptions` = snapshot operativo/read-model.

Decisiones técnicas confirmadas:

- `subscription_id CHAR(36) NULL` queda como enlace conceptual hacia `profile_subscriptions.subscription_id`.
- No se agrega todavía `contract_acceptance_id` a `profile_subscriptions`.
- No usar FKs reales en la primera versión ejecutable.
- No usar `ENUM`.
- No usar `CHECK`.
- La validación de relaciones, estados, fuentes y permisos queda para backend futuro.

Estados conceptuales:

- `accepted`.
- `pending_link`.
- `superseded`.
- `void`.
- `expired`.
- `cancelled`.

Roles conceptuales:

- `doctor`.
- `operator`.
- `admin`.
- `system`.

Fuentes conceptuales:

- `panel_subscription`.
- `admin_panel`.
- `checkout`.
- `migration`.
- `system`.

### E) Relación con `free`
Decisión vigente:

- `free` no se contrata.
- No se crean filas `free`.
- No se crean aceptaciones contractuales `free` por default.
- `free_default` sigue siendo fallback/read-model cuando no hay suscripción real.
- Backend futuro debe bloquear aceptación o contratación normal de `free`.

### F) Pagos, checkout y capacidades
Pagos, checkout y facturación son dominios separados.

Reglas:

- No se agregan campos de pago en `subscription_contract_acceptances`.
- No se conecta `PublicProfilePlanCapabilities`.
- No se activan capacidades productivas.
- No se toca perfil público.
- No se toca SEO productivo.

Secuencia futura correcta:

1. Storage.
2. Aceptación.
3. Suscripción real.
4. Vigencia.
5. Read-model.
6. QA.
7. Capacidades en microfase separada.

### G) Campos potenciales evaluados como no bloqueantes
Los siguientes campos se evaluaron y no bloquean la creación del SQL ejecutable final:

- `legal_terms_url`: no bloqueante; queda cubierto inicialmente por `contract_snapshot_url`.
- `contract_locale`: no bloqueante; puede agregarse en el futuro si hay multi-idioma.
- `accepted_by_display_name`: no bloqueante; se evita duplicar PII.
- `accepted_by_email`: no bloqueante; se evita duplicar PII.
- `consent_text`: no bloqueante; `contract_version`, `contract_hash` y snapshot cubren la fase inicial.
- `acceptance_method`: no bloqueante; `acceptance_source` cubre el origen inicial.
- `request_id`: no bloqueante; útil futuro para trazabilidad.
- `previous_acceptance_id`: no bloqueante; puede resolverse por entidad, suscripción, fecha y `status`.
- `superseded_by_acceptance_id`: no bloqueante; útil futuro.
- `void_reason`: no bloqueante; `status` y `notes` cubren la fase inicial.
- `metadata_json`: no bloqueante; se evita una bolsa genérica antes de necesitarla.

### H) Riesgos pendientes para backend futuro
Antes de writes reales, el backend futuro debe:

- Exigir `contract_version`.
- En producción, exigir `contract_hash` y/o snapshot/evidencia equivalente.
- Enlazar `subscription_id` transaccionalmente.
- Evitar aceptaciones huérfanas.
- Bloquear `free`.
- Validar el plan contra `subscription_plans`.
- Validar permisos de operador.
- No activar plan sin aceptación válida.
- No crear suscripción real sin aceptación válida.
- No borrar físicamente evidencia legal en el flujo normal.

### I) Próxima microfase recomendada
Siguiente microfase recomendada:

- `DB-Suscripciones-ContractAcceptance-CreateSchemaExecutable-01`.

Objetivo futuro:

- Crear el archivo SQL ejecutable final de `subscription_contract_acceptances`, sin ejecutarlo todavía.

### J) Límites de esta adenda
Esta adenda no implementa:

- SQL ejecutable.
- Ejecución SQL.
- Cambios DB.
- Backend.
- Frontend.
- Writes.
- Pagos.
- Facturación.
- `PublicProfilePlanCapabilities`.
- Capacidades productivas.
- Contratación.
- Aceptación real.
- Renovación.
- Cancelación.
- Perfil público.
- SEO productivo.

---

## Adenda PP-Decisiones 42 — Cierre del SQL ejecutable de aceptación contractual de suscripciones

### A) Microfases cerradas
Quedan cerradas las microfases:

- `DB-Suscripciones-ContractAcceptance-CreateSchemaExecutable-01`: PASS.
- `QA-Suscripciones-ContractAcceptance-SchemaExecutable-PostPush-01`: PASS sin cambios.

Commit remoto/alineado validado:

- `947bad6 db(suscripciones): crea SQL ejecutable de aceptacion contractual`.

### B) Estado del archivo SQL
Archivo creado y versionado:

- `modules/profiles/db/2026_06_20_create_subscription_contract_acceptances.sql`.

Estado confirmado:

- Existe.
- Está versionado.
- Es SQL ejecutable.
- Contiene `CREATE TABLE IF NOT EXISTS subscription_contract_acceptances`.
- No fue ejecutado.
- No modificó DB.
- No creó tabla en DB local.
- No crea seeds.
- No modifica tablas existentes.
- No modifica `profile_subscriptions`.
- No crea FKs reales.
- No usa `ENUM`.
- No usa `CHECK`.

### C) Decisión arquitectónica cerrada
Se confirma el enfoque híbrido:

- `subscription_contract_acceptances` = auditoría/evidencia legal.
- `profile_subscriptions` = snapshot operativo/read-model.

Decisiones técnicas vigentes:

- `subscription_id CHAR(36) NULL` queda como enlace conceptual hacia `profile_subscriptions.subscription_id`.
- No se agrega todavía `contract_acceptance_id` a `profile_subscriptions`.
- Las relaciones se validarán por backend futuro.
- La tabla de aceptación contractual no sustituye el read-model operativo.

### D) Alcance bloqueado
Este cierre no incluye:

- Ejecución SQL.
- Modificación DB.
- Creación real de tabla.
- Endpoint write.
- Aceptación contractual real.
- Contratación real.
- Renovación.
- Cancelación.
- Checkout.
- Pagos.
- Facturación.
- Conexión con `PublicProfilePlanCapabilities`.
- Activación de capacidades productivas.
- Cambios de perfil público.
- Cambios SEO productivos.

### E) Relación con `free`
Decisión vigente:

- `free` sigue siendo fallback/read-model.
- `free` no se contrata.
- No se crean filas `free`.
- No se crean aceptaciones contractuales `free` por default.
- Backend futuro debe bloquear aceptación o contratación normal de `free`.

### F) Condición antes de ejecutar SQL
Antes de ejecutar el SQL en DB local o cualquier entorno se requiere una microfase posterior explícita con:

- Precondición Git limpia.
- Revisión del archivo ejecutable.
- Validación del entorno DB.
- Respaldo o confirmación de entorno local según aplique.
- Ejecución controlada.
- Verificación de tabla creada.
- Verificación de que no se insertaron datos.
- QA post-ejecución.

### G) Siguiente microfase recomendada
Siguiente microfase recomendada:

- `DB/DIAG-Suscripciones-ContractAcceptance-ExecutionReadiness-01`.

Objetivo:

- Diagnosticar si ya es seguro ejecutar el SQL en DB local/dev, sin ejecutarlo todavía.

### H) Límites de esta adenda
Esta adenda no implementa:

- SQL adicional.
- Ejecución SQL.
- Cambios DB.
- Backend.
- Frontend.
- Writes.
- Pagos.
- Facturación.
- `PublicProfilePlanCapabilities`.
- Capacidades productivas.
- Contratación.
- Aceptación real.
- Renovación.
- Cancelación.
- Perfil público.
- SEO productivo.

---

## Adenda PP-Decisiones 43 — Readiness de ejecución del SQL de aceptación contractual de suscripciones

### A) Microfase diagnóstica cerrada
La microfase `DB/DIAG-Suscripciones-ContractAcceptance-ExecutionReadiness-01` cerró con PASS sin cambios.

Conclusiones:

- El SQL ejecutable está listo para preparar una futura microfase de ejecución local/dev.
- No debe ejecutarse todavía desde esta fase documental.
- No requiere ajuste del SQL.
- No se identificó riesgo bloqueante.
- Conviene documentar primero las condiciones de ejecución.

### B) Estado actual del SQL ejecutable
Archivo evaluado:

- `modules/profiles/db/2026_06_20_create_subscription_contract_acceptances.sql`.

Estado confirmado:

- Existe.
- Está versionado.
- Es ejecutable.
- No ha sido ejecutado.
- No ha modificado DB.
- No ha creado tabla en DB local/dev.
- No crea seeds.
- No modifica `profile_subscriptions`.
- No crea FKs reales.
- No usa `ENUM`.
- No usa `CHECK`.

### C) Diagnóstico del entorno DB
El proyecto tiene una forma conocida de conexión a DB local/dev:

- `api/_lib/db.php`.
- `api/mxmed-db.config.php`.
- Variables de entorno `MXMED_DB_*`.

Reglas de documentación:

- No exponer credenciales en este documento.
- La ejecución futura debe restringirse a entorno local/dev.
- Debe confirmarse explícitamente el nombre de la DB.
- Debe confirmarse explícitamente que no es producción.
- La convención del proyecto usa scripts SQL versionados y ejecución manual/controlada por microfase.

### D) Condiciones obligatorias antes de ejecutar
La futura microfase de ejecución deberá exigir:

- Git limpio y rama alineada.
- Confirmación explícita de entorno local/dev.
- Confirmación explícita del nombre de la base de datos.
- Confirmación explícita de no producción.
- Revisión del archivo SQL ejecutable correcto, sin usar el archivo `_draft`.
- Ejecución controlada una sola vez.
- Captura de salida completa.
- Verificación posterior de tabla creada.
- Verificación posterior de estructura esperada.
- Verificación posterior de cero registros.
- Confirmación de que no se insertaron seeds.
- Confirmación de que no se modificó backend/UI.
- Confirmación de que no se activaron contratación, pagos ni capacidades.

### E) Riesgos identificados antes de ejecutar
Riesgos a controlar:

- Tabla preexistente con estructura distinta.
- DB equivocada.
- Ejecución en producción por error.
- Falta de permisos `CREATE`.
- Diferencias menores de engine/collation.
- Confusión entre draft y ejecutable.
- Interpretar la creación de tabla como activación funcional.
- Conexión backend prematura si se pierde el alcance de la fase.

### F) Decisión actual
Decisión:

- Sí está listo para preparar una microfase de ejecución local/dev.
- No debe ejecutarse todavía desde esta fase.
- No requiere ajuste del SQL antes de planear ejecución.
- No requiere nueva decisión de schema.
- Sí requiere microfase posterior explícita de ejecución controlada.

### G) Relación con `free` y alcance bloqueado
Decisión vigente:

- `free` sigue siendo fallback/read-model.
- `free` no se contrata.
- No se crean filas `free`.
- No se crean aceptaciones `free` por default.

Sigue bloqueado:

- Contratación real.
- Aceptación contractual real.
- Pagos.
- Checkout.
- Facturación.
- Capacidades productivas.
- Conexión con `PublicProfilePlanCapabilities`.
- Cambios de perfil público.
- Cambios SEO productivos.

### H) Siguiente microfase recomendada
Siguiente microfase recomendada:

- `DB-Suscripciones-ContractAcceptance-ExecuteSchemaLocalDev-01`.

Objetivo futuro:

- Ejecutar de forma controlada el SQL en DB local/dev, con validaciones previas y posteriores, sin tocar producción.

### I) Límites de esta adenda
Esta adenda no implementa:

- Ejecución SQL.
- Cambios DB.
- Creación de tabla.
- Backend.
- Frontend.
- Writes.
- Pagos.
- Facturación.
- `PublicProfilePlanCapabilities`.
- Capacidades productivas.
- Contratación.
- Aceptación real.
- Renovación.
- Cancelación.
- Perfil público.
- SEO productivo.

---

## Adenda PP-Decisiones 44 — Cierre de ejecución local/dev del schema de aceptación contractual de suscripciones

### A) Microfase cerrada
La microfase `DB-Suscripciones-ContractAcceptance-ExecuteSchemaLocalDev-01` cerró con PASS.

Tipo de microfase:

- Ejecución controlada de schema en DB local/dev.

SQL ejecutado:

- `modules/profiles/db/2026_06_20_create_subscription_contract_acceptances.sql`.

### B) Entorno de ejecución
Entorno confirmado para la ejecución:

- Host seguro/local-dev: `127.0.0.1:3306`.
- Base de datos: `mxmed`.
- Producción descartada: sí.
- Credenciales expuestas en reporte: no.
- Fuente de configuración revisada: `api/_lib/db.php` y `api/mxmed-db.config.php`.

### C) Resultado de DB
Resultado confirmado:

- Tabla creada: `subscription_contract_acceptances`.
- Columnas esperadas: 27/27.
- Primary key: `PRIMARY KEY (id)`.
- Unique: `ux_subscription_contract_acceptances_uuid`.
- Índices base: presentes.
- Índices compuestos: presentes.
- Engine/collation: `InnoDB / utf8mb4_unicode_ci`.
- Conteo de registros: 0.
- Seeds insertados: no.
- `profile_subscriptions` modificado: no.
- `subscription_plans` modificado: no.

### D) Alcance bloqueado
Este cierre no incluye:

- Endpoint write.
- Aceptación contractual real productiva.
- Contratación real.
- Renovación.
- Cancelación.
- Checkout.
- Pagos.
- Facturación.
- Conexión con `PublicProfilePlanCapabilities`.
- Activación de capacidades productivas.
- Cambios de perfil público.
- Cambios SEO productivos.
- Modificación de backend.
- Modificación de frontend.

### E) Decisión arquitectónica vigente
Decisión vigente después de la creación local/dev de la tabla:

- La tabla existe ahora en DB local/dev como infraestructura de auditoría/evidencia.
- `subscription_contract_acceptances` sigue siendo auditoría/evidencia legal.
- `profile_subscriptions` sigue siendo snapshot operativo/read-model.
- La creación de la tabla no activa planes ni capacidades.
- `free` sigue siendo fallback/read-model.
- `free` no se contrata.
- No se crean filas `free`.
- No se crean aceptaciones `free` por default.

### F) Riesgos y controles posteriores
A partir de esta fase:

- Cualquier endpoint write futuro debe validar contrato, plan, actor, permisos y `free`.
- Cualquier activación de suscripción real debe quedar en microfase separada.
- Cualquier conexión con capacidades debe quedar en microfase separada.
- No debe asumirse que la tabla creada permite contratación por sí misma.
- Producción o staging requerirán microfase separada y autorización explícita.

### G) Siguiente microfase recomendada
Siguiente microfase recomendada:

- `QA-Suscripciones-ContractAcceptance-LocalSchema-PostExecution-01`.

Objetivo futuro:

- Verificar de forma independiente, sólo lectura, que la tabla local/dev existe con estructura correcta y cero registros, sin modificar DB ni archivos.

### H) Límites de esta adenda
Esta adenda no implementa:

- Ejecución SQL adicional.
- Cambios DB adicionales.
- Backend.
- Frontend.
- Writes.
- Pagos.
- Facturación.
- `PublicProfilePlanCapabilities`.
- Capacidades productivas.
- Contratación.
- Aceptación real productiva.
- Renovación.
- Cancelación.
- Perfil público.
- SEO productivo.

---

## Adenda PP-Decisiones 45 — Cierre del QA post-ejecución local/dev de aceptación contractual

### A) Microfase cerrada
La microfase `QA-Suscripciones-ContractAcceptance-LocalSchema-PostExecution-01` cerró con PASS sin cambios.

Tipo de microfase:

- QA post-ejecución sólo lectura.

Confirmaciones del QA:

- No modificó archivos.
- No ejecutó SQL de escritura.
- No modificó DB.

### B) Entorno validado
Entorno DB validado:

- Host: `127.0.0.1:3306`.
- Base de datos: `mxmed`.
- Producción descartada: sí.
- Credenciales expuestas: no.

### C) Tabla validada
Tabla:

- `subscription_contract_acceptances`.

Resultado:

- Existe: sí.
- Engine: `InnoDB`.
- Collation: `utf8mb4_unicode_ci`.
- Conteo de registros: 0.
- Seeds detectados: no.

### D) Estructura validada
Estructura confirmada:

- Columnas esperadas: 27.
- Columnas encontradas: 27.
- Columnas faltantes: ninguna.
- Columnas extra: ninguna.
- Tipos principales validados.
- Primary key: `PRIMARY KEY (id)`.
- Unique: `ux_subscription_contract_acceptances_uuid`.
- Índices base: presentes.
- Índices compuestos: presentes.
- Índices faltantes: ninguno.
- Índices extra relevantes: ninguno.

### E) Tablas relacionadas
Tablas relacionadas verificadas en sólo lectura:

- `profile_subscriptions` existe.
- `subscription_plans` existe.
- `profile_subscriptions` no fue modificada.
- `subscription_plans` no fue modificada.

### F) Alcance bloqueado
Este cierre de QA no incluye:

- Endpoint write.
- Aceptación contractual real productiva.
- Contratación real.
- Renovación.
- Cancelación.
- Checkout.
- Pagos.
- Facturación.
- Conexión con `PublicProfilePlanCapabilities`.
- Activación de capacidades productivas.
- Cambios de perfil público.
- Cambios SEO productivos.
- Modificación de backend.
- Modificación de frontend.

### G) Decisión arquitectónica vigente
Decisión vigente:

- La tabla local/dev queda verificada como infraestructura de auditoría/evidencia.
- `subscription_contract_acceptances` sigue siendo auditoría/evidencia legal.
- `profile_subscriptions` sigue siendo snapshot operativo/read-model.
- La existencia de la tabla no activa planes, pagos, contratación ni capacidades.
- `free` sigue siendo fallback/read-model.
- `free` no se contrata.
- No se crean filas `free`.
- No se crean aceptaciones `free` por default.

### H) Riesgos y controles posteriores
Controles vigentes para fases futuras:

- Cualquier endpoint write futuro debe validar contrato, plan, actor, permisos y bloqueo de `free`.
- Cualquier creación de suscripción real debe quedar en microfase separada.
- Cualquier conexión con capacidades debe quedar en microfase separada.
- Producción/staging requieren microfase separada y autorización explícita.
- La tabla existe sólo como base local/dev para futuras fases.

### I) Siguiente microfase recomendada
Siguiente microfase recomendada:

- `BE/DIAG-Suscripciones-ContractAcceptance-WriteFlowDesign-01`.

Objetivo futuro:

- Diagnosticar el diseño del flujo backend write de aceptación contractual y creación/enlace de suscripción, sin implementarlo todavía.

### J) Límites de esta adenda
Esta adenda no implementa:

- Ejecución SQL.
- Cambios DB.
- Backend.
- Frontend.
- Writes.
- Pagos.
- Facturación.
- `PublicProfilePlanCapabilities`.
- Capacidades productivas.
- Contratación.
- Aceptación real productiva.
- Renovación.
- Cancelación.
- Perfil público.
- SEO productivo.

---

## Adenda PP-Decisiones 46 — Diseño del write flow de aceptación contractual y suscripción

### A) Microfase diagnóstica cerrada
La microfase `BE/DIAG-Suscripciones-ContractAcceptance-WriteFlowDesign-01` cerró con PASS sin cambios.

Tipo de microfase:

- Diagnóstico sin implementación.

Conclusión:

- No se debe implementar todavía un endpoint write.
- Primero queda documentado el diseño del flujo de aceptación contractual y creación/enlace de suscripción.

### B) Backend actual
Rutas existentes:

- `GET /api/subscriptions/index.php/context/current`.
- `GET /api/subscriptions/index.php/entities/{entity_type}/{entity_id}/current`.

Estado actual:

- El módulo actual de suscripciones es read-only.
- No hay writes de suscripciones.
- No hay endpoint de aceptación contractual.
- No hay contratación real.
- `CurrentSubscriptionReadModelService` sigue leyendo `profile_subscriptions`.
- Si no hay suscripción real, el read-model cae a `free_default`.

### C) Endpoint futuro recomendado
Endpoint recomendado para la primera versión mínima segura:

- `POST /api/subscriptions/index.php/entities/{entity_type}/{entity_id}/subscriptions`.

Motivo:

- La aceptación contractual y la creación de suscripción deben ocurrir juntas en la primera versión segura.
- Una sola transacción reduce el riesgo de aceptación huérfana.
- El endpoint representa una intención comercial de suscripción, no sólo una aceptación aislada.

No se recomienda inicialmente separar en:

- Endpoint sólo de aceptación.
- Endpoint separado de suscripción.

Una separación posterior puede evaluarse si checkout, pagos o flujo comercial requieren otro orden.

### D) Transacción recomendada
Orden conceptual de la transacción futura:

1. Validar auth/actor.
2. Validar entidad y doctor scope.
3. Validar plan contra `subscription_plans`.
4. Bloquear `free`.
5. Validar contrato/evidencia.
6. Generar `subscription_id`.
7. Insertar aceptación en `subscription_contract_acceptances`.
8. Insertar suscripción operativa en `profile_subscriptions`.
9. Copiar snapshot mínimo de aceptación a `profile_subscriptions`.
10. Calcular `starts_at`.
11. Calcular `expires_at` una sola vez según duración del plan.
12. Confirmar transacción.
13. Devolver read-model actualizado.

### E) Relación aceptación/suscripción
Decisiones de enlace:

- `subscription_id` debe generarse antes de insertar los registros.
- `subscription_contract_acceptances.subscription_id` debe quedar lleno desde el inicio en v1 productiva.
- `profile_subscriptions.subscription_id` debe usar el mismo id.
- Se debe evitar `pending_link` en la primera versión productiva.
- `pending_link` queda reservado para migraciones o casos excepcionales.
- No se agrega todavía `contract_acceptance_id` a `profile_subscriptions`.
- `profile_subscriptions` sigue como snapshot operativo/read-model.
- `subscription_contract_acceptances` sigue como auditoría/evidencia legal.

### F) Planes y duración
Reglas de plan:

- Validar `plan_code` contra `subscription_plans`.
- Validar `billing_period`.
- Validar `duration_days`.
- Bloquear `free` siempre en el flujo normal.
- Los planes pagados anuales actuales usan 365 días.
- `expires_at` debe calcularse una sola vez.
- No recalcular vigencias después por lecturas o renders.
- Plan inválido, inactivo o no contratable debe rechazarse.

### G) Contrato y evidencia legal
Campos requeridos conceptualmente en producción:

- `contract_version`.
- `contract_hash`.
- `contract_snapshot_url` o evidencia equivalente.
- `contract_title`.
- `accepted_at`.
- `acceptance_source`.
- `accepted_by_user_id`.
- `accepted_by_actor_role`.
- `accepted_by_operator_id`, si aplica.
- `ip_address`.
- `user_agent`.

Reglas:

- Producción debe exigir `contract_hash` y snapshot/evidencia antes de activar un plan.
- Si falta contrato válido, el backend debe responder error y no crear suscripción.
- No exponer IP, hash ni user-agent en read-model público o privado salvo decisión futura explícita.

### H) Auth y permisos
Reglas de autorización:

- Reutilizar el guard privado actual.
- Para writes, strict auth debe ser obligatorio.
- `local_dev_open` no debe autorizar writes.
- Headers QA deben seguir limitados a local/dev y sólo si se decide explícitamente para pruebas controladas.
- Médico principal puede aceptar/contratar únicamente para su propio `doctor_id`.
- Operador queda bloqueado inicialmente.
- Operador futuro requeriría permiso explícito `subscriptions.write`.
- Admin queda fuera de la primera versión.

### I) Estados HTTP recomendados
Códigos recomendados:

- `400`: payload inválido.
- `401`: sin identidad.
- `403`: scope o permiso insuficiente.
- `404`: entidad o plan no encontrado.
- `409`: suscripción activa conflictiva o doble submit.
- `422`: contrato faltante/inválido o plan no contratable.
- `500`: error inesperado transaccional.

### J) Idempotencia y duplicados
Reglas recomendadas:

- Usar `Idempotency-Key`.
- Bloquear doble click o doble submit.
- Bloquear nueva suscripción si ya existe una activa incompatible.
- Una repetición segura debe devolver resultado consistente o conflicto controlado.
- Si falla la creación de suscripción, el rollback debe eliminar la aceptación de la misma transacción.
- No dejar aceptación huérfana.

### K) Read-model
Después del commit:

- Backend debe devolver el read-model actualizado.
- `CurrentSubscriptionReadModelService` puede seguir leyendo `profile_subscriptions`.
- Campos contractuales mínimos pueden exponerse desde el snapshot operativo.
- No exponer IP, user-agent, hash ni evidencia legal completa en el read-model normal.
- Auditoría legal se consulta desde `subscription_contract_acceptances`, no desde el panel público.

### L) Frontend / panel
Reglas de UI:

- `p-suscripcion` sigue read-only por ahora.
- No conectar botones ni acciones todavía.
- La UI de contratación futura debe quedar en microfase separada.
- No enviar writes desde frontend hasta tener backend, QA, contrato y reglas comerciales cerradas.

### M) Pagos / checkout
Decisiones vigentes:

- Pagos, checkout y facturación son dominios separados.
- No activar plan pagado sin criterio comercial/pago definido.
- No agregar campos de pago todavía en `subscription_contract_acceptances`.
- Si checkout futuro requiere orden distinto, deberá diagnosticarse en microfase separada.
- Esta etapa no implementa cobro ni facturación.

### N) Capacidades
Decisiones vigentes:

- No conectar `PublicProfilePlanCapabilities` todavía.
- La tabla y el write flow no activan capacidades.
- La activación de capacidades requerirá microfase posterior con read-model confiable y QA.
- No tocar perfil público ni SEO.

### O) Riesgos documentados
Riesgos a controlar:

- Aceptación huérfana.
- Suscripción sin aceptación.
- Activación sin pago.
- Doble submit.
- Operador sin permiso.
- `free` contratado por error.
- Capacidades prematuras.
- Datos legales incompletos.
- Exposición innecesaria de IP/user-agent/hash.
- Recalcular `expires_at`.

### P) QA futuro recomendado
Pruebas mínimas futuras:

- Auth médico principal.
- Sin identidad.
- Scope mismatch.
- Operador bloqueado.
- Plan `free` bloqueado.
- Plan pagado válido.
- Contrato faltante.
- Contract hash/snapshot faltante.
- Suscripción activa duplicada.
- Idempotencia/doble submit.
- Rollback transaccional.
- Read-model actualizado.
- Sin pagos activados.
- Sin capacidades activadas.
- Sin frontend write.

### Q) Siguiente microfase recomendada
Siguiente microfase recomendada:

- `BE/SPEC-Suscripciones-ContractAcceptance-WriteEndpoint-01`.

Objetivo futuro:

- Especificar el contrato técnico exacto del endpoint write, payload, validaciones, respuesta, errores y QA, sin implementarlo todavía.

### R) Límites de esta adenda
Esta adenda no implementa:

- Endpoint write.
- Backend.
- Frontend.
- SQL.
- Cambios DB.
- Writes.
- Pagos.
- Checkout.
- Facturación.
- `PublicProfilePlanCapabilities`.
- Capacidades productivas.
- Contratación.
- Aceptación real productiva.
- Renovación.
- Cancelación.
- Perfil público.
- SEO productivo.

---

## Adenda PP-Decisiones 47 — Especificación del endpoint write de aceptación contractual y suscripción

### A) Microfase de especificación
La microfase `BE/SPEC-Suscripciones-ContractAcceptance-WriteEndpoint-01` define el contrato técnico exacto del endpoint futuro.

Tipo de microfase:

- Especificación técnica sin implementación.

Alcance:

- No implementa endpoint.
- No modifica backend.
- No modifica frontend.
- No ejecuta SQL.
- No modifica DB.

### B) Endpoint especificado
Endpoint futuro recomendado:

- `POST /api/subscriptions/index.php/entities/{entity_type}/{entity_id}/subscriptions`.

Reglas:

- `entity_type` inicialmente soportado: `doctor`.
- `entity_id` debe corresponder al `doctor_id` permitido por auth/scope.
- El endpoint representa intención de suscripción pagada.
- La primera versión crea aceptación contractual y `profile_subscriptions` en una sola transacción.
- No es endpoint de pagos.
- No es endpoint de checkout.
- No activa capacidades productivas por sí mismo.

### C) Método y headers
Contrato HTTP:

- Método: `POST`.
- `Content-Type` requerido: `application/json`.
- Credenciales/sesión: obligatorias.
- Strict auth obligatorio.
- `local_dev_open` no autoriza writes.
- Headers QA sólo podrían permitirse en local/dev si se define explícitamente para pruebas controladas.
- Header recomendado: `Idempotency-Key`, opcional/futuro para evitar doble submit.
- No aceptar identidad sólo por `X-User-Id`.

### D) Payload JSON propuesto
Payload mínimo recomendado:

```json
{
  "plan_code": "standard",
  "billing_period": "annual",
  "contract": {
    "version": "mxmed-subscriptions-v1",
    "hash": "sha256:...",
    "snapshot_url": "/legal/subscriptions/mxmed-subscriptions-v1.html",
    "title": "Contrato de suscripción México Médico"
  },
  "acceptance": {
    "source": "panel_subscription",
    "accepted_at": "server_time"
  }
}
```

Reglas:

- `plan_code` requerido.
- `billing_period` requerido.
- `contract.version` requerido.
- `contract.hash` requerido en producción.
- `contract.snapshot_url` o evidencia equivalente requerida en producción.
- `contract.title` recomendado.
- `acceptance.source` requerido.
- `accepted_at` debe fijarse por servidor; no se debe confiar en cliente.
- IP y user-agent deben derivarse del request por backend.
- `accepted_by_user_id`, `accepted_by_actor_role` y `accepted_by_operator_id` deben derivarse de auth/contexto, no del payload.

### E) Campos que no debe aceptar desde cliente
El cliente no debe enviar ni controlar:

- `subscription_id`.
- `starts_at`.
- `expires_at`.
- `status`.
- `accepted_by_user_id`.
- `accepted_by_actor_role`.
- `accepted_by_operator_id`.
- `ip_address`.
- `user_agent`.
- `duration_days`.
- `price`.
- `capabilities`.
- `source` interno.
- `deleted_at`.
- Cualquier campo de `profile_subscriptions` que deba calcular backend.

### F) Validaciones obligatorias
Validaciones mínimas:

- Auth/session válida.
- Strict auth activo para writes.
- Actor permitido.
- `entity_type=doctor` en primera versión.
- `entity_id` corresponde al doctor scope.
- Médico principal sólo puede operar su propio `doctor_id`.
- Operador bloqueado inicialmente.
- Operador futuro requiere `subscriptions.write`.
- Plan existe en `subscription_plans`.
- Plan activo/contratable.
- Bloquear `free`.
- `billing_period` coincide con catálogo.
- `duration_days` se toma del catálogo.
- Plan pagado anual actual = 365 días.
- No existe suscripción activa incompatible.
- Contrato completo y válido.
- Hash/snapshot presentes en producción.
- No activar plan si falta aceptación válida.
- No crear suscripción si falla aceptación.

### G) Transacción backend
Orden exacto recomendado:

1. Iniciar transacción.
2. Resolver auth/contexto.
3. Validar scope.
4. Validar plan.
5. Bloquear `free`.
6. Validar contrato/evidencia.
7. Verificar conflicto de suscripción activa.
8. Generar `subscription_id`.
9. Calcular `accepted_at` en servidor.
10. Insertar en `subscription_contract_acceptances`.
11. Insertar en `profile_subscriptions`.
12. Copiar snapshot contractual mínimo a `profile_subscriptions`.
13. Calcular `starts_at`.
14. Calcular `expires_at` una sola vez.
15. Confirmar transacción.
16. Leer read-model actualizado.
17. Responder JSON.

Reglas:

- Si falla cualquier paso, rollback completo.
- No dejar aceptación huérfana.
- No dejar suscripción sin aceptación.
- No usar `pending_link` en v1 productiva normal.
- `pending_link` queda reservado para migraciones o casos excepcionales.

### H) Inserción en `subscription_contract_acceptances`
Mapeo conceptual:

- `uuid`: generado por backend.
- `entity_type`: `doctor`.
- `entity_id`: path param validado.
- `doctor_id`: doctor scope.
- `profile_id`: disponible si existe; nullable si no.
- `subscription_id`: generado antes e insertado desde el inicio.
- `plan_code`: catálogo.
- `billing_period`: catálogo.
- `duration_days`: catálogo.
- `contract_version`: payload validado.
- `contract_hash`: payload validado.
- `contract_snapshot_url`: payload validado.
- `contract_title`: payload validado/recomendado.
- `accepted_at`: servidor.
- `accepted_by_user_id`: auth.
- `accepted_by_actor_role`: auth/contexto.
- `accepted_by_operator_id`: auth/contexto si aplica.
- `acceptance_source`: payload validado.
- `ip_address`: request.
- `user_agent`: request.
- `status`: `accepted`.
- `source`: constante backend.
- `notes`: `NULL` en flujo normal.
- `deleted_at`: `NULL`.

### I) Inserción en `profile_subscriptions`
Mapeo conceptual:

- `subscription_id`: mismo id generado.
- `entity_type`: `doctor`.
- `entity_id`: path param validado.
- `doctor_id`/`profile_id`: según schema existente.
- `plan_code`: catálogo.
- `billing_period`: catálogo.
- `status`: activo/según convención existente.
- `starts_at`: servidor.
- `expires_at`: `starts_at + duration_days`.
- `contract_version`: contrato validado.
- `contract_accepted_at`: mismo `accepted_at`.
- `contract_accepted_by_user_id`: auth.
- `contract_acceptance_source`: source validado.
- `contract_acceptance_ip`: request.
- `contract_acceptance_user_agent`: request.
- `source`: constante backend.
- `notes`: `NULL` o sistema.
- `deleted_at`: `NULL`.

Aclaraciones:

- No agregar todavía `contract_acceptance_id`.
- `profile_subscriptions` sigue siendo snapshot operativo.
- Auditoría completa vive en `subscription_contract_acceptances`.

### J) Respuesta JSON esperada
Respuesta sugerida en éxito `201`:

```json
{
  "ok": true,
  "data": {
    "subscription_id": "...",
    "contract_acceptance_uuid": "...",
    "current_subscription": {
      "effective_plan_code": "standard",
      "status": "active",
      "starts_at": "...",
      "expires_at": "...",
      "contract_accepted_at": "..."
    }
  },
  "meta": {
    "source": "subscriptions_write_v1",
    "auth_mode": "session_scope"
  }
}
```

Reglas:

- Puede devolver read-model actualizado reutilizando `CurrentSubscriptionReadModelService`.
- No exponer IP.
- No exponer user-agent.
- No exponer contract hash completo si no es necesario.
- No exponer evidencia legal completa en respuesta normal.

### K) Errores HTTP especificados
Códigos:

- `400`: payload inválido o JSON malformado.
- `401`: sin identidad válida.
- `403`: scope inválido o actor sin permiso.
- `404`: entidad o plan no encontrado.
- `409`: suscripción activa conflictiva o doble submit.
- `422`: plan no contratable, `free`, contrato faltante/inválido, hash/snapshot faltantes.
- `500`: error inesperado transaccional.

Ejemplo breve:

```json
{
  "ok": false,
  "error": {
    "code": "plan_not_contractable",
    "message": "El plan solicitado no puede contratarse."
  }
}
```

### L) Idempotencia
Decisiones:

- Recomendada para la primera implementación real.
- Header sugerido: `Idempotency-Key`.
- Debe evitar doble click/doble submit.
- Si se repite el mismo payload con la misma key, devolver resultado consistente.
- Si se repite con conflicto, devolver `409`.
- Esta microfase no implementa idempotencia; sólo la especifica.

### M) Auth/permisos
Reglas:

- Strict auth obligatorio.
- Sesión como fuente primaria.
- `local_dev_open` bloqueado para writes.
- Headers QA no autorizan writes salvo decisión explícita local/dev.
- Médico principal permitido sólo para su propio `doctor_id`.
- Operador bloqueado inicialmente.
- Permiso futuro: `subscriptions.write`.
- Admin fuera de v1.

### N) Read-model
Después del commit:

- Backend debe consultar el read-model actual.
- `CurrentSubscriptionReadModelService` puede seguir basado en `profile_subscriptions`.
- El read-model debe reflejar plan pagado activo.
- No debe crear capacidades ni SEO.
- No debe exponer evidencia legal sensible.

### O) Fuera de alcance
Esta especificación no incluye:

- Implementación del endpoint.
- UI de contratación.
- Cambios en `p-suscripcion`.
- Pagos.
- Checkout.
- Facturación.
- Renovación.
- Cancelación.
- Jobs.
- Notificaciones.
- Capacidades productivas.
- Conexión con `PublicProfilePlanCapabilities`.
- Perfil público.
- SEO productivo.

### P) QA futuro de implementación
Pruebas mínimas futuras:

- `php -l api/subscriptions/index.php`.
- Sin identidad -> `401`.
- Scope mismatch -> `403`.
- Operador sin permiso -> `403`.
- `free` -> `422`.
- Plan inexistente -> `404`/`422`, según diseño final.
- Contrato faltante -> `422`.
- Hash faltante en producción -> `422`.
- Snapshot faltante en producción -> `422`.
- Suscripción activa existente -> `409`.
- Plan pagado válido -> `201`.
- Doble submit/idempotencia -> resultado estable o `409`.
- Rollback si falla inserción de `profile_subscriptions`.
- Rollback si falla inserción de aceptación.
- `subscription_contract_acceptances` queda con 1 registro sólo en éxito.
- `profile_subscriptions` queda con 1 registro sólo en éxito.
- Read-model devuelve plan pagado activo.
- No se activan capacidades.
- No se ejecutan pagos.
- No se toca frontend.

### Q) Siguiente microfase recomendada
Siguiente microfase recomendada:

- `BE/DIAG-Suscripciones-ContractAcceptance-WriteEndpoint-ImplementationReadiness-01`.

Objetivo futuro:

- Diagnosticar si ya es seguro implementar el endpoint write mínimo o si falta decidir algo más, sin modificar backend todavía.

### R) Límites de esta adenda
Esta adenda no implementa:

- Endpoint write.
- Backend.
- Frontend.
- SQL.
- Cambios DB.
- Writes.
- Pagos.
- Checkout.
- Facturación.
- `PublicProfilePlanCapabilities`.
- Capacidades productivas.
- Contratación.
- Aceptación real productiva.
- Renovación.
- Cancelación.
- Perfil público.
- SEO productivo.

---

## Adenda PP-Decisiones 48 — Plan técnico de implementación del endpoint write de aceptación contractual y suscripción

### A) Objetivo de la futura implementación
La futura microfase backend deberá implementar el endpoint mínimo:

- `POST /api/subscriptions/index.php/entities/{entity_type}/{entity_id}/subscriptions`.

Alcance inicial:

- Aceptación contractual.
- Creación de `profile_subscriptions`.
- Enlace entre aceptación y suscripción mediante `subscription_id`.
- Una sola transacción.

Entidad inicial:

- `entity_type = doctor`.

Alcance recomendado:

- Local/dev o controlado manualmente.
- Sin pagos.
- Sin checkout.
- Sin capacidades productivas.

Fuera de alcance:

- Checkout.
- Pagos.
- Facturación.
- Renovación.
- Cancelación.
- Capacidades.
- Perfil público.
- SEO.
- Frontend write.

### B) Archivos permitidos para la futura microfase backend
Archivos recomendados para permitir en la futura microfase de implementación:

- `api/subscriptions/index.php`.
- `modules/subscriptions/repositories/SubscriptionContractAcceptanceRepository.php`.
- `modules/subscriptions/services/CreateSubscriptionWithAcceptanceService.php`.

Archivos reutilizables en lectura o integración:

- `modules/subscriptions/services/CurrentSubscriptionReadModelService.php`, para devolver read-model actualizado.
- `modules/subscriptions/repositories/CurrentSubscriptionRepository.php`, para lectura/validación si no rompe responsabilidades.

Regla de alcance:

- No tocar frontend en la microfase backend mínima.
- No modificar `assets/js/app.js`.
- No modificar `index.html`.

### C) Diseño recomendado de responsabilidades
`api/subscriptions/index.php`:

- Routing `POST`.
- Parseo JSON.
- Llamada a guard write.
- Llamada al service transaccional.
- Respuesta HTTP/JSON.

`CreateSubscriptionWithAcceptanceService`:

- Orquestación transaccional.
- Validación de plan.
- Bloqueo de `free`.
- Validación de contrato.
- Detección de conflicto activo.
- Generación de `subscription_id`.
- Inserción de aceptación.
- Inserción de `profile_subscriptions`.
- Commit/rollback.
- Obtención del read-model actualizado.

`SubscriptionContractAcceptanceRepository`:

- Insert en `subscription_contract_acceptances`.
- Generación o uso de `uuid`.
- Persistencia de evidencia técnica.

Repository o lógica operativa para `profile_subscriptions`:

- Insert operativo mínimo.
- No crear `free`.
- No borrar histórico.

### D) Write guard / auth
Reglas:

- Strict auth obligatorio para writes.
- `local_dev_open` siempre bloqueado para writes.
- Headers QA bloqueados por defecto.
- Headers QA sólo podrían permitirse en microfase QA local separada, si se define explícitamente.
- Médico principal puede escribir sólo para su propio `doctor_id`.
- Operador bloqueado inicialmente.
- Operador futuro requiere permiso explícito `subscriptions.write`.
- No aceptar identidad sólo por `X-User-Id`.
- Mismatch entity/doctor debe devolver `403`.
- Sin identidad válida debe devolver `401`.

### E) Validaciones del payload
Payload mínimo esperado:

```json
{
  "plan_code": "standard",
  "billing_period": "annual",
  "contract": {
    "version": "mxmed-subscriptions-v1",
    "hash": "sha256:...",
    "snapshot_url": "/legal/subscriptions/mxmed-subscriptions-v1.html",
    "title": "Contrato de suscripción México Médico"
  },
  "acceptance": {
    "source": "panel_subscription"
  }
}
```

Validaciones:

- `plan_code` requerido.
- `billing_period` requerido.
- `plan_code=free` debe devolver `422`.
- Plan inexistente debe devolver `404` o `422`, según criterio final.
- `billing_period` debe coincidir con catálogo.
- `duration_days` debe venir del catálogo, no del cliente.
- `contract.version` requerido.
- `contract.hash` requerido para producción.
- `contract.hash` debe validar formato `sha256:` cuando aplique.
- `contract.snapshot_url` requerido para producción o evidencia equivalente.
- `contract.title` recomendado.
- `acceptance.source` requerido.
- `acceptance.source` debe validarse contra lista permitida.
- `accepted_at` siempre lo fija backend.
- IP y user-agent siempre los deriva backend.
- No aceptar `starts_at`, `expires_at`, `status`, `price`, `duration_days` ni `capabilities` desde cliente.

### F) Campos prohibidos desde cliente
El endpoint no debe aceptar desde cliente:

- `subscription_id`.
- `starts_at`.
- `expires_at`.
- `status`.
- `accepted_by_user_id`.
- `accepted_by_actor_role`.
- `accepted_by_operator_id`.
- `ip_address`.
- `user_agent`.
- `duration_days`.
- `price`.
- `capabilities`.
- `source` interno.
- `deleted_at`.
- `contract_acceptance_uuid`.
- Cualquier campo operativo calculado por backend.

### G) Orden transaccional recomendado
Orden mínimo:

1. Validar método `POST` y ruta.
2. Validar JSON.
3. Resolver auth/contexto con strict write.
4. Validar `entity_type=doctor`.
5. Validar `entity_id` contra `doctor_id` permitido.
6. Bloquear operador inicialmente.
7. Validar plan en `subscription_plans`.
8. Bloquear `free`.
9. Validar `billing_period` y `duration_days` desde catálogo.
10. Validar contrato/evidencia.
11. Validar `acceptance_source`.
12. Buscar suscripción activa existente.
13. Si existe suscripción activa, responder `409`.
14. Iniciar transacción.
15. Generar `subscription_id`.
16. Generar `contract_acceptance_uuid`.
17. Calcular `starts_at` con hora servidor.
18. Calcular `expires_at` una sola vez según `duration_days`.
19. Insertar `subscription_contract_acceptances`.
20. Insertar `profile_subscriptions` con snapshot operativo.
21. Reconsultar read-model.
22. Commit.
23. Responder `201`.
24. Rollback ante cualquier error transaccional.

### H) Reglas de fechas
Reglas:

- `starts_at` lo fija backend.
- `expires_at` se calcula una sola vez.
- Planes pagados anuales actuales: `duration_days=365`.
- `free` no tiene vigencia contratada.
- No recalcular `expires_at` automáticamente.
- Gracia y renovación quedan fuera de esta microfase.
- Jobs de vencimiento o recordatorios quedan fuera.

### I) Conflicto e idempotencia
Decisiones:

- En v1 no es obligatorio resolver idempotencia completa.
- `Idempotency-Key` es recomendado para fase posterior.
- No existe storage actual para idempotency key.

Mitigación inicial:

- Consultar suscripción activa antes de insertar.
- Usar transacción.
- Devolver `409` si ya existe activa.
- No crear segunda suscripción activa.

Riesgo restante:

- Doble submit concurrente sin storage dedicado.

Decisión:

- Idempotencia robusta debe quedar como microfase separada si se requiere.

### J) Pagos / criterio comercial
Reglas:

- La implementación mínima puede existir sólo como local/dev o controlada manualmente.
- No debe presentarse como contratación productiva.
- No debe cobrar.
- No debe generar factura.
- No debe conectar checkout.
- No debe activar capacidades.
- Si se crea `profile_subscriptions` activa sin pago, debe quedar documentado como alcance dev/manual/controlado.
- Antes de producción se requiere decisión comercial/pagos/checkout.

### K) Respuesta JSON esperada
Respuesta de éxito sugerida:

```json
{
  "ok": true,
  "data": {
    "subscription_id": "...",
    "contract_acceptance_uuid": "...",
    "current_subscription": {
      "effective_plan_code": "standard",
      "status": "active",
      "starts_at": "...",
      "expires_at": "...",
      "contract_accepted_at": "..."
    }
  },
  "meta": {
    "source": "subscriptions_write_v1",
    "auth_mode": "session_scope"
  }
}
```

No exponer:

- IP.
- User-agent.
- Hash completo si se decide ocultarlo.
- Evidencia legal completa.
- Datos sensibles.
- Capacidades productivas.

### L) Errores HTTP mínimos
Códigos:

- `400`: JSON inválido o payload inválido.
- `401`: sin identidad válida.
- `403`: scope inválido, `local_dev_open`, operador bloqueado o actor sin permiso.
- `404`: entidad o plan no encontrado.
- `409`: suscripción activa existente o doble submit básico.
- `422`: `free`, contrato faltante, hash/snapshot inválidos, plan no contratable.
- `500`: error inesperado con rollback.

### M) QA futuro mínimo
Checklist para la futura microfase backend:

- Precondición Git limpia.
- `php -l api/subscriptions/index.php`.
- `php -l` en services/repositories nuevos si se crean.
- Sin identidad -> `401`.
- `local_dev_open` -> bloqueado para `POST`.
- Scope mismatch -> `403`.
- Médico principal propio -> permitido.
- Operador -> `403`.
- `free` -> `422`.
- Plan inexistente -> `404`/`422`.
- Contrato faltante -> `422`.
- Hash inválido -> `422`.
- Snapshot faltante -> `422`.
- Plan pagado válido -> `201`.
- Conteo previo/posterior en `subscription_contract_acceptances`.
- Conteo previo/posterior en `profile_subscriptions`.
- Un éxito crea exactamente 1 aceptación y 1 suscripción.
- Falla después de iniciar transacción hace rollback completo.
- Suscripción activa existente -> `409`.
- Read-model posterior devuelve plan pagado activo.
- No frontend modificado.
- No pagos.
- No checkout.
- No capacidades.
- No perfil público.
- No SEO.
- `p-suscripcion` sigue read-only.

### N) Siguiente microfase recomendada
Siguiente microfase recomendada:

- `BE-Suscripciones-ContractAcceptance-WriteEndpoint-Minimal-01`.

Objetivo:

- Implementar endpoint backend mínimo con service/repository transaccional, sólo después de esta documentación.

Antes de esa implementación se debe revalidar en lectura:

- Columnas reales de `profile_subscriptions`.
- Nombres exactos de `status` y `source`.
- Compatibilidad con `CurrentSubscriptionReadModelService`.
- Que no haya cambios Git pendientes.

### O) Límites de esta adenda
Esta adenda no implementa:

- Endpoint.
- Backend.
- Rutas.
- Services reales.
- Repositories reales.
- Frontend.
- SQL.
- Cambios DB.
- Pagos.
- Checkout.
- `PublicProfilePlanCapabilities`.
- Capacidades productivas.
- Contratación real productiva.
- Aceptación real productiva.
- Perfil público.
- SEO productivo.

---

## Adenda PP-Decisiones 49 — Cierre de implementación backend mínima del endpoint write contractual

### A) Cierre de implementación
Queda cerrada la implementación backend mínima del endpoint:

- `POST /api/subscriptions/index.php/entities/{entity_type}/{entity_id}/subscriptions`.

Alcance inicial soportado:

- `entity_type=doctor`.
- Implementación backend-only.
- Sin frontend write.
- Sin pagos.
- Sin checkout.
- Sin facturación.
- Sin capacidades productivas.

Archivos versionados en la implementación:

- `api/subscriptions/index.php`.
- `modules/subscriptions/repositories/SubscriptionContractAcceptanceRepository.php`.
- `modules/subscriptions/services/CreateSubscriptionWithAcceptanceService.php`.

Commit remoto/alineado:

- `16ff64f feat(suscripciones): implementa write contractual minimo`.

### B) Alcance técnico implementado
La implementación incluye:

- Routing `POST` para el endpoint de suscripción.
- Service transaccional para crear aceptación contractual y suscripción operativa.
- Repository dedicado para insertar evidencia en `subscription_contract_acceptances`.
- Inserción conceptual de aceptación contractual en `subscription_contract_acceptances`.
- Inserción operativa en `profile_subscriptions`.
- Uso del mismo `subscription_id` en ambas tablas.
- Bloqueo de `free`.
- Validación de `plan_code` y `billing_period` contra catálogo.
- Uso de `duration_days` desde `subscription_plans`, no desde cliente.
- Validación de contrato:
  - `version`.
  - `hash` con prefijo `sha256:`.
  - `snapshot_url`.
- Validación de `acceptance_source`.
- Rechazo de campos prohibidos enviados por cliente.
- Cálculo backend de `accepted_at`, `starts_at` y `expires_at`.
- Rollback ante fallo transaccional.
- Reconsulta del read-model para respuesta.
- Respuesta `201` definida para caso válido con sesión real `session_scope`.

La implementación no crea filas `free` ni aceptaciones contractuales `free`.

### C) Guard de escritura
El write queda protegido por estas reglas:

- Strict/session guard requerido para writes.
- `local_dev_open` bloqueado para `POST`.
- `header_scope` bloqueado para `POST`.
- Sólo `session_scope` puede permitir write.
- Médico principal sólo puede escribir para su propio `doctor_id`.
- Operador bloqueado inicialmente.
- Futuro operador requerirá permiso explícito `subscriptions.write`.
- Sin identidad o scope válido se responde `401`/`403` según el caso.
- Scope mismatch responde `403`.
- No se acepta identidad sólo por headers QA.

Esta decisión evita que el endpoint parezca contratación productiva abierta.

### D) QA post-push confirmado
Microfase de QA post-push:

- `QA-Suscripciones-ContractAcceptance-WriteEndpoint-Minimal-PostPush-01`.

Resultado:

- PASS sin cambios.

Validaciones confirmadas:

- Rama limpia y alineada con origin.
- `php -l api/subscriptions/index.php`: PASS.
- `php -l modules/subscriptions/repositories/SubscriptionContractAcceptanceRepository.php`: PASS.
- `php -l modules/subscriptions/services/CreateSubscriptionWithAcceptanceService.php`: PASS.
- `git diff --check`: limpio.
- GET `context/current` intacto; sin sesión respondió `401` seguro.
- GET current intacto; en local/dev read-only respondió `200` con `free_default`.
- POST sin sesión/local_dev_open/headers QA bloqueado con `403`.
- Nunca hubo respuesta `201` durante el QA post-push.
- No se ejecutó QA de éxito `201` por ausencia de sesión real `session_scope`.
- No hubo DB writes durante el QA post-push.

### E) Fuera de alcance preservado
Este cierre no incluye:

- Frontend.
- Cambios en `index.html`.
- Cambios en `assets/js/app.js`.
- Conexión de `p-suscripcion` a writes.
- Pagos.
- Checkout.
- Facturación.
- Capacidades productivas.
- `PublicProfilePlanCapabilities`.
- Perfil público.
- SEO.
- Cambios SQL/schema.
- DDL.
- Seeds.
- Ejecución de éxito `201` sin sesión real.

### F) Estado funcional actual
Estado actual:

- El backend write mínimo existe y está versionado.
- En condiciones sin sesión real, el endpoint permanece cerrado.
- El endpoint no puede considerarse contratación productiva completa.
- La tabla `subscription_contract_acceptances` sigue siendo auditoría/evidencia legal.
- `profile_subscriptions` sigue siendo snapshot operativo/read-model.
- La existencia del endpoint no activa pagos, checkout, facturación, capacidades, perfil público ni SEO.

Pendientes:

- QA de éxito `201` con sesión real `session_scope`.
- Decidir cómo obtener o simular de forma segura una sesión real local/dev sin usar headers QA.
- Decidir cuándo probar caso exitoso sin activar capacidades ni pagos.
- Frontend write en microfase separada futura, no ahora.
- Integración con pagos, checkout y facturación en fase posterior.
- Conexión de capacidades productivas sólo en fase posterior y tras decisión explícita.

### G) Riesgos residuales
Riesgos vigentes:

- No hay idempotencia robusta con storage dedicado.
- La mitigación actual es conflicto activo `409` más transacción.
- Persiste riesgo residual de doble submit concurrente.
- No hay QA de éxito `201` todavía.
- No hay pago ni checkout, por lo que no debe usarse como contratación productiva real.
- Operador sigue bloqueado hasta definir y persistir permiso `subscriptions.write`.

Controles:

- No dejar aceptación huérfana.
- No dejar suscripción sin aceptación.
- No crear filas `free`.
- No crear aceptaciones `free`.
- No activar capacidades desde este flujo.

### H) Siguiente microfase recomendada
Siguiente microfase recomendada:

- `QA-Suscripciones-ContractAcceptance-WriteEndpoint-SessionScope-SuccessReadiness-01`.

Objetivo:

- Diagnosticar cómo obtener o simular de forma segura una sesión real `session_scope` local/dev para ejecutar QA de éxito `201`, sin usar headers QA, sin activar frontend, sin pagos, sin capacidades y sin tocar producción.

La siguiente microfase debe ser diagnóstico sin cambios, no ejecución directa del caso éxito.

---

## Adenda PP-Decisiones 50 — Especificación de fixture local/dev para sesión `session_scope` contractual

### A) Problema a resolver
El endpoint write contractual ya existe:

- `POST /api/subscriptions/index.php/entities/doctor/1/subscriptions`.

Estado confirmado:

- El endpoint requiere sesión real `session_scope`.
- No se identificó login médico real usable para crear esa sesión.
- `api/verify-password.php` es un stub de verificación y no crea sesión.
- No hay usuario médico principal asociado confirmado para `doctor_id=1`.
- Los operadores existentes no sirven para este QA porque el guard write los bloquea.
- No se puede usar `header_scope` para writes.
- No se puede usar `local_dev_open` para writes.
- No se puede relajar el guard.
- No se puede ejecutar QA de éxito `201` sin una sesión real segura.
- El QA de éxito `201` sigue pendiente.

### B) Reglas de seguridad del fixture
Cualquier fixture futuro debe cumplir:

- Sólo local/dev.
- Prohibido en producción.
- Debe exigir host local:
  - `127.0.0.1`.
  - `localhost`.
  - `::1` o equivalente explícitamente local.
- Debe exigir bandera de entorno explícita, por ejemplo:
  - `MXMED_SUBSCRIPTIONS_DEV_SESSION_FIXTURE_ENABLED=1`.
- Debe fallar cerrado si la bandera no existe.
- Debe fallar cerrado si el host no es local.
- Debe fallar cerrado si detecta ambiente productivo.
- Debe crear cookie PHP/session real, no headers QA.
- Debe producir `auth_mode=session_scope`.
- Debe crear sesión de médico principal, no operador.
- Debe limitarse al doctor fixture autorizado, inicialmente `doctor_id=1`.
- Debe ser temporal/dev-only.
- No debe crear usuarios.
- No debe cambiar contraseñas.
- No debe escribir DB.
- No debe modificar suscripciones.
- No debe ejecutar POST contractual.
- No debe activar capacidades.
- No debe conectar pagos.
- No debe tocar frontend productivo.
- Debe ser fácil de remover o quedar inactivo por default.

### C) Opciones evaluadas
Opción A — Login médico real existente:

- Sería ideal si existiera.
- No requeriría fixture especial.
- Problema: no se encontró flujo real usable que cree sesión de médico principal.
- Estado: no viable por ahora.

Opción B — Headers QA para write:

- Ya existen o se usan para lecturas locales.
- Problema: para el write contractual están explícitamente bloqueados.
- Riesgo: saltarse sesión real.
- Estado: rechazado.

Opción C — Insertar sesión manual por SQL o manipular session files:

- Problema: inseguro y frágil.
- Riesgo: sesión artificial no controlada y difícil de auditar.
- Estado: rechazado.

Opción D — Endpoint dev-only para crear sesión fixture:

- Ejemplo conceptual:
  - `POST /api/subscriptions/index.php/dev/session-fixture`.
- Sólo local/dev.
- Requiere bandera explícita.
- No escribe DB.
- Sólo crea `$_SESSION` y cookie PHP real.
- Asigna variables mínimas para `session_scope`.
- Permite validar con `GET /api/subscriptions/index.php/context/current`.
- Luego permite ejecutar POST contractual en microfase QA posterior usando cookie real.
- Riesgo: si no se protege bien, podría abrir acceso indebido.
- Mitigación: host local, env flag, fail closed y bloqueo en producción.
- Estado: opción recomendada para una microfase futura.

Opción E — Script CLI/dev que prepare cookie/session local:

- Ventaja: no expone endpoint HTTP.
- Problema: compatibilidad con `session_save_path`, cookie jar y servidor local puede ser frágil.
- Riesgo: menos parecido al flujo HTTP real.
- Estado: alternativa secundaria.

### D) Opción recomendada
La opción recomendada es:

- Opción D — Endpoint dev-only de fixture de sesión local/dev.

Condiciones:

- No se implementa en esta microfase.
- Debe implementarse en microfase separada.
- Debe quedar inactivo por default.
- Debe requerir host local.
- Debe requerir bandera explícita.
- Debe bloquear ambiente productivo.
- Debe limitarse al doctor fixture permitido.
- Debe crear sesión PHP real compatible con el guard actual.
- No debe cambiar el guard del endpoint contractual.
- No debe permitir write por headers QA.
- No debe crear DB writes.

### E) Variables mínimas de sesión a crear
Variables candidatas detectadas por el guard:

- `user_id`.
- `mxmed_user_id`.
- `auth_user_id`.
- `doctor_id`.
- `active_doctor_id`.
- `mxmed_doctor_id`.
- `entity_type=doctor`.
- `entity_id=1`.
- Rol opcional compatible:
  - `doctor`.
  - `medico`.
  - `principal`.
  - `owner`.

Reglas:

- La implementación futura debe usar el set mínimo que el guard actual realmente reconoce.
- No debe sobrepoblar la sesión.
- `user_id` fixture local/dev puede ser un entero controlado, por ejemplo `1`, sólo si el guard lo acepta y no implica DB write.
- `doctor_id` fixture inicial: `1`.
- `actor_role`: `doctor` o equivalente compatible.
- `operator_id`: ausente o `null`, para evitar bloqueo por operador.

### F) Validación posterior del fixture
La futura implementación del fixture debe probar:

1. Sin bandera env:
   - El fixture responde `403` o `404`.
2. Con bandera env pero host no local:
   - El fixture responde `403`.
3. Con bandera env y host local:
   - El fixture crea sesión PHP real.
4. GET `context/current` con cookie:
   - Devuelve `auth_mode=session_scope`.
   - Devuelve `doctor_id=1`.
   - Identifica médico principal.
5. POST contractual con esa cookie:
   - Queda para microfase posterior; no debe ejecutarse durante la implementación del fixture si se separa el QA.
6. POST con headers QA:
   - Sigue bloqueado.
7. `local_dev_open`:
   - Sigue bloqueado para POST write.

### G) Alcance fuera del fixture
El fixture no incluye:

- Pagos.
- Checkout.
- Facturación.
- Capacidades.
- `PublicProfilePlanCapabilities`.
- Frontend productivo.
- Perfil público.
- SEO.
- Creación de usuarios.
- Cambio de contraseñas.
- Modificación de DB.
- Ejecución automática del endpoint contractual.
- Limpieza de suscripciones.

### H) Secuencia futura recomendada
Secuencia:

1. `BE-Suscripciones-ContractAcceptance-LocalSessionFixture-DevOnly-01`.
   - Implementar endpoint/dev fixture local-only.
   - Inactivo por default.
   - Sin DB writes.
   - Sin POST contractual.
   - Sin frontend.
2. `QA-Suscripciones-ContractAcceptance-LocalSessionFixture-DevOnly-01`.
   - Validar que el fixture sólo funciona con host local y env flag.
   - Validar que crea `session_scope`.
   - Validar que headers QA siguen bloqueados para write.
   - Sin POST contractual `201` todavía, si se decide separar.
3. `QA-Suscripciones-ContractAcceptance-WriteEndpoint-SessionScope-Success-01`.
   - Ejecutar el POST `201` con cookie real.
   - Validar +1 aceptación.
   - Validar +1 suscripción.
   - Validar mismo `subscription_id`.
   - Validar read-model pagado activo.
   - Documentar que DB local/dev quedó modificada.
   - Sin frontend, sin pagos, sin capacidades.

### I) Riesgos residuales
Riesgos:

- Un fixture mal protegido podría ser riesgoso.
- Debe fallar cerrado.
- Debe ser local/dev-only.
- Debe estar desactivado por default.
- QA `201` generará datos persistentes local/dev.
- No se deben limpiar datos sin microfase explícita.
- No debe confundirse fixture con login productivo real.
- No debe usarse para operadores.
- No debe usarse para pacientes ni otros módulos.

### J) Siguiente microfase recomendada
Siguiente microfase recomendada:

- `BE-Suscripciones-ContractAcceptance-LocalSessionFixture-DevOnly-01`.

Objetivo:

- Implementar un fixture dev-only, local-only, desactivado por default, para crear sesión PHP real `session_scope` de médico principal `doctor_id=1`, sin DB writes, sin frontend, sin pagos y sin tocar el endpoint contractual.

### K) Límites de esta adenda
Esta adenda no implementa:

- Backend.
- Endpoint.
- Script.
- Frontend.
- SQL.
- Cambios DB.
- Sesión real.
- Usuario.
- POST contractual.
- QA `201`.
- Headers QA para write.
- Relajación de guards.
- Pagos.
- Checkout.
- Facturación.
- `PublicProfilePlanCapabilities`.
- Capacidades productivas.
- Perfil público.
- SEO productivo.

---

## Adenda PP-Decisiones 51 — Cierre del QA 201 con sesión real del endpoint write contractual

### A) Objetivo del QA cerrado
Se cerró la microfase:

- `QA-Suscripciones-ContractAcceptance-WriteEndpoint-SessionScope-Success-01`.

Resultado:

- PASS.

Objetivo validado:

- Validar el endpoint write contractual con sesión real `session_scope`.
- Confirmar que el endpoint crea aceptación contractual y suscripción operativa.
- Confirmar que ambas filas comparten el mismo `subscription_id`.
- Confirmar que el read-model cambia de `free_default` a plan pagado activo.
- Confirmar que el QA fue ejecutado únicamente en DB local/dev.

### B) Endpoint probado
Endpoint probado:

- `POST /api/subscriptions/index.php/entities/doctor/1/subscriptions`.

Alcance:

- Ejecución local/dev controlada.
- Sin frontend.
- Sin pagos.
- Sin checkout.
- Sin capacidades productivas.

### C) Sesión usada
Se usó el fixture dev-only/local-only:

- `POST /api/subscriptions/index.php/dev/session-fixture`.

Condiciones de sesión:

- Servidor local temporal: `127.0.0.1:8099`.
- Env flag temporal: `MXMED_SUBSCRIPTIONS_DEV_SESSION_FIXTURE_ENABLED=1`.
- Cookie PHP real creada.
- `context/current` devolvió `session_scope`.
- `doctor_id=1`.
- `operator_id=null`.
- No se usaron headers QA para write.
- No se relajaron guards.

### D) Payload usado
Payload conceptual usado:

```json
{
  "plan_code": "standard",
  "billing_period": "annual",
  "contract": {
    "version": "mxmed-subscriptions-v1",
    "hash": "sha256:qa-local-dev-contract-placeholder",
    "snapshot_url": "/legal/subscriptions/mxmed-subscriptions-v1.html",
    "title": "Contrato de suscripción México Médico"
  },
  "acceptance": {
    "source": "panel_subscription"
  }
}
```

Campos no enviados:

- `subscription_id`.
- `starts_at`.
- `expires_at`.
- `status`.
- `accepted_by_user_id`.
- `accepted_by_actor_role`.
- `accepted_by_operator_id`.
- `ip_address`.
- `user_agent`.
- `duration_days`.
- `price`.
- `capabilities`.
- `source` interno.
- `deleted_at`.
- `contract_acceptance_uuid`.

### E) Resultado HTTP
Resultado del POST contractual:

- HTTP `201`.
- `ok=true`.
- `meta.source=subscriptions_write_v1`.
- `meta.auth_mode=session_scope`.

La respuesta no expuso:

- IP.
- User-agent.
- Evidencia legal completa.
- Capacidades.
- Datos sensibles.

### F) Identificadores creados
Identificadores creados en DB local/dev:

- `subscription_id`: `9700c0d5-6dc5-490b-bdb4-766dee490590`.
- `contract_acceptance_uuid`: `e25d09de-1e54-45c5-95ae-3b0637151d20`.

Estos identificadores pertenecen únicamente al entorno local/dev usado en el QA.

### G) Conteos DB local/dev
Conteos antes:

- `subscription_contract_acceptances=0`.
- `profile_subscriptions=0`.
- Aceptaciones doctor 1: `0`.
- Suscripciones doctor 1: `0`.

Conteos después:

- `subscription_contract_acceptances=1`.
- `profile_subscriptions=1`.
- Aceptaciones doctor 1: `1`.
- Suscripciones doctor 1: `1`.

Delta:

- Aceptación contractual: `+1`.
- Suscripción operativa: `+1`.

### H) Validación de filas
Aceptación contractual validada:

- `entity_type=doctor`.
- `entity_id=1`.
- `doctor_id=1`.
- `plan_code=standard`.
- `billing_period=annual`.
- `duration_days=365`.
- `contract_version=mxmed-subscriptions-v1`.
- `contract_hash` con prefijo `sha256:`.
- `contract_snapshot_url` presente.
- `accepted_by_user_id` presente.
- `accepted_by_actor_role=doctor`.
- `accepted_by_operator_id=null`.
- `acceptance_source=panel_subscription`.
- `status=accepted`.
- `deleted_at=null`.

Suscripción operativa validada:

- Mismo `subscription_id`.
- Plan `standard`.
- Billing `annual`.
- Status `active`.
- `starts_at` presente.
- `expires_at` presente.
- `contract_accepted_at` presente.
- `contract_version` presente.
- `contract_acceptance_source=panel_subscription`.
- `deleted_at=null`.

### I) Read-model
Read-model antes:

- `effective_plan_code=free`.
- `status=free_default`.

Read-model después:

- `effective_plan_code=standard`.
- `status=active`.
- `starts_at` presente.
- `expires_at` presente.
- `contract_accepted_at` presente.
- Ya no es `free_default`.

### J) Alcance preservado
El QA preservó explícitamente:

- No frontend.
- No `p-suscripcion` write.
- No pagos.
- No checkout.
- No facturación.
- No capacidades productivas.
- No `PublicProfilePlanCapabilities`.
- No perfil público.
- No SEO.
- No filas `free`.
- No aceptación `free`.
- No limpieza de datos.
- No SQL DDL.
- No cambios de schema.
- No escritura manual por SQL.
- No headers QA para write.
- No guards relajados.

### K) Estado de DB local/dev
Estado posterior:

- La DB local/dev quedó modificada por el endpoint contractual de forma esperada.
- No hubo cambios Git durante el QA.
- No se debe limpiar ni borrar la evidencia sin microfase explícita.
- El doctor 1 ahora tiene una suscripción activa local/dev.
- Futuros QA sobre doctor 1 pueden recibir `409` si intentan crear otra suscripción activa.

### L) Riesgos residuales
Riesgos residuales:

- No hay idempotencia robusta con storage dedicado.
- Doble submit concurrente sigue como riesgo residual.
- El fixture dev-only debe permanecer desactivado por default.
- La suscripción creada no implica pago real.
- La suscripción creada no implica checkout.
- La suscripción creada no activa capacidades productivas.
- Producción sigue fuera de alcance.
- Si se requiere repetir QA `201`, se debe elegir otro fixture o diseñar limpieza local/dev explícita.

### M) Siguiente microfase recomendada
Preferencia de cierre:

- Primero cerrar documentalmente y pushear esta adenda.

Siguiente microfase recomendada después del cierre documental:

- `QA-Suscripciones-ContractAcceptance-WriteEndpoint-ActiveConflict-01`.

Objetivo:

- Validar que, después de existir una suscripción activa para doctor 1 en local/dev, un segundo POST contractual controlado con la misma sesión devuelva `409` y no cree una segunda aceptación ni una segunda suscripción.

### N) Límites de esta adenda
Esta adenda no ejecuta ni implementa:

- Backend.
- Frontend.
- SQL DDL.
- Cambios de schema.
- Escrituras SQL manuales.
- Nuevo QA con DB writes.
- Nuevo POST contractual `201`.
- Headers QA para write.
- Relajación de guards.
- Pagos.
- Checkout.
- Facturación.
- `PublicProfilePlanCapabilities`.
- Capacidades productivas.
- Perfil público.
- SEO productivo.
- Limpieza de datos.

---

## Adenda PP-Decisiones 52 — Cierre del QA de conflicto activo del endpoint write contractual

### A) Objetivo del QA cerrado
Se cerró la microfase:

- `QA-Suscripciones-ContractAcceptance-WriteEndpoint-ActiveConflict-01`.

Resultado:

- PASS sin cambios.

Objetivo validado:

- Validar que el endpoint write contractual bloquea un segundo intento de contratación si ya existe una suscripción activa.
- Confirmar que responde `409`.
- Confirmar que no crea una segunda aceptación contractual.
- Confirmar que no crea una segunda suscripción operativa.
- Confirmar que el read-model permanece en `standard active`.

### B) Endpoint probado
Endpoint probado:

- `POST /api/subscriptions/index.php/entities/doctor/1/subscriptions`.

### C) Sesión usada
Se usó el fixture dev-only/local-only:

- `POST /api/subscriptions/index.php/dev/session-fixture`.

Condiciones:

- Servidor local temporal: `127.0.0.1:8099`.
- Env flag temporal: `MXMED_SUBSCRIPTIONS_DEV_SESSION_FIXTURE_ENABLED=1`.
- Cookie PHP real creada.
- `context/current` devolvió `session_scope`.
- `doctor_id=1`.
- `operator_id=null`.
- No se usaron headers QA para write.
- No se relajaron guards.

### D) Estado previo de DB local/dev
Antes del QA de conflicto activo:

- Ya existía una suscripción activa para `doctor_id=1`.
- Esa suscripción fue creada por el QA `201` previo.
- `subscription_id` existente: `9700c0d5-6dc5-490b-bdb4-766dee490590`.
- `contract_acceptance_uuid` existente: `e25d09de-1e54-45c5-95ae-3b0637151d20`.
- Plan/status previo: `standard / active`.

Conteos antes:

- `subscription_contract_acceptances=1`.
- `profile_subscriptions=1`.
- Aceptaciones doctor 1: `1`.
- Suscripciones doctor 1: `1`.

### E) Segundo POST contractual
Se ejecutó un segundo POST contractual controlado con payload válido:

- Plan `standard`.
- Billing `annual`.
- Contrato `mxmed-subscriptions-v1`.
- Source `panel_subscription`.

Resultado:

- HTTP `409`.
- `ok=false`.
- Código/mensaje: `active_subscription_exists` / `active subscription already exists`.
- No hubo `201`.
- No se usaron headers QA.
- No se relajaron guards.

### F) Conteos después
Conteos después:

- `subscription_contract_acceptances=1`.
- `profile_subscriptions=1`.
- Aceptaciones doctor 1: `1`.
- Suscripciones doctor 1: `1`.

Delta:

- Aceptaciones: `0`.
- Suscripciones: `0`.

### G) Validación de integridad
Integridad confirmada:

- No se creó segunda aceptación.
- No se creó segunda suscripción.
- La suscripción existente quedó intacta.
- La aceptación existente quedó intacta.
- `deleted_at=null` se conserva en los registros vigentes.
- No hubo filas `free`.
- No hubo aceptación `free`.
- No hubo limpieza.

### H) Read-model posterior
Read-model posterior:

- HTTP `200`.
- `effective_plan_code=standard`.
- `status=active`.
- `starts_at` presente.
- `expires_at` presente.
- `contract_accepted_at` presente.
- No volvió a `free_default`.
- No expone evidencia legal completa.
- No expone IP/user-agent.
- No activa capacidades productivas.

### I) Alcance preservado
El QA preservó explícitamente:

- No frontend.
- No `p-suscripcion` write.
- No pagos.
- No checkout.
- No facturación.
- No capacidades productivas.
- No `PublicProfilePlanCapabilities`.
- No perfil público.
- No SEO.
- No SQL DDL.
- No cambios de schema.
- No escritura manual por SQL.
- No headers QA para write.
- No guards relajados.
- No limpieza de datos.

### J) Estado funcional actual
Estado funcional:

- El endpoint write contractual ya tiene QA de éxito `201` validado.
- El endpoint write contractual ya tiene QA de conflicto activo `409` validado.
- Doctor 1 queda con suscripción activa local/dev.
- Repetir QA `201` con doctor 1 ya no aplica mientras exista esa suscripción activa.
- Futuras pruebas de éxito deben usar otro doctor fixture o una microfase explícita de limpieza/rollback local/dev.
- No se deben limpiar datos sin autorización y microfase explícita.

### K) Riesgos residuales
Riesgos residuales:

- Sigue pendiente idempotencia robusta con storage dedicado.
- Doble submit concurrente sigue como riesgo residual.
- La mitigación actual confirmada es:
  - bloqueo por suscripción activa;
  - respuesta `409`;
  - no creación de segundas filas en caso secuencial.
- Producción sigue fuera de alcance.
- La suscripción local/dev no implica pago real.
- La suscripción local/dev no implica checkout.
- La suscripción local/dev no activa capacidades productivas.

### L) Siguiente microfase recomendada
Preferencia de cierre:

- Primero cerrar documentalmente y pushear esta adenda.

Siguiente microfase recomendada después del cierre documental:

- `QA-Suscripciones-ContractAcceptance-WriteEndpoint-InvalidPayload-01`.

Objetivo:

- Validar respuestas `422` para payloads inválidos sin modificar DB:
  - `plan_code=free`;
  - contrato faltante;
  - hash sin prefijo `sha256:`;
  - snapshot faltante;
  - campos prohibidos desde cliente.

### M) Límites de esta adenda
Esta adenda no ejecuta ni implementa:

- Backend.
- Frontend.
- SQL DDL.
- Cambios de schema.
- Escrituras SQL manuales.
- Nuevo QA con DB writes.
- POST contractual.
- Headers QA para write.
- Relajación de guards.
- Pagos.
- Checkout.
- Facturación.
- `PublicProfilePlanCapabilities`.
- Capacidades productivas.
- Perfil público.
- SEO productivo.
- Limpieza de datos.

---

## Adenda PP-Decisiones 53 — Cierre del QA de payload inválido del endpoint write contractual

### A) Objetivo del QA cerrado
Se cerró la microfase:

- `QA-Suscripciones-ContractAcceptance-WriteEndpoint-InvalidPayload-01`.

Resultado:

- PASS sin cambios.

Objetivo validado:

- Validar que el endpoint write contractual rechaza payloads inválidos.
- Confirmar respuestas `422`.
- Confirmar que no se crean nuevas aceptaciones contractuales.
- Confirmar que no se crean nuevas suscripciones operativas.
- Confirmar que el read-model permanece en `standard active`.
- Confirmar que no se activan pagos, capacidades ni frontend.

### B) Endpoint probado
Endpoint probado:

- `POST /api/subscriptions/index.php/entities/doctor/1/subscriptions`.

### C) Sesión usada
Se usó el fixture dev-only/local-only:

- `POST /api/subscriptions/index.php/dev/session-fixture`.

Condiciones:

- Servidor local temporal: `127.0.0.1:8099`.
- Env flag temporal: `MXMED_SUBSCRIPTIONS_DEV_SESSION_FIXTURE_ENABLED=1`.
- Cookie PHP real creada.
- `context/current` devolvió `session_scope`.
- `doctor_id=1`.
- `operator_id=null`.
- No se usaron headers QA para write.

### D) Estado previo de DB local/dev
Antes del QA de payload inválido:

- Ya existía una suscripción activa para `doctor_id=1`.
- `subscription_id` existente: `9700c0d5-6dc5-490b-bdb4-766dee490590`.
- Plan/status previo: `standard / active`.

Conteos antes:

- `subscription_contract_acceptances=1`.
- `profile_subscriptions=1`.
- Aceptaciones doctor 1: `1`.
- Suscripciones doctor 1: `1`.

### E) Casos probados y resultados
Caso 1 — `plan_code=free`:

- HTTP `422`.
- Error `plan_not_contractable`.
- No hubo `201`.

Caso 2 — Contrato faltante:

- HTTP `422`.
- Error `contract_invalid`.
- No hubo `201`.

Caso 3 — Hash inválido sin prefijo `sha256:`:

- HTTP `422`.
- Error `contract_invalid`.
- No hubo `201`.

Caso 4 — Snapshot faltante:

- HTTP `422`.
- Error `contract_invalid`.
- No hubo `201`.

Caso 5 — Campos prohibidos enviados desde cliente:

- HTTP `422`.
- Error `forbidden_fields`.
- No hubo `201`.

### F) Orden de validación observado
Hallazgos:

- Los cinco casos inválidos devolvieron `422`.
- No se observó `409` por conflicto activo en estos casos.
- El endpoint valida payload antes de evaluar conflicto activo.
- Esto permite rechazar payloads inválidos sin crear filas, incluso con suscripción activa previa.

### G) Conteos después
Conteos después:

- `subscription_contract_acceptances=1`.
- `profile_subscriptions=1`.
- Aceptaciones doctor 1: `1`.
- Suscripciones doctor 1: `1`.

Delta:

- Aceptaciones: `0`.
- Suscripciones: `0`.

### H) Validación de integridad
Integridad confirmada:

- No se creó nueva aceptación.
- No se creó nueva suscripción.
- La suscripción existente quedó intacta.
- La aceptación existente quedó intacta.
- No se creó fila `free`.
- No se creó aceptación `free`.
- No hubo limpieza.

### I) Read-model posterior
Read-model posterior:

- HTTP `200`.
- `effective_plan_code=standard`.
- `status=active`.
- `starts_at` presente.
- `expires_at` presente.
- `contract_accepted_at` presente.
- No volvió a `free_default`.
- No expone evidencia legal completa.
- No expone IP/user-agent.
- No activa capacidades.

### J) Alcance preservado
El QA preservó explícitamente:

- No frontend.
- No `p-suscripcion` write.
- No pagos.
- No checkout.
- No facturación.
- No capacidades productivas.
- No `PublicProfilePlanCapabilities`.
- No perfil público.
- No SEO.
- No SQL DDL.
- No cambios de schema.
- No escritura manual por SQL.
- No headers QA para write.
- No guards relajados.
- No limpieza de datos.

### K) Estado funcional actual
Estado funcional:

- Endpoint write contractual ya tiene QA de éxito `201` validado.
- Endpoint write contractual ya tiene QA de conflicto activo `409` validado.
- Endpoint write contractual ya tiene QA de payload inválido `422` validado.
- Doctor 1 permanece con suscripción activa local/dev.
- Repetir QA `201` con doctor 1 ya no aplica mientras exista esa suscripción activa.
- Futuros QA de éxito deben usar otro doctor fixture o una microfase explícita de limpieza/rollback local/dev.
- No se deben limpiar datos sin autorización y microfase explícita.

### L) Riesgos residuales
Riesgos residuales:

- Sigue pendiente idempotencia robusta con storage dedicado.
- Doble submit concurrente sigue como riesgo residual.
- El fixture dev-only debe permanecer desactivado por default.
- La suscripción local/dev no implica pago real.
- La suscripción local/dev no implica checkout.
- La suscripción local/dev no activa capacidades productivas.
- Producción sigue fuera de alcance.

### M) Siguiente microfase recomendada
Preferencia de cierre:

- Primero cerrar documentalmente y pushear esta adenda.

Siguiente microfase recomendada después del cierre documental:

- `QA-Suscripciones-ContractAcceptance-WriteEndpoint-AuthMatrix-01`.

Objetivo:

- Validar matriz de auth del endpoint write:
  - sin sesión;
  - `local_dev_open`;
  - headers QA;
  - fixture `session_scope`;
  - operador bloqueado si se puede simular sin DB writes;
  - confirmar que sólo `session_scope` médico principal permite pasar a validaciones de negocio.

### N) Límites de esta adenda
Esta adenda no ejecuta ni implementa:

- Backend.
- Frontend.
- SQL DDL.
- Cambios de schema.
- Escrituras SQL manuales.
- Nuevo QA con DB writes.
- POST contractual.
- Headers QA para write.
- Relajación de guards.
- Pagos.
- Checkout.
- Facturación.
- `PublicProfilePlanCapabilities`.
- Capacidades productivas.
- Perfil público.
- SEO productivo.
- Limpieza de datos.

---

## Adenda PP-Decisiones 54 — Cierre del QA de matriz auth del endpoint write contractual

### A) Objetivo del QA cerrado
Se cerró la microfase:

- `QA-Suscripciones-ContractAcceptance-WriteEndpoint-AuthMatrix-01`.

Resultado:

- PASS sin cambios.

Objetivo validado:

- Validar la matriz de autenticación/autorización del endpoint write contractual.
- Confirmar que `local_dev_open` no autoriza writes.
- Confirmar que headers QA no autorizan writes.
- Confirmar que sólo `session_scope` de médico principal permite llegar a negocio.
- Confirmar que no se crean nuevas filas.
- Confirmar que no se modifica DB local/dev.

### B) Endpoint probado
Endpoint probado:

- `POST /api/subscriptions/index.php/entities/doctor/1/subscriptions`.

### C) Sesión / fixture usado
Se usó el fixture dev-only/local-only:

- `POST /api/subscriptions/index.php/dev/session-fixture`.

Condiciones:

- Servidor local temporal: `127.0.0.1:8099`.
- Env flag temporal: `MXMED_SUBSCRIPTIONS_DEV_SESSION_FIXTURE_ENABLED=1`.
- Cookie PHP real creada.
- `context/current` devolvió `session_scope`.
- `doctor_id=1`.
- `operator_id=null`.

### D) Estado previo de DB local/dev
Antes del QA de matriz auth:

- Ya existía una suscripción activa para `doctor_id=1`.
- `subscription_id` existente: `9700c0d5-6dc5-490b-bdb4-766dee490590`.
- Plan/status previo: `standard / active`.

Conteos antes:

- `subscription_contract_acceptances=1`.
- `profile_subscriptions=1`.
- Aceptaciones doctor 1: `1`.
- Suscripciones doctor 1: `1`.

### E) Casos de auth validados
Caso 1 — Sin sesión / `local_dev_open`:

- HTTP `403`.
- Error `forbidden`.
- Mensaje `local_dev_open does not authorize writes`.
- No hubo `201`.
- No hubo DB writes.

Caso 2 — `local_dev_open`:

- HTTP `403`.
- Error `forbidden`.
- Mensaje `local_dev_open does not authorize writes`.
- No hubo `201`.
- No hubo DB writes.

Caso 3 — Headers QA:

- HTTP `403`.
- Error `forbidden`.
- Mensaje `header scope does not authorize writes`.
- No hubo `201`.
- No hubo DB writes.

Caso 4 — `session_scope` médico principal:

- HTTP `409`.
- Error `active_subscription_exists`.
- Sí llegó a validación de negocio.
- Confirma que `session_scope` pasa el auth/write guard.
- No hubo `201` porque ya existe suscripción activa.
- No hubo DB writes.

Caso 5 — Operador:

- No ejecutado.
- Motivo: no existe fixture seguro de operador sin modificar DB/archivos.
- Simularlo con headers QA sólo probaría `header_scope`.
- Queda pendiente únicamente si se diseña fixture/permiso operador futuro.

### F) Conclusión de matriz
Conclusiones:

- Sólo `session_scope` médico principal permite llegar a negocio.
- Sin sesión y `local_dev_open` se bloquean antes de negocio.
- Headers QA se bloquean para write.
- `local_dev_open` no autoriza writes.
- El endpoint conserva separación entre QA headers de lectura y writes contractuales.
- Operador sigue bloqueado hasta permiso/fixture seguro futuro.
- No se relajaron guards.

### G) Conteos después
Conteos después:

- `subscription_contract_acceptances=1`.
- `profile_subscriptions=1`.
- Aceptaciones doctor 1: `1`.
- Suscripciones doctor 1: `1`.

Delta:

- Aceptaciones: `0`.
- Suscripciones: `0`.

### H) Integridad
Integridad confirmada:

- No se creó nueva aceptación.
- No se creó nueva suscripción.
- La suscripción existente quedó intacta.
- La aceptación existente quedó intacta.
- No se creó fila `free`.
- No se creó aceptación `free`.
- No hubo limpieza.

### I) Read-model posterior
Read-model posterior:

- HTTP `200`.
- `effective_plan_code=standard`.
- `status=active`.
- `starts_at` presente.
- `expires_at` presente.
- `contract_accepted_at` presente.
- No volvió a `free_default`.
- No expone evidencia legal completa.
- No expone IP/user-agent.
- No activa capacidades.

### J) Alcance preservado
El QA preservó explícitamente:

- No frontend.
- No `p-suscripcion` write.
- No pagos.
- No checkout.
- No facturación.
- No capacidades productivas.
- No `PublicProfilePlanCapabilities`.
- No perfil público.
- No SEO.
- No SQL DDL.
- No cambios de schema.
- No escritura manual por SQL.
- No headers QA para write exitoso.
- No guards relajados.
- No limpieza de datos.

### K) Estado funcional actual
Estado funcional:

- Endpoint write contractual ya tiene QA de éxito `201` validado.
- Endpoint write contractual ya tiene QA de conflicto activo `409` validado.
- Endpoint write contractual ya tiene QA de payload inválido `422` validado.
- Endpoint write contractual ya tiene QA de matriz auth validado.
- Doctor 1 permanece con suscripción activa local/dev.
- Repetir QA `201` con doctor 1 ya no aplica mientras exista esa suscripción activa.
- Futuros QA de éxito deben usar otro doctor fixture o una microfase explícita de limpieza/rollback local/dev.
- No se deben limpiar datos sin autorización y microfase explícita.

### L) Riesgos residuales
Riesgos residuales:

- Sigue pendiente idempotencia robusta con storage dedicado.
- Doble submit concurrente sigue como riesgo residual.
- Operador write sigue bloqueado y no validado con fixture real.
- El fixture dev-only debe permanecer desactivado por default.
- La suscripción local/dev no implica pago real.
- La suscripción local/dev no implica checkout.
- La suscripción local/dev no activa capacidades productivas.
- Producción sigue fuera de alcance.

### M) Siguiente microfase recomendada
Preferencia de cierre:

- Primero cerrar documentalmente y pushear esta adenda.

Siguiente microfase recomendada después del cierre documental:

- `BE/DIAG-Suscripciones-ContractAcceptance-WriteEndpoint-IdempotencyReadiness-01`.

Objetivo:

- Diagnosticar si conviene implementar idempotencia robusta con storage dedicado para el endpoint write contractual, considerando que ya existen mitigaciones por payload validation y conflicto activo `409`, pero sigue el riesgo de doble submit concurrente.

### N) Límites de esta adenda
Esta adenda no ejecuta ni implementa:

- Backend.
- Frontend.
- SQL DDL.
- Cambios de schema.
- Escrituras SQL manuales.
- Nuevo QA con DB writes.
- POST contractual.
- Headers QA para write.
- Relajación de guards.
- Pagos.
- Checkout.
- Facturación.
- `PublicProfilePlanCapabilities`.
- Capacidades productivas.
- Perfil público.
- SEO productivo.
- Limpieza de datos.

---

## Adenda PP-Decisiones 55 — Diseño de idempotencia del endpoint write contractual

### A) Problema a resolver
El endpoint write contractual ya funciona para el flujo normal:

- `POST /api/subscriptions/index.php/entities/{entity_type}/{entity_id}/subscriptions`.
- Ya cuenta con QA de éxito `201`.
- Ya cuenta con QA de conflicto activo `409`.
- Ya cuenta con QA de payload inválido `422`.
- Ya cuenta con QA de matriz auth.
- Ya inserta aceptación contractual y suscripción operativa en una sola transacción.
- Ya ejecuta rollback ante fallo transaccional.

Riesgo pendiente:

- Falta idempotencia robusta.
- `409 active_subscription_exists` protege reintentos secuenciales después de existir una suscripción activa.
- `409 active_subscription_exists` no cubre completamente doble submit concurrente.
- Existe ventana si dos requests concurrentes pasan la consulta de suscripción activa antes del commit.
- Sin idempotencia, un cliente que reintenta tras timeout no sabe si el primer request completó.
- Antes de conectar UI write, pagos o checkout, conviene definir idempotencia robusta.

### B) Objetivo de idempotencia
La idempotencia debe permitir:

- Evitar duplicar aceptación contractual.
- Evitar duplicar `profile_subscriptions`.
- Permitir reintentos seguros del mismo request.
- Responder de forma estable a replays.
- Detectar misma key con payload distinto.
- Bloquear o controlar requests en estado `processing`.
- Mantener trazabilidad sin guardar datos sensibles innecesarios.
- Preparar una base compatible con futuro checkout/pagos.

### C) Header recomendado
Header recomendado:

- `Idempotency-Key`.

Reglas sugeridas:

- Recomendado para v1 backend.
- Obligatorio antes de UI write, pagos o checkout productivo.
- Debe ser string.
- Debe tener longitud mínima y máxima razonable.
- Debe aceptar valores generados por cliente, preferentemente UUID v4.
- Debe rechazarse si contiene caracteres peligrosos.
- No debe contener datos sensibles.
- No debe reutilizarse entre entidades, usuarios u operaciones distintas.

### D) Scope recomendado
La key debe estar acotada por:

- `user_id`.
- `entity_type`.
- `entity_id`.
- `doctor_id`.
- Operación: `subscriptions.create_with_contract_acceptance`.
- Actor role.

Reglas:

- Una key usada por otro usuario no debe aplicar.
- Una key usada para otra entidad no debe aplicar.
- Una key usada para otro endpoint no debe aplicar.
- El scope evita replay indebido entre actores.

### E) Request hash
El backend debe calcular un hash del request canonicalizado.

Debe incluir:

- Ruta/acción.
- `entity_type`.
- `entity_id`.
- `user_id`.
- `plan_code`.
- `billing_period`.
- `contract.version`.
- `contract.hash`.
- `contract.snapshot_url`.
- `acceptance.source`.

Debe excluir:

- Fechas generadas por backend.
- IP.
- User-agent.
- `subscription_id`.
- Campos prohibidos.
- Datos sensibles.

Reglas:

- Usar hash tipo `sha256`.
- El request hash permite detectar replay del mismo payload.
- El request hash permite detectar la misma key con payload distinto.
- No guardar payload completo si basta con guardar hash canonicalizado.

### F) Comportamiento de replay
Primera vez con key válida:

- Crear registro de idempotencia en estado `processing`.
- Ejecutar transacción contractual.
- Crear aceptación contractual.
- Crear `profile_subscriptions`.
- Guardar referencias:
  - `subscription_id`.
  - `contract_acceptance_uuid`.
  - HTTP status.
  - Estado `completed`.
- Responder `201`.

Misma key + mismo payload + `completed`:

- No crear filas nuevas.
- Devolver la misma respuesta o reconstruirla desde referencias.
- HTTP recomendado: `200` o `201` repetido, según decisión futura.
- La decisión debe ser consistente y documentada antes de implementar.

Misma key + payload distinto:

- Rechazar.
- HTTP recomendado: `409 idempotency_key_reused_with_different_payload`.
- Alternativa posible: `422` si se decide tratarlo como payload inválido.
- Preferencia inicial: `409`.

Misma key en estado `processing`:

- No iniciar segunda transacción.
- Responder `409 request_already_processing`.
- Alternativa posible: `425 Too Early`.
- Preferencia inicial: `409`.

Key expirada:

- Requiere decisión explícita.
- Si existe operación completada, conviene seguir devolviendo referencia mientras esté disponible.
- Si expiró sin completar, marcar `expired` y exigir nueva key.
- No reutilizar silenciosamente la misma key vencida sin decisión de producto/seguridad.

### G) Storage recomendado
Tabla sugerida:

- `subscription_write_idempotency_keys`.

Campos conceptuales mínimos:

- `id BIGINT UNSIGNED AUTO_INCREMENT`.
- `idempotency_key VARCHAR(128) NOT NULL`.
- `request_hash CHAR(64) NOT NULL`.
- `entity_type VARCHAR(64) NOT NULL`.
- `entity_id VARCHAR(64) NOT NULL`.
- `doctor_id VARCHAR(64) NULL`.
- `user_id BIGINT UNSIGNED NOT NULL`.
- `actor_role VARCHAR(32) NULL`.
- `operation VARCHAR(96) NOT NULL`.
- `status VARCHAR(32) NOT NULL`.
- `subscription_id CHAR(36) NULL`.
- `contract_acceptance_uuid CHAR(36) NULL`.
- `response_http_status SMALLINT UNSIGNED NULL`.
- `response_body_json JSON NULL` o `TEXT NULL`, según compatibilidad DB.
- `locked_at DATETIME NULL`.
- `completed_at DATETIME NULL`.
- `expires_at DATETIME NOT NULL`.
- `created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP`.
- `updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`.
- `deleted_at DATETIME NULL`.

Estados conceptuales:

- `processing`.
- `completed`.
- `failed`.
- `expired`.
- `cancelled`.

### H) Índices y unicidad sugeridos
Unique recomendado por scope:

- `idempotency_key`.
- `user_id`.
- `entity_type`.
- `entity_id`.
- `operation`.

Alternativa:

- Unique por hash de scope si los índices largos preocupan.

Índices sugeridos:

- `status`.
- `expires_at`.
- `entity_type, entity_id`.
- `doctor_id`.
- `subscription_id`.
- `contract_acceptance_uuid`.
- `created_at`.

Regla:

- No definir índices ni DDL todavía sin microfase DB específica.

### I) Guardado de response
Opción A — guardar response completa sanitizada:

- Ventaja: replay exacto.
- Ventaja: comportamiento consistente.
- Riesgo: guardar datos innecesarios.
- Riesgo: compatibilidad `JSON`/`TEXT`.
- Riesgo: cuidado adicional con datos sensibles.

Opción B — guardar sólo referencias y reconstruir respuesta:

- Ventaja: menos datos.
- Ventaja: menos riesgo de exponer información.
- Riesgo: la respuesta reconstruida puede variar si cambia el read-model.

Recomendación v1:

- Guardar referencias + HTTP status.
- Guardar response sanitizada sólo si se requiere replay exacto.
- Nunca guardar IP/user-agent si no es necesario.
- Nunca guardar payload completo.
- Guardar `request_hash` en lugar de payload.

### J) TTL y limpieza
TTL sugerido:

- 24 horas para QA/local/dev.
- 24 horas a 7 días para producción, según decisión futura de checkout.

Reglas:

- `expires_at` debe ser obligatorio.
- Cleanup futuro debe vivir en job separado.
- No borrar registros activos manualmente.
- Expirar lógicamente antes de limpieza física.
- `deleted_at` queda reservado para soft delete administrativo/controlado.

### K) Integración transaccional futura
Flujo sugerido:

1. Validar auth/write guard.
2. Validar payload básico.
3. Validar `Idempotency-Key`.
4. Calcular scope y request hash.
5. Intentar crear registro idempotency `processing`.
6. Si hay unique collision:
   - leer registro existente;
   - comparar scope/hash/status;
   - responder replay, conflict o processing.
7. Ejecutar transacción contractual.
8. Crear aceptación contractual.
9. Crear `profile_subscriptions`.
10. Guardar referencias en registro idempotency.
11. Marcar `completed`.
12. Responder.
13. Si falla:
   - rollback contractual;
   - marcar `failed` o liberar según decisión;
   - no dejar `processing` colgado sin TTL.

### L) Alternativas evaluadas
Mantener mitigación actual:

- Ventaja: no toca schema.
- Riesgo: no cubre doble submit concurrente.
- Estado: aceptable temporalmente local/dev sin UI/pagos.

Lock transaccional / `SELECT FOR UPDATE`:

- Puede ayudar.
- Requiere fila bloqueable.
- Bloquear ausencia de fila es delicado.
- No sustituye replay semántico.

Advisory lock MySQL `GET_LOCK`:

- Puede mitigar concurrencia.
- Es menos auditable.
- Depende de conexión/timeouts.
- No resuelve replay tras timeout.

Unique compuesto por entidad/status:

- MySQL/MariaDB no tiene unique parcial simple.
- Requeriría columna auxiliar/generada o diseño adicional.
- Puede ayudar a integridad.
- No resuelve replay de cliente.

Tabla de idempotencia dedicada:

- Más robusta.
- Auditable.
- Preparada para checkout.
- Requiere diseño DB y QA.
- Opción recomendada.

### M) Relación con frontend, pagos y checkout
Reglas:

- `p-suscripcion` debe seguir read-only hasta decisión explícita.
- Cuando exista UI write, frontend debe enviar `Idempotency-Key`.
- Antes de pagos/checkout productivo conviene tener idempotencia robusta.
- La idempotencia no debe activar capacidades.
- La idempotencia no debe conectar pagos.
- La idempotencia debe poder enlazar en el futuro con checkout intent/payment intent.
- No diseñar cobros todavía.

### N) Seguridad
Reglas de seguridad:

- La key debe ligarse a usuario y entidad.
- No permitir replay entre usuarios.
- No permitir replay entre entidades.
- No guardar datos sensibles.
- No guardar payload completo.
- Validar formato y longitud.
- Rate limit futuro recomendado.
- No confiar en la key para auth.
- Auth/session debe validarse antes de idempotencia.
- Si cambia sesión/actor, la key no debe aplicar.

### O) QA futuro
Checklist mínimo:

- Sin key.
- Key inválida.
- Primera request con key válida.
- Misma key + mismo payload.
- Misma key + payload distinto.
- Misma key en `processing`.
- Key expirada.
- Falla durante inserción de aceptación.
- Falla durante inserción de suscripción.
- Rollback completo.
- No doble fila en `subscription_contract_acceptances`.
- No doble fila en `profile_subscriptions`.
- Dos requests concurrentes.
- Replay no expone datos sensibles.
- Auth sigue bloqueando `local_dev_open`.
- Auth sigue bloqueando headers QA para write.

### P) Decisión recomendada
Decisión:

- No implementar idempotencia directa todavía.
- Documentar primero el storage exacto antes de tocar schema/backend.
- La mitigación actual es aceptable temporalmente en local/dev sin UI/pagos.
- No llevar a checkout/productivo sin resolver idempotencia o lock robusto.

Siguiente paso seguro:

- `DB/DIAG-Suscripciones-ContractAcceptance-IdempotencyStorageDecision-01`.

Objetivo:

- Diagnosticar el storage exacto de idempotencia, tipos, índices, unicidad, TTL, `JSON`/`TEXT` y compatibilidad MySQL/MariaDB antes de crear SQL draft.

### Q) Límites de esta adenda
Esta adenda no ejecuta ni implementa:

- Backend.
- Frontend.
- SQL DDL.
- Migrations.
- Cambios de schema.
- Escrituras SQL manuales.
- POST contractual.
- QA con DB writes.
- Headers QA para write.
- Relajación de guards.
- Pagos.
- Checkout.
- Facturación.
- `PublicProfilePlanCapabilities`.
- Capacidades productivas.
- Perfil público.
- SEO productivo.
- Limpieza de datos.

---

## Adenda PP-Decisiones 56 — Decisión de storage de idempotencia del endpoint write contractual

### A) Objetivo de la decisión
Esta adenda formaliza la decisión de storage de idempotencia antes de crear cualquier SQL draft.

Objetivos:

- Separar el control de reintentos del snapshot operativo.
- Separar el control de reintentos de la evidencia legal.
- Separar idempotencia de pagos, checkout y capacidades.
- Preparar una base auditable para replay seguro.
- Preparar control de doble submit con la misma key.
- Mantener fuera de alcance la ejecución de DDL y cualquier cambio de DB.

La decisión aplica al endpoint:

- `POST /api/subscriptions/index.php/entities/{entity_type}/{entity_id}/subscriptions`.

### B) Tabla dedicada
Tabla decidida para futuro draft SQL:

- `subscription_write_idempotency_keys`.

Motivos:

- Separa idempotencia del read-model operativo.
- Separa idempotencia de la evidencia contractual.
- Permite modelar estados `processing`, `completed`, `failed`, `expired` y `cancelled`.
- Permite TTL por request.
- Permite replay controlado.
- Permite guardar referencias sin duplicar aceptación ni suscripción.
- Es extensible a checkout futuro sin acoplar pagos ahora.

### C) Por qué no usar tablas existentes
No usar `profile_subscriptions`:

- Es snapshot operativo/read-model.
- No debe modelar requests.
- No modela `processing`.
- No modela TTL.
- No modela replay.
- Mezclaría vigencia de suscripción con transporte/idempotencia.

No usar `subscription_contract_acceptances`:

- Es evidencia legal/auditoría contractual.
- Debe conservar aceptaciones contractuales.
- No debe registrar requests fallidos o en `processing`.
- No debe convertirse en lock ledger.
- No debe mezclar evidencia legal con control de reintentos.

No usar sólo locks:

- Ayudan en concurrencia.
- No resuelven replay tras timeout.
- No devuelven respuesta estable.
- No guardan referencias de la operación completada.
- No auditan reintentos del cliente.

### D) Columnas decididas
Columnas conceptuales para el futuro draft:

- `id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT`.
- `uuid CHAR(36) NOT NULL`.
- `idempotency_key_hash CHAR(64) NOT NULL`.
- `request_hash CHAR(64) NOT NULL`.
- `entity_type VARCHAR(64) NOT NULL`.
- `entity_id VARCHAR(64) NOT NULL`.
- `doctor_id VARCHAR(64) NULL`.
- `profile_id VARCHAR(64) NULL`.
- `user_id BIGINT UNSIGNED NOT NULL`.
- `actor_role VARCHAR(32) NULL`.
- `operation VARCHAR(96) NOT NULL`.
- `status VARCHAR(32) NOT NULL DEFAULT 'processing'`.
- `subscription_id CHAR(36) NULL`.
- `contract_acceptance_uuid CHAR(36) NULL`.
- `response_http_status SMALLINT UNSIGNED NULL`.
- `response_body_text TEXT NULL`.
- `locked_at DATETIME NULL`.
- `completed_at DATETIME NULL`.
- `expires_at DATETIME NOT NULL`.
- `source VARCHAR(128) NOT NULL DEFAULT 'mxmed_subscription_idempotency_v1'`.
- `notes TEXT NULL`.
- `created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP`.
- `updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`.
- `deleted_at DATETIME NULL`.

Tipos decididos:

- `BIGINT UNSIGNED` para ids técnicos y `user_id`.
- `CHAR(36)` para uuid y `subscription_id`.
- `CHAR(64)` para hashes `sha256`.
- `VARCHAR(64)` para entidad, doctor y profile.
- `VARCHAR(32)` para status y actor role.
- `VARCHAR(96)` para operación.
- `VARCHAR(128)` para source.
- `SMALLINT UNSIGNED` para HTTP status.
- `DATETIME` para fechas de negocio/control.
- `TIMESTAMP` para timestamps técnicos.
- `TEXT` para respuesta sanitizada opcional y notas.

### E) Columnas descartadas para v1
No incluir en v1:

- Payload completo.
- IP.
- User-agent.
- `payment_id`.
- `checkout_id`.
- `invoice_id`.
- Capacidades.
- Datos sensibles.
- Campos de UI.

Motivos:

- Evitar almacenar datos innecesarios.
- Mantener la tabla enfocada en el write contractual.
- Evitar mezclar pagos/checkout antes de su diseño propio.
- Reducir exposición de datos si una key o payload llega mal formado.

### F) Key cruda vs hash
Decisión:

- No usar key cruda como fuente principal.
- Guardar `idempotency_key_hash CHAR(64) NOT NULL`.
- Calcular hash `sha256` de la key normalizada.

Motivos:

- Evita índices largos.
- Reduce exposición si el cliente manda una key con datos indebidos.
- Mantiene unicidad estable.
- Evita depender de collation para comparar keys crudas.

Regla futura:

- Si se requiere depuración, evaluar un preview no sensible en microfase separada.
- No guardar la key cruda en el primer draft.

### G) Request hash
Columna decidida:

- `request_hash CHAR(64) NOT NULL`.

Reglas:

- Debe calcularse con `sha256` sobre request canonicalizado.
- Debe detectar misma key con payload distinto.
- No debe almacenar payload completo.

Debe incluir:

- Operación.
- Ruta conceptual.
- `entity_type`.
- `entity_id`.
- `doctor_id`.
- `user_id`.
- `actor_role`.
- `plan_code`.
- `billing_period`.
- `contract.version`.
- `contract.hash`.
- `contract.snapshot_url`.
- `acceptance.source`.

Debe excluir:

- Fechas de servidor.
- IP.
- User-agent.
- `subscription_id`.
- Campos prohibidos.
- Datos sensibles.

### H) Response storage
Fuente principal de replay:

- `subscription_id`.
- `contract_acceptance_uuid`.
- `response_http_status`.

Columna opcional:

- `response_body_text TEXT NULL`.

Decisiones:

- Preferir referencias + HTTP status.
- No usar `JSON` inicialmente.
- Usar `TEXT` si se necesita respuesta sanitizada.
- Evitar fragilidad MySQL/MariaDB con diferencias de tipo `JSON`.
- No guardar payload completo.
- No guardar evidencia legal completa.
- No guardar IP/user-agent.

Si se requiere replay exacto:

- Debe documentarse en una fase posterior.
- Debe guardarse sólo respuesta sanitizada.

### I) Estados
Estados conceptuales:

- `processing`.
- `completed`.
- `failed`.
- `expired`.
- `cancelled`.

Reglas:

- Sin `ENUM`.
- Sin `CHECK`.
- Validación por backend.
- `processing` como default.
- `completed` debe tener referencias si el write contractual completó.
- `failed` no debe dejar aceptación/suscripción huérfana.
- `expired` se maneja por TTL y job futuro.
- `cancelled` queda reservado para corrección administrativa/controlada.

### J) TTL y cleanup
Columna obligatoria:

- `expires_at DATETIME NOT NULL`.

TTL inicial:

- Local/dev: 24 horas.
- Producción/checkout futuro: 24 horas a 7 días, pendiente decisión comercial/técnica.

Reglas:

- Cleanup debe vivir en job futuro separado.
- No hay borrado físico en flujo normal.
- `deleted_at` queda para soft delete administrativo/controlado.
- Debe existir índice por `status, expires_at`.

### K) Unicidad
Uniques decididos para futuro draft:

- `uuid` unique.
- Unique de scope idempotente:
  - `idempotency_key_hash`.
  - `user_id`.
  - `entity_type`.
  - `entity_id`.
  - `operation`.

Motivos:

- Evita key repetida dentro del mismo scope.
- Acota replay a usuario, entidad y operación.
- Evita índices largos usando hash.
- Permite que una misma key enviada por accidente en otro scope no afecte al scope actual.

Reglas:

- No usar unique global sólo por key cruda.
- No usar key cruda larga en unique compuesto.
- No usar esta unique como sustituto de auth.

### L) Índices recomendados
Índices conceptuales para validar en SQL draft:

- `ux_sub_write_idem_uuid`.
- `ux_sub_write_idem_scope`.
- `idx_sub_write_idem_entity`.
- `idx_sub_write_idem_doctor`.
- `idx_sub_write_idem_user`.
- `idx_sub_write_idem_operation_status`.
- `idx_sub_write_idem_status_expires`.
- `idx_sub_write_idem_subscription`.
- `idx_sub_write_idem_acceptance`.
- `idx_sub_write_idem_deleted_at`.
- `idx_sub_write_idem_created_at`.

Aclaración:

- Los nombres exactos deben validarse en el SQL draft contra límites MySQL/MariaDB.
- La unique de scope debe usar `idempotency_key_hash` para controlar longitud.

### M) FKs y relaciones
Decisión:

- Sin FKs reales iniciales.

Motivos:

- Sigue el criterio usado en `subscription_contract_acceptances`.
- Mantiene compatibilidad inicial MySQL/MariaDB.
- Evita acoplar dominios antes de pagos/checkout.
- Las relaciones se validan por backend.

Relaciones conceptuales:

- `subscription_id CHAR(36) NULL` se llena al completar.
- `contract_acceptance_uuid CHAR(36) NULL` se llena al completar.
- `processing` puede tener referencias `NULL`.
- `completed` debe tener referencias si el endpoint creó datos.
- `failed` puede quedar sin referencias.

Reglas:

- No agregar columnas a `profile_subscriptions`.
- No agregar columnas a `subscription_contract_acceptances`.
- Mantener índices para consultas y auditoría futura.

### N) Concurrencia y riesgo residual
La tabla resuelve:

- Reintentos con la misma key.
- Replay tras timeout.
- Detección de estado `processing`.
- Respuesta estable basada en referencias.

La tabla no resuelve por sí sola:

- Dos requests concurrentes con keys distintas.
- Doble click si el frontend genera dos keys diferentes.
- Creación simultánea sin lock adicional por entidad.

Para producción/checkout conviene diagnosticar además:

- Entity lock.
- Unique activo por entidad.
- Advisory lock.
- Estrategia equivalente de exclusión por entidad.

Ese diagnóstico queda como microfase futura separada.

### O) Compatibilidad
Decisiones de compatibilidad:

- `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`.
- Tipos alineados con `profile_subscriptions`.
- Tipos alineados con `subscription_contract_acceptances`.
- `TEXT` preferido sobre `JSON` para respuesta opcional.
- Sin `ENUM`.
- Sin `CHECK`.
- Sin FKs reales iniciales.
- Estados y constraints se validan en backend.

### P) Relación futura
Frontend:

- UI write futura debe enviar `Idempotency-Key`.
- `p-suscripcion` sigue read-only hasta microfase explícita.

Pagos/checkout:

- No se conectan todavía.
- La tabla puede ampliarse en el futuro con referencias a checkout/payment intent.
- No incluir campos de pago en v1.
- No diseñar cobros en este storage.

Capacidades:

- No activar capacidades.
- No conectar `PublicProfilePlanCapabilities`.

Producción:

- Producción/checkout no debe avanzar sin idempotencia implementada.
- Producción/checkout también requiere decisión de lock/unique activo por entidad.

### Q) Siguiente microfase recomendada
Siguiente microfase recomendada:

- `DB-Suscripciones-ContractAcceptance-IdempotencySchemaDraft-01`.

Objetivo:

- Crear un SQL draft, no ejecutable todavía, para la tabla `subscription_write_idempotency_keys`, alineado con esta decisión.

Restricciones esperadas:

- Sólo draft SQL.
- No ejecutar SQL.
- No modificar DB.
- No conectar backend.
- No frontend.
- No pagos.
- No checkout.
- No capacidades.

### R) Límites de esta adenda
Esta adenda no ejecuta ni implementa:

- Backend.
- Frontend.
- SQL DDL.
- Migrations.
- Cambios de schema.
- Escrituras SQL manuales.
- POST contractual.
- QA con DB writes.
- Headers QA para write.
- Relajación de guards.
- Pagos.
- Checkout.
- Facturación.
- `PublicProfilePlanCapabilities`.
- Capacidades productivas.
- Perfil público.
- SEO productivo.
- Limpieza de datos.

---

## Adenda PP-Decisiones 57 — Cierre del draft SQL de idempotencia contractual

### A) Microfase cerrada
Se cerró la microfase:

- `DB-Suscripciones-ContractAcceptance-IdempotencySchemaDraft-01`.

Commit remoto/alineado:

- `d413a34 db(suscripciones): agrega draft de idempotencia contractual`.

Archivo creado:

- `modules/profiles/db/2026_06_22_create_subscription_write_idempotency_keys_draft.sql`.

### B) QA asociado
QA del draft:

- `QA-Suscripciones-ContractAcceptance-IdempotencySchemaDraft-PendingDiff-01`.
- Resultado: PASS.

QA post-push:

- `QA-Suscripciones-ContractAcceptance-IdempotencySchemaDraft-PostPush-01`.
- Resultado: PASS sin cambios.

Confirmaciones del QA:

- Rama limpia y alineada.
- Draft trackeado por Git.
- Draft incluido en `HEAD`.
- Commit esperado validado.
- Contenido del draft validado.
- Sin SQL ejecutado.
- Sin efectos colaterales.

### C) Alcance del draft
El draft define conceptualmente la tabla:

- `subscription_write_idempotency_keys`.

La tabla queda planteada para soportar en fases futuras:

- `Idempotency-Key`.
- `idempotency_key_hash`.
- `request_hash`.
- Scope por `user_id`, entidad y operación.
- Estados `processing`, `completed`, `failed`, `expired` y `cancelled`.
- Referencias a `subscription_id`.
- Referencias a `contract_acceptance_uuid`.
- `response_http_status`.
- `response_body_text` opcional.
- TTL mediante `expires_at`.
- Cleanup futuro.
- Soft delete mediante `deleted_at`.

### D) Decisiones respetadas
El draft respeta las decisiones ya cerradas:

- Tabla dedicada.
- No usar `profile_subscriptions`.
- No usar `subscription_contract_acceptances`.
- No usar sólo locks.
- No guardar payload completo.
- No guardar key cruda como fuente principal.
- No guardar IP.
- No guardar user-agent.
- No guardar payment/checkout/invoice.
- No guardar capacidades.
- No guardar datos sensibles.
- Response opcional como `TEXT`, no `JSON` inicial.
- Sin `ENUM`.
- Sin `CHECK`.
- Sin FKs reales iniciales.
- Engine/collation: `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`.

### E) Estado explícito después del cierre
Estado actual:

- SQL draft creado y versionado.
- SQL draft validado.
- SQL draft pusheado.
- No SQL ejecutado.
- No DB/schema modificado.
- No tabla real creada.
- No SQL ejecutable final creado.
- No backend modificado.
- No frontend modificado.
- No `Idempotency-Key` conectado.
- No pagos.
- No checkout.
- No facturación.
- No capacidades.
- No `PublicProfilePlanCapabilities`.
- No perfil público.
- No SEO.

### F) Riesgo residual
Riesgo residual:

- La tabla de idempotencia futura protegerá reintentos con la misma key.
- La tabla no resuelve por sí sola dos requests concurrentes con keys distintas.
- Antes de producción/checkout sigue pendiente una microfase de diagnóstico de lock/unique activo por entidad o estrategia equivalente.

### G) Siguiente paso recomendado
Siguiente microfase recomendada:

- `DB-Suscripciones-ContractAcceptance-IdempotencyExecutableSql-Readiness-01`.

Objetivo:

- Validar readiness para convertir el draft en SQL ejecutable, sin ejecutar SQL todavía.

Restricciones esperadas:

- No ejecutar SQL.
- No modificar DB/schema.
- No conectar backend.
- No frontend.
- No pagos.
- No checkout.
- No capacidades.

Microfase alternativa posterior, antes de producción/checkout:

- `DB/DIAG-Suscripciones-ContractAcceptance-EntityLock-ActiveSubscriptionConcurrency-01`.

Objetivo:

- Diagnosticar estrategia de lock/unique activo por entidad para cubrir concurrencia con keys distintas.

### H) Límites de esta adenda
Esta adenda no ejecuta ni implementa:

- Backend.
- Frontend.
- SQL DDL.
- Migrations.
- Cambios de schema.
- Escrituras SQL manuales.
- POST contractual.
- QA con DB writes.
- Headers QA para write.
- Relajación de guards.
- Pagos.
- Checkout.
- Facturación.
- `PublicProfilePlanCapabilities`.
- Capacidades productivas.
- Perfil público.
- SEO productivo.
- Limpieza de datos.

---

## Adenda PP-Decisiones 58 — Cierre consolidado del bloque de idempotencia contractual

### A) Alcance cerrado
El bloque de idempotencia contractual de suscripciones ya pasó de diseño/documentación a un estado tangible local/dev.

Quedan cerrados y validados en local/dev:

- SQL ejecutable versionado.
- Tabla real local/dev `subscription_write_idempotency_keys`.
- Backend mínimo usando `Idempotency-Key`.
- Fixture alterno dev-only/local-only para QA `201`.
- QA funcional de conflicto activo con doctor 1.
- QA funcional exitosa `201` con doctor 2.
- Replay con la misma key sin duplicados.
- Bloque DEV/local visible en `p-suscripcion`.
- QA visual post-push del panel.

### B) Tabla real local/dev
Tabla creada:

- `subscription_write_idempotency_keys`.

Estado local/dev:

- DB: `mxmed`.
- Engine/collation: `InnoDB / utf8mb4_unicode_ci`.
- Columnas, uniques e índices esperados validados.
- Sin FKs reales iniciales.
- Sin key cruda.
- Sin payload completo.
- Sin IP/user-agent.
- Sin pagos.
- Sin checkout.
- Sin invoice.
- Sin capacidades productivas.

Campos centrales:

- `idempotency_key_hash`.
- `request_hash`.
- `status`.
- `response_http_status`.
- `subscription_id`.
- `contract_acceptance_uuid`.
- `expires_at`.

SQL ejecutable versionado:

- Commit: `e681ee3 db(suscripciones): agrega SQL ejecutable de idempotencia contractual`.
- Archivo: `modules/profiles/db/2026_06_22_create_subscription_write_idempotency_keys.sql`.

### C) Backend mínimo
Commit:

- `5407bfa feat(suscripciones): agrega idempotencia minima al write contractual`.

Archivos:

- `api/subscriptions/index.php`.
- `modules/subscriptions/repositories/SubscriptionWriteIdempotencyRepository.php`.
- `modules/subscriptions/services/SubscriptionWriteIdempotencyService.php`.

Contrato implementado:

- Header: `Idempotency-Key`.
- Operation: `subscriptions.create_with_contract_acceptance`.

Comportamientos validados:

- Key inválida: `422 idempotency_key_invalid`.
- Misma key en `processing`: `409 request_already_processing`.
- Misma key con payload distinto: `409 idempotency_key_reused_with_different_payload`.
- Replay `completed`: respuesta estable sin duplicados.
- Estados `failed`, `expired` o `cancelled`: key no reutilizable.

Alcance preservado:

- No relaja auth.
- `local_dev_open` sigue sin autorizar writes.
- Headers QA siguen sin autorizar writes.
- Sólo `session_scope` médico principal llega a negocio.
- No conecta pagos, checkout, facturación ni capacidades.

### D) QA funcional
#### Doctor 1: conflicto activo
Doctor 1 ya tenía suscripción activa `standard / active`.

Validaciones cerradas:

- Sin key: `409 active_subscription_exists`.
- Key inválida: `422 idempotency_key_invalid`.
- Key válida con conflicto activo: fila idempotencia `failed`, HTTP `409`.
- Replay de key `failed`: no reutilizable.
- Misma key con payload distinto: conflicto de idempotencia.
- Headers QA: `403`.
- Sin nueva suscripción.
- Sin nueva aceptación contractual.

Suscripción original de doctor 1:

- `subscription_id=9700c0d5-6dc5-490b-bdb4-766dee490590`.
- Estado: `standard / active`.

#### Doctor 2: éxito 201
Se preparó un fixture local/dev alterno:

- Commit: `f207b0a test(suscripciones): agrega fixture alterno para idempotencia`.
- Doctor fixture: `doctor_id=2`.
- Session scope: doctor 2.
- Sin tocar doctor 1.

QA exitosa:

- POST contractual con key válida: `201`.
- `subscription_id=e7f9c04f-4145-409e-98a9-a4f7b6714b14`.
- `contract_acceptance_uuid=6bffe5af-ecd4-47f4-90a7-fa5c24873411`.
- Fila de idempotencia: `completed`.
- `response_http_status=201`.
- Replay misma key/mismo payload: `idempotent_replay=true`, sin duplicados.
- Misma key con payload distinto: `409`.
- Doctor 1 quedó intacto.

### E) Frontend DEV/local
Commit:

- `1a51abe feat(suscripciones): agrega contratacion dev con idempotencia`.

Archivos:

- `index.html`.
- `assets/js/app.js`.

Panel:

- `p-suscripcion`.

Bloque visible:

- `Contratación DEV controlada`.

Botón:

- `Contratar Standard DEV`.

Comportamiento:

- Visible sólo en local/dev.
- Genera `Idempotency-Key` con prefijo `mxmed-dev-subscription-`.
- Payload fijo `standard / annual`.
- Usa `credentials: same-origin`.
- No usa headers QA.
- Reconsulta current después de respuesta controlada.
- Deshabilita el botón si ya hay suscripción activa.
- No es checkout.
- No es flujo productivo.
- No activa capacidades.

QA visual post-push:

- Microfase: `QA-Suscripciones-PanelDev-WriteContractual-VisualPostPush-01`.
- Navegador: Safari.
- Servidor local temporal: `127.0.0.1:8099`.
- Bloque visible confirmado.
- Botón visible confirmado.
- Botón deshabilitado por suscripción activa en el contexto observado.
- No se ejecutó POST contractual.
- No se creó suscripción.
- No se creó aceptación.
- No se creó fila de idempotencia.

Conteos DB sin cambios durante QA visual:

- `profile_subscriptions`: `2 / 2`.
- `subscription_contract_acceptances`: `2 / 2`.
- `subscription_write_idempotency_keys`: `2 / 2`.

### F) Estado final del bloque
Estado DB local/dev al cierre:

- `profile_subscriptions=2`.
- `subscription_contract_acceptances=2`.
- `subscription_write_idempotency_keys=2`.

Estado funcional:

- Doctor 1: `standard / active`, suscripción original intacta.
- Doctor 2: fixture local/dev, `standard / active` después del QA `201`.
- Backend idempotente mínimo operativo para misma key.
- Panel `p-suscripcion` tiene acción DEV/local controlada.

Alcance no conectado:

- No pagos.
- No checkout.
- No facturación.
- No activación de capacidades productivas.
- No `PublicProfilePlanCapabilities`.
- No perfil público modificado.
- No SEO modificado.
- `p-suscripcion` sigue en modo DEV/local controlado, no productivo.

### G) Riesgos y residuos pendientes
Pendientes antes de producción/checkout:

- La idempotencia actual cubre reintentos con la misma key, no dos requests concurrentes con keys distintas.
- Falta estrategia de entity lock / unique activo por entidad o equivalente.
- Falta convertir el flujo DEV/local en flujo productivo con pagos/checkout.
- Falta UX real de selección y confirmación contractual.
- Falta cleanup/TTL job para `subscription_write_idempotency_keys`.
- Falta política productiva de duración de TTL.
- Falta hardening productivo.
- El fixture alterno es dev-only/local-only y no debe usarse en producción.

### H) Siguiente bloque recomendado
Siguiente bloque recomendado:

- `BE/DIAG-Suscripciones-ContractAcceptance-EntityLock-ActiveSubscriptionConcurrency-01`.

Objetivo:

- Diagnosticar estrategia para evitar doble write con keys distintas concurrentes antes de checkout/productivo.

Alternativa si se prioriza UI antes del diagnóstico de concurrencia:

- `FE/UX-Suscripciones-PanelDev-ContractFlow-Polish-01`.

Objetivo:

- Pulir visualmente el flujo DEV/local de contratación contractual sin pagos.

### I) Límites de esta adenda
Esta adenda no ejecuta ni implementa:

- Backend.
- Frontend.
- SQL DDL.
- Cambios de schema.
- Escrituras SQL manuales.
- POST contractual.
- Pagos.
- Checkout.
- Facturación.
- `PublicProfilePlanCapabilities`.
- Capacidades productivas.
- Perfil público.
- SEO productivo.
- Limpieza de datos.

---

## Adenda PP-Decisiones 59 — Decisión de lock por entidad para concurrencia contractual

### A) Problema
La implementación actual de `Idempotency-Key` protege reintentos con la misma key, pero no protege por sí sola dos requests concurrentes con keys distintas para la misma entidad.

Riesgo específico:

- Dos keys distintas pueden crear dos registros `processing` distintos.
- Ambas requests podrían llegar al servicio contractual.
- La validación de "no existe suscripción activa" se revalida dentro de la transacción, pero no existe lock explícito por entidad.
- No existe unique activo por entidad.
- No existe `SELECT ... FOR UPDATE`.
- No existe `GET_LOCK`.
- No existe lock table.

### B) Hallazgos del diagnóstico
Microfase diagnóstica:

- `BE/DIAG-Suscripciones-ContractAcceptance-EntityLock-ActiveSubscriptionConcurrency-01`.

Resultado:

- PASS sin cambios.

Hallazgos:

- `profile_subscriptions` tiene unique sólo por `subscription_id`.
- `profile_subscriptions` tiene índices por entidad/status/fechas, pero no unique activo por entidad.
- `subscription_write_idempotency_keys` tiene unique por `idempotency_key_hash,user_id,entity_type,entity_id,operation`.
- Ese unique sólo protege la misma key en el mismo scope.
- La validación de activa vive en `CreateSubscriptionWithAcceptanceService::activeSubscriptionExists()`.
- La validación de activa ocurre antes y dentro de la transacción.
- La transacción actual cubre aceptación contractual + `profile_subscriptions`.
- Falta lock por entidad para serializar keys distintas.

### C) Opciones evaluadas
#### 1. Advisory lock `GET_LOCK`
Nombre conceptual:

- `mxmed:subscriptions:{entity_type}:{entity_id}:create`.

Reglas sugeridas:

- Timeout corto.
- `RELEASE_LOCK` en `finally`.
- Revalidar suscripción activa dentro del lock.
- No requiere schema nuevo.
- Compatible como v1 local/dev antes de checkout.

Riesgos:

- Depende de conexión.
- Requiere release seguro.
- Requiere manejo explícito de timeout.
- Es menos auditable que una tabla dedicada.

#### 2. Tabla dedicada de entity locks
Ventajas:

- Más auditable.
- Permite estado, TTL, expiración y limpieza.

Costos:

- Requiere nuevo schema.
- Requiere TTL/cleanup.
- Es más pesada para esta fase.

Decisión:

- Reservarla si `GET_LOCK` no basta o si producción/checkout exige auditoría explícita del lock.

#### 3. Unique activo por entidad
Ventajas:

- Garantía DB fuerte.

Riesgos:

- Es invasivo en MySQL/MariaDB por falta de unique parcial simple.
- Requeriría columna auxiliar/generada o rediseño del lifecycle.
- Puede afectar histórico, cancelación, gracia, vencimiento y renovación.

Decisión:

- No recomendado como siguiente paso inmediato.

#### 4. `SELECT ... FOR UPDATE`
Limitación:

- Es insuficiente como solución única cuando no existe fila activa.
- Para entidades sin suscripción no hay fila segura que bloquear.

Decisión:

- No usarlo como única protección de concurrencia.

#### 5. Combinación idempotency + entity lock
Estrategia recomendada:

- La idempotencia maneja replay por key.
- El lock por entidad serializa keys distintas.

### D) Decisión
Decisión técnica:

- Usar en v1 un advisory lock MySQL/MariaDB `GET_LOCK` por entidad/operación, combinado con la idempotencia actual.

Flujo conceptual futuro:

1. Validar auth/write guard.
2. Validar payload.
3. Validar/registrar `Idempotency-Key`.
4. Si idempotencia decide `proceed`, tomar lock por entidad:
   `mxmed:subscriptions:{entity_type}:{entity_id}:create`.
5. Si no se obtiene lock, responder preferentemente `409 subscription_write_lock_timeout`.
6. Dentro del lock:
   - revalidar suscripción activa;
   - ejecutar transacción contractual;
   - completar idempotencia.
7. Liberar lock en `finally`.
8. No relajar auth.
9. No usar headers QA para write.
10. No conectar pagos, checkout, facturación ni capacidades.

Código de respuesta preferido para timeout:

- HTTP `409`.
- Error: `subscription_write_lock_timeout`.

### E) Archivos futuros a tocar
Implementación futura probable:

- `api/subscriptions/index.php`.
- Posible nuevo servicio: `modules/subscriptions/services/SubscriptionEntityWriteLockService.php`.
- Opcional helper/repositorio sólo si la implementación lo requiere.

Alcance v1 esperado:

- No requiere SQL/schema.
- No requiere frontend.
- No requiere pagos.
- No requiere checkout.
- No requiere capacidades.

### F) QA futuro recomendado
QA mínimo futuro:

- Dos requests concurrentes con keys distintas para el mismo doctor sin suscripción activa.

Resultado esperado:

- Una request crea `201`.
- La otra no duplica.
- La segunda puede responder `409 active_subscription_exists` después del lock o `409 subscription_write_lock_timeout`, según timing.

Confirmaciones requeridas:

- Una sola fila nueva en `profile_subscriptions`.
- Una sola aceptación contractual.
- Idempotencia coherente.
- Lock liberado.
- Sin deadlocks.
- Headers QA siguen bloqueados para write.
- `local_dev_open` sigue bloqueado para write.
- Sin pagos.
- Sin checkout.
- Sin capacidades productivas.

### G) Estado explícito
Esta adenda sólo documenta decisión.

No se implementa:

- Lock por entidad.
- `GET_LOCK`.
- SQL DDL.
- Cambios de schema.
- Backend.
- Frontend.
- Checkout/productivo.

No se ejecuta:

- SQL.
- POST contractual.
- Escrituras manuales.

### H) Siguiente microfase recomendada
Siguiente microfase recomendada:

- `BE-Suscripciones-ContractAcceptance-EntityLock-AdvisoryLock-01`.

Objetivo:

- Implementar advisory lock `GET_LOCK` por entidad/operación alrededor del write contractual cuando idempotencia decide `proceed`, sin frontend ni pagos.

Restricciones esperadas:

- No modificar frontend.
- No modificar SQL/schema.
- No conectar pagos.
- No conectar checkout.
- No activar capacidades.
- Mantener bloqueados headers QA y `local_dev_open` para write.

### I) Límites de esta adenda
Esta adenda no modifica:

- Backend.
- Frontend.
- SQL.
- DB/schema.
- Perfil público.
- SEO.

Esta adenda no conecta:

- Pagos.
- Checkout.
- Facturación.
- `PublicProfilePlanCapabilities`.
- Capacidades productivas.

---

## Adenda PP-Decisiones 60 — Cierre del lock de concurrencia contractual

### A) Alcance cerrado
Se cerró la implementación del advisory lock por entidad/operación para el write contractual de suscripciones.

Queda implementado:

- Advisory lock MySQL/MariaDB `GET_LOCK` / `RELEASE_LOCK`.
- Integración alrededor del write contractual.
- Combinación con `Idempotency-Key`.
- Protección v1 sin schema nuevo.
- Sin frontend.
- Sin pagos.
- Sin checkout.
- Sin facturación.
- Sin capacidades productivas.

Commit de implementación:

- `d2bd437 feat(suscripciones): agrega lock de concurrencia contractual`.

Archivos implementados:

- `api/subscriptions/index.php`.
- `modules/subscriptions/services/SubscriptionEntityWriteLockService.php`.

### B) Comportamiento técnico
Servicio:

- `SubscriptionEntityWriteLockService`.

Lock:

- `GET_LOCK`.

Release:

- `RELEASE_LOCK`.

Nombre determinístico:

- `mxmed:subscriptions:{entity_type}:{entity_id}:create`.

Timeout:

- 2 segundos.

Error de timeout:

- HTTP `409`.
- Error: `subscription_write_lock_timeout`.
- Mensaje: `subscription write already in progress for this entity`.

Liberación:

- En `finally`.

Revalidación:

- Se conserva dentro de `CreateSubscriptionWithAcceptanceService::create()`.
- La validación de suscripción activa se ejecuta dentro del lock antes de crear aceptación/suscripción.

Auth preservada:

- No se relajó auth.
- `local_dev_open` sigue sin autorizar writes.
- Headers QA siguen sin autorizar writes.
- Sólo `session_scope` médico principal llega a negocio.

### C) QA validado
#### Entidad activa
QA controlado con doctor activo:

- POST con sesión real y key nueva.
- Resultado: `409 active_subscription_exists`.
- Se creó una fila idempotencia `failed` por el `409`.
- Lock liberado: `IS_FREE_LOCK(...) = 1`.
- No se creó nueva suscripción.
- No se creó nueva aceptación.

QA con headers QA sin sesión:

- Resultado: `403 forbidden`.
- Headers QA siguen sin autorizar write.
- No se intentó write contractual.
- No se creó suscripción.
- No se creó aceptación.

#### Concurrencia real con doctor 3
Microfase:

- `QA-Suscripciones-ContractAcceptance-EntityLock-ConcurrentDifferentKeys-Doctor3-01`.

Doctor fixture:

- `doctor_id=3`.
- `display_name=QA Concurrency Doctor`.
- Current previo: `free_default`.

Requests concurrentes con keys distintas:

- Request A:
  - HTTP `201`.
  - `subscription_id=92b2887b-241d-4d16-9b41-196fd4aff3a8`.
  - `contract_acceptance_uuid=184f2880-24b6-43cc-8288-53bd548f67af`.
- Request B:
  - HTTP `409`.
  - Error: `active_subscription_exists`.

Resultado:

- No hubo duplicados.
- Una sola suscripción nueva.
- Una sola aceptación contractual nueva.
- Dos filas de idempotencia para doctor 3:
  - una `completed`, HTTP `201`, con referencias;
  - una `failed`, HTTP `409`, sin referencias.
- Lock liberado: `IS_FREE_LOCK('mxmed:subscriptions:doctor:3:create') = 1`.
- Doctor 1 siguió `standard / active`.
- Doctor 2 siguió `standard / active`.

### D) Estado local/dev
Estado DB local/dev después del QA concurrente:

- `profile_subscriptions=3`.
- `subscription_contract_acceptances=3`.
- `subscription_write_idempotency_keys=5`.

Estado por doctor:

- Doctor 1: `standard / active`.
- Doctor 2: `standard / active`.
- Doctor 3: `standard / active` tras QA concurrente.

Estos datos son estado local/dev derivado de QA. No representan producción.

### E) Estado funcional del bloque
El write contractual ya cuenta con:

- Protección por key:
  - replay misma key;
  - payload distinto con misma key bloqueado.
- Protección por entidad:
  - serialización de requests concurrentes con keys distintas.
- Evidencia legal:
  - aceptación contractual en `subscription_contract_acceptances`.
- Snapshot operativo:
  - `profile_subscriptions`.
- Ledger de idempotencia:
  - `subscription_write_idempotency_keys`.

El flujo sigue sin conectar:

- Pagos.
- Checkout.
- Facturación.
- Capacidades productivas.
- `PublicProfilePlanCapabilities`.

### F) Pendientes
Sigue pendiente:

- Checkout/pagos productivos.
- Facturación.
- Activación real de capacidades.
- Conexión con `PublicProfilePlanCapabilities`.
- UX contractual productiva.
- Cleanup/TTL job de idempotencia.
- Política productiva de TTL.
- Hardening adicional antes de producción.
- Definir política para retirar o mantener fixtures dev-only/local-only.

### G) Siguiente bloque recomendado
Siguiente bloque recomendado:

- `FE/UX-Suscripciones-PanelDev-ContractFlow-Polish-01`.

Objetivo:

- Pulir el flujo visual DEV/local de contratación contractual y preparar transición futura a UX productiva, sin pagos todavía.

Alternativa técnica:

- `BE/SPEC-Suscripciones-Checkout-Readiness-01`.

Objetivo:

- Diagnosticar prerequisites para checkout/pagos sin implementarlos.

### H) Límites de esta adenda
Esta adenda no ejecuta ni implementa:

- Backend.
- Frontend.
- SQL DDL.
- Cambios de schema.
- Escrituras SQL manuales.
- POST contractual.
- Pagos.
- Checkout.
- Facturación.
- `PublicProfilePlanCapabilities`.
- Capacidades productivas.
- Perfil público.
- SEO productivo.
- Limpieza de datos.

---

## Adenda PP-Decisiones 61 — Decisión de flujo checkout-first para suscripciones productivas

### A) Problema
El flujo contractual DEV/local actual ya crea evidencia contractual y una fila operativa en `profile_subscriptions`.

Ese flujo es útil para validar localmente:

- Aceptación contractual.
- Write contractual controlado.
- `Idempotency-Key`.
- Replay sin duplicados.
- Advisory lock por entidad.
- QA del panel `p-suscripcion`.

Pero no debe usarse como checkout productivo porque activa `standard active` antes de que exista pago confirmado.

En producción debe existir una separación explícita entre:

- Intención de contratar.
- Aceptación contractual.
- Checkout.
- Pago confirmado.
- Activación de suscripción.
- Capacidades.
- Facturación.

### B) Estado actual validado
Ya existen y fueron validados en DEV/local:

- `subscription_contract_acceptances`.
- `subscription_write_idempotency_keys`.
- Backend con `Idempotency-Key`.
- Advisory lock MySQL/MariaDB `GET_LOCK` por entidad/operación.
- Replay sin duplicados.
- QA concurrente con keys distintas sin duplicados.
- Panel `p-suscripcion` DEV/local con acción de contratación controlada.

Estado aproximado de DB local/dev después de QA:

- `profile_subscriptions=3`.
- `subscription_contract_acceptances=3`.
- `subscription_write_idempotency_keys=5`.

Este estado sigue sin conectar:

- Pagos.
- Checkout.
- Facturación.
- Capacidades productivas.
- `PublicProfilePlanCapabilities`.

### C) Decisión
Adoptar modelo `checkout-first` para el flujo productivo de suscripciones.

El endpoint contractual actual queda como base DEV/local/manual validada y no se convierte directamente en endpoint productivo de cobro.

El flujo productivo futuro debe usar un endpoint separado:

- `POST /api/subscriptions/index.php/entities/{entity_type}/{entity_id}/checkout-intents`.

El webhook futuro debe quedar separado:

- `POST /api/subscriptions/index.php/payments/webhooks/{provider}`.

La activación de suscripción debe hacerse mediante un servicio interno no público después de pago confirmado.

### D) Separación conceptual
#### 1. Aceptación contractual
La aceptación contractual es la evidencia legal del contrato y del plan.

Debe ser:

- Trazable.
- Relacionable con el checkout intent.
- No duplicada.
- Consultable para auditoría.

Queda pendiente decidir si se registra como `pending_payment` antes de pago o si se registra/ratifica al confirmar pago.

#### 2. Checkout intent
El checkout intent representa la intención de pago.

Debe modelar, como mínimo:

- `uuid` propio.
- `entity_type`.
- `entity_id`.
- `doctor_id`.
- `user_id`.
- `plan_code`.
- `billing_period`.
- `amount`.
- `currency`.
- `status`.
- `expires_at`.
- Relación con contrato o aceptación contractual.
- Relación con idempotencia.

No activa suscripción por sí solo.

#### 3. Payment intent / provider
El payment intent representa la relación con el proveedor de pago futuro.

Debe modelar:

- Proveedor.
- `provider_payment_id`.
- `provider_status`.
- Payload mínimo o sanitizado.
- Timestamps de creación, actualización y confirmación.

No debe guardar:

- PAN.
- CVV.
- Datos sensibles de tarjeta.
- Payload completo si no es necesario.

#### 4. Webhook
El webhook debe:

- Validar firma.
- Ser idempotente.
- Mapear `provider_payment_id` al checkout intent.
- Manejar retries del proveedor.
- No duplicar activación.
- Registrar eventos útiles para auditoría operativa.

#### 5. Activación interna
La activación interna debe:

- Crear o activar `profile_subscriptions` sólo después de pago confirmado.
- Usar `Idempotency-Key`, lock por entidad o mecanismo equivalente.
- Evitar doble suscripción activa.
- Relacionar `subscription_id` con aceptación, checkout y pago.
- Resolver qué hacer si el pago llega tarde o ya existe una suscripción activa.

#### 6. Capacidades
Las capacidades quedan en fase posterior.

No se activan en el checkout inicial.

`PublicProfilePlanCapabilities` no debe conectarse hasta tener estado contractual y de pago confiable.

#### 7. Facturación
La facturación es un flujo separado.

Requiere:

- Datos fiscales.
- Modelo propio de invoices/CFDI/recibos.
- Política de emisión.
- Conciliación con pago.

No se mezcla con el checkout inicial.

### E) Opciones evaluadas
#### 1. Mantener write actual como productivo
No recomendado.

Riesgo principal:

- Activa suscripción antes de pago confirmado.

#### 2. Checkout-first
Recomendado.

Ventaja:

- Separa intención, pago confirmado y activación.
- Permite que el read-model sólo suba a plan pagado después de confirmación.

Riesgo:

- Requiere schema y endpoints nuevos.

#### 3. Acceptance pending + activation
Compatible con checkout-first.

Requiere decidir:

- Estados.
- Schema.
- Si la aceptación se guarda antes de pago, al confirmar pago o en ambos momentos con relación explícita.

#### 4. Endpoint productivo separado
Recomendado.

Motivo:

- Evita contaminar el endpoint DEV/local ya validado.
- Permite diseñar checkout, webhook y activación con contratos propios.

### F) Gaps documentados
Antes de implementar checkout faltan decisiones y diseño para:

- Proveedor de pago.
- Fuente de precio.
- `amount`.
- `currency`.
- Schema de checkout intents.
- Schema de payment intents/events.
- Estados de checkout.
- Expiración y cancelación de intents.
- Relación con aceptación contractual.
- Webhook.
- Verificación de firma.
- Idempotencia de webhook.
- Activación interna post-pago.
- Pago confirmado tardío.
- Caso donde ya existe suscripción activa.
- Facturación.
- Activación de capacidades.
- Limpieza de intents expirados.

### G) Restricciones
Esta decisión no implementa:

- Checkout.
- Pagos.
- Webhooks.
- Adapter de proveedor.
- Schema nuevo.
- Backend nuevo.
- Frontend nuevo.
- Facturación.
- Capacidades.
- `PublicProfilePlanCapabilities`.
- Perfil público.
- SEO.

### H) Siguiente microfase recomendada
Siguiente microfase recomendada:

- `DB/DIAG-Suscripciones-CheckoutIntent-StorageReadiness-01`.

Objetivo:

- Diagnosticar el storage necesario para checkout intents, payment intents/events y relación con aceptación contractual, sin crear SQL todavía.

---

## Adenda PP-Decisiones 62 — Decisión de storage para checkout intents y pagos de suscripciones

### A) Problema de storage
El flujo `checkout-first` requiere storage propio.

Las tablas actuales no modelan checkout ni pagos:

- `profile_subscriptions` es snapshot operativo/read-model y no debe registrar intentos de pago.
- `subscription_contract_acceptances` es evidencia legal y no debe convertirse en ledger de provider events.
- `subscription_write_idempotency_keys` protege writes contractuales, pero no debe ser el único ledger de eventos de proveedor.
- `subscription_plans` no tiene hoy `amount`, `currency` ni precio persistido.

Por lo tanto, checkout, payment intents y payment events deben tener storage dedicado antes de cualquier SQL ejecutable o backend productivo.

### B) Decisión de tablas futuras
#### 1. `subscription_checkout_intents`
Propósito:

- Registrar intención de checkout antes del pago.
- No activar suscripción por sí sola.
- Guardar snapshot contractual y comercial del intento.

Campos conceptuales:

- `id`.
- `uuid`.
- `entity_type`.
- `entity_id`.
- `doctor_id`.
- `profile_id`.
- `user_id`.
- `actor_role`.
- `plan_code`.
- `billing_period`.
- `amount_cents`.
- `currency`.
- `price_source`.
- `price_version`.
- `status`.
- `contract_version`.
- `contract_hash`.
- `contract_snapshot_url`.
- `contract_acceptance_uuid` nullable.
- `idempotency_key_hash` nullable.
- `request_hash` nullable.
- `provider` nullable.
- `provider_checkout_id` nullable.
- `provider_payment_id` nullable.
- `checkout_url` nullable.
- `expires_at`.
- `completed_at`.
- `cancelled_at`.
- `activated_at`.
- `subscription_id` nullable.
- `source`.
- `notes`.
- `created_at`.
- `updated_at`.
- `deleted_at`.

Estados conceptuales:

- `draft`.
- `pending_contract`.
- `pending_payment`.
- `payment_processing`.
- `paid`.
- `failed`.
- `expired`.
- `cancelled`.
- `activated`.

#### 2. `subscription_payment_intents`
Propósito:

- Modelar el intento de pago vivo con proveedor.
- Guardar estado normalizado del pago.
- Separar estado de pago del checkout intent general.

Campos conceptuales:

- `id`.
- `uuid`.
- `checkout_intent_uuid`.
- `provider`.
- `provider_payment_id`.
- `provider_checkout_id` nullable.
- `normalized_status`.
- `provider_status`.
- `amount_cents`.
- `currency`.
- `created_at_provider` nullable.
- `expires_at`.
- `paid_at` nullable.
- `failed_at` nullable.
- `cancelled_at` nullable.
- `source`.
- `notes`.
- `created_at`.
- `updated_at`.
- `deleted_at`.

Estados conceptuales:

- `created`.
- `requires_action`.
- `processing`.
- `paid`.
- `failed`.
- `cancelled`.
- `expired`.
- `refunded`.
- `disputed`.

#### 3. `subscription_payment_events`
Propósito:

- Ledger idempotente de eventos/webhooks del proveedor.
- Evitar procesar dos veces el mismo webhook.
- Guardar evidencia técnica mínima y sanitizada.

Campos conceptuales:

- `id`.
- `uuid`.
- `checkout_intent_uuid` nullable.
- `payment_intent_uuid` nullable.
- `provider`.
- `provider_event_id`.
- `provider_payment_id` nullable.
- `event_type`.
- `provider_status` nullable.
- `normalized_status` nullable.
- `amount_cents` nullable.
- `currency` nullable.
- `event_hash`.
- `signature_validated_at` nullable.
- `received_at`.
- `processed_at` nullable.
- `processing_status`.
- `error_message` nullable.
- `payload_text_sanitized` nullable.
- `source`.
- `notes`.
- `created_at`.
- `updated_at`.
- `deleted_at`.

Estados conceptuales:

- `received`.
- `ignored`.
- `processing`.
- `processed`.
- `failed`.
- `duplicate`.

### C) Tabla que no se crea en v1
No se recomienda crear `subscription_activation_log` en v1.

La activación debe quedar reflejada en:

- `profile_subscriptions`.
- Relación desde checkout/payment hacia `subscription_id`.
- `subscription_payment_events` como ledger técnico de eventos del proveedor.

Si auditoría futura exige una bitácora explícita de activación, se evaluará una tabla adicional.

### D) Relación con aceptación contractual
El checkout intent debe poder relacionarse con `contract_acceptance_uuid`.

Queda pendiente cerrar una decisión antes del SQL draft final:

- Crear aceptación como `accepted_pending_payment`.
- O crear/ratificar aceptación al confirmar pago.

En cualquier caso:

- No debe duplicarse la aceptación contractual.
- El contrato, hash y snapshot deben quedar congelados para el checkout intent.
- `subscription_contract_acceptances` debe seguir siendo evidencia legal, no ledger de provider events.

### E) Relación con activación
`subscription_id` debe generarse al activar después del pago confirmado, no al crear checkout.

`profile_subscriptions` debe crearse o activarse sólo con pago confirmado.

La activación debe ser interna, no endpoint público directo.

Debe usar lock por entidad:

- `mxmed:subscriptions:{entity_type}:{entity_id}:activate`.

También debe:

- Revalidar que no exista suscripción activa.
- Ser idempotente frente a webhooks duplicados.
- Relacionar la activación con checkout, payment y aceptación contractual.

### F) Relación con idempotencia
La creación de checkout intent puede usar `subscription_write_idempotency_keys` con operación nueva:

- `subscriptions.checkout_intent.create`.

La activación post-pago puede usar operación interna:

- `subscriptions.activate_after_payment`.

Los webhooks deben tener idempotencia propia en `subscription_payment_events` usando:

- Unique conceptual `provider + provider_event_id`.
- Fallback `event_hash`.

No se debe reutilizar sin más `subscription_write_idempotency_keys` como ledger de eventos de proveedor.

### G) Pricing snapshot
`subscription_plans` no tiene `amount` ni `currency` hoy.

Antes de checkout real se debe decidir la fuente de precio:

- Columnas futuras en `subscription_plans`.
- Tabla comercial de precios.
- Catálogo de proveedor.
- Configuración controlada por backend.

El checkout intent debe guardar snapshot:

- `amount_cents`.
- `currency`.
- `price_source`.
- `price_version`.

Motivo:

- Auditoría.
- Evitar cambios retroactivos si el catálogo cambia.
- Conciliación con proveedor.
- Reproducibilidad del contrato comercial.

### H) Seguridad
Reglas de seguridad para el storage futuro:

- No guardar PAN.
- No guardar CVV.
- No guardar payload completo sensible.
- Sanitizar payload si se conserva.
- Validar firma de webhook.
- No confiar en `amount` enviado por cliente.
- Recalcular `amount` server-side.
- Validar plan contractable.
- Validar entidad, sesión y ownership.
- Preparar rate limiting futuro.
- Mantener logs mínimos.
- Separar frontend checkout de activación interna.

### I) Unicidad e índices conceptuales
Para `subscription_checkout_intents`:

- `uuid` unique.
- Índice por `entity_type, entity_id, status`.
- Índice por `user_id, status`.
- Índice o unique por `provider_checkout_id` si aplica.
- Índice o unique por `provider_payment_id` si aplica.
- Índice por `status, expires_at`.
- Índice por `subscription_id`.
- Índice por `contract_acceptance_uuid`.
- Índice por `created_at`.

Para `subscription_payment_intents`:

- `uuid` unique.
- Índice por `checkout_intent_uuid`.
- Unique por `provider, provider_payment_id`.
- Índice por `normalized_status`.
- Índice por `provider_status`.
- Índice por `created_at`.

Para `subscription_payment_events`:

- `uuid` unique.
- Unique por `provider, provider_event_id`.
- Unique o índice por `event_hash`, según decisión final.
- Índice por `provider_payment_id`.
- Índice por `checkout_intent_uuid`.
- Índice por `payment_intent_uuid`.
- Índice por `processing_status`.
- Índice por `received_at`.
- Índice por `processed_at`.

### J) Pendientes antes de SQL draft
Antes de crear SQL draft deben cerrarse:

- Decisión `accepted_pending_payment` vs aceptación al pagar.
- Fuente de precio.
- Estados definitivos.
- Proveedor inicial o abstracción provider-agnostic.
- Campos mínimos de provider ids.
- Si se guarda payload sanitizado.
- Relación exacta con `subscription_contract_acceptances`.
- Si `subscription_plans` requiere `amount/currency` o si habrá tabla comercial de precios.
- TTL/expiración de checkout intents.

### K) Siguiente microfase recomendada
Siguiente microfase recomendada:

- `DOCS-Suscripciones-CheckoutIntent-ContractAcceptanceTimingDecision-01`.

Objetivo:

- Decidir si la aceptación contractual se registra como pendiente de pago al crear checkout intent o si se crea/ratifica hasta pago confirmado.

Alternativa:

- `DB/SPEC-Suscripciones-CheckoutIntent-SchemaDraft-01`.

Sólo debe avanzar a schema draft si ya se considera suficiente la decisión de timing.

### L) Límites de esta adenda
Esta adenda no implementa:

- Backend.
- Frontend.
- SQL.
- SQL draft.
- DDL.
- Cambios de schema.
- Checkout.
- Pagos.
- Webhooks.
- Proveedor de pago.
- Facturación.
- Capacidades.
- `PublicProfilePlanCapabilities`.
- Perfil público.
- SEO.

---

## Adenda PP-Decisiones 63 — Decisión de timing de aceptación contractual en checkout-first

### A) Problema
En el flujo `checkout-first`, el usuario puede aceptar el contrato antes de pagar.

La suscripción no debe activarse hasta que exista pago confirmado.

Debe decidirse si la aceptación contractual se registra:

- Al iniciar checkout.
- O hasta que el pago se confirme.

La decisión impacta:

- Evidencia legal.
- Abandono de checkout.
- Pagos tardíos.
- Duplicados.
- Auditoría.
- Relación con `subscription_checkout_intents`.
- Relación con `profile_subscriptions`.

### B) Opciones evaluadas
#### 1. Crear aceptación contractual al iniciar checkout como `accepted_pending_payment`
Descripción:

- Al crear checkout intent, se registra aceptación contractual.
- La aceptación queda asociada al checkout intent.
- La suscripción operativa todavía no se crea ni se activa.
- `profile_subscriptions` se crea sólo después de pago confirmado.

Ventajas:

- Captura evidencia del consentimiento en el momento exacto en que el usuario acepta.
- Permite auditar abandono de checkout.
- Congela versión, hash y snapshot del contrato antes del pago.
- Facilita conciliación entre contrato aceptado y pago posterior.

Riesgos:

- Puede haber aceptaciones sin pago.
- Requiere estado claro para no confundir aceptación legal con suscripción activa.
- Requiere expiración/cancelación del checkout intent.
- Debe evitarse que una aceptación pendiente se interprete como plan activo.

#### 2. Crear o ratificar aceptación hasta pago confirmado
Descripción:

- Durante checkout se conserva snapshot contractual en `subscription_checkout_intents`.
- La aceptación formal se crea sólo cuando el pago se confirma.

Ventajas:

- Evita aceptaciones sin pago.
- Mantiene `subscription_contract_acceptances` sólo con operaciones completadas.

Riesgos:

- Puede perder evidencia exacta del momento en que el usuario aceptó antes de pagar.
- Si el pago se confirma por webhook, la aceptación podría quedar registrada por backend, no por acto directo del usuario.
- Requiere confiar en snapshot/metadata del checkout intent para reconstruir consentimiento.

#### 3. Doble etapa: acceptance event pendiente + ratificación al pagar
Descripción:

- Registrar aceptación al iniciar checkout como pendiente.
- Al confirmar pago, ratificar o enlazar esa aceptación a la suscripción activada.

Ventajas:

- Conserva evidencia del acto de aceptación.
- Permite distinguir aceptación pendiente vs activada.
- Encaja con checkout-first.

Riesgos:

- Requiere estados adicionales.
- Requiere claridad de schema y read-model.
- Requiere no duplicar aceptaciones.

### C) Decisión
Adoptar modelo de aceptación contractual pendiente al crear checkout intent.

Decisión:

- Registrar aceptación como `accepted_pending_payment` o estado equivalente.
- Asociarla a `subscription_checkout_intents`.
- No crear ni activar `profile_subscriptions` en ese momento.
- Mantener la misma `contract_acceptance_uuid` al confirmar pago si la aceptación pendiente es válida.
- No duplicar aceptación contractual al activar.

Al confirmar pago:

- Activar suscripción.
- Enlazar `subscription_id`.
- Marcar checkout intent como `activated`.
- Mantener trazabilidad entre checkout, pago, aceptación y suscripción.

Si el checkout expira, se cancela o falla:

- La aceptación queda como evidencia de intento abandonado/cancelado.
- No se interpreta como suscripción activa.
- Debe quedar vinculada al checkout intent y su estado final.

El read-model de suscripción no debe tomar `accepted_pending_payment` como plan activo.

### D) Cambios conceptuales necesarios
En fases futuras se requiere extender o acordar estados para `subscription_contract_acceptances`, por ejemplo:

- `accepted`.
- `accepted_pending_payment`.
- `cancelled`.
- `expired`.

También se requiere:

- Relacionar `subscription_checkout_intents.contract_acceptance_uuid`.
- Asegurar que `profile_subscriptions` sólo se crea tras pago confirmado.
- Asegurar que el read-model sólo usa `profile_subscriptions`, no aceptación pendiente.
- Asegurar idempotencia: una aceptación pendiente por checkout intent.
- Asegurar que replay no duplique aceptación.
- Asegurar lock de activación:
  - `mxmed:subscriptions:{entity_type}:{entity_id}:activate`.

### E) Flujo futuro conceptual
1. Usuario selecciona plan y acepta contrato.
2. Backend crea `subscription_checkout_intent`.
3. Backend registra aceptación contractual en estado pendiente de pago.
4. Backend inicia payment intent/provider.
5. Usuario paga o abandona.
6. Webhook confirma pago.
7. Backend valida firma e idempotencia de evento.
8. Backend toma lock de activación.
9. Backend revalida que no exista suscripción activa.
10. Backend crea `profile_subscriptions`.
11. Backend enlaza `subscription_id` a checkout intent y aceptación.
12. Backend marca checkout intent `activated`.
13. Capacidades quedan para fase posterior.

### F) Estados recomendados
Para `subscription_checkout_intents`:

- `pending_contract`.
- `pending_payment`.
- `payment_processing`.
- `paid`.
- `failed`.
- `expired`.
- `cancelled`.
- `activated`.

Para `subscription_contract_acceptances`:

- `accepted_pending_payment`.
- `accepted`.
- `expired`.
- `cancelled`.

Si el schema actual no soporta estos estados, se diseñará en SQL draft futuro.

No se implementa ahora.

### G) Casos a considerar
El diseño futuro debe cubrir:

1. Usuario acepta contrato pero abandona pago.
2. Checkout expira.
3. Pago se confirma tarde.
4. Pago falla.
5. Usuario reintenta checkout con mismo plan.
6. Usuario cambia de plan antes de pagar.
7. Ya existe suscripción activa al llegar webhook.
8. Webhook llega duplicado.
9. Provider confirma pago pero checkout intent está expirado.
10. Contrato cambia entre aceptación pendiente y pago.

### H) Seguridad y auditoría
Reglas:

- Guardar contrato version/hash/snapshot en checkout intent.
- Guardar `accepted_at`, `user_id`, `actor_role` y `source`.
- No guardar datos sensibles de pago en aceptación.
- No mezclar evidencia legal con payload de proveedor.
- Validar que aceptación pendiente no active capacidades.
- Mantener trazabilidad del estado final del checkout.
- Mantener `subscription_contract_acceptances` como evidencia legal, no como ledger técnico de webhooks.

### I) Restricciones
Esta decisión no implementa:

- Checkout.
- Pagos.
- Webhooks.
- Cambios de schema.
- SQL.
- SQL draft.
- Backend.
- Frontend.
- Activación sin pago.
- Facturación.
- Capacidades.
- `PublicProfilePlanCapabilities`.
- Perfil público.
- SEO.

### J) Siguiente microfase recomendada
Siguiente microfase recomendada:

- `DB/DIAG-Suscripciones-ContractAcceptance-StatusExtensionReadiness-01`.

Objetivo:

- Diagnosticar si `subscription_contract_acceptances.status` y campos actuales soportan `accepted_pending_payment`, `expired`, `cancelled` sin romper datos existentes.

Motivo:

- La decisión de timing depende de estados de aceptación contractual. Conviene validar compatibilidad del storage actual antes de crear SQL draft de checkout intents.

Siguiente alternativa, después de esa validación:

- `DB/SPEC-Suscripciones-CheckoutIntent-SchemaDraft-01`.

Objetivo:

- Crear SQL draft conceptual para `subscription_checkout_intents`, `subscription_payment_intents` y `subscription_payment_events`, incorporando aceptación pendiente de pago y activación post-pago, sin ejecutar SQL.

### K) Límites de esta adenda
Esta adenda no modifica:

- Backend.
- Frontend.
- SQL.
- DB/schema.
- Datos.

Tampoco conecta:

- Proveedor de pago.
- Facturación.
- Capacidades productivas.
- `PublicProfilePlanCapabilities`.

## Adenda PP-Decisiones 64 — Cierre del draft SQL de checkout y pagos de suscripciones

### A) Microfase cerrada
Microfase cerrada:

- `DB/SPEC-Suscripciones-CheckoutIntent-SchemaDraft-01`.

Commit del draft:

- `3dcc6d6 db(suscripciones): agrega draft de checkout y pagos`.

Archivo creado:

- `modules/profiles/db/2026_06_22_create_subscription_checkout_intents_draft.sql`.

### B) Alcance del draft
El archivo creado es un SQL draft conceptual para el storage futuro del flujo checkout-first de suscripciones productivas.

Define conceptualmente tres tablas futuras:

#### 1. `subscription_checkout_intents`
Propósito:

- Registrar intención de checkout.
- No activar suscripción por sí sola.
- Guardar snapshot contractual/comercial.
- Relacionar aceptación pendiente mediante `contract_acceptance_uuid`.
- Guardar pricing snapshot:
  - `amount_cents`.
  - `currency`.
  - `price_source`.
  - `price_version`.
- Guardar `subscription_id` nullable para llenarse al activar.

#### 2. `subscription_payment_intents`
Propósito:

- Modelar intento de pago vivo con proveedor.
- Guardar `provider_payment_id`.
- Guardar `provider_checkout_id` si aplica.
- Guardar status normalizado y status del proveedor.
- Separar estado de pago del checkout intent general.

#### 3. `subscription_payment_events`
Propósito:

- Ser ledger idempotente de webhooks/eventos del proveedor.
- Evitar reprocesar eventos.
- Usar:
  - `provider + provider_event_id`.
  - `event_hash`.
- Guardar payload sanitizado opcional:
  - `payload_text_sanitized`.
- No guardar datos sensibles de tarjeta.

### C) Decisiones respetadas
El draft respeta las decisiones previas:

- Flujo checkout-first.
- Aceptación contractual pendiente `accepted_pending_payment`.
- No crear `profile_subscriptions` hasta pago confirmado.
- No alterar `subscription_contract_acceptances` en v1.
- No crear `subscription_activation_log` en v1.
- `subscription_id` se genera al activar.
- Activación futura con lock:
  - `mxmed:subscriptions:{entity_type}:{entity_id}:activate`.
- Webhook idempotente por provider event.
- Pricing snapshot obligatorio.
- Sin FKs reales en v1.
- Sin `ENUM`.
- Sin `CHECK`.
- Sin `JSON`.
- Sin seeds.
- Sin `INSERT`.
- Sin `ALTER`/`DROP`.
- Engine/collation:
  - InnoDB / `utf8mb4_unicode_ci`.

### D) Estado explícito después del cierre
Estado del bloque:

- SQL draft creado y versionado.
- SQL draft validado en pending diff.
- No SQL ejecutado.
- No DB/schema modificado.
- No tablas reales creadas.
- No SQL ejecutable final creado.
- No backend modificado.
- No frontend modificado.
- No checkout implementado.
- No pagos conectados.
- No webhooks implementados.
- No facturación conectada.
- No capacidades activadas.
- No `PublicProfilePlanCapabilities`.
- No perfil público.
- No SEO.

### E) Relación con decisiones anteriores
Esta adenda cierra el draft derivado de:

- PP-Decisiones 61 — Decisión de flujo checkout-first para suscripciones productivas.
- PP-Decisiones 62 — Decisión de storage para checkout intents y pagos de suscripciones.
- PP-Decisiones 63 — Decisión de timing de aceptación contractual en checkout-first.

### F) Pendientes antes de ejecución real
Antes de crear o ejecutar SQL real faltan:

- Convertir draft a SQL ejecutable en microfase posterior.
- QA de SQL ejecutable.
- Ejecución local/dev controlada.
- Definir proveedor de pago real.
- Definir fuente real de precio.
- Definir adapter de provider.
- Definir webhook y firma.
- Definir endpoint `checkout-intents`.
- Definir activación interna post-pago.
- Definir TTL/cleanup de checkout intents.
- Definir facturación.
- Definir activación de capacidades en fase posterior.

### G) Siguiente microfase recomendada
Siguiente microfase recomendada:

- `DB-Suscripciones-CheckoutIntent-ExecutableSql-Readiness-01`.

Objetivo:

- Validar readiness para convertir el draft de checkout/payment en SQL ejecutable, sin ejecutar SQL todavía.

Motivo:

- El storage conceptual ya existe y fue validado como pending diff. Antes de especificar endpoints o provider adapters conviene confirmar que el draft puede convertirse en SQL ejecutable de forma controlada, manteniendo el límite de no ejecutar DDL todavía.

Alternativa si se prioriza contrato backend antes de SQL:

- `BE/SPEC-Suscripciones-CheckoutIntent-Endpoint-01`.

Objetivo:

- Especificar endpoint futuro de checkout intent sin implementarlo.

### H) Límites de esta adenda
Esta adenda no modifica:

- Backend.
- Frontend.
- SQL.
- DB/schema.
- Datos.

Tampoco implementa ni conecta:

- Checkout.
- Pagos.
- Webhooks.
- Proveedor de pago.
- Facturación.
- Capacidades.
- `PublicProfilePlanCapabilities`.
- Perfil público.
- SEO.

## Adenda PP-Decisiones 65 — Cierre de ejecución local/dev del SQL de checkout y pagos de suscripciones

### A) Microfases cerradas
Queda cerrado el tramo de creación, validación y ejecución local/dev del SQL de checkout/payment:

- `DB-Suscripciones-CheckoutIntent-ExecutableSql-Readiness-01`.
- `DB-Suscripciones-CheckoutIntent-ExecutableSql-Create-01`.
- `QA-Suscripciones-CheckoutIntent-ExecutableSql-PendingDiff-01`.
- `DB-Suscripciones-CheckoutIntent-ExecutableSql-ApplyLocalDev-Readiness-01`.
- `DB-Suscripciones-CheckoutIntent-ExecutableSql-ApplyLocalDev-01`.
- `QA-Suscripciones-CheckoutIntent-ExecutableSql-PostApplyLocalDev-01`.

Commit del SQL ejecutable:

- `9fe267c db(suscripciones): agrega SQL ejecutable de checkout y pagos`.

Archivo SQL ejecutable:

- `modules/profiles/db/2026_06_22_create_subscription_checkout_intents.sql`.

### B) Tablas creadas en local/dev
La ejecución local/dev creó las tres tablas de storage futuro para checkout-first:

- `subscription_checkout_intents`.
- `subscription_payment_intents`.
- `subscription_payment_events`.

Estado local/dev posterior a la ejecución:

- `subscription_checkout_intents` = 0 filas.
- `subscription_payment_intents` = 0 filas.
- `subscription_payment_events` = 0 filas.
- `profile_subscriptions` = 3.
- `subscription_contract_acceptances` = 3.
- `subscription_write_idempotency_keys` = 5.

### C) QA post-ejecución
La QA post-ejecución local/dev confirmó:

- DB local/dev accesible.
- Versión reportada: `9.6.0`.
- Las tres tablas existen.
- Las tres tablas usan InnoDB.
- Las tres tablas usan `utf8mb4_unicode_ci`.
- No hay FKs reales.
- No hay `REFERENCES`.
- No hay `CHECK`.
- No hay `JSON`.
- No hay seeds ni datos iniciales.
- No hay columnas sensibles de tarjeta.
- No hay facturación operativa.
- No hay capacidades productivas.
- No hay `subscription_activation_log`.
- Índices críticos presentes.

Defaults principales validados:

- `subscription_checkout_intents.status` = `pending_contract`.
- `subscription_checkout_intents.currency` = `MXN`.
- `subscription_payment_intents.normalized_status` = `created`.
- `subscription_payment_intents.currency` = `MXN`.
- `subscription_payment_events.processing_status` = `received`.
- `created_at` / `updated_at` con timestamps esperados.

### D) Alcance explícito
Esta ejecución fue exclusivamente local/dev.

Este cierre no implementa:

- Checkout productivo.
- Pagos.
- Webhooks.
- Proveedor de pago.
- Facturación.
- Capacidades.
- `PublicProfilePlanCapabilities`.
- Perfil público.
- SEO.
- Endpoints nuevos.

Tampoco inserta datos.

### E) Relación con decisiones previas
Este cierre respeta y continúa:

- PP-Decisiones 61 — checkout-first.
- PP-Decisiones 62 — storage checkout/payment.
- PP-Decisiones 63 — aceptación contractual pending payment.
- PP-Decisiones 64 — cierre del draft SQL de checkout y pagos.

La diferencia con PP-Decisiones 64 es que esta adenda cierra la ejecución local/dev del SQL ejecutable ya versionado.

### F) Pendientes posteriores
Pendientes antes de cualquier flujo productivo:

- Diseñar/crear fuente real de precio.
- Diseñar endpoint futuro:
  - `POST /api/subscriptions/index.php/entities/{entity_type}/{entity_id}/checkout-intents`.
- Diseñar provider adapter.
- Diseñar webhook futuro:
  - `POST /api/subscriptions/index.php/payments/webhooks/{provider}`.
- Diseñar firma e idempotencia de webhook.
- Diseñar activación interna post-pago con lock:
  - `mxmed:subscriptions:{entity_type}:{entity_id}:activate`.
- Diseñar actualización de aceptación `accepted_pending_payment` -> `accepted`.
- Diseñar expiración/cancelación de checkout intents.
- Diseñar facturación por separado.
- Diseñar conexión futura de capacidades en fase posterior.
- No ejecutar nada productivo todavía.

### G) Siguiente microfase recomendada
Siguiente microfase recomendada:

- `BE/SPEC-Suscripciones-CheckoutIntent-Endpoint-01`.

Objetivo:

- Especificar el endpoint futuro de checkout intent sin implementarlo todavía.

Motivo:

- El storage local/dev ya existe y está validado. El siguiente paso lógico es definir contrato backend, payload, auth, idempotencia, estados y límites del endpoint antes de implementar código o conectar proveedor de pago.

### H) Límites de esta adenda
Esta adenda no modifica:

- Backend.
- Frontend.
- SQL.
- DB/schema.
- Datos.

Tampoco implementa ni conecta:

- Checkout.
- Pagos.
- Webhooks.
- Proveedor de pago.
- Facturación.
- Capacidades.
- `PublicProfilePlanCapabilities`.
- Perfil público.
- SEO.

## Adenda PP-Decisiones 66 — Diseño del endpoint checkout-intents de suscripciones

### A) Propósito del endpoint
Endpoint futuro:

- `POST /api/subscriptions/index.php/entities/{entity_type}/{entity_id}/checkout-intents`.

Propósito:

- Crear una intención de checkout para contratar o renovar plan.
- Registrar aceptación contractual en estado `accepted_pending_payment`.
- Crear o preparar el registro `subscription_checkout_intents`.
- Preparar la relación futura con `subscription_payment_intents`.
- No activar `profile_subscriptions`.
- No activar capacidades.
- No facturar.
- No procesar webhook.
- No confirmar pago.

### B) Modelo checkout-first
Este endpoint pertenece al flujo checkout-first definido para suscripciones productivas.

Reglas:

- Es distinto del endpoint DEV/local actual:
  - `POST /api/subscriptions/index.php/entities/{entity_type}/{entity_id}/subscriptions`.
- No debe convertir el write contractual DEV/local en checkout productivo.
- Debe crear estado pendiente y esperar pago confirmado.
- La activación real ocurrirá después mediante servicio interno post-pago.

### C) Autorización esperada
El endpoint futuro debe requerir sesión real autorizada.

Reglas de autorización:

- Sólo `session_scope` médico principal o actor autorizado futuro.
- `local_dev_open` no autoriza writes productivos.
- Headers QA no autorizan writes productivos.
- No confiar en `X-User-Id`.
- Validar relación usuario/entidad.
- `entity_type` inicial permitido: `doctor`.
- No aceptar entidad arbitraria fuera de scope.
- Mantener separación con fixtures DEV/local.

### D) Request esperado
Request JSON conceptual:

```json
{
  "plan_code": "standard",
  "billing_period": "annual",
  "contract": {
    "version": "mxmed-subscriptions-v1",
    "hash": "sha256:...",
    "snapshot_url": "/legal/subscriptions/mxmed-subscriptions-v1.html",
    "title": "Contrato de suscripción México Médico"
  },
  "acceptance": {
    "source": "panel_subscription"
  }
}
```

Validaciones:

- `plan_code` obligatorio.
- No permitir `free` como checkout pagado.
- `billing_period` obligatorio y compatible con catálogo.
- Plan debe existir y estar activo.
- Plan debe ser contratable.
- `contract.version` obligatorio.
- `contract.hash` obligatorio y debe iniciar con `sha256:`.
- `contract.snapshot_url` obligatorio.
- `acceptance.source` obligatorio y permitido.

Campos cliente-prohibidos:

- `amount_cents`.
- `currency`.
- `price_source`.
- `price_version`.
- `status`.
- `user_id`.
- `doctor_id`.
- `profile_id`.
- `subscription_id`.
- Provider ids.
- `checkout_url`.
- `accepted_at`.
- `starts_at`.
- `expires_at`.
- `activated_at`.

### E) Pricing server-side
El cliente no envía precio.

El backend debe calcular:

- `amount_cents`.
- `currency`.
- `price_source`.
- `price_version`.

Antes de implementación real falta decidir fuente de precio.

Reglas:

- No usar `subscription_plans` como precio final si no tiene `amount`/`currency`.
- Si no existe fuente de precio, el endpoint real debe responder error controlado.
- Sin fuente de precio resuelta no debe crear checkout productivo.

### F) Idempotencia
El endpoint requiere header:

- `Idempotency-Key`.

Validación esperada:

- Longitud de 8 a 128 caracteres.
- Caracteres permitidos: letras, números, `.`, `_`, `:`, `-`.
- No guardar key cruda.
- Guardar hash `sha256`.

Operación conceptual:

- `subscriptions.checkout_intent.create`.

El `request_hash` debe incluir:

- Scope de usuario/entidad.
- `plan_code`.
- `billing_period`.
- Contrato.
- `acceptance.source`.
- Pricing snapshot calculado server-side o `price_version` resuelta.

Comportamiento:

- Misma key + mismo payload: replay estable.
- Misma key + payload distinto: `409 idempotency_key_reused_with_different_payload`.
- Key en `processing`: `409 request_already_processing`.
- Key `failed`, `expired` o `cancelled`: no reusable.

La creación de checkout intent puede reutilizar `subscription_write_idempotency_keys` con operación nueva.

Los webhooks no deben usar esa tabla como ledger principal; usan `subscription_payment_events`.

### G) Locks
Lock recomendado para creación de checkout intent:

- `mxmed:subscriptions:{entity_type}:{entity_id}:checkout_create`.

Uso esperado:

- Tomar lock alrededor de la creación del checkout intent y aceptación pending.
- Revalidar que no exista suscripción activa antes de crear checkout.
- Evitar intents concurrentes conflictivos.
- Timeout conceptual: 2 segundos o similar al lock contractual.
- Error conceptual:
  - `409 subscription_checkout_lock_timeout`.

La activación post-pago usará otro lock:

- `mxmed:subscriptions:{entity_type}:{entity_id}:activate`.

No se implementa en esta microfase.

### H) Escrituras futuras esperadas
En una implementación futura, el endpoint hará en transacción:

1. Validar auth/session/scope.
2. Validar entidad.
3. Validar plan/billing.
4. Resolver pricing server-side.
5. Validar contrato y acceptance.
6. Validar idempotencia.
7. Tomar lock de `checkout_create`.
8. Revalidar que no exista suscripción activa.
9. Crear `subscription_contract_acceptances` con:
   - `status = accepted_pending_payment`.
   - `subscription_id = NULL`.
10. Crear `subscription_checkout_intents` con:
   - status inicial `pending_payment` o `pending_contract` según momento exacto.
   - `contract_acceptance_uuid`.
   - `amount_cents` / `currency` / `price_source` / `price_version`.
   - `expires_at`.
   - `idempotency_key_hash` / `request_hash`.
11. Preparar `subscription_payment_intents` si ya existe provider adapter.
12. Devolver respuesta con checkout intent y, si aplica, `checkout_url`.
13. No crear `profile_subscriptions`.

Si todavía no hay provider adapter, puede diseñarse respuesta `provider_not_configured` o `checkout_provider_unavailable`. Esta decisión debe cerrarse antes de implementación.

### I) Estados
Estados esperados de `subscription_checkout_intents`:

- `pending_contract`.
- `pending_payment`.
- `payment_processing`.
- `paid`.
- `failed`.
- `expired`.
- `cancelled`.
- `activated`.

Estado inicial recomendado:

- Si la aceptación se crea dentro del endpoint, el checkout puede avanzar a `pending_payment`.
- Si se separa confirmación contractual previa, usar `pending_contract`.
- Para este diseño, se recomienda `pending_payment` después de crear aceptación `accepted_pending_payment`.

Estados de aceptación:

- `accepted_pending_payment` al crear checkout.
- `accepted` al activar post-pago.
- `expired` / `cancelled` si checkout expira o se cancela.

### J) Response esperado
Response 201 conceptual:

```json
{
  "ok": true,
  "checkout_intent": {
    "uuid": "...",
    "entity_type": "doctor",
    "entity_id": "1",
    "plan_code": "standard",
    "billing_period": "annual",
    "amount_cents": 0,
    "currency": "MXN",
    "status": "pending_payment",
    "expires_at": "...",
    "contract_acceptance_uuid": "...",
    "provider": null,
    "checkout_url": null
  },
  "payment_intent": null,
  "current_subscription": {
    "plan_code": "free",
    "status": "free_default"
  },
  "idempotent_replay": false
}
```

Si hay provider adapter futuro:

- `provider` puede ir poblado.
- `checkout_url` puede ir poblado.
- `payment_intent` puede ir poblado.

Aclaraciones:

- `current_subscription` no debe cambiar a plan pagado hasta pago confirmado.
- No devolver datos sensibles.
- No devolver payload completo de proveedor.

### K) Errores esperados
Errores conceptuales:

- `400 invalid_json`.
- `401 unauthenticated`.
- `403 forbidden`.
- `404 entity_not_found`.
- `409 active_subscription_exists`.
- `409 checkout_already_pending`.
- `409 idempotency_key_reused_with_different_payload`.
- `409 request_already_processing`.
- `409 subscription_checkout_lock_timeout`.
- `422 plan_not_contractable`.
- `422 billing_period_invalid`.
- `422 contract_invalid`.
- `422 acceptance_source_invalid`.
- `422 idempotency_key_invalid`.
- `503 checkout_provider_unavailable`.
- `500 checkout_intent_create_failed`.

### L) Relación con tablas
`subscription_checkout_intents`:

- Tabla principal del endpoint.
- Guarda snapshot comercial/contractual.
- No activa por sí misma.

`subscription_contract_acceptances`:

- Guarda aceptación legal pending payment.
- `subscription_id` queda `NULL` hasta activación.
- Luego cambia a `accepted` al confirmar pago.

`subscription_payment_intents`:

- Se usará cuando exista provider adapter.
- Modela intento vivo de proveedor.

`subscription_payment_events`:

- No lo escribe este endpoint normalmente.
- Lo escriben webhooks/eventos del proveedor.
- Es ledger idempotente.

`profile_subscriptions`:

- No se crea ni modifica por este endpoint.
- Sólo cambia en activación post-pago.

### M) Seguridad
Reglas:

- No guardar PAN/CVV.
- No guardar payload sensible de proveedor.
- No confiar en amount del cliente.
- Rate limit futuro.
- Idempotencia obligatoria.
- Lock por entidad.
- Logs mínimos.
- Firma webhook queda para endpoint webhook, no para checkout create.
- No activar capacidades desde frontend.

### N) Fuera de alcance explícito
Esta microfase no:

- Implementa endpoint.
- Implementa provider adapter.
- Implementa webhook.
- Implementa firma.
- Implementa activación post-pago.
- Ejecuta SQL.
- Modifica tablas.
- Crea datos.
- Implementa frontend checkout.
- Conecta facturación.
- Activa capacidades.
- Toca perfil público.
- Toca SEO.

### O) Siguiente microfase recomendada
Siguiente microfase recomendada:

- `BE/SPEC-Suscripciones-CheckoutIntent-PricingSourceDecision-01`.

Objetivo:

- Decidir fuente server-side de precio para checkout intents antes de implementar endpoint productivo, ya que `subscription_plans` no tiene `amount`/`currency` persistidos.

Motivo:

- El endpoint no debe confiar en precio enviado por cliente. Sin fuente server-side de precio no debe crearse checkout productivo.

## Adenda PP-Decisiones 67 — Decisión de fuente de precio server-side para checkout intents

### A) Problema
El endpoint futuro `checkout-intents` requiere calcular precio en backend antes de crear un checkout productivo.

La tabla `subscription_checkout_intents` ya contiene campos para snapshot comercial:

- `amount_cents`.
- `currency`.
- `price_source`.
- `price_version`.

Reglas:

- El cliente no debe enviar ni controlar precio.
- `subscription_plans` actualmente no tiene monto ni moneda.
- `subscription_plans` tampoco tiene `price_source` ni `price_version`.
- No se debe implementar checkout productivo sin fuente de precio server-side.

Diagnóstico local/dev:

- `subscription_plans` funciona como catálogo técnico de `plan_code`, `billing_period`, `duration_days`, `is_active` y `sort_order`.
- Planes actuales observados: `free`, `basic`, `standard`, `optimum`, `professional`.
- No contiene `amount_cents`, `currency`, `price_source` ni `price_version`.

### B) Opciones evaluadas
#### 1. Agregar precio directamente a `subscription_plans`
Ventaja:

- Simple y cercano al catálogo actual.

Desventajas:

- Mezcla catálogo técnico/duración con precio comercial.
- Riesgo de cambios de precio retroactivos si no se versiona bien.
- No resuelve por sí solo vigencias ni versiones comerciales.

Decisión:

- No recomendado como decisión inmediata si se necesita historial/versionado.

#### 2. Crear tabla dedicada de precios/versiones
Tabla conceptual:

- `subscription_plan_prices`.

Ventajas:

- Permite versionar precios.
- Permite vigencia desde/hasta.
- Permite moneda.
- Permite fuente y versión.
- Permite preservar snapshot en checkout intent.
- Separa catálogo técnico de política comercial.

Decisión:

- Recomendado como fuente canónica futura.

#### 3. Usar configuración hardcodeada temporal en backend
Ventaja:

- Rápido para DEV/local.

Desventajas:

- No auditable.
- Riesgo de divergencia entre código, docs y DB.
- Difícil de conciliar con provider y webhooks.

Decisión:

- No recomendado para productivo.

#### 4. Usar proveedor de pago como fuente de precio
Ventaja:

- El proveedor controla productos/precios.

Desventajas:

- Acopla catálogo MXMed al proveedor.
- Dificulta auditar antes de crear checkout.
- Puede complicar conciliación si MXMed no resuelve precio primero.

Decisión:

- No recomendado como única fuente canónica MXMed.

### C) Decisión recomendada
Crear en microfase futura una tabla dedicada de precios versionados:

- `subscription_plan_prices`.

Esta tabla será la fuente server-side canónica para checkout intents.

`subscription_plans` se mantiene como catálogo técnico de planes:

- `plan_code`.
- `billing_period`.
- `duration_days`.
- `is_active`.
- `sort_order`.

`subscription_plan_prices` será la fuente de precio:

- `plan_code`.
- `billing_period`.
- `amount_cents`.
- `currency`.
- `price_source`.
- `price_version`.
- `valid_from`.
- `valid_until`.
- `is_active`.

El endpoint futuro resolverá precio server-side consultando esta tabla y copiará snapshot a `subscription_checkout_intents`.

### D) Campos conceptuales de `subscription_plan_prices`
Campos sugeridos:

- `id BIGINT UNSIGNED AUTO_INCREMENT`.
- `uuid CHAR(36) NOT NULL`.
- `plan_code VARCHAR(64) NOT NULL`.
- `billing_period VARCHAR(32) NOT NULL`.
- `amount_cents BIGINT UNSIGNED NOT NULL`.
- `currency CHAR(3) NOT NULL DEFAULT 'MXN'`.
- `price_source VARCHAR(128) NOT NULL DEFAULT 'subscription_plan_prices'`.
- `price_version VARCHAR(64) NOT NULL`.
- `valid_from DATETIME NOT NULL`.
- `valid_until DATETIME NULL DEFAULT NULL`.
- `is_active TINYINT(1) NOT NULL DEFAULT 1`.
- `source VARCHAR(128) NOT NULL DEFAULT 'mxmed_subscription_plan_price_v1'`.
- `notes TEXT NULL`.
- `created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP`.
- `updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`.
- `deleted_at DATETIME NULL DEFAULT NULL`.

Índices conceptuales:

- `uuid` unique.
- `plan_code + billing_period + currency + price_version` unique.
- `plan_code + billing_period + is_active`.
- `valid_from` / `valid_until`.
- `deleted_at`.

El SQL de esta tabla no se crea en esta microfase.

### E) Regla de resolución de precio
Algoritmo futuro:

1. Validar `plan_code` y `billing_period` contra `subscription_plans`.
2. Confirmar plan activo y contratable.
3. Buscar precio activo en `subscription_plan_prices` para:
   - `plan_code`.
   - `billing_period`.
   - `currency = MXN`.
   - `is_active = 1`.
   - `deleted_at IS NULL`.
   - `valid_from <= now`.
   - `valid_until IS NULL` o `valid_until > now`.
4. Si hay más de un precio activo para el mismo plan/periodo/moneda:
   - responder `500 pricing_configuration_conflict`.
   - no crear checkout intent.
5. Si no hay precio:
   - responder `422 plan_price_not_configured`.
   - no crear checkout intent.
6. Si la fuente de pricing falla por error técnico:
   - responder `503 pricing_source_unavailable`.
   - no crear checkout intent.
7. Copiar snapshot a `subscription_checkout_intents`:
   - `amount_cents`.
   - `currency`.
   - `price_source`.
   - `price_version`.
8. No recalcular monto desde frontend.
9. No modificar el checkout intent si el precio cambia después.

### F) Errores conceptuales
Errores recomendados:

- `422 plan_price_not_configured`: el plan existe, pero no tiene precio configurado vigente.
- `500 pricing_configuration_conflict`: existe más de un precio activo para el mismo plan/periodo/moneda.
- `503 pricing_source_unavailable`: la fuente de pricing no está disponible por error técnico.

### G) Relación con provider de pago
MXMed debe resolver precio antes de llamar al proveedor.

Reglas:

- El provider adapter debe recibir el precio ya resuelto por MXMed.
- El provider no debe ser la única fuente canónica de precio.
- Al crear payment intent, el monto enviado al proveedor debe coincidir con el snapshot guardado en `subscription_checkout_intents`.
- El webhook posterior debe validar `amount`/`currency` contra snapshot antes de activar.

### H) Relación con checkout intent
`subscription_checkout_intents.amount_cents` guarda snapshot inmutable del precio resuelto.

Campos de auditoría comercial:

- `currency` guarda moneda.
- `price_source` indica origen, recomendado: `subscription_plan_prices`.
- `price_version` identifica versión comercial.

Uso:

- Auditoría.
- Conciliación con proveedor.
- Evitar cambios retroactivos si el catálogo cambia.

Cambios futuros de precio no alteran checkout intents ya creados.

### I) Relación con facturación
La tabla de precios no implementa facturación.

Reglas:

- No contiene CFDI.
- No contiene datos fiscales.
- Facturación seguirá en flujo separado.
- Puede usar el snapshot de checkout/payment como referencia.
- No se implementa ahora.

### J) Seguridad y auditoría
Reglas:

- No confiar en precio enviado por cliente.
- No aceptar `amount_cents`, `currency`, `price_source`, `price_version` desde request.
- Versionar precios.
- Mantener vigencias.
- No borrar físicamente precios usados en checkout histórico.
- Soft delete sólo para ocultar/desactivar, no para destruir trazabilidad.
- Registrar `price_version` suficiente para auditoría.
- Validar `amount`/`currency` en webhook antes de activación.

### K) Fuera de alcance explícito
Esta microfase no:

- Crea `subscription_plan_prices`.
- Altera `subscription_plans`.
- Inserta precios.
- Implementa endpoint.
- Implementa provider adapter.
- Implementa webhook.
- Implementa activación post-pago.
- Implementa facturación.
- Conecta capacidades.
- Ejecuta SQL.

### L) Siguiente microfase recomendada
Siguiente microfase recomendada:

- `DB/SPEC-Suscripciones-PlanPrices-SchemaDraft-01`.

Objetivo:

- Diseñar el SQL draft conceptual de `subscription_plan_prices`, sin crear SQL ejecutable y sin ejecutar SQL.

Motivo:

- El endpoint `checkout-intents` necesita una fuente server-side de precio versionada antes de implementarse. El siguiente paso es diseñar esa tabla sin tocar todavía DB/schema real.

## Adenda PP-Decisiones 68 — Cierre del draft SQL de precios versionados de suscripciones

### A) Microfase cerrada
Microfase cerrada:

- `DB/SPEC-Suscripciones-PlanPrices-SchemaDraft-01`.

QA cerrada:

- `QA-Suscripciones-PlanPrices-SchemaDraft-PendingDiff-01`.

Commit:

- `a84f765 db(suscripciones): agrega draft de precios versionados`.

Archivo:

- `modules/profiles/db/2026_06_22_create_subscription_plan_prices_draft.sql`.

### B) Propósito del draft
El draft conceptual define la futura tabla:

- `subscription_plan_prices`.

Propósito:

- Ser la fuente server-side canónica de precios/versiones para checkout intents.
- Separar precio comercial versionado del catálogo técnico de planes.
- Permitir que el endpoint futuro `checkout-intents` copie un snapshot de precio a `subscription_checkout_intents`.

### C) Relación con PP-Decisiones 67
El draft materializa a nivel conceptual la decisión PP-Decisiones 67.

Reglas:

- `subscription_plans` queda como catálogo técnico.
- `subscription_plan_prices` será fuente futura de `amount_cents`, `currency`, `price_source`, `price_version`, `valid_from`, `valid_until` e `is_active`.
- El endpoint futuro `checkout-intents` resolverá precio server-side y copiará el snapshot a `subscription_checkout_intents`.
- Cambios futuros de precio no deben modificar checkout intents ya creados.

### D) Contenido del draft
Identidad:

- `id`.
- `uuid`.

Plan/periodo:

- `plan_code`.
- `billing_period`.

Precio:

- `amount_cents`.
- `currency`.
- `price_source`.
- `price_version`.

Vigencia:

- `valid_from`.
- `valid_until`.
- `is_active`.

Auditoría:

- `source`.
- `notes`.
- `created_at`.
- `updated_at`.
- `deleted_at`.

Índices/uniques conceptuales:

- `uuid` unique.
- Unique por `plan_code + billing_period + currency + price_version`.
- Lookup por `plan_code + billing_period + currency + is_active + vigencias`.
- Índices por plan, activo, vigencia, `created_at` y `deleted_at`.

### E) Validaciones de QA
La QA de pending diff confirmó:

- El archivo es `DRAFT ONLY`.
- No existe SQL ejecutable final.
- Define sólo `subscription_plan_prices`.
- No contiene FK ni `REFERENCES`.
- No contiene `ENUM`.
- No contiene `CHECK`.
- No contiene `JSON`.
- No contiene seeds ni `INSERT`.
- No contiene `ALTER` ni `DROP`.
- No inserta precios.
- No toca tablas existentes.
- No modifica backend, frontend ni documentación.
- No ejecuta SQL.
- `git diff --check` quedó limpio.

### F) Errores conceptuales asociados
El draft conserva los errores conceptuales definidos en PP-Decisiones 67:

- `plan_price_not_configured`.
- `pricing_configuration_conflict`.
- `pricing_source_unavailable`.

### G) Alcance explícito
Esta adenda no implica:

- SQL ejecutable creado.
- SQL ejecutado.
- Tabla real creada.
- Precios insertados.
- Seeds creados.
- Cambios en `subscription_plans`.
- Endpoint `checkout-intents` implementado.
- Provider adapter implementado.
- Webhook implementado.
- Facturación conectada.
- Capacidades activadas.
- Perfil público o SEO tocados.

### H) Pendientes posteriores
Pendientes:

1. Convertir el draft a SQL ejecutable: `modules/profiles/db/2026_06_22_create_subscription_plan_prices.sql`.
2. Hacer QA del SQL ejecutable.
3. Ejecutar el SQL en local/dev en microfase autorizada.
4. Hacer QA post-ejecución local/dev.
5. Decidir estrategia de precios iniciales:
   - Sin seeds en SQL de schema.
   - Posible microfase futura separada para precios DEV/local.
   - Precios reales/productivos sujetos a decisión comercial.
6. Definir si `free` tendrá precio `0` o si queda fuera de la tabla de precios pagados.
7. Integrar resolución de precio server-side en endpoint `checkout-intents` futuro.
8. Validar `amount`/`currency` contra snapshot en webhook futuro antes de activar.
9. Mantener facturación como flujo separado.
10. Conectar capacidades sólo en fase posterior.

### I) Siguiente microfase recomendada
Siguiente microfase recomendada:

- `DB-Suscripciones-PlanPrices-ExecutableSql-Readiness-01`.

Objetivo:

- Validar readiness para convertir el draft conceptual de `subscription_plan_prices` en SQL ejecutable versionado, sin crear todavía el SQL ejecutable y sin ejecutar SQL.

## Adenda PP-Decisiones 69 — Cierre de ejecución local/dev del SQL de precios versionados de suscripciones

### A) Microfases cerradas
Microfases cerradas:

- `DB-Suscripciones-PlanPrices-ExecutableSql-Readiness-01`.
- `DB-Suscripciones-PlanPrices-ExecutableSql-Create-01`.
- `QA-Suscripciones-PlanPrices-ExecutableSql-PendingDiff-01`.
- `DB-Suscripciones-PlanPrices-ExecutableSql-ApplyLocalDev-Readiness-01`.
- `DB-Suscripciones-PlanPrices-ExecutableSql-ApplyLocalDev-01`.
- `QA-Suscripciones-PlanPrices-ExecutableSql-PostApplyLocalDev-01`.

### B) Commit SQL ejecutable
Commit:

- `bf94085 db(suscripciones): agrega SQL ejecutable de precios versionados`.

### C) Archivo SQL ejecutable
Archivo:

- `modules/profiles/db/2026_06_22_create_subscription_plan_prices.sql`.

### D) Tabla creada en DB local/dev
Tabla creada:

- `subscription_plan_prices`.

### E) Estado DB local/dev despues de ejecucion
Conteos despues de la ejecucion local/dev:

- `subscription_plan_prices = 0`.
- `active_plan_prices = 0`.
- `subscription_checkout_intents = 0`.
- `subscription_payment_intents = 0`.
- `subscription_payment_events = 0`.
- `profile_subscriptions = 3`.
- `subscription_contract_acceptances = 3`.
- `subscription_write_idempotency_keys = 5`.

### F) Validaciones QA post-ejecucion
La QA post-ejecucion confirmo:

- DB local/dev accesible.
- Version reportada: `9.6.0`.
- Tabla `subscription_plan_prices` existe.
- Tabla vacia.
- Sin precios/seeds activos.
- Engine `InnoDB`.
- Collation `utf8mb4_unicode_ci`.
- Sin FKs reales.
- Sin `REFERENCES`.
- Sin `CHECK`.
- Sin `JSON`.
- Sin datos sensibles de tarjeta.
- Sin facturacion operativa.
- Sin capacidades productivas.
- Sin `subscription_activation_log`.

Columnas presentes:

- `id`.
- `uuid`.
- `plan_code`.
- `billing_period`.
- `amount_cents`.
- `currency`.
- `price_source`.
- `price_version`.
- `valid_from`.
- `valid_until`.
- `is_active`.
- `source`.
- `notes`.
- `created_at`.
- `updated_at`.
- `deleted_at`.

Defaults principales:

- `currency = MXN`.
- `price_source = subscription_plan_prices`.
- `is_active = 1`.
- `source = mxmed_subscription_plan_price_v1`.
- `created_at` / `updated_at` con timestamps esperados.
- `valid_until` / `deleted_at` nullable.

Indices criticos:

- `PRIMARY`.
- `ux_sub_plan_prices_uuid`.
- `ux_sub_plan_prices_version`.
- `idx_sub_plan_prices_lookup`.
- `idx_sub_plan_prices_plan`.
- `idx_sub_plan_prices_active`.
- `idx_sub_plan_prices_validity`.
- `idx_sub_plan_prices_created_at`.
- `idx_sub_plan_prices_deleted_at`.

### G) Alcance explicito
Este cierre:

- Es local/dev.
- Solo crea infraestructura DB.
- No inserta precios.
- No crea seeds.
- No define precios reales.
- No decide todavia si `free` tendra precio `0` o quedara fuera de precios pagados.
- No altera `subscription_plans`.
- No implementa endpoint `checkout-intents`.
- No implementa provider adapter.
- No implementa webhook.
- No activa suscripciones.
- No activa capacidades.
- No conecta facturacion.
- No toca perfil publico ni SEO.

### H) Relacion con decisiones previas
Este cierre:

- Continua PP-Decisiones 67.
- Cierra la ejecucion practica posterior al draft cerrado en PP-Decisiones 68.
- Deja lista la tabla para que el endpoint futuro resuelva pricing server-side.
- No vuelve usable checkout productivo todavia: faltan precios/versiones y backend de resolucion.

### I) Pendientes posteriores
Pendientes:

1. Decidir estrategia de precios iniciales:
   - DEV/local separado.
   - Productivo sujeto a decision comercial.
   - Sin mezclar seeds con schema.
2. Decidir si `free` tendra precio `0` o queda fuera de tabla de precios pagados.
3. Disenar/crear microfase de seeds DEV/local si aplica.
4. Disenar repositorio/servicio de resolucion de precio.
5. Validar "un solo precio activo vigente" en backend/QA v1.
6. Integrar precio server-side en endpoint `checkout-intents` futuro.
7. Copiar snapshot a `subscription_checkout_intents`.
8. Validar `amount`/`currency` contra snapshot en webhook futuro.
9. Mantener facturacion como flujo separado.
10. Conectar capacidades solo en fase posterior.

### J) Siguiente microfase recomendada
Siguiente microfase recomendada:

- `DB/SPEC-Suscripciones-PlanPrices-SeedStrategyDecision-01`.

Objetivo:

- Decidir estrategia de precios iniciales y seeds DEV/local para `subscription_plan_prices`, sin insertar todavia precios y sin ejecutar SQL.

## Adenda PP-Decisiones 70 — Estrategia de precios iniciales y seeds DEV/local para suscripciones

### A) Problema
La tabla `subscription_plan_prices` ya existe en local/dev, pero esta vacia:

- `subscription_plan_prices = 0`.
- `active_plan_prices = 0`.

Implicaciones:

- El endpoint futuro `checkout-intents` no puede crear checkout real si no existe un precio server-side vigente.
- No se deben mezclar seeds de precios con SQL de schema.
- No se deben confundir precios DEV/local con precios reales/productivos.
- Falta decidir si `free` entra o no en `subscription_plan_prices`.

### B) Principios de decision
Principios:

1. El schema no debe insertar precios.
2. Los precios iniciales deben ir en una microfase separada.
3. Los precios DEV/local deben quedar claramente marcados como dev/local.
4. Los precios productivos requieren decision comercial explicita.
5. El cliente nunca envia precio.
6. El endpoint `checkout-intents` debe fallar controladamente si no hay precio activo.
7. Los cambios de precio deben versionarse, no sobrescribirse.
8. No se deben borrar fisicamente precios usados historicamente.
9. Facturacion sigue separada.
10. Capacidades siguen separadas.

### C) Decision sobre seeds
Decision:

- No incluir seeds en SQL de schema.
- Crear, si se autoriza despues, un archivo SQL separado para seeds DEV/local.

Nombre futuro sugerido:

- `modules/profiles/db/2026_06_22_seed_subscription_plan_prices_dev.sql`.

Reglas del archivo futuro:

- Sera DEV/local only.
- No sera productivo.
- Insertara precios de prueba.
- Se ejecutara solo por microfase explicita.
- No se mezclara con schema.
- Debera documentarse y validarse por QA.

### D) Decision sobre plan `free`
Decision recomendada:

- `free` no debe tener fila de precio en `subscription_plan_prices` para checkout pagado v1.

Motivos:

- `free` no requiere checkout ni pago.
- Evita confundir flujo gratuito con flujo de cobro.
- El read-model actual ya puede resolver `free_default` sin precio.
- Los checkout-intents pagados deben rechazar `free`.

Si en el futuro se requiere auditar plan `free` con monto `0`, se disenara en microfase separada.

### E) Precios DEV/local sugeridos
Se podran usar precios placeholder DEV/local no productivos en una microfase futura.

Planes candidatos DEV/local:

- `basic annual`.
- `standard annual`.
- `optimum annual`.
- `professional annual`.

Valores simbolicos sugeridos:

- `basic annual = 10000` MXN cents.
- `standard annual = 20000` MXN cents.
- `optimum annual = 30000` MXN cents.
- `professional annual = 40000` MXN cents.

Aclaraciones:

- No son precios reales.
- No son precios comerciales aprobados.
- No deben usarse en produccion.
- Solo sirven para probar resolucion server-side y `checkout-intents` en DEV/local.

### F) Versionado de precios DEV/local
Convencion para seed DEV/local futuro:

- `price_source = subscription_plan_prices_dev_seed`.
- `price_version = mxmed-dev-pricing-2026-v1`.
- `currency = MXN`.
- `valid_from = fecha fija futura o NOW controlado en seed autorizado`.
- `valid_until = NULL`.
- `is_active = 1`.
- `source = mxmed_subscription_plan_price_dev_seed_v1`.

Esta convencion es solo para un seed DEV/local futuro.

### G) Reglas de unicidad y conflictos
Reglas:

- Debe existir maximo un precio activo vigente por `plan_code + billing_period + currency`.
- En v1 esta regla se validara en backend/QA, no solo por constraint SQL.

Errores:

- Si hay mas de un precio activo vigente: `pricing_configuration_conflict`.
- Si no hay precio activo vigente: `plan_price_not_configured`.
- Si la fuente falla: `pricing_source_unavailable`.

### H) Relacion con endpoint checkout-intents
El endpoint futuro consultara `subscription_plan_prices`.

Reglas:

- Si el plan es `free`, debe responder `plan_not_contractable` o equivalente en flujo de checkout pagado.
- Si no hay precio activo, no debe crear checkout intent.
- Si hay precio activo unico, copiara snapshot a `subscription_checkout_intents`:
  - `amount_cents`.
  - `currency`.
  - `price_source`.
  - `price_version`.

### I) Relacion con provider/webhook
Reglas:

- El provider adapter recibira el monto resuelto por MXMed.
- El webhook futuro validara `amount`/`currency` contra snapshot.
- No se activa suscripcion si `amount`/`currency` no coincide.
- El seed DEV/local no implementa provider ni webhook.

### J) Relacion con facturacion
Reglas:

- Precios en `subscription_plan_prices` no son factura.
- No contienen CFDI.
- No contienen datos fiscales.
- Facturacion se disena por separado.
- El snapshot de checkout/payment puede alimentar facturacion futura, pero no se implementa aqui.

### K) Fuera de alcance explicito
Esta microfase no:

- Inserta precios.
- Crea seed SQL.
- Ejecuta SQL.
- Modifica DB/schema.
- Modifica backend.
- Modifica frontend.
- Implementa checkout.
- Implementa provider adapter.
- Implementa webhook.
- Conecta facturacion.
- Activa capacidades.
- Decide precios reales/productivos.

### L) Siguiente microfase recomendada
Siguiente microfase recomendada:

- `DB/SPEC-Suscripciones-PlanPrices-DevSeedDraft-01`.

Objetivo:

- Crear un SQL draft conceptual DEV/local only para seeds de precios de prueba en `subscription_plan_prices`, sin crear SQL ejecutable y sin ejecutar SQL.

## Adenda PP-Decisiones 71 — Cierre del draft DEV/local de seeds de precios de suscripciones

### A) Microfase cerrada
Microfase cerrada:

- `DB/SPEC-Suscripciones-PlanPrices-DevSeedDraft-01`.

QA cerrada:

- `QA-Suscripciones-PlanPrices-DevSeedDraft-PendingDiff-01`.

Commit:

- `fa4e4a5 db(suscripciones): agrega draft seed dev de precios`.

Archivo:

- `modules/profiles/db/2026_06_22_seed_subscription_plan_prices_dev_draft.sql`.

### B) Proposito
El archivo es un draft conceptual DEV/local para un futuro seed de precios de prueba en:

- `subscription_plan_prices`.

Uso futuro:

- Probar resolucion server-side de precios.
- Probar `checkout-intents` en DEV/local.
- Mantener separados schema, seeds DEV/local y precios reales/productivos.

### C) Relacion con PP-Decisiones 70
El draft respeta PP-Decisiones 70:

- No mezcla seeds con schema.
- Es un archivo separado.
- Es `DEV/LOCAL ONLY`.
- Los precios son placeholders no productivos.
- No son precios reales ni comerciales aprobados.
- No deben usarse en produccion.
- `free` queda fuera de precios pagados v1.

### D) Contenido
Planes incluidos como placeholders DEV/local:

- `basic annual = 10000` centavos MXN.
- `standard annual = 20000` centavos MXN.
- `optimum annual = 30000` centavos MXN.
- `professional annual = 40000` centavos MXN.

Exclusiones:

- `free`.
- Mensualidades no decididas.
- Provider ids.
- Facturacion.
- Capacidades.
- Datos fiscales.

### E) Convenciones
Convenciones del draft:

- `price_source = subscription_plan_prices_dev_seed`.
- `price_version = mxmed-dev-pricing-2026-v1`.
- `source = mxmed_subscription_plan_price_dev_seed_v1`.
- `currency = MXN`.
- `valid_from = 2026-06-22 00:00:00`.
- `valid_until = NULL`.
- `is_active = 1`.
- `notes` indica `DEV/LOCAL placeholder` y no produccion.
- 4 UUIDs fijos distintos para reproducibilidad.

### F) QA del draft
La QA de pending diff confirmo:

- El archivo es `DRAFT ONLY`.
- El archivo es `DEV/LOCAL ONLY`.
- No existe SQL ejecutable final.
- Contiene `INSERT` conceptual solo a `subscription_plan_prices`.
- Incluye `basic annual`, `standard annual`, `optimum annual` y `professional annual`.
- Excluye `free` como fila seed.
- No contiene `ALTER`.
- No contiene `DROP`.
- No contiene `DELETE`.
- No contiene `TRUNCATE`.
- No contiene `UPDATE`.
- No contiene `CREATE TABLE`.
- No toca payment events/intents.
- No contiene datos sensibles.
- No contiene facturacion/capacidades operativas.
- No se ejecuto SQL.
- No se modifico DB/schema.
- `git diff --check` quedo limpio.

### G) Alcance explicito
Esta adenda no implica:

- SQL ejecutable creado.
- SQL ejecutado.
- Precios insertados.
- Seeds ejecutados.
- Cambios DB/schema.
- Cambios backend/frontend.
- Endpoint `checkout-intents` implementado.
- Provider adapter implementado.
- Webhook implementado.
- Facturacion conectada.
- Capacidades activadas.
- Perfil publico o SEO tocados.

### H) Pendientes posteriores
Pendientes:

1. Convertir el draft DEV/local a SQL ejecutable: `modules/profiles/db/2026_06_22_seed_subscription_plan_prices_dev.sql`.
2. Hacer QA del SQL ejecutable DEV/local.
3. Ejecutar el seed en local/dev en microfase autorizada.
4. Hacer QA post-seed:
   - 4 filas esperadas.
   - `active_plan_prices = 4`.
   - `free` excluido.
   - `price_version` correcta.
   - `price_source` correcto.
5. Documentar cierre de ejecucion local/dev del seed.
6. Disenar repositorio/servicio de resolucion de precios.
7. Validar un solo precio activo vigente por plan/periodo/moneda.
8. Integrar resolucion de precio con `checkout-intents` futuro.
9. Mantener provider/webhook/facturacion/capacidades fuera de esta fase.
10. Decidir precios reales/productivos en fase comercial separada.

### I) Siguiente microfase recomendada
Siguiente microfase recomendada:

- `DB-Suscripciones-PlanPrices-DevSeedExecutableSql-Readiness-01`.

Objetivo:

- Validar readiness para convertir el draft DEV/local de seeds de precios en SQL ejecutable versionado, sin crear todavia el SQL ejecutable final y sin ejecutar SQL.

## Adenda PP-Decisiones 72 — Cierre de ejecución local/dev del seed de precios de suscripciones

### A) Microfases cerradas
Microfases cerradas:

- `DB-Suscripciones-PlanPrices-DevSeedExecutableSql-Readiness-01`.
- `DB-Suscripciones-PlanPrices-DevSeedExecutableSql-Create-01`.
- `DB-Suscripciones-PlanPrices-DevSeedExecutableSql-ApplyLocalDev-Readiness-01`.
- `DB-Suscripciones-PlanPrices-DevSeedExecutableSql-ApplyLocalDev-01`.
- `QA-Suscripciones-PlanPrices-DevSeedExecutableSql-PostApplyLocalDev-01`.

Commit del SQL ejecutable DEV/local:

- `aad9d0a db(suscripciones): agrega seed dev ejecutable de precios`.

Archivo:

- `modules/profiles/db/2026_06_22_seed_subscription_plan_prices_dev.sql`.

### B) Alcance del seed
El seed cerrado en esta adenda:

- Es `DEV/LOCAL ONLY`.
- No es seed productivo.
- No contiene precios reales.
- No contiene precios comerciales aprobados.
- Sirve solo para pruebas DEV/local futuras de resolucion server-side de precio y `checkout-intents`.
- No incluye `free` como fila.
- No incluye mensualidades no decididas.
- No incluye provider ids.
- No incluye facturacion.
- No incluye capacidades.
- No incluye datos fiscales.
- No implementa checkout.

### C) Ejecucion local/dev
El seed fue ejecutado en DB local/dev `mxmed` con el comando autorizado:

```bash
mysql -u root mxmed < modules/profiles/db/2026_06_22_seed_subscription_plan_prices_dev.sql
```

Estado antes de ejecutar:

- `subscription_plan_prices = 0`.
- `active_plan_prices = 0`.
- `dev_seed_price_rows = 0`.

Estado despues de ejecutar:

- `subscription_plan_prices = 4`.
- `active_plan_prices = 4`.
- `dev_seed_price_rows = 4`.
- `free_seed_rows = 0`.
- `distinct_seed_uuids = 4`.

### D) Filas insertadas
Filas DEV/local insertadas:

- `basic annual = 10000` centavos MXN.
- `standard annual = 20000` centavos MXN.
- `optimum annual = 30000` centavos MXN.
- `professional annual = 40000` centavos MXN.

Convenciones aplicadas:

- `currency = MXN`.
- `price_source = subscription_plan_prices_dev_seed`.
- `price_version = mxmed-dev-pricing-2026-v1`.
- `source = mxmed_subscription_plan_price_dev_seed_v1`.
- `valid_from = 2026-06-22 00:00:00`.
- `valid_until = NULL`.
- `is_active = 1`.
- 4 UUIDs fijos distintos.

### E) QA post-ejecucion
La QA post-ejecucion local/dev confirmo:

- `subscription_plan_prices = 4`.
- `active_plan_prices = 4`.
- `dev_seed_price_rows = 4`.
- `free_seed_rows = 0`.
- `distinct_seed_uuids = 4`.
- Una fila activa por plan/periodo/moneda.
- `unexpected_billing_period_rows = 0`.
- `unexpected_currency_rows = 0`.
- Schema/indices siguen correctos.
- Sin FKs reales.
- Sin `CHECK`.
- Sin `JSON`.
- Sin datos sensibles.
- Sin facturacion/capacidades operativas.
- No se ejecuto de nuevo el seed durante QA.
- No se ejecuto SQL de escritura durante QA.
- No se modificaron archivos durante ejecucion/QA.

### F) Impacto no esperado descartado
Conteos base conservados:

- `subscription_checkout_intents = 0`.
- `subscription_payment_intents = 0`.
- `subscription_payment_events = 0`.
- `profile_subscriptions = 3`.
- `subscription_contract_acceptances = 3`.
- `subscription_write_idempotency_keys = 5`.

### G) Alcance explicito
Este cierre no implica:

- Checkout productivo implementado.
- Resolucion backend de precios implementada.
- Endpoint `checkout-intents` implementado.
- Provider adapter implementado.
- Webhook implementado.
- Activacion post-pago implementada.
- Facturacion conectada.
- Capacidades activadas.
- `PublicProfilePlanCapabilities` tocado.
- Perfil publico o SEO tocados.
- Precios reales/productivos aprobados.

### H) Pendientes posteriores
Pendientes:

1. Disenar repositorio/servicio de resolucion de precios server-side.
2. Validar un solo precio activo vigente por plan/periodo/moneda.
3. Integrar resolucion de precios con endpoint futuro `checkout-intents`.
4. Disenar/implementar manejo de errores:
   - `plan_price_not_configured`.
   - `pricing_configuration_conflict`.
   - `pricing_source_unavailable`.
5. Validar `amount/currency` contra snapshot en webhook futuro.
6. Mantener provider/webhook/facturacion/capacidades fuera hasta microfase explicita.
7. Decidir precios reales/productivos en fase comercial separada.

### I) Siguiente microfase recomendada
Siguiente microfase recomendada:

- `BE/SPEC-Suscripciones-CheckoutIntent-PriceResolverDesign-01`.

Objetivo:

- Disenar documentalmente el repositorio/servicio server-side que resolvera el precio vigente desde `subscription_plan_prices` para `checkout-intents` futuros, sin implementar backend todavia.

## Adenda PP-Decisiones 73 — Diseño del resolver server-side de precios para checkout-intents

### A) Proposito
Se define el diseno documental del futuro resolver server-side de precios que consultara `subscription_plan_prices` para determinar el precio vigente y copiarlo como snapshot en `subscription_checkout_intents`.

Reglas base:

- El cliente no enviara `amount_cents`, `currency`, `price_source` ni `price_version`.
- El servidor resolvera esos campos antes de crear el checkout intent.
- `subscription_plans` seguira siendo catalogo tecnico.
- `subscription_plan_prices` sera la fuente server-side de precio.
- El provider adapter recibira el precio ya resuelto por MXMed.
- El webhook futuro debera validar `amount/currency` contra el snapshot antes de activar suscripcion.

### B) Componentes futuros propuestos
Repositorio conceptual futuro:

- `SubscriptionPlanPriceRepository`.

Responsabilidad:

- Consultar `subscription_plan_prices`.
- Filtrar por `plan_code`.
- Filtrar por `billing_period`.
- Filtrar por `currency`.
- Filtrar por `is_active = 1`.
- Filtrar por `deleted_at IS NULL`.
- Filtrar por `valid_from <= now`.
- Filtrar por `valid_until IS NULL OR valid_until > now`.
- Ordenar y devolver candidatos vigentes.
- No decidir negocio por si solo.
- No crear checkout intents.
- No activar suscripciones.
- No llamar al provider.

Servicio conceptual futuro:

- `SubscriptionPlanPriceResolverService`.

Responsabilidad:

- Validar que el plan sea contratable.
- Bloquear `free` para checkout pagado.
- Validar `billing_period`.
- Pedir candidatos al repositorio.
- Resolver exactamente un precio vigente.
- Retornar snapshot normalizado:
  - `plan_code`.
  - `billing_period`.
  - `amount_cents`.
  - `currency`.
  - `price_source`.
  - `price_version`.
  - `valid_from`.
  - `valid_until`.
  - `price_uuid`.
- Mapear errores conceptuales.
- No crear checkout intent.
- No escribir payment intent.
- No activar plan.
- No conectar capacidades.

### C) Metodo conceptual
Firma documental sugerida:

```text
resolveForCheckout(string entityType, string entityId, string planCode, string billingPeriod, ?string currency = 'MXN', ?DateTimeImmutable now = null): ResolvedSubscriptionPlanPrice
```

Aclaraciones:

- `entityType/entityId` pueden usarse para contexto/auditoria futura, pero el precio v1 se resuelve por plan/periodo/moneda.
- La moneda v1 sera `MXN`.
- `now` permite pruebas deterministas.
- El resultado sera inmutable para el checkout intent.
- El snapshot guardado en `subscription_checkout_intents` no debe cambiar aunque despues cambien precios.

### D) Reglas de resolucion
Reglas del resolver:

1. Rechazar `free` para checkout pagado:
   - Error conceptual: `plan_not_contractable` o equivalente ya documentado para checkout.
2. Validar plan/billing contra `subscription_plans`:
   - El plan debe existir.
   - Debe estar activo.
   - Debe corresponder al billing period solicitado.
   - `subscription_plans` no aporta precio.
3. Consultar precio vigente en `subscription_plan_prices`:
   - `plan_code = planCode`.
   - `billing_period = billingPeriod`.
   - `currency = MXN`.
   - `is_active = 1`.
   - `deleted_at IS NULL`.
   - `valid_from <= now`.
   - `valid_until IS NULL OR valid_until > now`.
4. Si no hay precio:
   - `422 plan_price_not_configured`.
5. Si hay mas de un precio vigente:
   - `500 pricing_configuration_conflict`.
6. Si hay error tecnico consultando la fuente:
   - `503 pricing_source_unavailable`.
7. Si hay exactamente uno:
   - Devolver snapshot server-side.

### E) Snapshot para checkout-intents
El endpoint futuro:

```text
POST /api/subscriptions/index.php/entities/{entity_type}/{entity_id}/checkout-intents
```

debera copiar desde el resolver hacia `subscription_checkout_intents`:

- `plan_code`.
- `billing_period`.
- `amount_cents`.
- `currency`.
- `price_source`.
- `price_version`.

Si la tabla/DTO futuro lo contempla:

- `price_uuid`.
- `price_valid_from`.
- `price_valid_until`.

El snapshot:

- Debe ser auditado.
- No debe recalcularse despues para ese checkout intent.
- Debe usarse para comparar contra payment intent/provider/webhook.
- Debe permanecer aunque la tabla de precios cambie posteriormente.

### F) Relacion con seed DEV/local actual
Estado y alcance del seed actual:

- El seed DEV/local dejo 4 precios activos de prueba.
- Estos precios permiten probar el resolver futuro en DEV/local.
- Son placeholders no productivos.
- No deben usarse en produccion.
- `free` sigue fuera.
- No hay mensualidades todavia.
- No hay precios comerciales aprobados.

Estado DB local/dev actual:

- `subscription_plan_prices = 4`.
- `active_plan_prices = 4`.
- `dev_seed_price_rows = 4`.
- `free_seed_rows = 0`.

### G) Relacion con idempotencia y lock
El resolver:

- No reemplaza idempotencia.
- No reemplaza lock.

El endpoint `checkout-intents` futuro debera seguir usando:

- `Idempotency-Key` con operacion conceptual `subscriptions.checkout_intent.create`.
- Lock conceptual `mxmed:subscriptions:{entity_type}:{entity_id}:checkout_create`.

Reglas de flujo:

- El precio debe resolverse dentro del flujo server-side controlado, antes de crear payment intent.
- Si el checkout intent se reintenta con la misma idempotency key, el snapshot debe ser estable por replay.
- Si se usa otra key y el precio cambio, la politica debera definirse en la microfase de implementacion del endpoint, no aqui.

### H) Relacion con provider/webhook
Reglas futuras:

- El provider no sera fuente canonica unica.
- El provider adapter recibira `amount_cents` y `currency` desde MXMed.
- El payment intent debera guardar/usar el snapshot del checkout intent.
- El webhook futuro debera validar:
  - amount recibido.
  - currency recibida.
  - provider status.
  - relacion con checkout intent.
- No debe activarse suscripcion si `amount/currency` no coincide con snapshot.

### I) Relacion con facturacion y capacidades
El resolver:

- No implementa facturacion.
- No contiene CFDI.
- No contiene datos fiscales.
- No activa capacidades.
- No toca `PublicProfilePlanCapabilities`.

Separacion de responsabilidades:

- La facturacion se disenara por separado.
- Las capacidades se conectaran despues de activacion post-pago en fase separada.

### J) Errores conceptuales
Errores esperados:

- `plan_not_contractable`: para `free` o planes que no pueden contratarse por checkout pagado.
- `billing_period_invalid`: para periodos no soportados o no activos.
- `plan_price_not_configured`: plan valido, pero sin precio vigente.
- `pricing_configuration_conflict`: mas de un precio activo vigente para plan/periodo/moneda.
- `pricing_source_unavailable`: error tecnico al consultar fuente de pricing.

Errores opcionales documentales:

- `plan_not_found`.
- `plan_inactive`.

Estos ultimos podrian normalizarse dentro de `plan_not_contractable` o errores existentes del endpoint `checkout-intents`.

### K) Fuera de alcance
Esta adenda no implementa:

- Repositorio PHP.
- Servicio PHP.
- Endpoint `checkout-intents`.
- Provider adapter.
- Webhook.
- Activacion post-pago.
- Facturacion.
- Capacidades.
- Perfil publico.
- SEO.
- Cambios de DB/schema.
- Nuevos SQL.
- Ejecucion SQL.
- Precios reales/productivos.

### L) Pendientes posteriores
Pendientes:

1. Crear readiness para implementacion del repositorio/servicio.
2. Implementar `SubscriptionPlanPriceRepository`.
3. Implementar `SubscriptionPlanPriceResolverService`.
4. Agregar pruebas/QA para:
   - precio encontrado.
   - precio faltante.
   - conflicto por duplicidad vigente.
   - `free` excluido.
   - billing invalido.
   - source unavailable simulado.
5. Integrar resolver con endpoint futuro `checkout-intents`.
6. Guardar snapshot en `subscription_checkout_intents`.
7. Validar snapshot contra payment intent/provider/webhook.
8. Disenar activacion post-pago.
9. Mantener facturacion/capacidades fuera hasta microfase explicita.
10. Definir precios reales/productivos en fase comercial separada.

### M) Siguiente microfase recomendada
Siguiente microfase recomendada:

- `BE/SPEC-Suscripciones-CheckoutIntent-PriceResolverImplementation-Readiness-01`.

Objetivo:

- Validar readiness tecnica para implementar repositorio/servicio de resolucion server-side de precios, sin escribir codigo todavia.

## Adenda PP-Decisiones 74 — Cierre de implementación mínima del resolver server-side de precios

### A) Microfases cerradas
Microfases cerradas:

- `BE/SPEC-Suscripciones-CheckoutIntent-PriceResolverDesign-01`.
- `BE/SPEC-Suscripciones-CheckoutIntent-PriceResolverImplementation-Readiness-01`.
- `BE-Suscripciones-CheckoutIntent-PriceResolverRepositoryService-Create-01`.
- `QA-Suscripciones-CheckoutIntent-PriceResolverRepositoryService-PostPush-01`.

Commit de implementacion:

- `64d5091 feat(suscripciones): agrega resolver de precios checkout`.

Archivos creados:

- `modules/subscriptions/repositories/SubscriptionPlanPriceRepository.php`.
- `modules/subscriptions/services/SubscriptionPlanPriceResolverService.php`.

### B) Repositorio implementado
Repositorio:

- Clase: `SubscriptionPlanPriceRepository`.
- Metodo principal: `findActiveCandidates(...)`.
- Consulta solo `subscription_plan_prices`.

Filtros aplicados:

- `plan_code`.
- `billing_period`.
- `currency`.
- `is_active = 1`.
- `deleted_at IS NULL`.
- Vigencia por `valid_from / valid_until`.

Comportamiento:

- Retorna candidatos como arrays asociativos.
- Usa `LIMIT 2` para permitir detectar conflictos sin cargar mas filas de las necesarias.
- No escribe DB.
- No consulta checkout/payment.
- No activa suscripciones.
- No llama provider.

### C) Servicio implementado
Servicio:

- Clase: `SubscriptionPlanPriceResolverService`.
- Metodo principal: `resolveForCheckout(...)`.

Normaliza:

- `planCode`.
- `billingPeriod`.
- `currency`.

Reglas implementadas:

- Bloquea `free`.
- Permite por ahora `annual`.
- Resuelve precio vigente via repositorio.

Snapshot devuelto:

- `plan_code`.
- `billing_period`.
- `amount_cents`.
- `currency`.
- `price_source`.
- `price_version`.
- `valid_from`.
- `valid_until`.
- `price_uuid`.
- `source`.

Alcance:

- No crea checkout intent.
- No crea payment intent.
- No activa suscripcion.
- No conecta capacidades.

### D) Errores conceptuales implementados/validados
Errores conceptuales:

- `plan_not_contractable`.
- `billing_period_invalid`.
- `plan_price_not_configured`.
- `pricing_configuration_conflict`.
- `pricing_source_unavailable`.

### E) QA post-push
La QA post-push confirmo:

- Ambas clases existen y estan trackeadas.
- `php -l` PASS en ambas clases.
- QA estatica PASS:
  - sin `INSERT/UPDATE/DELETE/TRUNCATE/ALTER/DROP/CREATE TABLE`.
  - sin alcance indebido hacia checkout/payment, contractuales, capacidades, provider, webhook, facturacion o SEO.
- `api/subscriptions/index.php` no integra estas clases ni `checkout-intents`.
- QA funcional con `php -r` PASS:
  - `standard annual` -> `20000 MXN`.
  - `basic annual` -> `10000 MXN`.
  - `optimum annual` -> `30000 MXN`.
  - `professional annual` -> `40000 MXN`.
  - `free annual` -> `plan_not_contractable`.
  - `standard monthly` -> `billing_period_invalid`.
  - `nonexistent annual` -> `plan_price_not_configured`.
- No hubo writes DB durante QA.

### F) DB local/dev validada
Estado DB local/dev validado:

- `subscription_plan_prices = 4`.
- `active_plan_prices = 4`.
- `dev_seed_price_rows = 4`.
- `free_seed_rows = 0`.
- Checkout/payment: `0 / 0 / 0`.
- Contractuales: `3 / 3 / 5`.

### G) Alcance explicito
Este cierre no implica:

- Endpoint `checkout-intents` implementado.
- Integracion en `api/subscriptions/index.php`.
- Creacion de checkout intent.
- Creacion de payment intent.
- Provider adapter.
- Webhook.
- Activacion post-pago.
- Facturacion.
- Capacidades.
- Perfil publico.
- SEO.
- Cambios de DB/schema.
- Nuevos SQL.
- Precios reales/productivos.

### H) Pendientes posteriores
Pendientes:

1. Disenar readiness para integracion controlada del resolver en endpoint futuro `checkout-intents`.
2. Implementar endpoint `POST /api/subscriptions/index.php/entities/{entity_type}/{entity_id}/checkout-intents`.
3. Integrar auth/session_scope real.
4. Integrar idempotencia con operacion `subscriptions.checkout_intent.create`.
5. Integrar lock `mxmed:subscriptions:{entity_type}:{entity_id}:checkout_create`.
6. Copiar snapshot del resolver a `subscription_checkout_intents`.
7. Disenar provider adapter.
8. Disenar webhook y validar `amount/currency` contra snapshot.
9. Disenar activacion post-pago.
10. Mantener facturacion/capacidades fuera hasta microfase explicita.
11. Definir precios reales/productivos en fase comercial separada.

### I) Siguiente microfase recomendada
Siguiente microfase recomendada:

- `BE/SPEC-Suscripciones-CheckoutIntent-EndpointImplementation-Readiness-01`.

Objetivo:

- Validar readiness para iniciar implementacion controlada del endpoint `checkout-intents`, integrando el resolver de precios, idempotencia y lock, sin implementar todavia el endpoint.

## Adenda PP-Decisiones 75 — Decisión del primer write de checkout-intents

### A) Problema a resolver
Antes de implementar el endpoint:

- `POST /api/subscriptions/index.php/entities/{entity_type}/{entity_id}/checkout-intents`.

se decide el alcance exacto del primer write futuro.

Alternativas evaluadas:

1. Crear `subscription_checkout_intents` mas aceptacion contractual `accepted_pending_payment`.
2. Crear `subscription_checkout_intents` mas aceptacion contractual `accepted_pending_payment` mas `subscription_payment_intents` placeholder.

El endpoint `checkout-intents` no debe:

- activar `profile_subscriptions`;
- marcar aceptacion como `accepted` final;
- simular pago confirmado;
- activar capacidades;
- tocar facturacion;
- depender todavia de provider real.

### B) Decision
Para la primera implementacion controlada del endpoint `checkout-intents`, MXMed creara solo:

1. Una aceptacion contractual con status `accepted_pending_payment`.
2. Un registro en `subscription_checkout_intents`.

No creara todavia:

- `subscription_payment_intents`;
- `subscription_payment_events`;
- `profile_subscriptions`;
- activacion post-pago;
- capacidades;
- facturacion.

Razon:

- `subscription_checkout_intents` representa la intencion de contratar y preserva el snapshot contractual/comercial.
- `subscription_payment_intents` debe esperar a que exista provider adapter o una decision explicita de payment placeholder.
- Crear payment intent sin provider puede introducir estado artificial dificil de auditar.
- La separacion mantiene el primer write pequeno, testeable y reversible en DEV/local.
- El pago sigue siendo una fase posterior.

### C) Estado inicial recomendado
El status inicial recomendado para `subscription_checkout_intents` es:

- `pending_payment`.

Motivo:

- La aceptacion contractual ya se recibio en el request y queda registrada como `accepted_pending_payment`.
- El checkout queda esperando inicializacion de pago/provider en fase posterior.
- No se considera `paid`, `activated` ni `payment_processing`.

Aunque el schema tiene default `pending_contract`, para este primer write el contrato ya fue aceptado dentro del flujo, por lo que el registro puede iniciar en `pending_payment` de forma explicita.

### D) Orden conceptual del primer write
Orden futuro, sin implementar en esta adenda:

1. Validar auth/session_scope.
2. Validar entidad.
3. Validar que no exista suscripcion activa.
4. Validar payload.
5. Validar contrato.
6. Validar acceptance source.
7. Resolver precio server-side con `SubscriptionPlanPriceResolverService`.
8. Procesar `Idempotency-Key` con operacion `subscriptions.checkout_intent.create`.
9. Adquirir lock `mxmed:subscriptions:{entity_type}:{entity_id}:checkout_create`.
10. Revalidar dentro del lock:
    - no suscripcion activa;
    - no checkout pendiente incompatible, si aplica.
11. Crear aceptacion contractual:
    - status `accepted_pending_payment`;
    - `subscription_id = NULL`.
12. Crear `subscription_checkout_intents`:
    - status `pending_payment`;
    - snapshot plan/billing/precio/contrato/aceptacion.
13. Marcar idempotencia completed con respuesta estable.
14. Liberar lock.
15. Responder `201` con checkout intent y snapshot.

### E) Snapshot minimo de checkout intent
`subscription_checkout_intents` debe guardar:

- `entity_type`;
- `entity_id`;
- `doctor_id`, `profile_id` y `user_id`, segun contexto disponible;
- `plan_code`;
- `billing_period`;
- `amount_cents`;
- `currency`;
- `price_source`;
- `price_version`;
- `contract_acceptance_uuid`;
- `contract_version`;
- `contract_hash`;
- `contract_snapshot_url`;
- `status = pending_payment`;
- `source`;
- timestamps.

Si el schema incluye campos provider pero aun no se usan:

- dejarlos `NULL`;
- no inventar provider;
- no crear payment intent placeholder.

### F) Idempotencia
La operacion idempotente futura sera:

- `subscriptions.checkout_intent.create`.

Reglas:

- Replay con misma key y mismo payload debe devolver el mismo checkout intent.
- Misma key con payload distinto debe responder `idempotency_key_reused_with_different_payload`.
- El request hash debe cubrir:
  - `entity_type`;
  - `entity_id`;
  - `plan_code`;
  - `billing_period`;
  - contract `version`, `hash` y `snapshot_url`;
  - acceptance `source`.

La politica sobre incluir precio resuelto en el hash debe decidirse con cuidado. Recomendacion: el replay estable se basa en el snapshot guardado del primer intento; si el precio cambia despues, no debe alterar replay del mismo `Idempotency-Key`.

### G) Lock
El lock futuro sera:

- `mxmed:subscriptions:{entity_type}:{entity_id}:checkout_create`.

Reglas:

- Evita dos checkout intents competidores para la misma entidad.
- Se libera en `finally`.
- Requiere revalidacion dentro del lock.
- No reemplaza idempotencia.

### H) Relacion con aceptacion contractual
La aceptacion contractual del primer write:

- se crea con status `accepted_pending_payment`;
- deja `subscription_id = NULL`;
- no activa plan;
- se enlaza al checkout intent mediante `contract_acceptance_uuid`.

Si el checkout expira, se cancela o falla, la aceptacion queda como evidencia del intento. Si el pago se confirma en fase posterior, se usara esa aceptacion para activacion sin duplicarla.

### I) Relacion con payment intents
`subscription_payment_intents` queda fuera del primer write.

Se disenara cuando exista:

- provider adapter;
- o decision explicita de placeholder sin provider.

No se escribira payment intent en esta primera implementacion. `subscription_payment_events` queda reservado para webhooks/event ledger. No se simula pago ni provider.

### J) Errores conceptuales del primer write
Errores esperados:

- `invalid_json`;
- `unauthenticated`;
- `forbidden`;
- `entity_not_found`;
- `active_subscription_exists`;
- `checkout_already_pending`;
- `idempotency_key_invalid`;
- `idempotency_key_reused_with_different_payload`;
- `request_already_processing`;
- `subscription_checkout_lock_timeout`;
- `plan_not_contractable`;
- `billing_period_invalid`;
- `contract_invalid`;
- `acceptance_source_invalid`;
- `plan_price_not_configured`;
- `pricing_configuration_conflict`;
- `pricing_source_unavailable`;
- `checkout_intent_create_failed`.

`checkout_provider_unavailable` no debe usarse todavia si el primer write no llama provider.

### K) QA futura minima
QA futura esperada:

1. `201` con `standard annual` y snapshot `20000 MXN`.
2. Replay misma key/mismo payload devuelve mismo checkout intent.
3. Misma key/payload distinto devuelve `409`.
4. `free` devuelve `plan_not_contractable`.
5. `monthly` devuelve `billing_period_invalid`.
6. Sin sesion real devuelve `403`.
7. Headers QA sin sesion devuelven `403`.
8. `local_dev_open` sin sesion devuelve `403`.
9. Entidad con suscripcion activa devuelve `active_subscription_exists`.
10. No se crea `profile_subscriptions`.
11. Se crea una aceptacion `accepted_pending_payment`.
12. Se crea un `subscription_checkout_intents`.
13. No se crea `subscription_payment_intents`.
14. No se crea `subscription_payment_events`.
15. No se activan capacidades.
16. Lock liberado.
17. Idempotencia completed/failed segun caso.

### L) Fuera de alcance
Esta adenda no implementa:

- endpoint;
- rutas;
- repositorio checkout intent;
- servicio checkout intent;
- payment intent;
- provider adapter;
- webhook;
- activacion post-pago;
- facturacion;
- capacidades;
- perfil publico;
- SEO;
- cambios DB/schema;
- SQL nuevo;
- ejecucion SQL.

### M) Pendientes posteriores
Pendientes:

1. Readiness para implementar repositorio/servicio de checkout intent.
2. Implementar repositorio para insertar `subscription_checkout_intents`.
3. Ajustar o extender repositorio de aceptacion contractual para permitir status `accepted_pending_payment`.
4. Disenar servicio de creacion de checkout intent.
5. Integrar resolver de precios.
6. Integrar idempotencia con operation `subscriptions.checkout_intent.create`.
7. Integrar lock `checkout_create`.
8. Implementar ruta endpoint `checkout-intents`.
9. QA funcional 201/replay/409/auth/precio.
10. Disenar provider adapter.
11. Disenar payment intent.
12. Disenar webhook.
13. Disenar activacion post-pago.
14. Mantener facturacion/capacidades en fases separadas.

### N) Siguiente microfase recomendada
Siguiente microfase recomendada:

- `BE/SPEC-Suscripciones-CheckoutIntent-StorageRepositoryDesign-01`.

Objetivo:

- Disenar documentalmente el repositorio/servicio minimo para insertar `subscription_checkout_intents` y enlazar aceptacion `accepted_pending_payment`, sin implementar codigo todavia.

## Adenda PP-Decisiones 76 — Diseño de storage repository para checkout-intents

### A) Proposito
Esta adenda define el diseno minimo futuro para crear checkout-intents sin provider, sin payment intent y sin activacion.

El alcance documental se limita a storage y orquestacion futura para:

- crear `subscription_checkout_intents`;
- enlazar aceptacion contractual `accepted_pending_payment`;
- preservar snapshot de precio y contrato;
- mantener el flujo checkout-first.

No reabre checkout-first, storage checkout/payment, timing contractual, fuente server-side de precio, resolver de precios, idempotencia ni lock.

### B) Componentes futuros sugeridos
Repositorio futuro:

- `SubscriptionCheckoutIntentRepository`.

Archivo futuro sugerido:

- `modules/subscriptions/repositories/SubscriptionCheckoutIntentRepository.php`.

Servicio futuro:

- `CreateSubscriptionCheckoutIntentService`.

Archivo futuro sugerido:

- `modules/subscriptions/services/CreateSubscriptionCheckoutIntentService.php`.

### C) Repositorio futuro
`SubscriptionCheckoutIntentRepository` tendra responsabilidad estricta de storage sobre `subscription_checkout_intents`.

Debe encargarse de:

- Insertar en `subscription_checkout_intents`.
- Buscar checkout intent por `uuid`.
- Buscar checkout pendiente por entidad, si aplica.
- Persistir el snapshot recibido ya resuelto.
- Retornar fila persistida o DTO minimo.

Metodos conceptuales sugeridos:

- `create(array $snapshot): array`.
- `findByUuid(string $uuid): ?array`.
- `findPendingByEntity(string $entityType, string $entityId): ?array`.
- `findPendingByEntityPlanAndBilling(string $entityType, string $entityId, string $planCode, string $billingPeriod): ?array`.

No debe:

- Crear `subscription_payment_intents`.
- Crear `subscription_payment_events`.
- Crear `profile_subscriptions`.
- Llamar provider.
- Activar capacidades.
- Resolver precios por si mismo.
- Manejar idempotencia por si mismo.
- Manejar lock por si mismo.
- Validar sesion/auth por si mismo.
- Decidir plan comercial por si mismo.

### D) Servicio futuro
`CreateSubscriptionCheckoutIntentService` sera la capa de orquestacion del primer write de checkout-intents.

Debe orquestar:

- validacion de entidad;
- validacion de plan/billing;
- resolucion server-side de precio;
- creacion de aceptacion contractual `accepted_pending_payment`;
- creacion de checkout intent;
- integracion futura con idempotencia;
- integracion futura con lock.

Dependencias conceptuales:

- `SubscriptionPlanPriceResolverService`;
- `SubscriptionCheckoutIntentRepository`;
- repositorio/servicio de aceptacion contractual extendido;
- `SubscriptionWriteIdempotencyService`;
- `SubscriptionEntityWriteLockService`.

No debe:

- Crear payment intent.
- Crear payment event.
- Activar `profile_subscriptions`.
- Conectar provider.
- Conectar webhook.
- Conectar facturacion.
- Activar capacidades.
- Tocar perfil publico/SEO.

### E) Snapshot minimo para `subscription_checkout_intents`
El checkout intent futuro debe guardar snapshot con:

- `uuid`;
- `entity_type`;
- `entity_id`;
- `doctor_id`;
- `profile_id`;
- `user_id`;
- `plan_code`;
- `billing_period`;
- `amount_cents`;
- `currency`;
- `price_source`;
- `price_version`;
- `contract_acceptance_uuid`;
- `contract_version`;
- `contract_hash`;
- `contract_snapshot_url`;
- `status = pending_payment`;
- `source`;
- `idempotency_key_hash`, si el schema lo permite;
- `request_hash`, si el schema lo permite;
- `created_at`;
- `updated_at`;
- `deleted_at = NULL`.

Reglas:

- `amount_cents`, `currency`, `price_source` y `price_version` vienen del resolver server-side.
- El cliente no envia precio canonico.
- `contract_acceptance_uuid` enlaza el checkout intent con la aceptacion contractual.
- El status inicial siempre es `pending_payment`.

### F) Aceptacion contractual `accepted_pending_payment`
Este flujo requiere crear una fila en `subscription_contract_acceptances` con:

- `status = accepted_pending_payment`;
- `subscription_id = NULL`;
- campos contractuales completos;
- `contract_version`;
- `contract_hash`;
- `contract_snapshot_url`;
- `source = checkout_intent`;
- asociacion posterior desde `subscription_checkout_intents.contract_acceptance_uuid`.

Si el repositorio/servicio actual de aceptacion contractual solo soporta `accepted`, queda pendiente extenderlo. No se crea `profile_subscriptions` en esta etapa. La aceptacion existe antes del pago, pero no equivale a suscripcion activa.

### G) Pending checkout / `checkout_already_pending`
Decision conceptual:

- Si ya existe un checkout intent `pending_payment` para la misma entidad, plan y billing, y la idempotencia corresponde al mismo request, debe resolverse como replay estable en la capa de idempotencia.
- Si no corresponde al mismo request, debe evitar duplicado y devolver `checkout_already_pending`.
- Si existe checkout pending incompatible para la misma entidad, debe devolver `checkout_already_pending`.

No se debe:

- cerrar automaticamente checkout pendientes;
- sobrescribir automaticamente checkout pendientes;
- crear un segundo pending concurrente para la misma intencion contractual.

### H) Idempotencia
El servicio futuro debe integrarse con:

- `subscriptions.checkout_intent.create`.

`SubscriptionCheckoutIntentRepository` no debe conocer ni manejar idempotencia directamente.

La idempotencia queda en capa service/orquestacion, usando el componente existente o una extension controlada. Debe conservar:

- replay estable;
- bloqueo de payload distinto;
- `request_hash`;
- `idempotency_key_hash`, si aplica.

### I) Lock
El servicio futuro debe ejecutarse dentro del lock:

- `mxmed:subscriptions:{entity_type}:{entity_id}:checkout_create`.

`SubscriptionCheckoutIntentRepository` no debe manejar lock directamente. El lock queda en capa service/orquestacion.

Debe evitar:

- doble checkout pending;
- doble aceptacion pending payment;
- carreras con keys distintas.

### J) Errores conceptuales
Errores por storage:

- `checkout_intent_create_failed`.
- `checkout_already_pending`.

Errores por contrato:

- `contract_acceptance_create_failed`.

Errores por idempotencia:

- `idempotency_key_reused_with_different_payload`.
- `request_already_processing`.

Errores por lock:

- `subscription_checkout_lock_timeout`.

Errores por pricing:

- `plan_not_contractable`.
- `billing_period_invalid`.
- `plan_price_not_configured`.
- `pricing_configuration_conflict`.
- `pricing_source_unavailable`.

Errores por suscripcion activa:

- `active_subscription_exists`.

### K) Fuera de alcance
Esta adenda no implementa:

- repositorio;
- servicio;
- endpoint;
- rutas;
- SQL;
- migraciones;
- DB/schema;
- provider;
- payment intents;
- payment events;
- webhook;
- activacion post-pago;
- `profile_subscriptions`;
- facturacion;
- capacidades;
- conexion con `PublicProfilePlanCapabilities`;
- perfil publico;
- SEO;
- frontend.

### L) Pendientes posteriores
Pendientes:

1. Readiness para implementar repositorio checkout intent.
2. Implementar `SubscriptionCheckoutIntentRepository`.
3. Extender aceptacion contractual para `accepted_pending_payment`, si el componente actual no lo soporta.
4. Implementar `CreateSubscriptionCheckoutIntentService`.
5. Integrar resolver de precios.
6. Integrar idempotencia checkout.
7. Integrar lock checkout.
8. Integrar endpoint `checkout-intents`.
9. QA funcional `201`.
10. QA replay idempotente.
11. QA payload distinto `409`.
12. QA auth/forbidden.
13. QA `active_subscription_exists`.
14. QA `checkout_already_pending`.
15. QA errores de precio.
16. Disenar provider adapter.
17. Disenar payment intent.
18. Disenar webhook.
19. Disenar activacion post-pago.

### M) Siguiente microfase recomendada
Siguiente microfase recomendada:

- `BE/SPEC-Suscripciones-CheckoutIntent-StorageRepositoryImplementation-Readiness-01`.

Objetivo:

- Validar readiness tecnica para implementar el repositorio/servicio de storage de checkout-intents, sin escribir codigo todavia.

## Adenda PP-Decisiones 77 — Readiness de implementación del storage repository para checkout-intents

### A) Proposito de la readiness
Esta adenda valida la readiness tecnica para implementar posteriormente el storage repository-service de checkout-intents, sin escribir codigo todavia.

No cambia la decision de PP-Decisiones 76. La decision cerrada se mantiene: el primer write futuro crea `subscription_checkout_intents` y una aceptacion contractual `accepted_pending_payment`; no crea `subscription_payment_intents`, `subscription_payment_events` ni `profile_subscriptions`.

### B) Estado de componentes existentes
Estado observado en inspeccion read-only:

- Schema de `subscription_checkout_intents`: existe un SQL versionado con campos suficientes para el snapshot principal de checkout.
- Resolver server-side de precios: existe `SubscriptionPlanPriceResolverService::resolveForCheckout(...)`.
- Idempotencia: existen `SubscriptionWriteIdempotencyRepository` y `SubscriptionWriteIdempotencyService`, pero la operacion actual esta acoplada al write contractual existente.
- Lock de entidad: existe `SubscriptionEntityWriteLockService`, pero el nombre de operacion actual es generico `create`.
- Aceptacion contractual: existe `SubscriptionContractAcceptanceRepository`; el servicio actual `CreateSubscriptionWithAcceptanceService` fija `accepted` y crea `profile_subscriptions`, por lo que no sirve tal cual para checkout pending payment.
- Endpoint `api/subscriptions/index.php`: existe el endpoint contractual actual, pero no existe ruta `checkout-intents`.

### C) Readiness del schema de `subscription_checkout_intents`
El schema de `subscription_checkout_intents` contempla los campos necesarios para el primer write:

- `uuid`;
- `entity_type`;
- `entity_id`;
- `doctor_id`;
- `profile_id`;
- `user_id`;
- `plan_code`;
- `billing_period`;
- `amount_cents`;
- `currency`;
- `price_source`;
- `price_version`;
- `contract_acceptance_uuid`;
- `contract_version`;
- `contract_hash`;
- `contract_snapshot_url`;
- `status`;
- `source`;
- `idempotency_key_hash`;
- `request_hash`;
- `created_at`;
- `updated_at`;
- `deleted_at`.

Readiness:

- El snapshot de precio/contrato cabe en `subscription_checkout_intents`.
- `idempotency_key_hash` y `request_hash` estan disponibles en el schema.
- `contract_acceptance_uuid` esta contemplado.
- `status` es texto y permite guardar `pending_payment`, aunque el default del schema sea `pending_contract`; el primer write debe setear `pending_payment` explicitamente.
- No se requiere cambiar SQL para esta decision documental.

Brecha no bloqueante para implementacion posterior:

- Confirmar en QA que no se dependa del default `pending_contract` cuando el contrato ya fue aceptado en el request.

### D) Readiness del resolver de precios
El futuro servicio debe consumir:

- `SubscriptionPlanPriceResolverService::resolveForCheckout(...)`.

El snapshot devuelto incluye:

- `plan_code`;
- `billing_period`;
- `amount_cents`;
- `currency`;
- `price_source`;
- `price_version`;
- `valid_from`;
- `valid_until`;
- `price_uuid`;
- `source`.

`SubscriptionCheckoutIntentRepository` no debe resolver precios por si mismo. Solo debe persistir el snapshot recibido desde la capa de orquestacion.

Errores de pricing ya disponibles/documentados:

- `plan_not_contractable`;
- `billing_period_invalid`;
- `plan_price_not_configured`;
- `pricing_configuration_conflict`;
- `pricing_source_unavailable`.

### E) Readiness de idempotencia
La capa futura de servicio/orquestacion debe usar la operacion:

- `subscriptions.checkout_intent.create`.

Readiness observada:

- El repositorio de idempotencia ya persiste `idempotency_key_hash` y `request_hash`.
- El servicio actual soporta replay estable, payload distinto y `request_already_processing`.
- El servicio actual tiene la constante de operacion `subscriptions.create_with_contract_acceptance`, por lo que requiere extension o parametrizacion para checkout-intents.

`SubscriptionCheckoutIntentRepository` no debe conocer ni manejar idempotencia directamente.

Comportamientos que deben conservarse:

- replay estable;
- bloqueo de payload distinto;
- `request_already_processing`;
- `idempotency_key_reused_with_different_payload`;
- persistencia de `idempotency_key_hash` y `request_hash`, si aplica al checkout intent.

### F) Readiness de lock
La capa futura de servicio/orquestacion debe ejecutar el flujo bajo:

- `mxmed:subscriptions:{entity_type}:{entity_id}:checkout_create`.

Readiness observada:

- Existe `SubscriptionEntityWriteLockService` con `GET_LOCK` y `RELEASE_LOCK`.
- El lock actual usa operacion interna `create`, por lo que requiere extension o parametrizacion para producir `checkout_create`.

`SubscriptionCheckoutIntentRepository` no debe conocer ni manejar lock directamente.

El lock debe prevenir:

- doble checkout pending;
- doble aceptacion pending payment;
- carreras con keys distintas.

Error conceptual:

- `subscription_checkout_lock_timeout`.

### G) Readiness de aceptacion contractual `accepted_pending_payment`
Estado observado:

- `subscription_contract_acceptances.subscription_id` es nullable, por lo que puede soportar una aceptacion previa a suscripcion activa.
- `SubscriptionContractAcceptanceRepository::insert(...)` recibe `status` desde datos, por lo que el repositorio puede insertar un status distinto si se lo entrega una capa superior.
- El SQL historico lista estados conceptuales como `accepted`, `pending_link`, `superseded`, `void`, `expired`, `cancelled`; no lista explicitamente `accepted_pending_payment`.
- `CreateSubscriptionWithAcceptanceService` fija `status = accepted`, crea `profile_subscriptions` y activa suscripcion, por lo que no debe reutilizarse tal cual para el checkout-first pending payment.

Brecha:

- Pendiente validar/implementar extension de aceptacion contractual para `accepted_pending_payment`.

Reglas futuras:

- `subscription_id` debe quedar `NULL`.
- `source` debe ser `checkout_intent`.
- El enlace posterior se hace con `subscription_checkout_intents.contract_acceptance_uuid`.
- Esta aceptacion no activa suscripcion.

### H) Readiness de endpoint futuro
`api/subscriptions/index.php` debera integrar posteriormente la ruta:

- `POST /api/subscriptions/index.php/entities/{entity_type}/{entity_id}/checkout-intents`.

Esta microfase no la implementa.

El endpoint futuro debera orquestar:

- auth/session scope;
- validacion `entity_type` / `entity_id`;
- validacion `active_subscription_exists`;
- idempotencia;
- lock;
- resolver de precios;
- aceptacion `accepted_pending_payment`;
- creacion de checkout intent.

### I) Orden recomendado de implementacion posterior
Ruta segura de microfases posteriores:

1. Implementar o extender soporte de aceptacion contractual `accepted_pending_payment`, si falta.
2. Implementar `SubscriptionCheckoutIntentRepository`.
3. QA unitario/smoke del repository sin endpoint productivo.
4. Implementar `CreateSubscriptionCheckoutIntentService`.
5. Integrar resolver de precios.
6. Integrar idempotencia checkout.
7. Integrar lock checkout.
8. Integrar ruta endpoint `checkout-intents`.
9. QA funcional `201`.
10. QA replay idempotente.
11. QA payload distinto `409`.
12. QA `active_subscription_exists`.
13. QA `checkout_already_pending`.
14. QA errores de pricing.
15. QA auth/forbidden.
16. Disenar provider adapter en microfase futura separada.

### J) Riesgos y brechas
Brechas bloqueantes antes de implementar endpoint:

- Soporte real de `accepted_pending_payment` en capa de aceptacion contractual.
- Extension o parametrizacion de idempotencia para `subscriptions.checkout_intent.create`.
- Extension o parametrizacion de lock para `checkout_create`.
- Definir transaccion futura atomica acceptance + checkout intent.

Brechas no bloqueantes para esta readiness:

- El schema de `subscription_checkout_intents` contiene las columnas suficientes.
- `idempotency_key_hash` y `request_hash` existen y pueden usarse si la capa de orquestacion decide copiarlos al checkout intent.
- Falta implementar lookup de checkout pending en el repositorio futuro.
- Debe definirse rollback si falla checkout intent despues de crear aceptacion.
- Debe revalidarse `active_subscription_exists` antes y dentro del lock.
- Se mantiene la decision de no crear payment intent todavia.

### K) Decisiones que se mantienen
Se reafirma:

- El primer write crea `accepted_pending_payment` y `subscription_checkout_intents`.
- El primer write NO crea `subscription_payment_intents`.
- El primer write NO crea `subscription_payment_events`.
- El primer write NO crea `profile_subscriptions`.
- Status inicial checkout = `pending_payment`.
- Operation idempotencia = `subscriptions.checkout_intent.create`.
- Lock = `mxmed:subscriptions:{entity_type}:{entity_id}:checkout_create`.

### L) Fuera de alcance
Esta adenda no implementa:

- repositorio;
- servicio;
- endpoint;
- rutas;
- SQL;
- migraciones;
- DB/schema;
- provider;
- payment intents;
- payment events;
- webhook;
- activacion post-pago;
- `profile_subscriptions`;
- facturacion;
- capacidades;
- conexion con `PublicProfilePlanCapabilities`;
- perfil publico;
- SEO;
- frontend.

### M) Siguiente microfase recomendada
Siguiente microfase recomendada:

- `BE/SPEC-Suscripciones-ContractAcceptance-PendingPayment-Readiness-01`.

Objetivo:

- Validar readiness tecnica especifica para extender/usar aceptacion contractual con status `accepted_pending_payment`, sin escribir codigo todavia.

## Adenda PP-Decisiones 78 — Readiness de aceptación contractual accepted_pending_payment para checkout-intents

### A) Proposito
Esta adenda valida la readiness tecnica para que el flujo futuro `checkout-intents` cree una aceptacion contractual con:

- `status = accepted_pending_payment`.

sin crear todavia:

- `profile_subscriptions`;
- payment intents;
- payment events;
- activacion de capacidades.

No cambia el flujo contractual actual ya existente. Identifica la extension segura necesaria para checkout-first.

### B) Estado actual observado
Inspeccion read-only:

- Repositorio actual de aceptacion contractual: `modules/subscriptions/repositories/SubscriptionContractAcceptanceRepository.php`.
- Clase: `SubscriptionContractAcceptanceRepository`.
- Metodo de insercion: `insert(array $data): void`.
- Servicio actual: `modules/subscriptions/services/CreateSubscriptionWithAcceptanceService.php`.
- Clase: `CreateSubscriptionWithAcceptanceService`.
- Endpoint contractual actual: `api/subscriptions/index.php` invoca el servicio actual para `POST /entities/{entity_type}/{entity_id}/subscriptions`.
- Schema: `modules/profiles/db/2026_06_20_create_subscription_contract_acceptances.sql`.
- Relacion actual con `profile_subscriptions`: el servicio contractual actual crea aceptacion y suscripcion activa en el mismo flujo.

### C) Readiness del repositorio de aceptacion
El repositorio actual parece apto para insertar datos flexibles porque recibe los valores desde `$data`.

Readiness observada:

- Permite recibir `status` dinamico.
- Puede recibir `accepted_pending_payment` si la capa superior lo envia.
- Permite `subscription_id = NULL` porque la columna es nullable en el schema.
- Permite `source` configurable porque lo recibe desde `$data`.
- Permite `contract_version`, `contract_hash` y `contract_snapshot_url`.
- Permite campos de actor, usuario y entidad existentes.
- No mezcla por si mismo creacion de `profile_subscriptions`.

Brechas:

- El metodo `insert(...)` no devuelve `uuid` ni fila creada; para checkout-intents convendra que la capa superior conserve el UUID generado o que una extension futura devuelva la fila.
- Debe validarse explicitamente que `accepted_pending_payment` se acepta como status operacional en QA futura.

Conclusion:

- El repositorio puede ser reutilizable con ajuste minimo o con una envoltura de servicio; no debe ser invocado desde el endpoint sin una capa de orquestacion especifica.

### D) Brecha del servicio actual
Brecha detectada:

- `CreateSubscriptionWithAcceptanceService` fija `status = accepted`.
- `CreateSubscriptionWithAcceptanceService` crea `profile_subscriptions`.
- El flujo actual asume activacion inmediata de suscripcion.
- Por lo tanto no puede usarse tal cual para `checkout-intents`.
- Se requiere una variante o metodo separado para aceptacion pending payment.

No se implementa esa variante en esta adenda.

### E) Variante futura sugerida
Variante futura sugerida:

- `createPendingPaymentAcceptance(...)`.

Ubicacion conceptual posible:

- nuevo servicio `SubscriptionContractAcceptanceService`;
- o nuevo servicio `CreateSubscriptionContractAcceptanceService`;
- o extension controlada del servicio actual, siempre que no rompa el flujo contractual existente.

Responsabilidades:

- Validar contrato.
- Guardar evidencia contractual.
- Insertar `subscription_contract_acceptances` con `status = accepted_pending_payment`.
- Dejar `subscription_id = NULL`.
- Usar `source = checkout_intent`.
- Devolver `contract_acceptance_uuid` y snapshot contractual.
- No crear `profile_subscriptions`.
- No activar capacidades.
- No crear payment intents.
- No llamar provider.

### F) Contrato de datos futuro
Input conceptual:

- `entity_type`;
- `entity_id`;
- `doctor_id`, si aplica;
- `profile_id`, si aplica;
- `user_id` / `actor_id`, si aplica;
- `plan_code`;
- `billing_period`;
- `contract_version`;
- `contract_hash`;
- `contract_snapshot_url`;
- `acceptance_source = checkout_intent`;
- `accepted_at` / `created_at`;
- contexto minimo de request.

Output conceptual:

- `contract_acceptance_uuid`;
- `status = accepted_pending_payment`;
- `contract_version`;
- `contract_hash`;
- `contract_snapshot_url`;
- `subscription_id = NULL`;
- `source = checkout_intent`;
- `created_at`.

### G) Transaccion futura con checkout intent
En la implementacion futura, la creacion de:

- `subscription_contract_acceptances` con `accepted_pending_payment`;
- `subscription_checkout_intents` con `pending_payment`;

debe ocurrir en una unidad atomica o transaccion coordinada.

Reglas:

- Si falla la aceptacion, no crear checkout intent.
- Si falla checkout intent despues de aceptacion, hacer rollback si esta dentro de la misma transaccion.
- Si no hay transaccion compartida, documentar riesgo y estrategia de compensacion antes de implementar.
- No dejar aceptacion huerfana salvo estrategia explicita futura.

No se implementa transaccion en esta adenda.

### H) Idempotencia y lock
La variante futura no debe manejar idempotencia ni lock directamente si la arquitectura mantiene esas responsabilidades en la capa superior de checkout service.

Debe ejecutarse indirectamente bajo:

- operation `subscriptions.checkout_intent.create`;
- lock `mxmed:subscriptions:{entity_type}:{entity_id}:checkout_create`.

Decisiones preservadas:

- idempotencia en capa service/orquestacion;
- lock en capa service/orquestacion;
- repositorio/servicio de aceptacion solo crea evidencia contractual cuando se le ordena dentro del flujo controlado.

### I) Errores conceptuales
Errores por contrato:

- `contract_invalid`.
- `acceptance_source_invalid`.

Errores por aceptacion:

- `contract_acceptance_create_failed`.

Errores por idempotencia:

- `idempotency_key_reused_with_different_payload`.
- `request_already_processing`.

Errores por lock:

- `subscription_checkout_lock_timeout`.

Errores por estado de suscripcion:

- `active_subscription_exists`.

Errores por checkout pending:

- `checkout_already_pending`.

### J) Riesgos y brechas
Riesgos/brechas:

- El servicio actual esta acoplado a creacion de `profile_subscriptions`.
- Se debe mantener compatibilidad del endpoint contractual actual.
- Existe riesgo de duplicar logica contractual si no se separa una variante clara.
- Debe evitarse una aceptacion huerfana.
- La transaccion acceptance + checkout intent debe definirse antes de implementar.
- `accepted_pending_payment` no debe confundirse con suscripcion activa.
- QA debe validar especificamente que no se crea `profile_subscriptions`.

### K) Decisiones que se mantienen
Se reafirma:

- `accepted_pending_payment` ocurre antes del pago.
- `accepted_pending_payment` no activa suscripcion.
- `subscription_id` queda `NULL`.
- Checkout intent queda `pending_payment`.
- El primer write NO crea payment intents.
- El primer write NO crea payment events.
- El primer write NO crea `profile_subscriptions`.
- Provider, webhook, facturacion y capacidades quedan fuera.

### L) Fuera de alcance
Esta adenda no implementa:

- repositorio;
- servicio;
- endpoint;
- rutas;
- SQL;
- migraciones;
- DB/schema;
- provider;
- payment intents;
- payment events;
- webhook;
- activacion post-pago;
- `profile_subscriptions`;
- facturacion;
- capacidades;
- conexion con `PublicProfilePlanCapabilities`;
- perfil publico;
- SEO;
- frontend.

### M) Siguiente microfase recomendada
Siguiente microfase recomendada:

- `BE/SPEC-Suscripciones-ContractAcceptance-PendingPayment-ServiceDesign-01`.

Objetivo:

- Disenar documentalmente la variante/metodo de servicio para crear aceptacion contractual `accepted_pending_payment` sin crear `profile_subscriptions`, sin escribir codigo todavia.

## Adenda PP-Decisiones 79 — Diseño de servicio de aceptación contractual accepted_pending_payment para checkout-intents

### A) Propósito
Esta adenda diseña la variante futura de servicio para crear evidencia contractual con:

- `status = accepted_pending_payment`.

La variante aplica al flujo futuro `checkout-intents` y no debe activar suscripcion ni crear `profile_subscriptions`.

Esta adenda no implementa codigo, no crea repositorios/servicios, no modifica rutas, no crea SQL y no cambia DB/schema.

### B) Principio de separación
El flujo contractual actual:

- `POST /api/subscriptions/index.php/entities/{entity_type}/{entity_id}/subscriptions`

debe permanecer intacto. Ese flujo usa `CreateSubscriptionWithAcceptanceService` para crear aceptacion contractual `accepted` y suscripcion activa.

El flujo checkout-first requiere una variante separada porque:

- `accepted_pending_payment` no equivale a `accepted` activo.
- `subscription_id` debe quedar `NULL`.
- No se debe crear `profile_subscriptions`.
- No se deben activar capacidades.
- No se debe llamar provider.
- No se debe crear payment intent.
- No se debe crear payment event.

### C) Componente futuro recomendado
La opcion recomendada es crear un servicio nuevo:

- `CreateSubscriptionPendingPaymentAcceptanceService`.

Archivo futuro sugerido:

- `modules/subscriptions/services/CreateSubscriptionPendingPaymentAcceptanceService.php`.

Metodo conceptual:

- `createPendingPaymentAcceptance(array $input): array`.

Se recomienda un servicio nuevo para evitar romper o relajar el contrato actual de `CreateSubscriptionWithAcceptanceService`, que hoy esta orientado a aceptacion contractual final y creacion de `profile_subscriptions`.

### D) Responsabilidades del servicio futuro
`CreateSubscriptionPendingPaymentAcceptanceService` debe:

- validar datos minimos de entidad recibidos desde la capa superior;
- validar el contrato o recibir contrato ya validado, segun la arquitectura final;
- construir/generar UUID de aceptacion;
- construir snapshot contractual;
- insertar en `subscription_contract_acceptances`;
- usar `status = accepted_pending_payment`;
- usar `subscription_id = NULL`;
- usar `source = checkout_intent`;
- devolver `contract_acceptance_uuid` y campos contractuales necesarios para `subscription_checkout_intents`.

No debe:

- crear `profile_subscriptions`;
- activar capacidades;
- crear payment intent;
- crear payment event;
- llamar provider;
- manejar webhooks;
- resolver precio;
- manejar idempotencia directamente;
- manejar lock directamente.

### E) Dependencias conceptuales
Dependencias futuras sugeridas:

- `SubscriptionContractAcceptanceRepository`.
- Componente/helper actual de validacion contractual, si existe o se separa.
- Generador UUID o helper existente, si aplica.
- Reloj/now provider, o `DateTimeImmutable`.
- Logger o manejo de error controlado, si existe convencion local.

Separacion de responsabilidades:

- El resolver de precios vive en `CreateSubscriptionCheckoutIntentService`.
- La idempotencia vive en `CreateSubscriptionCheckoutIntentService`.
- El lock vive en `CreateSubscriptionCheckoutIntentService`.
- El repositorio de checkout intent vive en `SubscriptionCheckoutIntentRepository`.
- El servicio de aceptacion pending payment solo crea evidencia contractual cuando la capa superior se lo ordena.

### F) Input conceptual
Input minimo para `createPendingPaymentAcceptance(array $input): array`:

- `entity_type`;
- `entity_id`;
- `doctor_id`;
- `profile_id`;
- `user_id` / `actor_user_id`;
- `plan_code`;
- `billing_period`;
- `contract_version`;
- `contract_hash`;
- `contract_snapshot_url`;
- `acceptance_source = checkout_intent`;
- `accepted_at` / `created_at`;
- request context minimo;
- metadata minima si el schema actual lo permite.

No recibe:

- `amount_cents`;
- `currency`;
- payment provider data;
- payment intent id;
- profile subscription id activo.

### G) Output conceptual
Output minimo:

- `contract_acceptance_uuid`;
- `status = accepted_pending_payment`;
- `subscription_id = NULL`;
- `contract_version`;
- `contract_hash`;
- `contract_snapshot_url`;
- `source = checkout_intent`;
- `accepted_at` / `created_at`;
- `entity_type`;
- `entity_id`;
- `doctor_id`, si aplica;
- `profile_id`, si aplica.

Este output alimentara el snapshot de `subscription_checkout_intents`.

### H) Invariantes de seguridad
Invariantes obligatorias:

- `accepted_pending_payment` nunca activa suscripcion.
- `accepted_pending_payment` nunca crea `profile_subscriptions`.
- `subscription_id` siempre queda `NULL` en este flujo.
- `source` siempre debe ser `checkout_intent`.
- `status` no debe ser `accepted`.
- No se debe reutilizar el metodo actual que crea suscripcion activa.
- El endpoint contractual actual no debe cambiar.
- El flujo debe correr bajo idempotencia y lock de checkout en capa superior.
- Si falla la insercion de aceptacion, no se debe crear checkout intent.

### I) Transacción futura
La unidad atomica completa debe abarcar:

1. Creacion de `subscription_contract_acceptances` con `accepted_pending_payment`.
2. Creacion de `subscription_checkout_intents` con `pending_payment`.

Recomendacion:

- La transaccion debe vivir preferentemente en `CreateSubscriptionCheckoutIntentService`.

Motivo:

- Ese servicio futuro orquestara aceptacion, checkout intent, idempotencia y lock.

El servicio de aceptacion pending payment puede participar en una transaccion recibida/compartida si la infraestructura lo permite.

Si no hay transaccion compartida, antes de implementar el endpoint debe disenar una estrategia de compensacion. No deben quedar aceptaciones huerfanas por falla posterior del checkout intent.

### J) Errores conceptuales
Errores por validacion contractual:

- `contract_invalid`.
- `acceptance_source_invalid`.

Errores por payload:

- `invalid_pending_payment_acceptance_payload`.

Errores por storage de aceptacion:

- `contract_acceptance_create_failed`.

Errores por estado de suscripcion:

- `active_subscription_exists`.

Errores por checkout pending:

- `checkout_already_pending`.

Errores por idempotencia/lock superior:

- `idempotency_key_reused_with_different_payload`.
- `request_already_processing`.
- `subscription_checkout_lock_timeout`.

### K) Compatibilidad con flujo actual
`CreateSubscriptionWithAcceptanceService` debe mantenerse para el endpoint contractual actual.

La variante pending payment:

- no debe modificar comportamiento existente;
- no debe cambiar el endpoint contractual actual;
- no debe mezclar activacion inmediata con pending payment;
- debe ser consumida por checkout-intents desde un servicio separado.

El endpoint actual debe seguir creando suscripcion activa cuando corresponda.

### L) QA futura recomendada
Cuando se implemente la variante, QA debe validar:

1. Crear aceptacion `accepted_pending_payment` sin `profile_subscriptions`.
2. Verificar `subscription_id = NULL`.
3. Verificar `source = checkout_intent`.
4. Verificar contrato/hash/snapshot.
5. Verificar que no se crean payment intents.
6. Verificar que no se crean payment events.
7. Verificar que no se activan capacidades.
8. Verificar que el endpoint contractual actual sigue funcionando igual.
9. Verificar error `contract_invalid`.
10. Verificar error `acceptance_source_invalid`.
11. Verificar rollback o no persistencia si falla antes de checkout intent, cuando exista integracion.

### M) Riesgos y brechas
Riesgos/brechas:

- Riesgo de duplicar logica contractual.
- Riesgo de romper el flujo contractual actual si se modifica el servicio existente.
- Riesgo de aceptacion huerfana.
- Riesgo de confundir `accepted_pending_payment` con `accepted`.
- Necesidad de transaccion compartida en implementacion futura.
- Necesidad de preservar el UUID generado porque el repositorio actual no devuelve fila.
- Necesidad de QA especifico para comprobar que no se crea `profile_subscriptions`.

### N) Decisiones que se mantienen
Se reafirma:

- El primer write de checkout-intents crea aceptacion `accepted_pending_payment`.
- El primer write de checkout-intents crea `subscription_checkout_intents`.
- El primer write NO crea `profile_subscriptions`.
- El primer write NO crea payment intents.
- El primer write NO crea payment events.
- Checkout intent inicia `pending_payment`.
- Provider, webhook, facturacion y capacidades quedan fuera.
- Idempotencia checkout operation = `subscriptions.checkout_intent.create`.
- Lock checkout = `mxmed:subscriptions:{entity_type}:{entity_id}:checkout_create`.

### O) Fuera de alcance
Esta adenda no implementa:

- repositorio;
- servicio;
- endpoint;
- rutas;
- SQL;
- migraciones;
- DB/schema;
- provider;
- payment intents;
- payment events;
- webhook;
- activacion post-pago;
- `profile_subscriptions`;
- facturacion;
- capacidades;
- conexion con `PublicProfilePlanCapabilities`;
- perfil publico;
- SEO;
- frontend.

### P) Siguiente microfase recomendada
Siguiente microfase recomendada:

- `BE/SPEC-Suscripciones-ContractAcceptance-PendingPayment-Implementation-Readiness-01`.

Objetivo:

- Validar readiness tecnica final para implementar el servicio `CreateSubscriptionPendingPaymentAcceptanceService` o metodo equivalente, sin escribir codigo todavia.

## Adenda PP-Decisiones 80 — Readiness de implementación del servicio accepted_pending_payment

### A) Propósito
Esta adenda valida la readiness tecnica final para implementar posteriormente:

- `CreateSubscriptionPendingPaymentAcceptanceService`.

Metodo conceptual:

- `createPendingPaymentAcceptance(array $input): array`.

La microfase no implementa el servicio, no crea archivos PHP, no modifica rutas, no crea SQL y no cambia DB/schema. Solo confirma que el diseno ya es suficiente para una microfase futura de codigo controlada.

### B) Estado técnico observado
Convenciones de servicios existentes:

- Los servicios viven en `modules/subscriptions/services`.
- Usan `declare(strict_types=1);` y namespace `Subscriptions\Services`.
- Reciben dependencias por constructor.
- Devuelven arrays para operaciones de escritura o lectura compuesta.
- Usan excepciones controladas basadas en `RuntimeException` cuando el flujo requiere `status` y `errorCode`.
- Validan input con helpers privados dentro del servicio cuando no existe helper compartido.

Repositorio `SubscriptionContractAcceptanceRepository`:

- Existe en `modules/subscriptions/repositories/SubscriptionContractAcceptanceRepository.php`.
- Expone `insert(array $data): void`.
- Recibe `uuid`, `subscription_id`, `status`, `source`, `contract_version`, `contract_hash`, `contract_snapshot_url` y campos de actor/evidencia.
- No crea `profile_subscriptions`.
- No devuelve fila ni UUID.

Servicio `CreateSubscriptionWithAcceptanceService`:

- Genera UUIDs con `random_bytes`.
- Usa `DateTimeImmutable` y zona UTC.
- Fija `status = accepted`.
- Crea `profile_subscriptions`.
- Usa transaccion para aceptacion contractual + suscripcion activa.
- Asume activacion inmediata.

Riesgo observado:

- El servicio actual esta acoplado a `profile_subscriptions`; no debe reutilizarse tal cual para checkout-intents.

### C) Decisión de ubicación futura
Archivo futuro confirmado:

- `modules/subscriptions/services/CreateSubscriptionPendingPaymentAcceptanceService.php`.

Clase futura:

- `CreateSubscriptionPendingPaymentAcceptanceService`.

Metodo futuro:

- `createPendingPaymentAcceptance(array $input): array`.

Se prefiere un servicio nuevo porque:

- reduce el riesgo de romper el flujo contractual actual;
- mantiene intacto `CreateSubscriptionWithAcceptanceService`;
- separa `accepted_pending_payment` de `accepted` activo;
- evita activacion accidental;
- permite QA especifico de no creacion de `profile_subscriptions`.

### D) Dependencias listas / necesarias
Dependencias futuras:

- `SubscriptionContractAcceptanceRepository`.
- Generacion UUID local segura, siguiendo el patron actual con `random_bytes`, si no se extrae helper comun.
- `DateTimeImmutable` para timestamps.
- Validacion contractual minima.
- `Throwable` / `RuntimeException` o excepcion especifica equivalente al patron actual.
- `PDO` o transaccion recibida solo si la arquitectura final lo requiere.

No depende de:

- `SubscriptionPlanPriceResolverService`;
- `SubscriptionCheckoutIntentRepository`;
- `SubscriptionWriteIdempotencyService`;
- `SubscriptionEntityWriteLockService`;
- provider/payment/webhook.

Estas responsabilidades viven en el futuro `CreateSubscriptionCheckoutIntentService`.

### E) Contrato final de input
Input recomendado para `createPendingPaymentAcceptance(array $input): array`:

- `entity_type`;
- `entity_id`;
- `doctor_id`;
- `profile_id`;
- `user_id` o `actor_user_id`;
- `plan_code`;
- `billing_period`;
- `contract_version`;
- `contract_hash`;
- `contract_snapshot_url`;
- `acceptance_source = checkout_intent`;
- `accepted_at` opcional;
- request context minimo opcional;
- metadata opcional si el schema actual lo permite.

Valores que el metodo debe forzar o validar:

- `status` debe ser `accepted_pending_payment`.
- `subscription_id` debe ser `NULL`.
- `source` debe ser `checkout_intent`.

No debe recibir:

- `amount_cents`;
- `currency`;
- `provider_payment_id`;
- `payment_intent_id`;
- profile subscription id activo;
- status arbitrario enviado por cliente.

### F) Contrato final de output
Output recomendado:

- `contract_acceptance_uuid`;
- `status = accepted_pending_payment`;
- `subscription_id = NULL`;
- `source = checkout_intent`;
- `contract_version`;
- `contract_hash`;
- `contract_snapshot_url`;
- `accepted_at` / `created_at`;
- `entity_type`;
- `entity_id`;
- `doctor_id`, si aplica;
- `profile_id`, si aplica;
- `plan_code`;
- `billing_period`.

El output alimentara el snapshot de `subscription_checkout_intents`.

El servicio debe conservar y devolver el UUID generado porque el repositorio actual no devuelve fila ni UUID.

### G) Validaciones mínimas
Validaciones minimas futuras:

- `entity_type` permitido.
- `entity_id` presente.
- `plan_code` presente.
- `billing_period` presente.
- `contract_version` presente.
- `contract_hash` presente.
- `contract_snapshot_url` presente, o nullable solo si el contrato actual lo permite.
- `acceptance_source` debe ser `checkout_intent`.
- Status interno debe ser `accepted_pending_payment`.
- `subscription_id` debe ser `NULL`.
- No aceptar status externo arbitrario.
- No aceptar source externo arbitrario fuera de allowlist.

### H) Política de errores
Errores conceptuales propios de este servicio:

- `invalid_pending_payment_acceptance_payload`.
- `contract_invalid`.
- `acceptance_source_invalid`.
- `contract_acceptance_create_failed`.
- `pending_payment_acceptance_unexpected_subscription_id`.
- `pending_payment_acceptance_unexpected_status`.
- `pending_payment_acceptance_unexpected_source`.

Errores que pertenecen a capa superior:

- `active_subscription_exists`.
- `checkout_already_pending`.
- `idempotency_key_reused_with_different_payload`.
- `request_already_processing`.
- `subscription_checkout_lock_timeout`.
- `plan_not_contractable`.
- `billing_period_invalid`.
- `plan_price_not_configured`.
- `pricing_configuration_conflict`.
- `pricing_source_unavailable`.

### I) Transacción futura
Decision de readiness:

- Este servicio no debe ser dueno final de la transaccion completa de checkout.
- La transaccion completa debe vivir en `CreateSubscriptionCheckoutIntentService`.
- El servicio pending payment debe poder ejecutarse dentro de una transaccion ya abierta si la infraestructura lo permite.
- Si el servicio abre transaccion propia en una implementacion minima, debe documentarse como riesgo porque el checkout intent posterior podria fallar y dejar aceptacion huerfana.
- La implementacion recomendada debe evitar aceptacion huerfana.

### J) Invariantes de seguridad
Invariantes obligatorias:

- Nunca crear `profile_subscriptions`.
- Nunca crear payment intents.
- Nunca crear payment events.
- Nunca activar capacidades.
- Nunca usar `status = accepted` en este flujo.
- Nunca setear `subscription_id` distinto de `NULL`.
- Nunca aceptar `source` distinto de `checkout_intent`.
- Nunca llamar provider.
- Nunca tocar el endpoint contractual actual.
- Nunca modificar `CreateSubscriptionWithAcceptanceService` en la implementacion minima salvo microfase explicita.

### K) Plan de implementación futuro mínimo
Plan recomendado para la siguiente microfase de codigo, sin ejecutarlo ahora:

1. Crear archivo de servicio nuevo.
2. Inyectar `SubscriptionContractAcceptanceRepository`.
3. Implementar constructor segun patron existente.
4. Implementar `createPendingPaymentAcceptance(array $input): array`.
5. Generar `contract_acceptance_uuid` antes del insert.
6. Normalizar/validar input.
7. Construir payload para `repository->insert(...)`.
8. Forzar `status = accepted_pending_payment`.
9. Forzar `subscription_id = NULL`.
10. Forzar `source = checkout_intent`.
11. Llamar `repository->insert($payload)`.
12. Devolver array con UUID y snapshot contractual.
13. No tocar endpoint.
14. No tocar `CreateSubscriptionWithAcceptanceService`.
15. No crear `profile_subscriptions`.

### L) QA futura de implementación
QA minima para la microfase de codigo:

- `php -l` del nuevo archivo.
- `grep` para confirmar que el nuevo servicio no contiene writes a `profile_subscriptions`.
- `grep` para confirmar `accepted_pending_payment`.
- `grep` para confirmar `subscription_id = NULL`.
- `grep` para confirmar `source = checkout_intent`.
- Inspeccion de diff para confirmar que solo se agrego el nuevo servicio, si esa microfase lo permite.
- Si se permite prueba PHP aislada posterior, validar que el metodo construye payload esperado usando stub/mock controlado.
- Confirmar que `api/subscriptions/index.php` no cambio.
- Confirmar que `CreateSubscriptionWithAcceptanceService` no cambio.

### M) Riesgos y bloqueos
Bloqueantes antes de endpoint:

- Falta implementar este servicio.
- Falta integrar con `CreateSubscriptionCheckoutIntentService`.
- Falta transaccion acceptance + checkout intent.
- Falta integracion idempotencia/lock checkout.

No bloqueantes para implementar este servicio:

- Provider no existe.
- Payment intent no existe.
- Webhook no existe.
- Facturacion no existe.
- Capacidades productivas no estan conectadas.

### N) Decisiones que se mantienen
Se reafirma:

- Primer write checkout-intents crea aceptacion `accepted_pending_payment`.
- Primer write checkout-intents crea `subscription_checkout_intents`.
- Primer write NO crea `profile_subscriptions`.
- Primer write NO crea payment intents.
- Primer write NO crea payment events.
- Checkout intent inicia `pending_payment`.
- Provider, webhook, facturacion y capacidades quedan fuera.
- Idempotencia checkout operation = `subscriptions.checkout_intent.create`.
- Lock checkout = `mxmed:subscriptions:{entity_type}:{entity_id}:checkout_create`.

### O) Fuera de alcance
Esta adenda no implementa:

- repositorio;
- servicio;
- endpoint;
- rutas;
- SQL;
- migraciones;
- DB/schema;
- provider;
- payment intents;
- payment events;
- webhook;
- activacion post-pago;
- `profile_subscriptions`;
- facturacion;
- capacidades;
- conexion con `PublicProfilePlanCapabilities`;
- perfil publico;
- SEO;
- frontend.

### P) Siguiente microfase recomendada
Siguiente microfase recomendada:

- `BE/Suscripciones-ContractAcceptance-PendingPayment-Service-01`.

Objetivo:

- Implementar el servicio `CreateSubscriptionPendingPaymentAcceptanceService` con metodo `createPendingPaymentAcceptance(array $input): array`, sin endpoint, sin SQL, sin DB/schema y sin crear `profile_subscriptions`.

## Adenda PP-Decisiones 81 — Readiness de implementación del repositorio checkout-intents

### A) Propósito
Esta adenda valida readiness tecnica para implementar posteriormente:

- `SubscriptionCheckoutIntentRepository`.

La adenda prepara solo el repository de storage para `subscription_checkout_intents`. No implementa codigo, no crea repositorios reales, no crea servicio orquestador, no crea endpoint, no crea SQL y no cambia DB/schema.

### B) Estado técnico observado
Schema `subscription_checkout_intents`:

- Existe en `modules/profiles/db/2026_06_22_create_subscription_checkout_intents.sql`.
- Contempla `uuid`, entidad, actor, plan, precio, contrato, idempotencia, provider nullable, `subscription_id` nullable, fechas y soft-delete.
- Contempla `status`, con default `pending_contract`; el primer write debe setear explicitamente `pending_payment`.
- Contempla `contract_acceptance_uuid`.
- Contempla `idempotency_key_hash` y `request_hash`.
- Contempla `deleted_at`.
- Tiene indice unico por `uuid`.
- Tiene indices por entidad, doctor, usuario, status/expiracion, plan/periodo, aceptacion contractual, suscripcion, provider y `deleted_at`.

Estilo de repositories existentes:

- Usan `declare(strict_types=1);`.
- Namespace `Subscriptions\Repositories`.
- Reciben `PDO` por constructor.
- Usan `prepare(...)`, `execute(...)`, `fetch(...)` o `fetchAll(...)`.
- Retornan `array`, `?array`, `void` o booleanos segun responsabilidad.
- No abren transacciones por si mismos.

Servicio `accepted_pending_payment`:

- `CreateSubscriptionPendingPaymentAcceptanceService` ya existe y fue validado post-push.
- Devuelve `contract_acceptance_uuid` y snapshot contractual para alimentar el futuro checkout intent.

Relacion futura:

- `CreateSubscriptionCheckoutIntentService` orquestara aceptacion pending payment + checkout intent + idempotencia + lock.
- `SubscriptionCheckoutIntentRepository` sera solo storage sobre `subscription_checkout_intents`.

### C) Ubicación futura
Archivo futuro:

- `modules/subscriptions/repositories/SubscriptionCheckoutIntentRepository.php`.

Clase futura:

- `SubscriptionCheckoutIntentRepository`.

Namespace:

- `Subscriptions\Repositories`.

### D) Métodos futuros recomendados
Metodos recomendados:

- `create(array $snapshot): array`.

Responsabilidad:

- Insertar checkout intent y devolver snapshot/fila minima creada.

- `findByUuid(string $uuid): ?array`.

Responsabilidad:

- Buscar checkout intent por `uuid`.

- `findPendingByEntity(string $entityType, string $entityId): ?array`.

Responsabilidad:

- Buscar checkout intent `pending_payment` vigente/no eliminado para una entidad.

- `findPendingByEntityPlanAndBilling(string $entityType, string $entityId, string $planCode, string $billingPeriod): ?array`.

Responsabilidad:

- Buscar pending compatible para la misma entidad, plan y periodo.

La decision final de error, replay idempotente o continuidad del flujo queda en capa superior, no en el repository.

### E) Payload de `create(array $snapshot)`
Input recomendado:

- `uuid`;
- `entity_type`;
- `entity_id`;
- `doctor_id`;
- `profile_id`;
- `user_id`;
- `actor_role`;
- `plan_code`;
- `billing_period`;
- `amount_cents`;
- `currency`;
- `price_source`;
- `price_version`;
- `contract_acceptance_uuid`;
- `contract_version`;
- `contract_hash`;
- `contract_snapshot_url`;
- `status = pending_payment`;
- `source`;
- `idempotency_key_hash`, si la capa superior decide copiarlo;
- `request_hash`, si la capa superior decide copiarlo;
- `expires_at`;
- `created_at`, si se requiere snapshot de retorno;
- `updated_at`, si se requiere snapshot de retorno;
- `deleted_at = NULL`.

Aclaraciones:

- `amount_cents`, `currency`, `price_source` y `price_version` vienen del resolver server-side.
- `contract_acceptance_uuid` viene del servicio `accepted_pending_payment`.
- `status` debe ser `pending_payment`.
- `source` debe identificar el flujo de checkout intent, por ejemplo `mxmed_subscription_checkout_intent_v1` o equivalente documentado.
- El cliente no envia precio canonico.

### F) Validaciones mínimas del repository futuro
Validaciones minimas:

- `uuid` requerido.
- `entity_type` requerido.
- `entity_id` requerido.
- `user_id` requerido.
- `plan_code` requerido.
- `billing_period` requerido.
- `amount_cents` requerido y entero no negativo; en checkout pagado debe ser positivo porque `free` no es contratable.
- `currency` requerido.
- `price_source` requerido.
- `price_version` requerido.
- `contract_acceptance_uuid` requerido.
- `contract_version` requerido.
- `contract_hash` requerido.
- `contract_snapshot_url` requerido por el schema actual.
- `expires_at` requerido por el schema actual.
- `status` debe ser `pending_payment`.
- `source` requerido.
- No aceptar status externo arbitrario si el repository recibe payload desde capa superior.

### G) Normalización de salida
Los metodos deben devolver arrays normalizados con keys estables:

- `uuid`;
- `entity_type`;
- `entity_id`;
- `doctor_id`;
- `profile_id`;
- `user_id`;
- `actor_role`;
- `plan_code`;
- `billing_period`;
- `amount_cents`;
- `currency`;
- `price_source`;
- `price_version`;
- `contract_acceptance_uuid`;
- `contract_version`;
- `contract_hash`;
- `contract_snapshot_url`;
- `status`;
- `source`;
- `idempotency_key_hash`, si existe;
- `request_hash`, si existe;
- `expires_at`;
- `completed_at`;
- `cancelled_at`;
- `activated_at`;
- `created_at`;
- `updated_at`;
- `deleted_at`.

### H) Pending checkout
El repository solo consulta pending y no decide negocio.

La capa superior futura `CreateSubscriptionCheckoutIntentService` decidira:

- si corresponde replay idempotente;
- si corresponde `checkout_already_pending`;
- si existe `active_subscription_exists`;
- si puede continuar con `create(...)`.

El repository no debe:

- cerrar pending automaticamente;
- sobrescribir pending;
- crear uno nuevo si ya existe pending;
- decidir compatibilidad de negocio mas alla del lookup solicitado.

### I) Transacción futura
`SubscriptionCheckoutIntentRepository` no debe abrir transaccion propia.

Debe poder usarse dentro de una transaccion superior controlada por `CreateSubscriptionCheckoutIntentService`.

La unidad atomica futura sera:

1. Crear `subscription_contract_acceptances` con `accepted_pending_payment`.
2. Crear `subscription_checkout_intents` con `pending_payment`.

### J) Responsabilidades fuera del repository
El repository no debe:

- validar auth/session;
- validar entidad real;
- validar `active_subscription_exists`;
- resolver precio;
- validar contrato;
- crear aceptacion contractual;
- manejar idempotencia;
- manejar lock;
- crear payment intents;
- crear payment events;
- crear `profile_subscriptions`;
- activar capacidades;
- llamar provider;
- manejar webhooks;
- facturar;
- tocar perfil publico/SEO.

### K) Política de errores
Errores propios del repository:

- `invalid_checkout_intent_payload`.
- `checkout_intent_create_failed`.
- `checkout_intent_lookup_failed`.
- `checkout_intent_not_found`, solo si aplica a metodos estrictos futuros.
- `pricing_snapshot_missing`.
- `contract_acceptance_uuid_missing`.

Errores de capa superior, no del repository:

- `checkout_already_pending`.
- `active_subscription_exists`.
- `plan_not_contractable`.
- `billing_period_invalid`.
- `plan_price_not_configured`.
- `pricing_configuration_conflict`.
- `pricing_source_unavailable`.
- `contract_invalid`.
- `idempotency_key_reused_with_different_payload`.
- `request_already_processing`.
- `subscription_checkout_lock_timeout`.

### L) QA futura de implementación
QA futura cuando se implemente el repository:

- `php -l` del nuevo archivo.
- `grep` de clase y metodos.
- `grep` de `pending_payment`.
- `grep` de `contract_acceptance_uuid`.
- `grep` de prohibidos:
  - `profile_subscriptions`;
  - `subscription_payment_intents`;
  - `subscription_payment_events`;
  - provider;
  - webhook;
  - `PublicProfilePlanCapabilities`.
- Diff confirma solo archivo nuevo repository, si esa microfase lo permite.
- Pruebas aisladas sin DB real solo si hay patron existente.
- No endpoint.
- No SQL.
- No DB/schema.

### M) Riesgos y brechas
Riesgos/brechas:

- Confirmar nombres exactos de columnas antes de implementar.
- `idempotency_key_hash` y `request_hash` existen en schema, pero deben copiarse solo si la capa de orquestacion lo decide.
- No confundir repository con service orquestador.
- No abrir transaccion propia.
- No crear payment intent.
- No crear `profile_subscriptions`.
- Necesidad posterior de `CreateSubscriptionCheckoutIntentService`.
- Necesidad posterior de integracion con idempotencia/lock.
- Necesidad posterior de QA DB/local cuando se conecte endpoint o servicio orquestador.

### N) Decisiones que se mantienen
Se reafirma:

- Primer write checkout-intents crea aceptacion `accepted_pending_payment`.
- Primer write checkout-intents crea `subscription_checkout_intents`.
- Primer write NO crea `profile_subscriptions`.
- Primer write NO crea payment intents.
- Primer write NO crea payment events.
- Checkout intent inicia `pending_payment`.
- Provider, webhook, facturacion y capacidades quedan fuera.
- Idempotencia checkout operation = `subscriptions.checkout_intent.create`.
- Lock checkout = `mxmed:subscriptions:{entity_type}:{entity_id}:checkout_create`.

### O) Fuera de alcance
Esta adenda no implementa:

- repositorio;
- servicio orquestador;
- endpoint;
- rutas;
- SQL;
- migraciones;
- DB/schema;
- provider;
- payment intents;
- payment events;
- webhook;
- activacion post-pago;
- `profile_subscriptions`;
- facturacion;
- capacidades;
- conexion con `PublicProfilePlanCapabilities`;
- perfil publico;
- SEO;
- frontend.

### P) Siguiente microfase recomendada
Siguiente microfase recomendada:

- `BE/Suscripciones-CheckoutIntent-Repository-01`.

Objetivo:

- Implementar `SubscriptionCheckoutIntentRepository` con metodos `create`, `findByUuid`, `findPendingByEntity` y `findPendingByEntityPlanAndBilling`, sin endpoint, sin SQL, sin DB/schema, sin provider y sin crear payment/profile_subscriptions.

---

## Adenda PP-Decisiones 82 - Readiness de implementacion del servicio checkout-intents

### A) Proposito
Esta adenda valida la readiness tecnica para implementar posteriormente `CreateSubscriptionCheckoutIntentService`, sin escribir codigo en esta microfase.

El servicio futuro sera el orquestador del primer write de checkout-intents. Su responsabilidad conceptual sera coordinar:

- validacion de entidad y estado;
- resolucion server-side de precio;
- aceptacion contractual `accepted_pending_payment`;
- creacion de `subscription_checkout_intents` con `pending_payment`;
- integracion futura con idempotencia;
- integracion futura con lock;
- transaccion atomica entre aceptacion y checkout intent.

Esta adenda no implementa el servicio, no agrega endpoint, no modifica rutas, no crea SQL y no cambia DB/schema.

### B) Estado tecnico observado
En inspeccion read-only se observo:

- Servicio `CreateSubscriptionPendingPaymentAcceptanceService` disponible en `modules/subscriptions/services/CreateSubscriptionPendingPaymentAcceptanceService.php`.
- Repositorio `SubscriptionCheckoutIntentRepository` disponible en `modules/subscriptions/repositories/SubscriptionCheckoutIntentRepository.php`.
- Resolver `SubscriptionPlanPriceResolverService::resolveForCheckout(...)` disponible para snapshot server-side de precio.
- Idempotencia disponible mediante `SubscriptionWriteIdempotencyService`, pero actualmente acoplada a `subscriptions.create_with_contract_acceptance`.
- Lock disponible mediante `SubscriptionEntityWriteLockService`, pero actualmente genera lock con operacion fija `create`.
- Endpoint actual en `api/subscriptions/index.php` carga y usa el flujo contractual existente; no existe ruta `checkout-intents`.
- `CreateSubscriptionWithAcceptanceService` ya maneja transaccion para aceptacion + `profile_subscriptions`, pero ese flujo no debe reutilizarse tal cual para checkout-first.

Brechas restantes:

- parametrizar o extender idempotencia para `subscriptions.checkout_intent.create`;
- parametrizar o extender lock para `checkout_create`;
- definir helper/repositorio claro para `active_subscription_exists` sin activar suscripcion;
- disenar secuencia exacta de transaccion + idempotencia + lock antes de implementar el orquestador.

### C) Ubicacion futura
Archivo futuro recomendado:

- `modules/subscriptions/services/CreateSubscriptionCheckoutIntentService.php`.

Clase futura:

- `CreateSubscriptionCheckoutIntentService`.

Metodo futuro sugerido:

- `createCheckoutIntent(array $input): array`.

El nombre refleja que el servicio no expone HTTP directamente: recibe un input normalizado desde endpoint/capa superior y devuelve un resultado de checkout intent listo para responder o persistir en idempotencia.

### D) Dependencias futuras
Dependencias sugeridas:

- `SubscriptionPlanPriceResolverService`;
- `CreateSubscriptionPendingPaymentAcceptanceService`;
- `SubscriptionCheckoutIntentRepository`;
- `SubscriptionWriteIdempotencyService`;
- `SubscriptionEntityWriteLockService`;
- componente/repositorio para validar `active_subscription_exists` o brecha pendiente;
- `PDO`, si la transaccion superior vive en este servicio;
- `DateTimeImmutable` o reloj equivalente para `created_at`/`expires_at`.

El servicio no debe depender de:

- provider;
- payment intents;
- webhook;
- facturacion;
- `PublicProfilePlanCapabilities`;
- perfil publico/SEO.

### E) Input conceptual
Input minimo futuro para `createCheckoutIntent(array $input): array`:

- `entity_type`;
- `entity_id`;
- `doctor_id`, si aplica;
- `profile_id`, si aplica;
- `user_id` o `actor_user_id`;
- `actor_role`, si aplica;
- `plan_code`;
- `billing_period`;
- `contract_version`;
- `contract_hash`;
- `contract_snapshot_url`;
- `contract_title`, si aplica;
- `acceptance_source = checkout_intent`;
- `idempotency_key`;
- `request_context` minimo, incluyendo IP/user-agent si aplica;
- `source`;
- `expires_at`, opcional para checkout intent.

El cliente no debe enviar como fuente canonica:

- `amount_cents`;
- `currency`;
- `price_source`;
- `price_version`;
- `provider_payment_id`;
- `payment_intent_id`;
- `profile_subscription_id` activo;
- `status` arbitrario.

### F) Output conceptual
Output minimo futuro:

- `checkout_intent_uuid`;
- `checkout_status = pending_payment`;
- `contract_acceptance_uuid`;
- `acceptance_status = accepted_pending_payment`;
- `entity_type`;
- `entity_id`;
- `plan_code`;
- `billing_period`;
- `amount_cents`;
- `currency`;
- `price_source`;
- `price_version`;
- `price_uuid`, si viene del resolver;
- `contract_version`;
- `contract_hash`;
- `contract_snapshot_url`;
- `expires_at`, si aplica;
- `created_at`;
- `idempotent_replay`, si aplica.

El output alimentara la respuesta futura del endpoint y el resultado estable de idempotencia cuando corresponda.

### G) Orden recomendado del flujo futuro
Orden recomendado para `createCheckoutIntent(array $input): array`:

1. Normalizar input.
2. Validar auth/session scope si el endpoint no lo resolvio antes.
3. Validar `entity_type`/`entity_id`.
4. Validar `active_subscription_exists`.
5. Validar `Idempotency-Key`.
6. Calcular `request_hash`.
7. Entrar a idempotencia con operation `subscriptions.checkout_intent.create`.
8. Tomar lock `mxmed:subscriptions:{entity_type}:{entity_id}:checkout_create`.
9. Buscar checkout pending por entidad.
10. Si hay pending compatible:
    - replay si idempotencia corresponde;
    - si no, error `checkout_already_pending`.
11. Si hay pending incompatible:
    - error `checkout_already_pending`.
12. Resolver precio server-side.
13. Abrir transaccion.
14. Crear aceptacion contractual `accepted_pending_payment`.
15. Crear `subscription_checkout_intents` con status `pending_payment`.
16. Commit.
17. Guardar resultado idempotente.
18. Liberar lock.
19. Devolver respuesta minima.
20. Si falla algo:
    - rollback si la transaccion esta abierta;
    - liberar lock;
    - marcar idempotencia segun patron futuro.

### H) Pending checkout
Politica documental:

- `SubscriptionCheckoutIntentRepository` solo consulta pending.
- `CreateSubscriptionCheckoutIntentService` decide negocio.
- No cerrar pending automaticamente.
- No sobrescribir pending automaticamente.
- No crear un segundo pending concurrente.
- `checkout_already_pending` sale desde service/capa superior.

La capa superior debe distinguir pending compatible de pending incompatible, pero el replay estable debe depender de idempotencia, no de heuristicas del repositorio.

### I) Idempotencia
Readiness:

- Existe `SubscriptionWriteIdempotencyService`.
- El patron actual ya soporta key hash, request hash, estado `processing`, replay `completed`, payload distinto y `request_already_processing`.

Brechas:

- La operation actual esta fija en `subscriptions.create_with_contract_acceptance`.
- `markCompleted(...)` actual espera `subscription_id` y `contract_acceptance_uuid`; para checkout-intents debe admitir resultado con `checkout_intent_uuid` y snapshot de checkout.
- Se requiere extension o parametrizacion antes de usar operation `subscriptions.checkout_intent.create`.

Politica futura:

- Operation fija para checkout: `subscriptions.checkout_intent.create`.
- `request_hash` debe incluir entity, plan, billing, contrato y source relevante.
- Replay estable debe devolver el checkout intent existente o resultado guardado.
- Payload distinto debe devolver `idempotency_key_reused_with_different_payload`.
- `request_already_processing` debe evitar doble write.

### J) Lock
Readiness:

- Existe `SubscriptionEntityWriteLockService`.
- Usa advisory lock MySQL/MariaDB con `GET_LOCK` y `RELEASE_LOCK`.
- Libera por nombre de lock.

Brechas:

- El nombre actual usa operacion fija `create`.
- Para checkout-intents debe parametrizarse o extenderse para producir `mxmed:subscriptions:{entity_type}:{entity_id}:checkout_create`.

Politica futura:

- Lock fijo de checkout: `mxmed:subscriptions:{entity_type}:{entity_id}:checkout_create`.
- Debe envolver lookup pending + transaccion create.
- Timeout conceptual: `subscription_checkout_lock_timeout`.
- Debe prevenir doble checkout pending y doble aceptacion `accepted_pending_payment` con keys distintas.

### K) Transaccion
Decision de readiness:

- La transaccion superior debe vivir en `CreateSubscriptionCheckoutIntentService`.
- Debe cubrir:
  1. creacion de aceptacion `accepted_pending_payment`;
  2. creacion de checkout intent `pending_payment`.
- Debe evitar:
  - aceptacion huerfana;
  - checkout sin aceptacion;
  - doble checkout pending.
- Debe dejar fuera:
  - provider;
  - payment intent;
  - webhook;
  - facturacion;
  - activacion.

El servicio futuro probablemente necesitara `PDO` compartido con las dependencias que escriben, o una infraestructura equivalente que asegure que aceptacion + checkout intent participan en la misma unidad atomica.

### L) Validaciones y errores conceptuales
Errores del servicio orquestador:

- `invalid_checkout_intent_payload`;
- `unauthenticated`;
- `forbidden`;
- `entity_not_found`;
- `active_subscription_exists`;
- `checkout_already_pending`;
- `idempotency_key_invalid`;
- `idempotency_key_reused_with_different_payload`;
- `request_already_processing`;
- `subscription_checkout_lock_timeout`;
- `plan_not_contractable`;
- `billing_period_invalid`;
- `plan_price_not_configured`;
- `pricing_configuration_conflict`;
- `pricing_source_unavailable`;
- `contract_invalid`;
- `acceptance_source_invalid`;
- `contract_acceptance_create_failed`;
- `checkout_intent_create_failed`.

Origen esperado:

- Auth/scope y entidad: endpoint/capa superior o helper compartido.
- `active_subscription_exists`: componente de estado vigente o helper a definir.
- Pricing: `SubscriptionPlanPriceResolverService`.
- Aceptacion: `CreateSubscriptionPendingPaymentAcceptanceService`.
- Storage checkout: `SubscriptionCheckoutIntentRepository`.
- Idempotencia: `SubscriptionWriteIdempotencyService` extendido/parametrizado.
- Lock: `SubscriptionEntityWriteLockService` extendido/parametrizado.

### M) Responsabilidades fuera del servicio
`CreateSubscriptionCheckoutIntentService` no debe:

- exponer endpoint;
- parsear HTTP directamente;
- renderizar frontend;
- crear payment intents/events;
- llamar provider;
- manejar webhooks;
- activar `profile_subscriptions`;
- activar capacidades;
- facturar;
- tocar perfil publico/SEO.

### N) QA futura de implementacion
QA futura para cuando se implemente el servicio:

- `php -l` del nuevo archivo.
- Grep de clase/metodo.
- Grep de dependencias esperadas.
- Grep de `pending_payment`.
- Grep de `accepted_pending_payment`.
- Grep de prohibidos:
  - `profile_subscriptions`;
  - `subscription_payment_intents`;
  - `subscription_payment_events`;
  - `provider`;
  - `webhook`;
  - `PublicProfilePlanCapabilities`.
- Confirmar que no modifica `api/subscriptions/index.php`.
- Confirmar que no modifica endpoint contractual actual.
- Confirmar que no crea SQL.
- Confirmar que no ejecuta SQL.
- Si se permite prueba aislada con stubs, probar secuencia happy path sin DB real.
- QA posterior con DB solo en microfase explicita.

### O) Riesgos y brechas
Riesgos/brechas:

- Integracion de idempotencia puede requerir extension para guardar resultado checkout.
- Lock puede requerir parametrizacion `checkout_create`.
- `active_subscription_exists` puede requerir helper/repositorio claro.
- Transaccion superior requiere que dependencias compartan `PDO`.
- Pending checkout necesita politica final de respuesta/replay.
- No conectar provider todavia.
- No crear payment intent todavia.
- Endpoint queda para microfase posterior.

### P) Decisiones que se mantienen
Se reafirma:

- Primer write checkout-intents crea aceptacion `accepted_pending_payment`.
- Primer write checkout-intents crea `subscription_checkout_intents`.
- Primer write NO crea `profile_subscriptions`.
- Primer write NO crea payment intents.
- Primer write NO crea payment events.
- Checkout intent inicia `pending_payment`.
- Provider, webhook, facturacion y capacidades quedan fuera.
- Idempotencia checkout operation = `subscriptions.checkout_intent.create`.
- Lock checkout = `mxmed:subscriptions:{entity_type}:{entity_id}:checkout_create`.

### Q) Fuera de alcance
Esta adenda no implementa:

- servicio orquestador;
- endpoint;
- rutas;
- SQL;
- migraciones;
- DB/schema;
- provider;
- payment intents;
- payment events;
- webhook;
- activacion post-pago;
- `profile_subscriptions`;
- facturacion;
- capacidades;
- conexion con `PublicProfilePlanCapabilities`;
- perfil publico;
- SEO;
- frontend.

### R) Siguiente microfase recomendada
Siguiente microfase recomendada:

- `BE/SPEC-Suscripciones-CheckoutIntent-ServiceDesign-TransactionIdempotencyLock-01`.

Objetivo:

- Disenar documentalmente la secuencia exacta de transaccion + idempotencia + lock para `CreateSubscriptionCheckoutIntentService`, sin escribir codigo todavia.

---

## Adenda PP-Decisiones 83 - Diseno de transaccion, idempotencia y lock para checkout-intents

### A) Proposito
Esta adenda disena documentalmente la secuencia exacta de idempotencia, lock y transaccion para el futuro servicio `CreateSubscriptionCheckoutIntentService`.

Metodo futuro conceptual:

- `createCheckoutIntent(array $input): array`.

Esta adenda no implementa codigo. Solo define la secuencia que debera seguir el orquestador para crear, en el primer write futuro:

- aceptacion contractual `accepted_pending_payment`;
- checkout intent `pending_payment`.

### B) Componentes participantes
Componentes que participaran en el flujo futuro:

- `SubscriptionPlanPriceResolverService`.
- `CreateSubscriptionPendingPaymentAcceptanceService`.
- `SubscriptionCheckoutIntentRepository`.
- `SubscriptionWriteIdempotencyService`.
- `SubscriptionEntityWriteLockService`.
- `PDO` / conexion compartida para transaccion superior.
- Helper o repositorio futuro para validar `active_subscription_exists`, si no se reutiliza una pieza existente de forma segura.

Brechas observadas:

- `SubscriptionWriteIdempotencyService` usa hoy operation fija `subscriptions.create_with_contract_acceptance`.
- `SubscriptionEntityWriteLockService` usa hoy operation fija `create`.
- La validacion `active_subscription_exists` existe en el servicio contractual actual, pero esta acoplada a `CreateSubscriptionWithAcceptanceService` y a `profile_subscriptions`; debe extraerse o duplicarse con cuidado para checkout-first.

### C) Orden recomendado del flujo
Secuencia recomendada para `CreateSubscriptionCheckoutIntentService::createCheckoutIntent(array $input): array`:

1. Recibir input normalizado desde endpoint futuro.
2. Validar payload minimo.
3. Validar auth/session scope si no viene resuelto por el endpoint.
4. Validar `entity_type`/`entity_id`.
5. Validar `active_subscription_exists`.
6. Validar `Idempotency-Key`.
7. Calcular `request_hash`.
8. Registrar/entrar a idempotencia con operation `subscriptions.checkout_intent.create`.
9. Si hay replay estable, devolver resultado guardado sin nuevo write.
10. Si la key se reusa con payload distinto, responder `idempotency_key_reused_with_different_payload`.
11. Si la request ya esta en `processing`, responder `request_already_processing`.
12. Tomar lock `mxmed:subscriptions:{entity_type}:{entity_id}:checkout_create`.
13. Dentro del lock, revisar pending checkout con:
    - `SubscriptionCheckoutIntentRepository::findPendingByEntity(...)`;
    - `SubscriptionCheckoutIntentRepository::findPendingByEntityPlanAndBilling(...)`.
14. Si existe pending compatible y no es replay idempotente, responder `checkout_already_pending`.
15. Si existe pending incompatible, responder `checkout_already_pending`.
16. Resolver precio server-side con `SubscriptionPlanPriceResolverService::resolveForCheckout(...)`.
17. Abrir transaccion.
18. Crear aceptacion con `CreateSubscriptionPendingPaymentAcceptanceService::createPendingPaymentAcceptance(...)`.
19. Crear checkout intent con `SubscriptionCheckoutIntentRepository::create(...)`.
20. Ejecutar commit.
21. Guardar resultado idempotente.
22. Liberar lock.
23. Devolver respuesta minima.
24. Si falla algo:
    - rollback si la transaccion esta abierta;
    - liberar lock;
    - marcar/limpiar idempotencia segun patron futuro;
    - no dejar aceptacion huerfana;
    - no dejar checkout sin aceptacion.

### D) Limites de idempotencia
Operation fija para checkout:

- `subscriptions.checkout_intent.create`.

`request_hash` debe cubrir:

- `entity_type`;
- `entity_id`;
- `plan_code`;
- `billing_period`;
- `contract_version`;
- `contract_hash`;
- `contract_snapshot_url`;
- `acceptance_source`;
- `source`.

Reglas:

- Replay estable devuelve el resultado previamente guardado.
- Payload distinto se bloquea con `idempotency_key_reused_with_different_payload`.
- Estado `processing` evita doble write con `request_already_processing`.
- El resultado guardado debe incluir el checkout intent y la aceptacion contractual.

Brecha:

- Si el componente actual de idempotencia no guarda resultado checkout, debe extenderse o parametrizarse antes de implementar el orquestador.

### E) Limites de lock
Lock fijo:

- `mxmed:subscriptions:{entity_type}:{entity_id}:checkout_create`.

Debe envolver:

- lookup pending;
- resolucion final previa al write si aplica;
- transaccion acceptance + checkout.

Error conceptual:

- `subscription_checkout_lock_timeout`.

Brecha:

- `SubscriptionEntityWriteLockService` requiere parametrizacion o extension para generar `checkout_create`, porque hoy usa operation fija `create`.

### F) Limites de transaccion
La transaccion superior vive en:

- `CreateSubscriptionCheckoutIntentService`.

Debe cubrir:

- aceptacion `accepted_pending_payment`;
- checkout intent `pending_payment`.

Debe evitar:

- aceptacion huerfana;
- checkout sin aceptacion;
- doble checkout pending.

Debe dejar fuera:

- provider;
- payment intents;
- payment events;
- webhook;
- facturacion;
- activacion de capacidades;
- `profile_subscriptions`.

La implementacion futura debe asegurar que `CreateSubscriptionPendingPaymentAcceptanceService` y `SubscriptionCheckoutIntentRepository` usen la misma conexion/transaccion o un mecanismo equivalente.

### G) Pending checkout
Politica:

- El repository solo consulta.
- El service decide negocio.
- No cerrar pending automaticamente.
- No sobrescribir pending.
- No crear segundo pending concurrente.
- Error de negocio: `checkout_already_pending`.

El lookup compatible ayuda a informar decision, pero no reemplaza el replay idempotente. Replay estable debe venir de la capa de idempotencia.

### H) Errores conceptuales
Errores esperados del flujo:

- `invalid_checkout_intent_payload`;
- `unauthenticated`;
- `forbidden`;
- `entity_not_found`;
- `active_subscription_exists`;
- `checkout_already_pending`;
- `idempotency_key_invalid`;
- `idempotency_key_reused_with_different_payload`;
- `request_already_processing`;
- `subscription_checkout_lock_timeout`;
- `plan_not_contractable`;
- `billing_period_invalid`;
- `plan_price_not_configured`;
- `pricing_configuration_conflict`;
- `pricing_source_unavailable`;
- `contract_invalid`;
- `acceptance_source_invalid`;
- `contract_acceptance_create_failed`;
- `checkout_intent_create_failed`;
- `checkout_intent_transaction_failed`.

Origen esperado:

- Payload/auth/entity: endpoint o capa superior.
- Active subscription: helper/repositorio futuro.
- Idempotencia: `SubscriptionWriteIdempotencyService` extendido/parametrizado.
- Lock: `SubscriptionEntityWriteLockService` extendido/parametrizado.
- Pricing: `SubscriptionPlanPriceResolverService`.
- Aceptacion: `CreateSubscriptionPendingPaymentAcceptanceService`.
- Checkout storage: `SubscriptionCheckoutIntentRepository`.
- Transaccion: `CreateSubscriptionCheckoutIntentService`.

### I) Respuesta conceptual futura
Output futuro del service:

- `checkout_intent_uuid`;
- `checkout_status = pending_payment`;
- `contract_acceptance_uuid`;
- `acceptance_status = accepted_pending_payment`;
- `entity_type`;
- `entity_id`;
- `plan_code`;
- `billing_period`;
- `amount_cents`;
- `currency`;
- `price_source`;
- `price_version`;
- `price_uuid`, si viene del resolver;
- `contract_version`;
- `contract_hash`;
- `contract_snapshot_url`;
- `expires_at`, si aplica;
- `created_at`;
- `idempotent_replay`, si aplica.

La respuesta no debe incluir datos de provider, payment intent real, activacion ni suscripcion activa.

### J) Fuera de alcance
Esta adenda no implementa:

- servicio orquestador;
- endpoint;
- rutas;
- SQL;
- migraciones;
- DB/schema;
- provider;
- payment intents;
- payment events;
- webhook;
- activacion post-pago;
- `profile_subscriptions`;
- facturacion;
- capacidades;
- conexion con `PublicProfilePlanCapabilities`;
- perfil publico;
- SEO;
- frontend.

### K) QA futura
QA futura para implementacion posterior:

- `php -l` del nuevo servicio.
- Grep de clase/metodo.
- Grep de dependencias.
- Grep de `pending_payment`.
- Grep de `accepted_pending_payment`.
- Grep de prohibidos:
  - `profile_subscriptions`;
  - `subscription_payment_intents`;
  - `subscription_payment_events`;
  - `provider`;
  - `webhook`;
  - `PublicProfilePlanCapabilities`.
- Confirmar que no modifica endpoint.
- Confirmar que no modifica SQL.
- Prueba aislada con stubs solo si se disena explicitamente.
- QA DB/local solo en microfase posterior autorizada.

### L) Riesgos y brechas
Riesgos/brechas:

- Extension de idempotencia para operation `subscriptions.checkout_intent.create`.
- Extension de idempotencia para guardar respuesta con `checkout_intent_uuid`.
- Parametrizacion de lock para `checkout_create`.
- Helper/repositorio para `active_subscription_exists`.
- Garantizar conexion compartida para transaccion acceptance + checkout.
- Evitar aceptaciones huerfanas si falla el checkout intent.
- Evitar checkout sin aceptacion si falla la aceptacion.
- Mantener provider/payment/webhook fuera del primer write.

### M) Siguiente microfase recomendada
Siguiente microfase recomendada:

- `BE/SPEC-Suscripciones-CheckoutIntent-ServiceImplementation-Plan-01`.

Objetivo:

- Planificar la implementacion minima de `CreateSubscriptionCheckoutIntentService`, sin escribir codigo todavia.

---

## Adenda PP-Decisiones 84 - Plan de implementacion minima de CreateSubscriptionCheckoutIntentService

### A) Proposito
Esta adenda documenta el plan minimo para implementar posteriormente:

- `CreateSubscriptionCheckoutIntentService::createCheckoutIntent(array $input): array`.

No escribe codigo y no crea el servicio. Convierte el diseno cerrado en PP-Decisiones 83 en un plan tecnico de implementacion y clasifica brechas previas.

Esta adenda no reabre:

- checkout-first;
- primer write;
- `accepted_pending_payment`;
- `pending_payment`;
- resolver server-side de precios;
- operation de idempotencia `subscriptions.checkout_intent.create`;
- lock `checkout_create`.

### B) Estado base confirmado
Ya existen:

- `CreateSubscriptionPendingPaymentAcceptanceService`.
- `SubscriptionCheckoutIntentRepository`.
- `SubscriptionPlanPriceResolverService`.
- `SubscriptionWriteIdempotencyService`.
- `SubscriptionEntityWriteLockService`.
- Schema local/dev de `subscription_checkout_intents`.
- Schema local/dev de `subscription_plan_prices`.
- Seed DEV/local de precios.

Todavia no existe:

- `CreateSubscriptionCheckoutIntentService`.
- Endpoint `POST /api/subscriptions/index.php/entities/{entity_type}/{entity_id}/checkout-intents`.
- Integracion real de idempotencia checkout.
- Integracion real de lock checkout.
- Transaccion coordinada acceptance + checkout intent.
- Provider.
- Payment intents/events.
- Webhooks.
- Facturacion.
- Activacion de capacidades.
- `profile_subscriptions` dentro de checkout-first.

### C) Contrato conceptual del servicio futuro
Archivo futuro:

- `modules/subscriptions/services/CreateSubscriptionCheckoutIntentService.php`.

Clase futura:

- `CreateSubscriptionCheckoutIntentService`.

Metodo futuro:

- `createCheckoutIntent(array $input): array`.

Responsabilidad:

- validar input;
- validar entidad;
- validar ausencia de suscripcion activa;
- validar idempotencia;
- tomar lock checkout;
- revisar pending checkout;
- resolver precio server-side;
- abrir transaccion;
- crear aceptacion `accepted_pending_payment`;
- crear checkout intent `pending_payment`;
- guardar resultado idempotente;
- devolver respuesta minima.

### D) Dependencias minimas esperadas
Dependencias esperadas del constructor o factory futuro:

- `PDO` o conexion compartida.
- `SubscriptionPlanPriceResolverService`.
- `CreateSubscriptionPendingPaymentAcceptanceService`.
- `SubscriptionCheckoutIntentRepository`.
- `SubscriptionWriteIdempotencyService`.
- `SubscriptionEntityWriteLockService`.
- Helper/repository futuro para `active_subscription_exists`.
- Helper futuro de validacion `entity_type`/`entity_id` si no se extrae desde endpoint/capa superior.
- Reloj/fecha UTC si aplica.
- Generador UUID solo si el servicio lo requiere; preferentemente el UUID queda en servicios/repositorios existentes cuando aplique.

No son dependencias del servicio minimo:

- provider;
- payment intents/events;
- webhooks;
- facturacion;
- capacidades;
- `PublicProfilePlanCapabilities`.

### E) Input minimo esperado
Campos conceptuales minimos de `createCheckoutIntent(array $input): array`:

- `entity_type`;
- `entity_id`;
- `plan_code`;
- `billing_period`;
- `contract_version`;
- `contract_hash`;
- `contract_snapshot_url`;
- `acceptance_source`;
- `source`;
- `idempotency_key`;
- actor/session context si aplica;
- request metadata minimo si aplica.

El cliente no envia:

- `amount_cents`;
- `currency`;
- `price_source`;
- `price_version`;
- `price_uuid`.

El precio siempre se resuelve server-side mediante `SubscriptionPlanPriceResolverService::resolveForCheckout(...)`.

### F) Output minimo esperado
Respuesta conceptual:

- `checkout_intent_uuid`;
- `checkout_status = pending_payment`;
- `contract_acceptance_uuid`;
- `acceptance_status = accepted_pending_payment`;
- `entity_type`;
- `entity_id`;
- `plan_code`;
- `billing_period`;
- `amount_cents`;
- `currency`;
- `price_source`;
- `price_version`;
- `price_uuid`, si viene del resolver;
- `contract_version`;
- `contract_hash`;
- `contract_snapshot_url`;
- `expires_at`, si aplica;
- `created_at`;
- `idempotency_replay`, si aplica.

No debe devolver datos de provider, payment intent, activacion ni suscripcion activa.

### G) Secuencia de implementacion futura
Orden interno recomendado del metodo futuro:

1. Normalizar input.
2. Validar payload minimo.
3. Validar auth/session scope si no viene resuelto.
4. Validar `entity_type`/`entity_id`.
5. Validar ausencia de suscripcion activa.
6. Validar `Idempotency-Key`.
7. Calcular `request_hash`.
8. Entrar a idempotencia con operation `subscriptions.checkout_intent.create`.
9. Resolver replay estable si existe.
10. Bloquear payload distinto.
11. Bloquear request `processing`.
12. Tomar lock `mxmed:subscriptions:{entity_type}:{entity_id}:checkout_create`.
13. Revisar pending checkout con:
    - `findPendingByEntity`;
    - `findPendingByEntityPlanAndBilling`.
14. Rechazar pending existente con `checkout_already_pending`.
15. Resolver precio server-side con `SubscriptionPlanPriceResolverService::resolveForCheckout(...)`.
16. Abrir transaccion superior en este servicio.
17. Crear aceptacion con `CreateSubscriptionPendingPaymentAcceptanceService::createPendingPaymentAcceptance(...)`.
18. Crear checkout intent con `SubscriptionCheckoutIntentRepository::create(...)`.
19. Commit.
20. Guardar resultado idempotente.
21. Liberar lock.
22. Devolver respuesta.
23. En error:
    - rollback si transaccion abierta;
    - liberar lock;
    - marcar/limpiar idempotencia segun patron futuro;
    - no dejar aceptacion huerfana;
    - no dejar checkout sin aceptacion.

### H) Brechas clasificadas
#### 1. `SubscriptionWriteIdempotencyService`

- Operation configurable: requiere ajuste previo.
- Operation `subscriptions.checkout_intent.create`: requiere ajuste previo.
- Guardar resultado checkout: requiere ajuste previo.
- Replay estable del resultado checkout: requiere ajuste previo.
- Manejo de `processing`/failure: listo parcialmente; existe patron, pero debe parametrizarse para checkout.

Clasificacion: bloqueante antes de implementar `CreateSubscriptionCheckoutIntentService`, porque el servicio futuro depende de operation y replay correctos.

#### 2. `SubscriptionEntityWriteLockService`

- Lock purpose configurable: requiere ajuste previo.
- `checkout_create`: requiere ajuste previo.
- Nombre final `mxmed:subscriptions:{entity_type}:{entity_id}:checkout_create`: requiere parametrizacion o extension.
- Timeout esperado: listo parcialmente, existe timeout en `acquire(...)`.

Clasificacion: bloqueante antes de implementar el servicio si la implementacion debe usar el lock exacto cerrado documentalmente.

#### 3. Transaccion

- Repositorios/servicios usan `PDO` compartible: listo para implementacion si se construyen con la misma conexion.
- `CreateSubscriptionPendingPaymentAcceptanceService` no abre transaccion propia: listo.
- `SubscriptionCheckoutIntentRepository` no abre transaccion propia: listo.
- El servicio orquestador puede abrir transaccion superior: listo para implementar con `PDO`.

Clasificacion: listo para implementacion.

#### 4. Active subscription

- Helper/repository listo para `active_subscription_exists`: requiere ajuste previo.
- Existe logica privada en `CreateSubscriptionWithAcceptanceService`, pero esta acoplada al flujo contractual actual y no debe reutilizarse copiando efectos secundarios.

Clasificacion: bloqueante o ajuste previo recomendado, porque checkout debe validar ausencia de suscripcion activa sin crear `profile_subscriptions`.

#### 5. Validacion entity

- Validacion reutilizable de `entity_type`/`entity_id`: requiere ajuste previo o definicion explicita de contrato de input desde endpoint.
- El endpoint actual resuelve contexto de escritura, pero no existe ruta checkout-intents ni helper independiente para el servicio.

Clasificacion: requiere ajuste previo; puede ser no bloqueante si la implementacion del servicio asume input ya normalizado por capa superior, pero debe quedar explicitado antes de escribir codigo.

### I) Decision de implementacion por etapas
Decision: no implementar todavia `CreateSubscriptionCheckoutIntentService`.

La siguiente microfase debe planificar primero los ajustes minimos de dependencias, porque hay brechas bloqueantes en:

- idempotencia para operation `subscriptions.checkout_intent.create`;
- guardado/replay de resultado checkout;
- lock `checkout_create`;
- helper o contrato claro para `active_subscription_exists`;
- contrato de validacion de entidad.

Siguiente microfase recomendada:

- `BE/SPEC-Suscripciones-CheckoutIntent-DependenciesImplementation-Plan-01`.

Objetivo:

- Planificar ajustes minimos previos de idempotencia, lock y helpers necesarios antes de implementar `CreateSubscriptionCheckoutIntentService`.

### J) Errores conceptuales del servicio futuro
Errores que debe manejar o propagar el servicio futuro:

- `invalid_checkout_intent_payload`;
- `unauthenticated`;
- `forbidden`;
- `entity_not_found`;
- `active_subscription_exists`;
- `checkout_already_pending`;
- `idempotency_key_invalid`;
- `idempotency_key_reused_with_different_payload`;
- `request_already_processing`;
- `subscription_checkout_lock_timeout`;
- `plan_not_contractable`;
- `billing_period_invalid`;
- `plan_price_not_configured`;
- `pricing_configuration_conflict`;
- `pricing_source_unavailable`;
- `contract_invalid`;
- `acceptance_source_invalid`;
- `contract_acceptance_create_failed`;
- `checkout_intent_create_failed`;
- `checkout_intent_transaction_failed`;
- `checkout_intent_unavailable`.

### K) Fuera de alcance
Esta adenda no implementa:

- servicio orquestador;
- endpoint;
- rutas;
- SQL;
- migraciones;
- DB/schema;
- provider;
- payment intents;
- payment events;
- webhook;
- activacion post-pago;
- `profile_subscriptions`;
- facturacion;
- capacidades;
- conexion con `PublicProfilePlanCapabilities`;
- perfil publico;
- SEO;
- frontend.

### L) QA futura
QA futura para la implementacion posterior:

- `php -l` del nuevo servicio.
- Grep clase `CreateSubscriptionCheckoutIntentService`.
- Grep metodo `createCheckoutIntent`.
- Grep dependencies esperadas.
- Grep `subscriptions.checkout_intent.create`.
- Grep `checkout_create`.
- Grep `pending_payment`.
- Grep `accepted_pending_payment`.
- Grep prohibidos:
  - `profile_subscriptions`;
  - `subscription_payment_intents`;
  - `subscription_payment_events`;
  - `provider`;
  - `webhook`;
  - `PublicProfilePlanCapabilities`.
- Confirmar que no modifica endpoint.
- Confirmar que no crea SQL.
- Confirmar que no ejecuta SQL.
- Prueba aislada con stubs solo si se disena explicitamente.
- QA DB/local solo en microfase posterior autorizada.

### M) Siguiente microfase recomendada
Siguiente microfase recomendada:

- `BE/SPEC-Suscripciones-CheckoutIntent-DependenciesImplementation-Plan-01`.

Objetivo:

- Planificar ajustes minimos previos de idempotencia, lock y helpers necesarios antes de implementar `CreateSubscriptionCheckoutIntentService`.

---

## Adenda PP-Decisiones 85 - Plan de dependencias previas para CreateSubscriptionCheckoutIntentService

### A) Proposito
Esta adenda planifica los ajustes minimos previos necesarios antes de implementar:

- `CreateSubscriptionCheckoutIntentService::createCheckoutIntent(array $input): array`.

Esta adenda no implementa codigo y no reabre decisiones cerradas sobre:

- checkout-first;
- primer write;
- `accepted_pending_payment`;
- `pending_payment`;
- resolver server-side de precios;
- operation de idempotencia `subscriptions.checkout_intent.create`;
- lock `mxmed:subscriptions:{entity_type}:{entity_id}:checkout_create`.

### B) Brechas bloqueantes heredadas de PP-Decisiones 84
Brechas bloqueantes antes del servicio orquestador:

1. Idempotencia checkout:
   - operation checkout `subscriptions.checkout_intent.create`;
   - `request_hash` especifico de checkout;
   - replay estable del resultado checkout;
   - bloqueo de payload distinto;
   - estado processing/failure para evitar doble write.

2. Lock checkout:
   - purpose `checkout_create`;
   - nombre final `mxmed:subscriptions:{entity_type}:{entity_id}:checkout_create`;
   - timeout conceptual `subscription_checkout_lock_timeout`.

3. Active subscription:
   - helper/repository para validar `active_subscription_exists`;
   - debe evitar crear checkout intent si la entidad ya tiene suscripcion activa.

4. Validacion entity:
   - contrato minimo para validar `entity_type`/`entity_id`;
   - errores `entity_not_found`, `forbidden` y `unauthenticated`.

### C) Inspeccion de dependencias actuales
#### 1. SubscriptionWriteIdempotencyService
Estado observado:

- Expone flujo de begin/replay/reject/proceed, `markCompleted(...)` y `markFailed(...)`.
- La operation actual esta fija como `subscriptions.create_with_contract_acceptance`.
- El calculo de `request_hash` esta acoplado a esa operation y al payload contractual actual.
- Puede distinguir replay, payload distinto y request en processing.
- Guarda resultado, pero `markCompleted(...)` espera `subscription_id` y `contract_acceptance_uuid`.

Brecha exacta para checkout:

- No permite todavia operation parametrizable `subscriptions.checkout_intent.create`.
- No tiene todavia `request_hash` checkout cerrado.
- No guarda/reproduce de forma directa un resultado checkout con `checkout_intent_uuid`.

#### 2. SubscriptionWriteIdempotencyRepository
Estado observado:

- `findByScope(...)` recibe operation como parametro y por eso el storage puede reutilizarse por operation.
- `insertProcessing(...)` persiste operation, scope, hash, status, lock temporal y metadata.
- `markCompleted(...)` esta orientado a `subscription_id` + `contract_acceptance_uuid`.
- `markFailed(...)` existe para cerrar fallo controlado.

Brecha exacta para checkout:

- Requiere ajuste de metodo o contrato para completar idempotencia con resultado checkout sin exigir `subscription_id` activo.
- Debe preservar replay estable del checkout intent creado.

#### 3. SubscriptionEntityWriteLockService
Estado observado:

- Expone `acquire(string $entityType, string $entityId, int $timeoutSeconds = 2): ?string`.
- Expone `release(string $lockName): void`.
- Usa prefijo `mxmed:subscriptions`.
- El operation/purpose actual esta fijo como `create`.
- El timeout ya esta contemplado.

Brecha exacta para checkout:

- No permite todavia purpose configurable.
- No puede producir aun el lock exacto `mxmed:subscriptions:{entity_type}:{entity_id}:checkout_create`.

#### 4. Active subscription
Estado observado:

- Existe logica privada en `CreateSubscriptionWithAcceptanceService` para consultar `profile_subscriptions` y bloquear estados activos.
- `CurrentSubscriptionRepository` tambien lee `profile_subscriptions` para obtener candidato actual.
- La logica reutilizable no esta separada como dependencia limpia para checkout.

Brecha exacta:

- Se requiere helper/repository/servicio read-only para `active_subscription_exists` sin crear `profile_subscriptions` ni acoplarse al flujo contractual actual.

#### 5. Validacion entity
Estado observado:

- `api/subscriptions/index.php` contiene validaciones de contexto, scope, session/header local y entidad.
- La validacion esta hoy en el entry point y orientada principalmente a `doctor`.
- No existe aun helper independiente para que el futuro servicio checkout lo consuma.

Brecha exacta:

- Se requiere helper separado o contrato explicito de input normalizado para `entity_type`/`entity_id`, `unauthenticated`, `forbidden` y `entity_not_found`.

### D) Estrategia de implementacion por dependencias
#### 1. Idempotencia checkout
Objetivo:

- Soportar `subscriptions.checkout_intent.create`, `request_hash` checkout y replay estable del resultado checkout.

Archivos candidatos futuros:

- `modules/subscriptions/services/SubscriptionWriteIdempotencyService.php`;
- `modules/subscriptions/repositories/SubscriptionWriteIdempotencyRepository.php`.

Alcance permitido:

- Ajuste minimo para parametrizar operation y resultado checkout.
- Mantener compatible el flujo actual `subscriptions.create_with_contract_acceptance`.
- No endpoint, no SQL y no DB/schema salvo microfase explicita posterior si se demostrara insuficiencia del storage.

Riesgos:

- Romper replay del flujo contractual actual.
- Mantener una semantica incompleta si `markCompleted(...)` sigue exigiendo `subscription_id`.

QA esperada:

- `php -l` de archivos modificados.
- Grep de `subscriptions.checkout_intent.create`.
- Grep de operation actual para confirmar compatibilidad.
- Grep de `request_hash`, replay, payload distinto y processing.
- Confirmar sin endpoint, sin SQL, sin provider y sin payment intents/events.

#### 2. Lock checkout
Objetivo:

- Permitir lock purpose `checkout_create` y lock final `mxmed:subscriptions:{entity_type}:{entity_id}:checkout_create`.

Archivo candidato futuro:

- `modules/subscriptions/services/SubscriptionEntityWriteLockService.php`.

Alcance permitido:

- Parametrizar purpose o agregar metodo checkout-specific sin alterar el lock `create` existente.
- No endpoint, no SQL y no DB/schema.

Riesgos:

- Cambiar accidentalmente el lock del flujo contractual actual.
- No liberar el lock si el futuro servicio orquestador falla.

QA esperada:

- `php -l`.
- Grep de `checkout_create`.
- Grep de `mxmed:subscriptions`.
- Confirmar que `release(...)` sigue disponible y que el lock actual no se rompe.

#### 3. Active subscription helper
Objetivo:

- Proveer validacion reutilizable de `active_subscription_exists` antes de crear checkout intent.

Archivos candidatos futuros:

- nuevo helper/servicio/repository read-only en `modules/subscriptions`;
- o extension controlada de repository existente si se decide en microfase futura.

Alcance permitido:

- Lectura de `profile_subscriptions` para estados activos/incompatibles.
- No inserts, no activacion, no modificacion de suscripciones.

Riesgos:

- Duplicar la logica privada actual con criterios distintos.
- Confundir lectura de estado activo con creacion de `profile_subscriptions`.

QA esperada:

- `php -l`.
- Grep de `active_subscription_exists`.
- Grep para confirmar que cualquier referencia a `profile_subscriptions` es lectura estricta.
- Grep negativo de `INSERT INTO profile_subscriptions`.

#### 4. Entity validation helper
Objetivo:

- Definir validacion reutilizable o contrato explicito de `entity_type`/`entity_id` para checkout.

Archivos candidatos futuros:

- helper/servicio de scope de suscripciones;
- o contrato documentado entre endpoint futuro y `CreateSubscriptionCheckoutIntentService`.

Alcance permitido:

- Validar `entity_type`, `entity_id`, actor/session context y errores `unauthenticated`, `forbidden`, `entity_not_found`.
- No crear ruta checkout-intents todavia.

Riesgos:

- Duplicar reglas de `api/subscriptions/index.php`.
- Abrir soporte de entidades no definidas antes de tiempo.

QA esperada:

- Grep de `unauthenticated`, `forbidden`, `entity_not_found`.
- Confirmar que no se modifica endpoint hasta microfase explicita.

### E) Orden recomendado
Orden recomendado de ejecucion:

1. Plan/ajuste idempotencia checkout.
2. Plan/ajuste lock checkout.
3. Plan/ajuste helper `active_subscription_exists`.
4. Plan/ajuste helper entity validation.
5. Readiness final de dependencias.
6. Implementacion futura de `CreateSubscriptionCheckoutIntentService`.

### F) Criterios de aceptacion antes de implementar CreateSubscriptionCheckoutIntentService
No se debe implementar el servicio orquestador hasta que exista o quede confirmado:

- idempotencia parametrizable para operation `subscriptions.checkout_intent.create`;
- `request_hash` checkout estable;
- replay estable con resultado checkout o decision documentada alternativa;
- bloqueo de payload distinto;
- manejo de `request_already_processing`;
- lock parametrizable `checkout_create`;
- helper o metodo para `active_subscription_exists`;
- helper o contrato claro para validar `entity_type`/`entity_id`;
- confirmacion de que los servicios/repositorios participantes comparten `PDO` o pueden operar bajo transaccion superior;
- QA documental o backend de cada dependencia.

### G) Errores conceptuales por dependencia
Idempotencia:

- `idempotency_key_invalid`;
- `idempotency_key_reused_with_different_payload`;
- `request_already_processing`;
- `checkout_intent_unavailable`.

Lock:

- `subscription_checkout_lock_timeout`.

Active subscription:

- `active_subscription_exists`.

Entity validation:

- `unauthenticated`;
- `forbidden`;
- `entity_not_found`.

Transaccion futura:

- `checkout_intent_transaction_failed`;
- `contract_acceptance_create_failed`;
- `checkout_intent_create_failed`.

### H) Fuera de alcance
Esta adenda no implementa:

- servicio orquestador;
- endpoint;
- rutas;
- SQL;
- migraciones;
- DB/schema;
- provider;
- payment intents;
- payment events;
- webhook;
- activacion post-pago;
- `profile_subscriptions`;
- facturacion;
- capacidades;
- conexion con `PublicProfilePlanCapabilities`;
- perfil publico;
- SEO;
- frontend.

### I) QA futura
QA futura para las dependencias:

- `php -l` de archivos modificados en microfases posteriores.
- Grep de operation `subscriptions.checkout_intent.create`.
- Grep de `checkout_create`.
- Grep de `request_hash`.
- Grep de replay.
- Grep de `active_subscription_exists`.
- Grep de `entity_not_found` / `forbidden`.
- Grep prohibidos:
  - `subscription_payment_intents`;
  - `subscription_payment_events`;
  - `provider`;
  - `webhook`;
  - `PublicProfilePlanCapabilities`;
  - `profile_subscriptions`, salvo lectura estricta para `active_subscription_exists` si se documenta explicitamente.
- Confirmar que no modifica endpoint hasta microfase explicita.
- Confirmar que no crea SQL salvo microfase DB explicita.
- Confirmar que no ejecuta SQL salvo microfase autorizada.
- Confirmar que no toca frontend/perfil publico/SEO.

### J) Siguiente microfase recomendada
Siguiente microfase recomendada:

- `BE/SPEC-Suscripciones-CheckoutIntent-IdempotencyDependency-Plan-01`.

Objetivo:

- Planificar el ajuste minimo de idempotencia para soportar `subscriptions.checkout_intent.create`, `request_hash` checkout y replay estable.

---

## Adenda PP-Decisiones 86 - Plan de idempotencia checkout para CreateSubscriptionCheckoutIntentService

### A) Proposito
Esta adenda planifica el ajuste minimo de idempotencia para el futuro checkout-intent:

- `CreateSubscriptionCheckoutIntentService::createCheckoutIntent(array $input): array`.

Esta adenda no implementa codigo y no reabre decisiones cerradas sobre:

- checkout-first;
- primer write;
- `accepted_pending_payment`;
- `pending_payment`;
- resolver server-side de precios;
- operation fija para checkout `subscriptions.checkout_intent.create`.

### B) Estado actual inspeccionado
#### 1. SubscriptionWriteIdempotencyService
Estado observado:

- Expone `begin(...)`, `markCompleted(...)` y `markFailed(...)`.
- `begin(...)` valida `Idempotency-Key`, calcula hash de key, calcula `request_hash`, inserta estado `processing`, detecta replay, payload distinto y request en processing.
- La operation actual esta fija como `subscriptions.create_with_contract_acceptance`.
- `requestHash(...)` construye un canonical hash acoplado al flujo contractual actual.
- Replay completed usa `response_body_text` si existe; si no existe, reconstruye una respuesta minima con `subscription_id` y `contract_acceptance_uuid`.
- `markCompleted(...)` esta acoplado a `subscription_id` y `contract_acceptance_uuid`; si alguno falta, no completa la idempotencia.
- `markFailed(...)` marca failure con status HTTP.
- La dependencia actual es `SubscriptionWriteIdempotencyRepository`.

Brecha checkout:

- No acepta todavia operation parametrizable.
- No tiene builder de `request_hash` checkout.
- No puede completar idempotencia de checkout si no hay `subscription_id` activo.
- Debe poder guardar/reproducir resultado checkout con `checkout_intent_uuid`.

#### 2. SubscriptionWriteIdempotencyRepository
Estado observado:

- `findByScope(...)` ya recibe `operation` como parametro.
- `insertProcessing(...)` guarda `idempotency_key_hash`, `request_hash`, `entity_type`, `entity_id`, `doctor_id`, `profile_id`, `user_id`, `actor_role`, `operation`, `status`, `locked_at`, `expires_at` y `source`.
- El schema versionado incluye `operation`, `request_hash`, `status`, `subscription_id`, `contract_acceptance_uuid`, `response_http_status` y `response_body_text`.
- `markCompleted(...)` actualiza `status = completed`, `subscription_id`, `contract_acceptance_uuid`, `response_http_status`, `response_body_text` y `completed_at`.
- `subscription_id` es nullable en schema, pero la firma actual del metodo exige string.
- `response_body_text` permite guardar un payload JSON sanitizado.

Reutilizacion sin SQL:

- El storage existente parece suficiente para checkout si se usa `response_body_text` como fuente de replay estable.
- No se requiere SQL/schema para planificar el primer ajuste PHP.

Brecha checkout:

- La firma y semantica de `markCompleted(...)` deben permitir completed sin `subscription_id` activo.
- Debe guardarse un resultado checkout completo o al menos suficiente en `response_body_text`.

#### 3. CreateSubscriptionWithAcceptanceService
Estado observado:

- El endpoint contractual actual crea `SubscriptionWriteIdempotencyService`, llama `begin(...)`, maneja reject/replay, marca failed en errores y llama `markCompleted(...)` con respuesta 201.
- El flujo actual usa operation contractual `subscriptions.create_with_contract_acceptance`.
- El servicio contractual actual crea aceptacion `accepted`, crea `profile_subscriptions` y asume activacion inmediata.

Compatibilidad obligatoria:

- El flujo contractual actual debe permanecer intacto.
- No debe cambiar la semantica de `create_with_contract_acceptance`.
- No debe cambiarse el endpoint contractual actual en esta fase.
- Checkout debe agregarse como operation adicional, no como reemplazo del flujo existente.

### C) Requerimiento checkout
Contrato de idempotencia para checkout:

- Operation: `subscriptions.checkout_intent.create`.

El `request_hash` checkout debe cubrir:

- `entity_type`;
- `entity_id`;
- `plan_code`;
- `billing_period`;
- `contract_version`;
- `contract_hash`;
- `contract_snapshot_url`;
- `acceptance_source`;
- `source`.

La idempotencia checkout debe soportar:

- validar `Idempotency-Key`;
- calcular o recibir `request_hash` estable;
- crear/entrar en estado `processing`;
- detectar replay estable;
- bloquear payload distinto;
- bloquear request ya en `processing`;
- marcar completed con resultado checkout;
- devolver resultado checkout en replay;
- marcar failure o liberar estado segun patron documentado;
- no crear doble aceptacion;
- no crear doble checkout intent.

### D) Resultado idempotente checkout
Resultado minimo recomendado para replay estable:

- `checkout_intent_uuid`;
- `checkout_status`;
- `contract_acceptance_uuid`;
- `acceptance_status`;
- `entity_type`;
- `entity_id`;
- `plan_code`;
- `billing_period`;
- `amount_cents`;
- `currency`;
- `price_source`;
- `price_version`;
- `price_uuid`, si aplica;
- `contract_version`;
- `contract_hash`;
- `contract_snapshot_url`;
- `created_at`.

Decision recomendada:

- Usar la opcion 1: extender idempotency repository/service para guardar response payload si la tabla ya lo soporta.

Justificacion:

- El schema existente ya contempla `response_body_text`.
- `subscription_id` es nullable en schema.
- El repository actual ya persiste `response_body_text` al completar.
- El cambio requerido parece de contrato PHP, no de DB/schema.
- El replay estable puede basarse en el JSON de respuesta checkout guardado en `response_body_text`.

Alternativa de respaldo:

- Si durante la implementacion se detecta que `response_body_text` no es suficiente o no esta disponible en un ambiente, se debe detener y abrir microfase DB/SPEC antes de modificar schema.

### E) Estrategia de compatibilidad
Reglas para no romper el flujo contractual existente:

- Mantener operation contractual `subscriptions.create_with_contract_acceptance`.
- Agregar soporte checkout como operation adicional `subscriptions.checkout_intent.create`.
- No cambiar semantica actual del endpoint contractual.
- No cambiar replay actual contractual.
- No cambiar comportamiento de `create_with_contract_acceptance`.
- No cambiar estructura de respuesta contractual salvo microfase explicita.
- Preferir metodos nuevos o parametrizacion conservadora con wrappers compatibles.
- Mantener `begin(...)`, `markCompleted(...)` y `markFailed(...)` actuales funcionando para el flujo existente.

### F) Diseno de API interna futura
Contrato conceptual permitido para una microfase posterior, sin implementarlo aqui:

- `beginOperation(?string $headerValue, array $scope, array $payload, string $operation): SubscriptionWriteIdempotencyDecision`
  - Variante parametrizable de `begin(...)`.

- `buildCheckoutRequestHash(array $scope, array $payload): string`
  - Canonicaliza solo los campos checkout cerrados documentalmente.

- `completeOperation(array $record, array $response, int $httpStatus, array $references = []): void`
  - Permite completar operaciones que no tienen `subscription_id` activo.

- `completeCheckoutIntent(array $record, array $response, int $httpStatus): void`
  - Alternativa especifica si se prefiere no exponer API generica.

- `failOperation(array $record, int $httpStatus): void`
  - Wrapper compatible sobre `markFailed(...)` si se parametriza el flujo.

- `resolveReplayResult(array $idempotencyRow): ?array`
  - Lee `response_body_text` y devuelve replay estable.

La implementacion futura debe elegir la forma minima que preserve compatibilidad. No debe exigir cambios al endpoint contractual actual.

### G) Errores conceptuales
Errores conceptuales relacionados con esta dependencia:

- `idempotency_key_invalid`;
- `idempotency_key_reused_with_different_payload`;
- `request_already_processing`;
- `checkout_intent_unavailable`;
- `checkout_idempotency_result_unavailable`;
- `checkout_idempotency_complete_failed`;
- `checkout_idempotency_failure_mark_failed`.

### H) Decision por microfases
Decision: la inspeccion no muestra necesidad inmediata de SQL/schema para el primer ajuste de idempotencia checkout.

Ruta recomendada:

1. `BE/SPEC-Suscripciones-CheckoutIntent-IdempotencyDependency-Implementation-Readiness-01`.
2. `BE/Suscripciones-CheckoutIntent-IdempotencyDependency-01`.
3. `QA-Suscripciones-CheckoutIntent-IdempotencyDependency-PostPush-01`.

La microfase de readiness debe confirmar antes de codigo:

- que `response_body_text` esta disponible en el ambiente objetivo;
- que `subscription_id` nullable no bloquea completed checkout;
- que la firma nueva o metodo nuevo no rompe `markCompleted(...)` contractual;
- que el replay contractual actual sigue funcionando.

### I) Fuera de alcance
Esta adenda no implementa:

- servicio orquestador;
- endpoint;
- rutas;
- SQL;
- migraciones;
- DB/schema;
- provider;
- payment intents;
- payment events;
- webhook;
- activacion post-pago;
- `profile_subscriptions`;
- facturacion;
- capacidades;
- conexion con `PublicProfilePlanCapabilities`;
- perfil publico;
- SEO;
- frontend.

### J) QA futura
QA futura para la implementacion posterior:

- `php -l modules/subscriptions/services/SubscriptionWriteIdempotencyService.php`.
- `php -l modules/subscriptions/repositories/SubscriptionWriteIdempotencyRepository.php`, si se modifica.
- Grep de `subscriptions.checkout_intent.create`.
- Grep de `create_with_contract_acceptance` para confirmar compatibilidad.
- Grep de `request_hash`.
- Grep de `checkout_intent_uuid` o result payload segun decision.
- Grep de `idempotency_key_reused_with_different_payload`.
- Grep de `request_already_processing`.
- Grep prohibidos:
  - `subscription_payment_intents`;
  - `subscription_payment_events`;
  - `provider`;
  - `webhook`;
  - `PublicProfilePlanCapabilities`;
  - `profile_subscriptions`, salvo compatibilidad contractual existente.
- Confirmar que no modifica endpoint.
- Confirmar que no crea SQL salvo microfase DB explicita.
- Confirmar que no ejecuta SQL salvo microfase autorizada.
- Confirmar que no toca frontend/perfil publico/SEO.

### K) Siguiente microfase recomendada
Siguiente microfase recomendada:

- `BE/SPEC-Suscripciones-CheckoutIntent-IdempotencyDependency-Implementation-Readiness-01`.

Objetivo:

- Validar readiness tecnica final para ajustar `SubscriptionWriteIdempotencyService` y, si hace falta, `SubscriptionWriteIdempotencyRepository` para operation `subscriptions.checkout_intent.create`, `request_hash` checkout y replay estable sin SQL/schema.

---

## Adenda PP-Decisiones 87 - Readiness de implementacion de idempotencia checkout

### A) Proposito
Esta adenda valida la readiness de implementacion para agregar soporte de idempotencia checkout sin SQL/schema inmediato y sin modificar endpoint.

Esta adenda no implementa codigo y no reabre decisiones cerradas sobre:

- checkout-first;
- primer write;
- `accepted_pending_payment`;
- `pending_payment`;
- resolver server-side de precios;
- operation checkout `subscriptions.checkout_intent.create`;
- uso de `response_body_text` para replay checkout.

### B) Estado actual confirmado
Estado confirmado en inspeccion read-only:

- `SubscriptionWriteIdempotencyService` existe.
- `SubscriptionWriteIdempotencyRepository` existe.
- El flujo contractual actual usa `subscriptions.create_with_contract_acceptance`.
- El repository ya maneja `operation`.
- El repository ya maneja `request_hash`.
- El repository ya maneja `status`.
- El repository ya maneja `response_http_status`.
- El repository ya maneja `response_body_text`.
- El schema versionado permite `subscription_id` nullable.
- No se requiere SQL/schema inmediato para el ajuste minimo propuesto.
- El endpoint checkout-intents todavia no existe en `api/subscriptions/index.php`.
- `CreateSubscriptionCheckoutIntentService` todavia no existe.

### C) Brecha exacta a resolver
Brecha precisa:

- `SubscriptionWriteIdempotencyService` esta acoplado al flujo contractual actual.
- La operation contractual `subscriptions.create_with_contract_acceptance` no debe reemplazarse.
- Checkout requiere una operation adicional: `subscriptions.checkout_intent.create`.
- `markCompleted(...)` actual esta orientado al resultado contractual y exige `subscription_id` + `contract_acceptance_uuid`.
- Checkout requiere guardar resultado idempotente con `checkout_intent_uuid` y snapshot minimo en `response_body_text`.
- Replay checkout debe reconstruir/devolver el resultado desde `response_body_text`.
- Payload distinto debe seguir bloqueandose por `request_hash`.
- `processing` debe seguir bloqueando doble write.
- Failure debe quedar trazable sin dejar la operacion colgada.

### D) Decision tecnica recomendada
Decision para la implementacion posterior:

- Mantener intactos los metodos actuales usados por el flujo contractual.
- Agregar soporte checkout como operation adicional, no como reemplazo.
- Usar operation como argumento explicito en metodos nuevos o wrappers internos.
- Agregar builder de `request_hash` checkout.
- Agregar complete generico o complete checkout que persista JSON de respuesta en `response_body_text`.
- Reutilizar `response_body_text` como fuente principal de replay checkout.
- No cambiar schema.
- No tocar endpoint contractual.
- No tocar endpoint checkout-intents porque todavia no existe.

Se conserva intacto:

- operation contractual `subscriptions.create_with_contract_acceptance`;
- firmas llamadas hoy por `api/subscriptions/index.php`;
- replay contractual existente;
- `markFailed(...)` actual, salvo wrapper compatible si se decide.

Se agrega o parametriza:

- operation `subscriptions.checkout_intent.create`;
- `request_hash` checkout;
- completed checkout sin `subscription_id` activo;
- decode de replay checkout desde `response_body_text`;
- allowlist de operations validas.

No se toca:

- endpoint;
- rutas;
- SQL/schema;
- provider;
- payment intents/events;
- `profile_subscriptions`.

### E) Contrato interno futuro
Firmas conceptuales futuras, sin implementarlas en esta adenda:

- `beginOperation(?string $headerValue, string $operation, array $scope, array $payload): SubscriptionWriteIdempotencyDecision`
  - Variante parametrizable de `begin(...)`.

- `beginCheckoutIntent(?string $headerValue, array $scope, array $payload): SubscriptionWriteIdempotencyDecision`
  - Wrapper especifico que use `subscriptions.checkout_intent.create`.

- `buildCheckoutRequestHash(array $scope, array $payload): string`
  - Canonicaliza el payload checkout y mantiene hash estable ante orden de campos.

- `markOperationCompleted(array $record, array $responsePayload, int $httpStatus, array $references = []): void`
  - Completa operaciones con JSON generico en `response_body_text`.

- `markCheckoutIntentCompleted(array $record, array $responsePayload, int $httpStatus): void`
  - Wrapper especifico para resultado checkout.

- `markOperationFailed(array $record, int $httpStatus): void`
  - Wrapper compatible sobre `markFailed(...)`, si se requiere.

- `decodeReplayResponse(array $idempotencyRow): ?array`
  - Decodifica `response_body_text` y devuelve replay estable.

Los metodos actuales `begin(...)`, `markCompleted(...)` y `markFailed(...)` deben conservarse como wrappers compatibles para el contrato actual o mantenerse sin cambios si se agregan metodos nuevos.

### F) Request hash checkout
El hash checkout debe cubrir, como minimo:

- `entity_type`;
- `entity_id`;
- `plan_code`;
- `billing_period`;
- `contract_version`;
- `contract_hash`;
- `contract_snapshot_url`;
- `acceptance_source`;
- `source`.

Aclaraciones:

- El cliente no envia precio canonico.
- El precio no debe formar parte del hash si se resuelve despues, salvo decision explicita posterior.
- El hash debe ser estable ante orden de campos.
- Misma key con payload distinto debe producir `idempotency_key_reused_with_different_payload`.

### G) Response payload checkout para replay
Estructura minima a guardar en `response_body_text`:

- `checkout_intent_uuid`;
- `checkout_status = pending_payment`;
- `contract_acceptance_uuid`;
- `acceptance_status = accepted_pending_payment`;
- `entity_type`;
- `entity_id`;
- `plan_code`;
- `billing_period`;
- `amount_cents`;
- `currency`;
- `price_source`;
- `price_version`;
- `price_uuid`, si aplica;
- `contract_version`;
- `contract_hash`;
- `contract_snapshot_url`;
- `created_at`.

Reglas:

- Replay estable devuelve ese payload sin crear aceptacion nueva.
- Replay estable no crea checkout intent nuevo.
- Si `response_body_text` esta vacio o invalido, debe responderse error conceptual `checkout_idempotency_result_unavailable`.

### H) Compatibilidad contractual obligatoria
La implementacion posterior debe confirmar:

- `create_with_contract_acceptance` sigue funcionando.
- Las firmas usadas por `api/subscriptions/index.php` no se rompen.
- Las llamadas actuales a `begin(...)`, `markCompleted(...)` y `markFailed(...)` siguen validas.
- El resultado contractual actual no cambia.
- El endpoint contractual actual no cambia.
- Replay contractual actual no cambia.
- La nueva operation checkout es adicional, no reemplazo.

### I) Riesgos y mitigaciones
Riesgos:

- romper replay contractual existente;
- guardar JSON invalido en `response_body_text`;
- permitir operation incorrecta;
- marcar completed sin payload checkout;
- dejar `processing` sin fail en error;
- doble write si checkout no consume begin correctamente;
- mezclar resultado contractual con resultado checkout.

Mitigaciones:

- wrappers compatibles;
- allowlist de operations;
- validacion estricta de response payload;
- pruebas de replay contractual y checkout aislado;
- grep de operaciones;
- no tocar endpoint en esta fase.

### J) Errores conceptuales
Errores conceptuales:

- `idempotency_key_invalid`;
- `idempotency_key_reused_with_different_payload`;
- `request_already_processing`;
- `checkout_idempotency_result_unavailable`;
- `checkout_idempotency_complete_failed`;
- `checkout_idempotency_failure_mark_failed`;
- `checkout_intent_unavailable`;
- `idempotency_operation_invalid`.

### K) QA futura para implementacion
QA esperada para la microfase posterior:

- `php -l modules/subscriptions/services/SubscriptionWriteIdempotencyService.php`.
- `php -l modules/subscriptions/repositories/SubscriptionWriteIdempotencyRepository.php`, si se modifica.
- Grep `subscriptions.checkout_intent.create`.
- Grep `subscriptions.create_with_contract_acceptance`.
- Grep `request_hash`.
- Grep `response_body_text`.
- Grep `checkout_intent_uuid`.
- Grep `idempotency_key_reused_with_different_payload`.
- Grep `request_already_processing`.
- Grep `idempotency_operation_invalid`.
- Prueba o script aislado, si se disena:
  - begin checkout nuevo;
  - replay checkout completed;
  - payload distinto;
  - processing;
  - compatibilidad contractual existente.
- Grep prohibidos:
  - `subscription_payment_intents`;
  - `subscription_payment_events`;
  - `provider`;
  - `webhook`;
  - `PublicProfilePlanCapabilities`;
  - `profile_subscriptions`, salvo compatibilidad contractual existente.
- Confirmar que no modifica endpoint.
- Confirmar que no crea SQL.
- Confirmar que no ejecuta SQL.
- Confirmar que no toca frontend/perfil publico/SEO.

### L) Siguiente microfase recomendada
Readiness confirmada sin SQL/schema inmediato.

Siguiente microfase recomendada:

- `BE/Suscripciones-CheckoutIntent-IdempotencyDependency-01`.

Objetivo:

- Implementar el ajuste minimo de idempotencia checkout en `SubscriptionWriteIdempotencyService` y, solo si es necesario, `SubscriptionWriteIdempotencyRepository`, sin endpoint y sin SQL.

---

## Adenda PP-Decisiones 88 - Plan de lock checkout para CreateSubscriptionCheckoutIntentService

### A) Proposito
Esta adenda planifica el ajuste minimo de lock para el futuro checkout-intent:

- `CreateSubscriptionCheckoutIntentService::createCheckoutIntent(array $input): array`.

Esta adenda no implementa codigo y no reabre decisiones cerradas sobre:

- checkout-first;
- primer write;
- `accepted_pending_payment`;
- `pending_payment`;
- resolver server-side de precios;
- idempotencia checkout;
- operation `subscriptions.checkout_intent.create`;
- lock final `mxmed:subscriptions:{entity_type}:{entity_id}:checkout_create`.

### B) Estado actual inspeccionado
#### 1. SubscriptionEntityWriteLockService
Estado observado:

- Existe `SubscriptionEntityWriteLockService`.
- Constructor actual: recibe `PDO`.
- Metodo publico principal actual: `acquire(string $entityType, string $entityId, int $timeoutSeconds = 2): ?string`.
- Metodo publico de liberacion: `release(?string $lockName): void`.
- Purpose actual fijo: `create`.
- Prefijo actual: `mxmed:subscriptions`.
- Nombre contractual actual conceptual: `mxmed:subscriptions:{entity_type}:{entity_id}:create`.
- Construye el lock name en metodo privado `lockName(...)`.
- Usa `GET_LOCK(:lock_name, :timeout_seconds)`.
- Usa `RELEASE_LOCK(:lock_name)`.
- Timeout actual default: `2` segundos, normalizado con `max(0, $timeoutSeconds)`.
- Si `GET_LOCK` no devuelve `1`, `acquire(...)` devuelve `null`.
- Si el scope es invalido, lanza `InvalidArgumentException`.
- No permite purpose configurable actualmente.
- Puede reutilizarse sin SQL/schema porque usa locks nativos de MySQL por nombre.

Brecha exacta para checkout:

- Necesita generar purpose `checkout_create`.
- Necesita poder construir `mxmed:subscriptions:{entity_type}:{entity_id}:checkout_create`.
- Debe conservar el lock contractual actual sin cambiar el comportamiento de `acquire(...)`.

#### 2. CreateSubscriptionWithAcceptanceService
Estado observado:

- El servicio contractual actual no consume directamente `SubscriptionEntityWriteLockService`.
- El lock contractual se adquiere desde `api/subscriptions/index.php` antes de llamar a `CreateSubscriptionWithAcceptanceService`.
- El flujo contractual actual espera que `acquire(...)` mantenga su firma y semantics actuales.

Compatibilidad obligatoria:

- No romper `acquire(...)`.
- No romper `release(...)`.
- No cambiar el timeout contractual actual.
- No cambiar el error actual del endpoint contractual `subscription_write_lock_timeout`.

#### 3. api/subscriptions/index.php
Estado observado:

- El endpoint contractual actual existe.
- Usa `SubscriptionEntityWriteLockService`.
- Llama `acquire($entityType, $entityId, 2)`.
- Libera con `release($writeLockName)` en `finally`.
- No existe ruta `checkout-intents`.
- Esta microfase no debe modificar el endpoint.

#### 4. Idempotencia checkout
Estado observado:

- Ya existe operation `subscriptions.checkout_intent.create`.
- Ya existen metodos de soporte checkout en idempotencia.
- Esta microfase no debe tocar idempotencia.

### C) Requerimiento checkout
Contrato de lock checkout:

- Purpose: `checkout_create`.
- Lock name final: `mxmed:subscriptions:{entity_type}:{entity_id}:checkout_create`.

Debe proteger:

- lookup de checkout pending;
- validacion final de pending concurrente;
- resolucion final previa al write, si aplica;
- transaccion futura acceptance + checkout intent.

Debe evitar:

- doble checkout pending concurrente;
- aceptacion `accepted_pending_payment` huerfana por carrera;
- checkout intent sin aceptacion;
- duplicados cuando hay requests simultaneos con distintas `Idempotency-Key`.

Error conceptual:

- `subscription_checkout_lock_timeout`.

### D) Estrategia de compatibilidad
Reglas para evitar romper el flujo contractual existente:

- Mantener lock contractual actual.
- Mantener purpose actual contractual `create`.
- No cambiar semantica del endpoint contractual.
- No cambiar timeout contractual salvo microfase explicita.
- No cambiar `CreateSubscriptionWithAcceptanceService`.
- Agregar soporte checkout como purpose adicional.
- Preferir metodo nuevo o purpose parametrizable manteniendo wrappers actuales.

### E) Decision tecnica recomendada
Decision para implementacion posterior:

- Mantener `acquire(string $entityType, string $entityId, int $timeoutSeconds = 2): ?string` como wrapper compatible del purpose contractual `create`.
- Mantener `release(?string $lockName): void`.
- Agregar metodo nuevo o parametrizacion segura para purpose `checkout_create`.
- Usar allowlist estricta de purposes:
  - `create`;
  - `checkout_create`.
- Generar lock name como `mxmed:subscriptions:{entity_type}:{entity_id}:{purpose}`.
- Conservar timeout actual configurable por parametro.
- No cambiar schema.
- No cambiar endpoint.

Se conserva intacto:

- purpose contractual `create`;
- firma actual de `acquire(...)`;
- firma actual de `release(...)`;
- uso de `GET_LOCK`;
- uso de `RELEASE_LOCK`;
- endpoint contractual actual.

Se agrega o parametriza:

- purpose `checkout_create`;
- metodo checkout-specific o metodo `acquireForPurpose(...)`;
- validacion de purpose con allowlist;
- builder centralizado de lock name, si se expone como helper privado.

No se toca:

- endpoint;
- rutas;
- SQL/schema;
- idempotencia;
- provider;
- payment intents/events;
- `profile_subscriptions`.

### F) Contrato interno futuro
Firmas conceptuales futuras, sin implementarlas en esta adenda:

- `acquire(string $entityType, string $entityId, int $timeoutSeconds = 2): ?string`
  - Wrapper actual compatible para purpose `create`.

- `acquireForPurpose(string $entityType, string $entityId, string $purpose, int $timeoutSeconds = 2): ?string`
  - Variante parametrizable con allowlist de purposes.

- `acquireCheckoutCreate(string $entityType, string $entityId, int $timeoutSeconds = 2): ?string`
  - Wrapper especifico para `checkout_create`.

- `release(?string $lockName): void`
  - Metodo actual de liberacion.

- `buildLockName(string $entityType, string $entityId, string $purpose): string`
  - Constructor centralizado de lock names.

Los metodos actuales deben conservarse como wrappers para el contrato actual.

### G) Validacion de purpose
Purpose permitido contractual:

- `create`.

Purpose permitido checkout:

- `checkout_create`.

Si llega purpose invalido:

- `subscription_lock_purpose_invalid`.

Reglas:

- `entity_type` y `entity_id` deben normalizarse antes de construir lock.
- El nombre no debe contener datos sensibles.
- El nombre debe ser estable.
- El lock checkout no debe colisionar con el lock contractual actual.
- El lock checkout debe usar la misma conexion `PDO` o una conexion controlada segun patron actual.
- Si el lock name excede el maximo actual, debe conservarse el patron de hash de `entity_id` ya existente.

### H) Riesgos y mitigaciones
Riesgos:

- romper lock contractual existente;
- liberar lock incorrecto;
- colisionar lock contractual y checkout;
- no liberar lock si hay excepcion;
- timeout mal mapeado;
- permitir purpose arbitrario;
- usar nombres inconsistentes entre endpoint/servicio;
- doble checkout pending concurrente si el lock no envuelve lookup + transaccion.

Mitigaciones:

- wrappers compatibles;
- allowlist estricta de purposes;
- metodo `buildLockName` centralizado;
- `try/finally` en futuro servicio orquestador;
- QA con grep de lock names;
- no tocar endpoint en esta fase;
- QA concurrente en microfase posterior autorizada.

### I) Errores conceptuales
Errores conceptuales:

- `subscription_checkout_lock_timeout`;
- `subscription_lock_purpose_invalid`;
- `subscription_lock_acquire_failed`;
- `subscription_lock_release_failed`;
- `checkout_already_pending`;
- `checkout_intent_transaction_failed`.

### J) QA futura para implementacion
QA esperada para la microfase posterior:

- `php -l modules/subscriptions/services/SubscriptionEntityWriteLockService.php`.
- Grep `checkout_create`.
- Grep `mxmed:subscriptions`.
- Grep `GET_LOCK`.
- Grep `RELEASE_LOCK`.
- Grep `subscription_checkout_lock_timeout`.
- Grep `subscription_lock_purpose_invalid`.
- Grep `create` para compatibilidad contractual.
- Confirmar que `CreateSubscriptionWithAcceptanceService` no se rompe.
- Confirmar que `api/subscriptions/index.php` no se modifica.
- Grep prohibidos:
  - `subscription_payment_intents`;
  - `subscription_payment_events`;
  - `provider`;
  - `webhook`;
  - `PublicProfilePlanCapabilities`;
  - `profile_subscriptions`, salvo compatibilidad contractual existente.
- Confirmar que no crea SQL.
- Confirmar que no ejecuta SQL.
- Confirmar que no toca frontend/perfil publico/SEO.

### K) Decision por microfases
Decision: no se requiere SQL/schema para el ajuste de lock checkout.

Ruta recomendada:

1. `BE/SPEC-Suscripciones-CheckoutIntent-LockDependency-Implementation-Readiness-01`.
2. `BE/Suscripciones-CheckoutIntent-LockDependency-01`.
3. `QA-Suscripciones-CheckoutIntent-LockDependency-PostPush-01`.

### L) Fuera de alcance
Esta adenda no implementa:

- servicio orquestador;
- endpoint;
- rutas;
- SQL;
- migraciones;
- DB/schema;
- provider;
- payment intents;
- payment events;
- webhook;
- activacion post-pago;
- `profile_subscriptions`;
- facturacion;
- capacidades;
- conexion con `PublicProfilePlanCapabilities`;
- perfil publico;
- SEO;
- frontend.

### M) Siguiente microfase recomendada
Siguiente microfase recomendada:

- `BE/SPEC-Suscripciones-CheckoutIntent-LockDependency-Implementation-Readiness-01`.

Objetivo:

- Validar readiness de implementacion del ajuste minimo de lock checkout en `SubscriptionEntityWriteLockService`, sin endpoint y sin SQL.

---

## Adenda PP-Decisiones 89 - Readiness de implementacion de lock checkout

### A) Proposito
Esta adenda valida la readiness de implementacion para agregar soporte de lock checkout sin SQL/schema y sin modificar endpoint.

Esta adenda no implementa codigo y no reabre decisiones cerradas sobre:

- checkout-first;
- primer write;
- `accepted_pending_payment`;
- `pending_payment`;
- resolver server-side de precios;
- idempotencia checkout;
- operation `subscriptions.checkout_intent.create`;
- lock final `mxmed:subscriptions:{entity_type}:{entity_id}:checkout_create`.

### B) Estado actual confirmado
Estado confirmado en inspeccion read-only:

- `SubscriptionEntityWriteLockService` existe.
- Usa `PDO`/conexion actual.
- Usa advisory lock MySQL/MariaDB `GET_LOCK`.
- Usa `RELEASE_LOCK`.
- Timeout actual default: `2` segundos.
- Purpose contractual actual esta fijo/implicito como `create`.
- Lock contractual actual se construye como `mxmed:subscriptions:{entity_type}:{entity_id}:create`, o usa hash de `entity_id` si el nombre excede el maximo.
- El lock contractual actual debe conservarse.
- El endpoint contractual actual instancia `SubscriptionEntityWriteLockService`.
- El endpoint contractual actual llama `acquire($entityType, $entityId, 2)`.
- El endpoint contractual actual llama `release($writeLockName)` en `finally`.
- `CreateSubscriptionWithAcceptanceService` no consume lock directamente y no debe romperse.
- El endpoint checkout-intents todavia no existe.
- `CreateSubscriptionCheckoutIntentService` todavia no existe.
- No se requiere SQL/schema inmediato.

### C) Brecha exacta a resolver
Brecha precisa:

- `SubscriptionEntityWriteLockService` todavia no soporta purpose `checkout_create`.
- Lock contractual actual `create` no debe reemplazarse.
- Checkout requiere un purpose adicional: `checkout_create`.
- Checkout requiere lock name `mxmed:subscriptions:{entity_type}:{entity_id}:checkout_create`.
- El futuro servicio orquestador necesita adquirir este lock antes de:
  - revisar pending checkout;
  - resolver carrera concurrente;
  - abrir transaccion acceptance + checkout intent.
- El lock debe liberarse siempre con `try/finally` en el futuro servicio orquestador.

### D) Decision tecnica recomendada
Decision para implementacion posterior:

- Mantener metodos actuales para compatibilidad contractual.
- Mantener `acquire(string $entityType, string $entityId, int $timeoutSeconds = 2): ?string`.
- Mantener `release(?string $lockName): void`.
- Agregar soporte para purpose parametrizable con allowlist.
- Conservar `create` como purpose contractual.
- Agregar `checkout_create` como purpose checkout.
- Agregar metodo especifico o wrapper `acquireCheckoutCreate(string $entityType, string $entityId, int $timeoutSeconds = 2): ?string`.
- Agregar o reutilizar metodo centralizado `buildLockName(string $entityType, string $entityId, string $purpose): string`.
- Conservar timeout actual salvo microfase explicita.
- No cambiar endpoint contractual.
- No cambiar SQL/schema.
- No cambiar idempotencia.

Se conserva intacto:

- firma actual de `acquire(...)`;
- firma actual de `release(...)`;
- purpose contractual `create`;
- lock contractual actual;
- uso de `GET_LOCK`;
- uso de `RELEASE_LOCK`;
- endpoint contractual actual.

Se agrega o parametriza:

- purpose `checkout_create`;
- allowlist de purpose;
- wrapper checkout-specific;
- builder centralizado de lock name.

No se toca:

- endpoint;
- rutas;
- SQL/schema;
- idempotencia;
- provider;
- payment intents/events;
- `profile_subscriptions`.

### E) Contrato interno futuro propuesto
Firmas conceptuales futuras, sin implementarlas en esta adenda:

- `acquire(string $entityType, string $entityId, int $timeoutSeconds = 2): ?string`
  - Conservado para flujo contractual actual.

- `acquireForPurpose(string $entityType, string $entityId, string $purpose, int $timeoutSeconds = 2): ?string`
  - Nuevo o equivalente para permitir purpose controlado.

- `acquireCheckoutCreate(string $entityType, string $entityId, int $timeoutSeconds = 2): ?string`
  - Nuevo wrapper especifico checkout.

- `release(?string $lockName): void`
  - Conservado.

- `buildLockName(string $entityType, string $entityId, string $purpose): string`
  - Nuevo o equivalente.

`acquire(...)` debe quedar como wrapper de `acquireForPurpose(..., 'create')` si se implementa una API parametrizable.

### F) Validacion de purpose y lock name
Purposes permitidos:

- `create`;
- `checkout_create`.

Si llega purpose invalido:

- `subscription_lock_purpose_invalid`.

Reglas:

- `entity_type` debe normalizarse antes del lock.
- `entity_id` debe normalizarse antes del lock.
- `purpose` debe venir de allowlist, nunca libre.
- El lock name debe ser estable.
- El lock name no debe contener datos sensibles.
- El lock checkout no debe colisionar con el lock contractual.
- El lock contractual debe seguir usando `mxmed:subscriptions:{entity_type}:{entity_id}:create` o el formato real actual con hash si excede longitud maxima.
- El lock checkout debe usar `mxmed:subscriptions:{entity_type}:{entity_id}:checkout_create`.
- El patron de hash para nombres largos debe conservarse para ambos purposes.

### G) Timeout y errores
Timeout detectado:

- `2` segundos por default en `acquire(...)`.

Decision:

- Conservar el timeout contractual actual.
- Usar el mismo timeout configurable para checkout en primera implementacion.
- No introducir cambio de timeout en esta microfase.
- El timeout checkout puede parametrizarse en el futuro si se requiere.

Errores conceptuales:

- Error checkout: `subscription_checkout_lock_timeout`.
- Error generico de acquire: `subscription_lock_acquire_failed`.
- Error release: `subscription_lock_release_failed`.
- Error purpose: `subscription_lock_purpose_invalid`.

### H) Compatibilidad contractual obligatoria
La implementacion posterior debe confirmar:

- `acquire(...)` actual sigue funcionando igual.
- `release(...)` actual sigue funcionando igual.
- Lock contractual `create` no cambia.
- Endpoint contractual actual no cambia.
- `CreateSubscriptionWithAcceptanceService` no cambia.
- El nuevo purpose checkout es adicional, no reemplazo.
- La implementacion no toca idempotencia.

### I) Riesgos y mitigaciones
Riesgos:

- romper lock contractual existente;
- permitir purpose arbitrario;
- generar lock name inconsistente;
- colisionar checkout con contractual;
- liberar un lock distinto al adquirido;
- no liberar lock si hay excepcion;
- mapear timeout checkout como error generico;
- no envolver lookup pending + transaccion futura.

Mitigaciones:

- wrappers compatibles;
- allowlist estricta;
- `buildLockName` centralizado;
- `release(...)` usando token/lock retornado por `acquire(...)`;
- `try/finally` en servicio orquestador futuro;
- QA grep de `create` y `checkout_create`;
- QA `php -l`;
- QA post-push;
- QA concurrente futura cuando exista servicio/endpoint.

### J) Errores conceptuales
Errores conceptuales:

- `subscription_checkout_lock_timeout`;
- `subscription_lock_purpose_invalid`;
- `subscription_lock_acquire_failed`;
- `subscription_lock_release_failed`;
- `checkout_already_pending`;
- `checkout_intent_transaction_failed`.

### K) QA futura para implementacion
QA esperada para la microfase posterior:

- `php -l modules/subscriptions/services/SubscriptionEntityWriteLockService.php`.
- `php -l api/subscriptions/index.php`.
- Grep `checkout_create`.
- Grep `mxmed:subscriptions`.
- Grep `GET_LOCK`.
- Grep `RELEASE_LOCK`.
- Grep `subscription_checkout_lock_timeout`.
- Grep `subscription_lock_purpose_invalid`.
- Grep `create` para compatibilidad contractual.
- Confirmar que `api/subscriptions/index.php` no cambia salvo microfase explicita.
- Confirmar que `CreateSubscriptionWithAcceptanceService` no cambia.
- Confirmar que idempotencia no cambia.
- Grep prohibidos:
  - `subscription_payment_intents`;
  - `subscription_payment_events`;
  - `provider`;
  - `webhook`;
  - `PublicProfilePlanCapabilities`;
  - `profile_subscriptions`, salvo compatibilidad contractual existente.
- Confirmar que no crea SQL.
- Confirmar que no ejecuta SQL.
- Confirmar que no toca frontend/perfil publico/SEO.

### L) Decision por microfases
Readiness confirmada sin SQL/schema.

Ruta recomendada:

1. `BE/Suscripciones-CheckoutIntent-LockDependency-01`.
2. `QA-Suscripciones-CheckoutIntent-LockDependency-PostPush-01`.

### M) Fuera de alcance
Esta adenda no implementa:

- servicio orquestador;
- endpoint;
- rutas;
- SQL;
- migraciones;
- DB/schema;
- provider;
- payment intents;
- payment events;
- webhook;
- activacion post-pago;
- `profile_subscriptions`;
- facturacion;
- capacidades;
- conexion con `PublicProfilePlanCapabilities`;
- perfil publico;
- SEO;
- frontend.

### N) Siguiente microfase recomendada
Siguiente microfase recomendada:

- `BE/Suscripciones-CheckoutIntent-LockDependency-01`.

Objetivo:

- Implementar el ajuste minimo de lock checkout en `SubscriptionEntityWriteLockService`, sin endpoint y sin SQL.

---

## Adenda PP-Decisiones 90 - Plan de active_subscription_exists para CreateSubscriptionCheckoutIntentService

### A) Proposito
Esta adenda planifica la dependencia `active_subscription_exists` para el futuro `CreateSubscriptionCheckoutIntentService::createCheckoutIntent(array $input): array`.

La dependencia debe impedir que una entidad cree un checkout intent si ya tiene una suscripcion activa vigente.

Esta adenda no implementa codigo y no reabre decisiones cerradas:

- checkout-first;
- primer write de checkout-intents;
- aceptacion contractual `accepted_pending_payment`;
- checkout intent `pending_payment`;
- resolver server-side de precios;
- idempotencia checkout;
- lock checkout;
- no crear `profile_subscriptions` en el primer write checkout.

### B) Estado actual inspeccionado
Archivos inspeccionados en modo solo lectura:

- `api/subscriptions/index.php`;
- `modules/subscriptions/services/CreateSubscriptionWithAcceptanceService.php`;
- `modules/subscriptions/repositories/CurrentSubscriptionRepository.php`;
- `modules/subscriptions/services/CurrentSubscriptionReadModelService.php`;
- `modules/profiles/db/2026_06_19_create_subscription_plan_lifecycle.sql`;
- `modules/subscriptions/services/SubscriptionWriteIdempotencyService.php`;
- `modules/subscriptions/services/SubscriptionEntityWriteLockService.php`.

Hallazgos:

1. La validacion de suscripcion activa vive hoy duplicada/acoplada:
   - en `api/subscriptions/index.php` como helper `subscriptionDoctorHasActiveSubscription(...)` para fixtures DEV doctor-specific;
   - en `CreateSubscriptionWithAcceptanceService::activeSubscriptionExists(...)` como metodo privado del flujo contractual actual.
2. El servicio contractual actual valida suscripcion activa antes de crear una suscripcion y vuelve a validar dentro de su transaccion.
3. `CreateSubscriptionWithAcceptanceService` tambien crea `profile_subscriptions`, por lo que no debe reutilizarse tal cual para checkout-intents.
4. Existe `CurrentSubscriptionRepository`, que ya lee `profile_subscriptions` por `entity_type` y `entity_id`, pero no expone todavia un metodo dedicado `activeSubscriptionExists(...)`.
5. Existe `CurrentSubscriptionReadModelService`, pero su objetivo es construir read-model/fallback de suscripcion actual, no actuar como guard transaccional de writes.
6. La fuente canónica observada es `profile_subscriptions`.
7. La validacion actual considera activos los status:
   - `active`;
   - `expiring_soon`;
   - `grace_period`.
8. La validacion actual filtra `deleted_at IS NULL`.
9. La validacion actual considera vigencia temporal:
   - `starts_at IS NULL OR starts_at <= now`;
   - `expires_at IS NULL OR expires_at >= now`.
10. La validacion contractual privada usa `entity_type` y `entity_id`, por lo que el concepto puede ser entity-agnostic.
11. El helper DEV del endpoint esta acoplado a `doctor`, pero no debe ser la base de checkout.
12. La tabla `profile_subscriptions` ya tiene campos e indices suficientes para la consulta:
   - `entity_type`;
   - `entity_id`;
   - `status`;
   - `starts_at`;
   - `expires_at`;
   - `deleted_at`;
   - indices por entidad/status y entidad/fechas.
13. No se requiere SQL/schema para planificar esta dependencia.
14. No existe endpoint `checkout-intents`.
15. Idempotencia checkout ya esta disponible mediante `SubscriptionWriteIdempotencyService::beginCheckoutIntent(...)` y operation `subscriptions.checkout_intent.create`.
16. Lock checkout ya esta disponible mediante `SubscriptionEntityWriteLockService::acquireCheckoutCreate(...)` y purpose `checkout_create`.

### C) Requerimiento checkout
Antes de crear un checkout intent futuro, `CreateSubscriptionCheckoutIntentService` debe validar:

```text
active_subscription_exists(entity_type, entity_id) === false
```

Si existe una suscripcion activa vigente para la entidad, el flujo debe responder con:

```text
active_subscription_exists
```

La validacion debe ocurrir antes de:

- crear aceptacion contractual `accepted_pending_payment`;
- crear checkout intent `pending_payment`;
- abrir la transaccion de escritura, salvo que el diseno final decida abrirla antes del guard;
- resolver precio final si se decide evitar costo innecesario.

La validacion autoritativa debe repetirse dentro del lock checkout para reducir carreras entre activacion y checkout.

La validacion no debe:

- crear `profile_subscriptions`;
- modificar suscripciones activas;
- cancelar suscripciones;
- renovar suscripciones;
- activar capacidades;
- cambiar plan;
- tocar facturacion;
- tocar perfil publico/SEO.

### D) Definicion conceptual de suscripcion activa
Para checkout-intents, una suscripcion activa existe si hay una fila vigente en `profile_subscriptions` o fuente canonica equivalente para la entidad con:

- `entity_type = :entity_type`;
- `entity_id = :entity_id`;
- `deleted_at IS NULL`;
- `status IN ('active', 'expiring_soon', 'grace_period')`;
- `starts_at IS NULL OR starts_at <= now`;
- `expires_at IS NULL OR expires_at >= now`.

No deben considerarse activas las filas:

- `expired`;
- `inactive`;
- `renewed`;
- `cancelled`;
- eliminadas logicamente;
- fuera de vigencia temporal.

La definicion inicial es suficiente con el schema versionado actual. No se detecta necesidad de nueva tabla ni columna.

### E) Estrategia de compatibilidad
Para no romper el flujo contractual existente:

- no cambiar `api/subscriptions/index.php` en esta microfase;
- no cambiar `CreateSubscriptionWithAcceptanceService`;
- no cambiar la semantica de `profile_subscriptions`;
- no cambiar `CurrentSubscriptionReadModelService`;
- no cambiar endpoints contractuales actuales;
- no mover ni reusar directamente helpers DEV acoplados a doctor;
- agregar en microfase posterior un metodo/helper read-only reutilizable;
- mantener la validacion checkout como lectura estricta;
- conservar la decision de que checkout-intents no crea `profile_subscriptions`.

Si en el futuro se reutiliza la logica privada del servicio contractual, debe extraerse sin alterar el comportamiento actual y con microfase explicita.

### F) Decision tecnica recomendada
Decision recomendada: Estrategia B.

Agregar en microfase posterior un metodo read-only a `CurrentSubscriptionRepository`, sin crear SQL/schema y sin tocar endpoint:

```php
activeSubscriptionExists(string $entityType, string $entityId, ?string $now = null): bool
```

Razon:

- `CurrentSubscriptionRepository` ya es el punto existente de lectura de `profile_subscriptions`;
- evita acoplar checkout al endpoint contractual o a helpers DEV;
- no introduce un servicio nuevo si el repositorio actual puede resolver una consulta booleana simple;
- centraliza una fuente de lectura que hoy esta duplicada;
- permite que `CreateSubscriptionCheckoutIntentService` use la dependencia sin crear ni modificar suscripciones.

Como extension opcional posterior, puede agregarse:

```php
findActiveByEntity(string $entityType, string $entityId, ?string $now = null): ?array
```

Ese metodo permitiria diagnostico controlado sin cambiar la decision de negocio del servicio checkout.

No se recomienda source decision adicional porque la fuente canonica ya esta clara: `profile_subscriptions`.

### G) Contrato interno futuro propuesto
Firmas conceptuales:

```php
activeSubscriptionExists(string $entityType, string $entityId, ?string $now = null): bool
findActiveByEntity(string $entityType, string $entityId, ?string $now = null): ?array
```

Contrato:

- `activeSubscriptionExists(...)` devuelve `true` si existe suscripcion activa vigente segun la definicion de esta adenda.
- `activeSubscriptionExists(...)` devuelve `false` si no existe una fila activa vigente.
- En error de lectura, la capa superior debe mapear a `active_subscription_check_unavailable`.
- `findActiveByEntity(...)` devuelve una fila normalizada o `null`.

Campos minimos si se usa `findActiveByEntity(...)`:

- `subscription_id`;
- `entity_type`;
- `entity_id`;
- `doctor_id`;
- `profile_id`;
- `plan_code`;
- `billing_period`;
- `status`;
- `starts_at`;
- `expires_at`;
- `grace_starts_at`;
- `grace_ends_at`;
- `source`;
- `created_at`;
- `updated_at`.

El helper/metodo debe ser entity-agnostic. La validacion doctor-first queda limitada al endpoint/auth/session scope.

### H) Ubicacion en el flujo futuro
Orden recomendado en `CreateSubscriptionCheckoutIntentService`:

1. Validar payload minimo.
2. Validar auth/session/entity.
3. Calcular idempotencia y request hash.
4. Entrar a idempotencia checkout.
5. Tomar lock:
   `mxmed:subscriptions:{entity_type}:{entity_id}:checkout_create`.
6. Dentro del lock, validar `active_subscription_exists`.
7. Dentro del lock, validar pending checkout.
8. Resolver precio server-side.
9. Abrir transaccion superior.
10. Crear aceptacion `accepted_pending_payment`.
11. Crear checkout intent `pending_payment`.
12. Commit.
13. Guardar resultado idempotente.
14. Liberar lock en `finally`.

Se permite una validacion preliminar fuera del lock para respuesta rapida, pero la validacion autoritativa debe ocurrir dentro del lock.

### I) Riesgos y mitigaciones
Riesgos:

- permitir checkout aunque ya exista suscripcion activa;
- bloquear checkout por interpretar como activa una suscripcion expirada;
- acoplar checkout al endpoint contractual actual;
- depender solo de `doctor_id` y no de `entity_type/entity_id`;
- crear una query distinta al read-model y divergir;
- consultar `profile_subscriptions` sin filtrar `deleted_at`;
- carrera entre activacion y checkout;
- tocar capacidades o perfil publico accidentalmente.

Mitigaciones:

- metodo read-only centralizado en repositorio existente;
- definicion explicita de status activos;
- filtro obligatorio `deleted_at IS NULL`;
- filtros temporales iguales al flujo contractual actual;
- validacion autoritativa dentro del lock checkout;
- QA con fixtures controlados;
- no modificar endpoint contractual en esta fase;
- no activar capacidades;
- no crear ni modificar `profile_subscriptions`.

### J) Errores conceptuales
Errores conceptuales relacionados:

- `active_subscription_exists`;
- `active_subscription_check_unavailable`;
- `entity_not_found`;
- `forbidden`;
- `unauthenticated`;
- `invalid_checkout_intent_payload`.

`active_subscription_exists` pertenece a la capa de checkout service.

`active_subscription_check_unavailable` debe usarse si la lectura de la fuente canonica falla o queda indeterminada.

### K) QA futura para implementacion
QA esperada para la microfase posterior:

- `php -l` de archivos modificados.
- Grep `active_subscription_exists`.
- Grep `activeSubscriptionExists`.
- Grep `profile_subscriptions` solo si es lectura autorizada.
- Grep status activos:
  - `active`;
  - `expiring_soon`;
  - `grace_period`.
- Grep `deleted_at IS NULL`.
- Grep `starts_at`.
- Grep `expires_at`.
- Grep prohibidos:
  - `INSERT INTO profile_subscriptions`;
  - `UPDATE profile_subscriptions`;
  - `DELETE FROM profile_subscriptions`;
  - `subscription_payment_intents`;
  - `subscription_payment_events`;
  - `provider`;
  - `webhook`;
  - `PublicProfilePlanCapabilities`.
- Confirmar que no modifica endpoint salvo microfase explicita.
- Confirmar que no crea SQL.
- Confirmar que no ejecuta SQL.
- Confirmar que no toca frontend/perfil publico/SEO.

### L) Decision por microfases
No se requiere SQL/schema ni source decision.

Ruta recomendada:

1. `BE/SPEC-Suscripciones-CheckoutIntent-ActiveSubscriptionDependency-Implementation-Readiness-01`.
2. `BE/Suscripciones-CheckoutIntent-ActiveSubscriptionDependency-01`.
3. `QA-Suscripciones-CheckoutIntent-ActiveSubscriptionDependency-PostPush-01`.

### M) Fuera de alcance
Esta adenda no implementa:

- helper `active_subscription_exists`;
- servicio/repository nuevo;
- servicio orquestador;
- endpoint;
- rutas;
- SQL;
- migraciones;
- DB/schema;
- provider;
- payment intents;
- payment events;
- webhook;
- activacion post-pago;
- creacion/modificacion de `profile_subscriptions`;
- facturacion;
- capacidades;
- conexion con `PublicProfilePlanCapabilities`;
- perfil publico;
- SEO;
- frontend.

### N) Siguiente microfase recomendada
Siguiente microfase recomendada:

- `BE/SPEC-Suscripciones-CheckoutIntent-ActiveSubscriptionDependency-Implementation-Readiness-01`.

Objetivo:

- Validar readiness de implementacion del metodo read-only para `active_subscription_exists`, sin endpoint y sin SQL.

---

## Adenda PP-Decisiones 91 - Readiness de implementacion de active_subscription_exists

### A) Proposito
Esta adenda valida la readiness para implementar posteriormente una lectura reutilizable `active_subscription_exists` antes del futuro servicio orquestador checkout.

El objetivo tecnico posterior es agregar soporte read-only en `CurrentSubscriptionRepository` para:

```php
activeSubscriptionExists(string $entityType, string $entityId): bool
```

Opcionalmente, si la implementacion lo necesita para diagnostico o respuesta controlada:

```php
findActiveByEntity(string $entityType, string $entityId): ?array
```

Esta adenda no implementa codigo y no reabre decisiones cerradas:

- checkout-first;
- primer write de checkout-intents;
- aceptacion contractual `accepted_pending_payment`;
- checkout intent `pending_payment`;
- resolver server-side de precios;
- idempotencia checkout;
- lock checkout;
- no crear `profile_subscriptions` en el primer write checkout.

### B) Estado actual confirmado
Inspeccion read-only confirmada:

- `CurrentSubscriptionRepository` existe en `modules/subscriptions/repositories/CurrentSubscriptionRepository.php`.
- Usa `declare(strict_types=1);`, namespace `Subscriptions\Repositories`, constructor con `PDO` y manejo defensivo con `PDOException`.
- Metodos publicos actuales:
  - `findPlanByCodeAndPeriod(string $planCode, string $billingPeriod): ?array`;
  - `findFallbackFreePlan(): ?array`;
  - `findCurrentCandidateForEntity(string $entityType, string $entityId): ?array`.
- `findCurrentCandidateForEntity(...)` ya lee `profile_subscriptions`.
- La lectura actual usa `entity_type` y `entity_id`, no depende solo de `doctor_id`.
- La lectura actual filtra `deleted_at IS NULL`.
- La lectura actual ordena candidatos por prioridad de status y fechas, pero no es todavia un guard booleano de suscripcion activa.
- `findCurrentCandidateForEntity(...)` devuelve candidatos con status como `active`, `grace_period`, `expired`, `inactive`, `renewed`, `cancelled` y otros, por lo que no equivale directamente a `active_subscription_exists`.
- El endpoint actual contiene helper DEV `subscriptionDoctorHasActiveSubscription(...)`, acoplado a doctor.
- Ese helper DEV consulta `profile_subscriptions`, status activos y vigencia temporal, pero no debe reutilizarse tal cual para checkout.
- `CreateSubscriptionWithAcceptanceService` valida active subscription internamente con metodo privado `activeSubscriptionExists(...)`.
- `CreateSubscriptionWithAcceptanceService` tambien crea `profile_subscriptions`, por lo que no debe reutilizarse tal cual para checkout-first.
- Idempotencia checkout ya existe con operation `subscriptions.checkout_intent.create`.
- Lock checkout ya existe con purpose `checkout_create`.
- Endpoint `checkout-intents` todavia no existe.
- `CreateSubscriptionCheckoutIntentService` todavia no existe.
- No se requiere SQL/schema inmediato.

### C) Brecha exacta a resolver
Brecha confirmada:

- `CurrentSubscriptionRepository` no tiene todavia metodo explicito `activeSubscriptionExists(...)`.
- Checkout necesita una validacion read-only, entity-agnostic y reutilizable.
- La logica del endpoint actual esta acoplada a helpers DEV doctor-specific.
- La logica del servicio contractual actual esta acoplada a creacion de suscripcion activa.
- El futuro checkout debe consultar suscripcion activa sin escribir en `profile_subscriptions`.
- La validacion debe poder ejecutarse dentro del lock checkout.

Esta brecha es de implementacion read-only en repositorio existente. No requiere SQL/schema ni source decision.

### D) Definicion final propuesta de active subscription
Definicion recomendada para la implementacion posterior:

Una suscripcion activa existe si hay una fila en `profile_subscriptions` para la entidad con:

- `entity_type = :entity_type`;
- `entity_id = :entity_id`;
- `deleted_at IS NULL`;
- `status IN ('active', 'expiring_soon', 'grace_period')`;
- `starts_at IS NULL OR starts_at <= :now`;
- `expires_at IS NULL OR expires_at >= :now`.

Nota de frontera temporal:

- El codigo actual usa `expires_at >= now`.
- Si una microfase futura decide usar frontera estricta `expires_at > now`, debe documentarlo como ajuste semantico explicito.
- Para readiness actual, se recomienda preservar la semantica existente para no romper compatibilidad contractual.

No se consideran activas:

- `expired`;
- `inactive`;
- `renewed`;
- `cancelled`;
- filas con `deleted_at` no nulo;
- filas fuera de vigencia temporal.

El schema versionado actual contempla los campos necesarios:

- `subscription_id`;
- `entity_type`;
- `entity_id`;
- `doctor_id`;
- `profile_id`;
- `plan_code`;
- `billing_period`;
- `starts_at`;
- `expires_at`;
- `grace_starts_at`;
- `grace_ends_at`;
- `status`;
- `source`;
- `created_at`;
- `updated_at`;
- `deleted_at`.

### E) Decision tecnica recomendada
Decision recomendada:

- Agregar en una microfase posterior un metodo read-only a `CurrentSubscriptionRepository`.
- Metodo principal:
  `activeSubscriptionExists(string $entityType, string $entityId): bool`.
- Metodo opcional si la implementacion necesita la fila:
  `findActiveByEntity(string $entityType, string $entityId): ?array`.
- Reutilizar la misma conexion `PDO` del repository.
- No crear SQL/schema.
- No crear servicio nuevo todavia.
- No modificar endpoint.
- No modificar `CreateSubscriptionWithAcceptanceService`.
- No escribir en `profile_subscriptions`.
- No activar capacidades.

No se recomienda `ActiveSubscriptionDependency-SourceDecision`, porque la fuente canonica ya esta identificada: `profile_subscriptions`.

### F) Contrato interno futuro propuesto
Firmas conceptuales:

```php
activeSubscriptionExists(string $entityType, string $entityId): bool
findActiveByEntity(string $entityType, string $entityId): ?array
```

Normalizacion minima:

- `entity_type` debe trimmease y no puede quedar vacio.
- `entity_id` debe trimmease y no puede quedar vacio.
- La lectura debe ser entity-agnostic.
- `doctor_id` y `profile_id` pueden devolverse si existen, pero no deben ser el criterio primario del guard.

Contrato de retorno:

- `activeSubscriptionExists(...)` devuelve `true` si existe fila activa vigente.
- `activeSubscriptionExists(...)` devuelve `false` si no existe fila activa vigente.
- Si falla la consulta y no puede determinarse el estado, la capa superior debe mapear el fallo a `active_subscription_check_unavailable`.

Si se implementa `findActiveByEntity(...)`, debe devolver una fila normalizada o `null` con campos minimos:

- `subscription_id`;
- `entity_type`;
- `entity_id`;
- `doctor_id`;
- `profile_id`;
- `plan_code`;
- `billing_period`;
- `status`;
- `starts_at`;
- `expires_at`;
- `grace_starts_at`;
- `grace_ends_at`;
- `source`;
- `created_at`;
- `updated_at`.

### G) Ubicacion en el flujo futuro
Ubicacion recomendada en `CreateSubscriptionCheckoutIntentService`:

1. Validar payload minimo.
2. Validar auth/session/entity.
3. Entrar idempotencia checkout.
4. Tomar lock checkout.
5. Dentro del lock, ejecutar `active_subscription_exists`.
6. Si devuelve `true`, responder `active_subscription_exists`.
7. Dentro del lock, revisar pending checkout.
8. Resolver precio server-side.
9. Abrir transaccion superior.
10. Crear aceptacion `accepted_pending_payment`.
11. Crear checkout intent `pending_payment`.
12. Commit.
13. Guardar resultado idempotente.
14. Liberar lock.

Se permite una prevalidacion fuera del lock para respuesta rapida, pero la validacion autoritativa debe hacerse dentro del lock.

No debe abrirse una transaccion solo para leer active subscription, salvo decision posterior documentada.

### H) Compatibilidad contractual obligatoria
La implementacion posterior debe confirmar:

- Endpoint contractual actual no cambia.
- Helper DEV actual no se elimina.
- `CreateSubscriptionWithAcceptanceService` no cambia.
- Creacion contractual actual de `profile_subscriptions` no cambia.
- `CurrentSubscriptionRepository` conserva sus metodos existentes.
- La nueva lectura es adicional.
- No se modifica idempotencia.
- No se modifica lock.
- No se modifica schema.

### I) Riesgos y mitigaciones
Riesgos:

- bloquear checkout por una suscripcion expirada mal interpretada;
- permitir checkout con suscripcion activa por status incompleto;
- divergir del read-model actual;
- consultar por `doctor_id` cuando checkout usa `entity_type/entity_id`;
- ignorar `deleted_at`;
- ignorar vigencia temporal;
- reutilizar el servicio contractual que crea `profile_subscriptions`;
- crear dependencia circular con checkout service;
- tocar endpoint o DB accidentalmente.

Mitigaciones:

- metodo read-only centralizado en `CurrentSubscriptionRepository`;
- definicion explicita de statuses activos;
- mantener filtros `deleted_at`, `starts_at` y `expires_at`;
- pruebas con fixtures conocidos;
- grep prohibidos de `INSERT/UPDATE/DELETE profile_subscriptions`;
- validacion autoritativa dentro del lock checkout;
- no tocar endpoint en esta fase;
- no tocar `CreateSubscriptionWithAcceptanceService`;
- QA post-push.

### J) Errores conceptuales
Errores conceptuales:

- `active_subscription_exists`;
- `active_subscription_check_unavailable`;
- `entity_not_found`;
- `forbidden`;
- `unauthenticated`;
- `invalid_checkout_intent_payload`.

`active_subscription_exists` se dispara cuando el guard confirma una suscripcion activa vigente.

`active_subscription_check_unavailable` se reserva para fallo de lectura o estado indeterminado.

### K) QA futura para implementacion
QA esperada para la microfase posterior:

- `php -l modules/subscriptions/repositories/CurrentSubscriptionRepository.php`.
- Grep `activeSubscriptionExists`.
- Grep `findActiveByEntity` si se implementa.
- Grep `profile_subscriptions`.
- Grep statuses:
  - `active`;
  - `expiring_soon`;
  - `grace_period`.
- Grep `deleted_at`.
- Grep `starts_at`.
- Grep `expires_at`.
- Grep prohibidos:
  - `INSERT INTO profile_subscriptions`;
  - `UPDATE profile_subscriptions`;
  - `DELETE FROM profile_subscriptions`;
  - `subscription_payment_intents`;
  - `subscription_payment_events`;
  - `provider`;
  - `webhook`;
  - `PublicProfilePlanCapabilities`.
- Confirmar que no modifica endpoint.
- Confirmar que no modifica `CreateSubscriptionWithAcceptanceService`.
- Confirmar que no crea SQL.
- Confirmar que no ejecuta SQL.
- Confirmar que no toca frontend/perfil publico/SEO.
- Prueba aislada opcional con script PHP solo si se disena explicitamente y sin modificar DB.

### L) Decision por microfases
Readiness confirmada sin SQL/schema.

Ruta recomendada:

1. `BE/Suscripciones-CheckoutIntent-ActiveSubscriptionDependency-01`.
2. `QA-Suscripciones-CheckoutIntent-ActiveSubscriptionDependency-PostPush-01`.

No se requiere:

- `BE/SPEC-Suscripciones-CheckoutIntent-ActiveSubscriptionDependency-SourceDecision-01`;
- `DB/SPEC-Suscripciones-CheckoutIntent-ActiveSubscriptionDependency-SchemaPlan-01`.

### M) Fuera de alcance
Esta adenda no implementa:

- `activeSubscriptionExists`;
- `findActiveByEntity`;
- helper `active_subscription_exists`;
- servicio/repository nuevo;
- servicio orquestador;
- endpoint;
- rutas;
- SQL;
- migraciones;
- DB/schema;
- provider;
- payment intents;
- payment events;
- webhook;
- activacion post-pago;
- creacion/modificacion de `profile_subscriptions`;
- facturacion;
- capacidades;
- conexion con `PublicProfilePlanCapabilities`;
- perfil publico;
- SEO;
- frontend.

### N) Siguiente microfase recomendada
Siguiente microfase recomendada:

- `BE/Suscripciones-CheckoutIntent-ActiveSubscriptionDependency-01`.

Objetivo:

- Implementar metodo read-only `activeSubscriptionExists(...)` en `CurrentSubscriptionRepository`, sin endpoint y sin SQL.

---

## Adenda PP-Decisiones 92 - Plan de validacion entity_type/entity_id para checkout-intents

### A) Objetivo
Esta adenda planifica la validacion reutilizable de `entity_type` / `entity_id` para el futuro:

```php
CreateSubscriptionCheckoutIntentService::createCheckoutIntent(array $input): array
```

La validacion debe confirmar que la entidad existe, que es del tipo soportado y que puede participar en el flujo checkout-first antes de resolver precio, crear aceptacion contractual `accepted_pending_payment` o crear `subscription_checkout_intents`.

Esta microfase es solo documental. No implementa codigo, endpoint, rutas, SQL ni cambios de DB/schema.

### B) Problema
El futuro servicio orquestador checkout necesita saber si la entidad solicitada es valida y contratable antes de:

- entrar a decisiones de negocio;
- validar `active_subscription_exists`;
- resolver precio server-side;
- crear aceptacion contractual;
- crear checkout intent.

Hoy la validacion operativa esta repartida y acoplada:

- `api/subscriptions/index.php` valida el endpoint contractual actual con `subscriptionValidEntityId(...)`, `subscriptionResolveWriteContext(...)` y scope doctor-only.
- El helper DEV `subscriptionDoctorFixtureExists(...)` consulta `profiles_doctors` por `doctor_id`, pero esta orientado a fixtures locales.
- `CreateSubscriptionWithAcceptanceService` valida que `entity_type === doctor` y `entity_id === doctor_id`, pero tambien crea `profile_subscriptions`; no debe reutilizarse tal cual para checkout-intents.

No conviene duplicar validacion entre endpoint futuro y servicio futuro. La validacion debe vivir como dependencia read-only reutilizable.

### C) Alcance
Alcance de esta adenda:

- planificar la dependencia futura;
- documentar fuente de verdad;
- definir snapshot minimo;
- definir errores conceptuales;
- definir orden futuro en checkout.

Fuera de esta microfase:

- codigo PHP;
- endpoint checkout-intents;
- servicios/repositorios nuevos;
- SQL;
- migraciones;
- DB/schema;
- frontend;
- provider/payment/webhook/facturacion/capacidades.

### D) Entidades soportadas
Inspeccion actual:

- `subscriptionValidEntityType(...)` enumera varios tipos futuros: `doctor`, `dental`, `hospital`, `clinic`, `laboratory`, `diagnostic`, `insurer`, `pharmaceutical`, `service`.
- El write contractual actual solo permite `doctor`.
- `subscriptionResolveWriteContext(...)` rechaza tipos distintos a `doctor` para writes de suscripcion.
- `CreateSubscriptionWithAcceptanceService` exige `entity_type = doctor` y `entity_id = doctor_id`.

Decision:

- Para checkout-intents, el soporte inicial debe ser `doctor`.
- No se habilitan nuevos tipos productivos en esta etapa.
- La extension futura a otros tipos de perfil queda abierta, pero requiere microfases especificas de fuente de verdad, ownership y contractabilidad por tipo.

### E) Fuente de verdad
Fuente detectada para existencia inicial de doctor:

- Tabla: `profiles_doctors`.
- Campo: `doctor_id`.
- Uso actual: `subscriptionDoctorFixtureExists(...)` consulta `profiles_doctors WHERE doctor_id = :doctor_id`.
- Repositorios existentes relacionados: `modules/profiles/repositories/PrivateProfileRepository.php` y `modules/profiles/repositories/PublicProfileRepository.php` usan `profiles_doctors`, pero no son una dependencia actual de subscriptions.

Decision documental:

- La validacion de entidad para checkout debe usar `profiles_doctors` como fuente de existencia para `entity_type = doctor`.
- No debe inventar tablas nuevas.
- No debe depender de helpers DEV del endpoint.
- No debe escribir en `profiles_doctors`.
- No debe escribir en `profile_subscriptions`.

### F) Decision tecnica
Decision recomendada:

- Crear en microfase posterior una dependencia read-only pequena para resolver entidad.
- Nombre sugerido:
  `SubscriptionEntityResolverService`.
- Ubicacion sugerida:
  `modules/subscriptions/services/SubscriptionEntityResolverService.php`.
- Metodo conceptual:

```php
resolveForCheckout(string $entityType, string $entityId): array
```

Responsabilidades:

- normalizar `entity_type`;
- validar formato de `entity_id`;
- permitir inicialmente solo `doctor`;
- verificar existencia en `profiles_doctors`;
- devolver snapshot minimo;
- mapear razones de rechazo;
- no crear ni modificar entidades;
- no tocar `profile_subscriptions`;
- no tocar capacidades;
- no resolver precio;
- no manejar idempotencia;
- no manejar lock.

Alternativa aceptable para implementacion futura:

- Si se prefiere evitar un servicio nuevo, puede agregarse un metodo read-only en un repositorio existente de perfiles y envolverlo desde el service checkout. Esa alternativa no debe acoplar checkout al endpoint actual.

### G) Snapshot minimo sugerido
El resolver futuro debe devolver un snapshot estable:

```text
entity_type
entity_id
entity_exists
entity_is_contractable
display_name o label, si existe
source
reason/error si no es valida
```

Para `doctor`, snapshot recomendado:

- `entity_type = doctor`;
- `entity_id = doctor_id`;
- `entity_exists = true|false`;
- `entity_is_contractable = true|false`;
- `display_name`, `nombre`, `name` o equivalente si la fuente lo expone;
- `source = profiles_doctors`;
- `reason` con `entity_not_found`, `entity_not_contractable` o `entity_type_invalid` segun corresponda.

El snapshot no debe incluir precio, suscripcion activa, payment intent, capacidades ni datos privados innecesarios.

### H) Errores conceptuales
Errores conceptuales del resolver o capa checkout:

- `entity_type_invalid`;
- `entity_not_found`;
- `entity_not_contractable`;
- `entity_validation_unavailable`.

Mapeo recomendado:

- `entity_type_invalid`: tipo distinto a los soportados para checkout.
- `entity_not_found`: no existe fila canonica en la fuente esperada.
- `entity_not_contractable`: existe pero no debe contratar en esta fase.
- `entity_validation_unavailable`: falla tecnica de lectura o estado indeterminado.

### I) Orden futuro dentro de checkout
Orden recomendado:

1. Validar request basico.
2. Validar formato inicial de `entity_type` / `entity_id`.
3. Iniciar idempotencia checkout.
4. Entrar a lock checkout:
   `mxmed:subscriptions:{entity_type}:{entity_id}:checkout_create`.
5. Revalidar entidad dentro del lock mediante dependencia read-only.
6. Si no existe o no es contratable, responder error conceptual.
7. Validar `active_subscription_exists` dentro del lock.
8. Revisar checkout pending.
9. Resolver precio server-side.
10. Abrir transaccion superior.
11. Crear aceptacion `accepted_pending_payment`.
12. Crear checkout intent `pending_payment`.
13. Marcar idempotencia completed.
14. Liberar lock.

Se permite una prevalidacion fuera del lock para respuesta rapida, pero la validacion autoritativa debe ocurrir dentro del lock si puede haber carreras de estado.

### J) Restricciones
La dependencia futura no debe:

- crear `profile_subscriptions`;
- modificar `profile_subscriptions`;
- crear payment intents;
- crear payment events;
- conectar provider;
- conectar webhook;
- conectar facturacion;
- activar capacidades;
- tocar `PublicProfilePlanCapabilities`;
- tocar perfil publico/SEO;
- resolver precio;
- manejar idempotencia;
- manejar lock;
- crear endpoint/rutas.

### K) Riesgos y mitigaciones
Riesgos:

- habilitar tipos distintos a `doctor` sin fuente de ownership;
- duplicar validacion entre endpoint y servicio;
- usar helpers DEV como dependencia productiva;
- validar solo formato y no existencia;
- consultar fuente equivocada;
- mezclar validacion de entidad con suscripcion activa;
- filtrar por datos publicos no contractuales;
- tocar perfil publico/SEO accidentalmente.

Mitigaciones:

- soporte inicial cerrado a `doctor`;
- fuente canonica `profiles_doctors` para existencia;
- dependencia read-only separada;
- snapshot minimo;
- errores conceptuales explicitos;
- QA grep de ausencia de writes;
- no modificar endpoint actual;
- no modificar `CreateSubscriptionWithAcceptanceService`.

### L) QA futura para implementacion
QA esperada cuando se implemente la dependencia:

- `php -l` de archivos modificados.
- Grep del nombre de la dependencia, si aplica.
- Grep `entity_type_invalid`.
- Grep `entity_not_found`.
- Grep `entity_not_contractable`.
- Grep `entity_validation_unavailable`.
- Grep `profiles_doctors` solo como lectura.
- Grep prohibidos:
  - `INSERT INTO profile_subscriptions`;
  - `UPDATE profile_subscriptions`;
  - `DELETE FROM profile_subscriptions`;
  - `subscription_payment_intents`;
  - `subscription_payment_events`;
  - `provider`;
  - `webhook`;
  - `PublicProfilePlanCapabilities`.
- Confirmar que no modifica endpoint.
- Confirmar que no crea SQL.
- Confirmar que no ejecuta SQL.
- Confirmar que no toca frontend/perfil publico/SEO.

### M) Fuera de alcance
Esta adenda no implementa:

- `CreateSubscriptionCheckoutIntentService`;
- endpoint checkout-intents;
- rutas;
- servicios nuevos;
- repositorios nuevos;
- SQL;
- migraciones;
- DB/schema;
- frontend;
- provider;
- payment intents;
- payment events;
- webhooks;
- activacion post-pago;
- writes a `profile_subscriptions`;
- facturacion;
- capacidades;
- conexion con `PublicProfilePlanCapabilities`;
- perfil publico;
- SEO.

### N) Siguiente microfase recomendada
Siguiente microfase recomendada:

- `BE/SPEC-Suscripciones-CheckoutIntent-EntityValidationDependency-Implementation-Readiness-01`.

Objetivo:

- Validar readiness tecnica para implementar la dependencia read-only de `entity_type` / `entity_id`, sin endpoint, sin SQL y sin DB/schema.

---

## Adenda PP-Decisiones 93 - Readiness de implementacion de validacion entity_type/entity_id para checkout-intents

### A) Objetivo
Esta adenda valida si esta lista la implementacion futura de una dependencia read-only para resolver entidad en checkout-intents.

Dependencia probable:

```text
modules/subscriptions/services/SubscriptionEntityResolverService.php
```

Metodo conceptual:

```php
resolveForCheckout(string $entityType, string $entityId): array
```

Esta microfase no implementa el servicio. Solo confirma readiness tecnica, dependencias, contrato de salida, errores y restricciones.

### B) Hallazgos de inspeccion
Hallazgos sobre PP-Decisiones 92:

- PP-Decisiones 92 existe y define el plan de validacion `entity_type/entity_id`.
- Recomienda `SubscriptionEntityResolverService`.
- Recomienda `resolveForCheckout(string $entityType, string $entityId): array`.
- Define errores conceptuales:
  - `entity_type_invalid`;
  - `entity_not_found`;
  - `entity_not_contractable`;
  - `entity_validation_unavailable`.
- Define fuente inicial `profiles_doctors` para `doctor`.

Hallazgos sobre `api/subscriptions/index.php`:

- `subscriptionValidEntityType(...)` enumera tipos futuros, pero writes actuales de suscripcion solo permiten `doctor`.
- `subscriptionValidEntityId(...)` valida id no vacio, maximo 64 caracteres y patron `[A-Za-z0-9._:-]+`.
- `subscriptionResolveWriteContext(...)` rechaza writes para `entity_type` distinto de `doctor`.
- `subscriptionResolveWriteContext(...)` exige scope de doctor y mismatch devuelve `forbidden`.
- `subscriptionDoctorFixtureExists(...)` consulta `profiles_doctors WHERE doctor_id = :doctor_id`, pero es helper DEV para fixtures, no dependencia reutilizable.
- No existe endpoint `checkout-intents`.

Hallazgos sobre `CreateSubscriptionWithAcceptanceService`:

- Esta acoplado al flujo contractual actual.
- Exige `entity_type = doctor` y `entity_id = doctor_id`.
- Crea aceptacion contractual final y `profile_subscriptions`.
- No debe reutilizarse tal cual para checkout-first.

Hallazgos sobre `CurrentSubscriptionRepository`:

- Ya tiene `activeSubscriptionExists(...)`.
- Ya tiene `findActiveByEntity(...)`.
- Lee `profile_subscriptions`.
- No valida existencia real de entidad.
- No debe mezclarse con el resolver de entidad para evitar combinar estado de suscripcion con identidad/contractabilidad.

Hallazgos sobre `profiles_doctors`:

- Schema versionado: `modules/profiles/db/profiles_doctors_schema.sql`.
- Columna de identificacion: `doctor_id`.
- Tiene `display_name`.
- Tiene `profile_status`.
- Tiene `is_public_candidate`.
- No se observo `deleted_at` en el schema versionado.
- `PublicProfileRepository` lee `profiles_doctors` por `doctor_id`.
- `PrivateProfileRepository::fetchIdentity(...)` lee `profiles_doctors` por `doctor_id`.

### C) Decision de readiness
Readiness aprobada para implementar una dependencia read-only en microfase posterior.

Archivo futuro recomendado:

```text
modules/subscriptions/services/SubscriptionEntityResolverService.php
```

Clase futura recomendada:

```php
SubscriptionEntityResolverService
```

Metodo futuro recomendado:

```php
resolveForCheckout(string $entityType, string $entityId): array
```

Motivo:

- El endpoint actual tiene helpers utiles, pero estan acoplados al entry point y a DEV/session scope.
- El servicio contractual actual valida entidad, pero tambien crea suscripcion activa.
- `CurrentSubscriptionRepository` resuelve estado de suscripcion, no existencia de entidad.
- Una dependencia read-only separada permite que el futuro `CreateSubscriptionCheckoutIntentService` valide entidad sin crear writes ni acoplarse al endpoint.

No se requiere SQL/schema antes de implementar la primera version.

### D) Alcance futuro
Alcance de la implementacion futura:

- Read-only.
- Soporte inicial solo `doctor`.
- Fuente de existencia: `profiles_doctors.doctor_id`.
- Sin activar nuevos `entity_type`.
- Sin escribir `profile_subscriptions`.
- Sin escribir `profiles_doctors`.
- Sin endpoint.
- Sin `CreateSubscriptionCheckoutIntentService`.
- Sin payment/provider/webhook/facturacion/capacidades.

La implementacion futura debe aceptar otros tipos solo como rejection controlado con `entity_type_invalid`, no como soporte productivo.

### E) Dependencias futuras
Dependencias sugeridas:

- `PDO`, siguiendo el patron de servicios/repositories actuales.
- Consulta read-only a `profiles_doctors`.
- Formato de `entity_id` alineado con `subscriptionValidEntityId(...)`:
  - no vacio;
  - longitud maxima 64;
  - patron `[A-Za-z0-9._:-]+`.

No depender de:

- `CurrentSubscriptionRepository` para existencia de entidad;
- `CreateSubscriptionWithAcceptanceService`;
- endpoint `api/subscriptions/index.php`;
- helpers DEV;
- `profile_subscriptions`;
- `PublicProfilePlanCapabilities`.

### F) Snapshot minimo futuro
Snapshot minimo recomendado:

```text
entity_type
entity_id
entity_exists
entity_is_contractable
label
display_name
source
reason
error
```

Para `doctor`:

- `entity_type = doctor`;
- `entity_id = doctor_id`;
- `entity_exists = true` cuando exista fila en `profiles_doctors`;
- `entity_is_contractable = true` en la primera version si existe fila y el tipo es `doctor`;
- `display_name` si existe en `profiles_doctors`;
- `label` puede derivarse de `display_name` o `doctor_id`;
- `source = profiles_doctors`;
- `reason = null` en exito;
- `error = null` en exito.

Para rechazo:

- `entity_exists = false` si no hay fila;
- `entity_is_contractable = false`;
- `reason` y `error` deben usar codigo conceptual estable.

### G) Errores conceptuales
Errores que debe soportar la implementacion futura:

- `entity_type_invalid`;
- `entity_id_invalid`;
- `entity_not_found`;
- `entity_not_contractable`;
- `entity_validation_unavailable`.

Mapeo recomendado:

- `entity_type_invalid`: tipo no soportado para checkout.
- `entity_id_invalid`: formato de `entity_id` invalido.
- `entity_not_found`: no existe `profiles_doctors.doctor_id`.
- `entity_not_contractable`: existe entidad pero una regla de contractabilidad la bloquea.
- `entity_validation_unavailable`: error tecnico de lectura o fuente indeterminada.

### H) Contractabilidad
Para la primera version, `entity_is_contractable` significa:

- `entity_type = doctor`;
- `entity_id` valido;
- existe fila en `profiles_doctors` para `doctor_id = entity_id`.

No se debe inventar una regla de contractabilidad no soportada por codigo/schema actual.

Campos observados:

- `profile_status`;
- `is_public_candidate`.

Decision:

- No usar `profile_status` ni `is_public_candidate` como bloqueo obligatorio en la primera implementacion, salvo microfase posterior explicita.
- Documentar esos campos como candidatos para una politica futura de contractabilidad/publicacion.
- No usar `deleted_at` porque no existe en el schema versionado observado.

### I) Orden futuro dentro de CreateSubscriptionCheckoutIntentService
Orden recomendado:

1. Validar request basico.
2. Resolver entidad con `SubscriptionEntityResolverService::resolveForCheckout(...)`.
3. Iniciar idempotencia checkout.
4. Entrar a lock checkout.
5. Revalidar entidad dentro del lock.
6. Validar `activeSubscriptionExists(...)` dentro del lock.
7. Revisar checkout pending.
8. Resolver precio server-side.
9. Abrir transaccion superior.
10. Crear aceptacion `accepted_pending_payment`.
11. Crear checkout intent `pending_payment`.
12. Marcar idempotencia completed.
13. Liberar lock.

La revalidacion dentro del lock evita carreras con cambios de estado de entidad antes del write.

### J) QA futura esperada para implementacion
QA minima para la microfase de implementacion:

- `php -l modules/subscriptions/services/SubscriptionEntityResolverService.php`.
- Grep `class SubscriptionEntityResolverService`.
- Grep `function resolveForCheckout`.
- Grep `profiles_doctors`.
- Grep `entity_type_invalid`.
- Grep `entity_id_invalid`.
- Grep `entity_not_found`.
- Grep `entity_not_contractable`.
- Grep `entity_validation_unavailable`.
- Grep prohibidos:
  - `INSERT`;
  - `UPDATE`;
  - `DELETE`;
  - `profile_subscriptions`;
  - `subscription_payment_intents`;
  - `subscription_payment_events`;
  - `provider`;
  - `webhook`;
  - `PublicProfilePlanCapabilities`.
- Confirmar que no se crea endpoint.
- Confirmar que no se crea `CreateSubscriptionCheckoutIntentService`.
- Confirmar que no se crea SQL.
- Confirmar que no se ejecuta SQL.

### K) Restricciones
Esta readiness mantiene fuera de alcance:

- endpoint checkout-intents;
- `CreateSubscriptionCheckoutIntentService`;
- payment intents/events;
- provider;
- webhooks;
- facturacion;
- capacidades;
- `PublicProfilePlanCapabilities`;
- perfil publico/SEO;
- SQL;
- migraciones;
- DB/schema;
- consultas DB durante la microfase documental;
- writes a `profile_subscriptions`.

### L) Siguiente microfase recomendada
Readiness aprobada.

Siguiente microfase recomendada:

```text
BE/Suscripciones-CheckoutIntent-EntityValidationDependency-01
```

Objetivo:

- Implementar `SubscriptionEntityResolverService::resolveForCheckout(...)` como dependencia read-only para `doctor`, sin endpoint, sin checkout service, sin SQL y sin writes.

---

## Adenda PP-Decisiones 94 - Readiness final de implementacion de CreateSubscriptionCheckoutIntentService

### A) Objetivo
Esta adenda valida la readiness final para implementar posteriormente el servicio orquestador:

```text
modules/subscriptions/services/CreateSubscriptionCheckoutIntentService.php
```

Metodo futuro:

```php
createCheckoutIntent(array $input): array
```

Esta decision no implementa codigo y no reabre decisiones ya cerradas:

- checkout-first;
- primer write con aceptacion contractual `accepted_pending_payment`;
- checkout intent inicial `pending_payment`;
- resolver server-side de precios;
- operation de idempotencia `subscriptions.checkout_intent.create`;
- lock `mxmed:subscriptions:{entity_type}:{entity_id}:checkout_create`.

### B) Estado de dependencias
Dependencias revisadas en modo read-only:

- Entity resolver: listo. Existe `SubscriptionEntityResolverService::resolveForCheckout(...)`, soporta inicialmente `doctor`, lee `profiles_doctors` por `doctor_id` y no consulta `profile_subscriptions`.
- `active_subscription_exists`: listo. `CurrentSubscriptionRepository::activeSubscriptionExists(...)` y `findActiveByEntity(...)` leen `profile_subscriptions` y consideran estados `active`, `expiring_soon` y `grace_period`.
- Idempotencia checkout: lista. `SubscriptionWriteIdempotencyService` expone `beginCheckoutIntent(...)`, `buildCheckoutRequestHash(...)`, `markCheckoutIntentCompleted(...)` y operation `subscriptions.checkout_intent.create`; el replay se apoya en `response_body_text`.
- Lock checkout: listo. `SubscriptionEntityWriteLockService::acquireCheckoutCreate(...)` usa purpose `checkout_create` y lock final `mxmed:subscriptions:{entity_type}:{entity_id}:checkout_create`.
- Resolver de precios: listo. `SubscriptionPlanPriceResolverService::resolveForCheckout(...)` valida plan contratable, bloquea `free`, limita billing inicial a `annual` y devuelve snapshot de precio.
- Servicio `accepted_pending_payment`: listo. `CreateSubscriptionPendingPaymentAcceptanceService::createPendingPaymentAcceptance(...)` fuerza `status = accepted_pending_payment`, `subscription_id = null` y `source = checkout_intent`.
- Repository checkout-intents: listo. `SubscriptionCheckoutIntentRepository` expone `create(...)`, `findByUuid(...)`, `findPendingByEntity(...)` y `findPendingByEntityPlanAndBilling(...)`, usa `pending_payment` y no abre transaccion propia.

### C) Brechas resueltas
Quedan resueltas las brechas bloqueantes documentadas en decisiones previas:

- Entity validation.
- Active subscription validation.
- Idempotency checkout.
- Lock checkout.
- Price resolving server-side.
- Contract acceptance pending payment.
- Checkout intent storage.

No se detecta brecha bloqueante documental para implementar el servicio orquestador en la siguiente microfase.

### D) Decision de readiness
Readiness aprobada.

El servicio orquestador ya puede implementarse en una microfase posterior con alcance minimo controlado.

Archivo futuro recomendado:

```text
modules/subscriptions/services/CreateSubscriptionCheckoutIntentService.php
```

Clase futura:

```text
CreateSubscriptionCheckoutIntentService
```

Metodo futuro:

```php
createCheckoutIntent(array $input): array
```

### E) Alcance minimo futuro del servicio
El servicio futuro debe orquestar el primer write checkout-first:

- recibir input server-side;
- validar request basico;
- resolver entidad con `SubscriptionEntityResolverService`;
- iniciar idempotencia checkout con `beginCheckoutIntent(...)`;
- adquirir lock checkout con `acquireCheckoutCreate(...)`;
- revalidar entidad dentro del lock;
- validar `activeSubscriptionExists(...)` dentro del lock;
- revisar checkout pendiente con `findPendingByEntity(...)` y `findPendingByEntityPlanAndBilling(...)`;
- rechazar pending incompatible o competidor con error conceptual;
- resolver precio server-side con `SubscriptionPlanPriceResolverService::resolveForCheckout(...)`;
- abrir transaccion PDO superior;
- crear aceptacion `accepted_pending_payment`;
- crear checkout intent `pending_payment`;
- hacer commit;
- marcar idempotencia completed con `response_body_text`;
- liberar lock;
- en error, hacer rollback si aplica, liberar lock y marcar/limpiar idempotencia segun patron futuro.

### F) Orden futuro recomendado
Orden recomendado para `CreateSubscriptionCheckoutIntentService::createCheckoutIntent(...)`:

1. Validar request minimo.
2. Normalizar `entity_type`, `entity_id`, `plan_code`, `billing_period`, contrato y acceptance/source.
3. Calcular `request_hash` con `buildCheckoutRequestHash(...)`.
4. Iniciar idempotencia con `beginCheckoutIntent(...)` y operation `subscriptions.checkout_intent.create`.
5. Resolver replay estable si existe.
6. Bloquear payload distinto con `idempotency_key_reused_with_different_payload`.
7. Bloquear request en proceso con `request_already_processing`.
8. Adquirir lock con `acquireCheckoutCreate(...)`.
9. Revalidar entidad con `SubscriptionEntityResolverService::resolveForCheckout(...)`.
10. Validar `activeSubscriptionExists(...)`.
11. Revisar pending checkout con `findPendingByEntity(...)` y `findPendingByEntityPlanAndBilling(...)`.
12. Rechazar pending existente con `checkout_intent_already_pending`.
13. Resolver precio server-side.
14. Abrir transaccion superior con PDO.
15. Crear aceptacion con `CreateSubscriptionPendingPaymentAcceptanceService::createPendingPaymentAcceptance(...)`.
16. Crear checkout intent con `SubscriptionCheckoutIntentRepository::create(...)`.
17. Hacer commit.
18. Construir response minima estable.
19. Marcar idempotencia completed con `markCheckoutIntentCompleted(...)`.
20. Liberar lock.
21. Devolver response.
22. En error: rollback si transaccion abierta, liberar lock, marcar/limpiar idempotencia segun patron futuro, no dejar aceptacion huerfana y no dejar checkout sin aceptacion.

### G) Errores conceptuales minimos
El servicio futuro debe manejar, como minimo:

- `entity_type_invalid`;
- `entity_id_invalid`;
- `entity_not_found`;
- `entity_not_contractable`;
- `active_subscription_exists`;
- `checkout_intent_already_pending`;
- `plan_not_contractable`;
- `billing_period_invalid`;
- `plan_price_not_configured`;
- `pricing_configuration_conflict`;
- `checkout_lock_timeout`;
- `request_already_processing`;
- `idempotency_key_reused_with_different_payload`;
- `checkout_intent_create_failed`;
- `checkout_intent_unavailable`.

Tambien debe preservar errores de contrato/aceptacion ya previstos por dependencias:

- `contract_invalid`;
- `acceptance_source_invalid`;
- `contract_acceptance_create_failed`.

### H) Response conceptual minima
La respuesta estable del servicio futuro debe incluir:

- `checkout_intent_uuid`;
- `status = pending_payment`;
- snapshot de entidad;
- `plan_code`;
- `billing_period`;
- snapshot de precio:
  - `amount_cents`;
  - `currency`;
  - `price_source`;
  - `price_version`;
  - `price_uuid`, si viene del resolver;
- `contract_acceptance_uuid`;
- snapshot contractual:
  - `contract_version`;
  - `contract_hash`;
  - `contract_snapshot_url`;
- `expires_at`, si aplica;
- `created_at`;
- informacion de replay idempotente, si aplica.

### I) Restricciones obligatorias
La implementacion futura del servicio orquestador no debe:

- crear `profile_subscriptions`;
- crear `subscription_payment_intents`;
- crear `subscription_payment_events`;
- conectar provider;
- conectar webhooks;
- conectar facturacion;
- activar capacidades;
- tocar `PublicProfilePlanCapabilities`;
- tocar perfil publico/SEO;
- crear endpoint checkout-intents todavia;
- modificar frontend;
- crear SQL;
- modificar DB/schema.

### J) QA esperada para futura implementacion
QA minima cuando se implemente `CreateSubscriptionCheckoutIntentService`:

- `php -l` del servicio nuevo;
- `php -l` de dependencias;
- grep de clase `CreateSubscriptionCheckoutIntentService`;
- grep de metodo `createCheckoutIntent`;
- grep de dependencias usadas:
  - `SubscriptionEntityResolverService`;
  - `CurrentSubscriptionRepository`;
  - `SubscriptionWriteIdempotencyService`;
  - `SubscriptionEntityWriteLockService`;
  - `SubscriptionPlanPriceResolverService`;
  - `CreateSubscriptionPendingPaymentAcceptanceService`;
  - `SubscriptionCheckoutIntentRepository`;
- grep de transaccion `beginTransaction`, `commit`, `rollBack`;
- grep de `subscriptions.checkout_intent.create`;
- grep de `checkout_create`;
- grep de `accepted_pending_payment`;
- grep de `pending_payment`;
- grep prohibidos para `INSERT INTO profile_subscriptions`, `UPDATE profile_subscriptions`, `DELETE FROM profile_subscriptions`;
- grep prohibidos para `subscription_payment_intents`, `subscription_payment_events`, `provider`, `webhook`, `PublicProfilePlanCapabilities`;
- confirmar que no modifica endpoint;
- confirmar que no crea SQL;
- confirmar que no ejecuta SQL;
- QA DB/local solo en microfase posterior autorizada.

### K) Siguiente microfase recomendada
Readiness final aprobada.

Siguiente microfase recomendada:

```text
BE/Suscripciones-CheckoutIntent-Service-01
```

Objetivo:

- Implementar `CreateSubscriptionCheckoutIntentService::createCheckoutIntent(array $input): array` con alcance minimo, sin endpoint, sin SQL, sin DB/schema, sin provider, sin payment intents/events, sin `profile_subscriptions` y sin activar capacidades.

No avanzar a endpoint hasta cerrar servicio, commit, push y QA post-push del servicio.

---

## Adenda PP-Decisiones 95 - Readiness de implementacion del endpoint checkout-intents

### A) Objetivo
Esta adenda valida la readiness para implementar posteriormente el endpoint privado:

```text
POST /api/subscriptions/index.php/entities/{entity_type}/{entity_id}/checkout-intents
```

El endpoint futuro debe conectar de forma minima el servicio ya implementado:

```php
CreateSubscriptionCheckoutIntentService::createCheckoutIntent(array $input): array
```

Esta microfase es documental. No implementa endpoint, no modifica `api/subscriptions/index.php` y no cambia DB/schema.

### B) Estado de dependencias
Dependencias disponibles para la futura ruta:

- Servicio orquestador: listo. `CreateSubscriptionCheckoutIntentService::createCheckoutIntent(...)` existe y orquesta el primer write checkout-first.
- Entity resolver: listo. `SubscriptionEntityResolverService::resolveForCheckout(...)` valida `doctor` contra `profiles_doctors`.
- `active_subscription_exists`: listo. `CurrentSubscriptionRepository::activeSubscriptionExists(...)` valida suscripcion activa vigente.
- Idempotencia checkout: lista. `SubscriptionWriteIdempotencyService` soporta `subscriptions.checkout_intent.create`, `beginCheckoutIntent(...)`, `buildCheckoutRequestHash(...)` y `markCheckoutIntentCompleted(...)`.
- Lock checkout: listo. `SubscriptionEntityWriteLockService::acquireCheckoutCreate(...)` genera el lock `mxmed:subscriptions:{entity_type}:{entity_id}:checkout_create`.
- Resolver de precios: listo. `SubscriptionPlanPriceResolverService::resolveForCheckout(...)` resuelve precio server-side y bloquea planes no contratables.
- Aceptacion pending payment: lista. `CreateSubscriptionPendingPaymentAcceptanceService::createPendingPaymentAcceptance(...)` fuerza `accepted_pending_payment`, `subscription_id = null` y `source = checkout_intent`.
- Repository checkout-intents: listo. `SubscriptionCheckoutIntentRepository` crea `subscription_checkout_intents` con `pending_payment` y lookups pending.

### C) Hallazgos del endpoint actual
Hallazgos read-only sobre `api/subscriptions/index.php`:

- Routing: usa `subscriptionRelativeSegments()` y compara segmentos dentro del bloque principal `try`.
- Metodo HTTP: se normaliza con `$_SERVER['REQUEST_METHOD']` y `strtoupper(...)`.
- Path: rutas actuales se expresan como arrays de segmentos, por ejemplo `entities/{entity_type}/{entity_id}/subscriptions` y `entities/{entity_type}/{entity_id}/current`.
- JSON body: `subscriptionReadJsonPayload()` valida `Content-Type: application/json`, lee `php://input`, decodifica JSON y devuelve error `invalid_payload`.
- Respuesta JSON: `subscriptionRespond(...)` centraliza `http_response_code(...)` y `json_encode(...)`.
- Errores: existen helpers `subscriptionError(...)`, `subscriptionWriteError(...)`, `subscriptionContextError(...)` y metas por contrato.
- PDO: se instancia con `mxmed_pdo()` desde `api/_lib/db.php`.
- Dependencias: se cargan con `require_once` al inicio del archivo y se importan con `use`.
- Auth/private write: el flujo de writes actual usa `subscriptionResolveWriteContext(...)`, exige local/dev, rechaza header scope para writes, exige sesion real y restringe writes a `doctor`.
- Idempotencia actual: lee `Idempotency-Key` desde `subscriptionHeaders()` y usa servicios de idempotencia antes del write contractual actual.
- Endpoint checkout-intents: no existe todavia ruta `checkout-intents` en `api/subscriptions/index.php`.

### D) Decision de readiness
Readiness aprobada.

El endpoint privado checkout-intents ya puede implementarse en una siguiente microfase con alcance minimo controlado.

Archivo futuro a modificar:

```text
api/subscriptions/index.php
```

Ruta futura:

```text
POST /api/subscriptions/index.php/entities/{entity_type}/{entity_id}/checkout-intents
```

La implementacion futura debe limitarse a cablear dependencias, validar request/context, llamar `CreateSubscriptionCheckoutIntentService::createCheckoutIntent(...)` y responder JSON.

### E) Alcance minimo futuro del endpoint
El endpoint futuro debe:

- aceptar solo `POST`;
- ser privado y usar el patron de write context existente;
- recibir `entity_type` y `entity_id` desde path;
- leer `Idempotency-Key` desde header;
- leer JSON body con `subscriptionReadJsonPayload()`;
- recibir desde body:
  - `plan_code`;
  - `billing_period`;
  - `contract_version`;
  - `contract_hash`;
  - `contract_snapshot_url`;
  - `source`;
  - campos contractuales opcionales permitidos, si se deciden en la microfase de endpoint;
- construir input para `CreateSubscriptionCheckoutIntentService`;
- inyectar contexto server-side:
  - `actor_user_id`;
  - `actor_role`;
  - `doctor_id`;
  - `profile_id`;
  - `operator_id`;
  - `ip_address`;
  - `user_agent`;
- invocar `createCheckoutIntent(...)`;
- responder JSON estable con `201` para creacion o con el status de replay idempotente si el servicio lo devuelve asi;
- mapear errores conceptuales a HTTP segun el contrato de esta adenda.

El endpoint no debe aceptar como canonicos desde el cliente:

- `amount_cents`;
- `currency`;
- `price_source`;
- `price_version`;
- `price_uuid`.

El precio debe seguir resolviendose server-side.

### F) Mapeo HTTP conceptual sugerido
Mapeo sugerido para la futura implementacion:

- `201`: checkout intent creado.
- `200`: replay idempotente completed si el servicio lo devuelve asi.
- `400`: JSON mal formado, body ausente o content-type invalido.
- `401`: autenticacion ausente cuando el patron actual lo requiera.
- `403`: contexto privado/write no autorizado.
- `404`: `entity_not_found`.
- `409`: `active_subscription_exists`, `checkout_intent_already_pending`, `request_already_processing`, `idempotency_key_reused_with_different_payload`, lock timeout.
- `422`: `entity_type_invalid`, `entity_id_invalid`, `entity_not_contractable`, `plan_not_contractable`, `billing_period_invalid`, `plan_price_not_configured`, `pricing_configuration_conflict`, `contract_invalid`, `acceptance_source_invalid`, payload invalido.
- `500`: `checkout_intent_unavailable`, `entity_validation_unavailable` y errores inesperados.

Si el servicio expone una excepcion con `status()` y `errorCode()`, el endpoint futuro debe respetar esos valores.

### G) Request minimo futuro
Request minimo esperado:

```json
{
  "plan_code": "standard",
  "billing_period": "annual",
  "contract_version": "mxmed-contract-v1",
  "contract_hash": "sha256:...",
  "contract_snapshot_url": "https://...",
  "source": "checkout_intent"
}
```

Header requerido para idempotencia:

```text
Idempotency-Key: <stable-client-key>
```

Campos backend-controlled que deben rechazarse si aparecen como fuente canonica:

- `amount_cents`;
- `currency`;
- `price_source`;
- `price_version`;
- `price_uuid`;
- `status`;
- `subscription_id`;
- `contract_acceptance_uuid`;
- `checkout_intent_uuid`;
- provider/payment/capabilities fields.

### H) Response minima futura
La respuesta minima debe preservar la estructura del servicio:

- `checkout_intent_uuid`;
- `status = pending_payment`;
- `entity`;
- `plan_code`;
- `billing_period`;
- `price`;
- `contract_acceptance_uuid`;
- `contract`;
- `idempotency`;
- `source`.

La respuesta no debe incluir activacion de plan, capacidades, `profile_subscriptions`, payment intent ni provider.

### I) Restricciones obligatorias
La futura implementacion del endpoint no debe:

- crear `profile_subscriptions`;
- crear `subscription_payment_intents`;
- crear `subscription_payment_events`;
- conectar provider;
- conectar webhooks;
- conectar facturacion;
- activar capacidades;
- tocar `PublicProfilePlanCapabilities`;
- tocar perfil publico/SEO;
- modificar frontend;
- crear SQL;
- modificar DB/schema;
- ejecutar SQL fuera de las operaciones normales del servicio al recibir request.

### J) QA esperada para futura implementacion
QA minima cuando se implemente la ruta:

- `php -l api/subscriptions/index.php`;
- `php -l modules/subscriptions/services/CreateSubscriptionCheckoutIntentService.php`;
- grep de `checkout-intents`;
- grep de route `POST`;
- grep de `require_once` de dependencias nuevas;
- grep de `Idempotency-Key`;
- grep de `createCheckoutIntent`;
- grep de mapeo de errores conceptuales;
- grep prohibidos para `INSERT INTO profile_subscriptions`, `UPDATE profile_subscriptions`, `DELETE FROM profile_subscriptions`;
- grep prohibidos para `subscription_payment_intents`, `subscription_payment_events`, `provider`, `webhook`, `PublicProfilePlanCapabilities`;
- confirmar sin SQL nuevo;
- confirmar sin DB/schema;
- confirmar sin frontend.

### K) Siguiente microfase recomendada
Readiness aprobada.

Siguiente microfase recomendada:

```text
BE/Suscripciones-CheckoutIntent-Endpoint-01
```

Objetivo:

- Implementar la ruta privada `POST /api/subscriptions/index.php/entities/{entity_type}/{entity_id}/checkout-intents` conectando `CreateSubscriptionCheckoutIntentService::createCheckoutIntent(...)`, sin frontend, sin SQL, sin DB/schema, sin provider, sin payment intents/events, sin `profile_subscriptions` y sin activar capacidades.

No avanzar a frontend ni pagos hasta cerrar endpoint, commit, push y QA post-push.

---

## Adenda PP-Decisiones 96 - Plan de fixture DEV/local para QA funcional checkout-intents

### A) Objetivo
Definir un fixture DEV/local seguro para habilitar una prueba positiva `201` del endpoint privado:

```text
POST /api/subscriptions/index.php/entities/{entity_type}/{entity_id}/checkout-intents
```

Esta adenda es solo documental. No ejecuta SQL, no consulta DB, no crea fixture real, no modifica schema y no toca datos existentes.

### B) Problema
El diagnostico read-only previo de fixture libre dejo cerrado el siguiente estado local/dev:

- La DB local/dev solo tiene doctores `1`, `2` y `3`.
- Los doctores `1`, `2` y `3` tienen suscripcion activa.
- Esos doctores sirven para validar bloqueo `active_subscription_exists`, no para positive `201`.
- No existe doctor libre para crear checkout intent positivo sin tocar datos existentes.
- Counts base observados en el diagnostico:
  - `subscription_checkout_intents = 0`;
  - `subscription_contract_acceptances = 3`;
  - `profile_subscriptions = 3`;
  - `subscription_payment_intents = 0`;
  - `subscription_payment_events = 0`.

Para probar `201` se necesita una entidad `doctor` existente, sin suscripcion activa y sin checkout intent `pending_payment` compatible.

### C) Decision
La estrategia aprobada es crear en una microfase posterior un doctor fixture DEV/local controlado.

Reglas de la decision:

- No tocar doctores `1`, `2` ni `3`.
- No tocar doctores 1/2/3.
- No limpiar datos previos.
- No borrar suscripciones existentes.
- No actualizar suscripciones existentes.
- No truncar tablas.
- No usar este fixture en produccion.
- No usar migracion productiva.
- No modificar DB/schema.
- No crear `profile_subscriptions` directamente.
- No crear payment intents/events.

### D) Fixture recomendado
Fixture recomendado:

```text
QA Checkout Doctor Libre
```

Caracteristicas requeridas:

- Debe existir en `profiles_doctors`.
- Debe usar un `doctor_id` nuevo, no usado y distinto de `1`, `2` y `3`.
- Debe tener `display_name` identificable, por ejemplo `QA Checkout Doctor Libre`.
- `profile_status` e `is_public_candidate` solo deben definirse si el schema los requiere o si el resolver necesita valores consistentes.
- No debe tener filas en `profile_subscriptions`.
- No debe tener checkout intent `pending_payment` al inicio.
- Debe poder ser resuelto por `SubscriptionEntityResolverService::resolveForCheckout(...)`.

El resolver de entidad usa `profiles_doctors.doctor_id` y lee como campos informativos `display_name`, `profile_status` e `is_public_candidate`.

### E) Estrategia SQL futura
La creacion real del fixture debe quedar para una microfase separada.

Estrategia recomendada:

- Preparar un SQL draft DEV/local-only.
- Mantenerlo fuera de migraciones productivas.
- Limitarlo a insertar un doctor fixture en `profiles_doctors`.
- No crear tablas.
- No alterar columnas.
- No crear `profile_subscriptions`.
- No crear `subscription_checkout_intents`.
- No crear `subscription_contract_acceptances`.
- No crear `subscription_payment_intents`.
- No crear `subscription_payment_events`.
- No usar `DELETE`.
- No usar `UPDATE`.
- No usar `TRUNCATE`.
- No limpiar datos despues.

La siguiente fase debe revisar el SQL draft sin ejecutarlo. La ejecucion, si se autoriza, debe ocurrir en otra microfase explicita.

### F) Validaciones futuras previas al positive 201
Antes de ejecutar el caso positivo `201`, una microfase funcional posterior debe confirmar:

- El doctor fixture existe en `profiles_doctors`.
- El doctor fixture no es `1`, `2` ni `3`.
- El doctor fixture no tiene suscripcion activa en `profile_subscriptions`.
- El doctor fixture no tiene checkout intent `pending_payment` compatible en `subscription_checkout_intents`.
- Los counts base antes de la prueba:
  - `subscription_checkout_intents`;
  - `subscription_contract_acceptances`;
  - `profile_subscriptions`;
  - `subscription_payment_intents`;
  - `subscription_payment_events`.

### G) QA funcional futura
La QA funcional posterior debe usar el doctor fixture nuevo para el caso positive `201`.

Validacion esperada:

- Request valido con `standard / annual`.
- Header `Idempotency-Key` estable.
- Respuesta `201`.
- Incrementa exactamente:
  - `subscription_contract_acceptances +1`;
  - `subscription_checkout_intents +1`.
- No incrementa:
  - `profile_subscriptions`;
  - `subscription_payment_intents`;
  - `subscription_payment_events`.
- La aceptacion contractual queda con `status = accepted_pending_payment`.
- El checkout intent queda con `status = pending_payment`.
- El replay idempotente con misma key y mismo payload no duplica filas.
- La misma key con payload distinto devuelve `idempotency_key_reused_with_different_payload`.
- Un segundo request con checkout pending compatible devuelve `checkout_intent_already_pending` o el comportamiento documentado por el servicio.
- No se limpian datos despues.

### H) Restricciones
Restricciones para fixture y QA funcional:

- DEV/local only.
- No produccion.
- No crear fixture real en esta microfase.
- No ejecutar SQL en esta microfase.
- No consultar DB en esta microfase.
- No ejecutar HTTP en esta microfase.
- No modificar codigo PHP.
- No modificar `api/subscriptions/index.php`.
- No modificar servicios/repositorios.
- No modificar DB/schema.
- No tocar doctores `1`, `2` ni `3`.
- No hacer write directo a `profile_subscriptions`.
- No payment intents/events.
- No provider/webhook/facturacion/capacidades.
- No perfil publico/SEO.
- No frontend.

### I) Siguiente microfase recomendada
Siguiente microfase recomendada:

```text
DB/SPEC-Suscripciones-CheckoutIntent-Endpoint-DevFixture-SQLDraft-01
```

Objetivo:

- Crear un SQL draft DEV/local-only para insertar el doctor fixture seguro `QA Checkout Doctor Libre`, sin ejecutarlo todavia, sin modificar DB/schema, sin tocar doctores `1`, `2` ni `3`, sin limpiar datos y sin crear `profile_subscriptions`.

---

## Adenda PP-Decisiones 97 — Plan de helper DEV/local para sesion checkout doctor 900001

### A) Problema
La prueba funcional positive `201` del endpoint:

```text
POST /api/subscriptions/index.php/entities/doctor/900001/checkout-intents
```

requiere una sesion PHP local/dev real con `session_scope` para `doctor 900001`.

Estado confirmado:

- El fixture `doctor_id = 900001` existe en DEV/local.
- El endpoint checkout-intents rechaza writes con headers de identidad.
- `local_dev_open` no autoriza writes.
- No existe actualmente una sesion PHP valida para `doctor 900001`.
- No se debe ejecutar el POST positive `201` hasta contar con una sesion valida.

### B) Decision
Crear en una microfase futura un helper DEV/local especifico para generar una sesion PHP real de checkout para `doctor 900001`.

Ruta conceptual futura:

```text
POST /api/subscriptions/index.php/dev/session-fixture/checkout-doctor
```

El helper debe reutilizar el patron local/dev existente de `dev/session-fixture`, pero apuntando al fixture de checkout `doctor 900001`.

### C) Guardas obligatorias
El helper futuro debe fallar cerrado salvo que se cumplan todas estas condiciones:

- `MXMED_SUBSCRIPTIONS_DEV_SESSION_FIXTURE_ENABLED=1`.
- Host local permitido:
  - `127.0.0.1`;
  - `localhost`;
  - `::1`.
- Bloqueo explicito si se detecta ambiente productivo.
- Metodo permitido: `POST`.
- No debe aceptar headers de identidad como bypass de autorizacion.
- No debe relajar `subscriptionResolveWriteContext(...)`.
- No debe modificar el endpoint checkout-intents.

### D) Sesion conceptual
La sesion creada por el helper futuro debe ser una sesion PHP real, compatible con `session_scope`, con valores conceptuales:

- `user_id`: valor local/dev numerico controlado.
- `doctor_id = 900001`.
- `entity_type = doctor`.
- `entity_id = 900001`.
- `actor_role = doctor` o vacio si el guard actual lo permite de forma segura.
- Sin `operator_id`.
- Sin permisos de operador.
- Sin headers de identidad para autorizar el write.
- Cookie `PHPSESSID` real para usar en la microfase funcional posterior.

### E) Prohibiciones
El helper futuro no debe:

- Ejecutarse en produccion.
- Usar headers como bypass de write.
- Ejecutar SQL writes.
- Crear `profile_subscriptions`.
- Crear `subscription_checkout_intents`.
- Crear `subscription_contract_acceptances`.
- Crear `subscription_payment_intents`.
- Crear `subscription_payment_events`.
- Conectar provider.
- Implementar webhooks.
- Conectar facturacion.
- Activar capacidades.
- Limpiar datos.
- Tocar doctores `1`, `2` ni `3`.
- Modificar DB/schema.

### F) QA futura
Microfases futuras recomendadas:

1. `BE/SPEC-Suscripciones-CheckoutIntent-Endpoint-DevSessionFixture-Doctor900001-Readiness-01`
   - Validar readiness tecnica para agregar el helper DEV/local sin ejecutar checkout-intents.
2. `BE/Suscripciones-CheckoutIntent-Endpoint-DevSessionFixture-Doctor900001-01`
   - Implementar el helper DEV/local para `doctor 900001`, sin SQL writes y sin endpoint checkout-intents.
3. `QA-Suscripciones-CheckoutIntent-Endpoint-DevSessionFixture-Doctor900001-PostPush-01`
   - Validar post-push que el helper esta protegido por bandera, host local, bloqueo produccion y metodo POST.
4. `QA/Suscripciones-CheckoutIntent-Endpoint-SessionScope-Doctor900001-Generate-01`
   - Generar una cookie `PHPSESSID` real para `doctor 900001`, sin ejecutar checkout-intents.
5. `QA/Suscripciones-CheckoutIntent-Endpoint-FunctionalControlled-Positive201-Execute-01`
   - Reintentar el positive `201` usando la cookie real generada.

### G) Alcance de esta adenda
Esta adenda solo planifica el helper DEV/local.

No implementa:

- Helper DEV/local.
- Endpoint checkout-intents.
- HTTP/POST.
- SQL writes.
- DB/schema.
- Sesion real.
- Checkout intent.
- Aceptacion contractual.
- `profile_subscriptions`.
- Payment intents/events.
- Provider/webhook/facturacion/capacidades.

### H) Siguiente microfase recomendada
Siguiente microfase recomendada:

```text
BE/SPEC-Suscripciones-CheckoutIntent-Endpoint-DevSessionFixture-Doctor900001-Readiness-01
```

Objetivo:

- Validar readiness tecnica para implementar un helper DEV/local que genere una sesion PHP real con `session_scope` para `doctor 900001`, sin ejecutar checkout-intents, sin SQL writes y sin modificar DB/schema.

---

## Adenda PP-Decisiones 98 — Readiness de helper DEV/local para sesión checkout doctor 900001

### A) Resultado de readiness
Readiness aprobada para implementar, en una microfase futura, un helper DEV/local que cree una sesion PHP real con `session_scope` para `doctor_id = 900001`.

Esta readiness no implementa el helper y no cambia el endpoint checkout-intents.

### B) Patron existente confirmado
`api/subscriptions/index.php` ya contiene un patron de helpers DEV/local para sesion:

- `POST /api/subscriptions/index.php/dev/session-fixture`.
- `POST /api/subscriptions/index.php/dev/session-fixture/alternate-doctor`.
- `POST /api/subscriptions/index.php/dev/session-fixture/concurrency-doctor`.

El patron existente:

- exige metodo `POST`;
- exige `MXMED_SUBSCRIPTIONS_DEV_SESSION_FIXTURE_ENABLED=1`;
- exige host local;
- bloquea ambiente productivo;
- crea sesion PHP real mediante `subscriptionApplyDevDoctorSessionFixture(...)`;
- no hace SQL writes para crear la sesion;
- devuelve `auth_mode = session_scope`.

### C) Ruta futura compatible
La ruta futura compatible es:

```text
POST /api/subscriptions/index.php/dev/session-fixture/checkout-doctor
```

Puede implementarse en `api/subscriptions/index.php` junto a los helpers DEV/local existentes, antes del bloque generico que responde `not_found` para rutas `dev`.

### D) Archivo futuro permitido
Archivo futuro permitido para implementar el helper:

```text
api/subscriptions/index.php
```

No se requiere modificar servicios ni repositorios para esta pieza.

### E) Guardas obligatorias
El helper futuro debe conservar las guardas existentes:

- `MXMED_SUBSCRIPTIONS_DEV_SESSION_FIXTURE_ENABLED=1`.
- Host local:
  - `127.0.0.1`;
  - `localhost`;
  - `::1`.
- Bloqueo si `APP_ENV`, `MXMED_ENV` o `ENVIRONMENT` indican produccion.
- Metodo `POST`.
- Fallar cerrado si el fixture `doctor_id = 900001` no existe.
- Fallar cerrado si `doctor_id = 900001` tiene suscripcion activa.
- No aceptar headers de identidad como autorizacion de write.

### F) Sesion minima futura
La sesion futura debe poblar las mismas claves que el patron existente:

- `user_id`: valor numerico local/dev controlado.
- `doctor_id = 900001`.
- `entity_type = doctor`.
- `entity_id = 900001`.
- `actor_role = doctor`.
- `subscriptions_dev_session_fixture = 1`.
- `operator_id` ausente.
- `operator_permissions` ausente.
- `permissions` ausente.
- `mxmed_permissions` ausente.
- `scopes` ausente.
- `user_role`, `role` y `mxmed_user_role` ausentes, salvo decision futura explicita.

Compatibilidad con `subscriptionResolveWriteContext(...)`:

- Rechaza `header_scope` para writes.
- Rechaza `local_dev_open` para writes.
- Exige `user_id` numerico.
- Exige `entity_type = doctor`.
- Exige `doctor_id` o `entity_id` igual al path `900001`.
- Rechaza `operator_id`.
- Acepta `actor_role = doctor`.

### G) Estado DB read-only observado
El fixture `doctor_id = 900001` sigue listo para la implementacion futura:

- Existe en `profiles_doctors`.
- `profile_status = active`.
- `is_public_candidate = 1`.
- No tiene `profile_subscriptions`.
- No tiene `subscription_checkout_intents`.
- No tiene `subscription_contract_acceptances`.

Counts base esperados para la siguiente fase:

- `profiles_doctors = 4`.
- `subscription_checkout_intents = 0`.
- `subscription_contract_acceptances = 3`.
- `profile_subscriptions = 3`.
- `subscription_payment_intents = 0`.
- `subscription_payment_events = 0`.

### H) Prohibiciones
La implementacion futura del helper no debe:

- Ejecutar checkout-intents.
- Ejecutar HTTP/POST funcional del checkout.
- Ejecutar SQL writes.
- Modificar DB/schema.
- Crear `profile_subscriptions`.
- Crear `subscription_checkout_intents`.
- Crear `subscription_contract_acceptances`.
- Crear `subscription_payment_intents`.
- Crear `subscription_payment_events`.
- Conectar provider.
- Implementar webhooks.
- Conectar facturacion.
- Activar capacidades.
- Limpiar datos.
- Tocar doctores `1`, `2` ni `3`.
- Modificar servicios/repositorios.

### I) Siguiente microfase recomendada
Siguiente microfase recomendada:

```text
BE/Suscripciones-CheckoutIntent-Endpoint-DevSessionFixture-Doctor900001-01
```

Objetivo:

- Implementar en `api/subscriptions/index.php` el helper DEV/local `POST /api/subscriptions/index.php/dev/session-fixture/checkout-doctor`, protegido por flag, host local, bloqueo produccion y metodo `POST`, sin ejecutar checkout-intents, sin SQL writes y sin modificar DB/schema.

---

## Adenda PP-Decisiones 99 - Cierre de QA funcional controlada del endpoint checkout-intents

### A) Alcance del cierre
Esta adenda cierra documentalmente la QA funcional controlada del endpoint:

```text
POST /api/subscriptions/index.php/entities/{entity_type}/{entity_id}/checkout-intents
```

Alcance validado:

- Entorno local/dev.
- Fixture principal `doctor/900001`.
- Helper DEV/local de sesion para generar `session_scope` valido.
- Endpoint checkout-first sin provider, sin pagos, sin webhooks y sin facturacion.
- Verificaciones read-only de DB local/dev posteriores a los casos funcionales.

Fuera de este cierre:

- Provider adapter.
- `subscription_payment_intents`.
- `subscription_payment_events`.
- Activacion post-pago.
- `profile_subscriptions` productiva para el checkout.
- Facturacion.
- Webhooks.
- Limpieza de fixture local/dev.

### B) Casos PASS documentados
Los siguientes casos quedaron ejecutados y aprobados:

1. Positive201:
   - Resultado: `HTTP 201`.
   - Creo `subscription_checkout_intents` con `status = pending_payment`.
   - Creo `subscription_contract_acceptances` con `status = accepted_pending_payment`.
   - Checkout creado: `7d4beec3-b62a-40e1-a9f2-9edcc1a83364`.
   - Acceptance creada: `ae137e4c-75f7-42cb-a6be-7cd24e051ca9`.
   - Plan: `standard`.
   - Billing: `annual`.
   - Monto: `20000 MXN`.

2. Replay same key:
   - Resultado: `HTTP 200`.
   - `meta.idempotent_replay = true`.
   - Devolvio el mismo checkout intent y la misma acceptance.
   - No duplico `subscription_checkout_intents`.
   - No duplico `subscription_contract_acceptances`.

3. Different payload same key:
   - Resultado: `HTTP 409`.
   - Error: `idempotency_key_reused_with_different_payload`.
   - Bloqueo el reuso de la misma `Idempotency-Key` con payload distinto.
   - No duplico filas funcionales.

4. Pending existing new key:
   - Resultado: `HTTP 409`.
   - Error: `checkout_intent_already_pending`.
   - Bloqueo una nueva creacion con otra `Idempotency-Key` porque ya existia checkout `pending_payment`.
   - No duplico checkout ni acceptance.

5. Active subscription exists:
   - Resultado: `HTTP 409`.
   - Error: `active_subscription_exists`.
   - Doctor activo usado: `doctor/1`.
   - Confirmo que una suscripcion activa bloquea el checkout.
   - No creo filas funcionales nuevas.

6. Invalid payload:
   - Resultado: `HTTP 422`.
   - Error: `contract_invalid`.
   - Caso ejecutado con `contract_hash` y `contract_snapshot_url` omitidos.
   - Bloqueo antes de writes funcionales.

7. No session auth:
   - Resultado: `HTTP 403`.
   - Error: `forbidden`.
   - Mensaje observado: `local_dev_open does not authorize writes`.
   - Confirmo que headers/local dev open no autorizan writes privados.
   - No creo filas funcionales nuevas.

8. Missing Idempotency-Key:
   - Resultado: `HTTP 422`.
   - Error: `idempotency_key_invalid`.
   - Mensaje observado: `Idempotency-Key is required`.
   - Bloqueo antes de `buildCheckoutRequestHash(...)` y antes de `beginCheckoutIntent(...)`.
   - No creo idempotencia nueva ni filas funcionales.

### C) Estado funcional DB final
Estado read-only final confirmado en local/dev:

- `profiles_doctors = 4`.
- `subscription_checkout_intents = 1`.
- `subscription_contract_acceptances = 4`.
- `profile_subscriptions = 3`.
- `subscription_payment_intents = 0`.
- `subscription_payment_events = 0`.

Checkout funcional creado para el fixture:

- `uuid = 7d4beec3-b62a-40e1-a9f2-9edcc1a83364`.
- `entity_type = doctor`.
- `entity_id = 900001`.
- `status = pending_payment`.
- `plan_code = standard`.
- `billing_period = annual`.
- `amount_cents = 20000`.
- `currency = MXN`.
- `deleted_at = NULL`.

Acceptance contractual creada para el fixture:

- `uuid = ae137e4c-75f7-42cb-a6be-7cd24e051ca9`.
- `entity_type = doctor`.
- `entity_id = 900001`.
- `status = accepted_pending_payment`.
- `plan_code = standard`.
- `billing_period = annual`.

Resumen read-only de idempotencia observado, sin exponer hashes:

- `subscriptions.checkout_intent.create`, `completed`, `response_http_status = 201`, total `1`.
- `subscriptions.checkout_intent.create`, `failed`, `response_http_status = 409`, total `2`.
- `subscriptions.create_with_contract_acceptance`, `completed`, `response_http_status = 201`, total `2`.
- `subscriptions.create_with_contract_acceptance`, `failed`, `response_http_status = 409`, total `3`.

### D) Confirmaciones de contrato
Quedan confirmadas para el endpoint checkout-intents:

- El flujo checkout-first crea acceptance contractual pending y checkout intent pending.
- La acceptance queda en `accepted_pending_payment`.
- El checkout intent queda en `pending_payment`.
- No se crea `profile_subscriptions` hasta pago confirmado y activacion posterior.
- No se crean `subscription_payment_intents` en este endpoint.
- No se crean `subscription_payment_events` en este endpoint.
- No se ejecutan providers.
- No se ejecutan webhooks.
- No se ejecuta facturacion.
- La idempotencia positiva no duplica filas.
- El replay con la misma key devuelve respuesta estable.
- El reuso de la misma key con payload distinto bloquea con `idempotency_key_reused_with_different_payload`.
- Un checkout `pending_payment` existente bloquea nueva creacion con `checkout_intent_already_pending`.
- Una suscripcion activa bloquea el checkout con `active_subscription_exists`.
- Los writes privados requieren `session_scope`.
- `local_dev_open` y headers sin sesion no autorizan writes.
- Payload invalido bloquea antes de writes funcionales.
- La falta de `Idempotency-Key` bloquea antes de crear idempotencia.

### E) Restricciones confirmadas
Durante la QA funcional controlada:

- No se creo `profile_subscriptions` para `doctor/900001`.
- No se crearon `subscription_payment_intents`.
- No se crearon `subscription_payment_events`.
- No se conecto provider.
- No se ejecuto webhook.
- No se hizo facturacion.
- No se activaron capacidades.
- No se tocaron funcionalmente doctores `1`, `2` ni `3`, salvo sesion y POST de prueba para validar `active_subscription_exists` sin writes funcionales.
- No se limpiaron datos locales/dev.

### F) Advertencias no bloqueantes
Advertencias observadas:

- En el servidor local, algunos `HTTP 422` se muestran como `422 Unknown Status Code`; el codigo numerico y el JSON de error son correctos.
- Algunas pruebas negativas posteriores a `beginCheckoutIntent(...)` generan filas de idempotencia `failed`; esas filas no duplican checkout, acceptance, pagos ni suscripciones.
- La prueba `Missing Idempotency-Key` bloquea antes de idempotencia y no crea fila nueva en `subscription_write_idempotency_keys`.
- La DB local/dev conserva el fixture `doctor/900001` con checkout `pending_payment` para pruebas posteriores.
- La DB local/dev conserva los doctores `1`, `2` y `3` con suscripciones activas para casos de bloqueo.

### G) Siguiente recomendacion
Siguiente microfase recomendada:

```text
QA-Suscripciones-CheckoutIntent-Endpoint-FunctionalControlled-QAClosure-PostPush-01
```

Objetivo:

- Validar post-push que `PP-Decisiones 99` quedo versionada, que el working tree esta limpio, que no hay cambios PHP y que el cierre documental de QA funcional controlada conserva los resultados y restricciones confirmadas.

Despues de ese post-push, decidir una sola ruta:

- Documentacion final del endpoint checkout-intents.
- Limpieza controlada DEV/local del fixture, si se autoriza expresamente.
- Siguiente bloque de diseno/implementacion de pagos/provider.

---

## Adenda PP-Decisiones 100 - Plan de repositorio y servicio de payment intent

### A) Proposito
Esta adenda documenta el plan tecnico para implementar, en microfases posteriores, la capa interna de payment intents de suscripciones:

- `SubscriptionPaymentIntentRepository`.
- `CreateSubscriptionPaymentIntentService` o servicio equivalente.
- Relacion controlada con `subscription_checkout_intents`.
- Idempotencia de creacion de payment intent.
- Estados normalizados iniciales.
- Anti-duplicado por checkout intent.
- Preparacion para provider mock/dev futuro.

Esta adenda es solo documental. No implementa repositorio, servicio, endpoint, provider, webhook, SQL, migraciones, DB/schema ni activacion de suscripcion.

### B) Estado actual
Estado confirmado:

- El flujo checkout-first ya fue validado funcionalmente y cerrado en `PP-Decisiones 99`.
- Existe un checkout pending local/dev para `doctor/900001`, plan `standard`, periodo `annual`, monto `20000 MXN`, status `pending_payment`.
- Existe acceptance contractual relacionada con status `accepted_pending_payment`.
- El schema local/dev contempla `subscription_payment_intents`.
- El schema local/dev contempla `subscription_payment_events`.
- `subscription_payment_intents` esta vacia en el estado inspeccionado.
- `subscription_payment_events` esta vacia en el estado inspeccionado.
- No existe aun repositorio PHP de payment intents.
- No existe aun servicio PHP de payment intents.
- No existe aun repositorio/servicio PHP de payment events.
- No existe provider adapter.
- No existe webhook de pagos.
- No existe provider real integrado.

Columnas observadas en `subscription_payment_intents`:

- Identidad y relacion: `id`, `uuid`, `checkout_intent_uuid`.
- Provider: `provider`, `provider_payment_id`, `provider_checkout_id`.
- Estado: `normalized_status`, `provider_status`.
- Monto: `amount_cents`, `currency`.
- Fechas provider: `created_at_provider`, `expires_at`, `paid_at`, `failed_at`, `cancelled_at`.
- Auditoria: `source`, `notes`, `created_at`, `updated_at`, `deleted_at`.

Columnas observadas en `subscription_payment_events`:

- Identidad y relacion: `id`, `uuid`, `checkout_intent_uuid`, `payment_intent_uuid`.
- Provider/evento: `provider`, `provider_event_id`, `provider_payment_id`, `event_type`, `provider_status`, `normalized_status`.
- Monto/event hash: `amount_cents`, `currency`, `event_hash`.
- Proceso: `signature_validated_at`, `received_at`, `processed_at`, `processing_status`, `error_message`, `payload_text_sanitized`.
- Auditoria: `source`, `notes`, `created_at`, `updated_at`, `deleted_at`.

### C) Decision
Se decide planificar primero la capa interna repository/service de payment intent antes de conectar provider real.

La ruta recomendada es:

1. Validar readiness especifica del repositorio `SubscriptionPaymentIntentRepository`.
2. Implementar repositorio aislado de `subscription_payment_intents`.
3. Validar readiness del servicio `CreateSubscriptionPaymentIntentService`.
4. Implementar servicio aislado de creacion de payment intent.
5. Disenar provider mock/dev antes de cualquier provider real.
6. Disenar eventos/webhooks despues de tener adapter y validacion de firma.
7. Disenar activacion post-pago en microfase separada.

No se debe activar suscripcion hasta confirmacion de pago en un flujo posterior explicitamente autorizado.

### D) Responsabilidad del futuro repositorio
Repositorio futuro sugerido:

```text
modules/subscriptions/repositories/SubscriptionPaymentIntentRepository.php
```

Clase futura sugerida:

```text
SubscriptionPaymentIntentRepository
```

Responsabilidades:

- Buscar payment intent por `uuid`.
- Buscar payment intents por `checkout_intent_uuid`.
- Buscar payment intent activo/pending por `checkout_intent_uuid`.
- Crear payment intent de forma atomica en `subscription_payment_intents`.
- Persistir snapshot recibido de provider, amount y currency.
- Persistir `provider_checkout_id`, `provider_payment_id` y `provider_status` cuando existan.
- Usar los indices reales para evitar duplicados por `uuid` y por pares provider.
- Devolver arrays normalizados con keys estables.
- Filtrar `deleted_at IS NULL` en consultas operativas.

El repositorio NO debe:

- Modificar `subscription_checkout_intents` salvo microfase explicita posterior.
- Crear `subscription_payment_events`.
- Crear `profile_subscriptions`.
- Resolver precios.
- Validar contrato.
- Validar auth/session.
- Manejar idempotencia.
- Manejar lock.
- Llamar provider real.
- Ejecutar webhook.
- Facturar.
- Activar capacidades.

Metodos conceptuales sugeridos:

```text
create(array $snapshot): array
findByUuid(string $uuid): ?array
findByCheckoutIntentUuid(string $checkoutIntentUuid): array
findActiveByCheckoutIntentUuid(string $checkoutIntentUuid): ?array
findByProviderPaymentId(string $provider, string $providerPaymentId): ?array
findByProviderCheckoutId(string $provider, string $providerCheckoutId): ?array
```

### E) Responsabilidad del futuro servicio
Servicio futuro sugerido:

```text
modules/subscriptions/services/CreateSubscriptionPaymentIntentService.php
```

Clase futura sugerida:

```text
CreateSubscriptionPaymentIntentService
```

Responsabilidades:

- Recibir un checkout intent `pending_payment`.
- Validar que el checkout intent existe.
- Validar que el checkout intent no esta eliminado.
- Validar que el checkout intent sigue en `pending_payment`.
- Validar `amount_cents` y `currency` contra el snapshot MXMed del checkout intent.
- Validar que no exista payment intent activo/pending para el mismo checkout.
- Crear payment intent con status normalizado inicial.
- Preparar datos para provider mock/dev.
- Devolver respuesta minima para que una capa superior exponga el siguiente paso de pago en microfase futura.

El servicio NO debe:

- Crear `profile_subscriptions`.
- Crear `subscription_payment_events`.
- Ejecutar provider real.
- Ejecutar webhook.
- Confirmar pago.
- Facturar.
- Activar capacidades.
- Activar suscripcion.
- Cambiar el endpoint checkout-intents.
- Limpiar datos locales/dev.

### F) Estados normalizados
Estados normalizados propuestos para `subscription_payment_intents.normalized_status`:

- `created`: fila interna creada, aun sin entrega efectiva a provider/mock.
- `pending_provider`: solicitud preparada o enviada a provider/mock, pendiente de respuesta util.
- `pending_payment`: provider/mock devolvio una intencion o checkout pendiente de pago.
- `failed`: intento fallido.
- `cancelled`: intento cancelado.
- `paid`: pago confirmado en flujo futuro; no debe activar suscripcion dentro de esta capa.

La transicion a `paid` no debe crear `profile_subscriptions` por si misma. La activacion post-pago requiere microfase separada y contrato propio.

### G) Idempotencia
Operacion futura sugerida:

```text
subscriptions.payment_intent.create
```

Reglas:

- Reusar `subscription_write_idempotency_keys` si la capa existente aplica a esta operacion.
- El `request_hash` debe incluir al menos `checkout_intent_uuid`, `provider`, `amount_cents` y `currency`.
- Un replay con misma key y mismo hash debe devolver resultado estable sin duplicar payment intent.
- Un replay con misma key y payload distinto debe bloquear con error conceptual de mismatch.
- Una request en proceso debe bloquear o devolver estado controlado segun el patron existente.
- El repositorio de payment intent no debe conocer idempotencia; esta vive en servicio/orquestacion.

Errores conceptuales esperados:

- `idempotency_key_invalid`.
- `idempotency_key_reused_with_different_payload`.
- `request_already_processing`.
- `payment_intent_create_failed`.
- `payment_intent_unavailable`.

### H) Anti-duplicado
Reglas anti-duplicado:

- No permitir mas de un payment intent activo/pending para el mismo `checkout_intent_uuid`.
- Si ya existe payment intent pendiente para el checkout, la capa superior debe responder conflict/existing segun contrato futuro.
- No duplicar por `provider_checkout_id`.
- No duplicar por `provider_payment_id`.
- No crear payment intent si el checkout intent ya no esta en `pending_payment`.
- No crear payment intent si el checkout intent esta eliminado.

Errores conceptuales esperados:

- `payment_intent_already_pending`.
- `payment_intent_provider_reference_conflict`.
- `checkout_intent_not_found`.
- `checkout_intent_not_pending_payment`.
- `payment_intent_invalid_checkout_snapshot`.

### I) Provider mock/dev
Antes de provider real se recomienda una microfase de provider mock/dev.

El provider mock/dev debe:

- Ser local/dev only.
- Estar bloqueado en produccion.
- Generar `provider_checkout_id` simulado si aplica.
- Generar `provider_payment_id` simulado si aplica.
- Generar `checkout_url` simulada si la capa futura lo necesita.
- Permitir QA controlada sin contactar proveedor real.
- No confirmar pago real.
- No emitir webhook real.
- No facturar.
- No activar suscripcion.

El provider real queda fuera de esta adenda y debe disenar sus propias reglas de credenciales, firma, errores, retries, timeouts y observabilidad.

### J) Webhooks y payment_events
`subscription_payment_events` permanece fuera de esta microfase.

Decision:

- `subscription_payment_events` sera ledger futuro para eventos provider/webhook.
- No se deben procesar eventos hasta tener adapter y validacion de firma.
- No se debe crear payment event dentro de `CreateSubscriptionPaymentIntentService`.
- No se debe usar `payment_events` para activar suscripcion sin microfase de post-payment activation.

Microfases futuras necesarias:

- Diseno de provider adapter.
- Diseno de webhook de provider.
- Diseno de validacion de firma/event hash.
- Diseno de ledger `subscription_payment_events`.
- Diseno de procesamiento post-pago.

### K) Activacion de suscripcion
Reglas:

- `profile_subscriptions` no se crea en payment intent create.
- `profile_subscriptions` no se crea por provider mock/dev.
- La activacion futura solo debe ocurrir tras pago confirmado.
- La activacion futura requiere microfase separada de post-payment activation.
- La capa de payment intent no debe conectar `PublicProfilePlanCapabilities`.
- La capa de payment intent no debe activar capacidades.

### L) Fuera de alcance
Esta adenda NO implementa:

- Repositorio PHP.
- Servicio PHP.
- Endpoint.
- Rutas.
- SQL.
- Migraciones.
- DB/schema.
- Provider mock/dev.
- Provider real.
- Payment events.
- Webhook.
- Facturacion.
- Activacion post-pago.
- `profile_subscriptions`.
- Capacidades.
- Conexion con `PublicProfilePlanCapabilities`.
- Perfil publico.
- SEO.
- Frontend.

### M) Siguiente microfase recomendada
Siguiente microfase recomendada:

```text
BE/SPEC-Suscripciones-PaymentIntent-Repository-Readiness-01
```

Objetivo:

- Validar readiness tecnica para implementar `SubscriptionPaymentIntentRepository`, usando el schema real de `subscription_payment_intents`, sin escribir codigo todavia, sin provider, sin payment events, sin endpoint, sin SQL y sin DB/schema.

---

## Adenda PP-Decisiones 101 - Readiness de repositorio payment intent

### A) Proposito
Esta adenda valida la readiness tecnica para implementar posteriormente:

```text
modules/subscriptions/repositories/SubscriptionPaymentIntentRepository.php
```

El repositorio sera la primera capa interna para consultar y persistir payment intents de suscripciones sobre la tabla:

```text
subscription_payment_intents
```

Esta adenda no implementa codigo, no modifica PHP, no ejecuta HTTP/POST, no hace SQL write, no modifica DB/schema y no crea filas.

### B) Estado observado
Estado confirmado en modo read-only:

- `subscription_payment_intents` existe.
- `subscription_payment_intents` esta vacia en el estado inspeccionado.
- `subscription_payment_events` existe y tambien esta vacia.
- Existe checkout pending fixture `7d4beec3-b62a-40e1-a9f2-9edcc1a83364`.
- El checkout fixture corresponde a `doctor/900001`, `standard/annual`, `pending_payment`, `20000 MXN`.
- `SubscriptionPaymentIntentRepository` aun no existe.
- No existe servicio payment intent.
- No existe provider adapter.
- No existe webhook de pagos.

### C) Schema disponible
Columnas disponibles en `subscription_payment_intents`:

- `id`.
- `uuid`.
- `checkout_intent_uuid`.
- `provider`.
- `provider_payment_id`.
- `provider_checkout_id`.
- `normalized_status`.
- `provider_status`.
- `amount_cents`.
- `currency`.
- `created_at_provider`.
- `expires_at`.
- `paid_at`.
- `failed_at`.
- `cancelled_at`.
- `source`.
- `notes`.
- `created_at`.
- `updated_at`.
- `deleted_at`.

Indices observados:

- Primary key por `id`.
- Unique por `uuid`.
- Unique por `provider`, `provider_payment_id`.
- Indice por `checkout_intent_uuid`.
- Indice por `normalized_status`.
- Indice por `provider`, `provider_status`.
- Indice por `created_at`.
- Indice por `deleted_at`.

Readiness de schema:

- El schema es suficiente para crear el repositorio inicial.
- El schema permite lookup por `uuid`.
- El schema permite lookup por `checkout_intent_uuid`.
- El schema permite deduplicacion por `provider_payment_id` dentro de `provider`.
- El schema permite filtrar soft delete con `deleted_at IS NULL`.
- No hay unique activo por `checkout_intent_uuid`; por tanto el anti-duplicado por checkout debe combinar lookup previo, idempotencia/lock en capa superior y contrato del servicio futuro.

### D) Patron de repositorios existente
Patrones existentes en `modules/subscriptions/repositories`:

- `declare(strict_types=1);`.
- Namespace `Subscriptions\Repositories`.
- Clases `final`.
- Constructor `__construct(PDO $pdo)`.
- Uso de `prepare`, `execute`, `fetch` y `fetchAll`.
- Uso de `PDO::FETCH_ASSOC`.
- Validaciones defensivas con `InvalidArgumentException` en repositorios de storage.
- Errores de storage/query con `RuntimeException` cuando aplica.
- Consultas operativas filtran `deleted_at IS NULL`.
- Inserciones usan payloads normalizados y despues pueden consultar por `uuid` para devolver fila creada.

### E) Archivo y clase futuros
Archivo futuro permitido:

```text
modules/subscriptions/repositories/SubscriptionPaymentIntentRepository.php
```

Clase futura:

```text
SubscriptionPaymentIntentRepository
```

Constructor esperado:

```text
__construct(PDO $pdo)
```

### F) Metodos minimos recomendados
Metodos minimos para la primera implementacion:

```text
findByUuid(string $uuid): ?array
findByCheckoutIntentUuid(string $checkoutIntentUuid): ?array
findActiveByCheckoutIntentUuid(string $checkoutIntentUuid): ?array
create(array $input): array
```

Responsabilidades:

- `findByUuid(...)`: buscar una fila no eliminada por `uuid`.
- `findByCheckoutIntentUuid(...)`: buscar el payment intent mas reciente asociado a un checkout.
- `findActiveByCheckoutIntentUuid(...)`: buscar un payment intent no eliminado con status operativo activo/pending para el checkout.
- `create(...)`: insertar payment intent y devolver fila normalizada, preferentemente releyendo por `uuid`.

El metodo `findByCheckoutIntentUuid(...)` debe ordenar de forma estable por `created_at DESC, id DESC`.

### G) Metodos opcionales
Metodos opcionales justificados por indices provider:

```text
findByProviderPaymentId(string $provider, string $providerPaymentId): ?array
findByProviderCheckoutId(string $provider, string $providerCheckoutId): ?array
```

Notas:

- `findByProviderPaymentId(...)` esta respaldado por unique `provider + provider_payment_id`.
- `findByProviderCheckoutId(...)` es util por contrato, aunque no hay unique directo observado para `provider_checkout_id`.
- `markFailed(...)` no debe implementarse en la primera microfase del repositorio salvo decision posterior.
- `markPaid(...)` no debe implementarse todavia.
- Cambios de estado quedan para microfases de provider/webhook/post-payment posteriores.

### H) Campos requeridos para create
Campos requeridos del payload para `create(array $input): array`:

- `uuid`.
- `checkout_intent_uuid`.
- `provider`.
- `provider_payment_id`.
- `normalized_status`.
- `amount_cents`.
- `currency`.
- `source`.

Campos opcionales segun schema:

- `provider_checkout_id`.
- `provider_status`.
- `created_at_provider`.
- `expires_at`.
- `paid_at`.
- `failed_at`.
- `cancelled_at`.
- `notes`.

Reglas:

- `uuid` debe ser requerido y no vacio.
- `checkout_intent_uuid` debe ser requerido y no vacio.
- `provider` debe ser requerido y no vacio.
- `provider_payment_id` debe ser requerido por schema actual.
- `amount_cents` debe ser requerido y entero no negativo.
- `currency` debe ser requerido, normalizado a mayusculas y compatible con el snapshot del checkout.
- `normalized_status` debe limitarse a estados iniciales permitidos.
- `deleted_at` debe insertarse como `NULL` o dejarse en su default si el schema lo permite.

### I) Estados iniciales
Estados iniciales permitidos para `create(...)`:

- `created`.
- `pending_provider`.

No se debe usar `paid` en la creacion inicial.
No se debe activar suscripcion desde este repositorio.
No se debe asumir confirmacion de pago por crear payment intent.

### J) Anti-duplicado
Reglas de anti-duplicado:

- No debe existir mas de un payment intent activo/pending por `checkout_intent_uuid`.
- Como no hay unique por `checkout_intent_uuid`, el repositorio debe exponer lookup previo y el servicio futuro debe controlar idempotencia y lock.
- La capa superior futura debe usar la operacion idempotente `subscriptions.payment_intent.create`.
- `provider + provider_payment_id` tiene unique y debe respetarse.
- `provider_checkout_id` debe consultarse si el provider mock/dev o provider real lo entrega.
- `subscription_payment_events` no debe usarse para anti-duplicado de creacion del payment intent.

Estados considerados activos/pending para lookup inicial:

- `created`.
- `pending_provider`.
- `pending_payment`.

### K) Soft delete
Reglas:

- Todos los lookups operativos deben filtrar `deleted_at IS NULL`.
- `findActiveByCheckoutIntentUuid(...)` debe excluir filas eliminadas.
- `findByUuid(...)` debe excluir filas eliminadas salvo que una microfase futura defina lookup administrativo.
- `create(...)` no debe crear filas con `deleted_at` distinto de `NULL`.

### L) Normalizacion de salida
Los metodos deben devolver arrays con keys estables:

- `id`.
- `uuid`.
- `checkout_intent_uuid`.
- `provider`.
- `provider_payment_id`.
- `provider_checkout_id`.
- `normalized_status`.
- `provider_status`.
- `amount_cents`.
- `currency`.
- `created_at_provider`.
- `expires_at`.
- `paid_at`.
- `failed_at`.
- `cancelled_at`.
- `source`.
- `notes`.
- `created_at`.
- `updated_at`.
- `deleted_at`.

`amount_cents` debe normalizarse a entero. Los timestamps y strings deben conservarse como strings/null.

### M) No responsabilidades
El repositorio `SubscriptionPaymentIntentRepository` NO debe:

- Contactar provider real.
- Implementar provider mock/dev.
- Ejecutar webhook.
- Crear `subscription_payment_events`.
- Crear `profile_subscriptions`.
- Activar suscripcion.
- Activar capacidades.
- Facturar.
- Resolver precio.
- Validar contrato.
- Manejar auth/session.
- Manejar idempotencia directamente.
- Manejar lock directamente.
- Modificar `subscription_checkout_intents`.
- Implementar endpoint o rutas.
- Ejecutar SQL DDL.
- Modificar DB/schema.

### N) Riesgos y advertencias
Riesgos no bloqueantes para implementar el repositorio:

- No hay unique por `checkout_intent_uuid`; se requiere lookup previo mas idempotencia/lock en capa superior.
- `provider_payment_id` es `NOT NULL`; el provider mock/dev futuro debera poder generar un id simulado antes de crear la fila.
- `provider_checkout_id` es nullable y no tiene unique observado; se recomienda lookup defensivo si se usa.
- `paid`, `failed` y `cancelled` existen como campos temporales, pero sus transiciones quedan fuera de la primera implementacion.

No hay brecha bloqueante para implementar el repositorio aislado si se limita a create/lookups y no conecta provider ni activacion.

### O) QA futura de implementacion
QA minima para la microfase de implementacion posterior:

- `php -l modules/subscriptions/repositories/SubscriptionPaymentIntentRepository.php`.
- Grep de clase `SubscriptionPaymentIntentRepository`.
- Grep de metodos `create`, `findByUuid`, `findByCheckoutIntentUuid`, `findActiveByCheckoutIntentUuid`.
- Grep de `subscription_payment_intents`.
- Grep de `provider_payment_id`.
- Grep de `deleted_at IS NULL`.
- Grep de prohibidos:
  - `profile_subscriptions`.
  - `subscription_payment_events`.
  - `provider real`.
  - `webhook`.
  - `PublicProfilePlanCapabilities`.
  - `api/subscriptions`.
- Confirmar que no modifica endpoint.
- Confirmar que no crea SQL.
- Confirmar que no ejecuta SQL write.
- Confirmar que no modifica DB/schema.

### P) Siguiente microfase recomendada
Siguiente microfase recomendada:

```text
BE/Suscripciones-PaymentIntent-Repository-01
```

Objetivo:

- Implementar `SubscriptionPaymentIntentRepository` con los metodos minimos `findByUuid`, `findByCheckoutIntentUuid`, `findActiveByCheckoutIntentUuid` y `create`, sin endpoint, sin provider, sin payment events, sin profile_subscriptions, sin SQL y sin DB/schema.

---

## Adenda PP-Decisiones 102 - Readiness de servicio de creación de payment intent

### A) Proposito
Esta adenda valida la readiness tecnica para implementar posteriormente el servicio:

```text
modules/subscriptions/services/CreateSubscriptionPaymentIntentService.php
```

con metodo conceptual:

```text
createPaymentIntent(array $input): array
```

El servicio futuro debera crear el primer `subscription_payment_intents` asociado a un `checkout_intent_uuid` ya existente y en `pending_payment`, sin endpoint, sin provider real, sin `payment_events`, sin `profile_subscriptions` y sin activacion post-pago.

Esta adenda no implementa codigo, no modifica PHP, no ejecuta HTTP/POST, no hace SQL write, no modifica DB/schema y no crea filas.

### B) Estado base observado
Estado confirmado en modo read-only:

- Existe `SubscriptionPaymentIntentRepository`.
- Existe `SubscriptionCheckoutIntentRepository`.
- Existe `CreateSubscriptionCheckoutIntentService`.
- Existe `CreateSubscriptionPendingPaymentAcceptanceService`.
- Existe `SubscriptionPlanPriceResolverService`.
- Existe `SubscriptionWriteIdempotencyService`.
- Existe `SubscriptionEntityWriteLockService`.
- Existe schema local/dev de `subscription_payment_intents`.
- Existe checkout fixture `7d4beec3-b62a-40e1-a9f2-9edcc1a83364` para `doctor/900001`, `standard/annual`, `pending_payment`, `20000 MXN`.
- `subscription_payment_intents` no tiene filas en el estado inspeccionado.
- `subscription_payment_events` no tiene filas en el estado inspeccionado.
- No existe todavia `CreateSubscriptionPaymentIntentService`.
- No existe provider mock/dev productivo para crear ids provider.
- No existe provider real integrado.
- No existe webhook de pagos.

### C) Archivo y clase futuros
Archivo futuro recomendado:

```text
modules/subscriptions/services/CreateSubscriptionPaymentIntentService.php
```

Clase futura:

```text
CreateSubscriptionPaymentIntentService
```

Metodo futuro:

```text
createPaymentIntent(array $input): array
```

Responsabilidad:

- Orquestar la creacion minima de un payment intent interno para un checkout intent existente.
- Validar que el checkout exista y siga en `pending_payment`.
- Validar que el snapshot de monto/moneda coincida con el checkout.
- Aplicar idempotencia futura `subscriptions.payment_intent.create`.
- Aplicar lock futuro por `checkout_intent_uuid`.
- Evitar duplicado activo por checkout.
- Persistir `subscription_payment_intents` con status inicial permitido.
- Devolver respuesta minima estable.

### D) Dependencias esperadas
Dependencias futuras esperadas:

- `PDO` o conexion compartida.
- `SubscriptionCheckoutIntentRepository`.
- `SubscriptionPaymentIntentRepository`.
- `SubscriptionWriteIdempotencyService`, con extension para `subscriptions.payment_intent.create`.
- `SubscriptionEntityWriteLockService` o helper equivalente extendido para lock por `checkout_intent_uuid`.
- Reloj/fecha UTC si el servicio genera timestamps provider simulados.
- Generador UUID local si el servicio genera `uuid` de payment intent antes del repository.
- Provider mock/dev o input controlado de provider en una microfase separada.

No debe depender de:

- Endpoint/routing.
- Provider real.
- Webhook.
- Facturacion.
- Activacion de capacidades.
- `PublicProfilePlanCapabilities`.

### E) Input minimo esperado
Input conceptual minimo para `createPaymentIntent(array $input): array`:

- `checkout_intent_uuid`.
- `provider`.
- `provider_payment_id`.
- `provider_checkout_id` si aplica.
- `amount_cents`.
- `currency`.
- `idempotency_key`.
- `source`.
- `request_context` minimo si aplica.

Reglas:

- `amount_cents` y `currency` deben coincidir con el snapshot del checkout.
- `checkout_intent_uuid` debe referir un checkout no eliminado.
- El checkout debe estar en `pending_payment`.
- El cliente no debe enviar precio canonico para decidir monto.
- El servicio no debe resolver precio; el precio ya vive en el checkout intent.
- El provider real queda fuera de esta capa.

### F) Output minimo esperado
Respuesta conceptual minima:

- `payment_intent_uuid`.
- `checkout_intent_uuid`.
- `provider`.
- `provider_payment_id`.
- `provider_checkout_id` si aplica.
- `normalized_status`.
- `provider_status` si aplica.
- `amount_cents`.
- `currency`.
- `source`.
- `created_at`.
- `idempotency_replay` si aplica.

Estados iniciales permitidos:

- `created`.
- `pending_provider`.

No debe devolver ni implicar:

- Pago confirmado.
- Suscripcion activa.
- `profile_subscriptions`.
- `payment_events`.
- post-payment activation.

### G) Validaciones minimas
Validaciones futuras del servicio:

- `checkout_intent_uuid` requerido.
- Checkout existente por `SubscriptionCheckoutIntentRepository::findByUuid(...)`.
- Checkout no eliminado.
- Checkout en `pending_payment`.
- No existe payment intent activo por `checkout_intent_uuid`.
- `provider` requerido y permitido para DEV/local.
- `provider_payment_id` requerido por schema actual.
- `amount_cents` requerido y coincidente con checkout.
- `currency` requerida y coincidente con checkout.
- `source` requerido y controlado.
- `idempotency_key` requerido si la capa futura decide idempotencia obligatoria.

Errores conceptuales de validacion:

- `invalid_payment_intent_payload`.
- `checkout_intent_not_found`.
- `checkout_intent_not_pending_payment`.
- `payment_intent_already_exists`.
- `payment_intent_amount_mismatch`.
- `payment_intent_currency_mismatch`.
- `payment_provider_invalid`.

### H) Idempotencia
Operacion futura requerida:

```text
subscriptions.payment_intent.create
```

Readiness observada:

- `SubscriptionWriteIdempotencyService` tiene patron generico `beginOperation(...)`.
- El servicio actual conserva replay estable y bloqueo de payload distinto.
- El servicio actual guarda respuesta completa para checkout-intents mediante `markCheckoutIntentCompleted(...)`.
- La allowlist actual de operaciones no incluye `subscriptions.payment_intent.create`.

Clasificacion:

- Patron disponible: listo para reutilizar.
- Operacion `subscriptions.payment_intent.create`: requiere ajuste previo o extension minima.
- Hash canonico payment intent: requiere definir payload canonicalizado con `checkout_intent_uuid`, `provider`, `provider_payment_id`, `provider_checkout_id`, `amount_cents`, `currency` y `source`.
- Replay estable del resultado payment intent: requiere soporte especifico o uso seguro de `markOperationCompleted(...)` extendido.

La idempotencia debe:

- Rechazar `idempotency_key_reused_with_different_payload`.
- Rechazar `request_already_processing`.
- Reproducir respuesta estable si la operacion ya fue completada.
- No crear segundo payment intent para la misma llave.

### I) Lock
Lock futuro recomendado:

```text
mxmed:subscriptions:checkout_intents:{checkout_intent_uuid}:payment_intent_create
```

Readiness observada:

- `SubscriptionEntityWriteLockService` ya implementa patron de `GET_LOCK`/`RELEASE_LOCK`.
- El lock actual permite `create` y `checkout_create` por entidad.
- La allowlist actual no contempla `payment_intent_create`.
- El scope actual esta orientado a `entity_type/entity_id`, no a `checkout_intent_uuid`.

Clasificacion:

- Patron tecnico de lock: listo.
- Lock especifico por `checkout_intent_uuid`: requiere ajuste previo o helper minimo.
- Timeout y liberacion: patron listo para reutilizar.

El servicio futuro no debe crear payment intent sin lock si se habilita ejecucion concurrente.

### J) Transaccion
Decision de readiness:

- `CreateSubscriptionCheckoutIntentService` ya muestra patron de transaccion superior con `PDO::beginTransaction()`, `commit()` y `rollBack()`.
- `SubscriptionPaymentIntentRepository` no abre transaccion propia.
- `SubscriptionCheckoutIntentRepository` no abre transaccion propia.
- El servicio futuro `CreateSubscriptionPaymentIntentService` debe abrir la transaccion superior para el write del payment intent y el cierre idempotente asociado, o coordinarla con la capa que controle idempotencia.

La transaccion futura debe garantizar:

- Si falla el insert de `subscription_payment_intents`, no se guarda resultado idempotente exitoso.
- Si falla la persistencia idempotente completada despues del insert, debe quedar documentado el riesgo y la estrategia de recuperacion antes de endpoint productivo.
- No se crean `subscription_payment_events`.
- No se crean `profile_subscriptions`.
- No se activa suscripcion.

### K) Provider
Decision de readiness:

- Esta capa no debe conectar provider real.
- Provider mock/dev queda pendiente como dependencia para generar `provider_payment_id` y, si aplica, `provider_checkout_id`.
- Mientras no exista provider mock/dev, una implementacion aislada del servicio podria recibir datos provider controlados desde input de prueba, pero no debe exponerse a endpoint productivo.
- Provider real, webhooks, `payment_events`, facturacion y post-payment activation quedan fuera de alcance.

### L) No responsabilidades
`CreateSubscriptionPaymentIntentService` NO debe:

- Crear endpoint o rutas.
- Contactar provider real.
- Implementar provider mock/dev dentro de la primera version si no esta explicitamente autorizado.
- Crear `subscription_payment_events`.
- Crear `profile_subscriptions`.
- Activar suscripcion.
- Ejecutar post-payment activation.
- Activar capacidades.
- Conectar `PublicProfilePlanCapabilities`.
- Facturar.
- Resolver precio.
- Validar contrato.
- Crear checkout intent.
- Crear aceptacion contractual.
- Ejecutar SQL DDL.
- Modificar DB/schema.
- Limpiar fixtures.

### M) Brechas clasificadas
Clasificacion de readiness:

- `SubscriptionPaymentIntentRepository`: listo para uso por el servicio.
- `SubscriptionCheckoutIntentRepository`: listo para lookup de checkout por uuid.
- Schema `subscription_payment_intents`: listo para crear payment intent inicial.
- Transaccion con PDO compartido: lista como patron.
- Idempotencia `subscriptions.payment_intent.create`: requiere ajuste previo.
- Lock `payment_intent_create` por `checkout_intent_uuid`: requiere ajuste previo.
- Provider mock/dev: pendiente, no bloqueante para disenar el servicio si el input controlado entrega ids provider; bloqueante antes de endpoint productivo.
- Provider real: fuera de alcance y no listo.

Riesgos bloqueantes antes de endpoint productivo:

- Falta habilitar operacion idempotente `subscriptions.payment_intent.create`.
- Falta lock especifico para `payment_intent_create`.
- Falta provider mock/dev o contrato controlado para generar ids provider.

No hay brecha bloqueante para documentar readiness. La implementacion aislada del servicio debe decidir si primero extiende idempotencia/lock o queda limitada a una version no conectada a endpoint.

### N) Errores conceptuales
Errores que debe manejar el servicio futuro:

- `invalid_payment_intent_payload`.
- `checkout_intent_not_found`.
- `checkout_intent_not_pending_payment`.
- `payment_intent_already_exists`.
- `payment_intent_amount_mismatch`.
- `payment_intent_currency_mismatch`.
- `payment_provider_invalid`.
- `payment_intent_create_failed`.
- `payment_intent_lookup_failed`.
- `payment_intent_transaction_failed`.
- `idempotency_key_invalid`.
- `idempotency_key_reused_with_different_payload`.
- `request_already_processing`.
- `payment_intent_lock_timeout`.
- `payment_intent_unavailable`.

Errores fuera de esta capa:

- `contract_invalid`.
- `checkout_already_pending`.
- `active_subscription_exists`.
- `plan_not_contractable`.
- `billing_period_invalid`.
- `plan_price_not_configured`.
- `pricing_configuration_conflict`.
- `pricing_source_unavailable`.

### O) QA futura
QA minima para la implementacion posterior:

- `php -l modules/subscriptions/services/CreateSubscriptionPaymentIntentService.php`.
- Grep de clase `CreateSubscriptionPaymentIntentService`.
- Grep de metodo `createPaymentIntent`.
- Grep de `subscriptions.payment_intent.create`.
- Grep de `checkout_intent_uuid`.
- Grep de `pending_payment`.
- Grep de `SubscriptionPaymentIntentRepository`.
- Grep de `SubscriptionCheckoutIntentRepository`.
- Grep de prohibidos:
  - `profile_subscriptions`.
  - `subscription_payment_events`.
  - `subscription_payment_intents` fuera del repository permitido.
  - `provider real`.
  - `webhook`.
  - `PublicProfilePlanCapabilities`.
  - `post-payment activation`.
- Confirmar que no modifica endpoint.
- Confirmar que no crea SQL.
- Confirmar que no ejecuta SQL write.
- Confirmar que no modifica DB/schema.
- Prueba aislada con stubs/mocks solo si una microfase posterior la autoriza.

### P) Siguiente microfase recomendada
Siguiente microfase inmediata:

```text
BE/SPEC-Suscripciones-PaymentIntent-ServiceDependencies-Plan-01
```

Motivo:

- Planificar los ajustes minimos de idempotencia `subscriptions.payment_intent.create`, lock `payment_intent_create` por `checkout_intent_uuid` y contrato provider mock/dev necesarios antes de implementar o conectar `CreateSubscriptionPaymentIntentService`.

Microfase posterior, no inmediata:

```text
BE/Suscripciones-PaymentIntent-Service-01
```

Objetivo posterior:

- Implementar `CreateSubscriptionPaymentIntentService::createPaymentIntent(array $input): array` cuando esten cerradas las dependencias de idempotencia, lock por `checkout_intent_uuid`, anti-duplicado superior y provider mock/dev.

---

## Adenda PP-Decisiones 103 - Plan de dependencias del servicio payment intent

### A) Proposito
Esta adenda planifica las dependencias minimas antes de implementar el servicio futuro:

```text
modules/subscriptions/services/CreateSubscriptionPaymentIntentService.php
```

Metodo conceptual futuro:

```php
createPaymentIntent(array $input): array
```

El objetivo es convertir la readiness de PP-Decisiones 102 en un plan tecnico previo, sin escribir codigo todavia.

Esta adenda no reabre:

- Checkout-first.
- Primer write de checkout-intents.
- `accepted_pending_payment`.
- `pending_payment`.
- Resolver server-side de precios.
- Repository `SubscriptionPaymentIntentRepository`.
- Provider real.
- Webhooks.
- Activacion post-payment.

### B) Estado base confirmado
Ya existen componentes base para el flujo futuro:

- `SubscriptionPaymentIntentRepository`.
- `SubscriptionCheckoutIntentRepository`.
- `CreateSubscriptionCheckoutIntentService`.
- `CreateSubscriptionPendingPaymentAcceptanceService`.
- `SubscriptionWriteIdempotencyService`.
- `SubscriptionEntityWriteLockService`.
- Schema local/dev de `subscription_payment_intents`.
- Schema local/dev de `subscription_checkout_intents`.
- Fixture checkout `doctor/900001` con checkout intent `pending_payment`.

Todavia no existe:

- `CreateSubscriptionPaymentIntentService`.
- Idempotencia especifica para `subscriptions.payment_intent.create`.
- Lock especifico por `checkout_intent_uuid`.
- Provider mock/dev con contrato cerrado para payment intent.
- Endpoint productivo para crear payment intents.
- `payment_events`.
- Webhooks.
- Activacion post-payment.
- Creacion de `profile_subscriptions` desde payment intent.

### C) Dependencia de idempotencia
El servicio futuro debe usar una operacion propia:

```text
subscriptions.payment_intent.create
```

El `Idempotency-Key` debe ser obligatorio para el write de payment intent.

El `request_hash` debe cubrir, como minimo:

- `checkout_intent_uuid`.
- `provider`.
- `provider_payment_id`.
- `provider_checkout_id`, si aplica.
- `amount_cents`.
- `currency`.
- `source`.

Reglas esperadas:

- Misma key + mismo payload: replay estable sin duplicar `subscription_payment_intents`.
- Misma key + payload distinto: error `idempotency_key_reused_with_different_payload`.
- Request en proceso: error `request_already_processing`.
- Payload invalido: no crear payment intent ni guardar resultado exitoso.

Readiness observada:

- La tabla `subscription_write_idempotency_keys` permite `operation`, `request_hash`, `response_http_status` y `response_body_text`.
- La infraestructura actual ya resuelve replay para checkout-intents.
- Requiere ajuste previo para permitir y probar la operacion `subscriptions.payment_intent.create`, su hash y su resultado estable.

Clasificacion:

- Reutilizable: infraestructura de storage de idempotencia.
- Requiere ajuste previo: allowlist/entrada especifica de operacion, builder de hash y helpers de completion/replay para payment intent.
- Bloqueante antes del servicio: si la operacion `subscriptions.payment_intent.create` no queda soportada.

### D) Dependencia de lock
El servicio futuro debe correr bajo lock por checkout intent:

```text
mxmed:subscriptions:checkout_intents:{checkout_intent_uuid}:payment_intent_create
```

Proposito del lock:

- Evitar carreras que creen dos payment intents activos para el mismo checkout.
- Coordinar requests con keys distintas sobre el mismo `checkout_intent_uuid`.
- Proteger el lookup anti-duplicado y el insert.

Timeout sugerido:

- Usar una ventana corta equivalente al checkout intent create, por ejemplo 2 segundos, salvo que una microfase posterior ajuste ese valor.

Readiness observada:

- Existe patron de lock para `checkout_create`.
- El lock actual esta orientado a entidad `entity_type/entity_id`.
- El caso payment intent requiere helper o extension por `checkout_intent_uuid`.

Clasificacion:

- Reutilizable: patron de lock y errores de timeout.
- Requiere ajuste previo: scope/nombre especifico `payment_intent_create`.
- Bloqueante antes del servicio: si no existe lock por `checkout_intent_uuid`.

### E) Dependencia de anti-duplicado
El control superior debe apoyarse en:

```php
SubscriptionPaymentIntentRepository::findActiveByCheckoutIntentUuid(...)
```

Reglas esperadas:

- Si ya existe payment intent activo para el checkout, el servicio debe bloquear la creacion.
- La decision de error pertenece al servicio, no al repository.
- El repository solo consulta e inserta storage.
- No cerrar, sobrescribir ni cancelar payment intents desde esta fase.

Controles complementarios:

- Idempotencia por `Idempotency-Key`.
- Lock por `checkout_intent_uuid`.
- Lookup `findActiveByCheckoutIntentUuid`.
- Unicidad provider/provider_payment_id en schema.

Observacion de schema:

- `subscription_payment_intents` tiene indice por `checkout_intent_uuid`, pero no se observo unicidad por checkout.
- Por eso el anti-duplicado superior es obligatorio antes del insert.

### F) Dependencia provider mock/dev
El provider real queda fuera de alcance.

Para poder crear `subscription_payment_intents`, el servicio futuro necesita un contrato minimo mock/dev porque `provider_payment_id` es obligatorio.

Contrato mock/dev sugerido:

- `provider = mxmed_mock`.
- `provider_payment_id` generado local/dev o recibido desde adapter mock controlado.
- `provider_checkout_id` opcional.
- `provider_status` inicial: `created` o equivalente documentado.
- `normalized_status` inicial: `created` o `pending_provider`, segun repository.
- Sin llamadas externas.
- Sin `checkout_url` real salvo que una microfase futura defina un valor mock.

Clasificacion:

- Requiere plan/contrato previo: provider mock/dev.
- Bloqueante antes de QA funcional de payment intent: no debe inventarse provider real ni depender de pasarela externa.

### G) Input futuro minimo
El servicio futuro debe recibir, como minimo:

- `checkout_intent_uuid`.
- `provider`.
- `provider_payment_id`, si el adapter mock/dev ya lo genero.
- `provider_checkout_id`, opcional.
- `idempotency_key`.
- `source`.
- Contexto actor/session si aplica.
- Metadata o notes solo si el schema/repository lo permite.

El servicio no debe recibir como fuente canonica desde cliente:

- Precio final si puede resolverse desde el checkout intent existente.
- Estado arbitrario.
- `profile_subscription_id`.
- Datos de provider real.
- Resultado webhook.

El monto y moneda deben validarse contra el checkout intent almacenado:

- `amount_cents`.
- `currency`.
- `plan_code`.
- `billing_period`.

### H) Validaciones futuras
El servicio futuro debe validar:

- `checkout_intent_uuid` presente y valido.
- Checkout intent existe y no esta eliminado.
- Checkout intent esta en `pending_payment`.
- Checkout intent no tiene payment intent activo asociado.
- Monto y moneda del payment intent coinciden con el checkout intent.
- Provider permitido para esta fase: `mxmed_mock` o allowlist futura equivalente.
- `provider_payment_id` presente antes del insert.
- `Idempotency-Key` presente.
- `source` permitido.
- No existe suscripcion activa si el flujo superior lo exige antes del pago.

Errores conceptuales esperados:

- `invalid_payment_intent_payload`.
- `checkout_intent_not_found`.
- `checkout_intent_not_payable`.
- `payment_intent_already_exists`.
- `idempotency_key_invalid`.
- `idempotency_key_reused_with_different_payload`.
- `request_already_processing`.
- `payment_intent_lock_timeout`.
- `provider_mock_not_configured`.
- `payment_intent_create_failed`.

### I) Transaccion futura
El servicio futuro debe abrir una transaccion para el tramo de storage de payment intent.

La unidad atomica minima debe cubrir:

1. Validacion final de checkout intent.
2. Lookup anti-duplicado bajo lock.
3. Insert en `subscription_payment_intents`.
4. Persistencia del resultado idempotente.

No debe incluir en esta etapa:

- `payment_events`.
- `profile_subscriptions`.
- Activacion post-payment.
- Facturacion.
- Webhooks.

Si falla el insert:

- Hacer rollback si la transaccion esta abierta.
- Liberar lock.
- Marcar/limpiar idempotencia segun patron definido.
- No crear eventos, suscripciones ni activacion compensatoria.

### J) Orden recomendado de microfases
Orden seguro antes de implementar o conectar el servicio:

1. `BE/SPEC-Suscripciones-PaymentIntent-Idempotency-Readiness-01`
   - Validar y cerrar el soporte para `subscriptions.payment_intent.create`.
2. `BE/SPEC-Suscripciones-PaymentIntent-Lock-Readiness-01`
   - Validar y cerrar lock por `checkout_intent_uuid` con `payment_intent_create`.
3. `BE/SPEC-Suscripciones-PaymentIntent-ProviderMock-Plan-01`
   - Definir contrato mock/dev de provider y origen de `provider_payment_id`.
4. `BE/Suscripciones-PaymentIntent-Service-01`
   - Implementar `CreateSubscriptionPaymentIntentService` cuando las dependencias esten listas.

Siguiente microfase inmediata recomendada:

```text
BE/SPEC-Suscripciones-PaymentIntent-Idempotency-Readiness-01
```

### K) Responsabilidades que NO pertenecen al servicio futuro
`CreateSubscriptionPaymentIntentService` no debe:

- Crear `payment_events`.
- Ejecutar webhook.
- Crear `profile_subscriptions`.
- Activar suscripcion.
- Ejecutar post-payment activation.
- Facturar.
- Conectar provider real.
- Resolver SEO/perfil publico.
- Activar capacidades.
- Crear checkout intent.
- Crear aceptacion contractual.
- Modificar endpoint en esta fase.
- Hacer SQL write manual fuera del repository permitido.

### L) Fuera de alcance de esta adenda
Esta adenda NO implementa:

- Servicio payment intent.
- Endpoint.
- Rutas.
- Provider real.
- Provider mock/dev.
- SQL.
- Migraciones.
- DB/schema.
- HTTP/POST.
- SQL write.
- `payment_events`.
- Webhooks.
- `profile_subscriptions`.
- Activacion post-payment.
- Facturacion.
- Capacidades.
- `PublicProfilePlanCapabilities`.
- Perfil publico.
- SEO.
- Frontend.

### M) Decision de etapa
La siguiente etapa no debe implementar directamente el servicio si no se cierran antes las dependencias bloqueantes.

Decision:

- Idempotencia `subscriptions.payment_intent.create`: bloqueante.
- Lock `payment_intent_create` por `checkout_intent_uuid`: bloqueante.
- Provider mock/dev para `provider_payment_id`: bloqueante para QA funcional.
- Anti-duplicado con `findActiveByCheckoutIntentUuid`: listo como base, pero debe integrarse en servicio bajo lock.

Por lo tanto, la siguiente microfase recomendada es:

```text
BE/SPEC-Suscripciones-PaymentIntent-Idempotency-Readiness-01
```

Objetivo:

- Validar readiness tecnica para extender/reutilizar idempotencia con `subscriptions.payment_intent.create`, replay estable y bloqueo de payload distinto, sin escribir codigo todavia.

---

## Adenda PP-Decisiones 104 - Readiness de idempotencia para payment intent

### A) Motivo
PP-Decisiones 103 dejo la idempotencia de payment intent como dependencia bloqueante antes de implementar:

```text
modules/subscriptions/services/CreateSubscriptionPaymentIntentService.php
```

Esta adenda valida si la infraestructura actual de idempotencia puede soportar la operacion futura:

```text
subscriptions.payment_intent.create
```

No se debe crear `CreateSubscriptionPaymentIntentService.php` hasta cerrar este contrato minimo de idempotencia.

Esta adenda no implementa codigo y no reabre:

- Checkout-first.
- Primer write checkout-intents.
- Repository `SubscriptionPaymentIntentRepository`.
- Provider mock/dev.
- Provider real.
- Webhooks.
- `payment_events`.
- `profile_subscriptions`.
- Activacion post-payment.

### B) Infraestructura existente observada
La tabla `subscription_write_idempotency_keys` ya contiene campos suficientes para idempotencia general de writes:

- `idempotency_key_hash`.
- `request_hash`.
- `entity_type`.
- `entity_id`.
- `user_id`.
- `operation`.
- `status`.
- `response_http_status`.
- `response_body_text`.
- `locked_at`.
- `completed_at`.
- `expires_at`.
- `deleted_at`.

Indices observados:

- Unique por `uuid`.
- Unique por `idempotency_key_hash`, `user_id`, `entity_type`, `entity_id`, `operation`.
- Indice por `operation`, `status`.
- Indice por `status`, `expires_at`.
- Indices por entidad, doctor, usuario y `deleted_at`.

Operaciones existentes en DB local/dev:

- `subscriptions.checkout_intent.create`.
- `subscriptions.create_with_contract_acceptance`.

La infraestructura de storage puede reutilizarse porque la columna `operation` separa scopes y evita colisiones entre operaciones distintas.

Brecha observada:

- `SubscriptionWriteIdempotencyService::isAllowedOperation(...)` aun no permite `subscriptions.payment_intent.create`.
- `requestHashForOperation(...)` no tiene builder especifico para payment intent.
- `markOperationCompleted(...)` no tiene rama especifica para response de payment intent.

Clasificacion:

- Schema: listo para reutilizar.
- Repository: listo como base.
- Service: requiere ajuste previo minimo para operacion, hash y completion de payment intent.
- Bloqueante antes de `CreateSubscriptionPaymentIntentService`: si no se habilita `subscriptions.payment_intent.create`.

### C) Operacion futura
Operacion futura:

```text
subscriptions.payment_intent.create
```

Debe ser distinta de:

```text
subscriptions.checkout_intent.create
```

Reglas:

- No reutilizar la operacion de checkout para payment intent.
- No mezclar resultados de checkout intent con resultados de payment intent.
- El unique scope de `subscription_write_idempotency_keys` debe quedar separado por `operation`.
- La respuesta persistida debe describir el payment intent creado, no el checkout intent.

### D) Idempotency-Key
El `Idempotency-Key` debe ser obligatorio para el futuro write de payment intent.

Reglas minimas:

- Se valida antes de crear `subscription_payment_intents`.
- Missing key debe responder `idempotency_key_invalid` o error equivalente.
- Key con formato invalido debe responder `idempotency_key_invalid`.
- Payload invalido no debe crear payment intent funcional.
- Payload invalido no debe crear `payment_events`, `profile_subscriptions` ni activacion.

Decision documental:

- Si falta o es invalido el `Idempotency-Key`, no se debe abrir el flujo de storage de payment intent.
- Para payload invalido despues de iniciar idempotencia, se debe seguir el patron de checkout: marcar failure controlado si ya existe record `processing`, sin crear payment intent.
- Si el payload falla antes de calcular scope/hash canonico, no debe crear fila idempotente.

### E) `request_hash` futuro
El `request_hash` de `subscriptions.payment_intent.create` debe construirse de forma canonica y estable.

Campos minimos:

- `checkout_intent_uuid`.
- `provider`.
- `provider_payment_id`.
- `provider_checkout_id`, si aplica.
- `amount_cents`.
- `currency`.
- `source`, si aplica.

Reglas de canonicalizacion:

- Usar orden estable de keys antes de serializar.
- Normalizar strings con `trim`.
- Normalizar `provider` en lowercase si el contrato provider mock/dev lo define asi.
- Normalizar `currency` en uppercase.
- Convertir `amount_cents` a entero.
- Representar ausencias opcionales como `null` o string vacio de forma consistente.
- Incluir el literal de operacion `subscriptions.payment_intent.create`.

Monto y moneda:

- El cliente no debe ser fuente canonica de precio.
- `amount_cents` y `currency` deben validarse contra el checkout intent almacenado antes de completar el write.
- Si el servicio futuro permite recibir monto/moneda desde un adapter mock/dev, debe compararlos contra el snapshot del checkout intent.

### F) Replay mismo request
Para misma `Idempotency-Key` y mismo `request_hash`:

- Debe devolver el mismo payment intent.
- No debe insertar una segunda fila en `subscription_payment_intents`.
- Debe devolver metadata `idempotent_replay` o equivalente.
- Debe preservar el HTTP status original cuando exista `response_http_status`.
- Debe usar `response_body_text` como fuente de replay estable cuando este disponible.

Respuesta persistida minima esperada:

- `payment_intent_uuid`.
- `checkout_intent_uuid`.
- `provider`.
- `provider_payment_id`.
- `provider_checkout_id`, si aplica.
- `normalized_status`.
- `amount_cents`.
- `currency`.
- `source`.

Readiness observada:

- El replay de checkout ya usa `response_body_text`.
- El mismo patron es reutilizable para payment intent si se agrega completion especifico o generico compatible.

### G) Conflict misma key con payload distinto
Para misma `Idempotency-Key` y `request_hash` distinto:

- Debe bloquear con `idempotency_key_reused_with_different_payload`.
- El status recomendado es `409`.
- No debe crear un nuevo payment intent.
- No debe crear `payment_events`.
- No debe crear `profile_subscriptions`.
- No debe llamar provider.

Para status `processing` existente:

- Debe bloquear con `request_already_processing`.
- No debe reintentar insert paralelo.

### H) Relacion con anti-duplicado
La idempotencia no sustituye el anti-duplicado de negocio.

Antes de crear un payment intent nuevo, el servicio futuro debe consultar:

```php
SubscriptionPaymentIntentRepository::findActiveByCheckoutIntentUuid(...)
```

Reglas:

- Si ya existe payment intent activo para el checkout, el servicio debe bloquear o devolver el existente segun contrato posterior.
- La idempotencia protege retries de la misma intencion.
- El lookup anti-duplicado protege requests con keys distintas sobre el mismo checkout.
- Como no hay unique por `checkout_intent_uuid`, el control completo debe ser:
  - lookup anti-duplicado;
  - idempotencia;
  - lock por `checkout_intent_uuid`.

### I) Relacion con lock
La idempotencia necesita complementarse con lock por checkout intent.

Lock futuro documentado:

```text
mxmed:subscriptions:checkout_intents:{checkout_intent_uuid}:payment_intent_create
```

Motivo:

- Evitar carrera entre dos `Idempotency-Key` distintas para el mismo checkout.
- Proteger el intervalo entre `findActiveByCheckoutIntentUuid(...)` e insert.
- Evitar doble payment intent activo para `checkout_intent_uuid`.

Esta adenda no cierra el lock. La siguiente microfase debe ser:

```text
BE/SPEC-Suscripciones-PaymentIntent-Lock-Readiness-01
```

### J) Contrato minimo de ajuste posterior
Para que `subscriptions.payment_intent.create` quede listo, una microfase futura de implementacion debera:

1. Agregar constante conceptual `PAYMENT_INTENT_OPERATION = subscriptions.payment_intent.create`.
2. Permitir esa operacion en la allowlist.
3. Implementar builder canonico `buildPaymentIntentRequestHash(...)`.
4. Hacer que `requestHashForOperation(...)` use el builder de payment intent.
5. Agregar completion/replay para resultado de payment intent.
6. Persistir `response_body_text` con respuesta minima del payment intent.
7. Mantener `idempotency_key_reused_with_different_payload`.
8. Mantener `request_already_processing`.
9. No cambiar el comportamiento existente de checkout-intents.
10. No cambiar el comportamiento existente de aceptacion contractual.

### K) No responsabilidades
Esta readiness de idempotencia NO implementa:

- Servicio `CreateSubscriptionPaymentIntentService`.
- Provider mock/dev.
- Provider real.
- Endpoint.
- HTTP/POST.
- SQL write.
- DB/schema.
- `payment_events`.
- Webhook.
- `profile_subscriptions`.
- Activacion post-payment.
- Facturacion.
- Capacidades.
- `PublicProfilePlanCapabilities`.
- Perfil publico.
- SEO.
- Frontend.

### L) Decision de readiness
Decision:

- `subscription_write_idempotency_keys` esta lista como infraestructura de storage.
- `SubscriptionWriteIdempotencyRepository` esta listo como base reusable.
- `SubscriptionWriteIdempotencyService` requiere ajuste minimo antes del servicio payment intent.
- La operacion `subscriptions.payment_intent.create` debe ser soportada antes de implementar `CreateSubscriptionPaymentIntentService`.

Riesgo bloqueante:

- No implementar `CreateSubscriptionPaymentIntentService` hasta cerrar la extension de idempotencia y luego la readiness de lock.

### M) Siguiente microfase recomendada
Siguiente microfase inmediata:

```text
BE/SPEC-Suscripciones-PaymentIntent-Lock-Readiness-01
```

Objetivo:

- Validar readiness tecnica para lock `payment_intent_create` por `checkout_intent_uuid`, sin escribir codigo todavia.

Microfase posterior:

```text
BE/SPEC-Suscripciones-PaymentIntent-ProviderMock-Plan-01
```

Objetivo:

- Definir el contrato mock/dev que entregara `provider_payment_id` y datos minimos de provider para crear payment intents sin provider real.

---

## Adenda PP-Decisiones 105 - Readiness de lock para payment intent

### A) Motivo
Esta adenda valida la readiness tecnica del lock requerido para crear payment intents desde checkout-intents, antes de implementar el servicio futuro:

```text
modules/subscriptions/services/CreateSubscriptionPaymentIntentService.php
```

El objetivo es evitar carreras entre solicitudes concurrentes que intenten crear mas de un payment intent activo para el mismo `checkout_intent_uuid`.

Esta adenda parte de:

- PP-Decisiones 103, que dejo el lock por `checkout_intent_uuid` como dependencia del servicio payment intent.
- PP-Decisiones 104, que confirmo que `subscriptions.payment_intent.create` requiere idempotencia propia.
- El repositorio `SubscriptionPaymentIntentRepository`, que ya expone `findActiveByCheckoutIntentUuid(...)` para apoyar el anti-duplicado.

Esta adenda NO implementa el lock ni crea el servicio payment intent.

### B) Patron de lock existente
El patron actual observado para checkout-intents vive en `SubscriptionEntityWriteLockService`:

- Usa prefijo `mxmed:subscriptions`.
- Usa lock de checkout con forma conceptual `mxmed:subscriptions:{entity_type}:{entity_id}:checkout_create`.
- Usa `GET_LOCK(...)` y `RELEASE_LOCK(...)` sobre la conexion PDO compartida.
- Usa timeout observado de 2 segundos en el flujo checkout.
- Libera el lock en bloque `finally` desde `CreateSubscriptionCheckoutIntentService`.
- Tiene limite de nombre de lock y fallback por hash para no exceder la longitud permitida.
- Mantiene una allowlist de propositos permitidos.

Estado actual:

- `checkout_create` esta soportado.
- `payment_intent_create` todavia no esta soportado en la allowlist.
- El patron es reutilizable, pero requiere ajuste minimo antes de `CreateSubscriptionPaymentIntentService`.

### C) Lock futuro requerido
El lock futuro recomendado para payment intent es:

```text
mxmed:subscriptions:checkout_intents:{checkout_intent_uuid}:payment_intent_create
```

Scope:

- Un lock por `checkout_intent_uuid`.
- El lock protege la creacion del payment intent interno para un checkout intent especifico.
- No bloquea otros checkout-intents de la misma entidad.
- No reemplaza idempotencia ni anti-duplicado de storage.

Operacion conceptual:

```text
payment_intent_create
```

Error conceptual esperado si no se puede tomar el lock:

```text
payment_intent_lock_timeout
```

Si se mantiene una convencion generica de lock, tambien puede mapearse a `lock_acquisition_failed`, pero el servicio futuro debe exponer un codigo estable para payment intent.

### D) Orden logico futuro
El flujo futuro de `CreateSubscriptionPaymentIntentService::createPaymentIntent(array $input): array` debe seguir este orden minimo:

1. Normalizar input.
2. Validar payload minimo.
3. Validar `Idempotency-Key`.
4. Calcular `request_hash` canonico.
5. Iniciar idempotencia con operacion `subscriptions.payment_intent.create`.
6. Resolver replay estable si ya existe resultado completado.
7. Bloquear payload distinto con `idempotency_key_reused_with_different_payload`.
8. Bloquear request en proceso con `request_already_processing`.
9. Cargar checkout intent por `checkout_intent_uuid`.
10. Validar que exista, no este eliminado y siga en `pending_payment`.
11. Tomar lock `mxmed:subscriptions:checkout_intents:{checkout_intent_uuid}:payment_intent_create`.
12. Dentro del lock, revalidar anti-duplicado con `findActiveByCheckoutIntentUuid(...)`.
13. Si ya existe payment intent activo, devolver replay o error controlado segun idempotencia.
14. Validar amount/currency desde snapshot server-side del checkout intent.
15. Crear payment intent interno con provider mock/dev cuando exista esa dependencia.
16. Persistir resultado idempotente.
17. Liberar lock siempre.
18. Devolver respuesta minima.

En caso de error:

- Liberar lock siempre.
- No crear `payment_events`.
- No crear `profile_subscriptions`.
- No activar capacidades.
- No marcar el checkout como pagado.
- No ejecutar compensaciones fuera de una microfase autorizada.

### E) Relacion con idempotencia
El lock futuro no reemplaza la idempotencia.

La idempotencia debe seguir usando:

```text
subscriptions.payment_intent.create
```

Responsabilidades de idempotencia:

- Rechazar `Idempotency-Key` invalida.
- Rechazar misma key con payload distinto.
- Reproducir respuesta estable si la request ya fue completada.
- Bloquear request concurrente en estado processing.
- Persistir respuesta minima del payment intent.

Responsabilidades del lock:

- Serializar la seccion critica por `checkout_intent_uuid`.
- Evitar que dos procesos creen payment intents activos simultaneamente.
- Permitir que la revalidacion anti-duplicado ocurra dentro del lock.

### F) Relacion con anti-duplicado
El anti-duplicado futuro debe combinar tres controles:

1. Idempotencia por `subscriptions.payment_intent.create`.
2. Lock por `mxmed:subscriptions:checkout_intents:{checkout_intent_uuid}:payment_intent_create`.
3. Lookup de storage con `findActiveByCheckoutIntentUuid(...)`.

El schema de `subscription_payment_intents` tiene indice por `checkout_intent_uuid`, pero no se observo una restriccion unica que impida por si sola multiples payment intents activos para el mismo checkout intent.

Tambien existe unicidad por `(provider, provider_payment_id)`. Ese control ayuda contra duplicados externos de provider, pero no sustituye:

- lock por checkout intent;
- idempotencia por operacion;
- lookup activo antes de crear.

### G) Errores conceptuales
Errores que debe contemplar el servicio futuro alrededor del lock:

- `payment_intent_lock_timeout`.
- `lock_acquisition_failed`.
- `payment_intent_already_exists`.
- `checkout_intent_not_found`.
- `checkout_intent_not_pending_payment`.
- `checkout_intent_deleted`.
- `idempotency_key_invalid`.
- `idempotency_key_reused_with_different_payload`.
- `request_already_processing`.
- `payment_intent_create_failed`.
- `payment_intent_unavailable`.

La decision de exponer `payment_intent_already_exists` o replay idempotente depende del estado de idempotencia asociado a la request. El repository no debe decidir esa politica.

### H) No responsabilidades del lock
El lock de payment intent NO debe:

- Resolver precio.
- Validar contrato.
- Crear accepted_pending_payment.
- Crear checkout intent.
- Crear `payment_events`.
- Crear `profile_subscriptions`.
- Activar capacidades.
- Facturar.
- Llamar provider real.
- Manejar webhooks.
- Modificar SEO o perfil publico.
- Ejecutar SQL write fuera del servicio autorizado.
- Hacer limpieza de datos.

El lock tampoco debe vivir en `SubscriptionPaymentIntentRepository`; debe ser una dependencia de la capa de servicio/orquestacion.

### I) Brechas y readiness
Clasificacion:

- `SubscriptionEntityWriteLockService`: requiere ajuste previo para soportar `payment_intent_create`.
- Nombre de lock futuro: listo documentalmente.
- Scope por `checkout_intent_uuid`: listo documentalmente.
- Patron de acquire/release: listo como referencia tecnica.
- Timeout observado: listo como referencia inicial, sujeto a ajuste de implementacion.
- Relacion con idempotencia: lista documentalmente.
- Relacion con anti-duplicado: lista documentalmente.
- `findActiveByCheckoutIntentUuid(...)`: disponible en repository de payment intents.

Riesgo bloqueante antes de implementar `CreateSubscriptionPaymentIntentService`:

- `payment_intent_create` no esta soportado todavia por el servicio de lock existente.

Advertencia:

- No implementar el servicio payment intent hasta cerrar la extension tecnica de lock y la extension tecnica de idempotencia para `subscriptions.payment_intent.create`.

### J) Siguiente microfase recomendada
Siguiente microfase inmediata:

```text
BE/SPEC-Suscripciones-PaymentIntent-ProviderMock-Plan-01
```

Objetivo:

- Definir el contrato mock/dev que entregara `provider_payment_id` y datos minimos de provider para crear payment intents sin provider real.

Microfase tecnica posterior requerida antes del servicio:

```text
BE/Suscripciones-PaymentIntent-LockDependency-01
```

Objetivo:

- Extender el lock existente para permitir `payment_intent_create` por `checkout_intent_uuid`, sin endpoint y sin provider real.

---

## Adenda PP-Decisiones 106 - Plan de provider mock/dev para payment intent

### A) Motivo
Esta adenda documenta el contrato minimo del provider mock/dev que permitira crear payment intents internos en DEV/local sin integrar un provider real.

Motivos:

- PP-Decisiones 103 dejo provider mock/dev como dependencia bloqueante antes de `CreateSubscriptionPaymentIntentService`.
- PP-Decisiones 104 cerro el contrato documental de idempotencia para `subscriptions.payment_intent.create`.
- PP-Decisiones 105 cerro el contrato documental de lock `payment_intent_create` por `checkout_intent_uuid`.
- El provider real no esta listo.
- No se debe crear `CreateSubscriptionPaymentIntentService.php` hasta definir el contrato minimo del provider mock/dev.
- `subscription_payment_intents.provider_payment_id` es `NOT NULL`.
- El provider mock/dev permitira avanzar a flujo DEV end-to-end sin llamadas externas, sin credenciales reales y sin representar pago real.

Esta adenda NO implementa provider mock/dev, servicio, idempotencia, lock, endpoint, HTTP/POST ni SQL write.

### B) Provider mock/dev tentativo
Provider tentativo:

```text
mxmed_mock
```

Reglas:

- Debe ser explicitamente no productivo.
- Debe limitarse a DEV/local o ambiente controlado.
- No debe hacer llamadas externas.
- No debe requerir credenciales reales.
- No debe representar pago real.
- No debe activar suscripcion.
- No debe crear `payment_events`.
- No debe crear `profile_subscriptions`.
- No debe ejecutar post-payment activation.

El provider real queda fuera de esta etapa.

### C) provider_payment_id
`provider_payment_id` debe ser generado por el flujo mock/dev porque la columna `subscription_payment_intents.provider_payment_id` es `NOT NULL`.

Requisitos:

- Debe ser estable y trazable.
- Debe ser unico para `provider = mxmed_mock`.
- Debe permitir replay idempotente sin generar ids distintos para la misma operacion.
- Debe integrarse con el unique existente `provider + provider_payment_id`.
- No debe depender de llamadas externas.
- No debe ser enviado por el cliente como fuente canonica.

Propuesta conceptual:

```text
mxmed_mock_pi_{uuid_o_token_deterministico}
```

El token deterministico debe derivarse de datos controlados por backend, por ejemplo:

- `checkout_intent_uuid`;
- `request_hash` canonico de `subscriptions.payment_intent.create`;
- o UUID interno generado dentro del flujo idempotente y persistido como resultado.

Decision documental:

- Para replay estable, el servicio futuro debe recuperar el resultado idempotente completado antes de generar un nuevo `provider_payment_id`.
- Si se genera antes del insert, debe guardarse dentro del resultado idempotente exitoso.
- Misma `Idempotency-Key` + mismo `request_hash` debe devolver el mismo `provider_payment_id`.
- Misma `Idempotency-Key` + request distinto debe bloquear con `idempotency_key_reused_with_different_payload`.

### D) provider_checkout_id
`provider_checkout_id` es nullable en `subscription_payment_intents`.

Decision:

- Puede quedar `NULL` si el provider mock/dev no simula un checkout externo.
- Si se usa, debe ser opcional y trazable.
- No debe confundirse con `subscription_checkout_intents.uuid`.

Propuesta conceptual opcional:

```text
mxmed_mock_chk_{checkout_intent_uuid_o_token}
```

Si se documenta o implementa mas adelante, su uso debe ser defensivo:

- lookup opcional por `provider + provider_checkout_id`;
- sin asumir unique directo por `provider_checkout_id`;
- sin reemplazar `provider_payment_id`.

### E) Estado inicial del payment intent
Estados iniciales permitidos por el repository actual:

```text
created
pending_provider
```

Estado inicial recomendado para mock/dev minimo:

```text
created
```

Alternativa permitida si el mock simula envio a provider:

```text
pending_provider
```

Regla critica:

- No usar `paid` en create inicial.
- `paid` solo podra existir despues de confirmacion mock/dev o real de pago en una microfase posterior.
- El create de payment intent no debe activar suscripcion.
- El create de payment intent no debe crear `profile_subscriptions`.
- El create de payment intent no debe crear `payment_events`.

### F) Input futuro del provider mock/dev
Input conceptual minimo:

- `checkout_intent_uuid`.
- `amount_cents`.
- `currency`.
- `provider = mxmed_mock`.
- `source`.
- `idempotency_key`.
- `request_hash`, si la capa superior ya lo calculo.
- `metadata` o `notes` opcional si el servicio futuro lo permite.

El cliente no debe enviar como canonicos:

- `provider_payment_id`.
- `provider_checkout_id`.
- `normalized_status`.
- `paid_at`.
- datos de provider real.

### G) Salida futura del provider mock/dev
Salida conceptual minima:

- `provider = mxmed_mock`.
- `provider_payment_id`.
- `provider_checkout_id`, opcional.
- `provider_status`, por ejemplo `mock_created`.
- `normalized_status`, recomendado `created` o `pending_provider`.
- `amount_cents`.
- `currency`.
- `created_at_provider`, si aplica.
- `raw_response` opcional si el schema o servicio futuro lo soporta como metadata/notes.

La salida debe ser suficiente para construir el payload de:

```text
SubscriptionPaymentIntentRepository::create(...)
```

### H) Relacion con idempotencia
El provider mock/dev debe ser compatible con:

```text
subscriptions.payment_intent.create
```

Reglas:

- Replay con misma `Idempotency-Key` y mismo `request_hash` debe devolver el mismo `provider_payment_id`.
- Misma key con request distinto debe bloquear.
- El mock no debe cambiar el `request_hash` canonico documentado en PP-Decisiones 104.
- La generacion de `provider_payment_id` debe ocurrir despues de validar idempotencia y antes de persistir el resultado exitoso.
- El resultado idempotente debe incluir `provider`, `provider_payment_id`, `provider_checkout_id` si aplica, `normalized_status`, amount/currency y `payment_intent_uuid`.

### I) Relacion con lock
La creacion mock/dev debe ocurrir dentro del lock:

```text
mxmed:subscriptions:checkout_intents:{checkout_intent_uuid}:payment_intent_create
```

Reglas:

- El provider mock/dev no sustituye el lock.
- El lock debe proteger la seccion critica entre anti-duplicado y creacion.
- Si no se obtiene lock, no se debe generar ni persistir payment intent.
- El lock debe liberarse siempre.
- El lock no debe vivir en el repository.

### J) Relacion con anti-duplicado
Antes de crear el payment intent debe validarse:

```text
SubscriptionPaymentIntentRepository::findActiveByCheckoutIntentUuid(...)
```

Reglas:

- Si ya existe payment intent activo para el checkout, no debe crearse otro.
- El unique `provider + provider_payment_id` ayuda como defensa adicional.
- Como no hay unique por `checkout_intent_uuid`, el control completo sigue siendo:
  - lookup `findActiveByCheckoutIntentUuid(...)`;
  - idempotencia `subscriptions.payment_intent.create`;
  - lock `payment_intent_create`;
  - unique `provider + provider_payment_id`.

### K) No responsabilidades
Esta adenda NO implementa ni habilita:

- Provider mock/dev.
- Adapter/provider class.
- Provider real.
- Credenciales reales.
- Servicio `CreateSubscriptionPaymentIntentService`.
- `CreateSubscriptionPaymentIntentService.php`.
- Endpoint.
- HTTP/POST.
- SQL write.
- DB/schema.
- Idempotencia tecnica.
- Lock tecnico.
- Webhook.
- `payment_events`.
- Confirmacion de pago.
- Marcar `paid`.
- `profile_subscriptions`.
- Post-payment activation.
- Facturacion.
- Capacidades.
- Frontend.

### L) Orden recomendado posterior
Orden seguro antes de implementar el servicio:

1. `BE/Suscripciones-PaymentIntent-IdempotencyOperation-01`
   - Permitir/implementar operacion `subscriptions.payment_intent.create` en la infraestructura existente.
2. `BE/Suscripciones-PaymentIntent-LockOperation-01`
   - Permitir/implementar lock `payment_intent_create` por `checkout_intent_uuid`.
3. `BE/Suscripciones-PaymentIntent-ProviderMock-Contract-01`
   - Cerrar contrato tecnico del mock si la implementacion requiere adapter/helper separado.
4. `BE/Suscripciones-PaymentIntent-Service-01`
   - Crear `CreateSubscriptionPaymentIntentService.php` cuando idempotencia, lock y contrato mock esten listos.

Decision documental:

- El provider mock/dev puede quedar planificado en esta adenda.
- Antes del servicio son bloqueantes los ajustes tecnicos de idempotencia y lock.
- Si el servicio futuro implementa generacion local simple de `mxmed_mock_pi_...` sin adapter separado, la microfase `ProviderMock-Contract-01` puede omitirse o convertirse en readiness puntual.

### M) Readiness del plan
Clasificacion:

- Provider tentativo `mxmed_mock`: listo documentalmente.
- `provider_payment_id` NOT NULL: confirmado por schema y repository.
- Generacion conceptual `mxmed_mock_pi_...`: lista documentalmente.
- `provider_checkout_id` opcional `mxmed_mock_chk_...`: listo documentalmente.
- Estados iniciales `created` o `pending_provider`: soportados por repository.
- `paid` fuera de create inicial: decision cerrada documentalmente.
- Relacion con idempotencia: lista documentalmente.
- Relacion con lock: lista documentalmente.
- Relacion con anti-duplicado: lista documentalmente.

Riesgos bloqueantes antes de `CreateSubscriptionPaymentIntentService`:

- Falta implementar operacion idempotente `subscriptions.payment_intent.create`.
- Falta implementar lock `payment_intent_create`.
- Falta decidir si el mock sera helper interno del servicio o adapter separado.

---

## Adenda PP-Decisiones 107 - Readiness final de implementación del servicio payment intent

### A) Motivo
Esta adenda valida la readiness final antes de implementar en una microfase posterior:

```text
modules/subscriptions/services/CreateSubscriptionPaymentIntentService.php
```

Metodo conceptual futuro:

```text
CreateSubscriptionPaymentIntentService::createPaymentIntent(array $input): array
```

Quedan como base cerrada y versionada:

- PP-Decisiones 103: plan de dependencias del servicio payment intent.
- PP-Decisiones 104: readiness de idempotencia para payment intent.
- PP-Decisiones 105: readiness de lock para payment intent.
- PP-Decisiones 106: plan de provider mock/dev para payment intent.

Tambien ya existen implementaciones tecnicas de:

- Idempotencia payment intent con operacion `subscriptions.payment_intent.create`.
- Lock payment intent con operacion `payment_intent_create`.
- Provider mock/dev `SubscriptionPaymentIntentMockProvider`.

Esta microfase NO crea `CreateSubscriptionPaymentIntentService.php`, no implementa endpoint, no modifica PHP y no cambia DB/schema.

### B) Estado tecnico confirmado
Inspeccion read-only confirmada:

- `CreateSubscriptionPaymentIntentService.php` aun no existe.
- `SubscriptionPaymentIntentRepository` existe y opera sobre `subscription_payment_intents`.
- `SubscriptionPaymentIntentRepository::create(...)` inserta payment intents iniciales.
- `SubscriptionPaymentIntentRepository::findActiveByCheckoutIntentUuid(...)` permite anti-duplicado por checkout.
- `SubscriptionWriteIdempotencyService` soporta `PAYMENT_INTENT_OPERATION = subscriptions.payment_intent.create`.
- `SubscriptionWriteIdempotencyService::beginPaymentIntent(...)` existe.
- `SubscriptionWriteIdempotencyService::buildPaymentIntentRequestHash(...)` existe.
- `SubscriptionEntityWriteLockService::acquirePaymentIntentCreate(...)` existe.
- El lock usa scope conceptual `checkout_intent_uuid` y operacion `payment_intent_create`.
- `SubscriptionPaymentIntentMockProvider::create(...)` existe.
- El provider mock/dev fuerza `mxmed_mock`, genera `mxmed_mock_pi_...` y `mxmed_mock_chk_...`.
- El provider mock/dev permite `created` o `pending_provider` como estados iniciales.
- El provider mock/dev rechaza `paid` en create inicial.
- `SubscriptionCheckoutIntentRepository::findByUuid(...)` permite cargar el checkout por UUID si el servicio futuro lo requiere.

Inspeccion DB read-only confirmada:

- `subscription_payment_intents` existe.
- `subscription_payment_intents.provider_payment_id` es `NOT NULL`.
- Existe unique por `uuid`.
- Existe unique por `provider + provider_payment_id`.
- Existe indice no unico por `checkout_intent_uuid`.
- No se observo unique por `checkout_intent_uuid`.
- `subscription_payment_intents` tiene `0` filas en el estado inspeccionado.
- `subscription_payment_events` tiene `0` filas en el estado inspeccionado.
- `subscription_checkout_intents` tiene `1` fila en el estado inspeccionado.
- Fixture checkout pendiente confirmado:

```text
uuid = 7d4beec3-b62a-40e1-a9f2-9edcc1a83364
entity_type = doctor
entity_id = 900001
plan_code = standard
billing_period = annual
status = pending_payment
amount_cents = 20000
currency = MXN
deleted_at = NULL
```

### C) Dependencias confirmadas
El servicio futuro puede construirse sobre estas dependencias ya disponibles:

- `SubscriptionPaymentIntentRepository`.
- `SubscriptionPaymentIntentRepository::findActiveByCheckoutIntentUuid(...)`.
- `SubscriptionPaymentIntentRepository::create(...)`.
- `SubscriptionWriteIdempotencyService::beginPaymentIntent(...)`.
- `SubscriptionWriteIdempotencyService::buildPaymentIntentRequestHash(...)`.
- `SubscriptionEntityWriteLockService::acquirePaymentIntentCreate(...)`.
- `SubscriptionPaymentIntentMockProvider::create(...)`.
- `SubscriptionCheckoutIntentRepository`, para cargar checkout por UUID si aplica.

Dependencias que siguen fuera de esta readiness:

- Provider real.
- Webhooks.
- `payment_events`.
- Activacion post-pago.
- `profile_subscriptions`.
- Facturacion.
- Capacidades productivas.

### D) Input futuro recomendado
Contrato minimo recomendado para:

```text
createPaymentIntent(array $input): array
```

Input minimo:

- `checkout_intent_uuid`.
- `idempotency_key`.
- `provider`, opcional; default `mxmed_mock`.
- `source`, opcional.
- `notes` o metadata minima opcional, si el repositorio lo permite.

Reglas:

- `amount_cents` y `currency` deben salir del checkout server-side.
- Si el caller envia `amount_cents` o `currency`, no se deben confiar sin compararlos contra el checkout.
- `provider_payment_id` no debe venir del cliente como valor canonico.
- `normalized_status` no debe venir del cliente como valor canonico.
- `paid_at` no debe aceptarse para create inicial.

### E) Flujo interno futuro recomendado
Orden minimo recomendado para `CreateSubscriptionPaymentIntentService::createPaymentIntent(...)`:

1. Normalizar input.
2. Validar payload minimo.
3. Validar `Idempotency-Key`.
4. Cargar checkout intent por `checkout_intent_uuid`.
5. Validar que el checkout existe, no esta eliminado y sigue en `pending_payment`.
6. Resolver `amount_cents` y `currency` desde el checkout.
7. Construir `request_hash` canonico con `buildPaymentIntentRequestHash(...)`.
8. Iniciar idempotencia con operation `subscriptions.payment_intent.create` usando `beginPaymentIntent(...)`.
9. Si existe replay estable, devolver el resultado idempotente.
10. Si la key se reutilizo con payload distinto, bloquear con error de idempotencia.
11. Adquirir lock `payment_intent_create` por `checkout_intent_uuid` usando `acquirePaymentIntentCreate(...)`.
12. Dentro del lock, revalidar `findActiveByCheckoutIntentUuid(...)`.
13. Si ya existe payment intent activo, bloquear o devolver segun contrato final.
14. Llamar `SubscriptionPaymentIntentMockProvider::create(...)`.
15. Crear payment intent con `SubscriptionPaymentIntentRepository::create(...)`.
16. Persistir respuesta idempotente exitosa.
17. Liberar lock en `finally`.
18. Devolver payment intent interno.

En error:

- Liberar lock si fue adquirido.
- Marcar idempotencia fallida segun patron existente.
- No crear segunda fila.
- No crear `payment_events`.
- No crear `profile_subscriptions`.
- No activar suscripcion.

### F) Estados
Estados iniciales permitidos para create inicial:

```text
created
pending_provider
```

Reglas:

- `created` es el estado inicial recomendado para mock/dev minimo.
- `pending_provider` puede usarse si el mock simula una entrega pendiente al provider.
- `paid` esta prohibido en create inicial.
- `paid` solo debe llegar despues de confirmacion mock/dev o real de pago en una microfase posterior.
- El cambio a `paid` no debe ocurrir dentro de `CreateSubscriptionPaymentIntentService` inicial.

### G) No responsabilidades del servicio inicial
El servicio inicial `CreateSubscriptionPaymentIntentService` NO debe:

- Integrar provider real.
- Ejecutar HTTP externo.
- Ejecutar webhook.
- Crear `payment_events`.
- Crear `profile_subscriptions`.
- Hacer post-payment activation.
- Marcar `paid` en create inicial.
- Facturar.
- Activar capacidades.
- Crear endpoint.
- Modificar `api/subscriptions/index.php`.
- Ejecutar SQL directo fuera de repositorios.
- Modificar DB/schema.
- Limpiar datos locales/dev.

### H) Riesgos y guardas
Riesgo observado:

- No hay unique por `checkout_intent_uuid` en `subscription_payment_intents`.

Mitigacion obligatoria:

- Lookup `SubscriptionPaymentIntentRepository::findActiveByCheckoutIntentUuid(...)`.
- Idempotencia `subscriptions.payment_intent.create`.
- Lock `payment_intent_create` por `checkout_intent_uuid`.
- Unique `provider + provider_payment_id`.

Guardas adicionales:

- `provider_payment_id` es `NOT NULL`, por lo que el mock/dev debe generarlo antes de persistir.
- `SubscriptionPaymentIntentMockProvider` genera `provider_payment_id` estable con prefijo `mxmed_mock_pi_`.
- `SubscriptionPaymentIntentMockProvider` genera `provider_checkout_id` estable con prefijo `mxmed_mock_chk_`.
- El checkout debe seguir en `pending_payment`.
- El servicio debe usar el snapshot server-side del checkout para monto y moneda.
- No debe confiar en amount/currency enviados por cliente sin comparacion.

### I) Errores conceptuales esperados
Errores que debe manejar o propagar el servicio futuro:

- `invalid_payment_intent_payload`.
- `idempotency_key_invalid`.
- `idempotency_key_reused_with_different_payload`.
- `request_already_processing`.
- `payment_intent_lock_timeout`.
- `checkout_intent_not_found`.
- `checkout_intent_not_pending_payment`.
- `payment_intent_already_pending`.
- `payment_intent_provider_reference_conflict`.
- `payment_intent_create_failed`.
- `payment_intent_lookup_failed`.
- `payment_intent_unavailable`.

Errores fuera de esta capa inicial:

- Errores de provider real.
- Errores de webhook.
- Errores de `payment_events`.
- Errores de activacion post-pago.
- Errores de facturacion.

### J) Readiness final
Clasificacion:

- `SubscriptionPaymentIntentRepository`: listo.
- Anti-duplicado por `findActiveByCheckoutIntentUuid(...)`: listo como guarda de servicio.
- Idempotencia `subscriptions.payment_intent.create`: lista.
- `beginPaymentIntent(...)`: listo.
- `buildPaymentIntentRequestHash(...)`: listo.
- Lock `payment_intent_create`: listo.
- `acquirePaymentIntentCreate(...)`: listo.
- Provider mock/dev `SubscriptionPaymentIntentMockProvider`: listo.
- Fixture checkout `7d4beec3-b62a-40e1-a9f2-9edcc1a83364`: listo para QA local/dev.
- Regla no `payment_events`: lista.
- Regla no `profile_subscriptions`: lista.
- Regla no `post-payment activation`: lista.
- Regla no `paid` inicial: lista.

No se detectan brechas bloqueantes para implementar el servicio aislado en una siguiente microfase, siempre que esa microfase no implemente endpoint, provider real, webhook, payment events, facturacion ni activacion post-pago.

### K) Siguiente microfase recomendada
Siguiente microfase inmediata:

```text
BE/Suscripciones-PaymentIntent-Service-01
```

Objetivo:

- Implementar `CreateSubscriptionPaymentIntentService` con metodo `createPaymentIntent(array $input): array`, usando repository, idempotencia, lock y provider mock/dev ya existentes, sin endpoint, sin provider real, sin `payment_events`, sin `profile_subscriptions`, sin post-payment activation y sin DB/schema.

QA posterior recomendada:

```text
QA-Suscripciones-PaymentIntent-Service-01
```

Objetivo:

- Validar `php -l`, dependencias, idempotencia, lock, mock provider, anti-duplicado, estados iniciales `created`/`pending_provider`, prohibicion de `paid`, ausencia de `payment_events`, ausencia de `profile_subscriptions` y ausencia de post-payment activation.

---

## Adenda PP-Decisiones 108 - Readiness de endpoint payment intent

### A) Motivo
Ya existe el servicio interno:

```text
modules/subscriptions/services/CreateSubscriptionPaymentIntentService.php
```

con metodo publico:

```text
CreateSubscriptionPaymentIntentService::createPaymentIntent(array $input): array
```

La microfase actual valida readiness tecnica para exponerlo posteriormente mediante un endpoint privado/controlado. Esta adenda no implementa endpoint, no modifica `api/subscriptions/index.php`, no duplica logica de negocio y no ejecuta HTTP/POST.

Antes de modificar el router se cierra el contrato del endpoint futuro para asegurar que el endpoint:

- invoque `CreateSubscriptionPaymentIntentService`;
- no duplique validacion de checkout `pending_payment`;
- no duplique idempotencia;
- no duplique lock;
- no duplique provider mock/dev;
- no duplique anti-duplicado;
- no cree `payment_events`;
- no cree ni toque `profile_subscriptions`;
- no active plan;
- no conecte provider real ni webhook.

### B) Estado tecnico observado
Inspeccion read-only:

- Router actual: `api/subscriptions/index.php` ya expone `checkout-intents`, pero no expone `payment-intents`.
- Patron de writes: `subscriptionResolveWriteContext(...)` exige request local/dev, sesion PHP real `session_scope`, rechaza headers de identidad para writes y restringe el actor inicial a doctor.
- Idempotencia: el patron actual lee `Idempotency-Key` desde headers y lo pasa al servicio de write.
- Servicio payment intent: `CreateSubscriptionPaymentIntentService` ya existe y valida `checkout_intent_uuid`, `Idempotency-Key`, checkout `pending_payment`, provider `mxmed_mock`, lock `payment_intent_create` y anti-duplicado por `findActiveByCheckoutIntentUuid(...)`.
- Provider mock/dev: `SubscriptionPaymentIntentMockProvider` ya existe y usa `mxmed_mock`.
- Repository payment intent: `SubscriptionPaymentIntentRepository` ya existe y crea solo `subscription_payment_intents`.
- Repository checkout intent: `SubscriptionCheckoutIntentRepository::findByUuid(...)` permite recuperar el checkout server-side.
- DB read-only: `subscription_payment_intents = 0`, `subscription_payment_events = 0`, `subscription_checkout_intents = 1`; el fixture `7d4beec3-b62a-40e1-a9f2-9edcc1a83364` existe para `doctor 900001` en `pending_payment`, con `amount_cents = 20000`, `currency = MXN` y provider fields nulos.

Brecha detectada:

- Falta cablear el endpoint futuro en `api/subscriptions/index.php`.
- Falta agregar `require_once`/`use` de `CreateSubscriptionPaymentIntentService`, `SubscriptionPaymentIntentRepository` y `SubscriptionPaymentIntentMockProvider` cuando se implemente el endpoint.
- Falta mapper HTTP especifico para `CreateSubscriptionPaymentIntentException`.

No se detectan brechas bloqueantes para implementar el endpoint privado/controlado en una siguiente microfase, siempre que el alcance se limite a cablear el servicio ya existente.

### C) Endpoint futuro recomendado
Ruta recomendada:

```text
POST /api/subscriptions/index.php/entities/{entity_type}/{entity_id}/checkout-intents/{checkout_intent_uuid}/payment-intents
```

Fixture actual:

```text
POST /api/subscriptions/index.php/entities/doctor/900001/checkout-intents/7d4beec3-b62a-40e1-a9f2-9edcc1a83364/payment-intents
```

Justificacion:

- Mantiene jerarquia sobre el checkout intent ya creado.
- Evita endpoint suelto de payment intent sin entidad.
- Permite reutilizar `subscriptionResolveWriteContext(entity_type, entity_id)`.
- Permite pasar `checkout_intent_uuid` por path y no confiar en un body controlado por cliente para identificar el checkout.

### D) Autorizacion
El endpoint futuro debe exigir:

- metodo `POST`;
- sesion PHP valida;
- `session_scope`;
- `entity_type = doctor` en la primera version;
- `entity_id` compatible con la sesion;
- `actor_role` compatible con doctor;
- `operator_id = null` en esta etapa;
- request local/dev si se mantiene el mismo guard de writes actual;
- no autorizacion por headers sueltos.

Debe reutilizar el patron actual de writes de checkout-intents:

```text
subscriptionResolveWriteContext($entityType, $entityId)
```

No debe relajar el rechazo de headers de identidad para writes.

### E) Idempotencia
El endpoint futuro debe exigir header:

```text
Idempotency-Key
```

El endpoint debe mapearlo hacia el input del servicio:

```text
idempotency_key
```

Si falta o es invalido, debe responder con error equivalente a:

```text
idempotency_key_invalid
```

La idempotencia de negocio vive en `CreateSubscriptionPaymentIntentService` mediante:

- `buildPaymentIntentRequestHash(...)`;
- `beginPaymentIntent(...)`;
- `markPaymentIntentCompleted(...)`;
- `markOperationFailed(...)`.

El endpoint no debe recalcular ni persistir idempotencia por fuera del servicio.

### F) Input body futuro
Payload minimo recomendado:

```json
{
  "provider": "mxmed_mock",
  "source": "payment_intent",
  "notes": "optional",
  "metadata": {
    "qa": "optional"
  }
}
```

Reglas:

- `provider` es opcional y default `mxmed_mock`.
- `source` es opcional y default `payment_intent`.
- `notes` es opcional.
- `metadata` es opcional si se decide soportar notas JSON en el servicio.
- `checkout_intent_uuid` debe venir del path.
- `idempotency_key` debe venir de `Idempotency-Key`.

El cliente NO debe enviar ni controlar:

- `amount_cents`;
- `currency`;
- `price_source`;
- `price_version`;
- `provider_payment_id`;
- `provider_checkout_id`;
- `normalized_status = paid`;
- `paid_at`;
- `profile_subscription_id`;
- `payment_events`;
- `profile_subscriptions`.

`amount_cents` y `currency` deben venir del checkout server-side cargado por `SubscriptionCheckoutIntentRepository::findByUuid(...)`.

### G) Servicio invocado
El endpoint futuro debe instanciar e invocar:

```text
CreateSubscriptionPaymentIntentService::createPaymentIntent(...)
```

Input conceptual hacia el servicio:

```php
[
    'checkout_intent_uuid' => $checkoutIntentUuid,
    'idempotency_key' => $idempotencyKey,
    'provider' => $payload['provider'] ?? 'mxmed_mock',
    'source' => $payload['source'] ?? 'payment_intent',
    'notes' => $payload['notes'] ?? null,
    'metadata' => $payload['metadata'] ?? null,
]
```

El endpoint no debe duplicar:

- validacion de checkout `pending_payment`;
- obtencion server-side de `amount_cents` y `currency`;
- idempotencia;
- lock;
- provider mock;
- anti-duplicado;
- creacion en repository;
- generacion de `provider_payment_id`.

### H) Respuestas esperadas
Respuestas conceptuales:

- `201 Created`: se crea un payment intent nuevo.
- `200 OK`: replay idempotente devuelve resultado previo.
- `409 Conflict`: `idempotency_key_reused_with_different_payload`.
- `409 Conflict`: `payment_intent_already_exists`.
- `409 Conflict`: `payment_intent_lock_timeout` o conflicto equivalente.
- `422 Unprocessable Entity`: `idempotency_key_invalid`.
- `422 Unprocessable Entity`: `checkout_intent_uuid_required`.
- `422 Unprocessable Entity`: payload invalido.
- `404 Not Found`: `checkout_intent_not_found`, si se conserva el status del servicio.
- `409 Conflict`: `checkout_intent_not_pending_payment`, si se conserva el status del servicio.
- `500 Internal Server Error`: `payment_intent_create_failed` o `payment_intent_unavailable`.

El mapper HTTP futuro debe ser explicito para `CreateSubscriptionPaymentIntentException`.

### I) No responsabilidades
El endpoint inicial NO debe:

- implementar provider real;
- llamar provider real;
- implementar webhook;
- crear `payment_events`;
- crear `subscription_payment_events`;
- crear ni tocar `profile_subscriptions`;
- ejecutar post-payment activation;
- marcar payment intent como `paid`;
- activar plan;
- facturar;
- modificar DB/schema;
- crear SQL;
- aceptar writes por headers sueltos;
- exponer endpoint publico;
- limpiar datos de QA;
- modificar checkout-intents existentes fuera del payment intent creado.

### J) QA futura recomendada
QA futura controlada, todavia no ejecutada:

```text
QA/Suscripciones-PaymentIntent-Endpoint-FunctionalControlled-Positive201-01
```

Casos futuros:

- Positive201 con `doctor 900001` y checkout fixture `7d4beec3-b62a-40e1-a9f2-9edcc1a83364`.
- Replay same key.
- Same key different payload.
- Existing payment intent.
- Missing `Idempotency-Key`.
- No session auth.
- Checkout not found.
- Checkout not `pending_payment`.

La QA futura debe validar counts antes/despues:

- `subscription_payment_intents` incrementa solo en Positive201.
- `subscription_payment_events` no cambia.
- `profile_subscriptions` no cambia.
- No se crea post-payment activation.

### K) Siguiente microfase recomendada
Siguiente microfase inmediata:

```text
BE/Suscripciones-PaymentIntent-Endpoint-01
```

Objetivo:

- Implementar el endpoint privado/controlado `POST /api/subscriptions/index.php/entities/{entity_type}/{entity_id}/checkout-intents/{checkout_intent_uuid}/payment-intents`, invocando `CreateSubscriptionPaymentIntentService::createPaymentIntent(...)`, sin provider real, sin webhooks, sin `payment_events`, sin `profile_subscriptions`, sin post-payment activation, sin SQL nuevo y sin DB/schema.

Microfase posterior:

```text
QA/Suscripciones-PaymentIntent-Endpoint-FunctionalControlled-Positive201-01
```

Objetivo:

- Ejecutar QA funcional controlada Positive201 del endpoint payment intent con sesion `session_scope` y fixture `doctor 900001`.

---

## Adenda PP-Decisiones 109 - Plan de QA funcional controlada del endpoint payment intent

### A) Motivo
El endpoint payment intent ya fue implementado y validado post-push en:

```text
POST /api/subscriptions/index.php/entities/{entity_type}/{entity_id}/checkout-intents/{checkout_intent_uuid}/payment-intents
```

El endpoint invoca `CreateSubscriptionPaymentIntentService::createPaymentIntent(array $input): array`, reutiliza `subscriptionResolveWriteContext(...)`, exige `Idempotency-Key`, usa provider mock/dev `mxmed_mock` y conserva el flujo sin provider real.

Antes de ejecutar un POST real se cierra este plan de QA funcional controlada porque el caso positivo puede crear una fila en `subscription_payment_intents`. La ejecucion futura debe ser controlada, auditable y con counts antes/despues.

Esta adenda no ejecuta HTTP/POST, no hace SQL write, no modifica DB/schema y no crea filas.

### B) Estado inicial observado/esperado
Estado Git esperado para la QA funcional futura:

- HEAD: `50e4fb4 feat(suscripciones): agrega endpoint payment intent`.
- Rama: `fix/agenda-dia-mes-rescate-controlado`.
- Working tree limpio.
- Ahead/behind `0 0`.

Estado DB read-only observado para plan:

- `subscription_payment_intents`: `0`.
- `subscription_payment_events`: `0`.
- `profile_subscriptions`: `3` filas globales observadas; la QA futura debe confirmar que este count no cambia.
- `subscription_checkout_intents`: `1`.

Checkout fixture base:

- `uuid`: `7d4beec3-b62a-40e1-a9f2-9edcc1a83364`.
- `entity_type`: `doctor`.
- `entity_id`: `900001`.
- `plan_code`: `standard`.
- `billing_period`: `annual`.
- `status`: `pending_payment`.
- `amount_cents`: `20000`.
- `currency`: `MXN`.
- `provider`, `provider_checkout_id`, `provider_payment_id`, `checkout_url`: `NULL`.
- `deleted_at`: `NULL`.

### C) Endpoint a probar en QA futura
La QA funcional futura debe probar exactamente:

```text
POST /api/subscriptions/index.php/entities/doctor/900001/checkout-intents/7d4beec3-b62a-40e1-a9f2-9edcc1a83364/payment-intents
```

Requisitos:

- sesion PHP valida con `session_scope` para `doctor 900001`;
- header `Idempotency-Key`;
- payload JSON minimo:

```json
{
  "provider": "mxmed_mock",
  "source": "qa_functional_payment_intent",
  "notes": "QA funcional controlada payment intent doctor 900001"
}
```

`provider` es opcional porque el servicio default es `mxmed_mock`; se permite enviarlo explicitamente para trazabilidad QA. El cliente no debe enviar `amount_cents`, `currency`, status, provider ids, checkout URL ni campos de pago canonicos.

### D) Preparacion de sesion
Antes del primer POST funcional se debe validar o regenerar sesion local/dev con:

```text
POST /api/subscriptions/index.php/dev/session-fixture/checkout-doctor
```

Condiciones:

- usar solo ambiente local/dev;
- helper habilitado por flag DEV/local correspondiente;
- host local;
- bloqueo de produccion activo;
- metodo `POST`;
- sesion resultante compatible con `session_scope`, `doctor_id = 900001`, `entity_type = doctor`, `entity_id = 900001`;
- sin `operator_id`;
- no autorizar writes con headers sueltos como `x-user-id`, `x-doctor-id`, `x-entity-type` o `x-entity-id`.

### E) Casos QA funcional futura
#### A. Positive201
Request:

- POST valido al endpoint payment intent.
- `Idempotency-Key`: `qa-payment-intent-900001-standard-annual-001`.
- Payload recomendado: `provider = mxmed_mock`, `source = qa_functional_payment_intent`, `notes` opcional.

Esperado:

- HTTP `201`.
- `ok = true`.
- `data.payment_intent.checkout_intent_uuid = 7d4beec3-b62a-40e1-a9f2-9edcc1a83364`.
- `data.payment_intent.provider = mxmed_mock`.
- `provider_payment_id` con prefijo `mxmed_mock_pi_`.
- `provider_checkout_id` con prefijo `mxmed_mock_chk_`.
- `normalized_status` inicial `created` o `pending_provider`; no `paid`.
- `amount_cents = 20000`.
- `currency = MXN`.
- `subscription_payment_intents` incrementa de `0` a `1`.
- `subscription_payment_events` sigue en `0`.
- `profile_subscriptions` no cambia.

#### B. Replay same key
Request:

- mismo `Idempotency-Key`;
- mismo payload.

Esperado:

- HTTP `200`.
- mismo payment intent que Positive201.
- `meta.idempotent_replay = true` o equivalente.
- no duplica filas en `subscription_payment_intents`.
- counts de `subscription_payment_events` y `profile_subscriptions` no cambian.

#### C. Same key different payload
Request:

- misma key `qa-payment-intent-900001-standard-annual-001`;
- payload distinto, por ejemplo cambiar `source` o `notes`.

Esperado:

- HTTP `409`.
- error `idempotency_key_reused_with_different_payload`.
- no crea payment intent nuevo.
- counts sin cambio.

#### D. Existing payment intent new key
Request:

- nueva `Idempotency-Key`;
- mismo checkout que ya tiene payment intent activo.

Esperado:

- HTTP `409`.
- error `payment_intent_already_exists` o equivalente.
- no duplica filas.
- counts sin cambio.

#### E. Missing Idempotency-Key
Request:

- POST valido sin header `Idempotency-Key`.

Esperado:

- HTTP `422`.
- error `idempotency_key_invalid`.
- no crea filas.

#### F. No session auth
Request:

- POST sin `PHPSESSID` valido.

Esperado:

- HTTP `403`.
- error `forbidden`.
- no crea filas.

#### G. Checkout not found
Request:

- `checkout_intent_uuid` inexistente y bien formado.

Esperado:

- HTTP `404` o patron local documentado.
- error `checkout_intent_not_found`.
- no crea filas.

#### H. Checkout not pending_payment
Este caso queda conceptual/pendiente de fixture seguro. No debe ejecutarse si requiere modificar DB manualmente.

Esperado conceptual:

- HTTP `409` o `422` segun patron vigente;
- error `checkout_intent_not_pending_payment`;
- no crea filas.

### F) Counts y consultas read-only antes/despues
La QA futura debe capturar antes y despues:

```sql
SELECT COUNT(*) AS payment_intents_total FROM subscription_payment_intents;
SELECT COUNT(*) AS payment_events_total FROM subscription_payment_events;
SELECT COUNT(*) AS profile_subscriptions_total FROM profile_subscriptions;
SELECT COUNT(*) AS checkout_intents_total FROM subscription_checkout_intents;
```

Lookup del fixture:

```sql
SELECT
  id,
  uuid,
  entity_type,
  entity_id,
  plan_code,
  billing_period,
  status,
  amount_cents,
  currency,
  provider,
  provider_checkout_id,
  provider_payment_id,
  checkout_url,
  expires_at,
  created_at,
  deleted_at
FROM subscription_checkout_intents
WHERE uuid = '7d4beec3-b62a-40e1-a9f2-9edcc1a83364'
LIMIT 1;
```

Lookup del payment intent creado:

```sql
SELECT
  uuid,
  checkout_intent_uuid,
  provider,
  provider_payment_id,
  provider_checkout_id,
  normalized_status,
  provider_status,
  amount_cents,
  currency,
  source,
  created_at,
  deleted_at
FROM subscription_payment_intents
WHERE checkout_intent_uuid = '7d4beec3-b62a-40e1-a9f2-9edcc1a83364'
  AND deleted_at IS NULL
ORDER BY id DESC
LIMIT 5;
```

### G) No responsabilidades de la QA funcional futura
La QA funcional futura no debe:

- crear `payment_events`;
- crear `subscription_payment_events`;
- crear ni tocar `profile_subscriptions`;
- ejecutar post-payment activation;
- marcar payment intent como `paid`;
- activar plan;
- llamar provider real;
- implementar webhook;
- facturar;
- modificar DB/schema;
- ejecutar SQL write manual;
- limpiar, borrar o resetear filas;
- hacer `stash`, `reset` o `restore`;
- modificar PHP;
- modificar documentacion durante la ejecucion de casos funcionales.

### H) Microfases futuras recomendadas
Orden recomendado:

```text
QA/Suscripciones-PaymentIntent-Endpoint-FunctionalControlled-Session-01
QA/Suscripciones-PaymentIntent-Endpoint-FunctionalControlled-Positive201-01
QA/Suscripciones-PaymentIntent-Endpoint-FunctionalControlled-ReplaySameKey-01
QA/Suscripciones-PaymentIntent-Endpoint-FunctionalControlled-DifferentPayload409-01
QA/Suscripciones-PaymentIntent-Endpoint-FunctionalControlled-ExistingPaymentIntent409-01
QA/Suscripciones-PaymentIntent-Endpoint-FunctionalControlled-MissingIdempotencyKey422-01
QA/Suscripciones-PaymentIntent-Endpoint-FunctionalControlled-NoSessionAuth403-01
```

Cada microfase de ejecucion debe repetir precondicion Git, capturar counts antes/despues, ejecutar solo el POST autorizado por su caso y no hacer SQL write manual.

---

## Adenda PP-Decisiones 110 - Precedencia de errores en QA funcional payment intent

### A) Hallazgo
Durante la QA funcional del caso `ExistingPaymentIntent409` se observo una respuesta:

- HTTP `409`;
- `error/code = idempotency_key_not_reusable`;
- sin nuevas filas en `subscription_payment_intents`;
- sin filas en `subscription_payment_events`;
- sin cambios en `profile_subscriptions`;
- checkout fixture aun en `pending_payment`.

El caso se produjo al reutilizar una `Idempotency-Key` que ya habia quedado registrada con status `failed`. El error originalmente esperado por el plan para un intento con key nueva era `payment_intent_already_exists`.

### B) Causa y orden real
El orden real del servicio payment intent es:

1. Lookup del checkout intent.
2. Validacion de checkout `pending_payment`.
3. Inicio de idempotencia con `beginPaymentIntent(...)`.
4. Si idempotencia permite continuar, toma lock `payment_intent_create`.
5. Anti-duplicado con `findActiveByCheckoutIntentUuid(...)`.
6. Provider mock/dev.
7. Creacion del payment intent.

Por esta precedencia, si `beginPaymentIntent(...)` rechaza antes, el flujo no llega al lock ni al anti-duplicado que genera `payment_intent_already_exists`.

### C) Regla QA corregida
Para pruebas negativas funcionales del endpoint payment intent:

- Para probar `payment_intent_already_exists`, la QA debe usar una `Idempotency-Key` nueva, fresca y nunca usada previamente.
- Si una key ya fallo y se reutiliza con el mismo `request_hash`, el error correcto es `idempotency_key_not_reusable`.
- Si una key completada se reutiliza con el mismo payload, el resultado correcto es replay idempotente.
- Si una key completada se reutiliza con payload distinto, el resultado correcto es `idempotency_key_reused_with_different_payload`.
- Cada microfase negativa debe reservar una key unica salvo que el objetivo explicito sea probar replay, payload distinto o reuso de key fallida.

### D) Impacto sobre `ExistingPaymentIntent409`
La expectativa corregida queda asi:

- PASS esperado con key verdaderamente nueva sobre checkout que ya tiene payment intent activo: HTTP `409`, `payment_intent_already_exists`, sin duplicar filas.
- PASS esperado al repetir una key ya fallida con el mismo request: HTTP `409`, `idempotency_key_not_reusable`, sin duplicar filas.

El caso `QA/Suscripciones-PaymentIntent-Endpoint-FunctionalControlled-ExistingPaymentIntent409-01` sigue siendo valido si se ejecuta una sola vez con una key fresca. Si se repite con la misma key fallida, debe reclasificarse como verificacion de precedencia de idempotencia, no como prueba primaria del anti-duplicado.

### E) No responsabilidades
Este ajuste documental:

- no cambia backend;
- no cambia contrato runtime;
- no modifica `api/subscriptions/index.php`;
- no modifica servicios, repositorios, idempotencia, lock ni provider mock/dev;
- no modifica DB/schema;
- no limpia filas;
- no crea `subscription_payment_events`;
- no toca `profile_subscriptions`;
- no activa suscripciones;
- no marca payment intent como `paid`;
- no integra provider real.

### F) Siguiente microfase recomendada
Siguiente microfase recomendada:

```text
QA/Suscripciones-PaymentIntent-Endpoint-FunctionalControlled-ExistingPaymentIntent409-RetryFreshKey-01
```

Objetivo futuro:

- Repetir el caso existing payment intent con una `Idempotency-Key` fresca/no usada para confirmar HTTP `409` con `payment_intent_already_exists`.
- Confirmar que `subscription_payment_intents` sigue en `1`.
- Confirmar que `subscription_payment_events` sigue en `0`.
- Confirmar que `profile_subscriptions` no cambia.
- Confirmar que el checkout sigue `pending_payment`.
- No ejecutar SQL write manual, no modificar DB/schema, no limpiar datos y no modificar archivos.

---

## Adenda PP-Decisiones 111 - Cierre QA funcional controlada del endpoint payment-intents

### A) Resultado de cierre
El endpoint `payment-intents` queda validado funcionalmente en modo controlado/dev para el flujo interno con provider mock/dev.

El bloque QA funcional controlada cerro como PASS operativo con estas condiciones:

- `payment_intents=1`;
- `payment_events=0`;
- `profile_subscriptions=3`;
- checkout real `7d4beec3-b62a-40e1-a9f2-9edcc1a83364` sigue en `pending_payment`;
- payment intent unico `85493a1c-4a66-40ec-928a-09cb0eb5d007` sigue en `created`;
- no hay payment intent `paid`;
- no hay duplicados para el checkout real;
- no hay payment intent para el checkout inexistente `00000000-0000-4000-8000-000000000404`.

### B) Payment intent creado
El primer create controlado genero un unico payment intent interno:

- `uuid = 85493a1c-4a66-40ec-928a-09cb0eb5d007`;
- `checkout_intent_uuid = 7d4beec3-b62a-40e1-a9f2-9edcc1a83364`;
- `provider = mxmed_mock`;
- `provider_status = mock_created`;
- `normalized_status = created`;
- `amount_cents = 20000`;
- `currency = MXN`;
- `deleted_at = NULL`.

Ese write no activo suscripcion, no creo `payment_events`, no modifico `profile_subscriptions`, no marco `paid`, no ejecuto webhook, no integro provider real y no facturo.

### C) Pruebas funcionales cerradas
Quedan aprobadas las pruebas funcionales controladas principales:

- Positive201 recuperada por StateRecovery: existe un unico payment intent correcto con provider mock/dev.
- Same key different payload: HTTP `409`, `idempotency_key_reused_with_different_payload`, sin duplicados.
- Existing payment intent con RetryFreshKey: HTTP `409`, `payment_intent_already_exists`, reconciliado con la precedencia documentada en PP-Decisiones 110.
- Missing Idempotency-Key: HTTP `422`, `idempotency_key_invalid`, sin nuevas filas.
- Sin sesion/cookie valida: HTTP `403`, `forbidden`, `auth_mode = local_dev_open`, sin nuevas filas.
- Checkout inexistente: HTTP `404`, `checkout_intent_not_found`, sin nuevas filas.

### D) Estado DB de cierre
El estado de cierre observado queda:

- `subscription_payment_intents = 1`;
- `subscription_payment_events = 0`;
- `profile_subscriptions = 3`;
- `subscription_write_idempotency_keys = 11`;
- `payment_intents_for_real_checkout = 1`;
- `payment_intents_for_missing_checkout = 0`;
- `paid_payment_intents_total = 0`;
- checkout real sigue `pending_payment`;
- payment intent unico sigue `created`.

La idempotencia reciente observada para `subscriptions.payment_intent.create` incluye:

- id `22`, `failed`, HTTP `409`;
- id `19`, `failed`, HTTP `409`;
- id `15`, `completed`, HTTP `201`.

### E) Limites confirmados
El bloque QA confirma que el flujo payment intent todavia:

- no activa suscripción;
- no crea payment_events;
- no toca profile_subscriptions;
- no marca payment intent como `paid`;
- no webhook;
- no provider real;
- no facturación real;
- no crea `subscription_payment_events`;
- no modifica DB/schema.

### F) Siguiente bloque recomendado
El siguiente bloque futuro recomendado es planificar/readiness para confirmacion controlada de pago mock/dev o diseno de `payment_events`.

Esta adenda no implementa ese bloque futuro, no ejecuta HTTP/POST, no hace SQL write, no modifica backend, no modifica DB/schema y no crea filas.

---

## Adenda PP-Decisiones 112 - Readiness para confirmación mock/dev de payment intent

### A) Estado actual heredado de PP-Decisiones 111
El bloque de create del endpoint `payment-intents` queda cerrado como PASS funcional controlado.

Estado observado y heredado:

- existe un payment intent interno `85493a1c-4a66-40ec-928a-09cb0eb5d007`;
- pertenece al checkout `7d4beec3-b62a-40e1-a9f2-9edcc1a83364`;
- `provider = mxmed_mock`;
- `provider_status = mock_created`;
- `normalized_status = created`;
- `amount_cents = 20000`;
- `currency = MXN`;
- `deleted_at = NULL`;
- checkout sigue `pending_payment`;
- `subscription_payment_events = 0`;
- `profile_subscriptions = 3`;
- no hay payment intent `paid`;
- no hay activacion de suscripcion.

El codigo actual ya cubre create de payment intent con `CreateSubscriptionPaymentIntentService`, `SubscriptionPaymentIntentMockProvider`, `SubscriptionPaymentIntentRepository`, idempotencia `subscriptions.payment_intent.create` y lock `payment_intent_create`. Ese bloque no confirma pago.

### B) Brecha funcional: que falta para confirmar pago
Todavia no existe un bloque de confirmacion mock/dev de payment intent.

No existe todavia:

- endpoint de confirmacion mock/dev;
- servicio de confirmacion mock/dev;
- operation de idempotencia `subscriptions.payment_intent.confirm_mock`;
- lock separado `payment_intent_confirm`;
- transicion controlada de `created` o `pending_provider` a `paid`;
- insercion de `subscription_payment_events`;
- activacion de `profile_subscriptions`;
- webhook;
- provider real;
- facturacion real.

La confirmacion mock/dev debe ser un bloque separado del create de payment intent. No debe mezclar create payment intent con confirmacion.

### C) Alcance futuro recomendado para confirmacion mock/dev
La confirmacion mock/dev futura debe aceptar solo payment intents existentes, activos y no eliminados.

Alcance seguro recomendado:

- recibir un `payment_intent_uuid` existente;
- validar que pertenece a un checkout vigente;
- validar que el checkout sigue `pending_payment`;
- validar que el payment intent esta en estado inicial permitido, por ejemplo `created` o `pending_provider`;
- rechazar payment intent inexistente;
- rechazar payment intent eliminado;
- rechazar payment intent ya `paid`;
- rechazar payment intent con checkout no `pending_payment`, salvo decision futura documentada;
- mantener provider `mxmed_mock` como unico provider permitido en DEV/local;
- registrar trazabilidad clara del provider mock/dev;
- crear `subscription_payment_events` solo dentro del bloque de confirmacion;
- dejar activacion de `profile_subscriptions` para una decision explicita de transaccion futura o servicio de activacion posterior.

La confirmacion mock/dev NO es provider real, NO es webhook y NO es facturacion real.

### D) Idempotencia y lock recomendados
La confirmacion mock/dev debe usar idempotencia separada de create:

```text
subscriptions.payment_intent.confirm_mock
```

El lock futuro recomendado es separado de create:

```text
payment_intent_confirm
```

El lock debe proteger el intervalo entre:

1. lookup del payment intent;
2. validacion de estado;
3. insercion de `subscription_payment_events`;
4. cambio de estado a `paid`, si se autoriza;
5. activacion posterior, si se decide incluirla en la misma unidad atomica futura.

La idempotencia debe permitir replay estable de una confirmacion ya completada y debe bloquear payload distinto con la misma key.

### E) Validaciones minimas futuras
La confirmacion mock/dev futura debe validar como minimo:

- `payment_intent_uuid` requerido y valido;
- payment intent existe;
- payment intent no esta eliminado;
- `provider = mxmed_mock`;
- estado actual permitido: `created` o `pending_provider`;
- estado actual no debe ser `paid`;
- checkout asociado existe y sigue `pending_payment`;
- monto y moneda coinciden con el payment intent y checkout;
- no existe evento confirmado duplicado para el mismo provider/payment intent;
- `Idempotency-Key` presente y valida;
- request se ejecuta solo en ambiente DEV/local o bajo guardas explicitas de QA.

Debe fallar sin crear filas si cualquiera de esas validaciones falla.

### F) Writes futuros permitidos solo bajo confirmacion
Los writes que deben ocurrir solo cuando se confirme pago son:

- crear `subscription_payment_events` con evento mock/dev confirmado;
- actualizar o registrar estado de payment intent como `paid`, si la microfase futura lo autoriza;
- ejecutar activacion de `profile_subscriptions` solo despues de confirmacion, idealmente en la misma transaccion futura o en un servicio explicito documentado;
- registrar auditoria de confirmacion mock/dev.

No se debe marcar `paid` sin evento/confirmacion controlada documentada. No se debe activar suscripcion solo por create de payment intent.

### G) Prohibiciones explicitas
Esta readiness mantiene fuera de alcance:

- no implementar endpoint en esta microfase;
- no crear servicio;
- no modificar repositorios;
- no ejecutar HTTP/POST;
- no ejecutar SQL write;
- no modificar DB/schema;
- no crear `subscription_payment_events`;
- no tocar `profile_subscriptions`;
- no marcar `paid`;
- no activar suscripcion;
- no webhook;
- no provider real;
- no facturación real;
- no crear fixtures;
- no hacer commit;
- no hacer push.

### H) Riesgos y decisiones pendientes
Riesgos y decisiones antes de implementar confirmacion mock/dev:

- definir si la confirmacion cambia `subscription_payment_intents.normalized_status` a `paid` en la misma transaccion que crea `subscription_payment_events`;
- definir si la activacion de `profile_subscriptions` ocurre en la misma transaccion de confirmacion o en microfase posterior;
- definir estructura exacta de evento mock/dev y deduplicacion por `event_hash` o provider event id;
- definir operation y respuesta estable de `subscriptions.payment_intent.confirm_mock`;
- extender lock service con `payment_intent_confirm` sin romper `payment_intent_create`;
- asegurar que el checkout `pending_payment` no se cierre ni active antes de confirmar pago;
- mantener separacion estricta entre mock/dev, provider real, webhook y facturacion real.

### I) Siguiente microfase recomendada
Siguiente microfase recomendada:

```text
BE/SPEC-Suscripciones-PaymentIntent-ConfirmationMock-Plan-01
```

Objetivo:

- planificar el contrato tecnico de la confirmacion mock/dev de payment intent, incluyendo endpoint futuro, servicio futuro, `subscription_payment_events`, idempotencia `subscriptions.payment_intent.confirm_mock`, lock `payment_intent_confirm`, transicion a `paid` y limites de activacion, sin implementar codigo todavia.

---

## Adenda PP-Decisiones 113 - Plan técnico de confirmación mock/dev de payment intent

### A) Propósito
Esta adenda documenta el plan técnico futuro para implementar la confirmación controlada mock/dev de un payment intent existente.

No implementa código, no crea endpoint, no crea servicio, no ejecuta HTTP/POST, no ejecuta SQL write, no modifica DB/schema, no crea `subscription_payment_events`, no toca `profile_subscriptions` y no marca `paid`.

La confirmación mock/dev queda separada del create de payment intent ya cerrado. No debe repetir create payment intent ni mezclar la creación con la confirmación.

### B) Estado base confirmado
El estado heredado de PP-Decisiones 111 y PP-Decisiones 112 es:

- existe payment intent `85493a1c-4a66-40ec-928a-09cb0eb5d007`;
- pertenece al checkout `7d4beec3-b62a-40e1-a9f2-9edcc1a83364`;
- `provider = mxmed_mock`;
- `provider_status = mock_created`;
- `normalized_status = created`;
- `amount_cents = 20000`;
- `currency = MXN`;
- `deleted_at = NULL`;
- checkout sigue `pending_payment`;
- `subscription_payment_events = 0`;
- `profile_subscriptions = 3`;
- no existe payment intent `paid`;
- no hay webhook, no provider real y no facturación real.

El bloque actual disponible crea payment intents internos con provider mock/dev. Todavía no confirma pago.

### C) Endpoint futuro recomendado
Endpoint futuro recomendado:

```text
POST /api/subscriptions/index.php/entities/{entity_type}/{entity_id}/checkout-intents/{checkout_intent_uuid}/payment-intents/{payment_intent_uuid}/confirm-mock
```

El endpoint debe aceptar sólo confirmación mock/dev sobre un payment intent existente. Debe validar sesión, entidad, checkout, payment intent e idempotencia antes de ejecutar cualquier write.

No implementar endpoint en esta microfase.

### D) Servicio futuro recomendado
Servicio futuro recomendado:

```text
ConfirmSubscriptionPaymentIntentMockService::confirmMock(array $input): array
```

Responsabilidad conceptual:

- validar input mínimo;
- validar sesión/escritura con `session_scope`;
- validar `entity_type` y `entity_id`;
- validar `checkout_intent_uuid`;
- validar `payment_intent_uuid`;
- validar `Idempotency-Key`;
- validar provider permitido `mxmed_mock`;
- validar payment intent existente, activo y no eliminado;
- validar pertenencia payment intent -> checkout;
- validar pertenencia checkout -> entidad solicitada;
- validar checkout `pending_payment`;
- validar estado actual `created` o `pending_provider`;
- rechazar `paid`;
- ejecutar confirmación mock/dev dentro de la unidad transaccional futura;
- devolver respuesta estable e idempotente.

No crear servicio en esta microfase.

### E) Idempotencia futura
Operation futura recomendada:

```text
subscriptions.payment_intent.confirm_mock
```

Reglas mínimas:

- `Idempotency-Key` requerida;
- replay estable cuando una confirmación ya completada se repite con el mismo payload;
- rechazo de payload distinto con `idempotency_key_reused_with_different_payload`;
- protección contra request concurrente con `request_already_processing`;
- respuesta persistida suficiente para replay sin crear eventos duplicados;
- scope ligado a entidad, checkout y payment intent.

La idempotencia de confirmación debe ser independiente de `subscriptions.payment_intent.create`.

### F) Lock futuro
Lock futuro:

```text
payment_intent_confirm
```

Scope recomendado:

```text
mxmed:subscriptions:payment_intents:{payment_intent_uuid}:payment_intent_confirm
```

El lock debe cubrir:

1. lookup del payment intent;
2. validación de estado;
3. validación de checkout `pending_payment`;
4. inserción de `subscription_payment_events`;
5. cambio a `paid`, si se autoriza;
6. activación posterior, si una decisión futura la incluye.

El lock de confirmación no debe reutilizar `payment_intent_create`.

### G) Validaciones futuras mínimas
La implementación futura debe validar:

- sesión/escritura con `session_scope`;
- `entity_type` y `entity_id`;
- `checkout_intent_uuid`;
- `payment_intent_uuid`;
- `Idempotency-Key`;
- provider permitido `mxmed_mock`;
- payment intent existente y `deleted_at = NULL`;
- payment intent pertenece al checkout;
- checkout pertenece a la entidad solicitada;
- checkout status `pending_payment`;
- payment intent status `created` o `pending_provider`;
- payment intent no está `paid`;
- no existe evento confirmado duplicado;
- request no reutiliza idempotency key con payload distinto.

Debe rechazar:

- `payment_intent_not_found`;
- `checkout_intent_not_found`;
- `payment_intent_checkout_mismatch`;
- `payment_intent_provider_invalid`;
- `checkout_intent_not_pending_payment`;
- `payment_intent_already_paid`;
- `payment_intent_not_confirmable`.

### H) Writes futuros bajo confirmación mock/dev
Writes futuros permitidos sólo bajo confirmación mock/dev:

- insertar exactamente un `subscription_payment_events` de tipo/estado mock confirmado;
- actualizar `subscription_payment_intents.normalized_status` a `paid` sólo si la microfase futura lo autoriza;
- registrar `provider_status = mock_paid` o equivalente dev;
- registrar `paid_at` si la columna existe y el contrato futuro lo confirma;
- persistir respuesta idempotente para replay;
- opcionalmente cerrar o actualizar checkout intent sólo si se documenta en microfase separada.

No activar suscripción automáticamente sin decisión explícita. No crear `profile_subscriptions` sin confirmación y decisión separada.

### I) Errores futuros recomendados
Errores conceptuales mínimos:

- `payment_intent_not_found`;
- `payment_intent_not_confirmable`;
- `payment_intent_already_paid`;
- `checkout_intent_not_found`;
- `checkout_intent_not_pending_payment`;
- `payment_intent_checkout_mismatch`;
- `payment_intent_provider_invalid`;
- `idempotency_key_invalid`;
- `idempotency_key_reused_with_different_payload`;
- `request_already_processing`;
- `payment_intent_confirm_lock_timeout`;
- `payment_intent_confirm_unavailable`.

### J) HTTP status recomendado
Status HTTP recomendado:

- `200` para replay idempotente completado;
- `201` o `200` para confirmación mock/dev exitosa, a definir en microfase futura;
- `404` para `payment_intent_not_found` o `checkout_intent_not_found`;
- `409` para `payment_intent_already_paid`, `payment_intent_checkout_mismatch`, `checkout_intent_not_pending_payment`, payload distinto o request processing;
- `422` para payload inválido o `Idempotency-Key` faltante/inválida;
- `403` para auth insuficiente.

### K) Prohibiciones explícitas
Este plan mantiene fuera de alcance:

- no provider real;
- no webhook;
- no facturación real;
- no activar suscripción automáticamente;
- no crear `profile_subscriptions` sin confirmación y decisión separada;
- no marcar `paid` sin `subscription_payment_events` si el diseño futuro exige ledger;
- no mezclar create payment intent con confirmación;
- no repetir create payment intent;
- no implementar endpoint en esta microfase;
- no crear servicio en esta microfase;
- no modificar PHP;
- no ejecutar HTTP/POST;
- no ejecutar SQL write;
- no modificar DB/schema;
- no hacer commit;
- no hacer push.

### L) Decisión de siguiente microfase
Siguiente microfase recomendada principal:

```text
BE/SPEC-Suscripciones-PaymentIntent-ConfirmationMock-Idempotency-Readiness-01
```

Justificación:

- antes de implementar `ConfirmSubscriptionPaymentIntentMockService` conviene validar si la infraestructura actual de idempotencia puede aceptar `subscriptions.payment_intent.confirm_mock`, replay estable, payload hash, estado failed/completed y precedencia de errores sin afectar `subscriptions.payment_intent.create`;
- la confirmación mock/dev escribirá `subscription_payment_events` y posiblemente marcará `paid`, por lo que la idempotencia debe quedar cerrada antes del servicio;
- el servicio readiness puede venir después, una vez confirmada la dependencia de idempotencia.

Microfase alternativa no inmediata:

```text
BE/SPEC-Suscripciones-PaymentIntent-ConfirmationMock-Service-Readiness-01
```

Queda pospuesta hasta cerrar readiness de idempotencia de confirmación.

---

## Adenda PP-Decisiones 114 - Readiness de idempotencia confirm_mock de payment intent

### A) Estado actual de idempotencia payment_intent.create
La idempotencia actual ya soporta el create interno de payment intent con:

- operation `subscriptions.payment_intent.create`;
- wrapper `beginPaymentIntent(...)`;
- builder `buildPaymentIntentRequestHash(...)`;
- completion `markPaymentIntentCompleted(...)`;
- fail reusable `markOperationFailed(...)`;
- replay estable desde `response_body_text` cuando el registro queda `completed`;
- rechazo por `idempotency_key_invalid`;
- rechazo por `idempotency_key_reused_with_different_payload`;
- rechazo por `request_already_processing`;
- rechazo por `idempotency_result_unavailable`;
- rechazo de keys fallidas/no reutilizables con `idempotency_key_not_reusable`.

Ese soporte está acotado al create de payment intent. No debe reutilizarse semanticamente para confirmación mock/dev.

### B) Brecha para subscriptions.payment_intent.confirm_mock
Todavía no existe soporte explícito para:

```text
subscriptions.payment_intent.confirm_mock
```

Brechas identificadas antes de implementar confirmación:

- falta constante de operación para `subscriptions.payment_intent.confirm_mock`;
- falta permitir esa operation en el allowlist de idempotencia;
- falta wrapper futuro `beginPaymentIntentConfirmMock(...)`;
- falta builder `buildPaymentIntentConfirmMockRequestHash(...)`;
- falta completion dedicado `markPaymentIntentConfirmMockCompleted(...)`;
- falta decisión de replay y response body para confirmación;
- falta asegurar que una misma key no pueda confirmar otro `payment_intent_uuid`;
- falta documentar precedencia final entre lookup, begin idempotency, lock `payment_intent_confirm` y writes.

La idempotencia debe proteger la confirmación, no la creación. No debe reutilizar `subscriptions.payment_intent.create`.

### C) Operación y request_hash futuro
Operation futura:

```text
subscriptions.payment_intent.confirm_mock
```

El `request_hash` futuro debe ser estable y derivarse sólo de campos controlados. Debe incluir como mínimo:

- `operation = subscriptions.payment_intent.confirm_mock`;
- `entity_type`;
- `entity_id`;
- `user_id` si el patrón actual lo conserva en scope;
- `checkout_intent_uuid`;
- `payment_intent_uuid`;
- `provider = mxmed_mock`;
- `action = confirm_mock`;
- `source` sólo si la implementación futura decide aceptarlo como parte estable del contrato;
- `notes` sólo si se documenta que afecta la idempotencia; recomendación inicial: no incluir notes libres en el hash.

El builder recomendado es:

```text
buildPaymentIntentConfirmMockRequestHash(...)
```

Ese hash debe impedir:

- confirmar otro `payment_intent_uuid` con la misma key;
- confirmar otro `checkout_intent_uuid` con la misma key;
- cambiar provider distinto a `mxmed_mock`;
- cambiar action distinta de `confirm_mock`;
- repetir la key con payload distinto.

### D) Precedencia recomendada de validaciones
Se recomienda esta precedencia principal:

1. Validar sintaxis mínima de input y `Idempotency-Key`.
2. Validar sesión/escritura y scope base.
3. Hacer lookup básico de `checkout_intent_uuid` y `payment_intent_uuid` antes de `beginPaymentIntentConfirmMock(...)`.
4. Rechazar inexistentes sin crear idempotency key cuando el checkout o payment intent no existen.
5. Construir scope canónico con entidad, checkout y payment intent.
6. Ejecutar `beginPaymentIntentConfirmMock(...)`.
7. Resolver replay `completed` si aplica.
8. Rechazar misma key con payload distinto.
9. Rechazar `processing` con `request_already_processing`.
10. Tomar lock `payment_intent_confirm`.
11. Validar estado profundo bajo lock: provider `mxmed_mock`, checkout `pending_payment`, payment intent `created` o `pending_provider`, no `paid`.
12. Ejecutar writes futuros autorizados.
13. Marcar idempotencia completed con `markPaymentIntentConfirmMockCompleted(...)`.
14. En error posterior al begin, usar `markOperationFailed(...)` si aplica.

Justificación:

- evita llenar `subscription_write_idempotency_keys` con intentos sobre IDs inexistentes;
- conserva trazabilidad de requests válidos una vez identificado el recurso;
- mantiene lock después de begin idempotency y antes de cualquier write;
- preserva la precedencia ya observada para payload distinto y keys fallidas.

### E) Métodos futuros recomendados
Métodos futuros recomendados en idempotencia:

```text
beginPaymentIntentConfirmMock(...)
buildPaymentIntentConfirmMockRequestHash(...)
markPaymentIntentConfirmMockCompleted(...)
markOperationFailed(...)
```

`markOperationFailed(...)` ya existe como reutilizable y puede conservarse si la operación futura se integra en el dispatcher de operaciones. La completion dedicada debe persistir response body suficiente para replay estable, incluyendo `payment_intent_uuid`, `checkout_intent_uuid`, estado resultante y metadatos de evento si en una microfase futura se crea `subscription_payment_events`.

### F) Errores y replay esperados
La operación futura debe conservar y reutilizar estos errores:

- `idempotency_key_invalid`;
- `idempotency_key_reused_with_different_payload`;
- `idempotency_key_not_reusable`;
- `request_already_processing`;
- `idempotency_result_unavailable`.

Comportamiento esperado:

- mismo `Idempotency-Key` y mismo `request_hash` con status `completed`: replay estable;
- mismo key con `request_hash` distinto: `idempotency_key_reused_with_different_payload`;
- mismo key con status `processing`: `request_already_processing`;
- mismo key con status `failed`: `idempotency_key_not_reusable` si se mantiene el contrato actual;
- completed sin response persistida: `idempotency_result_unavailable`.

La idempotencia no debe crear `subscription_payment_events`, no debe marcar `paid` y no debe tocar `profile_subscriptions`; sólo debe coordinar seguridad de repetición y replay.

### G) Prohibiciones de esta microfase
Esta microfase mantiene fuera de alcance:

- no implementar código en esta microfase;
- no modificar PHP;
- no agregar métodos a `SubscriptionWriteIdempotencyService`;
- no crear endpoint;
- no crear servicio;
- no modificar repositorios;
- no ejecutar HTTP/POST;
- no ejecutar SQL write;
- no modificar DB/schema;
- no crear `subscription_payment_events`;
- no tocar `profile_subscriptions`;
- no marcar `paid`;
- no activar suscripción;
- no provider real;
- no webhook;
- no facturación real;
- no hacer commit;
- no hacer push.

### H) Riesgos / decisiones pendientes
Riesgos y decisiones antes de implementar:

- decidir si `source` participa en `buildPaymentIntentConfirmMockRequestHash(...)`;
- decidir si `notes` queda excluido del hash para evitar falsos mismatches por texto libre;
- confirmar que `subscriptions.payment_intent.confirm_mock` requiera stored response obligatoria para replay;
- extender allowlist sin romper `subscriptions.payment_intent.create`;
- decidir si `markPaymentIntentConfirmMockCompleted(...)` reutiliza `markPaymentIntentCompleted(...)` o queda dedicado;
- asegurar que el error de failed key se mantiene como `idempotency_key_not_reusable`;
- coordinar precedencia con `payment_intent_confirm` para no crear eventos duplicados;
- validar en microfase posterior si el lock service ya soporta `payment_intent_confirm`.

### I) Siguiente microfase recomendada
Siguiente microfase recomendada:

```text
BE/SPEC-Suscripciones-PaymentIntent-ConfirmationMock-Lock-Readiness-01
```

Objetivo:

- validar documentalmente readiness del lock `payment_intent_confirm`, scope `mxmed:subscriptions:payment_intents:{payment_intent_uuid}:payment_intent_confirm`, errores de timeout y compatibilidad con el lock actual `payment_intent_create`, sin implementar código todavía.

La implementación de idempotencia `subscriptions.payment_intent.confirm_mock` debe quedar para una microfase posterior, después de cerrar la readiness del lock de confirmación.

---

## Adenda PP-Decisiones 115 - Readiness de lock confirm_mock de payment intent

### A) Estado actual del lock payment_intent_create
El lock actual para create de payment intent ya existe con:

- operación `payment_intent_create`;
- wrapper `acquirePaymentIntentCreate(...)`;
- builder `buildPaymentIntentCreateLockName(...)`;
- uso de `GET_LOCK`;
- liberación con `release(...)`;
- liberación en `finally` dentro del servicio de create;
- timeout inicial observado de `2` segundos;
- scope actual por `checkout_intent_uuid`;
- fallback corto cuando el nombre excede el límite permitido.

Ese lock protege la creación inicial de un payment intent por checkout. No confirma pago, no crea `subscription_payment_events`, no marca `paid` y no toca `profile_subscriptions`.

### B) Brecha para payment_intent_confirm
Todavía no existe soporte de lock explícito para:

```text
payment_intent_confirm
```

Brechas antes de implementar confirmación mock/dev:

- falta constante futura para `payment_intent_confirm`;
- falta permitir esa operación si se integra al allowlist general;
- falta wrapper `acquirePaymentIntentConfirm(...)`;
- falta builder `buildPaymentIntentConfirmLockName(...)` si se conserva el patrón actual;
- falta scope por `payment_intent_uuid`;
- falta error específico `payment_intent_confirm_lock_timeout`;
- falta error general `payment_intent_confirm_unavailable`;
- falta decidir longitud/fallback hash del lock name;
- falta validar el orden final con `beginPaymentIntentConfirmMock(...)`.

`confirm_mock` necesita lock separado de `payment_intent_create` porque protege una zona crítica distinta: confirmar un pago existente, no crear uno nuevo.

### C) Scope futuro recomendado
Scope recomendado:

```text
mxmed:subscriptions:payment_intents:{payment_intent_uuid}:payment_intent_confirm
```

Justificación:

- la unidad de concurrencia de confirmación es el `payment_intent_uuid`;
- dos requests con Idempotency-Key distintas no deben confirmar el mismo payment intent en paralelo;
- el lock por payment intent evita duplicar `subscription_payment_events`;
- el lock por payment intent evita doble transición a `paid`;
- el checkout puede tener relaciones de lectura, pero el recurso que se confirma es el payment intent.

El lock futuro debe seguir validando caracteres seguros y aplicar fallback hash si el nombre excede la longitud máxima.

### D) Orden recomendado idempotencia / lock / writes
Orden recomendado:

1. Validar input mínimo.
2. Validar sesión y scope.
3. Lookup básico de `checkout_intent_uuid` y `payment_intent_uuid`.
4. Ejecutar `beginPaymentIntentConfirmMock(...)` para `subscriptions.payment_intent.confirm_mock`.
5. Resolver replay o rechazo idempotente antes de tomar lock.
6. Tomar lock `payment_intent_confirm`.
7. Revalidar estado profundo bajo lock.
8. Ejecutar writes futuros autorizados.
9. Marcar idempotencia completed.
10. Liberar lock siempre en `finally`.

`request_already_processing` queda como error de idempotencia previo, no como error de lock.

### E) Zona crítica protegida
El lock debe proteger el intervalo entre:

- validación profunda bajo lock;
- verificación de que el payment intent existe y no está eliminado;
- verificación de que el checkout asociado existe;
- verificación de que el checkout sigue `pending_payment`;
- verificación de `provider = mxmed_mock`;
- verificación de `normalized_status = created` o `pending_provider`;
- verificación de que `normalized_status` no es `paid`;
- verificación futura de que no hay evento confirmatorio duplicado;
- creación futura de `subscription_payment_events`;
- transición futura a `paid`, si se autoriza;
- actualización futura de `provider_status` y `paid_at`, si se autoriza.

El lock debe evitar:

- doble confirmación concurrente del mismo payment intent;
- duplicación de `subscription_payment_events`;
- carreras entre dos requests con Idempotency-Key distintas;
- transición doble o inconsistente a `paid`.

El lock no crea eventos por sí mismo, no marca `paid`, no toca `profile_subscriptions` y no activa suscripción.

### F) Métodos futuros recomendados
Métodos futuros recomendados:

```text
acquirePaymentIntentConfirm(...)
buildPaymentIntentConfirmLockName(...)
release(...)
```

`release(...)` ya existe y debe seguir usándose en `finally` o en un mecanismo equivalente que garantice liberación del lock incluso si hay error durante los writes futuros.

Timeout recomendado inicial: `2` segundos, salvo decisión futura.

### G) Errores futuros recomendados
Errores futuros de lock:

- `payment_intent_confirm_lock_timeout`;
- `payment_intent_confirm_unavailable`.

Error relacionado, pero no de lock:

- `request_already_processing`, sólo como error de idempotencia previo.

La capa del servicio futuro deberá mapear timeout de lock a `409`, salvo decisión posterior.

### H) Prohibiciones de esta microfase
Esta microfase mantiene fuera de alcance:

- no implementar código en esta microfase;
- no modificar PHP;
- no agregar métodos a `SubscriptionEntityWriteLockService`;
- no crear endpoint;
- no crear servicio;
- no modificar repositorios;
- no ejecutar HTTP/POST;
- no ejecutar SQL write;
- no modificar DB/schema;
- no crear `subscription_payment_events`;
- no tocar `profile_subscriptions`;
- no marcar `paid`;
- no activar suscripción;
- no provider real;
- no webhook;
- no facturación real;
- no hacer commit;
- no hacer push.

### I) Riesgos / decisiones pendientes
Riesgos y decisiones antes de implementar:

- confirmar si `payment_intent_confirm` debe entrar en el allowlist de `buildLockName(...)` o usar sólo builder dedicado;
- definir fallback hash para `mxmed:subscriptions:payment_intents:{payment_intent_uuid}:payment_intent_confirm`;
- decidir si el timeout inicial de `2` segundos se conserva;
- confirmar error final para timeout como `payment_intent_confirm_lock_timeout`;
- confirmar error general como `payment_intent_confirm_unavailable`;
- asegurar compatibilidad con `payment_intent_create`;
- mantener `acquirePaymentIntentCreate(...)` sin cambios;
- coordinar lock con idempotencia `subscriptions.payment_intent.confirm_mock`;
- validar posteriormente si hace falta consulta anti-duplicado para `subscription_payment_events`.

### J) Siguiente microfase recomendada
Siguiente microfase recomendada:

```text
BE/Suscripciones-PaymentIntent-ConfirmationMock-IdempotencyOperation-01
```

Objetivo:

- implementar soporte mínimo de idempotencia para `subscriptions.payment_intent.confirm_mock`, incluyendo operación permitida, `beginPaymentIntentConfirmMock(...)`, `buildPaymentIntentConfirmMockRequestHash(...)` y `markPaymentIntentConfirmMockCompleted(...)`, sin endpoint, sin servicio de confirmación, sin SQL, sin HTTP/POST y sin writes de pago.

La implementación del lock `payment_intent_confirm` debe quedar para la microfase posterior, después de cerrar la operación de idempotencia.

---

## Adenda PP-Decisiones 116 - Plan de fix replay idempotente confirm_mock

### A) Estado actual confirmado
La QA funcional de confirmación mock/dev de payment intent confirmó el siguiente estado:

- `confirm_mock` positivo funcionó con la Idempotency-Key `mxmed-confirm-mock-qa-20260626-002`;
- el payment intent `85493a1c-4a66-40ec-928a-09cb0eb5d007` quedó en `paid` / `mock_paid`;
- se creó exactamente 1 registro en `subscription_payment_events`;
- el evento creado es `86c29828-4537-4402-93cb-28d0947e81a7`, con `event_type = payment_intent_confirm`;
- `profile_subscriptions` sigue intacto en `3`;
- el replay con la misma key y el mismo payload devolvió HTTP `409` con `payment_intent_already_paid`;
- la idempotencia `confirm_mock` reciente conserva `id=26`, `completed`, `response_http_status=200`;
- el intento previo fallido conserva `id=24`, `failed`, `response_http_status=500`;
- no hubo duplicados de payment intent ni de payment event;
- no se activó suscripción;
- no se tocó `profile_subscriptions`.

Observación de schema real: la tabla `subscription_write_idempotency_keys` no tiene columnas `error_code` ni `error_message`. Las consultas SQL futuras sobre esa tabla no deben asumir esas columnas salvo que una microfase previa confirme explícitamente que existen.

### B) Bug confirmado
La idempotencia completada existe para `subscriptions.payment_intent.confirm_mock`, la response cacheada existe y corresponde a HTTP `200`.

El bug confirmado es de precedencia:

- `ConfirmSubscriptionPaymentIntentMockService::assertConfirmable()` valida `payment_intent_already_paid` antes de resolver el replay cacheado de `beginPaymentIntentConfirmMock(...)`;
- el camino de replay existe en `SubscriptionWriteIdempotencyService`;
- ese camino no se alcanza cuando el payment intent ya está `paid`;
- el resultado incumple el contrato esperado de replay idempotente para una key completada con el mismo `request_hash`.

### C) Regla técnica deseada
La validación de existencia y scope básico puede ocurrir antes de abrir idempotencia:

- validar input mínimo;
- localizar payment intent;
- localizar checkout intent;
- construir scope estable;
- calcular payload canónico.

Después de construir scope y request hash, `beginPaymentIntentConfirmMock(...)` debe ocurrir antes de guards terminales como:

- `payment_intent_already_paid`;
- evento `confirm_mock` ya existente;
- cualquier validación de estado que bloquee un replay completado.

Reglas esperadas:

- si `beginPaymentIntentConfirmMock(...)` devuelve replay completed con el mismo `request_hash`, el servicio debe retornar la response cacheada sin entrar a zona crítica, sin lock y sin crear eventos nuevos;
- si la key es nueva y el payment intent ya está `paid`, debe seguir devolviendo `payment_intent_already_paid`;
- si la key completada se reutiliza con payload distinto, debe seguir devolviendo `idempotency_key_reused_with_different_payload`;
- si la key está en `processing`, debe seguir devolviendo `request_already_processing`;
- si la key completada no tiene response utilizable, debe seguir devolviendo `idempotency_result_unavailable`.

### D) Fix recomendado
Fix recomendado para una microfase BE posterior:

1. Reordenar `ConfirmSubscriptionPaymentIntentMockService::confirmMock(...)`.
2. Mantener validación de input básico al inicio.
3. Mantener lookup básico de payment intent y checkout intent para construir scope.
4. Construir el payload idempotente con:
   - `checkout_intent_uuid`;
   - `payment_intent_uuid`;
   - `provider`;
   - `source`.
5. Ejecutar `beginPaymentIntentConfirmMock(...)` antes del guard `payment_intent_already_paid`.
6. Si la decisión es replay, retornar inmediatamente la response cacheada.
7. Si la decisión es reject, devolver el error idempotente correspondiente.
8. Sólo si la idempotencia permite continuar:
   - validar estado confirmable;
   - adquirir lock `payment_intent_confirm`;
   - reconsultar estado bajo lock;
   - validar anti-duplicado de evento;
   - abrir transacción;
   - crear `subscription_payment_events`;
   - marcar payment intent como `paid` / `mock_paid`;
   - marcar idempotencia completed;
   - liberar lock en `finally`.

El fix no debe cambiar schema, no debe cambiar endpoint público, no debe tocar `profile_subscriptions`, no debe activar suscripción y no debe introducir provider real, webhook ni facturación real.

### E) QA posterior recomendada
QA posterior recomendada después del fix:

- repetir replay same key `mxmed-confirm-mock-qa-20260626-002`;
- validar HTTP `200`;
- validar `idempotent_replay=true`;
- validar que `subscription_payment_events` sigue en `1`;
- validar que los eventos para `payment_intent_uuid = 85493a1c-4a66-40ec-928a-09cb0eb5d007` siguen en `1`;
- validar que `profile_subscriptions` sigue en `3`;
- validar que una key nueva contra payment intent ya `paid` devuelve `409 payment_intent_already_paid`;
- validar que payload distinto con key completada devuelve `409 idempotency_key_reused_with_different_payload`;
- validar que no se crean nuevos payment intents;
- validar que no se activa suscripción;
- validar que no se ejecuta provider real, webhook ni facturación real.

En SQL futuro sobre `subscription_write_idempotency_keys`, no consultar `error_code` ni `error_message` salvo que una microfase previa confirme que esas columnas existen en el schema local.

### F) Prohibiciones de esta microfase
Esta microfase mantiene fuera de alcance:

- no modificar PHP;
- no implementar fix;
- no ejecutar `confirm-mock`;
- no ejecutar HTTP/POST;
- no ejecutar curl;
- no ejecutar SQL;
- no modificar DB/schema;
- no crear `subscription_payment_events`;
- no marcar `paid`;
- no tocar `profile_subscriptions`;
- no activar suscripción;
- no provider real;
- no webhook;
- no facturación real;
- no hacer commit;
- no hacer push;
- no hacer stash/reset/restore.

### G) Siguiente microfase recomendada
Siguiente microfase recomendada:

```text
BE/Suscripciones-PaymentIntent-ConfirmationMock-ReplayPrecedence-Fix-01
```

Objetivo:

- reordenar la precedencia de `ConfirmSubscriptionPaymentIntentMockService::confirmMock(...)` para que un replay idempotente completed con mismo request hash retorne la response cacheada antes de aplicar guards terminales como `payment_intent_already_paid`, sin cambiar schema, sin cambiar endpoint, sin tocar `profile_subscriptions`, sin provider real, sin webhook y sin facturación real.

---

## Adenda PP-Decisiones 117 - Cierre QA funcional confirm_mock payment intent

### A) Resumen ejecutivo
La QA funcional controlada del endpoint `confirm-mock` para payment intent queda cerrada con PASS después de los fixes aplicados.

El endpoint ya confirma en modo mock/dev un payment intent existente:

- marca el payment intent como `paid` / `mock_paid`;
- registra exactamente 1 evento en `subscription_payment_events`;
- conserva el checkout en `pending_payment`;
- no activa suscripción;
- no crea ni modifica `profile_subscriptions`;
- no integra provider real;
- no ejecuta webhook;
- no ejecuta facturación real.

La activación post-pago de `profile_subscriptions` queda fuera de este cierre y requiere una microfase futura separada con contrato explícito.

### B) Incidentes detectados y corregidos
Incidente 1: lock name mayor al límite de MySQL `GET_LOCK()`.

- Primer intento `confirm_mock`: HTTP `500`.
- DB quedó segura.
- Idempotencia `confirm_mock`: `id=24`, `failed`, `response_http_status=500`.
- Fix aplicado: `36f0379 fix(suscripciones): acorta lock confirmacion mock payment intent`.
- Patrón nuevo del lock: `mxmed:sub:pi:{hash12}:confirm`.
- Longitud esperada del lock: `33` caracteres.

Incidente 2: replay idempotente bloqueado por `payment_intent_already_paid`.

- Replay con misma Idempotency-Key `mxmed-confirm-mock-qa-20260626-002` antes del fix: HTTP `409 payment_intent_already_paid`.
- DB quedó estable, sin duplicados.
- Causa confirmada: `assertConfirmable(...)` corría antes de resolver el replay idempotente completed.
- Plan documental: `0a933d9 docs(suscripciones): planifica fix replay confirmacion mock`.
- Fix aplicado: `101a03e fix(suscripciones): corrige replay confirmacion mock`.
- Resultado del fix: `beginPaymentIntentConfirmMock(...)` se ejecuta antes del guard `payment_intent_already_paid`, y el replay completed retorna antes de lock, transacción, creación de evento o update.

### C) Matriz QA funcional final
Matriz final validada:

| Caso | Resultado | Estado |
| --- | --- | --- |
| Confirm successful fresh key 002 | HTTP `200`, payment intent `paid` / `mock_paid`, `payment_events_total=1` | PASS |
| Replay same key 002 after fix | HTTP `200`, `meta.idempotent_replay=true`, `payment_events_total` sigue `1` | PASS |
| Fresh key 003 already paid | HTTP `409 payment_intent_already_paid`, `payment_events_total` sigue `1` | PASS |
| Same key 002 different payload | HTTP `409 idempotency_key_reused_with_different_payload`, no devuelve `payment_intent_already_paid`, `payment_events_total` sigue `1` | PASS |

Detalles relevantes:

- Idempotency-Key exitosa: `mxmed-confirm-mock-qa-20260626-002`.
- Idempotency-Key fresh already paid: `mxmed-confirm-mock-qa-20260626-003-already-paid`.
- Payload distinto con key completada bloquea por `idempotency_key_reused_with_different_payload`.
- No se crearon eventos duplicados.
- No se tocó `profile_subscriptions`.
- No se activó suscripción.

### D) Estado DB final
Estado DB final observado:

- `payment_intents_total=1`;
- `payment_events_total=1`;
- `profile_subscriptions_total=3`;
- checkout `7d4beec3-b62a-40e1-a9f2-9edcc1a83364` queda `pending_payment`;
- payment intent `85493a1c-4a66-40ec-928a-09cb0eb5d007` queda `paid` / `mock_paid`;
- `paid_at=2026-06-27 04:33:20`;
- `payment_intents_for_checkout=1`;
- `events_for_payment_intent=1`;
- `paid_payment_intents_total=1`.

Payment event único:

- `id=1`;
- `uuid=86c29828-4537-4402-93cb-28d0947e81a7`;
- `event_type=payment_intent_confirm`;
- `provider=mxmed_mock`;
- `provider_event_id=mxmed_mock_confirm:85493a1c-4a66-40ec-928a-09cb0eb5d007`;
- `amount_cents=20000`;
- `currency=MXN`.

Idempotencia `confirm_mock` reciente:

- `id=28`, `failed`, `response_http_status=409`;
- `id=26`, `completed`, `response_http_status=200`;
- `id=24`, `failed`, `response_http_status=500`.

### E) Decisiones y contratos cerrados
Decisiones cerradas para este alcance:

- `confirm_mock` es exclusivamente mock/dev;
- `confirm_mock` no es provider real;
- `confirm_mock` no es webhook;
- `confirm_mock` no es facturación real;
- `confirm_mock` no activa `profile_subscriptions`;
- `confirm_mock` no crea suscripción activa;
- `profile_subscriptions` permanece en `3`;
- checkout sigue `pending_payment` por diseño actual;
- el payment event funciona como evidencia de confirmación mock/dev;
- la activación post-pago requiere microfase y contrato separados.

La tabla `subscription_write_idempotency_keys` no debe asumirse con columnas `error_code` ni `error_message`. Las consultas futuras deben usar columnas reales observadas, por ejemplo:

- `id`;
- `uuid`;
- `operation`;
- `status`;
- `response_http_status`;
- `entity_type`;
- `entity_id`;
- `created_at`;
- `updated_at`;
- `response_body_json`, si existe y una microfase la requiere.

### F) Prohibiciones del cierre
Este cierre no autoriza:

- activar `profile_subscriptions`;
- crear suscripción activa;
- ejecutar provider real;
- ejecutar webhook;
- ejecutar facturación real;
- modificar DB/schema;
- crear payment events adicionales para el mismo payment intent;
- marcar paid nuevamente;
- limpiar datos de QA;
- cambiar el estado del checkout fuera de una microfase explícita.

### G) Siguiente bloque recomendado
Siguiente bloque recomendado:

```text
BE/SPEC-Suscripciones-PaymentIntent-PostPaymentActivation-Readiness-01
```

Objetivo recomendado:

- diseñar o validar la activación post-pago controlada como microfase separada, definiendo si y cuándo el payment event `payment_intent_confirm` puede activar `profile_subscriptions`, sin mezclar provider real, webhook ni facturación real.

No iniciar activación sin nueva decisión explícita.

---

## Adenda PP-Decisiones 118 - Plan de activación post-pago de suscripción

### A) Estado actual confirmado
El bloque `confirm_mock` de payment intent queda cerrado y no activa suscripción por sí mismo.

Estado funcional observado:

- `confirm_mock` cerrado con PASS;
- payment intent `85493a1c-4a66-40ec-928a-09cb0eb5d007` queda `paid` / `mock_paid`;
- existe exactamente `1` `subscription_payment_events`;
- checkout `7d4beec3-b62a-40e1-a9f2-9edcc1a83364` sigue `pending_payment`;
- `profile_subscriptions_total=3`;
- doctor `900001` tiene `profile_subscriptions=0`;
- doctor `900001` tiene `active_subscription=0`;
- no provider real;
- no webhook;
- no facturación real.

La evidencia de pago mock/dev existe, pero todavía no hay activación contractual operativa.

### B) Decisión de diseño
La activación post-pago debe implementarse como servicio propio y separado.

No se debe reutilizar directamente `CreateSubscriptionWithAcceptanceService`, porque ese servicio mezcla aceptación contractual final, creación de `profile_subscriptions` activa y resolución read-model en una sola transacción. Ese flujo no representa correctamente el camino checkout-first con aceptación `accepted_pending_payment` ya creada antes del pago.

La activación futura debe partir de evidencia ya confirmada:

- `subscription_payment_events` con `event_type=payment_intent_confirm` y `processing_status=processed`;
- payment intent `paid` / `mock_paid`;
- checkout `pending_payment`;
- aceptación contractual relacionada en `accepted_pending_payment`;
- ausencia de suscripción activa para la entidad.

### C) Servicio futuro recomendado
Servicio sugerido:

```text
ActivateSubscriptionAfterPaymentService
```

Método sugerido:

```text
activateAfterPayment(array $input): array
```

Entrada mínima:

- `entity_type`;
- `entity_id`;
- `checkout_intent_uuid`;
- `payment_intent_uuid`;
- `payment_event_uuid`;
- `idempotency_key`;
- actor/contexto técnico autorizado.

Este servicio no debe depender de provider real ni webhook en esta fase. El provider mock/dev ya dejó evidencia suficiente para una activación controlada, siempre que la microfase futura lo autorice explícitamente.

### D) Idempotencia futura
Operación sugerida:

```text
subscriptions.payment_intent.activate_after_payment
```

Scope recomendado:

- `entity_type`;
- `entity_id`;
- `checkout_intent_uuid`;
- `payment_intent_uuid`;
- `payment_event_uuid`.

Reglas:

- misma `Idempotency-Key` + mismo payload debe devolver replay;
- misma `Idempotency-Key` + payload distinto debe devolver `idempotency_key_reused_with_different_payload`;
- key nueva contra suscripción ya activada debe devolver `409` semántico, por ejemplo `subscription_already_activated` o `active_subscription_exists`;
- si el resultado de activación no está disponible, debe mantener error idempotente explícito y no ejecutar activación parcial.

La idempotencia debe completarse sólo después de crear `profile_subscriptions` y cerrar la transición de estado decidida.

### E) Lock futuro
Operación sugerida:

```text
payment_intent_activate_subscription
```

Scope posible:

- por `payment_intent_uuid`: protege directamente la evidencia que gatilla la activación;
- por `checkout_intent_uuid`: protege todo el flujo de checkout y evita doble transición del checkout.

Decisión recomendada inicial:

- usar `checkout_intent_uuid` como scope si la misma transacción también cambia checkout;
- usar `payment_intent_uuid` si la activación se trata como una consecuencia estricta del evento de pago.

Objetivo del lock:

- evitar doble inserción en `profile_subscriptions`;
- evitar doble transición de checkout;
- evitar activación concurrente con keys distintas.

Timeout sugerido: `2` segundos.

### F) Validaciones previas obligatorias
Antes de activar, el servicio futuro debe validar:

- `payment_intent_uuid` existe;
- payment intent tiene `normalized_status=paid`;
- payment intent tiene `provider_status=mock_paid` o estado equivalente aceptado por contrato;
- `payment_event_uuid` existe;
- payment event tiene `event_type=payment_intent_confirm`;
- payment event tiene `processing_status=processed`;
- checkout existe;
- checkout tiene `status=pending_payment`;
- checkout, payment intent y payment event pertenecen al mismo `checkout_intent_uuid`;
- `entity_type` y `entity_id` coinciden con el checkout;
- `contract_acceptance_uuid` existe en el checkout;
- la aceptación relacionada tiene `status=accepted_pending_payment`;
- no existe `active_subscription_exists` para la entidad;
- `amount_cents` y `currency` coinciden entre checkout, payment intent y payment event;
- `plan_code` y `billing_period` son válidos contra catálogo;
- no existe ya una fila activa en `profile_subscriptions` para la entidad.

Si cualquier validación falla, la activación debe abortar sin writes.

### G) Transacción futura
Dentro de una transacción futura:

1. Revalidar payment intent `paid`.
2. Revalidar payment event único/procesado.
3. Revalidar checkout `pending_payment`.
4. Revalidar `active_subscription_exists=false`.
5. Crear exactamente 1 fila en `profile_subscriptions` con snapshot operativo.
6. Opcional y por decisión explícita: cambiar checkout a `paid`, `confirmed` o estado equivalente.
7. Opcional y por decisión explícita: ligar `subscription_id` a la aceptación si el schema lo permite; si no, mantener enlace conceptual por `contract_acceptance_uuid`, `checkout_intent_uuid`, `payment_intent_uuid` y `payment_event_uuid`.
8. Completar idempotencia `subscriptions.payment_intent.activate_after_payment`.

Si cualquier paso falla, ejecutar rollback. No debe quedar `profile_subscriptions` creada sin idempotencia completed ni transición consistente.

### H) Snapshot canónico para `profile_subscriptions`
La fila futura de `profile_subscriptions` debe construirse con un snapshot canónico, no con datos arbitrarios del cliente.

Campos esperados:

- `subscription_id` nuevo;
- `entity_type` / `entity_id`;
- `doctor_id` / `profile_id`, si aplican;
- `plan_code`;
- `contracted_plan_code`;
- `effective_plan_code`;
- `billing_period`;
- `duration_days` desde catálogo;
- `status=active`;
- `contract_version`;
- `contract_accepted_at` desde aceptación `accepted_pending_payment`;
- `contract_accepted_by_user_id`;
- `contract_acceptance_source`;
- `starts_at` desde fecha de activación o pago confirmado;
- `expires_at` calculado según duración del plan;
- `grace_starts_at` / `grace_ends_at` según decisión futura;
- `source`;
- `notes`;
- campos auditables disponibles.

Referencias de pago/checkout:

- si el schema futuro agrega columnas específicas, guardar `checkout_intent_uuid`, `payment_intent_uuid` y `payment_event_uuid`;
- si no hay columnas específicas, documentar trazabilidad externa por tablas relacionadas y notas/audit, sin modificar DB/schema en esta fase documental.

### I) Estados esperados
Checkout:

- debe decidirse si pasa de `pending_payment` a `paid`, `confirmed` o un estado equivalente en la misma transacción.

Payment intent:

- ya queda `paid` / `mock_paid`;
- no debe mutarse salvo campos audit si una microfase futura lo justifica.

Payment event:

- ya queda `processed`;
- no debe crearse otro evento para activar.

`profile_subscriptions`:

- debe crearse exactamente 1 fila activa;
- no debe duplicarse en replay ni con key nueva.

Read-model:

- después de la activación debe devolver el plan contratado, no `free` fallback.

### J) Prohibiciones
Esta planificación no autoriza:

- implementar activación;
- crear servicio PHP;
- crear endpoint;
- modificar repositorios;
- ejecutar HTTP/POST;
- ejecutar SQL;
- modificar DB/schema;
- insertar en `profile_subscriptions`;
- actualizar `profile_subscriptions`;
- cambiar checkout status;
- cambiar payment intent;
- crear `subscription_payment_events`;
- activar capacidades públicas fuera del read-model;
- usar provider real;
- ejecutar webhook;
- ejecutar facturación real;
- activar sin idempotencia/lock;
- reutilizar `CreateSubscriptionWithAcceptanceService` tal cual.

### K) QA futura
QA futura mínima:

- preflight read-only antes de activar;
- activación positiva controlada con exactamente 1 POST o llamada backend cuando exista endpoint/servicio;
- replay idempotente con misma key y mismo payload;
- key nueva contra suscripción ya activada;
- misma key con payload distinto;
- confirmar que no se duplica `profile_subscriptions`;
- confirmar que no se crean `subscription_payment_events` adicionales;
- confirmar que checkout cambia sólo si esa transición fue decidida;
- confirmar que payment intent no se muta indebidamente;
- confirmar que read-model cambia de `free` a plan contratado;
- confirmar que no hay provider real, webhook ni facturación real.

### L) Siguiente microfase recomendada
Siguiente microfase recomendada:

```text
BE/SPEC-Suscripciones-PaymentIntent-PostPaymentActivation-Idempotency-Readiness-01
```

Justificación:

- la activación crea `profile_subscriptions`, que es un write de alto impacto;
- antes de diseñar lock o servicio conviene fijar operación, scope, replay y precedencia de errores;
- la idempotencia define cómo responder ante replay, payload distinto y activación ya realizada;
- sin ese contrato, el lock no basta para evitar duplicidad semántica.

No implementar activación hasta cerrar idempotencia y lock como contratos separados.

## Adenda PP-Decisiones 119 - Plan de lock para activación post-pago

### A) Estado actual
La idempotencia preparatoria para la activación post-pago ya existe con la operación:

```text
subscriptions.payment_intent.activate_after_payment
```

El lock específico de activación aún no existe. Tampoco existe el servicio `ActivateSubscriptionAfterPaymentService` ni un endpoint de activación post-pago.

Esta adenda no autoriza tocar `profile_subscriptions`, no activa suscripción, no cambia checkout status, no cambia payment intent y no crea `subscription_payment_events`.

### B) Decisión de scope
El scope primario recomendado para el lock futuro es:

```text
payment_intent_uuid
```

Justificación:

- la activación nace de un payment intent confirmado;
- protege la doble activación del mismo pago;
- mantiene el lock name corto;
- reduce bloqueo innecesario frente a un lock por `entity_type` / `entity_id`;
- es coherente con el patrón corregido de confirmación mock;
- debe complementarse con revalidación transaccional de `active_subscription_exists`.

### C) Operación futura
Nombre de operación de lock recomendado:

```text
payment_intent_activate_subscription
```

Error de timeout sugerido:

```text
payment_intent_activate_subscription_lock_timeout
```

Método futuro sugerido:

```php
acquirePaymentIntentActivateSubscription(string $paymentIntentUuid, int $timeoutSeconds = 2): ?string
```

Builder futuro sugerido:

```php
buildPaymentIntentActivateSubscriptionLockName(string $paymentIntentUuid): string
```

### D) Patrón de lock name
El lock name futuro debe usar hash corto, no el UUID crudo completo:

```text
mxmed:sub:pi:{hash12}:activate
```

Donde:

```php
substr(hash('sha256', $paymentIntentUuid), 0, 12)
```

Longitud estimada:

- `mxmed` = 5
- `:` = 1
- `sub` = 3
- `:` = 1
- `pi` = 2
- `:` = 1
- `hash12` = 12
- `:` = 1
- `activate` = 8
- total = 34 caracteres

El patrón queda muy por debajo del límite de 64 caracteres de MySQL `GET_LOCK()`.

### E) Comparativa de scopes
`payment_intent_uuid`:

- pros: protege el pago específico, mantiene lock corto y es coherente con `confirm_mock`;
- contras: exige revalidar `active_subscription_exists` dentro de la transacción.

`checkout_intent_uuid`:

- pros: protege el checkout completo;
- contras: acopla activación a checkout y no al evento/pago confirmado; también requiere hash corto para evitar exceder 64 caracteres.

`entity_type` / `entity_id`:

- pros: protege contra activaciones simultáneas por entidad;
- contras: serializa demasiado, puede bloquear flujos legítimos no relacionados y aumenta la contención.

### F) Reglas futuras de uso
La implementación futura debe seguir este orden:

1. Resolver replay idempotente completed antes de intentar lock cuando aplique.
2. Para key nueva o no replay, adquirir lock por `payment_intent_uuid`.
3. Dentro del lock y de la transacción:
   - revalidar payment intent `paid`;
   - revalidar payment event `processed`;
   - revalidar checkout `pending_payment`;
   - revalidar `active_subscription_exists=false`;
   - crear exactamente 1 fila en `profile_subscriptions`;
   - cerrar transición de checkout si esa decisión queda aprobada;
   - completar idempotencia.
4. Liberar lock siempre en `finally`.

### G) Riesgos
Riesgos identificados:

- doble activación si sólo se confía en idempotencia;
- carrera con key fresca;
- carrera contra `active_subscription_exists`;
- lock demasiado largo si se usa UUID crudo;
- lock demasiado amplio si se usa entity scope;
- lock demasiado estrecho si no se revalida entidad dentro de la transacción.

### H) Mitigaciones
Mitigaciones obligatorias para la implementación futura:

- lock corto hasheado;
- timeout de 2 segundos;
- revalidación transaccional;
- idempotencia completed/replay;
- no crear `profile_subscriptions` fuera de transacción;
- no mutar checkout/payment intent fuera del servicio autorizado;
- no provider real;
- no webhook;
- no facturación real.

### I) QA futura
QA futura mínima para la microfase de lock:

- `php -l modules/subscriptions/services/SubscriptionEntityWriteLockService.php`;
- grep de nueva constante/error/métodos;
- validar longitud de lock name menor a 64 caracteres;
- validar que usa hash `sha256` corto;
- validar que no usa UUID crudo completo;
- validar que no toca `profile_subscriptions`;
- validar que no crea endpoint;
- validar que no activa suscripción;
- futura QA funcional deberá probar doble submit/concurrencia cuando exista servicio.

### J) Siguiente microfase recomendada
Siguiente microfase recomendada:

```text
BE/Suscripciones-PaymentIntent-PostPaymentActivation-LockOperation-01
```

Alcance futuro:

- modificar únicamente `SubscriptionEntityWriteLockService.php`;
- agregar constante/error/método/builder del lock de activación;
- no crear servicio de activación;
- no crear endpoint;
- no ejecutar SQL;
- no modificar DB/schema;
- no tocar `profile_subscriptions`;
- no activar suscripción.

## Adenda PP-Decisiones 120 - Contrato de dependencias de escritura para activación post-pago

### A) Estado y decisión de alcance
La readiness del servicio `ActivateSubscriptionAfterPaymentService` quedó bloqueada porque faltan dependencias de escritura reutilizables. Esta adenda cierra el contrato técnico de esas dependencias antes de implementar el servicio de activación.

La confirmación `confirm_mock` ya dejó evidencia de pago mock/dev:

- payment intent `paid` / `mock_paid`;
- exactamente 1 `subscription_payment_events` con `payment_intent_confirm` / `processed`;
- checkout todavía en `pending_payment`;
- `profile_subscriptions` sin activación para `doctor/900001`.

Esta adenda no implementa código, no crea endpoint, no ejecuta writes, no activa suscripciones, no cambia DB/schema, no integra provider real, no crea webhooks, no factura y no crea `payment_events` adicionales.

### B) Soporte reusable para profile_subscriptions
Nombre recomendado:

```text
ProfileSubscriptionRepository
```

Archivo recomendado:

```text
modules/subscriptions/repositories/ProfileSubscriptionRepository.php
```

Método público recomendado:

```php
createActiveFromPaidCheckout(array $snapshot): array
```

Input requerido:

- `subscription_id`;
- `entity_type`;
- `entity_id`;
- `doctor_id`;
- `profile_id`;
- `plan_code`;
- `plan_label`;
- `billing_period`;
- `duration_days`;
- `contracted_plan_code`;
- `effective_plan_code`;
- `contract_version`;
- `contract_accepted_at`;
- `contract_accepted_by_user_id`;
- `contract_acceptance_source`;
- `contract_acceptance_ip`;
- `contract_acceptance_user_agent`;
- `starts_at`;
- `expires_at`;
- `status=active`;
- `auto_renew=0`;
- `source`;
- `notes`.

Output requerido:

- fila normalizada de `profile_subscriptions`;
- `subscription_id`;
- `entity_type`;
- `entity_id`;
- `plan_code`;
- `billing_period`;
- `status`;
- `starts_at`;
- `expires_at`;
- timestamps auditables disponibles.

Columnas mínimas a insertar:

- `subscription_id`;
- `entity_type`;
- `entity_id`;
- `doctor_id`;
- `profile_id`;
- `plan_code`;
- `plan_label`;
- `billing_period`;
- `duration_days`;
- `contracted_plan_code`;
- `effective_plan_code`;
- `contract_version`;
- `contract_accepted_at`;
- `contract_accepted_by_user_id`;
- `contract_acceptance_source`;
- `contract_acceptance_ip`;
- `contract_acceptance_user_agent`;
- `starts_at`;
- `expires_at`;
- `status`;
- `auto_renew`;
- `source`;
- `notes`;
- `deleted_at=NULL`.

Datos desde checkout:

- `entity_type`;
- `entity_id`;
- `doctor_id`;
- `profile_id`;
- `plan_code`;
- `billing_period`;
- `amount_cents`;
- `currency`;
- `contract_acceptance_uuid`;
- snapshot contractual disponible en checkout.

Datos desde payment intent:

- `payment_intent_uuid`;
- `checkout_intent_uuid`;
- `provider`;
- `provider_payment_id`;
- `provider_checkout_id`;
- `normalized_status=paid`;
- `provider_status=mock_paid`;
- `paid_at`;
- `amount_cents`;
- `currency`.

Datos desde payment event:

- `payment_event_uuid`;
- `event_type=payment_intent_confirm`;
- `processing_status=processed`;
- `provider`;
- `provider_event_id`;
- `processed_at`;
- `amount_cents`;
- `currency`.

Datos desde aceptación contractual `accepted_pending_payment`:

- `uuid`;
- `status=accepted_pending_payment`;
- `accepted_at`;
- `accepted_by_user_id`;
- `accepted_by_actor_role`;
- `accepted_by_operator_id`;
- `acceptance_source`;
- `ip_address`;
- `user_agent`;
- `contract_version`;
- `contract_hash`;
- `contract_snapshot_url`;
- `contract_title`.

Datos desde plan/price snapshot:

- `plan_label`;
- `duration_days`;
- vigencia calculada;
- plan contratado y plan efectivo;
- precio/currency sólo como validación cruzada, no como columna obligatoria de `profile_subscriptions` si el schema actual no la define.

Validaciones propias del repository:

- payload mínimo presente;
- `subscription_id` no vacío;
- `entity_type` / `entity_id` válidos;
- `status=active`;
- `duration_days > 0`;
- `starts_at` y `expires_at` coherentes;
- truncado/normalización de campos textuales según schema;
- retornar la fila creada o fallar con error semántico.

Validaciones que NO debe duplicar si pertenecen al orquestador:

- payment intent `paid` / `mock_paid`;
- payment event `payment_intent_confirm` / `processed`;
- checkout `pending_payment`;
- entity match entre checkout, payment intent y request;
- `active_subscription_exists=false`;
- idempotency begin/replay;
- adquisición/liberación de lock;
- transición de checkout.

### C) Transición post-pago del checkout
Estado actual:

```text
pending_payment
```

Estado final recomendado después de activación:

```text
activated
```

El nombre `activated` evita confundir checkout con payment intent `paid` y expresa que la suscripción ya fue creada desde ese checkout.

Repository recomendado:

```text
SubscriptionCheckoutIntentRepository
```

Método recomendado:

```php
markActivatedAfterPayment(string $checkoutIntentUuid, string $subscriptionId, array $metadata = []): ?array
```

Input requerido:

- `checkout_intent_uuid`;
- `subscription_id`;
- timestamp de activación;
- `payment_intent_uuid`;
- `payment_event_uuid`;
- `source`;
- `notes`.

Output requerido:

- checkout actualizado normalizado;
- `uuid`;
- `status=activated`;
- datos de entidad/plan;
- trazabilidad en `notes` si el schema no tiene columnas dedicadas.

Condiciones para permitir transición:

- checkout existe;
- `deleted_at IS NULL`;
- status actual `pending_payment`;
- pertenece a `entity_type` / `entity_id` esperados;
- coincide con el `checkout_intent_uuid` del payment intent;
- la transición ocurre dentro de la misma transacción que crea `profile_subscriptions`.

Si checkout ya no está `pending_payment`:

- si el replay idempotente completed tiene response almacenada, debe responder replay;
- si es key nueva y ya existe activación, responder `active_subscription_exists` o `checkout_intent_not_pending_payment` según precedencia decidida;
- no debe crear otra `profile_subscriptions`.

### D) Aceptación contractual pending payment
La aceptación `accepted_pending_payment` debe ligarse con la nueva `profile_subscriptions` mediante el `subscription_id` generado.

Repository recomendado:

```text
SubscriptionContractAcceptanceRepository
```

Métodos recomendados:

```php
findByUuid(string $uuid): ?array
linkSubscriptionId(string $acceptanceUuid, string $subscriptionId): ?array
```

Condiciones de seguridad:

- aceptación existe;
- `deleted_at IS NULL`;
- `status=accepted_pending_payment`;
- `subscription_id IS NULL`;
- `entity_type`, `entity_id`, `plan_code` y `billing_period` coinciden con checkout;
- el update ocurre dentro de la misma transacción que inserta `profile_subscriptions`;
- si ya tiene `subscription_id` y coincide con replay completed, no se crea duplicado.

Si la aceptación no existe:

```text
contract_acceptance_not_found
```

Si no está `accepted_pending_payment` o ya tiene `subscription_id` incompatible:

```text
contract_acceptance_not_pending_payment
```

Si falla el enlace:

```text
contract_acceptance_subscription_link_failed
```

### E) Orden transaccional recomendado
Orden futuro para `ActivateSubscriptionAfterPaymentService`:

1. Resolver idempotency begin/replay con `subscriptions.payment_intent.activate_after_payment`.
2. Si hay replay completed, responder sin intentar lock.
3. Cargar checkout, payment intent, payment event y aceptación contractual.
4. Adquirir lock por `payment_intent_uuid` con `payment_intent_activate_subscription`.
5. Abrir transacción.
6. Releer checkout, payment intent, payment event y aceptación dentro de la transacción.
7. Revalidar payment intent `paid` / `mock_paid`.
8. Revalidar payment event `payment_intent_confirm` / `processed`.
9. Revalidar checkout `pending_payment`.
10. Revalidar entity match.
11. Revalidar `active_subscription_exists=false`.
12. Generar `subscription_id`.
13. Insertar exactamente 1 `profile_subscriptions` activa.
14. Ligar `subscription_contract_acceptances.subscription_id` si aplica.
15. Actualizar checkout a `activated`.
16. Completar idempotencia con response almacenada.
17. Commit.
18. Liberar lock en `finally`.

Rollback obligatorio si falla cualquier paso entre la apertura de transacción y el commit.

### F) Errores esperados
Errores semánticos recomendados:

- `payment_intent_not_found`;
- `payment_event_not_found`;
- `payment_event_not_processed`;
- `checkout_intent_not_found`;
- `checkout_intent_not_pending_payment`;
- `contract_acceptance_not_found`;
- `contract_acceptance_not_pending_payment`;
- `active_subscription_exists`;
- `profile_subscription_create_failed`;
- `checkout_activation_transition_failed`;
- `contract_acceptance_subscription_link_failed`;
- `payment_intent_activate_subscription_lock_timeout`;
- `payment_intent_activation_unavailable`.

La precedencia debe favorecer replay completed antes de guards terminales, como ya se corrigió en confirmación mock.

### G) Límites explícitos
Esta microfase no autoriza:

- implementar código;
- crear endpoint;
- ejecutar writes;
- activar suscripciones;
- cambiar DB/schema;
- integrar provider real;
- crear webhooks;
- facturar;
- crear `payment_events` adicionales;
- cambiar checkout status;
- cambiar payment intent;
- tocar `profile_subscriptions`.

### H) Criterio de cierre
La siguiente microfase recomendada NO debe ser todavía `ActivateSubscriptionAfterPaymentService` completo.

Primero debe implementarse soporte de escritura reusable:

- repository/método reusable para `profile_subscriptions`;
- método de transición de checkout post-pago;
- finder/update para ligar `subscription_contract_acceptances` con `subscription_id`.

Siguiente microfase recomendada:

```text
BE/Suscripciones-PaymentIntent-PostPaymentActivation-WriteDependencies-01
```

Tipo:

```text
Backend / Implementación controlada de dependencias de escritura post-pago
```

Alcance:

- agregar soporte reusable mínimo para crear `profile_subscriptions`;
- agregar transición segura de checkout a `activated`;
- agregar finder/link de aceptación contractual;
- no crear `ActivateSubscriptionAfterPaymentService`;
- no crear endpoint;
- no ejecutar HTTP/POST;
- no ejecutar SQL manual;
- no modificar DB/schema;
- no activar suscripción fuera de pruebas explícitamente autorizadas.

---

## Adenda PP-Decisiones 121 - Cierre QA funcional post-activación payment intent

### A) Microfase cerrada
La microfase `QA/Suscripciones-PaymentIntent-PostPaymentActivation-Endpoint-FunctionalControlled-ReplayAndGuards-01` cerró con `PASS`.

El estado Git validado durante el cierre fue:

- branch: `fix/agenda-dia-mes-rescate-controlado`;
- HEAD local/origin: `dc1b29f feat(suscripciones): agrega endpoint activacion post pago`;
- working tree limpio;
- ahead/behind `0/0`.

Esta adenda documenta el cierre funcional de replay y guards terminales después de activar la suscripción post-pago del fixture `doctor/900001`.

### B) Casos HTTP validados
Fixture validado:

- `entity_type`: `doctor`;
- `entity_id`: `900001`;
- `checkout_intent_uuid`: `7d4beec3-b62a-40e1-a9f2-9edcc1a83364`;
- `payment_intent_uuid`: `85493a1c-4a66-40ec-928a-09cb0eb5d007`;
- `payment_event_uuid`: `86c29828-4537-4402-93cb-28d0947e81a7`;
- `contract_acceptance_uuid`: `ae137e4c-75f7-42cb-a6be-7cd24e051ca9`;
- `subscription_id` creado: `0d2c0113-5390-4548-9b61-3cbddfdfff06`.

Casos ejecutados y validados:

1. Replay misma key / mismo payload:
   - HTTP `200`;
   - `ok=true`;
   - `subscription_id=0d2c0113-5390-4548-9b61-3cbddfdfff06`;
   - replay idempotente sin duplicados.

2. Misma key / payload distinto:
   - HTTP `409`;
   - code `idempotency_key_reused_with_different_payload`;
   - sin duplicados funcionales.

3. Fresh-key después de activación:
   - HTTP `409`;
   - code `checkout_intent_not_pending_payment`;
   - guard terminal aceptado;
   - sólo registro idempotente `failed/409`;
   - sin duplicados funcionales.

### C) Estado DB post-QA
La validación SQL read-only posterior confirmó:

- `checkout_intents=1`;
- `payment_intents=1`;
- `payment_events=1`;
- `profile_subscriptions=4`;
- `contract_acceptances=4`;
- checkout fixture en `activated`;
- checkout `subscription_id=0d2c0113-5390-4548-9b61-3cbddfdfff06`;
- contract acceptance `accepted_pending_payment` con el mismo `subscription_id`;
- `doctor/900001` tiene exactamente 1 `profile_subscription`;
- `doctor/900001` tiene exactamente 1 `active_subscription`;
- payment intent sigue `paid` / `mock_paid`;
- payment event sigue `payment_intent_confirm` / `processed`;
- idempotencia original `id=30` quedó `completed/200`;
- fresh-key guard `id=34` quedó `failed/409`;
- columnas reales usadas para idempotencia: `response_http_status` y `response_body_text`.

No se observaron duplicados de `profile_subscriptions`, `payment_intents` ni `payment_events`.

### D) Observación de terminal
Durante la corrida terminal, VS Code/zsh reportó:

```text
The terminal process "/bin/zsh '-l'" terminated with exit code: 1.
```

Esta observación no invalida el `PASS` funcional porque:

- los casos HTTP fueron verificados;
- el SQL read-only post confirmó estado estable;
- no hubo duplicados funcionales;
- Git quedó limpio y alineado.

Se registra como observación de terminal/wrapper, no como fallo funcional.

### E) Decisión
La activación post-pago queda validada para replay y guards terminales.

La separación funcional queda ratificada:

- `confirm_mock` se mantiene separado y no activa suscripción;
- `confirm_mock` sólo confirma evidencia de pago mock/dev;
- la activación real de `profile_subscriptions` ocurre únicamente en el endpoint/servicio explícito `activate-after-payment`.

### F) Límites preservados
El cierre no autoriza:

- provider real;
- webhook;
- facturación real;
- activaciones fuera del endpoint controlado;
- cambios manuales de DB;
- duplicar `profile_subscriptions`;
- cambiar el contrato de `confirm_mock`.

### G) Siguiente microfase recomendada
Siguiente microfase recomendada:

```text
BE/SPEC-Suscripciones-PaymentIntent-PostPaymentActivation-NextHardening-Readiness-01
```

Alternativa si se decide cerrar el bloque antes:

```text
QA/Suscripciones-PaymentIntent-PostPaymentActivation-RegressionMatrix-ReadOnly-01
```

---

## Adenda PP-Decisiones 122 - Readiness de hardening post-activación payment intent

### A) Objetivo de readiness
Esta microfase no implementa hardening. Su objetivo es dejar documentado el estado técnico desde el cual se deben planificar microfases futuras de endurecimiento del flujo de activación post-pago de payment intent.

La base documental inmediata es `PP-Decisiones 121 - Cierre QA funcional post-activación payment intent`, que cerró el bloque funcional con `PASS`.

Estado base validado:

- branch: `fix/agenda-dia-mes-rescate-controlado`;
- HEAD local/origin: `48284ee docs(suscripciones): cierra qa activacion post pago`;
- working tree limpio;
- ahead/behind `0/0`.

### B) Bloque funcional cerrado
El bloque post-payment activation queda funcionalmente cerrado en:

- implementación del endpoint `activate-after-payment`;
- implementación de `ActivateSubscriptionAfterPaymentService`;
- dependencias de escritura para `profile_subscriptions`, checkout y contract acceptance;
- QA funcional controlada con POST real;
- replay idempotente con misma key/mismo payload;
- rechazo de payload distinto con `idempotency_key_reused_with_different_payload`;
- guard terminal fresh-key posterior a activación con HTTP `409`;
- cierre documental en `PP-Decisiones 121`.

Fixture de referencia validado:

- `entity_type=doctor`;
- `entity_id=900001`;
- `checkout_intent_uuid=7d4beec3-b62a-40e1-a9f2-9edcc1a83364`;
- `payment_intent_uuid=85493a1c-4a66-40ec-928a-09cb0eb5d007`;
- `payment_event_uuid=86c29828-4537-4402-93cb-28d0947e81a7`;
- `subscription_id=0d2c0113-5390-4548-9b61-3cbddfdfff06`.

Estados post-QA preservados:

- checkout fixture `activated`;
- payment intent `paid` / `mock_paid`;
- payment event `payment_intent_confirm` / `processed`;
- `doctor/900001` con exactamente una `profile_subscription` activa;
- sin duplicados funcionales;
- idempotencia original `completed/200`;
- fresh-key guard `failed/409`.

### C) Decisiones críticas a preservar
El hardening futuro no debe reabrir arquitectura ya cerrada.

Decisiones obligatorias:

- `confirm_mock` no activa suscripción;
- `confirm_mock` sólo confirma evidencia de pago mock/dev;
- la activación real de `profile_subscriptions` ocurre únicamente en `activate-after-payment`;
- replay de activación con misma key/mismo payload debe conservar respuesta HTTP `200` estable;
- misma key con payload distinto debe devolver HTTP `409` con `idempotency_key_reused_with_different_payload`;
- fresh-key después de activación terminal debe rechazarse con HTTP `409`;
- para fixture ya `activated`, `checkout_intent_not_pending_payment` es guard terminal aceptado;
- no debe haber duplicados funcionales de `profile_subscriptions`.

### D) Candidatos de hardening permitidos
Las microfases futuras de hardening deben ser pequeñas, controladas y verificables. Candidatos permitidos:

1. Matriz de regresión read-only para endpoint `activate-after-payment`.
2. Revisión de consistencia de códigos de error terminales.
3. Observabilidad mínima de idempotencia y guards.
4. Readiness de pruebas para estados no felices:
   - payment intent no pagado;
   - payment event inexistente;
   - payment event no `processed`;
   - checkout de otra entidad;
   - payment intent de otro checkout;
   - active subscription preexistente.
5. Revisión documental del estado `subscription_contract_acceptances.status=accepted_pending_payment` después de activación.
6. Posible decisión futura sobre si `contract_acceptance` debe permanecer como `accepted_pending_payment` o si conviene un estado adicional.

La decisión sobre un eventual estado adicional de contract acceptance queda expresamente fuera de esta microfase y no autoriza cambios de schema ni de implementación.

### E) Límites explícitos
Esta microfase no autoriza:

- cambiar endpoint;
- cambiar servicio;
- cambiar repositorios;
- tocar DB;
- modificar DB/schema;
- crear nuevos fixtures;
- volver a ejecutar activación;
- ejecutar SQL;
- ejecutar POST/curl;
- tocar PHP;
- tocar SQL versionado;
- tocar seeds;
- tocar frontend.

### F) Criterio de continuidad
Antes de implementar nuevos cambios conviene levantar una matriz read-only de estados, rutas, guards y dependencias. Esto evita endurecer el flujo sobre supuestos.

Siguiente microfase recomendada:

```text
QA/Suscripciones-PaymentIntent-PostPaymentActivation-RegressionMatrix-ReadOnly-01
```

La matriz debe confirmar, sin writes:

- estado actual del fixture activado;
- ruta `activate-after-payment`;
- dependencias del endpoint y servicio;
- reglas idempotentes;
- guards terminales aceptados;
- ausencia de duplicados;
- separación `confirm_mock` vs `activate-after-payment`.

### G) Observación operativa
Durante corridas recientes de terminal en VS Code/zsh se reportó:

```text
The terminal process "/bin/zsh '-l'" terminated with exit code: 1.
```

Esta observación no debe tratarse como fallo funcional si:

- Codex confirma `PASS`;
- el commit se crea;
- el push se completa;
- ahead/behind final queda `0/0`;
- working tree final queda limpio.

Se conserva como observación operativa de terminal/wrapper si vuelve a ocurrir.

---

## Adenda PP-Decisiones 123 - Readiness del contrato de errores activate-after-payment

### A) Objetivo documental
Esta microfase es readiness documental y no implementación.

El objetivo es definir el contrato de errores candidato para el endpoint de activación post-pago:

```text
POST /api/subscriptions/index.php/entities/{entity_type}/{entity_id}/checkout-intents/{checkout_intent_uuid}/payment-intents/{payment_intent_uuid}/activate-after-payment
```

No se cambia código PHP, SQL versionado, schema, seeds, frontend ni fixtures. Tampoco se ejecuta SQL, POST ni curl.

La base técnica validada por matriz read-only y lint verification confirma:

- endpoint `activate-after-payment` presente;
- servicio `ActivateSubscriptionAfterPaymentService` presente;
- repositorios de checkout, payment intent, payment event, contract acceptance y profile subscription presentes;
- idempotencia `subscriptions.payment_intent.activate_after_payment` presente;
- lock `payment_intent_activate_subscription` presente;
- `php -l` correcto en los 11 archivos críticos;
- Git limpio y alineado en `2da416b docs(suscripciones): define readiness hardening post pago`.

### B) Separación funcional preservada
El contrato de errores de `activate-after-payment` debe preservar la separación ya cerrada:

- `confirm_mock` no activa suscripción;
- `confirm_mock` sólo confirma evidencia de pago mock/dev;
- `activate-after-payment` es el único flujo que crea `profile_subscriptions` post-pago;
- replay misma `Idempotency-Key` y mismo payload conserva HTTP `200`;
- misma `Idempotency-Key` con payload distinto devuelve HTTP `409` con `idempotency_key_reused_with_different_payload`;
- fresh-key después de activación terminal devuelve HTTP `409`;
- para checkout ya `activated`, `checkout_intent_not_pending_payment` es guard terminal aceptado;
- no debe duplicarse `profile_subscriptions`.

### C) Códigos canónicos actuales
La matriz read-only detectó que los siguientes códigos existen actualmente en API/servicio y pueden tratarse como contrato canónico actual para `activate-after-payment`.

| Código | HTTP esperado | Origen/guard | Estado de contrato | Notas |
| --- | ---: | --- | --- | --- |
| `method_not_allowed` | 405 | API route method guard | Canónico actual | Método distinto a `POST`. |
| `invalid_payload` | 400 | API JSON/content-type guard | Canónico actual | Aplica desde parsing base del payload. |
| `invalid_payment_intent_activation_payload` | 422 | API/servicio input guard | Canónico actual | Payload incompleto o inválido para activación. |
| `idempotency_key_invalid` | 422 | API/idempotency service | Canónico actual | Falta o invalidez de `Idempotency-Key`. |
| `idempotency_key_reused_with_different_payload` | 409 | Idempotency service | Canónico actual | Validado funcionalmente. |
| `idempotency_key_not_reusable` | 409 | Idempotency service común | Canónico actual | Aplica si la key quedó en estado no reutilizable. |
| `idempotency_result_unavailable` | 409 | Idempotency service común | Canónico actual | Aplica si no se puede reconstruir replay completed. |
| `payment_intent_activate_subscription_lock_timeout` | 409 | Lock service | Canónico actual | Literal real definido en `SubscriptionEntityWriteLockService`. |
| `payment_intent_not_found` | 404 | Payment intent lookup | Canónico actual | Recurso inexistente. |
| `checkout_intent_not_found` | 404 | Checkout lookup | Canónico actual | Recurso inexistente. |
| `payment_event_not_found` | 404 | Payment event lookup | Canónico actual | Recurso inexistente. |
| `contract_acceptance_not_found` | 404 | Contract acceptance lookup | Canónico actual | Recurso inexistente o checkout sin acceptance vinculada. |
| `payment_intent_checkout_mismatch` | 409 | Scope guard | Canónico actual | Payment intent no pertenece al checkout solicitado. |
| `payment_event_payment_intent_mismatch` | 409 | Scope guard | Canónico actual | Payment event no pertenece al payment intent solicitado. |
| `checkout_intent_entity_mismatch` | 409 | Scope guard | Canónico actual | Checkout no pertenece a `entity_type/entity_id`. |
| `payment_intent_not_paid` | 409 | State guard | Canónico actual | Payment intent no está `paid`. |
| `payment_event_not_processed` | 409 | State guard | Canónico actual | Payment event no está procesado o no es `payment_intent_confirm`. |
| `checkout_intent_not_pending_payment` | 409 | State guard | Canónico actual | Guard terminal aceptado para checkout ya `activated`. |
| `contract_acceptance_not_pending_payment` | 409 | State guard | Canónico actual | Contract acceptance no está lista para activación post-pago. |
| `active_subscription_exists` | 409 | Active subscription guard | Canónico actual | Evita duplicar suscripción activa. |
| `payment_intent_activation_unavailable` | 500 | Fallback interno | Canónico actual | No debe filtrar detalles sensibles de repositorio/DB. |

### D) Equivalencia funcional actual
`payment_event_checkout_mismatch` no existe como literal actualmente.

La cobertura equivalente actual se expresa con:

- `payment_event_payment_intent_mismatch`;
- `payment_intent_checkout_mismatch`;
- validación del payment intent contra el checkout solicitado.

Esta combinación cubre el alcance funcional relevante sin requerir un alias nuevo en esta microfase.

Por lo tanto:

- `payment_event_checkout_mismatch` no debe documentarse como canónico actual;
- puede quedar como candidato futuro si se decide normalizar nombres;
- cualquier alias futuro debe ser planificado en una microfase separada.

### E) Dudas futuras de normalización
No hay inconsistencias críticas para el flujo actual, pero quedan dudas de contrato:

1. Decidir si conviene crear alias `payment_event_checkout_mismatch` o mantener sólo `payment_event_payment_intent_mismatch`.
2. Decidir si el contrato público debe agrupar mismatches de scope o conservar códigos específicos.
3. Decidir si `contract_acceptance.status=accepted_pending_payment` después de activación debe permanecer como está o si requiere estado adicional futuro.
4. Confirmar si `payment_intent_activation_unavailable` debe ser el único fallback público para errores de repositorio/DB.
5. Confirmar si la documentación pública debe exponer `idempotency_result_unavailable` o dejarlo como error técnico interno con HTTP `409`.

Estas dudas no autorizan cambios de código todavía.

### F) Límites preservados
Esta adenda no autoriza:

- implementar normalización de errores;
- cambiar lógica de guards;
- modificar endpoint;
- modificar servicio;
- modificar repositorios;
- ejecutar SQL;
- ejecutar POST/curl;
- tocar DB/schema;
- crear fixtures;
- tocar `profile_subscriptions`;
- ejecutar activaciones nuevas;
- modificar `confirm_mock`.

### G) Decisión
El contrato candidato queda documentado como readiness.

Se recomienda cerrar documentalmente el contrato antes de implementar cualquier cambio de normalización.

Siguiente microfase recomendada:

```text
DOCS/Suscripciones-PaymentIntent-PostPaymentActivation-ErrorContract-Closure-01
```

Alternativa si se decide preparar normalización futura sin tocar código todavía:

```text
BE/SPEC-Suscripciones-PaymentIntent-PostPaymentActivation-ErrorContract-Implementation-Readiness-01
```

---

## Adenda PP-Decisiones 124 - Cierre del contrato de errores activate-after-payment

### A) Objetivo de cierre
Esta microfase cierra documentalmente el contrato de errores candidato del endpoint de activación post-pago:

```text
POST /api/subscriptions/index.php/entities/{entity_type}/{entity_id}/checkout-intents/{checkout_intent_uuid}/payment-intents/{payment_intent_uuid}/activate-after-payment
```

El cierre toma como base `PP-Decisiones 123 - Readiness del contrato de errores activate-after-payment`.

Este cierre no autoriza cambios de implementación. No modifica PHP, SQL versionado, schema, seeds, frontend ni fixtures. No ejecuta SQL, POST ni curl.

### B) Contrato canónico actual
Para el estado actual del endpoint, los siguientes códigos quedan aceptados como contrato interno actual.

#### Request/base

| Código | HTTP | Estado |
| --- | ---: | --- |
| `method_not_allowed` | 405 | Canónico actual |
| `invalid_payload` | 400 | Canónico actual cuando aplica desde capa API común |
| `invalid_payment_intent_activation_payload` | 422 | Canónico actual |
| `idempotency_key_invalid` | 422 | Canónico actual |

#### Idempotencia

| Código | HTTP | Estado |
| --- | ---: | --- |
| `idempotency_key_reused_with_different_payload` | 409 | Canónico actual |
| `idempotency_key_not_reusable` | 409 | Canónico actual si aplica desde servicio común |
| `idempotency_result_unavailable` | 409 | Canónico actual si aplica desde servicio común |

#### Recursos no encontrados

| Código | HTTP | Estado |
| --- | ---: | --- |
| `payment_intent_not_found` | 404 | Canónico actual |
| `checkout_intent_not_found` | 404 | Canónico actual |
| `payment_event_not_found` | 404 | Canónico actual |
| `contract_acceptance_not_found` | 404 | Canónico actual |

#### Mismatch / scope

| Código | HTTP | Estado |
| --- | ---: | --- |
| `payment_intent_checkout_mismatch` | 409 | Canónico actual |
| `payment_event_payment_intent_mismatch` | 409 | Canónico actual |
| `checkout_intent_entity_mismatch` | 409 | Canónico actual si aplica desde guard actual |

#### Estados no válidos

| Código | HTTP | Estado |
| --- | ---: | --- |
| `payment_intent_not_paid` | 409 | Canónico actual |
| `payment_event_not_processed` | 409 | Canónico actual |
| `checkout_intent_not_pending_payment` | 409 | Canónico actual |
| `contract_acceptance_not_pending_payment` | 409 | Canónico actual |
| `active_subscription_exists` | 409 | Canónico actual |

#### Locks y fallback

| Código | HTTP | Estado |
| --- | ---: | --- |
| lock timeout de activación payment intent | 409 | Guard actual; no convertir nombre literal en contrato público estable sin microfase específica |
| `payment_intent_activation_unavailable` | 500 | Fallback público actual |

Los errores internos de repositorio/DB deben mantenerse detrás de fallback seguro. No se deben filtrar stacktraces, SQL, detalles PDO, provider secrets ni payload sensible.

### C) Equivalencia funcional cerrada
`payment_event_checkout_mismatch` no forma parte del contrato canónico actual porque no existe como literal implementado.

La equivalencia funcional actual queda cerrada así:

- `payment_event_payment_intent_mismatch` valida que el evento pertenezca al payment intent solicitado;
- `payment_intent_checkout_mismatch` valida que el payment intent pertenezca al checkout solicitado;
- la validación de scope checkout/entidad completa la cobertura funcional.

Si en el futuro se desea exponer `payment_event_checkout_mismatch` como alias o código normalizado, debe abrirse una microfase específica. Esta adenda no ordena ni autoriza esa implementación.

### D) Separación funcional preservada
El cierre del contrato preserva las decisiones funcionales ya validadas:

- `confirm_mock` no activa suscripción;
- `confirm_mock` sólo confirma evidencia de pago mock/dev;
- `activate-after-payment` activa `profile_subscriptions` post-pago;
- replay misma key/mismo payload devuelve HTTP `200`;
- misma key/payload distinto devuelve HTTP `409` con `idempotency_key_reused_with_different_payload`;
- fresh-key post-activación devuelve HTTP `409` con guard terminal;
- `checkout_intent_not_pending_payment` se acepta para checkout ya `activated`;
- no se duplican `profile_subscriptions`.

### E) Dudas futuras fuera de este cierre
Quedan fuera de esta microfase:

1. Normalizar o crear alias `payment_event_checkout_mismatch`.
2. Convertir el literal del lock timeout en contrato público estable.
3. Cambiar el estado de `subscription_contract_acceptances.status` después de activación.
4. Modificar la precedencia entre `checkout_intent_not_pending_payment` y `active_subscription_exists`.
5. Ajustar respuestas para frontend o soporte.

Estas dudas deben tratarse en microfases futuras, sin mezclar documentación con cambios de backend.

### F) Uso del contrato
Este contrato documental queda listo para:

- QA futura de errores y guards;
- mapeo seguro para frontend;
- soporte operativo;
- documentación de comportamiento esperado;
- microfases de normalización si se decide que son necesarias.

### G) Siguiente microfase recomendada
Siguiente microfase recomendada:

```text
BE/SPEC-Suscripciones-PaymentIntent-PostPaymentActivation-FrontendSupportErrorMapping-Readiness-01
```

Motivo: el contrato técnico de errores ya queda cerrado documentalmente; el siguiente paso razonable es preparar cómo traducir esos códigos a mensajes seguros para frontend/soporte sin cambiar todavía backend.

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

---

## Adenda PP-Decisiones 125 - Readiness de mapeo frontend y soporte para errores activate-after-payment

Fecha de readiness documental: 2026-06-27

### Microfase

`BE/SPEC-Suscripciones-PaymentIntent-PostPaymentActivation-FrontendSupportErrorMapping-Readiness-01`

### Tipo

BE/SPEC / Readiness documental de mapeo seguro de errores para frontend y soporte.

### Objetivo

Esta adenda prepara el mapeo seguro entre códigos técnicos del endpoint `activate-after-payment` y mensajes destinados a frontend y soporte.

No implementa cambios en backend, frontend, base de datos, endpoints, servicios, repositorios, fixtures ni guards.

### Base documental

Este readiness toma como base:

- `PP-Decisiones 121` — Cierre QA funcional post-activación payment intent.
- `PP-Decisiones 122` — Readiness de hardening posterior a activación post-pago.
- `PP-Decisiones 123` — Readiness del contrato de errores activate-after-payment.
- `PP-Decisiones 124` — Cierre documental del contrato de errores activate-after-payment.

### Endpoint bajo mapeo

`POST /api/subscriptions/index.php/entities/{entity_type}/{entity_id}/checkout-intents/{checkout_intent_uuid}/payment-intents/{payment_intent_uuid}/activate-after-payment`

### Decisiones preservadas

1. `confirm_mock` NO activa suscripción.
2. `confirm_mock` sólo confirma evidencia de pago mock/dev.
3. `activate-after-payment` es el endpoint explícito que activa `profile_subscriptions` post-pago.
4. El contrato técnico cerrado en `PP-Decisiones 124` no se modifica en esta microfase.
5. `payment_event_checkout_mismatch` no es canónico actual.
6. La equivalencia funcional para ese caso permanece compuesta por:
   - `payment_event_payment_intent_mismatch`
   - `payment_intent_checkout_mismatch`
   - validación de scope checkout/entidad
7. El frontend no decide activación; sólo presenta estado y mensajes seguros.
8. Soporte puede recibir más contexto operativo, pero sin secretos ni datos sensibles.

### Principios de seguridad del mapeo

Los mensajes para frontend y soporte no deben exponer:

- Stacktrace.
- SQL.
- Detalles PDO.
- Provider secrets.
- Payload sensible.
- Hashes de idempotencia.
- IDs internos autoincrementales.
- Datos clínicos o personales no necesarios.
- Detalles técnicos que faciliten abuso del endpoint.

Sí puede exponerse:

- Un mensaje general accionable.
- Un código técnico controlado.
- Una recomendación segura para el usuario.
- Una guía operativa para soporte.

### Propuesta candidata de mapeo

#### A) Request/base

Códigos incluidos:

- `method_not_allowed`
- `invalid_payment_intent_activation_payload`
- `invalid_payload`
- `idempotency_key_invalid`

Mensaje frontend sugerido:

> No pudimos procesar la solicitud. Actualiza la página y vuelve a intentarlo.

Mensaje soporte sugerido:

> Solicitud inválida o incompleta para activación post-pago. Revisar método, payload y encabezado `Idempotency-Key`.

Reintentabilidad:

- Sí, corrigiendo la solicitud.
- No insistir con el mismo request inválido.

Severidad sugerida:

- Media si ocurre en operación normal.
- Alta si se repite masivamente.

#### B) Idempotencia

Códigos incluidos:

- `idempotency_key_reused_with_different_payload`
- `idempotency_key_not_reusable`
- `idempotency_result_unavailable`

Mensaje frontend sugerido:

> Esta operación ya fue procesada o no puede repetirse con los mismos datos. Actualiza la página y revisa el estado de la suscripción.

Mensaje soporte sugerido:

> Conflicto de idempotencia. Revisar que la misma `Idempotency-Key` no se reutilice con payload distinto y confirmar estado actual de checkout/suscripción.

Reintentabilidad:

- No con la misma key si el payload cambió.
- Sí con flujo nuevo controlado después de revisar estado.

Severidad sugerida:

- Media.
- Alta si causa bloqueo recurrente de activaciones válidas.

#### C) Recursos no encontrados

Códigos incluidos:

- `payment_intent_not_found`
- `checkout_intent_not_found`
- `payment_event_not_found`
- `contract_acceptance_not_found`

Mensaje frontend sugerido:

> No encontramos la información necesaria para completar la activación. Actualiza la página o contacta a soporte.

Mensaje soporte sugerido:

> Falta un recurso requerido para activar: checkout, payment intent, payment event o aceptación contractual.

Reintentabilidad:

- No hasta revisar fixture, estado o relación de recursos.
- No forzar activación sin diagnóstico.

Severidad sugerida:

- Alta si ocurre en flujo real de pago confirmado.

#### D) Mismatch / scope

Códigos incluidos:

- `payment_intent_checkout_mismatch`
- `payment_event_payment_intent_mismatch`
- `checkout_intent_entity_mismatch`

Mensaje frontend sugerido:

> No pudimos validar la relación entre el pago y la contratación. Contacta a soporte.

Mensaje soporte sugerido:

> Conflicto de relación o scope entre payment event, payment intent, checkout o entidad. No forzar activación sin diagnóstico.

Reintentabilidad:

- No automáticamente.
- Requiere diagnóstico técnico.

Severidad sugerida:

- Alta.

Nota:

`payment_event_checkout_mismatch` no debe mostrarse como código canónico actual porque no existe como literal implementado. Su cobertura funcional se mantiene por composición de guards.

#### E) Estados inválidos

Códigos incluidos:

- `payment_intent_not_paid`
- `payment_event_not_processed`
- `checkout_intent_not_pending_payment`
- `contract_acceptance_not_pending_payment`
- `active_subscription_exists`

Mensajes frontend sugeridos:

| Código | Mensaje frontend |
|---|---|
| `payment_intent_not_paid` | El pago todavía no aparece como confirmado. Espera unos momentos y vuelve a revisar. |
| `payment_event_not_processed` | La confirmación del pago aún no está lista. Intenta nuevamente más tarde. |
| `checkout_intent_not_pending_payment` | Esta contratación ya no está disponible para activación. |
| `contract_acceptance_not_pending_payment` | La aceptación contractual ya no está disponible para esta activación. |
| `active_subscription_exists` | Este perfil ya tiene una suscripción activa. |

Mensaje soporte sugerido:

> Guard de estado impidió activación. Revisar estado terminal actual antes de intentar cualquier corrección.

Reintentabilidad:

| Código | Reintentable |
|---|---|
| `payment_intent_not_paid` | Sí, tras confirmación de pago. |
| `payment_event_not_processed` | Sí, tras procesamiento. |
| `checkout_intent_not_pending_payment` | No automáticamente. |
| `contract_acceptance_not_pending_payment` | No automáticamente. |
| `active_subscription_exists` | No. |

Severidad sugerida:

- Media si corresponde a flujo esperado.
- Alta si contradice evidencia de pago o estado visible al usuario.

#### F) Lock timeout

Mensaje frontend sugerido:

> El sistema está procesando esta operación. Espera unos segundos y vuelve a intentar.

Mensaje soporte sugerido:

> Timeout de lock de activación. Puede indicar concurrencia o reintento simultáneo.

Reintentabilidad:

- Sí, tras espera breve.
- Si persiste, revisar concurrencia e idempotencia.

Severidad sugerida:

- Baja si es aislado.
- Media si es recurrente.

#### G) Fallback interno

Código incluido:

- `payment_intent_activation_unavailable`

Mensaje frontend sugerido:

> No pudimos completar la activación en este momento. Intenta más tarde o contacta a soporte.

Mensaje soporte sugerido:

> Error interno controlado en activación post-pago. Revisar logs sin exponer detalles al usuario.

Reintentabilidad:

- Sí, después de revisar disponibilidad o logs.
- No repetir indefinidamente sin diagnóstico.

Severidad sugerida:

- Alta.

### Decisión de readiness

El mapeo frontend/soporte queda definido como propuesta candidata, no implementada.

Este readiness no cambia:

- Contrato técnico backend.
- Códigos actuales.
- Status HTTP.
- Orden de guards.
- Respuestas existentes.
- Frontend.
- Soporte real.
- Base de datos.

### Siguiente microfase recomendada

`DOCS/Suscripciones-PaymentIntent-PostPaymentActivation-FrontendSupportErrorMapping-Closure-01`

Motivo:

Antes de implementar mensajes en frontend o backend, conviene cerrar documentalmente el mapeo candidato y decidir si será consumido por frontend, soporte interno o ambos.

---

## Adenda PP-Decisiones 126 - Cierre de mapeo frontend y soporte para errores activate-after-payment

### Objetivo de cierre

Esta adenda cierra documentalmente el mapeo candidato entre códigos técnicos del endpoint `activate-after-payment` y mensajes seguros para frontend y soporte.

El cierre toma como base:

- `PP-Decisiones 124` - Cierre del contrato de errores activate-after-payment.
- `PP-Decisiones 125` - Readiness de mapeo frontend y soporte para errores activate-after-payment.

Esta microfase no implementa mensajes, no toca frontend, no toca backend, no cambia respuestas HTTP y no modifica la lógica de activación post-pago.

### Decisiones preservadas

1. `confirm_mock` no activa suscripción.
2. `confirm_mock` sólo confirma evidencia de pago mock/dev.
3. `activate-after-payment` es el endpoint explícito que activa `profile_subscriptions` post-pago.
4. El contrato técnico cerrado en `PP-Decisiones 124` no se modifica.
5. El readiness de mapeo documentado en `PP-Decisiones 125` queda cerrado como contrato documental candidato.
6. `payment_event_checkout_mismatch` no es canónico actual.
7. El frontend no decide activación; sólo presenta estado y mensajes seguros.
8. Soporte puede recibir más contexto operativo, pero sin secretos ni datos sensibles.

### Principios de comunicación segura

Los mensajes de frontend quedan cerrados como mensajes:

- seguros;
- breves;
- accionables;
- no técnicos en exceso;
- sin datos sensibles.

Los mensajes de soporte pueden ser más precisos, pero no deben exponer:

- stacktrace;
- SQL;
- detalles PDO;
- provider secrets;
- payload sensible;
- hashes de idempotencia;
- IDs internos autoincrementales;
- datos clínicos o personales no necesarios.

El código técnico backend puede conservarse para trazabilidad controlada, siempre separado del mensaje de usuario final.

### Mapeo cerrado por grupo

#### A) Request/base

Códigos:

- `method_not_allowed`
- `invalid_payment_intent_activation_payload`
- `invalid_payload`
- `idempotency_key_invalid`

Mensaje frontend cerrado:

> No pudimos procesar la solicitud. Actualiza la página y vuelve a intentarlo.

Mensaje soporte cerrado:

> Solicitud inválida o incompleta para activación post-pago. Revisar método, payload y encabezado Idempotency-Key.

Reintentabilidad:

- Sí, corrigiendo la solicitud.

#### B) Idempotencia

Códigos:

- `idempotency_key_reused_with_different_payload`
- `idempotency_key_not_reusable`
- `idempotency_result_unavailable`

Mensaje frontend cerrado:

> Esta operación ya fue procesada o no puede repetirse con los mismos datos. Actualiza la página y revisa el estado de la suscripción.

Mensaje soporte cerrado:

> Conflicto de idempotencia. Revisar que la misma Idempotency-Key no se reutilice con payload distinto y confirmar estado actual de checkout/suscripción.

Reintentabilidad:

- No con la misma key si el payload cambió.
- Sí con flujo nuevo controlado después de revisar estado.

#### C) Recursos no encontrados

Códigos:

- `payment_intent_not_found`
- `checkout_intent_not_found`
- `payment_event_not_found`
- `contract_acceptance_not_found`

Mensaje frontend cerrado:

> No encontramos la información necesaria para completar la activación. Actualiza la página o contacta a soporte.

Mensaje soporte cerrado:

> Falta un recurso requerido para activar: checkout, payment intent, payment event o aceptación contractual.

Reintentabilidad:

- No hasta revisar fixture, estado o relación de recursos.

#### D) Mismatch / scope

Códigos:

- `payment_intent_checkout_mismatch`
- `payment_event_payment_intent_mismatch`
- `checkout_intent_entity_mismatch`

Mensaje frontend cerrado:

> No pudimos validar la relación entre el pago y la contratación. Contacta a soporte.

Mensaje soporte cerrado:

> Conflicto de relación o scope entre payment event, payment intent, checkout o entidad. No forzar activación sin diagnóstico.

Reintentabilidad:

- No automática.

Nota: `payment_event_checkout_mismatch` no debe mostrarse como canónico actual.

#### E) Estados inválidos

| Código | Mensaje frontend cerrado | Reintentabilidad |
| --- | --- | --- |
| `payment_intent_not_paid` | El pago todavía no aparece como confirmado. Espera unos momentos y vuelve a revisar. | Sí, tras confirmación de pago. |
| `payment_event_not_processed` | La confirmación del pago aún no está lista. Intenta nuevamente más tarde. | Sí, tras procesamiento. |
| `checkout_intent_not_pending_payment` | Esta contratación ya no está disponible para activación. | No automáticamente. |
| `contract_acceptance_not_pending_payment` | La aceptación contractual ya no está disponible para esta activación. | No automáticamente. |
| `active_subscription_exists` | Este perfil ya tiene una suscripción activa. | No. |

Mensaje soporte cerrado:

> Guard de estado impidió activación. Revisar estado terminal actual antes de intentar cualquier corrección.

#### F) Lock timeout

Mensaje frontend cerrado:

> El sistema está procesando esta operación. Espera unos segundos y vuelve a intentar.

Mensaje soporte cerrado:

> Timeout de lock de activación. Puede indicar concurrencia o reintento simultáneo.

Reintentabilidad:

- Sí, tras espera breve.

#### G) Fallback interno

Código:

- `payment_intent_activation_unavailable`

Mensaje frontend cerrado:

> No pudimos completar la activación en este momento. Intenta más tarde o contacta a soporte.

Mensaje soporte cerrado:

> Error interno controlado en activación post-pago. Revisar logs sin exponer detalles al usuario.

Reintentabilidad:

- Sí, después de revisar disponibilidad o logs.

### Alcance de este cierre

Este cierre documental no ordena implementación automática.

No autoriza:

- modificar backend;
- modificar frontend;
- cambiar status HTTP;
- cambiar códigos técnicos;
- cambiar orden de guards;
- tocar DB/schema;
- ejecutar activaciones;
- exponer datos sensibles en UI o soporte.

### Siguiente microfase recomendada

Siguiente microfase recomendada:

```text
QA/Suscripciones-PaymentIntent-PostPaymentActivation-FrontendSupportErrorMapping-IntegrationReadiness-ReadOnly-01
```

Motivo:

Antes de implementar mensajes, conviene revisar read-only dónde se consumirían actualmente los errores en frontend/admin/soporte y si existe una capa central de mensajes.
