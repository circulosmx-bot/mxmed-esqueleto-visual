import { App, Stack } from 'aws-cdk-lib';
import { Template } from 'aws-cdk-lib/assertions';

import type { MxMedEnvironmentConfig } from '../lib/config/environment-config';
import { PRODUCTION_CONFIG, STAGING_CONFIG } from '../lib/config/environments';
import { MxMedEmailStage } from '../lib/stages/mxmed-email-stage';
import { MxMedEnvironmentStage } from '../lib/stages/mxmed-environment-stage';

function createStages(config: MxMedEnvironmentConfig): {
  readonly environment: MxMedEnvironmentStage;
  readonly email: MxMedEmailStage;
} {
  const app = new App({ analyticsReporting: false });
  const suffix = config.environmentName === 'staging' ? 'Staging' : 'Production';
  return {
    environment: new MxMedEnvironmentStage(app, `MxMed${suffix}`, { config }),
    email: new MxMedEmailStage(app, `MxMed${suffix}Email`, { config }),
  };
}

function directDependencyNames(stack: Stack): string[] {
  return stack.dependencies.map((dependency) => dependency.stackName).sort();
}

function expectNoDependencyCycle(stacks: readonly Stack[]): void {
  const stackSet = new Set(stacks);
  const visiting = new Set<Stack>();
  const visited = new Set<Stack>();

  const visit = (stack: Stack): void => {
    if (visiting.has(stack)) {
      throw new Error('dependency-cycle');
    }
    if (visited.has(stack)) {
      return;
    }
    visiting.add(stack);
    for (const dependency of stack.dependencies) {
      if (stackSet.has(dependency)) {
        visit(dependency);
      }
    }
    visiting.delete(stack);
    visited.add(stack);
  };

  for (const stack of stacks) {
    visit(stack);
  }
}

describe.each([
  ['staging', STAGING_CONFIG],
  ['production', PRODUCTION_CONFIG],
] as const)('%s stage topology', (_name, config) => {
  test('creates ten workload stacks and a separate email stack', () => {
    const { environment, email } = createStages(config);
    const workloadStacks = environment.node.children.filter((child) => Stack.isStack(child));
    const emailStacks = email.node.children.filter((child) => Stack.isStack(child));

    expect(workloadStacks).toHaveLength(10);
    expect(emailStacks).toEqual([email.emailStack]);
    expect(workloadStacks).not.toContain(email.emailStack);
  });

  test('uses the contracted primary and email regions', () => {
    const { environment, email } = createStages(config);
    expect(environment.networkStack.region).toBe('mx-central-1');
    expect(email.emailStack.region).toBe('us-east-1');
  });

  test('uses stable conceptual stack names', () => {
    const { environment, email } = createStages(config);
    expect(environment.networkStack.stackName).toBe(`mxmed-${config.environmentCode}-network`);
    expect(environment.operationsStack.stackName).toBe(
      `mxmed-${config.environmentCode}-operations`,
    );
    expect(email.emailStack.stackName).toBe(`mxmed-${config.environmentCode}-email`);
  });

  test('contains resources only in the typed foundation stack instances', () => {
    const { environment, email } = createStages(config);
    const stacks = [
      ...environment.node.children.filter((child): child is Stack => Stack.isStack(child)),
      email.emailStack,
    ];
    for (const stack of stacks) {
      const rendered = Template.fromStack(stack).toJSON() as unknown as {
        Resources?: Record<string, unknown>;
      };
      const resources = rendered.Resources ?? {};
      if (stack === environment.networkStack) {
        expect(Object.keys(resources).length).toBeGreaterThan(0);
        expect(environment.networkStack.publicIngressSubnets).toHaveLength(2);
        expect(environment.networkStack.privateAppSubnets).toHaveLength(2);
        expect(environment.networkStack.privateEndpointSubnets).toHaveLength(2);
        expect(environment.networkStack.isolatedDataSubnets).toHaveLength(2);
      } else if (stack === environment.securityStack) {
        expect(Object.keys(resources).length).toBeGreaterThan(0);
        expect(environment.securityStack.applicationDataKey).toBeDefined();
        expect(environment.securityStack.secretsKey).toBeDefined();
        expect(environment.securityStack.auditKey).toBeDefined();
        expect(environment.securityStack.backupKey).toBeDefined();
        expect(environment.securityStack.managementTrail).toBeDefined();
      } else if (stack === environment.dataStack) {
        expect(Object.keys(resources)).toHaveLength(4);
        expect(environment.dataStack.databaseInstance).toBeDefined();
        expect(environment.dataStack.parameterGroup).toBeDefined();
        expect(environment.dataStack.subnetGroup).toBeDefined();
        expect(environment.dataStack.monitoringRole).toBeDefined();
      } else if (stack === environment.storageStack) {
        expect(Object.keys(resources)).toHaveLength(8);
        expect(environment.storageStack.publicMediaBucket).toBeDefined();
        expect(environment.storageStack.privateDocumentsBucket).toBeDefined();
        expect(environment.storageStack.clinicalRecordsBucket).toBeDefined();
        expect(environment.storageStack.uploadQuarantineBucket).toBeDefined();
      } else if (stack === environment.sessionStack) {
        expect(Object.keys(resources)).toHaveLength(7);
        expect(environment.sessionStack.replicationGroup).toBeDefined();
        expect(environment.sessionStack.subnetGroup).toBeDefined();
        expect(environment.sessionStack.parameterGroup).toBeDefined();
        expect(environment.sessionStack.applicationUser).toBeDefined();
        expect(environment.sessionStack.defaultDisabledUser).toBeDefined();
        expect(environment.sessionStack.userGroup).toBeDefined();
        expect(environment.sessionStack.authSecret).toBeDefined();
      } else {
        expect(Object.keys(resources)).toHaveLength(0);
      }
    }
  });
});

describe('workload dependencies', () => {
  test('matches the PP249 dependency contract without cycles', () => {
    const { environment } = createStages(PRODUCTION_CONFIG);
    const stacks = environment.node.children.filter((child): child is Stack =>
      Stack.isStack(child),
    );

    expect(directDependencyNames(environment.dataStack)).toEqual(
      ['mxmed-prd-network', 'mxmed-prd-security'].sort(),
    );
    expect(directDependencyNames(environment.storageStack)).toEqual(['mxmed-prd-security']);
    expect(directDependencyNames(environment.sessionStack)).toEqual(
      ['mxmed-prd-network', 'mxmed-prd-security'].sort(),
    );
    expect(directDependencyNames(environment.computeStack)).toEqual(
      [
        'mxmed-prd-network',
        'mxmed-prd-security',
        'mxmed-prd-data',
        'mxmed-prd-storage',
        'mxmed-prd-session',
      ].sort(),
    );
    expect(directDependencyNames(environment.edgeStack)).toEqual(
      ['mxmed-prd-compute', 'mxmed-prd-security', 'mxmed-prd-storage'].sort(),
    );
    expect(directDependencyNames(environment.jobsStack)).toEqual(
      ['mxmed-prd-compute', 'mxmed-prd-security', 'mxmed-prd-storage'].sort(),
    );
    expect(directDependencyNames(environment.backupStack)).toEqual(
      ['mxmed-prd-data', 'mxmed-prd-storage', 'mxmed-prd-security'].sort(),
    );
    expect(() => {
      expectNoDependencyCycle(stacks);
    }).not.toThrow();
  });
});
