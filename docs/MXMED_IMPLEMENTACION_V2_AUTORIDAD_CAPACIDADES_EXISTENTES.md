# MXMed — Implementación V2 de autoridad backend para capacidades existentes

Estado de entrega: `EXISTING_CAPABILITIES_BACKEND_AUTHORITY_V2_READY_FOR_DIRECTOR_REVIEW`

Actividad candidata: `2/22` · clasificación `UI-1` · `VISUAL_DIFF_REQUIRED_TO_BE_ZERO`

Rama: `feature/mxmed-existing-capabilities-backend-authority-v2`
Known-good oficial: `recovery/mxmed-pre-22-known-good` en 8091, protegido y sin cambios.

## Resultado y límites

La implementación convierte la decisión B1–B7 en una autoridad backend mínima para capacidades ya operativas. La Actividad 1 permanece en `1/22` en el programa oficial; este candidato registra `2/22`, deja 20 actividades pendientes y no inicia la Actividad 3. No hay fast-forward, PR ni integración del programa en esta entrega.

La UI se trató como UI-1: binding de datos únicamente. No se modificaron `index.html`, CSS, imágenes, fuentes, copy, mensajes, orden de cards, layout, shell ni componentes de Agenda, Pacientes, Expediente o Recetas. La comparación contra 8091 es estática y dio diff visual cero; cualquier diferencia visual habría activado `STOP_UI_SCOPE_ESCALATION_REQUIRED`.

## Catálogo deliberadamente mínimo

| Capacidad | Clasificación | Plan mínimo |
|---|---|---|
| `profile_directory_basic` | `operational_existing` | `free` |
| `public_contact` | `operational_existing` | `basic` |
| `gallery` | `operational_existing` | `basic` |
| `agenda_appointments` | `operational_existing` | `standard` |
| `patients` | `operational_existing` | `optimum` |
| `clinical_record` | `operational_existing` | `optimum` |
| `prescriptions` | `operational_existing` | `optimum` |

El catálogo excluye `assistant_ai`, `call_center` y `addons` como funciones futuras; excluye `professional_additional_tools` por evidencia operacional insuficiente; y mantiene `commercial_card_benefits` como información comercial, nunca como autoridad técnica. `future_rules_granted=0`.

## Contrato y autoridad backend

`ExistingCapabilityDecision` conserva internamente `capability_id`, `available`, `reason_code`, `source`, `plan_code` y `operational_state`. `ExistingCapabilityAuthorityService` resuelve una capacidad o un conjunto usando rangos de plan canónicos (`free` → `professional`), estado de suscripción y estado operacional. Deniega por defecto con estos códigos internos estables: `allowed`, `plan_not_entitled`, `subscription_inactive`, `capability_not_operational`, `context_missing` y `unknown_capability`.

El servicio no depende de precios, copy, UI ni escrituras. La denegación es fail-closed: contexto ausente, plan no reconocido, suscripción inactiva, capacidad desconocida o estado no operacional nunca conceden acceso.

## API y read-model

`CurrentSubscriptionReadModelService` añade de forma aditiva `feature_access` al read-model actual. Cada entrada pública contiene sólo la decisión necesaria (`capability_id`, `available`, `source`, `plan_code`, `operational_state`); `reason_code`, catálogo técnico, dependencias, lifecycle, cuotas, conteos y funciones futuras permanecen fuera de la respuesta pública. Los campos existentes y la versión del read-model se conservan, por lo que el endpoint sigue siendo compatible con consumidores anteriores.

No se agregaron tablas, migraciones, SQL, escrituras de producción, Stripe, checkout, PaymentIntent, webhooks, activación, AWS ni cambios de esquema.

## Frontend no autoritativo

`assets/js/app.js` normaliza el binding recibido y sólo puede filtrar beneficios visibles ya existentes en un contexto real activo. La simulación QA local (`qa_plan_simulated`) queda aislada y conserva el catálogo visual congelado; nunca se usa como autoridad. El binding no agrega capacidades, no crea cards y no cambia copy, clases, estados visibles ni layout. El contrato está cubierto por `modules/subscriptions/tests/subscription-feature-access-binding.test.js`.

## Verificación visual y responsive

Se comparó 8091 (`http://127.0.0.1:8091/`) contra 8140 (`http://127.0.0.1:8140/`) en 1440×900, 1024×768 y 390×844. Se capturaron 60 pares (`free`, `basic`, `standard`, `optimum`, `professional` × `annual`, `monthly`, `Mi plan y pagos`, `upgrade`). Resultado: 60/60 PNG iguales por SHA-256, DOM normalizado 60/60, texto 60/60, CSS visual sin cambios y 0 pares con diferencias. La evidencia completa, hashes y capturas están en `/tmp/mxmed-activity02-existing-capabilities-backend-authority-v2/`.

## Pruebas ejecutadas

- `php -l` en contrato, servicios y endpoint.
- `php modules/subscriptions/tests/ExistingCapabilityAuthorityServiceTest.php` — PASS.
- `php modules/subscriptions/tests/CurrentSubscriptionFeatureAccessReadModelTest.php` — PASS.
- `node --check assets/js/app.js` — PASS.
- `node modules/subscriptions/tests/subscription-feature-access-binding.test.js` — PASS.
- Comparación API candidato/oficial: campos visuales preservados; `feature_access` sólo como adición candidata y sin `reason_code`.
- QA browser/responsive: PASS; no se ejecutaron pagos, escrituras de producción ni cambios externos.

## Estado de revisión

La entrega está lista para revisión técnica y visual del director. No integrar todavía. La Actividad 3 permanece `NO INICIADA`.
