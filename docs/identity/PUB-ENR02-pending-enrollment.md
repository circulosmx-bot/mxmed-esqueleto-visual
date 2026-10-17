# PUB-ENR02 — origen público e información inicial pendiente

2026-10-16. Worktree `mxmed-step2-vitals-r1`, rama `ux/consultation-step2-vitals-r1`, inicio local/upstream/remoto `0df3fd018e7b3464dc09e082cac1c041a36e29b8`, limpio. Alcance: fuente funcional y ensayo nuevo sintético, sin despliegue ni envíos reales. Continúa [UX-ALIGN01](UX-ALIGN01-product-experience.md), no rehace su UI ni los escritores C4, membership, ownership o clínicos.

## Estado y composición

`PublicEnrollmentService` implementa el origen persistido, asociación explícita a cuenta e intake cifrado versionado. `PublicEnrollmentHttp` reutiliza Identity, transporte HTTPS autenticado del proxy, CSRF, sesión y `EvidenceAuthority`. `PublicEnrollmentRuntime` carece de selector de entorno y devuelve null por defecto. Únicamente el router CLI-server de QA desechable instala actualmente esa composición. Instalar el esquema, definir una variable o poseer un enlace no activa el servicio normal.

Se reutilizan `/profiles/doctor.php`, `/profiles/listing.php`, `/public/identity/perfil-medico.php`, registro, verificación y login originales. El nuevo endpoint del mismo dominio, `/api/identity/physician-enrollment.php`, devuelve 503 pendiente cuando no se suministra la composición. No hay otra aplicación, acceso, base de profesionales, escritor de cuenta o página administrativa. La UI pública normal conserva las tarjetas previas deshabilitadas; no promete estado guardado.

## Origen confiable y pruebas distintas de autenticación

El GET público resuelve el perfil mediante `PublicProfileController`/`PublicProfileRepository` existentes. Un parámetro selecciona presentación, nunca titularidad. La preparación devuelve una intención firmada, limitada a treinta minutos y a una prueba de navegador aleatoria. El POST explícito y protegido vuelve a resolver publicación, mínimos públicos, FREE explícito, fuente de owner conocida, ausencia de owner y conflictos de evidencia/binding. También exige coincidencia exacta con el ID almacenado, sin adoptar aliases por collation. No se exige VID01/CRD02 al comenzar.

Al iniciar, la fila persistida congela UUID generado por servidor, tipo, perfil original o null, ruta pública real permitida, versión de read model y hash de proyección de elegibilidad. Claim deriva la ruta del perfil exacto. FORMAR PARTE conserva un referer público permitido cuando está presente; el acceso directo registra la propia entrada. No se aceptan destinos externos, parámetros de credenciales ni rutas privadas como procedencia. La ruta es procedencia, no prueba de que la persona sea el médico.

Las intenciones usan nonce de 256 bits. El handle opaco se deriva con un PRF HMAC y secreto privado independiente, permitiendo repetir una respuesta perdida sin almacenar el valor bruto. SQL guarda únicamente digests del handle, navegador e intención, no cookies ni token de sesión. La unicidad de intención impide duplicar un inicio al reintentar. El mismo navegador puede mantener varias pruebas por origen sin sobreescribir la de otra pestaña. Hay un límite de ocho inicios por navegador/hora; el despliegue público deberá además aprobar sus presupuestos operativos de abuso/capacidad y custodia/retención.

`__Host-mxmed_enrollment_browser` y la prueba específica por origen son Secure, HttpOnly, SameSite=Strict y Path=/, con vigencia acotada. La cookie de regreso contiene sólo el UUID público de navegación, comprobado contra la prueba de navegador y el estado en SQL. Ninguna es una credencial de sesión. Las pruebas no aparecen en URLs, notificaciones, auditoría o browser storage. Un UUID de solicitud tampoco autoriza a leerla.

Después de login/email verificado, el solicitante confirma expresamente continuar con su cuenta. El servidor vuelve a validar cuenta activa, credencial, sesión y exclusión de operadores no médicos, toma locks, comprueba ambas pruebas y consume el enlace de cuenta una vez. Una repetición por esa cuenta es idempotente; otra cuenta o navegador no puede adoptar el origen. La cuenta no se elige desde el payload. La unicidad cuenta/tipo/perfil impide aplicaciones contradictorias del mismo origen lógico; un duplicado independiente devuelve conflicto, sin fusionar orígenes ni reasignarlos.

Un origen sin asociar vencido no se renueva silenciosamente ni inventa intake: la página ofrece volver al perfil/directorio y reiniciar. Después de asociar, la aplicación se retoma con la cuenta canónica aun si la prueba pública ya venció. Ese acceso depende de Identity, no de extender un handle. IDLE=3600 y ABSOLUTE=43200 permanecen intactos.

## Registro y verificación originales, incluso con varias pestañas

La página original de registro conserva la ruta de solicitud validada en su contexto de presentación. Su POST usa los mismos `RegistrationService`, credenciales y consentimientos. Un decorador de `IdentityNotificationPort`, disponible sólo bajo la composición explícita, puede añadir a la notificación de verificación esa ruta exacta después de comprobar las pruebas server-side. `NotificationMessage` admite únicamente una continuación acotada a la ruta original de solicitud y sólo para email verification; la notificación SES y el sink local mantienen su contrato anterior cuando es null.

La continuación incluye sólo el UUID de navegación, nunca el handle, cuenta, contraseña, OTP, datos profesionales o autoridad. La otra pestaña no puede cambiarla sobreescribiendo una cookie de regreso. La página de verificación vuelve a validar el contexto, muestra el login habitual después del éxito y no autentica automáticamente. El login permite regresar a onboarding sin requerir una membresía clínica; el gate de `private-app.php` permanece independiente.

Antes del vínculo, el regreso confiable requiere conservar las pruebas del navegador original dentro de su plazo. Abrir el correo en otro dispositivo puede verificar la cuenta, pero no transfiere una intención pendiente: se debe volver al navegador original para continuar o reiniciar de forma segura. Después del vínculo basta la sesión legítima de la misma cuenta para retomar el UUID. El correo no se convierte en un mecanismo de adopción de origen.

## Esquema físico revisado e intake privado

La [migración preparada 05](../../modules/identity/db/migrations/2026_10_16_05_public_physician_enrollment.sql) sustituye el borrador lógico para este incremento; no ejecuta ni modifica 01→04. Se ensaya únicamente en el nuevo esquema propio, después de instalar esas dependencias para QA. No existe ejecutor normal-local en esta entrega.

| Tabla | Propósito y controles |
| --- | --- |
| `auth_physician_enrollment_origins` | PRESENTED→BOUND; tipo/perfil/ruta/procedencia/digests/fechas inmutables, revisión monotónica, plazo máximo de treinta minutos para adoptar, cuenta de un uso y unicidad lógica de aplicación. FKs a cuentas y perfil existentes. No delete de historia. |
| `auth_physician_enrollment_intake_revisions` | Una fila inmutable por versión; FK compuesta al origen/cuenta exactos, estado DRAFT/SUBMITTED, ciphertext, key ID, referencia de política y audit. Trigger exige la versión siguiente de una aplicación asociada todavía en preparación. |
| `auth_physician_enrollment_commands` | Idempotencia por aplicación/comando, digest HMAC del input normalizado, resultado/versiones ligados por FK; append-only. Un mismo comando con otro contenido se rechaza. |

La revisión del borrador corrige la ausencia de estado de intake, unicidad de cuenta/aplicación, prueba separada del navegador, clave de inicio idempotente, FK compuesta de propietario, comandos, guard de revisión y transición, y relación entre el avance del padre y su nueva revisión. No instala la relación de resolución revisada todavía sin escritor autorizado. El estado EXPIRED de una intención se determina por el reloj del servidor; no se habilita un worker de disposición ni eliminación automática.

El intake admite nombres, Primer Apellido, Segundo Apellido opcional, cédula declarada, especialidad, estado y ciudad. Cada valor tiene límite UTF-8, validación de controles y allowlist; no acepta cuenta, perfil, rol, owner, estado verificado o documentos. Guardar permite información aún incompleta; enviar exige los campos iniciales definidos. Son hechos declarados, no verificados. No se copian a VID01/CRD02 ni al catálogo público.

Se reutiliza `EvidenceCipher` XChaCha20-Poly1305, con scope que incluye aplicación, cuenta y versión, keyring y política inyectados; sin clave o política no hay almacenamiento. El ensayo usa claves efímeras y referencia sintética, nunca una política humana pretendidamente aprobada. Las revisiones se cifran antes del INSERT; los digests de comandos también son HMAC y no permiten un diccionario público de cédulas/nombres.

`EvidenceAuthority::transaction` valida la sesión/credencial al abrir y antes del commit, usando el mismo PDO de cuenta, origen, revisión, comando y writer canónico de audit. ORIGIN_BOUND, INTAKE_SAVED, INTAKE_SUBMITTED y MATCH_CANDIDATES_READ son operaciones finitas de `PHYSICIAN_EVIDENCE_CHANGED` con target `physician_enrollment`, distintas de las aprobaciones/autoridades finales. No se añaden eventos a su catálogo ni se cambian los checks previos. La creación preauth sólo conserva la intención sin cuenta; no se fabrica un actor/sesión de auditoría autenticados. La primera asociación y todos los cambios privados del intake sí son transaccionales y auditados. Un fallo de audit revierte el estado y no muestra éxito.

La lectura privada exige la cuenta asociada vigente; un endpoint público no entrega intake. SQL no contiene nombres/cédulas en claro y la auditoría no contiene payload, pruebas, contraseñas o documentos. Las respuestas y página privada tienen no-store y no-referrer. Al detectar sesión inválida, el componente borra los campos privados y deshabilita acciones; la observación de current-session no extiende el idle.

## Candidatos y handoff pendiente a evidencia final

`ProfessionalMatchCandidates` extrae sin alterar la precedencia del matcher existente: cédula exacta, luego combinación completa profesional/geográfica, luego no match. Tanto `ProfileRequestMatcher` como la lectura privada de candidatos del intake reutilizan esa consulta. El acceso del revisor exige capacidad staff exacta e independencia del solicitante; no hay acción pública para resolver ni enumerar candidatos. La lectura registra su audit y no modifica origen, perfil o cuenta.

Una coincidencia de una solicitud REQUEST_NEW_PROFILE conserva siempre ese origen y perfil original null. Una coincidencia distinta del target CLAIM conserva el target original y requiere conflicto humano. No match tampoco crea un perfil. La UI informa revisión pendiente; no ofrece resolver, acreditar, publicar, asignar owner o firmar.

El siguiente handoff deberá tener una resolución revisada inmutable que congele origen/intake, decisión, fuentes VID01/CRD02 legítimas y hashes actuales, caso/proof de revisión y auditoría. Para claim, el perfil resuelto debe ser exactamente el original. Para general, el consumidor final deberá reconocer explícitamente REQUEST_NEW_PROFILE resuelto sin inventar un CLAIM anterior. No se implementa ese escritor ni se adapta el issuer en PUB-ENR02. C4 final sigue exigiendo teléfono verificado, fuentes válidas, evidencia/documentos privados, revisión legítima con MFA, confirmación independiente y ausencia de conflicto.

## Ensayo reproducible y límites

```sh
python3 scripts/gov03b-disposable-qa.py \
  --suite enrollment \
  --connection /ruta/privada/connection.json \
  --evidence /ruta/privada/nueva-pub-enr02
```

El wrapper crea DB/usuario aleatorios propios, clona sólo esquema sin filas reales, instala 01→05, usa Valkey propio y semillas explícitamente sintéticas. Para los perfiles de QA añade únicamente en su esquema una columna comercial opcional ya reconocida por el read model y valores FREE/OPTIMUM explícitos; jamás interpreta un plan ausente como FREE. `mxmed` no tiene actualmente ese dato comercial explícito en `profiles_doctors`: su fuente/elegibilidad deberá resolverse antes de activar claims reales. No se cambia el read model comercial ni se añade esa columna al paquete normal.

La pérdida de fuente de owner se prueba renombrando/restaurando la tabla sólo dentro del esquema propio, no reseteando datos persistentes. Los workers PHP paralelos usan una sesión sintética legítima, el mismo writer SQL y comandos reales. El browser usa HTTPS de prueba, proxy y componentes originales; el perfil público usa su fallback local nativo al controller, sin inyectar otro DTO de producto. Chromium/WebKit ejecutan los tres tamaños, registro, email, login, asociación explícita, guardado, refresh, conflicto, envío y denegaciones. Los scripts no representan una revisión física del Director.

## Aceptación ejecutada

**RESULT=READY_MXMED_PUB_ENR02_PENDING_INTAKE_ISOLATED.** Funcionan el origen confiable y el intake pendiente en datos nuevos desechables. No significa acreditación final o activación normal-local. Las fuentes probadas se congelaron en un manifest privado y se publican por separado: núcleo/esquema, conexión original UI/Identity y documentación.

| Verificación | Resultado |
| --- | --- |
| Servicio nativo y SQL físico | 39 comprobaciones PASS: FREE/paid/owner, ID exacto, ausencia de VID01/CRD02, prueba de navegador, cuenta, expiración, cifrado, idempotencia/CAS, envío inmutable, audit rollback, matching separado, operador y credencial obsoleta. |
| Concurrencia PHP real | PASS: dos asociaciones simultáneas producen una aplicación; repetición del mismo comando produce una versión; comandos distintos con versión obsoleta producen un éxito y un conflicto. |
| Fuente de owner ausente | PASS nativo, sobre tabla temporalmente renombrada/restaurada sólo en el esquema propio. |
| UI pública/Identity original | 28 grupos de comprobación PASS; doce recorridos claim/general, Chromium y WebKit a 1440×900, 1366×768 y 820×1180. Registro y email conservan la solicitud exacta incluso con otra pestaña; no auto-login, no ID manual, refresh, envío, conflicto, CSRF/origin/transport, tipos malformados, cross-account y sesión expirada denegados. |
| Cierre y reanudación | PASS: logout canónico confirmado, campos/atributos/estado privado retirados, login legítimo retoma la misma solicitud. Los avisos de sesión entre pestañas sólo bloquean/limpian; no autorizan ni almacenan intake. |
| Presentación | Doce capturas sintéticas; revisión visual del claim WebKit a 820 y general Chromium a 1440, sin overflow y con campos etiquetados. No es aprobación Safari física del Director. |
| C4 original | 45 comprobaciones browser PASS y 101 comprobaciones del emisor nativo PASS, en bases propias separadas. Conserva teléfono/documentos/revisión/MFA/confirmación/issuer/denegaciones. Cero membresías emitidas por ese recorrido. |
| Identity/sesión | 80 comprobaciones normal-local aisladas PASS; PrivateSession02, GOV-SIM02 y notificación SES falsa/continuación acotada PASS. Sin cambios de TTL ni autoautenticación. |
| Agenda/Operadores | CONS-VIS01 y resolver clínico PASS; OperatorFoundation 200 aserciones y Readiness ocho casos PASS, con cleanup de sus bases nuevas. |
| Sintaxis y fuente | 26 archivos PHP/JS/Python PASS, whitespace PASS, manifest y fuentes clínicas/Agenda/Operadores productivas conservados. |

Los intentos iniciales fallidos también quedan en evidencia: ajuste del montaje local del perfil, lectura de body de respuesta que navega inmediatamente y corrección de expectativas del harness sobre fieldset deshabilitado/403 autenticado. La prueba de Origin detectó y corrigió una carga de clase faltante en el adaptador nuevo. La aceptación final repite los recorridos completos sobre fuente congelada; no cuenta esos intentos fallidos como positivos.

### Gate histórico clínico, sin ocultarlo

`php modules/clinical/qa/clin_auth02a_contract.php --security-regression` conserva un FAIL en `AUTH02A_29`. Se comprueba que ocurre también con el estado inicial `0df3fd01`: su `git diff` contra `aa7184c8` incluye únicamente la fixture no productiva `modules/clinical/qa/ux_align01_presentation_fixture.php`, incorporada por UX-ALIGN01. El contrato y todos los archivos clínicos actuales son idénticos al inicio de PUB-ENR02. Las comprobaciones anteriores del runner, el resolver, las denegaciones clínicas HTTP y los gates del issuer sí pasan; no se declara PASS la suite histórica completa ni sus assertions posteriores no alcanzadas. La prueba no se altera ni relaja. Evidencia: `regressions/clinical-security.log` y `clinical-baseline-pin.json`.

Se conserva además la deuda previa del pin de auditoría 52 frente a 54; no se repite ni cambia catálogo/política. Los fallos históricos de otros ámbitos documentados en UX-ALIGN01 no se convierten en PASS por esta entrega.

### Preservación final

`normal-before.json` y `normal-after.json` son idénticos: **128 tablas**, todas las filas/checksums, DDL, triggers, rutinas y auditoría. También coinciden snapshots de sesiones, configuración, journals OP-ACT02A y checksums QA05/OP02/UI03. No cambian membresías, autoridades, suscripciones, datos profesionales/clínicos o históricos. No queda ningún esquema propio c4ui/c4issuer del ensayo; los usuarios SQL temporales y procesos propios se eliminan. No se importó ninguna fila normal-local.

La QA HTTP normal usó sólo GET, sin cookies, con la CA existente verificada: perfil/listado/registro/acceso/entrada general 200; persistencia nueva 503 pendiente; anónimo privado 302 y current-session 401. La entrada general no carga el JavaScript de intake activo y conserva controles deshabilitados. Los listeners **8091, 8092, 8443 y 6385** siguen activos. No se abrió, recargó ni autenticó Safari por el Director durante PUB-ENR02.

Evidencia privada: `~/Library/Application Support/MXMED/pub-enr02/20261016-review/`, en particular `acceptance/`, `c4-acceptance/`, `c4-issuer-regression/`, `regressions/`, `tested-source-hold.json`, `normal-readonly-http.json` y `preservation-result.json`. Snapshots, claves, tokens y conexiones no se publican en Git.

## Próximo trabajo y autorizaciones separadas

El incremento de fuente siguiente es la resolución revisada y el handoff explícito intake→evidencia, primero en datos desechables: conservar la intención, caso/versión exactos, verificación legítima y revisión humana. Perfil nuevo y conflicto/transferencia siguen sin writer. No es necesario volver a implementar registro, sesión, matcher, issuer o membresías.

Para desplegar **intake pendiente** se necesita primero un paquete operacional revisado: fuente comercial explícita de elegibilidad FREE, orden/dependencias físicas y fingerprints, backup/restore, autorización de los SQL exactos sobre el target, key custody/retención/recuperación, principal mínimo, políticas de abuso/capacidad y compositor/guard autorizado por fuente/schema/cohorte. Ninguno está aprobado o activado por este commit. La compatibilidad de las fuentes de conflicto de claim requiere que las tablas canónicas esperadas estén disponibles; su ausencia no equivale a perfil sin conflicto.

Para continuar hasta la acreditación final rigen además los gates de [C4-ACT01](C4-ACT01-operational-activation-package.md): contacto/SMS operacional, custodia documental, staff/MFA legítimos y revisión/confirmación. Owner y firma clínica tienen autorizaciones independientes. El manifiesto C4-ACT01 es histórico y debe renovar sus fingerprints de fuente antes de cualquier autorización futura. Ninguna aceptación aislada autoriza DDL, SMS, documentos, staff, owner, composición operativa o firma reales.
