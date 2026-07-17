import { CfnOutput, CfnParameter, Fn, Tags } from 'aws-cdk-lib';
import {
  CfnCachePolicy,
  CfnDistribution,
  CfnOriginAccessControl,
  CfnOriginRequestPolicy,
  CfnResponseHeadersPolicy,
} from 'aws-cdk-lib/aws-cloudfront';
import { CfnRecordSet } from 'aws-cdk-lib/aws-route53';
import { CfnWebACL } from 'aws-cdk-lib/aws-wafv2';
import type { Construct } from 'constructs';

import {
  MXMED_PUBLIC_MEDIA_PATH,
  MXMED_SENSITIVE_EDGE_PATHS,
  MXMED_STATIC_ASSET_PATH,
  MXMED_STRIPE_WEBHOOK_PATH,
} from '../config/edge-config';
import { BaseMxMedStack } from './base-mxmed-stack';
import type { MxMedContractStackProps } from './base-mxmed-stack';

const CLOUDFRONT_HOSTED_ZONE_ID = 'Z2FDTNDATAQYW2';

function visibility(metricName: string): CfnWebACL.VisibilityConfigProperty {
  return {
    cloudWatchMetricsEnabled: true,
    metricName,
    sampledRequestsEnabled: false,
  };
}

function pathMatch(
  searchString: string,
  positionalConstraint: 'EXACTLY' | 'STARTS_WITH' | 'ENDS_WITH' | 'CONTAINS' = 'EXACTLY',
): CfnWebACL.StatementProperty {
  return {
    byteMatchStatement: {
      fieldToMatch: { uriPath: {} },
      positionalConstraint,
      searchString,
      textTransformations: [{ priority: 0, type: 'NONE' }],
    },
  };
}

function sensitiveRouteStatement(): CfnWebACL.StatementProperty {
  const exact = MXMED_SENSITIVE_EDGE_PATHS.slice(0, 4).map((path) => pathMatch(path));
  const secureNoteTokens = pathMatch('/api/clinical/index.php/note-capture-tokens/', 'STARTS_WITH');
  const paymentClientSecret: CfnWebACL.StatementProperty = {
    andStatement: {
      statements: [
        pathMatch('/payment-intents/', 'CONTAINS'),
        pathMatch('/client-secret', 'ENDS_WITH'),
      ],
    },
  };
  return {
    andStatement: {
      statements: [
        { orStatement: { statements: [...exact, secureNoteTokens, paymentClientSecret] } },
        { notStatement: { statement: pathMatch(MXMED_STRIPE_WEBHOOK_PATH) } },
      ],
    },
  };
}

function generalDynamicStatement(): CfnWebACL.StatementProperty {
  return {
    notStatement: {
      statement: {
        orStatement: {
          statements: [
            pathMatch('/assets/', 'STARTS_WITH'),
            pathMatch('/media/', 'STARTS_WITH'),
            pathMatch(MXMED_STRIPE_WEBHOOK_PATH),
          ],
        },
      },
    },
  };
}

/** us-east-1 CloudFront, OAC and CLOUDFRONT-scope WAF boundary. */
export class MxMedGlobalEdgeStack extends BaseMxMedStack {
  public readonly distribution: CfnDistribution;
  public readonly webAcl: CfnWebACL;
  public readonly originAccessControl: CfnOriginAccessControl;

  public constructor(scope: Construct, id: string, props: MxMedContractStackProps) {
    super(scope, id, {
      ...props,
      component: 'edge-global',
      description: 'MXMed global CloudFront, OAC and five-rule WAF edge; deployment is external.',
      metadata: { dataClassification: 'public', criticality: 'high', backup: 'not-required' },
    });

    const { config } = props;
    if (config.edgeActivationMode === 'disabled-v1') {
      throw new Error('MXMED_GLOBAL_EDGE_DISABLED');
    }

    const publicMediaBucketName = new CfnParameter(this, 'PublicMediaBucketName', {
      type: 'String',
      allowedPattern: '^[a-z0-9][a-z0-9.-]{1,61}[a-z0-9]$',
      description: 'Regional PublicMedia bucket name captured through the approved handoff.',
    });
    const publicMediaDomain = new CfnParameter(this, 'PublicMediaBucketRegionalDomainName', {
      type: 'String',
      allowedPattern: '^[a-z0-9.-]+\\.amazonaws\\.com$',
      description: 'Regional PublicMedia S3 REST domain captured through the approved handoff.',
    });
    publicMediaBucketName.overrideLogicalId('PublicMediaBucketName');

    this.originAccessControl = new CfnOriginAccessControl(this, 'PublicMediaOriginAccessControl', {
      originAccessControlConfig: {
        name: `${config.environmentCode}-mxmed-public-media-oac`,
        description: 'SigV4-only OAC for the MXMed PublicMedia S3 REST origin.',
        originAccessControlOriginType: 's3',
        signingBehavior: 'always',
        signingProtocol: 'sigv4',
      },
    });

    const dynamicCache = this.createCachePolicy('DynamicZeroCachePolicy', {
      name: `${config.environmentCode}-mxmed-dynamic-zero-v1`,
      minTtl: 0,
      defaultTtl: 0,
      maxTtl: 0,
    });
    const staticCache = this.createCachePolicy('StaticAssetCachePolicy', {
      name: `${config.environmentCode}-mxmed-static-zero-v1`,
      minTtl: 0,
      defaultTtl: 0,
      maxTtl: 0,
    });
    const mediaCache = this.createCachePolicy('PublicMediaCachePolicy', {
      name: `${config.environmentCode}-mxmed-media-immutable-v1`,
      minTtl: 86_400,
      defaultTtl: 31_536_000,
      maxTtl: 31_536_000,
    });
    const dynamicOriginRequest = new CfnOriginRequestPolicy(this, 'DynamicOriginRequestPolicy', {
      originRequestPolicyConfig: {
        name: `${config.environmentCode}-mxmed-all-viewer-except-host-v1`,
        comment: 'Forward dynamic query, cookies and viewer headers including Stripe-Signature.',
        cookiesConfig: { cookieBehavior: 'all' },
        headersConfig: { headerBehavior: 'allExcept', headers: ['Host'] },
        queryStringsConfig: { queryStringBehavior: 'all' },
      },
    });
    const staticOriginRequest = new CfnOriginRequestPolicy(this, 'StaticOriginRequestPolicy', {
      originRequestPolicyConfig: {
        name: `${config.environmentCode}-mxmed-static-no-viewer-data-v1`,
        comment: 'Do not forward cookies, authorization or query strings for static assets.',
        cookiesConfig: { cookieBehavior: 'none' },
        headersConfig: { headerBehavior: 'none' },
        queryStringsConfig: { queryStringBehavior: 'none' },
      },
    });
    const mediaOriginRequest = new CfnOriginRequestPolicy(this, 'MediaOriginRequestPolicy', {
      originRequestPolicyConfig: {
        name: `${config.environmentCode}-mxmed-media-no-viewer-data-v1`,
        comment: 'Do not forward cookies, authorization or query strings to PublicMedia.',
        cookiesConfig: { cookieBehavior: 'none' },
        headersConfig: { headerBehavior: 'none' },
        queryStringsConfig: { queryStringBehavior: 'none' },
      },
    });
    const responseHeaders = new CfnResponseHeadersPolicy(this, 'CommonResponseHeadersPolicy', {
      responseHeadersPolicyConfig: {
        name: `${config.environmentCode}-mxmed-common-security-headers-v1`,
        comment: 'Common security headers without overriding the PP235 application CSP.',
        securityHeadersConfig: {
          contentTypeOptions: { override: true },
          frameOptions: { frameOption: 'SAMEORIGIN', override: true },
          referrerPolicy: {
            referrerPolicy: 'strict-origin-when-cross-origin',
            override: true,
          },
          strictTransportSecurity: {
            accessControlMaxAgeSec: 31_536_000,
            includeSubdomains: true,
            preload: false,
            override: true,
          },
        },
        customHeadersConfig: {
          items: [
            {
              header: 'Permissions-Policy',
              value: 'geolocation=(), camera=(), microphone=()',
              override: true,
            },
            { header: 'Cross-Origin-Opener-Policy', value: 'same-origin', override: true },
          ],
        },
      },
    });

    this.webAcl = new CfnWebACL(this, 'CloudFrontWebAcl', {
      name: `${config.environmentCode}-mxmed-free-five-rule-v1`,
      description: 'Exactly five free, metrics-only edge rules; no request sampling or WAF logs.',
      scope: 'CLOUDFRONT',
      defaultAction: { allow: {} },
      visibilityConfig: visibility(`${config.environmentCode}-mxmed-edge-web-acl`),
      rules: [
        this.managedRule(0, 'AmazonIpReputation', 'AWSManagedRulesAmazonIpReputationList'),
        this.managedRule(1, 'Common', 'AWSManagedRulesCommonRuleSet'),
        this.managedRule(2, 'SQLi', 'AWSManagedRulesSQLiRuleSet'),
        {
          name: 'SensitiveRouteRateLimit',
          priority: 3,
          action: { block: {} },
          statement: {
            rateBasedStatement: {
              aggregateKeyType: 'IP',
              limit: 100,
              evaluationWindowSec: 300,
              scopeDownStatement: sensitiveRouteStatement(),
            },
          },
          visibilityConfig: visibility(`${config.environmentCode}-mxmed-sensitive-rate`),
        },
        {
          name: 'GeneralDynamicRateLimit',
          priority: 4,
          action: { block: {} },
          statement: {
            rateBasedStatement: {
              aggregateKeyType: 'IP',
              limit: 1200,
              evaluationWindowSec: 300,
              scopeDownStatement: generalDynamicStatement(),
            },
          },
          visibilityConfig: visibility(`${config.environmentCode}-mxmed-general-rate`),
        },
      ],
    });

    const origins: CfnDistribution.OriginProperty[] = [
      {
        id: 'PublicMediaOrigin',
        domainName: publicMediaDomain.valueAsString,
        originAccessControlId: this.originAccessControl.attrId,
        s3OriginConfig: { originAccessIdentity: '' },
      },
    ];
    const orderedCacheBehaviors: CfnDistribution.CacheBehaviorProperty[] = [];
    let defaultCacheBehavior: CfnDistribution.DefaultCacheBehaviorProperty;
    if (config.edgeActivationMode === 'media-cdn-ready-v1') {
      defaultCacheBehavior = this.cacheBehavior(
        'PublicMediaOrigin',
        mediaCache.ref,
        mediaOriginRequest.ref,
        responseHeaders.ref,
        false,
      );
      orderedCacheBehaviors.push({
        ...this.cacheBehavior(
          'PublicMediaOrigin',
          mediaCache.ref,
          mediaOriginRequest.ref,
          responseHeaders.ref,
          false,
        ),
        pathPattern: MXMED_PUBLIC_MEDIA_PATH,
      });
    } else {
      const regionalOriginDomain = new CfnParameter(this, 'RegionalOriginDomainName', {
        type: 'String',
        allowedPattern: '^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\\.)+[a-z]{2,63}$',
        description: 'Regional ALB origin DNS name captured through the approved handoff.',
      });
      const headerName = new CfnParameter(this, 'EdgeOriginVerificationHeaderName', {
        type: 'String',
        noEcho: true,
        minLength: 1,
        allowedPattern: '^[A-Za-z][A-Za-z0-9-]{0,63}$',
        description: 'Secret custom origin verification header name.',
      });
      const headerValue = new CfnParameter(this, 'EdgeOriginVerificationHeaderValue', {
        type: 'String',
        noEcho: true,
        minLength: 32,
        allowedPattern: '^[A-Za-z0-9._~+/=-]{32,256}$',
        description: 'Secret custom origin verification header value.',
      });
      origins.unshift({
        id: 'ApplicationOrigin',
        domainName: regionalOriginDomain.valueAsString,
        connectionAttempts: 3,
        connectionTimeout: 10,
        originCustomHeaders: [
          {
            headerName: headerName.valueAsString,
            headerValue: headerValue.valueAsString,
          },
        ],
        customOriginConfig: {
          httpPort: 80,
          httpsPort: 443,
          originKeepaliveTimeout: 5,
          originProtocolPolicy: 'https-only',
          originReadTimeout: 60,
          originSslProtocols: ['TLSv1.2'],
        },
      });
      defaultCacheBehavior = this.cacheBehavior(
        'ApplicationOrigin',
        dynamicCache.ref,
        dynamicOriginRequest.ref,
        responseHeaders.ref,
        true,
      );
      orderedCacheBehaviors.push(
        {
          ...this.cacheBehavior(
            'ApplicationOrigin',
            staticCache.ref,
            staticOriginRequest.ref,
            responseHeaders.ref,
            false,
          ),
          pathPattern: MXMED_STATIC_ASSET_PATH,
        },
        {
          ...this.cacheBehavior(
            'PublicMediaOrigin',
            mediaCache.ref,
            mediaOriginRequest.ref,
            responseHeaders.ref,
            false,
          ),
          pathPattern: MXMED_PUBLIC_MEDIA_PATH,
        },
      );
    }

    let aliases: string[] | undefined;
    let viewerCertificate: CfnDistribution.ViewerCertificateProperty = {
      cloudFrontDefaultCertificate: true,
    };
    let hostedZoneId: CfnParameter | undefined;
    let apexDomain: CfnParameter | undefined;
    let wwwDomain: CfnParameter | undefined;
    if (config.edgeActivationMode === 'public-traffic-enabled-v1') {
      const viewerCertificateArn = new CfnParameter(this, 'EdgeViewerCertificateArn', {
        type: 'String',
        allowedPattern: '^arn:[^:]+:acm:us-east-1:[0-9]{12}:certificate/[0-9a-f-]+$',
        description: 'ARN of the externally issued us-east-1 viewer certificate.',
      });
      apexDomain = new CfnParameter(this, 'EdgeApexDomainName', {
        type: 'String',
        allowedPattern: '^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\\.)+[a-z]{2,63}$',
      });
      wwwDomain = new CfnParameter(this, 'EdgeWwwDomainName', {
        type: 'String',
        allowedPattern:
          '^www\\.(?=.{1,249}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\\.)+[a-z]{2,63}$',
      });
      aliases = [apexDomain.valueAsString, wwwDomain.valueAsString];
      viewerCertificate = {
        acmCertificateArn: viewerCertificateArn.valueAsString,
        minimumProtocolVersion: 'TLSv1.2_2021',
        sslSupportMethod: 'sni-only',
      };
      if (config.edgeDnsMode === 'route53-managed-v1') {
        hostedZoneId = new CfnParameter(this, 'EdgeHostedZoneId', {
          type: 'String',
          allowedPattern: '^Z[A-Z0-9]+$',
          description: 'Existing approved Route 53 public hosted zone ID.',
        });
      }
    }

    const distributionConfig: CfnDistribution.DistributionConfigProperty = {
      enabled: config.edgeActivationMode !== 'application-origin-ready-v1',
      comment: `${config.environmentCode} MXMed ${config.edgeActivationMode}`,
      defaultCacheBehavior,
      origins,
      cacheBehaviors: orderedCacheBehaviors,
      httpVersion: 'http2',
      ipv6Enabled: false,
      webAclId: this.webAcl.attrArn,
      viewerCertificate,
      ...(aliases === undefined ? {} : { aliases }),
    };
    this.distribution = new CfnDistribution(this, 'Distribution', { distributionConfig });

    if (hostedZoneId !== undefined && apexDomain !== undefined && wwwDomain !== undefined) {
      for (const [id, domain] of [
        ['ApexAliasRecord', apexDomain],
        ['WwwAliasRecord', wwwDomain],
      ] as const) {
        new CfnRecordSet(this, id, {
          hostedZoneId: hostedZoneId.valueAsString,
          name: domain.valueAsString,
          type: 'A',
          aliasTarget: {
            dnsName: this.distribution.attrDomainName,
            hostedZoneId: CLOUDFRONT_HOSTED_ZONE_ID,
            evaluateTargetHealth: false,
          },
        });
      }
    }

    const tagOptions = { priority: 400 };
    for (const resource of [this.distribution, this.webAcl, this.originAccessControl]) {
      Tags.of(resource).add('EdgeActivationMode', config.edgeActivationMode, tagOptions);
      Tags.of(resource).add('EdgePricingProfile', config.edgePricingProfile, tagOptions);
      Tags.of(resource).add('CostReview', 'required', tagOptions);
      Tags.of(resource).add('CostTier', 'usage-controlled', tagOptions);
    }

    new CfnOutput(this, 'DistributionId', { value: this.distribution.ref });
    new CfnOutput(this, 'DistributionArn', {
      value: Fn.sub(`arn:\${AWS::Partition}:cloudfront::\${AWS::AccountId}:distribution/\${Id}`, {
        Id: this.distribution.ref,
      }),
    });
    new CfnOutput(this, 'DistributionDomainName', {
      value: this.distribution.attrDomainName,
    });
    new CfnOutput(this, 'WebAclArn', { value: this.webAcl.attrArn });
  }

  private createCachePolicy(
    id: string,
    ttl: {
      readonly name: string;
      readonly minTtl: number;
      readonly defaultTtl: number;
      readonly maxTtl: number;
    },
  ): CfnCachePolicy {
    return new CfnCachePolicy(this, id, {
      cachePolicyConfig: {
        name: ttl.name,
        comment: 'MXMed deterministic Edge cache contract.',
        minTtl: ttl.minTtl,
        defaultTtl: ttl.defaultTtl,
        maxTtl: ttl.maxTtl,
        parametersInCacheKeyAndForwardedToOrigin: {
          enableAcceptEncodingBrotli: true,
          enableAcceptEncodingGzip: true,
          cookiesConfig: { cookieBehavior: 'none' },
          headersConfig: { headerBehavior: 'none' },
          queryStringsConfig: { queryStringBehavior: 'none' },
        },
      },
    });
  }

  private managedRule(priority: number, suffix: string, name: string): CfnWebACL.RuleProperty {
    return {
      name,
      priority,
      overrideAction: { none: {} },
      statement: {
        managedRuleGroupStatement: { vendorName: 'AWS', name },
      },
      visibilityConfig: visibility(`${this.node.id}-${suffix}`),
    };
  }

  private cacheBehavior(
    originId: string,
    cachePolicyId: string,
    originRequestPolicyId: string,
    responseHeadersPolicyId: string,
    dynamic: boolean,
  ): CfnDistribution.DefaultCacheBehaviorProperty {
    return {
      targetOriginId: originId,
      viewerProtocolPolicy: 'redirect-to-https',
      allowedMethods: dynamic
        ? ['DELETE', 'GET', 'HEAD', 'OPTIONS', 'PATCH', 'POST', 'PUT']
        : ['GET', 'HEAD', 'OPTIONS'],
      cachedMethods: ['GET', 'HEAD', 'OPTIONS'],
      cachePolicyId,
      originRequestPolicyId,
      responseHeadersPolicyId,
      compress: true,
    };
  }
}
