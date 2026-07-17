import {
  MXMED_INCIDENT_SEVERITY_CONTRACT,
  MXMED_PROMOTION_SIGNAL_CONTRACT,
  MXMED_SLO_ERROR_BUDGET_CONTRACT,
  MXMED_STAGING_RELEASE_WINDOW_CONTRACT,
  canPromoteLaunchToStandard,
  sloProfile,
} from '../lib/constructs/operations-contract';

describe('Operations policy, SLO, promotion and staging contracts', () => {
  test.each([
    ['SEV1', 'acknowledgmentTargetMinutes', 15],
    ['SEV2', 'acknowledgmentTargetMinutes', 30],
    ['SEV3', 'reviewTarget', 'same-business-day'],
    ['SEV4', 'informational', true],
  ] as const)('defines the %s policy', (severity, field, value) => {
    expect(MXMED_INCIDENT_SEVERITY_CONTRACT[severity]).toMatchObject({ [field]: value });
  });

  test('defines launch availability at 99.5 percent', () => {
    expect(MXMED_SLO_ERROR_BUDGET_CONTRACT.launchLean.monthlyAvailabilityObjective).toBe(99.5);
  });

  test('defines standard availability at 99.9 percent', () => {
    expect(MXMED_SLO_ERROR_BUDGET_CONTRACT.productionStandard.monthlyAvailabilityObjective).toBe(
      99.9,
    );
  });

  test('defines launch dynamic p95 at 2000 ms', () => {
    expect(MXMED_SLO_ERROR_BUDGET_CONTRACT.launchLean.dynamicP95TargetMs).toBe(2000);
  });

  test('defines standard dynamic p95 at 1500 ms', () => {
    expect(MXMED_SLO_ERROR_BUDGET_CONTRACT.productionStandard.dynamicP95TargetMs).toBe(1500);
  });

  test('freezes nonessential changes at half error budget before mid-month', () => {
    expect(MXMED_SLO_ERROR_BUDGET_CONTRACT.errorBudgetActions.halfBeforeMidMonth).toBe(
      'freeze-nonessential-changes',
    );
  });

  test('prioritizes reliability at full error budget consumption', () => {
    expect(MXMED_SLO_ERROR_BUDGET_CONTRACT.errorBudgetActions.fullyConsumed).toBe(
      'reliability-first-no-new-capabilities',
    );
  });

  test('rejects promotion without seven-day evidence or override', () => {
    expect(
      canPromoteLaunchToStandard({
        sevenDayEvidence: false,
        criticalIncidentOverride: false,
        budgetApproved: true,
        manualPullRequest: true,
      }),
    ).toBe(false);
  });

  test('allows a manual, budgeted promotion with seven-day evidence', () => {
    expect(
      canPromoteLaunchToStandard({
        sevenDayEvidence: true,
        criticalIncidentOverride: false,
        budgetApproved: true,
        manualPullRequest: true,
      }),
    ).toBe(true);
  });

  test('allows a manual, budgeted critical-incident override', () => {
    expect(
      canPromoteLaunchToStandard({
        sevenDayEvidence: false,
        criticalIncidentOverride: true,
        budgetApproved: true,
        manualPullRequest: true,
      }),
    ).toBe(true);
  });

  test('forbids automatic profile promotion', () => {
    expect(MXMED_PROMOTION_SIGNAL_CONTRACT.automaticPromotion).toBe(false);
  });

  test('maps launch to the launch SLO profile', () => {
    expect(sloProfile('launch-lean-v1')).toBe('launchLean');
  });

  test('maps standard to the production standard SLO profile', () => {
    expect(sloProfile('production-standard-v1')).toBe('productionStandard');
  });

  test('requires a bounded staging pre-window checklist', () => {
    expect(MXMED_STAGING_RELEASE_WINDOW_CONTRACT.before).toHaveLength(7);
  });

  test('requires an eight-item residual review after staging', () => {
    expect(MXMED_STAGING_RELEASE_WINDOW_CONTRACT.after).toHaveLength(8);
  });

  test('does not create a staging scheduler', () => {
    expect(MXMED_STAGING_RELEASE_WINDOW_CONTRACT.scheduler).toBe(false);
  });

  test('does not automate staging shutdown', () => {
    expect(MXMED_STAGING_RELEASE_WINDOW_CONTRACT.automaticShutdown).toBe(false);
  });
});
