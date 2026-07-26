import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

import { App, Stack } from 'aws-cdk-lib';
import { Annotations as AssertionAnnotations, Match, Template } from 'aws-cdk-lib/assertions';
import { CfnRepository } from 'aws-cdk-lib/aws-ecr';
import { CfnService, CfnTaskDefinition } from 'aws-cdk-lib/aws-ecs';
import type { IConstruct } from 'constructs';

import {
  capabilityIncludesAi,
  capabilityIncludesClinical,
  capabilityIncludesPaid,
  computeCreatesRegistry,
  computeCreatesService,
  registryFoundationIsEnabled,
  computeCreatesTasks,
  computeEcrRetention,
  MXMED_COMPUTE_ACTIVATION_MODES,
  MXMED_COMPUTE_FOUNDATION_IMPLEMENTATION_CONTRACT,
  MXMED_COMPUTE_RUNTIME_CONTRACT,
  MXMED_RUNTIME_CAPABILITY_PROFILES,
  parseComputeActivationMode,
  parseRuntimeCapabilityProfile,
  resolveComputeControls,
} from '../lib/config/compute-config';
import type {
  MxMedComputeActivationMode,
  MxMedDeploymentProfile,
  MxMedEnvironmentName,
  MxMedRuntimeCapabilityProfile,
} from '../lib/config/environment-config';
import { getEnvironmentConfig } from '../lib/config/environments';
import { MxMedEnvironmentStage } from '../lib/stages/mxmed-environment-stage';
import {
  containerByName,
  namedEntries,
  renderCompute,
  requireOne,
  resourceProperties,
  resourcesOfType,
  taskByContainerName,
} from './compute-test-helpers';

const ACTIVATION_CASES = [
  ['disabled-v1', undefined, 0, 0, 0, 0, 0, 0],
  ['registry-only-v1', undefined, 1, 0, 0, 0, 0, 0],
  ['tasks-ready-v1', 'directory-core-v1', 1, 1, 2, 2, 0, 0],
  ['service-enabled-v1', 'directory-core-v1', 1, 1, 2, 2, 1, 2],
] as const satisfies readonly (readonly [
  MxMedComputeActivationMode,
  MxMedRuntimeCapabilityProfile | undefined,
  number,
  number,
  number,
  number,
  number,
  number,
])[];

const PROFILE_CASES = [
  ['staging', 'launch-lean-v1', 512, 1024, 1, 1, 1, 7, 20],
  ['production', 'launch-lean-v1', 512, 1024, 1, 1, 2, 7, 20],
  ['production', 'production-standard-v1', 1024, 2048, 2, 2, 6, 14, 50],
  ['production', 'scale-ready-v1', 1024, 2048, 2, 2, 6, 14, 50],
] as const satisfies readonly (readonly [
  MxMedEnvironmentName,
  MxMedDeploymentProfile,
  number,
  number,
  number,
  number,
  number,
  number,
  number,
])[];

const CAPABILITY_CASES = [
  ['directory-core-v1', 5, false, false, false],
  ['paid-profile-v1', 7, true, false, false],
  ['clinical-v1', 7, true, true, false],
  ['professional-ai-v1', 8, true, true, true],
] as const satisfies readonly (readonly [
  MxMedRuntimeCapabilityProfile,
  number,
  boolean,
  boolean,
  boolean,
])[];

function entriesByName(value: unknown): Readonly<Record<string, unknown>>[] {
  return namedEntries(value).sort((left, right) =>
    String(left.Name).localeCompare(String(right.Name)),
  );
}

function names(value: unknown): string[] {
  return entriesByName(value).map((entry) => String(entry.Name));
}

function createMutableServiceStage(
  capability: MxMedRuntimeCapabilityProfile = 'directory-core-v1',
): MxMedEnvironmentStage {
  const app = new App({ analyticsReporting: false });
  const config = getEnvironmentConfig(
    'production',
    'launch-lean-v1',
    'service-enabled-v1',
    capability,
  );
  return new MxMedEnvironmentStage(app, 'NegativeComputeFixture', { config });
}

function expectComputeSynthRejected(stage: MxMedEnvironmentStage, errorCode: string): void {
  AssertionAnnotations.fromStack(stage.computeStack).hasError(
    '*',
    Match.stringLikeRegexp(errorCode),
  );
}

function findConstruct<T extends IConstruct>(
  stage: MxMedEnvironmentStage,
  resourceType: new (...args: never[]) => T,
  pathFragment: string,
): T {
  const found = stage.computeStack.node
    .findAll()
    .find(
      (candidate): candidate is T =>
        candidate instanceof resourceType && candidate.node.path.includes(pathFragment),
    );
  if (found === undefined) throw new Error(`missing-construct:${pathFragment}`);
  return found;
}

describe('MXMED_AWS_COMPUTE_FOUNDATION_IMPLEMENTATION_V1 controls', () => {
  test('publishes the versioned implementation contract', () => {
    expect(MXMED_COMPUTE_FOUNDATION_IMPLEMENTATION_CONTRACT).toBe(
      'MXMED_AWS_COMPUTE_FOUNDATION_IMPLEMENTATION_V1',
    );
  });

  test('defines the four exact activation modes', () => {
    expect(MXMED_COMPUTE_ACTIVATION_MODES).toEqual([
      'disabled-v1',
      'registry-only-v1',
      'tasks-ready-v1',
      'service-enabled-v1',
    ]);
  });

  test('defines the four exact runtime capability profiles', () => {
    expect(MXMED_RUNTIME_CAPABILITY_PROFILES).toEqual([
      'directory-core-v1',
      'paid-profile-v1',
      'clinical-v1',
      'professional-ai-v1',
    ]);
  });

  test('rejects an absent activation context', () => {
    expect(() => parseComputeActivationMode(undefined)).toThrow(
      'MXMED_CONFIG_INVALID:computeActivationMode',
    );
  });

  test('rejects an unknown activation context', () => {
    expect(() => parseComputeActivationMode('automatic-v1')).toThrow(
      'MXMED_CONFIG_INVALID:computeActivationMode',
    );
  });

  test('rejects an absent capability where tasks require one', () => {
    expect(() => resolveComputeControls('tasks-ready-v1', undefined)).toThrow(
      'MXMED_CONFIG_INVALID:runtimeCapabilityProfile',
    );
  });

  test('rejects an unknown capability', () => {
    expect(() => parseRuntimeCapabilityProfile('full-access-v1')).toThrow(
      'MXMED_CONFIG_INVALID:runtimeCapabilityProfile',
    );
  });

  test('registry-only deliberately ignores capability context', () => {
    expect(resolveComputeControls('registry-only-v1', 'professional-ai-v1')).toEqual({
      activationMode: 'registry-only-v1',
      runtimeCapabilityProfile: null,
    });
  });

  test('maps Registry foundation activation monotonically with backward compatibility', () => {
    expect(MXMED_COMPUTE_ACTIVATION_MODES.map(registryFoundationIsEnabled)).toEqual([
      false,
      true,
      true,
      true,
    ]);
    expect(MXMED_COMPUTE_ACTIVATION_MODES.map(computeCreatesRegistry)).toEqual([
      false,
      true,
      true,
      true,
    ]);
  });

  test('maps task creation monotonically across activation modes', () => {
    expect(MXMED_COMPUTE_ACTIVATION_MODES.map(computeCreatesTasks)).toEqual([
      false,
      false,
      true,
      true,
    ]);
  });

  test('maps service creation only to the final activation mode', () => {
    expect(MXMED_COMPUTE_ACTIVATION_MODES.map(computeCreatesService)).toEqual([
      false,
      false,
      false,
      true,
    ]);
  });

  test('keeps the exact PP262 runtime coordinates', () => {
    expect(MXMED_COMPUTE_RUNTIME_CONTRACT).toMatchObject({
      architecture: 'X86_64',
      platformVersion: '1.4.0',
      phpMajorVersion: '8.5',
      documentRoot: '/var/www/html',
      containerPort: 8080,
      healthPath: '/healthz',
      readinessPath: '/readyz',
    });
  });

  test('keeps readiness, migration and ECS Exec fail-closed', () => {
    expect(MXMED_COMPUTE_RUNTIME_CONTRACT).toMatchObject({
      ecsExecEnabled: false,
      readonlyRootFilesystem: true,
      migrationCommandMode: 'fail-closed-v1',
    });
  });

  test('derives lean ECR retention without a second profile catalog', () => {
    expect(computeEcrRetention('production', 'launch-lean-v1')).toEqual({
      untaggedDays: 7,
      maxImages: 20,
    });
  });

  test('derives standard ECR retention without a second profile catalog', () => {
    expect(computeEcrRetention('production', 'production-standard-v1')).toEqual({
      untaggedDays: 14,
      maxImages: 50,
    });
  });
});

describe.each(ACTIVATION_CASES)(
  '%s activation inventory',
  (mode, capability, repositories, clusters, tasks, logs, services, scalingPolicies) => {
    const fixture = renderCompute('production', 'launch-lean-v1', mode, capability);
    const expectedTaskResources = tasks > 0;

    test('preserves the explicit selected controls', () => {
      expect(fixture.stage.computeStack.activationMode).toBe(mode);
      expect(fixture.stage.computeStack.runtimeCapabilityProfile).toBe(capability ?? null);
    });

    test('keeps ECR out of Compute and creates the dedicated Registry inventory for the mode', () => {
      expect(resourcesOfType(fixture.compute, 'AWS::ECR::Repository')).toHaveLength(0);
      expect(fixture.stage.registryStack === undefined).toBe(repositories === 0);
      if (fixture.stage.registryStack !== undefined) {
        const registry = Template.fromStack(fixture.stage.registryStack).toJSON();
        expect(resourcesOfType(registry, 'AWS::ECR::Repository')).toHaveLength(repositories);
      }
    });

    test('creates the exact ECS cluster inventory for the mode', () => {
      expect(resourcesOfType(fixture.compute, 'AWS::ECS::Cluster')).toHaveLength(clusters);
    });

    test('creates the exact task definition inventory for the mode', () => {
      expect(resourcesOfType(fixture.compute, 'AWS::ECS::TaskDefinition')).toHaveLength(tasks);
    });

    test('creates the exact Compute log inventory for the mode', () => {
      expect(resourcesOfType(fixture.compute, 'AWS::Logs::LogGroup')).toHaveLength(logs);
    });

    test('creates a required digest parameter only for task-bearing modes', () => {
      const digest = fixture.compute.Parameters?.ApplicationImageDigest;
      expect(digest === undefined).toBe(!expectedTaskResources);
      if (digest !== undefined) {
        expect(digest).toMatchObject({
          Type: 'String',
          AllowedPattern: '^sha256:[0-9a-f]{64}$',
        });
        expect(digest).not.toHaveProperty('Default');
        expect(digest).not.toHaveProperty('NoEcho');
      }
    });

    test('creates service and scaling only in service-enabled mode', () => {
      expect(resourcesOfType(fixture.compute, 'AWS::ECS::Service')).toHaveLength(services);
      expect(
        resourcesOfType(fixture.compute, 'AWS::ApplicationAutoScaling::ScalingPolicy'),
      ).toHaveLength(scalingPolicies);
    });

    test('creates the application DB secret only for task-bearing modes', () => {
      expect(resourcesOfType(fixture.data, 'AWS::SecretsManager::Secret')).toHaveLength(
        expectedTaskResources ? 1 : 0,
      );
    });
  },
);

describe.each(PROFILE_CASES)(
  '%s / %s service profile',
  (environment, profile, cpu, memory, desired, min, max, untaggedDays, maxImages) => {
    const fixture = renderCompute(environment, profile, 'service-enabled-v1', 'directory-core-v1');
    const appTask = taskByContainerName(fixture.compute, 'app');
    const service = requireOne(fixture.compute, 'AWS::ECS::Service');
    const target = requireOne(fixture.compute, 'AWS::ApplicationAutoScaling::ScalableTarget');
    const registryStack = fixture.stage.registryStack;
    if (registryStack === undefined) throw new Error('missing-registry-stack');
    const registry = Template.fromStack(registryStack).toJSON();
    const repository = requireOne(registry, 'AWS::ECR::Repository');

    test('uses the catalog CPU exactly', () => {
      expect(resourceProperties(appTask).Cpu).toBe(String(cpu));
    });

    test('uses the catalog memory exactly', () => {
      expect(resourceProperties(appTask).Memory).toBe(String(memory));
    });

    test('uses the catalog desired count exactly', () => {
      expect(resourceProperties(service).DesiredCount).toBe(desired);
    });

    test('uses the catalog autoscaling range exactly', () => {
      expect(resourceProperties(target)).toMatchObject({ MinCapacity: min, MaxCapacity: max });
    });

    test('uses the profile-specific ECR lifecycle window', () => {
      const lifecycle = JSON.parse(
        String(
          (resourceProperties(repository).LifecyclePolicy as Record<string, unknown>)
            .LifecyclePolicyText,
        ),
      ) as { readonly rules: readonly { readonly selection: { readonly countNumber: number } }[] };
      expect(lifecycle.rules.map((rule) => rule.selection.countNumber)).toEqual([
        untaggedDays,
        maxImages,
      ]);
    });

    test('uses private networking and platform 1.4.0', () => {
      const rendered = JSON.stringify(resourceProperties(service));
      expect(rendered).toContain('DISABLED');
      expect(rendered.toLowerCase()).toContain('privateappsubnet');
      expect(resourceProperties(service).PlatformVersion).toBe('1.4.0');
    });

    test('uses the dedicated Registry tag contract', () => {
      const rendered = JSON.stringify(resourceProperties(repository).Tags);
      expect(rendered).toContain('"Key":"Component","Value":"registry"');
      expect(rendered).toContain('service-enabled-v1');
      expect(rendered).toContain('directory-core-v1');
      expect(rendered).toContain('required');
      expect(rendered).toContain(environment === 'staging' ? 'release-window-v1' : 'always-on');
      expect(rendered).not.toContain('always-on-approved');
    });

    test('contains no edge resource in the Compute template', () => {
      for (const forbidden of [
        'AWS::ElasticLoadBalancingV2::LoadBalancer',
        'AWS::ElasticLoadBalancingV2::TargetGroup',
        'AWS::CloudFront::Distribution',
        'AWS::WAFv2::WebACL',
        'AWS::Route53::RecordSet',
      ]) {
        expect(resourcesOfType(fixture.compute, forbidden)).toHaveLength(0);
      }
    });
  },
);

describe.each(CAPABILITY_CASES)(
  '%s runtime capability',
  (capability, secretCount, paid, clinical, ai) => {
    const fixture = renderCompute('production', 'launch-lean-v1', 'service-enabled-v1', capability);
    const appTask = taskByContainerName(fixture.compute, 'app');
    const migrationTask = taskByContainerName(fixture.compute, 'migration');
    const app = containerByName(appTask, 'app');
    const migration = containerByName(migrationTask, 'migration');
    const appSecrets = names(app.Secrets);
    const appEnvironment = names(app.Environment);
    const policies = resourcesOfType(fixture.compute, 'AWS::IAM::Policy');
    const policyText = JSON.stringify(policies);

    test('injects the exact number of capability-scoped app secrets', () => {
      expect(appSecrets).toHaveLength(secretCount);
    });

    test('injects Stripe secrets only for paid-or-higher capabilities', () => {
      expect(appSecrets.includes('STRIPE_SECRET_KEY')).toBe(paid);
      expect(appSecrets.includes('STRIPE_WEBHOOK_SECRET')).toBe(paid);
    });

    test('injects AI only for professional-ai', () => {
      expect(appSecrets.includes('AI_API_KEY')).toBe(ai);
    });

    test('injects clinical bucket variables only for clinical-or-higher capabilities', () => {
      expect(appEnvironment.includes('PRIVATE_DOCUMENTS_BUCKET')).toBe(clinical);
      expect(appEnvironment.includes('CLINICAL_RECORDS_BUCKET')).toBe(clinical);
    });

    test('grants clinical object reads only for clinical-or-higher capabilities', () => {
      expect(policyText.includes('PrivateDocumentsBucket')).toBe(clinical);
      expect(policyText.includes('ClinicalRecordsBucket')).toBe(clinical);
    });

    test('never grants wildcard S3 actions', () => {
      expect(policyText).not.toMatch(/s3:\*/);
      expect(policyText).not.toContain('s3:ListAllMyBuckets');
      expect(policyText).not.toContain('s3:DeleteBucket');
    });

    test('never injects the RDS master credential in the app container', () => {
      expect(appSecrets).not.toContain('DB_MASTER_USERNAME');
      expect(appSecrets).not.toContain('DB_MASTER_PASSWORD');
    });

    test('keeps master and app credentials isolated to the migration container', () => {
      expect(names(migration.Secrets)).toEqual([
        'DB_MASTER_PASSWORD',
        'DB_MASTER_USERNAME',
        'DB_PASSWORD',
        'DB_USERNAME',
      ]);
    });

    test('publishes the explicit capability as a non-secret variable', () => {
      const capabilityEntry = entriesByName(app.Environment).find(
        (entry) => entry.Name === 'RUNTIME_CAPABILITY_PROFILE',
      );
      expect(capabilityEntry?.Value).toBe(capability);
    });

    test('keeps provider credentials out of the non-secret environment', () => {
      expect(appEnvironment).not.toContain('STRIPE_SECRET_KEY');
      expect(appEnvironment).not.toContain('STRIPE_WEBHOOK_SECRET');
      expect(appEnvironment).not.toContain('AI_API_KEY');
      expect(JSON.stringify(fixture.security)).not.toContain('EcsExecutionRoleDefaultPolicy');
      expect(fixture.serialized).not.toMatch(/sk_(?:live|test)|AKIA|ASIA/);
    });
  },
);

describe('task, service and data security contracts', () => {
  const fixture = renderCompute(
    'production',
    'launch-lean-v1',
    'service-enabled-v1',
    'directory-core-v1',
  );
  const appTask = taskByContainerName(fixture.compute, 'app');
  const migrationTask = taskByContainerName(fixture.compute, 'migration');
  const app = containerByName(appTask, 'app');
  const migration = containerByName(migrationTask, 'migration');
  const service = requireOne(fixture.compute, 'AWS::ECS::Service');
  const applicationSecret = requireOne(fixture.data, 'AWS::SecretsManager::Secret');

  test('uses Linux X86_64 Fargate awsvpc for the app task', () => {
    expect(resourceProperties(appTask)).toMatchObject({
      NetworkMode: 'awsvpc',
      RequiresCompatibilities: ['FARGATE'],
      RuntimePlatform: { CpuArchitecture: 'X86_64', OperatingSystemFamily: 'LINUX' },
    });
  });

  test('uses Linux X86_64 Fargate awsvpc for the migration task', () => {
    expect(resourceProperties(migrationTask)).toMatchObject({
      NetworkMode: 'awsvpc',
      RequiresCompatibilities: ['FARGATE'],
      RuntimePlatform: { CpuArchitecture: 'X86_64', OperatingSystemFamily: 'LINUX' },
    });
  });

  test('runs exactly one hardened app container', () => {
    expect(namedEntries(resourceProperties(appTask).ContainerDefinitions)).toHaveLength(1);
    expect(app).toMatchObject({
      Name: 'app',
      Essential: true,
      ReadonlyRootFilesystem: true,
      Privileged: false,
      Interactive: false,
      PseudoTerminal: false,
      User: 'www-data',
    });
  });

  test('drops every Linux capability and enables init', () => {
    expect(app.LinuxParameters).toEqual({
      Capabilities: { Drop: ['ALL'] },
      InitProcessEnabled: true,
    });
  });

  test('uses exactly the three contracted app ephemeral mounts', () => {
    expect(namedEntries(app.MountPoints)).toEqual([
      { ContainerPath: '/tmp', ReadOnly: false, SourceVolume: 'tmp' },
      { ContainerPath: '/var/run/apache2', ReadOnly: false, SourceVolume: 'apache-run' },
      { ContainerPath: '/var/lock/apache2', ReadOnly: false, SourceVolume: 'apache-lock' },
    ]);
  });

  test('exposes only the PP262 app port', () => {
    expect(namedEntries(app.PortMappings)).toEqual([{ ContainerPort: 8080, Protocol: 'tcp' }]);
    expect(JSON.stringify(app.PortMappings)).not.toContain('9000');
  });

  test('uses the dependency-free liveness health check', () => {
    expect(app.HealthCheck).toEqual({
      Command: [
        'CMD-SHELL',
        'curl --fail --silent --show-error http://127.0.0.1:8080/healthz >/dev/null || exit 1',
      ],
      Interval: 15,
      Retries: 3,
      StartPeriod: 60,
      Timeout: 5,
    });
  });

  test('uses one shared immutable digest parameter in both tasks', () => {
    const appImage = JSON.stringify(app.Image);
    const migrationImage = JSON.stringify(migration.Image);
    expect(appImage).toContain('ApplicationImageDigest');
    expect(appImage).toContain('@');
    expect(migrationImage).toBe(appImage);
    expect(appImage).not.toContain(':latest');
  });

  test('keeps migration fail-closed without SQL', () => {
    expect(migration.Command).toEqual([
      '/bin/sh',
      '-c',
      'echo "migration command is not configured" >&2; exit 78',
    ]);
    expect(JSON.stringify(migration.Command).toLowerCase()).not.toMatch(
      /select |insert |update |delete |alter |create table/,
    );
  });

  test('keeps migration outside any ECS service', () => {
    expect(migration).not.toHaveProperty('PortMappings');
    expect(resourcesOfType(fixture.compute, 'AWS::ECS::Service')).toHaveLength(1);
    expect(JSON.stringify(resourceProperties(service).TaskDefinition)).toContain(
      'ApplicationTaskDefinition',
    );
  });

  test('enables rollback circuit breaker with 100/200 deployment percentages', () => {
    expect(resourceProperties(service).DeploymentConfiguration).toMatchObject({
      DeploymentCircuitBreaker: { Enable: true, Rollback: true },
      MinimumHealthyPercent: 100,
      MaximumPercent: 200,
    });
  });

  test('disables ECS Exec and public IP', () => {
    expect(resourceProperties(service).EnableExecuteCommand).toBe(false);
    expect(JSON.stringify(resourceProperties(service).NetworkConfiguration)).toContain('DISABLED');
  });

  test('creates CPU target tracking at 60 percent with exact cooldowns', () => {
    const policies = resourcesOfType(
      fixture.compute,
      'AWS::ApplicationAutoScaling::ScalingPolicy',
    ).map(resourceProperties);
    const cpu = policies.find((policy) =>
      JSON.stringify(policy).includes('ECSServiceAverageCPUUtilization'),
    );
    expect(cpu).toMatchObject({
      TargetTrackingScalingPolicyConfiguration: {
        ScaleInCooldown: 300,
        ScaleOutCooldown: 60,
        TargetValue: 60,
      },
    });
  });

  test('creates memory target tracking at 70 percent with exact cooldowns', () => {
    const policies = resourcesOfType(
      fixture.compute,
      'AWS::ApplicationAutoScaling::ScalingPolicy',
    ).map(resourceProperties);
    const memory = policies.find((policy) =>
      JSON.stringify(policy).includes('ECSServiceAverageMemoryUtilization'),
    );
    expect(memory).toMatchObject({
      TargetTrackingScalingPolicyConfiguration: {
        ScaleInCooldown: 300,
        ScaleOutCooldown: 60,
        TargetValue: 70,
      },
    });
  });

  test('generates the application DB credential with no connection metadata', () => {
    const properties = resourceProperties(applicationSecret);
    expect(properties.Name).toBe('/mxmed/production/application/database-user');
    expect(properties.GenerateSecretString).toMatchObject({
      GenerateStringKey: 'password',
      IncludeSpace: false,
      PasswordLength: 64,
      SecretStringTemplate: '{"username":"mxmed_app"}',
    });
    expect(properties).not.toHaveProperty('SecretString');
    expect(JSON.stringify(properties)).not.toMatch(/DB_HOST|endpoint|databaseName|3306/);
  });

  test('encrypts and retains the application DB credential', () => {
    expect(JSON.stringify(resourceProperties(applicationSecret).KmsKeyId)).toContain('SecretsKey');
    expect(applicationSecret.DeletionPolicy).toBe('Retain');
    expect(applicationSecret.UpdateReplacePolicy).toBe('Retain');
  });

  test('retains both encrypted Compute log groups for 90 days', () => {
    for (const log of resourcesOfType(fixture.compute, 'AWS::Logs::LogGroup')) {
      expect(resourceProperties(log).RetentionInDays).toBe(90);
      expect(JSON.stringify(resourceProperties(log).KmsKeyId)).toContain('AuditKey');
      expect(log.DeletionPolicy).toBe('Retain');
      expect(log.UpdateReplacePolicy).toBe('Retain');
    }
  });
});

describe('ComputeFoundationAspect fail-closed mutations', () => {
  test('accepts the unmodified service-enabled contract without Compute errors', () => {
    const stage = createMutableServiceStage();
    AssertionAnnotations.fromStack(stage.computeStack).hasNoError(
      '*',
      Match.stringLikeRegexp('MXMED_COMPUTE_'),
    );
  });

  test('rejects ARM64 drift', () => {
    const stage = createMutableServiceStage();
    const task = findConstruct(stage, CfnTaskDefinition, 'ApplicationTaskDefinition/Resource');
    task.runtimePlatform = { cpuArchitecture: 'ARM64', operatingSystemFamily: 'LINUX' };
    expectComputeSynthRejected(stage, 'MXMED_COMPUTE_TASK_RUNTIME_INVALID');
  });

  test('rejects any ECR repository placed back inside Compute', () => {
    const stage = createMutableServiceStage();
    new CfnRepository(stage.computeStack, 'ForbiddenSyntheticRepository', {
      repositoryName: 'synthetic-forbidden',
    });
    expectComputeSynthRejected(stage, 'MXMED_COMPUTE_REPOSITORY_FORBIDDEN');
  });

  test('rejects an image tag in place of a digest', () => {
    const stage = createMutableServiceStage();
    const task = findConstruct(stage, CfnTaskDefinition, 'ApplicationTaskDefinition/Resource');
    task.containerDefinitions = [
      {
        name: 'app',
        image: 'example.invalid/mxmed:mutable-tag',
        readonlyRootFilesystem: true,
        privileged: false,
        user: 'www-data',
        linuxParameters: { capabilities: { drop: ['ALL'] } },
      },
    ];
    expectComputeSynthRejected(stage, 'MXMED_COMPUTE_TASK_RUNTIME_INVALID');
  });

  test('rejects an AI secret in directory-core', () => {
    const stage = createMutableServiceStage();
    const task = findConstruct(stage, CfnTaskDefinition, 'ApplicationTaskDefinition/Resource');
    const resolved = Stack.of(task).resolve(task.containerDefinitions) as Readonly<
      Record<string, unknown>
    >[];
    const appContainer = resolved[0];
    if (appContainer === undefined) throw new Error('missing-negative-app-container');
    task.containerDefinitions = [
      {
        ...appContainer,
        secrets: [
          ...namedEntries(appContainer.secrets ?? appContainer.Secrets),
          { name: 'AI_API_KEY', valueFrom: 'synthetic-secret-arn' },
        ],
      } as unknown as CfnTaskDefinition.ContainerDefinitionProperty,
    ];
    expectComputeSynthRejected(stage, 'MXMED_COMPUTE_APP_CONTAINER_SECURITY_INVALID');
  });

  test('rejects a master DB secret in the app container', () => {
    const stage = createMutableServiceStage();
    const task = findConstruct(stage, CfnTaskDefinition, 'ApplicationTaskDefinition/Resource');
    const resolved = Stack.of(task).resolve(task.containerDefinitions) as Readonly<
      Record<string, unknown>
    >[];
    const appContainer = resolved[0];
    if (appContainer === undefined) throw new Error('missing-negative-app-container');
    task.containerDefinitions = [
      {
        ...appContainer,
        secrets: [
          ...namedEntries(appContainer.secrets ?? appContainer.Secrets),
          { name: 'DB_MASTER_PASSWORD', valueFrom: 'synthetic-secret-arn' },
        ],
      } as unknown as CfnTaskDefinition.ContainerDefinitionProperty,
    ];
    expectComputeSynthRejected(stage, 'MXMED_COMPUTE_APP_CONTAINER_SECURITY_INVALID');
  });

  test('rejects public IP assignment', () => {
    const stage = createMutableServiceStage();
    const service = findConstruct(stage, CfnService, 'ApplicationService/Service');
    service.networkConfiguration = {
      awsvpcConfiguration: {
        assignPublicIp: 'ENABLED',
        subnets: ['subnet-public-synthetic'],
      },
    };
    expectComputeSynthRejected(stage, 'MXMED_COMPUTE_SERVICE_SECURITY_INVALID');
  });

  test('rejects a service resource in registry-only mode', () => {
    const app = new App({ analyticsReporting: false });
    const config = getEnvironmentConfig('production', 'launch-lean-v1', 'registry-only-v1');
    const stage = new MxMedEnvironmentStage(app, 'RegistryNegativeFixture', { config });
    new CfnService(stage.computeStack, 'ForbiddenSyntheticService');
    expectComputeSynthRejected(stage, 'MXMED_COMPUTE_INVENTORY_INVALID');
  });

  test('rejects service desired count above the launch-lean contract', () => {
    const stage = createMutableServiceStage();
    const service = findConstruct(stage, CfnService, 'ApplicationService/Service');
    service.desiredCount = 2;
    expectComputeSynthRejected(stage, 'MXMED_COMPUTE_SERVICE_SECURITY_INVALID');
  });
});

const RUNTIME_EXPECTATIONS = [
  ['Dockerfile', 'ARG PHP_BASE_IMAGE'],
  ['Dockerfile', 'ARG COMPOSER_BASE_IMAGE'],
  ['Dockerfile', 'AS composer-source'],
  ['Dockerfile', 'AS extension-builder'],
  ['Dockerfile', 'AS runtime'],
  ['Dockerfile', '"8.5"'],
  ['Dockerfile', 'VERSION_CODENAME=bookworm'],
  ['Dockerfile', 'Apache/2\\.4'],
  ['Dockerfile', 'docker-php-ext-install'],
  ['Dockerfile', 'a2enmod rewrite'],
  ['Dockerfile', 'USER www-data'],
  ['Dockerfile', 'EXPOSE 8080'],
  ['apache/000-default.conf', 'DocumentRoot /var/www/html'],
  ['apache/ports.conf', 'Listen 8080'],
  ['apache/security.conf', 'ServerTokens Prod'],
  ['apache/security.conf', 'ServerSignature Off'],
  ['apache/security.conf', 'TraceEnable Off'],
  ['apache/mxmed-runtime.conf', 'Options -Indexes'],
  ['php/zz-mxmed-production.ini', 'display_errors = Off'],
  ['php/zz-mxmed-production.ini', 'session.save_handler = redis'],
  ['health/healthz.php', 'http_response_code(200)'],
  ['health/readyz.php', 'http_response_code(503)'],
  ['health/readyz.php', 'readiness_not_integrated'],
  ['README.md', 'not built, pulled, pushed, scanned, or deployed'],
] as const;

describe.each(RUNTIME_EXPECTATIONS)('runtime scaffold %s', (relativePath, expected) => {
  test(`contains ${expected}`, () => {
    const content = readFileSync(resolve(__dirname, '..', 'runtime', 'app', relativePath), 'utf8');
    expect(content).toContain(expected);
  });
});

describe('runtime scaffold denylist', () => {
  const dockerfile = readFileSync(resolve(__dirname, '..', 'runtime', 'app', 'Dockerfile'), 'utf8');
  const dockerignore = readFileSync(
    resolve(__dirname, '..', 'runtime', 'app', 'Dockerfile.dockerignore'),
    'utf8',
  );
  const healthz = readFileSync(
    resolve(__dirname, '..', 'runtime', 'app', 'health', 'healthz.php'),
    'utf8',
  );
  const readyz = readFileSync(
    resolve(__dirname, '..', 'runtime', 'app', 'health', 'readyz.php'),
    'utf8',
  );

  test('has no mutable base image defaults', () => {
    expect(dockerfile).not.toMatch(/ARG (?:PHP|COMPOSER)_BASE_IMAGE=/);
  });

  test('has no latest image reference', () => {
    expect(dockerfile).not.toContain(':latest');
  });

  test('has no Nginx or PHP-FPM primary process', () => {
    expect(dockerfile.toLowerCase()).not.toMatch(/nginx|php-fpm/);
  });

  test('has no secret material', () => {
    expect(dockerfile).not.toMatch(/AKIA|ASIA|sk_(?:live|test)|BEGIN PRIVATE KEY/);
  });

  test('excludes every high-risk build-context family', () => {
    for (const entry of [
      '.git',
      'docs',
      'tests',
      'logs',
      '*.sql',
      '*.env',
      'secrets',
      'uploads',
      'documents',
      'node_modules',
      'cache',
      'cdk.out',
    ]) {
      expect(dockerignore).toContain(entry);
    }
  });

  test('keeps healthz dependency-free', () => {
    expect(healthz).not.toMatch(/mysql|valkey|redis|stripe|session|s3|ai_/i);
  });

  test('keeps readyz fail-closed and sanitized', () => {
    expect(readyz).toContain('http_response_code(503)');
    expect(readyz).not.toContain('http_response_code(200)');
    expect(readyz).not.toMatch(/host|port|exception|trace|password/i);
  });
});

const SCRIPT_CASES = [
  ['synth:staging', 'computeActivationMode=disabled-v1'],
  ['synth:production:launch-lean', 'synth:production'],
  ['synth:production:standard', 'computeActivationMode=disabled-v1'],
  ['synth:production:scale-ready', 'computeActivationMode=disabled-v1'],
  ['synth:production:launch-lean:compute-registry', 'computeActivationMode=registry-only-v1'],
  ['synth:production:launch-lean:compute-tasks', 'computeActivationMode=tasks-ready-v1'],
  ['synth:production:launch-lean:compute-service', 'computeActivationMode=service-enabled-v1'],
  ['synth:production:standard:compute-service', 'deploymentProfile=production-standard-v1'],
  ['synth:production:scale-ready:compute-service', 'deploymentProfile=scale-ready-v1'],
  ['synth:staging:release-window:compute-service', 'runtimeCapabilityProfile=directory-core-v1'],
] as const;

describe.each(SCRIPT_CASES)('offline script %s', (scriptName, requiredFragment) => {
  test(`contains ${requiredFragment}`, () => {
    const packageJson = JSON.parse(
      readFileSync(resolve(__dirname, '..', 'package.json'), 'utf8'),
    ) as { readonly scripts: Readonly<Record<string, string>> };
    expect(packageJson.scripts[scriptName]).toContain(requiredFragment);
    expect(packageJson.scripts[scriptName]).not.toMatch(/\b(?:deploy|bootstrap|Docker)\b/i);
  });
});

test('all Compute and Registry templates are account-agnostic, lookup-free and credential-free', () => {
  for (const [mode, capability] of ACTIVATION_CASES) {
    const fixture = renderCompute('production', 'launch-lean-v1', mode, capability);
    const registry =
      fixture.stage.registryStack === undefined
        ? ''
        : JSON.stringify(Template.fromStack(fixture.stage.registryStack).toJSON());
    const serialized = `${fixture.serialized}${registry}`;
    expect(serialized).not.toMatch(/\b\d{12}\b/);
    expect(serialized).not.toMatch(/AKIA|ASIA|sk_(?:live|test)|BEGIN PRIVATE KEY/);
    expect(serialized).not.toContain('VpcLookup');
    expect(serialized).not.toContain('DockerImageAsset');
  }
});

test('the same explicit configuration resolves deterministically', () => {
  const first = getEnvironmentConfig(
    'production',
    'launch-lean-v1',
    'service-enabled-v1',
    'directory-core-v1',
  );
  const second = getEnvironmentConfig(
    'production',
    'launch-lean-v1',
    'service-enabled-v1',
    'directory-core-v1',
  );
  expect(second).toEqual(first);
});

test('capability hierarchy is explicit and monotonic', () => {
  expect(MXMED_RUNTIME_CAPABILITY_PROFILES.map(capabilityIncludesPaid)).toEqual([
    false,
    true,
    true,
    true,
  ]);
  expect(MXMED_RUNTIME_CAPABILITY_PROFILES.map(capabilityIncludesClinical)).toEqual([
    false,
    false,
    true,
    true,
  ]);
  expect(MXMED_RUNTIME_CAPABILITY_PROFILES.map(capabilityIncludesAi)).toEqual([
    false,
    false,
    false,
    true,
  ]);
});
