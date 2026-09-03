import type { Construct } from 'constructs';
import { CfnRecordSet } from 'aws-cdk-lib/aws-route53';
import { CfnEmailIdentity } from 'aws-cdk-lib/aws-ses';

import { BaseMxMedStack } from './base-mxmed-stack';
import type { MxMedContractStackProps } from './base-mxmed-stack';
import { MXMED_EMAIL_HOSTED_ZONE_ID, MXMED_SES_DOMAIN_IDENTITY } from '../config/email-config';

/** Transactional outbound SES identity boundary in us-east-1. */
export class MxMedEmailStack extends BaseMxMedStack {
  public constructor(scope: Construct, id: string, props: MxMedContractStackProps) {
    super(scope, id, {
      ...props,
      component: 'email',
      description: 'MXMed transactional outbound SES domain identity.',
      metadata: { dataClassification: 'internal', criticality: 'medium', backup: 'not-required' },
    });

    const identity = new CfnEmailIdentity(this, 'TransactionalDomainIdentity', {
      emailIdentity: MXMED_SES_DOMAIN_IDENTITY,
      dkimAttributes: { signingEnabled: true },
      dkimSigningAttributes: { nextSigningKeyLength: 'RSA_2048_BIT' },
    });

    const dkimTokens = [
      [identity.attrDkimDnsTokenName1, identity.attrDkimDnsTokenValue1],
      [identity.attrDkimDnsTokenName2, identity.attrDkimDnsTokenValue2],
      [identity.attrDkimDnsTokenName3, identity.attrDkimDnsTokenValue3],
    ] as const;

    dkimTokens.forEach(([name, value], index) => {
      new CfnRecordSet(this, `DkimCname${String(index + 1)}`, {
        hostedZoneId: MXMED_EMAIL_HOSTED_ZONE_ID,
        name,
        type: 'CNAME',
        resourceRecords: [value],
        ttl: '300',
      });
    });
  }
}
