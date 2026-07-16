export type MxMedEnvironmentName = 'staging' | 'production';
export type MxMedEnvironmentCode = 'stg' | 'prd';
export type MxMedAccountSource = 'deployment-identity' | 'ci-variable';
export type MxMedNatStrategy = 'single-az' | 'dual-az';
export type MxMedInterfaceEndpointProfile = 's3-only' | 'production-core' | 'measured';
export type MxMedComputeSizingProfile = 'reduced' | 'production-ha';
export type MxMedDeploymentProfile = 'launch-lean-v1' | 'production-standard-v1' | 'scale-ready-v1';
export type MxMedStagingOperatingMode = 'release-window-v1';
export type MxMedDatabaseAvailabilityProfile = 'single-az' | 'multi-az';
export type MxMedSessionAvailabilityProfile = 'single-node' | 'primary-replica';
export type MxMedComputeAvailabilityProfile = 'single-task' | 'ha-minimum';
export type MxMedCostTier =
  'fixed-critical' | 'usage-based' | 'storage-based' | 'deferred-optional';
export type MxMedDatabaseEngine = 'mysql';
export type MxMedDatabaseStorageType = 'gp3';
export type MxMedDatabaseInsightsMode = 'standard';
export type MxMedDatabaseCloudWatchLogExport = 'error' | 'slowquery';
export type MxMedDatabaseEngineLifecycleSupport = 'open-source-rds-extended-support-disabled';
export type MxMedStripeReturnLoggingPolicy = 'path-only-no-query';
export type MxMedSecurityProfile = 'baseline-v1';
export type MxMedStorageProfile = 'storage-foundation-v1';
export type MxMedStorageEncryptionProfile = 'application-data-kms';
export type MxMedSessionProfile = 'session-foundation-v1';
export type MxMedSessionEngine = 'valkey';
export type MxMedSessionTransitEncryptionMode = 'create-time-tls-only';
export type MxMedSessionAuthProfile = 'valkey-rbac-password-v1';
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
  readonly DeploymentProfile: MxMedDeploymentProfile;
  readonly CostReview: string;
  readonly Ephemeral: 'true' | 'false';
  readonly SchedulePolicy: MxMedStagingOperatingMode | 'always-on';
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
  readonly deploymentProfile: MxMedDeploymentProfile;
  readonly stagingOperatingMode: MxMedStagingOperatingMode | null;
  readonly natStrategy: MxMedNatStrategy;
  readonly natGatewayCount: 1 | 2;
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
  readonly computeAvailabilityProfile: MxMedComputeAvailabilityProfile;
  readonly computeDesiredCount: 1 | 2;
  readonly computeMinCapacity: 1 | 2;
  readonly computeMaxCapacity: 1 | 2 | 6;
  readonly computeTaskCpuUnits: 512 | 1024;
  readonly computeTaskMemoryMiB: 1024 | 2048;
  readonly computeArchitecture: 'X86_64';
  readonly computeUseSpot: false;
  readonly computeAssignPublicIp: false;
  readonly databaseAvailabilityProfile: MxMedDatabaseAvailabilityProfile;
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
  readonly databaseProxyEnabled: false;
  readonly databaseReadReplicaCount: 0;
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
  readonly sessionProfile: MxMedSessionProfile;
  readonly sessionAvailabilityProfile: MxMedSessionAvailabilityProfile;
  readonly sessionEngine: MxMedSessionEngine;
  readonly sessionEngineVersion: '8.2';
  readonly sessionNodeType: 'cache.t4g.micro' | 'cache.t4g.medium';
  readonly sessionClusterModeEnabled: boolean;
  readonly sessionShardCount: number;
  readonly sessionReplicaCount: number;
  readonly sessionMultiAzEnabled: boolean;
  readonly sessionAutomaticFailoverEnabled: boolean;
  readonly sessionAtRestEncryptionEnabled: boolean;
  readonly sessionTransitEncryptionEnabled: boolean;
  readonly sessionTransitEncryptionMode: MxMedSessionTransitEncryptionMode;
  readonly sessionIdleTtlSeconds: number;
  readonly sessionAbsoluteLifetimeSeconds: number;
  readonly sessionMaxPayloadKiB: number;
  readonly sessionSnapshotRetentionDays: number;
  readonly sessionAutoMinorVersionUpgrade: boolean;
  readonly sessionPreferredMaintenanceWindow: string;
  readonly sessionParameterGroupFamily: 'valkey8';
  readonly sessionAuthProfile: MxMedSessionAuthProfile;
  readonly sessionAclKeyPattern: string;
  readonly sessionLockEnabled: boolean;
  readonly sessionLockTimeoutSeconds: number;
  readonly sessionLockWaitMicroseconds: number;
  readonly sessionLogDeliveryEnabled: boolean;
  readonly approvedMonthlyBudgetUsd: number | null;
  readonly planningFxMxnPerUsd: number | null;
  readonly planningFxAsOf: string | null;
  readonly anomalyAlertThresholdUsd: number | null;
  readonly maxInfrastructureCostToRevenuePercent: number | null;
  readonly budgetOwner: string | null;
  readonly alertRecipientsConfigured: boolean;
  readonly costReadinessReviewApproved: boolean;
  readonly costEstimateAsOf: string;
  readonly costEstimateVersion: string;
  readonly enableCostBudgets: false;
  readonly enableCostAnomalyDetection: false;
  readonly enableStagingSchedule: false;
  readonly stagingReleaseWindowHours: number | null;
  readonly costAlertThresholdPercentages: readonly [50, 75, 90, 100, 120];
  readonly profilePromotionPolicyVersion: string;
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
  'DeploymentProfile',
  'CostReview',
  'Ephemeral',
  'SchedulePolicy',
] as const;

export const MXMED_REQUIRED_RESOURCE_TAG_KEYS = [
  ...MXMED_REQUIRED_GLOBAL_TAG_KEYS,
  'Component',
  'DataClassification',
  'Criticality',
  'Backup',
  'CostTier',
] as const;
