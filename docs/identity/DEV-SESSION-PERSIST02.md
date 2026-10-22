# DEV-SESSION-PERSIST02 — QA staff e integración local preparada

2026-10-22, America/Mexico_City. Baseline `0145432fec761283110fde5853ec3d5e483783cc`, rama `feature/mxmed-dev-session-persist02`. **RESULT=COMPLETE_ISOLATED_QA_INTEGRATION_PREPARED**. Preparación completa; no despliegue ni continuidad original activada. Sucede los pendientes técnicos staff de [PERSIST01](DEV-SESSION-PERSIST01.md).

## Verificación staff

**VERIFICATION:** 49 checks PASS con SQL/Valkey/PHP/TLS nuevos propios, sin conectar a bases originales. Se reutilizaron setup y servicios canónicos; no suites históricas ni QA médica repetida. Cuentas `qa_director@example.test` y `qa_target@example.test` existen únicamente en esa nueva fixture. Ningún grant del preview persistente Director/Advisor fue cambiado.

- Chromium y WebKit: login por formulario Identity en los paneles reales Director y Asesor; recarga/navegación; cierre completo y reapertura con perfiles persistentes; reinicio de PHP y de Valkey con AOF. Los dos identificadores permanecieron distintos y cada uno conservó su valor mientras fue válido.
- Separación: **almacenes de navegador independientes**, cuentas y registros de sesión distintos en el servicio canónico Valkey compartido. No son dos servidores Identity ni un único cookie compartido. Expirar el registro Asesor no invalida Director. Asesor recibe 403 al consultar el directorio Director.
- Revocación en vivo: Director revoca `media_review_read` por HTTP/CSRF/writer canónico. El Asesor mantiene su misma sesión; el resumen básico conserva HTTP200 con actor, pero count=0 y listas protegidas vacías; documento directo 403. Grant restituido por el mismo writer y consumer recuperado. La fixture termina con su permiso inicial. No se exige 403 para información básica independiente.
- Expiración de ambos roles: se fija TTL=1s únicamente a su registro Valkey nuevo y se espera su eliminación real; protegidos responden401. No se modifica SessionPolicy ni el reloj global. Logout HTTP canónico de ambos roles deniega accesos posteriores. El contrato temporal prueba límites exactos 3600/43200 con reloj sintético; la prueba de navegador no espera doce horas reales.
- MFA: enrolamiento por ceremonia canónica con registrador distinto; contraseña correcta/incorrecta, ausencia de reautenticación, código inválido, código TOTP vigente y prueba vinculada a la sesión real del navegador; consumo exacto y rechazo de replay. Asesor sin autoridad no obtiene desafío privilegiado. QA del servicio MFA canónico con SQL real aislado y binding del navegador, no una nueva UI de gobierno ni ejecución de nombramiento Master. Caso sintético de prueba; ningún privilegio nuevo.
- Servicios y directorio propios se eliminaron al terminar. Durante navegación/cierre hubo avisos de conexión interrumpida del proxy de prueba; las aserciones de aplicación y reinicio pasaron. Dos intentos previos corrigieron el harness: referrerPolicy igual al consumidor existente y comprobación por campos en lugar de exigir403 al resumen básico. No se debilitó producto.

Evidencia privada fuera del docroot: `~/Library/Application Support/MXMED/dev-session-persist02/qa.json` (49 checks, rollback de recursos PASS), `integration-verification.json`, `integration-session-contract.txt`, `preservation.json`. Ninguna contraseña, cookie, token o código MFA se publica en Git. Sólo los identificadores sintéticos se usan en memoria durante QA. Reproducción: `python3 tools/dev-session-persist02/run.py`; siempre crea recursos nuevos y no altera previews existentes.

## Política: comparación y decisión pendiente

| Opción | Duración exacta | Autenticación/MFA y reautenticación | Revocación/logout y reinicio | Riesgo y aislamiento | Reversión |
|---|---|---|---|---|---|
| **A — recomendada, implementada en candidato** | Idle3600s, absoluto43200s, touch300s, máximo5 sesiones; cookie hasta absoluto restante | Login canónico; reingreso después de 1h inactivo o12h total. MFA sensible conserva ventana5min y alcance. Operaciones ordinarias siguen STAFF-AUTH-UX08. | Revocación servidor efectiva en siguiente operación; logout invalida registro. AOF privado + cookie persistente soportan reinicios mientras siga válido. | Cookie en disco prolonga acceso potencial ante dispositivo desbloqueado; cierre del navegador no es logout. Guard normal-local explícito, producción sin opt-in. | Desactivar emisión persistente; conservar store vivo. Cookies existentes mantienen límites servidor; no borrar sesiones para deshacer una preferencia. |
| B — alternativa, **no implementada** | Idle28800s (8h), absoluto259200s (72h); touch300s, máximo5 | Reduce reingresos durante tres jornadas activas; pausa superior8h vuelve a exigir login. MFA5min intacto, sin prueba reutilizable global. Requiere aprobación explícita de política y nuevas pruebas. | Mismos writers/logout/revalidación; AOF y cookie con plazo restante. | Amplía a72h la ventana potencial de una sesión robada; equipo perdido/desbloqueado requiere revocación. Debe existir perfil local separado que no modifique constantes globales. | Volver A para nuevas sesiones; decidir explícitamente si se acortan/revocan las ya emitidas, pues cambiarlas no es rollback inocuo. |
| C — funcionalidad existente | Rotación conserva el absoluto original43200s y renueva idle hasta3600s sin superarlo | Existe `session-rotate`; **no se encontró trusted-device, remember-me ni refresh-token** en Identity. No promete continuidad después de12h ni exime MFA. | Identificador anterior invalidado al rotar; logout/revocación intactos. Rotación por sí sola no hace persistente el navegador. | No instalar renovación automática ni heartbeat para eludir idle. Producción conserva contrato actual. | No añadir automatismo; mantener rotación canónica existente. |

**PRODUCT_POLICY:** se recomienda A antes de considerar B. Un token bearer válido sustraído no puede distinguirse automáticamente del titular; se preservan Secure/HttpOnly/SameSite, límites y revocación. No se inventa dispositivo confiable. AOF no prolonga tokens ni revive expirados. No hay cambios de duraciones en ningún runtime.

## Paquete mínimo de integración original

Original inspeccionado: `mxmed-step2-vitals-r1`, HEAD `f92f37f1958af5140a96c04cb52c5c1ce7af8e01`, seis modificaciones Consultorios autorizadas intactas por hash. Configuración Identity privada coincide con DEPLOY01. PHP8091/Identity/Valkey y sesiones originales no intervenidos.

**SOURCE_IMPLEMENTATION:** [parche exacto](../../tools/dev-session-persist02/integration.patch), SHA256 `a31d954192fa6f4eb6ad1f7c537a2a766b76e5549199cf7003d686c1f9255eaf`; [cinco hashes pre/post](../../tools/dev-session-persist02/integration-files.json). `prepare.py` sólo genera y hace apply/check read-only. **No merge/cherry-pick completo de esta rama.**

1. `modules/identity/contracts/DevelopmentSessionCookiePolicy.php`: nuevo descriptor de persistencia acotada; factory normal-local acepta únicamente configuración validada.
2. `modules/identity/services/SessionService.php`: sólo constructor y cookies create/rotate. Se excluyen expresamente cambios históricos `observeOnly` de la rama staff; no sobrescribir el archivo entero.
3. `modules/identity/http/IdentityHttpComposition.php`: pasar política sólo desde factory normal-local; preview conserva su opt-in aislado; producción no recibe esta política.
4. `modules/identity/http/NormalLocalIdentityConfiguration.php`: dos flags opcionales exactos `'0'`/`'1'`, default0; mismas restricciones APP_ENV local, origin localhost8443, DB/store locales y sin mezcla preview.
5. `scripts/normal-local-identity.py`: al iniciar un store nuevo, AOF según flag y appendfsync always; no modifica un store ya activo ni lo reinicia automáticamente.

Flags propuestos en **la configuración privada**, todavía NO escritos:

```json
{"MXMED_NORMAL_LOCAL_PERSISTENT_COOKIE":"1","MXMED_NORMAL_LOCAL_VALKEY_AOF":"1"}
```

Copia candidata privada basada en original+seis hunks Consultorios: `~/Library/Application Support/MXMED/dev-session-persist02/integration-candidate`. Parche apply/check, cinco postimages, contrato de sesión, diez gates de configuración, reversión y reaplicación verificados. Original real sólo recibió lecturas. No se importó el comportamiento `observeOnly` ajeno al alcance.

## Activación futura y reversión controlada — NO ejecutar ahora

1. Obtener autorización explícita del Director para A y el paquete exacto; revalidar HEAD, hashes, configuración, comandos/PIDs y propietarios vigentes. Backup privado de los cuatro archivos originales y constancia de inexistencia del nuevo; preservar las seis modificaciones Consultorios. Respaldar configuración privada sin imprimir secretos ni exportarla a Git.
2. Antes de detener Valkey con persistencia actualmente desactivada, habilitar AOF **en el proceso vivo verificado** mediante cliente local restringido: `CONFIG SET appendonly yes`, `CONFIG SET appendfsync always`. Esperar `aof_rewrite_in_progress=0`, `aof_last_bgrewrite_status=ok`, `aof_last_write_status=ok` y verificar archivos/directorio privados bajo el runtime0700. Si no está sano, detener el despliegue y conservar el proceso vivo. No reiniciar primero, no FLUSH, no copiar cookies y no restaurar un snapshot viejo que resucite revocaciones.
3. Instalar sólo los cinco hunks con coherencia de release y escribir flags privados aprobados. El launcher no convierte por sí solo un Valkey ya activo: la comprobación AOF anterior es obligatoria. Mantener port/prefix/pepper/DB y duración existentes.
4. Cargar configuración en los procesos PHP mediante intervención aprobada, conservando **sus argv y docroots actuales**. No usar ciegamente `--replace-dev-servers`: ese camino puede cambiar router/otros procesos. No reiniciar TLS, MySQL o todo el runtime para cambiar estas cookies. No invalidar sesiones existentes: el cambio se aplica al próximo login/rotación legítima; no convertir una cookie abierta copiándola.
5. Smoke lectura: sesión médica vigente/ámbito, tres consultorios sin escrituras, cookie nueva acotada en cuenta sintética aprobada, grants/CSRF/MFA/logout y prueba de continuidad autorizada. No logout forzado ni cierre de Safari médico. Si fallan gates, restituir cinco archivos/configuración por hashes; conservar el store vivo y la AOF más reciente para no perder sesiones/revocaciones. No restaurar DB.
6. Desactivar AOF inmediatamente también puede sacrificar continuidad al siguiente reinicio: si se revierte esa preferencia, hacerlo explícitamente con servicio vivo y explicar ese límite. Nunca restaurar estado de sesiones antiguo. Backup/restauración de código no autoriza borrar claves, restablecer cuentas o eliminar los seis cambios Consultorios.

**PRODUCTION_ISOLATION:** guards rechazan staging/production, origen público, flags ambiguos y mezcla de composiciones; factory productiva no recibe política normal-local. Configuración/launcher son locales. No extensión global de SessionPolicy ni configuración productiva.

## Safari: comprobación manual sin cerrar la sesión médica

**MANUAL_STATUS=PENDING.** WebKit automatizado no es Safari físico. [Apple documenta perfiles con cookies/datos separados](https://support.apple.com/en-ie/105100).

1. Conserva abierta la ventana/perfil donde utilizas Leticia. Identifica ese perfil existente como **MXMED Médico**; puedes cambiar su nombre desde Safari → Configuración → Perfiles, sin crear otro almacén para sustituirlo ni borrar datos. Si Safari crea un perfil Personal al configurar perfiles por primera vez, ése conserva el contexto existente: úsalo como Médico.
2. En Configuración → Perfiles, crea **MXMED Director** y **MXMED Asesor**, con colores diferentes. No uses ventanas privadas ni sólo dos pestañas del mismo perfil.
3. Abre una ventana nueva de cada perfil desde el selector de perfiles. En cada una abre `https://127.0.0.1:57452/`. Médico → panel médico existente; Director → entrada Director y cuenta QA Director; Asesor → entrada Asesor y cuenta QA Asesor indicada por el hub. Login siempre normal; credenciales desde el launcher actual, no desde fixtures descartadas de esta QA.
4. En cada panel verifica el rol/cuenta mostrados; recarga, navega y vuelve al hub. Comprueba que Director no se convierte en Asesor y que Médico mantiene su contexto. El hub no cambia de perfil ni autentica por sí solo.
5. Para probar cierre/reapertura de **ventanas staff**, cierra sólo esas ventanas y abre ventanas nuevas de los mismos perfiles. No cierres la ventana médica ni salgas de Safari para esta comprobación. Esto no equivale a cerrar el proceso completo: esa prueba física queda pendiente de una ventana de revisión acordada.
6. Registra fecha, perfil, cuenta visible, resultado de recarga/reapertura y ausencia de cruce. Logout de Asesor debe afectar sólo ese perfil. Los runtimes existentes aún no tienen este candidato: no prometer persistencia tras terminar Safari antes de una activación autorizada.

**CONNECTIVITY:** hub57452 HTTP200 y seis enlaces normales verificados; sin cambio de servicio ni contexto. Preview staff y Médico existentes no recreados. **OPERATIONAL_STATUS:** QA aislada completa y paquete listo para decisión; activación original no autorizada y aceptación física Safari pendiente.

## CTX-SYNC01

CONTEXT_BASELINE=0145432fec761283110fde5853ec3d5e483783cc
AFFECTED_CAPABILITY_IDS=Identity/session lifecycle;media_review_read verificado sin nueva autoridad
AFFECTED_JOURNEY_IDS=login,navegación,logout,revocación y MFA staff existentes
AFFECTED_DATA_OBJECTS=SessionCookieDescriptor;SessionRecord sin cambio;configuración local opcional;sin migración
AFFECTED_CONTEXT_FILES=DEV-SESSION-PERSIST02.md;DEV-SESSION-PERSIST01.md;ESTADO_ACTUAL_MXMED.md;README.md
CONTEXT_CHANGES_IMPLEMENTED=QA staff cerrada;comparación A/B/C;paquete original mínimo;guía Safari;rollback
CONTEXT_VERIFICATION=49 checks staff;contrato de sesión en candidato original;10 guards;hashes/apply/reverse;hub
CONTEXT_SYNC_STATUS=PASS_UPDATED
DOCUMENTATION_COMMIT=incluido en commit de esta feature
REMAINING_CONTEXT_GAPS=autorización de despliegue original y verificación física Safari pendientes
