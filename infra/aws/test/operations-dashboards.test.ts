import { App } from 'aws-cdk-lib';

import { getEnvironmentConfig } from '../lib/config/environments';
import { MxMedEnvironmentStage } from '../lib/stages/mxmed-environment-stage';
import {
  MXMED_GLOBAL_DASHBOARD_CONTRACT,
  MXMED_REGIONAL_DASHBOARD_CONTRACT,
} from '../lib/constructs/operations-dashboard-contract';
import type { RenderedTemplate } from './operations-test-helpers';
import {
  observabilityConfig,
  renderEnvironment,
  renderGlobal,
  resourcesOfType,
  serialized,
} from './operations-test-helpers';

let regional: RenderedTemplate;
let global: RenderedTemplate;

beforeAll(() => {
  const config = observabilityConfig();
  regional = renderEnvironment(config).operations;
  const renderedGlobal = renderGlobal(config).operations;
  if (renderedGlobal === undefined) throw new Error('global-dashboard-fixture-missing');
  global = renderedGlobal;
});

describe('Operations dashboards', () => {
  test('creates one regional dashboard', () => {
    expect(resourcesOfType(regional, 'AWS::CloudWatch::Dashboard')).toHaveLength(1);
  });

  test('uses the canonical regional dashboard name', () => {
    expect(resourcesOfType(regional, 'AWS::CloudWatch::Dashboard')[0]?.DashboardName).toContain(
      MXMED_REGIONAL_DASHBOARD_CONTRACT.name.toLowerCase(),
    );
  });

  test('contracts at most eight regional widgets', () => {
    expect(MXMED_REGIONAL_DASHBOARD_CONTRACT.maximumWidgets).toBe(8);
    expect(MXMED_REGIONAL_DASHBOARD_CONTRACT.widgets).toHaveLength(8);
  });

  test('omits blocked ALB metrics from the real regional dashboard', () => {
    const body = serialized(
      resourcesOfType(regional, 'AWS::CloudWatch::Dashboard')[0]?.DashboardBody,
    );
    expect(body).not.toContain('UnHealthyHostCount');
    expect(body).not.toContain('HTTPCode_Target_5XX_Count');
  });

  test('creates one global dashboard', () => {
    expect(resourcesOfType(global, 'AWS::CloudWatch::Dashboard')).toHaveLength(1);
  });

  test('uses the canonical global dashboard name', () => {
    expect(resourcesOfType(global, 'AWS::CloudWatch::Dashboard')[0]?.DashboardName).toContain(
      MXMED_GLOBAL_DASHBOARD_CONTRACT.name.toLowerCase(),
    );
  });

  test('contracts at most five global widgets', () => {
    expect(MXMED_GLOBAL_DASHBOARD_CONTRACT.maximumWidgets).toBe(5);
    expect(MXMED_GLOBAL_DASHBOARD_CONTRACT.widgets).toHaveLength(5);
  });

  test('uses periods no shorter than sixty seconds', () => {
    expect(MXMED_REGIONAL_DASHBOARD_CONTRACT.minimumPeriodSeconds).toBe(60);
    expect(MXMED_GLOBAL_DASHBOARD_CONTRACT.minimumPeriodSeconds).toBe(60);
    expect(serialized([regional, global])).not.toMatch(/"period":(?:[1-5]?[0-9])\b/i);
  });

  test('uses no Logs Insights widgets', () => {
    expect(MXMED_REGIONAL_DASHBOARD_CONTRACT.logWidgets).toBe(false);
    expect(MXMED_GLOBAL_DASHBOARD_CONTRACT.logWidgets).toBe(false);
    expect(serialized([regional, global])).not.toMatch(/LogQuery|Logs Insights/i);
  });

  test('enables no paid global metric option', () => {
    expect(MXMED_GLOBAL_DASHBOARD_CONTRACT.paidMetrics).toBe(false);
    expect(serialized(global)).not.toContain('AdditionalMetrics');
  });

  test('contains no personal or clinical dimension', () => {
    expect(serialized([regional, global])).not.toMatch(
      /patient_id|doctor_id|profile_id|payment_intent|session_id|filename|query string/i,
    );
  });

  test('does not create dashboards when Operations is disabled', () => {
    const stage = new MxMedEnvironmentStage(new App(), 'NoOperationsDashboard', {
      config: getEnvironmentConfig('production', 'launch-lean-v1'),
    });
    expect(stage.regionalOperationsStack).toBeUndefined();
  });
});
