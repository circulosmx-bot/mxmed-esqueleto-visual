import { App, CfnParameter, CfnResource, Stack } from 'aws-cdk-lib';
import { CfnAnomalySubscription } from 'aws-cdk-lib/aws-ce';
import { CfnAlarm } from 'aws-cdk-lib/aws-cloudwatch';

import { OperationsFoundationAspect } from '../lib/aspects/operations-foundation-aspect';
import type { MxMedEnvironmentConfig } from '../lib/config/environment-config';
import {
  MXMED_APPLICATION_METRIC_NAMES,
  MXMED_APPLICATION_METRIC_NAMESPACE,
  buildMxMedApplicationMetric,
  selectMxMedApplicationMetricNames,
} from '../lib/utils/operations-metric-builders';
import {
  assertNoPersonalNotificationTarget,
  assertOperationsDimensionName,
} from '../lib/utils/operations-validation';
import { observabilityConfig } from './operations-test-helpers';

function aspectStack(region = 'mx-central-1'): {
  readonly stack: Stack;
  readonly aspect: OperationsFoundationAspect;
} {
  const app = new App();
  const stack = new Stack(app, 'RegionalOperations', { env: { region } });
  return { stack, aspect: new OperationsFoundationAspect(observabilityConfig()) };
}

function validAlarm(stack: Stack, overrides: Partial<CfnAlarmProps> = {}): CfnAlarm {
  return new CfnAlarm(stack, 'Alarm', {
    comparisonOperator: 'GreaterThanOrEqualToThreshold',
    evaluationPeriods: 1,
    threshold: 1,
    metricName: 'CPUUtilization',
    namespace: 'AWS/ECS',
    period: 60,
    alarmDescription: 'severity=SEV3;runbook=ecs-task-deficit;code=ecs_high_cpu',
    alarmActions: ['arn:aws:sns:mx-central-1:${AWS::AccountId}:synthetic-topic'],
    ...overrides,
  });
}

type CfnAlarmProps = ConstructorParameters<typeof CfnAlarm>[2];

describe('Operations guardrails and application metric contract', () => {
  test.each(['ops@example.test', '+0000000', 'https://hooks.example.test/ops'])(
    'rejects personal notification destination %s',
    (target) => {
      expect(() => {
        assertNoPersonalNotificationTarget(target);
      }).toThrow('MXMED_OPERATIONS_PERSONAL_NOTIFICATION_TARGET_FORBIDDEN');
    },
  );

  test.each(['Environment', 'Component', 'Result', 'RuntimeCapabilityProfile'])(
    'allows aggregate application metric dimension %s',
    (dimension) => {
      expect(() => {
        assertOperationsDimensionName(dimension);
      }).not.toThrow();
    },
  );

  test.each([
    'user_id',
    'doctor_id',
    'profile_id',
    'patient_id',
    'email',
    'payment_intent',
    'session_id',
    'route',
    'filename',
    'token',
    'URL',
  ])('rejects personal application metric dimension %s', (dimension) => {
    expect(() => {
      assertOperationsDimensionName(dimension);
    }).toThrow(`MXMED_APPLICATION_METRIC_DIMENSION_FORBIDDEN:${dimension}`);
  });

  test.each(MXMED_APPLICATION_METRIC_NAMES)('builds contractual metric %s', (metricName) => {
    const metric = buildMxMedApplicationMetric(metricName, {
      Environment: 'production',
      Component: 'directory',
      Result: 'failure',
      RuntimeCapabilityProfile: 'directory-core-v1',
    });
    expect(metric.toMetricConfig().metricStat?.namespace).toBe(MXMED_APPLICATION_METRIC_NAMESPACE);
    expect(metric.toMetricConfig().metricStat?.metricName).toBe(metricName);
  });

  test('rejects a monetary parameter default', () => {
    const { stack, aspect } = aspectStack();
    const parameter = new CfnParameter(stack, 'ApprovedMonthlyBudgetUsd', {
      type: 'Number',
      default: 100,
    });
    expect(() => {
      aspect.visit(parameter);
    }).toThrow('MXMED_OPERATIONS_GUARDRAIL:MONETARY_DEFAULT');
  });

  test('rejects DAILY anomaly notification', () => {
    const { stack, aspect } = aspectStack();
    const subscription = new CfnAnomalySubscription(stack, 'Anomaly', {
      frequency: 'DAILY',
      monitorArnList: ['monitor'],
      subscribers: [{ type: 'SNS', address: 'topic' }],
      subscriptionName: 'synthetic',
      thresholdExpression: '{}',
    });
    expect(() => {
      aspect.visit(subscription);
    }).toThrow('MXMED_OPERATIONS_GUARDRAIL:ANOMALY_FREQUENCY');
  });

  test('rejects the deprecated anomaly threshold', () => {
    const { stack, aspect } = aspectStack();
    const subscription = new CfnAnomalySubscription(stack, 'Anomaly', {
      frequency: 'IMMEDIATE',
      monitorArnList: ['monitor'],
      subscribers: [{ type: 'SNS', address: 'topic' }],
      subscriptionName: 'synthetic',
      threshold: 10,
      thresholdExpression: '{}',
    });
    expect(() => {
      aspect.visit(subscription);
    }).toThrow('MXMED_OPERATIONS_GUARDRAIL:ANOMALY_DEPRECATED_THRESHOLD');
  });

  test('rejects a high-resolution alarm', () => {
    const { stack, aspect } = aspectStack();
    expect(() => {
      aspect.visit(validAlarm(stack, { period: 30 }));
    }).toThrow('MXMED_OPERATIONS_GUARDRAIL:HIGH_RESOLUTION_ALARM');
  });

  test('rejects an alarm without runbook metadata', () => {
    const { stack, aspect } = aspectStack();
    expect(() => {
      aspect.visit(validAlarm(stack, { alarmDescription: 'severity=SEV3' }));
    }).toThrow('MXMED_OPERATIONS_GUARDRAIL:ALARM_METADATA');
  });

  test('rejects a personal alarm dimension', () => {
    const { stack, aspect } = aspectStack();
    expect(() => {
      aspect.visit(validAlarm(stack, { dimensions: [{ name: 'patient_id', value: 'opaque' }] }));
    }).toThrow('MXMED_OPERATIONS_GUARDRAIL:PERSONAL_DIMENSION');
  });

  test('rejects CloudFront alarms outside us-east-1', () => {
    const { stack, aspect } = aspectStack('mx-central-1');
    expect(() => {
      aspect.visit(
        validAlarm(stack, {
          namespace: 'AWS/CloudFront',
          metricName: '5xxErrorRate',
        }),
      );
    }).toThrow('MXMED_OPERATIONS_GUARDRAIL:CLOUDFRONT_WRONG_REGION');
  });

  test.each(['AWS::XRay::Group', 'AWS::Synthetics::Canary', 'AWS::RUM::AppMonitor'])(
    'rejects uncontracted costly resource %s',
    (type) => {
      const { stack, aspect } = aspectStack();
      const resource = new CfnResource(stack, 'SyntheticResource', { type, properties: {} });
      expect(() => {
        aspect.visit(resource);
      }).toThrow('MXMED_OPERATIONS_GUARDRAIL:COSTLY_OR_AUTOMATION_RESOURCE');
    },
  );

  test('rejects automatic remediation', () => {
    const unsafe = {
      ...observabilityConfig(),
      operationsAutomaticRemediationEnabled: true,
    } as unknown as MxMedEnvironmentConfig;
    const { stack } = aspectStack();
    expect(() => {
      new OperationsFoundationAspect(unsafe).visit(stack);
    }).toThrow('MXMED_OPERATIONS_GUARDRAIL:AUTOMATIC_REMEDIATION');
  });

  test('selects future metrics by capability only after every applicable gate', () => {
    const real = observabilityConfig();
    expect(selectMxMedApplicationMetricNames(real)).toEqual([]);
    expect(
      selectMxMedApplicationMetricNames({
        runtimeCapabilityProfile: 'directory-core-v1',
        applicationMetricEmissionIntegrated: true,
        operationsRuntimeGateState: 'blocked-known-runtime-gaps-v1',
        readinessEndpointIntegrated: false,
      }),
    ).toEqual([]);
    expect(
      selectMxMedApplicationMetricNames({
        runtimeCapabilityProfile: 'directory-core-v1',
        applicationMetricEmissionIntegrated: true,
        operationsRuntimeGateState: 'operational-readiness-integrated-v1',
        readinessEndpointIntegrated: true,
      }),
    ).toEqual(['ReadinessFailureCount']);
    expect(
      selectMxMedApplicationMetricNames({
        runtimeCapabilityProfile: 'paid-profile-v1',
        applicationMetricEmissionIntegrated: true,
        operationsRuntimeGateState: 'operational-readiness-integrated-v1',
        readinessEndpointIntegrated: true,
      }),
    ).toEqual(['StripeWebhookFailureCount', 'SubscriptionActivationMismatchCount']);
    expect(
      selectMxMedApplicationMetricNames({
        runtimeCapabilityProfile: 'clinical-v1',
        applicationMetricEmissionIntegrated: true,
        operationsRuntimeGateState: 'operational-readiness-integrated-v1',
        readinessEndpointIntegrated: true,
      }),
    ).toEqual([
      'NotificationDeliveryFailureCount',
      'ClinicalUploadFailureCount',
      'SecureLinkAbuseCount',
    ]);
    expect(
      selectMxMedApplicationMetricNames({
        runtimeCapabilityProfile: 'professional-ai-v1',
        applicationMetricEmissionIntegrated: true,
        operationsRuntimeGateState: 'operational-readiness-integrated-v1',
        readinessEndpointIntegrated: true,
      }),
    ).toEqual(['AiProviderFailureCount', 'AiBudgetGateRejectionCount']);
  });
});
