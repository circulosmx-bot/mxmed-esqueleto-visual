import { App } from 'aws-cdk-lib';
import { Template } from 'aws-cdk-lib/assertions';

import type { MxMedEnvironmentConfig } from '../lib/config/environment-config';
import { MxMedEnvironmentStage } from '../lib/stages/mxmed-environment-stage';

export interface RenderedSessionResource {
  readonly Type: string;
  readonly Properties?: Record<string, unknown>;
  readonly DependsOn?: string | readonly string[];
  readonly DeletionPolicy?: string;
  readonly UpdateReplacePolicy?: string;
}

export interface RenderedSession {
  readonly app: App;
  readonly stage: MxMedEnvironmentStage;
  readonly resources: Readonly<Record<string, RenderedSessionResource>>;
  readonly outputs: Readonly<Record<string, unknown>>;
  readonly template: Readonly<Record<string, unknown>>;
}

export function renderSession(config: MxMedEnvironmentConfig): RenderedSession {
  const app = new App({ analyticsReporting: false });
  const suffix = config.environmentName === 'staging' ? 'StagingSession' : 'ProductionSession';
  const stage = new MxMedEnvironmentStage(app, `MxMed${suffix}`, { config });
  const template = Template.fromStack(stage.sessionStack).toJSON() as unknown as {
    Resources?: Record<string, RenderedSessionResource>;
    Outputs?: Record<string, unknown>;
  };
  return {
    app,
    stage,
    resources: template.Resources ?? {},
    outputs: template.Outputs ?? {},
    template,
  };
}

export function resourcesOfType(
  resources: Readonly<Record<string, RenderedSessionResource>>,
  type: string,
): (readonly [string, RenderedSessionResource])[] {
  return Object.entries(resources).filter(([, resource]) => resource.Type === type);
}

export function firstResource(rendered: RenderedSession, type: string): RenderedSessionResource {
  const resource = resourcesOfType(rendered.resources, type)[0]?.[1];
  if (resource === undefined) throw new Error(`missing-${type}`);
  return resource;
}

export function properties(resource: RenderedSessionResource): Readonly<Record<string, unknown>> {
  return resource.Properties ?? {};
}

export function userByName(rendered: RenderedSession, userName: string): RenderedSessionResource {
  const user = resourcesOfType(rendered.resources, 'AWS::ElastiCache::User').find(
    ([, resource]) => properties(resource).UserName === userName,
  )?.[1];
  if (user === undefined) throw new Error(`missing-user-${userName}`);
  return user;
}
