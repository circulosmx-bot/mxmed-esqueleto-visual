# Auditoría V2 de planes, capacidades, ownership y lifecycle

Estado candidato del segundo intento: `Actividad 1/22 CONCLUIDA — PENDIENTE DE INTEGRACIÓN FAST-FORWARD`; avance `1/22`, 21 pendientes y Actividad 2 `NO INICIADA`. Clasificación: `UI-0 — NO_UI_IMPACT`. La auditoría documenta hallazgos; la aprobación directoral posterior A1–A5 se formaliza en [Decisiones V2 de catálogo, precios, modalidades y autoridad](./MXMED_DECISIONES_V2_CATALOGO_PRECIOS_MODALIDADES.md). Ninguno de los dos documentos modifica producto.

## 1. Propósito

Inventariar el comportamiento verificable del known-good `e4f7d515cba4ae47fcdbd44cd55ce610466b982a` y separar implementación, presentación, placeholders y decisiones pendientes. La rama V2 añade sólo documentación.

## 2. Baseline

La rama de actividad nace de `3b5d9b2241de83a0d7b8e932b2990e8c3782a216`, commit de gobierno sobre `program/mxmed-product-refinement-22-v2`; su producto protegido es `recovery/mxmed-pre-22-known-good` en `e4f7d515...`. La URL oficial `http://127.0.0.1:8091/` se comprobó HTTP 200 y no se reinició ni alteró.

## 3. Metodología

Se revisaron estáticamente código, SQL versionado, contratos, pruebas, rutas, read-models y UI. Se reutilizó la evidencia runtime certificada del cutover known-good sin ejecutar writes, Stripe, migraciones ni servidores. Las siete refs archivadas autorizadas se consultaron sólo con comandos Git de lectura. La autoridad aplicada fue: baseline, tests/contratos, evidencia runtime, documentos V2 e historia.

## 4. Fuentes

- Catálogo: `modules/profiles/db/2026_06_19_seed_subscription_plans_catalog.sql`.
- Precios: `modules/profiles/db/2026_06_22_seed_subscription_plan_prices_dev.sql` y `SubscriptionPlanPriceResolverService.php`.
- Suscripción: `modules/subscriptions/**`, migraciones de `modules/profiles/db/**` y `api/subscriptions/index.php`.
- Perfil/capacidades: `PublicProfilePlanCapabilities.php`, `PublicProfileController.php` y repositorios de perfiles.
- Presentación: `assets/js/app.js` y contrato visual `SUBSCRIPTIONS_PLANS_AND_BILLING`.
- Runtime protegido: `/tmp/mxmed-pre-22-known-good-promotion-local-cutover-01/`.
- Comparación histórica: refs `archive/mxmed-product-refinement-*`, prototipos A/B y Stripe known-good.

## 5. Inventario de planes

| Código | Nombre | Orden/rank | Modalidad de catálogo | Precio anual mostrado | Mensual | Estado verificable |
|---|---|---:|---|---:|---:|---|
| `free` | Gratuito | 10 / 0 | lifetime, 0 días | $0 | no aplica | fallback real; no card comercial |
| `basic` | Básico | 20 / 1 | annual, 365 días | $6,990 MXN | derivado anual/12×1.25 | visible; precio DEV, no aprobado |
| `standard` | Estándar | 30 / 2 | annual, 365 días | $9,990 MXN | derivado | visible; precio DEV, no aprobado |
| `optimum` | Óptimo | 40 / 3 | annual, 365 días | $12,990 MXN | derivado | visible; precio DEV, no aprobado |
| `professional` | Profesional | 50 / 4 | annual, 365 días | $21,990 MXN | derivado | visible; precio DEV, no aprobado |

Los aliases inglés/español convergen en esos cinco códigos. Los cuatro pagos aparecen como cards y el gratuito opera como default/fallback. Como **hallazgo del baseline**, el seed marca planes activos, pero no demuestra despliegue de la migración, y la matriz de precio está duplicada en JavaScript y SQL DEV. Como **decisión posterior**, los cinco códigos y nombres quedan aprobados, `free` conserva su papel sin quinta card y los cuatro importes quedan aprobados provisionalmente para desarrollo, sujetos a revisión pre-lanzamiento. El backend impide contratación gratuita y bloquea recurrencia mensual (`monthly_recurring_not_ready`/`stripe_billing_not_ready`). Por ello, aprobación contractual y “visible” no equivalen a “comprable en producción”.

## 6. Capacidades

Existen dos taxonomías no equivalentes. La UI resume `Perfil`, `Agenda`, `Expediente`, `Recetas` y `Asistente IA` por escalones. El perfil público usa 18 flags (`show_photo`, contacto, teléfono, WhatsApp, inbox, mapa/GPS, agenda pública, promociones, reseñas/respuestas, claim, galería, seguros y datos de consulta). `free` permite reseñas y claim; `basic` habilita perfil/contacto/mapa; `standard`, `optimum` y `professional` reciben hoy la misma expansión en ese servicio. El contexto puede volver falsos los flags por falta de fuente pública, comercial o de claim.

No hay un entitlement authority transversal que conecte de extremo a extremo las cinco promesas visuales con todos los módulos. Perfil público es parcial; expediente, recetas e IA son presentación de plan sin enforcement localizado. La policy del primer intento no pertenece al baseline y no se reactiva.

## 7. Approval

El perfil admite estados `draft`, `pending_review`, `active`, `hidden`, `suspended` y `removed`; esto demuestra un estado de publicación, no un workflow completo de aprobación con actor, transición, caso y auditoría. Contact points incluyen verificación, pero no constituyen aprobación comercial. No se localizó una consola operativa de aprobación en el alcance auditado.

## 8. Ownership

Las escrituras de suscripción exigen sesión de doctor, coincidencia de entidad y bloquean operadores; existe asociación implícita sesión→doctor/perfil. El controlador público entrega `is_claimed=false`, `claim_source_ready=false`, `ownership_status=null` y `claim_url=null`: claim/ownership es un contrato placeholder condicionado, no un flujo. Invitación, transferencia, disputa y revocación de ownership no son verificables como end-to-end. Cualquier intervención requiere diseño posterior UI-3 y trazabilidad.

## 9. Lifecycle

El baseline versiona catálogo, precios, profile subscriptions, aceptaciones, checkout intents, payment routes, payment intents, payment events e idempotency. Los servicios producen preview, checkout pendiente, intento de pago, evento procesado y activación; aplican transacciones, locks por entidad/operación, claves idempotentes, firma de webhook y control paid-before-expiry. El read-model representa `free_default`, `active`, `expired`, `cancelled`, gracia y pending.

Upgrade superior con misma periodicidad y diferencia prorrateada tiene soporte. Renovación tiene preview/ruta y extensión estimada. Downgrade se expresa visualmente “al renovar”, pero no se localizó transición persistida canónica `future_plan`; cancelación, recuperación/fallo y auto-renew no forman un lifecycle comercial completo comprobable. No se importan los doce estados históricos.

## 10. Suscripciones/Stripe

La cadena estática es UI → resumen/preview → payment route → checkout → PaymentIntent → webhook firmado → payment event → activate-after-payment → current read-model. El endpoint expone config pública sin secretos, client secret controlado y shell de Payment Element. Mock/fixtures quedan acotados a local/dev; writes de producción están bloqueados sin autorización real. La evidencia previa certificó superficies de free, cuatro planes, mensual, upgrade, pagos y postactivación; esta auditoría no reejecutó Stripe ni escribió entidades.

## 11. Backend ↔ API ↔ UI

| Requisito | Paridad | Autoridad/gap |
|---|---|---|
| catálogo/códigos | parcial | SQL, PHP y JS coinciden en códigos; despliegue no comprobado |
| precios | `duplicated_authority` | SQL DEV y matriz JS; precio provisional aprobado contractualmente, aún sin autoridad única implementada |
| plan actual/vigencia | completa en read-model | fallback free y suscripción vigente llegan a UI |
| anual/mensual | parcial | anual respaldado; mensual visual/preview, cobro recurrente bloqueado |
| upgrade/prorrateo | parcial | preview y checkout técnico; operación externa no reejecutada |
| downgrade | placeholder | copy “al renovar”; no transición futura persistida localizada |
| ownership/approval | backend parcial | campos/estados aislados, sin workflow ni UI operativa |
| billing/Payment Element | protegido/no reejecutado | shell y contratos presentes; requiere configuración externa |
| activation | backend implementado | guards, evento y read-model; no ejecución en esta auditoría |
| publicación de perfil | parcial | status existente; aprobación/ownership no cerrados |
| operador | ausente | writes bloqueados; futura superficie UI-3 |

## 12. Duplicidades

Precios y aliases/rank viven tanto en frontend como backend. Las capacidades visuales de cards y las capacidades del perfil público son matrices distintas. Etiquetas/status/periodos aparecen en PHP y JavaScript. Esto eleva riesgo de divergencia; esta auditoría no elige una fuente futura.

## 13. Gaps

La autoridad comercial de catálogo, precio y modalidad ya está resuelta **contractualmente** por A1–A5: backend/catálogo persistido será autoridad, con API/read-model como transporte y frontend como presentación. Sigue pendiente implementar esa autoridad única y retirar duplicidades. También faltan entitlement transversal probado, lifecycle de downgrade/future-plan, ownership/claim real, approval auditable, cancelación/recuperación integral, renovación recurrente productiva y operación autorizada. Tampoco es verificable que todas las migraciones versionadas estén aplicadas en cada ambiente.

## 14. Riesgos

- R3: exponer cobro o presentar como final un precio que sólo está aprobado provisionalmente para desarrollo.
- R3: habilitar ownership/transferencia sin verificación, caso y auditoría.
- R2: prometer capacidades en cards sin enforcement equivalente.
- R2: autoridades duplicadas de precio/capacidad.
- R2: confundir shell/preview certificado con disponibilidad productiva.
- R1: copy de downgrade/renovación sin transición canónica completa.

## 15. Comparación histórica

El primer intento documentó auditoría, reconciliación, decisiones y una implementación posterior. Sólo se confirman los hallazgos que reaparecen en `e4f7d515`: cinco códigos, cuatro cards, fallback free, duplicidad y cadena de pagos. `MXMED_PLAN_CAPABILITY_POLICY_V1`, resolver/lifecycle/migración de Activity 2 y PP-273–PP-279 son `incompatible_with_known_good` o `historical_candidate_for_revalidation`; no se copiaron ni aprobaron. Los prototipos A/B son `not_applicable` para autoridad actual. La evidencia Stripe histórica sólo sirve como soporte runtime del baseline cuando coincide con el known-good.

## 16. Agenda de decisiones

La auditoría formuló los bloques: (A) catálogo/precio/modalidades; (B) capacidad y autoridad de entitlement; (C) approval/ownership/claim; (D) lifecycle y estados persistidos; (E) downgrade/retención; (F) si existirán add-ons; (G) actores/operadores y auditoría; (H) traducción UI bajo contrato visual.

El bloque A queda **resuelto contractualmente** por la aprobación directoral A1–A5: catálogo estable, `free` sin quinta card, precios provisionales con revisión pre-lanzamiento, mensualidad `anual ÷ 12 × 1.25` con redondeo vigente y primer pago de tres mensualidades, recurrencia Stripe diferida, y backend/catálogo persistido como autoridad. Esto es una decisión, no evidencia de implementación. Permanecen pendientes B, C, D, E, F, G y H; orden sugerido B→C→D/E→G→H, con F sólo si existe caso comercial.

## 17. Impacto UI futuro

Esta actividad es UI-0. Ajustes de copy/estado local podrían ser UI-1; reconciliar cards, comparador o downgrade sería UI-2 con prototipo; ownership, approval y dashboard operador son UI-3. El contrato `SUBSCRIPTIONS_PLANS_AND_BILLING` protege cuatro cards, identidad, selector, precio/ahorro/equivalente, CTA contextual, plan actual, upgrades, inferiores al renovar, calculadora, “Mi plan y pagos”, shell seguro y responsive. Cualquier propuesta histórica que lo rompa se clasifica `INCOMPATIBLE_WITH_APPROVED_VISUAL_SURFACE_CONTRACT`.

## 18. Dashboard

No existe ni se implementa un dashboard. Se detectan candidatos archivados para aprobar/publicar, verificar/reclamar, transferir/disputar/revocar ownership, suspender/reactivar, resolver pago/activación y auditar overrides. Todos requieren caso, actor, motivo, before/after, autorización y registro inmutable; nivel UI-3.

## 19. Límites

Auditoría estática y documental: no prueba despliegue de SQL, configuración productiva, cobro real, webhook externo ni autorización operativa. No contiene secretos, datos personales o clínicos. No modifica código, UI, tests, SQL, AWS ni 8091. La historia no es autoridad y las recomendaciones no son decisiones.

## 20. Recomendación para Actividad 2

No iniciar ni reintroducir Activity 2. La revisión directoral del bloque A ya se formalizó en [Decisiones V2 de catálogo, precios, modalidades y autoridad](./MXMED_DECISIONES_V2_CATALOGO_PRECIOS_MODALIDADES.md), por lo que la Actividad 1 queda candidata concluida, pendiente de verificación e integración fast-forward explícita y separada a la rama de programa. El siguiente contrato deberá abordar un bloque pendiente acotado —comenzando por capacidades y autoridad de entitlement— sin asumir implementación de A1–A5.
