import { App } from 'aws-cdk-lib';

import { getEnvironmentConfig } from '../lib/config/environments';
import { MxMedEnvironmentStage } from '../lib/stages/mxmed-environment-stage';
import type { RenderedTemplate } from './operations-test-helpers';
import {
  costOnlyConfig,
  observabilityConfig,
  renderEnvironment,
  renderGlobal,
  resourcesOfType,
  serialized,
} from './operations-test-helpers';

let regional: RenderedTemplate;
let security: RenderedTemplate;
let cost: RenderedTemplate;
let global: RenderedTemplate;

beforeAll(() => {
  const config = observabilityConfig();
  const environment = renderEnvironment(config);
  const globalStages = renderGlobal(config);
  regional = environment.operations;
  security = environment.security;
  cost = globalStages.cost;
  if (globalStages.operations === undefined) throw new Error('global-operations-fixture-missing');
  global = globalStages.operations;
});

describe('Operations SNS and KMS notification contract', () => {
  test('creates a cost alerts topic', () => {
    expect(resourcesOfType(cost, 'AWS::SNS::Topic')).toHaveLength(1);
  });

  test('creates regional critical and warning topics', () => {
    const topics = resourcesOfType(regional, 'AWS::SNS::Topic');
    expect(topics).toHaveLength(2);
    expect(serialized(topics)).toContain('regional-critical-alerts');
    expect(serialized(topics)).toContain('regional-warning-alerts');
  });

  test('creates a global Edge alerts topic', () => {
    expect(
      resourcesOfType(global, 'AWS::SNS::Topic').some(
        (topic) =>
          typeof topic.TopicName === 'string' && topic.TopicName.includes('global-edge-alerts'),
      ),
    ).toBe(true);
  });

  test('creates no subscriptions in any Operations template', () => {
    for (const template of [cost, regional, global]) {
      expect(resourcesOfType(template, 'AWS::SNS::Subscription')).toHaveLength(0);
    }
  });

  test('encrypts every Operations topic', () => {
    const topics = [cost, regional, global].flatMap((template) =>
      resourcesOfType(template, 'AWS::SNS::Topic'),
    );
    expect(topics.every((topic) => topic.KmsMasterKeyId !== undefined)).toBe(true);
  });

  test('reuses AuditKey regionally instead of creating another key', () => {
    expect(resourcesOfType(regional, 'AWS::KMS::Key')).toHaveLength(0);
    expect(serialized(regional)).toContain('AuditKey');
  });

  test('grants regional CloudWatch use through SNS on the existing AuditKey', () => {
    const text = serialized(security);
    expect(text).toContain('cloudwatch.amazonaws.com');
    expect(text).toContain('kms:GenerateDataKey*');
    expect(text).toContain('kms:Decrypt');
  });

  test('shares the cost key with global Edge alerts', () => {
    expect(resourcesOfType(global, 'AWS::KMS::Key')).toHaveLength(0);
    expect(serialized(global)).toContain('GlobalOperationsNotificationsKey');
  });

  test('allows Budgets to publish to the cost topic', () => {
    expect(serialized(resourcesOfType(cost, 'AWS::SNS::TopicPolicy'))).toContain(
      'budgets.amazonaws.com',
    );
  });

  test('allows Cost Anomaly Detection to publish to the cost topic', () => {
    expect(serialized(resourcesOfType(cost, 'AWS::SNS::TopicPolicy'))).toContain(
      'costalerts.amazonaws.com',
    );
  });

  test('allows CloudWatch to publish to regional and global topics', () => {
    expect(serialized(resourcesOfType(regional, 'AWS::SNS::TopicPolicy'))).toContain(
      'cloudwatch.amazonaws.com',
    );
    expect(serialized(resourcesOfType(global, 'AWS::SNS::TopicPolicy'))).toContain(
      'cloudwatch.amazonaws.com',
    );
  });

  test('uses SourceAccount tokens in every service topic policy', () => {
    for (const template of [cost, regional, global]) {
      const text = serialized(resourcesOfType(template, 'AWS::SNS::TopicPolicy'));
      expect(text).toContain('aws:SourceAccount');
      expect(text).toContain('AWS::AccountId');
    }
  });

  test('persists no wildcard principal or literal account identifier', () => {
    const text = serialized([cost, regional, global]);
    expect(text).not.toMatch(/"Principal":"\*"/);
    expect(text).not.toMatch(/\b[0-9]{12}\b/);
  });

  test('external mode still creates no personal subscriber resources', () => {
    const external = renderGlobal(
      costOnlyConfig('production', {
        operationsNotificationMode: 'external-subscribers-confirmed-v1',
      }),
    ).cost;
    expect(resourcesOfType(external, 'AWS::SNS::Subscription')).toHaveLength(0);
    expect(serialized(external)).not.toMatch(/@|mailto:|sms|https?:\/\//i);
  });

  test('notification none produces no Operations topics or alarms', () => {
    const config = getEnvironmentConfig('production', 'launch-lean-v1');
    const stage = new MxMedEnvironmentStage(new App(), 'DisabledOperations', { config });
    expect(stage.regionalOperationsStack).toBeUndefined();
  });
});
