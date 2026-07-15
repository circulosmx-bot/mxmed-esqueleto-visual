export type MxMedEnvironmentName = 'staging' | 'production';
export type MxMedEnvironmentCode = 'stg' | 'prd';
export type MxMedAccountSource = 'deployment-identity' | 'ci-variable';
export type MxMedNatStrategy = 'single-az' | 'dual-az';
export type MxMedInterfaceEndpointProfile = 's3-only' | 'production-core';
export type MxMedComputeSizingProfile = 'reduced' | 'production-ha';
export type MxMedDatabaseSizingProfile = 'single-az-reduced' | 'multi-az-production';
export type MxMedStripeReturnLoggingPolicy = 'path-only-no-query';
export type MxMedDataClassification = 'public' | 'internal' | 'sensitive' | 'clinical';
export type MxMedCriticality = 'low' | 'medium' | 'high';
export type MxMedBackupRequirement = 'required' | 'not-required';

export interface MxMedGlobalTags {
  readonly Project: 'mxmed';
  readonly Environment: MxMedEnvironmentName;
  readonly ManagedBy: 'aws-cdk';
  readonly Application: 'mexico-medico';
  readonly Owner: 'platform';
}

export interface MxMedStackTagMetadata {
  readonly dataClassification: MxMedDataClassification;
  readonly criticality: MxMedCriticality;
  readonly backup: MxMedBackupRequirement;
}

export interface MxMedSubnetMasks {
  readonly publicIngress: number;
  readonly privateApp: number;
  readonly privateEndpoints: number;
  readonly isolatedData: number;
}

export interface MxMedEnvironmentConfig {
  readonly environmentName: MxMedEnvironmentName;
  readonly environmentCode: MxMedEnvironmentCode;
  readonly projectName: 'mxmed';
  readonly applicationName: 'mexico-medico';
  readonly primaryRegion: string;
  readonly emailRegion: string;
  readonly accountSource: MxMedAccountSource;
  readonly vpcCidr: string;
  readonly subnetMasks: MxMedSubnetMasks;
  readonly availabilityZoneCount: number;
  readonly natStrategy: MxMedNatStrategy;
  readonly interfaceEndpointProfile: MxMedInterfaceEndpointProfile;
  readonly flowLogRetentionDays: number;
  readonly computeSizingProfile: MxMedComputeSizingProfile;
  readonly databaseSizingProfile: MxMedDatabaseSizingProfile;
  readonly domainAlias?: string;
  readonly logRetentionDays: number;
  readonly backupRetentionDays: number;
  readonly enableDeletionProtection: boolean;
  readonly enableTerminationProtection: boolean;
  readonly enableWaf: boolean;
  readonly enableCloudFrontLogging: boolean;
  readonly stripeReturnLoggingPolicy: MxMedStripeReturnLoggingPolicy;
  readonly tags: MxMedGlobalTags;
}

export function mxmedNatGatewayCount(strategy: MxMedNatStrategy): 1 | 2 {
  return strategy === 'single-az' ? 1 : 2;
}

export const MXMED_REQUIRED_GLOBAL_TAG_KEYS = [
  'Project',
  'Environment',
  'ManagedBy',
  'Application',
  'Owner',
] as const;

export const MXMED_REQUIRED_RESOURCE_TAG_KEYS = [
  ...MXMED_REQUIRED_GLOBAL_TAG_KEYS,
  'Component',
  'DataClassification',
  'Criticality',
  'Backup',
] as const;
