# OP-INT04 — Operadores dentro de la aplicación normal-local

**Actualización OP-IMP07:** la sección final documenta alias, posiciones y procedencia implementados y probados sólo en bases desechables. Sustituye las menciones anteriores a esos tres elementos como implementación futura; no activa normal-local.

Baseline `8e29d3fa5be7f6f75dfd7321b5cde58781f5d755`, rama `ux/consultation-step2-vitals-r1`, 2026-10-15.

## Cambio en el producto 8443

La aplicación objetivo es **https://localhost:8443/private-app.php**, panel `#p-ag-operadores`. OP-UI03 compartía templates, pero aún conservaba dos controladores y dos formularios de negocio: el normal legacy y el canónico aislado. Ese trabajo no había integrado el lifecycle canónico en la aplicación normal.

Ahora `partials/private-app/operators.php` contiene un único formulario: correo, tipo de integrante, invitación personal de siete días y cinco permisos independientes inicialmente apagados. Conserva el shell, lista/archivo, tarjetas, acordeones, resumen y modal de historial originales. Se eliminan del panel los campos/botones de usuario, contraseña temporal, credenciales compartidas y permisos amplios legacy. `assets/js/agenda/operators-panel.js` es el único controlador del panel y no consulta flags OP02. Recibe una decisión de readiness emitida por el servidor y un puerto de servicios; cuando hay servicio canónico autorizado reutiliza las mismas operaciones OP01/OP02, sin otro writer.

Normal-local carga este controlador dentro de `private-app.php`, con el resto de su aplicación médica. El antiguo IIFE de Operadores y sus fixtures Mary/Carlos, almacenamiento mutable, wizard de contraseña y migración local han sido retirados de `app.js`. No hay formulario de negocio alternativo por entorno. Las diferencias de componentes del shell siguen dependiendo del alcance autorizado del actor.

## Readiness real, sin activación implícita

`Identity\Operators\OperatorPanelReadiness::normalReadOnly` recibe el contexto médico ya autorizado por `PrivateApplicationAccessResolver`. Consulta metadatos del esquema; nunca intenta leer tablas canónicas ausentes. Las cuatro tablas OP01 no están presentes en `mxmed`. El servicio real no está compuesto/activado y la membresía real de doctor 1 sigue siendo **collaborator / profile / active**.

Por ello el HTML normal-local deshabilita los inputs, cinco grants y acciones de invitación antes de ejecutar JavaScript. Explica la falta de preparación y activación canónica y la necesidad de autoridad de titular. Los contadores canónicos dicen **No disponible**; el modelo aprobado se identifica como un titular y hasta dos operadores. No se afirma cupo libre, membresías canónicas creadas ni invitaciones enviadas. La presencia futura de DDL por sí sola tampoco activa un writer, ni lo hace una etiqueta owner o un parámetro del navegador.

En la composición de servicios ya autorizada para QA, la decisión disponible deriva del snapshot real del servicio y del owner verificado; las acciones continúan sometidas a sesión, CSRF, owner, revisiones, cupo, audit y plan del backend. El panel no importa `OperatorQaConfiguration`; el aislamiento queda en la composición de servicios. El adaptador acotado conserva exclusivamente las proyecciones de Agenda/invitaciones para operadores. Normal-local no carga ese adaptador ni cambia su Agenda médica.

## Origen exacto del tres y transición legacy

El modelo anterior usaba:

- `OperatorsRepository::MAX_ALLOWED = 3`, su comprobación de cupo y sus resúmenes históricos.
- `OperatorsController` devolvía `max_allowed => 3` en conflictos.
- El IIFE retirado de `app.js` tenía `plan_standard_limit: 2`, `plan_absolute_limit: 3`, textos de plan, fallback de fixtures y `max_allowed` del backend.
- El template normal anterior imprimía 3 como máximo.

No se cambia simplemente el texto a 2. **Todos los verbos distintos de GET de la ruta HTTP legacy `/api/agenda/index.php/operators` y sus subrutas están cerrados con denegación**, antes de instanciar su controlador. Se retiran creación, importación/migración, pause/reactivate/archive/restore y aliases de ese dispatch. Los métodos/repositorios históricos se conservan para lectura, análisis de reconciliación y sus contratos históricos; ya no tienen un writer HTTP operativo en la aplicación. Las demás rutas de Agenda conservan exactamente su implementación.

Inventario normal-local al preflight, sólo doctor 1: **1 activo, 1 pausado, 0 pendientes, 1 archivado; 9 eventos históricos**. La proyección nueva selecciona únicamente referencia, alias y estado; el historial sólo referencia/tipo/etiqueta/fecha. Filtra por el perfil derivado del servidor y no envía contraseñas, hashes, login, contactos o notas internas.

El panel muestra esos registros como **legacy**. El cache anterior del navegador se conserva íntegro y sólo se proyectan sus referencias/alias/estado con etiqueta de perfil sin verificar. Se separan las fuentes y se señalan referencias repetidas, sin fusionarlas. No se enlaza una cuenta Identity por nombre o correo, no se convierten permisos amplios, no se cuenta un registro legacy como plaza canónica y no se elimina/revoca a nadie. Un inventario por encima de dos plazas requiere conciliación; un fallo de lectura se presenta como no disponible, no como lista vacía exitosa.

## Agenda y seguridad

La fuente completa de Agenda, su partial, navegación/sidebar y guard de sesión son idénticos byte por byte al baseline. `app.js` conserva todos sus demás bloques, incluyendo clínica y Recetas. El dispatcher de Agenda cambia exclusivamente su case Operadores. No cambian los guards de CLIN-AUTH02A, acreditación/firma, paciente/perfil, fiscal o staff.

El shell médico completo sigue protegido por el resolver canónico. El operador no médico conserva la composición de servidor acotada, sin HTML o bootstrap clínico/fiscal oculto. El módulo común de panel no instala un segundo controlador de Agenda. OP01 mantiene el límite de dos plazas en transacción y la atribución a la cuenta real.

## DDL y activación que siguen pendientes

No se aplicó DDL a normal-local. Un corte real requiere una autorización separada para:

1. Rehearsal y backup/restauración de `mxmed`, comprobación de tipos/collations, claves únicas, checks y FKs de `auth_accounts`, `auth_account_memberships`, `profiles_doctors` y CanonicalAudit.
2. Aplicar y verificar `modules/identity/db/migrations/2026_10_15_02_profile_operator_foundation.sql`: `auth_profile_operator_invitations`, `auth_profile_operator_grant_revisions`, `auth_profile_operator_grants`, `auth_profile_operator_commands`. Su rollback existente no debe ejecutarse sin evaluación de datos.
3. Conciliar por referencias explícitas los registros legacy y su historial, fuentes locales, posibles duplicados y exceso de plazas; conservar la procedencia y obtener decisiones humanas. No hay migración automática por coincidencia de email/nombre ni asignación de permisos por plan.
4. Resolver la autoridad owner mediante el gobierno existente, sin promover automáticamente a la Directora ni cambiar su membresía collaborator. El owner no ocupa plaza; pending/active/suspended y reservas válidas se someten al cupo transaccional OP01.
5. Componer las rutas canónicas con sesión/CSRF/origin, proyección de operador, auditoría y entrega real autorizada. El outbox cifrado `2026_10_15_03_operator_qa_outbox.sql` es exclusivo de QA; **no** es una migración ni proveedor de entrega normal-local aprobado.
6. Ejecutar la matriz HTTP y de firma/clinical containment contra esa composición real; comprobar que no reaparezcan writers legacy. Sólo entonces activar invitaciones/aceptación y permisos para cuentas reales.

Ninguno de estos pasos se ejecuta por detectar tablas o modificar un flag del browser.

## Verificación y evidencia

- `php modules/identity/tests/OperatorPanelReadinessTest.php`: base independiente literal `mxmed_gate4d_preview_opint04_contract`, rechaza sobrescribirla si existe y la elimina en finally. Comprueba esquema ausente, inventario/historial, proyección sin credenciales/notas ni otro perfil, exceso, presencia de schema sin activación, etiqueta owner sin autoridad y lectura fallida sin falso vacío.
- `python3 scripts/op-int04-contract-qa.py`: preservación del shell normal, Agenda, guard de sesión, clínica/Recetas y todos los demás dispatchers; único panel con mutaciones deshabilitadas en el HTML normal. La entrada histórica `op-ui03-contract-qa.py` delega al contrato actualizado porque el formulario normal legacy se retira intencionalmente.
- `python3 scripts/op-int04-normal-safari-qa.py`: usa una sesión Safari normal-local ya autenticada y crea una pestaña de revisión, sin leer/copiar cookies ni credenciales. Carga la URL real 8443 en marcos temporales del tamaño solicitado; la pantalla física limita la ventana a 810 px de contenido. Registra el tamaño real de cada frame, readonly/legacy, navegación, Agenda, formulario abierto/cerrado sin guardar e historial. No es aceptación física del Director. Las capturas de ventana, si el sistema permite obtenerlas, sólo muestran la porción visible del marco; nunca se presentan como una captura completa del viewport.
- Mutaciones canónicas: sólo UI03 desechable, con los runners OP01/OP02 existentes. No se resetean OP02 persistente ni QA05.

Evidencia privada normal-local: `~/Library/Application Support/MXMED/op02-admin/evidence/op-int04/`. Evidencia sintética canónica: `~/Library/Application Support/MXMED/opui03-rehearsal/evidence/`. Los hashes de preflight/final abarcan todas las tablas normal-local/QA05 y las 49 tablas OP02 persistentes. No se publican credenciales ni capturas con datos reales en Git.

## Resultados normal-local y límites de evidencia

`normal-safari.json` registra **PASS de DOM y flujos de lectura autenticados** en frames reales de Safari a 1440×900, 1366×768 y 820×1180, con la propia URL 8443. Verifica un sidebar/panel/calendario/controlador, doctor 1/collaborator, schema/service/can_manage falsos, cinco grants e inputs deshabilitados, ausencia de credenciales legacy, cupo no disponible, inventario real, nueve eventos de historial, navegación ajena al panel, slots y apertura/cierre del formulario original sin guardar. Los caches previos duplican algunas referencias de servidor; se muestran separados y con advertencia, sin fusionarlos.

Las capturas de ventana fallaron con `could not create image from window`. Por ello la **comparación visual autenticada con capturas queda NOT_RUN**, al igual que la aceptación física del Director. No se presenta una captura OP02 como evidencia visual normal-local ni se identifica la prueba DOM como revisión física. El script restaura el tamaño de ventana y la pestaña de revisión al final.

Durante la regresión canónica se observaron rechazos de transporte WebKit en lecturas simultáneas de Agenda. El adaptador ahora devuelve un fallo explícito `ok=false`, status 0 y mensaje de conexión; conserva `AbortError` del guard de generación. No retorna un vacío exitoso, no reintenta una escritura y no altera denegaciones HTTP. La prueba de sesión incorpora un fallo de transporte inducido y exige no-success, junto a los cinco casos reales de CSRF/rotación/logout.

La prueba de creación usa la búsqueda explícita con **Enter** del formulario original. En una corrida WebKit, Filtrar con una sola palabra terminó mostrando «Presiona Enter o Filtrar» sin abrir el selector, por una intermitencia con la búsqueda debounced. La fuente Agenda permanece idéntica al baseline; esta corrección no declara resuelto ese comportamiento de búsqueda. Se conserva como deuda de Agenda, separado de autorización/Integración de Operadores.

El checksum versionado de `api/agenda/index.php` en `Gate8DAppointmentLifecycleIdempotencyTest.php` se actualiza exclusivamente por el cierre autorizado del case Operadores. El contrato OP-INT04 compara byte por byte todo el dispatcher antes/después de ese case y comprueba la ausencia de llamadas a los writers legacy. Se mantienen el pin exacto del archivo actualizado, todos los demás hashes y todas las pruebas de estado, versión, concurrencia e idempotencia; no se agrega un modo que omita esos controles.

La corrida final registra **6 PASS de browser canónico** (Chromium/WebKit en los tres tamaños), **6 PASS de sesión/transporte**, **48 PASS HTTP y 1020 denegaciones reales 403**, más los ocho casos de readiness/proyección, preservación de fuente/DOM, regresión privada de sesión y pruebas de OTP público sin entrega real. Los demás contratos Identity/GOV03A, cuota/concurrencia, audit/rollback, clínica y Agenda pasan con los runners existentes. La fuente de Agenda sigue intacta.

`preservation.json` confirma igualdad de los hashes de **inicio/final de OP-INT04** para todas las tablas normal-local/QA05 y las 49 tablas OP02. Los tres procesos OP02 siguen activos. El `NORMAL_LOCAL_BASELINE_MATCH=false` de `op02-env.py status` se refiere a su baseline histórico anterior, que ya difería del snapshot al inicio de OP-INT04; la evidencia registra esa distinción. No se reescribe esa referencia ni se atribuye al cambio actual. No hubo migración normal-local, mutación de membresías reales ni entrega de invitaciones reales.

## Enmienda OP-UX06 — lifecycle y presentación de identidad

Fuente de esta fase: `ec96537853185c69ffef2ca7b74a993a50bb8b10` (OP-VIS05), rama `ux/consultation-step2-vitals-r1`. Estado de entrega **PARTIAL**: presentación y lecturas seguras, sin activación normal-local ni persistencia nueva de alias/foto/posición. Sustituye las descripciones de presentación anteriores, no sus contratos de autoridad. OP-VIS05 ya separó la caché incierta en un desplegable; no hay reconciliación automática por ID coincidente, alias o correo.

### Qué se puede revisar ahora

La misma URL 8443, sidebar, dos columnas, tarjetas, acordeones, modales de Agenda y cinco checkboxes permanecen. Al expandir una tarjeta se consultan identidad de presentación, estado, fechas disponibles, permisos e historial por referencia estable. Normal-local usa sus registros históricos reales, sin ejemplos sintéticos. No confunde **SERVICE_NOT_ACTIVATED** con un equipo vacío; mantiene inputs y envío deshabilitados, contadores no disponibles y la membresía real collaborator intacta.

Con servicio canónico y owner verificados, **EMPTY_TEAM** requiere una respuesta válida con cero integrantes ocupando plaza y cero reservas. Muestra 0/2, dos plazas libres, explicación de delegación y CTA «Invitar operador». Una lectura fallida oculta ese estado y marca contadores no disponibles. Los archivados sólo proceden de registros reales del servicio. El resumen de plazas suma ocupadas y reservadas; la reserva pendiente se muestra además por separado, sin presentarla como una persona aceptada.

| Estado | Lectura y acciones actuales del owner |
| --- | --- |
| Pendiente | Contacto enmascarado, permisos ofrecidos exactos, fecha/expiración servidor; cambiar oferta/reexpedir o cancelar. No grants activos. |
| Vencida/cancelada/rechazada | Sin acciones de grants ni plaza reservada. El overview actual sólo lista pendientes; las cerradas se consultan en auditoría. El renderer admite un estado terminal explícito, no lo deduce del reloj del navegador. |
| Activo | Concedidos frente a efectivos, editar grants, pausar y revocar. |
| Suspendido | Acceso pausado, plaza retenida; editar/revisar grants, reactivar y revocar; el servidor revalida cada comando. |
| Revocado | Archivo histórico de sólo lectura, sin restaurar autoridad ni ocupar plaza; historial disponible. |
| Histórico legacy | Alias/estado anteriores e historial doctor-scoped; sin interpretar esos datos como membresía o permiso Identity. |

El reader `ProfileOperatorService::overview` añade únicamente columnas existentes: fecha de invitación/expiración/estado pendiente y, para miembros, estado actual de cuenta, booleano de contacto verificado y fechas de invitación/aceptación del registro que referencia esa membresía y ese perfil. No modifica autenticación, writers, locks ni cuotas. Una membresía activa no sustituye el booleano de verificación. No se inventa último acceso: ni la fecha de membresía ni una lectura de sesión demuestran actividad confiable en ese perfil.

El historial se filtra por `membership_id`, `invitation_id` o ID histórico, nunca por alias; conserva la referencia de actor original. La vista está limitada al historial que devuelve el reader, no promete un archivo completo. Las iniciales toleran Unicode y conservan el fallback sin cargar URLs de fotos arbitrarias.

### Alias de una palabra: política y cambio mínimo propuesto, NO instalado

Hallazgo: `ProfileOperatorService` genera hoy `alias` a partir de la parte local del correo. Es una referencia de cuenta, **no un alias de equipo guardado**. Las tarjetas lo identifican como alias de equipo pendiente. El formulario permite revisar, dentro de su acordeón original, un campo de alias siempre deshabilitado. No lo envía al writer ni confirma guardado.

El legacy `OperatorsController`/`OperatorsRepository` elimina acentos/espacios/caracteres, convierte a mayúsculas y acepta `[A-Z0-9-]{3,15}`; su unicidad excluye archivados. Esa normalización no sirve para Mary/Lupita/Tere ni para preservar capitalización y Unicode. No se reutilizará como identificador de cuenta o acceso.

Contrato propuesto para el writer canónico, sujeto a implementación y ensayo separados:

- Alias opcional al invitar; si falta, el destinatario autenticado lo propone al aceptar, en el mismo perfil y oferta. El owner puede renombrarlo después. Sin alias se mantiene «pendiente de definir», no se adopta el correo automáticamente. Una propuesta de invitado queda sujeta a esta misma política del perfil, sin editar otros campos.
- UTF-8 válido, trim de bordes, NFC para display; una palabra de letras Unicode y marcas combinantes, iniciada por letra, máximo 24 caracteres Unicode tras NFC y 96 bytes UTF-8. Rechazar espacios internos, controles/invisibles, dígitos, signos, guiones, emoji y entradas que normalicen a vacío. Conservar exactamente la capitalización visible NFC. No transformar silenciosamente una entrada inválida.
- Clave única: NFC(case-fold Unicode del display NFC), cotejo binario determinista. Mary/MARY colisionan; José y Jose son distintos, como Ñ/N. Requiere PHP intl/mbstring, misma versión de normalizador en todos los comandos; no fallback ASCII. Ausencia de alias usa NULL, no cadena vacía.
- Unicidad exacta de perfil para reservas pendientes vigentes y relaciones activas/suspendidas. Vencimiento/cancelación/revocación liberan la reserva; la historia persiste. Colisiones concurrentes producen conflicto 409 sin cambios parciales. Cambio de owner, reexpedición y aceptación conservan la transacción y el lock canónicos. Un alias nunca selecciona persona, scope, grant, actor ni sesión.

Persistencia mínima propuesta: **una extensión de presentación** `auth_profile_operator_presentations`, no un store de membresías: `presentation_id`, `profile_doctor_id`, `invitation_id` único, `membership_id` único nullable hasta aceptación, `alias_display` nullable, `alias_key` nullable VARBINARY(192), `seat_position` (1/2), `revision`, `created_at`, `updated_at`, `released_at` nullable. FKs a las entidades existentes; la relación de perfil/owner/cuenta se valida bajo el lock existente. Claves generadas sólo mientras `released_at IS NULL` permiten UNIQUE(profile, alias_key vigente) y UNIQUE(profile, seat_position vigente). La fuente del estado de acceso y de cuota sigue siendo exclusivamente Identity/OP01. La posición se reserva bajo el mismo lock y se conserva al aceptar/suspender; no depende del orden de filas. Al reemplazar persona, crear otra presentación/asignación, conservar la antigua. No copiar cuentas, credenciales, grants, role o flags de autoridad a esta extensión.

Extender los comandos existentes, sin endpoint/writer paralelo: invite/accept reciben alias opcional; renombrar requiere owner actual, `membership_id`, `expected_presentation_revision`, idempotencia, CSRF/origen/sesión y auditoría crítica en la misma transacción. El invitado no puede renombrar después salvo una política futura explícita. El reader añade `presentation_version`, alias guardado y posición sólo al disponer de ese contrato; el cliente no calcula posiciones por orden, email, localStorage ni IDs legacy. Ensayar: NFC equivalente, ñ/acentos, casefold, 409 concurrente, reserva/expiración, rename idempotente, rollback de audit y no alteración de eventos antiguos. **No se añadió DDL ni writer de alias en OP-UX06.**

La propuesta de alias del owner forma parte de la versión/hash de oferta; cambiarla exige reexpedir e invalidar el enlace anterior. Reexpedir cierra la presentación pendiente previa y crea la nueva vinculada a la nueva invitación, conservando la posición bajo el mismo lock; aceptar vincula esa presentación a la única membresía creada por el servicio. Cancelar, vencer o revocar marca `released_at` en la misma transacción canónica antes de reutilizar alias/posición. Suspender no lo marca. No usar reloj dentro de una clave generada ni liberar reservas por un temporizador del cliente. El rename conserva la misma presentación y membresía, aumenta revisión y no reescribe snapshots ni eventos previos.

### Fotografía privada, almacenamiento y captura móvil: adaptación exacta pendiente

Reutilizables: `PrivateMediaStoragePort`, `LocalPersistentPrivateMediaStorage`, decodificación/orientación/re-encode de `GdPublicLogoProcessor` y límites de `DoctorProfilePhotoService`. Este último guarda en PUBLIC y `MediaAssetsRepository::insertReady` fuerza esa clasificación. `GallerySessionScope` rechaza operadores. La whitelist del storage privado sólo admite firmas/media-review. Esas rutas públicas/de revisión médica no acreditan autoridad para avatar de equipo y no se llaman desde este panel.

Plan mínimo separado:

1. Adjuntar a la presentación un `avatar_media_id` nullable y revisión. Guardar versiones inmutables de avatar en una extensión privada de media con ID, perfil, presentación/membresía, uploader canónico, propósito `OPERATOR_AVATAR`, clasificación PRIVATE, storage key, WebP, dimensiones/bytes/hash, fecha y estado ready/superseded/removed. No usar `public_url`; una FK al registro privado no otorga lectura. Reutilizar el port de storage; ampliar su allowlist a `private/operator-avatars/<hash-profile-membership>/<uuid>.webp`, con las mismas defensas contra traversal/symlinks y almacenamiento fuera de docroot.
2. Adaptar el procesador a una única variante avatar de hasta 256×256, objetivo 30–60 KiB, techo 100 KiB. Entrada JPG/PNG/WebP hasta 5 MiB, 8192 px/lado y 25 MP; finfo, extensión coherente, dimensiones y decodificación real; rechazar SVG, animaciones, polyglots y formatos no soportados. Aplicar orientación antes de recodificar píxeles; retirar EXIF/IPTC/XMP/GPS. Si faltan decoders o validación de imagen animada, denegar; no guardar original. Procesar en temporal privado y limpiar fallos.
3. Adaptar el servicio/reader privado existente, sin writer de foto pública: owner actual de ese perfil o la propia cuenta autenticada de la membresía activa, según permiso explícito de avatar. Un collaborator ajeno, staff no autorizado o etiqueta de rol no basta. GET privado por ID revalida perfil/membresía y lector permitido (owner o propio sujeto), responde `Cache-Control: private, no-store`, `nosniff`, MIME fijo, sin CORS abierto/URL externa; cliente sólo recibe esta referencia escopada. No ampliar la allowlist HTTP de operadores antes de contar con ese guard.
4. Reemplazo/remoción: lock canónico, revisión optimista/idempotencia, nuevo objeto inmutable y cambio de puntero con audit en transacción; compensar objeto nuevo si falla SQL. Retirar anterior después del commit mediante cola/reintento; borrar puntero no implica borrar evidencia audit. Revocación invalida lecturas/escrituras y mantiene sólo retención privada autorizada. Una foto ausente/404/403 vuelve a iniciales, sin revelar URL física.
5. Captura futura mediante QR: reutilizar mecánica de handoff, no su propósito/autoridad médica. Nueva purpose ligada a perfil, presentación/membresía, iniciador, sesión y revisión; token aleatorio 256 bits, sólo hash, un uso, TTL máximo 5 min, invalidación al reexpedir/revocar/cambiar owner o revisión. Tanto crear como reclamar/consumir exigen Identity autenticado y scope de avatar; el teléfono usa su propia sesión y validación CSRF/origin, nunca cookies del desktop ni token QR como autoridad suficiente. Consumir token, verificar scope y publicar puntero de media de forma atómica/auditada; rechazar replay/cross-profile. GET del QR no escribe ni expone imagen o cuenta. URL base HTTPS confiable accesible desde el teléfono; localhost del Mac no es accesible desde otro dispositivo. Requiere aprobación de despliegue/TLS/red separada, no un túnel automático.

Hoy campo de archivo, dropzone y cámara son explícitamente inactivos. El drop se cancela para impedir navegación accidental; no hay upload, QR generado, almacenamiento local de fotos ni falsas confirmaciones. Botones futuros no se habilitan al terminar otro comando del equipo. No se adapta ni escribe media real en esta fase.

Nombres propuestos para preparar la migración posterior: `operator_avatar_assets` con los campos privados/versionados del punto 1 y FK desde `auth_profile_operator_presentations.avatar_media_id`; `auth_operator_avatar_captures` con `capture_id`, hash único, purpose, perfil/presentación/membresía, iniciador y hash de sesión, revisión esperada, expiración, estado y fechas de consumo/cancelación. No añadir esos propósitos al reader público ni usar la tabla de firma del médico. Extensiones HTTP propuestas bajo el dispatcher canónico existente: GET privado `/avatar/{membership_id}`; POST multipart y DELETE en esa misma ruta con revisión/idempotencia; POST `/avatar-captures` para crear y POST `/avatar-captures/{id}/claim` y `/complete` autenticados. El servicio OP01 deriva autoridad y perfil antes de delegar al procesador/storage; el wrapper no acepta actor/owner ni scope como autoridad desde el body. Estos paths **no se implementan ni se habilitan en esta entrega**. Requieren guard, tests y autorización de activación separados.

### Colores y procedencia histórica de Agenda

Se reutilizan exactamente `--agenda-origin-operator-01: #FF007F` y `--agenda-origin-operator-02: #FF66CC`, y las clases existentes `mx-ag-origin-dot--operator-01/02`. El desplegable de identificación muestra el catálogo, **pendiente de asignación**. No inventa un selector ni asigna colores a filas legacy por su alias.

Hallazgos verificables:

- En `mxmed.agenda_appointments` existen `created_by_id`, `created_by_role`, `channel_origin`; faltan posición, alias y snapshot de membresía creadora. El repositorio de lectura tolera columnas opcionales `operator_slot`, `operator_alias`, `origin_visual_key`, pero no las crea.
- `OperatorQaHttp` deriva account/membership del actor canónico y el writer guarda cuenta creadora; CanonicalAudit conserva membresía/grant. No persiste posición/alias como snapshot de la cita. Un evento posterior de reprogramación/cancelación tiene su propio actor, distinto del creador.
- El renderer existente prioriza `origin_visual_key`/posición del evento; su fallback consulta mapas actuales del navegador y puede inferir posición por orden si hay dos filas. La leyenda y detalle dicen Operador 01/02, sin alias. Conectar allí el roster actual sin más sería inseguro para el histórico. No se hace en OP-UX06.

Contrato aditivo futuro: en la **creación canónica** de cita, resolver en servidor y guardar una proyección inmutable versión 1 con `creator_account_id`, `creator_membership_id`, `creator_presentation_id`/asignación, `creator_alias_snapshot` nullable, `creator_operator_position_snapshot` 1/2 nullable, `creator_origin_visual_key_snapshot`, `created_at_utc`. Reutilizar `created_by_id/role/channel_origin` donde ya representan el hecho, sin duplicar autoridades; añadir sólo los campos/snapshot faltantes. El writer no acepta estos valores del body. Reschedule/cancel nunca reescriben creador; sus actores se registran en eventos aparte.

Reader propietario de la cita valida el perfil y entrega el snapshot mínimo con acceso Agenda. Detalle/Consulta y etiqueta de evento deben usar **exclusivamente ese snapshot**, nunca el roster actual: «Operador Mary» se conserva aunque Tere ocupe después la misma posición. Una cita antigua sin snapshot mantiene sus referencias y leyenda genérica «Operador (histórico sin alias confirmado)», sin backfill por nombre/email/posición actual. La leyenda de operadores actuales sí puede mostrar «Operador Mary»/«Operador Tere» sólo con el reader versionado de asignaciones vigentes; no determina etiquetas de eventos pasados. Orden estable 1/2 y variables existentes. Ningún dato legacy se reescribe en este cambio.

### Secuencia posterior y gates

1. Autorizar aparte ensayo/migración de presentación/posiciones y activación normal-local OP01, owner gobernado y entrega autorizada. No promover collaborator por conveniencia de QA.
2. Implementar alias/reader/puestos en transacciones existentes y probar colisiones, reexpedición, aceptación, cambio owner y concurrencia. Probar que alias no autoriza ningún endpoint.
3. Ensayar contrato aditivo de snapshot de origen y adaptar leyenda/detalles sin sustitución histórica ni inferencia de asignación actual.
4. Implementar media privada y luego QR autenticado; validar tamaño/MIME, metadata stripping, lectura cruzada, remoción, revisión concurrente, replay y expiración antes de habilitar los controles.
5. Activar sólo después de las pruebas de aislamiento, autoridad, datos y revisión Safari física. Este documento especifica trabajo posterior, no autoriza ejecutar las migraciones ni activar los writers.

Las categorías futuras (perfil público, medios, promociones, facturación, renovación y captura preparatoria limitada) se presentan como texto inactivo dentro del acordeón. No existen checkboxes adicionales, bundle administrador implícito, wildcard Agenda ni acceso clínico nuevo.

### Evidencia y límites de QA OP-UX06

- `python3 scripts/op-ux06-presentation-qa.py`: 68 comprobaciones de presentación, Chromium y WebKit a 1440×900, 1366×768 y 820×1180, usando el partial/controlador y CSS originales en documentos de prueba desechables, sin nueva página del producto, listener, cuentas o base de datos. Cubre vacío con CTA visible/foco en correo, uno/dos, pendiente/vencida, activo/suspendido/revocado, iniciales Unicode, foto ausente/URL no confiable sin cargar, catálogo de colores, cinco permisos y fallo de comando sin habilitar controles futuros. Referencias con alias coincidente permanecen distintas; rename y colisión **de persistencia** siguen deshabilitados/no implementados. Reemplazar una tarjeta no modifica el objeto de cita/historial original; no equivale a una prueba del futuro writer de snapshots.
- `php modules/identity/tests/OperatorFoundationTest.php`: los 38 contratos de autoridad/concurrencia existentes y las comprobaciones nuevas de proyección. Datos sólo en la base temporal propia, eliminada al terminar. Verifica fechas persistidas, estado/contacto actual, lector owner-only, cuenta deshabilitada sin permisos mostrados como efectivos y reserva de pendientes separada de aceptados.
- Servicio canónico UI03 **existente**, sin reset: seis renderizados reales Chromium/WebKit en los tres tamaños y rechazo de envío interceptado antes de llegar al servidor. Reader enriquecido comprobado por HTTPS. Matriz de denegación OP02 reutilizada en UI03: 880 respuestas 403, sin elevar roles ni abrir acceso clínico. No se ejecuta el runner destructivo contra OP02/QA05.
- Safari normal-local con sesión ya existente, sin leer/copiar cookies: Operadores/historial, shell, inventario real, referencias locales plegables, cinco permisos y controles deshabilitados. Marcos solicitados 1440×900, 1366×768, 820×1180; medidas reales 1440×900, **1365×768**, 820×1180. El marco intermedio tiene un redondeo de 1 px del host Safari; no se presenta como medición exacta de 1366. Los dos motores automatizados sí verifican los tres tamaños exactos.
- El runner adicional de Agenda dentro del marco Safari pasó una disposición y también tuvo timeouts intermitentes al dibujar horarios. La pestaña original conserva horarios, sin error visible. No se declara PASS de ese flujo adicional en los tres tamaños. Agenda/partial/CSS/adapter/dispatcher conservan sus bytes y pasa `Gate8DAppointmentLifecycleIdempotencyTest.php --semantic-regression`. No se cambió Agenda para resolver un problema del marco de prueba.
- CLIN-AUTH02A `--security-regression`, sesión privada, readiness y contratos de preservación pasan. La restricción ya existente `AUTH02A_15=BLOCKED_BY_AUTHORITY_CONTRACT` permanece; no es una autorización clínica nueva. Capturas normales y revisión física del Director: **NOT_RUN**. Las capturas sintéticas no acreditan aceptación visual normal-local.
- Hashes de todas las tablas al inicio/final comprueban normal-local + QA05 y las 49 tablas OP02 sin cambios. Ninguna migración real, invitación real, membresía, grant, foto, procedencia de cita o fixture persistente se modifica. Evidencia privada: `~/Library/Application Support/MXMED/op02-admin/evidence/op-ux06/`.

Quedan pendientes de autorización e implementación la persistencia de alias/posiciones, media privada y QR autenticado, snapshot inmutable y binding de alias en Agenda, además del corte de activación normal-local gobernado. **OP-UX06 no declara completo ese lifecycle funcional ni activa administración para collaborator.**


## OP-IMP07 — Alias canónico y procedencia inmutable, implementación aislada

Baseline `2add5afa8f652c0c1d00662642e9d33cd9dd8492`, 2026-10-15. Se extiende `ProfileOperatorService`, con las mismas sesiones Identity, CSRF/origin, lock de perfil, cuota de dos personas, membresías, revisiones de grants, idempotencia y auditoría. No hay otro writer de operadores ni motor de permisos. La normalización es un valor de presentación, nunca un identificador de autorización.

El titular puede proponer un alias opcional al invitar/reexpedir. Integra la versión/hash de oferta y no puede cambiarse durante aceptación; si no existe, el invitado puede proponerlo en su respuesta. Renombrar exige titular vigente, miembro activo de ese perfil, origen de nombramiento válido y revisión optimista de presentación. El mismo command ID/payload repite el recibo, y cualquier fallo de auditoría revierte alias/revisión. Los eventos anteriores conservan su actor y referencias reales.

`OperatorAlias` aplica UTF-8 válido, trim Unicode en extremos y NFC; una palabra de letras/marcas, hasta 24 codepoints NFC y 96 bytes. La presentación conserva mayúsculas y acentos. La clave binaria usa casefold Unicode y NFC (hasta 192 bytes), separada de la collation MySQL: `José` colisiona con `JOSÉ` o su forma descompuesta, pero no con `Jose`. Se rechazan espacios internos, controles/ZWJ, cifras y símbolos. La unicidad se limita a presentaciones no liberadas de ese perfil, con índice único físico y el lock existente. No se confirman alias a partir de prefijos de correo.

### Relaciones y recuperación preparadas

- `modules/identity/db/migrations/2026_10_15_04_operator_presentations.sql`: relación de presentación vinculada por FK compuesta al perfil, invitación y membresía existentes. Reserva posición 1/2 mediante las mismas transacciones de cuota, sin contador adicional. Una invitación reserva; aceptar conserva; suspender conserva; cancelar/rechazar/vencer/revocar libera. Reexpedir crea una nueva asignación con preferencia por la posición previa. El cambio gobernado de owner cancela ofertas/revisa grants y libera la asignación si su persona pasa a ser titular o su membresía fue revocada. La posición/identidad de asignación no se pueden reescribir ni reactivar después de liberarse.
- `modules/agenda/db/migrations/2026_10_15_12_appointment_creator_snapshots.sql`: relación uno-a-uno versión 1 con cita/perfil, cuenta/membresía y asignación creadoras, alias/posición/origen y fecha UTC del servidor. FKs y trigger verifican la vinculación física; otros triggers rechazan UPDATE/DELETE y cambios al creador original. La cita y su snapshot se guardan en la transacción existente antes de sus eventos. Reprogramar/cancelar conserva el snapshot; el actor de esas acciones se registra por separado.
- Cada forward tiene un archivo `rollback_*` emparejado. Ambos rechazan cualquier base ajena a `mxmed_op01_<10hex>` o `mxmed_gate4d_preview_opimp07` **antes de DDL persistente**. Ejecutar con el runner PDO existente (`PDO::exec` del archivo completo); sus cuerpos de trigger no llevan comandos DELIMITER del cliente mysql. Forward/replay y recuperación vacía comprobados. Recuperación exige tabla vacía; si hay datos, se rechaza sin borrar el historial. Recuperar primero snapshots y luego presentaciones. Datos poblados requieren desactivar consumidores y un plan explícito de conservación, no vaciar tablas para forzar rollback.

Los tipos físicos son `VARCHAR(64) / utf8mb4_unicode_ci` y DATETIME(6), compatibles con las claves reales inspeccionadas. Los índices compuestos agregados evitan depender de claves foráneas no únicas. Las nuevas migraciones **no están autorizadas para mxmed ni QA05/OP02/UI03 persistentes**; sus guards lo impiden. Una activación futura necesita migraciones de despliegue autorizadas y rehearsal/backup propios, además de los gates de owner, entrega y composición normal descritos arriba.

### Integración de los componentes originales

El formulario, tarjetas/acordeones, iniciales, historial, dos columnas y sidebar originales siguen siendo únicos. El mismo formulario invita/reexpide o renombra, sin otro modal de negocio. En edición de alias, los cinco grants se muestran sin permitir cambios accidentales. El input sólo se habilita cuando el servidor confirma servicio, owner y esquema de presentación. La aceptación reutiliza su modal existente; el alias del titular se muestra deshabilitado, y el invitado sólo propone uno cuando falta. Guardar sólo confirma después de la respuesta canónica.

`agenda-workspace.js` cambia exclusivamente la presentación del origen y el transporte de su DTO. El catálogo actual usa el roster de asignaciones validado en servidor: posición 1 `#FF007F`, posición 2 `#FF66CC`, mismas variables y clases existentes. La etiqueta del detalle y su proyección en Consulta usan el snapshot de la cita, nunca sustituyen el alias por quien ocupa hoy la plaza. Una cita creada por Mary sigue mostrando «Operador Mary» después de renombrarla, reemplazarla por Tere, reprogramarla o cancelarla. El reader mínimo no expone cuenta, membresía, email, avatar ni datos clínicos para pintar el origen.

No hay backfill ni escritura sobre citas anteriores. Operadores históricos sin snapshot dicen «Operador (histórico sin alias confirmado)» y no infieren posición por el roster actual. Una procedencia antigua indeterminada dice «Origen histórico no confirmado»; otros canales conocidos conservan sus etiquetas. El calendario, disponibilidad, navegación y reglas de negocio quedan preservados. `op-imp07-contract-qa.py` compara con el baseline excluyendo sólo los resolvers de origen y dos campos de transporte; el contrato OP-INT04 delega esa comprobación y mantiene sus restantes garantías byte a byte.

### Composición desechable y reproducción

Se amplía el runner OP02 existente con `MXMED_OP_IMP07_REHEARSAL=1`: base `mxmed_gate4d_preview_opimp07`, usuario DB propio, origen loopback `https://127.0.0.1:8447`, backend 8096, Valkey 6389 y cookie `__Host-mxmed_opimp07_session`. Son los mismos private-app/partials/controladores y Agenda originales, con el adaptador acotado ya existente. No se copian datos normales; se reutiliza el seeder sintético de OP02 y sus cinco cuentas example.invalid. No se resetea ningún entorno existente; el comando reset rechaza este modo.

Secuencia sobre recursos nuevos y propios:

```sh
MXMED_OP_IMP07_REHEARSAL=1 python3 scripts/op02-env.py prepare
MXMED_OP_IMP07_REHEARSAL=1 python3 scripts/op02-env.py start
php modules/identity/tests/OperatorFoundationTest.php
python3 scripts/op-imp07-http-qa.py
python3 scripts/op-imp07-browser-qa.py
python3 scripts/op-imp07-browser-qa.py --offers-only
```

Los runners HTTP focalizados exigen el preestado indicado y no son resets. El flujo focalizado deja B/C activos; antes de la matriz OP02 se revocan esas membresías sintéticas mediante el comando canónico, manteniendo sus filas/historial, y se cancelan ofertas pendientes mediante el mismo servicio. A continuación `MXMED_OP_IMP07_REHEARSAL=1 python3 scripts/op02-http-qa.py`. La evidencia debe copiarse fuera del soporte desechable antes de `MXMED_OP_IMP07_REHEARSAL=1 python3 scripts/op02-env.py destroy --confirm mxmed_gate4d_preview_opimp07`.

### Evidencia y límites de esta entrega

- OP01 ampliado: estados de plazas, TTL, oferta/reexpedición, Unicode físico, colisiones concurrentes, revisión/idempotencia, aislamiento, auditoría con rollback y recuperación protegida; conserva los 38 contratos originales y nueve regresiones Identity/GOV03A.
- HTTP focalizado: logins Identity genuinos sobre Valkey separado, alias owner/invitado, dos posiciones, cuota, forjado denegado, snapshot físico inmutable, actor separado de reprogramación/cancelación, sustitución de persona y DTO histórico mínimo.
- Chromium y WebKit sobre la aplicación original: 1440×900, 1366×768, 820×1180. Renombrar/rechazar colisión, cinco grants, leyenda actual Tere/Lupita, detalle real histórico Mary, colores exactos, sin overflow; foto y QR deshabilitados. La apertura de detalle espera la hidratación asíncrona del calendario, sin modificar su lógica para la prueba.
- Invitación del titular y propuesta del invitado ejercitadas en los formularios y modal originales, con confirmación del servidor y sesiones propias de las cuentas sintéticas. Revisión física del Director en Safari: **NOT_RUN**.
- Matriz HTTP OP02 completa: **48/48 PASS**, con **1020/1020 denegaciones 403** en las rutas/grants probados. Incluye OP01, concurrencia física, cambio gobernado de titular, CLIN-AUTH02A, composición de autoridad, idempotencia de Agenda y disponibilidad pública. Permanece la restricción clínica preexistente `AUTH02A_15=BLOCKED_BY_AUTHORITY_CONTRACT`; no se abre ni modifica autoridad clínica.
- Preservación: checksums coincidentes para las **226 tablas de normal-local/QA05**, **49 de OP02 persistente** y **49 de UI03 existente**. El postcheck desechable encontró tres FKs por relación, cinco triggers OP07, cero huérfanos y cero colisiones de posición/alias. Repetir una creación con la misma clave después de renombrar conserva la cita y snapshot originales.
- Safari, pestaña temporal nueva de la URL normal real con la sesión ya autenticada: un solo panel/calendario, collaborator, servicio/presentación/can_manage falsos, guardar y alias deshabilitados. Sólo lectura; la pestaña temporal se cerró y no se copió la sesión. Es evidencia de readiness, no aceptación física del Director ni prueba de edición normal-local.
- Contratos de sesión privada/readiness y preservación de fuente: PASS. El runner de presentación OP-UX06 pasó **74 casos** y adapta sus fixtures al DTO `personal_alias` explícito y comprueba que una referencia no confirmada sigue siendo genérica; este stub mantiene el writer deshabilitado y no sustituye las pruebas reales de persistencia. El selector de fixture UI03 usa account_id → membership_id del overview autorizado, no prefijos de correo/alias; se verificó ese helper en OP-IMP07 sin ejecutar el runner UI03 que resetea su base.
- Se conservan evidencias privadas en `~/Library/Application Support/MXMED/op02-admin/evidence/op-imp07/`; nunca se publican claves, tokens, correos reales ni credenciales.

**Normal-local continúa sin alias editable ni snapshots nuevos:** sus tablas OP01/presentación/origen no están activadas y su miembro real sigue collaborator, sin elevación. No se aplicó DDL ni cambios de datos a normal-local, QA05, OP02 persistente o UI03 existente. Las fotos privadas, dropzone y captura QR continúan pendientes del backend/media protegido y sus pruebas; no se habilitan por implementar alias.
