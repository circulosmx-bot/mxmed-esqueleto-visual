import { App } from 'aws-cdk-lib';
import type { Stack } from 'aws-cdk-lib';
import { Annotations as AssertionAnnotations, Match, Template } from 'aws-cdk-lib/assertions';
import { CfnRepository } from 'aws-cdk-lib/aws-ecr';
import { CfnKey } from 'aws-cdk-lib/aws-kms';
import type { IConstruct } from 'constructs';

import type {
  MxMedDeploymentProfile,
  MxMedEnvironmentName,
} from '../lib/config/environment-config';
import { getEnvironmentConfig } from '../lib/config/environments';
import { MxMedEnvironmentStage } from '../lib/stages/mxmed-environment-stage';

function createStage(
  environment: MxMedEnvironmentName = 'staging',
  profile: MxMedDeploymentProfile = 'launch-lean-v1',
): MxMedEnvironmentStage {
  const app = new App({ analyticsReporting: false });
  const config = getEnvironmentConfig(environment, profile, 'registry-only-v1');
  return new MxMedEnvironmentStage(app, `Registry${environment}${profile}`, { config });
}

function requireRegistry(
  stage: MxMedEnvironmentStage,
): NonNullable<MxMedEnvironmentStage['registryStack']> {
  if (stage.registryStack === undefined) throw new Error('missing-registry-stack');
  return stage.registryStack;
}

function findConstruct<T extends IConstruct>(
  stack: Stack,
  resourceType: new (...args: never[]) => T,
): T {
  const found = stack.node
    .findAll()
    .find((candidate): candidate is T => candidate instanceof resourceType);
  if (found === undefined) throw new Error(`missing-construct:${resourceType.name}`);
  return found;
}

describe.each([
  ['staging', 'launch-lean-v1', 7, 20, 7],
  ['production', 'launch-lean-v1', 7, 20, 30],
  ['production', 'production-standard-v1', 14, 50, 30],
] as const)(
  '%s / %s dedicated Registry',
  (environment, profile, untaggedDays, maxImages, deletionWindow) => {
    const stage = createStage(environment, profile);
    const registry = requireRegistry(stage);
    const template = Template.fromStack(registry).toJSON() as {
      readonly Resources?: Readonly<
        Record<
          string,
          {
            readonly Type: string;
            readonly Properties?: Readonly<Record<string, unknown>>;
            readonly DeletionPolicy?: string;
            readonly UpdateReplacePolicy?: string;
          }
        >
      >;
      readonly Outputs?: Readonly<Record<string, unknown>>;
    };
    const resources = Object.values(template.Resources ?? {});
    const ofType = (type: string) => resources.filter((resource) => resource.Type === type);
    const repository = ofType('AWS::ECR::Repository')[0];
    const key = ofType('AWS::KMS::Key')[0];
    const alias = ofType('AWS::KMS::Alias')[0];

    test('creates exactly KMS key, alias and ECR repository', () => {
      expect(resources).toHaveLength(3);
      expect(ofType('AWS::KMS::Key')).toHaveLength(1);
      expect(ofType('AWS::KMS::Alias')).toHaveLength(1);
      expect(ofType('AWS::ECR::Repository')).toHaveLength(1);
    });

    test('uses the exact dedicated KMS contract with retention', () => {
      expect(key).toBeDefined();
      expect(key?.Properties).toMatchObject({
        EnableKeyRotation: true,
        KeySpec: 'SYMMETRIC_DEFAULT',
        KeyUsage: 'ENCRYPT_DECRYPT',
        PendingWindowInDays: deletionWindow,
      });
      expect(key?.DeletionPolicy).toBe('Retain');
      expect(key?.UpdateReplacePolicy).toBe('Retain');
      expect(alias?.Properties).toMatchObject({
        AliasName: `alias/mxmed-${environment === 'staging' ? 'stg' : 'prd'}-registry`,
      });
    });

    test('uses the exact immutable encrypted ECR contract and lifecycle', () => {
      expect(repository).toBeDefined();
      expect(repository?.Properties).toMatchObject({
        RepositoryName: `mxmed-${environment === 'staging' ? 'stg' : 'prd'}-application`,
        EmptyOnDelete: false,
        EncryptionConfiguration: { EncryptionType: 'KMS' },
        ImageScanningConfiguration: { ScanOnPush: true },
        ImageTagMutability: 'IMMUTABLE',
      });
      const lifecycle = JSON.parse(
        String(
          (repository?.Properties?.LifecyclePolicy as Readonly<Record<string, unknown>>)
            .LifecyclePolicyText,
        ),
      ) as {
        readonly rules: readonly {
          readonly selection: { readonly tagStatus: string; readonly countNumber: number };
        }[];
      };
      expect(lifecycle.rules).toHaveLength(2);
      expect(
        lifecycle.rules.map((rule) => ({
          tagStatus: rule.selection.tagStatus,
          countNumber: rule.selection.countNumber,
        })),
      ).toEqual([
        { tagStatus: 'untagged', countNumber: untaggedDays },
        { tagStatus: 'any', countNumber: maxImages },
      ]);
      expect(repository?.DeletionPolicy).toBe('Retain');
      expect(repository?.UpdateReplacePolicy).toBe('Retain');
    });

    test('publishes only the three non-secret registry outputs', () => {
      expect(Object.keys(template.Outputs ?? {})).toHaveLength(3);
      expect(JSON.stringify(template.Outputs)).toContain('ApplicationRepository');
      expect(JSON.stringify(template.Outputs)).toContain('RegistryKey');
      expect(JSON.stringify(template.Outputs)).not.toMatch(/digest|password|credential/i);
    });

    test('creates no IAM, secret, audit, networking or runtime resource', () => {
      const forbidden = [
        'AWS::IAM::Role',
        'AWS::IAM::ManagedPolicy',
        'AWS::IAM::Policy',
        'AWS::SecretsManager::Secret',
        'AWS::CloudTrail::Trail',
        'AWS::S3::Bucket',
        'AWS::Logs::LogGroup',
        'AWS::EC2::VPC',
        'AWS::ECS::Cluster',
        'AWS::ECS::TaskDefinition',
      ];
      for (const type of forbidden) expect(ofType(type)).toHaveLength(0);
    });

    test('passes the unmodified Registry aspect', () => {
      AssertionAnnotations.fromStack(registry).hasNoError(
        '*',
        Match.stringLikeRegexp('MXMED_REGISTRY_'),
      );
    });
  },
);

describe('RegistryFoundationAspect fail-closed mutations', () => {
  test('rejects a mutable repository', () => {
    const registry = requireRegistry(createStage());
    const repository = findConstruct(registry, CfnRepository);
    repository.imageTagMutability = 'MUTABLE';
    AssertionAnnotations.fromStack(registry).hasError(
      '*',
      Match.stringLikeRegexp('MXMED_REGISTRY_ECR_CONTRACT_INVALID'),
    );
  });

  test('rejects key-rotation drift', () => {
    const registry = requireRegistry(createStage());
    const key = findConstruct(registry, CfnKey);
    key.enableKeyRotation = false;
    AssertionAnnotations.fromStack(registry).hasError(
      '*',
      Match.stringLikeRegexp('MXMED_REGISTRY_KEY_CONTRACT_INVALID'),
    );
  });
});
