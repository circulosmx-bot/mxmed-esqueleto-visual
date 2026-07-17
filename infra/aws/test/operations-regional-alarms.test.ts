import {
  MXMED_LAUNCH_LEAN_ALARM_CATALOG,
  deriveRdsConnectionBudget,
  storageThresholdBytes,
} from '../lib/constructs/operations-alarm-catalog';
import type { RenderedTemplate } from './operations-test-helpers';
import {
  observabilityConfig,
  publicTrafficFixture,
  renderEnvironment,
  resourcesOfType,
  serialized,
} from './operations-test-helpers';

function alarms(template: RenderedTemplate): readonly Record<string, unknown>[] {
  return resourcesOfType(template, 'AWS::CloudWatch::Alarm');
}

function alarmByCode(template: RenderedTemplate, code: string): Record<string, unknown> {
  const found = alarms(template).find((alarm) => serialized(alarm).includes(`code=${code}`));
  if (found === undefined) throw new Error(`alarm-fixture-missing:${code}`);
  return found;
}

let launch: RenderedTemplate;
let integrated: RenderedTemplate;

beforeAll(() => {
  launch = renderEnvironment(observabilityConfig()).operations;
  integrated = renderEnvironment(publicTrafficFixture()).operations;
});

describe('Operations launch-lean regional alarms', () => {
  test('creates eight real alarms while ALB readiness remains blocked', () => {
    expect(alarms(launch)).toHaveLength(8);
  });

  test('keeps the conceptual launch catalog at a maximum of eleven', () => {
    expect(MXMED_LAUNCH_LEAN_ALARM_CATALOG).toHaveLength(11);
  });

  test('uses DesiredTaskCount in the ECS deficit alarm', () => {
    expect(serialized(alarmByCode(launch, 'ecs_task_deficit'))).toContain('DesiredTaskCount');
  });

  test('uses RunningTaskCount in the ECS deficit alarm', () => {
    expect(serialized(alarmByCode(launch, 'ecs_task_deficit'))).toContain('RunningTaskCount');
  });

  test('calculates a nonnegative ECS running task deficit', () => {
    expect(serialized(alarmByCode(launch, 'ecs_task_deficit'))).toContain(
      'MAX([desired-running,0])',
    );
  });

  test('sets ECS high CPU at 75 percent', () => {
    expect(alarmByCode(launch, 'ecs_high_cpu')).toMatchObject({
      Threshold: 75,
      EvaluationPeriods: 3,
      DatapointsToAlarm: 3,
    });
  });

  test('sets ECS high memory at 80 percent', () => {
    expect(alarmByCode(launch, 'ecs_high_memory')).toMatchObject({
      Threshold: 80,
      EvaluationPeriods: 3,
      DatapointsToAlarm: 3,
    });
  });

  test('creates the RDS CPU alarm', () => {
    expect(alarmByCode(launch, 'rds_high_cpu')).toMatchObject({ MetricName: 'CPUUtilization' });
  });

  test('derives the RDS storage threshold from allocated GiB', () => {
    expect(alarmByCode(launch, 'rds_low_free_storage')).toMatchObject({
      Threshold: storageThresholdBytes(observabilityConfig().databaseAllocatedStorageGiB, 20),
      ComparisonOperator: 'LessThanOrEqualToThreshold',
    });
  });

  test('derives the launch RDS connection budget without a lookup', () => {
    expect(deriveRdsConnectionBudget(2)).toEqual({
      totalConnectionBudget: 54,
      alarmThreshold: 38,
    });
  });

  test('uses the derived RDS connection threshold in the template', () => {
    expect(alarmByCode(launch, 'rds_connection_budget')).toMatchObject({ Threshold: 38 });
  });

  test('creates an eviction alarm against the primary Valkey node', () => {
    const alarm = alarmByCode(launch, 'valkey_evictions');
    expect(alarm).toMatchObject({ MetricName: 'Evictions' });
    expect(serialized(alarm)).toContain('mxmed-prd-session-001');
  });

  test('creates a 75 percent Valkey memory pressure alarm', () => {
    expect(alarmByCode(launch, 'valkey_memory_pressure')).toMatchObject({
      MetricName: 'DatabaseMemoryUsagePercentage',
      Threshold: 75,
    });
  });

  test('does not add a second FreeableMemory alarm in launch mode', () => {
    expect(serialized(alarms(launch))).not.toContain('FreeableMemory');
  });

  test('omits ALB alarms while readyz remains blocked', () => {
    expect(serialized(alarms(launch))).not.toMatch(/alb_unhealthy_target|alb_target_5xx_rate/);
  });

  test('includes severity, runbook and sanitized code in every alarm', () => {
    for (const alarm of alarms(launch)) {
      expect(alarm.AlarmDescription).toEqual(expect.stringContaining('severity='));
      expect(alarm.AlarmDescription).toEqual(expect.stringContaining('runbook='));
      expect(alarm.AlarmDescription).toEqual(expect.stringContaining('code='));
    }
  });

  test('treats missing data as non-breaching', () => {
    expect(alarms(launch).every((alarm) => alarm.TreatMissingData === 'notBreaching')).toBe(true);
  });

  test('defines no OK actions', () => {
    expect(alarms(launch).every((alarm) => alarm.OKActions === undefined)).toBe(true);
  });

  test('defines no insufficient-data actions', () => {
    expect(alarms(launch).every((alarm) => alarm.InsufficientDataActions === undefined)).toBe(true);
  });

  test('routes each SEV2 or SEV3 alarm to one topic', () => {
    expect(
      alarms(launch).every(
        (alarm) => Array.isArray(alarm.AlarmActions) && alarm.AlarmActions.length === 1,
      ),
    ).toBe(true);
  });

  test('creates the integrated ALB unhealthy target fixture', () => {
    expect(alarmByCode(integrated, 'alb_unhealthy_target')).toMatchObject({
      MetricName: 'UnHealthyHostCount',
      Threshold: 1,
      EvaluationPeriods: 3,
      DatapointsToAlarm: 2,
    });
  });

  test('creates the integrated ALB target 5xx math alarm', () => {
    expect(serialized(alarmByCode(integrated, 'alb_target_5xx_rate'))).toContain(
      'HTTPCode_Target_5XX_Count',
    );
  });

  test('gates ALB 5xx calculation at twenty requests', () => {
    expect(serialized(alarmByCode(integrated, 'alb_target_5xx_rate'))).toContain('requests>=20');
  });

  test('adds only the two contracted ALB alarms when runtime is integrated', () => {
    expect(alarms(integrated)).toHaveLength(10);
  });

  test('uses one-minute periods for deficit and ALB rate gates', () => {
    expect(serialized(alarmByCode(integrated, 'ecs_task_deficit'))).toContain('"Period":60');
    expect(serialized(alarmByCode(integrated, 'alb_target_5xx_rate'))).toContain('"Period":60');
  });
});
