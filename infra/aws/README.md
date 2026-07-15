# MXMed AWS CDK foundation

## Propósito y estado

Este proyecto implementa la foundation local de Infrastructure as Code de México Médico. Traduce
PP-Decisiones 245 (`MXMED_AWS_ECS_FARGATE_REFERENCE_ARCHITECTURE_V1`) y PP-Decisiones 249
(`MXMED_AWS_CDK_FOUNDATION_CONTRACT_V1`) a AWS CDK v2 con TypeScript.

La foundation todavía no contiene recursos AWS. Los stacks sólo establecen nombres, tags,
regiones, dependencias y guardrails para las siguientes microfases. Bootstrap y deploy permanecen
pendientes y están prohibidos en esta etapa.

## Arquitectura contractual

- Workloads `staging` y `production`: región `mx-central-1`.
- Correo de cada ambiente: stage separado en `us-east-1` para SES.
- Compute futuro: ECS Fargate.
- Ingress futuro: Route 53, CloudFront, WAF, ALB y ECS.
- Datos futuros: RDS MySQL; objetos privados en S3; sesiones en ElastiCache.
- CloudFormation, sintetizado por CDK, será la fuente de verdad.

`MxMedEnvironmentStage` contiene diez stacks vacíos: Network, Security, Data, Storage, Session,
Compute, Edge, Operations, Jobs y Backup. `MxMedEmailStage` contiene únicamente Email y no crea
referencias CloudFormation cross-region.

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
- mínimo dos AZ;
- perfiles de NAT, compute y base de datos;
- retenciones y protecciones por ambiente;
- WAF y logging CloudFront seguro;
- tags obligatorios;
- política Stripe return `path-only-no-query`;
- ausencia de campos sensibles y valores con apariencia de credencial.

`domainAlias` permanece omitido hasta una decisión empresarial. No hay cuentas, dominios, ARNs,
IPs o nombres físicos globales versionados. Si `CDK_DEFAULT_ACCOUNT` existe, la app lo transmite
sin imprimirlo; si no existe, synth continúa offline y sin cuenta explícita.

## Synth offline

La foundation no usa AWS SDK, AWS CLI, profiles, context providers, lookups, Docker bundling ni
assets remotos. No existen `Vpc.fromLookup`, `HostedZone.fromLookup`, `AwsCustomResource` o
`cdk.context.json`.

```sh
npm run synth:staging
npm run synth:production
```

Ambos comandos deben funcionar sin credenciales AWS. Las plantillas actuales contienen cero
recursos productivos; los recursos sintéticos usados para probar Aspects viven sólo en Jest.
`cdk.out/` es temporal y no se versiona.

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

`MandatoryTagsAspect` falla síntesis ante tags ausentes. La allowlist inicial de recursos no
taggables contiene únicamente metadata de framework y debe revisarse explícitamente antes de
ampliarse.

## Seguridad y guardrails

Los Aspects iniciales son:

- `MandatoryTagsAspect`;
- `NoPublicBucketAspect`;
- `NoPublicDatabaseAspect`;
- `ProductionRetentionAspect`;
- `StripeReturnLoggingSafetyAspect`.

Emiten errores visibles; no corrigen recursos inseguros silenciosamente. Edge y Data permanecen
vacíos: los tests usan recursos sintéticos para demostrar el comportamiento futuro, pero esta
foundation no afirma que CloudFront, WAF, S3 o RDS estén desplegados.

Los secretos futuros procederán de Secrets Manager y roles autorizados. Nunca deben incluirse en
Git, props de configuración, CDK context, outputs, tags, plantillas, logs o evidencia. Tampoco se
guardan access keys en GitHub; el CI/CD futuro usará OIDC y credenciales temporales.

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

Las suites cubren configuración, naming, topología/dependencias, tags, buckets/DB públicas,
retención production, logging Stripe y síntesis determinista offline. Los snapshots completos no
son la única fuente de validación.

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
