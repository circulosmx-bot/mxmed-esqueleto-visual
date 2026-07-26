import { App, Stack } from 'aws-cdk-lib';
import { Template } from 'aws-cdk-lib/assertions';

import { getEnvironmentConfig } from '../lib/config/environments';
import { MxMedEnvironmentStage } from '../lib/stages/mxmed-environment-stage';

function directDependencyNames(stack: Stack): string[] {
  return stack.dependencies.map((dependency) => dependency.stackName).sort();
}

function resources(stack: Stack): Readonly<Record<string, { readonly Type: string }>> {
  return (
    (
      Template.fromStack(stack).toJSON() as {
        readonly Resources?: Readonly<Record<string, { readonly Type: string }>>;
      }
    ).Resources ?? {}
  );
}

function countType(stack: Stack, type: string): number {
  return Object.values(resources(stack)).filter((resource) => resource.Type === type).length;
}

test('registry-only creates an independently deployable three-resource Registry boundary', () => {
  const app = new App({ analyticsReporting: false });
  const config = getEnvironmentConfig('staging', 'launch-lean-v1', 'registry-only-v1');
  const stage = new MxMedEnvironmentStage(app, 'RegistryOnlyIntegration', {
    config,
    account: '123456789012',
  });
  const registry = stage.registryStack;
  if (registry === undefined) throw new Error('missing-registry-stack');

  expect(registry.stackName).toBe('mxmed-stg-registry');
  expect(registry.account).toBe('123456789012');
  expect(registry.region).toBe('mx-central-1');
  expect(Object.keys(resources(registry))).toHaveLength(3);
  expect(Object.keys(resources(stage.computeStack))).toHaveLength(0);
  expect(directDependencyNames(registry)).toEqual([]);
  expect(directDependencyNames(stage.computeStack)).toEqual([]);
  expect(countType(stage.securityStack, 'AWS::KMS::Key')).toBe(4);
});

test('tasks-ready makes Compute depend on Registry while Registry remains independent', () => {
  const app = new App({ analyticsReporting: false });
  const config = getEnvironmentConfig(
    'production',
    'launch-lean-v1',
    'tasks-ready-v1',
    'directory-core-v1',
  );
  const stage = new MxMedEnvironmentStage(app, 'RegistryTasksIntegration', { config });
  const registry = stage.registryStack;
  if (registry === undefined) throw new Error('missing-registry-stack');

  expect(directDependencyNames(registry)).toEqual([]);
  expect(directDependencyNames(stage.computeStack)).toEqual(
    [
      'mxmed-prd-data',
      'mxmed-prd-network',
      'mxmed-prd-registry',
      'mxmed-prd-security',
      'mxmed-prd-session',
      'mxmed-prd-storage',
    ].sort(),
  );
  expect(countType(stage.computeStack, 'AWS::ECR::Repository')).toBe(0);
  expect(countType(registry, 'AWS::ECR::Repository')).toBe(1);
  expect(countType(stage.computeStack, 'AWS::ECS::TaskDefinition')).toBe(2);
});

test('disabled mode creates no Registry and preserves the eight-stack baseline', () => {
  const app = new App({ analyticsReporting: false });
  const config = getEnvironmentConfig('staging', 'launch-lean-v1', 'disabled-v1');
  const stage = new MxMedEnvironmentStage(app, 'RegistryDisabledIntegration', { config });
  const workloadStacks = stage.node.children.filter((child) => Stack.isStack(child));

  expect(stage.registryStack).toBeUndefined();
  expect(workloadStacks).toHaveLength(8);
  expect(Object.keys(resources(stage.computeStack))).toHaveLength(0);
});
