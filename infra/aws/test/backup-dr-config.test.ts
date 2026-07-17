import {
  MXMED_BACKUP_DATA_RESIDENCY_STATES,
  MXMED_BACKUP_DR_ACTIVATION_MODES,
  MXMED_BACKUP_READINESS_STATES,
  MXMED_BACKUP_SELECTION_MODES,
  MXMED_BACKUP_VALIDATION_STATES,
  MXMED_BACKUP_VAULT_LOCK_MODES,
  MXMED_CROSS_ACCOUNT_BACKUP_MODES,
  MXMED_DR_REGION_STATES,
  MXMED_RESTORE_TESTING_MODES,
} from '../lib/config/backup-dr-profiles';
import { getEnvironmentConfig } from '../lib/config/environments';
import { activeBackupConfig } from './backup-dr-test-helpers';

const REAL_DEFAULTS = [
  ['backupDrActivationMode', 'disabled-v1'],
  ['disasterRecoveryStrategy', 'backup-and-restore-v1'],
  ['backupVaultLockMode', 'governance-v1'],
  ['drRegionState', 'not-selected-v1'],
  ['crossAccountBackupMode', 'disabled-v1'],
  ['restoreTestingMode', 'disabled-v1'],
  ['backupDataResidencyState', 'pending-review-v1'],
  ['backupValidationState', 'not-tested-v1'],
  ['backupSelectionMode', 'explicit-resource-arns-v1'],
  ['valkeyRecoveryMode', 'empty-rebuild-v1'],
  ['backupReadinessState', 'not-protected-v1'],
  ['backupAutomaticFailoverEnabled', false],
  ['backupAutomaticFailbackEnabled', false],
  ['backupPublicMediaProtectionEnabled', false],
  ['backupQuarantineProtectionEnabled', false],
  ['backupAuditBucketProtectionEnabled', false],
] as const;

describe('backup/dr configuration', () => {
  test.each(REAL_DEFAULTS)('uses the real disabled default for %s', (field, expected) => {
    const config = getEnvironmentConfig('production', 'launch-lean-v1');
    expect(config[field]).toBe(expected);
  });

  test.each([
    ['activation', MXMED_BACKUP_DR_ACTIVATION_MODES, 4],
    ['vault lock', MXMED_BACKUP_VAULT_LOCK_MODES, 3],
    ['DR region state', MXMED_DR_REGION_STATES, 2],
    ['cross-account', MXMED_CROSS_ACCOUNT_BACKUP_MODES, 2],
    ['restore testing', MXMED_RESTORE_TESTING_MODES, 3],
    ['residency', MXMED_BACKUP_DATA_RESIDENCY_STATES, 2],
    ['validation', MXMED_BACKUP_VALIDATION_STATES, 3],
    ['selection', MXMED_BACKUP_SELECTION_MODES, 2],
    ['readiness', MXMED_BACKUP_READINESS_STATES, 6],
  ] as const)('publishes the canonical %s enum', (_label, values, expectedLength) => {
    expect(values).toHaveLength(expectedLength);
    expect(new Set(values).size).toBe(values.length);
  });

  test.each([
    ['backupRdsPeriodicRetentionDays', 35],
    ['backupRdsMonthlyRetentionDays', 365],
    ['backupS3ContinuousRetentionDays', 35],
    ['backupS3PeriodicRetentionDays', 35],
    ['backupCrossRegionRetentionDays', 35],
    ['backupStartWindowMinutes', 60],
    ['backupCompletionWindowMinutes', 360],
    ['backupVaultMinRetentionDays', 1],
    ['backupVaultMaxRetentionDays', 365],
  ] as const)('sets %s to its contractual value', (field, value) => {
    expect(activeBackupConfig()[field]).toBe(value);
  });

  test('raises RDS native retention to 35 only for an active fixture', () => {
    expect(getEnvironmentConfig('staging', 'launch-lean-v1').databaseBackupRetentionDays).toBe(7);
    expect(
      activeBackupConfig('launch-lean-v1', undefined, 'staging').databaseBackupRetentionDays,
    ).toBe(35);
  });

  test('rejects an unknown activation mode', () => {
    expect(() =>
      activeBackupConfig('launch-lean-v1', { backupDrActivationMode: 'unknown-mode' }),
    ).toThrow('MXMED_BACKUP_DR_CONFIG_INVALID:backupDrActivationMode');
  });
});
