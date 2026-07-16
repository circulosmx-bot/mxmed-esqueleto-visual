import { readFileSync } from 'node:fs';
import { join } from 'node:path';

import { App, Stack } from 'aws-cdk-lib';
import { Template } from 'aws-cdk-lib/assertions';

import type { MxMedEnvironmentConfig } from '../lib/config/environment-config';
import { PRODUCTION_CONFIG, STAGING_CONFIG } from '../lib/config/environments';
import { MxMedEnvironmentStage } from '../lib/stages/mxmed-environment-stage';
import { renderStorage, resourcesOfType } from './storage-test-helpers';

function synthesize(config: MxMedEnvironmentConfig): Readonly<Record<string, unknown>> {
  return renderStorage(config).template;
}

function expectAcyclic(stacks: readonly Stack[]): void {
  const visiting = new Set<Stack>();
  const visited = new Set<Stack>();
  const stackSet = new Set(stacks);
  const visit = (stack: Stack): void => {
    if (visiting.has(stack)) throw new Error('dependency-cycle');
    if (visited.has(stack)) return;
    visiting.add(stack);
    for (const dependency of stack.dependencies) {
      if (stackSet.has(dependency)) visit(dependency);
    }
    visiting.delete(stack);
    visited.add(stack);
  };
  stacks.forEach(visit);
}

describe('storage offline synthesis', () => {
  test('STORAGE-IMP-108 synthesizes staging offline', () => {
    const rendered = renderStorage(STAGING_CONFIG);
    expect(resourcesOfType(rendered.resources, 'AWS::S3::Bucket')).toHaveLength(4);
  });

  test('STORAGE-IMP-109 synthesizes production offline', () => {
    const rendered = renderStorage(PRODUCTION_CONFIG);
    expect(resourcesOfType(rendered.resources, 'AWS::S3::Bucket')).toHaveLength(4);
  });

  test('STORAGE-IMP-110 performs no lookup or AWS SDK call', () => {
    const source = [
      readFileSync(join(process.cwd(), 'lib', 'stacks', 'mxmed-storage-stack.ts'), 'utf8'),
      readFileSync(join(process.cwd(), 'lib', 'constructs', 'storage-contract.ts'), 'utf8'),
      readFileSync(join(process.cwd(), 'lib', 'aspects', 'storage-foundation-aspect.ts'), 'utf8'),
    ].join('\n');
    expect(source).not.toMatch(
      /fromLookup|ContextProvider|AwsCustomResource|@aws-sdk|aws-sdk|DockerImageAsset|fromAsset/,
    );
  });

  test('STORAGE-IMP-111 persists no AWS account or credential identifier', () => {
    const templates = JSON.stringify([synthesize(STAGING_CONFIG), synthesize(PRODUCTION_CONFIG)]);
    expect(templates).not.toMatch(/\b\d{12}\b|AKIA|ASIA|arn:aws:[^:]+:[^:]*:\d{12}:/);
  });

  test('STORAGE-IMP-112 persists no secret or clinical payload', () => {
    const templates = JSON.stringify([synthesize(STAGING_CONFIG), synthesize(PRODUCTION_CONFIG)]);
    expect(templates).not.toMatch(
      /BEGIN PRIVATE KEY|(?:sk|rk)_(?:live|test)|whsec_|SecretString|patient|diagnosis|prescription/i,
    );
  });

  test('STORAGE-IMP-113 produces deterministic templates', () => {
    expect(synthesize(STAGING_CONFIG)).toEqual(synthesize(STAGING_CONFIG));
    expect(synthesize(PRODUCTION_CONFIG)).toEqual(synthesize(PRODUCTION_CONFIG));
  });

  test('STORAGE-IMP-114 defines no deploy path or runtime processor', () => {
    const packageJson = JSON.parse(readFileSync(join(process.cwd(), 'package.json'), 'utf8')) as {
      scripts?: Record<string, string>;
    };
    expect(packageJson.scripts?.deploy).toBeUndefined();
    expect(packageJson.scripts?.bootstrap).toBeUndefined();
    const resourceTypes = Object.values(renderStorage(PRODUCTION_CONFIG).resources).map(
      (resource) => resource.Type,
    );
    expect(resourceTypes).not.toEqual(
      expect.arrayContaining([
        'AWS::Lambda::Function',
        'AWS::SQS::Queue',
        'AWS::Events::Rule',
        'AWS::ECS::TaskDefinition',
        'Custom::S3BucketNotifications',
      ]),
    );
  });

  test('STORAGE-IMP-115 keeps stack and CloudFormation dependencies acyclic', () => {
    const app = new App({ analyticsReporting: false });
    const stage = new MxMedEnvironmentStage(app, 'MxMedStorageCycleAudit', {
      config: PRODUCTION_CONFIG,
    });
    const stacks = stage.node.children.filter((child): child is Stack => Stack.isStack(child));
    expect(() => {
      expectAcyclic(stacks);
    }).not.toThrow();
    expect(() => Template.fromStack(stage.storageStack)).not.toThrow();
  });
});
