# AWS y entornos — mapa de componentes existentes

## Alcance, ancla y clasificación

CTX-MAP02; inspección de fuente **2026-10-17**, baseline
`1b8dfccb68a7613b2182507a840454631dbf012a`, rama
`ux/consultation-step2-vitals-r1`. Se leyó la implementación y evidencia versionada;
no se ejecutó AWS CLI/API, SSO, CDK, builds, tests, contenedores ni scripts operacionales.
**AWS_CURRENT_REMOTE_STATE=NOT_VERIFIED_REMOTE** para todos los componentes.
Una evidencia histórica de recursos eliminados no representa un inventario cloud actual.

Este mapa sucede las afirmaciones de estado temporal del [README AWS](../../infra/aws/README.md),
sin reemplazar sus contratos ni borrar su historia. La aprobación de diseño de PP245,
PP249–271 está registrada en el [contrato acumulativo de perfiles](../PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md)
y referenciada por el README. Son decisiones del repositorio, no autorizaciones nuevas
para desplegar. No se vuelve a verificar disponibilidad regional/versiones con proveedores.

Se conservan cinco ejes: **PRODUCT_POLICY** approved para los contratos citados;
**SOURCE** implemented, partial o historical según la tabla;
**CONNECTIVITY** optional-composition para los recursos opt-in;
**VERIFICATION** source-inspected, más evidencia histórica identificada;
**OPERATION** external-state-not-verified. Los gates abiertos no equivalen a código ausente.

Denominador medido con `git ls-files`: **210 archivos versionados en `infra/aws/`**;
83 en `lib/` (22 archivos de stacks, 5 stages, 16 aspects, 17 constructs,
9 configuraciones y 14 utilidades); **84 archivos de pruebas/soporte**;
4 scripts en `scripts/aws/` y 2 cierres en `docs/aws/`.
`rg --files` sin ajustes omite algunos archivos versionados cuyo nombre contiene
`backup`, por las exclusiones locales; el inventario Git evita esa omisión.
Esto mide cobertura de artefactos, no resultados de ejecución ni cobertura funcional.

## Selección de ambiente y grafo CDK — AWS-ENV01

Entrada: [createMxMedApp](../../infra/aws/bin/mxmed.ts), `cdk.json` y
[package.json](../../infra/aws/package.json). El entrypoint resuelve contextos con
[getEnvironmentConfig](../../infra/aws/lib/config/environments.ts),
[validateEnvironmentConfig](../../infra/aws/lib/config/environment-schema.ts) y
[launch-profiles](../../infra/aws/lib/config/launch-profiles.ts).
Sin combinación válida se rechaza antes de crear stages; no se infiere desde rama,
hostname, plan de un médico ni fecha. `CDK_DEFAULT_ACCOUNT` aporta identidad cuando
existe; la ruta general admite síntesis offline sin ella. C3 tiene su propio objetivo
cerrado y autoridad externa; aquí no se reproducen identificadores de cuenta.

| Selector | Contrato implementado | Consumidor / límite |
|---|---|---|
| `environment` | `staging`, `production` | Ambos fijan región primaria `mx-central-1`; Email/Edge global/Cost en `us-east-1`. Son pins del proyecto. |
| `deploymentProfile` | `launch-lean-v1`, `production-standard-v1`, `scale-ready-v1` | Staging admite lean y ventana `release-window-v1`; production admite los tres. Capacidad/costos en definición central. |
| `computeActivationMode` | `disabled-v1`, `registry-only-v1`, `tasks-ready-v1`, `service-enabled-v1` | Registry, tasks y servicio se activan por separado. La ruta general no despliega automáticamente. |
| `runtimeCapabilityProfile` | `directory-core-v1`, `paid-profile-v1`, `clinical-v1`, `professional-ai-v1` | Determina inyección de providers/recursos. No atribuye capacidades clínicas a una cuenta. |
| `edgeActivationMode` | disabled, media, origin, public-traffic | [edge-config](../../infra/aws/lib/config/edge-config.ts) mantiene gates reales cerrados. `edgeDnsMode` none/external/Route53 requiere modo compatible. |
| `operationsActivationMode` | disabled, cost-controls, lean-observability, production-observability | [operations-profiles](../../infra/aws/lib/config/operations-profiles.ts); notificaciones y protección de logs independientes. |
| `backupDrActivationMode` | disabled, regional-recovery, cross-region-copy, restore-validation | [backup-dr-profiles](../../infra/aws/lib/config/backup-dr-profiles.ts); residencia, región DR, validación y cleanup tienen gates propios. |
| `activity=c3-ephemeral` | Grafo staging dedicado | Network, Security, Session, Registry, Runner y Janitor; no incluye RDS ni una aplicación productiva. |

Stages: [MxMedEnvironmentStage](../../infra/aws/lib/stages/mxmed-environment-stage.ts)
conecta Network/Security → Data/Storage/Session → Registry/Compute/Jobs, más Edge
regional, Operations y Backup condicionales;
[MxMedEmailStage](../../infra/aws/lib/stages/mxmed-email-stage.ts) aloja SES;
[MxMedGlobalEdgeStage](../../infra/aws/lib/stages/mxmed-global-edge-stage.ts) aloja Edge global;
[MxMedGlobalOperationsStage](../../infra/aws/lib/stages/mxmed-global-operations-stage.ts)
compone Cost y, cuando aplica, Edge y Operations globales sin duplicarlos;
[MxMedC3EphemeralStage](../../infra/aws/lib/stages/mxmed-c3-ephemeral-stage.ts)
es la composición temporal aislada.

Pins de toolchain en fuente: Node 22.22.0, npm 10.9.4, CDK library 2.260.0,
CDK CLI 2.1131.0 y TypeScript 6.0.3. El lockfile mantiene versiones exactas.
No se actualizaron dependencias ni se certifica su vigencia externa.

## Inventario de stacks, writers y límites

Los enlaces identifican las **22 definiciones de stack**. IAC indica recursos
implementados en fuente; TEST indica assertions disponibles o evidencia offline histórica,
sin ejecutarlas en CTX-MAP02. Todo estado remoto actual sigue no verificado.

| ID / archivo y símbolo | Ownership de recursos / conexiones | SOURCE y evidencia |
|---|---|---|
| AWS-ENV01 · [BaseMxMedStack](../../infra/aws/lib/stacks/base-mxmed-stack.ts) | Nombre, tags, retención/termination protection y synthesizer común. No crea un servicio. | implemented; source-inspected. |
| AWS-NET01 · [MxMedNetworkStack](../../infra/aws/lib/stacks/mxmed-network-stack.ts) | VPC IPv4; dos AZ contractuales; tiers public-ingress/private-app/private-endpoints/isolated-data; NAT por perfil, S3 gateway endpoint e interface endpoints condicionales, SG y Flow Logs. | IAC; TEST network/aspects; deployment histórico C3. No se copian CIDRs privados. |
| AWS-SEC01 · [MxMedSecurityStack](../../infra/aws/lib/stacks/mxmed-security-stack.ts) | Cuatro KMS keys: application-data, secrets, audit, backup; secretos propios/referencias de provider; IAM boundaries y roles, CloudTrail management y archivo privado. Pasa referencias a otros stacks. | IAC; TEST security/least privilege; deployment histórico C3. |
| AWS-DATA01 · [MxMedDataStack](../../infra/aws/lib/stacks/mxmed-data-stack.ts) | RDS MySQL 8.4.9, parameter/subnet group, Enhanced Monitoring; secreto master administrado; secreto generado `mxmed_app` sólo con tasks. Crear el secreto no crea el usuario SQL. | IAC; TEST data; no prueba física de RDS hallada en los dos cierres AWS. |
| AWS-STORE01 · [MxMedStorageStack](../../infra/aws/lib/stacks/mxmed-storage-stack.ts) | Cuatro buckets: publicMedia/privateDocuments/clinicalRecords/uploadQuarantine; KMS, Block Public Access, SSL, versionado, lifecycle y RETAIN. "publicMedia" describe uso, no bucket anónimo. | IAC; TEST storage. Quarantine emite EventBridge; no scanner desplegado acreditado. |
| AWS-SESSION01 · [MxMedSessionStack](../../infra/aws/lib/stacks/mxmed-session-stack.ts) | ElastiCache Valkey 8.2 dedicado, parameter/subnet group, RBAC user/group, default deshabilitado, TLS y KMS; secreto auth. Pasa endpoint/prefix a Compute/Runner. | IAC; TEST session; prueba física y teardown C3 históricos. |
| AWS-REG01 · [MxMedRegistryStack](../../infra/aws/lib/stacks/mxmed-registry-stack.ts) | ECR + KMS dedicado, tag inmutable, scan-on-push y lifecycle; RETAIN. Registry-first no requiere servicio ECS. | IAC; TEST registry; push/digest y eliminación históricos C3. |
| AWS-APP01 · [MxMedComputeStack](../../infra/aws/lib/stacks/mxmed-compute-stack.ts) | Cluster, logs app/migration, tasks Fargate, imagen por ECR+digest obligatorio; service privado/autoscaling sólo en service-enabled. Secrets de startup separados de task-role. | IAC; TEST compute; brechas de migrator/env/readiness descritas abajo. |
| AWS-EDGE01 · [MxMedRegionalEdgeFoundationStack](../../infra/aws/lib/stacks/mxmed-regional-edge-foundation-stack.ts) | ALB público restringido a CloudFront, HTTPS/certificado externo, header secreto, regla default403, target group al puerto app; health usa readiness. | IAC condicional; TEST edge; no cutover acreditado. |
| AWS-EDGE01 · [MxMedGlobalEdgeStack](../../infra/aws/lib/stacks/mxmed-global-edge-stack.ts) | CloudFront, OAC SigV4, cinco reglas WAF, policies de caché/headers/origin; certificado y DNS opcionales por parámetros. | IAC condicional; TEST edge; runtime gates reales bloquean tráfico. |
| AWS-EDGE01 · [MxMedEdgeStack](../../infra/aws/lib/stacks/mxmed-edge-stack.ts) | Contrato histórico vacío, conservado junto a las implementaciones regional/global. | historical; no usarlo para concluir que Edge está ausente. |
| AWS-EMAIL01 · [MxMedEmailStack](../../infra/aws/lib/stacks/mxmed-email-stack.ts) | Una SES domain identity, Easy DKIM RSA2048 y tres CNAME en hosted zone existente; no crea la zona, SMTP secret ni claves privadas DKIM. | IAC; TEST email; evidencia física SES de 2026-09-02. |
| AWS-OPS01 · [MxMedRegionalOperationsStack](../../infra/aws/lib/stacks/mxmed-regional-operations-stack.ts) | Topics críticos/warning cifrados, alarmas RDS/Valkey/ECS/ALB según inventario/gate y dashboard regional. | IAC condicional; TEST operations; sin subscribers personales automáticos. |
| AWS-OPS01 · [MxMedGlobalOperationsStack](../../infra/aws/lib/stacks/mxmed-global-operations-stack.ts) | Topic/dashboard/alarmas CloudFront/WAF en región global. Reutiliza key Cost. | IAC condicional; TEST operations; no remediación automática. |
| AWS-COST01 · [MxMedCostManagementStack](../../infra/aws/lib/stacks/mxmed-cost-management-stack.ts) | Dos budgets mensuales parametrizados, SNS/KMS y Cost Anomaly Detection; separa CostScope staging/production. | IAC condicional; TEST budgets/anomaly; no importe presupuestado inventado. C3 usa Budget directo separado. |
| AWS-DR01 · [MxMedRegionalBackupStack](../../infra/aws/lib/stacks/mxmed-regional-backup-stack.ts) | Vault cifrado/lock opcional; RDS periódico y S3 crítico continuous+periódico; selections explícitas y rol mínimo; errores a topics Operations. | IAC condicional; TEST backup; no restore real acreditado aquí. |
| AWS-DR01 · [MxMedDrCopyStack](../../infra/aws/lib/stacks/mxmed-dr-copy-stack.ts) | Key/vault destino en región explícita; handoff por parámetros, sin Export cross-region. | IAC fixture condicionada; región DR real no verificada. |
| AWS-DR01 · [MxMedRestoreValidationStack](../../infra/aws/lib/stacks/mxmed-restore-validation-stack.ts) | SG aislado sin ingress/egress, rol, bucket temporal; restore plan/selection sólo en scheduled mode; presupuesto/tiempo/cleanup sin defaults. | IAC fixture; completar restore no equivale a validar aplicación. |
| AWS-DR01 · [MxMedBackupStack](../../infra/aws/lib/stacks/mxmed-backup-stack.ts) | Contrato histórico vacío; el writer IaC real es RegionalBackup/DrCopy/RestoreValidation. | historical; no confundir placeholder con todo el dominio. |
| AWS-JOBS01 · [MxMedJobsStack](../../infra/aws/lib/stacks/mxmed-jobs-stack.ts) | Task Fargate one-shot del batch Media, dos log groups, roles/policy, regla de task events y Scheduler explícitamente DISABLED. Reusa imagen/datos de EnvironmentStage. | IAC condicional, partial operacional; TEST jobs/scheduler. |
| AWS-C301 · [MxMedC3RunnerStack](../../infra/aws/lib/stacks/mxmed-c3-runner-stack.ts) | Runner Fargate puntual, imagen sellada por digest, secret auth, logs y autoridad de ejecución delimitada; sin DB de producto. | IAC; prueba física única histórica C3. |
| AWS-C301 · [MxMedC3JanitorStack](../../infra/aws/lib/stacks/mxmed-c3-janitor-stack.ts) | Step Functions + dos Scheduler one-shot, captura recursos retenidos, elimina stacks en orden, limpia residuos permitidos y programa su propia eliminación. | IAC; TEST janitor; ejecución/eliminación históricas C3. No es un janitor de producción. |

### Red, datos y sesiones

La ruta de autoridad de red es CloudFront → SG ALB/HTTPS → SG app:8080 → SG
Data:3306 o Session:6379. NAT/HTTPS y DNS tienen reglas propias; datos y sesiones
se alojan en subnets aisladas, sin IP pública. Los endpoints de interfaz dependen
del perfil/costo, no se habilitan por el nombre scale-ready. Guardrails en
[network-guardrails](../../infra/aws/lib/utils/network-guardrails.ts).

RDS: `require_secure_transport=ON`, UTF8MB4/UTC, general log apagado, slow/error
logs, sin upgrades automáticos; retención/deletion protection por perfil.
RDS Proxy, replicas de lectura y Aurora no se activan por tener clases de mayor tamaño.
PITR nativo y snapshots no son una migración del esquema aplicativo.

Valkey: [session-contract](../../infra/aws/lib/constructs/session-contract.ts)
delimita keys `mxmed:stg:session:*` / `mxmed:prd:session:*`, ACL de comandos y
payload; TTL idle3600s, absoluto43200s, touch300s, máximo5 sesiones y locking
acotado. Los writers de sesión siguen en Identity
[ValkeySessionStoreAdapter](../../modules/identity/adapters/ValkeySessionStoreAdapter.php),
[ProductiveValkeyClient](../../modules/identity/adapters/ProductiveValkeyClient.php)
y [SessionService](../../modules/identity/services/SessionService.php).
No es caché clínico ni fuente de perfiles/pacientes; no garantiza continuidad tras desastre.

### Seguridad y secretos

[MxMedSecurityRoleFactory](../../infra/aws/lib/constructs/security-role-factory.ts)
y [SecuritySecretContainer](../../infra/aws/lib/constructs/security-secret-container.ts)
separan roles app/execution/migration/jobs y referencias vacías de providers.
Task app recibe SES `SendEmail` restringido a la identidad contractual; no control-plane
SES ni claves estáticas. Stripe y AI se inyectan sólo según runtime capability.
Un contenedor de secreto vacío no acredita una credencial presente en `AWSCURRENT`.
Los aspectos [least privilege](../../infra/aws/lib/aspects/least-privilege-iam-aspect.ts),
[no plaintext secret](../../infra/aws/lib/aspects/no-plaintext-secret-aspect.ts),
[no public bucket](../../infra/aws/lib/aspects/no-public-bucket-aspect.ts),
[no public database](../../infra/aws/lib/aspects/no-public-database-aspect.ts)
y sus pares de dominio rechazan configuraciones inseguras en fuente/tests.

CloudTrail management y archivo cifrado no son el audit sink aplicativo de Platform.
Hay referencias separadas de audit/log protection y políticas `path-only-no-query`
para Stripe return; no copiar queries, tokens, cuerpos clínicos, cookies o secretos
en observabilidad ni en este mapa. Las claves físicas/ARN/cuentas/zonas y evidencias
privadas no se transcriben aunque algunas referencias históricas ya contengan valores.

### Edge, caché, DNS y webhooks

[edge-config](../../infra/aws/lib/config/edge-config.ts) declara rutas de Stripe return
y webhook; [edge-handoff](../../infra/aws/lib/constructs/edge-handoff.ts) ordena
outputs regionales → distribución → parámetros ARN en Storage/Security.
Ese handoff manual evita un ciclo de referencias cross-region. El header de origen
secreto tiene `NoEcho`, no se imprime. Certificados regional/viewer se suministran
externamente; DNS none no publica automáticamente una aplicación.

Dinámicos usan caché cero y conservan headers/cookies/query necesarios, incluyendo
`Stripe-Signature`; static/media omiten cookies/auth/query, con fingerprint gate
para inmutabilidad. WAF es métricas sin request sampling/logs. No inferir que un
CloudFront template expone PHP ni que una ruta declarada está publicada.
El estado fuente `MXMED_REAL_EDGE_RUNTIME_GATES` conserva banderas false, incluso
para readiness/Stripe return; son **gates de release**, no inventario actualizado
de todas las rutas de producto. Contrastar con el
[mapa Stripe](STRIPE_Y_SERVICIOS_EXTERNOS_MXMED.md) antes de declarar integración ausente.

## Empaquetado, CI y jobs — AWS-CI01 / AWS-APP01 / AWS-JOBS01

La receta [scripts/packaging/README.md](../../scripts/packaging/README.md) y
[assemble-application.mjs](../../scripts/packaging/assemble-application.mjs)
usan [runtime-files.json](../../scripts/packaging/runtime-files.json) como allowlist
explícita de archivos Git regulares. El output `.application-build/` es generado,
con inventory path/size/SHA256; no es otra fuente editable. No se copia vendor
local, uploads, secretos, pruebas ni docs.
[build-application.mjs](../../scripts/packaging/build-application.mjs) vuelve a
validar bytes antes de Docker; [build-inputs.json](../../scripts/packaging/build-inputs.json)
fija PHP/Composer por digest, phpredis6.3.0 y `linux/amd64`.
El [Dockerfile](../../infra/aws/runtime/app/Dockerfile) instala Composer `--no-dev`;
PHP8.5/Apache, www-data, root readonly y volúmenes efímeros acotados son el contrato.
CDK no hace build/pull/push ni escaneo de la imagen.

**Brechas verificables sin ejecutar:**

- Compute inyecta `DB_HOST/PORT/NAME`, `DB_USERNAME/PASSWORD`; el writer
  [api/_lib/db.php](../../api/_lib/db.php) requiere `MXMED_DB_HOST/PORT/NAME/USER/PASS`.
  La receta documenta que no hay mapping de entrada. Jobs sí inyecta los nombres
  `MXMED_DB_*`. No extrapolar el éxito del executor Jobs a la app web.
- MigrationTask termina con `migration command is not configured`; disponer de
  task/master secret no ejecuta DDL ni crea `mxmed_app`.
- [healthz](../../infra/aws/runtime/app/health/healthz.php) es liveness sin dependencias;
  [readyz](../../infra/aws/runtime/app/health/readyz.php) ahora verifica TLS+ACL+PING
  Valkey y parámetros estrictos, con errores genéricos503. **No comprueba MySQL**.
  El README AWS que lo describe como 503 incondicional quedó sucedido en ese punto.
- El manifiesto de runtime actual contiene **688 entradas**; no incluye
  `private-app.php`, `modules/identity/Operational/WorkflowFactory.php`,
  `modules/identity/ProfessionalSources/SourceProvisioningService.php`,
  `partials/identity/professional-sources.php` ni
  `assets/js/identity/professional-sources.js`. Es una comprobación de JSON/fuentes,
  sin ensamblar imagen. La evidencia MR11.3 de653 archivos prueba su baseline;
  un archivo nuevo no entra automáticamente ni esa evidencia acredita un paquete
  completo de la aplicación privada/ACT03 actuales. Conectar un release exige
  reconciliar el manifiesto dentro de una tarea autorizada.

CI: [MxMedGitHubOidcDeployment](../../infra/aws/lib/constructs/github-oidc-deployment.ts)
implementa trust por `aud/sub` exactos, ambiente protegido production y branch
staging, con permissions boundary. Está exportado y probado en
[security-iam.test.ts](../../infra/aws/test/security-iam.test.ts); no se instancia en
los stages actuales. **Cero archivos versionados `.github/`**: no hay pipeline de
deploy conectado acreditado por este repositorio. `npm` declara validación/síntesis
local y diff futuro; no autoriza deploy/bootstrap.

Jobs reutiliza digest app y llama
[submit-inactive-review-batches.php](../../modules/media/bin/submit-inactive-review-batches.php)
→ [MediaReviewBatchService](../../modules/media/services/MediaReviewBatchService.php)
→ transacción/batches Media → ready signal/audit aplicativo. El Scheduler rate5min
está DISABLED en fuente; no demuestra activación MR12. ECS task events van a logs
sanitizados; no hay scanner SQS/DLQ implementado por este stack.
Hay batch CLI real; nunca ejecutarlo para inspección con credenciales normales.

## Operación, costos y recuperación — AWS-OPS01 / AWS-COST01 / AWS-DR01

[operations-alarm-catalog](../../infra/aws/lib/constructs/operations-alarm-catalog.ts),
[operations-dashboard-contract](../../infra/aws/lib/constructs/operations-dashboard-contract.ts)
y [operations-runbook-catalog](../../infra/aws/lib/constructs/operations-runbook-catalog.ts)
conectan señal → severidad/runbook → topic cifrado → subscriber externo confirmado
manualmente. No se crean SMS/email/webhooks personales por elegir topics.
SLIs/SLOs en [operations-contract](../../infra/aws/lib/constructs/operations-contract.ts)
son objetivos internos no medidos, no SLA. Staging general usa ventana manual;
no tiene scheduler de apagado general ni remediación automática.

Cost requiere importes aprobados, owner opaco, frecuencia, tags/verificación,
FX fechado y gate de costo/ingreso en [launch-profiles](../../infra/aws/lib/config/launch-profiles.ts).
Dos budgets separados disparan 50/75/100/120% actual y90% forecast; no Budget Actions.
Monitor de anomalías service puede crearse/importarse; monitor por tag exige tags
activos. No se consultaron tarifas ni se transforma un ledger en cotización vigente.

Backup: [backup-plan-catalog](../../infra/aws/lib/constructs/backup-plan-catalog.ts)
separa PITR nativo RDS del snapshot diario35d y mensual365d según perfil;
S3 clínico/privado usa un plan continuous35d más reglas periódicas.
PublicMedia no entra en launch, Quarantine queda excluido; Audit pertenece a Security.
Vault Lock governance/compliance no son equivalentes ni se encienden implícitamente.
Cross-region exige región explícita/residencia; cross-account permanece gate separado.

[backup-dr-contract](../../infra/aws/lib/constructs/backup-dr-contract.ts) y
[backup-restore-validation-contract](../../infra/aws/lib/constructs/backup-restore-validation-contract.ts)
exigen restore aislado → schema/checksum/sentinels → validación → autorización de
cutover → cleanup/costos. Valkey se reconstruye vacío y requiere login; ECR desde
commit/digest/scan; secretos se rotan/reemiten, no se exportan como valores.
Los **22 runbooks** de
[backup-runbook-catalog](../../infra/aws/lib/constructs/backup-runbook-catalog.ts)
incluyen corrupción, pérdida, keys/vault, restore, failback y residuos. Sus RPO/RTO
son internos/no medidos. Manual-quarterly crea preparación sin schedule;
scheduled-monthly es fixture cerrada por gates. No hay prueba actual de restore,
recovery points disponibles, DR_READY ni failover/failback automático.

## C3 y correo: evidencia histórica separada del inventario actual

| Evidencia | Fecha, commit, entorno y alcance comprobable del registro | Clasificación |
|---|---|---|
| [MXMED_C3_FINAL_CLOSEOUT](../aws/MXMED_C3_FINAL_CLOSEOUT.md) | Publicado 2026-09-02 en `29b4e4a3dee3b9695d79400f089ecb298f8e671a`; fuente terminal declarada `945203a64bac5360dc2a5b49419c729e31e914bc`. Staging temporal: Network/Security/Session/Registry/Runner/Janitor y Budget directo; imagen por digest. | HISTORICAL_DEPLOYMENT_EVIDENCE. Fecha de publicación Git, no timestamp nuevo del test. |
| C3 test físico | Mismo cierre: una ejecución autorizada de [C3ValkeySessionStoreIntegrationTest](../../modules/identity/tests/C3ValkeySessionStoreIntegrationTest.php), TLS hostname/ACL/namespace/TTL/rotación/revocación/index/máximo5 tras20 creates/PING; exit0. | Evidencia física histórica, no prueba de fuente actual ni reapertura C3. |
| C3 teardown | Mismo cierre: cero runtime stacks/residuos accionables/billables, sin ECR/cluster activo/template bucket/Budget directo; control-plane retenido y KMS pendientes de eliminación aceptados en su momento. | HISTORICAL_TEARDOWN_EVIDENCE. No concluir "AWS vacío hoy". |
| Limitación C3 cerrada | Runner CFN role carecía de `ecs:DeregisterTaskDefinition`; task exacta se desregistró después y se aceptó cierre no bloqueante. | KNOWN_NON_BLOCKING_CLOSEOUT_LIMITATION histórica; no abrir hardening ni ejecutar otro C3. |
| [EMAIL_OTP_PROVIDER_CLOSEOUT](../aws/MXMED_EMAIL_OTP_PROVIDER_CLOSEOUT.md) | Publicado 2026-09-02 en `80acdd6d4a4b9a27fcad1e64342638607f498bf5`; fuente autoridad `88dbdb96bef0ff870d4af471c4a66540d2ac81c9`. SES V2 us-east-1, identity+EasyDKIM/DNS y un envío al simulador oficial. Rol probe eliminado. | HISTORICAL_DEPLOYMENT_EVIDENCE + transport proof. Recursos persistentes deliberados, no los residuos de C3. |
| Correo diferido | Cierre registra sandbox, sin solicitar production access; task role de producción todavía sin prueba física por Security/compute no desplegados. Numeric OTP diferido, link high-entropy one-use implementado. | No es una integración ausente ni aprobación actual de envío/deliverability productiva. |
| Packaging MR11.3 | [Receta](../../scripts/packaging/README.md), publicada `9bc0c5bfb9e62874fbd34f1ba6780719dc73590b` el2026-09-09: imagen local amd64, extensiones/bootstrap/web/manifest y concurrencia contra MySQL desechable. | OFFLINE_TEST_EVIDENCE / QA local histórica; no AWS deploy ni DB Director. |

**C3 seguridad operativa existente:**
[c3-runtime-contract.sh](../../scripts/aws/c3-runtime-contract.sh),
[deploy](../../scripts/aws/c3-ephemeral-deploy.sh),
[test](../../scripts/aws/c3-ephemeral-test.sh) y
[teardown](../../scripts/aws/c3-ephemeral-teardown.sh) exigen manifiesto/run ID,
source HEAD, autorización fresh single-use, roles/per-stack boundaries exactos,
región no productiva, image digest sellado y Budget directo verificado antes de Runner.
Janitor tiene fail-safe+22h y autoeliminación+24h; captura13 recursos retenidos según
contrato, estados/red/sesiones/registry y costo residual; no basta con borrar stacks.
Las políticas revisables están en [policies/c3](../../infra/aws/policies/c3/).
Son herramientas con efectos reales. No ejecutar ni reutilizar antiguas aprobaciones.

**Correo productor/consumidor:** Identity
[SesIdentityNotificationAdapter](../../modules/identity/adapters/SesIdentityNotificationAdapter.php)
→ SDK PHP SesV2 `SendEmail` → SES; credenciales por cadena SDK/task role.
Operadores tiene [SesOperatorInvitationTransport](../../modules/identity/operators/SesOperatorInvitationTransport.php)
con su gate de composición. Normal-local utiliza sink local privado y no compone SES.
El cierre técnico SES no habilita invitaciones, cohortes, SMS ni enrollment reales.

## Entorno normal-local y entornos aislados — AWS-LOCAL01 / AWS-ACT01

La selección concreta pertenece a
[IdentityHttpCompositionSelector](../../modules/identity/http/IdentityHttpCompositionSelector.php):
`APP_ENV` prevalece sobre `MXMED_ENVIRONMENT`; normal-local exige flag exacto1 y
local, preview exige flag propio1, staging/production usa composición productive.
Combinaciones cruzadas/desconocidas fallan cerrado. No hay selección por branch.

| Entorno / autoridad | Topología y estado verificable | Frontera |
|---|---|---|
| Normal-local | [normal-local-identity.py](../../scripts/normal-local-identity.py) prepara/configura user-space y lanza PHP8091/8092, Valkey6385 y TLS8443. DB permitida exacta `mxmed`; loopback; namespace `mxmed:local:session:`. | Fuente inspeccionada; continuidad activa sólo según evidencia ACT04/estado, no nuevos probes HTTP/sesión en este mapa. No ejecutar start/replace/stop. |
| Browser | `https://localhost:8443/private-app.php`; [proxy](../../scripts/normal-local-https-proxy.py) TLS≥1.2 →8091; excepciones viewer/document→8092. Ambas instancias PHP usan router original. | `8092` no representa un IdP externo independiente. Cookies/Origin/CSRF preservados, Host estricto, forwarding cliente filtrado, sin log de URLs. |
| Custodia local | Config/procesos bajo `~/Library/Application Support/MXMED/local-identity-runtime/`, CA/leaf bajo `local-identity-tls/`; sink en `/tmp/` externo privado explícito. | Sólo rutas contractuales; no se leyó/copió configuración privada ni llaves. Preparar no instala trust macOS. Teléfono físico no alcanza localhost de Mac automáticamente. |
| QA persistente | QA05, OP02 y UI03 conservan composiciones/prefix/DB propios; consultar contratos en [router](../README.md). | No resetear fixtures ni extrapolar owner/grants sintéticos a Director. |
| Ensayos desechables | [gov03b-disposable-qa.py](../../scripts/gov03b-disposable-qa.py), [operator-deployment.php](../../scripts/operator-deployment.php) y runners específicos generan sus targets aislados y cleanup controlado. | Scripts mutantes, no ejecutados aquí. Un runner no es runtime normal y no autoriza migraciones. |
| Productive | [ProductiveIdentityHttpConfiguration](../../modules/identity/http/ProductiveIdentityHttpConfiguration.php) → composición productive → Valkey TLS/SES. | Fuente presente; estado AWS remoto no verificado. No reemplazar por config local o fake session. |
| Workflows ACT02/03 | [OperationalRuntime](../../modules/identity/Operational/OperationalRuntime.php) → instalación privada → [ActivationGuard](../../modules/identity/Operational/ActivationGuard.php) → [WorkflowFactory](../../modules/identity/Operational/WorkflowFactory.php). | optional-composition, normal-local inactivo según cierre; checks de aprobación/schema/hash/principal/llaves/capabilities. Preparado no significa aplicado. |

El launcher Valkey local desactiva snapshots/AOF: conserva estado entre requests PHP,
no tras restart. [NormalLocalIdentityConfiguration](../../modules/identity/http/NormalLocalIdentityConfiguration.php)
exige secrets propios/puerto no reservado/origen exacto y prohíbe preview/productive.
`MXMED_GOVERNANCE_PROXY_KEY` sólo firma transporte cuando está provisionada externamente;
el proxy no genera una ni inventa cookies. No se modifica exposición LAN/TLS.

Migraciones ACT02/03 y prerrequisitos reales se encuentran en
[DeploymentPackage](../../modules/identity/Operational/DeploymentPackage.php),
[ACT02](../identity/ACT02-integrated-public-workflows.md) y
[ACT03](../identity/ACT03-governed-professional-sources.md).
No son CloudFormation ni forman parte del PITR RDS. Esta documentación no aplica DDL,
concede staff/MFA ni convierte acreditación en ownership o firma clínica.

## Ramas, worktrees y reconciliación temporal

Inspección Git sólo lectura: **224 ramas locales**, **47 worktrees registrados**,
25 rutas existentes y22 registros prunable. No se cambiaron ni limpiaron.
Los tips de13 ramas `architecture/mxmed-aws-*` y11 `feature/mxmed-aws-*`
son ancestros del baseline: sus contratos/implementaciones están en la historia actual.
También lo son `feature/mxmed-dedicated-registry-foundation` y
`release/mxmed-runtime-operationalization-v1-staging`, tip
`35c4884fd2e068781e2e17d312139b5784f1e420`.
La comprobación fue `merge-base --is-ancestor`, no inferencia por nombres.

Excepción histórica relevante: `audit/mxmed-pg03-runtime-cutover-readiness-v2`,
`f1e8d065ccc6778c34ce8bda621889d79a610c02`, no es ancestro; su revisión r2
`1e3057f2a12afed9da7e6ce95cd20ae81d645c1f` sí lo es y conserva el
[informe](../MXMED_AUDITORIA_V2_PG03_RUNTIME_CUTOVER_READINESS.md).
El diff de la rama r1 se inspeccionó por metadatos; no se incorpora su texto como
diagnóstico actual ni se propone merge. Su estado de julio precede las autoridades
Identity/clinical posteriores. El checkout hermano `design/physician-dg04b-public-identity-v1`
conserva su HEAD independiente y permanece intacto.

| Afirmación histórica | Reconciliación actual de fuente/evidencia |
|---|---|
| README AWS: "Nada está desplegado" | Describe foundation inicial; C3/SES tienen cierres físicos posteriores. Hoy remoto no verificado. |
| README AWS: "Jobs y Email continúan vacíos" | Source SES+DKIM actualizado septiembre2; batch Fargate/Scheduler disabled actualizado septiembre9. |
| README AWS: `/readyz`503 fijo | Ready actual hace Valkey TLS/auth/PING; falta DB y gate de release sigue cerrado. |
| README AWS: "no DNS creado" | Sin cutover Edge acreditado; cierre SES documenta delegación/DKIM físicos. Son dominios de operación distintos. |
| Guardrails/comentarios Network: "no database/cache resource exists in this phase" | Comentarios del origen de Network; DataStack y SessionStack actuales sí contienen recursos IaC. |
| Staging no tiene automatic shutdown | Contrato general release-window; C3 añade janitor dedicado puntual. No hay contradicción si se conserva scope. |
| ACT03 mencionaba tres tests fallidos | [ACT04-RECON02](../ESTADO_ACTUAL_MXMED.md#evidencia-reciente-y-reconciliación-de-tests) los corrigió. No son fallos actuales por repetición histórica. |

## Recuperación dirigida, verificación pendiente y mantenimiento

Pregunta "¿qué AWS existe?": comenzar por inventario de stacks, luego selectors y
evidencia histórica; no interpretar nombres de archivos como inventario de nube.
Pregunta "¿cómo desplegar?": **no hay siguiente ejecución autorizada**. Recuperar
contrato de ambiente, guard de aplicación y brecha exacta antes de una decisión.
Pregunta "¿qué ocurrió con C3/SES?": usar cierres con commit/fecha, sin reabrirlos.

Test suites disponibles: [infra/aws/test](../../infra/aws/test/) incluye matrices de
stages/env/compute/edge/session/data/storage/security/operations/backup y C3;
los aspectos validan configuración y el README registra su aceptación offline de fase.
**No se ejecutaron ni se les atribuye PASS actual**. Los tests físicos C3 y packaging
se identificaron separadamente. No se sintetizaron templates para contar recursos.

Pendientes explícitos: inventario cloud/costos actual; configuración/secrets/versiones
físicas y región DR; app image/manifiesto frente a código nuevo; mapping DB web y
migrator; readiness end-to-end; certificados/pricing/DNS/cutover; restore y RPO/RTO
medidos; subscribers/guardias/budget aprobados; job Media real; production SES access
y task-role proof cuando compute exista; autorizaciones de workflows/cohortes/staff/MFA/SMS.
No pedir credenciales más amplias ni consultar proveedores para completar este mapa.

Mantener sólo entradas afectadas cuando haya un cambio **autorizado**, con baseline,
ambiente, evidencia y sus cinco ejes. No copiar logs/account IDs/ARNs/llaves a Git ni
crear documentación de activación por inferencia. El próximo paso producto/piloto es
**NONE_PENDING_DIRECTOR_REVIEW**. Volver al [router](../README.md) y al
[estado compacto](../ESTADO_ACTUAL_MXMED.md) para iniciar una tarea distinta.
