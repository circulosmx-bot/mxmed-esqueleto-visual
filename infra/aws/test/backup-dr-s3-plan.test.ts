import type { BackupTemplate } from './backup-dr-test-helpers';
import {
  activeBackupConfig,
  backupTemplate,
  resourceEntries,
  resourceProperties,
  templateText,
} from './backup-dr-test-helpers';

interface Rule {
  readonly RuleName?: string;
  readonly TargetBackupVaultName?: unknown;
  readonly ScheduleExpression?: string;
  readonly EnableContinuousBackup?: boolean;
  readonly Lifecycle?: Readonly<Record<string, unknown>>;
}

function s3Rules(template: BackupTemplate): Rule[] {
  const plan = resourceEntries(template, 'AWS::Backup::BackupPlan').find(([id]) =>
    id.includes('CriticalS3BackupPlan'),
  );
  const backupPlan = plan?.[1].Properties?.BackupPlan as { BackupPlanRule?: Rule[] } | undefined;
  return backupPlan?.BackupPlanRule ?? [];
}

const launch = backupTemplate(activeBackupConfig());
const standard = backupTemplate(activeBackupConfig('production-standard-v1'));

describe('critical S3 AWS Backup plan', () => {
  test.each([
    ['launch', launch, 1],
    ['standard', standard, 3],
  ] as const)('%s has the expected rule count', (_label, template, count) => {
    expect(s3Rules(template)).toHaveLength(count);
  });

  test.each([
    ['RuleName', 'CriticalS3Continuous'],
    ['EnableContinuousBackup', true],
  ] as const)('sets continuous %s=%s', (field, expected) => {
    expect(s3Rules(launch)[0]?.[field]).toBe(expected);
  });

  test('retains continuous recovery points for 35 days', () => {
    expect(s3Rules(launch)[0]?.Lifecycle).toEqual({ DeleteAfterDays: 35 });
  });

  test('has exactly one continuous rule in every regional profile', () => {
    for (const template of [launch, standard]) {
      expect(s3Rules(template).filter((rule) => rule.EnableContinuousBackup)).toHaveLength(1);
    }
  });

  test.each([
    ['CriticalS3DailyPeriodic', 'cron(0 3 * * ? *)', 35],
    ['CriticalS3MonthlyPeriodic', 'cron(0 3 ? * SUN#1 *)', 365],
  ] as const)('standard %s uses the contractual schedule and retention', (name, schedule, days) => {
    const rule = s3Rules(standard).find((candidate) => candidate.RuleName === name);
    expect(rule?.ScheduleExpression).toBe(schedule);
    expect(rule?.Lifecycle).toEqual({ DeleteAfterDays: days });
    expect(rule?.EnableContinuousBackup).toBe(false);
  });

  test('uses one vault for continuous and periodic rules', () => {
    const vaults = new Set(
      s3Rules(standard).map((rule) => JSON.stringify(rule.TargetBackupVaultName)),
    );
    expect(vaults.size).toBe(1);
  });

  test('selects exactly Clinical and Private bucket ARNs', () => {
    const selection = resourceProperties(launch, 'AWS::Backup::BackupSelection').find(
      (properties) => JSON.stringify(properties).includes('critical-s3'),
    );
    const resources = (selection?.BackupSelection as { Resources?: readonly unknown[] } | undefined)
      ?.Resources;
    expect(resources).toHaveLength(2);
    expect(JSON.stringify(resources)).toMatch(/ClinicalRecordsBucket/);
    expect(JSON.stringify(resources)).toMatch(/PrivateDocumentsBucket/);
  });

  test.each(['PublicMedia', 'UploadQuarantine', 'AuditBucket'])(
    'excludes %s from every S3 selection',
    (forbidden) => {
      const selections = resourceProperties(standard, 'AWS::Backup::BackupSelection');
      expect(JSON.stringify(selections)).not.toContain(forbidden);
    },
  );

  test('does not select all buckets with a wildcard', () => {
    expect(templateText(standard)).not.toMatch(/arn:[^" ]*:s3:::\*/);
  });
});
