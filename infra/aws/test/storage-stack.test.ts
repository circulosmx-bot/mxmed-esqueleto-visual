import type { Stack } from 'aws-cdk-lib';

import { PRODUCTION_CONFIG, STAGING_CONFIG } from '../lib/config/environments';
import {
  bucketByLogicalPrefix,
  lifecycleRules,
  properties,
  renderStorage,
  resourcesOfType,
  ruleById,
  tagMap,
  type RenderedStorage,
  type RenderedStorageResource,
} from './storage-test-helpers';

let staging: RenderedStorage;
let production: RenderedStorage;

beforeAll(() => {
  staging = renderStorage(STAGING_CONFIG);
  production = renderStorage(PRODUCTION_CONFIG);
});

function buckets(rendered: RenderedStorage): RenderedStorageResource[] {
  return resourcesOfType(rendered.resources, 'AWS::S3::Bucket').map(([, resource]) => resource);
}

function bucket(rendered: RenderedStorage, prefix: string): RenderedStorageResource {
  return bucketByLogicalPrefix(rendered.resources, prefix);
}

function directDependencyNames(stack: Stack): string[] {
  return stack.dependencies.map((dependency) => dependency.stackName).sort();
}

describe('storage bucket inventory', () => {
  test('STORAGE-IMP-023 creates four staging buckets', () => {
    expect(buckets(staging)).toHaveLength(4);
  });

  test('STORAGE-IMP-024 creates four production buckets', () => {
    expect(buckets(production)).toHaveLength(4);
  });

  test('STORAGE-IMP-025 creates the public media bucket contract', () => {
    expect(tagMap(bucket(staging, 'PublicMediaBucket'))).toMatchObject({
      Component: 'storage-public-media',
      DataClassification: 'public',
      Criticality: 'medium',
      Backup: 'required',
    });
  });

  test('STORAGE-IMP-026 creates the private documents bucket contract', () => {
    expect(tagMap(bucket(staging, 'PrivateDocumentsBucket'))).toMatchObject({
      Component: 'storage-private-documents',
      DataClassification: 'sensitive',
      Criticality: 'high',
      Backup: 'required',
    });
  });

  test('STORAGE-IMP-027 creates the clinical records bucket contract', () => {
    expect(tagMap(bucket(production, 'ClinicalRecordsBucket'))).toMatchObject({
      Component: 'storage-clinical-records',
      DataClassification: 'clinical',
      Criticality: 'high',
      Backup: 'required',
    });
  });

  test('STORAGE-IMP-028 creates the quarantine bucket contract', () => {
    expect(tagMap(bucket(production, 'UploadQuarantineBucket'))).toMatchObject({
      Component: 'storage-quarantine',
      DataClassification: 'sensitive',
      Criticality: 'high',
      Backup: 'not-required',
    });
  });

  test('STORAGE-IMP-029 creates no additional bucket', () => {
    expect(
      Object.keys(staging.resources).filter((logicalId) => logicalId.includes('Bucket')),
    ).toHaveLength(8);
  });

  test('STORAGE-IMP-030 fixes no physical bucket name', () => {
    for (const resource of [...buckets(staging), ...buckets(production)]) {
      expect(properties(resource)).not.toHaveProperty('BucketName');
    }
  });
});

describe('storage bucket security', () => {
  test('STORAGE-IMP-031 blocks all public access controls', () => {
    for (const resource of buckets(production)) {
      expect(properties(resource).PublicAccessBlockConfiguration).toEqual({
        BlockPublicAcls: true,
        BlockPublicPolicy: true,
        IgnorePublicAcls: true,
        RestrictPublicBuckets: true,
      });
    }
  });

  test('STORAGE-IMP-032 enforces bucket-owner ownership', () => {
    for (const resource of buckets(staging)) {
      expect(properties(resource).OwnershipControls).toEqual({
        Rules: [{ ObjectOwnership: 'BucketOwnerEnforced' }],
      });
    }
  });

  test('STORAGE-IMP-033 emits no ACL configuration', () => {
    for (const resource of buckets(staging))
      expect(properties(resource)).not.toHaveProperty('AccessControl');
  });

  test('STORAGE-IMP-034 requires SSL with one deny policy per bucket', () => {
    const policies = resourcesOfType(production.resources, 'AWS::S3::BucketPolicy');
    expect(policies).toHaveLength(4);
    for (const [, policy] of policies) {
      expect(JSON.stringify(properties(policy))).toContain('aws:SecureTransport');
      expect(JSON.stringify(properties(policy))).toContain('false');
    }
  });

  test('STORAGE-IMP-035 uses SSE-KMS on all buckets', () => {
    for (const resource of buckets(production)) {
      expect(JSON.stringify(properties(resource).BucketEncryption)).toContain('aws:kms');
    }
  });

  test('STORAGE-IMP-036 references ApplicationDataKey on all buckets', () => {
    for (const resource of buckets(staging)) {
      expect(JSON.stringify(properties(resource).BucketEncryption)).toContain('ApplicationDataKey');
    }
  });

  test('STORAGE-IMP-037 enables every S3 Bucket Key', () => {
    for (const resource of buckets(staging)) {
      expect(JSON.stringify(properties(resource).BucketEncryption)).toContain(
        '"BucketKeyEnabled":true',
      );
    }
  });

  test('STORAGE-IMP-038 enables versioning on every bucket', () => {
    for (const resource of buckets(production)) {
      expect(properties(resource).VersioningConfiguration).toEqual({ Status: 'Enabled' });
    }
  });

  test('STORAGE-IMP-039 emits no website configuration', () => {
    for (const resource of buckets(production))
      expect(properties(resource)).not.toHaveProperty('WebsiteConfiguration');
  });

  test('STORAGE-IMP-040 emits no CORS configuration', () => {
    for (const resource of buckets(production))
      expect(properties(resource)).not.toHaveProperty('CorsConfiguration');
  });

  test('STORAGE-IMP-041 emits no replication configuration', () => {
    for (const resource of buckets(production))
      expect(properties(resource)).not.toHaveProperty('ReplicationConfiguration');
  });

  test('STORAGE-IMP-042 emits no Object Lock configuration', () => {
    for (const resource of buckets(production)) {
      expect(properties(resource)).not.toHaveProperty('ObjectLockEnabled');
      expect(properties(resource)).not.toHaveProperty('ObjectLockConfiguration');
    }
  });

  test('STORAGE-IMP-043 emits no server access logging', () => {
    for (const resource of buckets(production))
      expect(properties(resource)).not.toHaveProperty('LoggingConfiguration');
  });

  test('STORAGE-IMP-044 retains every bucket on deletion', () => {
    for (const resource of [...buckets(staging), ...buckets(production)]) {
      expect(resource.DeletionPolicy).toBe('Retain');
    }
  });

  test('STORAGE-IMP-045 retains every bucket on replacement', () => {
    for (const resource of [...buckets(staging), ...buckets(production)]) {
      expect(resource.UpdateReplacePolicy).toBe('Retain');
    }
  });
});

describe('storage lifecycle', () => {
  test('STORAGE-IMP-046 aborts multipart at one day in all buckets', () => {
    for (const resource of [...buckets(staging), ...buckets(production)]) {
      expect(ruleById(resource, 'AbortIncompleteMultipartUploads')).toMatchObject({
        AbortIncompleteMultipartUpload: { DaysAfterInitiation: 1 },
        Status: 'Enabled',
      });
    }
  });

  test('STORAGE-IMP-047 expires public staging noncurrent versions at 30 days', () => {
    expect(
      ruleById(bucket(staging, 'PublicMediaBucket'), 'ExpirePublicMediaNoncurrentVersions'),
    ).toMatchObject({
      NoncurrentVersionExpiration: { NoncurrentDays: 30 },
    });
  });

  test('STORAGE-IMP-048 expires public production noncurrent versions at 90 days', () => {
    expect(
      ruleById(bucket(production, 'PublicMediaBucket'), 'ExpirePublicMediaNoncurrentVersions'),
    ).toMatchObject({
      NoncurrentVersionExpiration: { NoncurrentDays: 90 },
    });
  });

  test('STORAGE-IMP-049 expires private staging noncurrent versions at 30 days', () => {
    expect(
      ruleById(
        bucket(staging, 'PrivateDocumentsBucket'),
        'ExpirePrivateDocumentNoncurrentVersions',
      ),
    ).toMatchObject({
      NoncurrentVersionExpiration: { NoncurrentDays: 30 },
    });
  });

  test('STORAGE-IMP-050 transitions private production objects after 30 days', () => {
    expect(
      ruleById(
        bucket(production, 'PrivateDocumentsBucket'),
        'TransitionPrivateDocumentsToIntelligentTiering',
      ),
    ).toMatchObject({
      Transitions: [{ StorageClass: 'INTELLIGENT_TIERING', TransitionInDays: 30 }],
    });
  });

  test('STORAGE-IMP-051 gives private production no general expiration', () => {
    const rules = lifecycleRules(bucket(production, 'PrivateDocumentsBucket'));
    expect(
      rules.filter((rule) => rule.Prefix === undefined && rule.ExpirationInDays !== undefined),
    ).toHaveLength(0);
    expect(JSON.stringify(rules)).not.toContain('NoncurrentVersionExpiration');
  });

  test('STORAGE-IMP-052 expires synthetic clinical staging noncurrent versions at 30 days', () => {
    expect(
      ruleById(
        bucket(staging, 'ClinicalRecordsBucket'),
        'ExpireSyntheticClinicalNoncurrentVersions',
      ),
    ).toMatchObject({
      NoncurrentVersionExpiration: { NoncurrentDays: 30 },
    });
  });

  test('STORAGE-IMP-053 transitions clinical production objects after 30 days', () => {
    expect(
      ruleById(
        bucket(production, 'ClinicalRecordsBucket'),
        'TransitionClinicalRecordsToIntelligentTiering',
      ),
    ).toMatchObject({
      Transitions: [{ StorageClass: 'INTELLIGENT_TIERING', TransitionInDays: 30 }],
    });
  });

  test('STORAGE-IMP-054 gives clinical production no current expiration', () => {
    expect(
      JSON.stringify(lifecycleRules(bucket(production, 'ClinicalRecordsBucket'))),
    ).not.toContain('ExpirationInDays');
  });

  test('STORAGE-IMP-055 gives clinical production no noncurrent expiration', () => {
    expect(
      JSON.stringify(lifecycleRules(bucket(production, 'ClinicalRecordsBucket'))),
    ).not.toContain('NoncurrentVersionExpiration');
  });

  test('STORAGE-IMP-056 expires temporary exports after seven days', () => {
    for (const rendered of [staging, production]) {
      expect(
        ruleById(bucket(rendered, 'PrivateDocumentsBucket'), 'ExpireTemporaryExports'),
      ).toMatchObject({
        Prefix: 'temporary-exports/',
        ExpirationInDays: 7,
      });
    }
  });

  test('STORAGE-IMP-057 expires pending quarantine objects after seven days', () => {
    expect(
      ruleById(bucket(staging, 'UploadQuarantineBucket'), 'ExpireQuarantinePending'),
    ).toMatchObject({
      ExpirationInDays: 7,
      TagFilters: [{ Key: 'scan-status', Value: 'pending' }],
    });
  });

  test('STORAGE-IMP-058 expires failed quarantine objects after fourteen days', () => {
    expect(
      ruleById(bucket(staging, 'UploadQuarantineBucket'), 'ExpireQuarantineFailed'),
    ).toMatchObject({
      ExpirationInDays: 14,
      TagFilters: [{ Key: 'scan-status', Value: 'failed' }],
    });
  });

  test('STORAGE-IMP-059 expires infected quarantine objects after thirty days', () => {
    expect(
      ruleById(bucket(production, 'UploadQuarantineBucket'), 'ExpireQuarantineInfected'),
    ).toMatchObject({
      ExpirationInDays: 30,
      TagFilters: [{ Key: 'scan-status', Value: 'infected' }],
    });
  });

  test('STORAGE-IMP-060 expires clean quarantine objects after one day', () => {
    expect(
      ruleById(bucket(production, 'UploadQuarantineBucket'), 'ExpireQuarantineClean'),
    ).toMatchObject({ ExpirationInDays: 1, TagFilters: [{ Key: 'scan-status', Value: 'clean' }] });
  });

  test('STORAGE-IMP-061 uses no Glacier storage class', () => {
    expect(JSON.stringify([...buckets(staging), ...buckets(production)])).not.toContain('GLACIER');
  });

  test('STORAGE-IMP-062 uses no Deep Archive storage class', () => {
    expect(JSON.stringify([...buckets(staging), ...buckets(production)])).not.toContain(
      'DEEP_ARCHIVE',
    );
  });
});

describe('storage events and dependencies', () => {
  test('STORAGE-IMP-063 enables native EventBridge only on quarantine', () => {
    expect(
      properties(bucket(production, 'UploadQuarantineBucket')).NotificationConfiguration,
    ).toEqual({ EventBridgeConfiguration: { EventBridgeEnabled: true } });
    for (const prefix of ['PublicMediaBucket', 'PrivateDocumentsBucket', 'ClinicalRecordsBucket']) {
      expect(properties(bucket(production, prefix))).not.toHaveProperty(
        'NotificationConfiguration',
      );
    }
  });

  test('STORAGE-IMP-064 creates no SQS resource', () => {
    expect(resourcesOfType(staging.resources, 'AWS::SQS::Queue')).toHaveLength(0);
  });

  test('STORAGE-IMP-065 creates no Lambda resource', () => {
    expect(resourcesOfType(staging.resources, 'AWS::Lambda::Function')).toHaveLength(0);
  });

  test('STORAGE-IMP-066 creates no Fargate task resource', () => {
    expect(resourcesOfType(staging.resources, 'AWS::ECS::TaskDefinition')).toHaveLength(0);
  });

  test('STORAGE-IMP-067 creates no EventBridge Rule', () => {
    expect(resourcesOfType(staging.resources, 'AWS::Events::Rule')).toHaveLength(0);
  });

  test('STORAGE-IMP-068 keeps Storage independent from Jobs while Jobs consumes Storage', () => {
    expect(directDependencyNames(production.stage.storageStack)).toEqual(['mxmed-prd-security']);
    expect(directDependencyNames(production.stage.jobsStack)).toEqual(
      ['mxmed-prd-compute', 'mxmed-prd-security', 'mxmed-prd-storage'].sort(),
    );
  });
});

describe('typed storage outputs', () => {
  test('STORAGE-IMP-122 exposes the four-bucket inventory', () => {
    expect(Object.keys(production.stage.storageStack.bucketInventory).sort()).toEqual(
      [
        'clinicalRecordsBucket',
        'privateDocumentsBucket',
        'publicMediaBucket',
        'uploadQuarantineBucket',
      ].sort(),
    );
  });

  test('STORAGE-IMP-123 exposes the classification map', () => {
    expect(production.stage.storageStack.classificationMap).toEqual({
      'public-media': 'public',
      'private-documents': 'sensitive',
      'clinical-records': 'clinical',
      'upload-quarantine': 'sensitive',
    });
  });

  test('STORAGE-IMP-124 exposes the lifecycle contract', () => {
    expect(staging.stage.storageStack.lifecycleContract).toMatchObject({
      abortIncompleteMultipartUploadDays: 1,
      publicMediaNoncurrentRetentionDays: 30,
      privateDocumentsNoncurrentRetentionDays: 30,
      clinicalNoncurrentRetentionDays: 30,
      quarantineRetentionDays: { pending: 7, clean: 1, infected: 30, failed: 14 },
      temporaryExportRetentionDays: 7,
      privateStorageTransitionDays: null,
      clinicalStorageTransitionDays: null,
    });
    expect(production.stage.storageStack.lifecycleContract).toMatchObject({
      publicMediaNoncurrentRetentionDays: 90,
      privateDocumentsNoncurrentRetentionDays: null,
      clinicalNoncurrentRetentionDays: null,
      privateStorageTransitionDays: 30,
      clinicalStorageTransitionDays: 30,
    });
  });

  test('STORAGE-IMP-125 exposes bounded presigned URL TTLs', () => {
    expect(production.stage.storageStack.uploadUrlTtlSeconds).toBe(600);
    expect(production.stage.storageStack.downloadUrlTtlSeconds).toBe(300);
  });

  test('STORAGE-IMP-126 exposes exact MIME allowlists', () => {
    expect(production.stage.storageStack.allowedMimeTypes).toEqual({
      public: ['image/jpeg', 'image/png', 'image/webp'],
      private: ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'],
      clinical: ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'],
    });
  });
});
