import { CfnDeletionPolicy, CfnParameter, CfnResource, Stack } from 'aws-cdk-lib';
import type { IAspect } from 'aws-cdk-lib';
import {
  CfnBackupPlan,
  CfnBackupSelection,
  CfnBackupVault,
  CfnRestoreTestingPlan,
  CfnRestoreTestingSelection,
} from 'aws-cdk-lib/aws-backup';
import { CfnRule } from 'aws-cdk-lib/aws-events';
import { CfnPolicy, CfnRole } from 'aws-cdk-lib/aws-iam';
import { CfnKey } from 'aws-cdk-lib/aws-kms';
import { CfnDBInstance } from 'aws-cdk-lib/aws-rds';
import { CfnBucket } from 'aws-cdk-lib/aws-s3';
import type { IConstruct } from 'constructs';

import type { MxMedEnvironmentConfig } from '../config/environment-config';

const BACKUP_PATH = /(?:RegionalBackup|DrCopy|RestoreValidation)/;
const SENSITIVE_TEXT =
  /(?:client_secret|secret.?value|password|patient|doctor.?id|session.?id|sql.?dump|clinical.?content)/i;

function reject(condition: boolean, code: string): void {
  if (condition) throw new Error(`MXMED_BACKUP_DR_GUARDRAIL:${code}`);
}

function text(value: unknown): string {
  return JSON.stringify(value);
}

export class BackupDrFoundationAspect implements IAspect {
  public constructor(private readonly config: MxMedEnvironmentConfig) {}

  public visit(node: IConstruct): void {
    const backupOwned = BACKUP_PATH.test(node.node.path);
    reject(
      backupOwned &&
        node instanceof CfnResource &&
        this.config.backupDrActivationMode === 'disabled-v1',
      'RESOURCES_IN_DISABLED',
    );
    reject(this.config.backupAutomaticFailoverEnabled, 'AUTOMATIC_FAILOVER');
    reject(this.config.backupAutomaticFailbackEnabled, 'AUTOMATIC_FAILBACK');
    reject(this.config.backupQuarantineProtectionEnabled, 'QUARANTINE_ENABLED');

    if (node instanceof CfnDBInstance && this.config.backupDrActivationMode !== 'disabled-v1') {
      this.inspectRds(node);
    }
    if (node instanceof CfnBucket) this.inspectBucket(node);
    if (node instanceof CfnBackupVault) this.inspectVault(node);
    if (node instanceof CfnBackupPlan) this.inspectPlan(node);
    if (node instanceof CfnBackupSelection) this.inspectSelection(node);
    if (node instanceof CfnRestoreTestingPlan) this.inspectRestorePlan(node);
    if (node instanceof CfnRestoreTestingSelection) this.inspectRestoreSelection(node);
    if (node instanceof CfnRole && backupOwned) this.inspectRole(node);
    if (node instanceof CfnPolicy && backupOwned) this.inspectPolicy(node);
    if (node instanceof CfnKey && backupOwned) this.inspectKey(node);
    if (node instanceof CfnRule && backupOwned) this.inspectRule(node);
    if (node instanceof CfnParameter && node.node.path.includes('RestoreTest')) {
      reject(node.default !== undefined, 'RESTORE_PARAMETER_DEFAULT');
    }
    if (node instanceof CfnResource && backupOwned) {
      reject(SENSITIVE_TEXT.test(text(this.properties(node))), 'SENSITIVE_CONFIGURATION');
      reject(/\b[0-9]{12}\b/.test(text(this.properties(node))), 'LITERAL_ACCOUNT_ID');
      reject(
        new Set([
          'AWS::Backup::LogicallyAirGappedBackupVault',
          'AWS::Backup::Framework',
          'AWS::Backup::ReportPlan',
          'AWS::Lambda::Function',
        ]).has(node.cfnResourceType),
        'FORBIDDEN_RESOURCE',
      );
    }
  }

  private inspectRds(instance: CfnDBInstance): void {
    reject(
      instance.backupRetentionPeriod === undefined || instance.backupRetentionPeriod < 35,
      'RDS_RETENTION',
    );
    reject(instance.storageEncrypted !== true, 'RDS_ENCRYPTION');
    reject(
      this.config.environmentName === 'production' && instance.deletionProtection !== true,
      'RDS_DELETION_PROTECTION',
    );
    reject(
      instance.cfnOptions.deletionPolicy !== CfnDeletionPolicy.RETAIN &&
        this.config.environmentName === 'production',
      'RDS_RETAIN',
    );
  }

  private inspectBucket(bucket: CfnBucket): void {
    const critical = /(?:ClinicalRecordsBucket|PrivateDocumentsBucket)/.test(bucket.node.path);
    if (!critical || this.config.backupDrActivationMode === 'disabled-v1') return;
    const versioning = Stack.of(bucket).resolve(bucket.versioningConfiguration) as
      { status?: string } | undefined;
    const encryption = Stack.of(bucket).resolve(bucket.bucketEncryption) as unknown;
    const publicBlock = Stack.of(bucket).resolve(bucket.publicAccessBlockConfiguration) as
      Record<string, unknown> | undefined;
    const notifications = Stack.of(bucket).resolve(bucket.notificationConfiguration) as unknown;
    reject(versioning?.status !== 'Enabled', 'CRITICAL_BUCKET_VERSIONING');
    reject(encryption === undefined, 'CRITICAL_BUCKET_ENCRYPTION');
    reject(
      publicBlock === undefined || Object.values(publicBlock).some((value) => value !== true),
      'CRITICAL_BUCKET_PUBLIC_BLOCK',
    );
    reject(
      !/EventBridgeEnabled[^}]*true/i.test(text(notifications)),
      'CRITICAL_BUCKET_EVENTBRIDGE',
    );
    reject(bucket.cfnOptions.deletionPolicy !== CfnDeletionPolicy.RETAIN, 'CRITICAL_BUCKET_RETAIN');
  }

  private inspectVault(vault: CfnBackupVault): void {
    reject(vault.encryptionKeyArn === undefined, 'VAULT_ENCRYPTION');
    reject(vault.cfnOptions.deletionPolicy !== CfnDeletionPolicy.RETAIN, 'VAULT_RETAIN');
    reject(vault.accessPolicy !== undefined, 'VAULT_ACCESS_POLICY');
    const lock = Stack.of(vault).resolve(vault.lockConfiguration) as
      Record<string, unknown> | undefined;
    if (this.config.backupVaultLockMode === 'governance-v1') {
      reject(
        lock?.minRetentionDays !== 1 || lock.maxRetentionDays !== 365,
        'VAULT_GOVERNANCE_RETENTION',
      );
      reject('changeableForDays' in (lock ?? {}), 'GOVERNANCE_CHANGEABLE_FOR_DAYS');
    }
  }

  private inspectPlan(plan: CfnBackupPlan): void {
    const resolved = Stack.of(plan).resolve(plan.backupPlan) as {
      backupPlanRule?: Record<string, unknown>[];
    };
    const rules = resolved.backupPlanRule ?? [];
    const isRds = plan.node.path.includes('RdsRegionalPeriodicBackupPlan');
    if (isRds) {
      reject(
        rules.some((rule) => rule.enableContinuousBackup === true),
        'RDS_CONTINUOUS',
      );
    }
    if (plan.node.path.includes('CriticalS3BackupPlan')) {
      reject(
        rules.filter((rule) => rule.enableContinuousBackup === true).length !== 1,
        'S3_CONTINUOUS_COUNT',
      );
      const vaults = new Set(rules.map((rule) => text(rule.targetBackupVault)));
      reject(vaults.size !== 1, 'S3_VAULT_MISMATCH');
    }
  }

  private inspectSelection(selection: CfnBackupSelection): void {
    const resolved = Stack.of(selection).resolve(selection.backupSelection) as {
      resources?: unknown[];
    };
    const resources = text(resolved.resources ?? []);
    reject(/(?:PublicMedia|UploadQuarantine|AuditBucket)/i.test(resources), 'FORBIDDEN_SELECTION');
    reject(/arn:[^" ]*\*/i.test(resources), 'WILDCARD_SELECTION');
  }

  private inspectRestorePlan(plan: CfnRestoreTestingPlan): void {
    reject(this.config.restoreTestingMode !== 'scheduled-monthly-v1', 'UNSCHEDULED_RESTORE_PLAN');
    reject(!this.config.backupSentinelsIntegrated, 'RESTORE_SENTINELS_GATE');
    reject(!this.config.backupApplicationValidationIntegrated, 'RESTORE_VALIDATION_GATE');
    reject(plan.scheduleExpressionTimezone !== 'UTC', 'RESTORE_TIMEZONE');
  }

  private inspectRestoreSelection(selection: CfnRestoreTestingSelection): void {
    reject(
      selection.protectedResourceArns === undefined ||
        selection.protectedResourceArns.some((resource) => resource.includes('*')),
      'RESTORE_WILDCARD',
    );
    const roleArn = (selection as unknown as { iamRoleArn?: unknown }).iamRoleArn;
    reject(roleArn === undefined, 'RESTORE_ROLE');
  }

  private inspectRole(role: CfnRole): void {
    const trust = text(role.assumeRolePolicyDocument);
    reject(!trust.includes('backup.amazonaws.com'), 'ROLE_TRUST');
    reject(text(role.managedPolicyArns).includes('AdministratorAccess'), 'ADMINISTRATOR_ACCESS');
    reject(text(role.managedPolicyArns).includes('AWSBackupFullAccess'), 'BACKUP_FULL_ACCESS');
  }

  private inspectPolicy(policy: CfnPolicy): void {
    const policyText = text(Stack.of(policy).resolve(policy.policyDocument));
    reject(/secretsmanager:GetSecretValue/i.test(policyText), 'SECRETS_READ');
    reject(
      /(?:rds:DeleteDBInstance|s3:DeleteBucket|kms:ScheduleKeyDeletion)/i.test(policyText),
      'DESTRUCTIVE_PERMISSION',
    );
  }

  private inspectKey(key: CfnKey): void {
    reject(key.enableKeyRotation !== true, 'KEY_ROTATION');
    reject(key.cfnOptions.deletionPolicy !== CfnDeletionPolicy.RETAIN, 'KEY_RETAIN');
  }

  private inspectRule(rule: CfnRule): void {
    const pattern = text(Stack.of(rule).resolve(rule.eventPattern));
    const targets: unknown = Stack.of(rule).resolve(rule.targets);
    reject(!pattern.includes('aws.backup'), 'EVENT_SOURCE');
    reject(!Array.isArray(targets) || targets.length !== 1, 'EVENT_TARGET');
  }

  private properties(resource: CfnResource): unknown {
    return (resource as unknown as { cfnProperties?: unknown }).cfnProperties ?? {};
  }
}
