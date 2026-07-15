import { validateEnvironmentConfig } from '../lib/config/environment-schema';
import { PRODUCTION_CONFIG, STAGING_CONFIG } from '../lib/config/environments';

describe('security configuration contract', () => {
  test('SEC-IMP-001 accepts staging security configuration', () => {
    expect(() => {
      validateEnvironmentConfig(STAGING_CONFIG);
    }).not.toThrow();
    expect(STAGING_CONFIG).toMatchObject({
      securityProfile: 'baseline-v1',
      kmsDeletionWindowDays: 7,
      secretRecoveryWindowDays: 7,
      cloudTrailLogRetentionDays: 90,
      auditArchiveRetentionDays: 365,
      enableManagementTrail: true,
      enableKeyRotation: true,
      enableDataEventTrail: false,
    });
  });

  test('SEC-IMP-002 accepts production security configuration', () => {
    expect(() => {
      validateEnvironmentConfig(PRODUCTION_CONFIG);
    }).not.toThrow();
    expect(PRODUCTION_CONFIG).toMatchObject({
      securityProfile: 'baseline-v1',
      kmsDeletionWindowDays: 30,
      secretRecoveryWindowDays: 30,
      cloudTrailLogRetentionDays: 365,
      auditArchiveRetentionDays: 2555,
      enableManagementTrail: true,
      enableKeyRotation: true,
      enableDataEventTrail: false,
    });
  });

  test('SEC-IMP-003 rejects an unknown security profile', () => {
    expect(() => {
      validateEnvironmentConfig({ ...STAGING_CONFIG, securityProfile: 'unknown-profile' });
    }).toThrow('MXMED_CONFIG_INVALID:securityProfile');
  });

  test('SEC-IMP-004 rejects disabled KMS rotation', () => {
    expect(() => {
      validateEnvironmentConfig({ ...STAGING_CONFIG, enableKeyRotation: false });
    }).toThrow('MXMED_CONFIG_INVALID:enableKeyRotation');
  });

  test('SEC-IMP-005 rejects the wrong staging KMS deletion window', () => {
    expect(() => {
      validateEnvironmentConfig({ ...STAGING_CONFIG, kmsDeletionWindowDays: 30 });
    }).toThrow('MXMED_CONFIG_INVALID:kmsDeletionWindowDays');
  });

  test('SEC-IMP-006 rejects the wrong production KMS deletion window', () => {
    expect(() => {
      validateEnvironmentConfig({ ...PRODUCTION_CONFIG, kmsDeletionWindowDays: 7 });
    }).toThrow('MXMED_CONFIG_INVALID:kmsDeletionWindowDays');
  });

  test('SEC-IMP-007 rejects secret recovery outside the operational policy', () => {
    expect(() => {
      validateEnvironmentConfig({ ...STAGING_CONFIG, secretRecoveryWindowDays: 6 });
    }).toThrow('MXMED_CONFIG_INVALID:secretRecoveryWindowDays');
    expect(() => {
      validateEnvironmentConfig({ ...PRODUCTION_CONFIG, secretRecoveryWindowDays: 31 });
    }).toThrow('MXMED_CONFIG_INVALID:secretRecoveryWindowDays');
  });

  test('SEC-IMP-008 rejects a disabled management trail', () => {
    expect(() => {
      validateEnvironmentConfig({ ...PRODUCTION_CONFIG, enableManagementTrail: false });
    }).toThrow('MXMED_CONFIG_INVALID:enableManagementTrail');
  });

  test('SEC-IMP-009 rejects staging CloudTrail retention drift', () => {
    expect(() => {
      validateEnvironmentConfig({ ...STAGING_CONFIG, cloudTrailLogRetentionDays: 365 });
    }).toThrow('MXMED_CONFIG_INVALID:cloudTrailLogRetentionDays');
  });

  test('SEC-IMP-010 rejects production CloudTrail retention below 365 days', () => {
    expect(() => {
      validateEnvironmentConfig({ ...PRODUCTION_CONFIG, cloudTrailLogRetentionDays: 90 });
    }).toThrow('MXMED_CONFIG_INVALID:cloudTrailLogRetentionDays');
  });

  test('SEC-IMP-011 rejects staging archive retention below 365 days', () => {
    expect(() => {
      validateEnvironmentConfig({ ...STAGING_CONFIG, auditArchiveRetentionDays: 364 });
    }).toThrow('MXMED_CONFIG_INVALID:auditArchiveRetentionDays');
  });

  test('SEC-IMP-012 rejects production archive retention below 2555 days', () => {
    expect(() => {
      validateEnvironmentConfig({ ...PRODUCTION_CONFIG, auditArchiveRetentionDays: 2554 });
    }).toThrow('MXMED_CONFIG_INVALID:auditArchiveRetentionDays');
  });

  test('SEC-IMP-013 defers data events while Storage has no clinical bucket', () => {
    expect(() => {
      validateEnvironmentConfig({ ...PRODUCTION_CONFIG, enableDataEventTrail: true });
    }).toThrow('MXMED_CONFIG_INVALID:enableDataEventTrail');
  });
});
