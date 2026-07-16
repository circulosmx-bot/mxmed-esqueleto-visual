import { readFileSync } from 'node:fs';
import { join } from 'node:path';

import { App, Stack } from 'aws-cdk-lib';
import { Template } from 'aws-cdk-lib/assertions';

import { PRODUCTION_CONFIG, STAGING_CONFIG } from '../lib/config/environments';
import type { MxMedEnvironmentConfig } from '../lib/config/environment-config';
import { MxMedEnvironmentStage } from '../lib/stages/mxmed-environment-stage';
import { renderData } from './data-test-helpers';

function synthesize(config: MxMedEnvironmentConfig): Readonly<Record<string, unknown>> {
  return renderData(config).template;
}

function expectAcyclic(stacks: readonly Stack[]): void {
  const visiting = new Set<Stack>();
  const visited = new Set<Stack>();
  const visit = (stack: Stack): void => {
    if (visiting.has(stack)) throw new Error('dependency-cycle');
    if (visited.has(stack)) return;
    visiting.add(stack);
    for (const dependency of stack.dependencies) visit(dependency);
    visiting.delete(stack);
    visited.add(stack);
  };
  stacks.forEach(visit);
}

describe('data offline synthesis', () => {
  test('DATA-IMP-086 synthesizes staging offline', () => {
    expect(Object.keys(synthesize(STAGING_CONFIG))).toContain('Resources');
  });
  test('DATA-IMP-087 synthesizes production offline', () => {
    expect(Object.keys(synthesize(PRODUCTION_CONFIG))).toContain('Resources');
  });
  test('DATA-IMP-088 persists no real account ID', () => {
    expect(JSON.stringify([synthesize(STAGING_CONFIG), synthesize(PRODUCTION_CONFIG)])).not.toMatch(
      /\b\d{12}\b/,
    );
  });
  test('DATA-IMP-089 performs no context lookup', () => {
    const source = readFileSync(
      join(process.cwd(), 'lib', 'stacks', 'mxmed-data-stack.ts'),
      'utf8',
    );
    expect(source).not.toMatch(/fromLookup|ContextProvider|AwsCustomResource|@aws-sdk|aws-sdk/);
  });
  test('DATA-IMP-090 produces deterministic templates', () => {
    expect(synthesize(STAGING_CONFIG)).toEqual(synthesize(STAGING_CONFIG));
    expect(synthesize(PRODUCTION_CONFIG)).toEqual(synthesize(PRODUCTION_CONFIG));
  });
  test('DATA-IMP-091 persists no secret value', () => {
    const templates = JSON.stringify([synthesize(STAGING_CONFIG), synthesize(PRODUCTION_CONFIG)]);
    expect(templates).not.toMatch(
      /"MasterUserPassword":|SecretString|AKIA|ASIA|BEGIN PRIVATE KEY|whsec_/,
    );
  });
  test('DATA-IMP-092 defines no deploy or bootstrap script', () => {
    const packageJson = JSON.parse(readFileSync(join(process.cwd(), 'package.json'), 'utf8')) as {
      scripts?: Record<string, string>;
    };
    expect(packageJson.scripts?.deploy).toBeUndefined();
    expect(packageJson.scripts?.bootstrap).toBeUndefined();
  });
  test('DATA-IMP-093 keeps stack and resource dependencies acyclic', () => {
    const app = new App({ analyticsReporting: false });
    const stage = new MxMedEnvironmentStage(app, 'MxMedProductionDataCycles', {
      config: PRODUCTION_CONFIG,
    });
    const stacks = stage.node.children.filter((child): child is Stack => Stack.isStack(child));
    expect(() => {
      expectAcyclic(stacks);
    }).not.toThrow();
    expect(() => Template.fromStack(stage.dataStack)).not.toThrow();
  });
});
