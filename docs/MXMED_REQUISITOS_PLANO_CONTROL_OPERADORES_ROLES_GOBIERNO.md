# Requisitos del plano de control de operadores, roles y gobierno — México Médico

**Contrato:** `MXMED_OPERATOR_CONTROL_PLANE_REQUIREMENTS_V1`
**Versión:** `1.1.0`
**Fecha contractual:** 2026-07-18
**Estado documental:** `documented_required_future`
**Autoridad:** requerimiento de dirección de plataforma
**Implementación confirmada por este contrato:** ninguna
**Código funcional modificado:** 0

## 1. Portada y versión

Este documento es el contrato canónico preliminar para el futuro plano interno de control de México Médico. Define separación de accesos, responsabilidades, controles, módulos, riesgos y preguntas que deberán auditar producto, diseño, soporte, seguridad e ingeniería antes de implementar.

Referencias:

- [Registro maestro de deuda](./MXMED_REGISTRO_MAESTRO_DE_DEUDA_PRODUCTO.md)
- [Inventario global](./MXMED_INVENTARIO_GLOBAL_PANTALLAS_FUNCIONES_APIS_DATOS.md)
- [Contrato maestro y PP-Decisiones](./PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md)

## 2. Propósito

Formalizar el requisito del director de disponer de una consola interna gobernada para dirección, administración, operación, soporte, revisión, moderación, facturación, privacidad, seguridad, auditoría y observabilidad técnica. El contrato evita que un plan comercial, una cadena `admin` o un bypass global se conviertan en autorización.

## 3. Alcance

Incluye requisitos documentales para:

- tres planos de acceso independientes;
- catálogo preliminar de roles internos;
- autoridad de dirección y recuperación de emergencia;
- lifecycle de operadores internos;
- módulos de consola, casos, colas y notificaciones;
- acciones R0–R3, doble aprobación y separación de funciones;
- sesiones asistidas, enmascaramiento y auditoría;
- principios futuros de UX, API y datos;
- impacto en el ciclo oficial de 22 actividades.

No implementa dashboard, login interno, MFA, APIs, tablas, permisos, casos, colas, sesiones privilegiadas, auditoría de plataforma ni runbooks ejecutables. Tampoco declara que los nombres preliminares sean definitivos.

## 4. Relación con el ciclo 1/22

- Contador principal: `1/22`.
- Actividad 1: `PRODUCT-AUDIT/MXMed-Plans-Capabilities-Ownership-Lifecycle-Audit-01`.
- Estado de la Actividad 1: `concluida` en PP276.
- Estado de la Actividad 2: `bloqueada` hasta aprobación directoral.
- Ciclo AWS offline: `24/24 concluido`; despliegue real no iniciado y tráfico `NO-GO`.

Este amendment no incrementa el contador, no agrega actividades y no crea una Microfase 25.

## 5. Estado actual

La inspección fue estática. La presencia de un nombre no certifica autorización, persistencia efectiva ni equivalencia frontend/backend.

| Hallazgo versionado | Evidencia | Lectura permitida |
|---|---|---|
| Superficie `p-ag-operadores` | `index.html` | Administración parcial de colaboradores de Agenda por el profesional; no es consola interna de plataforma |
| Roles `operator` y `assistant` | `modules/agenda/controllers/OperatorsController.php` | Roles acotados al contexto de Agenda; requieren auditoría especializada |
| Ocho rutas `/operators` | `api/agenda/index.php` | API de colaboradores de Agenda; no confirma API administrativa interna |
| Tres entidades `agenda_operator_*` | `modules/agenda/db/operators_phase1.sql` | Persistencia acotada a Agenda, permisos por módulo y audit trail local |
| Siete rutas `medical-groups` | `modules/agenda/routes.php` | Flujos candidatos de creación/revisión; no equivalen a case management de plataforma |
| `medical_group_review_log` | `modules/agenda/db/medical_groups_schema.sql` | Bitácora acotada al dominio, no auditoría administrativa transversal |
| `use_for_platform_admin` | `modules/profiles/db/doctor_contact_points_schema.sql` | Bandera de uso de contacto, no rol `platform_admin` |
| `admin`/`system` en comentario contractual SQL | `modules/profiles/db/2026_06_20_create_subscription_contract_acceptances.sql` | Nombres conceptuales, no catálogo RBAC implementado |
| `break-glass-role` y `security-audit-role` | `infra/aws/lib/constructs/security-role-factory.ts` | Roles de infraestructura AWS, no roles del producto ni consola |

Estado rector: `partial_domain_specific_evidence_only`. No se confirmó una implementación integral del plano interno. Login interno, MFA administrativo, director de plataforma, break-glass de producto, doble aprobación, sesiones asistidas, case management de soporte, colas internas y auditoría administrativa transversal quedan `documented_required_future` y sujetos a auditorías especializadas.

## 6. Principios

1. Menor privilegio y denegación por defecto.
2. Un plan comercial nunca concede un rol interno.
3. Un rol interno no hereda capacidades de plan.
4. Ser médico o owner no convierte a la persona en administradora de plataforma.
5. Autorización por rol, permiso explícito, scope, riesgo, contexto de caso y aprobación.
6. Ninguna cuenta puede autoelevarse ni aprobar su propia elevación.
7. Acciones sensibles requieren motivo, reautenticación y auditoría proporcional.
8. Datos y secretos se minimizan y enmascaran; no se incluyen en URLs ni logs.
9. Break-glass es excepcional, temporal, visible y revisado posteriormente.
10. No se usa `is_superadmin=true`, `allow_all=true` ni `skip_authorization=true` como bypass.
11. La evidencia de un dominio no se generaliza a toda la plataforma.
12. Toda implementación futura debe ser auditable y reversible cuando sea posible.

## 7. Tres planos de acceso

| Plano | Actores | Fuente de autoridad | Restricción esencial | Estado |
|---|---|---|---|---|
| Customer/professional | público, perfil no reclamado, owner, planes free/basic/standard/optimum/professional, colaboradores y enlaces seguros de paciente | plan + rol funcional + ownership + entity scope + suscripción + capability + cuota | no concede permisos internos | parcialmente observado; requiere PG-01/PG-08 |
| Internal operator | asesores, soporte, revisores, moderadores, billing, operaciones, auditoría y administración | identidad interna + estado de operador + rol + permiso + scope + riesgo + caso + aprobación | no depende del plan comercial | `documented_required_future` |
| Governance/emergency | dirección de plataforma, privacidad/seguridad y recuperación excepcional | nombramiento gobernado + MFA + reautenticación + doble control + expiración + auditoría | no es operación cotidiana | `documented_required_future` |

Los tres planos se modelan y auditan por separado. PG-01 deberá impedir `plan-derived admin role`; PG-08 deberá demostrar el enforcement equivalente.

## 8. Catálogo preliminar de roles

Los nombres son propuestas sujetas a PG-01 y PG-10. `proposed_required_role` significa requisito futuro, no rol creado.

| Rol preliminar | Propósito | Módulos visibles | Acciones permitidas | Acciones prohibidas | Sensibilidad máxima | Aprobación | MFA | Access review | Auditoría | Estado |
|---|---|---|---|---|---|---|---|---|---|---|
| `platform_director` | gobierno, personal interno y recuperación organizacional | todos según scope, con datos minimizados | nombrar/suspender/revocar mediante flujos gobernados; revisar auditoría; aprobar R3 | secretos, tarjetas, rutina clínica, borrar rastro o bypass | alta, no secretos ni clínica rutinaria | doble para R3 | obligatoria | periódica reforzada | detallada e independiente | `proposed_required_role` |
| `break_glass_superadmin` | recuperación excepcional por incidente | sólo módulos necesarios durante la sesión | acciones temporales expresamente aprobadas | uso cotidiano, acceso ilimitado o permanente | excepcional y mínimo necesario | doble | obligatoria + reautenticación | por cada uso | inmutable + revisión posterior | `proposed_required_role` |
| `platform_admin` | configuración operativa y personal dentro de límites | staff, configuración, usuarios y casos por scope | operar R0–R2 autorizadas | autoelevarse, crear director unilateralmente, desactivar último director, ver secretos | alta no clínica | según riesgo | obligatoria | periódica | detallada | `proposed_required_role` |
| `operations_manager` | supervisión de asesores, carga, casos e incidentes | home, casos, colas, alertas y staff acotado | asignar, escalar y balancear trabajo | cambiar políticas de seguridad o datos clínicos | operativa enmascarada | R2/R3 | obligatoria | periódica | detallada | `proposed_required_role` |
| `support_advisor` | asistencia de cuenta y navegación | usuarios enmascarados, casos y guía | lectura mínima, seguimiento y recuperación guiada | expediente, pagos sensibles, secretos y cambios críticos | cuenta enmascarada | para escalamiento | obligatoria | periódica | por caso | `proposed_required_role` |
| `profile_claim_reviewer` | reclamos, identidad, duplicados, disputas y transferencias | claims, usuarios y evidencia referenciada | revisar, solicitar evidencia, recomendar/aprobar según riesgo | acceso clínico o transferencias disputadas sin control | identidad mínima necesaria | R2/R3 | obligatoria | periódica | por caso y decisión | `proposed_required_role` |
| `billing_subscription_operator` | planes, renovación, gracia, comprobantes y conciliación | suscripciones, excepciones y casos | conciliación y activación controlada | tarjeta, secretos Stripe, cargos paralelos u override directo | pagos sin tarjeta | override R3 | obligatoria | periódica | detallada | `proposed_required_role` |
| `content_moderator` | reseñas, denuncias, spam y perfiles falsos | moderación y casos | ocultar/restringir de forma reversible según política | suscripciones, seguridad o clínica | contenido público/denunciado | apelaciones críticas | obligatoria | periódica | decisión y razón | `proposed_required_role` |
| `privacy_security_officer` | privacidad, incidentes, derechos, retención y accesos extraordinarios | privacidad, seguridad, audit y casos | aprobar acceso excepcional y gestionar solicitudes | operar fuera de propósito o borrar auditoría | alta bajo mínimo necesario | R3 doble | obligatoria + reautenticación | reforzada | inmutable | `proposed_required_role` |
| `technical_operations_viewer` | salud, alarmas, backups, versiones, despliegues y costos | operación técnica | lectura técnica y escalamiento | mutaciones de negocio, secretos o datos clínicos | telemetría saneada | mutación no permitida | obligatoria | periódica | accesos y exportes | `proposed_required_role` |
| `audit_read_only` | cumplimiento, acciones, aprobaciones y accesos | audit y reportes permitidos | leer y exportar con aprobación | cualquier mutación | registros saneados | exportación sensible | obligatoria | periódica | accesos al audit | `proposed_required_role` |

Los términos actuales `operator`, `assistant`, `administrator`, `moderator`, `admin`, `platform_admin` y los roles AWS son candidatos o coincidencias que requieren auditoría; no se consideran aliases definitivos.

## 9. Autoridad de dirección

`platform_director` conserva la máxima autoridad organizacional para alta/baja de asesores y administradores, asignación/revocación de roles, suspensión de operadores, recuperación de control, auditoría, aprobación crítica, designación de responsables y resolución de bloqueos.

La autoridad se ejerce por permisos, scopes, reautenticación, doble control, break-glass y auditoría. No permite revelar secretos, contraseñas o tarjetas; realizar atención clínica rutinaria; desactivar seguridad; borrar huellas; ni aprobar la propia elevación.

Protecciones obligatorias:

- no desactivar ni retirar el rol al último `platform_director` activo;
- conservar al menos un mecanismo de recuperación gobernado;
- impedir que un director modifique o elimine su propio rastro;
- reforzar confirmación de acciones irreversibles;
- separar iniciador y aprobador en acciones R3.

## 10. Break-glass

`break_glass_superadmin` representa una sesión de emergencia, no una cuenta cotidiana.

Requisitos:

- incidente activo y motivo codificado;
- identidad nominativa previamente autorizada;
- MFA y reautenticación;
- aprobación independiente;
- scope mínimo y duración explícita;
- banner persistente y correlación de sesión;
- auditoría inmutable de acceso y acciones;
- revocación automática al expirar;
- revisión posterior obligatoria.

La duración máxima, responsables y excepciones se deciden en PG-01/PG-02/PG-08/PG-10. No se presume que el rol AWS de break-glass implemente este contrato de producto.

## 11. Lifecycle de operadores

Estados preliminares:

`invited → pending_verification → pending_mfa → active → temporarily_suspended | access_review_required → revoked → archived`

Flujo contractual:

`invitation → identity verification → password creation → MFA enrollment → role assignment → policy acknowledgment → activation → periodic access review → suspension/revocation → archival`

Reglas:

- invitación privada, nominativa y con expiración; sin registro público;
- cada persona crea su contraseña; nadie genera una contraseña para otra;
- MFA obligatorio antes de activación;
- rol y scope requieren asignación autorizada y dejan auditoría;
- permisos temporales expiran y operadores inactivos entran a revisión;
- suspensión y revocación invalidan sesiones;
- baja o archivo no eliminan historial;
- no autoelevación ni delegación fuera de política.

La superficie actual de Agenda que muestra contraseña temporal es evidencia de un flujo distinto del plano profesional y no satisface este lifecycle interno.

## 12. Módulos de la consola

Todas las filas son `required_future_control_plane_surfaces`; no alteran los totales implementados del inventario.

| Módulo | Roles principales | Lecturas | Escrituras | Datos restringidos | Aprobaciones/auditoría | Dependencias | Grupo |
|---|---|---|---|---|---|---|---|
| Operations home | director, admin, operations manager | pendientes, incidentes, carga y alertas | asignar/escalar según riesgo | tarjetas sin datos sensibles; sin clínica | por acción; resumen no sustituye caso | cases, queues, RBAC | PG-10 |
| Users and accounts | admin, support, security | búsqueda enmascarada, estado, sesiones e historial | suspender/reactivar/revocar sesiones | contraseña, secretos y clínica | R2 con motivo/caso | PG-01/02/08 | PG-10 |
| Profile claims and ownership | claim reviewer, operations manager | reclamos, evidencia, duplicados y disputa | aprobar/rechazar/transferir/revocar | identidad mínima | R2/R3 y doble control en disputa | ownership contract | PG-01/02/10 |
| Subscriptions and billing | billing operator | plan, estado, gracia, comprobantes e inconsistencias | conciliación/reactivación controlada | tarjeta y secretos Stripe | override R3 | PG-01/06/08 | PG-06/10 |
| Support cases | support, operations manager | conversación, estado y evidencia referenciada | crear/asignar/escalar/cerrar | mínimo por propósito | toda acción sensible ligada a caso | case model | PG-10 |
| Content moderation | moderator | reseñas, denuncias y spam | restringir/restaurar según política | identidad innecesaria oculta | razón, apelación y audit | PG-07/08 | PG-10 |
| Notification operations | operations manager, support | eventos, fallos, tareas y alertas | asignar/reintentar/escalar | sin clínica en tarjeta | historial y correlación | PG-05/08 | PG-05/10 |
| Internal staff | director, admin, security | estado, rol, MFA, sesiones y vigencia | invitar/suspender/revocar/asignar | credenciales y factores nunca visibles | R2/R3; último director protegido | PG-01/02/08 | PG-10 |
| Privacy and security | security officer | incidentes, solicitudes, retención y actividad | gestionar solicitud/acceso excepcional | máxima sensibilidad bajo scope | R3 y auditoría inmutable | PG-08 | PG-10 |
| Audit | audit read-only, director, security | actor, scope, caso, cambio, resultado y correlación | ninguna para auditor | payloads y secretos excluidos | acceso/exportación auditados | audit store independiente | PG-08/10 |
| Technical operations | technical viewer | salud, alarmas, backups, costos, versión y despliegue | ninguna por defecto | secretos y datos de negocio excluidos | acceso auditado | contratos AWS/ops | PG-10 |
| Platform configuration | director, admin | políticas, catálogos, gates y límites no secretos | cambiar configuración explícita | secretos fuera de consola | R2/R3, doble aprobación según política | PG-01/08 | PG-10 |

## 13. Case management

Las acciones sensibles de soporte se asocian a un caso, incidente, aprobación y motivo.

Modelo conceptual futuro: `case_id`, `case_type`, `priority`, `requester`, `subject_entity`, `assigned_operator`, `status`, `reason`, `evidence_reference`, `actions`, `escalation`, `resolution` y timestamps.

Tipos preliminares: `account_access`, `profile_claim`, `profile_dispute`, `billing`, `moderation`, `privacy`, `security`, `technical`, `clinical_access_request`, `operator_access` e `incident`.

No se crea tabla. Los `clinical_cases` actuales pertenecen al dominio clínico y no implementan este modelo operativo.

## 14. Sesión asistida

`support_assisted_session` sustituye cualquier suplantación silenciosa genérica. Requiere caso activo, motivo, scope, duración, banner, auditoría, consentimiento cuando sea posible, lectura por defecto, datos enmascarados, expiración y revocación.

Durante la sesión se prohíbe ver o cambiar contraseñas fuera de flujo, acceder a secretos, capturar tarjetas, confirmar pagos, abrir expediente completo, exportar datos, modificar permisos, desactivar MFA, eliminar cuentas o ejercer acciones de dirección.

El acceso clínico extraordinario no forma parte de la sesión ordinaria: exige `privacy_security_officer`, caso, necesidad documentada, mínimo alcance, break-glass, doble aprobación, auditoría y revisión posterior.

## 15. Riesgo de acciones R0–R3

| Nivel | Tipo | Ejemplos | Control mínimo |
|---|---|---|---|
| R0 | lectura | consultar estado, caso o historial no sensible | autorización, scope y auditoría básica |
| R1 | reversible | reenviar aviso, reasignar tarea, reintentar entrega | confirmación y auditoría |
| R2 | sensible | suspender cuenta, transferir perfil, cambiar rol, revocar sesiones | motivo, reautenticación, caso y auditoría detallada |
| R3 | crítica | crear director, break-glass, acceso clínico excepcional, exportación masiva, eliminación, retención, override de pago o política de seguridad | doble aprobación, MFA, reautenticación, caso/incidente, expiración, audit inmutable y revisión posterior |

La clasificación concreta de cada endpoint se cerrará en PG-08/PG-10.

## 16. Doble aprobación

Las acciones candidatas R3 requieren `initiator + approver`, con cuentas distintas y vigentes. Incluyen designar director; afectar al último director; activar break-glass; exportar masivamente; acceder de forma clínica excepcional; eliminar irreversiblemente; cambiar retención; transferir propiedad disputada; hacer override de pago; restaurar backup en producción; habilitar tráfico público; y cambiar política de seguridad.

El aprobador verifica caso, motivo, scope, riesgo, vigencia y resultado. La aprobación expira y no se reutiliza para otra acción.

## 17. Separación de funciones

- soporte no aprueba su propio escalamiento crítico;
- facturación no modifica auditoría;
- moderación no cambia suscripciones;
- technical viewer no muta datos de negocio;
- auditoría read-only no muta;
- operador no aprueba su propia elevación;
- director cotidiano no usa break-glass sin incidente y registro;
- quien ejecuta una acción no edita su evidencia;
- plan comercial, ownership y rol funcional no otorgan privilegio interno.

## 18. Enmascaramiento y privacidad

La consola futura aplica mínimo necesario, `field-level masking`, limitación por propósito, acceso por caso, scope, separación read/write, restricción de descargas, aprobación de exportación, ausencia de datos en URLs y ausencia de datos sensibles en logs.

Correo y teléfono se muestran parcialmente; identificadores completos sólo si son imprescindibles; contenido clínico queda oculto por defecto; soporte general no obtiene preview clínico; pagos excluyen datos de tarjeta; secretos nunca son visibles.

## 19. Auditoría administrativa

Contrato conceptual de evento:

`action_id`, `occurred_at`, `actor_operator_id`, `actor_role`, `active_scope`, `case_id`, `incident_id`, `target_type`, `target_id` pseudonimizado cuando proceda, `action_code`, `risk_level`, `reason_code`, `before_summary`, `after_summary`, `result`, `approval_reference`, `correlation_id`, `source_session`, `break_glass_session_id` y `retention_class`.

No se registran contraseñas, tokens, cookies, secretos, `client_secret`, firmas de webhook, contenido clínico completo, cuerpos completos de solicitud ni datos de tarjeta. El mismo operador que actúa no puede editar el evento.

La tabla actual `agenda_operator_audit_events` es evidencia parcial de Agenda y no satisface por inferencia este contrato transversal.

## 20. Notificaciones internas

El plano interno es distinto del buzón del usuario. Tipos preliminares: `task`, `case_assignment`, `approval_required`, `incident`, `security_alert`, `billing_exception`, `claim_review`, `moderation`, `privacy_request` y `system_warning`.

Estados: `new`, `assigned`, `acknowledged`, `in_progress`, `waiting_external`, `waiting_approval`, `resolved`, `closed` y `escalated`.

Cada tarjeta evita datos clínicos, enlaza al caso, define SLA, prioridad, responsable, expiración, escalamiento e historial. Persistencia y delivery no están confirmados.

## 21. UX y accesibilidad

Requisitos preliminares: navegación modular; visibilidad por rol; dashboard por responsabilidad; búsqueda con scopes; tablas densas legibles; filtros persistentes seguros; diferenciación de acciones primarias/críticas; motivos y confirmaciones reforzadas; estados empty/loading/error/success/conflict/insufficient-permission; masking; read-only; banners de sesión asistida y break-glass; teclado, lector, contraste y foco.

Las mutaciones críticas son desktop-first; tablet sólo con operación controlada; móvil se limita inicialmente a lectura/alertas hasta una decisión. Este contrato no contiene diseño final ni bocetos.

## 22. Principios API

Toda API administrativa futura verifica autenticación interna, MFA, estado de operador, rol, permiso, scope, requisito de caso, nivel de riesgo, aprobación, reautenticación, idempotencia, auditoría y rate limit.

Modelo recomendado:

`RBAC + scope + action risk + case context + approval state`

No basta `role=admin`, `is_admin` ni un permiso global. Los endpoints actuales de Agenda y suscripciones deben auditarse en PG-01/PG-08 antes de clasificar cualquier reutilización.

## 23. Entidades conceptuales futuras

Todas se marcan `conceptual_future_entity`:

- `platform_operators`;
- `platform_roles`;
- `platform_permissions`;
- `platform_role_permissions`;
- `platform_operator_role_assignments`;
- `platform_access_reviews`;
- `platform_cases`;
- `platform_case_assignments`;
- `platform_case_actions`;
- `platform_approval_requests`;
- `platform_privileged_sessions`;
- `platform_break_glass_sessions`;
- `platform_assisted_sessions`;
- `platform_operator_notifications`;
- `platform_audit_events`;
- `platform_reason_codes`.

La lista no obliga a crear una tabla por entidad. PG-08 decidirá normalización, retención, índices y relación con datos actuales.

## 24. Impacto en PG-01/02/05/06/08/09/10

| Grupo | Amendment obligatorio |
|---|---|
| PG-01 | separar entitlements comerciales, roles funcionales, ownership y permisos internos; auditar jerarquía, riesgo, director, break-glass, no autoelevación y no plan-derived admin role |
| PG-02 | invitación, login interno, recuperación, MFA, sesiones administrativas, suspensión, revocación y sesión break-glass |
| PG-05 | buzón interno, colas, tareas, aprobaciones, escalamiento y alertas administrativas |
| PG-06 | operación de facturación, conciliación, activación manual gobernada y override sólo como R3 |
| PG-08 | RBAC, scopes, caso, approvals, masking, auditoría, access reviews, retención y separación de funciones |
| PG-09 | consola, tablas, filtros, estados, accesibilidad y UX de sesiones asistidas/break-glass |
| PG-10 | módulos, personal, soporte, reclamos, moderación, privacidad, auditoría, sesiones asistidas, acciones críticas y dirección |

### Alcance obligatorio de la Actividad 1 de 22

`PRODUCT-AUDIT/MXMed-Plans-Capabilities-Ownership-Lifecycle-Audit-01` auditará por separado commercial entitlements, user functional roles, ownership/entity scope e internal operator permissions. Deberá responder qué fuentes actuales definen roles; cuáles son de usuario o administrativos; qué aliases existen; si un plan concede por accidente privilegios internos; si `admin` es demasiado amplio; cómo se separan `platform_director` y break-glass; qué permisos administrativos existen o faltan; qué acciones requieren doble aprobación; cómo se impide autoelevación; y qué datos puede ver cada operador.

La Actividad 1 es una auditoría, no una implementación de consola. Este amendment sólo la desbloquea; no la inicia.

Nombre oficial de PG-10: **Consola operativa, administración, soporte, moderación y gobierno de plataforma**.

Orden preservado: `PG-01 → PG-02 → PG-08 → PG-03 → PG-04 → PG-06 → PG-05 → PG-07 → PG-09 → PG-10 → PG-11`.

## 25. Deudas relacionadas

El [registro maestro](./MXMED_REGISTRO_MAESTRO_DE_DEUDA_PRODUCTO.md) conserva la fuente canónica. Este amendment amplía `ADM-001`, `ADM-002`, `AUTH-004`, `DATA-002`, `CAP-008`, `NOT-001` a `NOT-005`, `REV-002`, `PRIV-001/002`, `SUB-002` y `UX-005`; y agrega deudas específicas `ADM-003` a `ADM-008` y `DOC-006` sin duplicar temas existentes.

## 26. Preguntas pendientes

No bloquean este amendment; cada una se resolverá en auditoría.

| Pregunta | Grupo responsable |
|---|---|
| ¿Cuántos directores activos como mínimo? | PG-01 |
| ¿Toda acción R3 exige dos personas? | PG-08 |
| ¿Quién puede nombrar un director? | PG-01 |
| ¿Cuál es la duración máxima de break-glass? | PG-02 |
| ¿Cuál es la duración de sesión asistida? | PG-10 |
| ¿Qué alcance tendrá móvil? | PG-09 |
| ¿Qué retención aplica a auditoría administrativa? | PG-08 |
| ¿Cómo se gobierna el acceso clínico extraordinario? | PG-08 |
| ¿Cómo se aprueba una exportación masiva? | PG-08 |
| ¿Existe override de pagos y bajo qué R3? | PG-06 |
| ¿Se permiten operadores externos temporales? | PG-01 |
| ¿Habrá segregación por región o unidad? | PG-01 |
| ¿Cómo se modelan horarios y turnos internos? | PG-10 |
| ¿Cuál es el SLA interno de soporte? | PG-10 |
| ¿Qué formación y aceptación de política se exige? | PG-02 |
| ¿Con qué periodicidad se revisan accesos? | PG-08 |

## 27. Criterios de aceptación

Este contrato queda aceptado cuando:

- los tres planos permanecen separados;
- los 11 roles preliminares declaran propósito, límites y controles;
- dirección y último director están protegidos sin bypass;
- lifecycle, 12 módulos, casos, sesión asistida, R0–R3, doble aprobación y separación de funciones están definidos;
- masking, auditoría, notificaciones, UX, API y entidades futuras quedan contractuales sin afirmar implementación;
- las 16 preguntas tienen grupo;
- el registro de deuda, inventario global y PP275 son consistentes;
- el contador sigue `1/22` y la Actividad 2 permanece bloqueada;
- código, schemas, tests e infraestructura modificados son 0.

## 28. No repetición

- Reutilizar la evidencia de Agenda sólo como candidato del plano profesional.
- No crear otro backend de suscripciones ni reabrir cierres Stripe/AWS.
- No convertir nombres de roles AWS en roles de producto.
- No duplicar deudas ya cubiertas; ampliar primero.
- No inventar endpoints, tablas, cuentas, secretos, personas ni datos clínicos.
- No iniciar PG-01 desde este amendment.

## 29. HISTORICAL FUNCTIONAL SOURCES RECONCILIATION AMENDMENT

**Contrato:** `MXMED_HISTORICAL_FUNCTIONAL_DOCUMENTS_RECONCILIATION_V1`
**Autoridad de las fuentes:** `historical_noncanonical`
**Resultado:** crosswalk y requisitos futuros; cero roles creados o declarados definitivos.

### Crosswalk de roles históricos

| Rol histórico | Equivalencia propuesta | Clasificación/límite |
|---|---|---|
| Administrador Principal / Súper Administrador | `platform_director` + `platform_admin` scopiado + `break_glass_superadmin` | acceso universal cotidiano `superseded` |
| Operador Administrativo Económico | `billing_subscription_operator` | conciliación; pago manual/override R3 |
| Operador de Asistencia Técnica | `support_advisor` + `technical_operations_viewer` | recuperación y observabilidad separadas |
| Operador de Verificación/Clasificación | `profile_claim_reviewer` + `content_moderator` | claim, publicación y grupos separados |
| Operador de Mercadotecnia/Difusión | role o permission set pendiente | `requires_specialized_audit`; sin clínica |
| Operador de Citas Global | servicio scopiado/temporal pendiente | no reutilizar operador Agenda por inferencia |
| Administrador/Titular de perfil | ownership + rol funcional por entidad | nunca rol interno de plataforma |
| Operador de Perfil/Asistente | `agenda_operator` scopiado | Agenda only; no soporte global |
| Responsable Sanitario | rol institucional pendiente | no se convierte automáticamente en owner/director |

No se crean roles. Mercadotecnia y citas globales requieren decidir si son roles
separados, permisos, scopes temporales o variantes gobernadas. El plan comercial
no concede ninguna equivalencia.

### Conflictos y elementos superseded

- “acceso universal” se sustituye por permisos explícitos, caso, MFA, riesgo,
  doble aprobación y break-glass temporal;
- dos cuentas históricas no fijan el mínimo actual de directores;
- 2FA de perfiles reclamados refina AUTH-004, pero no sustituye MFA interno;
- passkeys opcionales quedan futuras;
- responsable sanitario, representante y owner son autoridades diferentes;
- publicación/moderación no reutiliza estados de claim o suscripción.

### Dry-run y agentes

`dryRun:true` permanece requisito futuro para agentes y acciones sensibles. Debe
devolver acción, scope, targets, efectos y denials sin ejecutar. IA interna usa
tools allowlist, datos mínimos, caso, aprobación humana, audit y kill switch. El
modelo “todos los campos” queda rechazado.

### Publication moderation

Máquina candidata independiente: `draft`, `pending_review`, `approved`,
`published`, `changes_pending_review`, `suspended`. La cola requiere owner, SLA,
escalamiento, before/after, aprobar/rechazar/pedir ajustes y auditoría. Claim
reviewer y content moderator no obtienen privilegios de pago o clínica.

### Payments manual review

| Acción | Riesgo | Control mínimo |
|---|---|---|
| reenviar enlace | R1 | canal permitido, audit e idempotencia |
| aplicar prórroga | R2 | caso, motivo, duración aprobada; no altera alta/ranking |
| registrar/acreditar pago manual | R3 | referencia, comprobante, conciliación y doble aprobación |
| override de pago | R3 | excepción específica, reauth, audit y revisión posterior |

No se implementan SPEI, acreditación, CFDI o cambio de plan. Stripe protegido no
se reabre. Documento fuente: [reconciliación histórica](./MXMED_RECONCILIACION_DOCUMENTACION_HISTORICA_FUNCIONAL.md).

## 30. Historial de cambios

| Versión | Fecha | Cambio | Autoridad |
|---|---|---|---|
| 1.0.0 | 2026-07-17 | Contrato documental inicial del plano interno, roles y gobierno; contador 0/22 sin cambio | `PRODUCT-DOC/MXMed-Operator-Control-Plane-And-Platform-Roles-Requirement-Amendment-01` |
| 1.1.0 | 2026-07-18 | Crosswalk histórico, superadmin superseded, roles candidatos, publication moderation, dry-run y pagos manuales R1–R3; contador 1/22 | `PRODUCT-AUDIT/MXMed-Historical-Functional-Documents-Reconciliation-01` |
