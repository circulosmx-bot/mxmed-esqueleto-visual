import { App } from 'aws-cdk-lib';
import { Template } from 'aws-cdk-lib/assertions';

import type { MxMedEnvironmentConfig } from '../lib/config/environment-config';
import { MxMedEnvironmentStage } from '../lib/stages/mxmed-environment-stage';

export interface RenderedStorageResource {
  readonly Type: string;
  readonly Properties?: Record<string, unknown>;
  readonly DeletionPolicy?: string;
  readonly UpdateReplacePolicy?: string;
}

export interface RenderedStorage {
  readonly app: App;
  readonly stage: MxMedEnvironmentStage;
  readonly resources: Readonly<Record<string, RenderedStorageResource>>;
  readonly outputs: Readonly<Record<string, unknown>>;
  readonly template: Readonly<Record<string, unknown>>;
}

export function renderStorage(config: MxMedEnvironmentConfig): RenderedStorage {
  const app = new App({ analyticsReporting: false });
  const suffix = config.environmentName === 'staging' ? 'StagingStorage' : 'ProductionStorage';
  const stage = new MxMedEnvironmentStage(app, `MxMed${suffix}`, { config });
  const template = Template.fromStack(stage.storageStack).toJSON() as unknown as {
    Resources?: Record<string, RenderedStorageResource>;
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
  resources: Readonly<Record<string, RenderedStorageResource>>,
  type: string,
): (readonly [string, RenderedStorageResource])[] {
  return Object.entries(resources).filter(([, resource]) => resource.Type === type);
}

export function bucketByLogicalPrefix(
  resources: Readonly<Record<string, RenderedStorageResource>>,
  logicalPrefix: string,
): RenderedStorageResource {
  const resource = Object.entries(resources).find(
    ([logicalId, candidate]) =>
      candidate.Type === 'AWS::S3::Bucket' && logicalId.startsWith(logicalPrefix),
  )?.[1];
  if (resource === undefined) throw new Error(`missing-bucket-${logicalPrefix}`);
  return resource;
}

export function properties(resource: RenderedStorageResource): Readonly<Record<string, unknown>> {
  return resource.Properties ?? {};
}

export function lifecycleRules(resource: RenderedStorageResource): Record<string, unknown>[] {
  const lifecycle = properties(resource).LifecycleConfiguration as
    { Rules?: Record<string, unknown>[] } | undefined;
  return lifecycle?.Rules ?? [];
}

export function ruleById(
  resource: RenderedStorageResource,
  id: string,
): Readonly<Record<string, unknown>> {
  const rule = lifecycleRules(resource).find((candidate) => candidate.Id === id);
  if (rule === undefined) throw new Error(`missing-lifecycle-rule-${id}`);
  return rule;
}

export function tagMap(resource: RenderedStorageResource): Readonly<Record<string, string>> {
  const tags = properties(resource).Tags as { Key: string; Value: string }[] | undefined;
  return Object.fromEntries((tags ?? []).map((tag) => [tag.Key, tag.Value]));
}
