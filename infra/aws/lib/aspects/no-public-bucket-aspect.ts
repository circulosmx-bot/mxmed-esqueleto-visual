import { Annotations } from 'aws-cdk-lib';
import type { IAspect } from 'aws-cdk-lib';
import { CfnBucket } from 'aws-cdk-lib/aws-s3';
import type { IConstruct } from 'constructs';

const PUBLIC_ACCESS_CONTROLS = new Set(['AuthenticatedRead', 'PublicRead', 'PublicReadWrite']);

function hasSafePublicAccessBlock(value: unknown): boolean {
  if (typeof value !== 'object' || value === null) {
    return false;
  }

  const block = value as Record<string, unknown>;
  return (
    block.blockPublicAcls === true &&
    block.blockPublicPolicy === true &&
    block.ignorePublicAcls === true &&
    block.restrictPublicBuckets === true
  );
}

export class NoPublicBucketAspect implements IAspect {
  public visit(node: IConstruct): void {
    if (!(node instanceof CfnBucket)) {
      return;
    }

    if (
      PUBLIC_ACCESS_CONTROLS.has(node.accessControl ?? '') ||
      !hasSafePublicAccessBlock(node.publicAccessBlockConfiguration)
    ) {
      Annotations.of(node).addError('MXMED_PUBLIC_BUCKET_FORBIDDEN');
    }
  }
}
