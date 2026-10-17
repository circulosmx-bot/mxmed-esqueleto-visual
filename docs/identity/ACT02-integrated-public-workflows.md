# ACT02 — preparación integrada de flujos públicos

Baseline `41f5147e50e8f29b2be093e7fe2d6cd62fe32cc2`, rama `ux/consultation-step2-vitals-r1`, worktree inicial limpio y upstream igual. Esta entrega prepara código y despliegue; no aplica DDL ni habilita flujos en `https://localhost:8443`. Las decisiones operativas siguen vacías en [decisions.json](../../modules/identity/deployment/act02/decisions.json). No es un receipt.

Se reutilizan los servicios y componentes de [PUB-ENR02](PUB-ENR02-pending-enrollment.md), [PUB-ENR03](PUB-ENR03-reviewed-resolution-handoff.md), [PUB-COR01](../profiles/PUB-COR01-secure-intake-human-review.md), [PUB-COR02](../profiles/PUB-COR02-governed-typed-application.md) y C4. [C4-ACT01](C4-ACT01-operational-activation-package.md) conserva sus antecedentes, no una autorización actualizada. No se crea otro portal, login, editor de perfiles, maestro profesional o writer de suscripciones.

## Fuente comercial explícita

`PublicCommercialPlanSource` incorpora la fuente canónica `profile_subscriptions` al perfil público mediante el read model existente con `commercialOnly=true`. No consume el override local del widget del doctor 1. Conserva sus entitlements y selección de suscripción; no reimplementa pagos ni activa planes. El lector comercial existente puede usar FREE como fallback; **ese fallback no acredita elegibilidad**. La consulta canónica ofrece un modo estricto opcional para este adaptador: un error de almacenamiento no se confunde con ausencia de suscripción. Los demás consumidores conservan su contrato anterior.

| Estado | Lectura pública / elegibilidad |
| --- | --- |
| Suscripción actual activa FREE explícita | FREE de `profile_subscriptions`; Claim sin upgrade si los demás guards permiten. |
| Basic, Standard, Optimum, Professional activos | Mismo plan efectivo del read model comercial; entitlements existentes, sin Claim ni intake FREE de correcciones. |
| Suscripción desconocida, inactiva o fallback por vencimiento | Fuente no apta para conceder Claim o correcciones. No se infiere FREE. |
| Perfil precargado FREE explícito sin suscripción | Tres campos aditivos: `preloaded_free_plan='free'`, referencia de mapping revisado y fecha. Sin defaults ni backfill. |
| Campo histórico explícito reconocido, sin suscripción | Compatibilidad con los campos existentes; un valor desconocido nunca se convierte en autoridad FREE. |
| Falta de datos o fallo de fuente | Elegibilidad cerrada; las reglas conservadoras de presentación siguen ocultando contacto FREE. |

La [migración comercial](../../modules/subscriptions/db/2026_10_16_01_preloaded_public_free_source.sql) prepara únicamente la declaración explícita FREE, con CHECK de los tres campos completos, incluyendo el rechazo explícito de NULL para impedir que SQL UNKNOWN acepte un mapping parcial. Una suscripción actual prevalece sobre esa declaración. No hay segunda tabla de planes ni edición comercial desde el runtime público. El mapping de perfiles reales necesita revisión y autorización separada; no se presupone que todos los registros sin suscripción sean gratuitos. El código nuevo permanece inactivo en el esquema normal mientras falten esas columnas. Los perfiles sintéticos de QA sí migran de su columna legacy a un mapping explícito nuevo antes del navegador.

Claim, recepción de informes y resolución usan el mismo `PublicProfileRepository`/DTO. Se corrige además la ruta de autoload de las carpetas minúsculas Subscriptions para evitar cargar dos veces una clase por diferencias de mayúsculas en macOS; no cambia el cálculo de capacidades.

## Paquete de despliegue y restauración

El inventario ejecutable es `DeploymentPackage::migrations()`. Generar el manifiesto completo, ordenado y con SHA-256:

```sh
php scripts/act02-deployment.php --manifest > /ruta/privada/nueva/manifest.json
php scripts/act02-deployment.php --connection /ruta/privada/connection.json
```

El CLI sólo inspecciona: no tiene modo apply, generación de aprobación o activación. Devuelve identidad exacta, fuente, fingerprint de esquema bajo el principal conectado y readiness. El principal es parte del fingerprint; el hash obtenido como administrador no sustituye el del runtime restringido.

| Orden | Artefacto |
| --- | --- |
| 1–6 | Identity `2026_10_16_01` security bindings → `02` binding → `03` evidence → `04` issuer → `05` enrollment → `06` resolución. |
| 7 | Profiles `2026_10_16_01_public_correction_reports.sql`. |
| 8 | Profiles `2026_10_16_02_correction_applications.sql`. |
| 9 | Subscriptions `2026_10_16_01_preloaded_public_free_source.sql`. |
| 10 | [metadata.sql](../../modules/identity/deployment/act02/metadata.sql), lector operacional de triggers sin argumentos. Requiere sustituir target y definer revisados. |

Se preservan las tablas OP-ACT02A. Las migraciones publicadas 01–06/PUB-COR01/02 no se reescriben. La 04 conserva su rechazo previo a DDL si las autoridades de 02 ya están pobladas. La etapa 10 se justifica por el contrato real de MySQL: ver triggers exige TRIGGER, un privilegio DDL que el runtime no debe tener. Una vista con definer tampoco resuelve ese filtro en MySQL 9.6. Se prepara un **procedimiento READS SQL DATA, SQL SECURITY DEFINER**, con una cuenta SQL separada bloqueada para login, TRIGGER sólo en el target y EXECUTE de ese lector. El runtime recibe sólo EXECUTE; verifica cuerpo, seguridad, metadatos y fingerprint. No se conceden SHOW_ROUTINE ni otros privilegios globales. Los dos procedimientos de auditoría siguen usando su contrato canónico y definer restringido.

Ensayo reproducible autorizado exclusivamente sobre un target aleatorio nuevo:

```sh
python3 scripts/act02-rehearsal.py \
  --connection /ruta/privada/connection.json \
  --evidence /ruta/privada/nueva/ACT02-restore
```

El runner usa `mysqldump --single-transaction` con datos/rutinas, conserva el dump sólo en memoria y compara hashes de las columnas originales y multiplicidad de filas. Lee de nuevo el origen para detectar cambios durante backup/ensayo. Los triggers se restauran desde sus cuerpos reales en `information_schema`: evita el terminador inválido que mysqldump 9.6 produce para algunos triggers SIGNAL con punto y coma final. No elimina triggers para importar sobre una base real.

Primero restaura los datos y provoca una interrupción de la migración 01; el replay ciego falla. Recupera **la copia temporal completa** desde el backup original, vuelve al preestado y aplica secuencialmente las diez etapas. Cada checkpoint registra preservación y fingerprint de metadatos. Revisa todas las FKs, el contrato de auditoría y el principal restringido. Finalmente elimina DB/usuarios propios y archivos de conexión; sólo conserva hashes, conteos y evidencia privada. No conserva filas copiadas, ni toca `mxmed`, QA05, OP02 o UI03.

Recuperación operacional propuesta: ante DDL parcial, detener el despliegue, inspeccionar objetos/checkpoint y restaurar el backup completo en un target nuevo; no rerun parcial ni DROP improvisado sobre historia aceptada. Antes de cualquier DDL real se deben repetir fingerprints, verificar el backup y autorizar la ventana y los definers exactos. Un revert Git no revierte DDL.

## Composición y autorización

`WorkflowFactory` compone Identity, MFA, Evidence/issuer, PublicEnrollment/ReviewedEnrollment, ReportRuntime y ApplicationService existentes sobre el mismo PDO. No duplica writers. Reutiliza storage privado, OTP canónico y auditoría. `OperationalRuntime` consulta sólo el directorio fijo privado del usuario de servicio:

`~/Library/Application Support/MXMED/public-workflows/`

La instalación real requeriría `installation.json`, `authorization.json`, `trust.json` y una fuente privada de secretos y un `connection_file` privado para el PDO restringido. **No se crean aquí.** El loader no acepta paths de query string ni un flag de entorno como aprobación. Los archivos deben ser externos al repositorio, absolutos/canónicos, privados, del UID efectivo y sin symlinks en su ruta inmediata. La instalación y el trust root son actos de custodia externos pendientes; el paquete no crea firmantes operativos.

La autorización Ed25519 privada y de máximo 24 horas liga exactamente servidor UUID/versión, DB, principal runtime, principal de despliegue atestado, origen, hashes de fuentes/esquema, referencias aprobadas de privacidad/custodia/recuperación/retención/legal hold/disposición/abuso/staff/piloto, key IDs y hashes de cada secreto y su archivo fuente, storage, workflows y cohortes finitas de perfiles/cuentas. Sólo admite los siete workflows enumerados; ownership, Operadores y firma clínica no son opciones. Revalida staff activo, cuenta verificada, clase MFA elegible, grant exacto y factor activo. Rechaza root, DDL, permisos globales y escrituras sobre fuentes profesionales, perfiles, membresías, grants, suscripciones o clínica.

El PDO operacional se ensambla con la configuración canónica normal-local de Identity y sus sesiones; no sustituye el PDO clínico ni las variables de conexión del proceso. La conexión y el archivo exacto de credenciales quedan ligados por la autorización privada. Los grants de ensayo incluyen LOCK TABLES en el target porque MySQL lo exige para los SELECT FOR UPDATE de fuentes que deben permanecer de sólo lectura, como staff y grants; no conceden TRIGGER al runtime.

Los guards se ejecutan en una transacción nativa READ ONLY y **antes** de construir los writers privilegiados. Se exige `foreign_key_checks=1` y CHECKs enforced; la huella incorpora motores, collations, reglas/esquemas referenciados por FKs y definers/orden/contexto SQL de triggers. El protocolo del metadata reader es de sólo lectura, con cuerpo y resultados ligados al fingerprint; no tiene parámetros ni SQL dinámico. Las operaciones de dominio conservan además sesión/credencial, grant, reauth/MFA, caso, versión, audit e idempotencia. `WorkflowScope` restringe las cuentas y recursos en los límites de los servicios; filtra colas por perfil y cuenta antes de paginar y rechaza otro perfil/solicitud antes de entregar payload o mutar. Cada actor staff debe tener esa capacidad expresamente incluida en el mapa firmado del piloto, además de su grant canónico vigente. MFA sólo recibe UPDATE sobre `last_totp_time_step` y `enrollment_context`; enrolamiento, activación y recuperación usan otro principal. El grant del despliegue no reemplaza la autoridad de cada actor. Los origins anónimos no crean una cuenta ni autoridad; sólo una cuenta explícitamente incluida puede continuar el intake autenticado. La selección exacta del piloto sigue pendiente.

El entry original de acreditación, el workspace staff y los runtimes de enrollment/correcciones usan este compositor opcional. La integración de notificaciones conserva la continuación del enrollment durante registro/verificación, reutilizando Identity. Sin instalación aprobada, el resultado original continúa inactivo. No hay proveedor sintético en el loader normal.

## VID01/CRD02 y límite pendiente de provisioning

`VerifiedPhysicianIdentityService::provisionFromTrustedAuthority` ya permite identidad nueva con nombres estructurados, timestamp y referencia de fuente, aprobador interno activo y rechazo de duplicados. No convierte el nombre legacy en identidad. `VerifiedDoctorCredentialService::provisionFromTrustedAuthority/verify` ya exige información profesional completa y contexto interno, conserva fuentes/lifecycle y audita dentro de su propia transacción; rechaza una transacción exterior. No convierte una cédula declarada en verificada.

`ProfessionalSourceReadiness` integra esas fuentes canónicas en la sección original staff de resolución: informa si VID01 y CRD02 están presentes, únicos, vigentes y no sintéticos según `EvidenceAuthority::sources`. La UI explica honestamente PENDING_SOURCES y sólo permite resolución positiva cuando los servicios originales reconocen la verificación real.

**Dependencia de fuente todavía pendiente:** no existe un intake gobernado de fuentes primarias para precargados sin VID/CRD que ligue evidencia profesional genuina, acceso humano, capacidad específica, caso/reauth/MFA y recuperación de ambos writers. No se lo reemplaza con un formulario que acepte nombre/cédula/referencia libre como prueba. El adaptador entregado es de readiness, no un writer de provisioning. Para preparar esa mutación hacen falta el contrato de evidencia/revisión y resolver la transacción propia de CRD02 junto con la auditoría/proveniencia VID01. No se habilita una acción de provisioning ficticia en el workspace ni se fabrica una attestación. Este punto debe permanecer explícito en el resultado; una fuente ya legítimamente provisionada sí puede continuar por PUB-ENR03/C4.

## Staff, SMS y decisiones del Director

No hay provisión real en esta entrega. La cuenta colaboradora actual no se promueve. El primer Director interno requiere `FirstDirectorBootstrapService` y la ceremonia `governance-ceremony.php bootstrap`, con evidencia externa v2 independiente, firma/trust root legítimos, reauth y autorización exacta; rechaza una cuenta con membresía médica. Después, ese Director puede usar `InternalGovernanceService::execute` para `register`/`appoint_master` y `grant` de las tres capacidades exclusivas, conservando auditoría. El runtime público preparado no puede escribir `internal_staff`, grants o membresías.

MFA requiere `GovernanceStaffMfaCeremony` y `mfa_begin/mfa_activate`: sujeto y registrador **distintos**, DIRECTOR/MASTER_ADMIN activos, sesiones/reauth actuales y registrador legítimo permitido. Una persona puede revisar ordinariamente un expediente; el registrador independiente de enrolamiento no es otro revisor obligatorio. ADVISOR no puede enrolarse con la política vigente: cambiarla exige decisión del Director y código/QA específico, no promoción silenciosa.

`SmsIntegration::APPROVED_ADAPTERS` está vacío. La integración concreta es `Agenda\Contracts\OtpProviderPort` → `PhoneVerificationService`; no un segundo sistema OTP. Factory exige proveedor configurado y, para modo real, clase y hash de fuente aprobados. No se elige proveedor, envía SMS o simula aceptación en producción. La clave HMAC OTP sigue usando dominio/key ID canónicos, separada de la credencial del gateway. El fixture sintético sólo existe en routers de QA cuyo DB/usuario no pueden ser `mxmed`.

| Decisión | Aprobación necesaria; valor actual |
| --- | --- |
| D01 | Gateway SMS real, cuenta/sender/cobertura, credencial mínima, endpoint/región, costes/timeouts e integración revisada. **Pendiente.** |
| D02 | Custodios y suplencia, origen privado de keyrings/secretos, backup cifrado independiente y ensayo de recuperación. **Pendiente.** El factory inicial admite una clave por propósito; rotación preservando versiones históricas requiere un incremento revisado. |
| D03 | Finalidad/avisos, retención por tipo, acceso, legal hold y disposición compatible con historia inmutable. **Pendiente.** No existe purga operacional nueva. |
| D04 | Cuentas staff legítimas, clase/permisos, registrador MFA independiente y política/evidencia de provisioning profesional. **Pendiente.** |
| D05 | Perfiles/cuentas/acciones del piloto, límites de abuso, mapping FREE explícito y ventana de operación. **Pendiente.** |

Codex entrega código, contratos, manifiesto, inspector, grants de ensayo y QA. Las cinco decisiones, las personas, el trust root, las attestaciones, el mapping real y la activación requieren autorizaciones externas concretas; no se inventan valores.

## Aplicabilidad de correcciones

Se conserva exactamente PUB-COR02: sólo un teléfono global existente, realmente publicado, sin usos privados/citas/verificación y sin administración activa. FREE normalmente lo oculta; ni el source comercial ni el aplicador lo publican para forzar aplicabilidad. Intake y aceptación no significan que exista un campo aplicable.

Siguiente candidato de valor: dirección de consultorio con ID exacto. `ConsultoriosController` usa PDO implícito, actor médico y patch/upsert con geocodificación/defaults; necesita un adaptador staff tipado, revisión de efectos y transacción/auditoría propias. Teléfono de sede procede de otra fuente; `doctor_contact_points.consultorio_id` no autoriza reasignación. Horarios reemplazan semanas y afectan disponibilidad/conflictos de Agenda con transacción propia. **No se implementan esos adaptadores aquí.** Retiro/fallecimiento, identidad, ownership, merges y clínica siguen denegados.

## Evidencia y alcance de QA

Evidencia privada: `~/Library/Application Support/MXMED/act02/20261016-review/`. El restore completo pasa diez etapas, preserva 128 tablas originales y verifica 206 grupos FK sin huérfanos. Se conserva cada intento diagnóstico y su disposal; sólo la ejecución final acreditada se usa para aceptación. No se importan esas filas en los navegadores: sus DB/usuarios/Valkey/TLS/documentos/cuentas/grants son nuevos y sintéticos.

Los controles adicionales del guard prueban una tabla realmente ausente con autorización ligada a la huella parcial actual y FK enforcement desactivado; no dependen únicamente de falsificar un hash de esquema.

QA del compositor:

```sh
python3 scripts/gov03b-disposable-qa.py --connection /ruta/privada/connection.json \
  --evidence /ruta/privada/nueva/ACT02-correction --suite application --act02
python3 scripts/gov03b-disposable-qa.py --connection /ruta/privada/connection.json \
  --evidence /ruta/privada/nueva/ACT02-enrollment --suite resolution --act02
```

Las suites retienen los contratos nativos anteriores, agregan pruebas comerciales/guard y pasan el navegador por **WorkflowFactory** y componentes originales. Las autorizaciones de test se firman sólo para su DB sintético, se eliminan al terminar y no sirven para normal-local. La cohorte dinámica de fixtures no es un modelo de aprobación real ni un permiso de ampliar automáticamente un piloto. Se prueban fuentes FREE/pago/desconocidas, Claim/Formar parte, resolución/C4, recepción/revisión/aplicación, falta/expiry de autorización, secretos, workflow/cohorte y privilegios SQL. Chromium/WebKit a 1440×900, 1366×768 y 820×1180. No se acredita Safari físico.

Se conservan los fallos históricos de los pins `PhysicianCredentialAuditPolicyTest` (52 frente a 57 eventos), `PublicProfileHeroConsultorioContactLayoutTest` (literal Material Symbols) y `AUTH02A_29` (fixture UX-ALIGN01 ya presente). No se cambian sus pruebas para convertirlas en PASS.

## Siguiente autorización separada

Primero revisar el paquete exacto, principal/definer, backup/restauración y las diez etapas; autorizar DDL bajo fingerprints nuevos. El mapping FREE real es otra mutación de datos revisada, nunca un default. Separadamente resolver D01–D05, el provisioning de fuentes genuinas y la composición operativa: instalación/trust/secretos, staff/MFA y piloto exactos. DDL o una variable de entorno no habilitan el servicio. No se emiten receipts operativos, ni se activan submissions, owners, Operadores o firma clínica.

## Resultado de validación de fuente

Correcciones pasa 264 comprobaciones nativas y 35 grupos de navegador (17 PUB-COR02, 18 PUB-COR01). Enrollment/C4 pasa 89 comprobaciones nativas, 12 recorridos completos y 25 grupos de navegador, incluyendo registro/verificación/login, Claim y Formar parte, revisión humana, teléfono, documentos privados, confirmación y emisor; no crea owner ni habilita firma. Ambos navegadores, Chromium y WebKit, cubren los tres tamaños solicitados. Las capturas originales fueron inspeccionadas. Safari físico del Director: **NOT_RUN**.

Después del navegador se cierran tres denegaciones de backend con QA nativa específica: los seis mappings comerciales parciales (semántica NULL/UNKNOWN), una fuente canónica ilegible y el replay de un origen cuyo perfil salió del piloto. El read model de suscripciones se revalida sin cambiar sus consumidores anteriores. `source-final/` pasa 36 controles comerciales, 44 del guard/compositor y dos de esquema parcial real; conserva también las 89 comprobaciones de dominio. La firma inválida se rechaza. Los navegadores no se presentan como reejecutados después de esos tres ajustes, que conservan el recorrido positivo.

Regresiones: normal-local aislado 80, sesión privada 20, GOV-SIM02 diez, Agenda CONS-VIS01 catorce, Operadores 200 aserciones más marcador agregado, readiness ocho y presentación 74; resolver clínico, gate de corrección (52 aserciones), visibilidad pública y read model comercial pasan. Los tres fallos históricos indicados arriba permanecen; no se declara verde su runner completo. Los cambios de huella durante iteraciones de desarrollo provocaron rechazos esperados del guard; sólo las ejecuciones finales completas acreditan navegador y se conservan los intentos previos con su disposal.

La preparación sigue **PARTIAL** respecto al alcance completo ACT02 por el writer gobernado VID01/CRD02 faltante. El factory, guard y ensayo integrado son entregables verificables; su PASS no autoriza una operación real ni convierte el adaptador de readiness en provisioning.

## Inventario de entrega

49 archivos de fuente, QA y documentación; no incluye secretos, receipts ni evidencia privada.

- `api/identity/physician-onboarding.php`
- `assets/js/identity/physician-enrollment.js`
- `assets/js/identity/physician-onboarding.js`
- `docs/identity/ACT02-integrated-public-workflows.md`
- `docs/identity/C4-ACT01-operational-activation-package.md`
- `docs/profiles/PUB-COR02-governed-typed-application.md`
- `modules/identity/Accreditation/EvidenceAuthority.php`
- `modules/identity/Accreditation/PhysicianAdmissionComposition.php`
- `modules/identity/Accreditation/PhysicianOnboardingHttp.php`
- `modules/identity/Accreditation/PhysicianOnboardingPage.php`
- `modules/identity/Accreditation/PublicEnrollmentHttp.php`
- `modules/identity/Accreditation/PublicEnrollmentRuntime.php`
- `modules/identity/Accreditation/PublicEnrollmentService.php`
- `modules/identity/Accreditation/ReviewedEnrollmentService.php`
- `modules/identity/Operational/ActivationGuard.php`
- `modules/identity/Operational/DeploymentPackage.php`
- `modules/identity/Operational/OperationalRuntime.php`
- `modules/identity/Operational/ProfessionalSourceReadiness.php`
- `modules/identity/Operational/SmsIntegration.php`
- `modules/identity/Operational/WorkflowFactory.php`
- `modules/identity/Operational/WorkflowScope.php`
- `modules/identity/Operational/WorkflowScopeDenied.php`
- `modules/identity/deployment/act02/decisions.json`
- `modules/identity/deployment/act02/metadata.sql`
- `modules/identity/deployment/act02/preparation.json`
- `modules/identity/http/IdentityHttpComposition.php`
- `modules/identity/tests/Act02CommercialIntegrationTest.php`
- `modules/identity/tests/Act02IntegrationTest.php`
- `modules/identity/tests/support/Act02SyntheticComposition.php`
- `modules/identity/tests/support/PublicEnrollmentQaRouter.php`
- `modules/profiles/Corrections/ApplicationAuthority.php`
- `modules/profiles/Corrections/ReportEligibility.php`
- `modules/profiles/Corrections/ReportHttp.php`
- `modules/profiles/Corrections/ReportReviewService.php`
- `modules/profiles/Corrections/ReportRuntime.php`
- `modules/profiles/Corrections/ReportService.php`
- `modules/profiles/controllers/PublicProfileController.php`
- `modules/profiles/repositories/PublicProfileRepository.php`
- `modules/profiles/services/PublicProfileClaimEligibility.php`
- `modules/profiles/tests/support/CorrectionQaRouter.php`
- `modules/subscriptions/db/2026_10_16_01_preloaded_public_free_source.sql`
- `modules/subscriptions/repositories/CurrentSubscriptionRepository.php`
- `modules/subscriptions/services/PublicCommercialPlanSource.php`
- `partials/identity/physician-enrollment-intake.php`
- `scripts/act02-deployment.php`
- `scripts/act02-principal.py`
- `scripts/act02-rehearsal.py`
- `scripts/gov03b-disposable-qa.py`
- `scripts/pub-enr03-browser-qa.py`
