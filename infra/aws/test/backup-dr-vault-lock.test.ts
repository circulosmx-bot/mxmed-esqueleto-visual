import { CfnDeletionPolicy } from 'aws-cdk-lib';

import {
  activeBackupConfig,
  backupTemplate,
  createBackupStage,
  resourceEntries,
  resourceProperties,
  templateText,
} from './backup-dr-test-helpers';

const template = backupTemplate(activeBackupConfig());

describe('regional recovery vault and governance lock', () => {
  test('creates exactly one named recovery vault', () => {
    const vaults = resourceProperties(template, 'AWS::Backup::BackupVault');
    expect(vaults).toHaveLength(1);
    expect(vaults[0]?.BackupVaultName).toBe('mxmed-production-regional-recovery');
  });

  test.each([
    ['MinRetentionDays', 1],
    ['MaxRetentionDays', 365],
  ] as const)('sets governance %s=%s', (field, expected) => {
    const vault = resourceProperties(template, 'AWS::Backup::BackupVault')[0];
    const lock = vault?.LockConfiguration as Record<string, unknown> | undefined;
    expect(lock?.[field]).toBe(expected);
  });

  test('omits ChangeableForDays in governance mode', () => {
    expect(templateText(template)).not.toContain('ChangeableForDays');
  });

  test('encrypts the vault with the existing Security BackupKey reference', () => {
    const vault = resourceProperties(template, 'AWS::Backup::BackupVault')[0];
    expect(JSON.stringify(vault?.EncryptionKeyArn)).toContain('BackupKey');
  });

  test('retains the vault on delete and replacement', () => {
    const vault = resourceEntries(template, 'AWS::Backup::BackupVault')[0]?.[1];
    expect(vault).toMatchObject({ DeletionPolicy: 'Retain', UpdateReplacePolicy: 'Retain' });
  });

  test('does not synthesize a public or application access policy', () => {
    const vault = resourceProperties(template, 'AWS::Backup::BackupVault')[0];
    expect(vault?.AccessPolicy).toBeUndefined();
    expect(templateText(template)).not.toMatch(/Principal[^}]*\*/);
  });

  test('does not synthesize the default vault', () => {
    expect(templateText(template)).not.toMatch(/DefaultBackupVault|default-vault/i);
  });

  test('reuses exactly one Security-owned BackupKey', () => {
    const stage = createBackupStage(activeBackupConfig());
    expect(stage.regionalBackupStack?.backupKey).toBe(stage.securityStack.backupKey);
  });

  test('keeps the Security BackupKey rotation enabled', () => {
    const stage = createBackupStage(activeBackupConfig());
    expect(stage.securityStack.backupKey.node.defaultChild).toMatchObject({
      enableKeyRotation: true,
    });
  });

  test('keeps the Security BackupKey retention policy', () => {
    const stage = createBackupStage(activeBackupConfig());
    const key = stage.securityStack.backupKey.node.defaultChild as unknown as {
      cfnOptions: { deletionPolicy?: CfnDeletionPolicy };
    };
    expect(key.cfnOptions.deletionPolicy).toBe(CfnDeletionPolicy.RETAIN);
  });
});
