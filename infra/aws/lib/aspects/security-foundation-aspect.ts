import { Annotations, CfnDeletionPolicy, Stack } from 'aws-cdk-lib';
import type { IAspect } from 'aws-cdk-lib';
import { CfnTrail } from 'aws-cdk-lib/aws-cloudtrail';
import { CfnAlias, CfnKey } from 'aws-cdk-lib/aws-kms';
import { CfnLogGroup } from 'aws-cdk-lib/aws-logs';
import { CfnBucket } from 'aws-cdk-lib/aws-s3';
import type { IConstruct } from 'constructs';

import type { MxMedEnvironmentConfig } from '../config/environment-config';

function retained(node: CfnKey | CfnBucket | CfnLogGroup): boolean {
  return (
    node.cfnOptions.deletionPolicy === CfnDeletionPolicy.RETAIN &&
    node.cfnOptions.updateReplacePolicy === CfnDeletionPolicy.RETAIN
  );
}

function safePublicAccessBlock(value: unknown): boolean {
  if (typeof value !== 'object' || value === null) return false;
  const block = value as Record<string, unknown>;
  return (
    block.blockPublicAcls === true &&
    block.blockPublicPolicy === true &&
    block.ignorePublicAcls === true &&
    block.restrictPublicBuckets === true
  );
}

export class SecurityFoundationAspect implements IAspect {
  public constructor(private readonly config: MxMedEnvironmentConfig) {}

  public visit(node: IConstruct): void {
    if (node instanceof CfnKey) {
      if (
        node.enableKeyRotation !== true ||
        node.multiRegion !== false ||
        !retained(node) ||
        (this.config.environmentName === 'production' &&
          node.cfnOptions.deletionPolicy === CfnDeletionPolicy.DELETE)
      ) {
        Annotations.of(node).addError('MXMED_SECURITY_KMS_GUARDRAIL_FAILED');
      }
      return;
    }

    if (node instanceof CfnAlias) {
      const expectedPrefix = `alias/mxmed-${this.config.environmentCode}-`;
      if (typeof node.aliasName !== 'string' || !node.aliasName.startsWith(expectedPrefix)) {
        Annotations.of(node).addError('MXMED_SECURITY_KMS_ALIAS_GUARDRAIL_FAILED');
      }
      return;
    }

    if (node instanceof CfnTrail) {
      if (
        node.enableLogFileValidation !== true ||
        node.isMultiRegionTrail !== true ||
        node.includeGlobalServiceEvents !== true ||
        node.kmsKeyId === undefined ||
        JSON.stringify(node.eventSelectors ?? []).includes('DataResources')
      ) {
        Annotations.of(node).addError('MXMED_SECURITY_CLOUDTRAIL_GUARDRAIL_FAILED');
      }
      return;
    }

    if (node instanceof CfnBucket) {
      const versioning = node.versioningConfiguration as { status?: string } | undefined;
      if (
        !safePublicAccessBlock(node.publicAccessBlockConfiguration) ||
        versioning?.status !== 'Enabled' ||
        !retained(node)
      ) {
        Annotations.of(node).addError('MXMED_SECURITY_AUDIT_BUCKET_GUARDRAIL_FAILED');
      }
      return;
    }

    if (node instanceof CfnLogGroup) {
      if (node.retentionInDays === undefined || node.kmsKeyId === undefined || !retained(node)) {
        Annotations.of(node).addError('MXMED_SECURITY_LOG_GROUP_GUARDRAIL_FAILED');
      }
      return;
    }

    if (node instanceof Stack && node.stackName.endsWith('-security')) {
      const trails = node.node.findAll().filter((child) => child instanceof CfnTrail);
      if (trails.length !== 1) {
        Annotations.of(node).addError('MXMED_SECURITY_MANAGEMENT_TRAIL_REQUIRED');
      }
    }
  }
}
