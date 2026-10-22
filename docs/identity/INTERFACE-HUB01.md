# INTERFACE-HUB01 — navegación funcional de revisión local

Baseline `4fc81b61b51b940e0c6c102a9f2ba2355c11790c`, rama `feature/mxmed-interface-hub01`. Descendiente verificado de CROSS-SURFACE-UX05; conserva [CLOSURE02](ADVISOR-CAPABILITY-CLOSURE02.md) y sus 17 capacidades sin modificar grants ni datos.

## PRODUCT_POLICY

UI-2 autorizada por INTERFACE-HUB01: seis entradas, apertura/enfoque entre ventanas independientes y barra compacta persistente. No habilita nuevas autoridades médicas, operadores, proveedores ni publicación. Aceptación visual pendiente.

## SOURCE_IMPLEMENTATION

[Launcher](../../scripts/interface-hub01.py) y [componente](../../tools/interface-hub01/navigation.js) nuevos, exclusivamente tooling de revisión. Reutilizan la instancia CLOSURE02 sana, su verificador de ownership, rama/HEAD, hashes, listeners loopback, TLS y expiración. No cambian su router, origen, source pins, DB ni sesiones anteriores.

El proceso propietario abre contextos Chromium separados y autentica staff mediante los formularios Identity existentes y sus credenciales exclusivamente sintéticas. No importa cookies. Una nueva acción enfoca la misma página y conserva su selección; cerrar una ventana permite volver a abrirla con contexto independiente. No renueva automáticamente una sesión expirada ni modifica validez Identity/MFA.

Un binding Playwright limitado a enums conocidos abre/enfoca ventanas. Sólo acepta páginas principales administradas y orígenes exactos. Rechaza parámetros arbitrarios y superficies no preparadas. No hay endpoint HTTP de comandos, shell, argumentos de proceso suministrados por browser ni credenciales en el componente. El servidor HTTPS propio sólo entrega GET `/`, escucha en 127.0.0.1 y rechaza POST. La barra se inyecta únicamente en estas ventanas, persiste tras navegación/refresh y reemplaza visualmente el índice histórico de copiar comandos. No se agrega al HTML servido por el runtime original ni a producción.

El login médico permanece manual en el origen original, sin leer/resetear/copiar su contraseña. La ruta médica no se habilita hasta que el login original responde 200 y contiene su formulario canónico. Posteriormente se verifica el doctor_id del bootstrap autorizado, sin fabricar una sesión ni sustituir el resolver de membresías. La herramienta no guarda contraseñas médicas ni datos clínicos.

## CONNECTIVITY

| Superficie | Estado comprobado |
|---|---|
| Director | Login sintético real; abrir/enfocar desde el hub y Asesor. |
| Asesor | Login sintético independiente; Leticia seleccionada mediante búsqueda y reader UX05 original. El enfoque conserva la selección. |
| Médico | **BLOCKED_ORIGINAL_IDENTITY_503**. La cuenta preferida tiene membresía única activa, colaborador/profile/manual_review, para `mxmed-local-development:1`; eso no equivale a una sesión autenticada. |
| Operador | Existe shell/consumidor, pero falta cuenta/contexto granular preparado y verificado. No se convierte al colaborador médico en operador. |
| Público | Fuente original `candidate`, `route_enabled=0`, `canonical_enabled=0`. No se abre una ruta supuestamente publicada. |
| Proveedor | Existe portal; falta sesión y relación organizacional verificadas para este contexto. No se crean organizaciones de demostración. |

Diagnóstico médico de sólo lectura: `/private-app.php` y `/public/identity/acceso.php` originales responden HTTP503. Falta el directorio privado de notificaciones exigido por `NormalLocalIdentityConfiguration`; validación canónica devuelve `normal_local_notification_path_required`. Valkey escucha. No se restableció el directorio, reinició el servidor, cambió configuración ni extendió una sesión. **Siguiente acción mínima fuera de este alcance:** autorizar la restauración acotada del directorio original con sus permisos canónicos, verificar Identity y efectuar login manual. No hace falta crear membresías ni copiar credenciales.

Comparación entre fichas médicas: pendiente porque el panel original no pudo autenticarse. Sólo se verificó la selección de Leticia en Asesor y la preservación de las fuentes administrativas. No se declara comparación visual completa, ni acceso a pacientes/agenda/expediente desde staff.

## VERIFICATION

[QA dirigida](../../tools/interface-hub01/qa.py): Chromium visible, botones reales, sesiones D/A distintas, selección preservada, refresh, seis entradas, cuatro contextos no disponibles y diseño 1366×768/390×844. Denegaciones de acciones arbitrarias, página no administrada y POST/endpoint HTTP inexistente. [Evidencia](../evidence/interface-hub01/browser-checks.json).

[Inspección acotada](../../tools/interface-hub01/inspect.py): transacción READ ONLY, membresía y gates, fingerprints de los cuatro conjuntos originales iguales al recibo UX05; no lectura de credenciales médicas. [Preservación](../evidence/interface-hub01/source-preservation.json). Listeners8091/8443 y HEAD original permanecen iguales. [Boundary HTTP](../evidence/interface-hub01/normal-runtime-boundary.json): componente/binding ausentes en respuestas normales; registra el503 sin presentarlo como login exitoso.

En el primer ensayo se abrió la ruta médica antes de comprobar su salud; se corrigió el tooling para verificar disponibilidad y deshabilitar ese acceso ante503. No hubo autenticación médica ni intervención original. No se repite la suite de14 grants ni pruebas históricas.

## OPERATIONAL_STATUS / guía

Sólo revisión local; reutiliza `/tmp/mxmed-ase-adm02imp01-83vg864p`, expira **2026-10-22 23:56:19 -06:00 (CDMX)**. No se amplía la vigencia de infraestructura ni Identity/MFA.

```sh
python3 /Users/circulodigital/Documents/GitHub/mxmed-interface-hub01/scripts/interface-hub01.py /tmp/mxmed-ase-adm02imp01-83vg864p start
python3 /Users/circulodigital/Documents/GitHub/mxmed-interface-hub01/scripts/interface-hub01.py /tmp/mxmed-ase-adm02imp01-83vg864p status
python3 /Users/circulodigital/Documents/GitHub/mxmed-interface-hub01/scripts/interface-hub01.py /tmp/mxmed-ase-adm02imp01-83vg864p open
```

`start` reutiliza el hub si ya está abierto; `open` enfoca o inicia sus ventanas. `status` entrega la URL vigente del hub y rechaza un estado sin heartbeat reciente. Director y Asesor siguen en `https://127.0.0.1:54400/internal/media-review/index.php?section=equipo` y `https://127.0.0.1:54400/internal/staff-workspace/index.php` respectivamente. El hub cambia de puerto al reiniciarlo; usar su launcher, no un enlace antiguo.

El mismo comando admite `credentials-director`, `credentials-advisor`, `mfa-director` y `mfa-advisor`: copia al portapapeles desde el launcher sintético existente, sin imprimir secretos. `open-director` / `open-advisor` enfocan cada ventana. Login médico: manual con la cuenta preferida únicamente cuando Identity original esté disponible.

Para cerrar sólo el hub y sus ventanas, preservando el preview anterior:
```sh
python3 /Users/circulodigital/Documents/GitHub/mxmed-interface-hub01/scripts/interface-hub01.py /tmp/mxmed-ase-adm02imp01-83vg864p stop
```
Para terminar también la infraestructura desechable base, el comando existente notifica únicamente a su propietario:
```sh
python3 /Users/circulodigital/Documents/GitHub/mxmed-advisor-capability-closure02/scripts/advisor-capability-closure02-review.py /tmp/mxmed-ase-adm02imp01-83vg864p stop
```

## CTX-SYNC01

- CONTEXT_BASELINE: `4fc81b61b51b940e0c6c102a9f2ba2355c11790c`, UX05 incluido.
- AFFECTED_CAPABILITY_IDS: ID-02, ID-03, COM-10 (navegación de revisión; no nuevas facultades); PUB-02, OP-01 y ORG-01 conservan sus límites.
- AFFECTED_JOURNEY_IDS: J09 navegación staff; límite J07 sin activación nueva.
- AFFECTED_DATA_OBJECTS: sólo estado efímero del hub; Identity/membership/SEO y proyecciones originales leídos, sin writer nuevo.
- AFFECTED_CONTEXT_FILES: este contrato, estado actual, router, inventario funcional e interacciones.
- CONTEXT_CHANGES_IMPLEMENTED: índice ejecutable en navegador administrado, preservación y bloqueo médico503 documentados.
- CONTEXT_VERIFICATION: diff, rutas locales, QA dirigida, evidencia HTTP/fingerprints y jerarquía de fuentes.
- CONTEXT_SYNC_STATUS: PASS_UPDATED.
- DOCUMENTATION_COMMIT: incluido en el commit de INTERFACE-HUB01; consultar Git de este archivo.
- REMAINING_CONTEXT_GAPS: login/comparación médica pendiente por503; operador/proveedor sin contexto preparado; publicación deshabilitada; aceptación visual PENDING.

## IDENTITY-RECOVERY01 — recuperación local autorizada, 2026-10-22

**Sucesor del bloqueo503 anterior.** Baseline `3ebd8af4f21dc323451796ba7f1e2acf597b97b4`; rama documental `feature/mxmed-identity-recovery01`. El Director autorizó exclusivamente reparar el prerrequisito local verificado, sin cambiar cuentas, membresías, reglas de autenticación ni servicios originales.

PRODUCT_POLICY: autorización expresa para restaurar directorio/configuración local mínima; login médico exclusivamente interactivo. SOURCE_IMPLEMENTATION: **sin cambios de código ni configuración**. Se comprobó el path canónico configurado `/tmp/mxmed-normal-local-identity-501`, ausente y sin symlink; se creó sólo ese directorio con propietario UID501 y modo0700, fuera del document root. No existían archivos en ese directorio ausente; no se borraron, reemplazaron ni recuperaron notificaciones históricas. El fallo canónico era `normal_local_notification_path_required`; log reciente registraba503 sin fatal. Después de crear el directorio, la composición Identity completa devuelve `IDENTITY_READY`, incluyendo verificaciones de schema/conexión y store. No hubo reinicios, migraciones, DDL, nuevas credenciales ni cambios de configuración.

CONNECTIVITY: login original200 y `/private-app.php` redirige al formulario canónico. El hub existente, mismo PID92727 y URL `https://127.0.0.1:57452/`, detectó la recuperación mediante su probe periódico y abrió la ventana médica independiente. No fue necesario modificarlo ni cerrar/recrear las ventanas Director/Asesor. Sus sesiones no se reautenticaron ni se amplió su vigencia; el status visual heredado no se usa como prueba nueva de vigencia del token. La sesión médica queda **PENDING_MANUAL_LOGIN**, no autenticada por Codex. La membresía única activa de colaborador de la cuenta preferida para doctor1 se revalidó por lectura; no implica facultades owner-only.

VERIFICATION: [Chromium dirigido](../evidence/identity-recovery01/browser.json), siete checks:200, campos email/password visibles, redirección privada al login, bootstrap médico ausente sin autenticación, current-session anónimo401 y contexto sin cookies staff. [Preservación](../evidence/identity-recovery01/preservation.json): fingerprints originales iguales a UX05, membresía/gates y HEAD/listeners originales intactos. [Estado del hub](../evidence/identity-recovery01/hub-status.json): ventanas previas conservadas y ventana médica abierta. MFA, CSRF, reglas de membresía y TTL conservan exactamente su código original; no se ejecutó un reto MFA real ni se probaron credenciales reales. No se repitieron suites históricas.

OPERATIONAL_STATUS: Identity normal-local recuperada mediante reparación autorizada del filesystem, **no activación productiva**. Login manual y comparación posterior (nombre/especialidad/retrato/tres consultorios/domicilios/C.P./rol) pendientes. La existencia de datos en la proyección administrativa no acredita haberlos comparado contra una sesión médica autenticada. Ningún registro médico, clínico, relación o grant modificado. La causa de la desaparición previa del directorio no se ha establecido; no se instala un daemon ni mecanismo de recreación automática.

Reabrir/enfocar la ventana preparada:
```sh
python3 /Users/circulodigital/Documents/GitHub/mxmed-interface-hub01/scripts/interface-hub01.py /tmp/mxmed-ase-adm02imp01-83vg864p open-physician
```
El Director debe usar su contraseña habitual exclusivamente en `https://localhost:8443/public/identity/acceso.php`, con continuación hacia `/private-app.php`. No compartirla por chat. La infraestructura staff/hub conserva su expiración2026-10-22T23:56:19-06:00; no extiende Identity.

### CTX-SYNC01 — recuperación

- CONTEXT_BASELINE: `3ebd8af4f21dc323451796ba7f1e2acf597b97b4`.
- AFFECTED_CAPABILITY_IDS: ID-02, ID-03; sin facultades nuevas.
- AFFECTED_JOURNEY_IDS: J07 frontera médica y navegación J09.
- AFFECTED_DATA_OBJECTS: directorio privado de notificaciones local; ninguna mutación de negocio.
- AFFECTED_CONTEXT_FILES: este contrato/guía, estado actual y evidencia acotada.
- CONTEXT_CHANGES_IMPLEMENTED: bloqueo503 sucedido por recuperación verificada; autenticación manual pendiente explícita.
- CONTEXT_VERIFICATION: bootstrap completo, HTTP, Chromium, membresía/fingerprints y listeners originales.
- CONTEXT_SYNC_STATUS: PASS_UPDATED.
- DOCUMENTATION_COMMIT: commit documental IDENTITY-RECOVERY01, consultar Git de este archivo.
- REMAINING_CONTEXT_GAPS: autenticación interactiva y comparación médica pendientes; causa histórica de desaparición no determinada.
