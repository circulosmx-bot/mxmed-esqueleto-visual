import { Duration } from 'aws-cdk-lib';
import { Metric } from 'aws-cdk-lib/aws-cloudwatch';

import type { MxMedRuntimeCapabilityProfile } from '../config/environment-config';
import { assertOperationsDimensionName } from './operations-validation';

export const MXMED_APPLICATION_METRIC_NAMESPACE = 'MXMed/Application' as const;
export const MXMED_APPLICATION_METRIC_NAMES = Object.freeze([
  'ReadinessFailureCount',
  'StripeWebhookFailureCount',
  'SubscriptionActivationMismatchCount',
  'NotificationDeliveryFailureCount',
  'ClinicalUploadFailureCount',
  'SecureLinkAbuseCount',
  'AiProviderFailureCount',
  'AiBudgetGateRejectionCount',
] as const);

export type MxMedApplicationMetricName = (typeof MXMED_APPLICATION_METRIC_NAMES)[number];

export interface MxMedApplicationMetricGateContext {
  readonly runtimeCapabilityProfile: MxMedRuntimeCapabilityProfile | null;
  readonly applicationMetricEmissionIntegrated: boolean;
  readonly operationsRuntimeGateState:
    'blocked-known-runtime-gaps-v1' | 'operational-readiness-integrated-v1';
  readonly readinessEndpointIntegrated: boolean;
}

export interface MxMedApplicationMetricDimensions {
  readonly Environment: 'staging' | 'production';
  readonly Component: string;
  readonly Result: string;
  readonly RuntimeCapabilityProfile: MxMedRuntimeCapabilityProfile;
}

export function buildMxMedApplicationMetric(
  metricName: MxMedApplicationMetricName,
  dimensions: MxMedApplicationMetricDimensions,
): Metric {
  for (const key of Object.keys(dimensions)) assertOperationsDimensionName(key);
  const dimensionValues: readonly string[] = [
    dimensions.Environment,
    dimensions.Component,
    dimensions.Result,
    dimensions.RuntimeCapabilityProfile,
  ];
  for (const value of dimensionValues) {
    if (!/^[A-Za-z0-9._-]{1,64}$/.test(value)) {
      throw new Error('MXMED_APPLICATION_METRIC_DIMENSION_VALUE_INVALID');
    }
  }
  const dimensionsMap: Record<string, string> = { ...dimensions };
  return new Metric({
    namespace: MXMED_APPLICATION_METRIC_NAMESPACE,
    metricName,
    dimensionsMap,
    period: Duration.minutes(1),
  });
}

export function selectMxMedApplicationMetricNames(
  context: MxMedApplicationMetricGateContext,
): readonly MxMedApplicationMetricName[] {
  if (!context.applicationMetricEmissionIntegrated) return [];
  switch (context.runtimeCapabilityProfile) {
    case 'directory-core-v1':
      return context.operationsRuntimeGateState === 'operational-readiness-integrated-v1' &&
        context.readinessEndpointIntegrated
        ? ['ReadinessFailureCount']
        : [];
    case 'paid-profile-v1':
      return ['StripeWebhookFailureCount', 'SubscriptionActivationMismatchCount'];
    case 'clinical-v1':
      return [
        'NotificationDeliveryFailureCount',
        'ClinicalUploadFailureCount',
        'SecureLinkAbuseCount',
      ];
    case 'professional-ai-v1':
      return ['AiProviderFailureCount', 'AiBudgetGateRejectionCount'];
    case null:
      return [];
  }
}
