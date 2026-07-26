import { AspectPriority, Aspects, CfnOutput, Duration, RemovalPolicy, Tags } from 'aws-cdk-lib';
import { Repository, RepositoryEncryption, TagMutability, TagStatus } from 'aws-cdk-lib/aws-ecr';
import { CfnKey, Key, KeySpec, KeyUsage } from 'aws-cdk-lib/aws-kms';
import type { Construct } from 'constructs';

import { RegistryFoundationAspect } from '../aspects/registry-foundation-aspect';
import { registryFoundationIsEnabled } from '../config/compute-config';
import { mxmedName } from '../utils/naming';
import { BaseMxMedStack } from './base-mxmed-stack';
import type { MxMedContractStackProps } from './base-mxmed-stack';

/** Dedicated KMS and immutable ECR boundary, independently deployable from runtime foundations. */
export class MxMedRegistryStack extends BaseMxMedStack {
  public readonly registryKey: Key;
  public readonly applicationRepository: Repository;

  public constructor(scope: Construct, id: string, props: MxMedContractStackProps) {
    super(scope, id, {
      ...props,
      component: 'registry',
      description: 'MXMed dedicated immutable application-image registry foundation.',
      metadata: { dataClassification: 'internal', criticality: 'high', backup: 'not-required' },
    });

    const { config } = props;
    if (!registryFoundationIsEnabled(config.computeActivationMode)) {
      throw new Error('MXMED_REGISTRY_STACK_MODE_INVALID');
    }

    const registryTagOptions = { priority: 300 };
    Tags.of(this).add('ComputeActivationMode', config.computeActivationMode, registryTagOptions);
    Tags.of(this).add(
      'RuntimeCapabilityProfile',
      config.runtimeCapabilityProfile ?? 'not-applicable',
      registryTagOptions,
    );

    this.registryKey = new Key(this, 'RegistryKey', {
      alias: `alias/${mxmedName(config.environmentCode, 'registry')}`,
      description: `MXMed ${config.environmentName} container registry encryption key.`,
      keySpec: KeySpec.SYMMETRIC_DEFAULT,
      keyUsage: KeyUsage.ENCRYPT_DECRYPT,
      enableKeyRotation: config.enableKeyRotation,
      multiRegion: false,
      pendingWindow: Duration.days(config.kmsDeletionWindowDays),
      removalPolicy: RemovalPolicy.RETAIN,
    });
    const keyResource = this.registryKey.node.defaultChild;
    if (!(keyResource instanceof CfnKey)) {
      throw new Error('MXMED_REGISTRY_KMS_RESOURCE_INVALID');
    }
    keyResource.bypassPolicyLockoutSafetyCheck = false;
    keyResource.applyRemovalPolicy(RemovalPolicy.RETAIN);

    this.applicationRepository = new Repository(this, 'ApplicationRepository', {
      repositoryName: mxmedName(config.environmentCode, 'application'),
      encryption: RepositoryEncryption.KMS,
      encryptionKey: this.registryKey,
      imageScanOnPush: config.computeImageScanOnPush,
      imageTagMutability: TagMutability.IMMUTABLE,
      emptyOnDelete: false,
      removalPolicy: RemovalPolicy.RETAIN,
      lifecycleRules: [
        {
          description: 'Expire untagged images after the profile-specific review window.',
          tagStatus: TagStatus.UNTAGGED,
          maxImageAge: Duration.days(config.computeEcrUntaggedRetentionDays),
        },
        {
          description: 'Retain only the profile-specific maximum image count.',
          tagStatus: TagStatus.ANY,
          maxImageCount: config.computeEcrMaxImageCount,
        },
      ],
    });

    new CfnOutput(this, 'ApplicationRepositoryUri', {
      value: this.applicationRepository.repositoryUri,
      description: 'MXMed application repository URI.',
    });
    new CfnOutput(this, 'ApplicationRepositoryArn', {
      value: this.applicationRepository.repositoryArn,
      description: 'MXMed application repository ARN.',
    });
    new CfnOutput(this, 'RegistryKeyArn', {
      value: this.registryKey.keyArn,
      description: 'MXMed registry KMS key ARN.',
    });

    Aspects.of(this).add(new RegistryFoundationAspect(config), {
      priority: AspectPriority.READONLY,
    });
  }
}
