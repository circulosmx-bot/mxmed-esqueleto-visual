import type {
  MxMedBackupDataResidencyState,
  MxMedBackupDrActivationMode,
  MxMedBackupReadinessState,
  MxMedBackupSelectionMode,
  MxMedBackupValidationState,
  MxMedBackupVaultLockMode,
  MxMedCrossAccountBackupMode,
  MxMedDisasterRecoveryStrategy,
  MxMedDrRegionState,
  MxMedEnvironmentConfig,
  MxMedRestoreTestingMode,
  MxMedValkeyRecoveryMode,
} from './environment-config';

export const MXMED_AWS_BACKUP_DR_FOUNDATION_IMPLEMENTATION_V1 =
  'MXMED_AWS_BACKUP_DR_FOUNDATION_IMPLEMENTATION_V1' as const;

export const MXMED_BACKUP_DR_ACTIVATION_MODES = Object.freeze([
  'disabled-v1',
  'regional-recovery-ready-v1',
  'cross-region-copy-ready-v1',
  'restore-validation-ready-v1',
] as const satisfies readonly MxMedBackupDrActivationMode[]);

export const MXMED_BACKUP_VAULT_LOCK_MODES = Object.freeze([
  'unlocked-v1',
  'governance-v1',
  'compliance-approved-v1',
] as const satisfies readonly MxMedBackupVaultLockMode[]);

export const MXMED_DR_REGION_STATES = Object.freeze([
  'not-selected-v1',
  'selected-and-verified-v1',
] as const satisfies readonly MxMedDrRegionState[]);

export const MXMED_CROSS_ACCOUNT_BACKUP_MODES = Object.freeze([
  'disabled-v1',
  'organization-vault-approved-v1',
] as const satisfies readonly MxMedCrossAccountBackupMode[]);

export const MXMED_RESTORE_TESTING_MODES = Object.freeze([
  'disabled-v1',
  'manual-quarterly-v1',
  'scheduled-monthly-v1',
] as const satisfies readonly MxMedRestoreTestingMode[]);

export const MXMED_BACKUP_DATA_RESIDENCY_STATES = Object.freeze([
  'pending-review-v1',
  'approved-v1',
] as const satisfies readonly MxMedBackupDataResidencyState[]);

export const MXMED_BACKUP_VALIDATION_STATES = Object.freeze([
  'not-tested-v1',
  'restore-job-completed-v1',
  'application-validation-passed-v1',
] as const satisfies readonly MxMedBackupValidationState[]);

export const MXMED_BACKUP_SELECTION_MODES = Object.freeze([
  'explicit-resource-arns-v1',
  'verified-tags-v1',
] as const satisfies readonly MxMedBackupSelectionMode[]);

export const MXMED_BACKUP_READINESS_STATES = Object.freeze([
  'not-protected-v1',
  'backup-configured-v1',
  'recovery-point-available-v1',
  'restore-job-completed-v1',
  'application-validated-v1',
  'dr-ready-v1',
] as const satisfies readonly MxMedBackupReadinessState[]);

export interface MxMedBackupDrContextValues {
  readonly backupDrActivationMode?: unknown;
  readonly backupVaultLockMode?: unknown;
  readonly drRegionState?: unknown;
  readonly drRegion?: unknown;
  readonly backupDataResidencyState?: unknown;
  readonly crossAccountBackupMode?: unknown;
  readonly restoreTestingMode?: unknown;
  readonly backupSelectionMode?: unknown;
  readonly backupValidationState?: unknown;
  readonly backupComplianceChangeableForDays?: unknown;
  readonly backupApplicationValidationIntegrated?: unknown;
  readonly backupSentinelsIntegrated?: unknown;
}

export type MxMedResolvedBackupDrConfig = Readonly<
  Pick<
    MxMedEnvironmentConfig,
    | 'backupDrActivationMode'
    | 'disasterRecoveryStrategy'
    | 'backupVaultLockMode'
    | 'drRegionState'
    | 'drRegion'
    | 'backupDataResidencyState'
    | 'crossAccountBackupMode'
    | 'restoreTestingMode'
    | 'backupSelectionMode'
    | 'backupValidationState'
    | 'valkeyRecoveryMode'
    | 'backupReadinessState'
    | 'backupRdsPeriodicRetentionDays'
    | 'backupRdsMonthlyRetentionDays'
    | 'backupS3ContinuousRetentionDays'
    | 'backupS3PeriodicRetentionDays'
    | 'backupCrossRegionRetentionDays'
    | 'backupStartWindowMinutes'
    | 'backupCompletionWindowMinutes'
    | 'backupVaultMinRetentionDays'
    | 'backupVaultMaxRetentionDays'
    | 'backupComplianceChangeableForDays'
    | 'backupRestoreTestMaxRuntimeHours'
    | 'backupRestoreTestCleanupDeadlineHours'
    | 'backupAutomaticFailoverEnabled'
    | 'backupAutomaticFailbackEnabled'
    | 'backupPublicMediaProtectionEnabled'
    | 'backupQuarantineProtectionEnabled'
    | 'backupAuditBucketProtectionEnabled'
    | 'backupMonitoringEnabled'
    | 'backupApplicationValidationIntegrated'
    | 'backupSentinelsIntegrated'
  >
>;

function select<const T extends readonly string[]>(
  field: string,
  value: unknown,
  allowed: T,
  fallback: T[number],
): T[number] {
  const selected = value === undefined ? fallback : value;
  if (typeof selected !== 'string' || !allowed.includes(selected)) {
    throw new Error(`MXMED_BACKUP_DR_CONFIG_INVALID:${field}`);
  }
  return selected;
}

function optionalBoolean(field: string, value: unknown, fallback: boolean): boolean {
  const selected = value === undefined ? fallback : value;
  if (typeof selected !== 'boolean') throw new Error(`MXMED_BACKUP_DR_CONFIG_INVALID:${field}`);
  return selected;
}

function optionalPositiveInteger(field: string, value: unknown): number | undefined {
  if (value === undefined) return undefined;
  if (!Number.isInteger(value) || Number(value) < 1) {
    throw new Error(`MXMED_BACKUP_DR_CONFIG_INVALID:${field}`);
  }
  return Number(value);
}

export function backupDrCreatesRegional(config: MxMedEnvironmentConfig): boolean {
  return config.backupDrActivationMode !== 'disabled-v1';
}

export function backupDrCreatesCrossRegion(config: MxMedEnvironmentConfig): boolean {
  return (
    config.backupDrActivationMode === 'cross-region-copy-ready-v1' ||
    config.backupDrActivationMode === 'restore-validation-ready-v1'
  );
}

export function backupDrCreatesRestoreValidation(config: MxMedEnvironmentConfig): boolean {
  return config.backupDrActivationMode === 'restore-validation-ready-v1';
}

export function resolveBackupDrContext(
  values: MxMedBackupDrContextValues = {},
): MxMedResolvedBackupDrConfig {
  const activation = select(
    'backupDrActivationMode',
    values.backupDrActivationMode,
    MXMED_BACKUP_DR_ACTIVATION_MODES,
    'disabled-v1',
  );
  const lockMode = select(
    'backupVaultLockMode',
    values.backupVaultLockMode,
    MXMED_BACKUP_VAULT_LOCK_MODES,
    'governance-v1',
  );
  const drRegionState = select(
    'drRegionState',
    values.drRegionState,
    MXMED_DR_REGION_STATES,
    'not-selected-v1',
  );
  const dataResidency = select(
    'backupDataResidencyState',
    values.backupDataResidencyState,
    MXMED_BACKUP_DATA_RESIDENCY_STATES,
    'pending-review-v1',
  );
  const restoreTestingMode = select(
    'restoreTestingMode',
    values.restoreTestingMode,
    MXMED_RESTORE_TESTING_MODES,
    'disabled-v1',
  );
  const drRegion = values.drRegion;
  if (
    drRegion !== undefined &&
    (typeof drRegion !== 'string' || !/^[a-z]{2}-[a-z]+-[0-9]$/.test(drRegion))
  ) {
    throw new Error('MXMED_BACKUP_DR_CONFIG_INVALID:drRegion');
  }
  const complianceChangeable = optionalPositiveInteger(
    'backupComplianceChangeableForDays',
    values.backupComplianceChangeableForDays,
  );
  const sentinels = optionalBoolean(
    'backupSentinelsIntegrated',
    values.backupSentinelsIntegrated,
    false,
  );
  const applicationValidation = optionalBoolean(
    'backupApplicationValidationIntegrated',
    values.backupApplicationValidationIntegrated,
    false,
  );

  return Object.freeze({
    backupDrActivationMode: activation,
    disasterRecoveryStrategy: 'backup-and-restore-v1' satisfies MxMedDisasterRecoveryStrategy,
    backupVaultLockMode: lockMode,
    drRegionState,
    ...(typeof drRegion === 'string' ? { drRegion } : {}),
    backupDataResidencyState: dataResidency,
    crossAccountBackupMode: select(
      'crossAccountBackupMode',
      values.crossAccountBackupMode,
      MXMED_CROSS_ACCOUNT_BACKUP_MODES,
      'disabled-v1',
    ),
    restoreTestingMode,
    backupSelectionMode: select(
      'backupSelectionMode',
      values.backupSelectionMode,
      MXMED_BACKUP_SELECTION_MODES,
      'explicit-resource-arns-v1',
    ),
    backupValidationState: select(
      'backupValidationState',
      values.backupValidationState,
      MXMED_BACKUP_VALIDATION_STATES,
      'not-tested-v1',
    ),
    valkeyRecoveryMode: 'empty-rebuild-v1' satisfies MxMedValkeyRecoveryMode,
    backupReadinessState:
      activation === 'disabled-v1' ? 'not-protected-v1' : 'backup-configured-v1',
    backupRdsPeriodicRetentionDays: 35,
    backupRdsMonthlyRetentionDays: 365,
    backupS3ContinuousRetentionDays: 35,
    backupS3PeriodicRetentionDays: 35,
    backupCrossRegionRetentionDays: 35,
    backupStartWindowMinutes: 60,
    backupCompletionWindowMinutes: 360,
    backupVaultMinRetentionDays: 1,
    backupVaultMaxRetentionDays: 365,
    ...(complianceChangeable === undefined
      ? {}
      : { backupComplianceChangeableForDays: complianceChangeable }),
    backupRestoreTestMaxRuntimeHours: 24,
    backupRestoreTestCleanupDeadlineHours: 24,
    backupAutomaticFailoverEnabled: false,
    backupAutomaticFailbackEnabled: false,
    backupPublicMediaProtectionEnabled: false,
    backupQuarantineProtectionEnabled: false,
    backupAuditBucketProtectionEnabled: false,
    backupMonitoringEnabled: activation !== 'disabled-v1',
    backupApplicationValidationIntegrated: applicationValidation,
    backupSentinelsIntegrated: sentinels,
  });
}

export function validateBackupDrConfig(config: MxMedEnvironmentConfig): void {
  const active = backupDrCreatesRegional(config);
  const runtimeConfig = config as unknown as Record<string, unknown>;
  if (runtimeConfig.disasterRecoveryStrategy !== 'backup-and-restore-v1') {
    throw new Error('MXMED_BACKUP_DR_STRATEGY_INVALID');
  }
  if (
    runtimeConfig.backupAutomaticFailoverEnabled === true ||
    runtimeConfig.backupAutomaticFailbackEnabled === true ||
    runtimeConfig.backupQuarantineProtectionEnabled === true
  ) {
    throw new Error('MXMED_BACKUP_DR_AUTOMATION_OR_QUARANTINE_FORBIDDEN');
  }
  if (active && config.databaseBackupRetentionDays < 35) {
    throw new Error('rds_native_pitr_retention_below_contract');
  }
  if (
    active &&
    (!config.storageVersioningEnabled || config.backupSelectionMode !== 'explicit-resource-arns-v1')
  ) {
    throw new Error('critical_s3_bucket_versioning_not_enabled');
  }
  if (
    active &&
    (config.operationsActivationMode === 'disabled-v1' ||
      config.operationsNotificationMode === 'none-v1')
  ) {
    throw new Error('backup_monitoring_topics_not_available');
  }
  if (backupDrCreatesCrossRegion(config)) {
    if (
      config.drRegionState !== 'selected-and-verified-v1' ||
      config.backupDataResidencyState !== 'approved-v1' ||
      config.drRegion === undefined
    ) {
      throw new Error('dr_region_not_selected_or_verified');
    }
  }
  if (config.crossAccountBackupMode !== 'disabled-v1') {
    throw new Error('MXMED_BACKUP_DR_CROSS_ACCOUNT_NOT_APPROVED');
  }
  if (
    config.backupVaultLockMode === 'unlocked-v1' &&
    active &&
    config.environmentName === 'production'
  ) {
    throw new Error('MXMED_BACKUP_DR_UNLOCKED_PRODUCTION_FORBIDDEN');
  }
  if (
    config.backupVaultLockMode === 'compliance-approved-v1' &&
    (config.backupComplianceChangeableForDays === undefined ||
      config.backupComplianceChangeableForDays < 3)
  ) {
    throw new Error('MXMED_BACKUP_DR_COMPLIANCE_GATE_CLOSED');
  }
  if (backupDrCreatesRestoreValidation(config)) {
    if (config.restoreTestingMode === 'disabled-v1') {
      throw new Error('MXMED_BACKUP_DR_RESTORE_MODE_REQUIRED');
    }
    if (
      config.restoreTestingMode === 'scheduled-monthly-v1' &&
      (!config.backupSentinelsIntegrated || !config.backupApplicationValidationIntegrated)
    ) {
      throw new Error('restore_testing_sentinels_not_integrated');
    }
  } else if (config.restoreTestingMode !== 'disabled-v1') {
    throw new Error('MXMED_BACKUP_DR_RESTORE_MODE_WITHOUT_ACTIVATION');
  }
  if (
    config.backupValidationState === 'application-validation-passed-v1' &&
    (!config.backupSentinelsIntegrated || !config.backupApplicationValidationIntegrated)
  ) {
    throw new Error('MXMED_BACKUP_DR_APPLICATION_VALIDATION_GATE_CLOSED');
  }
}
