# GOV-SIM02 — owner médico, autoridad de receta y expiración canónica

**Seguimiento C4-UI01:** [acreditación en las superficies originales](C4-UI01-original-physician-onboarding.md), probada de extremo a extremo sólo en ensayo desechable. La designación de titular sigue separada y no se activa autoridad clínica ni operación real.

**Seguimiento C4-ADM02-IMP02:** el [emisor y proyección protegida](C4-ADM02-IMP02-physician-authority-issuer.md) completan la emisión rutinaria de fuente y sustituyen el fixture manual de admisión/binding por QA del escritor real. Se conserva el escritor de owner y se prueba su revalidación transaccional. La composición normal-local sigue cerrada y no se activa firma.

**C4-EV01:** la [fundación de evidencia protegida](C4-EV01-protected-evidence-foundation.md) implementa en fuente contacto/origen/revisión/confirmación, sólo con QA sintética. Sustituye la ausencia de esos componentes descrita históricamente abajo; no activa el emisor C4/ADM02, la proyección en ownership ni firma real. SMS/custodia/retención/despliegue permanecen pendientes por separado.

**Seguimiento C4-ADM02-IMP01 (2026-10-16):** implementación acotada de fuente autorizada, sin activación real. La [auditoría del emisor](C4-ADM02-IMP01-issuer-readiness.md) detiene el componente por falta de contrato/fuente protegida de evidencia cuenta–persona–perfil y prerrequisitos de contacto/origen no resueltos. No se implementó ni conectó un emisor o binding; los positivos SQL descritos abajo siguen siendo fixtures GOV-SIM02. No interpretar la autorización como aprobación de DDL, autoridades o firma normal-local.

Fecha: 2026-10-16. Repositorio `mxmed-step2-vitals-r1`, rama `ux/consultation-step2-vitals-r1`. Fuente y upstream iniciales: `9b86872e91eec35775bf5a60575c5a41267122b2`; árbol inicialmente limpio. No se encontró una implementación GOV-SIM01 en esta fuente.

```text
PRODUCT_POLICY=APPROVED_BY_DIRECTOR_GOV_SIM02
PHYSICIAN_ONLY_OWNER=true
ROUTINE_PLATFORM_STAFF_APPROVAL_COUNT=1
INDEPENDENT_PHYSICIAN_ACCEPTANCE_REQUIRED=true
MANDATORY_TWELVE_MONTH_OWNER_REVIEW=DEFERRED_NOT_APPROVED
EXTRAORDINARY_ROOT_RECOVERY_V3=DEFERRED_NOT_APPROVED
NORMAL_LOCAL_ACTIVATION_AUTHORIZED=false
REAL_CLINICAL_SIGNING_ACTIVATED=false
```

Esta instrucción sustituye interpretaciones incompatibles de GOV-SIM01/GOV03B-DEC01 y especializa ROLE-POL01. El owner de un perfil médico debe ser el médico legítimamente asociado con ese perfil y usar su cuenta Identity exacta. Un administrador u operador no médico **no puede ser owner**. El profesional se acredita en un único proceso humano C4/ADM02 que referencia VID01/CRD02 y la relación comprobada cuenta–persona–perfil. No se inventa otra revisión ni se otorga autoridad por coincidencia de nombres, correo, cédula textual o etiqueta de rol.

## Proceso rutinario aprobado y límites

1. El médico conserva su cuenta y solicita el perfil exacto. C4/ADM02 debe comprobar identidad, credenciales, titularidad de cuenta, relación con el perfil y origen/claim, conservando evidencia privada y trazabilidad. No clasificar ni backfillear automáticamente legado; los prerrequisitos de contacto/onboarding pendientes no se dan por resueltos.
2. Un revisor humano de plataforma, independiente del médico, con autorización explícita, revisa y aprueba. La clase staff por sí sola no basta. Se añade al catálogo `physician_accreditation_review`, sin otorgarla a nadie; `profile_initial_ownership_approve` sigue siendo la capacidad separada de aprobar la transición administrativa. Una misma persona puede ser el revisor legítimo de ambos aspectos si tiene ambas autorizaciones.
3. Para el caso inicial rutinario, un mismo staff autorizado puede iniciarlo y aprobarlo con su propia sesión, reauth reciente y MFA existentes. No se necesitan un segundo aprobador ni evidencia externa/root. La persona beneficiaria nunca se autoaprueba.
4. El médico acepta de forma independiente el caso/cuenta/perfil/membresía/versiones exactos con su propia sesión y reautenticación. El único escritor de membresías revalida elegibilidad, cuenta/credencial, versiones, perfil sin owner, prueba/consumos y cuotas; cambio y auditoría son atómicos. No se crea otra membresía o motor de roles.
5. Suspensión/revocación y cambios de evidencia invalidan nuevas decisiones; conservar historia y auditar cada mutación por los escritores existentes. Las excepciones, conflictos y transferencias siguen su vía específica; esta simplificación no reduce quorum de recuperación MFA, sustitución de owner o gobierno de plataforma.

`PhysicianOwnershipPolicy` marca únicamente nuevos casos `INITIAL_OWNER` con `GOV-SIM02_PHYSICIAN_INITIAL_OWNER`. Los consumidores de aprobación, aceptación, finalización y auditoría aceptan exactamente **una** aprobación para esa versión. Casos anteriores y recuperaciones conservan dos. No se reinterpreta ni reescribe evidencia existente. La composición rutinaria ya no necesita configurar un trust anchor externo; si falta, bootstrap y recuperaciones que lo requieren rechazan mediante sus adaptadores. Los controles TLS, CSRF, sesión, separación del beneficiario, MFA/reauth, auditoría y autorización de despliegue permanecen.

## Vinculación profesional: preparación y deuda real

La [migración preparada](../../modules/identity/db/migrations/2026_10_16_02_physician_account_binding.sql) contiene dos superficies relacionadas, sin seeds ni backfill:

| Superficie | Responsabilidad |
|---|---|
| `profiles_physician_admissions` | Una admisión C4/ADM02 vigente por perfil, con IDs VID01/CRD02, revisión humana, capacidad, decisión/caso/evidencia/auditoría, versión y vigencia. No duplicar nombre ni licencia. |
| `auth_account_professional_bindings` | Cuenta, identidad/perfil/admisión y versión exactos, prueba de relación/aceptación, estados y revisiones de fuentes; unicidad de relación efectiva y de identidad, preservando historia terminal. |

No existe una revisión obligatoria de doce meses. `valid_until`/`reverify_at` pueden fijarse sólo desde evidencia/política aprobada; cuando existen se respetan, sin gracia. La revocación y la validación de fuentes no quedan diferidas por diferir ese plazo numérico.

`ProfessionalAccountBindingProjection` es una lectura transaccional preparada: verifica admisión aprobada, binding activo, sujeto/perfil/identidad/cédula exactos, estados/vigencia/versiones y hashes de las fuentes, cuenta vigente y capacidad explícita del revisor. Rechaza procedencia de operador no médico, fuentes sintéticas, cambio de identidad/cédula y revisiones revocadas. No compara nombres/email/licencia para crear la relación. La identidad y credencial referenciadas se reutilizan; no se revisan ni copian por segunda vez.

**No hay emisor C4/ADM02 instalado ni nueva composición habilitada.** `CanonicalInitialOwnerEligibilityAdapter` sigue sin proyección en runtime y deniega por defecto. La inyección de esa proyección sólo se ejercita en pruebas SQL desechables. Instalar tablas no la conecta automáticamente. La capacidad nueva no concede autoridad a staff existente. El futuro emisor debe unir revisión y auditoría canónica, control de evidencia/documentos, aceptación, lifecycle, FK/invariantes, carreras y revalidación final; su contrato de eventos/retención y clasificación de legado siguen pendientes. No se concede que el DDL preparado resuelva por sí mismo la admisión.

La migración v2 anterior `2026_10_16_01_governance_security_bindings.sql` continúa pendiente en `mxmed`. Ambas migraciones requieren aprobación/aplicación normal-local separadas. Los quince objetos de OP-ACT02A no se repiten.

## Contrato de firma y operadores

Un médico owner con identidad/cédula verificadas y vinculación/aprobación profesionales vigentes **puede** firmar y emitir recetas bajo la política aprobada, mediante su cuenta y perfil propios. El permiso efectivo requiere en servidor, al ejecutar: sesión/cuenta/credencial, membresía y perfil exactos, binding y fuentes actuales, entitlement, paciente/encuentro/documento, creator/version/fingerprint/lifecycle, intención de firma y auditoría. Revalidar revocaciones/versiones antes de commit, sin autoridad copiada en PHP o navegador. Imagen de firma y `role_code=owner` no sustituyen esos hechos.

La implementación clínica real sigue cerrada por CLIN-AUTH02A: no se conectó la proyección a `clinical_professional_authority_decision`, no se emitieron recetas ni se activaron firmas/QR. La prueba positiva de evidencia y ownership es un **ensayo de futura autoridad**, no una receta firmada o una aceptación clínica operacional. La integración clínica completa de 02B1, su operación/snapshot/intención y sus carreras se autorizará por separado; SQL y Valkey no tienen transacción distribuida.

Un médico owner puede invitar, asignar/modificar permisos individuales, pausar, reactivar y revocar hasta dos operadores **no médicos**. Administrator ocupa una de esas dos plazas, sin ownership o privilegio clínico. Ningún grant de Agenda o administrativo permite crear/emitir recetas, solicitar firma profesional, usar credenciales del médico o gobernar plataforma. Se conservan cinco grants separados de Agenda, alias, avatar, creador inmutable, historial y concurrencia. No se activó Operadores ni se cambiaron fixtures QA05/OP02/UI03.

## Sesión: enforcement y aviso en la aplicación original

| Contrato | Resultado |
|---|---|
| Inactividad | 3,600 segundos en el store/servicio canónico. |
| Vida absoluta | 43,200 segundos desde creación; actividad y rotación no desplazan ese origen. |
| Touch / concurrencia | Intervalo 300 segundos y máximo cinco sesiones, sin cambios. Un `touch` que pierde la sesión por revocación/expiración ya no autoriza una copia anterior. |
| Observación | `GET current-session` usa validación sin touch en toda su composición, incluidos snapshots de operadores. Foco, avisos o comprobaciones automáticas no se convierten en keepalive. |
| Revocación | Cuenta deshabilitada, versión de credencial distinta, logout/revocación y expiración deniegan. Suspender/revocar operador o grants deniega el acceso correspondiente en el consumidor actual; no depende de una copia de permisos en la sesión. |
| Fallo de dependencia | Validación/consumidor deniega; no fallback a PHP, headers o almacenamiento del navegador. Algunos adaptadores legacy responden 401 donde el resolver informa indisponibilidad: la UI reconcilia con el endpoint canónico. |

La aplicación original muestra dos minutos antes del vencimiento comunicado por servidor: **“Tu sesión está por finalizar.”** El aviso consulta el dirty guard existente para indicar cambios pendientes y conserva los controles actuales de guardado. No hace fetch, rotación ni autosave y no modifica TTL. Es orientativo: reloj local o deadline desactualizado no conceden acceso; la respuesta del servidor manda.

Al expirar muestra **“Por seguridad, inicia sesión nuevamente para continuar.”**, bloquea/aborta operaciones y conserva la limpieza de estado sensible existente. No intenta guardar tras expirar ni promete recuperar contenido nunca persistido. Los drafts ya guardados por el flujo soportado siguen en su autoridad existente; el aviso permite usar ese flujo mientras la sesión es válida. No se crea un almacén nuevo de PHI ni se trasladan borradores a otra cuenta. No se añadieron credenciales/cookies de sesión a JavaScript. El CSS usa colores y geometría del shell original; sin página nueva ni rediseño.

## Cierre de vías legacy comprobadas

| Familia | Corrección / frontera |
|---|---|
| Medios privados, información profesional y facturación | `GallerySessionScope` exige el resolver canónico en ambientes Identity configurados, incluyendo normal-local y preview. PHP conserva CSRF/form state, pero sus aliases no crean identidad. Auditoría de submission usa el session ID canónico, sin copiar token. |
| Subscriptions | Contexto/lecturas validan cuenta y perfil canónicos; fixtures de sesión quedan deshabilitadas en ambientes canónicos. Los escritores antiguos basados en IDs numéricos/PHP permanecen denegados allí: falta adaptación comercial específica, no se traduce un account opaco a autoridad legacy. Webhook firmado y configuración Stripe pública conservan su contrato. |
| Agenda / Profiles privados | Las ramas canónicas ya existentes se usan también cuando otra composición Identity está configurada; gates QA05/operadores y rutas públicas se conservan. |
| Media review interno | Un ambiente canónico no permite fallback a fixture PHP aunque falte la cookie canónica. Staff y capacidades siguen siendo explícitos. |
| Entradas standalone C16, C17 y hospitalización | Se añadió Identity antes de schema/resource/writer, con la misma regla de enlace médico–paciente extraída al helper existente. C16/C17 no pueden generar documentos por fuera de la contención profesional 02A. Los harnesses CLI aislados retienen su contrato histórico, sin bypass HTTP. |
| Clinical, Patients, Provider, gobierno y operadores | Conservan resolvers/gates canónicos y scope/entitlements propios. Sesión de navegador no sustituye proof de operación. Gobierno/operadores normales siguen deshabilitados. |
| Superficies públicas | Descubrimiento/medios públicos, catálogo, booking/OTP y páginas de acceso no representan acceso privado; QR móviles usan su token y scope específicos. Los prototipos legacy de verificación no crean sesiones Identity ni acceso privado. No se exponen nuevos endpoints públicos. |

El router de operadores conserva el perímetro de módulos permitidos. Una cookie/header legacy o timer local no autoriza una API privada en la composición revisada. No se afirma que hayan quedado implementados todos los workflows profesionales o el despliegue productivo.

## QA ejecutada y límites

Evidencia privada: `~/Library/Application Support/MXMED/gov-sim02/20261016-review/`. Sólo cuentas `example.invalid`, perfiles sintéticos y bases/Valkey/procesos propios desechables; no se adelantó el reloj del Mac ni se autenticó/expiró una sesión real.

| Gate | Evidencia |
|---|---|
| Médico no verificado / no médico / operador / otro perfil | Denegados por la proyección preparada; también prueba procedencia de operador aun con etiqueta/caso aparentes. |
| Evidencia vigente y aprobación simplificada | SQL nativo: una aprobación staff con capacidad y MFA/reauth, aceptación independiente del médico, transición/auditoría canónicas y cero evidencia externa del caso. |
| Revocación/integridad | Binding/revisor revocados, admisión vencida/versionada, cambio VID01/CRD02 deniegan. Rollback de auditoría, replay y dos procesos finalizando conservan un solo éxito. |
| Firma profesional | Evidencia del futuro owner elegible en aislamiento; operador, owner sin binding y médico ensayado siguen denegados por la puerta clínica real no activada. No se afirma prueba positiva de emisión real. |
| Relojes simulados | Expiración exacta 3,600; 43,200 aun con actividad/rotaciones; polling de observación no extiende idle; revocación en read/touch, credencial/cuenta y cookie legacy deniegan. |
| HTTP real desechable | Sesiones idle/absolute/revoked serializadas en Valkey propio; rutas privadas, lecturas y mutaciones legacy rechazan junto con PHPSESSID y headers antiguos. GET current-session no cambia last_seen del registro sintético. TLS/proxy, origen, CSRF y bootstrap no público siguen pasando. |
| Navegador | Chromium y WebKit: 1440×900, 1366×768, 820×1180; aviso español, dirty guard real, draft sin cambios antes de expirar, sin requests de keepalive/autosave, bloqueo por 401 y POST posterior abortado. QA física Safari del Director no ejecutada. |
| Regresiones | OP-IMP01/02 01–38, cuotas/Agenda/grants/suspensión/revocación/concurrencia; resolver clínico; C3 47 checks; sesión privada; contratos y gate HTTP desechable CLIN-AUTH02A. |

La matriz desechable final ejecutó 73 comprobaciones SIM02 de HTTP (19 lecturas y cinco mutaciones por cada estado idle/absolute/revoked, más observación sin touch), además de los siete gates de gobierno existentes. La regresión de navegador original también pasó en Chromium/WebKit, incluidos 401 ajeno, 403, indisponibilidad, recarga, logout y varias pestañas. Sus asserts se actualizaron a la copia española aprobada y el fixture declara UTF-8; no se omitió la distinción entre expiración y pérdida de permiso.

Lectura del origen original `https://localhost:8443`: raíz 200, aplicación privada anónima 302, acceso 200 y las tres entradas clínicas standalone anónimas 401. El SHA-256 del JS servido coincide con el worktree correcto; no se hizo login del Director. Snapshots finales: las 128 tablas conservan conteos/checksums/DDL y las autoridades reales; esquema/rutinas/triggers/audit, configuración, payloads de sesiones y QA05/OP02/UI03 iguales. Los quince objetos de OP-ACT02A siguen vacíos, no hay staff real, la membresía sigue `collaborator/profile/active`, ninguna migración nueva está instalada y los procesos normales se preservan con flags deshabilitados.

El pin estático AUTH02A_29 se actualizó de forma explícita: los archivos concretos de contención autorizados por SIM02 tienen SHA-256 fijos; las demás guardas clínicas siguen byte por byte contra OP-IMP01. No se quitó ni relajó la puerta. La extracción conserva el predicado de enlace activo, y HTTP prueba que los nuevos caminos deniegan antes de writers. La falla estática histórica de otro suite (`AuditMp01CStaticContractsTest`, pin 44 frente a 52 policies) permanece separada; esta entrega no declara que todos los tests globales del repositorio estén verdes.

## Pendiente antes de una operación real

No se asignó owner, staff, grant o MFA real; no se envió invitación ni se modificó dato clínico. No hubo DDL en `mxmed`. Se preservan sus servidores y los entornos persistentes.

Falta autorizar/completar el emisor humano C4/ADM02, evidencia/contacto/claim, ciclo/auditoría y clasificación de legado; ensayar y aprobar las dos migraciones y sus consumidores; luego nominar revisores y beneficiario concretos y activar por alcance. La firma clínica necesita su implementación y QA por operación, validación de snapshot/intención/QR y autorización explícita separadas. Adaptación de escritores comerciales legacy y arquitectura de recuperación raíz siguen fuera de activación. Ninguna de esas deudas permite usar un bypass para obtener verde.

Rollback de fuente: revertir este commit por Git bajo el procedimiento ordinario; no hay rollback SQL normal porque no se aplicó migración. No reabrir accesos legacy para simular disponibilidad: si se revierte por un problema operativo, mantener los guards o deshabilitar el endpoint afectado con autorización específica. Los registros y evidencia de QA persistentes no se resetean.
