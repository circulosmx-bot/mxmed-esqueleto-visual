# MXMed — Auditoría V2 de identidad, acceso, reclamación, recuperación y sesiones

Contrato: `MXMED_IDENTITY_ACCESS_SESSION_SECURITY_AUDIT_V2`  
Actividad: `3/22` — `PRODUCT-AUDIT/MXMed-Claim-Registration-Login-Recovery-Sessions-Security-Audit-01`  
Estado: **`IDENTITY_ACCESS_SESSION_DIRECTOR_DECISIONS_READY_FOR_INTEGRATION`**
Clasificación: **UI-0 — NO_UI_IMPACT**  
Tipo: reconocimiento técnico y funcional read-only; no es una implementación.

## 1. Alcance y baseline

La auditoría se realizó sobre el worktree autorizado `/Users/circulodigital/Documents/GitHub/mxmed-esqueleto-visual-activity03-v2`, rama `audit/mxmed-claim-registration-login-recovery-sessions-security-v2`, derivada de `program/mxmed-product-refinement-22-v2` en `8eb0ff5a5f7c1e7af533713a9d6b70bdb82e049a`. La URL oficial `http://127.0.0.1:8091/` fue tratada como sólo lectura y quedó intacta. El avance oficial continúa en `2/22` hasta una integración posterior; la Actividad 4 no se inició.

No se modificaron PHP, JavaScript, CSS, HTML, SQL, migraciones, seeds, configuración, AWS/CDK, Stripe, imágenes, tests productivos, datos, sesiones, contraseñas, tokens, cookies, cuentas, perfiles ni suscripciones. No se ejecutaron verbos mutantes ni flujos reales de registro, recuperación, correo, cambio de contraseña, reclamación o logout.

## 2. Resultado ejecutivo

El repositorio tiene entidades de perfil profesional, contactos, operadores y una especificación de sesión AWS, pero no presenta un circuito productivo completo de cuenta, autenticación, registro, recuperación, reclamación o logout. Los endpoints de verificación de contraseña y SMS son stubs de pruebas: aceptan cualquier valor no vacío, admiten entrada por GET y publican CORS comodín. Las rutas privadas de perfil están protegidas por una compuerta transicional que puede tomar identificadores de cabeceras, no por un resolvedor de identidad/propiedad verificable. La CTA pública de reclamación está visible pero deshabilitada, y el backend fuerza el estado no reclamado.

Por tanto, identidad, acceso, recuperación, sesiones y reclamación no son seguros para habilitar como producto. La especificación `infra/aws/lib/constructs/session-contract.ts` es un contrato de infraestructura valioso (cookie `__Host-`, TTL y payload mínimo), pero la auditoría no encontró el puente runtime que lo conecte con autenticación, revocación y autorización PHP. Las decisiones directorales C1–C8 quedan aprobadas como contrato para integración futura; no autorizan implementación productiva en este commit.

## 3. Mapa real de niveles (no confundirlos)

| Nivel | Estado real observado | Fuente/evidencia | Clasificación |
|---|---|---|---|
| Identidad de cuenta | No se encontró registro canónico de cuentas, credenciales ni relación account↔profile | ausencia de controlador/esquema de cuenta; `modules/profiles/db/profiles_doctors_schema.sql:5-27` | ABSENT / DECISION_REQUIRED |
| Autenticación | Sólo stubs de contraseña/SMS y OTP público de citas; no existe login productivo | `api/verify-password.php:2-20`; `api/verify-sms.php:2-20` | UNSAFE_TO_ENABLE |
| Sesión | PHP llama `session_start()` en APIs; existe contrato AWS propuesto, sin adaptador runtime integral descubierto | `api/profiles/index.php:19-22`; `infra/aws/lib/constructs/session-contract.ts:15-77` | PARTIALLY_IMPLEMENTED |
| Rol interno | Hay modelo de operadores/permisos de Agenda y resolución parcial de actor; no hay resolver global de cuenta | `modules/agenda/db/operators_phase1.sql`; `api/agenda/index.php` | PARTIALLY_IMPLEMENTED |
| Propiedad/administración de perfil | Estados están documentados, pero código devuelve `ownership_status: null` e `is_claimed: false` | `PublicProfileController.php:58-64,159-169` | DOCUMENTED_ONLY / ABSENT |
| Entidad profesional | `profiles_doctors` identifica al profesional por `doctor_id`, con estado de visibilidad | `modules/profiles/db/profiles_doctors_schema.sql:5-27` | IMPLEMENTED (entidad, no ownership) |
| Suscripción | La Actividad 2 aporta autoridad de capacidades/read-model; no autentica ni autoriza una cuenta | contratos de capacidades y `profile_subscriptions` | SEPARATE_CONCERN |
| Capacidad habilitada por plan | Se calcula por plan y se transporta en el read-model; no sustituye identidad o permiso | `PublicProfilePlanCapabilities` | IMPLEMENTED (capacidad) |
| Sesión asistida por soporte | No se encontró flujo ni contrato de impersonación | búsqueda read-only del repositorio | ABSENT / DECISION_REQUIRED |
| Permisos administrativos | Tablas de operadores/auditoría existen para Agenda; no existe frontera administrativa unificada | `modules/agenda/db/operators_phase1.sql` | PARTIALLY_IMPLEMENTED |

### Modelos de estado

El inventario histórico conserva los estados observados y propuestos; las reglas aprobadas para implementarlos se encuentran en la sección 6A. Ningún estado se considera implementado por esta decisión documental.

## 4. Superficies funcionales y UI visible

| Superficie | Backend real | UI visible | Estado y futuro impacto |
|---|---|---|---|
| Registro | No endpoint/controlador/esquema de cuenta localizado | No se localizó formulario conectado | ABSENT; UI-1 si se conecta formulario existente, UI-2/3 si se rediseña |
| Login | No login productivo; verificador es stub | No flujo conectado localizado | ABSENT/UNSAFE_TO_ENABLE; UI-1 mínimo |
| Logout | No invalidación server-side ni ruta productiva localizada | No acción conectada localizada | ABSENT; UI-1 |
| Recuperación/restablecimiento | No servicio de token, correo ni cambio de contraseña localizado | No flujo conectado localizado | ABSENT; UI-1 mínimo |
| Verificación | `verify-password` y `verify-sms` de pruebas; OTP público pertenece a citas | Panel Seguridad/2FA y cambio de contraseña en `index.html` son estáticos/no conectados | PARTIAL/UNSAFE_TO_ENABLE; UI-1/2 |
| Reclamación | No workflow ni persistencia de claim | CTA “Yo soy este médico…” con `href="#"` y `aria-disabled="true"` | DOCUMENTED_ONLY/ABSENT; UI-2 o UI-3 si cambia composición |
| Perfil público | GET público y estados de visibilidad existentes | Perfil público visible | IMPLEMENTED, pero no prueba ownership |
| Contactos privados | GET/PATCH/POST/DELETE con compuerta transicional | No se evaluó interacción mutante | PARTIAL; UI-1 si se conecta auth |
| Agenda OTP | Flujo de OTP para cita pública, separado de cuenta | UI pública de agenda | No reutilizar como recuperación de cuenta |

La auditoría no altera ninguna de esas superficies. Un futuro rediseño o cambio de copy/composición debe abrir un protocolo UI-3; un binding sin cambio visual puede permanecer UI-1.

## 5. Hallazgos

`CONFIRMED` describe evidencia directa; `STRONG_EVIDENCE` describe ausencia o integración incompleta demostrada por el inventario; `INFERENCE` no se presenta como explotación confirmada.

| ID | Dominio | Severidad | Certeza | Estado/impacto | Lanzamiento | Decisión |
|---|---|---:|---|---|---|---|
| ID-AUTH-001 | identidad | HIGH | STRONG_EVIDENCE | No hay cuenta canónica ni vínculo account↔profile; ownership, roles y revocación quedan indeterminados | Bloquea identidad | C1 |
| ID-CLAIM-001 | reclamación | HIGH | CONFIRMED | CTA deshabilitada; controller fuerza `is_claimed=false`/`ownership_status=null`; habilitarla permitiría administración sin workflow | Bloquea claim | C2 |
| ID-CLAIM-002 | documentación | MEDIUM | STRONG_EVIDENCE | Contrato de perfil mezcla estados futuros con pasajes que describen claim funcional; fuente conflictiva | Bloquea decisión | C2 |
| ID-REG-001 | registro | HIGH | STRONG_EVIDENCE | No endpoint, validación, unicidad de cuenta, consentimiento o activación auditables | Bloquea registro | C1/C3 |
| ID-LOGIN-001 | login | HIGH | CONFIRMED | `verify-password.php` acepta cualquier valor no vacío, también por GET, y CORS `*` | Bloquea lanzamiento | C4/C6 |
| ID-LOGIN-002 | verificación | HIGH | CONFIRMED | `verify-sms.php` acepta cualquier código no vacío, también por GET, y CORS `*` | Bloquea lanzamiento | C4/C6 |
| ID-SESSION-001 | sesiones | HIGH | STRONG_EVIDENCE | Hay `session_start()` y contrato AWS, pero no se descubrieron rotación, logout, revocación ni adaptador runtime completo | Bloquea sesión segura | C5/C7 |
| ID-AUTHZ-001 | autorización | HIGH | CONFIRMED | `profileResolvePrivateContext` por defecto `transitional_open`; acepta IDs de cabecera y sólo exige presencia/alcance en strict | Bloquea escrituras privadas | C1/C5 |
| ID-AUTHZ-002 | permisos | MEDIUM | STRONG_EVIDENCE | Agenda tiene enforcement estricto sólo en rutas elegibles y compatibilidad transicional en otras fuentes de actor | Riesgo de frontera | C1/C8 |
| ID-SEC-001 | enumeración | MEDIUM | STRONG_EVIDENCE | Ruta pública distingue 404 `profile_not_found`; posible enumeración depende de predictibilidad del `doctor_id`, sin explotación ejecutada | Requiere decisión | C2 |
| ID-SEC-002 | controles transversales | MEDIUM | UNKNOWN | CSRF, rate limiting, host/redirect, anti-enumeración y reason codes de cuentas no son demostrables sin flujo de cuenta; no se afirma vulnerabilidad | Requiere pruebas posteriores | C4/C6 |
| ID-DATA-001 | datos/ownership | HIGH | STRONG_EVIDENCE | Tabla profesional no contiene owner/account/claim state; contacto tiene flags de seguridad pero no identidad vinculada | Bloquea ownership | C1/C2 |
| ID-UI-001 | UI/contratos | MEDIUM | CONFIRMED | Panel Seguridad/2FA/cambio de contraseña y CTA claim existen como superficies no conectadas; presentarlos como seguridad real sería engañoso | Bloquea habilitación | C3/C4 |

Recomendación común: mantener stubs y CTA deshabilitados fuera de cualquier release productivo, definir contratos C1–C8, implementar fail-closed y probar sólo con fixtures no sensibles en la Actividad 4. No se ejecutó explotación ni se copiaron credenciales, tokens, cookies o datos personales.

## 6. Decisiones para dirección (C1–C8)

Las ocho decisiones están **`APPROVED_BY_DIRECTOR`** como contrato documental. La aprobación no equivale a implementación ni habilita la Actividad 4.

### C1 — Modelo de identidad y cuenta ↔ perfil

- Problema/estado: no existe cuenta canónica ni ownership verificable; `doctor_id` es entidad profesional, no identidad autenticada.
- Opciones: A) cuenta canónica separada con tabla de membresía/ownership; B) una cuenta por doctor con migración rígida; C) conservar cabeceras transicionales.
- Recomendación técnica: A, con relación explícita account↔professional entity↔profile, roles y versionado de permisos; C sólo durante migración fail-closed y con fecha de retiro.
- Impacto UX/operativo: alta inicial y selección de perfil en multi-profesional; soporte puede revisar membresías.
- Riesgo/costo: alto; riesgo de datos huérfanos si se migra sin reconciliación. Dependencias: C2, C5, C8. Si se difiere, no habilitar rutas privadas.
- Estado: `APPROVED_BY_DIRECTOR`.

### C2 — Política de reclamación y propiedad

- Problema/estado: CTA deshabilitada, sin evidencia, revisión, expiración, revocación o anti-duplicado.
- Opciones: A) revisión manual solamente; B) auto-inicio con correo/teléfono verificado más revisión profesional; C) operator-assisted con evidencia y doble control.
- Recomendación técnica: B con revisión manual y opción C para excepciones; estados propuestos explícitos y anti-enumeración uniforme.
- Impacto UX/operativo: espera de verificación/revisión y cola de soporte; requiere mensajes no enumerables.
- Riesgo/costo: alto; riesgo de apropiación si se habilita sin evidencia. Dependencias: C1, C3, C8. Si se difiere, mantener CTA disabled.
- Estado: `APPROVED_BY_DIRECTOR`.

### C3 — Alta, verificación y activación

- Problema/estado: no hay registro ni política de consentimiento, unicidad o activación.
- Opciones: A) activación inmediata; B) pending hasta verificar email/teléfono y consentimiento; C) alta por operador.
- Recomendación técnica: B, con normalización, idempotencia, mensajes anti-enumeración y bloqueo fail-closed.
- Impacto UX/operativo: paso adicional y reenvío limitado; soporte para cuentas pendientes.
- Riesgo/costo: medio-alto; dependencia C1/C4/C6. Si se difiere, no registrar usuarios reales.
- Estado: `APPROVED_BY_DIRECTOR`.

### C4 — Contraseñas y recuperación

- Problema/estado: verificador de pruebas no compara hash; no hay recuperación ni invalidación.
- Opciones: A) password-only; B) password + segundo factor opcional; C) passwordless con enlaces/OTP.
- Recomendación técnica: B inicialmente: Argon2id, token de recuperación de un uso almacenado como hash, TTL, anti-enumeración, revocación de sesiones al cambiar contraseña.
- Impacto UX/operativo: enrolamiento y fallback de segundo factor; soporte para recuperación segura.
- Riesgo/costo: alto; dependencia C1, C5, C6. Si se difiere, conservar stubs sólo en pruebas aisladas.
- Estado: `APPROVED_BY_DIRECTOR`.

### C5 — Duración, rotación y revocación de sesiones

- Problema/estado: contrato AWS propone cookie segura, idle 30 min y absoluto 12 h, pero no existe integración runtime comprobada.
- Opciones: A) sesión PHP local; B) contrato AWS Redis/Valkey fail-closed; C) proveedor externo.
- Recomendación técnica: B, usando el contrato existente, `__Host-mxmed_session`, rotación tras login/privilege change, revocación server-side y payload mínimo.
- Impacto UX/operativo: re-login por expiración y panel de sesiones; observabilidad de storage.
- Riesgo/costo: alto; dependencia C1/C7. Si se difiere, no autenticar rutas privadas.
- Estado: `APPROVED_BY_DIRECTOR`.

### C6 — Intentos, bloqueos y rate limiting

- Problema/estado: no hay límites demostrables y los stubs admiten cualquier valor.
- Opciones: A) límite por IP; B) buckets por cuenta/IP/dispositivo con backoff; C) proveedor gestionado.
- Recomendación técnica: B con reason codes internos, umbrales revisables, no enumeración y alertas sin datos sensibles.
- Impacto UX/operativo: mensajes de espera y soporte de desbloqueo; métricas de abuso.
- Riesgo/costo: medio-alto; dependencia C3/C4. Si se difiere, mantener endpoints fuera de producción.
- Estado: `APPROVED_BY_DIRECTOR`.

### C7 — Sesiones simultáneas y dispositivos

- Problema/estado: no hay modelo de dispositivo, listado ni revocación por sesión.
- Opciones: A) una sesión global; B) sesiones por dispositivo con límite configurable; C) ilimitadas con revocación manual.
- Recomendación técnica: B, con identificador opaco, última actividad no sensible, revocación individual/global y rotación de `session_version`.
- Impacto UX/operativo: nueva pantalla de sesiones y soporte para pérdida de dispositivo.
- Riesgo/costo: medio; dependencia C5. Si se difiere, revocar todas las sesiones ante eventos críticos.
- Estado: `APPROVED_BY_DIRECTOR`.

### C8 — Soporte asistido, impersonación y auditoría

- Problema/estado: no existe contrato de impersonación ni frontera global de soporte.
- Opciones: A) acceso directo de soporte; B) sesión asistida temporal, acotada, visible y auditada; C) sólo acompañamiento sin acceso.
- Recomendación técnica: B sin conocer contraseñas, con aprobación, duración corta, banner inequívoco, acciones auditadas y revocación inmediata.
- Impacto UX/operativo: flujo de aprobación y trazabilidad para soporte; mayor carga operativa inicial.
- Riesgo/costo: alto; dependencia C1/C5. Si se difiere, soporte no debe acceder a datos privados.
- Estado: `APPROVED_BY_DIRECTOR`.

## 6A. Registro oficial de decisiones aprobadas

Las siguientes claves son los códigos oficiales aprobados por dirección. Su estado es `APPROVED_BY_DIRECTOR`; describen el contrato de diseño y no una capacidad existente.

| Código | Política aprobada | Parámetros y reglas obligatorias | Estado |
|---|---|---|---|
| `ACCOUNT_ENTITY_PROFILE_MEMBERSHIP_MODEL` | Cuenta humana → membresía/autorización → entidad profesional u organización → perfil público | Una cuenta puede administrar varios perfiles; un perfil puede tener varias cuentas; cada membresía tiene rol, alcance y estado; autenticación no implica propiedad; suscripción/capacidades permanecen separadas | `APPROVED_BY_DIRECTOR` |
| `VERIFIED_START_PLUS_MANUAL_REVIEW` | Reclamación con inicio verificado y revisión manual | Cuenta verificada antes de reclamar; evidencia de propiedad/representación; aprobación/rechazo auditables; separación creación/aprobación; asistente no autoaprueba; CTA permanece deshabilitada; respuestas anti-enumeración | `APPROVED_BY_DIRECTOR` |
| `VERIFICATION_AND_CONSENT_BEFORE_ACTIVATION` | Verificación y consentimiento antes de activar | Estado inicial `pending_verification`; correo verificado; contraseña válida; términos/privacidad con versión y fecha; acceso privado bloqueado antes de `active`; pantalla limitada para pendientes | `APPROVED_BY_DIRECTOR` |
| `SECURE_PASSWORD_EMAIL_RECOVERY_AND_PRIVILEGED_MFA` | Contraseña segura, recuperación por email y MFA privilegiado | Argon2id; token aleatorio almacenado hasheado, un uso y caducidad; anti-enumeración; revocar sesiones al cambiar contraseña; sin SMS hasta proveedor real; MFA obligatorio para administradores/operadores; MFA profesional inicialmente opcional; passwordless fuera del alcance inicial | `APPROVED_BY_DIRECTOR` |
| `AWS_SERVER_SIDE_FAIL_CLOSED_SESSIONS` | Sesiones server-side AWS Redis/Valkey fail-closed | Sin fallback silencioso; regeneración al autenticar o elevar privilegios; cookie Secure/HttpOnly/SameSite; inactividad 60 minutos; duración absoluta 12 horas; sin remember-me inicial; DEV local explícito y aislado; sesiones administrativas/asistidas más cortas | `APPROVED_BY_DIRECTOR` |
| `MULTI_DIMENSION_RATE_LIMIT_WITH_PROGRESSIVE_BACKOFF` | Límites multidimensionales con backoff | Login: 5 fallos/15 minutos; recuperación: 3 solicitudes/hora; reclamación: 3 intentos diarios por cuenta y perfil; límites por cuenta/identificador, IP, dispositivo y operación; sin bloqueo permanente automático | `APPROVED_BY_DIRECTOR` |
| `FIVE_REVOCABLE_DEVICE_SESSIONS` | Cinco sesiones revocables por dispositivo | Máximo cinco activas; creación, último uso, dispositivo aproximado, IP redactada, estado y revocación; revocación individual; password/recovery y cambios críticos revocan anteriores; prohibidas cuentas compartidas de operadores | `APPROVED_BY_DIRECTOR` |
| `SCOPED_AUDITED_SUPPORT_ASSISTED_SESSION` | Sesión asistida de soporte acotada y auditada | Sin impersonación libre; `support_assisted_session` vinculada a `platform_case`; operador, motivo, alcance y vencimiento obligatorios; indicador visible; auditoría inmutable; niveles R0–R3; sin acceso clínico por defecto; autorización adicional para excepción; autocierre; no autoaprobación de claim | `APPROVED_BY_DIRECTOR` |

## 7. Alcance aprobado para Actividad 4 (implementación no iniciada)

Identificador sugerido: `ACTIVITY-4-OF-22/PRODUCT-IMPLEMENTATION/MXMed-Identity-Auth-Session-Foundation-V2`.

Una sola actividad de implementación, condicionada a cerrar los cuatro gates internos en orden. La Actividad 4 sigue `NO INICIADA`.

- **Contratos y backend:** cuenta/ownership/membership, estados, endpoints de registro, login, logout, recuperación, verificación, sesiones y claim; resolver único fail-closed que separe autenticación, rol, propiedad y capacidad de plan.
- **Archivos previstos:** nuevos módulos de cuenta/auth/session/claim, repositorios y controladores, adaptador PHP al contrato AWS, configuración de entorno documentada y migraciones mínimas versionadas. No tocar los stubs hasta que el contrato y las pruebas estén listos; no modificar el contenido visual aprobado sin gate.
- **Migraciones:** cuenta, credenciales/verificación, relaciones account↔professional entity/profile, claims, recovery records, sessions/revocations y audit events; sin datos reales ni seeds de producción durante esta actividad.
- **Contratos HTTP:** sólo métodos y payloads definidos tras C1–C8; anti-enumeración, CSRF donde corresponda, idempotencia, códigos estables y errores genéricos. Mail/SMS se simulan con adaptadores de prueba, nunca con destinatarios reales.
- **Tests:** unitarios de normalización/hash/estados; integración de resolver/ownership; contratos de endpoints; seguridad (rate limit, CSRF, fixation, revocación, IDOR); pruebas de migración y rollback. Fixtures sintéticos sin cuentas clínicas.
- **UI impact:** UI-0 si sólo se entregan contratos/adaptadores; UI-1 si se conectan formularios existentes sin cambio visual; UI-2/3 requieren aprobación separada. `mobile smoke` sólo interim si se toca frontend; el cierre final móvil continúa `MOBILE_RESPONSIVE_FINALIZATION_PENDING`.
- **Riesgos/rollback:** migración reversible y bandera de activación fail-closed; apagar rutas nuevas, revocar sesiones de prueba y revertir el commit de actividad sin borrar evidencia. No activar claim, recovery o login en 8091 sin QA.
- **Criterios PASS:** baseline preservado; no secrets/PII; contratos C1–C8 trazables; auth≠authorization; sesiones rotan y revocan; anti-enumeración y límites comprobados; endpoints sólo en fixtures; 8091 intacto; diff visual cero si UI-1.
- **Exclusiones:** no pagos/Stripe, correo transaccional productivo, SMS real, panel visible de dispositivos, passwordless, MFA para profesionales, reclamación operativa completa/aprobación manual, impersonación o soporte asistido operativo, rediseño/copy no aprobado, acceso clínico, migración masiva de usuarios reales, lanzamiento productivo ni cambios AWS fuera del runtime de sesiones aprobado.

### Gates internos obligatorios

Cada gate debe cerrar con `PASS` antes de continuar; no se integra parcialmente a `program` sin revisión.

- **Gate 4A — Modelo y migraciones:** cuentas, membresías, entidad, perfil, ownership, estados, migraciones reversibles y contratos backend; sin UI.
- **Gate 4B — Autenticación y recuperación:** registro, activación, hashing, login/logout, recuperación, tokens, rate limiting, pruebas de seguridad y adaptadores de correo simulados/controlados, nunca producción.
- **Gate 4C — Sesiones y autorización:** adaptador runtime AWS Redis/Valkey, cookies, rotación, revocación, expiración, eliminación de `transitional_open`, autorización fail-closed y relación con la autoridad de capacidades de Actividad 2.
- **Gate 4D — Integración controlada frontend:** conectar superficies existentes sin rediseño, objetivo de visual diff cero, mínimo UI-2 por cambio de comportamiento, puerto separado `8140+`, revisión directoral, `INTERIM_MOBILE_SMOKE_ONLY` y rollback comprobado.

Clasificación del alcance: UI-0 para contratos/backend sin UI; UI-2 mínimo si Gate 4D cambia comportamiento; UI-3 si aparece rediseño, copy o composición visual. El capítulo móvil final sigue pendiente como UI-3.

## 8. Evidencia y gobernanza

La evidencia histórica no versionada se encuentra en `/tmp/mxmed-activity03-identity-access-security-audit-v2/`. La evidencia de este cierre se encuentra en `/tmp/mxmed-activity03-identity-access-security-decisions-v2/`; contiene decisiones aprobadas, gates, exclusiones, alcance documental, no-cambio UI, no-datos sensibles, QA y Git. Los JSON son estructurales y no contienen secretos, contraseñas, hashes, tokens, IDs de sesión, cookies, credenciales, correos personales completos ni datos clínicos/pacientes.

El registro de auditoría y el cierre de decisiones se añadirán al Plan Maestro con numeración única. La Actividad 3 queda lista para integración fast-forward; el contador oficial no cambia hasta integración explícita.

## 9. Gate de cierre

`PASS` únicamente con `IDENTITY_ACCESS_SESSION_DIRECTOR_DECISIONS_READY_FOR_INTEGRATION`. Si se detecta `baseline mismatch`, historia de auditoría alterada, alcance documental excedido, implementación de Actividad 4, colisión de numeración, modificación de UI oficial, evidencia sensible o cambio de 8091, el estado es `BLOCKED` (o el bloqueo específico aplicable). Siguiente paso: esperar integración fast-forward; no iniciar Actividad 4.
