import type {
  MxMedComputeAvailabilityProfile,
  MxMedComputeSizingProfile,
  MxMedDatabaseAvailabilityProfile,
  MxMedDeploymentProfile,
  MxMedEnvironmentName,
  MxMedInterfaceEndpointProfile,
  MxMedNatStrategy,
  MxMedSessionAvailabilityProfile,
  MxMedStagingOperatingMode,
} from './environment-config';

export const MXMED_COST_AWARE_LAUNCH_PROFILES_CONTRACT =
  'MXMED_AWS_COST_AWARE_LAUNCH_PROFILES_CONTRACT_V1' as const;
export const MXMED_COST_ESTIMATE_AS_OF = '2026-07-16' as const;

export const MXMED_DEPLOYMENT_PROFILES = [
  'launch-lean-v1',
  'production-standard-v1',
  'scale-ready-v1',
] as const satisfies readonly MxMedDeploymentProfile[];

export type MxMedCostNature = 'fixed-idle' | 'usage-based' | 'storage-based';
export type MxMedCapabilityState = 'enabled' | 'deferred';

export interface MxMedLaunchCapacity {
  readonly natStrategy: MxMedNatStrategy;
  readonly natGatewayCount: 1 | 2;
  readonly interfaceEndpointProfile: MxMedInterfaceEndpointProfile;
  readonly interfaceEndpointCount: 0 | 4;
  readonly computeSizingProfile: MxMedComputeSizingProfile;
  readonly computeAvailabilityProfile: MxMedComputeAvailabilityProfile;
  readonly computeDesiredCount: 1 | 2;
  readonly computeMinCapacity: 1 | 2;
  readonly computeMaxCapacity: 1 | 2 | 6;
  readonly computeTaskCpuUnits: 512 | 1024;
  readonly computeTaskMemoryMiB: 1024 | 2048;
  readonly computeArchitecture: 'X86_64';
  readonly computeUseSpot: false;
  readonly computeAssignPublicIp: false;
  readonly databaseAvailabilityProfile: MxMedDatabaseAvailabilityProfile;
  readonly databaseInstanceClass: 'db.t4g.medium' | 'db.m6g.large';
  readonly databaseMultiAz: boolean;
  readonly databaseAllocatedStorageGiB: 40 | 100;
  readonly databaseMaxAllocatedStorageGiB: 200 | 1000;
  readonly databaseProxyEnabled: false;
  readonly databaseReadReplicaCount: 0;
  readonly sessionAvailabilityProfile: MxMedSessionAvailabilityProfile;
  readonly sessionNodeType: 'cache.t4g.micro' | 'cache.t4g.medium';
  readonly sessionReplicaCount: 0 | 1;
  readonly sessionMultiAzEnabled: boolean;
  readonly sessionAutomaticFailoverEnabled: boolean;
  readonly enableCrossRegionReplication: false;
}

export interface MxMedProfileCapability {
  readonly id:
    | 'second-permanent-task'
    | 'second-nat-gateway'
    | 'interface-endpoints'
    | 'database-multi-az'
    | 'session-replica'
    | 'session-medium'
    | 'database-proxy'
    | 'database-read-replica'
    | 'cross-region-backup'
    | 'cross-region-storage-replication'
    | 'broad-data-events'
    | 'permanent-workers'
    | 'continuous-scanner';
  readonly state: MxMedCapabilityState;
  readonly reviewGate: string | null;
}

export interface MxMedCostLedgerDriver {
  readonly stack: 'Network' | 'Security' | 'Data' | 'Storage' | 'Session' | 'Compute' | 'Edge';
  readonly service: string;
  readonly resource: string;
  readonly profile: MxMedDeploymentProfile;
  readonly environment: MxMedEnvironmentName;
  readonly region: 'mx-central-1';
  readonly quantity: number | null;
  readonly unit: string;
  readonly hours: number | null;
  readonly rateUsd: null;
  readonly fixedMonthlyUsd: null;
  readonly estimatedVariableUsd: null;
  readonly estimatedStorageUsd: null;
  readonly estimatedTransferUsd: null;
  readonly quantityDriver: string;
  readonly formula: string;
  readonly costNature: readonly MxMedCostNature[];
  readonly capacityState: 'configured' | 'deferred' | 'future-microphase';
  readonly reviewRequired: boolean;
  readonly officialSource: string;
  readonly queriedAtUtc: null;
  readonly uncertainty: 'rates-and-usage-require-cost-readiness-review';
  readonly taxesIncluded: false;
  readonly fxIncluded: false;
}

export interface MxMedLaunchProfileDefinition {
  readonly name: MxMedDeploymentProfile;
  readonly purpose: string;
  readonly production: MxMedLaunchCapacity;
  readonly capabilities: readonly MxMedProfileCapability[];
}

export interface MxMedLaunchProfileResolution {
  readonly contract: typeof MXMED_COST_AWARE_LAUNCH_PROFILES_CONTRACT;
  readonly environment: MxMedEnvironmentName;
  readonly deploymentProfile: MxMedDeploymentProfile;
  readonly stagingOperatingMode: MxMedStagingOperatingMode | null;
  readonly capacity: MxMedLaunchCapacity;
  readonly capabilities: readonly MxMedProfileCapability[];
  readonly costLedger: readonly MxMedCostLedgerDriver[];
}

const alwaysDeferred = (id: MxMedProfileCapability['id'], reviewGate: string) =>
  ({
    id,
    state: 'deferred',
    reviewGate,
  }) as const;

const LAUNCH_LEAN_CAPABILITIES = Object.freeze([
  alwaysDeferred('second-permanent-task', 'launch-to-standard-review'),
  alwaysDeferred('second-nat-gateway', 'launch-to-standard-review'),
  alwaysDeferred('interface-endpoints', 'endpoint-break-even-and-resilience-review'),
  alwaysDeferred('database-multi-az', 'launch-to-standard-review'),
  alwaysDeferred('session-replica', 'launch-to-standard-review'),
  alwaysDeferred('session-medium', 'session-memory-and-performance-review'),
  alwaysDeferred('database-proxy', 'connection-churn-review'),
  alwaysDeferred('database-read-replica', 'read-load-review'),
  alwaysDeferred('cross-region-backup', 'backup-dr-readiness'),
  alwaysDeferred('cross-region-storage-replication', 'backup-dr-readiness'),
  alwaysDeferred('broad-data-events', 'selective-audit-review'),
  alwaysDeferred('permanent-workers', 'workload-evidence-review'),
  alwaysDeferred('continuous-scanner', 'workload-evidence-review'),
] satisfies readonly MxMedProfileCapability[]);

const STANDARD_CAPABILITIES = Object.freeze(
  LAUNCH_LEAN_CAPABILITIES.map((capability): MxMedProfileCapability =>
    new Set([
      'second-permanent-task',
      'second-nat-gateway',
      'database-multi-az',
      'session-replica',
    ]).has(capability.id)
      ? { ...capability, state: 'enabled', reviewGate: null }
      : capability,
  ),
);

const SCALE_CAPABILITIES = Object.freeze(
  STANDARD_CAPABILITIES.map((capability): MxMedProfileCapability =>
    capability.id === 'session-medium'
      ? { ...capability, state: 'enabled', reviewGate: null }
      : capability,
  ),
);

const PRODUCTION_LAUNCH_LEAN_CAPACITY: MxMedLaunchCapacity = Object.freeze({
  natStrategy: 'single-az',
  natGatewayCount: 1,
  interfaceEndpointProfile: 's3-only',
  interfaceEndpointCount: 0,
  computeSizingProfile: 'reduced',
  computeAvailabilityProfile: 'single-task',
  computeDesiredCount: 1,
  computeMinCapacity: 1,
  computeMaxCapacity: 2,
  computeTaskCpuUnits: 512,
  computeTaskMemoryMiB: 1024,
  computeArchitecture: 'X86_64',
  computeUseSpot: false,
  computeAssignPublicIp: false,
  databaseAvailabilityProfile: 'single-az',
  databaseInstanceClass: 'db.t4g.medium',
  databaseMultiAz: false,
  databaseAllocatedStorageGiB: 40,
  databaseMaxAllocatedStorageGiB: 200,
  databaseProxyEnabled: false,
  databaseReadReplicaCount: 0,
  sessionAvailabilityProfile: 'single-node',
  sessionNodeType: 'cache.t4g.micro',
  sessionReplicaCount: 0,
  sessionMultiAzEnabled: false,
  sessionAutomaticFailoverEnabled: false,
  enableCrossRegionReplication: false,
});

const PRODUCTION_STANDARD_CAPACITY: MxMedLaunchCapacity = Object.freeze({
  ...PRODUCTION_LAUNCH_LEAN_CAPACITY,
  natStrategy: 'dual-az',
  natGatewayCount: 2,
  computeSizingProfile: 'production-ha',
  computeAvailabilityProfile: 'ha-minimum',
  computeDesiredCount: 2,
  computeMinCapacity: 2,
  computeMaxCapacity: 6,
  computeTaskCpuUnits: 1024,
  computeTaskMemoryMiB: 2048,
  databaseAvailabilityProfile: 'multi-az',
  databaseInstanceClass: 'db.m6g.large',
  databaseMultiAz: true,
  databaseAllocatedStorageGiB: 100,
  databaseMaxAllocatedStorageGiB: 1000,
  sessionAvailabilityProfile: 'primary-replica',
  sessionReplicaCount: 1,
  sessionMultiAzEnabled: true,
  sessionAutomaticFailoverEnabled: true,
});

const SCALE_READY_CAPACITY: MxMedLaunchCapacity = Object.freeze({
  ...PRODUCTION_STANDARD_CAPACITY,
  interfaceEndpointProfile: 'measured',
  sessionNodeType: 'cache.t4g.medium',
});

const STAGING_RELEASE_WINDOW_CAPACITY: MxMedLaunchCapacity = Object.freeze({
  ...PRODUCTION_LAUNCH_LEAN_CAPACITY,
  computeMaxCapacity: 1,
});

const PROFILE_DEFINITIONS: Readonly<Record<MxMedDeploymentProfile, MxMedLaunchProfileDefinition>> =
  Object.freeze({
    'launch-lean-v1': {
      name: 'launch-lean-v1',
      purpose: 'Soft launch with contained capacity and complete security controls.',
      production: PRODUCTION_LAUNCH_LEAN_CAPACITY,
      capabilities: LAUNCH_LEAN_CAPABILITIES,
    },
    'production-standard-v1': {
      name: 'production-standard-v1',
      purpose: 'High-availability baseline after promotion gates and budget review.',
      production: PRODUCTION_STANDARD_CAPACITY,
      capabilities: STANDARD_CAPABILITIES,
    },
    'scale-ready-v1': {
      name: 'scale-ready-v1',
      purpose: 'Measured scaling baseline; advanced capabilities remain independently gated.',
      production: SCALE_READY_CAPACITY,
      capabilities: SCALE_CAPABILITIES,
    },
  });

export const MXMED_NON_NEGOTIABLE_CONTROLS = Object.freeze([
  'private-networking',
  'no-public-task-ip',
  'private-rds-and-valkey',
  's3-block-public-access',
  'tls',
  'kms',
  'secrets-manager',
  'least-privilege-iam',
  'cloudtrail-log-file-validation',
  's3-versioning',
  'rds-backup-pitr-protection-final-snapshot',
  'ecr-immutable-scan-digest',
  'no-secrets-or-clinical-data-in-logs',
  'waf-when-edge-is-deployed',
  'health-rollback-budgets-cost-alerts',
] as const);

export const MXMED_COST_GATES = Object.freeze({
  preGoLive: {
    id: 'pre-go-live-cost-gate-v1',
    requiredInputs: [
      'approvedMonthlyBudgetUsd',
      'planningFxMxnPerUsd',
      'planningFxAsOf',
      'anomalyAlertThresholdUsd',
      'maxInfrastructureCostToRevenuePercent',
      'budgetOwner',
      'alertRecipientsConfigured',
      'costReadinessReviewApproved',
      'deploymentProfile',
      'costEstimateAsOf',
      'costEstimateVersion',
    ],
  },
  costReadinessReview: {
    requiredBeforeFirstDeploy: true,
    requiredBeforeProfilePromotion: true,
    pricingMustBeRevalidated: true,
    taxesSupportFxDiscountsAndTrafficSeparate: true,
  },
  futureBillingControls: {
    resourcesDeferred: true,
    monthlyBudgetThresholds: [
      { percent: 50, basis: 'actual' },
      { percent: 75, basis: 'actual' },
      { percent: 90, basis: 'forecast' },
      { percent: 100, basis: 'actual' },
      { percent: 120, basis: 'actual-critical' },
    ],
    serviceBudgets: [
      'rds',
      'ecs-fargate',
      'vpc-nat-privatelink',
      'elasticache',
      'cloudwatch',
      's3-cloudfront',
    ],
    anomalyMonitors: ['account', 'principal-services', 'tag-environment', 'tag-project'],
    productionAutoStopForbidden: true,
    realRecipientsStoredInSource: false,
  },
  endpointPromotion: {
    requiresRegionalAvailability: true,
    requiresLedger: true,
    requiresBreakEvenOrResilienceEvidence: true,
    fixedEndpointFormula: 'azCount * endpointHourlyRate * 730',
    netSavingPerGbFormula: 'natProcessingRate + avoidedCrossAzRate - endpointDataRate',
    breakEvenGbFormula: 'fixedEndpoint / netSavingPerGb',
  },
  stagingReleaseWindow: {
    operatingMode: 'release-window-v1',
    stagingReleaseWindowHoursRequired: true,
    independentBudgetRequired: true,
    outsideWindowSpendAlertRequired: true,
    schedulerAndDestroyRunbookDeferred: true,
    retainedResidueReconciliationRequired: true,
  },
  standardSessionMicroSizing: {
    requiredBeforeDeploy: true,
    requiredSessionBytesFormula:
      'peakConcurrentSessions * (payloadP95Bytes + measuredValkeyOverheadBytes) * safetyFactor',
    acceptanceFormula: 'requiredSessionBytes <= 70% * measuredUsableMicroBytes',
    requiredMetrics: ['cpu', 'connections', 'latency', 'evictions'],
    fallbackWhenGateFails: 'cache.t4g.medium',
  },
  launchToStandard: {
    evidenceDays: 7,
    business: [
      'sla-committed',
      'public-uncontrolled-onboarding',
      'interruption-no-longer-acceptable',
      'standard-budget-approved',
      'cost-to-revenue-within-approved-limit',
    ],
    compute: ['cpu-p95>60%', 'memory-p95>70%', 'second-task-materially-active'],
    database: [
      'cpu-p95>60%',
      'connections>70%',
      'free-memory<20%',
      'storage>70%',
      'single-az-recovery-no-longer-acceptable',
    ],
    session: [
      'any-eviction',
      'memory>70%',
      'sustained-cpu',
      'commercial-session-loss',
      'failover-required',
    ],
    network: ['second-nat-resilience', 'endpoint-break-even'],
    automaticPromotionForbidden: true,
  },
  scaleReady: {
    compute: ['max-capacity-sustained', 'p95-outside-objective', 'task-attributed-latency'],
    databaseProxy: ['connection-churn', 'reconnection-storms', 'connections-near-budget'],
    readReplica: ['demonstrated-read-saturation'],
    session: ['evictions', 'memory', 'cpu', 'connections', 'latency'],
    endpoints: ['documented-monthly-saving-or-resilience'],
    crossRegion: ['rpo-rto', 'budget', 'dr-exercise', 'secondary-region-approval'],
    profileNameAloneEnablesNothing: true,
  },
  profileChangeWorkflow: [
    'metrics',
    'estimate',
    'current-vs-target-comparison',
    'impact',
    'pull-request',
    'tests',
    'synth',
    'diff',
    'approval',
    'deploy',
    'validation',
    'invoice-review',
  ],
  rollback: {
    previousProfileOnlyWhenTechnicallySafe: true,
    reduceRedundancyDuringIncidentForbidden: true,
  },
});

export interface MxMedPreGoLiveApprovalInputs {
  readonly approvedMonthlyBudgetUsd: number | null;
  readonly planningFxMxnPerUsd: number | null;
  readonly planningFxAsOf: string | null;
  readonly anomalyAlertThresholdUsd: number | null;
  readonly maxInfrastructureCostToRevenuePercent: number | null;
  readonly budgetOwner: string | null;
  readonly alertRecipientsConfigured: boolean;
  readonly costReadinessReviewApproved: boolean;
  readonly deploymentProfile: MxMedDeploymentProfile | null;
  readonly costEstimateAsOf: string | null;
  readonly costEstimateVersion: string | null;
}

export interface MxMedPreGoLiveGateResult {
  readonly allowed: boolean;
  readonly missing: readonly (keyof MxMedPreGoLiveApprovalInputs)[];
}

export function evaluatePreGoLiveCostGate(
  inputs: MxMedPreGoLiveApprovalInputs,
): MxMedPreGoLiveGateResult {
  const positiveNumberFields = [
    'approvedMonthlyBudgetUsd',
    'planningFxMxnPerUsd',
    'anomalyAlertThresholdUsd',
    'maxInfrastructureCostToRevenuePercent',
  ] as const;
  const missing: (keyof MxMedPreGoLiveApprovalInputs)[] = positiveNumberFields.filter((field) => {
    const value = inputs[field];
    return typeof value !== 'number' || !Number.isFinite(value) || value <= 0;
  });
  if (inputs.planningFxAsOf === null || !/^\d{4}-\d{2}-\d{2}$/.test(inputs.planningFxAsOf)) {
    missing.push('planningFxAsOf');
  }
  if (inputs.budgetOwner === null || inputs.budgetOwner.trim().length === 0) {
    missing.push('budgetOwner');
  }
  if (!inputs.alertRecipientsConfigured) missing.push('alertRecipientsConfigured');
  if (!inputs.costReadinessReviewApproved) missing.push('costReadinessReviewApproved');
  if (
    inputs.deploymentProfile === null ||
    !MXMED_DEPLOYMENT_PROFILES.includes(inputs.deploymentProfile)
  ) {
    missing.push('deploymentProfile');
  }
  if (inputs.costEstimateAsOf !== MXMED_COST_ESTIMATE_AS_OF) missing.push('costEstimateAsOf');
  if (inputs.costEstimateVersion !== MXMED_COST_AWARE_LAUNCH_PROFILES_CONTRACT) {
    missing.push('costEstimateVersion');
  }
  return Object.freeze({ allowed: missing.length === 0, missing: Object.freeze(missing) });
}

export function parseDeploymentProfile(value: unknown): MxMedDeploymentProfile {
  if (!MXMED_DEPLOYMENT_PROFILES.includes(value as MxMedDeploymentProfile)) {
    throw new Error(
      'MXMED_DEPLOYMENT_PROFILE_INVALID:deploymentProfile:context must be launch-lean-v1, production-standard-v1 or scale-ready-v1',
    );
  }
  return value as MxMedDeploymentProfile;
}

export function getLaunchProfileDefinition(value: unknown): MxMedLaunchProfileDefinition {
  return PROFILE_DEFINITIONS[parseDeploymentProfile(value)];
}

function buildCostLedger(
  environment: MxMedEnvironmentName,
  profile: MxMedDeploymentProfile,
  capacity: MxMedLaunchCapacity,
): readonly MxMedCostLedgerDriver[] {
  const driver = (
    values: Omit<
      MxMedCostLedgerDriver,
      | 'profile'
      | 'environment'
      | 'region'
      | 'rateUsd'
      | 'fixedMonthlyUsd'
      | 'estimatedVariableUsd'
      | 'estimatedStorageUsd'
      | 'estimatedTransferUsd'
      | 'queriedAtUtc'
      | 'uncertainty'
      | 'taxesIncluded'
      | 'fxIncluded'
    >,
  ): MxMedCostLedgerDriver => ({
    ...values,
    profile,
    environment,
    region: 'mx-central-1',
    rateUsd: null,
    fixedMonthlyUsd: null,
    estimatedVariableUsd: null,
    estimatedStorageUsd: null,
    estimatedTransferUsd: null,
    queriedAtUtc: null,
    uncertainty: 'rates-and-usage-require-cost-readiness-review',
    taxesIncluded: false,
    fxIncluded: false,
  });
  return Object.freeze([
    driver({
      stack: 'Network',
      service: 'Amazon VPC',
      resource: 'NAT Gateway and public IPv4',
      quantity: capacity.natGatewayCount,
      unit: 'gateway-and-public-ipv4',
      hours: environment === 'production' ? 730 : null,
      quantityDriver: `natGatewayCount=${String(capacity.natGatewayCount)}`,
      formula: 'quantity * hours * currentRegionalHourlyRate + measuredProcessedGb',
      officialSource: 'https://aws.amazon.com/vpc/pricing/',
      costNature: ['fixed-idle', 'usage-based'],
      capacityState: 'configured',
      reviewRequired: true,
    }),
    driver({
      stack: 'Network',
      service: 'AWS PrivateLink',
      resource: 'Interface endpoint ENI-AZ',
      quantity: capacity.interfaceEndpointCount * 2,
      unit: 'endpoint-eni-az',
      hours: environment === 'production' ? 730 : null,
      quantityDriver: `interfaceEndpointCount=${String(capacity.interfaceEndpointCount)}`,
      formula: 'serviceCount * azCount * hours * currentRegionalHourlyRate + measuredGb',
      officialSource: 'https://aws.amazon.com/privatelink/pricing/',
      costNature: ['fixed-idle', 'usage-based'],
      capacityState: capacity.interfaceEndpointCount === 0 ? 'deferred' : 'configured',
      reviewRequired: true,
    }),
    driver({
      stack: 'Compute',
      service: 'AWS Fargate',
      resource: 'Minimum application tasks',
      quantity: capacity.computeMinCapacity,
      unit: 'fargate-task',
      hours: environment === 'production' ? 730 : null,
      quantityDriver: `minTasks=${String(capacity.computeMinCapacity)};cpu=${String(capacity.computeTaskCpuUnits)};memoryMiB=${String(capacity.computeTaskMemoryMiB)}`,
      formula: 'tasks * hours * currentRegionalCpuAndMemoryRates',
      officialSource: 'https://aws.amazon.com/fargate/pricing/',
      costNature: ['fixed-idle', 'usage-based'],
      capacityState: 'future-microphase',
      reviewRequired: true,
    }),
    driver({
      stack: 'Data',
      service: 'Amazon RDS for MySQL',
      resource: 'DB instance and gp3 storage',
      quantity: 1,
      unit: 'db-instance-plus-provisioned-gib',
      hours: environment === 'production' ? 730 : null,
      quantityDriver: `class=${capacity.databaseInstanceClass};multiAz=${String(capacity.databaseMultiAz)};storageGiB=${String(capacity.databaseAllocatedStorageGiB)}`,
      formula: 'instanceHours + provisionedStorage + measuredIoAndBackupOverage',
      officialSource: 'https://aws.amazon.com/rds/mysql/pricing/',
      costNature: ['fixed-idle', 'storage-based', 'usage-based'],
      capacityState: 'configured',
      reviewRequired: true,
    }),
    driver({
      stack: 'Session',
      service: 'Amazon ElastiCache for Valkey',
      resource: 'Session nodes',
      quantity: capacity.sessionReplicaCount + 1,
      unit: 'cache-node',
      hours: environment === 'production' ? 730 : null,
      quantityDriver: `nodes=${String(capacity.sessionReplicaCount + 1)};class=${capacity.sessionNodeType}`,
      formula: 'nodes * hours * currentRegionalNodeRate + measuredCrossAzAndSnapshots',
      officialSource: 'https://aws.amazon.com/elasticache/pricing/',
      costNature: ['fixed-idle', 'storage-based', 'usage-based'],
      capacityState: 'configured',
      reviewRequired: true,
    }),
    driver({
      stack: 'Security',
      service: 'AWS KMS',
      resource: 'Customer-managed keys and requests',
      quantity: 4,
      unit: 'key-version',
      hours: null,
      quantityDriver: 'kmsKeys=4;requests=measured',
      formula: 'keys * currentMonthlyKeyRate + measuredRequests',
      officialSource: 'https://aws.amazon.com/kms/pricing/',
      costNature: ['fixed-idle', 'usage-based'],
      capacityState: 'configured',
      reviewRequired: true,
    }),
    driver({
      stack: 'Security',
      service: 'AWS Secrets Manager',
      resource: 'Foundation and managed secrets plus requests',
      quantity: 7,
      unit: 'secret',
      hours: null,
      quantityDriver: 'secrets=7;requests=measured',
      formula: 'secrets * currentMonthlySecretRate + measuredRequests',
      officialSource: 'https://aws.amazon.com/secrets-manager/pricing/',
      costNature: ['fixed-idle', 'usage-based'],
      capacityState: 'configured',
      reviewRequired: true,
    }),
    driver({
      stack: 'Storage',
      service: 'Amazon S3',
      resource: 'Application and audit objects, versions and requests',
      quantity: 5,
      unit: 'bucket-with-measured-objects',
      hours: null,
      quantityDriver: 'measured-storage-requests-kms-and-transfer',
      formula: 'measuredStorage + requests + kmsRequests + transfer',
      officialSource: 'https://aws.amazon.com/s3/pricing/',
      costNature: ['storage-based', 'usage-based'],
      capacityState: 'configured',
      reviewRequired: true,
    }),
    driver({
      stack: 'Security',
      service: 'CloudWatch and CloudTrail',
      resource: 'Flow logs, audit logs and events',
      quantity: null,
      unit: 'measured-log-gib-events-and-alarms',
      hours: null,
      quantityDriver: 'measured-ingestion-storage-events-and-alarms',
      formula: 'measuredIngestion + storage + events + alarms',
      officialSource: 'https://aws.amazon.com/cloudwatch/pricing/',
      costNature: ['storage-based', 'usage-based'],
      capacityState: 'configured',
      reviewRequired: true,
    }),
    driver({
      stack: 'Compute',
      service: 'Amazon ECR',
      resource: 'Future immutable image repository',
      quantity: 1,
      unit: 'future-repository-with-measured-gib',
      hours: null,
      quantityDriver: 'measured-image-storage-and-transfer',
      formula: 'measuredImageStorage + transfer',
      officialSource: 'https://aws.amazon.com/ecr/pricing/',
      costNature: ['storage-based', 'usage-based'],
      capacityState: 'future-microphase',
      reviewRequired: true,
    }),
    driver({
      stack: 'Edge',
      service: 'ALB, WAF and CloudFront',
      resource: 'Future public edge',
      quantity: 0,
      unit: 'deferred-edge-capacity',
      hours: null,
      quantityDriver: 'separate-edge-readiness-ledger',
      formula: 'separateEdgeLedgerAfterReadiness',
      officialSource: 'https://aws.amazon.com/elasticloadbalancing/pricing/',
      costNature: ['fixed-idle', 'usage-based'],
      capacityState: 'deferred',
      reviewRequired: true,
    }),
  ]);
}

export function resolveLaunchProfile(
  environment: MxMedEnvironmentName,
  deploymentProfileValue: unknown,
): MxMedLaunchProfileResolution {
  const deploymentProfile = parseDeploymentProfile(deploymentProfileValue);
  if (environment === 'staging' && deploymentProfile !== 'launch-lean-v1') {
    throw new Error(
      `MXMED_ENVIRONMENT_PROFILE_COMBINATION_INVALID:${environment}/${deploymentProfile}:staging permits only launch-lean-v1 with release-window-v1`,
    );
  }
  const definition = PROFILE_DEFINITIONS[deploymentProfile];
  const capacity =
    environment === 'staging' ? STAGING_RELEASE_WINDOW_CAPACITY : definition.production;
  const capabilities =
    environment === 'staging' ? LAUNCH_LEAN_CAPABILITIES : definition.capabilities;
  return Object.freeze({
    contract: MXMED_COST_AWARE_LAUNCH_PROFILES_CONTRACT,
    environment,
    deploymentProfile,
    stagingOperatingMode: environment === 'staging' ? 'release-window-v1' : null,
    capacity,
    capabilities,
    costLedger: buildCostLedger(environment, deploymentProfile, capacity),
  });
}

export function mxmedCostTierForComponent(
  component: string,
): 'fixed-critical' | 'usage-based' | 'storage-based' | 'deferred-optional' {
  if (component === 'storage') return 'storage-based';
  if (['network', 'security', 'data', 'session'].includes(component)) return 'fixed-critical';
  return 'deferred-optional';
}
