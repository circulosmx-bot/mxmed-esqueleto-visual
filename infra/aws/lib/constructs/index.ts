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
export {
  MXMED_LAUNCH_LEAN_ALARM_CATALOG,
  MXMED_PRODUCTION_STANDARD_ADDITIONAL_ALARM_CATALOG,
  MXMED_RDS_INSTANCE_MEMORY_GIB,
  MXMED_VALKEY_CONNECTION_WARNING,
  deriveRdsConnectionBudget,
  storageThresholdBytes,
} from './operations-alarm-catalog';
export type { MxMedAlarmDefinition, MxMedIncidentSeverity } from './operations-alarm-catalog';
export {
  MXMED_GLOBAL_DASHBOARD_CONTRACT,
  MXMED_REGIONAL_DASHBOARD_CONTRACT,
} from './operations-dashboard-contract';
export {
  MXMED_INCIDENT_SEVERITY_CONTRACT,
  MXMED_PROMOTION_SIGNAL_CONTRACT,
  MXMED_SLO_ERROR_BUDGET_CONTRACT,
  MXMED_STAGING_RELEASE_WINDOW_CONTRACT,
  canPromoteLaunchToStandard,
  sloProfile,
} from './operations-contract';
export type {
  MxMedLaunchToStandardPromotionEvidence,
  StagingResidualCostAudit,
} from './operations-contract';
export { MXMED_OPERATIONS_RUNBOOK_CATALOG, operationsRunbook } from './operations-runbook-catalog';
export type { MxMedOperationsRunbook } from './operations-runbook-catalog';
