import {
  backupRunbook,
  MXMED_BACKUP_RUNBOOK_CATALOG,
} from '../lib/constructs/backup-runbook-catalog';

const EXPECTED_IDS = [
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

describe('Backup/DR runbook catalog', () => {
  test('contains exactly 22 unique runbooks in the canonical order', () => {
    expect(MXMED_BACKUP_RUNBOOK_CATALOG.map((runbook) => runbook.id)).toEqual(EXPECTED_IDS);
    expect(new Set(EXPECTED_IDS).size).toBe(22);
  });

  test.each(EXPECTED_IDS)('%s has every required safe field', (id) => {
    const runbook = backupRunbook(id);
    expect(Object.keys(runbook).sort()).toEqual(
      [
        'authorization',
        'cleanup',
        'closureCriteria',
        'cutover',
        'evidence',
        'id',
        'prohibitedActions',
        'recoveryPointSelection',
        'restoreTarget',
        'rollback',
        'safeChecks',
        'severity',
        'trigger',
        'validation',
      ].sort(),
    );
    expect(['SEV1', 'SEV2', 'SEV3']).toContain(runbook.severity);
    expect(runbook.authorization).toBeTruthy();
    expect(runbook.trigger).toBeTruthy();
    expect(runbook.safeChecks.length).toBeGreaterThan(0);
    expect(runbook.prohibitedActions.length).toBeGreaterThan(0);
    expect(runbook.restoreTarget).toBeTruthy();
    expect(runbook.validation.length).toBeGreaterThan(0);
    expect(runbook.cleanup.length).toBeGreaterThan(0);
    expect(runbook.closureCriteria.length).toBeGreaterThan(0);
  });

  test.each([
    'aws backup start-restore-job',
    'aws rds delete-db-instance',
    'rm -rf',
    'DROP DATABASE',
    'terraform apply',
    'cdk deploy',
  ])('catalog contains no executable destructive command: %s', (command) => {
    expect(JSON.stringify(MXMED_BACKUP_RUNBOOK_CATALOG)).not.toContain(command);
  });

  test('rejects unknown runbook identifiers', () => {
    expect(() => backupRunbook('unknown-runbook')).toThrow('MXMED_BACKUP_RUNBOOK_UNKNOWN');
  });
});
