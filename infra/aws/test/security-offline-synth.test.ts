import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join } from 'node:path';

import { App, Stack } from 'aws-cdk-lib';
import { Template } from 'aws-cdk-lib/assertions';

import type { MxMedEnvironmentConfig } from '../lib/config/environment-config';
import { PRODUCTION_CONFIG, STAGING_CONFIG } from '../lib/config/environments';
import { MxMedEnvironmentStage } from '../lib/stages/mxmed-environment-stage';
import { renderSecurity, resourcesOfType } from './security-test-helpers';

function renderEnvironment(config: MxMedEnvironmentConfig): Record<string, unknown> {
  const app = new App({ analyticsReporting: false });
  const suffix = config.environmentName === 'staging' ? 'Staging' : 'Production';
  const stage = new MxMedEnvironmentStage(app, `MxMed${suffix}`, { config });
  const stacks = stage.node.children.filter((child): child is Stack => Stack.isStack(child));
  return Object.fromEntries(
    stacks.map((stack) => [stack.stackName, Template.fromStack(stack).toJSON()]),
  );
}

function readSource(root: string): string {
  return readdirSync(root)
    .sort()
    .map((entry) => {
      const path = join(root, entry);
      return statSync(path).isDirectory() ? readSource(path) : readFileSync(path, 'utf8');
    })
    .join('\n');
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
  for (const stack of stacks) visit(stack);
}

describe('security offline synthesis', () => {
  test('SEC-IMP-095 synthesizes staging without an account', () => {
    const previous = process.env.CDK_DEFAULT_ACCOUNT;
    delete process.env.CDK_DEFAULT_ACCOUNT;
    try {
      const templates = renderEnvironment(STAGING_CONFIG);
      expect(Object.keys(templates)).toHaveLength(9);
      expect(
        resourcesOfType(renderSecurity(STAGING_CONFIG).resources, 'AWS::KMS::Key'),
      ).toHaveLength(4);
    } finally {
      if (previous === undefined) delete process.env.CDK_DEFAULT_ACCOUNT;
      else process.env.CDK_DEFAULT_ACCOUNT = previous;
    }
  });

  test('SEC-IMP-096 synthesizes production without an account', () => {
    const previous = process.env.CDK_DEFAULT_ACCOUNT;
    delete process.env.CDK_DEFAULT_ACCOUNT;
    try {
      const templates = renderEnvironment(PRODUCTION_CONFIG);
      expect(Object.keys(templates)).toHaveLength(9);
      expect(
        resourcesOfType(renderSecurity(PRODUCTION_CONFIG).resources, 'AWS::KMS::Key'),
      ).toHaveLength(4);
    } finally {
      if (previous === undefined) delete process.env.CDK_DEFAULT_ACCOUNT;
      else process.env.CDK_DEFAULT_ACCOUNT = previous;
    }
  });

  test('SEC-IMP-097 persists no real AWS account identifier', () => {
    const templates = JSON.stringify([
      renderEnvironment(STAGING_CONFIG),
      renderEnvironment(PRODUCTION_CONFIG),
    ]);
    expect(templates).not.toMatch(/\b\d{12}\b/);
    expect(templates).toContain('AWS::AccountId');
  });

  test('SEC-IMP-098 uses no account or network lookup', () => {
    const source = readSource(join(process.cwd(), 'lib'));
    expect(source).not.toMatch(
      /Vpc\.fromLookup|HostedZone\.fromLookup|ContextProvider|AwsCustomResource/,
    );
  });

  test('SEC-IMP-099 uses no Docker or remote asset mechanism', () => {
    const source = readSource(join(process.cwd(), 'lib'));
    expect(source).not.toMatch(/DockerImageAsset|DockerImage|BundlingOptions|fromAsset/);
  });

  test('SEC-IMP-100 produces deterministic security templates', () => {
    expect(renderSecurity(STAGING_CONFIG).template).toEqual(
      renderSecurity(STAGING_CONFIG).template,
    );
    expect(renderSecurity(PRODUCTION_CONFIG).template).toEqual(
      renderSecurity(PRODUCTION_CONFIG).template,
    );
  });

  test('SEC-IMP-101 contains no plaintext credential', () => {
    const templates = JSON.stringify([
      renderSecurity(STAGING_CONFIG).template,
      renderSecurity(PRODUCTION_CONFIG).template,
    ]);
    expect(templates).not.toMatch(
      /AKIA|ASIA|(?:sk|rk)_(?:live|test)|whsec_|BEGIN PRIVATE KEY|"SecretString":/,
    );
  });

  test('SEC-IMP-102 contains no clinical data or clinical resource', () => {
    const templates = JSON.stringify([
      renderSecurity(STAGING_CONFIG).template,
      renderSecurity(PRODUCTION_CONFIG).template,
    ]);
    expect(templates).not.toMatch(/diagnosis|prescription|medical-record|clinical_upload/i);
    expect(
      resourcesOfType(renderSecurity(PRODUCTION_CONFIG).resources, 'AWS::RDS::DBInstance'),
    ).toEqual([]);
  });

  test('SEC-IMP-103 has no deploy or bootstrap script', () => {
    const packageJson = JSON.parse(readFileSync(join(process.cwd(), 'package.json'), 'utf8')) as {
      scripts?: Record<string, string>;
    };
    expect(packageJson.scripts?.deploy).toBeUndefined();
    expect(packageJson.scripts?.bootstrap).toBeUndefined();
  });

  test('SEC-IMP-104 has no stack or CloudFormation resource cycle', () => {
    for (const config of [STAGING_CONFIG, PRODUCTION_CONFIG]) {
      const app = new App({ analyticsReporting: false });
      const suffix = config.environmentName === 'staging' ? 'Staging' : 'Production';
      const stage = new MxMedEnvironmentStage(app, `MxMed${suffix}`, { config });
      const stacks = stage.node.children.filter((child): child is Stack => Stack.isStack(child));
      expect(() => {
        expectAcyclic(stacks);
      }).not.toThrow();
      expect(() => {
        Template.fromStack(stage.securityStack);
      }).not.toThrow();
      expect(stage.securityStack.dependencies).toEqual([]);
    }
  });
});
