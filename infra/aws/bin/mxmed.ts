#!/usr/bin/env node
import { App, AspectPriority, Aspects, Tags } from 'aws-cdk-lib';
import type { IConstruct } from 'constructs';

import { MandatoryTagsAspect } from '../lib/aspects/mandatory-tags-aspect';
import { NoPublicBucketAspect } from '../lib/aspects/no-public-bucket-aspect';
import { NoPublicDatabaseAspect } from '../lib/aspects/no-public-database-aspect';
import { ProductionRetentionAspect } from '../lib/aspects/production-retention-aspect';
import {
  MXMED_SAFE_STRIPE_RETURN_LOGGING_CONTROLS,
  StripeReturnLoggingSafetyAspect,
} from '../lib/aspects/stripe-return-logging-safety-aspect';
import type { MxMedEnvironmentConfig } from '../lib/config/environment-config';
import { getEnvironmentConfig } from '../lib/config/environments';
import { MxMedEmailStage } from '../lib/stages/mxmed-email-stage';
import { MxMedEnvironmentStage } from '../lib/stages/mxmed-environment-stage';

function applyGlobalTags(scope: IConstruct, config: MxMedEnvironmentConfig): void {
  Tags.of(scope).add('Project', config.tags.Project);
  Tags.of(scope).add('Environment', config.tags.Environment);
  Tags.of(scope).add('ManagedBy', config.tags.ManagedBy);
  Tags.of(scope).add('Application', config.tags.Application);
  Tags.of(scope).add('Owner', config.tags.Owner);
}

function applyFoundationAspects(
  environmentStage: MxMedEnvironmentStage,
  emailStage: MxMedEmailStage,
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
}

export function createMxMedApp(): App {
  const app = new App({ analyticsReporting: false });
  const config = getEnvironmentConfig(app.node.tryGetContext('environment'));
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

  applyGlobalTags(environmentStage, config);
  applyGlobalTags(emailStage, config);
  applyFoundationAspects(environmentStage, emailStage, config);

  return app;
}

createMxMedApp();
