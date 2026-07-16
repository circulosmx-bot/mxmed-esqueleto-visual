import { App } from 'aws-cdk-lib';
import { Template } from 'aws-cdk-lib/assertions';

import type { MxMedEnvironmentConfig } from '../lib/config/environment-config';
import { MxMedEnvironmentStage } from '../lib/stages/mxmed-environment-stage';

export interface RenderedResource {
  readonly Type: string;
  readonly Properties?: Record<string, unknown>;
  readonly DeletionPolicy?: string;
  readonly UpdateReplacePolicy?: string;
}

export interface RenderedData {
  readonly app: App;
  readonly stage: MxMedEnvironmentStage;
  readonly resources: Readonly<Record<string, RenderedResource>>;
  readonly outputs: Readonly<Record<string, unknown>>;
  readonly template: Readonly<Record<string, unknown>>;
  readonly networkTemplate: Readonly<Record<string, unknown>>;
  readonly securityTemplate: Readonly<Record<string, unknown>>;
}

export function renderData(config: MxMedEnvironmentConfig): RenderedData {
  const app = new App({ analyticsReporting: false });
  const suffix = config.environmentName === 'staging' ? 'StagingData' : 'ProductionData';
  const stage = new MxMedEnvironmentStage(app, `MxMed${suffix}`, { config });
  const template = Template.fromStack(stage.dataStack).toJSON() as unknown as {
    Resources?: Record<string, RenderedResource>;
    Outputs?: Record<string, unknown>;
  };
  return {
    app,
    stage,
    resources: template.Resources ?? {},
    outputs: template.Outputs ?? {},
    template,
    networkTemplate: Template.fromStack(stage.networkStack).toJSON(),
    securityTemplate: Template.fromStack(stage.securityStack).toJSON(),
  };
}

export function resourcesOfType(
  resources: Readonly<Record<string, RenderedResource>>,
  type: string,
): (readonly [string, RenderedResource])[] {
  return Object.entries(resources).filter(([, resource]) => resource.Type === type);
}

export function firstResource(
  resources: Readonly<Record<string, RenderedResource>>,
  type: string,
): RenderedResource {
  const resource = resourcesOfType(resources, type)[0]?.[1];
  if (resource === undefined) throw new Error(`missing-${type}`);
  return resource;
}

export function properties(resource: RenderedResource): Readonly<Record<string, unknown>> {
  return resource.Properties ?? {};
}
