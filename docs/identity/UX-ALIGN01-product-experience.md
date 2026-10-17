# UX-ALIGN01 — experiencia original y contratos pendientes

2026-10-16. Worktree `mxmed-step2-vitals-r1`, rama `ux/consultation-step2-vitals-r1`, inicio local/upstream/remoto `e927dcfa86e3bf1fb7cf4e972b4155051d2e51c0`, limpio. Se conserva la aplicación original `https://localhost:8443/private-app.php`, el runtime y las autoridades existentes. No se dispone de los mockups históricos en filesystem y no se inventan rutas para ellos; se aplican las descripciones funcionales del Director con el sistema visual actual.

## A — identidad del encabezado

`PrivateApplicationAccessResolver` añade exclusivamente `profiles_doctors.professional_designation` al SELECT del perfil resuelto desde la membresía canónica y al DTO `professional`. La selección de membresía, `professional.role`, scope, capabilities y sesión conservan su autoridad anterior. No se cambia ningún escritor, rol, grant, propietario o suscripción.

El renderer original `assets/js/core/header-account.js` muestra esa denominación exacta (trim de espacios), con fallback “Perfil profesional”. No usa el código de membresía, especialidad inferida, género, localStorage ni un control de edición como título. Reacciona al evento canónico `mxmed:session-authenticated`; los cambios de `fs-esp` dejan de sobreescribir la identidad. Nombre, geometría, colores, avatar y acciones conservan sus componentes.

El contexto acotado de Operadores se reconoce por `workspace=agenda_operators` o el atributo canónico del shell, y conserva su renderer personal de alias/identidad. La denominación del médico no sustituye al operador ni se transforma en permiso clínico. En lectura normal-local el perfil 1 conserva la denominación almacenada “Endocrinóloga”; no es un literal global de la UI.

La QA distingue el resolver/sesión nativos con datos sintéticos, la presentación de componentes originales en Chromium/WebKit y la observación DOM read-only de una pestaña Safari ya autenticada. Ninguno equivale a aprobación física del Director. Una pestaña abierta antes del cambio conserva sus scripts/DTO antiguos hasta una actualización normal; no se alteran sus cookies, credenciales o sesión para fabricar una prueba.

Resultado A: 42 comprobaciones del resolver nativo en SQLite en memoria y 48 casos de componentes originales en Chromium/WebKit (1440×900, 1366×768, 820×1180). Cubren denominaciones específicas/neutras/vacías/null, otra membresía/perfil, denegaciones de anónimo/revocado/ambiguo/deshabilitado/operador, ausencia de escrituras y owner, refresh canónico y conservación del alias de operador. La prueba de presentación no se declara E2E de toda la aplicación privada. Se conservan fuentes/manifest y capturas sintéticas en `~/Library/Application Support/MXMED/ux-align01/header/`.

La pestaña Safari normal-local ya autenticada se observó sin reload, navegación o acceso a cookies. El DOM antiguo mostraba “collaborator”. Un GET nontouch al `current-session` existente devuelve la denominación nueva del servidor y conserva rol `collaborator`; no se fuerza la carga de scripts nuevos en esa pestaña ni se cambia su sesión. Los tests de sesión privada y GOV-SIM02 también pasan.

## B — dos entradas públicas, una incorporación profesional

Las rutas operativas descubiertas son `/profiles/doctor.php?doctor_id=…` y `/profiles/listing.php` (filtros de estado/ciudad/especialidad). El router SEO por slug sigue sin implementar; no se sustituyen sus URLs. El footer de ambos templates ofrece FORMAR PARTE → `/public/identity/perfil-medico.php?entry=join`. En un perfil público FREE sin owner activo y con fuentes conocidas, el CTA de administración lleva a esa misma página con contexto público vuelto a resolver por el servidor. El plan visual de QA no convierte un perfil pagado en reclamable.

La pantalla original muestra el perfil del que se viene o la inscripción general, sin búsqueda, ID o referencia interna manual. Mantiene marca, tipografía y controles de Identity; no crea cuentas ni otro acceso. El contexto público no consulta sesión/cola privada ni anuncia una solicitud guardada. Los expedientes existentes conservan snapshot, documentos, revisión, MFA, confirmación y emisor.

**Límite B:** el query sólo selecciona una presentación pública; no es un origen persistido ni autoridad. No existe aún almacén de origen preauth, asociación autenticada, intake profesional versionado o resolución gobernada de perfil nuevo/coincidencia/conflicto. Claim sin VID01/CRD02 debe poder iniciar como pendiente en ese intake futuro; la evidencia final actual sigue exigiendo las fuentes verificadas y no se debilita. REQUEST_NEW_PROFILE deberá conservar su origen aunque se resuelva a un perfil existente. Ver [contrato](UX-ALIGN01-public-enrollment-contract.md) y [propuesta SQL DRAFT](UX-ALIGN01-enrollment-amendment-proposal.sql), fuera de migraciones/runners y sin aplicar. El usuario debe volver a esta página tras registrar/verificar su cuenta; no se afirma conservación automática durante registro/login.

QA B: 24 aserciones PHP, cuatro regresiones públicas y presentación Chromium/WebKit en tres tamaños, con doce capturas sintéticas. La adaptación del runner C4 retira el setup manual por ID: un helper CLI restringido al DB/usuario y cuentas/perfiles sintéticos crea el draft mediante el servicio nativo tras verificar teléfono; la UI original lo reanuda. Pasan sus comprobaciones de documentos, revisión/MFA, confirmación/replay/expiry, rollback de audit, emisor y denegaciones, con seis admisiones/bindings sintéticos y cero owner/membresías. El DB/usuario/recursos propios se eliminan. Ese positivo **no acredita** persistencia pública ni creación de perfil nuevo. La UI pública y preparación contractual están implementadas; la recepción real y resolución permanecen PARTIAL.

## C — informe de corrección separado del reclamo

Se reutiliza “Sugerir corrección” en el perfil público original, con su gate FREE/no administrado. La disponibilidad de la fuente de membresías ahora se propaga correctamente: una fuente ausente o fallida oculta la acción. El desplegable accesible muestra el perfil exacto y explica que la recepción sigue pendiente. Prepara catálogos de categoría y relación como opciones deshabilitadas, sin abrir ni seleccionar esos catálogos. Sus controles no tienen formulario ni envío y no almacenan datos del visitante. El reclamo y FORMAR PARTE permanecen separados.

No existe actualmente un backend equivalente de recepción, revisión o aplicación de informes. El [contrato de correcciones](../profiles/UX-ALIGN01-public-correction-contract.md) prepara ese agregado, contexto, privacidad, política de abuso/CSRF, revisión humana y adaptación a escritores autorizados, sin inventar grants ni activar infraestructura. Una alegación de retiro o fallecimiento nunca cambia publicación, cuenta, suscripción, autoridad o propietario automáticamente. La UI informativa está implementada; el flujo de recepción/moderación sigue PARTIAL.

QA C: 52 comprobaciones del repositorio/controlador nativos en memoria, 24 escenarios del template original en Chromium/WebKit y tres tamaños, más repetición de los seis casos públicos B tras este cambio: PASS. Cubren gate, origen de contexto, teclado, controles deshabilitados, separación de acciones, ausencia de mutaciones y overflow. El test estático más amplio `PublicProfileHeroConsultorioContactLayoutTest.php:52` conserva un fallo literal de iconos reproducido también en el baseline; no se modifica para ocultarlo. Evidencia privada en `~/Library/Application Support/MXMED/ux-align01/20261016-review/correction/`.

## D — reconciliación del panel original de Operadores

El panel `#p-ag-operadores` conserva sus dos columnas, tarjetas, acordeones, editor de cinco grants y acciones sujetas a servicio/titular. Ya presenta alias personal canónico, iniciales Unicode, puesto, colores `#FF007F` y `#FF66CC`, estado, permisos efectivos e historial. Cada operador conserva su cuenta Identity personal e invitación; no se introduce registro redundante ni contraseña elegida por el médico. El cambio de ocupante conserva el snapshot histórico de creador de cita. No se reimplementa OP-IMP07.

El overview actual entrega `contact_verified`, no nombre completo ni teléfono/correo privado verificables para la tarjeta. No se infieren a partir del alias, contacto de invitación o email. Tampoco existe backend privado de foto autorizado; siguen deshabilitados dropzone, QR y controles de foto, sin URL pública ni acceso a archivos clínicos. Se conserva el plan OP-UX06: cualquier ampliación de contacto o avatar necesita DTO acotado, permisos de cuenta personal, custodia privada y escritor propio autorizado antes de habilitar esos controles.

La única modificación de producto de esta fase sustituye una explicación técnica del archivo histórico por “Estos registros anteriores se conservan para consulta. No confirman una cuenta vigente ni ocupan plazas del equipo.” No borra ni fusiona referencias de navegador con registros del servidor. La colección local incierta sigue accesible en su sección separada; la igualdad de alias o ID no se trata como identidad acreditada.

QA D: 74 casos del runner original OP-UX06 y doce casos adicionales de referencias históricas, Chromium/WebKit en los tres tamaños: PASS. Incluyen servicio disponible/inactivo, error, no-owner, alias ausente/Unicode/colisión, foto no autorizada, dos plazas, cinco grants, rechazo de comando sin éxito falso y snapshot inmutable. Los doce casos conservan dos tarjetas de servidor y dos referencias locales sintéticas DUPX01/PQA1779469502 sin escrituras. Las suites nativas `OperatorFoundationTest.php` (200 aserciones) y `OperatorPanelReadinessTest.php` (ocho casos) pasan en datos recién desechables, con cleanup verificado. No se ejecuta ni resetea el entorno OP02 persistente.

El contrato estático `op-imp07-contract-qa.py` conserva un pin antiguo de `private-session.js` anterior a cambios ya publicados. Se reprodujo el mismo fallo utilizando las fuentes de `e927dcfa`; sus inputs y los dos runners son idénticos al inicio. `op-int04-contract-qa.py` se detiene al invocar ese contrato. No se actualizan ni omiten sus aserciones, y no se declaran verdes esas suites. Los componentes protegidos de Agenda/shell/sesión siguen idénticos al inicio de UX-ALIGN01. Evidencia en `~/Library/Application Support/MXMED/ux-align01/20261016-review/operators/`.

## E — evaluación de Recetas y límite de documentos versionados

La jerarquía solicitada ya existe en los componentes originales: médico/cédulas, paciente/edad/sexo/fecha, contexto, bloques de medicamentos, indicaciones, firma y consultorios/teléfonos publicados. No se añade información del mockup ni se inventan contactos. Se conservan las fuentes de producto byte por byte. El CSS compartido también afecta documentos históricos, de modo que un rediseño requiere una versión de renderer/stylesheet y una resolución histórica explícita; el [informe de evaluación](../clinical/UX-ALIGN01-prescription-presentation-assessment.md) prepara ese trabajo por separado.

La fixture CLI nueva sólo usa snapshots sintéticos y el renderer nativo, sin runtime clínico, PDO, sesiones o escrituras. Pasan 36 casos en Chromium/WebKit y tres tamaños, con seis snapshots que incluyen opcionales ausentes, sin encabezado, formato anterior y 24 medicamentos. Se verifican preview original, print media, legibilidad y datos privados excluidos. Chromium produce además dos PDF Letter de dos y siete páginas con todos los medicamentos y footer legibles; WebKit no exporta PDF y no se afirma esa aceptación. Se inspeccionaron capturas sintéticas; no hay prueba de emisión, firma, móvil o impresión física.

Pasan nueve contratos existentes. `rec-vis08-contract.test.mjs:36` conserva una expectativa anterior de “Médico responsable” dentro de `hidden`, incompatible con el contrato DOC-REC-STD03 vigente. Sus inputs son idénticos al inicio; no se cambia renderer o test para silenciarla. La evaluación acotada está terminada; no se aplica refinamiento visual al documento ni se declara verde toda la suite histórica. Evidencia en `~/Library/Application Support/MXMED/ux-align01/20261016-review/prescription/`.

## F — verificación integrada y entrega

**Resultado: PARTIAL_MXMED_UX_ALIGN01.** A queda implementada; D concluye su reconciliación y ajuste de texto apoyados en los contratos existentes; E concluye la evaluación y aceptación de presentación sin alterar documentos. B/C entregan presentación original y contratos, pero sus flujos persistentes siguen parciales. Ninguna página renderizada acredita activación o aprobación profesional real.

### Componentes, rutas y navegador

Los runners usan los templates, controllers, renderer, CSS y JavaScript originales con DTOs explícitamente sintéticos. No son otra aplicación entregada. Chromium y WebKit cubren los tres tamaños exigidos en A (48 casos), B (seis casos tras C), C (24), D (74 más doce de referencias) y E (36, pantalla e impresión). El recorrido C4 de 45 comprobaciones reanuda drafts sintéticos y verifica los pasos privados existentes; no sustituye el origen público aún ausente.

Los GET de normal-local verifican el certificado con la CA existente, sin cookies ni métodos mutantes: perfil, listing y API pública 200; entradas `entry=join` y `entry=claim&profile=1` 200; registro/login 200; acreditación privada, sección staff y `private-app.php` anónimos 302; current-session y búsqueda de pacientes anónimos 401. La consulta de claim es navegación de presentación, no una credencial. El perfil real tiene colaborador; no se afirma que sea un caso libre sin administración para todos los gates. Las URLs permanecen:

- `https://localhost:8443/private-app.php`, panel original `#p-ag-operadores`.
- `https://localhost:8443/profiles/doctor.php?doctor_id=1` y `/profiles/listing.php` con sus filtros originales.
- `https://localhost:8443/public/identity/perfil-medico.php?entry=join` y la variante de claim generada desde un perfil elegible.
- `https://localhost:8443/public/identity/acceso.php` y `/public/identity/crear-cuenta.php`.
- `https://localhost:8443/internal/media-review/index.php?section=acreditacion`, todavía guardada por autorización staff.

Se observó read-only el DOM de la pestaña Safari normal ya autenticada y el DTO GET nontouch del servidor. El DTO contiene la denominación almacenada y el rol anterior; no se recargó la pestaña ni se probó de nuevo todo el shell con los scripts nuevos dentro de esa sesión. No se extraen cookies ni credenciales. **DIRECTOR_PHYSICAL_SAFARI_QA=NOT_RUN.** Una revisión física posterior puede actualizar normalmente la aplicación y observar la denominación bajo el nombre; los envíos públicos, SMS, foto y activaciones continúan pendientes.

### Seguridad, datos y límites de los resultados

Los snapshots completos antes/después de `mxmed` son idénticos: 128 tablas, filas/checksums, DDL, triggers, rutinas y auditoría. También coinciden payloads de sesiones Valkey, configuración normal, journals OP-ACT02A y checksums de QA05, OP02 y UI03. Se preservan cuentas, membresías, propietarios, autoridades, planes, datos clínicos e historia. Los listeners 8091, 8092, 8443 y 6385 siguen activos; no se detienen al entregar. Los runners mutantes usaron bases/usuarios nuevos sintéticos, con cleanup comprobado; cero filas reales importadas en C4. No hay DDL normal, SMS, invitación real, staff nuevo, subida real, firma, receta emitida ni composición operativa nueva.

Los archivos clínicos de producto, writers, fingerprints, renderer/CSS de documentos, guard CLIN-AUTH02A, Agenda, estructura del shell, sesión y autoridades de C4/Operadores permanecen idénticos al baseline. Las excepciones del encabezado son su proyección y renderer `header-account.js` descritos en A; no cambia su estructura visual o autorización. Las cuatro migraciones canónicas C4 y la preparación operativa se conservan sin ejecutar; la propuesta B está en documentación, no en un runner. El manifiesto C4-ACT01 sigue siendo una preparación histórica, no una aprobación vigente; cualquier futuro despliegue deberá renovar fingerprints tras los cambios de fuente UX.

Pasan la regresión CLIN-AUTH02A, el resolver clínico canónico, el contrato Agenda CONS-VIS01, sesión privada/GOV-SIM02, las 80 comprobaciones aisladas normal-local y las suites nativas de Operadores (200 más ocho). El conjunto dirigido estático registra diez de once runners PASS, con el fallo RecVis08 informado abajo. Además pasan las 42 comprobaciones nativas del header, 24 de inscripción pública y 52 de corrección, y los contratos de presentación de documentos descritos en E. Sintaxis de 25 archivos PHP/JS/Python y whitespace del diff completo: PASS. No se amplía una aceptación dirigida a toda la suite histórica.

Deudas previas preservadas y visibles:

| Gate | Estado y evidencia |
| --- | --- |
| `PhysicianCredentialAuditPolicyTest.php` | Pin histórico de 52 eventos frente a 54. No modificado ni repetido aquí; evidencia previa `c4-ui01/20261016-review/historical-audit-pins.log`. |
| `PublicProfileHeroConsultorioContactLayoutTest.php:52` | FAIL literal de iconos, reproducido también con archivo Git del baseline; `correction/hero-baseline-rerun.json`. |
| `rec-vis08-contract.test.mjs:36` | FAIL de expectativa de modo hidden, inputs idénticos al baseline; `regressions/rec-vis08-baseline-confirmation.json`. |
| `op-imp07-contract-qa.py` / `op-int04-contract-qa.py` | Pin antiguo de sesión falla también con las fuentes baseline; segundo runner bloqueado por esa dependencia. `operators/baseline-contract-pin-failure.json`. |

Evidencia principal privada: `~/Library/Application Support/MXMED/ux-align01/20261016-review/`, en particular `preservation-result.json`, `normal-before.json`/`normal-after.json`, `state-before.json`/`state-after.json`, `final/normal-readonly-http.json`, manifests, resultados por dominio y logs. Los snapshots no se publican en Git porque contienen datos privados.

### Inventario y siguiente incremento

Son 31 archivos: trece fuentes de producto, trece fixtures/tests/runners y cinco documentos/propuestas. Producto: proyección del header y JS original; controller/repository y dos servicios públicos de presentación/elegibilidad; templates de perfil/listing/onboarding, adaptador de página original y JS de onboarding; CSS acotado del informe y una frase del archivo de Operadores. Las pruebas nuevas son CLI/componentes/datos sintéticos; no son páginas nuevas del producto. Los cambios se dividen en commits independientes de A, compatibilidad de fixture A, B, C, D, E y documentación de cierre.

Los bloqueos exactos pendientes son: almacén preauth de origen inmutable y asociación a cuenta tras login, intake profesional pendiente sin VID01/CRD02 prematuros, resolución revisada sin-match/coincidencia/conflicto y backend de perfil nuevo (B); agregado/endpoint de informes con política de abuso/CSRF, moderación y aplicación tipada autorizada (C). Para operar C4 normal-local siguen faltando los gates de [C4-ACT01](C4-ACT01-operational-activation-package.md): esquema autorizado, proveedor/adaptador SMS, custodia/retención/recuperación, staff/MFA legítimos y factory/guard operacional. No se modifican por este trabajo. Contacto/avatar de operadores requieren su backend privado; un rediseño de Recetas requiere el plan versionado de E.

El siguiente incremento pequeño recomendado es implementar y probar **sólo en datos nuevos desechables** el origen preauth, su asociación autenticada y el intake pendiente de B, usando la propuesta como borrador a revisar. Mantener separadas la resolución final y cualquier autorización posterior de DDL/runtime. Las deudas estáticas deben reconciliarse en una corrección explícita que conserve las pruebas de autoridad y el catálogo real; no se silencian para publicar esta UI. Recepción de informes será un incremento separado. No se declara activación completa de Operadores, gobierno, C4 o firma clínica.
