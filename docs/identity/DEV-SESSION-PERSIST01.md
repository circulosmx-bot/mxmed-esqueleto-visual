# DEV-SESSION-PERSIST01 — continuidad local y límites verificados

> Sucesor verificado: [PERSIST02](DEV-SESSION-PERSIST02.md) completa QA staff y prepara integración original, sin desplegar. Los pendientes siguientes describen el cierre histórico PERSIST01.

2026-10-22. SOURCE baseline `bde7476083ebd69385dbe6a8396d294845ada591`, rama `feature/mxmed-dev-session-persist01`. **OPERATIONAL_STATUS=PARTIAL_ISOLATED_VERIFIED**. Hub actualizado; cambio de cookies sólo probado en servicios propios. No desplegado a Identity original ni al preview staff existente. No constituye persistencia resuelta para todos los roles.

## Diagnóstico por contexto

| Contexto | Evidencia y causa | Estado |
|---|---|---|
| Director y Asesor | Servicio 54400 disponible. `SessionService::create/rotate` emite cookie sin Max-Age; los launchers crean contextos temporales. Comparten host/nombre de cookie: puertos diferentes NO separan cookies; requieren perfiles independientes. El runner es desechable y caduca; recrearlo sustituye almacén/sesiones. | No se atribuye una pérdida histórica concreta sin telemetría. La política canónica sí explica caducidad a 1h idle/12h absolutas. |
| Médico | HTTPS localhost:8443 disponible, misma política/cookie canónica. Host distinto del staff, no copiar cookies a 127.0.0.1. Valkey normal-local se lanza con `--save '' --appendonly no`; reiniciarlo pierde las sesiones en memoria. | Configuración y seis cambios Consultorios preservados por hash. No había pestaña médica abierta en la observación final; vigencia de sesión Safari no acreditada. |
| Operador | Entrada existente, sin contexto operador preparado/verificado en el hub. | No se crea membresía ni acceso. |
| Proveedor | Entrada existente, sin relación organizacional/sesión verificada. | No se asigna autoridad de médico ni staff. |
| Público | Publicación de referencia deshabilitada según contrato del hub. | No se habilita publicación; autenticación específica sólo donde el contrato existente la requiera. |

Identity usa su servicio/Valkey, no PHPSESSID ni GC de archivos PHP como autoridad canónica. La pérdida por GC PHP no está demostrada. Cookie Secure, HttpOnly, SameSite=Lax, Path=/ y sin Domain. Máximo cinco sesiones; una sexta puede sustituir la más antigua. Validación reconsulta estado de cuenta/credenciales; rotación y logout invalidan el identificador correspondiente. GET current-session observa sin mantener viva la inactividad. No se añaden heartbeats.

MFA se conserva: los retos/pruebas staff sensibles existentes tienen 5 minutos y binding/replay prevention. No confundir esa vigencia con una sesión Identity. STAFF-AUTH-UX08 ya autoriza operaciones ordinarias de admisión por sesión actual/versiones; no se reintroduce MFA por cada lectura. Gobierno y operaciones sensibles conservan sus controles propios. No se ha demostrado que las incidencias históricas fueran causadas por un fallo de MFA.

## Corrección acotada

**PRODUCT_POLICY:** misma política canónica: idle 3600s, absoluto 43200s, touch 300s, máximo 5. Ninguna extensión de TTL ni renovación indefinida.

**SOURCE_IMPLEMENTATION:** `DevelopmentSessionCookiePolicy` opt-in `MXMED_DEV_PERSISTENT_COOKIE=1`, sólo preview explícito local, DB sintética prefix `mxmed_gate4d_preview_`, MySQL loopback no3306 y origen HTTPS 127.0.0.1 con puerto. Rechaza producción/staging y normal-local original, incluso con flag presente. `SessionService` sólo cambia descriptor de cookie: Max-Age hasta el vencimiento absoluto original; rotación reduce el restante, no reinicia 12h. Sin flag, comportamiento idéntico. El servidor sigue denegando tras 1h inactivo aunque el navegador conserve la cookie.

La QA usa perfiles persistentes de navegador y Valkey AOF propio en directorio privado. Reiniciar PHP o Valkey conserva sesiones válidas; no se ofrece resurrección de una instancia caducada ni se migra el store original. Retención del perfil aumenta la duración del material de autenticación en disco; requiere acceso local protegido y logout al terminar. Nunca se almacenan códigos MFA reutilizables.

**CONNECTIVITY:** hub estable 57452 sirve las seis entradas mediante enlaces normales y distingue disponibilidad, contexto preparado, autenticación por verificar y bloqueo. No afirma autenticación sin consultar Identity en la aplicación destino. No centraliza cookies ni realiza checks cross-origin con secretos. Su reinicio sólo afecta al hub. El marcador se abre en cada perfil Safari propio; el enlace NO selecciona otro perfil por sí mismo.

## QA realizada y límites exactos

- Contrato PHP: cookie por defecto intacta; flag rechazado fuera del aislamiento; 12h máximo; rotación no extiende; token sustituido/inválido y logout denegados; idle exacto; uso activo no supera 12h. Membresía retirada se deniega con la misma sesión todavía válida. Incluye contratos Gate4C existentes en memoria, sin SQL original.
- Chromium y WebKit, login HTTP canónico con cuenta médica sintética existente: reload, cierre real/reapertura del proceso con perfil persistente, reinicio de PHP, reinicio Valkey AOF, contexto separado no autenticado y logout del shell: PASS. No se exportan storage_state ni tokens.
- Chromium: navegación al hub y reinicio del hub conservan sesión de prueba: PASS. Safari físico: hub abierto, seis entradas y aviso de autenticación visible: PASS. **Perfiles Safari simultáneos y cierre/reapertura autenticada de Safari: NOT_TESTED.** WebKit automatizado no los acredita.
- QA de navegador usa una fixture médica autorizada, no nuevos grants ni registros originales. No se repitió ejercicio de grants históricos. **Revocación staff E2E, MFA E2E nuevo y persistencia de Director/Advisor mediante sus pantallas: pendientes.** No atribuirles PASS por compartir SessionService.
- Un token bearer válido robado no se distingue mágicamente de su titular. Se verifica rechazo de inválidos/revocados/sustituidos y se conserva protección de cookie; no se acredita rechazo universal de un token válido robado ni se introduce otra arquitectura.
- Procesos de QA propios detenidos al terminar; AOF/perfiles/resultados privados fuera del docroot: `~/Library/Application Support/MXMED/dev-session-persist01/qa-5_rao_p9/`. No secretos ni evidence privada en Git. No trazas de requests con passwords/tokens.

Reproducción acotada: `php modules/identity/tests/DevelopmentSessionPersistenceTest.php`; `python3 tools/dev-session-persist01/browser-qa.py /tmp/mxmed-init02-7f57_ka1` sólo mientras la fixture está vigente. La segunda reinicia únicamente el hub propietario y servicios nuevos; no resetea fixture ni cambia grants.

## Activación pendiente y reversión

La sección 9 de la solicitud protege explícitamente Identity original y su persistencia. Habilitar cookies duraderas allí y AOF en su store requiere autorización separada e integración específica; este candidato lo rechaza deliberadamente. No basta copiar el flag. No se propone extender 1h/12h: si la revisión requiere pausas mayores, hará falta una decisión explícita de política distinta.

Para completar todos los roles faltan QA E2E staff en una instancia propia preparada y validación manual de perfiles Safari. Operador/Proveedor continúan bloqueados hasta contar con contextos legítimos. No afirmar COMPLETE.

Hub activo desde esta rama. Reabrir: `python3 /Users/circulodigital/Documents/GitHub/mxmed-dev-session-persist01/scripts/interface-hub02.py open`. Reversión hub: detener ese script con `stop`, esperar su cierre, ejecutar `start` del worktree `mxmed-interface-hub02`. No intervenir Identity. El flag del candidato es opt-in y no se activó en runtimes preexistentes; no requiere rollback de sesiones.

## CTX-SYNC01

CONTEXT_BASELINE=bde7476083ebd69385dbe6a8396d294845ada591
AFFECTED_CAPABILITY_IDS=Identity/session lifecycle;sin nuevas capacidades ni grants
AFFECTED_JOURNEY_IDS=login,logout,navegación entre interfaces existentes
AFFECTED_DATA_OBJECTS=SessionCookieDescriptor;SessionRecord sin cambios de contrato/TTL;ninguna migración
AFFECTED_CONTEXT_FILES=DEV-SESSION-PERSIST01.md;ESTADO_ACTUAL_MXMED.md;README.md
CONTEXT_CHANGES_IMPLEMENTED=diagnóstico por rol;cookie opt-in aislada;hub honesto;límites de QA y activación
CONTEXT_VERIFICATION=contratos PHP;Chromium/WebKit;Safari hub;hashes originales;enlaces
CONTEXT_SYNC_STATUS=PASS_UPDATED
DOCUMENTATION_COMMIT=incluido en commit de esta feature;sin push
REMAINING_CONTEXT_GAPS=activación original no autorizada;QA staff E2E y perfiles Safari;contextos Operador/Proveedor no preparados
