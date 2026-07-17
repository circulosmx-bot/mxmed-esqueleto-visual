import type { MxMedEnvironmentConfig } from '../config/environment-config';
import type { MxMedAlarmDefinition } from '../constructs/operations-alarm-catalog';
import { operationsRunbook } from '../constructs/operations-runbook-catalog';

const SAFE_CODE_PATTERN = /^[a-z0-9_]+$/;
const FORBIDDEN_TEXT =
  /(?:https?:\/\/|@|authorization|cookie|stripe-signature|client_secret|request.?body|full.?query|patient|doctor|session[_ -]?id|account[_ -]?id|token|secret value)/i;

export function buildOperationsAlarmDescription(
  config: MxMedEnvironmentConfig,
  alarm: MxMedAlarmDefinition,
  thresholdSummary: string,
): string {
  operationsRunbook(alarm.runbookId);
  if (!SAFE_CODE_PATTERN.test(alarm.sanitizedCode) || FORBIDDEN_TEXT.test(thresholdSummary)) {
    throw new Error('MXMED_OPERATIONS_ALARM_DESCRIPTION_UNSAFE');
  }
  return [
    `severity=${alarm.severity}`,
    `runbook=${alarm.runbookId}`,
    `code=${alarm.sanitizedCode}`,
    `environment=${config.environmentName}`,
    `operations_profile=${config.operationsActivationMode}`,
    `threshold=${thresholdSummary}`,
  ].join(';');
}

export function assertNoPersonalNotificationTarget(value: string): void {
  if (/@|^\+?[0-9][0-9 ()-]{6,}$|^https?:\/\//i.test(value)) {
    throw new Error('MXMED_OPERATIONS_PERSONAL_NOTIFICATION_TARGET_FORBIDDEN');
  }
}

export function assertOperationsDimensionName(name: string): void {
  if (!['Environment', 'Component', 'Result', 'RuntimeCapabilityProfile'].includes(name)) {
    throw new Error(`MXMED_APPLICATION_METRIC_DIMENSION_FORBIDDEN:${name}`);
  }
}
