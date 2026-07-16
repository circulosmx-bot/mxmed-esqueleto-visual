import { validateEnvironmentConfig } from '../lib/config/environment-schema';
import { PRODUCTION_CONFIG, STAGING_CONFIG } from '../lib/config/environments';

type ConfigField = keyof typeof STAGING_CONFIG;

interface ConfigCase {
  readonly id: string;
  readonly description: string;
  readonly field: ConfigField;
  readonly staging: unknown;
  readonly production: unknown;
  readonly invalid: unknown;
}

const CASES: readonly ConfigCase[] = [
  {
    id: '001',
    description: 'staging profile',
    field: 'sessionProfile',
    staging: 'session-foundation-v1',
    production: 'session-foundation-v1',
    invalid: 'unknown',
  },
  {
    id: '002',
    description: 'production profile',
    field: 'sessionProfile',
    staging: 'session-foundation-v1',
    production: 'session-foundation-v1',
    invalid: 'session-v2',
  },
  {
    id: '003',
    description: 'Valkey engine',
    field: 'sessionEngine',
    staging: 'valkey',
    production: 'valkey',
    invalid: 'redis',
  },
  {
    id: '004',
    description: 'engine version',
    field: 'sessionEngineVersion',
    staging: '8.2',
    production: '8.2',
    invalid: '8.1',
  },
  {
    id: '005',
    description: 'parameter family',
    field: 'sessionParameterGroupFamily',
    staging: 'valkey8',
    production: 'valkey8',
    invalid: 'redis7',
  },
  {
    id: '006',
    description: 'staging node',
    field: 'sessionNodeType',
    staging: 'cache.t4g.micro',
    production: 'cache.t4g.medium',
    invalid: 'cache.t3.micro',
  },
  {
    id: '007',
    description: 'production node',
    field: 'sessionNodeType',
    staging: 'cache.t4g.micro',
    production: 'cache.t4g.medium',
    invalid: 'cache.m6g.large',
  },
  {
    id: '008',
    description: 'cluster mode',
    field: 'sessionClusterModeEnabled',
    staging: false,
    production: false,
    invalid: true,
  },
  {
    id: '009',
    description: 'one shard',
    field: 'sessionShardCount',
    staging: 1,
    production: 1,
    invalid: 2,
  },
  {
    id: '010',
    description: 'environment replicas',
    field: 'sessionReplicaCount',
    staging: 0,
    production: 1,
    invalid: 2,
  },
  {
    id: '011',
    description: 'Multi-AZ',
    field: 'sessionMultiAzEnabled',
    staging: false,
    production: true,
    invalid: 'yes',
  },
  {
    id: '012',
    description: 'automatic failover',
    field: 'sessionAutomaticFailoverEnabled',
    staging: false,
    production: true,
    invalid: 'yes',
  },
  {
    id: '013',
    description: 'at-rest encryption',
    field: 'sessionAtRestEncryptionEnabled',
    staging: true,
    production: true,
    invalid: false,
  },
  {
    id: '014',
    description: 'transit encryption',
    field: 'sessionTransitEncryptionEnabled',
    staging: true,
    production: true,
    invalid: false,
  },
  {
    id: '015',
    description: 'create-time TLS mode',
    field: 'sessionTransitEncryptionMode',
    staging: 'create-time-tls-only',
    production: 'create-time-tls-only',
    invalid: 'preferred',
  },
  {
    id: '016',
    description: 'idle TTL',
    field: 'sessionIdleTtlSeconds',
    staging: 1800,
    production: 1800,
    invalid: 0,
  },
  {
    id: '017',
    description: 'absolute lifetime',
    field: 'sessionAbsoluteLifetimeSeconds',
    staging: 43200,
    production: 43200,
    invalid: 86400,
  },
  {
    id: '018',
    description: 'payload maximum',
    field: 'sessionMaxPayloadKiB',
    staging: 32,
    production: 32,
    invalid: 33,
  },
  {
    id: '019',
    description: 'snapshot retention',
    field: 'sessionSnapshotRetentionDays',
    staging: 0,
    production: 0,
    invalid: 1,
  },
  {
    id: '020',
    description: 'automatic minor upgrade',
    field: 'sessionAutoMinorVersionUpgrade',
    staging: false,
    production: false,
    invalid: true,
  },
  {
    id: '021',
    description: 'maintenance windows',
    field: 'sessionPreferredMaintenanceWindow',
    staging: 'sun:03:30-sun:04:30',
    production: 'sun:04:30-sun:05:30',
    invalid: 'latest',
  },
  {
    id: '022',
    description: 'auth profile',
    field: 'sessionAuthProfile',
    staging: 'valkey-rbac-password-v1',
    production: 'valkey-rbac-password-v1',
    invalid: 'no-password',
  },
  {
    id: '023',
    description: 'environment prefix',
    field: 'sessionAclKeyPattern',
    staging: '~mxmed:stg:session:*',
    production: '~mxmed:prd:session:*',
    invalid: '~*',
  },
  {
    id: '024',
    description: 'bounded locking',
    field: 'sessionLockEnabled',
    staging: true,
    production: true,
    invalid: false,
  },
  {
    id: '025',
    description: 'logs disabled',
    field: 'sessionLogDeliveryEnabled',
    staging: false,
    production: false,
    invalid: true,
  },
];

describe('session environment configuration', () => {
  test.each(CASES)(
    'SESSION-IMP-$id fixes $description',
    ({ field, staging, production, invalid }) => {
      expect(STAGING_CONFIG[field]).toEqual(staging);
      expect(PRODUCTION_CONFIG[field]).toEqual(production);
      expect(() => {
        validateEnvironmentConfig({ ...STAGING_CONFIG, [field]: invalid });
      }).toThrow(`MXMED_CONFIG_INVALID:${field}`);
    },
  );
});
