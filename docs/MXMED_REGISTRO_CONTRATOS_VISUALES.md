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

### 2.1 Freeze directoral de fichas de planes

La referencia visual obligatoria permanece en `recovery/mxmed-pre-22-known-good` @ `e4f7d515cba4ae47fcdbd44cd55ce610466b982a`. Beneficios, servicios, copy, orden, cantidad, jerarquía, colores, iconos, densidad, cuatro cards, CTAs, subheader, selector anual/mensual, cálculo proporcional, “Mi plan y pagos”, shell de Pago seguro y responsive quedan preservados exactamente como en ese known-good.

Cualquier cambio futuro de esos elementos se clasifica `UI-3 — UI_VISUAL_CHANGE_REQUIRES_APPROVAL`. Esta regla directoral específica y posterior endurece a UI-3 cualquier umbral previo aplicable a las fichas sin cambiar su especificación visual existente. Los cambios backend no implican ni autorizan cambios visuales. Un data binding UI-1 sólo será admisible con `VISUAL_DIFF_REQUIRED_TO_BE_ZERO` contra el commit de referencia; de lo contrario aplica `STOP_UI_SCOPE_ESCALATION_REQUIRED`.

Queda prohibido imprimir el registro técnico completo en las cards, listar capabilities internas, cuotas técnicas, estados internos, dependencias o reason codes; agregar “próximamente; no operativa” o funciones futuras indiscriminadamente; mostrar conteos de capacidades; saturar el subheader; o convertir la matriz backend en copy comercial. La imagen del incidente aportada como referencia negativa no se versiona: este registro conserva únicamente la definición semántica del anti-patrón.

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

## 5. Capítulo transversal móvil final

| Campo | Contrato vigente |
|---|---|
| `surfaceId` | `MOBILE_RESPONSIVE_FINALIZATION` |
| Estado | `MOBILE_RESPONSIVE_FINALIZATION_PENDING` |
| Clasificación actual | `UI-0 — NO_UI_IMPACT` |
| Clasificación de ejecución | `UI-3 — UI_VISUAL_CHANGE_REQUIRES_APPROVAL` |
| Alcance | Navegación, menús, shell, panel privado, perfil, Suscripciones, Agenda, Pacientes, Expediente, Recetas, formularios, tablas, cards, modales, fecha/hora, archivos, teclado, scroll, orientación, safe areas, touch accessibility y estados vacío/error/loading |
| Viewports iniciales | Teléfonos 320/360/375/390/412/430; tablets/transición 768/820/1024 |
| Navegadores | Safari iOS, Chrome Android y desktop emulado; físicos cuando estén disponibles |
| Smoke intermedio | `INTERIM_MOBILE_SMOKE_ONLY`; no equivale a `FINAL_MOBILE_APPROVED` |
| Gate final | Inventario, auditoría, priorización, wireframes/prototipo reversible, 8140+, comparación 8091, pruebas táctiles, teclado/focus/accesibilidad, revisión y aprobación del director, checkpoint y rollback |
| Prohibido sin revisión | Layout, navegación, cards, tipografía, copy, formularios, breakpoints o cambios silenciosos en 8091 |

Este registro formaliza un gate transversal y no una especificación visual nueva. La definición completa vive en [MXMED_CAPITULO_FINAL_ADAPTACION_MOVIL_RESPONSIVE.md](./MXMED_CAPITULO_FINAL_ADAPTACION_MOVIL_RESPONSIVE.md). La frase de aprobación requerida es: **“Apruebo visualmente la adaptación móvil final.”**
### ACCESS — UI-3 aprobada integrada en HTTP

| Campo | Contrato vigente |
|---|---|
| surfaceId | ACCESS |
| Referencia visual | checkpoint/mxmed-identity-access-ui3-approved-v2 |
| Rutas | /acceso, /crear-cuenta, /verificar-correo, /recuperar-acceso, /restablecer-acceso |
| Estado | APPROVED_BY_DIRECTOR |
| Preservación | Copy, layout, tokens, estados iniciales, DOM visible, estilos, geometría y píxel iguales al prototipo aprobado |
| Cambios técnicos permitidos | Form action/method, CSRF invisible, cabeceras, IDs/ARIA dinámicos y JS de estados no sensibles |
| Seguridad | Same-origin, CSRF, POST para escrituras, cookie __Host-mxmed_session, no-store, nosniff, CSP, sin CORS wildcard |
| Evidencia | /tmp/mxmed-activity04-gate4d-http-ui-integration-v2/ |
| Prohibido | Alterar copy/layout, exponer tokens/IDs/planes/capacidades, conectar AWS/8091, añadir claim/MFA/passwordless/social |

### Cierre de aprobación visual y funcional

“Apruebo visual y funcionalmente la candidata Gate 4D en 8140 como base para
integrar y cerrar la Actividad 4.”

La candidata conserva visual diff 0, HTTPS local, cookie segura, CSRF,
anti-enumeración, guards fail-closed, aislamiento de base/store y rollback
probado. El smoke móvil es INTERIM_MOBILE_SMOKE_ONLY y la aprobación móvil final
permanece pendiente.

## ASE-INT01 R2 — Equipo interno, sólo consulta

[Contrato y procedencia](identity/ASE-INT01-R2-internal-team-readonly.md). R2 d871747 aprobado; la respuesta real “apruebo”, al preguntar por COM/ADM, aprueba visualmente las extensiones hasta312ee388. Historia R1/R2/COM/ADM conservada por enlaces inmutables. Composición/directorio-detalle, cinco grupos tri-state y secciones de incorporación/comercial/personal preservados con tres CSS idénticos. Diferencias explícitas para revisión: navegación real/badge, referencia/paginación, retrato neutral/datos ausentes, scope limitado y controles de escritura deshabilitados; sin claim pixel0 ni nueva aprobación inventada. QA8 combinaciones Chromium/WebKit, Safari físico NOT_RUN, móvil interino. No activación ni aprobación de políticas pendientes.

## ASE-INT02B — conexión de interacciones aprobadas

[Contrato/evidencia](identity/ASE-INT02B-team-permission-ui.md), superficie Equipo interno en `/internal/media-review/index.php?section=equipo`, baseline aprobado `312ee388`. UI-2 expresamente autorizada en solicitud ASE-INT02B: checkboxes/grupos, revertir, confirmación, secuencia, guardas y resultados honestos. Tres CSS aprobados idénticos; conserva composición R2/COM/ADM y modal. Diferencias de copy/estados explican disponibilidad, parcialidad e incertidumbre solicitadas; no rediseño ni pixel0. Comparación de geometría/tokens y capturas Chromium/WebKit en 1440×900, 1366×768, 820×1180 y 390×844; Safari físico NOT_RUN, móvil interino. No se inventa aprobación de aceptación final ni autorización operacional.

## ASE-INT03A — botones lifecycle aprobados

[Ficha UI-2 y evidencia](identity/ASE-INT03A-advisor-lifecycle.md): solicitud ASE-INT03A autoriza conectar SUSPENDER ASESOR/REACTIVAR ASESOR del header y modal existentes en baseline `312ee388`. Sin cambios CSS/template/header/directorio/matriz. Copy autorizado de consecuencias, conservación, conflicto e incertidumbre; guardia de borrador. Ocho combinaciones Chromium/WebKit y 36 capturas; comparación de geometría/tokens, sin claim pixel0 ni Safari físico. Aprobaciones históricas R2/COM/ADM preservadas; no activación.

## ASE-INT03B-PREP01 — ficha de seguridad pendiente

[Ficha UI-2](identity/ASE-INT03B-PREP01-master-privileged-authorization.md#ficha-ui-2-y-decisiones-del-director) propone confirmación/reauth/TOTP y estados de fallo/expiración/incertidumbre en Equipo. Entrega actual UI-0 documental; no implementación ni aprobación nueva. Preserva baseline visual `312ee388`, botones de designación y separación uso/gestión/delegación; controles Master siguen deshabilitados. Deuda `ASE-INT03B-PRIVILEGED-UI`, decisión del Director antes de implementar conducta nueva según protocolo.

## ASE-INT03B-IMP01 — concepto UI-2 aprobado, conexión pendiente

El Director aprobó selección → consecuencias → confirmación → contraseña → MFA → resultado/reconciliación. [IMP01](identity/ASE-INT03B-IMP01-master-security-foundation.md) implementa sólo backend; IMP02 requiere revisión separada para conectar controles. Layout, header, navegación y baseline visual312ee388 conservados; acciones Master continúan deshabilitadas. La deuda ASE-INT03B-PRIVILEGED-UI sigue abierta para conexión, no para aprobación conceptual.

## ASE-INT03B-IMP02 — conexión UI aislada

[IMP02](identity/ASE-INT03B-IMP02-master-ui-integration.md) ejecuta la conexión UI-2 expresamente autorizada: modal existente por etapas, controles de designación/gestión/delegación individual y mensajes de seguridad. CSS/header/navegación/layout R2/COM/ADM conservados; no UI-3. Appointment visible deshabilitado con motivo. Deuda de conexión IMP01 se sucede sólo en esta feature aislada; no activación ni revisión física Safari.

## ASE-ADM02-POL01 — política UI-0, sin modificación visual

[Contrato vigente](identity/ASE-ADM02-POL01-staff-admission-policy.md): siete principios de admisión/identidad aprobados por respuesta explícita “apruebo”; no una nueva aprobación visual ni implementación. Conserva baseline312ee388 y aprobaciones R2/COM/ADM; propone semántica de estados/legacy y reutiliza + AGREGAR INTEGRANTE sin habilitarlo. Retrato/datos principales sólo para directorio autorizado, ID scans fuera; fuente personal/documental aún desconectada. Legal, retención, catálogo extranjero, autoridad exacta y futuras fases pendientes; sin QA visual nueva, Safari físico ni activación.

## ASE-ADM02-IMP01 — UI-0

[Backend aislado](identity/ASE-ADM02-IMP01-staff-case-foundation.md): sin layout, copy, CSS, imágenes o interacción nuevos. + AGREGAR INTEGRANTE, invitación/documentos/review/admisión/activación no se habilitan. Baseline R2/COM/ADM conservado; no QA visual nueva ni autorización operacional.

## ASE-ADVISOR-WORKSPACE01-R2 — composición autorizada, aceptación pendiente

[Contrato R2](identity/ASE-ADVISOR-WORKSPACE01-R2.md), baseline OPER09 `5ca9bfa24b081afdc652d7f65443f3b80979f34e`: la solicitud revisada del Director autoriza encabezado staff, búsqueda/pendientes, encabezado médico y una fila de pestañas superiores con panel amplio. Reemplaza expresamente el menú lateral global solicitado antes de la interrupción; no se entregan dos sistemas de navegación equivalentes. Las nueve etiquetas son dominios propuestos sujetos a consumidor/autoridad, no nueve permisos o formularios obligatorios. Resumen e Imágenes son el alcance declarado; controles contextuales y overflow accesible, sin Save global engañoso.

La referencia externa sólo orienta estructura. No autoriza copiar estilos/código/personas, overrides de planes, opiniones o workflows de variantes. El Director considera resuelto el modal OPER09 para esta etapa salvo regresión y pide preservar el panel Director; no se reabre su rediseño. QA visual nueva: ventana Chromium nativa 1366×783 dentro de ventana/área disponible de 870 px; Chromium/WebKit a 1440×900, 1366×768 y 390×844, Más/acciones y footer Director alcanzables. Evidencia enlazada en el contrato. `DIRECTOR_VISUAL_ACCEPTANCE=PENDING` para este workspace, sin afirmar pixel0, Safari físico, aceptación móvil definitiva ni activación productiva.


## ASE-ADVISOR-WORKSPACE02 — integración contextual autorizada

[Contrato](identity/ASE-ADVISOR-WORKSPACE02.md), baseline R2 `0dbccc946476f7f5e8e4de000e7f186913069a80`: solicitud del Director autoriza selector/búsqueda/inbox compactos al elegir contexto y pestañas Solicitudes cerca de Resumen e Historial al final de las disponibles. Preserva encabezado staff, logout, médico, navegación horizontal, Resumen/Imágenes y el modal aceptado. Acciones explícitas por tipo, motivo/confirmación/contraseña/MFA sólo en la acción y visor documental protegido dentro del contexto; ninguna pestaña concede permisos. Nuevo sin physician_id muestra aplicación, sin médico dummy ni iframe/otra app.

Continuación del solicitante usa su ruta Identity existente y sus estilos, con respuesta de información y aceptación personal; no cambia el intake público por defecto. [Navegador](evidence/ase-advisor-workspace02/browser-result.json): Chromium/WebKit 1440×900, 1366×768 y 390×844 PASS. [Complemento](evidence/ase-advisor-workspace02/ui-supplement.json): ventana nativa 1366×783, outerHeight=availableHeight=870, sin overflow horizontal ni errores JS. [Solicitantes](evidence/ase-advisor-workspace02/ownership-browser.json): respuesta enviada/propiedad consumida ocultan realmente sus formularios no aplicables. `DIRECTOR_VISUAL_ACCEPTANCE=PENDING`; Safari físico NOT_RUN. No afirmar pixel0 ni aceptación móvil definitiva. Las aprobaciones históricas y siete dominios no conectados de R2 permanecen con su alcance exacto.

La corrección dirigida de WORKSPACE02 alinea el indicador del encabezado contextual con Resumen: Media y solicitudes pendientes del mismo médico se suman y las solicitudes se deduplican por request_id, sin pendientes residuales en perfiles completados. [Conteo contextual](evidence/ase-advisor-workspace02/count-smoke.json): 3 PASS separados (4 = 3 Media + 1 claim; lectura duplicada sin doble conteo; nuevo completado = 0), sin mutaciones de negocio ni errores JS.

## ASE-ADVISOR-WORKSPACE03 — agrupación, once pestañas y contadores

[Contrato y ficha acotada](identity/ASE-ADVISOR-WORKSPACE03.md). Cambio UI-2/UI-3 expresamente solicitado por el Director: una tarjeta por médico/aplicación, fila completa desplazable, menú accesible de secciones, badges por fuente y estados restringidos. Se conserva header, selección, paneles y modal WORKSPACE02; 8091 protegido. Ensayo Chromium/WebKit PASS en 1440×900, 1366×768 y 390×844, teclado, overflow y viewport nativo; 53 checks principales y 10 complementarios PASS. DIRECTOR_VISUAL_ACCEPTANCE=PENDING; Safari físico NOT_RUN y adaptación móvil final no acreditada.
