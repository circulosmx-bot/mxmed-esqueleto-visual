import type { CfnBackupPlan } from 'aws-cdk-lib/aws-backup';

import type { MxMedEnvironmentConfig } from '../config/environment-config';

const DAILY_SCHEDULE = 'cron(0 3 * * ? *)';
const MONTHLY_SCHEDULE = 'cron(0 3 ? * SUN#1 *)';

function copyAction(
  destinationBackupVaultArn: string,
  deleteAfterDays: number,
): CfnBackupPlan.CopyActionResourceTypeProperty {
  return {
    destinationBackupVaultArn,
    lifecycle: { deleteAfterDays },
  };
}

function periodicRule(
  config: MxMedEnvironmentConfig,
  ruleName: string,
  targetBackupVault: string,
  scheduleExpression: string,
  deleteAfterDays: number,
  destinationBackupVaultArn?: string,
): CfnBackupPlan.BackupRuleResourceTypeProperty {
  return {
    ruleName,
    targetBackupVault,
    scheduleExpression,
    scheduleExpressionTimezone: 'UTC',
    startWindowMinutes: config.backupStartWindowMinutes,
    completionWindowMinutes: config.backupCompletionWindowMinutes,
    enableContinuousBackup: false,
    lifecycle: { deleteAfterDays },
    recoveryPointTags: {
      BackupPolicyVersion: 'v1',
      RecoveryTier: 'tier-1',
    },
    ...(destinationBackupVaultArn === undefined
      ? {}
      : { copyActions: [copyAction(destinationBackupVaultArn, deleteAfterDays)] }),
  };
}

export function buildRdsBackupRules(
  config: MxMedEnvironmentConfig,
  targetBackupVault: string,
  destinationBackupVaultArn?: string,
): readonly CfnBackupPlan.BackupRuleResourceTypeProperty[] {
  const rules: CfnBackupPlan.BackupRuleResourceTypeProperty[] = [
    periodicRule(
      config,
      'RdsDailyRegional',
      targetBackupVault,
      DAILY_SCHEDULE,
      config.backupRdsPeriodicRetentionDays,
      destinationBackupVaultArn,
    ),
  ];
  if (config.deploymentProfile !== 'launch-lean-v1' || destinationBackupVaultArn !== undefined) {
    rules.push(
      periodicRule(
        config,
        'RdsMonthlyRegional',
        targetBackupVault,
        MONTHLY_SCHEDULE,
        config.backupRdsMonthlyRetentionDays,
        destinationBackupVaultArn,
      ),
    );
  }
  return Object.freeze(rules);
}

export function buildCriticalS3BackupRules(
  config: MxMedEnvironmentConfig,
  targetBackupVault: string,
  destinationBackupVaultArn?: string,
): readonly CfnBackupPlan.BackupRuleResourceTypeProperty[] {
  const rules: CfnBackupPlan.BackupRuleResourceTypeProperty[] = [
    {
      ruleName: 'CriticalS3Continuous',
      targetBackupVault,
      enableContinuousBackup: true,
      lifecycle: { deleteAfterDays: config.backupS3ContinuousRetentionDays },
      recoveryPointTags: { BackupPolicyVersion: 'v1', RecoveryTier: 'tier-1' },
    },
  ];
  if (config.deploymentProfile !== 'launch-lean-v1' || destinationBackupVaultArn !== undefined) {
    rules.push(
      periodicRule(
        config,
        'CriticalS3DailyPeriodic',
        targetBackupVault,
        DAILY_SCHEDULE,
        config.backupS3PeriodicRetentionDays,
        destinationBackupVaultArn,
      ),
    );
    if (config.deploymentProfile !== 'launch-lean-v1') {
      rules.push(
        periodicRule(
          config,
          'CriticalS3MonthlyPeriodic',
          targetBackupVault,
          MONTHLY_SCHEDULE,
          config.backupRdsMonthlyRetentionDays,
          destinationBackupVaultArn,
        ),
      );
    }
  }
  return Object.freeze(rules);
}

export const MXMED_BACKUP_SCHEDULES = Object.freeze({
  daily: DAILY_SCHEDULE,
  firstSundayMonthly: MONTHLY_SCHEDULE,
});
