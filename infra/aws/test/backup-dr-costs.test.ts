import type {
  MxMedBackupCostInputs,
  MxMedBackupCostLedgerEntry,
} from '../lib/constructs/backup-cost-contract';
import { MXMED_BACKUP_COST_CONTRACT } from '../lib/constructs/backup-cost-contract';

const inputs: MxMedBackupCostInputs = {
  protectedRdsAllocatedGiB: null,
  expectedRdsSnapshotGiBMonth: null,
  clinicalProtectedGiB: null,
  privateProtectedGiB: null,
  monthlyObjectChangeGiB: null,
  continuousBackupRetentionDays: 35,
  periodicBackupRetentionDays: 35,
  crossRegionCopyGiB: null,
  restoreTestFrequency: 'not-approved',
  temporaryRdsHours: null,
  restoreDataGiB: null,
  destinationStorageGiB: null,
  kmsKeyCount: 1,
  residualResourceRisk: 'manual-audit-required',
};

const ledger: MxMedBackupCostLedgerEntry = {
  service: 'aws-backup',
  resource: 'regional-recovery-vault',
  region: 'primary-region',
  profile: 'regional-recovery-ready-v1',
  quantity: null,
  unit: 'GB-month',
  rateUsd: null,
  estimatedMonthlyUsd: null,
  formula: 'quantity-times-approved-rate',
  pricingEvidenceReference: null,
  uncertainty: 'pricing-and-usage-not-queried',
  taxesIncluded: false,
  fxIncluded: false,
};

describe('Backup/DR cost contract', () => {
  test.each([
    ['pricingQueried', false],
    ['zeroCostClaim', false],
  ] as const)('declares %s=%s', (field, expected) => {
    expect(MXMED_BACKUP_COST_CONTRACT[field]).toBe(expected);
  });

  test.each([
    'backup-storage',
    'restore-testing',
    'temporary-rds-hours',
    'cross-region-transfer',
    'destination-storage',
    'kms-keys',
    'residual-resources',
  ])('tracks required driver %s', (driver) => {
    expect(MXMED_BACKUP_COST_CONTRACT.requiredDrivers).toContain(driver);
  });

  test.each([
    'logically-air-gapped-vault',
    'backup-audit-manager',
    'report-plan',
    'backup-indexing',
    'cross-account-copy',
  ])('excludes launch service %s', (service) => {
    expect(MXMED_BACKUP_COST_CONTRACT.excludedLaunchServices).toContain(service);
  });

  test('keeps unknown usage inputs nullable', () => {
    expect(inputs.protectedRdsAllocatedGiB).toBeNull();
    expect(inputs.crossRegionCopyGiB).toBeNull();
    expect(inputs.temporaryRdsHours).toBeNull();
  });

  test.each(['rateUsd', 'estimatedMonthlyUsd', 'pricingEvidenceReference'] as const)(
    'keeps ledger %s null until sourced',
    (field) => {
      expect(ledger[field]).toBeNull();
    },
  );

  test('records pricing uncertainty and excludes tax and FX assumptions', () => {
    expect(ledger.uncertainty).toBe('pricing-and-usage-not-queried');
    expect(ledger.taxesIncluded).toBe(false);
    expect(ledger.fxIncluded).toBe(false);
  });
});
