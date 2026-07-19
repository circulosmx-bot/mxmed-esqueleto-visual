# Decisiones V2 de catálogo, precios, modalidades y autoridad

Contrato: `MXMED_CATALOG_PRICES_MODALITIES_AUTHORITY_DIRECTOR_APPROVAL_V2`.

## 1. Estado y alcance

El director aprobó las decisiones A1–A5 como contrato para el desarrollo posterior. Su estado conjunto es `DIRECTOR_APPROVED_CONTRACT_ONLY`. Esta formalización es `UI-0 — NO_UI_IMPACT`: no implementa las decisiones, no modifica código productivo, schema, SQL, API, Stripe, AWS, catálogo persistido, precios visibles, cards, copy, checkout ni comportamiento.

En el segundo intento del programa, la Actividad 1 de 22 queda `CONCLUIDA — PENDIENTE DE INTEGRACIÓN FAST-FORWARD`, con avance candidato `1/22`, 21 actividades pendientes y Actividad 2 `NO INICIADA`. La URL protegida `http://127.0.0.1:8091/` y el known-good `recovery/mxmed-pre-22-known-good` permanecen intactos.

## 2. A1 — Catálogo

Se aprueba este catálogo contractual:

| Código técnico estable | Nombre visible en español |
|---|---|
| `free` | Gratuito |
| `basic` | Básico |
| `standard` | Estándar |
| `optimum` | Óptimo |
| `professional` | Profesional |

No se crearán códigos alternativos. Esta decisión no cambia todavía código, schema, catálogo persistido ni UI. Estado: `DIRECTOR_APPROVED_CONTRACT_ONLY`.

## 3. A2 — Plan gratuito

`free` permanece como estado o modo del perfil y se presenta como estado actual cuando corresponda. No se agrega como quinta tarjeta comercial: Suscripciones conserva las cuatro tarjetas pagadas actuales y su composición conocida.

Preservar la superficie actual puede resolverse sin cambio visual. Una quinta card sería UI-3 y está expresamente prohibida por esta aprobación. Estado: `DIRECTOR_APPROVED_PRESERVE_KNOWN_GOOD_UI`.

## 4. A3 — Precios anuales provisionales

| Plan | Precio anual provisional |
|---|---:|
| Básico | $6,990 MXN |
| Estándar | $9,990 MXN |
| Óptimo | $12,990 MXN |
| Profesional | $21,990 MXN |

Los importes están aprobados provisionalmente para el desarrollo, no son precios finales ni inmutables y pueden cambiar por decisión comercial. Deben someterse a revisión formal antes del lanzamiento. Esta actividad no los implementa ni modifica su presentación en 8091.

Un cambio contractual futuro de backend se clasificará UI-0 o UI-1 según su implementación. Todo cambio de precio visible será UI-3 y requerirá prototipo o revisión visual antes de alcanzar 8091. Estado: `DIRECTOR_APPROVED_PROVISIONAL_PRE_LAUNCH_REVIEW_REQUIRED`.

## 5. A4 — Modalidad mensual

La regla comercial aprobada es:

`mensualidad = precio anual ÷ 12 × 1.25`

Se aplicará el redondeo comercial conforme a la regla vigente del proyecto. El primer pago equivaldrá a tres mensualidades y después se efectuará el cobro mensual. El precio visible por mes no será sustituido por el total del anticipo; el resumen deberá explicar claramente el primer pago de tres meses.

El contrato separa cuatro capas:

1. Regla comercial: fórmula, redondeo y primer pago.
2. Presentación UI: precio mensual y explicación del primer pago.
3. Implementación recurrente real: lifecycle y cobros posteriores.
4. Activación y cobros Stripe: integración externa autorizada y operable.

La aprobación actual no autoriza crear `PaymentIntent`, habilitar recurrencia incompleta, cambiar cards o copy, modificar checkout, ejecutar Stripe ni alterar 8091. La recurrencia y el cobro mensual real quedan diferidos hasta cerrar el gate explícito de Stripe. Una implementación backend invisible será UI-0/UI-1; un cambio de comportamiento del flujo será UI-2; cambios de copy o composición serán UI-3. Estado: `DIRECTOR_APPROVED_IMPLEMENTATION_DEFERRED_WITH_EXPLICIT_GATE`.

## 6. A5 — Fuente única de autoridad

La autoridad contractual será el **backend y catálogo persistido**. La **API/read-model** transportará y representará los datos. El **frontend** se limitará a presentación e interacción y no será autoridad contractual.

El frontend productivo no podrá gobernar precios, ranks, elegibilidad, prorrateo, periodos, descuentos, mensualidades, anticipo ni capacidades. Un fallback QA sólo será válido si está aislado explícitamente en DEV/QA, no opera en producción, está probado y no reemplaza el contrato backend.

Las duplicidades hoy localizadas en frontend deberán retirarse en una implementación futura controlada. La migración de autoridad podrá ser UI-1 únicamente con diferencia visual cero; cualquier diferencia visual o conductual escalará a UI-2 o UI-3. Estado: `DIRECTOR_APPROVED_BACKEND_AUTHORITY`.

## 7. Impacto UI futuro

`CURRENT_ACTIVITY_UI_LEVEL: UI-0`.

`FUTURE_UI_IMPACTS:`

| Decisión | Impacto futuro | Restricción |
|---|---|---|
| A1 | UI-0/UI-1 | Catálogo estable; sin cambio visual no autorizado |
| A2 | Preservar UI conocida | Quinta card prohibida; cualquier alternativa sería UI-3 |
| A3 | UI-3 al cambiar precios visibles | Prototipo o revisión visual antes de 8091 |
| A4 | UI-2 para comportamiento; UI-3 para copy/composición | Recurrencia y Stripe sujetos a gate separado |
| A5 | UI-1 sólo con visual diff cero | Toda diferencia escala a UI-2/UI-3 |

El contrato visual protegido `SUBSCRIPTIONS_PLANS_AND_BILLING` conserva cuatro tarjetas, colores, selector anual/mensual, cálculo proporcional, “Mi plan y pagos”, subheader, shell de Pago seguro y responsive.

## 8. Gates

- Gate contractual: A1–A5 aprobadas; no equivale a autorización de implementación.
- Gate backend: diseñar una implementación acotada que mantenga catálogo persistido como autoridad.
- Gate UI-1: demostrar diferencia visual cero antes de integrar un cambio invisible.
- Gate UI-2: revisar y aprobar cambios de comportamiento del flujo.
- Gate UI-3: prototipo y revisión visual obligatorios para precios, copy o composición.
- Gate Stripe: cerrar recurrencia, lifecycle, activación, configuración y cobros antes de habilitar mensual real.
- Gate pre-lanzamiento: revisar y volver a aprobar formalmente los precios provisionales.
- Gate 8091: no modificar la URL oficial protegida sin autorización posterior expresa.

## 9. Backend ↔ API ↔ UI

| Capa | Responsabilidad aprobada | No debe hacer |
|---|---|---|
| Backend / catálogo persistido | Ser autoridad contractual de catálogo, precios, modalidad y reglas | Delegar reglas comerciales al cliente |
| API / read-model | Transportar y representar el contrato backend | Inventar o reemplazar valores contractuales |
| UI | Presentar datos e interacción conforme al contrato visual | Ser autoridad de precios, ranks, elegibilidad, periodos, descuentos, prorrateo, mensualidades, anticipo o capacidades |
| Fallback DEV/QA | Dar soporte aislado, explícito y probado | Operar en producción o sustituir el backend |

## 10. Riesgos

- Presentar precios provisionales como finales o inmutables.
- Habilitar cobro mensual sin recurrencia Stripe y lifecycle completos.
- Confundir el primer pago de tres mensualidades con el precio mensual visible.
- Mantener autoridades duplicadas en backend y frontend.
- Introducir una quinta card o alterar el contrato visual protegido.
- Implementar A1–A5 sin el gate UI y técnico correspondiente.

## 11. Revisión pre-lanzamiento

Antes del lanzamiento se deberá revisar formalmente cada precio, moneda, vigencia, redondeo, fórmula mensual, primer pago, recurrencia, copy, cumplimiento del contrato visual y configuración Stripe. El resultado deberá quedar registrado como una nueva decisión comercial; esta aprobación provisional no satisface ese gate.

## 12. Implementaciones futuras no autorizadas

No están autorizados en esta actividad cambios a catálogo, precios, cards, API, frontend, schema, SQL, checkout, `PaymentIntent`, Stripe, AWS, cobros, activación, recurrencia, copy o 8091. Tampoco se autorizan una quinta tarjeta comercial, fallbacks productivos ni reglas comerciales codificadas con autoridad en frontend.

## 13. Relación con Actividad 1

Este documento formaliza el bloque A derivado de los hallazgos de la [Auditoría V2 de planes, capacidades, ownership y lifecycle](./MXMED_AUDITORIA_V2_PLANES_CAPACIDADES_OWNERSHIP_LIFECYCLE.md). Un hallazgo describe el baseline verificable; una decisión directoral fija una regla contractual futura. La aprobación A1–A5 resuelve contractualmente catálogo, precios, modalidad y autoridad, pero no corrige aún las duplicidades o gaps de implementación señalados por la auditoría.

El segundo intento queda candidato `1/22`, Actividad 1 concluida y pendiente de integración fast-forward, con 21 actividades pendientes. El primer intento histórico permanece `PAUSED_AND_ARCHIVED` en `2/22` y no se modifica ni reactiva. Actividad 2 no ha iniciado.

## 14. Siguiente bloque de decisiones

Después de verificar e integrar por fast-forward la Actividad 1, el director podrá abordar en contratos separados: capacidades y autoridad de entitlement; approval, ownership y claim; lifecycle y estados persistidos; downgrade y retención; add-ons si existe caso comercial; actores, operadores y auditoría; y sólo después su traducción UI. Esta actividad no inicia ninguno de esos bloques ni la Actividad 2.
