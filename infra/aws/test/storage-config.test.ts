import { validateEnvironmentConfig } from '../lib/config/environment-schema';
import { PRODUCTION_CONFIG, STAGING_CONFIG } from '../lib/config/environments';

describe('storage environment configuration', () => {
  test('STORAGE-IMP-001 fixes the staging profile', () => {
    expect(STAGING_CONFIG.storageProfile).toBe('storage-foundation-v1');
    expect(() => {
      validateEnvironmentConfig({ ...STAGING_CONFIG, storageProfile: 'unknown' });
    }).toThrow('MXMED_CONFIG_INVALID:storageProfile');
  });

  test('STORAGE-IMP-002 fixes the production profile', () => {
    expect(PRODUCTION_CONFIG.storageProfile).toBe('storage-foundation-v1');
  });

  test('STORAGE-IMP-003 requires versioning', () => {
    expect(STAGING_CONFIG.storageVersioningEnabled).toBe(true);
    expect(() => {
      validateEnvironmentConfig({ ...STAGING_CONFIG, storageVersioningEnabled: false });
    }).toThrow('MXMED_CONFIG_INVALID:storageVersioningEnabled');
  });

  test('STORAGE-IMP-004 fixes ApplicationDataKey encryption', () => {
    expect(PRODUCTION_CONFIG.storageEncryptionProfile).toBe('application-data-kms');
    expect(() => {
      validateEnvironmentConfig({ ...PRODUCTION_CONFIG, storageEncryptionProfile: 's3-managed' });
    }).toThrow('MXMED_CONFIG_INVALID:storageEncryptionProfile');
  });

  test('STORAGE-IMP-005 requires bucket keys', () => {
    expect(STAGING_CONFIG.storageBucketKeyEnabled).toBe(true);
    expect(() => {
      validateEnvironmentConfig({ ...STAGING_CONFIG, storageBucketKeyEnabled: false });
    }).toThrow('MXMED_CONFIG_INVALID:storageBucketKeyEnabled');
  });

  test('STORAGE-IMP-006 fixes public staging noncurrent retention', () => {
    expect(STAGING_CONFIG.publicMediaNoncurrentRetentionDays).toBe(30);
  });

  test('STORAGE-IMP-007 fixes public production noncurrent retention', () => {
    expect(PRODUCTION_CONFIG.publicMediaNoncurrentRetentionDays).toBe(90);
    expect(() => {
      validateEnvironmentConfig({
        ...PRODUCTION_CONFIG,
        publicMediaNoncurrentRetentionDays: 30,
      });
    }).toThrow('MXMED_CONFIG_INVALID:publicMediaNoncurrentRetentionDays');
  });

  test('STORAGE-IMP-008 fixes private staging noncurrent retention', () => {
    expect(STAGING_CONFIG.privateDocumentsNoncurrentRetentionDays).toBe(30);
  });

  test('STORAGE-IMP-009 represents private production no-expiration with null', () => {
    expect(PRODUCTION_CONFIG.privateDocumentsNoncurrentRetentionDays).toBeNull();
    expect(() => {
      validateEnvironmentConfig({
        ...PRODUCTION_CONFIG,
        privateDocumentsNoncurrentRetentionDays: 0,
      });
    }).toThrow('MXMED_CONFIG_INVALID:privateDocumentsNoncurrentRetentionDays');
  });

  test('STORAGE-IMP-010 represents clinical production no-expiration with null', () => {
    expect(PRODUCTION_CONFIG.clinicalNoncurrentRetentionDays).toBeNull();
    expect(() => {
      validateEnvironmentConfig({ ...PRODUCTION_CONFIG, clinicalNoncurrentRetentionDays: 30 });
    }).toThrow('MXMED_CONFIG_INVALID:clinicalNoncurrentRetentionDays');
  });

  test('STORAGE-IMP-011 fixes every quarantine retention', () => {
    expect(STAGING_CONFIG).toMatchObject({
      quarantinePendingRetentionDays: 7,
      quarantineFailedRetentionDays: 14,
      quarantineInfectedRetentionDays: 30,
      quarantineCleanRetentionDays: 1,
    });
    expect(() => {
      validateEnvironmentConfig({ ...STAGING_CONFIG, quarantineCleanRetentionDays: 2 });
    }).toThrow('MXMED_CONFIG_INVALID:quarantineCleanRetentionDays');
  });

  test('STORAGE-IMP-012 fixes temporary exports at seven days', () => {
    expect(PRODUCTION_CONFIG.temporaryExportRetentionDays).toBe(7);
  });

  test('STORAGE-IMP-013 fixes environment-specific transitions', () => {
    expect(STAGING_CONFIG.privateStorageTransitionDays).toBeNull();
    expect(STAGING_CONFIG.clinicalStorageTransitionDays).toBeNull();
    expect(PRODUCTION_CONFIG.privateStorageTransitionDays).toBe(30);
    expect(PRODUCTION_CONFIG.clinicalStorageTransitionDays).toBe(30);
    expect(() => {
      validateEnvironmentConfig({ ...STAGING_CONFIG, clinicalStorageTransitionDays: 30 });
    }).toThrow('MXMED_CONFIG_INVALID:clinicalStorageTransitionDays');
  });

  test('STORAGE-IMP-014 caps upload TTL at 600 seconds', () => {
    expect(STAGING_CONFIG.uploadUrlTtlSeconds).toBe(600);
    expect(() => {
      validateEnvironmentConfig({ ...STAGING_CONFIG, uploadUrlTtlSeconds: 601 });
    }).toThrow('MXMED_CONFIG_INVALID:uploadUrlTtlSeconds');
  });

  test('STORAGE-IMP-015 caps download TTL at 300 seconds', () => {
    expect(PRODUCTION_CONFIG.downloadUrlTtlSeconds).toBe(300);
    expect(() => {
      validateEnvironmentConfig({ ...PRODUCTION_CONFIG, downloadUrlTtlSeconds: 301 });
    }).toThrow('MXMED_CONFIG_INVALID:downloadUrlTtlSeconds');
  });

  test('STORAGE-IMP-016 fixes upload size ceilings', () => {
    expect(PRODUCTION_CONFIG).toMatchObject({
      publicMediaMaxUploadMiB: 20,
      privateMaxUploadMiB: 100,
      clinicalMaxUploadMiB: 100,
    });
    expect(() => {
      validateEnvironmentConfig({ ...PRODUCTION_CONFIG, clinicalMaxUploadMiB: 101 });
    }).toThrow('MXMED_CONFIG_INVALID:clinicalMaxUploadMiB');
  });

  test('STORAGE-IMP-017 fixes the public derived ceiling', () => {
    expect(STAGING_CONFIG.publicMediaMaxDerivedMiB).toBe(10);
  });

  test('STORAGE-IMP-018 requires quarantine EventBridge', () => {
    expect(PRODUCTION_CONFIG.enableQuarantineEventBridge).toBe(true);
    expect(() => {
      validateEnvironmentConfig({ ...PRODUCTION_CONFIG, enableQuarantineEventBridge: false });
    }).toThrow('MXMED_CONFIG_INVALID:enableQuarantineEventBridge');
  });

  test('STORAGE-IMP-019 keeps Object Lock disabled', () => {
    expect(STAGING_CONFIG.enableObjectLock).toBe(false);
    expect(() => {
      validateEnvironmentConfig({ ...STAGING_CONFIG, enableObjectLock: true });
    }).toThrow('MXMED_CONFIG_INVALID:enableObjectLock');
  });

  test('STORAGE-IMP-020 keeps replication disabled', () => {
    expect(PRODUCTION_CONFIG.enableCrossRegionReplication).toBe(false);
    expect(() => {
      validateEnvironmentConfig({ ...PRODUCTION_CONFIG, enableCrossRegionReplication: true });
    }).toThrow('MXMED_CONFIG_INVALID:enableCrossRegionReplication');
  });

  test('STORAGE-IMP-021 keeps Storage data events disabled', () => {
    expect(PRODUCTION_CONFIG.enableStorageDataEvents).toBe(false);
    expect(() => {
      validateEnvironmentConfig({ ...PRODUCTION_CONFIG, enableStorageDataEvents: true });
    }).toThrow('MXMED_CONFIG_INVALID:enableStorageDataEvents');
  });

  test('STORAGE-IMP-022 fixes MIME allowlists and rejects wildcard MIME', () => {
    expect(STAGING_CONFIG.storageAllowedMimeTypes).toEqual({
      public: ['image/jpeg', 'image/png', 'image/webp'],
      private: ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'],
      clinical: ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'],
    });
    expect(() => {
      validateEnvironmentConfig({
        ...STAGING_CONFIG,
        storageAllowedMimeTypes: { ...STAGING_CONFIG.storageAllowedMimeTypes, public: ['image/*'] },
      });
    }).toThrow('MXMED_CONFIG_INVALID:storageAllowedMimeTypes');
    expect(() => {
      validateEnvironmentConfig({ ...STAGING_CONFIG, storageBucketName: 'fixed-name' });
    }).toThrow('MXMED_CONFIG_SENSITIVE_FIELD:configuration');
  });
});
