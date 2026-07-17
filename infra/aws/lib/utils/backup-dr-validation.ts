import type { CfnBackupPlan } from 'aws-cdk-lib/aws-backup';

import type { MxMedEnvironmentConfig } from '../config/environment-config';

export function assertNativeRdsProtection(config: MxMedEnvironmentConfig): void {
  const runtimeConfig = config as unknown as Record<string, unknown>;
  if (
    runtimeConfig.databaseEngine !== 'mysql' ||
    runtimeConfig.databaseEngineVersion !== '8.4.9' ||
    config.databaseBackupRetentionDays < 35 ||
    (config.environmentName === 'production' && !config.databaseDeletionProtection)
  ) {
    throw new Error('rds_native_pitr_retention_below_contract');
  }
}

export function assertCriticalBucketProtection(config: MxMedEnvironmentConfig): void {
  const runtimeConfig = config as unknown as Record<string, unknown>;
  if (
    !config.storageVersioningEnabled ||
    runtimeConfig.storageEncryptionProfile !== 'application-data-kms'
  ) {
    throw new Error('critical_s3_bucket_versioning_not_enabled');
  }
}

export function assertNoContinuousRds(
  rules: readonly CfnBackupPlan.BackupRuleResourceTypeProperty[],
): void {
  if (rules.some((rule) => rule.enableContinuousBackup === true)) {
    throw new Error('MXMED_BACKUP_RDS_CONTINUOUS_FORBIDDEN');
  }
}

export function assertSingleS3ContinuousRule(
  rules: readonly CfnBackupPlan.BackupRuleResourceTypeProperty[],
): void {
  if (rules.filter((rule) => rule.enableContinuousBackup === true).length !== 1) {
    throw new Error('MXMED_BACKUP_S3_CONTINUOUS_RULE_COUNT_INVALID');
  }
  const vaults = new Set(rules.map((rule) => rule.targetBackupVault));
  if (vaults.size !== 1) throw new Error('MXMED_BACKUP_S3_VAULT_MISMATCH');
}
