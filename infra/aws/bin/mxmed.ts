#!/usr/bin/env node
import { App, AspectPriority, Aspects, Tags } from 'aws-cdk-lib';
import type { IConstruct } from 'constructs';

import { MandatoryTagsAspect } from '../lib/aspects/mandatory-tags-aspect';
import { EdgeFoundationAspect } from '../lib/aspects/edge-foundation-aspect';
import { NoPublicBucketAspect } from '../lib/aspects/no-public-bucket-aspect';
import { NoPublicDatabaseAspect } from '../lib/aspects/no-public-database-aspect';
import { ProductionRetentionAspect } from '../lib/aspects/production-retention-aspect';
import { OperationsFoundationAspect } from '../lib/aspects/operations-foundation-aspect';
import { BackupDrFoundationAspect } from '../lib/aspects/backup-dr-foundation-aspect';
import {
  MXMED_SAFE_STRIPE_RETURN_LOGGING_CONTROLS,
  StripeReturnLoggingSafetyAspect,
} from '../lib/aspects/stripe-return-logging-safety-aspect';
import type { MxMedEnvironmentConfig } from '../lib/config/environment-config';
import { parseComputeActivationMode } from '../lib/config/compute-config';
import { getEnvironmentConfig } from '../lib/config/environments';
import { MxMedEmailStage } from '../lib/stages/mxmed-email-stage';
import { MxMedEnvironmentStage } from '../lib/stages/mxmed-environment-stage';
import { MxMedGlobalEdgeStage } from '../lib/stages/mxmed-global-edge-stage';
import { edgeCreatesGlobal } from '../lib/config/edge-config';
import { operationsCreatesCost } from '../lib/config/operations-profiles';
import { MxMedGlobalOperationsStage } from '../lib/stages/mxmed-global-operations-stage';

function applyGlobalTags(scope: IConstruct, config: MxMedEnvironmentConfig): void {
  Tags.of(scope).add('Project', config.tags.Project);
  Tags.of(scope).add('Environment', config.tags.Environment);
  Tags.of(scope).add('ManagedBy', config.tags.ManagedBy);
  Tags.of(scope).add('Application', config.tags.Application);
  Tags.of(scope).add('Owner', config.tags.Owner);
  Tags.of(scope).add('DeploymentProfile', config.tags.DeploymentProfile);
  Tags.of(scope).add('CostReview', config.tags.CostReview);
  Tags.of(scope).add('Ephemeral', config.tags.Ephemeral);
  Tags.of(scope).add('SchedulePolicy', config.tags.SchedulePolicy);
  Tags.of(scope).add('CostScope', config.tags.CostScope);
}

function applyFoundationAspects(
  environmentStage: MxMedEnvironmentStage,
  emailStage: MxMedEmailStage,
  globalEdgeStage: MxMedGlobalEdgeStage | undefined,
  globalOperationsStage: MxMedGlobalOperationsStage | undefined,
  config: MxMedEnvironmentConfig,
): void {
  for (const stage of [environmentStage, emailStage]) {
    Aspects.of(stage).add(new MandatoryTagsAspect(), { priority: AspectPriority.READONLY });
    Aspects.of(stage).add(new NoPublicBucketAspect(), { priority: AspectPriority.READONLY });
    Aspects.of(stage).add(new NoPublicDatabaseAspect(), { priority: AspectPriority.READONLY });
    Aspects.of(stage).add(new ProductionRetentionAspect(config.environmentName), {
      priority: AspectPriority.READONLY,
    });
  }

  Aspects.of(environmentStage).add(
    new StripeReturnLoggingSafetyAspect({
      ...MXMED_SAFE_STRIPE_RETURN_LOGGING_CONTROLS,
      policy: config.stripeReturnLoggingPolicy,
    }),
    { priority: AspectPriority.READONLY },
  );
  Aspects.of(environmentStage).add(new EdgeFoundationAspect(), {
    priority: AspectPriority.READONLY,
  });
  Aspects.of(environmentStage).add(new OperationsFoundationAspect(config), {
    priority: AspectPriority.READONLY,
  });
  Aspects.of(environmentStage).add(new BackupDrFoundationAspect(config), {
    priority: AspectPriority.READONLY,
  });
  if (globalEdgeStage !== undefined) {
    Aspects.of(globalEdgeStage).add(new EdgeFoundationAspect(), {
      priority: AspectPriority.READONLY,
    });
  }
  if (globalOperationsStage?.globalEdgeStack !== undefined) {
    Aspects.of(globalOperationsStage).add(new EdgeFoundationAspect(), {
      priority: AspectPriority.READONLY,
    });
  }
  if (globalOperationsStage !== undefined) {
    Aspects.of(globalOperationsStage).add(new OperationsFoundationAspect(config), {
      priority: AspectPriority.READONLY,
    });
  }
}

export function createMxMedApp(): App {
  const app = new App({ analyticsReporting: false });
  const computeActivationMode = parseComputeActivationMode(
    app.node.tryGetContext('computeActivationMode'),
  );
  const config = getEnvironmentConfig(
    app.node.tryGetContext('environment'),
    app.node.tryGetContext('deploymentProfile'),
    computeActivationMode,
    app.node.tryGetContext('runtimeCapabilityProfile'),
    {
      edgeActivationMode: app.node.tryGetContext('edgeActivationMode'),
      edgePricingProfile: app.node.tryGetContext('edgePricingProfile'),
      edgeOriginMode: app.node.tryGetContext('edgeOriginMode'),
      edgeLoggingProfile: app.node.tryGetContext('edgeLoggingProfile'),
      edgeCacheProfile: app.node.tryGetContext('edgeCacheProfile'),
      edgeWafProfile: app.node.tryGetContext('edgeWafProfile'),
      edgeMapsMode: app.node.tryGetContext('edgeMapsMode'),
      edgeDnsMode: app.node.tryGetContext('edgeDnsMode'),
      edgeCutoverState: app.node.tryGetContext('edgeCutoverState'),
      staticAssetCacheState: app.node.tryGetContext('staticAssetCacheState'),
    },
    {
      operationsActivationMode: app.node.tryGetContext('operationsActivationMode'),
      operationsNotificationMode: app.node.tryGetContext('operationsNotificationMode'),
      operationsLogProtectionProfile: app.node.tryGetContext('operationsLogProtectionProfile'),
      operationsRuntimeGateState: app.node.tryGetContext('operationsRuntimeGateState'),
      clinicalLogSanitizationState: app.node.tryGetContext('clinicalLogSanitizationState'),
      costAllocationTagState: app.node.tryGetContext('costAllocationTagState'),
      costAnomalyMonitorOwnershipMode: app.node.tryGetContext('costAnomalyMonitorOwnershipMode'),
      costTagAnomalyMonitorMode: app.node.tryGetContext('costTagAnomalyMonitorMode'),
    },
    {
      backupDrActivationMode: app.node.tryGetContext('backupDrActivationMode'),
      backupVaultLockMode: app.node.tryGetContext('backupVaultLockMode'),
      drRegionState: app.node.tryGetContext('drRegionState'),
      drRegion: app.node.tryGetContext('drRegion'),
      backupDataResidencyState: app.node.tryGetContext('backupDataResidencyState'),
      crossAccountBackupMode: app.node.tryGetContext('crossAccountBackupMode'),
      restoreTestingMode: app.node.tryGetContext('restoreTestingMode'),
      backupSelectionMode: app.node.tryGetContext('backupSelectionMode'),
      backupValidationState: app.node.tryGetContext('backupValidationState'),
      backupComplianceChangeableForDays: app.node.tryGetContext(
        'backupComplianceChangeableForDays',
      ),
      backupApplicationValidationIntegrated: app.node.tryGetContext(
        'backupApplicationValidationIntegrated',
      ),
      backupSentinelsIntegrated: app.node.tryGetContext('backupSentinelsIntegrated'),
    },
  );
  const accountValue = process.env.CDK_DEFAULT_ACCOUNT?.trim();
  const stageAccount =
    accountValue === undefined || accountValue.length === 0 ? undefined : accountValue;
  const environmentStageId =
    config.environmentName === 'staging' ? 'MxMedStaging' : 'MxMedProduction';
  const emailStageId =
    config.environmentName === 'staging' ? 'MxMedStagingEmail' : 'MxMedProductionEmail';
  const stageProps = stageAccount === undefined ? { config } : { config, account: stageAccount };

  const environmentStage = new MxMedEnvironmentStage(app, environmentStageId, stageProps);
  const emailStage = new MxMedEmailStage(app, emailStageId, stageProps);
  const globalOperationsStage = operationsCreatesCost(config)
    ? new MxMedGlobalOperationsStage(app, `${environmentStageId}GlobalOperations`, stageProps)
    : undefined;
  const globalEdgeStage =
    globalOperationsStage === undefined && edgeCreatesGlobal(config)
      ? new MxMedGlobalEdgeStage(app, `${environmentStageId}GlobalEdge`, stageProps)
      : undefined;

  applyGlobalTags(environmentStage, config);
  applyGlobalTags(emailStage, config);
  if (globalEdgeStage !== undefined) applyGlobalTags(globalEdgeStage, config);
  if (globalOperationsStage !== undefined) applyGlobalTags(globalOperationsStage, config);
  applyFoundationAspects(
    environmentStage,
    emailStage,
    globalEdgeStage,
    globalOperationsStage,
    config,
  );

  return app;
}

createMxMedApp();
