import type { RenderedTemplate } from './operations-test-helpers';
import {
  costOnlyConfig,
  renderGlobal,
  resourcesOfType,
  serialized,
} from './operations-test-helpers';

let template: RenderedTemplate;
let budgets: readonly Record<string, unknown>[];

beforeAll(() => {
  template = renderGlobal(costOnlyConfig()).cost;
  budgets = resourcesOfType(template, 'AWS::Budgets::Budget');
});

describe('Operations budgets', () => {
  test('creates exactly two budgets', () => {
    expect(budgets).toHaveLength(2);
  });

  test('uses deterministic production and staging budget names', () => {
    expect(serialized(budgets)).toContain('mxmed-production-monthly-cost');
    expect(serialized(budgets)).toContain('mxmed-staging-monthly-cost');
  });

  test('filters production by its CostScope tag', () => {
    expect(serialized(budgets)).toContain('user:CostScope$mxmed-production');
  });

  test('filters staging by its CostScope tag', () => {
    expect(serialized(budgets)).toContain('user:CostScope$mxmed-staging');
  });

  test('uses monthly periods', () => {
    expect(budgets.every((budget) => serialized(budget).includes('MONTHLY'))).toBe(true);
  });

  test('uses USD limits from parameters', () => {
    expect(budgets.every((budget) => serialized(budget).includes('USD'))).toBe(true);
    expect(serialized(budgets)).toContain('ApprovedMonthlyBudgetUsd');
    expect(serialized(budgets)).toContain('StagingMonthlyBudgetUsd');
  });

  test('excludes taxes from both budgets', () => {
    expect(budgets.every((budget) => serialized(budget).includes('"IncludeTax":false'))).toBe(true);
  });

  test('includes support costs in both budgets', () => {
    expect(budgets.every((budget) => serialized(budget).includes('"IncludeSupport":true'))).toBe(
      true,
    );
  });

  test.each([
    [50, 'ACTUAL'],
    [75, 'ACTUAL'],
    [90, 'FORECASTED'],
    [100, 'ACTUAL'],
    [120, 'ACTUAL'],
  ] as const)('defines threshold %s as %s', (threshold, type) => {
    for (const budget of budgets) {
      const notifications = (budget.NotificationsWithSubscribers ?? []) as readonly unknown[];
      expect(
        notifications.some((notification) => {
          const text = serialized(notification);
          return (
            text.includes(`"Threshold":${String(threshold)}`) &&
            text.includes(`"NotificationType":"${type}"`)
          );
        }),
      ).toBe(true);
    }
  });

  test('uses SNS as the only budget subscriber type', () => {
    const text = serialized(budgets);
    expect(text).toContain('SNS');
    expect(text).not.toContain('EMAIL');
  });

  test('persists no email address in budget templates', () => {
    expect(serialized(budgets)).not.toMatch(/@|mailto:/i);
  });

  test('creates no Budget Actions', () => {
    expect(resourcesOfType(template, 'AWS::Budgets::BudgetsAction')).toHaveLength(0);
  });
});
