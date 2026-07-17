import {
  activeBackupConfig,
  backupTemplate,
  createBackupStage,
  crossRegionBackupConfig,
  resourceEntries,
  resourceProperties,
  templateJson,
  templateText,
} from './backup-dr-test-helpers';

const config = crossRegionBackupConfig();
const stage = createBackupStage(config);
const regional = backupTemplate(config);
const destination = templateJson(stage.drCopyStack ?? stage.networkStack);

interface CopyRule {
  readonly RuleName?: string;
  readonly EnableContinuousBackup?: boolean;
  readonly CopyActions?: readonly unknown[];
}

function regionalRules(): CopyRule[] {
  return resourceProperties(regional, 'AWS::Backup::BackupPlan').flatMap((properties) => {
    const plan = properties.BackupPlan as { BackupPlanRule?: CopyRule[] } | undefined;
    return plan?.BackupPlanRule ?? [];
  });
}

describe('explicit offline cross-region handoff', () => {
  test('regional mode does not select a DR region', () => {
    expect(activeBackupConfig().drRegion).toBeUndefined();
    expect(activeBackupConfig().drRegionState).toBe('not-selected-v1');
  });

  test('rejects cross-region activation without a region', () => {
    expect(() =>
      activeBackupConfig('production-standard-v1', {
        backupDrActivationMode: 'cross-region-copy-ready-v1',
        drRegionState: 'selected-and-verified-v1',
        backupDataResidencyState: 'approved-v1',
      }),
    ).toThrow('dr_region_not_selected_or_verified');
  });

  test('rejects cross-region activation without residency approval', () => {
    expect(() =>
      activeBackupConfig('production-standard-v1', {
        backupDrActivationMode: 'cross-region-copy-ready-v1',
        drRegionState: 'selected-and-verified-v1',
        drRegion: 'us-test-1',
      }),
    ).toThrow('dr_region_not_selected_or_verified');
  });

  test('creates an independent destination stack in the explicit fixture region', () => {
    expect(stage.drCopyStack?.region).toBe('us-test-1');
    expect(stage.drCopyStack?.dependencies).toHaveLength(0);
  });

  test.each([
    ['AWS::KMS::Key', 1],
    ['AWS::KMS::Alias', 1],
    ['AWS::Backup::BackupVault', 1],
  ] as const)('destination creates exactly %s x %d', (type, count) => {
    expect(resourceEntries(destination, type)).toHaveLength(count);
  });

  test('destination key rotates and is retained', () => {
    const key = resourceEntries(destination, 'AWS::KMS::Key')[0]?.[1];
    expect(key?.Properties?.EnableKeyRotation).toBe(true);
    expect(key).toMatchObject({ DeletionPolicy: 'Retain', UpdateReplacePolicy: 'Retain' });
  });

  test('destination vault uses governance lock and its destination key', () => {
    const vault = resourceProperties(destination, 'AWS::Backup::BackupVault')[0];
    expect(vault?.LockConfiguration).toEqual({ MinRetentionDays: 1, MaxRetentionDays: 365 });
    expect(JSON.stringify(vault?.EncryptionKeyArn)).toContain('DestinationBackupKey');
  });

  test.each(['SourceRegionalBackupVaultArn', 'DrDestinationBackupVaultArn'])(
    '%s is a no-default parameter',
    (parameterName) => {
      const selected = parameterName.startsWith('Source') ? destination : regional;
      const parameter = Object.entries(selected.Parameters ?? {}).find(([id]) =>
        id.includes(parameterName),
      )?.[1];
      expect(parameter).toBeDefined();
      expect(parameter?.Default).toBeUndefined();
    },
  );

  test.each(['RdsDailyRegional', 'RdsMonthlyRegional', 'CriticalS3DailyPeriodic'])(
    'adds a periodic copy action to %s',
    (ruleName) => {
      const rule = regionalRules().find((candidate) => candidate.RuleName === ruleName);
      expect(rule?.EnableContinuousBackup).toBe(false);
      expect(rule?.CopyActions).toHaveLength(1);
    },
  );

  test('never adds a copy action to the continuous S3 rule', () => {
    const rule = regionalRules().find((candidate) => candidate.RuleName === 'CriticalS3Continuous');
    expect(rule?.EnableContinuousBackup).toBe(true);
    expect(rule?.CopyActions).toBeUndefined();
  });

  test('has no cross-account policy or cross-region export', () => {
    const text = `${templateText(regional)}${templateText(destination)}`;
    expect(text).not.toMatch(/Organization|PrincipalOrgID|AWS::CloudFormation::Export/);
    expect(regional.Outputs).toBeUndefined();
    expect(destination.Outputs).toBeUndefined();
  });

  test('does not include PublicMedia or Quarantine in copied selections', () => {
    expect(templateText(regional)).not.toMatch(/PublicMediaBucket|UploadQuarantineBucket/);
  });
});
