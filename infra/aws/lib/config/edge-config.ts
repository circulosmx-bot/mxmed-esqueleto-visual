import type { MxMedEnvironmentConfig } from './environment-config';

export const MXMED_AWS_EDGE_FOUNDATION_IMPLEMENTATION_V1 =
  'MXMED_AWS_EDGE_FOUNDATION_IMPLEMENTATION_V1' as const;

export const MXMED_STRIPE_WEBHOOK_PATH = '/api/subscriptions/index.php/webhooks/stripe' as const;
export const MXMED_STRIPE_RETURN_PATH = '/subscriptions/stripe-return' as const;
export const MXMED_STATIC_ASSET_PATH = '/assets/*' as const;
export const MXMED_PUBLIC_MEDIA_PATH = '/media/*' as const;
export const MXMED_PUBLIC_MEDIA_OBJECT_PREFIX = 'media/*' as const;

export const MXMED_SENSITIVE_EDGE_PATHS = Object.freeze([
  '/api/verify-password.php',
  '/api/verify-sms.php',
  '/api/agenda/index.php/public/otp/request',
  '/api/agenda/index.php/public/otp/verify',
  '/api/clinical/index.php/note-capture-tokens/*',
  '*/payment-intents/*/client-secret',
] as const);

export const MXMED_EDGE_ACTIVATION_MODES = Object.freeze([
  'disabled-v1',
  'media-cdn-ready-v1',
  'application-origin-ready-v1',
  'public-traffic-enabled-v1',
] as const);
export const MXMED_EDGE_PRICING_PROFILES = Object.freeze([
  'flat-rate-free-v1',
  'flat-rate-pro-v1',
  'pay-as-you-go-approved-v1',
] as const);

export interface MxMedEdgeContextValues {
  readonly edgeActivationMode?: unknown;
  readonly edgePricingProfile?: unknown;
  readonly edgeOriginMode?: unknown;
  readonly edgeLoggingProfile?: unknown;
  readonly edgeCacheProfile?: unknown;
  readonly edgeWafProfile?: unknown;
  readonly edgeMapsMode?: unknown;
  readonly edgeDnsMode?: unknown;
  readonly edgeCutoverState?: unknown;
  readonly staticAssetCacheState?: unknown;
}

export type { MxMedCloudFrontPricingPlanVerification } from './environment-config';

export const MXMED_REAL_EDGE_RUNTIME_GATES = Object.freeze({
  readinessEndpointIntegrated: false,
  stripeReturnRouteImplemented: false,
  stripeWebhookRouteConfirmed: true,
  assetFingerprintingReady: false,
  edgeDomainApproved: false,
  viewerCertificateIssued: false,
  originCertificateIssued: false,
  cloudFrontPricingPlanVerified: false,
  budgetApproved: false,
  dnsCutoverApproved: false,
} as const);

const EDGE_DEFAULTS = Object.freeze({
  edgeActivationMode: 'disabled-v1',
  edgePricingProfile: 'flat-rate-free-v1',
  edgeOriginMode: 'cloudfront-restricted-public-alb-v1',
  edgeLoggingProfile: 'metrics-only-no-request-logs-v1',
  edgeCacheProfile: 'dynamic-zero-media-immutable-v1',
  edgeWafProfile: 'free-five-rule-v1',
  edgeMapsMode: 'external-link-only-v1',
  edgeDnsMode: 'none-v1',
  edgeCutoverState: 'blocked-known-gaps-v1',
  staticAssetCacheState: 'disabled-until-fingerprinted-v1',
} as const);

function select<const T extends readonly string[]>(
  field: string,
  value: unknown,
  allowed: T,
  fallback: T[number],
): T[number] {
  const selected = value === undefined ? fallback : value;
  if (typeof selected !== 'string' || !allowed.includes(selected)) {
    throw new Error(`MXMED_EDGE_CONFIG_INVALID:${field}`);
  }
  return selected;
}

type MxMedResolvedEdgeContext = Readonly<
  Pick<
    MxMedEnvironmentConfig,
    | 'edgeActivationMode'
    | 'edgePricingProfile'
    | 'edgeOriginMode'
    | 'edgeLoggingProfile'
    | 'edgeCacheProfile'
    | 'edgeWafProfile'
    | 'edgeMapsMode'
    | 'edgeDnsMode'
    | 'edgeCutoverState'
    | 'staticAssetCacheState'
  >
>;

export function resolveEdgeContext(values: MxMedEdgeContextValues = {}): MxMedResolvedEdgeContext {
  return Object.freeze({
    edgeActivationMode: select(
      'edgeActivationMode',
      values.edgeActivationMode,
      MXMED_EDGE_ACTIVATION_MODES,
      EDGE_DEFAULTS.edgeActivationMode,
    ),
    edgePricingProfile: select(
      'edgePricingProfile',
      values.edgePricingProfile,
      MXMED_EDGE_PRICING_PROFILES,
      EDGE_DEFAULTS.edgePricingProfile,
    ),
    edgeOriginMode: select(
      'edgeOriginMode',
      values.edgeOriginMode,
      ['cloudfront-restricted-public-alb-v1'] as const,
      EDGE_DEFAULTS.edgeOriginMode,
    ),
    edgeLoggingProfile: select(
      'edgeLoggingProfile',
      values.edgeLoggingProfile,
      ['metrics-only-no-request-logs-v1'] as const,
      EDGE_DEFAULTS.edgeLoggingProfile,
    ),
    edgeCacheProfile: select(
      'edgeCacheProfile',
      values.edgeCacheProfile,
      ['dynamic-zero-media-immutable-v1'] as const,
      EDGE_DEFAULTS.edgeCacheProfile,
    ),
    edgeWafProfile: select(
      'edgeWafProfile',
      values.edgeWafProfile,
      ['free-five-rule-v1'] as const,
      EDGE_DEFAULTS.edgeWafProfile,
    ),
    edgeMapsMode: select(
      'edgeMapsMode',
      values.edgeMapsMode,
      ['external-link-only-v1'] as const,
      EDGE_DEFAULTS.edgeMapsMode,
    ),
    edgeDnsMode: select(
      'edgeDnsMode',
      values.edgeDnsMode,
      ['none-v1', 'external-dns-v1', 'route53-managed-v1'] as const,
      EDGE_DEFAULTS.edgeDnsMode,
    ),
    edgeCutoverState: select(
      'edgeCutoverState',
      values.edgeCutoverState,
      ['blocked-known-gaps-v1', 'verified-for-cutover-v1'] as const,
      EDGE_DEFAULTS.edgeCutoverState,
    ),
    staticAssetCacheState: select(
      'staticAssetCacheState',
      values.staticAssetCacheState,
      ['disabled-until-fingerprinted-v1', 'immutable-fingerprinted-v1'] as const,
      EDGE_DEFAULTS.staticAssetCacheState,
    ),
  });
}

export function edgeCreatesGlobal(config: MxMedEnvironmentConfig): boolean {
  return config.edgeActivationMode !== 'disabled-v1';
}

export function edgeCreatesRegional(config: MxMedEnvironmentConfig): boolean {
  return (
    config.edgeActivationMode === 'application-origin-ready-v1' ||
    config.edgeActivationMode === 'public-traffic-enabled-v1'
  );
}

export function edgeUsesPublicMedia(config: MxMedEnvironmentConfig): boolean {
  return edgeCreatesGlobal(config);
}

export function validateEdgeFoundationConfig(config: MxMedEnvironmentConfig): void {
  resolveEdgeContext(config);
  if (
    config.edgeActivationMode === 'media-cdn-ready-v1' &&
    config.computeActivationMode !== 'disabled-v1'
  ) {
    throw new Error('MXMED_EDGE_MEDIA_COMPUTE_MUST_BE_DISABLED');
  }
  if (
    edgeCreatesRegional(config) &&
    (config.computeActivationMode !== 'service-enabled-v1' ||
      config.runtimeCapabilityProfile === null)
  ) {
    throw new Error('MXMED_EDGE_ORIGIN_REQUIRES_COMPUTE_SERVICE');
  }
  if (
    config.staticAssetCacheState === 'immutable-fingerprinted-v1' &&
    !config.assetFingerprintingReady
  ) {
    throw new Error('MXMED_EDGE_FINGERPRINT_GATE_OPEN');
  }
  if (config.edgeActivationMode !== 'public-traffic-enabled-v1') return;

  const publicTrafficReady =
    config.edgeCutoverState === 'verified-for-cutover-v1' &&
    config.staticAssetCacheState === 'immutable-fingerprinted-v1' &&
    config.edgeDnsMode !== 'none-v1' &&
    config.readinessEndpointIntegrated &&
    config.stripeReturnRouteImplemented &&
    config.stripeWebhookRouteConfirmed &&
    config.assetFingerprintingReady &&
    config.edgeDomainApproved &&
    config.viewerCertificateIssued &&
    config.originCertificateIssued &&
    config.cloudFrontPricingPlanVerified &&
    config.budgetApproved &&
    config.dnsCutoverApproved &&
    config.cloudFrontPricingPlanVerification.accountEligibilityVerified &&
    config.cloudFrontPricingPlanVerification.planAttached &&
    config.cloudFrontPricingPlanVerification.verifiedAt !== null &&
    config.cloudFrontPricingPlanVerification.verificationEvidenceReference !== null;
  if (!publicTrafficReady) throw new Error('MXMED_PUBLIC_TRAFFIC_BLOCKED_BY_RUNTIME_GATES');
}
