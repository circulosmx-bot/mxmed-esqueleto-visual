import type { Construct } from 'constructs';

import { BaseMxMedStack } from './base-mxmed-stack';
import type { MxMedContractStackProps } from './base-mxmed-stack';

/** Future private S3, versioning, lifecycle and object-policy boundary. */
export class MxMedStorageStack extends BaseMxMedStack {
  public constructor(scope: Construct, id: string, props: MxMedContractStackProps) {
    super(scope, id, {
      ...props,
      component: 'storage',
      description: 'MXMed storage contract; no bucket resources in the foundation phase.',
      metadata: { dataClassification: 'clinical', criticality: 'high', backup: 'required' },
    });
  }
}
