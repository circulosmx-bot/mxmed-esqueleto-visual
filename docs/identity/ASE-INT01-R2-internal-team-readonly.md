# ASE-INT01 R2 — equipo interno: integración de consulta

Fecha: 2026-10-17. Rama `feature/ase-int01-internal-team-readonly`, worktree `mxmed-ase-int01-readonly`, derivada exclusivamente de fuente oficial `f92f37f1958af5140a96c04cb52c5c1ce7af8e01`. Fuente y upstream oficial limpios y coincidentes al inicio. No merge del prototipo, promoción ni activación normal-local.

## Aprobación y procedencia

R2 `d87174774de6526d54082c1b7a770b228c472746` ya estaba aprobado. Cuando se preguntó expresamente al Director si aprobaba visualmente las adiciones ASE-COM01 y ASE-ADM01, respondió **“apruebo”**. Ésa es la respuesta real registrada en ASE-INT01 R2; no se amplía la cita. El acumulado visual aprobado es `312ee388f93c892e6caf7e845b11e2b42f9c3565`; COM corresponde a `bee02e5106d443abeeecaa2a88ec8caf2c1db6d1`.

Historia inmutable en la rama original preservada:

- [ASE-VIS01 R1/R2 y contrato de componentes](https://github.com/circulosmx-bot/mxmed-esqueleto-visual/blob/312ee388f93c892e6caf7e845b11e2b42f9c3565/docs/identity/ASE-VIS01-advisor-matrix-prototype.md).
- [ASE-COM01 y D01–D08](https://github.com/circulosmx-bot/mxmed-esqueleto-visual/blob/312ee388f93c892e6caf7e845b11e2b42f9c3565/docs/identity/ASE-COM01-commercial-attribution-recurring-commission.md).
- [ASE-ADM01 y siete políticas pendientes](https://github.com/circulosmx-bot/mxmed-esqueleto-visual/blob/312ee388f93c892e6caf7e845b11e2b42f9c3565/docs/identity/ASE-ADM01-staff-admission-personal-profile.md).

Sus avisos históricos de aprobación COM/ADM pendiente quedan sucedidos aquí **sólo para presentación**. Se aprueban directorio, administración visual Advisor/Master, cinco grupos/tri-state, incorporación unificada, captación/condiciones/actividad propia, retrato/perfil, revisión documental, preparación asistida/remota y estados de revisión separados del acceso. No son aprobación de políticas financieras/de identidad pendientes ni habilitación de writers.

## Cinco ejes y estado efectivo

| Eje | Estado verificado |
|---|---|
| PRODUCT_POLICY | Diseño R2/COM/ADM aprobado; IW01 y ASE-ALIGN01 V2 conservan gobierno, grants exactos y delegaciones. COM D01–D08 y siete decisiones ADM pendientes. |
| SOURCE_IMPLEMENTATION | Nueva proyección mínima `Identity\Team` y GET/HEAD; sección `equipo` del workspace existente, sin nuevo login ni commands. |
| CONNECTIVITY | UI → `/api/internal/team/read.php` → composición/resolver de sesión canónicos → tablas staff/cuenta/grants/delegaciones. Conectado y probado sólo en preview aislado. |
| VERIFICATION | 76 checks HTTP/SQL/autoridad; 176 checks navegador (incluyen 24 calificaciones del diagnóstico de captura WebKit), ocho combinaciones; ocho checks del retorno login y ocho comparaciones visuales. Safari físico NOT_RUN; móvil smoke interino. |
| OPERATIONAL_STATUS | Sin merge, despliegue, cambios de datos reales o activación. Fuente oficial y prototipo intactos. |

```text
VISUAL_DESIGN_APPROVED=true
READ_ONLY_INTEGRATION_PREPARED=true
READ_ONLY_UI_CONNECTED=true (preview aislado únicamente)
REAL_ADMISSION_IMPLEMENTED=false
REAL_DOCUMENT_REVIEW_IMPLEMENTED=false
REAL_GRANT_MUTATIONS_ENABLED=false
REAL_MASTER_APPOINTMENTS_ENABLED=false
COMMERCIAL_COMMISSION_ENGINE_ENABLED=false
OPERATIONAL_ACTIVATION=false
```

## Componentes reutilizados y límite HTTP

Se extiende `internal/media-review/index.php?section=equipo` antes del guard Media, con autorización independiente en `Team\Page` y `Team\Http`; los tres guards previos Media/C4/correcciones no cambian. Se añade enlace a Equipo en sus navegaciones existentes. El retorno del login canónico admite únicamente el destino exacto de Equipo, igual que las otras secciones staff: no exige una membresía clínica a staff; el destino vuelve a validar autoridad canónica. No hay otro mecanismo de sesión, writer de password ni cambio de acceso clínico.

`IdentityHttpComposition` y `CanonicalHttpSessionResolver` validan cookie canónica, store, cuenta, credencial/versiones y expiración. El lifecycle canónico puede actualizar/revocar una sesión en el store; no es una mutación de staff. No se usa PHP session, un rol enviado por cliente ni `InternalGovernanceService::execute`.

Directorio y detalle sólo GET/HEAD, `private, no-store`, no-cache, CORP same-origin, nosniff, no-referrer, CSP self con frame/object/base/form bloqueados. Origin ajeno y Fetch-Site cross-site/same-site se deniegan; sin CORS. POST/PUT/PATCH/DELETE → 405, anónimo/inválido → 401, staff no autorizado → 403, target ausente/fuera de categoría → 404, composición/fuente no disponible → 503 genérico. HEAD no entrega cuerpo. No endpoints nuevos de escritura, descarga privada, documentos o finanzas.

### Autoridad y minimización

- DIRECTOR: cuenta y staff ACTIVE actuales; lee Advisors y Masters bajo gobierno raíz. No recibe uso de otros módulos por clase; la fixture Director sin Media prueba esta independencia.
- MASTER_ADMIN: cuenta/staff ACTIVE y `internal_advisors_manage` vigente; sólo categoría/targets ADVISOR. Relee sus delegaciones exactas cada request y sólo devuelve grants/conteos dentro de ellas. No lee Masters, Director, self ni datos administrativos fuera del ámbito. Target Master y target inexistente producen el mismo 404.
- ADVISOR, incluso con Media: sin directorio. Cuenta deshabilitada, sesión expirada/revocada/version mismatch, staff suspendido o management revocado se deniegan. Revocación se refleja en la siguiente lectura; no se promete una transacción global contra cambios concurrentes.

`ReadService` usa sólo SELECT preparado nativo. Proyecta referencia exacta de cuenta, clase, status de cuenta/staff, grants activos no revocados conocidos, conteo visible y delegaciones del Master sólo para Director. No emails, credenciales, tokens, IDs de sesión, MFA o documentos. Fuera de staff no se enumeran cuentas. Búsqueda literal por referencia staff (2–64 caracteres ASCII `[A-Za-z0-9_.:-]`); page 1–1000, page_size 1–50 (UI20), un registro extra para has_more; sin COUNT global. Se rechazan arrays, query desconocida/repetida y valores ambiguos.

`personal_profile`, `identity_review`, `commercial_activity` e `history` son null/NOT_CONNECTED. Retrato neutral, nombre no disponible, contacto sin conectar y revisión aún no registrada; ACTIVE no se reinterpreta como verificación. No se fabrican nombres desde emails/IDs. Permisos registrados, autoridad de administración, uso efectivo y disponibilidad del consumidor son hechos diferentes. La matriz usa `InternalCapabilityCatalog::all()`: 18 definiciones, 16 operativas, dos reservadas, cinco grupos visuales; nunca grant de familia ni nuevas claves comerciales. En Master, datos fuera del ámbito son desconocidos/mixtos, no «sin asignación». Los cuatro `profile_*` siguen desconectados; correcciones no se habilita para Advisor; cada consumidor conserva gates propios.

## Comercial y admisión pendientes

Un asesor originador por cliente y código autorizado; atribución para renovaciones pagadas mensuales/anuales elegibles, relación pagada y perfil elegible, sin reingresar código. Registro/cliente FREE no genera comisión; el 15% histórico es descuento del cliente, no tasa de comisión ni descuento vivo. D01 tasa, D02 base/cargos/impuestos, D03 alcance/duración descuento, D04 liquidación mensual/anual, D05 refund/fallos/reactivación, D06 salida/suspensión, D07 correcciones/transferencias, D08 upgrades/parcialidades siguen sin defaults. No hay códigos, balances, cartera ni pagos conectados. Actividad propia deshabilitada.

Las siete políticas ADM permanecen en el contrato histórico enlazado, sin inferir aprobación por diseño. No se crean perfil personal, documentos, casos, invitaciones, activación o MFA. C4 no se usa para IDs staff. Los controles de alta, lifecycle, Master, grants/delegación, evidencia y guardado permanecen deshabilitados y sin handlers de mutación.

`InternalGovernanceService` conserva un comando por transacción, locks/auditoría existentes; no se afirma guardado grupal atómico. Futuro writer requiere snapshots autoritativos, concurrencia, auditoría por comando, conciliación parcial y retries seguros; admisión requiere invitación/caso/evidencia propios; Master un límite privilegiado aprobado; comisiones reglas/versiones y conciliación de pagos separadas.

## Preservación visual y diferencias explícitas

Las tres hojas R2/COM/ADM se copian byte por byte del commit aprobado; no app.js, catálogo exportado, personas, retratos, evidencia ni fixtures del prototipo. Reuso de CSS Media más una hoja acotada para navegación/paginación y estados ausentes. [Comparación reproducible](../../scripts/ase-int01-visual.py) y [resultado](../evidence/ase-int01/visual-comparison.json): mismos anchos, columnas/gap, fuentes, colores y radios en Chromium/WebKit a 1440×900, 1366×768, 820×1180 y 390×844.

Diferencias necesarias, declaradas para revisión (sin aprobación visual nueva inventada): navegación staff y badge sólo consulta sustituyen controles de actor ficticio; búsqueda por referencia/paginación sustituye nombres ficticios; retrato neutral/contactos ausentes/revisión desconocida sustituyen personas y documentación simuladas; visibilidad de grants limitada por delegación; alta/comercial/history sin datos ficticios ni interacción de escritura. Alturas y contenido cambian; **no se afirma igualdad de píxeles**. [Captura desktop](../evidence/ase-int01/browser/chromium-1440x900-directory.png), [móvil](../evidence/ase-int01/browser/webkit-390x844-directory.png), [detalle Master](../evidence/ase-int01/browser/webkit-1440x900-master.png).

## QA aislada, revisión y reversibilidad

[Runner](../../scripts/ase-int01-isolated.py) crea instancia MySQL nueva en datadir temporal propio, Valkey privado sin persistencia, PHP del feature y TLS loopback propio. No clona ni conecta a mxmed/QA05/OP02/UI03, no lee staff/clientes reales. Sólo cinco DDL fuente en esa instancia y actores sintéticos; sesiones creadas mediante autenticación y SessionService canónicos. Principal HTTP SELECT-only, cuyo UPDATE está comprobado denegado. Config, password, pepper, TLS y tokens permanecen modo privado fuera de Git; no se imprimen.

URL: `https://127.0.0.1:8141/internal/media-review/index.php?section=equipo`. Sin sesión devuelve 401 correctamente. Revisión autenticada sintética, **sin actuar como el Director real ni usar Safari original**:

```sh
cd /Users/circulodigital/Documents/GitHub/mxmed-ase-int01-readonly
python3 scripts/ase-int01-isolated.py review
```

Abre un contexto Chromium temporal separado con sesión canónica de la fixture Director; cerrar la ventana termina ese contexto. El preview expira ocho horas desde start; la composición de prueba bloquea rutas ajenas y todos los writers. La instalación final de esta entrega expira `2026-10-18T12:52:35.593564+00:00`. Reproducir/retirar sólo recursos propios:

```sh
python3 scripts/ase-int01-isolated.py stop
python3 scripts/ase-int01-isolated.py start
python3 scripts/ase-int01-qa.py
node modules/identity/tests/TeamReadDestinationTest.mjs
python3 scripts/ase-int01-visual.py
```

Stop verifica PIDs/comandos propios, espera terminación y elimina datadir, secretos y pointer. Start/stop/start comprobado; listeners originales 8091/8092/8443/6385 y prototipo8140 conservados. Ningún reset de fixtures persistentes. [Aislamiento](../evidence/ase-int01/isolation.json).

[QA HTTP/SQL](../evidence/ase-int01/qa.json) y [navegadores/capturas](../evidence/ase-int01/browser/summary.json) prueban sesión/rol/scope/revocaciones, paginación/búsqueda, mismo origen, métodos, privacidad, loading/error503/empty, navegación, teclado/collapse, límites responsive y controles deshabilitados. Digests de cinco tablas iguales durante reads/denials HTTP; experimentos de revocación sólo sobre fixture propia y restaurados aparte (sus updated_at cambian). 403/503 deliberados se registran, no se ocultan como éxito. Diagnóstico WebKit exacto del screenshotter `body {}` rechazado por CSP se distingue de errores de aplicación; no se relaja CSP. Sin pruebas físicas Safari ni activación normal-local. La comparación compartida no es aprobación de las diferencias necesarias.

## Recuperación dirigida de evidencia

La auditoría posterior a la interrupción clasificó la entrega como `CODE_COMPLETE_QA_PENDING`: el commit `230dc03610bd0c235e2c35d8d9d19bfe5b14d9d6` estaba publicado y limpio, pero no se localizó una salida independiente de `TeamReadDestinationTest.mjs`. La afirmación histórica de ocho PASS se conserva como tal; el cierre siguiente no reconstruye ni acredita retroactivamente aquella ejecución.

Con autorización específica del Director se inspeccionaron el test y las funciones extraídas de `public/identity/identity-access.js`, y se ejecutó únicamente `node modules/identity/tests/TeamReadDestinationTest.mjs` sobre ese commit. La nueva ejecución posterior a la recuperación produjo **PASS, ocho checks, exit code 0 y stderr vacío**. El [registro independiente](../evidence/ase-int01/login-return-recovery.json) conserva timestamps UTC reales, stdout/stderr exactos, versión Node y SHA-256 de ambos archivos antes/después. Sólo lectura local de fuente y VM con dobles en memoria de fetch, navegación y almacenamiento; sin red, SQL, staff, sesiones reales ni servicios operacionales. No prueba autenticación de servidor.

`RESULT=READY_MXMED_ASE_INT01_R2_QA_GAP_CLOSED` cierra exclusivamente la falta de evidencia independiente del retorno-login. Los 76 checks HTTP/SQL, 176 navegador y ocho comparaciones visuales originales permanecen como evidencia histórica PASS, sin reejecución ni sobrescritura; los 53 archivos previos de evidencia quedan vinculados por un manifiesto SHA-256. No hay cambios de producto ni promoción. La revisión de diferencias visuales por el Director sigue PENDING, Safari físico NOT_RUN, finalización móvil PENDING y activación operacional FALSE.

### CTX-SYNC01 del cierre dirigido

| Campo | Registro del cierre de evidencia |
|---|---|
| CONTEXT_BASELINE | Integración `230dc03610bd0c235e2c35d8d9d19bfe5b14d9d6`; fuente oficial `f92f37f1958af5140a96c04cb52c5c1ce7af8e01`; visual `312ee388f93c892e6caf7e845b11e2b42f9c3565`. |
| AFFECTED_CAPABILITY_IDS | ID-02/ID-03, retorno de login; ID-07 como destino staff. Sólo evidencia, sin nuevas capacidades. |
| AFFECTED_JOURNEY_IDS | J09, retorno al workspace; comportamiento intacto. |
| AFFECTED_DATA_OBJECTS | Ninguno leído o escrito en bases; sólo archivos locales de fuente y evidencia. |
| AFFECTED_CONTEXT_FILES | Este contrato, `docs/ESTADO_ACTUAL_MXMED.md` y `docs/evidence/ase-int01/login-return-recovery.json`. Router/mapas conservados: no cambia ubicación ni contrato funcional. |
| CONTEXT_CHANGES_IMPLEMENTED | Se sucede el estado de evidencia no localizada por un resultado independiente posterior a la recuperación, preservando el histórico y sus límites. |
| CONTEXT_VERIFICATION | Salida/exit reales, dos hashes de fuente sin cambios y coincidentes con QA histórica; preservación de evidencia previa, revisión de diff y enlaces locales del cierre. |
| CONTEXT_SYNC_STATUS | PASS_UPDATED |
| DOCUMENTATION_COMMIT | Commit documental posterior a `230dc036`; resolver con `git log -- docs/evidence/ase-int01/login-return-recovery.json`. La tabla siguiente conserva el registro de la implementación original. |
| REMAINING_CONTEXT_GAPS | Revisión visual del Director, Safari físico, móvil final; siete políticas ADM, D01–D08, readers pendientes, writers y autorización operacional sin cambios. |

## CTX-SYNC01

| Campo | Registro de esta entrega |
|---|---|
| CONTEXT_BASELINE | Fuente f92f37f; visual312ee388; CTX-MAP02 1b8dfccb/CTX-MAP03 0bea8195 e IW01/ASE-ALIGN01 V2 contrastados. |
| AFFECTED_CAPABILITY_IDS | ID-02/ID-03 (sólo retorno/reuso sesión), ID-07 y COM-10; catálogo canónico intacto. |
| AFFECTED_JOURNEY_IDS | J09 workspace/navegación; J08 sólo límites comerciales, no cadena pagos cambiada. |
| AFFECTED_DATA_OBJECTS | Lectura auth_accounts/internal_staff/internal_operator_grants/internal_capability_delegations; auth_account_credentials únicamente validador canónico. Sin tablas nuevas/DDL real. |
| AFFECTED_CONTEXT_FILES | Este contrato, estado, router, registro visual, IW01, inventario ID-07, interacciones J09, mapa de datos y trazabilidad COM-10. |
| CONTEXT_CHANGES_IMPLEMENTED | Aprobación real importada; sucesores de frases «sin UI» limitados a read-only; reader/autoridad/gaps/evidencia y cinco ejes documentados. Historia y censos119/11/35 conservados. |
| CONTEXT_VERIFICATION | Enlaces/paths locales y referencias Git históricas, bind SHA256 QA, diff/aislamiento y coherencia fuente→reader→UI→evidencia verificados. |
| CONTEXT_SYNC_STATUS | PASS_UPDATED |
| DOCUMENTATION_COMMIT | Mismo commit feature que la implementación; resolver mediante git log de este archivo, sin hash circular embebido. |
| REMAINING_CONTEXT_GAPS | Siete políticas ADM, D01–D08, readers personales/evidencia/comercial/history, writers seguros, Safari físico/móvil final y autorización operacional. |

NEXT_AUTHORIZATION_REQUIRED: revisión de diferencias de esta integración y fase explícita separada para cualquier reader sensible, política pendiente, writer, admisión, Master, finanzas, merge/despliegue o activación. Esta entrega sólo queda lista para revisión read-only.

## Sucesor backend aislado ASE-INT02A

[ASE-INT02A](ASE-INT02A-governed-advisor-grants.md), derivado del cierre `09d3aefc`, añade exclusivamente el adapter grant/revoke individual para ADVISOR activo, con precondición transaccional y CSRF canónicos. Sucede la ausencia de adapter de commands sólo en la nueva feature; esta UI y toda evidencia ASE-INT01 permanecen intactas. No habilita guardado, grupos, Master, admisión ni operación real.
