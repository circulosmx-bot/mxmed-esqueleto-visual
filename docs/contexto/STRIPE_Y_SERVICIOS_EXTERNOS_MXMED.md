# Stripe, Billing y servicios externos — mapa vigente de fuentes

## Ancla, alcance y lectura

**CTX-MAP02 · 2026-10-17 · baseline `1b8dfccb68a7613b2182507a840454631dbf012a` · rama `ux/consultation-step2-vitals-r1`.**
Inspección estática de fuente, contratos, SQL versionado, tests y referencias Git locales.
No se llamó Stripe, AWS, PAC, Maps, correo ni SMS; no se ejecutaron pruebas de producto,
SQL, simulaciones de pago ni pilots. `NOT_VERIFIED_REMOTE` describe el estado externo actual.
Los importes, IDs y payloads de operaciones reales no forman parte de este mapa.

Este es el sucesor de consulta para estos dominios; el
[contrato acumulativo de perfiles](../PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md) conserva las decisiones históricas.
Volver al [router](../README.md), al [inventario funcional](INVENTARIO_FUNCIONAL_MXMED.md), al
[mapa de interacciones](INTERACCIONES_MXMED.md) o al [mapa AWS](AWS_Y_ENTORNOS_MXMED.md).
La presencia de fuente no demuestra reachability o autorización actual. Los cinco ejes
de estado se explicitan abajo; no equivalen a una etiqueta global de completitud.

## Fronteras financieras que no deben mezclarse

| Frontera | Autoridad existente | Qué no acredita |
|---|---|---|
| México Médico cobra a médicos por planes | [Subscriptions API](../../api/subscriptions/index.php), PaymentIntent Stripe, eventos y activación interna | CFDI de pacientes, Stripe Connect, Stripe Billing recurrente o liquidaciones a médicos |
| Médico conserva datos fiscales y facturas de pacientes | [Billing API](../../api/billing/patient-profiles.php), [archivo](../../api/billing/invoices.php), [borradores](../../api/billing/issuance.php) | Procesamiento de tarjeta, cobro al paciente o timbrado operativo |
| Confirmación de pago | [ProcessStripeSubscriptionWebhookService](../../modules/subscriptions/services/ProcessStripeSubscriptionWebhookService.php) | Alta de suscripción: el webhook sólo registra evento/estado del pago |
| Activación de suscripción | [ActivateSubscriptionAfterPaymentService](../../modules/subscriptions/services/ActivateSubscriptionAfterPaymentService.php) → `ProfileSubscriptionRepository` | Pago observado en navegador o evento no procesado no bastan |
| Suscripción de aplicación | `profile_subscriptions`, vigencia, aceptación y enlaces de renovación/upgrade | No es un recurso Stripe Billing `subscription`; `auto_renew_requested` no es un débito recurrente configurado |
| CFDI/PAC | [CfdiCertificationProviderPort](../../modules/billing/services/CfdiCertificationProviderPort.php), actualmente [UnconfiguredPacAdapter](../../modules/billing/services/UnconfiguredPacAdapter.php) | La selección SW no implementa `SwProvider`, no contrata timbres ni abre timbrado |
| Entitlement de organización sanitaria | [ProviderOrganizationSubscriptionService](../../modules/subscriptions/services/ProviderOrganizationSubscriptionService.php), gobierno interno | No usa checkout de médicos, Stripe Connect ni una ruta pública de cobro |

### Política y estados de este mapa

Política comercial aprobada: [catálogo/precios/modalidades A1–A5](../MXMED_DECISIONES_V2_CATALOGO_PRECIOS_MODALIDADES.md),
[autoridad/capacidades](../MXMED_DECISIONES_V2_CAPACIDADES_AUTORIDAD_PERMISOS.md) y
[registro visual](../MXMED_REGISTRO_CONTRATOS_VISUALES.md).
Los precios provisionales requieren revisión previa al lanzamiento; el catálogo persistido
y servidor son autoridad. Cuatro tarjetas pagadas, FREE como estado, fórmula mensual y
anticipo de tres mensualidades no autorizan habilitar recurrencia. Los datos FREE pueden
persistir privados sin ser visibles en el DTO público.

| Área | PRODUCT_POLICY | SOURCE | CONNECTIVITY | VERIFICATION | OPERATION |
|---|---|---|---|---|---|
| Catálogo/aceptación/checkout/pago/activación | approved | implemented | wired | source-inspected | authorization-pending |
| Stripe.js y Payment Element | approved | implemented | wired | source-inspected | external-state-not-verified |
| Confirmar desde navegador y retorno | approved | partial | disconnected | source-inspected | inactive |
| Renewal y mensual recurrente | approved | partial | unresolved | source-inspected | inactive |
| Perfiles fiscales/archivo/borradores/CSD | approved | implemented | wired | source-inspected | external-state-not-verified |
| PAC | approved | partial | optional-composition | source-inspected | authorization-pending |

Los valores de la tabla describen el alcance inspeccionado y usan un único enum por eje.
La aprobación de confirmación/recurrencia corresponde a su contrato/readiness, con ejecución
expresamente diferida. `wired` en pagos refiere al dispatcher legacy; los writers Identity
están cerrados. PP220/231/232 y PP234/236/241 aportan evidencia histórica de QA aislada a
los componentes indicados abajo, sin sustituir la verificación de fuente de este baseline.
El estado remoto Stripe sigue desconocido aunque la ruta local de activación requiera
autorización. Para Billing se inspecciona la conexión de fuente, no su operación real actual.
PAC conserva puerto implementado que rechaza, adaptador SW ausente y autorización pendiente.

## Gates actuales de Subscriptions

1. [API](../../api/subscriptions/index.php), bloque `canonicalRuntimeRequested()` antes del dispatch:
   en runtime Identity canónico exige acceso válido y devuelve **403** a métodos distintos
   de `GET/HEAD` de estos writers. Explicación en fuente: esperan IDs numéricos PHP legacy;
   no se traduce una cuenta opaca Identity a esa autoridad. `webhooks` y `config` conservan
   su propio contrato. No se debe elevar owner ni fabricar sesión para sortearlo.
2. [StripePaymentIntentProviderService::stripeSecretKey](../../modules/subscriptions/services/StripePaymentIntentProviderService.php)
   sólo admite prefijos test/restricted-test; rechaza claves live.
3. [RetrieveSubscriptionPaymentIntentClientSecretService](../../modules/subscriptions/services/RetrieveSubscriptionPaymentIntentClientSecretService.php)
   exige `livemode=false`, PI remoto `requires_payment_method`, metadata y precio exactos.
   El soporte de clave pública live en el resolver no elimina este gate.
4. [app.js](../../assets/js/app.js), factories `createSubscriptionStripeRuntime` y
   `createSubscriptionStripePaymentElementMountLayer`: carga singleton y monta Elements;
   `[data-subp-payment-final-submit]` sigue disabled. No se encontró una invocación
   productiva `confirmPayment` ni `return_url`. La ruta `/subscriptions/stripe-return`
   es contractual y **no existe como entrypoint versionado**.
5. PP242–244 señalan la exposición de query sensible en la primera petición de retorno.
   Un `history.replaceState` posterior no protege el access log inicial. El
   [aspecto IaC](../../infra/aws/lib/aspects/stripe-return-logging-safety-aspect.ts)
   no crea por sí solo ese bridge ni permite cobrar.
6. Aunque `config` está excluida del gate general, `config/public/stripe` exige después
   `auth_mode=session_scope`; la resolución actual Identity informa `canonical_identity`.
   No se acredita que la clave pública sea recuperable desde la sesión normal-local actual.
7. El bridge conserva `CONTRACT_HASH` como placeholder QA y
   `CONTRACT_SNAPSHOT_URL=/legal/subscriptions/mxmed-subscriptions-v1.html`; no existe ese
   documento legal versionado en el baseline. La aceptación persistida del ensayo no
   acredita un snapshot contractual productivo aprobado/disponible.

## Capacidades de planes y pago

Las rutas relativas siguientes llevan prefijo `/api/subscriptions/index.php`.
`E` significa `entities/{type}/{id}`; los handlers de escritura de médicos exigen
`type=doctor`, contexto de actor/entidad validado, payload permitido y
`Idempotency-Key` donde se indica. No se documentan valores de identidades.

### SUBS-01 — consultar plan, capacidades y publicación

- **Propósito/actor/UI:** médico con sesión y scope válido; lector operador sólo según
  permiso de lectura comprobado; panel original [private-app.php](../../private-app.php)
  `#p-suscripcion` / «Mi plan y pagos» y representación pública por sus propios DTO.
- **Handler/rutas:** `buildSubscriptionContextEndpoint`, `buildSubscriptionEndpointFromContext`
  en [app.js](../../assets/js/app.js); `GET context/current`, `GET E/current`.
- **Servicio/read model:** [CurrentSubscriptionReadModelService](../../modules/subscriptions/services/CurrentSubscriptionReadModelService.php)
  → [CurrentSubscriptionRepository](../../modules/subscriptions/repositories/CurrentSubscriptionRepository.php);
  [ExistingCapabilityAuthorityService](../../modules/subscriptions/services/ExistingCapabilityAuthorityService.php)
  decide `feature_access`. No writer en estas lecturas.
- **Datos/gates:** `subscription_plans`, `profile_subscriptions`; fechas, status,
  grace period y fallback FREE. El overlay local
  [NormalLocalReviewScenario](../../modules/subscriptions/services/NormalLocalReviewScenario.php)
  no cambia la suscripción real.
- **Proyección pública:** [PublicCommercialPlanSource](../../modules/subscriptions/services/PublicCommercialPlanSource.php)
  exige fuente comercial explícita y estable o metadata preloaded FREE; no confunde el
  fallback genérico FREE con procedencia de elegibilidad. Lectura pública
  [PublicProfileRepository](../../modules/profiles/repositories/PublicProfileRepository.php) /
  [PublicProfileController](../../modules/profiles/controllers/PublicProfileController.php) usa
  [PublicProfilePlanCapabilities](../../modules/profiles/services/PublicProfilePlanCapabilities.php),
  flags de contacto y estado de publicación. No publica teléfono FREE por estar almacenado.
- **Resultado/error/evidencia:** plan/estado/vigencia y capacidades, 401/403 por sesión/scope,
  fuente comercial desconocida cierra elegibilidad. Tests existentes
  [feature access](../../modules/subscriptions/tests/CurrentSubscriptionFeatureAccessReadModelTest.php),
  [autoridad](../../modules/subscriptions/tests/ExistingCapabilityAuthorityServiceTest.php),
  [ACT03 contactos](../../modules/identity/tests/Act03FreeContactTest.php). Fuente inspeccionada
  en el baseline; no ejecución actual. Estado: implemented/wired/source-inspected,
  operación de cada perfil no verificada.

### SUBS-02 — seleccionar catálogo y preparar ruta de pago

- **Actor/UI/decisión:** médico en `#p-suscripcion`, cuatro tarjetas, anual/mensual,
  upgrade o renovación; A1–A5 y PP225/226.
- **Handlers/rutas:** `buildPaymentRoutePreviewEndpoint` y `buildPaymentRouteCreateEndpoint`;
  `POST E/payment-routes/preview`, `POST E/payment-routes`.
- **Servicios/writer:** [BuildSubscriptionPaymentRoutePreviewService](../../modules/subscriptions/services/BuildSubscriptionPaymentRoutePreviewService.php)
  recalcula plan/precio/periodo; [CreateSubscriptionPaymentRouteService](../../modules/subscriptions/services/CreateSubscriptionPaymentRouteService.php)
  → [SubscriptionPaymentRouteRepository](../../modules/subscriptions/repositories/SubscriptionPaymentRouteRepository.php).
  [SubscriptionPlanPriceResolverService](../../modules/subscriptions/services/SubscriptionPlanPriceResolverService.php)
  → [SubscriptionPlanPriceRepository](../../modules/subscriptions/repositories/SubscriptionPlanPriceRepository.php).
- **Datos/proveedor:** `subscription_plan_prices`, `subscription_plans`,
  `profile_subscriptions`, `subscription_payment_routes`, idempotency table. Sin Stripe
  en preview/ruta. Snapshot entero en `amount_cents`, moneda normalizada `MXN`, versión,
  vigencia y fuente; ningún precio del navegador es autoridad. Si no hay precio mensual
  aplica la derivación comercial desde anual y redondeo del resolver, no un precio inventado.
- **Resultado/gates:** `preview_ready_for_checkout_sandbox`, luego `created_no_provider`;
  conflictos de contexto, precio, entidad o idempotencia bloquean. Mensual/`auto_renew_requested`
  describe intención y precio; no instala método guardado ni cobro posterior. Gate canónico anterior.
- **Estado/evidencia:** implemented/wired/source-inspected; PP225/226/228 y cierre histórico PP232;
  no nueva confirmación funcional ni operación live.

### SUBS-03 — aceptación contractual y checkout interno

- **Actor/UI/rutas:** mismo actor autorizado; resumen y aceptación del panel original;
  `POST E/payment-routes/{route}/checkout` o `POST E/checkout-intents`.
- **Servicios/writers:** [CreateSubscriptionCheckoutFromPaymentRouteService](../../modules/subscriptions/services/CreateSubscriptionCheckoutFromPaymentRouteService.php)
  y [CreateSubscriptionCheckoutIntentService](../../modules/subscriptions/services/CreateSubscriptionCheckoutIntentService.php)
  son dos caminos existentes; ambos reutilizan
  [CreateSubscriptionPendingPaymentAcceptanceService](../../modules/subscriptions/services/CreateSubscriptionPendingPaymentAcceptanceService.php)
  y los mismos repositorios. PP230 propuso delegación al servicio directo, pero el bridge
  inspeccionado no hace esa llamada; no documentar una conexión inexistente. Repositorios:
  repositorios [checkout](../../modules/subscriptions/repositories/SubscriptionCheckoutIntentRepository.php),
  [aceptación](../../modules/subscriptions/repositories/SubscriptionContractAcceptanceRepository.php),
  [idempotencia](../../modules/subscriptions/repositories/SubscriptionWriteIdempotencyRepository.php).
- **Autoridad/transacción:** entidad/perfil validado, actor original, contrato versión/hash/URL;
  puente verifica ruta y preview recalculado, lock por entidad, transacción aceptación +
  checkout + consumo de ruta + respuesta idempotente. UUID único de checkout por ruta.
  Snapshot de importe y prorrateo queda fijado; no se cobra ni activa aquí.
- **Datos/resultado:** `subscription_contract_acceptances` queda
  `accepted_pending_payment`; `subscription_checkout_intents` queda `pending_payment`;
  ruta `checkout_created_no_provider`. No es Stripe Checkout Session. Direct new checkout
  restringe anual; bridge rechaza new monthly con `monthly_recurring_not_ready`.
- **Errores/estado/evidencia:** ruta consumida, precio/contexto cambiado, pago/checkout existente,
  contrato inválido, expiración, lock timeout; implemented/wired con gate canónico de escritura.
  PP228/230 y QA histórica PP232; no reejecutada.

### SUBS-04 — crear PaymentIntent Stripe desde snapshot servidor

- **Actor/UI/route:** médico en ruta elegible del panel; handler `buildPaymentIntentCreateEndpoint`;
  `POST E/checkout-intents/{checkout}/payment-intents`.
- **Servicio/writer:** [CreateSubscriptionPaymentIntentService](../../modules/subscriptions/services/CreateSubscriptionPaymentIntentService.php)
  → [StripePaymentIntentProviderService](../../modules/subscriptions/services/StripePaymentIntentProviderService.php)
  → Stripe `/v1/payment_intents` → [SubscriptionPaymentIntentRepository](../../modules/subscriptions/repositories/SubscriptionPaymentIntentRepository.php).
- **Control:** checkout pendiente/vigente/importe positivo, replay previo, lock por checkout,
  un PI activo, key Stripe derivada de key de aplicación + request hash. El proveedor recibe
  cents/MXN desde checkout, `automatic_payment_methods.enabled=true`, `allow_redirects=never`,
  metadata de correlación MXMED; valida respuesta/precio. Guarda resumen seguro, no secreto efímero.
- **Frontera de fallo:** llamada remota y persistencia SQL **no son una transacción distribuida**.
  Etapas sanitizadas distinguen transporte, HTTP, decode, validación, persistencia y fin de
  idempotencia. La key de proveedor limita repetición pero no demuestra recuperación total
  de un resultado ambiguo; revisar correlación antes de cualquier reintento aprobado.
- **Datos/gates/estado:** `subscription_payment_intents`, idempotencia; `STRIPE_SECRET_KEY`,
  cURL o stream HTTPS timeout20s. Sólo test keys y writes canónicos cerrados. Errores
  `payment_intent_already_exists`, checkout expirado, proveedor ausente/fallido.
  Implemented/wired/source-inspected; QA sandbox histórica PP220/232; live no verificado.

### SUBS-05 — clave pública, secreto efímero y montaje Payment Element

- **Actor/UI/rutas:** sesión y entidad médica válida; `GET config/public/stripe`,
  `POST E/payment-intents/{pi}/client-secret` (body vacío), host único
  `[data-subp-stripe-payment-element-host]` generado en `#p-suscripcion`.
- **Servicios/lectura:** [ResolveSubscriptionStripePublicConfigService](../../modules/subscriptions/services/ResolveSubscriptionStripePublicConfigService.php)
  y [RetrieveSubscriptionPaymentIntentClientSecretService](../../modules/subscriptions/services/RetrieveSubscriptionPaymentIntentClientSecretService.php)
  cotejan scope, ruta, checkout, PI local/remoto, amount/currency, metadata y test mode.
  La recuperación hace GET remoto autorizado; no constituye confirmación de pago.
- **Cliente:** [app.js](../../assets/js/app.js) carga `https://js.stripe.com/dahlia/stripe.js`,
  singleton validado, Elements ligado al secreto sólo en memoria, mount/change/ready/loaderror,
  epochs de contexto, timeout/retry acotado y teardown en salida/cambio/expiración.
  No copia datos de tarjeta al DOM propio, storage ni snapshots.
- **Resultado/gates:** input readiness y errores sanitizados, respuestas `private/no-store`;
  secreto ausente o mismatch cierra. `STRIPE_PUBLISHABLE_KEY` y expectation de mode deben
  coincidir. POST client-secret también queda cerrado por el gate canónico actual.
- **Estado/evidencia:** implemented/wired/source-inspected, PP234/236/241 (13–14 julio2026),
  [commits históricos](#referencias-git-e-historia). No implica CTA confirmador.

### SUBS-06 — confirmar en navegador y retornar de Stripe

- **Actor/UI/route:** CTA `[data-subp-payment-final-submit]` en la misma UI permanece disabled;
  `/subscriptions/stripe-return` es ruta propuesta, no handler entregado.
- **Contrato:** PP242 define llamada futura `stripe.confirmPayment` con Elements existente,
  submit único, doble-submit compartido, `redirect:'if_required'`, retorno same-origin,
  scrub temprano, correlación de sesión y polling backend acotado. No se debe convertir
  esa especificación en afirmación de implementación.
- **Writer/datos:** no writer propio ni activación cliente. Nunca confiar en redirect status
  o un PI observado para otorgar entitlements.
- **Fallo/gate/estado:** `stripe_return_url_sensitive_query_logging_unresolved`;
  partial/absent, disconnected, source-inspected, inactive. PP243/244 no encontraron ingress
  productivo versionado; posterior IaC impone gates, no un bridge funcional. Próximo paso
  técnico mínimo, sólo si se autoriza: cerrar ingreso/logging/correlación antes del confirmador.

### SUBS-07 — webhook firmado, deduplicación y confirmación autoritativa de pago

- **Actor/route:** proveedor Stripe, sin depender de cookies de médico;
  único `POST /api/subscriptions/index.php/webhooks/stripe`.
  `/webhooks/stripe` no es alias implementado. [API boundary](../../api/subscriptions/index.php)
  lee raw body y `Stripe-Signature` antes de decodificar.
- **Firma/normalización:** [StripeWebhookSignatureVerifier](../../modules/subscriptions/services/StripeWebhookSignatureVerifier.php)
  verifica HMAC-SHA256 v1 sobre timestamp + raw body, `hash_equals`, tolerancia300s;
  [StripeWebhookPayloadNormalizer](../../modules/subscriptions/services/StripeWebhookPayloadNormalizer.php)
  exige Event ID/type, objeto `payment_intent`, provider ID/status/amount/currency;
  valida expected livemode, hash raw, resumen allowlistado. La opción expected currency
  se registra como contexto; cotejo monetario efectivo ocurre contra el snapshot local.
- **Writer/estado:** [ProcessStripeSubscriptionWebhookService](../../modules/subscriptions/services/ProcessStripeSubscriptionWebhookService.php)
  busca PI por `(provider,provider_payment_id)` y evento por `(provider,provider_event_id)`.
  Mismo Event ID/hash → replay sin transición; mismo ID/hash distinto →409.
  Pago ausente404, amount/currency mismatch422, estado terminal incompatible409.
- **Transacción/datos:** inserta `subscription_payment_events` y marca PI `paid`, `failed`
  o `cancelled` en una transacción. Repositorios de
  [eventos](../../modules/subscriptions/repositories/SubscriptionPaymentEventRepository.php) y
  [PI](../../modules/subscriptions/repositories/SubscriptionPaymentIntentRepository.php),
  únicos SQL de provider event/payment. `payment_intent.succeeded` produce
  `payment_intent_confirm / paid / processed`; eventos no accionables se registran fallidos.
  No escribe `profile_subscriptions` ni consume aceptación para activar.
- **Concurrencia/auditoría:** los únicos SQL previenen doble persistencia; dos solicitudes
  concurrentes pueden competir tras el lookup previo y el perdedor recibir500, recuperando
  el evento ya confirmado en una entrega posterior. No se acredita un retry automático
  interno. PaymentEvent es el registro comercial de esta cadena; no atribuirle integración
  al sink de auditoría canónica de Platform sin un productor explícito inspeccionado.
- **Tiempo/error/evidencia:** acepta llegada tardía si la transición pagada es anterior/al
  vencimiento del checkout; rechaza pago posterior. Fallo de transacción rollback y500.
  `STRIPE_WEBHOOK_SECRET`, `STRIPE_WEBHOOK_EXPECTED_LIVEMODE`; producción exige expectation
  explícita, local no productivo default test. Implemented/wired/source-inspected,
  PP220/231/232 sandbox histórico; secreto/config/endpoint remoto actuales no verificados.

### SUBS-08 — reconciliar lectura y activar después del pago

- **Actor/UI/rutas:** soporte/médico con scope permitido; UI de estado del panel original;
  `GET E/payment-activation-state` con IDs locales opcionales;
  `POST E/checkout-intents/{checkout}/payment-intents/{pi}/activate-after-payment` exige
  `payment_event_uuid` + `Idempotency-Key`. No recibe precio, plan, fechas ni status del cliente.
- **Lectura:** [BuildSubscriptionPaymentActivationStateService](../../modules/subscriptions/services/BuildSubscriptionPaymentActivationStateService.php)
  reúne checkout, PI, último evento, aceptación y current; `can_activate`/reasons son lectura,
  no una transición. No hace polling Stripe ni descubre una cadena desde un ID remoto.
- **Autoridad/writer:** [ActivateSubscriptionAfterPaymentService](../../modules/subscriptions/services/ActivateSubscriptionAfterPaymentService.php)
  verifica PI pagado/Stripe succeeded, evento confirm procesado, pertenencia de todos los IDs,
  entidad, aceptación `accepted_pending_payment`, checkout pendiente y regla temporal.
  [ProfileSubscriptionRepository](../../modules/subscriptions/repositories/ProfileSubscriptionRepository.php)
  crea suscripción activa; contrato enlazado, checkout activado y respuesta idempotente
  quedan en **una transacción**, con lock por PI y rollback. En upgrade enlaza la anterior
  `renewed` a la nueva, conserva vigencia y usa snapshot fijado, sin recalcular prorrateo.
- **Datos/salida:** `profile_subscriptions`, aceptación, checkout, idempotencia; después
  current/entitlements/public DTO vuelven a leer. No se emite una membresía, ownership ni
  autoridad clínica. `auto_renew=0` en snapshot de activación.
- **Fallo/estado/evidencia:** no pagado, evento no procesado, aceptación incompatible,
  activa existente, expirado, lock timeout409; mismo replay no duplica.
  Implemented/wired pero POST cerrado con Identity canónico; QA sandbox histórica PP220/231/232.
  No se encontró worker de reconciliación automática de pagos perdidos ni activación de fondo;
  esa ausencia es límite de operación, no permiso para reenviar eventos o cobrar en este audit.

### SUBS-09 — vencimiento, renovación, cancelación y recurrencia

- **Existente:** [current read model](../../modules/subscriptions/services/CurrentSubscriptionReadModelService.php)
  calcula active, expired, grace y fallback FREE; no exige cron para efectuar esa lectura.
  Preview/ruta/checkout admiten `renewal` y estimación de vigencia regular futura.
  `ProfileSubscriptionRepository::markRenewedTo` enlaza reemplazo utilizado por upgrade.
- **Brecha precisa:** activador inspeccionado tiene ramas new/upgrade; renewal llega a la
  rama genérica que rechaza suscripción activa existente, mientras bridge renewal la exige.
  Por ello no se acredita una renovación pagada completa ni se infiere su ejecución desde
  el enum/ruta. Estado partial/unresolved/source-inspected/inactive para ese cierre.
- **Recurrencia:** preview diferencia `disabled`, `pending_saved_payment_method`,
  `not_supported_for_manual_method`, `pending_payment_method`. No se encontraron creación
  de Stripe Billing Subscription/SetupIntent, método guardado, scheduler de débito, facturas
  recurrentes Stripe ni endpoint productivo de cancelación del plan médico.
- **Refund/dispute:** no se hallaron handlers `charge.refunded`/`charge.dispute.*`, refund API
  ni ajuste de entitlement por disputa en el dominio inspeccionado. Cancelación de PI
  recibido por webhook no equivale a devolución ni cancelación recurrente. SPEI/OXXO/card
  pueden ser valores de selección/preview; no prueban cada método operable.
- **Actor/UI/evidencia:** médico, mismo panel; sólo rutas y repositorios anteriores,
  sin writer adicional acreditado; A4 y PP242 mantienen gate, operación externa desconocida.

### SUBS-10 — entitlement comercial interno de proveedores sanitarios

- **Actor/entrada:** staff interno autenticado con `provider_subscription_activate`;
  no UI/HTTP público ni checkout. [ProviderOrganizationSubscriptionService](../../modules/subscriptions/services/ProviderOrganizationSubscriptionService.php)
  expone `activateInternal` y `cancelInternal`.
- **Writer/read model/datos:** transacción locks de organización, plan y suscripción;
  requiere organización elegible, owner gobernado `provider_claim_approval`, familia
  `PROVIDER_ORGANIZATION`, evita dos activas y escribe `profile_subscriptions`/eventos.
  [ProviderCommercialEntitlementService](../../modules/subscriptions/services/ProviderCommercialEntitlementService.php)
  lee entitlement; no sustituye suscripción médica.
- **Gates/estado/evidencia:** `medical_group` necesita provider enablement; no firma ni
  acceso clínico derivado. Implemented/optional-composition/source-inspected,
  activación real no verificada. [DDL](../../modules/subscriptions/db/2026_10_01_01_provider_organization_commercial_entitlement.sql)
  y [QA desechable](../../modules/subscriptions/qa/prov04d_disposable_gate.php) existen; no ejecutados aquí.

## Persistencia y consistencia de pagos

| Almacén/writer | Autoridad y consumidores | Clave/idempotencia y límite |
|---|---|---|
| `subscription_plans` / catálogo y lectores current | Código técnico, familia, periodo/duración | Único `(plan_code,billing_period)`; no significa precio vigente |
| `subscription_plan_prices` / repositorio de precios | Cents, currency, versiones y vigencias servidor | Único plan/period/currency/version; resolver exige candidato único |
| `subscription_payment_routes` / RouteService | Preview congelado y enlace al checkout | UUID único, status/expiración/contexto; consumo transaccional |
| `subscription_contract_acceptances` / AcceptanceService | Versión/hash/snapshot y actor que aceptó | UUID único, vinculación posterior a suscripción; dato financiero/seguridad privado |
| `subscription_checkout_intents` / CheckoutService | Precio, aceptación, plazo y entidad | UUID único y payment_route único; no recurso Checkout Session |
| `subscription_payment_intents` / Create + webhook | Correlación local/remota y status de pago | UUID y `(provider,provider_payment_id)` únicos |
| `subscription_payment_events` / webhook | Registro de confirmación/fallo/cancelación sanitizado | `(provider,provider_event_id)` único + hash; no payload raw ni secreto |
| `subscription_write_idempotency_keys` / IdempotencyService | Request hash, operación/scope, replay/completed/failed | Key por ámbito no autoriza cambiar payload; resultados privados |
| `profile_subscriptions` / activador, escritor legacy, proveedor interno | Suscripción de aplicación, current/visibilidad/capabilities | Doctor versus provider family comprobados en writers; no cobro automático |

Definiciones SQL principales están en
[lifecycle](../../modules/profiles/db/2026_06_19_create_subscription_plan_lifecycle.sql),
[aceptaciones](../../modules/profiles/db/2026_06_20_create_subscription_contract_acceptances.sql),
[precios](../../modules/profiles/db/2026_06_22_create_subscription_plan_prices.sql),
[checkout/PI/eventos](../../modules/profiles/db/2026_06_22_create_subscription_checkout_intents.sql),
[idempotencia](../../modules/profiles/db/2026_06_22_create_subscription_write_idempotency_keys.sql),
[rutas](../../modules/profiles/db/2026_07_08_create_subscription_payment_routes.sql) y
[bridge](../../modules/profiles/db/2026_07_08_add_payment_route_checkout_bridge.sql).
Archivos `_draft` y semillas DEV son históricos/preparación, no una orden de replay.
Relaciones por UUID, entidad y aceptación se validan en servicios; no convertirlas en FKs
físicas si el SQL no las declara. Estado instalado se consulta en el
[mapa de datos](DATOS_Y_MIGRACIONES_MXMED.md), no se deduce de este inventario fuente.

El escritor legacy [CreateSubscriptionWithAcceptanceService](../../modules/subscriptions/services/CreateSubscriptionWithAcceptanceService.php)
existe en `POST E/subscriptions`, transacciona aceptación+suscripción; su source explicita
que no prueba pago. **No es sustituto de confirmación Stripe.** El gate Identity canónico
lo bloquea junto con los demás writers; no usarlo para activar planes desde este mapa.
[ConfirmSubscriptionPaymentIntentMockService](../../modules/subscriptions/services/ConfirmSubscriptionPaymentIntentMockService.php)
y `confirm-mock` están limitados a DEV/local; tampoco son confirmación de Stripe.

## BILL — facturación clínica, archivo y límite PAC

**Ampliación CTX-MAP03 · 2026-10-17 · baseline `0bea8195ce75c309a54cba35acb4605ba290ee00`.**
Los IDs `BILL-01`…`BILL-06` se conservan; `UC-BILL-*` identifica las preguntas de negocio
concretas añadidas. Se inspeccionaron los tres entrypoints Billing, sus cuatro módulos JS,
servicios/repositorios, DDL fiscal y contratos enlazados. Se reutiliza la
[metadata local CTX-MAP02](METADATOS_LOCALES_MXMED.json), no se consultaron filas, HTTP,
navegadores, SAT o PAC ni se ejecutaron tests. Sus diez tablas `billing_*` constan instaladas
**en ese snapshot**; no se verifica instalación ni operación actuales por leerlo.

#### Autoridad compartida y actores por operación

Las tres APIs llaman a [GallerySessionScope::resolve](../../modules/media/services/GallerySessionScope.php#L10).
En runtime canónico revalida la cookie Identity mediante
[PrivateApplicationAccessResolver::resolve](../../modules/identity/http/PrivateApplicationAccessResolver.php#L38)
y [CanonicalDoctorMembershipPolicy::select](../../modules/identity/http/CanonicalClinicalIdentityResolver.php#L40):
una membresía médica elegible, activa, scope `profile|profile_doctor`, rol
`owner|administrator|collaborator`, sin grupo y sin ambigüedad. El origen
`owner_operator_invitation` se deniega expresamente, aunque su etiqueta de rol cambie.
Esto resuelve contexto de aplicación médica; no comprueba una capacidad fiscal individual,
acreditación profesional C4, titularidad del RFC ni autorización del emisor por sí solo.
Las capacidades clínicas que el resolver incluye en su DTO no son un gate fiscal en estos entrypoints.

En la rama legacy del mismo resolver se exige usuario/médico coherentes y se rechaza
`operator_id` o rol distinto de `doctor|medico|principal|owner`; no se usa una marca
`billing` del registro Agenda como autoridad. Los servicios fiscales reciben `doctor_id`,
no un comando delegado con `account_id/session_id/grant_id`. El usuario resuelto se usa
para ligar el CSRF a usuario+médico, no se persiste como actor de negocio.

| Caso/operación | Médico/contexto elegible actual | Operador nombrado por médico | Staff interno México Médico |
|---|---|---|---|
| UC-BILL-01A buscar/seleccionar paciente | Scope del médico de sesión; búsqueda reutilizada | Sin grant fiscal; su nombramiento no pasa el resolver médico | Su relación/grant IW01 no pasa como contexto médico |
| UC-BILL-01B crear/editar/default/archivar receptor | Misma autoridad + relación/paciente activos + CSRF escritura | No conectado; Agenda no concede `billing.receivers.*` | Sin consumidor fiscal interno |
| UC-BILL-02A preview XML/PDF | Misma autoridad + relación activa; perfil opcional de esa relación | No conectado | Sin consumidor fiscal interno |
| UC-BILL-02B confirmar/importar histórico | Misma autoridad + lock relación + CSRF + hashes confirmados | No conectado | Sin consumidor fiscal interno |
| UC-BILL-02C listar/detalle/descargar | Médico propietario + paciente/vínculo activos; scope del documento | No conectado; read/download no se heredan de Agenda | Sin consumidor fiscal interno |
| UC-BILL-03A administrar/default/archivar emisor | Contexto médico + emisor propio + CSRF | No grant ratificado para CSD/emisor; no conectado | No por clase Director/Master/Advisor |
| UC-BILL-03B preferencias y sugerencias | Contexto médico + emisor activo propio + CSRF al guardar | No conectado | Sin consumidor fiscal interno |
| UC-BILL-03C listar/registrar certificado | Contexto médico + emisor propio; CSRF y custodia al registrar | No conectado; nunca implica acceso a llaves/contraseña | No por capacidad de acreditación/revisión |
| UC-BILL-04A crear/actualizar conceptos y totales | Contexto médico + relación activa al guardar + revisión/CSRF | No grant draft/preview implementado | Sin consumidor fiscal interno |
| UC-BILL-04B cargar/listar/preview borrador | Filtro médico; diferencias de revalidación detalladas abajo | No conectado; bootstrap no es DTO fiscal mínimo delegado | Sin consumidor fiscal interno |
| UC-BILL-04C confirmar para emitir | Checkbox UI; no comando de confirmación persistida | No conectado | No implementado |
| UC-BILL-05A certificar | API existe, siempre bloquea 409 | No grant issue implementado | No consumidor de emisión |
| UC-BILL-05B reconciliar resultado PAC | Puerto preparado, sin ruta/conexión operativa | No implementado | No consumidor de reconciliación |
| UC-BILL-06A cancelar CFDI/ver canceladas verificadas | Pestaña informativa, sin autoridad SAT/handler | No implementado | No consumidor fiscal interno |
| UC-BILL-06B cobrar/refund/CFDI de pagos | No writer localizado en Billing inspeccionado | No implementado | No consumidor fiscal interno |

Un staff que además tuviera una membresía médica elegible entraría por **esa membresía**,
no por ser staff; no se hizo un probe de cuentas reales ni se infirió tal relación.
[IW01](../product-completion/iw01-internal-governance-foundation.md) separa gobierno interno
y operaciones; [OP-POL02, matriz fiscal y Q08](../identity/OP-POL02_NONMEDICAL_OPERATOR_IMPLEMENTATION_READINESS.md#4-mapa-por-operación-capacidades-endpoints-y-cambios-mínimos)
plantea `billing.receivers.read/edit`, `billing.invoices.draft/preview/issue/read/download/import`
como consumidores futuros con DTO/actor mínimos y decisiones Q08 pendientes. No son grants
entregados ni permiso para habilitarlos. El
[catálogo canónico actual](../../modules/identity/services/ProfileOperatorCapabilityCatalog.php#L8)
contiene sólo cinco grants Agenda. El
[catálogo interno](../../modules/identity/services/InternalCapabilityCatalog.php#L13)
no contiene una operación de facturación de pacientes. Reutilizar los servicios fiscales
exigiría un consumidor delegado explícito, autorización por acción, proyección mínima,
identidad del actor y auditoría; no ampliar el bootstrap médico ni copiar el rol amplio legacy.

### BILL-01 — perfiles fiscales del receptor

- **Actor/UI:** médico autorizado respecto de paciente activo; Facturación → Pacientes,
  `#mx-billing-profile-editor`, [patient-profiles.js](../../assets/js/billing/patient-profiles.js).
- **Rutas/servicio/writer:** [patient-profiles.php](../../api/billing/patient-profiles.php):
  GET catálogo/search/list, POST crear/default, PUT editar, DELETE archivar;
  [PatientBillingProfilesService](../../modules/billing/services/PatientBillingProfilesService.php)
  → [PatientBillingProfilesRepository](../../modules/billing/repositories/PatientBillingProfilesRepository.php).
- **Scope/datos/read:** `billing_patient_profiles` pertenece a `doctor_id+patient_id`,
  exige `patients_doctor_links` activo; no autoridad fiscal global del paciente ni permiso
  operador billing genérico. Predeterminado único activo y lock de vínculo médico/paciente;
  archivo lógico, no borrado. Catálogo SAT local, CSRF `X-Billing-Profiles-CSRF`, private/no-store.
- **Resultado/errores/estado:** perfiles para facturar, RFC/régimen/uso/CP validados,
  no verificación de registro RFC ante SAT; 401/403/404, validación422.
  Implemented/wired/source-inspected; [contrato FISC01](../../modules/billing/README.md) y
  [test](../../modules/billing/tests/PatientBillingProfilesTest.php) histórico/inspeccionado,
  sin nueva ejecución ni lectura de filas reales.

#### UC-BILL-01A / UC-BILL-01B — ¿a quién factura y qué receptor se usa?

- **Política:** [FISC01](../../modules/billing/README.md) hace al receptor propiedad de la
  relación médico–paciente, no de la identidad global ni del pagador de suscripción.
  El paciente clínico y la persona física/moral receptora pueden tener nombres distintos;
  la aplicación no los equipara ni verifica la inscripción fiscal ante SAT.
- **UC-BILL-01A, pantalla→lectura:** `#mx-billing-patient-search` submit →
  [handler de búsqueda](../../assets/js/billing/patient-profiles.js#L92) →
  `GET /api/billing/patient-profiles.php?action=search&q=…` →
  [PatientsRepository::searchPatientsByDoctorId](../../api/billing/patient-profiles.php#L48).
  Devuelve hasta 25 referencias `patient_id/display_name/sex`; `selectPatient` carga
  `GET ?patient_id=…` y emite `mxmed:billing-patient-selected` a archivo/compositor.
  Seleccionar en navegador no crea relación ni factura; no persiste una selección global.
  La proyección contiene sexo para el icono: no asumirla suficiente/minimizada para un futuro delegado.
- **UC-BILL-01B, controles→writer:** formulario `#mx-billing-profile-editor`, acciones
  Editar/Hacer predeterminado/Eliminar → [handlers](../../assets/js/billing/patient-profiles.js#L108)
  → `POST` crear, `PUT` editar, `POST action=set_default`, `DELETE` archivar en la misma API
  → [PatientBillingProfilesService::create/update/makeDefault/archive](../../modules/billing/services/PatientBillingProfilesService.php#L23)
  → [repositorio](../../modules/billing/repositories/PatientBillingProfilesRepository.php#L24).
  Persiste `billing_patient_profiles.{billing_profile_id,doctor_id,patient_id,alias,
  receiver_legal_name,rfc,fiscal_zip_code,fiscal_regime_code,default_cfdi_use_code,
  billing_email,is_default,archived_at,created_at,updated_at}`. Esos son nombres de campos,
  no un payload ni valores reales. Default del primero o explícito; lock del vínculo y
  unique generado `(doctor_id,patient_id,active_default_slot)` impiden dos activos.
- **Lectores/efectos/historia:** `list/findActive` exige vínculo y paciente activos;
  compositor elige default o primer perfil y guarda el ID. Al archivar se oculta, no se
  borra; receptor snapshot de CFDI importado no cambia. No se halló ledger de cambios de
  receptor con cuenta/sesión en servicio/repositorio/DDL inspeccionados; timestamps no
  identifican quién editó. Reutilizar este maestro, no inventar otro receptor por pantalla.

### BILL-02 — importar y consultar CFDI histórico

- **Actor/UI/route:** médico en la misma relación; tabs `#mx-billing-invoices-panel`,
  «Listar Facturas», [invoices.js](../../assets/js/billing/invoices.js);
  [invoices.php](../../api/billing/invoices.php) GET list/detail/download, POST search/preview/import.
- **Servicio/writer/lectura:** [InvoiceArchiveService](../../modules/billing/services/InvoiceArchiveService.php),
  [HistoricalCfdiParser](../../modules/billing/services/HistoricalCfdiParser.php),
  [InvoiceRepository](../../modules/billing/repositories/InvoiceRepository.php),
  [InvoiceDocumentStorage](../../modules/billing/services/InvoiceDocumentStorage.php).
- **Datos/transacción:** `billing_invoices`, snapshot receptor del XML inmutable;
  `source_type=HISTORICAL_IMPORT`, estado inicial `UNKNOWN`. XML CFDI4 ingreso/timbre1.1,
  PDF opcional; checksums únicos por médico, confirmación del preview. Bloquea relación,
  objetos privados inmutables y compensación de objetos nuevos si SQL falla; downloads
  revalidan scope/hash, nunca URL pública. Importación no verifica vigencia SAT.
- **Gates/errores/evidencia:** CSRF `X-Billing-Invoices-CSRF`; límites5MiB,
  XXE/DTD/red externa XML rechazados; duplicado, checksum/scope/file mismatch cierran.
  Implemented/wired/source-inspected; [FISC02A](../../modules/billing/README.md),
  [test archivo](../../modules/billing/tests/InvoiceArchiveTest.php); no PAC llamado ni factura emitida.

#### UC-BILL-02A / UC-BILL-02B / UC-BILL-02C — archivo, confirmación y descarga privada

- **Política:** [FISC02A](../../modules/billing/README.md#fisc02a--archivo-de-facturas-históricas)
  archiva evidencia histórica sin convertirla en emisión propia ni afirmar vigencia SAT.
  Pantallas originales `#mx-billing-invoices-panel` y `#cfdi-listar`; no son la factura
  de México Médico por un plan ni un recibo de Stripe.
- **UC-BILL-02A:** `#mx-invoice-import-form` submit →
  [invoices.js: preview/importData](../../assets/js/billing/invoices.js#L83) →
  `POST multipart /api/billing/invoices.php action=preview` (`patient_id`, perfil opcional,
  XML obligatorio/PDF opcional) →
  [InvoiceArchiveService::preview](../../modules/billing/services/InvoiceArchiveService.php#L16).
  Revalida scope, formato, duplicado y hashes; no escribe factura ni storage durable.
- **UC-BILL-02B:** «Confirmar importación» `#mx-invoice-confirm` →
  [handler de confirmación](../../assets/js/billing/invoices.js#L89) →
  `POST multipart action=import` con `confirmed_xml_sha256/confirmed_pdf_sha256` →
  [InvoiceArchiveService::import](../../modules/billing/services/InvoiceArchiveService.php#L37).
  Repite parseo/alcance/duplicado, exige exactamente los archivos vistos, inicia transacción,
  bloquea vínculo, guarda privados inmutables e inserta `billing_invoices`.
  `(doctor_id,cfdi_uuid)` y `(doctor_id,xml_sha256)` son únicos; un fallo compensa sólo los
  objetos recién guardados si no existe factura comprometida. No hay actualización/borrado HTTP.
- **Persistencia/lectores:** `billing_invoices` conserva `invoice_id,doctor_id,patient_id,
  billing_profile_id?,source_type=HISTORICAL_IMPORT,status=UNKNOWN,cfdi_uuid`, importes,
  `receiver_*_snapshot,cfdi_use_code_snapshot`, claves privadas `xml/pdf_storage_key`,
  `xml/pdf_sha256,xml/pdf_bytes,created_at`. Snapshot viene del XML, no del receptor actual;
  no hay `imported_by_account_id/session_id` en este writer/DDL. El XML/PDF y su contexto
  identifican documento/origen, no el humano importador.
- **UC-BILL-02C:** tabs/lista → `GET action=list`, filtros fiscales por
  `POST application/json action=search` para no poner RFC/receptor en URL; Ver detalles →
  `GET action=detail&invoice_id=…&patient_id=…`; Descargar XML/PDF →
  `GET action=download&invoice_id=…&patient_id=…&kind=xml|pdf` →
  [InvoiceRepository::get/list](../../modules/billing/repositories/InvoiceRepository.php#L45)
  (join médico/vínculo/paciente activos; `patient_id` adicional puede omitirse en API sin
  perder el filtro médico) →
  [InvoiceArchiveService::document](../../modules/billing/services/InvoiceArchiveService.php#L102)
  comprueba clave esperada, hash/tamaño y sirve `attachment`; no devuelve URL pública.
  No se halló auditoría de acceso/descarga por cuenta en estas rutas. Reutilizar archivo,
  parser y storage; una futura auditoría/grant de lectura es distinta de importar o emitir.

### BILL-03 — emisor, preferencias y custodia CSD

- **Actor/UI/route:** médico; `#cfdi-perfil-fiscal` y
  [fiscal-profile.js](../../assets/js/billing/fiscal-profile.js);
  [issuance.php](../../api/billing/issuance.php) GET bootstrap/issuer_csd,
  POST create_issuer/default_issuer/register_csd,
  PUT update_issuer/save_issuer_preferences, DELETE archive_issuer.
- **Servicios/writer/read:** [IssuerProfileService](../../modules/billing/services/IssuerProfileService.php),
  [IssuerPreferencesService](../../modules/billing/services/IssuerPreferencesService.php),
  [CsdCredentialService](../../modules/billing/services/CsdCredentialService.php).
- **Datos/seguridad:** `billing_issuer_profiles` (varios emisores, un default activo),
  `billing_issuer_preferences`, `billing_csd_credentials`; nombre/RFC emisor fiscal no se
  infiere del perfil público. [EncryptedLocalCsdStore](../../modules/billing/services/EncryptedLocalCsdStore.php)
  implementa `CsdSecretPort`, AES-256-GCM con AAD de clave relativa, llave32 bytes y archivos0600
  fuera del document root; no basta el nombre `.enc` para afirmar cifrado.
- **Resultado/gates:** parseo/password/pareja cert-key/serie/RFC/vigencia, conserva
  `certificate_type=UNVERIFIED`: X.509 válido no prueba CSD versus e.firma ni permite timbrar.
  `MXMED_CSD_ENCRYPTION_KEY`, `MXMED_PRIVATE_CSD_ROOT`, CSRF `X-Billing-Issuance-CSRF`;
  sin llave cierra503. Implemented/wired/source-inspected, QA CSD sintética FISC02B;
  operación real/custodia productiva no verificadas.

#### UC-BILL-03A / UC-BILL-03B / UC-BILL-03C — emisor autorizado y secretos

- **Política:** [FISC02B](../billing/FISC02B_ISSUANCE_CORE.md#autoridades) y
  [FISC-UX02](../billing/FISC-UX02_ISSUER_PREFERENCES.md) separan persona fiscal emisora,
  perfil médico publicado, sugerencias UX y certificado. Las referencias PAC viejas se
  leen con sucesor [PAC-DEC01](../billing/PAC_DEC01_PROVIDER_SELECTION_AND_INTEGRATION_STRATEGY.md).
- **UC-BILL-03A:** `#mx-fiscal-editor`, tarjetas/default/archivo del módulo
  [fiscal-profile.js](../../assets/js/billing/fiscal-profile.js#L99) →
  `/api/billing/issuance.php`: `GET action=bootstrap`, `POST create_issuer/default_issuer`,
  `PUT update_issuer`, `DELETE archive_issuer` →
  [IssuerProfileService](../../modules/billing/services/IssuerProfileService.php#L26).
  Persiste `billing_issuer_profiles.{issuer_profile_id,doctor_id,alias,issuer_legal_name,
  rfc,fiscal_regime_code,expedition_postal_code,is_default,archived_at}`; lock médico +
  unique `(doctor_id,active_default_slot)` preservan máximo un predeterminado activo;
  archivar el predeterminado lo deja en cero y no nombra automáticamente otro.
  `ISSUER_CREATED/UPDATED/DEFAULT_CHANGED/ARCHIVED` se registran
  por médico/recurso. No es permiso para hacer owner a un collaborator ni prueba de mandato fiscal.
- **UC-BILL-03B:** `#mx-fiscal-preferences` →
  [submit](../../assets/js/billing/fiscal-profile.js#L114) →
  `PUT action=save_issuer_preferences` →
  [IssuerPreferencesService::save/list/suggestion](../../modules/billing/services/IssuerPreferencesService.php#L23).
  `billing_issuer_preferences` usa `(issuer_profile_id,doctor_id)` y campos
  `default_concept_description,habitual_unit_price,invoice_logo_mode,updated_at`.
  El lector JS sólo propone descripción/precio al primer concepto no editado; al guardar,
  el borrador toma valores explícitos. Nunca infiere clave SAT, impuestos ni reescribe
  borradores/facturas previos. `suggestion` consulta credenciales profesionales verificadas
  activas; fallback «Servicios profesionales». Logo refiere medio aprobado READY,
  no archivo/CSD; render PDF fiscal pendiente. Save no emite evento de auditoría en el servicio inspeccionado.
- **UC-BILL-03C:** formulario CSD → [submit multipart](../../assets/js/billing/fiscal-profile.js#L120)
  → `POST action=register_csd` (`certificate/private_key/password`), consulta metadata
  `GET action=issuer_csd&issuer_id=…` →
  [CsdCredentialService::register/list](../../modules/billing/services/CsdCredentialService.php#L14).
  `billing_csd_credentials` guarda `credential_id,issuer_profile_id,doctor_id,
  certificate_serial,certificate_rfc,certificate_sha256,valid_from,valid_to,
  certificate_type=UNVERIFIED` y referencias de custodia, no claves/password en columnas.
  `EncryptedLocalCsdStore` cifra cer/key/password; rollback compensa objetos nuevos.
  Registro comprueba RFC si se pudo extraer del sujeto; no garantiza haberlo extraído.
  No hay writer entregado que promueva `UNVERIFIED` a `CSD_VERIFIED`; el validador previo
  exige este último y vigencia. `CSD_REGISTERED` registra recurso/resultado sin material secreto.
  Reutilizar el puerto de custodia, no exponer el bootstrap/CSD al delegado.

### BILL-04 — compositor, cálculo exacto y preview de borrador

- **Actor/UI/route:** médico/relación paciente válida, `#cfdi-crear`,
  [issuance.js](../../assets/js/billing/issuance.js);
  [issuance.php](../../api/billing/issuance.php) POST create_draft, PUT update_draft,
  GET draft/preview/bootstrap.
- **Servicios/writer:** [InvoiceDraftService](../../modules/billing/services/InvoiceDraftService.php),
  [ExactInvoiceMath](../../modules/billing/services/ExactInvoiceMath.php),
  [CfdiPreStampValidator](../../modules/billing/services/CfdiPreStampValidator.php),
  [CfdiDraftXmlBuilder](../../modules/billing/services/CfdiDraftXmlBuilder.php).
- **Transacción/datos:** `billing_invoice_drafts/items/item_taxes`, lock scope al guardar, revisión
  optimista, conceptos/impuestos y sumas DECIMAL18,6/BCMath servidor; no float cliente como
  autoridad. Guarda `DRAFT`, lectura devuelve cálculos y validaciones. XML estructural sin sello.
  `billing_invoice_issuance_events` registra metadata de acción sin secretos/XML.
- **Gates/error/estado:** claves SAT, uso/régimen, forma/método, importes/tasas/vigencia,
  revision conflict/locked, CSRF y permisos; catálogos incompletos para timbrar permanecen
  como errores previos, no se completan con supuestos. Implemented/wired/source-inspected;
  [FISC02B](../billing/FISC02B_ISSUANCE_CORE.md),
  [IssuanceCoreTest](../../modules/billing/tests/IssuanceCoreTest.php), QA histórica16sept2026;
  no acredita `CERTIFIED` ni cálculo ejecutado en este audit.

#### UC-BILL-04A / UC-BILL-04B / UC-BILL-04C — conceptos, revisión y confirmación

- **UC-BILL-04A, productor:** `#cfdi-crear` → selección recibida por
  `mxmed:billing-patient-selected`, emisor/default receptor → formulario
  `#mx-issuance-draft-form` → [readDraft/submit](../../assets/js/billing/issuance.js#L86)
  → `POST action=create_draft` o `PUT action=update_draft` en `issuance.php` →
  [InvoiceDraftService::save](../../modules/billing/services/InvoiceDraftService.php#L33).
  Se comprueban paciente/vínculo activos, emisor propio y receptor activo de esa misma
  relación; IDs de solicitud no sustituyen el médico de sesión. Campo `revision` requerido
  al editar; lock + comparación exacta o `draft_revision_conflict`409.
- **Cálculo/persistencia:** [ExactInvoiceMath::compute](../../modules/billing/services/ExactInvoiceMath.php#L21)
  recibe cantidades/valores/descuentos como strings decimales, admite 1–100 conceptos,
  valida descuento ≤ subtotal y calcula traslados/retenciones (`Tasa|Cuota|Exento`) con
  BCMath a seis decimales, sin autoridad del total del navegador.
  `billing_invoice_drafts` guarda scope/IDs, uso/moneda/método/forma/serie/folio,
  `state=DRAFT,revision,subtotal,discount,tax_total,total`; `billing_invoice_draft_items`
  guarda `line_no,product_service_code,description,quantity,unit_code,unit_value,discount,
  line_subtotal,line_total,tax_object_code`; `billing_invoice_draft_item_taxes` guarda
  `direction,tax_code,factor_code,rate,tax_base,amount`. Cada actualización sustituye
  items/impuestos y aumenta revisión; no conserva todos los contenidos anteriores como
  versiones inmutables. Evento `DRAFT_CREATED/UPDATED` se une a la transacción.
  **Descuento fiscal por concepto no es cupón del plan**, promoción Stripe, comisión ni
  atribución de asesor; no se deduce ninguna conexión comercial de esos campos.
- **UC-BILL-04B, lectores:** `GET action=bootstrap` lista borradores médicos;
  `GET action=draft&draft_id=…` →
  [InvoiceDraftService::list/get](../../modules/billing/services/InvoiceDraftService.php#L17)
  filtra doctor, pero esos métodos no vuelven a exigir vínculo/paciente activo.
  `GET action=preview` →
  [CfdiPreStampValidator::inspect](../../modules/billing/services/CfdiPreStampValidator.php#L14)
  comprueba vínculo activo, receptor/emisor, CSD verificado, catálogos e importes y devuelve
  incidencias como datos; `issuance.php` responde preview200 incluso con errores de validación.
  No tratarlo como denegación403 de lectura ni como borrador READY autorizado.
  El preview consulta emisor/receptor actuales: `DRAFT` tiene referencias y cantidades
  guardadas, no snapshot fiscal final del emisor/receptor aceptado para emitir.
- **UC-BILL-04C, primer límite:** el renderer de
  [issuance.js](../../assets/js/billing/issuance.js#L22) ofrece checkbox
  `#mx-issuance-confirm` y botón `#mx-issuance-stamp` permanentemente disabled.
  No se encontró handler de ese checkbox que persista confirmación, identidad del aceptante
  o revisión confirmada para timbrar. «Guardar borrador y revisar» sí guarda y abre preview;
  no equivale a aceptar emisión. Reutilizar compositor, revisión, validación y archivo
  antes de definir un futuro comando certificado; el contrato exacto de confirmación fiscal sigue pendiente.

### BILL-05 — timbrado, timeout y reconciliación PAC: frontera preparada

- **Actor/UI/route:** mismo médico, acción certify existente pero cerrada;
  `POST issuance.php` action `certify` registra `CERTIFICATION_BLOCKED/PAC_UNCONFIGURED`
  y devuelve409 `pac_provider_selection_required`.
- **Decisión actual:** [PAC-DEC01](../billing/PAC_DEC01_PROVIDER_SELECTION_AND_INTEGRATION_STRATEGY.md),
  publicado `4f71fb0e` (05oct2026), fija **PRIMARY_PAC_TARGET=SW** y FINKOK como alternativa.
  No reabrir selección por el copy antiguo de FISC02B ni por el nombre del error runtime.
- **Puerto/estado/datos:** [CfdiCertificationProviderPort](../../modules/billing/services/CfdiCertificationProviderPort.php)
  `certify/reconcile`, adaptador actual `UnconfiguredPacAdapter` rechaza ambos.
  DDL de `billing_invoice_certification_attempts` prepara unicidad de
  `(draft_id,draft_revision)` e idempotency única; no hay writer HTTP de reserva entregado.
  `CERTIFICATION_PENDING/CERTIFIED/CERTIFICATION_FAILED/RECONCILIATION_REQUIRED` están modelados,
  no una transición HTTP entregada de certificación. `SwProvider` no existe.
- **Contrato de fallo:** timeout ambiguo exige reconciliar identidad de emisión estable;
  recuperar mismo XML/UUID si timbrado, no repetir factura a ciegas. CustomID SW es defensa
  adicional con límite temporal registrado en PAC-DEC01, nunca idempotencia permanente única.
  Debe validar respuesta, custodia y vínculo exacto factura/borrador antes de activar.
- **Estado/evidencia:** approved/partial/optional boundary/source-inspected/inactive.
  No sandbox ni producción PAC realizados aquí; contrato de firma, cancelación ordinaria,
  respuestas SAT, multi-RFC, auth y compras aún requieren implementación/QA/autorización.

#### UC-BILL-05A / UC-BILL-05B — ¿se puede timbrar o resolver una respuesta ambigua?

- **UC-BILL-05A:** [dispatcher certify](../../api/billing/issuance.php#L93) no invoca
  validador, puerto PAC ni writer de tentativa: sólo registra `CERTIFICATION_BLOCKED`
  con `PAC_UNCONFIGURED` y responde409. Bootstrap mantiene `pac_provider_selected=false`
  y `certification_enabled=false`: describe integración runtime, no revoca la selección SW.
  El botón disabled no es una certificación parcialmente exitosa.
- **UC-BILL-05B:** [CfdiCertificationProviderPort::reconcile](../../modules/billing/services/CfdiCertificationProviderPort.php#L11)
  existe, pero [UnconfiguredPacAdapter::reconcile](../../modules/billing/services/UnconfiguredPacAdapter.php#L10)
  rechaza; no se halló action HTTP `reconcile`, worker reconciliador ni writer productivo
  de `billing_invoice_certification_attempts` en `api/billing`, servicios Billing y JS inspeccionados.
  Tabla/estados/idempotencia son preparación, no historial de un PAC ejecutado.
- **Recuperación/decisión:** PAC-DEC01 exige reutilizar identidad estable y reconciliar
  timeout sin duplicar; integración SW primaria y Finkok alternativa requieren autorización,
  adapter/custodia/modelo de firma, validación de respuesta, snapshot final y transacción
  tentativa+factura+objetos. No se reabre selección ni se propone llamar al proveedor para completar este mapa.

### BILL-06 — facturas canceladas, CFDI pagos y cobro al paciente

- **Actor/UI:** médico; `#cfdi-canceladas` es informativo. No handler de cancelación fiscal,
  CFDI de pagos, refund al paciente ni writer de cobro clínico entregado en Billing inspeccionado.
- **Fuente/read model/datos:** archivo conserva status histórico sin verificar y snapshots;
  un XML importado no acredita vigencia/cancelación ante SAT. No integrar un procesador de
  pagos nuevo por una pestaña o enum existente.
- **Estado:** partial/absent para ejecución fiscal, disconnected, source-inspected,
  authorization-pending. La mínima siguiente acción depende de autorización PAC, no de Stripe.

#### UC-BILL-06A / UC-BILL-06B — cancelación y cobro son casos distintos

- **UC-BILL-06A:** [pestaña original `#cfdi-canceladas`](../../private-app.php#L10306)
  advierte que necesita verificación fiscal confiable. El archivo puede leer
  `status=VERIFIED_CANCELED` como filtro/enum, pero import siempre escribe `UNKNOWN`;
  no se encontró verificador SAT, comando de cancelación, motivo/sustitución/acuse ni
  writer que cambie el estado fiscal en `api/billing`, `modules/billing` y JS Billing.
  No interpretar enum, pestaña o XML importado como cancelación ejecutable/confirmada.
- **UC-BILL-06B:** método/forma de pago CFDI describen la factura; no capturan dinero.
  En esas mismas áreas se buscaron `cancel`, `cancelar`, `refund`, `payment`, `pago`,
  `reconcile`, `certify`, `PAC` y sus dispatchers/servicios. No se localizó cobro a paciente,
  refund o complemento CFDI de pagos funcional. Ésta es ausencia en el alcance inspeccionado,
  no afirmación universal sobre todos los proveedores/rutas históricas del repositorio.
  [SUBS-04](#subs-04--crear-paymentintent-stripe-desde-snapshot-servidor) procesa el plan
  del médico para México Médico; no reutilizarlo como cobro clínico por coincidencia de términos.

#### Estado por caso y límites de evidencia CTX-MAP03

| Casos | PRODUCT_POLICY | SOURCE_IMPLEMENTATION | CONNECTION | VERIFICATION / entorno | OPERATION |
|---|---|---|---|---|---|
| UC-BILL-01A/01B | FISC01; delegación Q08 no decidida | IMPLEMENTED_AND_WIRED para contexto médico | UI/API/servicio/maestro conectados en fuente | SOURCE_INSPECTED `0bea8195`; tests FISC01 existentes/históricos, no corridos | UNVERIFIED normal-local actual |
| UC-BILL-02A/02B/02C | FISC02A archivo privado | IMPLEMENTED_AND_WIRED para contexto médico | Preview→confirmación→archivo→download; sin actor delegado | SOURCE_INSPECTED; QA histórica FISC02A local 16sept2026; sin reejecución | UNVERIFIED normal-local actual; no emisión |
| UC-BILL-03A/03B | FISC02B/FISC-UX02; no grant de operador emisor | IMPLEMENTED_AND_WIRED | Emisor/default/preferencias→compositor; PDF logo pendiente | SOURCE_INSPECTED; tests IssuerPreferencesSuggestion/IssuanceCore existentes | UNVERIFIED normal-local actual |
| UC-BILL-03C | FISC02B custodia; promoción CSD pendiente | IMPLEMENTED_AND_WIRED registro; PREPARED_BUT_INACTIVE emisión | CSD registra UNVERIFIED; no writer de verificación SAT localizado | SOURCE_INSPECTED; certificados sintéticos QA histórica, ninguno real usado | UNVERIFIED custodia actual; emisión inactiva |
| UC-BILL-04A/04B | FISC02B borrador/validación | IMPLEMENTED_AND_WIRED con diferencias de scope de lectura explícitas | Guardar→revisión→preview; sin snapshot final fiscal | SOURCE_INSPECTED; QA FISC02B 16sept2026 histórica; no test actual | UNVERIFIED normal-local actual |
| UC-BILL-04C | Confirmación final requiere contrato técnico fiscal | UI_ONLY | Checkbox sin writer/handler de aceptación | SOURCE_INSPECTED del JS/dispatcher | Inactiva |
| UC-BILL-05A/05B | PAC-DEC01 SW primario/Finkok alternativa aprobado | PREPARED_BUT_INACTIVE; certify bloqueado; puerto/DDL preparados | Sin adapter SW, ruta reconcile ni writer de tentativa localizado | SOURCE_INSPECTED; sin PAC/SAT remoto ni sandbox actual | Inactiva; autorización pendiente |
| UC-BILL-06A/06B | Extensión fiscal/cobro fuera de entrega FISC02B | UI_ONLY canceladas; NOT_FOUND_IN_INSPECTED_SCOPE comandos | Sin escritor fiscal/de cobro localizado | SOURCE_INSPECTED en áreas/alternativas de búsqueda citadas | No ejecución operativa entregada en este scope |

La auditoría [BillingIssuanceAudit::record](../../modules/billing/services/BillingIssuanceAudit.php#L12)
y [DDL de eventos](../../modules/billing/db/2026_09_16_03_create_invoice_issuance_core.sql#L166)
guardan `event_id,doctor_id,patient_id?,draft_id?,invoice_id?,issuer_profile_id?,
billing_profile_id?,action,result,occurred_at`, sin cuenta/sesión/grant. La revisión del
borrador tampoco equivale a versionado íntegro de contenido. Preservar estas diferencias
es requisito previo a una delegación futura, no instrucción para migrar o modificar datos ahora.

## Journeys representativos 8 y 10

### J08 — plan → pago → evento → activación → entitlements

**ASE-COM01, sucesor de política comercial:** [contrato y traza acotada](../identity/ASE-COM01-commercial-attribution-recurring-commission.md) exige conservar un originador por cliente y comisiones por pagos elegibles, incluidas renovaciones mensuales/anuales. El 15% recuperado es descuento del cliente, no comisión; tasa/base/duración del descuento/liquidación pendientes D01–D08. Este requerimiento no elimina las brechas SUBS-09 ni demuestra Stripe Billing, cargos recurrentes, Connect o payouts. Se reutilizan SUBS-02..08 como evidencia de precio/contrato/pago/activación, pero faltan código/atribución/reglas/consumer comisionable/resultado único y liquidación. Confirmación, activación, devengado y liquidación son cuatro hechos separados; no una transacción común. Nada de esto modifica Patient Billing/PAC ni comisiones de Agenda/Call Center. Sin llamadas Stripe, SQL ni aceptación financiera nueva.

`#p-suscripcion / seleccionar plan` → handlers de ruta [SUBS-02](#subs-02--seleccionar-catálogo-y-preparar-ruta-de-pago)
→ API y contexto servidor → preview/precio catálogo → writer ruta/idempotencia
→ [SUBS-03](#subs-03--aceptación-contractual-y-checkout-interno) aceptación/checkout transaccional
→ [SUBS-04](#subs-04--crear-paymentintent-stripe-desde-snapshot-servidor) request Stripe test
→ PI local → [SUBS-05](#subs-05--clave-pública-secreto-efímero-y-montaje-payment-element) input montado.

**Interrupción actual:** [SUBS-06](#subs-06--confirmar-en-navegador-y-retornar-de-stripe)
no tiene confirmador/bridge; writes Identity también están cerrados. Confirmación externa
sandbox sólo forma parte de QA histórica, nunca de una instrucción de este mapa.

`Stripe confirma externamente` → webhook asíncrono firmado
[SUBS-07](#subs-07--webhook-firmado-deduplicación-y-confirmación-autoritativa-de-pago)
→ transacción evento+PI pagado → lectura `payment-activation-state`
→ acción **separada** de servidor [SUBS-08](#subs-08--reconciliar-lectura-y-activar-después-del-pago)
→ transacción suscripción/aceptación/checkout/idempotencia → current/capacidades
[SUBS-01](#subs-01--consultar-plan-capacidades-y-publicación) → panel/DTO público aplicando flags.

Idempotencia por ruta/checkout/PI/evento/activación es distinta en cada frontera;
una key HTTP no transforma toda la cadena en una sola transacción. No hay auto-activación
por return, refresh, nombre de plan ni webhook. Pago confirmado y plan activo son estados distintos.

### J10 — contexto clínico → receptor/emisor → borrador/archivo → límite PAC

`Médico selecciona paciente` → buscador canónico en Facturación
→ [BILL-01](#bill-01--perfiles-fiscales-del-receptor), scope doctor-patient activo
→ perfil fiscal receptor almacenado → [BILL-03](#bill-03--emisor-preferencias-y-custodia-csd)
emisor/preferencias y CSD privado → [BILL-04](#bill-04--compositor-cálculo-exacto-y-preview-de-borrador)
handler create/update → transacción revisión+conceptos+impuestos+metadata de auditoría
→ preview y errores previos → [BILL-05](#bill-05--timbrado-timeout-y-reconciliación-pac-frontera-preparada)
certify bloqueado409. No crea CFDI ni comunica un pago.

Camino histórico independiente: XML/PDF del médico → preview parser seguro
→ confirmación de hashes → [BILL-02](#bill-02--importar-y-consultar-cfdi-histórico)
transacción factura/snapshot + archivos privados inmutables → lista/detalle/download scope/hash
→ estado fiscal `UNKNOWN` hasta verificación confiable. No se halló una conexión que
convierta automáticamente receta/encounter/orden en factura timbrada o pago Stripe.

## Integraciones externas y recursos compartidos

Inventario de **18 dependencias o fronteras** detectadas en las superficies fuente
inspeccionadas. Agrupa proveedores/recursos por propósito; no cuenta cada URL ni cada biblioteca
vendor como integración de producto. Dominios `.example/.invalid`, payloads fixtures y links
de documentación no son servicios contratados. Variables se registran **sólo por nombre**.

| ID / propósito y productor → consumidor | Fuente y configuración | Comportamiento/gate, evidencia y estado |
|---|---|---|
| EXT-01 Stripe API / planes | [Provider](../../modules/subscriptions/services/StripePaymentIntentProviderService.php); `STRIPE_SECRET_KEY`, `MXMED_ENV`, `APP_ENV`, `ENVIRONMENT` según helpers | Creación/GET PI, timeout20s y validaciones; sólo test, firma webhook separada. SUBS-04/07/08, sandbox histórico; remoto no verificado. |
| EXT-02 Stripe.js / input de pago | [app.js](../../assets/js/app.js), `STRIPE_PUBLISHABLE_KEY`, `STRIPE_WEBHOOK_EXPECTED_LIVEMODE`; loader Dahlia | Configuración pública autenticada y Element; timeout15s/conflict/contexto/teardown. Confirmar/retorno pendientes; no credencial secreta en docs/DOM/storage. |
| EXT-03 SES Identity / registro, recuperación, invitación | [SesIdentityNotificationAdapter](../../modules/identity/adapters/SesIdentityNotificationAdapter.php), [ProductiveIdentityHttpConfiguration](../../modules/identity/http/ProductiveIdentityHttpConfiguration.php), [composition](../../modules/identity/http/IdentityHttpComposition.php); `MXMED_EMAIL_PROVIDER`, `MXMED_SES_REGION`, `MXMED_EMAIL_FROM_ADDRESS`, `MXMED_EMAIL_FROM_NAME`, `MXMED_EMAIL_REPLY_TO`, `MXMED_IDENTITY_ORIGIN` | SDK `SesV2Client`, runtime IAM, región/remitente/origen HTTPS fijados por contrato. Env keys AWS/SMTP estáticas son rechazadas por configuración productiva. MessageId obligatorio, error `notification_unavailable`. Normal-local usa sink privado preview; envío externo no acreditado ahora. |
| EXT-04 SES OTP de Agenda / email desafío | [PublicAgendaOtpComposition](../../modules/agenda/composition/PublicAgendaOtpComposition.php) → [SesPublicAgendaOtpAdapter](../../modules/agenda/adapters/SesPublicAgendaOtpAdapter.php), mismas variables email | OtpProviderPort, sólo email; no confundir con SMS. SDK/config faltante cierra. [Test](../../modules/agenda/tests/SesPublicAgendaOtpAdapterTest.php) existe; no envío ni prueba aquí. |
| EXT-05 SES outbox de Operadores / entrega invitación | [dispatcher CLI](../../scripts/operator-invitation-dispatch.php), [SesOperatorInvitationTransport](../../modules/identity/operators/SesOperatorInvitationTransport.php); `MXMED_OPERATOR_DELIVERY_ENABLED`, provider SES y autorización en receipt privado | Default disabled, una dispatch acotada; gobierno de delivery/origen/grants. Diferencia fallo conocido no aceptado, retryable y `uncertain`; SES no idempotency token, no retry ciego incierto. No invitación real enviada. |
| EXT-06 SMS/OTP / verificación profesional | [SmsIntegration](../../modules/identity/Operational/SmsIntegration.php), `OtpProviderPort`; registro aprobado vacío | `configured()` null: proveedor real no integrado/aprobado. Tests sintéticos son otra composición. [verify-sms.php](../../api/verify-sms.php) está retired410, no gateway; copy o número no prueba entrega SMS/WhatsApp/voz. |
| EXT-07 Google Maps JS / selector Consultorio y perfil | [multisede.js](../../assets/js/perfil/consultorio/multisede.js) y [GoogleGeocodeController](../../modules/agenda/controllers/GoogleGeocodeController.php); `MXMED_GOOGLE_MAPS_JS_API_KEY`, `GOOGLE_MAPS_JS_API_KEY`, clave de configuración `google_maps_js_api_key` en [ejemplo](../../api/mxmed-google.config.example.php) | Config autenticada/scoped, carga biblioteca marker, marcador/manual existentes; sin key retorna enabled=false. Referrer restrictions y disponibilidad real no verificadas. Errores externos ACT03 no prueban defecto del selector privado; ACT04 preserva ese límite. |
| EXT-08 Google Geocoding / dirección → candidatos | [GoogleGeocodeController](../../modules/agenda/controllers/GoogleGeocodeController.php); `MXMED_GOOGLE_GEOCODE_API_KEY`, `GOOGLE_MAPS_API_KEY`, config `google_geocode_api_key` | Backend HTTPS, scope doctor, MX/es, no guarda hasta confirmación del usuario. `not_configured/provider_unreachable/geocode_no_result`; configuración privada real no leída. |
| EXT-09 Maps embed/GPS y WhatsApp deep links | [consultorio_map.php](../../modules/agenda/helpers/consultorio_map.php), [doctor.php](../../profiles/doctor.php), [PublicProfilePanelContent](../../modules/profiles/services/PublicProfilePanelContent.php) | URLs Google/`wa.me` se construyen desde datos permitidos por DTO/plan; abrir una URL externa no es API de mensajería ni tracking de entrega. No llamada de este audit. |
| EXT-10 Geocoder Nominatim histórico | [geocode-proxy.php](../../geocode-proxy.php) | Proxy OSM `/search`, cURL/stream y timeout/502. Referencias legacy no se equiparan al selector Google canónico; reachability/uso actual no acreditados. Sin selección nueva de proveedor. |
| EXT-11 Catálogo CP persistido | [api/catalog/index.php](../../api/catalog/index.php), [multisede](../../assets/js/perfil/consultorio/multisede.js), CP paciente en [app.js](../../assets/js/app.js); `/api/catalog[/index.php]/cp/{cp}` | Tabla `catalog_cp_colonias`, GET por CP y cache sesión cliente; no llamada externa en lookup vigente. Sin tabla `catalog_not_initialized`, sin resultados mensaje específico; instalación física remitir mapa datos. |
| EXT-12 Importación/fallback CP históricos | [sepomex-import.php](../../sepomex-import.php), [sepomex-local.php](../../sepomex-local.php), [sepomex-proxy.php](../../sepomex-proxy.php), `assets/data/sepomex-fallback.json`; `HTTP_PROXY`, `http_proxy`, clave `proxy` del sample | Correos de México para origen/importación; proxy tercero SEPOMEX y AllOrigins, HTTP fallback y TLS verification desactivada en esa fuente legacy. Se registra como deuda histórica, **no** patrón aprobado ni mecanismo probado de la UI actual. No importar/descargar/reconfigurar aquí. |
| EXT-13 PAC/SAT / CFDI | [PAC-DEC01](../billing/PAC_DEC01_PROVIDER_SELECTION_AND_INTEGRATION_STRATEGY.md), [SAT local](../../modules/billing/services/SatCfdiCatalog.php), [SatIssuanceCatalog](../../modules/billing/services/SatIssuanceCatalog.php) | SW primario seleccionado, Finkok secundario viable; sin SwProvider ni calls. Artefactos SAT fechados03/16sept2026, no consulta actual en runtime; vigencia/reglas completas siguen gate. `MXMED_CSD_ENCRYPTION_KEY`, `MXMED_PRIVATE_CSD_ROOT`, `MXMED_PRIVATE_BILLING_ROOT`. |
| EXT-14 S3/objetos y filesystem / Media, firmas, documentos | [puertos Media](../../modules/media/contracts/PrivateMediaStoragePort.php), [bootstrap público](../../modules/media/bootstrap.php), [privado](../../modules/media/private-bootstrap.php), [storage clínico](../../api/_lib/clinical_private_binary_storage.php), [AWS map](AWS_Y_ENTORNOS_MXMED.md); `MXMED_PUBLIC_MEDIA_ROOT`, `MXMED_PRIVATE_MEDIA_ROOT`, `MXMED_PRIVATE_BILLING_ROOT` | Composición PHP inspeccionada usa filesystem local persistente y puertos; IaC S3 no significa adaptador/runtime migrado. Privado/público/versiones/checksum separados. No listar bucket ni leer objetos reales. |
| EXT-15 QR externos legacy versus QR local | [app.js](../../assets/js/app.js) `openNotaCaptureQrModal`, `openConsentIdentityQrModal`; [handoff admin](../../assets/js/signature-handoff-admin.js) | Dos ramas legacy construyen imagen `api.qrserver.com` con `data=normalizedQrValue`; puede comunicar URL bearer al tercero si se ejecutan. No imprimir ni solicitar ese valor. Contratos recientes usan QR local/fragmento y endpoint de dispositivo sin cookies; no extrapolar solución a todos los handlers ni afirmar corrección sin audit de alcance. |
| EXT-16 CDN, fuentes y bibliotecas de navegador | [private-app.php](../../private-app.php), [doctor.php](../../profiles/doctor.php), [layout Agenda](../../api/agenda/ui/_layout/header.php) | jsDelivr Bootstrap5.3.3/icons1.11.3/FullCalendar5.11.5; jQuery3.7.1, unpkg Leaflet1.9.4; Google Fonts/Material Symbols. Son pins encontrados, no recomendación de versiones ni disponibilidad. Failure de CDN puede afectar layout/iconos/calendario; CSP y ingreso de pago deben revisarlos por sus contratos. |
| EXT-17 IA / Asistente y mejoras gráficas | [app.js](../../assets/js/app.js) copy `Asistente IA`; [LogoImprovementService](../../modules/media/services/LogoImprovementService.php); configuración IaC en [AWS](AWS_Y_ENTORNOS_MXMED.md) menciona `AI_API_KEY` | No se halló adaptador productivo OpenAI/Anthropic ni ruta de llamada IA en módulos inspeccionados. Logo mejora mediante GD/background determinista con revisión humana; no es generación IA. Copy/entitlement/campo de secreto no prueba proveedor integrado. Source partial/proposed, remoto desconocido. |
| EXT-18 Referencias clínicas/catalogadas / recurso documental | [reference_registry.php](../../modules/clinical/vitals/reference_registry.php), [catálogo medicamentos](../../assets/data/medication_catalog_seed.json), [catálogos clínicos](../../modules/clinical/catalog/) | Links a referencias médicas no son API de decisión clínica. JSON local de medicamentos/estudios/FDI requiere su propio contrato de procedencia; no se atribuye validación externa en vivo. Sin llamada a proveedor ni lectura de información clínica real. |

La tabla agrupa las integraciones descubiertas; no certifica toda ruta dinámica, todo
transitivo vendor o la disponibilidad actual de los hosts. `composer.json` sólo declara AWS SDK
como dependencia runtime PHP de proveedor; Stripe usa HTTP propio, no se infiere SDK oficial
instalado. Recursos externos de snapshots `index.bak*` son históricos, no nuevas UI aceptadas.

## Referencias Git e historia

Se inspeccionaron refs locales/remotos ya disponibles, sin fetch, checkout ni merge.
Las siete refs siguientes son **ancestros de HEAD**, comprobado con `merge-base`.
Su nombre no prueba despliegue; el contenido actual fue inspeccionado por separado.

| Ref (prefijo `origin/`) | Commit / fecha | Resultado recuperado |
|---|---|---|
| `feature/suscripciones-client-secret-ephemeral-endpoint` | `d2ee6110eda858dc0839a9117e82d8fbb998f006` · 2026-07-13 | Endpoint efímero existente, contrato de elegibilidad/test |
| `feature/suscripciones-stripejs-loader-implementation` | `84a948f395b44dd4881703d9e0f1531700dd25df` · 2026-07-14 | Loader/singleton existente |
| `feature/suscripciones-stripe-payment-element-secure-mount-implementation` | `30359258a675f06830cf4ff6fc1ef6637363dd46` · 2026-07-14 | Mount seguro entregado, no confirmador |
| `feature/suscripciones-stripe-payment-confirmation-readiness` | `bda74bf1545b45bc9dcc36deb09b9576b9f3706a` · 2026-07-14 | Readiness PP242 bloqueada, no implementación |
| `feature/suscripciones-stripe-return-bridge-secure-ingress` | `376adfaf97acde6be3beac2cb2620ad373cfc12d` · 2026-07-14 | Nombre de branch podría sugerir bridge; commit sólo documenta bloqueo |
| `devops/suscripciones-production-ingress-configuration-versioning` | `9bfa21338629d03a9b3780edc39c6e85c8893814` · 2026-07-14 | Ingress productivo no identificado en su fecha |
| `fix/suscripciones-stripe-provider-sanitized-failure-telemetry` | `a1d3203aec5a3929858e888749a25a8cb849b6cc` · 2026-07-13 | Telemetría de etapas existente, sin secretos |

Otras anclas fuente: provider `35208baa` (01jul2026), UI createPI `9cf8081d`
(09jul2026), guard webhook tardío `950240bd` y activación previa a expiry `3194fcae`
(08jul2026); gate Identity en API `97db0129` (16oct2026).

Evidencia histórica contractual del [archivo de decisiones](../PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md):

- **[PP220](../PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md#pp-decisiones-220---cierre-end-to-end-upgrade-stripe-sandbox):** cierre E2E DEV/local Stripe sandbox de upgrade, PI real test, webhook firmado,
  evento procesado, activación explícita una vez y bloqueo de repetición. Historial de
  julio2026; no rerun actual ni certificado de cuenta real.
- **[PP231](../PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md#pp-decisiones-231---cierre-de-panel-real-profesional-activo-post-stripe-sandbox)/[PP232](../PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md#pp-decisiones-232---cierre-e2e-ui-stripe-sandbox-doctor990104):** cierre UI/ruta→checkout→PI test→**confirmación externa sandbox**→webhook→
  activate-after-payment→current Profesional; referencia de fuente `9cf8081` o posterior,
  activación fechada10jul2026 y validación visual posterior sin nuevos POSTs de pago.
  Se conserva prohibición de repetir el pilot cerrado sin regresión nueva demostrada.
- **[PP234](../PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md#pp-decisiones-234---implementacion-contrato-publishable-key-stripe)/[PP236](../PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md#pp-decisiones-236---implementacion-frontend-de-runtime-stripejs-y-configuracion-publica)/[PP241](../PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md#pp-decisiones-241---implementacion-de-montaje-seguro-de-stripe-payment-element):** clave pública, loader y mount entregados. [PP242](../PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md#pp-decisiones-242---readiness-de-confirmacion-stripe-payment-element-y-retorno-seguro)–244 limitan la última
  milla browser/retorno; no contradicen el E2E anterior que confirmó **fuera** del navegador MXMED.
- **FISC02B → PAC-DEC01:** la frase histórica «PAC por seleccionar» fue sucedida por SW
  primario aprobado; código sigue `UNCONFIGURED`, error no actualizado no revoca la decisión.
- Los tres tests históricos ACT02/ACT03 fueron corregidos por ACT04-RECON02; este mapa
  no los conserva como fallos actuales ni ejecutó suites para inventar otra certificación.

## Cobertura, incertidumbres y próximo paso mínimo

Se definieron **10 capacidades SUBS, 6 BILL, 18 integraciones/fronteras y dos journeys**
con UI/entrada, actor, servicio/writer/read model, datos, gates y evidencia. Es cobertura
de inventario fuente, no cobertura funcional. Se trazaron los nueve repositorios Subscriptions,
las rutas principales de pago, los tres entrypoints Billing y contratos actuales de PAC.

No verificado: suscripciones/credenciales/configuración reales, Stripe dashboard/endpoint,
cuenta live o test actual, credenciales PAC/SES/Maps, envío real, browser confirm/return,
resultado actual de suites históricas, renovación pagada completa y todos los posibles
dispatches legacy QR/geocoder fuera de los productores citados. Ver denominadores globales,
instalación física y exclusiones en [cobertura](COBERTURA_Y_PENDIENTES_MXMED.md) y
[datos](DATOS_Y_MIGRACIONES_MXMED.md).

La tarea siguiente autorizada de producto/piloto es **NONE_PENDING_DIRECTOR_REVIEW**.
La decisión del Director precede al diagnóstico/implementación de la autoridad de escritura
Subscriptions bajo Identity, retorno seguro, renovación/recurrencia, PAC o activación de
proveedores. No necesitan un nuevo checkout, un nuevo writer de facturas ni otra selección
PAC antes de comprender los componentes recuperados.

**Mantenimiento:** un cambio autorizado actualiza sólo la capacidad/integración afectada,
su estado y evidencia, más el estado compacto cuando corresponda. Conservar política,
fuente y operación en ejes distintos; no copiar secretos ni payloads reales a este mapa.
