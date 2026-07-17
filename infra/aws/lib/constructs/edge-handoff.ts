export interface MxMedEdgeHandoffContract {
  readonly regionalToGlobal: {
    readonly loadBalancerDnsName: string;
    readonly originDomainName: string;
    readonly originCertificateArn: string;
    readonly publicMediaBucketName: string;
    readonly publicMediaBucketRegionalDomainName: string;
  };
  readonly globalToRegional: {
    readonly cloudFrontDistributionId: string;
    readonly cloudFrontDistributionArn: string;
  };
  readonly independentSecrets: {
    readonly originVerificationHeaderName: string;
    readonly originVerificationHeaderValue: string;
  };
}

export const MXMED_EDGE_HANDOFF_SEQUENCE = Object.freeze([
  'deploy-regional-edge-foundation',
  'capture-non-sensitive-regional-outputs',
  'deploy-global-edge',
  'capture-cloudfront-distribution-arn',
  'update-security-and-storage-source-arn-parameters',
  'verify-oac',
  'attach-approved-pricing-plan-manually',
  'keep-dns-unchanged',
] as const);
