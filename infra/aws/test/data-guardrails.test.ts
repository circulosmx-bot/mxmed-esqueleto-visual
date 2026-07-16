import { App, AspectPriority, Aspects, RemovalPolicy, Stack } from 'aws-cdk-lib';
import { CfnDBCluster, CfnDBInstance, CfnDBParameterGroup, CfnDBProxy } from 'aws-cdk-lib/aws-rds';
import { Annotations as AssertionAnnotations } from 'aws-cdk-lib/assertions';

import { DataFoundationAspect } from '../lib/aspects/data-foundation-aspect';
import { PRODUCTION_CONFIG, STAGING_CONFIG } from '../lib/config/environments';
import type { MxMedEnvironmentConfig } from '../lib/config/environment-config';
import { renderData, resourcesOfType } from './data-test-helpers';

function syntheticStack(config: MxMedEnvironmentConfig): Stack {
  const stack = new Stack(new App({ analyticsReporting: false }), 'SyntheticDataGuardrail');
  Aspects.of(stack).add(new DataFoundationAspect(config), { priority: AspectPriority.READONLY });
  return stack;
}

function syntheticInstance(
  config: MxMedEnvironmentConfig,
  overrides: Partial<CfnDBInstance>,
): { readonly stack: Stack; readonly instance: CfnDBInstance } {
  const stack = syntheticStack(config);
  const instance = new CfnDBInstance(stack, 'SyntheticDatabase', {
    allocatedStorage: String(config.databaseAllocatedStorageGiB),
    allowMajorVersionUpgrade: false,
    applyImmediately: false,
    autoMinorVersionUpgrade: false,
    backupRetentionPeriod: config.databaseBackupRetentionDays,
    copyTagsToSnapshot: true,
    databaseInsightsMode: 'standard',
    dbInstanceClass: config.databaseInstanceClass,
    dbName: 'mxmed',
    deleteAutomatedBackups: false,
    deletionProtection: config.databaseDeletionProtection,
    enableCloudwatchLogsExports: ['error', 'slowquery'],
    enableIamDatabaseAuthentication: false,
    enablePerformanceInsights: true,
    engine: 'mysql',
    engineLifecycleSupport: 'open-source-rds-extended-support-disabled',
    engineVersion: '8.4.9',
    iops: 3000,
    kmsKeyId: 'ApplicationDataKey',
    manageMasterUserPassword: true,
    masterUsername: 'mxmed_admin',
    masterUserSecret: { kmsKeyId: 'SecretsKey' },
    maxAllocatedStorage: config.databaseMaxAllocatedStorageGiB,
    monitoringInterval: config.databaseEnhancedMonitoringIntervalSeconds,
    monitoringRoleArn: 'EnhancedMonitoringRole',
    multiAz: config.databaseMultiAz,
    networkType: 'IPV4',
    performanceInsightsKmsKeyId: 'ApplicationDataKey',
    performanceInsightsRetentionPeriod: 7,
    preferredBackupWindow: config.databasePreferredBackupWindow,
    preferredMaintenanceWindow: config.databasePreferredMaintenanceWindow,
    publiclyAccessible: false,
    storageEncrypted: true,
    storageThroughput: 125,
    storageType: 'gp3',
    vpcSecurityGroups: ['DatabaseSecurityGroup'],
  });
  Object.assign(instance, overrides);
  instance.applyRemovalPolicy(
    config.environmentName === 'production' ? RemovalPolicy.RETAIN : RemovalPolicy.SNAPSHOT,
    { applyToUpdateReplacePolicy: true },
  );
  return { stack, instance };
}

function expectInvalid(instance: ReturnType<typeof syntheticInstance>): void {
  AssertionAnnotations.fromStack(instance.stack).hasError('*', 'MXMED_DATA_DB_INSTANCE_INVALID');
}

describe('data foundation guardrails', () => {
  test('DATA-IMP-075 rejects a non-MySQL engine', () => {
    expectInvalid(syntheticInstance(STAGING_CONFIG, { engine: 'postgres' }));
  });
  test('DATA-IMP-076 rejects a public database', () => {
    expectInvalid(syntheticInstance(STAGING_CONFIG, { publiclyAccessible: true }));
  });
  test('DATA-IMP-077 rejects a plaintext master password', () => {
    expectInvalid(syntheticInstance(STAGING_CONFIG, { masterUserPassword: 'synthetic-value' }));
  });
  test('DATA-IMP-078 rejects zero backup retention', () => {
    expectInvalid(syntheticInstance(STAGING_CONFIG, { backupRetentionPeriod: 0 }));
  });
  test('DATA-IMP-079 rejects production Single-AZ', () => {
    expectInvalid(syntheticInstance(PRODUCTION_CONFIG, { multiAz: false }));
  });
  test('DATA-IMP-080 rejects production DESTROY', () => {
    const subject = syntheticInstance(PRODUCTION_CONFIG, {});
    subject.instance.applyRemovalPolicy(RemovalPolicy.DESTROY, {
      applyToUpdateReplacePolicy: true,
    });
    expectInvalid(subject);
  });
  test('DATA-IMP-081 rejects disabled secure transport', () => {
    const stack = syntheticStack(STAGING_CONFIG);
    new CfnDBParameterGroup(stack, 'SyntheticParameters', {
      description: 'synthetic invalid parameter group',
      family: 'mysql8.4',
      parameters: { require_secure_transport: 'OFF' },
    });
    AssertionAnnotations.fromStack(stack).hasError('*', 'MXMED_DATA_PARAMETER_GROUP_INVALID');
  });
  test('DATA-IMP-082 rejects general log export', () => {
    expectInvalid(
      syntheticInstance(STAGING_CONFIG, {
        enableCloudwatchLogsExports: ['error', 'slowquery', 'general'],
      }),
    );
  });
  test('DATA-IMP-083 creates no Aurora or DB cluster', () => {
    const resources = renderData(PRODUCTION_CONFIG).resources;
    expect(resourcesOfType(resources, 'AWS::RDS::DBCluster')).toEqual([]);
    expect(CfnDBCluster).toBeDefined();
  });
  test('DATA-IMP-084 creates no RDS Proxy', () => {
    expect(resourcesOfType(renderData(PRODUCTION_CONFIG).resources, 'AWS::RDS::DBProxy')).toEqual(
      [],
    );
    expect(CfnDBProxy).toBeDefined();
  });
  test('DATA-IMP-085 creates no read replica', () => {
    const instance = resourcesOfType(
      renderData(PRODUCTION_CONFIG).resources,
      'AWS::RDS::DBInstance',
    )[0]?.[1];
    expect(instance?.Properties).not.toHaveProperty('SourceDBInstanceIdentifier');
  });
});
