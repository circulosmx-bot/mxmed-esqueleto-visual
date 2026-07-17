# MXMed AWS CDK foundation

## Propósito y estado

Este proyecto implementa la foundation local de Infrastructure as Code, la red V1 y las
foundations de seguridad, datos, almacenamiento, sesiones, Compute y Edge de México Médico. Traduce PP-Decisiones 245
(`MXMED_AWS_ECS_FARGATE_REFERENCE_ARCHITECTURE_V1`), PP-Decisiones 249
(`MXMED_AWS_CDK_FOUNDATION_CONTRACT_V1`), PP-Decisiones 251
(`MXMED_AWS_NETWORK_READINESS_CONTRACT_V1`) y PP-Decisiones 253
(`MXMED_AWS_SECURITY_READINESS_CONTRACT_V1`), además de PP-Decisiones 255
(`MXMED_AWS_DATA_READINESS_CONTRACT_V1`) y PP-Decisiones 257
(`MXMED_AWS_STORAGE_FOUNDATION_CONTRACT_V1`) y PP-Decisiones 260
(`MXMED_AWS_SESSION_FOUNDATION_CONTRACT_V1`), además de PP-Decisiones 263
(`MXMED_AWS_COST_AWARE_LAUNCH_PROFILES_CONTRACT_V1`) y PP-Decisiones 265
(`MXMED_AWS_COMPUTE_FOUNDATION_IMPLEMENTATION_V1`), PP-Decisiones 266
(`MXMED_AWS_EDGE_FOUNDATION_CONTRACT_V1`) y PP-Decisiones 267
(`MXMED_AWS_EDGE_FOUNDATION_IMPLEMENTATION_V1`), PP-Decisiones 268
(`MXMED_AWS_OPERATIONS_FOUNDATION_CONTRACT_V1`) y PP-Decisiones 269
(`MXMED_AWS_OPERATIONS_FOUNDATION_IMPLEMENTATION_V1`), PP-Decisiones 270
(`MXMED_AWS_BACKUP_DR_FOUNDATION_CONTRACT_V1`) y PP-Decisiones 271
(`MXMED_AWS_BACKUP_DR_FOUNDATION_IMPLEMENTATION_V1`), a AWS CDK v2 con TypeScript.

`MxMedNetworkStack`, `MxMedSecurityStack`, `MxMedDataStack`, `MxMedStorageStack` y
`MxMedSessionStack` contienen recursos CloudFormation sintetizables offline. `MxMedComputeStack`
implementa ECR, ECS tasks y el servicio Fargate de forma condicional. Edge implementa offline
CloudFront/OAC/WAF y, cuando corresponde, ALB/target group/listener; los scripts generales lo
mantienen deshabilitado. Operations implementa offline controles de costo, alarmas, dashboards y
topics condicionales. Backup/DR implementa templates offline condicionales; Jobs y Email continúan
vacíos. Nada está desplegado: bootstrap, diff y deploy permanecen pendientes y están prohibidos
en esta etapa.

## Arquitectura contractual

- Workloads `staging` y `production`: región `mx-central-1`.
- Correo de cada ambiente: stage separado en `us-east-1` para SES.
- Compute implementado offline: ECS Fargate Linux X86_64, activado sólo por contexto explícito.
- Edge offline: CloudFront/OAC/WAF global en `us-east-1` y ALB restringido regional en
  `mx-central-1`; no existe cutover real.
- Operations offline: Cost Management en `us-east-1`, observabilidad regional en `mx-central-1`
  y observabilidad Global Edge en `us-east-1`, sólo mediante modos explícitos.
- Datos: template RDS MySQL 8.4.9, cuatro buckets S3 privados y sesiones en ElastiCache Valkey 8.2.
- CloudFormation, sintetizado por CDK, será la fuente de verdad.

`MxMedEnvironmentStage` contiene ocho stacks base y agrega condicionalmente
`MxMedRegionalEdgeFoundationStack`, `MxMedRegionalOperationsStack`,
`MxMedRegionalBackupStack`, `MxMedDrCopyStack` y `MxMedRestoreValidationStack`;
`MxMedGlobalEdgeStage` existe sólo en modos Edge no disabled y
`MxMedGlobalOperationsStage` compone Cost Management con los stacks globales aplicables.
El stack Edge heredado y Jobs permanecen contractuales/vacíos. `MxMedEmailStage` contiene
únicamente Email, continúa vacío y no crea referencias CloudFormation cross-region.

## Prerrequisitos

- nvm `0.40.5`.
- Node.js `22.22.0` (`.nvmrc`).
- npm `10.9.4`.
- Sin instalación global de CDK, TypeScript o ts-node.

Desde `infra/aws/`:

```sh
nvm use
npm ci
```

`package-lock.json` es obligatorio. `.npmrc` exige el engine correcto, dependencias exactas y
lockfile. No se admiten `--force`, `--legacy-peer-deps` ni versiones flotantes.

## Comandos

| Comando                                                 | Propósito                                            |
| ------------------------------------------------------- | ---------------------------------------------------- |
| `npm run build`                                         | Compila TypeScript a `dist/`.                        |
| `npm run typecheck`                                     | Ejecuta el compilador sin emitir archivos.           |
| `npm run lint`                                          | Valida TypeScript con ESLint local.                  |
| `npm run format`                                        | Formatea exclusivamente archivos bajo `infra/aws/`.  |
| `npm run format:check`                                  | Comprueba formato sin modificar.                     |
| `npm run test`                                          | Ejecuta unit tests y assertions finas.               |
| `npm run test:watch`                                    | Ejecuta Jest en modo local interactivo.              |
| `npm run synth:staging`                                 | Sintetiza staging offline en `cdk.out/staging`.      |
| `npm run synth:production`                              | Sintetiza production lean offline.                   |
| `npm run synth:production:launch-lean`                  | Alias explícito para production lean.                |
| `npm run synth:production:standard`                     | Sintetiza production standard offline.               |
| `npm run synth:production:scale-ready`                  | Sintetiza production scale-ready offline.            |
| `npm run synth:production:launch-lean:compute-registry` | Sintetiza ECR registry-first.                        |
| `npm run synth:production:launch-lean:compute-tasks`    | Sintetiza ECR, cluster y tasks sin servicio.         |
| `npm run synth:production:launch-lean:compute-service`  | Sintetiza el servicio lean directory-core.           |
| `npm run synth:production:standard:compute-service`     | Sintetiza el servicio standard directory-core.       |
| `npm run synth:production:scale-ready:compute-service`  | Sintetiza el servicio scale-ready directory-core.    |
| `npm run synth:staging:release-window:compute-service`  | Sintetiza el servicio staging release-window.        |
| `npm run synth:production:launch-lean:edge-media`       | Sintetiza sólo el Edge global de media.              |
| `npm run synth:production:launch-lean:edge-origin`      | Sintetiza Edge regional/global lean sin tráfico.     |
| `npm run synth:production:standard:edge-origin`         | Sintetiza Edge regional/global standard sin tráfico. |
| `npm run synth:staging:release-window:edge-origin`      | Sintetiza Edge staging sin tráfico.                  |
| `npm run synth:production:operations:cost-controls`     | Sintetiza sólo controles de costo Operations.        |
| `npm run synth:production:launch-lean:operations`       | Sintetiza Operations lean con gates reales.          |
| `npm run synth:production:standard:operations`          | Sintetiza el catálogo Operations standard.           |
| `npm run synth:production:scale-ready:operations`       | Sintetiza Operations sobre perfil scale-ready.       |
| `npm run synth:staging:release-window:operations`       | Sintetiza Operations lean para la ventana staging.   |
| `npm run synth:production:launch-lean:backup-regional`  | Sintetiza Backup regional lean offline.              |
| `npm run synth:production:standard:backup-regional`     | Sintetiza Backup regional standard offline.          |
| `npm run synth:production:scale-ready:backup-regional`  | Sintetiza Backup regional scale-ready offline.       |
| `npm run synth:staging:release-window:backup-regional`  | Sintetiza Backup regional staging offline.           |
| `npm run diff:staging`                                  | Contrato futuro de diff; no ejecutar todavía.        |
| `npm run diff:production`                               | Contrato futuro de diff; no ejecutar todavía.        |
| `npm run validate`                                      | Ejecuta typecheck, lint, formato y tests.            |
| `npm run clean`                                         | Elimina únicamente outputs locales generados.        |

No existe script de deploy o bootstrap automático.

## Ambientes y configuración

El entrypoint `bin/mxmed.ts` exige contextos explícitos e independientes:

```text
environment=staging|production
deploymentProfile=launch-lean-v1|production-standard-v1|scale-ready-v1
computeActivationMode=disabled-v1|registry-only-v1|tasks-ready-v1|service-enabled-v1
runtimeCapabilityProfile=directory-core-v1|paid-profile-v1|clinical-v1|professional-ai-v1
edgeActivationMode=disabled-v1|media-cdn-ready-v1|application-origin-ready-v1|public-traffic-enabled-v1
edgePricingProfile=flat-rate-free-v1|flat-rate-pro-v1|pay-as-you-go-approved-v1
edgeDnsMode=none-v1|external-dns-v1|route53-managed-v1
edgeCutoverState=blocked-known-gaps-v1|verified-for-cutover-v1
staticAssetCacheState=disabled-until-fingerprinted-v1|immutable-fingerprinted-v1
operationsActivationMode=disabled-v1|cost-controls-ready-v1|launch-lean-observability-ready-v1|production-observability-ready-v1
operationsNotificationMode=none-v1|topics-only-v1|external-subscribers-confirmed-v1
operationsLogProtectionProfile=source-sanitized-only-v1|targeted-data-protection-v1
operationsRuntimeGateState=blocked-known-runtime-gaps-v1|operational-readiness-integrated-v1
clinicalLogSanitizationState=blocked-legacy-agenda-logs-v1|source-sanitization-verified-v1
costAllocationTagState=inactive-v1|active-and-verified-v1
costAnomalyMonitorOwnershipMode=create-service-monitor-v1|import-existing-service-monitor-v1
costTagAnomalyMonitorMode=disabled-until-tags-active-v1|enabled-v1
backupDrActivationMode=disabled-v1|regional-recovery-ready-v1|cross-region-copy-ready-v1|restore-validation-ready-v1
backupVaultLockMode=unlocked-v1|governance-v1|compliance-approved-v1
drRegionState=not-selected-v1|selected-and-verified-v1
crossAccountBackupMode=disabled-v1|organization-vault-approved-v1
restoreTestingMode=disabled-v1|manual-quarterly-v1|scheduled-monthly-v1
backupDataResidencyState=pending-review-v1|approved-v1
backupValidationState=not-tested-v1|restore-job-completed-v1|application-validation-passed-v1
backupSelectionMode=explicit-resource-arns-v1|verified-tags-v1
```

Los scripts de synth proporcionan los selectores aplicables. Los cuatro scripts generales fijan
`disabled-v1`; registry ignora capacidad y tasks/service exigen una capacidad explícita. No hay fallback por rama, cuenta, hostname,
fecha o consumo. Staging permite únicamente `launch-lean-v1` y agrega
`stagingOperatingMode=release-window-v1`; production admite los tres perfiles. Una combinación
desconocida o incompleta falla antes de crear stages. La configuración tipada vive en
`lib/config/` y valida:

- región primaria y región de correo;
- CIDR VPC RFC1918 `/16` exacto por ambiente;
- masks de los cuatro tiers y exactamente dos AZ;
- perfiles de NAT y compute, los 21 campos cerrados de base de datos, los 25 campos cerrados de
  Storage y los 25 campos cerrados de Session;
- perfil de interface endpoints y retención de VPC Flow Logs;
- perfil de seguridad, ventanas KMS y recuperación operativa de secretos;
- retención de CloudTrail y archivo de auditoría;
- activación obligatoria de rotación KMS y management trail;
- data events de Storage desactivados hasta que Operations implemente selectors por bucket;
- retenciones y protecciones por ambiente;
- WAF y logging CloudFront seguro;
- tags obligatorios;
- política Stripe return `path-only-no-query`;
- ausencia de campos sensibles y valores con apariencia de credencial.

### Perfiles de lanzamiento y selección

`lib/config/launch-profiles.ts` es el único punto de definición de capacidad, ledger y gates. Los
stacks reciben el `MxMedEnvironmentConfig` ya resuelto; no duplican valores por ambiente.

| Combinación                           | Network                                                 | Compute contratado                       | RDS                                                             | Session                          |
| ------------------------------------- | ------------------------------------------------------- | ---------------------------------------- | --------------------------------------------------------------- | -------------------------------- |
| staging / `launch-lean-v1`            | 1 NAT, S3 Gateway, 0 endpoints de interfaz              | desired/min/max `1/1/1`, 0.5 vCPU, 1 GiB | t4g.medium, Single-AZ, 40/200 GiB, 7 días                       | 1 micro, sin réplica/HA/snapshot |
| production / `launch-lean-v1`         | 1 NAT, S3 Gateway, 0 endpoints de interfaz              | `1/1/2`, 0.5 vCPU, 1 GiB                 | t4g.medium, Single-AZ, 40/200 GiB, 35 días, deletion protection | 1 micro, sin réplica/HA/snapshot |
| production / `production-standard-v1` | 2 NAT, S3 Gateway; endpoints siguen apagados hasta gate | `2/2/6`, 1 vCPU, 2 GiB                   | m6g.large, Multi-AZ, 100/1000 GiB, 35 días                      | 2 micro, Multi-AZ/failover       |
| production / `scale-ready-v1`         | 2 NAT, endpoints `measured` con baseline 0              | `2/2/6`, 1 vCPU, 2 GiB                   | m6g.large, Multi-AZ, 100/1000 GiB, 35 días                      | 2 medium, Multi-AZ/failover      |

`launch-lean-v1` es el default explícito de los scripts de staging y production, no un fallback
implícito. Reduce capacidad, pero no TLS, KMS, Secrets Manager, IAM mínimo, CloudTrail, backups,
PITR, deletion protection production, final snapshots, Block Public Access, versioning ni
retención. Tampoco equivale a una producción publicada o a un SLA de alta disponibilidad.

El ledger versionado describe drivers `fixed-idle`, `usage-based` y `storage-based`, cantidades y
capacidades diferidas; no guarda tarifas que envejezcan ni finge una cotización actual. Antes del
primer deploy permanecen obligatorios y sin defaults ficticios el presupuesto aprobado, FX de
planificación con fecha, umbral de anomalía, límite costo/ingreso, owner, destinatarios y la
aprobación de Cost Readiness Review. `evaluatePreGoLiveCostGate` devuelve `allowed=false` mientras
falte cualquiera.

Promover a standard o habilitar endpoints requiere métricas, ledger actualizado, revisión de
break-even/resiliencia, PR, tests, synth, diff y aprobación. `scale-ready-v1` no habilita por su
nombre RDS Proxy, read replicas, cross-region, workers, scanner ni endpoints: cada capacidad
mantiene su gate independiente. Para agregar un perfil futuro se amplían los tipos, la definición
central, la matriz de compatibilidad y sus tests; no se añaden condicionales duplicados en stacks.

## Compute foundation implementada en templates

`MxMedComputeStack` implementa cuatro modos ortogonales al deployment profile:

| Modo                 | Inventario Compute                                                                                                                               |
| -------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------ |
| `disabled-v1`        | stack contractual con cero recursos, usado por todos los scripts generales                                                                       |
| `registry-only-v1`   | un repositorio ECR KMS, inmutable, scan-on-push y retenido                                                                                       |
| `tasks-ready-v1`     | ECR, cluster, dos log groups, `ApplicationTaskDefinition`, `MigrationTaskDefinition`, secreto DB de aplicación y parámetro de digest obligatorio |
| `service-enabled-v1` | lo anterior más un Fargate service privado y autoscaling CPU/memoria                                                                             |

Las capacidades runtime son `directory-core-v1`, `paid-profile-v1`, `clinical-v1` y
`professional-ai-v1`. Stripe sólo se inyecta desde paid; los buckets privados y clínicos sólo
desde clinical; `AI_API_KEY` sólo desde professional-ai. Ninguna capacidad se deriva de un usuario,
plan comercial, rama, dominio o account ID.

El contenedor app usa PHP 8.5 con Apache 2.4/mod_php/mod_rewrite sobre Bookworm X86_64, puerto 8080,
root filesystem read-only, usuario `www-data`, capabilities `ALL` eliminadas y volúmenes efímeros
únicamente para `/tmp`, `/var/run/apache2` y `/var/lock/apache2`. La imagen se forma exclusivamente
como URI ECR + `@` + `ApplicationImageDigest`; el parámetro no tiene default y no existen assets o
builds durante synth.

`/healthz` devuelve 200 sin dependencias. `/readyz` devuelve 503 con
`readiness_not_integrated` hasta una microfase posterior. MigrationTask usa la misma imagen y queda
fail-closed con `migration command is not configured`; no se ejecutó SQL y un despliegue operativo
no puede habilitar service antes de integrar y ejecutar un migrator idempotente.

El primer despliegue futuro autorizado debe respetar este orden: disabled → registry-only → push
externo por tag inmutable → obtención del digest → tasks-ready → MigrationTask → verificación de
`mxmed_app` y secretos `AWSCURRENT` → service-enabled → healthz → Edge → readyz → tráfico. Los
recursos Edge existen sólo en templates opt-in; Route 53 y tráfico siguen ausentes de los perfiles
reales.

`domainAlias` permanece omitido hasta una decisión empresarial. No hay cuentas, dominios, ARNs,
IPs o nombres físicos globales versionados. Si `CDK_DEFAULT_ACCOUNT` existe, la app lo transmite
sin imprimirlo; si no existe, synth continúa offline y sin cuenta explícita.

## Edge foundation implementada offline

Los cuatro modos son explícitos y ortogonales al ambiente: `disabled-v1` crea cero recursos Edge;
`media-cdn-ready-v1` crea sólo el stack global; `application-origin-ready-v1` crea los stacks
regional y global con la distribución deshabilitada; `public-traffic-enabled-v1` está implementado
para una fixture verificada, pero la configuración real lo rechaza por runtime gates abiertos. No
existe script de tráfico público.

`MxMedRegionalEdgeFoundationStack` pertenece a `mx-central-1`. Reutiliza
`AlbIngressSecurityGroup`, agrega un único ingress independiente TCP 443 desde el parámetro
`CloudFrontOriginFacingPrefixListId`, y crea ALB IPv4, listener HTTPS, default 403 y target group IP
HTTP 8080 con health check `/readyz`. Host y header secreto deben coincidir para hacer forward. El
nombre/valor del header son parámetros `NoEcho`, sin default ni output. Compute recibe el target
group opcional y adjunta su propio service; por eso la dependencia es Compute → Regional Edge y no
hay ciclo.

`MxMedGlobalEdgeStack` pertenece a `us-east-1` y sólo consume parámetros: no usa exports ni
lookups cross-region. Crea CloudFront IPv4-only, OAC S3 REST SigV4, policies de cache/request/
headers y WAF CLOUDFRONT de exactamente cinco reglas: reputación IP, Common, SQLi, rate sensible
100/300 e IP general 1200/300. Sampled requests y logs de CloudFront, WAF y ALB están deshabilitados.
El webhook real `/api/subscriptions/index.php/webhooks/stripe` queda fuera de los rate limits; no
se crea `/webhooks/stripe` ni la ruta todavía ausente `/subscriptions/stripe-return`.

El default dinámico y `/assets/*` conservan TTL cero mientras no exista fingerprint completo.
`/media/*` usa 86400/31536000/31536000, sin cookies, query ni Authorization. Sólo
`PublicMediaBucket` recibe `s3:GetObject` para el prefix `media/*`; su policy existente y
`ApplicationDataKey` limitan el principal CloudFront mediante el parámetro exacto
`PublicMediaCloudFrontDistributionArn`. PrivateDocuments, ClinicalRecords, Quarantine y Audit no
son origins públicos.

El handoff futuro, todavía no ejecutado, es: desplegar Regional; capturar outputs no sensibles;
desplegar Global con parámetros; capturar distribution ARN; actualizar los parámetros de
Security/Storage; verificar OAC; asociar manualmente el plan CloudFront aprobado; mantener DNS sin
cambios. Free/Pro/pay-as-you-go son perfiles tipados, no recursos CloudFormation. La elegibilidad y
la asociación del plan deben verificarse manualmente en Billing; no se asumen por Free Tier.

Los diez gates reales permanecen: `/readyz` no integrado (503), Stripe return ausente, webhook
confirmado, assets sin fingerprint completo, dominio/certificados sin aprobar o emitir, pricing
sin verificar, presupuesto sin aprobar y DNS sin cutover. En consecuencia:

```text
public_traffic_status=BLOCKED_BY_RUNTIME_GATES
```

No se emitieron certificados, no se creó DNS, no se llamó una cuenta AWS y no se desplegó ningún
recurso.

## Backup/DR foundation implementada offline

`MXMED_AWS_BACKUP_DR_FOUNDATION_IMPLEMENTATION_V1` materializa PP270 sin consultar una cuenta ni
iniciar jobs. La estrategia cerrada es `backup-and-restore-v1`. Los synth generales conservan
`backupDrActivationMode=disabled-v1` y crean cero stacks o recursos Backup/DR; los cuatro scripts
regionales usan activación explícita y reutilizan los topics cifrados de Operations.

En regional mode, DataStack conserva ownership del PITR nativo RDS a 35 días y AWS Backup añade
únicamente snapshots periódicos: diario a las 03:00 UTC, 35 días, y mensual/365 días en standard o
scale-ready. AWS Backup continuous para RDS permanece prohibido. ClinicalRecords y
PrivateDocuments conservan versioning, KMS, Block Public Access y RETAIN; al activar Backup se
habilita EventBridge y un único plan S3 usa continuous/35 días más reglas periódicas del perfil en
el mismo `RegionalRecoveryVault`. PublicMedia se excluye en launch, Quarantine siempre se excluye y
Audit conserva ownership en Security.

El vault regional reutiliza la `BackupKey` de Security, usa Vault Lock `governance-v1` con
retención mínima 1 y máxima 365, sin `ChangeableForDays`. Compliance no está activa. El
`BackupServiceRole` y el `RestoreValidationRole` están separados, confían sólo en
`backup.amazonaws.com`, no usan `AdministratorAccess`/`AWSBackupFullAccess`, no leen secretos y no
borran fuentes. Los fallos de backup/copy/restore se enrutan de forma sanitizada al topic crítico
regional; EventBridge sigue siendo best-effort.

No hay región DR real seleccionada ni script cross-region productivo. La fixture exige región
explícita, residencia aprobada y parámetros sin default; crea key/vault destino y añade copy
actions sólo a reglas periódicas, sin Exports cross-region y con cross-account deshabilitado.
Valkey se recupera vacío con reautenticación; ECR se reconstruye desde el commit publicado y
digest verificado; los secretos se rotan o reemiten, nunca se respaldan como valores.

`manual-quarterly-v1` refina el contrato: crea aislamiento, roles, parámetros de presupuesto y
cleanup, pero ningún schedule automático. `scheduled-monthly-v1` existe sólo como fixture cerrada
por residencia, presupuesto, cleanup, sentinels y workflow de validación; usa SG sin ingress ni
egress, DB temporal no pública y bucket temporal privado/KMS/RETAIN. Un restore `COMPLETED` no
equivale a validación. La máquina de estados exige restore, schema/checksum/sentinels, cleanup,
runbook, owners, costos, monitoreo y evidencia antes de `dr-ready-v1`.

El catálogo contiene 22 runbooks y objetivos RPO/RTO internos no medidos; no son SLA ni evidencia
de restore. El contrato de costo conserva quantities/rates desconocidos como `null`, no consulta
precios y excluye air-gapped vault, Audit Manager, report plans, indexing y cross-account en
launch. Automatic failover/failback permanecen `false`. Recovery points reales, restore probado,
región DR, cumplimiento legal y `DR_READY` no se afirman. Recursos AWS desplegados: cero.

## Operations foundation implementada offline

`MXMED_AWS_OPERATIONS_FOUNDATION_IMPLEMENTATION_V1` implementa PP268 sin consultar AWS y sin
desplegar recursos. `operationsActivationMode` controla cuatro inventarios cerrados:

- `disabled-v1`: cero stacks o recursos Operations; es el valor de todos los synth generales;
- `cost-controls-ready-v1`: sólo `MxMedCostManagementStack` en `us-east-1`;
- `launch-lean-observability-ready-v1`: Cost Management, Operations regional y, cuando Global
  Edge existe, Operations global;
- `production-observability-ready-v1`: el mismo topology con catálogo standard ampliado.

`none-v1` es el único notification mode válido en disabled. Los modos `topics-only-v1` y
`external-subscribers-confirmed-v1` crean los mismos topics cifrados, pero nunca crean email, SMS,
webhook ni otra subscription personal. El segundo añade un parameter/rule de verificación y deja
la administración del subscriber en un runbook externo con evidencia privada.

Cost Management usa una KMS key global retenida, `CostAlertsTopic`, dos budgets mensuales
parametrizados por separado para `CostScope=mxmed-production` y `CostScope=mxmed-staging`, y cinco
notificaciones SNS: 50% actual, 75% actual, 90% forecast, 100% actual y 120% actual. Los importes,
threshold absoluto de anomalía, owner opaco, cadencia y gates de verificación son parameters sin
defaults monetarios. No existen Budget Actions. Cost Anomaly Detection puede crear el monitor
DIMENSIONAL de servicio o importar su ARN por parameter; la subscription es `IMMEDIATE`, usa
`ThresholdExpression` absoluto y SNS. El monitor CUSTOM por `CostScope` sólo existe en la fixture
`active-and-verified-v1`/`enabled-v1`.

Regional Operations reutiliza `AuditKey` para `RegionalCriticalTopic` y `RegionalWarningTopic`,
sin nueva key ni subscribers, y crea un dashboard de máximo ocho widgets. Launch-lean tiene un
catálogo conceptual máximo de once alarmas ECS, RDS, Valkey, ALB y CloudFront; las alarmas ALB se
omiten mientras `operationsRuntimeGateState=blocked-known-runtime-gaps-v1`. Global Operations
reutiliza la key de costo, crea `GlobalEdgeAlertsTopic` y un dashboard de máximo cinco widgets en
`us-east-1`; las alarmas CloudFront se omiten mientras el tráfico público y runtime sigan
bloqueados. Standard agrega memoria/latencias/cola/storage RDS, CPU/conexiones/lag Valkey, p95 ALB,
error total CloudFront y spikes WAF. No habilita high resolution, CloudFront additional metrics,
X-Ray, Synthetics, RUM, Application Signals ni remediación automática.

`MXMed/Application` queda sólo como contrato para ocho métricas agregadas y las dimensiones
`Environment`, `Component`, `Result` y `RuntimeCapabilityProfile`. La emisión real continúa en
`false`, por lo que no existen alarmas funcionales. El gate de agenda conserva
`blocked-legacy-agenda-logs-v1`: observabilidad production para `clinical-v1` o
`professional-ai-v1` falla hasta verificar saneamiento en origen. El perfil real
`source-sanitized-only-v1` no agrega data protection policy; la fixture
`targeted-data-protection-v1` sólo cubre app/migration después del gate clínico y nunca concede
`logs:Unmask`. Las retenciones continúan en 30 días staging y 90 producción, sin reducir auditoría.

El catálogo tipado contiene exactamente 20 runbooks con severidad, trigger, checks, diagnósticos
seguros, prohibiciones, escalamiento, rollback, evidencia y cierre. Los SLO son internos: 99.5% y
p95 2 s en launch, 99.9% y p95 1.5 s en standard; el error budget congela cambios no esenciales al
50% temprano y prioriza confiabilidad al 100%. La promoción exige evidencia de siete días o
override crítico, budget y pull request manual. Staging conserva release-window y un residual cost
audit explícito para Fargate, NAT, Valkey, ALB, endpoints, RDS/snapshots y recursos retenidos; no
crea scheduler ni auto-shutdown.

`OperationsFoundationAspect` rechaza budgets inseguros, anomaly subscriptions inválidas, topics o
keys no contractuales, alarmas sin metadata/runbook o de alta resolución, dimensiones personales,
widgets de logs, retenciones incorrectas, servicios costosos no aprobados, IDs de cuenta literales
y remediación. Toda esta implementación describe templates offline: budgets, topics, alarmas,
dashboards y monitores desplegados permanecen en cero; tampoco se afirma readiness 200, tráfico
público, subscribers activos o costos reales consultados.

## Red V1 implementada en templates

Cada ambiente crea su propia VPC IPv4-only en `mx-central-1`, con DNS support y hostnames
habilitados. Las AZ se resuelven como dos slots lógicos mediante CloudFormation; no se fijan letras
físicas ni se presupone equivalencia entre cuentas.

| Ambiente/perfil          | VPC            | NAT Gateway | Interface endpoints      | Flow Logs    |
| ------------------------ | -------------- | ----------- | ------------------------ | ------------ |
| staging / lean           | `10.20.0.0/16` | 1           | ninguno                  | ALL, 30 días |
| production / lean        | `10.30.0.0/16` | 1           | ninguno                  | ALL, 90 días |
| production / standard    | `10.30.0.0/16` | 2, uno/AZ   | ninguno inicialmente     | ALL, 90 días |
| production / scale-ready | `10.30.0.0/16` | 2, uno/AZ   | baseline medido: ninguno | ALL, 90 días |

Cada VPC contiene exactamente dos subnets por tier:

| Tier                | Tipo CDK              | Máscara | Uso                                     |
| ------------------- | --------------------- | ------- | --------------------------------------- |
| `public-ingress`    | `PUBLIC`              | `/24`   | futuro ALB y NAT                        |
| `private-app`       | `PRIVATE_WITH_EGRESS` | `/20`   | futuras tasks ECS/jobs sin IP pública   |
| `private-endpoints` | `PRIVATE_ISOLATED`    | `/24`   | ENI de endpoints de interfaz production |
| `isolated-data`     | `PRIVATE_ISOLATED`    | `/24`   | futuros RDS y ElastiCache               |

CIDR exactos por slot:

| Ambiente   | `private-app` A/B               | `public-ingress` A/B             | `private-endpoints` A/B          | `isolated-data` A/B              |
| ---------- | ------------------------------- | -------------------------------- | -------------------------------- | -------------------------------- |
| staging    | `10.20.0.0/20`, `10.20.32.0/20` | `10.20.16.0/24`, `10.20.48.0/24` | `10.20.17.0/24`, `10.20.49.0/24` | `10.20.18.0/24`, `10.20.50.0/24` |
| production | `10.30.0.0/20`, `10.30.32.0/20` | `10.30.16.0/24`, `10.30.48.0/24` | `10.30.17.0/24`, `10.30.49.0/24` | `10.30.18.0/24`, `10.30.50.0/24` |

El L2 `Vpc` conserva NAT, route tables y asociaciones. Como su asignador no expone CIDR exacto
por subnet, un escape hatch limitado fija sólo `AWS::EC2::Subnet.CidrBlock`; assertions y el
validator bloquean cualquier drift. No se crean rutas manuales redundantes.

Rutas y endpoints:

- `public-ingress` usa Internet Gateway;
- `private-app` usa NAT; los perfiles con una NAT comparten NAT A y los de dos conservan una por AZ;
- `private-endpoints` e `isolated-data` no tienen default route;
- S3 Gateway Endpoint existe en ambos ambientes y se asocia sólo a `private-app`;
- ningún perfil inicial crea endpoints de interfaz; `production-core` conserva soporte para ECR
  API/DKR, CloudWatch Logs y Secrets Manager sólo después de disponibilidad, ledger y break-even;
- no existen DynamoDB endpoint, IPv6, custom NACL, peering, VPN o Transit Gateway.

Security Groups base:

- `AlbIngressSecurityGroup`;
- `ApplicationSecurityGroup`;
- `DatabaseSecurityGroup`;
- `SessionSecurityGroup`;
- `EndpointSecurityGroup`.

Todos parten sin ingress ni egress implícito. Application acepta únicamente ALB en TCP 8080,
puerto cerrado por PP245/PP251, y puede salir por HTTPS, DNS dentro de la VPC, MySQL 3306, cache
TLS 6379 y endpoints 443. Database, Session y Endpoint aceptan sólo Application en su puerto. El
ingress CloudFront→ALB se agregará junto con Edge/ALB; no existe ingress público provisional.

VPC Flow Logs captura `ALL` a CloudWatch Logs con intervalo de 60 segundos y formato de campos de
red allowlisted. No captura URL, path HTTP, query, cookie, header, body o datos clínicos. El log
group usa cifrado administrado de CloudWatch, nombre estable, 30 días staging y 90 días
production; production retiene el recurso al retirar el stack.

Costos relativos: staging y production lean usan una NAT y aceptan egress cross-AZ desde
`private-app` B; standard y scale-ready usan dos. Los endpoints candidatos representan ocho
asociaciones endpoint-AZ, pero permanecen diferidos hasta sus gates. S3 Gateway Endpoint reduce
tráfico NAT de S3. No se incluyen importes: se recotizan antes de deploy y promoción.

## Security foundation implementada en templates

Cada ambiente sintetiza cuatro CMK simétricas, single-region y con rotación habilitada:
`application-data`, `secrets`, `audit` y `backup`. Sus aliases estables siguen
`alias/mxmed-{stg|prd}-{purpose}`. Las keys conservan `DeletionPolicy` y
`UpdateReplacePolicy=Retain`; la ventana de borrado KMS es 7 días en staging y 30 días en
production. Las policies de key mantienen administración para la identidad raíz de la cuenta vía
pseudoparámetros, y limitan los usos de servicio: Secrets Manager usa `SecretsKey`; CloudTrail,
CloudWatch Logs y el bucket de auditoría usan `AuditKey`. Los grants de workload se otorgan a
recursos concretos, no a identidades o ARNs reales versionados.

Secrets Manager contiene cuatro recursos por ambiente:

- `/mxmed/{environment}/application/session-signing` genera 64 caracteres durante creación;
- `/mxmed/{environment}/providers/stripe/secret-key` es un contenedor vacío;
- `/mxmed/{environment}/providers/stripe/webhook-secret` es un contenedor vacío;
- `/mxmed/{environment}/providers/ai/api-key` es un contenedor vacío.

Los tres valores externos se cargan únicamente mediante un runbook futuro: no hay plaintext,
`SecretString`, generación provisional, outputs de valores ni valores en evidencia. Data solicita
a RDS administrar su master password y referencia el secreto resultante; Security no crea un
secreto RDS adicional.

IAM crea permissions boundaries separadas para workload y deployment, y cuatro roles ECS con
trust exclusivo en `ecs-tasks.amazonaws.com`: execution, application, migration y jobs. Security
no concede startup secrets de forma anticipada; Compute adjunta al execution role únicamente los
secretos requeridos por el activation mode y capability, junto con decrypt en `SecretsKey`. Los
roles de aplicación, migración y jobs esperan grants del stack propietario de cada recurso. La
factory reutilizable para `SecurityAuditRole` y `BreakGlassRole` exige principal explícito, MFA,
sesión exacta de una hora, boundary, ambiente y justificación contractual, pero no instancia roles
humanos mientras AWS IAM Identity Center siga pendiente.

También existe un construct reusable para GitHub OIDC nativo con audience
`sts.amazonaws.com`, subject exacto por rama o environment, sesión limitada y deployment
boundary. No se instancia: organización, repositorio, rama y GitHub Environment aún no tienen
contrato, por lo que las plantillas predeterminadas no contienen provider ni deployment role.

La auditoría crea un bucket privado, cifrado con `AuditKey`, versionado, Bucket Owner Enforced,
bucket key, SSL obligatorio y retención del recurso. Su lifecycle conserva objetos y versiones no
actuales 365 días en staging y 2555 días en production. CloudTrail es multi-region, incluye
eventos globales, valida archivos, registra management events de lectura y escritura, escribe en
S3 y en `/mxmed/{environment}/security/cloudtrail`, con retención de logs 90 días staging y 365
días production. Los data events permanecen desactivados hasta que Storage identifique los
buckets clínicos concretos; no se habilita `All S3 buckets`.

CloudFormation no expone `RecoveryWindowInDays` como propiedad de `AWS::SecretsManager::Secret`.
Por eso los cuatro secretos usan `DeletionPolicy` y `UpdateReplacePolicy=Retain`; las ventanas de
recuperación 7/30 días son un control operativo validado en configuración y aplicable sólo por el
runbook de borrado. Retain evita que eliminar o reemplazar el stack programe una eliminación
accidental.

### SECRET DELETION RUNBOOK — NO EJECUTADO

1. Confirmar que ningún workload, job, pipeline o rotación usa el secreto.
2. Desactivar referencias y despliegues consumidores antes de cualquier borrado.
3. Verificar CloudTrail, alarmas y ausencia de lecturas inesperadas.
4. Retirar el recurso de IaC conservándolo mediante `Retain`; nunca convertirlo en borrado
   automático.
5. Importar o mantener el secreto bajo control operativo si todavía se necesita administrar con
   IaC.
6. Programar `DeleteSecret` con ventana de recuperación de 7 días en staging o 30 días en
   production.
7. Prohibir `ForceDeleteWithoutRecovery`.
8. Verificar que `RestoreSecret` es viable durante toda la ventana.
9. Registrar aprobación, motivo, propietario, fecha y evidencia sin incluir el valor secreto.
10. No reutilizar el mismo nombre hasta concluir o cancelar la eliminación programada.

## Data foundation implementada en templates

Cada ambiente sintetiza exactamente una `AWS::RDS::DBInstance` MySQL `8.4.9`. Se usa
intencionalmente el L1 `CfnDBInstance`, porque el contrato requiere
`ManageMasterUserPassword=true`, `MasterUserSecret.KmsKeyId` y
`EngineLifecycleSupport=open-source-rds-extended-support-disabled` de forma explícita y auditable.
No hay `MasterUserPassword` ni output del secreto master. En `disabled-v1` y `registry-only-v1`
Data no agrega secretos. En `tasks-ready-v1` y `service-enabled-v1` crea exclusivamente
`/mxmed/{environment}/application/database-user`, cifrado con `SecretsKey`, generado con usuario
`mxmed_app`, password de 64 caracteres y retención; su existencia no crea el usuario SQL.

| Ambiente/perfil                     | Clase           | Topología | Storage inicial / máximo | Backup  | Monitoring | Removal           |
| ----------------------------------- | --------------- | --------- | ------------------------ | ------- | ---------- | ----------------- |
| staging / lean                      | `db.t4g.medium` | Single-AZ | 40 / 200 GiB             | 7 días  | 60 s       | Snapshot/Snapshot |
| production / lean                   | `db.t4g.medium` | Single-AZ | 40 / 200 GiB             | 35 días | 15 s       | Retain/Retain     |
| production / standard o scale-ready | `db.m6g.large`  | Multi-AZ  | 100 / 1000 GiB           | 35 días | 15 s       | Retain/Retain     |

Ambos ambientes usan gp3 con 3000 IOPS y 125 MiB/s, cifrado por `ApplicationDataKey`, IPv4,
acceso no público, exactamente un `DatabaseSecurityGroup` y un DB subnet group con las dos
`isolated-data`. Production activa deletion protection. Los backups automatizados se conservan al
retirar la instancia, las tags se copian a snapshot y `applyImmediately`, auto minor upgrade y
major upgrade permanecen deshabilitados.

El parameter group explícito `mysql8.4` exige TLS mediante `require_secure_transport=ON`, usa
`utf8mb4`/`utf8mb4_unicode_ci`, UTC, binlog ROW, general log apagado y slow query log a archivo con
umbral de un segundo. Sólo `error` y `slowquery` se exportan a CloudWatch. Database Insights opera
en modo Standard con Performance Insights cifrado y retención de siete días. Enhanced Monitoring
usa un role exclusivo que confía sólo en `monitoring.rds.amazonaws.com` y contiene únicamente la
policy oficial `service-role/AmazonRDSEnhancedMonitoringRole`.

Las migraciones, los usuarios funcionales (`mxmed_app`, `mxmed_migration`) y sus grants continúan
pendientes y no se ejecutan en synth. RDS Proxy, read replicas, Aurora y Multi-AZ DB Cluster quedan
diferidos. Los templates y tests no afirman que la instancia, contraseña, backups o failover
existan realmente; esta fase creó cero recursos AWS.

## Storage foundation implementada en templates

Cada ambiente sintetiza exactamente cuatro `AWS::S3::Bucket`, sin nombres físicos ni outputs:

| Identificador            | Clasificación | Uso contractual                               |
| ------------------------ | ------------- | --------------------------------------------- |
| `PublicMediaBucket`      | `public`      | media pública ya aprobada y sus derivados     |
| `PrivateDocumentsBucket` | `sensitive`   | documentos administrativos y exports privados |
| `ClinicalRecordsBucket`  | `clinical`    | objetos de expediente y anexos clínicos       |
| `UploadQuarantineBucket` | `sensitive`   | entrada temporal pendiente de análisis        |

`public` describe el contenido, no el acceso. Los cuatro buckets bloquean todo acceso público,
usan `BucketOwnerEnforced` sin ACL, requieren TLS, cifran con `ApplicationDataKey` mediante
SSE-KMS y S3 Bucket Keys, habilitan versionado y conservan tanto el recurso eliminado como su
reemplazo con `Retain`. No tienen website, CORS, replication, Object Lock ni server access
logging.

Todos abortan multipart incompleto al día uno. PublicMedia expira versiones no actuales a 30 días
en staging y 90 en production. PrivateDocuments y ClinicalRecords expiran versiones sintéticas a
30 días sólo en staging; production no expira objetos ni versiones y transiciona current objects
a Intelligent-Tiering al día 30. `temporary-exports/` expira a siete días. Quarantine expira por
tag `scan-status`: `pending=7`, `failed=14`, `infected=30` y `clean=1` días. No se usa Glacier ni
Deep Archive.

Sólo Quarantine habilita la configuración nativa de notificación EventBridge. El scanner, rule,
SQS/DLQ y Fargate corresponden a Jobs y todavía no existen; esta foundation no analiza, copia,
promueve, lee o elimina objetos. CloudFront/OAC y las presigned URLs de upload/download tampoco
están implementadas. Los límites contractuales que deberán aplicar sus futuros consumidores son
600 segundos para upload, 300 para download, 20 MiB para media pública de entrada, 10 MiB para
derivados y 100 MiB para private/clinical.

Los helpers puros construyen únicamente keys opacas con UUID para Quarantine, public media,
private, clinical y exports. Los validators rechazan metadata/tags fuera de allowlist, valores con
semántica personal, MIME no permitido, tamaños fuera de techo y TTL fuera del límite. Ningún
helper genera URL o llama AWS.

Storage depende directamente sólo de Security para `ApplicationDataKey`. Compute, Jobs, Edge,
Operations y Backup declaran consumo de Storage sin invertir la dependencia. Los data events se
difieren a Operations; Object Lock requiere contrato legal propio y replication/backup quedan
diferidos a Backup/DR. Versioning no se presenta como backup independiente y no existen backups
S3 reales en esta etapa.

## Session foundation implementada en templates

Cada ambiente sintetiza un replication group node-based dedicado a sesiones con Amazon
ElastiCache for Valkey `8.2`, cluster mode disabled y un shard lógico. Staging y production lean
usan un `cache.t4g.micro`, un primary, cero réplicas, sin Multi-AZ ni failover. Production standard
usa primary y réplica `micro`; scale-ready conserva la misma topología con nodos `medium`. Los
perfiles HA distribuyen nodos en las dos subnets `isolated-data` y habilitan automatic failover. La
aplicación futura consumirá sólo el primary endpoint; no se fijan IP, endpoint de nodo o
configuration endpoint.

Session reutiliza exactamente la VPC, las dos subnets aisladas y `SessionSecurityGroup` de
Network, cuyo único ingress es TCP 6379 desde `ApplicationSecurityGroup`. El replication group es
IPv4, cifra en reposo con `ApplicationDataKey` y habilita tránsito cifrado desde su creación. La
propiedad CloudFormation `TransitEncryptionMode` se omite intencionalmente: `preferred` es el paso
de migración para clusters preexistentes con plaintext, mientras que un cluster Valkey nuevo con
`TransitEncryptionEnabled=true` nace TLS-only. El runtime futuro deberá validar certificado y
hostname y nunca tendrá fallback plaintext.

El parameter group `valkey8` fija `volatile-ttl`, timeout 300, active rehashing, keepalive 60 y
notificaciones de keyspace vacías. No configura `cluster-enabled`, appendonly, save, slow/command
log, modules o search/vector. Snapshots, snapshot window, Global Datastore, data tiering, log
delivery y auto minor upgrade permanecen apagados. Las ventanas UTC son
`sun:03:30-sun:04:30` para staging y `sun:04:30-sun:05:30` para production.

Session crea un secreto separado en
`/mxmed/{environmentName}/application/session-store-auth`, cifrado con `SecretsKey` y conservado
con `Retain`. Secrets Manager genera el campo `password` de 64 caracteres; CDK representa la
exclusión de espacio como `IncludeSpace=false`, equivalente al contrato conceptual
`excludeSpace=true`. El template sólo contiene una dynamic reference versionless al campo y no
contiene password, parámetro u output sensible.

Por compatibilidad con la referencia CloudFormation, el user group incluye dos users Valkey con
la misma referencia dinámica:

- `mxmed_session_app`, activo y restringido a `~mxmed:{stg|prd}:session:*` con sólo `GET`, `SET`,
  `SETEX`, `DEL`, `UNLINK`, `EXISTS`, `EXPIRE`, `PEXPIRE`, `TTL`, `PTTL`, `TOUCH` y `PING`;
- `default`, deshabilitado mediante `off ~* -@all`, sin comandos, keys o uso runtime.

Compartir la referencia de password no concede acceso al default user porque su access string lo
mantiene apagado y elimina todas las categorías. No existen `+@all`, `KEYS`, `SCAN`, flush,
administración, script o `EVAL/EVALSHA`; la readiness no demostró que locking requiera esos
comandos y la ACL no se amplió por intuición.

Los helpers puros formalizan prefix/key opacos, allowlist de 12 keys mínimas de payload, máximo de
32 KiB sin truncamiento, cookie `__Host-mxmed_session`, TTL idle de 1800 segundos, lifetime
absoluto de 43200 segundos y locking acotado a 10 segundos con espera de 100000 microsegundos.
No generan session IDs ni conectan a Valkey.

Los recursos efímeros de cache, subnet/parameter/user groups y users usan `Delete` al retirar o
reemplazar; perder el cache obliga a reautenticarse y no elimina información empresarial. El auth
secret usa `Retain` y requiere runbook seguro. Production conserva además termination protection
del stack. Session depende sólo de Network y Security; Compute y Operations dependen de Session,
sin ciclo ni integración runtime adelantada.

El scaffold PHP/Apache y las definiciones ECS están implementados sin ejecutar sus runtimes.
La integración funcional de phpredis, readiness y migración del handler filesystem sigue
pendiente; no hubo login, conexión, failover real ni recurso AWS desplegado.

## Synth offline

La foundation no usa AWS SDK, AWS CLI, profiles, context providers, lookups, Docker bundling ni
assets remotos. No existen `Vpc.fromLookup`, `HostedZone.fromLookup`, `AwsCustomResource` o
`cdk.context.json`.

```sh
npm run synth:staging
npm run synth:production
npm run synth:production:launch-lean
npm run synth:production:standard
npm run synth:production:scale-ready
npm run synth:production:launch-lean:compute-registry
npm run synth:production:launch-lean:compute-tasks
npm run synth:production:launch-lean:compute-service
npm run synth:production:standard:compute-service
npm run synth:production:scale-ready:compute-service
npm run synth:staging:release-window:compute-service
npm run synth:production:operations:cost-controls
npm run synth:production:launch-lean:operations
npm run synth:production:standard:operations
npm run synth:production:scale-ready:operations
npm run synth:staging:release-window:operations
npm run synth:production:launch-lean:backup-regional
npm run synth:production:standard:backup-regional
npm run synth:production:scale-ready:backup-regional
npm run synth:staging:release-window:backup-regional
```

Todos los comandos deben funcionar sin credenciales AWS y no despliegan nada. Los generales
mantienen Compute, Edge, Operations y Backup/DR deshabilitados; los explícitos prueban
registry/tasks/service, los cinco perfiles Operations y los cuatro perfiles regionales Backup.
SecurityStack crea cuatro contenedores de
secreto pero cero valores versionados; DataStack crea el contrato RDS sin valor secreto;
StorageStack crea sólo cuatro buckets y cuatro políticas SSL; SessionStack crea la topología
Valkey y un secreto generado sin revelar su valor. Compute no crea scanner, SQS,
EventBridge Rule, ALB, CloudFront, WAF, OIDC/deployment role ni roles humanos. `cdk.out/` es temporal y
no se versiona.

## Naming y tags

Los nombres conceptuales siguen:

```text
mxmed-{environmentCode}-{component}
```

Los códigos son `stg` y `prd`. La utilidad rechaza ambientes desconocidos, componentes vacíos,
caracteres no permitidos, nombres demasiado largos y separadores inválidos. No incorpora cuenta,
región, usuario, datos personales o secretos.

Todo recurso taggable futuro deberá tener:

- `Project=mxmed`;
- `Environment=staging|production`;
- `ManagedBy=aws-cdk`;
- `Application=mexico-medico`;
- `Component`;
- `DataClassification`;
- `Criticality`;
- `Backup`;
- `Owner=platform`.
- `DeploymentProfile`;
- `CostReview` con fecha ISO no personal;
- `Ephemeral`;
- `SchedulePolicy`;
- `CostTier`.
- `CostScope=mxmed-staging|mxmed-production`.

`MandatoryTagsAspect` falla síntesis ante tags ausentes. La allowlist explícita contiene metadata
de framework y los tipos cuya representación CloudFormation no acepta los tags contractuales:
`AWS::IAM::ManagedPolicy`, `AWS::KMS::Alias` y `AWS::S3::BucketPolicy`. Debe revisarse
explícitamente antes de ampliarse.

## Seguridad y guardrails

Los Aspects iniciales son:

- `MandatoryTagsAspect`;
- `NoPublicBucketAspect`;
- `NoPublicDatabaseAspect`;
- `ProductionRetentionAspect`;
- `StripeReturnLoggingSafetyAspect`;
- `SecurityFoundationAspect`;
- `NoPlaintextSecretAspect`;
- `LeastPrivilegeIamAspect`;
- `DataFoundationAspect`;
- `StorageFoundationAspect`;
- `SessionFoundationAspect`;
- `ComputeFoundationAspect`.
- `OperationsFoundationAspect`.
- `BackupDrFoundationAspect`.

NetworkStack registra además un validator bloqueante que comprueba CIDR/DNS, dos AZ, subnet
tiers, NAT, rutas, ausencia de IPv6/NACL/peering/VPN/TGW, SG sin ingress público/SSH, S3 e
interface endpoints y Flow Logs ALL con retención contractual.

SecurityStack registra otro validator bloqueante que comprueba cantidades y contrato de KMS,
Secrets Manager, boundaries, roles, bucket, log group y management trail. Los Aspects de seguridad
rechazan plaintext/generación externa, secretos sin KMS/retención/path contractual, IAM
administrativo o wildcard fuera de la allowlist y drift de cifrado, auditoría o retención. El
`Deny` de SSL del bucket usa `Principal: *` porque debe negar transporte inseguro a cualquier
principal; es una excepción técnica de guardrail, no un permiso público, y la bucket policy sólo
concede escritura/ACL check al service principal de CloudTrail bajo condiciones estrictas.

DataStack registra su Aspect y validator bloqueantes para comprobar instancia, subnet group,
parameter group, role de Enhanced Monitoring, cifrado, credencial administrada, backups,
retención, Multi-AZ, logs y ausencia de cluster, Proxy, replica, secreto duplicado u output
sensible.

StorageStack registra su Aspect y validator bloqueantes para comprobar inventario de cuatro
buckets, acceso privado, ownership, versioning, SSE-KMS con `ApplicationDataKey`, Bucket Keys,
TLS, retención, lifecycle, EventBridge de Quarantine y ausencia de nombre físico, output, CORS,
website, Object Lock, replication, server logging, CloudFront, scanner, SQS o data trail.

SessionStack registra su Aspect y validator bloqueantes para comprobar inventario, Valkey 8.2,
topología por ambiente, subnet/SG, KMS, TLS desde creación sin mode `preferred`, RBAC dinámico,
default user apagado, ACL mínima, parameter group, ausencia de snapshots/logs/Global Datastore,
removal policy y cero outputs sensibles.

ComputeStack registra `ComputeFoundationAspect`, que comprueba inventario por activation mode,
ECR KMS/inmutable/scan/lifecycle, digest requerido, Linux X86_64/Fargate, CPU y memoria de perfil,
allowlist exacta de secretos, contenedor no root/read-only, service privado 1.4.0, desired/min/max,
autoscaling 60/70 y ausencia de recursos Edge.

Emiten errores visibles; no corrigen recursos inseguros silenciosamente. Jobs y el stack Edge
heredado permanecen vacíos; los recursos Edge nuevos sólo existen en templates opt-in y este
proyecto no afirma que CloudFront, WAF, ALB, scanner, S3 workload o RDS estén desplegados.

Los valores secretos proceden de Secrets Manager y runbooks autorizados. Nunca deben incluirse en
Git, props de configuración, CDK context, outputs, tags, plantillas, logs o evidencia. Tampoco se
guardan access keys en GitHub; el CI/CD futuro usará el construct OIDC y credenciales temporales
sólo después de contratar la identidad exacta.

## Retorno Stripe

La única política inicial es `path-only-no-query`. Representa contractualmente:

- query, Referer, Cookie y request line completa excluidos de logs;
- cache deshabilitada;
- redacción WAF de query obligatoria.

El Aspect rechaza variantes sintéticas inseguras. Edge modela CloudFront/WAF/ALB offline, pero no
crea el bridge `/subscriptions/stripe-return`; tampoco modifica webhook, PHP o pagos.

## Bootstrap, diff y deploy futuros

El proyecto usa `DefaultStackSynthesizer` y requerirá bootstrap moderno independiente por cuenta y
región. El futuro runbook deberá usar identidad temporal, trusted accounts mínimos y políticas de
ejecución limitadas. No se debe ejecutar bootstrap desde esta microfase.

Los scripts `diff:*` son interfaces contractuales; requieren una microfase autorizada y nunca deben
apuntarse de forma improvisada a una cuenta. Production usará change set, revisión de reemplazos y
aprobación manual.

Deploy permanece prohibido hasta que las microfases de recursos, seguridad, staging y CI/CD lo
autoricen. No se usará `--require-approval never` como valor predeterminado de production.

## Pruebas

```sh
npm run typecheck
npm run lint
npm run format:check
npm run test
npm run validate
```

Las 1501 pruebas (1196 preservadas y 305 nuevas para Backup/DR) cubren configuración, naming,
topología/dependencias, tags, buckets/DB públicas, retención production, logging Stripe,
VPC/subnets/NAT/rutas, endpoints, perfiles launch/standard/scale, ledger/gates, SG, Flow Logs, KMS,
secretos, IAM, CloudTrail, RDS MySQL 8.4.9,
parameter/subnet groups, Enhanced Monitoring, inventario/lifecycle/cifrado de Storage, helpers de
keys, metadata/tags, MIME/tamaños/TTL, Valkey/RBAC/TLS, contratos de sesión, guardrails negativos
y síntesis determinista offline, además de activation modes, capability profiles, digest, ECR,
tasks, servicio, autoscaling, runtime scaffold, guardrails Compute y los contratos Edge de
activación, ALB, attachment, OAC, bucket/KMS policies, cache, WAF, Stripe, DNS fixture y privacidad,
además de modos Operations, budgets, Cost Anomaly Detection, SNS/KMS, alarmas profile-aware,
dashboards, métricas de aplicación, log privacy, runbooks, SLO/error budget, residual audit,
guardrails y synth determinista. Los contratos Backup/DR añaden coverage de activation modes,
PITR RDS y planes periódicos, S3 continuous/periodic, Vault Lock governance, selecciones
explícitas, IAM/KMS, monitoring, handoff cross-region, restore aislado, sentinels/cleanup, 22
runbooks, RPO/RTO, readiness, costo, privacidad, guardrails y determinismo offline. Los
snapshots completos no son la única fuente de validación.

## Cambios, rollback y drift

CloudFormation será la fuente de verdad. Los cambios manuales están prohibidos salvo emergencia
documentada y reconciliada en IaC. Todo cambio futuro debe revisar `cdk diff`, reemplazos,
retención, seguridad y rollback.

Rollback de esta foundation: revertir atómicamente su commit versionado. Cuando existan recursos,
el rollback será específico por stack y nunca se inferirá que revertir código revierte datos. No
se debe borrar `cdk.out/` para ocultar una diferencia; se regenera desde el commit revisado.

## Troubleshooting

- Node incorrecto: ejecutar `nvm use` y confirmar `node --version`.
- Dependencias divergentes: eliminar sólo `node_modules/` local y ejecutar `npm ci`; no regenerar
  el lockfile sin una actualización autorizada.
- Context ausente: usar uno de los scripts `synth:*`; la app no infiere ambiente ni perfil.
- Solicitud de credenciales o lookup: detenerse; la foundation debe sintetizar offline.
- Error de Aspect: corregir configuración o recurso. No silenciar la anotación.
- Diff, bootstrap o deploy requerido: detenerse y solicitar la microfase correspondiente.
