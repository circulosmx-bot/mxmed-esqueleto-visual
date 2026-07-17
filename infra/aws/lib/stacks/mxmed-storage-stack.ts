import { AspectPriority, Aspects, CfnParameter, Duration, RemovalPolicy, Tags } from 'aws-cdk-lib';
import { Effect, PolicyStatement, ServicePrincipal } from 'aws-cdk-lib/aws-iam';
import type { IKey } from 'aws-cdk-lib/aws-kms';
import {
  BlockPublicAccess,
  Bucket,
  BucketEncryption,
  ObjectOwnership,
  StorageClass,
} from 'aws-cdk-lib/aws-s3';
import type { BucketProps, IBucket, LifecycleRule, CfnBucket } from 'aws-cdk-lib/aws-s3';
import type { Construct } from 'constructs';

import { BaseMxMedStack } from './base-mxmed-stack';
import type { MxMedContractStackProps } from './base-mxmed-stack';
import { StorageFoundationAspect } from '../aspects/storage-foundation-aspect';
import type { MxMedStorageAllowedMimeTypes } from '../config/environment-config';
import {
  STORAGE_BUCKET_CLASSIFICATION_MAP,
  type StorageBucketClassification,
  type StorageBucketInventory,
  type StorageBucketPurpose,
  type StorageLifecycleContract,
} from '../constructs/storage-contract';
import { registerMxMedStorageValidation } from '../utils/storage-validation';
import { edgeUsesPublicMedia, MXMED_PUBLIC_MEDIA_OBJECT_PREFIX } from '../config/edge-config';

export interface MxMedStorageStackProps extends MxMedContractStackProps {
  readonly applicationDataKey: IKey;
}

interface BucketTagContract {
  readonly classification: StorageBucketClassification;
  readonly criticality: 'medium' | 'high';
  readonly backup: 'required' | 'not-required';
  readonly component: string;
}

function abortMultipartRule(): LifecycleRule {
  return {
    id: 'AbortIncompleteMultipartUploads',
    enabled: true,
    abortIncompleteMultipartUploadAfter: Duration.days(1),
  };
}

function applyBucketTags(bucket: Bucket, contract: BucketTagContract): void {
  const options = { priority: 200 };
  Tags.of(bucket).add('Component', contract.component, options);
  Tags.of(bucket).add('DataClassification', contract.classification, options);
  Tags.of(bucket).add('Criticality', contract.criticality, options);
  Tags.of(bucket).add('Backup', contract.backup, options);
}

function asBucketInterface(bucket: Bucket): IBucket {
  // CDK 2.260.0 declares Bucket.isWebsite as optional while IBucket requires boolean.
  return bucket as unknown as IBucket;
}

/** Private, encrypted and versioned S3 object foundation for one MXMed environment. */
export class MxMedStorageStack extends BaseMxMedStack {
  public readonly publicMediaBucket: IBucket;
  public readonly privateDocumentsBucket: IBucket;
  public readonly clinicalRecordsBucket: IBucket;
  public readonly uploadQuarantineBucket: IBucket;
  public readonly bucketInventory: StorageBucketInventory<IBucket>;
  public readonly classificationMap: Readonly<
    Record<StorageBucketPurpose, StorageBucketClassification>
  >;
  public readonly lifecycleContract: StorageLifecycleContract;
  public readonly uploadUrlTtlSeconds: number;
  public readonly downloadUrlTtlSeconds: number;
  public readonly allowedMimeTypes: MxMedStorageAllowedMimeTypes;

  public constructor(scope: Construct, id: string, props: MxMedStorageStackProps) {
    super(scope, id, {
      ...props,
      component: 'storage',
      description: 'MXMed private, KMS-encrypted and versioned S3 storage foundation.',
      metadata: { dataClassification: 'clinical', criticality: 'high', backup: 'required' },
    });

    const { config } = props;
    const commonBucketProps: BucketProps = {
      autoDeleteObjects: false,
      blockPublicAccess: BlockPublicAccess.BLOCK_ALL,
      bucketKeyEnabled: config.storageBucketKeyEnabled,
      encryption: BucketEncryption.KMS,
      encryptionKey: props.applicationDataKey,
      enforceSSL: true,
      objectOwnership: ObjectOwnership.BUCKET_OWNER_ENFORCED,
      publicReadAccess: false,
      removalPolicy: RemovalPolicy.RETAIN,
      versioned: config.storageVersioningEnabled,
    };

    const publicMedia = new Bucket(this, 'PublicMediaBucket', {
      ...commonBucketProps,
      lifecycleRules: [
        abortMultipartRule(),
        {
          id: 'ExpirePublicMediaNoncurrentVersions',
          enabled: true,
          noncurrentVersionExpiration: Duration.days(config.publicMediaNoncurrentRetentionDays),
        },
      ],
    });
    applyBucketTags(publicMedia, {
      classification: 'public',
      criticality: 'medium',
      backup: 'required',
      component: 'storage-public-media',
    });
    if (edgeUsesPublicMedia(config)) {
      const distributionArn = new CfnParameter(this, 'PublicMediaCloudFrontDistributionArn', {
        type: 'String',
        allowedPattern: '^arn:[^:]+:cloudfront::[0-9]{12}:distribution/[A-Z0-9]+$',
        description: 'CloudFront distribution ARN captured through the approved edge handoff.',
      });
      publicMedia.addToResourcePolicy(
        new PolicyStatement({
          sid: 'AllowCloudFrontReadOnlyPublicMedia',
          effect: Effect.ALLOW,
          principals: [new ServicePrincipal('cloudfront.amazonaws.com')],
          actions: ['s3:GetObject'],
          resources: [publicMedia.arnForObjects(MXMED_PUBLIC_MEDIA_OBJECT_PREFIX)],
          conditions: {
            StringEquals: { 'AWS:SourceArn': distributionArn.valueAsString },
          },
        }),
      );
    }

    const privateLifecycleRules: LifecycleRule[] = [
      abortMultipartRule(),
      {
        id: 'ExpireTemporaryExports',
        enabled: true,
        prefix: 'temporary-exports/',
        expiration: Duration.days(config.temporaryExportRetentionDays),
      },
    ];
    if (config.privateDocumentsNoncurrentRetentionDays !== null) {
      privateLifecycleRules.push({
        id: 'ExpirePrivateDocumentNoncurrentVersions',
        enabled: true,
        noncurrentVersionExpiration: Duration.days(config.privateDocumentsNoncurrentRetentionDays),
      });
    }
    if (config.privateStorageTransitionDays !== null) {
      privateLifecycleRules.push({
        id: 'TransitionPrivateDocumentsToIntelligentTiering',
        enabled: true,
        transitions: [
          {
            storageClass: StorageClass.INTELLIGENT_TIERING,
            transitionAfter: Duration.days(config.privateStorageTransitionDays),
          },
        ],
      });
    }
    const privateDocuments = new Bucket(this, 'PrivateDocumentsBucket', {
      ...commonBucketProps,
      lifecycleRules: privateLifecycleRules,
    });
    applyBucketTags(privateDocuments, {
      classification: 'sensitive',
      criticality: 'high',
      backup: 'required',
      component: 'storage-private-documents',
    });

    const clinicalLifecycleRules: LifecycleRule[] = [abortMultipartRule()];
    if (config.clinicalNoncurrentRetentionDays !== null) {
      clinicalLifecycleRules.push({
        id: 'ExpireSyntheticClinicalNoncurrentVersions',
        enabled: true,
        noncurrentVersionExpiration: Duration.days(config.clinicalNoncurrentRetentionDays),
      });
    }
    if (config.clinicalStorageTransitionDays !== null) {
      clinicalLifecycleRules.push({
        id: 'TransitionClinicalRecordsToIntelligentTiering',
        enabled: true,
        transitions: [
          {
            storageClass: StorageClass.INTELLIGENT_TIERING,
            transitionAfter: Duration.days(config.clinicalStorageTransitionDays),
          },
        ],
      });
    }
    const clinicalRecords = new Bucket(this, 'ClinicalRecordsBucket', {
      ...commonBucketProps,
      lifecycleRules: clinicalLifecycleRules,
    });
    applyBucketTags(clinicalRecords, {
      classification: 'clinical',
      criticality: 'high',
      backup: 'required',
      component: 'storage-clinical-records',
    });

    const quarantineRetentionDays = Object.freeze({
      pending: config.quarantinePendingRetentionDays,
      clean: config.quarantineCleanRetentionDays,
      infected: config.quarantineInfectedRetentionDays,
      failed: config.quarantineFailedRetentionDays,
    });
    const quarantine = new Bucket(this, 'UploadQuarantineBucket', {
      ...commonBucketProps,
      lifecycleRules: [
        abortMultipartRule(),
        ...Object.entries(quarantineRetentionDays).map(([status, days]) => ({
          id: `ExpireQuarantine${status[0]?.toUpperCase() ?? ''}${status.slice(1)}`,
          enabled: true,
          expiration: Duration.days(days),
          tagFilters: { 'scan-status': status },
        })),
      ],
    });
    const quarantineResource = quarantine.node.defaultChild as CfnBucket;
    quarantineResource.notificationConfiguration = {
      eventBridgeConfiguration: {
        eventBridgeEnabled: config.enableQuarantineEventBridge,
      },
    };
    applyBucketTags(quarantine, {
      classification: 'sensitive',
      criticality: 'high',
      backup: 'not-required',
      component: 'storage-quarantine',
    });

    this.publicMediaBucket = asBucketInterface(publicMedia);
    this.privateDocumentsBucket = asBucketInterface(privateDocuments);
    this.clinicalRecordsBucket = asBucketInterface(clinicalRecords);
    this.uploadQuarantineBucket = asBucketInterface(quarantine);
    this.bucketInventory = Object.freeze({
      publicMediaBucket: this.publicMediaBucket,
      privateDocumentsBucket: this.privateDocumentsBucket,
      clinicalRecordsBucket: this.clinicalRecordsBucket,
      uploadQuarantineBucket: this.uploadQuarantineBucket,
    });
    this.classificationMap = STORAGE_BUCKET_CLASSIFICATION_MAP;
    this.lifecycleContract = Object.freeze({
      abortIncompleteMultipartUploadDays: 1,
      publicMediaNoncurrentRetentionDays: config.publicMediaNoncurrentRetentionDays,
      privateDocumentsNoncurrentRetentionDays: config.privateDocumentsNoncurrentRetentionDays,
      clinicalNoncurrentRetentionDays: config.clinicalNoncurrentRetentionDays,
      quarantineRetentionDays,
      temporaryExportRetentionDays: config.temporaryExportRetentionDays,
      privateStorageTransitionDays: config.privateStorageTransitionDays,
      clinicalStorageTransitionDays: config.clinicalStorageTransitionDays,
    });
    this.uploadUrlTtlSeconds = config.uploadUrlTtlSeconds;
    this.downloadUrlTtlSeconds = config.downloadUrlTtlSeconds;
    this.allowedMimeTypes = config.storageAllowedMimeTypes;

    Aspects.of(this).add(new StorageFoundationAspect(config), {
      priority: AspectPriority.READONLY,
    });
    registerMxMedStorageValidation(this);
  }
}
