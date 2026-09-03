import type { Construct } from 'constructs';
import { CfnEmailIdentity } from 'aws-cdk-lib/aws-ses';

import { BaseMxMedStack } from './base-mxmed-stack';
import type { MxMedContractStackProps } from './base-mxmed-stack';
import { MXMED_SES_DOMAIN_IDENTITY } from '../config/email-config';

/** Transactional outbound SES identity boundary in us-east-1. */
export class MxMedEmailStack extends BaseMxMedStack {
  public constructor(scope: Construct, id: string, props: MxMedContractStackProps) {
    super(scope, id, {
      ...props,
      component: 'email',
      description: 'MXMed transactional outbound SES domain identity.',
      metadata: { dataClassification: 'internal', criticality: 'medium', backup: 'not-required' },
    });

    new CfnEmailIdentity(this, 'TransactionalDomainIdentity', {
      emailIdentity: MXMED_SES_DOMAIN_IDENTITY,
      dkimAttributes: { signingEnabled: true },
      dkimSigningAttributes: { nextSigningKeyLength: 'RSA_2048_BIT' },
    });
  }
}
