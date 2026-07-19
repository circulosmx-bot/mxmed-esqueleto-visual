# Registro de contratos visuales — MXMed

**Concepto:** `VISUAL_SURFACE_CONTRACT`

**Gobierno:** [Protocolo UI/UX](./MXMED_PROTOCOLO_CONTROL_CAMBIOS_UI_UX_Y_ENTREGA_SEGURA.md)

**Decisión:** `PP-280`

## 1. Esquema progresivo

Cada superficie tocada debe registrar:

- `surfaceId`, nombre y URL/ruta;
- branch/commit aprobado;
- composición y componentes;
- copy y colores/tokens;
- jerarquía y estados;
- interacciones;
- responsive y accesibilidad;
- elementos prohibidos sin revisión;
- capturas baseline, fecha y aprobación directoral.

Este registro crece por superficie; no obliga a redocumentar el sistema completo en una sola actividad.

## 2. SUBSCRIPTIONS_PLANS_AND_BILLING

| Campo | Contrato conocido aprobado |
|---|---|
| `surfaceId` | `SUBSCRIPTIONS_PLANS_AND_BILLING` |
| Nombre | Planes, suscripción y pagos |
| URL/ruta | Panel de Suscripciones desde `/index.html` |
| Branch/commit | `recovery/mxmed-pre-22-known-good` / `e4f7d515cba4ae47fcdbd44cd55ce610466b982a` |
| Composición | Panel de planes; “Mi plan y pagos” separado; resumen de upgrade; shell de Pago seguro |
| Componentes | Cuatro tarjetas, selector anual/mensual, precio, ahorro, equivalente diario, CTA contextual, resumen y calculadora proporcional |
| Copy | Copy del baseline known-good; cualquier cambio se clasifica al menos `UI-2` y puede escalar a `UI-3` |
| Colores/tokens | Identidad visual diferenciada por plan conforme al baseline |
| Jerarquía | Planes y comparación primero; información de vigencia/pagos en sección separada; subheader no saturado |
| Estados | Gratuito, Básico, Estándar, Óptimo y Profesional; plan actual; upgrades; planes inferiores sólo al renovar; pago protegido |
| Interacciones | Cambio anual/mensual; CTA según contexto; upgrade con cálculo proporcional; navegación a Pago seguro; retroceso seguro |
| Responsive | Desktop, tablet y móvil sin overflow horizontal |
| Accesibilidad | Controles etiquetados, orden de lectura y foco preservados; auditoría exhaustiva pendiente cuando se toque la superficie |
| Prohibido sin revisión | Añadir datos técnicos permanentes, saturar subheader, cambiar jerarquía/cards/copy/colores, montar prototipos en 8091 o crear campos propios de tarjeta |
| Capturas baseline | Evidencia certificada del baseline y cutover known-good bajo `/tmp/mxmed-pre-22-known-good-promotion-local-cutover-01/screenshots/` |
| Fecha | 2026-07-19 |
| Aprobación | Director: baseline máximo de recuperación y cutover visual known-good aprobados |

Este contrato registra el estado existente; no introduce una especificación visual nueva.

## 3. Superficies pendientes

| `surfaceId` | Superficie | Estado | Condición para completar |
|---|---|---|---|
| `PUBLIC_PROFILE` | Perfil público | `PENDING_VISUAL_SURFACE_CONTRACT` | Primera actividad que la modifique |
| `PRIVATE_PANEL` | Panel privado | `PENDING_VISUAL_SURFACE_CONTRACT` | Primera actividad que la modifique |
| `AGENDA` | Agenda | `PENDING_VISUAL_SURFACE_CONTRACT` | Primera actividad que la modifique |
| `PATIENTS` | Pacientes | `PENDING_VISUAL_SURFACE_CONTRACT` | Primera actividad que la modifique |
| `CLINICAL_RECORD` | Expediente | `PENDING_VISUAL_SURFACE_CONTRACT` | Primera actividad que la modifique |
| `PRESCRIPTIONS` | Recetas | `PENDING_VISUAL_SURFACE_CONTRACT` | Primera actividad que la modifique |
| `ACCESS` | Acceso/autenticación | `PENDING_VISUAL_SURFACE_CONTRACT` | Primera actividad que la modifique |
| `NAVIGATION` | Navegación | `PENDING_VISUAL_SURFACE_CONTRACT` | Primera actividad que la modifique |
| `NOTIFICATIONS` | Notificaciones | `PENDING_VISUAL_SURFACE_CONTRACT` | Primera actividad que la modifique |
| `OPERATOR_DASHBOARD` | Dashboard de operadores | `PENDING_UI_3` | Arquitectura de información, wireframe, prototipo y aprobación expresa |

## 4. Control de actualización

Una modificación sólo se registra como aprobada cuando existe evidencia del nivel UI correspondiente. Los prototipos permanecen identificados como tales y nunca reemplazan el contrato aprobado antes de la frase directoral de aprobación.
