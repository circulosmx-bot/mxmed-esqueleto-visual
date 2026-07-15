import type { Construct } from 'constructs';

import { BaseMxMedStack } from './base-mxmed-stack';
import type { MxMedContractStackProps } from './base-mxmed-stack';

/** Future RDS, parameter, monitoring, backup and credential-reference boundary. */
export class MxMedDataStack extends BaseMxMedStack {
  public constructor(scope: Construct, id: string, props: MxMedContractStackProps) {
    super(scope, id, {
      ...props,
      component: 'data',
      description: 'MXMed data contract; no database resources in the foundation phase.',
      metadata: { dataClassification: 'clinical', criticality: 'high', backup: 'required' },
    });
  }
}
