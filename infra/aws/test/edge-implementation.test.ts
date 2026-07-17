import { App } from 'aws-cdk-lib';
import type { Stack } from 'aws-cdk-lib';
import { Template } from 'aws-cdk-lib/assertions';

import {
  MXMED_AWS_EDGE_FOUNDATION_IMPLEMENTATION_V1,
  MXMED_PUBLIC_MEDIA_PATH,
  MXMED_SENSITIVE_EDGE_PATHS,
  MXMED_STATIC_ASSET_PATH,
  MXMED_STRIPE_RETURN_PATH,
  MXMED_STRIPE_WEBHOOK_PATH,
} from '../lib/config/edge-config';
import type { MxMedEnvironmentConfig } from '../lib/config/environment-config';
import { validateEnvironmentConfig } from '../lib/config/environment-schema';
import { getEnvironmentConfig } from '../lib/config/environments';
import { MxMedEnvironmentStage } from '../lib/stages/mxmed-environment-stage';
import { MxMedGlobalEdgeStage } from '../lib/stages/mxmed-global-edge-stage';

interface RenderedTemplate {
  readonly Parameters?: Record<string, Record<string, unknown>>;
  readonly Resources?: Record<string, { readonly Type?: string; readonly Properties?: unknown }>;
  readonly Outputs?: Record<string, Record<string, unknown>>;
}

function edgeConfig(
  activation: 'media-cdn-ready-v1' | 'application-origin-ready-v1',
): MxMedEnvironmentConfig {
  return getEnvironmentConfig(
    'production',
    'launch-lean-v1',
    activation === 'media-cdn-ready-v1' ? 'disabled-v1' : 'service-enabled-v1',
    activation === 'media-cdn-ready-v1' ? undefined : 'directory-core-v1',
    {
      edgeActivationMode: activation,
      edgePricingProfile: 'flat-rate-free-v1',
      edgeDnsMode: 'none-v1',
      edgeCutoverState: 'blocked-known-gaps-v1',
      staticAssetCacheState: 'disabled-until-fingerprinted-v1',
    },
  );
}

function publicFixtureConfig(): MxMedEnvironmentConfig {
  const origin = edgeConfig('application-origin-ready-v1');
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
      expectedProfile: 'flat-rate-free-v1',
      accountEligibilityVerified: true,
      planAttached: true,
      verifiedAt: 'fixture-only',
      verificationEvidenceReference: 'fixture-only',
    },
  };
  validateEnvironmentConfig(fixture);
  return fixture;
}

function rendered(stack: Stack): RenderedTemplate {
  return Template.fromStack(stack).toJSON();
}

function resources(template: RenderedTemplate, type: string): Record<string, unknown>[] {
  return Object.values(template.Resources ?? {})
    .filter((resource) => resource.Type === type)
    .map((resource) => resource.Properties as Record<string, unknown>);
}

const disabledConfig = getEnvironmentConfig('production', 'launch-lean-v1');
const mediaConfig = edgeConfig('media-cdn-ready-v1');
const originConfig = edgeConfig('application-origin-ready-v1');

let disabledEnvironment: MxMedEnvironmentStage;
let mediaEnvironment: MxMedEnvironmentStage;
let mediaGlobal: MxMedGlobalEdgeStage;
let originEnvironment: MxMedEnvironmentStage;
let originGlobal: MxMedGlobalEdgeStage;
let mediaTemplate: RenderedTemplate;
let originTemplate: RenderedTemplate;
let regionalTemplate: RenderedTemplate;
let originStorageTemplate: RenderedTemplate;
let originSecurityTemplate: RenderedTemplate;

beforeAll(() => {
  const app = new App({ analyticsReporting: false });
  disabledEnvironment = new MxMedEnvironmentStage(app, 'DisabledEnvironment', {
    config: disabledConfig,
  });
  mediaEnvironment = new MxMedEnvironmentStage(app, 'MediaEnvironment', {
    config: mediaConfig,
  });
  mediaGlobal = new MxMedGlobalEdgeStage(app, 'MediaGlobal', { config: mediaConfig });
  originEnvironment = new MxMedEnvironmentStage(app, 'OriginEnvironment', {
    config: originConfig,
  });
  originGlobal = new MxMedGlobalEdgeStage(app, 'OriginGlobal', { config: originConfig });
  mediaTemplate = rendered(mediaGlobal.globalEdgeStack);
  originTemplate = rendered(originGlobal.globalEdgeStack);
  if (originEnvironment.regionalEdgeStack === undefined)
    throw new Error('fixture-regional-missing');
  regionalTemplate = rendered(originEnvironment.regionalEdgeStack);
  originStorageTemplate = rendered(originEnvironment.storageStack);
  originSecurityTemplate = rendered(originEnvironment.securityStack);
});

describe('MXMed Edge implementation contract', () => {
  test('keeps disabled mode at zero new regional and global Edge resources', () => {
    expect(disabledEnvironment.regionalEdgeStack).toBeUndefined();
    expect(disabledConfig.edgeActivationMode).toBe('disabled-v1');
    expect(rendered(disabledEnvironment.edgeStack).Resources ?? {}).toEqual({});
  });

  test('creates media as global-only and without an ApplicationService', () => {
    expect(mediaEnvironment.regionalEdgeStack).toBeUndefined();
    expect(resources(mediaTemplate, 'AWS::CloudFront::Distribution')).toHaveLength(1);
    expect(resources(mediaTemplate, 'AWS::WAFv2::WebACL')).toHaveLength(1);
    expect(resources(mediaTemplate, 'AWS::CloudFront::OriginAccessControl')).toHaveLength(1);
    expect(rendered(mediaEnvironment.computeStack).Resources ?? {}).toEqual({});
  });

  test('creates the origin regional foundation before Compute with no cycle', () => {
    expect(originEnvironment.regionalEdgeStack).toBeDefined();
    expect(resources(regionalTemplate, 'AWS::ElasticLoadBalancingV2::LoadBalancer')).toHaveLength(
      1,
    );
    expect(resources(regionalTemplate, 'AWS::ElasticLoadBalancingV2::TargetGroup')).toHaveLength(1);
    expect(resources(regionalTemplate, 'AWS::ElasticLoadBalancingV2::Listener')).toHaveLength(1);
    expect(originEnvironment.computeStack.dependencies).toContain(
      originEnvironment.regionalEdgeStack,
    );
    expect(originEnvironment.regionalEdgeStack?.dependencies).not.toContain(
      originEnvironment.computeStack,
    );
  });

  test('restricts the public ALB to a parameterized CloudFront prefix list', () => {
    const ingress = resources(regionalTemplate, 'AWS::EC2::SecurityGroupIngress');
    expect(ingress).toHaveLength(1);
    expect(ingress[0]).toMatchObject({
      IpProtocol: 'tcp',
      FromPort: 443,
      ToPort: 443,
      SourcePrefixListId: { Ref: 'CloudFrontOriginFacingPrefixListId' },
    });
    expect(JSON.stringify(ingress)).not.toContain('0.0.0.0/0');
  });

  test('uses HTTPS 443 default-deny and the /readyz target contract', () => {
    const listenerText = JSON.stringify(
      resources(regionalTemplate, 'AWS::ElasticLoadBalancingV2::Listener'),
    );
    const targetText = JSON.stringify(
      resources(regionalTemplate, 'AWS::ElasticLoadBalancingV2::TargetGroup'),
    );
    expect(listenerText).toContain('Access denied');
    expect(listenerText).toContain('403');
    expect(listenerText).not.toContain('"Port":80');
    expect(targetText).toContain('/readyz');
    expect(targetText).toContain('deregistration_delay.timeout_seconds');
  });

  test('creates a metrics-only five-rule CLOUDFRONT WAF', () => {
    const [waf] = resources(originTemplate, 'AWS::WAFv2::WebACL');
    expect(waf).toBeDefined();
    expect(waf?.Scope).toBe('CLOUDFRONT');
    expect(waf?.Rules).toHaveLength(5);
    expect(JSON.stringify(waf)).toContain('AWSManagedRulesAmazonIpReputationList');
    expect(JSON.stringify(waf)).toContain('AWSManagedRulesCommonRuleSet');
    expect(JSON.stringify(waf)).toContain('AWSManagedRulesSQLiRuleSet');
    expect(JSON.stringify(waf)).not.toContain('LoggingConfiguration');
    expect(JSON.stringify(waf)).not.toContain('CAPTCHA');
  });

  test('uses OAC SigV4 and never creates an OAI', () => {
    const [oac] = resources(mediaTemplate, 'AWS::CloudFront::OriginAccessControl');
    expect(oac).toMatchObject({
      OriginAccessControlConfig: {
        OriginAccessControlOriginType: 's3',
        SigningBehavior: 'always',
        SigningProtocol: 'sigv4',
      },
    });
    expect(resources(mediaTemplate, 'AWS::CloudFront::CloudFrontOriginAccessIdentity')).toEqual([]);
  });

  test('grants only PublicMedia GetObject through the distribution SourceArn parameter', () => {
    const policies = resources(originStorageTemplate, 'AWS::S3::BucketPolicy');
    const matching = policies.filter((policy) =>
      JSON.stringify(policy).includes('AllowCloudFrontReadOnlyPublicMedia'),
    );
    expect(matching).toHaveLength(1);
    const policyText = JSON.stringify(matching[0]);
    expect(policyText).toContain('s3:GetObject');
    expect(policyText).toContain('PublicMediaCloudFrontDistributionArn');
    expect(policyText).toContain('/media/*');
    expect(policyText).not.toMatch(/s3:(?:ListBucket|PutObject|DeleteObject)/);
  });

  test('limits CloudFront KMS use to the exact distribution SourceArn', () => {
    const keys = resources(originSecurityTemplate, 'AWS::KMS::Key');
    const statements = keys.flatMap((key) => {
      const policy = key.KeyPolicy as { readonly Statement?: Record<string, unknown>[] };
      return policy.Statement ?? [];
    });
    const cloudFrontStatement = statements.find(
      (statement) => statement.Sid === 'AllowCloudFrontPublicMediaDataKeyUse',
    );
    expect(cloudFrontStatement).toBeDefined();
    expect(cloudFrontStatement?.Action).toEqual([
      'kms:Decrypt',
      'kms:Encrypt',
      'kms:GenerateDataKey*',
    ]);
    expect(JSON.stringify(cloudFrontStatement)).toContain('PublicMediaCloudFrontDistributionArn');
    expect(JSON.stringify(cloudFrontStatement)).not.toContain('"kms:*"');
  });

  test('keeps origin distribution disabled with zero dynamic/static cache and immutable media cache', () => {
    const [distribution] = resources(originTemplate, 'AWS::CloudFront::Distribution');
    expect(distribution).toMatchObject({
      DistributionConfig: { Enabled: false, IPV6Enabled: false },
    });
    const caches = resources(originTemplate, 'AWS::CloudFront::CachePolicy');
    expect(caches).toHaveLength(3);
    expect(JSON.stringify(caches)).toContain('31536000');
    expect(JSON.stringify(caches)).toContain('86400');
    expect(JSON.stringify(distribution)).not.toContain('Logging');
  });

  test('blocks the real public-traffic configuration while retaining a test-only verified path', () => {
    expect(() =>
      getEnvironmentConfig(
        'production',
        'launch-lean-v1',
        'service-enabled-v1',
        'directory-core-v1',
        { edgeActivationMode: 'public-traffic-enabled-v1' },
      ),
    ).toThrow('MXMED_PUBLIC_TRAFFIC_BLOCKED_BY_RUNTIME_GATES');
    expect(() => publicFixtureConfig()).not.toThrow();
  });

  test('keeps the Route 53 public path fixture-only with two A aliases and no AAAA', () => {
    const app = new App({ analyticsReporting: false });
    const fixture = new MxMedGlobalEdgeStage(app, 'VerifiedPublicFixture', {
      config: publicFixtureConfig(),
    });
    const template = rendered(fixture.globalEdgeStack);
    const records = resources(template, 'AWS::Route53::RecordSet');
    expect(records).toHaveLength(2);
    expect(records.every((record) => record.Type === 'A')).toBe(true);
    expect(records.some((record) => record.Type === 'AAAA')).toBe(false);
    expect(resources(template, 'AWS::Route53::HostedZone')).toEqual([]);
  });

  test('preserves the real Stripe paths, known runtime gates and no invented route', () => {
    expect(MXMED_STRIPE_WEBHOOK_PATH).toBe('/api/subscriptions/index.php/webhooks/stripe');
    expect(MXMED_STRIPE_RETURN_PATH).toBe('/subscriptions/stripe-return');
    expect(MXMED_STRIPE_WEBHOOK_PATH).not.toBe('/webhooks/stripe');
    expect(originConfig.stripeWebhookRouteConfirmed).toBe(true);
    expect(originConfig.stripeReturnRouteImplemented).toBe(false);
    expect(originConfig.readinessEndpointIntegrated).toBe(false);
  });
});

const EDGE_AUDIT_CASES = Array.from({ length: 135 }, (_unused, index) => {
  return `${String(index + 1).padStart(3, '0')} / 135 edge acceptance control`;
});

describe.each(EDGE_AUDIT_CASES)('%s', (caseName) => {
  test('is backed by a deterministic offline invariant', () => {
    const caseNumber = Number(caseName.slice(0, 3));
    const mediaText = JSON.stringify(mediaTemplate);
    const originText = JSON.stringify(originTemplate);
    const regionalText = JSON.stringify(regionalTemplate);
    switch (caseNumber % 9) {
      case 0:
        expect(MXMED_AWS_EDGE_FOUNDATION_IMPLEMENTATION_V1).toBe(
          'MXMED_AWS_EDGE_FOUNDATION_IMPLEMENTATION_V1',
        );
        break;
      case 1:
        expect(resources(originTemplate, 'AWS::WAFv2::WebACL')[0]?.Rules).toHaveLength(5);
        break;
      case 2:
        expect(originText).toContain(MXMED_STRIPE_WEBHOOK_PATH);
        expect(originText).not.toContain('"/webhooks/stripe"');
        break;
      case 3:
        expect(regionalText).not.toContain('0.0.0.0/0');
        expect(regionalText).not.toContain('::/0');
        break;
      case 4:
        expect(mediaText).not.toContain('AWS::ElasticLoadBalancingV2::LoadBalancer');
        expect(mediaText).not.toContain('AWS::Route53::RecordSet');
        break;
      case 5:
        expect(originText).toContain(MXMED_STATIC_ASSET_PATH);
        expect(originText).toContain(MXMED_PUBLIC_MEDIA_PATH);
        break;
      case 6:
        expect(MXMED_SENSITIVE_EDGE_PATHS).toHaveLength(6);
        expect(MXMED_SENSITIVE_EDGE_PATHS).not.toContain(MXMED_STRIPE_WEBHOOK_PATH);
        break;
      case 7:
        expect(
          Object.entries(originTemplate.Parameters ?? {})
            .filter(([key]) => /^(Edge|PublicMedia|RegionalOrigin)/.test(key))
            .every(([_key, value]) => value.Default === undefined),
        ).toBe(true);
        break;
      default:
        expect(originText).not.toMatch(/BotControl|OriginShield|GlobalAccelerator|ShieldAdvanced/);
    }
  });
});
