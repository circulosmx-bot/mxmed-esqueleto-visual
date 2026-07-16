import {
  buildClinicalRecordObjectKey,
  buildPrivateDocumentObjectKey,
  buildPublicMediaObjectKey,
  buildQuarantineObjectKey,
  buildTemporaryExportObjectKey,
  validateDownloadTtl,
  validateStorageMimeType,
  validateStorageObjectMetadata,
  validateStorageObjectSize,
  validateStorageObjectTags,
  validateUploadTtl,
} from '../lib/constructs/storage-contract';

const UUID = '123e4567-e89b-42d3-a456-426614174000';
const MIB = 1024 * 1024;

describe('storage object key helpers', () => {
  test('STORAGE-IMP-069 builds the quarantine key', () => {
    expect(buildQuarantineObjectKey(UUID)).toBe(`uploads/${UUID}/source`);
  });

  test('STORAGE-IMP-070 builds the public media key', () => {
    expect(buildPublicMediaObjectKey(UUID, 'optimized', 'webp')).toBe(
      `assets/${UUID}/optimized.webp`,
    );
  });

  test('STORAGE-IMP-071 builds the private object key', () => {
    expect(buildPrivateDocumentObjectKey(UUID)).toBe(`objects/${UUID}`);
  });

  test('STORAGE-IMP-072 builds the clinical object key', () => {
    expect(buildClinicalRecordObjectKey(UUID)).toBe(`records/${UUID}`);
  });

  test('STORAGE-IMP-073 builds the temporary export key', () => {
    expect(buildTemporaryExportObjectKey(UUID)).toBe(`temporary-exports/${UUID}`);
  });

  test('STORAGE-IMP-074 rejects invalid UUID values', () => {
    expect(() => buildPrivateDocumentObjectKey('not-a-uuid')).toThrow(
      'MXMED_STORAGE_CONTRACT_INVALID:objectUuid',
    );
  });

  test('STORAGE-IMP-075 rejects path traversal', () => {
    expect(() => buildQuarantineObjectKey(`../${UUID}`)).toThrow(
      'MXMED_STORAGE_CONTRACT_INVALID:uploadUuid',
    );
  });

  test('STORAGE-IMP-076 rejects personal-name variants', () => {
    expect(() => buildPublicMediaObjectKey(UUID, 'juan-perez', 'jpg')).toThrow(
      'MXMED_STORAGE_CONTRACT_INVALID:variant',
    );
  });

  test('STORAGE-IMP-077 rejects filename-like extensions', () => {
    expect(() => buildPublicMediaObjectKey(UUID, 'optimized', 'photo.jpg')).toThrow(
      'MXMED_STORAGE_CONTRACT_INVALID:extension',
    );
  });

  test('STORAGE-IMP-078 rejects an extra slash', () => {
    expect(() => buildClinicalRecordObjectKey(`${UUID}/extra`)).toThrow(
      'MXMED_STORAGE_CONTRACT_INVALID:objectUuid',
    );
  });

  test('STORAGE-IMP-079 rejects control characters', () => {
    expect(() => buildTemporaryExportObjectKey(`${UUID}\n`)).toThrow(
      'MXMED_STORAGE_CONTRACT_INVALID:exportUuid',
    );
  });
});

describe('storage metadata and tag validators', () => {
  test('STORAGE-IMP-080 accepts allowlisted metadata', () => {
    expect(() => {
      validateStorageObjectMetadata({
        'upload-id': UUID,
        'checksum-sha256': 'a'.repeat(64),
        'normalized-content-type': 'application/pdf',
        'schema-version': 'v1',
        'source-type': 'web-upload',
      });
    }).not.toThrow();
  });

  test('STORAGE-IMP-081 rejects unknown metadata', () => {
    expect(() => {
      validateStorageObjectMetadata({ unknown: 'value' });
    }).toThrow('MXMED_STORAGE_CONTRACT_INVALID:metadata');
  });

  test('STORAGE-IMP-082 rejects patient_id metadata', () => {
    expect(() => {
      validateStorageObjectMetadata({ patient_id: UUID });
    }).toThrow('MXMED_STORAGE_CONTRACT_INVALID:metadata');
  });

  test('STORAGE-IMP-083 rejects doctor-id metadata', () => {
    expect(() => {
      validateStorageObjectMetadata({ 'doctor-id': UUID });
    }).toThrow('MXMED_STORAGE_CONTRACT_INVALID:metadata');
  });

  test('STORAGE-IMP-084 rejects original filename metadata', () => {
    expect(() => {
      validateStorageObjectMetadata({ 'original-filename': 'report.pdf' });
    }).toThrow('MXMED_STORAGE_CONTRACT_INVALID:metadata');
  });

  test('STORAGE-IMP-085 accepts allowlisted tags', () => {
    expect(() => {
      validateStorageObjectTags({
        classification: 'clinical',
        'scan-status': 'clean',
        'retention-class': 'clinical-record',
        'processing-status': 'ready',
      });
    }).not.toThrow();
  });

  test('STORAGE-IMP-086 rejects an invalid scan status', () => {
    expect(() => {
      validateStorageObjectTags({ 'scan-status': 'unknown' });
    }).toThrow('MXMED_STORAGE_CONTRACT_INVALID:scan-status');
  });

  test('STORAGE-IMP-087 rejects an invalid classification', () => {
    expect(() => {
      validateStorageObjectTags({ classification: 'internal' });
    }).toThrow('MXMED_STORAGE_CONTRACT_INVALID:classification');
  });
});

describe('storage MIME, size and TTL validators', () => {
  test('STORAGE-IMP-088 accepts public image MIME', () => {
    expect(() => {
      validateStorageMimeType('public', 'image/webp');
    }).not.toThrow();
  });

  test('STORAGE-IMP-089 accepts private PDF', () => {
    expect(() => {
      validateStorageMimeType('private', 'application/pdf');
    }).not.toThrow();
  });

  test('STORAGE-IMP-090 rejects SVG', () => {
    expect(() => {
      validateStorageMimeType('public', 'image/svg+xml');
    }).toThrow('MXMED_STORAGE_CONTRACT_INVALID:mimeType');
  });

  test('STORAGE-IMP-091 rejects ZIP', () => {
    expect(() => {
      validateStorageMimeType('clinical', 'application/zip');
    }).toThrow('MXMED_STORAGE_CONTRACT_INVALID:mimeType');
  });

  test('STORAGE-IMP-092 enforces the public upload size', () => {
    expect(() => {
      validateStorageObjectSize('public-upload', 20 * MIB);
    }).not.toThrow();
    expect(() => {
      validateStorageObjectSize('public-upload', 20 * MIB + 1);
    }).toThrow('MXMED_STORAGE_CONTRACT_INVALID:bytes');
  });

  test('STORAGE-IMP-093 enforces the public derived size', () => {
    expect(() => {
      validateStorageObjectSize('public-derived', 10 * MIB);
    }).not.toThrow();
    expect(() => {
      validateStorageObjectSize('public-derived', 10 * MIB + 1);
    }).toThrow('MXMED_STORAGE_CONTRACT_INVALID:bytes');
  });

  test('STORAGE-IMP-094 enforces the private size', () => {
    expect(() => {
      validateStorageObjectSize('private', 100 * MIB);
    }).not.toThrow();
    expect(() => {
      validateStorageObjectSize('private', 100 * MIB + 1);
    }).toThrow('MXMED_STORAGE_CONTRACT_INVALID:bytes');
  });

  test('STORAGE-IMP-095 enforces the clinical size', () => {
    expect(() => {
      validateStorageObjectSize('clinical', 100 * MIB);
    }).not.toThrow();
    expect(() => {
      validateStorageObjectSize('clinical', 0);
    }).toThrow('MXMED_STORAGE_CONTRACT_INVALID:bytes');
  });

  test('STORAGE-IMP-096 enforces upload TTL', () => {
    expect(() => {
      validateUploadTtl(600);
    }).not.toThrow();
    expect(() => {
      validateUploadTtl(601);
    }).toThrow('MXMED_STORAGE_CONTRACT_INVALID:uploadUrlTtlSeconds');
  });

  test('STORAGE-IMP-097 enforces download TTL', () => {
    expect(() => {
      validateDownloadTtl(300);
    }).not.toThrow();
    expect(() => {
      validateDownloadTtl(301);
    }).toThrow('MXMED_STORAGE_CONTRACT_INVALID:downloadUrlTtlSeconds');
  });
});
