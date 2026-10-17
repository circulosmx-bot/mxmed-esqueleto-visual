# Plantilla de actividad segura Backend ↔ API ↔ UI — MXMed

**Contrato de gobierno:** [Protocolo de control UI/UX](./MXMED_PROTOCOLO_CONTROL_CAMBIOS_UI_UX_Y_ENTREGA_SEGURA.md)

**Decisión:** `PP-280`

Copiar esta plantilla al preparar cualquier actividad futura. Ningún campo aplicable debe quedar implícito.

## 1. Identidad y alcance

- Identificador:
- Objetivo:
- Necesidad:
- Branch/worktree:
- Baseline:
- Owner:
- Clasificación: `UI-0` / `UI-1` / `UI-2` / `UI-3`
- Estado inicial:
- Fuera de alcance:

## 2. Matriz Backend ↔ API/read-model ↔ Frontend/UI

| Campo | Declaración obligatoria |
|---|---|
| Backend afectado | Servicio, repositorio, regla o `NO_CHANGE` |
| Schema afectado | Tabla/migración o `NO_CHANGE` |
| API/read-model afectado | Endpoint, DTO, campo o `NO_CHANGE` |
| Frontend afectado | Archivo/componente o `NO_CHANGE` |
| Pantalla exacta | `surfaceId`, URL/ruta o `NO_UI` |
| Dato visible | Campo y formato o `NONE` |
| Momento | Permanente, contextual, bajo acción, operador o nunca |
| Componente | Elemento responsable o `NONE` |
| Clasificación del campo | `backend_only`, `operator_only`, `contextual_user_ui`, `permanent_user_ui`, `future_ui` o `no_ui_representation` |
| Nivel UI | `UI-0` a `UI-3` |
| Aprobación | No requerida / requerida / aprobada / pendiente |
| Estado temporal | Comportamiento seguro antes de UI final |
| Deuda | ID o `NONE` |
| Dashboard de operadores | Rol, acción, riesgo `R0–R3`, caso, estado, métrica, auditoría y pantalla futura, o `NO_CHANGE` |
| Pruebas | Unitarias, integración, contrato, visuales y responsive |
| Rollback | Acción verificable |

Un dato existe en backend **no significa** que deba mostrarse en UI. Una regla implementada **no significa** que su representación visual esté aprobada.

## 3. Gate visual

- Composición actual preservada:
- Copy preservado:
- Jerarquía preservada:
- Colores/tokens preservados:
- Interacciones preservadas:
- Responsive preservado:
- Archivos UI esperados:
- Archivos UI realmente modificados:
- Evidencia exigida por nivel:
- Resultado del gate:

Si un `UI-1` produce una diferencia, registrar `STOP_UI_SCOPE_ESCALATION_REQUIRED` y reclasificar.

## 4. Frontend diferido

Completar sólo si se pretende `BACKEND_COMPLETED_FRONTEND_DEFERRED_WITH_EXPLICIT_GATE`:

- Pantalla responsable:
- Actividad futura:
- Debt ID:
- Razón:
- Riesgo:
- Estado visual temporal:
- Evidencia de backend seguro sin UI:
- Gate:
- Owner:
- Condición de desbloqueo:

Sin actividad futura y deuda explícita, el estado permitido es `BLOCKED_BACKEND_FRONTEND_PARITY_INCOMPLETE`.

## 5. Ficha UI-2

Máximo una página:

- Pantalla:
- Componente:
- Comportamiento actual:
- Comportamiento propuesto:
- Razón:
- Alternativa sin cambio:
- Impacto backend:
- Riesgo:
- Rollback:
- Recomendación no vinculante:
- Decisión del director:

## 6. Prototipo UI-3

- Branch/worktree separado:
- Commit funcional:
- Commit visual:
- Puerto `8140+`:
- URL y expiración:
- Fixture QA sin datos reales:
- Desktop/tablet/móvil:
- Estados cubiertos:
- Teclado/focus/accesibilidad:
- Rollback probado:
- Frase de aprobación recibida:

Sin “Apruebo visualmente esta versión”, no integrar, promover ni sustituir 8091.

## 7. Orden de ejecución

1. Terminal previa — EJECUTAR AHORA.
2. Instrucciones Codex — EJECUTAR DESPUÉS DEL PASO 1.
3. Esperar reporte — NO EJECUTAR TODAVÍA LOS SIGUIENTES PASOS.
4. Terminal posterior — EJECUTAR DESPUÉS DEL REPORTE.
5. Revisión del director — CUANDO EXISTA IMPACTO VISIBLE.
6. Cierre, integración, promoción o rollback.

## 8. Cierre

- Tests:
- Evidencia:
- Git limpio:
- 8091 preservado:
- Writes/Stripe/SQL/AWS:
- Deuda registrada:
- Estado, uno de:
  - `BACKEND_AND_FRONTEND_COMPLETED`
  - `BACKEND_COMPLETED_NO_UI_IMPACT`
  - `BACKEND_COMPLETED_FRONTEND_DEFERRED_WITH_EXPLICIT_GATE`
  - `BLOCKED_BACKEND_FRONTEND_PARITY_INCOMPLETE`
  - `STOP_UI_SCOPE_ESCALATION_REQUIRED`

- Aprobación o siguiente gate:

## 9. Registro móvil transversal

Toda actividad con frontend debe completar estos campos, incluso cuando el impacto móvil sea sólo de smoke intermedio:

| Campo | Valor obligatorio |
|---|---|
| `mobileSmokeRequired` | `true` / `false` y razón |
| `mobileSmokeResult` | `INTERIM_MOBILE_SMOKE_ONLY`, `NOT_APPLICABLE` o bloqueo explícito; nunca aprobación final |
| `newMobileDebt` | ID de deuda o `NONE` |
| `affectedSurfaces` | Lista de `surfaceId` o `NONE` |
| `finalizationChapterDependency` | `MOBILE_RESPONSIVE_FINALIZATION_PENDING`, `NONE` o dependencia explícita |
| `regressionDetected` | `true` / `false`, evidencia y severidad |
| `UI escalation required` | `true` / `false`; `true` exige `STOP_UI_SCOPE_ESCALATION_REQUIRED` y reclasificación UI-3 |

El smoke mínimo cubre overflow horizontal, controles críticos, navegación, cortes de texto, formularios, modales, touch targets y viewport representativo. El cierre móvil final sólo puede ocurrir mediante el capítulo transversal y aprobación visual expresa del director.

<a id="context-sync"></a>
## 10. Sincronización obligatoria de contexto — CTX-SYNC01

Decisión permanente del Director, 2026-10-17, sobre `ef8a7b4f4e8230c1c047c6b1a2d6e00a14312752`.
La [regla de AGENTS.md](../AGENTS.md#sincronización-obligatoria-de-contexto--ctx-sync01) hace la revisión/sincronización parte de cada ajuste autorizado. Actualiza las notas previas que condicionaban el mantenimiento del estado a autorización documental: esta revisión ahora está incluida en cada ajuste autorizado. Conserva los límites del trabajo y de cualquier operación real.

Antes de implementar, recuperar estado → router → mapa pertinente → fuente/decisión/evidencia; localizar IDs y dueños existentes. Durante el cambio, registrar impacto en UI/API/writers/datos/autoridad/servicios/interacciones, dependencias y límites, preservando IDs. Antes del commit, revisar todo el diff y archivos nuevos, actualizar contexto afectado, pruebas/entorno/estado operacional y validar enlaces/paths/anchors. Actualizar el estado ante cambios materiales y el router sólo ante cambios de rutas documentales. No cargar todo el corpus ni regenerar inventarios por unos pocos IDs.

Toda tarea Codex futura debe incluir estos campos en su entrega, aunque no cambie producto:

```text
CONTEXT_BASELINE=
AFFECTED_CAPABILITY_IDS=
AFFECTED_JOURNEY_IDS=
AFFECTED_DATA_OBJECTS=
AFFECTED_CONTEXT_FILES=
CONTEXT_CHANGES_IMPLEMENTED=
CONTEXT_VERIFICATION=
CONTEXT_SYNC_STATUS=
DOCUMENTATION_COMMIT=
REMAINING_CONTEXT_GAPS=
```

Indicar HEAD fuente y baselines documentales consultados; reutilizar IDs existentes, sin inventarlos para rellenar el reporte. Usar `NONE` con razón cuando no aplique. Identificar documentos y cambios concretos, comprobaciones realmente realizadas y límites pendientes. `DOCUMENTATION_COMMIT` es el hash que contiene la sincronización, incluido un commit mixto autorizado; si no hay commit documental, explicar por qué.

| `CONTEXT_SYNC_STATUS` | Criterio de aceptación |
|---|---|
| `PASS_UPDATED` | Documentos impactados actualizados y contrastados con la misma implementación, alcance del commit y evidencia; enlaces pertinentes válidos. |
| `PASS_NO_IMPACT_WITH_REASON` | Revisión completa demuestra ausencia real de impacto; registrar además `CONTEXT_CHANGE=NO_IMPACT` y la razón precisa, sin generar un cambio documental innecesario. |
| `BLOCKED_INCOMPLETE` | Falta revisión, actualización o verificación aplicable; detallar la brecha y no declarar el resultado global COMPLETE. |

Una función cambiada sin revisión de contexto no está completa. Un Markdown editado no basta: separar `PRODUCT_POLICY`, `SOURCE_IMPLEMENTATION`, `CONNECTIVITY`, `VERIFICATION` y `OPERATIONAL_STATUS` (los mapas anteriores abrevian fuente/operación como `SOURCE`/`OPERATION`). Conservar contratos y evidencia históricos, marcar afirmaciones sucedidas y enlazar la fuente nueva verificada. No inventar tests, verificación externa ni activación a partir de QA aislada. La revisión es manual por tarea; no introduce automatismos, agentes de fondo, hooks globales ni instalaciones.
