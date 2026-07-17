import { MXMED_BACKUP_SCHEDULES } from '../constructs/backup-plan-catalog';

export const MXMED_RESTORE_TESTING_MONTHLY_SCHEDULE = 'cron(0 5 ? * SUN#1 *)';

export function assertMxMedBackupSchedule(schedule: string): void {
  const allowed = new Set([
    MXMED_BACKUP_SCHEDULES.daily,
    MXMED_BACKUP_SCHEDULES.firstSundayMonthly,
    MXMED_RESTORE_TESTING_MONTHLY_SCHEDULE,
  ]);
  if (!allowed.has(schedule)) throw new Error(`MXMED_BACKUP_SCHEDULE_INVALID:${schedule}`);
}
