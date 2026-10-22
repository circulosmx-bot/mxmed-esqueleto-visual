# CROSS-SURFACE-LINK03 — lector administrativo canónico y adapter local

Baseline `7b3675d15931e559227d67decb20f1c82a7f1b46`, 2026-10-21. Rama `feature/mxmed-cross-surface-link03`. Implementación aislada con acceso administrativo original restringido; **no es un entorno de datos exclusivamente sintéticos**. Cuentas staff, sesiones, grants y auditoría son sintéticos. Aceptación visual del Director PENDING; producción no activada.

## PRODUCT_POLICY / arquitectura aprobada

LINK03 y su enmienda autorizan únicamente nuevos principales SQL locales y vistas administrativas de lectura; no DML médico, cambios de cuentas originales ni 8091. El perfil de referencia es un caso de prueba, nunca una bifurcación del código. UI-2: búsqueda/contexto y estado de disponibilidad dentro del workspace aprobado, sin rediseño de shell ni nuevas facultades de operador.

El camino de producto es `Identity → StaffGovernance → ámbito exacto → Reader → Source → proyección canónica`, con auditoría canónica. [Reader](../../modules/identity/PhysicianAdministration/Reader.php) mantiene autorización en la fuente staff. [Source](../../modules/identity/PhysicianAdministration/Source.php) define búsqueda paginada, resumen y relaciones. [SqlSource](../../modules/identity/PhysicianAdministration/SqlSource.php) no conoce hosts, usuarios ni médicos concretos. Su modo explícito `joinCanonicalTransaction=true` admite el PDO canónico ya abierto, conserva la transacción del llamador y fue probado. La producción debe inyectar su propia fuente/ámbito; no depende del MySQL del desarrollador.

## SOURCE_IMPLEMENTATION / contrato

- `physician_administrative_read`: nueva capacidad operacional delegable en catálogo y matriz existentes. Se concedió/revocó a `qa_target` mediante Director/Identity y writer canónico. Irene permanece intacta.
- Cada petición valida sesión/versión de credencial, cuenta activa, staff activo, grant vigente bajo locks y lista explícita de recursos; revalida el ámbito después de leer. Sin caché de autorización. Ámbito local por origen/cuenta/capacidad, máximo 100 IDs por cohorte; no es acceso universal.
- `physician_operator_support_read` sigue independiente y exige además el ámbito de relaciones del recurso. Esta nueva proyección presenta estados de relación/cuenta y contacto verificado, sin IDs de cuenta, correos o secretos. Conserva la semántica diagnóstica de AdvisorSupportReader: no asume titular activo, delegación, aceptación ni derechos de mutación. El lector sintético previo permanece intacto.
- Referencia `origin:canonical_id` en búsquedas/contexto/API; IDs sintéticos existentes siguen su namespace sin dos puntos. No hay joins entre fuentes por ID numérico. La fuente y columnas de procedencia se entregan en el DTO.
- Búsqueda `admin_search`: prefijo literal de nombre o ID exacto, cursor por doctor_id y límite 1–20, consulta 20+1 como máximo. Resumen `admin_summary`: dos queries constantes, hasta 20 consultorios más indicador de continuación. Relaciones `admin_operators`: hasta 100, falla cerrado si se supera. Parámetros preparados, identificador de proyección validado; no N+1.
- Fuente fallida → 503 saneado, sin fallback. Scope/grant no vigente → 403 antes de consultar el origen. Auditoría `SENSITIVE_ADMIN_ACTION` en la base staff, con recurso calificado y ruta de lectura; denegaciones separadas. No se afirma atomicidad entre bases.
- UI conserva quince pestañas, selección y bandeja independiente. No inventa pendientes. Áreas no compatibles muestran indisponibilidad explícita. Cambiar contexto limpia el DOM y descarta respuestas tardías por epoch.
- `profile_status` es estado administrativo, **no certifica publicación pública**. Referencia pública queda nula y publicación no verificada. Invitaciones/delegaciones no están en estas vistas y se presentan no integradas; no se infieren vacías. Correspondencia colaborador–Director continúa no acreditada.

## CONNECTIVITY / provisión local v1

[Provisionador versionado](../../scripts/cross-surface-link03-provision.py), separado de migraciones de aplicación. Revalida entorno local explícito, loopback, base `mxmed`, existencia de IDs y UUID de instancia registrado. Recibo privado permite repetir/recuperar una ejecución parcial. No cambia migraciones históricas ni crea tablas/copias de datos. Vistas TEMPTABLE no actualizables, SECURITY DEFINER, con allowlist de IDs fijada en configuración local y sólo columnas permitidas:

| Principal local, host 127.0.0.1 | Únicos SELECT concedidos |
|---|---|
| `mxmed_summary_26dc88e5` | `mxmed_staff_read_v1_26dc88e5_profiles`, `mxmed_staff_read_v1_26dc88e5_locations` |
| `mxmed_relations_26dc88e5` | `mxmed_staff_read_v1_26dc88e5_relationships` |

Sin acceso directo a tablas originales ni a la otra familia de vistas, sin permisos de escritura/DDL/globales. USAGE no concede lectura. Root sólo se utilizó para la configuración autorizada y metadata de planes, nunca se entrega al servidor preview. Credenciales generadas en `~/Library/Application Support/MXMED/physician-administration-link03/reader-v1.json` (0600, directorio 0700); no en Git ni navegador. El adapter local verifica UUID antes de leer. Principal y proyecciones permanecen tras detener el preview hasta revocación explícita.

[Adapter temporal](../../scripts/cross-surface-link03-runtime.php): sólo composición owned con `MXMED_LINK03_REVIEW=1`; construcción lazy para que un fallo no rompa Requests/Media. Conexión de 2 s y MAX_EXECUTION_TIME 1500 ms; transacciones independientes READ ONLY con rollback. La composición normal no selecciona el adapter. El servicio fuente puede incorporarse al PDO canónico mediante inyección explícita; activar producción requiere su propio deployment y autorización.

## VERIFICATION / rendimiento

90 verificaciones dirigidas: 26 de privilegios SQL, 38 de navegador/HTTP principal, 20 suplementarias y 6 de contrato/paginación. No se reejecutó la suite histórica. Evidencia privada en `/tmp/mxmed-ase-adm02imp01-x5tb2_lj/link03-evidence`; sólo resultados sin datos personales se resumen aquí.

- Chromium: Director concede/revoca con writer canónico; Advisor busca el registro original, consulta contexto/relaciones, logout/login, 15 tabs, anchos 1366/820/390 sin overflow.
- Revocación en sesión abierta, scope retirado, origen incorrecto, ID sin origen, ID no autorizado, campos HTTP ambiguos, límite excesivo; consultas SQL no permitidas denegadas.
- Fuente ausente y puerto de prueba cerrado: 503; scope no autorizado con fuente caída: 403 antes de conexión. No se detuvo el origen para probar fallos.
- No copia en profiles_doctors aislada; contadores Requests/Media no cambian por búsqueda; cambio a contexto sintético y respuesta original tardía no mezclan datos.
- 80 perfiles totalmente sintéticos, cuatro páginas sin duplicados/omisiones; límite y escape literal de comodines; veinte consultas repetidas. Fuente SQL: cuatro conexiones concurrentes / veinte lecturas estables. HTTP: cuatro clientes / doce peticiones; el servidor PHP de desarrollo serializa atención, **no demuestra paralelismo de aplicación ni escala de producción**.
- EXPLAIN de consultas y vistas: perfiles `uniq_profiles_doctors_doctor_id` (const, 1); consultorios PRIMARY (ref, 3); membresías `idx_auth_account_memberships_profile_status` (ref, 1) y cuentas PRIMARY (eq_ref, 1). Vistas TEMPTABLE materializan el ámbito permitido y ordenan pequeños resultados: locations derived ALL/filesort 3 filas, relaciones derived ALL/filesort estimado 2. No se añadieron índices a originales. Cohortes mayores requieren medición específica, no extrapolación.
- Original: worktree limpio `mxmed-step2-vitals-r1`, HEAD `f92f37f1958af5140a96c04cb52c5c1ce7af8e01`, mismo PID90106/8091. Ningún DML médico/clínico, importación, sesión original, cuenta existente ni grant de Irene alterados. Las únicas modificaciones originales son los dos principales/vistas autorizados.

## Revisión y revocación

Preview: `https://127.0.0.1:60152`, Director `/internal/media-review/index.php?section=equipo`, Advisor `/internal/staff-workspace/index.php`. Expira **2026-10-22 20:59:22 -06:00 (CDMX)**. No se extendieron sesiones Identity ni MFA. Launcher: `scripts/cross-surface-link03-review.py /tmp/mxmed-ase-adm02imp01-x5tb2_lj open`; acepta también `credentials-director`, `credentials-advisor`, `mfa-director` y `stop`. Contraseñas/código se copian al portapapeles por el mecanismo existente.

Revocar exclusivamente estos lectores locales (no toca cuentas administrativas existentes):

```sh
python3 scripts/cross-surface-link03-provision.py revoke --environment "$HOME/Library/Application Support/MXMED/local-identity-runtime/environment.json" --private-dir "$HOME/Library/Application Support/MXMED/physician-administration-link03"
```

El comando verifica el UUID/recibo y bloquea ambos principales propios. Nuevas conexiones fallan; una lectura ya iniciada puede concluir bajo su límite. No mata procesos ni elimina vistas/recibos. Recuperación de provisión interrumpida: repetir `provision` con el mismo recibo; tras revocación no se desbloquea automáticamente. Retirar vistas/cuentas definitivamente exige revisar el recibo y dependencias antes de DROP; no se ejecutó ese teardown destructivo.

## OPERATIONAL_STATUS / pendientes de producción

Integración local disponible para revisión; no despliegue ni merge. Médico/Operador/Público/Proveedor no se presentan como cuatro contextos browser integrados. Pendientes: aceptación visual, composición de producción explícita con ámbito canónico operativo, política/retención/volumen de auditoría de consultas, medición multiusuario real, provisión y recuperación por entorno, publicación pública y proyecciones adicionales separadas si se autorizan. No se afirma readiness ni carga de miles de usuarios.

Sucesor de UX y lectura de sedes: [CROSS-SURFACE-UX04](CROSS-SURFACE-UX04.md). Separa la lista detallada del resumen general mediante permiso/ámbito propio; conserva esta provisión v1 sin ampliarla.
