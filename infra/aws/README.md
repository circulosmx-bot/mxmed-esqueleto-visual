# MXMed AWS CDK foundation

## Propósito y estado

Este proyecto implementa la foundation local de Infrastructure as Code, la red V1 y las
foundations de seguridad, datos y almacenamiento de México Médico. Traduce PP-Decisiones 245
(`MXMED_AWS_ECS_FARGATE_REFERENCE_ARCHITECTURE_V1`), PP-Decisiones 249
(`MXMED_AWS_CDK_FOUNDATION_CONTRACT_V1`), PP-Decisiones 251
(`MXMED_AWS_NETWORK_READINESS_CONTRACT_V1`) y PP-Decisiones 253
(`MXMED_AWS_SECURITY_READINESS_CONTRACT_V1`), además de PP-Decisiones 255
(`MXMED_AWS_DATA_READINESS_CONTRACT_V1`) y PP-Decisiones 257
(`MXMED_AWS_STORAGE_FOUNDATION_CONTRACT_V1`), a AWS CDK v2 con TypeScript.

`MxMedNetworkStack`, `MxMedSecurityStack`, `MxMedDataStack` y `MxMedStorageStack` contienen
recursos CloudFormation sintetizables offline. Los demás stacks continúan vacíos. Nada está
desplegado: bootstrap, diff y deploy permanecen pendientes y están prohibidos en esta etapa.

## Arquitectura contractual

- Workloads `staging` y `production`: región `mx-central-1`.
- Correo de cada ambiente: stage separado en `us-east-1` para SES.
- Compute futuro: ECS Fargate.
- Ingress futuro: Route 53, CloudFront, WAF, ALB y ECS.
- Datos: template RDS MySQL 8.4.9 y cuatro buckets S3 privados; sesiones en ElastiCache futuras.
- CloudFormation, sintetizado por CDK, será la fuente de verdad.

`MxMedEnvironmentStage` contiene diez stacks: Network, Security, Data y Storage implementados;
Session, Compute, Edge, Operations, Jobs y Backup todavía vacíos. `MxMedEmailStage` contiene
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

| Comando                    | Propósito                                             |
| -------------------------- | ----------------------------------------------------- |
| `npm run build`            | Compila TypeScript a `dist/`.                         |
| `npm run typecheck`        | Ejecuta el compilador sin emitir archivos.            |
| `npm run lint`             | Valida TypeScript con ESLint local.                   |
| `npm run format`           | Formatea exclusivamente archivos bajo `infra/aws/`.   |
| `npm run format:check`     | Comprueba formato sin modificar.                      |
| `npm run test`             | Ejecuta unit tests y assertions finas.                |
| `npm run test:watch`       | Ejecuta Jest en modo local interactivo.               |
| `npm run synth:staging`    | Sintetiza staging offline en `cdk.out/staging`.       |
| `npm run synth:production` | Sintetiza production offline en `cdk.out/production`. |
| `npm run diff:staging`     | Contrato futuro de diff; no ejecutar todavía.         |
| `npm run diff:production`  | Contrato futuro de diff; no ejecutar todavía.         |
| `npm run validate`         | Ejecuta typecheck, lint, formato y tests.             |
| `npm run clean`            | Elimina únicamente outputs locales generados.         |

No existe script de deploy o bootstrap automático.

## Ambientes y configuración

El entrypoint `bin/mxmed.ts` exige un único context:

```text
environment=staging|production
```

Los scripts de synth lo proporcionan de forma explícita. La configuración tipada vive en
`lib/config/`, permite sólo esos dos ambientes y valida antes de crear stages:

- región primaria y región de correo;
- CIDR VPC RFC1918 `/16` exacto por ambiente;
- masks de los cuatro tiers y exactamente dos AZ;
- perfiles de NAT y compute, los 21 campos cerrados de base de datos y los 25 campos cerrados de
  Storage;
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

`domainAlias` permanece omitido hasta una decisión empresarial. No hay cuentas, dominios, ARNs,
IPs o nombres físicos globales versionados. Si `CDK_DEFAULT_ACCOUNT` existe, la app lo transmite
sin imprimirlo; si no existe, synth continúa offline y sin cuenta explícita.

## Red V1 implementada en templates

Cada ambiente crea su propia VPC IPv4-only en `mx-central-1`, con DNS support y hostnames
habilitados. Las AZ se resuelven como dos slots lógicos mediante CloudFormation; no se fijan letras
físicas ni se presupone equivalencia entre cuentas.

| Ambiente   | VPC            | NAT Gateway | Interface endpoints                | Flow Logs    |
| ---------- | -------------- | ----------- | ---------------------------------- | ------------ |
| staging    | `10.20.0.0/16` | 1           | ninguno                            | ALL, 30 días |
| production | `10.30.0.0/16` | 2, uno/AZ   | ECR API/DKR, Logs, Secrets Manager | ALL, 90 días |

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
- `private-app` usa NAT; staging comparte NAT A y production conserva NAT por AZ;
- `private-endpoints` e `isolated-data` no tienen default route;
- S3 Gateway Endpoint existe en ambos ambientes y se asocia sólo a `private-app`;
- staging no crea interface endpoints;
- production usa constantes oficiales CDK para ECR API, ECR DKR, CloudWatch Logs y Secrets
  Manager, con private DNS, las dos `private-endpoints` y un SG dedicado;
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

Costos relativos: staging paga una NAT y acepta egress cross-AZ desde `private-app` B; production
paga dos NAT y hasta ocho asociaciones endpoint-AZ para conservar HA/ruta privada. S3 Gateway
Endpoint reduce tráfico NAT de S3. No se incluyen importes: deberán cotizarse antes de deploy.

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
trust exclusivo en `ecs-tasks.amazonaws.com`: execution, application, migration y jobs. Sólo el
execution role recibe lectura de los cuatro secretos anteriores y decrypt en `SecretsKey`; los
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
No hay `MasterUserPassword`, `AWS::SecretsManager::Secret` adicional ni output del secreto. La
referencia tipada al secreto administrado por RDS queda en DataStack y todavía no se concede a la
aplicación ni a MigrationTaskRole.

| Ambiente   | Clase           | Topología | Storage inicial / máximo | Backup  | Monitoring | Removal           |
| ---------- | --------------- | --------- | ------------------------ | ------- | ---------- | ----------------- |
| staging    | `db.t4g.medium` | Single-AZ | 40 / 200 GiB             | 7 días  | 60 s       | Snapshot/Snapshot |
| production | `db.m6g.large`  | Multi-AZ  | 100 / 1000 GiB           | 35 días | 15 s       | Retain/Retain     |

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

## Synth offline

La foundation no usa AWS SDK, AWS CLI, profiles, context providers, lookups, Docker bundling ni
assets remotos. No existen `Vpc.fromLookup`, `HostedZone.fromLookup`, `AwsCustomResource` o
`cdk.context.json`.

```sh
npm run synth:staging
npm run synth:production
```

Ambos comandos deben funcionar sin credenciales AWS. Las plantillas actuales contienen recursos
únicamente en NetworkStack, SecurityStack, DataStack y StorageStack; Email y los otros seis
workload stacks continúan sin recursos. SecurityStack crea cuatro contenedores de secreto pero
cero valores versionados; DataStack crea el contrato RDS sin valor secreto; StorageStack crea sólo
cuatro buckets y cuatro políticas SSL. No crea ECS services/tasks, scanner, SQS, EventBridge Rule,
ElastiCache, ALB, CloudFront, WAF, OIDC/deployment role ni roles humanos. `cdk.out/` es temporal y
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
- `DataFoundationAspect`.
- `StorageFoundationAspect`.

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

Emiten errores visibles; no corrigen recursos inseguros silenciosamente. Edge y Jobs permanecen
vacíos: los tests usan mutaciones sintéticas para demostrar guardrails, pero este proyecto no
afirma que CloudFront, WAF, scanner, S3 workload o RDS estén desplegados.

Los valores secretos proceden de Secrets Manager y runbooks autorizados. Nunca deben incluirse en
Git, props de configuración, CDK context, outputs, tags, plantillas, logs o evidencia. Tampoco se
guardan access keys en GitHub; el CI/CD futuro usará el construct OIDC y credenciales temporales
sólo después de contratar la identidad exacta.

## Retorno Stripe

La única política inicial es `path-only-no-query`. Representa contractualmente:

- query, Referer, Cookie y request line completa excluidos de logs;
- cache deshabilitada;
- redacción WAF de query obligatoria.

El Aspect rechaza variantes sintéticas inseguras. Todavía no crea CloudFront, WAF, ALB ni el
bridge `/subscriptions/stripe-return`; tampoco implementa webhook o pagos.

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

Las 419 pruebas (293 heredadas y 126 nuevas de Storage) cubren configuración, naming,
topología/dependencias, tags, buckets/DB públicas, retención production, logging Stripe,
VPC/subnets/NAT/rutas, endpoints, SG, Flow Logs, KMS, secretos, IAM, CloudTrail, RDS MySQL 8.4.9,
parameter/subnet groups, Enhanced Monitoring, inventario/lifecycle/cifrado de Storage, helpers de
keys, metadata/tags, MIME/tamaños/TTL, guardrails negativos y síntesis determinista offline. Los
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
- Context ausente: usar uno de los scripts `synth:*`; la app no elige production por defecto.
- Solicitud de credenciales o lookup: detenerse; la foundation debe sintetizar offline.
- Error de Aspect: corregir configuración o recurso. No silenciar la anotación.
- Diff, bootstrap o deploy requerido: detenerse y solicitar la microfase correspondiente.
