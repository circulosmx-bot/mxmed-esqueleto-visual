# Auditoría de planes, capacidades, ownership y lifecycle — México Médico

## 1. Portada y versión

- Contrato: `MXMED_PLANS_CAPABILITIES_OWNERSHIP_LIFECYCLE_AUDIT_V1`.
- Actividad: `PRODUCT-AUDIT/MXMed-Plans-Capabilities-Ownership-Lifecycle-Audit-01`.
- Grupo: `PG-01`.
- Contador: Actividad 1 de 22.
- Versión: `1.1.0`.
- Fecha contractual: 2026-07-17.
- Estado: `PASS_STATIC_AUDIT`; PP278 formaliza aprobación comercial documental
  posterior, sin validación runtime ni implementación.
- Rama de trabajo: `audit/mxmed-plans-capabilities-ownership-lifecycle`.
- Base: `39441f530b028b7f47b34493fbebb5b5cc49ce14`.

Estado vigente del gate: 30/30 decisiones `director_approved`; Actividad 2
`UNBLOCKED_READY_NOT_STARTED`. Los apartados de decisiones pendientes
conservan el snapshot de PP276/PP277 y son superseded por el amendment final.

## 2. Propósito

Reconciliar las fuentes versionadas que hoy describen planes, capacidades,
ownership, roles, scope y ciclo de suscripción, y dejar una base verificable
para la Actividad 2. La auditoría distingue lo observado, lo documentado y la
propuesta: no convierte una cadena aislada, una card, un fixture o un perfil
AWS en autoridad comercial.

## 3. Alcance

Se inspeccionaron estáticamente frontend, PHP, servicios, repositorios,
schemas, migraciones, fixtures, documentación, QA y contratos de
infraestructura relacionados. Se cubren los cinco planes, aliases, 14 fuentes
base, 41 claves de capability, cuotas y límites, nueve roles funcionales, seis
estados base de ownership, 11 roles internos preliminares, lifecycle,
grace/downgrade, Agenda sin Expediente y equivalencia entre capas.

## 4. Limitaciones estáticas

No se ejecutaron PHP, SQL, tests, servidor, navegador, HTTP, Stripe, pagos,
correo, IA, AWS ni CDK. No se consultaron datos reales ni secretos. Por ello,
la existencia física de tablas, configuración de entorno, contenido real,
sesiones, side effects y conducta de proveedores permanecen
`requires_runtime_verification`. Las afirmaciones de código describen rutas
versionadas, no ejecución en un ambiente.

## 5. Fuentes revisadas

Fuentes rectoras:

- [Registro maestro de deuda](./MXMED_REGISTRO_MAESTRO_DE_DEUDA_PRODUCTO.md).
- [Inventario global](./MXMED_INVENTARIO_GLOBAL_PANTALLAS_FUNCIONES_APIS_DATOS.md).
- [Requisitos del plano de operadores](./MXMED_REQUISITOS_PLANO_CONTROL_OPERADORES_ROLES_GOBIERNO.md).
- [Contrato maestro PP-Decisiones](./PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md).

El registro detallado contiene 14 fuentes base y ocho fuentes adicionales. Las
adicionales son `PublicProfileController`, `PublicProfileRepository`, el
schema y seed del catálogo, `CurrentSubscriptionRepository`, el resolver de
precios, el seed DEV de precios y el write de suscripción con aceptación.
También se revisaron rutas de Agenda, pacientes, clínica, perfiles,
suscripciones y los perfiles AWS sólo para evitar colisiones semánticas.

## 6. Método y niveles de evidencia

Cada hallazgo registra ruta, referencia, tipo, vigencia, autoridad y conflicto.
Se aplicaron estos niveles: `confirmed_direct_reference`,
`confirmed_code_and_documentation`, `documented_only`, `code_only`,
`probable_by_structure`, `requires_runtime_verification` y
`not_found_in_scanned_scope`.

Las autoridades se clasificaron como `director_business_requirement`,
`canonical_contract_candidate`, `backend_enforcement`,
`frontend_presentation`, `database_representation`,
`infrastructure_runtime_profile`, `fixture_or_test_value`,
`historical_or_superseded`, `projection_only` o `unresolved`. “Implementado”
en este documento exige más que la emisión de una clave; la mayoría de las 41
claves sólo tienen implementación parcial en el read-model público.

## 7. Separación de modelos

| Modelo | Pregunta | Fuente observada principal | Regla de separación |
|---|---|---|---|
| Commercial plan | ¿Qué servicio contrató la entidad? | `profile_subscriptions`, catálogo y aliases | nunca concede rol interno |
| Profile ownership | ¿Quién puede administrar el perfil? | documentación; código público aún fijo | no es un plan ni un rol |
| User functional role | ¿Qué función cumple el actor? | sesión, Agenda, grupos, enlaces | requiere scope por entidad |
| Entity scope | ¿Sobre qué entidad actúa? | `doctor_id`, `entity_type/id`, relaciones | no se infiere sólo del rol |
| Subscription lifecycle | ¿En qué estado está el contrato? | suscripción, checkout y pago | no es una capability |
| Capability | ¿Qué función está disponible? | política backend parcial y UI | depende de plan y contexto |
| Quota | ¿Cuánto puede usarse? | límites dispersos | unidad/ventana/enforcement explícitos |
| Internal operator role | ¿Qué puede hacer el personal MXMed? | contrato futuro PP275 | independiente del plan |
| Action risk | ¿Qué control reforzado exige la acción? | R0–R3 documentado | independiente de precio |
| Infrastructure profile | ¿Qué capacidad AWS se despliega? | PP263–PP264/IaC | nunca es plan de cliente |

## 8. Intención de negocio del director

La siguiente es entrada autoritativa de negocio, no constatación de código:

| Variante | Intención | Contraste estático |
|---|---|---|
| Free/unclaimed | directorio y datos básicos; sin administración/login; ubicación por enlace externo | el perfil público cae a `free`, pero claim/ownership no está conectado |
| Free/claimed | mismo plan; login, recuperación, edición textual y una foto optimizada | no está implementado: `is_claimed=false` y `ownership_status=null` |
| Basic | datos ampliados, `tel:`, `wa.me`, galería limitada | contacto sí tiene gates parciales; galería de backend empieza en Standard |
| Standard | Agenda y notificaciones operativas; no presupone Expediente | card y read-model público asocian Agenda; API pública no localiza gate de plan |
| Optimum | hipótesis Agenda + Expediente + Recetas + archivos | card frontend lo anuncia; enforcement clínico por plan no se localizó |
| Professional | IA/voz activada, cuota/presupuesto y revisión humana | card/flag de IA parciales; cuota, proveedor y enforcement no cerrados |

## 9. Inventario de fuentes de plan

| ID | Fuente/referencia | Uso observado | Autoridad | Vigencia/propuesta |
|---|---|---|---|---|
| INV-CAP-001 | `api/subscriptions/index.php:1086` | fixture DEV Stripe con plan | fixture/test | `fixture_only` |
| INV-CAP-002 | `assets/js/app.js:1276,59221-59254` | opciones, aliases, cards y precios visuales | frontend presentation | `adapt` |
| INV-CAP-003 | `docs/PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md:1-620` | intención, reglas históricas y decisiones | director requirement/candidate | `retain` |
| INV-CAP-004 | `docs/assets/js/app.js:3833` | corrección de texto “Óptimo”; falso positivo de plan | historical/superseded | `remove_after_migration` |
| INV-CAP-005 | `modules/clinical/ui/viewer.php:104` | valor `standard` de modo visual; falso positivo | unresolved/non-plan | `retain` fuera del catálogo |
| INV-CAP-006 | `DoctorContactPointsController.php:77` | allowlist de visibilidad mínima | backend enforcement | `adapt` |
| INV-CAP-007 | cleanup `free/annual` legacy | limpieza de representación histórica | database representation | `supersede` |
| INV-CAP-008 | `PublicProfilePlanCapabilities.php:8-279` | aliases, tiers y capabilities públicas | backend enforcement | `adapt` |
| INV-CAP-009 | `ActivateSubscriptionAfterPaymentService.php:60` | ranks y activación/upgrade | backend enforcement | `adapt` |
| INV-CAP-010 | `BuildSubscriptionPaymentActivationStateService.php:37` | ranks y beneficios de upgrade | backend enforcement | `adapt` |
| INV-CAP-011 | `BuildSubscriptionPaymentRoutePreviewService.php:47` | ranks y precio/rutas | backend enforcement | `adapt` |
| INV-CAP-012 | `CreateSubscriptionCheckoutIntentService.php:50` | ranks y validación de target | backend enforcement | `adapt` |
| INV-CAP-013 | `CurrentSubscriptionReadModelService.php:14` | plan efectivo y fallback | backend enforcement | `adapt` |
| INV-CAP-014 | `profiles/doctor.php:197-210` | override sólo local/dev | fixture/test | `fixture_only` |
| PLAN-ADD-001 | `PublicProfileController.php:23-66` | selecciona plan del perfil u override | backend enforcement | `adapt` |
| PLAN-ADD-002 | `PublicProfileRepository.php:458-480` | cinco campos legacy de plan | backend enforcement | `remove_after_migration` |
| PLAN-ADD-003 | `2026_06_19_create_subscription_plan_lifecycle.sql:57-134` | catálogo y suscripción | database representation | `adapt` |
| PLAN-ADD-004 | `2026_06_19_seed_subscription_plans_catalog.sql:13-73` | cinco códigos/periodos | database representation | `retain` |
| PLAN-ADD-005 | `CurrentSubscriptionRepository.php:19-187` | lee catálogo/candidato actual | backend enforcement | `adapt` |
| PLAN-ADD-006 | `SubscriptionPlanPriceResolverService.php` | precio server-side | backend enforcement | `retain` |
| PLAN-ADD-007 | `2026_06_22_seed_subscription_plan_prices_dev.sql:1-80` | precios DEV no aprobados | fixture/test | `fixture_only` |
| PLAN-ADD-008 | `CreateSubscriptionWithAcceptanceService.php` | write directo histórico sin pago | backend enforcement/historical | `supersede` |

Las 14/14 fuentes base quedaron reconciliadas. Dos eran coincidencias no
comerciales; su presencia no las convierte en fuentes de plan. No existe una
fuente canónica actual única.

## 10. Planes y aliases

| Canónico propuesto | Labels/aliases observados | Observación |
|---|---|---|
| `free` | free, gratis, gratuito, Gratuito, `free_default` | `gratis` está en frontend; `free_default` es estado/read-model, no plan nuevo |
| `basic` | basic, basico, básico, Básico | normalizado en varias capas |
| `standard` | standard, estandar, estándar, Estándar | colisiona con “standard” no comercial del viewer |
| `optimum` | optimum, optimo, óptimo, Óptimo | card usa `optimo` |
| `professional` | professional, profesional, Profesional, `pro` | `pro` sólo es alias UI |

Los IDs numéricos del catálogo y UUID/price IDs identifican filas/precios, no
son aliases de plan. `deploymentProfile`, `runtimeCapabilityProfile`,
`professional-ai-v1`, `launch-lean` y `production-standard-v1` son perfiles
de infraestructura y quedan fuera del plan comercial. La propuesta de códigos
ingleses ASCII está pendiente de aprobación y no renombra datos existentes.

## 11. Plan contratado y efectivo

`profile_subscriptions` representa `plan_code`, `contracted_plan_code` y
`effective_plan_code`; también conserva fechas, status y enlaces de renovación.
`CurrentSubscriptionReadModelService` vuelve a calcular el efectivo: usa el
contratado/plan almacenado durante vigencia o grace, y devuelve `free` tras
expirar fuera de grace. El snapshot `effective_plan_code` no es autoridad por
sí solo.

Un checkout `pending_payment` no crea una suscripción activa. La activación
exige aceptación y pago confirmado; el upgrade crea nueva fila con el plan
target, mantiene el vencimiento y marca la anterior `renewed`. El downgrade
inmediato sólo está bloqueado visualmente y se presenta “al renovar”; no se
localizaron `scheduled_plan` ni transición backend canónica. Tampoco hay un
`pending_plan` transversal. El frontend contiene simulación QA y cálculo
duplicado de ranks/precio, por lo que no es autoridad.

Propuesta de modelo lógico, sin imponer columnas: `contracted_plan`,
`effective_plan`, `scheduled_plan` y `subscription_state`, resueltos por una
política backend única y expuestos al frontend como read-model.

## 12. Inventario de capacidades

Las 41/41 claves base se emiten desde
`PublicProfilePlanCapabilities`. “Directa” significa que participa en la
matriz privada de 17 booleanos; “derivada” es alias/contexto; “placeholder”
está fijada a falso; ninguna etiqueta prueba enforcement end-to-end.

| Clave | Clase | Plan observado / estado | Gate y dependencia |
|---|---|---|---|
| `allow_review_replies` | directa | Standard+ | backend read-model; perfil público requerido |
| `can_allow_review_replies` | derivada | Standard+ | alias de policy |
| `can_show_contact_buttons` | derivada | Basic+ | no considera disponibilidad del dato |
| `can_show_promotional_packages` | derivada | Standard+ | fuente comercial requerida para mostrar |
| `can_show_public_agenda` | derivada | Standard+ | perfil público requerido para mostrar |
| `has_ai_agent` | derivada parcial | Professional | flag de salida; no gate de IA localizado |
| `has_ai_prescription_safety` | placeholder | ninguno | fijo `false` |
| `has_ai_profile_writer` | placeholder | ninguno | fijo `false` pese a documentación histórica |
| `has_commercial_profile_data` | contexto | cualquier plan si hay fuente | depende de `commercial_source_ready` |
| `has_ecosystem_links` | placeholder | ninguno | fijo `false` |
| `has_insurance_affiliations` | derivada | Standard+ con fuente | alias contextual |
| `has_promotions` | derivada | Standard+ con fuente | alias contextual |
| `has_public_agenda` | derivada | Standard+ con perfil | alias contextual |
| `has_public_contact` | derivada | Basic+ con dato | alias contextual |
| `has_public_profile` | contexto | cualquier plan | no es entitlement de plan |
| `has_reviews` | derivada | todos, con perfil | read-model solamente |
| `has_video_consultation` | placeholder | ninguno | fijo `false` |
| `show_accepted_insurances` | derivada | Standard+ con fuente | alias de seguros |
| `show_ai_claims` | placeholder | ninguno | fijo `false` |
| `show_claim_button` | derivada | Free, pero fuente no lista | ownership/source bloquean |
| `show_claim_profile` | directa | Free | suprimida si claimed; contexto hoy no conectado |
| `show_clickable_map` | directa | Basic+ | no prueba mapa externo en todas las UI |
| `show_consultation_details` | directa | Standard+ | requiere datos comerciales |
| `show_consultation_fee` | derivada | Standard+ con fuente | alias contextual |
| `show_contact_buttons` | directa/contextual | Basic+ con dato | backend read-model + renderer |
| `show_gallery` | directa | Standard+ | sin persistencia/cuota canónica localizada |
| `show_gps_directions` | directa | Basic+ | renderer/contexto parcial |
| `show_insurances` | directa/contextual | Standard+ con fuente | backend read-model |
| `show_internal_inbox` | directa/contextual | Basic+ con contacto | no prueba buzón transaccional |
| `show_internal_message` | derivada | Basic+ con contacto | alias de inbox |
| `show_logo` | directa | Basic+ | read-model; fuente de media parcial |
| `show_map_gps` | derivada | Basic+ | alias de directions |
| `show_phone` | directa/contextual | Basic+ con dato | min-plan adicional por contact point |
| `show_photo` | directa | Basic+ | contradice foto única esperada en Free claimed |
| `show_professional_review` | directa | Basic+ | read-model solamente |
| `show_promotional_packages` | directa/contextual | Standard+ con fuente | backend read-model |
| `show_promotions` | derivada | Standard+ con fuente | alias contextual |
| `show_public_agenda` | directa/contextual | Standard+ con perfil | API pública sin gate equivalente localizado |
| `show_reviews` | directa/contextual | todos con perfil | persistencia/moderación no cerradas |
| `show_video_consultation` | placeholder | ninguno | fijo `false` |
| `show_whatsapp` | directa/contextual | Basic+ con dato | enlace, no mensajería automatizada |

Delta conceptual: perfil administrable, notificaciones, contacto de paciente,
Expediente, Recetas, archivos clínicos, colaboración, suscripciones e IA/voz
aparecen en cards/documentación/código de dominio, pero no forman parte de las
41 claves del read-model público. Se registran como 11 capacidades de dominio
candidatas, no como botones ni como implementación confirmada.

## 13. Matriz observada

`CURRENT_OBSERVED_MATRIX`:

| Grupo de capability | Free | Basic | Standard | Optimum | Professional |
|---|---|---|---|---|---|
| reviews visibles | policy true | true | true | true | true |
| claim profile | true, bloqueado por fuente actual | false | false | false | false |
| foto/logo/reseña profesional | false | true | true | true | true |
| teléfono/WhatsApp/inbox/mapa | false | true si hay dato | true si hay dato | true si hay dato | true si hay dato |
| Agenda pública | false | false | true si perfil público | true | true |
| promociones/galería/seguros/detalles | false | false | true, con dependencias | igual | igual |
| IA agent flag | false | false | false | false | true |
| Expediente/Recetas | no definidos por policy | no definidos | card no | card sí; backend no localizado | card sí; backend no localizado |

El backend de capabilities no distingue Standard, Optimum y Professional salvo
el flag derivado `has_ai_agent`. Esto contradice las cards y la intención
clínica. Además, el origen del plan público puede venir de campos legacy de
`profiles_doctors`, mientras el panel privado usa el read-model de
`profile_subscriptions`.

## 14. Matriz canónica propuesta

`PROPOSED_CANONICAL_MATRIX_V1`, pendiente de aprobación:

| Plan/ownership | Capacidades propuestas | Dependencias y quota | Grace/downgrade |
|---|---|---|---|
| Free/unclaimed | publicación básica, enlace de ubicación externo, claim CTA | perfil publicable; sin login owner | conservar publicación mínima |
| Free/claimed | lo anterior + login/recovery, edición textual, una foto optimizada | ownership `claimed`; quota `PROFILE_PHOTO=1` | preservar textos/foto; no borrar |
| Basic | perfil ampliado, `tel:`, `wa.me`, galería limitada | contacto verificado; tamaño/cantidad por decidir | ocultar exceso, preservar objetos |
| Standard | Basic + Agenda + notificaciones operativas | agenda/configuración/consentimiento de contacto | historial preservado; writes según estado |
| Optimum | Standard + Expediente + Recetas + archivos clínicos | consentimiento, rol/scope clínico, retención | lectura/exportación segura; nunca borrar por impago |
| Professional | Optimum + IA y voz activada | cuota/presupuesto/proveedor; revisión humana | no nuevas sesiones/costo; preservar resultados aprobados |

Cada celda debe materializarse en estados de capability, no sólo booleanos. El
upsell puede ser visible cuando es seguro; clínica, seguridad y operaciones
internas no se revelan por ese patrón.

## 15. Cuotas y tipos de límites

| Valor | Categoría | Fuente | Conclusión |
|---|---|---|---|
| 1 foto Free claimed | commercial quota propuesta | requisito director | explícita, no implementada |
| galería “limitada” Basic | commercial quota ambigua | requisito director | falta cantidad/unidad |
| 3 operadores Agenda | domain/technical quota | `OperatorsRepository::MAX_ALLOWED` | enforced backend; no depende de plan |
| 2 contactos en import legacy | technical limit | `DoctorContactPointsController:387` | contexto de importación, no quota de plan |
| alias 15 caracteres | technical limit | `OperatorsController:299` | validación técnica |
| teléfono 10–15 dígitos | technical/security validation | contact controller | no promesa comercial |
| motivo de cita 1000 chars | technical limit | `profiles/doctor.php` y PublicAppointments | frontend/backend alineados para ese campo |
| cancel reason 280 chars | technical limit | PublicAppointments | validación API |
| OTP 6 dígitos/TTL | security limit | PublicAppointments/PublicOtp | protección, no quota comercial |
| 365 días pagados, 0/lifetime free | contract duration | catálogo/read-model | no quota de uso |
| 699000/999000/1299000/2199000 cents | fixture QA | seed DEV | no precio productivo aprobado |
| 6990/9990/12990/21990 MXN | frontend reference | `app.js` | coincide con seed DEV en escala; no autoridad comercial |
| 25 MiB archivo; 50/500 GiB mes | technical target/projection | PP245 AWS | no quota de cliente |
| 25k/250k perfiles; 50k/500k expedientes | projection | PP245 AWS | dimensionamiento, no entitlement |
| 200/1000 GiB DB | cost safety/capacity | launch profiles | infraestructura, no plan comercial |
| 50/75/90/100/120% budget | cost safety cap | PP operations | control AWS, no capability de usuario |
| 100 users/500 profiles/50 actions/200 AI sessions | projection examples | contrato de auditoría | expresamente no convertir a quota |

No se localizó cuota comercial aprobada para citas, colaboradores por plan,
notificaciones, archivos clínicos, recetas, sesiones IA o minutos de voz. La
quota Agenda de tres operadores es real en código, pero su relación comercial
es `unresolved`.

## 16. Ownership

El código público no distingue hoy Free unclaimed/claimed de forma efectiva:
`PublicProfileController` fija `ownership_status=null`, `is_claimed=false` y
`claim_source_ready=false`. El schema `profiles_doctors` no contiene owner ID ni
estado de claim. La documentación describe `claim_pending`, `claimed`,
`rejected` y `needs_info`; el inventario base agrega `unclaimed`, `disputed`,
`suspended`, `transferred` y `revoked`.

Reconciliación base:

| Estado | Evidencia actual | Propuesta |
|---|---|---|
| unclaimed | documentado; fallback implícito | estado inicial explícito |
| claimed | booleano aceptado por policy, pero caller fijo false | requiere owner único y vínculo de cuenta |
| disputed | documentado | congelar transferencias/edición sensible |
| suspended | documentado; también existe status de publicación distinto | no confundir ownership/publication |
| transferred | documentado | transición gobernada y auditada |
| revoked | documentado | quitar administración, preservar entidad/datos |

`claim_pending` se añade a la máquina propuesta. `rejected` y `needs_info` se
tratan como estados del workflow de claim, no como ownership estable. No se
localizaron prevención de doble ownership, transferencia, revocación ni API
canónica de claim; permanecen deuda OWN-001..003.

## 17. Scope por entidad

| Entidad | Scope observado | Enforcement | Gap principal |
|---|---|---|---|
| profile/doctor | `doctor_id`, sesión y fila pública | filtros parciales por doctor | owner/claim no conectado |
| medical group | membership/role en tablas de Agenda | controlador específico | equivalencia transversal pendiente |
| agenda/appointment | doctor/consultorio/operator | controller y repository filtran doctor en varias rutas | public booking sin plan gate |
| patient contact | patient/doctor link | lookup por doctor en reserva | política de alta/consentimiento pendiente |
| clinical record | patient/doctor/encounter | APIs/repos clínicos separados | plan/collaborator matrix ausente |
| prescription/document | patient/doctor/document | endpoints clínicos | entitlement y downgrade ausentes |
| subscription | `entity_type + entity_id` | sesión/resolver + repository | no FK a entidad; runtime pendiente |
| review/comment | perfil/doctor documentado | parcial | ownership/moderation incompletos |
| notification | usuario/entidad implícitos | disperso | catálogo y delivery pendientes |
| support case | futuro | no localizado | modelo interno futuro |
| internal assignment | doctor-scoped Agenda operator | Agenda only | no confundir con plataforma |

La recepción de IDs no se declara vulnerabilidad por sí sola. Los candidatos
sin matriz completa se clasifican `requires_runtime_verification` y pasan a
PG-02/PG-08.

## 18. Roles funcionales

| Base | Estado reconciliado | Aliases/alcance |
|---|---|---|
| public | actor/acceso público parcial | no rol persistido |
| doctor | implementado parcial | owner/médico; scope por doctor |
| agenda_operator | implementado parcial | códigos reales `operator` y `assistant`, Agenda only |
| medical_group_member | implementado parcial | roles de membresía del grupo; no admin plataforma |
| administrator | shell/cadena conceptual; reclasificado | no se confirmó rol interno integral |
| moderator | documentado únicamente | candidato futuro `content_moderator` |
| patient_secure_link | implementado parcial | `patient_link_user`, acceso por enlace/token |
| qa_local | fixture/test only | nunca producción |
| system_webhook | actor de sistema | no persona ni rol comercial |

Los nueve roles base quedaron reconciliados con delta semántico: `assistant`,
`operator`, `owner`, `collaborator` y `patient_link_user` son aliases/candidatos
de dominio; `administrator` no tiene evidencia suficiente para conservar el
estado “implemented_partial” del inventario como rol de plataforma.

## 19. Roles internos

Los 11/11 roles preliminares de PP275 se auditaron. Todos permanecen
`documented_required_future`; no existe catálogo, login, MFA, asignación,
scope, approval ni lifecycle transversal implementado.

| Rol | Equivalente observado | Límite contractual |
|---|---|---|
| `platform_director` | ninguno | gobierno; R3 con controles |
| `break_glass_superadmin` | rol AWS homónimo no equivalente | sesión excepcional, temporal, doble control |
| `platform_admin` | cadenas/bandera de contacto no equivalentes | sin secretos, autoelevación ni último director |
| `operations_manager` | ninguno | operación y casos, no política crítica |
| `support_advisor` | ninguno | datos mínimos enmascarados |
| `profile_claim_reviewer` | moderator/claim docs sólo candidatos | sin clínica; disputa R3 |
| `billing_subscription_operator` | `operator/admin` conceptuales en suscripción | sin tarjeta/secretos; override R3 |
| `content_moderator` | `moderator` documentado | contenido, no suscripciones/clínica |
| `privacy_security_officer` | ninguno producto | acceso extraordinario gobernado |
| `technical_operations_viewer` | roles AWS no equivalentes | telemetría saneada, read-only |
| `audit_read_only` | rol AWS no equivalente | sin mutaciones |

## 20. Acciones R0–R3

| Riesgo | Acciones candidatas | Control mínimo propuesto |
|---|---|---|
| R0 | leer plan/estado/caso/telemetría saneada | auth, permission, scope, auditoría |
| R1 | reenviar aviso, reintentar delivery, reasignar tarea, pausa reversible | confirmación, motivo y auditoría |
| R2 | suspender cuenta, transferir perfil no disputado, cambiar rol, revocar sesiones | caso, MFA, reauth, motivo, rollback |
| R3 | director, break-glass, clínica extraordinaria, disputa, exportación masiva, eliminación/retención, payment override, seguridad | initiator≠approver, doble aprobación, expiración y audit inmutable |

Esta clasificación es propuesta; las acciones actuales de Agenda tienen audit
local, pero no implementan el contrato de plataforma.

## 21. Subscription lifecycle

Namespaces observados:

- `profile_subscriptions`: `draft`, `active`, `expiring_soon`,
  `grace_period`, `expired`, `inactive`, `cancelled`, `renewed`.
- checkout: `pending_contract` en schema, `pending_payment` en repository y
  `activated`; cancel/expiry se representan parcialmente.
- payment intent/event: `created`, `paid`, `failed`, `cancelled` y estados de
  processing.
- read-model: `free_default`, status persistido y fallback `expired` efectivo
  a Free.

No existe una máquina única que gobierne de punta a punta renovación, pago
fallido, past due, grace, restricted, cancelación y reactivación. Tampoco se
localizó writer que programe de forma canónica `active→grace_period→expired`.

`PROPOSED_SUBSCRIPTION_LIFECYCLE_V1`:

`free → pending_payment → pending_activation → active → renewal_due → past_due → grace → restricted → expired`

Ramas: pago/activación fallida → `failed`; cancelación → `cancelled`; upgrade o
renovación reemplazada → `superseded`; pago válido desde grace/restricted →
`active`. `superseded` unifica semántica de la fila anterior, aunque hoy el
código usa `renewed` para upgrade.

## 22. Grace

La representación existe mediante `grace_starts_at`, `grace_ends_at`, status
`grace_period` e `is_in_grace`. El read-model conserva el plan contratado como
efectivo mientras la ventana está activa y luego cae a Free. No existe duración
default ni writer/notificador/override de grace localizado; tampoco un mapa de
capabilities `read_only` durante ese estado.

Principio propuesto: duración aprobada explícitamente; cero borrado; pagos,
seguridad y exportación visibles; capacidades vencidas sin nuevos writes o en
read-only; recordatorios no desactivables; reactivación restaura entitlement
sin recrear datos. Hasta aprobación, el default seguro es no borrar, no
extender derechos indefinidamente y no inventar días.

## 23. Downgrade

La UI clasifica planes menores como “Disponible al renovar”; no se localizó
`scheduled_plan` ni transición backend de downgrade. El fallback por expiración
cambia el plan efectivo a Free, pero no implementa conducta por dato.

`DOWNGRADE_DATA_BEHAVIOR_V1` propuesto:

| Categoría | Preservación | Acceso/escritura tras downgrade |
|---|---|---|
| perfil público | conservar | ocultar premium; edición según plan |
| galería | conservar objetos | publicar hasta quota; seleccionar/archivar |
| Agenda | conservar historial | bloquear nuevas operaciones según contrato; exportar |
| patient contacts | conservar por propósito/retención | no convertir a Expediente |
| clinical records | nunca borrar por impago | lectura/exportación segura; no nuevos writes |
| recetas/archivos | preservar | controlar acceso/regeneración; no borrar |
| IA | preservar resultados aprobados según política | no nuevas sesiones/costos |
| internal operations | sin efecto | independencia total del plan |

Retención legal, ventana de exportación y sobre-quota requieren dirección,
privacidad y seguridad. El borrado automático por impago queda prohibido como
default seguro.

## 24. Upgrade y activación

El flujo protegido exige checkout/aceptación pendientes, PaymentIntent pagado,
evento procesado e idempotencia; crea una suscripción `active`. Upgrade valida
rank superior y mismo periodo, calcula ajuste, mantiene vencimiento, crea nueva
fila y enlaza la anterior como `renewed`. El read-model de activación muestra
beneficios tomados de `PublicProfilePlanCapabilities`.

No se localizó una recalculación/invalidación única que conecte la suscripción
activada con todas las APIs de Agenda, clínica y perfil público. La preparación
de dependencia, refresco UI, notificación y retry tienen cobertura desigual.
Queda protegido: `automatic_user_triggered_infrastructure_changes=false`; un
pago nunca cambia `deploymentProfile`, crea AWS ni activa IA/voz por sí solo.

## 25. Agenda sin expediente

La Agenda pública solicita nombre, teléfono, correo, fecha/hora, consultorio,
modalidad, tipo de paciente, DOB, sexo y motivo opcional. El backend valida esos
campos; el payload de creación crea o vincula un registro de paciente/contacto
y la cita persiste `patient_id`, no diagnóstico/alergias/medicación. El motivo
queda en el flujo/payload, no en una tabla clínica canónica.

Hallazgos:

- UI: el botón público depende de `show_public_agenda` Standard+.
- API: `PublicAppointmentsController::reserve` no localiza consulta de plan o
  capability equivalente.
- Datos: `agenda_appointments`, `agenda_public_appointment_flows` y tablas
  `patients_*`; contacto de paciente sí puede crearse antes de confirmación.
- Clínica: `ClinicalEncounterBridge` puede crear encounter automáticamente
  para una cita `completed` si `AGENDA_ENABLE_CLINICAL_ENCOUNTER_BRIDGE=1`.
  La creación pública inicial usa `pending_otp`, por lo que no dispara esa rama.
- No se localizó gate de plan/consentimiento alrededor del bridge.

Conclusión estática: Agenda puede operar con datos no clínicos sin Expediente,
pero la creación automática condicional de encounter existe y contradice la
regla documental “no automático”. Su activación real y llamadas quedan
`requires_runtime_verification`. DOB/sexo/motivo exceden el mínimo de contacto
y requieren decisión de minimización/consentimiento.

## 26. Funciones bloqueadas

Patrones observados: elementos ocultos por renderer, botones `disabled`, notas
read-only, badges/labels de plan, cards con “Mejorar ahora” o “Disponible al
renovar”, modal de checkout, razones `plan_not_included`/`source_not_ready` y
errores backend puntuales. No existe un contrato transversal de
`locked_upsell`; accesibilidad y denial equivalente varían.

Contrato propuesto:

| Estado | UI | API/write | Upsell |
|---|---|---|---|
| enabled | interactiva | permitido con auth/scope | no |
| read_only | visible, explicación | rechaza writes; permite lectura | opcional |
| locked_upsell | visible, lock, plan objetivo y CTA | rechazo determinista | sí |
| blocked_dependency | visible si seguro, explica dependencia | rechazo por dependencia | no engañoso |
| pending_activation | progreso/retry | sin write prematuro | no |
| grace_limited | banner y capacidades explícitas | sólo operaciones permitidas | reactivar |
| hidden_security | no revelar superficie | deny seguro | nunca |
| not_applicable | no renderizar | no aplica | no |

Un `disabled` HTML nunca es enforcement. Todo lock visible debe conservar foco,
nombre accesible, explicación y CTA; nunca aceptar datos para fallar después.

## 27. Frontend enforcement

Gates principales:

- `assets/js/app.js`: aliases, ranks, cards, precios de referencia, QA plan,
  upgrade/downgrade y navegación de pago; mezcla `presentation_only`,
  `duplicated_business_logic` y `QA_only`.
- `profiles/doctor.php`: consume `public_visibility`; oculta contacto/Agenda y
  permite override local/dev.
- editor de contact points: ofrece plan mínimo por contacto.
- cards clínicas anuncian Expediente/Recetas en Optimum, pero no son seguridad.

No se considera canónica ninguna decisión frontend. Los gates QA, localStorage
y overrides deben quedar fuera de producción y no alimentar writes
autoritativos.

## 28. Backend enforcement

Gates localizados:

- `PublicProfilePlanCapabilities`: matriz de visibilidad pública y razones.
- `PublicProfileController`: combina plan, publicación y disponibilidad de
  datos; ownership está desconectado.
- contact points: allowlists/campos prohibidos/plan mínimo.
- suscripciones: sesión/entity scope, ranks, precio server-side, idempotencia,
  aceptación, pago y activación.
- Agenda profesional: actor role/doctor scope en múltiples controllers.

Sin equivalente localizado:

- plan gate en reserva pública de Agenda;
- plan/capability gates en APIs clínicas, Recetas y archivos;
- ownership claim para edición del perfil;
- quota comercial de galería/IA;
- estados grace/restricted por capability.

Hay gates genéricos de sesión/scope, pero no sustituyen entitlements. Los
campos prohibidos están mejor definidos en contact points que en la matriz
transversal. La equivalencia completa exige QA de Actividad 2 y PG-08.

## 29. Datos

| Área | Representación | Brecha |
|---|---|---|
| catálogo | `subscription_plans`, code/label/period/duration | no contiene capabilities/version de policy |
| precios | `subscription_plan_prices` | seed DEV no comercial |
| suscripción | `profile_subscriptions` | estados textuales, sin FK, snapshot efectivo |
| activation | checkout, acceptance, payment intent/event, routes, idempotency | varios namespaces de estado |
| ownership | no tabla/owner state canónico localizado | claim sólo documental/read-model fijo |
| roles de usuario | Agenda operators, group memberships, session strings | sin catálogo transversal |
| roles internos | no tablas producto | futuro PP275 |
| capabilities | sólo código/read-model | no versión/override/audit canónicos |
| quotas | constantes/copy/config dispersos | sin unidad/ventana por plan |
| grace | fechas/status en suscripción | sin política/writer transversal |
| overrides | dev plan, QA plan, legacy profile fields | riesgo de divergencia |

Fuentes candidatas actuales son policy backend versionada, catálogo/precio en
datos y read-model; ninguna satisface por sí sola todo el contrato.

## 30. Equivalencia entre capas

| Dominio | Dirección/docs | Frontend | API/service | Data/tests | Estado |
|---|---|---|---|---|---|
| plan codes/aliases | aligned parcial | duplicado | duplicado | catálogo existe | conflicto de autoridad |
| perfil/contacto | Basic esperado | gates parciales | policy/contact gate | datos parciales | parcialmente aligned |
| Free claimed | requerido | no flujo canónico | caller fija false | sin owner fields | absent/conflict |
| Agenda | Standard | visible Standard+ | reserva sin plan gate | citas/flows | frontend/backend mismatch |
| Expediente/Recetas | Optimum hipótesis | card Optimum+ | gate por plan ausente | clínica separada | documented/frontend only |
| IA | Professional | card/flag | enforcement/quota ausente | provider futuro | documented/placeholder |
| lifecycle | completo requerido | labels parciales | read-model/activation parcial | estados dispersos | conflict |
| internal roles | 11 futuros | shells/cadenas | no plano integral | Agenda-only tables | documented only |

## 31. Conflictos

1. Basic debería incluir galería limitada según dirección, pero la policy actual
   inicia `show_gallery` en Standard.
2. Free claimed debería permitir edición/foto, pero ownership no está conectado
   y la foto inicia en Basic.
3. Optimum/Professional deberían diferenciar clínica/IA; la policy pública hace
   Standard/Optimum/Professional casi idénticos.
4. La card asigna Expediente/Recetas a Optimum, sin backend gate equivalente.
5. Agenda se oculta por plan en el perfil, pero su API pública no consulta el
   entitlement.
6. La documentación prohíbe creación automática de Expediente; existe un bridge
   condicional de cita completada a encounter sin gate de plan localizado.
7. El plan del perfil público puede proceder de campos legacy; el panel privado
   procede de `profile_subscriptions`.
8. `grace` conserva plan pagado en read-model, pero no define capacidades
   limitadas ni duración.
9. “Disponible al renovar” no tiene `scheduled_plan` backend.
10. Las 14 fuentes base incluyen dos falsos positivos no comerciales.
11. El inventario marcó `administrator` parcial; no se confirmó un rol interno
    integral y scopiado.
12. Precio UI y seed DEV coinciden, pero ambos declaran no ser precio productivo.

## 32. Fuente canónica propuesta

Opciones evaluadas:

| Opción | Ventaja | Riesgo |
|---|---|---|
| PHP catalog | enforcement cercano y versionado | JS/documentación pueden duplicarse |
| JSON shared PHP/JS | un artefacto validable y generable | consumo PHP/JS y build deben cerrarse |
| DB-driven | cambios comerciales auditables sin release | bootstrap, cache, privilegios y drift |
| Hybrid | policy estable versionada + datos comerciales variables | exige límites de autoridad explícitos |

Recomendación: `HYBRID_VERSIONED_POLICY_V1`.

1. Contrato de dirección aprobado y versionado.
2. Catálogo canónico de capability/lifecycle en backend, con schema validado.
3. Artefacto JSON generado para frontend/documentación; nunca lógica paralela.
4. Backend como enforcement y productor del read-model.
5. DB para catálogo comercial, precios, suscripción y overrides explícitos
   versionados/auditados; no para bypass de policy.
6. Frontend sólo consumidor/presentación.
7. Tests y fixtures derivados, con namespace no productivo.

La alternativa segura si el generador compartido no cabe en Actividad 2 es PHP
canónico con endpoint/read-model, eliminando gradualmente matrices JS. No se
recomienda DB-driven puro en V1.

## 33. Decisiones pendientes

| ID | Decisión | Recomendación/default seguro | Bloquea A2 |
|---|---|---|---|
| DEC-001 | códigos canónicos | aprobar cinco códigos ASCII; no migrar aún | sí |
| DEC-002 | plan de Expediente | Optimum+; deny si no aprobado | sí |
| DEC-003 | plan de Recetas | Optimum+ con clínica/consentimiento | sí |
| DEC-004 | galería por plan | Basic limitada; preservar exceso | sí |
| DEC-005 | cuotas exactas | ninguna cuota sin unidad/ventana/enforcement | sí |
| DEC-006 | duración grace | no inventar días | sí |
| DEC-007 | capabilities en grace | read-only/no writes, pagos/export visibles | sí |
| DEC-008 | downgrade/retención | preservar; no borrar por impago | sí |
| DEC-009 | visibilidad de locks | upsell accesible sólo para funciones seguras | sí |
| DEC-010 | alta patient contact desde Agenda | consentimiento/minimización explícitos | sí |
| DEC-011 | creación de encounter | acción explícita; bridge automático off | sí |
| DEC-012 | mínimo de directores | al menos uno; recomendar dos | no, PG-08/10 |
| DEC-013 | toda R3 con doble control | sí salvo emergencia documentada | no, PG-08 |
| DEC-014 | duración break-glass | corta, temporal, aprobada y revisada | no, PG-02/08 |
| DEC-015 | catálogo de roles internos | aprobar 11 o delta antes de crear | no, PG-10 |
| DEC-016 | payment override | sólo R3, sin tocar Stripe protegido | no, PG-06 |
| DEC-017 | acceso clínico extraordinario | caso+MFA+doble aprobación+audit | no, PG-08 |
| DEC-018 | retención | preservar hasta política legal aprobada | no, PG-07/08 |

Son 18 decisiones: 11 bloquean los valores funcionales de Actividad 2 y siete
pueden conservarse como invariantes/documentación para grupos posteriores.

## 34. Cruce de deuda

| Deudas | Hallazgo | Estado/implicación |
|---|---|---|
| CAP-001..009 | matriz, quota, estados, ownership, lifecycle, downgrade, lock y equivalencia no canónicos | confirmed/refined; Actividad 2 |
| CAP-010 | perfiles AWS distintos del plan | protected; no reabrir |
| OWN-001..003 | claim/disputa/transferencia no conectados | confirmed; PG-02 |
| AUTH-001..004 | Free claimed requiere auth/recovery/MFA por riesgo | refined; PG-02 |
| AGD-003/004 | frontera Agenda/Expediente y bridge condicional | confirmed/refined; PG-03 |
| CLN-002/004/005 | consentimiento, scope y retención clínica | requires_runtime/decision; PG-03/08 |
| SUB-001 | Stripe backend | protected; no repetir |
| SUB-002/003/004 | lifecycle, bloqueo y adapter parcial | confirmed/refined; PG-06 |
| DATA-001..004 | scope/equivalencia/retención | refined; PG-08 |
| ADM-001..008 | plano interno futuro | confirmed/documented; PG-02/05/08/09/10 |
| UX-003/005/006 | estados/accessibility/copy de locks | refined; PG-09 |
| NOT-001..005 | notificaciones Standard/lifecycle | requires later audit; PG-05 |
| PRIV-001/002 | retención y evidencia | confirmed; PG-08 |
| DOC-001..003 | autoridad e historia contradictoria | refined; gobernanza documental |

El registro maestro permanece intacto. Se proponen tres deudas candidatas en
evidencia: plan público vs suscripción vigente; endpoint Agenda sin entitlement;
y bridge clínico condicional sin policy. No se dan de alta automáticamente.

## 35. Riesgos

- P0: UI y API pueden discrepar en Agenda/clínica.
- P0: ownership no conectado impide distinguir Free claimed/unclaimed.
- P0: downgrade/grace sin policy por dato puede permitir write indebido o
  pérdida accidental si se implementa por intuición.
- P0: roles internos no deben derivarse de `admin`, plan o rol Agenda.
- P1: cinco+ matrices de aliases/rank pueden divergir.
- P1: campos legacy del perfil pueden contradecir la suscripción.
- P1: fixtures/precios/proyecciones pueden parecer comerciales.
- P1: el bridge de encounter requiere decisión de consentimiento y gate.
- P2: locks no uniformes pueden confundir y fallar accesibilidad.

## 36. Recomendación para Actividad 2

Implementar únicamente después de aprobar DEC-001..011:

1. contrato versionado y schema de capability/lifecycle;
2. resolver backend único de plan contratado/efectivo/estado/ownership;
3. adapters para `PublicProfilePlanCapabilities`, suscripciones y campos legacy;
4. read-model JSON consumido por frontend;
5. estados no booleanos, quota references y deny codes;
6. gates equivalentes iniciales para perfil, contacto y Agenda;
7. ownership Free como dimensión separada, sin construir aún el workflow claim;
8. migración/telemetría sin borrar ni reescribir datos;
9. tests unitarios, contract tests y matrices derivadas;
10. documentación/fixtures generados y guardas contra perfiles AWS/QA.

No reimplementar Stripe, PaymentIntent, webhook o activación. No desplegar
infraestructura ni activar clínica/IA por un pago.

## 37. Criterios de aceptación

- 14/14 fuentes y delta reconciliados; 41/41 claves y delta reconciliados.
- Cinco planes/aliases con una autoridad aprobada.
- Plan, ownership, rol, scope, lifecycle, capability, quota, rol interno,
  riesgo e infraestructura separados.
- Frontend no decide permisos; backend niega todo write bloqueado.
- Free claimed/unclaimed y Agenda sin Expediente tienen contrato verificable.
- Grace/downgrade preservan datos y tienen estados/denials explícitos.
- Ningún plan concede rol interno; último director y R3 quedan protegidos.
- Ninguna proyección/fixture se vuelve quota/precio productivo.
- Tests derivan de la fuente canónica; cero matrices paralelas no validadas.
- Stripe/AWS y datos personales/clínicos reales permanecen protegidos.

## 38. No repetición

Esta auditoría tuvo cero llamadas AWS/CDK/HTTP/Stripe/pagos/email/IA, cero
ejecuciones PHP/SQL/browser/npm/tests, cero lecturas/escrituras de base, cero
cambios de aplicación/schema/tests/planes/capabilities/roles/permisos/estados y
cero acceso a secretos o datos personales/clínicos concretos. Los únicos
cambios versionados son este documento y PP276.

## 39. Historial

| Versión | Fecha | Cambio |
|---|---|---|
| 1.0.0 | 2026-07-17 | Auditoría estática completa; 14 fuentes, 41 capabilities, ownership, roles, lifecycle, equivalencia, recomendación hybrid y alcance de Actividad 2 |
| 1.1.0 | 2026-07-18 | PP278 formaliza 30 decisiones y cambia el gate a ready/not started sin alterar hallazgos |

## HISTORICAL FUNCTIONAL SOURCES RECONCILIATION AMENDMENT

**Contrato:** `MXMED_HISTORICAL_FUNCTIONAL_DOCUMENTS_RECONCILIATION_V1`
**Fecha:** 2026-07-18
**Fuentes:** ocho PDF, 29 páginas, todas `historical_noncanonical`.
**Documento:** [reconciliación histórica funcional](./MXMED_RECONCILIACION_DOCUMENTACION_HISTORICA_FUNCIONAL.md).

Este amendment no reescribe los hallazgos PG-01 ni convierte historia en
autoridad. Agrega 95 requisitos clasificados y revisa DEC-001 a DEC-011 sin
aprobarlos. La recomendación `HYBRID_VERSIONED_POLICY_V1` continúa siendo
candidata; deberá incorporar sólo decisiones directorales aprobadas.

### Impacto y DEC modificadas

- DEC-001 queda confirmada y DEC-002 refinada, pendientes de aprobación;
- DEC-003 y DEC-011 requieren dividir temas independientes;
- DEC-004 se refina separando galería de claim/ownership/publicación;
- DEC-005 se divide por capabilities, canales, unidades, cuotas y costo;
- DEC-006 se refina por múltiples máquinas; no absorbe el conflicto temporal;
- DEC-007 registra exclusivamente el conflicto de grace D+8 frente a 15 días;
- DEC-008 confirma preservación/freeze y no borrado por impago;
- DEC-009/010 refinan lock, ficha/contacto y frontera Agenda/Expediente;
- 11/11 siguen bloqueando la Actividad 2.

El paquete recomienda 20 decisiones atómicas en vez del borrador de 11, pero el
número oficial no cambia hasta aprobación explícita.

### Grace conflict

La opción histórica usa D+8, recordatorios cada 15 días y downgrade a Free. El
borrador actual usa `past_due` hasta día 3, grace hasta día 15 y `restricted`
desde día 16. No se elige opción. Deben decidirse duración, capabilities,
notificaciones, comportamiento de datos, soporte y reactivación.

### IA decomposition

La etiqueta IA se divide en `AI-CONTENT-WRITING`, `AI-IMAGE-GENERATION`,
`AI-MEDICATION-INTERACTION`, `AI-PROFESSIONAL-AGENT`,
`AI-INTERNAL-OPERATIONS` y `AI-INTERNAL-SUPERVISOR`. Chat/voz Professional
refina la intención actual; imágenes Standard+, redacción y plan requieren
decisión; interacción medicamentosa requiere PG-04/PG-11; el supervisor
all-fields se rechaza por seguridad.

### Claim refinement

Claim request (`draft/submitted/pending_review/needs_info/approved/rejected/cancelled`),
ownership (`unclaimed/claim_pending/claimed/disputed/suspended/transferred/revoked`)
y publication/moderation (`draft/pending_review/approved/published/changes_pending_review/suspended`)
son máquinas independientes. Sólo un perfil sin owner es reclamable. Free es la
condición histórica inicial; ownership verificado permite contratar después.

### RBAC refinement

El superadmin universal histórico queda `superseded`. Los roles actuales
preliminares, scopes, risk R0–R3, `platform_director`, break-glass y doble
aprobación conservan precedencia. Mercadotecnia/citas globales requieren
auditoría especializada. Ningún plan concede rol interno.

### Agenda/Expediente refinement

Una cita puede crear o vincular un contacto operativo. No crea automáticamente
Expediente. El paso clínico requiere capability, acción explícita, profesional
autorizado y consentimiento aplicable. Diagnóstico, notas clínicas, emisión/firma
de Recetas y consentimientos son no delegables; la reimpresión exacta puede ser
delegable con auditoría/notificación. Una Receta corregida genera nueva versión
y preserva la emitida. El QR permanece pendiente de auditoría especializada
PG-04. Facturación a pacientes no es suscripción MXMed.

### Estado del ciclo

Actividad 1 de 22 permanece concluida. Esta actividad auxiliar no incrementa el
contador. Actividad 2 continúa bloqueada hasta aprobar el paquete revisado. AWS
24/24 y Stripe permanecen protegidos; deployment no iniciado y tráfico `NO-GO`.

## DIRECTOR DECISION APPROVAL AMENDMENT

**Contrato:** `MXMED_PLANS_CAPABILITIES_OWNERSHIP_LIFECYCLE_DIRECTOR_DECISION_APPROVAL_V1`

Este amendment preserva todos los hallazgos estáticos de PG-01 y formaliza la
decisión posterior. No convierte la auditoría en implementación.

| DEC original | Resultado final |
|---|---|
| DEC-001 | DEC-001 `director_approved` |
| DEC-002 | DEC-002 `director_approved` |
| DEC-003 | DEC-003A–DEC-003F `director_approved` |
| DEC-004 | DEC-004A–DEC-004C `director_approved` |
| DEC-005 | DEC-005A–DEC-005E `director_approved` |
| DEC-006 | DEC-006A–DEC-006B `director_approved` |
| DEC-007 | DEC-007A–DEC-007B `director_approved` |
| DEC-008 | DEC-008A–DEC-008B `director_approved` |
| DEC-009 | DEC-009A–DEC-009B `director_approved` |
| DEC-010 | DEC-010A `director_approved` |
| DEC-011 | DEC-011A–DEC-011E `director_approved` |

Cobertura: 11/11 decisiones originales y 30/30 atómicas. Pendientes dentro del
gate original: 0. Implementadas por esta actividad: 0.

### Estado contractual final

- policy: `HYBRID_VERSIONED_POLICY_V1`;
- códigos: free/basic/standard/optimum/professional;
- perfiles iniciales: médicos individuales;
- instituciones: diferidas;
- grace: past_due 1–3, grace 4–15, restricted desde 16;
- downgrade: Free + `archived_read_only`;
- Agenda/contacto/Expediente: separados;
- backend: autoridad de capabilities y denials;
- roles: doce requisitos, cero implementados;
- Call Center e IA productiva: fuera de implementación de Actividad 2.

### Gate de Actividad 2

El assessment documental concluye PASS: el núcleo PG-01 puede prepararse sin
inventar proveedores, precios finales o parámetros especializados. Estado:
`UNBLOCKED_READY_NOT_STARTED`. La Actividad 2 no se inició y requiere
autorización separada. Contador principal: `1/22`.

Documento rector:
[Aprobación directoral](./MXMED_APROBACION_DECISIONES_PLANES_CAPACIDADES_OWNERSHIP_LIFECYCLE.md).

## 39. Cierre técnico de Actividad 2 — 2026-07-18

La Actividad 2 implementa el núcleo PG-01 aprobado mediante una policy única,
serializable y versionada: `MXMED_PLAN_CAPABILITY_POLICY_V1`. El inventario de
14 fuentes base, ocho adicionales y 41 códigos legacy se reconcilia mediante
adapters; la existencia previa de archivos no se utilizó como evidencia de
cierre.

Resultados verificables:

- cinco planes canónicos y aliases de entrada, sin sexto plan;
- 41 capabilities legacy preservadas por crosswalk y 28 códigos canónicos;
- resolver backend con orden determinista de once gates y denials sanitizados;
- approval/ownership fail-closed para administración y compra, conservando
  publicación histórica gratuita;
- lifecycle de doce estados, grace D1/D3/D4/D15/D16, prórrogas validadas y
  downgrade programado;
- `archived_read_only` conserva galería, Agenda y clínica sin ejecutar purga;
- add-ons Call Center e IA futura modelados, no operativos y no comprables;
- read-model extendido en la ruta existente y cancelación de cambio programado
  por `DELETE .../scheduled-plan`;
- frontend alimentado por `plan_catalog`; aliases, ranks, precios y matriz de
  capacidades locales eliminados;
- Stripe protegido: el cambio se limita a consumir la policy canónica para
  normalización/rank sin alterar provider, firma, webhook, idempotencia o
  activación;
- migración aditiva versionada y no ejecutada.

QA disponible: `PlanCapabilityPolicyTest.php` PASS (166),
`SubscriptionReadModelContractTest.php` PASS (85), lint PHP, parser JavaScript,
`git diff --check`, matriz backend↔frontend y snapshots semánticos. El runner no
incluye Node; el test Node queda versionado y su contrato equivalente se valida
con el motor JavaScript local y las aserciones PHP de paridad.

Estado de paridad: `BACKEND_AND_FRONTEND_COMPLETED`. Capacidades futuras,
workflows completos de ownership, consola operativa, proveedores IA/Call Center,
metering productivo y retención especializada continúan explícitamente
diferidos; no se contabilizan como implementados.
