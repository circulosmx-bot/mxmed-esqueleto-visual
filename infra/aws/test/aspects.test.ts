import { App, AspectPriority, Aspects, RemovalPolicy, Stack } from 'aws-cdk-lib';
import { CfnDBInstance } from 'aws-cdk-lib/aws-rds';
import { CfnBucket } from 'aws-cdk-lib/aws-s3';
import { Annotations as AssertionAnnotations, Match } from 'aws-cdk-lib/assertions';

import { MandatoryTagsAspect } from '../lib/aspects/mandatory-tags-aspect';
import { NoPublicBucketAspect } from '../lib/aspects/no-public-bucket-aspect';
import { NoPublicDatabaseAspect } from '../lib/aspects/no-public-database-aspect';
import { ProductionRetentionAspect } from '../lib/aspects/production-retention-aspect';
import {
  MXMED_SAFE_STRIPE_RETURN_LOGGING_CONTROLS,
  StripeReturnLoggingSafetyAspect,
} from '../lib/aspects/stripe-return-logging-safety-aspect';

function testStack(): Stack {
  const app = new App({ analyticsReporting: false });
  return new Stack(app, 'SyntheticTestStack');
}

function addRequiredTags(bucket: CfnBucket): void {
  const tags = {
    Project: 'mxmed',
    Environment: 'production',
    ManagedBy: 'aws-cdk',
    Application: 'mexico-medico',
    Component: 'storage',
    DataClassification: 'clinical',
    Criticality: 'high',
    Backup: 'required',
    Owner: 'platform',
    DeploymentProfile: 'launch-lean-v1',
    CostReview: '2026-07-16',
    Ephemeral: 'false',
    SchedulePolicy: 'always-on',
    CostTier: 'storage-based',
  };
  for (const [key, value] of Object.entries(tags)) {
    bucket.tags.setTag(key, value);
  }
}

describe('MandatoryTagsAspect', () => {
  test('accepts a taggable resource with every mandatory tag', () => {
    const stack = testStack();
    const bucket = new CfnBucket(stack, 'Bucket');
    addRequiredTags(bucket);
    Aspects.of(stack).add(new MandatoryTagsAspect(), { priority: AspectPriority.READONLY });
    AssertionAnnotations.fromStack(stack).hasNoError(
      '*',
      Match.stringLikeRegexp('MXMED_MANDATORY_TAGS_MISSING'),
    );
  });

  test('rejects a taggable resource with missing tags', () => {
    const stack = testStack();
    new CfnBucket(stack, 'Bucket');
    Aspects.of(stack).add(new MandatoryTagsAspect(), { priority: AspectPriority.READONLY });
    AssertionAnnotations.fromStack(stack).hasError(
      '*',
      Match.stringLikeRegexp('MXMED_MANDATORY_TAGS_MISSING'),
    );
  });
});

describe('NoPublicBucketAspect', () => {
  test('accepts an explicitly blocked bucket', () => {
    const stack = testStack();
    new CfnBucket(stack, 'Bucket', {
      publicAccessBlockConfiguration: {
        blockPublicAcls: true,
        blockPublicPolicy: true,
        ignorePublicAcls: true,
        restrictPublicBuckets: true,
      },
    });
    Aspects.of(stack).add(new NoPublicBucketAspect(), { priority: AspectPriority.READONLY });
    AssertionAnnotations.fromStack(stack).hasNoError('*', 'MXMED_PUBLIC_BUCKET_FORBIDDEN');
  });

  test('rejects a bucket with public access enabled', () => {
    const stack = testStack();
    new CfnBucket(stack, 'Bucket', {
      accessControl: 'PublicRead',
      publicAccessBlockConfiguration: {
        blockPublicAcls: false,
        blockPublicPolicy: false,
        ignorePublicAcls: false,
        restrictPublicBuckets: false,
      },
    });
    Aspects.of(stack).add(new NoPublicBucketAspect(), { priority: AspectPriority.READONLY });
    AssertionAnnotations.fromStack(stack).hasError('*', 'MXMED_PUBLIC_BUCKET_FORBIDDEN');
  });
});

describe('NoPublicDatabaseAspect', () => {
  test('accepts an explicitly private database', () => {
    const stack = testStack();
    new CfnDBInstance(stack, 'Database', {
      dbInstanceClass: 'db.t4g.micro',
      engine: 'mysql',
      publiclyAccessible: false,
    });
    Aspects.of(stack).add(new NoPublicDatabaseAspect(), { priority: AspectPriority.READONLY });
    AssertionAnnotations.fromStack(stack).hasNoError('*', 'MXMED_PUBLIC_DATABASE_FORBIDDEN');
  });

  test('rejects a publicly accessible database', () => {
    const stack = testStack();
    new CfnDBInstance(stack, 'Database', {
      dbInstanceClass: 'db.t4g.micro',
      engine: 'mysql',
      publiclyAccessible: true,
    });
    Aspects.of(stack).add(new NoPublicDatabaseAspect(), { priority: AspectPriority.READONLY });
    AssertionAnnotations.fromStack(stack).hasError('*', 'MXMED_PUBLIC_DATABASE_FORBIDDEN');
  });
});

describe('ProductionRetentionAspect', () => {
  test('accepts an explicitly retained production bucket', () => {
    const stack = testStack();
    const bucket = new CfnBucket(stack, 'Bucket');
    bucket.applyRemovalPolicy(RemovalPolicy.RETAIN);
    Aspects.of(stack).add(new ProductionRetentionAspect('production'), {
      priority: AspectPriority.READONLY,
    });
    AssertionAnnotations.fromStack(stack).hasNoError(
      '*',
      'MXMED_PRODUCTION_DATA_RETENTION_REQUIRED',
    );
  });

  test('rejects a destructive production data resource', () => {
    const stack = testStack();
    new CfnBucket(stack, 'Bucket');
    Aspects.of(stack).add(new ProductionRetentionAspect('production'), {
      priority: AspectPriority.READONLY,
    });
    AssertionAnnotations.fromStack(stack).hasError('*', 'MXMED_PRODUCTION_DATA_RETENTION_REQUIRED');
  });
});

describe('StripeReturnLoggingSafetyAspect', () => {
  test('accepts the only contracted policy', () => {
    const stack = testStack();
    Aspects.of(stack).add(
      new StripeReturnLoggingSafetyAspect(MXMED_SAFE_STRIPE_RETURN_LOGGING_CONTROLS),
      { priority: AspectPriority.READONLY },
    );
    AssertionAnnotations.fromStack(stack).hasNoError(
      '*',
      'MXMED_STRIPE_RETURN_LOGGING_POLICY_UNSAFE',
    );
  });

  test('rejects any unsafe synthetic policy', () => {
    const stack = testStack();
    Aspects.of(stack).add(
      new StripeReturnLoggingSafetyAspect({
        ...MXMED_SAFE_STRIPE_RETURN_LOGGING_CONTROLS,
        queryFieldsExcluded: false,
      }),
      { priority: AspectPriority.READONLY },
    );
    AssertionAnnotations.fromStack(stack).hasError(
      '*',
      'MXMED_STRIPE_RETURN_LOGGING_POLICY_UNSAFE',
    );
  });
});
