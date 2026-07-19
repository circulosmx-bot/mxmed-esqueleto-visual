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
