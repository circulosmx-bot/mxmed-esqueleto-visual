import { Annotations, CfnParameter, Stack } from 'aws-cdk-lib';
import type { IAspect } from 'aws-cdk-lib';
import { CfnScalableTarget, CfnScalingPolicy } from 'aws-cdk-lib/aws-applicationautoscaling';
import { CfnRepository } from 'aws-cdk-lib/aws-ecr';
import { CfnCluster, CfnService, CfnTaskDefinition } from 'aws-cdk-lib/aws-ecs';
import { CfnLoadBalancer, CfnTargetGroup } from 'aws-cdk-lib/aws-elasticloadbalancingv2';
import { CfnLogGroup } from 'aws-cdk-lib/aws-logs';
import type { IConstruct } from 'constructs';

import {
  computeCreatesService,
  computeCreatesTasks,
  capabilityIncludesAi,
  capabilityIncludesPaid,
} from '../config/compute-config';
import type { MxMedEnvironmentConfig } from '../config/environment-config';

const DIGEST_PATTERN = '^sha256:[0-9a-f]{64}$';

function text(value: unknown, node: IConstruct): string {
  return JSON.stringify(Stack.of(node).resolve(value));
}

function isApplicationContainer(value: unknown): boolean {
  return (
    typeof value === 'object' &&
    value !== null &&
    (('Name' in value && value.Name === 'app') || ('name' in value && value.name === 'app'))
  );
}

function field(
  value: Readonly<Record<string, unknown>>,
  cloudFormationName: string,
  propertyName: string,
): unknown {
  return value[cloudFormationName] ?? value[propertyName];
}

/** Fail-closed validation for profile-aware Compute resources. */
export class ComputeFoundationAspect implements IAspect {
  public constructor(private readonly config: MxMedEnvironmentConfig) {}

  public visit(node: IConstruct): void {
    const mode = this.config.computeActivationMode;
    if (node instanceof Stack) {
      this.validateInventory(node);
      return;
    }
    if (node instanceof CfnLoadBalancer || node instanceof CfnTargetGroup) {
      Annotations.of(node).addError('MXMED_COMPUTE_EDGE_RESOURCE_FORBIDDEN');
      return;
    }
    if (node instanceof CfnRepository) {
      Annotations.of(node).addError('MXMED_COMPUTE_REPOSITORY_FORBIDDEN');
      return;
    }
    if (node instanceof CfnParameter && node.node.path.includes('ApplicationImageDigest')) {
      if (!computeCreatesTasks(mode) || node.default !== undefined || node.noEcho) {
        Annotations.of(node).addError('MXMED_COMPUTE_DIGEST_PARAMETER_MODE_INVALID');
      }
      if (node.allowedPattern !== DIGEST_PATTERN) {
        Annotations.of(node).addError('MXMED_COMPUTE_DIGEST_PATTERN_INVALID');
      }
      return;
    }
    if (node instanceof CfnCluster) {
      if (!computeCreatesTasks(mode)) {
        Annotations.of(node).addError('MXMED_COMPUTE_CLUSTER_MODE_INVALID');
      }
      if (!text(node.clusterSettings, node).includes('containerInsights')) {
        Annotations.of(node).addError('MXMED_COMPUTE_CONTAINER_INSIGHTS_REQUIRED');
      }
      return;
    }
    if (node instanceof CfnLogGroup) {
      if (!computeCreatesTasks(mode)) {
        Annotations.of(node).addError('MXMED_COMPUTE_LOG_GROUP_MODE_INVALID');
      }
      if (
        node.kmsKeyId === undefined ||
        node.retentionInDays !== this.config.computeLogRetentionDays
      ) {
        Annotations.of(node).addError('MXMED_COMPUTE_LOG_GROUP_CONTRACT_INVALID');
      }
      return;
    }
    if (node instanceof CfnTaskDefinition) {
      this.validateTaskDefinition(node);
      return;
    }
    if (node instanceof CfnService) {
      if (!computeCreatesService(mode)) {
        Annotations.of(node).addError('MXMED_COMPUTE_SERVICE_MODE_INVALID');
      }
      const rendered = text(
        {
          networkConfiguration: node.networkConfiguration,
          loadBalancers: node.loadBalancers,
          deploymentConfiguration: node.deploymentConfiguration,
        },
        node,
      );
      const normalized = rendered.toLowerCase();
      if (
        (rendered.includes('ENABLED') && rendered.toLowerCase().includes('assignpublicip')) ||
        normalized.includes('publicingress') ||
        node.enableExecuteCommand === true ||
        Stack.of(node).resolve(node.loadBalancers) !== undefined ||
        node.platformVersion !== '1.4.0' ||
        node.desiredCount !== this.config.computeDesiredCount ||
        !normalized.includes('deploymentcircuitbreaker') ||
        !normalized.includes('"rollback":true') ||
        !normalized.includes('"minimumhealthypercent":100') ||
        !normalized.includes('"maximumpercent":200')
      ) {
        Annotations.of(node).addError('MXMED_COMPUTE_SERVICE_SECURITY_INVALID');
      }
      return;
    }
    if (node instanceof CfnScalableTarget) {
      if (!computeCreatesService(mode)) {
        Annotations.of(node).addError('MXMED_COMPUTE_SCALING_MODE_INVALID');
      }
      if (
        node.minCapacity !== this.config.computeMinCapacity ||
        node.maxCapacity !== this.config.computeMaxCapacity
      ) {
        Annotations.of(node).addError('MXMED_COMPUTE_SCALING_CAPACITY_INVALID');
      }
      return;
    }
    if (node instanceof CfnScalingPolicy) {
      if (!computeCreatesService(mode)) {
        Annotations.of(node).addError('MXMED_COMPUTE_SCALING_MODE_INVALID');
      }
      const rendered = text(node.targetTrackingScalingPolicyConfiguration, node);
      const expectedTarget = rendered.includes('ECSServiceAverageCPUUtilization')
        ? this.config.computeCpuTargetPercent
        : rendered.includes('ECSServiceAverageMemoryUtilization')
          ? this.config.computeMemoryTargetPercent
          : null;
      if (
        expectedTarget === null ||
        !rendered.includes(`"targetValue":${String(expectedTarget)}`) ||
        !rendered.includes(
          `"scaleOutCooldown":${String(this.config.computeScaleOutCooldownSeconds)}`,
        ) ||
        !rendered.includes(`"scaleInCooldown":${String(this.config.computeScaleInCooldownSeconds)}`)
      ) {
        Annotations.of(node).addError('MXMED_COMPUTE_SCALING_POLICY_INVALID');
      }
    }
  }

  private validateInventory(stack: Stack): void {
    const nodes = stack.node.findAll();
    const count = (resourceType: new (...args: never[]) => IConstruct): number =>
      nodes.filter((candidate) => candidate instanceof resourceType).length;
    const digestParameters = nodes.filter(
      (candidate) =>
        candidate instanceof CfnParameter && candidate.node.path.includes('ApplicationImageDigest'),
    ).length;
    const expectedTasks = computeCreatesTasks(this.config.computeActivationMode) ? 1 : 0;
    const expectedService = computeCreatesService(this.config.computeActivationMode) ? 1 : 0;
    if (
      count(CfnRepository) !== 0 ||
      count(CfnCluster) !== expectedTasks ||
      count(CfnTaskDefinition) !== expectedTasks * 2 ||
      count(CfnLogGroup) !== expectedTasks * 2 ||
      digestParameters !== expectedTasks ||
      count(CfnService) !== expectedService ||
      count(CfnScalableTarget) !== expectedService ||
      count(CfnScalingPolicy) !== expectedService * 2 ||
      count(CfnLoadBalancer) !== 0 ||
      count(CfnTargetGroup) !== 0
    ) {
      Annotations.of(stack).addError('MXMED_COMPUTE_INVENTORY_INVALID');
    }
  }

  private validateTaskDefinition(node: CfnTaskDefinition): void {
    if (!computeCreatesTasks(this.config.computeActivationMode)) {
      Annotations.of(node).addError('MXMED_COMPUTE_TASK_MODE_INVALID');
      return;
    }
    const rendered = Stack.of(node).resolve({
      networkMode: node.networkMode,
      requiresCompatibilities: node.requiresCompatibilities,
      runtimePlatform: node.runtimePlatform,
      containers: node.containerDefinitions,
    }) as {
      networkMode?: unknown;
      requiresCompatibilities?: unknown;
      runtimePlatform?: unknown;
      containers?: unknown;
    };
    const serialized = JSON.stringify(rendered);
    if (
      !serialized.includes('X86_64') ||
      !serialized.includes('LINUX') ||
      !serialized.includes('FARGATE') ||
      rendered.networkMode !== 'awsvpc' ||
      serialized.includes(':latest') ||
      !serialized.includes('@') ||
      !serialized.includes('ApplicationImageDigest') ||
      node.cpu !== String(this.config.computeTaskCpuUnits) ||
      node.memory !== String(this.config.computeTaskMemoryMiB) ||
      serialized.includes('9000')
    ) {
      Annotations.of(node).addError('MXMED_COMPUTE_TASK_RUNTIME_INVALID');
    }
    const containers = Array.isArray(rendered.containers) ? rendered.containers : [];
    const app = containers.find(isApplicationContainer) as Record<string, unknown> | undefined;
    if (app !== undefined) {
      const appText = JSON.stringify(app);
      const appSecrets = field(app, 'Secrets', 'secrets');
      const actualSecretNames = Array.isArray(appSecrets)
        ? appSecrets
            .flatMap((secret) => {
              if (typeof secret !== 'object' || secret === null) return [];
              const secretName = field(secret as Readonly<Record<string, unknown>>, 'Name', 'name');
              return typeof secretName === 'string' ? [secretName] : [];
            })
            .sort()
        : [];
      const expectedSecretNames = [
        'DB_PASSWORD',
        'DB_USERNAME',
        'SESSION_SIGNING_KEY',
        'SESSION_STORE_PASSWORD',
        'SESSION_STORE_USERNAME',
      ];
      const capability = this.config.runtimeCapabilityProfile;
      if (capability !== null && capabilityIncludesPaid(capability)) {
        expectedSecretNames.push('STRIPE_SECRET_KEY', 'STRIPE_WEBHOOK_SECRET');
      }
      if (capability !== null && capabilityIncludesAi(capability)) {
        expectedSecretNames.push('AI_API_KEY');
      }
      if (
        containers.length !== 1 ||
        field(app, 'ReadonlyRootFilesystem', 'readonlyRootFilesystem') !== true ||
        field(app, 'Privileged', 'privileged') === true ||
        field(app, 'User', 'user') !== 'www-data' ||
        !appText.includes('ALL') ||
        appText.includes('DB_MASTER_') ||
        JSON.stringify(actualSecretNames) !== JSON.stringify(expectedSecretNames.sort())
      ) {
        Annotations.of(node).addError('MXMED_COMPUTE_APP_CONTAINER_SECURITY_INVALID');
      }
    }
  }
}
