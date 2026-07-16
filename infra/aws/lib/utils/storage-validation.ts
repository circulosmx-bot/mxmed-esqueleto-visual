import { CfnOutput, CfnResource } from 'aws-cdk-lib';
import { CfnBucket } from 'aws-cdk-lib/aws-s3';
import type { IConstruct } from 'constructs';

const EXPECTED_BUCKET_IDS = new Set([
  'PublicMediaBucket',
  'PrivateDocumentsBucket',
  'ClinicalRecordsBucket',
  'UploadQuarantineBucket',
]);

const FORBIDDEN_RESOURCE_TYPES = new Set([
  'AWS::CloudFront::Distribution',
  'AWS::CloudTrail::Trail',
  'AWS::ECS::Service',
  'AWS::ECS::TaskDefinition',
  'AWS::Events::Rule',
  'AWS::Lambda::Function',
  'AWS::SQS::Queue',
]);

function validateStorageFoundation(scope: IConstruct): string[] {
  const errors: string[] = [];
  const resources = scope.node
    .findAll()
    .filter((node): node is CfnResource => node instanceof CfnResource);
  const buckets = resources.filter(
    (resource): resource is CfnBucket => resource instanceof CfnBucket,
  );
  const bucketIds = buckets.map((bucket) => bucket.node.scope?.node.id ?? 'unknown');
  const outputs = scope.node.findAll().filter((node) => node instanceof CfnOutput);

  if (buckets.length !== 4) errors.push('MXMED_STORAGE_BUCKET_COUNT_INVALID');
  if (
    bucketIds.length !== EXPECTED_BUCKET_IDS.size ||
    bucketIds.some((id) => !EXPECTED_BUCKET_IDS.has(id))
  ) {
    errors.push('MXMED_STORAGE_BUCKET_INVENTORY_INVALID');
  }
  if (outputs.length !== 0) errors.push('MXMED_STORAGE_OUTPUT_FORBIDDEN');
  if (resources.some((resource) => FORBIDDEN_RESOURCE_TYPES.has(resource.cfnResourceType))) {
    errors.push('MXMED_STORAGE_FUTURE_RESOURCE_FORBIDDEN');
  }

  return [...new Set(errors)].sort();
}

export function registerMxMedStorageValidation(scope: IConstruct): void {
  scope.node.addValidation({ validate: () => validateStorageFoundation(scope) });
}
