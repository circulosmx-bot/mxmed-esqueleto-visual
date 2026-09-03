import {
  ArnFormat,
  AspectPriority,
  Aspects,
  CfnParameter,
  Duration,
  RemovalPolicy,
  Tags,
} from 'aws-cdk-lib';
import { CfnTrail, ReadWriteType, Trail } from 'aws-cdk-lib/aws-cloudtrail';
import {
  Effect,
  ManagedPolicy,
  PolicyStatement,
  Role,
  ServicePrincipal,
} from 'aws-cdk-lib/aws-iam';
import { CfnKey, Key, KeySpec, KeyUsage } from 'aws-cdk-lib/aws-kms';
import { LogGroup, RetentionDays } from 'aws-cdk-lib/aws-logs';
import { BlockPublicAccess, Bucket, BucketEncryption, ObjectOwnership } from 'aws-cdk-lib/aws-s3';
import type { IBucket } from 'aws-cdk-lib/aws-s3';
import { Secret } from 'aws-cdk-lib/aws-secretsmanager';
import type { ISecret } from 'aws-cdk-lib/aws-secretsmanager';
import type { Construct } from 'constructs';

import { BaseMxMedStack } from './base-mxmed-stack';
import type { MxMedContractStackProps } from './base-mxmed-stack';
import { LeastPrivilegeIamAspect } from '../aspects/least-privilege-iam-aspect';
import { NoPlaintextSecretAspect } from '../aspects/no-plaintext-secret-aspect';
import { SecurityFoundationAspect } from '../aspects/security-foundation-aspect';
import { MxMedSecurityRoleFactory, SecuritySecretContainer } from '../constructs';
import { MXMED_C3_RUNNER_CONTRACT } from '../constructs/c3-runner-contract';
import type { MxMedEnvironmentConfig } from '../config/environment-config';
import { mxmedName } from '../utils/naming';
import {
  mxmedBoundaryName,
  mxmedCloudTrailLogGroupName,
  mxmedSecurityKeyAlias,
  mxmedSecuritySecretName,
} from '../utils/security-naming';
import { registerMxMedSecurityValidation } from '../utils/security-validation';
import { edgeUsesPublicMedia } from '../config/edge-config';
import { MXMED_SES_DOMAIN_IDENTITY, MXMED_SES_REGION } from '../config/email-config';

function cloudTrailRetention(days: number): RetentionDays {
  return days === 90 ? RetentionDays.THREE_MONTHS : RetentionDays.ONE_YEAR;
}

export interface MxMedSecurityStackProps extends MxMedContractStackProps {
  readonly c3RunnerLogGroupKmsAccess?: boolean;
}

/** KMS, Secrets Manager, IAM boundaries/roles and management-audit foundation. */
export class MxMedSecurityStack extends BaseMxMedStack {
  public readonly applicationDataKey: Key;
  public readonly secretsKey: Key;
  public readonly auditKey: Key;
  public readonly backupKey: Key;
  public readonly auditBucket: Bucket;
  public readonly cloudTrailLogGroup: LogGroup;
  public readonly managementTrail: Trail;
  public readonly workloadBoundary: ManagedPolicy;
  public readonly deploymentBoundary: ManagedPolicy;
  public readonly sessionSigningSecret: Secret;
  public readonly stripeSecretKeyReference: ISecret;
  public readonly stripeWebhookSecretReference: ISecret;
  public readonly aiApiKeyReference: ISecret;
  public readonly ecsExecutionRole: Role;
  public readonly applicationTaskRole: Role;
  public readonly migrationTaskRole: Role;
  public readonly jobsTaskRole: Role;
  public readonly workloadRoleFactory: MxMedSecurityRoleFactory;

  public constructor(scope: Construct, id: string, props: MxMedSecurityStackProps) {
    super(scope, id, {
      ...props,
      component: 'security',
      description: 'MXMed KMS, secrets, least-privilege IAM and management audit foundation.',
      metadata: { dataClassification: 'sensitive', criticality: 'high', backup: 'required' },
    });

    const { config } = props;
    const isC3Ephemeral = props.c3AuditBucketName !== undefined;
    this.applicationDataKey = this.createKey(
      'ApplicationDataKey',
      mxmedSecurityKeyAlias(config.environmentCode, 'application-data'),
      'MXMed application data encryption key.',
      config,
    );
    if (edgeUsesPublicMedia(config)) {
      const distributionArn = new CfnParameter(this, 'PublicMediaCloudFrontDistributionArn', {
        type: 'String',
        allowedPattern: '^arn:[^:]+:cloudfront::[0-9]{12}:distribution/[A-Z0-9]+$',
        description: 'CloudFront distribution ARN captured through the approved edge handoff.',
      });
      this.applicationDataKey.addToResourcePolicy(
        new PolicyStatement({
          sid: 'AllowCloudFrontPublicMediaDataKeyUse',
          effect: Effect.ALLOW,
          principals: [new ServicePrincipal('cloudfront.amazonaws.com')],
          actions: ['kms:Decrypt', 'kms:Encrypt', 'kms:GenerateDataKey*'],
          resources: ['*'],
          conditions: {
            StringEquals: { 'AWS:SourceArn': distributionArn.valueAsString },
          },
        }),
      );
    }
    this.secretsKey = this.createKey(
      'SecretsKey',
      mxmedSecurityKeyAlias(config.environmentCode, 'secrets'),
      'MXMed Secrets Manager encryption key.',
      config,
    );
    this.auditKey = this.createKey(
      'AuditKey',
      mxmedSecurityKeyAlias(config.environmentCode, 'audit'),
      'MXMed audit evidence encryption key.',
      config,
    );
    this.backupKey = this.createKey(
      'BackupKey',
      mxmedSecurityKeyAlias(config.environmentCode, 'backup'),
      'MXMed protected backup encryption key.',
      config,
    );
    this.addSecretsManagerKeyPolicy(config);
    const managementTrailName = mxmedName(config.environmentCode, 'management-trail');
    this.addAuditKeyPolicies(config, managementTrailName);
    if (props.c3RunnerLogGroupKmsAccess === true) {
      this.addC3RunnerLogGroupAuditKeyPolicy(config);
    }

    this.sessionSigningSecret = new Secret(this, 'SessionSigningSecret', {
      secretName: mxmedSecuritySecretName(config.environmentName, 'application/session-signing'),
      description: 'MXMed generated session-signing material; no value is versioned.',
      encryptionKey: this.secretsKey,
      generateSecretString: { passwordLength: 64 },
    });
    this.sessionSigningSecret.applyRemovalPolicy(RemovalPolicy.RETAIN);

    const stripeSecret = new SecuritySecretContainer(this, 'StripeSecretKeyContainer', {
      environmentName: config.environmentName,
      path: 'providers/stripe/secret-key',
      encryptionKey: this.secretsKey,
      description: 'MXMed external Stripe API credential container; initially empty.',
    });
    const stripeWebhook = new SecuritySecretContainer(this, 'StripeWebhookSecretContainer', {
      environmentName: config.environmentName,
      path: 'providers/stripe/webhook-secret',
      encryptionKey: this.secretsKey,
      description: 'MXMed external Stripe webhook credential container; initially empty.',
    });
    const aiApiKey = new SecuritySecretContainer(this, 'AiApiKeyContainer', {
      environmentName: config.environmentName,
      path: 'providers/ai/api-key',
      encryptionKey: this.secretsKey,
      description: 'MXMed external AI provider credential container; initially empty.',
    });
    this.stripeSecretKeyReference = stripeSecret.secret;
    this.stripeWebhookSecretReference = stripeWebhook.secret;
    this.aiApiKeyReference = aiApiKey.secret;

    this.workloadBoundary = this.createWorkloadBoundary(config, isC3Ephemeral);
    this.deploymentBoundary = this.createDeploymentBoundary(config);
    this.workloadRoleFactory = new MxMedSecurityRoleFactory(
      config.environmentName,
      config.environmentCode,
      this.workloadBoundary,
    );
    this.ecsExecutionRole = this.workloadRoleFactory.createWorkloadRole(
      this,
      'EcsExecutionRole',
      'ecs-execution',
      'MXMed ECS execution role; only approved startup secrets are granted in this phase.',
    );
    this.applicationTaskRole = this.workloadRoleFactory.createWorkloadRole(
      this,
      'ApplicationTaskRole',
      'application',
      'MXMed application task role with exact transactional email authority.',
    );
    const sesIdentityArn = this.formatArn({
      service: 'ses',
      region: MXMED_SES_REGION,
      resource: 'identity',
      resourceName: MXMED_SES_DOMAIN_IDENTITY,
    });
    if (!isC3Ephemeral) {
      this.applicationTaskRole.addToPolicy(
        new PolicyStatement({
          sid: 'AllowTransactionalEmailSending',
          actions: ['ses:SendEmail'],
          resources: [sesIdentityArn],
        }),
      );
    }
    this.migrationTaskRole = this.workloadRoleFactory.createWorkloadRole(
      this,
      'MigrationTaskRole',
      'migration',
      'MXMed migration task role; database permissions are intentionally deferred.',
    );
    this.jobsTaskRole = this.workloadRoleFactory.createWorkloadRole(
      this,
      'JobsTaskRole',
      'jobs',
      'MXMed jobs task role; each future job receives explicit owner grants.',
    );
    this.auditBucket = new Bucket(this, 'AuditBucket', {
      ...(props.c3AuditBucketName === undefined ? {} : { bucketName: props.c3AuditBucketName }),
      blockPublicAccess: BlockPublicAccess.BLOCK_ALL,
      objectOwnership: ObjectOwnership.BUCKET_OWNER_ENFORCED,
      versioned: true,
      enforceSSL: true,
      encryption: BucketEncryption.KMS,
      encryptionKey: this.auditKey,
      bucketKeyEnabled: true,
      autoDeleteObjects: false,
      removalPolicy: RemovalPolicy.RETAIN,
      lifecycleRules: [
        {
          id: 'ContractualAuditRetention',
          enabled: true,
          expiration: Duration.days(config.auditArchiveRetentionDays),
          noncurrentVersionExpiration: Duration.days(config.auditArchiveRetentionDays),
        },
      ],
    });
    Tags.of(this.auditBucket).add('DataClassification', 'internal', { priority: 200 });
    this.cloudTrailLogGroup = new LogGroup(this, 'CloudTrailLogGroup', {
      logGroupName: mxmedCloudTrailLogGroupName(config.environmentName),
      encryptionKey: this.auditKey,
      retention: cloudTrailRetention(config.cloudTrailLogRetentionDays),
      removalPolicy: RemovalPolicy.RETAIN,
    });
    Tags.of(this.cloudTrailLogGroup).add('DataClassification', 'internal', { priority: 200 });
    const cloudTrailLogsRole = isC3Ephemeral
      ? new Role(this, 'CloudTrailLogsRole', {
          roleName: mxmedName(config.environmentCode, 'cloudtrail-logs-role', 64),
          assumedBy: new ServicePrincipal('cloudtrail.amazonaws.com').withConditions({
            StringEquals: { 'aws:SourceAccount': this.account },
            ArnLike: {
              'aws:SourceArn': this.formatArn({
                service: 'cloudtrail',
                resource: 'trail',
                resourceName: managementTrailName,
              }),
            },
          }),
          permissionsBoundary: this.workloadBoundary,
          description: 'MXMed C3 CloudTrail delivery to the exact staging security log group.',
        })
      : undefined;
    cloudTrailLogsRole?.addToPolicy(
      new PolicyStatement({
        actions: ['logs:CreateLogStream', 'logs:PutLogEvents'],
        resources: [this.cloudTrailLogGroup.logGroupArn],
      }),
    );
    this.managementTrail = new Trail(this, 'ManagementTrail', {
      trailName: managementTrailName,
      // CDK 2.260.0 models Bucket/IBucket incompatibly with exactOptionalPropertyTypes.
      bucket: this.auditBucket as unknown as IBucket,
      encryptionKey: this.auditKey,
      ...(isC3Ephemeral ? {} : { cloudWatchLogGroup: this.cloudTrailLogGroup }),
      sendToCloudWatchLogs: !isC3Ephemeral,
      isMultiRegionTrail: true,
      includeGlobalServiceEvents: true,
      enableFileValidation: true,
      managementEvents: ReadWriteType.ALL,
    });
    if (cloudTrailLogsRole !== undefined) {
      const cfnTrail = this.managementTrail.node.defaultChild;
      if (!(cfnTrail instanceof CfnTrail)) {
        throw new Error('MXMED_C3_CLOUDTRAIL_ESCAPE_HATCH_INVALID');
      }
      cfnTrail.cloudWatchLogsLogGroupArn = this.cloudTrailLogGroup.logGroupArn;
      cfnTrail.cloudWatchLogsRoleArn = cloudTrailLogsRole.roleArn;
      cfnTrail.node.addDependency(cloudTrailLogsRole);
    }
    Tags.of(this.managementTrail).add('DataClassification', 'internal', { priority: 200 });

    Aspects.of(this).add(new SecurityFoundationAspect(config), {
      priority: AspectPriority.READONLY,
    });
    Aspects.of(this).add(new NoPlaintextSecretAspect(config.environmentName), {
      priority: AspectPriority.READONLY,
    });
    Aspects.of(this).add(new LeastPrivilegeIamAspect(), {
      priority: AspectPriority.READONLY,
    });
    registerMxMedSecurityValidation(this, config);
  }

  private createKey(
    id: string,
    alias: string,
    description: string,
    config: MxMedEnvironmentConfig,
  ): Key {
    const key = new Key(this, id, {
      alias,
      description,
      keySpec: KeySpec.SYMMETRIC_DEFAULT,
      keyUsage: KeyUsage.ENCRYPT_DECRYPT,
      enableKeyRotation: config.enableKeyRotation,
      multiRegion: false,
      pendingWindow: Duration.days(config.kmsDeletionWindowDays),
      removalPolicy: RemovalPolicy.RETAIN,
    });
    const resource = key.node.defaultChild;
    if (!(resource instanceof CfnKey)) throw new Error('MXMED_SECURITY_KMS_RESOURCE_INVALID');
    resource.bypassPolicyLockoutSafetyCheck = false;
    resource.applyRemovalPolicy(RemovalPolicy.RETAIN);
    return key;
  }

  private addSecretsManagerKeyPolicy(config: MxMedEnvironmentConfig): void {
    this.secretsKey.addToResourcePolicy(
      new PolicyStatement({
        sid: 'AllowSecretsManagerUseWithinEnvironment',
        effect: Effect.ALLOW,
        principals: [new ServicePrincipal('secretsmanager.amazonaws.com')],
        actions: [
          'kms:Encrypt',
          'kms:Decrypt',
          'kms:ReEncrypt*',
          'kms:GenerateDataKey*',
          'kms:DescribeKey',
        ],
        resources: ['*'],
        conditions: {
          StringEquals: {
            'kms:CallerAccount': this.account,
            'kms:ViaService': `secretsmanager.${config.primaryRegion}.${this.urlSuffix}`,
          },
        },
      }),
    );
  }

  private addAuditKeyPolicies(config: MxMedEnvironmentConfig, trailName: string): void {
    const trailArn = this.formatArn({
      service: 'cloudtrail',
      resource: 'trail',
      resourceName: trailName,
    });
    const logGroupArn = this.formatArn({
      service: 'logs',
      resource: 'log-group',
      resourceName: mxmedCloudTrailLogGroupName(config.environmentName),
      arnFormat: ArnFormat.COLON_RESOURCE_NAME,
    });
    this.auditKey.addToResourcePolicy(
      new PolicyStatement({
        sid: 'AllowCloudTrailAuditEncryption',
        principals: [new ServicePrincipal('cloudtrail.amazonaws.com')],
        actions: ['kms:GenerateDataKey*', 'kms:DescribeKey'],
        resources: ['*'],
        conditions: { StringEquals: { 'aws:SourceArn': trailArn } },
      }),
    );
    this.auditKey.addToResourcePolicy(
      new PolicyStatement({
        sid: 'AllowCloudWatchLogsAuditEncryption',
        principals: [new ServicePrincipal(`logs.${config.primaryRegion}.${this.urlSuffix}`)],
        actions: [
          'kms:Encrypt',
          'kms:Decrypt',
          'kms:ReEncrypt*',
          'kms:GenerateDataKey*',
          'kms:DescribeKey',
        ],
        resources: ['*'],
        conditions: {
          ArnEquals: { 'kms:EncryptionContext:aws:logs:arn': logGroupArn },
        },
      }),
    );
  }

  private addC3RunnerLogGroupAuditKeyPolicy(config: MxMedEnvironmentConfig): void {
    const runnerLogGroupArn = this.formatArn({
      service: 'logs',
      resource: 'log-group',
      resourceName: MXMED_C3_RUNNER_CONTRACT.logGroupName,
      arnFormat: ArnFormat.COLON_RESOURCE_NAME,
    });
    this.auditKey.addToResourcePolicy(
      new PolicyStatement({
        sid: 'AllowC3RunnerCloudWatchLogsEncryption',
        principals: [new ServicePrincipal(`logs.${config.primaryRegion}.${this.urlSuffix}`)],
        actions: [
          'kms:Encrypt',
          'kms:Decrypt',
          'kms:ReEncrypt*',
          'kms:GenerateDataKey*',
          'kms:DescribeKey',
        ],
        resources: ['*'],
        conditions: {
          ArnEquals: { 'kms:EncryptionContext:aws:logs:arn': runnerLogGroupArn },
        },
      }),
    );
  }

  private createWorkloadBoundary(
    config: MxMedEnvironmentConfig,
    isC3Ephemeral: boolean,
  ): ManagedPolicy {
    const environmentSecretArn = this.formatArn({
      service: 'secretsmanager',
      resource: 'secret',
      resourceName: `/mxmed/${config.environmentName}/*`,
      arnFormat: ArnFormat.COLON_RESOURCE_NAME,
    });
    const oppositeEnvironment = config.environmentName === 'staging' ? 'production' : 'staging';
    const oppositeSecretArn = this.formatArn({
      service: 'secretsmanager',
      resource: 'secret',
      resourceName: `/mxmed/${oppositeEnvironment}/*`,
      arnFormat: ArnFormat.COLON_RESOURCE_NAME,
    });
    const logArn = this.formatArn({
      service: 'logs',
      resource: 'log-group',
      resourceName: `/mxmed/${config.environmentName}/*`,
      arnFormat: ArnFormat.COLON_RESOURCE_NAME,
    });
    const repositoryArn = this.formatArn({
      service: 'ecr',
      resource: 'repository',
      resourceName: `mxmed-${config.environmentCode}-*`,
    });
    const bucketArn = this.formatArn({
      service: 's3',
      region: '',
      account: '',
      resource: `mxmed-${config.environmentCode}-*`,
    });
    const sesIdentityArn = this.formatArn({
      service: 'ses',
      region: MXMED_SES_REGION,
      resource: 'identity',
      resourceName: MXMED_SES_DOMAIN_IDENTITY,
    });
    return new ManagedPolicy(this, 'WorkloadBoundary', {
      managedPolicyName: mxmedBoundaryName(config.environmentCode, 'workload'),
      description: 'Maximum data-plane permissions for MXMed workload roles.',
      statements: [
        new PolicyStatement({
          sid: 'AllowEcrAuthorizationInPrimaryRegion',
          actions: ['ecr:GetAuthorizationToken'],
          resources: ['*'],
          conditions: { StringEquals: { 'aws:RequestedRegion': config.primaryRegion } },
        }),
        new PolicyStatement({
          sid: 'AllowEcrRepositoryPull',
          actions: [
            'ecr:BatchCheckLayerAvailability',
            'ecr:GetDownloadUrlForLayer',
            'ecr:BatchGetImage',
          ],
          resources: [repositoryArn],
        }),
        new PolicyStatement({
          sid: 'AllowEnvironmentLogs',
          actions: ['logs:CreateLogStream', 'logs:DescribeLogStreams', 'logs:PutLogEvents'],
          resources: [logArn],
        }),
        new PolicyStatement({
          sid: 'AllowEnvironmentSecrets',
          actions: ['secretsmanager:DescribeSecret', 'secretsmanager:GetSecretValue'],
          resources: [environmentSecretArn],
        }),
        new PolicyStatement({
          sid: 'AllowApprovedKeyUse',
          actions: ['kms:Decrypt', 'kms:DescribeKey'],
          resources: ['*'],
          conditions: {
            StringEquals: { 'aws:ResourceTag/Environment': config.environmentName },
          },
        }),
        new PolicyStatement({
          sid: 'AllowFutureEnvironmentStorageGrants',
          actions: ['s3:ListBucket', 's3:GetObject', 's3:PutObject', 's3:DeleteObject'],
          resources: [bucketArn, `${bucketArn}/*`],
        }),
        new PolicyStatement({
          sid: 'AllowContractedEmailSending',
          actions: isC3Ephemeral ? ['ses:SendEmail', 'ses:SendRawEmail'] : ['ses:SendEmail'],
          resources: isC3Ephemeral
            ? [
                this.formatArn({
                  service: 'ses',
                  region: config.emailRegion,
                  resource: 'identity',
                  resourceName: '*',
                }),
              ]
            : [sesIdentityArn],
        }),
        new PolicyStatement({
          sid: 'AllowNamespacedMetrics',
          actions: ['cloudwatch:PutMetricData'],
          resources: ['*'],
          conditions: {
            StringEquals: { 'cloudwatch:namespace': `MxMed/${config.environmentName}` },
          },
        }),
        new PolicyStatement({
          sid: 'DenyControlPlaneAndPrivilegeEscalation',
          effect: Effect.DENY,
          actions: [
            'iam:*',
            'iam:PassRole',
            'organizations:*',
            'account:*',
            'cloudformation:*',
            'sts:AssumeRole',
            'kms:CreateKey',
            'kms:ScheduleKeyDeletion',
            'kms:DisableKey',
            'kms:PutKeyPolicy',
            'kms:CreateGrant',
            'cloudtrail:StopLogging',
            'cloudtrail:DeleteTrail',
            'logs:DeleteLogGroup',
            'logs:DeleteRetentionPolicy',
            'secretsmanager:CreateSecret',
            'secretsmanager:DeleteSecret',
            'secretsmanager:PutResourcePolicy',
            'secretsmanager:UpdateSecret',
            'secretsmanager:RotateSecret',
            's3:DeleteBucket',
          ],
          resources: ['*'],
        }),
        new PolicyStatement({
          sid: 'DenyOppositeEnvironmentSecrets',
          effect: Effect.DENY,
          actions: ['secretsmanager:*'],
          resources: [oppositeSecretArn],
        }),
        new PolicyStatement({
          sid: 'DenyOppositeEnvironmentTaggedResources',
          effect: Effect.DENY,
          actions: ['kms:Decrypt', 's3:*', 'secretsmanager:*'],
          resources: ['*'],
          conditions: {
            StringEquals: { 'aws:ResourceTag/Environment': oppositeEnvironment },
          },
        }),
      ],
    });
  }

  private createDeploymentBoundary(config: MxMedEnvironmentConfig): ManagedPolicy {
    const mxmedRoleArn = this.formatArn({
      service: 'iam',
      region: '',
      resource: 'role',
      resourceName: `mxmed-${config.environmentCode}-*`,
    });
    const mxmedPolicyArn = this.formatArn({
      service: 'iam',
      region: '',
      resource: 'policy',
      resourceName: `mxmed-${config.environmentCode}-*`,
    });
    const oppositeEnvironment = config.environmentName === 'staging' ? 'production' : 'staging';

    return new ManagedPolicy(this, 'DeploymentBoundary', {
      managedPolicyName: mxmedBoundaryName(config.environmentCode, 'deployment'),
      description: 'Maximum permissions for future MXMed OIDC deployment roles.',
      statements: [
        new PolicyStatement({
          sid: 'AllowContractedRegionalStackServices',
          actions: [
            'cloudformation:*',
            'ec2:*',
            'ecr:*',
            'ecs:*',
            'elasticloadbalancing:*',
            'application-autoscaling:*',
            'autoscaling:*',
            'rds:*',
            'elasticache:*',
            's3:*',
            'secretsmanager:*',
            'logs:*',
            'cloudwatch:*',
            'cloudtrail:*',
            'backup:*',
            'events:*',
            'scheduler:*',
            'sqs:*',
            'sns:*',
          ],
          resources: ['*'],
          conditions: {
            StringEquals: {
              'aws:RequestedRegion': [config.primaryRegion, config.emailRegion],
            },
          },
        }),
        new PolicyStatement({
          sid: 'AllowContractedKmsAdministrationWithoutDeletion',
          actions: [
            'kms:CreateKey',
            'kms:DescribeKey',
            'kms:GetKeyPolicy',
            'kms:PutKeyPolicy',
            'kms:EnableKeyRotation',
            'kms:CreateAlias',
            'kms:UpdateAlias',
            'kms:DeleteAlias',
            'kms:TagResource',
            'kms:UntagResource',
            'kms:ListResourceTags',
            'kms:CreateGrant',
            'kms:RetireGrant',
            'kms:RevokeGrant',
          ],
          resources: ['*'],
          conditions: { StringEquals: { 'aws:RequestedRegion': config.primaryRegion } },
        }),
        new PolicyStatement({
          sid: 'AllowMxMedRoleAndPolicyManagement',
          actions: [
            'iam:CreateRole',
            'iam:DeleteRole',
            'iam:GetRole',
            'iam:UpdateRole',
            'iam:UpdateAssumeRolePolicy',
            'iam:TagRole',
            'iam:UntagRole',
            'iam:PutRolePolicy',
            'iam:GetRolePolicy',
            'iam:DeleteRolePolicy',
            'iam:AttachRolePolicy',
            'iam:DetachRolePolicy',
            'iam:CreatePolicy',
            'iam:GetPolicy',
            'iam:GetPolicyVersion',
            'iam:CreatePolicyVersion',
            'iam:DeletePolicyVersion',
            'iam:DeletePolicy',
            'iam:TagPolicy',
            'iam:UntagPolicy',
            'iam:PassRole',
          ],
          resources: [mxmedRoleArn, mxmedPolicyArn],
        }),
        new PolicyStatement({
          sid: 'DenyIdentityAndOrganizationExpansion',
          effect: Effect.DENY,
          actions: [
            'iam:CreateUser',
            'iam:CreateAccessKey',
            'iam:CreateLoginProfile',
            'organizations:*',
            'account:*',
          ],
          resources: ['*'],
        }),
        new PolicyStatement({
          sid: 'DenyProtectedSecurityDeletion',
          effect: Effect.DENY,
          actions: [
            'kms:ScheduleKeyDeletion',
            'kms:DisableKey',
            'cloudtrail:StopLogging',
            'cloudtrail:DeleteTrail',
            'logs:DeleteLogGroup',
            's3:DeleteBucket',
            'backup:DeleteBackupVault',
            'backup:DeleteRecoveryPoint',
          ],
          resources: ['*'],
        }),
        new PolicyStatement({
          sid: 'DenyOppositeEnvironmentResources',
          effect: Effect.DENY,
          actions: ['cloudformation:*', 'iam:PassRole', 'kms:*', 's3:*', 'secretsmanager:*'],
          resources: ['*'],
          conditions: {
            StringEquals: { 'aws:ResourceTag/Environment': oppositeEnvironment },
          },
        }),
      ],
    });
  }
}
