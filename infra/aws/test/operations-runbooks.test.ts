import {
  MXMED_OPERATIONS_RUNBOOK_CATALOG,
  operationsRunbook,
} from '../lib/constructs/operations-runbook-catalog';

const expectedIds = [
  'public-site-unavailable',
  'ecs-task-deficit',
  'alb-unhealthy-targets',
  'cloudfront-error-spike',
  'waf-rate-spike',
  'rds-high-cpu',
  'rds-low-storage',
  'rds-connections',
  'valkey-evictions',
  'valkey-unavailable',
  'stripe-webhook-failure',
  'subscription-activation-mismatch',
  'notification-delivery-failure',
  'clinical-upload-failure',
  'secure-link-abuse',
  'cost-budget-exceeded',
  'cost-anomaly-detected',
  'staging-left-running',
  'secret-configuration-missing',
  'rollback-after-edge-cutover',
] as const;

describe('Operations runbook catalog', () => {
  test('contains the exact twenty contracted runbooks', () => {
    expect(MXMED_OPERATIONS_RUNBOOK_CATALOG.map(({ id }) => id)).toEqual(expectedIds);
  });

  test.each(expectedIds)('%s has the complete safe typed contract', (id) => {
    const entry = operationsRunbook(id);
    expect(entry.severity).toMatch(/^SEV[1-4]$/);
    expect(entry.trigger.length).toBeGreaterThan(3);
    expect(entry.firstChecks.length).toBeGreaterThan(0);
    expect(entry.safeDiagnostics.length).toBeGreaterThan(0);
    expect(entry.prohibitedActions.length).toBeGreaterThan(0);
    expect(entry.escalation.length).toBeGreaterThan(3);
    expect(entry.rollback.length).toBeGreaterThan(3);
    expect(entry.evidence.length).toBeGreaterThan(0);
    expect(entry.closureCriteria.length).toBeGreaterThan(0);
  });

  test('keeps every runbook ID unique', () => {
    const ids = MXMED_OPERATIONS_RUNBOOK_CATALOG.map(({ id }) => id);
    expect(new Set(ids).size).toBe(ids.length);
  });

  test('includes no destructive command, secret, personal address or inline SQL', () => {
    const text = JSON.stringify(MXMED_OPERATIONS_RUNBOOK_CATALOG);
    expect(text).not.toMatch(/rm -|delete-stack|drop table|truncate table|@[A-Za-z]|\+\d{7}/i);
    expect(text).not.toMatch(/secret(?:=|:)|password(?:=|:)|select .* from/i);
  });

  test('fails closed for an unknown runbook', () => {
    expect(() => operationsRunbook('unknown')).toThrow('MXMED_OPERATIONS_RUNBOOK_UNKNOWN');
  });
});
