import type { Construct } from 'constructs';

import { BaseMxMedStack } from './base-mxmed-stack';
import type { MxMedContractStackProps } from './base-mxmed-stack';

/** Future AWS Backup vault, plan, selection and restoration-contract boundary. */
export class MxMedBackupStack extends BaseMxMedStack {
  public constructor(scope: Construct, id: string, props: MxMedContractStackProps) {
    super(scope, id, {
      ...props,
      component: 'backup',
      description: 'MXMed backup contract; no backup resources in the foundation phase.',
      metadata: { dataClassification: 'sensitive', criticality: 'high', backup: 'required' },
    });
  }
}
