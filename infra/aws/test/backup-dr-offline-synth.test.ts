import { Stack } from 'aws-cdk-lib';

import { getEnvironmentConfig } from '../lib/config/environments';
import {
  activeBackupConfig,
  backupTemplate,
  createBackupStage,
  crossRegionBackupConfig,
  resourceEntries,
  restoreBackupConfig,
  templateJson,
  templateText,
} from './backup-dr-test-helpers';

describe('offline and deterministic Backup/DR synthesis', () => {
  test.each([
    ['production launch', activeBackupConfig()],
    ['production standard', activeBackupConfig('production-standard-v1')],
    ['production scale', activeBackupConfig('scale-ready-v1')],
    ['staging release window', activeBackupConfig('launch-lean-v1', undefined, 'staging')],
  ] as const)('%s fixture synthesizes without lookups', (_label, config) => {
    const stage = createBackupStage(config);
    expect(stage.regionalBackupStack).toBeDefined();
    expect(templateText(backupTemplate(config))).not.toMatch(/AWS::CDK::Metadata|ContextProvider/);
  });

  test('disabled real config synthesizes zero AWS Backup resources', () => {
    const stage = createBackupStage(getEnvironmentConfig('production', 'launch-lean-v1'));
    const allTemplates = stage.node.children
      .filter((child): child is Stack => Stack.isStack(child))
      .map((child) => templateText(templateJson(child)));
    expect(allTemplates.join('')).not.toContain('AWS::Backup::');
  });

  test.each([
    ['cross-region', crossRegionBackupConfig()],
    ['restore manual', restoreBackupConfig('manual-quarterly-v1')],
    ['restore scheduled', restoreBackupConfig('scheduled-monthly-v1')],
  ] as const)('%s fixture synthesizes fully offline', (_label, config) => {
    const stage = createBackupStage(config);
    expect(stage.regionalBackupStack).toBeDefined();
    expect(stage.drCopyStack).toBeDefined();
  });

  test('regional templates are byte-deterministic for the same config', () => {
    const first = templateText(backupTemplate(activeBackupConfig('production-standard-v1')));
    const second = templateText(backupTemplate(activeBackupConfig('production-standard-v1')));
    expect(second).toBe(first);
  });

  test.each([
    /\b[0-9]{12}\b/,
    /client_secret/i,
    /SecretString/,
    /patient|doctor.?id|diagnosis/i,
    /automatic.?failover|automatic.?failback/i,
  ])('template omits prohibited literal %s', (pattern) => {
    expect(templateText(backupTemplate(activeBackupConfig()))).not.toMatch(pattern);
  });

  test('creates no Lambda, air-gapped vault, Audit Manager or report plan', () => {
    const template = backupTemplate(activeBackupConfig('scale-ready-v1'));
    for (const type of [
      'AWS::Lambda::Function',
      'AWS::Backup::LogicallyAirGappedBackupVault',
      'AWS::Backup::Framework',
      'AWS::Backup::ReportPlan',
    ]) {
      expect(resourceEntries(template, type)).toHaveLength(0);
    }
  });

  test('all fixture resources remain only synthesized, never deployed', () => {
    const stage = createBackupStage(activeBackupConfig());
    expect(stage.regionalBackupStack?.artifactId).toContain('RegionalBackup');
    expect(process.env.CDK_DEPLOY_REGION).toBeUndefined();
  });
});
