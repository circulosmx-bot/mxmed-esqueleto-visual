export const MXMED_RESTORE_SENTINEL_CONTRACT = Object.freeze({
  integrated: false,
  rds: {
    name: 'backup_restore_sentinel',
    allowedFields: ['opaque_identifier', 'expected_value', 'updated_at'],
  },
  s3: {
    key: 'dr-sentinel/health.json',
    nonSensitive: true,
    checksumRequired: true,
  },
  createsData: false,
} as const);

export interface RestoreResidualCostAudit {
  readonly restoreWindowId: string;
  readonly restoreEndedAt: string;
  readonly temporaryRdsRemoved: boolean;
  readonly temporaryBucketsReviewed: boolean;
  readonly temporaryObjectsReviewed: boolean;
  readonly securityGroupsRemoved: boolean;
  readonly enisReviewed: boolean;
  readonly snapshotsReviewed: boolean;
  readonly kmsGrantsReviewed: boolean;
  readonly logsReviewed: boolean;
  readonly cleanupDeadline: string;
  readonly evidenceReference: string;
  readonly approved: boolean;
}

export const MXMED_RESTORE_VALIDATION_PROFILE = Object.freeze({
  completedMeansValidated: false,
  validatorLambdaImplemented: false,
  requires: [
    'restore-job-completed',
    'infrastructure-validation',
    'schema-and-checksum-validation',
    'synthetic-sentinels',
    'cleanup-audit',
    'evidence-approval',
  ],
  forbiddenEvidence: [
    'sql-row',
    'clinical-content',
    'personal-identifier',
    'client-secret',
    'password',
    'session-id',
  ],
} as const);
