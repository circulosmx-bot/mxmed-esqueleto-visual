# PUB-ENR03 — resolución revisada y continuación explícita a C4

2026-10-16. Inicio `11811731e5fd54ecc34d632a75eba9217e6c6e88`, rama `ux/consultation-step2-vitals-r1`, worktree correcto `mxmed-step2-vitals-r1` limpio y upstream coincidente. Incremento de fuente y QA sintética nueva; **sin DDL, decisiones, acreditaciones, titularidad ni activación normal-local**. Continúa [PUB-ENR02](PUB-ENR02-pending-enrollment.md); no rehace su origen, registro, email, asociación o intake.

## Autoridades distintas

| Etapa | Hecho que produce | Límite |
| --- | --- | --- |
| A. Solicitud pública | Origen inmutable, cuenta verificada e información declarada cifrada/versionada. | No acredita una profesión ni selecciona un owner. |
| B. Resolución humana | Correspondencia explícita con un perfil existente y fuentes VID01/CRD02 concretas. | Matching, incluso único, no aprueba por sí solo la acreditación. |
| C. Acreditación profesional | C4 comprueba contacto, documentos privados, revisión/MFA y confirmación independiente; su emisor produce admisión/binding. | No crea membresía, owner, acceso al expediente ni firma clínica. |
| D. Titular médico | Transición separada por el escritor canónico de membresías y su autorización exacta. | No se ejecuta desde la resolución ni desde el emisor. |
| E. Operadores | Administración independiente por owner autorizado, dos plazas y grants de Agenda separados. | Operadores no reciben ownership o autoridad profesional; runtime sigue deshabilitado. |
| F. Autorización clínica | Requiere sesión, profesional/membresía/entitlement, operación, paciente/documento/intención y auditoría actuales. | CLIN-AUTH02A sigue cerrado; no se activa firma ni se toca Recetas. |

No se exige suscripción pagada para completar la admisión FREE ensayada. La elegibilidad comercial del inicio/revisión y la acreditación profesional son dimensiones distintas: un cambio comercial posterior no convierte una evidencia profesional en falsa. No se impone revisión anual ni recuperación extraordinaria raíz V3.

## Escritor de resolución

`ReviewedEnrollmentService` reutiliza `PublicEnrollmentService::reviewIntakeInTransaction`, `ProfessionalMatchCandidates`, `EvidenceAuthority`, `GovernanceSecurityContext`, `StaffMfaService` y `EvidenceAudit`. Todas las referencias profesionales se resuelven en servidor desde **la revisión enviada exacta**. La UI no envía doctor, candidato seleccionado, VID01/CRD02, cuenta beneficiaria ni revisor.

Se conserva la precedencia del matcher: cédula exacta, luego combinación profesional/geográfica completa, luego no match. La consulta no es un emisor de identidad: se exige decisión humana explícita, fuentes verificadas y la revisión final de documentos de C4. Se congelan cuenta y versión de credencial del solicitante, origen original, intake, conjunto/modelo de candidatos, elegibilidad pública/FREE de revisión, perfil propuesto, IDs y hashes de las fuentes.

La capacidad existente `physician_accreditation_review` cubre estas operaciones acotadas de revisión profesional. No se concede administración general ni se añade un grant a personas reales. El revisor requiere cuenta activa con email verificado, credencial/sesión canónicas vigentes, staff activo, grant exacto no revocado, independencia del beneficiario, reauth y MFA actuales. Revisión de medios u ownership no sustituyen esa capacidad.

`begin` crea un caso `PHYSICIAN_ENROLLMENT_REVIEW` de cinco minutos sobre hechos/versión/decisión exactos. Reiniciar expresamente la ceremonia propia, con nueva reauth, revoca el caso anterior sin borrar historia. Un caso activo de otro revisor produce conflicto; los casos vencidos no bloquean para siempre un reinicio legítimo. `verify` sólo admite el desafío del caso/actor/operación correspondientes. `decide` vuelve a calcular hechos, comprueba hashes/versiones/autoridad, consume MFA y añade la decisión/auditoría en el mismo PDO/transacción. Un cambio entre inicio y decisión deniega.

| Estado persistido | Regla |
| --- | --- |
| `PENDING_REVIEW` | Conserva la solicitud para revisión posterior. |
| `NEEDS_INFORMATION` | Requiere información o procedimiento adicional; no inventa una nueva revisión de intake. |
| `PENDING_SOURCES` | Perfil precargado sin fuentes profesionales verificadas suficientes. No hay positivo C4. |
| `PENDING_NEW_PROFILE` | Solicitud general sin coincidencia. No crea un perfil público. |
| `CONFLICT` | Ambigüedad, otro candidato para CLAIM, perfil no elegible, owner o antecedente incompatible. No redirige ni transfiere. |
| `REJECTED` | Resultado terminal conservado. No se reaprueba silenciosamente. |
| `APPROVED` | Perfil existente exacto revisado con fuentes/versiones válidas y attestación humana. Todavía no hay acreditación. |
| `REVOKED` | Retiro gobernado de una resolución aprobada; invalida el handoff y nuevas emisiones/replays de autoridad. |

`IN_REVIEW` es una presentación derivada de un caso abierto, vigente y sobre la versión actual; no se fabrica una aprobación ni se cambia el origen para mostrarla. Los resultados terminales no se reactivan por replay. Correcciones del intake enviado, reapertura terminal, creación de perfil nuevo y conflictos/transferencias requieren su siguiente procedimiento autorizado.

CLAIM conserva siempre el perfil original. Si la cédula apunta a otro perfil se permite registrar conflicto, nunca una resolución positiva hacia ese candidato. FORMAR PARTE conserva `REQUEST_NEW_PROFILE` y original null incluso cuando un revisor aprueba un perfil existente. Un resultado único nunca se acepta automáticamente.

## Handoff a los servicios C4 existentes

`PhysicianEvidenceService::createFromEnrollment` recibe sólo la aplicación de la cuenta autenticada. Bloquea el origen, obtiene la última resolución y la revalida mediante `EnrollmentResolutionAuthority`, exige teléfono canónico verificado y crea **un** draft C4 con relación inmutable a la resolución. Una repetición devuelve el mismo expediente; otra cuenta o relación no se adopta. Creación del expediente, relación y auditoría son atómicas.

`EnrollmentResolutionAuthority` es un lector interno del mismo PDO/transacción: comprueba origen/intake/cuenta exactos, cabeza aprobada, caso consumido, revisor/grant/credencial, factor y proof MFA consumido ligados a resolución, fuentes/revisiones actuales, conflictos de licencia, owner y binding. No usa cookies, nombres o licencia declarada como identidad profesional. La revocación o una nueva decisión invalida la resolución anterior.

`EvidenceAuthority::currentFacts` conserva el caso CLAIM anterior. Añade un caso explícito para expedientes **ligados a una resolución aprobada**; no elimina el control de origen ni convierte FORMAR PARTE en CLAIM. General sin relación sigue denegado. `ProtectedPhysicianEvidencePort::requireConfirmed` añade referencias minimizadas de origen, perfil original/null, intake y resolución/caso/audit; no devuelve documentos, contactos, licencia, claves o proofs brutos.

`ProfessionalIssuanceWorkflow` liga la relación revisada al hash del comando; conserva el hash anterior para expedientes antiguos. `ProfessionalAuthorityIssuer` revalida explícitamente esa relación dentro de su transacción y continúa siendo el único escritor de admisión/binding. Conserva staging, CAS, unicidad, historial, rollback y replay. No se crea otro emisor ni se repiten documentos aceptados.

Siguen obligatorios todos los controles C4-EV01: correo/teléfono y versión de contacto actuales; identidad oficial y cédula privadas cifradas; lectura real de cada archivo exacto por el revisor; attestación de relación cuenta–persona–perfil; MFA/reauth; expiración real de fuentes; confirmación independiente del médico, ligada a sesión/revisión, cinco minutos y un uso; revalidación de revocaciones y auditoría canónica. Un mismo humano autorizado puede revisar el perfil y después los documentos, con la ceremonia propia de cada acción. No se requiere otro revisor ni una segunda carga del mismo documento.

## Esquema preparado y dependencias

La migración aditiva [06](../../modules/identity/db/migrations/2026_10_16_06_reviewed_physician_enrollment.sql) se ensaya después de **01→02→03→04→05**, sobre un clon de esquema sin filas reales. Las cinco migraciones anteriores permanecen byte por byte y ninguna se aplica a `mxmed`.

* `auth_physician_enrollment_resolutions`: decisiones append-only por origen/versión; FKs a origen/cuenta/intake, perfil, VID01/CRD02, staff, caso y proof; unicidad de caso/proof y guardas de transición/origen exacto.
* `auth_physician_enrollment_handoffs`: aplicación/resolución/expediente únicos, inmutables y relacionados por trigger/FKs.
* `auth_physician_evidence_requests.enrollment_resolution_id`: relación opcional e inmutable. Se reemplaza únicamente el CHECK anónimo de forma de 03, localizado por su contrato físico y cantidad exacta, por una alternativa explícita para general resuelto. General sin resolución conserva perfil/fuentes null; general con perfil requiere la relación exacta aprobada. El trigger de origen de 03 permanece y no se actualizan perfiles/fuentes de expedientes existentes.
* Casos/MFA reutilizan una operación enumerada adicional. No cambia el catálogo canónico de eventos: operaciones finitas de resolución usan `PHYSICIAN_EVIDENCE_CHANGED`, target enrollment, actor/capacidad server-side y metadata sin PII/proofs. Un fallo de auditoría revierte también el consumo de MFA.

El source fingerprint de gobierno incorpora 05, 06 y las superficies de enrollment. El manifiesto C4-ACT01 sigue siendo histórico, no un receipt vigente ni un ejecutor de despliegue. Antes de cualquier DDL futuro deben revisarse sus fingerprints/dependencias, principal, copia/restauración y autorización exacta.

## UI original y composición

Se reutilizan perfil/listado público, registro/login/verificación, `/public/identity/perfil-medico.php` y `/internal/media-review/index.php?section=acreditacion`. La sección staff existente añade la cola y decisión de incorporación; muestra información declarada mínima y perfil propuesto, sin IDs técnicos, fuentes documentales, contraseñas o matching sensible al solicitante. El texto visible no usa términos de esquema. Los secretos de reauth/MFA sólo permanecen durante su acción y los formularios usan POST.

El solicitante consulta recibido/pendiente/en revisión/información adicional, aprobación pendiente de confirmación, rechazo y acreditación completada desde respuestas de servidor. Completar sólo se muestra tras la proyección profesional actual. La continuación retoma la aplicación/expediente exactos, sin mover datos entre cuentas o perfiles. El origen público original sigue visible y no cambia al avanzar. La designación como titular continúa indicada como trámite independiente.

Las nuevas opciones de composición son inyectadas y opcionales. **Sólo el router CLI de QA nuevo las instala**, después de verificar nombre de DB, usuario efectivo, entorno explícitamente no normal-local y bandera propia. No hay otro portal, login, dashboard, selector de producto o flag que active normal-local. En 8443 las entradas conservan estado pendiente/deshabilitado y el endpoint de persistencia 503; no se conectan tablas ausentes ni se anuncian envíos reales.

## QA y preservación

Ensayo reproducible, siempre con un directorio privado nuevo:

```sh
python3 scripts/gov03b-disposable-qa.py --suite resolution \
  --connection /ruta/privada/connection.json \
  --evidence /ruta/privada/nueva-pub-enr03
```

El runner crea usuario/DB/Valkey/objetos/claves/HTTPS propios; no importa filas normales y elimina únicamente sus recursos al terminar. El principal de aplicación no tiene DDL ni acceso a `mxmed`. Los perfiles de prueba tienen comercialización FREE explícita sólo en su esquema. Las fuentes profesionales se provisionan mediante los servicios VID01/CRD02 originales, con autoridad y documentos inequívocamente sintéticos del target propio; no son verificaciones o autorizaciones operativas reales.

La aceptación incluye la suite nativa PUB-ENR02 conservada; CLAIM exacto y general resuelto hasta el emisor canónico; fuentes ausentes y revisiones VID/CRD/cuenta cambiadas; self-review, grant revocado, cuentas ajenas; no match/conflicto/owner; attestación y acceso documental obligatorio; confirmación de un uso; replay, procesos PHP concurrentes, caso reemplazado, CAS y rollback de audit; revocación de resolución; guardas SQL y ausencia de owner/firma. Browser: ambos orígenes, Chromium y WebKit, 1440×900, 1366×768 y 820×1180, mediante las mismas plantillas/servicios.

**RESULT=READY_MXMED_PUB_ENR03_REVIEWED_RESOLUTION_HANDOFF_ISOLATED.** Verificado en datos desechables; no es autorización operacional.

| Aceptación ejecutada | Resultado |
| --- | --- |
| Resolución/handoff nativos sobre 01→06 | **89 PASS**, incluidos los 39 contratos PUB-ENR02 conservados. CLAIM y general llegan al mismo emisor; fuentes/documentos/contacto/confirmación siguen obligatorios. |
| Browser final original | **24 grupos PASS, doce recorridos completos**: claim/general × Chromium/WebKit × tres tamaños. Capturas y checks de overflow, texto sin IDs, estado profesional actual y ausencia de owner/firma. |
| Retorno C4 dirigido | **Tres grupos PASS, un recorrido adicional a 820×1180**. Logout canónico seguido del login conserva exactamente `continue_enrollment` y su expediente; también lo conserva el enlace de recuperación de sesión. |
| PUB-ENR02 separado | 39 contratos nativos y 28 grupos browser PASS; origen, cuenta, registro/email, intake, concurrencia y reanudación originales. |
| C4 existente | Evidencia **86 PASS**, emisor **101 PASS** y UI original **45 checks PASS**, cada suite en su propio target nuevo y eliminado. |
| Identity y gobierno | 80 checks normal-local aislados, contratos de sesión privada/SIM02, continuación de notificación y contrato HTTP de gobierno PASS. Sin cambiar TTL. |
| Agenda/Operadores | CONS-VIS01, resolver clínico, OP01 01–38 y ocho casos Readiness PASS; autoridades/concurrencia y fuentes productivas conservadas. |
| Fuente y preservación | 25 archivos de fuente/pruebas/migración fijados en manifest, sintaxis y whitespace PASS. 128 tablas normales y QA05/OP02/UI03 idénticos, configuración/journals conservados, listeners 8091/8092/8443/6385 activos. |

La revisión visual final corrigió una presentación que confundía el binding emitido por la propia solicitud con un conflicto nuevo. La sección staff muestra la vigencia revalidada de la resolución; la continuación del médico muestra su acreditación completada, sin volver a pedir pasos concluidos. Las comprobaciones finales incluyen ambas condiciones. La protección de ID canónico rechaza aliases por collation antes de crear un handoff.

La prueba C4-UI01 anterior comprobaba el ID técnico en texto visible. Ahora comprueba el mismo perfil exacto en el DTO autenticado y además su ausencia del texto; conserva apertura de documentos, MFA, confirmación, emisor, expiración, logout y denegaciones. No se omite la comprobación de scope.

Evidencia privada de esta ejecución: `~/Library/Application Support/MXMED/pub-enr03/20261016-review/`. Los intentos fallidos se conservan separados de `acceptance/`. No se publican conexiones, tokens, claves, documentos o snapshots privados en Git. La revisión física Safari del Director no se ejecuta.

`AUTH02A_29` mantiene el fallo histórico: aplicando exactamente las exclusiones de su gate al HEAD inicial, queda la fixture `modules/clinical/qa/ux_align01_presentation_fixture.php`. Los archivos clínicos productivos y el contrato permanecen iguales al inicio de PUB-ENR03. No se relaja el gate ni se declara PASS su suite completa; las comprobaciones posteriores no alcanzadas no cuentan como ejecutadas. La deuda previa de pin del catálogo de auditoría también permanece separada.

Los snapshots comparan las 128 tablas normales, filas/checksums, conteos, DDL/triggers/rutinas y los esquemas persistentes QA05/OP02/UI03, más configuración/journals privados. No se usa ni modifica la sesión del Director. Los listeners normales 8091/8092/8443/6385 se conservan.

## Gates pendientes

Para perfiles sin fuentes legítimas, el próximo gate es la provisión autorizada por **`VerifiedPhysicianIdentityService::provisionFromTrustedAuthority`** y **`VerifiedDoctorCredentialService::provisionFromTrustedAuthority`/`verify`**, con el aprobador/contexto canónico exigidos por esos servicios. Esta fase no expone un formulario operativo de provisión ni interpreta nombre/licencia legacy como verificados. Hasta entonces se conserva `PENDING_SOURCES`.

Perfil nuevo, conflicto, transferencia, corrección de intake enviado o reapertura terminal necesitan sus procedimientos separados. No son atajos para crear otra identidad, un CLAIM ficticio, un perfil duplicado o un owner.

Para desplegar realmente este flujo siguen pendientes [C4-ACT01](C4-ACT01-operational-activation-package.md) y PUB-ENR02: paquete físico 01→06 revisado, backup/restore y autorización DDL exacta; fuente comercial FREE real; custodia/retención/rotación/recuperación/reconciliación; SMS y documentos legítimos; staff/capacidad/MFA nominados; principal mínimo, protección de abuso/capacidad y compositor operacional con guards de fuente/esquema/cohorte. No se activa ninguno aquí. Ownership, Operadores y autorización clínica requieren sus autorizaciones independientes.
