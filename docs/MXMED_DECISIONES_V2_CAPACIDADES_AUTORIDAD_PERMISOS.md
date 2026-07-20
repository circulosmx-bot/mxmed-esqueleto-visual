# Decisiones V2 de capacidades, autoridad y permisos

Contrato: `MXMED_CAPABILITIES_PERMISSIONS_AUTHORITY_DIRECTOR_APPROVAL_V2`.

## 1. Estado y alcance

El director aprueba las decisiones B1–B7 como contrato para una implementación posterior. Esta formalización es `UI-0 — NO_UI_IMPACT`: sólo documenta reglas, protege las fichas comerciales y delimita preliminarmente la futura Actividad 2. No modifica código productivo, UI, SQL, Stripe, AWS, catálogo, precios, permisos efectivos ni comportamiento.

`CURRENT_ACTIVITY_UI_LEVEL: UI-0`.

La Actividad 1 permanece `CONCLUIDA — 1/22`. La Actividad 2 permanece `NO INICIADA`. El known-good `recovery/mxmed-pre-22-known-good` y la URL oficial `http://127.0.0.1:8091/` no se modifican.

## 2. Dirección visual obligatoria

Los beneficios y servicios visibles en las fichas de planes quedan congelados exactamente como aparecen en:

- Branch: `recovery/mxmed-pre-22-known-good`.
- Commit: `e4f7d515cba4ae47fcdbd44cd55ce610466b982a`.
- Superficie: `SUBSCRIPTIONS_PLANS_AND_BILLING`.

Sin instrucción expresa del director no se modificarán beneficios visibles, copy, orden, cantidad de beneficios, jerarquía, colores, iconos, densidad, tarjetas, CTAs, subheader, selector anual/mensual, cálculo proporcional, “Mi plan y pagos”, shell de Pago seguro ni responsive.

Cualquier cambio futuro de esos elementos será `UI-3 — UI_VISUAL_CHANGE_REQUIRES_APPROVAL`. Un cambio backend no implica ni autoriza un cambio visual.

`BENEFITS_COPY_ORDER: FROZEN`.

## 3. Anti-patrón técnico prohibido

Está prohibido convertir la matriz backend en copy comercial. Esto incluye:

- imprimir el registro técnico completo en las cards;
- listar capabilities internas, dependencias o reason codes;
- mostrar cuotas técnicas o estados internos;
- agregar textos como “próximamente; no operativa”;
- agregar funciones futuras indiscriminadamente;
- mostrar conteos de capacidades;
- saturar el subheader.

La captura aportada por el director es únicamente una referencia negativa del incidente anterior. La imagen no se agrega ni se versiona en el repositorio; el anti-patrón queda documentado sólo de forma semántica.

## 4. B1 — Autoridad backend

El backend será la única autoridad de capacidades y permisos. Resolverá según plan, suscripción, estado, approval, ownership y límites. La API/read-model transportará la resolución y el frontend la reflejará.

El frontend no autorizará mediante rank, matrices JavaScript, botones ocultos ni copy. El impacto futuro será UI-0 para backend invisible, UI-1 para data binding visualmente idéntico, UI-2 para comportamiento visible y UI-3 para visual o copy.

## 5. B2 — Separación técnica y comercial

El registro técnico de capacidades y la presentación comercial de las fichas son contratos separados. El registro técnico puede contener IDs, dependencias, limits, denials, estados y auditoría. Las fichas mantendrán exclusivamente la presentación known-good aprobada.

Una capability técnica no genera automáticamente un beneficio visible, una modificación de copy ni una nueva promesa comercial.

## 6. B3 — Trazabilidad sin alteración automática de UI

Cada beneficio visible deberá relacionarse con una capacidad backend, una característica informativa real o una implementación futura planificada. Esa trazabilidad no convierte la UI en autoridad.

Una brecha técnica no autoriza retirar el beneficio, cambiar copy, agregar “no operativo”, cambiar la card ni modificar 8091. Cuando una promesa visible aún no tenga enforcement completo, se registrará la deuda técnica, se materializará backend incrementalmente y se mantendrá la ficha conocida. Sólo se escalará al director si resultara indispensable modificar UI.

## 7. B4 — Progresión funcional objetivo

La matriz técnica objetivo es:

| Plan | Progresión funcional objetivo |
|---|---|
| Gratuito | Perfil/directorio básico aprobado, sin contacto público restringido, sin Agenda ni módulo clínico |
| Básico | Contacto público autorizado y galería |
| Estándar | Agenda y gestión de citas |
| Óptimo | Pacientes, Expediente y Recetas |
| Profesional | Óptimo más funciones profesionales que estén operativas y aprobadas |

Esta matriz orienta la implementación backend; no reemplaza el contenido actual de las cards, no autoriza reescribir beneficios, no crea una card Gratuito y no activa IA, Call Center ni add-ons.

Si la matriz técnica y la presentación actual requieren reconciliación, se deberá detener la actividad con `STOP_UI_SCOPE_ESCALATION_REQUIRED`. La implementación técnica no modificará UI.

## 8. B5 — Denegación segura

La resolución será fail-closed: un contexto desconocido no autoriza. Los reason codes serán estables y el motivo interno estará separado del mensaje de usuario. El frontend no inventará reglas ni mensajes.

Los mensajes visibles futuros se clasificarán UI-2 o UI-3 según el cambio. Esta actividad no modifica mensajes.

## 9. B6 — Funciones futuras

Una función futura podrá tener uno de estos estados: `candidate`, `documented`, `in_development`, `operational` o `retired`. Sólo `operational` concede capacidad efectiva.

Las funciones futuras no se agregan automáticamente a cards o subheader, no generan CTA, no aumentan conteos comerciales y no se anuncian sin autorización del director. Las fichas known-good permanecen intactas.

## 10. B7 — Implementación incremental

La futura Actividad 2 deberá:

1. modelar capacidades ya existentes;
2. aplicar autoridad backend;
3. evitar cambios visuales;
4. conservar Suscripciones y Stripe;
5. probar plan por plan;
6. comprobar diferencia visual cero;
7. detenerse antes de capacidades futuras.

Quedan fuera de su alcance preliminar cambios de cards o copy, IA, Call Center, add-ons, cuotas nuevas, ownership completo, lifecycle ampliado, dashboard, Stripe y precios.

## 11. Alcance preliminar de Actividad 2

- Nombre previsto: `PRODUCT-IMPLEMENTATION/MXMed-Existing-Capabilities-Backend-Authority-V2-01`.
- Clasificación inicial: `UI-1 — UI_DATA_BINDING_ONLY`.
- Condición: `VISUAL_DIFF_REQUIRED_TO_BE_ZERO`.
- Objetivo: crear autoridad backend mínima para capacidades existentes, reemplazar decisiones frontend duplicadas y conservar exactamente el render known-good.
- Incluido: capacidades actuales, resolución backend, read-model, sustitución controlada de duplicidades, pruebas por plan y comprobación visual.
- Excluido: toda función o superficie enumerada fuera del alcance de B7.

Si fuese necesario modificar card, beneficio, copy, botón, mensaje, layout, subheader, navegación o responsive, aplica el emergency stop `STOP_UI_SCOPE_ESCALATION_REQUIRED`.

Este alcance es preliminar y no inicia ni autoriza la Actividad 2.

## 12. Gates

- Gate contractual: B1–B7 aprobadas; no equivalen a implementación.
- Gate de autoridad: backend resuelve y API/read-model transporta; frontend no autoriza.
- Gate UI-1: `VISUAL_DIFF_REQUIRED_TO_BE_ZERO` contra `e4f7d515...`.
- Gate UI-2: revisión de cualquier comportamiento o mensaje visible.
- Gate UI-3: aprobación directoral para cualquier cambio de beneficio, copy, orden, card o composición.
- Gate de deuda: documentar gaps sin alterar automáticamente la UI.
- Gate de funciones futuras: sólo `operational` concede capacidad y no implica anuncio comercial.
- Emergency stop: `STOP_UI_SCOPE_ESCALATION_REQUIRED` ante cualquier necesidad visual fuera del data binding idéntico.

## 13. Backend ↔ API ↔ UI

| Capa | Responsabilidad | Prohibición |
|---|---|---|
| Backend | Autoridad de capacidades y permisos; resolución fail-closed con contexto, límites y reason codes | Delegar autorización al cliente |
| API/read-model | Transportar resultado, estado y representación necesaria | Inventar capacidades o reglas comerciales |
| Frontend | Reflejar el resultado con el render known-good | Autorizar por rank, matrices JS, botones ocultos o copy |
| Fichas comerciales | Comunicar beneficios aprobados del known-good | Exponer el registro técnico o cambiar automáticamente por gaps backend |

## 14. Relación con Actividad 1 y siguiente paso

Este documento resuelve contractualmente el bloque B identificado en la [Auditoría V2 de planes, capacidades, ownership y lifecycle](./MXMED_AUDITORIA_V2_PLANES_CAPACIDADES_OWNERSHIP_LIFECYCLE.md) y refuerza el [Registro de contratos visuales](./MXMED_REGISTRO_CONTRATOS_VISUALES.md). Una decisión fija la política futura; no demuestra que la autoridad backend ya esté implementada.

La Actividad 1 sigue concluida en `1/22`. Permanecen pendientes ownership/claim, approval, lifecycle, downgrade/retención, add-ons si existe caso comercial, actores/operadores y su eventual traducción UI. El siguiente paso es verificar e integrar esta formalización; no iniciar todavía la Actividad 2.
