import { AspectPriority, Aspects, CfnParameter, RemovalPolicy, Stack, Tags } from 'aws-cdk-lib';
import { CfnBackupPlan, CfnBackupSelection, CfnBackupVault } from 'aws-cdk-lib/aws-backup';
import { Rule } from 'aws-cdk-lib/aws-events';
import type { IRule } from 'aws-cdk-lib/aws-events';
import { SnsTopic } from 'aws-cdk-lib/aws-events-targets';
import { PolicyStatement, Role, ServicePrincipal } from 'aws-cdk-lib/aws-iam';
import type { IKey } from 'aws-cdk-lib/aws-kms';
import type { CfnDBInstance } from 'aws-cdk-lib/aws-rds';
import type { IBucket } from 'aws-cdk-lib/aws-s3';
import type { ITopic } from 'aws-cdk-lib/aws-sns';
import type { Construct } from 'constructs';

import { BackupDrFoundationAspect } from '../aspects/backup-dr-foundation-aspect';
import { backupDrCreatesCrossRegion } from '../config/backup-dr-profiles';
import type { MxMedEnvironmentConfig } from '../config/environment-config';
import { buildCriticalS3BackupRules, buildRdsBackupRules } from '../constructs/backup-plan-catalog';
import { BaseMxMedStack } from './base-mxmed-stack';
import type { MxMedContractStackProps } from './base-mxmed-stack';
import { backupRoleName, regionalRecoveryVaultName } from '../utils/backup-dr-naming';
import {
  assertCriticalBucketProtection,
  assertNativeRdsProtection,
  assertNoContinuousRds,
  assertSingleS3ContinuousRule,
} from '../utils/backup-dr-validation';
import { assertExplicitBackupResources } from '../utils/backup-dr-resource-selection';

export interface MxMedRegionalBackupStackProps extends MxMedContractStackProps {
  readonly databaseInstance: CfnDBInstance;
  readonly clinicalRecordsBucket: IBucket;
  readonly privateDocumentsBucket: IBucket;
  readonly applicationDataKey: IKey;
  readonly backupKey: IKey;
  readonly regionalCriticalTopic: ITopic;
  readonly regionalWarningTopic: ITopic;
}

/** Profile-aware regional AWS Backup foundation; synth-only until deployment readiness. */
export class MxMedRegionalBackupStack extends BaseMxMedStack {
  private readonly backupConfig: MxMedEnvironmentConfig;
  public readonly backupKey: IKey;
  public readonly regionalRecoveryVault: CfnBackupVault;
  public readonly rdsBackupPlan: CfnBackupPlan;
  public readonly criticalS3BackupPlan: CfnBackupPlan;
  public readonly rdsBackupSelection: CfnBackupSelection;
  public readonly criticalS3BackupSelection: CfnBackupSelection;
  public readonly backupServiceRole: Role;
  public readonly backupEventRules: readonly IRule[];
  public readonly protectionProfile: string;
  public readonly readinessState = 'backup-configured-v1' as const;
  public readonly drDestinationBackupVaultArn: CfnParameter | undefined;

  public constructor(scope: Construct, id: string, props: MxMedRegionalBackupStackProps) {
    super(scope, id, {
      ...props,
      component: 'regional-backup',
      description: 'MXMed profile-aware regional Backup/DR foundation.',
      metadata: { dataClassification: 'sensitive', criticality: 'high', backup: 'required' },
    });

    const { config } = props;
    this.backupConfig = config;
    assertNativeRdsProtection(config);
    assertCriticalBucketProtection(config);
    this.backupKey = props.backupKey;
    this.protectionProfile = config.backupDrActivationMode;

    this.backupServiceRole = this.createBackupServiceRole(props);
    this.regionalRecoveryVault = new CfnBackupVault(this, 'RegionalRecoveryVault', {
      backupVaultName: regionalRecoveryVaultName(config),
      encryptionKeyArn: props.backupKey.keyArn,
      ...(config.backupVaultLockMode === 'unlocked-v1'
        ? {}
        : {
            lockConfiguration: {
              minRetentionDays: config.backupVaultMinRetentionDays,
              maxRetentionDays: config.backupVaultMaxRetentionDays,
              ...(config.backupVaultLockMode === 'compliance-approved-v1'
                ? { changeableForDays: config.backupComplianceChangeableForDays ?? 3 }
                : {}),
            },
          }),
    });
    this.regionalRecoveryVault.applyRemovalPolicy(RemovalPolicy.RETAIN, {
      applyToUpdateReplacePolicy: true,
    });
    this.tagBackupResource(this.regionalRecoveryVault, 'critical', 'tier-1', false);

    const destinationVaultArn = backupDrCreatesCrossRegion(config)
      ? this.createDestinationVaultParameter()
      : undefined;
    this.drDestinationBackupVaultArn = destinationVaultArn;
    const resolvedDestination = destinationVaultArn?.valueAsString;

    const rdsRules = buildRdsBackupRules(
      config,
      this.regionalRecoveryVault.ref,
      resolvedDestination,
    );
    assertNoContinuousRds(rdsRules);
    this.rdsBackupPlan = new CfnBackupPlan(this, 'RdsRegionalPeriodicBackupPlan', {
      backupPlan: {
        backupPlanName: `mxmed-${config.environmentCode}-rds-regional-periodic`,
        backupPlanRule: [...rdsRules],
      },
      backupPlanTags: this.backupTags('transactional-critical', 'tier-1', false),
    });
    this.tagBackupResource(this.rdsBackupPlan, 'transactional-critical', 'tier-1', false);
    this.rdsBackupPlan.addDependency(this.regionalRecoveryVault);

    const s3Rules = buildCriticalS3BackupRules(
      config,
      this.regionalRecoveryVault.ref,
      resolvedDestination,
    );
    assertSingleS3ContinuousRule(s3Rules);
    this.criticalS3BackupPlan = new CfnBackupPlan(this, 'CriticalS3BackupPlan', {
      backupPlan: {
        backupPlanName: `mxmed-${config.environmentCode}-critical-s3`,
        backupPlanRule: [...s3Rules],
      },
      backupPlanTags: this.backupTags('clinical-and-private-critical', 'tier-1', false),
    });
    this.tagBackupResource(
      this.criticalS3BackupPlan,
      'clinical-and-private-critical',
      'tier-1',
      false,
    );
    this.criticalS3BackupPlan.addDependency(this.regionalRecoveryVault);

    const rdsResources = [props.databaseInstance.attrDbInstanceArn];
    assertExplicitBackupResources(rdsResources, 1);
    this.rdsBackupSelection = new CfnBackupSelection(this, 'RdsCriticalSelection', {
      backupPlanId: this.rdsBackupPlan.ref,
      backupSelection: {
        selectionName: `mxmed-${config.environmentCode}-rds-critical`,
        iamRoleArn: this.backupServiceRole.roleArn,
        resources: rdsResources,
      },
    });
    this.rdsBackupSelection.addDependency(this.rdsBackupPlan);

    const s3Resources = [
      props.clinicalRecordsBucket.bucketArn,
      props.privateDocumentsBucket.bucketArn,
    ];
    assertExplicitBackupResources(s3Resources, 2);
    this.criticalS3BackupSelection = new CfnBackupSelection(this, 'CriticalS3Selection', {
      backupPlanId: this.criticalS3BackupPlan.ref,
      backupSelection: {
        selectionName: `mxmed-${config.environmentCode}-critical-s3`,
        iamRoleArn: this.backupServiceRole.roleArn,
        resources: s3Resources,
      },
    });
    this.criticalS3BackupSelection.addDependency(this.criticalS3BackupPlan);

    Tags.of(props.databaseInstance).add('BackupScope', 'transactional-critical', {
      priority: 220,
    });
    Tags.of(props.databaseInstance).add('RecoveryTier', 'tier-1', { priority: 220 });
    for (const bucket of [props.clinicalRecordsBucket, props.privateDocumentsBucket]) {
      Tags.of(bucket).add('BackupScope', 'clinical-critical', { priority: 220 });
      Tags.of(bucket).add('RecoveryTier', 'tier-1', { priority: 220 });
      Tags.of(bucket).add('BackupPolicyVersion', 'v1', { priority: 220 });
    }

    const eventRules: Rule[] = [
      this.failureRule(
        'BackupJobFailureRule',
        'Backup Job State Change',
        ['FAILED', 'ABORTED', 'EXPIRED', 'PARTIAL'],
        props.regionalCriticalTopic,
      ),
    ];
    if (backupDrCreatesCrossRegion(config)) {
      eventRules.push(
        this.failureRule(
          'CopyJobFailureRule',
          'Copy Job State Change',
          ['FAILED'],
          props.regionalCriticalTopic,
        ),
      );
    }
    if (config.backupDrActivationMode === 'restore-validation-ready-v1') {
      eventRules.push(
        this.failureRule(
          'RestoreJobFailureRule',
          'Restore Job State Change',
          ['FAILED', 'ABORTED'],
          props.regionalCriticalTopic,
        ),
      );
    }
    this.backupEventRules = Object.freeze(eventRules);

    Aspects.of(this).add(new BackupDrFoundationAspect(config), {
      priority: AspectPriority.READONLY,
    });
  }

  private createBackupServiceRole(props: MxMedRegionalBackupStackProps): Role {
    const role = new Role(this, 'BackupServiceRole', {
      roleName: backupRoleName(props.config),
      assumedBy: new ServicePrincipal('backup.amazonaws.com'),
      description: 'Dedicated least-privilege MXMed AWS Backup role.',
    });
    role.addToPolicy(
      new PolicyStatement({
        sid: 'ReadBackupSourceMetadata',
        actions: [
          'rds:DescribeDBInstances',
          'rds:DescribeDBSnapshots',
          'rds:ListTagsForResource',
          'backup:ListRecoveryPointsByBackupVault',
          'backup:ListTags',
        ],
        resources: ['*'],
      }),
    );
    const snapshotArn = Stack.of(this).formatArn({
      service: 'rds',
      resource: 'snapshot',
      resourceName: `mxmed-${props.config.environmentCode}-*`,
    });
    role.addToPolicy(
      new PolicyStatement({
        sid: 'CreateContractedRdsRecoveryPoints',
        actions: ['rds:CreateDBSnapshot', 'rds:CopyDBSnapshot', 'rds:AddTagsToResource'],
        resources: [props.databaseInstance.attrDbInstanceArn, snapshotArn],
      }),
    );
    role.addToPolicy(
      new PolicyStatement({
        sid: 'ReadCriticalBucketVersions',
        actions: [
          's3:GetBucketVersioning',
          's3:GetBucketNotification',
          's3:GetBucketTagging',
          's3:ListBucket',
          's3:ListBucketVersions',
        ],
        resources: [props.clinicalRecordsBucket.bucketArn, props.privateDocumentsBucket.bucketArn],
      }),
    );
    role.addToPolicy(
      new PolicyStatement({
        sid: 'ReadCriticalObjectVersions',
        actions: [
          's3:GetObject',
          's3:GetObjectVersion',
          's3:GetObjectTagging',
          's3:GetObjectVersionTagging',
        ],
        resources: [
          props.clinicalRecordsBucket.arnForObjects('*'),
          props.privateDocumentsBucket.arnForObjects('*'),
        ],
      }),
    );
    role.addToPolicy(
      new PolicyStatement({
        sid: 'WriteRegionalRecoveryVault',
        actions: [
          'backup:DescribeBackupVault',
          'backup:GetRecoveryPointRestoreMetadata',
          'backup:TagResource',
        ],
        resources: [
          this.formatArn({
            service: 'backup',
            resource: 'backup-vault',
            resourceName: regionalRecoveryVaultName(props.config),
          }),
        ],
      }),
    );
    props.applicationDataKey.grantEncryptDecrypt(role);
    props.backupKey.grantEncryptDecrypt(role);
    this.tagBackupResource(role, 'critical', 'tier-1', false);
    return role;
  }

  private failureRule(
    id: string,
    detailType: string,
    states: readonly string[],
    topic: ITopic,
  ): Rule {
    const rule = new Rule(this, id, {
      description: 'MXMed sanitized AWS Backup failure routing; EventBridge is best-effort.',
      eventPattern: {
        source: ['aws.backup'],
        detailType: [detailType],
        detail: { state: [...states] },
      },
      targets: [new SnsTopic(topic)],
    });
    this.tagBackupResource(rule, 'monitoring', 'tier-1', false);
    return rule;
  }

  private createDestinationVaultParameter(): CfnParameter {
    return new CfnParameter(this, 'DrDestinationBackupVaultArn', {
      type: 'String',
      allowedPattern: '^arn:[^:]+:backup:[a-z0-9-]+:[0-9]{12}:backup-vault:[A-Za-z0-9_-]+$',
      description: 'Approved DR backup vault ARN supplied after the separate DR stack handoff.',
    });
  }

  private backupTags(scope: string, tier: string, ephemeral: boolean): Record<string, string> {
    return {
      Project: 'mxmed',
      Environment: this.backupConfig.environmentName,
      DeploymentProfile: this.backupConfig.deploymentProfile,
      BackupDrActivationMode: this.protectionProfile,
      BackupPolicyVersion: 'v1',
      BackupScope: scope,
      RecoveryTier: tier,
      CostTier: 'usage-controlled',
      CostReview: 'required',
      Ephemeral: String(ephemeral),
      RestoreValidation: 'false',
    };
  }

  private tagBackupResource(
    resource: Construct,
    scope: string,
    tier: string,
    ephemeral: boolean,
  ): void {
    for (const [key, value] of Object.entries(this.backupTags(scope, tier, ephemeral))) {
      Tags.of(resource).add(key, value, { priority: 220 });
    }
  }
}
