import { App } from 'aws-cdk-lib';
import type { Stack } from 'aws-cdk-lib';
import { Template as CdkTemplate } from 'aws-cdk-lib/assertions';

import type { MxMedEnvironmentConfig } from '../lib/config/environment-config';
import type { MxMedBackupDrContextValues } from '../lib/config/backup-dr-profiles';
import { getEnvironmentConfig } from '../lib/config/environments';
import { MxMedEnvironmentStage } from '../lib/stages/mxmed-environment-stage';

export type BackupTemplate = Readonly<{
  Resources?: Record<string, Readonly<{ Type?: string; Properties?: Record<string, unknown> }>>;
  Parameters?: Record<string, Readonly<Record<string, unknown>>>;
  Outputs?: Record<string, unknown>;
}>;

const ACTIVE_OPERATIONS = Object.freeze({
  operationsNotificationMode: 'topics-only-v1',
  operationsRuntimeGateState: 'blocked-known-runtime-gaps-v1',
  clinicalLogSanitizationState: 'blocked-legacy-agenda-logs-v1',
  costAllocationTagState: 'inactive-v1',
  costTagAnomalyMonitorMode: 'disabled-until-tags-active-v1',
});

export function activeBackupConfig(
  profile: 'launch-lean-v1' | 'production-standard-v1' | 'scale-ready-v1' = 'launch-lean-v1',
  backup: MxMedBackupDrContextValues = {
    backupDrActivationMode: 'regional-recovery-ready-v1',
  },
  environment: 'production' | 'staging' = 'production',
): MxMedEnvironmentConfig {
  return getEnvironmentConfig(
    environment,
    profile,
    'service-enabled-v1',
    'directory-core-v1',
    {},
    {
      ...ACTIVE_OPERATIONS,
      operationsActivationMode:
        profile === 'launch-lean-v1'
          ? 'launch-lean-observability-ready-v1'
          : 'production-observability-ready-v1',
    },
    backup,
  );
}

export function crossRegionBackupConfig(
  profile:
    'launch-lean-v1' | 'production-standard-v1' | 'scale-ready-v1' = 'production-standard-v1',
): MxMedEnvironmentConfig {
  return activeBackupConfig(profile, {
    backupDrActivationMode: 'cross-region-copy-ready-v1',
    drRegionState: 'selected-and-verified-v1',
    drRegion: 'us-test-1',
    backupDataResidencyState: 'approved-v1',
  });
}

export function restoreBackupConfig(
  restoreTestingMode: 'manual-quarterly-v1' | 'scheduled-monthly-v1',
): MxMedEnvironmentConfig {
  return activeBackupConfig('production-standard-v1', {
    backupDrActivationMode: 'restore-validation-ready-v1',
    drRegionState: 'selected-and-verified-v1',
    drRegion: 'us-test-1',
    backupDataResidencyState: 'approved-v1',
    restoreTestingMode,
    backupSentinelsIntegrated: restoreTestingMode === 'scheduled-monthly-v1',
    backupApplicationValidationIntegrated: restoreTestingMode === 'scheduled-monthly-v1',
  });
}

export function createBackupStage(config: MxMedEnvironmentConfig): MxMedEnvironmentStage {
  const app = new App({ analyticsReporting: false });
  return new MxMedEnvironmentStage(app, 'MxMedBackupFixture', { config });
}

export function backupTemplate(config: MxMedEnvironmentConfig): BackupTemplate {
  const stack = createBackupStage(config).regionalBackupStack;
  if (stack === undefined) throw new Error('backup-test-stack-missing');
  return templateJson(stack);
}

export function templateJson(stack: Stack): BackupTemplate {
  return CdkTemplate.fromStack(stack).toJSON();
}

export function resourceEntries(
  template: BackupTemplate,
  type?: string,
): readonly (readonly [
  string,
  Readonly<{ Type?: string; Properties?: Record<string, unknown> }>,
])[] {
  return Object.entries(template.Resources ?? {}).filter(([, resource]) =>
    type === undefined ? true : resource.Type === type,
  );
}

export function resourceProperties(
  template: BackupTemplate,
  type: string,
): Record<string, unknown>[] {
  return resourceEntries(template, type).map(([, resource]) => resource.Properties ?? {});
}

export function templateText(template: BackupTemplate): string {
  return JSON.stringify(template);
}
