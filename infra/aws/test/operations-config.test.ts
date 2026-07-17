import { App } from 'aws-cdk-lib';

import {
  MXMED_AWS_OPERATIONS_FOUNDATION_IMPLEMENTATION_V1,
  MXMED_CLINICAL_LOG_SANITIZATION_STATES,
  MXMED_COST_ALLOCATION_TAG_STATES,
  MXMED_COST_ANOMALY_MONITOR_OWNERSHIP_MODES,
  MXMED_COST_TAG_ANOMALY_MONITOR_MODES,
  MXMED_OPERATIONS_ACTIVATION_MODES,
  MXMED_OPERATIONS_LOG_PROTECTION_PROFILES,
  MXMED_OPERATIONS_NOTIFICATION_MODES,
  MXMED_OPERATIONS_RUNTIME_GATE_STATES,
  MXMED_REAL_OPERATIONS_GATES,
} from '../lib/config/operations-profiles';
import { getEnvironmentConfig } from '../lib/config/environments';
import { MxMedEnvironmentStage } from '../lib/stages/mxmed-environment-stage';
import { costOnlyConfig, observabilityConfig } from './operations-test-helpers';

describe('Operations activation and configuration', () => {
  test('publishes the implementation contract identifier', () => {
    expect(MXMED_AWS_OPERATIONS_FOUNDATION_IMPLEMENTATION_V1).toBe(
      'MXMED_AWS_OPERATIONS_FOUNDATION_IMPLEMENTATION_V1',
    );
  });

  test.each([
    ['activation', MXMED_OPERATIONS_ACTIVATION_MODES, 4],
    ['notification', MXMED_OPERATIONS_NOTIFICATION_MODES, 3],
    ['log protection', MXMED_OPERATIONS_LOG_PROTECTION_PROFILES, 2],
    ['runtime gate', MXMED_OPERATIONS_RUNTIME_GATE_STATES, 2],
    ['clinical gate', MXMED_CLINICAL_LOG_SANITIZATION_STATES, 2],
    ['cost tag state', MXMED_COST_ALLOCATION_TAG_STATES, 2],
    ['anomaly ownership', MXMED_COST_ANOMALY_MONITOR_OWNERSHIP_MODES, 2],
    ['tag anomaly mode', MXMED_COST_TAG_ANOMALY_MONITOR_MODES, 2],
  ])('defines the exact %s selections', (_label, values, size) => {
    expect(values).toHaveLength(size);
    expect(new Set(values).size).toBe(size);
  });

  test('defaults to disabled Operations with no notifications', () => {
    const config = getEnvironmentConfig('production', 'launch-lean-v1');
    expect(config.operationsActivationMode).toBe('disabled-v1');
    expect(config.operationsNotificationMode).toBe('none-v1');
  });

  test('cost controls do not create a regional Operations stack', () => {
    const config = costOnlyConfig();
    const stage = new MxMedEnvironmentStage(new App(), 'CostOnlyEnvironment', { config });
    expect(stage.regionalOperationsStack).toBeUndefined();
  });

  test('launch observability requires the launch deployment profile', () => {
    expect(() =>
      getEnvironmentConfig(
        'production',
        'production-standard-v1',
        'service-enabled-v1',
        'directory-core-v1',
        {},
        {
          operationsActivationMode: 'launch-lean-observability-ready-v1',
          operationsNotificationMode: 'topics-only-v1',
        },
      ),
    ).toThrow('MXMED_OPERATIONS_LAUNCH_REQUIRES_LAUNCH_PROFILE');
  });

  test('production observability accepts a standard profile', () => {
    expect(observabilityConfig('production-standard-v1').operationsActivationMode).toBe(
      'production-observability-ready-v1',
    );
  });

  test('rejects an unknown activation mode', () => {
    expect(() => costOnlyConfig('production', { operationsActivationMode: 'invented' })).toThrow(
      'MXMED_OPERATIONS_CONFIG_INVALID:operationsActivationMode',
    );
  });

  test('rejects notification none while Operations is active', () => {
    expect(() => costOnlyConfig('production', { operationsNotificationMode: 'none-v1' })).toThrow(
      'MXMED_OPERATIONS_ACTIVE_REQUIRES_NOTIFICATION_TOPICS',
    );
  });

  test('rejects topics while Operations is disabled', () => {
    expect(() =>
      getEnvironmentConfig(
        'production',
        'launch-lean-v1',
        'disabled-v1',
        undefined,
        {},
        {
          operationsActivationMode: 'disabled-v1',
          operationsNotificationMode: 'topics-only-v1',
        },
      ),
    ).toThrow('MXMED_OPERATIONS_DISABLED_REQUIRES_NOTIFICATION_NONE');
  });

  test('accepts the external subscriber mode behind a deployment parameter gate', () => {
    expect(
      costOnlyConfig('production', {
        operationsNotificationMode: 'external-subscribers-confirmed-v1',
      }).operationsNotificationMode,
    ).toBe('external-subscribers-confirmed-v1');
  });

  test('keeps the real runtime, clinical, log and metric gates blocked', () => {
    expect(MXMED_REAL_OPERATIONS_GATES).toEqual({
      operationsRuntimeGateState: 'blocked-known-runtime-gaps-v1',
      clinicalLogSanitizationState: 'blocked-legacy-agenda-logs-v1',
      operationsLogProtectionProfile: 'source-sanitized-only-v1',
      applicationMetricEmissionIntegrated: false,
    });
  });
});
