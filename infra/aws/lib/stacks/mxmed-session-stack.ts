import type { Construct } from 'constructs';

import { BaseMxMedStack } from './base-mxmed-stack';
import type { MxMedContractStackProps } from './base-mxmed-stack';

/** Future encrypted shared-session, TTL, networking and metric boundary. */
export class MxMedSessionStack extends BaseMxMedStack {
  public constructor(scope: Construct, id: string, props: MxMedContractStackProps) {
    super(scope, id, {
      ...props,
      component: 'session',
      description: 'MXMed session contract; no cache resources in the foundation phase.',
      metadata: {
        dataClassification: 'sensitive',
        criticality: 'high',
        backup: 'not-required',
      },
    });
  }
}
