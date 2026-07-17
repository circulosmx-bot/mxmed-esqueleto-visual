import type { MxMedEnvironmentConfig } from '../config/environment-config';
import { mxmedName } from './naming';

export function regionalRecoveryVaultName(config: MxMedEnvironmentConfig): string {
  return `mxmed-${config.environmentName}-regional-recovery`;
}

export function drCopyVaultName(config: MxMedEnvironmentConfig): string {
  return `mxmed-${config.environmentName}-dr-copy`;
}

export function backupRoleName(config: MxMedEnvironmentConfig): string {
  return mxmedName(config.environmentCode, 'backup-service-role', 64);
}

export function restoreRoleName(config: MxMedEnvironmentConfig): string {
  return mxmedName(config.environmentCode, 'restore-validation-role', 64);
}

export function restoreTestingName(config: MxMedEnvironmentConfig): string {
  return `mxmed_${config.environmentCode}_restore_validation_v1`;
}
