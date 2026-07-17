import type { MxMedIncidentSeverity } from './operations-alarm-catalog';

export interface MxMedOperationsRunbook {
  readonly id: string;
  readonly severity: MxMedIncidentSeverity;
  readonly trigger: string;
  readonly firstChecks: readonly string[];
  readonly safeDiagnostics: readonly string[];
  readonly prohibitedActions: readonly string[];
  readonly escalation: string;
  readonly rollback: string;
  readonly evidence: readonly string[];
  readonly closureCriteria: readonly string[];
}

const commonProhibited = Object.freeze([
  'automatic-production-shutdown',
  'force-delete',
  'secret-or-personal-data-disclosure',
  'inline-sql',
] as const);

function runbook(
  id: string,
  severity: MxMedIncidentSeverity,
  trigger: string,
  firstChecks: readonly string[],
  escalation: string,
  rollback: string,
): MxMedOperationsRunbook {
  return Object.freeze({
    id,
    severity,
    trigger,
    firstChecks,
    safeDiagnostics: ['aggregate-native-metrics', 'sanitized-events', 'approved-change-reference'],
    prohibitedActions: commonProhibited,
    escalation,
    rollback,
    evidence: ['sanitized-timeline', 'aggregate-metrics', 'approved-change-reference'],
    closureCriteria: ['signal-stable', 'root-cause-recorded', 'follow-up-owned'],
  });
}

export const MXMED_OPERATIONS_RUNBOOK_CATALOG = Object.freeze([
  runbook(
    'public-site-unavailable',
    'SEV1',
    'public availability or sustained 5xx',
    ['cloudfront', 'alb', 'ecs', 'readiness'],
    'incident-commander-security-edge',
    'last-approved-cutover',
  ),
  runbook(
    'ecs-task-deficit',
    'SEV2',
    'running tasks below desired',
    ['ecs-events', 'stopped-reason', 'cpu-memory'],
    'compute-owner',
    'previous-task-definition',
  ),
  runbook(
    'alb-unhealthy-targets',
    'SEV2',
    'unhealthy target or target errors',
    ['target-health', 'security-groups', 'readiness'],
    'edge-compute-owner',
    'previous-release',
  ),
  runbook(
    'cloudfront-error-spike',
    'SEV2',
    'global error rate and request gate',
    ['viewer-origin-split', 'behaviors', 'regional-origin'],
    'global-edge-owner',
    'previous-distribution-change',
  ),
  runbook(
    'waf-rate-spike',
    'SEV3',
    'aggregate blocked request spike',
    ['rule-metric', 'recent-change', 'false-positive'],
    'security-edge-owner',
    'approved-rule-change',
  ),
  runbook(
    'rds-high-cpu',
    'SEV3',
    'RDS CPU or latency saturation',
    ['cpu', 'connections', 'latencies', 'queue'],
    'data-owner',
    'previous-release-or-approved-query-change',
  ),
  runbook(
    'rds-low-storage',
    'SEV2',
    'RDS free storage gate',
    ['free-storage', 'allocated-storage', 'growth'],
    'data-security-owner',
    'approved-capacity-change',
  ),
  runbook(
    'rds-connections',
    'SEV3',
    'RDS connection budget gate',
    ['connections', 'task-count', 'connection-churn'],
    'data-compute-owner',
    'previous-pool-or-release',
  ),
  runbook(
    'valkey-evictions',
    'SEV2',
    'any eviction',
    ['memory', 'cpu', 'connections'],
    'session-owner',
    'approved-release-or-capacity-change',
  ),
  runbook(
    'valkey-unavailable',
    'SEV2',
    'Valkey availability or lag',
    ['node-state', 'security-groups', 'sanitized-app-errors'],
    'session-compute-owner',
    'previous-release-or-config',
  ),
  runbook(
    'stripe-webhook-failure',
    'SEV2',
    'sanitized webhook failures',
    ['sanitized-code', 'capability', 'aggregate-provider-status'],
    'payments-security-owner',
    'previous-release-or-config',
  ),
  runbook(
    'subscription-activation-mismatch',
    'SEV1',
    'payment and activation mismatch',
    ['authoritative-source', 'contract-state'],
    'payments-product-security',
    'approved-compensation',
  ),
  runbook(
    'notification-delivery-failure',
    'SEV2',
    'critical delivery failure',
    ['component', 'result', 'aggregate-provider-status'],
    'clinical-owner',
    'approved-alternate-channel',
  ),
  runbook(
    'clinical-upload-failure',
    'SEV1',
    'critical upload failure',
    ['technical-status', 'kms', 's3', 'scanner'],
    'clinical-security-owner',
    'previous-release-or-policy',
  ),
  runbook(
    'secure-link-abuse',
    'SEV1',
    'aggregate secure-link abuse',
    ['rate', 'status', 'component'],
    'security-clinical-owner',
    'approved-revocation-control',
  ),
  runbook(
    'cost-budget-exceeded',
    'SEV3',
    'budget threshold',
    ['budget', 'service', 'region', 'tags', 'profile'],
    'finops-stack-owner',
    'approved-capacity-or-change',
  ),
  runbook(
    'cost-anomaly-detected',
    'SEV3',
    'cost anomaly subscription',
    ['history', 'service', 'region', 'tags', 'staging'],
    'finops-stack-owner',
    'causing-change',
  ),
  runbook(
    'staging-left-running',
    'SEV3',
    'spend outside release window',
    ['window', 'environment-tag', 'residual-audit'],
    'staging-owner',
    'approved-teardown-runbook',
  ),
  runbook(
    'secret-configuration-missing',
    'SEV2',
    'critical configuration gate absent',
    ['gate-name', 'source', 'rollout'],
    'security-service-owner',
    'previous-release-or-config',
  ),
  runbook(
    'rollback-after-edge-cutover',
    'SEV1',
    'degraded Edge cutover',
    ['cloudfront', 'waf', 'alb', 'readiness'],
    'incident-commander-edge',
    'approved-edge-rollback',
  ),
] as const satisfies readonly MxMedOperationsRunbook[]);

export function operationsRunbook(id: string): MxMedOperationsRunbook {
  const found = MXMED_OPERATIONS_RUNBOOK_CATALOG.find((entry) => entry.id === id);
  if (found === undefined) throw new Error(`MXMED_OPERATIONS_RUNBOOK_UNKNOWN:${id}`);
  return found;
}
