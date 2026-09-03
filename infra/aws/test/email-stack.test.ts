import { App } from 'aws-cdk-lib';
import { Template } from 'aws-cdk-lib/assertions';

import {
  MXMED_EMAIL_FROM_ADDRESS,
  MXMED_EMAIL_FROM_NAME,
  MXMED_EMAIL_HOSTED_ZONE_ID,
  MXMED_EMAIL_PROVIDER,
  MXMED_SES_DOMAIN_IDENTITY,
  MXMED_SES_REGION,
  mxmedSesIdentityArn,
} from '../lib/config/email-config';
import { PRODUCTION_CONFIG } from '../lib/config/environments';
import { MxMedEmailStage } from '../lib/stages/mxmed-email-stage';

describe('EOTP transactional SES domain identity', () => {
  const app = new App({ analyticsReporting: false });
  const stage = new MxMedEmailStage(app, 'EotpEmail', {
    account: '875691018466',
    config: PRODUCTION_CONFIG,
  });
  const template = Template.fromStack(stage.emailStack);
  const rendered = template.toJSON();
  const serialized = JSON.stringify(rendered);

  test('targets the contracted us-east-1 email region', () => {
    expect(stage.emailStack.region).toBe(MXMED_SES_REGION);
  });

  test('declares exactly one domain identity with enabled 2048-bit Easy DKIM', () => {
    template.resourceCountIs('AWS::SES::EmailIdentity', 1);
    template.hasResourceProperties('AWS::SES::EmailIdentity', {
      EmailIdentity: MXMED_SES_DOMAIN_IDENTITY,
      DkimAttributes: { SigningEnabled: true },
      DkimSigningAttributes: { NextSigningKeyLength: 'RSA_2048_BIT' },
    });
  });

  test('manages exactly three Easy DKIM CNAMEs in the existing public hosted zone', () => {
    template.resourceCountIs('AWS::Route53::HostedZone', 0);
    template.resourceCountIs('AWS::Route53::RecordSet', 3);

    for (const tokenNumber of ['1', '2', '3'] as const) {
      template.hasResourceProperties('AWS::Route53::RecordSet', {
        HostedZoneId: MXMED_EMAIL_HOSTED_ZONE_ID,
        Name: { 'Fn::GetAtt': ['TransactionalDomainIdentity', `DkimDNSTokenName${tokenNumber}`] },
        Type: 'CNAME',
        ResourceRecords: [
          { 'Fn::GetAtt': ['TransactionalDomainIdentity', `DkimDNSTokenValue${tokenNumber}`] },
        ],
        TTL: '300',
      });
    }
  });

  test('contains no BYODKIM private key, custom MAIL FROM, SMTP secret, or unrelated DNS record', () => {
    expect(serialized).not.toMatch(
      /DomainSigningPrivateKey|DomainSigningSelector|MailFromAttributes/,
    );
    expect(serialized).not.toMatch(/AWS::SecretsManager::Secret/);
  });

  test('locks the accepted application integration constants', () => {
    expect({
      provider: MXMED_EMAIL_PROVIDER,
      region: MXMED_SES_REGION,
      identity: MXMED_SES_DOMAIN_IDENTITY,
      identityArn: mxmedSesIdentityArn('875691018466'),
      fromAddress: MXMED_EMAIL_FROM_ADDRESS,
      fromName: MXMED_EMAIL_FROM_NAME,
    }).toEqual({
      provider: 'ses',
      region: 'us-east-1',
      identity: 'mexicomedico.com',
      identityArn: 'arn:aws:ses:us-east-1:875691018466:identity/mexicomedico.com',
      fromAddress: 'no-reply@mexicomedico.com',
      fromName: 'México Médico',
    });
  });
});
