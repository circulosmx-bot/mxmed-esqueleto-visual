import { CfnParameter, DefaultStackSynthesizer, Stage, Tags } from 'aws-cdk-lib';
import type { StackProps, StageProps } from 'aws-cdk-lib';
import type { ISecret } from 'aws-cdk-lib/aws-secretsmanager';
import type { Construct } from 'constructs';

import type { MxMedEnvironmentConfig } from '../config/environment-config';
import { registryFoundationIsEnabled } from '../config/compute-config';
import { validateEnvironmentConfig } from '../config/environment-schema';
import { MXMED_C3_ACCOUNT, MXMED_C3_REGION } from '../constructs/c3-runner-contract';
import { MxMedC3JanitorStack } from '../stacks/mxmed-c3-janitor-stack';
import { MxMedC3RunnerStack } from '../stacks/mxmed-c3-runner-stack';
import { MxMedNetworkStack } from '../stacks/mxmed-network-stack';
import { MxMedRegistryStack } from '../stacks/mxmed-registry-stack';
import { MxMedSecurityStack } from '../stacks/mxmed-security-stack';
import { MxMedSessionStack } from '../stacks/mxmed-session-stack';
import type { BaseMxMedStack } from '../stacks/base-mxmed-stack';
import { mxmedC3AuditBucketName } from '../utils/naming';

export interface MxMedC3EphemeralStageProps {
  readonly config: MxMedEnvironmentConfig;
  readonly account?: string;
}

function applyEphemeralRunTags(stack: BaseMxMedStack): void {
  const runId = new CfnParameter(stack, 'RunId', {
    type: 'String',
    allowedPattern: '^c3-[a-z0-9][a-z0-9-]{5,62}$',
  });
  const expiresAt = new CfnParameter(stack, 'ExpiresAtUtc', {
    type: 'String',
    allowedPattern: '^20[0-9]{2}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z$',
  });
  Tags.of(stack).add('Phase', 'C3', { priority: 500 });
  Tags.of(stack).add('Ephemeral', 'true', { priority: 500 });
  Tags.of(stack).add('RunId', runId.valueAsString, { priority: 500 });
  Tags.of(stack).add('ExpiresAt', expiresAt.valueAsString, { priority: 500 });
}

function directCloudFormationSynthesizer(): NonNullable<StackProps['synthesizer']> {
  return new DefaultStackSynthesizer({
    generateBootstrapVersionRule: false,
  }) as unknown as NonNullable<StackProps['synthesizer']>;
}

/** Dedicated C3 graph: Network, Security, Session, Registry, Runner and Janitor only. */
export class MxMedC3EphemeralStage extends Stage {
  public readonly networkStack: MxMedNetworkStack;
  public readonly securityStack: MxMedSecurityStack;
  public readonly sessionStack: MxMedSessionStack;
  public readonly registryStack: MxMedRegistryStack;
  public readonly runnerStack: MxMedC3RunnerStack;
  public readonly janitorStack: MxMedC3JanitorStack;

  public constructor(scope: Construct, id: string, props: MxMedC3EphemeralStageProps) {
    validateEnvironmentConfig(props.config);
    if (
      props.config.environmentName !== 'staging' ||
      props.config.primaryRegion !== MXMED_C3_REGION ||
      !registryFoundationIsEnabled(props.config.computeActivationMode)
    ) {
      throw new Error('MXMED_C3_EPHEMERAL_STAGE_NONPRODUCTION_CONTRACT_INVALID');
    }
    if (props.account !== undefined && props.account !== MXMED_C3_ACCOUNT) {
      throw new Error('MXMED_C3_EPHEMERAL_STAGE_ACCOUNT_INVALID');
    }
    const env: StageProps['env'] = {
      account: props.account ?? MXMED_C3_ACCOUNT,
      region: MXMED_C3_REGION,
    };
    super(scope, id, { env });

    const stackProps = () => ({
      config: props.config,
      synthesizer: directCloudFormationSynthesizer(),
    });
    this.networkStack = new MxMedNetworkStack(this, 'Network', stackProps());
    this.securityStack = new MxMedSecurityStack(this, 'Security', {
      ...stackProps(),
      c3AuditBucketName: mxmedC3AuditBucketName(MXMED_C3_ACCOUNT, MXMED_C3_REGION),
    });
    this.sessionStack = new MxMedSessionStack(this, 'Session', {
      ...stackProps(),
      vpc: this.networkStack.vpc,
      isolatedDataSubnets: this.networkStack.isolatedDataSubnets,
      sessionSecurityGroup: this.networkStack.sessionSecurityGroup,
      applicationDataKey: this.securityStack.applicationDataKey,
      secretsKey: this.securityStack.secretsKey,
    });
    this.registryStack = new MxMedRegistryStack(this, 'Registry', stackProps());
    for (const foundation of [
      this.networkStack,
      this.securityStack,
      this.sessionStack,
      this.registryStack,
    ]) {
      applyEphemeralRunTags(foundation);
    }
    this.runnerStack = new MxMedC3RunnerStack(this, 'C3Runner', {
      ...stackProps(),
      privateAppSubnets: this.networkStack.privateAppSubnets,
      applicationSecurityGroup: this.networkStack.applicationSecurityGroup,
      sessionEndpoint: this.sessionStack.primaryEndpointAddress,
      sessionAuthSecret: this.sessionStack.authSecret as unknown as ISecret,
      secretsKey: this.securityStack.secretsKey,
      auditKey: this.securityStack.auditKey,
      applicationRepository: this.registryStack.applicationRepository,
    });
    this.janitorStack = new MxMedC3JanitorStack(this, 'C3Janitor', stackProps());

    this.sessionStack.addDependency(this.networkStack);
    this.sessionStack.addDependency(this.securityStack);
    this.runnerStack.addDependency(this.networkStack);
    this.runnerStack.addDependency(this.securityStack);
    this.runnerStack.addDependency(this.sessionStack);
    this.runnerStack.addDependency(this.registryStack);
  }
}
