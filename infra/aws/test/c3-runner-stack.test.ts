import { App, Stack } from 'aws-cdk-lib';
import { Template } from 'aws-cdk-lib/assertions';

import {
  MXMED_C3_EXPECTED_RESOURCE_TYPE_COUNTS,
  expectedC3ResourceCount,
} from '../lib/constructs/c3-runner-contract';
import { getEnvironmentConfig } from '../lib/config/environments';
import { MxMedC3EphemeralStage } from '../lib/stages/mxmed-c3-ephemeral-stage';

interface Resource {
  readonly Type: string;
  readonly Properties?: Readonly<Record<string, unknown>>;
}

function stage(): MxMedC3EphemeralStage {
  return new MxMedC3EphemeralStage(new App({ analyticsReporting: false }), 'C3Fixture', {
    config: getEnvironmentConfig('staging', 'launch-lean-v1', 'registry-only-v1'),
    account: '875691018466',
  });
}

function resources(stack: Stack): readonly Resource[] {
  const template = Template.fromStack(stack).toJSON() as { Resources?: Record<string, Resource> };
  return Object.values(template.Resources ?? {});
}

describe('C3 ephemeral runner graph', () => {
  const fixture = stage();
  const runner = resources(fixture.runnerStack);
  const runnerOfType = (type: string) => runner.filter((resource) => resource.Type === type);

  test('contains only the six approved stacks and excludes broad application stacks', () => {
    const names = fixture.node.children
      .filter((child): child is Stack => Stack.isStack(child))
      .map((stack) => stack.stackName);
    expect(names).toEqual([
      'mxmed-stg-network',
      'mxmed-stg-security',
      'mxmed-stg-session',
      'mxmed-stg-registry',
      'mxmed-stg-c3-runner',
      'mxmed-stg-c3-janitor',
    ]);
    expect(names).not.toEqual(expect.arrayContaining(['mxmed-stg-data', 'mxmed-stg-storage']));
  });

  test('synthesizes the exact reviewed 107-resource manifest', () => {
    const all = fixture.node.children
      .filter((child): child is Stack => Stack.isStack(child))
      .flatMap((stack) => resources(stack));
    const counts = Object.fromEntries(
      [...new Set(all.map((resource) => resource.Type))]
        .sort()
        .map((type) => [type, all.filter((resource) => resource.Type === type).length]),
    );
    expect(all).toHaveLength(expectedC3ResourceCount());
    expect(counts).toEqual(MXMED_C3_EXPECTED_RESOURCE_TYPE_COUNTS);
    expect(all.filter((resource) => resource.Type === 'AWS::RDS::DBInstance')).toHaveLength(0);
    expect(all.filter((resource) => resource.Type === 'AWS::ECS::Service')).toHaveLength(0);
  });

  test('emits bootstrap-independent deployment templates only for the C3 graph', () => {
    for (const stack of fixture.node.children.filter((child): child is Stack =>
      Stack.isStack(child),
    )) {
      const template = Template.fromStack(stack).toJSON() as {
        readonly Parameters?: Readonly<Record<string, unknown>>;
        readonly Rules?: Readonly<Record<string, unknown>>;
      };
      expect(template.Parameters?.BootstrapVersion).toBeUndefined();
      expect(template.Rules?.CheckBootstrapVersion).toBeUndefined();
      expect(JSON.stringify(template)).not.toMatch(/hnb659fds|\/cdk-bootstrap\/|CDKToolkit/);
    }
  });

  test('gives only the C3 staging audit bucket its deterministic physical name', () => {
    const security = Template.fromStack(fixture.securityStack).toJSON() as {
      readonly Resources: Readonly<Record<string, Resource>>;
    };
    const buckets = Object.values(security.Resources).filter(
      (resource) => resource.Type === 'AWS::S3::Bucket',
    );
    expect(buckets).toHaveLength(1);
    expect(buckets[0]?.Properties?.BucketName).toBe('mxmed-stg-audit-875691018466-mx-central-1');
  });

  test('uses one digest-pinned one-shot Fargate task at 0.25 vCPU and 512 MiB', () => {
    expect(runner).toHaveLength(7);
    expect(runnerOfType('AWS::ECS::Cluster')).toHaveLength(1);
    expect(runnerOfType('AWS::ECS::TaskDefinition')).toHaveLength(1);
    expect(runnerOfType('AWS::ECS::Service')).toHaveLength(0);
    const task = runnerOfType('AWS::ECS::TaskDefinition')[0];
    expect(task?.Properties).toMatchObject({
      Cpu: '256',
      Memory: '512',
      NetworkMode: 'awsvpc',
      RequiresCompatibilities: ['FARGATE'],
    });
    const serialized = JSON.stringify(task);
    expect(serialized).toContain('RunnerImageDigest');
    expect(serialized).toContain('@');
    expect(serialized).not.toMatch(/:latest|DesiredCount|PortMappings/);
    expect(serialized).toContain('readonlyRootFilesystem'.replace(/^r/, 'R'));
    expect(serialized).toContain('C3_TASK_TIMEOUT_SECONDS');
    expect(serialized).toContain('900');
  });

  test('limits the task role to the exact staging session secret and its KMS context', () => {
    const policies = JSON.stringify(runnerOfType('AWS::IAM::Policy'));
    const session = JSON.stringify(Template.fromStack(fixture.sessionStack).toJSON());
    expect(session).toContain('/mxmed/staging/application/session-store-auth');
    expect(policies).toContain('SessionAuthSecret');
    expect(policies).toContain('kms:EncryptionContext:SecretARN');
    expect(policies).not.toMatch(/\/mxmed\/production|stripe|providers\/ai|database/i);
    const getSecretStatements = runnerOfType('AWS::IAM::Policy').filter((resource) =>
      JSON.stringify(resource).includes('secretsmanager:GetSecretValue'),
    );
    expect(getSecretStatements).toHaveLength(1);
    expect(JSON.stringify(getSecretStatements)).not.toContain('"Resource":"*"');
  });

  test('publishes private subnet and reused application security-group execution inputs', () => {
    const template = Template.fromStack(fixture.runnerStack).toJSON();
    const outputs = JSON.stringify(template.Outputs);
    expect(outputs).toContain('PrivateAppSubnetIds');
    expect(outputs).toContain('ApplicationSecurityGroupId');
    expect(outputs).not.toMatch(/PublicSubnet|AssignPublicIp|ENABLED/);
  });

  test('tags every taggable foundation resource with the sealed run and expiry parameters', () => {
    for (const foundation of [
      fixture.networkStack,
      fixture.securityStack,
      fixture.sessionStack,
      fixture.registryStack,
    ]) {
      const template = Template.fromStack(foundation).toJSON() as {
        readonly Parameters?: Readonly<Record<string, unknown>>;
        readonly Resources?: Readonly<Record<string, unknown>>;
      };
      expect(Object.keys(template.Parameters ?? {})).toEqual(
        expect.arrayContaining(['RunId', 'ExpiresAtUtc']),
      );
      const serialized = JSON.stringify(template.Resources);
      expect(serialized).toContain('RunId');
      expect(serialized).toContain('ExpiresAt');
      expect(serialized).toContain('"Phase","Value":"C3"');
    }
  });
});
