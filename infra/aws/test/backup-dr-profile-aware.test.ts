import { getEnvironmentConfig } from '../lib/config/environments';
import {
  activeBackupConfig,
  backupTemplate,
  createBackupStage,
  resourceEntries,
  resourceProperties,
  templateJson,
  templateText,
} from './backup-dr-test-helpers';

function bucketById(
  config: ReturnType<typeof activeBackupConfig>,
  id: string,
): Record<string, unknown> {
  const storage = templateJson(createBackupStage(config).storageStack);
  return (
    resourceEntries(storage, 'AWS::S3::Bucket').find(([logicalId]) => logicalId.includes(id))?.[1]
      .Properties ?? {}
  );
}

describe('profile-aware protection boundaries', () => {
  test.each(['ClinicalRecordsBucket', 'PrivateDocumentsBucket'])(
    'enables EventBridge only when Backup is active for %s',
    (bucket) => {
      const active = bucketById(activeBackupConfig(), bucket);
      const disabledStage = createBackupStage(getEnvironmentConfig('production', 'launch-lean-v1'));
      const disabled = resourceEntries(
        templateJson(disabledStage.storageStack),
        'AWS::S3::Bucket',
      ).find(([id]) => id.includes(bucket))?.[1].Properties;
      expect(active.NotificationConfiguration).toEqual({
        EventBridgeConfiguration: { EventBridgeEnabled: true },
      });
      expect(disabled?.NotificationConfiguration).toBeUndefined();
    },
  );

  test('preserves Quarantine EventBridge while keeping it excluded from Backup', () => {
    const quarantine = bucketById(activeBackupConfig(), 'UploadQuarantineBucket');
    expect(quarantine.NotificationConfiguration).toEqual({
      EventBridgeConfiguration: { EventBridgeEnabled: true },
    });
    expect(templateText(backupTemplate(activeBackupConfig()))).not.toContain(
      'UploadQuarantineBucket',
    );
  });

  test.each([
    ['launch-lean-v1', 1, 1],
    ['production-standard-v1', 2, 3],
    ['scale-ready-v1', 2, 3],
  ] as const)('%s creates %d RDS and %d S3 rules', (profile, rdsCount, s3Count) => {
    const plans = resourceProperties(
      backupTemplate(activeBackupConfig(profile)),
      'AWS::Backup::BackupPlan',
    );
    const text = JSON.stringify(plans);
    const rdsPlan = plans.find((plan) => JSON.stringify(plan).includes('rds-regional-periodic'));
    const s3Plan = plans.find((plan) => JSON.stringify(plan).includes('critical-s3'));
    expect((rdsPlan?.BackupPlan as { BackupPlanRule?: unknown[] }).BackupPlanRule).toHaveLength(
      rdsCount,
    );
    expect((s3Plan?.BackupPlan as { BackupPlanRule?: unknown[] }).BackupPlanRule).toHaveLength(
      s3Count,
    );
    expect(plans).toHaveLength(2);
    expect(text).not.toContain('MoveToColdStorageAfterDays');
  });

  test.each([
    ['staging', 'launch-lean-v1'],
    ['production', 'launch-lean-v1'],
    ['production', 'production-standard-v1'],
    ['production', 'scale-ready-v1'],
  ] as const)('%s %s active fixture keeps native RDS retention at 35', (environment, profile) => {
    const config = activeBackupConfig(profile, undefined, environment);
    const data = templateJson(createBackupStage(config).dataStack);
    expect(resourceProperties(data, 'AWS::RDS::DBInstance')[0]?.BackupRetentionPeriod).toBe(35);
  });

  test.each([
    ['BackupDrActivationMode', 'regional-recovery-ready-v1'],
    ['BackupPolicyVersion', 'v1'],
    ['RecoveryTier', 'tier-1'],
    ['CostTier', 'usage-controlled'],
    ['RestoreValidation', 'false'],
  ] as const)('applies resource tag %s=%s', (key, value) => {
    const text = templateText(backupTemplate(activeBackupConfig()));
    expect(text).toContain(`"Key":"${key}","Value":"${value}"`);
  });
});
