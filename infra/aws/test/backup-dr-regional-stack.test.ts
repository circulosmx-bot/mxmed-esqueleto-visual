import type { Stack } from 'aws-cdk-lib';

import { getEnvironmentConfig } from '../lib/config/environments';
import {
  activeBackupConfig,
  backupTemplate,
  createBackupStage,
  resourceEntries,
  templateText,
} from './backup-dr-test-helpers';

describe('regional backup stage topology', () => {
  test.each([
    ['staging', 'launch-lean-v1'],
    ['production', 'launch-lean-v1'],
    ['production', 'production-standard-v1'],
    ['production', 'scale-ready-v1'],
  ] as const)('keeps Backup stacks absent for disabled %s %s', (environment, profile) => {
    const stage = createBackupStage(getEnvironmentConfig(environment, profile));
    expect(stage.regionalBackupStack).toBeUndefined();
    expect(stage.drCopyStack).toBeUndefined();
    expect(stage.restoreValidationStack).toBeUndefined();
  });

  test('creates the regional stack only for regional activation', () => {
    const stage = createBackupStage(activeBackupConfig());
    expect(stage.regionalBackupStack).toBeDefined();
    expect(stage.drCopyStack).toBeUndefined();
    expect(stage.restoreValidationStack).toBeUndefined();
  });

  test.each([
    ['AWS::Backup::BackupVault', 1],
    ['AWS::Backup::BackupPlan', 2],
    ['AWS::Backup::BackupSelection', 2],
    ['AWS::Events::Rule', 1],
  ] as const)('creates exactly %s x %d', (type, count) => {
    expect(resourceEntries(backupTemplate(activeBackupConfig()), type)).toHaveLength(count);
  });

  test('uses the configured primary region', () => {
    const stack = createBackupStage(activeBackupConfig()).regionalBackupStack;
    expect(stack?.region).toBe('mx-central-1');
  });

  test('depends only on approved source and Operations stacks', () => {
    const stack = createBackupStage(activeBackupConfig()).regionalBackupStack;
    expect(stack).toBeDefined();
    const dependencies = (stack?.dependencies ?? []).map((entry) => entry.stackName).sort();
    expect(dependencies).toEqual(
      [
        'mxmed-prd-data',
        'mxmed-prd-operations-regional',
        'mxmed-prd-security',
        'mxmed-prd-storage',
      ].sort(),
    );
  });

  test('does not create a dependency from a functional source stack to Backup', () => {
    const stage = createBackupStage(activeBackupConfig());
    for (const stack of [stage.dataStack, stage.storageStack, stage.operationsStack]) {
      expect(stack?.dependencies).not.toContain(stage.regionalBackupStack as Stack);
    }
  });

  test('contains no cross-region, restore or failover resources in regional mode', () => {
    expect(templateText(backupTemplate(activeBackupConfig()))).not.toMatch(
      /RestoreTesting|CopyAction|automatic.?fail/i,
    );
  });
});
