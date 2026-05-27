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
