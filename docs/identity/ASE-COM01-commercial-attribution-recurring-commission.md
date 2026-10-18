# ASE-COM01 — captación, descuento y comisión recurrente

Decisión del Director, 2026-10-17. Fuente oficial inspeccionada `f92f37f1958af5140a96c04cb52c5c1ce7af8e01`; prototipo aprobado R2 `d87174774de6526d54082c1b7a770b228c472746`, rama `prototype/ase-vis01-advisor-capability-matrix`. Extensión documental y visual aislada; **ASE-INT01 no ejecutado**. [Contrato de la misma superficie](ASE-VIS01-advisor-matrix-prototype.md), [COM](../contexto/TRAZABILIDAD_COMERCIAL_Y_ASESORES_MXMED.md#ase-com01), [J08](../contexto/INTERACCIONES_MXMED.md#j08-plan-stripe-y-activación).

## Política y procedencia

| Clasificación | Hecho y límite |
|---|---|
| `APPROVED_USER_REQUIREMENT` | Asesor capta médicos con código individual autorizado. Un asesor originador por cliente, identificable en la relación comercial. El **médico cliente** es quien paga recurrentemente. Comisión porcentual sobre suscripciones pagadas elegibles, incluidas renovaciones mensuales/anuales, mientras continúen la relación pagada y el perfil elegible activo. No sólo primera venta, sin caducidad inventada de doce meses ni reingreso del código por renovación. FREE activo sin pago no es un nuevo evento comisionable. |
| `RECOVERED_HISTORICAL_DECISION` | ASE-COM01 recupera decisiones del usuario: código temprano en registro, vínculo cliente–asesor, seguimiento por cliente/perfil y total; **15% de descuento al cliente** al contratar mediante asesor. La procedencia es la solicitud del Director, no una implementación probada. |
| `EXISTING_IMPLEMENTATION` | Catálogo/grants Identity, pricing versionado y snapshots, aceptación, checkout, PI, webhook y activación separados; trazados abajo. No configuran automáticamente descuento ni comisión. |
| `MISSING_CONNECTION` | No se localizaron productor/consumidor de código autorizado, atribución comercial validada, regla de descuento/comisión, resultado comisionable ni liquidación en el ámbito inspeccionado. `NOT_FOUND_IN_INSPECTED_SCOPE`, no afirmación universal de inexistencia. |
| `PROPOSED_DETAIL` | Cuatro actividades comerciales individuales, presentación de condiciones y vista propia; IDs de permisos, consumers, administración financiera y persistencia pendientes. Sin defaults financieros. |
| `OPERATIONAL_STATUS` | `PROTOTYPE_ONLY_NOT_OPERATIONAL`. Sin SQL, proveedor, cuentas/códigos/grants/descuentos/pagos/comisiones reales. R2 conserva la aprobación visual explícita de la solicitud actual; sólo las adiciones comerciales esperan revisión. |

**Búsqueda histórica acotada:** revisados `MXMED_DECISIONES_V2_CATALOGO_PRECIOS_MODALIDADES.md`, `MXMED_DECISIONES_V2_CAPACIDADES_AUTORIDAD_PERMISOS.md`, `PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md`, COM e IW01. El contrato de perfil conserva cupón placeholder/demo/read-only (secciones históricas en líneas 2948–3005, 3426 y 3592). Las menciones de comisiones del proveedor de pago no fijan comisión del asesor. No se encontró en estos contratos referencia verificable al descuento comercial del 15% ni enmienda posterior aprobada: se conserva como decisión recuperada de ASE-COM01, con aplicabilidad y duración pendientes D03. No se adopta una sugerencia previa del asistente sobre porcentajes, vigencia, transferencias o calendario de pago.

## Hechos separados y continuidad

| Hecho | Dueño / semántica que debe conservarse |
|---|---|
| Origen técnico | `CLAIM_EXISTING_PROFILE` / `REQUEST_NEW_PROFILE`, origen inicial y target originales, inmutables en enrollment. No se convierten en campaña ni código. |
| Captación comercial | Fuente comercial diferenciada, código/promoción presentada y estado **introducido no validado** frente a vínculo validado por servidor. No inferir orgánico si falta el dato. |
| Asesor originador | Cuenta exacta del beneficiario comercial único por cliente; vínculo identificable con cliente y perfiles elegibles. Relación pendiente de contrato/persistencia, sin backfill silencioso. |
| Descuento | Regla y versión del beneficio del cliente, elegibilidad, vigencia y efecto en precio aceptado. El 15% no determina comisión ni continuidad de descuento. |
| Comisión | Regla y versión independientes, porcentaje/base pendientes, periodo de suscripción elegible, pago y contrato exactos. |
| Contratación/pago | Referencias de aceptación/snapshot, checkout, PI/evento confirmado, suscripción/periodo. Aceptante y actor técnico no son vendedor. |
| Devengado | Resultado rastreable por pago/cliente/perfil/asesor/periodo/regla; importes por cliente y total sólo cuando exista cálculo aprobado. No es liquidación. |
| Liquidado | Registro separado referenciado al devengado, con autoridad/evidencia y ajustes; no deducido del webhook ni del balance visual. |
| Soporte actual | Relación de atención separada si se define; reasignarla no sustituye beneficiario económico. Revisor de acreditación, aceptante y creador/editor conservan sus identidades propias. |

La captura temprana debe preservar la intención durante registro, verificación y checkout, pero un texto introducido no prueba código válido ni asignación. Antes de aceptar precio/contrato el servidor deberá validar código, oferta, cuenta del asesor, cliente/ámbito y reglas/versiones; producir un vínculo comercial autorizado y consumirlo en el snapshot aceptado. No escribirlo en `source_provenance`, `created_by`, `reviewer_account_id` o `accepted_by_user_id`. No ampliar scope de cuenta/perfil ni alterar origen técnico. Correcciones retroactivas/transferencias necesitan D07, fechas efectivas y auditoría; ausencia de otro campo nunca acredita vendedor.

## Traza dirigida: productor, consumidor y primera conexión pendiente

Lectura de archivos de las dos ramas verificadas; ningún servicio instanciado ni fila consultada. Paths se refieren a fuente idéntica al baseline oficial.

| Paso / productor reusable → consumidor existente | Primera conexión comercial pendiente |
|---|---|
| [CanonicalHttpSessionResolver](../../modules/identity/http/CanonicalHttpSessionResolver.php) → [InternalOperatorAuthority](../../modules/identity/services/InternalOperatorAuthority.php), [InternalOperatorGrantRepository](../../modules/identity/repositories/InternalOperatorGrantRepository.php). [InternalGovernanceService::execute](../../modules/identity/services/InternalGovernanceService.php) → grants/delegaciones + `INTERNAL_GOVERNANCE_CHANGED`. | Catálogo de 18 entradas intacto; no permiso ejecutable de ventas encontrado. Definir capacidades/consumers/scope propio y gestión financiera separados. No otro login, clase comercial, bundle o cuota de asientos. Master requiere gestión y delegación exactas; ninguna delegación operativa habilita ventas automáticamente. |
| [PublicEnrollmentService](../../modules/identity/Accreditation/PublicEnrollmentService.php), [RegistrationService](../../modules/identity/services/RegistrationService.php), [EmailVerificationService](../../modules/identity/services/EmailVerificationService.php) → origen técnico, cuenta/consents, verificación. | Captura de código temprano y handoff validado comercial no encontrados en esas entradas. No modificar intake/origen técnico para simular atribución. |
| [UI original](../../private-app.php):3019–3024 → [app.js](../../assets/js/app.js):40335–40337, 50444–50445 y 50487–50488. Fila `d-none`, input/botón deshabilitados; handler informa modo lectura, sin API de cupón. | UI→validador de código/autoridad/beneficiario/oferta. `NOT_FOUND_IN_INSPECTED_SCOPE`; no redemption ni éxito de descuento actual. No se modifica la UI original. |
| [SubscriptionPlanPriceRepository](../../modules/subscriptions/repositories/SubscriptionPlanPriceRepository.php) → [SubscriptionPlanPriceResolverService::resolveForCheckout](../../modules/subscriptions/services/SubscriptionPlanPriceResolverService.php) → [BuildSubscriptionPaymentRoutePreviewService](../../modules/subscriptions/services/BuildSubscriptionPaymentRoutePreviewService.php), [SubscriptionPaymentRouteRepository](../../modules/subscriptions/repositories/SubscriptionPaymentRouteRepository.php). | Precio de servidor, importe/moneda/versiones/vigencia y preview snapshots existentes. Falta oferta comercial validada y su efecto/versión; `price_version` no es versión de descuento. Ningún precio se cambia aquí. |
| [CreateSubscriptionCheckoutFromPaymentRouteService](../../modules/subscriptions/services/CreateSubscriptionCheckoutFromPaymentRouteService.php) / [CreateSubscriptionCheckoutIntentService](../../modules/subscriptions/services/CreateSubscriptionCheckoutIntentService.php) → [checkout](../../modules/subscriptions/repositories/SubscriptionCheckoutIntentRepository.php) y [aceptación](../../modules/subscriptions/repositories/SubscriptionContractAcceptanceRepository.php). | Snapshot precio/contrato y aceptante existen; no vínculo comercial ni snapshots de descuento/comisión localizados. Rutas distintas, no delegación interna común acreditada; locks/idempotencia por comando, sin transacción global. |
| [CreateSubscriptionPaymentIntentService](../../modules/subscriptions/services/CreateSubscriptionPaymentIntentService.php) → [StripePaymentIntentProviderService](../../modules/subscriptions/services/StripePaymentIntentProviderService.php) → [SubscriptionPaymentIntentRepository](../../modules/subscriptions/repositories/SubscriptionPaymentIntentRepository.php). | Importe autoritativo desde checkout y correlación de pago reutilizables. Falta consumir vínculo/reglas comerciales sin confiar en browser. Proveedor HTTP y commit SQL son fronteras separadas; transporte ambiguo exige reconciliación. |
| [ProcessStripeSubscriptionWebhookService](../../modules/subscriptions/services/ProcessStripeSubscriptionWebhookService.php) → [SubscriptionPaymentEventRepository](../../modules/subscriptions/repositories/SubscriptionPaymentEventRepository.php) + PI. Firma, correlación exacta, importe/moneda, hash/event ID; evento+transición PI en transacción propia. | Pago confirmado no produce comisión. Falta consumer comercial de evidencia autoritativa y resultado único. Replay igual devuelve duplicado, hash distinto 409; unique protege persistencia, pero carrera perdedora puede retornar 500 antes de replay. No prometer retry automático. |
| [BuildSubscriptionPaymentActivationStateService](../../modules/subscriptions/services/BuildSubscriptionPaymentActivationStateService.php) → lectura; [ActivateSubscriptionAfterPaymentService](../../modules/subscriptions/services/ActivateSubscriptionAfterPaymentService.php) → [ProfileSubscriptionRepository::createActiveFromPaidCheckout](../../modules/subscriptions/repositories/ProfileSubscriptionRepository.php), checkout/aceptación/idempotencia. | Activación explícita en otra transacción, referencias exactas y pago/evento confirmados. `auto_renew=0`; renovación completa y cobro recurrente siguen con brechas SUBS-09. Falta consumer de elegibilidad comercial por periodo, no activación desde éxito browser. |
| Aceptación/checkout/PI/eventos/subscription lineage y auditoría Identity → readers actuales de suscripción/gobierno. | No estado de cuenta por vendedor, audit writer financiero, resultado de comisión o liquidación encontrado. Evento de pago no prueba auditoría financiera canónica de comisión. Definir readers minimizados y conciliación sin ledger alternativo en esta tarea. |

Ámbito adicional de búsqueda de productores: `modules/subscriptions/services`, `modules/subscriptions/repositories`, los tres servicios Identity/enrollment citados y catálogo/gobierno, con términos coupon/cupon/commission/comision/referral/promotion/originating_advisor/discount_rule. Sólo se encontró la visibilidad de paquetes promocionales en el read model de activación, no un validador de códigos. Los límites fuente de [SUBS-01..10](../contexto/STRIPE_Y_SERVICIOS_EXTERNOS_MXMED.md) prevalecen: gates Identity cerrados, checkout mensual nuevo no listo, confirmador/retorno incompletos, renovación parcial, sin Billing Subscription/SetupIntent/scheduler acreditados ni refunds/disputes del dominio. No se ejecutó QA financiera histórica.

## Cadena financiera requerida, todavía sin implementación

Código autorizado → oferta y relación asesor/cliente validadas → snapshot precio/contrato aceptado → pago **del cliente** confirmado y periodo elegible acreditado → **un resultado de comisión trazable** → registro de liquidación separado.

- Identificar exactamente cliente, perfil elegible, asesor originador, promoción/regla de descuento, regla de comisión, aceptación/checkout, pago confirmado, suscripción y periodo. Nunca derivar vendedor de actor/revisor/aceptante. Conservar versiones y fechas aplicables históricas; no recalcular periodos previos con la tasa actual.
- Una notificación duplicada, comando repetido, respuesta perdida o nuevo event ID sobre el mismo pago no crea otra comisión para el mismo hecho económico. Deben definirse identidad económica única, conflictos, locking, idempotencia, auditoría y recuperación; event-ID del proveedor por sí solo es insuficiente. Fallos ambiguos requieren lectura/reconciliación antes de reintentar. No se implementa esquema, ledger, outbox ni transacción distribuida en ASE-COM01.
- Pago confirmado no implica activación. Acreditar relación pagada/perfil elegible activo y periodo según política aprobada; activación pendiente no debe convertirse por UI en elegibilidad. Un cobro anual no se transforma en doce nuevos pagos; comisión mensual/anual sigue el periodo/evidencia del pago real. La renovación pagada puede ser elegible sin reingresar código, pero esto no demuestra cobro automático ni resuelve D03/D04.
- Reembolso, contracargo, cancelación, fallo y corrección deben conservar pago/resultado original y ajustes referenciados, sin borrar historia ni duplicar devengado; reglas de elegibilidad/importe pendientes D05/D08. Separar devengado, ajuste, pendiente de liquidación y liquidado, con autorización/evidencia propias y conciliación. No inventar cuándo se paga al asesor.
- Revocar acceso no elimina historia financiera ni determina automáticamente derechos futuros o ya devengados. Un originador por cliente no autoriza splits ni estructura multinivel. Reasignar soporte tampoco cambia beneficiario. D06/D07 gobiernan cualquier cambio económico futuro.

**Dominios distintos:** suscripción México Médico→médico, cobro médico→paciente y PAC/CFDI, comisiones de citas/Call Center y comisión de captación son contratos diferentes. Stripe PaymentIntent procesa pago; Stripe Billing recurrente no queda probado por él. Liquidar a un asesor no equivale a ninguno de los anteriores: no se presupone Stripe Connect, payout automático, transferencia ni débito recurrente de tarjeta.

## Decisiones del Director pendientes, sin defaults

| ID | Decisión requerida |
|---|---|
| D01 | Porcentaje de comisión y variaciones. |
| D02 | Base de cálculo; descuentos, impuestos, comisiones/cargos del proveedor. |
| D03 | Aplicabilidad y duración del descuento histórico del cliente, incluidas renovaciones. |
| D04 | Momento de liquidación de comisiones mensuales/anuales. |
| D05 | Refund/contracargo/fallo, lapse y reactivación. |
| D06 | Salida/suspensión del asesor y derechos económicos futuros. |
| D07 | Correcciones/transferencias de atribución y fechas efectivas. |
| D08 | Upgrades/cambios de plan, parcialidades iniciales y demás estructuras de pago. |

No se asigna aquí tasa, saldo, vigencia de código, continuidad perpetua del descuento ni calendario de liquidación.

## Extensión de la UI aprobada y verificación

Mismo prototipo/R2: familia contraíble **CAPTACIÓN Y VENTAS**, cuatro actividades propuestas sólo lectura, sin claves `data-capability`, sin writer de grants. Director podrá combinarlas individualmente con las operativas cuando catálogo/consumers/autoridad estén aprobados. Consultar código/clientes/renovaciones/estado propio no concede fijar descuentos/tasas, transferir atribución, ajustar ni liquidar. Advisor no modifica sus condiciones; Master no recibe esa autoridad de su clase ni delegación operativa.

**CONDICIONES COMERCIALES** es un bloque acotado del detalle seleccionado: código simbólico no válido, descuento histórico no aplicado, porcentaje/base pendientes, recurrencia y estado/ámbito. La vista **MI ACTIVIDAD COMERCIAL**, dentro del mismo prototipo, presenta únicamente los dos médicos ficticios del asesor seleccionado, tres periodos mensuales y contratación/renovación anual del segundo año, sin datos clínicos/fiscales ni importes. No tiene selector de otros vendedores; es una previsualización de diseño, no un control de acceso real. Master/propuestas de alta no reciben códigos o clientes automáticamente.

La prueba de replay reproyecta fixtures constantes: cinco pagos/periodos ficticios distintos; al añadir un duplicado idéntico y otro aviso del mismo pago sigue habiendo cinco resultados conceptuales. No calcula ni devenga importes y no acredita idempotencia financiera/concurrencia. Pendiente de activación y FREE sin pago aparecen separados, sin nuevo resultado conceptual.

QA y digests del candidato: [comercial](../../prototypes/ase-vis01/evidence/com01/browser/summary.json), [regresión R2](../../prototypes/ase-vis01/evidence/com01/r2-regression/summary.json), [comparación R2 anterior](../../prototypes/ase-vis01/evidence/com01/r2-before.json), [aislamiento](../../prototypes/ase-vis01/evidence/com01/isolation.json) y [enlaces](../../prototypes/ase-vis01/evidence/com01/context-links.json). Resultado final: **380 checks comerciales + 1.112 de regresión R2 = 1.492 PASS**, 52 capturas (24 comerciales y 28 R2); 722 paths/anchors y 2.992 archivos oficiales idénticos. Ocho casos por suite: Chromium/WebKit, 1440×900, 1366×768, 820×1180, 390×844. DOM/teclado/foco/targets/scope sólo sintéticos; Safari físico `NOT_RUN`, móvil `INTERIM_MOBILE_SMOKE_ONLY`. Se conserva la calificación exacta del estilo vacío `body {}` que el driver de captura WebKit intenta inyectar y CSP rechaza; cualquier otro error falla. Se corrigió en el harness una expresión de espera sin función que WebKit rechazaba por `unsafe-eval`; no se relajó CSP. Se fijó además el foco explícito en el botón de entrada antes de abrir el modal, para que WebKit/macOS lo restaure al cerrar también con ratón.

Preview sólo loopback `http://127.0.0.1:8140/`, caduca `2026-10-25T00:00:00Z`, mismo servidor allowlisted/CSP y [retiro seguro](ASE-VIS01-advisor-matrix-prototype.md#preview-expiración-y-descarte-seguro). R2 aprobado; extensión comercial **PENDING_DIRECTOR_VISUAL_APPROVAL**. Integración/ASE-INT01 y activación financiera requieren tarea futura distinta.

## CTX-SYNC01

| Campo | Resultado del alcance |
|---|---|
| `CONTEXT_BASELINE` | Fuente f92f37f; R2 d871747; CTX-MAP02 1b8dfccb y CTX-MAP03 0bea8195 conservados. |
| `AFFECTED_CAPABILITY_IDS` | ID-07 y SUBS-01..09 sólo contrato/trazabilidad; COM-02/03/04/06/07/08/09/10. Ninguna clave ejecutable añadida. |
| `AFFECTED_JOURNEY_IDS` | J08 documental; J01/J02 referencia de origen inmutable. Ningún journey ejecutado/modificado en producto. |
| `AFFECTED_DATA_OBJECTS` | Ninguno modificado/consultado. Contratos de auth_accounts/internal_staff/grants/delegaciones, enrollment origins, rutas/checkout/acceptances/PI/events/profile_subscriptions y auditoría referenciados; hechos comerciales futuros no son tablas nuevas. |
| `AFFECTED_CONTEXT_FILES` | Política ASE-COM01, COM, interacciones J08 y mapa financiero SUBS, contrato prototipo, registro visual, IW01, router, estado. |
| `CONTEXT_CHANGES_IMPLEMENTED` | Política recurrente/procedencia/decisiones pendientes y primeros enlaces ausentes; extensión sintética en misma UI, aprobación R2 preservada; sin activación ni nuevos counts del inventario. |
| `CONTEXT_VERIFICATION` | Fuente contrastada, comparación DOM/geometría R2, ambas suites y enlaces/digests/aislamiento verificados; resultados en evidencia y entrega. |
| `CONTEXT_SYNC_STATUS` | PASS_UPDATED: ambas suites y digests, 722 enlaces/anchors, aislamiento y diff completo contrastados; fuente/listeners originales intactos. |
| `DOCUMENTATION_COMMIT` | Commit exacto de esta entrega en reporte final; sin hash autorreferente futuro. |
| `REMAINING_CONTEXT_GAPS` | D01–D08, aprobación visual comercial, catálogo/consumers/autoridad, atribución y handoffs, resultados/conciliación/liquidación y brechas SUBS; integración no autorizada aquí. |
