import { App } from 'aws-cdk-lib';
import { Template } from 'aws-cdk-lib/assertions';

import type {
  MxMedDeploymentProfile,
  MxMedEnvironmentConfig,
  MxMedEnvironmentName,
} from '../lib/config/environment-config';
import { getEnvironmentConfig, PRODUCTION_CONFIG } from '../lib/config/environments';
import {
  evaluatePreGoLiveCostGate,
  getLaunchProfileDefinition,
  MXMED_COST_AWARE_LAUNCH_PROFILES_CONTRACT,
  MXMED_COST_GATES,
  MXMED_DEPLOYMENT_PROFILES,
  MXMED_NON_NEGOTIABLE_CONTROLS,
  resolveLaunchProfile,
} from '../lib/config/launch-profiles';
import { validateEnvironmentConfig } from '../lib/config/environment-schema';
import { MxMedEnvironmentStage } from '../lib/stages/mxmed-environment-stage';

interface Resource {
  readonly Type: string;
  readonly Properties?: Readonly<Record<string, unknown>>;
}

interface RenderedCombination {
  readonly config: MxMedEnvironmentConfig;
  readonly stage: MxMedEnvironmentStage;
  readonly templates: Readonly<Record<string, Readonly<Record<string, Resource>>>>;
}

const COMBINATIONS = [
  ['staging', 'launch-lean-v1'],
  ['production', 'launch-lean-v1'],
  ['production', 'production-standard-v1'],
  ['production', 'scale-ready-v1'],
] as const satisfies readonly (readonly [MxMedEnvironmentName, MxMedDeploymentProfile])[];

function renderCombination(
  environment: MxMedEnvironmentName,
  profile: MxMedDeploymentProfile,
): RenderedCombination {
  const config = getEnvironmentConfig(environment, profile);
  const app = new App({ analyticsReporting: false });
  const stage = new MxMedEnvironmentStage(
    app,
    `Contract${environment}${profile.replaceAll('-', '')}`,
    { config },
  );
  const templates = Object.fromEntries(
    [
      stage.networkStack,
      stage.securityStack,
      stage.dataStack,
      stage.storageStack,
      stage.sessionStack,
      stage.computeStack,
    ].map((stack) => {
      const template = Template.fromStack(stack).toJSON() as {
        Resources?: Record<string, Resource>;
      };
      return [stack.stackName, template.Resources ?? {}];
    }),
  );
  return { config, stage, templates };
}

function resourcesOfType(
  fixture: RenderedCombination,
  component: 'network' | 'security' | 'data' | 'storage' | 'session' | 'compute',
  type: string,
): Resource[] {
  const resources = fixture.templates[`mxmed-${fixture.config.environmentCode}-${component}`] ?? {};
  return Object.values(resources).filter((resource) => resource.Type === type);
}

function properties(resource: Resource): Readonly<Record<string, unknown>> {
  return resource.Properties ?? {};
}

function firstResource(resources: readonly Resource[], label: string): Resource {
  const resource = resources[0];
  if (resource === undefined) throw new Error(`missing-resource:${label}`);
  return resource;
}

describe('MXMED_AWS_COST_AWARE_LAUNCH_PROFILES_CONTRACT_V1', () => {
  test('defines every PP263 profile once with exact versioned names', () => {
    expect(MXMED_COST_AWARE_LAUNCH_PROFILES_CONTRACT).toBe(
      'MXMED_AWS_COST_AWARE_LAUNCH_PROFILES_CONTRACT_V1',
    );
    expect(MXMED_DEPLOYMENT_PROFILES).toEqual([
      'launch-lean-v1',
      'production-standard-v1',
      'scale-ready-v1',
    ]);
    expect(
      MXMED_DEPLOYMENT_PROFILES.map((profile) => getLaunchProfileDefinition(profile).name),
    ).toEqual(MXMED_DEPLOYMENT_PROFILES);
  });

  test('rejects an absent or unknown profile before creating a stage', () => {
    expect(() => getEnvironmentConfig('production', undefined)).toThrow(
      'MXMED_DEPLOYMENT_PROFILE_INVALID',
    );
    expect(() => getEnvironmentConfig('production', 'unexpectedly-expensive')).toThrow(
      'MXMED_DEPLOYMENT_PROFILE_INVALID',
    );
  });

  test('rejects prohibited staging/profile combinations', () => {
    for (const profile of ['production-standard-v1', 'scale-ready-v1'] as const) {
      expect(() => getEnvironmentConfig('staging', profile)).toThrow(
        `MXMED_ENVIRONMENT_PROFILE_COMBINATION_INVALID:staging/${profile}`,
      );
    }
  });

  test('rejects incomplete and internally inconsistent profile configuration', () => {
    const { computeDesiredCount: _omitted, ...incomplete } = PRODUCTION_CONFIG;
    expect(() => {
      validateEnvironmentConfig(incomplete);
    }).toThrow('MXMED_CONFIG_INVALID:computeDesiredCount');
    expect(() => {
      validateEnvironmentConfig({ ...PRODUCTION_CONFIG, natGatewayCount: 2 });
    }).toThrow('MXMED_CONFIG_INVALID:natGatewayCount');
  });

  test('protects production from an implicit expensive default', () => {
    expect(PRODUCTION_CONFIG).toMatchObject({
      deploymentProfile: 'launch-lean-v1',
      natGatewayCount: 1,
      interfaceEndpointProfile: 's3-only',
      databaseAvailabilityProfile: 'single-az',
      sessionAvailabilityProfile: 'single-node',
      approvedMonthlyBudgetUsd: null,
      costReadinessReviewApproved: false,
    });
    expect(MXMED_COST_GATES.costReadinessReview.requiredBeforeFirstDeploy).toBe(true);
  });

  test('keeps the pre-Go-Live gate blocked without invented business inputs', () => {
    const pending = evaluatePreGoLiveCostGate(PRODUCTION_CONFIG);
    expect(pending.allowed).toBe(false);
    expect(pending.missing).toEqual(
      expect.arrayContaining([
        'approvedMonthlyBudgetUsd',
        'planningFxMxnPerUsd',
        'planningFxAsOf',
        'anomalyAlertThresholdUsd',
        'maxInfrastructureCostToRevenuePercent',
        'budgetOwner',
        'alertRecipientsConfigured',
        'costReadinessReviewApproved',
      ]),
    );
  });

  test('can evaluate a complete synthetic approval without storing it as a default', () => {
    const syntheticPositiveValue = 1;
    expect(
      evaluatePreGoLiveCostGate({
        approvedMonthlyBudgetUsd: syntheticPositiveValue,
        planningFxMxnPerUsd: syntheticPositiveValue,
        planningFxAsOf: '2000-01-01',
        anomalyAlertThresholdUsd: syntheticPositiveValue,
        maxInfrastructureCostToRevenuePercent: syntheticPositiveValue,
        budgetOwner: 'synthetic-test-owner',
        alertRecipientsConfigured: true,
        costReadinessReviewApproved: true,
        deploymentProfile: 'launch-lean-v1',
        costEstimateAsOf: '2026-07-16',
        costEstimateVersion: 'MXMED_AWS_COST_AWARE_LAUNCH_PROFILES_CONTRACT_V1',
      }),
    ).toEqual({ allowed: true, missing: [] });
  });

  test('represents promotion thresholds and independently gated scale capabilities', () => {
    expect(MXMED_COST_GATES.launchToStandard.evidenceDays).toBe(7);
    expect(MXMED_COST_GATES.launchToStandard.automaticPromotionForbidden).toBe(true);
    expect(MXMED_COST_GATES.launchToStandard.compute).toEqual(
      expect.arrayContaining(['cpu-p95>60%', 'memory-p95>70%']),
    );
    expect(MXMED_COST_GATES.launchToStandard.database).toEqual(
      expect.arrayContaining(['connections>70%', 'free-memory<20%', 'storage>70%']),
    );
    expect(MXMED_COST_GATES.launchToStandard.session).toEqual(
      expect.arrayContaining(['any-eviction', 'memory>70%']),
    );
    expect(MXMED_COST_GATES.scaleReady.profileNameAloneEnablesNothing).toBe(true);
    expect(MXMED_COST_GATES.endpointPromotion.breakEvenGbFormula).toBe(
      'fixedEndpoint / netSavingPerGb',
    );
    expect(MXMED_COST_GATES.standardSessionMicroSizing).toMatchObject({
      requiredBeforeDeploy: true,
      acceptanceFormula: 'requiredSessionBytes <= 70% * measuredUsableMicroBytes',
      fallbackWhenGateFails: 'cache.t4g.medium',
    });
    expect(MXMED_COST_GATES.stagingReleaseWindow).toMatchObject({
      stagingReleaseWindowHoursRequired: true,
      independentBudgetRequired: true,
      outsideWindowSpendAlertRequired: true,
      schedulerAndDestroyRunbookDeferred: true,
    });
    expect(MXMED_COST_GATES.futureBillingControls).toMatchObject({
      resourcesDeferred: true,
      productionAutoStopForbidden: true,
      realRecipientsStoredInSource: false,
      monthlyBudgetThresholds: [
        { percent: 50, basis: 'actual' },
        { percent: 75, basis: 'actual' },
        { percent: 90, basis: 'forecast' },
        { percent: 100, basis: 'actual' },
        { percent: 120, basis: 'actual-critical' },
      ],
    });
    expect(MXMED_COST_GATES.profileChangeWorkflow).toEqual(
      expect.arrayContaining(['metrics', 'pull-request', 'tests', 'synth', 'diff', 'approval']),
    );
  });

  test('preserves every non-negotiable safety family across profiles', () => {
    expect(MXMED_NON_NEGOTIABLE_CONTROLS).toEqual(
      expect.arrayContaining([
        'private-networking',
        'private-rds-and-valkey',
        's3-block-public-access',
        'tls',
        'kms',
        'secrets-manager',
        'cloudtrail-log-file-validation',
        's3-versioning',
        'rds-backup-pitr-protection-final-snapshot',
      ]),
    );
  });
});

describe.each(COMBINATIONS)('%s / %s', (environment, profile) => {
  const fixture = renderCombination(environment, profile);
  const resolution = resolveLaunchProfile(environment, profile);

  test('resolves deterministically with explicit environment/profile separation', () => {
    expect(fixture.config.environmentName).toBe(environment);
    expect(fixture.config.deploymentProfile).toBe(profile);
    expect(resolveLaunchProfile(environment, profile)).toEqual(resolution);
    expect(fixture.config.tags.Environment).toBe(environment);
    expect(fixture.config.tags.DeploymentProfile).toBe(profile);
    expect(fixture.config.tags.Ephemeral).toBe(environment === 'staging' ? 'true' : 'false');
  });

  test('applies the profile to Network without activating conditional endpoints', () => {
    expect(resourcesOfType(fixture, 'network', 'AWS::EC2::NatGateway')).toHaveLength(
      resolution.capacity.natGatewayCount,
    );
    expect(resourcesOfType(fixture, 'network', 'AWS::EC2::VPCEndpoint')).toHaveLength(1);
    expect(fixture.config.interfaceEndpointProfile).toBe(
      resolution.capacity.interfaceEndpointProfile,
    );
  });

  test('applies the profile to Data while preserving private encrypted protected production', () => {
    const instances = resourcesOfType(fixture, 'data', 'AWS::RDS::DBInstance');
    expect(instances).toHaveLength(1);
    expect(properties(firstResource(instances, 'database'))).toMatchObject({
      DBInstanceClass: resolution.capacity.databaseInstanceClass,
      MultiAZ: resolution.capacity.databaseMultiAz,
      AllocatedStorage: String(resolution.capacity.databaseAllocatedStorageGiB),
      MaxAllocatedStorage: resolution.capacity.databaseMaxAllocatedStorageGiB,
      StorageEncrypted: true,
      PubliclyAccessible: false,
      BackupRetentionPeriod: environment === 'production' ? 35 : 7,
      DeletionProtection: environment === 'production',
    });
  });

  test('preserves Storage safety and keeps cross-region replication deferred', () => {
    const buckets = resourcesOfType(fixture, 'storage', 'AWS::S3::Bucket');
    expect(buckets).toHaveLength(4);
    for (const bucket of buckets) {
      expect(properties(bucket)).toMatchObject({
        PublicAccessBlockConfiguration: {
          BlockPublicAcls: true,
          BlockPublicPolicy: true,
          IgnorePublicAcls: true,
          RestrictPublicBuckets: true,
        },
        VersioningConfiguration: { Status: 'Enabled' },
      });
      expect(properties(bucket)).not.toHaveProperty('ReplicationConfiguration');
    }
  });

  test('applies the profile to Session without weakening TLS, KMS or ACL topology', () => {
    const groups = resourcesOfType(fixture, 'session', 'AWS::ElastiCache::ReplicationGroup');
    expect(groups).toHaveLength(1);
    expect(properties(firstResource(groups, 'session'))).toMatchObject({
      CacheNodeType: resolution.capacity.sessionNodeType,
      NumCacheClusters: resolution.capacity.sessionReplicaCount + 1,
      MultiAZEnabled: resolution.capacity.sessionMultiAzEnabled,
      AutomaticFailoverEnabled: resolution.capacity.sessionAutomaticFailoverEnabled,
      AtRestEncryptionEnabled: true,
      TransitEncryptionEnabled: true,
      SnapshotRetentionLimit: 0,
    });
  });

  test('exposes Compute configuration but creates no Compute resources', () => {
    expect(fixture.stage.computeStack.launchCapacity).toMatchObject({
      computeDesiredCount: resolution.capacity.computeDesiredCount,
      computeMinCapacity: resolution.capacity.computeMinCapacity,
      computeMaxCapacity: resolution.capacity.computeMaxCapacity,
      computeTaskCpuUnits: resolution.capacity.computeTaskCpuUnits,
      computeTaskMemoryMiB: resolution.capacity.computeTaskMemoryMiB,
      computeAssignPublicIp: false,
      computeUseSpot: false,
    });
    expect(
      Object.keys(fixture.templates[`mxmed-${fixture.config.environmentCode}-compute`] ?? {}),
    ).toHaveLength(0);
  });

  test('keeps Security complete and templates free of credential values', () => {
    expect(resourcesOfType(fixture, 'security', 'AWS::KMS::Key')).toHaveLength(4);
    const serialized = JSON.stringify(fixture.templates);
    expect(serialized).not.toMatch(/AKIA|ASIA|sk_(?:live|test)|BEGIN PRIVATE KEY/);
    for (const resources of Object.values(fixture.templates)) {
      for (const resource of Object.values(resources)) {
        if (resource.Type === 'AWS::RDS::DBInstance') {
          expect(properties(resource)).not.toHaveProperty('MasterUserPassword');
        }
        if (resource.Type === 'AWS::SecretsManager::Secret') {
          expect(properties(resource)).not.toHaveProperty('SecretString');
        }
      }
    }
  });

  test('provides a price-free ledger of fixed, variable, storage and deferred decisions', () => {
    expect(resolution.costLedger.length).toBeGreaterThanOrEqual(10);
    expect(resolution.costLedger.flatMap((row) => row.costNature)).toEqual(
      expect.arrayContaining(['fixed-idle', 'usage-based', 'storage-based']),
    );
    for (const row of resolution.costLedger) {
      expect(row).toMatchObject({
        rateUsd: null,
        fixedMonthlyUsd: null,
        estimatedVariableUsd: null,
        estimatedStorageUsd: null,
        estimatedTransferUsd: null,
        queriedAtUtc: null,
        taxesIncluded: false,
        fxIncluded: false,
      });
      expect(row.formula.length).toBeGreaterThan(0);
      expect(row.unit.length).toBeGreaterThan(0);
      expect(row.officialSource).toMatch(/^https:\/\/aws\.amazon\.com\//);
    }
    expect(resolution.capabilities.some((capability) => capability.state === 'deferred')).toBe(
      true,
    );
  });
});

test('production profile capacity matrix matches PP263', () => {
  const staging = resolveLaunchProfile('staging', 'launch-lean-v1').capacity;
  const lean = resolveLaunchProfile('production', 'launch-lean-v1').capacity;
  const standard = resolveLaunchProfile('production', 'production-standard-v1').capacity;
  const scale = resolveLaunchProfile('production', 'scale-ready-v1').capacity;

  expect(staging).toMatchObject({
    natGatewayCount: 1,
    interfaceEndpointProfile: 's3-only',
    computeDesiredCount: 1,
    computeMinCapacity: 1,
    computeMaxCapacity: 1,
    computeTaskCpuUnits: 512,
    computeTaskMemoryMiB: 1024,
    databaseInstanceClass: 'db.t4g.medium',
    databaseMultiAz: false,
    databaseAllocatedStorageGiB: 40,
    sessionNodeType: 'cache.t4g.micro',
    sessionReplicaCount: 0,
  });
  expect(lean).toMatchObject({
    natGatewayCount: 1,
    computeDesiredCount: 1,
    computeMinCapacity: 1,
    computeMaxCapacity: 2,
    computeTaskCpuUnits: 512,
    computeTaskMemoryMiB: 1024,
    databaseInstanceClass: 'db.t4g.medium',
    databaseMultiAz: false,
    databaseAllocatedStorageGiB: 40,
    databaseMaxAllocatedStorageGiB: 200,
    sessionNodeType: 'cache.t4g.micro',
    sessionReplicaCount: 0,
  });
  expect(standard).toMatchObject({
    natGatewayCount: 2,
    interfaceEndpointProfile: 's3-only',
    computeDesiredCount: 2,
    computeMinCapacity: 2,
    computeMaxCapacity: 6,
    computeTaskCpuUnits: 1024,
    computeTaskMemoryMiB: 2048,
    databaseInstanceClass: 'db.m6g.large',
    databaseMultiAz: true,
    databaseAllocatedStorageGiB: 100,
    databaseMaxAllocatedStorageGiB: 1000,
    sessionNodeType: 'cache.t4g.micro',
    sessionReplicaCount: 1,
  });
  expect(scale).toMatchObject({
    interfaceEndpointProfile: 'measured',
    sessionNodeType: 'cache.t4g.medium',
    sessionReplicaCount: 1,
  });
});
