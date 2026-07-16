import { App } from 'aws-cdk-lib';
import { Peer, Port } from 'aws-cdk-lib/aws-ec2';
import { CfnServerlessCache } from 'aws-cdk-lib/aws-elasticache';
import { Annotations, Match, Template } from 'aws-cdk-lib/assertions';

import type { MxMedEnvironmentConfig } from '../lib/config/environment-config';
import { getEnvironmentConfig, STAGING_CONFIG } from '../lib/config/environments';
import { MxMedEnvironmentStage } from '../lib/stages/mxmed-environment-stage';

const PRODUCTION_CONFIG = getEnvironmentConfig('production', 'production-standard-v1');

function stage(config: MxMedEnvironmentConfig, id: string): MxMedEnvironmentStage {
  return new MxMedEnvironmentStage(new App({ analyticsReporting: false }), id, { config });
}

function expectSessionError(environment: MxMedEnvironmentStage, code: string): void {
  try {
    Annotations.fromStack(environment.sessionStack).hasError('*', Match.stringLikeRegexp(code));
  } catch (error) {
    const message = String(error);
    if (!message.includes('Validation failed')) throw error;
    expect(message).toContain(code);
  }
}

describe('SessionFoundationAspect negative mutations', () => {
  test('SESSION-IMP-115 rejects Redis OSS', () => {
    const environment = stage(STAGING_CONFIG, 'InvalidRedisSession');
    environment.sessionStack.replicationGroup.engine = 'redis';
    expectSessionError(environment, 'MXMED_SESSION_ENGINE_INVALID');
  });
  test('SESSION-IMP-116 rejects serverless cache', () => {
    const environment = stage(STAGING_CONFIG, 'InvalidServerlessSession');
    new CfnServerlessCache(environment.sessionStack, 'InvalidServerlessCache', {
      engine: 'valkey',
      serverlessCacheName: 'synthetic-session-cache',
    });
    expectSessionError(environment, 'MXMED_SESSION_SERVERLESS_FORBIDDEN');
  });
  test('SESSION-IMP-117 rejects cluster mode enabled', () => {
    const environment = stage(STAGING_CONFIG, 'InvalidClusterModeSession');
    environment.sessionStack.replicationGroup.clusterMode = 'enabled';
    expectSessionError(environment, 'MXMED_SESSION_CLUSTER_MODE_INVALID');
  });
  test('SESSION-IMP-118 rejects production without a replica', () => {
    const environment = stage(PRODUCTION_CONFIG, 'InvalidReplicaSession');
    environment.sessionStack.replicationGroup.numCacheClusters = 1;
    expectSessionError(environment, 'MXMED_SESSION_NODE_COUNT_INVALID');
  });
  test('SESSION-IMP-119 rejects production without failover', () => {
    const environment = stage(PRODUCTION_CONFIG, 'InvalidFailoverSession');
    environment.sessionStack.replicationGroup.automaticFailoverEnabled = false;
    expectSessionError(environment, 'MXMED_SESSION_AVAILABILITY_INVALID');
  });
  test('SESSION-IMP-120 rejects encryption disabled', () => {
    const environment = stage(STAGING_CONFIG, 'InvalidEncryptionSession');
    environment.sessionStack.replicationGroup.transitEncryptionEnabled = false;
    expectSessionError(environment, 'MXMED_SESSION_ENCRYPTION_INVALID');
  });
  test('SESSION-IMP-121 rejects preferred transit mode', () => {
    const environment = stage(STAGING_CONFIG, 'InvalidPreferredTlsSession');
    environment.sessionStack.replicationGroup.transitEncryptionMode = 'preferred';
    expectSessionError(environment, 'MXMED_SESSION_ENCRYPTION_INVALID');
  });
  test('SESSION-IMP-122 rejects enabled snapshots', () => {
    const environment = stage(STAGING_CONFIG, 'InvalidSnapshotSession');
    environment.sessionStack.replicationGroup.snapshotRetentionLimit = 1;
    expectSessionError(environment, 'MXMED_SESSION_REPLICATION_POLICY_INVALID');
  });
  test('SESSION-IMP-123 rejects log delivery', () => {
    const environment = stage(STAGING_CONFIG, 'InvalidLogDeliverySession');
    environment.sessionStack.replicationGroup.logDeliveryConfigurations = [];
    expectSessionError(environment, 'MXMED_SESSION_REPLICATION_POLICY_INVALID');
  });
  test('SESSION-IMP-124 rejects a non-dynamic application password', () => {
    const environment = stage(STAGING_CONFIG, 'InvalidPlaintextSession');
    environment.sessionStack.applicationUser.authenticationMode = {
      type: 'password',
      passwords: [''],
    };
    expectSessionError(environment, 'MXMED_SESSION_USER_AUTH_INVALID');
  });
  test('SESSION-IMP-125 rejects an active default user', () => {
    const environment = stage(STAGING_CONFIG, 'InvalidDefaultSession');
    environment.sessionStack.defaultDisabledUser.accessString = 'on ~* +get';
    expectSessionError(environment, 'MXMED_SESSION_DEFAULT_USER_INVALID');
  });
  test('SESSION-IMP-126 rejects +@all for the application user', () => {
    const environment = stage(STAGING_CONFIG, 'InvalidAclSession');
    environment.sessionStack.applicationUser.accessString = 'on ~mxmed:stg:session:* +@all';
    expectSessionError(environment, 'MXMED_SESSION_APPLICATION_USER_INVALID');
  });
  test('SESSION-IMP-127 preserves the network guardrail against public session ingress', () => {
    const environment = stage(STAGING_CONFIG, 'InvalidPublicSessionIngress');
    environment.networkStack.sessionSecurityGroup.addIngressRule(
      Peer.anyIpv4(),
      Port.tcp(6379),
      'Synthetic invalid public cache ingress.',
    );
    expect(() => Template.fromStack(environment.networkStack)).toThrow(
      'MXMED_NETWORK_PUBLIC_SECURITY_GROUP_INGRESS_FORBIDDEN',
    );
  });
});
