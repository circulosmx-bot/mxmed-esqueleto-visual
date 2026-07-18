# Reconciliación de documentación histórica funcional — México Médico

## 1. Portada y versión

- Contrato: `MXMED_HISTORICAL_FUNCTIONAL_DOCUMENTS_RECONCILIATION_V1`.
- Actividad: `PRODUCT-AUDIT/MXMed-Historical-Functional-Documents-Reconciliation-01`.
- Tipo: actividad auxiliar; no incrementa el contador principal.
- Versión: `1.0.0`.
- Fecha contractual: 2026-07-18.
- Estado: `PASS_STATIC_RECONCILIATION`.
- Fuentes: 8 PDF, 29 páginas, `historical_noncanonical`.
- Contador: Actividad 1 de 22 concluida; Actividad 2 bloqueada.

## 2. Propósito

Preservar ocho fuentes funcionales históricas byte a byte y reconciliar sus
requisitos con la dirección, contratos, implementación y auditorías actuales.
La reconciliación conserva contexto útil sin convertir un documento de 2025 en
autoridad automática, implementación demostrada o decisión aprobada.

## 3. Alcance

Se cubrieron Agenda, contacto de paciente, Expediente, Recetas, facturación a
pacientes, reclamo, ownership, publicación, RBAC, roles internos, 22 triggers,
canales, grace, backoffice, pagos manuales, moderación e IA. El análisis fue
estático y local: no ejecutó aplicación, PHP, SQL, servidor, navegador, HTTP,
Stripe, AWS, CDK, correo, IA ni OCR; tampoco consultó datos reales o secretos.

## 4. Fuentes y hashes

| ID | Fuente versionada | Páginas | SHA-256 |
|---|---|---:|---|
| HIST-SRC-001 | [Lógica transversal](./fuentes-historicas/mxmed-2025/00-revision-integral-interacciones-logica-transversal.pdf) | 4 | `f7103b8cd19c456068f1cbf8f7255e398e60adc619fa08e742ef8a97e04a0551` |
| HIST-SRC-002 | [Triggers y notificaciones](./fuentes-historicas/mxmed-2025/01-repositorio-maestro-triggers-notificaciones.pdf) | 5 | `3e3e350b7627f07b7dd1232b3ccf78b46e4a98221845900adf353b181e797362` |
| HIST-SRC-003 | [Reclamo de perfiles Free](./fuentes-historicas/mxmed-2025/02-flujo-reclamo-perfiles-gratuitos.pdf) | 3 | `080e8638693680aa9c971530c32798bcfea516f5a0f5485c2c0b27bb9eab2c98` |
| HIST-SRC-004 | [RBAC histórico](./fuentes-historicas/mxmed-2025/03-modulo-a1-roles-permisos-seguridad-rbac.pdf) | 4 | `5b50cdb779ec3c5999bf6584276a9e6b8e6b9ce137ff9a88e7e1c27dcd256961` |
| HIST-SRC-005 | [Roles de agentes IA](./fuentes-historicas/mxmed-2025/04-funciones-roles-agentes-inteligencia-artificial.pdf) | 2 | `82aac06efc6d0a07feb145dda199400fe9c71bcdd164fe3e269d8ad668f37445` |
| HIST-SRC-006 | [Módulo de notificaciones](./fuentes-historicas/mxmed-2025/05-modulo-a3-notificaciones-triggers.pdf) | 4 | `f28d149e1e2605be894c362215448a29143886be7f5e436d6a41dcc0d62eed0a` |
| HIST-SRC-007 | [Backoffice histórico](./fuentes-historicas/mxmed-2025/06-modulo-a5-flujos-administrativos-backoffice.pdf) | 4 | `a5f04d0c90038e78390cada536c4da1d7e204496422ffa31afa8001db90cebfc` |
| HIST-SRC-008 | [Apéndice de IA](./fuentes-historicas/mxmed-2025/07-modulos-inteligencia-artificial-contemplados.pdf) | 3 | `e39871eae471d1a4268d138814e3ea395189af41ddf08566a85c1521a0b471d3` |

Los hashes del intake y del repositorio coinciden en 8/8 y las copias son
byte-identical. El [manifiesto](./fuentes-historicas/mxmed-2025/manifest.json)
conserva metadata, dominio, impacto y estado de inspección de cada fuente.

## 5. Estado histórico no canónico

Cada PDF está clasificado `historical_noncanonical`. Su presencia en `docs/`
no significa vigencia, implementación, aprobación, precedencia, cumplimiento
legal ni confirmación runtime. La etiqueta “MXMed 2025” es un periodo
aproximado declarado por las fuentes; no se inventó fecha o autor personal.

Un requisito sólo puede promoverse mediante decisión explícita del director,
PP posterior, implementación validada y contrato canónico actualizado. La
[guía del directorio](./fuentes-historicas/mxmed-2025/README.md) fija cómo citar
y promover requisitos y prohíbe implementarlos directamente.

## 6. Precedencia

| Nivel | Autoridad | Regla |
|---:|---|---|
| A | requisito actual y explícito del director | mayor autoridad de producto |
| B | PP/contrato canónico actual aprobado | decisión vigente |
| C | comportamiento implementado actual | evidencia de conducta, no decisión pendiente |
| D | auditoría actual | hallazgo verificable con limitaciones |
| E | fuente histórica funcional | contexto y candidato, nunca vigencia automática |
| F | fixture/proyección | nunca autoridad de negocio |

No se resolvió silenciosamente ningún conflicto. Antigüedad no invalida un
detalle útil ni lo eleva sobre decisiones posteriores.

## 7. Método

1. Se validaron nombres, SHA-256 y número de páginas antes de modificar docs.
2. PDFKit local extrajo texto por página sin OCR ni dependencias nuevas.
3. Se renderizaron e inspeccionaron visualmente las 29 páginas, incluidas tablas.
4. Cada requisito recibió ID, fuente/página, dominio, actores, datos, refs,
   deuda, DEC, clasificación, recomendación, nivel de evidencia y grupo.
5. Se cruzó con PP273–PP276, deuda, inventario, operadores y PG-01.
6. Las propuestas seguras se documentaron sin implementar ni aprobar.

Clasificaciones permitidas: `adopt_current`, `confirm_current`,
`refine_current`, `conflict_requires_director`, `defer_future_scope`,
`reject_for_safety`, `superseded`, `requires_specialized_audit` e
`historical_reference_only`.

## 8. Cobertura por página

| Fuente | Cobertura | Contenido y estructuras inspeccionadas |
|---|---:|---|
| HIST-SRC-001 | 4/4 | Agenda→ficha, Recetas, facturación, tabla de triggers y riesgos |
| HIST-SRC-002 | 5/5 | tabla maestra de 22 triggers, condiciones, destinatarios y copys |
| HIST-SRC-003 | 3/3 | elegibilidad, documentos, transiciones, consentimientos y origen |
| HIST-SRC-004 | 4/4 | matrices de rol, no delegabilidad, bitácora, 2FA y passkeys |
| HIST-SRC-005 | 2/2 | cinco clases de IA y modelo supervisor |
| HIST-SRC-006 | 4/4 | canales, anti-spam, grace D+8, grupos y opt-out |
| HIST-SRC-007 | 4/4 | backoffice, pagos, publicación, cola y dry-run |
| HIST-SRC-008 | 3/3 | agente Professional, imágenes, redacción e interacciones |
| **Total** | **29/29** | extracción completa y visual inspection completa, sin OCR |

## 9. Registro de requisitos

Se registraron 95 requisitos únicos:

| Familia | IDs | Cantidad | Foco |
|---|---|---:|---|
| Interacción | `HIST-INT-001..006` | 6 | Agenda, contacto, clínica, riesgo |
| Notificaciones | `HIST-NOT-001..037` | 37 | 22 triggers y reglas transversales |
| Reclamo | `HIST-CLM-001..010` | 10 | request, ownership, publicación |
| RBAC | `HIST-RBAC-001..014` | 14 | roles, scopes, MFA, no delegabilidad |
| IA | `HIST-AI-001..011` | 11 | seis capabilities y gobernanza |
| Backoffice | `HIST-BO-001..004` | 4 | operación, cola y control de cambios |
| Pagos | `HIST-PAY-001..007` | 7 | paciente/plataforma, manual y prórroga |
| Clínica | `HIST-CLN-001..004` | 4 | integridad, emisión y reimpresión |
| Datos | `HIST-DATA-001` | 1 | auditoría saneada |
| Publicación | `HIST-PUB-001` | 1 | máquina de moderación |

Distribución: 1 `adopt_current`, 15 `confirm_current`, 27 `refine_current`,
10 `conflict_requires_director`, 7 `defer_future_scope`, 4
`reject_for_safety`, 1 `superseded`, 28 `requires_specialized_audit` y 2
`historical_reference_only`. El registro JSON completo permanece en la
evidencia de la actividad; ninguna entrada quedó sin clasificar.

## 10. Lógica transversal

Propuesta reconciliada:

- una cita puede crear o vincular sólo un contacto/ficha operativa mínima;
- una cita no crea automáticamente un expediente clínico;
- Expediente exige capability clínica, acción explícita, profesional autorizado
  y consentimiento aplicable;
- Agenda puede enlazar al contexto clínico sólo tras autorización y scope;
- diagnóstico, notas clínicas, emisión/firma de Recetas y consentimientos
  clínicos son no delegables hasta una decisión especializada;
- la reimpresión exacta de un documento emitido puede delegarse con actor,
  motivo, auditoría y notificación;
- una Receta emitida no se edita: la corrección crea versión/folio/documento
  nuevo y preserva el anterior;
- facturación a pacientes y suscripción MXMed son dominios distintos.

La fuente histórica que afirma apertura automática de ficha se refina a
contacto operativo. El bridge clínico condicional localizado por PG-01 sigue
requiriendo gate y verificación runtime; no se activó ni modificó.

## 11. Reclamo

Elegibilidad propuesta: un perfil sin owner puede reclamarse; Free es la
condición histórica de entrada, no una prohibición perpetua de contratar.
Ownership debe verificarse antes de administrar capacidades pagadas.

Se separan tres máquinas:

| Máquina | Estados propuestos/reconciliados |
|---|---|
| claim request | `draft`, `submitted`, `pending_review`, `needs_info`, `approved`, `rejected`, `cancelled` |
| ownership | `unclaimed`, `claim_pending`, `claimed`, `disputed`, `suspended`, `transferred`, `revoked` |
| publication/moderation | `draft`, `pending_review`, `approved`, `published`, `changes_pending_review`, `suspended` |

La cuenta del solicitante precede la revisión; la documentación se minimiza y
requiere política de retención; la revisión es humana y el acceso permanece
bloqueado hasta aprobación. Tras el reclamo el plan continúa Free salvo
contratación independiente. Instituciones, representación legal, responsable
sanitario, origen y desvinculación requieren auditoría especializada.

## 12. RBAC

| Rol histórico | Equivalencia candidata | Resultado |
|---|---|---|
| Súper Administrador | director + admin scopiado + break-glass | `superseded`; acceso universal rechazado |
| Operador Económico | `billing_subscription_operator` | refinar; pagos manuales son R3 |
| Asistencia Técnica | support + technical viewer | auditoría especializada |
| Verificación/Clasificación | claim reviewer + moderator | separar permisos y scopes |
| Mercadotecnia/Difusión | rol o permission set pendiente | auditoría especializada |
| Citas Globales | servicio scopiado/temporal pendiente | auditoría especializada |
| Titular | ownership + rol funcional por entidad | separar modelos |
| Asistente | operador Agenda scopiado | no es operador de plataforma |
| Responsable Sanitario | rol institucional pendiente | no implica ownership automático |

2FA disponible para perfiles reclamados refina AUTH-004; no sustituye MFA
obligatorio de operadores internos por riesgo. Passkeys quedan futuras. Ningún
plan concede un rol interno y ningún rol interno depende de plan.

## 13. Triggers y notificaciones

Los 22 triggers históricos quedaron cubiertos 22/22:

| # | Evento | Reconciliación |
|---:|---|---|
| 1 | nueva cita/estudio | confirma evento; productor/canal en PG-05 |
| 2 | recordatorio de cita | refina; frecuencia T-24 no aprobada |
| 3 | cancelación/reprogramación | confirma evento candidato |
| 4 | invitación a reseña | frecuencia/reenvío requieren auditoría |
| 5 | nueva reseña | refina buzón y scope del perfil |
| 6 | resultados listos | requiere auditoría clínica y token seguro |
| 7 | egreso/alta | futuro hospitalario especializado |
| 8 | inasistencia | lista negra rechazada; señal de riesgo revisable |
| 9 | vencimiento | refina eventos de lifecycle; días no aprobados |
| 10 | vencimiento + prórroga | conflicto D+8 frente a 15 días |
| 11 | prórroga agotada | conflicto de frecuencia y comportamiento |
| 12 | pago/renovación exitosa | confirma evento, protege Stripe |
| 13 | inactividad prolongada | umbral 45 días no aprobado |
| 14 | asignación de recurso | futuro hospitalario |
| 15 | coincidencia de horarios | refina alerta Agenda |
| 16 | firma pendiente | auditoría clínica/operativa especializada |
| 17 | insumos críticos | futuro hospitalario |
| 18 | solicitud de vínculo | auditoría de afiliación/ownership |
| 19 | aceptación de vínculo | auditoría de afiliación/notificación |
| 20 | campaña farmacéutica | sólo opt-in granular y aprobación |
| 21 | RSVP | futuro, configurable |
| 22 | patrocinio por vencer | futuro, configurable |

Separación de canales:

- buzón interno y tarea de operador son superficies diferentes;
- email es el canal inicial acordado, sujeto a delivery y preferencias;
- WhatsApp y push son futuros y requieren opt-in/app/proveedor/costo;
- seguridad, pagos, activación, vencimiento, grace, acceso y legal no pueden
  desactivarse;
- reseñas, perfil incompleto, promoción, eventos y contenido son configurables.

La frase “correo siempre se envía” contradice “correo desactivable”; queda
`conflict_requires_director`. Frecuencias y desfase de una hora no se aprueban.

## 14. Backoffice

Se adopta como invariante que la operación ordinaria no interviene DB
directamente. Edición in-context, guardar/borrador/publicar, historial,
reversión, before/after, cola, SLA y escalamiento se refinan bajo caso, scope,
riesgo, approval y auditoría.

| Acción | Riesgo propuesto | Condición |
|---|---|---|
| reenviar enlace de pago | R1 | canal consentido, idempotencia y audit |
| aplicar prórroga | R2 | caso, motivo, duración aprobada; no cambia alta/ranking |
| registrar/acreditar pago manual | R3 | referencia, comprobante, conciliación y doble aprobación |
| override de estado de pago | R3 | excepción explícita, nunca bypass silencioso |
| cambiar plan manualmente | especializado/R3 | no reabrir ni puentear Stripe |
| emitir/corregir CFDI | especializado | contrato fiscal separado |

No se implementan SPEI, pagos manuales, CFDI ni un selector administrativo.
`dryRun:true` queda como requisito futuro para agentes y acciones sensibles.

## 15. IA

| Capability separada | Fuente histórica | Resultado reconciliado |
|---|---|---|
| `AI-CONTENT-WRITING` | perfil/artículos/SEO | conflicto/auditoría de plan, fuentes y revisión |
| `AI-IMAGE-GENERATION` | Standard+ | `conflict_requires_director`; moderación/derechos/costo |
| `AI-MEDICATION-INTERACTION` | Tratamiento/Recetas | auditoría PG-04/PG-11; no upsell automático |
| `AI-PROFESSIONAL-AGENT` | Professional, chat/voz/Agenda | refina intención actual; provider/cuota/consentimiento pendientes |
| `AI-INTERNAL-OPERATIONS` | backoffice futuro | defer; allowlist, dry-run, caso y aprobación |
| `AI-INTERNAL-SUPERVISOR` | todos los campos | `reject_for_safety` |

Toda capability requiere plan o autoridad, riesgo, datos mínimos, presupuesto,
human-in-the-loop, logging saneado, auditoría, degradación y dependencia de
proveedor. No se seleccionó proveedor ni se afirmó que los agentes estén listos
por la frase histórica. El modelo supervisor se sustituye por scope mínimo,
herramientas permitidas, dry-run, aprobación humana y prohibiciones explícitas.

## 16. Máquinas de estado

Se registran 11 máquinas independientes; esto no obliga a crear 11 tablas:

1. `commercial_subscription_state`;
2. `capability_state`;
3. `ownership_state`;
4. `claim_request_state`;
5. `publication_moderation_state`;
6. `operator_account_state`;
7. `support_case_state`;
8. `notification_delivery_state`;
9. `appointment_state`;
10. `clinical_document_state`;
11. `privileged_session_state`.

Sus autoridades, precondiciones, transiciones, expiración, auditoría y grupos
especializados deben cerrarse por separado. No se fusionan claim, ownership,
publicación, suscripción o capability en un `status` genérico.

## 17. Requisitos confirmados

Quince requisitos confirman dirección actual, entre ellos: plan Free después de
claim; acceso bloqueado hasta revisión; contacto separado de clínica; no
delegabilidad; Receta anterior preservada; reimpresión auditada; facturación a
paciente separada; eventos de cita/pago; opt-in farmacéutico; bitácora saneada;
delivery auditable y dry-run futuro. Uno adicional, no operar DB ordinariamente,
se clasifica `adopt_current` como invariante documental.

Confirmar no equivale a afirmar implementación. Cada requisito conserva su
grupo, deuda y prueba futura.

## 18. Requisitos refinados

Los 27 refinamientos principales son:

- “ficha automática” pasa a contacto operativo mínimo;
- expediente requiere acción explícita/capability/rol/consentimiento;
- Receta corregida crea una nueva versión;
- titular/asistente se separan de ownership y scope;
- claim se divide en request, ownership y publicación;
- 2FA de perfil no sustituye MFA interno;
- eventos, prioridades y categorías no fijan canal/frecuencia;
- grupos médicos usan detección, revisión e invitación;
- publicación usa máquina propia;
- backoffice usa caso, risk, before/after, approval y audit;
- agente Professional conserva plan hipotético pero agrega cuota, provider,
  handoff y revisión humana.

## 19. Conflictos

Diez requisitos quedan `conflict_requires_director`:

1. ficha automática histórica frente a expediente explícito;
2. canales por plan frente a consentimiento/criticidad;
3. grace D+8 frente a 15 días;
4. recordatorios postgrace sin frecuencia aprobada;
5. rol condicionado por plan frente a separación de modelos;
6. generación de imágenes Standard+ frente a plan actual no decidido;
7. email siempre enviado frente a email desactivable;
8. prórroga discrecional frente a lifecycle gobernado;
9. reiteración histórica de image generation sin autoridad adicional;
10. frecuencia/comportamiento de grace dependiente de la opción elegida.

El conflicto de grace compara experiencia, recuperación, costo, riesgo,
continuidad clínica, complejidad, avisos, datos y soporte. No se elige ocho ni
quince días. DEC-007 queda `revised_pending_director_approval`.

## 20. Requisitos rechazados por seguridad

Cuatro requisitos se rechazan literalmente:

- lista negra que bloquea reservas por inasistencia;
- autoenrollment en grupos de padecimientos sin confirmación;
- IA supervisora con lectura/escritura de todos los campos;
- tratamiento estigmatizante equivalente en triggers de inasistencia.

La alternativa es `attendance_risk_flag` con criterios, vigencia, revisión,
corrección, excepción y audit; y para padecimientos:
`detección → sugerencia → revisión → confirmación → publicación`.

## 21. Requisitos superseded

El superadministrador de acceso universal cotidiano queda `superseded`. Se
sustituye por `platform_director`, permisos explícitos/scopiados,
`break_glass_superadmin`, MFA, caso, expiración, doble aprobación, auditoría y
revisión posterior. La existencia histórica de dos cuentas no fija el número
actual de directores ni autoriza un bypass.

## 22. Requisitos futuros

Siete requisitos se difieren: passkeys, recursos hospitalarios, RSVP,
patrocinios, insumos, IA operativa y navegación avanzada. Veintiocho requieren
auditoría especializada: documentación de claim, instituciones, responsable
sanitario, roles de marketing/citas globales, clínica, facturación, canales,
provider IA, pagos manuales/CFDI y señales de riesgo.

Ninguno cuenta como pantalla, API, tabla, rol o capability implementada.

## 23. Impacto DEC-001–DEC-011

| DEC | Estado revisado | Impacto histórico |
|---|---|---|
| DEC-001 | `confirmed_pending_approval` | confirma cinco tiers/códigos; aliases no canónicos |
| DEC-002 | `refined_pending_approval` | clínica exige acción, scope y consentimiento |
| DEC-003 | `split_into_multiple_decisions` | Recetas e IA son elecciones independientes |
| DEC-004 | `split_into_multiple_decisions` | galería no debe mezclar claim/ownership/publicación |
| DEC-005 | `refined_pending_approval` | nuevas capabilities requieren unidades/costo/enforcement |
| DEC-006 | `conflict_pending_approval` | lifecycle y duración D+8 vs 15 |
| DEC-007 | `conflict_pending_approval` | grace cambia capabilities y avisos |
| DEC-008 | `confirmed_pending_approval` | preservar/freeze; no borrar por impago |
| DEC-009 | `refined_pending_approval` | locks y frontera ficha/contacto/Expediente |
| DEC-010 | `refined_pending_approval` | Agenda vincula contacto, no expediente automático |
| DEC-011 | `split_into_multiple_decisions` | bridge, no delegabilidad, RBAC y backoffice |

Las 11/11 continúan bloqueando la Actividad 2. Ninguna fue aprobada,
implementada, renumerada oficialmente o retirada.

## 24. Paquete revisado

Se recomienda aumentar el borrador de 11 a 20 decisiones atómicas. El número
oficial permanece 11 hasta aprobación del director.

| ID borrador | Decisión propuesta |
|---|---|
| RDD-001 | códigos canónicos y aliases |
| RDD-002 | entitlement clínico: Expediente/Recetas/archivos |
| RDD-003 | quota de galería |
| RDD-004 | schema general de cuotas |
| RDD-005 | elegibilidad de claim y perfiles pagados |
| RDD-006 | máquina claim request y evidencia |
| RDD-007 | máquina ownership e instituciones |
| RDD-008 | publicación/moderación |
| RDD-009 | duración grace: 8 vs 15 |
| RDD-010 | capabilities y avisos en grace |
| RDD-011 | downgrade, retención y read/export |
| RDD-012 | Agenda contact vs Expediente |
| RDD-013 | no delegabilidad y consentimiento clínico |
| RDD-014 | locks/upsell y disclosure seguro |
| RDD-015 | roles internos, marketing y citas globales |
| RDD-016 | pago manual, prórroga y override |
| RDD-017 | canales, consentimiento y clases obligatorias |
| RDD-018 | capabilities IA, plan, cuota y costo |
| RDD-019 | attendance risk y grupos de padecimientos |
| RDD-020 | corrección/reimpresión/integridad de Recetas |

Cada decisión permite `aprobar`, `modificar`, `rechazar` o `diferir`. El delta
de nueve evita que una respuesta apruebe accidentalmente temas con autoridades,
riesgos o grupos distintos.

## 25. Impacto sobre deuda

El registro pasa de 99 a 105 entradas, sin borrar ni reutilizar IDs.

Altas confirmadas:

- `AGD-006`: política de riesgo por inasistencia/cancelación;
- `CLN-007`: no delegabilidad e integridad documental clínica;
- `SUB-005`: conciliación y override de pagos manuales;
- `ADM-009`: máquina de publicación/moderación;
- `ADM-010`: scopes de mercadotecnia y citas globales;
- `AI-003`: descomposición de capabilities y seguridad IA.

Se amplían `CAP-004/005/006`, `OWN-001..003`, `AGD-003/004`, `PAT-003`,
`NOT-003..005`, `AI-001/002`, `DOC-001..003`, `ADM-001/002/007`. No se
duplica Stripe, la frontera Agenda/Expediente, channel preferences ni claim.

## 26. Impacto sobre inventario

Los totales implementados permanecen sin cambio: 953 entradas, 143 superficies,
166 APIs, 47 entidades y 31 pantallas. Los PDF aportan únicamente:

- 95 requisitos históricos documentados;
- superficies futuras requeridas;
- roles candidatos no implementados;
- elementos rechazados/superseded.

No se suman endpoints, tablas, eventos, roles, pantallas o capabilities por
estar mencionados históricamente.

## 27. Impacto sobre PG-01–PG-11

| Grupo | Impacto |
|---|---|
| PG-01 | decisiones de plan/capability y separación de modelos |
| PG-02 | claim, ownership, auth, MFA y lifecycle de operador |
| PG-03 | contacto Agenda, attendance risk y frontera clínica |
| PG-04 | no delegabilidad, Recetas e interacción medicamentosa |
| PG-05 | 22 triggers, canales, consentimientos y delivery |
| PG-06 | grace, pagos manuales, prórroga y override |
| PG-07 | publicación, moderación, grupos y contenido |
| PG-08 | RBAC, scopes, privacidad, audit y approvals |
| PG-09 | navegación, locks, copy y accesibilidad |
| PG-10 | backoffice, roles, casos y colas |
| PG-11 | IA, voz, provider, costo y degradación |

AWS 24/24 permanece cerrado offline y no se reabre. Deployment sigue no
iniciado y tráfico público `NO-GO`.

## 28. Decisiones requeridas

El director debe revisar el paquete completo y responder por cada RDD. Son
especialmente bloqueantes: códigos/entitlements; tres máquinas de claim;
publicación; grace; downgrade; Agenda/Expediente; no delegabilidad; canales;
IA; e integridad de Recetas. Roles, pagos manuales y riesgos pueden diferirse a
su grupo, pero no deben implementarse desde estas fuentes.

Hasta aprobación explícita, permanecen defaults seguros: no borrar datos por
impago, no crear expediente automáticamente, no delegar actos clínicos, no
acreditar pagos manuales, no activar canales/proveedores futuros, no publicar
grupos automáticamente y no conceder acceso universal.

## 29. Criterios de aprobación

- Cada decisión se aprueba, modifica, rechaza o difiere explícitamente.
- La autoridad y el contrato canónico afectados quedan identificados.
- Plan, capability, rol, scope, ownership y estados permanecen separados.
- Clínica, pagos y R3 tienen consentimiento/aprobación/auditoría proporcional.
- Frecuencia, cuota o proveedor nunca se infieren de una fuente histórica.
- Requisitos rechazados no reaparecen mediante aliases.
- La Actividad 2 recibe un paquete versionado aprobado antes de iniciar.

## 30. No repetición

Esta actividad realizó cero llamadas AWS/CDK/HTTP/Stripe/pagos/email/maps/IA,
cero ejecución PHP/SQL/npm/app/browser, cero OCR, cero acceso a bases, secretos,
datos personales concretos o datos clínicos concretos, y cero cambios de código,
schema o tests. Los PDF no se editaron. No se reabrió AWS 24/24, no se incrementó
el contador y no se inició la Actividad 2.

## 31. Historial

| Versión | Fecha | Cambio |
|---|---|---|
| 1.0.0 | 2026-07-18 | Incorporación byte-identical de ocho fuentes; 29/29 páginas, 95 requisitos, 22 triggers, 11 DEC revisadas y borrador de 20 decisiones |
