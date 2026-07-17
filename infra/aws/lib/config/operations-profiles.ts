import type {
  MxMedClinicalLogSanitizationState,
  MxMedCostAllocationTagState,
  MxMedCostAnomalyMonitorOwnershipMode,
  MxMedCostTagAnomalyMonitorMode,
  MxMedDeploymentProfile,
  MxMedEnvironmentConfig,
  MxMedEnvironmentName,
  MxMedOperationsActivationMode,
  MxMedOperationsLogProtectionProfile,
  MxMedOperationsNotificationMode,
  MxMedOperationsRuntimeGateState,
} from './environment-config';

export const MXMED_AWS_OPERATIONS_FOUNDATION_IMPLEMENTATION_V1 =
  'MXMED_AWS_OPERATIONS_FOUNDATION_IMPLEMENTATION_V1' as const;

export const MXMED_OPERATIONS_ACTIVATION_MODES = Object.freeze([
  'disabled-v1',
  'cost-controls-ready-v1',
  'launch-lean-observability-ready-v1',
  'production-observability-ready-v1',
] as const);

export const MXMED_OPERATIONS_NOTIFICATION_MODES = Object.freeze([
  'none-v1',
  'topics-only-v1',
  'external-subscribers-confirmed-v1',
] as const);

export const MXMED_OPERATIONS_LOG_PROTECTION_PROFILES = Object.freeze([
  'source-sanitized-only-v1',
  'targeted-data-protection-v1',
] as const);

export const MXMED_OPERATIONS_RUNTIME_GATE_STATES = Object.freeze([
  'blocked-known-runtime-gaps-v1',
  'operational-readiness-integrated-v1',
] as const);

export const MXMED_CLINICAL_LOG_SANITIZATION_STATES = Object.freeze([
  'blocked-legacy-agenda-logs-v1',
  'source-sanitization-verified-v1',
] as const);

export const MXMED_COST_ALLOCATION_TAG_STATES = Object.freeze([
  'inactive-v1',
  'active-and-verified-v1',
] as const);

export const MXMED_COST_ANOMALY_MONITOR_OWNERSHIP_MODES = Object.freeze([
  'create-service-monitor-v1',
  'import-existing-service-monitor-v1',
] as const);

export const MXMED_COST_TAG_ANOMALY_MONITOR_MODES = Object.freeze([
  'disabled-until-tags-active-v1',
  'enabled-v1',
] as const);

export const MXMED_REAL_OPERATIONS_GATES = Object.freeze({
  operationsRuntimeGateState: 'blocked-known-runtime-gaps-v1',
  clinicalLogSanitizationState: 'blocked-legacy-agenda-logs-v1',
  operationsLogProtectionProfile: 'source-sanitized-only-v1',
  applicationMetricEmissionIntegrated: false,
} as const);

export const MXMED_OPERATIONS_THRESHOLDS = Object.freeze({
  ecsCpuWarningPercent: 75,
  ecsMemoryWarningPercent: 80,
  rdsCpuWarningPercent: 75,
  rdsFreeStoragePercent: 20,
  rdsConnectionBudgetPercent: 70,
  valkeyMemoryWarningPercent: 75,
  albTarget5xxRatePercent: 2,
  cloudFront5xxRatePercent: 1,
  launchAvailabilityTarget: 99.5,
  standardAvailabilityTarget: 99.9,
  launchDynamicP95TargetMs: 2000,
  standardDynamicP95TargetMs: 1500,
} as const);

export interface MxMedOperationsContextValues {
  readonly operationsActivationMode?: unknown;
  readonly operationsNotificationMode?: unknown;
  readonly operationsLogProtectionProfile?: unknown;
  readonly operationsRuntimeGateState?: unknown;
  readonly clinicalLogSanitizationState?: unknown;
  readonly costAllocationTagState?: unknown;
  readonly costAnomalyMonitorOwnershipMode?: unknown;
  readonly costTagAnomalyMonitorMode?: unknown;
}

type MxMedResolvedOperationsContext = Readonly<
  Pick<
    MxMedEnvironmentConfig,
    | 'operationsActivationMode'
    | 'operationsNotificationMode'
    | 'operationsLogProtectionProfile'
    | 'operationsRuntimeGateState'
    | 'clinicalLogSanitizationState'
    | 'operationsCostAllocationTagState'
    | 'costAnomalyMonitorOwnershipMode'
    | 'operationsCostTagAnomalyMonitorMode'
    | 'operationsIncidentPolicyVersion'
    | 'operationsRunbookVersion'
    | 'operationsDashboardProfile'
    | 'operationsAlarmProfile'
    | 'operationsAutomaticRemediationEnabled'
    | 'operationsEcsCpuWarningPercent'
    | 'operationsEcsMemoryWarningPercent'
    | 'operationsRdsCpuWarningPercent'
    | 'operationsRdsFreeStoragePercent'
    | 'operationsRdsConnectionBudgetPercent'
    | 'operationsValkeyMemoryWarningPercent'
    | 'operationsAlbTarget5xxRatePercent'
    | 'operationsCloudFront5xxRatePercent'
    | 'operationsInternalAvailabilityTarget'
    | 'operationsDynamicP95TargetMs'
    | 'operationsStagingResidualAuditEnabled'
    | 'operationsCostScopeTagKey'
    | 'operationsCostScopeTagValue'
    | 'applicationMetricEmissionIntegrated'
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
    throw new Error(`MXMED_OPERATIONS_CONFIG_INVALID:${field}`);
  }
  return selected;
}

export function resolveOperationsContext(
  environmentName: MxMedEnvironmentName,
  deploymentProfile: MxMedDeploymentProfile,
  values: MxMedOperationsContextValues = {},
): MxMedResolvedOperationsContext {
  const standard = deploymentProfile !== 'launch-lean-v1';
  return Object.freeze({
    operationsActivationMode: select(
      'operationsActivationMode',
      values.operationsActivationMode,
      MXMED_OPERATIONS_ACTIVATION_MODES,
      'disabled-v1',
    ),
    operationsNotificationMode: select(
      'operationsNotificationMode',
      values.operationsNotificationMode,
      MXMED_OPERATIONS_NOTIFICATION_MODES,
      'none-v1',
    ),
    operationsLogProtectionProfile: select(
      'operationsLogProtectionProfile',
      values.operationsLogProtectionProfile,
      MXMED_OPERATIONS_LOG_PROTECTION_PROFILES,
      MXMED_REAL_OPERATIONS_GATES.operationsLogProtectionProfile,
    ),
    operationsRuntimeGateState: select(
      'operationsRuntimeGateState',
      values.operationsRuntimeGateState,
      MXMED_OPERATIONS_RUNTIME_GATE_STATES,
      MXMED_REAL_OPERATIONS_GATES.operationsRuntimeGateState,
    ),
    clinicalLogSanitizationState: select(
      'clinicalLogSanitizationState',
      values.clinicalLogSanitizationState,
      MXMED_CLINICAL_LOG_SANITIZATION_STATES,
      MXMED_REAL_OPERATIONS_GATES.clinicalLogSanitizationState,
    ),
    operationsCostAllocationTagState: select(
      'costAllocationTagState',
      values.costAllocationTagState,
      MXMED_COST_ALLOCATION_TAG_STATES,
      'inactive-v1',
    ),
    costAnomalyMonitorOwnershipMode: select(
      'costAnomalyMonitorOwnershipMode',
      values.costAnomalyMonitorOwnershipMode,
      MXMED_COST_ANOMALY_MONITOR_OWNERSHIP_MODES,
      'create-service-monitor-v1',
    ),
    operationsCostTagAnomalyMonitorMode: select(
      'costTagAnomalyMonitorMode',
      values.costTagAnomalyMonitorMode,
      MXMED_COST_TAG_ANOMALY_MONITOR_MODES,
      'disabled-until-tags-active-v1',
    ),
    operationsIncidentPolicyVersion: 'mxmed-incident-policy-v1',
    operationsRunbookVersion: 'mxmed-operations-runbooks-v1',
    operationsDashboardProfile: 'minimal-profile-aware-v1',
    operationsAlarmProfile: 'profile-aware-v1',
    operationsAutomaticRemediationEnabled: false,
    operationsEcsCpuWarningPercent: MXMED_OPERATIONS_THRESHOLDS.ecsCpuWarningPercent,
    operationsEcsMemoryWarningPercent: MXMED_OPERATIONS_THRESHOLDS.ecsMemoryWarningPercent,
    operationsRdsCpuWarningPercent: MXMED_OPERATIONS_THRESHOLDS.rdsCpuWarningPercent,
    operationsRdsFreeStoragePercent: MXMED_OPERATIONS_THRESHOLDS.rdsFreeStoragePercent,
    operationsRdsConnectionBudgetPercent: MXMED_OPERATIONS_THRESHOLDS.rdsConnectionBudgetPercent,
    operationsValkeyMemoryWarningPercent: MXMED_OPERATIONS_THRESHOLDS.valkeyMemoryWarningPercent,
    operationsAlbTarget5xxRatePercent: MXMED_OPERATIONS_THRESHOLDS.albTarget5xxRatePercent,
    operationsCloudFront5xxRatePercent: MXMED_OPERATIONS_THRESHOLDS.cloudFront5xxRatePercent,
    operationsInternalAvailabilityTarget: standard
      ? MXMED_OPERATIONS_THRESHOLDS.standardAvailabilityTarget
      : MXMED_OPERATIONS_THRESHOLDS.launchAvailabilityTarget,
    operationsDynamicP95TargetMs: standard
      ? MXMED_OPERATIONS_THRESHOLDS.standardDynamicP95TargetMs
      : MXMED_OPERATIONS_THRESHOLDS.launchDynamicP95TargetMs,
    operationsStagingResidualAuditEnabled: environmentName === 'staging',
    operationsCostScopeTagKey: 'CostScope',
    operationsCostScopeTagValue:
      environmentName === 'staging' ? 'mxmed-staging' : 'mxmed-production',
    applicationMetricEmissionIntegrated:
      MXMED_REAL_OPERATIONS_GATES.applicationMetricEmissionIntegrated,
  });
}

export function operationsCreatesCost(config: MxMedEnvironmentConfig): boolean {
  return config.operationsActivationMode !== 'disabled-v1';
}

export function operationsCreatesObservability(config: MxMedEnvironmentConfig): boolean {
  return (
    config.operationsActivationMode === 'launch-lean-observability-ready-v1' ||
    config.operationsActivationMode === 'production-observability-ready-v1'
  );
}

export function operationsCreatesGlobalObservability(config: MxMedEnvironmentConfig): boolean {
  return operationsCreatesObservability(config) && config.edgeActivationMode !== 'disabled-v1';
}

export function validateOperationsConfig(config: MxMedEnvironmentConfig): void {
  const activation = config.operationsActivationMode;
  const notification = config.operationsNotificationMode;
  if (activation === 'disabled-v1' && notification !== 'none-v1') {
    throw new Error('MXMED_OPERATIONS_DISABLED_REQUIRES_NOTIFICATION_NONE');
  }
  if (activation !== 'disabled-v1' && notification === 'none-v1') {
    throw new Error('MXMED_OPERATIONS_ACTIVE_REQUIRES_NOTIFICATION_TOPICS');
  }
  if (
    activation === 'launch-lean-observability-ready-v1' &&
    config.deploymentProfile !== 'launch-lean-v1'
  ) {
    throw new Error('MXMED_OPERATIONS_LAUNCH_REQUIRES_LAUNCH_PROFILE');
  }
  if (
    (activation === 'launch-lean-observability-ready-v1' ||
      activation === 'production-observability-ready-v1') &&
    config.computeActivationMode !== 'service-enabled-v1'
  ) {
    throw new Error('MXMED_OPERATIONS_OBSERVABILITY_REQUIRES_COMPUTE_SERVICE');
  }
  if (
    activation === 'cost-controls-ready-v1' &&
    (config.computeActivationMode !== 'disabled-v1' || config.edgeActivationMode !== 'disabled-v1')
  ) {
    throw new Error('MXMED_OPERATIONS_COST_ONLY_REQUIRES_WORKLOADS_DISABLED');
  }
  if (
    activation === 'production-observability-ready-v1' &&
    config.deploymentProfile === 'launch-lean-v1'
  ) {
    throw new Error('MXMED_OPERATIONS_PRODUCTION_REQUIRES_STANDARD_PROFILE');
  }
  const clinicalRuntime =
    config.runtimeCapabilityProfile === 'clinical-v1' ||
    config.runtimeCapabilityProfile === 'professional-ai-v1';
  if (
    activation === 'production-observability-ready-v1' &&
    clinicalRuntime &&
    config.clinicalLogSanitizationState !== 'source-sanitization-verified-v1'
  ) {
    throw new Error('clinical_observability_blocked_by_legacy_agenda_logs');
  }
  if (
    config.operationsLogProtectionProfile === 'targeted-data-protection-v1' &&
    (activation !== 'production-observability-ready-v1' ||
      !clinicalRuntime ||
      config.clinicalLogSanitizationState !== 'source-sanitization-verified-v1')
  ) {
    throw new Error('MXMED_OPERATIONS_TARGETED_LOG_PROTECTION_GATE_CLOSED');
  }
  if (
    config.operationsCostTagAnomalyMonitorMode === 'enabled-v1' &&
    config.operationsCostAllocationTagState !== 'active-and-verified-v1'
  ) {
    throw new Error('MXMED_OPERATIONS_COST_TAG_MONITOR_GATE_CLOSED');
  }
}

export type {
  MxMedClinicalLogSanitizationState,
  MxMedCostAllocationTagState,
  MxMedCostAnomalyMonitorOwnershipMode,
  MxMedCostTagAnomalyMonitorMode,
  MxMedOperationsActivationMode,
  MxMedOperationsLogProtectionProfile,
  MxMedOperationsNotificationMode,
  MxMedOperationsRuntimeGateState,
};
