export const MXMED_REGIONAL_DASHBOARD_CONTRACT = Object.freeze({
  name: 'MxMedRegionalOperationsDashboard',
  maximumWidgets: 8,
  minimumPeriodSeconds: 60,
  widgets: [
    'ecs-desired-running',
    'ecs-cpu-memory',
    'alb-health',
    'alb-requests-errors-latency',
    'rds-cpu-connections',
    'rds-storage-memory',
    'valkey-memory-evictions-connections',
    'alarm-status',
  ],
  logWidgets: false,
} as const);

export const MXMED_GLOBAL_DASHBOARD_CONTRACT = Object.freeze({
  name: 'MxMedGlobalEdgeDashboard',
  region: 'us-east-1',
  maximumWidgets: 5,
  minimumPeriodSeconds: 60,
  widgets: [
    'cloudfront-requests-bytes',
    'cloudfront-errors',
    'waf-allowed-blocked',
    'waf-rate-rules',
    'global-alarm-status',
  ],
  logWidgets: false,
  paidMetrics: false,
} as const);
