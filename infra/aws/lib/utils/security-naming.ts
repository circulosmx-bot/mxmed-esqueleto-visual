import type { MxMedEnvironmentCode, MxMedEnvironmentName } from '../config/environment-config';
import { assertMxMedCondition } from './validation';

export type MxMedSecurityKeyPurpose = 'application-data' | 'secrets' | 'audit' | 'backup';

export type MxMedExternalSecretPath =
  'providers/stripe/secret-key' | 'providers/stripe/webhook-secret' | 'providers/ai/api-key';

export function mxmedSecurityKeyAlias(
  environmentCode: MxMedEnvironmentCode,
  purpose: MxMedSecurityKeyPurpose,
): string {
  return `alias/mxmed-${environmentCode}-${purpose}`;
}

export function mxmedSecuritySecretName(
  environmentName: MxMedEnvironmentName,
  path: 'application/session-signing' | MxMedExternalSecretPath,
): string {
  assertMxMedCondition(
    !path.startsWith('/') && !path.endsWith('/') && !path.includes('..'),
    'MXMED_NAMING_INVALID',
    'secretPath',
    'must be a contracted relative path',
  );
  return `/mxmed/${environmentName}/${path}`;
}

export function mxmedCloudTrailLogGroupName(environmentName: MxMedEnvironmentName): string {
  return `/mxmed/${environmentName}/security/cloudtrail`;
}

export function mxmedBoundaryName(
  environmentCode: MxMedEnvironmentCode,
  kind: 'workload' | 'deployment',
): string {
  return `mxmed-${environmentCode}-${kind}-boundary`;
}
