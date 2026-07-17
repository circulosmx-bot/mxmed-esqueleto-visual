import {
  createBackupStage,
  resourceEntries,
  resourceProperties,
  restoreBackupConfig,
  templateJson,
  templateText,
} from './backup-dr-test-helpers';

const manualStage = createBackupStage(restoreBackupConfig('manual-quarterly-v1'));
const manual = templateJson(manualStage.restoreValidationStack ?? manualStage.networkStack);
const scheduledStage = createBackupStage(restoreBackupConfig('scheduled-monthly-v1'));
const scheduled = templateJson(
  scheduledStage.restoreValidationStack ?? scheduledStage.networkStack,
);

describe('isolated restore-testing fixture', () => {
  test.each([
    ['AWS::Backup::RestoreTestingPlan', 0],
    ['AWS::Backup::RestoreTestingSelection', 0],
  ] as const)('manual quarterly creates %s x %d', (type, count) => {
    expect(resourceEntries(manual, type)).toHaveLength(count);
  });

  test.each([
    ['AWS::Backup::RestoreTestingPlan', 1],
    ['AWS::Backup::RestoreTestingSelection', 2],
    ['AWS::EC2::SecurityGroup', 1],
    ['AWS::S3::Bucket', 1],
    ['AWS::IAM::Role', 1],
  ] as const)('scheduled fixture creates %s x %d', (type, count) => {
    expect(resourceEntries(scheduled, type)).toHaveLength(count);
  });

  test('uses a monthly UTC plan against the regional vault', () => {
    const plan = resourceProperties(scheduled, 'AWS::Backup::RestoreTestingPlan')[0];
    expect(plan?.ScheduleExpression).toBe('cron(0 5 ? * SUN#1 *)');
    expect(plan?.ScheduleExpressionTimezone).toBe('UTC');
    expect(JSON.stringify(plan?.RecoveryPointSelection)).toContain('RegionalRecoveryVault');
  });

  test.each(['RDS', 'S3'])('creates one exact %s restore selection', (type) => {
    const selections = resourceProperties(scheduled, 'AWS::Backup::RestoreTestingSelection');
    expect(selections.filter((selection) => selection.ProtectedResourceType === type)).toHaveLength(
      1,
    );
  });

  test.each([
    ['PubliclyAccessible', 'false'],
    ['MultiAZ', 'false'],
    ['DeletionProtection', 'false'],
    ['Engine', 'mysql'],
    ['DBInstanceClass', 'db.t4g.medium'],
  ] as const)('RDS temporary restore sets %s=%s', (field, expected) => {
    const rds = resourceProperties(scheduled, 'AWS::Backup::RestoreTestingSelection').find(
      (selection) => selection.ProtectedResourceType === 'RDS',
    );
    const metadata = rds?.RestoreMetadataOverrides as Record<string, unknown> | undefined;
    expect(metadata?.[field]).toBe(expected);
  });

  test('uses an isolated zero-ingress, zero-egress security group', () => {
    const group = resourceProperties(scheduled, 'AWS::EC2::SecurityGroup')[0];
    expect(group?.SecurityGroupIngress).toBeUndefined();
    expect(group?.SecurityGroupEgress).toBeUndefined();
  });

  test('keeps the temporary bucket private, versioned and encrypted', () => {
    const bucket = resourceProperties(scheduled, 'AWS::S3::Bucket')[0];
    expect(bucket?.PublicAccessBlockConfiguration).toEqual({
      BlockPublicAcls: true,
      BlockPublicPolicy: true,
      IgnorePublicAcls: true,
      RestrictPublicBuckets: true,
    });
    expect(bucket?.VersioningConfiguration).toEqual({ Status: 'Enabled' });
    expect(bucket?.BucketEncryption).toBeDefined();
  });

  test.each([
    'RestoreTestMonthlyBudgetUsd',
    'RestoreTestMaximumRuntimeHours',
    'RestoreTestApprovedInstanceClass',
    'RestoreTestOwnerReference',
    'RestoreTestCleanupDeadlineHours',
  ])('%s is explicit and has no default', (name) => {
    const parameter = Object.entries(scheduled.Parameters ?? {}).find(([id]) =>
      id.includes(name),
    )?.[1];
    expect(parameter).toBeDefined();
    expect(parameter?.Default).toBeUndefined();
  });

  test('contains no public edge, production secret, validator Lambda or automatic cleanup', () => {
    const text = templateText(scheduled);
    expect(text).not.toMatch(/AWS::Lambda|CloudFront|Route53|PublicIp|SecretString/);
    expect(text).not.toMatch(/AutoDeleteObjects|Custom::S3AutoDeleteObjects/);
  });

  test('requires sentinels before scheduled restore testing', () => {
    expect(() =>
      createBackupStage({
        ...restoreBackupConfig('scheduled-monthly-v1'),
        backupSentinelsIntegrated: false,
      }),
    ).toThrow('restore_testing_sentinels_not_integrated');
  });

  test('requires application validation before scheduled restore testing', () => {
    expect(() =>
      createBackupStage({
        ...restoreBackupConfig('scheduled-monthly-v1'),
        backupApplicationValidationIntegrated: false,
      }),
    ).toThrow('restore_testing_sentinels_not_integrated');
  });
});
