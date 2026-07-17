import type { MxMedEnvironmentConfig } from '../config/environment-config';

export type MxMedIncidentSeverity = 'SEV1' | 'SEV2' | 'SEV3' | 'SEV4';

export interface MxMedAlarmDefinition {
  readonly id: string;
  readonly severity: MxMedIncidentSeverity;
  readonly runbookId: string;
  readonly sanitizedCode: string;
}

export const MXMED_LAUNCH_LEAN_ALARM_CATALOG = Object.freeze([
  {
    id: 'ecs-task-deficit',
    severity: 'SEV2',
    runbookId: 'ecs-task-deficit',
    sanitizedCode: 'ecs_task_deficit',
  },
  {
    id: 'ecs-high-cpu',
    severity: 'SEV3',
    runbookId: 'ecs-task-deficit',
    sanitizedCode: 'ecs_high_cpu',
  },
  {
    id: 'ecs-high-memory',
    severity: 'SEV3',
    runbookId: 'ecs-task-deficit',
    sanitizedCode: 'ecs_high_memory',
  },
  {
    id: 'alb-unhealthy-target',
    severity: 'SEV2',
    runbookId: 'alb-unhealthy-targets',
    sanitizedCode: 'alb_unhealthy_target',
  },
  {
    id: 'alb-target-5xx-rate',
    severity: 'SEV2',
    runbookId: 'alb-unhealthy-targets',
    sanitizedCode: 'alb_target_5xx_rate',
  },
  {
    id: 'rds-high-cpu',
    severity: 'SEV3',
    runbookId: 'rds-high-cpu',
    sanitizedCode: 'rds_high_cpu',
  },
  {
    id: 'rds-low-free-storage',
    severity: 'SEV2',
    runbookId: 'rds-low-storage',
    sanitizedCode: 'rds_low_free_storage',
  },
  {
    id: 'rds-connection-budget',
    severity: 'SEV3',
    runbookId: 'rds-connections',
    sanitizedCode: 'rds_connection_budget',
  },
  {
    id: 'valkey-evictions',
    severity: 'SEV2',
    runbookId: 'valkey-evictions',
    sanitizedCode: 'valkey_evictions',
  },
  {
    id: 'valkey-memory-pressure',
    severity: 'SEV3',
    runbookId: 'valkey-unavailable',
    sanitizedCode: 'valkey_memory_pressure',
  },
  {
    id: 'cloudfront-5xx-rate',
    severity: 'SEV2',
    runbookId: 'cloudfront-error-spike',
    sanitizedCode: 'cloudfront_5xx_rate',
  },
] as const satisfies readonly MxMedAlarmDefinition[]);

export const MXMED_PRODUCTION_STANDARD_ADDITIONAL_ALARM_CATALOG = Object.freeze([
  {
    id: 'rds-low-free-memory',
    severity: 'SEV2',
    runbookId: 'rds-high-cpu',
    sanitizedCode: 'rds_low_free_memory',
  },
  {
    id: 'rds-read-latency',
    severity: 'SEV3',
    runbookId: 'rds-high-cpu',
    sanitizedCode: 'rds_read_latency',
  },
  {
    id: 'rds-write-latency',
    severity: 'SEV3',
    runbookId: 'rds-high-cpu',
    sanitizedCode: 'rds_write_latency',
  },
  {
    id: 'rds-disk-queue',
    severity: 'SEV3',
    runbookId: 'rds-high-cpu',
    sanitizedCode: 'rds_disk_queue',
  },
  {
    id: 'rds-storage-warning',
    severity: 'SEV3',
    runbookId: 'rds-low-storage',
    sanitizedCode: 'rds_storage_warning',
  },
  {
    id: 'rds-storage-critical',
    severity: 'SEV2',
    runbookId: 'rds-low-storage',
    sanitizedCode: 'rds_storage_critical',
  },
  {
    id: 'valkey-high-cpu',
    severity: 'SEV3',
    runbookId: 'valkey-unavailable',
    sanitizedCode: 'valkey_high_cpu',
  },
  {
    id: 'valkey-connections',
    severity: 'SEV3',
    runbookId: 'valkey-unavailable',
    sanitizedCode: 'valkey_connections',
  },
  {
    id: 'valkey-replication-lag',
    severity: 'SEV2',
    runbookId: 'valkey-unavailable',
    sanitizedCode: 'valkey_replication_lag',
  },
  {
    id: 'alb-target-response-p95',
    severity: 'SEV3',
    runbookId: 'alb-unhealthy-targets',
    sanitizedCode: 'alb_target_response_p95',
  },
  {
    id: 'cloudfront-total-error-rate',
    severity: 'SEV2',
    runbookId: 'cloudfront-error-spike',
    sanitizedCode: 'cloudfront_total_error_rate',
  },
  {
    id: 'waf-sensitive-rate-spike',
    severity: 'SEV3',
    runbookId: 'waf-rate-spike',
    sanitizedCode: 'waf_sensitive_rate_spike',
  },
  {
    id: 'waf-general-rate-spike',
    severity: 'SEV3',
    runbookId: 'waf-rate-spike',
    sanitizedCode: 'waf_general_rate_spike',
  },
] as const satisfies readonly MxMedAlarmDefinition[]);

export const MXMED_RDS_INSTANCE_MEMORY_GIB = Object.freeze({
  'db.t4g.medium': 4,
  'db.m6g.large': 8,
} satisfies Readonly<Record<MxMedEnvironmentConfig['databaseInstanceClass'], number>>);

export const MXMED_VALKEY_CONNECTION_WARNING = Object.freeze({
  'cache.t4g.micro': 500,
  'cache.t4g.medium': 2000,
} satisfies Readonly<Record<MxMedEnvironmentConfig['sessionNodeType'], number>>);

export function deriveRdsConnectionBudget(computeMaxCapacity: number): {
  readonly totalConnectionBudget: number;
  readonly alarmThreshold: number;
} {
  if (!Number.isInteger(computeMaxCapacity) || computeMaxCapacity < 1) {
    throw new Error('MXMED_OPERATIONS_CONNECTION_BUDGET_INVALID');
  }
  const totalConnectionBudget = Math.ceil((computeMaxCapacity * 20) / 0.75);
  return Object.freeze({
    totalConnectionBudget,
    alarmThreshold: Math.ceil(totalConnectionBudget * 0.7),
  });
}

export function storageThresholdBytes(allocatedStorageGiB: number, freePercent: number): number {
  if (allocatedStorageGiB <= 0 || freePercent <= 0 || freePercent > 100) {
    throw new Error('MXMED_OPERATIONS_STORAGE_THRESHOLD_INVALID');
  }
  return allocatedStorageGiB * 1024 ** 3 * (freePercent / 100);
}
