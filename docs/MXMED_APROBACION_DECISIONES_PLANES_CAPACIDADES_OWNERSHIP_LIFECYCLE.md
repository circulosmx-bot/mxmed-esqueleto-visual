# Aprobación directoral — Planes, capacidades, ownership y lifecycle MXMed

## 1. Portada

- Actividad: `PRODUCT-DOC/MXMed-Plans-Capabilities-Ownership-Lifecycle-Director-Decision-Approval-01`.
- Tipo: actividad auxiliar documental; no incrementa el contador principal.
- Fecha contractual: 2026-07-18.
- Resultado documental: `DIRECTOR_APPROVED_CONTRACT`.
- Contador: `1/22`; pendientes principales: 21.
- Actividad 2: `UNBLOCKED_READY_NOT_STARTED` sólo al cerrar QA de este contrato.

## 2. Contrato y versión

- Contrato: `MXMED_PLANS_CAPABILITIES_OWNERSHIP_LIFECYCLE_DIRECTOR_DECISION_APPROVAL_V1`.
- Versión: `1.0.0`.
- Decisiones originales: 11.
- Decisiones atómicas aprobadas: 30/30.
- Estado común: `director_approved`.
- Implementación autorizada por esta actividad: no.

## 3. Propósito

Formalizar la decisión del director sobre la política comercial, capacidades,
ownership, lifecycle y gobierno necesarios para preparar la Actividad 2. La
aprobación elimina el bloqueo de decisión, pero no demuestra ni ejecuta código,
datos, roles, proveedores, precios finales, infraestructura o comportamiento
runtime.

## 4. Baseline

- Rama fuente: `audit/mxmed-historical-functional-documents-reconciliation`.
- HEAD: `773d16a7bce2bd4677d9111eba2603bb54415e5a`.
- PP277: presente y único.
- Reconciliación histórica: PASS; 8 fuentes, 29 páginas, 95 requisitos y 22 triggers.
- Código funcional modificado en el baseline histórico: 0.

## 5. Fuentes contractuales

1. [PP273 — registro maestro de deuda](./MXMED_REGISTRO_MAESTRO_DE_DEUDA_PRODUCTO.md).
2. [PP274 — inventario global](./MXMED_INVENTARIO_GLOBAL_PANTALLAS_FUNCIONES_APIS_DATOS.md).
3. [PP275 — plano de operadores](./MXMED_REQUISITOS_PLANO_CONTROL_OPERADORES_ROLES_GOBIERNO.md).
4. [PP276 — auditoría PG-01](./MXMED_AUDITORIA_PLANES_CAPACIDADES_OWNERSHIP_LIFECYCLE.md).
5. [PP277 — reconciliación histórica](./MXMED_RECONCILIACION_DOCUMENTACION_HISTORICA_FUNCIONAL.md).
6. Instrucción explícita actual del director contenida en esta actividad.

## 6. Precedencia

La decisión directoral formalizada aquí prevalece para producto sobre el
borrador DEC-001–DEC-011 y sobre fuentes históricas. La policy versionada será
autoridad funcional; el backend será autoridad de enforcement; datos
versionados alimentarán catálogo, precios, suscripciones y overrides
autorizados; frontend, tests, fixtures y documentación serán consumidores.

La precedencia no transforma automáticamente el código actual en correcto ni
autoriza contradecir seguridad, clínica, privacidad, Stripe o AWS.

## 7. Metodología de aprobación

Cada decisión se descompuso de la decisión original, se contrastó con PP273–277
y recibió estado final, alcance, exclusiones, dependencias, PG, deuda, actividad
futura y nota de versionado. No se ejecutó QA runtime. Los parámetros que no
son necesarios para modelar la policy quedaron diferidos y fail-closed.

## 8. Relación con DEC-001–DEC-011

| Decisión original | Decisiones atómicas finales | Cantidad |
|---|---|---:|
| DEC-001 | DEC-001 | 1 |
| DEC-002 | DEC-002 | 1 |
| DEC-003 | DEC-003A, DEC-003B, DEC-003C, DEC-003D, DEC-003E, DEC-003F | 6 |
| DEC-004 | DEC-004A, DEC-004B, DEC-004C | 3 |
| DEC-005 | DEC-005A, DEC-005B, DEC-005C, DEC-005D, DEC-005E | 5 |
| DEC-006 | DEC-006A, DEC-006B | 2 |
| DEC-007 | DEC-007A, DEC-007B | 2 |
| DEC-008 | DEC-008A, DEC-008B | 2 |
| DEC-009 | DEC-009A, DEC-009B | 2 |
| DEC-010 | DEC-010A | 1 |
| DEC-011 | DEC-011A, DEC-011B, DEC-011C, DEC-011D, DEC-011E | 5 |
| **Total** | **30 decisiones** | **30** |

## 9. Catálogo de 30 decisiones aprobadas

Todas las filas tienen `implementationAuthorized=false`.

| decisionId / título | Estado anterior → final | Decisión aprobada y alcance | Exclusiones | Dependencias | PG | Deuda | Actividad prevista | Versionado |
|---|---|---|---|---|---|---|---|---|
| DEC-001 — Códigos canónicos | draft | `director_approved`; free/basic/standard/optimum/professional y nombres visibles | `free_default` no es plan; aliases sólo compatibilidad | policy versionada | PG-01 | CAP-001 | Actividad 2 | códigos estables; copy versionable |
| DEC-002 — Fuente canónica | refined draft | `director_approved`; `HYBRID_VERSIONED_POLICY_V1`, backend autoridad | plan no sustituye ownership, rol, scope, estado, cuota o seguridad | adapters/read-model | PG-01/08 | CAP-001/003/008, DATA-002 | Actividad 2 | policy con versión y artefactos derivados |
| DEC-003A — Matriz base | split draft | `director_approved`; matriz Free→Professional para médico individual | instituciones diferidas; Professional no agrega toda IA | DEC-001/002 | PG-01 | CAP-001/009 | Actividad 2 | matriz versionada |
| DEC-003B — Agente Agenda Professional | split draft | `director_approved`; `ai_agenda_agent`, sólo Agenda/público, confirmación y fail-safe | sin clínica, finanzas o administración | provider futuro; tools allowlist | PG-01/11 | AI-001/002/003 | modelado en Actividad 2; implementación PG-11 | provider/cuota técnica diferidos |
| DEC-003C — Call Center complemento | split draft | `director_approved`; add-on por perfil médico Standard+ y una Agenda | no instituciones; no clínica/pagos/ownership | framework add-on | PG-01/10 | CAP-011, ADM-001 | contrato en Actividad 2; operación futura | activación expresa versionada |
| DEC-003D — Modalidades Call Center | split draft | `director_approved`; Complementario e Integral, ilimitado comercial con fair use | sin excedentes; no servicio implementado | DEC-003C | PG-01/10 | CAP-011 | contrato en Actividad 2; operación futura | ventanas/cobertura configurables |
| DEC-003E — Precio y ciclo Call Center | split draft | `director_approved`; 1,999/2,999 MXN anuales tentativos, prorrateo y anticipo mensual de tres mensualidades | no precios definitivos; no cobro actual | catálogo/add-on/suscripción | PG-01/06 | CAP-011, SUB-002 | contrato en Actividad 2 | precios pre-lanzamiento versionados |
| DEC-003F — Framework de complementos | split draft | `director_approved`; plan + add-ons elegibles + activación por perfil y nueve estados | sin implementar add-ons | policy/catálogo | PG-01/06 | CAP-011, CAP-003 | Actividad 2 | código/precio/periodo versionados |
| DEC-004A — Aprobación previa | refined draft | `director_approved`; claim y alta separados, revisión humana y Free inicial | sin panel/checkout antes de aprobar; sin contraseñas en texto plano | auth/email/reviewer | PG-02/07 | OWN-001/002, AUTH-001/002 | gates Actividad 2; workflow posterior PG-02 | templates y estados versionados |
| DEC-004B — Ownership | refined draft | `director_approved`; seis estados, transferencia como flujo auditado | correo no transfiere; no elimina perfil/plan/datos | claim/revisión/notificación | PG-02/08 | OWN-002/003 | gates Actividad 2; workflow posterior | historial preservado |
| DEC-004C — Publicación e intervención | refined draft | `director_approved`; seis estados y acciones scopiadas con caso/evidencia/apelación | no cambia plan, ownership, pagos o clínica | RBAC/cases/R3 | PG-07/08/10 | ADM-009, REV-002 | estados Actividad 2; consola futura | policy y reasons versionados |
| DEC-005A — Clases de cuota | split draft | `director_approved`; comercial/técnica/seguridad/costo/fixture separadas | sólo cuota comercial es visible | backend meter | PG-01 | CAP-002/008 | Actividad 2 | unidad/cantidad/periodo/versión |
| DEC-005B — Fotografía y galería | split draft | `director_approved`; 1 principal + 21 galería, total 22, espacios activos, <300 KB | original sólo temporal; 300 KB no upsell | procesamiento/moderación | PG-01/07 | PUB-005, CAP-002 | contrato Actividad 2; procesamiento posterior | límite técnico versionado |
| DEC-005C — IA, promociones y paquetes | split draft | `director_approved`; imágenes 3/10/20/30 mensuales, agenda/fallback ilimitados con fair use | interacción medicamentosa especializada; supervisor rechazado | metering/provider/moderación | PG-01/04/11 | AI-001/003, CAP-002 | cuotas Actividad 2; IA futura | provisional pre-lanzamiento |
| DEC-005D — Redacción IA | split draft | `director_approved`; textos 15/30/60/100 mensuales con revisión | sin datos clínicos/privados; sin proveedor | metering/provider | PG-01/08/11 | AI-001/002/003 | cuotas Actividad 2; IA futura | provisional y ajustable |
| DEC-005E — Agenda ilimitada | split draft | `director_approved`; citas/reprogramaciones/cancelaciones ilimitadas Standard+ | antiabuso técnico, no comercial | Agenda entitlement | PG-01/03 | CAP-002, AGD-003 | Actividad 2 | sin cargos por excedente |
| DEC-006A — Máquina comercial | refined draft | `director_approved`; 12 estados y separación checkout/pago/activación | pending_payment no activa; impago no borra | Stripe protegido/adapters | PG-01/06 | CAP-005, SUB-002 | Actividad 2 | namespace comercial versionado |
| DEC-006B — Estado efectivo | refined draft | `director_approved`; nueve estados y cinco fuentes de capability | frontend no autoriza; fail-closed | DEC-002/006A | PG-01/08 | CAP-003/008, DATA-002 | Actividad 2 | reasons y source versionados |
| DEC-007A — Grace | conflict draft | `director_approved`; días 1–3 past_due, 4–15 grace, 16 restricted | no reinicio; no borrado; cancelación voluntaria separada | lifecycle/notificaciones | PG-01/05/06 | CAP-005/006, SUB-002/003 | Actividad 2 | ventanas versionadas |
| DEC-007B — Prórroga | conflict draft | `director_approved`; ordinaria hasta 7 días, excepcional hasta 15, estado grace_limited | no cambia renovación, precio, ranking, plan o deuda | cases/R2-R3/notificaciones | PG-05/06/08 | SUB-003/005, ADM-005 | contrato Actividad 2; operación futura | concesión y límite auditados |
| DEC-008A — Free posterior | confirmed draft | `director_approved`; ownership/admin básica, premium archived_read_only y reactivación | no writes premium; clínica no se elimina por impago | retención/export | PG-01/08 | CAP-006, PRIV-001, CLN-005 | Actividad 2 | plazos por clase diferidos |
| DEC-008B — Downgrade voluntario | confirmed draft | `director_approved`; programado a renovación, resumen, cancelación y compatibilidad de add-ons | sin reembolso automático; no borrado | scheduler/read-model | PG-01/06 | CAP-006, SUB-003 | Actividad 2 | snapshot y fecha efectiva |
| DEC-009A — Agenda/contacto/Expediente | refined draft | `director_approved`; entidades separadas y gate clínico explícito | cita no crea Expediente; Call Center/IA sin clínica | rol/plan/consentimiento | PG-03/04/08 | AGD-003/004, PAT-003 | frontera Actividad 2; clínica PG-04 | authority IDs versionados |
| DEC-009B — Datos mínimos y duplicados | refined draft | `director_approved`; contacto provisional/verificado, consentimientos separados y merge reversible | sin DOB/género indiscriminados; sin fusión automática | identity/dedupe/audit | PG-03/08 | PAT-001/003, PRIV-001 | contratos Actividad 2; workflow posterior | criterios y reason versionados |
| DEC-010A — Funciones bloqueadas | refined draft | `director_approved`; visibles, explicadas y accesibles con denial backend | no upsell para not_applicable/hidden/suspended/no aprobado | state read-model | PG-01/09 | CAP-007/008, SUB-003 | Actividad 2 | denial reasons versionados |
| DEC-011A — Planos y 12 roles | split draft | `director_approved`; tres planos y catálogo de doce roles, incluido call_center_agenda_operator | ningún plan concede rol; rol Call Center sólo citas | MFA/RBAC/last director | PG-02/08/10 | ADM-002/010, AUTH-004 | requisitos; implementación posterior | catálogo versionado |
| DEC-011B — Riesgo R0–R3 | split draft | `director_approved`; controles crecientes, doble aprobación R3 y urgencia temporal | sin autoaprobación ni bypass | cases/MFA/audit | PG-08/10 | ADM-005/006 | requisitos; implementación posterior | policy de riesgo versionada |
| DEC-011C — Sesiones asistidas | split draft | `director_approved`; support session temporal y acceso clínico extraordinario R3 | sin suplantación silenciosa ni actos médicos | case/scope/expiry | PG-02/08/10 | ADM-004/006, CLN-005 | implementación posterior | sesión y grants versionados |
| DEC-011D — Lifecycle operadores | split draft | `director_approved`; ocho estados, revisión 6/3 meses y revocación inmediata | sin autoelevación; último director protegido | identity/MFA/access review | PG-02/08 | ADM-003, AUTH-004 | implementación posterior | assignments y revisiones versionados |
| DEC-011E — Platform cases | split draft | `director_approved`; 12 tipos, 11 estados y toda acción R2/R3 vinculada | no reutilizar clinical_cases; cierre exige resolución | roles/queues/audit | PG-08/10 | ADM-004/007 | implementación posterior | tipos/estados/reasons versionados |

## 10. Matriz comercial

| Plan | Capacidades base aprobadas |
|---|---|
| `free` | presencia básica; tras aprobación/reclamo, administración básica y una fotografía principal; sin contacto público pagado, Agenda, clínica o IA comercial |
| `basic` | Free + teléfono, llamada, WhatsApp público, galería y perfil ampliado; sin Agenda |
| `standard` | Basic + Agenda, reservación, disponibilidad, bloqueos, citas y contacto operativo; sin Expediente |
| `optimum` | Standard + Expediente, Recetas y archivos clínicos bajo rol/autorización |
| `professional` | Optimum + agente IA de Agenda y automatización aprobada; otras IA independientes |

Alcance inicial: perfiles médicos individuales. Instituciones permanecen
diferidas y ninguna capability sustituye aprobación, ownership, rol, scope,
estado, dependencia, cuota, consentimiento o seguridad.

## 11. Complementos

Se aprueba `plan + add-ons elegibles + activación por perfil`. Cada add-on debe
definir código, nombre, elegibilidad, tipo de perfil, precio versionado, periodo,
unidad, dependencias, capabilities, activación, renovación, prorrateo,
cancelación y estados. Estados candidatos: `available`, `selected`,
`pending_payment`, `paid_pending_configuration`, `active`, `paused`,
`cancel_at_period_end`, `expired`, `ineligible`.

## 12. Call Center

El Call Center es un complemento futuro, no una implementación. Aplica por
perfil médico individual Standard/Optimum/Professional y una Agenda. Modalidad
Complementaria: apoyo/desbordamiento/ventanas compartidas. Modalidad Integral:
recepción principal en todo el horario configurado. Incluye llamadas, WhatsApp
y acciones de Agenda ilimitadas comercialmente con fair use; requiere
activación expresa y `call_center_agenda_operator` sin clínica, pagos,
ownership o moderación.

Precios tentativos pre-lanzamiento: 1,999 MXN anuales Complementario y 2,999
MXN anuales Integral. Se renueva anualmente, se prorratea por días restantes y,
si el plan es mensual, exige anticipo inicial equivalente a tres mensualidades
del complemento. No son precios definitivos ni existe cobro implementado.

## 13. Inteligencia artificial

- `ai_agenda_agent`: candidato Professional, sólo Agenda/público, tools
  restringidas, confirmación antes de mutar, transparencia y fail-safe.
- `call_center_ai_fallback`: futuro, sólo dentro del complemento.
- Promociones/Paquetes: imágenes provisionales 3/10/20/30 al mes.
- Redacción: borradores provisionales 15/30/60/100 al mes.
- Interacción medicamentosa: auditoría especializada; no quota promocional.
- Operación IA interna: límite de costo interno, no cuota comercial.
- Supervisor universal: `reject_for_safety`.

Proveedor, costo real, herramientas productivas e implementación permanecen
diferidos. Un fallo técnico no consume cuota; un resultado válido sí.

## 14. Cuotas

Se separan cuotas comerciales, límites técnicos, límites de seguridad, topes
internos de costo y fixtures. Toda cuota comercial exige unidad, cantidad,
periodo, versión, medición backend y comportamiento al agotarse. Agenda y Call
Center se presentan ilimitados comercialmente, sin excedentes, con controles
técnicos/fair use que no se usan como upsell.

Galería: una imagen principal y 21 adicionales; 22 públicas máximo. Son
espacios activos, no cargas anuales. Las imágenes se validan, orientan,
redimensionan, comprimen, sanean y quedan bajo 300 KB. El original sólo puede
existir temporalmente en cuarentena/procesamiento.

## 15. Claim y alta de perfiles

`claim_existing_profile` y `create_new_profile` son flujos separados. Perfil no
aprobado no es administrable, contratable ni pagable. Antes de aprobar, la
cuenta se limita a la solicitud y no expone panel, checkout, plan o add-on.
Documentos, revisión humana, operador autorizado, `needs_info`, aprobación,
rechazo y trazabilidad son obligatorios. Tras aprobar: ownership, administración,
compra y plan inicial Free.

El correo es obligatorio para identidad, invitación, contraseña segura,
recuperación y estados de solicitud. Nunca se envían contraseñas en texto plano.

## 16. Ownership

Estados: `unclaimed`, `claim_pending`, `claimed`, `disputed`, `suspended`,
`revoked`. Transferencia es flujo auditado, no estado permanente; exige
evidencia, revisión, aceptación y notificación. Cambiar email no transfiere y
cambiar owner no elimina perfil, datos, historial, plan o suscripción.

## 17. Publicación y moderación

Estados: `draft`, `pending_review`, `approved`, `published`,
`changes_pending_review`, `suspended`. Operadores scopiados pueden rechazar,
retirar, restaurar, solicitar corrección, cambiar datos verificables, congelar,
suspender o ejecutar instrucción válida de autoridad. Siempre exige RBAC, caso,
motivo, evidencia, referencia, before/after, auditoría, email y apelación; R3
requiere doble aprobación. No altera automáticamente plan, ownership, pagos o
datos clínicos.

## 18. Lifecycle comercial

Estados: `free`, `draft`, `pending_payment`, `pending_activation`, `active`,
`past_due`, `grace`, `restricted`, `expired`, `cancelled`, `superseded`,
`failed`. Checkout, pago y activación permanecen separados; pending_payment no
activa e impago no elimina datos.

Estado efectivo: `enabled`, `read_only`, `locked_upsell`,
`blocked_dependency`, `pending_activation`, `grace_limited`,
`suspended_policy`, `hidden_security`, `not_applicable`. Backend evalúa perfil,
ownership, suscripción, plan/add-on, capability, rol, scope, dependencia, cuota
y seguridad. Fuentes: plan, addon, temporary grant, contractual override y
security policy.

## 19. Grace, prórroga y downgrade

Grace aprobado: días 1–3 `past_due`, días 4–15 `grace`, día 16 `restricted`.
No reinicia con intentos y no borra. Prórroga ordinaria: hasta 7 días naturales;
excepcional: hasta 15, con controles reforzados y doble aprobación cuando
corresponda. Estado `grace_limited`; no cambia renovación, precio, ranking, plan
o deuda.

Dashboard y correo deben comunicar importe, fecha original, límite, días,
funciones afectadas, consecuencias y pago; avisos al conceder, 48h, 24h,
vencimiento y recuperación. Nunca se presenta como renovación pagada.

## 20. Retención y recuperación

Al terminar grace, plan efectivo Free y premium `archived_read_only`:
lectura/consulta/exportación/recontratación, sin crear, editar, emitir, subir,
generar o usar módulos premium. Datos estructurados pueden conservarse años;
pesados pueden expirar por clase/plazo con aviso y descarga/reactivación.
Archivos clínicos no se eliminan sólo por impago.

Downgrade voluntario se programa para renovación, conserva vigencia, admite
cancelar el cambio, no da reembolso automático, recalcula capabilities en la
fecha y finaliza add-ons incompatibles. Recontratación compatible restaura
escritura sobre datos preservados.

## 21. Agenda, contacto y Expediente

`appointment`, `patient contact` y `clinical record` son entidades separadas.
Cita crea/vincula contacto operativo, nunca Expediente automático. Expediente
requiere plan elegible, actor clínico, acción explícita, dedupe, confirmación,
consentimiento y trazabilidad. Agenda recolecta sólo identificación/contacto,
cita y consentimiento operativo; DOB/género sólo cuando consolidación clínica
lo requiera. Dedupe es asistido; merge explícito, reversible y auditado. Call
Center e IA no acceden a clínica.

## 22. Funciones visibles bloqueadas

Las funciones promocionables pueden ser visibles, bloqueadas, explicadas,
accesibles y no invasivas con razón, plan/add-on y acción. No se usa upsell para
`not_applicable`, `hidden_security`, `suspended_policy` o perfil no aprobado.
Datos archivados se muestran preservados. Backend produce denial reasons;
frontend consume read-model.

## 23. Roles internos

Tres planos: profesional/cliente, operadores internos y gobierno/emergencia.

Roles aprobados como requisitos, no implementados: `platform_director`,
`break_glass_superadmin`, `platform_admin`, `operations_manager`,
`support_advisor`, `profile_claim_reviewer`, `billing_subscription_operator`,
`content_moderator`, `privacy_security_officer`,
`technical_operations_viewer`, `audit_read_only` y
`call_center_agenda_operator`.

MFA, no autoelevación y protección del último director son obligatorios.

## 24. Riesgos R0–R3

- R0: lectura.
- R1: reversible.
- R2: sensible; caso, motivo, evidencia, before/after, auditoría y notificación.
- R3: crítica; MFA, reauth, doble aprobación, separación solicitante/aprobador,
  trazabilidad, reversión posible y revisión posterior.

Una medida urgente es temporal, mínima y sujeta a ratificación.

## 25. Sesiones asistidas

`support_assisted_session`: temporal, ligada a caso/scope/expiración, identidad
del operador preservada, lectura por defecto, banner y auditoría; nunca
suplantación silenciosa. `extraordinary_clinical_access`: R3, excepcional,
doble aprobación, mínimo privilegio, expiración y sin actos médicos.

## 26. Platform cases

Tipos: `account_access`, `profile_claim`, `profile_dispute`, `billing`,
`moderation`, `privacy`, `security`, `technical`, `clinical_access_request`,
`operator_access`, `incident`, `call_center_escalation`.

Estados: `draft`, `open`, `assigned`, `in_progress`, `waiting_for_user`,
`waiting_for_internal_approval`, `waiting_for_external_authority`, `resolved`,
`closed`, `cancelled`, `reopened`. Toda acción R2/R3 se liga a caso y sólo se
cierra con resolución documentada.

## 27. Decisiones deliberadamente diferidas

Estado: `approved_framework_with_deferred_parameters`.

1. precios finales de Call Center al lanzamiento;
2. proveedor de voz;
3. proveedor de WhatsApp;
4. proveedor IA;
5. costos reales IA;
6. dimensiones técnicas finales de imagen;
7. plazo exacto de cada retención no clínica;
8. implementación clínica de interacciones medicamentosas;
9. perfiles futuros de clínicas/hospitales/laboratorios/gabinetes;
10. compra futura de créditos adicionales;
11. pagos manuales, SPEI y CFDI;
12. implementación completa de consola operativa;
13. implementación del Call Center;
14. implementación de agentes IA;
15. canal push.

No bloquean la Actividad 2 porque ésta sólo modelará policy, estados,
elegibilidad y denials, manteniendo funciones futuras desactivadas/fail-closed y
sin inventar proveedor o parámetro definitivo.

## 28. Límites de la Actividad 2

Puede implementar sólo el núcleo PG-01: policy versionada, códigos, matriz,
catálogo y fuentes de capability, estados comerciales/efectivos, gates de
approval/ownership, read-model, denials, downgrade programado, lifecycle/grace,
cuotas/unidades, contratos de add-ons, tests y adapters.

No puede construir Call Center, telefonía, WhatsApp, IA productiva, generación
real, Expediente/Recetas nuevos, consola, workflows completos de operadores,
pagos manuales, SPEI, CFDI, sistema completo de notificaciones, migraciones
destructivas ni deploy AWS. Sólo puede representarlos como documented,
disabled, future implementation o fail-closed.

## 29. Evaluación de desbloqueo

- Decisiones bloqueantes originales: 11.
- Decisiones atómicas aprobadas: 30/30.
- Bloqueadores originales sin resolver: 0.
- Parámetros diferidos que bloquean el núcleo PG-01: 0.
- Scope boundary documentado: sí.
- Implementación iniciada: no.
- Estado recomendado: `UNBLOCKED_READY_NOT_STARTED`.

Rationale: el núcleo puede modelarse sin inventar los quince parámetros
diferidos y sin construir funciones especializadas; todos los defaults futuros
son disabled/fail-closed.

## 30. No repetición

Esta aprobación no ejecuta Activity 2, PHP, SQL, npm, servidor, navegador,
HTTP, AWS, CDK, Docker, deploy, Stripe o migraciones. No modifica código,
schemas, tests, fixtures, PDFs o infraestructura. No crea roles, tablas,
endpoints, proveedores, precios definitivos ni recursos.

## 31. Historial

| Versión | Fecha | Cambio |
|---|---|---|
| 1.0.0 | 2026-07-18 | Formalización de 30 decisiones atómicas `director_approved`, parámetros diferidos y assessment `UNBLOCKED_READY_NOT_STARTED` |
