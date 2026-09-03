import { App } from 'aws-cdk-lib';
import { Template } from 'aws-cdk-lib/assertions';

import {
  MXMED_EMAIL_FROM_ADDRESS,
  MXMED_EMAIL_FROM_NAME,
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

  test('contains no BYODKIM private key, custom MAIL FROM, SMTP secret, or DNS mutation', () => {
    expect(serialized).not.toMatch(
      /DomainSigningPrivateKey|DomainSigningSelector|MailFromAttributes/,
    );
    expect(serialized).not.toMatch(/AWS::SecretsManager::Secret|AWS::Route53::RecordSet/);
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
