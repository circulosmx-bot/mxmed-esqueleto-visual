import type { Construct } from 'constructs';

import type { MxMedLaunchCapacity } from '../config/launch-profiles';
import { resolveLaunchProfile } from '../config/launch-profiles';
import { BaseMxMedStack } from './base-mxmed-stack';
import type { MxMedContractStackProps } from './base-mxmed-stack';

/** Future ECS, Fargate, task, service, health, scaling and IAM boundary. */
export class MxMedComputeStack extends BaseMxMedStack {
  /** Configuration-only handoff for Microphase 17; this stack intentionally has no resources. */
  public readonly launchCapacity: Readonly<
    Pick<
      MxMedLaunchCapacity,
      | 'computeSizingProfile'
      | 'computeAvailabilityProfile'
      | 'computeDesiredCount'
      | 'computeMinCapacity'
      | 'computeMaxCapacity'
      | 'computeTaskCpuUnits'
      | 'computeTaskMemoryMiB'
      | 'computeArchitecture'
      | 'computeUseSpot'
      | 'computeAssignPublicIp'
    >
  >;

  public constructor(scope: Construct, id: string, props: MxMedContractStackProps) {
    super(scope, id, {
      ...props,
      component: 'compute',
      description: 'MXMed compute contract; no ECS resources in the foundation phase.',
      metadata: { dataClassification: 'internal', criticality: 'high', backup: 'not-required' },
    });
    const capacity = resolveLaunchProfile(
      props.config.environmentName,
      props.config.deploymentProfile,
    ).capacity;
    this.launchCapacity = Object.freeze({
      computeSizingProfile: capacity.computeSizingProfile,
      computeAvailabilityProfile: capacity.computeAvailabilityProfile,
      computeDesiredCount: capacity.computeDesiredCount,
      computeMinCapacity: capacity.computeMinCapacity,
      computeMaxCapacity: capacity.computeMaxCapacity,
      computeTaskCpuUnits: capacity.computeTaskCpuUnits,
      computeTaskMemoryMiB: capacity.computeTaskMemoryMiB,
      computeArchitecture: capacity.computeArchitecture,
      computeUseSpot: capacity.computeUseSpot,
      computeAssignPublicIp: capacity.computeAssignPublicIp,
    });
  }
}
