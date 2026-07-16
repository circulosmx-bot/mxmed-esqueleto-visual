import { PRODUCTION_CONFIG, STAGING_CONFIG } from '../lib/config/environments';
import { firstResource, properties, renderSession, resourcesOfType } from './session-test-helpers';

const staging = renderSession(STAGING_CONFIG);
const production = renderSession(PRODUCTION_CONFIG);
const stagingGroup = firstResource(staging, 'AWS::ElastiCache::ReplicationGroup');
const productionGroup = firstResource(production, 'AWS::ElastiCache::ReplicationGroup');
const stg = properties(stagingGroup);
const prd = properties(productionGroup);

describe('session replication groups', () => {
  test('SESSION-IMP-074 creates one group per environment', () => {
    expect(resourcesOfType(staging.resources, 'AWS::ElastiCache::ReplicationGroup')).toHaveLength(
      1,
    );
    expect(
      resourcesOfType(production.resources, 'AWS::ElastiCache::ReplicationGroup'),
    ).toHaveLength(1);
  });
  test('SESSION-IMP-075 selects Valkey', () => {
    expect(stg.Engine).toBe('valkey');
    expect(prd.Engine).toBe('valkey');
  });
  test('SESSION-IMP-076 fixes version 8.2', () => {
    expect(stg.EngineVersion).toBe('8.2');
    expect(prd.EngineVersion).toBe('8.2');
  });
  test('SESSION-IMP-077 uses the staging node class', () => {
    expect(stg.CacheNodeType).toBe('cache.t4g.micro');
  });
  test('SESSION-IMP-078 uses the production node class', () => {
    expect(prd.CacheNodeType).toBe('cache.t4g.medium');
  });
  test('SESSION-IMP-079 creates one staging node', () => {
    expect(stg.NumCacheClusters).toBe(1);
  });
  test('SESSION-IMP-080 creates two production nodes', () => {
    expect(prd.NumCacheClusters).toBe(2);
  });
  test('SESSION-IMP-081 enables production Multi-AZ', () => {
    expect(prd.MultiAZEnabled).toBe(true);
  });
  test('SESSION-IMP-082 enables production failover', () => {
    expect(prd.AutomaticFailoverEnabled).toBe(true);
  });
  test('SESSION-IMP-083 keeps staging without failover or HA claim', () => {
    expect(stg.MultiAZEnabled).toBe(false);
    expect(stg.AutomaticFailoverEnabled).toBe(false);
  });
  test('SESSION-IMP-084 fixes TCP port 6379', () => {
    expect(stg.Port).toBe(6379);
    expect(prd.Port).toBe(6379);
  });
  test('SESSION-IMP-085 uses IPv4', () => {
    expect(stg.NetworkType).toBe('ipv4');
  });
  test('SESSION-IMP-086 disables cluster mode without shard properties', () => {
    expect(prd.ClusterMode).toBe('disabled');
    expect(prd).not.toHaveProperty('NumNodeGroups');
    expect(prd).not.toHaveProperty('ReplicasPerNodeGroup');
  });
  test('SESSION-IMP-087 encrypts at rest with ApplicationDataKey', () => {
    expect(prd.AtRestEncryptionEnabled).toBe(true);
    expect(JSON.stringify(prd.KmsKeyId)).toContain('ApplicationDataKey');
  });
  test('SESSION-IMP-088 enables transit encryption from creation', () => {
    expect(stg.TransitEncryptionEnabled).toBe(true);
    expect(prd.TransitEncryptionEnabled).toBe(true);
  });
  test('SESSION-IMP-089 omits migration-only TransitEncryptionMode', () => {
    expect(stg).not.toHaveProperty('TransitEncryptionMode');
    expect(prd).not.toHaveProperty('TransitEncryptionMode');
  });
  test('SESSION-IMP-090 associates the user group', () => {
    expect(stg.UserGroupIds).toHaveLength(1);
  });
  test('SESSION-IMP-091 disables automatic snapshots', () => {
    expect(stg.SnapshotRetentionLimit).toBe(0);
    expect(prd.SnapshotRetentionLimit).toBe(0);
  });
  test('SESSION-IMP-092 omits snapshot windows and restore sources', () => {
    for (const group of [stg, prd]) {
      expect(group).not.toHaveProperty('SnapshotWindow');
      expect(group).not.toHaveProperty('SnapshotName');
      expect(group).not.toHaveProperty('SnapshotArns');
    }
  });
  test('SESSION-IMP-093 disables automatic minor upgrades', () => {
    expect(stg.AutoMinorVersionUpgrade).toBe(false);
    expect(prd.AutoMinorVersionUpgrade).toBe(false);
  });
  test('SESSION-IMP-094 fixes distinct UTC maintenance windows', () => {
    expect(stg.PreferredMaintenanceWindow).toBe('sun:03:30-sun:04:30');
    expect(prd.PreferredMaintenanceWindow).toBe('sun:04:30-sun:05:30');
  });
  test('SESSION-IMP-095 emits no engine or slow log delivery', () => {
    expect(stg).not.toHaveProperty('LogDeliveryConfigurations');
    expect(prd).not.toHaveProperty('LogDeliveryConfigurations');
  });
  test('SESSION-IMP-096 uses RBAC rather than AuthToken', () => {
    expect(stg).not.toHaveProperty('AuthToken');
  });
  test('SESSION-IMP-097 emits no global datastore or data tiering', () => {
    expect(prd).not.toHaveProperty('GlobalReplicationGroupId');
    expect(prd).not.toHaveProperty('DataTieringEnabled');
  });
});
