# STAFF-CONTROL07 — navegación estable y confirmación por operación

Baseline limpio `01d17ac8726f827841fcc8fc83c24b9f9ea003a6`, rama anterior `feature/mxmed-staff-control06-r2-director-layout`. Worktree/rama nueva `mxmed-staff-control07-stable-navigation` / `feature/mxmed-staff-control07-stable-navigation`.

## Decisión y alcance UI-2

Solicitud expresa STAFF-CONTROL07 del Director: estabilizar categorías/lista/detalle y reducir ambigüedad de autenticación sin cambiar políticas. Antes se borraban lista y detalle durante cada carga y el mensaje superior desplazaba los paneles; la lista activa recibía pendientes en una segunda inserción. Ahora el mensaje tiene una región fija dentro de STAFF, los paneles conservan dimensiones y desplazan sólo su contenido, y la lista completa se presenta junta. El contenido anterior se oculta e inhabilita mientras cambia la categoría; el detalle muestra un estado neutral durante cada selección. Se preservan cabecera compacta, alta superior y selector geográfico. Aceptación visual del resultado PENDING.

Riesgos tratados: respuestas fuera de orden, foco/scroll, cuentas/casos diferentes, viewport móvil y confirmación de operaciones protegidas. Rollback: detener únicamente esta composición y volver al checkout R2 conservado; ningún DDL producto ni cambio a 8091.

## Seguridad canónica y límite de reutilización

| Operación | Política existente | Integración STAFF-CONTROL07 |
|---|---|---|
| Categorías, selección, datos administrativos permitidos | Sesión y ámbito actual en servidor; sin step-up | Ningún diálogo de contraseña/MFA |
| Grant/revoke ordinario de Advisor | Autorización/CSRF, confirmación explícita, writer y audit | Se conserva; no pide contraseña/MFA adicional |
| Documentos privados, decisión de identidad, admisión final | Contraseña y challenge/proof exactos de cada operación | Se conserva separación READ/ACCEPT/ADMIT |
| Nombramiento, retiro y delegación Master | Caso privilegiado, autoridad Director, reauth/MFA y gate propio | Independiente; no hereda prueba de admisión |

`EXISTING_STEPUP_REUSE_AVAILABLE=false` para estas acciones. `GovernanceSecurityContext::assertLive(recent)` valida un binding de reautenticación de menos de cinco minutos, pero no ofrece un ticket de gracia entre acciones HTTP de StaffWorkflow. `StaffWorkflow\Service::challenge` llama a `reauthenticate` en cada nueva intención y vincula actor/sesión/credencial/caso/conjunto/acción. `consume` y `StaffMfaService::consumeProof` rechazan expiración/replay; cada proof es de un solo uso. ACCEPT requiere consulta READ reciente del conjunto exacto; esa condición no sustituye el challenge de la decisión. ADMIT exige revisión positiva y proof propio. Crear un token reutilizable o saltar una contraseña cambiaría el contrato, y queda fuera de esta solicitud.

Fuentes: [consolidación funcional](ASE-ADM02-FUNCTIONAL-CONSOLIDATION.md), [fundación Master](ASE-INT03B-IMP01-master-security-foundation.md), [UI Master](ASE-INT03B-IMP02-master-ui-integration.md), [selección previa](STAFF-CONTROL06.md), [R2](STAFF-CONTROL06-R2.md). El gate HTTP Master conserva su limitación de revisión interna independiente; no se declara appointment operativo.

## Implementación y conexión

- `PRODUCT_POLICY`: contrato anterior preservado; no período de gracia, TTL, prueba reutilizable, autoridad o módulo nuevos.
- `SOURCE_IMPLEMENTATION`: CSS limita las regiones de scroll; Team mantiene controles y aplica listas de forma conjunta. Generaciones list/detail/case descartan respuestas previas; selección explícita prevalece sobre la automática. El foco del título no desplaza la ventana.
- `CONNECTIVITY`: mismos endpoints, servicios, readers, writers y audit. El diálogo identifica la operación y la cuenta autenticada, muestra contraseña y código en dos etapas y ejecuta una sola acción al verificar. Sólo correcciones/rechazo piden motivo. Botones bloqueados durante la solicitud, inputs borrados; no caché de credenciales. Denegaciones se presentan según el resultado HTTP conservador existente.
- `OPERATIONAL_STATUS`: infraestructura nueva owned sintética loopback. R2, runtime original/8091 y producción preservados. Sin activación de gobierno Master.

## Verificación y revisión

82 checks PASS en el cierre integrado. Máximo desplazamiento de controles/ventana: 0 px; suma de layout-shift observada en Chromium: 0. [Verificación final](../evidence/staff-control07/final-verification.json).

Evidencia dirigida: [resultado del runner](../evidence/staff-control07/qa.json), [mediciones por frame y carreras](../evidence/staff-control07/navigation.json), [cabecera/alta/geografía](../evidence/staff-control07/layout.json). Chromium nativo y 1440×900, 1366×768, 390×844; WebKit 1366×768 y 390×844. En Chromium se incluyen cambios con `hadRecentInput` en la observación de layout-shift; WebKit verifica geometría/scroll por frame, pues no ofrece esa API. La medición detectó inicialmente un movimiento de 45.6 px: la regla anterior `.feedback:empty` quitaba el espacio del estado vacío. La región ahora conserva su espacio y oculta sólo su contenido vacío.

Se verifican respuestas retenidas de categorías, cuentas y casos, selección exacta, campos por operación, footer del modal, rechazo de contraseña/MFA incorrectos y READ sin decisión/admisión implícita. El registro sintético Irene se creó en esta ejecución mediante el flujo canónico READ → ACCEPT → ADMIT y grants explícitos; se conserva al reanudar los ensayos, sin repetir la admisión ni contar de nuevo sus checks. Martha queda pendiente de decisión para inspección. Revocación/restauración ordinaria de permisos y logout/login se ejercitan con writers/Identity existentes. Master se verifica como gate independiente bloqueado; no se ejecutó un nombramiento. No se ha repetido la suite histórica.

Los timeouts de espera del catálogo y apertura protegida en el harness se alinearon al timeout HTTP existente de 20 segundos; no se modificó el timeout del producto. API, servicios, catálogo, esquema y seguridad permanecen idénticos al baseline. Safari físico y aceptación visual del Director quedan pendientes.

### Preview y comandos locales

URL Director: `https://127.0.0.1:61555/internal/media-review/index.php?section=equipo`.
URL Advisor: `https://127.0.0.1:61555/internal/staff-workspace/index.php`.
Caducidad: `2026-10-21T23:41:16-06:00` (America/Mexico_City); 24 h desde lanzamiento, sin extender sesiones/challenges. Sólo loopback y datos sintéticos. Cada navegador usa contexto independiente.

```sh
cd /Users/circulodigital/Documents/GitHub/mxmed-staff-control07-stable-navigation
python3 scripts/staff-control07-review.py /tmp/mxmed-ase-adm02imp01-ip9av40p status
python3 scripts/staff-control07-review.py /tmp/mxmed-ase-adm02imp01-ip9av40p credentials-director
python3 scripts/staff-control07-review.py /tmp/mxmed-ase-adm02imp01-ip9av40p mfa-director
python3 scripts/staff-control07-review.py /tmp/mxmed-ase-adm02imp01-ip9av40p open
python3 scripts/staff-control07-review.py /tmp/mxmed-ase-adm02imp01-ip9av40p stop
```

Los comandos de contraseña/código copian sólo el valor sintético al portapapeles. Cuenta Director: `qa_director@example.test`; usar un código vigente y no consumido. `open` abre Director y Advisor con login legítimo o enfoca sus ventanas existentes. Si la sesión caducó dentro de una ventana, iniciar sesión en Identity usando la contraseña recuperada; para abrir un navegador nuevo, cerrar esa ventana aislada y ejecutar `open-director`. `stop` señala sólo al runner dueño; éste cierra su composición. Después de su caducidad, este launcher falla cerrado: para crear otra instancia se requiere el runner de QA aislado, no prorrogar tokens ni reaprovechar puertos.

Para los mapas de datos: `CONTEXT_CHANGE=NO_IMPACT`; no hay objetos, relaciones, fuentes de verdad ni escrituras nuevas.

## CTX-SYNC01

```ini
CONTEXT_BASELINE=01d17ac8726f827841fcc8fc83c24b9f9ea003a6
AFFECTED_CAPABILITY_IDS=ID-02,ID-03,ID-07,COM-10; mismas autoridades
AFFECTED_JOURNEY_IDS=J09; navegación de equipo y revisión de identidad
AFFECTED_DATA_OBJECTS=ninguno modificado; lecturas existentes y pruebas canónicas
AFFECTED_CONTEXT_FILES=STAFF-CONTROL07; estado; router; registro visual; inventario; interacciones
CONTEXT_CHANGES_IMPLEMENTED=regiones estables, lista conjunta, generación de selección y step-up por acción
CONTEXT_VERIFICATION=QA dirigida PASS; fuentes/pins y contextos protegidos idénticos; enlaces locales verificados
CONTEXT_SYNC_STATUS=PASS_UPDATED
DOCUMENTATION_COMMIT=incluido en commit aislado; hash en entrega
REMAINING_CONTEXT_GAPS=aceptación visual; Safari físico; gobierno Master operativo fuera de alcance
```

## Sucesor del visor documental

[STAFF-DOC-VIS01](STAFF-DOC-VIS01.md) mejora sólo el visor privado y conserva navegación/step-up aquí documentados.
