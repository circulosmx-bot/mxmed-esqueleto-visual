import type { Construct } from 'constructs';

import { BaseMxMedStack } from './base-mxmed-stack';
import type { MxMedContractStackProps } from './base-mxmed-stack';

/** Future VPC, subnet, routing, endpoint, security-group and flow-log boundary. */
export class MxMedNetworkStack extends BaseMxMedStack {
  public constructor(scope: Construct, id: string, props: MxMedContractStackProps) {
    super(scope, id, {
      ...props,
      component: 'network',
      description: 'MXMed network contract; no resources in the foundation phase.',
      metadata: { dataClassification: 'internal', criticality: 'high', backup: 'not-required' },
    });
  }
}
