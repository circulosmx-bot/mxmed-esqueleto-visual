import type { BackupTemplate } from './backup-dr-test-helpers';
import {
  activeBackupConfig,
  backupTemplate,
  resourceEntries,
  resourceProperties,
} from './backup-dr-test-helpers';

interface Rule {
  readonly RuleName?: string;
  readonly ScheduleExpression?: string;
  readonly ScheduleExpressionTimezone?: string;
  readonly StartWindowMinutes?: number;
  readonly CompletionWindowMinutes?: number;
  readonly EnableContinuousBackup?: boolean;
  readonly Lifecycle?: Readonly<Record<string, unknown>>;
  readonly CopyActions?: readonly unknown[];
}

function rdsRules(template: BackupTemplate): Rule[] {
  const plan = resourceEntries(template, 'AWS::Backup::BackupPlan').find(([id]) =>
    id.includes('RdsRegionalPeriodicBackupPlan'),
  );
  const properties = plan?.[1].Properties;
  const backupPlan = properties?.BackupPlan as { BackupPlanRule?: Rule[] } | undefined;
  return backupPlan?.BackupPlanRule ?? [];
}

const launch = backupTemplate(activeBackupConfig());
const standard = backupTemplate(activeBackupConfig('production-standard-v1'));

describe('RDS periodic AWS Backup plan', () => {
  test('creates one RDS plan and one exact selection', () => {
    expect(rdsRules(launch)).toHaveLength(1);
    const selections = resourceEntries(launch, 'AWS::Backup::BackupSelection').filter(([id]) =>
      id.includes('RdsCriticalSelection'),
    );
    expect(selections).toHaveLength(1);
  });

  test.each([
    ['RuleName', 'RdsDailyRegional'],
    ['ScheduleExpression', 'cron(0 3 * * ? *)'],
    ['ScheduleExpressionTimezone', 'UTC'],
    ['StartWindowMinutes', 60],
    ['CompletionWindowMinutes', 360],
    ['EnableContinuousBackup', false],
  ] as const)('sets daily %s=%s', (field, expected) => {
    expect(rdsRules(launch)[0]?.[field]).toBe(expected);
  });

  test('retains launch daily recovery points for 35 days with no cold storage', () => {
    expect(rdsRules(launch)[0]?.Lifecycle).toEqual({ DeleteAfterDays: 35 });
  });

  test('does not attach copy actions in regional mode', () => {
    expect(rdsRules(launch)[0]?.CopyActions).toBeUndefined();
  });

  test('adds daily and monthly rules for standard', () => {
    expect(rdsRules(standard).map((rule) => rule.RuleName)).toEqual([
      'RdsDailyRegional',
      'RdsMonthlyRegional',
    ]);
  });

  test.each([
    ['ScheduleExpression', 'cron(0 3 ? * SUN#1 *)'],
    ['ScheduleExpressionTimezone', 'UTC'],
    ['StartWindowMinutes', 60],
    ['CompletionWindowMinutes', 360],
    ['EnableContinuousBackup', false],
  ] as const)('sets monthly %s=%s', (field, expected) => {
    expect(rdsRules(standard)[1]?.[field]).toBe(expected);
  });

  test('retains the monthly standard recovery point for 365 days', () => {
    expect(rdsRules(standard)[1]?.Lifecycle).toEqual({ DeleteAfterDays: 365 });
  });

  test('selects exactly one generated DB instance ARN without a wildcard', () => {
    const selection = resourceProperties(launch, 'AWS::Backup::BackupSelection').find(
      (properties) => JSON.stringify(properties).includes('rds-critical'),
    );
    const text = JSON.stringify(selection);
    expect(text).toContain('DatabaseInstance');
    expect(text).not.toContain('arn:aws:rds:*');
    expect(text).not.toMatch(/"Resources":\["[^"]*\*"/);
  });
});
