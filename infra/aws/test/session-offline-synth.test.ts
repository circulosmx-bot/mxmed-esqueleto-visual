import { readFileSync } from 'node:fs';
import { join } from 'node:path';

import type { Stack } from 'aws-cdk-lib';

import { PRODUCTION_CONFIG, STAGING_CONFIG } from '../lib/config/environments';
import { renderSession } from './session-test-helpers';

const staging = renderSession(STAGING_CONFIG);
const production = renderSession(PRODUCTION_CONFIG);

function assertAcyclic(stacks: readonly Stack[]): void {
  const stackSet = new Set(stacks);
  const visiting = new Set<Stack>();
  const visited = new Set<Stack>();
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

describe('session offline synthesis', () => {
  test('SESSION-IMP-128 synthesizes staging', () => {
    expect(Object.keys(staging.resources)).toHaveLength(7);
  });
  test('SESSION-IMP-129 synthesizes production', () => {
    expect(Object.keys(production.resources)).toHaveLength(7);
  });
  test('SESSION-IMP-130 performs no context lookup', () => {
    for (const template of [staging.template, production.template]) {
      expect(JSON.stringify(template)).not.toMatch(
        /availability-zones:|ssm:account=|vpc-provider:/i,
      );
    }
  });
  test('SESSION-IMP-131 contains no literal account ID or real ARN', () => {
    for (const template of [staging.template, production.template]) {
      const serialized = JSON.stringify(template);
      expect(serialized).not.toMatch(/\b\d{12}\b/);
      expect(serialized).not.toMatch(/arn:aws[a-z-]*:/i);
    }
  });
  test('SESSION-IMP-132 contains no secret value', () => {
    const serialized = JSON.stringify([staging.template, production.template]);
    expect(serialized).not.toMatch(/AKIA[0-9A-Z]{16}|sk_(?:live|test)_[A-Za-z0-9]{12,}/);
    expect(serialized).toContain('resolve:secretsmanager');
  });
  test('SESSION-IMP-133 produces deterministic templates', () => {
    expect(renderSession(STAGING_CONFIG).template).toEqual(staging.template);
    expect(renderSession(PRODUCTION_CONFIG).template).toEqual(production.template);
  });
  test('SESSION-IMP-134 defines no deploy or bootstrap script', () => {
    const packageJson = JSON.parse(readFileSync(join(__dirname, '..', 'package.json'), 'utf8')) as {
      scripts?: Readonly<Record<string, string>>;
    };
    expect(packageJson.scripts).not.toHaveProperty('deploy');
    expect(packageJson.scripts).not.toHaveProperty('bootstrap');
  });
  test('SESSION-IMP-135 keeps the workload graph acyclic', () => {
    const stacks = production.stage.node.children.filter(
      (node): node is Stack => 'dependencies' in node,
    );
    expect(() => {
      assertAcyclic(stacks);
    }).not.toThrow();
  });
});
