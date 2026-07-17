import { App, Stack } from 'aws-cdk-lib';

import { getEnvironmentConfig } from '../lib/config/environments';
import { MxMedEnvironmentStage } from '../lib/stages/mxmed-environment-stage';
import {
  costOnlyConfig,
  observabilityConfig,
  renderEnvironment,
  renderGlobal,
  serialized,
} from './operations-test-helpers';

function assertNoCycles(stacks: readonly Stack[]): void {
  const relevant = new Set(stacks);
  const visiting = new Set<Stack>();
  const visited = new Set<Stack>();
  const visit = (stack: Stack): void => {
    if (visiting.has(stack)) throw new Error('operations-dependency-cycle');
    if (visited.has(stack)) return;
    visiting.add(stack);
    for (const dependency of stack.dependencies) {
      if (relevant.has(dependency)) visit(dependency);
    }
    visiting.delete(stack);
    visited.add(stack);
  };
  for (const stack of stacks) visit(stack);
}

describe('Operations offline synthesis', () => {
  test('synthesizes disabled mode without an Operations stack', () => {
    const stage = new MxMedEnvironmentStage(new App(), 'DisabledOffline', {
      config: getEnvironmentConfig('production', 'launch-lean-v1'),
    });
    expect(stage.regionalOperationsStack).toBeUndefined();
  });

  test('synthesizes cost-only without an account', () => {
    expect(() => renderGlobal(costOnlyConfig())).not.toThrow();
  });

  test('synthesizes launch-lean Operations without an account', () => {
    expect(() => renderEnvironment(observabilityConfig())).not.toThrow();
  });

  test('synthesizes standard Operations without an account', () => {
    expect(() => renderEnvironment(observabilityConfig('production-standard-v1'))).not.toThrow();
  });

  test('synthesizes scale-ready Operations without an account', () => {
    expect(() => renderEnvironment(observabilityConfig('scale-ready-v1'))).not.toThrow();
  });

  test('synthesizes staging release-window Operations without an account', () => {
    expect(() => renderEnvironment(observabilityConfig('launch-lean-v1', 'staging'))).not.toThrow();
  });

  test('synthesizes the global Operations stage without an account', () => {
    expect(() => renderGlobal(observabilityConfig())).not.toThrow();
  });

  test('uses no context provider lookups', () => {
    const templates = [
      renderEnvironment(observabilityConfig()).operations,
      renderGlobal(observabilityConfig()).cost,
    ];
    expect(serialized(templates)).not.toMatch(/Custom::.*Lookup|ContextProvider|VpcLookup/i);
  });

  test('persists no literal AWS account identifiers', () => {
    const rendered = renderGlobal(observabilityConfig());
    expect(serialized([rendered.cost, rendered.edge, rendered.operations])).not.toMatch(
      /\b[0-9]{12}\b/,
    );
  });

  test('persists no secret values or personal notification targets', () => {
    const text = serialized([
      renderEnvironment(observabilityConfig()).operations,
      ...(() => {
        const rendered = renderGlobal(observabilityConfig());
        return [rendered.cost, rendered.edge, rendered.operations];
      })(),
    ]);
    expect(text).not.toMatch(/password\s*[=:]|client_secret\s*[=:]|@[A-Za-z]|mailto:/i);
  });

  test('renders deterministic Operations templates', () => {
    const first = serialized({
      regional: renderEnvironment(observabilityConfig()).operations,
      global: renderGlobal(observabilityConfig()).operations,
      cost: renderGlobal(costOnlyConfig()).cost,
    });
    const second = serialized({
      regional: renderEnvironment(observabilityConfig()).operations,
      global: renderGlobal(observabilityConfig()).operations,
      cost: renderGlobal(costOnlyConfig()).cost,
    });
    expect(first).toBe(second);
  });

  test('contains no deployment or destructive custom resource', () => {
    const text = serialized([
      renderEnvironment(observabilityConfig()).operations,
      ...(() => {
        const rendered = renderGlobal(observabilityConfig());
        return [rendered.cost, rendered.edge, rendered.operations];
      })(),
    ]);
    expect(text).not.toMatch(/AWS::CodeDeploy|Custom::.*(?:Delete|Deploy|Remediation)/i);
  });

  test('has no stack dependency cycles', () => {
    const app = new App();
    const environment = new MxMedEnvironmentStage(app, 'CycleAudit', {
      config: observabilityConfig(),
    });
    const stacks = environment.node.children.filter((child): child is Stack =>
      Stack.isStack(child),
    );
    expect(() => {
      assertNoCycles(stacks);
    }).not.toThrow();
  });
});
