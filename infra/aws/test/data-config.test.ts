import { PRODUCTION_CONFIG, STAGING_CONFIG } from '../lib/config/environments';

describe('data configuration contract', () => {
  test('DATA-IMP-001 selects MySQL', () => {
    expect([STAGING_CONFIG.databaseEngine, PRODUCTION_CONFIG.databaseEngine]).toEqual([
      'mysql',
      'mysql',
    ]);
  });
  test('DATA-IMP-002 pins version 8.4.9', () => {
    expect([STAGING_CONFIG.databaseEngineVersion, PRODUCTION_CONFIG.databaseEngineVersion]).toEqual(
      ['8.4.9', '8.4.9'],
    );
  });
  test('DATA-IMP-003 pins family mysql8.4', () => {
    expect([
      STAGING_CONFIG.databaseParameterGroupFamily,
      PRODUCTION_CONFIG.databaseParameterGroupFamily,
    ]).toEqual(['mysql8.4', 'mysql8.4']);
  });
  test('DATA-IMP-004 disables extended support enrollment', () => {
    expect(STAGING_CONFIG.databaseEngineLifecycleSupport).toBe(
      'open-source-rds-extended-support-disabled',
    );
    expect(PRODUCTION_CONFIG.databaseEngineLifecycleSupport).toBe(
      'open-source-rds-extended-support-disabled',
    );
  });
  test('DATA-IMP-005 selects the staging class', () => {
    expect(STAGING_CONFIG.databaseInstanceClass).toBe('db.t4g.medium');
  });
  test('DATA-IMP-006 selects the production class', () => {
    expect(PRODUCTION_CONFIG.databaseInstanceClass).toBe('db.m6g.large');
  });
  test('DATA-IMP-007 keeps staging Single-AZ', () => {
    expect(STAGING_CONFIG.databaseMultiAz).toBe(false);
  });
  test('DATA-IMP-008 makes production Multi-AZ', () => {
    expect(PRODUCTION_CONFIG.databaseMultiAz).toBe(true);
  });
  test('DATA-IMP-009 starts staging at 40 GiB', () => {
    expect(STAGING_CONFIG.databaseAllocatedStorageGiB).toBe(40);
  });
  test('DATA-IMP-010 starts production at 100 GiB', () => {
    expect(PRODUCTION_CONFIG.databaseAllocatedStorageGiB).toBe(100);
  });
  test('DATA-IMP-011 sets contracted storage ceilings', () => {
    expect([
      STAGING_CONFIG.databaseMaxAllocatedStorageGiB,
      PRODUCTION_CONFIG.databaseMaxAllocatedStorageGiB,
    ]).toEqual([200, 1000]);
  });
  test('DATA-IMP-012 sets 3000 IOPS', () => {
    expect([STAGING_CONFIG.databaseIops, PRODUCTION_CONFIG.databaseIops]).toEqual([3000, 3000]);
  });
  test('DATA-IMP-013 sets 125 MiB/s throughput', () => {
    expect([
      STAGING_CONFIG.databaseStorageThroughput,
      PRODUCTION_CONFIG.databaseStorageThroughput,
    ]).toEqual([125, 125]);
  });
  test('DATA-IMP-014 sets 7 and 35 day backup retention', () => {
    expect([
      STAGING_CONFIG.databaseBackupRetentionDays,
      PRODUCTION_CONFIG.databaseBackupRetentionDays,
    ]).toEqual([7, 35]);
  });
  test('DATA-IMP-015 protects production deletion only', () => {
    expect([
      STAGING_CONFIG.databaseDeletionProtection,
      PRODUCTION_CONFIG.databaseDeletionProtection,
    ]).toEqual([false, true]);
  });
  test('DATA-IMP-016 selects Standard Database Insights', () => {
    expect([STAGING_CONFIG.databaseInsightsMode, PRODUCTION_CONFIG.databaseInsightsMode]).toEqual([
      'standard',
      'standard',
    ]);
  });
  test('DATA-IMP-017 sets Enhanced Monitoring intervals', () => {
    expect([
      STAGING_CONFIG.databaseEnhancedMonitoringIntervalSeconds,
      PRODUCTION_CONFIG.databaseEnhancedMonitoringIntervalSeconds,
    ]).toEqual([60, 15]);
  });
  test('DATA-IMP-018 sets non-overlapping operational windows', () => {
    expect(STAGING_CONFIG.databasePreferredBackupWindow).toBe('00:00-00:30');
    expect(PRODUCTION_CONFIG.databasePreferredMaintenanceWindow).toBe('sun:02:30-sun:03:30');
  });
  test('DATA-IMP-019 exports only error and slowquery', () => {
    expect(STAGING_CONFIG.databaseCloudWatchLogsExports).toEqual(['error', 'slowquery']);
    expect(PRODUCTION_CONFIG.databaseCloudWatchLogsExports).toEqual(['error', 'slowquery']);
  });
  test('DATA-IMP-020 preserves the PP255 collation', () => {
    expect([STAGING_CONFIG.databaseCollation, PRODUCTION_CONFIG.databaseCollation]).toEqual([
      'utf8mb4_unicode_ci',
      'utf8mb4_unicode_ci',
    ]);
  });
});
