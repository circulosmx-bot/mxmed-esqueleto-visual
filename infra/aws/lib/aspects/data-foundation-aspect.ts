import { Annotations, CfnDeletionPolicy, Stack } from 'aws-cdk-lib';
import type { IAspect } from 'aws-cdk-lib';
import {
  CfnDBCluster,
  CfnDBInstance,
  CfnDBParameterGroup,
  CfnDBProxy,
  CfnDBSubnetGroup,
} from 'aws-cdk-lib/aws-rds';
import { CfnSecret } from 'aws-cdk-lib/aws-secretsmanager';
import type { IConstruct } from 'constructs';

import type { MxMedEnvironmentConfig } from '../config/environment-config';
import { computeCreatesTasks } from '../config/compute-config';

const EXPECTED_PARAMETERS = Object.freeze({
  require_secure_transport: 'ON',
  character_set_server: 'utf8mb4',
  collation_server: 'utf8mb4_unicode_ci',
  time_zone: 'UTC',
  slow_query_log: '1',
  long_query_time: '1',
  log_output: 'FILE',
  general_log: '0',
  event_scheduler: 'OFF',
  binlog_format: 'ROW',
  lower_case_table_names: '0',
});

function sameStringArray(actual: unknown, expected: readonly string[]): boolean {
  return (
    Array.isArray(actual) &&
    actual.length === expected.length &&
    actual.every((entry, index) => entry === expected[index])
  );
}

function sameRecord(actual: unknown, expected: Readonly<Record<string, string>>): boolean {
  if (typeof actual !== 'object' || actual === null || Array.isArray(actual)) return false;
  const record = actual as Record<string, unknown>;
  const keys = Object.keys(expected);
  return (
    Object.keys(record).sort().join(',') === keys.sort().join(',') &&
    keys.every((key) => record[key] === expected[key])
  );
}

export class DataFoundationAspect implements IAspect {
  public constructor(private readonly config: MxMedEnvironmentConfig) {}

  public visit(node: IConstruct): void {
    if (node instanceof CfnDBCluster) {
      Annotations.of(node).addError('MXMED_DATA_DB_CLUSTER_FORBIDDEN');
      return;
    }
    if (node instanceof CfnDBProxy) {
      Annotations.of(node).addError('MXMED_DATA_DB_PROXY_FORBIDDEN');
      return;
    }
    if (node instanceof CfnSecret) {
      if (
        computeCreatesTasks(this.config.computeActivationMode) &&
        node.node.path.includes('/ApplicationUserSecret/Resource')
      ) {
        this.validateApplicationUserSecret(node);
      } else {
        Annotations.of(node).addError('MXMED_DATA_DUPLICATE_MASTER_SECRET_FORBIDDEN');
      }
      return;
    }
    if (node instanceof CfnDBParameterGroup) {
      const parameters = Stack.of(node).resolve(node.parameters) as unknown;
      if (node.family !== 'mysql8.4' || !sameRecord(parameters, EXPECTED_PARAMETERS)) {
        Annotations.of(node).addError('MXMED_DATA_PARAMETER_GROUP_INVALID');
      }
      const text = JSON.stringify(parameters).toLowerCase();
      if (text.includes('mysql_native_password') || text.includes('query_cache')) {
        Annotations.of(node).addError('MXMED_DATA_LEGACY_MYSQL_CONFIGURATION_FORBIDDEN');
      }
      return;
    }
    if (node instanceof CfnDBSubnetGroup) {
      const subnets = Stack.of(node).resolve(node.subnetIds) as unknown;
      if (!Array.isArray(subnets) || subnets.length !== 2) {
        Annotations.of(node).addError('MXMED_DATA_SUBNET_GROUP_INVALID');
      }
      const text = JSON.stringify(subnets).toLowerCase();
      if (
        !text.includes('isolateddata') ||
        text.includes('publicingress') ||
        text.includes('privateapp') ||
        text.includes('privateendpoints')
      ) {
        Annotations.of(node).addError('MXMED_DATA_SUBNET_TIER_INVALID');
      }
      return;
    }
    if (node instanceof CfnDBInstance) this.validateInstance(node);
  }

  private validateApplicationUserSecret(secret: CfnSecret): void {
    const generator = Stack.of(secret).resolve(secret.generateSecretString) as unknown;
    const expectedGenerator = {
      ExcludeCharacters: '"\'`\\/@',
      GenerateStringKey: 'password',
      IncludeSpace: false,
      PasswordLength: 64,
      RequireEachIncludedType: true,
      SecretStringTemplate: '{"username":"mxmed_app"}',
    };
    if (
      secret.name !== `/mxmed/${this.config.environmentName}/application/database-user` ||
      secret.kmsKeyId === undefined ||
      secret.secretString !== undefined ||
      JSON.stringify(generator) !== JSON.stringify(expectedGenerator) ||
      secret.cfnOptions.deletionPolicy !== CfnDeletionPolicy.RETAIN ||
      secret.cfnOptions.updateReplacePolicy !== CfnDeletionPolicy.RETAIN
    ) {
      Annotations.of(secret).addError('MXMED_DATA_APPLICATION_SECRET_INVALID');
    }
  }

  private validateInstance(instance: CfnDBInstance): void {
    const { config } = this;
    const resolved = Stack.of(instance).resolve({
      kmsKeyId: instance.kmsKeyId,
      masterUserSecret: instance.masterUserSecret,
      performanceInsightsKmsKeyId: instance.performanceInsightsKmsKeyId,
      vpcSecurityGroups: instance.vpcSecurityGroups,
    }) as unknown;
    const text = JSON.stringify(resolved);
    const applicationDataKeyReferences = text.match(/ApplicationDataKey/g)?.length ?? 0;
    const secretsKeyReferences = text.match(/SecretsKey/g)?.length ?? 0;
    const expectedRemoval =
      config.environmentName === 'production'
        ? CfnDeletionPolicy.RETAIN
        : CfnDeletionPolicy.SNAPSHOT;
    const invalid =
      instance.engine !== 'mysql' ||
      instance.engineVersion !== '8.4.9' ||
      instance.engineLifecycleSupport !== 'open-source-rds-extended-support-disabled' ||
      instance.dbInstanceClass !== config.databaseInstanceClass ||
      instance.dbName !== 'mxmed' ||
      instance.dbInstanceIdentifier !== undefined ||
      instance.dbSnapshotIdentifier !== undefined ||
      instance.dbParameterGroupName === undefined ||
      instance.dbSubnetGroupName === undefined ||
      instance.masterUsername !== 'mxmed_admin' ||
      instance.masterUserPassword !== undefined ||
      instance.manageMasterUserPassword !== true ||
      instance.publiclyAccessible !== false ||
      instance.networkType !== 'IPV4' ||
      instance.storageEncrypted !== true ||
      instance.storageType !== 'gp3' ||
      instance.allocatedStorage !== String(config.databaseAllocatedStorageGiB) ||
      instance.maxAllocatedStorage !== config.databaseMaxAllocatedStorageGiB ||
      instance.iops !== 3000 ||
      instance.storageThroughput !== 125 ||
      instance.backupRetentionPeriod !== config.databaseBackupRetentionDays ||
      instance.copyTagsToSnapshot !== true ||
      instance.deleteAutomatedBackups !== false ||
      instance.deletionProtection !== config.databaseDeletionProtection ||
      instance.multiAz !== config.databaseMultiAz ||
      instance.autoMinorVersionUpgrade !== false ||
      instance.allowMajorVersionUpgrade !== false ||
      instance.applyImmediately !== false ||
      instance.enablePerformanceInsights !== true ||
      instance.performanceInsightsRetentionPeriod !== 7 ||
      instance.databaseInsightsMode !== 'standard' ||
      instance.enableIamDatabaseAuthentication !== false ||
      instance.monitoringInterval !== config.databaseEnhancedMonitoringIntervalSeconds ||
      instance.monitoringRoleArn === undefined ||
      instance.port !== '3306' ||
      instance.preferredBackupWindow !== config.databasePreferredBackupWindow ||
      instance.preferredMaintenanceWindow !== config.databasePreferredMaintenanceWindow ||
      !sameStringArray(instance.enableCloudwatchLogsExports, ['error', 'slowquery']) ||
      instance.vpcSecurityGroups === undefined ||
      !Array.isArray(Stack.of(instance).resolve(instance.vpcSecurityGroups)) ||
      (Stack.of(instance).resolve(instance.vpcSecurityGroups) as unknown[]).length !== 1 ||
      instance.sourceDbInstanceIdentifier !== undefined ||
      instance.sourceDbiResourceId !== undefined ||
      instance.dbClusterIdentifier !== undefined ||
      instance.availabilityZone !== undefined ||
      instance.cfnOptions.deletionPolicy !== expectedRemoval ||
      instance.cfnOptions.updateReplacePolicy !== expectedRemoval ||
      applicationDataKeyReferences < 2 ||
      secretsKeyReferences < 1;
    if (invalid) Annotations.of(instance).addError('MXMED_DATA_DB_INSTANCE_INVALID');
  }
}
