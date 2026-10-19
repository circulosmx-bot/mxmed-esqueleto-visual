# ASE-ADM02-PANEL07 — correcciones funcionales y recuperación de acceso

Baseline verificado: [PANEL06](ASE-ADM02-PANEL06.md), `419fe1fc6d79b545aae3502a3f6991699fc4c685`, worktree limpio y HEAD/upstream coincidentes. Nueva rama `feature/ase-adm02-panel07-final-functional-fixes`, worktree `mxmed-ase-adm02-panel07`. Sólo esta rama se entrega; fuente oficial, puerto 8091 y previews anteriores preservados.

## PRODUCT_POLICY y alcance UI-2

Solicitud explícita PANEL07: corregir edición, consulta de identidad, disponibilidad real de dos grupos de permisos, desplegables y regreso al login. Conserva encabezado, siete secciones, categorías, filtros, búsqueda, asistente de cuatro pasos, baja/historial, clientes y fronteras comerciales. Sin nueva arquitectura, módulos, roles, migraciones, datos reales, notificaciones, merge o producción. La aceptación visual corresponde al Director.

Ficha acotada: controles existentes → recuperación de CSRF/sesión y errores contextuales; selects nativos → patrón compacto botón/listbox ya usado por Estado, colocado en popover dentro del diálogo. Razón: fallos tras inactividad y menú nativo sin contención controlada por el modal. Conservar el comportamiento anterior dejaría los botones silenciosos y menús sin límites de viewport. Backend: mismo writer/versiones y autorizaciones; sólo se distingue ausencia de sesión (401) de denegación autorizada (403). Riesgos: foco/cierre anidado y resultado incierto; cubiertos por pruebas dirigidas. Rollback: dejar de usar este preview y conservar PANEL06; no se ha promovido esta rama al runtime original.

## SOURCE_IMPLEMENTATION / CONNECTIVITY

### Edición y consulta

La [reproducción del baseline](../evidence/ase-adm02-panel07/baseline.json) confirma que ambos botones abren con sesión vigente. Al invalidar canónicamente la sesión, edición deja una promesa rechazada sin feedback y consulta escribe un error genérico fuera del modal.

La [reproducción del CSRF caducado](../evidence/ase-adm02-panel07/baseline-expired-csrf.json) mantiene sesión autenticada y usa el JS original de Git: un token emitido por `CsrfTokenService` con reloj de prueba a −901 segundos produce el rechazo real del servidor y reproduce ambos botones sin apertura. La autoridad no se falsea ni se modifica el reloj/TTL del servidor. El cliente conservaba el token inicial, cuyo TTL canónico es 900 segundos.

- `staff-workflow.js` pide un bootstrap canónico antes de cada acción explícita y actualiza su CSRF. No repite ninguna escritura ni prolonga sesiones/MFA.
- Perfil y consulta abren su diálogo inmediatamente con estado de carga y muestran sus errores allí. Una sesión ausente va al login Identity; 403 sigue siendo una denegación de autoridad, sin redirección que la oculte.
- `StaffWorkflow::Http` retorna 401 cuando el resolver canónico no encuentra sesión; preserva transporte firmado, origen, CSRF, autoridades y auditoría de denegaciones aplicables.
- La indisponibilidad del lector de fotografía no bloquea otros campos autorizados. Su control queda deshabilitado y explícito; la edición parcial sigue el mismo writer/versiones. El correo Identity permanece de consulta, el teléfono de contacto no se convierte en credencial verificada.
- Mientras una operación de permisos está en curso, los botones contextuales y logout reflejan ese estado y no parecen aceptar una acción que se descarta silenciosamente.
- La consulta muestra referencia de caso, último resultado/motivo/fecha, conjuntos documentales y versiones personales asociadas. Si no existe revisión o evidencia, lo indica. Si la versión personal cambió, muestra el antecedente y explica por qué las imágenes no son consultables como evidencia vigente.
- `READ` sigue exigiendo contraseña y MFA propios de la acción. Consultar no acepta evidencia ni admite a nadie. Una edición de un integrante ya admitido no inventa una nueva aprobación ni habilita un proceso de readmisión.

### Resultado exacto de los grupos reportados

No se encontró conexión faltante compatible con las autoridades vigentes en estos seis permisos. No se conceden capacidades implícitas ni se sustituyen consumidores por mocks.

| Capacidad | Resultado y dependencia exacta |
|---|---|
| `public_profile_correction_review` | No editable para Advisor. `ReportReviewService::authority` exige DIRECTOR/MASTER_ADMIN y grant; `StaffRoutineOperationPolicy` no autoriza esta operación MFA a Advisor. [PUB-COR01](../profiles/PUB-COR01-secure-intake-human-review.md) exige ampliación explícita de ese contrato para moderación Advisor. |
| `public_profile_correction_apply` | No editable para Advisor. `ApplicationAuthority::grants` exige DIRECTOR/MASTER_ADMIN, sesión/credencial y grant. Su operación no está en el contrato MFA rutinario Advisor. [PUB-COR02](../profiles/PUB-COR02-governed-typed-application.md) conserva aplicación tipada y gobierno del recurso; no equivale a ownership o colaboración médica. |
| `profile_create` | Sin ruta/consumidor operativo de creación asistida que consuma este grant; sólo proyección de requisito en `ProfileGovernancePolicy`. |
| `profile_curate` | Sin ruta/consumidor operativo de curación asistida que consuma este grant; la política declarativa no autoriza escrituras. |
| `profile_publication_manage` | Sin ruta/consumidor operativo conectado a este grant; no sustituye las reglas existentes de publicación/owner. |
| `profile_support_assist` | Sin ruta/consumidor operativo de soporte asistido conectado al grant; no implica ser colaborador/owner. |

Las diez capacidades operativas detalladas en PANEL06 conservan sus conexiones. Se prueba aquí guardar/revocar y el consumidor real de lectura Media. Correcciones se contrasta también ejecutando sus consumidores reales con un Advisor sintético y grants almacenados por el writer canónico: ambos rechazan 403 y las operaciones MFA siguen denegadas; los grants de prueba se revocan canónicamente al terminar. Las seis claves permanecen deshabilitadas en ambos navegadores con explicación de administrador. No se simula una asignación UI imposible ni una operación de perfiles inexistente.

### Desplegables

El selector nativo de Vía de preparación y los de perfil dejaban la presentación del popup al navegador/sistema; `max-height` del diálogo no restringía ese menú. `team-selects.js` reutiliza el patrón existente del filtro Estado con fuente de valor/eventos en el select original y un listbox accesible en popover. No nueva librería.

Texto 14 px, ancho acotado, altura máxima 240 px o espacio disponible, apertura hacia arriba cuando corresponde, scroll interno, Arrow/Home/End/Enter/Space, búsqueda por letras, Escape, Tab y retorno de foco. El popup pertenece al diálogo y está en top layer: no lo recorta el contenedor con scroll. Un click en backdrop cierra primero el popup, sin cerrar también el formulario; desplazar el contenedor cierra el popup. La categoría del asistente continúa fija en Advisor por el contrato de admisión, sin habilitar appointment Master.

### Logout, login y guía sintética

Mismo formulario, endpoints y credenciales Identity. El router propietario aporta una guía sólo al login de este ensayo; fuera de esa composición no se muestra. Identifica `qa_director@example.test`, comandos privados para copiar su contraseña ficticia al portapapeles y obtener el TOTP actual, y regreso a Equipo tras login. No muestra/prellena contraseñas ni guarda secretos en Git. La cuenta habitual no se importa desde ninguna base real.

El comando `open` abre expresamente un navegador nuevo y completa el formulario Identity con la cuenta ficticia; es un control de revisión separado, no la prueba de logout/login ni una reautenticación automática. La prueba funcional completa usa el botón de producto, denegación de ruta protegida, contraseña incorrecta y posterior login normal. El servicio no se recrea para volver a entrar. MFA sensible permanece independiente del login normal y no se prolonga.

## VERIFICATION

- [QA funcional](../evidence/ase-adm02-panel07/qa.json): **134 PASS**, ambos motores, interacción real, guardado/recarga, conflicto de versión, privacidad, consulta de imágenes con contraseña/TOTP sin aprobación, grants aplicables, logout/login y sesión invalidada.
- Desplegables **abiertos** en 1440×900, 1366×768, 390×844 y 900×400; campos, teclado, texto compacto, contención, ausencia de overflow y capturas en [screens](../evidence/ase-adm02-panel07/screens).
- [Zoom nativo](../evidence/ase-adm02-panel07/native-zoom.json): **42 PASS**, 125 % real. Chromium usa `chrome.tabs.setZoom` en extensión local exclusiva del navegador QA; WebKit usa su comando `Playwright.setPageZoomFactor`. Se comprueban cambio de viewport/DPR y CSS zoom=1, popups abiertos y pie alcanzable. Sucede la limitación CSS-only de PANEL06; no afirma aceptación física en Safari del Director.
- [Recuperación final](../evidence/ase-adm02-panel07/recovery.json): **18 PASS** sobre la fuente final: CSRF caducado, consulta, apertura hacia arriba, scroll, backdrop anidado, retrato privado, lector de media indisponible sin bloquear perfil y rechazo real de consumidores de correcciones.
- [Layout final](../evidence/ase-adm02-panel07/layout.json): **58 PASS** después de corregir un cierre HTML sobrante detectado en el diff. Comprueba directorio/detalle lado a lado, vista móvil, encabezado exacto, campos de login visibles con guía desplegable, popups y pie en los cuatro tamaños. Zoom nativo repetido sobre esa estructura final.
- [Verificación estática](../evidence/ase-adm02-panel07/verification.json): sintaxis, enlaces, hashes, diff, pins propios y preservación del runtime/worktrees.

Se conservan intentos fallidos: escape `%` del harness SQL; espera de finalización del segundo guardado; colocación de trigger en altura reducida; doble cierre real popup/dialog corregido; expectativa errónea de nueva versión cuando no había cambio. La matriz 134 pasó antes del ajuste final de doble cierre/media opcional; recuperación, layout y zoom se verifican después. No se repiten suites históricas ni se acredita operación normal-local.

## OPERATIONAL_STATUS / revisión

URL: `https://127.0.0.1:65095/internal/media-review/index.php?section=equipo`.
Infraestructura propia hasta **2026-10-19 19:44:57 UTC−06:00**, máximo 24 h desde su lanzamiento; sesiones conservan TTL normal. Sólo loopback, imágenes/contactos ficticios e invitaciones inertes. No DDL nuevo ni datos/DB originales, merge, despliegue o producción.

Comando base:

```sh
python3 /Users/circulodigital/Documents/GitHub/mxmed-ase-adm02-panel07/scripts/ase-adm02-panel07-review.py /tmp/mxmed-ase-adm02imp01-vr6d2foi
```

Añadir `open` para abrir otra revisión mediante login, `credentials` para copiar sólo la contraseña ficticia, `mfa` para el código actual, `status` para URL/caducidad y `stop` para terminar únicamente estos recursos mediante su runner propietario. Verifica dueño, root, HEAD, pins y expiración. No actúa sobre listeners encontrados por puerto ni reinicia otros entornos.

Bruno queda ACTIVE, con Datos personales abierto, contacto ficticio y fotografía privada; sus ediciones dejan su revisión anterior como antecedente. **Alma conserva una revisión positiva vigente y dos imágenes artificiales** para probar Consultar revisión → Consultar evidencia → contraseña/TOTP. Fernanda conserva un caso pendiente. No se deduce identidad verificada de ACTIVE.

## CTX-SYNC01

Baseline PANEL06; IDs ID-02, ID-03, ID-07, COM-10; journey J09. Se actualizan estado, inventario e interacciones y este contrato, sin regenerar mapas ni router. Objetos existentes: sesiones/CSRF, versiones de admisión, conjuntos/revisiones/operaciones StaffMedia y grants/auditoría. No schema ni nuevo dueño de datos. Fuente, conectividad, restricciones, evidencia y estado operacional contrastados: PASS_UPDATED. Aceptación visual pendiente; no se ocultan las seis limitaciones legítimas de permisos.
