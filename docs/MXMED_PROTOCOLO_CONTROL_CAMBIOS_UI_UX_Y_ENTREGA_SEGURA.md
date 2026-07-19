# Protocolo de control de cambios UI/UX y entrega segura — MXMed

**Contrato:** `MXMED_UI_UX_CHANGE_CONTROL_SAFE_DELIVERY_PROTOCOL_V1`

**Decisión:** `PP-280`

**Estado:** obligatorio para toda actividad futura

**UI oficial protegida:** `http://127.0.0.1:8091/` (`OFFICIAL_LAST_APPROVED_UI`)

## 1. Propósito y autoridad

MXMed separa la autoridad funcional de la representación visual:

- El backend conserva la autoridad sobre reglas, seguridad, datos y contratos.
- La existencia de un dato en backend no autoriza mostrarlo en una pantalla.
- Codex propone, implementa dentro del alcance autorizado y presenta evidencia.
- El director aprueba la experiencia visible.
- El puerto 8091 conserva la última UI aprobada.

Backend puede avanzar rápidamente. UI no puede cambiar silenciosamente.

## 2. Lección técnica

Una actividad anterior de planes, capacidades y ciclo de vida alcanzó también la presentación comercial: información técnica se proyectó directamente en tarjetas y subheader, alterando jerarquía, densidad, textos y comportamiento sin una decisión visual explícita. El director identificó que esos cambios no correspondían al concepto visual esperado y fue necesaria una recuperación forense. El baseline `e4f7d515cba4ae47fcdbd44cd55ce610466b982a` fue restaurado, certificado y promovido.

La lección no cuestiona el baseline actual: establece que una regla funcional aprobada y su representación visual son decisiones distintas.

## 3. Clasificación contractual

| Nivel | Ejemplos | Gate | Evidencia | Aprobación | Rollback | Puerto | PASS permitido |
|---|---|---|---|---|---|---|---|
| `UI-0 — NO_UI_IMPACT` | Backend, schema, seguridad o tests sin archivos ni efectos visibles | Scope y pruebas | Tests, Git limpio y comprobación de cero archivos UI | No adicional | Revertir rama de trabajo | Fuera de 8091 hasta promoción normal | `BACKEND_COMPLETED_NO_UI_IMPACT` |
| `UI-1 — UI_DATA_BINDING_ONLY` | Conectar o corregir datos sin alterar composición, copy, jerarquía, colores o conducta | Diff visual cero | Antes/después, DOM, estructura, comportamiento y diff cero | Sólo se detiene si aparece diff | Commit reversible y checkpoint | Fuera de 8091 hasta demostrar diff cero | `BACKEND_AND_FRONTEND_COMPLETED` |
| `UI-2 — UI_BEHAVIOR_CHANGE_REQUIRES_APPROVAL` | Navegación, interacción, estados, disponibilidad, acciones o mensajes contextuales | Ficha breve previa | Decisión, escenarios, capturas contextuales, pruebas y rollback | Director antes de implementar | Rama separada y reversión probada | Revisión separada; 8091 sólo tras aprobación | `BACKEND_AND_FRONTEND_COMPLETED` tras aprobación |
| `UI-3 — UI_VISUAL_CHANGE_REQUIRES_APPROVAL` | Aspecto, composición, UX, copy permanente, densidad, colores o jerarquía | Propuesta y prototipo reversible | URL separada, tres viewports, estados, teclado, focus, accesibilidad y rollback | Expresa y visual | Descartar worktree/branch o revertir commit visual | `8140+`; nunca primero en 8091 | PASS visual sólo tras aprobación expresa |

Una actividad `UI-1` escala automáticamente a `UI-2` o `UI-3` si aparece cualquier diferencia visible.

## 4. Protección de 8091

`http://127.0.0.1:8091/` es `OFFICIAL_LAST_APPROVED_UI`:

1. Contiene la última experiencia visual aprobada.
2. No recibe prototipos `UI-3`.
3. No recibe `UI-2` sin aprobación previa.
4. `UI-0` trabaja fuera de 8091.
5. `UI-1` sólo llega después de demostrar diff visual cero.
6. Los prototipos usan puertos `8140` en adelante e identifican branch, commit, puerto y expiración.
7. Ningún prototipo sustituye accidentalmente el baseline oficial.

## 5. Backend ↔ API/read-model ↔ Frontend/UI

Toda actividad debe completar la matriz de [actividad segura](./MXMED_PLANTILLA_ACTIVIDAD_SEGURA_BACKEND_API_UI.md). Cada campo nuevo se clasifica como:

- `backend_only`
- `operator_only`
- `contextual_user_ui`
- `permanent_user_ui`
- `future_ui`
- `no_ui_representation`

Una regla técnica implementada no significa que su representación visual esté aprobada.

## 6. Estados de cierre y frontend diferido

Estados permitidos:

- `BACKEND_AND_FRONTEND_COMPLETED`
- `BACKEND_COMPLETED_NO_UI_IMPACT`
- `BACKEND_COMPLETED_FRONTEND_DEFERRED_WITH_EXPLICIT_GATE`
- `BLOCKED_BACKEND_FRONTEND_PARITY_INCOMPLETE`

El frontend diferido exige pantalla responsable, actividad futura, identificador de deuda, razón, riesgo, estado visual temporal, prueba de que el backend es seguro sin UI, gate, owner y condición de desbloqueo.

Está prohibido declarar PASS cuando exista una regla visible incoherente, UI sin clasificación, una representación decidida unilateralmente o frontend diferido sin actividad y deuda explícitas.

## 7. Vía rápida

- `UI-0`: alcance y pruebas; no requiere pausa del director.
- `UI-1`: puede implementarse y sólo se detiene si el diff deja de ser cero.
- `UI-2`: ficha breve y una decisión del director; prototipo sólo si se solicita.
- `UI-3`: prototipo completo y aprobación visual.

### Ficha UI-2, máximo una página

Debe contener: pantalla, componente, comportamiento actual, comportamiento propuesto, razón, alternativa sin cambio, impacto backend, riesgo, rollback y recomendación no vinculante.

## 8. Prototipos reversibles

Todo prototipo requiere:

- rama y worktree separados;
- puerto `8140+`, sin modificar 8091;
- commit visual separado del funcional cuando aplique;
- URL de revisión y expiración;
- fixture QA y cero datos reales;
- desktop, tablet y móvil;
- rollback probado y descarte seguro.

La integración sólo procede tras la frase: **“Apruebo visualmente esta versión.”** Sin ella no hay merge, promoción, sustitución de 8091 ni PASS visual final.

## 9. Orden obligatorio de ejecución

1. **Terminal previa — EJECUTAR AHORA.**
2. **Instrucciones Codex — EJECUTAR DESPUÉS DEL PASO 1.**
3. **Esperar reporte — NO EJECUTAR TODAVÍA LOS SIGUIENTES PASOS.**
4. **Terminal posterior — EJECUTAR DESPUÉS DEL REPORTE.**
5. **Revisión del director — CUANDO EXISTA IMPACTO VISIBLE.**
6. **Cierre, integración, promoción o rollback.**

Se prohíben comandos finales antes de sus artefactos, promoción antes de aprobación, validaciones dependientes de commits inexistentes, bloques fuera de secuencia e instrucciones ambiguas sobre qué ejecutar.

## 10. Evidencia mínima

| Nivel | Evidencia obligatoria |
|---|---|
| UI-0 | Scope, tests, Git limpio, lista de archivos y comprobación de cero archivos UI |
| UI-1 | Capturas antes/después, DOM/estructura, diff visual cero y conducta idéntica |
| UI-2 | Decisión, escenarios, capturas contextuales, pruebas funcionales y rollback |
| UI-3 | Prototipo, URL separada, tres viewports, estados, teclado, focus, accesibilidad, aprobación, checkpoint y rollback probado |

## 11. Emergency stop

Estado obligatorio: `STOP_UI_SCOPE_ESCALATION_REQUIRED`.

Se activa cuando cambia UI sin declararse, aparece un archivo frontend fuera de scope, `UI-1` produce diff, cambia copy sin autorización, se agregan datos técnicos a una pantalla, 8091 cambia antes de aprobación, un prototipo toca datos reales o un trabajo backend requiere UI inesperadamente.

El trabajo se conserva en una rama separada y no continúa silenciosamente.

## 12. Dashboard de operadores

El dashboard de operadores es siempre `UI-3`. Cada actividad registra rol, acción, riesgo `R0–R3`, caso, estado, métrica, auditoría, pantalla futura, dependencia backend y aprobación.

No se genera una consola automáticamente desde tablas o endpoints. Su implementación requiere consolidación, arquitectura de información, wireframe, prototipo, aprobación del director y conexión backend posterior.

## 13. Registro de superficies

Los contratos visuales aprobados y pendientes viven en [MXMED_REGISTRO_CONTRATOS_VISUALES.md](./MXMED_REGISTRO_CONTRATOS_VISUALES.md). Cada actividad actualiza sólo las superficies que toque.
