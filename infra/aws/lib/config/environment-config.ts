export type MxMedEnvironmentName = 'staging' | 'production';
export type MxMedEnvironmentCode = 'stg' | 'prd';
export type MxMedAccountSource = 'deployment-identity' | 'ci-variable';
export type MxMedNatStrategy = 'single-az' | 'dual-az';
export type MxMedInterfaceEndpointProfile = 's3-only' | 'production-core';
export type MxMedComputeSizingProfile = 'reduced' | 'production-ha';
export type MxMedDatabaseEngine = 'mysql';
export type MxMedDatabaseStorageType = 'gp3';
export type MxMedDatabaseInsightsMode = 'standard';
export type MxMedDatabaseCloudWatchLogExport = 'error' | 'slowquery';
export type MxMedDatabaseEngineLifecycleSupport = 'open-source-rds-extended-support-disabled';
export type MxMedStripeReturnLoggingPolicy = 'path-only-no-query';
export type MxMedSecurityProfile = 'baseline-v1';
export type MxMedStorageProfile = 'storage-foundation-v1';
export type MxMedStorageEncryptionProfile = 'application-data-kms';
export type MxMedStorageMimeType = 'application/pdf' | 'image/jpeg' | 'image/png' | 'image/webp';
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

export interface MxMedStorageAllowedMimeTypes {
  readonly public: readonly MxMedStorageMimeType[];
  readonly private: readonly MxMedStorageMimeType[];
  readonly clinical: readonly MxMedStorageMimeType[];
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
  readonly securityProfile: MxMedSecurityProfile;
  readonly kmsDeletionWindowDays: number;
  readonly secretRecoveryWindowDays: number;
  readonly cloudTrailLogRetentionDays: number;
  readonly auditArchiveRetentionDays: number;
  readonly enableManagementTrail: boolean;
  readonly enableKeyRotation: boolean;
  readonly enableDataEventTrail: boolean;
  readonly computeSizingProfile: MxMedComputeSizingProfile;
  readonly databaseEngine: MxMedDatabaseEngine;
  readonly databaseEngineVersion: '8.4.9';
  readonly databaseParameterGroupFamily: 'mysql8.4';
  readonly databaseInstanceClass: 'db.t4g.medium' | 'db.m6g.large';
  readonly databaseMultiAz: boolean;
  readonly databaseAllocatedStorageGiB: number;
  readonly databaseMaxAllocatedStorageGiB: number;
  readonly databaseStorageType: MxMedDatabaseStorageType;
  readonly databaseIops: number;
  readonly databaseStorageThroughput: number;
  readonly databaseBackupRetentionDays: number;
  readonly databaseDeletionProtection: boolean;
  readonly databaseInsightsMode: MxMedDatabaseInsightsMode;
  readonly databaseEnhancedMonitoringIntervalSeconds: number;
  readonly databasePreferredBackupWindow: string;
  readonly databasePreferredMaintenanceWindow: string;
  readonly databaseCloudWatchLogsExports: readonly MxMedDatabaseCloudWatchLogExport[];
  readonly databaseName: 'mxmed';
  readonly databaseMasterUsername: 'mxmed_admin';
  readonly databaseCharacterSet: 'utf8mb4';
  readonly databaseCollation: 'utf8mb4_unicode_ci';
  readonly databaseEngineLifecycleSupport: MxMedDatabaseEngineLifecycleSupport;
  readonly storageProfile: MxMedStorageProfile;
  readonly storageVersioningEnabled: boolean;
  readonly storageEncryptionProfile: MxMedStorageEncryptionProfile;
  readonly storageBucketKeyEnabled: boolean;
  readonly publicMediaNoncurrentRetentionDays: number;
  readonly privateDocumentsNoncurrentRetentionDays: number | null;
  readonly clinicalNoncurrentRetentionDays: number | null;
  readonly quarantinePendingRetentionDays: number;
  readonly quarantineFailedRetentionDays: number;
  readonly quarantineInfectedRetentionDays: number;
  readonly quarantineCleanRetentionDays: number;
  readonly temporaryExportRetentionDays: number;
  readonly privateStorageTransitionDays: number | null;
  readonly clinicalStorageTransitionDays: number | null;
  readonly uploadUrlTtlSeconds: number;
  readonly downloadUrlTtlSeconds: number;
  readonly publicMediaMaxUploadMiB: number;
  readonly publicMediaMaxDerivedMiB: number;
  readonly privateMaxUploadMiB: number;
  readonly clinicalMaxUploadMiB: number;
  readonly enableQuarantineEventBridge: boolean;
  readonly enableObjectLock: boolean;
  readonly enableCrossRegionReplication: boolean;
  readonly enableStorageDataEvents: boolean;
  readonly storageAllowedMimeTypes: MxMedStorageAllowedMimeTypes;
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
