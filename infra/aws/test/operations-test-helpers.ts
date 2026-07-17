import type { Stack } from 'aws-cdk-lib';
import { App } from 'aws-cdk-lib';
import type { MxMedEnvironmentConfig } from '../lib/config/environment-config';
import { validateEnvironmentConfig } from '../lib/config/environment-schema';
import { getEnvironmentConfig } from '../lib/config/environments';
import { MxMedEnvironmentStage } from '../lib/stages/mxmed-environment-stage';
import { MxMedGlobalOperationsStage } from '../lib/stages/mxmed-global-operations-stage';
import { Template } from 'aws-cdk-lib/assertions';

export interface RenderedResource {
  readonly Type?: string;
  readonly Properties?: Record<string, unknown>;
  readonly DeletionPolicy?: string;
  readonly UpdateReplacePolicy?: string;
}

export interface RenderedTemplate {
  readonly Parameters?: Record<string, Record<string, unknown>>;
  readonly Rules?: Record<string, Record<string, unknown>>;
  readonly Resources?: Record<string, RenderedResource>;
  readonly Outputs?: Record<string, Record<string, unknown>>;
}

export function costOnlyConfig(
  environment: 'staging' | 'production' = 'production',
  overrides: Record<string, unknown> = {},
): MxMedEnvironmentConfig {
  return getEnvironmentConfig(
    environment,
    'launch-lean-v1',
    'disabled-v1',
    undefined,
    {},
    {
      operationsActivationMode: 'cost-controls-ready-v1',
      operationsNotificationMode: 'topics-only-v1',
      ...overrides,
    },
  );
}

export function observabilityConfig(
  profile: 'launch-lean-v1' | 'production-standard-v1' | 'scale-ready-v1' = 'launch-lean-v1',
  environment: 'staging' | 'production' = 'production',
  overrides: Record<string, unknown> = {},
): MxMedEnvironmentConfig {
  const launch = profile === 'launch-lean-v1';
  return getEnvironmentConfig(
    environment,
    profile,
    'service-enabled-v1',
    'directory-core-v1',
    {
      edgeActivationMode: 'application-origin-ready-v1',
      edgePricingProfile: launch ? 'flat-rate-free-v1' : 'flat-rate-pro-v1',
      edgeDnsMode: 'none-v1',
      edgeCutoverState: 'blocked-known-gaps-v1',
      staticAssetCacheState: 'disabled-until-fingerprinted-v1',
    },
    {
      operationsActivationMode: launch
        ? 'launch-lean-observability-ready-v1'
        : 'production-observability-ready-v1',
      operationsNotificationMode: 'topics-only-v1',
      ...overrides,
    },
  );
}

export function publicTrafficFixture(
  profile: 'launch-lean-v1' | 'production-standard-v1' = 'launch-lean-v1',
): MxMedEnvironmentConfig {
  const origin = observabilityConfig(profile, 'production', {
    operationsRuntimeGateState: 'operational-readiness-integrated-v1',
  });
  const fixture: MxMedEnvironmentConfig = {
    ...origin,
    edgeActivationMode: 'public-traffic-enabled-v1',
    edgeDnsMode: 'route53-managed-v1',
    edgeCutoverState: 'verified-for-cutover-v1',
    staticAssetCacheState: 'immutable-fingerprinted-v1',
    readinessEndpointIntegrated: true,
    stripeReturnRouteImplemented: true,
    stripeWebhookRouteConfirmed: true,
    assetFingerprintingReady: true,
    edgeDomainApproved: true,
    viewerCertificateIssued: true,
    originCertificateIssued: true,
    cloudFrontPricingPlanVerified: true,
    budgetApproved: true,
    dnsCutoverApproved: true,
    cloudFrontPricingPlanVerification: {
      expectedProfile: profile === 'launch-lean-v1' ? 'flat-rate-free-v1' : 'flat-rate-pro-v1',
      accountEligibilityVerified: true,
      planAttached: true,
      verifiedAt: 'fixture-only',
      verificationEvidenceReference: 'fixture-only',
    },
  };
  validateEnvironmentConfig(fixture);
  return fixture;
}

export function renderEnvironment(config: MxMedEnvironmentConfig): {
  readonly stage: MxMedEnvironmentStage;
  readonly operations: RenderedTemplate;
  readonly compute: RenderedTemplate;
  readonly session: RenderedTemplate;
  readonly security: RenderedTemplate;
} {
  const app = new App({ analyticsReporting: false });
  const stage = new MxMedEnvironmentStage(app, `Environment${config.environmentCode}`, { config });
  if (stage.regionalOperationsStack === undefined) throw new Error('operations-fixture-missing');
  return {
    stage,
    operations: renderStack(stage.regionalOperationsStack),
    compute: renderStack(stage.computeStack),
    session: renderStack(stage.sessionStack),
    security: renderStack(stage.securityStack),
  };
}

export function renderGlobal(config: MxMedEnvironmentConfig): {
  readonly stage: MxMedGlobalOperationsStage;
  readonly cost: RenderedTemplate;
  readonly edge: RenderedTemplate | undefined;
  readonly operations: RenderedTemplate | undefined;
} {
  const app = new App({ analyticsReporting: false });
  const stage = new MxMedGlobalOperationsStage(app, `Global${config.environmentCode}`, { config });
  return {
    stage,
    cost: renderStack(stage.costManagementStack),
    edge: stage.globalEdgeStack === undefined ? undefined : renderStack(stage.globalEdgeStack),
    operations:
      stage.globalOperationsStack === undefined
        ? undefined
        : renderStack(stage.globalOperationsStack),
  };
}

export function renderStack(stack: Stack): RenderedTemplate {
  return Template.fromStack(stack).toJSON();
}

export function resourcesOfType(
  template: RenderedTemplate,
  type: string,
): readonly Record<string, unknown>[] {
  return Object.values(template.Resources ?? {})
    .filter((resource) => resource.Type === type)
    .map((resource) => resource.Properties ?? {});
}

export function resourceEntriesOfType(
  template: RenderedTemplate,
  type: string,
): readonly (readonly [string, RenderedResource])[] {
  return Object.entries(template.Resources ?? {}).filter(([, resource]) => resource.Type === type);
}

export function serialized(value: unknown): string {
  return JSON.stringify(value);
}
