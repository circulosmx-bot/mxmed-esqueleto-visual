import { validateEnvironmentConfig } from '../lib/config/environment-schema';
import {
  getEnvironmentConfig,
  PRODUCTION_CONFIG,
  STAGING_CONFIG,
} from '../lib/config/environments';

describe('environment configuration', () => {
  test('accepts the explicit staging configuration', () => {
    expect(() => {
      validateEnvironmentConfig(STAGING_CONFIG);
    }).not.toThrow();
    expect(getEnvironmentConfig('staging')).toBe(STAGING_CONFIG);
  });

  test('accepts the explicit production configuration', () => {
    expect(() => {
      validateEnvironmentConfig(PRODUCTION_CONFIG);
    }).not.toThrow();
    expect(getEnvironmentConfig('production')).toBe(PRODUCTION_CONFIG);
  });

  test('rejects an unknown environment', () => {
    expect(() => getEnvironmentConfig('preview')).toThrow(
      'MXMED_ENVIRONMENT_INVALID:environment:context must be staging or production',
    );
  });

  test('rejects a non-contractual primary region', () => {
    expect(() => {
      validateEnvironmentConfig({ ...STAGING_CONFIG, primaryRegion: 'us-west-2' });
    }).toThrow('MXMED_CONFIG_INVALID:primaryRegion');
  });

  test('rejects production without every guardrail', () => {
    for (const field of [
      'enableDeletionProtection',
      'enableTerminationProtection',
      'enableWaf',
      'enableCloudFrontLogging',
    ] as const) {
      expect(() => {
        validateEnvironmentConfig({ ...PRODUCTION_CONFIG, [field]: false });
      }).toThrow(`MXMED_CONFIG_INVALID:${field}`);
    }
  });

  test('rejects missing mandatory tags', () => {
    const { Owner: _owner, ...missingOwner } = PRODUCTION_CONFIG.tags;
    expect(() => {
      validateEnvironmentConfig({ ...PRODUCTION_CONFIG, tags: missingOwner });
    }).toThrow('MXMED_CONFIG_INVALID:tags');
  });

  test('rejects an unsafe Stripe return policy', () => {
    expect(() => {
      validateEnvironmentConfig({
        ...PRODUCTION_CONFIG,
        stripeReturnLoggingPolicy: 'full-request-line',
      });
    }).toThrow('MXMED_CONFIG_INVALID:stripeReturnLoggingPolicy');
  });

  test('rejects sensitive field names without echoing the field', () => {
    expect(() => {
      validateEnvironmentConfig({ ...STAGING_CONFIG, databasePassword: 'redacted-value' });
    }).toThrow('MXMED_CONFIG_SENSITIVE_FIELD:configuration');
  });

  test('rejects credential-like values without echoing the value', () => {
    const credentialLikeValue = ['AKIA', '12345678', '90ABCDEF'].join('');
    expect(() => {
      validateEnvironmentConfig({ ...STAGING_CONFIG, domainAlias: credentialLikeValue });
    }).toThrow('MXMED_CONFIG_CREDENTIAL_LIKE_VALUE:configuration');
  });
});
