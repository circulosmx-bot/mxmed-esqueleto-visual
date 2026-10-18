# ASE-ADM02-POL01 — admisión e identidad del staff interno

2026-10-18. Contrato de producto **UI-0, exclusivamente documental**. Padre verificado `732938df607dc508f50f14e564ada86bba4590ca`, `feature/act03-governance-check-compat01`, limpio y coincidente con remoto; entrega en `feature/ase-adm02-staff-admission-policy`, worktree `mxmed-ase-adm02-staff-admission-policy`. Oficial `f92f37f1958af5140a96c04cb52c5c1ce7af8e01` y prototipo aprobado `312ee388f93c892e6caf7e845b11e2b42f9c3565` preservados.

## Autoridad, procedencia y clasificación

La solicitud del Director ASE-ADM02-POL01 registra expresamente su respuesta **“apruebo”** a los siete principios siguientes. Esta aprobación sucede la condición de principios pendientes de ASE-ADM01; no ratifica automáticamente detalles técnicos, contratos legales, plazos ni operación. Las notas históricas se conservan como evidencia de su fase.

| Clasificación | Alcance vigente |
|---|---|
| DIRECTOR_APPROVED_POLICY | Los siete principios de la sección siguiente y los límites de producto solicitados; admisión asistida/remota individual, un caso, sin autoaprobación. |
| PROPOSED_IMPLEMENTATION_DETAIL | Diccionario por etapa, objetos/versiones, capacidades nuevas propuestas, guardas, recuperación y fases descritos aquí; no son SQL, API ni catálogo aprobados/implementados. |
| UNRESOLVED_POLICY_DETAIL | Retención/borrado, textos legales, relación contractual, catálogo extranjero, parámetros técnicos de custodia y delegación exacta Master; requieren ratificación antes de sus usos. |
| SOURCE_IMPLEMENTATION | Identity, gobierno, MFA y auditoría existentes; snapshot Master tipado de tres campos, reader producto indisponible. Sin writer de admisión implementado en este alcance. |
| CONNECTIVITY | Equipo/grants/lifecycle/Master conservan conexiones aisladas previas. Caso/perfil/invitación/evidencia/revisión/activación staff siguen sin conexión producto; + AGREGAR INTEGRANTE no se habilita. |
| VERIFICATION | Inspección dirigida de fuente y versiones inmutables, revisión del diff, referencias y preservación Git/runtime. Ninguna QA de producto ni consulta SQL nueva. |
| OPERATIONAL_STATUS | DOCUMENTATION_ONLY_NOT_OPERATIONAL. Sin cuentas, documentos, retratos, permisos, MFA, correos, proveedores ni datos reales; sin merge o despliegue. |

Fuentes inmutables leídas en el prototipo: [ASE-ADM01](https://github.com/circulosmx-bot/mxmed-esqueleto-visual/blob/312ee388f93c892e6caf7e845b11e2b42f9c3565/docs/identity/ASE-ADM01-staff-admission-personal-profile.md), [ASE-VIS01 R2](https://github.com/circulosmx-bot/mxmed-esqueleto-visual/blob/312ee388f93c892e6caf7e845b11e2b42f9c3565/docs/identity/ASE-VIS01-advisor-matrix-prototype.md), [ASE-COM01](https://github.com/circulosmx-bot/mxmed-esqueleto-visual/blob/312ee388f93c892e6caf7e845b11e2b42f9c3565/docs/identity/ASE-COM01-commercial-attribution-recurring-commission.md). Recuperables con `git show 312ee388f93c892e6caf7e845b11e2b42f9c3565:docs/identity/<nombre>.md`; no se copian ni se modifica esa rama. La aprobación visual posterior está registrada en [INT01 R2](ASE-INT01-R2-internal-team-readonly.md).

Continuidad: [IW01](../product-completion/iw01-internal-governance-foundation.md), [GOV03A](IDENTITY-GOV03A-governance-runtime.md), [INT02A](ASE-INT02A-governed-advisor-grants.md), [INT02B](ASE-INT02B-team-permission-ui.md), [INT03A](ASE-INT03A-advisor-lifecycle.md), [PREP01](ASE-INT03B-PREP01-master-privileged-authorization.md), [IMP01](ASE-INT03B-IMP01-master-security-foundation.md), [IMP02](ASE-INT03B-IMP02-master-ui-integration.md) y [COMPAT01](ACT03-CHECK-COMPAT01-historical-case-check.md). No se reinterpreta su QA como prueba de admisión. COMPAT01 conserva reparación SQL aislada; no se reescribe migración ni evidencia. ASE-COM01 conserva recurrencia aprobada, descuento histórico y D01–D08 pendientes: admisión no asigna clientes ni devenga comisiones.

## Siete principios aprobados

| Política | DIRECTOR_APPROVED_POLICY | Detalle pendiente o límite |
|---|---|---|
| 01 Documentos | Un documento individual aprobado: INE vigente o pasaporte vigente para mexicanos; no exigir varios indiscriminadamente. | Catálogo extranjero PENDING_DIRECTOR_DECISION; presentación técnica/formato por aprobar. |
| 02 Revisor | Director; Master elegible sólo para Advisor con autoridad exacta de revisión staff aprobada separadamente. Un humano autorizado basta. Sin autoaprobación ni nombramiento Master por Master. | Capability/delegación/consumidor staff específico aún no implementados. |
| 03 Custodia | Evidencia privada, acceso restringido, auditable y cifrada conforme al contrato de custodia aprobado. | Plazos/borrado, custodios/llaves y contrato operativo pendientes; intake real cerrado. |
| 04 Aceptaciones | Persona acepta personalmente aviso/condiciones aplicables; conservar cuenta, versión exacta, acción y momento. | Texto y relación jurídica requieren validación/aprobación independiente. |
| 05 Cambios materiales | Nombres/documentos revisados que cambien materialmente requieren nueva evidencia versionada y revisión renovada apropiada; no sobrescribir historia. | Criterios detallados de materialidad y validez temporal por ratificar; incertidumbre bloquea reutilizar aprobación. |
| 06 Invitación | Individual, previamente autorizada, destinatario y caso exactos, expiración, revocación, aceptación segura y matching legítimo. | TTL, entrega, reemisión y política exacta pendientes; enlace nunca concede acceso operativo. |
| 07 Activación | Cuenta legítimamente verificada, revisión interna completada, admisión aprobada y permisos individuales actuales. MFA según operación/política, salvaguardas Master adicionales. | Conexión canónica y preparación por consumidor pendientes; sin rol «todos los permisos». |

## Dos entradas, un caso

**Asistida:** Director o Master autorizado prepara desde **+ AGREGAR INTEGRANTE** información básica, categoría y capacidades propuestas. Guarda autor/procedencia; no acepta por la persona ni establece/conoce su contraseña reutilizable. La persona registra o autentica por Identity, confirma/corrige información y acepta los documentos aplicables. Preparación administrativa no satisface confirmación personal.

**Remota preautorizada:** administrador autorizado crea el mismo caso y una invitación dirigida. La persona obtiene acceso limitado a su propia información, evidencia, envío y aclaraciones; no cambia invitador, categoría de gobierno propuesta, poderes solicitados, identidad/autorización del caso ni grants efectivos. La aceptación vincula servidor, destinatario y cuenta autenticada bajo control de versión. Ningún enrollment staff anónimo irrestricto; reclutamiento público requiere otra aprobación.

Propuesta técnica: token aleatorio de alta entropía, digest almacenado, propósito staff específico, expiración/revocación/consumo atómicos ligados al caso y destinatario. El token no sustituye autenticación ni permite enumerar cuentas. Reemisión invalida el anterior y conserva trazabilidad; TTL y transporte no se toman de otro dominio. No transportar tokens a logs, analytics o documentación; respuestas privadas/no-store y protección de origen/CSRF para mutaciones. Esta especificación no habilita envíos.

## Reuso Identity y recuperación A–H

| Componente fuente exacto | Hecho existente y primer adaptador staff faltante |
|---|---|
| [RegistrationService::register(array $input, array $dimensions)](../../modules/identity/services/RegistrationService.php) | Crea cuenta PENDING_VERIFICATION mediante IdentityAccountRepository, credencial mediante AccountCredentialRepository, consents y token en transacción propia; notifica después del commit. No escribe perfil staff. Falta vincular caso/persona autenticada; no segundo writer de cuentas/contraseñas. |
| [EmailVerificationService::verify/resend](../../modules/identity/services/EmailVerificationService.php) | Verify comprueba propósito, expiración/consumo/invalidación, consents y cuenta pendiente; activateAfterVerification y consume son canónicos. Resend invalida anteriores, emite y luego notifica. No acredita documento ni staff. Falta handoff de verificación al caso. |
| [OneTimeTokenRepository](../../modules/identity/repositories/OneTimeTokenRepository.php) | issue/findByHashAndPurpose/consume/invalidateForPurpose/invalidateToken, digest y consumo condicional. [OneTimeTokenPurpose](../../modules/identity/contracts/OneTimeTokenPurpose.php) sólo email_verification, password_recovery y operator_invitation. No propósito staff ni vínculo de invitación pre-cuenta: requiere contrato/extensión propios, sin reutilizar invitación médica. |
| [AccountConsentRepository::record/hasRequiredForAccount](../../modules/identity/repositories/AccountConsentRepository.php) | auth_account_consents: consent_id, account_id, document_type, document_version, accepted_at, metadata_json. [Tipos actuales](../../modules/identity/contracts/ConsentDocumentType.php): terms/privacy_notice. hasRequired sólo cuenta ambos tipos, no vigencia legal/versiones staff ni aceptación de colaboración. Futuro adaptador debe validar documento exacto/acción personal y extender contrato canónico cuando proceda, nunca disfrazar colaboración de terms. |
| [CanonicalHttpSessionResolver::resolve](../../modules/identity/http/CanonicalHttpSessionResolver.php), [SessionService::validate/bindingIsCurrent](../../modules/identity/services/SessionService.php) | Cookie opaca canónica; sesión, cuenta activa, expiración/revocación y versión de credencial. Reusar contexto actual y rechazar actor/cuenta/rol del navegador como autoridad. Faltan readers propios de caso y revisor. |
| [IdentityHttpComposition](../../modules/identity/http/IdentityHttpComposition.php) | Construye registration/verification/authentication/recovery/sessions y expone PDO/CSRF; reuso en adaptadores staff futuros con método/origen/privacidad. No nuevo login/store de sesión ni cambio de composición en POL01. |
| [InternalGovernanceService::execute](../../modules/identity/services/InternalGovernanceService.php) | Writer único staff/grants, transacción propia y auditoría canónica por comando. No hay método PHP register: es la operación string `register` de execute. Falta precondición de admisión transaccional, detallada más abajo. |

| Caso | Resultado y recuperación requeridos; propuesta de integración |
|---|---|
| A Sin cuenta | Registro canónico por la persona, aceptación real y verificación; mantener caso pendiente hasta matching autenticado. No auto-crear staff ni copiar contraseña. |
| B Cuenta legítima existente | Reutilizar cuenta tras autenticación/verificación aprobada y vínculo destinatario–caso comprobado; nombre/email coincidentes no bastan. No reset, merge o duplicado silencioso. |
| C Email pendiente | Continuar verify/resend canónicos; retorno accepted no es cuenta activa. Sin sesión válida no dar acceso al caso completo. |
| D Invitación duplicada/incompleta | Un intento lógico por destinatario/caso/versionado; releer resultado, continuar mismo caso. Cambio de intención requiere nueva versión/autorización; no otra cuenta. |
| E Expirada/revocada | Denegar aceptación sin divulgar existencia; administrador autorizado reemite con nuevo token y anula anterior. No reabrir autorización revocada desde el cliente. |
| F Ya es staff | Resolver relación canónica actual; no insertar segunda fila, reactivar suspendido ni cambiar categoría. Legacy pasa a revisión pendiente separada; Master sigue su flujo privilegiado exclusivo. |
| G Fallo/parcial | Registro puede haber hecho commit aunque falle notificación (intenta invalidar token); conservar cuenta pendiente y usar recuperación canónica. En cada paso distinguir persistido, pendiente y resultado incierto; sin borrar cuenta/evidencia legítima como compensación. |
| H ACK perdido/retry | Consultar resultado autorizado por caso/operation-id y auditoría; no repetir alta/envío/grant a ciegas. Propuesta futura de idempotency-key ligada a actor/caso/payload/versiones: misma intención devuelve resultado canónico, distinta intención conflicto. No existe esa garantía global hoy. |

Los mensajes externos deben ser genéricos frente a cuentas desconocidas/duplicadas; rate limits canónicos y límites del caso. El futuro adapter no debe exponer `DUPLICATE_ACCOUNT`, account_id o razones internas a un tercero para enumeración. El dueño autenticado puede recuperar sólo sus resultados. Cambio de destinatario invalida la invitación previa y exige autorización nueva; no rebinding por editar email.

## Diccionario personal mínimo

**PROPOSED_IMPLEMENTATION_DETAIL:** requisitos por etapa derivados de ADM01; no nuevos campos SQL. `P` = futuro perfil/caso staff versionado; `I` = Identity canónica. Toda preparación registra actor/fecha/origen; confirmación registra persona/cuenta/versión/fecha. Históricos revisados son inmutables; una corrección crea versión vinculada. Los lectores son la propia persona, revisor exacto y administradores autorizados del directorio dentro de su scope (Master sólo Advisors); no pares ni público. Plazos de todas las clases pendientes, nunca perpetuos por defecto.

| Campo y propósito | Requisito/etapa | Fuente; proveedor y editor | Quién ve / disponibilidad | Verificación y versión/procedencia | Retención conceptual |
|---|---|---|---|---|---|
| Nombre(s), identificación/presentación | Requerido al confirmar para revisión | P; admin prepara, persona confirma/corrige; cambio posterior versionado | Persona/revisor; directorio interno autorizado; privado | Declarado → confirmado → contrastado con evidencia exacta; nombre no prueba cuenta. Versión P y autor de cada paso | PERFIL_INTERNO; referencia mínima DECISION_AUDITORIA |
| Primer Apellido, identificación | Requerido al confirmar para revisión; excepciones de nomenclatura requieren aclaración, no inventar | P; admin prepara, persona confirma/corrige | Persona/revisor/directorio autorizado; privado | Mismo contraste limitado y versión P; edición material invalida aprobación corriente | PERFIL_INTERNO / DECISION_AUDITORIA |
| Segundo Apellido, identificación cuando aplica | Sólo si aplica; ausencia legítima no bloquea | P; admin prepara, persona confirma/corrige | Persona/revisor/directorio autorizado; privado | Ausencia declarada preservada, sin fabricar valor; versión P/procedencia | PERFIL_INTERNO |
| Email, contacto y vínculo de cuenta | Destinatario propuesto en preparación; Identity verificada antes de decisión | I tras matching; admin propone destinatario, persona modifica cuenta sólo por mecanismo canónico autorizado | Persona; detalle mínimo al revisor/admin autorizado; sin directorio público ni búsqueda anónima | email_verified_at real; snapshot mínimo de cuenta/versión al decidir, no inferir desde invitación | CONTACTO_SEGURIDAD / DECISION_AUDITORIA |
| Móvil, contacto personal | Requerido propuesto antes de decisión, exigibilidad pendiente de ratificación | P; admin prepara, persona confirma/edita; fuente de verificación separada si se aprueba | Persona/revisor y detalle interno autorizado; privado | Introducido = NO VERIFICADO; sin SMS/OTP supuesto. Versión P y procedencia; no vincular a MFA automáticamente | PERFIL_INTERNO; CONTACTO_SEGURIDAD sólo bajo propósito aprobado |
| Fotografía de presentación, retrato interno | Requerida propuesta en configuración personal; obligatoriedad por ratificar | P/referencia privada propia; persona aporta/reemplaza, admin no extrae del ID | Miniatura directorio y detalle autorizado; persona/revisor; no publicación | Validación técnica ≠ identidad biométrica; referencia/hash/versión y aportante, sin sobrescribir | PERFIL_INTERNO; originales/derivados bajo política de imagen pendiente |
| Estado/ciudad de operación, organización | Opcional | P; persona/admin con confirmación y corrección atribuida | Perfil interno autorizado/persona; uso comercial exacto requiere finalidad; privado | Declarado, no residencia comprobada; versión P/procedencia | PERFIL_INTERNO |

Directorio aprobado: retrato y datos principales confirmados; **nunca scans de identificación**. Hasta conectar fuentes, conservar ausencia honesta de datos; no rellenar con email/nombre inferidos. No exigir por defecto CURP, RFC, banco, domicilio residencial completo, documentos fiscales, antecedentes ni plantillas biométricas. Liquidación de comisiones requiere finalidad y autoridad fiscal/bancaria separadas; no se mezcla con acreditar identidad.

## Evidencia, catálogo y custodia

Catálogo de principio aprobado: INE vigente o pasaporte vigente del solicitante mexicano, un documento apropiado. No pedir ambos por defecto; cualquier aclaración debe justificar necesidad y minimizar datos. Catálogo extranjero **PENDING_DIRECTOR_DECISION**: detener esa ruta hasta aprobación, sin rechazar nacionalidades por inferencia ni improvisar equivalencias. La elegibilidad documental se evalúa al revisar y al reutilizar revisión; regla temporal exacta pendiente.

Metadatos mínimos propuestos: referencia opaca inmutable, cuenta/caso, tipo aprobado/emisor/país cuando necesario, vigencia relevante, versión de evidencia, hash de integridad, MIME/tamaño comprobados, referencia privada de storage/cifrado, política de custodia, aportante/fecha y sustitución anterior. Fechas/identificadores sólo cuando necesarios para comprobación; no números gubernamentales innecesarios indexados en cuentas o buscables en texto plano. Hash/versiones tampoco se publican como identificadores de personas.

| Control futuro | Contrato y límite |
|---|---|
| Tres finalidades | Retrato interno separado de evidencia ID restringida; posible credencial pública de agente necesita aprobación propia. No extraer/reutilizar foto de ID ni publicar evidencia. |
| Formatos/tamaño | Propuesta allowlist JPEG/PNG y PDF para evidencia; retrato sólo imagen decodificable. Ratificar límites exactos de bytes/páginas/píxeles y parser seguro antes de intake. Sin SVG/HTML/ejecutables, archivos comprimidos o contenido activo aceptado por extensión. |
| Validación servidor | Comprobar MIME real y estructura/decodificación, tamaño efectivo, límites de recursos, extensión incoherente/contenido forjado; rechazar PDF cifrado/activo si no puede inspeccionarse de forma segura. No basta Content-Type del navegador. Cuarentena privada y scan/sanitización deben tener contrato antes de habilitar esos formatos; sin enviar a terceros por defecto. |
| Almacenamiento | Namespace staff privado fuera de webroot, referencias inmutables; transporte protegido y respuestas no-store/nosniff. Sin URL pública, ruta física, claves o bytes en audit/logs/fixtures. Referencia opaca no equivale a permiso. |
| Cifrado | Propuesta cifrado autenticado ligado a cuenta/caso/documento/versión y hash de ciphertext; key-id y política versionados, custodia/rotación/recuperación/backup mínimos ratificados. Sin llave default ni generar llave de producción silenciosa. Separación de llaves/propósito staff respecto de C4 por diseño aprobado. |
| Lectura exacta | Revalidar sesión/cuenta/credencial, actor, autoridad vigente, sujeto/caso/documento/versión y motivo; reader propio del solicitante no accede a notas privadas del revisor. Director/revisor autorizado tampoco obtiene descarga masiva por ver directorio. Auditoría de acceso y denegaciones minimizada, falla cerrada si no puede registrar lo exigido. |
| Revisión humana | Registro del documento/versiones efectivamente examinados y comprobaciones reales. Carga válida no completa revisión; incertidumbre pide aclaración. No garantiza autenticidad ni consulta de registro oficial. |
| Reemplazo/aclaración | Nuevo objeto y versión; enlazar anterior y solicitud/respuesta. Invalidar elegibilidad vigente cuando cambio material; conservar decisión histórica bajo retención aprobada. Nunca sustituir bytes ya revisados. |
| Fallido/abandonado | No visibilidad pública ni aprobación; objetos huérfanos cifrados se reconcilian contra resultado SQL canónico antes de borrar. Plazo, custodia temporal, rechazo/abandono y borrado comprobable requieren política. |
| Eliminación | Separar contenido, derivados, backups y metadata mínima de auditoría; prohibido asumir conservación indefinida. Borrado autorizado verificable con referencia de decisión, sin volcar contenido personal. Periodos aún pendientes. |

Primitivas fuente: [EvidenceCipher](../../modules/identity/Accreditation/EvidenceCipher.php) usa XChaCha20-Poly1305, keyring inyectado y dominio `C4-EV01`; [PhysicianEvidenceService](../../modules/identity/Accreditation/PhysicianEvidenceService.php) admite actualmente PDF/JPEG/PNG hasta 8 MiB, `storeImmutable`, hash y lectura controlada. **Son hechos C4, no límites staff aprobados ni prueba de scanner completo.** Su [EvidenceAuthority](../../modules/identity/Accreditation/EvidenceAuthority.php), permiso physician_accreditation_review y tablas auth_physician_evidence_* pertenecen a médicos. Reutilizar primitivas adecuadas mediante adaptación revisada; no medical ownership, tablas médicas, grants médicos ni `ProtectedPhysicianEvidencePort` como autoridad de identidad staff. No reconocimiento facial, embeddings, liveness, OCR/biometría automática o consulta oficial implícita.

## Hechos, decisiones y versiones

Los siguientes son **hechos conceptuales, no enums SQL autorizados**. El caso no puede saltar de invitación/upload a acceso; cada resultado tiene versión/autor/tiempo y precondiciones evaluadas servidor.

| Hecho | Condición, dueño y efecto |
|---|---|
| ACCOUNT_VERIFIED | Cuenta exacta activa con verificación canónica y matching legítimo, credencial/sesión vigentes; dueño Identity. No revisión documental. |
| PERSON_INFORMATION_CONFIRMED | Persona confirma versión de datos preparada/declarada; requiere vínculo autenticado al caso. Corrección posterior produce nueva versión. |
| IDENTITY_EVIDENCE_SUBMITTED | Intake privado acepta referencia/versiones y metadatos mínimos validados del caso; no resultado de identidad. |
| IDENTITY_REVIEW_COMPLETED | Revisor elegible independiente completa comprobaciones y decisión sobre versión personal/evidencias exactas. Completada puede ser negativa: **sólo resultado aprobado vigente** es elegible para admisión/port. Aclaración permanece pendiente. |
| STAFF_ADMISSION_APPROVED | Autoridad de admisión distinta del solicitante decide sobre cuenta, confirmación, revisión aprobada vigente y aceptaciones aplicables exactas. No concede staff/grants automáticamente. Rechazo deniega nueva operación. |
| STAFF_ACCESS_READY | Proyección de aprobación vigente + vínculo staff + permisos actuales para función concreta + MFA aplicable + disponibilidad/autorización del consumidor. No un flag durable de «todo listo». |
| STAFF_ACTIVE | Hecho canónico internal_staff.status=ACTIVE; no prueba retrospectiva de revisión ni readiness de cada función. |
| STAFF_SUSPENDED | Estado canónico SUSPENDED bloquea operación, conserva identidad/grants/historia; revisión no lo reactiva ni restaura grants. |

Secuencia propuesta: preparar → matching/confirmar/aceptar → presentar → revisión humana → aclaración versionada o decisión positiva/negativa → admisión → configuración/handoff → readiness por función. Rechazo/cancelación son resultados propios, nunca staff activo. Decisiones posteriores referencian la versión anterior; no alteran hechos históricos. Cambiar nombre/documento materialmente invalida la aprobación corriente y requiere revisión renovada antes de reutilizarla para admisión o incremento privilegiado; no borra relación legacy ni ejecuta suspensión masiva.

Registro de revisión propuesto: reviewer account/credential y autoridad exacta a la hora de decidir, applicant/case, versión de perfil, referencias/hash/versiones de documentos, política aplicable, lecturas/comprobaciones realizadas (legibilidad, coincidencias declaradas, vigencia), hallazgos/limitaciones, aclaraciones/respuestas, resultado/razón/fecha servidor y correlación audit. Validación y commit bajo locks/versiones actuales; revocación de autoridad concurrente impide aprobar. Un revisor autorizado basta, incluso si preparó el caso de otra persona; no puede ser el solicitante. Admisión registra decisión separada aunque el mismo humano sea elegible para ambas.

Notificación futura: sólo estado, pendientes propios y motivo minimizado de rechazo/aclaración; no notas sensibles, documentos ajenos o juicio de autenticidad garantizada. No enviar hasta aprobar canal/plantillas; fallo de entrega no revierte ni convierte decisión en positiva.

## Matriz de autoridad

| Función | Director | Master ACTIVE elegible | Advisor / solicitante |
|---|---|---|---|
| Preparar caso/invitar | Según política/ámbito y consumidor futuro | Sólo Advisor, autoridad exacta de admisión/delegación aprobada e implementada | No invita ni cambia categoría/poderes |
| Presentar/confirmar datos y aceptar | Sólo sus propios datos como persona | Sólo sus propios datos como persona | Sólo caso propio autenticado; no autoaprueba |
| Revisar identidad | Principio aprobado, consumidor staff protegido pendiente | Sólo Advisor ajeno con autoridad staff específica | Denegado incluso para sí mismo |
| Aprobar admisión | Según política, nunca propia | Sólo Advisor ajeno con autoridad de admisión específica | Denegado |
| Leer ID privado | Reader exacto, finalidad/autoridad staff y audit, sin wildcard de rol | Sólo caso Advisor autorizado y permiso exacto de lectura | Sólo su documento bajo reader propio; no notas privadas ni pares |
| Grant/revoke Advisor | IW01/INT02A: writer/target/grant exactos | Management + delegación exacta vigente | Sin autogrants |
| Nombrar/retirar Master | Sólo flujo privilegiado IMP01/02; appointment real bloqueado | Denegado | Denegado |
| Usar función/MFA | Grant/consumidor/operación exactos, sin bypass | Igual; delegar no concede uso | Igual si staff elegible; sujeto MFA no registra su propio factor |

Propuesta **PENDING_DIRECTOR_DECISION**, no catálogo ejecutable: `internal_staff_identity_review` para consumidor de revisión humana; `internal_staff_identity_evidence_read` para reader privado de casos; `internal_staff_admission_manage` para preparar/invitar y decidir admisión (operaciones explícitas auditadas, target Advisor para Master). Son autoridades separadas: revisión no concede admisión; lectura no aprueba; admisión no da lectura de bytes. Perfil propio/aceptación se autorizan por vínculo de caso, sin dar grants de staff al solicitante. Ratificar si preparación y decisión requieren claves separadas antes de implementar ese consumidor.

Reuso propuesto del catálogo/delegación canónicos exige aprobación expresa de nuevas claves, su kind/delegable, otorgante y consumidores; el catálogo actual no contiene estas claves. Director conserva raíz de gobierno, pero consumidores operativos de revisión/lectura deben exigir sus permisos explícitos conforme IW01; no un nuevo bypass por clase. Master necesita tanto la autoridad individual de uso como habilitación exacta del alcance Advisor; `internal_advisors_manage` por sí solo no autoriza revisión/admisión documental. Tampoco physician_accreditation_review ni permisos C4. No wildcard «staff admin», autodelegación o nombramiento Master por Master. Toda decisión conserva actor fuente, revisor y target separados; autoridad releída bajo locks.

## Legacy y contrato Master

Preservar ACTIVE/SUSPENDED y auditoría existentes. Proponer rótulo **PREEXISTING_STAFF_REVIEW_PENDING**, sólo presentación para ausencia de revisión; no enum/backfill ni falsa etiqueta verificado. No deshabilitar masivamente cuentas legacy. Incrementos que exijan revisión aprobada fallan cerrados mientras falte; operaciones históricas conservan sus propios gates. La transición individual debe recibir autorización y evidencia real, nunca convertir fixture/email/MFA en revisión.

Contrato fuente exacto de [StaffIdentityReviewPort](../../modules/identity/contracts/StaffIdentityReviewPort.php):

```php
currentApproved(PDO $transaction, string $accountId, int $credentialVersion): StaffIdentityReviewSnapshot
```

[StaffIdentityReviewSnapshot](../../modules/identity/contracts/StaffIdentityReviewSnapshot.php) contiene **sólo** `accountId`, `credentialVersion`, `reviewVersion`. Exige cuenta no vacía, versión de credencial >=1 y reviewVersion no vacío de hasta 128 bytes; `values()` devuelve `account_id`, `credential_version`, `review_version`. No tiene campos evidence/status/expiry/materialChange y no los valida mágicamente.

[Cases::snapshot/revalidate](../../modules/identity/MasterGovernance/Cases.php) bloquea target ADVISOR/ACTIVE y cuenta activa para appoint_master, obtiene el port para cuenta/credencial exactas, comprueba correspondencia e incluye values en snapshot/digest de intención. Revalida snapshot en el flujo y rechaza deriva. [Policy](../../modules/identity/MasterGovernance/Policy.php) protege Director/no-self/caso y ordena locks cuentas/staff/credenciales. [Composition](../../modules/identity/MasterGovernance/Composition.php) inyecta incondicionalmente [UnavailableStaffIdentityReview](../../modules/identity/adapters/UnavailableStaffIdentityReview.php), que lanza `staff_identity_review_source_unavailable`. Se conserva **BLOCKED_PENDING_STAFF_IDENTITY_REVIEW**; sin env/browser/test-adapter de éxito en HTTP normal.

Obligación del futuro adapter (propuesta): usar **el mismo PDO/transacción activa** bajo locks, fuente staff autoritativa, cuenta/credencial actuales, revisión positiva vigente y versión de perfil/evidencias examinadas; validar invalidación, expiración/revocación y ausencia de cambios materiales. Debe negar si falta cualquier dependencia o no puede determinar vigencia. `reviewVersion` debe identificar una revisión inmutable cuyo backing record liga esas dependencias; cualquier cambio relevante cambia/invalida esa versión y los casos Master abiertos. No emitir un «approved=true» del navegador ni construir snapshot desde fixture.

Credencial rotada requiere binding autoritativo actualizado o rechazo; nunca sustituir la versión recibida sin validar cuenta/estado. Orden de locks del adapter debe ser compatible con Policy; writers de materialidad/revisión deben serializar por cuenta y versión para evitar que se apruebe con evidencia reemplazada entre lectura y mutación. Necesita ensayo de concurrencia/rollback antes de conectar. Datos de respaldo y eventual extensión tipada del port se aprueban en IMP04; no son campos del DTO vigente. Nombramiento Master continúa requiriendo caso, confirmación, contraseña, proof MFA exacto y consumo/mutación/audit atómicos; revisión válida no sustituye nada de ello.

## Handoff de activación canónica

Conflicto fuente: `execute(array $cookies, string $operation, string $target, ?string $capability=null, ?string $targetPrecondition=null)` con operación `register` exige cuenta target activa y ausencia de staff; inserta **ADVISOR/ACTIVE** y audita. Abre transacción propia y rechaza exterior. No comprueba expediente, aceptación staff ni revisión. Sus dos precondiciones actuales son ACTIVE_ADVISOR_OPERATIONAL (grant/revoke) y ADVISOR_LIFECYCLE (suspend/reactivate); no valen para admisión. El flujo nuevo **no debe llamar register al preparar, invitar o subir evidencia**, ni envolverlo en una transacción exterior suponiendo atomicidad.

Propuesta mínima para IMP05: una entrada restringida de admisión en el **mismo writer** con precondición servidor tipada que, dentro de su transacción y orden de locks, revalide caso/cuenta/versiones/decisiones/aceptaciones y autoridad actual, consuma la intención de activación e inserte relación mediante la lógica canónica con auditoría correlacionada. Aprobar diseño antes de implementarlo; nunca guardia sólo HTTP anterior al lock, segundo writer SQL ni cambio silencioso del contrato histórico register/IW01. Los callers legacy conservan contrato; el nuevo flujo no expone register sin esa guarda.

Comprobar al handoff: Identity legítima verificada; persona y revisión positivas actuales; admisión aprobada; aceptaciones personales exactas requeridas; ninguna relación conflictiva; actor autorizado actual. Una relación ya existente se reconcilia, no se duplica/promueve/reactiva. Registrar ADVISOR no concede capacidades. Después, configuración de grants individuales mediante writers existentes y MFA según función; **STAFF_ACCESS_READY se calcula sólo con relación efectiva, grants vigentes, seguridad y consumidor disponible**. Cero grants significa ningún acceso operativo, no «todas» por ACTIVE.

Dependencia a resolver antes de live: ceremonia MFA actual requiere staff ACTIVE y elegibilidad de función. Propuesta: relación canónica tras admisión/revisión con alcance operativo inicialmente cerrado por guarda de readiness de los nuevos admitidos, permitir sólo configuración personal autorizada, preparar MFA y conceder/usar funciones según orden aprobado. Si una función exige grant previo para enrolar MFA, la guarda debe impedir su uso hasta preparación completa. No crear estado SUSPENDED temporal ni cambiar legacy para simularlo. Esa restricción aún no existe: **IMP05 queda bloqueado hasta demostrar que ningún consumidor permite operación prematura y aprobar el orden grants/MFA**.

Cada paso conserva operation-id/intención, resultado canónico y audit; éxito parcial cuenta/invitación/evidencia/decisión/relación/grants no se presenta como activación completa. Idempotencia futura persistida se liga a actor/caso/payload/versiones; unicidad por relación canónica y resultado por comando. Error de audit revierte su mutación transaccional; archivo/notificación no comparten transacción SQL y necesitan reconciliación. ACK perdido implica lectura del resultado/correlación antes de nueva confirmación; conflicto exige refresh/rebase y autoridad actual. Sin rollback global, revocaciones compensatorias automáticas, doble staff ni éxito final supuesto.

## Datos propuestos y ratificaciones antes de intake real

Objetos conceptuales siguientes **no autorizan nombres de tabla, enums, DDL/DML ni API**. Reutilizar referencias Identity/gobierno/audit; no otra cuenta, motor de contraseñas, permisos o historial paralelo.

| Objeto lógico propuesto | Binding/invariante y dueño futuro |
|---|---|
| Caso de admisión | ID opaco, canal, creador autorizado, destinatario, cuenta vinculada sólo después de matching, categoría/capacidades propuestas, versión, decisiones e intención correlacionadas. Un caso para ambos canales; servidor dueño de poderes y vínculo. |
| Perfil y confirmación | Valores mínimos/versiones, proveedor/editor/fechas, fuente declarada/admin/Identity y confirmación exacta de la persona; no edición de historia revisada. |
| Invitación | Caso/destinatario/autorizador/versiones, digest, propósito, expiración, revocación, aceptación única/cuenta e intentos; no token plaintext persistido ni grants implícitos. |
| Retrato y evidencia | Referencias separadas, versiones/hash, custodia/key-id/retención, sujeto/caso/documento; no tablas médicas. |
| Revisión y admisión | Decisiones separadas, exactas versiones/facts/reviewer/authority/reason/time/audit; resolución de aclaración y materialidad; aprobación vigente verificable. |
| Aceptación aplicable | Documento aprobado inmutable/versionado, cuenta personal, acción explícita y timestamp servidor; reuso/extensión canónica consent por diseñar, no aceptación delegada. |
| Intento de handoff | Clave de idempotencia/intención, versiones, resultado/correlación canónicos por paso; no segunda tabla de staff ni autoridad de grants. |

| Pendiente UNRESOLVED_POLICY_DETAIL | Ratificación responsable/gate y comportamiento hasta resolver |
|---|---|
| Retención de aceptados, rechazados, abandonados y huérfanos | Director y responsable de privacidad/custodia: intervalos por finalidad, disparador, excepciones justificadas, backups y borrado verificable. Sin valores arbitrarios; no intake real. |
| Aviso de privacidad y condiciones de colaboración | Validación legal y aprobación Director de texto/versiones/acción requerida antes de aceptación real. Ninguna plantilla lista para firma en POL01. |
| Relación contractual | Director con validación legal define naturaleza/aplicabilidad; ADVISOR no presume empleado/agente. No alta contractual por aprobar identidad. |
| Catálogo extranjero | Director aprueba tipos y comprobaciones apropiadas; ruta no disponible hasta entonces. |
| Custodia/cifrado y validación de archivos | Responsables de seguridad/custodia + Director: keyring/rotación/acceso, límites bytes/páginas/píxeles, formatos, contingencia y eliminación. Sin llave/proveedor o límite staff heredado de C4. |
| Delegación/capacidades de reviewer/admisión | Director ratifica claves, separación y alcance; implementación y pruebas posteriores. Master sin autoridad exacta denegado. |
| Invitación/validez/materialidad | Director ratifica TTL, entrega/reemisión, vigencia de revisión, criterios finos de cambios; fallo cerrado ante ambigüedad. |
| Campos requeridos y configuración | Ratificar exigibilidad móvil/retrato, orden grants/MFA y guardas por consumidor; nunca inventar teléfono verificado ni readiness. |

Principios privados/audit/cifrado/finalidad/aceptación y retención definida están aprobados; los parámetros anteriores no. **No habilitar cargas reales mientras custodia/retención esenciales estén pendientes.** Conservar este contrato técnico permanentemente no autoriza conservar documentos personales para siempre.

## Plan futuro de seguridad y QA — NO EJECUTADO

Todas las pruebas futuras requieren autorización de fase y DB/files/sesiones/llaves propios sintéticos, sin importación de personas, fotografías/IDs, mensajes o acciones reales. Preflight de propiedad y descarte limitado al ensayo. Cada escenario debe comprobar respuesta, estado canónico y audit, no sólo botón/fixture.

| Escenario | Assertion verificable futura |
|---|---|
| Asistida / remota | Ambas llegan al mismo caso; preparación no crea staff; admin no ve/elige contraseña; persona confirma/acepta. |
| Cuenta existente / nueva / pendiente | Matching exige autenticación; nuevo registro verifica canónicamente; pending no activa staff; nombre/email no fusiona ni resetea cuenta. |
| Enumeración | Desconocida/duplicada/resend no filtran existencia, ID ni detalle interno a tercero; límites de intentos. |
| Duplicado/expirado/revocado/consumo simultáneo | Mismo caso, denegar token inválido, una sola aceptación con dos conexiones; reemitido invalida anterior. |
| Caso propio / payload manipulado | Persona modifica sólo campos propios; rechazar cambio invitador/categoría/poderes/case/account ajenos. |
| Revisión Director / Master autorizado | Positivos con evidencia exacta y permisos vigentes; una revisión humana basta. |
| Master no autorizado / Advisor / self | Denegar management solo, grant médico solo, target Master y auto-revisión/auto-admisión; ninguna mutación/audit de éxito. |
| Documento/caso/versión equivocados | Denegar revisión/lectura con ID/versiones cruzadas o viejas; revocación concurrente del reviewer y reemplazo concurrente impiden commit. |
| Inválido/sobredimensionado/forjado | Rechazar MIME/extensión falsos, contenido activo, estructura rota y límites configurados excedidos; nada público ni aprobación. |
| Lectores privados | Permiso/directorio solo no revela bytes/keys; reader propio/Reviewer exacto positivo y audit; revocación deniega próxima lectura. |
| Sustitución/materialidad | Nueva referencia/versiones preservan anterior; aprobación deja de ser corriente y exige nueva revisión. |
| Upload/revisión/admisión | Upload no revisa, revisión negativa no admite, admisión no concede grants; aceptaciones ajenas/obsoletas no satisfacen gate. |
| Configuración pendiente | Admisión aprobada sin grants/MFA/consumer readiness nunca muestra ni permite función lista; probar ventanas concurrentes de handoff. |
| Legacy | ACTIVE sin review sigue estado canónico y rótulo honesto; no backfill verificado ni desactivación masiva. |
| Master sin snapshot / positivo autoritativo | Normal HTTP permanece denegado sin fuente; futuro adapter positivo usa transacción/cuenta/credencial/review/evidencia actuales; material change/rotación/caso viejo deniega. Fixture no seleccionable por env/HTTP. |
| Rechazo/cancelación | Sin nueva relación/grants/acceso operativo; mensaje minimizado, conservación/borrado conforme política ratificada. |
| Fallo parcial/ACK/audit | Fallo de audit revierte su comando; notificación fallida no duplica cuenta; ACK perdido se reconcilia sin retry ni doble staff/grants. |
| Aislamiento | Cero envíos/operaciones reales, ningún original tocado; evidencia sólo sintética y recursos propios comprobados. |

## Secuencia recomendada, no autorización de implementación

| Fase futura | Dependencias y alcance | Prueba sintética posible | Primer bloqueo de despliegue |
|---|---|---|---|
| ADM-IMP01 | Aprobar contrato técnico de caso/perfil/procedencia/versiones; reutilizar Identity y audit, sin writer staff duplicado | Casos/versiones/confirmación propia, carreras/denegaciones sin documentos reales | Modelo/autoridad/retención de datos personales aún no ratificados e implementados |
| ADM-IMP02 | IMP01 + propósito/destinatario/expiración/delegación; invitación preautorizada y handoff Identity | A–H con transporte falso, cuenta existente/nueva y consumo concurrente | Política TTL/canal/consent y entrega segura aún pendientes; sin envío real autorizado |
| ADM-IMP03 | IMP01/02 + contrato custodia/catálogo/validación; portrait/evidence privados versionados | Archivos artificiales válidos/forjados, lectores exactos, orphan reconciliation | Custodia/retención/llaves y límites no aprobados: intake real cerrado |
| ADM-IMP04 | IMP03 + capacidades/delegación, legal vigente, política revisión; decisiones humanas y adapter StaffIdentityReviewPort | Snapshot positivo/negativo, autoridad revocada, versión/materialidad/locks/audit; fixture sólo ensayo | Fuente autoritativa y permisos reviewer aún ausentes; no conectar fixture a producto |
| ADM-IMP05 | IMP04 + handoff mínimo mismo writer, orden grants/MFA y consumer gates; conectar panel aprobado con alcance UI explícito | Parciales/idempotencia/ACK/concurrencia, MFA/configuración pendiente y proyección honesta | Riesgo register ACTIVE prematuro y circularidad MFA no resueltos/ensayados; activación requiere aprobación separada |

Ninguna fase implementada aquí. Primero aprobar el alcance técnico sintético ADM-IMP01; decisiones de custodia/legal/delegación pueden prepararse en paralelo sin recibir datos reales. Cada fase exige nueva autorización; completar una no autoriza merge, despliegue ni operación.

## CTX-SYNC01 y verificación documental

| Campo obligatorio | Entrega |
|---|---|
| CONTEXT_BASELINE | Padre 732938df607dc508f50f14e564ada86bba4590ca; oficial f92f37f1958af5140a96c04cb52c5c1ce7af8e01; visual/ADM/COM inmutable312ee388; CTX-MAP02/03 e IW01/GOV/ASE/COMPAT contrastados. |
| AFFECTED_CAPABILITY_IDS | ID-07 / COM-10 en contexto; ID-01/02/03 reutilizados; ID-04/05 frontera médica preservada. Ninguna capability ejecutable nueva. |
| AFFECTED_JOURNEY_IDS | J09: propuesta de admisión y handoff; J08 frontera comercial preservada. Ningún journey ejecutado o conectado nuevo. |
| AFFECTED_DATA_OBJECTS | Cero objetos físicos modificados. Referencias auth_accounts, auth_account_credentials, auth_account_consents, auth_account_one_time_tokens, internal_staff, internal_operator_grants, internal_capability_delegations, auth_governance_cases, audit y snapshot; objetos lógicos propuestos arriba. |
| AFFECTED_CONTEXT_FILES | Este contrato; estado/router; mapas datos/interacciones/inventario; IW01; IMP01/IMP02; registro visual. |
| CONTEXT_CHANGES_IMPLEMENTED | Siete principios aprobados separados de detalles pendientes; diccionario/caso/evidencia/autoridad/legacy/port/handoff/QA futura/fases, sin producto. Sucesores históricos explícitos y acotados. |
| CONTEXT_VERIFICATION | Contraste de firmas, writers, límites y Git inmutable; revisión documental completa, 41 referencias locales/inmutables verificadas (sin anchors nuevos), diff docs-only y preservación de diez worktrees/listeners/pointer antes y después. Sin QA producto ni SQL. |
| CONTEXT_SYNC_STATUS | PASS_UPDATED para contrato documental; no prueba implementación o seguridad operacional. |
| DOCUMENTATION_COMMIT | Hash que contiene este documento informado en entrega Git; no autoinsertar hash futuro. |
| REMAINING_CONTEXT_GAPS | Ratificaciones de tabla anterior, adaptadores/capabilities/guardas y pruebas futuras, D01–D08 preservados, autorización operacional separada. |
