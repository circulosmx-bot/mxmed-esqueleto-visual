import {
  MXMED_PRODUCTION_STANDARD_ADDITIONAL_ALARM_CATALOG,
  MXMED_RDS_INSTANCE_MEMORY_GIB,
  MXMED_VALKEY_CONNECTION_WARNING,
} from '../lib/constructs/operations-alarm-catalog';
import type { RenderedTemplate } from './operations-test-helpers';
import {
  observabilityConfig,
  renderEnvironment,
  resourcesOfType,
  serialized,
} from './operations-test-helpers';

function alarms(template: RenderedTemplate): readonly Record<string, unknown>[] {
  return resourcesOfType(template, 'AWS::CloudWatch::Alarm');
}

function alarmByCode(template: RenderedTemplate, code: string): Record<string, unknown> {
  const found = alarms(template).find((alarm) => serialized(alarm).includes(`code=${code}`));
  if (found === undefined) throw new Error(`standard-alarm-fixture-missing:${code}`);
  return found;
}

let launch: ReturnType<typeof renderEnvironment>;
let standard: ReturnType<typeof renderEnvironment>;

beforeAll(() => {
  launch = renderEnvironment(observabilityConfig());
  standard = renderEnvironment(observabilityConfig('production-standard-v1'));
});

describe('Operations profile-aware standard extensions', () => {
  test('defines exactly thirteen standard additions', () => {
    expect(MXMED_PRODUCTION_STANDARD_ADDITIONAL_ALARM_CATALOG).toHaveLength(13);
  });

  test.each([
    'rds_low_free_memory',
    'rds_read_latency',
    'rds_write_latency',
    'rds_disk_queue',
    'rds_storage_warning',
    'rds_storage_critical',
    'valkey_high_cpu',
    'valkey_connections',
    'valkey_replication_lag',
  ])('creates regional standard alarm %s', (code) => {
    expect(alarmByCode(standard.operations, code)).toBeDefined();
  });

  test('uses the canonical memory map for both approved RDS classes', () => {
    expect(MXMED_RDS_INSTANCE_MEMORY_GIB).toEqual({
      'db.t4g.medium': 4,
      'db.m6g.large': 8,
    });
  });

  test('sets production RDS free memory to twenty percent of eight GiB', () => {
    expect(alarmByCode(standard.operations, 'rds_low_free_memory')).toMatchObject({
      Threshold: 8 * 1024 ** 3 * 0.2,
    });
  });

  test('sets both RDS latency warnings at 20 milliseconds', () => {
    expect(alarmByCode(standard.operations, 'rds_read_latency')).toMatchObject({
      Threshold: 0.02,
    });
    expect(alarmByCode(standard.operations, 'rds_write_latency')).toMatchObject({
      Threshold: 0.02,
    });
  });

  test('sets RDS disk queue depth at ten', () => {
    expect(alarmByCode(standard.operations, 'rds_disk_queue')).toMatchObject({ Threshold: 10 });
  });

  test('derives warning and critical storage thresholds at 30 and 15 percent', () => {
    expect(alarmByCode(standard.operations, 'rds_storage_warning')).toMatchObject({
      Threshold: 100 * 1024 ** 3 * 0.3,
    });
    expect(alarmByCode(standard.operations, 'rds_storage_critical')).toMatchObject({
      Threshold: 100 * 1024 ** 3 * 0.15,
    });
  });

  test('uses the canonical Valkey connection warning map', () => {
    expect(MXMED_VALKEY_CONNECTION_WARNING).toEqual({
      'cache.t4g.micro': 500,
      'cache.t4g.medium': 2000,
    });
  });

  test('uses the production Valkey node warning in the template', () => {
    expect(alarmByCode(standard.operations, 'valkey_connections')).toMatchObject({
      Threshold:
        MXMED_VALKEY_CONNECTION_WARNING[
          observabilityConfig('production-standard-v1').sessionNodeType
        ],
    });
  });

  test('uses the deterministic primary CacheClusterId', () => {
    expect(standard.stage.sessionStack.primaryCacheClusterId).toBe('mxmed-prd-session-001');
  });

  test('uses the deterministic replica CacheClusterId in standard mode', () => {
    expect(standard.stage.sessionStack.replicaCacheClusterId).toBe('mxmed-prd-session-002');
    expect(serialized(alarmByCode(standard.operations, 'valkey_replication_lag'))).toContain(
      'mxmed-prd-session-002',
    );
  });

  test('keeps launch lean without a replica CacheClusterId', () => {
    expect(launch.stage.sessionStack.replicaCacheClusterId).toBeUndefined();
    expect(serialized(alarms(launch.operations))).not.toContain('ReplicationLag');
  });

  test('sets an explicit deterministic replication group identifier', () => {
    expect(
      resourcesOfType(standard.session, 'AWS::ElastiCache::ReplicationGroup')[0],
    ).toMatchObject({ ReplicationGroupId: 'mxmed-prd-session' });
  });

  test('does not use a lookup for Valkey node identifiers', () => {
    expect(serialized(standard.session)).not.toMatch(/Custom::|Lookup|DescribeCache/i);
  });

  test('omits ALB p95 while runtime integration remains blocked', () => {
    expect(serialized(alarms(standard.operations))).not.toContain('alb_target_response_p95');
  });

  test('keeps standard-only alarms out of launch templates', () => {
    expect(serialized(alarms(launch.operations))).not.toContain('rds_low_free_memory');
  });
});
