# MED-CONSULTORIOS-INTEGRATION01 — preflight protegido

## Autorización y alcance

2026-10-22, America/Mexico_City. **PRODUCT_POLICY:** el Director autoriza inspección, preparación y QA aislada, no despliegue. Leticia (`mxmed-local-development:1`) sigue siendo la referencia manual; Aurora sólo fixture sintético. No copiar registros, claves ni sesiones del original.

**Original actual verificado:** worktree `/Users/circulodigital/Documents/GitHub/mxmed-step2-vitals-r1`, rama `ux/consultation-step2-vitals-r1`, HEAD `f92f37f1958af5140a96c04cb52c5c1ce7af8e01`, limpio. Fuente probada INIT02/03: `615d4655fd7812d86c5f03470335eb989b6f1412`, rama `feature/mxmed-med-consultorios-init02`, limpia. Candidato independiente: `feature/mxmed-med-consultorios-integration01`, creado desde el HEAD original actual, aplicando únicamente el diff de seis archivos por hunks. No cherry-pick completo de INIT02/03.

**CONNECTIVITY actual:** PHP PID 90106 sirve el docroot original en 8091; TLS PID 93582 conserva 8443. Los cuatro archivos JS servidos por HTTPS coinciden byte a byte con el original actual. La sesión Safari autenticada de doctor 1 conserva `app.js?v=op-int04`, header-account sin versión, ordering `cons-vis01`, multisede `cons-imp02b`; no tiene el estado del loader nuevo. PHP se verificó por docroot/proceso y hashes en disco, sin instalar sondas ni inspeccionar bytecode OPcache en el proceso protegido. [Observación de Safari](../evidence/med-consultorios-integration01/original-safari-before.json).

## Matriz de compatibilidad por archivo

Todos los archivos actuales coinciden con el baseline f92f37f1: **sin cambios posteriores ni conflictos**. [Manifiesto de hashes antes/después](../evidence/med-consultorios-integration01/files.json). Los seis requieren modificación; no hay archivos añadidos al paquete desplegable.

| Archivo | Hunks necesarios | Dependencias y compatibilidad |
|---|---|---|
| `private-app.php` | Notice de carga/error/reintento/EMPTY; wrapper hidden/inert del editor; versiones de cuatro scripts. | Conserva gate Identity, shell, encabezado y secciones restantes. IDs/markup existentes preservados; sin CSS nuevo. |
| `assets/js/perfil/consultorio/multisede.js` | Máquina LOADING/READY/EMPTY/ERROR/RETRYING; validación de respuesta/ámbito/IDs; cancelación/timeout/generación; reconciliación de sedes; dirty/revisión/fingerprint de payload; suprimir materialización y autosave de hidratación; geocodificación sólo por intención. | GET/PUT `/api/agenda/index.php/consultorios`; `meta.doctor_id_effective`, IDs 1–3, presentation_order/fingerprint; emergency-contacts, medical-groups y Maps conservan contratos. Body doctor canónico primero; sin nuevos writers ni grants. |
| `assets/js/perfil/consultorio/consultorio-ordering.js` | Excluir plantillas hidden; no persistir fuera de READY; no resetear orden confirmado al crear panes durante hidratación. | Eventos `mx:consultorios-hydrated`, `mx:consultorio-pane-created`; PUT `/consultorios/reorder` con fingerprint. Primario y orden siguen siendo canónicos. |
| `assets/js/app.js` | Un hunk de presentación de header en applyIdentity: prefijo Dr./Dra. canónico, sin duplicación. | `identity_public.prefix/display_name` del reader privado; no cambia formularios, clínica, Agenda ni persistencia del nombre. No reemplazar este archivo completo con una copia antigua. |
| `assets/js/core/header-account.js` | Preferir `professional.presentation_name`, fallback display_name. | Contexto de private-session; mantiene guard del workspace de operadores, fotografía, metadata, plan, logout. |
| `modules/identity/http/PrivateApplicationAccessResolver.php` | Leer columna prefix existente; campo aditivo presentation_name limitado a Dr./Dra.; reutilizar PublicNamePresentation. | Servicio ya presente e idéntico en original; sin migración. Cuenta, selección de membresía, scope, capacidades, cookies, MFA y duración de sesión permanecen sin cambio. |

**SOURCE_IMPLEMENTATION:** parche exacto [deployment.patch](../evidence/med-consultorios-integration01/deployment.patch), 234 inserciones/175 eliminaciones de producto, seis archivos. El contenido resultante de cada uno coincide con la fuente 615d4655. No aplicar el resto del commit candidato al runtime: contiene sólo soporte QA/contexto/evidencia, que no forma parte del despliegue. Todas las hojas CSS y markup del encabezado permanecen idénticos al original. Cero cambios de APIs Agenda, repositorios, modelos clínicos o esquema. Syntax PHP/JS y whitespace del código pasan. El archivo .patch conserva los espacios de contexto exigidos por el formato unified diff; se valida con apply/check y hashes, no recortando esos espacios. La restricción preparada por rol de Agenda no se activa ni se amplía: no confundir QA de scope con una nueva garantía de permisos de escritura collaborator.

## Verificación independiente

Infraestructura propia nueva `/tmp/mxmed-init02-7f57_ka1`, origen QA `https://127.0.0.1:60562`, MySQL loopback 60560 y Valkey propios; fixture canónico sintético nuevo. Cookie distinta `__Host-mxmed_integration01_session`. Ni el preview INIT02 anterior ni Director/Advisor se reiniciaron. El runner sólo extrae esquema del original (sin registros); todas las escrituras de seed/edición/orden se limitan a la nueva base desechable.

- Loader: 36 casos PASS, 18 Chromium + 18 WebKit. Primera carga de tres sedes, HTTP/red/timeout/JSON/shape/ámbito/IDs inválidos, EMPTY, retry, navegación repetida, respuesta tardía, cuenta collaborator, otro médico y edición real/restauración mediante PUT. Cero escrituras inesperadas; dos PUT de edición/restauración por motor. [Resultados](../evidence/med-consultorios-init02/results.json).
- Estados visibles: LOADING/RETRYING, aria-busy, ocultamiento/inert, reintento por teclado, orden confirmado y lookup explícito de mapa PASS en ambos motores. [Resultados](../evidence/med-consultorios-init02/states.json). El harness original pulsaba nuevamente una sección restaurada tras reload; la prueba visible usa ahora un contexto nuevo con la misma sesión sintética válida. No se cambió navegación de producto. El suite de 36 conserva su prueba separada de reload. Hubo avisos Playwright de cleanup de peticiones abortadas en timeout; ningún pageerror/aserción de producto.
- Prefijo/autoridad: siete pruebas por motor PASS, incluyendo contexto canónico, rehidratación sin prefijo legacy, no duplicación, rechazo de médico original y de petición sin sesión, cookie propia y cero autosave. [Chromium](../evidence/med-consultorios-init03/chromium-results.json), [WebKit](../evidence/med-consultorios-init03/webkit-results.json).
- Identity: contrato PrivateSession02 con 19 comprobaciones PASS; gates, 401/403, single-flight, logout, bfcache, multitab, abort, destino de login y no replay. [Salida](../evidence/med-consultorios-integration01/identity-contract.txt). No se ejecutan suites que borren cuentas o grants persistentes.
- Header: screenshots/estilos calculados en Chromium y WebKit a 1694/768/390; Asap, peso 800 y geometría responsive original. [Evidencia](../evidence/med-consultorios-integration01/header/chromium.json). Safari original conserva su zoom y datos; se compara contrato CSS/markup, no igualdad de píxeles entre perfiles/ventanas distintos.
- Orden e Identity dinámica: [resultado específico](../evidence/med-consultorios-integration01/ordering-identity.json): movimiento por teclado con PUT real, primario, persistencia tras reload, rechazo de fingerprint obsoleto, rollback visual ante 503, restitución del orden sintético original, logout/login canónico. PASS en ambos motores; el orden inicial queda restituido.

Reproducción: iniciar el runner en este worktree; ejecutar en orden `med-consultorios-init02-qa.py INSTANCE`, `med-consultorios-init02-states-qa.py INSTANCE`, `med-consultorios-init03-qa.py INSTANCE chromium` y `... webkit`, `med-consultorios-integration01-header-qa.py INSTANCE`, `med-consultorios-integration01-order-qa.py INSTANCE`. No ejecutar esas pruebas de escritura contra un preview persistente de otro trabajo.

## Test histórico index.html

Clasificación **OBSOLETE_UI_TARGET**, no fallo de composición de nombres. `PublicNamePresentationTest.php` no fue modificado: sus casos de composición terminan, luego falla por capturar `mxpi-prefix` en index.html. El commit `24fa4a7f` convirtió esa entrada en gate neutral de Identity; el shell real es private-app.php. Test e index.html son idénticos al baseline protegido. El selector vigente contiene todo KNOWN_PREFIXES más una opción vacía explícita «Selecciona»; se comprobó el catálogo, sin eliminar opciones ni debilitar aserciones para obtener PASS. Simplemente cambiar el path del test tampoco basta: debe distinguir placeholder de prefijos en una futura actualización independiente. [Clasificación](../evidence/med-consultorios-integration01/historical-test.json).

## Gate futuro, backup y despliegue (NO ejecutado)

No hay autorización de despliegue en esta tarea. Las instrucciones siguientes se ejecutarán únicamente tras decisión explícita del Director:

1. Repetir HEAD/limpieza/hashes de los seis archivos y la identidad del runtime. Si hay divergencia, detener y rebasar hunks en otro candidato; nunca forzar ni sobreescribir cambios nuevos. `git apply --check deployment.patch` debe pasar contra el destino. Este check read-only ya pasó en el original actual.
2. Guardar en directorio privado fuera del docroot (modo 0700) un `tar` de exactamente los seis paths de files.json, sus SHA-256 y el HEAD. No incluir .env, credenciales, DB, perfiles de navegador ni cookies. Verificar listado/hashes del backup antes de aplicar. Copia del diff y manifiesto identificados por hash junto al backup.
3. Revisar y aplicar únicamente deployment.patch por hunks, sin checkout/copy masivo, merge ni cherry-pick de todo el candidato. Revalidar hashes postimage. Coordinar una ventana sin requests nuevas durante el paso para evitar servir PHP/JS de versiones mezcladas; no cerrar sesiones ni borrar almacenamiento del navegador.
4. Cache: private-app cambia app/header-account a `med-consultorios-init03` y ordering/multisede a `med-consultorios-init02`. La pestaña abierta mantiene sus scripts hasta una recarga voluntaria después de guardar cambios pendientes. No hard-reload automático, limpieza global de caché ni deslogueo. El PHP built-in actual no exige reinicio por archivo editado; verificar OPcache de forma acotada en la fase autorizada y, si es necesario, invalidar sólo archivos afectados. Nunca reiniciar Identity/Valkey/MySQL/TLS por este parche.
5. No migración, DDL, semilla, backfill ni cambios de permisos. No copiar scripts QA o infraestructura de prueba al runtime protegido.

**Smoke original posterior autorizado:** observar sesión actual y scope Leticia; tomar hashes read-only de profiles_doctors, consultorios, membresías y media; abrir/recargar voluntariamente Perfil → Consultorios; primer GET debe devolver/pintar IDs 1/2/3 con nombres/orden/primario intactos, sin PUT/POST de hidratación; comprobar prefijo Dra., foto, metadata, plan y sesión. Navegar ida/vuelta y comprobar que no se duplican sedes ni aparecen placeholders. Revisar red/console sin capturar tokens. Repetir hashes. No introducir errores ni hacer ediciones de prueba en Leticia: esos escenarios pertenecen a Aurora. No probar logout del Director en el original como parte de este smoke.

## Rollback y condiciones de detención

[Ensayo](../evidence/med-consultorios-integration01/rollback-rehearsal.json): patch check/apply en directorio propio; seis hashes resultantes correctos; reverse check/apply; seis hashes baseline restaurados. También se ensayó backup tar de exactamente los seis archivos y su restauración por hash. No se ensayó sobre el original.

Detener/retroceder si el primer GET falla pese a API sana, falta una sede, aparece placeholder, hay autosave no solicitado, cambia order/primary, falla ámbito/Identity, desaparecen metadata/foto/plan, hay excepción JS nueva o divergen hashes originales. Un 401 por expiración legítima requiere login normal; no prolongar tokens ni desactivar gates.

Rollback autorizado: comprobar que las seis postimages siguen coincidiendo con el candidato; ejecutar `git apply --reverse --check deployment.patch`, luego revertir sólo esos hunks (o restaurar exactamente los seis archivos del backup validado), verificar seis hashes originales. Si hubo cambios concurrentes, detener y reconciliar, no sobrescribirlos. Recarga voluntaria del navegador una vez guardadas ediciones pendientes. No restaurar la DB ni purgar sesiones: el parche no migra datos. Si se detecta una escritura inesperada en datos originales, conservar evidencia y detenerse; rollback de código no autoriza deshacer datos legítimos ni restaurar backups de DB.

## Cierre operacional

**OPERATIONAL_STATUS=READY_FOR_EXPLICIT_DIRECTOR_AUTHORIZATION.** Todos los grupos focalizados pasan; test histórico obsoleto conservado. [Verificación final de compatibilidad](../evidence/med-consultorios-integration01/compatibility-summary.json). [Antes](../evidence/med-consultorios-integration01/original-before.json) y [después](../evidence/med-consultorios-integration01/original-after.json) del original son idénticos. No merge ni despliegue. Instancia de QA propia disponible temporalmente, sin sustituir la referencia manual Leticia.

## CTX-SYNC01

CONTEXT_BASELINE=f92f37f1958af5140a96c04cb52c5c1ce7af8e01;fuente=615d4655fd7812d86c5f03470335eb989b6f1412
AFFECTED_CAPABILITY_IDS=PRF-02,PRF-03,PRF-04,PRF-05
AFFECTED_JOURNEY_IDS=J04;presentación transversal del shell sin nuevo journey
AFFECTED_DATA_OBJECTS=consultorios (contrato intacto);profiles_doctors.prefix (lectura);professional.presentation_name (aditivo)
AFFECTED_CONTEXT_FILES=este documento;ESTADO_ACTUAL_MXMED.md;README.md;INVENTARIO_FUNCIONAL_MXMED.md;INTERACCIONES_MXMED.md
CONTEXT_CHANGES_IMPLEMENTED=matriz de seis archivos;diff acotado;QA independiente;clasificación histórica;gate/backup/rollback
CONTEXT_VERIFICATION=hashes fuente/HTTP;runtime/Safari observados;QA aislada;ensayo de parche inverso
CONTEXT_SYNC_STATUS=PASS_UPDATED
DOCUMENTATION_COMMIT=incluido en el commit del candidato INTEGRATION01
REMAINING_CONTEXT_GAPS=despliegue y smoke original requieren autorización posterior;test histórico conservado y clasificado
