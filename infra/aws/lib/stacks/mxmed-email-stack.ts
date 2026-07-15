import type { Construct } from 'constructs';

import { BaseMxMedStack } from './base-mxmed-stack';
import type { MxMedContractStackProps } from './base-mxmed-stack';

/** Future SES identity, DKIM, event and bounce-observability boundary in us-east-1. */
export class MxMedEmailStack extends BaseMxMedStack {
  public constructor(scope: Construct, id: string, props: MxMedContractStackProps) {
    super(scope, id, {
      ...props,
      component: 'email',
      description: 'MXMed email contract; no SES resources in the foundation phase.',
      metadata: { dataClassification: 'internal', criticality: 'medium', backup: 'not-required' },
    });
  }
}
