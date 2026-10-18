# ASE-ADM02-IMP03 — retrato y evidencia staff privados

2026-10-18. Padre local/upstream/remoto verificado `27e0da02a4daf15d3c1a32ab5590e2527e835b52`, limpio. Feature `feature/ase-adm02-private-staff-media`, worktree `mxmed-ase-adm02-private-staff-media`. Oficial `f92f37f1958af5140a96c04cb52c5c1ce7af8e01`, visual aprobado `312ee388f93c892e6caf7e845b11e2b42f9c3565` y trece worktrees anteriores protegidos. **UI-0, backend callable y archivos exclusivamente sintéticos. Ningún intake personal real ni endpoint de carga/descarga.**

## Alcance, procedencia y estados

La solicitud IMP03 autoriza almacenamiento privado, cifrado autenticado, versiones/metadatos, acceso exacto, auditoría, reconciliación SQL/archivos y QA sintética. [POL01](ASE-ADM02-POL01-staff-admission-policy.md) conserva sus siete principios; [IMP01](ASE-ADM02-IMP01-staff-case-foundation.md) es el expediente/perfil y [IMP02](ASE-ADM02-IMP02-staff-invitation-binding.md) establece el vínculo legítimo. No hay otro maestro de personas, writer de staff o decisión de revisión.

| Eje | Estado |
|---|---|
| PRODUCT_POLICY | Retrato interno separado de documento restringido; persona actúa sobre caso propio. Un documento mexicano apropiado INE/pasaporte, validez/autenticidad por revisión posterior; catálogo extranjero pendiente. Ninguna foto acredita identidad. |
| SOURCE_IMPLEMENTATION | StaffMedia: Policy/Images/Storage/Cipher/Authority/Input/Service/Audit/Composition; dos tablas nuevas, cuatro triggers y dos eventos canónicos. Reuso UnitOfWork/Identity, puerto privado y procesador GD existentes. |
| CONNECTIVITY | Métodos internos write/read/result, sin rutas HTTP ni conexión visual. Composición producto siempre indisponible; las pruebas construyen explícitamente política/llaves sintéticas en CLI con guardas de propiedad. |
| VERIFICATION | MySQL/Valkey/TLS nuevos, servicios Identity y binding reales con datos sintéticos; imágenes artificiales, cifrado, SQL/audit y carreras de conexiones independientes. [QA](../evidence/ase-adm02-imp03/qa.json). No browser/UI QA. |
| OPERATIONAL_STATUS | IMPLEMENTED_BUT_INACTIVE; sin promoción/merge/deploy, datos reales, proveedores externos, reviewer, grants/MFA ni activación staff. |

Se inspeccionaron PrivateMediaStoragePort, LocalPersistentPrivateMediaStorage, EvidenceCipher, PhysicianEvidenceService, GdPublicLogoProcessor, DoctorProfilePhotoService, CanonicalAuditWriter/mapper/catálogos y los boundaries StaffAdmission/Identity. El storage existente permite ámbitos physician-evidence/signatures/media-review; el cipher usa AAD C4-EV01 y el writer de foto médica crea PUBLIC y retira anteriores. No son adapters de staff: se conservan byte por byte, igual que [Master](ASE-INT03B-IMP01-master-security-foundation.md), [su UI](ASE-INT03B-IMP02-master-ui-integration.md), [IW01](../product-completion/iw01-internal-governance-foundation.md) y [COMPAT01](ACT03-CHECK-COMPAT01-historical-case-check.md). La aprobación visual ADM01/R2/COM no se reabre; D01–D08 sigue separado.

## Dos propósitos y autoridad

| Propósito | Contenido y lector implementado | Frontera futura |
|---|---|---|
| INTERNAL_PRESENTATION_PORTRAIT | Derivado WebP de píxeles decodificados, sin metadata fuente; sólo solicitante autenticado de su caso exacto. Privado/cifrado, sin public_url ni media_assets. | Directorio/perfil internos requieren reader autorizado por ámbito. Esta fase no conecta esos readers ni reemplaza placeholders. |
| STAFF_IDENTITY_EVIDENCE | Imagen JPEG/PNG original del subconjunto validado, cifrada con propósito diferente; sólo solicitante autenticado de su caso. | Director/reviewer necesitan autoridad documental específica, finalidad y políticas ratificadas. Nunca usar el scan como avatar ni extraer cara. |

[Authority](../../modules/identity/StaffMedia/Authority.php) expone comprobaciones distintas portrait/evidence; ambas sólo admiten own-applicant por ahora. Se consulta el mismo caso bloqueado, con bound_account_id igual al actor, binding_reference presente, credential version actual y estado PREPARING o PERSON_CONFIRMED. No basta token de invitación, email, nombre o actor suministrado por cliente. Director no tiene bypass de evidencia por leer directorio. Master/Advisor pares y grants internal_advisors_manage, physician_accreditation_review o media_review_read no confieren acceso documental. No capability ejecutable nueva ni adapter de reviewer positivo.

[Service](../../modules/identity/StaffMedia/Service.php) reutiliza UnitOfWork: sesión canónica, locks cuenta → staff → credencial → caso, cuenta activa/verificada, credencial actual y revalidación final de sesión antes del commit. Lectura exige caso/asset/purpose/asset_version exactos y versión actual del caso conocida. Permite historia propia mientras el caso siga elegible; no ofrece listados generales. Session/SQL no son una transacción distribuida; conserva la ventana y límites documentados en IMP02.

## Almacenamiento y cifrado

[Storage](../../modules/identity/StaffMedia/Storage.php) implementa **PrivateMediaStoragePort** en un adapter específico. Namespace exacto:

```text
private/staff-admission/sma_<32 hex>.enc
```

ID opaco generado por código servidor con HMAC dedicado a actor/caso/operation-id. La entrada nunca contiene ruta/key de storage. Reproducir ese ID con la misma clave de operación permite localizar un huérfano tras fallo SQL sin un journal paralelo que guarde datos personales. La clave de operación es independiente de las llaves de cifrado y debe preservarse para reconciliar operaciones antiguas; rotación/recuperación y retención reales siguen pendientes.

Root existente, absoluto/canónico, fuera del árbol de aplicación/document root, no solapado con raíces públicas; se rechazan symlinks de root, componentes y destino, traversal y claves de otros ámbitos. Directorios0700 del usuario del servicio, archivos0600; se rechazan archivos con múltiples hardlinks al leer. Escritura temporal exclusiva, copia acotada/verificada, flush/fsync y link atómico sin clobber dentro del mismo filesystem; nunca truncar un asset existente. No URLs públicas, ni permiso ampliado en el adapter C4. El usuario OS propietario se considera confiable: PHP no ofrece aquí una garantía openat/O_NOFOLLOW contra un atacante que ya controle esa misma cuenta y reemplace componentes entre checks. Custodia operativa debe protegerla.

[Cipher](../../modules/identity/StaffMedia/Cipher.php) reutiliza la primitiva sodium XChaCha20-Poly1305 del patrón C4, con dominio **STAFF-ADMISSION-MEDIA-v1**, sin llamar al cipher C4. AAD estructurada JSON liga caso, cuenta, asset, propósito, versión del asset y perfil, referencias de custodia/retención. Nonce aleatorio24 bytes, tag16, keyring inyectado y key-id explícito; sin llave/default/env de prueba en producto. Persistencia incluye algoritmo y SHA-256 del ciphertext; lectura verifica longitud/hash, AEAD/contexto/key-id, longitud plaintext y MIME antes de devolver bytes. Falla cerrada por contexto incorrecto, clave desconocida/incorrecta o tampering.

Plaintext sólo en memoria y staging privado0600 mientras el procesador GD lo requiere; temporales retirados en finally. GD también crea temporal0600 del derivado en el tempdir del sistema y lo retira al finalizar. Ninguna serialización de bytes/llaves en Git, audit o evidencia QA. Fallo fatal del proceso/OS puede dejar temporales y requiere futura custodia/reconciliación operacional; esta fase no instala un limpiador global. Product delete siempre falla `staff_media_disposal_policy_unavailable`.

## Validación de imagen y límites pendientes

[Images](../../modules/identity/StaffMedia/Images.php) recibe bytes acotados, no rutas del usuario. Comprueba longitud real, finfo, getimagesize, MIME concordante con declaración, dimensiones/píxeles y estructura antes de GD. JPEG/PNG solamente. No trusts de extensión/browser MIME; no streams ilimitados ni importación de imágenes médicas.

- PNG: firma/chunks/CRC completos, IHDR único primero, IDAT e IEND, sin bytes posteriores. Evidencia admite IHDR/PLTE/IDAT/IEND/tRNS; rechaza metadata, animación, chunks desconocidos y comprimidos fuera del subset. Retrato admite además tEXt/pHYs/sRGB/gAMA/cHRM/eXIf y los elimina al recodificar.
- JPEG: segmentos completos y allowlist SOF0/SOF2/DHT/DQT/DRI/SOS/APP0 JFIF/JFXX, scan y EOI sin trailing. Retrato admite adicionalmente COM y APP1 Exif para orientar/retirar; evidencia los rechaza. Otros perfiles JPEG quedan no soportados, sin fallback por extensión.
- Reusa GdPublicLogoProcessor para MIME/decode/orientación y derivado. El writer público DoctorProfilePhotoService nunca se invoca. Retrato conserva sólo WebP recodificado; evidencia conserva bytes del raster original después de validar/decode, sin convertirlo a avatar ni reducir el documento a800px. El derivado de validación de evidencia se descarta.
- **PDF NOT_IMPLEMENTED**: no parser estructural aprobado/demostrado; incluso un PDF nominal sintético se rechaza. Sin OCR, face recognition, liveness, biometría, consulta de registro, autenticidad/validez documental automática ni afirmación de pipeline antivirus completo.

Policy requiere valores explícitos, ninguna política live predeterminada. Ensayo: input65536 bytes, lado512,65536 píxeles; retrato hasta800px/153600 bytes como parámetros compatibles con los tech ceilings del procesador existente. Sus constantes de implementación no ratifican límites staff. Se verifica la salida contra límites inyectados; configuración más restrictiva puede rechazar el resultado. Claves aleatorias desechables y referencias `synthetic-*`, nunca política productiva. Ratificar límites/formato/retención/custodia antes de intake real.

## Modelo y materialidad

[Migración18_06](../../modules/identity/db/migrations/2026_10_18_06_staff_private_media.sql) sólo después de IMP01/02 en un target expresamente autorizado. Sin editar SQL histórico, backfill, DeploymentPackage ni orden COMPAT01.

| Tabla | Datos e invariantes |
|---|---|
| auth_staff_media_assets | Asset ID, caso, cuenta ligada, propósito, asset_version/previous_version, case_version FK de snapshot, private key, key-id/algoritmo/hash/bytes cifrados, MIME real/source MIME, longitud/width/height, actor/credencial/fecha, referencias custodia/retención y audit UUID. Unique caso+purpose+versión, FK anterior del mismo caso/propósito y cuatro triggers de historia/recibos. |
| auth_staff_media_operations | PK actor+operation UUID, caso/asset, HMAC de intención completa y contenido, audit UUID, fecha. Un resultado por asset, FK y registro inmutable. No almacén paralelo de personas/auditoría. |

Actual/histórico se deriva de MAX(asset_version) por caso+propósito bajo lock del caso; reemplazo inserta nueva versión sin UPDATE ni borrado anterior. Versionado de media es independiente del personal: el snapshot de caso en cada asset deja explícita la información vinculada a su captura. Write/read no alteran cases/versions/invitations, cuentas, staff, grants o MFA. Cambios posteriores del perfil se exponen como `profile_changed_since_capture`; no convierten evidencia vieja en evidencia revisada de nombres nuevos.

No reviewVersion se deriva de upload/hash/retrato. Toda futura elegibilidad deberá comprobar exactamente perfil, evidencia actual, credencial y decisión humana vigentes; cambio material exige reevaluación. UnavailableStaffIdentityReview y appointment fail-closed permanecen idénticos. Los triggers no son una política de conservación perpetua: la eliminación gobernada deberá tratar bytes/versiones/derivados/huérfanos/backups/llaves y metadata audit mínima, con plazos y excepciones aprobados; no implementada ni ejecutada en producto.

## SQL, archivos, idempotencia y reconciliación

Write valida actor/caso/versiones bajo locks, determina ID servidor, comprueba ausencia física, valida/procesa/cifra, almacena archivo inmutable y después inserta metadatos/recibo junto con audit en el mismo PDO. La transacción SQL no abarca el filesystem. Si SQL/audit/sesión/commit falla después de almacenar, **se conserva ciphertext**; nunca se elimina como compensación ni se reintenta a ciegas.

Operation-id UUIDv4 por actor con HMAC de payload ordenado y contenido. Duplicado exacto legítimo retorna asset/recibo previo, sin nuevo archivo; intención distinta deniega. MAX versión y expected_asset_version bajo lock dan un ganador por reemplazo concurrente. La clave estable permite detectar el huérfano en un replay sin SQL previo: responde reconcile_required, no sobrescribe.

Result exige sesión y autoridad propia actual sobre caso/purpose/operation exactos; audita consulta y clasifica:

| Estado | Significado/acción |
|---|---|
| COMMITTED_AVAILABLE | Recibo, asset y lectura criptográfica disponibles; recuperar resultado de ACK perdido. |
| COMMITTED_FILE_UNAVAILABLE | SQL confirmado, archivo ausente; no rollback ficticio ni éxito de lectura. Restauración futura gobernada. |
| COMMITTED_KEY_UNAVAILABLE | SQL/archivo, llave no disponible; no borrar ni regenerar clave. |
| COMMITTED_INTEGRITY_UNAVAILABLE | Cifrado/contexto/longitud/hash inválidos u otro fallo; mantener evidencia y cerrar lectura. |
| ORPHAN_ENCRYPTED_RETAINED | No recibo/asset SQL, archivo exacto de esa operación presente bajo locks; conservar para reconciliación gobernada, sin adoptar/borrar automáticamente. |
| NO_COMMITTED_OPERATION_OR_FILE | Ausencia verificada en SQL y ubicación de esa operación; no ejecución/retry implícitos. |

No listado filesystem público ni camino de eliminación producto. Un outcome de write con committed=true prueba el commit histórico, no disponibilidad eterna del archivo. Bytes de read sólo salen después de audit y revalidación final de sesión exitosos. Un archivo faltante jamás convierte el SQL en no confirmado.

## Auditoría y producto cerrado

[Audit](../../modules/identity/StaffMedia/Audit.php) usa CanonicalAuditWriter/JoinedPdoCanonicalAuditTransactionAdapter. Se añaden sólo STAFF_MEDIA_CHANGED y STAFF_MEDIA_READ, catálogo61. Eventos anteriores59 byte-equivalentes en política, pin SHA-256 `ddbaf575ecbb95ec0ac3671ddfd36a3d7abf870164feec1930f1496c99ae2f1e`; se preservan también pins57/52/38. ADMIN/SENSITIVE_ADMIN_OPERATION existentes, retención ROLE_ADMIN sin plazo inventado.

Metadata permitida únicamente operation/case_id/purpose/asset_version, con actor/sesión/correlación/recurso/tiempo canónicos. Sin nombres/email/document number/bytes/keys/storage paths. SUCCESS/USER_REQUEST; DENIED/POLICY_DENIED para peticiones sensibles de actor autenticado fuera de ámbito; FAILURE/INTERNAL_ERROR para lectura autorizada no disponible. Sesión inválida se rechaza antes del dominio, sin inventar un contexto autenticado de audit. Falla audit de escritura revierte metadatos/recibo/stream heads; falla audit de lectura no devuelve contenido. Tests verifican también fallo SQL posterior a append audit exitoso.

[Composition](../../modules/identity/StaffMedia/Composition.php): gate nuevo default off; flag1 todavía exige autorización de fundación y termina en `staff_media_production_policy_unavailable`. No selección de test authority desde HTTP/env, ninguna ruta upload/download, sin cambios de app original ni placeholders. Hash propio de StaffMedia y migración disponible para futura composición; source/schema/private-policy gates anteriores no se debilitan. Una futura conexión deberá añadir la aprobación específica y pin de media, custodia/keyring/límites ratificados y reader exacto; este documento no los habilita.

## QA y límites

[Runner](../../scripts/ase-adm02-media-qa.py), [checks](../../scripts/ase-adm02-media-checks.py), [fixture CLI](../../modules/identity/tests/StaffMediaFixture.php), [test audit](../../modules/platform/tests/StaffMediaAuditPolicyTest.php). Reutiliza harness propietario/namespace temporal IMP01 con instancia vacía por ejecución; valida datadir antes de DDL, añade IMP02/IMP03 sólo allí. Primero ejecuta201 regresiones IMP01/02, incluida aceptación de invitación legítima, registro/verificación/login canónicos sintéticos, siete carreras y Master indisponible. Media usa esos vínculos reales del ensayo, sin un binding positivo falso nuevo. Principal ase_media no puede modificar accounts/cases/staff/grants; sólo SELECT de autoridad y INSERT de media/audit.

**312 comprobaciones PASS** (201 de regresión IMP01/02 y111 de media), **nueve escenarios concurrentes** (siete anteriores y dos de media), tres tests de política audit y lint PHP/Python PASS. [Verificación final](../evidence/ase-adm02-imp03/final-verification.json) y [limpieza propietaria](../evidence/ase-adm02-imp03/cleanup.json).

A–AJ: retrato/metadata, separación de propósitos, evidencia cifrada, MIME/decodificación/bytes/dimensiones, formatos rechazados, historia, autoridad y denegaciones auditadas, contexto/keys/tampering, path/symlink/public root, idempotencia/reconciliación, carreras SQL, rollback/audit de lectura, archivos huérfanos/ausentes, conservación de IMP01/02/staff/grants y producto cerrado. No fotografías humanas/IDs, importaciones, customer files, llamadas externas, browser QA ni aceptación PDF. Fixtures son bloques de color artificiales; GD agrega densidad/comentario por defecto y el generador los retira explícitamente en los fixtures de evidencia para cumplir el subset, sin relajar el validador.

Fallos de harness conservados en evidencia: primer PNG de evidencia contenía pHYs no admitido; se corrigió el generador. Consulta/trigger audit usó inicialmente el nombre lógico event_type en vez del físico action/outcome; se corrigió QA conforme AuditV1PhysicalMapper. No cambios de guardas para obtener PASS. Recursos de cada intento se detienen por handles propietarios; limpieza sólo tras verificar ownership/ausencia de procesos. Archivos/keys sintéticos fuera de Git y retirados al cierre. No se afirma borrado forense de bloques/snapshots del filesystem.

Limitaciones: no lector de directorio/reviewer, política live de límites/custodia/retención/rotación/backups, PDF, catálogo extranjero, decisión de identidad/admisión, upload/download HTTP, UI o operación real. Ensayo incremental sobre schema de admisión ya ejercitado en una instancia nueva, no upgrade sobre DB persistente ni garantía de rollback DDL.

## CTX-SYNC01

| Campo | Valor |
|---|---|
| CONTEXT_BASELINE | 27e0da02a4daf15d3c1a32ab5590e2527e835b52; oficial f92f37f1; visual312ee388; POL01/IMP01/IMP02 e IW01/ASE/GOV/COMPAT. |
| AFFECTED_CAPABILITY_IDS | ID-07 / COM-10; reuso ID-01/02/03 y primitivas Media, sin nuevas capabilities de reviewer. |
| AFFECTED_JOURNEY_IDS | J09 fundación privada parcial; J08/D01–D08 sin cambios. |
| AFFECTED_DATA_OBJECTS | auth_staff_media_assets/operations nuevos; dos eventos audit. Cases/versions/invitations y cuentas/staff/grants intactos. |
| AFFECTED_CONTEXT_FILES | Este contrato; sucesores POL01/IMP01/IMP02; estado/router; inventario/datos/interacciones; IW01. |
| CONTEXT_CHANGES_IMPLEMENTED | Propósitos/storage/cipher/versiones/autoridad/derivados/reconciliación/audit/QA y bloqueos futuros explícitos. |
| CONTEXT_VERIFICATION | Evidencia QA, política audit/pins, lint, diff, referencias y hashes/protección de trece worktrees/listeners/pointer. |
| CONTEXT_SYNC_STATUS | PASS_UPDATED para fundación sintética, no readiness operacional. |
| DOCUMENTATION_COMMIT | Commit de código/documentación informado al cierre, sin hash autorreferente futuro. |
| REMAINING_CONTEXT_GAPS | Legal/retención/borrado/custodia/rotación/backups/límites/PDF/catálogo extranjero/readers/delegación; revisión IMP04 y handoff IMP05 separados. |

Siguiente autorización: ratificar parámetros pendientes y definir la fase de revisión/reader posterior. No autoriza recibir documentos reales, conectar UI, merge ni desplegar.
