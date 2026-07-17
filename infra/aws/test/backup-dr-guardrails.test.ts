import type { CfnBackupPlan } from 'aws-cdk-lib/aws-backup';

import { getEnvironmentConfig } from '../lib/config/environments';
import { transitionBackupReadiness } from '../lib/constructs/backup-dr-contract';
import {
  assertExplicitBackupResources,
  assertQuarantineExcluded,
} from '../lib/utils/backup-dr-resource-selection';
import { assertMxMedBackupSchedule } from '../lib/utils/backup-dr-schedules';
import {
  assertCriticalBucketProtection,
  assertNativeRdsProtection,
  assertNoContinuousRds,
  assertSingleS3ContinuousRule,
} from '../lib/utils/backup-dr-validation';
import {
  activeBackupConfig,
  createBackupStage,
  restoreBackupConfig,
} from './backup-dr-test-helpers';

function rule(
  targetBackupVault: string,
  enableContinuousBackup: boolean,
): CfnBackupPlan.BackupRuleResourceTypeProperty {
  return { ruleName: 'fixture', targetBackupVault, enableContinuousBackup };
}

describe('Backup/DR fail-closed guardrails', () => {
  test('rejects RDS continuous backup', () => {
    expect(() => {
      assertNoContinuousRds([rule('vault', true)]);
    }).toThrow('MXMED_BACKUP_RDS_CONTINUOUS_FORBIDDEN');
  });

  test('rejects duplicate S3 continuous rules', () => {
    expect(() => {
      assertSingleS3ContinuousRule([rule('vault', true), rule('vault', true)]);
    }).toThrow('MXMED_BACKUP_S3_CONTINUOUS_RULE_COUNT_INVALID');
  });

  test('rejects S3 rules targeting different vaults', () => {
    expect(() => {
      assertSingleS3ContinuousRule([rule('a', true), rule('b', false)]);
    }).toThrow('MXMED_BACKUP_S3_VAULT_MISMATCH');
  });

  test.each(['*', 'PublicMediaBucket', 'UploadQuarantineBucket', 'AuditBucket'])(
    'rejects forbidden selection %s',
    (resource) => {
      expect(() => {
        assertExplicitBackupResources([resource], 1);
      }).toThrow('MXMED_BACKUP_RESOURCE_SELECTION_INVALID');
    },
  );

  test('uses the specific Quarantine rejection code', () => {
    expect(() => {
      assertQuarantineExcluded(['UploadQuarantineBucket']);
    }).toThrow('quarantine_bucket_backup_forbidden');
  });

  test('rejects an active configuration without Operations topics', () => {
    expect(() =>
      getEnvironmentConfig(
        'production',
        'launch-lean-v1',
        'service-enabled-v1',
        'directory-core-v1',
        {},
        {},
        { backupDrActivationMode: 'regional-recovery-ready-v1' },
      ),
    ).toThrow('backup_monitoring_topics_not_available');
  });

  test('rejects disabled critical bucket versioning', () => {
    expect(() => {
      assertCriticalBucketProtection({
        ...activeBackupConfig(),
        storageVersioningEnabled: false,
      });
    }).toThrow('critical_s3_bucket_versioning_not_enabled');
  });

  test('rejects native RDS retention below 35', () => {
    expect(() => {
      assertNativeRdsProtection({
        ...activeBackupConfig(),
        databaseBackupRetentionDays: 34,
      });
    }).toThrow('rds_native_pitr_retention_below_contract');
  });

  test.each(['backupAutomaticFailoverEnabled', 'backupAutomaticFailbackEnabled'] as const)(
    'rejects %s=true',
    (field) => {
      expect(() => createBackupStage({ ...activeBackupConfig(), [field]: true })).toThrow(
        'MXMED_BACKUP_DR_AUTOMATION_OR_QUARANTINE_FORBIDDEN',
      );
    },
  );

  test.each(['cron(1 1 * * ? *)', 'rate(1 hour)', 'cron(* * * * ? *)'])(
    'rejects unapproved schedule %s',
    (schedule) => {
      expect(() => {
        assertMxMedBackupSchedule(schedule);
      }).toThrow('MXMED_BACKUP_SCHEDULE_INVALID');
    },
  );

  test('rejects compliance mode without ChangeableForDays', () => {
    expect(() =>
      activeBackupConfig('production-standard-v1', {
        backupDrActivationMode: 'regional-recovery-ready-v1',
        backupVaultLockMode: 'compliance-approved-v1',
      }),
    ).toThrow('MXMED_BACKUP_DR_COMPLIANCE_GATE_CLOSED');
  });

  test('rejects restore activation without an explicit testing mode', () => {
    expect(() =>
      activeBackupConfig('production-standard-v1', {
        backupDrActivationMode: 'restore-validation-ready-v1',
        drRegionState: 'selected-and-verified-v1',
        drRegion: 'us-test-1',
        backupDataResidencyState: 'approved-v1',
      }),
    ).toThrow('MXMED_BACKUP_DR_RESTORE_MODE_REQUIRED');
  });

  test('rejects application validation without sentinel gates', () => {
    expect(() =>
      activeBackupConfig('production-standard-v1', {
        backupValidationState: 'application-validation-passed-v1',
      }),
    ).toThrow('MXMED_BACKUP_DR_APPLICATION_VALIDATION_GATE_CLOSED');
  });

  test('does not mark a scheduled restore fixture DR ready', () => {
    expect(restoreBackupConfig('scheduled-monthly-v1').backupReadinessState).toBe(
      'backup-configured-v1',
    );
    expect(() => transitionBackupReadiness('backup-configured-v1', 'dr-ready-v1')).toThrow();
  });
});
