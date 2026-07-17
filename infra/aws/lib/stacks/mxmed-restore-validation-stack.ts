import { CfnParameter, RemovalPolicy, Stack, Tags } from 'aws-cdk-lib';
import { CfnRestoreTestingPlan, CfnRestoreTestingSelection } from 'aws-cdk-lib/aws-backup';
import { SecurityGroup } from 'aws-cdk-lib/aws-ec2';
import type { ISubnet, IVpc, Vpc } from 'aws-cdk-lib/aws-ec2';
import { PolicyStatement, Role, ServicePrincipal } from 'aws-cdk-lib/aws-iam';
import type { IKey } from 'aws-cdk-lib/aws-kms';
import { BlockPublicAccess, Bucket, BucketEncryption, ObjectOwnership } from 'aws-cdk-lib/aws-s3';
import type { IBucket } from 'aws-cdk-lib/aws-s3';
import type { Construct } from 'constructs';

import { BaseMxMedStack } from './base-mxmed-stack';
import type { MxMedContractStackProps } from './base-mxmed-stack';
import {
  MXMED_RESTORE_SENTINEL_CONTRACT,
  MXMED_RESTORE_VALIDATION_PROFILE,
} from '../constructs/backup-restore-validation-contract';
import { restoreRoleName, restoreTestingName } from '../utils/backup-dr-naming';
import { MXMED_RESTORE_TESTING_MONTHLY_SCHEDULE } from '../utils/backup-dr-schedules';

export interface MxMedRestoreValidationStackProps extends MxMedContractStackProps {
  readonly vpc: Vpc;
  readonly isolatedDataSubnets: readonly ISubnet[];
  readonly databaseInstanceArn: string;
  readonly databaseSubnetGroupName: string;
  readonly clinicalRecordsBucket: IBucket;
  readonly privateDocumentsBucket: IBucket;
  readonly regionalRecoveryVaultArn: string;
  readonly backupKey: IKey;
  readonly applicationDataKey: IKey;
}

/** Isolated restore-testing fixture. It never creates application or public connectivity. */
export class MxMedRestoreValidationStack extends BaseMxMedStack {
  public readonly restoreTestingPlan?: CfnRestoreTestingPlan;
  public readonly rdsRestoreSelection?: CfnRestoreTestingSelection;
  public readonly s3RestoreSelection?: CfnRestoreTestingSelection;
  public readonly restoreRole: Role;
  public readonly restoreSecurityGroup: SecurityGroup;
  public readonly temporaryRestoreBucket: Bucket;
  public readonly validationProfile = MXMED_RESTORE_VALIDATION_PROFILE;
  public readonly cleanupProfile = 'manual-residual-cost-audit-v1' as const;
  public readonly sentinelProfile = MXMED_RESTORE_SENTINEL_CONTRACT;

  public constructor(scope: Construct, id: string, props: MxMedRestoreValidationStackProps) {
    super(scope, id, {
      ...props,
      component: 'restore-validation',
      description: 'MXMed isolated restore-validation fixture with explicit cleanup gates.',
      metadata: { dataClassification: 'sensitive', criticality: 'high', backup: 'required' },
    });

    const { config } = props;
    this.restoreSecurityGroup = new SecurityGroup(this, 'RestoreValidationSecurityGroup', {
      vpc: props.vpc as unknown as IVpc,
      description: 'MXMed restore validation only; zero inbound and no default egress.',
      allowAllOutbound: false,
      disableInlineRules: true,
    });
    this.restoreRole = this.createRestoreRole(props);
    this.temporaryRestoreBucket = new Bucket(this, 'TemporaryRestoreBucket', {
      blockPublicAccess: BlockPublicAccess.BLOCK_ALL,
      objectOwnership: ObjectOwnership.BUCKET_OWNER_ENFORCED,
      versioned: true,
      enforceSSL: true,
      encryption: BucketEncryption.KMS,
      encryptionKey: props.backupKey,
      bucketKeyEnabled: true,
      autoDeleteObjects: false,
      removalPolicy: RemovalPolicy.RETAIN,
    });
    this.temporaryRestoreBucket.grantReadWrite(this.restoreRole);

    new CfnParameter(this, 'RestoreTestMonthlyBudgetUsd', {
      type: 'Number',
      minValue: 1,
      description: 'Approved monthly restore-test budget; intentionally no default.',
    });
    new CfnParameter(this, 'RestoreTestMaximumRuntimeHours', {
      type: 'Number',
      minValue: 1,
      maxValue: 168,
      description: 'Maximum restore-test runtime; intentionally no default.',
    });
    new CfnParameter(this, 'RestoreTestApprovedInstanceClass', {
      type: 'String',
      allowedValues: ['db.t4g.medium', 'db.m6g.large'],
      description: 'Approved compatible temporary RDS class; intentionally no default.',
    });
    new CfnParameter(this, 'RestoreTestOwnerReference', {
      type: 'String',
      allowedPattern: '^[A-Za-z0-9_-]{3,64}$',
      description: 'Opaque restore-test owner reference; no contact data and no default.',
    });
    new CfnParameter(this, 'RestoreTestCleanupDeadlineHours', {
      type: 'Number',
      minValue: 1,
      maxValue: 168,
      description: 'Cleanup deadline for audited manual removal; intentionally no default.',
    });

    if (config.restoreTestingMode === 'scheduled-monthly-v1') {
      const planName = restoreTestingName(config);
      this.restoreTestingPlan = new CfnRestoreTestingPlan(this, 'RestoreTestingPlan', {
        restoreTestingPlanName: planName,
        scheduleExpression: MXMED_RESTORE_TESTING_MONTHLY_SCHEDULE,
        scheduleExpressionTimezone: 'UTC',
        startWindowHours: config.backupRestoreTestMaxRuntimeHours,
        recoveryPointSelection: {
          algorithm: 'LATEST_WITHIN_WINDOW',
          includeVaults: [props.regionalRecoveryVaultArn],
          recoveryPointTypes: ['SNAPSHOT', 'CONTINUOUS'],
          selectionWindowDays: 35,
        },
        tags: this.restoreTags(config),
      });
      this.rdsRestoreSelection = new CfnRestoreTestingSelection(
        this,
        'RdsRestoreTestingSelection',
        {
          restoreTestingPlanName: this.restoreTestingPlan.ref,
          restoreTestingSelectionName: `mxmed_${config.environmentCode}_rds_restore_v1`,
          protectedResourceType: 'RDS',
          protectedResourceArns: [props.databaseInstanceArn],
          iamRoleArn: this.restoreRole.roleArn,
          validationWindowHours: config.backupRestoreTestMaxRuntimeHours,
          restoreMetadataOverrides: {
            DBInstanceIdentifier: `mxmed-${config.environmentCode}-restore-test`,
            DBInstanceClass: 'db.t4g.medium',
            DBSubnetGroupName: props.databaseSubnetGroupName,
            Engine: 'mysql',
            MultiAZ: 'false',
            PubliclyAccessible: 'false',
            DeletionProtection: 'false',
            VpcSecurityGroupIds: this.restoreSecurityGroup.securityGroupId,
          },
        },
      );
      this.rdsRestoreSelection.addDependency(this.restoreTestingPlan);
      this.s3RestoreSelection = new CfnRestoreTestingSelection(this, 'S3RestoreTestingSelection', {
        restoreTestingPlanName: this.restoreTestingPlan.ref,
        restoreTestingSelectionName: `mxmed_${config.environmentCode}_s3_restore_v1`,
        protectedResourceType: 'S3',
        protectedResourceArns: [
          props.clinicalRecordsBucket.bucketArn,
          props.privateDocumentsBucket.bucketArn,
        ],
        iamRoleArn: this.restoreRole.roleArn,
        validationWindowHours: config.backupRestoreTestMaxRuntimeHours,
        restoreMetadataOverrides: {
          DestinationBucketName: this.temporaryRestoreBucket.bucketName,
        },
      });
      this.s3RestoreSelection.addDependency(this.restoreTestingPlan);
    }

    for (const resource of [
      this.restoreSecurityGroup,
      this.restoreRole,
      this.temporaryRestoreBucket,
      ...(this.restoreTestingPlan === undefined ? [] : [this.restoreTestingPlan]),
    ]) {
      Tags.of(resource).add('BackupDrActivationMode', config.backupDrActivationMode, {
        priority: 220,
      });
      Tags.of(resource).add('BackupPolicyVersion', 'v1', { priority: 220 });
      Tags.of(resource).add('BackupScope', 'restore-validation', { priority: 220 });
      Tags.of(resource).add('RecoveryTier', 'tier-1', { priority: 220 });
      Tags.of(resource).add('CostTier', 'usage-controlled', { priority: 220 });
      Tags.of(resource).add('CostReview', 'required', { priority: 220 });
      Tags.of(resource).add('Ephemeral', 'true', { priority: 220 });
      Tags.of(resource).add('RestoreValidation', 'true', { priority: 220 });
    }
    void props.isolatedDataSubnets;
  }

  private createRestoreRole(props: MxMedRestoreValidationStackProps): Role {
    const role = new Role(this, 'RestoreValidationRole', {
      roleName: restoreRoleName(props.config),
      assumedBy: new ServicePrincipal('backup.amazonaws.com'),
      description: 'Dedicated MXMed role for isolated restore-validation resources only.',
    });
    const temporaryDbArn = Stack.of(this).formatArn({
      service: 'rds',
      resource: 'db',
      resourceName: `mxmed-${props.config.environmentCode}-restore-*`,
    });
    const subnetGroupArn = Stack.of(this).formatArn({
      service: 'rds',
      resource: 'subgrp',
      resourceName: props.databaseSubnetGroupName,
    });
    role.addToPolicy(
      new PolicyStatement({
        sid: 'CreateIsolatedRdsRestoreTarget',
        actions: [
          'rds:RestoreDBInstanceFromDBSnapshot',
          'rds:RestoreDBInstanceToPointInTime',
          'rds:DescribeDBInstances',
          'rds:DescribeDBSnapshots',
          'rds:AddTagsToResource',
        ],
        resources: [props.databaseInstanceArn, temporaryDbArn, subnetGroupArn],
      }),
    );
    role.addToPolicy(
      new PolicyStatement({
        sid: 'DescribeRestoreJobs',
        actions: ['backup:DescribeRestoreJob', 'backup:GetRecoveryPointRestoreMetadata'],
        resources: [props.regionalRecoveryVaultArn],
      }),
    );
    props.backupKey.grantEncryptDecrypt(role);
    props.applicationDataKey.grantEncryptDecrypt(role);
    return role;
  }

  private restoreTags(
    config: MxMedRestoreValidationStackProps['config'],
  ): { key: string; value: string }[] {
    return [
      { key: 'Project', value: 'mxmed' },
      { key: 'Environment', value: config.environmentName },
      { key: 'DeploymentProfile', value: config.deploymentProfile },
      { key: 'BackupDrActivationMode', value: config.backupDrActivationMode },
      { key: 'BackupPolicyVersion', value: 'v1' },
      { key: 'BackupScope', value: 'restore-validation' },
      { key: 'RecoveryTier', value: 'tier-1' },
      { key: 'CostTier', value: 'usage-controlled' },
      { key: 'CostReview', value: 'required' },
      { key: 'Ephemeral', value: 'true' },
      { key: 'RestoreValidation', value: 'true' },
    ];
  }
}
