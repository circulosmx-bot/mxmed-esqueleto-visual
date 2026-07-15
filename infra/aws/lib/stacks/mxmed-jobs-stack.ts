import type { Construct } from 'constructs';

import { BaseMxMedStack } from './base-mxmed-stack';
import type { MxMedContractStackProps } from './base-mxmed-stack';

/** Future Scheduler, ECS RunTask and contracted SQS/DLQ workload boundary. */
export class MxMedJobsStack extends BaseMxMedStack {
  public constructor(scope: Construct, id: string, props: MxMedContractStackProps) {
    super(scope, id, {
      ...props,
      component: 'jobs',
      description: 'MXMed jobs contract; no scheduled or queue resources in foundation.',
      metadata: { dataClassification: 'internal', criticality: 'medium', backup: 'not-required' },
    });
  }
}
