import {
  MXMED_BACKUP_RPO_RTO_CONTRACT,
  MXMED_ECR_RECOVERY_CONTRACT,
  MXMED_SECRET_RECOVERY_CONTRACTS,
  MXMED_VALKEY_RECOVERY_CONTRACT,
  transitionBackupReadiness,
} from '../lib/constructs/backup-dr-contract';
import {
  MXMED_RESTORE_SENTINEL_CONTRACT,
  MXMED_RESTORE_VALIDATION_PROFILE,
} from '../lib/constructs/backup-restore-validation-contract';

describe('backup/dr pure recovery contracts', () => {
  test.each([
    ['backupEnabled', false],
    ['sessionContinuityGuaranteed', false],
    ['activeSessionsLost', true],
    ['locksRecreated', true],
    ['reauthenticationRequired', true],
  ] as const)('Valkey declares %s=%s', (field, expected) => {
    expect(MXMED_VALKEY_RECOVERY_CONTRACT[field]).toBe(expected);
  });

  test.each([
    ['rebuildRequired', true],
    ['scanRequired', true],
    ['immutableTagRequired', true],
    ['digestRequired', true],
    ['crossRegionReplicationEnabled', false],
  ] as const)('ECR declares %s=%s', (field, expected) => {
    expect(MXMED_ECR_RECOVERY_CONTRACT[field]).toBe(expected);
  });

  test.each(MXMED_SECRET_RECOVERY_CONTRACTS)(
    'secret $secretContractName uses rotate-or-reissue without values',
    (contract) => {
      expect(contract.recoveryMethod).toBe('rotate-or-reissue');
      expect(contract.validationRequired).toBe(true);
      expect(JSON.stringify(contract)).not.toMatch(/SecretString|client_secret|password/i);
    },
  );

  test.each([
    ['rdsLaunch', 'operationalRpoMinutes', 5],
    ['rdsLaunch', 'regionalSnapshotRpoHours', 24],
    ['rdsLaunch', 'rtoHours', 4],
    ['rdsStandard', 'operationalRpoMinutes', 5],
    ['rdsStandard', 'crossRegionCopyRpoHours', 24],
    ['rdsStandard', 'rtoHours', 2],
    ['s3Launch', 'continuousRpoMinutes', 15],
    ['s3Launch', 'rtoHours', 8],
    ['s3Standard', 'regionalContinuousRpoMinutes', 15],
    ['s3Standard', 'crossRegionPeriodicRpoHours', 24],
    ['s3Standard', 'rtoHours', 4],
    ['sessions', 'rtoMinutes', 30],
    ['infrastructure', 'rtoHours', 8],
  ] as const)('publishes %s.%s=%s', (section, field, expected) => {
    const selected = MXMED_BACKUP_RPO_RTO_CONTRACT[section] as unknown as Record<string, unknown>;
    expect(selected[field]).toBe(expected);
  });

  test('allows only the first readiness transition from the real state', () => {
    expect(transitionBackupReadiness('not-protected-v1', 'backup-configured-v1')).toBe(
      'backup-configured-v1',
    );
  });

  test('rejects readiness skips', () => {
    expect(() => transitionBackupReadiness('not-protected-v1', 'dr-ready-v1')).toThrow(
      'MXMED_BACKUP_READINESS_TRANSITION_INVALID',
    );
  });

  test('does not treat a completed restore as application validation', () => {
    expect(() => transitionBackupReadiness('restore-job-completed-v1', 'dr-ready-v1')).toThrow(
      'MXMED_BACKUP_READINESS_TRANSITION_INVALID',
    );
  });

  test('requires every approval for DR ready', () => {
    expect(() =>
      transitionBackupReadiness('application-validated-v1', 'dr-ready-v1', {
        restoreCompleted: true,
        applicationValidated: true,
        cleanupApproved: false,
        runbookApproved: true,
        ownersApproved: true,
        costsApproved: true,
        evidenceApproved: true,
        monitoringActive: true,
        regionApprovedWhenRequired: true,
      }),
    ).toThrow('MXMED_BACKUP_DR_READY_EVIDENCE_INCOMPLETE');
  });

  test('sentinel contract creates no data', () => {
    expect(MXMED_RESTORE_SENTINEL_CONTRACT.createsData).toBe(false);
    expect(MXMED_RESTORE_SENTINEL_CONTRACT.integrated).toBe(false);
  });

  test('restore completed never means validated', () => {
    expect(MXMED_RESTORE_VALIDATION_PROFILE.completedMeansValidated).toBe(false);
    expect(MXMED_RESTORE_VALIDATION_PROFILE.validatorLambdaImplemented).toBe(false);
  });
});
