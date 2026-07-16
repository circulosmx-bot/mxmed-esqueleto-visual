import { PRODUCTION_CONFIG, STAGING_CONFIG } from '../lib/config/environments';
import { firstResource, properties, renderSession, resourcesOfType } from './session-test-helpers';

const staging = renderSession(STAGING_CONFIG);
const production = renderSession(PRODUCTION_CONFIG);
const stagingSubnet = firstResource(staging, 'AWS::ElastiCache::SubnetGroup');
const parameter = firstResource(staging, 'AWS::ElastiCache::ParameterGroup');
const parameterProperties = properties(parameter);
const parameters = parameterProperties.Properties as Readonly<Record<string, unknown>>;

describe('session subnet and parameter groups', () => {
  test('SESSION-IMP-026 creates one subnet group', () => {
    expect(resourcesOfType(staging.resources, 'AWS::ElastiCache::SubnetGroup')).toHaveLength(1);
  });
  test('SESSION-IMP-027 uses exactly two isolated-data subnets', () => {
    const subnetIds = properties(stagingSubnet).SubnetIds as unknown[];
    expect(subnetIds).toHaveLength(2);
    expect(JSON.stringify(subnetIds)).toContain('isolateddata');
  });
  test('SESSION-IMP-028 excludes public and application subnets', () => {
    const text = JSON.stringify(properties(stagingSubnet).SubnetIds).toLowerCase();
    expect(text).not.toContain('publicingress');
    expect(text).not.toContain('privateapp');
    expect(text).not.toContain('privateendpoint');
  });
  test('SESSION-IMP-029 uses family valkey8', () => {
    expect(parameterProperties.CacheParameterGroupFamily).toBe('valkey8');
  });
  test('SESSION-IMP-030 uses volatile-ttl', () => {
    expect(parameters['maxmemory-policy']).toBe('volatile-ttl');
  });
  test('SESSION-IMP-031 uses timeout 300', () => {
    expect(parameters.timeout).toBe('300');
  });
  test('SESSION-IMP-032 disables keyspace notifications', () => {
    expect(parameters['notify-keyspace-events']).toBe('');
  });
  test('SESSION-IMP-033 enables active rehashing', () => {
    expect(parameters.activerehashing).toBe('yes');
  });
  test('SESSION-IMP-034 uses keepalive 60', () => {
    expect(parameters['tcp-keepalive']).toBe('60');
  });
  test('SESSION-IMP-035 omits appendonly and save', () => {
    expect(parameters).not.toHaveProperty('appendonly');
    expect(parameters).not.toHaveProperty('save');
  });
  test('SESSION-IMP-036 omits cluster-enabled from parameter groups', () => {
    expect(parameters).not.toHaveProperty('cluster-enabled');
  });
  test('SESSION-IMP-037 emits no log or module parameters in either environment', () => {
    const productionParameters = properties(
      firstResource(production, 'AWS::ElastiCache::ParameterGroup'),
    ).Properties;
    for (const value of [parameters, productionParameters]) {
      expect(JSON.stringify(value).toLowerCase()).not.toMatch(
        /slowlog|commandlog|module|search|vector/,
      );
    }
  });
});
