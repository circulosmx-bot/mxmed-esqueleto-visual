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
