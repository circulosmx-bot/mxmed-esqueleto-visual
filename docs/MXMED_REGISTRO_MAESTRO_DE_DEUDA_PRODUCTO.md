# Registro Maestro de Deuda de Producto — México Médico

**Contrato:** `MXMED_PRODUCT_DEBT_REGISTRY_V1`
**Versión:** `1.2.0`
**Fecha de incorporación:** 2026-07-17
**Última revisión:** 2026-07-18
**Estado:** canónico, versionado y mantenible
**Owner documental:** `product-operations-owner`

## 1. Portada y versión

Este documento es la fuente canónica de deuda conocida de producto de México Médico. Registra evidencia, incertidumbre, prioridades, dependencias y criterios de cierre sin sustituir contratos funcionales, decisiones PP, mapas técnicos ni reportes de QA.

Referencias rectoras:

- [Contrato maestro y PP-Decisiones](./PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md)
- [Mapa total del sistema](./MAPA_TOTAL_SISTEMA_MXMED.md)
- [Plan Maestro](./PLAN_MAESTRO_MXMED.md)
- [Reglas UI](./ui/REGLAS_UI_MXMED.md)
- [Glosario UI](./ui/GLOSARIO_UI_MXMED.md)
- [Requisitos del plano de control de operadores](./MXMED_REQUISITOS_PLANO_CONTROL_OPERADORES_ROLES_GOBIERNO.md)

## 2. Propósito

Concentrar en un solo registro la deuda conocida de producto, UX, diseño, planes, flujos, identidad, agenda, pacientes, clínica, recetas, notificaciones, pagos, reseñas, administración, datos, permisos, privacidad, accesibilidad, documentación, runtime y preparación para despliegue.

El registro no declara que una función exista, falte, esté conectada, persista o esté rota sin evidencia. Cuando la inspección no permite concluir, usa `REQUIRES_AUDIT`.

## 3. Alcance

Incluye deuda y referencias en:

- producto, experiencia, diseño, responsive y accesibilidad;
- planes, capacidades, ownership, grace, downgrade y upsell;
- identidad, autenticación, agenda, pacientes y clínica;
- recetas, buzón, triggers, preferencias, suscripciones y pagos;
- reseñas, administración, IA, privacidad, datos y documentación;
- runtime y gates previos a despliegue/tráfico.

No incluye implementación ni auditorías funcionales detalladas. No ejecuta aplicación, PHP, SQL, base de datos, navegador, HTTP, Stripe, npm, CDK o AWS.

## 4. Estado del proyecto

- Ciclo AWS offline: `24/24 concluido`.
- Foundation AWS: `offline-release-candidate-v1`.
- Despliegue real AWS: `NO iniciado`.
- Tráfico público: `NO-GO`.
- Datos reales/costo real: no iniciados.
- Agenda: v1 funcional consolidada, con deuda explícita de convergencia/hardening.
- Stripe backend: PaymentIntent, webhook, activación y flujo E2E DEV/local cerrados como referencia.
- Registro de deuda: 105 entradas en versión 1.2.
- Ciclo principal de producto: `1/22`; Actividad 1 concluida y Actividad 2 bloqueada hasta aprobación directoral.
- Microfase 25: no existe.

## 5. Reglas de gobernanza

1. Este archivo es la fuente canónica de deuda de producto.
2. Las decisiones PP y documentos de dominio siguen siendo fuente de evidencia; no se copian completos aquí.
3. Un ID nunca se reutiliza, aun si una entrada se cierra.
4. Toda alta incluye los campos obligatorios, evidencia o `REQUIRES_AUDIT`, owner opaco y criterio de aceptación.
5. Una coincidencia textual aislada no confirma deuda.
6. Una decisión `CLOSED_REFERENCE_ONLY` sólo se reabre con evidencia nueva y una decisión explícita.
7. Cerrar deuda exige implementación o decisión, QA proporcional, evidencia y referencia de commit/PP.
8. No se registran nombres, correos, teléfonos, account IDs, secretos, payloads ni datos clínicos.
9. El Markdown es la fuente canónica; cada amendment produce delta y reconciliación JSON propios sin reescribir snapshots históricos de `/tmp`.
10. Los grupos PG-01 a PG-11 y su orden son oficiales desde el inventario global y su amendment del plano de control.
11. Ninguna actividad de auditoría cambia código por implicación.
12. El contador principal aprobado es `1/22`; una actividad auxiliar no lo incrementa.

## 6. Taxonomía de IDs

| Prefijo | Dominio |
|---|---|
| `CAP` | Planes y capacidades |
| `OWN` | Propiedad y reclamo |
| `AUTH` | Identidad y acceso |
| `UX` | Experiencia y accesibilidad |
| `PUB` | Perfil público |
| `REV` | Comentarios y reseñas |
| `AGD` | Agenda |
| `PAT` | Pacientes |
| `CLN` | Clínico |
| `RX` | Recetas |
| `NOT` | Notificaciones |
| `SUB` | Suscripciones y pagos |
| `DATA` | Datos e interconexiones |
| `ADM` | Administración |
| `AI` | Inteligencia artificial |
| `PRIV` | Privacidad |
| `DOC` | Documentación |
| `RUNTIME` | Runtime y despliegue |
| `TECH` | Refactors técnicos |
| `QA` | Calidad |

Formato: `PREFIJO-NNN`. Los IDs retirados permanecen reservados.

## 7. Clasificaciones, estados y prioridades

Clasificaciones válidas:

| Clasificación | Uso |
|---|---|
| `CONFIRMED_DEBT` | Evidencia suficiente de carencia, contradicción o implementación incompleta |
| `PARTIAL_IMPLEMENTATION` | Existe shell, UI, endpoint o flujo parcial |
| `DECISION_PENDING` | Falta una decisión funcional/producto aunque pueda existir implementación |
| `REQUIRES_AUDIT` | Evidencia insuficiente para concluir |
| `RUNTIME_GATE` | Bloqueo conocido previo a despliegue o tráfico |
| `DEFERRED_REFACTOR` | Solución válida con refactor técnico diferido |
| `CLOSED_REFERENCE_ONLY` | Cierre protegido que no se reabre sin evidencia nueva |

Estados válidos:

- `OPEN`: deuda, parcial, decisión o auditoría pendiente.
- `GATED`: runtime/deployment bloqueado.
- `DEFERRED`: refactor válido deliberadamente diferido.
- `PROTECTED`: referencia cerrada, no trabajo abierto.
- `RESOLVED`: sólo después de cumplir el proceso de cierre; ninguna entrada v1 se marca así por inferencia.

Prioridades:

- `P0`: seguridad, privacidad, integridad, acceso, lanzamiento, pagos o clínica críticos.
- `P1`: flujo principal, beta, plan/capacidad, identidad, agenda, suscripciones o notificaciones esenciales.
- `P2`: experiencia, claridad, accesibilidad, responsive u optimización operativa.
- `P3`: mejora o refactor no bloqueante.

## 8. DECISIONES CERRADAS QUE NO DEBEN REABRIRSE SIN EVIDENCIA NUEVA

| Referencia | Estado protegido |
|---|---|
| AWS 24/24 | Cerrado offline en PP272; no equivale a deploy |
| Despliegue AWS | No iniciado; `NO-GO` |
| Tráfico público | `NO-GO` |
| Stripe backend | Existe; no crear backend paralelo |
| PaymentIntent | Existe y fue validado en flujo Stripe sandbox |
| Webhook real | Existe; ruta canónica `/api/subscriptions/index.php/webhooks/stripe` |
| Activación post-pago | Existe; preservar separación webhook/activación |
| Payment route bridge | Solución válida con WARN; `SUB-004` es refactor diferido |
| Cost-Aware | PP263–PP264 cerrados |
| Compute/Edge/Operations/Backup | Profile-aware y cerrados offline |
| Región DR | No seleccionada; no inventarla |
| Microfase 25 | No existe |

## 9. Resumen ejecutivo de deuda

| Métrica | Total |
|---|---:|
| Entradas | 105 |
| CLOSED_REFERENCE_ONLY | 4 |
| CONFIRMED_DEBT | 14 |
| DECISION_PENDING | 32 |
| DEFERRED_REFACTOR | 6 |
| PARTIAL_IMPLEMENTATION | 10 |
| REQUIRES_AUDIT | 26 |
| RUNTIME_GATE | 13 |
| P0 | 44 |
| P1 | 44 |
| P2 | 12 |
| P3 | 5 |
| estado DEFERRED | 6 |
| estado GATED | 13 |
| estado OPEN | 82 |
| estado PROTECTED | 4 |

Lectura ejecutiva:

- Los P0 se concentran en identidad, autorización, clínica, privacidad, pagos y runtime.
- `REQUIRES_AUDIT` preserva veracidad donde el repositorio sólo permite afirmar presencia o ausencia de evidencia.
- Los cierres Stripe/AWS evitan re-trabajo.
- El inventario global PP274 está cerrado; la siguiente actividad es PG-01 read-only, Actividad 1 de 22, aún no iniciada.

## 10. Registro maestro

| ID | Título | Clasificación | Prioridad | Estado | Grupo |
|---|---|---|---|---|---|
| `CAP-001` | Matriz canónica única de planes y capacidades | `DECISION_PENDING` | `P1` | `OPEN` | `G1` |
| `CAP-002` | Cuotas y límites por plan | `REQUIRES_AUDIT` | `P1` | `OPEN` | `G1` |
| `CAP-003` | Estados homogéneos de capacidades | `DECISION_PENDING` | `P1` | `OPEN` | `G1` |
| `CAP-004` | Ownership separado del plan gratuito | `DECISION_PENDING` | `P1` | `OPEN` | `G1` |
| `CAP-005` | Máquina de estados comercial completa | `DECISION_PENDING` | `P1` | `OPEN` | `G1` |
| `CAP-006` | Downgrade, reactivación y conservación de datos | `DECISION_PENDING` | `P0` | `OPEN` | `G1` |
| `CAP-007` | Patrón visual único de bloqueo y upsell | `REQUIRES_AUDIT` | `P2` | `OPEN` | `G1` |
| `CAP-008` | Enforcement equivalente frontend y backend | `REQUIRES_AUDIT` | `P0` | `OPEN` | `G1` |
| `CAP-009` | Contrato gratuito no administrado vs administrado | `DECISION_PENDING` | `P1` | `OPEN` | `G1` |
| `CAP-010` | Perfiles AWS Cost-Aware y profile-aware protegidos | `CLOSED_REFERENCE_ONLY` | `P3` | `PROTECTED` | `G7` |
| `OWN-001` | Flujo real de reclamo de perfil | `PARTIAL_IMPLEMENTATION` | `P1` | `OPEN` | `G2` |
| `OWN-002` | Estados y revisión manual del reclamo | `DECISION_PENDING` | `P1` | `OPEN` | `G2` |
| `OWN-003` | Disputa, duplicado, revocación y transferencia | `DECISION_PENDING` | `P0` | `OPEN` | `G2` |
| `AUTH-001` | Login productivo del médico | `CONFIRMED_DEBT` | `P0` | `OPEN` | `G2` |
| `AUTH-002` | Recuperación de cuenta y tokens de un solo uso | `CONFIRMED_DEBT` | `P0` | `OPEN` | `G2` |
| `AUTH-003` | Ciclo de sesión y cambios sensibles | `REQUIRES_AUDIT` | `P0` | `OPEN` | `G2` |
| `AUTH-004` | MFA por riesgo y cuentas operativas | `DECISION_PENDING` | `P0` | `OPEN` | `G2` |
| `UX-001` | Inventario global de pantallas y funciones | `REQUIRES_AUDIT` | `P1` | `OPEN` | `G8` |
| `UX-002` | Consistencia de navegación y jerarquía visual | `REQUIRES_AUDIT` | `P2` | `OPEN` | `G8` |
| `UX-003` | Cobertura de estados vacío, carga, error y éxito | `REQUIRES_AUDIT` | `P1` | `OPEN` | `G8` |
| `UX-004` | Responsive y experiencia móvil | `REQUIRES_AUDIT` | `P2` | `OPEN` | `G8` |
| `UX-005` | Accesibilidad teclado, lector, foco y contraste | `REQUIRES_AUDIT` | `P1` | `OPEN` | `G8` |
| `UX-006` | Lenguaje, copy y errores accionables | `REQUIRES_AUDIT` | `P2` | `OPEN` | `G8` |
| `UX-007` | Ayuda contextual y centro de ayuda | `REQUIRES_AUDIT` | `P2` | `OPEN` | `G8` |
| `PUB-001` | CTA de reclamo y sugerencia de corrección | `PARTIAL_IMPLEMENTATION` | `P1` | `OPEN` | `G6` |
| `PUB-002` | Teléfono y WhatsApp por plan/ownership | `DECISION_PENDING` | `P1` | `OPEN` | `G6` |
| `PUB-003` | Maps sólo como enlace externo | `PARTIAL_IMPLEMENTATION` | `P2` | `OPEN` | `G6` |
| `PUB-004` | Rutas SEO y canonical productivo | `PARTIAL_IMPLEMENTATION` | `P1` | `OPEN` | `G6` |
| `PUB-005` | Galería, imagen y límites públicos | `REQUIRES_AUDIT` | `P2` | `OPEN` | `G6` |
| `REV-001` | Persistencia de comentarios y reseñas | `PARTIAL_IMPLEMENTATION` | `P1` | `OPEN` | `G6` |
| `REV-002` | Moderación, respuesta, denuncia y spam | `DECISION_PENDING` | `P1` | `OPEN` | `G6` |
| `REV-003` | Privacidad y reputación en reseñas | `DECISION_PENDING` | `P0` | `OPEN` | `G6` |
| `AGD-001` | Convergencia shell Agenda y frontend legacy | `DEFERRED_REFACTOR` | `P2` | `DEFERRED` | `G3` |
| `AGD-002` | Retiro progresivo de localStorage legacy en bloqueos | `DEFERRED_REFACTOR` | `P2` | `DEFERRED` | `G3` |
| `AGD-003` | Agenda sin expediente: frontera de datos permitidos | `DECISION_PENDING` | `P0` | `OPEN` | `G3` |
| `AGD-004` | Creación controlada de expediente desde Agenda | `DECISION_PENDING` | `P0` | `OPEN` | `G3` |
| `AGD-005` | Concurrencia, reintentos y estados borde de Agenda | `REQUIRES_AUDIT` | `P1` | `OPEN` | `G3` |
| `AGD-006` | Política de riesgo por inasistencia y cancelación | `DECISION_PENDING` | `P0` | `OPEN` | `PG-03` |
| `PAT-001` | Detección y conciliación de contactos duplicados | `REQUIRES_AUDIT` | `P1` | `OPEN` | `G3` |
| `PAT-002` | Identidad canónica y contratos divergentes | `CONFIRMED_DEBT` | `P0` | `OPEN` | `G3` |
| `PAT-003` | Separación contacto de paciente y expediente clínico | `DECISION_PENDING` | `P0` | `OPEN` | `G3` |
| `CLN-001` | Persistencia clínica heterogénea | `CONFIRMED_DEBT` | `P0` | `OPEN` | `G3` |
| `CLN-002` | Consentimiento informado clínico | `PARTIAL_IMPLEMENTATION` | `P0` | `OPEN` | `G3` |
| `CLN-003` | Adjuntos clínicos del expediente | `PARTIAL_IMPLEMENTATION` | `P0` | `OPEN` | `G3` |
| `CLN-004` | Permisos de colaboradores por entidad clínica | `REQUIRES_AUDIT` | `P0` | `OPEN` | `G7` |
| `CLN-005` | Portabilidad, exportación, eliminación y retención clínica | `DECISION_PENDING` | `P0` | `OPEN` | `G7` |
| `CLN-006` | Datos clínicos fuera de notificaciones y logs | `CONFIRMED_DEBT` | `P0` | `OPEN` | `G7` |
| `CLN-007` | No delegabilidad e integridad documental clínica | `DECISION_PENDING` | `P0` | `OPEN` | `PG-04` |
| `RX-001` | Recetas: persistencia y flujo canónico incompletos | `PARTIAL_IMPLEMENTATION` | `P0` | `OPEN` | `G3` |
| `RX-002` | PDF, descarga y regeneración de recetas | `REQUIRES_AUDIT` | `P1` | `OPEN` | `G3` |
| `NOT-001` | Buzón interno transaccional | `PARTIAL_IMPLEMENTATION` | `P1` | `OPEN` | `G4` |
| `NOT-002` | Modelo de estados y prioridades del buzón | `DECISION_PENDING` | `P1` | `OPEN` | `G4` |
| `NOT-003` | Catálogo mínimo de triggers | `DECISION_PENDING` | `P1` | `OPEN` | `G4` |
| `NOT-004` | Preferencias obligatorias y configurables | `DECISION_PENDING` | `P1` | `OPEN` | `G4` |
| `NOT-005` | Entregas, reintentos, fallos y auditoría | `DECISION_PENDING` | `P1` | `OPEN` | `G4` |
| `SUB-001` | Arquitectura Stripe backend cerrada | `CLOSED_REFERENCE_ONLY` | `P3` | `PROTECTED` | `G5` |
| `SUB-002` | Ciclo comercial completo posterior al pago | `DECISION_PENDING` | `P1` | `OPEN` | `G5` |
| `SUB-003` | Mensajes y datos al bloquear capacidades | `DECISION_PENDING` | `P1` | `OPEN` | `G5` |
| `SUB-004` | Duplicación parcial payment_route→checkout | `DEFERRED_REFACTOR` | `P3` | `DEFERRED` | `G5` |
| `SUB-005` | Conciliación y override de pagos manuales | `CONFIRMED_DEBT` | `P0` | `OPEN` | `PG-06` |
| `DATA-001` | Inventario pantalla→API→dato→evento | `REQUIRES_AUDIT` | `P1` | `OPEN` | `G7` |
| `DATA-002` | Scope, ownership, plan, rol y autorización por flujo | `REQUIRES_AUDIT` | `P0` | `OPEN` | `G7` |
| `DATA-003` | Idempotencia, errores, retención y downgrade | `REQUIRES_AUDIT` | `P1` | `OPEN` | `G7` |
| `DATA-004` | Zona horaria, concurrencia, offline y borradores | `REQUIRES_AUDIT` | `P1` | `OPEN` | `G7` |
| `ADM-001` | Backoffice de soporte, moderación y disputas | `PARTIAL_IMPLEMENTATION` | `P1` | `OPEN` | `PG-10` |
| `ADM-002` | Roles internos y break-glass | `DECISION_PENDING` | `P0` | `OPEN` | `G8` |
| `ADM-003` | Lifecycle de operadores internos y access reviews | `CONFIRMED_DEBT` | `P0` | `OPEN` | `PG-02` |
| `ADM-004` | Case management y sesiones asistidas de soporte | `CONFIRMED_DEBT` | `P0` | `OPEN` | `PG-10` |
| `ADM-005` | Doble aprobación y separación de funciones | `CONFIRMED_DEBT` | `P0` | `OPEN` | `PG-08` |
| `ADM-006` | Auditoría administrativa, masking y acceso extraordinario | `CONFIRMED_DEBT` | `P0` | `OPEN` | `PG-08` |
| `ADM-007` | Colas y notificaciones internas de operadores | `CONFIRMED_DEBT` | `P1` | `OPEN` | `PG-05` |
| `ADM-008` | UX y accesibilidad de la consola operativa | `DECISION_PENDING` | `P1` | `OPEN` | `PG-09` |
| `ADM-009` | Máquina de publicación y moderación | `CONFIRMED_DEBT` | `P1` | `OPEN` | `PG-07` |
| `ADM-010` | Scopes de mercadotecnia y citas globales | `DECISION_PENDING` | `P1` | `OPEN` | `PG-10` |
| `AI-001` | IA Profesional: plan, cuota y presupuesto | `DECISION_PENDING` | `P1` | `OPEN` | `G8` |
| `AI-002` | IA como borrador con confirmación explícita | `DECISION_PENDING` | `P0` | `OPEN` | `G8` |
| `AI-003` | Descomposición de capabilities y seguridad IA | `DECISION_PENDING` | `P0` | `OPEN` | `PG-11` |
| `PRIV-001` | Privacidad, retención, eliminación y analítica | `DECISION_PENDING` | `P0` | `OPEN` | `G7` |
| `PRIV-002` | Minimización de logs, métricas y evidencia | `CONFIRMED_DEBT` | `P0` | `OPEN` | `G7` |
| `DOC-001` | Índice canónico de documentos y contratos vigentes | `REQUIRES_AUDIT` | `P2` | `OPEN` | `G8` |
| `DOC-002` | Contradicciones y siguientes pasos históricos | `REQUIRES_AUDIT` | `P1` | `OPEN` | `G8` |
| `DOC-003` | Gobernanza del registro de deuda | `DECISION_PENDING` | `P1` | `OPEN` | `G8` |
| `DOC-004` | Ciclo AWS 24/24 cerrado | `CLOSED_REFERENCE_ONLY` | `P3` | `PROTECTED` | `G7` |
| `DOC-005` | No existe Microfase 25 | `CLOSED_REFERENCE_ONLY` | `P3` | `PROTECTED` | `G8` |
| `DOC-006` | Runbooks internos del plano de control | `CONFIRMED_DEBT` | `P1` | `OPEN` | `PG-10` |
| `RUNTIME-001` | Readiness responde 503 | `RUNTIME_GATE` | `P0` | `GATED` | `G7` |
| `RUNTIME-002` | Retorno Stripe productivo ausente | `RUNTIME_GATE` | `P0` | `GATED` | `G5` |
| `RUNTIME-003` | Fingerprinting de assets incompleto | `RUNTIME_GATE` | `P1` | `GATED` | `G8` |
| `RUNTIME-004` | Maps y CSP legacy pendientes | `RUNTIME_GATE` | `P1` | `GATED` | `G6` |
| `RUNTIME-005` | Logs legacy de Agenda sin saneamiento | `RUNTIME_GATE` | `P0` | `GATED` | `G7` |
| `RUNTIME-006` | Application metric emission no integrada | `RUNTIME_GATE` | `P1` | `GATED` | `G7` |
| `RUNTIME-007` | Migrator fail-closed | `RUNTIME_GATE` | `P0` | `GATED` | `G7` |
| `RUNTIME-008` | Usuario DB mxmed_app no creado | `RUNTIME_GATE` | `P0` | `GATED` | `G7` |
| `RUNTIME-009` | Secretos reales no configurados | `RUNTIME_GATE` | `P0` | `GATED` | `G7` |
| `RUNTIME-010` | Dominio y certificados pendientes | `RUNTIME_GATE` | `P0` | `GATED` | `G6` |
| `RUNTIME-011` | Backup y restore reales no ejecutados | `RUNTIME_GATE` | `P0` | `GATED` | `G7` |
| `RUNTIME-012` | Cost Explorer y tags no verificados | `RUNTIME_GATE` | `P0` | `GATED` | `G7` |
| `RUNTIME-013` | Despliegue AWS y tráfico en NO-GO | `RUNTIME_GATE` | `P0` | `GATED` | `G7` |
| `TECH-001` | Convergencia Clinical v1/v2 y fuente paciente | `DEFERRED_REFACTOR` | `P1` | `DEFERRED` | `G7` |
| `TECH-002` | Wrappers API no homogéneos | `DEFERRED_REFACTOR` | `P2` | `DEFERRED` | `G7` |
| `TECH-003` | Schemas y documentación Agenda divergentes | `DEFERRED_REFACTOR` | `P2` | `DEFERRED` | `G3` |
| `QA-001` | Auditoría global de cobertura antes de G1 | `REQUIRES_AUDIT` | `P1` | `OPEN` | `GLOBAL` |
| `QA-002` | Matriz de estados de error y casos borde | `REQUIRES_AUDIT` | `P1` | `OPEN` | `G8` |
| `QA-003` | Fixtures, mocks y demo fuera de producción | `REQUIRES_AUDIT` | `P0` | `OPEN` | `G7` |
| `QA-004` | Integridad cruzada entre módulos | `REQUIRES_AUDIT` | `P0` | `OPEN` | `G7` |

## 11. Deuda por dominio

### CAP — Planes y capacidades

#### CAP-001 — Matriz canónica única de planes y capacidades

- **ID:** `CAP-001`
- **Título:** Matriz canónica única de planes y capacidades
- **Dominio:** `planes-capacidades`
- **Clasificación:** `DECISION_PENDING`
- **Prioridad:** `P1`
- **Estado:** `OPEN`
- **Descripción actual:** Existen reglas y beneficios por plan en decisiones y servicios, pero este registro no certifica que formen una matriz productiva única y vigente.
- **Evidencia:** `Solicitud PRODUCT-DOC/MXMed-System-Wide-Product-Debt-Registry-01`; `docs/PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md: PP01/PP220`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** Puede haber mensajes o disponibilidad de capacidades difíciles de comparar entre pantallas.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Sin riesgo adicional confirmado; validar alcance, autorización y privacidad antes de implementar.
- **Planes afectados:** `free`, `basic`, `standard`, `optimum`, `professional`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `plan-catalog`, `entitlements`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** Aprobar matriz plan→capacidad→estado→límite.
- **Auditoría requerida:** Comparar contratos, read-models, UI y enforcement backend; declarar una fuente canónica.
- **Grupo recomendado:** `G1`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** La evidencia, decisión, estados y QA quedan documentados sin duplicar fuentes de verdad.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `product-commercial-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### CAP-002 — Cuotas y límites por plan

- **ID:** `CAP-002`
- **Título:** Cuotas y límites por plan
- **Dominio:** `planes-capacidades`
- **Clasificación:** `REQUIRES_AUDIT`
- **Prioridad:** `P1`
- **Estado:** `OPEN`
- **Descripción actual:** No se concluye que todas las cuotas, límites de imágenes, IA, agenda, colaboradores y otras capacidades estén modeladas de forma uniforme.
- **Evidencia:** `Solicitud PRODUCT-DOC/MXMed-System-Wide-Product-Debt-Registry-01`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Sin riesgo adicional confirmado; validar alcance, autorización y privacidad antes de implementar.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `quotas`, `image-limits`, `ai-usage`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** Inventariar cada cuota visible, configurada y aplicada; marcar ausencias o contradicciones.
- **Grupo recomendado:** `G1`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** Cada cuota tiene unidad, ventana, fuente, enforcement y respuesta al agotarse.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `product-commercial-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### CAP-003 — Estados homogéneos de capacidades

- **ID:** `CAP-003`
- **Título:** Estados homogéneos de capacidades
- **Dominio:** `planes-capacidades`
- **Clasificación:** `DECISION_PENDING`
- **Prioridad:** `P1`
- **Estado:** `OPEN`
- **Descripción actual:** Falta adoptar una semántica transversal para enabled, read_only, locked_upsell, blocked_dependency, pending_activation, grace_limited, hidden_security y not_applicable.
- **Evidencia:** `Solicitud PRODUCT-DOC/MXMed-System-Wide-Product-Debt-Registry-01`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Sin riesgo adicional confirmado; validar alcance, autorización y privacidad antes de implementar.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `entitlement-state`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** Aprobar estado y precedencia por capacidad.
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G1`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** Frontend y backend consumen el mismo estado contractual y razón.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `product-architecture-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### CAP-004 — Ownership separado del plan gratuito

- **ID:** `CAP-004`
- **Título:** Ownership separado del plan gratuito
- **Dominio:** `planes-ownership`
- **Clasificación:** `DECISION_PENDING`
- **Prioridad:** `P1`
- **Estado:** `OPEN`
- **Descripción actual:** Un perfil unclaimed/claimed no debe confundirse con una suscripción gratuita; la separación requiere contrato canónico transversal.
- **Evidencia:** `Solicitud PRODUCT-DOC/MXMed-System-Wide-Product-Debt-Registry-01`; `docs/PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md: PP01, decisiones ownership multi-entidad`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Sin riesgo adicional confirmado; validar alcance, autorización y privacidad antes de implementar.
- **Planes afectados:** `free`, `paid`
- **Roles afectados:** `profile-owner`, `platform-operator`
- **Capacidades relacionadas:** `claim-profile`, `profile-management`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** Definir ejes independientes ownership, publicación, plan y verificación.
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G1`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** La evidencia, decisión, estados y QA quedan documentados sin duplicar fuentes de verdad.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `product-identity-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### CAP-005 — Máquina de estados comercial completa

- **ID:** `CAP-005`
- **Título:** Máquina de estados comercial completa
- **Dominio:** `planes-ciclo-comercial`
- **Clasificación:** `DECISION_PENDING`
- **Prioridad:** `P1`
- **Estado:** `OPEN`
- **Descripción actual:** Grace, past_due, restricted, expired y activación existen en decisiones parciales; falta una máquina de estado de producto única con efectos por capacidad.
- **Evidencia:** `Solicitud PRODUCT-DOC/MXMed-System-Wide-Product-Debt-Registry-01`; `docs/PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md: decisiones de suscripción, gracia y activación`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Sin riesgo adicional confirmado; validar alcance, autorización y privacidad antes de implementar.
- **Planes afectados:** `paid`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `grace`, `past-due`, `restriction`, `expiration`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** Aprobar transiciones, temporizadores, mensajes y acciones permitidas.
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G1`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** La evidencia, decisión, estados y QA quedan documentados sin duplicar fuentes de verdad.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `product-commercial-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### CAP-006 — Downgrade, reactivación y conservación de datos

- **ID:** `CAP-006`
- **Título:** Downgrade, reactivación y conservación de datos
- **Dominio:** `planes-ciclo-comercial`
- **Clasificación:** `DECISION_PENDING`
- **Prioridad:** `P0`
- **Estado:** `OPEN`
- **Descripción actual:** Debe definirse qué queda read-only, bloqueado o retenido al perder una capacidad y cómo se reactiva sin pérdida ni acceso indebido.
- **Evidencia:** `Solicitud PRODUCT-DOC/MXMed-System-Wide-Product-Debt-Registry-01`; `docs/PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md: PP01 sección gating/suscripción`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Riesgo de pérdida, exposición o borrado incorrecto de datos si no hay política por entidad.
- **Planes afectados:** `paid-to-lower`, `paid-to-free`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `downgrade`, `reactivation`, `data-retention`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** Aprobar política por capacidad, datos, retención y rollback.
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G1`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** La evidencia, decisión, estados y QA quedan documentados sin duplicar fuentes de verdad.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `data-governance-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### CAP-007 — Patrón visual único de bloqueo y upsell

- **ID:** `CAP-007`
- **Título:** Patrón visual único de bloqueo y upsell
- **Dominio:** `planes-ux`
- **Clasificación:** `REQUIRES_AUDIT`
- **Prioridad:** `P2`
- **Estado:** `OPEN`
- **Descripción actual:** Hay controles y módulos condicionados, pero no se certifica una explicación contextual y CTA coherente en todo el sistema.
- **Evidencia:** `Solicitud PRODUCT-DOC/MXMed-System-Wide-Product-Debt-Registry-01`; `index.html`; `assets/js/app.js`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** Una función puede parecer deshabilitada o rota sin explicar requisito, plan o dependencia.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Sin riesgo adicional confirmado; validar alcance, autorización y privacidad antes de implementar.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `upsell`, `locked-feature-messaging`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** Localizar funciones visibles/bloqueadas y clasificar explicación, CTA, accesibilidad y seguridad.
- **Grupo recomendado:** `G1`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** Ningún disabled de producto queda sin razón accionable; hidden_security no revela capacidad.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `ux-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### CAP-008 — Enforcement equivalente frontend y backend

- **ID:** `CAP-008`
- **Título:** Enforcement equivalente frontend y backend
- **Dominio:** `planes-seguridad`
- **Clasificación:** `REQUIRES_AUDIT`
- **Prioridad:** `P0`
- **Estado:** `OPEN`
- **Descripción actual:** La presencia de gating visual o de un servicio de capacidades no prueba enforcement equivalente en cada write/read backend.
- **Evidencia:** `Solicitud PRODUCT-DOC/MXMed-System-Wide-Product-Debt-Registry-01`; `modules/profiles/services/PublicProfilePlanCapabilities.php`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Posible acceso a capacidades por ruta directa si el backend no aplica el mismo contrato.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** ninguna específica
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** Trazar por capacidad UI→endpoint→guard→servicio→repositorio y probar denegación.
- **Grupo recomendado:** `G1`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** Cada capacidad sensible tiene guard backend y respuesta consistente con UI.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `security-product-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### CAP-009 — Contrato gratuito no administrado vs administrado

- **ID:** `CAP-009`
- **Título:** Contrato gratuito no administrado vs administrado
- **Dominio:** `planes-gratuito`
- **Clasificación:** `DECISION_PENDING`
- **Prioridad:** `P1`
- **Estado:** `OPEN`
- **Descripción actual:** Debe cerrarse qué puede mostrar y editar un perfil gratuito unclaimed y claimed, sin activar servicios pagados.
- **Evidencia:** `Solicitud PRODUCT-DOC/MXMed-System-Wide-Product-Debt-Registry-01`; `docs/PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md: PP01`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Sin riesgo adicional confirmado; validar alcance, autorización y privacidad antes de implementar.
- **Planes afectados:** `free`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `profile-text`, `avatar`, `gallery`, `tel`, `whatsapp`, `external-maps`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** Aprobar matriz gratuita por ownership y verificación.
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G1`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** Funciones gratuitas no cambian activation modes ni crean recursos pagados.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `product-commercial-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### CAP-010 — Perfiles AWS Cost-Aware y profile-aware protegidos

- **ID:** `CAP-010`
- **Título:** Perfiles AWS Cost-Aware y profile-aware protegidos
- **Dominio:** `infraestructura-referencia`
- **Clasificación:** `CLOSED_REFERENCE_ONLY`
- **Prioridad:** `P3`
- **Estado:** `PROTECTED`
- **Descripción actual:** Cost-Aware, Compute, Edge, Operations y Backup/DR profile-aware ya están cerrados como foundation offline y no son deuda de producto a reimplementar.
- **Evidencia:** `docs/PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md: PP263-PP272`; `infra/aws/README.md`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Sin riesgo adicional confirmado; validar alcance, autorización y privacidad antes de implementar.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `aws-launch-profiles`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G7`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** La evidencia, decisión, estados y QA quedan documentados sin duplicar fuentes de verdad.
- **No repetición:** No reabrir arquitectura AWS ni inferir despliegue; sólo nuevas evidencias pueden cambiar el cierre.
- **Owner funcional:** `platform-architecture-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

### OWN — Propiedad y reclamo

#### OWN-001 — Flujo real de reclamo de perfil

- **ID:** `OWN-001`
- **Título:** Flujo real de reclamo de perfil
- **Dominio:** `ownership-reclamo`
- **Clasificación:** `PARTIAL_IMPLEMENTATION`
- **Prioridad:** `P1`
- **Estado:** `OPEN`
- **Descripción actual:** El contrato y el CTA existen, pero el perfil público expone claim_url null/source not ready y el enlace visible permanece aria-disabled.
- **Evidencia:** `profiles/doctor.php`; `modules/profiles/services/PublicProfilePlanCapabilities.php`; `docs/PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md: PP01`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El titular ve la invitación a reclamar, pero no dispone de un flujo funcional confirmado.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Sin riesgo adicional confirmado; validar alcance, autorización y privacidad antes de implementar.
- **Planes afectados:** `all`
- **Roles afectados:** `unclaimed-profile-holder`, `platform-operator`
- **Capacidades relacionadas:** `claim-profile`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G2`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** Solicitud, credenciales, evidencia, revisión y resultado funcionan con trazabilidad.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `product-identity-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### OWN-002 — Estados y revisión manual del reclamo

- **ID:** `OWN-002`
- **Título:** Estados y revisión manual del reclamo
- **Dominio:** `ownership-reclamo`
- **Clasificación:** `DECISION_PENDING`
- **Prioridad:** `P1`
- **Estado:** `OPEN`
- **Descripción actual:** Los estados unclaimed, claim_pending, claimed, rejected y needs_info están contratados; falta certificar implementación, transiciones y notificaciones.
- **Evidencia:** `Solicitud PRODUCT-DOC/MXMed-System-Wide-Product-Debt-Registry-01`; `docs/PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md: PP01`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Sin riesgo adicional confirmado; validar alcance, autorización y privacidad antes de implementar.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `claim-review`, `claim-notifications`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** Aprobar autoridad, SLA, evidencia mínima y transiciones.
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G2`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** La evidencia, decisión, estados y QA quedan documentados sin duplicar fuentes de verdad.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `trust-safety-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### OWN-003 — Disputa, duplicado, revocación y transferencia

- **ID:** `OWN-003`
- **Título:** Disputa, duplicado, revocación y transferencia
- **Dominio:** `ownership-reclamo`
- **Clasificación:** `DECISION_PENDING`
- **Prioridad:** `P0`
- **Estado:** `OPEN`
- **Descripción actual:** No existe decisión consolidada para reclamaciones duplicadas, disputa, revocación o transferencia de propiedad.
- **Evidencia:** `Solicitud PRODUCT-DOC/MXMed-System-Wide-Product-Debt-Registry-01`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Riesgo de control indebido de perfil o pérdida de acceso del titular.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `claim-dispute`, `ownership-transfer`, `claim-audit`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** Aprobar prueba de titularidad, doble control, rollback y auditoría.
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G2`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** La evidencia, decisión, estados y QA quedan documentados sin duplicar fuentes de verdad.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `trust-safety-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

### AUTH — Identidad y acceso

#### AUTH-001 — Login productivo del médico

- **ID:** `AUTH-001`
- **Título:** Login productivo del médico
- **Dominio:** `identidad-acceso`
- **Clasificación:** `CONFIRMED_DEBT`
- **Prioridad:** `P0`
- **Estado:** `OPEN`
- **Descripción actual:** La documentación AWS/Edge no localizó handlers productivos explícitos de login; verify-password es un stub y no crea una sesión médica real.
- **Evidencia:** `docs/PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md: PP50 y PP266`; `api/verify-password.php`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** No hay evidencia de un acceso productivo completo para administrar el sistema.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Bloquea identidad fiable, sesiones, permisos y despliegue.
- **Planes afectados:** `all`
- **Roles afectados:** `doctor`, `platform-operator`
- **Capacidades relacionadas:** `login`, `session`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G2`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** Login real crea sesión segura, aplica rate limits y no depende de fixtures.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `identity-security-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### AUTH-002 — Recuperación de cuenta y tokens de un solo uso

- **ID:** `AUTH-002`
- **Título:** Recuperación de cuenta y tokens de un solo uso
- **Dominio:** `identidad-acceso`
- **Clasificación:** `CONFIRMED_DEBT`
- **Prioridad:** `P0`
- **Estado:** `OPEN`
- **Descripción actual:** No se encontraron handlers productivos explícitos de recovery/reset en el inventario Edge y no se presume implementación por UI.
- **Evidencia:** `docs/PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md: PP266 inventario de rutas`; `Solicitud PRODUCT-DOC/MXMed-System-Wide-Product-Debt-Registry-01`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El usuario no tiene recuperación productiva confirmada.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Riesgo de bloqueo de cuenta o recuperación insegura.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `account-recovery`, `one-time-token`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G2`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** Respuesta anti-enumeración, token one-time, expiración, revocación y auditoría probadas.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `identity-security-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### AUTH-003 — Ciclo de sesión y cambios sensibles

- **ID:** `AUTH-003`
- **Título:** Ciclo de sesión y cambios sensibles
- **Dominio:** `identidad-seguridad`
- **Clasificación:** `REQUIRES_AUDIT`
- **Prioridad:** `P0`
- **Estado:** `OPEN`
- **Descripción actual:** Logout, revocación global, cambio de correo/password y sesiones sospechosas requieren inventario funcional fuera del contrato de infraestructura de sesiones.
- **Evidencia:** `Solicitud PRODUCT-DOC/MXMed-System-Wide-Product-Debt-Registry-01`; `docs/PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md: PP260-PP261 sólo foundation de sesión`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Riesgo de sesiones persistentes tras cambio sensible o incidente.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `logout`, `session-revocation`, `email-change`, `password-change`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** Trazar UI/API/session store/eventos/notificaciones para cada cambio.
- **Grupo recomendado:** `G2`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** La evidencia, decisión, estados y QA quedan documentados sin duplicar fuentes de verdad.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `identity-security-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### AUTH-004 — MFA por riesgo y cuentas operativas

- **ID:** `AUTH-004`
- **Título:** MFA por riesgo y cuentas operativas
- **Dominio:** `identidad-seguridad`
- **Clasificación:** `DECISION_PENDING`
- **Prioridad:** `P0`
- **Estado:** `OPEN`
- **Descripción actual:** Falta decidir segundo factor por riesgo para médicos, operadores, administradores y break-glass.
- **Evidencia:** `Solicitud PRODUCT-DOC/MXMed-System-Wide-Product-Debt-Registry-01`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Riesgo de toma de cuenta y acceso privilegiado.
- **Planes afectados:** `all`
- **Roles afectados:** `doctor`, `operator`, `administrator`, `break-glass`
- **Capacidades relacionadas:** `mfa`, `risk-auth`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** Aprobar factores, enrollment, recovery, step-up y eventos obligatorios.
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G2`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** La evidencia, decisión, estados y QA quedan documentados sin duplicar fuentes de verdad.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `identity-security-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

### UX — Experiencia y accesibilidad

#### UX-001 — Inventario global de pantallas y funciones

- **ID:** `UX-001`
- **Título:** Inventario global de pantallas y funciones
- **Dominio:** `experiencia-producto`
- **Clasificación:** `REQUIRES_AUDIT`
- **Prioridad:** `P1`
- **Estado:** `OPEN`
- **Descripción actual:** Existen inventarios parciales, pero no uno actual que cubra todo el sistema, estados y relaciones.
- **Evidencia:** `Solicitud PRODUCT-DOC/MXMed-System-Wide-Product-Debt-Registry-01`; `docs/MAPA_TOTAL_SISTEMA_MXMED.md`; `docs/PLAN_MAESTRO_MXMED.md`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Sin riesgo adicional confirmado; validar alcance, autorización y privacidad antes de implementar.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** ninguna específica
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** Ejecutar primero PRODUCT-AUDIT/MXMed-System-Wide-Screen-Function-Api-Data-Inventory-01.
- **Grupo recomendado:** `G8`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** Cada pantalla y función tiene ruta, estado, rol, plan, API/dato y evidencia.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `product-operations-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### UX-002 — Consistencia de navegación y jerarquía visual

- **ID:** `UX-002`
- **Título:** Consistencia de navegación y jerarquía visual
- **Dominio:** `experiencia-navegacion`
- **Clasificación:** `REQUIRES_AUDIT`
- **Prioridad:** `P2`
- **Estado:** `OPEN`
- **Descripción actual:** El shell, vistas legacy, embeds y snapshots documentales coexisten; no se concluye consistencia completa.
- **Evidencia:** `docs/MAPA_TOTAL_SISTEMA_MXMED.md`; `docs/AGENDA_ESTADO_CONSOLIDACION_Y_DEUDA_UI_MXMED.md`; `docs/ui/GLOSARIO_UI_MXMED.md`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** Navegación, términos o ubicación de acciones pueden variar entre frentes.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Sin riesgo adicional confirmado; validar alcance, autorización y privacidad antes de implementar.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** ninguna específica
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** Comparar shell, embed, legacy y móvil usando glosario oficial.
- **Grupo recomendado:** `G8`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** La evidencia, decisión, estados y QA quedan documentados sin duplicar fuentes de verdad.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `ux-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### UX-003 — Cobertura de estados vacío, carga, error y éxito

- **ID:** `UX-003`
- **Título:** Cobertura de estados vacío, carga, error y éxito
- **Dominio:** `experiencia-estados`
- **Clasificación:** `REQUIRES_AUDIT`
- **Prioridad:** `P1`
- **Estado:** `OPEN`
- **Descripción actual:** No existe evidencia consolidada de los cuatro estados por función/pantalla.
- **Evidencia:** `Solicitud PRODUCT-DOC/MXMed-System-Wide-Product-Debt-Registry-01`; `docs/ui/REGLAS_UI_MXMED.md`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** Una operación puede quedar sin feedback o parecer rota.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Sin riesgo adicional confirmado; validar alcance, autorización y privacidad antes de implementar.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** ninguna específica
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** Inventariar estado por pantalla y por operación, incluidos retry y recuperación.
- **Grupo recomendado:** `G8`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** La evidencia, decisión, estados y QA quedan documentados sin duplicar fuentes de verdad.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `ux-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### UX-004 — Responsive y experiencia móvil

- **ID:** `UX-004`
- **Título:** Responsive y experiencia móvil
- **Dominio:** `experiencia-responsive`
- **Clasificación:** `REQUIRES_AUDIT`
- **Prioridad:** `P2`
- **Estado:** `OPEN`
- **Descripción actual:** No se ejecutó auditoría responsive integral; la existencia de estilos o breakpoints no prueba cobertura.
- **Evidencia:** `Solicitud PRODUCT-DOC/MXMed-System-Wide-Product-Debt-Registry-01`; `assets/css/style.css`; `index.html`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** Controles o contenido pueden degradarse en tamaños no auditados.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Sin riesgo adicional confirmado; validar alcance, autorización y privacidad antes de implementar.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** ninguna específica
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** Revisar viewports, orientación, zoom, teclado móvil y contenido extenso.
- **Grupo recomendado:** `G8`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** La evidencia, decisión, estados y QA quedan documentados sin duplicar fuentes de verdad.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `ux-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### UX-005 — Accesibilidad teclado, lector, foco y contraste

- **ID:** `UX-005`
- **Título:** Accesibilidad teclado, lector, foco y contraste
- **Dominio:** `experiencia-accesibilidad`
- **Clasificación:** `REQUIRES_AUDIT`
- **Prioridad:** `P1`
- **Estado:** `OPEN`
- **Descripción actual:** Atributos aislados no prueban conformidad transversal ni flujo completo.
- **Evidencia:** `Solicitud PRODUCT-DOC/MXMed-System-Wide-Product-Debt-Registry-01`; `index.html`; `profiles/doctor.php`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** Personas que usan teclado o tecnologías asistivas pueden no completar tareas.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Sin riesgo adicional confirmado; validar alcance, autorización y privacidad antes de implementar.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** ninguna específica
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** Auditar navegación, nombre/rol/estado, focus order/return, contraste y anuncios dinámicos.
- **Grupo recomendado:** `G8`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** La evidencia, decisión, estados y QA quedan documentados sin duplicar fuentes de verdad.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `accessibility-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### UX-006 — Lenguaje, copy y errores accionables

- **ID:** `UX-006`
- **Título:** Lenguaje, copy y errores accionables
- **Dominio:** `experiencia-contenido`
- **Clasificación:** `REQUIRES_AUDIT`
- **Prioridad:** `P2`
- **Estado:** `OPEN`
- **Descripción actual:** No existe inventario global de copy, tono, inconsistencias ni errores con siguiente acción.
- **Evidencia:** `Solicitud PRODUCT-DOC/MXMed-System-Wide-Product-Debt-Registry-01`; `index.html`; `assets/js/app.js`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** Mensajes técnicos, ambiguos o contradictorios pueden impedir decisiones.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Sin riesgo adicional confirmado; validar alcance, autorización y privacidad antes de implementar.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** ninguna específica
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** Catalogar labels, ayudas, errores, mensajes comerciales y clínicos sin datos reales.
- **Grupo recomendado:** `G8`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** La evidencia, decisión, estados y QA quedan documentados sin duplicar fuentes de verdad.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `content-design-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### UX-007 — Ayuda contextual y centro de ayuda

- **ID:** `UX-007`
- **Título:** Ayuda contextual y centro de ayuda
- **Dominio:** `experiencia-soporte`
- **Clasificación:** `REQUIRES_AUDIT`
- **Prioridad:** `P2`
- **Estado:** `OPEN`
- **Descripción actual:** No se confirma cobertura de ayuda contextual, artículos, soporte o escalamiento por flujo.
- **Evidencia:** `Solicitud PRODUCT-DOC/MXMed-System-Wide-Product-Debt-Registry-01`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El usuario puede quedar sin explicación o ruta de soporte.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Sin riesgo adicional confirmado; validar alcance, autorización y privacidad antes de implementar.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** ninguna específica
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** Inventariar enlaces de ayuda, contacto, FAQ, soporte y fallback por estado.
- **Grupo recomendado:** `G8`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** La evidencia, decisión, estados y QA quedan documentados sin duplicar fuentes de verdad.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `support-product-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

### PUB — Perfil público

#### PUB-001 — CTA de reclamo y sugerencia de corrección

- **ID:** `PUB-001`
- **Título:** CTA de reclamo y sugerencia de corrección
- **Dominio:** `perfil-publico`
- **Clasificación:** `PARTIAL_IMPLEMENTATION`
- **Prioridad:** `P1`
- **Estado:** `OPEN`
- **Descripción actual:** Ambos enlaces aparecen en el perfil público, pero usan href # y aria-disabled.
- **Evidencia:** `profiles/doctor.php`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** Las acciones se muestran sin una ruta utilizable.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Sin riesgo adicional confirmado; validar alcance, autorización y privacidad antes de implementar.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `claim-profile`, `suggest-correction`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G6`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** Cada CTA abre flujo accesible, seguro, trazable y con estados completos.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `public-profile-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### PUB-002 — Teléfono y WhatsApp por plan/ownership

- **ID:** `PUB-002`
- **Título:** Teléfono y WhatsApp por plan/ownership
- **Dominio:** `perfil-publico-contacto`
- **Clasificación:** `DECISION_PENDING`
- **Prioridad:** `P1`
- **Estado:** `OPEN`
- **Descripción actual:** Los contratos describen tel/wa.me y gating, pero falta una matriz canónica verificada end-to-end.
- **Evidencia:** `Solicitud PRODUCT-DOC/MXMed-System-Wide-Product-Debt-Registry-01`; `docs/PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md: PP01 y decisiones de perfil público`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Sin riesgo adicional confirmado; validar alcance, autorización y privacidad antes de implementar.
- **Planes afectados:** `free`, `basic`, `standard`, `optimum`, `professional`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `tel-link`, `whatsapp-link`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** Definir visibilidad, source, consentimiento, configuración y tracking.
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G6`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** La evidencia, decisión, estados y QA quedan documentados sin duplicar fuentes de verdad.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `public-profile-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### PUB-003 — Maps sólo como enlace externo

- **ID:** `PUB-003`
- **Título:** Maps sólo como enlace externo
- **Dominio:** `perfil-publico-maps`
- **Clasificación:** `PARTIAL_IMPLEMENTATION`
- **Prioridad:** `P2`
- **Estado:** `OPEN`
- **Descripción actual:** El contrato AWS mantiene Maps legacy pendiente y el perfil contiene una representación placeholder; no se certifica enlace externo homogéneo.
- **Evidencia:** `profiles/doctor.php`; `docs/PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md: PP267/PP272`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** La ubicación puede presentarse sin acción clara o con dependencia legacy.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Sin riesgo adicional confirmado; validar alcance, autorización y privacidad antes de implementar.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `external-maps`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G6`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** Dirección válida abre enlace externo seguro; no carga servicio pagado por mostrarlo.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `public-profile-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### PUB-004 — Rutas SEO y canonical productivo

- **ID:** `PUB-004`
- **Título:** Rutas SEO y canonical productivo
- **Dominio:** `perfil-publico-seo`
- **Clasificación:** `PARTIAL_IMPLEMENTATION`
- **Prioridad:** `P1`
- **Estado:** `OPEN`
- **Descripción actual:** El controlador mantiene route_generation disabled y el sistema conserva rutas transicionales; dominio/canonical finales no están activos.
- **Evidencia:** `modules/profiles/controllers/PublicProfileController.php`; `docs/PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md: decisiones de Perfil Público y PP272`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El perfil no tiene ruta SEO productiva confirmada.
- **Riesgo de negocio:** Impacta descubrimiento, enlaces estables y lanzamiento.
- **Riesgo de datos o seguridad:** Sin riesgo adicional confirmado; validar alcance, autorización y privacidad antes de implementar.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `seo-route`, `canonical`, `redirect-history`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G6`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** Slug, canonical, redirects y estados index/noindex están aprobados y probados.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `seo-product-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### PUB-005 — Galería, imagen y límites públicos

- **ID:** `PUB-005`
- **Título:** Galería, imagen y límites públicos
- **Dominio:** `perfil-publico-media`
- **Clasificación:** `REQUIRES_AUDIT`
- **Prioridad:** `P2`
- **Estado:** `OPEN`
- **Descripción actual:** No se certifica matriz actual de foto, galería, límites, moderación y retención por plan.
- **Evidencia:** `Solicitud PRODUCT-DOC/MXMed-System-Wide-Product-Debt-Registry-01`; `docs/PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md: PP01`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Sin riesgo adicional confirmado; validar alcance, autorización y privacidad antes de implementar.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `avatar`, `gallery`, `image-moderation`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** Trazar carga, límites, storage, moderación, publicación, borrado y downgrade.
- **Grupo recomendado:** `G6`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** La evidencia, decisión, estados y QA quedan documentados sin duplicar fuentes de verdad.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `public-profile-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

### REV — Comentarios y reseñas

#### REV-001 — Persistencia de comentarios y reseñas

- **ID:** `REV-001`
- **Título:** Persistencia de comentarios y reseñas
- **Dominio:** `comentarios-reseñas`
- **Clasificación:** `PARTIAL_IMPLEMENTATION`
- **Prioridad:** `P1`
- **Estado:** `OPEN`
- **Descripción actual:** MAPA_TOTAL registra UI de opiniones sin DB/API detectada; el read-model público expone previews vacíos y capacidades.
- **Evidencia:** `docs/MAPA_TOTAL_SISTEMA_MXMED.md`; `index.html`; `modules/profiles/services/PublicProfilePlanCapabilities.php`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** La UI puede mostrar el dominio sin publicación/persistencia confirmada.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Sin riesgo adicional confirmado; validar alcance, autorización y privacidad antes de implementar.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `review-create`, `review-list`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G6`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** Publicación y lectura usan identidad, consentimiento, rate limit, persistencia y auditoría.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `trust-safety-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### REV-002 — Moderación, respuesta, denuncia y spam

- **ID:** `REV-002`
- **Título:** Moderación, respuesta, denuncia y spam
- **Dominio:** `comentarios-reseñas`
- **Clasificación:** `DECISION_PENDING`
- **Prioridad:** `P1`
- **Estado:** `OPEN`
- **Descripción actual:** Existen reglas de producto, pero no se certifica flujo completo de respuesta, denuncia, archivo/restauración, spam o eliminación.
- **Evidencia:** `Solicitud PRODUCT-DOC/MXMed-System-Wide-Product-Debt-Registry-01`; `docs/PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md: PP01`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Riesgo de abuso, difamación, suplantación o contenido sensible.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `review-reply`, `review-report`, `review-moderation`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** Aprobar roles, estados, evidencias, SLA, apelación y retención.
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G6`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** La evidencia, decisión, estados y QA quedan documentados sin duplicar fuentes de verdad.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `trust-safety-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### REV-003 — Privacidad y reputación en reseñas

- **ID:** `REV-003`
- **Título:** Privacidad y reputación en reseñas
- **Dominio:** `comentarios-reseñas`
- **Clasificación:** `DECISION_PENDING`
- **Prioridad:** `P0`
- **Estado:** `OPEN`
- **Descripción actual:** Debe prohibirse contenido clínico y definirse reputación sin exponer relación asistencial o datos sensibles.
- **Evidencia:** `Solicitud PRODUCT-DOC/MXMed-System-Wide-Product-Debt-Registry-01`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Riesgo de revelar información clínica o vínculo paciente-profesional.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `review-privacy`, `reputation`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** Aprobar moderación preventiva/reactiva, redacción y manejo de evidencia.
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G6`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** La evidencia, decisión, estados y QA quedan documentados sin duplicar fuentes de verdad.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `privacy-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

### AGD — Agenda

#### AGD-001 — Convergencia shell Agenda y frontend legacy

- **ID:** `AGD-001`
- **Título:** Convergencia shell Agenda y frontend legacy
- **Dominio:** `agenda`
- **Clasificación:** `DEFERRED_REFACTOR`
- **Prioridad:** `P2`
- **Estado:** `DEFERRED`
- **Descripción actual:** Agenda core está consolidada, pero coexisten shell custom y UI server-rendered con cobertura no uniforme.
- **Evidencia:** `docs/AGENDA_ESTADO_CONSOLIDACION_Y_DEUDA_UI_MXMED.md`; `docs/MAPA_TOTAL_SISTEMA_MXMED.md`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** Algunos flujos pueden tener experiencia distinta según entrada.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Sin riesgo adicional confirmado; validar alcance, autorización y privacidad antes de implementar.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `agenda-ui`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G3`
- **Acción recomendada:** Planificar convergencia sin reescribir API ni reabrir Agenda v1.
- **Criterio de aceptación:** Cobertura y semántica equivalentes; fallback/retirada documentados.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `agenda-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### AGD-002 — Retiro progresivo de localStorage legacy en bloqueos

- **ID:** `AGD-002`
- **Título:** Retiro progresivo de localStorage legacy en bloqueos
- **Dominio:** `agenda`
- **Clasificación:** `DEFERRED_REFACTOR`
- **Prioridad:** `P2`
- **Estado:** `DEFERRED`
- **Descripción actual:** El backend canónico existe, pero persiste compatibilidad localStorage y hardening multi-sesión pendiente.
- **Evidencia:** `docs/AGENDA_ESTADO_CONSOLIDACION_Y_DEUDA_UI_MXMED.md`; `docs/PLAN_MAESTRO_MXMED.md`; `assets/js/app.js`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Riesgo operativo de divergencia cliente/backend en fallos parciales o multi-sesión.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `availability-blocks`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G3`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** Backend es autoritativo; fallback y migración están retirados o encapsulados con QA.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `agenda-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### AGD-003 — Agenda sin expediente: frontera de datos permitidos

- **ID:** `AGD-003`
- **Título:** Agenda sin expediente: frontera de datos permitidos
- **Dominio:** `agenda-clinico`
- **Clasificación:** `DECISION_PENDING`
- **Prioridad:** `P0`
- **Estado:** `OPEN`
- **Descripción actual:** Debe aprobarse el mínimo de contacto/cita y bloquear diagnóstico, antecedentes, alergias, medicamentos, notas y archivos clínicos sin expediente/consentimiento.
- **Evidencia:** `Solicitud PRODUCT-DOC/MXMed-System-Wide-Product-Debt-Registry-01`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El formulario de agenda debe pedir sólo datos operativos pertinentes.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Riesgo de capturar clínica sin contrato, consentimiento o control de acceso.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `appointment-contact`, `clinical-upgrade`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** Aprobar campos, separación, upgrade y retención.
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G3`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** La evidencia, decisión, estados y QA quedan documentados sin duplicar fuentes de verdad.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `clinical-product-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### AGD-004 — Creación controlada de expediente desde Agenda

- **ID:** `AGD-004`
- **Título:** Creación controlada de expediente desde Agenda
- **Dominio:** `agenda-clinico`
- **Clasificación:** `DECISION_PENDING`
- **Prioridad:** `P0`
- **Estado:** `OPEN`
- **Descripción actual:** Agenda no debe crear encounter automáticamente; falta completar política de expediente/consentimiento, upgrade, downgrade y read-only.
- **Evidencia:** `docs/AGENDA_ESTADO_CONSOLIDACION_Y_DEUDA_UI_MXMED.md`; `docs/clinical/DECISION_AGENDA_CREATES_CLINICAL_ENCOUNTER_V1.md`; `Solicitud PRODUCT-DOC/MXMed-System-Wide-Product-Debt-Registry-01`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Riesgo de creación clínica implícita o acceso fuera de consentimiento.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `appointment-to-patient`, `appointment-to-encounter`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** Definir acción explícita, autoridad, consentimiento y rollback.
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G3`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** La evidencia, decisión, estados y QA quedan documentados sin duplicar fuentes de verdad.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `clinical-product-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### AGD-005 — Concurrencia, reintentos y estados borde de Agenda

- **ID:** `AGD-005`
- **Título:** Concurrencia, reintentos y estados borde de Agenda
- **Dominio:** `agenda-calidad`
- **Clasificación:** `REQUIRES_AUDIT`
- **Prioridad:** `P1`
- **Estado:** `OPEN`
- **Descripción actual:** Existen contratos y QA parciales; falta inventario global de doble clic, retry, sesión vencida, edición simultánea, error de red y duplicados.
- **Evidencia:** `Solicitud PRODUCT-DOC/MXMed-System-Wide-Product-Debt-Registry-01`; `docs/qa/QA_AGENDA_OPERADORES_CHECKLIST.md`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Sin riesgo adicional confirmado; validar alcance, autorización y privacidad antes de implementar.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** ninguna específica
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** Consolidar matriz por write y por actor con idempotencia, conflicto y recuperación.
- **Grupo recomendado:** `G3`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** Cada write tiene respuesta determinista, retry seguro y feedback accionable.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `agenda-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

### PAT — Pacientes

#### PAT-001 — Detección y conciliación de contactos duplicados

- **ID:** `PAT-001`
- **Título:** Detección y conciliación de contactos duplicados
- **Dominio:** `pacientes`
- **Clasificación:** `REQUIRES_AUDIT`
- **Prioridad:** `P1`
- **Estado:** `OPEN`
- **Descripción actual:** No se certifica estrategia transversal de matching, merge, conflicto o reversión de duplicados.
- **Evidencia:** `Solicitud PRODUCT-DOC/MXMed-System-Wide-Product-Debt-Registry-01`; `docs/MAPEO_PACIENTES_MXMED.md`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Riesgo de fragmentar identidad o fusionar datos de personas distintas.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `patient-deduplication`, `patient-merge`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** Inventariar claves, matching, revisión humana, merge, undo y auditoría.
- **Grupo recomendado:** `G3`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** La evidencia, decisión, estados y QA quedan documentados sin duplicar fuentes de verdad.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `patient-domain-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### PAT-002 — Identidad canónica y contratos divergentes

- **ID:** `PAT-002`
- **Título:** Identidad canónica y contratos divergentes
- **Dominio:** `pacientes-datos`
- **Clasificación:** `CONFIRMED_DEBT`
- **Prioridad:** `P0`
- **Estado:** `OPEN`
- **Descripción actual:** MAPA_TOTAL documenta divergencia display_name/birthdate vs full_name/birth_date e IDs legacy en recursos clínicos.
- **Evidencia:** `docs/MAPA_TOTAL_SISTEMA_MXMED.md`; `docs/MAPEO_PACIENTES_MXMED.md`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Riesgo de enlazar documentos o citas a identidad incorrecta.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `patient-identity`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G3`
- **Acción recomendada:** Definir mapping/versionado y eliminar inferencias sintéticas en trabajo separado.
- **Criterio de aceptación:** patient_id canónico se preserva end-to-end y divergencias tienen adapter probado.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `patient-domain-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### PAT-003 — Separación contacto de paciente y expediente clínico

- **ID:** `PAT-003`
- **Título:** Separación contacto de paciente y expediente clínico
- **Dominio:** `pacientes-clinico`
- **Clasificación:** `DECISION_PENDING`
- **Prioridad:** `P0`
- **Estado:** `OPEN`
- **Descripción actual:** Debe quedar explícito qué datos administrativos pueden existir sin expediente y cómo se promueven con consentimiento.
- **Evidencia:** `Solicitud PRODUCT-DOC/MXMed-System-Wide-Product-Debt-Registry-01`; `docs/ARQUITECTURA_PACIENTE_GLOBAL_EXPEDIENTE_PRIVADO_MXMED.md`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Riesgo de ampliar acceso o retención clínica por una cita/contacto.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `patient-contact`, `clinical-record`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** Aprobar límites, propósito, retención, acceso y conversión.
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G3`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** La evidencia, decisión, estados y QA quedan documentados sin duplicar fuentes de verdad.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `privacy-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

### CLN — Clínico

#### CLN-001 — Persistencia clínica heterogénea

- **ID:** `CLN-001`
- **Título:** Persistencia clínica heterogénea
- **Dominio:** `clinico`
- **Clasificación:** `CONFIRMED_DEBT`
- **Prioridad:** `P0`
- **Estado:** `OPEN`
- **Descripción actual:** Expediente avanzado mezcla backend, documentos clínicos, localStorage y DOM; varias secciones no tienen persistencia estructurada homogénea.
- **Evidencia:** `docs/MAPA_TOTAL_SISTEMA_MXMED.md`; `docs/expediente_inventario_existente.md`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** Una sección puede aparentar guardado sin persistencia homogénea confirmada.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Riesgo de pérdida, divergencia o trazabilidad incompleta.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `clinical-record`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G3`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** Cada sección declara fuente, save/read, auditoría, rollback y estado offline.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `clinical-domain-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### CLN-002 — Consentimiento informado clínico

- **ID:** `CLN-002`
- **Título:** Consentimiento informado clínico
- **Dominio:** `clinico-consentimiento`
- **Clasificación:** `PARTIAL_IMPLEMENTATION`
- **Prioridad:** `P0`
- **Estado:** `OPEN`
- **Descripción actual:** Existe UI/contrato/mocks, pero el inventario no detecta endpoints productivos api/ci ni persistencia clínica específica completa.
- **Evidencia:** `docs/expediente_inventario_existente.md`; `docs/CONSENTIMIENTO_INFORMADO_MXMED.md`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Riesgo de tratamiento documental sin consentimiento verificable.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `clinical-consent`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G3`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** Crear, firmar, revocar, consultar y auditar consentimiento con identidad y versiones.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `clinical-governance-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### CLN-003 — Adjuntos clínicos del expediente

- **ID:** `CLN-003`
- **Título:** Adjuntos clínicos del expediente
- **Dominio:** `clinico-archivos`
- **Clasificación:** `PARTIAL_IMPLEMENTATION`
- **Prioridad:** `P0`
- **Estado:** `OPEN`
- **Descripción actual:** El inventario identifica el tab de archivo como futuro/placeholder sin DB/API completos para ese flujo.
- **Evidencia:** `docs/expediente_inventario_existente.md`; `docs/MAPA_TOTAL_SISTEMA_MXMED.md`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Riesgo de almacenamiento, clasificación o acceso incorrecto a documentos.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `clinical-attachments`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G3`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** Upload, scan, clasificación, cifrado, permisos, preview, download, retención y backup probados.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `clinical-domain-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### CLN-004 — Permisos de colaboradores por entidad clínica

- **ID:** `CLN-004`
- **Título:** Permisos de colaboradores por entidad clínica
- **Dominio:** `clinico-permisos`
- **Clasificación:** `REQUIRES_AUDIT`
- **Prioridad:** `P0`
- **Estado:** `OPEN`
- **Descripción actual:** No se certifica matriz completa de acceso por entidad, rol, colaboración, propósito y break-glass.
- **Evidencia:** `Solicitud PRODUCT-DOC/MXMed-System-Wide-Product-Debt-Registry-01`; `docs/SEGURIDAD_DATOS_CLINICOS_AWS_KMS_MXMED.md`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Riesgo de acceso clínico indebido.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `clinical-rbac`, `entity-permissions`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** Trazar cada read/write/export a identidad, entidad, scope, auditoría y revocación.
- **Grupo recomendado:** `G7`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** La evidencia, decisión, estados y QA quedan documentados sin duplicar fuentes de verdad.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `clinical-security-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### CLN-005 — Portabilidad, exportación, eliminación y retención clínica

- **ID:** `CLN-005`
- **Título:** Portabilidad, exportación, eliminación y retención clínica
- **Dominio:** `clinico-gobernanza`
- **Clasificación:** `DECISION_PENDING`
- **Prioridad:** `P0`
- **Estado:** `OPEN`
- **Descripción actual:** Faltan decisiones legales y funcionales consolidadas para exportar, portar, retener, legal hold y eliminar.
- **Evidencia:** `Solicitud PRODUCT-DOC/MXMed-System-Wide-Product-Debt-Registry-01`; `docs/PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md: PP272 legal pending`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Riesgo legal, privacidad y pérdida/retención indebida.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `clinical-export`, `clinical-deletion`, `legal-hold`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** Aprobar política y excepciones con autoridad legal/privacidad.
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G7`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** La evidencia, decisión, estados y QA quedan documentados sin duplicar fuentes de verdad.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `privacy-legal-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### CLN-006 — Datos clínicos fuera de notificaciones y logs

- **ID:** `CLN-006`
- **Título:** Datos clínicos fuera de notificaciones y logs
- **Dominio:** `clinico-privacidad`
- **Clasificación:** `CONFIRMED_DEBT`
- **Prioridad:** `P0`
- **Estado:** `OPEN`
- **Descripción actual:** PP268/PP272 registran logs legacy de agenda sin saneamiento y prohíben clínica en observabilidad/evidencia.
- **Evidencia:** `docs/PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md: PP268 y PP272`; `infra/aws/README.md`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Riesgo de exposición en logs, alertas, correo o buzón.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `log-sanitization`, `notification-redaction`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G7`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** Pruebas negativas demuestran ausencia de payload, query, identificadores y contenido clínico.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `privacy-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

### RX — Recetas

#### RX-001 — Recetas: persistencia y flujo canónico incompletos

- **ID:** `RX-001`
- **Título:** Recetas: persistencia y flujo canónico incompletos
- **Dominio:** `recetas`
- **Clasificación:** `PARTIAL_IMPLEMENTATION`
- **Prioridad:** `P0`
- **Estado:** `OPEN`
- **Descripción actual:** Existe UI y persistencia como documento clínico, pero el inventario registra endpoints históricos faltantes y ausencia de CRUD dedicado homogéneo.
- **Evidencia:** `docs/expediente_inventario_existente.md`; `docs/MAPA_TOTAL_SISTEMA_MXMED.md`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** Generación, búsqueda o continuidad puede variar según entrada.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Riesgo clínico si borrador, firma o paciente no son inequívocos.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `prescription-create`, `prescription-list`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G3`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** Paciente, prescriptor, contenido, firma, save/read y auditoría funcionan sin rutas paralelas.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `prescription-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### RX-002 — PDF, descarga y regeneración de recetas

- **ID:** `RX-002`
- **Título:** PDF, descarga y regeneración de recetas
- **Dominio:** `recetas-documentos`
- **Clasificación:** `REQUIRES_AUDIT`
- **Prioridad:** `P1`
- **Estado:** `OPEN`
- **Descripción actual:** No se certifica flujo integral de PDF, descarga, regeneración, versión y revocación para recetas.
- **Evidencia:** `Solicitud PRODUCT-DOC/MXMed-System-Wide-Product-Debt-Registry-01`; `docs/catalogo-medicamentos-mxmed.md`; `docs/expediente_inventario_existente.md`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Riesgo de entregar versión obsoleta o documento sin trazabilidad.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `prescription-pdf`, `prescription-download`, `prescription-regeneration`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** Trazar generación, almacenamiento, firma, versión, download y reemisión.
- **Grupo recomendado:** `G3`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** La evidencia, decisión, estados y QA quedan documentados sin duplicar fuentes de verdad.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `prescription-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

### NOT — Notificaciones

#### NOT-001 — Buzón interno transaccional

- **ID:** `NOT-001`
- **Título:** Buzón interno transaccional
- **Dominio:** `notificaciones`
- **Clasificación:** `PARTIAL_IMPLEMENTATION`
- **Prioridad:** `P1`
- **Estado:** `OPEN`
- **Descripción actual:** MAPA_TOTAL registra UI y messages.js con semilla/localStorage, sin DB/API transaccional detectada.
- **Evidencia:** `docs/MAPA_TOTAL_SISTEMA_MXMED.md`; `assets/js/messages.js`; `index.html`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** Lectura/no lectura puede ser local y no reflejar eventos reales.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Sin riesgo adicional confirmado; validar alcance, autorización y privacidad antes de implementar.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `notification-inbox`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G4`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** Buzón persistente recibe eventos, conserva estado por usuario y audita cambios.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `notifications-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### NOT-002 — Modelo de estados y prioridades del buzón

- **ID:** `NOT-002`
- **Título:** Modelo de estados y prioridades del buzón
- **Dominio:** `notificaciones`
- **Clasificación:** `DECISION_PENDING`
- **Prioridad:** `P1`
- **Estado:** `OPEN`
- **Descripción actual:** Debe aprobarse no leída, leída, requiere acción, archivada, vencida y resuelta, con prioridad, contador, filtros, deep links y agrupación.
- **Evidencia:** `Solicitud PRODUCT-DOC/MXMed-System-Wide-Product-Debt-Registry-01`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Sin riesgo adicional confirmado; validar alcance, autorización y privacidad antes de implementar.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `notification-state`, `notification-filtering`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** Definir transiciones, expiración, agrupación, contador y navegación.
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G4`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** La evidencia, decisión, estados y QA quedan documentados sin duplicar fuentes de verdad.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `notifications-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### NOT-003 — Catálogo mínimo de triggers

- **ID:** `NOT-003`
- **Título:** Catálogo mínimo de triggers
- **Dominio:** `notificaciones-eventos`
- **Clasificación:** `DECISION_PENDING`
- **Prioridad:** `P1`
- **Estado:** `OPEN`
- **Descripción actual:** No existe catálogo canónico único que cubra reclamo, reseña, perfil, cita, pago/plan, seguridad, archivo y proveedor IA.
- **Evidencia:** `Solicitud PRODUCT-DOC/MXMed-System-Wide-Product-Debt-Registry-01`; `docs/PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md: PP01 eventos previstos`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Sin riesgo adicional confirmado; validar alcance, autorización y privacidad antes de implementar.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `notification-events`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** Aprobar evento, productor, destinatario, prioridad, obligatoriedad, deep link y dedupe.
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G4`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** Todos los triggers mínimos de la solicitud tienen entrada versionada y prueba de emisión.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `notifications-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### NOT-004 — Preferencias obligatorias y configurables

- **ID:** `NOT-004`
- **Título:** Preferencias obligatorias y configurables
- **Dominio:** `notificaciones-preferencias`
- **Clasificación:** `DECISION_PENDING`
- **Prioridad:** `P1`
- **Estado:** `OPEN`
- **Descripción actual:** Debe separarse seguridad/pagos/activación/renovación/vencimiento/gracia/legal/acceso no desactivables de comentarios/resúmenes/consejos/novedades/quiet hours configurables.
- **Evidencia:** `Solicitud PRODUCT-DOC/MXMed-System-Wide-Product-Debt-Registry-01`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Permitir silenciar seguridad o pagos puede ocultar eventos críticos.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `notification-preferences`, `quiet-hours`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** Aprobar canales, defaults, legal basis, quiet hours y override crítico.
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G4`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** La evidencia, decisión, estados y QA quedan documentados sin duplicar fuentes de verdad.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `notifications-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### NOT-005 — Entregas, reintentos, fallos y auditoría

- **ID:** `NOT-005`
- **Título:** Entregas, reintentos, fallos y auditoría
- **Dominio:** `notificaciones-entrega`
- **Clasificación:** `DECISION_PENDING`
- **Prioridad:** `P1`
- **Estado:** `OPEN`
- **Descripción actual:** Falta contrato transversal para correo/buzón/canales, retry, dead-letter, estado de entrega, preferencias y privacidad.
- **Evidencia:** `Solicitud PRODUCT-DOC/MXMed-System-Wide-Product-Debt-Registry-01`; `docs/PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md: PP268-PP272 Operations offline`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Riesgo de pérdida, duplicación o exposición de mensajes.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `notification-delivery`, `delivery-retry`, `delivery-audit`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** Definir idempotencia, retry/backoff, fallback, redacción y evidencia.
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G4`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** La evidencia, decisión, estados y QA quedan documentados sin duplicar fuentes de verdad.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `notifications-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

### SUB — Suscripciones y pagos

#### SUB-001 — Arquitectura Stripe backend cerrada

- **ID:** `SUB-001`
- **Título:** Arquitectura Stripe backend cerrada
- **Dominio:** `suscripciones-pagos`
- **Clasificación:** `CLOSED_REFERENCE_ONLY`
- **Prioridad:** `P3`
- **Estado:** `PROTECTED`
- **Descripción actual:** PaymentIntent, webhook, activación y flujo E2E DEV/local Stripe sandbox están cerrados; este registro sólo protege la referencia.
- **Evidencia:** `docs/PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md: PP220, PP230, PP231-PP244`; `api/subscriptions/index.php`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Sin riesgo adicional confirmado; validar alcance, autorización y privacidad antes de implementar.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `payment-intent`, `stripe-webhook`, `post-payment-activation`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G5`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** La evidencia, decisión, estados y QA quedan documentados sin duplicar fuentes de verdad.
- **No repetición:** No crear PaymentIntent, webhook, activación ni backend Stripe paralelo; auditar sólo uso/integración con evidencia nueva.
- **Owner funcional:** `payments-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### SUB-002 — Ciclo comercial completo posterior al pago

- **ID:** `SUB-002`
- **Título:** Ciclo comercial completo posterior al pago
- **Dominio:** `suscripciones`
- **Clasificación:** `DECISION_PENDING`
- **Prioridad:** `P1`
- **Estado:** `OPEN`
- **Descripción actual:** Contratación, renovación, pago fallido, gracia, restricción, downgrade, cancelación, comprobantes y reactivación requieren una máquina de producto única.
- **Evidencia:** `Solicitud PRODUCT-DOC/MXMed-System-Wide-Product-Debt-Registry-01`; `docs/PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md: decisiones de suscripciones`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Sin riesgo adicional confirmado; validar alcance, autorización y privacidad antes de implementar.
- **Planes afectados:** `paid`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `subscription-lifecycle`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** Aprobar estados, timers, notificaciones, acciones, comprobantes y conservación.
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G5`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** La evidencia, decisión, estados y QA quedan documentados sin duplicar fuentes de verdad.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `product-commercial-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### SUB-003 — Mensajes y datos al bloquear capacidades

- **ID:** `SUB-003`
- **Título:** Mensajes y datos al bloquear capacidades
- **Dominio:** `suscripciones-ux`
- **Clasificación:** `DECISION_PENDING`
- **Prioridad:** `P1`
- **Estado:** `OPEN`
- **Descripción actual:** Falta decidir qué mensaje, acceso read-only y preservación aplica por capacidad tras pago fallido, cancelación o downgrade.
- **Evidencia:** `Solicitud PRODUCT-DOC/MXMed-System-Wide-Product-Debt-Registry-01`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** Una función puede desaparecer, parecer rota o perder contexto.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** No debe conservar write cuando sólo corresponde lectura.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `subscription-restriction`, `data-preservation`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** Aprobar matriz estado comercial→capacidad→UX→retención.
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G5`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** La evidencia, decisión, estados y QA quedan documentados sin duplicar fuentes de verdad.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `product-commercial-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### SUB-004 — Duplicación parcial payment_route→checkout

- **ID:** `SUB-004`
- **Título:** Duplicación parcial payment_route→checkout
- **Dominio:** `suscripciones-tecnico`
- **Clasificación:** `DEFERRED_REFACTOR`
- **Prioridad:** `P3`
- **Estado:** `DEFERRED`
- **Descripción actual:** PP230 conserva con WARN un adapter que reimplementa parcialmente reglas de checkout; no duplica PaymentIntent, Stripe, webhook ni activación.
- **Evidencia:** `docs/PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md: PP230`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Sin riesgo adicional confirmado; validar alcance, autorización y privacidad antes de implementar.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `payment-route`, `checkout-bridge`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G5`
- **Acción recomendada:** Mantener protegido; evaluar extracción común sólo en refactor dedicado posterior.
- **Criterio de aceptación:** Bridge delega o comparte reglas sin relajar validación, idempotencia ni locks.
- **No repetición:** No reabrir en esta actividad ni crear motor de pagos paralelo.
- **Owner funcional:** `payments-architecture-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

### DATA — Datos e interconexiones

#### DATA-001 — Inventario pantalla→API→dato→evento

- **ID:** `DATA-001`
- **Título:** Inventario pantalla→API→dato→evento
- **Dominio:** `datos-interconexiones`
- **Clasificación:** `REQUIRES_AUDIT`
- **Prioridad:** `P1`
- **Estado:** `OPEN`
- **Descripción actual:** No existe inventario actual completo de pantalla, endpoint, controlador, servicio, repositorio, tabla, evento, notificación y auditoría.
- **Evidencia:** `Solicitud PRODUCT-DOC/MXMed-System-Wide-Product-Debt-Registry-01`; `docs/MAPA_TOTAL_SISTEMA_MXMED.md`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Sin riesgo adicional confirmado; validar alcance, autorización y privacidad antes de implementar.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** ninguna específica
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** Ejecutar la primera auditoría global read-only y registrar cada cadena sin afirmar conexión por nombre.
- **Grupo recomendado:** `G7`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** Cada función queda mapeada o marcada requires_audit con evidencia.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `data-architecture-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### DATA-002 — Scope, ownership, plan, rol y autorización por flujo

- **ID:** `DATA-002`
- **Título:** Scope, ownership, plan, rol y autorización por flujo
- **Dominio:** `datos-autorizacion`
- **Clasificación:** `REQUIRES_AUDIT`
- **Prioridad:** `P0`
- **Estado:** `OPEN`
- **Descripción actual:** No se certifica una matriz transversal de lectura/escritura y alcance por entidad, owner, plan y rol.
- **Evidencia:** `Solicitud PRODUCT-DOC/MXMed-System-Wide-Product-Debt-Registry-01`; `docs/MAPA_TOTAL_SISTEMA_MXMED.md`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Riesgo de IDOR, lectura/escritura cross-entity o bypass de plan.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `authorization`, `entity-scope`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** Trazar guards y repositorios para reads/writes; probar casos negativos.
- **Grupo recomendado:** `G7`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** La evidencia, decisión, estados y QA quedan documentados sin duplicar fuentes de verdad.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `security-product-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### DATA-003 — Idempotencia, errores, retención y downgrade

- **ID:** `DATA-003`
- **Título:** Idempotencia, errores, retención y downgrade
- **Dominio:** `datos-operacion`
- **Clasificación:** `REQUIRES_AUDIT`
- **Prioridad:** `P1`
- **Estado:** `OPEN`
- **Descripción actual:** Hay contratos por módulos, pero falta matriz global de idempotencia, códigos de error, retry, retención y acceso tras downgrade.
- **Evidencia:** `Solicitud PRODUCT-DOC/MXMed-System-Wide-Product-Debt-Registry-01`; `docs/PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md: decisiones de suscripciones`; `docs/AGENDA_ESTADO_CONSOLIDACION_Y_DEUDA_UI_MXMED.md`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Sin riesgo adicional confirmado; validar alcance, autorización y privacidad antes de implementar.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** ninguna específica
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** Inventariar cada write y cada dato con retry, dedupe, retención y post-downgrade.
- **Grupo recomendado:** `G7`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** La evidencia, decisión, estados y QA quedan documentados sin duplicar fuentes de verdad.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `data-architecture-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### DATA-004 — Zona horaria, concurrencia, offline y borradores

- **ID:** `DATA-004`
- **Título:** Zona horaria, concurrencia, offline y borradores
- **Dominio:** `datos-transversales`
- **Clasificación:** `REQUIRES_AUDIT`
- **Prioridad:** `P1`
- **Estado:** `OPEN`
- **Descripción actual:** No se ha consolidado manejo de timezone, doble clic, sesión vencida, edición simultánea, guardado parcial, offline, restore de draft y error de red.
- **Evidencia:** `Solicitud PRODUCT-DOC/MXMed-System-Wide-Product-Debt-Registry-01`; `assets/js/app.js`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** Puede haber duplicados, pérdida de captura o timestamps inconsistentes.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Sin riesgo adicional confirmado; validar alcance, autorización y privacidad antes de implementar.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** ninguna específica
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** Revisar por función crítica y documentar fuente temporal, conflict policy y recovery.
- **Grupo recomendado:** `G7`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** La evidencia, decisión, estados y QA quedan documentados sin duplicar fuentes de verdad.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `product-architecture-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

### ADM — Administración

#### ADM-001 — Backoffice de soporte, moderación y disputas

- **ID:** `ADM-001`
- **Título:** Backoffice de soporte, moderación y disputas
- **Dominio:** `administracion`
- **Clasificación:** `PARTIAL_IMPLEMENTATION`
- **Prioridad:** `P1`
- **Estado:** `OPEN`
- **Descripción actual:** Existen una superficie de operadores de Agenda, ocho rutas `/operators`, rutas de revisión de grupos y datos acotados; no forman un plano interno integral para soporte, claims, facturación, moderación, privacidad y gobierno.
- **Evidencia:** `docs/MXMED_INVENTARIO_GLOBAL_PANTALLAS_FUNCIONES_APIS_DATOS.md`; `index.html: p-ag-operadores`; `modules/agenda/routes.php`; `PRODUCT-DOC/MXMed-Operator-Control-Plane-And-Platform-Roles-Requirement-Amendment-01`
- **Archivos o decisiones relacionadas:** `docs/MXMED_REQUISITOS_PLANO_CONTROL_OPERADORES_ROLES_GOBIERNO.md`
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Riesgo de acciones administrativas sin separación, evidencia o apelación.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `support`, `moderation`, `dispute`
- **Dependencias:** PG-01, PG-02 y PG-08
- **Decisión pendiente:** Aprobar módulos, ownership operativo, límites y secuencia de implementación.
- **Auditoría requerida:** PG-10 debe reconciliar superficies existentes con los 12 módulos futuros sin convertir Agenda en consola de plataforma.
- **Grupo recomendado:** `PG-10`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** Cada módulo interno tiene owner, roles, reads/writes, datos restringidos, controles, caso, auditoría y estados UX; no quedan shells presentados como implementación integral.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `platform-operations-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### ADM-002 — Roles internos y break-glass

- **ID:** `ADM-002`
- **Título:** Roles internos y break-glass
- **Dominio:** `administracion-seguridad`
- **Clasificación:** `DECISION_PENDING`
- **Prioridad:** `P0`
- **Estado:** `OPEN`
- **Descripción actual:** Falta cerrar el catálogo canónico de roles internos, autoridad de dirección, protección del último director, prohibición de autoelevación y break-glass de producto. Los roles AWS son sólo infraestructura y `operator`/`assistant` pertenecen a Agenda.
- **Evidencia:** `docs/MXMED_REQUISITOS_PLANO_CONTROL_OPERADORES_ROLES_GOBIERNO.md`; `infra/aws/lib/constructs/security-role-factory.ts`; `modules/agenda/controllers/OperatorsController.php`
- **Archivos o decisiones relacionadas:** `docs/MXMED_INVENTARIO_GLOBAL_PANTALLAS_FUNCIONES_APIS_DATOS.md`
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Riesgo de privilegio excesivo o acceso de emergencia no trazable.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `internal-rbac`, `break-glass`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** Aprobar nombres definitivos, jerarquía, permisos, scopes, mínimo de directores, step-up, duración, aprobación y revisión posterior.
- **Auditoría requerida:** PG-01 y PG-10 deben reconciliar aliases, impedir plan-derived admin role y separar `platform_director` de `break_glass_superadmin`.
- **Grupo recomendado:** `PG-01/PG-10`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** Ningún plan concede rol interno; no existe bypass global; último director, autoelevación y emergencia tienen controles verificables.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `security-governance-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### ADM-003 — Lifecycle de operadores internos y access reviews

- **ID:** `ADM-003`
- **Título:** Lifecycle de operadores internos y access reviews
- **Dominio:** `administracion-identidad`
- **Clasificación:** `CONFIRMED_DEBT`
- **Prioridad:** `P0`
- **Estado:** `OPEN`
- **Descripción actual:** No se localizó un lifecycle interno transversal con invitación privada, verificación, MFA obligatorio, activación, access review, suspensión, revocación de sesiones y archivo preservando historial.
- **Evidencia:** `docs/MXMED_REQUISITOS_PLANO_CONTROL_OPERADORES_ROLES_GOBIERNO.md`; auditoría estática del amendment; `AUTH-004` cubre el requisito MFA sin implementación confirmada
- **Archivos o decisiones relacionadas:** `modules/agenda/controllers/OperatorsController.php` como evidencia parcial de un flujo profesional distinto
- **Efecto visible para el usuario:** Operación interna inconsistente o con acceso vigente más allá de la necesidad.
- **Riesgo de negocio:** Altas, bajas y responsabilidades no gobernadas.
- **Riesgo de datos o seguridad:** Acceso huérfano, sesión no revocada, factor ausente o privilegio temporal permanente.
- **Planes afectados:** `none-internal-role-independent`
- **Roles afectados:** `all-proposed-platform-roles`
- **Capacidades relacionadas:** `operator-invitation`, `mfa`, `access-review`, `suspension`, `revocation`
- **Dependencias:** PG-01 y PG-02
- **Decisión pendiente:** Definir mínimo de identidad, política MFA, periodicidad de revisión, inactividad y retención de historial.
- **Auditoría requerida:** Inventariar identidad/sesiones existentes y demostrar que no hay registro libre ni autoelevación.
- **Grupo recomendado:** `PG-02`
- **Acción recomendada:** Cerrar contrato de lifecycle antes de diseñar login interno.
- **Criterio de aceptación:** Cada estado/transición tiene autoridad, precondiciones, expiración, revocación, notificación y auditoría.
- **No repetición:** No reutilizar el flujo de contraseña temporal de Agenda como autenticación interna.
- **Owner funcional:** `identity-security-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### ADM-004 — Case management y sesiones asistidas de soporte

- **ID:** `ADM-004`
- **Título:** Case management y sesiones asistidas de soporte
- **Dominio:** `administracion-soporte`
- **Clasificación:** `CONFIRMED_DEBT`
- **Prioridad:** `P0`
- **Estado:** `OPEN`
- **Descripción actual:** No se localizaron case management operativo transversal ni `support_assisted_session` gobernada; los casos clínicos no deben reutilizarse por inferencia para soporte.
- **Evidencia:** `docs/MXMED_REQUISITOS_PLANO_CONTROL_OPERADORES_ROLES_GOBIERNO.md`; auditoría estática del amendment
- **Archivos o decisiones relacionadas:** `modules/clinical/db/schema_v2.sql` sólo como frontera de no reutilización
- **Efecto visible para el usuario:** Asistencia sin contexto, motivo, alcance, consentimiento o trazabilidad uniformes.
- **Riesgo de negocio:** Escalamientos y resoluciones sin owner ni evidencia.
- **Riesgo de datos o seguridad:** Suplantación silenciosa o acceso excesivo a cuenta y clínica.
- **Planes afectados:** `all-customers-without-entitlement-effect`
- **Roles afectados:** `support_advisor`, `operations_manager`, `privacy_security_officer`
- **Capacidades relacionadas:** `platform-cases`, `assisted-session`, `escalation`
- **Dependencias:** PG-02 y PG-08
- **Decisión pendiente:** Definir estados, SLA, consentimiento, duración, scopes y separación del acceso clínico extraordinario.
- **Auditoría requerida:** Mapear flujos actuales de soporte y probar que ninguna sesión equivale a impersonation silenciosa.
- **Grupo recomendado:** `PG-10`
- **Acción recomendada:** Cerrar modelo de caso antes de habilitar acciones asistidas.
- **Criterio de aceptación:** Toda acción sensible referencia caso/motivo; la sesión expira, muestra banner, enmascara datos y puede revocarse.
- **No repetición:** No confundir `clinical_cases` con `platform_cases`.
- **Owner funcional:** `support-operations-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### ADM-005 — Doble aprobación y separación de funciones

- **ID:** `ADM-005`
- **Título:** Doble aprobación y separación de funciones
- **Dominio:** `administracion-gobierno`
- **Clasificación:** `CONFIRMED_DEBT`
- **Prioridad:** `P0`
- **Estado:** `OPEN`
- **Descripción actual:** No se localizó un contrato transversal implementado de initiator/approver, expiración de aprobaciones, riesgo R0–R3 y separación de funciones para acciones administrativas.
- **Evidencia:** `docs/MXMED_REQUISITOS_PLANO_CONTROL_OPERADORES_ROLES_GOBIERNO.md`; auditoría estática del amendment
- **Archivos o decisiones relacionadas:** `DATA-002`, `CAP-008`
- **Efecto visible para el usuario:** Acciones críticas podrían depender de una sola identidad sin revisión independiente.
- **Riesgo de negocio:** Fraude, error irreversible o indisponibilidad organizacional.
- **Riesgo de datos o seguridad:** Autoaprobación, elevación propia, exportación o override fuera de propósito.
- **Planes afectados:** `none-internal-role-independent`
- **Roles afectados:** `platform_director`, `platform_admin`, `privacy_security_officer`, `billing_subscription_operator`
- **Capacidades relacionadas:** `dual-approval`, `risk-tiering`, `separation-of-duties`
- **Dependencias:** PG-01, PG-06 y PG-08
- **Decisión pendiente:** Determinar qué R3 siempre exige dos personas y qué contingencia evita perder recuperación.
- **Auditoría requerida:** Clasificar acciones administrativas y verificar frontend/backend equivalentes por caso negativo.
- **Grupo recomendado:** `PG-08`
- **Acción recomendada:** Aprobar matriz de riesgo y conflicto de funciones antes de endpoints mutables.
- **Criterio de aceptación:** Iniciador y aprobador son distintos; la aprobación es específica, vigente, auditada y no reutilizable.
- **No repetición:** No implementar un permiso global ni excepción silenciosa.
- **Owner funcional:** `security-governance-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### ADM-006 — Auditoría administrativa, masking y acceso extraordinario

- **ID:** `ADM-006`
- **Título:** Auditoría administrativa, masking y acceso extraordinario
- **Dominio:** `administracion-privacidad`
- **Clasificación:** `CONFIRMED_DEBT`
- **Prioridad:** `P0`
- **Estado:** `OPEN`
- **Descripción actual:** Existe auditoría acotada de Agenda, pero no se localizó un evento administrativo transversal inmutable con scope/caso/aprobación, masking de campo ni gobierno del acceso clínico extraordinario.
- **Evidencia:** `modules/agenda/db/operators_phase1.sql`; `docs/MXMED_REQUISITOS_PLANO_CONTROL_OPERADORES_ROLES_GOBIERNO.md`
- **Archivos o decisiones relacionadas:** `PRIV-001`, `PRIV-002`, `CLN-005`, `CLN-006`
- **Efecto visible para el usuario:** Accesos o cambios podrían no tener explicación verificable y proporcional.
- **Riesgo de negocio:** Incumplimiento, investigación incompleta o pérdida de confianza.
- **Riesgo de datos o seguridad:** Exposición de identidad, pagos, secretos o información clínica; log manipulable.
- **Planes afectados:** `all-without-entitlement-effect`
- **Roles afectados:** `all-proposed-platform-roles`
- **Capacidades relacionadas:** `administrative-audit`, `field-masking`, `extraordinary-clinical-access`
- **Dependencias:** PG-02 y PG-08
- **Decisión pendiente:** Definir retention classes, pseudonimización, masking, acceso extraordinario y autoridad de exportación.
- **Auditoría requerida:** Trazar campos sensibles, eventos y stores; probar que actor no edita su rastro.
- **Grupo recomendado:** `PG-08`
- **Acción recomendada:** Cerrar el contrato de audit/masking antes de ampliar visibilidad de datos.
- **Criterio de aceptación:** Cada acción sensible registra los campos mínimos y excluye contraseñas, tokens, secretos, tarjetas y contenido clínico completo.
- **No repetición:** No generalizar `agenda_operator_audit_events` a auditoría de plataforma.
- **Owner funcional:** `privacy-security-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### ADM-007 — Colas y notificaciones internas de operadores

- **ID:** `ADM-007`
- **Título:** Colas y notificaciones internas de operadores
- **Dominio:** `administracion-operaciones`
- **Clasificación:** `CONFIRMED_DEBT`
- **Prioridad:** `P1`
- **Estado:** `OPEN`
- **Descripción actual:** El buzón de usuario y los eventos actuales no implementan una cola interna de tareas, asignaciones, aprobaciones, incidentes y alertas con SLA y escalamiento.
- **Evidencia:** `docs/MXMED_INVENTARIO_GLOBAL_PANTALLAS_FUNCIONES_APIS_DATOS.md`; `docs/MXMED_REQUISITOS_PLANO_CONTROL_OPERADORES_ROLES_GOBIERNO.md`
- **Archivos o decisiones relacionadas:** `NOT-001` a `NOT-005`
- **Efecto visible para el usuario:** Casos y excepciones podrían carecer de responsable o seguimiento oportuno.
- **Riesgo de negocio:** Incumplimiento de SLA y trabajo operativo perdido.
- **Riesgo de datos o seguridad:** Datos sensibles expuestos en tarjetas o alertas sin scope.
- **Planes afectados:** `none-internal-role-independent`
- **Roles afectados:** `operations_manager`, `support_advisor`, `profile_claim_reviewer`, `content_moderator`, `privacy_security_officer`
- **Capacidades relacionadas:** `operator-queue`, `approval-task`, `internal-alert`
- **Dependencias:** PG-02, PG-05 y PG-08
- **Decisión pendiente:** Definir prioridad, SLA, expiración, escalamiento, delivery y retención.
- **Auditoría requerida:** Separar buzón de usuario y plano interno; mapear productores/consumidores sin afirmar persistencia.
- **Grupo recomendado:** `PG-05`
- **Acción recomendada:** Cerrar catálogo de tareas y estados antes de diseñar dashboard.
- **Criterio de aceptación:** Cada tarea tiene owner, prioridad, estado, deep link al caso, expiración, historial y tarjeta sin datos clínicos.
- **No repetición:** No reutilizar notificaciones del usuario como autorización operativa.
- **Owner funcional:** `operations-notification-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### ADM-008 — UX y accesibilidad de la consola operativa

- **ID:** `ADM-008`
- **Título:** UX y accesibilidad de la consola operativa
- **Dominio:** `administracion-ux`
- **Clasificación:** `DECISION_PENDING`
- **Prioridad:** `P1`
- **Estado:** `OPEN`
- **Descripción actual:** Faltan decisiones de arquitectura UX para navegación por rol, tablas, filtros seguros, estados, acciones críticas, masking, read-only, banners privilegiados, accesibilidad y alcance móvil.
- **Evidencia:** `docs/MXMED_REQUISITOS_PLANO_CONTROL_OPERADORES_ROLES_GOBIERNO.md`; `UX-003` a `UX-005`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** Operación propensa a error, ambigua o inaccesible.
- **Riesgo de negocio:** Acciones equivocadas, baja productividad y exclusión de operadores.
- **Riesgo de datos o seguridad:** Confusión de scope, exposición por filtros persistidos o acción crítica poco distinguible.
- **Planes afectados:** `none-internal-role-independent`
- **Roles afectados:** `all-proposed-platform-roles`
- **Capacidades relacionadas:** `operator-console-ux`, `privileged-banners`, `accessible-operations`
- **Dependencias:** PG-01, PG-02 y PG-08
- **Decisión pendiente:** Aprobar arquitectura, densidad, responsive, desktop-first y política móvil.
- **Auditoría requerida:** Mapear estados y restricciones por módulo antes de diseño final.
- **Grupo recomendado:** `PG-09`
- **Acción recomendada:** Diseñar sólo después de cerrar roles, scopes y riesgos.
- **Criterio de aceptación:** Los 12 módulos cubren estados, teclado, lector, foco, contraste, permisos insuficientes y confirmaciones reforzadas.
- **No repetición:** No crear bocetos ni presentar shells actuales como consola final.
- **Owner funcional:** `product-design-accessibility-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

### AI — Inteligencia artificial

#### AI-001 — IA Profesional: plan, cuota y presupuesto

- **ID:** `AI-001`
- **Título:** IA Profesional: plan, cuota y presupuesto
- **Dominio:** `inteligencia-artificial`
- **Clasificación:** `DECISION_PENDING`
- **Prioridad:** `P1`
- **Estado:** `OPEN`
- **Descripción actual:** La IA se reserva para Profesional en la dirección actual, pero faltan cuotas, presupuesto, límites y degradación aprobados.
- **Evidencia:** `Solicitud PRODUCT-DOC/MXMed-System-Wide-Product-Debt-Registry-01`; `docs/PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md: PP01 capacidades IA futuras`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Sin riesgo adicional confirmado; validar alcance, autorización y privacidad antes de implementar.
- **Planes afectados:** `professional`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `ai-assistant`, `ai-voice`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** Aprobar casos de uso, cuota, presupuesto, fallback y mensajes.
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G8`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** La evidencia, decisión, estados y QA quedan documentados sin duplicar fuentes de verdad.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `ai-product-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### AI-002 — IA como borrador con confirmación explícita

- **ID:** `AI-002`
- **Título:** IA como borrador con confirmación explícita
- **Dominio:** `inteligencia-artificial`
- **Clasificación:** `DECISION_PENDING`
- **Prioridad:** `P0`
- **Estado:** `OPEN`
- **Descripción actual:** Debe prohibirse write autónomo, uso patient-facing inicial y decisión clínica automática; faltan contrato de voz, privacidad, proveedor, degradación y logs.
- **Evidencia:** `Solicitud PRODUCT-DOC/MXMed-System-Wide-Product-Debt-Registry-01`; `docs/PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md: PP01 IA futura`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Riesgo de acción no consentida, exposición o recomendación clínica no supervisada.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `ai-draft`, `ai-confirmation`, `ai-provider`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** Aprobar human-in-the-loop, data boundary, retention, audit, fail-closed y vendor terms.
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G8`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** La evidencia, decisión, estados y QA quedan documentados sin duplicar fuentes de verdad.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `ai-governance-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

### PRIV — Privacidad

#### PRIV-001 — Privacidad, retención, eliminación y analítica

- **ID:** `PRIV-001`
- **Título:** Privacidad, retención, eliminación y analítica
- **Dominio:** `privacidad`
- **Clasificación:** `DECISION_PENDING`
- **Prioridad:** `P0`
- **Estado:** `OPEN`
- **Descripción actual:** PP272 deja pendientes retención, eliminación, legal hold, portabilidad, consentimientos y proveedores; falta política de analítica por dominio.
- **Evidencia:** `Solicitud PRODUCT-DOC/MXMed-System-Wide-Product-Debt-Registry-01`; `docs/PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md: PP272`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Riesgo legal y de conservación o uso no autorizado.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `retention`, `deletion`, `analytics-consent`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** Aprobar inventario de datos, propósito, base, plazo, borrado, export y excepciones.
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G7`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** La evidencia, decisión, estados y QA quedan documentados sin duplicar fuentes de verdad.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `privacy-legal-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### PRIV-002 — Minimización de logs, métricas y evidencia

- **ID:** `PRIV-002`
- **Título:** Minimización de logs, métricas y evidencia
- **Dominio:** `privacidad-observabilidad`
- **Clasificación:** `CONFIRMED_DEBT`
- **Prioridad:** `P0`
- **Estado:** `OPEN`
- **Descripción actual:** PP268/PP272 bloquean observabilidad clínica por logs legacy no saneados y prohíben datos sensibles como dimensiones/evidencia.
- **Evidencia:** `docs/PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md: PP268, PP272`; `infra/aws/README.md`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Riesgo de filtrar identificadores, payloads, queries o clínica.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `log-sanitization`, `safe-metrics`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G7`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** Allowlist de campos y pruebas negativas cubren logs, alertas, traces y evidencias.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `privacy-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

### DOC — Documentación

#### DOC-001 — Índice canónico de documentos y contratos vigentes

- **ID:** `DOC-001`
- **Título:** Índice canónico de documentos y contratos vigentes
- **Dominio:** `documentacion`
- **Clasificación:** `REQUIRES_AUDIT`
- **Prioridad:** `P2`
- **Estado:** `OPEN`
- **Descripción actual:** Se localizaron 112 Markdown; no hay índice actual que marque canónico, histórico, superseded, duplicado o sólo QA.
- **Evidencia:** `Solicitud PRODUCT-DOC/MXMed-System-Wide-Product-Debt-Registry-01`; `docs/PLAN_MAESTRO_MXMED.md`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Sin riesgo adicional confirmado; validar alcance, autorización y privacidad antes de implementar.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** ninguna específica
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** Clasificar cada Markdown por propósito, autoridad, vigencia y reemplazo.
- **Grupo recomendado:** `G8`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** Existe índice versionado sin borrar historia y cada fuente canónica está identificada.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `documentation-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### DOC-002 — Contradicciones y siguientes pasos históricos

- **ID:** `DOC-002`
- **Título:** Contradicciones y siguientes pasos históricos
- **Dominio:** `documentacion`
- **Clasificación:** `REQUIRES_AUDIT`
- **Prioridad:** `P1`
- **Estado:** `OPEN`
- **Descripción actual:** El contrato maestro contiene muchas recomendaciones históricas; no se consideran deuda vigente sin reconciliar con decisiones posteriores.
- **Evidencia:** `Solicitud PRODUCT-DOC/MXMed-System-Wide-Product-Debt-Registry-01`; `docs/PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Sin riesgo adicional confirmado; validar alcance, autorización y privacidad antes de implementar.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** ninguna específica
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** Relacionar cada TODO/WARN/siguiente paso con su decisión de cierre posterior o deuda aún abierta.
- **Grupo recomendado:** `G8`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** La evidencia, decisión, estados y QA quedan documentados sin duplicar fuentes de verdad.
- **No repetición:** No reabrir una microfase cerrada por una coincidencia textual antigua.
- **Owner funcional:** `documentation-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### DOC-003 — Gobernanza del registro de deuda

- **ID:** `DOC-003`
- **Título:** Gobernanza del registro de deuda
- **Dominio:** `documentacion`
- **Clasificación:** `DECISION_PENDING`
- **Prioridad:** `P1`
- **Estado:** `OPEN`
- **Descripción actual:** El nuevo registro necesita intake, IDs no reutilizables, evidencia, owner opaco, aceptación y cierre verificable.
- **Evidencia:** `Solicitud PRODUCT-DOC/MXMed-System-Wide-Product-Debt-Registry-01`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Sin riesgo adicional confirmado; validar alcance, autorización y privacidad antes de implementar.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** ninguna específica
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** Adoptar MXMED_PRODUCT_DEBT_REGISTRY_V1 como única fuente canónica de deuda de producto.
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G8`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** Altas/cambios/cierres siguen el proceso y el JSON refleja el Markdown.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `product-operations-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### DOC-004 — Ciclo AWS 24/24 cerrado

- **ID:** `DOC-004`
- **Título:** Ciclo AWS 24/24 cerrado
- **Dominio:** `documentacion-referencia`
- **Clasificación:** `CLOSED_REFERENCE_ONLY`
- **Prioridad:** `P3`
- **Estado:** `PROTECTED`
- **Descripción actual:** PP272 cerró 24/24 offline; despliegue real no inició y tráfico sigue NO-GO.
- **Evidencia:** `docs/PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md: PP272`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Sin riesgo adicional confirmado; validar alcance, autorización y privacidad antes de implementar.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** ninguna específica
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G7`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** La evidencia, decisión, estados y QA quedan documentados sin duplicar fuentes de verdad.
- **No repetición:** No llamar Microfase 25 ni reabrir foundation AWS desde auditorías de producto.
- **Owner funcional:** `platform-architecture-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### DOC-005 — No existe Microfase 25

- **ID:** `DOC-005`
- **Título:** No existe Microfase 25
- **Dominio:** `documentacion-referencia`
- **Clasificación:** `CLOSED_REFERENCE_ONLY`
- **Prioridad:** `P3`
- **Estado:** `PROTECTED`
- **Descripción actual:** La continuidad posterior a AWS usa actividades PRODUCT-DOC/PRODUCT-AUDIT y etapas DEPLOY-OPS fuera del contador.
- **Evidencia:** `docs/PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md: PP272`; `Solicitud PRODUCT-DOC/MXMed-System-Wide-Product-Debt-Registry-01`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Sin riesgo adicional confirmado; validar alcance, autorización y privacidad antes de implementar.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** ninguna específica
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G8`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** La evidencia, decisión, estados y QA quedan documentados sin duplicar fuentes de verdad.
- **No repetición:** No crear ni inferir Microfase 25.
- **Owner funcional:** `product-operations-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### DOC-006 — Runbooks internos del plano de control

- **ID:** `DOC-006`
- **Título:** Runbooks internos del plano de control
- **Dominio:** `documentacion-operaciones`
- **Clasificación:** `CONFIRMED_DEBT`
- **Prioridad:** `P1`
- **Estado:** `OPEN`
- **Descripción actual:** No se localizaron runbooks canónicos para altas/bajas de personal, pérdida de MFA, access review, suspensión, break-glass, sesión asistida, doble aprobación, incidentes y recuperación del último director.
- **Evidencia:** `docs/MXMED_REQUISITOS_PLANO_CONTROL_OPERADORES_ROLES_GOBIERNO.md`; auditoría estática del amendment
- **Archivos o decisiones relacionadas:** `ADM-002` a `ADM-006`
- **Efecto visible para el usuario:** Respuesta operativa inconsistente durante soporte o incidentes.
- **Riesgo de negocio:** Dependencia de conocimiento informal y recuperación tardía.
- **Riesgo de datos o seguridad:** Acciones privilegiadas sin secuencia, evidencia, reversión o revisión uniforme.
- **Planes afectados:** `none-internal-role-independent`
- **Roles afectados:** `platform_director`, `platform_admin`, `operations_manager`, `privacy_security_officer`
- **Capacidades relacionadas:** `operator-runbooks`, `break-glass-runbook`, `access-review-runbook`
- **Dependencias:** PG-01, PG-02, PG-08 y PG-10
- **Decisión pendiente:** Definir owner, periodicidad de prueba, escalamiento, contingencias y evidencia por runbook.
- **Auditoría requerida:** Inventariar procedimientos existentes y proteger las decisiones AWS/Stripe sin mezclarlas con operación de producto.
- **Grupo recomendado:** `PG-10`
- **Acción recomendada:** Redactar runbooks después de cerrar roles, riesgos, sesiones y datos.
- **Criterio de aceptación:** Cada evento operativo crítico tiene precondiciones, responsables, pasos, rollback/recovery, evidencia y revisión.
- **No repetición:** No ejecutar despliegues, proveedores ni sesiones privilegiadas para documentar.
- **Owner funcional:** `platform-operations-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

### RUNTIME — Runtime y despliegue

#### RUNTIME-001 — Readiness responde 503

- **ID:** `RUNTIME-001`
- **Título:** Readiness responde 503
- **Dominio:** `runtime`
- **Clasificación:** `RUNTIME_GATE`
- **Prioridad:** `P0`
- **Estado:** `GATED`
- **Descripción actual:** El endpoint /readyz devuelve readiness_not_integrated con HTTP 503.
- **Evidencia:** `infra/aws/runtime/app/health/readyz.php`; `infra/aws/runtime/app/README.md`; `docs/PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md: PP272`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El target no puede considerarse listo para tráfico.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Sin riesgo adicional confirmado; validar alcance, autorización y privacidad antes de implementar.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `runtime-readiness`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G7`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** Checks acotados MySQL/Valkey, respuesta sanitizada y HTTP 200 probados.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `runtime-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### RUNTIME-002 — Retorno Stripe productivo ausente

- **ID:** `RUNTIME-002`
- **Título:** Retorno Stripe productivo ausente
- **Dominio:** `runtime`
- **Clasificación:** `RUNTIME_GATE`
- **Prioridad:** `P0`
- **Estado:** `GATED`
- **Descripción actual:** La ruta /subscriptions/stripe-return es contractual pero no existe como entrypoint versionado.
- **Evidencia:** `docs/PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md: PP266/PP272`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El retorno seguro del Payment Element no está disponible para tráfico.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Sin riesgo adicional confirmado; validar alcance, autorización y privacidad antes de implementar.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `stripe-return`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G5`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** Ruta, scrub, headers, CSP y QA end-to-end aprobados.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `payments-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### RUNTIME-003 — Fingerprinting de assets incompleto

- **ID:** `RUNTIME-003`
- **Título:** Fingerprinting de assets incompleto
- **Dominio:** `runtime`
- **Clasificación:** `RUNTIME_GATE`
- **Prioridad:** `P1`
- **Estado:** `GATED`
- **Descripción actual:** Edge mantiene cache de assets en TTL 0 hasta fingerprinting inmutable.
- **Evidencia:** `docs/PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md: PP266-PP272`; `infra/aws/README.md`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** No se puede habilitar caching público inmutable de forma segura.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Sin riesgo adicional confirmado; validar alcance, autorización y privacidad antes de implementar.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `asset-fingerprinting`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G8`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** Nombres versionados, invalidación y cache policy probados.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `frontend-platform-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### RUNTIME-004 — Maps y CSP legacy pendientes

- **ID:** `RUNTIME-004`
- **Título:** Maps y CSP legacy pendientes
- **Dominio:** `runtime`
- **Clasificación:** `RUNTIME_GATE`
- **Prioridad:** `P1`
- **Estado:** `GATED`
- **Descripción actual:** PP272 registra dependencias legacy de Maps/CSP antes de tráfico.
- **Evidencia:** `docs/PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md: PP272`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Sin riesgo adicional confirmado; validar alcance, autorización y privacidad antes de implementar.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `maps`, `content-security-policy`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G6`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** Mapa opera en modo aprobado y CSP soporta Stripe sin relajación insegura.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `security-frontend-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### RUNTIME-005 — Logs legacy de Agenda sin saneamiento

- **ID:** `RUNTIME-005`
- **Título:** Logs legacy de Agenda sin saneamiento
- **Dominio:** `runtime`
- **Clasificación:** `RUNTIME_GATE`
- **Prioridad:** `P0`
- **Estado:** `GATED`
- **Descripción actual:** Operations documenta campos/IDs/mensajes legacy no aprobados para observabilidad.
- **Evidencia:** `docs/PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md: PP268/PP272`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Riesgo de datos sensibles en logs.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `agenda-log-sanitization`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G7`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** Source sanitization y pruebas negativas aprobadas.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `privacy-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### RUNTIME-006 — Application metric emission no integrada

- **ID:** `RUNTIME-006`
- **Título:** Application metric emission no integrada
- **Dominio:** `runtime`
- **Clasificación:** `RUNTIME_GATE`
- **Prioridad:** `P1`
- **Estado:** `GATED`
- **Descripción actual:** El catálogo de métricas existe offline, pero la emisión de aplicación no está integrada.
- **Evidencia:** `docs/PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md: PP268/PP272`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Sin riesgo adicional confirmado; validar alcance, autorización y privacidad antes de implementar.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `application-metrics`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G7`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** Métricas allowlisted se emiten sin datos personales/clínicos y alarmas las reciben.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `operations-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### RUNTIME-007 — Migrator fail-closed

- **ID:** `RUNTIME-007`
- **Título:** Migrator fail-closed
- **Dominio:** `runtime`
- **Clasificación:** `RUNTIME_GATE`
- **Prioridad:** `P0`
- **Estado:** `GATED`
- **Descripción actual:** Compute conserva migration command fail-closed y no hay ejecución productiva aprobada.
- **Evidencia:** `docs/PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md: PP262-PP272`; `infra/aws/README.md`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Sin riesgo adicional confirmado; validar alcance, autorización y privacidad antes de implementar.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `database-migration`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G7`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** Comando versionado, least privilege, rollback y ledger probados antes de carga.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `data-platform-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### RUNTIME-008 — Usuario DB mxmed_app no creado

- **ID:** `RUNTIME-008`
- **Título:** Usuario DB mxmed_app no creado
- **Dominio:** `runtime`
- **Clasificación:** `RUNTIME_GATE`
- **Prioridad:** `P0`
- **Estado:** `GATED`
- **Descripción actual:** El contrato existe, pero la cuenta/DB real no está desplegada y el usuario no existe.
- **Evidencia:** `docs/PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md: PP272`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** La aplicación no debe usar master credentials.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `application-db-user`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G7`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** Usuario least-privilege creado, secreto AWSCURRENT y master no usado.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `data-platform-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### RUNTIME-009 — Secretos reales no configurados

- **ID:** `RUNTIME-009`
- **Título:** Secretos reales no configurados
- **Dominio:** `runtime`
- **Clasificación:** `RUNTIME_GATE`
- **Prioridad:** `P0`
- **Estado:** `GATED`
- **Descripción actual:** Los contenedores/contratos de secretos existen offline; valores reales no se han configurado.
- **Evidencia:** `docs/PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md: PP272`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Sin secretos válidos la app debe fallar cerrada; nunca usar placeholders productivos.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `runtime-secrets`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G7`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** Inventario aprobado, AWSCURRENT, rotación y no exposición validados.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `security-platform-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### RUNTIME-010 — Dominio y certificados pendientes

- **ID:** `RUNTIME-010`
- **Título:** Dominio y certificados pendientes
- **Dominio:** `runtime`
- **Clasificación:** `RUNTIME_GATE`
- **Prioridad:** `P0`
- **Estado:** `GATED`
- **Descripción actual:** Dominio, hosted zone, certificados viewer/origin, header y DNS no están aprobados ni desplegados.
- **Evidencia:** `docs/PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md: PP272`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Sin riesgo adicional confirmado; validar alcance, autorización y privacidad antes de implementar.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `public-domain`, `tls-certificates`, `dns`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G6`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** Certificados ISSUED, origin 403, CloudFront válido y GO manual de DNS.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `edge-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### RUNTIME-011 — Backup y restore reales no ejecutados

- **ID:** `RUNTIME-011`
- **Título:** Backup y restore reales no ejecutados
- **Dominio:** `runtime`
- **Clasificación:** `RUNTIME_GATE`
- **Prioridad:** `P0`
- **Estado:** `GATED`
- **Descripción actual:** IaC Backup/DR está sintetizada offline, pero no hay vault, recovery point ni restore real validado.
- **Evidencia:** `docs/PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md: PP270-PP272`; `infra/aws/README.md`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** No hay evidencia operativa de recuperación.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `backup`, `restore`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G7`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** Recovery point y restore a recurso nuevo con validación/cleanup/runbook.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `backup-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### RUNTIME-012 — Cost Explorer y tags no verificados

- **ID:** `RUNTIME-012`
- **Título:** Cost Explorer y tags no verificados
- **Dominio:** `runtime`
- **Clasificación:** `RUNTIME_GATE`
- **Prioridad:** `P0`
- **Estado:** `GATED`
- **Descripción actual:** La cuenta no se consultó; tags de asignación, Cost Explorer, budgets y medición real no están activos.
- **Evidencia:** `docs/PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md: PP272`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** No se puede aprobar burn rate ni presupuesto real.
- **Riesgo de datos o seguridad:** Sin riesgo adicional confirmado; validar alcance, autorización y privacidad antes de implementar.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `cost-observability`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G7`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** Baseline, tags activos, filters y budgets aprobados en Stage 0/2.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `cost-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### RUNTIME-013 — Despliegue AWS y tráfico en NO-GO

- **ID:** `RUNTIME-013`
- **Título:** Despliegue AWS y tráfico en NO-GO
- **Dominio:** `runtime`
- **Clasificación:** `RUNTIME_GATE`
- **Prioridad:** `P0`
- **Estado:** `GATED`
- **Descripción actual:** Foundation offline está cerrada, pero recursos desplegados=0 y tráfico público permanece bloqueado.
- **Evidencia:** `docs/PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md: PP272`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** No existe servicio público AWS autorizado.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Sin riesgo adicional confirmado; validar alcance, autorización y privacidad antes de implementar.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `aws-deployment`, `public-traffic`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G7`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** Stages controlados cierran cuenta, runtime, data, backup, operations, cost y cutover.
- **No repetición:** No desplegar ni habilitar tráfico desde actividad documental/auditoría.
- **Owner funcional:** `release-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

### TECH — Refactors técnicos

#### TECH-001 — Convergencia Clinical v1/v2 y fuente paciente

- **ID:** `TECH-001`
- **Título:** Convergencia Clinical v1/v2 y fuente paciente
- **Dominio:** `deuda-tecnica`
- **Clasificación:** `DEFERRED_REFACTOR`
- **Prioridad:** `P1`
- **Estado:** `DEFERRED`
- **Descripción actual:** MAPA_TOTAL registra coexistencia v1/v2 y duplicación histórica de identidad paciente.
- **Evidencia:** `docs/MAPA_TOTAL_SISTEMA_MXMED.md`; `docs/clinical/DECISION_FUENTES_DE_VERDAD.md`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Riesgo de IDs y modelos paralelos.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `clinical-contracts`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G7`
- **Acción recomendada:** Mantener fuente canónica y planificar adapters/migración con QA.
- **Criterio de aceptación:** No quedan writers paralelos ni identidad clínica duplicada.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `clinical-architecture-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### TECH-002 — Wrappers API no homogéneos

- **ID:** `TECH-002`
- **Título:** Wrappers API no homogéneos
- **Dominio:** `deuda-tecnica`
- **Clasificación:** `DEFERRED_REFACTOR`
- **Prioridad:** `P2`
- **Estado:** `DEFERRED`
- **Descripción actual:** Agenda/Patients usan wrapper más uniforme; endpoints clínicos y verify legacy difieren.
- **Evidencia:** `docs/MAPA_TOTAL_SISTEMA_MXMED.md`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** Errores y metadatos pueden variar entre módulos.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Sin riesgo adicional confirmado; validar alcance, autorización y privacidad antes de implementar.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** `api-contract`
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G7`
- **Acción recomendada:** Inventariar versiones y normalizar con compatibilidad en refactor separado.
- **Criterio de aceptación:** La evidencia, decisión, estados y QA quedan documentados sin duplicar fuentes de verdad.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `api-architecture-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### TECH-003 — Schemas y documentación Agenda divergentes

- **ID:** `TECH-003`
- **Título:** Schemas y documentación Agenda divergentes
- **Dominio:** `deuda-tecnica`
- **Clasificación:** `DEFERRED_REFACTOR`
- **Prioridad:** `P2`
- **Estado:** `DEFERRED`
- **Descripción actual:** MAPA_TOTAL registra dos ready_schema de Agenda con diferencias y README desactualizado en partes.
- **Evidencia:** `docs/MAPA_TOTAL_SISTEMA_MXMED.md`; `modules/agenda/README.md`; `modules/agenda/db/README.md`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Riesgo operativo al preparar ambientes con fuentes distintas.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** ninguna específica
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** evidence-scope-review
- **Grupo recomendado:** `G3`
- **Acción recomendada:** Definir schema canónico y marcar histórico sin ejecutar migraciones en esta actividad.
- **Criterio de aceptación:** La evidencia, decisión, estados y QA quedan documentados sin duplicar fuentes de verdad.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `agenda-architecture-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

### QA — Calidad

#### QA-001 — Auditoría global de cobertura antes de G1

- **ID:** `QA-001`
- **Título:** Auditoría global de cobertura antes de G1
- **Dominio:** `calidad`
- **Clasificación:** `REQUIRES_AUDIT`
- **Prioridad:** `P1`
- **Estado:** `OPEN`
- **Descripción actual:** La cobertura completa debe empezar por inventario global, no por una auditoría detallada de dominio.
- **Evidencia:** `Solicitud PRODUCT-DOC/MXMed-System-Wide-Product-Debt-Registry-01`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Sin riesgo adicional confirmado; validar alcance, autorización y privacidad antes de implementar.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** ninguna específica
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** PRODUCT-AUDIT/MXMed-System-Wide-Screen-Function-Api-Data-Inventory-01.
- **Grupo recomendado:** `GLOBAL`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** Inventario completo propone ajustes de grupos y sólo entonces un contador.
- **No repetición:** No iniciar G1 antes de cerrar el inventario global.
- **Owner funcional:** `qa-product-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### QA-002 — Matriz de estados de error y casos borde

- **ID:** `QA-002`
- **Título:** Matriz de estados de error y casos borde
- **Dominio:** `calidad`
- **Clasificación:** `REQUIRES_AUDIT`
- **Prioridad:** `P1`
- **Estado:** `OPEN`
- **Descripción actual:** No se certifica cobertura uniforme de validación, 401/403/404/409/422/5xx, timeout, retry y recovery.
- **Evidencia:** `Solicitud PRODUCT-DOC/MXMed-System-Wide-Product-Debt-Registry-01`; `docs/qa/README.md`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Sin riesgo adicional confirmado; validar alcance, autorización y privacidad antes de implementar.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** ninguna específica
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** Mapear cada función crítica a error esperado, copy, telemetría segura y recuperación.
- **Grupo recomendado:** `G8`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** La evidencia, decisión, estados y QA quedan documentados sin duplicar fuentes de verdad.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `qa-product-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### QA-003 — Fixtures, mocks y demo fuera de producción

- **ID:** `QA-003`
- **Título:** Fixtures, mocks y demo fuera de producción
- **Dominio:** `calidad-seguridad`
- **Clasificación:** `REQUIRES_AUDIT`
- **Prioridad:** `P0`
- **Estado:** `OPEN`
- **Descripción actual:** El repo contiene fixtures/mocks/localStorage de desarrollo; decisiones de suscripciones cierran parte del hardening, pero falta inventario global.
- **Evidencia:** `Solicitud PRODUCT-DOC/MXMed-System-Wide-Product-Debt-Registry-01`; `docs/PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md: PP178-PP182`; `public-agenda.html`; `index.html`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Riesgo de usar datos o rutas demo como autoridad productiva.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** ninguna específica
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** Inventariar flags, defaults, rutas, datos semilla y pruebas negativas de producción.
- **Grupo recomendado:** `G7`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** Cada fixture está eliminado o fail-closed con evidencia negativa.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `security-qa-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

#### QA-004 — Integridad cruzada entre módulos

- **ID:** `QA-004`
- **Título:** Integridad cruzada entre módulos
- **Dominio:** `calidad-datos`
- **Clasificación:** `REQUIRES_AUDIT`
- **Prioridad:** `P0`
- **Estado:** `OPEN`
- **Descripción actual:** MAPA_TOTAL propone validaciones paciente/encounter/documento/resultado, pero no hay matriz global cerrada.
- **Evidencia:** `docs/MAPA_TOTAL_SISTEMA_MXMED.md`; `Solicitud PRODUCT-DOC/MXMed-System-Wide-Product-Debt-Registry-01`
- **Archivos o decisiones relacionadas:** ninguna adicional
- **Efecto visible para el usuario:** El efecto exacto debe confirmarse en la auditoría del flujo y no se infiere por presencia de UI.
- **Riesgo de negocio:** Priorización, alcance o expectativa de producto inconsistentes.
- **Riesgo de datos o seguridad:** Riesgo de referencias huérfanas o cross-patient.
- **Planes afectados:** `all`
- **Roles afectados:** `product-user`, `platform-operator`
- **Capacidades relacionadas:** ninguna específica
- **Dependencias:** inventario global y decisión del owner
- **Decisión pendiente:** none-recorded
- **Auditoría requerida:** Definir invariantes, fixtures sintéticos y pruebas de integridad sin leer datos reales.
- **Grupo recomendado:** `G7`
- **Acción recomendada:** Inventariar evidencia, cerrar decisión y crear trabajo separado si corresponde.
- **Criterio de aceptación:** Invariantes críticas tienen prueba automatizada o gate explícito.
- **No repetición:** No implementar ni reabrir decisiones cerradas desde este registro.
- **Owner funcional:** `data-qa-owner`
- **Fecha de incorporación:** `2026-07-17`
- **Última revisión:** `2026-07-17`

## 12. Deuda transversal

| Tema obligatorio | Entradas que lo cubren |
|---|---|
| Revisión integral, pantallas, flujos y navegación | UX-001, UX-002, UX-003, DATA-001, QA-001 |
| Responsive, accesibilidad, lenguaje y deuda visual | UX-004, UX-005, UX-006, UX-007 |
| Planes, capacidades, cuotas y estados | CAP-001 a CAP-008 |
| Gratuito unclaimed/claimed, edición, foto, galería, tel, WhatsApp y Maps | CAP-004, CAP-009, OWN-001, PUB-002, PUB-003, PUB-005 |
| Reclamo completo | OWN-001 a OWN-003, PUB-001 |
| Registro, login, recovery y seguridad | AUTH-001 a AUTH-004 |
| Agenda sin expediente y upgrade clínico controlado | AGD-003, AGD-004, PAT-003 |
| Pacientes, duplicados, consentimiento, clínica y colaboradores | PAT-001 a PAT-003, CLN-001 a CLN-006 |
| Recetas, PDF, descarga y regeneración | RX-001, RX-002 |
| Buzón, estados, triggers, preferencias y entregas | NOT-001 a NOT-005 |
| Suscripciones y ciclo comercial | CAP-005 a CAP-007, SUB-001 a SUB-004 |
| Comentarios, moderación y reputación | REV-001 a REV-003 |
| Interconexiones y autorización | DATA-001 a DATA-004 |
| Concurrencia, doble clic, retry, offline y borradores | AGD-005, DATA-003, DATA-004 |
| Funciones bloqueadas y upsell | CAP-003, CAP-007, SUB-003 |
| Plano de control, módulos, personal, roles internos, dirección y break-glass | ADM-001, ADM-002 |
| Lifecycle, MFA, access reviews, suspensión, baja y revocación | AUTH-004, ADM-003 |
| Case management, sesiones asistidas y acceso clínico extraordinario | ADM-004, ADM-006, PRIV-001, CLN-005 |
| Scopes, equivalencia frontend/backend y autorización administrativa | CAP-008, DATA-002, ADM-005 |
| Doble aprobación y separación de funciones | ADM-005 |
| Auditoría administrativa y enmascaramiento | ADM-006, PRIV-001, PRIV-002 |
| Colas y notificaciones internas | ADM-007, NOT-001 a NOT-005 |
| Moderación y operación de pagos gobernada | REV-002, SUB-002, ADM-001, ADM-005 |
| UX y accesibilidad de consola | UX-003 a UX-005, ADM-008 |
| Runbooks operativos internos | DOC-006 |
| IA, voz, cuotas, confirmación y privacidad | AI-001, AI-002 |
| Privacidad, retención, eliminación y analítica | PRIV-001, PRIV-002, CLN-005 |
| Documentación, superseded, WARN y duplicados | DOC-001 a DOC-003, TECH-001 a TECH-003 |
| Runtime y deployment | RUNTIME-001 a RUNTIME-013 |

## 13. Gates runtime y deployment

Los trece `RUNTIME-*` permanecen `GATED`. Su presencia no autoriza resolverlos desde este documento. El gate agregado es:

`publicTrafficDecision=no-go-runtime-and-operational-gates-v1`

El cierre de un gate requiere su etapa operativa o funcional autorizada, no una edición del registro.

## 14. Dependencias entre deudas

| Cadena | Dependencia |
|---|---|
| CAP-001 → CAP-003 → CAP-008 | Matriz de planes antes de estado común y enforcement |
| CAP-004 → OWN-001/002/003 | Ownership independiente antes de reclamo completo |
| AUTH-001/003/004 → DATA-002 | Identidad/sesión antes de autorización transversal |
| PAT-002/003 → AGD-004 → CLN-001 | Identidad y frontera administrativa antes de crear contexto clínico |
| CLN-002/004/005 → RX-001/002 | Consentimiento, permisos y retención antes del flujo documental completo |
| NOT-003 → NOT-002/004/005 | Catálogo de eventos antes de estados, preferencias y delivery |
| SUB-002/003 → CAP-005/006 | Ciclo comercial y efectos por capacidad deben cerrarse juntos |
| DATA-001 → PG-01–PG-11 | PP274 satisface el inventario global y habilita auditorías detalladas sin afirmar conexiones runtime |
| ADM-002/003 → ADM-001/004–008 | Roles y lifecycle preceden módulos, sesiones, aprobaciones, audit, colas y UX |
| ADM-004/005/006 → PG-10 | Caso, riesgo, aprobación, masking y auditoría preceden mutaciones de consola |
| RUNTIME-001/005/006/009 → RUNTIME-013 | Readiness, logs, métricas y secretos antes de deploy/tráfico |
| RUNTIME-010/011/012 → RUNTIME-013 | Edge, recuperación y costo antes de cutover |

## 15. Mapa oficial de auditorías

Decisión: **AUDITORÍA COMPLETA EN COBERTURA, EJECUTADA EN 22 ACTIVIDADES DE 11 GRUPOS.**

PP274 cerró el inventario global read-only. PP276 concluyó la Actividad 1; el contador permanece `1/22`. La Actividad 2 está bloqueada hasta aprobación explícita del paquete directoral revisado:

`PRODUCT-IMPLEMENTATION/MXMed-Plans-Capabilities-Ownership-Lifecycle-Implementation-01`

El paquete histórico refina DEC-001 a DEC-011 sin aprobarlas. El orden oficial se conserva en el inventario global: `PG-01, PG-02, PG-08, PG-03, PG-04, PG-06, PG-05, PG-07, PG-09, PG-10, PG-11`.

## 16. Criterios de priorización

1. P0 con acceso, privacidad, integridad, pagos, clínica o launch gate.
2. P1 que impida flujo principal o contrato de plan/capacidad/identidad.
3. Dependencia que desbloquee múltiples deudas sin reabrir cierres.
4. Evidencia: primero convertir `REQUIRES_AUDIT` en conclusión verificable.
5. Impacto y frecuencia, sin confundir presencia visual con función operativa.
6. Reversibilidad, owner y criterio de aceptación.
7. Refactors P3 sólo cuando reduzcan riesgo medible o habiliten trabajo aprobado.

## 17. Criterios de cierre

Una entrada sólo pasa a `RESOLVED` cuando:

- la deuda o decisión exacta está implementada/aprobada;
- frontend/backend/datos están alineados cuando aplique;
- seguridad, privacidad y accesibilidad tienen gate proporcional;
- estados vacío/carga/error/éxito están cubiertos;
- QA y evidencia no usan datos personales, secretos o clínica;
- el criterio de aceptación de la entrada está satisfecho;
- se registra commit/PP/reporte y fecha;
- se actualiza el JSON espejo;
- no se contradice una decisión protegida.

Cerrar una entrada no elimina el ID ni su historial.

## 18. Proceso para agregar deuda nueva

1. Buscar ID/tema para evitar duplicado.
2. Reconciliar decisiones posteriores y cierres.
3. Elegir prefijo y siguiente número libre.
4. Clasificar con evidencia; usar `REQUIRES_AUDIT` si no alcanza.
5. Asignar prioridad y estado.
6. Completar los 25 campos.
7. Definir owner opaco, grupo y aceptación.
8. Actualizar Markdown y generar delta/reconciliación JSON del amendment sin reescribir snapshots históricos.
9. Ejecutar auditorías de IDs, campos, cobertura, privacidad y links.
10. Publicar con historial de cambios.

## 19. Proceso para marcar resuelta

1. Adjuntar evidencia nueva.
2. Verificar aceptación y no repetición.
3. Confirmar que no quedan rutas/estados contradictorios.
4. Registrar commit, QA y decisión de cierre.
5. Cambiar estado a `RESOLVED`, conservando clasificación/historial.
6. Si es una decisión cerrada, usar `CLOSED_REFERENCE_ONLY` y `PROTECTED`.
7. Actualizar conteos, dependencias, cobertura y JSON.
8. Nunca borrar ni reutilizar el ID.

## 20. Historical Functional Sources Reconciliation Amendment

**Contrato:** `MXMED_HISTORICAL_FUNCTIONAL_DOCUMENTS_RECONCILIATION_V1`
**Fuente:** [reconciliación histórica](./MXMED_RECONCILIACION_DOCUMENTACION_HISTORICA_FUNCIONAL.md) y ocho PDF `historical_noncanonical`.
**Resultado:** seis altas confirmadas, 20 deudas ampliadas, cero IDs eliminados o reutilizados; contador `1/22` sin incremento.

Ampliaciones sin duplicar tema:

| IDs | Refinamiento incorporado |
|---|---|
| `CAP-004/005/006` | claim/ownership/publicación separados; conflicto grace D+8 vs 15; freeze/preservación |
| `OWN-001..003` | documentación, revisión humana, instituciones, origen y desvinculación |
| `AGD-003/004`, `PAT-003` | cita puede crear contacto; expediente sólo por acción clínica explícita |
| `NOT-003..005` | 22 triggers históricos, conflicto email y clases no desactivables/configurables |
| `AI-001/002` | chat/voz Professional, human-in-loop, dry-run, provider y presupuesto |
| `DOC-001..003` | fuentes históricas no canónicas, precedencia, cita y promoción gobernada |
| `ADM-001/002/007` | backoffice, superadmin superseded, roles candidatos, cola y before/after |

#### AGD-006 — Política de riesgo por inasistencia y cancelación

- **ID:** `AGD-006`
- **Título:** Política de riesgo por inasistencia y cancelación
- **Dominio:** `agenda-riesgo-asistencia`
- **Clasificación:** `DECISION_PENDING`
- **Prioridad:** `P0`
- **Estado:** `OPEN`
- **Descripción actual:** Las fuentes históricas proponen lista negra y gris; no se adopta bloqueo o etiqueta estigmatizante.
- **Evidencia:** `HIST-INT-005/006`, `HIST-NOT-008`; HIST-SRC-001/002.
- **Archivos o decisiones relacionadas:** `AGD-003`, `NOT-003`, paquete `RDD-019`.
- **Efecto visible para el usuario:** Bloqueo de reserva o señal privada todavía no aprobados.
- **Riesgo de negocio:** Rechazo incorrecto de citas y trato inconsistente.
- **Riesgo de datos o seguridad:** Perfilamiento, estigmatización y falta de corrección.
- **Planes afectados:** `all-without-plan-derived-effect`
- **Roles afectados:** `doctor`, `agenda_operator`, `patient_contact`
- **Capacidades relacionadas:** `attendance-risk`, `booking-policy`
- **Dependencias:** PG-03 y PG-08.
- **Decisión pendiente:** Criterios, vigencia, revisión, excepción y derecho de corrección.
- **Auditoría requerida:** Casos de no-show/cancelación, scopes y efectos negativos.
- **Grupo recomendado:** `PG-03`
- **Acción recomendada:** Usar candidato `attendance_risk_flag`; no publicar listas.
- **Criterio de aceptación:** Señal temporal, explicable, corregible, auditada y sin bloqueo automático no aprobado.
- **No repetición:** No reintroducir lista negra/gris mediante alias.
- **Owner funcional:** `agenda-policy-owner`
- **Fecha de incorporación:** `2026-07-18`
- **Última revisión:** `2026-07-18`

#### CLN-007 — No delegabilidad e integridad documental clínica

- **ID:** `CLN-007`
- **Título:** No delegabilidad e integridad documental clínica
- **Dominio:** `clinica-autorizacion-documentos`
- **Clasificación:** `DECISION_PENDING`
- **Prioridad:** `P0`
- **Estado:** `OPEN`
- **Descripción actual:** Diagnóstico, notas, emisión/firma de Recetas y consentimientos requieren política no delegable; correcciones deben preservar el documento emitido.
- **Evidencia:** `HIST-CLN-002..004`, `HIST-RBAC-004..006`; HIST-SRC-001/004/008.
- **Archivos o decisiones relacionadas:** `CLN-002/004`, `RX-001/002`, `DEC-003/011`, `RDD-013/020`.
- **Efecto visible para el usuario:** Autoría, corrección y reimpresión pueden ser ambiguas.
- **Riesgo de negocio:** Documento clínico sin profesional responsable o historia íntegra.
- **Riesgo de datos o seguridad:** Acceso o mutación clínica fuera de scope/consentimiento.
- **Planes afectados:** `clinical-capability-plans-pending`
- **Roles afectados:** `doctor`, `delegated_operator`
- **Capacidades relacionadas:** `clinical-write`, `prescription-issue`, `document-reprint`
- **Dependencias:** PG-02, PG-03, PG-04 y PG-08.
- **Decisión pendiente:** Matriz no delegable, excepciones, firma, versión, folio y notificación.
- **Auditoría requerida:** Endpoints, repositories, documento emitido y pruebas negativas por rol/scope.
- **Grupo recomendado:** `PG-04`
- **Acción recomendada:** Prohibir edición in-place; permitir sólo copia exacta auditada cuando se apruebe.
- **Criterio de aceptación:** Emisión profesional, versión inmutable, corrección nueva y reimpresión trazable.
- **No repetición:** No tratar una autorización administrativa como consentimiento clínico.
- **Owner funcional:** `clinical-safety-owner`
- **Fecha de incorporación:** `2026-07-18`
- **Última revisión:** `2026-07-18`

#### SUB-005 — Conciliación y override de pagos manuales

- **ID:** `SUB-005`
- **Título:** Conciliación y override de pagos manuales
- **Dominio:** `suscripciones-operacion-pagos`
- **Clasificación:** `CONFIRMED_DEBT`
- **Prioridad:** `P0`
- **Estado:** `OPEN`
- **Descripción actual:** No existe contrato transversal gobernado para SPEI/transferencia, conciliación, prórroga u override manual.
- **Evidencia:** `HIST-PAY-003..006`; HIST-SRC-007; PP275 clasifica override como R3.
- **Archivos o decisiones relacionadas:** `SUB-001/002`, `ADM-005/006`, `RDD-016`.
- **Efecto visible para el usuario:** Pago, vigencia o plan podrían depender de una excepción opaca.
- **Riesgo de negocio:** Fraude, doble acreditación, ranking o revenue incorrectos.
- **Riesgo de datos o seguridad:** Comprobante expuesto, autoaprobación o bypass de Stripe.
- **Planes afectados:** `paid`
- **Roles afectados:** `billing_subscription_operator`, `platform_director`
- **Capacidades relacionadas:** `manual-payment-reconciliation`, `extension`, `payment-override`
- **Dependencias:** PG-02, PG-06 y PG-08.
- **Decisión pendiente:** Autoridad, idempotencia, doble aprobación, retención de comprobante y notificación.
- **Auditoría requerida:** Flujos actuales y casos negativos sin reabrir Stripe.
- **Grupo recomendado:** `PG-06`
- **Acción recomendada:** Manual payment R3; reenvío R1; prórroga R2; override R3.
- **Criterio de aceptación:** Caso, referencia, conciliación, actor, motivo, approval y audit correlacionados.
- **No repetición:** No crear backend paralelo ni alterar PaymentIntent/webhook protegido.
- **Owner funcional:** `billing-governance-owner`
- **Fecha de incorporación:** `2026-07-18`
- **Última revisión:** `2026-07-18`

#### ADM-009 — Máquina de publicación y moderación

- **ID:** `ADM-009`
- **Título:** Máquina de publicación y moderación
- **Dominio:** `publicacion-moderacion`
- **Clasificación:** `CONFIRMED_DEBT`
- **Prioridad:** `P1`
- **Estado:** `OPEN`
- **Descripción actual:** Claim, ownership, suscripción y publicación se describen con estados superpuestos; falta una máquina independiente.
- **Evidencia:** `HIST-PUB-001`, `HIST-NOT-036`, `HIST-CLM-006`; HIST-SRC-003/006/007.
- **Archivos o decisiones relacionadas:** `OWN-002`, `REV-002`, `ADM-007`, `RDD-008`.
- **Efecto visible para el usuario:** Cambios pueden aparecer, ocultarse o esperar revisión sin estado claro.
- **Riesgo de negocio:** Publicación prematura o cola sin responsable/SLA.
- **Riesgo de datos o seguridad:** Contenido o identidad no verificados expuestos.
- **Planes afectados:** `all-without-plan-derived-moderation`
- **Roles afectados:** `profile_claim_reviewer`, `content_moderator`, `profile_owner`
- **Capacidades relacionadas:** `publication-review`, `moderation`, `before-after`
- **Dependencias:** PG-02, PG-05, PG-07, PG-08 y PG-10.
- **Decisión pendiente:** Estados, transiciones, SLA, cambios sensibles, scope y rollback.
- **Auditoría requerida:** UI/API/data/events/notificaciones y separación de otras máquinas.
- **Grupo recomendado:** `PG-07`
- **Acción recomendada:** Propuesta `draft→pending_review→approved→published`, con changes/suspension gobernados.
- **Criterio de aceptación:** Autoridad única, cola, actor, reason, before/after, audit y denials.
- **No repetición:** No usar status de suscripción u ownership como publicación.
- **Owner funcional:** `publication-governance-owner`
- **Fecha de incorporación:** `2026-07-18`
- **Última revisión:** `2026-07-18`

#### ADM-010 — Scopes de mercadotecnia y citas globales

- **ID:** `ADM-010`
- **Título:** Scopes de mercadotecnia y citas globales
- **Dominio:** `operadores-internos-scope`
- **Clasificación:** `DECISION_PENDING`
- **Prioridad:** `P1`
- **Estado:** `OPEN`
- **Descripción actual:** Los roles históricos de mercadotecnia/difusión y citas globales tienen facultades amplias sin equivalencia actual aprobada.
- **Evidencia:** `HIST-RBAC-002/012/014`; HIST-SRC-004/007.
- **Archivos o decisiones relacionadas:** `ADM-002/003/004`, `DATA-002`, `RDD-015`.
- **Efecto visible para el usuario:** Contenido o citas podrían mutarse por personal no scopiado.
- **Riesgo de negocio:** Operación transversal sin owner, temporalidad o segregación.
- **Riesgo de datos o seguridad:** Acceso excesivo a perfiles, agendas o datos de contacto.
- **Planes afectados:** `none-internal-role-independent`
- **Roles afectados:** `role_or_permission_pending`
- **Capacidades relacionadas:** `marketing-content`, `global-booking-service`
- **Dependencias:** PG-02, PG-08 y PG-10.
- **Decisión pendiente:** Rol separado, permission set, scope temporal o variante de rol actual.
- **Auditoría requerida:** Casos de uso, entidades, consentimientos, horarios y actions R0–R3.
- **Grupo recomendado:** `PG-10`
- **Acción recomendada:** No crear roles; presentar alternativas al director.
- **Criterio de aceptación:** Permiso mínimo, caso/scope, vigencia, no clínica, audit y revocación.
- **No repetición:** No reutilizar operador Agenda como operador global/plataforma.
- **Owner funcional:** `operator-governance-owner`
- **Fecha de incorporación:** `2026-07-18`
- **Última revisión:** `2026-07-18`

#### AI-003 — Descomposición de capabilities y seguridad IA

- **ID:** `AI-003`
- **Título:** Descomposición de capabilities y seguridad IA
- **Dominio:** `inteligencia-artificial-gobernanza`
- **Clasificación:** `DECISION_PENDING`
- **Prioridad:** `P0`
- **Estado:** `OPEN`
- **Descripción actual:** Las fuentes mezclan redacción, imágenes, interacción medicamentosa, agente Professional, operación interna y supervisor all-fields.
- **Evidencia:** `HIST-AI-001..011`; HIST-SRC-005/007/008.
- **Archivos o decisiones relacionadas:** `AI-001/002`, `CLN-007`, `RDD-018`.
- **Efecto visible para el usuario:** Un label IA podría prometer capacidades, datos o planes distintos.
- **Riesgo de negocio:** Costo, provider, moderación o responsabilidad sin contrato.
- **Riesgo de datos o seguridad:** Acción autónoma, clínica no supervisada o acceso universal.
- **Planes afectados:** `standard/professional historical; current pending`
- **Roles afectados:** `doctor`, `product_user`, `future_internal_operator`
- **Capacidades relacionadas:** `AI-CONTENT-WRITING`, `AI-IMAGE-GENERATION`, `AI-MEDICATION-INTERACTION`, `AI-PROFESSIONAL-AGENT`, `AI-INTERNAL-OPERATIONS`, `AI-INTERNAL-SUPERVISOR`
- **Dependencias:** PG-01, PG-04, PG-08 y PG-11.
- **Decisión pendiente:** Plan, riesgo, datos, costo, cuotas, human-in-loop, audit y provider por capability.
- **Auditoría requerida:** Herramientas, prompts/data boundary, fallos, provider y casos negativos.
- **Grupo recomendado:** `PG-11`
- **Acción recomendada:** Rechazar supervisor all-fields; separar seis capabilities y aprobar una por una.
- **Criterio de aceptación:** Cada capability tiene autoridad, scope, presupuesto, review, logging saneado y kill switch.
- **No repetición:** No afirmar readiness ni seleccionar proveedor por documento histórico.
- **Owner funcional:** `ai-governance-owner`
- **Fecha de incorporación:** `2026-07-18`
- **Última revisión:** `2026-07-18`

## 21. Historial de cambios

| Versión | Fecha | Cambio | Autoridad |
|---|---|---|---|
| 1.0.0 | 2026-07-17 | Alta del registro canónico con 92 entradas y plan de auditoría por grupos | `PRODUCT-DOC/MXMed-System-Wide-Product-Debt-Registry-01` |
| 1.1.0 | 2026-07-17 | Amendment del plano de control: 7 altas, ADM-001/002 ampliadas, 99 entradas y contador principal 0/22 | `PRODUCT-DOC/MXMed-Operator-Control-Plane-And-Platform-Roles-Requirement-Amendment-01` |
| 1.2.0 | 2026-07-18 | Reconciliación histórica: 6 altas, 20 ampliaciones, 105 entradas y contador principal 1/22 | `PRODUCT-AUDIT/MXMed-Historical-Functional-Documents-Reconciliation-01` |
