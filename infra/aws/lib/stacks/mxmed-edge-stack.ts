import type { Construct } from 'constructs';

import { BaseMxMedStack } from './base-mxmed-stack';
import type { MxMedContractStackProps } from './base-mxmed-stack';

/** Future ALB, CloudFront, WAF, DNS, Stripe return and webhook ingress boundary. */
export class MxMedEdgeStack extends BaseMxMedStack {
  public constructor(scope: Construct, id: string, props: MxMedContractStackProps) {
    super(scope, id, {
      ...props,
      component: 'edge',
      description: 'MXMed edge contract; no ingress resources in the foundation phase.',
      metadata: { dataClassification: 'public', criticality: 'high', backup: 'not-required' },
    });
  }
}
