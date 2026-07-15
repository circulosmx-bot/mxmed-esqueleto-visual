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

export interface RenderedSecurity {
  readonly app: App;
  readonly stage: MxMedEnvironmentStage;
  readonly resources: Readonly<Record<string, RenderedResource>>;
  readonly outputs: Readonly<Record<string, unknown>>;
  readonly template: Readonly<Record<string, unknown>>;
}

export function renderSecurity(config: MxMedEnvironmentConfig): RenderedSecurity {
  const app = new App({ analyticsReporting: false });
  const suffix = config.environmentName === 'staging' ? 'Staging' : 'Production';
  const stage = new MxMedEnvironmentStage(app, `MxMed${suffix}`, { config });
  const template = Template.fromStack(stage.securityStack).toJSON() as unknown as {
    Resources?: Record<string, RenderedResource>;
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
  resources: Readonly<Record<string, RenderedResource>>,
  type: string,
): (readonly [string, RenderedResource])[] {
  return Object.entries(resources).filter(([, resource]) => resource.Type === type);
}

export function properties(resource: RenderedResource): Record<string, unknown> {
  return resource.Properties ?? {};
}

export function first<T>(items: readonly T[], label: string): T {
  const item = items[0];
  if (item === undefined) throw new Error(`missing-${label}`);
  return item;
}

export function findByLogicalId(
  resources: Readonly<Record<string, RenderedResource>>,
  prefix: string,
): readonly [string, RenderedResource] {
  const entry = Object.entries(resources).find(([logicalId]) => logicalId.startsWith(prefix));
  if (entry === undefined) throw new Error(`missing-${prefix}`);
  return entry;
}

export function policyStatements(resource: RenderedResource): Record<string, unknown>[] {
  const document = properties(resource).PolicyDocument as
    { Statement?: Record<string, unknown>[] } | undefined;
  return document?.Statement ?? [];
}
