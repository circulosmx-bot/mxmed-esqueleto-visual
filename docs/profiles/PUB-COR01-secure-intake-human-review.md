# PUB-COR01 — informes públicos y revisión humana

2026-10-16. Baseline `bb7e1c177cf6462b60d2cd3b063f5e3d5aec1e58`, rama `ux/consultation-step2-vitals-r1`, árbol inicial limpio y upstream igual. Implementación acotada sobre `/Users/circulodigital/Documents/GitHub/mxmed-step2-vitals-r1`; no se modifica el checkout hermano. Resultado de aceptación: `READY_MXMED_PUB_COR01_INTAKE_MODERATION_ISOLATED`.

La recepción y la moderación funcionan con persistencia real en MySQL **sintético y desechable**. Se reutilizan el perfil público y el workspace interno originales. Aceptar un informe sólo registra `ACCEPTED_FOR_APPLICATION`; no aplica cambios. Normal-local continúa inactivo, sin DDL, grants, cuentas, sesiones o datos reales nuevos. No se ejecuta aprobación física de Safari.

## Superficies y composición

* El desplegable original `profiles/doctor.php` / `#mxpp-correction` conserva “Sugerir corrección”. El formulario interior se titula “Informar cambios de este perfil”. Su perfil de contexto procede del servidor. Claim y FORMAR PARTE conservan sus rutas, contratos y escritores.
* Recepción: `POST /api/profiles/public/correction-reports.php`, únicamente JSON y sin autenticación Identity obligatoria. No existe lectura pública por ID de recibo.
* Revisión: `internal/media-review/index.php?section=correcciones`, dentro de la entrada original y con sus componentes `inbox`, `cards`, `card-body`, botones y estilos. La API privada es `POST /api/profiles/internal/correction-review.php`; acciones cerradas `queue`, `open`, `begin`, `decide`. No es otro portal ni otro login.
* `ReportRuntime` requiere inyección explícita de `ReportHttp`; no selecciona configuración por ambiente ni genera claves o políticas por defecto. El único instalador entregado es `CorrectionQaRouter.php`, guardado por SAPI, flag sintético, nombre aleatorio de DB, usuario exacto y `CURRENT_USER()/DATABASE()`. No se instala en normal-local.
* Sin composición, el perfil conserva exactamente la presentación informativa deshabilitada. Las APIs y la sección de revisión devuelven indisponibilidad 503. No se abren tablas nuevas ni se leen sesiones reales para habilitar esa presentación.
* El login existente permite exclusivamente el nuevo destino interno exacto, sin parámetros adicionales ni fragmento. La excepción de navegación no concede acceso a la aplicación clínica: la página y cada comando exigen de nuevo el permiso staff. Un anónimo en la composición activa vuelve al login canónico con ese destino; alguien sin grant recibe denegación.

El perfil original continúa obteniendo su DTO público de `PublicProfileController` / `PublicProfileRepository`. Para el envío se vuelve a resolver ese mismo modelo **sin override de plan**: perfil público, fila exacta, plan FREE explícito de la fuente canónica reconocida y fuente de membresías disponible sin administración activa. Owner, administrator o collaborator de scope profile/profile_doctor bloquean el informe. Una fuente ausente, incompleta, fallida o un plan desconocido no acredita elegibilidad. No se sustituye este gate por el gate owner-only de Claim.

La ruta habilitada en este incremento es `/profiles/doctor.php?doctor_id=<referencia exacta>`. Un alias SEO todavía no implementado, otra consulta o un cambio de contexto no se adopta como origen. El compositor futuro debe disponer de una fuente comercial FREE autoritativa: QA agrega el campo opcional reconocido `plan_code` sólo a sus perfiles sintéticos; no se agrega ese campo ni se presupone FREE en `mxmed`.

## Datos, privacidad y abuso

`ReportCatalog` versión 1 contiene exactamente las diez categorías aprobadas, incluidas retiro y fallecimiento como **posibles hechos informados**, no estados profesionales. Ocho relaciones declaradas, opcionales; la ausencia se guarda como cadena vacía. El correo es opcional, validado y privado. No se pide nombre del informante, cuenta, contraseña, autoridad profesional, propietario propuesto, archivo, teléfono ni información clínica.

La explicación requiere 20–2000 caracteres Unicode válidos; el correo admite hasta 254. Se rechazan controles no permitidos, valores estructurados en campos escalares, categorías/relaciones desconocidas, campos extra y falta del acuse explícito de privacidad. No hay HTML interpretado: el servidor conserva texto cifrado y el workspace usa `textContent`. El formulario carece de almacenamiento local; limpia contenido tras confirmación y al abandonar la página. Todos los formularios tienen POST explícito para evitar el fallback GET con datos en la URL si falla JavaScript.

El servidor emite un intent firmado de quince minutos, ligado al perfil exacto, ruta original, nonce, cookie privada `__Host-` Secure/HttpOnly/SameSite=Strict y token `CsrfTokenService::issuePreAuth`. La firma, dominio HMAC y vínculo al CSRF no permiten adoptar un intent de otro navegador o perfil. El cuerpo no acepta `doctor_id`. El envío exige HTTPS verificable con `GovernanceTransport`, host y Origin exactos, referer del perfil original y metadatos same-origin cuando existen. El body se limita a 16 KiB antes de decodificar; no se confía en `X-Forwarded-Proto` ni en IP reenviada sin autenticar. Sólo el terminador TLS existente puede firmar el transporte; no se modifica ni expone el servidor normal.

`ReportRatePolicy` define la operación exclusiva `PUBLIC_PROFILE_CORRECTION_SUBMIT`: por hora UTC, tres reservas por navegador, diez por peer de transporte y veinte por perfil. No toca operaciones ni buckets de login/reset. Las dimensiones se guardan con HMAC de propósito propio, nunca con IP o cookie brutas. Se reserva el presupuesto durable antes del intake: un rollback de auditoría no devuelve intentos ilimitados. El replay exacto de un informe ya confirmado no consume otro presupuesto. Exceso → 429; fallo de almacenamiento de abuso, persistencia o auditoría → error sin éxito anunciado. QA inyecta límites peer/perfil 100 para sus recorridos múltiples; verifica además los límites independientes y la denegación incluso al cambiar navegador o peer.

El peer es la dirección de socket confiable, **no** una IP de visitante supuestamente extraída de headers. Tras un proxy compartido ese límite es conservador y compartido. Una futura política para IP de visitante autenticada requiere contrato del proxy, privacidad y aprobación de parámetros; no se añade aquí una ruta de confianza implícita. El conjunto de fingerprints, claves y límites necesita autorización operativa antes de recopilar datos reales.

El éxito sólo se muestra después de persistir informe y auditoría en la misma transacción:

> Recibimos tu informe. Nuestro equipo revisará la información antes de realizar cualquier cambio.

Un recibo opaco no otorga lectura, seguimiento público, autenticación ni privilegios. No contiene explicación, contacto o vínculo privado. Un timeout es recepción no confirmada; el mismo formulario puede repetir el comando exacto dentro de su ventana. Un contenido diferente con el mismo intent produce conflicto, no sobreescritura. Un intent expirado no sirve para leer o recuperar datos.

## Agregado Profiles y migración preparada

[2026_10_16_01_public_correction_reports.sql](../../modules/profiles/db/2026_10_16_01_public_correction_reports.sql) es aditiva y no contiene seeds. Requiere el runtime de gobernanza existente, sus tablas MFA y `2026_10_16_01_governance_security_bindings.sql`. Es compatible con las migraciones preparadas Identity 02–06; QA ensaya 01→06 y después esta migración para comprobar esa composición. Ninguna se ejecuta en `mxmed`.

| Tabla | Contrato |
| --- | --- |
| `profiles_correction_reports` | UUID opaco generado por servidor, FK de perfil, ruta exacta, catálogo/categoría, payload original cifrado, key ID, referencia de retención, digests de intent/comando, referencia de abuso, auditoría y fecha. Inmutable. |
| `profiles_correction_decisions` | Historial append-only por informe/versión, estado anterior/nuevo, revisor/credencial, caso y proof únicos, motivo, evidencia/notas cifradas y referencia de auditoría. |
| `profiles_correction_review_intents` | Comando privado congelado y cifrado sobre caso, informe/versión y fingerprint del grant. Inmutable; no reutilizable para otro informe. |
| `profiles_correction_rate_buckets` | Presupuesto propio por operación/dimensión HMAC/ventana, con incremento serializado. Sin referencias a cuentas de informantes. |

La cabeza lógica inicial es PENDING_REVIEW/1. La cabeza actual se deriva de la última decisión. `SELECT ... FOR UPDATE` sobre el agregado y comparación de versión realizan CAS antes de añadir historia. Los triggers rechazan UPDATE/DELETE de informe, intent e historia; guardan secuencia, estado anterior, caso consumido y proof exactos. Las FKs restringen borrados y retargeting. El replay de migración no es un instalador idempotente ni una vía de reparación: falla al encontrar sus tablas; cualquier instalación parcial exige inspección, sin replay ciego.

`ReportCipher` reutiliza sodium XChaCha20-Poly1305, con AAD y HMAC propios `PUB-COR01`, separados de C4. El AAD liga payload a informe+perfil y decisiones a caso/decisión+informe. Cambiar scope falla al descifrar. Key ID y política de retención quedan registrados. Las claves son inyectadas, sin defaults o credenciales reales; QA usa claves efímeras nuevas y la referencia inequívocamente sintética `synthetic-only-no-real-retention-approval`.

**Retención real: pendiente de aprobación del Director.** No existe duración real aprobada, purga pública, carga de documentos o procedimiento de eliminación/rotación operativa nuevo. Antes de activar se necesita custodia, recuperación, rotación conservando keys históricas, atención de derechos/retención y un procedimiento privilegiado aprobado compatible con la historia inmutable. El runtime de la aplicación no puede borrar esa historia.

## Auditoría y revisión autorizada

Se agregan dos eventos finitos Profiles: `PUBLIC_PROFILE_CORRECTION_RECEIVED` y `PUBLIC_PROFILE_CORRECTION_REVIEWED`, operación correlacionable propia y source PROFILE. El writer canónico y su adaptador PDO joined conservan la auditoría dentro de la transacción de recepción/decisión. Se preservan exactamente las primeras **54** políticas y mappings publicados; la nueva suite compara su digest contra el baseline. El catálogo total pasa a 56 sin cambiar semántica de los eventos anteriores.

La convención anónima es explícita: actor type `anonymous_reporter`, principal `report-<UUID del informe>`, role `unverified_public_reporter`, sesión/effective account nulos. No se crea ni suplanta una cuenta staff o médica y no se vinculan informantes entre reportes. La política de recepción no exige actor Identity o sesión. La metadata sólo contiene operación, versión, estados, caso y capacidad; no email, explicación, evidencia libre, cookie, intent, contraseña, OTP o IP brutos. Los eventos de revisión usan la cuenta/sesión canónica actual del staff.

La capacidad nueva y exclusiva es `public_profile_correction_review`, registrada en `InternalCapabilityCatalog`. En QA la concede el servicio canónico `InternalGovernanceService` desde un Director sintético. No se conceden grants reales. No equivalen C4, revisión de medios, owner, operador, curación/publicación ni autoridad clínica. Bajo el servicio MFA existente, los revisores de esta fase deben ser staff ACTIVE de clase DIRECTOR o MASTER_ADMIN, con cuenta activa, email verificado, credencial y sesión actuales y exactamente un grant activo vigente. La delegabilidad del catálogo no elimina ese requisito de MFA; no se habilita moderación por ADVISOR sin un incremento autorizado de su contrato de seguridad.

El alcance del grant es la **cola interna de informes de perfiles**; no es acceso clínico ni autoridad sobre sus cuentas. La cola autorizada muestra 50 informes por página, pendientes primero, sin payload/contacto. Abrir un informe vuelve a comprobar sesión, cuenta, credencial, staff y grant, audita la lectura y entrega sólo ese payload/historia. No existe búsqueda pública por contacto ni permiso de lectura derivado del recibo. El revisor con este grant de dominio puede consultar distintos informes; un caso/proof nunca autoriza decidir sobre otro informe, perfil, cuenta o versión.

Para decidir, `begin` exige reautenticación canónica y congela estado, motivo, nota, referencias y attestación de hechos en un caso de cinco minutos `PUBLIC_PROFILE_CORRECTION_REVIEW`. El MFA TOTP existente liga actor, sesión, credencial, operación, caso y perfil exactos. Al no existir beneficiario Identity del informe anónimo, los campos target/beneficiary MFA se refieren expresamente al propio revisor; el recurso real vive en el intent inmutable. No se inventa un informante Identity. Reiniciar una decisión revoca el caso OPEN anterior del mismo revisor/perfil; conserva historia y no actúa sobre otros revisores.

`decide` valida el caso exacto antes de gastar intentos MFA. En el commit se vuelven a validar grant/fingerprint, cuenta, credencial, staff, sesión, reauth reciente, perfil/informe/versión y proof actual, con locks SQL. Se consumen proof/caso, se añade decisión y auditoría atómicamente. Revocar o reemplazar el grant, deshabilitar cuenta, cambiar credencial, revocar sesión/factor, vencer el caso o cambiar la versión deniega. Dos decisiones reales paralelas producen un único ganador CAS. No se exige un segundo humano para revisión ordinaria.

La UI no deja mezclar dos aperturas concurrentes durante una decisión, vacía contraseña/OTP tras su acción y borra el contenido privado del DOM al abandonar la página. Una falla no muestra decisión confirmada. Tras un fallo de auditoría de decisión, no se registra resultado; el servicio conserva la posibilidad de reintentar el commit con el proof válido cuando corresponde, pero la UI no inventa recuperación por ID: puede reiniciar la ceremonia con nueva reauth/MFA. No promete entrega de aclaración.

| Estado | Resultado de esta fase |
| --- | --- |
| PENDING_REVIEW | Recepción privada durable, pendiente. |
| NEEDS_CLARIFICATION | Necesidad de aclaración registrada; no email automático ni canal público de seguimiento. Puede recibir otra decisión versionada. |
| REJECTED | Rechazo con motivo cerrado y nota privada, terminal. |
| ACCEPTED_FOR_APPLICATION | Hechos contrastados y al menos una referencia privada; cambio al perfil pendiente de otro procedimiento autorizado. Terminal en esta fase. |
| APPLIED | Reservado. Rechazado en servicio/esquema y ausente de las opciones de decisión. |

## Aplicación futura separada

No se invocan `PrivateProfileController`/`PrivateProfileRepository`, `DoctorContactPointsController`, `ProfessionalInformationService` ni los escritores de consultorios para aplicar informes. Existen escritores canónicos por campo, pero no se encontró un comando gobernado que consuma una decisión de corrección con todas sus precondiciones. El permiso de revisión no otorga un PATCH o SQL genérico.

Un futuro adaptador deberá recibir report ID + decisión aceptada/versión + perfil exacto + revisión vigente de los campos + cambios **tipados y allowlisted**, y exigir permiso de aplicación distinto, reauth/MFA según riesgo, caso exacto, idempotencia y auditoría atómicos. Volverá a comprobar estado, autoridad, fuentes y conflictos; aplicará solamente los escritores autorizados y registrará APPLIED después de su commit. Texto del informante o una referencia de evidencia nunca serán SQL/PATCH ejecutables. Reapertura terminal, rectificación del informe y contacto posterior requieren contratos separados.

Retiro/fallecimiento requieren además un procedimiento verificado de publicación/lifecycle propio. Ni recepción ni aceptación ocultan/publican el perfil, cambian estado profesional, identidad, credencial, propiedad, plan, Agenda, firma o datos clínicos, ni generan anuncios públicos.

## QA y preservación

Runner reproducible, con conexión administrativa privada existente y evidencia nueva fuera del repositorio:

```sh
python3 scripts/gov03b-disposable-qa.py \
  --connection /ruta/privada/connection.json \
  --evidence /ruta/privada/nueva/PUB-COR01 \
  --suite correction
```

El owner crea un DB `mxmed_gate4d_preview_cor01_<10hex>` y SQL principal aleatorios nuevos, copia **sólo esquema** normal, prepara rutinas de auditoría para ese DB, aplica migraciones sólo allí e inicia Valkey/HTTPS/PHP propios en puertos efímeros. El principal de aplicación sólo accede al target propio, sin DDL; los fixtures, cuentas, grants, factores, claves y perfiles son sintéticos. Se elimina DB, usuario, Valkey y servidores propios incluso tras errores. Los listeners normales no se reinician. El keyring de reportes no se escribe en la configuración normal. La suite también ofrece `--native-only` para diagnóstico.

Aprobación aislada: **71 comprobaciones nativas**, 18 grupos de navegador en seis recorridos Chromium/WebKit a **1440×900, 1366×768, 820×1180**, con persistencia SQL real, login/MFA canónicos, capturas inspeccionadas, labels, validación, teclado, ausencia de overflow, errores honestos y ausencia de datos privados en URLs. Los recursos externos de mapas/fonts se sustituyen sólo en el navegador QA para no contactar terceros; templates, scripts y CSS de primera parte siguen siendo originales. La prueba no acredita funcionamiento de Google Maps ni aprobación física Safari.

| Escenarios solicitados | Evidencia de aceptación |
| --- | --- |
| 01–04 | FREE permitido; pago/owner/collaborator/fuente fallida denegados, más 52 aserciones del gate original. |
| 05–09 | Perfil/ruta firmados, Claim/FORMAR PARTE separados, anónimo 401 en Identity, membresías intactas, sustitución/cookie ajena denegadas. |
| 10–13 | Catálogos/tipos/carácteres/tamaño, CSRF ausente, Origin/referer ajenos y backend cleartext denegados. |
| 14–16 | Buckets propios, límites browser/peer/perfil, almacenamiento de abuso fallido, replay y concurrencia anónima, rollback de auditoría. |
| 17–18 | Payload cifrado/AAD, texto XSS inerte, contacto privado, sin lectura pública por recibo ni adjuntos. |
| 19–22 | Sin grant, sólo C4, owner/usuario, grant revocado/reemplazado, cuenta/credencial/sesión invalidados y proof de otro informe/perfil/cuenta denegados. |
| 23–24 | Dos procesos/PDO independientes, una decisión CAS ganadora; triggers rechazan overwrite/delete de historia, informe e intent. |
| 25–29 | Rechazo, aclaración, aceptación, retiro y fallecimiento conservan checksum de perfiles, consultorios y membresías; APPLIED denegado. |
| 30–34 | Checksums de enrollment/C4/Agenda/documentos en QA; snapshot completo antes/después de normal y tres QA persistentes idéntico. |

Regresiones reejecutadas: PUB-ENR02 39 nativas + 28 grupos de navegador; PUB-ENR03 89 nativas, incluyendo sus carreras y handoff/emisor; C4-EV01 86; emisor C4 101 más dos guardas de migración; Operadores OP01 38 casos/200 aserciones más marcador agregado y readiness ocho, presentación original 74; normal-local 80 comprobaciones con Valkey propio. Pasan además los 24 escenarios de corrección deshabilitada y seis originales Claim/FORMAR PARTE, gate/visibilidad, sesión privada/GOV-SIM02, resolver clínico y contrato Agenda CONS-VIS01. No se repite el navegador completo de resolución C4 en esta entrega; sí sus servicios nativos y la navegación pública existente.

Se conservan e informan tres fallos históricos, sin debilitar sus pruebas:

* `PhysicianCredentialAuditPolicyTest.php`: espera 52 eventos frente a los 54 que ya tenía el baseline; ahora son 56. Se reproduce el mismo fallo en el archivo Git del baseline. La nueva suite verifica por separado que sus primeras 54 políticas no cambian.
* `PublicProfileHeroConsultorioContactLayoutTest.php:52`: literal antiguo de Material Symbols; reproduce el mismo fallo con fuentes Git del baseline.
* `clin_auth02a_contract.php --security-regression`: AUTH02A_29 ve `modules/clinical/qa/ux_align01_presentation_fixture.php` entre sus operandos, idénticos en baseline y entrega. Los guards clínicos de producción y sus pins permanecen intactos; no se declara verde todo ese runner.

Antes/después: `mxmed` conserva 128 tablas, filas/checksums, DDL, FKs, triggers, rutinas y auditoría idénticos. Coinciden también QA05, OP02, UI03, configuración normal, journals y hashes de registros de sesión Valkey (cero registros en el namespace normal al preflight y al cierre). Ninguna cuenta, membresía, staff, factor, grant, owner, suscripción, cita o dato clínico real cambia. No hay email/SMS real, envío de invitaciones, subida real o firma clínica. Los listeners originales 8091, 8092, 8443 y 6385 conservan sus PIDs. GET anónimo normal: raíz/perfil/listado/join/login 200, current-session 401 y ambas APIs nuevas/workspace 503; ningún formulario nuevo activo.

Evidencia privada: `~/Library/Application Support/MXMED/pub-cor01/20261016-review/`, especialmente `acceptance-release/`, `preservation-result.json`, snapshots `before.json`/`after.json`, `session-before.json`/`session-after.json`, `normal-readonly-http.json`, manifests y `regressions/`. Se guardan fuera de Git por privacidad. Inventario de entrega: 37 archivos (30 de producto/esquema, cinco de QA y dos documentos).

## Activación pendiente

Siguiente incremento recomendado: preparar el paquete de despliegue/privacidad y revisar el contrato tipado de aplicación, por separado. Antes de habilitar recepción real se necesita aprobación del Director sobre finalidad/aviso y retención, custodia/rotación/recuperación de claves, límites de abuso/peer y origen TLS, fuente autoritativa FREE, principal mínimo y migración exacta con backup/restauración y fingerprints nuevos. Moderación real requiere además personas staff legítimas, grant exclusivo y MFA gobernado; no se infiere esa autoridad del Director clínico o de la pertenencia al perfil.

Sólo otra autorización explícita puede permitir DDL o composición normal-local. El aplicador de cambios, comunicaciones de aclaración y cualquier lifecycle/publicación por retiro/fallecimiento necesitan su propia implementación y autorización. Los manifests históricos C4/gobernanza no son receipts renovados por este incremento y deberán revisarse porque cambiaron fuentes compartidas. Esta entrega no habilita Operadores, gobierno normal, acreditación o firma.
