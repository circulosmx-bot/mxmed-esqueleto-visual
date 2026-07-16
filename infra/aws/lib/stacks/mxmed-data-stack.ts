import { AspectPriority, Aspects, RemovalPolicy } from 'aws-cdk-lib';
import type { ISecurityGroup, ISubnet, Vpc } from 'aws-cdk-lib/aws-ec2';
import { ManagedPolicy, Role, ServicePrincipal } from 'aws-cdk-lib/aws-iam';
import type { IRole } from 'aws-cdk-lib/aws-iam';
import type { IKey } from 'aws-cdk-lib/aws-kms';
import {
  CfnDBInstance,
  CfnDBParameterGroup,
  CfnDBSubnetGroup,
  MysqlEngineVersion,
} from 'aws-cdk-lib/aws-rds';
import { Secret } from 'aws-cdk-lib/aws-secretsmanager';
import type { ISecret } from 'aws-cdk-lib/aws-secretsmanager';
import type { Construct } from 'constructs';

import { BaseMxMedStack } from './base-mxmed-stack';
import type { MxMedContractStackProps } from './base-mxmed-stack';
import { DataFoundationAspect } from '../aspects/data-foundation-aspect';
import { LeastPrivilegeIamAspect } from '../aspects/least-privilege-iam-aspect';
import { registerMxMedDataValidation } from '../utils/data-validation';

const MYSQL_PORT = 3306;
const STANDARD_INSIGHTS_RETENTION_DAYS = 7;

const MYSQL_PARAMETERS = Object.freeze({
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

export interface MxMedDataStackProps extends MxMedContractStackProps {
  readonly vpc: Vpc;
  readonly isolatedDataSubnets: readonly ISubnet[];
  readonly databaseSecurityGroup: ISecurityGroup;
  readonly applicationDataKey: IKey;
  readonly secretsKey: IKey;
  readonly migrationTaskRole: IRole;
}

/** RDS MySQL 8.4 data foundation for one MXMed environment. */
export class MxMedDataStack extends BaseMxMedStack {
  public readonly databaseInstance: CfnDBInstance;
  public readonly databaseEndpoint: string;
  public readonly databasePort: string;
  public readonly masterUserSecret: ISecret;
  public readonly parameterGroup: CfnDBParameterGroup;
  public readonly subnetGroup: CfnDBSubnetGroup;
  public readonly monitoringRole: Role;
  public readonly databaseName: string;

  public constructor(scope: Construct, id: string, props: MxMedDataStackProps) {
    super(scope, id, {
      ...props,
      component: 'data',
      description: 'MXMed RDS MySQL 8.4 encrypted data foundation.',
      metadata: { dataClassification: 'clinical', criticality: 'high', backup: 'required' },
    });

    if (props.isolatedDataSubnets.length !== 2) {
      throw new Error('MXMED_DATA_SUBNET_COUNT_INVALID');
    }

    const { config } = props;
    this.databaseName = config.databaseName;
    this.subnetGroup = new CfnDBSubnetGroup(this, 'DatabaseSubnetGroup', {
      dbSubnetGroupDescription: 'MXMed RDS subnet group with exactly two isolated-data subnets.',
      subnetIds: props.isolatedDataSubnets.map((subnet) => subnet.subnetId),
    });
    this.parameterGroup = new CfnDBParameterGroup(this, 'DatabaseParameterGroup', {
      description: 'MXMed MySQL 8.4 security, charset and logging parameters.',
      family: config.databaseParameterGroupFamily,
      parameters: MYSQL_PARAMETERS,
    });
    this.monitoringRole = new Role(this, 'EnhancedMonitoringRole', {
      assumedBy: new ServicePrincipal('monitoring.rds.amazonaws.com'),
      description: 'MXMed RDS Enhanced Monitoring service role.',
      managedPolicies: [
        ManagedPolicy.fromAwsManagedPolicyName('service-role/AmazonRDSEnhancedMonitoringRole'),
      ],
    });

    this.databaseInstance = new CfnDBInstance(this, 'DatabaseInstance', {
      allocatedStorage: String(config.databaseAllocatedStorageGiB),
      allowMajorVersionUpgrade: false,
      applyImmediately: false,
      autoMinorVersionUpgrade: false,
      backupRetentionPeriod: config.databaseBackupRetentionDays,
      copyTagsToSnapshot: true,
      databaseInsightsMode: config.databaseInsightsMode,
      dbInstanceClass: config.databaseInstanceClass,
      dbName: config.databaseName,
      dbParameterGroupName: this.parameterGroup.ref,
      dbSubnetGroupName: this.subnetGroup.ref,
      deleteAutomatedBackups: false,
      deletionProtection: config.databaseDeletionProtection,
      enableCloudwatchLogsExports: [...config.databaseCloudWatchLogsExports],
      enableIamDatabaseAuthentication: false,
      enablePerformanceInsights: true,
      engine: config.databaseEngine,
      engineLifecycleSupport: config.databaseEngineLifecycleSupport,
      engineVersion: MysqlEngineVersion.VER_8_4_9.mysqlFullVersion,
      iops: config.databaseIops,
      kmsKeyId: props.applicationDataKey.keyArn,
      manageMasterUserPassword: true,
      masterUsername: config.databaseMasterUsername,
      masterUserSecret: { kmsKeyId: props.secretsKey.keyArn },
      maxAllocatedStorage: config.databaseMaxAllocatedStorageGiB,
      monitoringInterval: config.databaseEnhancedMonitoringIntervalSeconds,
      monitoringRoleArn: this.monitoringRole.roleArn,
      multiAz: config.databaseMultiAz,
      networkType: 'IPV4',
      performanceInsightsKmsKeyId: props.applicationDataKey.keyArn,
      performanceInsightsRetentionPeriod: STANDARD_INSIGHTS_RETENTION_DAYS,
      port: String(MYSQL_PORT),
      preferredBackupWindow: config.databasePreferredBackupWindow,
      preferredMaintenanceWindow: config.databasePreferredMaintenanceWindow,
      publiclyAccessible: false,
      storageEncrypted: true,
      storageThroughput: config.databaseStorageThroughput,
      storageType: config.databaseStorageType,
      vpcSecurityGroups: [props.databaseSecurityGroup.securityGroupId],
    });
    this.databaseInstance.addDependency(this.parameterGroup);
    this.databaseInstance.addDependency(this.subnetGroup);
    this.databaseInstance.applyRemovalPolicy(
      config.environmentName === 'production' ? RemovalPolicy.RETAIN : RemovalPolicy.SNAPSHOT,
      { applyToUpdateReplacePolicy: true },
    );

    this.databaseEndpoint = this.databaseInstance.attrEndpointAddress;
    this.databasePort = this.databaseInstance.attrEndpointPort;
    this.masterUserSecret = Secret.fromSecretCompleteArn(
      this,
      'MasterUserSecretReference',
      this.databaseInstance.attrMasterUserSecretSecretArn,
    );

    // Data owns no grants yet. These typed references reserve the PP255 integration boundary.
    void props.vpc;
    void props.migrationTaskRole;

    Aspects.of(this).add(new DataFoundationAspect(config), {
      priority: AspectPriority.READONLY,
    });
    Aspects.of(this).add(new LeastPrivilegeIamAspect(), {
      priority: AspectPriority.READONLY,
    });
    registerMxMedDataValidation(this, config);
  }
}
