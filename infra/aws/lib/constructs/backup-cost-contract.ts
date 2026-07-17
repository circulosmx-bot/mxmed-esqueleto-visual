export interface MxMedBackupCostInputs {
  readonly protectedRdsAllocatedGiB: number | null;
  readonly expectedRdsSnapshotGiBMonth: number | null;
  readonly clinicalProtectedGiB: number | null;
  readonly privateProtectedGiB: number | null;
  readonly monthlyObjectChangeGiB: number | null;
  readonly continuousBackupRetentionDays: number;
  readonly periodicBackupRetentionDays: number;
  readonly crossRegionCopyGiB: number | null;
  readonly restoreTestFrequency: string;
  readonly temporaryRdsHours: number | null;
  readonly restoreDataGiB: number | null;
  readonly destinationStorageGiB: number | null;
  readonly kmsKeyCount: number;
  readonly residualResourceRisk: string;
}

export interface MxMedBackupCostLedgerEntry {
  readonly service: string;
  readonly resource: string;
  readonly region: string;
  readonly profile: string;
  readonly quantity: number | null;
  readonly unit: string;
  readonly rateUsd: null;
  readonly estimatedMonthlyUsd: null;
  readonly formula: string;
  readonly pricingEvidenceReference: null;
  readonly uncertainty: 'pricing-and-usage-not-queried';
  readonly taxesIncluded: false;
  readonly fxIncluded: false;
}

export const MXMED_BACKUP_COST_CONTRACT = Object.freeze({
  pricingQueried: false,
  zeroCostClaim: false,
  excludedLaunchServices: [
    'logically-air-gapped-vault',
    'backup-audit-manager',
    'report-plan',
    'backup-indexing',
    'cross-account-copy',
  ],
  requiredDrivers: [
    'backup-storage',
    'restore-testing',
    'temporary-rds-hours',
    'cross-region-transfer',
    'destination-storage',
    'kms-keys',
    'residual-resources',
  ],
} as const);
