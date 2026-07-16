import { App, CfnDeletionPolicy } from 'aws-cdk-lib';
import { CfnBucket } from 'aws-cdk-lib/aws-s3';
import { Annotations, Match, Template } from 'aws-cdk-lib/assertions';

import type { MxMedEnvironmentConfig } from '../lib/config/environment-config';
import { PRODUCTION_CONFIG, STAGING_CONFIG } from '../lib/config/environments';
import { MxMedEnvironmentStage } from '../lib/stages/mxmed-environment-stage';

function mutatedBucket(
  config: MxMedEnvironmentConfig,
  bucketId: string,
  mutate: (bucket: CfnBucket) => void,
): MxMedEnvironmentStage {
  const app = new App({ analyticsReporting: false });
  const stage = new MxMedEnvironmentStage(app, `Mutated${bucketId}`, { config });
  const bucket = stage.storageStack.node
    .findAll()
    .find(
      (node): node is CfnBucket =>
        node instanceof CfnBucket && node.node.scope?.node.id === bucketId,
    );
  if (bucket === undefined) throw new Error(`missing-${bucketId}`);
  mutate(bucket);
  return stage;
}

function expectStorageError(stage: MxMedEnvironmentStage, code: string): void {
  Annotations.fromStack(stage.storageStack).hasError('*', Match.stringLikeRegexp(code));
}

describe('StorageFoundationAspect negative mutations', () => {
  test('STORAGE-IMP-098 rejects a public bucket', () => {
    const stage = mutatedBucket(STAGING_CONFIG, 'PublicMediaBucket', (bucket) => {
      bucket.accessControl = 'PublicRead';
    });
    expectStorageError(stage, 'MXMED_STORAGE_PUBLIC_ACCESS_FORBIDDEN');
  });

  test('STORAGE-IMP-099 rejects disabled versioning', () => {
    const stage = mutatedBucket(STAGING_CONFIG, 'PrivateDocumentsBucket', (bucket) => {
      bucket.versioningConfiguration = { status: 'Suspended' };
    });
    expectStorageError(stage, 'MXMED_STORAGE_VERSIONING_REQUIRED');
  });

  test('STORAGE-IMP-100 rejects SSE-S3', () => {
    const stage = mutatedBucket(STAGING_CONFIG, 'ClinicalRecordsBucket', (bucket) => {
      bucket.bucketEncryption = {
        serverSideEncryptionConfiguration: [
          { serverSideEncryptionByDefault: { sseAlgorithm: 'AES256' } },
        ],
      };
    });
    expectStorageError(stage, 'MXMED_STORAGE_ENCRYPTION_INVALID');
  });

  test('STORAGE-IMP-101 rejects a disabled Bucket Key', () => {
    const stage = mutatedBucket(STAGING_CONFIG, 'PublicMediaBucket', (bucket) => {
      bucket.bucketEncryption = {
        serverSideEncryptionConfiguration: [
          {
            bucketKeyEnabled: false,
            serverSideEncryptionByDefault: {
              kmsMasterKeyId: 'ApplicationDataKey',
              sseAlgorithm: 'aws:kms',
            },
          },
        ],
      };
    });
    expectStorageError(stage, 'MXMED_STORAGE_ENCRYPTION_INVALID');
  });

  test('STORAGE-IMP-102 rejects website hosting', () => {
    const stage = mutatedBucket(STAGING_CONFIG, 'PublicMediaBucket', (bucket) => {
      bucket.websiteConfiguration = { indexDocument: 'index.html' };
    });
    expectStorageError(stage, 'MXMED_STORAGE_WEBSITE_FORBIDDEN');
  });

  test('STORAGE-IMP-103 rejects Object Lock', () => {
    const stage = mutatedBucket(STAGING_CONFIG, 'ClinicalRecordsBucket', (bucket) => {
      bucket.objectLockEnabled = true;
    });
    expectStorageError(stage, 'MXMED_STORAGE_OBJECT_LOCK_FORBIDDEN');
  });

  test('STORAGE-IMP-104 rejects replication', () => {
    const stage = mutatedBucket(STAGING_CONFIG, 'PrivateDocumentsBucket', (bucket) => {
      bucket.replicationConfiguration = {
        role: 'synthetic-role',
        rules: [{ destination: { bucket: 'synthetic-destination' }, status: 'Enabled' }],
      };
    });
    expectStorageError(stage, 'MXMED_STORAGE_REPLICATION_FORBIDDEN');
  });

  test('STORAGE-IMP-105 rejects production clinical expiration', () => {
    const stage = mutatedBucket(PRODUCTION_CONFIG, 'ClinicalRecordsBucket', (bucket) => {
      bucket.lifecycleConfiguration = {
        rules: [
          {
            id: 'InvalidClinicalExpiry',
            status: 'Enabled',
            expirationInDays: 1,
            abortIncompleteMultipartUpload: { daysAfterInitiation: 1 },
          },
        ],
      };
    });
    expectStorageError(stage, 'MXMED_STORAGE_CLINICAL_RETENTION_INVALID');
  });

  test('STORAGE-IMP-106 rejects quarantine without EventBridge', () => {
    const stage = mutatedBucket(STAGING_CONFIG, 'UploadQuarantineBucket', (bucket) => {
      bucket.notificationConfiguration = {
        eventBridgeConfiguration: { eventBridgeEnabled: false },
      };
    });
    expectStorageError(stage, 'MXMED_STORAGE_QUARANTINE_EVENTBRIDGE_REQUIRED');
  });

  test('STORAGE-IMP-107 rejects a delete removal policy', () => {
    const stage = mutatedBucket(STAGING_CONFIG, 'PublicMediaBucket', (bucket) => {
      bucket.cfnOptions.deletionPolicy = CfnDeletionPolicy.DELETE;
    });
    expectStorageError(stage, 'MXMED_STORAGE_RETENTION_POLICY_REQUIRED');
  });

  test('STORAGE-IMP-116 rejects unsafe ownership controls', () => {
    const stage = mutatedBucket(STAGING_CONFIG, 'PrivateDocumentsBucket', (bucket) => {
      bucket.ownershipControls = { rules: [{ objectOwnership: 'ObjectWriter' }] };
    });
    expectStorageError(stage, 'MXMED_STORAGE_OWNERSHIP_INVALID');
  });

  test('STORAGE-IMP-117 rejects CORS even without a wildcard', () => {
    const stage = mutatedBucket(STAGING_CONFIG, 'UploadQuarantineBucket', (bucket) => {
      bucket.corsConfiguration = {
        corsRules: [{ allowedMethods: ['PUT'], allowedOrigins: ['https://example.invalid'] }],
      };
    });
    expectStorageError(stage, 'MXMED_STORAGE_CORS_FORBIDDEN');
  });

  test('STORAGE-IMP-118 rejects server access logging', () => {
    const stage = mutatedBucket(STAGING_CONFIG, 'PublicMediaBucket', (bucket) => {
      bucket.loggingConfiguration = { destinationBucketName: 'synthetic-log-bucket' };
    });
    expectStorageError(stage, 'MXMED_STORAGE_SERVER_LOGGING_FORBIDDEN');
  });

  test('STORAGE-IMP-119 rejects a physical bucket name', () => {
    const stage = mutatedBucket(STAGING_CONFIG, 'PublicMediaBucket', (bucket) => {
      bucket.bucketName = 'synthetic-fixed-name';
    });
    expectStorageError(stage, 'MXMED_STORAGE_PHYSICAL_NAME_FORBIDDEN');
  });

  test('STORAGE-IMP-120 rejects lifecycle without multipart abort', () => {
    const stage = mutatedBucket(STAGING_CONFIG, 'PrivateDocumentsBucket', (bucket) => {
      bucket.lifecycleConfiguration = { rules: [{ id: 'Invalid', status: 'Enabled' }] };
    });
    expectStorageError(stage, 'MXMED_STORAGE_MULTIPART_ABORT_REQUIRED');
  });

  test('STORAGE-IMP-121 rejects an unknown fifth bucket', () => {
    const app = new App({ analyticsReporting: false });
    const stage = new MxMedEnvironmentStage(app, 'UnknownBucketMutation', {
      config: STAGING_CONFIG,
    });
    new CfnBucket(stage.storageStack, 'UnknownBucket');
    expect(() => Template.fromStack(stage.storageStack)).toThrow(
      /MXMED_STORAGE_BUCKET_COUNT_INVALID[\s\S]*MXMED_STORAGE_BUCKET_INVENTORY_INVALID/,
    );
  });
});
