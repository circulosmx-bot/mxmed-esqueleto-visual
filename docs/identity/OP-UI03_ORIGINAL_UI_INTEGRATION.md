# OP-UI03 — integración en la aplicación original

> Actualización OP-INT04: [integración normal-local](OP-INT04_NORMAL_LOCAL_OPERATORS_INTEGRATION.md) sustituye el formulario/controller legacy por el panel canónico único con readiness de servidor. Los resultados y límites descritos abajo corresponden al hito histórico anterior. Normal-local permanece sin activación, migraciones ni mutaciones reales de operadores.

Baseline: `4e3e4db0c1cacd3236a57483f5f6e46ef2309635`. Rama `ux/consultation-step2-vitals-r1`. Fecha: 2026-10-15.

## Corrección de OP-IMP02

OP02 eligió una página acotada independiente para evitar que el shell médico enviara contenido clínico a operadores. Esa decisión preservó el límite de datos, pero sustituyó la interfaz y la Agenda que el Director había desarrollado. La revisión física rechazó esa integración visual. Se eliminan `operator-workspace.php`, su JavaScript y su CSS. El router sirve ahora **private-app.php**; no hay otra aplicación ni sidebar alternativo.

Se mantienen [ROLE-POL01 / OP-POL02](../product-completion/MXMED_PRODUCT_COMPLETION_EXECUTION_GUIDE_POST_PP325.md), [OP-IMP01](OP-IMP01_OPERATOR_FOUNDATION.md), la infraestructura [OP-IMP02](OP-IMP02_ISOLATED_OPERATORS_RUNBOOK.md), [CLIN-AUTH02A](../clinical/CLIN-AUTH02A_URGENT_CONTAINMENT.md), [CLIN-AUTH02B1](../clinical/CLIN-AUTH02B1_PROFESSIONAL_AUTHORITY_CONTRACT.md) y el aislamiento de [QA05](../qa/IDENTITY-QA05-SAFARI-ADMIN-QA.md). No se implementa acreditación ni autoridad clínica.

## Una implementación visual compartida

- `private-app.php` conserva el header, `#mmSidebar`, contenedores, navegación y estilos originales. En normal-local su resolver médico no cambia. Sólo la composición aislada explícita obtiene un contexto administrativo sin capacidades clínicas.
- [operators.php](../../partials/private-app/operators.php) es el panel original extraído, con `#p-ag-operadores`, `#ag_ops_shell`, `#ag_ops_layout`, `#ag_ops_list`, lista/archivo, tarjetas, resumen, acordeones y modal de auditoría. Normal-local renderiza el mismo DOM anterior. La rama canónica sustituye exclusivamente credenciales/campos innecesarios y cuatro flags amplios por email, oferta Identity y cinco checkboxes independientes, todos inicialmente apagados.
- [agenda.php](../../partials/private-app/agenda.php) es la Agenda original extraída: `#p-ag-admin`, `#agendaFrontendV1`, `#ag_calendar`, día/semana, controles de horas y consultorio, detalle, creación y reprogramación. Se usa tanto para titular como para operador; no contiene un segundo calendario.
- [agenda-workspace.js](../../assets/js/agenda/agenda-workspace.js) mueve el bloque Agenda original de `app.js` a un único archivo compartido. Conserva su FullCalendar, calendario custom, slots, validaciones, modales y flujo de confirmación. El resto de `app.js` no se entrega al operador. La aplicación normal carga este mismo componente; un initializer síncrono se llama desde la posición original del bloque en `app.js`, conservando el orden de inicialización respecto a los demás módulos.
- [canonical-operators.js](../../assets/js/agenda/canonical-operators.js) conecta el DOM original a los servicios ya existentes, adapta los DTO mínimos y las cabeceras canónicas. No contiene un calendario ni un writer de citas/membresías.
- `core/navigation.js` conserva paneles y flyouts; `core/sidebar.js` extrae exactamente el IIFE VIS25 original de `app.js`, incluyendo pin/hover, drawer móvil, Escape y sincronización de geometría. Se inicializa desde la misma posición original en la aplicación médica y explícitamente en la composición acotada. No se inventa otro menú. En la composición acotada el panel inicial debe existir en el HTML autorizado; no intenta abrir Resumen ni cargar Manejo Hospitalario.

Los includes no pueden abrirse directamente como páginas: requieren el acceso del entrypoint. La composición del servidor no emite los paneles clínicos/fiscales/staff, ni la configuración de Agenda o el panel de operadores para un no-owner. Una cuenta sin grants recibe únicamente invitaciones. No se manda HTML sensible para ocultarlo después con CSS.

## Integración funcional y seguridad

El panel usa `ProfileOperatorService` y el mismo contrato HTTP de invitación/aceptación, revisión optimista, cupo, lifecycle, plan, revocación y CanonicalAuditWriter. El actor de una cita sigue siendo la cuenta real; el médico es el ámbito de práctica. No se fabrica un rol o doctor del cliente para autorizar.

El bootstrap acotado entrega una marca opaca HMAC ligada al digest de la sesión canónica, sin exponer cookie/digest. El guard de respuestas distingue esta identidad estable de los nonces CSRF de cada validación. En OP02, renovar CSRF no descarta lecturas válidas; rotar sesión/cambiar cuenta sí cambia la marca y descarta respuestas previas. El fallback y la validación normal-local no se modifican. Se conservan las expiraciones canónicas.

El proxy mantiene el mapeo exclusivo de su cookie aislada incluso si el cliente envía el nombre de cabecera `cookie` en minúsculas. Las cookies normal-local, QA05 y del otro entorno siguen sin aceptarse.

La aceptación necesita una nueva respuesta del servidor para incorporar componentes recién autorizados. Tras aceptar se navega a `/private-app.php?workspace=agenda#p-ag-admin`, evitando conservar el HTML previo sin Agenda.

Los handlers legacy de contraseña/migración no se instalan en esta composición. Sus endpoints y aliases siguen denegados. El browser no carga `app.js` junto al adapter: no hay dobles listeners/writes. No existe otra membresía ni un cupo frontend de tres. Los contadores provienen de `overview`; un fallo dice «No disponible».

La Agenda llama sus writers existentes a través de los mismos cinco consumidores autorizados de OP02. El adapter retira del DTO de presentación los campos legacy de actor/doctor antes de enviarlo; el servidor sigue rechazando peticiones forjadas y obtiene cuenta, perfil y recursos de fuentes canónicas.

READ devuelve únicamente estado/horario/consultorio/referencia de cita y nombre mínimo de la persona vinculada al perfil. CREATE recibe únicamente id/nombre mínimo para seleccionar una persona ya registrada. No se consultan los endpoints de expediente para enriquecer eventos o buscar pacientes; no se entregan contactos, edad, notas, motivos clínicos, flags ni documentos.

Consultar disponibilidad permite proyectar ventanas/slots y cierres acotados del consultorio, sin habilitar `/schedule`, `/settings` o sus escrituras. La visualización del calendario usa esa misma respuesta autorizada. Ausencia de permiso no se interpreta como horario cerrado ni como disponibilidad conocida.

CREATE sin READ ni AVAILABILITY usa el **mismo modal original**: muestra sus campos existentes de inicio/fin para proponer horario mediante un botón nativo en la Agenda. RESCHEDULE sin AVAILABILITY usa una fecha/hora propuesta dentro del **mismo modal original**, validación y confirmación. La UI explica que no consultó disponibilidad; el writer real comprueba horario y conflictos al guardar. No se simulan slots disponibles ni se concede AVAILABILITY implícitamente. El parámetro visual legacy `agenda_demo` queda inerte en la composición canónica; no se inyectan citas demo ni se habilita una autoridad QA por cliente. RESCHEDULE/CANCEL sin READ conservan el contrato HTTP para una referencia/versión ya conocida; no reciben un listado ni una nueva interfaz de consulta por locator.

No se activan confirmar/no-show, waitlist, bloqueos, horarios, settings, pacientes nuevos, expediente, firma, fiscal, medios, suscripción o plataforma. El router aplica la denegación a todas las solicitudes, archivos existentes y verbos. Los guards clínicos y los dispatchers normales no se debilitan.

## Entornos y conservación del estado

Instancia de revisión existente, conservada:

**https://127.0.0.1:8445/private-app.php#p-ag-operadores**

Credenciales sintéticas fuera de Git:

`/Users/circulodigital/Library/Application Support/MXMED/op02-admin/Safari credentials.txt`

Usar owner para Operadores; A/B para Agenda. La cita de demostración anterior está en `evidence/review-state.json`. No se ejecutó reset, seed ni preparación destructiva sobre OP02. No se cambian sus cuentas, memberships, grants o citas para hacer pasar las pruebas. Sus procesos y sesiones permanecen activos.

Las pruebas destructivas usan exclusivamente un recurso adicional literal:

| Recurso | UI03 desechable |
|---|---|
| Selector del proceso | `MXMED_OP_UI03_REHEARSAL=1`, además de todos los guards OP02 |
| DB / usuario | `mxmed_gate4d_preview_opui03` / `mxmed_opui03@127.0.0.1` |
| HTTPS / PHP / Valkey | loopback `8446` / `8095` / `6388` |
| Cookie | `__Host-mxmed_opui03_session`; ignora las cookies normales/QA05/OP02 |
| Recursos propios | `~/Library/Application Support/MXMED/opui03-rehearsal/` |
| Datos | schema canónico vacío + fixtures sintéticos nuevos; sin clonar datos reales ni OP02 |

```sh
MXMED_OP_UI03_REHEARSAL=1 python3 scripts/op02-env.py prepare
MXMED_OP_UI03_REHEARSAL=1 python3 scripts/op02-env.py start
MXMED_OP_UI03_REHEARSAL=1 python3 scripts/op02-http-qa.py
python3 scripts/op-ui03-browser-qa.py
python3 scripts/op-ui03-session-qa.py
python3 scripts/op-ui03-contract-qa.py
```

El browser runner exige/selecciona el recurso UI03 antes de cualquier reset; nunca reinicia OP02. La antigua entrada `op02-browser-qa.py` delega a este runner. `op02-env.py status` sin selector conserva compatibilidad con la configuración OP02 previa, sin reescribirla. Start/stop/reset/destroy siempre quedan limitados al recurso literal seleccionado; no cambian MySQL global, normal-local ni QA05.

## Evidencia y límites

`op-ui03-contract-qa.py` compara el DOM normal renderizado contra el baseline: header/sidebar, Operadores y Agenda deben ser idénticos, incluyendo orden, atributos y texto normalizado. Comprueba también fuente única de Agenda y retiro de la página paralela. Esto es una prueba de preservación del template; no representa una sesión autenticada del Director en normal-local.

Evidencia privada: `~/Library/Application Support/MXMED/op02-admin/evidence/op-ui03/normal-component-parity.json` y `baseline.json`; resultados HTTP/browser/capturas en `~/Library/Application Support/MXMED/opui03-rehearsal/evidence/`. Sólo fixtures sintéticos en capturas; sin contraseñas ni tokens en Git/resultados.

La comparación autenticada contra `https://localhost:8443/private-app.php` depende de una sesión manual disponible. No se copia una cookie del Director ni se autentica en su nombre. WebKit automatizado y esta preservación de DOM no equivalen a aceptación física en Safari.

Resultado ejecutado del browser: Chromium y WebKit pasan en 1440×900, 1366×768 y 820×1180, incluyendo navegación estrecha a 390 px. Cada recorrido usa Identity real del fixture, invitación/aceptación, dos plazas, edición/revocación de grants, creación/reprogramación/cancelación en los formularios originales, persistencia y denegaciones. Capturas finales: `{chromium,webkit}-{ancho}x{alto}-original-{operators,agenda,appointment-form}.png` y `*-narrow-navigation.png`; `op-ui03-browser.json` registra los seis PASS. Los archivos `failure-*` conservan intentos de desarrollo previos, no el resultado final.

`op-ui03-session.json` registra cinco PASS con HTTPS real: marca estable y nonce nuevo, lectura en curso conservada al renovar CSRF, descarte al rotar sesión, acceso válido después de rotación y denegación tras logout. El test demora una respuesta real; no sustituye la decisión del servidor.

La corrida HTTP final registra **48 PASS y 1020 denegaciones reales 403** (`http-qa.json`): rutas clínicas, Recetas/firma, consentimientos, fiscal, staff, aliases y archivos PHP alternos, writers legacy, cambio de perfil y recursos de otro médico. Incluye cupo/concurrencia OP01, transición gobernada, CSRF/origin/cookies, auditoría y rollback, idempotencia y regresiones existentes de Identity/GOV03A/CLIN-AUTH02A/Agenda. También pasan `PrivateSession02ContractTest`, `PublicAgendaEmailOtpDeliveryTest` y `Gate8EPublicAgendaOtpPrivacyTest` sin entrega real. No se debilita ningún guard para ejecutar QA.

La comparación final de checksums de todas las tablas normal-local/QA05 y las 49 tablas del OP02 persistente coincide con el preflight (`preservation.json`). Los tres procesos OP02 originales continúan activos. No hay migración normal-local, cambios reales de cuentas/roles/membresías ni entrega de invitaciones reales.

Safari normal-local se comprobó en una pestaña nueva sin alterar las anteriores: `/private-app.php#p-ag-operadores` redirigió al acceso canónico, por lo que no había sesión autenticada disponible para esa comparación. Queda pendiente el login manual del Director en `https://localhost:8443/public/identity/acceso.php?next=%2Fprivate-app.php#p-ag-operadores` y su comparación visual autenticada. La preservación del DOM y las pruebas aisladas no cierran ese gate: el resultado de OP-UI03 se reporta **PARTIAL**. Tampoco se declara aceptación física de Safari.

Antes de activación real se mantienen pendientes la autorización de migraciones/recuperación/composición real, cierre de autoridad legacy, proveedor de entrega real y gate transversal HTTP en el despliegue objetivo. Esta corrección no habilita médicos reales ni promueve a la Directora.
