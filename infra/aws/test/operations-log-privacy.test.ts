import { getEnvironmentConfig } from '../lib/config/environments';
import { MXMED_LAUNCH_LEAN_ALARM_CATALOG } from '../lib/constructs/operations-alarm-catalog';
import { buildOperationsAlarmDescription } from '../lib/utils/operations-validation';
import type { RenderedTemplate } from './operations-test-helpers';
import {
  observabilityConfig,
  renderEnvironment,
  resourcesOfType,
  serialized,
} from './operations-test-helpers';

function targetedClinicalConfig() {
  return getEnvironmentConfig(
    'production',
    'production-standard-v1',
    'service-enabled-v1',
    'clinical-v1',
    {},
    {
      operationsActivationMode: 'production-observability-ready-v1',
      operationsNotificationMode: 'topics-only-v1',
      operationsLogProtectionProfile: 'targeted-data-protection-v1',
      clinicalLogSanitizationState: 'source-sanitization-verified-v1',
    },
  );
}

let stagingCompute: RenderedTemplate;
let productionCompute: RenderedTemplate;
let productionSecurity: RenderedTemplate;

beforeAll(() => {
  stagingCompute = renderEnvironment(observabilityConfig('launch-lean-v1', 'staging')).compute;
  const production = renderEnvironment(observabilityConfig());
  productionCompute = production.compute;
  productionSecurity = production.security;
});

describe('Operations log privacy and retention', () => {
  test('retains staging application and migration logs for thirty days', () => {
    const logs = resourcesOfType(stagingCompute, 'AWS::Logs::LogGroup');
    expect(logs.filter((log) => log.RetentionInDays === 30)).toHaveLength(2);
  });

  test('retains production application and migration logs for ninety days', () => {
    const logs = resourcesOfType(productionCompute, 'AWS::Logs::LogGroup');
    expect(logs.filter((log) => log.RetentionInDays === 90)).toHaveLength(2);
  });

  test('preserves the longer production audit retention', () => {
    expect(serialized(productionSecurity)).toContain('365');
  });

  test('keeps source-sanitized mode free of new data protection policies', () => {
    expect(
      resourcesOfType(productionCompute, 'AWS::Logs::LogGroup').every(
        (log) => log.DataProtectionPolicy === undefined,
      ),
    ).toBe(true);
  });

  test('blocks production clinical observability while legacy agenda logs remain unsafe', () => {
    expect(() =>
      getEnvironmentConfig(
        'production',
        'production-standard-v1',
        'service-enabled-v1',
        'clinical-v1',
        {},
        {
          operationsActivationMode: 'production-observability-ready-v1',
          operationsNotificationMode: 'topics-only-v1',
        },
      ),
    ).toThrow('clinical_observability_blocked_by_legacy_agenda_logs');
  });

  test('allows the targeted fixture only after source verification', () => {
    const compute = renderEnvironment(targetedClinicalConfig()).compute;
    const policies = resourcesOfType(compute, 'AWS::Logs::LogGroup').filter(
      (log) => log.DataProtectionPolicy !== undefined,
    );
    expect(policies).toHaveLength(2);
  });

  test('never grants logs:Unmask to workload roles', () => {
    expect(
      serialized([productionCompute, renderEnvironment(targetedClinicalConfig()).compute]),
    ).not.toContain('logs:Unmask');
  });

  test.each([
    'full_query=patient',
    'cookie=session',
    'authorization=bearer',
    'stripe-signature=value',
    'client_secret=value',
    'request_body=value',
  ])('rejects sensitive alarm metadata: %s', (unsafeText) => {
    expect(() =>
      buildOperationsAlarmDescription(
        observabilityConfig(),
        MXMED_LAUNCH_LEAN_ALARM_CATALOG[0],
        unsafeText,
      ),
    ).toThrow('MXMED_OPERATIONS_ALARM_DESCRIPTION_UNSAFE');
  });

  test('persists no sensitive request fields in current Operations templates', () => {
    const logGroups = [stagingCompute, productionCompute].flatMap((template) =>
      resourcesOfType(template, 'AWS::Logs::LogGroup'),
    );
    expect(serialized(logGroups)).not.toMatch(
      /request.?body|cookie|authorization|stripe-signature|client_secret|full.?query/i,
    );
  });
});
