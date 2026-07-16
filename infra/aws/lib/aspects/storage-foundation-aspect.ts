import { Annotations, CfnDeletionPolicy, Stack } from 'aws-cdk-lib';
import type { IAspect } from 'aws-cdk-lib';
import { CfnBucket, CfnBucketPolicy } from 'aws-cdk-lib/aws-s3';
import type { IConstruct } from 'constructs';

import type { MxMedEnvironmentConfig } from '../config/environment-config';

const CONTRACT_BUCKET_IDS = [
  'PublicMediaBucket',
  'PrivateDocumentsBucket',
  'ClinicalRecordsBucket',
  'UploadQuarantineBucket',
] as const;

type ContractBucketId = (typeof CONTRACT_BUCKET_IDS)[number];

function asRecord(value: unknown): Record<string, unknown> | undefined {
  return typeof value === 'object' && value !== null && !Array.isArray(value)
    ? (value as Record<string, unknown>)
    : undefined;
}

function asArray(value: unknown): unknown[] {
  return Array.isArray(value) ? value : [];
}

function contractBucketId(bucket: CfnBucket): ContractBucketId | undefined {
  return CONTRACT_BUCKET_IDS.find((id) => bucket.node.path.includes(`/${id}/Resource`));
}

function hasSafePublicAccessBlock(value: unknown): boolean {
  const block = asRecord(value);
  return (
    block?.blockPublicAcls === true &&
    block.blockPublicPolicy === true &&
    block.ignorePublicAcls === true &&
    block.restrictPublicBuckets === true
  );
}

function hasBucketOwnerEnforced(value: unknown): boolean {
  const controls = asRecord(value);
  return asArray(controls?.rules).some(
    (rule) => asRecord(rule)?.objectOwnership === 'BucketOwnerEnforced',
  );
}

function hasApplicationDataKmsEncryption(value: unknown): boolean {
  const encryption = asRecord(value);
  const configurations = asArray(encryption?.serverSideEncryptionConfiguration);
  return configurations.some((configuration) => {
    const record = asRecord(configuration);
    const byDefault = asRecord(record?.serverSideEncryptionByDefault);
    return (
      record?.bucketKeyEnabled === true &&
      byDefault?.sseAlgorithm === 'aws:kms' &&
      JSON.stringify(byDefault.kmsMasterKeyId).includes('ApplicationDataKey')
    );
  });
}

function hasSslOnlyPolicy(bucket: CfnBucket): boolean {
  const bucketId = bucket.node.scope?.node.id;
  if (bucketId === undefined) return false;
  const policies = bucket.node.scope?.node
    .findAll()
    .filter((node): node is CfnBucketPolicy => node instanceof CfnBucketPolicy);
  return (policies ?? []).some((policy) => {
    const text = JSON.stringify(Stack.of(policy).resolve(policy.policyDocument));
    return (
      policy.node.path.includes(`/${bucketId}/Policy/`) &&
      text.includes('aws:SecureTransport') &&
      text.includes('false') &&
      text.includes('Deny')
    );
  });
}

function lifecycleRules(bucket: CfnBucket): Record<string, unknown>[] {
  const configuration = asRecord(Stack.of(bucket).resolve(bucket.lifecycleConfiguration));
  return asArray(configuration?.rules)
    .map(asRecord)
    .filter((rule): rule is Record<string, unknown> => rule !== undefined);
}

function hasMultipartAbortAtOneDay(rules: readonly Record<string, unknown>[]): boolean {
  return rules.some((rule) => {
    const abort = asRecord(rule.abortIncompleteMultipartUpload);
    return abort?.daysAfterInitiation === 1;
  });
}

function hasForbiddenClinicalLifecycle(rules: readonly Record<string, unknown>[]): boolean {
  return rules.some((rule) => {
    const hasExpiration =
      rule.expirationDate !== undefined ||
      rule.expirationInDays !== undefined ||
      rule.expiredObjectDeleteMarker === true ||
      rule.noncurrentVersionExpiration !== undefined ||
      rule.noncurrentVersionExpirationInDays !== undefined;
    const transitionText = JSON.stringify([
      rule.transition,
      rule.transitions,
      rule.noncurrentVersionTransition,
      rule.noncurrentVersionTransitions,
    ]).toUpperCase();
    return (
      hasExpiration || transitionText.includes('GLACIER') || transitionText.includes('DEEP_ARCHIVE')
    );
  });
}

function hasEventBridgeEnabled(value: unknown): boolean {
  const notification = asRecord(value);
  const eventBridge = asRecord(notification?.eventBridgeConfiguration);
  return eventBridge?.eventBridgeEnabled === true;
}

/** Storage-specific fail-closed checks. It reports drift and never mutates resources. */
export class StorageFoundationAspect implements IAspect {
  public constructor(private readonly config: MxMedEnvironmentConfig) {}

  public visit(node: IConstruct): void {
    if (!(node instanceof CfnBucket)) return;

    const bucketId = contractBucketId(node);
    const stackBuckets = Stack.of(node)
      .node.findAll()
      .filter((candidate): candidate is CfnBucket => candidate instanceof CfnBucket);
    if (stackBuckets.length > 4) {
      Annotations.of(node).addError('MXMED_STORAGE_BUCKET_COUNT_INVALID');
    }
    if (bucketId === undefined) {
      Annotations.of(node).addError('MXMED_STORAGE_BUCKET_UNKNOWN');
      return;
    }

    if (
      node.accessControl === 'AuthenticatedRead' ||
      node.accessControl === 'PublicRead' ||
      node.accessControl === 'PublicReadWrite' ||
      !hasSafePublicAccessBlock(Stack.of(node).resolve(node.publicAccessBlockConfiguration))
    ) {
      Annotations.of(node).addError('MXMED_STORAGE_PUBLIC_ACCESS_FORBIDDEN');
    }
    if (!hasBucketOwnerEnforced(Stack.of(node).resolve(node.ownershipControls))) {
      Annotations.of(node).addError('MXMED_STORAGE_OWNERSHIP_INVALID');
    }
    if (asRecord(Stack.of(node).resolve(node.versioningConfiguration))?.status !== 'Enabled') {
      Annotations.of(node).addError('MXMED_STORAGE_VERSIONING_REQUIRED');
    }
    if (!hasApplicationDataKmsEncryption(Stack.of(node).resolve(node.bucketEncryption))) {
      Annotations.of(node).addError('MXMED_STORAGE_ENCRYPTION_INVALID');
    }
    if (!hasSslOnlyPolicy(node)) {
      Annotations.of(node).addError('MXMED_STORAGE_SSL_REQUIRED');
    }
    if (node.bucketName !== undefined || node.bucketNamePrefix !== undefined) {
      Annotations.of(node).addError('MXMED_STORAGE_PHYSICAL_NAME_FORBIDDEN');
    }
    if (node.websiteConfiguration !== undefined) {
      Annotations.of(node).addError('MXMED_STORAGE_WEBSITE_FORBIDDEN');
    }
    if (node.objectLockEnabled === true || node.objectLockConfiguration !== undefined) {
      Annotations.of(node).addError('MXMED_STORAGE_OBJECT_LOCK_FORBIDDEN');
    }
    if (node.replicationConfiguration !== undefined) {
      Annotations.of(node).addError('MXMED_STORAGE_REPLICATION_FORBIDDEN');
    }
    if (node.corsConfiguration !== undefined) {
      Annotations.of(node).addError('MXMED_STORAGE_CORS_FORBIDDEN');
    }
    if (node.loggingConfiguration !== undefined) {
      Annotations.of(node).addError('MXMED_STORAGE_SERVER_LOGGING_FORBIDDEN');
    }
    if (
      node.cfnOptions.deletionPolicy !== CfnDeletionPolicy.RETAIN ||
      node.cfnOptions.updateReplacePolicy !== CfnDeletionPolicy.RETAIN
    ) {
      Annotations.of(node).addError('MXMED_STORAGE_RETENTION_POLICY_REQUIRED');
    }

    const rules = lifecycleRules(node);
    if (!hasMultipartAbortAtOneDay(rules)) {
      Annotations.of(node).addError('MXMED_STORAGE_MULTIPART_ABORT_REQUIRED');
    }
    if (
      bucketId === 'UploadQuarantineBucket' &&
      !hasEventBridgeEnabled(Stack.of(node).resolve(node.notificationConfiguration))
    ) {
      Annotations.of(node).addError('MXMED_STORAGE_QUARANTINE_EVENTBRIDGE_REQUIRED');
    }
    if (
      bucketId === 'ClinicalRecordsBucket' &&
      this.config.environmentName === 'production' &&
      hasForbiddenClinicalLifecycle(rules)
    ) {
      Annotations.of(node).addError('MXMED_STORAGE_CLINICAL_RETENTION_INVALID');
    }
  }
}
