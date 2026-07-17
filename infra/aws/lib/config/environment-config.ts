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
export type MxMedComputeActivationMode =
  'disabled-v1' | 'registry-only-v1' | 'tasks-ready-v1' | 'service-enabled-v1';
export type MxMedRuntimeCapabilityProfile =
  'directory-core-v1' | 'paid-profile-v1' | 'clinical-v1' | 'professional-ai-v1';
export type MxMedMigrationCommandMode = 'fail-closed-v1';
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
export type MxMedBackupDrActivationMode =
  | 'disabled-v1'
  | 'regional-recovery-ready-v1'
  | 'cross-region-copy-ready-v1'
  | 'restore-validation-ready-v1';
export type MxMedDisasterRecoveryStrategy = 'backup-and-restore-v1';
export type MxMedBackupVaultLockMode = 'unlocked-v1' | 'governance-v1' | 'compliance-approved-v1';
export type MxMedDrRegionState = 'not-selected-v1' | 'selected-and-verified-v1';
export type MxMedCrossAccountBackupMode = 'disabled-v1' | 'organization-vault-approved-v1';
export type MxMedRestoreTestingMode =
  'disabled-v1' | 'manual-quarterly-v1' | 'scheduled-monthly-v1';
export type MxMedBackupDataResidencyState = 'pending-review-v1' | 'approved-v1';
export type MxMedBackupValidationState =
  'not-tested-v1' | 'restore-job-completed-v1' | 'application-validation-passed-v1';
export type MxMedBackupSelectionMode = 'explicit-resource-arns-v1' | 'verified-tags-v1';
export type MxMedValkeyRecoveryMode = 'empty-rebuild-v1';
export type MxMedBackupReadinessState =
  | 'not-protected-v1'
  | 'backup-configured-v1'
  | 'recovery-point-available-v1'
  | 'restore-job-completed-v1'
  | 'application-validated-v1'
  | 'dr-ready-v1';
export type MxMedEdgeActivationMode =
  | 'disabled-v1'
  | 'media-cdn-ready-v1'
  | 'application-origin-ready-v1'
  | 'public-traffic-enabled-v1';
export type MxMedCloudFrontPricingProfile =
  'flat-rate-free-v1' | 'flat-rate-pro-v1' | 'pay-as-you-go-approved-v1';
export type MxMedEdgeOriginMode = 'cloudfront-restricted-public-alb-v1';
export type MxMedEdgeLoggingProfile = 'metrics-only-no-request-logs-v1';
export type MxMedEdgeCacheProfile = 'dynamic-zero-media-immutable-v1';
export type MxMedEdgeWafProfile = 'free-five-rule-v1';
export type MxMedEdgeMapsMode = 'external-link-only-v1';
export type MxMedEdgeDnsMode = 'none-v1' | 'external-dns-v1' | 'route53-managed-v1';
export type MxMedEdgeCutoverState = 'blocked-known-gaps-v1' | 'verified-for-cutover-v1';
export type MxMedStaticAssetCacheState =
  'disabled-until-fingerprinted-v1' | 'immutable-fingerprinted-v1';
export type MxMedOperationsActivationMode =
  | 'disabled-v1'
  | 'cost-controls-ready-v1'
  | 'launch-lean-observability-ready-v1'
  | 'production-observability-ready-v1';
export type MxMedOperationsNotificationMode =
  'none-v1' | 'topics-only-v1' | 'external-subscribers-confirmed-v1';
export type MxMedOperationsLogProtectionProfile =
  'source-sanitized-only-v1' | 'targeted-data-protection-v1';
export type MxMedOperationsRuntimeGateState =
  'blocked-known-runtime-gaps-v1' | 'operational-readiness-integrated-v1';
export type MxMedClinicalLogSanitizationState =
  'blocked-legacy-agenda-logs-v1' | 'source-sanitization-verified-v1';
export type MxMedCostAllocationTagState = 'inactive-v1' | 'active-and-verified-v1';
export type MxMedCostAnomalyMonitorOwnershipMode =
  'create-service-monitor-v1' | 'import-existing-service-monitor-v1';
export type MxMedCostTagAnomalyMonitorMode = 'disabled-until-tags-active-v1' | 'enabled-v1';

export interface MxMedCloudFrontPricingPlanVerification {
  readonly expectedProfile: MxMedCloudFrontPricingProfile;
  readonly accountEligibilityVerified: boolean;
  readonly planAttached: boolean;
  readonly verifiedAt: string | null;
  readonly verificationEvidenceReference: string | null;
}

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
  readonly CostScope: 'mxmed-staging' | 'mxmed-production';
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
  readonly computeActivationMode: MxMedComputeActivationMode;
  readonly runtimeCapabilityProfile: MxMedRuntimeCapabilityProfile | null;
  readonly computePlatformVersion: '1.4.0';
  readonly computePhpMajorVersion: '8.5';
  readonly computeApacheEnabled: true;
  readonly computeModRewriteEnabled: true;
  readonly computeDocumentRoot: '/var/www/html';
  readonly computeContainerPort: 8080;
  readonly computeEphemeralStorageGiB: 20;
  readonly computeHealthPath: '/healthz';
  readonly computeReadinessPath: '/readyz';
  readonly computeCpuTargetPercent: 60;
  readonly computeMemoryTargetPercent: 70;
  readonly computeScaleOutCooldownSeconds: 60;
  readonly computeScaleInCooldownSeconds: 300;
  readonly computeLogRetentionDays: 30 | 90;
  readonly computeEcsExecEnabled: false;
  readonly computeReadonlyRootFilesystem: true;
  readonly computeImageScanOnPush: true;
  readonly computeImageTagImmutable: true;
  readonly computeEcrUntaggedRetentionDays: 7 | 14;
  readonly computeEcrMaxImageCount: 20 | 50;
  readonly computeMigrationCommandMode: MxMedMigrationCommandMode;
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
  readonly backupDrActivationMode: MxMedBackupDrActivationMode;
  readonly disasterRecoveryStrategy: MxMedDisasterRecoveryStrategy;
  readonly backupVaultLockMode: MxMedBackupVaultLockMode;
  readonly drRegionState: MxMedDrRegionState;
  readonly drRegion?: string;
  readonly backupDataResidencyState: MxMedBackupDataResidencyState;
  readonly crossAccountBackupMode: MxMedCrossAccountBackupMode;
  readonly restoreTestingMode: MxMedRestoreTestingMode;
  readonly backupSelectionMode: MxMedBackupSelectionMode;
  readonly backupValidationState: MxMedBackupValidationState;
  readonly valkeyRecoveryMode: MxMedValkeyRecoveryMode;
  readonly backupReadinessState: MxMedBackupReadinessState;
  readonly backupRdsPeriodicRetentionDays: 35;
  readonly backupRdsMonthlyRetentionDays: 365;
  readonly backupS3ContinuousRetentionDays: 35;
  readonly backupS3PeriodicRetentionDays: 35;
  readonly backupCrossRegionRetentionDays: 35;
  readonly backupStartWindowMinutes: 60;
  readonly backupCompletionWindowMinutes: 360;
  readonly backupVaultMinRetentionDays: 1;
  readonly backupVaultMaxRetentionDays: 365;
  readonly backupComplianceChangeableForDays?: number;
  readonly backupRestoreTestMaxRuntimeHours: number;
  readonly backupRestoreTestCleanupDeadlineHours: number;
  readonly backupAutomaticFailoverEnabled: false;
  readonly backupAutomaticFailbackEnabled: false;
  readonly backupPublicMediaProtectionEnabled: false;
  readonly backupQuarantineProtectionEnabled: false;
  readonly backupAuditBucketProtectionEnabled: false;
  readonly backupMonitoringEnabled: boolean;
  readonly backupApplicationValidationIntegrated: boolean;
  readonly backupSentinelsIntegrated: boolean;
  readonly enableDeletionProtection: boolean;
  readonly enableTerminationProtection: boolean;
  readonly enableWaf: boolean;
  readonly enableCloudFrontLogging: boolean;
  readonly stripeReturnLoggingPolicy: MxMedStripeReturnLoggingPolicy;
  readonly edgeActivationMode: MxMedEdgeActivationMode;
  readonly edgePricingProfile: MxMedCloudFrontPricingProfile;
  readonly edgeOriginMode: MxMedEdgeOriginMode;
  readonly edgeLoggingProfile: MxMedEdgeLoggingProfile;
  readonly edgeCacheProfile: MxMedEdgeCacheProfile;
  readonly edgeWafProfile: MxMedEdgeWafProfile;
  readonly edgeMapsMode: MxMedEdgeMapsMode;
  readonly edgeDnsMode: MxMedEdgeDnsMode;
  readonly edgeCutoverState: MxMedEdgeCutoverState;
  readonly staticAssetCacheState: MxMedStaticAssetCacheState;
  readonly readinessEndpointIntegrated: boolean;
  readonly stripeReturnRouteImplemented: boolean;
  readonly stripeWebhookRouteConfirmed: boolean;
  readonly assetFingerprintingReady: boolean;
  readonly edgeDomainApproved: boolean;
  readonly viewerCertificateIssued: boolean;
  readonly originCertificateIssued: boolean;
  readonly cloudFrontPricingPlanVerified: boolean;
  readonly budgetApproved: boolean;
  readonly dnsCutoverApproved: boolean;
  readonly cloudFrontPricingPlanVerification: MxMedCloudFrontPricingPlanVerification;
  readonly operationsActivationMode: MxMedOperationsActivationMode;
  readonly operationsNotificationMode: MxMedOperationsNotificationMode;
  readonly operationsLogProtectionProfile: MxMedOperationsLogProtectionProfile;
  readonly operationsRuntimeGateState: MxMedOperationsRuntimeGateState;
  readonly clinicalLogSanitizationState: MxMedClinicalLogSanitizationState;
  readonly operationsIncidentPolicyVersion: 'mxmed-incident-policy-v1';
  readonly operationsRunbookVersion: 'mxmed-operations-runbooks-v1';
  readonly operationsDashboardProfile: 'minimal-profile-aware-v1';
  readonly operationsAlarmProfile: 'profile-aware-v1';
  readonly operationsAutomaticRemediationEnabled: false;
  readonly operationsEcsCpuWarningPercent: 75;
  readonly operationsEcsMemoryWarningPercent: 80;
  readonly operationsRdsCpuWarningPercent: 75;
  readonly operationsRdsFreeStoragePercent: 20;
  readonly operationsRdsConnectionBudgetPercent: 70;
  readonly operationsValkeyMemoryWarningPercent: 75;
  readonly operationsAlbTarget5xxRatePercent: 2;
  readonly operationsCloudFront5xxRatePercent: 1;
  readonly operationsInternalAvailabilityTarget: 99.5 | 99.9;
  readonly operationsDynamicP95TargetMs: 1500 | 2000;
  readonly operationsStagingResidualAuditEnabled: boolean;
  readonly operationsCostScopeTagKey: 'CostScope';
  readonly operationsCostScopeTagValue: 'mxmed-staging' | 'mxmed-production';
  readonly operationsCostAllocationTagState: MxMedCostAllocationTagState;
  readonly costAnomalyMonitorOwnershipMode: MxMedCostAnomalyMonitorOwnershipMode;
  readonly operationsCostTagAnomalyMonitorMode: MxMedCostTagAnomalyMonitorMode;
  readonly applicationMetricEmissionIntegrated: false;
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
  'CostScope',
] as const;

export const MXMED_REQUIRED_RESOURCE_TAG_KEYS = [
  ...MXMED_REQUIRED_GLOBAL_TAG_KEYS,
  'Component',
  'DataClassification',
  'Criticality',
  'Backup',
  'CostTier',
] as const;
