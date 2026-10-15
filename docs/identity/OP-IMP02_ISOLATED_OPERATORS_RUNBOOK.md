# OP-IMP02 — OPERADORES y Agenda funcionales en QA aislada

Fecha: 2026-10-15. Baseline: `aa7184c8c49b171f89a969226acbb605e94a0eb6`, rama `ux/consultation-step2-vitals-r1`.

**Entrega funcional exclusivamente aislada.** No habilita operadores reales en normal-local, staging o producción. Conserva [OP-POL02](OP-POL02_NONMEDICAL_OPERATOR_IMPLEMENTATION_READINESS.md), las reglas Q01–Q04/Q12 de [OP-IMP01](OP-IMP01_OPERATOR_FOUNDATION.md) y la contención [CLIN-AUTH02A](../clinical/CLIN-AUTH02A_URGENT_CONTAINMENT.md).

## Abrir la instancia verificada

URL: **https://127.0.0.1:8445/private-app.php#p-ag-operadores**. Acceso: **https://127.0.0.1:8445/public/identity/acceso.php**.

Credenciales exclusivamente sintéticas, fuera de Git y con permiso 0600:

`/Users/circulodigital/Library/Application Support/MXMED/op02-admin/Safari credentials.txt`

Usar `owner` para administrar, `operador-a` o `operador-b` para probar los permisos propios. El Director no necesita introducir su contraseña real. El perfil es **Dra. Aurora Entorno Aislado**, `op02-doctor`, independiente de doctor 1. La preparación final deja dos nombramientos aceptados mediante los endpoints reales: A puede consultar, crear, reprogramar y consultar disponibilidad; B puede consultar y cancelar. Administrator y collaborator usan las mismas plazas. No hay vínculo profesional ni permiso clínico.

Una cita de demostración se crea por el writer real para la fecha indicada en `evidence/review-state.json` y en la salida de `op02-review-ready.py`. Elegir esa fecha en Agenda. Los horarios sintéticos de los consultorios son 08:00–20:00. No hay pacientes ni citas reales.

El titular encuentra **OPERADORES** en la misma URL/ancla privada `p-ag-operadores`, con los componentes `mm-card`, cabeceras y clases `mx-ag-ops-*` existentes. Puede actualizar el equipo, invitar con todos los permisos inicialmente apagados, revisar/cancelar/reexpedir ofertas, editar grants, suspender/reactivar/revocar y ver auditoría. El contador proviene del servidor; un fallo muestra «No disponible», nunca un cero inventado.

El invitado inicia sesión en Identity, abre **Mis invitaciones**, ve el perfil y permisos exactos y acepta o rechaza. No se muestra ni se copia un token. Abrir el mensaje entrega su secreto una sola vez; si se cierra sin responder, el titular debe reexpedir. La oferta anterior no puede aceptar permisos de la nueva.

El operador recibe una proyección de Agenda con navegación acotada, no el HTML/bootstrap médico completo. CREATE sin READ puede crear y recibir la referencia de su propia cita sin consultar la lista. RESCHEDULE/CANCEL sin READ tienen un formulario de referencia/horario para una tarea facilitada por el titular; el servidor comprueba el recurso real, su perfil y versión. Ninguno de esos permisos implica otros.

## Recursos y procedimientos

| Recurso | Aislamiento real |
|---|---|
| MySQL | `mxmed_gate4d_preview_op02`, usuario exclusivo `mxmed_op02@127.0.0.1`; sin permisos sobre mxmed/QA05. Schema vacío + migraciones canónicas + datos sintéticos, sin dump de datos reales. |
| HTTPS / app | `127.0.0.1:8445` / `127.0.0.1:8094`; bind loopback, Host exacto, TLS verificado con el certificado local copiado a su propio directorio. |
| Sesiones | Valkey independiente `127.0.0.1:6387`, prefijo `mxmed:op02:session:`, AOF propio. SessionService/credenciales/CSRF canónicos. |
| Cookie | `__Host-mxmed_op02_session`; proxy traduce sólo esa cookie al nombre interno canónico e ignora cookies normales/QA05. Secure, HttpOnly, path raíz. |
| Archivos | `~/Library/Application Support/MXMED/op02-admin/`: configuración, TLS, tmp, media privada/pública, Valkey, evidencia y procesos propios. Notificaciones Identity preview sólo en `/tmp/mxmed-op02-<uid>/`. |
| Entrega | Buzón SQL local cifrado con libsodium y clave privada del entorno. Ningún proveedor de correo real. |

Desde el worktree correcto:

```sh
python3 scripts/op02-env.py prepare
python3 scripts/op02-env.py start
python3 scripts/op02-env.py status
python3 scripts/op02-env.py stop
```

`prepare` no sobreescribe datos de revisión existentes. Si no hay configuración propia y ya existe el nombre de DB/usuario, se detiene para evitar apropiarse de recursos ajenos. Instalación de schema usa el administrador local **sólo por CLI y en la DB fija OP02**; no concede SUPER al usuario runtime ni altera opciones globales de MySQL.

Para reiniciar **únicamente los fixtures OP02** y preparar una revisión limpia:

```sh
python3 scripts/op02-env.py reset
python3 scripts/op02-env.py start
python3 scripts/op02-review-ready.py
```

`reset` borra sólo la DB OP02 y su persistencia Valkey; conserva la configuración, credenciales sintéticas y evidencia. No usar durante trabajo manual que se quiera conservar. `op02-review-ready.py` exige dos plazas vacantes; invita/acepta por HTTPS, crea una cita y verifica persistencia real de grants y cookies tras stop/start. No inserta membresías ni sesiones para aparentar éxito.

La instancia se deja ejecutando al finalizar. Para eliminarla después de la revisión:

```sh
python3 scripts/op02-env.py destroy --confirm mxmed_gate4d_preview_op02
```

El guard de destrucción no acepta mxmed ni QA05. No se detuvieron ni resetearon sus servidores.

## Una autoridad compartida

[ProfileOperatorService](../../modules/identity/operators/ProfileOperatorService.php) extrae el servicio OP-IMP01 sin duplicar su lógica. DisposableOperatorFoundation queda como factory compatible para las pruebas CLI originales. La segunda composición exige configuración fija OP02 y un front controller que haya establecido el boundary; no hay factory normal-local/productivo.

Se reutilizan auth_accounts, credenciales, memberships, normalización de email, token codec, SessionService y CSRF. El owner no crea contraseñas. Los cinco usuarios canónicos del fixture se preparan sólo para QA. `agenda_operators`, sus hashes temporales y flags legacy permanecen vacíos y sin autoridad. No se vinculan personas por similitud de nombre/email.

Todas las mutaciones de equipo conservan lock de perfil, cupo de dos por cuenta/reserva, revisión optimista, contacto verificado, owner actual y audit unido. Suspensión conserva plaza; revocación confirmada libera. Las pruebas OP-IMP01 de procesos concurrentes y cambio GOV03A con aprobación dual/carrera se ejecutan contra sus bases desechables independientes.

El catálogo ejecutable sigue siendo exactamente:

```text
agenda.appointments.read
agenda.appointments.create
agenda.appointments.reschedule
agenda.appointments.cancel
agenda.availability.read
```

No se habilitaron confirm/no-show, waitlist, bloques, schedule/settings, consultorios, perfiles/contactos, medios, fiscal, renovaciones o captura clínica. Entitlement se resuelve desde CurrentSubscriptionReadModelService en la conexión aislada y nunca desde un plan del cliente. Datos de display no son decisiones de autorización.

## HTTP y consumidores reales

El [router aislado](../../scripts/op02-router.php) procesa **cada** request, incluso archivos PHP existentes, antes de despachar. Valida Host/origen/loopback, rechaza paths codificados, traversal y variantes, y sólo permite assets explícitos, Identity y las rutas aprobadas. Todos los demás módulos, aliases, controladores alternativos y descargas se deniegan por servidor. Un marker interno impide construir el gateway privilegiado sin ese boundary. La ruta nueva `/api/profile-operators/index.php/*` devuelve 404 en normal-local sin activación explícita; se verificó por HTTP.

| Ruta aislada | Autorización / comportamiento |
|---|---|
| GET `/api/identity/index.php/current-session` | SessionService real + bootstrap mínimo `agenda_operators`; sin contexto clínico. Login/logout/registro/verificación siguen usando los handlers Identity existentes y entrega preview inerte; contactos externos a `.invalid` se rechazan. |
| GET `/api/profile-operators/index.php/bootstrap` | Cuenta activa verificada; perfil único derivado de membresía, grants efectivos y buzón propio. Datos mínimos de consultorio. Sólo CREATE permite id/nombre mínimo para seleccionar una persona ya vinculada al perfil; nunca ficha clínica/contactos/notas. |
| GET `…/overview`, `…/audit` | Owner exacto, sesión/CSRF; cupo y equipo exclusivamente del perfil canónico. Sin tokens en listas o auditoría. |
| POST `…/command` | Invitar/cancelar/reexpedir/grants/suspender/reactivar/revocar/revisar por owner; aceptar/rechazar por destinatario verificado y oferta exacta. Actor y perfil no vienen como evidencia del body. |
| POST `…/open-invitation` | Destinatario canónico exacto, sesión/CSRF/origen; entrega local cifrada de un uso. |
| GET `/api/agenda/index.php/appointments` | Sólo READ; rango acotado de máximo 32 días, perfil servidor, DTO de cita sin contenido clínico. |
| POST `…/appointments` | Sólo CREATE, Idempotency-Key, consultorio real y vínculo persona-perfil; writer existente. No creación implícita de pacientes ni actor/doctor/flags del body. |
| PATCH `…/appointments/{id}/reschedule` | Sólo RESCHEDULE, recurso real bloqueado y horario anterior exacto; writer existente. |
| POST `…/appointments/{id}/cancel` | Sólo CANCEL, recurso real y versión del horario; writer existente, sin flags clínicos/notificación externa. |
| GET `…/availability` | Sólo AVAILABILITY READ, consultorio del perfil, AvailabilityController existente y DTO sin pacientes. |

Los métodos y paths no aprobados devuelven 403. Las acciones legítimas con conflicto de horario/versión devuelven 409; fallos de infraestructura/audit devuelven 503 y conservan el formulario. No hay éxito optimista ni recurso protegido identificado en errores.

AppointmentWriteController incorpora seams `*FromPayload` para conservar sus validaciones. AppointmentWriteRepository se une a la transacción del caller cuando existe; sus callers anteriores conservan commit propio. El lock de perfil, autorización/revisión actual, writer, evento lifecycle y audit canónico comparten commit. Fallo de audit revierte la cita. Se conservan conflictos de slots, idempotencia create y lifecycle. Código/rutas de booking público normales no se activan ni modifican en este router aislado.

AvailabilityController omite únicamente el bootstrap de aliases para callers con perfil canónico ya resuelto: su CREATE TABLE legacy cerraba implícitamente la transacción. Callers anteriores conservan su comportamiento. El perfil delegado nunca se remapea por un alias legacy después de autorización.

## Entrega durable y auditoría

[Outbox forward](../../modules/identity/db/migrations/2026_10_15_03_operator_qa_outbox.sql) se aplica sólo a OP02. El token se cifra/enfila dentro de la misma transacción que invitación/audit; un fallo no deja invitación sin entrega durable. Sólo la cuenta verificada destinataria puede abrirlo por POST. Se borra el ciphertext tras abrir, cancelar, reexpedir o materializar expiración, conservando metadata. Aceptación vuelve a verificar oferta, owner, perfil, contacto, vigencia y replay. [Recovery](../../modules/identity/db/migrations/2026_10_15_03_rollback_operator_qa_outbox.sql) rechaza historia poblada; no elimina silenciosamente entregas reales.

CanonicalAuditWriter, sealer, mapper y eventos OP-IMP01 se reutilizan. El evento cerrado adicional `PROFILE_OPERATOR_AGENDA_ACTION` registra cuenta real, membresía, revisión, capacidad, perfil/recurso, resultado, sesión y correlación; los catálogos tienen 52 entradas coherentes. No se usa el catálogo administrativo genérico vacío. El puerto SQL OP02 se une a la transacción, usa CAS y tablas del schema aislado; no llama rutinas calificadas a mxmed. No incluye notas, contactos ni tokens.

## Evidencia ejecutada

```sh
# Requiere fixture OP02 vacío; muta sólo datos sintéticos OP02.
python3 scripts/op02-env.py reset
python3 scripts/op02-env.py start
python3 scripts/op02-http-qa.py

# Seis casos, reinicia sólo OP02 entre casos; conserva evidencia.
python3 scripts/op02-browser-qa.py --reset-between-cases
```

- **OP02_01–48: PASS.** HTTP real con cookies Identity/Valkey, cinco grants independientes, cupo/lifecycle, outbox, replay/oferta, revocación inmediata y scope; no son assertions de navegación.
- **995 denegaciones HTTPS** registradas: módulos clínicos, recetas/firmas/consentimientos, documentos/adjuntos, credenciales/medios, billing/subscriptions, staff/governance, aliases legacy y archivos PHP API existentes; GET/POST/PUT/PATCH/DELETE. Los endpoints válidos del slice se prueban por separado con permiso y sin él.
- Rollback físico del writer ante trigger de fallo de audit exclusivamente en OP02; se verifica que no apareció cita. El trigger se retira en `finally`; no existe un endpoint/flag HTTP de bypass o fallo.
- Las 38 pruebas OP-IMP01 retienen concurrencia real SQL y cambio gobernado de owner. Sus nueve regresiones Identity/GOV03A pasan; también las de Agenda, audit y contención clínica.
- Chromium **y** WebKit: 1440×900, 1366×768, 820×1180, sin overflow horizontal ni errores JS. Recorrido: owner, dos ofertas, aceptación, edición, login operador, crear/reprogramar/disponibilidad, recarga, revocación sin renovar sesión, denegación backend, navegación acotada y logout/login. No se afirma aprobación física del Director en Safari.
- CHECKSUM de todas las tablas normales y QA05 antes/después; sin diferencias. No se accedió a sesiones/media/fixtures de QA05 ni se copió dato real.
- HTTPS se verificó también con `curl --cacert …/tls/op02.crt`, HTTP 200, sin modificar confianza/TLS de otras instancias.

Evidencia privada, sin contraseñas/tokens: `~/Library/Application Support/MXMED/op02-admin/evidence/http-qa.json`, `browser-qa.json`, capturas por engine/viewport y `review-state.json`. Las capturas sólo muestran el fixture sintético. No se suben a Git.

Dos pruebas históricas conservan sus gates de entrega originales por defecto y añaden modos explícitos de regresión: `clin_auth02a_contract.php --security-regression` bloquea cambios en los guards clínicos de producción frente a OP-IMP01, manteniendo sus asserts de firma/fingerprint; `Gate8DAppointmentLifecycleIdempotencyTest.php --semantic-regression` mantiene sus pruebas de máquina/idempotencia y archivos protegidos, sin exigir manifests históricos desaparecidos de `/tmp`. No se fabrican esos manifests ni se debilita una guarda runtime para pasar QA.

## Activación real pendiente

El resultado es completo **en QA aislada**. Normal-local continúa sin migración OP-IMP01/OP-IMP02, sin cambios de usuarios/roles/membresías reales y sin envío de correo. GOV03B1 no se aplicó. No se modificaron writers o documentos de Recetas ni su autoridad profesional.

Antes de habilitar médicos reales se necesita autorización separada de migración/ensayo/recuperación y composición normal-local/productiva, reconciliación y cierre definitivo de writers legacy, entrega productiva durable autorizada con controles operativos de frecuencia/retención, y repetir el gate transversal HTTP en esa composición real. El guard del router OP02 no constituye prueba automática de los dispatchers productivos. Mantener fail-closed hasta entonces. Q05–Q11 y cualquier función fuera de los cinco grants requieren decisión/integración propia.
