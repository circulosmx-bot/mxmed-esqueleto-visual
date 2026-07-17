import {
  activeBackupConfig,
  backupTemplate,
  createBackupStage,
  resourceEntries,
  resourceProperties,
  templateJson,
  templateText,
} from './backup-dr-test-helpers';

describe('Backup KMS boundaries', () => {
  test('does not create a second regional Backup key', () => {
    expect(resourceEntries(backupTemplate(activeBackupConfig()), 'AWS::KMS::Key')).toHaveLength(0);
  });

  test('keeps exactly four Security-owned keys including BackupKey', () => {
    const stage = createBackupStage(activeBackupConfig());
    const template = templateJson(stage.securityStack);
    expect(resourceEntries(template, 'AWS::KMS::Key')).toHaveLength(4);
    expect(
      resourceEntries(template, 'AWS::KMS::Key').some(([id]) => id.includes('BackupKey')),
    ).toBe(true);
  });

  test.each(['ApplicationDataKey', 'BackupKey'])(
    'grants the dedicated role use of %s without plaintext material',
    (keyName) => {
      const text = templateText(backupTemplate(activeBackupConfig()));
      expect(text).toContain(keyName);
      expect(text).not.toMatch(/KeyMaterial|Plaintext|SecretString/);
    },
  );

  test.each(['kms:Encrypt', 'kms:Decrypt', 'kms:ReEncrypt*', 'kms:GenerateDataKey*'])(
    'grants the expected non-destructive key action %s',
    (action) => {
      const policies = JSON.stringify(
        resourceProperties(backupTemplate(activeBackupConfig()), 'AWS::IAM::Policy'),
      );
      expect(policies).toContain(action);
    },
  );

  test('never grants key deletion or key-policy mutation', () => {
    const text = templateText(backupTemplate(activeBackupConfig()));
    expect(text).not.toMatch(/ScheduleKeyDeletion|PutKeyPolicy|DisableKey/);
  });
});
