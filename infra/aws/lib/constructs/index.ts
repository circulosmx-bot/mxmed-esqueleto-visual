/**
 * Reserved catalog for policy-bearing reusable constructs contracted by PP249.
 * No construct is implemented until its resource-specific microphase is approved.
 */
export type MxMedPlannedConstructName =
  | 'MxMedVpc'
  | 'MxMedDatabase'
  | 'MxMedPrivateBucket'
  | 'MxMedFargateApplication'
  | 'MxMedCloudFrontIngress'
  | 'MxMedStripeReturnBehavior'
  | 'MxMedStripeWebhookBehavior'
  | 'MxMedLogGroup'
  | 'MxMedAlarmSet';

export { MxMedGitHubOidcDeployment } from './github-oidc-deployment';
export type { MxMedGitHubOidcDeploymentProps } from './github-oidc-deployment';
export { MxMedSecurityRoleFactory } from './security-role-factory';
export type { MxMedHumanRoleProps, MxMedWorkloadRoleKind } from './security-role-factory';
export { SecuritySecretContainer } from './security-secret-container';
export type { SecuritySecretContainerProps } from './security-secret-container';
export {
  SESSION_ACL_COMMANDS,
  SESSION_COOKIE_CONTRACT,
  SESSION_LOCK_CONTRACT,
  SESSION_PAYLOAD_CONTRACT,
  SESSION_PAYLOAD_KEYS,
  SessionContractError,
  buildSessionAclContract,
  buildSessionApplicationAccessString,
  buildSessionKey,
  buildSessionPrefix,
  validateOpaqueSessionId,
  validateSessionAcl,
  validateSessionCookieContract,
  validateSessionExpiration,
  validateSessionLockContract,
  validateSessionPayloadKeys,
  validateSessionPayloadSize,
} from './session-contract';
export type {
  SessionAclCommand,
  SessionAclContract,
  SessionCookieContract,
  SessionEnvironmentCode,
  SessionExpirationContract,
  SessionKeyContract,
  SessionLockContract,
  SessionPayloadContract,
  SessionPayloadKey,
} from './session-contract';
