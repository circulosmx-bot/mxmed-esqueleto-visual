import { App } from 'aws-cdk-lib';
import { Template } from 'aws-cdk-lib/assertions';

import type {
  MxMedComputeActivationMode,
  MxMedDeploymentProfile,
  MxMedEnvironmentName,
  MxMedRuntimeCapabilityProfile,
} from '../lib/config/environment-config';
import { getEnvironmentConfig } from '../lib/config/environments';
import { MxMedEnvironmentStage } from '../lib/stages/mxmed-environment-stage';

export interface TemplateResource {
  readonly Type: string;
  readonly Properties?: Readonly<Record<string, unknown>>;
  readonly DeletionPolicy?: string;
  readonly UpdateReplacePolicy?: string;
  readonly Metadata?: Readonly<Record<string, unknown>>;
}

export interface RenderedTemplate {
  readonly Parameters?: Readonly<Record<string, Readonly<Record<string, unknown>>>>;
  readonly Resources?: Readonly<Record<string, TemplateResource>>;
}

export interface ComputeFixture {
  readonly app: App;
  readonly stage: MxMedEnvironmentStage;
  readonly compute: RenderedTemplate;
  readonly data: RenderedTemplate;
  readonly security: RenderedTemplate;
  readonly serialized: string;
}

const fixtureCache = new Map<string, ComputeFixture>();

function renderTemplate(stack: MxMedEnvironmentStage['computeStack']): RenderedTemplate {
  return Template.fromStack(stack).toJSON();
}

export function renderCompute(
  environment: MxMedEnvironmentName,
  deploymentProfile: MxMedDeploymentProfile,
  activationMode: MxMedComputeActivationMode,
  runtimeCapabilityProfile?: MxMedRuntimeCapabilityProfile,
): ComputeFixture {
  const key = [environment, deploymentProfile, activationMode, runtimeCapabilityProfile].join(':');
  const cached = fixtureCache.get(key);
  if (cached !== undefined) return cached;
  const config = getEnvironmentConfig(
    environment,
    deploymentProfile,
    activationMode,
    runtimeCapabilityProfile,
  );
  const app = new App({ analyticsReporting: false });
  const stage = new MxMedEnvironmentStage(app, `ComputeFixture${String(fixtureCache.size)}`, {
    config,
  });
  const compute = renderTemplate(stage.computeStack);
  const data = Template.fromStack(stage.dataStack).toJSON();
  const security = Template.fromStack(stage.securityStack).toJSON();
  const fixture = {
    app,
    stage,
    compute,
    data,
    security,
    serialized: JSON.stringify({ compute, data, security }),
  };
  fixtureCache.set(key, fixture);
  return fixture;
}

export function resourcesOfType(
  template: RenderedTemplate,
  resourceType: string,
): TemplateResource[] {
  return Object.values(template.Resources ?? {}).filter(
    (resource) => resource.Type === resourceType,
  );
}

export function requireOne(template: RenderedTemplate, resourceType: string): TemplateResource {
  const matches = resourcesOfType(template, resourceType);
  if (matches.length !== 1 || matches[0] === undefined) {
    throw new Error(`expected-one:${resourceType}:received-${String(matches.length)}`);
  }
  return matches[0];
}

export function resourceProperties(resource: TemplateResource): Readonly<Record<string, unknown>> {
  return resource.Properties ?? {};
}

export function taskByContainerName(
  template: RenderedTemplate,
  containerName: string,
): TemplateResource {
  const task = resourcesOfType(template, 'AWS::ECS::TaskDefinition').find((candidate) => {
    const containers = resourceProperties(candidate).ContainerDefinitions;
    return (
      Array.isArray(containers) &&
      (containers as unknown[]).some((container) => {
        if (typeof container !== 'object' || container === null) return false;
        return (container as Readonly<Record<string, unknown>>).Name === containerName;
      })
    );
  });
  if (task === undefined) throw new Error(`missing-task-container:${containerName}`);
  return task;
}

export function containerByName(
  task: TemplateResource,
  containerName: string,
): Readonly<Record<string, unknown>> {
  const containers = resourceProperties(task).ContainerDefinitions;
  if (!Array.isArray(containers)) throw new Error('task-containers-missing');
  const candidates: readonly unknown[] = containers;
  const container = candidates.find((candidate) => {
    if (typeof candidate !== 'object' || candidate === null) return false;
    return (candidate as Readonly<Record<string, unknown>>).Name === containerName;
  });
  if (typeof container !== 'object' || container === null) {
    throw new Error(`missing-container:${containerName}`);
  }
  return container as Readonly<Record<string, unknown>>;
}

export function namedEntries(value: unknown): Readonly<Record<string, unknown>>[] {
  if (!Array.isArray(value)) return [];
  return value.filter(
    (entry): entry is Readonly<Record<string, unknown>> =>
      typeof entry === 'object' && entry !== null,
  );
}
