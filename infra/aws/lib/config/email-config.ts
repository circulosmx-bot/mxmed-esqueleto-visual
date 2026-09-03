export const MXMED_EMAIL_PROVIDER = 'ses';
export const MXMED_SES_REGION = 'us-east-1';
export const MXMED_SES_DOMAIN_IDENTITY = 'mexicomedico.com';
export const MXMED_EMAIL_FROM_ADDRESS = 'no-reply@mexicomedico.com';
export const MXMED_EMAIL_FROM_NAME = 'México Médico';

export function mxmedSesIdentityArn(account: string): string {
  return `arn:aws:ses:${MXMED_SES_REGION}:${account}:identity/${MXMED_SES_DOMAIN_IDENTITY}`;
}
