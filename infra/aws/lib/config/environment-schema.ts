import type {
  MxMedEnvironmentCode,
  MxMedEnvironmentConfig,
  MxMedEnvironmentName,
} from './environment-config';
import { MXMED_REQUIRED_GLOBAL_TAG_KEYS } from './environment-config';
import {
  computeEcrRetention,
  MXMED_COMPUTE_RUNTIME_CONTRACT,
  resolveComputeControls,
} from './compute-config';
import {
  MXMED_COST_AWARE_LAUNCH_PROFILES_CONTRACT,
  MXMED_COST_ESTIMATE_AS_OF,
  resolveLaunchProfile,
} from './launch-profiles';
import { validateEdgeFoundationConfig } from './edge-config';
import { assertMxMedCondition, assertNoSensitiveConfiguration } from '../utils/validation';

const ENVIRONMENT_CODES: Readonly<Record<MxMedEnvironmentName, MxMedEnvironmentCode>> = {
  staging: 'stg',
  production: 'prd',
};

const EXPECTED_VPC_CIDRS: Readonly<Record<MxMedEnvironmentName, string>> = {
  staging: '10.20.0.0/16',
  production: '10.30.0.0/16',
};

const EXPECTED_SUBNET_MASKS = Object.freeze({
  publicIngress: 24,
  privateApp: 20,
  privateEndpoints: 24,
  isolatedData: 24,
});

const EXPECTED_FLOW_LOG_RETENTION_DAYS = Object.freeze({
  staging: 30,
  production: 90,
} as const);

const EXPECTED_SECURITY_CONFIGURATION = Object.freeze({
  staging: {
    kmsDeletionWindowDays: 7,
    cloudTrailLogRetentionDays: 90,
    auditArchiveRetentionDays: 365,
  },
  production: {
    kmsDeletionWindowDays: 30,
    cloudTrailLogRetentionDays: 365,
    auditArchiveRetentionDays: 2555,
  },
} as const);

const EXPECTED_DATABASE_CONFIGURATION = Object.freeze({
  staging: {
    databaseEngine: 'mysql',
    databaseEngineVersion: '8.4.9',
    databaseParameterGroupFamily: 'mysql8.4',
    databaseInstanceClass: 'db.t4g.medium',
    databaseMultiAz: false,
    databaseAllocatedStorageGiB: 40,
    databaseMaxAllocatedStorageGiB: 200,
    databaseStorageType: 'gp3',
    databaseIops: 3000,
    databaseStorageThroughput: 125,
    databaseBackupRetentionDays: 7,
    databaseDeletionProtection: false,
    databaseInsightsMode: 'standard',
    databaseEnhancedMonitoringIntervalSeconds: 60,
    databasePreferredBackupWindow: '00:00-00:30',
    databasePreferredMaintenanceWindow: 'sun:01:30-sun:02:30',
    databaseCloudWatchLogsExports: ['error', 'slowquery'],
    databaseName: 'mxmed',
    databaseMasterUsername: 'mxmed_admin',
    databaseCharacterSet: 'utf8mb4',
    databaseCollation: 'utf8mb4_unicode_ci',
    databaseEngineLifecycleSupport: 'open-source-rds-extended-support-disabled',
  },
  production: {
    databaseEngine: 'mysql',
    databaseEngineVersion: '8.4.9',
    databaseParameterGroupFamily: 'mysql8.4',
    databaseInstanceClass: 'db.m6g.large',
    databaseMultiAz: true,
    databaseAllocatedStorageGiB: 100,
    databaseMaxAllocatedStorageGiB: 1000,
    databaseStorageType: 'gp3',
    databaseIops: 3000,
    databaseStorageThroughput: 125,
    databaseBackupRetentionDays: 35,
    databaseDeletionProtection: true,
    databaseInsightsMode: 'standard',
    databaseEnhancedMonitoringIntervalSeconds: 15,
    databasePreferredBackupWindow: '00:30-01:00',
    databasePreferredMaintenanceWindow: 'sun:02:30-sun:03:30',
    databaseCloudWatchLogsExports: ['error', 'slowquery'],
    databaseName: 'mxmed',
    databaseMasterUsername: 'mxmed_admin',
    databaseCharacterSet: 'utf8mb4',
    databaseCollation: 'utf8mb4_unicode_ci',
    databaseEngineLifecycleSupport: 'open-source-rds-extended-support-disabled',
  },
} as const);

const STORAGE_MIME_TYPES = Object.freeze({
  public: ['image/jpeg', 'image/png', 'image/webp'],
  private: ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'],
  clinical: ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'],
} as const);

const EXPECTED_STORAGE_CONFIGURATION = Object.freeze({
  staging: {
    storageProfile: 'storage-foundation-v1',
    storageVersioningEnabled: true,
    storageEncryptionProfile: 'application-data-kms',
    storageBucketKeyEnabled: true,
    publicMediaNoncurrentRetentionDays: 30,
    privateDocumentsNoncurrentRetentionDays: 30,
    clinicalNoncurrentRetentionDays: 30,
    quarantinePendingRetentionDays: 7,
    quarantineFailedRetentionDays: 14,
    quarantineInfectedRetentionDays: 30,
    quarantineCleanRetentionDays: 1,
    temporaryExportRetentionDays: 7,
    privateStorageTransitionDays: null,
    clinicalStorageTransitionDays: null,
    uploadUrlTtlSeconds: 600,
    downloadUrlTtlSeconds: 300,
    publicMediaMaxUploadMiB: 20,
    publicMediaMaxDerivedMiB: 10,
    privateMaxUploadMiB: 100,
    clinicalMaxUploadMiB: 100,
    enableQuarantineEventBridge: true,
    enableObjectLock: false,
    enableCrossRegionReplication: false,
    enableStorageDataEvents: false,
    storageAllowedMimeTypes: STORAGE_MIME_TYPES,
  },
  production: {
    storageProfile: 'storage-foundation-v1',
    storageVersioningEnabled: true,
    storageEncryptionProfile: 'application-data-kms',
    storageBucketKeyEnabled: true,
    publicMediaNoncurrentRetentionDays: 90,
    privateDocumentsNoncurrentRetentionDays: null,
    clinicalNoncurrentRetentionDays: null,
    quarantinePendingRetentionDays: 7,
    quarantineFailedRetentionDays: 14,
    quarantineInfectedRetentionDays: 30,
    quarantineCleanRetentionDays: 1,
    temporaryExportRetentionDays: 7,
    privateStorageTransitionDays: 30,
    clinicalStorageTransitionDays: 30,
    uploadUrlTtlSeconds: 600,
    downloadUrlTtlSeconds: 300,
    publicMediaMaxUploadMiB: 20,
    publicMediaMaxDerivedMiB: 10,
    privateMaxUploadMiB: 100,
    clinicalMaxUploadMiB: 100,
    enableQuarantineEventBridge: true,
    enableObjectLock: false,
    enableCrossRegionReplication: false,
    enableStorageDataEvents: false,
    storageAllowedMimeTypes: STORAGE_MIME_TYPES,
  },
} as const);

const EXPECTED_SESSION_CONFIGURATION = Object.freeze({
  staging: {
    sessionProfile: 'session-foundation-v1',
    sessionEngine: 'valkey',
    sessionEngineVersion: '8.2',
    sessionNodeType: 'cache.t4g.micro',
    sessionClusterModeEnabled: false,
    sessionShardCount: 1,
    sessionReplicaCount: 0,
    sessionMultiAzEnabled: false,
    sessionAutomaticFailoverEnabled: false,
    sessionAtRestEncryptionEnabled: true,
    sessionTransitEncryptionEnabled: true,
    sessionTransitEncryptionMode: 'create-time-tls-only',
    sessionIdleTtlSeconds: 1800,
    sessionAbsoluteLifetimeSeconds: 43200,
    sessionMaxPayloadKiB: 32,
    sessionSnapshotRetentionDays: 0,
    sessionAutoMinorVersionUpgrade: false,
    sessionPreferredMaintenanceWindow: 'sun:03:30-sun:04:30',
    sessionParameterGroupFamily: 'valkey8',
    sessionAuthProfile: 'valkey-rbac-password-v1',
    sessionAclKeyPattern: '~mxmed:stg:session:*',
    sessionLockEnabled: true,
    sessionLockTimeoutSeconds: 10,
    sessionLockWaitMicroseconds: 100000,
    sessionLogDeliveryEnabled: false,
  },
  production: {
    sessionProfile: 'session-foundation-v1',
    sessionEngine: 'valkey',
    sessionEngineVersion: '8.2',
    sessionNodeType: 'cache.t4g.medium',
    sessionClusterModeEnabled: false,
    sessionShardCount: 1,
    sessionReplicaCount: 1,
    sessionMultiAzEnabled: true,
    sessionAutomaticFailoverEnabled: true,
    sessionAtRestEncryptionEnabled: true,
    sessionTransitEncryptionEnabled: true,
    sessionTransitEncryptionMode: 'create-time-tls-only',
    sessionIdleTtlSeconds: 1800,
    sessionAbsoluteLifetimeSeconds: 43200,
    sessionMaxPayloadKiB: 32,
    sessionSnapshotRetentionDays: 0,
    sessionAutoMinorVersionUpgrade: false,
    sessionPreferredMaintenanceWindow: 'sun:04:30-sun:05:30',
    sessionParameterGroupFamily: 'valkey8',
    sessionAuthProfile: 'valkey-rbac-password-v1',
    sessionAclKeyPattern: '~mxmed:prd:session:*',
    sessionLockEnabled: true,
    sessionLockTimeoutSeconds: 10,
    sessionLockWaitMicroseconds: 100000,
    sessionLogDeliveryEnabled: false,
  },
} as const);

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function containsIpv6Field(value: unknown, visited = new Set<object>()): boolean {
  if (typeof value !== 'object' || value === null || visited.has(value)) {
    return false;
  }

  visited.add(value);
  if (Array.isArray(value)) {
    return value.some((entry) => containsIpv6Field(entry, visited));
  }

  return Object.entries(value).some(
    ([key, entry]) => key.toLowerCase().includes('ipv6') || containsIpv6Field(entry, visited),
  );
}

function isRfc1918Cidr16(value: unknown): value is string {
  if (typeof value !== 'string') {
    return false;
  }

  const match = /^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})\/(\d{1,2})$/.exec(value);
  if (match === null) {
    return false;
  }

  const octets = match.slice(1, 5).map(Number);
  const prefix = Number(match[5]);
  if (octets.some((octet) => !Number.isInteger(octet) || octet < 0 || octet > 255)) {
    return false;
  }

  const first = octets[0];
  const second = octets[1];
  const privateRange =
    first === 10 ||
    (first === 172 && second !== undefined && second >= 16 && second <= 31) ||
    (first === 192 && second === 168);
  return privateRange && prefix === 16;
}

function validateSubnetMasks(value: unknown): void {
  assertMxMedCondition(
    isRecord(value),
    'MXMED_CONFIG_INVALID',
    'subnetMasks',
    'must be the contracted subnet mask map',
  );

  const expectedKeys = Object.keys(EXPECTED_SUBNET_MASKS).sort();
  assertMxMedCondition(
    Object.keys(value).sort().join(',') === expectedKeys.join(','),
    'MXMED_CONFIG_INVALID',
    'subnetMasks',
    'must contain only the contracted subnet tiers',
  );

  for (const [key, mask] of Object.entries(EXPECTED_SUBNET_MASKS)) {
    assertMxMedCondition(
      value[key] === mask,
      'MXMED_CONFIG_INVALID',
      'subnetMasks',
      'must match the MXMed V1 network contract',
    );
  }
}

function assertBooleanField(config: Record<string, unknown>, field: string): void {
  assertMxMedCondition(
    typeof config[field] === 'boolean',
    'MXMED_CONFIG_INVALID',
    field,
    'must be boolean',
  );
}

function validateTags(
  value: unknown,
  environmentName: MxMedEnvironmentName,
  deploymentProfile: unknown,
): void {
  assertMxMedCondition(isRecord(value), 'MXMED_CONFIG_INVALID', 'tags', 'must be a tag map');

  for (const key of MXMED_REQUIRED_GLOBAL_TAG_KEYS) {
    assertMxMedCondition(
      typeof value[key] === 'string' && value[key].length > 0,
      'MXMED_CONFIG_INVALID',
      'tags',
      `mandatory tag ${key} is required`,
    );
  }

  assertMxMedCondition(
    value.Project === 'mxmed' &&
      value.Environment === environmentName &&
      value.ManagedBy === 'aws-cdk' &&
      value.Application === 'mexico-medico' &&
      value.Owner === 'platform' &&
      value.DeploymentProfile === deploymentProfile &&
      value.CostReview === MXMED_COST_ESTIMATE_AS_OF &&
      value.Ephemeral === (environmentName === 'staging' ? 'true' : 'false') &&
      value.SchedulePolicy === (environmentName === 'staging' ? 'release-window-v1' : 'always-on'),
    'MXMED_CONFIG_INVALID',
    'tags',
    'mandatory tag values must match the MXMed contract',
  );
}

function validateDatabaseConfiguration(
  config: Record<string, unknown>,
  environmentName: MxMedEnvironmentName,
): void {
  const capacity = resolveLaunchProfile(environmentName, config.deploymentProfile).capacity;
  const expected = {
    ...EXPECTED_DATABASE_CONFIGURATION[environmentName],
    databaseAvailabilityProfile: capacity.databaseAvailabilityProfile,
    databaseInstanceClass: capacity.databaseInstanceClass,
    databaseMultiAz: capacity.databaseMultiAz,
    databaseAllocatedStorageGiB: capacity.databaseAllocatedStorageGiB,
    databaseMaxAllocatedStorageGiB: capacity.databaseMaxAllocatedStorageGiB,
    databaseProxyEnabled: capacity.databaseProxyEnabled,
    databaseReadReplicaCount: capacity.databaseReadReplicaCount,
  };
  for (const [field, expectedValue] of Object.entries(expected)) {
    const actualValue = config[field];
    const matches = Array.isArray(expectedValue)
      ? Array.isArray(actualValue) &&
        actualValue.length === expectedValue.length &&
        actualValue.every((entry, index) => entry === expectedValue[index])
      : actualValue === expectedValue;
    assertMxMedCondition(
      matches,
      'MXMED_CONFIG_INVALID',
      field,
      'must match the PP255 data contract for the selected environment',
    );
  }

  assertMxMedCondition(
    config.databaseMasterUsername !== 'root' && config.databaseMasterUsername !== 'admin',
    'MXMED_CONFIG_INVALID',
    'databaseMasterUsername',
    'root and admin are forbidden master usernames',
  );
  assertMxMedCondition(
    config.databaseBackupRetentionDays === config.backupRetentionDays,
    'MXMED_CONFIG_INVALID',
    'databaseBackupRetentionDays',
    'must remain aligned with the environment backup baseline',
  );
  assertMxMedCondition(
    config.databaseDeletionProtection === config.enableDeletionProtection,
    'MXMED_CONFIG_INVALID',
    'databaseDeletionProtection',
    'must remain aligned with the environment deletion baseline',
  );
}

function matchesContractValue(actual: unknown, expected: unknown): boolean {
  if (typeof expected !== 'object' || expected === null) return actual === expected;
  return JSON.stringify(actual) === JSON.stringify(expected);
}

function validateComputeConfiguration(
  config: Record<string, unknown>,
  environmentName: MxMedEnvironmentName,
): void {
  const controls = resolveComputeControls(
    config.computeActivationMode,
    config.runtimeCapabilityProfile,
  );
  assertMxMedCondition(
    config.computeActivationMode === controls.activationMode &&
      config.runtimeCapabilityProfile === controls.runtimeCapabilityProfile,
    'MXMED_CONFIG_INVALID',
    'computeControls',
    'must match the explicit activation mode and capability profile',
  );
  const runtimeFields = {
    computeArchitecture: MXMED_COMPUTE_RUNTIME_CONTRACT.architecture,
    computePlatformVersion: MXMED_COMPUTE_RUNTIME_CONTRACT.platformVersion,
    computePhpMajorVersion: MXMED_COMPUTE_RUNTIME_CONTRACT.phpMajorVersion,
    computeApacheEnabled: MXMED_COMPUTE_RUNTIME_CONTRACT.apacheEnabled,
    computeModRewriteEnabled: MXMED_COMPUTE_RUNTIME_CONTRACT.modRewriteEnabled,
    computeDocumentRoot: MXMED_COMPUTE_RUNTIME_CONTRACT.documentRoot,
    computeContainerPort: MXMED_COMPUTE_RUNTIME_CONTRACT.containerPort,
    computeEphemeralStorageGiB: MXMED_COMPUTE_RUNTIME_CONTRACT.ephemeralStorageGiB,
    computeHealthPath: MXMED_COMPUTE_RUNTIME_CONTRACT.healthPath,
    computeReadinessPath: MXMED_COMPUTE_RUNTIME_CONTRACT.readinessPath,
    computeCpuTargetPercent: MXMED_COMPUTE_RUNTIME_CONTRACT.cpuTargetPercent,
    computeMemoryTargetPercent: MXMED_COMPUTE_RUNTIME_CONTRACT.memoryTargetPercent,
    computeScaleOutCooldownSeconds: MXMED_COMPUTE_RUNTIME_CONTRACT.scaleOutCooldownSeconds,
    computeScaleInCooldownSeconds: MXMED_COMPUTE_RUNTIME_CONTRACT.scaleInCooldownSeconds,
    computeLogRetentionDays: environmentName === 'staging' ? 30 : 90,
    computeEcsExecEnabled: MXMED_COMPUTE_RUNTIME_CONTRACT.ecsExecEnabled,
    computeReadonlyRootFilesystem: MXMED_COMPUTE_RUNTIME_CONTRACT.readonlyRootFilesystem,
    computeImageScanOnPush: MXMED_COMPUTE_RUNTIME_CONTRACT.imageScanOnPush,
    computeImageTagImmutable: MXMED_COMPUTE_RUNTIME_CONTRACT.imageTagImmutable,
    computeMigrationCommandMode: MXMED_COMPUTE_RUNTIME_CONTRACT.migrationCommandMode,
  } as const;
  for (const [field, expected] of Object.entries(runtimeFields)) {
    assertMxMedCondition(
      config[field] === expected,
      'MXMED_CONFIG_INVALID',
      field,
      'must match MXMED_AWS_COMPUTE_FOUNDATION_CONTRACT_V1',
    );
  }
  const deploymentProfile = config.deploymentProfile;
  assertMxMedCondition(
    typeof deploymentProfile === 'string',
    'MXMED_CONFIG_INVALID',
    'deploymentProfile',
    'must be resolved before Compute retention',
  );
  const retention = computeEcrRetention(
    environmentName,
    deploymentProfile as MxMedEnvironmentConfig['deploymentProfile'],
  );
  assertMxMedCondition(
    config.computeEcrUntaggedRetentionDays === retention.untaggedDays &&
      config.computeEcrMaxImageCount === retention.maxImages,
    'MXMED_CONFIG_INVALID',
    'computeEcrRetention',
    'must match the selected deployment profile',
  );
}

function validateStorageConfiguration(
  config: Record<string, unknown>,
  environmentName: MxMedEnvironmentName,
): void {
  const expected = EXPECTED_STORAGE_CONFIGURATION[environmentName];
  for (const [field, expectedValue] of Object.entries(expected)) {
    assertMxMedCondition(
      matchesContractValue(config[field], expectedValue),
      'MXMED_CONFIG_INVALID',
      field,
      'must match the PP257 storage contract for the selected environment',
    );
  }
  assertMxMedCondition(
    config.enableCrossRegionReplication === false,
    'MXMED_CONFIG_INVALID',
    'enableCrossRegionReplication',
    'must remain deferred until Backup/DR Readiness',
  );
}

function validateSessionConfiguration(
  config: Record<string, unknown>,
  environmentName: MxMedEnvironmentName,
): void {
  const capacity = resolveLaunchProfile(environmentName, config.deploymentProfile).capacity;
  const expected = {
    ...EXPECTED_SESSION_CONFIGURATION[environmentName],
    sessionAvailabilityProfile: capacity.sessionAvailabilityProfile,
    sessionNodeType: capacity.sessionNodeType,
    sessionReplicaCount: capacity.sessionReplicaCount,
    sessionMultiAzEnabled: capacity.sessionMultiAzEnabled,
    sessionAutomaticFailoverEnabled: capacity.sessionAutomaticFailoverEnabled,
  };
  for (const [field, expectedValue] of Object.entries(expected)) {
    assertMxMedCondition(
      config[field] === expectedValue,
      'MXMED_CONFIG_INVALID',
      field,
      'must match the PP260 session contract for the selected environment',
    );
  }
}

function validateCostAwareConfiguration(
  config: Record<string, unknown>,
  environmentName: MxMedEnvironmentName,
): void {
  const resolved = resolveLaunchProfile(environmentName, config.deploymentProfile);
  assertMxMedCondition(
    config.stagingOperatingMode === resolved.stagingOperatingMode,
    'MXMED_CONFIG_INVALID',
    'stagingOperatingMode',
    'must match release-window-v1 for staging and be null for production',
  );
  for (const field of [
    'approvedMonthlyBudgetUsd',
    'planningFxMxnPerUsd',
    'anomalyAlertThresholdUsd',
    'maxInfrastructureCostToRevenuePercent',
  ]) {
    const value = config[field];
    assertMxMedCondition(
      value === null || (typeof value === 'number' && Number.isFinite(value) && value > 0),
      'MXMED_CONFIG_INVALID',
      field,
      'must be null before business approval or a positive approved value',
    );
  }
  assertMxMedCondition(
    config.planningFxAsOf === null ||
      (typeof config.planningFxAsOf === 'string' &&
        /^\d{4}-\d{2}-\d{2}$/.test(config.planningFxAsOf)),
    'MXMED_CONFIG_INVALID',
    'planningFxAsOf',
    'must be null or an ISO planning date',
  );
  assertMxMedCondition(
    config.budgetOwner === null ||
      (typeof config.budgetOwner === 'string' && config.budgetOwner.trim().length > 0),
    'MXMED_CONFIG_INVALID',
    'budgetOwner',
    'must be null or a non-empty approved owner',
  );
  for (const field of ['alertRecipientsConfigured', 'costReadinessReviewApproved']) {
    assertBooleanField(config, field);
  }
  assertMxMedCondition(
    config.costEstimateAsOf === MXMED_COST_ESTIMATE_AS_OF &&
      config.costEstimateVersion === MXMED_COST_AWARE_LAUNCH_PROFILES_CONTRACT &&
      config.profilePromotionPolicyVersion === MXMED_COST_AWARE_LAUNCH_PROFILES_CONTRACT,
    'MXMED_CONFIG_INVALID',
    'costEstimateVersion',
    'must identify the PP263 estimate and promotion policy',
  );
  assertMxMedCondition(
    config.enableCostBudgets === false && config.enableCostAnomalyDetection === false,
    'MXMED_CONFIG_INVALID',
    'costControls',
    'AWS billing resources remain deferred to their own implementation',
  );
  assertMxMedCondition(
    config.enableStagingSchedule === false && config.stagingReleaseWindowHours === null,
    'MXMED_CONFIG_INVALID',
    'stagingSchedule',
    'scheduler remains deferred and release window hours have no invented default',
  );
  assertMxMedCondition(
    JSON.stringify(config.costAlertThresholdPercentages) === JSON.stringify([50, 75, 90, 100, 120]),
    'MXMED_CONFIG_INVALID',
    'costAlertThresholdPercentages',
    'must preserve the PP263 budget thresholds',
  );
}

export function validateEnvironmentConfig(input: unknown): asserts input is MxMedEnvironmentConfig {
  assertNoSensitiveConfiguration(input);
  assertMxMedCondition(
    isRecord(input),
    'MXMED_CONFIG_INVALID',
    'configuration',
    'must be an object',
  );
  assertMxMedCondition(
    !containsIpv6Field(input),
    'MXMED_CONFIG_INVALID',
    'networkAddressFamily',
    'IPv6 configuration is not allowed in V1',
  );

  const environmentName = input.environmentName;
  assertMxMedCondition(
    environmentName === 'staging' || environmentName === 'production',
    'MXMED_ENVIRONMENT_INVALID',
    'environmentName',
    'must be staging or production',
  );

  assertMxMedCondition(
    input.environmentCode === ENVIRONMENT_CODES[environmentName],
    'MXMED_CONFIG_INVALID',
    'environmentCode',
    'must match the selected environment',
  );
  assertMxMedCondition(
    input.projectName === 'mxmed',
    'MXMED_CONFIG_INVALID',
    'projectName',
    'must be mxmed',
  );
  assertMxMedCondition(
    input.applicationName === 'mexico-medico',
    'MXMED_CONFIG_INVALID',
    'applicationName',
    'must be mexico-medico',
  );
  assertMxMedCondition(
    input.primaryRegion === 'mx-central-1',
    'MXMED_CONFIG_INVALID',
    'primaryRegion',
    'must be mx-central-1',
  );
  assertMxMedCondition(
    input.emailRegion === 'us-east-1',
    'MXMED_CONFIG_INVALID',
    'emailRegion',
    'must be us-east-1',
  );
  assertMxMedCondition(
    input.accountSource === 'deployment-identity' || input.accountSource === 'ci-variable',
    'MXMED_CONFIG_INVALID',
    'accountSource',
    'must use an approved deployment source',
  );
  assertMxMedCondition(
    isRfc1918Cidr16(input.vpcCidr),
    'MXMED_CONFIG_INVALID',
    'vpcCidr',
    'must be an RFC1918 /16 CIDR',
  );
  assertMxMedCondition(
    input.vpcCidr === EXPECTED_VPC_CIDRS[environmentName],
    'MXMED_CONFIG_INVALID',
    'vpcCidr',
    'must match the selected environment',
  );
  validateSubnetMasks(input.subnetMasks);
  assertMxMedCondition(
    input.availabilityZoneCount === 2,
    'MXMED_CONFIG_INVALID',
    'availabilityZoneCount',
    'must be exactly two in V1',
  );
  assertMxMedCondition(
    input.natStrategy === 'single-az' || input.natStrategy === 'dual-az',
    'MXMED_CONFIG_INVALID',
    'natStrategy',
    'must be an approved strategy',
  );
  const resolvedLaunchProfile = resolveLaunchProfile(environmentName, input.deploymentProfile);
  assertMxMedCondition(
    input.natStrategy === resolvedLaunchProfile.capacity.natStrategy,
    'MXMED_CONFIG_INVALID',
    'natStrategy',
    'must match the selected environment and deployment profile',
  );
  assertMxMedCondition(
    input.natGatewayCount === resolvedLaunchProfile.capacity.natGatewayCount,
    'MXMED_CONFIG_INVALID',
    'natGatewayCount',
    'must match the selected deployment profile',
  );
  assertMxMedCondition(
    input.interfaceEndpointProfile === resolvedLaunchProfile.capacity.interfaceEndpointProfile,
    'MXMED_CONFIG_INVALID',
    'interfaceEndpointProfile',
    'must match the selected environment and deployment profile',
  );
  assertMxMedCondition(
    input.flowLogRetentionDays === EXPECTED_FLOW_LOG_RETENTION_DAYS[environmentName],
    'MXMED_CONFIG_INVALID',
    'flowLogRetentionDays',
    'must match the selected environment',
  );
  const expectedSecurity = EXPECTED_SECURITY_CONFIGURATION[environmentName];
  assertMxMedCondition(
    input.securityProfile === 'baseline-v1',
    'MXMED_CONFIG_INVALID',
    'securityProfile',
    'must be baseline-v1',
  );
  assertMxMedCondition(
    input.kmsDeletionWindowDays === expectedSecurity.kmsDeletionWindowDays,
    'MXMED_CONFIG_INVALID',
    'kmsDeletionWindowDays',
    'must match the selected environment',
  );
  assertMxMedCondition(
    Number.isInteger(input.secretRecoveryWindowDays) &&
      Number(input.secretRecoveryWindowDays) >= 7 &&
      Number(input.secretRecoveryWindowDays) <= 30 &&
      input.secretRecoveryWindowDays === (environmentName === 'staging' ? 7 : 30),
    'MXMED_CONFIG_INVALID',
    'secretRecoveryWindowDays',
    'must match the operational recovery policy',
  );
  assertMxMedCondition(
    input.cloudTrailLogRetentionDays === expectedSecurity.cloudTrailLogRetentionDays,
    'MXMED_CONFIG_INVALID',
    'cloudTrailLogRetentionDays',
    'must match the selected environment',
  );
  assertMxMedCondition(
    Number.isInteger(input.auditArchiveRetentionDays) &&
      Number(input.auditArchiveRetentionDays) >= expectedSecurity.auditArchiveRetentionDays,
    'MXMED_CONFIG_INVALID',
    'auditArchiveRetentionDays',
    'must meet the selected environment minimum',
  );
  for (const field of ['enableManagementTrail', 'enableKeyRotation', 'enableDataEventTrail']) {
    assertBooleanField(input, field);
  }
  assertMxMedCondition(
    input.enableManagementTrail === true,
    'MXMED_CONFIG_INVALID',
    'enableManagementTrail',
    'must remain enabled',
  );
  assertMxMedCondition(
    input.enableKeyRotation === true,
    'MXMED_CONFIG_INVALID',
    'enableKeyRotation',
    'must remain enabled',
  );
  assertMxMedCondition(
    input.enableDataEventTrail === false,
    'MXMED_CONFIG_INVALID',
    'enableDataEventTrail',
    'must remain false until the clinical Storage contract exists',
  );
  assertMxMedCondition(
    input.computeSizingProfile === 'reduced' || input.computeSizingProfile === 'production-ha',
    'MXMED_CONFIG_INVALID',
    'computeSizingProfile',
    'must be an approved profile',
  );
  for (const field of [
    'computeSizingProfile',
    'computeAvailabilityProfile',
    'computeDesiredCount',
    'computeMinCapacity',
    'computeMaxCapacity',
    'computeTaskCpuUnits',
    'computeTaskMemoryMiB',
    'computeArchitecture',
    'computeUseSpot',
    'computeAssignPublicIp',
  ] as const) {
    assertMxMedCondition(
      input[field] === resolvedLaunchProfile.capacity[field],
      'MXMED_CONFIG_INVALID',
      field,
      'must match the selected deployment profile',
    );
  }
  if (input.domainAlias !== undefined) {
    assertMxMedCondition(
      typeof input.domainAlias === 'string' &&
        /^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/.test(
          input.domainAlias,
        ),
      'MXMED_CONFIG_INVALID',
      'domainAlias',
      'must be omitted or a valid lowercase DNS alias',
    );
  }

  assertMxMedCondition(
    Number.isInteger(input.logRetentionDays) &&
      Number(input.logRetentionDays) >= (environmentName === 'production' ? 90 : 30) &&
      Number(input.logRetentionDays) <= 3653,
    'MXMED_CONFIG_INVALID',
    'logRetentionDays',
    'must be within the environment retention range',
  );
  assertMxMedCondition(
    Number.isInteger(input.backupRetentionDays) &&
      Number(input.backupRetentionDays) >= (environmentName === 'production' ? 35 : 7) &&
      Number(input.backupRetentionDays) <= 3650,
    'MXMED_CONFIG_INVALID',
    'backupRetentionDays',
    'must be within the environment retention range',
  );

  for (const field of [
    'enableDeletionProtection',
    'enableTerminationProtection',
    'enableWaf',
    'enableCloudFrontLogging',
  ]) {
    assertBooleanField(input, field);
  }

  if (environmentName === 'production') {
    for (const field of [
      'enableDeletionProtection',
      'enableTerminationProtection',
      'enableWaf',
      'enableCloudFrontLogging',
    ]) {
      assertMxMedCondition(
        input[field] === true,
        'MXMED_CONFIG_INVALID',
        field,
        'must be enabled in production',
      );
    }
  }

  assertMxMedCondition(
    input.enableWaf === true && input.enableCloudFrontLogging === true,
    'MXMED_CONFIG_INVALID',
    'environmentGuardrails',
    'WAF and safe CloudFront logging are required',
  );
  validateDatabaseConfiguration(input, environmentName);
  validateStorageConfiguration(input, environmentName);
  validateSessionConfiguration(input, environmentName);
  validateComputeConfiguration(input, environmentName);
  validateCostAwareConfiguration(input, environmentName);
  validateEdgeFoundationConfig(input as unknown as MxMedEnvironmentConfig);
  assertMxMedCondition(
    input.stripeReturnLoggingPolicy === 'path-only-no-query',
    'MXMED_CONFIG_INVALID',
    'stripeReturnLoggingPolicy',
    'must be path-only-no-query',
  );

  validateTags(input.tags, environmentName, input.deploymentProfile);
}

export function validateEnvironmentNetworkSeparation(
  staging: MxMedEnvironmentConfig,
  production: MxMedEnvironmentConfig,
): void {
  assertMxMedCondition(
    staging.environmentName === 'staging' && production.environmentName === 'production',
    'MXMED_CONFIG_INVALID',
    'networkEnvironments',
    'must compare staging and production in canonical order',
  );
  assertMxMedCondition(
    staging.vpcCidr !== production.vpcCidr,
    'MXMED_CONFIG_INVALID',
    'vpcCidrPair',
    'staging and production CIDRs must differ',
  );
}

export function parseEnvironmentName(value: unknown): MxMedEnvironmentName {
  assertMxMedCondition(
    value === 'staging' || value === 'production',
    'MXMED_ENVIRONMENT_INVALID',
    'environment',
    'context must be staging or production',
  );
  return value;
}
