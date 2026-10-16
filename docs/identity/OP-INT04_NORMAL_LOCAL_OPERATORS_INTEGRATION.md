# OP-INT04 — Operadores dentro de la aplicación normal-local

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
