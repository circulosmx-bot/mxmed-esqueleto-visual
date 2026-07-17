import type { RenderedTemplate } from './operations-test-helpers';
import {
  costOnlyConfig,
  renderGlobal,
  resourcesOfType,
  serialized,
} from './operations-test-helpers';

let template: RenderedTemplate;

beforeAll(() => {
  template = renderGlobal(costOnlyConfig()).cost;
});

describe('Operations cost anomaly detection', () => {
  test('creates one dimensional service monitor by default', () => {
    const monitors = resourcesOfType(template, 'AWS::CE::AnomalyMonitor');
    expect(monitors).toHaveLength(1);
    expect(monitors[0]).toMatchObject({ MonitorType: 'DIMENSIONAL', MonitorDimension: 'SERVICE' });
  });

  test('uses a deterministic service monitor name', () => {
    expect(resourcesOfType(template, 'AWS::CE::AnomalyMonitor')[0]).toMatchObject({
      MonitorName: 'mxmed-service-cost-monitor',
    });
  });

  test('imports an approved existing service monitor without creating one', () => {
    const imported = renderGlobal(
      costOnlyConfig('production', {
        costAnomalyMonitorOwnershipMode: 'import-existing-service-monitor-v1',
      }),
    ).cost;
    expect(resourcesOfType(imported, 'AWS::CE::AnomalyMonitor')).toHaveLength(0);
    expect(imported.Parameters?.ExistingServiceAnomalyMonitorArn).toBeDefined();
  });

  test('uses immediate anomaly notification frequency', () => {
    expect(resourcesOfType(template, 'AWS::CE::AnomalySubscription')[0]).toMatchObject({
      Frequency: 'IMMEDIATE',
    });
  });

  test('uses only an SNS anomaly subscriber', () => {
    const subscription = resourcesOfType(template, 'AWS::CE::AnomalySubscription')[0];
    expect(subscription?.Subscribers).toHaveLength(1);
    expect(serialized(subscription?.Subscribers)).toContain('"Type":"SNS"');
    expect(serialized(subscription?.Subscribers)).toContain('"Address"');
  });

  test('uses a deterministic absolute impact ThresholdExpression', () => {
    const subscription = resourcesOfType(template, 'AWS::CE::AnomalySubscription')[0];
    expect(serialized(subscription?.ThresholdExpression)).toContain(
      'ANOMALY_TOTAL_IMPACT_ABSOLUTE',
    );
    expect(serialized(subscription?.ThresholdExpression)).toContain('GREATER_THAN_OR_EQUAL');
  });

  test('does not use the deprecated Threshold property', () => {
    expect(resourcesOfType(template, 'AWS::CE::AnomalySubscription')[0]).not.toHaveProperty(
      'Threshold',
    );
  });

  test('references the required anomaly threshold parameter', () => {
    expect(template.Parameters?.AnomalyAlertThresholdUsd).toBeDefined();
    expect(serialized(resourcesOfType(template, 'AWS::CE::AnomalySubscription'))).toContain(
      'AnomalyAlertThresholdUsd',
    );
  });

  test('keeps the tag monitor disabled while CostScope is inactive', () => {
    expect(resourcesOfType(template, 'AWS::CE::AnomalyMonitor')).toHaveLength(1);
    expect(serialized(resourcesOfType(template, 'AWS::CE::AnomalyMonitor'))).not.toContain(
      'CostScopeTagAnomalyMonitor',
    );
  });

  test('creates a custom CostScope monitor only in the verified fixture', () => {
    const enabled = renderGlobal(
      costOnlyConfig('production', {
        costAllocationTagState: 'active-and-verified-v1',
        costTagAnomalyMonitorMode: 'enabled-v1',
      }),
    ).cost;
    const monitors = resourcesOfType(enabled, 'AWS::CE::AnomalyMonitor');
    expect(monitors).toHaveLength(2);
    expect(monitors).toContainEqual(expect.objectContaining({ MonitorType: 'CUSTOM' }));
    const specification = monitors.find(
      (monitor) => monitor.MonitorType === 'CUSTOM',
    )?.MonitorSpecification;
    if (typeof specification !== 'string') throw new Error('custom-monitor-specification-missing');
    const parsed: unknown = JSON.parse(specification);
    expect(parsed).toEqual({
      Tags: {
        Key: 'CostScope',
        MatchOptions: ['EQUALS'],
        Values: ['mxmed-production', 'mxmed-staging'],
      },
    });
  });

  test('adds both monitor ARNs to the verified anomaly subscription', () => {
    const enabled = renderGlobal(
      costOnlyConfig('production', {
        costAllocationTagState: 'active-and-verified-v1',
        costTagAnomalyMonitorMode: 'enabled-v1',
      }),
    ).cost;
    expect(
      resourcesOfType(enabled, 'AWS::CE::AnomalySubscription')[0]?.MonitorArnList,
    ).toHaveLength(2);
  });

  test('rejects a tag monitor before CostScope activation', () => {
    expect(() => costOnlyConfig('production', { costTagAnomalyMonitorMode: 'enabled-v1' })).toThrow(
      'MXMED_OPERATIONS_COST_TAG_MONITOR_GATE_CLOSED',
    );
  });
});
