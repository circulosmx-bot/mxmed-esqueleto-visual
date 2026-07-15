import type { Construct } from 'constructs';

import { BaseMxMedStack } from './base-mxmed-stack';
import type { MxMedContractStackProps } from './base-mxmed-stack';

/** Future KMS, secret-reference, base-role and shared-security boundary. */
export class MxMedSecurityStack extends BaseMxMedStack {
  public constructor(scope: Construct, id: string, props: MxMedContractStackProps) {
    super(scope, id, {
      ...props,
      component: 'security',
      description: 'MXMed security contract; no resources or secret values in foundation.',
      metadata: { dataClassification: 'sensitive', criticality: 'high', backup: 'required' },
    });
  }
}
