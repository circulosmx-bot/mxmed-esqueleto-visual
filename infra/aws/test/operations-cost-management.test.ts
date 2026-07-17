import type { RenderedTemplate } from './operations-test-helpers';
import {
  costOnlyConfig,
  renderGlobal,
  resourceEntriesOfType,
  resourcesOfType,
  serialized,
} from './operations-test-helpers';

let template: RenderedTemplate;

beforeAll(() => {
  template = renderGlobal(costOnlyConfig()).cost;
});

describe('Operations cost management stack', () => {
  test('creates only the account-scoped cost stack in cost-controls mode', () => {
    const rendered = renderGlobal(costOnlyConfig());
    expect(rendered.stage.globalEdgeStack).toBeUndefined();
    expect(rendered.stage.globalOperationsStack).toBeUndefined();
  });

  test('pins cost management to us-east-1', () => {
    expect(renderGlobal(costOnlyConfig()).stage.costManagementStack.region).toBe('us-east-1');
  });

  test('creates exactly one global Operations KMS key', () => {
    expect(resourcesOfType(template, 'AWS::KMS::Key')).toHaveLength(1);
  });

  test('rotates and retains the global Operations key', () => {
    const key = resourceEntriesOfType(template, 'AWS::KMS::Key')[0]?.[1];
    if (key === undefined) throw new Error('cost-key-fixture-missing');
    expect(key.Properties).toMatchObject({ EnableKeyRotation: true });
    expect(key.DeletionPolicy).toBe('Retain');
    expect(key.UpdateReplacePolicy).toBe('Retain');
  });

  test('creates the deterministic key alias', () => {
    expect(resourcesOfType(template, 'AWS::KMS::Alias')).toContainEqual(
      expect.objectContaining({ AliasName: 'alias/mxmed-global-operations-notifications' }),
    );
  });

  test('creates one encrypted cost alerts topic', () => {
    const topics = resourcesOfType(template, 'AWS::SNS::Topic');
    expect(topics).toHaveLength(1);
    expect(topics[0]).toMatchObject({ TopicName: 'mxmed-cost-alerts' });
    expect(topics[0]?.KmsMasterKeyId).toBeDefined();
  });

  test('creates no external subscription', () => {
    expect(resourcesOfType(template, 'AWS::SNS::Subscription')).toHaveLength(0);
    expect(serialized(resourcesOfType(template, 'AWS::SNS::Topic'))).not.toMatch(/Subscription/);
  });

  test.each([
    'ApprovedMonthlyBudgetUsd',
    'StagingMonthlyBudgetUsd',
    'AnomalyAlertThresholdUsd',
    'MaxInfrastructureCostToRevenuePercent',
  ])('keeps monetary parameter %s without a default', (parameter) => {
    expect(template.Parameters?.[parameter]).toBeDefined();
    expect(template.Parameters?.[parameter]).not.toHaveProperty('Default');
  });

  test('requires an opaque owner reference with no personal destination', () => {
    const owner = template.Parameters?.BudgetOwnerReference;
    expect(owner).toMatchObject({ Type: 'String', MinLength: 3, MaxLength: 128 });
    expect(String(owner?.AllowedPattern)).not.toContain('@');
  });

  test('requires an explicit review cadence', () => {
    expect(template.Parameters?.CostReviewCadence).toMatchObject({
      AllowedValues: ['daily-first-30-days-v1', 'weekly-after-first-month-v1'],
    });
  });

  test('requires cost allocation tag verification at deployment', () => {
    expect(template.Parameters?.CostAllocationTagsVerified).toBeDefined();
    expect(template.Rules?.CostAllocationTagsDeploymentGate).toBeDefined();
  });

  test('adds the subscriber verification rule only for external mode', () => {
    const external = renderGlobal(
      costOnlyConfig('production', {
        operationsNotificationMode: 'external-subscribers-confirmed-v1',
      }),
    ).cost;
    expect(external.Rules?.ExternalSubscribersDeploymentGate).toBeDefined();
  });

  test('exports only safe identifiers and state', () => {
    expect(Object.keys(template.Outputs ?? {}).sort()).toEqual(
      [
        'AnomalyMonitorArn',
        'AnomalySubscriptionArn',
        'CostAlertsTopicArn',
        'CostControlState',
        'GlobalNotificationsKeyArn',
        'ProductionBudgetId',
        'StagingBudgetId',
      ].sort(),
    );
  });
});
