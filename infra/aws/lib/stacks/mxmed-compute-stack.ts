import type { Construct } from 'constructs';

import { BaseMxMedStack } from './base-mxmed-stack';
import type { MxMedContractStackProps } from './base-mxmed-stack';

/** Future ECS, Fargate, task, service, health, scaling and IAM boundary. */
export class MxMedComputeStack extends BaseMxMedStack {
  public constructor(scope: Construct, id: string, props: MxMedContractStackProps) {
    super(scope, id, {
      ...props,
      component: 'compute',
      description: 'MXMed compute contract; no ECS resources in the foundation phase.',
      metadata: { dataClassification: 'internal', criticality: 'high', backup: 'not-required' },
    });
  }
}
