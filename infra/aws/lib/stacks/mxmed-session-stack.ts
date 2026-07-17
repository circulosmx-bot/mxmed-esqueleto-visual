import { AspectPriority, Aspects, RemovalPolicy, Tags } from 'aws-cdk-lib';
import type { ISubnet, ISecurityGroup, Vpc } from 'aws-cdk-lib/aws-ec2';
import {
  CfnParameterGroup,
  CfnReplicationGroup,
  CfnSubnetGroup,
  CfnUser,
  CfnUserGroup,
} from 'aws-cdk-lib/aws-elasticache';
import type { IKey } from 'aws-cdk-lib/aws-kms';
import { CfnSecret, Secret } from 'aws-cdk-lib/aws-secretsmanager';
import type { Construct } from 'constructs';

import { BaseMxMedStack } from './base-mxmed-stack';
import type { MxMedContractStackProps } from './base-mxmed-stack';
import { SessionFoundationAspect } from '../aspects/session-foundation-aspect';
import {
  SESSION_LOCK_CONTRACT,
  buildSessionApplicationAccessString,
  buildSessionPrefix,
} from '../constructs/session-contract';
import type { SessionLockContract } from '../constructs/session-contract';
import { mxmedName } from '../utils/naming';
import { registerMxMedSessionValidation } from '../utils/session-validation';

const SESSION_PORT = 6379;
const DEFAULT_DISABLED_ACCESS_STRING = 'off ~* -@all';
const SESSION_PARAMETERS = Object.freeze({
  'maxmemory-policy': 'volatile-ttl',
  timeout: '300',
  'notify-keyspace-events': '',
  activerehashing: 'yes',
  'tcp-keepalive': '60',
});

export interface MxMedSessionStackProps extends MxMedContractStackProps {
  readonly vpc: Vpc;
  readonly isolatedDataSubnets: readonly ISubnet[];
  readonly sessionSecurityGroup: ISecurityGroup;
  readonly applicationDataKey: IKey;
  readonly secretsKey: IKey;
}

/** Dedicated encrypted ElastiCache Valkey session foundation for one environment. */
export class MxMedSessionStack extends BaseMxMedStack {
  public readonly replicationGroup: CfnReplicationGroup;
  public readonly primaryEndpointAddress: string;
  public readonly primaryEndpointPort: string;
  public readonly subnetGroup: CfnSubnetGroup;
  public readonly parameterGroup: CfnParameterGroup;
  public readonly userGroup: CfnUserGroup;
  public readonly applicationUser: CfnUser;
  public readonly defaultDisabledUser: CfnUser;
  public readonly authSecret: Secret;
  public readonly sessionPrefix: string;
  public readonly sessionIdleTtlSeconds: number;
  public readonly sessionAbsoluteLifetimeSeconds: number;
  public readonly sessionMaxPayloadKiB: number;
  public readonly sessionLockContract: SessionLockContract;
  public readonly replicationGroupId: string;
  public readonly primaryCacheClusterId: string;
  public readonly replicaCacheClusterId?: string;

  public constructor(scope: Construct, id: string, props: MxMedSessionStackProps) {
    super(scope, id, {
      ...props,
      component: 'session',
      description: 'MXMed encrypted Valkey 8.2 shared-session foundation.',
      metadata: {
        dataClassification: 'sensitive',
        criticality: 'high',
        backup: 'not-required',
      },
    });

    const { config } = props;
    this.validateSubnets(props.isolatedDataSubnets);
    this.sessionPrefix = buildSessionPrefix(config.environmentCode);
    this.sessionIdleTtlSeconds = config.sessionIdleTtlSeconds;
    this.sessionAbsoluteLifetimeSeconds = config.sessionAbsoluteLifetimeSeconds;
    this.sessionMaxPayloadKiB = config.sessionMaxPayloadKiB;
    this.sessionLockContract = SESSION_LOCK_CONTRACT;
    this.replicationGroupId = mxmedName(config.environmentCode, 'session');
    this.primaryCacheClusterId = `${this.replicationGroupId}-001`;
    if (config.sessionReplicaCount === 1) {
      this.replicaCacheClusterId = `${this.replicationGroupId}-002`;
    }

    this.subnetGroup = new CfnSubnetGroup(this, 'SessionSubnetGroup', {
      description: 'MXMed session cache subnet group with exactly two isolated-data subnets.',
      subnetIds: props.isolatedDataSubnets.map((subnet) => subnet.subnetId),
    });
    this.parameterGroup = new CfnParameterGroup(this, 'SessionParameterGroup', {
      cacheParameterGroupFamily: config.sessionParameterGroupFamily,
      description: 'MXMed Valkey 8 session TTL and connection parameters.',
      properties: SESSION_PARAMETERS,
    });

    this.authSecret = new Secret(this, 'SessionAuthSecret', {
      secretName: `/mxmed/${config.environmentName}/application/session-store-auth`,
      description: 'MXMed generated Valkey session application credential.',
      encryptionKey: props.secretsKey,
      generateSecretString: {
        secretStringTemplate: JSON.stringify({ username: 'mxmed_session_app' }),
        generateStringKey: 'password',
        passwordLength: 64,
        excludeCharacters: ',"/@',
        includeSpace: false,
        requireEachIncludedType: true,
      },
    });
    this.authSecret.applyRemovalPolicy(RemovalPolicy.RETAIN);
    Tags.of(this.authSecret).add('DataClassification', 'sensitive', { priority: 200 });

    const passwordReference = this.authSecret.secretValueFromJson('password').toString();
    const authenticationMode: CfnUser.AuthenticationModeProperty = {
      type: 'password',
      passwords: [passwordReference],
    };
    this.applicationUser = new CfnUser(this, 'SessionApplicationUser', {
      userId: mxmedName(config.environmentCode, 'session-app'),
      userName: 'mxmed_session_app',
      engine: config.sessionEngine,
      accessString: buildSessionApplicationAccessString(config.environmentCode),
      authenticationMode,
    });
    this.defaultDisabledUser = new CfnUser(this, 'SessionDefaultDisabledUser', {
      userId: mxmedName(config.environmentCode, 'default-disabled'),
      userName: 'default',
      engine: config.sessionEngine,
      accessString: DEFAULT_DISABLED_ACCESS_STRING,
      authenticationMode,
    });
    const secretResource = this.authSecret.node.defaultChild;
    if (!(secretResource instanceof CfnSecret)) {
      throw new Error('MXMED_SESSION_AUTH_SECRET_RESOURCE_INVALID');
    }
    secretResource.applyRemovalPolicy(RemovalPolicy.RETAIN, {
      applyToUpdateReplacePolicy: true,
    });
    this.applicationUser.addDependency(secretResource);
    this.defaultDisabledUser.addDependency(secretResource);

    this.userGroup = new CfnUserGroup(this, 'SessionUserGroup', {
      userGroupId: mxmedName(config.environmentCode, 'session-users'),
      engine: config.sessionEngine,
      userIds: [this.defaultDisabledUser.ref, this.applicationUser.ref],
    });
    this.userGroup.addDependency(this.defaultDisabledUser);
    this.userGroup.addDependency(this.applicationUser);

    this.replicationGroup = new CfnReplicationGroup(this, 'SessionReplicationGroup', {
      replicationGroupId: this.replicationGroupId,
      replicationGroupDescription: 'MXMed dedicated encrypted shared-session cache.',
      engine: config.sessionEngine,
      engineVersion: config.sessionEngineVersion,
      cacheNodeType: config.sessionNodeType,
      cacheParameterGroupName: this.parameterGroup.ref,
      cacheSubnetGroupName: this.subnetGroup.ref,
      securityGroupIds: [props.sessionSecurityGroup.securityGroupId],
      port: SESSION_PORT,
      clusterMode: 'disabled',
      numCacheClusters: config.sessionReplicaCount + 1,
      atRestEncryptionEnabled: config.sessionAtRestEncryptionEnabled,
      kmsKeyId: props.applicationDataKey.keyArn,
      transitEncryptionEnabled: config.sessionTransitEncryptionEnabled,
      userGroupIds: [this.userGroup.ref],
      automaticFailoverEnabled: config.sessionAutomaticFailoverEnabled,
      multiAzEnabled: config.sessionMultiAzEnabled,
      snapshotRetentionLimit: config.sessionSnapshotRetentionDays,
      autoMinorVersionUpgrade: config.sessionAutoMinorVersionUpgrade,
      preferredMaintenanceWindow: config.sessionPreferredMaintenanceWindow,
      networkType: 'ipv4',
    });
    this.replicationGroup.addDependency(this.parameterGroup);
    this.replicationGroup.addDependency(this.subnetGroup);
    this.replicationGroup.addDependency(this.userGroup);
    this.replicationGroup.applyRemovalPolicy(RemovalPolicy.DESTROY, {
      applyToUpdateReplacePolicy: true,
    });
    for (const resource of [
      this.subnetGroup,
      this.parameterGroup,
      this.applicationUser,
      this.defaultDisabledUser,
      this.userGroup,
    ]) {
      resource.applyRemovalPolicy(RemovalPolicy.DESTROY, {
        applyToUpdateReplacePolicy: true,
      });
    }

    this.primaryEndpointAddress = this.replicationGroup.attrPrimaryEndPointAddress;
    this.primaryEndpointPort = this.replicationGroup.attrPrimaryEndPointPort;

    // VPC ownership is a typed integration boundary; subnet IDs carry the resource references.
    void props.vpc;

    Aspects.of(this).add(new SessionFoundationAspect(config), {
      priority: AspectPriority.READONLY,
    });
    registerMxMedSessionValidation(this, config);
  }

  private validateSubnets(subnets: readonly ISubnet[]): void {
    const subnetIds = subnets.map((subnet) => subnet.subnetId);
    const paths = subnets.map((subnet) => subnet.node.path.toLowerCase());
    if (
      subnets.length !== 2 ||
      new Set(subnetIds).size !== 2 ||
      paths.some((path) => !path.includes('isolated-data'))
    ) {
      throw new Error('MXMED_SESSION_SUBNET_CONTRACT_INVALID');
    }
  }
}
