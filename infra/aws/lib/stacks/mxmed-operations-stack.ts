import type { Construct } from 'constructs';

import { BaseMxMedStack } from './base-mxmed-stack';
import type { MxMedContractStackProps } from './base-mxmed-stack';

/** Future log, dashboard, metric, alarm and alerting boundary. */
export class MxMedOperationsStack extends BaseMxMedStack {
  public constructor(scope: Construct, id: string, props: MxMedContractStackProps) {
    super(scope, id, {
      ...props,
      component: 'operations',
      description: 'MXMed operations contract; no observability resources in foundation.',
      metadata: { dataClassification: 'internal', criticality: 'high', backup: 'required' },
    });
  }
}
