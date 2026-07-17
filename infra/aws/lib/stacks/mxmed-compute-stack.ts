import {
  AspectPriority,
  Aspects,
  CfnParameter,
  Duration,
  Fn,
  RemovalPolicy,
  Tags,
} from 'aws-cdk-lib';
import type { ISubnet, ISecurityGroup, IVpc, Vpc } from 'aws-cdk-lib/aws-ec2';
import {
  Capability,
  Cluster,
  ContainerInsights,
  ContainerImage,
  CpuArchitecture,
  FargatePlatformVersion,
  FargateService,
  FargateTaskDefinition,
  type ICluster,
  LinuxParameters,
  LogDrivers,
  OperatingSystemFamily,
  PropagatedTagSource,
  Protocol,
  Secret as EcsSecret,
} from 'aws-cdk-lib/aws-ecs';
import type { ContainerDefinition, ScalableTaskCount } from 'aws-cdk-lib/aws-ecs';
import { Repository, RepositoryEncryption, TagMutability, TagStatus } from 'aws-cdk-lib/aws-ecr';
import { CfnPolicy, Effect, PolicyDocument, PolicyStatement, Role } from 'aws-cdk-lib/aws-iam';
import type { IRole } from 'aws-cdk-lib/aws-iam';
import type { IKey } from 'aws-cdk-lib/aws-kms';
import {
  CfnLogGroup,
  CustomDataIdentifier,
  DataIdentifier,
  DataProtectionPolicy,
  LogGroup,
  RetentionDays,
} from 'aws-cdk-lib/aws-logs';
import type { IBucket } from 'aws-cdk-lib/aws-s3';
import type { ISecret } from 'aws-cdk-lib/aws-secretsmanager';
import type { IApplicationTargetGroup } from 'aws-cdk-lib/aws-elasticloadbalancingv2';
import type { Construct } from 'constructs';

import { ComputeFoundationAspect } from '../aspects/compute-foundation-aspect';
import {
  capabilityIncludesAi,
  capabilityIncludesClinical,
  capabilityIncludesPaid,
  computeCreatesRegistry,
  computeCreatesService,
  computeCreatesTasks,
} from '../config/compute-config';
import type { MxMedRuntimeCapabilityProfile } from '../config/environment-config';
import type { MxMedLaunchCapacity } from '../config/launch-profiles';
import { resolveLaunchProfile } from '../config/launch-profiles';
import { mxmedName } from '../utils/naming';
import { BaseMxMedStack } from './base-mxmed-stack';
import type { MxMedContractStackProps } from './base-mxmed-stack';

const APPLICATION_CONTAINER_NAME = 'app';
const MIGRATION_CONTAINER_NAME = 'migration';
const IMAGE_DIGEST_PATTERN = '^sha256:[0-9a-f]{64}$';
const MIGRATION_FAIL_CLOSED_COMMAND = [
  '/bin/sh',
  '-c',
  'echo "migration command is not configured" >&2; exit 78',
] as const;

export interface MxMedComputeStackProps extends MxMedContractStackProps {
  readonly vpc: Vpc;
  readonly privateAppSubnets: readonly ISubnet[];
  readonly applicationSecurityGroup: ISecurityGroup;
  readonly applicationDataKey: IKey;
  readonly auditKey: IKey;
  readonly secretsKey: IKey;
  readonly ecsExecutionRole: IRole;
  readonly applicationTaskRole: IRole;
  readonly migrationTaskRole: IRole;
  readonly sessionSigningSecret: ISecret;
  readonly stripeSecretKeyReference: ISecret;
  readonly stripeWebhookSecretReference: ISecret;
  readonly aiApiKeyReference: ISecret;
  readonly databaseEndpoint: string;
  readonly databasePort: string;
  readonly databaseName: string;
  readonly masterUserSecret: ISecret;
  readonly applicationUserSecret?: ISecret;
  readonly publicMediaBucket: IBucket;
  readonly privateDocumentsBucket: IBucket;
  readonly clinicalRecordsBucket: IBucket;
  readonly uploadQuarantineBucket: IBucket;
  readonly uploadUrlTtlSeconds: number;
  readonly downloadUrlTtlSeconds: number;
  readonly sessionEndpoint: string;
  readonly sessionPort: string;
  readonly sessionAuthSecret: ISecret;
  readonly sessionPrefix: string;
  readonly sessionIdleTtlSeconds: number;
  readonly sessionAbsoluteLifetimeSeconds: number;
  readonly sessionLockEnabled: boolean;
  readonly sessionLockTimeoutSeconds: number;
  readonly sessionLockWaitMicroseconds: number;
  readonly applicationTargetGroup?: IApplicationTargetGroup;
}

/** Profile-aware ECS/Fargate boundary with explicit cost-safe activation modes. */
export class MxMedComputeStack extends BaseMxMedStack {
  public readonly launchCapacity: Readonly<
    Pick<
      MxMedLaunchCapacity,
      | 'computeSizingProfile'
      | 'computeAvailabilityProfile'
      | 'computeDesiredCount'
      | 'computeMinCapacity'
      | 'computeMaxCapacity'
      | 'computeTaskCpuUnits'
      | 'computeTaskMemoryMiB'
      | 'computeArchitecture'
      | 'computeUseSpot'
      | 'computeAssignPublicIp'
    >
  >;
  public readonly applicationRepository?: Repository;
  public readonly cluster?: Cluster;
  public readonly applicationTaskDefinition?: FargateTaskDefinition;
  public readonly migrationTaskDefinition?: FargateTaskDefinition;
  public readonly service?: FargateService;
  public readonly appContainer?: ContainerDefinition;
  public readonly appContainerName?: string;
  public readonly appContainerPort?: number;
  public readonly healthPath?: string;
  public readonly readinessPath?: string;
  public readonly appLogGroup?: LogGroup;
  public readonly migrationLogGroup?: LogGroup;
  public readonly scalingTarget?: ScalableTaskCount;
  public readonly applicationImageDigestParameter?: CfnParameter;
  public readonly activationMode: MxMedComputeStackProps['config']['computeActivationMode'];
  public readonly runtimeCapabilityProfile: MxMedRuntimeCapabilityProfile | null;
  public readonly migrationCommandMode: MxMedComputeStackProps['config']['computeMigrationCommandMode'];

  public constructor(scope: Construct, id: string, props: MxMedComputeStackProps) {
    super(scope, id, {
      ...props,
      component: 'compute',
      description: 'MXMed profile-aware ECS Fargate compute foundation; deployment is external.',
      metadata: { dataClassification: 'internal', criticality: 'high', backup: 'not-required' },
    });

    const { config } = props;
    const capacity = resolveLaunchProfile(
      config.environmentName,
      config.deploymentProfile,
    ).capacity;
    this.launchCapacity = Object.freeze({
      computeSizingProfile: capacity.computeSizingProfile,
      computeAvailabilityProfile: capacity.computeAvailabilityProfile,
      computeDesiredCount: capacity.computeDesiredCount,
      computeMinCapacity: capacity.computeMinCapacity,
      computeMaxCapacity: capacity.computeMaxCapacity,
      computeTaskCpuUnits: capacity.computeTaskCpuUnits,
      computeTaskMemoryMiB: capacity.computeTaskMemoryMiB,
      computeArchitecture: capacity.computeArchitecture,
      computeUseSpot: capacity.computeUseSpot,
      computeAssignPublicIp: capacity.computeAssignPublicIp,
    });
    this.activationMode = config.computeActivationMode;
    this.runtimeCapabilityProfile = config.runtimeCapabilityProfile;
    this.migrationCommandMode = config.computeMigrationCommandMode;

    const computeTagOptions = { priority: 300 };
    Tags.of(this).add('ComputeActivationMode', this.activationMode, computeTagOptions);
    Tags.of(this).add(
      'RuntimeCapabilityProfile',
      this.runtimeCapabilityProfile ?? 'not-applicable',
      computeTagOptions,
    );
    Tags.of(this).add('CostReview', 'required', computeTagOptions);
    Tags.of(this).add(
      'CostTier',
      computeCreatesService(this.activationMode) ? 'fixed-critical' : 'deferred-optional',
      computeTagOptions,
    );
    Tags.of(this).add(
      'SchedulePolicy',
      config.environmentName === 'staging'
        ? 'release-window-v1'
        : computeCreatesService(this.activationMode)
          ? 'always-on-approved'
          : 'always-on',
      computeTagOptions,
    );

    if (!computeCreatesRegistry(this.activationMode)) {
      this.registerGuardrails(config);
      return;
    }

    this.applicationRepository = this.createRepository(props);
    if (!computeCreatesTasks(this.activationMode)) {
      this.registerGuardrails(config);
      return;
    }

    const runtimeCapabilityProfile = this.requireRuntimeCapability();
    const applicationUserSecret = this.requireApplicationUserSecret(props);
    this.applicationImageDigestParameter = new CfnParameter(this, 'ApplicationImageDigest', {
      type: 'String',
      allowedPattern: IMAGE_DIGEST_PATTERN,
      description: 'Immutable MXMed application image digest in sha256:<64 lowercase hex> form.',
    });
    const image = ContainerImage.fromRegistry(
      Fn.join('', [
        this.applicationRepository.repositoryUri,
        '@',
        this.applicationImageDigestParameter.valueAsString,
      ]),
    );

    this.cluster = new Cluster(this, 'ApplicationCluster', {
      vpc: props.vpc as unknown as IVpc,
      clusterName: mxmedName(config.environmentCode, 'application-cluster'),
      containerInsightsV2: ContainerInsights.ENABLED,
    });
    this.appLogGroup = this.createLogGroup(
      'ApplicationLogGroup',
      `/mxmed/${config.environmentName}/compute/app`,
      props.auditKey,
      config.computeLogRetentionDays,
      config.operationsLogProtectionProfile,
    );
    this.migrationLogGroup = this.createLogGroup(
      'MigrationLogGroup',
      `/mxmed/${config.environmentName}/compute/migration`,
      props.auditKey,
      config.computeLogRetentionDays,
      config.operationsLogProtectionProfile,
    );
    this.createExecutionPolicy(props, applicationUserSecret, runtimeCapabilityProfile);
    this.createApplicationPolicy(props, runtimeCapabilityProfile);
    const executionRoleReference = Role.fromRoleArn(
      this,
      'EcsExecutionRoleReference',
      props.ecsExecutionRole.roleArn,
      { mutable: false },
    );
    const applicationTaskRoleReference = Role.fromRoleArn(
      this,
      'ApplicationTaskRoleReference',
      props.applicationTaskRole.roleArn,
      { mutable: false },
    );
    const migrationTaskRoleReference = Role.fromRoleArn(
      this,
      'MigrationTaskRoleReference',
      props.migrationTaskRole.roleArn,
      { mutable: false },
    );

    this.applicationTaskDefinition = this.createApplicationTaskDefinition(
      props,
      image,
      applicationUserSecret,
      runtimeCapabilityProfile,
      executionRoleReference,
      applicationTaskRoleReference,
    );
    const appContainer = this.applicationTaskDefinition.defaultContainer;
    if (appContainer === undefined) {
      throw new Error('MXMED_COMPUTE_APP_CONTAINER_MISSING');
    }
    this.appContainer = appContainer;
    this.appContainerName = APPLICATION_CONTAINER_NAME;
    this.appContainerPort = config.computeContainerPort;
    this.healthPath = config.computeHealthPath;
    this.readinessPath = config.computeReadinessPath;
    this.migrationTaskDefinition = this.createMigrationTaskDefinition(
      props,
      image,
      applicationUserSecret,
      executionRoleReference,
      migrationTaskRoleReference,
    );

    if (computeCreatesService(this.activationMode)) {
      this.service = new FargateService(this, 'ApplicationService', {
        cluster: this.cluster as unknown as ICluster,
        taskDefinition: this.applicationTaskDefinition,
        serviceName: mxmedName(config.environmentCode, 'application-service'),
        desiredCount: config.computeDesiredCount,
        assignPublicIp: false,
        vpcSubnets: { subnets: [...props.privateAppSubnets] },
        securityGroups: [props.applicationSecurityGroup],
        platformVersion: FargatePlatformVersion.VERSION1_4,
        enableExecuteCommand: false,
        circuitBreaker: { rollback: true },
        minHealthyPercent: 100,
        maxHealthyPercent: 200,
        enableECSManagedTags: true,
        propagateTags: PropagatedTagSource.SERVICE,
      });
      if (props.applicationTargetGroup !== undefined) {
        this.service.attachToApplicationTargetGroup(props.applicationTargetGroup);
      }
      this.scalingTarget = this.service.autoScaleTaskCount({
        minCapacity: config.computeMinCapacity,
        maxCapacity: config.computeMaxCapacity,
      });
      this.scalingTarget.scaleOnCpuUtilization('CpuTargetTracking', {
        targetUtilizationPercent: config.computeCpuTargetPercent,
        scaleOutCooldown: Duration.seconds(config.computeScaleOutCooldownSeconds),
        scaleInCooldown: Duration.seconds(config.computeScaleInCooldownSeconds),
      });
      this.scalingTarget.scaleOnMemoryUtilization('MemoryTargetTracking', {
        targetUtilizationPercent: config.computeMemoryTargetPercent,
        scaleOutCooldown: Duration.seconds(config.computeScaleOutCooldownSeconds),
        scaleInCooldown: Duration.seconds(config.computeScaleInCooldownSeconds),
      });
    }

    this.registerGuardrails(config);
  }

  private createRepository(props: MxMedComputeStackProps): Repository {
    const { config } = props;
    return new Repository(this, 'ApplicationRepository', {
      repositoryName: mxmedName(config.environmentCode, 'application'),
      encryption: RepositoryEncryption.KMS,
      encryptionKey: props.applicationDataKey,
      imageScanOnPush: config.computeImageScanOnPush,
      imageTagMutability: TagMutability.IMMUTABLE,
      emptyOnDelete: false,
      removalPolicy: RemovalPolicy.RETAIN,
      lifecycleRules: [
        {
          description: 'Expire untagged images after the profile-specific review window.',
          tagStatus: TagStatus.UNTAGGED,
          maxImageAge: Duration.days(config.computeEcrUntaggedRetentionDays),
        },
        {
          description: 'Retain only the profile-specific maximum image count.',
          tagStatus: TagStatus.ANY,
          maxImageCount: config.computeEcrMaxImageCount,
        },
      ],
    });
  }

  private createLogGroup(
    id: string,
    name: string,
    auditKey: IKey,
    retentionDays: 30 | 90,
    protectionProfile: MxMedComputeStackProps['config']['operationsLogProtectionProfile'],
  ): LogGroup {
    const dataProtectionPolicy =
      protectionProfile === 'targeted-data-protection-v1'
        ? new DataProtectionPolicy({
            name: `${id}-targeted-v1`,
            description: 'Selective MXMed workload log redaction; no account-wide policy.',
            identifiers: [
              DataIdentifier.AWSSECRETKEY,
              DataIdentifier.CREDITCARDNUMBER,
              DataIdentifier.EMAILADDRESS,
              DataIdentifier.PHONENUMBER_US,
              new CustomDataIdentifier('MxMedStripeSecret', 'sk_(live|test)_[A-Za-z0-9]{16,}'),
              new CustomDataIdentifier(
                'MxMedSecureLinkToken',
                'secure[-_]?link[-_]?token[=:][A-Za-z0-9._~-]{16,}',
              ),
            ],
          })
        : undefined;
    const logGroup = new LogGroup(this, id, {
      logGroupName: name,
      encryptionKey: auditKey,
      retention: retentionDays === 30 ? RetentionDays.ONE_MONTH : RetentionDays.THREE_MONTHS,
      removalPolicy: RemovalPolicy.RETAIN,
      ...(dataProtectionPolicy === undefined ? {} : { dataProtectionPolicy }),
    });
    const resource = logGroup.node.defaultChild;
    if (!(resource instanceof CfnLogGroup)) {
      throw new Error('MXMED_COMPUTE_LOG_GROUP_RESOURCE_INVALID');
    }
    resource.applyRemovalPolicy(RemovalPolicy.RETAIN, { applyToUpdateReplacePolicy: true });
    return logGroup;
  }

  private createExecutionPolicy(
    props: MxMedComputeStackProps,
    applicationUserSecret: ISecret,
    capability: MxMedRuntimeCapabilityProfile,
  ): void {
    if (
      this.applicationRepository === undefined ||
      this.appLogGroup === undefined ||
      this.migrationLogGroup === undefined
    ) {
      throw new Error('MXMED_COMPUTE_EXECUTION_POLICY_PREREQUISITE_MISSING');
    }
    const startupSecrets: ISecret[] = [
      props.sessionSigningSecret,
      props.sessionAuthSecret,
      applicationUserSecret,
      props.masterUserSecret,
    ];
    if (capabilityIncludesPaid(capability)) {
      startupSecrets.push(props.stripeSecretKeyReference, props.stripeWebhookSecretReference);
    }
    if (capabilityIncludesAi(capability)) startupSecrets.push(props.aiApiKeyReference);
    const statements = [
      new PolicyStatement({
        effect: Effect.ALLOW,
        actions: ['ecr:GetAuthorizationToken'],
        resources: ['*'],
      }),
      new PolicyStatement({
        effect: Effect.ALLOW,
        actions: [
          'ecr:BatchCheckLayerAvailability',
          'ecr:GetDownloadUrlForLayer',
          'ecr:BatchGetImage',
        ],
        resources: [this.applicationRepository.repositoryArn],
      }),
      new PolicyStatement({
        effect: Effect.ALLOW,
        actions: ['logs:CreateLogStream', 'logs:PutLogEvents'],
        resources: [`${this.appLogGroup.logGroupArn}:*`, `${this.migrationLogGroup.logGroupArn}:*`],
      }),
      new PolicyStatement({
        effect: Effect.ALLOW,
        actions: ['secretsmanager:DescribeSecret', 'secretsmanager:GetSecretValue'],
        resources: startupSecrets.map((secret) => secret.secretArn),
      }),
      new PolicyStatement({
        effect: Effect.ALLOW,
        actions: ['kms:Decrypt', 'kms:DescribeKey'],
        resources: [
          props.applicationDataKey.keyArn,
          props.auditKey.keyArn,
          props.secretsKey.keyArn,
        ],
      }),
    ];
    new CfnPolicy(this, 'EcsExecutionRuntimePolicy', {
      policyName: mxmedName(props.config.environmentCode, 'ecs-execution-runtime-policy', 128),
      roles: [props.ecsExecutionRole.roleName],
      policyDocument: new PolicyDocument({ statements }).toJSON() as unknown,
    });
  }

  private createApplicationPolicy(
    props: MxMedComputeStackProps,
    capability: MxMedRuntimeCapabilityProfile,
  ): void {
    const objectStatements = [
      new PolicyStatement({
        effect: Effect.ALLOW,
        actions: ['s3:GetObject', 's3:PutObject'],
        resources: [props.uploadQuarantineBucket.arnForObjects('*')],
      }),
      new PolicyStatement({
        effect: Effect.ALLOW,
        actions: ['s3:GetObject'],
        resources: [props.publicMediaBucket.arnForObjects('*')],
      }),
    ];
    if (capabilityIncludesClinical(capability)) {
      objectStatements.push(
        new PolicyStatement({
          effect: Effect.ALLOW,
          actions: ['s3:GetObject'],
          resources: [
            props.privateDocumentsBucket.arnForObjects('authorized/*'),
            props.clinicalRecordsBucket.arnForObjects('authorized/*'),
          ],
        }),
      );
    }
    const statements = [
      ...objectStatements,
      new PolicyStatement({
        effect: Effect.ALLOW,
        actions: ['cloudwatch:PutMetricData'],
        resources: ['*'],
        conditions: { StringEquals: { 'cloudwatch:namespace': 'MxMed/Application' } },
      }),
    ];
    new CfnPolicy(this, 'ApplicationTaskDataPolicy', {
      policyName: mxmedName(props.config.environmentCode, 'application-data-policy', 128),
      roles: [props.applicationTaskRole.roleName],
      policyDocument: new PolicyDocument({ statements }).toJSON() as unknown,
    });
  }

  private createApplicationTaskDefinition(
    props: MxMedComputeStackProps,
    image: ContainerImage,
    applicationUserSecret: ISecret,
    capability: MxMedRuntimeCapabilityProfile,
    executionRole: IRole,
    taskRole: IRole,
  ): FargateTaskDefinition {
    const { config } = props;
    if (this.appLogGroup === undefined) throw new Error('MXMED_COMPUTE_APP_LOG_GROUP_MISSING');
    const task = new FargateTaskDefinition(this, 'ApplicationTaskDefinition', {
      family: mxmedName(config.environmentCode, 'application'),
      cpu: config.computeTaskCpuUnits,
      memoryLimitMiB: config.computeTaskMemoryMiB,
      executionRole,
      taskRole,
      runtimePlatform: {
        cpuArchitecture: CpuArchitecture.X86_64,
        operatingSystemFamily: OperatingSystemFamily.LINUX,
      },
    });
    for (const name of ['tmp', 'apache-run', 'apache-lock']) task.addVolume({ name });
    const linuxParameters = this.createLinuxParameters('ApplicationLinuxParameters');
    const container = task.addContainer('ApplicationContainer', {
      containerName: APPLICATION_CONTAINER_NAME,
      image,
      essential: true,
      cpu: config.computeTaskCpuUnits,
      memoryReservationMiB: Math.floor(config.computeTaskMemoryMiB * 0.75),
      memoryLimitMiB: config.computeTaskMemoryMiB,
      readonlyRootFilesystem: config.computeReadonlyRootFilesystem,
      privileged: false,
      interactive: false,
      pseudoTerminal: false,
      user: 'www-data',
      linuxParameters,
      logging: LogDrivers.awsLogs({
        logGroup: this.appLogGroup,
        streamPrefix: 'app',
      }),
      environment: this.applicationEnvironment(props, capability),
      secrets: this.applicationSecrets(props, applicationUserSecret, capability),
      healthCheck: {
        command: [
          'CMD-SHELL',
          `curl --fail --silent --show-error http://127.0.0.1:${String(config.computeContainerPort)}${config.computeHealthPath} >/dev/null || exit 1`,
        ],
        interval: Duration.seconds(15),
        timeout: Duration.seconds(5),
        retries: 3,
        startPeriod: Duration.seconds(60),
      },
      portMappings: [{ containerPort: config.computeContainerPort, protocol: Protocol.TCP }],
    });
    container.addMountPoints(
      { sourceVolume: 'tmp', containerPath: '/tmp', readOnly: false },
      { sourceVolume: 'apache-run', containerPath: '/var/run/apache2', readOnly: false },
      { sourceVolume: 'apache-lock', containerPath: '/var/lock/apache2', readOnly: false },
    );
    return task;
  }

  private createMigrationTaskDefinition(
    props: MxMedComputeStackProps,
    image: ContainerImage,
    applicationUserSecret: ISecret,
    executionRole: IRole,
    taskRole: IRole,
  ): FargateTaskDefinition {
    const { config } = props;
    if (this.migrationLogGroup === undefined) {
      throw new Error('MXMED_COMPUTE_MIGRATION_LOG_GROUP_MISSING');
    }
    const task = new FargateTaskDefinition(this, 'MigrationTaskDefinition', {
      family: mxmedName(config.environmentCode, 'migration'),
      cpu: config.computeTaskCpuUnits,
      memoryLimitMiB: config.computeTaskMemoryMiB,
      executionRole,
      taskRole,
      runtimePlatform: {
        cpuArchitecture: CpuArchitecture.X86_64,
        operatingSystemFamily: OperatingSystemFamily.LINUX,
      },
    });
    task.addVolume({ name: 'migration-tmp' });
    const container = task.addContainer('MigrationContainer', {
      containerName: MIGRATION_CONTAINER_NAME,
      image,
      essential: true,
      command: [...MIGRATION_FAIL_CLOSED_COMMAND],
      readonlyRootFilesystem: true,
      privileged: false,
      interactive: false,
      pseudoTerminal: false,
      user: 'www-data',
      linuxParameters: this.createLinuxParameters('MigrationLinuxParameters'),
      logging: LogDrivers.awsLogs({
        logGroup: this.migrationLogGroup,
        streamPrefix: 'migration',
      }),
      environment: {
        APP_ENV: config.environmentName,
        APP_DEBUG: 'false',
        APP_TIMEZONE: 'UTC',
        AWS_REGION: config.primaryRegion,
        DB_HOST: props.databaseEndpoint,
        DB_PORT: props.databasePort,
        DB_NAME: props.databaseName,
        LOG_FORMAT: 'json',
      },
      secrets: {
        DB_MASTER_USERNAME: EcsSecret.fromSecretsManager(props.masterUserSecret, 'username'),
        DB_MASTER_PASSWORD: EcsSecret.fromSecretsManager(props.masterUserSecret, 'password'),
        DB_USERNAME: EcsSecret.fromSecretsManager(applicationUserSecret, 'username'),
        DB_PASSWORD: EcsSecret.fromSecretsManager(applicationUserSecret, 'password'),
      },
    });
    container.addMountPoints({
      sourceVolume: 'migration-tmp',
      containerPath: '/tmp',
      readOnly: false,
    });
    return task;
  }

  private createLinuxParameters(id: string): LinuxParameters {
    const parameters = new LinuxParameters(this, id, { initProcessEnabled: true });
    parameters.dropCapabilities(Capability.ALL);
    return parameters;
  }

  private applicationEnvironment(
    props: MxMedComputeStackProps,
    capability: MxMedRuntimeCapabilityProfile,
  ): Record<string, string> {
    const { config } = props;
    const environment: Record<string, string> = {
      APP_ENV: config.environmentName,
      APP_DEBUG: 'false',
      APP_TIMEZONE: 'UTC',
      AWS_REGION: config.primaryRegion,
      DB_HOST: props.databaseEndpoint,
      DB_PORT: props.databasePort,
      DB_NAME: props.databaseName,
      SESSION_HOST: props.sessionEndpoint,
      SESSION_PORT: props.sessionPort,
      SESSION_PREFIX: props.sessionPrefix,
      SESSION_IDLE_TTL: String(props.sessionIdleTtlSeconds),
      SESSION_ABSOLUTE_LIFETIME: String(props.sessionAbsoluteLifetimeSeconds),
      SESSION_LOCK_ENABLED: String(props.sessionLockEnabled),
      SESSION_LOCK_TIMEOUT_SECONDS: String(props.sessionLockTimeoutSeconds),
      SESSION_LOCK_WAIT_MICROSECONDS: String(props.sessionLockWaitMicroseconds),
      PUBLIC_MEDIA_BUCKET: props.publicMediaBucket.bucketName,
      UPLOAD_QUARANTINE_BUCKET: props.uploadQuarantineBucket.bucketName,
      UPLOAD_URL_TTL: String(props.uploadUrlTtlSeconds),
      DOWNLOAD_URL_TTL: String(props.downloadUrlTtlSeconds),
      LOG_FORMAT: 'json',
      RUNTIME_CAPABILITY_PROFILE: capability,
    };
    if (capabilityIncludesPaid(capability)) {
      environment.STRIPE_MODE = config.environmentName === 'production' ? 'live' : 'test';
    }
    if (capabilityIncludesClinical(capability)) {
      environment.PRIVATE_DOCUMENTS_BUCKET = props.privateDocumentsBucket.bucketName;
      environment.CLINICAL_RECORDS_BUCKET = props.clinicalRecordsBucket.bucketName;
    }
    return environment;
  }

  private applicationSecrets(
    props: MxMedComputeStackProps,
    applicationUserSecret: ISecret,
    capability: MxMedRuntimeCapabilityProfile,
  ): Record<string, EcsSecret> {
    const secrets: Record<string, EcsSecret> = {
      SESSION_SIGNING_KEY: EcsSecret.fromSecretsManager(props.sessionSigningSecret),
      SESSION_STORE_USERNAME: EcsSecret.fromSecretsManager(props.sessionAuthSecret, 'username'),
      SESSION_STORE_PASSWORD: EcsSecret.fromSecretsManager(props.sessionAuthSecret, 'password'),
      DB_USERNAME: EcsSecret.fromSecretsManager(applicationUserSecret, 'username'),
      DB_PASSWORD: EcsSecret.fromSecretsManager(applicationUserSecret, 'password'),
    };
    if (capabilityIncludesPaid(capability)) {
      secrets.STRIPE_SECRET_KEY = EcsSecret.fromSecretsManager(props.stripeSecretKeyReference);
      secrets.STRIPE_WEBHOOK_SECRET = EcsSecret.fromSecretsManager(
        props.stripeWebhookSecretReference,
      );
    }
    if (capabilityIncludesAi(capability)) {
      secrets.AI_API_KEY = EcsSecret.fromSecretsManager(props.aiApiKeyReference);
    }
    return secrets;
  }

  private requireRuntimeCapability(): MxMedRuntimeCapabilityProfile {
    if (this.runtimeCapabilityProfile === null) {
      throw new Error('MXMED_RUNTIME_CAPABILITY_PROFILE_REQUIRED');
    }
    return this.runtimeCapabilityProfile;
  }

  private requireApplicationUserSecret(props: MxMedComputeStackProps): ISecret {
    if (props.applicationUserSecret === undefined) {
      throw new Error('MXMED_APPLICATION_DB_SECRET_REQUIRED');
    }
    return props.applicationUserSecret;
  }

  private registerGuardrails(config: MxMedComputeStackProps['config']): void {
    Aspects.of(this).add(new ComputeFoundationAspect(config), {
      priority: AspectPriority.READONLY,
    });
  }
}
