export type MxMedBackupRunbookSeverity = 'SEV1' | 'SEV2' | 'SEV3';

export interface MxMedBackupRunbook {
  readonly id: string;
  readonly severity: MxMedBackupRunbookSeverity;
  readonly authorization: string;
  readonly trigger: string;
  readonly recoveryPointSelection: string;
  readonly safeChecks: readonly string[];
  readonly prohibitedActions: readonly string[];
  readonly restoreTarget: string;
  readonly validation: readonly string[];
  readonly cutover: string;
  readonly rollback: string;
  readonly cleanup: readonly string[];
  readonly evidence: readonly string[];
  readonly closureCriteria: readonly string[];
}

const RUNBOOK_IDS = [
  'rds-point-in-time-restore',
  'rds-snapshot-restore',
  's3-object-version-recovery',
  's3-backup-restore',
  'accidental-clinical-object-deletion',
  'database-logical-corruption',
  'database-total-loss',
  'source-region-unavailable',
  'backup-job-failed',
  'copy-job-failed',
  'restore-job-failed',
  'restore-validation-failed',
  'recovery-point-expired',
  'backup-vault-access-denied',
  'backup-key-deletion-risk',
  'secret-reissuance-after-disaster',
  'valkey-empty-recovery',
  'ecr-image-rebuild',
  'cross-region-cutover',
  'failback-to-primary-region',
  'restore-test-residual-cost',
  'suspected-backup-compromise',
] as const;

function severity(id: string): MxMedBackupRunbookSeverity {
  if (
    ['region', 'total-loss', 'corruption', 'key-deletion', 'compromise', 'cutover'].some((marker) =>
      id.includes(marker),
    )
  ) {
    return 'SEV1';
  }
  if (id.includes('residual-cost')) return 'SEV3';
  return 'SEV2';
}

function runbook(id: (typeof RUNBOOK_IDS)[number]): MxMedBackupRunbook {
  return Object.freeze({
    id,
    severity: severity(id),
    authorization: 'incident-commander-and-resource-owner',
    trigger: `contractual-${id}-condition`,
    recoveryPointSelection: 'explicit-approved-recovery-point-or-not-applicable',
    safeChecks: ['verify-scope', 'verify-key-and-vault', 'preserve-source'],
    prohibitedActions: ['no-in-place-overwrite', 'no-public-restore', 'no-secret-output'],
    restoreTarget: 'new-isolated-resource-or-empty-rebuild',
    validation: ['infrastructure', 'schema-or-checksum', 'synthetic-sentinel'],
    cutover: 'manual-after-approved-validation',
    rollback: 'return-to-last-validated-target',
    cleanup: ['review-temporary-resources', 'review-residual-cost'],
    evidence: ['sanitized-job-state', 'validation-result', 'approval-reference'],
    closureCriteria: ['service-stable', 'evidence-approved', 'residual-audit-closed'],
  });
}

export const MXMED_BACKUP_RUNBOOK_CATALOG: readonly MxMedBackupRunbook[] = Object.freeze(
  RUNBOOK_IDS.map(runbook),
);

export function backupRunbook(id: string): MxMedBackupRunbook {
  const selected = MXMED_BACKUP_RUNBOOK_CATALOG.find((entry) => entry.id === id);
  if (selected === undefined) throw new Error(`MXMED_BACKUP_RUNBOOK_UNKNOWN:${id}`);
  return selected;
}
