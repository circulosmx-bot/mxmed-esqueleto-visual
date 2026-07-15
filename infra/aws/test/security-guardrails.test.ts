import { App, AspectPriority, Aspects, RemovalPolicy, Stack } from 'aws-cdk-lib';
import { CfnTrail } from 'aws-cdk-lib/aws-cloudtrail';
import { CfnAccessKey, CfnRole, CfnUser } from 'aws-cdk-lib/aws-iam';
import { CfnKey } from 'aws-cdk-lib/aws-kms';
import { CfnBucket } from 'aws-cdk-lib/aws-s3';
import { CfnSecret } from 'aws-cdk-lib/aws-secretsmanager';
import { Annotations as AssertionAnnotations, Match } from 'aws-cdk-lib/assertions';

import { LeastPrivilegeIamAspect } from '../lib/aspects/least-privilege-iam-aspect';
import { NoPlaintextSecretAspect } from '../lib/aspects/no-plaintext-secret-aspect';
import { SecurityFoundationAspect } from '../lib/aspects/security-foundation-aspect';
import { PRODUCTION_CONFIG } from '../lib/config/environments';

function syntheticStack(id = 'SyntheticSecurityGuardrailStack'): Stack {
  return new Stack(new App({ analyticsReporting: false }), id);
}

describe('security guardrail failures', () => {
  test('SEC-IMP-087 rejects a synthetic IAM user', () => {
    const stack = syntheticStack();
    new CfnUser(stack, 'SyntheticUser');
    Aspects.of(stack).add(new LeastPrivilegeIamAspect(), { priority: AspectPriority.READONLY });
    AssertionAnnotations.fromStack(stack).hasError('*', 'MXMED_IAM_USER_FORBIDDEN');
  });

  test('SEC-IMP-088 rejects a synthetic access key', () => {
    const stack = syntheticStack();
    new CfnAccessKey(stack, 'SyntheticAccessKey', { userName: 'synthetic-user' });
    Aspects.of(stack).add(new LeastPrivilegeIamAspect(), { priority: AspectPriority.READONLY });
    AssertionAnnotations.fromStack(stack).hasError('*', 'MXMED_IAM_ACCESS_KEY_FORBIDDEN');
  });

  test('SEC-IMP-089 rejects a KMS key without rotation', () => {
    const stack = syntheticStack();
    const key = new CfnKey(stack, 'SyntheticKey', {
      keyPolicy: {
        Version: '2012-10-17',
        Statement: [],
      },
      enableKeyRotation: false,
      keySpec: 'SYMMETRIC_DEFAULT',
      keyUsage: 'ENCRYPT_DECRYPT',
      multiRegion: false,
      pendingWindowInDays: 30,
    });
    key.applyRemovalPolicy(RemovalPolicy.RETAIN);
    Aspects.of(stack).add(new SecurityFoundationAspect(PRODUCTION_CONFIG), {
      priority: AspectPriority.READONLY,
    });
    AssertionAnnotations.fromStack(stack).hasError('*', 'MXMED_SECURITY_KMS_GUARDRAIL_FAILED');
  });

  test('SEC-IMP-090 rejects plaintext in a secret resource', () => {
    const stack = syntheticStack();
    const secret = new CfnSecret(stack, 'SyntheticPlaintextSecret', {
      name: '/mxmed/production/providers/ai/api-key',
      kmsKeyId: 'synthetic-key-reference',
      secretString: ['synthetic', 'plaintext'].join('-'),
    });
    secret.applyRemovalPolicy(RemovalPolicy.RETAIN);
    Aspects.of(stack).add(new NoPlaintextSecretAspect('production'), {
      priority: AspectPriority.READONLY,
    });
    AssertionAnnotations.fromStack(stack).hasError('*', 'MXMED_SECURITY_SECRET_GUARDRAIL_FAILED');
  });

  test('SEC-IMP-091 rejects a public audit bucket', () => {
    const stack = syntheticStack();
    const bucket = new CfnBucket(stack, 'SyntheticAuditBucket', {
      publicAccessBlockConfiguration: {
        blockPublicAcls: false,
        blockPublicPolicy: false,
        ignorePublicAcls: false,
        restrictPublicBuckets: false,
      },
      versioningConfiguration: { status: 'Enabled' },
    });
    bucket.applyRemovalPolicy(RemovalPolicy.RETAIN);
    Aspects.of(stack).add(new SecurityFoundationAspect(PRODUCTION_CONFIG), {
      priority: AspectPriority.READONLY,
    });
    AssertionAnnotations.fromStack(stack).hasError(
      '*',
      'MXMED_SECURITY_AUDIT_BUCKET_GUARDRAIL_FAILED',
    );
  });

  test('SEC-IMP-092 rejects a trail without file validation', () => {
    const stack = syntheticStack();
    new CfnTrail(stack, 'SyntheticTrail', {
      s3BucketName: 'synthetic-bucket-reference',
      enableLogFileValidation: false,
      includeGlobalServiceEvents: true,
      isLogging: true,
      isMultiRegionTrail: true,
      kmsKeyId: 'synthetic-key-reference',
      eventSelectors: [{ includeManagementEvents: true, readWriteType: 'All' }],
    });
    Aspects.of(stack).add(new SecurityFoundationAspect(PRODUCTION_CONFIG), {
      priority: AspectPriority.READONLY,
    });
    AssertionAnnotations.fromStack(stack).hasError(
      '*',
      'MXMED_SECURITY_CLOUDTRAIL_GUARDRAIL_FAILED',
    );
  });

  test('SEC-IMP-093 rejects a wildcard trust principal', () => {
    const stack = syntheticStack();
    new CfnRole(stack, 'SyntheticRole', {
      assumeRolePolicyDocument: {
        Version: '2012-10-17',
        Statement: [
          {
            Effect: 'Allow',
            Action: 'sts:AssumeRole',
            Principal: '*',
          },
        ],
      },
    });
    Aspects.of(stack).add(new LeastPrivilegeIamAspect(), { priority: AspectPriority.READONLY });
    AssertionAnnotations.fromStack(stack).hasError('*', 'MXMED_IAM_PUBLIC_PRINCIPAL_FORBIDDEN');
  });

  test('SEC-IMP-094 rejects a workload role without a boundary', () => {
    const stack = syntheticStack();
    new CfnRole(stack, 'SyntheticWorkloadRole', {
      assumeRolePolicyDocument: {
        Version: '2012-10-17',
        Statement: [
          {
            Effect: 'Allow',
            Action: 'sts:AssumeRole',
            Principal: { Service: 'ecs-tasks.amazonaws.com' },
          },
        ],
      },
    });
    Aspects.of(stack).add(new LeastPrivilegeIamAspect(), { priority: AspectPriority.READONLY });
    AssertionAnnotations.fromStack(stack).hasError(
      '*',
      Match.stringLikeRegexp('MXMED_IAM_PERMISSION_BOUNDARY_REQUIRED'),
    );
  });
});
