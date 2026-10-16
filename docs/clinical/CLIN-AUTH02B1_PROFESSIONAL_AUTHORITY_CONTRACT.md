# CLIN-AUTH02B1 — contrato de autoridad profesional y operaciones clínicas

**C4-ADM02-IMP02:** el [emisor canónico y consumidor transaccional](../identity/C4-ADM02-IMP02-physician-authority-issuer.md) ya preparan admisión/binding con evidencia protegida y QA desechable. `requireCurrent` devuelve hechos exactos, no permiso clínico. La integración de sesión/owner/operación/entitlement/scope/intención/auditoría y la activación de firma siguen pendientes; CLIN-AUTH02A no cambia.

**Corrección vinculante GOV-SIM02 (2026-10-16):** owner exclusivamente médico legítimo del perfil; un no médico no es elegible. Ownership inicial rutinario: una aprobación de staff explícitamente autorizado y aceptación independiente del médico, con identidad/cédula/binding aprobados y auditoría. Un owner médico acreditado podrá firmar/emitir por su cuenta sólo tras implementar/probar/activar las verificaciones de operación. Los operadores no médicos nunca reciben creación/emisión de receta ni firma profesional. El esquema y lectura preparada de [GOV-SIM02](../identity/GOV-SIM02-physician-ownership-session.md) no activan firma. Revisión obligatoria de doce meses y recuperación raíz V3: `DEFERRED_NOT_APPROVED`. Se sustituyen las alternativas incompatibles D07; las demás decisiones pendientes no se dan por ratificadas.

Fecha: 2026-10-15. Fuente revisada: `3fc19bc59d5aa9fc73d739035b2f30737bc85168`, rama `ux/consultation-step2-vitals-r1`.

**Estado: diseño documental completo; recomendaciones pendientes de ratificación e implementación separada.** Este documento especializa Identity, Profiles y Clinical; no crea otro modelo maestro de identidad. Ninguna relación, permiso, capacidad, tabla, emisor o cambio de autorización descrito como propuesto está instalado. CLIN-AUTH02A sigue vigente, incluido su rechazo de todas las firmas profesionales sin vinculación comprobada. La revisión fue de archivos y contratos; no consultó ni modificó cuentas, pacientes, sesiones o fixtures de QA05.

## 1. Autoridades documentales y precedencia

| Referencia existente | Requisito o evidencia que se conserva |
|---|---|
| [Guía de ejecución, §6–10, ROLE-POL01 en §10, §13 y C4](../product-completion/MXMED_PRODUCT_COMPLETION_EXECUTION_GUIDE_POST_PP325.md) | Acreditación humana; coincidencia de cédula no basta; invitación y asignación exactas; independencia de ownership, staff y plan; un owner y hasta dos usuarios delegados. C4 figura `NOT_STARTED`. |
| [CLIN-AUTH01](CLIN-AUTH01_CLINICAL_ROLE_AUTHORITY_AUDIT.md) | Inventario completo de rutas, guardas y brechas; referencia histórica A1 RBAC a actos no delegables. Sus descripciones de vulnerabilidades son del estado anterior a 02A. |
| [CLIN-AUTH02A](CLIN-AUTH02A_URGENT_CONTAINMENT.md) | Contención instalada de legacy y firmas/emisiones; alcance exacto, M6, entitlement `prescriptions`, separación de firma de paciente y preservación de fingerprints. |
| [ADM01/DG05C3](../product-completion/dg05c3-designation-governance-readiness.md) | No existe autoridad global de admisión médica ejecutable. ADM02 quedó diferido por el Director. `source_type=admission_approved` es procedencia de una fila, no estado vigente de admisión. |
| [QA02A](../identity/IDENTITY-QA02A-governed-initial-profile-ownership.md), [QA02B](../identity/IDENTITY-QA02B-ownership-approval-step-up-persistence.md), [QA02E](../identity/IDENTITY-QA02E-internal-governance-bootstrap-preparation.md), [GOV03A](../identity/IDENTITY-GOV03A-governance-runtime.md) | Ownership gobernado, evidencia y consumo atómicos, separación de beneficiario/aprobador, MFA y recuperación; preparación no equivale a activación real. |
| [SIG03A](../product-completion/SIG03A-canonical-signature.md), bindings PHP/JS de receta y consentimiento citados en §12 | Imagen de firma y vínculo de contenido/versionado existentes; no acreditan a la persona que controla una cuenta. |

Las invariantes anteriores son requisitos aprobados. Los detalles de persistencia, nuevos permisos operativos, autoridades emisoras, plazos, delegación clínica y cobertura de este documento son **recomendaciones**. Las decisiones D01–D10 delimitan su ratificación. Completar este contrato no autoriza reabrir ADM02, aplicar migraciones, promover owners ni restaurar firmas.

ROLE-POL01 conserva exactamente `owner`, `administrator`, `collaborator`; un administrator consume una de las dos plazas no-owner. No se propone un rol `doctor`, `assistant` o `covering_doctor`. Ser médico es un hecho verificado; asistir o cubrir es una autorización contextual. El personal `DIRECTOR`/`MASTER_ADMIN`/`ADVISOR` ocupa el plano de plataforma, sin plaza de perfil ni acceso clínico implícito. La política de conteo de invitaciones pendientes/suspendidas sigue pendiente; este contrato no la resuelve por inferencia.

## 2. Inventario comprobado y hechos independientes

| Hecho | Fuente canónica existente | Qué demuestra y qué falta |
|---|---|---|
| `PROFESSIONAL_PROFILE_EXISTS` | `profiles_doctors.doctor_id` | Existe un perfil. Publicación, nombre visible, designación y `profile_status` no demuestran identidad del actor ni admisión vigente. |
| Identidad médica verificada del perfil | `profiles_verified_identities`; `VerifiedPhysicianIdentityService` y repositorio | `verified_identity_id`, nombres oficiales y procedencia, uno por `doctor_id`. No hay `subject_account_id`, estado de vinculación o ciclo de revocación de esa persona-cuenta. `verified_by_account_id` es el revisor interno, nunca el médico beneficiario. |
| `PROFESSIONAL_CREDENTIALS_VERIFIED` | `profiles_doctor_credentials`; `VerifiedDoctorCredentialService::resolveVerifiedProfessional` | Cédula `PROFESSIONAL`, `VERIFIED` y `ACTIVE`, con revisor y procedencia; especialidades independientes. La unicidad de cédula es por perfil; no resuelve identidad duplicada entre perfiles. El servicio recibe revisión confiable: no implementa consulta automática a un registro externo ni verificación del titular de la cuenta. |
| `ACCOUNT_AUTHENTICATED` | `auth_accounts`, `auth_account_credentials`, `SessionService`, `CanonicalHttpSessionResolver` | Cuenta y versión de credencial vigentes con sesión canónica. Verificar correo acredita control del correo, no identidad civil/profesional. |
| Relación con el perfil | `auth_account_memberships`, `AccountMembershipRepository` | Cuenta, perfil exacto, rol, alcance y estado. No contiene identidad profesional, prueba del titular, grant clínico granular o aprobación médica. |
| Claim, acreditación y ownership | Guía C4; `PublicProfileController`/`PublicDiscoveryController` (`claim_source_ready=false`); servicios de ownership GOV03A | Hay primitivas ejecutables de ownership gobernado; no existe pipeline médico canónico de admisión/claim/invitación que pueda emitir la vinculación requerida. Claim de organizaciones proveedoras de Agenda no es claim de médico. |
| `ACCOUNT_BOUND_TO_PROFESSIONAL` | Sin fuente canónica encontrada | Búsqueda en servicios, contratos, repositorios y migraciones de Identity/Profiles no encuentra relación durmiente que una cuenta titular a `verified_identity_id` con evidencia y revocación. No se reutilizan columnas de revisor, `assignment_source` o correo como sustituto. |
| `ACTOR_AUTHORIZED_FOR_OPERATION` | `CanonicalClinicalIdentityResolver`, entitlement y guardas de Clinical; límite de 02A | Resolver actual: exactamente una membresía, cuenta/sesión, existencia del perfil y capability comercial. No decide calificación médica o delegación por operación. `clinical_professional_authority_decision` devuelve denegación por falta de vinculación. |

VID01 acepta provisión por `internal_staff` activo de clase Director/Master/Advisor; CRD02 usa comprobación similar y auditoría transaccional. Esas comprobaciones no son un emisor seguro de vinculación de cuentas: no exigen la nueva capacidad específica, no prueban titularidad del beneficiario y no realizan el caso de acreditación propuesto. El catálogo interno revisado tampoco contiene una capacidad de emitir vinculación profesional. Reutilizar los hechos verificados no implica reutilizar esas clases como permiso suficiente.

## 3. Modelo canónico propuesto

La decisión futura es una intersección, calculada en servidor para cada solicitud y revalidada al mutar:

```text
sesión/cuenta/versión de credencial vigentes
AND membresía activa en el perfil seleccionado y autorizado
AND entitlement de la operación
AND alcance exacto de paciente/encuentro/documento
AND (
  acto profesional: vinculación profesional ACTIVE + evidencia vigente
                   + cédula profesional VERIFIED/ACTIVE + operación permitida
  O acto delegable: grant explícito vigente de esa operación y ese alcance
)
AND reglas clínicas de estado, versión, idempotencia, firma y M6
```

Una rama de preparación nunca satisface una operación de validación, firma o emisión. Ausencia, ambigüedad, fuente no disponible, permiso desconocido o conflicto deniegan. El campo `role_code` sirve para la relación de equipo/administración; no es prueba profesional. Los identificadores recibidos se contrastan contra registros de servidor, no seleccionan autoridad por sí solos.

La identidad profesional inicial será el `verified_identity_id` existente, asociado al mismo `doctor_id` de su cédula y membresía. La primera implementación recomendada admite **sólo al profesional titular de ese perfil**. Vinculación, membresía, admisión y credencial mantienen estados separados. El resultado profesional será request-scoped y no se guardará como autoridad en `PHPSESSID`, navegador, JWT libre o contexto comercial.

El contrato de decisión propuesto devuelve `allowed`, motivo estable, operación y versión de política, `account_id`, `session_id`, `membership_id`, perfil de práctica, identidad profesional, binding/versión y cédulas pertinentes, alcance P/E/D, fuente de aprobación y vigencia. Para un delegado devuelve el grant/versión y el revisor responsable en lugar de fingir una identidad médica. La capa HTTP expone sólo el mensaje mínimo; el detalle queda en auditoría. Operación ausente/desconocida se deniega. `authentication_required` corresponde a 401; prueba/grant/capability insuficientes a 403; recurso fuera de alcance a 404 sin revelar existencia; autoridad no disponible a 503 sin fallback. Una decisión permitida es específica a esa operación y solicitud, nunca un permiso reutilizable recibido del cliente.

La existencia de una identidad VID01 no prueba que el médico esté admitido. La emisión de una vinculación requiere evidencia explícita de acreditación del titular. Recomiendo resolver esa evidencia mediante el futuro contrato C4/ADM02, sin tratar publicación, pago o `admission_approved` como aprobación vigente. Si ese contrato sigue diferido, la vinculación permanece `PENDING` y las firmas siguen denegadas. Una ceremonia excepcional de onboarding requeriría otra autorización y evidencia gobernada equivalente; no se propone como atajo operativo en esta fase.

## 4. Vinculación: persistencia mínima, evidencia e invariantes

**Esquema nuevo requerido para una implementación futura: sí. Esquema aplicado aquí: ninguno.** Agregar `account_id` a membresías no alcanza (ya existe) y agregarlo a la identidad del perfil mezclaría la persona representada con la cuenta que puede recuperarse/reemplazarse. Tampoco hay un campo JSON genérico con semántica suficiente. Recomiendo una sola relación de Identity para la primera fase, nombre conceptual `auth_account_professional_bindings`, pendiente de revisión de migración. No se duplica nombre, cédula, contraseña ni maestro de personas.

| Grupo de campos propuestos | Contrato |
|---|---|
| Identificadores | `binding_id` estable, `account_id` FK a cuenta, `verified_identity_id` FK a VID01, `profile_doctor_id` FK a perfil, `professional_credential_id` FK a CRD02. Las tres referencias profesionales deben corresponder al mismo perfil. |
| Estado y concurrencia | `status`, revisión monotónica `authority_version`, `created_at`, `updated_at`; compare-and-swap y bloqueo en emisión/cambio. Ningún update debe cambiar el sujeto o perfil de un binding ya emitido. |
| Decisión | Caso/decisión de verificación exactos, emisor interno y beneficiario distintos, timestamp, reason code, referencia de evidencia protegida y revisión de evidencia. Referencias auditables, no documentos personales ni secretos embebidos en la fila o logs. |
| Titularidad de cuenta | Evidencia de comprobación del titular enlazada al caso y a la sesión canónica del beneficiario; referencia a su aceptación y reautenticación. No basta tener el correo abierto. La prueba de step-up se consume, no se conserva clara. |
| Vigencia | `valid_from`, `reverify_at` obligatorio según política aprobada, `expires_at` si aplica; todos en UTC de servidor. Fuente, revisión de credencial y evidencia aceptadas quedan referenciadas para detectar cambios. |
| Suspensión y revocación | Timestamp, actor, motivo y caso para suspensión/revocación; historial mediante auditoría canónica. Estado revocado terminal; reincorporación usa nueva decisión/binding y conserva el anterior. |

En fase inicial: a lo sumo un binding efectivo para `(account_id, profile_doctor_id)` y una sola cuenta activa vinculada a cada identidad verificada. Emisión y recuperación serializan la identidad y el perfil; restricciones físicas deben impedir dobles altas concurrentes. `PENDING`/`SUSPENDED` reservan el candidato bajo revisión; conflictos bloquean activación. Ninguna cuenta se vincula a dos personas distintas; varios perfiles del mismo titular requieren evidencia independiente de equivalencia y ámbito, nunca concatenar nombres/cédulas para inferirla. El diseño físico definitivo debe probar esas invariantes; no basta un índice que distinga `status` y permita múltiples filas efectivas.

VID01 no tiene versión ni ciclo de revocación y su servicio actual crea una sola identidad por perfil. Por eso una corrección de identidad no se representa reescribiendo nombres desde el navegador: la futura revisión gobernada deberá invalidar vínculos dependientes y emitir nueva evidencia. CRD02 tiene `updated_at`, pero no una revisión dedicada: la vinculación debe fijar la revisión/evidencia aceptada y comparar los hechos relevantes en servidor. Cambiar cédula, titular, verificación, procedencia o restricciones profesionales suspende el uso hasta reverificación; editar logo, dirección o nombre público no cambia identidad. Un cambio de especialidad suspende las operaciones que dependan de esa especialidad; el resto exige decisión explícita, sin ampliación automática.

El primer esquema de binding no implementa permisos de asistentes ni cobertura. Si se autorizan después, se necesita persistencia adicional de grants ligada a `membership_id` dentro de la autoridad de asignaciones existente; no columnas libres que representen roles clínicos, ni reutilizar `internal_operator_grants` para usuarios de consultorio. La migración de esa extensión y de metadatos de autorización de firma se decidirá al implementar esos alcances, por separado del mínimo inicial.

## 5. Emisión, ciclo de vida, revocación y auditoría

Estados propuestos: `PENDING`, `ACTIVE`, `SUSPENDED`, `REVOKED`, `REJECTED`, `EXPIRED`. Sólo `ACTIVE` dentro de vigencia autoriza; `reverify_at` vencido también deniega aunque todavía no se haya ejecutado un proceso de expiración. `REJECTED`, `REVOKED` y `EXPIRED` conservan evidencia terminal. `SUSPENDED → ACTIVE` exige nueva revisión, aceptación cuando corresponda y aumento de `authority_version`; nunca desbloqueo por login.

| Transición | Autoridad y evidencia recomendadas |
|---|---|
| Solicitar `PENDING` | Cuenta canónica activa presenta expediente; caso enlaza cuenta, identidad profesional, perfil y cédula exactos. Ser owner no crea la solicitud automáticamente. |
| `PENDING → ACTIVE` | Revisor de acreditación autorizado con capacidad específica **propuesta** `professional_binding_issue`, cuenta/staff activos, MFA reciente, caso sin conflicto y evidencia del titular; aceptación con reautenticación del beneficiario. Revalidación y auditoría atómicas. Capacidad aún ausente del catálogo. |
| Aprobación normal / conflicto | Reutilizar la regla histórica de acreditación: un revisor independiente en caso normal; segunda aprobación independiente en conflicto/transferencia/excepción. GOV03A ownership sigue R3 y conserva siempre su doble aprobación; no se rebaja su política. Ratificar aplicación exacta a bindings en D02. |
| Suspender | Autoridad de verificación con capacidad específica propuesta `professional_binding_suspend`; titular puede solicitar suspensión propia y revocar sus sesiones. Riesgo de cuenta, credencial revocada/inactiva, identidad impugnada o revisión vencida bloquean efectividad de inmediato, sin esperar una notificación. |
| Revocar | Autoridad independiente de verificación/seguridad con capacidad propuesta `professional_binding_revoke`, motivo y evidencia; titular puede renunciar a su propio vínculo sin poder reemitirlo. Revocación administrativa no exige aprobación del beneficiario. |
| Recuperar cuenta | Recovery de Identity prueba control de cuenta según su contrato. Si cambia la cuenta titular, nueva verificación de persona y caso de conflicto/recuperación, revocación atómica del binding anterior antes de activar el nuevo; sin migración de sesiones o firmas ni cambio de owner implícito. |
| Renovar o rechazar | Revisor independiente reconsulta evidencia y fuentes vigentes; registra revisión/motivo. No se reactivan credenciales CRD02 revocadas mediante binding. Se usa su transición canónica autorizada o un nuevo registro revisado. |

La clase de staff permite participar en un plano; no concede las capacidades propuestas. Quien emite debe ser distinto del beneficiario; un médico con funciones internas no aprueba su propia vinculación. Sólo el owner médico puede aprobar los aspectos administrativos del equipo dentro de sus permisos; no se admite un owner administrativo no médico y la incorporación administrativa no certifica cédulas o identidad profesional. Un médico puede aprobar la preparación clínica bajo su responsabilidad, pero no emitirse su identidad.

Evidencia mínima: identificación del titular mediante revisión documental y comprobación presencial o equivalente aprobado; contraste de nombre oficial y cédula con fuente de verificación identificada; método/fecha/revisor/resultado; discrepancias resueltas; exactitud de cuenta y perfil; consentimiento del titular y reautenticación; decisión de acreditación aplicable. El método remoto equivalente y sus garantías de presencia son D01; correo, una licencia digitada o una firma dibujada no lo sustituyen. Los documentos se custodian en almacenamiento privado con acceso limitado y retención aprobada; la auditoría sólo lleva referencias mínimas.

Emitir/cambiar binding y aceptar la evidencia debe ser una transacción con `CanonicalAuditWriter` y adaptador unido. Eventos propuestos de solicitud, activación, suspensión, revocación, rechazo, expiración y reverificación necesitan alta explícita en catálogos/policies/sanitizadores; el registro actual es cerrado y no admite nombres arbitrarios. Incluir actor real, sesión/revisión de credencial, caso, binding y versión, cuenta beneficiaria, perfil, identidad/cédula referenciadas, before/after, reason, resultado y correlación. Fallar auditoría revierte el alta o cambio; secretos, token QR, imagen de firma e identificación personal no van a metadata. Retención y acceso se ratifican en D03.

La revocación del binding no borra la cuenta, membresía o documentos emitidos. La revocación de membresía elimina el acceso a ese perfil, aunque la identidad de la persona siga probada. El bloqueo de cuenta/session deniega todas las operaciones aplicables. Un cambio de owner no transfiere ni cancela automáticamente la identidad profesional; su relación de práctica se reevalúa separadamente. Las autorizaciones clínicas deben consultar estado vigente, no una copia cacheada en sesión. No existe período de gracia para firmar tras revocación.

## 6. Matriz de operaciones clínicas propuesta

La matriz es la política recomendada para ratificar; **no describe permisos instalados**. Todos los renglones privados requieren sesión canónica y contexto explícito. `R` = membresía exacta activa; `P` = paciente ligado al perfil; `E` = encuentro de ese paciente y perfil, con lifecycle permitido; `D` = documento/versión/creador y linaje exactos. Ausencia legítima de encuentro en un documento longitudinal se conserva, sin inventar uno. `V` = binding profesional activo y cédula verificada/vigente, evidencia y habilitación para el acto; incluye especialidad sólo cuando el acto la exige. `G` = grant explícito propuesto, temporal, revocable y limitado a operaciones/campos/finalidad. `CR` = entitlement existente `clinical_record`; `RX` = `prescriptions`; `AG` = capability de Agenda que corresponda a la operación, resuelta por su catálogo, nunca por un nombre inventado.

`G` no está implementado. No se admite wildcard clínico, herencia por rol, re-delegación o grant que permita emitir/finalizar una afirmación profesional. Para preparación, recomiendo aprobación administrativa de asignación por owner/administrador autorizado **y** aprobación de alcance clínico por V del perfil; revisión final por V. El grant guarda quién prepara y quién valida, vigencia, campos, pacientes/encuentros y finalidad. La elección final de aprobadores y operaciones es D04/D05. Distinguir `save_proposal` de `validate`/`finalize` en el comando, no sólo en UI.

| Operación / clase | Profesional requerido | Relación y alcance | Entitlement | Delegación / aprobación y revisión | Quién finaliza / firma | Enforcement instalado en 02A | Guarda pendiente |
|---|---|---|---|---|---|---|---|
| Consulta: datos de cita y coordinación / administrativa | No para agenda mínima | Asignación Agenda exacta; paciente/cita mínimos | AG | Sí, G administrativo según contrato de Agenda | Operador cierra tarea administrativa; no consulta | Autoridad Agenda separada | Verificar grant administrativo; no abrir expediente por asignación de agenda |
| Iniciar consulta / flujo clínico | V para iniciar acto médico | R+P; cita doctor/paciente si existe | CR | Propuesta de preparación de sala como tarea separada; no apertura médica por defecto | V inicia atención | Resolver + P/cita/idempotencia, sin V | V al inicio; delimitar preparación D04 |
| Continuar consulta: leer expediente / sensible | V o G de lectura explícita | R+P+E; finalidad vigente | CR | G sólo subconjunto aprobado D05 | V valida atención | Alcance E/P, mismo acceso por roles | Operación y proyección de lectura |
| Finalizar/anular consulta o nota final / profesional | V | R+P+E y lifecycle | CR | No por miembro no profesional | V; auditar razón y autor | Integridad terminal e inmutabilidad; sin V por operación | V antes de transacción de cierre/anulación |
| Historia: capturar relato del paciente / preparación | V o G acotado | R+P; E cuando aplique | CR | Candidato D04: transcribir relato, procedencia visible, revisión V; sin validar antecedentes | V valida; preparador sólo guarda propuesta | Legacy autenticado; M6 conserva retiros | G de campos y estado propuesto; ningún legacy retirado se reactiva |
| Historia: validar antecedentes/revisión clínica / profesional | V | R+P+E si corresponde | CR | No; aportes del paciente permanecen sin validar | V | Alcance, idempotencia; sin V general | V y atribución de validación |
| Exploración física / profesional | V | R+P+E | CR | No delegación de hallazgos/validación; ayuda mecánica no publica hallazgos | V | Legacy con scope y M6; secciones con integridad | V para aseveración física; preservar writer vigente |
| Signos vitales: captura/corrección técnica / preparación | V o G de captura | R+P+E abierto; fuente, unidad, hora, versión | CR | Candidato D04: captura medida y corrección técnica con razón; revisión V | V interpreta; preparador no valida diagnóstico | Mecánica estructurada y scope; sin G | G granular, trazabilidad y revisión; no edición de interpretación |
| Diagnóstico/problemas: confirmar, resolver o cambiar estado clínico / profesional | V | R+P+E si promoción desde consulta | CR | No; transcripción propuesta en canal separado sólo si D04 | V | Scope/estados/idempotencia; sin V | V por transición profesional |
| Notas: evolución, valoración o enmienda clínica / profesional | V | R+P+E+D; postcierre sólo enmienda admitida | CR | No autoría independiente; transcripción de dictado requeriría D04, sin firma | V | Secciones/revisiones y lineage; firma del documento versionado denegada | V en secciones/enmiendas/cierre; no confundir borrador con delegación aprobada |
| Assessment y plan / profesional | V | R+P+E | CR | No decisión/tratamiento por delegado | V | Integridad/scope; sin V general | V en escritura/validación clínica |
| Órdenes: indicación, firma, emisión, cambio clínico / profesional | V | R+P+E o D longitudinal permitido | CR | No selección independiente de estudio/indicación; preparación logística fuera de orden | V | Clases de documento, scope y estado; emisión no cubierta por V general | V en orden, corrección/anulación profesional; no asumir que unsigned es administrativo |
| Resultados: adjuntar archivo externo / preparación | V o G de ingestión | R+P+D; E/orden de origen exactos si vinculados | CR | Candidato D04: cargar evidencia sin interpretación; revisión V | Preparador adjunta evidencia; V valida clínicamente | MIME/privacidad/origen/lineage; roles amplios | G de carga y campo, distinguir resultado externo de validado |
| Resultados: interpretar, concluir, validar o corregir conclusión / profesional | V | R+P+E/D; fuente intacta | CR | No | V | Scope/lineage; sin V general | V y estado de validación separados del upload |
| Receta: borrador, edición de fármacos/dosis/indicaciones / profesional por defecto | V | R+P+D; E exacto si explícito | RX | No autorización nueva a asistentes: A1 histórico es restrictivo; D04 puede aprobar sólo propuesta separada | V adopta, finaliza y firma | 02A permite borrador por miembro con RX, creator/version; deuda conocida | V o propuesta explícitamente ratificada; handoff ausente, no suplantar creator |
| Receta: contexto, vista previa e historial / lectura sensible | V o G separado D05 | R+P+D y alcance autor existente | RX; CR adicional sólo si se lee expediente | Lectura mínima/reimpresión, no edición, con finalidad | Sin finalización | RX corregido, scope y creator en drafts/history | G de lectura y proyección; mantener restricciones actuales hasta aprobación |
| Receta: preparar/aplicar firma local o registrada / profesional | V | R+P+D+E si existe; actor real y snapshot exactos | RX | No; imagen almacenada no delega | Mismo V titular del documento | 403 por límite profesional 02A | Resolver binding y prueba de intención antes de artefacto |
| Receta: emitir/finalizar / profesional | V actual, no sólo al firmar | R+P+D y binding/firma vigentes | RX | No | Mismo V que firma | 403 antes de writer | Revalidación atómica; revocación y firma anterior no dan gracia |
| Receta: anular / profesional | V | R+P+D, creador original mientras rija contrato | RX | No | V autor; cobertura correctiva requiere D06 | 403 antes de transacción; motivo/estado/lineage retenidos | V y autoría vigente; no levantar creator sin contrato |
| Receta: reemitir / profesional | V | R+P+D, original/reemplazo y creator | RX | No | V firma el nuevo documento | 403; relación atómica preservada | Nueva autorización, versión/firma; original inmutable |
| Consentimiento: preparación administrativa/datos del firmante / preparación | V o G específico | R+P+D; E/cita exactos si existen | CR | Candidato D04; revisar identidad/representación; no declarar explicación médica realizada | V valida contenido médico | Draft y firma paciente separados, scope/adjuntos/versión | G acotado y atribución; no reutilizar firma médica |
| Consentimiento: riesgos/beneficios/alternativas y confirmación informada / profesional | V | R+P+D+E si aplica | CR | No declaración por delegado | V explica/valida | Borradores amplios; final grant denegado | V para aseveración informada, además de firma |
| Consentimiento: firma del médico/finalización / profesional | V | R+P+D; identidad y versión exactas | CR | No | V responsable, con firma paciente/representante válida | 403, antes de staging/transacción | Binding y revalidación sin alterar fingerprint |
| Firma paciente/representante / participante | No V por esa firma | Identidad/calidad del firmante y documento exactos, relación/representación | Entitlement del flujo originante CR | Participante consiente por sí; personal sólo facilita, no firma en su lugar | Participante firma su voluntad; V finaliza el acto médico | QR/revisión/TTL/binding separados | No usar binding médico como identidad del paciente; cualquier mejora de prueba del participante requiere contrato propio |
| Documentos versionados: borrador sin firma / preparación según contenido | V, salvo G de campos ratificado | R+P+D; E si existe | CR | Datos mecánicos candidatos; nota/certificado/alta/informe/interconsulta con conclusiones siguen profesionales | V valida y firma | Drafts permitidos, firmas/emisión 403 | Política por tipo/campo; no otorgar todo documento como preparación |
| Documentos profesionales: firma/emisión/enmienda clínica / profesional | V | R+P+D+E si existe; signer real | CR | No | V; respetar inmutabilidad, nueva versión/enmienda | 02A bloquea firma/emisión versionadas, no toda enmienda genérica | V también en alternativas genéricas y revisiones; no bypass por tipo/alias |
| Adjuntos clínicos sensibles: ver/descargar / lectura sensible | V o G dedicado D05 | R+P+D, manifest y finalidad exactos | CR | Permiso distinto de upload/lista; exportación masiva denegada por defecto | Sin firma; acceso auditado | Binarios privados con scope/hash; sin distinción granular | Proyección, permiso de descarga y auditoría; metadata no revela existencia |
| Historial clínico longitudinal / lectura sensible | V o G de subconjunto D05 | R+P; E/documentos pertenecen al ámbito | CR, RX para historia de recetas | Sin acceso global por rol; selección mínima por tarea | Sin firma | Scope exacto, resolver comercial común a roles | Autoridad de lectura; finalidad, vigencia y mínimo necesario |
| Documento emitido: reimprimir/entregar / administrativa sensible | V o G de reimpresión | R+P+D, destinatario y documento exactos | CR o RX según documento | Candidato con auditoría según A1; no edición/firma/reescritura | Operador entrega copia inmutable | Lecturas existentes protegidas, sin G de reimpresión | Grant separado, registro de entrega, no acceso a expediente completo |

Una escritura puede finalizar una afirmación profesional aunque no adjunte firma: diagnóstico, orden, assessment/plan, cierre y enmienda se tratan por su semántica. Las futuras guardas deben cubrir todos sus entry points; dejar estas brechas como deuda documentada no las declara resueltas por 02A.

Lecturas separadas: (1) Agenda mínima, sin datos clínicos; (2) cola/tarea con metadata mínima; (3) contexto clínico seleccionado para preparación; (4) expediente/historial; (5) contenido de anexos de identidad o clínicos; (6) descarga/reimpresión/entrega; (7) exportación masiva. Cada una requiere permiso y proyección distintos cuando no actúa V. Un grant de upload no concede descarga; receta no concede todos los anexos del expediente; soporte interno no concede ninguno. Los eventos de lectura deben minimizar identificadores y registrar finalidad/operación, sin volcar contenidos.

## 7. Escenarios reales y cobertura

| Escenario | Decisión del contrato propuesto | Disponibilidad actual / dependencia |
|---|---|---|
| A. Médico verificado y owner | Owner conserva administración; sólo binding, cédula y alcance le permiten ejercer y firmar | Sigue 403 hasta emisión legítima del binding; owner no es migración automática |
| B. Candidato no médico a owner | Denegado por GOV-SIM02; un administrador/operador no médico sólo ocupa una de las dos plazas no-owner autorizadas | No es un caso normal/aprobado de ownership médico; sin firma, emisión o creación de receta |
| C. Administrator delegado no médico | Consume una de dos plazas; administración explícita; sólo preparación/lecturas con G ratificado | No firma; G clínico todavía inexistente |
| D. Collaborator verificado como médico | Calificación independiente no autoriza actuar bajo otra identidad; si es titular del perfil exacto y se prueba binding, puede tener V sin volverse owner | Preserva rol de la Directora. Otro médico necesita D06 y atribución propia |
| E. Asistente no profesional | Sólo preparación acotada ratificada, autoría real y revisión V; no diagnóstico, prescripción ni firma | No interpretar drafts hoy permitidos como G concedido; handoff pendiente |
| F. Médico suplente | Relación profesional temporal explícita con titular y perfil receptor; actúa con sus propias credenciales/firma | Bloqueado en mínimo inicial; no impersonación ni compartir cuenta/imagen |
| G. Binding revocado | Denegación inmediata de actos profesionales, QR pendientes y emisión; permisos administrativos independientes no crecen | Históricos permanecen; recuperación no reactiva vínculo terminal |
| H. Cédula necesita revalidación | Cédula no verificada/vigente o evidencia vencida: V falso, suspensión/revisión con motivo | Sin gracia de firma; no convertir `ACTIVE` de cuenta en habilitación profesional |
| I. Varios contextos legítimos | Misma cuenta, relaciones independientes y selector validado en servidor; ningún permiso heredado | Resolver actual rechaza más de una membresía (`membership_ambiguous`); seguir así hasta extensión ratificada D06/D08 |
| J. Advisor interno en soporte | Caso/capacidad/tiempo de plataforma, actor real; sin P/E/documentos/firma ni plaza de perfil por ser staff | GOV03A no emite V. Persona con doble función usa relación clínica independiente, nunca contexto de soporte |

Cobertura futura recomendada: profesional suplente con binding propio y cédulas propias, membresía exacta del perfil receptor dentro de las dos plazas y autorización temporal adicional que referencia binding origen, perfil receptor, aprobador profesional, inicio/fin, pacientes/encuentros y operaciones. El owner/administrador autoriza la incorporación administrativa; el profesional responsable autoriza cobertura médica y el suplente acepta con step-up. Si el titular no puede aprobar, una recuperación/contingencia gobernada requiere decisión separada; no el administrador por sí solo. Expiración/revocación deniega nuevas firmas y QR, conserva históricos.

La cobertura no crea una plaza adicional. Si las dos plazas están ocupadas no se incorpora un tercer delegado encubierto; el Director debe decidir la gestión temporal de plazas y continuidad sin eludir ROLE-POL01. Tampoco se reutiliza el perfil del titular como identidad del suplente. Los snapshots y bindings actuales combinan `doctor_id` del ámbito con nombre/cédula del titular y el account actor. Permitir otro médico sin distinguir práctica y firmante causaría falsa atribución. Cobertura exige extensión explícita de procedencia/snapshot y pruebas de documentos nuevos; si el fingerprint vigente no puede expresar esa distinción, se mantiene bloqueada y se solicita contrato versionado aparte. Este diseño no modifica serialización histórica ni autoriza esa versión.

## 8. Firma y transición sensible

Cadena futura para titular del mismo perfil: `account_id` autenticado → binding activo y versión → VID01 exacta → cédula CRD02 vigente → perfil/membresía → operación → P/E/D → versión de documento/fingerprint → sesión/artefacto de firma → decisión y writer canónicos. Comparar snapshot del documento con hechos profesionales aceptados por servidor. Cédula/nombre heredados de presentación no son fallback de autoridad. Una discrepancia invalida el borrador para nueva firma y obliga a revisión; no reescribe un documento emitido.

| Frontera | Revalidación requerida para la futura implementación |
|---|---|
| Abrir editor/preview | Puede leer/preparar sólo según matriz; presentar que firma no está habilitada cuando falte V. UI no concede permiso. No imprimir una receta unsigned como sustituto de emisión. |
| Firma local | V actual, cuenta del actor exacta y confirmación de intención/reautenticación fresca propuesta en D09; documento y versión actuales, firma sobre fingerprint existente. Imagen de canvas no acredita titular. |
| Firma registrada | Las mismas guardas más checksum/propiedad SIG03A y vinculación al profesional; revisar su endpoint legado `session_scope` al implementar. Una imagen del perfil no permite firmar a otro miembro. |
| Crear QR médico | V y entitlement actuales; guardar actor, sesión originante, binding/versión, operación, P/E/D y fingerprint exactos antes de crear token. Mantener TTL máximo y reglas actuales, sin alargarlos para facilitar QA. |
| Abrir/revisar QR | El token continúa como handoff limitado a una versión y participante; no concede autoridad clínica ni acceso general. Su posesión no prueba que quien tiene el teléfono sea el médico. |
| Completar firma QR médico | Recomiendo exigir sesión Identity propia en el teléfono del **mismo account_id** y confirmación fresca, además del token y autoridad actual del actor almacenado. No asumir cookies del escritorio. Validar tanto sesión originante no revocada como sesión firmante; persistir referencias separadas y negar sustitución. Este cambio de UX/semántica requiere D09 y futura adaptación; hoy no está implementado. |
| Emitir | Revalidar V/membresía/cédula/entitlement/sesión y versiones dentro de la transición; autor que firma = autor que emite en mínimo inicial; validar fingerprint, artefacto, TTL/replay, patient/encounter y firma actual antes de consumir autorización/idempotencia. |
| Anular/reemitir | V fresco y alcance de creator original; motivo y lifecycle existentes. Reemisión crea autorización y firma nuevas, preserva linaje y no modifica original salvo transición canónica de lifecycle. Corrección por suplente no habilitada sin D06. |
| Médico en consentimiento/otros documentos | Misma composición de V y revalidación; paciente/representante mantiene su flujo y prueba independientes. Firmar por paciente no obtiene V ni permite final grant. |

El QR no resuelve conectividad: `localhost` del teléfono no es el Mac. La selección de origen accesible, TLS y exposición pertenece a la composición de entorno aprobada; queda fuera de este contrato y no debe relajarse para obtener una prueba positiva.

Recomiendo registrar una evidencia lateral de decisión de autoridad, fuera de la proyección clínica: binding/versión y referencias de identidad/cédula, actor/sesión, operación, scope, document UUID/version, fingerprint ya calculado y resultado. Usar la auditoría transaccional existente y, para QR, metadata de su sesión; la necesidad de columnas adicionales se evalúa en la migración futura. No agregar esas claves a la serialización PHP/JS del fingerprint ni reinterpretar `doctor_id|account_id`. Una firma de borrador anterior a la activación del binding no recibe autoridad retroactiva: requerirá nueva confirmación y firma autorizadas sobre la versión actual.

No se autoriza un resultado con una decisión vieja. El servicio de transición debe bloquear/consultar las filas de binding, membresía, cédula y documento y verificar versiones nuevamente antes de commit; los cambios de autoridad usan el mismo orden de locks. Validar sesión con `SessionService` antes de ejecución y otra vez en la frontera final cuando haya handoff/demora; nunca escribir una copia de autoridad en PHP. Valkey y SQL no comparten transacción: la implementación debe definir el punto de orden de revocación, comprobación y commit y probar carreras; no prometer atomicidad distribuida con un `SELECT FOR UPDATE`. Si no puede demostrar la garantía de revocación exigida, la transición queda denegada y su liberación bloqueada.

Históricos: se siguen verificando con su versión y golden vectors originales, sin backfill de autoridad ni resignado masivo. Una revocación actual no cambia la evidencia de quién firmó históricamente; afecta operaciones nuevas. Lectura/reimpresión sigue su propio permiso, y una firma históricamente verificable no autoriza una nueva emisión.

## 9. Onboarding, invitación y cuentas existentes

| Entrada | Reutilización recomendada y límite |
|---|---|
| Claim de perfil existente | C4: conservar perfil origen; matching de cédula orienta revisión humana; acreditar persona y control de cuenta, luego emitir binding. Claim y ownership se deciden por sus propias autoridades; ninguno sustituye al otro. Pipeline médico todavía ausente. |
| Invitación legítima | Reutilizar cuenta Identity existente, aceptar perfil/rol/alcance exactos y comprobar plazas. Invitación no prueba profesión: iniciar binding independiente sólo si el invitado ejercerá. No crear credenciales temporales ni otra cuenta por comodidad. |
| Profesional ya verificado en VID01/CRD02 | Referenciar registros existentes; revisar evidencia vigente y titularidad de la cuenta. No reinsertar identidad/cédula ni vincular por nombre/email. Evidencia insuficiente deja `PENDING`. |
| Cuenta ya registrada | Conservar cuenta, contraseña, consents, membresía y rol; presentar ruta de verificación profesional. Sesión activa no completa la prueba del titular. Ningún binding automático en despliegue. |
| Pérdida de acceso a cuenta antigua | Preferir Recovery normal. Si requiere otra cuenta, caso excepcional con antiimpersonación, segunda revisión y revocación del vínculo anterior; informar por canales verificados sin filtrar documentos. Ownership se trata separadamente. |
| Transferencia de ownership administrativo | GOV03A/claim aprobado; no copiar binding/cédula/firma del owner saliente al entrante. La continuidad médica depende de relaciones profesionales independientes. |
| Cese profesional | Suspender/revocar relación profesional y QR pendientes; conservar trazabilidad e históricos. Revocar acceso de práctica cuando corresponda por su servicio; no borrar cuenta o promover a otra persona. |

La Directora conserva `collaborator`; este documento no prueba ni crea su binding y no presupone estado vigente de sus credenciales/admisión a partir de su nombre o del contexto previo. Se deberá verificar por el proceso ratificado, con la misma regla que cualquier profesional. QA05 permanece exclusivamente administrativo; una fixture owner/admin no se convierte en médico para obtener verde en firma.

## 10. Decisiones pendientes del Director

Cada fila distingue alternativa, recomendación, consecuencia y futuro alcance; todas requieren ratificación antes de implementarse. Las invariantes históricas de separación, seguridad y plazas no se someten nuevamente a aprobación.

| ID / contrato existente | Opciones | Recomendación mínima | Seguridad y UX | Alcance futuro / aprobación |
|---|---|---|---|---|
| D01. VID01/CRD02 + C4/ADM02 diferido | Reanudar acreditación canónica; o definir ceremonia excepcional gobernada por separado | Alinear con C4/ADM02 y prueba explícita de titular; sin activar mientras siga diferido | Evita inventar admisión desde procedencia; usuario ve verificación pendiente, no éxito falso | Contrato de evidencia, método de presencia, onboarding/caso y revisión; sí |
| D02. Revisor de acreditación / GOV03A | Una revisión normal y dos excepcionales; o doble revisión siempre | Regla histórica de acreditación, independiente del beneficiario, capacidad específica y MFA; ownership conserva R3 | Impide staff genérico/autoconcesión; más pasos al conflicto | Emisor/revocador, catálogo interno y step-up exactos; sí |
| D03. Relación mínima / auditoría canónica | Columna sobre identidad o membresía; o relación separada con historial | Una relación de binding, referencias existentes, versiones y auditoría unida | Recuperación sin cambiar identidad/roles; requiere migración ensayada, retención de evidencia y acceso definidos | Persistencia, constraints, eventos/policies, retención; sí; ninguna migración aquí |
| D04. A1 restrictivo + ROLE-POL01 flexible | Cero preparación delegada; o lista de campos no profesionales; incluir propuestas de receta sólo con ratificación expresa | Empezar sin delegación clínica nueva; candidatos acotados: vitals, relato, carga externa y logística de consentimiento | No valida diagnóstico/tratamiento; asistentes verán tareas limitadas y revisión pendiente | Grants por membresía, permisos/campos/estados y UI de revisión; sí |
| D05. Lecturas clínicas y reimpresión | V solamente; o G por finalidad/proyección/descarga | V por defecto; G explícito sólo tras ratificar clases de §6 | Evita expediente completo para agenda/upload; delimita qué ve un delegado | Proyecciones, grants, auditoría, exportación/entrega; sí |
| D06. Cobertura y autoría | Mantener titular único; o suplente con relación y snapshots propios | Diferir cobertura; no caber en snapshot actual implica bloqueo | Evita firma bajo cédula ajena; plazas y continuidad deben resolverse | Relación temporal, doble aprobación funcional, identidad de firmante, documentos/versiones nuevas y eventual nueva versión de contrato aparte; sí |
| D07. Owner médico | GOV-SIM02 resuelve: sólo el médico legítimo del perfil | No médicos denegados; administrador dentro de dos plazas no-owner | Owner no sustituye binding/credenciales ni concede firma por sí solo | Política aprobada; emisor/migración y activación pendientes separadamente |
| D08. Múltiples perfiles | Mantener resolver de una membresía; o selector de contexto con verificación exacta | Primera liberación de titular con una membresía; fase posterior para selector | Ambigüedad sigue denegada; cuenta no necesita duplicarse | Selector servidor, aislamiento de grants/sesiones y locks; sí |
| D09. Firma y QR | Mantener bearer como prueba de intención; o autenticar mismo actor en móvil y confirmar | Para nuevo QR médico, mismo account autenticado y confirmación reciente; reautenticación de firma propuesta de cinco minutos, sujeta a aprobación | QR robado no basta; teléfono necesita login y origen accesible; no cambia firma paciente | Metadata de sesión/decisión, UX móvil, validación de revocación/carreras; sí |
| D10. Reverificación y recuperación | Sin revisión temporal; o vencimiento de evidencia y disparadores | `reverify_at` obligatorio según tipo de evidencia y fuente; suspensión al vencer y ante cambio material; plazo numérico debe ratificarse | Evita autoridad indefinida; avisos y cola de revisión sin período de gracia para firmar | Política de cadencias, avisos, recuperación, revocación y expiración; sí |

Los blockers de instalación son D01/D02/D03/D09/D10 y la autorización de implementar/probar/desplegar. D04–D08 pueden diferirse manteniendo denegación de los escenarios ampliados. La fase inicial no debe anunciar que resolvió toda la delegación ni toda la autoría clínica general H-01.

## 11. Implementación mínima recomendada, aún no autorizada

1. Ratificar D01/D02/D03/D09/D10, fuentes de acreditación/titularidad y disponibilidad de autoridad emisora independiente. Si ADM02 continúa diferido o no hay evidencia válida, conservar 02A cerrado.
2. Reutilizar Identity/session/Profiles; implementar binding y su evidencia auditada, constraints/carreras y ciclo de revocación en base desechable. Registrar sólo capacidades/eventos aprobados; sin seeds ni auto-backfill de usuarios existentes.
3. Extender la composición request-scoped de `CanonicalClinicalIdentityResolver` y el límite `clinical_professional_authority_*` con una decisión de operación. No reemplazar el resolver por otro motor de roles ni hacer que un resultado booleano omita patient/encounter/entitlement.
4. Habilitar sólo titular del mismo perfil y misma cuenta en snapshot; comparar cédulas/identidad verificada y exigir confirmación actual. Revalidar en writers/QR/finalización/void/reissue. Agregar guardas profesionales a diagnóstico, notas finales, assessment/plan, órdenes y enmiendas según matriz ratificada para cerrar H-01, sin convertir preparación no ratificada en permiso.
5. Verificar endpoint SIG03A legado, entrada genérica/alias de documentos y QR de todos los tipos. Preservar fingerprint, M6, single writer, lifecycle e históricos. Hasta tener handoff, un médico no firma el borrador de otro creator ni hereda su snapshot; una futura adopción debe crear un borrador propio trazable, sin cambiar autor original.
6. Ejecutar aceptación desechable §13 y obtener autorización separada de migración normal-local/onboarding real. Sólo entonces validar el perfil del profesional real, conservando rol y cuenta; no usar QA05 ni bypass. Diseñar delegación, selector y cobertura como fases posteriores explícitas, sin plazas extra.

## 12. Mapa de reutilización y faltantes

| Componente revisado | Reutilizar | Falta; no instalado por este contrato |
|---|---|---|
| `modules/identity/http/CanonicalHttpSessionResolver.php`, `IdentityHttpComposition.php`, `services/SessionService.php` | Sesión, cuenta, versión, expiración, revocación, CSRF/composición normal-local | Prueba de intención de firma; enlace de sesión móvil al actor y versión de autoridad |
| `modules/identity/repositories/AccountMembershipRepository.php`, migración Gate 4A, `http/CanonicalClinicalIdentityResolver.php` | Membresía existente, rol/alcance/estado y contexto canónico | Relación profesional y decisión por operación; grants clínicos futuros; selector multi-perfil ratificado |
| `modules/profiles/services/VerifiedPhysicianIdentityService.php`, `repositories/VerifiedPhysicianIdentityRepository.php`, `db/2026_09_11_create_profiles_verified_identities.sql` | Identidad oficial/procedencia referenciada | Binding del titular de cuenta, reverificación y corrección gobernada; no usar `verified_by_account_id` como sujeto |
| `modules/profiles/services/VerifiedDoctorCredentialService.php`, `repositories/DoctorCredentialRepository.php`, `db/2026_09_12_create_profiles_doctor_credentials.sql` | `VERIFIED/ACTIVE`, tipo profesional/especialidad, cédula textual y audit trail | Cédula no prueba titular de cuenta ni admisión; enlazar evidencia/cambio de estado a efectividad de binding |
| `modules/identity/services/InternalCapabilityCatalog.php`, `InternalGovernanceService.php`, `R3ProfileGovernanceService.php`, `services/ProfileGovernancePolicy.php` | Asignación/capacidades de staff, casos, MFA, separación, patrones de revalidación | Capacidad y caso de verificación profesional específicos; no reutilizar `profile_initial_ownership_approve` como permiso médico |
| `modules/profiles/controllers/PublicProfileController.php`, `PublicDiscoveryController.php`; contratos C4/ADM01 | Publicación y estado de claim expresado honestamente | Autoridad canónica de admisión y claim médico ejecutables; mantener diferimiento hasta autorización |
| `modules/subscriptions/services/ExistingCapabilityAuthorityService.php`, adaptador Identity y lectura de suscripción | Resolver entitlements CR/RX | No sustituye calificación ni grant de operación |
| `api/_lib/clinical_professional_authority.php`, `api/clinical/index.php` | Límite fail-closed, rutas, patient/encounter/case scope y origen | Consumir binding ratificado en el mismo límite, sin fallbacks de owner/staff/plan |
| `api/_lib/clinical_prescription_v2.php`, `clinical_prescription_lifecycle.php`, `clinical_prescription_binding.php`, `clinical_prescription_qr.php` | Writer único, creator, versiones, fingerprint/artefacto y QR replay | Resolver actor profesional y revalidar decisiones antes de cada transición; handoff/cobertura pendientes |
| `api/_lib/clinical_consent_write.php`, `clinical_consent_signature_binding.php`, `clinical_consent_qr.php` y writers/QR de documentos versionados | Firmas separadas, snapshots, attachment scope e inmutabilidad | V para médico y finalización; no exigir vínculo médico al paciente/representante |
| `modules/signatures`, `SIG03A`, `assets/js/clinical/consent-signature-binding.js`, `assets/js/clinical/prescription-signature-binding.js` | Imagen privada y algoritmos/versiones existentes | Reconciliar management legacy con Identity/autor profesional; sin promover imágenes o firmas antiguas a autoridad |
| `modules/platform/services/CanonicalAuditWriter.php`, `repositories/JoinedPdoCanonicalAuditTransactionAdapter.php` | Un solo escritor de auditoría y atomicidad de mutación/evidencia | Alta aprobada de nuevos eventos, metadata/retención y productores; no emitir eventos fuera de catálogo |
| `modules/clinical/qa/clin_auth02a_*`, tests Identity, fingerprint y M6 | Baseline y regresiones existentes | Fixture positiva legítima y adversarial de binding, pruebas de delegación/cobertura sólo cuando se autoricen |

Inventario de faltantes de primera fase: persistencia/repositorio de binding; emisor de evidencia del titular y acreditación; revisión/revocación/reverificación; catálogo de capacidades/eventos; extensión de contexto/decisión; guardas por operación; prueba de intención y sesiones QR; revalidación de snapshots; pruebas de concurrencia y rollout. Faltantes de fases ampliadas: grants granulares sobre membresías, review/adopción de propuestas, selector y cobertura con atribución propia. No se concede autorización porque falte una de esas piezas.

## 13. Aceptación desechable de la futura implementación

Esta lista es un gate futuro; ninguna prueba positiva de binding se declara ejecutada en esta fase documental. Fixtures independientes: profesional titular verificado, owner/admin/collaborator sin prueba, colaborador que es titular verificado, médico de otro perfil, asistente acotado si se ratifica, staff de soporte y cuenta revocada. Todos sintéticos en base/Valkey/media aislados y eliminables; QA05 persistente y la Directora quedan fuera de fixtures.

| Gate | Evidencia exigida |
|---|---|
| Q01. Positivo `AUTH02A_15` | Emisión por revisor independiente autorizado con evidencia del titular, VID01/CRD02 válidos, aceptación y auditoría; cuenta canónica entra por HTTP y firma/emite con su propio perfil sin cambio de rol ni bypass |
| Q02. Independencia de hechos | Perfil existente, cédula verificada, cuenta con correo verificado, owner/admin/collab, plan y firma guardada por separado nunca dan V; permutaciones con cada prerrequisito ausente |
| Q03. Emisor y evidencia | Staff sin capacidad, autoaprobación, evidencia vencida/reutilizada, sujeto o perfil incorrecto, aprobador revocado y fallo de auditoría deniegan sin binding efectivo |
| Q04. Ciclo y recuperación | Pending/suspended/rejected/expired/revoked deniegan; reverificación no inventa fecha/revisor; recovery no duplica identidad/cuenta ni transfiere ownership; dos activaciones concurrentes conservan invariantes |
| Q05. Alcance y roles | Sesión expirada/revocada, credential version, membresía suspendida/ambigua, paciente/encuentro/documento ajenos, capability CR sin RX y selector falsificado deniegan con 401/403/404/503 apropiado; evitar enumeración |
| Q06. Cada transición sensible | Firma local/registrada, QR creación/upload/claim, emisión, anulación, reemisión, consentimiento médico y demás documentos; revocación o cambio de cédula entre firma y emisión deja cero mutaciones persistidas y cero idempotency replay autorizado |
| Q07. QR e intención | Token sin sesión de médico según D09 no firma; cuenta móvil distinta, originante revocada, sesión móvil expirada, step-up viejo, token robado, replay/TTL, cambio de fingerprint/version y binding reemitido deniegan; teléfono no hereda cookie de escritorio |
| Q08. Preparación y paciente | Borradores seguros/firmas de paciente continúan; ningún grant de captura obtiene diagnóstico/orden/firma; si D04 no se aprueba no hay positivo de asistente; si se aprueba, evidencia de campos, revisión y autoría real |
| Q09. Lecturas | Agenda mínima, metadata, expediente, adjuntos, descarga y reimpresión tienen proyecciones/denegaciones propias; soporte no accede a pacientes; upload no concede lectura masiva |
| Q10. Integridad/históricos | Todos AUTH02A_01–30, M6, golden vectors PHP/JS y documentos históricos sin cambios; single writer, optimistic version, inmutabilidad, lineage y idempotencia conservados |
| Q11. Carreras | Revocación de sesión/binding/credencial concurrente con firma/commit, doble emisión y replay; evidencia del punto de orden/locks y de ausencia de permisos persistidos en sesión; dependencia caída deniega |
| Q12. Datos y UI | Checksums normal-local y QA05 idénticos; sin membresías/roles/grants reales; origen/CSRF/HTTPS; mensajes españoles sin errores internos, éxito falso ni sustituto unsigned; dispositivos y red del QR comprobados en entorno aislado |

Cobertura y multi-perfil sólo reciben pruebas positivas tras D06/D08, con actor/cédula propios, selector servidor, grants independientes, expiración y límite de plazas. Hasta entonces la prueba correcta es denegación. No alterar fingerprints antiguos para hacer pasar una fixture.

## 14. Verificación de esta entrega documental

Preflight: repositorio correcto, HEAD reportado y upstream iguales a `3fc19bc59d5aa9fc73d739035b2f30737bc85168`; árbol limpio antes de crear este archivo. Se revisaron CLIN-AUTH01 completo, contención 02A, ROLE-POL01/guía histórica, fuentes VID01/CRD02, Identity/resolver, firma/QR/consentimiento, ownership/GOV03A y checkpoint de admisión. Se buscaron relaciones profesionales dormidas y fuentes de claim/invitación sin hallar una vinculación canónica reutilizable.

Validación de entrega: sólo este documento, referencias locales y diff revisados, sin archivo PHP/JS/SQL cambiado. No se ejecutaron flujos de autenticación, alta de binding, firma ni mutación de datos para diseñar el contrato. No se atribuye una aprobación del Director a las recomendaciones pendientes.

```text
CONTRACT_STATUS=DESIGN_COMPLETE_PENDING_DIRECTOR_RATIFICATION
CANONICAL_ACCOUNT_PROFESSIONAL_BINDING_FOUND=false
PROPOSED_BINDING_INSTALLED=false
NEW_SCHEMA_REQUIRED_FOR_FUTURE_IMPLEMENTATION=true
CLIN_AUTH02A_GUARDS_PRESERVED=true
NORMAL_LOCAL_DATA_UNCHANGED=true
QA05_DATA_UNCHANGED=true
MEMBERSHIPS_UNCHANGED=true
RUNTIME_AUTHORIZATION_CHANGED=false
SQL_MIGRATION_APPLIED=false
AUTH02A_15=BLOCKED_BY_AUTHORITY_CONTRACT_UNTIL_SEPARATELY_AUTHORIZED_IMPLEMENTATION
```
