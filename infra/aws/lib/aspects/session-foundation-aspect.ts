import { Annotations, CfnDeletionPolicy, CfnOutput, Stack } from 'aws-cdk-lib';
import {
  CfnParameterGroup,
  CfnReplicationGroup,
  CfnServerlessCache,
  CfnSubnetGroup,
  CfnUser,
  CfnUserGroup,
} from 'aws-cdk-lib/aws-elasticache';
import { CfnSecret } from 'aws-cdk-lib/aws-secretsmanager';
import type { IAspect } from 'aws-cdk-lib';
import type { IConstruct } from 'constructs';

import type { MxMedEnvironmentConfig } from '../config/environment-config';
import { buildSessionApplicationAccessString } from '../constructs/session-contract';

function text(value: unknown): string {
  return JSON.stringify(value).toLowerCase();
}

function isDynamicPassword(value: unknown): boolean {
  const rendered = text(value);
  return rendered.includes('resolve:secretsmanager') && rendered.includes('secretstring:password');
}

/** Session-specific fail-closed checks. It reports drift and never mutates resources. */
export class SessionFoundationAspect implements IAspect {
  public constructor(private readonly config: MxMedEnvironmentConfig) {}

  public visit(node: IConstruct): void {
    if (node instanceof CfnServerlessCache) {
      Annotations.of(node).addError('MXMED_SESSION_SERVERLESS_FORBIDDEN');
      return;
    }
    if (node instanceof CfnReplicationGroup) this.validateReplicationGroup(node);
    else if (node instanceof CfnSubnetGroup) this.validateSubnetGroup(node);
    else if (node instanceof CfnParameterGroup) this.validateParameterGroup(node);
    else if (node instanceof CfnUser) this.validateUser(node);
    else if (node instanceof CfnUserGroup) this.validateUserGroup(node);
    else if (node instanceof CfnSecret && node.node.path.includes('SessionAuthSecret')) {
      this.validateSecret(node);
    } else if (node instanceof CfnOutput) {
      Annotations.of(node).addError('MXMED_SESSION_OUTPUT_FORBIDDEN');
    }
  }

  private validateReplicationGroup(node: CfnReplicationGroup): void {
    const expectedNodes = this.config.sessionReplicaCount + 1;
    if (node.engine !== 'valkey') Annotations.of(node).addError('MXMED_SESSION_ENGINE_INVALID');
    if (node.engineVersion !== '8.2') {
      Annotations.of(node).addError('MXMED_SESSION_ENGINE_VERSION_INVALID');
    }
    if (node.clusterMode !== 'disabled' || node.numNodeGroups !== undefined) {
      Annotations.of(node).addError('MXMED_SESSION_CLUSTER_MODE_INVALID');
    }
    if (node.numCacheClusters !== expectedNodes || node.replicasPerNodeGroup !== undefined) {
      Annotations.of(node).addError('MXMED_SESSION_NODE_COUNT_INVALID');
    }
    if (node.cacheNodeType !== this.config.sessionNodeType) {
      Annotations.of(node).addError('MXMED_SESSION_NODE_TYPE_INVALID');
    }
    if (
      node.multiAzEnabled !== this.config.sessionMultiAzEnabled ||
      node.automaticFailoverEnabled !== this.config.sessionAutomaticFailoverEnabled
    ) {
      Annotations.of(node).addError('MXMED_SESSION_AVAILABILITY_INVALID');
    }
    if (
      node.atRestEncryptionEnabled !== true ||
      node.kmsKeyId === undefined ||
      node.transitEncryptionEnabled !== true ||
      node.transitEncryptionMode !== undefined
    ) {
      Annotations.of(node).addError('MXMED_SESSION_ENCRYPTION_INVALID');
    }
    if (node.securityGroupIds?.length !== 1 || node.cacheSubnetGroupName === undefined) {
      Annotations.of(node).addError('MXMED_SESSION_NETWORK_INVALID');
    }
    if (
      node.userGroupIds?.length !== 1 ||
      node.authToken !== undefined ||
      node.snapshotRetentionLimit !== 0 ||
      node.snapshotWindow !== undefined ||
      node.logDeliveryConfigurations !== undefined ||
      node.globalReplicationGroupId !== undefined ||
      node.dataTieringEnabled !== undefined ||
      node.networkType !== 'ipv4'
    ) {
      Annotations.of(node).addError('MXMED_SESSION_REPLICATION_POLICY_INVALID');
    }
    if (
      node.cfnOptions.deletionPolicy !== CfnDeletionPolicy.DELETE ||
      node.cfnOptions.updateReplacePolicy !== CfnDeletionPolicy.DELETE
    ) {
      Annotations.of(node).addError('MXMED_SESSION_CACHE_REMOVAL_POLICY_INVALID');
    }
  }

  private validateSubnetGroup(node: CfnSubnetGroup): void {
    const resolved: unknown = Stack.of(node).resolve(node.subnetIds) as unknown;
    const serialized = text(resolved);
    if (
      !Array.isArray(resolved) ||
      resolved.length !== 2 ||
      new Set(resolved.map((entry) => JSON.stringify(entry))).size !== 2 ||
      /publicingress|privateapp|privateendpoint/.test(serialized)
    ) {
      Annotations.of(node).addError('MXMED_SESSION_SUBNET_GROUP_INVALID');
    }
  }

  private validateParameterGroup(node: CfnParameterGroup): void {
    const parameters = Stack.of(node).resolve(node.properties) as Readonly<Record<string, unknown>>;
    const expected = {
      'maxmemory-policy': 'volatile-ttl',
      timeout: '300',
      'notify-keyspace-events': '',
      activerehashing: 'yes',
      'tcp-keepalive': '60',
    };
    if (
      node.cacheParameterGroupFamily !== 'valkey8' ||
      JSON.stringify(parameters) !== JSON.stringify(expected)
    ) {
      Annotations.of(node).addError('MXMED_SESSION_PARAMETER_GROUP_INVALID');
    }
  }

  private validateUser(node: CfnUser): void {
    const auth: unknown = Stack.of(node).resolve(node.authenticationMode) as unknown;
    if (node.engine !== 'valkey' || !isDynamicPassword(auth)) {
      Annotations.of(node).addError('MXMED_SESSION_USER_AUTH_INVALID');
    }
    if (node.userName === 'default') {
      if (node.accessString !== 'off ~* -@all') {
        Annotations.of(node).addError('MXMED_SESSION_DEFAULT_USER_INVALID');
      }
      return;
    }
    if (
      node.userName !== 'mxmed_session_app' ||
      node.accessString !== buildSessionApplicationAccessString(this.config.environmentCode)
    ) {
      Annotations.of(node).addError('MXMED_SESSION_APPLICATION_USER_INVALID');
    }
  }

  private validateUserGroup(node: CfnUserGroup): void {
    if (node.engine !== 'valkey' || node.userIds.length !== 2) {
      Annotations.of(node).addError('MXMED_SESSION_USER_GROUP_INVALID');
    }
  }

  private validateSecret(node: CfnSecret): void {
    const generator: unknown = Stack.of(node).resolve(node.generateSecretString) as unknown;
    if (
      node.kmsKeyId === undefined ||
      node.secretString !== undefined ||
      !text(generator).includes('"passwordlength":64') ||
      node.cfnOptions.deletionPolicy !== CfnDeletionPolicy.RETAIN ||
      node.cfnOptions.updateReplacePolicy !== CfnDeletionPolicy.RETAIN
    ) {
      Annotations.of(node).addError('MXMED_SESSION_AUTH_SECRET_INVALID');
    }
  }
}
