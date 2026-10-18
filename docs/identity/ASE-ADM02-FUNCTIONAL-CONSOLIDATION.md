# ASE-ADM02 — consolidación funcional de incorporación

2026-10-18. Base `58f3fcf5db81ff5f2804833fd8470eed7c46e2c8`; rama aislada `feature/ase-adm02-functional-consolidation`. Oficial `f92f37f1958af5140a96c04cb52c5c1ce7af8e01` y visual `312ee388f93c892e6caf7e845b11e2b42f9c3565` protegidos. No merge/deploy, documentos o personas reales.

La solicitud de consolidación ratifica P01–P10 recomendados en POL02 (auditoría entregada en conversación, sin archivo/commit POL02), precisa que un caso puede contener varios documentos justificados y autoriza completar backend/API/UI/QA en infraestructura propia sintética. No abre otra fase de política ni ratifica textos legales, plazos o límites productivos.

| Eje | Entrega y límite |
|---|---|
| PRODUCT_POLICY | Retrato opcional; imágenes JPEG/PNG; documentos lógicos con caras/versiones; Director inicial con MFA y sin auto-revisión; correcciones/aceptación/rechazo; admisión independiente, sin Master ni grants automáticos. P01–P10 aprobados por la solicitud; detalles legales/operativos pendientes. |
| SOURCE_IMPLEMENTATION | Reutiliza Identity, StaffAdmission/InvitationService, StaffMedia, StaffMfaService, auditoría y el writer InternalGovernanceService. StaffWorkflow une esos componentes. No segundo registry de staff, cifrado, permisos, login o almacenamiento. |
| CONNECTIVITY | UI de solicitudes dentro del shell aprobado; página propia del solicitante; HTTP sólo compuesto por router del ensayo propietario. Endpoint normal falla cerrado sin composición. Invitación entrega a sink privado example.test, nunca correo externo. |
| VERIFICATION | Runner propio nuevo, datadir/Valkey/TLS desechables; prueba HTTP/SQL, dos conexiones para alta duplicada, navegadores y revisión de fuentes. Evidencia en directorio enlazado abajo. |
| OPERATIONAL_STATUS | Funcionalidad aislada; producción cerrada por legal/custodia/retención/eliminación/canal/seguridad y autorización de despliegue. No readiness general ni promoción implícita. |

## Recorrido integrado y dueños canónicos

1. Director prepara el mismo caso IMP01 e invita mediante IMP02 al destinatario exacto.
2. Registro/verificación/login existentes de Identity o cuenta legítima existente. La invitación no sustituye la sesión, aceptación personal ni verificación email.
3. Solicitante corrige/confirma su versión personal. Presenta imágenes privadas y aceptación de un bundle legal versionado, exclusivamente ficticio en el ensayo.
4. Se registra un conjunto de documentos: cada documento tiene ID lógico, tipo, justificación, versión y páginas ordenadas con referencias a assets IMP03 inmutables. INE exige FRONT/BACK; pasaporte BIOGRAPHIC. No catálogo extranjero ni PDF. Un documento adicional necesita justificación; no exigencia indiscriminada de varios.
5. Director reautentica con contraseña canónica y obtiene desafío/proof TOTP existente. Consultar evidencia, corregir, aceptar/rechazar y admitir son intenciones separadas ligadas a sesión/cuenta/credencial/caso/conjunto/acción/motivo/vencimiento. No permiso de reviewer para Master/Advisor.
6. Lectura devuelve únicamente páginas del conjunto autorizado, verifica almacenamiento/hash/AEAD y registra audit antes de responder. Aceptar exige lectura autorizada reciente del mismo conjunto y verifica que sus bytes sigan disponibles.
7. Revisión inmutable con resultado explícito. Una corrección puede reutilizar caras intactas y reemplazar otra, conservando el mismo documento lógico y su historia. Cambio de perfil/conjunto/evidencia invalida la aplicabilidad positiva; retrato separado no cambia identidad.
8. El Director confirma admisión ADVISOR con proof propio. `InternalGovernanceService::completeAdmission` conserva el único INSERT staff, dentro de su transacción: locks cuenta/staff ordenados, autoridad, binding actual, revisión positiva, aceptación versionada, consumo MFA, registro canónico, audit y recibo. Fallo audit revierte todo; replay exacto recupera el recibo. Otra intención concurrente no duplica staff.
9. La relación aparece en el directorio existente con cero grants iniciales. La matriz existente concede/revoca permisos individualmente mediante el writer canónico; tener ACTIVE nunca sustituye la autoridad del consumidor/MFA aplicable. Ningún cambio de ownership o de operadores médicos.

La composición Master conserva `UnavailableStaffIdentityReview` y todos sus requisitos independientes. El consumidor de admisión sí consulta la revisión real del caso dentro de la transacción; no se sustituye el reader Master por un fixture positivo. Legacy no se etiqueta verificado ni se suspende masivamente.

## Datos, MFA y auditoría

[Migración aditiva](../../modules/identity/db/migrations/2026_10_18_07_staff_workflow.sql): conjuntos documentales/aceptación, intenciones de revisión, revisiones inmutables y recibos de admisión. Son objetos del mismo caso, no otro modelo de personas/staff. Los assets, referencias privadas, cifrado, cuentas y grants siguen en sus fuentes originales.

`auth_governance_cases` y las tablas MFA existentes mantienen sus FKs: la intención documental crea su caso de seguridad con propósito nuevo `STAFF_ADMISSION_REVIEW`. El CHECK conserva los 17 propósitos históricos y añade éste. Se actualiza la expectativa exacta de schema Master para esa extensión; no se relaja el CHECK ni se reescribe SQL histórico. Una nueva intención revoca otra abierta para el mismo target; proofs se consumen atómicamente sólo para su acción exacta. Reutilización TOTP y límites de intentos siguen canónicos, sin reset producto.

Eventos existentes STAFF_ADMISSION_CASE_CHANGED/READ, STAFF_MEDIA_CHANGED/READ y INTERNAL_GOVERNANCE_CHANGED; metadata cerrada y sin documentos, nombres, contraseñas, tokens o claves. La nueva clasificación de acciones administrativas conserva el catálogo/pins de políticas. Motivos de revisión se guardan en los objetos privados, no como bytes de evidencia en audit.

Los bytes/SQL no son atómicos. Se conserva reconciliación IMP03 para huérfano, archivo/llave ausente, integridad, operación duplicada y ACK perdido. Ante error HTTP no se afirma rollback global ni se reenvía automáticamente; consultar el estado canónico antes de repetir. No eliminación operativa ni retención perpetua derivada de triggers.

## UI-2 autorizada y preservación

La solicitud actual autoriza la conexión funcional al diseño aprobado. Ficha: superficie Equipo/Solicitudes, DATOS PERSONALES, IDENTIDAD Y DOCUMENTACIÓN y matriz existente; comportamiento preparar/invitar/confirmar/subir/agrupar/revisar/admitir; estados pendiente/correcciones/resultado/confirmado/conflicto; confirmación de acción sensible con contraseña y MFA. Sin cambios CSS, categorías nuevas, dashboard o rediseño comercial. Reversión por rama aislada; 8091 y previews anteriores intactos.

Datos de perfil y resultado de revisión del admitido sólo se agregan al read model de filas ya autorizadas para Director en la composición del ensayo; Master conserva proyección anterior. Nunca se entregan documentos en el directorio. Placeholders de retrato permanecen sin publicación automática. La pantalla propia recibe sólo el caso ligado; no puede obtener controles/autoridad Director por manipular el navegador.

## Ensayo y límites exactos

[Runner](../../scripts/ase-adm02-workflow-qa.py), [checks](../../scripts/ase-adm02-workflow-checks.py), [UI](../../scripts/ase-adm02-workflow-ui.py), [runtime propietario](../../scripts/ase-adm02-workflow-runtime.php), [QA](../evidence/ase-adm02-functional/qa.json).

Instancias frescas con nombre/datadir/UID comprobados, aprobación privada con checkout/hash/expiración y composición sólo en router allowlisted. No selector de éxito desde HTTP normal. Principal HTTP restringido a tablas/columnas necesarias; no root/fixture en solicitudes. La matrícula MFA del Director sintético usa ceremonia canónica y registrador distinto. Registro Identity y verificación usan servicios reales con notificaciones inertes; ningún bypass de matching, email o proof.

Parámetros de ensayo, no política productiva: 64 KiB por imagen, lado512/65536 píxeles, derivado800/153600 bytes, hasta cuatro documentos de dos páginas en el envelope. JPEG/PNG del subconjunto seguro IMP03; metadata no admitida se rechaza. No nuevo saneamiento documental, PDF, OCR, biometría o garantía de autenticidad. Las imágenes son bloques artificiales, no identificaciones falsificadas.

Los estados y las vistas son funcionales sólo en esa composición aislada. Autenticación/registro reutilizados no constituyen aprobación de textos reales. Retención/eliminación, custodia/rotación/backups, límites/formato y entrega productiva continúan como gates de lanzamiento, sin nuevas funcionalidades opcionales propuestas.

## Cierre verificado

41 comprobaciones HTTP/SQL/navegador aprobadas. Incluyen todos los fallos críticos solicitados, corrección/resubmisión, alta concurrente única y grant explícito. Chromium y WebKit automatizados en 1366/820/390; ajuste final de tipografía del solicitante comprobado además en ambos motores a 1366/390 ([evidencia UI final](../evidence/ase-adm02-functional/ui-final.json)). No aceptación física Safari ni recorrido MFA manual acreditados. Registro/verificación Identity se ejercitan mediante servicios canónicos; el navegador cubre invitación, confirmación y envío, y HTTP cubre revisión MFA/admisión.

[Verificación de cierre](../evidence/ase-adm02-functional/verification.json). El primer intento de capturas tuvo timeout, conservado en evidencia histórica; el recorrido final pasó. Los hashes de `qa.json` corresponden al recorrido integrado; `ui-final.json` sustituye sólo el hash del partial ajustado después, sin cambios backend.

La revisión Chromium aislada se abre con `python3 scripts/ase-adm02-workflow-review.py /tmp/mxmed-ase-adm02imp01-jzczj_zu`. URL/expiración constan en QA. Para finalizar anticipadamente, ejecutar el mismo comando con `--stop`: solicita cierre al runner propietario, que detiene exclusivamente sus procesos y elimina su directorio. El cierre de la ventana por sí solo no elimina el runtime; éste vence automáticamente a la hora indicada. Nunca introducir documentos reales. El ensayo permite mutaciones sintéticas autorizadas; no es la antigua vista de sólo lectura ASE-INT01.

## CTX-SYNC01

| Campo | Valor |
|---|---|
| CONTEXT_BASELINE | IMP03 58f3fcf5; POL01/IMP01/IMP02/IMP03 y ratificación P01–P10 actual; oficial f92f37f1 y visual312ee388. |
| AFFECTED_CAPABILITY_IDS | ID-07 / COM-10; reuso ID-01/02/03; sin nuevos grants de reviewer ni ámbito médico. |
| AFFECTED_JOURNEY_IDS | J09 integración sintética; J08 y D01–D08 preservados. |
| AFFECTED_DATA_OBJECTS | Conjuntos, intenciones, revisión y recibo asociados al caso; extensión acumulativa de propósito MFA/gobierno; writer staff canónico. |
| AFFECTED_CONTEXT_FILES | Este contrato, estado/router, inventario/datos/interacciones, POL01/IMP03 e IW01. |
| CONTEXT_CHANGES_IMPLEMENTED | Ratificación, conexiones verificadas, autoridad/agrupación/versiones/MFA/alta/grants/UI y gates operacionales. |
| CONTEXT_VERIFICATION | QA enlazada, hashes, diff, referencias, UI y protección de worktrees/listeners. |
| CONTEXT_SYNC_STATUS | PASS_UPDATED; verificación final enlazada; no activación. |
| DOCUMENTATION_COMMIT | Commit de consolidación informado en entrega, sin hash autorreferente. |
| REMAINING_CONTEXT_GAPS | Sólo gates productivos legales/custodia/retención/eliminación/límites/canal/seguridad y autorización de integración; PDF y catálogo extranjero diferidos. |

## Corrección sucesora UXFUNC01

[ASE-ADM02-UXFUNC01](ASE-ADM02-UXFUNC01.md) sucede la presentación/disponibilidad de acciones de esta consolidación y añade la baja definitiva de Advisor solicitada expresamente. Conserva Identity, admisión, media privada, MFA y gobierno Master independientes; la evidencia anterior describe su commit histórico.
