import { getEnvironmentConfig, STAGING_CONFIG } from '../lib/config/environments';
import type { MxMedEnvironmentConfig } from '../lib/config/environment-config';
import { firstResource, properties, renderData, resourcesOfType } from './data-test-helpers';

const PRODUCTION_CONFIG = getEnvironmentConfig('production', 'production-standard-v1');

function instance(config: MxMedEnvironmentConfig) {
  const resource = firstResource(renderData(config).resources, 'AWS::RDS::DBInstance');
  return { resource, props: properties(resource) };
}

describe('RDS DB instance contract', () => {
  test('DATA-IMP-034 creates exactly one DB instance', () => {
    expect(
      resourcesOfType(renderData(STAGING_CONFIG).resources, 'AWS::RDS::DBInstance'),
    ).toHaveLength(1);
  });
  test('DATA-IMP-035 uses MySQL', () => {
    expect(instance(STAGING_CONFIG).props.Engine).toBe('mysql');
  });
  test('DATA-IMP-036 pins engine version 8.4.9', () => {
    expect(instance(STAGING_CONFIG).props.EngineVersion).toBe('8.4.9');
  });
  test('DATA-IMP-037 uses db.t4g.medium in staging', () => {
    expect(instance(STAGING_CONFIG).props.DBInstanceClass).toBe('db.t4g.medium');
  });
  test('DATA-IMP-038 uses db.m6g.large in production', () => {
    expect(instance(PRODUCTION_CONFIG).props.DBInstanceClass).toBe('db.m6g.large');
  });
  test('DATA-IMP-039 keeps staging Single-AZ', () => {
    expect(instance(STAGING_CONFIG).props.MultiAZ).toBe(false);
  });
  test('DATA-IMP-040 makes production Multi-AZ', () => {
    expect(instance(PRODUCTION_CONFIG).props.MultiAZ).toBe(true);
  });
  test('DATA-IMP-041 disables public access', () => {
    expect(instance(PRODUCTION_CONFIG).props.PubliclyAccessible).toBe(false);
  });
  test('DATA-IMP-042 uses IPv4', () => {
    expect(instance(PRODUCTION_CONFIG).props.NetworkType).toBe('IPV4');
  });
  test('DATA-IMP-043 uses port 3306', () => {
    expect(instance(STAGING_CONFIG).props.Port).toBe('3306');
  });
  test('DATA-IMP-044 uses gp3', () => {
    expect(instance(PRODUCTION_CONFIG).props.StorageType).toBe('gp3');
  });
  test('DATA-IMP-045 enables storage encryption', () => {
    expect(instance(PRODUCTION_CONFIG).props.StorageEncrypted).toBe(true);
  });
  test('DATA-IMP-046 uses ApplicationDataKey for data and Insights', () => {
    const props = instance(STAGING_CONFIG).props;
    expect(JSON.stringify(props.KmsKeyId)).toMatch(/ApplicationDataKey/);
    expect(JSON.stringify(props.PerformanceInsightsKMSKeyId)).toMatch(/ApplicationDataKey/);
  });
  test('DATA-IMP-047 delegates the master password to RDS', () => {
    expect(instance(PRODUCTION_CONFIG).props.ManageMasterUserPassword).toBe(true);
  });
  test('DATA-IMP-048 contains no MasterUserPassword', () => {
    expect(instance(STAGING_CONFIG).props).not.toHaveProperty('MasterUserPassword');
  });
  test('DATA-IMP-049 encrypts the RDS-managed secret with SecretsKey', () => {
    expect(JSON.stringify(instance(PRODUCTION_CONFIG).props.MasterUserSecret)).toMatch(
      /SecretsKey/,
    );
  });
  test('DATA-IMP-050 creates the mxmed database', () => {
    expect(instance(STAGING_CONFIG).props.DBName).toBe('mxmed');
  });
  test('DATA-IMP-051 uses a non-root master username', () => {
    const username = instance(PRODUCTION_CONFIG).props.MasterUsername;
    expect(username).toBe('mxmed_admin');
    expect(username).not.toMatch(/^(?:root|admin)$/);
  });
  test('DATA-IMP-052 sets environment backup retention', () => {
    expect(instance(STAGING_CONFIG).props.BackupRetentionPeriod).toBe(7);
    expect(instance(PRODUCTION_CONFIG).props.BackupRetentionPeriod).toBe(35);
  });
  test('DATA-IMP-053 copies tags to snapshots', () => {
    expect(instance(PRODUCTION_CONFIG).props.CopyTagsToSnapshot).toBe(true);
  });
  test('DATA-IMP-054 retains automated backups on removal', () => {
    expect(instance(PRODUCTION_CONFIG).props.DeleteAutomatedBackups).toBe(false);
  });
  test('DATA-IMP-055 disables automatic minor upgrades', () => {
    expect(instance(STAGING_CONFIG).props.AutoMinorVersionUpgrade).toBe(false);
  });
  test('DATA-IMP-056 disables major upgrades in this microphase', () => {
    expect(instance(PRODUCTION_CONFIG).props.AllowMajorVersionUpgrade).toBe(false);
  });
  test('DATA-IMP-057 exports only error and slowquery logs', () => {
    expect(instance(STAGING_CONFIG).props.EnableCloudwatchLogsExports).toEqual([
      'error',
      'slowquery',
    ]);
  });
  test('DATA-IMP-058 enables Standard Database Insights', () => {
    const props = instance(PRODUCTION_CONFIG).props;
    expect(props.DatabaseInsightsMode).toBe('standard');
    expect(props.EnablePerformanceInsights).toBe(true);
  });
  test('DATA-IMP-059 retains Performance Insights for seven days', () => {
    expect(instance(PRODUCTION_CONFIG).props.PerformanceInsightsRetentionPeriod).toBe(7);
  });
  test('DATA-IMP-060 configures Enhanced Monitoring', () => {
    expect(instance(STAGING_CONFIG).props.MonitoringInterval).toBe(60);
    expect(instance(PRODUCTION_CONFIG).props.MonitoringInterval).toBe(15);
    expect(JSON.stringify(instance(PRODUCTION_CONFIG).props.MonitoringRoleArn)).toMatch(
      /EnhancedMonitoringRole/,
    );
  });
});

describe('RDS removal contract', () => {
  test('DATA-IMP-061 snapshots staging on deletion', () => {
    expect(instance(STAGING_CONFIG).resource.DeletionPolicy).toBe('Snapshot');
  });
  test('DATA-IMP-062 retains production on deletion', () => {
    expect(instance(PRODUCTION_CONFIG).resource.DeletionPolicy).toBe('Retain');
  });
  test('DATA-IMP-063 snapshots staging on replacement', () => {
    expect(instance(STAGING_CONFIG).resource.UpdateReplacePolicy).toBe('Snapshot');
  });
  test('DATA-IMP-064 retains production on replacement', () => {
    expect(instance(PRODUCTION_CONFIG).resource.UpdateReplacePolicy).toBe('Retain');
  });
  test('DATA-IMP-065 enables production deletion protection', () => {
    expect(instance(PRODUCTION_CONFIG).props.DeletionProtection).toBe(true);
  });
});
