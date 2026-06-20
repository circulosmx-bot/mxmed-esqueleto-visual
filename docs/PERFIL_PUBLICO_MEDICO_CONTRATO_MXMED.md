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
