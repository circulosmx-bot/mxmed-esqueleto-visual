import type { RenderedTemplate } from './operations-test-helpers';
import {
  costOnlyConfig,
  observabilityConfig,
  publicTrafficFixture,
  renderGlobal,
  resourcesOfType,
  serialized,
} from './operations-test-helpers';

function alarms(template: RenderedTemplate): readonly Record<string, unknown>[] {
  return resourcesOfType(template, 'AWS::CloudWatch::Alarm');
}

function alarmByCode(template: RenderedTemplate, code: string): Record<string, unknown> {
  const found = alarms(template).find((alarm) => serialized(alarm).includes(`code=${code}`));
  if (found === undefined) throw new Error(`global-alarm-fixture-missing:${code}`);
  return found;
}

let blockedLaunch: RenderedTemplate;
let publicLaunch: RenderedTemplate;
let blockedStandard: RenderedTemplate;
let publicStandard: RenderedTemplate;

beforeAll(() => {
  const blockedLaunchResult = renderGlobal(observabilityConfig());
  const publicLaunchResult = renderGlobal(publicTrafficFixture());
  const blockedStandardResult = renderGlobal(observabilityConfig('production-standard-v1'));
  const publicStandardResult = renderGlobal(publicTrafficFixture('production-standard-v1'));
  if (
    blockedLaunchResult.operations === undefined ||
    publicLaunchResult.operations === undefined ||
    blockedStandardResult.operations === undefined ||
    publicStandardResult.operations === undefined
  ) {
    throw new Error('global-operations-fixture-missing');
  }
  blockedLaunch = blockedLaunchResult.operations;
  publicLaunch = publicLaunchResult.operations;
  blockedStandard = blockedStandardResult.operations;
  publicStandard = publicStandardResult.operations;
});

describe('Operations global alarms', () => {
  test('omits CloudFront alarms while public traffic is blocked', () => {
    expect(alarms(blockedLaunch)).toHaveLength(0);
  });

  test('creates the CloudFront 5xx fixture when public traffic is integrated', () => {
    expect(alarmByCode(publicLaunch, 'cloudfront_5xx_rate')).toMatchObject({
      Threshold: 1,
      EvaluationPeriods: 5,
      DatapointsToAlarm: 5,
    });
  });

  test('uses Requests in the CloudFront 5xx math alarm', () => {
    expect(serialized(alarmByCode(publicLaunch, 'cloudfront_5xx_rate'))).toContain('Requests');
  });

  test('uses 5xxErrorRate in the CloudFront 5xx math alarm', () => {
    expect(serialized(alarmByCode(publicLaunch, 'cloudfront_5xx_rate'))).toContain('5xxErrorRate');
  });

  test('gates CloudFront rate at one hundred requests', () => {
    expect(serialized(alarmByCode(publicLaunch, 'cloudfront_5xx_rate'))).toContain('requests>=100');
  });

  test('uses DistributionId and Global dimensions', () => {
    const text = serialized(alarmByCode(publicLaunch, 'cloudfront_5xx_rate'));
    expect(text).toContain('DistributionId');
    expect(text).toContain('"Name":"Region","Value":"Global"');
  });

  test('places global Operations in us-east-1', () => {
    expect(renderGlobal(publicTrafficFixture()).stage.globalOperationsStack?.region).toBe(
      'us-east-1',
    );
  });

  test('creates standard WAF alarms even while public traffic remains blocked', () => {
    expect(alarms(blockedStandard)).toHaveLength(2);
    expect(serialized(alarms(blockedStandard))).toContain('waf_sensitive_rate_spike');
    expect(serialized(alarms(blockedStandard))).toContain('waf_general_rate_spike');
  });

  test('sets the sensitive WAF spike threshold at 50', () => {
    expect(alarmByCode(blockedStandard, 'waf_sensitive_rate_spike')).toMatchObject({
      Threshold: 50,
    });
  });

  test('sets the general WAF spike threshold at 500', () => {
    expect(alarmByCode(blockedStandard, 'waf_general_rate_spike')).toMatchObject({
      Threshold: 500,
    });
  });

  test('adds the standard total CloudFront error alarm after integration', () => {
    expect(alarmByCode(publicStandard, 'cloudfront_total_error_rate')).toMatchObject({
      Threshold: 5,
    });
  });

  test('creates exactly four global standard alarms in the integrated fixture', () => {
    expect(alarms(publicStandard)).toHaveLength(4);
  });

  test('enables no paid CloudFront monitoring subscription', () => {
    expect(resourcesOfType(publicStandard, 'AWS::CloudFront::MonitoringSubscription')).toHaveLength(
      0,
    );
  });

  test('does not create a global Operations stack when Global Edge is absent', () => {
    expect(renderGlobal(costOnlyConfig()).stage.globalOperationsStack).toBeUndefined();
  });
});
