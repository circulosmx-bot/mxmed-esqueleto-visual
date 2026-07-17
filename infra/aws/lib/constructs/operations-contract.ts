import type { MxMedDeploymentProfile } from '../config/environment-config';

export const MXMED_INCIDENT_SEVERITY_CONTRACT = Object.freeze({
  customerSla: false,
  SEV1: { acknowledgmentTargetMinutes: 15, initialTriageTargetMinutes: 30 },
  SEV2: { acknowledgmentTargetMinutes: 30, initialPlanTargetMinutes: 60 },
  SEV3: { reviewTarget: 'same-business-day' },
  SEV4: { informational: true },
} as const);

export const MXMED_SLO_ERROR_BUDGET_CONTRACT = Object.freeze({
  customerSla: false,
  launchLean: {
    monthlyAvailabilityObjective: 99.5,
    dynamicErrorRateObjectivePercent: 1,
    dynamicP95TargetMs: 2000,
    readinessSuccessObjectivePercent: 99,
  },
  productionStandard: {
    monthlyAvailabilityObjective: 99.9,
    dynamicErrorRateObjectivePercent: 0.5,
    dynamicP95TargetMs: 1500,
  },
  errorBudgetActions: {
    halfBeforeMidMonth: 'freeze-nonessential-changes',
    fullyConsumed: 'reliability-first-no-new-capabilities',
  },
} as const);

export const MXMED_STAGING_RELEASE_WINDOW_CONTRACT = Object.freeze({
  before: [
    'budget-approved',
    'synthetic-data',
    'owner-reference',
    'start-end-utc',
    'profile-checked',
    'secrets-checked',
    'rollback-checked',
  ],
  after: [
    'fargate-zero-or-stack-removed',
    'nat-zero',
    'valkey-zero',
    'alb-zero',
    'cloudfront-disabled-or-removed',
    'rds-runbook',
    'logs-retained',
    'retained-resources-inventoried',
  ],
  scheduler: false,
  automaticShutdown: false,
} as const);

export interface StagingResidualCostAudit {
  readonly windowId: string;
  readonly endedAt: string;
  readonly fargateResidual: boolean;
  readonly natResidual: boolean;
  readonly valkeyResidual: boolean;
  readonly albResidual: boolean;
  readonly interfaceEndpointResidual: boolean;
  readonly rdsState: 'stopped' | 'snapshot-recreate-reviewed' | 'not-created';
  readonly snapshotStorageReviewed: boolean;
  readonly retainedResourcesReviewed: boolean;
  readonly evidenceReference: string;
}

export interface MxMedLaunchToStandardPromotionEvidence {
  readonly sevenDayEvidence: boolean;
  readonly criticalIncidentOverride: boolean;
  readonly budgetApproved: boolean;
  readonly manualPullRequest: boolean;
}

export function canPromoteLaunchToStandard(
  evidence: MxMedLaunchToStandardPromotionEvidence,
): boolean {
  return (
    (evidence.sevenDayEvidence || evidence.criticalIncidentOverride) &&
    evidence.budgetApproved &&
    evidence.manualPullRequest
  );
}

export const MXMED_PROMOTION_SIGNAL_CONTRACT = Object.freeze({
  launchToStandard: [
    'ecs-cpu-p95-over-60',
    'ecs-memory-p95-over-70',
    'second-task-frequently-active',
    'rds-cpu-p95-over-60',
    'rds-connections-over-70',
    'rds-free-memory-under-20',
    'rds-storage-over-70',
    'valkey-eviction',
    'valkey-memory-over-70',
    'business-sla',
    'public-onboarding',
    'standard-budget-approved',
  ],
  standardToScale: [
    'task-max-sustained',
    'rds-proxy-by-connection-churn',
    'read-replica-by-read-load',
    'valkey-size-by-memory-evictions',
    'endpoints-by-break-even',
    'cross-region-by-rpo-rto',
  ],
  automaticPromotion: false,
} as const);

export function sloProfile(profile: MxMedDeploymentProfile): 'launchLean' | 'productionStandard' {
  return profile === 'launch-lean-v1' ? 'launchLean' : 'productionStandard';
}
