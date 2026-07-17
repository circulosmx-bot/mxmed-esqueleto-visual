import type { MxMedBackupReadinessState } from '../config/environment-config';

export const MXMED_VALKEY_RECOVERY_CONTRACT = Object.freeze({
  mode: 'empty-rebuild-v1',
  backupEnabled: false,
  sessionContinuityGuaranteed: false,
  activeSessionsLost: true,
  locksRecreated: true,
  reauthenticationRequired: true,
} as const);

export const MXMED_ECR_RECOVERY_CONTRACT = Object.freeze({
  source: 'last-published-git-commit',
  dockerfileVersioned: true,
  rebuildRequired: true,
  scanRequired: true,
  immutableTagRequired: true,
  digestRequired: true,
  crossRegionReplicationEnabled: false,
} as const);

export interface MxMedSecretRecoveryContract {
  readonly secretContractName: string;
  readonly recoveryMethod: 'rotate-or-reissue';
  readonly ownerReference: string;
  readonly externalProvider: boolean;
  readonly validationRequired: true;
}

export const MXMED_SECRET_RECOVERY_CONTRACTS: readonly MxMedSecretRecoveryContract[] =
  Object.freeze([
    {
      secretContractName: 'application/session-signing',
      recoveryMethod: 'rotate-or-reissue',
      ownerReference: 'security-owner',
      externalProvider: false,
      validationRequired: true,
    },
    {
      secretContractName: 'providers/stripe/secret-key',
      recoveryMethod: 'rotate-or-reissue',
      ownerReference: 'payments-owner',
      externalProvider: true,
      validationRequired: true,
    },
    {
      secretContractName: 'providers/stripe/webhook-secret',
      recoveryMethod: 'rotate-or-reissue',
      ownerReference: 'payments-owner',
      externalProvider: true,
      validationRequired: true,
    },
    {
      secretContractName: 'providers/ai/api-key',
      recoveryMethod: 'rotate-or-reissue',
      ownerReference: 'ai-owner',
      externalProvider: true,
      validationRequired: true,
    },
  ]);

export const MXMED_BACKUP_RPO_RTO_CONTRACT = Object.freeze({
  rdsLaunch: { operationalRpoMinutes: 5, regionalSnapshotRpoHours: 24, rtoHours: 4 },
  rdsStandard: { operationalRpoMinutes: 5, crossRegionCopyRpoHours: 24, rtoHours: 2 },
  s3Launch: { continuousRpoMinutes: 15, rtoHours: 8 },
  s3Standard: { regionalContinuousRpoMinutes: 15, crossRegionPeriodicRpoHours: 24, rtoHours: 4 },
  sessions: { backupEnabled: false, rtoMinutes: 30, activeSessionsLost: true },
  infrastructure: { rpoSource: 'last-published-commit', rtoHours: 8 },
  internalObjective: true,
  customerSla: false,
  measured: false,
} as const);

const NEXT_READINESS_STATE: Readonly<
  Partial<Record<MxMedBackupReadinessState, MxMedBackupReadinessState>>
> = Object.freeze({
  'not-protected-v1': 'backup-configured-v1',
  'backup-configured-v1': 'recovery-point-available-v1',
  'recovery-point-available-v1': 'restore-job-completed-v1',
  'restore-job-completed-v1': 'application-validated-v1',
  'application-validated-v1': 'dr-ready-v1',
});

export interface MxMedDrReadyEvidence {
  readonly restoreCompleted: boolean;
  readonly applicationValidated: boolean;
  readonly cleanupApproved: boolean;
  readonly runbookApproved: boolean;
  readonly ownersApproved: boolean;
  readonly costsApproved: boolean;
  readonly evidenceApproved: boolean;
  readonly monitoringActive: boolean;
  readonly regionApprovedWhenRequired: boolean;
}

export function transitionBackupReadiness(
  current: MxMedBackupReadinessState,
  next: MxMedBackupReadinessState,
  evidence?: MxMedDrReadyEvidence,
): MxMedBackupReadinessState {
  if (NEXT_READINESS_STATE[current] !== next) {
    throw new Error(`MXMED_BACKUP_READINESS_TRANSITION_INVALID:${current}:${next}`);
  }
  if (
    next === 'dr-ready-v1' &&
    (evidence === undefined || Object.values(evidence).some((value) => !value))
  ) {
    throw new Error('MXMED_BACKUP_DR_READY_EVIDENCE_INCOMPLETE');
  }
  return next;
}
